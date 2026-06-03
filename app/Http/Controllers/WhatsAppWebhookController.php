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

        $verifyToken = env('WHATSAPP_WEBHOOK_VERIFY_TOKEN', 'tu_token_de_verificacion');

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
            $errorMessage = 'Error desconocido';
            if (isset($status['errors']) && count($status['errors']) > 0) {
                $errorMessage = $status['errors'][0]['message'] ?? $errorMessage;
            }
            $updateData['error_message'] = $errorMessage;
        }

        $message->update($updateData);

        Log::info('✅ Estado de mensaje actualizado', [
            'wamid'  => $wamid,
            'status' => $newStatus
        ]);
    }
}
