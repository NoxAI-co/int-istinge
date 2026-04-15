<?php

namespace App\Services;

use App\Integracion;
use App\Empresa;
use App\Model\Ingresos\Factura;
use App\Contacto;
use App\MovimientoLOG;
use App\Funcion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class OnePayService
{
    protected $baseUri = 'https://api.onepay.la/v1';
    protected $token;

    public function __construct($empresaId = null)
    {
        // Obtener la integración de OnePay
        $servicio = Integracion::whereIn('nombre', ['ONEPAY', 'INTEGRAPAY'])
            ->where('tipo', 'PASARELA')
            ->where('lectura', 1)
            ->where('status', 1)
            ->first();

        if ($servicio) {
            $this->token = $servicio->api_key; // appkey guardado en api_key
        }
    }

    /**
     * Generar x-idempotency como hash único basado en la factura
     */
    protected function generateIdempotencyKey($facturaId)
    {
        return hash('sha256', $facturaId . '_' . time() . '_' . uniqid());
    }

    /**
     * Crear factura en OnePay
     */
    public function createInvoice(Factura $factura, $empresaId)
    {
        try {
            $empresa = Empresa::find($empresaId);
            $cliente = Contacto::find($factura->cliente);

            if (!$empresa || !$cliente) {
                throw new \Exception('Empresa o cliente no encontrado');
            }

            // Generar x-idempotency
            $idempotencyKey = $this->generateIdempotencyKey($factura->id);

            // Construir URL del documento
            $documentUrl = url('/api/factura/' . $factura->nonkey . '/pdf-onepay');

            // Calcular total de la factura
            $total = $factura->totalAPI($empresaId);
            $amount = (int) round($total->total);

            // Validar que el monto esté entre 5.000 y 100.000.000 millones de pesos
            if ($amount < 5000 || $amount > 100000000) {
                throw new \Exception('El monto debe estar entre $5.000 y $100.000.000 COP');
            }

            //para efectos de prueba:
            // $cliente->celular = '573002457118';
            // $empresa->nombre = 'Vivecomunicaciones';

            // Preparar datos
            $data = [
                'reference' => $cliente->nit,
                'provider_id' => $factura->codigo,
                'provider' => 'integra',
                'amount' => $amount,
                'name' => 'Factura #' . $factura->codigo,
                'phone' => $cliente->celular ? $this->formatPhone($cliente->celular) : null,
                'email' => $cliente->email ?: null,
                'due_date' => $factura->vencimiento ? date('Y-m-d', strtotime($factura->vencimiento)) : null,
                'document_url' => $documentUrl,
                'metadata' => [
                    'factura_id' => $factura->id,
                    'empresa_id' => $empresaId
                ]
            ];

            // Hacer petición POST
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $this->baseUri . '/invoices',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->token,
                    'Content-Type: application/json',
                    'x-idempotency: ' . $idempotencyKey
                ],
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);

            if ($error) {
                Log::error('OnePay Error: ' . $error);
                throw new \Exception('Error en la conexión con OnePay: ' . $error);
            }

            $responseData = json_decode($response, true);

            if ($httpCode >= 200 && $httpCode < 300) {
                // Guardar onepay_invoice_id en la factura
                if (isset($responseData['id'])) {
                    $factura->onepay_invoice_id = $responseData['id'];
                    $factura->save();
                }

                // Registrar log de éxito
                $montoFormateado = Funcion::ParsearAPI($amount/100, $empresaId);
                $descripcion = '<i class="fas fa-check text-success"></i> <b>Factura creada en Integra Pay</b> exitosamente. ID Integra Pay: <b>' . ($responseData['id'] ?? 'N/A') . '</b>. Monto: <b>' . $montoFormateado . '</b>';
                $this->registrarLogFactura($factura, $descripcion, false);

                return $responseData;
            } else {
                $errorMessage = isset($responseData['message']) ? $responseData['message'] : 'Error desconocido';
                Log::error('Integra Pay API Error: ' . $errorMessage, ['response' => $responseData]);

                // Registrar log de error
                $descripcion = '<i class="fas fa-times text-danger"></i> <b>Error al crear factura en Integra Pay</b>: ' . $errorMessage;
                $this->registrarLogFactura($factura, $descripcion, true);

                throw new \Exception('Error al crear factura en Integra Pay: ' . $errorMessage);
            }
        } catch (\Exception $e) {
            Log::error('Integra Pay createInvoice Exception: ' . $e->getMessage());

            // Registrar log de error en la excepción
            $descripcion = '<i class="fas fa-times text-danger"></i> <b>Error al crear factura en Integra Pay</b>: ' . $e->getMessage();
            $this->registrarLogFactura($factura, $descripcion, true);

            throw $e;
        }
    }

    /**
     * Actualizar factura en OnePay
     */
    public function updateInvoice(Factura $factura, $empresaId)
    {
        try {
            if (!$factura->onepay_invoice_id) {
                throw new \Exception('La factura no tiene onepay_invoice_id');
            }

            $empresa = Empresa::find($empresaId);
            $cliente = Contacto::find($factura->cliente);

            if (!$empresa || !$cliente) {
                throw new \Exception('Empresa o cliente no encontrado');
            }

            // Calcular total de la factura
            $total = $factura->totalAPI($empresaId);
            $amount = (int) round($total->total);

            // Validar que el monto esté entre 5.000 y 100.000.000 millones de pesos
            if ($amount < 5000 || $amount > 100000000) {
                throw new \Exception('El monto debe estar entre $5.000 y $100.000.000 COP');
            }

            //para efectos de prueba:
            // $cliente->celular = '573002457118';
            // $empresa->nombre = 'Vivecomunicaciones';

            // Preparar datos
            $data = [
                'reference' => $cliente->nit,
                'provider_id' => $factura->codigo,
                'amount' => $amount,
                'name' => 'Factura #' . $factura->codigo,
                'phone' => $cliente->celular ? $this->formatPhone($cliente->celular) : null,
                'email' => $cliente->email ?: null
            ];

            // Hacer petición PUT
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $this->baseUri . '/invoices/' . $factura->onepay_invoice_id,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->token,
                    'Content-Type: application/json'
                ],
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);

            if ($error) {
                Log::error('In  tegra Pay Error: ' . $error);
                throw new \Exception('Error en la conexión con Integra Pay: ' . $error);
            }

            $responseData = json_decode($response, true);

            if ($httpCode >= 200 && $httpCode < 300) {
                // Actualizar onepay_invoice_id si viene en la respuesta
                if (isset($responseData['id'])) {
                    $factura->onepay_invoice_id = $responseData['id'];
                    $factura->save();
                }

                // Registrar log de éxito
                $montoFormateado = Funcion::ParsearAPI($amount/100, $empresaId);
                $descripcion = '<i class="fas fa-check text-success"></i> <b>Factura actualizada en OnePay</b> exitosamente. ID OnePay: <b>' . ($responseData['id'] ?? $factura->onepay_invoice_id) . '</b>. Nuevo monto: <b>' . $montoFormateado . '</b>';
                $this->registrarLogFactura($factura, $descripcion, false);

                return $responseData;
            } else {
                $errorMessage = isset($responseData['message']) ? $responseData['message'] : 'Error desconocido';
                Log::error('Integra Pay API Error: ' . $errorMessage, ['response' => $responseData]);

                // Registrar log de error
                $descripcion = '<i class="fas fa-times text-danger"></i> <b>Error al actualizar factura en Integra Pay</b>: ' . $errorMessage;
                $this->registrarLogFactura($factura, $descripcion, true);

                throw new \Exception('Error al actualizar factura en Integra Pay: ' . $errorMessage);
            }
        } catch (\Exception $e) {
            Log::error('Integra Pay updateInvoice Exception: ' . $e->getMessage());

            // Registrar log de error en la excepción
            $descripcion = '<i class="fas fa-times text-danger"></i> <b>Error al actualizar factura en Integra Pay</b>: ' . $e->getMessage();
            $this->registrarLogFactura($factura, $descripcion, true);

            throw $e;
        }
    }

    /**
     * Eliminar factura en OnePay
     */
    public function deleteInvoice(Factura $factura, $reason = 'DELETE_FROM_PROVIDER')
    {
        try {
            if (!$factura->onepay_invoice_id) {
                return; // No hay nada que eliminar en OnePay
            }

            // Preparar datos
            $data = [
                'reason' => $reason
            ];

            // Hacer petición DELETE
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $this->baseUri . '/invoices/' . $factura->onepay_invoice_id,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'DELETE',
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->token,
                    'Content-Type: application/json'
                ],
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);

            if ($error) {
                Log::error('Integra Pay Error: ' . $error);
                throw new \Exception('Error en la conexión con Integra Pay: ' . $error);
            }

            $responseData = json_decode($response, true);

            if ($httpCode >= 200 && $httpCode < 300) {
                // Registrar log de éxito
                $descripcion = '<i class="fas fa-trash text-warning"></i> <b>Factura eliminada en Integra Pay</b> exitosamente. Motivo: <b>' . $reason . '</b>';
                $this->registrarLogFactura($factura, $descripcion, false);

                // Limpiar onepay_invoice_id
                $factura->onepay_invoice_id = null;
                $factura->save();

                return $responseData;
            } else {
                $errorMessage = isset($responseData['message']) ? $responseData['message'] : 'Error desconocido';

                // Si OnePay responde que la factura no existe (404), igual limpiamos el ID local
                if ($httpCode == 404) {
                    $factura->onepay_invoice_id = null;
                    $factura->save();
                    return;
                }

                Log::error('Integra Pay API Error: ' . $errorMessage, ['response' => $responseData]);

                // Registrar log de error
                $descripcion = '<i class="fas fa-times text-danger"></i> <b>Error al eliminar factura en Integra Pay</b>: ' . $errorMessage;
                $this->registrarLogFactura($factura, $descripcion, true);

                throw new \Exception('Error al eliminar factura en Integra Pay: ' . $errorMessage);
            }
        } catch (\Exception $e) {
            Log::error('Integra Pay deleteInvoice Exception: ' . $e->getMessage());

             // Registrar log de error en la excepción
             $descripcion = '<i class="fas fa-times text-danger"></i> <b>Error al eliminar factura en Integra Pay</b>: ' . $e->getMessage();
             $this->registrarLogFactura($factura, $descripcion, true);

            throw $e;
        }
    }

    /**
     * Registrar log en la factura
     */
    protected function registrarLogFactura(Factura $factura, $descripcion, $esError = false)
    {
        try {
            $movimiento = new MovimientoLOG();
            $movimiento->contrato = $factura->id; // ID de la factura
            $movimiento->modulo = 8; // Módulo de facturas
            $movimiento->descripcion = $descripcion;
            $movimiento->created_by = Auth::check() ? Auth::user()->id : null;
            $movimiento->empresa = $factura->empresa;
            $movimiento->save();
        } catch (\Exception $e) {
            // Si falla el registro del log, solo lo registramos en el log del sistema
            Log::error('Error al registrar log de factura Integra Pay: ' . $e->getMessage());
        }
    }

    /**
     * Formatear teléfono a formato E.164
     */
    protected function formatPhone($phone)
    {
        // Eliminar espacios y caracteres especiales
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Si no empieza con +, agregar código de país de Colombia
        if (substr($phone, 0, 1) !== '+') {
            // Si empieza con 57, agregar +
            if (substr($phone, 0, 2) === '57') {
                $phone = '+' . $phone;
            } else {
                // Asumir que es número colombiano y agregar +57
                $phone = '+57' . $phone;
            }
        }

        return $phone;
    }

    /**
     * Obtener listado de facturas en OnePay con filtros y paginación
     */
    public function getInvoices(array $filters = [])
    {
        try {
            if (!$this->token) {
                throw new \Exception('No hay token configurado para Integra Pay');
            }

            // Construir query params
            $params = [];

            if (!empty($filters['page'])) {
                $params['page'] = (int) $filters['page'];
            }

            if (!empty($filters['filter_id'])) {
                $params['filter[id]'] = $filters['filter_id'];
            }

            if (!empty($filters['filter_status'])) {
                $params['filter[status]'] = $filters['filter_status'];
            }

            if (!empty($filters['filter_reference'])) {
                $params['filter[reference]'] = $filters['filter_reference'];
            }

            if (!empty($filters['filter_provider_id'])) {
                $params['filter[provider_id]'] = $filters['filter_provider_id'];
            }

            if (!empty($filters['sort'])) {
                $params['sort'] = $filters['sort'];
            } else {
                $params['sort'] = '-created_at';
            }

            $url = $this->baseUri . '/invoices';
            if (!empty($params)) {
                $url .= '?' . http_build_query($params);
            }

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => 'GET',
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $this->token,
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error    = curl_error($curl);
            curl_close($curl);

            if ($error) {
                Log::error('Integra Pay getInvoices Error: ' . $error);
                throw new \Exception('Error en la conexión con Integra Pay: ' . $error);
            }

            $responseData = json_decode($response, true);

            if ($httpCode >= 200 && $httpCode < 300) {
                return $responseData;
            } else {
                $errorMessage = isset($responseData['message']) ? $responseData['message'] : 'Error desconocido';
                Log::error('Integra Pay getInvoices API Error: ' . $errorMessage, ['response' => $responseData]);
                throw new \Exception('Error al obtener facturas de Integra Pay: ' . $errorMessage);
            }
        } catch (\Exception $e) {
            Log::error('Integra Pay getInvoices Exception: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Verificar si OnePay está habilitado
     */
    public static function isEnabled($empresaId = null)
    {
        $servicio = Integracion::whereIn('nombre', ['ONEPAY', 'INTEGRAPAY'])
            ->where('tipo', 'PASARELA')
            ->where('lectura', 1)
            ->where('status', 1)
            ->first();

        return $servicio !== null;
    }
}

