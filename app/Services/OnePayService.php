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
use App\Http\Controllers\FacturasController;
use Illuminate\Support\Facades\File;

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
     * Generar x-idempotency como hash único determinista basado en la factura y operación.
     * Esto evita que múltiples invocaciones produzcan claves distintas para el mismo recurso.
     */
    protected function generateIdempotencyKey(Factura $factura, $operation, $empresaId)
    {
        // Se deriva de datos estables: ID de factura, código y empresa.
        // Incluye un prefijo semántico por operación para diferenciar eventos (create vs update).
        return hash('sha256', 'invoice_' . $operation . '_' . $factura->id . '_' . $factura->codigo . '_' . $empresaId);
    }

    /**
     * Crear factura en OnePay
     */
    public function createInvoice(Factura $factura, $empresaId)
    {
        try {
            // Guard de duplicado: Si la factura ya tiene un ID de OnePay asignado,
            // no intentamos crearla de nuevo sino que redirigimos a actualización.
            if ($factura->onepay_invoice_id) {
                return $this->updateInvoice($factura, $empresaId);
            }

            $empresa = Empresa::find($empresaId);
            $cliente = Contacto::find($factura->cliente);

            if (!$empresa || !$cliente) {
                throw new \Exception('Empresa o cliente no encontrado');
            }

            // Asegurar que la factura tenga un nonkey generado (necesario para el nombre del archivo)
            if (empty($factura->nonkey)) {
                $factura->nonkey = md5($factura->id . time() . 'integra');
                $factura->save();
            }

            // Generar x-idempotency determinista. 
            // Se prefiere reutilizar la almacenada si existe por robustez en reintentos.
            $idempotencyKey = !empty($factura->onepay_idempotency_key) 
                ? $factura->onepay_idempotency_key 
                : $this->generateIdempotencyKey($factura, 'create', $empresaId);

            // Generar y asegurar la ruta estática para el documento (Evita corrupción de Meta)
            $documentUrl = $this->prepareDocument($factura);

            // Calcular total de la factura
            $total = $factura->totalAPI($empresaId);
            $amount = (int) round($total->total);

            // Validar que el monto esté entre 5.000 y 100.000.000 millones de pesos
            if ($amount < 5000 || $amount > 100000000) {
                throw new \Exception('El monto debe estar entre $5.000 y $100.000.000 COP');
            }

            $periodoCobrado = "";
            if($factura->periodo_cobrado_text != null || $factura->periodo_cobrado_text != ""){
                $periodoCobrado = $factura->periodo_cobrado_text;
            }else{
                $periodoCobrado = $factura->periodoCobradoTexto() . ' ' . $factura->diasCobradosProrrateo(null, null, true) . ' días';
            }

            if($periodoCobrado == "dias"){
                $periodoCobrado = "Facturación de servicios";
            }

            // Auto-corregir y persistir el teléfono y correo del cliente
            $phoneFormatted = $cliente->celular ? $this->autoFixClientPhone($cliente) : null;
            $emailFormatted = $this->autoFixClientEmail($cliente);

            // Preparar datos
            $data = [
                'reference' => $cliente->nit,
                'provider_id' => $factura->codigo,
                'provider' => 'integra',
                'amount' => $amount,
                'name' => 'Factura #' . $factura->codigo,
                'phone' => $phoneFormatted,
                'email' => $emailFormatted,
                'due_date' => $factura->vencimiento ? date('Y-m-d', strtotime($factura->vencimiento)) : null,
                'description' => $periodoCobrado,
                // 'document_url' => $documentUrl,
                'metadata' => [
                    'factura_id' => $factura->id,
                    'empresa_id' => $empresaId
                ]
            ];

            // Intentar enviar con reintento automático en caso de error de teléfono o correo
            $maxRetries = 3;
            $responseData = null;
            $httpCode = null;

            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
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
                        'x-idempotency: ' . $idempotencyKey . ($attempt > 1 ? '_retry' . $attempt : '')
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

                // Si es error de validación y aún quedan reintentos, intentar auto-corregir
                if ($httpCode >= 400 && $attempt < $maxRetries) {
                    $errorMsg = $responseData['message'] ?? '';

                    // Error de teléfono E.164
                    if (stripos($errorMsg, 'E.164') !== false || stripos($errorMsg, 'teléfono') !== false || stripos($errorMsg, 'telefono') !== false) {
                        Log::warning("OnePay E.164 error en intento {$attempt}, auto-corrigiendo teléfono para cliente #{$cliente->id}");
                        $cliente->celular = '3000000000';
                        $cliente->save();
                        $data['phone'] = '+573000000000';

                        $descripcion = '<i class="fas fa-sync text-info"></i> <b>Auto-corrección teléfono</b>: Teléfono inválido corregido automáticamente para cliente #' . $cliente->id . '. Reintentando envío a Integra Pay.';
                        $this->registrarLogFactura($factura, $descripcion, false);
                        continue;
                    }

                    // Error de correo electrónico
                    if (stripos($errorMsg, 'correo') !== false || stripos($errorMsg, 'email') !== false || stripos($errorMsg, 'e-mail') !== false) {
                        Log::warning("OnePay email error en intento {$attempt}, auto-corrigiendo correo para cliente #{$cliente->id}: '{$cliente->email}'");
                        $fallbackEmail = 'cliente' . $cliente->id . '@integrapay.temp';
                        $cliente->email = $fallbackEmail;
                        $cliente->save();
                        $data['email'] = $fallbackEmail;

                        $descripcion = '<i class="fas fa-sync text-info"></i> <b>Auto-corrección correo</b>: Correo inválido corregido automáticamente para cliente #' . $cliente->id . '. Reintentando envío a Integra Pay.';
                        $this->registrarLogFactura($factura, $descripcion, false);
                        continue;
                    }
                }

                break; // Si no es error corregible o ya es el último intento, salir del loop
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                // Guardar onepay_invoice_id y la idempotency key en la factura
                if (isset($responseData['id'])) {
                    $nuevoId = $responseData['id'];
                    
                    // Asegurar que el ID no haya sido tomado accidentalmente por otra factura
                    $idExistente = Factura::where('onepay_invoice_id', $nuevoId)
                        ->where('id', '!=', $factura->id)
                        ->first();
                        
                    if ($idExistente) {
                        throw new \Exception("Alerta: El ID de OnePay devuelto ({$nuevoId}) ya existe asignado a la factura interna ID {$idExistente->id}.");
                    }
                    
                    $factura->onepay_invoice_id = $nuevoId;
                    $factura->onepay_idempotency_key = $idempotencyKey;
                    $factura->save();
                }

                // Registrar log de éxito - Corregido: $amount ya está en pesos enteros
                $montoFormateado = Funcion::ParsearAPI($amount, $empresaId);
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

            // Generar x-idempotency para actualización
            $idempotencyKey = $this->generateIdempotencyKey($factura, 'update', $empresaId);

            // Calcular total de la factura
            $total = $factura->totalAPI($empresaId);
            $amount = (int) round($total->total);

            // Validar que el monto esté entre 5.000 y 100.000.000 millones de pesos
            if ($amount < 5000 || $amount > 100000000) {
                throw new \Exception('El monto debe estar entre $5.000 y $100.000.000 COP');
            }

            // Auto-corregir y persistir el teléfono y correo del cliente
            $phoneFormatted = $cliente->celular ? $this->autoFixClientPhone($cliente) : null;
            $emailFormatted = $this->autoFixClientEmail($cliente);

            // Preparar datos
            $data = [
                'reference' => $cliente->nit,
                'provider_id' => $factura->codigo,
                'amount' => $amount,
                'name' => 'Factura #' . $factura->codigo,
                'phone' => $phoneFormatted,
                'email' => $emailFormatted
            ];

            // Intentar enviar con reintento automático en caso de error de teléfono o correo
            $maxRetries = 3;
            $responseData = null;
            $httpCode = null;

            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
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
                        'Content-Type: application/json',
                        'x-idempotency: ' . $idempotencyKey . ($attempt > 1 ? '_retry' . $attempt : '')
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

                // Si es error de validación y aún quedan reintentos, intentar auto-corregir
                if ($httpCode >= 400 && $attempt < $maxRetries) {
                    $errorMsg = $responseData['message'] ?? '';

                    // Error de teléfono E.164
                    if (stripos($errorMsg, 'E.164') !== false || stripos($errorMsg, 'teléfono') !== false || stripos($errorMsg, 'telefono') !== false) {
                        Log::warning("OnePay E.164 error en intento {$attempt}, auto-corrigiendo teléfono para cliente #{$cliente->id}");
                        $cliente->celular = '3000000000';
                        $cliente->save();
                        $data['phone'] = '+573000000000';

                        $descripcion = '<i class="fas fa-sync text-info"></i> <b>Auto-corrección teléfono</b>: Teléfono inválido corregido automáticamente para cliente #' . $cliente->id . '. Reintentando envío a Integra Pay.';
                        $this->registrarLogFactura($factura, $descripcion, false);
                        continue;
                    }

                    // Error de correo electrónico
                    if (stripos($errorMsg, 'correo') !== false || stripos($errorMsg, 'email') !== false || stripos($errorMsg, 'e-mail') !== false) {
                        Log::warning("OnePay email error en intento {$attempt}, auto-corrigiendo correo para cliente #{$cliente->id}: '{$cliente->email}'");
                        $fallbackEmail = 'cliente' . $cliente->id . '@integrapay.temp';
                        $cliente->email = $fallbackEmail;
                        $cliente->save();
                        $data['email'] = $fallbackEmail;

                        $descripcion = '<i class="fas fa-sync text-info"></i> <b>Auto-corrección correo</b>: Correo inválido corregido automáticamente para cliente #' . $cliente->id . '. Reintentando envío a Integra Pay.';
                        $this->registrarLogFactura($factura, $descripcion, false);
                        continue;
                    }
                }

                break; // Si no es error corregible o ya es el último intento, salir del loop
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                // Actualizar onepay_invoice_id si viene en la respuesta
                if (isset($responseData['id'])) {
                    $factura->onepay_invoice_id = $responseData['id'];
                    $factura->save();
                }

                // Registrar log de éxito - Corregido: $amount ya está en pesos enteros
                $montoFormateado = Funcion::ParsearAPI($amount, $empresaId);
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
     * Formatear teléfono a formato E.164 para Colombia (+57XXXXXXXXXX)
     * Maneja casos como:
     * - Números con más de 10 dígitos (se recortan desde el final)
     * - Números con menos de 10 dígitos (se genera uno de relleno válido)
     * - Números con código de país ya incluido (+57 o 57)
     * - Números vacíos o inválidos (se genera uno de relleno)
     */
    protected function formatPhone($phone)
    {
        if (empty($phone)) {
            return '+573000000000'; // Número de relleno por defecto
        }

        // Eliminar todo excepto dígitos
        $digits = preg_replace('/[^0-9]/', '', $phone);

        // Si no quedan dígitos después de limpiar, retornar relleno
        if (empty($digits)) {
            return '+573000000000';
        }

        // Si empieza con 57 y tiene más de 10 dígitos, es probable que tenga código de país
        if (substr($digits, 0, 2) === '57' && strlen($digits) > 10) {
            // Quitar el prefijo 57 para trabajar solo con el número nacional
            $digits = substr($digits, 2);
        }

        // Ahora $digits debería ser el número nacional (idealmente 10 dígitos)
        $len = strlen($digits);

        if ($len > 10) {
            // Más de 10 dígitos: recortar desde el final hasta tener 10
            $digits = substr($digits, 0, 10);
        } elseif ($len < 10) {
            // Menos de 10 dígitos: número inválido, generar uno de relleno
            // Usamos 300 como prefijo de operador genérico + los dígitos que hay + ceros de relleno
            if ($len >= 3 && in_array(substr($digits, 0, 1), ['3'])) {
                // Parece un celular colombiano incompleto, rellenar con ceros al final
                $digits = str_pad($digits, 10, '0');
            } else {
                // No parece un celular válido, generar uno de relleno completo
                $digits = '3000000000';
            }
        }

        // Validar que el primer dígito sea 3 (celulares colombianos)
        // Si no lo es, anteponer 3 y recortar para mantener 10 dígitos
        if (substr($digits, 0, 1) !== '3') {
            $digits = '3' . substr($digits, 0, 9);
        }

        return '+57' . $digits;
    }

    /**
     * Corregir y persistir el teléfono del cliente en la base de datos.
     * Retorna el teléfono formateado en E.164.
     */
    protected function autoFixClientPhone($cliente)
    {
        $originalPhone = $cliente->celular;
        $formattedPhone = $this->formatPhone($originalPhone);

        // Extraer solo los 10 dígitos nacionales para guardar en la BD
        $nationalDigits = substr($formattedPhone, 3); // Quitar +57

        // Solo actualizar si realmente cambió
        if ($originalPhone !== $nationalDigits && $cliente->celular !== $nationalDigits) {
            Log::info("OnePay AutoFix teléfono cliente #{$cliente->id}: '{$originalPhone}' -> '{$nationalDigits}'");
            $cliente->celular = $nationalDigits;
            $cliente->save();
        }

        return $formattedPhone;
    }

    /**
     * Formatear y validar correo electrónico para OnePay.
     * Maneja casos como:
     * - Correos que empiezan con punto: .usuario@gmail.com
     * - Múltiples correos separados por coma o punto y coma: a@g.com, b@g.com
     * - Espacios en el correo
     * - Puntos consecutivos en la parte local: usuario..nombre@gmail.com
     * - Correos sin @
     * - Correos vacíos o nulos
     * - Caracteres especiales no permitidos
     */
    protected function formatEmail($email)
    {
        if (empty($email) || trim($email) === '') {
            return null; // Se generará un fallback en autoFixClientEmail
        }

        $email = trim($email);

        // Si tiene múltiples correos separados por coma, punto y coma, o espacio, tomar solo el primero
        if (preg_match('/[,;\s]/', $email)) {
            $parts = preg_split('/[,;\s]+/', $email);
            $email = '';
            // Buscar el primer correo que parezca válido
            foreach ($parts as $part) {
                $part = trim($part);
                if (!empty($part) && strpos($part, '@') !== false) {
                    $email = $part;
                    break;
                }
            }
            if (empty($email)) {
                return null;
            }
        }

        // Eliminar espacios internos
        $email = str_replace(' ', '', $email);

        // Eliminar puntos al inicio de la parte local
        // .rodriguezlozano@gmail.com -> rodriguezlozano@gmail.com
        $email = ltrim($email, '.');

        // Separar parte local y dominio
        $atPos = strpos($email, '@');
        if ($atPos === false) {
            return null; // Sin @ no es un correo válido
        }

        $local = substr($email, 0, $atPos);
        $domain = substr($email, $atPos + 1);

        // Limpiar la parte local
        // Eliminar puntos consecutivos: usuario..nombre -> usuario.nombre
        $local = preg_replace('/\.{2,}/', '.', $local);
        // Eliminar punto al final de la parte local
        $local = rtrim($local, '.');
        // Eliminar punto al inicio de la parte local (por si quedó después de limpiar)
        $local = ltrim($local, '.');

        // Eliminar caracteres no permitidos en la parte local (solo letras, números, ., _, -, +)
        $local = preg_replace('/[^a-zA-Z0-9._\-+]/', '', $local);

        // Si la parte local quedó vacía después de limpiar
        if (empty($local)) {
            return null;
        }

        // Limpiar el dominio
        $domain = strtolower(trim($domain));
        // Eliminar puntos consecutivos en el dominio
        $domain = preg_replace('/\.{2,}/', '.', $domain);
        $domain = trim($domain, '.');

        // Validar que el dominio tenga al menos un punto (ej: gmail.com)
        if (strpos($domain, '.') === false || empty($domain)) {
            return null;
        }

        $cleanEmail = $local . '@' . $domain;

        // Validación final con filter_var de PHP
        if (!filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $cleanEmail;
    }

    /**
     * Corregir y persistir el correo del cliente en la base de datos.
     * Si el correo es inválido o vacío, genera un correo de relleno.
     * Retorna el correo formateado o null.
     */
    protected function autoFixClientEmail($cliente)
    {
        $originalEmail = $cliente->email;
        $formattedEmail = $this->formatEmail($originalEmail);

        // Si el correo no pudo ser formateado (inválido), generar uno de relleno
        if ($formattedEmail === null) {
            if (!empty($originalEmail)) {
                // Tenía un correo pero era inválido: generar fallback
                $formattedEmail = 'cliente' . $cliente->id . '@integrapay.temp';
                Log::info("OnePay AutoFix correo cliente #{$cliente->id}: '{$originalEmail}' -> '{$formattedEmail}' (correo original inválido)");
                $cliente->email = $formattedEmail;
                $cliente->save();
            }
            // Si no tenía correo, retornar null (campo opcional)
            return $formattedEmail;
        }

        // Si el correo fue corregido (diferente al original), persistir
        if ($originalEmail !== $formattedEmail) {
            Log::info("OnePay AutoFix correo cliente #{$cliente->id}: '{$originalEmail}' -> '{$formattedEmail}'");
            $cliente->email = $formattedEmail;
            $cliente->save();
        }

        return $formattedEmail;
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
    /**
     * Enviar campaña de sensibilización masiva
     */
    public function sendSensibilizacionCampaign($phones, $template, $imageUrl, $idempotencyKey, $optional1 = null, $optional2 = null, $optional3 = null)
    {
        try {
            if (!$this->token) {
                throw new \Exception('No hay token configurado para Integra Pay');
            }

            $data = [
                'template' => $template,
                'image_url' => $imageUrl,
                'phones' => $phones,
                'optional_1' => $optional1,
                'optional_2' => $optional2,
                'optional_3' => $optional3,
            ];

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $this->baseUri . '/campaigns/import-messages',
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
                Log::error('Integra Pay Campaign Error: ' . $error);
                throw new \Exception('Error en la conexión con Integra Pay (Campaign): ' . $error);
            }

            $responseData = json_decode($response, true);

            if ($httpCode >= 200 && $httpCode < 300) {
                return [
                    'success' => true,
                    'data' => $responseData,
                    'http_code' => $httpCode
                ];
            } else {
                return [
                    'success' => false,
                    'data' => $responseData,
                    'http_code' => $httpCode,
                    'message' => $responseData['message'] ?? 'Error desconocido en la API de campañas'
                ];
            }
        } catch (\Exception $e) {
            Log::error('Integra Pay sendSensibilizacionCampaign Exception: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener listado de pagos de OnePay con filtros y paginación.
     * Usado por el CRON de conciliación (syncIntegraPay).
     *
     * @param array $filters Filtros opcionales: page, sort, filter[status], etc.
     * @return array Respuesta decodificada de la API
     * @throws \Exception
     */
    public function getPayments(array $filters = [])
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

            if (!empty($filters['sort'])) {
                $params['sort'] = $filters['sort'];
            } else {
                $params['sort'] = '-created_at';
            }

            if (!empty($filters['filter_status'])) {
                $params['filter[status]'] = $filters['filter_status'];
            }

            if (!empty($filters['filter_id'])) {
                $params['filter[id]'] = $filters['filter_id'];
            }

            if (!empty($filters['filter_external_id'])) {
                $params['filter[external_id]'] = $filters['filter_external_id'];
            }

            $url = $this->baseUri . '/payments';
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
                Log::error('Integra Pay getPayments Error: ' . $error);
                throw new \Exception('Error en la conexión con Integra Pay: ' . $error);
            }

            $responseData = json_decode($response, true);

            if ($httpCode >= 200 && $httpCode < 300) {
                return $responseData;
            } else {
                $errorMessage = isset($responseData['message']) ? $responseData['message'] : 'Error desconocido';
                Log::error('Integra Pay getPayments API Error: ' . $errorMessage, ['response' => $responseData]);
                throw new \Exception('Error al obtener pagos de Integra Pay: ' . $errorMessage);
            }
        } catch (\Exception $e) {
            Log::error('Integra Pay getPayments Exception: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Prepara el documento PDF estático. Se puede llamar antes de createInvoice para asegurar su existencia.
     */
    public function prepareDocument(Factura $factura): string
    {
        return $this->ensureStaticDocument($factura);
    }

    private function ensureStaticDocument(Factura $factura): string
    {
        try {
            $directory = public_path('documentos_meta');
            
            // 1. Crear carpeta si no existe
            if (!File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            // 2. Limpieza automática (Archivos > 40 días)
            $this->purgeOldDocuments($directory);

            // 3. Definir nombre y ruta
            $filename = 'factura_' . $factura->nonkey . '.pdf';
            $fullPath = $directory . '/' . $filename;

            // 4. Generar el contenido PDF (Llamada interna al controlador)
            // Usamos output() para capturar el stream del PDF sin enviarlo al navegador
            // Pasamos el 4to argumento ($save) como true para que retorne el objeto PDF
            $pdfResponse = FacturasController::Imprimir($factura->id, 'original', true, true, false);
            
            if (is_object($pdfResponse) && method_exists($pdfResponse, 'output')) {
                $pdfBinary = $pdfResponse->output();
                File::put($fullPath, $pdfBinary);

                // Validar que el archivo realmente se guardó y no está vacío
                if (!File::exists($fullPath) || File::size($fullPath) == 0) {
                    Log::error("Fallo crítico en generación de PDF para OnePay: {$fullPath}");
                    throw new \Exception("No se pudo confirmar la creación física del PDF.");
                }

                return url('documentos_meta/' . $filename);
            }

            // Fallback en caso de error en generación interna
            return url('/api/factura/' . $factura->nonkey . '/pdf-onepay');

        } catch (\Exception $e) {
            Log::error('Error en ensureStaticDocument: ' . $e->getMessage());
            return url('/api/factura/' . $factura->nonkey . '/pdf-onepay');
        }
    }

    /**
     * Elimina archivos PDF antiguos en el directorio especificado.
     */
    private function purgeOldDocuments(string $directory): void
    {
        $files = File::files($directory);
        $now = time();
        foreach ($files as $file) {
            if ($now - File::lastModified($file) > 3456000) { // 40 días (40 * 24 * 3600)
                File::delete($file);
            }
        }
    }
}