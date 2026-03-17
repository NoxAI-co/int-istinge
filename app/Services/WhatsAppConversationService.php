<?php

namespace App\Services;

use App\WhatsappMetaLog;
use Illuminate\Support\Collection;

class WhatsAppConversationService
{
    /**
     * Agrupa logs por wamid y calcula el estado final del mensaje.
     *
     * @param \Illuminate\Support\Collection<int, WhatsappMetaLog> $logs
     * @return \Illuminate\Support\Collection<int, array>
     */
    public function groupByMessage(Collection $logs): Collection
    {
        return $logs
            ->groupBy('wamid')
            ->map(function (Collection $group) {
                // Prioridad de estados: read > delivered > sent/success > failed
                $finalStatus = 'failed';

                if ($group->contains('status', 'read')) {
                    $finalStatus = 'read';
                } elseif ($group->contains('status', 'delivered')) {
                    $finalStatus = 'delivered';
                } elseif ($group->contains(function ($log) {
                    return in_array($log->status, ['sent', 'success'], true);
                })) {
                    $finalStatus = 'sent';
                }

                /** @var WhatsappMetaLog $latest */
                $latest = $group->sortByDesc('created_at')->first();

                return [
                    'wamid'               => $latest->wamid,
                    'incoming_invoice_id' => $latest->incoming_invoice_id,
                    'incoming_payment_id' => $latest->incoming_payment_id,
                    'template_id'         => $latest->template_id,
                    'final_status'        => $finalStatus, // read|delivered|sent|failed
                    'has_success'         => in_array($finalStatus, ['read', 'delivered'], true),
                    'mensaje_enviado'     => $latest->mensaje_enviado,
                    'created_at'          => $latest->created_at,
                    'logs'                => $group->values(),
                ];
            })
            ->values();
    }

    /**
     * Resumen por documento (factura / ingreso) a partir de los mensajes.
     *
     * @param \Illuminate\Support\Collection<int, array> $messages
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function summarizeByDocument(Collection $messages): array
    {
        $summary = [
            'facturas' => [],
            'ingresos' => [],
        ];

        $statusPriority = [
            'failed'    => 0,
            'sent'      => 1,
            'delivered' => 2,
            'read'      => 3,
        ];

        foreach ($messages as $msg) {
            $score = $statusPriority[$msg['final_status']] ?? 0;

            if (!empty($msg['incoming_invoice_id'])) {
                $id = $msg['incoming_invoice_id'];
                if (!isset($summary['facturas'][$id])) {
                    $summary['facturas'][$id] = [
                        'has_success' => $msg['has_success'],
                        'best_status' => $msg['final_status'],
                        'wamids'      => [$msg['wamid']],
                    ];
                } else {
                    $current = $summary['facturas'][$id];
                    if ($score > ($statusPriority[$current['best_status']] ?? 0)) {
                        $current['best_status'] = $msg['final_status'];
                    }
                    $current['has_success'] = $current['has_success'] || $msg['has_success'];
                    $current['wamids'][] = $msg['wamid'];
                    $summary['facturas'][$id] = $current;
                }
            }

            if (!empty($msg['incoming_payment_id'])) {
                $id = $msg['incoming_payment_id'];
                if (!isset($summary['ingresos'][$id])) {
                    $summary['ingresos'][$id] = [
                        'has_success' => $msg['has_success'],
                        'best_status' => $msg['final_status'],
                        'wamids'      => [$msg['wamid']],
                    ];
                } else {
                    $current = $summary['ingresos'][$id];
                    if ($score > ($statusPriority[$current['best_status']] ?? 0)) {
                        $current['best_status'] = $msg['final_status'];
                    }
                    $current['has_success'] = $current['has_success'] || $msg['has_success'];
                    $current['wamids'][] = $msg['wamid'];
                    $summary['ingresos'][$id] = $current;
                }
            }
        }

        return $summary;
    }
}

