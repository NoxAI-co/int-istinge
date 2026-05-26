<?php

namespace App\Http\Controllers;

use App\Empresa;
use App\Impuesto;
use App\Model\Ingresos\Factura;
use App\Model\Ingresos\FacturaRetencion;
use App\Model\Ingresos\ItemsFactura;
use App\Model\Inventario\Inventario;
use App\MovimientoLOG;
use App\Retencion;
use App\Vendedor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SiigoController extends Controller
{
    /**
     * Método helper para ejecutar llamadas a la API de Siigo con reintento automático en caso de 401
     *
     * @param array $curlOptions Opciones de cURL
     * @param bool $returnArray Si debe retornar como array (true) o objeto (false)
     * @return mixed Respuesta de la API
     */
    private function parseName($fullName)
    {
        $parts = explode(' ', trim($fullName));
        $parts = array_values(array_filter($parts));
        
        if (count($parts) > 2) {
            $firstName = array_shift($parts);
            $lastName = implode(' ', $parts);
            return [$firstName, $lastName];
        }
        
        return (count($parts) > 0) ? $parts : [$fullName];
    }

    private function executeSiigoRequest($curlOptions, $returnArray = false)
    {
        $curl = curl_init();
        curl_setopt_array($curl, $curlOptions);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $decodedResponse = $returnArray ? json_decode($response, true) : json_decode($response);

        // Verificar si la respuesta tiene Status 401 (no autorizado)
        if ($httpCode == 401 || (is_array($decodedResponse) && isset($decodedResponse['Status']) && $decodedResponse['Status'] == 401)) {
            // Hacer login automático
            $loginResult = $this->configurarSiigo(null, true);

            if ($loginResult == 1) {
                // Reintentar la llamada una vez después del login
                $empresa = Empresa::find(1);
                $empresa->refresh(); // Refrescar para obtener el token actualizado

                // Actualizar el token en las opciones de cURL si existe Authorization header
                if (isset($curlOptions[CURLOPT_HTTPHEADER])) {
                    foreach ($curlOptions[CURLOPT_HTTPHEADER] as $key => $header) {
                        if (strpos($header, 'Authorization: Bearer') !== false) {
                            $curlOptions[CURLOPT_HTTPHEADER][$key] = 'Authorization: Bearer ' . $empresa->token_siigo;
                            break;
                        }
                    }
                }

                // Reintentar la llamada
                $curl = curl_init();
                curl_setopt_array($curl, $curlOptions);
                $response = curl_exec($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                curl_close($curl);

                $retryResponse = $returnArray ? json_decode($response, true) : json_decode($response);

                // Si después del reintento sigue siendo 401, retornar la respuesta original
                if ($httpCode == 401 || (is_array($retryResponse) && isset($retryResponse['Status']) && $retryResponse['Status'] == 401)) {
                    return $decodedResponse;
                }

                return $retryResponse;
            }
        }

        return $decodedResponse;
    }

    public function configurarSiigo(Request $request = null, $cron = null)
    {
        $empresa = (Auth::check()) ? Auth::user()->empresa() : Empresa::find(1);
        $usuario_siigo = null;
        $api_key_siigo = null;

        // Si se llama desde el método executeSiigoRequest, $request será null y $cron será true
        if ($request === null && $cron === true) {
            // Usar los datos guardados en la empresa para renovar el token
            // No hacer nada aquí, el código del else if se encargará
        } else {
            // Si viene desde la ruta web, obtener el Request usando el helper
            if ($request === null) {
                $request = request();
            }

            // Obtener parámetros del request
            $usuario_siigo = $request->input('usuario_siigo');
            $api_key_siigo = $request->input('api_key_siigo');
            $cron = $request->input('cron', null);
        }

        if ($empresa && $cron == null && $request->has('usuario_siigo') && $request->has('api_key_siigo')) {
            // Si los campos vienen vacíos (o null por el middleware), eliminamos la configuración
            if (empty($usuario_siigo) || empty($api_key_siigo)) {
                $empresa->usuario_siigo = null;
                $empresa->api_key_siigo = null;
                $empresa->token_siigo = null;
                $empresa->fecha_token_siigo = null;
                $empresa->save();
                return 1;
            }

            //Probando conexion de la api.
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api.siigo.com/auth',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode([
                    'username' => $usuario_siigo,
                    'access_key' => $api_key_siigo,
                ]),
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json'
                ),
            ));

            $response = curl_exec($curl);
            curl_close($curl);
            $response = json_decode($response);

            if (isset($response->access_token)) {
                $empresa->usuario_siigo = $usuario_siigo;
                $empresa->api_key_siigo = $api_key_siigo;
                $empresa->token_siigo = $response->access_token;
                $empresa->fecha_token_siigo = Carbon::now();
                $empresa->save();
                return 1;
            }

            return 0;
        }

        else if($cron && $empresa->usuario_siigo != "" && $empresa->api_key_siigo != ""){
            //Si ya tiene configurado el usuario y la api key, solo actualizamos el token.
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api.siigo.com/auth',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode([
                    'username' => $empresa->usuario_siigo,
                    'access_key' => $empresa->api_key_siigo,
                ]),
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json'
                ),
            ));

            $response = curl_exec($curl);
            curl_close($curl);
            $response = json_decode($response);

            if (isset($response->access_token)) {
                $empresa->token_siigo = $response->access_token;
                $empresa->fecha_token_siigo = Carbon::now();
                $empresa->save();
                return 1;
            }

            return 0;
        }
    }

    public function getModalInvoice(Request $request)
    {

        //Obtenemos los tipos de comprobantes que puede crear el cliente.
        $response_document_types = $this->getDocumentTypes();

        //Obtenemos los centros de costos
        $response_costs =  $this->getCostCenters();

        //obtenemos los tipos de pago
        $response_payments_methods = $this->getPaymentTypes();

        //obtenemos los sellers (usuarios)
        $response_users = $this->getSeller();

        if (isset($response_users['results'])) {
            $response_users = $response_users['results'];
        }

        if ($response_document_types) {
            return response()->json([
                'status' => 200,
                'tipos_comprobante' => $response_document_types,
                'centro_costos' => $response_costs,
                'tipos_pago' => $response_payments_methods,
                'usuarios' => $response_users,
            ]);
        } else {
            return response()->json([
                'status' => 400,
                'error' => "Ha ocurrido un error"
            ]);
        }
    }

    public static function getTaxes()
    {
        $empresa = Empresa::Find(1);
        $instance = new self();

        $curlOptions = array(
            CURLOPT_URL => 'https://api.siigo.com/v1/taxes',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Partner-Id: Integra',
                'Authorization: Bearer ' . $empresa->token_siigo,
            ),
        );

        $response = $instance->executeSiigoRequest($curlOptions, false);

        if (is_array($response)) {
            return response()->json([
                'status' => 200,
                'taxes' => $response
            ]);
        } else {
            return response()->json([
                'status' => 400,
                'error' => "Ha ocurrido un error"
            ]);
        }
    }

    public static function getDocumentTypes()
    {
        $empresa = Empresa::Find(1);
        $instance = new self();

        $curlOptions = array(
            CURLOPT_URL => 'https://api.siigo.com/v1/document-types?type=FV',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Partner-Id: Integra',
                'Authorization: Bearer ' . $empresa->token_siigo,
            ),
        );

        return $instance->executeSiigoRequest($curlOptions, true);
    }

    public static function getCostCenters()
    {
        $empresa = Empresa::Find(1);
        $instance = new self();

        $curlOptions = array(
            CURLOPT_URL => 'https://api.siigo.com/v1/cost-centers',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Partner-Id: Integra',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $empresa->token_siigo,
            ),
        );

        return $instance->executeSiigoRequest($curlOptions, true);
    }

    public static function getPaymentTypes()
    {
        $empresa = Empresa::Find(1);
        $instance = new self();

        $curlOptions = array(
            CURLOPT_URL => 'https://api.siigo.com/v1/payment-types?document_type=FV',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Partner-Id: Integra',
                'Authorization: Bearer ' . $empresa->token_siigo,
            ),
        );

        return $instance->executeSiigoRequest($curlOptions, true);
    }

    public static function getSeller()
    {
        $empresa = Empresa::Find(1);
        $instance = new self();

        $curlOptions = array(
            CURLOPT_URL => 'https://api.siigo.com/v1/users',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Partner-Id: Integra',
                'Authorization: Bearer ' . $empresa->token_siigo,
            ),
        );

        return $instance->executeSiigoRequest($curlOptions, true);
    }
    
    public function sendInvoice(Request $request, $factura = null, $isRetry = false)
{
    try {

        if ($factura === null) {
            $factura = Factura::findOrFail($request->factura_id);
        }

        $cliente_factura = $factura->cliente();

        $items_factura = ItemsFactura::join('inventario', 'inventario.id', 'items_factura.producto')
            ->where('factura', $factura->id)
            ->select(
                'items_factura.precio',
                'inventario.codigo_siigo',
                'inventario.siigo_id',
                'items_factura.cant',
                'items_factura.id_impuesto',
                'items_factura.producto',
                'inventario.ref',
                'inventario.producto as nombreProducto',
                'inventario.id',
                'items_factura.desc'
            )
            ->get();

        /* ===============================
           VALIDAR MAPEOS DE PRODUCTOS
        =============================== */
        $itemsSinMapeo = [];
        foreach ($items_factura as $item) {
            if (empty($item->codigo_siigo)) {
                $itemsSinMapeo[] = $item->nombreProducto;
            }
        }

        if (!empty($itemsSinMapeo)) {
            return response()->json([
                'status' => 400,
                'error'  => 'Productos sin mapeo en Siigo: ' . implode(', ', $itemsSinMapeo)
            ]);
        }

        /* ===============================
           RETENCIONES FACTURA
        =============================== */
        $retencionesFactura = FacturaRetencion::where('factura', $factura->id)->get();
        $totalRetencionSiigo = 0;
        $retencionesArray = [];
        $retefuenteArray = []; // Guardará las retenciones de fuente para aplicar por ítem

        // Necesitamos calcular el IVA global y subtotal para las retenciones
        $ivaTotalFactura = 0;
        $subtotalTotalFactura = 0;

        foreach ($items_factura as $item) {
            $cantidad = $item->cant;
            $precio   = $item->precio;
            $descuento = ($item->desc > 0) ? (($precio * $cantidad) * $item->desc) / 100 : 0;
            $subtotalConDesc = ($precio * $cantidad) - $descuento;
            $subtotalTotalFactura += $subtotalConDesc;

            if ($item->id_impuesto) {
                $impuesto = Impuesto::find($item->id_impuesto);
                if ($impuesto && $impuesto->siigo_id) {
                    $ivaTotalFactura += round($subtotalConDesc * ($impuesto->porcentaje / 100), 2);
                }
            }
        }

        foreach ($retencionesFactura as $ret) {
            $retObj = Retencion::find($ret->id_retencion);
            if ($retObj && $retObj->siigo_id) {
                $baseRet = ($retObj->tipo == 1) ? $ivaTotalFactura : $subtotalTotalFactura;
                $totalRetencionSiigo += round($baseRet * ($retObj->porcentaje / 100), 2);

                if ($retObj->tipo == 2) {
                    // Es Retefuente (se aplicará a nivel de ítem en Siigo)
                    $retefuenteArray[] = (int) $retObj->siigo_id;
                } else {
                    // Es ReteIVA o ReteICA (se aplica a nivel global)
                    $retencionesArray[] = [
                        "id"    => (int) $retObj->siigo_id,
                        "value" => round((float) $ret->valor, 2)
                    ];
                }
            }
        }

        /* ===============================
           ARMADO ITEMS
        =============================== */
        $array_items_factura = [];
        $totalFacturaOriginal = 0;
        $cont = 0;

        foreach ($items_factura as $item) {
            $cantidad = $item->cant;
            $precio   = $item->precio;
            $descuento = ($item->desc > 0) ? (($precio * $cantidad) * $item->desc) / 100 : 0;
            $subtotalConDesc = ($precio * $cantidad) - $descuento;

            $impuesto = null;
            $impuestoValor = 0;

            if ($item->id_impuesto) {
                $impuesto = Impuesto::find($item->id_impuesto);
                if ($impuesto && $impuesto->siigo_id) {
                    $impuestoValor = round($subtotalConDesc * ($impuesto->porcentaje / 100), 2);
                }
            }

            $totalFacturaOriginal += ($subtotalConDesc + $impuestoValor);

            $siigoItem = [
                "code"     => $item->codigo_siigo,
                "quantity" => (int) $cantidad,
                "price"    => number_format((float)$precio, 2, '.', ''),
                "taxes"    => []
            ];

            if ($descuento > 0) {
                $siigoItem["discount"] = number_format((float)$item->desc, 2, '.', '');
            }

            if ($impuesto && $impuesto->siigo_id) {
                $siigoItem["taxes"][] = [
                    "id"       => (int) $impuesto->siigo_id,
                    "tax_base" => round($subtotalConDesc, 2)
                ];
            }

            // Aplicar Retenciones en la fuente (tipo 2) a nivel de ítem
            foreach ($retefuenteArray as $retefuenteId) {
                $siigoItem["taxes"][] = [
                    "id"       => $retefuenteId,
                    "tax_base" => round($subtotalConDesc, 2)
                ];
            }

            $array_items_factura[] = $siigoItem;
            $cont++;
        }

        /* ===============================
           TOTAL NETO PARA SIIGO
        =============================== */
        // Usamos el total de retenciones que Siigo calculará internamente para evitar el error de pago
        $totalFacturaParaSiigo = round($totalFacturaOriginal - $totalRetencionSiigo, 2);

        /* ===============================
           TIPOS DE PAGO DESDE SIIGO
        =============================== */
        $empresa = Empresa::find(1);
        
        $paymentTypesRaw = $this->executeSiigoRequest([
            CURLOPT_URL            => 'https://api.siigo.com/v1/payment-types?document_type=FV',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => [
                'Partner-Id: Integra',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $empresa->token_siigo
            ]
        ], false);
    
        $paymentTypes = collect(json_decode(json_encode($paymentTypesRaw), true) ?? []);

        // Para factura PAGADA: busca por nombre enviado desde el request
        // Para factura ABIERTA: busca el tipo "Crédito" (due_date: true, type: Cartera)
        $pagoSeleccionado    = null;
        $pagoCredito         = null;

        foreach ($paymentTypes as $pt) {
            $nombreNormalizado = strtolower(trim($pt['name'] ?? ''));

            // Tipo de pago enviado por el usuario (factura pagada)
            if (isset($request->tipos_pago)) {
                $pagoRequest = strtolower(trim($request->tipos_pago));
                if (
                    (string)($pt['id'] ?? '') === $pagoRequest ||
                    $nombreNormalizado === $pagoRequest
                ) {
                    $pagoSeleccionado = $pt;
                }
            }

            // Tipo crédito para factura abierta:
            // Busca cualquiera que sea Cartera, acepte due_date y su nombre contenga "crédito"
            if (
                ($pt['type'] ?? '') === 'Cartera' &&
                ($pt['due_date'] ?? false) === true &&
                str_contains($nombreNormalizado, 'crédito')
            ) {
                $pagoCredito = $pt;
            }
        }

        // Fallback: si no encontró crédito por nombre, toma el primero de Cartera con due_date
        if (!$pagoCredito) {
            $pagoCredito = $paymentTypes->first(function ($pt) {
                return ($pt['type'] ?? '') === 'Cartera' && ($pt['due_date'] ?? false) === true;
            });
        }

        if (!$pagoCredito) {
            return response()->json([
                'status' => 400,
                'error'  => 'No se encontró un tipo de pago de crédito/cartera disponible en Siigo.'
            ]);
        }

        /* ===============================
           DATA FINAL SIIGO
        =============================== */
        $departamento = $cliente_factura->departamento();
        $municipio    = $cliente_factura->municipio();

        $draft = ($empresa->siigo_emitida == 1) ? false : true;

        $nombreArr = (function($c) {
            if ($c->dv) return [$c->nombre];
            $nArr = $this->parseName($c->nombre . (isset($c->apellido1) ? ' ' . $c->apellido1 . ' ' . $c->apellido2 : ''));
            if (count($nArr) < 2) {
                $f = \App\Contacto::where('nit', $c->nit)->first();
                if ($f) $nArr = $this->parseName($f->nombre . ' ' . $f->apellido1 . ' ' . $f->apellido2);
            }
            return $nArr;
        })($cliente_factura);

        // Sanitizar el nombre para remover comillas simples y dobles prohibidas por la API de Siigo
        $nombreArr = array_map(function($val) {
            return str_replace(["'", '"'], "", $val);
        }, $nombreArr);

        $customerData = [
            "person_type"    => $cliente_factura->dv ? "Company" : "Person",
            "id_type"        => $cliente_factura->dv ? "31" : "13",
            "identification" => $cliente_factura->nit,
            "branch_office"  => "0",
            "name"           => $nombreArr,
            "address" => [
                "address" => $cliente_factura->direccion ?: "Sin dirección",
                "city" => [
                    "country_code" => "CO",
                    "country_name" => "Colombia",
                    "state_code"   => $departamento->codigo,
                    "state_name"   => $departamento->nombre,
                    "city_code"    => $municipio->codigo_completo,
                    "city_name"    => $municipio->nombre
                ]
            ],
            "contacts" => [
                [
                    "first_name" => substr(isset($nombreArr[0]) && !empty($nombreArr[0]) ? $nombreArr[0] : "Contacto", 0, 50),
                    "last_name"  => substr(isset($nombreArr[1]) && !empty($nombreArr[1]) ? $nombreArr[1] : (isset($nombreArr[0]) && !empty($nombreArr[0]) ? $nombreArr[0] : "Apellido"), 0, 50),
                    "email"      => !empty($cliente_factura->email) ? $cliente_factura->email : "correo@ejemplo.com"
                ]
            ]
        ];

        $celular = !empty($cliente_factura->celular) ? $cliente_factura->celular : (!empty($cliente_factura->telefono1) ? $cliente_factura->telefono1 : null);
        if ($celular) {
            $customerData["phones"] = [
                ["number" => $celular]
            ];
        }

        /* ===============================
           AUTOCREACIÓN / SINCRONIZACIÓN DE CLIENTE
        =============================== */
        // Consultar si el cliente ya existe en Siigo antes de proceder
        $checkCustomer = $this->executeSiigoRequest([
            CURLOPT_URL            => 'https://api.siigo.com/v1/customers?identification=' . $cliente_factura->nit,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => [
                'Partner-Id: Integra',
                'Authorization: Bearer ' . $empresa->token_siigo
            ]
        ], true);

        if (!isset($checkCustomer['results']) || count($checkCustomer['results']) === 0) {
            // El cliente no existe en Siigo, se procede a crearlo de manera transparente
            $newCustomerPayload = [
                "type"        => "Customer",
                "person_type" => $cliente_factura->dv ? "Company" : "Person",
                "id_type"     => $cliente_factura->dv ? "31" : "13",
                "identification" => $cliente_factura->nit,
                "name"        => $nombreArr,
                "active"      => true,
                "address"     => [
                    "address" => $cliente_factura->direccion ?: "Sin dirección",
                    "city" => [
                        "country_code" => "Co",
                        "state_code"   => (string)$departamento->codigo,
                        "city_code"    => (string)$municipio->codigo_completo
                    ]
                ],
                "contacts" => [
                    [
                        "first_name" => substr(isset($nombreArr[0]) && !empty($nombreArr[0]) ? $nombreArr[0] : "Contacto", 0, 50),
                        "last_name"  => substr(isset($nombreArr[1]) && !empty($nombreArr[1]) ? $nombreArr[1] : (isset($nombreArr[0]) && !empty($nombreArr[0]) ? $nombreArr[0] : "Apellido"), 0, 50),
                        "email"      => !empty($cliente_factura->email) ? $cliente_factura->email : "correo@ejemplo.com"
                    ]
                ]
            ];

            // Si es un cliente de tipo Empresa (NIT), Siigo requiere DV y responsabilidades fiscales
            if ($cliente_factura->dv) {
                $newCustomerPayload["check_digit"] = (string)$cliente_factura->dv;
                $newCustomerPayload["fiscal_responsibilities"] = [
                    ["code" => "R-99-PN"] // Responsabilidad tributaria estándar
                ];
                $newCustomerPayload["fiscal_details"] = [
                    ["code" => "05"] // No responsable de IVA estándar
                ];
            }

            if ($celular) {
                $newCustomerPayload["phones"] = [
                    ["number" => $celular]
                ];
            }

            $createCustomerRes = $this->executeSiigoRequest([
                CURLOPT_URL            => 'https://api.siigo.com/v1/customers',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_POSTFIELDS     => json_encode($newCustomerPayload),
                CURLOPT_HTTPHEADER     => [
                    'Partner-Id: Integra',
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $empresa->token_siigo
                ]
            ], true);

            if (!isset($createCustomerRes['id']) && isset($createCustomerRes['Errors'])) {
                $errMsgs = [];
                foreach ($createCustomerRes['Errors'] as $err) {
                    if (isset($err['Message'])) {
                        $errMsgs[] = $err['Message'];
                    }
                }
                return response()->json([
                    'status' => 400,
                    'error'  => 'No se pudo registrar automáticamente al cliente en Siigo: ' . implode(' | ', $errMsgs)
                ]);
            }
        }

        $data = [
            "document" => ["id" => (int) $request->tipo_comprobante],
            "date"     => $factura->fecha,
            "draft"    => $draft,
            "customer" => [
                "identification" => $cliente_factura->nit,
                "branch_office"  => 0
            ],
            "seller"   => (int) $request->usuario,
            "items"    => $array_items_factura,
        ];

        if (!empty($retencionesArray)) {
            $data["retentions"] = $retencionesArray;
        }

        /* ===============================
           PAGOS
        =============================== */
        if ($factura->estatus == 0) {
            // Factura PAGADA → usa el tipo de pago seleccionado por el usuario
            if (!$pagoSeleccionado) {
                return response()->json([
                    'status' => 400,
                    'error'  => "No se encontró el tipo de pago '{$request->tipos_pago}' en Siigo."
                ]);
            }

            $data["payments"] = [
                [
                    "id"       => (int) $pagoSeleccionado['id'],
                    "value"    => number_format($totalFacturaParaSiigo, 2, '.', ''),
                    "due_date" => $factura->fecha
                ]
            ];
        } else {
            // Factura ABIERTA → usa crédito/cartera, queda pendiente de cobro
            $data["payments"] = [
                [
                    "id"       => (int) $pagoCredito['id'],
                    "value"    => number_format($totalFacturaParaSiigo, 2, '.', ''),
                    "due_date" => $factura->vencimiento
                ]
            ];
        }

        if (!$draft) {
            $data["stamp"] = ["send" => true];
            $data["mail"]  = ["send" => true];
        }

        /* ===============================
           ENVÍO SIIGO
        =============================== */
        $curlOptions = [
            CURLOPT_URL            => 'https://api.siigo.com/v1/invoices',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => [
                'Partner-Id: Integra',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $empresa->token_siigo
            ]
        ];

        $response = $this->executeSiigoRequest($curlOptions, true);

        if (isset($response['id'])) {
            $factura->siigo_id   = $response['id'];
            $factura->siigo_name = $response['name'];
            $factura->save();

            return response()->json([
                'status'  => 200,
                'message' => 'Factura creada correctamente en Siigo'
            ]);
        }

        $errorMessage   = 'Error desconocido en Siigo';
        $hasInvalidDate = false;

        if (isset($response['Errors']) && is_array($response['Errors']) && count($response['Errors']) > 0) {
            $messages = [];
            foreach ($response['Errors'] as $err) {
                if (isset($err['Message'])) {
                    $messages[] = $err['Message'];
                    if (strpos($err['Message'], 'Invalid date') !== false) {
                        $hasInvalidDate = true;
                    }
                }
            }
            if (count($messages) > 0) {
                $errorMessage = implode(' | ', $messages);
            }
        } elseif (isset($response['Message'])) {
            $errorMessage = $response['Message'];
            if (strpos($errorMessage, 'Invalid date') !== false) {
                $hasInvalidDate = true;
            }
        }

        if ($hasInvalidDate && !$isRetry) {
            $factura->fecha = \Carbon\Carbon::now()->format('Y-m-d');
            $factura->save();
            return $this->sendInvoice($request, $factura, true);
        }

        return response()->json([
            'status' => 400,
            'error'  => $errorMessage
        ]);

    } catch (\Throwable $th) {
        return response()->json([
            'status' => 400,
            'error'  => 'Error al crear factura en Siigo: ' . $th->getMessage()
        ]);
    }
}
    
    public function impuestosSiigo()
    {
        $empresa = Empresa::Find(1);

        $curlOptions = array(
            CURLOPT_URL => 'https://api.siigo.com/v1/taxes',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Partner-Id: Integra',
                'Authorization: Bearer ' . $empresa->token_siigo,
            ),
        );

        return $this->executeSiigoRequest($curlOptions, false);
    }

    public function mapeoImpuestos()
    {
        $this->getAllPermissions(Auth::user()->id);
        view()->share(['title' => 'Mapeo de impuestos', 'icon' => 'fa fa-cogs', 'seccion' => 'Configuración']);
        $impuestos = Impuesto::where('estado', 1)->get()->where('porcentaje', '!=', 0);
        $retenciones = Retencion::where('estado', 1)->where('porcentaje', '!=', 0)->get();
        $impuestosSiigo = $this->impuestosSiigo();
        return view('siigo.impuestos', compact('impuestos','retenciones','impuestosSiigo'));
    }

    public function storeImpuestos(Request $request){

        for($i = 0; $i < count($request->imp); $i++){
            $impuesto = Impuesto::find($request->imp[$i]);
            $impuesto->siigo_id = $request->siigo_imp[$i];
            $impuesto->save();
        }

        for($i = 0; $i < count($request->ret); $i++){
            $retencion = Retencion::find($request->ret[$i]);
            $retencion->siigo_id = $request->siigo_ret[$i];
            $retencion->save();
        }

        return redirect()->route('siigo.mapeo_impuestos')->with('success', 'Impuesto y Retenciones guardados correctamente.');
    }

    public function mapeoVendedores(){
        $this->getAllPermissions(Auth::user()->id);
        view()->share(['title' => 'Mapeo de vendedores', 'icon' => 'fa fa-cogs', 'seccion' => 'Configuración']);
        $vendedores = Vendedor::where('estado', 1)->get();
        $vendedoresSiigo = $this->getSeller()['results'];

        return view('siigo.vendedores', compact('vendedores','vendedoresSiigo'));
    }

    public function storeVendedores(Request $request){
        for($i = 0; $i < count($request->vendedores); $i++){
            $vendedor = Vendedor::find($request->vendedores[$i]);
            $vendedor->siigo_id = $request->siigo_vendedores[$i];
            $vendedor->save();
        }

        return redirect()->route('siigo.mapeo_vendedores')->with('success', 'Vendedores guardados correctamente.');
    }

    public function getProducts($page = 1, $pageSize = 25)
    {
        $empresa = Empresa::find(1);

        $url = 'https://api.siigo.com/v1/products'
            . '?page=' . $page
            . '&page_size=' . $pageSize
            . '&order_by=code'
            . '&order_direction=asc'
            . '&status=active';

        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Partner-Id: Integra',
                'Authorization: Bearer ' . $empresa->token_siigo,
            ],
        ];

        return $this->executeSiigoRequest($curlOptions, true);
    }


    public function mapeoProductos()
    {
        $this->getAllPermissions(Auth::user()->id);

        view()->share([
            'title'   => 'Mapeo de productos',
            'icon'    => 'fa fa-cogs',
            'seccion' => 'Configuración'
        ]);

        $productos = Inventario::where('status', 1)->get();

        // 🔹 Traer todos los productos de Siigo
        $productosSiigo = [];
        $page = 1;
        $pageSize = 25;
        $total = 0;

        do {
            $response = $this->getProducts($page, $pageSize);

            if (!empty($response['results'])) {
                $productosSiigo = array_merge($productosSiigo, $response['results']);
            }

            $total = $response['pagination']['total_results'] ?? 0;
            $page++;

        } while (count($productosSiigo) < $total);

        return view('siigo.productos', compact('productos', 'productosSiigo'));
    }


    public function storeProductos(Request $request)
    {
        for ($i = 0; $i < count($request->productos); $i++) {

            $producto = Inventario::find($request->productos[$i]);

            // Valor que viene del select de Siigo
            $siigoValue = $request->siigo_productos[$i] ?? null;

            if (empty($siigoValue) || $siigoValue === '0') {
                $producto->siigo_id = null;
                $producto->codigo_siigo = null;
                $producto->save();
                continue;
            }

            if (strpos($siigoValue, '|') === false) {
                // Formato inválido → no guardamos basura
                $producto->siigo_id = null;
                $producto->codigo_siigo = null;
                $producto->save();
                continue;
            }

            [$siigo_id, $siigo_code] = explode('|', $siigoValue, 2);

            // Validación final por seguridad
            if (empty($siigo_id) || empty($siigo_code)) {
                $producto->siigo_id = null;
                $producto->siigo_code = null;
            } else {
                $producto->siigo_id = trim($siigo_id);
                $producto->codigo_siigo = trim($siigo_code);
            }

            $producto->save();
        }

        return redirect()
            ->route('siigo.mapeo_productos')
            ->with('success', 'Productos guardados correctamente.');
    }



    public function createItem($item){

        //Validacion para creacion de item en siigo en caso tal de que no exista.
        try {
            $empresa = Empresa::Find(1);
            $iva = Impuesto::find($item->id_impuesto);

            $curlOptionsGrupo = array(
                CURLOPT_URL => 'https://api.siigo.com/v1/account-groups',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_POSTFIELDS => '',
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Partner-Id: Integra',
                    'Authorization: Bearer ' . $empresa->token_siigo,
                ),
            );

            $grupo = $this->executeSiigoRequest($curlOptionsGrupo, true);

            $data = [
                "code" => $item->ref,
                "name" => $item->nombreProducto,
                "price" => round($item->precio,0),
                "status" => "active",
                "type" => "Product",
                "unit_measure" => "unit",
                "account_group" => $grupo[0]['id']
            ];

            if ($iva && $iva->siigo_id != null) {
                $data['taxes'] = [
                    [
                        "id" => $iva->siigo_id
                    ]
                ];
            }

            $curlOptionsProducto = array(
                CURLOPT_URL => 'https://api.siigo.com/v1/products',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Partner-Id: Integra',
                    'Authorization: Bearer ' . $empresa->token_siigo,
                ),
            );

            $response = $this->executeSiigoRequest($curlOptionsProducto, true);

            if (isset($response['id'])) {
                //Guardamos el codigo siigo en el item de la factura.
                Inventario::where('id', $item->id)->update(['siigo_id' => $response['id'], 'codigo_siigo' => $response['code']]);
            } else {
                return response()->json([
                    'status' => 400,
                    'error' => "Error al crear el producto en Siigo"
                ]);
            }
        } catch (\Throwable $th) {

            return response()->json([
                'status' => 400,
                'error' => "Error al crear el producto en Siigo: " . $th->getMessage()
            ]);
        }

    }
    
    public function envioMasivoSiigo($facturas, $ingreso = null)
    {
        try {
    
            $facturas = explode(",", $facturas);
            $lstResultados = [];
    
            // Fetch payment types and seller data once before the loop to optimize performance
            $tiposPago = collect($this->getPaymentTypes());
            $sellerData = $this->getSeller();
            $sellers = collect($sellerData['results'] ?? []);

            // Obtener tipo de comprobante por defecto de la configuración de Mikrotik
            $defaultTipoComprobante = \DB::table('mikrotik')
                ->whereNotNull('tipodoc_siigo_id')
                ->where('tipodoc_siigo_id', '!=', '')
                ->where('tipodoc_siigo_id', '!=', 0)
                ->value('tipodoc_siigo_id');

            // Si no hay ninguno configurado en Mikrotik, obtenemos el primer tipo de comprobante de venta (FV) activo en Siigo
            if (!$defaultTipoComprobante) {
                $docTypes = collect($this->getDocumentTypes());
                $defaultTipoComprobante = $docTypes->first()['id'] ?? null;
            }

            // Vendedor por defecto de Siigo (el primero activo en la cuenta de Siigo)
            $defaultSellerId = $sellers->first()['id'] ?? null;
 
            $tipoPagoCredito = $tiposPago
                ->whereIn('name', ['Pago a crédito', 'Crédito'])
                ->first();

            $tipoPagoEfectivo = $tiposPago
                ->whereIn('name', ['Efectivo', 'Contado'])
                ->first();

            foreach ($facturas as $facturaId) {
    
                $factura = Factura::find($facturaId);
    
                if (!$factura || !empty($factura->siigo_id)) {
                    continue;
                }
    
                // ==============================
                // FECHAS → DEFINIR SI ES CRÉDITO
                // ==============================
                $fechaCreacion    = Carbon::parse($factura->fecha)->startOfDay();
                $fechaVencimiento = Carbon::parse($factura->vencimiento)->startOfDay();
    
                $esCredito = $fechaVencimiento->gt($fechaCreacion);
    
                // ==============================
                // SELECCIÓN SEGURA DEL PAGO
                // ==============================
                $tipoPagoSeleccionado = null;
    
                if ($esCredito) {
                    if ($tipoPagoCredito) {
                        $tipoPagoSeleccionado = $tipoPagoCredito['id'];
                    } elseif ($tipoPagoEfectivo) {
                        $tipoPagoSeleccionado = $tipoPagoEfectivo['id'];
                    }
                } else {
                    if ($tipoPagoEfectivo) {
                        $tipoPagoSeleccionado = $tipoPagoEfectivo['id'];
                    } elseif ($tipoPagoCredito) {
                        $tipoPagoSeleccionado = $tipoPagoCredito['id'];
                    }
                }
    
                // ==============================
                // VALIDACIÓN CRÍTICA
                // ==============================
                if (!$tipoPagoSeleccionado) {
                    $lstResultados[] = [
                        'factura_id' => $facturaId,
                        'codigo'     => $factura->codigo,
                        'resultado'  => [
                            'status' => 400,
                            'error'  => 'No existe forma de pago válida en Siigo (Crédito / Efectivo).'
                        ]
                    ];
                    continue;
                }
    
                // ==============================
                // DATOS ADICIONALES
                // ==============================
                $servidor = $factura->servidor();
                $usuario  = null;

                if ($servidor && is_object($servidor) && isset($servidor->email_siigo)) {
                    $usuario = $sellers->where('username', $servidor->email_siigo)->first()['id'] ?? null;
                }

                // Fallback 1: usar el vendedor de la factura si no hay usuario por servidor
                if (!$usuario && $factura->vendedorObj) {
                    $usuario = $factura->vendedorObj->siigo_id;
                }

                // Fallback 2: usar el primer vendedor de la cuenta de Siigo
                if (!$usuario) {
                    $usuario = $defaultSellerId;
                }
    
                // ==============================
                // REQUEST PARA sendInvoice
                // ==============================
                $request = new Request();
                $tipoComprobante = ($servidor && is_object($servidor) && isset($servidor->tipodoc_siigo_id)) ? $servidor->tipodoc_siigo_id : null;

                // Fallback para tipo de comprobante si no se pudo determinar por el servidor/contrato
                if (!$tipoComprobante) {
                    $tipoComprobante = $defaultTipoComprobante;
                }

                $request->merge([
                    'tipos_pago'       => $tipoPagoSeleccionado,
                    'factura_id'       => $facturaId,
                    'usuario'          => $usuario,
                    'tipo_comprobante' => $tipoComprobante
                ]);
    
                if (!$request->tipo_comprobante) {
                    $lstResultados[] = [
                        'factura_id' => $facturaId,
                        'codigo'     => $factura->codigo,
                        'resultado'  => [
                            'status' => 400,
                            'error'  => 'No se ha configurado el tipo de comprobante Siigo para el servidor de esta factura ni existe un valor por defecto.'
                        ]
                    ];
                    continue;
                }

                // ==============================
                // ENVÍO A SIIGO
                // ==============================
                $response = $this->sendInvoice($request, $factura);
    
                if ($response instanceof \Illuminate\Http\JsonResponse) {
                    $data = $response->getData(true);
                } else {
                    $data = [
                        'status' => 500,
                        'error'  => 'Respuesta inválida desde sendInvoice'
                    ];
                }
    
                $lstResultados[] = [
                    'factura_id' => $facturaId,
                    'codigo'     => $factura->codigo,
                    'resultado'  => $data
                ];
            }
    
            return response()->json([
                'success' => true,
                'text'    => 'Conversión masiva de facturas electrónicas terminada',
                'resultados' => $lstResultados
            ]);
    
        } catch (\Throwable $th) {
    
            return response()->json([
                'success' => false,
                'text' => 'Error obteniendo los datos de Siigo: ' . $th->getMessage(),
                'resultados' => []
            ]);
        }
    }    
}
