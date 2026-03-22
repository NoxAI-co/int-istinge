<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Instance;
use App\WhatsAppConversation;
use App\WhatsAppMessage;
use App\Services\CentralizedWhatsAppService;
use App\Model\Ingresos\Factura;
use App\Model\Ingresos\Ingreso;
use App\Contrato;
use Auth;

class ChatController extends Controller
{
    private $centralizedService;

    public function __construct(CentralizedWhatsAppService $centralizedService)
    {
        $this->middleware('auth');
        $this->centralizedService = $centralizedService;
    }

    /**
     * Vista principal del chat
     */
    public function index()
    {
        $this->getAllPermissions(Auth::user()->id);
        $user = auth()->user();

        // Obtener instancias activas de Meta para esta empresa
        // type=1, meta=0 es la nueva configuración para Meta Direct
        $instances = Instance::where('company_id', $user->empresa)
            ->where('type', 1)
            ->where('meta', 0)
            ->where('activo', true)
            ->get();

        if($instances->count() > 0 && $instances->first()->waba_id == "875445451477896"){
            return redirect()->route('home')->with('error', 'La instancia no tiene un portafolio personalizado');
        }

        \Log::info('ChatController::index instances', ['count' => $instances->count(), 'sample' => $instances->first()]);

        return view('chat.index', compact('instances'))
            ->with('title', 'Chat WhatsApp Meta')
            ->with('icon', 'fab fa-whatsapp')
            ->with('seccion', 'CRM')
            ->with('subseccion', 'chat_whatsapp_meta');
    }

    /**
     * Listar conversaciones desde API centralizada
     */
    public function conversations(Request $request)
    {
        $user = auth()->user();
        $instanceId = $request->instance_id;

        if (!$instanceId) {
            return response()->json(['error' => 'instance_id es requerido'], 400);
        }

        // Verificar que la instancia pertenece a la empresa del usuario
        $instance = Instance::where('id', $instanceId)
            ->where('company_id', $user->empresa)
            ->firstOrFail();

        if (empty($instance->phone_number_id)) {
            return response()->json(['error' => 'La instancia no tiene configurado un ID de WhatsApp (phone_number_id)'], 400);
        }

        \Log::info('ChatController::conversations request (Centralized)', [
            'instance_id' => $instanceId,
            'phone_number_id' => $instance->phone_number_id
        ]);

        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 20);

        // Consumir API Centralizada
        $response = $this->centralizedService->getConversations(
            $instance->phone_number_id,
            $page,
            $perPage
        );

        // Si hay error en la petición externa, devolverlo con código apropiado
        if (isset($response['errorMessage'])) {
            return response()->json([
                'success' => false,
                'error' => $response['errorMessage'],
                'data' => []
            ], $response['statusCode'] ?? 500);
        }

        if (isset($response['data']) && count($response['data']) > 0) {
            foreach ($response['data'] as &$conv) {
                $phone = preg_replace('/[^0-9]/', '', $conv['phone_number'] ?? '');
                $hasWarning = false;
                if ($phone) {
                    $hasWarning = \App\Model\Ingresos\Factura::join('contracts as c', 'c.id', '=', 'factura.contrato_id')
                        ->join('contactos as con', 'con.id', 'c.client_id')
                        ->where(function($q) use ($phone) {
                            $q->where('con.celular', 'LIKE', '%' . $phone . '%')
                              ->orWhere('con.telefono1', 'LIKE', '%' . $phone . '%');
                        })
                        ->where('factura.cont_message_undeliverable', '>=', 3)
                        ->exists();

                    if (!$hasWarning) {
                        $hasWarning = \App\Model\Ingresos\Ingreso::join('contactos as con', 'con.id', 'ingresos.cliente')
                            ->where(function($q) use ($phone) {
                                $q->where('con.celular', 'LIKE', '%' . $phone . '%')
                                  ->orWhere('con.telefono1', 'LIKE', '%' . $phone . '%');
                            })
                            ->where('ingresos.cont_message_undeliverable', '>=', 3)
                            ->exists();
                    }
                }
                $conv['has_undeliverable_warning'] = $hasWarning;
            }
            \Log::debug('ChatController::conversations sample item', ['item' => $response['data'][0]]);
        }

        return response()->json($response);
    }

    /**
     * Obtener actualizaciones (para polling) - Ajustado para API centralizada o mantenido si el frontend lo requiere
     * NOTA: La API centralizada no parece tener un endpoint de 'updates' específico por timestamp.
     * Podríamos re-consultar conversaciones o implementar algo similar.
     * Por ahora devolvemos vacío o re-listamos conversaciones.
     */
    public function updates(Request $request)
    {
        $user = auth()->user();
        $instanceId = $request->instance_id;
        $conversationId = $request->conversation_id;

        if (!$instanceId) {
            return response()->json(['error' => 'instance_id es requerido'], 400);
        }

        $instance = Instance::where('id', $instanceId)
            ->where('company_id', $user->empresa)
            ->firstOrFail();

        if (empty($instance->phone_number_id)) {
            return response()->json(['error' => 'La instancia no tiene configurado un ID de WhatsApp (phone_number_id)'], 400);
        }

        $newMessages = [];
        $updatedConversations = [];

        // 1. Si hay conversación seleccionada, obtener mensajes recientes
        if ($conversationId) {
            $messagesResponse = $this->centralizedService->getMessages(
                $instance->phone_number_id,
                $conversationId,
                1, // Página 1
                20 // Últimos 20 mensajes
            );

            if (isset($messagesResponse['data'])) {
                $newMessages = $messagesResponse['data'];
            }
        }

        // 2. Obtener lista de conversaciones reciente (para actualizar últimos mensajes/orden)
        $conversationsResponse = $this->centralizedService->getConversations(
            $instance->phone_number_id,
            1,
            20
        );

        if (isset($conversationsResponse['data'])) {
            $updatedConversations = $conversationsResponse['data'];
        }

        return response()->json([
            'conversations'     => $updatedConversations,
            'new_messages'      => $newMessages,
            'updated_statuses'  => [], // Por ahora vacío, la API no devuelve estados separados
            'timestamp'         => now()->toIso8601String()
        ]);
    }

    /**
     * Obtener mensajes de una conversación desde API centralizada
     */
    public function messages(Request $request, $conversationId)
    {
        $user = auth()->user();
        $instanceId = $request->instance_id;

        if (!$instanceId) {
            return response()->json(['error' => 'instance_id es requerido para identificar el token'], 400);
        }

        $instance = Instance::where('id', $instanceId)
            ->where('company_id', $user->empresa)
            ->firstOrFail();

        if (empty($instance->phone_number_id)) {
            return response()->json(['error' => 'La instancia no tiene configurado un ID de WhatsApp (phone_number_id)'], 400);
        }

        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 50);

        \Log::info('ChatController::messages request', [
            'instance_id' => $instanceId,
            'conversation_id' => $conversationId,
            'phone_number_id' => $instance->phone_number_id
        ]);

        $response = $this->centralizedService->getMessages(
            $instance->phone_number_id,
            $conversationId,
            $page,
            $perPage
        );

        if (isset($response['errorMessage'])) {
            \Log::error('ChatController::messages API error', [
                'error' => $response['errorMessage'],
                'th' => $response['th'] ?? null,
                'status' => $response['statusCode'] ?? null
            ]);
            return response()->json([
                'success' => false,
                'error' => $response['errorMessage'],
                'data' => []
            ], $response['statusCode'] ?? 500);
        }

        // Enriquecer mensajes con datos de facturas, contratos e ingresos
        if (isset($response['data']) && is_array($response['data'])) {
            $response['data'] = $this->enrichMessagesWithRelations($response['data']);
        }

        return response()->json($response);
    }

    /**
     * Enviar mensaje de texto vía API centralizada
     */
    public function sendMessage(Request $request, $conversationId)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:4096'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = auth()->user();
        $instanceId = $request->instance_id;

        if (!$instanceId) {
            return response()->json(['error' => 'instance_id es requerido'], 400);
        }

        $instance = Instance::where('id', $instanceId)
            ->where('company_id', $user->empresa)
            ->firstOrFail();

        if (empty($instance->phone_number_id)) {
            return response()->json(['error' => 'La instancia no tiene configurado un ID de WhatsApp (phone_number_id)'], 400);
        }

        // Enviar mensaje vía API Centralizada
        $result = $this->centralizedService->sendMessage(
            $instance->phone_number_id,
            $conversationId, // phone_number
            $request->message
        );

        \Log::info('ChatController::sendMessage result', ['result' => $result]);

        if (isset($result['errorMessage'])) {
            return response()->json([
                'success' => false,
                'error'   => $result['errorMessage']
            ], $result['statusCode'] ?? 500);
        }

        // Si el API devuelve success:true y data, lo usamos.
        // Si no, asumimos que el resultado mismo es el objeto del mensaje.
        $data = $result;
        if (isset($result['success']) && $result['success'] && isset($result['data'])) {
            $data = $result['data'];
        }

        // Asegurar que exista created_at para evitar "Invalid Date" en frontend
        if (!isset($data['created_at'])) {
            $data['created_at'] = now()->toIso8601String();
        }

        return response()->json([
            'success' => true,
            'message' => 'Mensaje enviado',
            'data'    => $data
        ]);
    }

    /**
     * Enviar imagen (Ajustado para API centralizada)
     * NOTA: La API centralizada documentada solo muestra /messages/send para texto.
     * Si no hay endpoint de media, registraremos el mensaje o daremos error informativo.
     */
    public function sendImage(Request $request, $conversationId)
    {
        return response()->json([
            'success' => false,
            'error'   => 'El envío de imágenes no está disponible actualmente en la API centralizada.'
        ], 501);
    }

    /**
     * Asignar conversación
     */
    public function assign(Request $request, $conversationId)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = auth()->user();

        $conversation = WhatsAppConversation::with('instance')
            ->findOrFail($conversationId);

        if ($conversation->instance->company_id !== $user->empresa) {
            abort(403, 'No autorizado');
        }

        $conversation->update(['assigned_to' => $request->user_id]);

        return response()->json([
            'success' => true,
            'message' => 'Conversación asignada'
        ]);
    }

    /**
     * Cerrar conversación
     */
    public function close($conversationId)
    {
        $user = auth()->user();

        $conversation = WhatsAppConversation::with('instance')
            ->findOrFail($conversationId);

        if ($conversation->instance->company_id !== $user->empresa) {
            abort(403, 'No autorizado');
        }

        $conversation->update(['status' => 'closed']);

        return response()->json([
            'success' => true,
            'message' => 'Conversación cerrada'
        ]);
    }

    /**
     * Enriquecer mensajes con datos de facturas, contratos e ingresos
     */
    private function enrichMessagesWithRelations(array $messages)
    {
        // 1. Calcular estado agregado por documento (factura / ingreso) a partir de TODOS los mensajes
        $statusPriority = [
            'failed'    => 0,
            'error'     => 0,
            'sent'      => 1,
            'success'   => 1,
            'delivered' => 2,
            'read'      => 3,
        ];

        $facturasStatus = [];
        $ingresosStatus = [];

        foreach ($messages as $message) {
            $status = $message['status'] ?? null;
            if (!$status || !isset($statusPriority[$status])) {
                continue;
            }

            $score = $statusPriority[$status];

            if (isset($message['incoming_invoice_id']) && $message['incoming_invoice_id']) {
                $id = $message['incoming_invoice_id'];
                if (!isset($facturasStatus[$id]) || $score > $statusPriority[$facturasStatus[$id]]) {
                    $facturasStatus[$id] = $status;
                }
            }

            if (isset($message['incoming_payment_id']) && $message['incoming_payment_id']) {
                $id = $message['incoming_payment_id'];
                if (!isset($ingresosStatus[$id]) || $score > $statusPriority[$ingresosStatus[$id]]) {
                    $ingresosStatus[$id] = $status;
                }
            }
        }

        foreach ($messages as &$message) {
            // Agregar información de factura si existe
            if (isset($message['incoming_invoice_id']) && $message['incoming_invoice_id']) {
                $factura = Factura::find($message['incoming_invoice_id']);
                if ($factura) {
                    $status = $facturasStatus[$factura->id] ?? null;
                    $statusLabel = 'Sin estado';
                    $statusClass = 'error';

                    if ($status === 'read') {
                        $statusLabel = 'Leído';
                        $statusClass = 'success';
                    } elseif ($status === 'delivered') {
                        $statusLabel = 'Entregado';
                        $statusClass = 'success';
                    } elseif (in_array($status, ['sent', 'success'], true)) {
                        $statusLabel = 'Enviado';
                        $statusClass = 'warning';
                    } elseif ($status === 'failed' || $status === 'error') {
                        $statusLabel = 'Fallido';
                        $statusClass = 'error';
                    }

                    $message['factura'] = [
                        'id'                     => $factura->id,
                        'codigo'                 => $factura->codigo,
                        'url'                    => route('facturas.show', $factura->id),
                        'whatsapp_status'        => $status,
                        'whatsapp_status_label'  => $statusLabel,
                        'whatsapp_status_class'  => $statusClass,
                    ];
                }
            }

            // Agregar información de contrato si existe
            if (isset($message['incoming_contract_id']) && $message['incoming_contract_id']) {
                $contrato = Contrato::find($message['incoming_contract_id']);
                if ($contrato) {
                    $message['contrato'] = [
                        'id' => $contrato->id,
                        'nro' => $contrato->nro,
                        'url' => route('contratos.show', $contrato->id)
                    ];
                }
            }

            // Agregar información de ingreso si existe
            if (isset($message['incoming_payment_id']) && $message['incoming_payment_id']) {
                $ingreso = Ingreso::find($message['incoming_payment_id']);
                if ($ingreso) {
                    $status = $ingresosStatus[$ingreso->id] ?? null;
                    $statusLabel = 'Sin estado';
                    $statusClass = 'error';

                    if ($status === 'read') {
                        $statusLabel = 'Leído';
                        $statusClass = 'success';
                    } elseif ($status === 'delivered') {
                        $statusLabel = 'Entregado';
                        $statusClass = 'success';
                    } elseif (in_array($status, ['sent', 'success'], true)) {
                        $statusLabel = 'Enviado';
                        $statusClass = 'warning';
                    } elseif ($status === 'failed' || $status === 'error') {
                        $statusLabel = 'Fallido';
                        $statusClass = 'error';
                    }

                    $message['ingreso'] = [
                        'id'                     => $ingreso->id,
                        'nro'                    => $ingreso->nro,
                        'url'                    => route('ingresos.show', $ingreso->id),
                        'whatsapp_status'        => $status,
                        'whatsapp_status_label'  => $statusLabel,
                        'whatsapp_status_class'  => $statusClass,
                    ];
                }
            }
        }

        return $messages;
    }
}
