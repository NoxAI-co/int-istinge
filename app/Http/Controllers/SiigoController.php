<?php

namespace App\Http\Controllers;

use App\Empresa;
use App\Impuesto;
use App\Model\Ingresos\Factura;
use App\Model\Ingresos\ItemsFactura;
use App\Model\Inventario\Inventario;
use App\Retencion;
use App\Vendedor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SiigoController extends Controller
{
    public function configurarSiigo(Request $request)
    {
        $empresa = Empresa::find(Auth::user()->empresa);

        if ($empresa) {

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
                    'username' => $request->usuario_siigo,
                    'access_key' => $request->api_key_siigo,
                ]),
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json'
                ),
            ));

            $response = curl_exec($curl);
            curl_close($curl);
            $response = json_decode($response);

            if (isset($response->access_token)) {
                $empresa->usuario_siigo = $request->usuario_siigo;
                $empresa->api_key_siigo = $request->api_key_siigo;
                $empresa->token_siigo = $response->access_token;
                $empresa->fecha_token_siigo = Carbon::now();
                $empresa->save();
                return 1;
            }

            return 0;
            // dd($response->response[0]->name);
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
        $curl = curl_init();

        curl_setopt_array($curl, array(
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
        ));

        $response = curl_exec($curl);
        $response = json_decode($response);
        curl_close($curl);

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
        $curl = curl_init();

        curl_setopt_array($curl, array(
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
        ));

        $response_document_types = curl_exec($curl);
        curl_close($curl);
        return $response_document_types = json_decode($response_document_types, true);
    }

    public static function getCostCenters()
    {

        $empresa = Empresa::Find(1);
        $curl = curl_init();

        curl_setopt_array($curl, array(
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
        ));

        $response_costs = curl_exec($curl);
        curl_close($curl);
        return $response_costs = json_decode($response_costs, true);
    }

    public static function getPaymentTypes()
    {
        $empresa = Empresa::Find(1);
        $curl = curl_init();

        curl_setopt_array($curl, array(
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
        ));

        $response = curl_exec($curl);
        $response = json_decode($response, true);
        curl_close($curl);
        return $response;
    }

    public static function getSeller()
    {
        $empresa = Empresa::Find(1);
        $curl = curl_init();

        curl_setopt_array($curl, array(
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
        ));

        $response = curl_exec($curl);
        $response = json_decode($response, true);
        curl_close($curl);
        return $response;
    }

    public function sendInvoice(Request $request, $factura = null)
    {

        try {
            if($factura == null){
                $factura = Factura::Find($request->factura_id);
            }

            $cliente_factura = $factura->cliente();
            $items_factura = ItemsFactura::join('inventario', 'inventario.id', 'items_factura.producto')
                ->where('factura', $factura->id)
                ->select('items_factura.precio','inventario.codigo_siigo','items_factura.cant',
                'items_factura.id_impuesto','items_factura.producto','inventario.ref',
                'inventario.producto as nombreProducto','inventario.id')
                ->get();

            $empresa = Empresa::Find(1);
            $departamento = $cliente_factura->departamento();
            $municipio = $cliente_factura->municipio();

            $array_items_factura = [];
            $douTotalFactura = 0;
            $cont = 0;

            foreach ($items_factura as $item) {
                if (!isset($item['codigo_siigo']) || $item['codigo_siigo'] == null) {
                    //Si no tiene código Siigo,lo creamos.
                    $respuesta = $this->createItem($item);

                    $item = Inventario::leftJoin('items_factura as if', 'if.producto', 'inventario.id')
                        ->select('if.precio', 'inventario.codigo_siigo', 'if.cant', 'if.id_impuesto',
                        'if.producto','inventario.ref', 'inventario.producto as nombreProducto')
                        ->where('inventario.id', $item->id)
                        ->first();

                }

                $douPrecio = round($item['precio'], 2);
                $intCantidad = round($item['cant']);
                $douSubtotalItem = $douPrecio * $intCantidad;
                $douImpuestoItem = 0;

                $impuestoItem = Impuesto::find($item->id_impuesto);
                if ($impuestoItem && $impuestoItem->siigo_id != null) {
                    $douImpuestoItem = $douSubtotalItem * ($impuestoItem->porcentaje / 100);
                }

                $douTotalFactura += ($douSubtotalItem + $douImpuestoItem);

                $array_items_factura[] = [
                    "code" => $item['codigo_siigo'],
                    "quantity" => $intCantidad,
                    "price" => number_format(round($douPrecio, 2), 2, '.', ''),
                ];

                if ($impuestoItem && $impuestoItem->siigo_id != null) {
                    $array_items_factura[$cont]['taxes'] = [
                        [
                            "id" => $impuestoItem->siigo_id
                        ]
                    ];
                }

                $cont++;
            }

            $apellidos = $cliente_factura->apellido1 . ($cliente_factura->apellido2 != "" ?  " " . $cliente_factura->apellido2 : "");

            $data = [
                "document" => [
                    "id" => $request->tipo_comprobante
                ],
                "date" => Carbon::now()->format('Y-m-d'),
                "customer" => [
                    "person_type" => $cliente_factura->dv != null ? 'Company' : 'Person',
                    "id_type" => $cliente_factura->dv != null ? "31" : "13", //13 cedula 31 nit
                    "identification" => $cliente_factura->nit,
                    "branch_office" => "0", //por defecto 0
                    "name" => $cliente_factura->dv != null
                        ? [$cliente_factura->nombre . " " . $apellidos]
                        : [ $cliente_factura->nombre, $apellidos],
                    "address" => [
                        "address" => $cliente_factura->direccion,
                        "city" => [
                            "country_code" => $cliente_factura->fk_idpais,
                            "country_name" => "Colombia",
                            "state_code" => $departamento->codigo,
                            "state_name" => $departamento->nombre,
                            "city_code" => $municipio->codigo_completo,
                            "city_name" => $municipio->nombre
                        ],
                        "postal_code" => $cliente_factura->cod_postal
                    ],
                    "phones" => [
                        [
                            "indicative" => "57",
                            "number" => $cliente_factura->celular,
                            "extension" => ""
                        ]
                    ],
                    "contacts" => [
                        [
                            "first_name" => $cliente_factura->nombre,
                            "last_name" => $cliente_factura->apellido1 . " " . $cliente_factura->apellido2,
                            "email" => $cliente_factura->email,
                            "phone" => [
                                "indicative" => "57",
                                "number" => $cliente_factura->celular,
                                "extension" => ""
                            ]
                        ]
                    ]
                ],
                "seller" => $request->usuario,
                'items' => $array_items_factura,
                "payments" => [
                    [
                        "id" => $request->tipos_pago,
                        'value' => number_format(round($douTotalFactura, 2), 2, '.', ''),
                        "due_date" => $factura->vencimiento
                    ]
                ]
            ];


            //Envio a curl invoice
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api.siigo.com/v1/invoices',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => array(
                    'Partner-Id: Integra',
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $empresa->token_siigo,
                ),
            ));

            $response = curl_exec($curl);
            $response = json_decode($response, true);
            curl_close($curl);

            if(isset($response['id'])){
                $factura->siigo_id = $response['id'];
                $factura->siigo_name = $response['name'];
                $factura->save();

                return response()->json([
                    'status' => 200,
                    'message' => "Factura creada correctamente en Siigo",
                    'factura_id' => $factura->id
                ]);
                //Guardamos los items de la factura en siigo.
            }else{
                $mensajes = '';
                if (isset($response['Errors'])){
                    foreach ($response['Errors'] as $error) {
                        $mensajes .= $error['Message'] . ' ';
                    }
                } elseif (isset($response['Message'])) {
                    $mensajes = $response['Message'];
                }


                return response()->json([
                    'status' => 400,
                    'error' => "Error al crear la factura en Siigo " . ($mensajes != '' ? $mensajes : ''),

                ]);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 400,
                'error' => "Error al crear la factura en Siigo: " . $th->getMessage()
            ]);
        }

    }

    public function impuestosSiigo()
    {
        $empresa = Empresa::Find(1);
        $curl = curl_init();

        curl_setopt_array($curl, array(
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
        ));

        $response = curl_exec($curl);
        $response = json_decode($response);
        curl_close($curl);
        return $response;
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

    public function getProducts(){
        $empresa = Empresa::Find(1);
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.siigo.com/v1/products?limit=1000&offset=0&order_by=code&order_direction=asc&status=active',
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
        ));

        $response = curl_exec($curl);
        $response = json_decode($response, true);
        curl_close($curl);
        return $response;
    }

    public function mapeoProductos(){
        $this->getAllPermissions(Auth::user()->id);
        view()->share(['title' => 'Mapeo de productos', 'icon' => 'fa fa-cogs', 'seccion' => 'Configuración']);
        $productos = Inventario::where('status', 1)->get();
        $productosSiigo = $this->getProducts()['results'];

        return view('siigo.productos', compact('productos','productosSiigo'));
    }

    public function storeProductos(Request $request){
        for($i = 0; $i < count($request->productos); $i++){
            $producto = Inventario::find($request->productos[$i]);
            $producto->siigo_id = $request->siigo_productos[$i];
            $producto->save();
        }

        return redirect()->route('siigo.mapeo_productos')->with('success', 'Productos guardados correctamente.');
    }

    public function createItem($item){

        //Validacion para creacion de item en siigo en caso tal de que no exista.
        try {
            $curl = curl_init();
            $empresa = Empresa::Find(1);
            $iva = Impuesto::find($item->id_impuesto);

            curl_setopt_array($curl, array(
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
            ));

            $grupo = curl_exec($curl);
            $grupo = json_decode($grupo, true);

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

            curl_setopt_array($curl, array(
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
            ));

            $response = curl_exec($curl);
            $response = json_decode($response, true);

            curl_close($curl);

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

    public function envioMasivoSiigo($facturas)
    {
        try {
            $facturas = explode(",", $facturas);
            $lstResultados = [];

            for ($i = 0; $i < count($facturas); $i++) {
                $request = new Request();

                $factura = Factura::Find($facturas[$i]);

                if($factura->siigo_id == null || $factura->siigo_id == ""){
                    $tiposPago = collect($this->getPaymentTypes());
                    $credito = $tiposPago->firstWhere('name', 'Crédito')['id'];
                    $servidor = $factura->servidor();
                    $usuario = collect($this->getSeller())->last()[1]['id'];



                    $request->merge(['tipos_pago' => $credito]);
                    $request->merge(['factura_id' => $facturas[$i]]);
                    $request->merge(['usuario' => $usuario]);
                    $request->merge(['tipo_comprobante' => $servidor->tipodoc_siigo_id]);

                    $response = $this->sendInvoice($request,$factura);
                    // Extraer contenido del JSON si es instancia de Response
                    if ($response instanceof \Illuminate\Http\JsonResponse) {
                        $data = $response->getData(true);
                    } else {
                        $data = ['status' => 500, 'error' => 'Respuesta no válida de sendInvoice'];
                    }

                    $lstResultados[] = [
                        'factura_id' => $facturas[$i],
                        'codigo' => $factura->codigo,
                        'resultado' => $data
                    ];

                }
            }

            return response()->json([
                'success' => true,
                'text' => 'Conversión masiva de facturas electrónicas terminada',
                'resultados' => $lstResultados
            ]);

        } catch (\Throwable $th) {

                return response()->json([
                    'success' => false,
                    'text' => 'Error obteniendo los datos de siigo: ' . $th->getMessage(),
                    'resultados' => []
                ]);
        }
    }

}
