<?php

namespace App\Services;

use App\WhatsappMetaLog;
use App\Model\Ingresos\Factura;
use App\Model\Ingresos\Ingreso;
use App\Contrato;
use App\Instance;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class WhatsAppMessageSyncService
{
    /**
     * @var CentralizedWhatsAppService
     */
    protected $centralizedService;

    public function __construct(CentralizedWhatsAppService $centralizedService)
    {
        $this->centralizedService = $centralizedService;
    }

    /**
     * Sincronizar mensajes de WhatsApp para una instancia/empresa en un rango de fechas.
     *
     * @param Instance $instance
     * @param int $companyNit
     * @param string $dateFrom YYYY-MM-DD
     * @param string $dateTo YYYY-MM-DD
     * @return array [logs_created, logs_updated, facturas_actualizadas, ingresos_actualizados]
     */
    public function syncForInstanceAndCompany(Instance $instance, int $companyNit, string $dateFrom, string $dateTo): array
    {
        $page = 1;
        $perPage = 200;
        $logsCreated = 0;
        $logsUpdated = 0;
        $facturasActualizadas = 0;
        $ingresosActualizados = 0;

        $dateFromStr = Carbon::parse($dateFrom)->format('Y-m-d');
        $dateToStr = Carbon::parse($dateTo)->format('Y-m-d');

        Log::info('WhatsAppMessageSyncService::syncForInstanceAndCompany start', [
            'instance_id' => $instance->id,
            'phone_number_id' => $instance->phone_number_id,
            'company_nit' => $companyNit,
            'date_from' => $dateFromStr,
            'date_to' => $dateToStr,
        ]);

        if (empty($instance->phone_number_id)) {
            Log::warning('WhatsAppMessageSyncService: instance without phone_number_id', [
                'instance_id' => $instance->id,
            ]);

            return compact('logsCreated', 'logsUpdated', 'facturasActualizadas', 'ingresosActualizados');
        }

        $hasMore = true;
        $maxPages = 50; // límite de seguridad

        while ($hasMore && $page <= $maxPages) {
            $response = $this->centralizedService->getWhatsAppMessages(
                $instance->phone_number_id,
                [
                    'incoming_company_nit' => $companyNit,
                    'date_from' => $dateFromStr,
                    'date_to' => $dateToStr,
                    // Opcional: podríamos filtrar por status aquí si se requiere
                ],
                $page,
                $perPage
            );

            if (isset($response['errorMessage'])) {
                Log::error('WhatsAppMessageSyncService: error fetching whatsapp-messages', [
                    'instance_id' => $instance->id,
                    'error' => $response['errorMessage'],
                    'status_code' => $response['statusCode'] ?? null,
                ]);
                break;
            }

            $data = $response['data'] ?? [];
            if (empty($data)) {
                break;
            }

            foreach ($data as $remoteMessage) {
                $result = $this->syncSingleMessage($remoteMessage, $instance, $companyNit);
                if ($result['created']) {
                    $logsCreated++;
                } elseif ($result['updated']) {
                    $logsUpdated++;
                }

                $facturasActualizadas += $result['facturasActualizadas'] ?? 0;
                $ingresosActualizados += $result['ingresosActualizados'] ?? 0;
            }

            $meta = $response['meta'] ?? [];
            $lastPage = $meta['last_page'] ?? $meta['lastPage'] ?? $page;
            $hasMore = $page < $lastPage;
            $page++;
        }

        Log::info('WhatsAppMessageSyncService::syncForInstanceAndCompany finished', [
            'instance_id' => $instance->id,
            'logs_created' => $logsCreated,
            'logs_updated' => $logsUpdated,
            'facturas_actualizadas' => $facturasActualizadas,
            'ingresos_actualizados' => $ingresosActualizados,
        ]);

        return compact('logsCreated', 'logsUpdated', 'facturasActualizadas', 'ingresosActualizados');
    }

    /**
     * Sincronizar un solo mensaje remoto hacia log_meta y actualizar documentos.
     *
     * @param array $remoteMessage
     * @param Instance $instance
     * @param int $companyNit
     * @return array
     */
    protected function syncSingleMessage(array $remoteMessage, Instance $instance, int $companyNit): array
    {
        $remoteId = $remoteMessage['id'] ?? null;
        $wamid = $remoteMessage['wamid'] ?? null;
        $status = $remoteMessage['status'] ?? null;
        $errorMessage = $remoteMessage['error_message'] ?? null;
        $direction = $remoteMessage['direction'] ?? null;
        $incomingInvoiceId = $remoteMessage['incoming_invoice_id'] ?? null;
        $incomingContractId = $remoteMessage['incoming_contract_id'] ?? null;
        $incomingPaymentId = $remoteMessage['incoming_payment_id'] ?? null;
        $content = $remoteMessage['content'] ?? null;
        $metadata = $remoteMessage['metadata'] ?? null;

        $sentAt = isset($remoteMessage['sent_at']) ? Carbon::parse($remoteMessage['sent_at']) : null;
        $deliveredAt = isset($remoteMessage['delivered_at']) ? Carbon::parse($remoteMessage['delivered_at']) : null;
        $readAt = isset($remoteMessage['read_at']) ? Carbon::parse($remoteMessage['read_at']) : null;

        $created = false;
        $updated = false;
        $facturasActualizadas = 0;
        $ingresosActualizados = 0;

        if (!$remoteId && !$wamid) {
            // Sin identificadores confiables, mejor no guardar
            return compact('created', 'updated', 'facturasActualizadas', 'ingresosActualizados');
        }

        // Clave única: remote_id si existe, si no wamid
        $query = WhatsappMetaLog::query();
        if ($remoteId) {
            $query->where('remote_id', $remoteId);
        } elseif ($wamid) {
            $query->where('wamid', $wamid);
        }

        /** @var WhatsappMetaLog|null $log */
        $log = $query->first();

        // Determinar contacto_id basándose en las relaciones
        $contactoId = null;
        if ($incomingInvoiceId) {
            $factura = Factura::find($incomingInvoiceId);
            if ($factura && $factura->cliente) {
                $contactoId = $factura->cliente;
            }
        } elseif ($incomingPaymentId) {
            $ingreso = Ingreso::find($incomingPaymentId);
            if ($ingreso && $ingreso->cliente) {
                $contactoId = $ingreso->cliente;
            }
        } elseif ($incomingContractId) {
            $contrato = Contrato::find($incomingContractId);
            if ($contrato && $contrato->client_id) {
                $contactoId = $contrato->client_id;
            }
        }

        $payload = [
            'remote_id' => $remoteId,
            'wamid' => $wamid,
            'incoming_invoice_id' => $incomingInvoiceId,
            'incoming_contract_id' => $incomingContractId,
            'incoming_payment_id' => $incomingPaymentId,
            'incoming_company_nit' => $companyNit,
            'contacto_id' => $contactoId, // Actualizar contacto_id basado en las relaciones
            'direction' => $direction,
            'status' => $status,
            'error_message' => $errorMessage,
            'metadata' => is_array($metadata) ? json_encode($metadata) : $metadata,
            'sent_at' => $sentAt,
            'delivered_at' => $deliveredAt,
            'read_at' => $readAt,
            'empresa' => $instance->company_id ?? null,
            'mensaje_enviado' => $content,
        ];

        if ($log) {
            $log->fill($payload);
            if ($log->isDirty()) {
                $log->save();
                $updated = true;
            }
        } else {
            $log = WhatsappMetaLog::create($payload);
            $created = true;
        }

        // Actualizar documentos asociados según status
        if ($status === 'delivered' || $status === 'read') {
            // Marcar como entregado/leído (whatsapp = 1)
            if ($incomingInvoiceId) {
                $factura = Factura::find($incomingInvoiceId);
                if ($factura && $factura->whatsapp != 1) {
                    DB::table('factura')
                        ->where('id', $incomingInvoiceId)
                        ->update(['whatsapp' => 1]);
                    $facturasActualizadas++;
                }
            }

            if ($incomingPaymentId) {
                $ingreso = Ingreso::find($incomingPaymentId);
                if ($ingreso && $ingreso->whatsapp != 1) {
                    DB::table('ingresos')
                        ->where('id', $incomingPaymentId)
                        ->update(['whatsapp' => 1]);
                    $ingresosActualizados++;
                }
            }
        } elseif ($status === 'failed') {
            $isUndeliverable = $errorMessage && stripos($errorMessage, 'Message undeliverable') !== false;

            // Solo incrementar y permitir reintento para números verdaderamente inválidos.
            //
            // IMPORTANTE: tanto el incremento del contador COMO el reseteo whatsapp=0 deben
            // ocurrir únicamente la PRIMERA vez que vemos este log ($created). Si el reseteo
            // se ejecutara también al re-leer un log ya existente, cada sincronización (cada
            // 15 min) volvería a poner whatsapp=0 sin subir el contador, el tope de 3 nunca
            // se alcanzaría y el cron reenviaría la factura en bucle.
            if ($isUndeliverable && $created) {
                // Resetear whatsapp=0 SOLO para "Message undeliverable" (número inválido):
                // el cron reintentará hasta cont_message_undeliverable=3 y luego se detiene.
                // Para otros tipos de fallo (transitorios, rate-limit, healthy ecosystem)
                // NO reseteamos para evitar bucles de reenvío cuando el mensaje sí llegó.
                if ($incomingInvoiceId) {
                    DB::table('factura')->where('id', $incomingInvoiceId)->increment('cont_message_undeliverable');

                    $factura = Factura::find($incomingInvoiceId);
                    if ($factura && $factura->cont_message_undeliverable < 3 && $factura->whatsapp != 0) {
                        DB::table('factura')
                            ->where('id', $incomingInvoiceId)
                            ->update(['whatsapp' => 0]);
                        $facturasActualizadas++;
                    }
                }

                if ($incomingPaymentId) {
                    DB::table('ingresos')->where('id', $incomingPaymentId)->increment('cont_message_undeliverable');

                    $ingreso = Ingreso::find($incomingPaymentId);
                    if ($ingreso && $ingreso->cont_message_undeliverable < 3 && $ingreso->whatsapp != 0) {
                        DB::table('ingresos')
                            ->where('id', $incomingPaymentId)
                            ->update(['whatsapp' => 0]);
                        $ingresosActualizados++;
                    }
                }
            }
            // Para failed con otros errores (transitorio, spam, healthy ecosystem, etc.):
            // no tocar whatsapp ni cont_message_undeliverable para evitar reenvíos.
        }

        return compact('created', 'updated', 'facturasActualizadas', 'ingresosActualizados');
    }
}

