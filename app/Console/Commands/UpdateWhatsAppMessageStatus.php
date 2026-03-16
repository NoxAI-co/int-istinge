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
                // Consultar conversaciones recientes
                $conversationsResponse = $this->centralizedService->getConversations(
                    $instance->phone_number_id,
                    1,
                    50 // Obtener últimas 50 conversaciones
                );

                if (isset($conversationsResponse['errorMessage'])) {
                    $this->error("Error consultando conversaciones: {$conversationsResponse['errorMessage']}");
                    continue;
                }

                $conversations = $conversationsResponse['data'] ?? [];
                $processedMessages = 0;

                // Para cada conversación, obtener sus mensajes recientes
                foreach ($conversations as $conversation) {
                    $conversationId = $conversation['id'] ?? null;
                    if (!$conversationId) {
                        continue;
                    }

                    // Obtener mensajes de la conversación (últimos 100 para asegurar capturar todos los relevantes)
                    $messagesResponse = $this->centralizedService->getMessages(
                        $instance->phone_number_id,
                        $conversationId,
                        1,
                        100
                    );

                    if (isset($messagesResponse['errorMessage'])) {
                        continue;
                    }

                    $messages = $messagesResponse['data'] ?? [];

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
                        }
                    }
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
        $incomingInvoiceId = $message['incoming_invoice_id'] ?? null;
        $incomingPaymentId = $message['incoming_payment_id'] ?? null;

        // Determinar el valor de whatsapp según el status
        // Si es "delivered" o "read" → whatsapp = 1
        // Si es diferente (sent, failed, etc.) → whatsapp = 0
        $whatsappValue = ($status === 'delivered' || $status === 'read') ? 1 : 0;

        $result = [
            'factura_actualizada' => false,
            'ingreso_actualizado' => false
        ];

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
