<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Instance;
use App\Services\CentralizedWhatsAppService;
use App\Model\Ingresos\Factura;
use App\Model\Ingresos\Ingreso;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class UpdateWhatsAppMessageStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:update-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza el campo whatsapp de facturas e ingresos según el status de los mensajes';

    protected $centralizedService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(CentralizedWhatsAppService $centralizedService)
    {
        parent::__construct();
        $this->centralizedService = $centralizedService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Iniciando actualización de status WhatsApp...');

        // Obtener todas las instancias activas
        $instances = Instance::where('activo', true)
            ->whereNotNull('phone_number_id')
            ->get();

        if ($instances->isEmpty()) {
            $this->warn('No se encontraron instancias activas.');
            return 0;
        }

        $totalFacturasActualizadas = 0;
        $totalIngresosActualizados = 0;

        foreach ($instances as $instance) {
            $this->info("Procesando instancia: {$instance->phone_number_id}");

            try {
                $processedMessages = 0;
                $conversationPage = 1;
                $hasMoreConversations = true;
                $maxConversationPages = 20; // Límite de seguridad: máximo 20 páginas (20 * 50 = 1000 conversaciones)

                // Consultar todas las conversaciones paginadas
                while ($hasMoreConversations && $conversationPage <= $maxConversationPages) {
                    $this->line("  Consultando página {$conversationPage} de conversaciones...");
                    
                    $conversationsResponse = $this->centralizedService->getConversations(
                        $instance->phone_number_id,
                        $conversationPage,
                        50 // 50 conversaciones por página
                    );

                    if (isset($conversationsResponse['errorMessage'])) {
                        $this->error("Error consultando conversaciones página {$conversationPage}: {$conversationsResponse['errorMessage']}");
                        break;
                    }

                    $conversations = $conversationsResponse['data'] ?? [];
                    
                    if (empty($conversations)) {
                        $hasMoreConversations = false;
                        break;
                    }

                    // Verificar si hay más páginas
                    $meta = $conversationsResponse['meta'] ?? [];
                    $lastPage = $meta['last_page'] ?? $meta['lastPage'] ?? 1;
                    $hasMoreConversations = $conversationPage < $lastPage;

                    // Para cada conversación, obtener todos sus mensajes paginados
                    foreach ($conversations as $conversation) {
                        $conversationId = $conversation['id'] ?? null;
                        if (!$conversationId) {
                            continue;
                        }

                        $messagePage = 1;
                        $hasMoreMessages = true;
                        $maxMessagePages = 10; // Límite de seguridad: máximo 10 páginas (10 * 100 = 1000 mensajes por conversación)

                        // Consultar todos los mensajes de la conversación paginados
                        while ($hasMoreMessages && $messagePage <= $maxMessagePages) {
                            $messagesResponse = $this->centralizedService->getMessages(
                                $instance->phone_number_id,
                                $conversationId,
                                $messagePage,
                                100 // 100 mensajes por página
                            );

                            if (isset($messagesResponse['errorMessage'])) {
                                $this->warn("  Error consultando mensajes de conversación {$conversationId}, página {$messagePage}: {$messagesResponse['errorMessage']}");
                                break;
                            }

                            $messages = $messagesResponse['data'] ?? [];

                            if (empty($messages)) {
                                $hasMoreMessages = false;
                                break;
                            }

                            // Verificar si hay más páginas de mensajes
                            $messageMeta = $messagesResponse['meta'] ?? [];
                            $messageLastPage = $messageMeta['last_page'] ?? $messageMeta['lastPage'] ?? 1;
                            $hasMoreMessages = $messagePage < $messageLastPage;

                            foreach ($messages as $message) {
                                // Solo procesar mensajes que tengan relación con facturas o ingresos
                                if (isset($message['incoming_invoice_id']) || isset($message['incoming_payment_id'])) {
                                    $result = $this->processMessage($message, $instance);
                                    if ($result['factura_actualizada']) {
                                        $totalFacturasActualizadas++;
                                    }
                                    if ($result['ingreso_actualizado']) {
                                        $totalIngresosActualizados++;
                                    }
                                    $processedMessages++;
                                    
                                    Log::debug('Mensaje procesado en UpdateWhatsAppMessageStatus', [
                                        'conversation_id' => $conversationId,
                                        'message_id' => $message['id'] ?? null,
                                        'incoming_invoice_id' => $message['incoming_invoice_id'] ?? null,
                                        'incoming_payment_id' => $message['incoming_payment_id'] ?? null,
                                        'status' => $message['status'] ?? null,
                                        'factura_actualizada' => $result['factura_actualizada'],
                                        'ingreso_actualizado' => $result['ingreso_actualizado']
                                    ]);
                                }
                            }

                            $messagePage++;
                        }
                    }

                    $conversationPage++;
                }

                $this->info("Procesados {$processedMessages} mensajes para la instancia {$instance->phone_number_id}");

            } catch (\Exception $e) {
                $this->error("Error procesando instancia {$instance->phone_number_id}: " . $e->getMessage());
                Log::error('Error en UpdateWhatsAppMessageStatus', [
                    'instance_id' => $instance->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        $this->info("Actualización completada. Facturas: {$totalFacturasActualizadas}, Ingresos: {$totalIngresosActualizados}");
        return 0;
    }

    /**
     * Procesar un mensaje y actualizar facturas/ingresos según su status
     * 
     * @return array ['factura_actualizada' => bool, 'ingreso_actualizado' => bool]
     */
    private function processMessage($message, Instance $instance)
    {
        $status = $message['status'] ?? 'sent';
        $errorMessage = $message['error_message'] ?? null;
        $incomingInvoiceId = $message['incoming_invoice_id'] ?? null;
        $incomingPaymentId = $message['incoming_payment_id'] ?? null;

        // Verificar si es el error específico de Meta sobre healthy ecosystem engagement
        // En este caso, no actualizamos whatsapp = 0 porque el primer mensaje pudo haberse enviado bien
        $isHealthyEcosystemError = $errorMessage && 
            stripos($errorMessage, 'This message was not delivered to maintain healthy ecosystem engagement') !== false;

        // Solo marcar whatsapp = 1 si el status es "delivered" o "read"
        // NO marcar whatsapp = 0 para otros status (sent, failed, etc.)
        // EXCEPCIÓN: Si es el error de healthy ecosystem, no hacer nada
        if ($isHealthyEcosystemError && ($status === 'failed' || $status === 'sent')) {
            // No actualizar en este caso, mantener el valor actual
            return [
                'factura_actualizada' => false,
                'ingreso_actualizado' => false
            ];
        }

        $result = [
            'factura_actualizada' => false,
            'ingreso_actualizado' => false
        ];

        // Determinar el valor de whatsapp según el status
        if ($status === 'delivered' || $status === 'read') {
            $whatsappValue = 1; // Entregado/leído
        } elseif ($status === 'failed' && !$isHealthyEcosystemError) {
            $whatsappValue = 0; // Fallido (no entregado), excepto si es error de healthy ecosystem
        } else {
            // Para otros estados (sent, pending, etc.) no actualizamos
            return $result;
        }

        // Actualizar factura si existe
        if ($incomingInvoiceId) {
            try {
                $factura = Factura::find($incomingInvoiceId);
                if ($factura) {
                    // Solo actualizar si el valor es diferente
                    if ($factura->whatsapp != $whatsappValue) {
                        DB::table('factura')
                            ->where('id', $incomingInvoiceId)
                            ->update(['whatsapp' => $whatsappValue]);
                        
                        $this->line("  Factura {$factura->codigo} actualizada: whatsapp = {$whatsappValue} (status: {$status})");
                        $result['factura_actualizada'] = true;
                    }
                }
            } catch (\Exception $e) {
                $this->error("  Error actualizando factura {$incomingInvoiceId}: " . $e->getMessage());
                Log::error('Error actualizando factura en UpdateWhatsAppMessageStatus', [
                    'factura_id' => $incomingInvoiceId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Actualizar ingreso si existe
        if ($incomingPaymentId) {
            try {
                $ingreso = Ingreso::find($incomingPaymentId);
                if ($ingreso) {
                    // Solo actualizar si el valor es diferente
                    if ($ingreso->whatsapp != $whatsappValue) {
                        DB::table('ingresos')
                            ->where('id', $incomingPaymentId)
                            ->update(['whatsapp' => $whatsappValue]);
                        
                        $this->line("  Ingreso {$ingreso->nro} actualizado: whatsapp = {$whatsappValue} (status: {$status})");
                        $result['ingreso_actualizado'] = true;
                    }
                }
            } catch (\Exception $e) {
                $this->error("  Error actualizando ingreso {$incomingPaymentId}: " . $e->getMessage());
                Log::error('Error actualizando ingreso en UpdateWhatsAppMessageStatus', [
                    'ingreso_id' => $incomingPaymentId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $result;
    }
}
