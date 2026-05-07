<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Empresa;
use App\Banco; use App\Contacto;
use App\Categoria; use App\Retencion;
use App\Movimiento; use App\Impuesto;
use App\Numeracion;
use App\Model\Inventario\Inventario;
use App\Model\Ingresos\Factura;
use App\Model\Ingresos\ItemsFactura;
use Illuminate\Support\Facades\Schema;
use App\Model\Ingresos\Ingreso;
use App\Model\Ingresos\IngresosFactura;
use App\Model\Ingresos\IngresosCategoria;
use App\Model\Ingresos\IngresosRetenciones;
use App\Model\Gastos\Gastos;
use App\Model\Gastos\GastosCategoria;
use Carbon\Carbon;  use Mail;
use Validator; use Illuminate\Validation\Rule;
use bcrypt;
use Barryvdh\DomPDF\Facade as PDF;
use App\Contrato;
use App\Mikrotik;
use App\User;
use App\CRM;
use App\Campos;
use Config;
use App\ServidorCorreo;
use App\Integracion;
use App\Puc;
use App\PucMovimiento;
use App\Anticipo;
use App\FormaPago;
use App\NumeracionFactura;
use App\Funcion;
use App\Instance;
use App\MovimientoLOG;
use App\Plantilla;
use App\Services\WapiService;

include_once(app_path() .'/../public/routeros_api.class.php');
use RouterosAPI;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

include_once(app_path() .'/../public/PHPExcel/Classes/PHPExcel.php');
use PHPExcel;
use PHPExcel_IOFactory;
use PHPExcel_Style_Alignment;
use PHPExcel_Style_Fill;
use PHPExcel_Style_Border;
use PHPExcel_Style_NumberFormat;
use PHPExcel_Shared_ZipArchive;

use App\Producto;
use App\GrupoCorte;
use App\TerminosPago;
use App\WhatsappMetaLog;
use App\Helpers\CamposDinamicosHelper;
use App\PlanesVelocidad;
use App\Vendedor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Traits\CentralizedWhatsApp;

class IngresosController extends Controller
{
    use CentralizedWhatsApp;
    public function __construct() {
        $this->middleware('auth');
        view()->share(['seccion' => 'facturas', 'subseccion' => 'ingresos', 'title' => 'Pagos / Ingresos', 'icon' =>'fas fa-plus']);
    }

    public function index(Request $request){
        $this->getAllPermissions(Auth::user()->id);
        if (Auth::user()->rol == 8 || Auth::user()->cuenta > 0) {
            $bancos = Banco::where('estatus', 1)->where('empresa', Auth::user()->empresa)->whereIn('id', auth()->user()->cuentas())->get();
        } else {
            $bancos = Banco::where('empresa', Auth::user()->empresa)->where('estatus', 1)->get();
        }
        $clientes = (Auth::user()->oficina && Auth::user()->empresa()->oficina) ? Contacto::where('status', 1)->where('empresa', Auth::user()->empresa)->where('oficina', Auth::user()->oficina)->orderBy('nombre','asc')->get() : Contacto::where('status', 1)->where('empresa', Auth::user()->empresa)->orderBy('nombre','asc')->get();
        //$clientes = Contacto::where('empresa', auth()->user()->empresa)->orderBy('nombre','asc')->get();
        $metodos = DB::table('metodos_pago')->where('id', '!=', 8)->where('id', '!=', 7)->get();
        $tabla = Campos::join('campos_usuarios', 'campos_usuarios.id_campo', '=', 'campos.id')->where('campos_usuarios.id_modulo', 5)->where('campos_usuarios.id_usuario', Auth::user()->id)->where('campos_usuarios.estado', 1)->orderBy('campos_usuarios.orden', 'ASC')->get();

        return view('ingresos.indexnew', compact('bancos','clientes','metodos','tabla'));
    }

    public function ingresos(Request $request){

        $user = Auth::user();
        $this->getAllPermissions($user->id);
        $empresa = $user->empresa;
        $modoLectura = $user->modo_lectura();
        $ingresos = Ingreso::query()
        ->select('ingresos.*','contactos.nombre','contactos.apellido1','contactos.apellido2','bancos.nombre as banco')
        ->leftjoin('ingresos_factura as if', 'if.ingreso', '=', 'ingresos.id')
        ->leftJoin('factura as f','f.id','=','if.factura')
        ->leftJoin('facturas_contratos as fc','fc.factura_id','=','f.id')
        ->leftJoin('contracts as cs1','cs1.nro','=','fc.contrato_nro')
        ->leftJoin('contracts as cs2', 'cs2.id', '=', 'f.contrato_id')   // contrato directo en factura
        ->leftjoin('contactos', 'contactos.id', '=', 'ingresos.cliente')
        ->join('bancos', 'bancos.id', '=', 'ingresos.cuenta')
        ;

        // if ($user->servidores->count() > 0) {
        //     $servers = $user->servidores->pluck('id')->toArray();

        //     $ingresos->where(function ($query) use ($servers) {
        //         $query->where(function ($q1) use ($servers) {
        //             // Caso 1: contratos con server del usuario (por cualquiera de los dos contratos)
        //             $q1->where(function ($sub) use ($servers) {
        //                 $sub->whereIn('cs1.server_configuration_id', $servers)
        //                     ->orWhereIn('cs2.server_configuration_id', $servers);
        //             });
        //         })->orWhere(function ($q2) use ($servers) {
        //             // Caso 2: contratos que no son del servidor del usuario pero tienen servicio_tv
        //             $q2->where(function ($sub) use ($servers) {
        //                 $sub->where(function ($inner) use ($servers) {
        //                     $inner->whereNotIn('cs1.server_configuration_id', $servers)
        //                           ->orWhereNull('cs1.server_configuration_id');
        //                 })
        //                 ->where(function ($inner2) use ($servers) {
        //                     $inner2->whereNotIn('cs2.server_configuration_id', $servers)
        //                            ->orWhereNull('cs2.server_configuration_id');
        //                 });
        //             })->where(function ($tv) {
        //                 $tv->whereNotNull('cs1.servicio_tv')
        //                    ->orWhereNotNull('cs2.servicio_tv');
        //             });
        //         })->orWhere(function ($q3) {
        //             // Caso 3: ingresos sin contrato pero con tipo 2, 3 o 4
        //             $q3->whereNull('fc.contrato_nro')
        //                ->whereNull('f.contrato_id')
        //                ->whereIn('ingresos.tipo', [2, 3, 4]);
        //         });
        //     });
        // }

        if ($request->filtro == true) {
            if($request->numero){
                $ingresos->where(function ($query) use ($request) {
                    $query->orWhere('ingresos.nro', 'like', "%{$request->numero}%");
                });
            }
            if($request->comprobante_pago){
                $ingresos->where(function ($query) use ($request) {
                    $query->orWhere('ingresos.comprobante_pago', 'like', "%{$request->comprobante_pago}%");
                });
            }
            if($request->status){

                $ingresos->where(function ($query) use ($request) {
                    $query->orWhere('ingresos.estatus', 'like', "%{$request->status}%");
                });
            }
            if($request->cliente){
                $ingresos->where(function ($query) use ($request) {
                    $query->orWhere('ingresos.cliente', $request->cliente);
                });
            }
            if($request->banco){
                $ingresos->where(function ($query) use ($request) {
                    $query->orWhere('ingresos.cuenta', $request->banco);
                });
            }
            if($request->metodo){
                $ingresos->where(function ($query) use ($request) {
                    $query->orWhere('ingresos.metodo_pago', $request->metodo);
                });
            }
            if($request->fecha){
                $ingresos->where(function ($query) use ($request) {
                    $query->orWhere('ingresos.fecha', $request->fecha);
                });
            }
            if($request->estado){
                $ingresos->where(function ($query) use ($request) {
                    $query->orWhere('ingresos.estatus', $request->estado);
                });
            }
        }

        $ingresos->where('ingresos.empresa', $empresa)->groupBy('ingresos.id');

        if(Auth::user()->empresa()->oficina){
            if(auth()->user()->oficina){
                $ingresos->where('contactos.oficina', auth()->user()->oficina);
            }
        }

        return datatables()->eloquent($ingresos)
            ->editColumn('nro', function (Ingreso $ingreso) {
                return isset($ingreso->nro) ? "<a href=" . route('ingresos.show', $ingreso->id) . ">{$ingreso->nro}</div></a>" : '';
            })
            ->editColumn('comprobante_pago', function (Ingreso $ingreso) {
                return isset($ingreso->comprobante_pago) ? "<a href=" . route('ingresos.show', $ingreso->id) . ">{$ingreso->comprobante_pago}</div></a>" : '';
            })
            ->editColumn('cliente', function (Ingreso $ingreso) {
                return isset($ingreso->nombre) ? "<a href=" . route('contactos.show', $ingreso->cliente) . ">{$ingreso->nombre} {$ingreso->apellido1} {$ingreso->apellido2}</div></a>" : auth()->user()->empresa()->nombre;
            })
            ->addColumn('detalle', function (Ingreso $ingreso) {
                return $ingreso->detalle();
            })
            ->editColumn('fecha', function (Ingreso $ingreso) {
                return date('d-m-Y', strtotime($ingreso->fecha));
            })
            ->editColumn('cuenta', function (Ingreso $ingreso) {
                return  $ingreso->banco ?? '';
            })
            ->addColumn('estado', function (Ingreso $ingreso) {
                return $ingreso->estatus();
            })
            ->addColumn('monto', function (Ingreso $ingreso) {
                return auth()->user()->empresa()->moneda . " {$ingreso->parsear($ingreso->pago())}";
            })
            ->addColumn('acciones', $modoLectura ?  "" : "ingresos.acciones-ingresos")
            ->rawColumns(['nro', 'cliente', 'comprobante_pago', 'acciones'])
            ->toJson();
    }

    public function create($cliente=false, $factura=false, $banco=false){
        $this->getAllPermissions(Auth::user()->id);

        $pers = $cliente;
        $bank = $banco;

        view()->share(['icon' =>'', 'title' => 'Nuevo Ingreso', 'subseccion' => 'ingresos']);

        if ($cliente && !$factura) {
            $banco=$cliente; $cliente=false;
        }
        $numero = (Ingreso::where('empresa', Auth::user()->empresa)->get());
        if (count($numero)>0){
            $numero = ($numero->last())->nro+1;
        }else{
            $numero = 1;
        }
        $contrato = false;
        $pagoEmitirDian = false;
        if($cliente){
            $contrato = Contrato::where('client_id',$cliente)->first();
            $pagoEmitirDian = Contrato::where('client_id', $cliente)
                ->where('pago_emitir', 1)->exists();
        }

        //$bancos = Banco::where('empresa',Auth::user()->empresa)->where('estatus', 1)->get();
        if (Auth::user()->rol == 8 || Auth::user()->cuenta > 0) {
            $bancos = Banco::where('estatus', 1)->where('empresa', Auth::user()->empresa)->whereIn('id', auth()->user()->cuentas())->get();
        } else {
            $bancos = Banco::where('empresa', Auth::user()->empresa)->where('estatus', 1)->get();
        }
        // $clientes = (Auth::user()->empresa()->oficina) ? Contacto::where('status', 1)->whereIn('tipo_contacto',[0,2])->where('empresa', Auth::user()->empresa)->where('oficina', Auth::user()->oficina)->orderBy('nombre','asc')->get() : Contacto::where('status', 1)->whereIn('tipo_contacto',[0,2])->where('empresa', Auth::user()->empresa)->orderBy('nombre','asc')->get();
        $clientes = Contacto::where('status', 1)->whereIn('tipo_contacto',[0,2])->where('empresa', Auth::user()->empresa)->orderBy('nombre','asc')->get();
        //$clientes = Contacto::where('empresa',Auth::user()->empresa)->whereIn('tipo_contacto',[0,2])->where('status', 1)->get();
        $metodos_pago =DB::table('metodos_pago')->whereIn('id',[1,2,3,4,5,6,9])->orderby('orden','asc')->get();
        $inventario = Inventario::where('empresa',Auth::user()->empresa)->where('status', 1)->get();
        $retenciones = Retencion::where('empresa',Auth::user()->empresa)->where('modulo',1)->get();
        $impuestos = Impuesto::where('empresa',Auth::user()->empresa)->orWhere('empresa', null)->Where('estado', 1)->get();
         //Tomar las categorias del puc que no son transaccionables.
         $categorias = Puc::where('empresa',auth()->user()->empresa)
         ->whereRaw('length(codigo) >= 6')
         ->get();

        //obtiene los anticipos relacionados con este modulo (Ingresos)
        $anticipos = Anticipo::where('relacion',1)->orWhere('relacion',3)->get();

        //tomamos las formas de pago cuando no es un recibo de caja por anticipo
        $formas = FormaPago::where('relacion',1)->orWhere('relacion',3)->get();

        //obtiene las formas de pago relacionadas con este modulo (Facturas)
        $relaciones = FormaPago::where('relacion',1)->orWhere('relacion',3)->get();

        $saldo_favor = 0;
        if($cliente){
            $saldo_favor = Contacto::Find($cliente)->saldo_favor;
        }

        return view('ingresos.create')->with(compact('contrato','clientes', 'inventario', 'cliente', 'factura',
        'bancos', 'metodos_pago', 'impuestos', 'saldo_favor',
        'retenciones',  'banco', 'numero','pers','bank','categorias','anticipos','formas','relaciones','pagoEmitirDian'));
    }

    public function saldoContacto($id){
        $cliente = Contacto::find($id);
        $contrato = Contrato::where('client_id',$id)->first();
        if($cliente->saldo_favor == null){
            $saldo = 0;
        }else{
            $saldo = $cliente->saldo_favor;
        }
        return json_encode(['saldo' => $saldo, 'contrato' => $contrato->opciones_dian]);
    }

    public function pendiente($cliente, $id=false){

        $this->getAllPermissions(Auth::user()->id);
        $facturas = Factura::where('cliente', $cliente)->where('empresa',Auth::user()->empresa)->where('estatus', 1);
        $facturas = $facturas->orderBy('created_at', 'desc')->take(30)->get();
        $contrato = Contrato::where('client_id',$cliente)->first();
        //$total = Factura::where('cliente', $cliente)->where('empresa',Auth::user()->empresa)->where('tipo','!=',2)->where('estatus', 1)->count();
        $total = 1;

        return view('ingresos.pendiente')->with(compact('facturas', 'id', 'total','contrato'));
    }

    public function ingpendiente($cliente, $id=false){
        $this->getAllPermissions(Auth::user()->id);
        $facturas=Factura::where('cliente', $cliente)->where('empresa',Auth::user()->empresa)->where('estatus', 1)->get();
        $entro=false;
        $retencioness = Retencion::where('empresa',Auth::user()->empresa)->where('modulo',1)->get();
        $ingreso = Ingreso::where('empresa',Auth::user()->empresa)->where('nro', $id)->first();
        $items = IngresosFactura::where('ingreso',$ingreso->id)->get();
        $new=$facturas;
        $contrato = Contrato::where('client_id',$cliente)->first();

        //obtiene las formas de pago relacionadas con este modulo (Facturas)
        $relaciones = FormaPago::where('relacion',1)->orWhere('relacion',3)->get();
        $formasPago = PucMovimiento::where('documento_id',$ingreso->id)->where('tipo_comprobante',1)->whereIn('enlace_a',[4,5])->get();

        foreach ($items as $item) {
            foreach ($facturas as $factura) {
                if ($factura->id==$item->factura) {
                    $entro=true;
                }
            }
            if (!$entro) {
                $new[]=Factura::where('id', $item->factura)->first();
            }
            $entro=false;
        }

        return view('ingresos.ingpendiente')->with(compact('facturas', 'id', 'items',
        'ingreso', 'retencioness','contrato','formasPago','relaciones'
    ));
    }

    public function store(Request $request){

        try {
            $user = Auth::user();
            $empresa = Empresa::Find($user->empresa);
            $morosos = "";
            $msj_siigo = "";

            // Verificamos si la suma es mayor que 0
            if($request->anticipo == 1){
                $sumaPrecios = $request->valor_recibido;
            }else{
                $sumaPrecios = collect($request->precio)
                ->map(fn($precio) => floatval($precio))
                ->sum();
            }

            // $contrato = Contrato::where('nro',632)->first();
            // $morosos = $this->funcionesPagoMK($contrato);
            // dd($morosos);
            if ($sumaPrecios <= 0) {
            return back()->with('danger', 'La suma de los precios no puede ser 0.')->withInput();
            }

            //store: prorrateo Validaciones de opciones radiobutton
            if(isset($request->tipo_electronica) && $request->tipo_electronica == 4 && $request->realizar == 1){
                $conteo = count(array_filter($request->precio, function ($item) {
                    return !is_null($item) && is_numeric($item); // opcionalmente, solo números válidos
                }));
                if($conteo > 1){
                    return back()->with('danger', 'No puedes realizar prorrateo al seleccionar mas de una factura.')->withInput();
                }

                foreach ($request->factura_pendiente as $key => $value) {
                    if ($request->precio[$key]) {
                        $contrato = Contrato::join('facturas_contratos as fc','fc.contrato_nro','contracts.nro')->where('factura_id', $value)->first();
                        if(!$contrato){
                            return back()->with('danger', 'La factura a la cual le asociaste un pago no tiene un contrato asociado.')->withInput();
                        }
                    }
                }
            }

            //el tipo 2 significa que estoy realizando un ingreso para darle un anticipo a un cliente
            if($request->realizar == 2){
                //Cuando se realiza el ingreso por categoría.
                $this->storeIngresoPucCategoria($request);

                $mensaje='SE HA CREADO SATISFACTORIAMENTE EL PAGO';
                return redirect('empresa/ingresos')->with('success', $mensaje);

            }else{

            if(isset($request->comprobante_pago)){
                if(Ingreso::where('comprobante_pago', $request->comprobante_pago)->count() > 0){
                    return back()->withInput()->with('danger', 'DISCULPE, EL NRO DE COMPROBANTE DE PAGO INGRESADO YA HA SIDO REGISTRADO');
                }
            }

            if($user->rol == 8){
                $monto_pagar = 0;
                foreach ($request->factura_pendiente as $key => $value) {
                    if ($request->precio[$key]) {
                        $monto_pagar += $request->precio[$key];
                    }
                }

                if($monto_pagar > auth()->user()->saldo){
                    $mensaje='NO POSEE SALDO DISPONIBLE PARA CANCELAR LA FACTURA, LO INVITAMOS A REALIZAR UNA RECARGA';
                    return back()->with('danger', $mensaje)->withInput();
                }
            }
            //Si es tipo 1 osea pagos a facturas.
            if ($request->tipo == 1) {

                //Validaciones
                if(is_array($request->factura_pendiente)){

                    foreach ($request->factura_pendiente as $key => $factura_id) {

                        $montoPago = $this->precision($request->precio[$key]);
                        $factura = Factura::find($request->factura_pendiente[$key]);

                        if ($factura->contratos() != false &&
                            $factura->contratos()->first()->contrato_nro) {

                                $contrato = $factura->contratos()->first()->contrato_nro;
                                $contrato = Contrato::where('nro',$contrato)->first();

                            if($empresa->pago_siigo == 1 || ($contrato && $contrato->pago_siigo_contrato == 1)){
                                $siigo = new SiigoController();
                                $response = $siigo->envioMasivoSiigo($factura->id,true)->getData(true);
                                Log::info($response);
                                if(isset($response['success']) && $response['success'] == false){
                                    // Intentar refrescar la conexión a Siigo
                                    Log::info("Error de conexión con Siigo, intentando refrescar token...");
                                    $refreshResult = $siigo->configurarSiigo(null, true);

                                    if($refreshResult == 1){
                                        // Si el refresh fue exitoso, intentar nuevamente el envío
                                        Log::info("Token de Siigo refrescado exitosamente, reintentando envío...");
                                        $response = $siigo->envioMasivoSiigo($factura->id,true)->getData(true);
                                        Log::info("Respuesta después del refresh: " . json_encode($response));

                                        if(isset($response['success']) && $response['success'] == false){
                                            $msj_siigo = " No se ha podido establecer conexión con siigo después de refrescar el token.";
                                        }
                                    } else {
                                        $msj_siigo = " No se ha podido establecer conexión con siigo, no se pudo refrescar el token.";
                                    }
                                }
                            }
                        }

                        $pagoRepetido = IngresosFactura::where('factura', $factura_id)
                            ->where('pagado', $montoPago)
                            ->whereHas('ingresoRelation', function ($query) {
                                $query->whereBetween('created_at', [now()->subSeconds(600), now()]);
                            })
                            ->exists();


                        if ($pagoRepetido) {
                            $factura = Factura::find($factura_id);
                            Log::info("No permitio la creacion de un pago duplicado" . $factura_id);
                            return back()->with('danger', ' Ya has registrado un pago de $' . number_format($montoPago, 0, ',', '.') . ' recientemente para la factura N° ' . $factura->codigo . '. Evita pagos duplicados, intenta en dos minutos de nuevo.')->withInput();
                        }

                        if($factura->estatus == 0){
                            $mensaje='DISCULPE ESTÁ INTENTANDO PAGAR UNA FACTURA YA PAGADA. (FACTURA N° '.$factura->codigo.')';
                            return back()->with('danger', $mensaje)->withInput();
                        }

                        if(!$pagoRepetido){
                            $sumaPagos = round(IngresosFactura::join('ingresos as i','i.id','ingresos_factura.ingreso')
                            ->where('factura',$factura_id)
                            ->where('i.estatus',1)
                            ->sum('pago')
                            );
                            $totalFact = $factura->total()->total;

                            if($sumaPagos >= $totalFact){

                                $factura->estatus = 0;
                                $factura->save();

                                Log::info("No permitio la creacion de un pago duplicado" . $factura_id);
                                $mensaje='La factura que estas intentando pagar ya tiene el total de la factura pagado. FACTURA N° '.$factura->codigo;
                                return back()->with('danger', $mensaje)->withInput();
                            }
                        }

                        //Conversión de factura estandar a factura electrónica.
                        if(isset($request->tipo_electronica) && $request->tipo_electronica == 1){

                            //primero recuperamos
                            $nro=NumeracionFactura::where('empresa',$user->empresa)->where('preferida',1)->where('estado',1)->where('tipo',2)->first();
                            $inicio = $nro->inicio;

                            $codigoUsado = Factura::where('empresa', $user->empresa)
                            ->where('codigo', $nro->prefijo.$inicio)
                            ->where('id', '!=', $factura->id)
                            ->where('numeracion', $nro->id)
                            ->first();

                            if($codigoUsado){
                                return back()->with('danger', 'Revisar la ultima factura del segmento y modificar la numeracion. Razon: Codigo duplicado ('.$nro->prefijo.$inicio.')');
                            }

                            if($factura->tipo != 2 && $request->precio[$key] > 0)
                            {
                                $factura->tipo = 2;
                                $factura->codigo = $nro->prefijo.$inicio;
                                $factura->numeracion = $nro->id;
                                $factura->fecha =  Carbon::now()->format('Y-m-d');
                                if($factura->vencimiento < Carbon::now()->format('Y-m-d')){
                                    $factura->vencimiento = Carbon::now()->format('Y-m-d');
                                }
                                $factura->save();

                                $nro->inicio += 1;
                                $nro->save();
                            }
                        }

                        //tipo_electronica 2
                        if(isset($request->tipo_electronica) && $request->tipo_electronica == 2){
                                //si tiene el tipo 2 es por que desean emitir la(s) factura(s).
                                if($factura->emitida != 1){

                                    if($factura->tipo == 1){
                                        $conversion = app(FacturasController::class)->convertirelEctronica($factura->id,0,1);
                                    }

                                    if(isset($empresa->proveedor) && $empresa->proveedor == 2){
                                        $emision = app(FacturasController::class)->jsonDianFacturaVenta($factura->id);
                                    }else{
                                        $emision = app(FacturasController::class)->xmlFacturaVentaMasivo($factura->id);
                                    }

                                }
                        }
                    }
                }else {
                        $mensaje='No hay facturas pendientes seleccionadas.';
                        return back()->with('danger', $mensaje)->withInput();
                }
            }

            if (Ingreso::where('empresa', $user->empresa)->count() > 0) {
                Session::put('posttimer', Ingreso::where('empresa', $user->empresa)->get()->last()->created_at);
                $sw = 1;

                foreach (Session::get('posttimer') as $key) {
                    if ($sw == 1) {
                        $ultimoingreso = $key;
                        $sw = 0;
                    }
                }

                if(isset($ultimoingreso)){
                    $diasDiferencia = Carbon::now()->diffInseconds($ultimoingreso);

                    if ($diasDiferencia <= 10) {
                        $mensaje='EL PAGO NO HA SIDO PROCESADO, INTÉNTELO NUEVAMENTE';
                        return back()->with('danger', $mensaje)->withInput();
                    }
                }
            }

            $request->validate([
                'cuenta' => 'required|numeric'
            ]);

            $nro = Numeracion::where('empresa', $user->empresa)->first();
            $caja = $nro->caja;

            while (true) {
                $numero = Ingreso::where('empresa', $user->empresa)->where('nro', $caja)->count();
                if ($numero == 0) {
                    break;
                }
                $caja++;
            }

            if(isset($request->uso_saldo) && $request->uso_saldo){
                $banco_favor = Banco::where('empresa',$user->empresa)->where('nombre','like','Saldos a favor')->first();
                if($banco_favor){
                    $request->cuenta = $banco_favor->id;
                }else{
                    $mensaje='DISCULPE, NO SE ENCUENTRA REGISTRADO UN BANCO CON EL NOMBRE "SALDOS A FAVOR"';
                    return back()->with('danger', $mensaje)->withInput();
                }
            }

            $ingreso = new Ingreso;
            $ingreso->nro = $caja;
            $ingreso->empresa = Auth::user()->empresa;
            $ingreso->cliente = $request->cliente;
            $ingreso->cuenta = $request->cuenta;
            $ingreso->metodo_pago = $request->metodo_pago;
            $ingreso->notas = $request->notas;
            $ingreso->tipo = $request->tipo;
            $ingreso->fecha = Carbon::parse($request->fecha)->format('Y-m-d');
            $ingreso->observaciones = mb_strtolower($request->observaciones);
            $ingreso->created_by = Auth::user()->id;
            $ingreso->anticipo = $request->saldofavor > 0 ? '1' : ''; // variables que me indican si se trata de un anticipo
            $ingreso->valor_anticipo = $request->saldofavor > 0 ? $request->saldofavor : ''; //variables que me indican si se trata de un anticipo
            $ingreso->comprobante_pago = $request->comprobante_pago;
            $ingreso->forma_pago = $request->forma_pago;
            $ingreso->save();

            //Si el tipo de ingreso es de facturas
            $totalIngreso=0;
            $contratos_procesados_mk = []; // Controlar ejecuciones duplicadas MK
            if ($ingreso->tipo == 1) {
                $saldoFavorUsado = 0;
                foreach ($request->factura_pendiente as $key => $value) {

                    if ($request->precio[$key]) {
                        $totalIngreso+=$precio = $this->precision($request->precio[$key]);
                        $factura = Factura::find($request->factura_pendiente[$key]);


                        //registro de que se creo un ingreso de factura
                        $movimiento = new MovimientoLOG();
                        $movimiento->contrato    = $factura->id;
                        $movimiento->modulo      = 8;
                        $movimiento->descripcion = 'Se creo un ingreso de factura con el recibo de caja nro ' . $ingreso->nro . ' por un total de $' . number_format($request->precio[$key], 0, ',', '.');
                        $movimiento->created_by  = Auth::user()->id;
                        $movimiento->empresa     = $factura->empresa;
                        $movimiento->save();

                        //Registro el Movimiento de ingreso de saldo a favor
                        if($request->saldofavor > 0){
                            $descripcion = '<i class="fas fa-check text-success"></i> <b>Ingreso de saldo a favor con el recibo de caja nro ' . $ingreso->nro . '</b> por un total de $' . number_format($request->saldofavor, 0, ',', '.') . '<br>';
                            $movimiento = new MovimientoLOG();
                            $movimiento->contrato    = $factura->id;
                            $movimiento->modulo      = 8;
                            $movimiento->descripcion = $descripcion;
                            $movimiento->created_by  = Auth::user()->id;
                            $movimiento->empresa     = $factura->empresa;
                            $movimiento->save();
                        }

                        $contrato = Contrato::join('facturas_contratos as fc', 'fc.contrato_nro', '=', 'contracts.nro')
                                ->where('fc.factura_id', $factura->id)
                                ->select('contracts.*')
                                ->first();

                        if(!$contrato){
                           $contrato = Contrato::where('id',$factura->contrato_id)->first();
                        }

                        Log::debug("IngresosController@store: Contrato asociado a factura #{$factura->codigo}: " . ($contrato ? "Contrato #{$contrato->nro}" : "Ninguno"));

                        if($contrato){
                            try {
                                Log::debug("IngresosController@store: Validando ejecución MK para contrato #{$contrato->nro} (ID: {$contrato->id}). Consultas_mk: {$empresa->consultas_mk}");
                                if($empresa->consultas_mk == 1 && !in_array($contrato->id, $contratos_procesados_mk)){
                                    $contratos_procesados_mk[] = $contrato->id; // Registramos para no repetir en esta petición
                                    
                                    // Ejecutar funciones MK en segundo plano, después de enviar la respuesta HTTP
                                    $contratoId = $contrato->id;
                                    $empresaId = $empresa->id;
                                    $ingresoId = $ingreso->id;
                                    
                                    Log::debug("IngresosController@store: Registrando callback terminating para contrato ID: {$contratoId}, Ingreso ID: {$ingresoId}");
                                    
                                    app()->terminating(function () use ($contratoId, $empresaId, $ingresoId) {
                                        try {
                                            Log::debug("IngresosController@store (Background): Iniciando callback terminating...");
                                            DB::reconnect();
                                            $contratoBG = \App\Contrato::find($contratoId);
                                            $empresaBG = \App\Empresa::find($empresaId);
                                            $ingresoBG = Ingreso::find($ingresoId);
                                            
                                            Log::debug("IngresosController@store (Background): Modelos cargados - Contrato: " . ($contratoBG ? 'OK' : 'FAIL') . ", Empresa: " . ($empresaBG ? 'OK' : 'FAIL') . ", Ingreso: " . ($ingresoBG ? 'OK' : 'FAIL'));
                                            
                                            if($contratoBG && $empresaBG && $ingresoBG){
                                                Log::debug("IngresosController@store (Background): Llamando a funcionesPagoMK para contrato #{$contratoBG->nro}");
                                                $controller = new \App\Http\Controllers\IngresosController();
                                                $controller->funcionesPagoMK($contratoBG, $empresaBG, $ingresoBG);
                                                Log::debug("IngresosController@store (Background): funcionesPagoMK finalizado.");
                                            } else {
                                                Log::warning("IngresosController@store (Background): No se pudieron cargar todos los modelos necesarios para funcionesPagoMK.");
                                            }
                                        } catch (\Throwable $e) {
                                            Log::error('Error en funcionesPagoMK (background): ' . $e->getMessage(), [
                                                'contratoId' => $contratoId,
                                                'ingresoId' => $ingresoId,
                                                'trace' => $e->getTraceAsString()
                                            ]);
                                        }
                                    });
                                } else {
                                    if ($empresa->consultas_mk != 1) {
                                        Log::debug("IngresosController@store: No se ejecuta MK porque consultas_mk está desactivado.");
                                    }
                                    if (in_array($contrato->id, $contratos_procesados_mk)) {
                                        Log::debug("IngresosController@store: Contrato ID {$contrato->id} ya procesado en esta petición.");
                                    }
                                }
                            } catch (\Throwable $thMK) {
                                Log::error('Error al ejecutar funcionesPagoMK desde store: ' . $thMK->getMessage());
                            }
                        }

                        /*
                        vamos a sumar el total del anticipo usado sobre una factura
                        (este se aplica cuando se crea la factura de venta en una forma de pago)
                        */
                        $saldoFavorUsado+=$factura->saldoFavorUsado();

                        $retencion = 'fact' . $factura->id . '_retencion';
                        $precio_reten = 'fact' . $factura->id . '_precio_reten';
                        if ($request->$retencion) {
                            foreach ($request->$retencion as $key2 => $value2) {
                                if ($request->$precio_reten[$key2]) {
                                    $retencion = Retencion::where('id', $value2)->first();
                                    $items = new IngresosRetenciones;
                                    $items->ingreso = $ingreso->id;
                                    $items->factura = $factura->id;
                                    $items->valor = $this->precision($request->$precio_reten[$key2]);
                                    $precio += $this->precision($request->$precio_reten[$key2]);
                                    $items->retencion = $retencion->porcentaje;
                                    $items->id_retencion = $retencion->id;
                                    $items->save();
                                }
                            }
                        }

                        $descuentoPct = 0;
                        if (is_array($request->descuento_pendiente) && isset($request->descuento_pendiente[$key])) {
                            $descuentoPct = floatval($request->descuento_pendiente[$key]);
                            if ($descuentoPct < 0) { $descuentoPct = 0; }
                            if ($descuentoPct > 99) { $descuentoPct = 99; }
                        }

                        $items = new IngresosFactura;
                        $items->ingreso = $ingreso->id;
                        $items->factura = $factura->id;
                        $items->pagado = $precio; //asi exista mas dinero del  pagado ese se debe usar.
                        $items->descuento = $descuentoPct;
                        $items->puc_factura = $factura->cuenta_id;
                        $items->puc_banco = $request->saldofavor > 0 ? $request->forma_pago : $request->forma_pago;
                        $items->anticipo = $request->saldofavor > 0 ? $request->anticipo_factura : null;

                        /*
                        Validacion cuando se recibe un valor mayor a la factura. entonces guardamos
                        sobre el total de la factura por que el resto es saldo a favor.
                        */
                        if($factura->total()->total < $request->precio[$key]){

                            // $items->pago = $factura->total()->total; ya no se desea asi, ahora quieren que aparezca el total asi sobrepase
                            $items->pago = $this->precision($request->precio[$key]);
                            $factura->estatus = 0;
                            $factura->save();
                            }else{
                                $items->pago=$this->precision($request->precio[$key]);
                            }

                            if ($this->precision($precio) == $this->precision($factura->porpagar())) {
                                $factura->estatus = 0;
                                $factura->save();

                                CRM::where('cliente', $factura->cliente)->whereIn('estado', [0,2,3,6])->delete();

                                $crms = CRM::where('cliente', $factura->cliente)->whereIn('estado', [0,2,3,6])->get();
                                foreach ($crms as $crm) {
                                    $crm->delete();
                                }
                            }

                            $items->save();

                            if ($descuentoPct > 0) {
                                $this->aplicarDescuentoItemsFactura($factura->id, $descuentoPct);
                            }

                            // Auto-emisión a la DIAN cuando pago_emitir está activo en ALGÚN contrato del cliente
                            $pagoEmitirCliente = Contrato::where('client_id', $factura->cliente)->where('pago_emitir', 1)->exists();
                            
                            if($pagoEmitirCliente && $factura->estatus == 0){
                                // Solo auto-emitir si el usuario NO seleccionó tipo_electronica=2 manualmente (para evitar doble ejecución)
                                if(!isset($request->tipo_electronica) || $request->tipo_electronica != 2){
                                    try {
                                        if($factura->emitida != 1){
                                            if($factura->tipo == 1){
                                                app(FacturasController::class)->convertirelEctronica($factura->id, 0, 1);
                                                $factura->refresh();
                                            }
                                            if(isset($empresa->proveedor) && $empresa->proveedor == 2){
                                                app(FacturasController::class)->jsonDianFacturaVenta($factura->id);
                                            } else {
                                                app(FacturasController::class)->xmlFacturaVentaMasivo($factura->id);
                                            }
                                        }
                                    } catch (\Throwable $eEmitir) {
                                        Log::error('Error en auto-emisión DIAN (pago_emitir cliente): ' . $eEmitir->getMessage(), [
                                            'factura_id' => $factura->id,
                                            'cliente_id' => $factura->cliente,
                                        ]);
                                    }
                                }
                            }

                            // Eliminar factura en OnePay si existe ya que se está registrando pago por la plataforma
                            if ($factura->onepay_invoice_id) {
                                try {
                                    $onePayService = new \App\Services\OnePayService($empresa->id);
                                    $onePayService->deleteInvoice($factura);
                                } catch (\Exception $e) {
                                    \Illuminate\Support\Facades\Log::error('Error al eliminar factura en OnePay: ' . $e->getMessage(), [
                                        'factura_id' => $factura->id,
                                        'empresa_id' => $empresa->id
                                    ]);
                                }
                            }

                            if(isset($request->tipo_electronica) && $request->tipo_electronica != 6 || !isset($request->tipo_electronica)){
                            if(!$contrato){
                                $db_contrato = DB::table('facturas_contratos')->where('factura_id',$factura->id)->first();
                                if($db_contrato){
                                    $contrato = Contrato::where('nro',$db_contrato->contrato_nro)->first();
                                }
                            }

                            $cliente = $factura->cliente();

                            if($contrato){
                                $contrato->state = "enabled";
                                $contrato->save();
                            }
                            if(!$contrato){
                                $contrato = Contrato::where('client_id', $cliente->id)->first();
                                if($contrato){
                                    $contrato->state = "enabled";
                                    $contrato->save();
                                }
                            }

                            if($contrato){

                                $asignacion = Producto::where('contrato', $contrato->id)->where('venta', 1)->where('status', 2)->where('cuotas_pendientes', '>', 0)->get()->last();

                                if ($asignacion) {
                                    $cuotas_pendientes = $asignacion->cuotas_pendientes -= 1;
                                    $asignacion->cuotas_pendientes = $cuotas_pendientes;
                                    if ($cuotas_pendientes == 0) {
                                        $asignacion->status = 1;
                                    }
                                    $asignacion->save();
                                }


                            }

                            //store: CREAR FACTURA CON PRORRATEO
                            if($contrato){
                                if(isset($request->tipo_electronica) && $request->tipo_electronica == 4){
                                        $facturaInicio = 1; //Esta opcion me permite crear la factura con prorrateo desde le dia que se creo la factura
                                        $this->createFacturaProrrateo($contrato, $facturaInicio);
                                }
                            }

                            }
                        }
                    }
                } else { //Si el tipo de ingreso es de categorias
                    foreach ($request->categoria as $key => $value) {
                        if ($request->precio_categoria[$key]) {
                            $impuesto = Impuesto::where('id', $request->impuesto_categoria[$key])->first();
                            if (!$impuesto) {
                                $impuesto = Impuesto::where('id', 0)->first();
                            }

                            $items = new IngresosCategoria;
                            $items->valor = $this->precision($request->precio_categoria[$key]);
                            $items->id_impuesto = $request->impuesto_categoria[$key];
                            $items->ingreso = $ingreso->id;
                            $items->categoria = $request->categoria[$key];
                            $items->cant = $request->cant_categoria[$key];
                            $items->descripcion = $request->descripcion_categoria[$key];
                            $items->impuesto = $impuesto->porcentaje;
                            $items->save();
                        }
                    }

                    if ($request->retencion) {
                        foreach ($request->retencion as $key => $value) {
                            if ($request->precio_reten[$key]) {
                                $retencion = Retencion::where('id', $request->retencion[$key])->first();
                                $items = new IngresosRetenciones;
                                $items->ingreso = $ingreso->id;
                                $items->valor = $this->precision($request->precio_reten[$key]);
                                $items->retencion = $retencion->porcentaje;
                                $items->id_retencion = $retencion->id;
                                $items->save();
                            }
                        }
                    }
                }

                //sumo a las numeraciones el recibo
                $nro->caja = $caja + 1;
                $nro->save();

                //store: CREAR PRÓXIMA FACTURA (después de procesar todos los pagos)
                if(isset($request->tipo_electronica) && $request->tipo_electronica == 3 && $request->tipo == 1){
                    // Agrupar contratos únicos de las facturas pagadas
                    $contratosUnicos = [];
                    foreach ($request->factura_pendiente as $key => $facturaId) {
                        if (isset($request->precio[$key]) && $request->precio[$key] > 0) {
                            $factura = Factura::find($facturaId);
                            if ($factura) {
                                $facturaContrato = DB::table('facturas_contratos')
                                    ->where('factura_id', $facturaId)
                                    ->first();
                                
                                if ($facturaContrato) {
                                    $contratoNro = $facturaContrato->contrato_nro;
                                    if (!isset($contratosUnicos[$contratoNro])) {
                                        $contrato = Contrato::where('nro', $contratoNro)->first();
                                        if ($contrato) {
                                            $contratosUnicos[$contratoNro] = $contrato;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    
                    // Crear próxima factura para cada contrato único
                    foreach ($contratosUnicos as $contrato) {
                        $this->crearProximaFactura($contrato, $empresa);
                    }
                }

                //Registro el Movimiento
                $ingreso = Ingreso::find($ingreso->id);

                //aqui va a entrar cuando no se use saldo a favor en una factura.
                if(!isset($request->uso_saldo) || !$request->uso_saldo){
                    $this->up_transaccion(1, $ingreso->id, $ingreso->cuenta, $ingreso->cliente, 1, $totalIngreso, $ingreso->fecha, $ingreso->descripcion);
                }

                //Necesitamos obtener el valor que usamos de saldo a favor para descontarlo del banco, ya que se guardó. (obtener todo el total)
                if(isset($saldoFavorUsado) && $saldoFavorUsado > 0){
                    //la cuenta de anticipo es la 6
                    $this->up_transaccion(6, $ingreso->id, $ingreso->cuenta, $ingreso->cliente, 2, $saldoFavorUsado, $ingreso->fecha, $ingreso->descripcion);
                }


                //registramos el saldo a favor que se generó al pagar la factura
                if($request->saldofavor > 0){
                    $contacto = Contacto::find($request->cliente);
                    $contacto->saldo_favor = $contacto->saldo_favor+$request->saldofavor;
                    $contacto->save();

                    $ingreso->puc_banco = $request->forma_pago; //cuenta de forma de pago genérico del ingreso. (en memoria)
                    $ingreso->anticipo = $request->anticipo_factura; //cuenta de anticipo genérico del ingreso. (en memoria)

                    $ingreso->saldoFavorIngreso = $request->saldofavor; //Variable en memoria, no creada.
                    PucMovimiento::ingreso($ingreso,1,1,$request);

                    //Nuevo desarrollo: reigtsramos en un banco llamado saldos a Favor el ingreso de dinero extra.
                    $bancoId = Banco::where('empresa',$user->empresa)->where('nombre','like','Saldos a favor')->first()->id;
                    $this->up_transaccion(7, $ingreso->id, $bancoId, $ingreso->cliente, 1, $request->saldofavor, $ingreso->fecha, "Ingreso de saldo a favor",$request->saldofavor);

                }else{
                    $ingreso->puc_banco = $request->forma_pago; //cuenta de forma de pago genérico del ingreso. (en memoria)
                    PucMovimiento::ingreso($ingreso,1,2,$request);

                    if(isset($request->uso_saldo) && $request->uso_saldo){
                        $this->up_transaccion(7, $ingreso->id, $ingreso->cuenta, $ingreso->cliente, 2, $ingreso->pago(), $ingreso->fecha, "Uso de saldo a favor");
                    }
                }

                if ($ingreso->tipo == 1) {
                    if($factura->estatus == 0){
                        $cliente = Contacto::where('id', $request->cliente)->first();

                        /* * * ENVÍO SMS * * */
                        $servicio = Integracion::where('empresa', Auth::user()->empresa)->where('tipo', 'SMS')->where('status', 1)->first();
                        if($servicio){
                            $numero = str_replace('+','',$cliente->celular);
                            $numero = str_replace(' ','',$numero);
                            $mensaje = "Estimado Cliente, le informamos que hemos recibido el pago de su factura por valor de ".$factura->parsear($precio)." gracias por preferirnos. ".Auth::user()->empresa()->slogan;
                            if($servicio->nombre == 'Hablame SMS'){
                                if($servicio->api_key && $servicio->user && $servicio->pass){
                                    $post['toNumber'] = $numero;
                                    $post['sms'] = $mensaje;

                                    $curl = curl_init();
                                    curl_setopt_array($curl, array(
                                        CURLOPT_URL => 'https://api103.hablame.co/api/sms/v3/send/marketing',
                                        CURLOPT_RETURNTRANSFER => true,
                                        CURLOPT_ENCODING => '',
                                        CURLOPT_MAXREDIRS => 10,
                                        CURLOPT_TIMEOUT => 0,
                                        CURLOPT_FOLLOWLOCATION => true,
                                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                        CURLOPT_CUSTOMREQUEST => 'POST',CURLOPT_POSTFIELDS => json_encode($post),
                                        CURLOPT_HTTPHEADER => array(
                                            'account: '.$servicio->user,
                                            'apiKey: '.$servicio->api_key,
                                            'token: '.$servicio->pass,
                                            'Content-Type: application/json'
                                        ),
                                    ));
                                    $result = curl_exec ($curl);
                                    $err  = curl_error($curl);
                                    curl_close($curl);
                                }
                            }elseif($servicio->nombre == 'SmsEasySms'){
                                if($servicio->user && $servicio->pass){
                                    $post['to'] = array('57'.$numero);
                                    $post['text'] = $mensaje;
                                    $post['from'] = "SMS";
                                    $login = $servicio->user;
                                    $password = $servicio->pass;

                                    $ch = curl_init();
                                    curl_setopt($ch, CURLOPT_URL, "https://sms.istsas.com/Api/rest/message");
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                    curl_setopt($ch, CURLOPT_POST, 1);
                                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
                                    curl_setopt($ch, CURLOPT_HTTPHEADER,
                                        array(
                                            "Accept: application/json",
                                            "Authorization: Basic ".base64_encode($login.":".$password)));
                                    $result = curl_exec ($ch);
                                    $err  = curl_error($ch);
                                    curl_close($ch);
                                }
                            }else{
                                if($servicio->user && $servicio->pass){
                                    $post['to'] = array('57'.$numero);
                                    $post['text'] = $mensaje;
                                    $post['from'] = "";
                                    $login = $servicio->user;
                                    $password = $servicio->pass;

                                    $ch = curl_init();
                                    curl_setopt($ch, CURLOPT_URL, "https://masivos.colombiared.com.co/Api/rest/message");
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                    curl_setopt($ch, CURLOPT_POST, 1);
                                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
                                    curl_setopt($ch, CURLOPT_HTTPHEADER,
                                        array(
                                            "Accept: application/json",
                                            "Authorization: Basic ".base64_encode($login.":".$password)));
                                    $result = curl_exec ($ch);
                                    $err  = curl_error($ch);
                                    curl_close($ch);
                                }
                            }
                        }
                        /* * * ENVÍO SMS * * */
                    }
                }

                if(auth()->user()->rol == 8){
                    $user = User::find(auth()->user()->id);
                    $user->ganancia += 1000;
                    $user->saldo -= $monto_pagar;
                    $user->save();
                }

                if($request->cant_facturas > 1){
                    $nro = Numeracion::where('empresa', Auth::user()->empresa)->first();
                    $caja = $nro->caja;

                    while (true) {
                        $numero = Ingreso::where('empresa', Auth::user()->empresa)->where('nro', $caja)->count();
                        if ($numero == 0) {
                            break;
                        }
                        $caja++;
                    }

                    $ingreso = new Ingreso;
                    $ingreso->nro = $caja;
                    $ingreso->empresa = Auth::user()->empresa;
                    $ingreso->cliente = $request->cliente;
                    $ingreso->cuenta = $request->cuenta;
                    $ingreso->metodo_pago = $request->metodo_pago;
                    $ingreso->notas = $request->notas;
                    $ingreso->tipo = 2;
                    $ingreso->fecha = Carbon::parse($request->fecha)->format('Y-m-d');
                    $ingreso->observaciones = 'Ingreso por concepto de reconexión';
                    $ingreso->created_by = Auth::user()->id;
                    $ingreso->save();

                    $items = new IngresosCategoria;
                    $items->valor = $this->precision(10000);
                    $items->id_impuesto = 2;
                    $items->ingreso = $ingreso->id;
                    $items->categoria = 56;
                    $items->cant = 1;
                    $items->descripcion = 'Ingreso por concepto de reconexión';
                    $items->impuesto = 0;
                    $items->save();

                    //sumo a las numeraciones el recibo
                    $nro->caja = $caja + 1;
                    $nro->save();

                    //Registro el Movimiento
                    $ingreso = Ingreso::find($ingreso->id);
                    //ingresos
                    if(!isset($request->uso_saldo) || $request->uso_saldo){
                    $this->up_transaccion(1, $ingreso->id, $ingreso->cuenta, $ingreso->cliente, 1, $ingreso->pago(), $ingreso->fecha, 'Ingreso por concepto de reconexión');
                    }

                    $facturas = Factura::where('cliente', $ingreso->cliente)->where('estatus', 1)->get();
                    if ($facturas) {
                        foreach ($facturas as $factura) {
                            $factura->estatus = 0;
                            $factura->save();
                        }
                    }
                }

                $tirilla = false;
                if ($request->tirilla) {
                    $tirilla = true;
                }

                //Desarrollo para enviar tirilla por wpp.
                if($request->tirilla_wpp){
                    $cliente = $ingreso->cliente();

                    if($cliente->celular){
                        $numero = str_replace('+','',$cliente->celular);
                        $numero = str_replace(' ','',$numero);
                        $numero = (substr($numero, 0, 2) == 57) ? $numero : '57'.$numero;
                        $empresa = Empresa::find($ingreso->empresa);

                        //Datos para tirilla.
                        if ($ingreso->tipo==1) {
                            $itemscount=IngresosFactura::where('ingreso',$ingreso->id)->count();
                            $items = IngresosFactura::join('items_factura as itf','itf.factura','ingresos_factura.factura')->select('itf.*')->where('ingreso',$ingreso->id)->get();
                        }else if ($ingreso->tipo==2){
                            $itemscount=IngresosCategoria::where('ingreso',$ingreso->id)->count();
                            $items = IngresosCategoria::where('ingreso',$ingreso->id)->get();
                        }else{
                            $itemscount=1;
                            $items = Ingreso::where('empresa',$empresa->id)->where('nro', $id)->get();
                        }

                        $plantilla = Plantilla::where('empresa', Auth::user()->empresa)->where('clasificacion', 'Facturacion')->where('tipo', 2)->where('status', 1)->get()->last();

                        if($plantilla){
                            $mensaje = str_replace('{{ $company }}', $empresa->nombre, $plantilla->contenido);
                            $mensaje = str_replace('{{ $name }}', ucfirst($cliente->nombre), $mensaje);
                            $mensaje = str_replace('{{ $factura->codigo }}', $ingreso->nro, $mensaje);
                        }else{
                            $mensaje = Auth::user()->empresa()->nombre.", le informa que su soporte de pago ha sido generado bajo el Nro. ".$ingreso->nro;
                        }


                        $retenciones = IngresosRetenciones::where('ingreso',$ingreso->id)->get();
                        $resolucion = NumeracionFactura::where('empresa', $empresa->id)
                        ->where('num_equivalente', 0)->where('nomina',0)->where('tipo',2)->where('preferida', 1)->first();
                        $paper_size = array(0,0,270,580);
                        $pdf = PDF::loadView('pdf.plantillas.ingreso_tirilla', compact('ingreso', 'items', 'retenciones',
                        'itemscount','empresa', 'resolucion'));
                        $pdf->setPaper($paper_size, 'portrait');
                        $pdf->save(public_path() . "/convertidor/" . $ingreso->nro . ".pdf")->stream();
                        $fields = [
                            "action"=>"sendFile",
                            "id"=>$numero."@c.us",
                            "file"=>public_path() . "/convertidor/" . $ingreso->nro . ".pdf", // debe existir el archivo en la ubicacion que se indica aqui
                            "mime"=>"application/pdf",
                            "namefile"=>"Recibo ".$ingreso->nro,
                            "mensaje"=>$mensaje,
                            "cron"=>"true"
                        ];

                        $request = new Request();
                        $request->merge($fields);
                        $controller = new CRMController();
                        $respuesta = $controller->whatsappActions($request);
                    }
                }

                ### ADJUNTO DE PAGO ###

                $xmax = 1080; $ymax = 720;
                if($request->file('adjunto_pago')){
                    $ext_permitidas = array('image/jpeg','image/png','image/gif');
                    $file = $request->file('adjunto_pago');
                    $nombre =  'adjunto_pago_'.$ingreso->nro.'.'.$file->getClientOriginalExtension();
                    Storage::disk('documentos')->put($nombre, \File::get($file));
                    $ingreso->adjunto_pago = $nombre;

                    if(in_array($file->getMimeType(), $ext_permitidas)){
                        switch($file->getMimeType()){
                            case 'image/jpeg':
                            $imagen = imagecreatefromjpeg(public_path('/adjuntos/documentos').'/'.$nombre);
                            break;
                            case 'image/png':
                            $imagen = imagecreatefrompng(public_path('/adjuntos/documentos').'/'.$nombre);
                            break;
                            case 'image/gif':
                            $imagen = imagecreatefromgif(public_path('/adjuntos/documentos').'/'.$nombre);
                            break;
                        }
                        $x = imagesx($imagen);
                        $y = imagesy($imagen);

                        if($x <= $xmax && $y <= $ymax){
                            switch($file->getMimeType()){
                                case 'image/jpeg':
                                imagejpeg(imagecreatefromjpeg(public_path('/adjuntos/documentos').'/'.$nombre), public_path('/adjuntos/documentos').'/'.$nombre, 5);
                                break;
                                case 'image/png':
                                imagepng(imagecreatefrompng(public_path('/adjuntos/documentos').'/'.$nombre), public_path('/adjuntos/documentos').'/'.$nombre, 5);
                                break;
                                case 'image/gif':
                                imagegif(imagecreatefromgif(public_path('/adjuntos/documentos').'/'.$nombre), public_path('/adjuntos/documentos').'/'.$nombre, 5);
                                break;
                            }
                        }else{
                            if($x >= $y) {
                                $nuevax = $xmax;
                                $nuevay = $nuevax * $y / $x;
                            }else{
                                $nuevay = $ymax;
                                $nuevax = $x / $y * $nuevay;
                            }
                            $img2 = imagecreatetruecolor($nuevax, $nuevay);
                            imagecopyresized($img2, $imagen, 0, 0, 0, 0, floor($nuevax), floor($nuevay), $x, $y);
                            switch($file->getMimeType()){
                                case 'image/jpeg':
                                imagejpeg($img2, public_path('/adjuntos/documentos').'/'.$nombre, 100);
                                break;
                                case 'image/png':
                                imagepng($img2, public_path('/adjuntos/documentos').'/'.$nombre, 100);
                                break;
                                case 'image/gif':
                                imagegif($img2, public_path('/adjuntos/documentos').'/'.$nombre, 100);
                                break;
                            }
                        }
                    }

                    $ingreso->save();
                }

                // // DB::commit();
                if (isset($empresa->envio_wpp_ingreso) && $empresa->envio_wpp_ingreso == 1) {
                    return redirect()->route('ingresos.tirillawpp', [
                        'id'   => $ingreso->nro, // igual que en tu blade
                        'name' => $factura->id
                    ])->with('success', 'SE HA CREADO SATISFACTORIAMENTE EL PAGO.' . $msj_siigo);
                }

                $mensaje = 'SE HA CREADO SATISFACTORIAMENTE EL PAGO.' . $msj_siigo;
                return redirect('empresa/ingresos/'.$ingreso->id)->with('success', $mensaje)->with('factura_id', $ingreso->id)->with('tirilla', $tirilla);
            }

        } catch (\Throwable $th) {
            // DB::rollBack();
            Log::error($th->getMessage());
            return back()->with('danger', $th->getMessage());
        }
    }

    public function funcionesPagoMK($contrato,$empresa,$ingreso){
        $mensaje = "";
        Log::debug("funcionesPagoMK: Iniciando procesamiento para Contrato #{$contrato->nro} (ID: {$contrato->id}), Empresa ID: {$empresa->id}, Ingreso ID: {$ingreso->id}");

        try {

            /* * * Smart OLT - DHCP (independiente de Mikrotik y CATV) * * */
            // Si queries_dhcp_smartolt es 1, usamos OLT para DHCP
            $condicionOLT = ($contrato !== null && ($contrato->conexion == 2 || $contrato->conexion == 3) && $empresa->queries_dhcp_smartolt == 1 && !empty($contrato->serial_onu));
            Log::debug("funcionesPagoMK: Verificando condición Smart OLT DHCP: " . ($condicionOLT ? 'CUMPLE' : 'NO CUMPLE') . " [Conexión: {$contrato->conexion}, DHCP OLT: " . ($empresa->queries_dhcp_smartolt ?? 'NULL') . ", Serial: " . ($contrato->serial_onu ?? 'VACÍO') . "]");
            
            if ($condicionOLT) {
                try {
                    Log::debug("funcionesPagoMK: Ejecutando enableOnu en OLT para serial: {$contrato->serial_onu}");
                    $oltController = app('App\Http\Controllers\OltController');
                    $oltController->enableOnu($contrato->serial_onu);

                    DB::reconnect();
                    $contrato->state = 'enabled';
                    $contrato->save();

                    $movimiento = new MovimientoLOG;
                    $movimiento->contrato    = $contrato->id;
                    $movimiento->modulo      = 5;
                    $movimiento->descripcion = '<i class="fas fa-check text-success"></i> <b>Cambiado en OLT (automático)</b> a Habilitado por pago de factura<br>';
                    $movimiento->created_by  = Auth::user() ? Auth::user()->id : $ingreso->created_by;
                    $movimiento->empresa     = Auth::user() ? Auth::user()->empresa : $empresa->id;
                    $movimiento->save();
                    Log::debug("funcionesPagoMK: OLT habilitado y log de movimiento guardado.");
                } catch (\Throwable $e) {
                    Log::error('Error en bloque Smart OLT de funcionesPagoMK: ' . $e->getMessage());
                }
            }
            /* * * Smart OLT - DHCP * * */

            /* * * API MK * * */
            // Si queries_dhcp_smartolt NO es 1 (es 0 o NULL), y hay un servidor asignado, usamos API Mikrotik
            $condicionMK = ($contrato->server_configuration_id && $empresa->queries_dhcp_smartolt != 1);
            Log::debug("funcionesPagoMK: Verificando condición API MK: " . ($condicionMK ? 'CUMPLE' : 'NO CUMPLE') . " [Server ID: {$contrato->server_configuration_id}, DHCP OLT: " . ($empresa->queries_dhcp_smartolt ?? 'NULL') . "]");
            
            if($condicionMK){
                $mikrotik = Mikrotik::where('id', $contrato->server_configuration_id)->first();
                if(!$mikrotik){
                    Log::warning('No se encontró configuración Mikrotik con id: ' . $contrato->server_configuration_id);
                    $ingreso->revalidacion_enable_internet = 1;
                    $ingreso->save();
                    return $mensaje;
                }
                
                Log::debug("funcionesPagoMK: Intentando conectar a Mikrotik ID: {$mikrotik->id} IP: {$mikrotik->ip}");
                $API = new RouterosAPI();
                $API->port = $mikrotik->puerto_api;
                $API->timeout = 5;
                $API->attempts = 2;
                $API->delay = 1;
                if ($API->connect($mikrotik->ip,$mikrotik->usuario,$mikrotik->clave)) {
                    Log::debug("funcionesPagoMK: Conexión exitosa a Mikrotik.");

                    $API->write('/ip/firewall/address-list/print', TRUE);
                    $ARRAYS = $API->read();

                    Log::debug("funcionesPagoMK: Verificando activeconn_secret: " . ($empresa->activeconn_secret ?? 0));
                    if(isset($empresa->activeconn_secret) && $empresa->activeconn_secret == 1){

                        #HABILITACION DEL SECRET#
                        Log::debug("funcionesPagoMK: Iniciando habilitación de Secret (Tipo Conexión: {$contrato->conexion})");
                        if ($contrato->conexion == 1 && $contrato->usuario != null) {
                            // Buscar el ID interno del secret
                            $API->write('/ppp/secret/print', false);
                            $API->write('?name=' . $contrato->usuario, true);
                            $ARRAYS = $API->read();

                            if (count($ARRAYS) > 0) {
                                $id = $ARRAYS[0]['.id'];
                                // Habilitar el secret
                                $API->write('/ppp/secret/enable', false);
                                $API->write('=numbers=' . $id, true);
                                $response = $API->read();
                                Log::debug("funcionesPagoMK: Secret '{$contrato->usuario}' habilitado. Respuesta: " . json_encode($response));
                            } else {
                                Log::warning("funcionesPagoMK: No se encontró el Secret '{$contrato->usuario}' en el MikroTik.");
                            }
                        }
                        #HABILITACION DEL SECRET#

                        #AGREGAMOS A IP_AUTORIZADAS#
                        Log::debug("funcionesPagoMK: Agregando IP {$contrato->ip} a ips_autorizadas");
                        $API->comm("/ip/firewall/address-list/add", array(
                            "address" => $contrato->ip,
                            "list" => 'ips_autorizadas'
                            )
                        );
                        #AGREGAMOS A IP_AUTORIZADAS#

                        $mensaje = "- Se ha habilitado el secret.";
                        // Recargar el modelo para evitar "Server has gone away" después de operaciones largas
                        DB::reconnect();

                        $ingreso->revalidacion_enable_internet = 1;
                        $ingreso->save();

                        $contrato->state = 'enabled';
                        $contrato->save();


                    }else{

                        // Recargar el modelo para evitar "Server has gone away" después de operaciones largas
                        DB::reconnect();

                        Log::debug("funcionesPagoMK: Iniciando remoción de Morosos para IP: {$contrato->ip}");
                        // OPTIMIZADO: Una sola consulta para obtener IDs directamente (elimina el print doble)
                        $API->write('/ip/firewall/address-list/print', false);
                        $API->write('?address=' . $contrato->ip, false);
                        $API->write('?list=morosos', false);
                        $API->write('=.proplist=.id');
                        $ARRAYS = $API->read();
                        Log::debug("funcionesPagoMK: Entradas encontradas en Morosos: " . count($ARRAYS));

                        if (!empty($ARRAYS)) {
                            #ELIMINAMOS DE MOROSOS#
                            // Recopilar TODOS los IDs (puede haber duplicados de la misma IP)
                            $idsToRemove = array_filter(array_column($ARRAYS, '.id'));

                            // Registro MovimientoLOG intentando remove
                            $movimiento = new MovimientoLOG;
                            $movimiento->contrato    = $contrato->id;
                            $movimiento->modulo      = 5;
                            $movimiento->descripcion = '[PAGO] Intentando remover de la lista de morosos la IP: ' . $contrato->ip . ' (' . count($idsToRemove) . ' entrada(s): ' . implode(', ', $idsToRemove) . ') | Ingreso: ' . $ingreso->nro;
                            $movimiento->created_by  = Auth::user() ? Auth::user()->id : $ingreso->created_by;
                            $movimiento->empresa     = Auth::user() ? Auth::user()->empresa : $empresa->id;
                            $movimiento->save();

                            // OPTIMIZADO: Un solo remove en batch con todos los IDs (=numbers= acepta lista separada por coma)
                            $READ = $API->comm('/ip/firewall/address-list/remove', [
                                'numbers' => implode(',', $idsToRemove)
                            ]);
                            Log::debug("funcionesPagoMK: Resultado remoción Morosos: " . json_encode($READ));

                            // Registro MovimientoLOG respuesta remove
                            $movimiento = new MovimientoLOG;
                            $movimiento->contrato    = $contrato->id;
                            $movimiento->modulo      = 5;
                            $movimiento->descripcion = '[PAGO] Respuesta remove batch (' . count($idsToRemove) . ' entrada(s)): ' . json_encode($READ);
                            $movimiento->created_by  = Auth::user() ? Auth::user()->id : $ingreso->created_by;
                            $movimiento->empresa     = Auth::user() ? Auth::user()->empresa : $empresa->id;
                            $movimiento->save();

                            // Verificar si realmente se eliminaron todas las entradas
                            $API->write('/ip/firewall/address-list/print', false);
                            $API->write('?address=' . $contrato->ip, false);
                            $API->write('?list=morosos', true);
                            $verificacion = $API->read();

                            $descVerif = empty($verificacion)
                                ? '[PAGO] Verificación exitosa: La IP ' . $contrato->ip . ' ya no está en la lista de morosos.'
                                : '[PAGO] ADVERTENCIA: La IP ' . $contrato->ip . ' sigue en morosos (' . count($verificacion) . ' entrada(s) restantes).';
                            Log::debug("funcionesPagoMK: {$descVerif}");

                            $movimiento = new MovimientoLOG;
                            $movimiento->contrato    = $contrato->id;
                            $movimiento->modulo      = 5;
                            $movimiento->descripcion = $descVerif;
                            $movimiento->created_by  = Auth::user() ? Auth::user()->id : $ingreso->created_by;
                            $movimiento->empresa     = Auth::user() ? Auth::user()->empresa : $empresa->id;
                            $movimiento->save();

                            #AGREGAMOS A IP_AUTORIZADAS#
                            $resultAdd = $API->comm('/ip/firewall/address-list/add', [
                                'address' => $contrato->ip,
                                'list'    => 'ips_autorizadas'
                            ]);
                            Log::debug("funcionesPagoMK: Resultado agregar a ips_autorizadas: " . json_encode($resultAdd));

                            $movimiento = new MovimientoLOG;
                            $movimiento->contrato    = $contrato->id;
                            $movimiento->modulo      = 5;
                            $movimiento->descripcion = '[PAGO] Resultado agregar a ips_autorizadas: ' . json_encode($resultAdd);
                            $movimiento->created_by  = Auth::user() ? Auth::user()->id : $ingreso->created_by;
                            $movimiento->empresa     = Auth::user() ? Auth::user()->empresa : $empresa->id;
                            $movimiento->save();
                            #AGREGAMOS A IP_AUTORIZADAS#

                            $mensaje = "- Se ha sacado la ip de morosos.";

                            DB::reconnect();

                            $ingreso->revalidacion_enable_internet = 1;
                            $ingreso->save();

                            $contrato->state = 'enabled';
                            $contrato->save();

                            // Etiqueta automática: contrato habilitado por pago de factura
                            \App\Traits\AplicaEtiquetaAutomatica::aplicarEtiquetaAutomatica(
                                $contrato->id,
                                $empresa->id,
                                \App\EtiquetaAutomaticaContrato::MODULO_CONTRATOS,
                                \App\EtiquetaAutomaticaContrato::PAGO_FACTURA
                            );

                            $movimiento = new MovimientoLOG;
                            $movimiento->contrato    = $contrato->id;
                            $movimiento->modulo      = 5;
                            $movimiento->descripcion = 'Proceso de habilitación completado. Contrato marcado como habilitado y revalidación de internet exitosa.';
                            $movimiento->created_by  = Auth::user() ? Auth::user()->id : $ingreso->created_by;
                            $movimiento->empresa     = Auth::user() ? Auth::user()->empresa : $empresa->id;
                            $movimiento->save();

                            #ELIMINAMOS DE MOROSOS#
                        }else{
                            // [FIX] Aunque la IP no estaba en morosos, se debe agregar a ips_autorizadas
                            // para evitar que un CRON posterior encuentre state='enabled' con factura abierta
                            // y vuelva a cortar el servicio por race condition.
                            if ($contrato->ip && filter_var($contrato->ip, FILTER_VALIDATE_IP)) {
                                $resultAddAut = $API->comm('/ip/firewall/address-list/add', [
                                    'address' => $contrato->ip,
                                    'list'    => 'ips_autorizadas'
                                ]);
                                Log::debug("funcionesPagoMK: [FIX] Agregando a ips_autorizadas (no estaba en morosos): " . json_encode($resultAddAut));

                                $movimiento = new MovimientoLOG;
                                $movimiento->contrato    = $contrato->id;
                                $movimiento->modulo      = 5;
                                $movimiento->descripcion = '[Manual] Resultado agregar a ips_autorizadas: ' . json_encode($resultAddAut);
                                $movimiento->created_by  = Auth::user() ? Auth::user()->id : $ingreso->created_by;
                                $movimiento->empresa     = Auth::user() ? Auth::user()->empresa : $empresa->id;
                                $movimiento->save();
                            }

                            DB::reconnect();

                            $ingreso->revalidacion_enable_internet = 1;
                            $ingreso->save();

                            $contrato->state = 'enabled';
                            $contrato->save();

                            $movimiento = new MovimientoLOG;
                            $movimiento->contrato    = $contrato->id;
                            $movimiento->modulo      = 5;
                            $movimiento->descripcion = "[Manual] Al realizar el pago del ingreso nro {$ingreso->nro}, la IP {$contrato->ip} del contrato nro {$contrato->nro} no se encontró en la lista de morosos. Se habilitó el contrato y se agregó a ips_autorizadas.";
                            $movimiento->created_by  = Auth::user() ? Auth::user()->id : $ingreso->created_by;
                            $movimiento->empresa     = Auth::user() ? Auth::user()->empresa : $empresa->id;
                            $movimiento->save();

                            // Etiqueta automática: contrato habilitado por pago de factura
                            \App\Traits\AplicaEtiquetaAutomatica::aplicarEtiquetaAutomatica(
                                $contrato->id,
                                $empresa->id,
                                \App\EtiquetaAutomaticaContrato::MODULO_CONTRATOS,
                                \App\EtiquetaAutomaticaContrato::PAGO_FACTURA
                            );

                            Log::info('Contrato nro:' . $contrato->nro . ' no estaba en morosos. Habilitado y agregado a ips_autorizadas.');
                            Log::debug("funcionesPagoMK: La IP {$contrato->ip} no se encontró en la lista de morosos. Habilitación completa aplicada.");
                        }
                    }
                    $API->disconnect();
                } else {
                    Log::error("funcionesPagoMK: No se pudo conectar a Mikrotik ID: {$mikrotik->id} IP: {$mikrotik->ip}");
                }
            }else{
                Log::debug("funcionesPagoMK: Saltando bloque MK (No cumple condiciones o DHCP OLT activo).");
                $ingreso->revalidacion_enable_internet = 1;
                $ingreso->save();
            }
            /* * * API MK * * */

             /* * * API CATV * * */
            $condicionCATV = (($contrato !== null && isset($contrato->olt_sn_mac)) && $empresa->adminOLT != null);
            Log::debug("funcionesPagoMK: Verificando condición API CATV: " . ($condicionCATV ? 'CUMPLE' : 'NO CUMPLE') . " [OLT SN/MAC: " . ($contrato->olt_sn_mac ?? 'N/A') . ", AdminOLT: " . ($empresa->adminOLT ?? 'N/A') . "]");

            if($condicionCATV){
                Log::debug("funcionesPagoMK: Ejecutando enable_catv para MAC: {$contrato->olt_sn_mac}");
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => $empresa->adminOLT.'/api/onu/enable_catv/'.$contrato->olt_sn_mac,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_HTTPHEADER => array(
                        'X-token: '.$empresa->smartOLT
                    ),
                    ));

                $response = curl_exec($curl);
                $responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                $decodedResponse = json_decode($response);
                Log::debug("funcionesPagoMK: Respuesta CATV (Status Code: $responseCode): " . $response);

                if(isset($decodedResponse->status) && $decodedResponse->status == true){

                    $ingreso->revalidacion_enable_tv = 1;
                    $ingreso->save();

                    $contrato->state_olt_catv = 1;
                    $contrato->save();
                    Log::debug("funcionesPagoMK: CATV habilitado exitosamente.");
                } else {
                    Log::warning("funcionesPagoMK: Falló habilitación CATV.");
                }
                curl_close($curl);
            }else{
                $ingreso->revalidacion_enable_tv = 1;
                $ingreso->save();
            }
            /* * * API CATV * * */


        } catch (\Throwable $th) {
            Log::error('Error en funcionesPagoMK: ' . $th->getMessage() . ' en la linea ' . $th->getLine() . ' del archivo ' . $th->getFile());
        }

        return $mensaje;
    }

    public function storeIngresoPucCategoria($request){

        $nro = Numeracion::where('empresa', Auth::user()->empresa)->first();
            $caja = $nro->caja;

        while (true) {
            $numero = Ingreso::where('empresa', Auth::user()->empresa)->where('nro', $caja)->count();
            if ($numero == 0) {
                break;
            }
            $caja++;
        }

        //sumo a las numeraciones el recibo
        $nro->caja = $caja + 1;
        $nro->save();

        $ingreso = new Ingreso;
        $ingreso->nro = $caja;
        $ingreso->empresa = Auth::user()->empresa;
        $ingreso->cliente = $request->cliente;
        $ingreso->cuenta = $request->cuenta;
        $ingreso->metodo_pago = $request->metodo_pago;
        $ingreso->notas = $request->notas;
        $ingreso->tipo = 2;
        $ingreso->fecha = Carbon::parse($request->fecha)->format('Y-m-d');
        $ingreso->observaciones = mb_strtolower($request->observaciones);
        $ingreso->created_by = Auth::user()->id;
        $ingreso->anticipo = 1;
        $ingreso->valor_anticipo = $request->valor_recibido;
        $ingreso->save();

        $impuesto = Impuesto::where('porcentaje',0)->first();

        //Registramos el ingreso de anticipo en una sola cuenta del puc.
        $items = new IngresosCategoria;
        $items->valor = $this->precision($request->valor_recibido);
        $items->id_impuesto = $impuesto->id;
        $items->impuesto = $impuesto->porcentaje;
        $items->ingreso = $ingreso->id;
        $items->categoria = $request->puc;
        $items->anticipo = $request->anticipo; //hace referencia a la pk de la tabla anticipo
        $items->cant = 1;
        $items->save();

        $contacto = Contacto::find($request->cliente);
        $contacto->saldo_favor+=$request->valor_recibido;
        $contacto->save();

        //ingresos
        $this->up_transaccion(1, $ingreso->id, $ingreso->cuenta, $ingreso->cliente, 1, $ingreso->pago(), $ingreso->fecha, 'Ingreso por concepto de reconexión');

        //mandamos por parametro el ingreso y el 1 (guardar)
        PucMovimiento::ingreso($ingreso,1,0);

        DB::commit();
    }

    public function getIngresoTirillaTemp($id, $token)
    {
        // 1️⃣ Validar token de seguridad
        if ($token !== config('app.key')) {
            abort(403, 'Token inválido');
        }

        // 2️⃣ Buscar ingreso
        $ingreso = Ingreso::where('empresa', Auth::user()->empresa)
                        ->where('nro', $id)
                        ->first();

        if (!$ingreso) {
            abort(404, 'Ingreso no encontrado');
        }

        // 3️⃣ Generar nombre y rutas relativas
        $fileName = 'Ingreso_' . preg_replace('/[^A-Za-z0-9\-\_]/', '', $ingreso->nro) . '.pdf';
        $folderPath = public_path('documentos_meta');
        $storagePath = $folderPath . '/' . $fileName;

        // 4️⃣ Crear carpeta si no existe
        if (!file_exists($folderPath)) {
            mkdir($folderPath, 0775, true);
        }

        // 5️⃣ Si ya existe, devolver directamente
        if (file_exists($storagePath)) {
            return response()->file($storagePath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            ]);
        }

        // 5️⃣ Generar el PDF en binario
        if ($ingreso->tipo == 1) {
            $itemscount = IngresosFactura::where('ingreso', $ingreso->id)->count();
            $items = IngresosFactura::join('items_factura as itf', 'itf.factura', 'ingresos_factura.factura')
                                    ->select('itf.*')
                                    ->where('ingreso', $ingreso->id)
                                    ->get();
        } else if ($ingreso->tipo == 2) {
            $itemscount = IngresosCategoria::where('ingreso', $ingreso->id)->count();
            $items = IngresosCategoria::where('ingreso', $ingreso->id)->get();
        } else {
            $itemscount = 1;
            $items = Ingreso::where('empresa', Auth::user()->empresa)
                            ->where('nro', $id)
                            ->get();
        }

        $retenciones = IngresosRetenciones::where('ingreso', $ingreso->id)->get();
        $resolucion = NumeracionFactura::where('empresa', Auth::user()->empresa)
                                    ->where('num_equivalente', 0)
                                    ->where('nomina', 0)
                                    ->where('tipo', 2)
                                    ->where('preferida', 1)
                                    ->first();
        $empresa = Empresa::find($ingreso->empresa);

        // Obtener el contrato correcto desde la factura asociada al ingreso
        $contratoNro = null;
        $direccionMostrar = null;

        if ($ingreso->tipo == 1) {
            $primeraFactura = IngresosFactura::where('ingreso', $ingreso->id)->first();

            if ($primeraFactura) {
                // Opción 1: Buscar en la tabla facturas_contratos
                $contratoRelacion = DB::table('facturas_contratos')
                    ->where('factura_id', $primeraFactura->factura)
                    ->first();

                if ($contratoRelacion) {
                    $contratoNro = $contratoRelacion->contrato_nro;
                    $contratoObj = Contrato::where('nro', $contratoNro)->first();
                    if ($contratoObj) {
                        $direccionMostrar = $contratoObj->address_street ?? $contratoObj->direccion_instalacion ?? null;
                    }
                } else {
                    // Opción 2: Buscar desde el contrato_id directo de la factura
                    $factura = Factura::find($primeraFactura->factura);
                    if ($factura && $factura->contrato_id) {
                        $contrato = Contrato::find($factura->contrato_id);
                        if ($contrato) {
                            $contratoNro = $contrato->nro;
                            $direccionMostrar = $contrato->address_street ?? $contrato->direccion_instalacion ?? null;
                        }
                    }
                }
            }
        }

        // Si no hay dirección del contrato, usar la del cliente como fallback
        if (!$direccionMostrar) {
            $direccionMostrar = $ingreso->cliente()->direccion;
        }

        $paper_size = [0, 0, 270, 580];

        if ($ingreso->valor_anticipo > 0) {
            $pdf = PDF::loadView('pdf.plantillas.ingreso_tirilla_anticipo', compact(
                'ingreso', 'items', 'retenciones', 'itemscount', 'empresa', 'resolucion', 'contratoNro', 'direccionMostrar'
            ));
        } else {
            $pdf = PDF::loadView('pdf.plantillas.ingreso_tirilla', compact(
                'ingreso', 'items', 'retenciones', 'itemscount', 'empresa', 'resolucion', 'contratoNro', 'direccionMostrar'
            ));
        }

        $pdf->setPaper($paper_size, 'portrait');
        $ingresoPDF = $pdf->output();

        // 6️⃣ Guardar el archivo directamente apuntando al directorio público
        file_put_contents($storagePath, $ingresoPDF);

        // 8️⃣ Retornar el archivo directamente
        return response()->file($storagePath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
        ]);
    }

    public function tirillaWpp($nro, WapiService $wapiService){
        $ingreso = Ingreso::where('empresa', Auth::user()->empresa)->where('nro', $nro)->first();

        if (!$ingreso) {
            return back()->with('error', 'No se encontró el ingreso especificado.');
        }

        if ($ingreso->whatsapp == 1) {
            return back()->with('error', 'Esta tirilla ya ha sido enviada por WhatsApp.');
        }

        if ($ingreso->cont_message_undeliverable >= 3) {
            return back()->with('error', 'La siguiente linea telefónica según nuestros análisis probablemente no tiene una linea de whatsapp activa, te recomendamos comunicarte y enviar el documento con otra alternativa');
        }

        // 1️⃣ Buscar instancia activa
        $instance = Instance::where('company_id', auth()->user()->empresa)
                            ->where('activo', 1)
                            ->where('meta', 0) 
                            ->first();

        if (is_null($instance) || empty($instance) || empty($instance->phone_number_id)) {
             $instance = Instance::where('company_id', auth()->user()->empresa)
                            ->where('activo', 1)
                            ->whereNotNull('phone_number_id')
                            ->first();
        }
        
        if (is_null($instance) || empty($instance) || empty($instance->phone_number_id)) {
            return back()->with('error', 'Aún no ha creado una instancia activa con ID de teléfono válido, por favor póngase en contacto con el administrador.');
        }

        $cliente = $ingreso->cliente();
        if ($cliente->celular == null) {
            $cliente->celular = $cliente->telefono;
        }
        if (!$cliente->celular) {
            return back()->with('error', 'El cliente no tiene número de teléfono registrado.');
        }

        $prefijo = '57'; // valor por defecto (Colombia)
        if (!empty($cliente->fk_idpais)) {
            $prefijoData = \DB::table('prefijos_telefonicos')
                ->where('iso2', strtoupper($cliente->fk_idpais))
                ->first();
            if ($prefijoData && !empty($prefijoData->phone_code)) {
                $prefijo = $prefijoData->phone_code;
            }
        }

        // 📱 Construir número completo con prefijo dinámico
        $telefonoCompleto = '+' . $prefijo . ltrim($cliente->celular, '0');

        /**
         * 🧭 Si META == 0 → flujo normal (usa plantilla WABA)
         * 🧭 Si META == 1 → flujo alternativo (envía mensaje manual con PDF base64)
         */
        
        // Validar que sea instancia Meta Direct (type=0, meta=0)
        if ($instance->type != 1 || $instance->meta != 0) {
            return back()->with('error', 'La instancia configurada no es compatible con Meta Direct (Type != 0).');
        }

        // ============================================================
        // 🧩 GENERAR Y GUARDAR PDF TEMPORALMENTE
        // ============================================================
        $token = config('app.key');
        $this->getIngresoTirillaTemp($nro, $token);

        // Asegurar que el archivo fue generado y accesible
        $fileName = 'Ingreso_' . preg_replace('/[^A-Za-z0-9\-\_]/', '', $ingreso->nro) . '.pdf';
        $storagePath = public_path('documentos_meta/' . $fileName);

        // Esperar hasta que el archivo exista (máx. 5 intentos)
        $attempts = 0;
        while (!file_exists($storagePath) && $attempts < 5) {
            usleep(300000); // 0.3 segundos
            $attempts++;
        }

        if (!file_exists($storagePath)) {
            return back()->with('error', 'No se pudo generar el archivo PDF temporal.');
        }

        // Generar la URL pública accesible
        $urlDoc = url('documentos_meta/' . $fileName);

        // ============================================================
        // 📦 CONSTRUIR BODY PARA META
        // ============================================================
        $empresaObj = auth()->user()->empresa();
        $total = $ingreso->total()->total;

        // Buscar plantilla preferida para ingresos
        $plantilla = Plantilla::where('empresa', auth()->user()->empresa)
            ->where('tipo', 3) // 3 = Ingresos/Recibos/Facturas (General Meta)
            ->where('preferida_tirilla', 1)
            ->where('status', 1)
            ->first();

        if (!$plantilla) {
            return back()->with('error', 'No hay una plantilla preferida configurada para Tirillas (preferida_tirilla=1).');
        }

        // Procesar body_dinamic
        $bodyTextParams = [];
        if ($plantilla->body_dinamic) {
            $bodyDinamicArray = json_decode($plantilla->body_dinamic, true);
            if (is_array($bodyDinamicArray) && isset($bodyDinamicArray[0]) && is_array($bodyDinamicArray[0])) {
                $bodyDinamicArray = $bodyDinamicArray[0];
            }

            if (is_array($bodyDinamicArray)) {
                foreach ($bodyDinamicArray as $paramTemplate) {
                    $paramValue = is_string($paramTemplate) ? $paramTemplate : '';
                    
                    // Usar helper para procesar campos dinámicos
                    $paramValue = \App\Helpers\CamposDinamicosHelper::procesarCamposDinamicos($paramValue, $cliente, null, $empresaObj, $ingreso);
                    
                    $bodyTextParams[] = $paramValue; 
                }
            }
        } else {
            // Fallback (aunque si es preferida debería tener config, mantenemos compatibilidad)
            $bodyTextArray = json_decode($plantilla->body_text, true);
            if (is_array($bodyTextArray) && isset($bodyTextArray[0]) && is_array($bodyTextArray[0])) {
                $bodyTextParams = $bodyTextArray[0];
            } else {
                $bodyTextParams = [
                    $cliente->nombre . " " . $cliente->apellido1,
                    $empresaObj->nombre,
                    number_format($total, 0, ',', '.')
                ];
            }
        }

        $parameters = [];
        foreach ($bodyTextParams as $paramValue) {
            $parameters[] = ["type" => "text", "text" => strval($paramValue)];
        }

        $components = [
            [
                "type" => "body",
                "parameters" => $parameters
            ]
        ];

        $metaService = new \App\Services\MetaWhatsAppService();

        if ($plantilla->body_header === 'DOCUMENT') {
            // Subir PDF a Meta en vez de pasar un link
            $mediaId = $metaService->uploadMedia(
                $instance->phone_number_id,
                $storagePath,
                'application/pdf'
            );

            if (!$mediaId) {
                return back()->with('error', 'No se pudo subir el documento PDF de la tirilla a Meta.');
            }

            array_unshift($components, [
                "type" => "header",
                "parameters" => [
                    [
                        "type" => "document",
                        "document" => [
                            "id"       => $mediaId,
                            "filename" => "Recibo_Caja_{$ingreso->nro}.pdf"
                        ]
                    ]
                ]
            ]);
        }

        // ============================================================
        // 🚀 ENVIAR MENSAJE (MetaWhatsAppService)
        // ============================================================

        $response = (object) $metaService->sendTemplate(
            $instance->phone_number_id,
            $telefonoCompleto, // Ya tiene prefijo
            $plantilla->title,
            $plantilla->language ?? 'es',
            $components
        );

        // ============================================================
        // ✅ VALIDAR RESPUESTA Y REGISTRAR LOG
        // ============================================================
        $responseData = json_decode(json_encode($response), true);
        $status = 'error';

        if (isset($responseData['success']) && $responseData['success']) {
            $status = 'success';
        } elseif (isset($responseData['messaging_product']) && $responseData['messaging_product'] === 'whatsapp') {
            if (isset($responseData['messages']) && count($responseData['messages']) > 0) {
                $status = 'success';
            }
        }

        // Construir mensaje visual para el log
        $mensajeEnviado = $plantilla->contenido ?? '';
         foreach ($bodyTextParams as $index => $paramValue) {
            $mensajeEnviado = str_replace('{{' . ($index + 1) . '}}', $paramValue, $mensajeEnviado);
        }
        if ($plantilla->body_header === 'DOCUMENT') {
            $mensajeEnviado = "[Documento adjunto: Recibo_Caja_{$ingreso->nro}.pdf]\n\n" . $mensajeEnviado;
        }

        // Registrar log
        WhatsappMetaLog::create([
            'status' => $status,
            'response' => json_encode($response),
            'factura_id' => $ingreso->nro, 
            'incoming_payment_id' => $ingreso->id, // Identificador del ingreso para historial
            'contacto_id' => $cliente->id,
            'empresa' => Auth::user()->empresa,
            'mensaje_enviado' => $mensajeEnviado,
            'plantilla_id' => $plantilla->id,
            'enviado_por' => Auth::user()->id
        ]);

        if ($status === 'success') {
            $ingreso->whatsapp = 1;
            $ingreso->save();
            // Sync con Chat System (Centralizado)
            $wamid = $responseData['data']['messages'][0]['id'] ?? ($responseData['messages'][0]['id'] ?? null);
            
            if ($wamid) {
                // El teléfono ya incluye prefijo en tirillaWpp ($telefonoCompleto)
                // quitamos el + si lo tiene para la API
                $phone = ltrim($telefonoCompleto, '+');

                $companyNit = $empresaObj->nit ?? \App\Empresa::find(1)->nit;

                $this->registerCentralizedBatch(
                    $instance->phone_number_id,
                    $phone,
                    $wamid,
                    $mensajeEnviado,
                    $cliente->nombre . ' ' . $cliente->apellido1,
                    'template',
                    'sent',
                    null,
                    null,
                    $ingreso->id,
                    $companyNit,
                    $plantilla->id
                );
            }

            return back()->with('success', 'Mensaje enviado correctamente.');
        } else {
            return back()->with('error', 'No se pudo enviar el mensaje: ' . json_encode($responseData));
        }
    }

    public function updateIngresoPucCategoria($request,$id){

        //sumo a las numeraciones el recibo
        $ingreso = Ingreso::where('empresa',Auth::user()->empresa)->where('nro', $id)->first();

        // Validar el número si viene en el request
        if ($request->has('nro') && $request->nro != $ingreso->nro) {
            // Verificar si existe otro ingreso con el mismo número (excluyendo el actual)
            $existeIngreso = Ingreso::where('empresa', Auth::user()->empresa)
                ->where('nro', $request->nro)
                ->where('id', '!=', $ingreso->id)
                ->first();

            if ($existeIngreso) {
                return back()->with('error', 'Ya existe un registro con el número ' . $request->nro . '. Por favor, use otro número.')->withInput();
            }

            // Actualizar el número solo si ha cambiado y no existe duplicado
            $ingreso->nro = $request->nro;
        }

        $ingreso->empresa = Auth::user()->empresa;
        $ingreso->cliente = $request->cliente;
        $ingreso->cuenta = $request->cuenta;
        $ingreso->metodo_pago = $request->metodo_pago;
        $ingreso->notas = $request->notas;
        $ingreso->tipo = 2;
        $ingreso->fecha = Carbon::parse($request->fecha)->format('Y-m-d');
        $ingreso->observaciones = mb_strtolower($request->observaciones);
        $ingreso->created_by = Auth::user()->id;
        $ingreso->anticipo = 1;
        $ingreso->valor_anticipo = $request->valor_recibido;
        $ingreso->save();

        $impuesto = Impuesto::where('porcentaje',0)->first();

        //Registramos el ingreso de anticipo en una sola cuenta del puc.
        $items = IngresosCategoria::where('ingreso',$ingreso->id)->get();
        // dd($items);
        foreach($items as $item){
        $item->valor = $this->precision($request->valor_recibido);
        $item->id_impuesto = $impuesto->id;
        $item->impuesto = $impuesto->porcentaje;
        $item->ingreso = $ingreso->id;
        $item->categoria = $request->puc;
        $item->anticipo = $request->anticipo; //hace referencia a la pk de la tabla anticipo
        $item->cant = 1;
        $item->save();
        }

        $contacto = Contacto::find($request->cliente);
        $contacto->saldo_favor+=$request->valor_recibido;
        $contacto->save();

        //ingresos
        $this->up_transaccion(1, $ingreso->id, $ingreso->cuenta, $ingreso->cliente, 1, $ingreso->pago(), $ingreso->fecha, $ingreso->descripcion);

        //mandamos por parametro el ingreso y el 1 (guardar)
        PucMovimiento::ingreso($ingreso,2,0);
    }

    public function showMovimiento($id){
        $this->getAllPermissions(Auth::user()->id);
        $ingreso = Ingreso::find($id);
        /*
        obtenemos los movimiento sque ha tenido este documento
        sabemos que se trata de un tipo de movimiento 03
        */
        $movimientos = PucMovimiento::where('documento_id',$id)->where('tipo_comprobante',1)->get();
        if ($ingreso) {
            view()->share(['title' => 'Detalle Movimiento ' .$ingreso->codigo]);
            return view('ingresos.show-movimiento')->with(compact('ingreso','movimientos'));
        }
        return redirect('empresa/ingresos')->with('success', 'No existe un registro con ese id');
    }

    public function show($id){
        $this->getAllPermissions(Auth::user()->id);
        $ingreso = Ingreso::where('empresa',Auth::user()->empresa)->where('id', $id)->first();

        if ($ingreso) {
            // Logs de WhatsApp Meta asociados a este ingreso (incoming_payment_id)
            // Solo se muestran registros con estados delivered o read.
            // Agrupamos por wamid para que un mismo mensaje no aparezca duplicado
            // cuando cambia de "delivered" a "read"; se muestra solo el estado más reciente.
            $rawWhatsappLogs = \App\WhatsappMetaLog::where('incoming_payment_id', $ingreso->id)
                ->whereIn('status', ['delivered', 'read'])
                ->orderBy('created_at', 'desc')
                ->get();

            $whatsappLogs = $rawWhatsappLogs
                ->groupBy('wamid')
                ->map(function ($group) {
                    // Tomar el registro más reciente por wamid
                    return $group->sortByDesc('created_at')->first();
                })
                ->values();

            if ($ingreso->tipo==1) {
                $titulo='Pago a facturas de venta';
                $items = IngresosFactura::where('ingreso',$ingreso->id)->get();
            }else if($ingreso->tipo==3){
                $titulo=$ingreso->detalle(true);
            }else{
                $titulo='Ingreso';
                $items = IngresosCategoria::where('ingreso',$ingreso->id)->get();
            }
            view()->share(['icon' =>'', 'title' => $titulo, 'middel'=>true]);
            $retenciones = IngresosRetenciones::where('ingreso',$ingreso->id)->get();
            $print = false;
            return view('ingresos.show')->with(compact('ingreso', 'items', 'retenciones', 'print', 'whatsappLogs'));
        }
        return redirect('empresa/ingresos')->with('error', 'No existe un registro con ese id');
    }

    public function edit($id){
        $this->getAllPermissions(Auth::user()->id);
        $ingreso = Ingreso::where('empresa',Auth::user()->empresa)->where('id', $id)->first();

        if(!$ingreso){
            return redirect('empresa/ingresos')->with('danger', 'no existe un pago con ese número');
        }

        //tomamos las formas de pago cuando no es un recibo de caja por anticipo
        $formas = FormaPago::where('relacion',1)->orWhere('relacion',3)->get();

        $formasPago = PucMovimiento::where('documento_id',$ingreso->id)->where('tipo_comprobante',1)->whereIn('enlace_a',[4,5])->get();

        if ($ingreso) {
            view()->share(['icon' =>'', 'title' => 'Modificar Ingreso (Recibo de Caja) #'.$ingreso->nro]);
            if ($ingreso->tipo==3) {
                return redirect('empresa/ingresos')->with('danger', 'No puede editar un pago de nota de débito');
            }
            if ($ingreso->tipo==4) {
                return redirect('empresa/ingresos')->with('danger', 'No puede editar una transferencia');
            }
            if (Auth::user()->rol == 8 || Auth::user()->cuenta > 0) {
                $bancos = Banco::where('estatus', 1)->where('empresa', Auth::user()->empresa)->whereIn('id', auth()->user()->cuentas())->get();
            } else {
                $bancos = Banco::where('empresa', Auth::user()->empresa)->where('estatus', 1)->get();
            }
            $clientes = (Auth::user()->empresa()->oficina) ? Contacto::where('status', 1)->whereIn('tipo_contacto',[0,2])->where('empresa', Auth::user()->empresa)->where('oficina', Auth::user()->oficina)->orderBy('nombre','asc')->get() : Contacto::where('status', 1)->whereIn('tipo_contacto',[0,2])->where('empresa', Auth::user()->empresa)->orderBy('nombre','asc')->get();
            $metodos_pago =DB::table('metodos_pago')->get();
            $retenciones = Retencion::where('empresa',Auth::user()->empresa)->where('modulo',1)->get();
            $categorias = Puc::where('empresa',auth()->user()->empresa)
            ->whereRaw('length(codigo) >= 6')
            ->get();
            $impuestos = Impuesto::where('empresa',Auth::user()->empresa)->orWhere('empresa', null)->Where('estado', 1)->get();
            $items= $retencionesIngreso=array();
            $items = IngresosFactura::where('ingreso',$ingreso->id);

            if($ingreso->tipo==2){
                $items = IngresosCategoria::where('ingreso',$ingreso->id);
                $retencionesIngreso = IngresosRetenciones::where('ingreso',$ingreso->id)->get();
            }

            $cuentaIngresoDinero = false;
            $cuentaAnticipo = false;
            $valorAnticipo = false;
            if($ingreso->anticipo == 1){
                $itemAnticipo = $items->first();
                $cuentaIngresoDinero = isset($itemAnticipo->categoria) ? $itemAnticipo->categoria : false; //hace referencia a la pk de la tabla puc
                $cuentaAnticipo = isset($itemAnticipo->anticipo) ? $itemAnticipo->anticipo : false; //hace referencia a la pk de la tabla anticipo
                $valorAnticipo = isset($itemAnticipo->valor) ? round($itemAnticipo->valor) : false;
            }

            $items = $items->get();

            //obtiene los anticipos relacionados con este modulo (Ingresos)
            $anticipos = Anticipo::where('relacion',1)->orWhere('relacion',3)->get();

            //obtiene las formas de pago relacionadas con este modulo (Facturas)
            $relaciones = FormaPago::where('relacion',1)->orWhere('relacion',3)->get();
            $banco = Banco::Find($ingreso->cuenta);

            return view('ingresos.edit')->with(compact('ingreso', 'items', 'clientes', 'retencionesIngreso',
            'categorias', 'bancos', 'metodos_pago', 'impuestos','items', 'retenciones','formasPago','anticipos','formas'
            ,'cuentaIngresoDinero','cuentaAnticipo','valorAnticipo','relaciones','banco'));
        }
        return redirect('empresa/ingresos')->with('error', 'No existe un registro con ese id');
    }

    public function update(Request $request, $id){
        //el tipo 2 significa que estoy realizando un ingreso para darle un anticipo a un cliente
        if($request->realizar == 2){

            //Cuando se realiza el ingreso por categoría.
            $resultado = $this->updateIngresoPucCategoria($request,$id);

            // Si updateIngresoPucCategoria retorna un redirect (error de validación), retornarlo
            if ($resultado instanceof \Illuminate\Http\RedirectResponse) {
                return $resultado;
            }

            $mensaje='SE HA ACTUALIZADO SATISFACTORIAMENTE EL ANTICIPO';
            return redirect('empresa/ingresos')->with('success', $mensaje);

        }

        //pendiente metodo de actualizar un ingreso por categorias, (en elos movimeintos del puc)

        $ingreso = Ingreso::where('empresa',Auth::user()->empresa)->where('nro', $id)->first();

        if ($ingreso) {
            if ($ingreso->tipo==3) {
                return redirect('empresa/ingresos')->with('error', 'No puede editar un pago de nota de débito');
            }

            // Validar si paso de Anticipo a Pago a factura/categoría
            if ($ingreso->anticipo == 1 && $ingreso->valor_anticipo > 0) {
                $contacto_saldo = Contacto::find($ingreso->cliente);
                if ($contacto_saldo) {
                    $saldo_anterior = $contacto_saldo->saldo_favor;
                    $nuevo_saldo = $saldo_anterior - $ingreso->valor_anticipo;
                    $contacto_saldo->saldo_favor = $nuevo_saldo;
                    $contacto_saldo->save();

                    DB::table('log_saldos')->insert([
                        'id_contacto' => $contacto_saldo->id,
                        'accion' => 'Edición de ingreso Nro ' . $ingreso->nro . ' (Cambio de Anticipo a Pago), resta de saldo a favor de ' . \App\Funcion::Parsear($ingreso->valor_anticipo) . ' (Saldo anterior: ' . \App\Funcion::Parsear($saldo_anterior) . ' / Actual: ' . \App\Funcion::Parsear($nuevo_saldo) . ')',
                        'created_by' => Auth::user()->id,
                        'fecha' => Carbon::now()->format('Y-m-d'),
                        'created_at' => Carbon::now(),
                    ]);
                }
                
                $ingreso->anticipo = null;
                $ingreso->valor_anticipo = null;
            }

            $request->validate([
                'cuenta' => 'required|numeric',
                'nro' => [
                    'required',
                    'numeric',
                    function ($attribute, $value, $fail) use ($ingreso) {
                        // Verificar si existe otro ingreso con el mismo número (excluyendo el actual)
                        $existeIngreso = Ingreso::where('empresa', Auth::user()->empresa)
                            ->where('nro', $value)
                            ->where('id', '!=', $ingreso->id)
                            ->first();

                        if ($existeIngreso) {
                            $fail('Ya existe un registro con el número ' . $value . '. Por favor, use otro número.');
                        }
                    },
                ]
            ]);

            //Si se cambia de tipo se elimina todos
            if ($ingreso->tipo!=$request->tipo) {
                if ($ingreso->tipo==1) {
                    DB::table('factura')->where('empresa',Auth::user()->empresa)->whereRaw('id in (Select id from ingresos_factura where ingreso=?)', [$ingreso->id])->update(['estatus'=>1]);
                    IngresosFactura::where('ingreso',$ingreso->id)->delete();
                }else{
                    IngresosCategoria::where('ingreso',$ingreso->id)->delete();
                }
                IngresosRetenciones::where('ingreso',$ingreso->id)->delete();
            }

            // Actualizar el número solo si ha cambiado
            if ($ingreso->nro != $request->nro) {
                $ingreso->nro = $request->nro;
            }


            $ingreso->cliente=$request->cliente;
            $ingreso->cuenta=$request->cuenta;
            $ingreso->metodo_pago=$request->metodo_pago;
            $ingreso->notas=$request->notas;
            $ingreso->tipo=$request->tipo;
            $ingreso->fecha=Carbon::parse($request->fecha)->format('Y-m-d');
            $ingreso->observaciones=mb_strtolower($request->observaciones);
            $ingreso->updated_by = Auth::user()->id;
            $ingreso->forma_pago = $request->forma_pago;
            $ingreso->save();

            //Si el tipo de ingreso es de facturas de venta
            if ($ingreso->tipo==1) {
                foreach ($request->factura_pendiente as $key => $value) {
                    $factura = Factura::find($request->factura_pendiente[$key]);
                    $items = IngresosFactura::where('ingreso',$ingreso->id)->where('factura', $factura->id)->first();
                    $porpagar=$factura->porpagar();
                    if ($request->precio[$key]) {
                        if (!$items) {
                            $items = new IngresosFactura;
                            $items->factura=$request->factura_pendiente[$key];
                            $items->pagado=$factura->pagado();
                            $items->ingreso=$ingreso->id;
                        }else{
                            $porpagar+=$this->precision($items->pago);
                        }
                        $items->pago=$this->precision($request->precio[$key]);
                        $items->save();
                        $precio=$this->precision($request->precio[$key]);
                        $retencion='fact'.$factura->id.'_retencion';
                        $precio_reten='fact'.$factura->id.'_precio_reten';
                        $cont=0; $fact=0;
                        if ($request->$retencion) {
                            $inner=array();
                            foreach ($request->$retencion as $key2 => $value2) {
                                if ($request->$precio_reten[$key2]) {
                                    $retencion = Retencion::where('id', $value2)->first();
                                    $cont+=1;
                                    $id='fact'.$factura->id.'_nro_'.$cont;
                                    if ($request->$id) {
                                        $items = IngresosRetenciones::where('id', $request->$id)->first();
                                    }else{
                                        $items = new IngresosRetenciones;
                                    }
                                    $inner[]=$items->id;
                                    $items->ingreso=$ingreso->id;
                                    $items->factura=$factura->id;
                                    $items->valor=$this->precision($request->$precio_reten[$key2]);
                                    $precio+=$this->precision( $request->$precio_reten[$key2]);
                                    $items->retencion=$retencion->porcentaje;
                                    $items->id_retencion=$retencion->id;
                                    $items->save();
                                }
                            }
                            if (count($inner)>0) {
                                DB::table('ingresos_retenciones')->where('ingreso', $ingreso->id)->where('factura', $factura->id)->whereNotIn('id', $inner)->delete();
                            }
                        }else{
                            DB::table('ingresos_retenciones')->where('ingreso', $ingreso->id)->where('factura', $factura->id)->delete();
                        }
                        if ($this->precision($factura->pagado())==$this->precision($factura->total()->total)) {
                            $factura->estatus=0;
                        }else{
                            $factura->estatus=1;
                        }
                        $factura->save();
                    }else{
                        if($items){
                            $items->delete();
                            $factura->estatus=1;
                            $factura->save();
                        }
                    }
                }
            }else{ //Ingresos por categorias
                $retencionesIngreso = IngresosRetenciones::where('ingreso',$ingreso->id)->get();
                $inner=array();
                foreach ($request->categoria as $key => $value) {
                    if ($request->precio_categoria[$key]) {
                        $cat='id_cate'.($key+1);
                        if($request->$cat){
                            $items = IngresosCategoria::where('id', $request->$cat)->first();
                        }else{
                            $items = new IngresosCategoria;
                        }
                        $impuesto = Impuesto::where('id', $request->impuesto_categoria[$key])->first();
                        if (!$impuesto) {
                            $impuesto = Impuesto::where('id', 0)->first();
                        }
                        $items->valor=$request->precio_categoria[$key];
                        $items->id_impuesto=$request->impuesto_categoria[$key];
                        $items->ingreso=$ingreso->id;
                        $items->categoria=$request->categoria[$key];
                        $items->cant=$request->cant_categoria[$key];
                        $items->descripcion=$request->descripcion_categoria[$key];
                        $items->impuesto=$impuesto->porcentaje;
                        $items->save();
                        $inner[]=$items->id;
                    }
                }
                if (count($inner)>0) {
                    DB::table('ingresos_categoria')->where('ingreso', $ingreso->id)->whereNotIn('id', $inner)->delete();
                }
                $inner=array();
                if ($request->retencion) {
                    foreach ($request->retencion as $key => $value) {
                        if ($request->precio_reten[$key]) {
                            $cat='reten'.($key+1);
                            if($request->$cat){
                                $items = IngresosRetenciones::where('id', $request->$cat)->first();
                            }else{
                                $items = new IngresosRetenciones;
                            }
                            $retencion = Retencion::where('id', $request->retencion[$key])->first();
                            $items->ingreso=$ingreso->id;
                            $items->valor=$request->precio_reten[$key];
                            $items->retencion=$retencion->porcentaje;
                            $items->id_retencion=$retencion->id;
                            $items->save();
                            $inner[]=$items->id;
                        }
                    }
                    if (count($inner)>0) {
                        DB::table('ingresos_retenciones')->where('ingreso', $ingreso->id)->whereNotIn('id', $inner)->delete();
                    }
                }else{
                    DB::table('ingresos_retenciones')->where('ingreso', $ingreso->id)->delete();
                }
            }

            //registramos el saldo a favor que se generó al pagar la factura
            if($request->saldofavor > 0){
                $contacto = Contacto::find($request->cliente);
                $contacto->saldo_favor = $contacto->saldo_favor+$request->saldofavor;
                $contacto->save();

                $ingreso->puc_banco = $request->forma_pago; //cuenta de forma de pago genérico del ingreso. (en memoria)
                $ingreso->anticipo = $request->anticipo_factura; //cuenta de anticipo genérico del ingreso. (en memoria)

                $ingreso->saldoFavorIngreso = $request->saldofavor; //Variable en memoria, no creada.
                PucMovimiento::ingreso($ingreso,2,1,$request);
            }else{
                $ingreso->puc_banco = $request->forma_pago; //cuenta de forma de pago genérico del ingreso. (en memoria)
                PucMovimiento::ingreso($ingreso,2,2,$request);
            }

            //ingresos
            $this->up_transaccion(1, $ingreso->id, $ingreso->cuenta, $ingreso->cliente, 1, $ingreso->pago(), $ingreso->fecha, $ingreso->descripcion);

            $mensaje='Se ha modificado satisfactoriamente el ingreso';
            return redirect('empresa/ingresos')->with('success', $mensaje)->with('ingreso_id', $ingreso->id);
        }
        return redirect('empresa/ingresos')->with('error', 'No existe un registro con ese id');
    }

    public function Imprimir(Request $request, $id){
        view()->share(['title' => 'Imprimir Ingreso']);
        $ingreso = Ingreso::where('empresa',Auth::user()->empresa)->where('nro', $id)->first();
        if ($ingreso) {
            if ($ingreso->tipo==1) {
                $itemscount=IngresosFactura::where('ingreso',$ingreso->id)->count();
                $items = IngresosFactura::where('ingreso',$ingreso->id)->get();
            }else if ($ingreso->tipo==2){
                $itemscount=IngresosCategoria::where('ingreso',$ingreso->id)->count();
                $items = IngresosCategoria::where('ingreso',$ingreso->id)->get();
            }else{
                $itemscount=1;
                $items = Ingreso::where('empresa',Auth::user()->empresa)->where('nro', $id)->get();
            }
            $retenciones = IngresosRetenciones::where('ingreso',$ingreso->id)->get();
            $empresa = Empresa::find($ingreso->empresa);

            // Verificar si se solicita versión detallada
            $detalle = $request->query('detalle', 0);
            $vista = $detalle ? 'pdf.ingreso_detallado' : 'pdf.ingreso';

            $pdf = PDF::loadView($vista, compact('ingreso', 'items', 'retenciones', 'itemscount','empresa'));
            return  response ($pdf->stream())->withHeaders(['Content-Type' =>'application/pdf',]);
        }
    }

    public function imprimirTirilla($id, $tipo='original')
    {
        view()->share(['title' => 'Imprimir Ingreso']);
        $ingreso = Ingreso::where('empresa', Auth::user()->empresa)
                        ->where('nro', $id)
                        ->first();

        if ($ingreso) {
            if ($ingreso->tipo == 1) {
                $itemscount = IngresosFactura::where('ingreso', $ingreso->id)->count();
                $items = IngresosFactura::join('items_factura as itf', 'itf.factura', 'ingresos_factura.factura')
                                        ->select('itf.*')
                                        ->where('ingreso', $ingreso->id)
                                        ->get();
            } else if ($ingreso->tipo == 2) {
                $itemscount = IngresosCategoria::where('ingreso', $ingreso->id)->count();
                $items = IngresosCategoria::where('ingreso', $ingreso->id)->get();
            } else {
                $itemscount = 1;
                $items = Ingreso::where('empresa', Auth::user()->empresa)
                                ->where('nro', $id)
                                ->get();
            }

            $retenciones = IngresosRetenciones::where('ingreso', $ingreso->id)->get();
            $resolucion = NumeracionFactura::where('empresa', Auth::user()->empresa)
                                        ->where('num_equivalente', 0)
                                        ->where('nomina', 0)
                                        ->where('tipo', 2)
                                        ->where('preferida', 1)
                                        ->first();
            $empresa = Empresa::find($ingreso->empresa);

            // Obtener el contrato correcto desde la factura asociada al ingreso
            $contratoNro = null;
            $direccionMostrar = null; // NUEVA VARIABLE
            $saldo_inicial = 0;

            if ($ingreso->tipo == 1) {
                $primeraFactura = IngresosFactura::where('ingreso', $ingreso->id)->first();

                if ($primeraFactura) {
                    $factura = Factura::find($primeraFactura->factura);
                    // Opción 1: Buscar en la tabla facturas_contratos
                    $contratoRelacion = DB::table('facturas_contratos')
                        ->where('factura_id', $primeraFactura->factura)
                        ->first();

                    if ($contratoRelacion) {
                        $contratoNro = $contratoRelacion->contrato_nro;
                        // Obtener la dirección del contrato
                        $contratoObj = Contrato::where('nro', $contratoNro)->first();
                        if ($contratoObj) {
                            $direccionMostrar = $contratoObj->address_street ?? $contratoObj->direccion_instalacion ?? null;
                        }

                        // Cálculo de Saldo Inicial sin filtro de vencimiento: 
                        // Incluir facturas de venta (estándar y electrónicas), abiertas, con contrato y sin pagos previos.
                        if ($factura) {
                            $facturasContratoIds = DB::table('facturas_contratos')
                                ->where('contrato_nro', $contratoNro)
                                ->pluck('factura_id');

                            $sumatoria = Factura::whereIn('id', $facturasContratoIds)
                                ->where('tipo', 1) // Facturas de venta (Estándar/Electrónica)
                                ->where('estatus', 1) // Abiertas
                                ->get()
                                ->filter(function($f) {
                                    return $f->pagado() == 0; // Sin pagos previos
                                })
                                ->sum(function($f) {
                                    return $f->porpagar();
                                });
                            
                            $saldo_inicial = $sumatoria + $ingreso->pago();
                        }
                    } else {
                        // Opción 2: Buscar desde el contrato_id directo de la factura
                        if ($factura && $factura->contrato_id) {
                            $contrato = Contrato::find($factura->contrato_id);
                            if ($contrato) {
                                $contratoNro = $contrato->nro;
                                $direccionMostrar = $contrato->address_street ?? $contrato->direccion_instalacion ?? null;

                                // Cálculo de Saldo Inicial sin filtro de vencimiento (Opción 2)
                                $sumatoria = Factura::where('contrato_id', $factura->contrato_id)
                                    ->where('tipo', 1)
                                    ->where('estatus', 1)
                                    ->get()
                                    ->filter(function($f) {
                                        return $f->pagado() == 0;
                                    })
                                    ->sum(function($f) {
                                        return $f->porpagar();
                                    });
                                
                                $saldo_inicial = $sumatoria + $ingreso->pago();
                            }
                        }
                    }
                }
            }

            // Si no hay dirección del contrato, usar la del cliente como fallback
            if (!$direccionMostrar) {
                $direccionMostrar = $ingreso->cliente()->direccion;
            }

            $paper_size = [0, 0, 270, 580];

            if ($ingreso->valor_anticipo > 0) {
                $pdf = PDF::loadView('pdf.plantillas.ingreso_tirilla_anticipo', compact(
                    'ingreso', 'items', 'retenciones', 'itemscount', 'empresa', 'resolucion', 'contratoNro', 'direccionMostrar', 'saldo_inicial'
                ));
            } else {
                $pdf = PDF::loadView('pdf.plantillas.ingreso_tirilla', compact(
                    'ingreso', 'items', 'retenciones', 'itemscount', 'empresa', 'resolucion', 'contratoNro', 'direccionMostrar', 'saldo_inicial'
                ));
            }

            $pdf->setPaper($paper_size, 'portrait');
            return response($pdf->stream())->withHeaders(['Content-Type' => 'application/pdf']);
        }
    }


    public function enviar($id, $emails=null, $redireccionar=true){
        view()->share(['title' => 'Enviando Recibo de Caja']);
        $ingreso = Ingreso::where('empresa',Auth::user()->empresa)->where('nro', $id)->first();
        if ($ingreso) {
            if (!$emails) {
                $emails[]=$ingreso->cliente()->email;
                if ($ingreso->cliente()->asociados('number')>0) {
                    $email=$emails;
                    foreach ($ingreso->cliente()->asociados() as $asociado) {
                        if ($asociado->notificacion==1 && $asociado->email) {
                            $emails[]=$asociado->email;
                        }
                    }
                }
            }
            if (!$emails || count($emails)==0) {
                return redirect('empresa/ingresos/'.$ingreso->nro)->with('error', 'El Cliente ni sus contactos asociados tienen correo registrado');
            }

            if ($ingreso->tipo==1) {
                $itemscount=IngresosFactura::where('ingreso',$ingreso->id)->count();
                $items = IngresosFactura::where('ingreso',$ingreso->id)->get();
            }else{
                $itemscount=IngresosCategoria::where('ingreso',$ingreso->id)->count();
                $items = IngresosCategoria::where('ingreso',$ingreso->id)->get();
            }

            $pdf = PDF::loadView('pdf.ingreso', compact('ingreso', 'items', 'retenciones', 'itemscount'))->stream();
            $asunto = "Recibo de Caja # $ingreso->nro";

            $host = ServidorCorreo::where('estado', 1)->where('empresa', Auth::user()->empresa)->first();
            if($host){
                $existing = config('mail');
                $new =array_merge(
                    $existing, [
                        'host' => $host->servidor,
                        'port' => $host->puerto,
                        'encryption' => $host->seguridad,
                        'username' => $host->usuario,
                        'password' => $host->password,
                        'from' => [
                            'address' => $host->address,
                            'name' => $host->name
                        ],
                    ]
                );
                config(['mail'=>$new]);
            }

            self::sendMail('emails.ingreso', compact('ingreso'), compact('pdf', 'emails', 'ingreso', 'asunto'), function($message) use ($pdf, $emails, $ingreso, $asunto){
                $message->from(Auth::user()->empresa()->email, Auth::user()->empresa()->nombre);
                $message->to($emails)->subject($asunto);
                $message->attachData($pdf, 'recibo.pdf', ['mime' => 'application/pdf']);
            });
        }

        if ($redireccionar) {
            return redirect('empresa/ingresos/'.$ingreso->id)->with('success', 'Se ha enviado el correo');
        }
    }

    public function anular($id){

        $ingreso = Ingreso::where('empresa',Auth::user()->empresa)->where('nro', $id)->first();
        if ($ingreso) {
            $ingreso->updated_by = Auth::user()->id;
            if ($ingreso->tipo==3) {
                return redirect('empresa/pagos')->with('error', 'No puede editar un ingreso de nota de débito');
            }
            if ($ingreso->tipo==4) {
                return redirect('empresa/pagos')->with('error', 'No puede editar una transferencia');
            }
            if ($ingreso->estatus==1) {

                // Si el ingreso que se anula fue un anticipo, se debe restar el saldo a favor generado
                if ($ingreso->anticipo == 1 && $ingreso->valor_anticipo > 0) {
                    $contacto_saldo = Contacto::find($ingreso->cliente);
                    if($contacto_saldo){
                        $saldo_anterior = $contacto_saldo->saldo_favor;
                        $nuevo_saldo = $saldo_anterior - $ingreso->valor_anticipo;
                        $contacto_saldo->saldo_favor = $nuevo_saldo;
                        $contacto_saldo->save();

                        DB::table('log_saldos')->insert([
                            'id_contacto' => $contacto_saldo->id,
                            'accion' => 'Anulación de ingreso Nro ' . $ingreso->nro . ', resta de saldo a favor de ' . \App\Funcion::Parsear($ingreso->valor_anticipo) . ' (Saldo anterior: ' . \App\Funcion::Parsear($saldo_anterior) . ' / Actual: ' . \App\Funcion::Parsear($nuevo_saldo) . ')',
                            'created_by' => Auth::user()->id,
                            'fecha' => Carbon::now()->format('Y-m-d'),
                            'created_at' => Carbon::now(),
                        ]);
                    }
                }

                $ingreso->estatus=2;
                $mensaje='Se ha anulado satisfactoriamente el pago';

                $items = IngresosFactura::where('ingreso',$ingreso->id)->get();
                foreach ($items as $item) {
                    $factura= $item->factura();
                    $descripcion = '<i class="fas fa-check text-success"></i> <b>Cambio de Status</b> de abierto a anulado el pago nro '.$ingreso->nro.'<br>';
                    $movimiento = new MovimientoLOG();
                    $movimiento->contrato    = $factura->id;
                    $movimiento->modulo      = 8;
                    $movimiento->descripcion = $descripcion;
                    $movimiento->created_by  = Auth::user()->id;
                    $movimiento->empresa     = $factura->empresa;
                    $movimiento->save();
                }

            }else{

                // Si el ingreso que se vuelve a abrir fue un anticipo, se debe sumar nuevamente el saldo a favor
                if ($ingreso->anticipo == 1 && $ingreso->valor_anticipo > 0) {
                    $contacto_saldo = Contacto::find($ingreso->cliente);
                    if($contacto_saldo){
                        $saldo_anterior = $contacto_saldo->saldo_favor;
                        $nuevo_saldo = $saldo_anterior + $ingreso->valor_anticipo;
                        $contacto_saldo->saldo_favor = $nuevo_saldo;
                        $contacto_saldo->save();

                        DB::table('log_saldos')->insert([
                            'id_contacto' => $contacto_saldo->id,
                            'accion' => 'Apertura de ingreso Nro ' . $ingreso->nro . ', reintegro de saldo a favor de ' . \App\Funcion::Parsear($ingreso->valor_anticipo) . ' (Saldo anterior: ' . \App\Funcion::Parsear($saldo_anterior) . ' / Actual: ' . \App\Funcion::Parsear($nuevo_saldo) . ')',
                            'created_by' => Auth::user()->id,
                            'fecha' => Carbon::now()->format('Y-m-d'),
                            'created_at' => Carbon::now(),
                        ]);
                    }
                }

                if ($ingreso->tipo==1) {
                    $items = IngresosFactura::where('ingreso',$ingreso->id)->get();
                    foreach ($items as $item) {
                        $factura= $item->factura();

                        $descripcion = '<i class="fas fa-check text-success"></i> <b>Cambio de Status</b> de anulado a abierto el pago nro '.$ingreso->nro.'<br>';
                        $movimiento = new MovimientoLOG();
                        $movimiento->contrato    = $factura->id;
                        $movimiento->modulo      = 8;
                        $movimiento->descripcion = $descripcion;
                        $movimiento->created_by  = Auth::user()->id;
                        $movimiento->empresa     = $factura->empresa;
                        $movimiento->save();

                        if ($factura->porpagar()<$item->pago) {
                            return back()->with('error', 'El monto es mayor que lo que falta por pagar en la venta')->with('ingreso_id', $ingreso->id);
                        }
                    }
                }
                $ingreso->estatus=1;
                $mensaje='Se ha abierto satisfactoriamente el pago';
            }
            $ingreso->save();

            if ($ingreso->tipo==1) {
                $items=ingresosFactura::where('ingreso',$ingreso->id)->get();
                foreach ($items as $item) {
                    $factura= $item->factura();
                    if ($this->precision($factura->porpagar())<=0) {
                        $factura->estatus=0;
                    }else{
                        $factura->estatus=1;
                    }
                    $factura->save();
                }
            }

            $this->chage_status_transaccion(1, $ingreso->id, $ingreso->estatus);
            return back()->with('success', $mensaje)->with('ingreso_id', $ingreso->id);
        }
        return back()->with('error', 'No existe un registro con ese id');
    }


    public function destroy($id)
    {
        $ingreso = Ingreso::Find($id);
        if ($ingreso) {

            // Si el ingreso que se elimina fue un anticipo y no estaba anulado previamente, se debe restar el saldo a favor generado
            if ($ingreso->estatus != 2 && $ingreso->anticipo == 1 && $ingreso->valor_anticipo > 0) {
                $contacto_saldo = Contacto::find($ingreso->cliente);
                if($contacto_saldo){
                    $saldo_anterior = $contacto_saldo->saldo_favor;
                    $nuevo_saldo = $saldo_anterior - $ingreso->valor_anticipo;
                    $contacto_saldo->saldo_favor = $nuevo_saldo;
                    $contacto_saldo->save();

                    DB::table('log_saldos')->insert([
                        'id_contacto' => $contacto_saldo->id,
                        'accion' => 'Eliminación de ingreso Nro ' . $ingreso->nro . ', resta de saldo a favor de ' . \App\Funcion::Parsear($ingreso->valor_anticipo) . ' (Saldo anterior: ' . \App\Funcion::Parsear($saldo_anterior) . ' / Actual: ' . \App\Funcion::Parsear($nuevo_saldo) . ')',
                        'created_by' => Auth::user()->id,
                        'fecha' => Carbon::now()->format('Y-m-d'),
                        'created_at' => Carbon::now(),
                    ]);
                }
            }

            if ($ingreso->tipo == 3) {
                return redirect('empresa/pagos')->with('error', 'No puede editar un pago de nota de débito');
            } else if ($ingreso->tipo == 1) {

                if ($ingreso->estatus != 2) {
                    $ids = DB::table('ingresos_factura')->where('ingreso', $ingreso->id)->select('factura', 'pago')->get();
                    $factura = array();
                    foreach ($ids as $id) {
                        $factura[] = $id->factura;
                    }
                    DB::table('factura')->where('empresa', Auth::user()->empresa)->whereIn('id', $factura)->update(['estatus' => 1]);
                }

                $items = IngresosFactura::where('ingreso',$ingreso->id)->get();
                foreach ($items as $item) {
                    $factura= $item->factura();
                    $descripcion = '<i class="fas fa-check text-success"></i> <b>Eliminación de pago</b> nro '.$ingreso->nro.'<br>';
                    $movimiento = new MovimientoLOG();
                    $movimiento->contrato    = $factura->id;
                    $movimiento->modulo      = 8;
                    $movimiento->descripcion = $descripcion;
                    $movimiento->created_by  = Auth::user()->id;
                    $movimiento->empresa     = $factura->empresa;
                    $movimiento->save();
                }

                    IngresosFactura::where('ingreso', $ingreso->id)->delete();
                    $this->destroy_transaccion(1, $ingreso->id);

            } else if ($ingreso->tipo == 2) {

                IngresosCategoria::where('ingreso', $ingreso->id)->delete();
                //ingresos
                $this->destroy_transaccion(1, $ingreso->id);

            } else if ($ingreso->tipo == 4) {
                IngresosCategoria::where('ingreso', $ingreso->id)->delete();
                $mov1 = Movimiento::where('modulo', 1)->where('id_modulo', $ingreso->id)->first();
                if ($mov1) {
                    $gasto = Gastos::where('id', $mov1->id_modulo)->first();
                    if ($gasto) {
                        GastosCategoria::where('gasto', $gasto->id)->delete();
                        $gasto->delete();
                    }
                    Movimiento::where('transferencia', $mov1->id)->delete();
                    $mov1->delete();
                }
            }

            DB::table('ingresos_retenciones')->where('ingreso', $ingreso->id)->delete();
            $ingreso->delete();

            $mensaje = 'Se ha eliminado satisfactoriamente el ingreso';
            return redirect('empresa/ingresos')->with('success', $mensaje);
            // return back()->with('success', $mensaje);
        }

        return redirect('empresa/ingresos')->with('error', 'No existe un registro con ese id');
    }

    public function efecty(){
        $this->getAllPermissions(Auth::user()->id);
        view()->share(['title' => 'Carga de Archivos Efecty', 'icon' => 'fas fa-cloud-upload-alt']);

        return view('ingresos.efecty');
    }

    public function efecty_xlsx(){
        $this->getAllPermissions(Auth::user()->id);
        view()->share(['title' => 'Carga de Archivos Efecty XLSX', 'icon' => 'fas fa-cloud-upload-alt']);

        return view('ingresos.efecty_xlsx');
    }

    public function efecty_store(Request $request){
        $this->getAllPermissions(Auth::user()->id);
        $request->validate([
            'archivo_efecty' => 'required'
        ]);

        if($request->archivo_efecty){
            $registros = [];
            $mensaje = '';
            $gestor = fopen($request->archivo_efecty, "r"); # Modo r, read
            if (!$gestor) {
                return back()->with('danger','ERROR: ALGO HA FALLADO EN LA CARGA DEL ARCHIVO, INTENTE NUEVAMENTE');
            }
            $tamanio_bufer = 400; # bytes
            while (($lectura = fgets($gestor, $tamanio_bufer)) != false) {
                $lectura = explode("|", $lectura);
                if($lectura[0] == '01' || $lectura[0] == '03'){}else{
                    array_push($registros, $lectura);
                }
            }
            if (!feof($gestor)) {
                return back()->with('danger','ERROR: ALGO HA FALLADO EN LA APERTURA Y LECTURA DEL ARCHIVO, INTENTE NUEVAMENTE');
            }
            fclose($gestor);

            foreach ($registros as $registro) {

                if(!isset(explode(',', $registro[0])[4])){
                    return back()->with('danger','ERROR: Estructura no válida.');
                }

                $nit = explode(',', $registro[0])[4];
                $precio = $this->precision(explode(',', $registro[0])[6]);
                $cliente = Contacto::where('nit',$nit)->first();

                if(!$cliente){
                    $mensaje .= 'CLIENTE CON CC '.$nit.' NO EXISTE<br>';
                    continue;
                }

                $fecha = explode(',', $registro[0])[0];
                $fecha = Carbon::createFromFormat('d/m/Y', $fecha)->format('Y-m-d');

                $factura = Factura::where('factura.cliente', $cliente->id)
                ->orderBy('id', 'desc')->first();

                if($factura){
                    if($factura->estatus == 0){
                        $mensaje .= 'FACTURA N° '.$factura->codigo.' YA SE ENCUENTRA PAGADA<br>';
                    }elseif($factura->estatus == 1){
                        $nro = Numeracion::where('empresa', Auth::user()->empresa)->first();
                        $caja = $nro->caja;

                        while (true) {
                            $numero = Ingreso::where('empresa', Auth::user()->empresa)->where('nro', $caja)->count();
                            if ($numero == 0) {
                                break;
                            }
                            $caja++;
                        }

                        $banco = Banco::where('empresa',Auth::user()->empresa)->where('nombre', 'EFECTY')->first();

                        $ingreso              = new Ingreso;
                        $ingreso->nro         = $caja;
                        $ingreso->empresa     = Auth::user()->empresa;
                        $ingreso->cliente     = $factura->cliente;
                        $ingreso->cuenta      = $banco->id;
                        $ingreso->metodo_pago = 1;
                        $ingreso->notas       = 'Pago Realizado por Carga de Archivo';
                        $ingreso->tipo        = 1;
                        $ingreso->fecha       = $fecha;
                        $ingreso->created_by  = Auth::user()->id;
                        $ingreso->save();

                        $items                = new IngresosFactura;
                        $items->ingreso       = $ingreso->id;
                        $items->factura       = $factura->id;
                        $items->pagado        = $factura->pagado();
                        $items->pago          = $precio;
                        $items->save();

                        if ($precio >= $factura->porpagar()) {
                            $factura->estatus = 0;
                            $factura->save();
                            CRM::where('cliente', $factura->cliente)->whereIn('estado', [0,2,3,6])->delete();
                            $crms = CRM::where('cliente', $factura->cliente)->whereIn('estado', [0,2,3,6])->get();
                            foreach ($crms as $crm) {
                                $crm->delete();
                            }
                        }

                        ##SUMO A LAS NUMERACIONES EL RECIBO
                        $nro->caja = $caja + 1;
                        $nro->save();

                        ##REGISTRO EL MOVIMIENTO
                        //$ingreso = Ingreso::find($ingreso->id);
                        $this->up_transaccion(1, $ingreso->id, $ingreso->cuenta, $ingreso->cliente, 1, $ingreso->pago(), $ingreso->fecha, $ingreso->descripcion);

                        if ($factura->estatus == 0) {
                            $cliente = Contacto::where('id', $factura->cliente)->first();
                            $contrato = Contrato::where('client_id', $cliente->id)->first();
                            $res = DB::table('contracts')->where('client_id',$cliente->id)->update(["state" => 'enabled']);

                            //LA EJECUCION DE FUNCIONES MIKROTIK SE DEJARA EN EL CRONJOB REFRESHCORTEINTERNETTV

                            /* * * ENVÍO SMS * * */
                            $servicio = Integracion::where('empresa', Auth::user()->empresa)->where('tipo', 'SMS')->where('status', 1)->first();
                            if($servicio){
                                $numero = str_replace('+','',$cliente->celular);
                                $numero = str_replace(' ','',$numero);
                                $mensaje = "Estimado Cliente, le informamos que hemos recibido el pago de su factura por valor de ".$factura->parsear($precio)." gracias por preferirnos. ".Auth::user()->empresa()->slogan;
                                if($servicio->nombre == 'Hablame SMS'){
                                    if($servicio->api_key && $servicio->user && $servicio->pass){
                                        $post['toNumber'] = $numero;
                                        $post['sms'] = $mensaje;

                                        $curl = curl_init();
                                        curl_setopt_array($curl, array(
                                            CURLOPT_URL => 'https://api103.hablame.co/api/sms/v3/send/marketing',
                                            CURLOPT_RETURNTRANSFER => true,
                                            CURLOPT_ENCODING => '',
                                            CURLOPT_MAXREDIRS => 10,
                                            CURLOPT_TIMEOUT => 0,
                                            CURLOPT_FOLLOWLOCATION => true,
                                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                            CURLOPT_CUSTOMREQUEST => 'POST',CURLOPT_POSTFIELDS => json_encode($post),
                                            CURLOPT_HTTPHEADER => array(
                                                'account: '.$servicio->user,
                                                'apiKey: '.$servicio->api_key,
                                                'token: '.$servicio->pass,
                                                'Content-Type: application/json'
                                            ),
                                        ));
                                        $result = curl_exec ($curl);
                                        $err  = curl_error($curl);
                                        curl_close($curl);
                                    }
                                }elseif($servicio->nombre == 'SmsEasySms'){
                                    if($servicio->user && $servicio->pass){
                                        $post['to'] = array('57'.$numero);
                                        $post['text'] = $mensaje;
                                        $post['from'] = "SMS";
                                        $login = $servicio->user;
                                        $password = $servicio->pass;

                                        $ch = curl_init();
                                        curl_setopt($ch, CURLOPT_URL, "https://sms.istsas.com/Api/rest/message");
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                        curl_setopt($ch, CURLOPT_POST, 1);
                                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
                                        curl_setopt($ch, CURLOPT_HTTPHEADER,
                                            array(
                                                "Accept: application/json",
                                                "Authorization: Basic ".base64_encode($login.":".$password)));
                                        $result = curl_exec ($ch);
                                        $err  = curl_error($ch);
                                        curl_close($ch);
                                    }
                                }else{
                                    if($servicio->user && $servicio->pass){
                                        $post['to'] = array('57'.$numero);
                                        $post['text'] = $mensaje;
                                        $post['from'] = "";
                                        $login = $servicio->user;
                                        $password = $servicio->pass;

                                        $ch = curl_init();
                                        curl_setopt($ch, CURLOPT_URL, "https://masivos.colombiared.com.co/Api/rest/message");
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                        curl_setopt($ch, CURLOPT_POST, 1);
                                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
                                        curl_setopt($ch, CURLOPT_HTTPHEADER,
                                            array(
                                                "Accept: application/json",
                                                "Authorization: Basic ".base64_encode($login.":".$password)));
                                        $result = curl_exec ($ch);
                                        $err  = curl_error($ch);
                                        curl_close($ch);
                                    }
                                }
                            }
                            /* * * ENVÍO SMS * * */

                            $mensaje .= 'La factura '.$factura->codigo.' del cliente '.$cliente->nombre.' '.$cliente->apellidos().' ha sido pagada bajo el ingreso Nro.'.$ingreso->nro.' por '.Auth::user()->empresa()->moneda.''.Funcion::Parsear($precio).'<br>';
                        }
                    }
                }else{
                    $mensaje .= 'FACTURA NO ENCONTRADA PARA EL CLIENTE CON NIT ' .$nit. '<br>';
                }
            }
            return back()->with('success', $mensaje);
        }else{
            return back()->with('danger','ERROR: EL ARCHIVO NO HA PODIDO SER CARGADO A LA PLATAFORMA, INTENTE NUEVAMENTE');
        }
    }

    //metodo que calcula que recibos de caja tiene un anticipo para poder cruzar en una forma de pago.
    public function recibosAnticipo(Request $request){

        //obtenemos los ingresos que tiene un anticpo vigente.
        if(!isset($request->recibo) || $request->recibo == 0){
            $ingresos = Ingreso::where('cliente',$request->cliente)
            ->where('anticipo',1)
            ->where('estatus','!=',2)
            ->where('valor_anticipo','>',0)
            ->get();
        }else{
            $ingresos = [];
        }


        return response()->json($ingresos);
    }

    public function efecty_store_xlsx(Request $request){
        $request->validate([
            'archivo_efecty' => 'required|mimes:xlsx',
        ],[
            'archivo_efecty.mimes' => 'El archivo debe ser de extensión xlsx'
        ]);

        $create=0;
        $modf=0;
        $mensaje = '';
        $imagen = $request->file('archivo_efecty');
        $nombre_imagen = 'archivo_efecty.'.$imagen->getClientOriginalExtension();
        $path = public_path() .'/images/Empresas/Empresa'.Auth::user()->empresa;
        $imagen->move($path,$nombre_imagen);
        Ini_set ('max_execution_time', 500);
        $fileWithPath=$path."/".$nombre_imagen;
        //Identificando el tipo de archivo
        $inputFileType = PHPExcel_IOFactory::identify($fileWithPath);
        //Creando el lector.
        $objReader = PHPExcel_IOFactory::createReader($inputFileType);
        //Cargando al lector de excel el archivo, le pasamos la ubicacion
        $objPHPExcel = $objReader->load($fileWithPath);
        //obtengo la hoja 0
        $sheet = $objPHPExcel->getSheet(0);
        //obtiene el tamaño de filas
        $highestRow = $sheet->getHighestRow();
        //obtiene el tamaño de columnas
        $highestColumn = $sheet->getHighestColumn();

        for ($row = 2; $row <= $highestRow; $row++){
            $request= (object) array();
            //obtengo el A4 desde donde empieza la data
            $nit=$sheet->getCell("B".$row)->getValue();
            if (empty($nit)) {
                break;
            }

            $request->monto=$sheet->getCell("C".$row)->getValue();
            $request->fecha=$sheet->getCell("D".$row)->getValue();
            $error=(object) array();

            if (!$request->monto) {
                $error->monto="EL CAMPO MONTO ES OBLIGATORIO";
            }
            if (!$request->fecha) {
                $error->monto="EL CAMPO FECHA ES OBLIGATORIO";
            }

            if (count((array) $error)>0) {
                $fila["error"]='FILA '.$row;
                $error=(array) $error;
                var_dump($error);
                var_dump($fila);

                array_unshift ( $error ,$fila);
                $result=(object) $error;
                //reenvia los errores
                return back()->withErrors($result)->withInput();
            }
        }

        for ($row = 2; $row <= $highestRow; $row++){
            $request        = (object) array();
            $request->nit   = $sheet->getCell("B".$row)->getValue();
            $request->monto = $sheet->getCell("C".$row)->getValue() / 10000;
            $request->fecha = date('Y-m-d');

            $cliente = Contacto::where('nit', $request->nit)->where('status', 1)->first();
            if($cliente){
                $factura = Factura::where('cliente',$cliente->id)->where('empresa',Auth::user()->empresa)->where('estatus', 1)->get()->last();

                if($factura){
                    $nro = Numeracion::where('empresa', Auth::user()->empresa)->first();
                    $caja = $nro->caja;

                    while (true) {
                        $numero = Ingreso::where('empresa', Auth::user()->empresa)->where('nro', $caja)->count();
                        if ($numero == 0) {
                            break;
                        }
                        $caja++;
                    }

                    $banco = Banco::where('empresa',Auth::user()->empresa)->where('nombre', 'EFECTY')->first();

                    $ingreso              = new Ingreso;
                    $ingreso->nro         = $caja;
                    $ingreso->empresa     = Auth::user()->empresa;
                    $ingreso->cliente     = $factura->cliente;
                    $ingreso->cuenta      = $banco->id;
                    $ingreso->metodo_pago = 1;
                    $ingreso->notas       = 'Pago Realizado por Carga de Archivo';
                    $ingreso->tipo        = 1;
                    $ingreso->fecha       = $request->fecha;
                    $ingreso->created_by  = Auth::user()->id;
                    $ingreso->save();

                    $precio               = $this->precision($request->monto);
                    $items                = new IngresosFactura;
                    $items->ingreso       = $ingreso->id;
                    $items->factura       = $factura->id;
                    $items->pagado        = $factura->pagado();
                    $items->pago          = $this->precision($request->monto);

                    if ($this->precision($request->monto) == $this->precision($factura->porpagar())) {
                        $factura->estatus = 0;
                        $factura->save();
                    }
                    $items->save();

                    ##SUMO A LAS NUMERACIONES EL RECIBO
                    $nro->caja = $caja + 1;
                    $nro->save();

                    ##REGISTRO EL MOVIMIENTO
                    $ingreso = Ingreso::find($ingreso->id);
                    $this->up_transaccion(1, $ingreso->id, $ingreso->cuenta, $ingreso->cliente, 1, $ingreso->pago(), $ingreso->fecha, $ingreso->descripcion);
                    $create++;
                }
            }
        }

        if ($create>0) {
            $mensaje = 'SE HA COMPLETADO EXITOSAMENTE LA CARGA DE DATOS AL SISTEMA - FACTURAS PAGADAS '.$create;
            $style   = 'success';
        }else{
            $mensaje = 'SE HA COMPLETADO EXITOSAMENTE LA CARGA DE DATOS AL SISTEMA PERO NO HAY FACTURAS POR PAGAR';
            $style   = 'danger';
        }
        return back()->with($style, $mensaje);
    }

    // ==================== IMPORTAR PAGOS / INGRESOS ====================

    public function importar()
    {
        $this->getAllPermissions(Auth::user()->id);
        view()->share(['title' => 'Importar Pagos a Facturas', 'full' => true]);
        
        $bancos = Banco::where('empresa', Auth::user()->empresa)->where('estatus', 1)->where('lectura', 0)->get();
        $metodos = DB::table('metodos_pago')->where('id', '!=', 8)->where('id', '!=', 7)->get();
        $formas = FormaPago::where('relacion',1)->orWhere('relacion',3)->get();
        
        return view('ingresos.importar')->with(compact('bancos', 'metodos', 'formas'));
    }

    public function ejemploImportar()
    {
        $titulosColumnas = array(
            'Identificacion', 'codigo factura', 'monto a pagar', 'cuenta', 'metodo de pago', 'fecha', 'observaciones', 'forma de pago'
        );

        $comentarios = array(
            'A' => 'Nit o Identificación del cliente asociado a la factura. Obligatorio.',
            'B' => 'Código de la factura a pagar. Obligatorio.',
            'C' => 'Monto a pagar sin puntos ni comas. Obligatorio.',
            'D' => 'Nombre de la cuenta destino tal como aparece en el sistema. Obligatorio.',
            'E' => 'Nombre del método de pago tal cual está en el sistema (ej: Efectivo, Consignacion). Obligatorio.',
            'F' => 'Fecha del pago en formato YYYY-MM-DD. Obligatorio.',
            'G' => 'Observaciones del pago. Opcional.',
            'H' => 'Código PUC de la forma de pago, debe ser uno de los mostrados en la vista. Obligatorio.',
        );

        $objPHPExcel = new \PHPExcel();
        $tituloReporte = "Archivo de Importación de Pagos - " . Auth::user()->empresa()->nombre;

        $letras = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H');
        $ultimaColumna = $letras[count($titulosColumnas) - 1];

        $objPHPExcel->getProperties()->setCreator("Sistema")
            ->setLastModifiedBy("Sistema")
            ->setTitle("Importación Pagos")
            ->setSubject("Importación Pagos")
            ->setDescription("Importación Pagos")
            ->setKeywords("Importación Pagos")
            ->setCategory("Importación");

        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('A1:' . $ultimaColumna . '1');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $tituloReporte);
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('A2:' . $ultimaColumna . '2');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A2', 'Fecha ' . date('d-m-Y'));

        $estilo = array(
            'font'  => array('bold'  => true, 'size'  => 12, 'name'  => 'Times New Roman'),
            'alignment' => array('horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER)
        );
        $objPHPExcel->getActiveSheet()->getStyle('A1:' . $ultimaColumna . '3')->applyFromArray($estilo);

        $estilo = array(
            'fill' => array(
                'type' => \PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('rgb' => substr(Auth::user()->empresa()->color, 1))
            ),
            'font'  => array('bold'  => true, 'size'  => 12, 'name'  => 'Times New Roman', 'color' => array('rgb' => 'FFFFFF')),
            'alignment' => array('horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER)
        );
        $objPHPExcel->getActiveSheet()->getStyle('A3:' . $ultimaColumna . '3')->applyFromArray($estilo);

        for ($i = 0; $i < count($titulosColumnas); $i++) {
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($letras[$i] . '3', utf8_decode($titulosColumnas[$i]));
        }

        foreach ($comentarios as $columna => $texto) {
            $objPHPExcel->getActiveSheet()->getComment($columna . '3')->setAuthor('Integra Colombia')->getText()->createTextRun($texto);
        }

        $estilo = array(
            'font'  => array('size'  => 12, 'name'  => 'Times New Roman'),
            'borders' => array('allborders' => array('style' => \PHPExcel_Style_Border::BORDER_THIN)),
            'alignment' => array('horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER)
        );
        $objPHPExcel->getActiveSheet()->getStyle('A3:' . $ultimaColumna . '3')->applyFromArray($estilo);

        for ($i = 'A'; $i <= $ultimaColumna; $i++) {
            $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension($i)->setAutoSize(TRUE);
        }

        // Obtener datos para validaciones
        $bancos = Banco::where('empresa', Auth::user()->empresa)->where('estatus', 1)->where('lectura', 0)->get()->pluck('nombre')->toArray();
        $metodos = DB::table('metodos_pago')->where('id', '!=', 8)->where('id', '!=', 7)->get()->pluck('metodo')->toArray();
        $formas = FormaPago::where('relacion',1)->orWhere('relacion',3)->get()->pluck('codigo')->toArray();

        $bancos_str = '"' . implode(',', $bancos) . '"';
        $metodos_str = '"' . implode(',', $metodos) . '"';
        $formas_str = '"' . implode(',', $formas) . '"';

        // Agregar validaciones de lista desplegable
        for ($row = 4; $row <= 100; $row++) {
            // Cuenta (D)
            if (strlen($bancos_str) < 255) {
                $validationD = $objPHPExcel->getActiveSheet()->getCell('D' . $row)->getDataValidation();
                $validationD->setType(\PHPExcel_Cell_DataValidation::TYPE_LIST);
                $validationD->setAllowBlank(false);
                $validationD->setShowDropDown(true);
                $validationD->setFormula1($bancos_str);
            }

            // Metodo de pago (E)
            if (strlen($metodos_str) < 255) {
                $validationE = $objPHPExcel->getActiveSheet()->getCell('E' . $row)->getDataValidation();
                $validationE->setType(\PHPExcel_Cell_DataValidation::TYPE_LIST);
                $validationE->setAllowBlank(false);
                $validationE->setShowDropDown(true);
                $validationE->setFormula1($metodos_str);
            }

            // Forma de pago (H)
            if (strlen($formas_str) < 255) {
                $validationH = $objPHPExcel->getActiveSheet()->getCell('H' . $row)->getDataValidation();
                $validationH->setType(\PHPExcel_Cell_DataValidation::TYPE_LIST);
                $validationH->setAllowBlank(false);
                $validationH->setShowDropDown(true);
                $validationH->setFormula1($formas_str);
            }
        }

        $objPHPExcel->getActiveSheet()->setTitle('Pagos');
        $objPHPExcel->setActiveSheetIndex(0);
        
        // Obtener facturas abiertas para pre-llenar la plantilla
        $facturasAbiertas = Factura::where('factura.empresa', Auth::user()->empresa)
            ->where('factura.estatus', 1)
            ->join('contactos', 'contactos.id', '=', 'factura.cliente')
            ->select('factura.codigo', 'contactos.nit', 'factura.id')
            ->get();
            
        $filaIni = 4;
        foreach ($facturasAbiertas as $facturaItem) {
            // Obtenemos el objeto factura real para usar sus métodos ya definidos
            $facturaReal = Factura::where('id', $facturaItem->id)->first();
            $porPagar = $facturaReal ? $facturaReal->porpagar() : 0;

            $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue("A" . $filaIni, $facturaItem->nit)
                ->setCellValue("B" . $filaIni, $facturaItem->codigo)
                ->setCellValue("C" . $filaIni, $porPagar);
            $filaIni++;
        }
        
        $objPHPExcel->getActiveSheet(0)->freezePane('A4');

        header("Pragma: no-cache");
        header('Content-type: application/vnd.ms-excel');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="Plantilla_Importacion_Pagos.xlsx"');
        header('Cache-Control: max-age=0');
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');
        exit;
    }

    public function importarCargando(Request $request)
    {
        $request->validate([
            'archivo' => 'required|mimes:xlsx',
        ], [
            'archivo.mimes' => 'El archivo debe ser de extensión xlsx'
        ]);

        $create = 0;
        $errores = [];
        $imagen = $request->file('archivo');
        $nombre_imagen = 'archivo_pagos.' . $imagen->getClientOriginalExtension();
        $path = public_path() . '/images/Empresas/Empresa' . Auth::user()->empresa;
        $imagen->move($path, $nombre_imagen);
        Ini_set('max_execution_time', 500);
        $fileWithPath = $path . "/" . $nombre_imagen;

        $inputFileType = \PHPExcel_IOFactory::identify($fileWithPath);
        $objReader = \PHPExcel_IOFactory::createReader($inputFileType);
        $objPHPExcel = $objReader->load($fileWithPath);
        $sheet = $objPHPExcel->getSheet(0);
        $highestRow = $sheet->getHighestRow();

        for ($row = 4; $row <= $highestRow; $row++) {
            $identificacion   = trim($sheet->getCell("A" . $row)->getValue());
            if (empty($identificacion)) {
                break;
            }

            $codigo_factura   = trim($sheet->getCell("B" . $row)->getValue());
            $monto            = trim($sheet->getCell("C" . $row)->getValue());
            $cuenta_nombre    = trim($sheet->getCell("D" . $row)->getValue());
            $metodo_nombre    = trim($sheet->getCell("E" . $row)->getValue());
            
            $fecha_excel      = $sheet->getCell("F" . $row)->getValue();
            if(\PHPExcel_Shared_Date::isDateTime($sheet->getCell("F" . $row))) {
                $fecha = date('Y-m-d', \PHPExcel_Shared_Date::ExcelToPHP($fecha_excel));
            } else {
                $fecha = trim($fecha_excel);
            }

            $observaciones    = trim($sheet->getCell("G" . $row)->getValue());
            $forma_pago_cod   = trim($sheet->getCell("H" . $row)->getValue());

            // Validaciones básicas de campos vacíos
            if (empty($identificacion) || empty($codigo_factura) || empty($monto) || empty($cuenta_nombre) ||
                empty($metodo_nombre) || empty($fecha)) {
                $errores[] = "Fila $row: Faltan campos obligatorios. Verifique identificacion, código factura, monto, cuenta, método, fecha y forma de pago.";
                continue;
            }

            // Validar existencia de cliente
            $cliente = Contacto::where('empresa', Auth::user()->empresa)
                ->where('nit', $identificacion)
                ->first();
                
            if (!$cliente) {
                $errores[] = "Fila $row: Cliente con Identificación '<b>$identificacion</b>' no encontrado en el sistema.";
                continue;
            }

            // Validar existencia de factura asociada a ese cliente
            $factura = Factura::where('empresa', Auth::user()->empresa)
                ->where('cliente', $cliente->id)
                ->where('codigo', $codigo_factura)
                ->first();
                
            if (!$factura) {
                $errores[] = "Fila $row: Factura '<b>$codigo_factura</b>' no encontrada o no pertenece al cliente con identificación '<b>$identificacion</b>'.";
                continue;
            }
            
            if ($factura->estatus == 0) {
                $errores[] = "Fila $row: Factura '<b>$codigo_factura</b>' ya se encuentra PAGADA en su totalidad.";
                continue;
            }

            // Validar Cuenta (banco)
            $cuenta = Banco::where('empresa', Auth::user()->empresa)
                ->where('estatus', 1)
                ->where('lectura', 0)
                ->where('nombre', $cuenta_nombre)
                ->first();
            if (!$cuenta) {
                $errores[] = "Fila $row: Cuenta '<b>$cuenta_nombre</b>' no encontrada o inactiva.";
                continue;
            }

            // Validar Metodo de Pago
            $metodo = DB::table('metodos_pago')
                ->where('metodo', $metodo_nombre)
                ->where('id', '!=', 8)
                ->where('id', '!=', 7)
                ->first();
            if (!$metodo) {
                $errores[] = "Fila $row: Método de pago '<b>$metodo_nombre</b>' no encontrado.";
                continue;
            }

            // Validar Forma de Pago (Puc)
            $forma_pago = FormaPago::where('codigo', $forma_pago_cod)
                 ->where(function($q) {
                     $q->where('relacion',1)->orWhere('relacion',3);
                 })->first();
            if (!$forma_pago) {
                $forma_pago = false;
                // $errores[] = "Fila $row: Forma de pago con código PUC '<b>$forma_pago_cod</b>' no encontrada o no válida para ingresos.";
                // continue;
            }

            // Validaciones adicionales para evitar pago duplicado
            $monto_precision = floatval(str_replace(',','',$monto));
            $sumaPagos = round(IngresosFactura::join('ingresos as i','i.id','ingresos_factura.ingreso')
                ->where('factura',$factura->id)
                ->where('i.estatus',1)
                ->sum('pago')
            );
            $totalFact = $factura->total()->total;
            if ($sumaPagos >= $totalFact) {
                // actualizar si era necesario
                $factura->estatus = 0;
                $factura->save();
                
                $errores[] = "Fila $row: La factura '<b>$codigo_factura</b>' ya tiene el total pagado.";
                continue;
            }

            // ---------- INICIO DE CREACIÓN DE PAGO ----------
            // Obtener número consecutivo para ingreso
            $nro = Numeracion::where('empresa', Auth::user()->empresa)->first();
            $caja = $nro->caja;
            while (true) {
                $numero_ingreso = Ingreso::where('empresa', Auth::user()->empresa)->where('nro', $caja)->count();
                if ($numero_ingreso == 0) {
                    break;
                }
                $caja++;
            }

            $ingreso              = new Ingreso;
            $ingreso->nro         = $caja;
            $ingreso->empresa     = Auth::user()->empresa;
            $ingreso->cliente     = $cliente->id;
            $ingreso->cuenta      = $cuenta->id;
            $ingreso->metodo_pago = $metodo->id;
            $ingreso->tipo        = 1;
            $ingreso->fecha       = Carbon::parse($fecha)->format('Y-m-d');
            $ingreso->observaciones = $observaciones;
            $ingreso->created_by  = Auth::user()->id;
            $ingreso->forma_pago  = $forma_pago ? $forma_pago->id : null; 
            $ingreso->puc_banco   = $forma_pago ? $forma_pago->id : null; // En memoria para PucMovimiento
            $ingreso->save();

            // Sumar a numeracion
            $nro->caja = $caja + 1;
            $nro->save();

            // Registro MovimientoLOG
            $movimiento = new MovimientoLOG();
            $movimiento->contrato    = $factura->id;
            $movimiento->modulo      = 8;
            $movimiento->descripcion = 'Se creo un ingreso de factura con el recibo de caja nro ' . $ingreso->nro . ' por un total de $' . number_format($monto_precision, 0, ',', '.') . ' (Importación)';
            $movimiento->created_by  = Auth::user()->id;
            $movimiento->empresa     = $factura->empresa;
            $movimiento->save();

            // Guardar factura a ingreso
            $items          = new IngresosFactura;
            $items->ingreso = $ingreso->id;
            $items->factura = $factura->id;
            $items->pagado  = $monto_precision; // El precio de la bd que se envia por request en web
            $items->puc_factura = $factura->cuenta_id;
            $items->puc_banco   = $forma_pago ? $forma_pago->id : null;

            // Validacion si el pago excede el total
            if($totalFact <= ($monto_precision + $sumaPagos)) {
                $items->pago = $totalFact - $sumaPagos;
                $factura->estatus = 0;
                $factura->save();
            } else {
                $items->pago = $monto_precision;
            }

            if ($this->precision($monto_precision) == $this->precision($factura->porpagar())) {
                $factura->estatus = 0;
                $factura->save();

                CRM::where('cliente', $factura->cliente)->whereIn('estado', [0,2,3,6])->delete();
            }

            $items->save();

            // Registrar movimiento contable
            // Simulamos $requestObj para el movimiento
            $requestObj = new \Illuminate\Http\Request();
            // Requerido si se ejecuta PucMovimiento::ingreso
            PucMovimiento::ingreso($ingreso, 1, 2, $requestObj);

            // Modificar el estado del contrato
            $contrato = Contrato::join('facturas_contratos as fc', 'fc.contrato_nro', '=', 'contracts.nro')
                ->where('fc.factura_id', $factura->id)
                ->select('contracts.*')
                ->first();

            if (!$contrato) {
                $contrato = Contrato::where('id', $factura->contrato_id)->first();
            }

            if ($contrato) {
                $contrato->state = "enabled";
                $contrato->save();
            }
            if (!$contrato) {
                $contrato = Contrato::where('client_id', $cliente->id)->first();
                if ($contrato) {
                    $contrato->state = "enabled";
                    $contrato->save();
                }
            }

            $create++;
        }

        // Limpiar archivo temporal
        if (file_exists($fileWithPath)) {
            unlink($fileWithPath);
        }

        if (count($errores) > 0) {
            return redirect()->route('ingresos.importar')
                ->withErrors($errores)
                ->with('success', "Se importaron $create pagos correctamente.");
        }

        return redirect('empresa/ingresos')
            ->with('success', "Se importaron $create pagos a facturas correctamente.");
    }

    /**
     * Preview de la próxima factura que se creará
     * Retorna información de las facturas que se generarán basándose en los pagos asociados
     */
    public function previewNextInvoice(Request $request)
    {
        try {
            $facturasConPago = [];
            $facturasSinContrato = [];
            $facturasPreview = [];

            // Recopilar facturas con pagos
            if ($request->has('factura_pendiente') && $request->has('precio')) {
                foreach ($request->factura_pendiente as $key => $facturaId) {
                    $monto = isset($request->precio[$key]) ? floatval($request->precio[$key]) : 0;
                    if ($monto > 0) {
                        $factura = Factura::find($facturaId);
                        if ($factura) {
                            // Verificar si tiene contrato asociado
                            $facturaContrato = DB::table('facturas_contratos')
                                ->where('factura_id', $facturaId)
                                ->first();

                            if (!$facturaContrato) {
                                $facturasSinContrato[] = [
                                    'codigo' => $factura->codigo ?? $factura->nro,
                                    'id' => $factura->id
                                ];
                            } else {
                                $facturasConPago[] = [
                                    'factura_id' => $facturaId,
                                    'monto' => $monto,
                                    'contrato_nro' => $facturaContrato->contrato_nro
                                ];
                            }
                        }
                    }
                }
            }

            // Si hay facturas sin contrato, retornar error
            if (count($facturasSinContrato) > 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'facturas_sin_contrato',
                    'facturas' => $facturasSinContrato,
                    'message' => 'Algunas facturas no tienen contrato asociado'
                ], 400);
            }

            // Agrupar por contrato
            $contratosUnicos = [];
            foreach ($facturasConPago as $item) {
                $contratoNro = $item['contrato_nro'];
                if (!isset($contratosUnicos[$contratoNro])) {
                    $contratosUnicos[$contratoNro] = [];
                }
                $contratosUnicos[$contratoNro][] = $item;
            }

            // Calcular próxima factura para cada contrato
            foreach ($contratosUnicos as $contratoNro => $items) {
                $contrato = Contrato::where('nro', $contratoNro)->first();
                if ($contrato) {
                    $preview = $this->calcularProximaFactura($contrato);
                    if ($preview) {
                        $facturasPreview[] = $preview;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'facturas' => $facturasPreview
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'error_general',
                'message' => 'Error al calcular la próxima factura: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calcula la información de la próxima factura basándose en el contrato
     */
    private function calcularProximaFactura($contrato)
    {
        try {
            // Obtener grupo de corte
            $grupoCorte = GrupoCorte::find($contrato->grupo_corte);
            if (!$grupoCorte || !$grupoCorte->fecha_factura) {
                return null;
            }

            // Calcular fecha de factura (siguiente mes)
            $fechaActual = Carbon::now();
            $mesSiguiente = $fechaActual->copy()->addMonth();
            $diaFactura = $grupoCorte->fecha_factura;

            // Validar si el día excede los días del mes
            $ultimoDiaMes = $mesSiguiente->endOfMonth()->day;
            if ($diaFactura > $ultimoDiaMes) {
                $diaFactura = $ultimoDiaMes;
            }

            $fechaFactura = Carbon::create($mesSiguiente->year, $mesSiguiente->month, $diaFactura);

            // Calcular fecha de vencimiento (similar a CronController)
            $y = $mesSiguiente->year;
            $m = $mesSiguiente->month;
            $ds = $grupoCorte->fecha_suspension;
            $da = Carbon::now()->day;
            
            if ($contrato->fecha_suspension) {
                $fechaSuspension = Carbon::parse($contrato->fecha_suspension);
            } else {
                if ($da > $ds && $m != 12) {
                    $m = $m + 1;
                }
                if ($m == 12) {
                    if ($da > $ds) {
                        if (Carbon::now()->month != 11) {
                            $m = 1;
                            $y = $y + 1;
                        }
                    }
                }
                $fechaSuspensionStr = $this->validarFechaUltimoDiaMes($y, $m, $ds);
                $fechaSuspension = Carbon::parse($fechaSuspensionStr);
            }

            // Obtener numeración
            $nro = NumeracionFactura::tipoNumeracion($contrato);
            if (!$nro) {
                return null;
            }

            // Obtener código siguiente disponible
            $inicio = $nro->inicio;
            $codigoTemporal = $nro->prefijo . $inicio;
            
            // Verificar si el código ya existe (similar a CronController)
            while (Factura::where('codigo', $codigoTemporal)->first()) {
                $nro = $nro->fresh();
                $inicio = $nro->inicio;
                $nro->inicio += 1;
                $nro->save();
                $codigoTemporal = $nro->prefijo . $inicio;
            }

            // Calcular items
            $items = [];
            $empresa = Empresa::find(Auth::user()->empresa);
            $fechaActual = Carbon::now()->format('Y-m-d');

            // Obtener contratos múltiples si aplica
            if ($contrato->factura_individual == 0) {
                $contratosMultiples = Contrato::where('client_id', $contrato->client_id)
                    ->where('factura_individual', 0)
                    ->get();
            } else {
                $contratosMultiples = Contrato::where('nro', $contrato->nro)
                    ->where('client_id', $contrato->client_id)
                    ->get();
            }

            foreach ($contratosMultiples as $cm) {
                $descuentoHasta = isset($cm->fecha_hasta_desc) ? $cm->fecha_hasta_desc : null;

                // Plan de Internet
                if ($cm->plan_id) {
                    $plan = PlanesVelocidad::find($cm->plan_id);
                    if ($plan) {
                        $item = Inventario::find($plan->item);
                        if ($item) {
                            $precio = $item->precio;
                            $descuento = 0;
                            $descuentoPesos = 0;

                            // Aplicar descuentos
                            if (($descuentoHasta && $fechaActual <= $descuentoHasta) || !$descuentoHasta) {
                                if ($cm->descuento_pesos && $descuentoPesos == 0) {
                                    $precio = $precio - $cm->descuento_pesos;
                                    $descuentoPesos = 1;
                                }
                                $descuento = $cm->descuento ?? 0;
                            }

                            $impuesto = $item->impuesto;
                            if ($cm->iva_factura == 1) {
                                $impuesto = 19;
                            }

                            $items[] = [
                                'descripcion' => $plan->name,
                                'precio' => round($precio, $empresa->precision ?? 2),
                                'cantidad' => 1,
                                'descuento' => $descuento,
                                'impuesto' => $impuesto,
                                'tipo' => 'Plan Internet'
                            ];
                        }
                    }
                }

                // Plan de Televisión
                if ($cm->servicio_tv) {
                    $item = Inventario::find($cm->servicio_tv);
                    if ($item) {
                        $precio = $item->precio;
                        $descuento = 0;
                        $descuentoPesos = 0;

                        if (($descuentoHasta && $fechaActual <= $descuentoHasta) || !$descuentoHasta) {
                            if ($cm->descuento_pesos && $descuentoPesos == 0) {
                                $precio = $precio - $cm->descuento_pesos;
                                $descuentoPesos = 1;
                            }
                            $descuento = $cm->descuento ?? 0;
                        }

                        $items[] = [
                            'descripcion' => $item->producto,
                            'precio' => round($precio, $empresa->precision ?? 2),
                            'cantidad' => 1,
                            'descuento' => $descuento,
                            'impuesto' => $item->impuesto,
                            'tipo' => 'Televisión'
                        ];
                    }
                }

                // Otro servicio
                if ($cm->servicio_otro) {
                    $item = Inventario::find($cm->servicio_otro);
                    if ($item) {
                        // Validar vencimiento del item si aplica
                        if ($cm->rd_item_vencimiento == 1 && $cm->dt_item_hasta < now()) {
                            continue;
                        }

                        $precio = $item->precio;
                        $descuento = 0;
                        $descuentoPesos = 0;

                        if (($descuentoHasta && $fechaActual <= $descuentoHasta) || !$descuentoHasta) {
                            if ($cm->descuento_pesos && $descuentoPesos == 0) {
                                $precio = $precio - $cm->descuento_pesos;
                                $descuentoPesos = 1;
                            }
                            $descuento = $cm->descuento ?? 0;
                        }

                        $items[] = [
                            'descripcion' => $item->producto,
                            'precio' => round($precio, $empresa->precision ?? 2),
                            'cantidad' => 1,
                            'descuento' => $descuento,
                            'impuesto' => $item->impuesto,
                            'tipo' => 'Otro Servicio'
                        ];
                    }
                }

                // Productos con cuotas pendientes
                $asignacion = Producto::where('contrato', $cm->id)
                    ->where('venta', 1)
                    ->where('status', 2)
                    ->where('cuotas_pendientes', '>', 0)
                    ->get()
                    ->last();

                if ($asignacion) {
                    $item = Inventario::find($asignacion->producto);
                    if ($item) {
                        $precio = $asignacion->precio / $asignacion->cuotas;
                        $descuento = 0;
                        $descuentoPesos = 0;

                        if (($descuentoHasta && $fechaActual <= $descuentoHasta) || !$descuentoHasta) {
                            if ($cm->descuento_pesos && $descuentoPesos == 0) {
                                $precio = $precio - $cm->descuento_pesos;
                                $descuentoPesos = 1;
                            }
                            $descuento = $cm->descuento ?? 0;
                        }

                        $items[] = [
                            'descripcion' => $item->producto,
                            'precio' => round($precio, $empresa->precision ?? 2),
                            'cantidad' => 1,
                            'descuento' => $descuento,
                            'impuesto' => $item->impuesto,
                            'tipo' => 'Producto'
                        ];
                    }
                }
            }

            // Calcular totales
            $subtotal = 0;
            $totalDescuento = 0;
            $totalImpuestos = 0;
            $impuestosPorTipo = [];

            foreach ($items as $item) {
                $subtotalItem = $item['precio'] * $item['cantidad'];
                $subtotal += $subtotalItem;

                // Descuento
                if ($item['descuento'] > 0) {
                    $descuentoItem = ($subtotalItem * $item['descuento']) / 100;
                    $totalDescuento += $descuentoItem;
                    $subtotalItem -= $descuentoItem;
                }

                // Impuestos
                if ($item['impuesto'] > 0) {
                    $impuestoItem = ($subtotalItem * $item['impuesto']) / 100;
                    $totalImpuestos += $impuestoItem;
                    
                    if (!isset($impuestosPorTipo[$item['impuesto']])) {
                        $impuestosPorTipo[$item['impuesto']] = 0;
                    }
                    $impuestosPorTipo[$item['impuesto']] += $impuestoItem;
                }
            }

            $total = $subtotal - $totalDescuento + $totalImpuestos;
            $total = round($total, $empresa->precision ?? 2);

            // Determinar tipo de factura (estándar o electrónica)
            $tipoFactura = 1; // 1 = Estándar, 2 = Electrónica
            $tipoFacturaTexto = 'Estándar';
            
            if ($contrato->facturacion == 3) {
                $electronica = Factura::booleanFacturaElectronica($contrato->client_id);
                if ($electronica) {
                    $tipoFactura = 2;
                    $tipoFacturaTexto = 'Electrónica';
                }
            }

            return [
                'contrato_nro' => $contrato->nro,
                'codigo' => $codigoTemporal,
                'fecha' => $fechaFactura->format('Y-m-d'),
                'vencimiento' => $fechaSuspension->format('Y-m-d'),
                'tipo' => $tipoFactura,
                'tipo_texto' => $tipoFacturaTexto,
                'items' => $items,
                'subtotal' => round($subtotal, $empresa->precision ?? 2),
                'descuento' => round($totalDescuento, $empresa->precision ?? 2),
                'impuestos' => round($totalImpuestos, $empresa->precision ?? 2),
                'total' => $total,
                'impuestos_detalle' => $impuestosPorTipo
            ];

        } catch (\Exception $e) {
            Log::error('Error en calcularProximaFactura: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Crea la próxima factura basándose en el contrato
     * Similar a CronController::CrearFactura pero para creación manual
     */
    private function crearProximaFactura($contrato, $empresa)
    {
        try {
            // Obtener grupo de corte
            $grupoCorte = GrupoCorte::find($contrato->grupo_corte);
            if (!$grupoCorte || !$grupoCorte->fecha_factura) {
                return;
            }

            // Calcular fecha de factura (siguiente mes)
            $fechaActual = Carbon::now();
            $mesSiguiente = $fechaActual->copy()->addMonth();
            $diaFactura = $grupoCorte->fecha_factura;

            // Validar si el día excede los días del mes
            $ultimoDiaMes = $mesSiguiente->endOfMonth()->day;
            if ($diaFactura > $ultimoDiaMes) {
                $diaFactura = $ultimoDiaMes;
            }

            $fechaFactura = Carbon::create($mesSiguiente->year, $mesSiguiente->month, $diaFactura);

            // Calcular fecha de vencimiento
            $y = $mesSiguiente->year;
            $m = $mesSiguiente->month;
            $ds = $grupoCorte->fecha_suspension;
            $da = Carbon::now()->day;
            
            if ($contrato->fecha_suspension) {
                $fechaSuspension = Carbon::parse($contrato->fecha_suspension);
            } else {
                if ($da > $ds && $m != 12) {
                    $m = $m + 1;
                }
                if ($m == 12) {
                    if ($da > $ds) {
                        if (Carbon::now()->month != 11) {
                            $m = 1;
                            $y = $y + 1;
                        }
                    }
                }
                $fechaSuspensionStr = $this->validarFechaUltimoDiaMes($y, $m, $ds);
                $fechaSuspension = Carbon::parse($fechaSuspensionStr);
            }

            // Calcular fecha de pago oportuno
            $y = $mesSiguiente->year;
            $m = $mesSiguiente->month;
            $d = $grupoCorte->fecha_pago;
            if ($d == 0) {
                $d = 30;
            }

            if ($grupoCorte->fecha_factura > $grupoCorte->fecha_pago && $m != 12) {
                $m = $m + 1;
            }

            if ($m == 12 && $grupoCorte->fecha_factura > $grupoCorte->fecha_pago) {
                $y = $y + 1;
                $m = 1;
            }
            $datePagoOportunoStr = $this->validarFechaUltimoDiaMes($y, $m, $d);
            $datePagoOportuno = Carbon::parse($datePagoOportunoStr);

            // Obtener numeración
            $nro = NumeracionFactura::tipoNumeracion($contrato);
            if (!$nro) {
                return;
            }

            // Obtener número de factura
            $num = Factura::where('empresa', $empresa->id)->orderby('id', 'asc')->get()->last();
            if ($num) {
                $numero = $num->nro;
            } else {
                $numero = 0;
            }
            $numero = round(floatval($numero)) + 1;

            // Obtener código siguiente disponible
            while (Factura::where('codigo', $nro->prefijo . $nro->inicio)->where('empresa', $empresa->id)->first()) {
                $nro->inicio += 1;
                $nro->save();
            }
            $facturaCodigo = $nro->prefijo . $nro->inicio;

            // Determinar tipo de factura
            $tipo = 1; // 1 = normal, 2 = Electrónica
            $electronica = Factura::booleanFacturaElectronica($contrato->client_id);

            if ($contrato->facturacion == 3 && !$electronica) {
                $tipo = 1;
            } elseif ($contrato->facturacion == 3 && $electronica) {
                $tipo = 2;
            }

            // Obtener plazo
            $plazo = TerminosPago::where('dias', Funcion::diffDates($fechaSuspension->format('Y-m-d'), Carbon::now()) + 1)->first();

            // Verificar que no exista factura para este contrato en la fecha
            if (DB::table('facturas_contratos')
                ->whereDate('created_at', $fechaFactura->format('Y-m-d'))
                ->where('contrato_nro', $contrato->nro)
                ->where('is_cron', 0)
                ->first()) {
                return; // Ya existe una factura para esta fecha
            }

            // Crear factura
            $factura = new Factura;
            $factura->nro = $numero;
            $factura->codigo = $facturaCodigo;
            $factura->numeracion = $nro->id;
            $factura->plazo = isset($plazo->id) ? $plazo->id : '';
            $factura->term_cond = $contrato->terminos_cond;
            $factura->facnotas = $contrato->notas_fact;
            $factura->empresa = $empresa->id;
            $factura->cliente = $contrato->client_id;
            $factura->fecha = $fechaFactura->format('Y-m-d');
            $factura->tipo = $tipo;
            $factura->vencimiento = $fechaSuspension->format('Y-m-d');
            $factura->suspension = $fechaSuspension->format('Y-m-d');
            $factura->pago_oportuno = $datePagoOportuno->format('Y-m-d');
            $factura->observaciones = 'Facturación Manual - Corte ' . $grupoCorte->fecha_corte;
            $factura->bodega = 1;
            
            // Asignación dinámica del vendedor para evitar errores de integridad (FK)
            $vendedor = Vendedor::where('id', $contrato->vendedor)->where('empresa', $empresa->id)->first();
            if (!$vendedor) {
                // Fallback a vendedor por defecto habilitado de la empresa
                $vendedor = Vendedor::where('empresa', $empresa->id)->where('estado', 1)->first();
                if (!$vendedor) {
                    // Fallback a cualquier vendedor de la empresa
                    $vendedor = Vendedor::where('empresa', $empresa->id)->first();
                }
            }
            $factura->vendedor = $vendedor ? $vendedor->id : 1;
            $factura->prorrateo_aplicado = 0;
            $factura->facturacion_automatica = 0;
            $factura->factura_mes_manual = 1;
            $factura->contrato_id = $contrato->id;
            $factura->created_by = Auth::user()->id;

            // Validar que no exista el código
            if (Factura::where('codigo', $factura->codigo)->count() <= 1) {
                $factura->save();

                // Obtener contratos múltiples si aplica
                if ($contrato->factura_individual == 0) {
                    $contratosMultiples = Contrato::where('client_id', $factura->cliente)
                        ->where('factura_individual', 0)
                        ->get();
                } else {
                    $contratosMultiples = Contrato::where('nro', $contrato->nro)
                        ->where('client_id', $factura->cliente)
                        ->get();
                }

                foreach ($contratosMultiples as $cm) {
                    $descuentoPesos = 0;
                    $descuentoHasta = isset($cm->fecha_hasta_desc) ? $cm->fecha_hasta_desc : null;
                    $fechaActual = Carbon::now()->format('Y-m-d');

                    // Plan de Internet
                    if ($cm->plan_id) {
                        $plan = PlanesVelocidad::find($cm->plan_id);
                        if ($plan) {
                            $item = Inventario::find($plan->item);
                            if ($item) {
                                $item_reg = new ItemsFactura;
                                $item_reg->factura = $factura->id;
                                $item_reg->producto = $item->id;
                                $item_reg->ref = $item->ref;
                                $item_reg->precio = $item->precio;
                                $item_reg->descripcion = $plan->name;
                                $item_reg->id_impuesto = $item->id_impuesto;
                                $item_reg->impuesto = $item->impuesto;
                                if ($cm->iva_factura == 1) {
                                    $item_reg->id_impuesto = 1;
                                    $item_reg->impuesto = 19;
                                }
                                $item_reg->cant = 1;

                                if ($descuentoHasta != null && $fechaActual <= $descuentoHasta) {
                                    $item_reg->desc = $cm->descuento;
                                    if ($cm->descuento_pesos != null && $descuentoPesos == 0) {
                                        $item_reg->precio = $item_reg->precio - $cm->descuento_pesos;
                                        $descuentoPesos = 1;
                                    }
                                } elseif ($descuentoHasta == null || $descuentoHasta == "") {
                                    $item_reg->desc = $cm->descuento;
                                    if ($cm->descuento_pesos != null && $descuentoPesos == 0) {
                                        $item_reg->precio = $item_reg->precio - $cm->descuento_pesos;
                                        $descuentoPesos = 1;
                                    }
                                }

                                $item_reg->save();
                            }
                        }
                    }

                    // Plan de Televisión
                    if ($cm->servicio_tv) {
                        $item = Inventario::find($cm->servicio_tv);
                        if ($item) {
                            $item_reg = new ItemsFactura;
                            $item_reg->factura = $factura->id;
                            $item_reg->producto = $item->id;
                            $item_reg->ref = $item->ref;
                            $item_reg->precio = $item->precio;
                            $item_reg->descripcion = $item->producto;
                            $item_reg->id_impuesto = $item->id_impuesto;
                            $item_reg->impuesto = $item->impuesto;
                            $item_reg->cant = 1;

                            if ($descuentoHasta != null && $fechaActual <= $descuentoHasta) {
                                $item_reg->desc = $cm->descuento;
                                if ($cm->descuento_pesos != null && $descuentoPesos == 0) {
                                    $item_reg->precio = $item_reg->precio - $cm->descuento_pesos;
                                    $descuentoPesos = 1;
                                }
                            } elseif ($descuentoHasta == null || $descuentoHasta == "") {
                                $item_reg->desc = $cm->descuento;
                                if ($cm->descuento_pesos != null && $descuentoPesos == 0) {
                                    $item_reg->precio = $item_reg->precio - $cm->descuento_pesos;
                                    $descuentoPesos = 1;
                                }
                            }

                            $item_reg->save();
                        }
                    }

                    // Otro servicio
                    if ($cm->servicio_otro) {
                        $item = Inventario::find($cm->servicio_otro);
                        if ($item) {
                            if ($cm->rd_item_vencimiento == 1) {
                                if ($cm->dt_item_hasta >= now()) {
                                    $item_reg = new ItemsFactura;
                                    $item_reg->factura = $factura->id;
                                    $item_reg->producto = $item->id;
                                    $item_reg->ref = $item->ref;
                                    $item_reg->precio = $item->precio;
                                    $item_reg->descripcion = $item->producto;
                                    $item_reg->id_impuesto = $item->id_impuesto;
                                    $item_reg->impuesto = $item->impuesto;
                                    $item_reg->cant = 1;

                                    if ($descuentoHasta != null && $fechaActual <= $descuentoHasta) {
                                        $item_reg->desc = $cm->descuento;
                                        if ($cm->descuento_pesos != null && $descuentoPesos == 0) {
                                            $item_reg->precio = $item_reg->precio - $cm->descuento_pesos;
                                            $descuentoPesos = 1;
                                        }
                                    } elseif ($descuentoHasta == null || $descuentoHasta == "") {
                                        $item_reg->desc = $cm->descuento;
                                        if ($cm->descuento_pesos != null && $descuentoPesos == 0) {
                                            $item_reg->precio = $item_reg->precio - $cm->descuento_pesos;
                                            $descuentoPesos = 1;
                                        }
                                    }

                                    $item_reg->save();
                                }
                            } else {
                                $item_reg = new ItemsFactura;
                                $item_reg->factura = $factura->id;
                                $item_reg->producto = $item->id;
                                $item_reg->ref = $item->ref;
                                $item_reg->precio = $item->precio;
                                $item_reg->descripcion = $item->producto;
                                $item_reg->id_impuesto = $item->id_impuesto;
                                $item_reg->impuesto = $item->impuesto;
                                $item_reg->cant = 1;

                                if ($descuentoHasta != null && $fechaActual <= $descuentoHasta) {
                                    $item_reg->desc = $cm->descuento;
                                    if ($cm->descuento_pesos != null && $descuentoPesos == 0) {
                                        $item_reg->precio = $item_reg->precio - $cm->descuento_pesos;
                                        $descuentoPesos = 1;
                                    }
                                } elseif ($descuentoHasta == null || $descuentoHasta == "") {
                                    $item_reg->desc = $cm->descuento;
                                    if ($cm->descuento_pesos != null && $descuentoPesos == 0) {
                                        $item_reg->precio = $item_reg->precio - $cm->descuento_pesos;
                                        $descuentoPesos = 1;
                                    }
                                }

                                $item_reg->save();
                            }
                        }
                    }

                    // Productos con cuotas pendientes
                    $asignacion = Producto::where('contrato', $cm->id)
                        ->where('venta', 1)
                        ->where('status', 2)
                        ->where('cuotas_pendientes', '>', 0)
                        ->get()
                        ->last();

                    if ($asignacion) {
                        $item = Inventario::find($asignacion->producto);
                        if ($item) {
                            $item_reg = new ItemsFactura;
                            $item_reg->factura = $factura->id;
                            $item_reg->producto = $item->id;
                            $item_reg->ref = $item->ref;
                            $item_reg->precio = ($asignacion->precio / $asignacion->cuotas);
                            $item_reg->descripcion = $item->producto;
                            $item_reg->id_impuesto = $item->id_impuesto;
                            $item_reg->impuesto = $item->impuesto;
                            $item_reg->cant = 1;

                            if ($descuentoHasta != null && $fechaActual <= $descuentoHasta) {
                                $item_reg->desc = $cm->descuento;
                                if ($cm->descuento_pesos != null && $descuentoPesos == 0) {
                                    $item_reg->precio = $item_reg->precio - $cm->descuento_pesos;
                                    $descuentoPesos = 1;
                                }
                            } elseif ($descuentoHasta == null || $descuentoHasta == "") {
                                $item_reg->desc = $cm->descuento;
                                if ($cm->descuento_pesos != null && $descuentoPesos == 0) {
                                    $item_reg->precio = $item_reg->precio - $cm->descuento_pesos;
                                    $descuentoPesos = 1;
                                }
                            }

                            $item_reg->save();
                        }
                    }

                    // Guardar en facturas_contratos
                    DB::table('facturas_contratos')->insert([
                        'factura_id' => $factura->id,
                        'contrato_nro' => $cm->nro,
                        'created_by' => Auth::user()->id,
                        'client_id' => $factura->cliente,
                        'is_cron' => 0,
                        'created_at' => Carbon::now()
                    ]);
                }

                // Aplicar prorrateo si está habilitado
                if ($empresa->prorrateo == 1) {
                    $dias = $factura->diasCobradosProrrateo();
                    if ($dias < 30) {
                        DB::table('factura')->where('id', $factura->id)->update([
                            'prorrateo_aplicado' => 1
                        ]);

                        foreach ($factura->itemsFactura as $item) {
                            $precioItemProrrateo = round($item->precio * $dias / 30, $empresa->precision);
                            DB::table('items_factura')->where('id', $item->id)->update([
                                'precio' => $precioItemProrrateo
                            ]);
                        }
                    }
                }

                // Actualizar numeración
                $nro->save();
            }

        } catch (\Exception $e) {
            Log::error('Error en crearProximaFactura: ' . $e->getMessage());
        }
    }

    /**
     * Valida y corrige una fecha si el día excede los días del mes
     * Si el día es inválido para el mes, lo ajusta al último día del mes
     * 
     * @param int|string $year Año
     * @param int|string $month Mes (1-12)
     * @param int|string $day Día
     * @return string Fecha corregida en formato Y-m-d
     */
    private function validarFechaUltimoDiaMes($year, $month, $day)
    {
        try {
            // Convertir a enteros
            $year = (int)$year;
            $month = (int)$month;
            $day = (int)$day;
            
            // Intentar crear la fecha con el primer día del mes para obtener el último día válido
            $fecha = Carbon::create($year, $month, 1);
            $ultimoDiaMes = $fecha->endOfMonth()->day;
            
            // Si el día excede el último día del mes, usar el último día
            if ($day > $ultimoDiaMes) {
                $day = $ultimoDiaMes;
            }
            
            // Crear y retornar la fecha corregida
            $fechaCorregida = Carbon::create($year, $month, $day);
            return $fechaCorregida->format('Y-m-d');
            
        } catch (\Exception $e) {
            // En caso de error, usar el último día del mes
            try {
                $fecha = Carbon::create($year, $month, 1);
                return $fecha->endOfMonth()->format('Y-m-d');
            } catch (\Exception $e2) {
                // Si aún hay error, retornar fecha actual como fallback
                return Carbon::now()->format('Y-m-d');
            }
        }
    }

    /**
     * Aplica el porcentaje de descuento a los ítems de la factura.
     *
     * - 1 ítem: se asigna el porcentaje directamente.
     * - >1 ítems: se asigna el mismo porcentaje a todos; el último ítem absorbe
     *   cualquier diferencia por redondeo para que la suma de descuentos
     *   coincida exactamente con (subtotal * pct / 100).
     */
    private function aplicarDescuentoItemsFactura($facturaId, $descuentoPct)
    {
        $itemsFactura = ItemsFactura::where('factura', $facturaId)->get();
        $totalItems = $itemsFactura->count();

        if ($totalItems == 0) {
            return;
        }

        if ($totalItems == 1) {
            $item = $itemsFactura->first();
            $item->desc = round($descuentoPct, 2);
            $item->save();
            $this->logDescuentoFactura($facturaId);
            return;
        }

        $subtotalFactura = 0;
        foreach ($itemsFactura as $item) {
            $subtotalFactura += $item->precio * $item->cant;
        }

        $descuentoTotalEsperado = round($subtotalFactura * $descuentoPct / 100, 4);
        $descuentoAcumulado = 0;
        $idx = 0;

        foreach ($itemsFactura as $item) {
            $itemBase = $item->precio * $item->cant;

            if ($idx < $totalItems - 1) {
                $item->desc = round($descuentoPct, 2);
                $descuentoAcumulado += $itemBase * $descuentoPct / 100;
            } else {
                $descuentoRestante = $descuentoTotalEsperado - $descuentoAcumulado;
                if ($itemBase > 0) {
                    $pctAjustado = ($descuentoRestante * 100) / $itemBase;
                    if ($pctAjustado < 0) { $pctAjustado = 0; }
                    if ($pctAjustado > 99) { $pctAjustado = 99; }
                    $item->desc = round($pctAjustado, 2);
                } else {
                    $item->desc = round($descuentoPct, 2);
                }
            }

            $item->save();
            $idx++;
        }

        $this->logDescuentoFactura($facturaId);
    }

    private function logDescuentoFactura($facturaId)
    {
        $factura = Factura::find($facturaId);
        if (!$factura) {
            return;
        }

        $usuario = Auth::user();
        $nombreUsuario = $usuario ? trim($usuario->nombres . ' ' . $usuario->apellidos) : 'Usuario APP';

        $movimiento = new MovimientoLOG();
        $movimiento->contrato    = $factura->id;
        $movimiento->modulo      = 8;
        $movimiento->descripcion = 'El usuario ' . $nombreUsuario . ' aplicó un descuento a la transacción y la factura.';
        $movimiento->created_by  = $usuario ? $usuario->id : null;
        $movimiento->empresa     = $factura->empresa;
        $movimiento->save();
    }

    /**
     * Verifica si algún contrato del cliente tiene habilitada la opción pago_emitir
     * para auto-seleccionar la opción de emisión en el formulario de ingresos.
     *
     * @param int $cliente_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function pagoEmitirDian($cliente_id)
    {
        $pago_emitir = Contrato::where('client_id', $cliente_id)
            ->where('pago_emitir', 1)
            ->exists();
            
        return response()->json(['pago_emitir' => $pago_emitir]);
    }
}
