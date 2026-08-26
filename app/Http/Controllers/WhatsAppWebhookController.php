<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Instance;
use App\WhatsAppConversation;
use App\WhatsAppMessage;
use App\Services\MetaWhatsAppService;
use Carbon\Carbon;

class WhatsAppWebhookController extends Controller
{
    private $metaService;

    /**
     * Códigos de error de la Cloud API → explicación legible.
     *
     * Un envío puede devolver HTTP 200 y fallar después; el motivo sólo llega
     * por webhook. Sin traducir el código, `error_message` guardaba textos de
     * Meta en inglés que no decían qué hacer.
     *
     * @see https://developers.facebook.com/docs/whatsapp/cloud-api/support/error-codes
     */
    private const ERRORES_META = [
        // Del destinatario: reintentar no sirve de nada.
        '131026' => 'El número no tiene WhatsApp o no puede recibir el mensaje',
        '133010' => 'El número no está registrado en WhatsApp',
        '131051' => 'Tipo de mensaje no soportado por el destinatario',

        // Ventana de 24 h: hay que reintentar con plantilla, no con texto libre.
        '131047' => 'Fuera de la ventana de 24 h: solo se admiten plantillas',
        '470'    => 'Fuera de la ventana de 24 h: solo se admiten plantillas',

        // De la plantilla: corregirla antes de reintentar.
        '132000' => 'La cantidad de parámetros no coincide con la plantilla',
        '132001' => 'La plantilla no existe o no está aprobada en este idioma',
        '132005' => 'El texto traducido de la plantilla excede el límite',
        '132007' => 'Formato de parámetro incorrecto para la plantilla',
        '132012' => 'Valor de parámetro inválido para la plantilla',
        '132015' => 'La plantilla está pausada por baja calidad',
        '132016' => 'La plantilla fue deshabilitada por Meta',
        '131008' => 'Falta un parámetro obligatorio del mensaje',
        '131009' => 'Un valor del mensaje no cumple el formato exigido',

        // De la cuenta: requieren acción del administrador.
        '131031' => 'La cuenta de WhatsApp Business está restringida',
        '131042' => 'Problema con el método de pago de la cuenta de Meta',
        '190'    => 'El token de acceso expiró: reconfigura la instancia',
        '368'    => 'Cuenta bloqueada temporalmente por políticas de Meta',
        '100'    => 'Parámetro inválido en la petición a Meta',
        '33'     => 'El número de la instancia no existe o no es accesible',

        // Transitorios: reintentar tiene sentido.
        '130429' => 'Límite de envíos de Meta alcanzado: reintenta más tarde',
        '80007'  => 'Límite de la cuenta alcanzado: reintenta más tarde',
        '131016' => 'Servicio de Meta no disponible temporalmente',
        '131000' => 'Error interno de Meta',
        '131053' => 'Meta no pudo procesar el archivo adjunto',
    ];

    public function __construct(MetaWhatsAppService $metaService)
    {
        $this->metaService = $metaService;
    }

    /**
     * Verificación GET de Meta
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        // Con `config:cache` (lo corre docker/start.sh al arrancar) env() devuelve
        // null en runtime, así que esto caía al literal 'tu_token_de_verificacion'
        // y la verificación en Meta sólo pasaba con ese valor de ejemplo.
        $verifyToken = config('services.meta.verify_token');

        if (empty($verifyToken)) {
            Log::error('❌ WHATSAPP_WEBHOOK_VERIFY_TOKEN sin configurar: no se puede verificar el webhook');

            return response('Forbidden', 403);
        }

        if ($mode === 'subscribe' && $token === $verifyToken) {
            Log::info('✅ Webhook verificado exitosamente');
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('❌ Intento de verificación fallido', [
            'mode'  => $mode,
            'token' => $token
        ]);

        return response('Forbidden', 403);
    }

    /**
     * Recibir eventos POST de Meta
     */
    public function webhook(Request $request)
    {
        // La firma se calcula sobre el cuerpo crudo: $request->all() ya viene
        // decodificado y re-serializarlo no reproduce byte a byte lo que firmó Meta.
        if (!$this->metaService->validateWebhookSignature($request->getContent(), $request->header('X-Hub-Signature-256'))) {
            Log::warning('❌ Webhook rechazado: firma inválida', [
                'ip'          => $request->ip(),
                'tiene_firma' => $request->header('X-Hub-Signature-256') !== null,
            ]);

            return response('Forbidden', 403);
        }

        $data = $request->all();

        Log::info('📩 Webhook recibido de Meta', ['data' => $data]);

        try {
            if (isset($data['entry'])) {
                foreach ($data['entry'] as $entry) {
                    foreach ($entry['changes'] as $change) {
                        if ($change['field'] === 'messages') {
                            $this->processChange($change['value']);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('❌ Error procesando webhook', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Procesar cambios del webhook
     */
    private function processChange($value)
    {
        $metadata = $value['metadata'];
        $phoneNumberId = $metadata['phone_number_id'];

        // Buscar la instancia correspondiente
        $instance = Instance::where('phone_number_id', $phoneNumberId)
            ->where('activo', true)
            ->first();

        if (!$instance) {
            Log::warning('⚠️ No se encontró instancia para phone_number_id', [
                'phone_number_id' => $phoneNumberId
            ]);
            return;
        }

        // Configurar el token de la instancia específica en el servicio
        if (!empty($instance->access_token)) {
            $this->metaService->setAccessToken($instance->access_token);
        }

        // Mensajes recibidos
        if (isset($value['messages'])) {
            foreach ($value['messages'] as $message) {
                $this->processInboundMessage($message, $instance, $value);
            }
        }

        // Estados de mensajes enviados
        if (isset($value['statuses'])) {
            foreach ($value['statuses'] as $status) {
                $this->updateMessageStatus($status, $instance);
            }
        }
    }

    /**
     * Procesar mensaje entrante
     */
    private function processInboundMessage($message, Instance $instance, $metadata)
    {
        $from = $message['from'];
        $wamid = $message['id'];
        $timestamp = $message['timestamp'];

        // Obtener nombre del contacto
        $contactName = 'Desconocido';
        if (isset($metadata['contacts']) && count($metadata['contacts']) > 0) {
            $contactName = $metadata['contacts'][0]['profile']['name'] ?? $from;
        }

        // Buscar o crear conversación
        $conversation = WhatsAppConversation::firstOrCreate(
            [
                'instance_id' => $instance->id,
                'wa_id'       => $from
            ],
            [
                'phone_number'     => $from,
                'name'             => $contactName,
                'status'           => 'open',
                'last_message_at'  => now()
            ]
        );

        // Verificar si el mensaje ya existe (evitar duplicados)
        $existingMessage = WhatsAppMessage::where('wamid', $wamid)->first();
        if ($existingMessage) {
            Log::info('ℹ️ Mensaje duplicado, ignorando', ['wamid' => $wamid]);
            return;
        }

        // Preparar datos del mensaje
        $messageData = [
            'conversation_id' => $conversation->id,
            'wamid'           => $wamid,
            'direction'       => 'inbound',
            'status'          => 'delivered',
            'sent_at'         => Carbon::createFromTimestamp($timestamp)
        ];

        // Según tipo de mensaje
        switch ($message['type']) {
            case 'text':
                $messageData['type'] = 'text';
                $messageData['content'] = $message['text']['body'];
                break;

            case 'image':
                $messageData['type'] = 'image';
                $messageData['media_id'] = $message['image']['id'];
                $messageData['media_mime_type'] = $message['image']['mime_type'];
                $messageData['content'] = $message['image']['caption'] ?? '';

                // Descargar imagen
                $mediaInfo = $this->metaService->downloadMedia($message['image']['id']);
                if ($mediaInfo) {
                    $messageData['media_url'] = $mediaInfo['url'];
                    $messageData['filename'] = $mediaInfo['filename'];
                }
                break;

            case 'document':
                $messageData['type'] = 'document';
                $messageData['media_id'] = $message['document']['id'];
                $messageData['media_mime_type'] = $message['document']['mime_type'];
                $messageData['filename'] = $message['document']['filename'] ?? 'document';

                // Descargar documento
                $mediaInfo = $this->metaService->downloadMedia($message['document']['id']);
                if ($mediaInfo) {
                    $messageData['media_url'] = $mediaInfo['url'];
                    $messageData['filename'] = $mediaInfo['filename'] ?? $mediaInfo['filename'];
                }
                break;

            case 'audio':
                $messageData['type'] = 'audio';
                $messageData['media_id'] = $message['audio']['id'];
                $messageData['media_mime_type'] = $message['audio']['mime_type'];

                // Descargar audio
                $mediaInfo = $this->metaService->downloadMedia($message['audio']['id']);
                if ($mediaInfo) {
                    $messageData['media_url'] = $mediaInfo['url'];
                    $messageData['filename'] = $mediaInfo['filename'];
                }
                break;

            case 'video':
                $messageData['type'] = 'video';
                $messageData['media_id'] = $message['video']['id'];
                $messageData['media_mime_type'] = $message['video']['mime_type'];
                $messageData['content'] = $message['video']['caption'] ?? '';

                // Descargar video
                $mediaInfo = $this->metaService->downloadMedia($message['video']['id']);
                if ($mediaInfo) {
                    $messageData['media_url'] = $mediaInfo['url'];
                    $messageData['filename'] = $mediaInfo['filename'];
                }
                break;

            case 'sticker':
                $messageData['type'] = 'sticker';
                $messageData['media_id'] = $message['sticker']['id'];
                $messageData['media_mime_type'] = $message['sticker']['mime_type'];
                $messageData['content'] = '🎨 Sticker';

                // Descargar sticker
                $mediaInfo = $this->metaService->downloadMedia($message['sticker']['id']);
                if ($mediaInfo) {
                    $messageData['media_url'] = $mediaInfo['url'];
                }
                break;

            case 'location':
                // Ubicación compartida: se guarda el payload en metadata para
                // que el chat pueda mostrar nombre, dirección y mapa.
                $location = $message['location'] ?? [];
                $messageData['type'] = 'location';
                $messageData['content'] = trim(implode(' — ', array_filter([
                    $location['name'] ?? null,
                    $location['address'] ?? null,
                ]))) ?: '📍 Ubicación compartida';
                $messageData['metadata'] = ['location' => $location];
                break;

            case 'contacts':
                // Tarjetas de contacto compartidas. Se guarda el payload completo
                // en metadata para que el chat pueda mostrar nombre(s) y teléfono(s).
                $contacts = $message['contacts'] ?? [];
                $names = array_values(array_filter(array_map(
                    fn ($c) => $c['name']['formatted_name'] ?? ($c['phones'][0]['phone'] ?? null),
                    $contacts
                )));
                $messageData['type'] = 'contacts';
                $messageData['content'] = $names ? implode(', ', $names) : '👤 Contacto compartido';
                $messageData['metadata'] = ['contacts' => $contacts];
                break;

            case 'interactive':
                // Respuesta a botones o listas interactivas: se guarda como texto
                // con el título de la opción elegida.
                $interactive = $message['interactive'] ?? [];
                $reply = $interactive['button_reply'] ?? $interactive['list_reply'] ?? [];
                $messageData['type'] = 'text';
                $messageData['content'] = $reply['title'] ?? 'Respuesta interactiva';
                $messageData['metadata'] = ['interactive' => $interactive];
                break;

            case 'button':
                // Respuesta a un botón rápido de plantilla.
                $messageData['type'] = 'text';
                $messageData['content'] = $message['button']['text'] ?? 'Botón';
                $messageData['metadata'] = ['button' => $message['button'] ?? []];
                break;

            default:
                $messageData['type'] = 'text';
                $messageData['content'] = "Tipo de mensaje no soportado: {$message['type']}";
                Log::warning('⚠️ Tipo de mensaje no soportado', [
                    'type' => $message['type'],
                    'message' => $message
                ]);
        }

        // Guardar mensaje
        $savedMessage = WhatsAppMessage::create($messageData);

        // Actualizar conversación
        $conversation->update([
            'last_message'    => $messageData['content'] ?? 'Media',
            'last_message_at' => now()
        ]);
        $conversation->incrementUnread();

        // Marcar como leído en WhatsApp
        $this->metaService->markAsRead($instance->phone_number_id, $wamid);

        Log::info('✅ Mensaje procesado', [
            'message_id'      => $savedMessage->id,
            'conversation_id' => $conversation->id,
            'type'            => $messageData['type']
        ]);
    }

    /**
     * Actualizar estado de mensaje
     */
    private function updateMessageStatus($status, Instance $instance)
    {
        $wamid = $status['id'];
        $newStatus = $status['status']; // sent, delivered, read, failed

        $message = WhatsAppMessage::where('wamid', $wamid)->first();

        if (!$message) {
            Log::info('ℹ️ Mensaje no encontrado para actualizar estado', ['wamid' => $wamid]);
            return;
        }

        $updateData = ['status' => $newStatus];

        if ($newStatus === 'delivered') {
            $updateData['delivered_at'] = now();
        } elseif ($newStatus === 'read') {
            $updateData['read_at'] = now();
        } elseif ($newStatus === 'failed') {
            $error = isset($status['errors'][0]) ? $status['errors'][0] : [];
            $code = isset($error['code']) ? (string) $error['code'] : null;

            // Meta responde 200 con wamid aunque el envío vaya a fallar; el
            // fallo real sólo llega por este webhook. Guardar el código además
            // del texto permite saber si tiene sentido reintentar (y con qué):
            // 131047 significa que hay que mandar plantilla, no texto libre.
            $explicacion = $code !== null && isset(self::ERRORES_META[$code])
                ? self::ERRORES_META[$code]
                : ($error['message'] ?? 'Error desconocido');

            $updateData['error_message'] = $code !== null
                ? "[{$code}] {$explicacion}"
                : $explicacion;

            Log::warning('⚠️ Meta reportó un envío fallido', [
                'wamid'   => $wamid,
                'code'    => $code,
                'detalle' => $explicacion,
                'titulo'  => $error['title'] ?? null,
            ]);
        }

        $message->update($updateData);

        Log::info('✅ Estado de mensaje actualizado', [
            'wamid'  => $wamid,
            'status' => $newStatus
        ]);
    }
}
