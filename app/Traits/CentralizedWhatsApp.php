<?php

namespace App\Traits;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait CentralizedWhatsApp
{
    /**
     * Registra un mensaje de WhatsApp en el sistema centralizado.
     *
     * @param string $phoneNumberId ID del número de teléfono de la instancia Meta.
     * @param string $phone Teléfono del destinatario (con prefijo).
     * @param string $wamid ID del mensaje devuelto por Meta.
     * @param string $content Contenido completo del mensaje procesado.
     * @param string $name Nombre del contacto.
     * @param string $type Tipo de mensaje (por defecto 'template').
     * @param string $status Estado del mensaje (por defecto 'sent').
     * @param int|null $incomingInvoiceId ID de la factura opcional.
     * @param int|null $incomingContractId ID del contrato opcional.
     * @param int|null $incomingPaymentId ID del pago opcional.
     * @param int|null $incomingCompanyNit NIT de la empresa.
     * @return void
     */
    public function registerCentralizedBatch(
        string $phoneNumberId,
        string $phone,
        string $wamid,
        string $content,
        string $name,
        string $type = 'template',
        string $status = 'sent',
        ?int $incomingInvoiceId = null,
        ?int $incomingContractId = null,
        ?int $incomingPaymentId = null,
        ?int $incomingCompanyNit = null,
        ?int $templateId = null
    ) {
        try {
            $data = [
                'to'      => $phone,
                'wamid'   => $wamid,
                'content' => $content,
                'type'    => $type,
                'status'  => $status,
                'name'    => $name,
            ];

            if ($incomingInvoiceId !== null) {
                $data['incoming_invoice_id'] = $incomingInvoiceId;
            }
            if ($incomingContractId !== null) {
                $data['incoming_contract_id'] = $incomingContractId;
            }
            if ($incomingPaymentId !== null) {
                $data['incoming_payment_id'] = $incomingPaymentId;
            }
            if ($incomingCompanyNit !== null) {
                $data['incoming_company_nit'] = $incomingCompanyNit;
            }
            if ($templateId !== null) {
                $data['template_id'] = $templateId;
            }

            Http::withHeaders([
                'X-Instance-Token' => $phoneNumberId,
            ])->post('https://wpp.integracolombia.com/api/v1/messages/register', $data);
        } catch (\Exception $e) {
            Log::error('Error syncing WhatsApp message to central chat: ' . $e->getMessage());
        }
    }
}
