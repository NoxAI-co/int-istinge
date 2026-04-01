<?php

namespace App\Http\Controllers;

use App\Etiqueta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade as PDF;
use Validator;
use Session;

use App\Model\Ingresos\Ingreso;
use App\Model\Ingresos\IngresosCategoria;
use App\Model\Inventario\ListaPrecios;
use App\Model\Inventario\Inventario;
use App\Solicitud;
use App\Empresa;
use App\Contrato;
use App\Servicio;
use App\User;
use App\AP;
use App\Barrios;
use App\Contacto;
use App\TipoIdentificacion;
use App\Vendedor;
use App\TipoEmpresa;
use App\Numeracion;
use App\Impuesto;
use App\Categoria;
use App\Movimiento;
use App\MovimientoLOG;
use App\Servidor;
use App\Mikrotik;
use App\Funcion;
use App\PlanesVelocidad;
use App\Interfaz;
use App\Ping;
use App\Canal;
use App\Integracion;
use App\Nodo;
use App\GrupoCorte;
use App\Segmento;
use App\Campos;
use App\Puerto;
use App\Oficina;
use App\CRM;
use App\CajaNap;
use App\Model\Ingresos\Factura;
use App\Model\Ingresos\ItemsFactura;
use App\NumeracionFactura;
use App\TerminosPago;
use Illuminate\Support\Facades\Auth as Auth;
use Illuminate\Support\Facades\DB as DB;

include_once(app_path() . '/../public/PHPExcel/Classes/PHPExcel.php');

use PHPExcel;
use PHPExcel_IOFactory;
use PHPExcel_Style_Alignment;
use PHPExcel_Style_Fill;
use PHPExcel_Style_Border;
use PHPExcel_Style_NumberFormat;
use PHPExcel_Shared_ZipArchive;

include_once(app_path() . '/../public/routeros_api.class.php');

use RouterosAPI;

class ContratosController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        set_time_limit(300);
        view()->share(['seccion' => 'contratos', 'subseccion' => 'listado', 'title' => 'Contratos de Servicio', 'icon' => 'fas fa-file-contract']);
    }

    public function actualizarFecha(Request $request)
    {
        try {
            $request->validate([
                'contrato_id' => 'required|exists:contracts,id',
                'fecha' => 'required|date_format:d-m-Y H:i:s'
            ]);

            $contrato = Contrato::where('id', $request->contrato_id)
                ->where('empresa', Auth::user()->empresa)
                ->first();

            if (!$contrato) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contrato no encontrado'
                ], 404);
            }

            // Convertir fecha de formato d-m-Y H:i:s a formato de base de datos
            $fechaCarbon = Carbon::createFromFormat('d-m-Y H:i:s', $request->fecha);
            $contrato->created_at = $fechaCarbon;
            $contrato->save();

            return response()->json([
                'success' => true,
                'message' => 'Fecha actualizada correctamente',
                'fecha' => $fechaCarbon->format('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la fecha: ' . $e->getMessage()
            ], 500);
        }
    }

    public function index(Request $request)
    {

        $this->getAllPermissions(Auth::user()->id);
        $user = auth()->user();
        $userServer = $user->servidores->pluck('id')->toArray();
        $clientes = (Auth::user()->oficina && $user->empresa()->oficina) ? Contacto::whereIn('tipo_contacto', [0, 2])->where('status', 1)->where('empresa', $user->empresa)->where('oficina', $user->oficina)->orderBy('nombre', 'ASC')->get() : Contacto::whereIn('tipo_contacto', [0, 2])->where('status', 1)->where('empresa', $user->empresa)->orderBy('nombre', 'ASC')->get();
        $planes = PlanesVelocidad::where('status', 1)->where('empresa', $user->empresa)->get();
        $planestv = Inventario::where('type', 'like', '%TV%')->get();
        $servidores = Mikrotik::where('status', 1)->where('empresa', $user->empresa)->whereIn('id', $userServer)->get();
        $grupos = GrupoCorte::where('status', 1)->where('empresa', $user->empresa)->get();
        view()->share(['title' => 'Contratos', 'invert' => true]);
        $tipo = false;
        $tabla = Campos::join('campos_usuarios', 'campos_usuarios.id_campo', '=', 'campos.id')->where('campos_usuarios.id_modulo', 2)->where('campos_usuarios.id_usuario', $user->id)->where('campos_usuarios.estado', 1)->orderBy('campos_usuarios.orden', 'ASC')->get();
        $nodos = Nodo::where('status', 1)->where('empresa', $user->empresa)->get();
        $aps = AP::where('status', 1)->where('empresa', $user->empresa)->get();
        $cajaNaps = CajaNap::where('status', 1)->orderBy('nombre', 'ASC')->get();

        $vendedores = Vendedor::where('empresa', $user->empresa)
            ->where('estado', 1)
            ->get();

        $canales = Canal::where('empresa', $user->empresa)->where('status', 1)->get();
        $etiquetas = Etiqueta::where('empresa_id', $user->empresa)->get();
        $barrios = Barrios::where('status', '1')->get();
        return view('contratos.indexnew', compact('clientes', 'planes', 'servidores', 'planestv', 'grupos', 'tipo', 'tabla', 'nodos', 'aps', 'vendedores', 'canales', 'etiquetas', 'barrios', 'cajaNaps'));
    }

    public function disabled(Request $request)
    {
        $this->getAllPermissions(Auth::user()->id);
        $clientes = (Auth::user()->oficina && Auth::user()->empresa()->oficina) ? Contacto::whereIn('tipo_contacto', [0, 2])->where('status', 1)->where('empresa', Auth::user()->empresa)->where('oficina', Auth::user()->oficina)->orderBy('nombre', 'ASC')->get() : Contacto::whereIn('tipo_contacto', [0, 2])->where('status', 1)->where('empresa', Auth::user()->empresa)->orderBy('nombre', 'ASC')->get();
        $planes = PlanesVelocidad::where('status', 1)->where('empresa', Auth::user()->empresa)->get();
        $servidores = Mikrotik::where('status', 1)->where('empresa', Auth::user()->empresa)->get();
        $grupos = GrupoCorte::where('status', 1)->where('empresa', Auth::user()->empresa)->get();
        view()->share(['title' => 'Contratos', 'invert' => true]);
        $tipo = 'disabled';
        $tabla = Campos::join('campos_usuarios', 'campos_usuarios.id_campo', '=', 'campos.id')->where('campos_usuarios.id_modulo', 2)->where('campos_usuarios.id_usuario', Auth::user()->id)->where('campos_usuarios.estado', 1)->orderBy('campos_usuarios.orden', 'ASC')->get();
        $nodos = Nodo::where('status', 1)->where('empresa', Auth::user()->empresa)->get();
        $aps = AP::where('status', 1)->where('empresa', Auth::user()->empresa)->get();
        $vendedores = Vendedor::where('empresa', Auth::user()->empresa)->where('estado', 1)->get();
        $canales = Canal::where('empresa', Auth::user()->empresa)->where('status', 1)->get();
        $barrios = Barrios::where('status', '1')->get();
        $cajaNaps = CajaNap::where('status', 1)->orderBy('nombre', 'ASC')->get();
        return view('contratos.indexnew', compact('clientes', 'planes', 'servidores', 'grupos', 'tipo', 'tabla', 'nodos', 'aps', 'vendedores', 'canales', 'barrios', 'cajaNaps'));
    }

    public function enabled(Request $request)
    {
        $this->getAllPermissions(Auth::user()->id);
        $clientes = (Auth::user()->oficina && Auth::user()->empresa()->oficina) ? Contacto::whereIn('tipo_contacto', [0, 2])->where('status', 1)->where('empresa', Auth::user()->empresa)->where('oficina', Auth::user()->oficina)->orderBy('nombre', 'ASC')->get() : Contacto::whereIn('tipo_contacto', [0, 2])->where('status', 1)->where('empresa', Auth::user()->empresa)->orderBy('nombre', 'ASC')->get();
        $planes = PlanesVelocidad::where('status', 1)->where('empresa', Auth::user()->empresa)->get();
        $servidores = Mikrotik::where('status', 1)->where('empresa', Auth::user()->empresa)->get();
        $grupos = GrupoCorte::where('status', 1)->where('empresa', Auth::user()->empresa)->get();
        view()->share(['title' => 'Contratos', 'invert' => true]);
        $tipo = 'enabled';
        $tabla = Campos::join('campos_usuarios', 'campos_usuarios.id_campo', '=', 'campos.id')->where('campos_usuarios.id_modulo', 2)->where('campos_usuarios.id_usuario', Auth::user()->id)->where('campos_usuarios.estado', 1)->orderBy('campos_usuarios.orden', 'ASC')->get();
        $nodos = Nodo::where('status', 1)->where('empresa', Auth::user()->empresa)->get();
        $aps = AP::where('status', 1)->where('empresa', Auth::user()->empresa)->get();
        $vendedores = Vendedor::where('empresa', Auth::user()->empresa)->where('estado', 1)->get();
        $canales = Canal::where('empresa', Auth::user()->empresa)->where('status', 1)->get();
        $barrios = Barrios::where('status', '1')->get();
        $cajaNaps = CajaNap::where('status', 1)->orderBy('nombre', 'ASC')->get();
        return view('contratos.indexnew', compact('clientes', 'planes', 'servidores', 'grupos', 'tipo', 'tabla', 'nodos', 'aps', 'vendedores', 'canales', 'barrios', 'cajaNaps'));
    }

    public function contratos(Request $request, $nodo)
    {

        $this->getAllPermissions(Auth::user()->id);
        $user = auth()->user();
        $modoLectura = $user->modo_lectura();
        $etiquetas = Etiqueta::where('empresa_id', $user->empresa)->get();

        $contratos = Contrato::query()
            ->select(
                'contracts.*',
                'contactos.id as c_id',
                'contactos.nombre as c_nombre',
                'contactos.apellido1 as c_apellido1',
                'municipios.nombre as nombre_municipio',
                'contactos.apellido2 as c_apellido2',
                'contactos.nit as c_nit',
                'contactos.celular as c_telefono',
                'contactos.email as c_email',
                'contactos.barrio_id as c_barrio',
                'contactos.direccion',
                'contactos.celular as c_celular',
                'contactos.fk_idmunicipio',
                'contactos.firma_isp',
                'barrio.nombre as barrio_nombre',
                'cn.nombre as cajanap_nombre',
                DB::raw('(select fecha from ingresos where ingresos.cliente = contracts.client_id and ingresos.tipo = 1 LIMIT 1) AS pago')
            )
            ->selectRaw('INET_ATON(contracts.ip) as ipformat')
            ->join('contactos', 'contracts.client_id', '=', 'contactos.id')
            ->leftJoin('municipios', 'contactos.fk_idmunicipio', '=', 'municipios.id')
            ->leftJoin('barrios as barrio', 'barrio.id', 'contactos.barrio_id')
            ->leftJoin('caja_naps as cn', 'cn.id', '=', 'contracts.cajanap_id')
            ->where('contracts.empresa', Auth::user()->empresa)
            ->where('contracts.status', '!=', 0);

        //Buscamos los contratos con server configuration + los que no tienen conf pero son de tv.
        if ($user->servidores->count() > 0) {
            $servers = $user->servidores->pluck('id')->toArray();

            $contratos->where(function ($query) use ($servers) {
                $query->whereIn('server_configuration_id', $servers)
                    ->orWhere(function ($subQuery) use ($servers) {
                        $subQuery->whereNotNull('servicio_tv');
                    });
            });
        }

        if ($request->filtro == true) {

            if ($request->cliente_id) {
                $contratos->where(function ($query) use ($request) {
                    $query->orWhere('contracts.client_id', $request->cliente_id);
                });
            }


            if ($request->fecha_sin_facturas) {
                $fechaFiltro = Carbon::parse($request->fecha_sin_facturas)->format('Y-m-d');
                $inicioDia = Carbon::parse($fechaFiltro)->startOfDay();
                $finDia = Carbon::parse($fechaFiltro)->endOfDay();
                // Excluir contratos que tengan facturas en la relación many-to-many (facturas_contratos) en el rango de fecha
                $contratos->whereDoesntHave('facturas', function ($query) use ($inicioDia, $finDia) {
                    $query->whereBetween('factura.fecha', [$inicioDia, $finDia]);
                });
                // Excluir contratos que tengan facturas directamente en la tabla factura en el rango de fecha
                $contratos->whereDoesntHave('facturasDirectas', function ($query) use ($inicioDia, $finDia) {
                    $query->whereBetween('factura.fecha', [$inicioDia, $finDia]);
                });
            }

            if ($request->plan) {
                $contratos->where(function ($query) use ($request) {
                    $query->orWhere('contracts.plan_id', $request->plan);
                });
            }

            if ($request->otra_opcion && $request->otra_opcion == "opcion_5") {
                $contratos->leftJoin('facturas_contratos as fc', 'fc.contrato_nro', '=', 'contracts.nro')
                    ->leftJoin('factura as f', 'fc.factura_id', '=', 'f.id')
                    ->whereNull('f.id')
                    ->groupBy('contracts.id');
            }

            // Filtro para contratos cuya última factura creada esté vencida
            if ($request->otra_opcion && $request->otra_opcion == "opcion_6") {
                $contratos->join('facturas_contratos as fc_ultima', function($join) {
                    $join->on('fc_ultima.contrato_nro', '=', 'contracts.nro')
                         ->whereRaw('fc_ultima.factura_id = (
                             SELECT MAX(fc2.factura_id)
                             FROM facturas_contratos as fc2
                             WHERE fc2.contrato_nro = contracts.nro
                         )');
                })
                ->join('factura as f_ultima', 'f_ultima.id', '=', 'fc_ultima.factura_id')
                ->where('f_ultima.vencimiento', '<=', Carbon::now()->format('Y-m-d'))
                ->where('f_ultima.estatus', '=', 1)
                ->groupBy('contracts.id');
            }

            // Aplica el filtro de facturas si el usuario lo selecciona
            if ($request->otra_opcion && $request->otra_opcion == "opcion_4") {
                $contratos->join('facturas_contratos as fc', 'fc.contrato_nro', '=', 'contracts.nro')
                    ->join('factura as f', 'fc.factura_id', '=', 'f.id')
                    ->where('f.estatus', '=', 1)
                    ->where('f.vencimiento', '<=', Carbon::now()->format('Y-m-d'))
                    ->groupBy('contracts.id')
                    ->havingRaw('COUNT(f.id) > 1');
            }
            if ($request->otra_opcion && $request->otra_opcion == "opcion_3") {
                $contratos->join('facturas_contratos as fc', 'fc.contrato_nro', '=', 'contracts.nro')
                    ->join('factura as f', 'fc.factura_id', '=', 'f.id')
                    ->where('f.estatus', '=', 1)
                    ->groupBy('contracts.id')
                    ->havingRaw('COUNT(f.id) > 1');
            }
            if ($request->ip) {
                $contratos->where(function ($query) use ($request) {
                    $query->orWhere('contracts.ip', 'like', "%{$request->ip}%");
                });
            }
            if(isset($request->state_olt_catv)){
                if($request->state_olt_catv == 1){
                    $contratos->where(function ($query) use ($request) {
                        $query->orWhere('contracts.state_olt_catv', $request->state_olt_catv);
                    });
                }else{
                    $contratos->where(function ($query) use ($request) {
                        $query->orWhere('contracts.state_olt_catv', $request->state_olt_catv)->where('contracts.olt_sn_mac', '!=', null);
                    });
                }
            }
            if ($request->mac) {
                $contratos->where(function ($query) use ($request) {
                    $query->orWhere('contracts.mac_address', 'like', "%{$request->mac}%");
                });
            }
            if ($request->grupo_corte) {
                $contratos->where('contracts.grupo_corte', $request->grupo_corte);
            }
            if ($request->state) {
                $contratos->where(function ($query) use ($request) {
                    $query->orWhere('contracts.state', $request->state)
                        ->whereIn('contracts.status', [0, 1]);
                });
            }
            if ($request->conexion) {
                $contratos->where(function ($query) use ($request) {
                    $query->orWhere('contracts.conexion', $request->conexion);
                });
            }
            if ($request->server_configuration_id) {
                $contratos->where(function ($query) use ($request) {
                    $query->orWhere('contracts.server_configuration_id', $request->server_configuration_id);
                });
            }
            if ($request->nodo) {
                $contratos->where(function ($query) use ($request) {
                    $query->orWhere('contracts.nodo', $request->nodo);
                });
            }
            if ($request->plan_tv) {

                $contratos->where(function ($query) use ($request) {
                    $query->orWhereIn('contracts.servicio_tv', $request->plan_tv);
                });
            }
            if (isset($request->catv)) {
                $contratos->where(function ($query) use ($request) {
                    $query->orWhereNotNull('contracts.olt_sn_mac')
                        ->whereIn('contracts.state_olt_catv', $request->catv);
                });
            }
            if ($request->ap) {
                $contratos->where(function ($query) use ($request) {
                    $query->orWhere('contracts.ap', $request->ap);
                });
            }
            if ($request->cajanap_id) {
                $contratos->where('contracts.cajanap_id', $request->cajanap_id);
            }
            if ($request->c_direccion) {

                $direccion = $request->c_direccion;
                $direccion = explode(' ', $direccion);
                $direccion = array_reverse($direccion);

                foreach ($direccion as $dir) {
                    $dir = strtolower($dir);
                    $dir = str_replace("#", "", $dir);

                    $contratos->where(function ($query) use ($dir) {
                        $query->orWhere('contactos.direccion', 'like', "%{$dir}%");
                        $query->orWhere('contracts.address_street', 'like', "%{$dir}%");
                    });
                }
            }
            if ($request->c_direccion_precisa) {
                $contratos->where(function ($query) use ($request) {
                    $query->orWhere('contracts.address_street', 'like', "%{$request->c_direccion_precisa}%");
                    $query->orWhere('contactos.direccion', 'like', "%{$request->c_direccion_precisa}%");
                });
            }
            if ($request->c_barrio) {
                $contratos->where(function ($query) use ($request) {
                    $query->orWhereIn('contactos.barrio_id', $request->c_barrio);
                });
            }
            if ($request->c_celular) {
                $contratos->where(function ($query) use ($request) {
                    $query->orWhere('contactos.celular', 'like', "%{$request->c_celular}%");
                });
            }
            if ($request->c_email) {
                $contratos->where(function ($query) use ($request) {
                    $query->orWhere('contactos.email', 'like', "%{$request->c_email}%");
                });
            }
            if ($request->vendedor) {
                $contratos->where('contracts.vendedor', $request->vendedor);
            }
            if ($request->canal) {
                $contratos->where(function ($query) use ($request) {
                    $query->orWhere('contracts.canal', $request->canal);
                });
            }
            if ($request->tecnologia) {
                $contratos->where(function ($query) use ($request) {
                    $query->orWhere('contracts.tecnologia', $request->tecnologia);
                });
            }

            if ($request->etiqueta_id) {
                $contratos->where(function ($query) use ($request) {
                    $query->orWhere('contracts.etiqueta_id', $request->etiqueta_id);
                });
            }

            if ($request->facturacion) {
                $contratos->where(function ($query) use ($request) {
                    $query->orWhere('contracts.facturacion', $request->facturacion);
                });
            }
            if ($request->desde) {
                $contratos->where(function ($query) use ($request) {
                    $query->whereDate('contracts.created_at', '>=', Carbon::parse($request->desde)->format('Y-m-d'));
                });
            }
            if ($request->hasta) {
                $contratos->where(function ($query) use ($request) {
                    $query->whereDate('contracts.created_at', '<=', Carbon::parse($request->hasta)->format('Y-m-d'));
                });
            }
            if ($request->fecha_corte) {
                $idContratos = Contrato::select('contracts.*')
                    ->join('contactos', 'contactos.id', '=', 'contracts.client_id')
                    ->join('factura as f', 'f.cliente', '=', 'contactos.id')
                    ->whereDate('f.vencimiento', Carbon::parse($request->fecha_corte)->format('Y-m-d'))
                    ->groupBy('contracts.id')
                    ->get()
                    ->keyBy('id')
                    ->keys()
                    ->all();

                $contratos->where(function ($query) use ($idContratos) {
                    $query->whereIn('contracts.id', $idContratos);
                });
            }
            if ($request->tipo_contrato) {
                $contratos->where(function ($query) use ($request) {
                    $query->orWhere('contracts.tipo_contrato', $request->tipo_contrato);
                });
            }
            if ($request->nro) {
                $contratos->where(function ($query) use ($request) {
                    $query->orWhere('contracts.nro', 'like', "%{$request->nro}%");
                });
            }
            if ($request->sn) {
                $contratos->where(function ($query) use ($request) {
                    $query->orWhere('contracts.olt_sn_mac', 'like', "%{$request->sn}%")
                    ->orWhere('contracts.serial_onu', 'like', "%{$request->sn}%");
                });
            }
            if ($request->observaciones) {
                $contratos->where(function ($query) use ($request) {
                    $query->orWhere('contracts.observaciones', 'like', "%{$request->observaciones}%");
                });
            }
            if ($request->c_estrato) {
                $contratos->where(function ($query) use ($request) {
                    $query->orWhere('contracts.estrato', 'like', "%{$request->c_estrato}%");
                });
            }

            if ($request->linea) {
                $contratos->where(function ($query) use ($request) {
                    $query->orWhere('contracts.linea', 'like', "%{$request->linea}%");
                });
            }

            if ($request->otra_opcion && $request->otra_opcion == "opcion_2") {
                $contratos->where(function ($query) {
                    $query->whereNotNull('contracts.descuento')
                        ->orWhereNotNull('contracts.descuento_pesos');
                });
            }
        }

        $contratos->where('contracts.empresa', Auth::user()->empresa);
        $nodo = explode("-", $nodo);

        if ($nodo[0] == 'n') {
            $contratos->where('contracts.nodo', $nodo[1]);
        } elseif ($nodo[0] == 'a') {
            $contratos->where('contracts.ap', $nodo[1]);
        } elseif ($nodo[0] == 'g') {
            $contratos->where('contracts.grupo_corte', $nodo[1]);
        } elseif ($nodo[0] == 'm') {
            $contratos->where('contracts.server_configuration_id', $nodo[1]);
            $contratos->where('contracts.ip_autorizada', 0);
        } elseif ($nodo[0] == 'p') {
            $contratos->where('contracts.plan_id', $nodo[1]);
        }

        if (Auth::user()->empresa()->oficina) {
            if ($user->oficina) {
                $contratos->where('contracts.oficina', $user->oficina);
            }
        }

        //Esta opción es para mirar los contratos deshabilitados con su ultima factura pagada.
        if ($request->otra_opcion && $request->otra_opcion == "opcion_1") {

            $contratos = Contrato::where('state', 'disabled')
            ->orWhere('state_olt_catv',0)->where('olt_sn_mac','!=','NULL')
            ->get();

            $i = 0;
            $arrayContratos = array();
            foreach ($contratos as $contrato) {

                $facturaContratos = DB::table('facturas_contratos')
                    ->where('contrato_nro', $contrato->nro)->orderBy('id', 'DESC')->first();

                if ($facturaContratos) {
                    $ultFactura = Factura::Find($facturaContratos->factura_id);
                    if (isset($ultFactura->estatus) && $ultFactura->estatus == 0) {
                        array_push($arrayContratos, $contrato->id);
                    }
                }
            }
            $contratos = Contrato::select(
                'contracts.*',
                'contactos.id as c_id',
                'contactos.nombre as c_nombre',
                'contactos.apellido1 as c_apellido1',
                'municipios.nombre as nombre_municipio',
                'contactos.apellido2 as c_apellido2',
                'contactos.nit as c_nit',
                'contactos.celular as c_telefono',
                'contactos.email as c_email',
                'contactos.barrio as c_barrio',
                'contactos.direccion',
                'contactos.celular as c_celular',
                'contactos.fk_idmunicipio',
                'contactos.email as c_email',
                'contactos.id as c_id',
                'contactos.firma_isp',
                'contracts.estrato',
                'barrio.nombre as barrio_nombre',
                DB::raw('(select fecha from ingresos where ingresos.cliente = contracts.client_id and ingresos.tipo = 1 LIMIT 1) AS pago')
            )
                ->selectRaw('INET_ATON(contracts.ip) as ipformat')
                ->join('contactos', 'contracts.client_id', '=', 'contactos.id')
                ->join('municipios', 'contactos.fk_idmunicipio', '=', 'municipios.id')
                ->leftJoin('barrios as barrio', 'barrio.id', 'contactos.barrio_id')
                ->whereIn('contracts.id', $arrayContratos);
        }

        return datatables()->eloquent($contratos)
            ->editColumn('nro', function (Contrato $contrato) {
                if ($contrato->ip) {
                    return $contrato->nro ? "<a href=" . route('contratos.show', $contrato->id) . " class='ml-2'><strong>$contrato->nro</strong></a>" : "";
                } else {
                    return $contrato->nro ? "<a href=" . route('contratos.show', $contrato->id) . " class='ml-2'><strong>$contrato->nro</strong></a>" : "";
                }
            })
            ->editColumn('client_id', function (Contrato $contrato) {
                return  "<a href=" . route('contactos.show', $contrato->c_id) . ">{$contrato->c_nombre} {$contrato->c_apellido1} {$contrato->c_apellido2} {$contrato->municipio}</a>";
            })
            ->editColumn('nit', function (Contrato $contrato) {
                return '(' . $contrato->cliente()->tip_iden('mini') . ') ' . $contrato->c_nit;
            })
            ->addColumn('etiqueta', function (Contrato $contrato) use ($etiquetas) {
                return view('contratos.etiqueta', compact('etiquetas', 'contrato'));
            })
            ->editColumn('telefono', function (Contrato $contrato) {
                return $contrato->c_telefono;
            })
            ->editColumn('email', function (Contrato $contrato) {
                return $contrato->c_email;
            })
            ->editColumn('sn', function (Contrato $contrato) {
                return $contrato->olt_sn_mac ?: '';
            })
            ->editColumn('barrio', function (Contrato $contrato) {
                return $contrato->barrio_nombre;
            })
            ->editColumn('fk_idmunicipio', function (Contrato $contrato) {
                return $contrato->nombre_municipio;
            })
            ->editColumn('plan', function (Contrato $contrato) {
                if ($contrato->plan_id) {
                    return "<div class='elipsis-short-325'><a href=" . route('planes-velocidad.show', $contrato->plan()->id) . " target='_blank'>{$contrato->plan()->name}</a></div>";
                } else {
                    return 'N/A';
                }
            })
            ->editColumn('mac', function (Contrato $contrato) {
                return ($contrato->mac_address) ? $contrato->mac_address : 'N/A';
            })
            ->editColumn('ipformat', function (Contrato $contrato) {
                return ($contrato->ip) ? '<a href="http://' . $contrato->ip . '" target="_blank">' . $contrato->ip . '  <i class="fas fa-external-link-alt"></i></a>' : 'N/A';
                // $puerto = $contrato->puerto ? ':'.$contrato->puerto->nombre : '';
                // return ($contrato->ipformat) ? '<a href="http://'.$contrato->ip.''.$puerto.'" target="_blank">'.$contrato->ip.''.$puerto.'  <i class="fas fa-external-link-alt"></i></a>' : 'N/A';
                // return $contrato->ipformat;
            })
            ->editColumn('grupo_corte', function (Contrato $contrato) {
                return $contrato->grupo_corte('true');
            })
            ->editColumn('state', function (Contrato $contrato) {
                return '<span class="text-' . $contrato->status('true') . ' font-weight-bold">' . $contrato->status() . '</span>';
            })
            ->editColumn('state_olt_catv', function (Contrato $contrato) {
                if ($contrato->olt_sn_mac) {
                    $estado = $contrato->state_olt_catv == 1 ? 'Habilitado' : 'Deshabilitado';
                    $color = $contrato->state_olt_catv == 1 ? 'success' : 'danger';
                    return '<span class="text-' . $color . ' font-weight-bold">' . $estado . '</span>';
                }
                return 'N/A';
            })
            ->editColumn('pago', function (Contrato $contrato) {
                return ($contrato->pago($contrato->c_id)) ? '<a href=' . route('ingresos.show', $contrato->pago($contrato->c_id)->id) . ' target="_blank">Nro. ' . $contrato->pago($contrato->c_id)->nro . ' | ' . date('d-m-Y', strtotime($contrato->pago($contrato->c_id)->fecha)) . '</a>' : 'N/A';
            })
            ->editColumn('servicio', function (Contrato $contrato) {
                return 'N/A';
            })
            ->editColumn('conexion', function (Contrato $contrato) {
                if ($contrato->conexion) {
                    return $contrato->conexion();
                }
                return 'N/A';
            })
            ->editColumn('server_configuration_id', function (Contrato $contrato) {
                return ($contrato->server_configuration_id) ? $contrato->servidor()->nombre : 'N/A';
            })
            ->editColumn('interfaz', function (Contrato $contrato) {
                return ($contrato->interfaz) ? $contrato->interfaz : 'N/A';
            })
            ->editColumn('nodo', function (Contrato $contrato) {
                // Puede ser un objeto o un String. ¿Por qué?
                $nodo = $contrato->nodo();
                if (is_object($nodo)) {
                    return $nodo->nombre;
                }
                return "N/A";
            })
            ->editColumn('ap', function (Contrato $contrato) {
                // Puede ser un objeto o un String. ¿Por qué?
                $ap = $contrato->ap();
                if (is_object($ap)) {
                    return $ap->nombre;
                }
                return "N/A";
            })
            ->editColumn('direccion', function (Contrato $contrato) {
                return ($contrato->address_street) ? $contrato->address_street : $contrato->direccion;
            })
            ->editColumn('celular', function (Contrato $contrato) {
                return $contrato->c_celular;
            })
            ->editColumn('email', function (Contrato $contrato) {
                return $contrato->c_email;
            })
            ->editColumn('factura', function (Contrato $contrato) {
                return $contrato->factura();
            })
            ->editColumn('servicio_tv', function (Contrato $contrato) {
                return ($contrato->servicio_tv) ? '<a href=' . route('inventario.show', $contrato->servicio_tv) . ' target="_blank">' . $contrato->plan('true')->producto . '</a>' : 'N/A';
            })
            ->editColumn('vendedor', function (Contrato $contrato) {
                $vendedor = $contrato->vendedor();
                return ($vendedor) ? $vendedor->nombre : 'N/A';
            })
            ->editColumn('canal', function (Contrato $contrato) {
                $canal = $contrato->canal();
                return ($canal) ? $canal->nombre : 'N/A';
            })
            ->editColumn('tecnologia', function (Contrato $contrato) {
                return ($contrato->tecnologia) ? $contrato->tecnologia() : 'N/A';
            })
            ->editColumn('facturacion', function (Contrato $contrato) {
                return ($contrato->facturacion) ? $contrato->facturacion() : 'N/A';
            })
            ->editColumn('tipo_contrato', function (Contrato $contrato) {
                return ($contrato->tipo_contrato) ? ucfirst($contrato->tipo_contrato) : 'N/A';
            })
            ->editColumn('created_at', function (Contrato $contrato) {
                return ($contrato->created_at) ? date('d-m-Y', strtotime($contrato->created_at)) : 'N/A';
            })
            ->editColumn('estrato', function (Contrato $contrato) {
                return ($contrato->estrato) ? $contrato->estrato : 'N/A';
            })
            ->editColumn('observaciones', function (Contrato $contrato) {
                return ($contrato->observaciones) ? $contrato->observaciones : 'N/A';
            })
            ->editColumn('acciones', $modoLectura ?  "" : "contratos.acciones")
            ->rawColumns(['nro', 'client_id', 'nit', 'telefono', 'email', 'barrio', 'plan', 'mac', 'ipformat', 'grupo_corte', 'state', 'state_olt_catv', 'pago', 'servicio', 'factura', 'servicio_tv', 'acciones', 'vendedor', 'canal', 'tecnologia', 'observaciones', 'created_at'])
            ->toJson();
    }

    public function getPlanes($servidor_id)
    {
        try {
            $planes = PlanesVelocidad::where('status', 1)
                                    ->where('empresa', Auth::user()->empresa)
                                    ->where('mikrotik', $servidor_id)
                                    ->get();

            return response()->json([
                'success' => true,
                'planes' => $planes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los planes: ' . $e->getMessage()
            ], 500);
        }
    }

    public function create($cliente = false)
    {

        // $profile = $API->comm("/ppp/profile/getall");
        // dd($profile);

        $this->getAllPermissions(Auth::user()->id);
        $empresa = Auth::user()->empresa;
        $clientes = (Auth::user()->oficina && Auth::user()->empresa()->oficina) ? Contacto::whereIn('tipo_contacto', [0, 2])->where('status', 1)->where('empresa', Auth::user()->empresa)->where('oficina', Auth::user()->oficina)->orderBy('nombre', 'ASC')->get() : Contacto::whereIn('tipo_contacto', [0, 2])->where('status', 1)->where('empresa', Auth::user()->empresa)->orderBy('nombre', 'ASC')->get();
        // $clientes = Contacto::whereIn('tipo_contacto', [0,2])->where('status', 1)->where('empresa', Auth::user()->empresa)->orderBy('nombre', 'ASC')->get();

        $cajas    = DB::table('bancos')->where('tipo_cta', 3)->where('estatus', 1)->where('empresa', Auth::user()->empresa)->get();
        $servidores = Mikrotik::where('status', 1)->where('empresa', Auth::user()->empresa)->get();
    $planes = PlanesVelocidad::where('status', 1)->where('empresa', Auth::user()->empresa)->get();
        $identificaciones = TipoIdentificacion::all();
        $paises  = DB::table('pais')->where('codigo', 'CO')->get();
        $departamentos = DB::table('departamentos')->get();
        $nodos = Nodo::where('status', 1)->where('empresa', Auth::user()->empresa)->get();
        $aps = AP::where('status', 1)->where('empresa', Auth::user()->empresa)->get();
        $marcas = DB::table('marcas')->get();
        $grupos = GrupoCorte::where('status', 1)->where('empresa', Auth::user()->empresa)->get();
        $puertos = Puerto::where('empresa', Auth::user()->empresa)->get();
        $servicios = Inventario::where('empresa', Auth::user()->empresa)->where('type', 'TV')->where('status', 1)->get();
        $serviciosOtros = Inventario::where('empresa', Auth::user()->empresa)->where('type', '<>', 'TV')->where('status', 1)->get();
        $vendedores = Vendedor::where('empresa', Auth::user()->empresa)->where('estado', 1)->get();
        $canales = Canal::where('empresa', Auth::user()->empresa)->where('status', 1)->get();
        $gmaps = Integracion::where('empresa', Auth::user()->empresa)->where('tipo', 'GMAPS')->first();
        $oficinas = (Auth::user()->oficina && Auth::user()->empresa()->oficina) ? Oficina::where('id', Auth::user()->oficina)->get() : Oficina::where('empresa', Auth::user()->empresa)->where('status', 1)->get();
        $cajasNaps = CajaNap::where('status', 1)->get();

        $empresa_obj = Empresa::Find(Auth::user()->empresa);
        $nro = Numeracion::where('empresa', 1)->first();
        if (isset($empresa_obj->separar_numeracion) && $empresa_obj->separar_numeracion == 1) {
            $numero_contrato = "Por definir (Según servidor)";
        } else {
            $numero_contrato = $nro->contrato;
            while (true) {
                $existe = Contrato::where('nro', $numero_contrato)->count();
                if ($existe == 0) {
                    break;
                }
                $numero_contrato++;
            }
        }

        view()->share(['icon' => 'fas fa-file-contract', 'title' => 'Nuevo Contrato']);
        return view('contratos.create')->with(compact(
            'clientes',
            'planes',
            'servidores',
            'identificaciones',
            'paises',
            'departamentos',
            'nodos',
            'aps',
            'marcas',
            'grupos',
            'cliente',
            'puertos',
            'empresa',
            'servicios',
            'vendedores',
            'canales',
            'gmaps',
            'oficinas',
            'serviciosOtros',
            'cajasNaps',
            'numero_contrato'
        ));
    }

    public function store(Request $request)
    {

        $this->getAllPermissions(Auth::user()->id);
        $request->validate([
            'client_id' => 'required',
            'grupo_corte' => 'required',
            'facturacion' => 'required',
            'contrato_permanencia' => 'required',
            'tipo_contrato' => 'required',
        ]);

        if ($request->contrato_permanencia == 1) {
            $request->validate([
                'contrato_permanencia_meses' => 'required'
            ]);
        }

        if (!$request->server_configuration_id && !$request->servicio_tv) {
            return back()->with('danger', 'ESTÁ INTENTANDO GENERAR UN CONTRATO PERO NO HA SELECCIONADO NINGÚN SERVICIO')->withInput();
        }

        if ($request->mac_address) {
            $mac_address = Contrato::where('mac_address', $request->mac_address)->where('status', 1)->first();

            if ($mac_address) {
                return back()->withInput()->with('danger', 'LA DIRECCIÓN MAC YA SE ENCUENTRA REGISTRADA PARA OTRO CONTRATO');
            }
        }

        if ($request->server_configuration_id) {
            $rules = [
                'plan_id' => 'required',
                'server_configuration_id' => 'required',
                'conexion' => 'required',
            ];

            // El campo IP no es obligatorio cuando la conexión es DHCP o PPPOE y simple_queue es dinámico
            if (!(($request->conexion == 2 || $request->conexion == 1) && $request->simple_queue == 'dinamica')) {
                $rules['ip'] = 'required';
            }

            $request->validate($rules);
        } else        if ($request->servicio_tv) {
            $request->validate([
                'servicio_tv' => 'required'
            ]);
        }

        // Validación: Si hay adjunto, debe haber referencia correspondiente
        if ($request->hasFile('adjunto_a') && empty($request->referencia_a)) {
            return back()->withInput()->with('danger', 'Si ingresa un Adjunto A, debe ingresar también una Referencia A');
        }
        if ($request->hasFile('adjunto_b') && empty($request->referencia_b)) {
            return back()->withInput()->with('danger', 'Si ingresa un Adjunto B, debe ingresar también una Referencia B');
        }
        if ($request->hasFile('adjunto_c') && empty($request->referencia_c)) {
            return back()->withInput()->with('danger', 'Si ingresa un Adjunto C, debe ingresar también una Referencia C');
        }
        if ($request->hasFile('adjunto_d') && empty($request->referencia_d)) {
            return back()->withInput()->with('danger', 'Si ingresa un Adjunto D, debe ingresar también una Referencia D');
        }

        $ppoe_local_adress = "";
        $mikrotik = Mikrotik::where('id', $request->server_configuration_id)->first();
        $plan = PlanesVelocidad::where('id', $request->plan_id)->first();
        $empresa = Empresa::Find(Auth::user()->empresa);
        $cliente = Contacto::find($request->client_id);
        $servicio = $cliente->nombre . ' ' . $cliente->apellido1 . ' ' . $cliente->apellido2;
        $empresa = Empresa::Find(Auth::user()->empresa);

        if ($mikrotik) {
            $API = new RouterosAPI();
            $API->port = $mikrotik->puerto_api;
            $registro = false;
            $getall = '';
            $ip_autorizada = 0;
            //$API->debug = true;

            $nro = Numeracion::where('empresa', 1)->first();


            if (isset($empresa->separar_numeracion) && $empresa->separar_numeracion == 1) {

                $contratoMk = Contrato::where('server_configuration_id', $request->server_configuration_id)
                    ->orderBy('nro', 'desc')
                    ->first();

                if ($contratoMk) {
                    $nro_contrato = $contratoMk->nro + 1;
                }else{
                    $nro_contrato = 0;
                }

                $existe = Contrato::where('nro', $nro_contrato)->count();
                while ($existe > 0) {
                    $nro_contrato++;
                    $existe = Contrato::where('nro', $nro_contrato)->count();
                }
            } else {
                $nro_contrato = $nro->contrato;

                while (true) {
                    $numero = Contrato::where('nro', $nro_contrato)->count();
                    if ($numero == 0) {
                        break;
                    }
                    $nro_contrato++;
                }
            }

            if($empresa->consultas_mk == 1){

                if ($API->connect($mikrotik->ip, $mikrotik->usuario, $mikrotik->clave)) {

                    $rate_limit = '';
                    $priority        = (isset($plan->prioridad) && !empty($plan->prioridad)) ? $plan->prioridad : 8;
                    $burst_limit     = (strlen($plan->burst_limit_subida) > 1) ? $plan->burst_limit_subida . '/' . $plan->burst_limit_bajada : 0;
                    $burst_threshold = (strlen($plan->burst_threshold_subida) > 1) ? $plan->burst_threshold_subida . '/' . $plan->burst_threshold_bajada : 0;
                    $burst_time      = ($plan->burst_time_subida) ? $plan->burst_time_subida . '/' . $plan->burst_time_bajada : 0;
                    $limit_at        = (strlen($plan->limit_at_subida) > 1) ? $plan->limit_at_subida . '/' . $plan->limit_at_bajada  : 0;
                    $max_limit       = $plan->upload . '/' . $plan->download;

                    if ($max_limit) {
                        $rate_limit .= $max_limit;
                    }
                    if (strlen($burst_limit) > 3) {
                        $rate_limit .= ' ' . $burst_limit;
                    }
                    if (strlen($burst_threshold) > 3) {
                        $rate_limit .= ' ' . $burst_threshold;
                    }
                    if (strlen($burst_time) > 3) {
                        $rate_limit .= ' ' . $burst_time;
                    }
                    if ($priority) {
                        $rate_limit .= ' ' . $priority;
                    }
                    if (strlen($limit_at) > 3) {
                        $rate_limit .= ' ' . $limit_at;
                    }

                    /*PPPOE*/
                    if ($request->conexion == 1) {

                        $ppoe_local_adress = $request->direccion_local_address;

                        $data = [
                            "name"           => $request->usuario,
                            "password"       => $request->password,
                            "profile"        => $request->profile,
                            "service"        => 'pppoe',
                            "comment"        => $this->normaliza($servicio) . '-' . $nro_contrato
                        ];

                        // Solo agregar remote-address y local-address si Simple Queue es estática
                        // Si Simple Queue es dinámica, no se incluyen estos campos (la IP se asigna automáticamente)
                        if ($request->simple_queue != 'dinamica') {
                            if (!empty($request->ip)) {
                                $data["remote-address"] = $request->ip;
                            }
                            // Solo agregar si viene con valor válido
                            if (!empty($request->direccion_local_address)) {
                                $data["local-address"] = $request->direccion_local_address;
                            }
                        }

                        $error = $API->comm("/ppp/secret/add", $data);

                        $registro = true;
                        $getall = $API->comm(
                            "/ppp/secret/getall",
                            array(
                                "?local-address" => $request->ip
                            )
                        );
                    }

                    /*DHCP*/
                    if ($request->conexion == 2) {
                        if ($plan->dhcp_server) {
                            if ($request->simple_queue == 'dinamica') {
                                $API->comm("/ip/dhcp-server/set\n=name=" . $plan->dhcp_server . "\n=address-pool=static-only\n=parent-queue=" . $plan->parenta);

                                $API->comm(
                                    "/ip/dhcp-server/lease/add",
                                    array(
                                        "comment"     => $this->normaliza($servicio) . '-' . $nro_contrato,
                                        "address"     => $request->ip,
                                        "server"      => $plan->dhcp_server,
                                        "mac-address" => $request->mac_address,
                                        "rate-limit"  => $rate_limit
                                    )
                                );

                                $name = $API->comm(
                                    "/ip/dhcp-server/lease/getall",
                                    array(
                                        "?comment" => $this->normaliza($servicio) . '-' . $nro_contrato
                                    )
                                );
                            } elseif ($request->simple_queue == 'estatica') {
                                $API->comm(
                                    "/ip/dhcp-server/lease/add",
                                    array(
                                        "comment"     => $this->normaliza($servicio) . '-' . $nro_contrato,
                                        "address"     => $request->ip,
                                        "server"      => $plan->dhcp_server,
                                        "mac-address" => $request->mac_address
                                    )
                                );

                                $name = $API->comm(
                                    "/ip/dhcp-server/lease/getall",
                                    array(
                                        "?comment" => $this->normaliza($servicio) . '-' . $nro_contrato
                                    )
                                );

                                if ($name) {
                                    $registro = true;
                                    $API->comm(
                                        "/queue/simple/add",
                                        array(
                                            "name"            => $this->normaliza($servicio) . '-' . $nro_contrato,
                                            "target"          => $request->ip,
                                            "max-limit"       => $plan->upload . '/' . $plan->download,
                                            "burst-limit"     => $burst_limit,
                                            "burst-threshold" => $burst_threshold,
                                            "burst-time"      => $burst_time,
                                            "priority"        => $priority,
                                            "limit-at"        => $limit_at
                                        )
                                    );
                                }
                            }
                        } else {
                            $mensaje = 'NO SE HA PODIDO CREAR EL CONTRATO DE SERVICIOS, NO EXISTE UN SERVIDOR DHCP DEFINIDO PARA EL PLAN ' . $plan->name;
                            return redirect('empresa/contratos')->with('danger', $mensaje);
                        }
                    }

                    /*IP ESTÁTICA*/
                    if ($request->conexion == 3) {

                        if ($mikrotik->amarre_mac == 1) {
                            $request->validate([
                                'mac_address' => 'required'
                            ]);

                            $API->comm(
                                "/ip/arp/add",
                                array(
                                    "comment"     => $this->normaliza($servicio) . '-' . $nro_contrato,
                                    "address"     => $request->ip,
                                    "interface"   => $request->interfaz,
                                    "mac-address" => $request->mac_address
                                )
                            );
                        }

                        if (!empty($plan->queue_type_subida) && !empty($plan->queue_type_bajada)) {
                            // Si tienen datos, asignar "queue" con los valores de subida y bajada
                            $queue = $plan->queue_type_subida . '/' . $plan->queue_type_bajada;
                        } else {
                            // Si no tienen datos, asignar "queue" con los valores predeterminados
                            $queue = "default-small/default-small";
                        }

                        $API->comm(
                            "/queue/simple/add",
                            array(
                                "name"            => $this->normaliza($servicio) . '-' . $nro_contrato,
                                "target"          => $request->ip,
                                "max-limit"       => $plan->upload . '/' . $plan->download,
                                "burst-limit"     => $burst_limit,
                                "burst-threshold" => $burst_threshold,
                                "burst-time"      => $burst_time,
                                "priority"        => $priority,
                                "limit-at"        => $limit_at,
                                // "queue"           => $plan->queue_type_subida.'/'.$plan->queue_type_bajada
                                "queue"           => $queue
                            )
                        );

                        $name = $API->comm(
                            "/queue/simple/getall",
                            array(
                                "?target" => $request->ip
                            )
                        );

                        if ($name) {
                            $registro = true;
                        }

                        if ($request->ip_new) {
                            if ($mikrotik->amarre_mac == 1) {
                                $API->comm(
                                    "/ip/arp/add",
                                    array(
                                        "comment"     => $this->normaliza($servicio) . '-' . $nro_contrato,
                                        "address"     => $request->ip_new,
                                        "interface"   => $request->interfaz,
                                        "mac-address" => $request->mac_address
                                    )
                                );
                            }

                            $API->comm(
                                "/queue/simple/add",
                                array(
                                    "name"            => $this->normaliza($servicio) . '-' . $nro_contrato,
                                    "target"          => $request->ip,
                                    "max-limit"       => $plan->upload . '/' . $plan->download,
                                    "burst-limit"     => $burst_limit,
                                    "burst-threshold" => $burst_threshold,
                                    "burst-time"      => $burst_time,
                                    "priority"        => $priority,
                                    "limit-at"        => $limit_at
                                )
                            );
                        }
                    }

                    /*VLAN*/
                    if ($request->conexion == 4) {
                        $API->comm(
                            "/interface/vlan/add",
                            array(
                                "name"        => $request->name_vlan,
                                "vlan-id"     => $request->id_vlan,
                                "interface"   => $request->interfaz
                            )
                        );

                        $API->comm(
                            "/ip/address/add",
                            array(
                                "address"     => $request->local_address,
                                "interface"   => $request->name_vlan
                            )
                        );

                        $API->comm(
                            "/queue/simple/add",
                            array(
                                "name"            => $this->normaliza($servicio) . '-' . $nro_contrato,
                                "target"          => $request->ip,
                                "max-limit"       => $plan->upload . '/' . $plan->download,
                                "burst-limit"     => $burst_limit,
                                "burst-threshold" => $burst_threshold,
                                "burst-time"      => $burst_time,
                                "priority"        => $priority,
                                "limit-at"        => $limit_at
                            )
                        );
                    }

                    if ($mikrotik->regla_ips_autorizadas == 1) {
                        $API->comm("/ip/firewall/address-list/add\n=list=ips_autorizadas\n=address=" . $request->ip);
                        $ip_autorizada = 1;
                    }

                    /*ACTIVE CONNECTION*/
                    if (isset($empresa->activeconn_secret) && $empresa->activeconn_secret == 1) {
                        if ($contrato->conexion == 1 && $contrato->usuario != null) {
                            $API->write('/ppp/secret/print', false);
                            $API->write('?name=' . $contrato->usuario, true);
                            $ARRAYS = $API->read();
                            if (count($ARRAYS) > 0) {
                                $API->write('/ppp/secret/enable', false);
                                $API->write('=numbers=' . $ARRAYS[0]['.id'], true);
                                $API->read();
                            }
                        }
                    } 

                    $API->disconnect();

                }else {
                    $mensaje = 'La conexión a la mikrotik ' . $mikrotik->nombre . ' no se pudo establecer';
                    return redirect('empresa/contratos')->with('danger', $mensaje);
                }
            }

            $contrato = new Contrato();
            $contrato->prorrateo                 = $request->prorrateo;
            $contrato->plan_id                 = $request->plan_id;
            $contrato->nro                     = $nro_contrato;
            $contrato->servicio                = $this->normaliza($servicio) . '-' . $nro_contrato;
            $contrato->client_id               = $request->client_id;
            $contrato->server_configuration_id = $request->server_configuration_id;
            $contrato->ip                      = $request->ip;
            $contrato->ip_new                  = $request->ip_new;
            $contrato->usuario                 = $request->usuario;
            $contrato->password                = $request->password;
            $contrato->conexion                = $request->conexion;
            $contrato->simple_queue            = $request->simple_queue;
            $contrato->interfaz                = $request->interfaz;
            $contrato->local_address           = $request->local_address;
            $contrato->direccion_local_address = $request->direccion_local_address;
            $contrato->local_address_new       = $request->local_address_new;
            $contrato->profile                 = $request->profile;
            $contrato->local_adress_pppoe      = $ppoe_local_adress;
            $contrato->mac_address             = $request->mac_address;
            $contrato->id_vlan                 = $request->id_vlan;
            $contrato->name_vlan               = $request->name_vlan;
            $contrato->marca_router            = $request->marca_router;
            $contrato->modelo_router           = $request->modelo_router;
            $contrato->marca_antena            = $request->marca_antena;
            $contrato->modelo_antena           = $request->modelo_antena;
            $contrato->grupo_corte             = $request->grupo_corte;
            $contrato->facturacion             = $request->facturacion;
            $contrato->ip_autorizada           = $ip_autorizada;
            $contrato->empresa                 = Auth::user()->empresa;
            $contrato->puerto_conexion         = $request->puerto_conexion;
            $contrato->cajanap_id              = $request->cajanap_id;
            $contrato->cajanap_puerto          = $request->cajanap_puerto;
            $contrato->latitude                = $request->latitude;
            $contrato->longitude               = $request->longitude;
            $contrato->contrato_permanencia    = $request->contrato_permanencia;
            $contrato->serial_onu              = $request->serial_onu;
            $contrato->linea                   = $request->linea;
            $contrato->descuento               = $request->descuento;
            $contrato->vendedor                = $request->vendedor;
            $contrato->canal                   = $request->canal;
            $contrato->address_street          = $request->address_street;
            $contrato->tecnologia              = $request->tecnologia;
            $contrato->tipo_contrato           = $request->tipo_contrato;
            $contrato->iva_factura             = $request->iva_factura;
            $contrato->observaciones           = $request->observaciones;
            $contrato->usuario_wifi            = $request->usuario_wifi;
            $contrato->contrasena_wifi         = $request->contrasena_wifi;
            $contrato->ip_receptora            = $request->ip_receptora;
            $contrato->puerto_receptor         = $request->puerto_receptor;
            $contrato->serial_moden            = $request->serial_moden;
            $contrato->tipo_moden              = $request->tipo_moden;
            $contrato->descuento_pesos         = $request->descuento_pesos;
            $contrato->fact_primer_mes         = $request->fact_primer_mes;
            $contrato->estrato                 = $request->estrato;
            $contrato->fecha_hasta_desc        = isset($request->fecha_hasta_desc) ? $request->fecha_hasta_desc : null;

            if ($request->rd_item_vencimiento) {
                $contrato->dt_item_hasta           = $request->dt_item_hasta;
                $contrato->rd_item_vencimiento     = $request->rd_item_vencimiento;
            }

            if ($request->olt_sn_mac && $empresa->adminOLT != null && isset($request->state_olt_catv)) {

                $contrato->olt_sn_mac          = $request->olt_sn_mac;
                $curl = curl_init();

                if ($request->state_olt_catv == 1) {
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $empresa->adminOLT . '/api/onu/enable_catv/' . $contrato->olt_sn_mac,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'POST',
                        CURLOPT_HTTPHEADER => array(
                            'X-token: ' . $empresa->smartOLT
                        ),
                    ));
                } else if ($request->state_olt_catv == 0) {
                    $curl = curl_init();

                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $empresa->adminOLT . '/api/onu/disable_catv/' . $contrato->olt_sn_mac,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'POST',
                        CURLOPT_HTTPHEADER => array(
                            'X-token: ' . $empresa->smartOLT
                        ),
                    ));
                }
                //

                $response = curl_exec($curl);
                $response = json_decode($response);

                if (isset($response->status) && $response->status == false) {
                    return redirect('empresa/contratos')->with('danger', 'EL CONTRATO NO HA SIDO ACTUALIZADO POR QUE FALLÓ LA HABILITACIÓN DEL CATV');
                } else {
                    if (isset($response->status) && $response->status == true && $request->state_olt_catv == 0) {
                        $contrato->state_olt_catv = 0;
                    } else {
                        $contrato->state_olt_catv = 1;
                    }
                }
            }


            // Handle tipo_suspension_no toggle
            if ($request->tipo_suspension_no == 1) {
                $contrato->tipo_nosuspension = 1;
                $contrato->fecha_desde_nosuspension = $request->fecha_desde_nosuspension;
                $contrato->fecha_hasta_nosuspension = $request->fecha_hasta_nosuspension;
            } else {
                $contrato->tipo_nosuspension = 0;
                $contrato->fecha_desde_nosuspension = null;
                $contrato->fecha_hasta_nosuspension = null;
            }

            if ($request->factura_individual) {
                $contrato->factura_individual = $request->factura_individual;
            }

            if ($request->ap) {
                $ap = AP::find($request->ap);
                $contrato->nodo    = $ap->nodo;
                $contrato->ap      = $request->ap;
            }

            if ($request->servicio_tv) {
                $contrato->servicio_tv = $request->servicio_tv;
            }

            // Precios personalizados
            if(isset($request->precio_personalizado_internet) && $request->precio_personalizado_internet != ''){
                $contrato->precio_personalizado_internet = $request->precio_personalizado_internet;
            }
            if(isset($request->precio_personalizado_tv) && $request->precio_personalizado_tv != ''){
                $contrato->precio_personalizado_tv = $request->precio_personalizado_tv;
            }

            if ($request->oficina) {
                $contrato->oficina = $request->oficina;
            }

            if ($request->contrato_permanencia_meses) {
                $contrato->contrato_permanencia_meses = $request->contrato_permanencia_meses;
            }

            if ($request->costo_reconexion) {
                $contrato->costo_reconexion = $request->costo_reconexion;
            }

            ### DOCUMENTOS ADJUNTOS ###

            if ($request->adjunto_a) {
                $file = $request->file('adjunto_a');
                $nombre =  $file->getClientOriginalName();
                Storage::disk('documentos')->put($nombre, \File::get($file));
                $contrato->adjunto_a = $nombre;
                $contrato->referencia_a = $request->referencia_a;
            }
            if ($request->adjunto_b) {
                $file = $request->file('adjunto_b');
                $nombre =  $file->getClientOriginalName();
                Storage::disk('documentos')->put($nombre, \File::get($file));
                $contrato->adjunto_b = $nombre;
                $contrato->referencia_b = $request->referencia_b;
            }
            if ($request->adjunto_c) {
                $file = $request->file('adjunto_c');
                $nombre =  $file->getClientOriginalName();
                Storage::disk('documentos')->put($nombre, \File::get($file));
                $contrato->adjunto_c = $nombre;
                $contrato->referencia_c = $request->referencia_c;
            }
            if ($request->adjunto_d) {
                $file = $request->file('adjunto_d');
                $nombre =  $file->getClientOriginalName();
                Storage::disk('documentos')->put($nombre, \File::get($file));
                $contrato->adjunto_d = $nombre;
                $contrato->referencia_d = $request->referencia_d;
            }

            ### DOCUMENTOS ADJUNTOS ###

            $contrato->creador = Auth::user()->nombres;
            if(isset($request->pago_siigo_contrato) && $request->pago_siigo_contrato == 1){
                $contrato->pago_siigo_contrato = 1;
            } else {
                $contrato->pago_siigo_contrato = 0;
            }
            $contrato->save();

            $nro->contrato = $nro_contrato + 1;
            $nro->save();

            //Opcion de crear factrua con prorrateo
            if($contrato->prorrateo == 1 || $contrato->fact_primer_mes == 1){
                try {
                    $this->createFacturaProrrateo($contrato);
                } catch (\Exception $e) {
                    return redirect('empresa/contratos/' . $contrato->id)->with('danger', 'CONTRATO CREADO PERO NO SE PUDO GENERAR LA FACTURA: ' . $e->getMessage());
                }
            }

            if ($registro) {
                $mensaje = 'SE HA CREADO SATISFACTORIAMENTE EL CONTRATO DE SERVICIOS EN EL SISTEMA Y LA MIKROTIK';
            } else {
                $mensaje = 'SE HA CREADO SATISFACTORIAMENTE EL CONTRATO DE SERVICIOS';
            }

            return redirect('empresa/contratos/' . $contrato->id)->with('success', $mensaje);

        } else {
            $nro = Numeracion::where('empresa', 1)->first();
            $nro_contrato = $nro->contrato;

            while (true) {
                $numero = Contrato::where('nro', $nro_contrato)->count();
                if ($numero == 0) {
                    break;
                }
                $nro_contrato++;
            }

            $contrato = new Contrato();
            $contrato->nro                  = $nro_contrato;
            $contrato->servicio             = $this->normaliza($servicio) . '-' . $nro_contrato;
            $contrato->client_id            = $request->client_id;
            $contrato->grupo_corte          = $request->grupo_corte;
            $contrato->facturacion          = $request->facturacion;
            $contrato->empresa              = Auth::user()->empresa;
            $contrato->latitude             = $request->latitude;
            $contrato->longitude            = $request->longitude;
            $contrato->contrato_permanencia = $request->contrato_permanencia;
            $contrato->linea                   = $request->linea;
            $contrato->estrato                  = $request->estrato;
            $contrato->servicio_tv          = $request->servicio_tv;
            $contrato->descuento            = $request->descuento;
            $contrato->vendedor             = $request->vendedor;
            $contrato->canal                = $request->canal;
            $contrato->address_street       = $request->address_street;
            $contrato->tipo_contrato        = $request->tipo_contrato;
            $contrato->observaciones           = $request->observaciones;
            $contrato->ip_receptora            = $request->ip_receptora;
            $contrato->puerto_receptor         = $request->puerto_receptor;

            if ($request->olt_sn_mac && $empresa->adminOLT != null && isset($request->state_olt_catv)) {

                $contrato->olt_sn_mac          = $request->olt_sn_mac;
                $curl = curl_init();

                if ($request->state_olt_catv == 1) {
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $empresa->adminOLT . '/api/onu/enable_catv/' . $contrato->olt_sn_mac,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'POST',
                        CURLOPT_HTTPHEADER => array(
                            'X-token: ' . $empresa->smartOLT
                        ),
                    ));
                } else if ($request->state_olt_catv == 0) {
                    $curl = curl_init();

                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $empresa->adminOLT . '/api/onu/disable_catv/' . $contrato->olt_sn_mac,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'POST',
                        CURLOPT_HTTPHEADER => array(
                            'X-token: ' . $empresa->smartOLT
                        ),
                    ));
                }

                $response = curl_exec($curl);
                $response = json_decode($response);

                if (isset($response->status) && $response->status == false) {
                    return redirect('empresa/contratos')->with('danger', 'EL CONTRATO NO HA SIDO ACTUALIZADO POR QUE FALLÓ LA HABILITACIÓN DEL CATV');
                } else {
                    if (isset($response->status) && $response->status == true && $request->state_olt_catv == 0) {
                        $contrato->state_olt_catv = 0;
                    } else {
                        $contrato->state_olt_catv = 1;
                    }
                }
            }


            if ($request->factura_individual) {
                $contrato->factura_individual   = $request->factura_individual;
            }

            if ($request->oficina) {
                $contrato->oficina = $request->oficina;
            }

            if ($request->contrato_permanencia_meses) {
                $contrato->contrato_permanencia_meses = $request->contrato_permanencia_meses;
            }

            if ($request->costo_reconexion) {
                $contrato->costo_reconexion = $request->costo_reconexion;
            }

            ### DOCUMENTOS ADJUNTOS ###

            if ($request->adjunto_a) {
                $file = $request->file('adjunto_a');
                $nombre =  $file->getClientOriginalName();
                Storage::disk('documentos')->put($nombre, \File::get($file));
                $contrato->adjunto_a = $nombre;
                $contrato->referencia_a = $request->referencia_a;
            }
            if ($request->adjunto_b) {
                $file = $request->file('adjunto_b');
                $nombre =  $file->getClientOriginalName();
                Storage::disk('documentos')->put($nombre, \File::get($file));
                $contrato->adjunto_b = $nombre;
                $contrato->referencia_b = $request->referencia_b;
            }
            if ($request->adjunto_c) {
                $file = $request->file('adjunto_c');
                $nombre =  $file->getClientOriginalName();
                Storage::disk('documentos')->put($nombre, \File::get($file));
                $contrato->adjunto_c = $nombre;
                $contrato->referencia_c = $request->referencia_c;
            }
            if ($request->adjunto_d) {
                $file = $request->file('adjunto_d');
                $nombre =  $file->getClientOriginalName();
                Storage::disk('documentos')->put($nombre, \File::get($file));
                $contrato->adjunto_d = $nombre;
                $contrato->referencia_d = $request->referencia_d;
            }

            ### DOCUMENTOS ADJUNTOS ###

            $contrato->creador = Auth::user()->nombres;
            $contrato->save();

            $nro->contrato = $nro_contrato + 1;
            $nro->save();

            //Opcion de crear factrua con prorrateo
            if ($contrato->prorrateo == 1 || $contrato->fact_primer_mes == 1) {
                try {
                    $this->createFacturaProrrateo($contrato);
                } catch (\Exception $e) {
                    return redirect('empresa/asignaciones/create')->with('cliente_id', $contrato->client_id)->with('danger', 'CONTRATO CREADO PERO NO SE PUDO GENERAR LA FACTURA: ' . $e->getMessage());
                }
            }

            return redirect('empresa/asignaciones/create')->with('cliente_id', $contrato->client_id)->with('success', 'SE HA CREADO SATISFACTORIAMENTE EL CONTRATO DE SERVICIOS');
            // return redirect('empresa/contratos/'.$contrato->id)->with('success', 'SE HA CREADO SATISFACTORIAMENTE EL CONTRATO DE SERVICIOS');
        }

        ## Otro tipo de servicio ingresa tenga o no tenga mk ##
        if ($request->servicio_otro) {
            $contrato->servicio_otro = $request->servicio_otro;
            $contrato->save();
        }
    }

    public function edit($id)
    {
        $this->getAllPermissions(Auth::user()->id);
        $contrato = Contrato::join('contactos as c', 'c.id', '=', 'contracts.client_id')->select(
            'contracts.plan_id',
            'contracts.id',
            'contracts.opciones_dian',
            'contracts.nro',
            'contracts.state',
            'contracts.interfaz',
            'c.nombre',
            'c.apellido1',
            'c.apellido2',
            'c.nit',
            'c.celular',
            'c.telefono1',
            'contracts.ip',
            'contracts.mac_address',
            'contracts.server_configuration_id',
            'contracts.conexion',
            'contracts.marca_router',
            'contracts.modelo_router',
            'contracts.marca_antena',
            'contracts.modelo_antena',
            'contracts.nodo',
            'contracts.ap',
            'contracts.interfaz',
            'contracts.local_address',
            'contracts.local_address_new',
            'contracts.ip_new',
            'contracts.grupo_corte',
            'contracts.contrasena_wifi',
            'contracts.usuario_wifi',
            'contracts.ip_receptora',
            'contracts.puerto_receptor',
            'contracts.facturacion',
            'contracts.fecha_suspension',
            'contracts.usuario',
            'contracts.local_adress_pppoe',
            'contracts.direccion_local_address',
            'contracts.password',
            'contracts.adjunto_a',
            'contracts.referencia_a',
            'contracts.adjunto_b',
            'contracts.referencia_b',
            'contracts.factura_individual',
            'contracts.adjunto_c',
            'contracts.referencia_c',
            'contracts.adjunto_d',
            'contracts.profile',
            'contracts.referencia_d',
            'contracts.simple_queue',
            'contracts.latitude',
            'contracts.longitude',
            'contracts.servicio_tv',
            'contracts.servicio_otro',
            'contracts.contrato_permanencia',
            'contracts.contrato_permanencia_meses',
            'contracts.serial_onu',
            'contracts.iva_factura',
            'contracts.linea',
            'contracts.descuento',
            'contracts.vendedor',
            'contracts.canal',
            'contracts.address_street',
            'contracts.tecnologia',
            'contracts.costo_reconexion',
            'contracts.tipo_contrato',
            'contracts.puerto_conexion',
            'contracts.observaciones',
            'contracts.fecha_hasta_nosuspension',
            'contracts.fecha_desde_nosuspension',
            'contracts.tipo_nosuspension',
            'contracts.serial_moden',
            'contracts.tipo_moden',
            'contracts.descuento_pesos',
            'contracts.olt_sn_mac',
            'contracts.state_olt_catv',
            'contracts.fact_primer_mes',
            'contracts.rd_item_vencimiento',
            'contracts.dt_item_hasta',
            'contracts.pago_siigo_contrato',
            'contracts.cajanap_id',
            'contracts.cajanap_puerto',
            'contracts.prorrateo',
            'contracts.estrato',
            'contracts.precio_personalizado_internet',
            'contracts.precio_personalizado_tv',
        )
            ->where('contracts.id', $id)->where('contracts.empresa', Auth::user()->empresa)->first();


        $planes = ($contrato->server_configuration_id) ? PlanesVelocidad::where('status', 1)->where('mikrotik', $contrato->server_configuration_id)->get() : PlanesVelocidad::where('status', 1)->get();
        $nodos = Nodo::where('status', 1)->where('empresa', Auth::user()->empresa)->get();
        $aps = AP::where('status', 1)->where('empresa', Auth::user()->empresa)->get();
        $marcas = DB::table('marcas')->get();
        // Incluir servidores activos + el servidor actual del contrato (aunque esté deshabilitado)
        $servidores = Mikrotik::where('empresa', Auth::user()->empresa)
            ->where(function ($query) use ($contrato) {
                $query->where('status', 1)
                      ->orWhere('id', $contrato->server_configuration_id);
            })->get();
        $interfaces = Interfaz::all();
        $grupos = GrupoCorte::where('status', 1)->where('empresa', Auth::user()->empresa)->get();
        $puertos = Puerto::where('empresa', Auth::user()->empresa)->get();
        $servicios = Inventario::where('empresa', Auth::user()->empresa)->where('type', 'TV')->where('status', 1)->get();
        $serviciosOtros = Inventario::where('empresa', Auth::user()->empresa)->where('type', '<>', 'TV')->where('status', 1)->get();
        $vendedores = Vendedor::where('empresa', Auth::user()->empresa)->where('estado', 1)->get();
        $canales = Canal::where('empresa', Auth::user()->empresa)->where('status', 1)->get();
        $gmaps = Integracion::where('empresa', Auth::user()->empresa)->where('tipo', 'GMAPS')->first();
        $oficinas = (Auth::user()->oficina && Auth::user()->empresa()->oficina) ? Oficina::where('id', Auth::user()->oficina)->get() : Oficina::where('empresa', Auth::user()->empresa)->where('status', 1)->get();
        $contactos = Contacto::where('status',1)->get();
        $empresa = Empresa::find(1);
        $cajasNaps = CajaNap::where('status', 1)->get();

        // Obtener consultas_mk de la empresa
        $consultasMk = $empresa ? $empresa->consultas_mk : 1;

        if ($contrato) {
            view()->share(['icon' => 'fas fa-file-contract', 'title' => 'Editar Contrato: ' . $contrato->nro]);
            return view('contratos.edit')->with(compact(
                'contrato',
                'planes',
                'nodos',
                'aps',
                'marcas',
                'servidores',
                'interfaces',
                'grupos',
                'puertos',
                'servicios',
                'vendedores',
                'canales',
                'gmaps',
                'oficinas',
                'cajasNaps',
                'serviciosOtros',
                'contactos',
                'empresa',
                'consultasMk'
            ));
        }
        return redirect('empresa/contratos')->with('danger', 'EL CONTRATO DE SERVICIOS NO HA ENCONTRADO');
    }

    public function update(Request $request, $id)
    {

        $this->getAllPermissions(Auth::user()->id);
        $request->validate([
            'grupo_corte' => 'required',
            'facturacion' => 'required',
            'contrato_permanencia' => 'required',
            'nro' => 'required',
        ]);

        if ($request->contrato_permanencia == 1) {
            $request->validate([
                'contrato_permanencia_meses' => 'required'
            ]);
        }

        $verificar = Contrato::where('empresa', Auth::user()->empresa)->where('nro', $request->nro)->where('id', '<>', $id)->first();

        if ($verificar) {
            return back()->with('danger', 'ESTÁ INTENTANDO REGISTRAR UN NRO DE CONTRATO QUE YA SE ENCUENTRA REGISTRADO');
        }

        if (!$request->server_configuration_id && !$request->servicio_tv) {
            return back()->with('danger', 'ESTÁ INTENTANDO GENERAR UN CONTRATO PERO NO HA SELECCIONADO NINGÚN SERVICIO');
        }

        // Validación: Si hay adjunto, debe haber referencia correspondiente
        if ($request->hasFile('adjunto_a') && empty($request->referencia_a)) {
            return back()->withInput()->with('danger', 'Si ingresa un Adjunto A, debe ingresar también una Referencia A');
        }
        if ($request->hasFile('adjunto_b') && empty($request->referencia_b)) {
            return back()->withInput()->with('danger', 'Si ingresa un Adjunto B, debe ingresar también una Referencia B');
        }
        if ($request->hasFile('adjunto_c') && empty($request->referencia_c)) {
            return back()->withInput()->with('danger', 'Si ingresa un Adjunto C, debe ingresar también una Referencia C');
        }
        if ($request->hasFile('adjunto_d') && empty($request->referencia_d)) {
            return back()->withInput()->with('danger', 'Si ingresa un Adjunto D, debe ingresar también una Referencia D');
        }

        if ($request->mac_address) {
            $mac_address = Contrato::where('mac_address', $request->mac_address)->where('status', 1)->where('id', '<>', $id)->first();

            if ($mac_address) {
                return back()->withInput()->with('danger', 'LA DIRECCIÓN MAC YA SE ENCUENTRA REGISTRADA PARA OTRO CONTRATO');
            }
        }

        if ($request->server_configuration_id) {
            $rules = [
                'plan_id' => 'required',
                'server_configuration_id' => 'required',
                'conexion' => 'required',
            ];

            // El campo IP no es obligatorio cuando la conexión es DHCP o PPPOE y simple_queue es dinámico
            if (!(($request->conexion == 2 || $request->conexion == 1) && $request->simple_queue == 'dinamica')) {
                $rules['ip'] = 'required';
            }

            $request->validate($rules);
        } elseif ($request->servicio_tv) {
            $request->validate([
                'servicio_tv' => 'required'
            ]);
        }

        $contrato = Contrato::find($id);
        $empresa = Empresa::Find(Auth::user()->empresa);
        $ppoe_local_adress = "";
        $descripcion = null;
        $registro = false;
        $getall = '';
        if ($contrato) {

            ## Otro tipo de servicio ingresa tenga o no tenga mk ##
            if ($request->servicio_otro) {
                $contrato->servicio_otro = $request->servicio_otro;
            } else {
                $contrato->servicio_otro = null;
            }

            
            // Precios personalizados
            if(isset($request->precio_personalizado_internet)){
                $contrato->precio_personalizado_internet = $request->precio_personalizado_internet != '' ? $request->precio_personalizado_internet : null;
            }
            if(isset($request->precio_personalizado_tv)){
                $contrato->precio_personalizado_tv = $request->precio_personalizado_tv != '' ? $request->precio_personalizado_tv : null;
            }

            $contrato->save();

            $plan = PlanesVelocidad::where('id', $request->plan_id)->first();
            $mikrotik = ($plan) ? Mikrotik::where('id', $plan->mikrotik)->first() : false;
            $cliente = $contrato->cliente();
            $servicio = $cliente->nombre . ' ' . $cliente->apellido1 . ' ' . $cliente->apellido2;

            if ($mikrotik && $mikrotik->status == 1) {
                $API = new RouterosAPI();
                $API->port = $mikrotik->puerto_api;
                //$API->debug = true;

                if($empresa->consultas_mk == 1){
                if ($API->connect($mikrotik->ip, $mikrotik->usuario, $mikrotik->clave)) {
                    ## ELIMINAMOS DE MK ##
                    if ($contrato->conexion == 1) {
                        //OBTENEMOS AL CONTRATO MK
                        $mk_user = $API->comm(
                            "/ppp/secret/getall",
                            array(
                                "?remote-address" => $contrato->ip,
                            )
                        );
                        if ($mk_user) {
                            // REMOVEMOS EL SECRET
                            $API->comm(
                                "/ppp/secret/remove",
                                array(
                                    ".id" => $mk_user[0][".id"],
                                )
                            );
                        }
                    }

                    if ($contrato->conexion == 2) {
                        $name = $API->comm(
                            "/ip/dhcp-server/lease/getall",
                            array(
                                "?address" => $contrato->ip
                            )
                        );
                        if ($name) {
                            // REMOVEMOS EL IP DHCP
                            $API->comm(
                                "/ip/dhcp-server/lease/remove",
                                array(
                                    ".id" => $name[0][".id"],
                                )
                            );
                        }
                    }

                    if ($contrato->conexion == 3) {
                        //OBTENEMOS AL CONTRATO MK
                        $mk_user = $API->comm(
                            "/ip/arp/getall",
                            array(
                                "?address" => $contrato->ip // IP DEL CLIENTE
                            )
                        );
                        if ($mk_user) {
                            // REMOVEMOS EL IP ARP
                            $API->comm(
                                "/ip/arp/remove",
                                array(
                                    ".id" => $mk_user[0][".id"],
                                )
                            );
                        }
                    }

                    #ELMINAMOS DEL QUEUE#
                    $queue = $API->comm(
                        "/queue/simple/getall",
                        array(
                            "?target" => $contrato->ip . '/32'
                        )
                    );

                    #ELMINAMOS DEL QUEUE#

                    #ELIMINAMOS DE IP_AUTORIZADAS#
                    $API->write('/ip/firewall/address-list/print', TRUE);
                    $ARRAYS = $API->read();

                    $API->write('/ip/firewall/address-list/print', false);
                    $API->write('?address=' . $contrato->ip, false);
                    $API->write("?list=ips_autorizadas", false);
                    $API->write('=.proplist=.id');
                    $ARRAYS = $API->read();

                    if (count($ARRAYS) > 0) {
                        $API->write('/ip/firewall/address-list/remove', false);
                        $API->write('=.id=' . $ARRAYS[0]['.id']);
                        $READ = $API->read();
                    }
                    #ELIMINAMOS DE IP_AUTORIZADAS#
                    ## ELIMINAMOS DE MK ##

                    $rate_limit      = '';
                    $priority        = (isset($plan->prioridad) && !empty($plan->prioridad)) ? $plan->prioridad : 8;
                    $burst_limit     = (strlen($plan->burst_limit_subida) > 1) ? $plan->burst_limit_subida . '/' . $plan->burst_limit_bajada : 0;
                    $burst_threshold = (strlen($plan->burst_threshold_subida) > 1) ? $plan->burst_threshold_subida . '/' . $plan->burst_threshold_bajada : 0;
                    $burst_time      = ($plan->burst_time_subida) ? $plan->burst_time_subida . '/' . $plan->burst_time_bajada : 0;
                    $limit_at        = (strlen($plan->limit_at_subida) > 1) ? $plan->limit_at_subida . '/' . $plan->limit_at_bajada : 0;
                    $max_limit       = $plan->upload . '/' . $plan->download;

                    if ($max_limit) {
                        $rate_limit .= $max_limit;
                    }
                    if (strlen($burst_limit) > 3) {
                        $rate_limit .= ' ' . $burst_limit;
                    }
                    if (strlen($burst_threshold) > 3) {
                        $rate_limit .= ' ' . $burst_threshold;
                    }
                    if (strlen($burst_time) > 3) {
                        $rate_limit .= ' ' . $burst_time;
                    }
                    if ($priority) {
                        $rate_limit .= ' ' . $priority;
                    }
                    if ($limit_at) {
                        $rate_limit .= ' ' . $limit_at;
                    }

                    /*PPPOE*/
                    if ($request->conexion == 1) {

                        $ppoe_local_adress = $request->direccion_local_address;

                        $data = [
                            "name"           => $request->usuario,
                            "password"       => $request->password,
                            "profile"        => $request->profile,
                            "service"        => 'pppoe',
                            "comment"        => $this->normaliza($servicio) . '-' . $contrato->nro
                        ];

                        // Solo agregar remote-address y local-address si Simple Queue es estática
                        // Si Simple Queue es dinámica, no se incluyen estos campos (la IP se asigna automáticamente)
                        if ($request->simple_queue != 'dinamica') {
                            if (!empty($request->ip)) {
                                $data["remote-address"] = $request->ip;
                            }
                            // Solo agregar si viene con valor válido
                            if (!empty($request->direccion_local_address)) {
                                $data["local-address"] = $request->direccion_local_address;
                            }
                        }

                        $error = $API->comm("/ppp/secret/add", $data);

                        $registro = true;
                        $getall = $API->comm(
                            "/ppp/secret/getall",
                            array(
                                "?local-address" => $request->ip
                            )
                        );
                    }


                    /*DHCP*/
                    if ($request->conexion == 2) {
                        if (isset($plan->dhcp_server)) {
                            if ($request->simple_queue == 'dinamica') {
                                $API->comm("/ip/dhcp-server/set\n=name=" . $plan->dhcp_server . "\n=address-pool=static-only\n=parent-queue=" . $plan->parenta);
                                $API->comm(
                                    "/ip/dhcp-server/lease/add",
                                    array(
                                        "comment"     => $this->normaliza($servicio) . '-' . $request->nro,
                                        "address"     => $request->ip,
                                        "server"      => $plan->dhcp_server,
                                        "mac-address" => $request->mac_address,
                                        "rate-limit"  => $rate_limit
                                    )
                                );
                            } elseif ($request->simple_queue == 'estatica') {
                                $API->comm(
                                    "/ip/dhcp-server/lease/add",
                                    array(
                                        "comment"     => $this->normaliza($servicio) . '-' . $request->nro,
                                        "address"     => $request->ip,
                                        "server"      => $plan->dhcp_server,
                                        "mac-address" => $request->mac_address
                                    )
                                );
                            }

                            $getall = $API->comm(
                                "/ip/dhcp-server/lease/getall",
                                array(
                                    "?address" => $request->ip
                                )
                            );
                        } else {
                            $mensaje = 'NO SE HA PODIDO EDITAR EL CONTRATO DE SERVICIOS, NO EXISTE UN SERVIDOR DHCP DEFINIDO PARA EL PLAN ' . $plan->name;
                            return redirect('empresa/contratos')->with('danger', $mensaje);
                        }
                    }

                    /*IP ESTÁTICA*/
                    if ($request->conexion == 3) {
                        if ($mikrotik->amarre_mac == 1) {
                            $API->comm(
                                "/ip/arp/add",
                                array(
                                    "comment"     => $this->normaliza($servicio) . '-' . $request->nro,
                                    "address"     => $request->ip,
                                    "interface"   => $request->interfaz,
                                    "mac-address" => $request->mac_address
                                )
                            );

                            $getall = $API->comm(
                                "/ip/arp/getall",
                                array(
                                    "?address" => $request->ip
                                )
                            );
                        }

                        if (!empty($plan->queue_type_subida) && !empty($plan->queue_type_bajada)) {
                            // Si tienen datos, asignar "queue" con los valores de subida y bajada
                            $queue_edit = $plan->queue_type_subida . '/' . $plan->queue_type_bajada;
                        } else {
                            // Si no tienen datos, asignar "queue" con los valores predeterminados
                            $queue_edit = "default-small/default-small";
                        }

                        // Eliminar todas las colas asociadas a la IP
                        foreach ($queue as $q) {
                            $API->comm("/queue/simple/remove", array(
                                ".id" => $q['.id']
                            ));
                        }

                        $response = $API->comm(
                            "/queue/simple/add",
                            array(
                                "name"            => $this->normaliza($servicio) . '-' . $request->nro,
                                "target"          => $request->ip,
                                "max-limit"       => $plan->upload . '/' . $plan->download,
                                "burst-limit"     => $burst_limit,
                                "burst-threshold" => $burst_threshold,
                                "burst-time"      => $burst_time,
                                "priority"        => $priority,
                                "limit-at"        => $limit_at,
                                "queue"           => $queue_edit
                            )
                        );
                    }

                    /*VLAN*/
                    if ($request->conexion == 4) {
                    }
                    //if($getall){
                    $registro = true;
                    $queue = $API->comm(
                        "/queue/simple/getall",
                        array(
                            "?target" => $contrato->ip . '/32'
                        )
                    );


                    #AGREGAMOS A IP_AUTORIZADAS#
                    $API->comm(
                        "/ip/firewall/address-list/add",
                        array(
                            "address" => $request->ip,
                            "list" => 'ips_autorizadas'
                        )
                    );
                    #AGREGAMOS A IP_AUTORIZADAS#
                }

                $API->disconnect();
            }else{
                $registro = true;
            }

                if ($registro) {
                    $grupo = GrupoCorte::find($request->grupo_corte);

                    if ($contrato->grupo_corte) {
                        $descripcion .= ($contrato->grupo_corte == $request->grupo_corte) ? '' : '<i class="fas fa-check text-success"></i> <b>Cambio Grupo de Corte</b> de ' . $contrato->grupo_corte()->nombre . ' a ' . $grupo->nombre . '<br>';
                    } else {
                        $descripcion .= ($contrato->grupo_corte == $request->grupo_corte) ? '' : '<i class="fas fa-check text-success"></i> <b>Cambio Grupo de Corte</b> a ' . $grupo->nombre . '<br>';
                    }
                    $contrato->grupo_corte = $request->grupo_corte;
                    $contrato->facturacion = $request->facturacion;
                    $contrato->prorrateo = $request->prorrateo;

                    /*$descripcion .= ($contrato->fecha_corte == $request->fecha_corte) ? '' : '<i class="fas fa-check text-success"></i> <b>Cambio Fecha de Corte</b> de '.$contrato->fecha_corte.' a '.$request->fecha_corte.'<br>';
                    $contrato->fecha_corte = $request->fecha_corte;*/
                    if($request->fecha_suspension == ""){
                        $request->fecha_suspension = 'Ninguna';
                    }

                    $descripcion .= ($contrato->fecha_suspension == $request->fecha_suspension) ? '' : '<i class="fas fa-check text-success"></i> <b>Cambio Fecha de Suspensión Personalizada</b> a ' . $request->fecha_suspension . '<br>';
                    $contrato->fecha_suspension = $request->fecha_suspension;

                    $plan_old = ($contrato->plan_id) ? PlanesVelocidad::find($contrato->plan_id)->name : 'Ninguno';
                    $plan_new = PlanesVelocidad::find($request->plan_id);

                    $descripcion .= ($contrato->plan_id == $request->plan_id) ? '' : '<i class="fas fa-check text-success"></i> <b>Cambio Plan</b> de ' . $plan_old . ' a ' . $plan_new->name . '<br>';
                    $contrato->plan_id = $request->plan_id;

                    $descripcion .= ($contrato->ip == $request->ip) ? '' : '<i class="fas fa-check text-success"></i> <b>Cambio de IP</b> de ' . $contrato->ip . ' a ' . $request->ip . '<br>';
                    $contrato->ip = $request->ip;

                    $descripcion .= ($contrato->ip_new == $request->ip_new) ? '' : '<i class="fas fa-check text-success"></i> <b>Cambio de IP</b> de ' . $contrato->ip_new . ' a ' . $request->ip_new . '<br>';
                    $contrato->ip_new = $request->ip_new;

                    $descripcion .= ($contrato->local_address == $request->local_address) ? '' : '<i class="fas fa-check text-success"></i> <b>Cambio de Segmento</b> de ' . $contrato->local_address . ' a ' . $request->local_address . '<br>';
                    $contrato->local_address = $request->local_address;

                    $descripcion .= ($contrato->local_address_new == $request->local_address_new) ? '' : '<i class="fas fa-check text-success"></i> <b>Cambio de Segmento</b> de ' . $contrato->local_address_new . ' a ' . $request->local_address_new . '<br>';
                    $contrato->local_address_new = $request->local_address_new;

                    $descripcion .= ($contrato->mac_address == $request->mac_address) ? '' : '<i class="fas fa-check text-success"></i> <b>Cambio de MAC</b> de ' . $contrato->mac_address . ' a ' . $request->mac_address . '<br>';
                    $contrato->mac_address   = $request->mac_address;

                    $descripcion .= ($contrato->marca_router == $request->marca_router) ? '' : '<i class="fas fa-check text-success"></i> <b>Cambio Marca Router</b> de ' . $contrato->marca_router . ' a ' . $request->marca_router . '<br>';
                    $contrato->marca_router  = $request->marca_router;

                    $descripcion .= ($contrato->modelo_router == $request->modelo_router) ? '' : '<i class="fas fa-check text-success"></i> <b>Cambio Modelo Router</b> de ' . $contrato->modelo_router . ' a ' . $request->modelo_router . '<br>';
                    $contrato->modelo_router = $request->modelo_router;

                    $descripcion .= ($contrato->marca_antena == $request->marca_antena) ? '' : '<i class="fas fa-check text-success"></i> <b>Cambio Marca Antena</b> de ' . $contrato->marca_antena . ' a ' . $request->marca_antena . '<br>';
                    $contrato->marca_antena  = $request->marca_antena;

                    $descripcion .= ($contrato->modelo_antena == $request->modelo_antena) ? '' : '<i class="fas fa-check text-success"></i> <b>Cambio Modelo Antena</b> de ' . $contrato->modelo_antena . ' a ' . $request->modelo_antena . '<br>';
                    $contrato->modelo_antena = $request->modelo_antena;

                    $descripcion .= ($contrato->interfaz == $request->interfaz) ? '' : '<i class="fas fa-check text-success"></i> <b>Cambio de Interfaz</b> de ' . $contrato->interfaz . ' a ' . $request->interfaz . '<br>';
                    $contrato->interfaz = $request->interfaz;

                    if ($request->ap) {
                        $ap_new = AP::find($request->ap);
                        $ap_old = AP::find($contrato->ap);
                        if (isset($ap_new)) {
                            //  $descripcion .= ($contrato->ap == $ap_new->ap) ? '' : '<i class="fas fa-check text-success"></i> <b>Cambio Access Point</b> de '.$ap_old->nombre.' a '.$ap_new->nombre.'<br>';
                            $contrato->ap   = $request->ap;
                        }
                    }

                    if ($contrato->nodo) {
                        $nodo_old = Nodo::find($contrato->nodo);

                        if (isset($ap_new->nodo)) {
                            $nodo_new = Nodo::find($ap_new->nodo)->nombre;
                        } else {
                            $nodo_new = '';
                        }

                        if (isset($ap_new->nodo)) {
                            $contrato->nodo = $ap_new->nodo;
                            $descripcion .= ($contrato->nodo == $ap_new->nodo) ? '' : '<i class="fas fa-check text-success"></i> <b>Cambio Nodo</b> de ' . $nodo_old->nombre . ' a ' . $nodo_new . '<br>';
                        }
                    }

                    $contrato->puerto_conexion    = $request->puerto_conexion;
                    $contrato->cajanap_id         = $request->cajanap_id;
                    $contrato->cajanap_puerto     = $request->cajanap_puerto;
                    $contrato->plan_id            = $request->plan_id;
                    $contrato->usuario            = $request->usuario;
                    $contrato->password           = $request->password;
                    $contrato->direccion_local_address = $request->direccion_local_address;
                    $contrato->local_address_new       = $request->local_address_new;
                    $contrato->profile                 = $request->profile;
                    $contrato->local_adress_pppoe      = $ppoe_local_adress;
                    $contrato->simple_queue       = $request->simple_queue;
                    $contrato->conexion           = $request->conexion;
                    $contrato->latitude           = $request->latitude;
                    $contrato->longitude          = $request->longitude;
                    if ($request->factura_individual) {
                        $contrato->factura_individual = $request->factura_individual;
                    }
                    $contrato->servicio_tv             = $request->servicio_tv;
                    $contrato->contrato_permanencia    = $request->contrato_permanencia;
                    $contrato->serial_onu              = $request->serial_onu;
                    $contrato->linea                   = $request->linea;
                    $contrato->estrato                  = $request->estrato;
                    $contrato->servicio                = $this->normaliza($servicio) . '-' . $request->nro;
                    $contrato->server_configuration_id = $mikrotik->id;
                    $contrato->descuento               = $request->descuento;
                    $contrato->vendedor                = $request->vendedor;
                    $contrato->canal                   = $request->canal;
                    $contrato->address_street          = $request->address_street;
                    $contrato->tecnologia              = $request->tecnologia;
                    $contrato->tipo_contrato           = $request->tipo_contrato;
                    $contrato->iva_factura             = $request->iva_factura; //es el iva al plan de internet.
                    $contrato->observaciones           = $request->observaciones;
                    $contrato->usuario_wifi            = $request->usuario_wifi;
                    $contrato->contrasena_wifi         = $request->contrasena_wifi;
                    $contrato->ip_receptora            = $request->ip_receptora;
                    $contrato->puerto_receptor         = $request->puerto_receptor;
                    $contrato->tipo_moden              = $request->tipo_moden;
                    $contrato->serial_moden            = $request->serial_moden;
                    $contrato->descuento_pesos         = $request->descuento_pesos;
                    $contrato->fact_primer_mes         = $request->fact_primer_mes;
                    $contrato->fecha_hasta_desc        = isset($request->fecha_hasta_desc) ? $request->fecha_hasta_desc : null;

                    if($request->change_cliente == 1){
                        $contrato->client_id               = $request->new_contacto_contrato;
                    }

                    //Validacion para cambiar todas las facturas_contratos de nro al nuevo ingresado
                    if ($request->nro != $contrato->nro) {

                        $nro_contrato = $request->nro;
                        $existe = Contrato::where('nro', $nro_contrato)->count();
                        while ($existe > 0) {
                            $nro_contrato++;
                            $existe = Contrato::where('nro', $nro_contrato)->count();
                        }

                        $factura_contratos = DB::table('facturas_contratos')->where('contrato_nro', $contrato->nro)->get();
                        foreach ($factura_contratos as $factura_contrato) {
                            DB::table('facturas_contratos')->where('id', $factura_contrato->id)->update([
                                'contrato_nro' => $request->nro
                            ]);
                        }

                        $contrato->nro                 = $nro_contrato;
                    }

                    if ($request->rd_item_vencimiento) {
                        $contrato->dt_item_hasta           = $request->dt_item_hasta;
                        $contrato->rd_item_vencimiento     = $request->rd_item_vencimiento;
                    } else {
                        $contrato->rd_item_vencimiento = null;
                        $contrato->rd_item_vencimiento     = $request->rd_item_vencimiento;
                    }

                    if ($request->olt_sn_mac && $empresa->adminOLT != null && isset($request->state_olt_catv)) {

                        $contrato->olt_sn_mac          = $request->olt_sn_mac;
                        $curl = curl_init();

                        if ($request->state_olt_catv == 1) {
                            curl_setopt_array($curl, array(
                                CURLOPT_URL => $empresa->adminOLT . '/api/onu/enable_catv/' . $contrato->olt_sn_mac,
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_ENCODING => '',
                                CURLOPT_MAXREDIRS => 10,
                                CURLOPT_TIMEOUT => 0,
                                CURLOPT_FOLLOWLOCATION => true,
                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                CURLOPT_CUSTOMREQUEST => 'POST',
                                CURLOPT_HTTPHEADER => array(
                                    'X-token: ' . $empresa->smartOLT
                                ),
                            ));
                        } else if ($request->state_olt_catv == 0) {
                            $curl = curl_init();

                            curl_setopt_array($curl, array(
                                CURLOPT_URL => $empresa->adminOLT . '/api/onu/disable_catv/' . $contrato->olt_sn_mac,
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_ENCODING => '',
                                CURLOPT_MAXREDIRS => 10,
                                CURLOPT_TIMEOUT => 0,
                                CURLOPT_FOLLOWLOCATION => true,
                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                CURLOPT_CUSTOMREQUEST => 'POST',
                                CURLOPT_HTTPHEADER => array(
                                    'X-token: ' . $empresa->smartOLT
                                ),
                            ));
                        }

                        $response = curl_exec($curl);
                        $response = json_decode($response);

                        if (isset($response->status) && $response->status == false) {
                            return redirect('empresa/contratos')->with('danger', 'EL CONTRATO NO HA SIDO ACTUALIZADO POR QUE FALLÓ LA HABILITACIÓN DEL CATV');
                        } else {
                            if (isset($response->status) && $response->status == true && $request->state_olt_catv == 0) {
                                $contrato->state_olt_catv = 0;
                            } else {
                                $contrato->state_olt_catv = 1;
                            }
                        }
                    }

                    if (isset($request->factura_individual)) {
                        $contrato->factura_individual = $request->factura_individual;
                    }

                    // Handle tipo_suspension_no toggle
                    if ($request->tipo_suspension_no == 1) {
                        $contrato->tipo_nosuspension = 1;
                        $contrato->fecha_desde_nosuspension = $request->fecha_desde_nosuspension;
                        $contrato->fecha_hasta_nosuspension = $request->fecha_hasta_nosuspension;
                    } else {
                        $contrato->tipo_nosuspension = 0;
                        $contrato->fecha_desde_nosuspension = null;
                        $contrato->fecha_hasta_nosuspension = null;
                    }

                    if ($request->oficina) {
                        $contrato->oficina = $request->oficina;
                    }

                    if ($request->contrato_permanencia_meses) {
                        $contrato->contrato_permanencia_meses = $request->contrato_permanencia_meses;
                    }

                    if ($request->costo_reconexion) {
                        $contrato->costo_reconexion = $request->costo_reconexion;
                    } else {
                        $contrato->costo_reconexion = 0;
                    }

                    ### DOCUMENTOS ADJUNTOS ###

                    if ($request->referencia_a) {
                        $contrato->referencia_a = $request->referencia_a;
                        if ($request->adjunto_a) {
                            $file = $request->file('adjunto_a');
                            $nombre =  $file->getClientOriginalName();
                            Storage::disk('documentos')->put($nombre, \File::get($file));
                            $contrato->adjunto_a = $nombre;
                        }
                    }
                    if ($request->referencia_b) {
                        $contrato->referencia_b = $request->referencia_b;
                        if ($request->adjunto_b) {
                            $file = $request->file('adjunto_b');
                            $nombre =  $file->getClientOriginalName();
                            Storage::disk('documentos')->put($nombre, \File::get($file));
                            $contrato->adjunto_b = $nombre;
                        }
                    }
                    if ($request->referencia_c) {
                        $contrato->referencia_c = $request->referencia_c;
                        if ($request->adjunto_c) {
                            $file = $request->file('adjunto_c');
                            $nombre =  $file->getClientOriginalName();
                            Storage::disk('documentos')->put($nombre, \File::get($file));
                            $contrato->adjunto_c = $nombre;
                        }
                    }
                    if ($request->referencia_d) {
                        $contrato->referencia_d = $request->referencia_d;
                        if ($request->adjunto_d) {
                            $file = $request->file('adjunto_d');
                            $nombre =  $file->getClientOriginalName();
                            Storage::disk('documentos')->put($nombre, \File::get($file));
                            $contrato->adjunto_d = $nombre;
                        }
                    }

                    ### DOCUMENTOS ADJUNTOS ###

                    if(isset($request->pago_siigo_contrato) && $request->pago_siigo_contrato == 1){
                        $contrato->pago_siigo_contrato = 1;
                    } else {
                        $contrato->pago_siigo_contrato = 0;
                    }

                    $contrato->save();

                    //  //Opcion de crear factrua con prorrateo
                    // if (Auth::user()->empresa()->contrato_factura_pro == 1) {
                    //     $this->createFacturaProrrateo($contrato);
                    // }

                    /*REGISTRO DEL LOG*/
                    if (!is_null($descripcion)) {
                        $movimiento = new MovimientoLOG;
                        $movimiento->contrato    = $id;
                        $movimiento->modulo      = 5;
                        $movimiento->descripcion = $descripcion;
                        $movimiento->created_by  = Auth::user()->id;
                        $movimiento->empresa     = Auth::user()->empresa;
                        $movimiento->save();
                    }

                    $mensaje = 'SE HA MODIFICADO EL CONTRATO DE SERVICIOS SATISFACTORIAMENTE';
                    return redirect('empresa/contratos/' . $id)->with('success', $mensaje);
                } else {
                    return redirect('empresa/contratos')->with('danger', 'EL CONTRATO DE SERVICIOS NO HA SIDO ACTUALIZADO');
                }
            } else {
                $contrato->servicio             = $this->normaliza($servicio) . '-' . $request->nro;
                $contrato->grupo_corte          = $request->grupo_corte;
                $contrato->facturacion          = $request->facturacion;
                $contrato->latitude             = $request->latitude;
                $contrato->longitude            = $request->longitude;
                $contrato->contrato_permanencia = $request->contrato_permanencia;
                $contrato->servicio_tv          = $request->servicio_tv;
                $contrato->fecha_suspension     = $request->fecha_suspension;
                $contrato->descuento            = $request->descuento;
                $contrato->vendedor             = $request->vendedor;
                $contrato->linea                   = $request->linea;
                $contrato->canal                = $request->canal;
                $contrato->nro                  = $request->nro;
                $contrato->address_street       = $request->address_street;
                $contrato->tipo_contrato        = $request->tipo_contrato;
                $contrato->observaciones        = $request->observaciones;
                $contrato->usuario_wifi            = $request->usuario_wifi;
                $contrato->contrasena_wifi         = $request->contrasena_wifi;
                $contrato->ip_receptora            = $request->ip_receptora;
                $contrato->puerto_receptor         = $request->puerto_receptor;
                $contrato->fecha_hasta_desc        = $request->fecha_hasta_desc;

                //campos al quitar una mikrotik
                $contrato->server_configuration_id = isset($request->server_configuration_id) ? $request->server_configuration_id : null;
                $contrato->plan_id = isset($request->plan_id) ? $request->plan_id : null;
                $contrato->conexion = isset($request->conexion) ? $request->conexion : null;
                $contrato->local_address = isset($request->local_address) ? $request->local_address : null;
                $contrato->ip = isset($request->ip) ? $request->ip : null;
                $contrato->tecnologia = isset($request->tecnologia) ? $request->tecnologia : null;
                $contrato->mac_address = isset($request->mac_address) ? $request->mac_address : null;

                if ($request->olt_sn_mac && $empresa->adminOLT != null && isset($request->state_olt_catv)) {

                    $contrato->olt_sn_mac          = $request->olt_sn_mac;
                    $curl = curl_init();

                    if ($request->state_olt_catv == 1) {
                        curl_setopt_array($curl, array(
                            CURLOPT_URL => $empresa->adminOLT . '/api/onu/enable_catv/' . $contrato->olt_sn_mac,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING => '',
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_TIMEOUT => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST => 'POST',
                            CURLOPT_HTTPHEADER => array(
                                'X-token: ' . $empresa->smartOLT
                            ),
                        ));
                    } else if ($request->state_olt_catv == 0) {
                        $curl = curl_init();

                        curl_setopt_array($curl, array(
                            CURLOPT_URL => $empresa->adminOLT . '/api/onu/disable_catv/' . $contrato->olt_sn_mac,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING => '',
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_TIMEOUT => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST => 'POST',
                            CURLOPT_HTTPHEADER => array(
                                'X-token: ' . $empresa->smartOLT
                            ),
                        ));
                    }

                    $response = curl_exec($curl);
                    $response = json_decode($response);

                    if (isset($response->status) && $response->status == false) {
                        return redirect('empresa/contratos')->with('danger', 'EL CONTRATO NO HA SIDO ACTUALIZADO POR QUE FALLÓ LA HABILITACIÓN DEL CATV');
                    } else {
                        if (isset($response->status) && $response->status == true && $request->state_olt_catv == 0) {
                            $contrato->state_olt_catv = 0;
                        } else {
                            $contrato->state_olt_catv = 1;
                        }
                    }
                }

                if ($request->factura_individual) {
                    $contrato->factura_individual   = $request->factura_individual;
                }

                if ($request->oficina) {
                    $contrato->oficina = $request->oficina;
                }

                if ($request->contrato_permanencia_meses) {
                    $contrato->contrato_permanencia_meses = $request->contrato_permanencia_meses;
                }

                if ($request->costo_reconexion) {
                    $contrato->costo_reconexion = $request->costo_reconexion;
                } else {
                    $contrato->costo_reconexion = 0;
                }

                ### DOCUMENTOS ADJUNTOS ###
                if ($request->referencia_a) {
                    $contrato->referencia_a = $request->referencia_a;
                    if ($request->adjunto_a) {
                        $file = $request->file('adjunto_a');
                        $nombre =  $file->getClientOriginalName();
                        Storage::disk('documentos')->put($nombre, \File::get($file));
                        $contrato->adjunto_a = $nombre;
                    }
                }
                if ($request->referencia_b) {
                    $contrato->referencia_b = $request->referencia_b;
                    if ($request->adjunto_b) {
                        $file = $request->file('adjunto_b');
                        $nombre =  $file->getClientOriginalName();
                        Storage::disk('documentos')->put($nombre, \File::get($file));
                        $contrato->adjunto_b = $nombre;
                    }
                }
                if ($request->referencia_c) {
                    $contrato->referencia_c = $request->referencia_c;
                    if ($request->adjunto_c) {
                        $file = $request->file('adjunto_c');
                        $nombre =  $file->getClientOriginalName();
                        Storage::disk('documentos')->put($nombre, \File::get($file));
                        $contrato->adjunto_c = $nombre;
                    }
                }
                if ($request->referencia_d) {
                    $contrato->referencia_d = $request->referencia_d;
                    if ($request->adjunto_d) {
                        $file = $request->file('adjunto_d');
                        $nombre =  $file->getClientOriginalName();
                        Storage::disk('documentos')->put($nombre, \File::get($file));
                        $contrato->adjunto_d = $nombre;
                    }
                }
                ### DOCUMENTOS ADJUNTOS ###

                $contrato->creador = Auth::user()->nombres;
                $contrato->save();

                return redirect('empresa/contratos/' . $contrato->id)->with('success', 'SE HA ACTUALIZADO SATISFACTORIAMENTE EL CONTRATO DE SERVICIOS');
            }
        }
        return redirect('empresa/contratos')->with('danger', 'EL CONTRATO DE SERVICIOS NO HA ENCONTRADO');
    }

    public function show($id)
    {
        $this->getAllPermissions(Auth::user()->id);
        view()->share(['middel' => true]);
        $inventario = false;


        // Buscar por id o por nro según el parámetro recibido
        $baseQuery = Contrato::
        join('contactos as c', 'c.id', '=', 'contracts.client_id')
        ->select('contracts.*', 'contracts.status as cs_status', 'c.nombre', 'c.apellido1', 'c.apellido2', 'c.nit', 'c.celular', 'c.telefono1', 'c.direccion', 'c.barrio', 'c.email', 'c.id as id_cliente', 'contracts.marca_router', 'contracts.modelo_router', 'contracts.marca_antena', 'contracts.modelo_antena', 'contracts.ip',
         'contracts.grupo_corte', 'contracts.adjunto_a', 'contracts.referencia_a', 'contracts.adjunto_b', 'contracts.referencia_b', 'contracts.adjunto_c', 'contracts.referencia_c', 'contracts.adjunto_d', 'contracts.referencia_d', 'contracts.simple_queue', 'contracts.latitude', 'contracts.longitude', 'contracts.servicio_tv', 'contracts.contrato_permanencia', 'contracts.contrato_permanencia_meses',
         'contracts.serial_onu', 'contracts.descuento', 'contracts.vendedor', 'contracts.canal', 'contracts.address_street', 'contracts.tecnologia', 'contracts.costo_reconexion', 'contracts.tipo_contrato', 'contracts.observaciones')
         ->where('contracts.empresa', Auth::user()->empresa);

        // Si el parámetro es numérico, intentar buscar por id primero
        if (is_numeric($id)) {
            $contrato = (clone $baseQuery)->where('contracts.id', $id)->first();
            // Si no se encuentra por id, buscar por nro
            if (!$contrato) {
                $contrato = (clone $baseQuery)->where('contracts.nro', $id)->first();
            }
        } else {
            // Si no es numérico, buscar directamente por nro
            $contrato = (clone $baseQuery)->where('contracts.nro', $id)->first();
        }

        if ($contrato) {
            if ($contrato->servicio_tv) {
                $inventario = Inventario::where('id', $contrato->servicio_tv)->where('empresa', Auth::user()->empresa)->first();
            }
            if ($contrato->servicio_otro) {
                $servicio_otro = Inventario::where('id', $contrato->servicio_otro)->where('empresa', Auth::user()->empresa)->first();
            } else {
                $servicio_otro = null;
            }
            view()->share(['icon' => 'fas fa-file-contract', 'title' => 'Detalles Contrato: ' . $contrato->nro]);
            return view('contratos.show')->with(compact('contrato', 'inventario', 'servicio_otro'));
        }
        return redirect('empresa/contratos')->with('danger', 'EL CONTRATO DE SERVICIOS NO HA SIDO ENCONTRADO');
    }

    public function destroy($id)
    {
        $this->getAllPermissions(Auth::user()->id);
        $contrato = Contrato::find($id);
        $empresa  = Auth::user()->empresaObj;
        if ($contrato) {
            if ($contrato->server_configuration_id) {
                $mikrotik = Mikrotik::where('id', $contrato->server_configuration_id)->first();
                $API = new RouterosAPI();
                $API->port = $mikrotik->puerto_api;
                //$API->debug = true;

                if($empresa->consultas_mk == 1){
                if ($API->connect($mikrotik->ip, $mikrotik->usuario, $mikrotik->clave)) {
                    if ($contrato->conexion == 1) {
                        //OBTENEMOS AL CONTRATO MK
                        $mk_user = $API->comm(
                            "/ppp/secret/getall",
                            array(
                                "?remote-address" => $contrato->ip,
                            )
                        );

                        if ($mk_user) {
                            // REMOVEMOS EL SECRET
                            $API->comm(
                                "/ppp/secret/remove",
                                array(
                                    ".id" => $mk_user[0][".id"],
                                )
                            );
                        }

                        //OBTENEMOS EL ID DEL NOMBRE DEL CLIENTE
                        $id_simple = $API->comm(
                            "/queue/simple/getall",
                            array(
                                "?target" => $contrato->ip . '/32'
                            )
                        );

                        if ($id_simple) {
                            // REMOVEMOS LA COLA SIMPLE
                            $API->comm(
                                "/queue/simple/remove",
                                array(
                                    ".id" => $id_simple[0][".id"],
                                )
                            );
                        }
                    }

                    if ($contrato->conexion == 2) {
                        $name = $API->comm(
                            "/ip/dhcp-server/lease/getall",
                            array(
                                "?address" => $contrato->ip,
                            )
                        );

                        if ($name) {
                            // REMOVEMOS EL IP DHCP
                            $API->comm(
                                "/ip/dhcp-server/lease/remove",
                                array(
                                    ".id" => $name[0][".id"],
                                )
                            );
                        }

                        //OBTENEMOS EL ID DEL NOMBRE DEL CLIENTE
                        $id_simple = $API->comm(
                            "/queue/simple/getall",
                            array(
                                "?target" => $contrato->ip . '/32'
                            )
                        );
                        // REMOVEMOS LA COLA SIMPLE
                        if ($id_simple) {
                            $API->comm(
                                "/queue/simple/remove",
                                array(
                                    ".id" => $id_simple[0][".id"],
                                )
                            );
                        }
                    }

                    if ($contrato->conexion == 3) {
                        //OBTENEMOS AL CONTRATO MK
                        $mk_user = $API->comm(
                            "/ip/arp/getall",
                            array(
                                "?address" => $contrato->ip,
                            )
                        );
                        if ($mk_user) {
                            // REMOVEMOS EL IP ARP
                            $API->comm(
                                "/ip/arp/remove",
                                array(
                                    ".id" => $mk_user[0][".id"],
                                )
                            );
                        }
                        //OBTENEMOS EL ID DEL NOMBRE DEL CLIENTE
                        $id_simple = $API->comm(
                            "/queue/simple/getall",
                            array(
                                "?target" => $contrato->ip . '/32'
                            )
                        );
                        // REMOVEMOS LA COLA SIMPLE
                        if ($id_simple) {
                            $API->comm(
                                "/queue/simple/remove",
                                array(
                                    ".id" => $id_simple[0][".id"],
                                )
                            );
                        }

                        if ($contrato->ip_new) {
                            $mk_user = $API->comm(
                                "/ip/arp/getall",
                                array(
                                    "?comment" => $contrato->servicio . '-' . $contrato->nro,
                                )
                            );

                            if ($mk_user) {
                                // REMOVEMOS EL IP ARP
                                $API->comm(
                                    "/ip/arp/remove",
                                    array(
                                        ".id" => $mk_user[0][".id"],
                                    )
                                );
                            }
                            //OBTENEMOS EL ID DEL NOMBRE DEL CLIENTE
                            $id_simple = $API->comm(
                                "/queue/simple/getall",
                                array(
                                    "?target" => $contrato->ip_new . '/32'
                                )
                            );
                            // REMOVEMOS LA COLA SIMPLE
                            if ($id_simple) {
                                $API->comm(
                                    "/queue/simple/remove",
                                    array(
                                        ".id" => $id_simple[0][".id"],
                                    )
                                );
                            }
                        }
                    }

                    $API->write('/ip/firewall/address-list/print', TRUE);
                    $ARRAYS = $API->read();

                    $API->write('/ip/firewall/address-list/print', false);
                    $API->write('?address=' . $contrato->ip, false);
                    $API->write('=.proplist=.id');
                    $ARRAYS = $API->read();

                    if (count($ARRAYS) > 0) {
                        //REMOVEMOS EL ID DE LA ADDRESS LIST
                        $API->write('/ip/firewall/address-list/remove', false);
                        $API->write('=.id=' . $ARRAYS[0]['.id']);
                        $READ = $API->read();
                    }

                    $API->disconnect();
                    Ping::where('contrato', $contrato->id)->delete();

                    $cliente = Contacto::find($contrato->client_id);
                    $cliente->fecha_contrato = Carbon::now();
                    $cliente->save();
                    $contrato->delete();

                    $mensaje = 'SE HA ELIMINADO EL CONTRATO DE SERVICIOS SATISFACTORIAMENTE';
                    return redirect('empresa/contratos')->with('success', $mensaje);
                } else {
                    $mensaje = 'NO SE HA PODIDO ELIMINAR EL CONTRATO DE SERVICIOS POR QUE NO SE LOGRO CONECTAR A LA MIKROTIK CON LA IP: ' . $mikrotik->ip . ' EL USUARIO: ' . $mikrotik->usuario . ' Y LA CLAVE: ' . $mikrotik->clave;
                    return redirect('empresa/contratos')->with('danger', $mensaje);
                }
            }else{
                Ping::where('contrato', $contrato->id)->delete();

                $cliente = Contacto::find($contrato->client_id);
                $cliente->fecha_contrato = Carbon::now();
                $cliente->save();
                $contrato->delete();
                $mensaje = 'SE HA ELIMINADO EL CONTRATO DE SERVICIOS SATISFACTORIAMENTE';
                return redirect('empresa/contratos')->with('success', $mensaje);
            }
        } else {
                $cliente = Contacto::find($contrato->client_id);
                $cliente->fecha_contrato = Carbon::now();
                $cliente->save();
                $contrato->delete();
                $mensaje = 'SE HA ELIMINADO EL CONTRATO DE SERVICIOS SATISFACTORIAMENTE';
                return redirect('empresa/contratos')->with('success', $mensaje);
        }
        }
        return redirect('empresa/contratos')->with('danger', 'EL CONTRATO DE SERVICIOS NO HA ENCONTRADO');
    }

    public function destroy_to_networksoft($id)
    {
        $contrato = Contrato::find($id);
        if ($contrato) {
            Ping::where('contrato', $contrato->id)->delete();
            $contrato->delete();
            $mensaje = 'SE HA ELIMINADO EL CONTRATO DE SERVICIOS SATISFACTORIAMENTE';
            return redirect('empresa/contratos')->with('success', $mensaje);
        }
        return redirect('empresa/contratos')->with('danger', 'EL CONTRATO DE SERVICIOS NO HA ENCONTRADO');
    }

    public function state($id)
    {
        $this->getAllPermissions(Auth::user()->id);
        $contrato = Contrato::find($id);

        if (!$contrato) {
            return redirect('empresa/contratos')->with('danger', 'EL CONTRATO DE SERVICIOS NO SE HA ENCONTRADO');
        }

        $empresa = Auth::user()->empresa();
        $old_state = $contrato->state;
        
        // 1. Determinar el nuevo estado objetivo
        // 1. Determinar el nuevo estado objetivo
        $new_state = ($old_state == 'enabled') ? 'disabled' : 'enabled';
        
        $mikrotik_failed = false;
        $olt_executed = false;
        $descripcion = "";
        $type = 'success';
        $mensaje = 'EL CONTRATO NRO. ' . $contrato->nro . ' HA SIDO ' . ($new_state == 'enabled' ? 'Habilitado' : 'Deshabilitado');

        // 2. Lógica de Smart OLT
        if ($contrato->conexion == 2 && $empresa->queries_dhcp_smartolt == 1 && !empty($contrato->serial_onu)) {
            $olt_executed = true;
            $oltController = app('App\Http\Controllers\OltController');
            if ($new_state == 'enabled') {
                $oltController->enableOnu($contrato->serial_onu);
            } else {
                $oltController->disableOnu($contrato->serial_onu);
            }
            $descripcion .= '<i class="fas fa-check text-success"></i> <b>Cambiado en OLT</b> a ' . ($new_state == 'enabled' ? 'Habilitado' : 'Deshabilitado') . '<br>';
        }else

        // 3. Lógica de Mikrotik
        if ($contrato->plan_id && $empresa->consultas_mk == 1 && $contrato->server_configuration_id) {
            $mikrotik = Mikrotik::find($contrato->server_configuration_id);

            if ($mikrotik) {
                $API = new RouterosAPI();
                $API->port = $mikrotik->puerto_api;

                if ($API->connect($mikrotik->ip, $mikrotik->usuario, $mikrotik->clave)) {
                    $API->write('/ip/firewall/address-list/print', true);
                    $ARRAYS = $API->read();

                    switch ($new_state) {
                        case 'disabled':
                            $API->comm("/ip/firewall/address-list/add", [
                                "address" => $contrato->ip,
                                "comment" => $contrato->servicio,
                                "list" => 'morosos'
                            ]);

                            $API->write('/ip/firewall/address-list/print', false);
                            $API->write('?address=' . $contrato->ip, false);
                            $API->write("?list=ips_autorizadas", false);
                            $API->write('=.proplist=.id');
                            $ARRAYS = $API->read();
                            if (count($ARRAYS) > 0) {
                                $API->write('/ip/firewall/address-list/remove', false);
                                $API->write('=.id=' . $ARRAYS[0]['.id']);
                                $API->read();
                            }

                            if (isset($empresa->activeconn_secret) && $empresa->activeconn_secret == 1) {
                                if ($contrato->conexion == 1 && $contrato->usuario != null) {
                                    $API->write('/ppp/secret/print', false);
                                    $API->write('?name=' . $contrato->usuario, true);
                                    $ARRAYS = $API->read();
                                    if (count($ARRAYS) > 0) {
                                        $API->write('/ppp/secret/disable', false);
                                        $API->write('=numbers=' . $ARRAYS[0]['.id'], true);
                                        $API->read();
                                    }

                                    $API->write('/ppp/active/print', false);
                                    $API->write('?name=' . $contrato->usuario);
                                    $response = $API->read();
                                    if (isset($response['0']['.id'])) {
                                        $API->comm("/ppp/active/remove", [".id" => $response['0']['.id']]);
                                    } else {
                                        $API->write('/ppp/active/print', false);
                                        $API->write('?name=' . $contrato->nro);
                                        $response = $API->read();
                                        if (isset($response['0']['.id'])) {
                                            $API->comm("/ppp/active/remove", [".id" => $response['0']['.id']]);
                                        }
                                    }
                                }
                            }
                            $descripcion .= '<i class="fas fa-check text-success"></i> <b>Cambiado en Mikrotik</b> a Deshabilitado<br>';
                            break;

                        case 'enabled':
                            if (isset($empresa->activeconn_secret) && $empresa->activeconn_secret == 1) {
                                if ($contrato->conexion == 1 && $contrato->usuario != null) {
                                    $API->write('/ppp/secret/print', false);
                                    $API->write('?name=' . $contrato->usuario, true);
                                    $ARRAYS = $API->read();
                                    if (count($ARRAYS) > 0) {
                                        $API->write('/ppp/secret/enable', false);
                                        $API->write('=numbers=' . $ARRAYS[0]['.id'], true);
                                        $API->read();
                                    }
                                }
                            } else {
                                $API->write('/ip/firewall/address-list/print', false);
                                $API->write('?address=' . $contrato->ip, false);
                                $API->write("?list=morosos", false);
                                $API->write('=.proplist=.id');
                                $ARRAYS = $API->read();
                                if (count($ARRAYS) > 0) {
                                    $API->write('/ip/firewall/address-list/remove', false);
                                    $API->write('=.id=' . $ARRAYS[0]['.id']);
                                    $API->read();
                                }
                            }

                            $API->comm("/ip/firewall/address-list/add", [
                                "address" => $contrato->ip,
                                "list" => 'ips_autorizadas'
                            ]);
                            $descripcion .= '<i class="fas fa-check text-success"></i> <b>Cambiado en Mikrotik</b> a Habilitado<br>';
                            break;
                    }
                    $API->disconnect();
                } else {
                    $mikrotik_failed = true;
                }
            }
        }

        // 4. Procesamiento final, decidir si actualizar estado en DB
        if ($mikrotik_failed && !$olt_executed) {
            // Si falló mikrotik y no había OLT de respaldo al cual aplicarle, abortamos.
            $type = 'danger';
            $mensaje = 'EL CONTRATO NRO. ' . $contrato->nro . ' NO HA PODIDO SER ACTUALIZADO EN MIKROTIK';
            return back()->with($type, $mensaje);
        }

        // Si falló mikrotik pero SÍ había OLT, actualizamos el estado con una advertencia
        if ($mikrotik_failed) {
            $type = 'warning';
            $mensaje = 'EL CONTRATO NRO. ' . $contrato->nro . ' ACTUALIZADO EN OLT, PERO FALLÓ MIKROTIK';
        }

        // Actualizamos estado en base de datos
        $contrato->state = $new_state;
        $contrato->save();

        // Etiquetas automáticas: se aplica según el tipo de cambio de estado
        if ($new_state == 'disabled') {
            \App\Traits\AplicaEtiquetaAutomatica::aplicarEtiquetaAutomatica(
                $contrato->id,
                Auth::user()->empresa,
                \App\EtiquetaAutomaticaContrato::MODULO_CONTRATOS,
                \App\EtiquetaAutomaticaContrato::DESHABILITAR_MANUAL
            );
        }

        if ($descripcion == "") {
            $descripcion = '<i class="fas fa-check text-success"></i> <b>Cambio de Status</b> a ' . ($new_state == 'enabled' ? 'Habilitado' : 'Deshabilitado') . '<br>';
        }

        // Registro en LOG de Movimientos
        $movimiento = new MovimientoLOG;
        $movimiento->contrato    = $id;
        $movimiento->modulo      = 5;
        $movimiento->descripcion = $descripcion;
        $movimiento->created_by  = Auth::user()->id;
        $movimiento->empresa     = Auth::user()->empresa;
        $movimiento->save();

        // Registro en CRM
        $crm = new CRM();
        $crm->cliente = $contrato->client_id;
        $crm->servidor = $contrato->server_configuration_id ?? '';
        $crm->grupo_corte = $contrato->grupo_corte ?? '';
        $crm->estado = 0;
        if ($lastFact = $contrato->lastFactura()) {
            $crm->factura = $lastFact->id;
        }
        $crm->save();

        return back()->with($type, $mensaje);
    }

    public function state_oltcatv($id)
    {
        $this->getAllPermissions(Auth::user()->id);
        $contrato = Contrato::find($id);
        $empresa = Empresa::Find($contrato->empresa);

        if ($contrato->state_olt_catv == true) {
            $contrato->state_olt_catv = false;
        } else {
            $contrato->state_olt_catv = true;
        }

        if ($contrato->state_olt_catv == true) {
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $empresa->adminOLT . '/api/onu/enable_catv/' . $contrato->olt_sn_mac,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_HTTPHEADER => array(
                    'X-token: ' . $empresa->smartOLT
                ),
            ));

            $response = curl_exec($curl);
            $response = json_decode($response);

            if (isset($response->status) && $response->status == true) {
                $message = 'HABILITADO';
                $contrato->save();

                $descripcion = '<i class="fas fa-check text-success"></i> <b>Cambio de Status</b> de deshabilitado a habilitado CATV<br>';

                /*REGISTRO DEL LOG*/
                $movimiento = new MovimientoLOG;
                $movimiento->contrato    = $id;
                $movimiento->modulo      = 5;
                $movimiento->descripcion = $descripcion;
                $movimiento->created_by  = Auth::user()->id;
                $movimiento->empresa     = Auth::user()->empresa;
                $movimiento->save();

                return back()->with('success', 'EL CONTRATO NRO. ' . $contrato->nro . ' HA SIDO MODIFICADO EN SU CATV A ' . $message);
            } else {
                return back()->with('danger', 'EL CONTRATO NRO. ' . $contrato->nro . ' NO HA SIDO MODIFICADO POR UN ERROR');
            }

            curl_close($curl);
        } else {

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $empresa->adminOLT . '/api/onu/disable_catv/' . $contrato->olt_sn_mac,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_HTTPHEADER => array(
                    'X-token: ' . $empresa->smartOLT
                ),
            ));

            $response = curl_exec($curl);
            $response = json_decode($response);

            curl_close($curl);

            if (isset($response->status) && $response->status == true) {
                $message = 'DESHABILITADO';
                $contrato->save();

                $descripcion = '<i class="fas fa-check text-success"></i> <b>Cambio de Status</b> de habilitado a deshabilitado CATV<br>';

                /*REGISTRO DEL LOG*/
                $movimiento = new MovimientoLOG;
                $movimiento->contrato    = $id;
                $movimiento->modulo      = 5;
                $movimiento->descripcion = $descripcion;
                $movimiento->created_by  = Auth::user()->id;
                $movimiento->empresa     = Auth::user()->empresa;
                $movimiento->save();

                return back()->with('success', 'EL CONTRATO NRO. ' . $contrato->nro . ' HA SIDO MODIFICADO EN SU CATV A ' . $message);
            } else {
                return back()->with('danger', 'EL CONTRATO NRO. ' . $contrato->nro . ' NO HA SIDO MODIFICADO POR UN ERROR');
            }
        }
    }


    public function exportar(Request $request)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $this->getAllPermissions(Auth::user()->id);
        $objPHPExcel = new PHPExcel();
        $tituloReporte = "Reporte de Contratos";
        $titulosColumnas = array(
            'Nro',
            'Cliente',
            'Identificacion',
            'Celular',
            'Telefono1',
            'Telefono2',
            'Correo Electronico',
            'Direccion',
            'Barrio',
            'Corregimiento/Vereda',
            'Estrato',
            'Plan TV',
            'Plan Internet',
            'Servidor',
            'Direccion IP',
            'Direccion MAC',
            'Interfaz',
            'Serial ONU',
            'SN_MAC',
            'Estado',
            'Estado de CATV',
            'Grupo de Corte',
            'Facturacion',
            'Costo Reconexion',
            'Municipio',
            'Tipo Contrato',
            'Iva',
            'Descuento (%)',
            'Descuento ($)',
            'Plan Internet',
            'Valor Plan Internet',
            'Plan TV',
            'Otros Items',
            'Deuda Facturas',
            'Pagar / Mes',
            'Etiqueta',
            'Fecha Desconexion',
            'Linea',
            'Latitud',
            'Longitud',
            'Fecha Creacion',
            'Creador',
            'Ultimo pago',
            'Desactivado',
            'Usuario',
            'Contrasena',
            'Perfil'
        );

        $letras = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU');

        $objPHPExcel->getProperties()->setCreator("Sistema") // Nombre del autor
            ->setLastModifiedBy("Sistema") //Ultimo usuario que lo modific171717
            ->setTitle("Reporte Excel Contratos") // Titulo
            ->setSubject("Reporte Excel Contratos") //Asunto
            ->setDescription("Reporte de Contratos") //Descripci171717n
            ->setKeywords("reporte Contratos") //Etiquetas
            ->setCategory("Reporte excel"); //Categorias
        // Se combinan las celdas A1 hasta D1, para colocar ah171717 el titulo del reporte
        $objPHPExcel->setActiveSheetIndex(0)
            ->mergeCells('A1:AU1');
        // Se agregan los titulos del reporte
        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1', $tituloReporte);
        // Titulo del reporte
        $objPHPExcel->setActiveSheetIndex(0)
            ->mergeCells('A1:AU1');
        // Se agregan los titulos del reporte
        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1', 'Reporte Contratos - Fecha ' . date('d-m-Y')); // Titulo del reporte

        $estilo = array('font'  => array('bold'  => true, 'size'  => 12, 'name'  => 'Times New Roman'), 'alignment' => array(
            'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
        ));
        $objPHPExcel->getActiveSheet()->getStyle('A1:AU1')->applyFromArray($estilo);
        $estilo = array('fill' => array(
            'type' => PHPExcel_Style_Fill::FILL_SOLID,
            'color' => array('rgb' => 'd08f50')
        ));
        $objPHPExcel->getActiveSheet()->getStyle('A2:AU2')->applyFromArray($estilo);

        $estilo = array(
            'fill' => array(
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('rgb' => substr(Auth::user()->empresa()->color, 1))
            ),
            'font'  => array(
                'bold'  => true,
                'size'  => 12,
                'name'  => 'Times New Roman',
                'color' => array(
                    'rgb' => 'FFFFFF'
                ),
            ),
            'alignment' => array(
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER
            )
        );
        $objPHPExcel->getActiveSheet()->getStyle('A2:AU2')->applyFromArray($estilo);

        for ($i = 0; $i < count($titulosColumnas); $i++) {
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($letras[$i] . '2', utf8_decode($titulosColumnas[$i]));
        }

        $i = 3;
        $letra = 0;

        $barrios = [];
        if(isset($request->barrio)){
            $barrios = array_map('intval', explode(',', $request->barrio));
        }


        $contratos = Contrato::query()
            ->select(
                'contracts.*',
                'contactos.id as c_id',
                'contactos.nombre as c_nombre',
                'contactos.apellido1 as c_apellido1',
                'contactos.apellido2 as c_apellido2',
                'contactos.nit as c_nit',
                'contactos.celular as c_celular',
                'contactos.telefono1 as c_telefono1',
                'contactos.telefono2 as c_telefono2',
                'contactos.email as c_email',
                'contactos.barrio as c_barrio',
                'contactos.vereda as c_vereda',
                'contactos.direccion as c_direccion',
                'contracts.estrato',
                'contactos.fk_idmunicipio as c_municipio',
                'contracts.latitude as c_latitude',
                'contracts.longitude as c_longitude',
                'municipios.nombre as c_nombre_municipio',
                'e.nombre as c_etiqueta',
                'barrio.nombre as nombre_barrio'
            )
            ->join('contactos', 'contracts.client_id', '=', 'contactos.id')
            ->leftJoin('municipios', 'contactos.fk_idmunicipio', '=', 'municipios.id')
            ->leftJoin('etiquetas as e', 'e.id', '=', 'contracts.etiqueta_id')
            ->leftJoin('barrios as barrio', 'barrio.id', 'contactos.barrio_id')
            ->where('contracts.empresa', Auth::user()->empresa)
            ->where('contracts.status', '!=', 0)
            ->orderBy('nro', 'desc');

        if ($request->client_id != null) {
            $contratos->where(function ($query) use ($request) {
                $query->orWhere('contracts.client_id', $request->client_id);
            });
        }
        if ($request->catv != null) {
            $contratos->where(function ($query) use ($request) {
                $query->orWhereNotNull('contracts.olt_sn_mac')
                    ->whereIn('contracts.state_olt_catv', [$request->catv]);
            });
        }
        if ($request->plan != null) {
            $contratos->where(function ($query) use ($request) {
                $query->orWhere('contracts.plan_id', $request->plan);
            });
        }
        if ($request->ip != null) {
            $contratos->where(function ($query) use ($request) {
                $query->orWhere('contracts.ip', 'like', "%{$request->ip}%");
            });
        }

        if ($request->mac != null) {
            $contratos->where(function ($query) use ($request) {
                $query->orWhere('contracts.mac_address', 'like', "%{$request->mac}%");
            });
        }
        if ($request->grupo_cort != null) {
            $contratos->where(function ($query) use ($request) {
                $query->orWhere('contracts.grupo_corte', $request->grupo_cort);
            });
        }
        if ($request->state != null) {
            $contratos->where(function ($query) use ($request) {
                $query->orWhere('contracts.state', $request->state)
                    ->whereIn('contracts.status', [1]);
            });
        }
        if ($request->conexion_s != null) {
            $contratos->where(function ($query) use ($request) {
                $query->orWhere('contracts.conexion', $request->conexion_s);
            });
        }
        if ($request->server_configuration_id_s != null) {
            $contratos->where(function ($query) use ($request) {
                $query->orWhere('contracts.server_configuration_id', $request->server_configuration_id_s);
            });
        }
        if ($request->nodo_s != null) {
            $contratos->where(function ($query) use ($request) {
                $query->orWhere('contracts.nodo', $request->nodo_s);
            });
        }
        if ($request->ap_s != null) {
            $contratos->where(function ($query) use ($request) {
                $query->orWhere('contracts.ap', $request->ap_s);
            });
        }
        if ($request->direccion != null) {
            $contratos->where(function ($query) use ($request) {
                $query->orWhere('contactos.direccion', 'like', "%{$request->direccion}%");
            });
        }

        if ($request->direccion_precisa != null) {
            $contratos->where(function ($query) use ($request) {
                $query->orWhere('contracts.address_street', 'like', "%{$request->c_direccion_precisa}%");
                $query->orWhere('contactos.direccion', 'like', "%{$request->c_direccion_precisa}%");
            });
        }
        if($request->barrio != null){
            $contratos->where(function ($query) use ($request,$barrios) {
                $query->orWhereIn('contactos.barrio_id',$barrios);
            });
        }
        if ($request->celular != null) {
            $contratos->where(function ($query) use ($request) {
                $query->orWhere('contactos.celular', 'like', "%{$request->celular}%");
            });
        }
        if ($request->email != null) {
            $contratos->where(function ($query) use ($request) {
                $query->orWhere('contactos.email', 'like', "%{$request->email}%");
            });
        }
        if ($request->vendedor != null) {
            $contratos->where(function ($query) use ($request) {
                $query->orWhere('contracts.vendedor', $request->vendedor);
            });
        }
        if ($request->canal != null) {
            $contratos->where(function ($query) use ($request) {
                $query->orWhere('contracts.canal', $request->canal);
            });
        }
        if ($request->tecnologia_s != null) {
            $contratos->where(function ($query) use ($request) {
                $query->orWhere('contracts.tecnologia', $request->tecnologia_s);
            });
        }
        if ($request->facturacion_s != null) {
            $contratos->where(function ($query) use ($request) {
                $query->orWhere('contracts.facturacion', $request->facturacion_s);
            });
        }
        if ($request->desde != null) {
            $contratos->where(function ($query) use ($request) {
                $query->whereDate('contracts.created_at', '>=', Carbon::parse($request->desde)->format('Y-m-d'));
            });
        }

        if ($request->hasta != null) {
            $contratos->where(function ($query) use ($request) {
                $query->whereDate('contracts.created_at', '<=', Carbon::parse($request->hasta)->format('Y-m-d'));
            });
        }

        if ($request->tipo_contrato != null) {
            $contratos->where(function ($query) use ($request) {
                $query->orWhere('contracts.tipo_contrato', $request->tipo_contrato);
            });
        }
        if ($request->nro != null) {
            $contratos->where(function ($query) use ($request) {
                $query->orWhere('contracts.nro', 'like', "%{$request->nro}%");
            });
        }
        if ($request->etiqueta != null) {
            $contratos->where(function ($query) use ($request) {
                $query->orWhere('contracts.etiqueta_id', $request->etiqueta);
            });
        }

        if ($request->otra_opcion && $request->otra_opcion == "opcion_5") {
            $contratos->leftJoin('facturas_contratos as fc', 'fc.contrato_nro', '=', 'contracts.nro')
                ->leftJoin('factura as f', 'fc.factura_id', '=', 'f.id')
                ->whereNull('f.id')
                ->groupBy('contracts.id');
        }

        // Aplica el filtro de facturas si el usuario lo selecciona
        if ($request->otra_opcion && $request->otra_opcion == "opcion_4") {
            $contratos->join('facturas_contratos as fc', 'fc.contrato_nro', '=', 'contracts.nro')
                ->join('factura as f', 'fc.factura_id', '=', 'f.id')
                ->where('f.estatus', '=', 1)
                ->where('f.vencimiento', '<=', Carbon::now()->format('Y-m-d'))
                ->groupBy('contracts.id')
                ->havingRaw('COUNT(f.id) > 1');
        }

        if ($request->otra_opcion && $request->otra_opcion == "opcion_3") {
            $contratos->join('facturas_contratos as fc', 'fc.contrato_nro', '=', 'contracts.nro')
                ->join('factura as f', 'fc.factura_id', '=', 'f.id')
                ->where('f.estatus', '=', 1)
                ->groupBy('contracts.id')
                ->havingRaw('COUNT(f.id) > 1');
        }

        if ($request->otra_opcion && $request->otra_opcion == "opcion_2") {
            $contratos->where(function ($query) {
                $query->whereNotNull('contracts.descuento')
                    ->orWhereNotNull('contracts.descuento_pesos');
            });
        }

        //Esta opción es para mirar los contratos deshabilitados con su ultima factura pagada.
        if ($request->otra_opcion && $request->otra_opcion == "opcion_1") {
            $contratos = Contrato::where('state', 'disabled')->get();
            $i = 0;
            $arrayContratos = array();
            foreach ($contratos as $contrato) {

                $facturaContratos = DB::table('facturas_contratos')
                    ->where('contrato_nro', $contrato->nro)->orderBy('id', 'DESC')->first();

                if ($facturaContratos) {
                    $ultFactura = Factura::Find($facturaContratos->factura_id);
                    if (isset($ultFactura->estatus) && $ultFactura->estatus == 0) {
                        array_push($arrayContratos, $contrato->id);
                    }
                }
            }
            $contratos = Contrato::select(
                'contracts.*',
                'contactos.id as c_id',
                'contactos.nombre as c_nombre',
                'contactos.apellido1 as c_apellido1',
                'municipios.nombre as nombre_municipio',
                'contactos.apellido2 as c_apellido2',
                'contactos.nit as c_nit',
                'contactos.celular as c_telefono',
                'contactos.email as c_email',
                'contactos.barrio as c_barrio',
                'contactos.direccion',
                'contactos.celular as c_celular',
                'contactos.fk_idmunicipio',
                'contactos.email as c_email',
                'contactos.id as c_id',
                'contactos.firma_isp',
                'contracts.estrato',
                'contracts.latitude as c_latitude',
                'contracts.longitude as c_longitude',
                'barrio.nombre as barrio_nombre',
                DB::raw('(select fecha from ingresos where ingresos.cliente = contracts.client_id and ingresos.tipo = 1 LIMIT 1) AS pago')
            )
                ->selectRaw('INET_ATON(contracts.ip) as ipformat')
                ->join('contactos', 'contracts.client_id', '=', 'contactos.id')
                ->join('municipios', 'contactos.fk_idmunicipio', '=', 'municipios.id')
                ->leftJoin('barrios as barrio', 'barrio.id', 'contactos.barrio_id')
                ->whereIn('contracts.id', $arrayContratos);
        }

        // $contratos = $contratos->where('contracts.status', 1)->get();
        $contratos = $contratos->get();

        // ===== BATCH PRE-LOADING (elimina N+1 queries) =====
        $planIds = $contratos->pluck('plan_id')->filter()->unique()->values()->all();
        $servicioTvIds = $contratos->pluck('servicio_tv')->filter()->unique()->values()->all();
        $servicioOtroIds = $contratos->pluck('servicio_otro')->filter()->unique()->values()->all();
        $servidorIds = $contratos->pluck('server_configuration_id')->filter()->unique()->values()->all();
        $grupoCorteIds = $contratos->pluck('grupo_corte')->filter()->unique()->values()->all();
        $contratoNros = $contratos->pluck('nro')->filter()->unique()->values()->all();
        $contratoIds = $contratos->pluck('id')->filter()->unique()->values()->all();
        $clientIds = $contratos->pluck('client_id')->filter()->unique()->values()->all();

        // Planes de velocidad
        $planesMap = PlanesVelocidad::whereIn('id', $planIds)->get()->keyBy('id');

        // Inventario (items de planes + servicios TV + servicios otros)
        $allItemIds = $planesMap->pluck('item')->filter()->unique()->values()->all();
        $allItemIds = array_unique(array_merge($allItemIds, $servicioTvIds, $servicioOtroIds));
        $inventarioMap = \App\Model\Inventario\Inventario::whereIn('id', $allItemIds)->get()->keyBy('id');

        // Servidores (Mikrotik)
        $servidoresMap = Mikrotik::whereIn('id', $servidorIds)->get()->keyBy('id');

        // Grupos de corte
        $gruposCorteMap = GrupoCorte::whereIn('id', $grupoCorteIds)->get()->keyBy('id');

        // Deuda de facturas (query masiva)
        $deudasMap = [];
        if (count($contratoNros) > 0) {
            $deudasRaw = DB::table('factura')
                ->leftJoin('items_factura as itemsf', 'itemsf.factura', 'factura.id')
                ->leftJoin('facturas_contratos as fc', 'fc.factura_id', 'factura.id')
                ->leftJoin(DB::raw("(SELECT ing_fact.factura, COALESCE(SUM(ing_fact.pago), 0) as totalIngreso
                    FROM ingresos_factura as ing_fact
                    LEFT JOIN ingresos as i ON i.id = ing_fact.ingreso
                    WHERE i.estatus = 1
                    GROUP BY ing_fact.factura) as ingresos"), 'ingresos.factura', 'factura.id')
                ->select('fc.contrato_nro')
                ->selectRaw('SUM(
                    (ROUND(itemsf.precio * itemsf.cant) - IF(itemsf.desc > 0, (itemsf.precio * itemsf.cant) * (itemsf.desc / 100), 0))
                    * IF(itemsf.impuesto > 0, 1 + (itemsf.impuesto / 100), 1)
                ) as totalFactura')
                ->selectRaw('COALESCE(ingresos.totalIngreso, 0) as totalIngreso')
                ->whereIn('fc.contrato_nro', $contratoNros)
                ->where('factura.estatus', 1)
                ->groupBy('fc.contrato_nro', 'factura.id', 'ingresos.totalIngreso')
                ->get();

            foreach ($deudasRaw as $d) {
                if (!isset($deudasMap[$d->contrato_nro])) {
                    $deudasMap[$d->contrato_nro] = 0;
                }
                $deudasMap[$d->contrato_nro] += ($d->totalFactura - $d->totalIngreso);
            }
        }

        // Fecha de desconexión (última por contrato)
        $desconexionesMap = [];
        if (count($contratoIds) > 0) {
            $desconexiones = DB::table('log_movimientos')
                ->select('contrato', DB::raw('MAX(created_at) as ultima_desconexion'))
                ->whereIn('contrato', $contratoIds)
                ->whereRaw("LOWER(descripcion) LIKE '%de habilitado a deshabilitado%'")
                ->groupBy('contrato')
                ->get();
            foreach ($desconexiones as $d) {
                $desconexionesMap[$d->contrato] = Carbon::parse($d->ultima_desconexion)->format('Y-m-d H:i:s');
            }
        }

        // Último pago por contrato
        $ultimosPagosMap = [];
        if (count($contratoNros) > 0) {
            // Para contratos con facturas_contratos
            $ultimosPagos = DB::table('facturas_contratos as fc')
                ->join('factura as f', 'fc.factura_id', '=', 'f.id')
                ->join('ingresos_factura as inf', 'inf.factura', '=', 'f.id')
                ->join('ingresos as i', 'i.id', '=', 'inf.ingreso')
                ->select('fc.contrato_nro', DB::raw('MAX(i.fecha) as ultimo_pago'))
                ->where('i.estatus', 1)
                ->where('i.tipo', 1)
                ->whereIn('fc.contrato_nro', $contratoNros)
                ->groupBy('fc.contrato_nro')
                ->get();
            foreach ($ultimosPagos as $p) {
                $ultimosPagosMap[$p->contrato_nro] = $p->ultimo_pago;
            }
        }

        // ===== GENERAR FILAS DEL EXCEL =====
        $totalPlan = 0;
        $totalServicio = 0;
        $totalServicioOtro = 0;
        foreach ($contratos as $contrato) {

            // Producto plan_id
            $planObj = (object)['precio' => 0, 'nombre' => '', 'conIva' => 0];
            if ($contrato->plan_id && isset($planesMap[$contrato->plan_id])) {
                $planVel = $planesMap[$contrato->plan_id];
                if (isset($planVel->item) && isset($inventarioMap[$planVel->item])) {
                    $itemPlan = $inventarioMap[$planVel->item];
                    $planObj->precio = $itemPlan->precio;
                    $planObj->nombre = $planVel->name;
                    $planObj->conIva = round($itemPlan->precio + ($itemPlan->precio * ($itemPlan->impuesto / 100)));
                }
            }

            // Producto servicio_tv
            $servicioObj = (object)['precio' => 0, 'nombre' => '', 'conIva' => 0];
            if ($contrato->servicio_tv && isset($inventarioMap[$contrato->servicio_tv])) {
                $itemTv = $inventarioMap[$contrato->servicio_tv];
                $servicioObj->nombre = $itemTv->producto;
                $servicioObj->precio = $itemTv->precio;
                $servicioObj->conIva = round($itemTv->precio + ($itemTv->precio * ($itemTv->impuesto / 100)));
            }

            // Producto servicio_otro
            $servicioOtroObj = (object)['precio' => 0, 'nombre' => '', 'conIva' => 0];
            if ($contrato->servicio_otro && isset($inventarioMap[$contrato->servicio_otro])) {
                $itemOtro = $inventarioMap[$contrato->servicio_otro];
                $servicioOtroObj->nombre = $itemOtro->producto;
                $servicioOtroObj->precio = $itemOtro->precio;
                $servicioOtroObj->conIva = round($itemOtro->precio + ($itemOtro->precio * ($itemOtro->impuesto / 100)));
            }

            $sumaPlanes = $planObj->conIva + $servicioObj->conIva + $servicioOtroObj->conIva;

            isset($planObj->precio) && $planObj->precio > 0 ? $totalPlan += $planObj->precio : '';
            isset($servicioObj->precio) && $servicioObj->precio > 0 ? $totalServicio += $servicioObj->precio : '';
            isset($servicioOtroObj->precio) && $servicioOtroObj->precio > 0 ? $totalServicioOtro += $servicioOtroObj->precio : '';

            // Lookups optimizados
            $planTvNombre = ($contrato->servicio_tv && isset($inventarioMap[$contrato->servicio_tv])) ? $inventarioMap[$contrato->servicio_tv]->producto : '';
            $planInternetNombre = ($contrato->plan_id && isset($planesMap[$contrato->plan_id])) ? $planesMap[$contrato->plan_id]->name : '';
            $servidorNombre = ($contrato->server_configuration_id && isset($servidoresMap[$contrato->server_configuration_id])) ? $servidoresMap[$contrato->server_configuration_id]->nombre : '';
            $estadoTexto = $contrato->state == 'enabled' ? 'Habilitado' : 'Deshabilitado';

            // Grupo de corte
            $grupoCorteTexto = 'SIN GRUPO ASOCIADO';
            if ($contrato->grupo_corte && isset($gruposCorteMap[$contrato->grupo_corte])) {
                $gc = $gruposCorteMap[$contrato->grupo_corte];
                $grupoCorteTexto = $gc->nombre . '(CORTE ' . $gc->fecha_corte . ' - SUSPENSIÓN ' . $gc->fecha_suspension . ')';
            }

            // Facturación
            $facturacionTexto = 'N/A';
            if ($contrato->facturacion == 1) { $facturacionTexto = 'Estándar'; }
            elseif ($contrato->facturacion == 3) { $facturacionTexto = 'Electrónica'; }

            // Deuda, desconexión, último pago desde mapas
            $deudaFacturas = isset($deudasMap[$contrato->nro]) ? round($deudasMap[$contrato->nro]) : 0;
            $fechaDesconexion = isset($desconexionesMap[$contrato->id]) ? $desconexionesMap[$contrato->id] : null;
            $ultimoPago = isset($ultimosPagosMap[$contrato->nro]) ? $ultimosPagosMap[$contrato->nro] : '';

            $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue($letras[0] . $i, $contrato->nro)
                ->setCellValue($letras[1] . $i, $contrato->c_nombre . ' ' . $contrato->c_apellido1 . ' ' . $contrato->c_apellido2)
                ->setCellValue($letras[2] . $i, $contrato->c_nit)
                ->setCellValue($letras[3] . $i, $contrato->c_celular)
                ->setCellValue($letras[4] . $i, $contrato->c_telefono1)
                ->setCellValue($letras[5] . $i, $contrato->c_telefono2)
                ->setCellValue($letras[6] . $i, $contrato->c_email)
                ->setCellValue($letras[7] . $i, $contrato->c_direccion)
                ->setCellValue($letras[8] . $i, $contrato->nombre_barrio)
                ->setCellValue($letras[9] . $i, $contrato->c_vereda)
                ->setCellValue($letras[10] . $i, $contrato->estrato)
                ->setCellValue($letras[11] . $i, $planTvNombre)
                ->setCellValue($letras[12] . $i, $planInternetNombre)
                ->setCellValue($letras[13] . $i, $servidorNombre)
                ->setCellValue($letras[14] . $i, $contrato->ip)
                ->setCellValue($letras[15] . $i, $contrato->mac_address)
                ->setCellValue($letras[16] . $i, $contrato->interfaz)
                ->setCellValue($letras[17] . $i, $contrato->serial_onu)
                ->setCellValue($letras[18] . $i, $contrato->olt_sn_mac)
                ->setCellValue($letras[19] . $i, $estadoTexto)
                ->setCellValue($letras[20] . $i, $contrato->state_olt_catv == 1 ? 'Activo' : 'Inactivo')
                ->setCellValue($letras[21] . $i, $grupoCorteTexto)
                ->setCellValue($letras[22] . $i, $facturacionTexto)
                ->setCellValue($letras[23] . $i, $contrato->costo_reconexion)
                ->setCellValue($letras[24] . $i, $contrato->c_nombre_municipio)
                ->setCellValue($letras[25] . $i, ucfirst($contrato->tipo_contrato))
                ->setCellValue($letras[26] . $i, $contrato->iva_factura == null || $contrato->iva_factura == 0 ? 'No' : 'Si')
                ->setCellValue($letras[27] . $i, $contrato->descuento != null ? $contrato->descuento . '%' : '0%')
                ->setCellValue($letras[28] . $i, $contrato->descuento_pesos != null ? $contrato->descuento_pesos : 0)
                ->setCellValue($letras[29] . $i, isset($planObj->nombre) ? $planObj->nombre : '')
                ->setCellValue($letras[30] . $i, isset($planObj->precio) ? $planObj->precio : '')
                ->setCellValue($letras[31] . $i, isset($servicioObj->nombre) && $servicioObj->nombre != "" ? $servicioObj->nombre . " - $" . number_format($servicioObj->precio, 0, ',', '.') : '')
                ->setCellValue($letras[32] . $i, isset($servicioOtroObj->nombre) && $servicioOtroObj->nombre != "" ? $servicioOtroObj->nombre . " - $" . number_format($servicioOtroObj->precio, 0, ',', '.') : '')
                ->setCellValue($letras[33] . $i, $deudaFacturas)
                ->setCellValue($letras[34] . $i, round($sumaPlanes))
                ->setCellValue($letras[35] . $i, $contrato->c_etiqueta)
                ->setCellValue($letras[36] . $i, $fechaDesconexion)
                ->setCellValue($letras[37] . $i, $contrato->linea ? $contrato->linea : 0)
                ->setCellValue($letras[38] . $i, $contrato->c_latitude)
                ->setCellValue($letras[39] . $i, $contrato->c_longitude)
                ->setCellValue($letras[40] . $i, Carbon::parse($contrato->created_at)->format('Y-m-d'))
                ->setCellValue($letras[41] . $i, $contrato->creador)
                ->setCellValue($letras[42] . $i, $ultimoPago)
                ->setCellValue($letras[43] . $i, $contrato->status ? 'No' : 'Si')
                ->setCellValue($letras[44] . $i, $contrato->usuario)
                ->setCellValue($letras[45] . $i, $contrato->password)
                ->setCellValue($letras[46] . $i, $contrato->profile);
            $i++;
        }



        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue($letras[29] . $i, $totalPlan)
            ->setCellValue($letras[30] . $i, $totalServicio)
            ->setCellValue($letras[31] . $i, $totalServicioOtro)
        ;

        $estilo = array(
            'font'  => array('size'  => 12, 'name'  => 'Times New Roman'),
            'borders' => array(
                'allborders' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN
                )
            ),
            'alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,)
        );
        $objPHPExcel->getActiveSheet()->getStyle('A3:AT' . $i)->applyFromArray($estilo);

        for ($j = 'A'; $j <= $letras[45]; $j++) {
            $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension($j)->setAutoSize(TRUE);
        }

        // Se asigna el nombre a la hoja
        $objPHPExcel->getActiveSheet()->setTitle('Lista de Contratos');

        // Se activa la hoja para que sea la que se muestre cuando el archivo se abre
        $objPHPExcel->setActiveSheetIndex(0);

        // Inmovilizar paneles
        $objPHPExcel->getActiveSheet(0)->freezePane('A4');
        $objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0, 4);
        $objPHPExcel->setActiveSheetIndex(0);
        header("Pragma: no-cache");
        header('Content-type: application/vnd.ms-excel');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="Reporte_Contratos.xlsx"');
        header('Cache-Control: max-age=0');
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');
        exit;
    }

    public function grafica($id)
    {
        $this->getAllPermissions(Auth::user()->id);
        $contrato = Contrato::find($id);
        if ($contrato) {
            view()->share(['icon' => 'fas fa-chart-area', 'title' => 'Gráfica de Conexión | Contrato: ' . $contrato->nro]);
            return view('contratos.grafica')->with(compact('contrato'));
        }
        return back()->with('danger', 'EL CONTRATO DE SERVICIOS NO HA ENCONTRADO');
    }

    public function graficajson($id)
    {
        $this->getAllPermissions(Auth::user()->id);

        $contrato = Contrato::find($id);
        $mikrotik = Mikrotik::where('id', $contrato->server_configuration_id)->first();

        $API = new RouterosAPI();
        $API->port = $mikrotik->puerto_api;
        //$API->debug = true;

        if ($API->connect($mikrotik->ip, $mikrotik->usuario, $mikrotik->clave)) {
            $rows = array();
            $rows2 = array();
            $Type = 1;
            $Interface = 'ether1';
            if ($Type == 0) {  // Interfaces
                $API->write("/interface/monitor-traffic", false);
                $API->write("=interface=" . $contrato->name_vlan, false);
                $API->write("=once=", true);
                $READ = $API->read(false);
                $ARRAY = $API->parseResponse($READ);
                if (count($ARRAY) > 0) {
                    $rx = ($ARRAY[0]["rx-bits-per-second"]);
                    $tx = ($ARRAY[0]["tx-bits-per-second"]);
                    $rows['name'] = 'Tx';
                    $rows['data'][] = $tx;
                    $rows2['name'] = 'Rx';
                    $rows2['data'][] = $rx;
                } else {
                    echo $ARRAY['!trap'][0]['message'];
                }
            } else if ($Type == 1) { //  Queues
                $API->write("/queue/simple/print", false);
                $API->write("=stats", false);
                $API->write("?target=" . $contrato->ip . '/32', true);
                $READ = $API->read(false);
                $ARRAY = $API->parseResponse($READ);
                if (count($ARRAY) > 0) {
                    $rx = explode("/", $ARRAY[0]["rate"])[0];
                    $tx = explode("/", $ARRAY[0]["rate"])[1];
                    $rows['name'] = 'Tx';
                    $rows['data'][] = $tx;
                    $rows2['name'] = 'Rx';
                    $rows2['data'][] = $rx;
                } else {
                    return response()->json([
                        'success' => false,
                        'icon'    => 'error',
                        'title'   => 'ERROR',
                        'text'    => 'NO SE HA PODIDO REALIZAR LA GRÁFICA'
                    ]);
                }
            }

            $ConnectedFlag = true;

            if ($ConnectedFlag) {
                $result = array();
                array_push($result, $rows);
                array_push($result, $rows2);
                echo json_encode($result, JSON_NUMERIC_CHECK);
            }
            $API->disconnect();
        }
    }

    public function conexion($id)
    {
        $this->getAllPermissions(Auth::user()->id);
        $contrato = Contrato::find($id);
        if ($contrato) {
            if (!$contrato->ip) {
                return back()->with('danger', 'EL CONTRATO NO POSEE DIRECCIÓN IP ASOCIADA');
            }

            /*REGISTRO DEL LOG*/
            $movimiento = new MovimientoLOG;
            $movimiento->contrato    = $id;
            $movimiento->modulo      = 5;
            $movimiento->descripcion = '<i class="fas fa-check text-success"></i> <b>PROCESO DE PING REALIZADO</b><br>';;
            $movimiento->created_by  = Auth::user()->id;
            $movimiento->empresa     = Auth::user()->empresa;
            $movimiento->save();

            view()->share(['icon' => 'fas fa-plug', 'title' => 'Ping de Conexión: ' . $contrato->nro]);
            return view('contratos.ping')->with(compact('contrato'));
        }
        return redirect('empresa/contratos')->with('danger', 'EL CONTRATO DE SERVICIOS NO HA ENCONTRADO');
    }

    public function ping_nuevo($id)
    {
        $contrato = Contrato::find($id);

        if ($contrato) {
            if (!$contrato->ip) {
                return response()->json([
                    'success' => false,
                    'icon'    => 'error',
                    'title'   => 'ERROR',
                    'text'    => 'EL CONTRATO NO POSEE DIRECCIÓN IP ASOCIADA'
                ]);
            }

            $mikrotik = Mikrotik::where('id', $contrato->server_configuration_id)->first();

            $API = new RouterosAPI();
            $API->port = $mikrotik->puerto_api;
            //$API->debug = true;

            if ($API->connect($mikrotik->ip, $mikrotik->usuario, $mikrotik->clave)) {
                // PING
                $API->write("/ping", false);
                $API->write("=address=" . $contrato->ip, false);
                $API->write("=count=1", true);
                $READ = $API->read(false);
                $ARRAY = $API->parseResponse($READ);

                if (count($ARRAY) > 0) {
                    if ($ARRAY[0]["received"] != $ARRAY[0]["sent"]) {
                        $data = [
                            'contrato' => $contrato->id,
                            'ip' => $contrato->ip,
                            'fecha' => Carbon::parse(now())->format('Y-m-d')
                        ];

                        $ping = Ping::updateOrCreate(
                            ['contrato' => $contrato->id],
                            $data
                        );
                        return response()->json([
                            'success' => false,
                            'icon'    => 'error',
                            'title'   => 'ERROR',
                            'text'    => 'SE HA REALIZADO EL PING PERO NO SE TIENE CONEXIÓN (ERROR: ' . strtoupper($ARRAY[0]["status"]) . ')',
                            'data'    => $ARRAY
                        ]);
                    } else {
                        Ping::where('contrato', $contrato->id)->delete();
                        return response()->json([
                            'success' => true,
                            'icon'    => 'success',
                            'title'   => 'PROCESO EXITOSO',
                            'text'    => 'SE HA REALIZADO EL PING DE CONEXIÓN DE MANERA EXITOSA',
                            'data'    => $ARRAY
                        ]);
                    }
                } else {
                    return response()->json([
                        'success' => false,
                        'icon'    => 'error',
                        'title'   => 'ERROR',
                        'text'    => 'SE HA REALIZADO EL PING PERO NO SE TIENE CONEXIÓN',
                        'data'    => $ARRAY
                    ]);
                }
                $API->disconnect();
            } else {
                $data = [
                    'contrato' => $contrato->id,
                    'ip' => $contrato->ip,
                    'fecha' => Carbon::parse(now())->format('Y-m-d')
                ];

                $ping = Ping::updateOrCreate(
                    ['contrato' => $contrato->id],
                    $data
                );

                return response()->json([
                    'success' => false,
                    'icon'    => 'error',
                    'title'   => 'ERROR',
                    'text'    => 'NO SE HA PODIDO REALIZAR EL PING. VERIFIQUE LA CONEXIÓN DE LA MIKROTIK <b><i><u>' . $mikrotik->nombre . '</u></i></b>'
                ]);
            }
        }

        return response()->json([
            'success' => false,
            'icon'    => 'error',
            'title'   => 'ERROR',
            'text'    => 'EL CONTRATO DE SERVICIOS NO HA ENCONTRADO'
        ]);
    }

    public function destroy_to_mk($id)
    {
        $this->getAllPermissions(Auth::user()->id);
        $contrato = Contrato::find($id);
        if ($contrato) {
            $mikrotik = Mikrotik::where('id', $contrato->server_configuration_id)->first();

            $API = new RouterosAPI();
            $API->port = $mikrotik->puerto_api;
            //$API->debug = true;

            if ($API->connect($mikrotik->ip, $mikrotik->usuario, $mikrotik->clave)) {
                if ($contrato->conexion == 1) {
                    //OBTENEMOS AL CONTRATO MK
                    $mk_user = $API->comm(
                        "/ppp/secret/getall",
                        array(
                            "?comment" => $contrato->id,
                        )
                    );
                    // REMOVEMOS EL SECRET
                    $API->comm(
                        "/ppp/secret/remove",
                        array(
                            ".id" => $mk_user[0][".id"],
                        )
                    );

                    //OBTENEMOS EL ID DEL NOMBRE DEL CLIENTE
                    $id_simple = $API->comm(
                        "/queue/simple/getall",
                        array(
                            "?comment" => $contrato->id,
                        )
                    );
                    // REMOVEMOS LA COLA SIMPLE
                    $API->comm(
                        "/queue/simple/remove",
                        array(
                            ".id" => $id_simple[0][".id"],
                        )
                    );
                }

                if ($contrato->conexion == 2) {
                }

                if ($contrato->conexion == 3) {
                    //OBTENEMOS AL CONTRATO MK
                    $mk_user = $API->comm(
                        "/ip/arp/getall",
                        array(
                            "?comment" => $contrato->servicio,
                        )
                    );

                    if ($mk_user) {
                        // REMOVEMOS EL IP ARP
                        $API->comm(
                            "/ip/arp/remove",
                            array(
                                ".id" => $mk_user[0][".id"],
                            )
                        );
                        //OBTENEMOS EL ID DEL NOMBRE DEL CLIENTE
                        $id_simple = $API->comm(
                            "/queue/simple/getall",
                            array(
                                "?comment" => $contrato->id,
                            )
                        );
                        // REMOVEMOS LA COLA SIMPLE
                        $API->comm(
                            "/queue/simple/remove",
                            array(
                                ".id" => $id_simple[0][".id"],
                            )
                        );
                    }
                }

                $API->disconnect();

                $mensaje = 'SE HA ELIMINADO EL CONTRATO DEL MIKROTIK';
                return redirect('empresa/contratos')->with('success', $mensaje);
            } else {
                $mensaje = 'NO SE HA PODIDO ELIMINAR EL CONTRATO DE SERVICIOS';
                return redirect('empresa/contratos')->with('danger', $mensaje);
            }
        }
        return redirect('empresa/contratos')->with('danger', 'EL CONTRATO DE SERVICIOS NO HA ENCONTRADO');
    }

    public function log($id)
    {

        $this->getAllPermissions(Auth::user()->id);
        $contrato = Contrato::find($id);
        $contrato_log = DB::table('log_movimientos')->where('contrato', $contrato->id)->get();

        if ($contrato) {
            view()->share(['icon' => 'fas fa-chart-area', 'title' => 'Log | Contrato: ' . $contrato->nro]);
            return view('contratos.log')->with(compact('contrato', 'contrato_log'));
        } else {
            $mensaje = 'NO SE HA PODIDO OBTENER EL LOG DEL CONTRATO DE SERVICIOS';
            return redirect('empresa/contratos/' . $contrato->id)->with('danger', $mensaje);
        }
        return redirect('empresa/contratos')->with('danger', 'EL CONTRATO DE SERVICIOS NO HA ENCONTRADO');
    }

    public function logs(Request $request, $contrato)
    {
        $modoLectura = auth()->user()->modo_lectura();
        $contratos = MovimientoLOG::query();
        $contratos->where('log_movimientos.contrato', $contrato);

        return datatables()->eloquent($contratos)
            ->editColumn('created_at', function (MovimientoLOG $contrato) {
                return date('d-m-Y g:i:s A', strtotime($contrato->created_at));
            })
            ->editColumn('created_by', function (MovimientoLOG $contrato) {
                return $contrato->created_by();
            })
            ->editColumn('descripcion', function (MovimientoLOG $contrato) {
                return $contrato->descripcion;
            })
            ->rawColumns(['created_at', 'created_by', 'descripcion'])
            ->toJson();
    }

    public function grafica_consumo($id)
    {
        $this->getAllPermissions(Auth::user()->id);
        $contrato = Contrato::find($id);
        if ($contrato) {
            $mikrotik = Mikrotik::where('id', $contrato->server_configuration_id)->first();

            $API = new RouterosAPI();
            $API->port = $mikrotik->puerto_api;
            //$API->debug = true;

            if ($API->connect($mikrotik->ip, $mikrotik->usuario, $mikrotik->clave)) {
                $API->write("/tool/graph/interface/print", true);
                $ARRAYS = $API->read();
                if (count($ARRAYS) > 0) {
                    view()->share(['icon' => '', 'title' => 'Gráfica de Consumo | Contrato: ' . $contrato->nro]);
                    $url = $mikrotik->ip . ':' . $mikrotik->puerto_web . '/graphs/queue/' . str_replace(' ', '%20', $contrato->servicio);
                    return view('contratos.grafica-consumo')->with(compact('contrato', 'url'));
                }
                return redirect('empresa/contratos/' . $contrato->id)->with('danger', 'EL SERVIDOR NO TIENE HABILITADA LA VISUALIZACIÓN DE LOS GRÁFICOS');
            }
        }
        return back()->with('danger', 'EL CONTRATO DE SERVICIOS NO HA ENCONTRADO');
    }

    public function grafica_proxy($id, $tipo)
    {
        $this->getAllPermissions(Auth::user()->id);
        $contrato = Contrato::find($id);

        if (!$contrato) {
            abort(404);
        }

        $mikrotik = Mikrotik::where('id', $contrato->server_configuration_id)->first();

        if (!$mikrotik) {
            abort(404);
        }

        // Tipos permitidos de gráficas
        $tiposPermitidos = ['daily', 'weekly', 'monthly', 'yearly'];

        if (!in_array($tipo, $tiposPermitidos)) {
            abort(404);
        }

        // Codificar el servicio correctamente para URL
        // Usar urlencode para manejar todos los caracteres especiales
        $servicio = urlencode($contrato->servicio);
        $mikrotikUrl = "http://{$mikrotik->ip}:{$mikrotik->puerto_web}/graphs/queue/{$servicio}/{$tipo}.gif";

        // Hacer la petición al Mikrotik
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $mikrotikUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

            // Si el Mikrotik requiere autenticación HTTP, descomenta y configura:
            // curl_setopt($ch, CURLOPT_USERPWD, "{$mikrotik->usuario}:{$mikrotik->clave}");
            // curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

            $imageData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $error = curl_error($ch);

            curl_close($ch);

            // Verificar si hay error de cURL
            if ($error) {
                Log::error("Error cURL al obtener gráfica de Mikrotik. Error: {$error}, URL: {$mikrotikUrl}");
                abort(404);
            }

            // Verificar código HTTP
            if ($httpCode !== 200) {
                Log::error("Error HTTP al obtener gráfica de Mikrotik. HTTP Code: {$httpCode}, URL: {$mikrotikUrl}");
                abort(404);
            }

            // Verificar que realmente sea una imagen
            // Si el Mikrotik devuelve un error, normalmente devuelve HTML o texto
            if (!$imageData || strlen($imageData) < 100) {
                Log::error("Respuesta vacía o muy pequeña de Mikrotik. Tamaño: " . strlen($imageData) . " bytes, URL: {$mikrotikUrl}");
                abort(404);
            }

            // Verificar si la respuesta es HTML (error del Mikrotik)
            // Las imágenes GIF empiezan con "GIF89a" o "GIF87a"
            if (substr($imageData, 0, 6) !== 'GIF89a' && substr($imageData, 0, 6) !== 'GIF87a') {
                // Si no es un GIF válido, probablemente es un error del Mikrotik
                $errorMessage = substr($imageData, 0, 500); // Primeros 500 caracteres para el log
                Log::error("Mikrotik devolvió error en lugar de imagen. Respuesta: {$errorMessage}, URL: {$mikrotikUrl}");
                abort(404);
            }

            // Devolver la imagen con los headers correctos
            return response($imageData, 200)
                ->header('Content-Type', $contentType ?: 'image/gif')
                ->header('Cache-Control', 'public, max-age=300') // Cache de 5 minutos
                ->header('Access-Control-Allow-Origin', '*');

        } catch (\Exception $e) {
            Log::error("Excepción al obtener gráfica de Mikrotik: " . $e->getMessage() . ", URL: {$mikrotikUrl}");
            abort(500);
        }
    }

    public function eliminarAdjunto($id, $archivo)
    {
        $contrato = Contrato::where('id', $id)->where('empresa', Auth::user()->empresa)->first();
        if ($contrato) {
            switch ($archivo) {
                case 'adjunto_a':
                    $contrato->adjunto_a = NULL;
                    $contrato->referencia_a = NULL;
                    break;
                case 'adjunto_b':
                    $contrato->adjunto_b = NULL;
                    $contrato->referencia_b = NULL;
                    break;
                case 'adjunto_c':
                    $contrato->adjunto_c = NULL;
                    $contrato->referencia_c = NULL;
                    break;
                case 'adjunto_d':
                    $contrato->adjunto_d = NULL;
                    $contrato->referencia_d = NULL;
                    break;
                default:
                    break;
            }
            $contrato->save();
            return response()->json([
                'success' => true,
                'type'    => 'success',
                'title'   => 'Archivo Adjunto Eliminado',
                'text'    => ''
            ]);
        }
        return response()->json([
            'success' => false,
            'type'    => 'error',
            'title'   => 'Archivo no eliminado',
            'text'    => 'Inténtelo Nuevamente'
        ]);
    }

    public function enviar_mk(Request $request, $id)
    {
        $this->getAllPermissions(Auth::user()->id);
        $contrato = Contrato::find($id);
        if ($contrato) {
            $plan = PlanesVelocidad::where('id', $contrato->plan_id)->first();
            $mikrotik = Mikrotik::where('id', $plan->mikrotik)->first();
            $cliente = $contrato->cliente();
            $servicio = $cliente->nombre . ' ' . $cliente->apellido1 . ' ' . $cliente->apellido2;

            if ($mikrotik) {
                $API = new RouterosAPI();
                $API->port = $mikrotik->puerto_api;
                //$API->debug = true;

                if ($API->connect($mikrotik->ip, $mikrotik->usuario, $mikrotik->clave)) {
                    $rate_limit      = '';
                    $priority        = $plan->prioridad;
                    $burst_limit     = (strlen($plan->burst_limit_subida) > 1) ? $plan->burst_limit_subida . '/' . $plan->burst_limit_bajada : '';
                    $burst_threshold = (strlen($plan->burst_threshold_subida) > 1) ? $plan->burst_threshold_subida . '/' . $plan->burst_threshold_bajada : '';
                    $burst_time      = ($plan->burst_time_subida) ? $plan->burst_time_subida . '/' . $plan->burst_time_bajada : '';
                    $limit_at        = (strlen($plan->limit_at_subida) > 1) ? $plan->limit_at_subida . '/' . $plan->limit_at_bajada : '';
                    $max_limit       = $plan->upload . '/' . $plan->download;

                    if ($max_limit) {
                        $rate_limit .= $max_limit;
                    }
                    if (strlen($burst_limit) > 3) {
                        $rate_limit .= ' ' . $burst_limit;
                    }
                    if (strlen($burst_threshold) > 3) {
                        $rate_limit .= ' ' . $burst_threshold;
                    }
                    if (strlen($burst_time) > 3) {
                        $rate_limit .= ' ' . $burst_time;
                    }
                    if ($priority) {
                        $rate_limit .= ' ' . $priority;
                    }
                    if ($limit_at) {
                        $rate_limit .= ' ' . $limit_at;
                    }

                    /*PPPOE*/
                    if ($contrato->conexion == 1) {
                        $API->comm(
                            "/ppp/secret/add",
                            array(
                                "name"           => $contrato->usuario,
                                "password"       => $contrato->password,
                                "profile"        => 'default',
                                "local-address"  => $contrato->ip,
                                "remote-address" => $contrato->ip,
                                "service"        => 'pppoe',
                                "comment"        => $this->normaliza($servicio) . '-' . $contrato->nro
                            )
                        );

                        $getall = $API->comm(
                            "/ppp/secret/getall",
                            array(
                                "?local-address" => $contrato->ip
                            )
                        );
                    }

                    /*DHCP*/
                    if ($contrato->conexion == 2) {
                        if (isset($plan->dhcp_server)) {
                            if ($contrato->simple_queue == 'dinamica') {
                                $API->comm("/ip/dhcp-server/set\n=name=" . $plan->dhcp_server . "\n=address-pool=static-only\n=parent-queue=" . $plan->parenta);
                                $API->comm(
                                    "/ip/dhcp-server/lease/add",
                                    array(
                                        "comment"     => $this->normaliza($servicio) . '-' . $contrato->nro,
                                        "address"     => $contrato->ip,
                                        "server"      => $plan->dhcp_server,
                                        "mac-address" => $contrato->mac_address,
                                        "rate-limit"  => $rate_limit
                                    )
                                );
                            } elseif ($contrato->simple_queue == 'estatica') {
                                $API->comm(
                                    "/ip/dhcp-server/lease/add",
                                    array(
                                        "comment"     => $this->normaliza($servicio) . '-' . $contrato->nro,
                                        "address"     => $contrato->ip,
                                        "server"      => $plan->dhcp_server,
                                        "mac-address" => $contrato->mac_address
                                    )
                                );
                            }

                            $getall = $API->comm(
                                "/ip/dhcp-server/lease/getall",
                                array(
                                    "?address" => $contrato->ip
                                )
                            );
                        } else {
                            $mensaje = 'NO SE HA PODIDO EDITAR EL CONTRATO DE SERVICIOS, NO EXISTE UN SERVIDOR DHCP DEFINIDO PARA EL PLAN ' . $plan->name;
                            return redirect('empresa/contratos')->with('danger', $mensaje);
                        }
                    }

                    /*IP ESTÁTICA*/
                    if ($contrato->conexion == 3) {
                        if ($mikrotik->amarre_mac == 1) {
                            $API->comm(
                                "/ip/arp/add",
                                array(
                                    "comment"     => $this->normaliza($servicio) . '-' . $contrato->nro,
                                    "address"     => $contrato->ip,
                                    "interface"   => $contrato->interfaz,
                                    "mac-address" => $contrato->mac_address
                                )
                            );

                            $getall = $API->comm(
                                "/ip/arp/getall",
                                array(
                                    "?address" => $contrato->ip
                                )
                            );
                        }
                    }

                    /*VLAN*/
                    if ($contrato->conexion == 4) {
                    }

                    //if($getall){
                    $registro = true;
                    $queue = $API->comm(
                        "/queue/simple/getall",
                        array(
                            "?target" => $contrato->ip . '/32'
                        )
                    );

                    if ($queue) {
                        $API->comm(
                            "/queue/simple/set",
                            array(
                                ".id"             => $queue[0][".id"],
                                "target"          => $contrato->ip,
                                "max-limit"       => $plan->upload . '/' . $plan->download,
                                "burst-limit"     => $burst_limit,
                                "burst-threshold" => $burst_threshold,
                                "burst-time"      => $burst_time,
                                "priority"        => $priority,
                                "limit-at"        => $limit_at
                            )
                        );
                    } else {
                        $API->comm(
                            "/queue/simple/add",
                            array(
                                "name"            => $this->normaliza($servicio) . '-' . $contrato->nro,
                                "target"          => $contrato->ip,
                                "max-limit"       => $plan->upload . '/' . $plan->download,
                                "burst-limit"     => $burst_limit,
                                "burst-threshold" => $burst_threshold,
                                "burst-time"      => $burst_time,
                                "priority"        => $priority,
                                "limit-at"        => $limit_at
                            )
                        );
                    }
                    //}
                    #AGREGAMOS A IP_AUTORIZADAS#
                    $API->comm(
                        "/ip/firewall/address-list/add",
                        array(
                            "address" => $contrato->ip,
                            "list" => 'ips_autorizadas'
                        )
                    );
                    #AGREGAMOS A IP_AUTORIZADAS#
                }

                $API->disconnect();

                if ($registro) {
                    $contrato->mk = 1;
                    $contrato->state = 'enabled';
                    $contrato->servicio = $this->normaliza($servicio) . '-' . $contrato->nro;
                    $contrato->save();
                    $mensaje = 'SE HA REGISTRADO SATISFACTORIAMENTE EN EL MIKROTIK EL CONTRATO DE SERVICIOS';
                    return redirect('empresa/contratos/' . $id)->with('success', $mensaje);
                } else {
                    $mensaje = 'NO SE HA PODIDO REGISTRAR EL CONTRATO EN LA MIKROTIK';
                    return redirect('empresa/contratos')->with('danger', $mensaje);
                }
            }
        }
        return redirect('empresa/contratos')->with('danger', 'EL CONTRATO DE SERVICIOS NO HA ENCONTRADO');
    }

    public function carga_adjuntos(Request $request, $id)
    {
        $this->getAllPermissions(Auth::user()->id);
        $contrato = Contrato::find($id);

        if ($contrato) {
            ### DOCUMENTOS ADJUNTOS ###
            if ($request->referencia_a) {
                $contrato->referencia_a = $request->referencia_a;
                if ($request->adjunto_a) {
                    $file = $request->file('adjunto_a');
                    $nombre =  $file->getClientOriginalName();
                    Storage::disk('documentos')->put($nombre, \File::get($file));
                    $contrato->adjunto_a = $nombre;
                }
            }
            if ($request->referencia_b) {
                $contrato->referencia_b = $request->referencia_b;
                if ($request->adjunto_b) {
                    $file = $request->file('adjunto_b');
                    $nombre =  $file->getClientOriginalName();
                    Storage::disk('documentos')->put($nombre, \File::get($file));
                    $contrato->adjunto_b = $nombre;
                }
            }
            if ($request->referencia_c) {
                $contrato->referencia_c = $request->referencia_c;
                if ($request->adjunto_c) {
                    $file = $request->file('adjunto_c');
                    $nombre =  $file->getClientOriginalName();
                    Storage::disk('documentos')->put($nombre, \File::get($file));
                    $contrato->adjunto_c = $nombre;
                }
            }
            if ($request->referencia_d) {
                $contrato->referencia_d = $request->referencia_d;
                if ($request->adjunto_d) {
                    $file = $request->file('adjunto_d');
                    $nombre =  $file->getClientOriginalName();
                    Storage::disk('documentos')->put($nombre, \File::get($file));
                    $contrato->adjunto_d = $nombre;
                }
            }

            ### DOCUMENTOS ADJUNTOS ###

            $contrato->save();
            return redirect('empresa/contactos/' . $request->contacto_id)->with('success', 'SE HA CARGADO SATISFACTORIAMENTE LOS ARCHIVOS ADJUNTOS');
        }
        return redirect('empresa/contratos')->with('danger', 'EL CONTRATO DE SERVICIOS NO HA ENCONTRADO');
    }

    public function state_lote($contratos, $state)
    {
        $this->getAllPermissions(Auth::user()->id);
        $empresa = Auth::user()->empresa();

        $succ = 0;
        $fail = 0;

        $contratos = explode(",", $contratos);

        for ($i = 0; $i < count($contratos); $i++) {
            $contrato = Contrato::find($contratos[$i]);

            if (!$contrato) {
                $fail++;
                continue;
            }

            $mikrotik_ok = false;
            $olt_ok = false;

            // 1. Bloque Mikrotik
            if ($contrato->server_configuration_id && $empresa->consultas_mk == 1) {
                $mikrotik = Mikrotik::find($contrato->server_configuration_id);

                if ($mikrotik) {
                    $API = new RouterosAPI();
                    $API->port = $mikrotik->puerto_api;

                    if ($API->connect($mikrotik->ip, $mikrotik->usuario, $mikrotik->clave)) {
                        $API->write('/ip/firewall/address-list/print', true);
                        $ARRAYS = $API->read();

                        switch ($state) {
                            case 'disabled':
                                $API->comm("/ip/firewall/address-list/add", [
                                    "address" => $contrato->ip,
                                    "comment" => $contrato->servicio,
                                    "list" => 'morosos'
                                ]);

                                $API->write('/ip/firewall/address-list/print', false);
                                $API->write('?address=' . $contrato->ip, false);
                                $API->write("?list=ips_autorizadas", false);
                                $API->write('=.proplist=.id');
                                $ARRAYS = $API->read();
                                if (count($ARRAYS) > 0) {
                                    $API->write('/ip/firewall/address-list/remove', false);
                                    $API->write('=.id=' . $ARRAYS[0]['.id']);
                                    $API->read();
                                }
                                break;

                            case 'enabled':
                                $API->write('/ip/firewall/address-list/print', false);
                                $API->write('?address=' . $contrato->ip, false);
                                $API->write("?list=morosos", false);
                                $API->write('=.proplist=.id');
                                $ARRAYS = $API->read();
                                if (count($ARRAYS) > 0) {
                                    $API->write('/ip/firewall/address-list/remove', false);
                                    $API->write('=.id=' . $ARRAYS[0]['.id']);
                                    $API->read();
                                }

                                $API->comm("/ip/firewall/address-list/add", [
                                    "address" => $contrato->ip,
                                    "list" => 'ips_autorizadas'
                                ]);
                                break;
                        }

                        $API->disconnect();
                        $mikrotik_ok = true;
                    }
                }
            }

            // 2. Bloque Smart OLT (independiente de Mikrotik)
            if ($contrato->conexion == 2 && $empresa->queries_dhcp_smartolt == 1 && !empty($contrato->serial_onu)) {
                $oltController = app('App\Http\Controllers\OltController');
                if ($state == 'enabled') {
                    $oltController->enableOnu($contrato->serial_onu);
                } else {
                    $oltController->disableOnu($contrato->serial_onu);
                }
                $olt_ok = true;
            }

            // 3. Guardar estado si al menos un sistema operó (o si no aplica ninguno, igual guardamos)
            $contrato->state = $state;
            $contrato->save();

            // Etiquetas automáticas: se aplica según el tipo de cambio de estado masivo
            if ($state == 'disabled') {
                \App\Traits\AplicaEtiquetaAutomatica::aplicarEtiquetaAutomatica(
                    $contrato->id,
                    Auth::user()->empresa,
                    \App\EtiquetaAutomaticaContrato::MODULO_CONTRATOS,
                    \App\EtiquetaAutomaticaContrato::DESHABILITAR_MANUAL
                );
            }

            if ($mikrotik_ok || $olt_ok || (!$contrato->server_configuration_id && !$olt_ok)) {
                $succ++;
            } else {
                $fail++;
            }
        }

        return response()->json([
            'success'   => true,
            'fallidos'  => $fail,
            'correctos' => $succ,
            'state'     => $state
        ]);
    }

    public function state_oltcatv_lote($contratos, $state)
    {
        $this->getAllPermissions(Auth::user()->id);

        $succ = 0;
        $fail = 0;

        $contratos = explode(",", $contratos);
        $empresa = Auth::user()->empresa();

        for ($i = 0; $i < count($contratos); $i++) {
            $contrato = Contrato::find($contratos[$i]);

            if ($contrato && $contrato->olt_sn_mac && $empresa->adminOLT != null) {
                $curl = curl_init();

                if ($state == 'enabled') {
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $empresa->adminOLT . '/api/onu/enable_catv/' . $contrato->olt_sn_mac,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'POST',
                        CURLOPT_HTTPHEADER => array(
                            'X-token: ' . $empresa->smartOLT
                        ),
                    ));
                } else if ($state == 'disabled') {
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $empresa->adminOLT . '/api/onu/disable_catv/' . $contrato->olt_sn_mac,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'POST',
                        CURLOPT_HTTPHEADER => array(
                            'X-token: ' . $empresa->smartOLT
                        ),
                    ));
                }

                $response = curl_exec($curl);
                $response = json_decode($response);

                if (isset($response->status) && $response->status == true) {
                    if ($state == 'disabled') {
                        $contrato->state_olt_catv = 0;
                    } else {
                        $contrato->state_olt_catv = 1;
                    }
                    $contrato->save();
                    $succ++;
                } else {
                    $fail++;
                }

                curl_close($curl);
            } else {
                if (!$contrato || !$contrato->olt_sn_mac || $empresa->adminOLT == null) {
                    $fail++;
                }
            }
        }

        return response()->json([
            'success'   => true,
            'fallidos'  => $fail,
            'correctos' => $succ,
            'state'     => $state
        ]);
    }

    public function enviar_mk_lote($contratos)
    {

        $this->getAllPermissions(Auth::user()->id);

        $succ = 0;
        $fail = 0;
        $registro = false;
        $contracts_fallidos = '';
        $contracts_correctos = '';

        $contratos = explode(",", $contratos);

        for ($i = 0; $i < count($contratos); $i++) {

            if ($i == 0) {
                $microtik = str_replace('m', '', $contratos[$i]);
            } else {
                $contrato = Contrato::find($contratos[$i]);

                if ($contrato) {

                    if ($contrato->mk == 1) {
                        $plan = PlanesVelocidad::where('id', $contrato->plan_id)->first();

                        if (!$plan) {
                            $fail++;
                            $contracts_fallidos .= 'Nro ' . $contrato->nro . ' (Sin plan asignado)<br>';
                            continue;
                        }

                        $mikrotik = Mikrotik::where('id', $microtik)->first();
                        $mikrotik_plan = ($plan) ? Mikrotik::where('id', $plan->mikrotik)->first() : false;


                        $cliente = $contrato->cliente();
                        $servicio = $cliente->nombre . ' ' . $cliente->apellido1 . ' ' . $cliente->apellido2;

                        if ($mikrotik) {

                            $API = new RouterosAPI();
                            $API->port = $mikrotik->puerto_api;
                            //$API->debug = true;

                            if ($API->connect($mikrotik->ip, $mikrotik->usuario, $mikrotik->clave)) {
                                ## ELIMINAMOS DE MK ##

                                if ($contrato->conexion == 1) {
                                    //OBTENEMOS AL CONTRATO MK
                                    $mk_user = $API->comm(
                                        "/ppp/secret/getall",
                                        array(
                                            "?remote-address" => $contrato->ip,
                                        )
                                    );
                                    if ($mk_user) {
                                        // REMOVEMOS EL SECRET
                                        $API->comm(
                                            "/ppp/secret/remove",
                                            array(
                                                ".id" => $mk_user[0][".id"],
                                            )
                                        );
                                    }
                                }

                                if ($contrato->conexion == 2) {
                                    $name = $API->comm(
                                        "/ip/dhcp-server/lease/getall",
                                        array(
                                            "?address" => $contrato->ip
                                        )
                                    );
                                    if ($name) {
                                        // REMOVEMOS EL IP DHCP
                                        $API->comm(
                                            "/ip/dhcp-server/lease/remove",
                                            array(
                                                ".id" => $name[0][".id"],
                                            )
                                        );
                                    }
                                }

                                if ($contrato->conexion == 3) {
                                    //OBTENEMOS AL CONTRATO MK
                                    $mk_user = $API->comm(
                                        "/ip/arp/getall",
                                        array(
                                            "?address" => $contrato->ip // IP DEL CLIENTE
                                        )
                                    );
                                    if ($mk_user) {
                                        // REMOVEMOS EL IP ARP
                                        $API->comm(
                                            "/ip/arp/remove",
                                            array(
                                                ".id" => $mk_user[0][".id"],
                                            )
                                        );
                                    }
                                }

                                #ELMINAMOS DEL QUEUE#
                                $queue = $API->comm(
                                    "/queue/simple/getall",
                                    array(
                                        "?target" => $contrato->ip . '/32'
                                    )
                                );

                                if ($queue) {
                                    $API->comm(
                                        "/queue/simple/remove",
                                        array(
                                            ".id" => $queue[0][".id"],
                                        )
                                    );
                                }
                                #ELMINAMOS DEL QUEUE#

                                #ELIMINAMOS DE IP_AUTORIZADAS#
                                $API->write('/ip/firewall/address-list/print', TRUE);
                                $ARRAYS = $API->read();

                                $API->write('/ip/firewall/address-list/print', false);
                                $API->write('?address=' . $contrato->ip, false);
                                $API->write("?list=ips_autorizadas", false);
                                $API->write('=.proplist=.id');
                                $ARRAYS = $API->read();

                                if (count($ARRAYS) > 0) {
                                    $API->write('/ip/firewall/address-list/remove', false);
                                    $API->write('=.id=' . $ARRAYS[0]['.id']);
                                    $READ = $API->read();
                                }
                                #ELIMINAMOS DE IP_AUTORIZADAS#
                                ## ELIMINAMOS DE MK ##

                                $rate_limit      = '';
                                $priority        = $plan->prioridad;
                                $burst_limit     = (strlen($plan->burst_limit_subida) > 1) ? $plan->burst_limit_subida . '/' . $plan->burst_limit_bajada : 0;
                                $burst_threshold = (strlen($plan->burst_threshold_subida) > 1) ? $plan->burst_threshold_subida . '/' . $plan->burst_threshold_bajada : 0;
                                $burst_time      = ($plan->burst_time_subida) ? $plan->burst_time_subida . '/' . $plan->burst_time_bajada : 0;
                                $limit_at        = (strlen($plan->limit_at_subida) > 1) ? $plan->limit_at_subida . '/' . $plan->limit_at_bajada : 0;
                                $max_limit       = $plan->upload . '/' . $plan->download;

                                if ($max_limit) {
                                    $rate_limit .= $max_limit;
                                }
                                if (strlen($burst_limit) > 3) {
                                    $rate_limit .= ' ' . $burst_limit;
                                }
                                if (strlen($burst_threshold) > 3) {
                                    $rate_limit .= ' ' . $burst_threshold;
                                }
                                if (strlen($burst_time) > 3) {
                                    $rate_limit .= ' ' . $burst_time;
                                }
                                if ($priority) {
                                    $rate_limit .= ' ' . $priority;
                                }
                                if ($limit_at) {
                                    $rate_limit .= ' ' . $limit_at;
                                }

                                /*PPPOE*/
                                if ($contrato->conexion == 1) {
                                    $API->comm(
                                        "/ppp/secret/add",
                                        array(
                                            "name"           => $contrato->usuario,
                                            "password"       => $contrato->password,
                                            "profile"        => 'default',
                                            "local-address"  => $contrato->ip,
                                            "remote-address" => $contrato->ip,
                                            "service"        => 'pppoe',
                                            "comment"        => $this->normaliza($servicio) . '-' . $contrato->nro
                                        )
                                    );

                                    $getall = $API->comm(
                                        "/ppp/secret/getall",
                                        array(
                                            "?local-address" => $contrato->ip
                                        )
                                    );
                                }

                                /*DHCP*/
                                if ($contrato->conexion == 2) {
                                    if (isset($plan->dhcp_server)) {
                                        if ($contrato->simple_queue == 'dinamica') {
                                            $API->comm("/ip/dhcp-server/set\n=name=" . $plan->dhcp_server . "\n=address-pool=static-only\n=parent-queue=" . $plan->parenta);
                                            $API->comm(
                                                "/ip/dhcp-server/lease/add",
                                                array(
                                                    "comment"     => $this->normaliza($servicio) . '-' . $contrato->nro,
                                                    "address"     => $contrato->ip,
                                                    "server"      => $plan->dhcp_server,
                                                    "mac-address" => $contrato->mac_address,
                                                    "rate-limit"  => $rate_limit
                                                )
                                            );
                                        } elseif ($contrato->simple_queue == 'estatica') {
                                            $API->comm(
                                                "/ip/dhcp-server/lease/add",
                                                array(
                                                    "comment"     => $this->normaliza($servicio) . '-' . $contrato->nro,
                                                    "address"     => $contrato->ip,
                                                    "server"      => $plan->dhcp_server,
                                                    "mac-address" => $contrato->mac_address
                                                )
                                            );
                                        }

                                        $getall = $API->comm(
                                            "/ip/dhcp-server/lease/getall",
                                            array(
                                                "?address" => $contrato->ip
                                            )
                                        );
                                    } else {
                                        $mensaje = 'NO SE HA PODIDO EDITAR EL CONTRATO DE SERVICIOS, NO EXISTE UN SERVIDOR DHCP DEFINIDO PARA EL PLAN ' . $plan->name;
                                        return redirect('empresa/contratos')->with('danger', $mensaje);
                                    }
                                }

                                /*IP ESTÁTICA*/
                                if ($contrato->conexion == 3) {
                                    if ($mikrotik->amarre_mac == 1) {
                                        $API->comm(
                                            "/ip/arp/add",
                                            array(
                                                "comment"     => $this->normaliza($servicio) . '-' . $contrato->nro,
                                                "address"     => $contrato->ip,
                                                "interface"   => $contrato->interfaz,
                                                "mac-address" => $contrato->mac_address
                                            )
                                        );



                                        $getall = $API->comm(
                                            "/ip/arp/getall",
                                            array(
                                                "?address" => $contrato->ip
                                            )
                                        );
                                    }

                                    if (!empty($plan->queue_type_subida) && !empty($plan->queue_type_bajada)) {
                                        // Si tienen datos, asignar "queue" con los valores de subida y bajada
                                        $queue_edit = $plan->queue_type_subida . '/' . $plan->queue_type_bajada;
                                    } else {
                                        // Si no tienen datos, asignar "queue" con los valores predeterminados
                                        $queue_edit = "default-small/default-small";
                                    }

                                    // Eliminar todas las colas asociadas a la IP
                                    foreach ($queue as $q) {
                                        $API->comm("/queue/simple/remove", array(
                                            ".id" => $q['.id']
                                        ));
                                    }

                                    $response = $API->comm(
                                        "/queue/simple/add",
                                        array(
                                            "name"            => $this->normaliza($servicio) . '-' . $contrato->nro,
                                            "target"          => $contrato->ip,
                                            "max-limit"       => $plan->upload . '/' . $plan->download,
                                            "burst-limit"     => $burst_limit,
                                            "burst-threshold" => $burst_threshold,
                                            "burst-time"      => $burst_time,
                                            "priority"        => $priority,
                                            "limit-at"        => $limit_at,
                                            "queue"           => $queue_edit
                                        )
                                    );
                                }


                                /*VLAN*/
                                if ($contrato->conexion == 4) {
                                }

                                $registro = true;
                                $queue = $API->comm(
                                    "/queue/simple/getall",
                                    array(
                                        "?target" => $contrato->ip . '/32'
                                    )
                                );

                                if ($queue) {
                                    $API->comm(
                                        "/queue/simple/set",
                                        array(
                                            ".id"             => $queue[0][".id"],
                                            "target"          => $contrato->ip,
                                            "max-limit"       => $plan->upload . '/' . $plan->download,
                                            "burst-limit"     => $burst_limit,
                                            "burst-threshold" => $burst_threshold,
                                            "burst-time"      => $burst_time,
                                            "priority"        => $priority,
                                            "limit-at"        => $limit_at
                                        )
                                    );
                                } else {
                                    $API->comm(
                                        "/queue/simple/add",
                                        array(
                                            "name"            => $this->normaliza($servicio) . '-' . $contrato->nro,
                                            "target"          => $contrato->ip,
                                            "max-limit"       => $plan->upload . '/' . $plan->download,
                                            "burst-limit"     => $burst_limit,
                                            "burst-threshold" => $burst_threshold,
                                            "burst-time"      => $burst_time,
                                            "priority"        => $priority,
                                            "limit-at"        => $limit_at
                                        )
                                    );
                                }

                                #AGREGAMOS A IP_AUTORIZADAS#
                                $API->comm(
                                    "/ip/firewall/address-list/add",
                                    array(
                                        "address" => $contrato->ip,
                                        "list" => 'ips_autorizadas'
                                    )
                                );
                                #AGREGAMOS A IP_AUTORIZADAS#
                            } else {
                                $fail++;
                            }

                            $API->disconnect();

                            if ($registro) {
                                $contrato->mk = 1;
                                $contrato->state = 'enabled';
                                $contrato->servicio = $this->normaliza($servicio) . '-' . $contrato->nro;
                                $contrato->server_configuration_id = $mikrotik->id;
                                $contrato->save();
                                $succ++;
                                $contracts_fallidos .= 'Nro ' . $contrato->nro . '<br>';
                            }
                        }
                    } else {
                        $fail++;
                        $contracts_fallidos .= 'Nro ' . $contrato->nro . '<br>';
                    }
                }
            }
        }

        return response()->json([
            'success'             => true,
            'fallidos'            => $fail,
            'correctos'           => $succ,
            'contracts_fallidos'  => $contracts_fallidos,
            'contracts_correctos' => $contracts_correctos
        ]);
    }

    public function importar()
    {
        $this->getAllPermissions(Auth::user()->id);
        view()->share(['title' => 'Importar Contratos Internet desde Excel', 'full' => true]);

        $mikrotiks = Mikrotik::all();
        $planes = PlanesVelocidad::all();
        $grupos = GrupoCorte::all();
        return view('contratos.importar')->with(compact('mikrotiks', 'planes', 'grupos'));
    }

    public function actualizar()
    {
        $this->getAllPermissions(Auth::user()->id);
        view()->share(['title' => 'Actualizar Contratos Internet desde Excel', 'full' => true]);

        $mikrotiks = Mikrotik::all();
        $planes = PlanesVelocidad::all();
        $grupos = GrupoCorte::all();

        return view('contratos.actualizar')->with(compact('mikrotiks', 'planes', 'grupos'));
    }

    public function data_ejemplo(Request $request){

        // Obtener el tipo de conexión del request
        $conexion = $request->input('conexion');
        // Convertir valor 0 a 3 para IP Estática (mantener compatibilidad)
        if ($conexion == 0) {
            $conexion = 3;
        }

        // Estructura unificada con todos los campos independiente del tipo de conexión
        $titulosColumnas = array(
            'Nro Contrato', 'Identificacion', 'Servicio', 'Serial ONU', 'OLT SN MAC', 'Plan', 'Mikrotik', 'Estado',
            'IP', 'MAC', 'Conexion', 'Interfaz', 'Local Address / Segmento', 'Simple Queue', 'Tipo de Tecnologia',
            'Nombre de la Caja NAP', 'Nodo', 'Access Point', 'Grupo de Corte', 'Facturacion', 'Descuento',
            'Canal', 'Oficina', 'Tecnologia', 'Fecha del Contrato', 'Cliente en Mikrotik', 'Tipo Contrato',
            'Profile', 'IP Local Address', 'Usuario', 'Contrasena', 'Linea', 'Estrato', 'Direccion', 'Precio Personalizado Internet', 'Precio Personalizado TV'
        );

        // Comentarios detallados para cada campo con información de obligatoriedad y tipo de conexión
        $comentarios = array(
            'A' => 'Número de contrato existente para actualizar. Solo para actualización. Dejar vacío para crear nuevo contrato.',
            'B' => 'NIT/Cédula del cliente ya registrado en el sistema. Obligatorio en todos los tipos de conexión.',
            'C' => 'Nombre del servicio. Obligatorio en todos los tipos de conexión.',
            'D' => 'Serial de la ONU. Opcional en todos los tipos de conexión.',
            'E' => 'Serial MAC de la OLT. Opcional en todos los tipos de conexión.',
            'F' => 'Nombre del plan ya registrado en el sistema. Obligatorio en todos los tipos de conexión.',
            'G' => 'Nombre de la mikrotik ya registrado en el sistema. Obligatorio en todos los tipos de conexión.',
            'H' => 'Estado del contrato: Habilitado o Deshabilitado. Obligatorio en todos los tipos de conexión.',
            'I' => 'Dirección IP del cliente. Obligatorio para PPPoE, IP Estática y VLAN. No aplica para DHCP.',
            'J' => 'Dirección MAC del cliente. Opcional en todos los tipos de conexión.',
            'K' => 'Tipo de conexión: PPPOE, DHCP, IP Estatica o VLAN. Obligatorio en todos los tipos de conexión.',
            'L' => 'Interfaz de red. Obligatorio para PPPoE, IP Estática y VLAN. No aplica para DHCP.',
            'M' => 'Dirección local o segmento de red. Opcional para PPPoE, IP Estática y VLAN. No aplica para DHCP.',
            'N' => 'Nombre de la cola simple configurada en Mikrotik (Dinamica o Estatica). Obligatorio solo para DHCP. No aplica para otros tipos.',
            'O' => 'Tipo de tecnología adicional. Opcional y solo aplica para DHCP. No aplica para otros tipos.',
            'P' => 'Nombre de la caja NAP ya registrada en el sistema. Opcional y principalmente usado para DHCP. No aplica para otros tipos.',
            'Q' => 'Nombre del nodo ya registrado en el sistema. Opcional para PPPoE, IP Estática y VLAN. No aplica para DHCP.',
            'R' => 'Nombre del access point ya registrado en el sistema. Opcional para PPPoE, IP Estática y VLAN. No aplica para DHCP.',
            'S' => 'Nombre del grupo de corte ya registrado en el sistema. Obligatorio en todos los tipos de conexión.',
            'T' => 'Tipo de facturación: Estandar o Electronica. Obligatorio en todos los tipos de conexión.',
            'U' => 'Porcentaje o valor de descuento. Opcional en todos los tipos de conexión.',
            'V' => 'Nombre del canal ya registrado en el sistema. Opcional en todos los tipos de conexión.',
            'W' => 'Nombre de la oficina ya registrada en el sistema. Opcional en todos los tipos de conexión.',
            'X' => 'Tipo de tecnología: Fibra, Inalambrica o Cableado UTP. Obligatorio en todos los tipos de conexión.',
            'Y' => 'Fecha del contrato en formato yyyy-mm-dd hh:mm:ss. Opcional en todos los tipos de conexión.',
            'Z' => 'Indique si el cliente existe en Mikrotik: Si o No. Obligatorio en todos los tipos de conexión.',
            'AA' => 'Tipo de contrato. Opcional en todos los tipos de conexión.',
            'AB' => 'Profile de PPPoE. Obligatorio solo para PPPoE. No aplica para otros tipos.',
            'AC' => 'IP Local Address para PPPoE. Obligatorio solo para PPPoE. No aplica para otros tipos.',
            'AD' => 'Usuario para conexión PPPoE. Obligatorio solo para PPPoE. No aplica para otros tipos.',
            'AE' => 'Contraseña para conexión PPPoE. Obligatorio solo para PPPoE. No aplica para otros tipos.',
            'AF' => 'Línea. Opcional en todos los tipos de conexión.',
            'AG' => 'Estrato del contrato. Opcional.',
            'AH' => 'Dirección de instalación del contrato. Opcional.',
            'AI' => 'Precio personalizado de Internet. Opcional.',
            'AJ' => 'Precio personalizado de TV. Opcional.'
        );
        $objPHPExcel = new PHPExcel();
        $tituloReporte = "Archivo de actualizacion de Contratos Internet " . Auth::user()->empresa()->nombre;

        $letras = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ');
        $ultimaColumna = $letras[count($titulosColumnas) - 1];

        $objPHPExcel->getProperties()->setCreator("Sistema")
            ->setLastModifiedBy("Sistema")
            ->setTitle("Archivo Actualización Contratos")
            ->setSubject("Archivo Actualización Contratos")
            ->setDescription("Archivo Actualización Contratos")
            ->setKeywords("Archivo Actualización Contratos")
            ->setCategory("Archivo Actualización");

        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('A1:' . $ultimaColumna . '1');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $tituloReporte);
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('A2:' . $ultimaColumna . '2');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A2', 'Fecha ' . date('d-m-Y'));

        $estilo = array(
            'font'  => array(
                'bold'  => true,
                'size'  => 12,
                'name'  => 'Times New Roman'
            ),
            'alignment' => array(
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER
            )
        );

        $objPHPExcel->getActiveSheet()->getStyle('A1:' . $ultimaColumna . '3')->applyFromArray($estilo);

        $estilo = array(
            'fill' => array(
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('rgb' => substr(Auth::user()->empresa()->color, 1))
            ),
            'font'  => array(
                'bold'  => true,
                'size'  => 12,
                'name'  => 'Times New Roman',
                'color' => array(
                    'rgb' => 'FFFFFF'
                ),
            ),
            'alignment' => array(
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER
            )
        );

        $objPHPExcel->getActiveSheet()->getStyle('A3:' . $ultimaColumna . '3')->applyFromArray($estilo);

        for ($i = 0; $i < count($titulosColumnas); $i++) {
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($letras[$i] . '3', utf8_decode($titulosColumnas[$i]));
        }

        // Obtener contratos de la empresa filtrados por tipo de conexión
        $contratos = Contrato::where('empresa', Auth::user()->empresa)
            ->where('conexion', $conexion)
            ->get();
        $j = 4;

        // Agregar comentarios dinámicamente
        foreach ($comentarios as $columna => $texto) {
            $objPHPExcel->getActiveSheet()->getComment($columna . '3')->setAuthor('Integra Colombia')->getText()->createTextRun($texto);
        }

        $estilo = array(
            'font'  => array('size'  => 12, 'name'  => 'Times New Roman'),
            'borders' => array(
                'allborders' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN
                )
            ),
            'alignment' => array(
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER
            )
        );

        $objPHPExcel->getActiveSheet()->getStyle('A3:' . $ultimaColumna . '3')->applyFromArray($estilo);

        for ($i = 'A'; $i <= $ultimaColumna; $i++) {
            $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension($i)->setAutoSize(TRUE);
        }

        // Llenado de datos con estructura unificada
        foreach ($contratos as $contrato) {
            $cliente = $contrato->cliente();
            $plan = PlanesVelocidad::find($contrato->plan_id);
            $microtik = Mikrotik::find($contrato->server_configuration_id);
            $grupo = GrupoCorte::find($contrato->grupo_corte);
            $facturacion = $contrato->facturacion == 1 ? 'Estandar' : 'Electronica';

            $tecnologia_text = '';
            if ($contrato->tecnologia == 1) {
                $tecnologia_text = 'Fibra';
            } elseif ($contrato->tecnologia == 2) {
                $tecnologia_text = 'Inalambrica';
            } elseif ($contrato->tecnologia == 3) {
                $tecnologia_text = 'Cableado UTP';
            }

            $conexion_text = '';
            if ($contrato->conexion == 1) {
                $conexion_text = 'PPPOE';
            } elseif ($contrato->conexion == 2) {
                $conexion_text = 'DHCP';
            } elseif ($contrato->conexion == 3) {
                $conexion_text = 'IP Estatica';
            } elseif ($contrato->conexion == 4) {
                $conexion_text = 'VLAN';
            }

            $cajaNap = $contrato->cajanap_id ? CajaNap::find($contrato->cajanap_id) : null;
            $nodo_obj = $contrato->nodo ? Nodo::find($contrato->nodo) : null;
            $ap_obj = $contrato->ap ? AP::find($contrato->ap) : null;

            // Estructura unificada - todos los campos siempre presentes
            $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue("A$j", $contrato->nro ?? '')
                ->setCellValue("B$j", $cliente ? $cliente->nit : '')
                ->setCellValue("C$j", $contrato->servicio ?? '')
                ->setCellValue("D$j", $contrato->serial_onu ?? '')
                ->setCellValue("E$j", $contrato->olt_sn_mac ?? '')
                ->setCellValue("F$j", $plan ? $plan->name : '')
                ->setCellValue("G$j", $microtik ? $microtik->nombre : '')
                ->setCellValue("H$j", $contrato->state ?? '')
                ->setCellValue("I$j", $contrato->ip ?? '')
                ->setCellValue("J$j", $contrato->mac_address ?? '')
                ->setCellValue("K$j", $conexion_text)
                ->setCellValue("L$j", $contrato->interfaz ?? '')
                ->setCellValue("M$j", $contrato->local_address ?? '')
                ->setCellValue("N$j", $contrato->simple_queue ?? '')
                ->setCellValue("O$j", $contrato->tipo_tecnologia ?? '')
                ->setCellValue("P$j", $cajaNap ? $cajaNap->nombre : '')
                ->setCellValue("Q$j", $nodo_obj ? $nodo_obj->nombre : '')
                ->setCellValue("R$j", $ap_obj ? $ap_obj->nombre : '')
                ->setCellValue("S$j", $grupo ? $grupo->nombre : '')
                ->setCellValue("T$j", $facturacion ?? '')
                ->setCellValue("U$j", $contrato->descuento ?? '')
                ->setCellValue("V$j", $contrato->canal ?? '')
                ->setCellValue("W$j", $contrato->oficina ?? '')
                ->setCellValue("X$j", $tecnologia_text)
                ->setCellValue("Y$j", $contrato->created_at ?? '')
                ->setCellValue("Z$j", $contrato->mk ? 'Si' : 'No')
                ->setCellValue("AA$j", $contrato->tipo_contrato ?? '')
                ->setCellValue("AB$j", $contrato->profile ?? '')
                ->setCellValue("AC$j", $contrato->local_adress_pppoe ?? '')
                ->setCellValue("AD$j", $contrato->usuario ?? '')
                ->setCellValue("AE$j", $contrato->password ?? '')
                ->setCellValue("AF$j", $contrato->linea ?? '')
                ->setCellValue("AG$j", $contrato->estrato ?? '')
                ->setCellValue("AH$j", $contrato->address_street ?? '');

            $j++;
        }

        // Se asigna el nombre a la hoja
        $objPHPExcel->getActiveSheet()->setTitle('Contratos');

        // Se activa la hoja para que sea la que se muestre cuando el archivo se abre
        $objPHPExcel->setActiveSheetIndex(0);

        // Inmovilizar paneles
        $objPHPExcel->getActiveSheet(0)->freezePane('A4');
        $objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0, 4);
        $objPHPExcel->setActiveSheetIndex(0);
        header("Pragma: no-cache");
        header('Content-type: application/vnd.ms-excel');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="Archivo_Actualizacion_Contratos.xlsx"');
        header('Cache-Control: max-age=0');
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');
        exit;
    }


    public function ejemplo(Request $request)
    {
        // Estructura unificada con todos los campos independiente del tipo de conexión
        // Nota: Esta función es para importación, por lo que NO incluye "Nro Contrato"
        $titulosColumnas = array(
            'Identificacion', 'Servicio', 'Serial ONU', 'OLT SN MAC', 'Plan', 'Mikrotik', 'Estado',
            'IP', 'MAC', 'Conexion', 'Interfaz', 'Local Address / Segmento', 'Simple Queue', 'Tipo de Tecnologia',
            'Nombre de la Caja NAP', 'Nodo', 'Access Point', 'Grupo de Corte', 'Facturacion', 'Descuento',
            'Canal', 'Oficina', 'Tecnologia', 'Fecha del Contrato', 'Cliente en Mikrotik', 'Tipo Contrato',
            'Profile', 'IP Local Address', 'Usuario', 'Contrasena', 'Linea', 'Estrato', 'Direccion', 'Precio Personalizado Internet', 'Precio Personalizado TV'
        );

        // Comentarios detallados para cada campo con información de obligatoriedad y tipo de conexión
        $comentarios = array(
            'A' => 'NIT/Cédula del cliente ya registrado en el sistema. Obligatorio en todos los tipos de conexión.',
            'B' => 'Nombre del servicio. Obligatorio en todos los tipos de conexión.',
            'C' => 'Serial de la ONU. Opcional en todos los tipos de conexión.',
            'D' => 'Serial MAC de la OLT. Opcional en todos los tipos de conexión.',
            'E' => 'Nombre del plan ya registrado en el sistema. Obligatorio en todos los tipos de conexión.',
            'F' => 'Nombre de la mikrotik ya registrado en el sistema. Obligatorio en todos los tipos de conexión.',
            'G' => 'Estado del contrato: Habilitado o Deshabilitado. Obligatorio en todos los tipos de conexión.',
            'H' => 'Dirección IP del cliente. Obligatorio para PPPoE, IP Estática y VLAN. No aplica para DHCP.',
            'I' => 'Dirección MAC del cliente. Opcional en todos los tipos de conexión.',
            'J' => 'Tipo de conexión: PPPOE, DHCP, IP Estatica o VLAN. Obligatorio en todos los tipos de conexión.',
            'K' => 'Interfaz de red. Obligatorio para PPPoE, IP Estática y VLAN. No aplica para DHCP.',
            'L' => 'Dirección local o segmento de red. Opcional para PPPoE, IP Estática y VLAN. No aplica para DHCP.',
            'M' => 'Nombre de la cola simple configurada en Mikrotik (Dinamica o Estatica). Obligatorio solo para DHCP. No aplica para otros tipos.',
            'N' => 'Tipo de tecnología adicional. Opcional y solo aplica para DHCP. No aplica para otros tipos.',
            'O' => 'Nombre de la caja NAP ya registrada en el sistema. Opcional y principalmente usado para DHCP. No aplica para otros tipos.',
            'P' => 'Nombre del nodo ya registrado en el sistema. Opcional para PPPoE, IP Estática y VLAN. No aplica para DHCP.',
            'Q' => 'Nombre del access point ya registrado en el sistema. Opcional para PPPoE, IP Estática y VLAN. No aplica para DHCP.',
            'R' => 'Nombre del grupo de corte ya registrado en el sistema. Obligatorio en todos los tipos de conexión.',
            'S' => 'Tipo de facturación: Estandar o Electronica. Obligatorio en todos los tipos de conexión.',
            'T' => 'Porcentaje o valor de descuento. Opcional en todos los tipos de conexión.',
            'U' => 'Nombre del canal ya registrado en el sistema. Opcional en todos los tipos de conexión.',
            'V' => 'Nombre de la oficina ya registrada en el sistema. Opcional en todos los tipos de conexión.',
            'W' => 'Tipo de tecnología: Fibra, Inalambrica o Cableado UTP. Obligatorio en todos los tipos de conexión.',
            'X' => 'Fecha del contrato en formato yyyy-mm-dd hh:mm:ss. Opcional en todos los tipos de conexión.',
            'Y' => 'Indique si el cliente existe en Mikrotik: Si o No. Obligatorio en todos los tipos de conexión.',
            'Z' => 'Tipo de contrato. Opcional en todos los tipos de conexión.',
            'AA' => 'Profile de PPPoE. Obligatorio solo para PPPoE. No aplica para otros tipos.',
            'AB' => 'IP Local Address para PPPoE. Obligatorio solo para PPPoE. No aplica para otros tipos.',
            'AC' => 'Usuario para conexión PPPoE. Obligatorio solo para PPPoE. No aplica para otros tipos.',
            'AD' => 'Contraseña para conexión PPPoE. Obligatorio solo para PPPoE. No aplica para otros tipos.',
            'AE' => 'Línea. Opcional en todos los tipos de conexión.',
            'AF' => 'Estrato del contrato. Opcional.',
            'AG' => 'Dirección de instalación del contrato. Opcional.',
            'AH' => 'Precio personalizado de Internet. Opcional.',
            'AI' => 'Precio personalizado de TV. Opcional.'
        );
        $objPHPExcel = new PHPExcel();
        $tituloReporte = "Archivo de Importación de Contratos Internet " . Auth::user()->empresa()->nombre;

        $letras = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI');
        $ultimaColumna = $letras[count($titulosColumnas) - 1];

        $objPHPExcel->getProperties()->setCreator("Sistema") // Nombre del autor
            ->setLastModifiedBy("Sistema") //Ultimo usuario que lo modific171717
            ->setTitle("Archivo Importacion Contratos") // Titulo
            ->setSubject("Archivo Importacion Contratos") //Asunto
            ->setDescription("Archivo Importacion Contratos") //Descripción
            ->setKeywords("Archivo Importacion Contratos") //Etiquetas
            ->setCategory("Archivo Importacion"); //Categorias
        // Se combinan las celdas A1 hasta D1, para colocar ah171717 el titulo del reporte
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('A1:' . $ultimaColumna . '1');
        // Se agregan los titulos del reporte
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $tituloReporte);
        // Titulo del reporte
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('A2:' . $ultimaColumna . '2');
        // Se agregan los titulos del reporte
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A2', 'Fecha ' . date('d-m-Y')); // Titulo del reporte

        $estilo = array(
            'font'  => array(
                'bold'  => true,
                'size'  => 12,
                'name'  => 'Times New Roman'
            ),
            'alignment' => array(
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER
            )
        );

        $objPHPExcel->getActiveSheet()->getStyle('A1:' . $ultimaColumna . '3')->applyFromArray($estilo);

        $estilo = array(
            'fill' => array(
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('rgb' => substr(Auth::user()->empresa()->color, 1))
            ),
            'font'  => array(
                'bold'  => true,
                'size'  => 12,
                'name'  => 'Times New Roman',
                'color' => array(
                    'rgb' => 'FFFFFF'
                ),
            ),
            'alignment' => array(
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER
            )
        );

        $objPHPExcel->getActiveSheet()->getStyle('A3:' . $ultimaColumna . '3')->applyFromArray($estilo);

        for ($i = 0; $i < count($titulosColumnas); $i++) {
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($letras[$i] . '3', utf8_decode($titulosColumnas[$i]));
        }

        // Agregar comentarios dinámicamente
        foreach ($comentarios as $columna => $texto) {
            $objPHPExcel->getActiveSheet()->getComment($columna . '3')->setAuthor('Integra Colombia')->getText()->createTextRun($texto);
        }

        $estilo = array(
            'font'  => array('size'  => 12, 'name'  => 'Times New Roman'),
            'borders' => array(
                'allborders' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN
                )
            ),
            'alignment' => array(
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER
            )
        );

        $objPHPExcel->getActiveSheet()->getStyle('A3:' . $ultimaColumna . '3')->applyFromArray($estilo);

        for ($i = 'A'; $i <= $ultimaColumna; $i++) {
            $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension($i)->setAutoSize(TRUE);
        }

        // Se asigna el nombre a la hoja
        $objPHPExcel->getActiveSheet()->setTitle('Contratos');

        // Se activa la hoja para que sea la que se muestre cuando el archivo se abre
        $objPHPExcel->setActiveSheetIndex(0);

        // Inmovilizar paneles - Solo congelar filas 1-3 (título, fecha y encabezados)
        // La fila 4 forma parte de los datos y no debe estar congelada
        $objPHPExcel->getActiveSheet(0)->freezePane('A4');
        $objPHPExcel->setActiveSheetIndex(0);
        header("Pragma: no-cache");
        header('Content-type: application/vnd.ms-excel');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="Archivo_Actualizacion_Contratos.xlsx"');
        header('Cache-Control: max-age=0');
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');
        exit;
    }

    public function cargando(Request $request)
    {
        $create = 0;
        $modf = 0;

        // Opciones de auto-relleno (enviadas desde el modal de configuración)
        $autofillIp = $request->input('autofill_ip', 0);
        $autofillIpCount = 0;
        
        $autofillState = $request->input('autofill_state', 0);
        $autofillStateValue = $request->input('autofill_state_value', 'habilitado');
        $autofillStateCount = 0;
        
        $autofillFacturacion = $request->input('autofill_facturacion', 0);
        $autofillFacturacionValue = $request->input('autofill_facturacion_value', 'estandar');
        $autofillFacturacionCount = 0;

        $path = public_path() . '/images/Empresas/Empresa' . Auth::user()->empresa;

        // Si viene del modal de configuración, re-usar el archivo ya subido
        if ($request->input('archivo_guardado')) {
            $nombre_imagen = $request->input('archivo_guardado');
            $fileWithPath = $path . "/" . $nombre_imagen;
            if (!file_exists($fileWithPath)) {
                return back()->withErrors(['archivo' => 'El archivo ya no existe. Por favor, vuelva a subirlo.'])->withInput();
            }
        } else {
            // Primera vez: validar y subir archivo
            $request->validate([
                'archivo' => 'required|mimes:xlsx',
            ], [
                'archivo.mimes' => 'El archivo debe ser de extensión xlsx'
            ]);
            $imagen = $request->file('archivo');
            $nombre_imagen = 'archivo.' . $imagen->getClientOriginalExtension();
            $imagen->move($path, $nombre_imagen);
            $fileWithPath = $path . "/" . $nombre_imagen;
        }

        Ini_set('max_execution_time', 500);
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

        // Array para recopilar todas las identificaciones no encontradas
        $identificacionesNoEncontradas = [];
        $ipsDuplicadas = [];
        $ipsRegistradasEnArchivo = [];
        $validacionesSolucionables = [];
        $erroresRecopilados = [];

        // Verificar el encabezado en la fila 3 para determinar si es actualización
        // Si la columna A en la fila 3 dice "Nro Contrato", entonces todas las filas tienen nro contrato
        $encabezadoColumnaA = trim((string) $sheet->getCell("A3")->getValue());
        $tieneNroContratoEnEncabezado = (strtoupper($encabezadoColumnaA) === 'NRO CONTRATO' ||
                                         strtoupper($encabezadoColumnaA) === 'NRO. CONTRATO' ||
                                         strtoupper($encabezadoColumnaA) === 'NUMERO CONTRATO' ||
                                         strtoupper($encabezadoColumnaA) === 'NÚMERO CONTRATO');

        for ($row = 4; $row <= $highestRow; $row++) {
            $request = (object) array();
            //obtengo el A4 desde donde empieza la data
            $valorColumnaA = $sheet->getCell("A" . $row)->getValue();
            if (empty($valorColumnaA)) {
                break;
            }

            // Si el encabezado indica que hay Nro Contrato, todas las filas se tratan como actualización
            if ($tieneNroContratoEnEncabezado) {
                $esNroContrato = true;
                $nro_contrato_actualizar = is_numeric($valorColumnaA) ? $valorColumnaA : null;
                $nit_raw = $sheet->getCell("B" . $row)->getValue(); // Si hay nro contrato, la identificación está en B
            } else {
                // Sin encabezado de Nro Contrato, tratar A como identificación
                $esNroContrato = false;
                $nro_contrato_actualizar = null;
                $nit_raw = $valorColumnaA;
            }

            // Limpiar NIT de formatos Excel (decimales) o guiones de verificación
            $nit_str = trim((string)$nit_raw);
            if (strpos($nit_str, '-') !== false) {
                $nit_parts = explode('-', $nit_str);
                    // $nit = trim($nit_parts[0]);
            } else {
                // $nit = preg_replace('/\.0+$/', '', $nit_str);
            }

            // Datos comunes - ajustar columnas según si hay nro contrato
            // IMPORTANTE: Cuando hay Nro Contrato en A, TODAS las columnas se desplazan una posición a la derecha
            // Estructura CON Nro Contrato: A=Nro, B=Identificacion, C=Servicio, D=Serial ONU, E=OLT SN MAC, F=Plan, G=Mikrotik, H=Estado
            // Estructura SIN Nro Contrato: A=Identificacion, B=Servicio, C=Serial ONU, D=OLT SN MAC, E=Plan, F=Mikrotik, G=Estado
            $nit = $this->cleanIdentification($nit_raw);
            if ($esNroContrato) {
                // CON nro contrato: todo desplazado una columna a la derecha
                $request->servicio   = $this->repairEncoding($sheet->getCell("C" . $row)->getValue());  // C = Servicio
                $request->serial_onu = $sheet->getCell("D" . $row)->getValue();  // D = Serial ONU
                $request->olt_sn_mac = $sheet->getCell("E" . $row)->getValue();  // E = OLT SN MAC
                $request->plan       = $sheet->getCell("F" . $row)->getValue();  // F = Plan (NO E!)
                $request->mikrotik   = $sheet->getCell("G" . $row)->getValue();  // G = Mikrotik (NO F!)
                $request->state      = $sheet->getCell("H" . $row)->getValue();  // H = Estado
            } else {
                // SIN nro contrato: lectura normal
                $request->servicio   = $this->repairEncoding($sheet->getCell("B" . $row)->getValue());  // B = Servicio
                $request->serial_onu = $sheet->getCell("C" . $row)->getValue();  // C = Serial ONU
                $request->olt_sn_mac = $sheet->getCell("D" . $row)->getValue();  // D = OLT SN MAC
                $request->plan       = $sheet->getCell("E" . $row)->getValue();  // E = Plan
                $request->mikrotik   = $sheet->getCell("F" . $row)->getValue();  // F = Mikrotik
                $request->state      = $sheet->getCell("G" . $row)->getValue();  // G = Estado
            }

            // Limpiar y validar valores leídos
            if (!empty($request->plan)) {
                $request->plan = trim((string) $request->plan);
            }
            if (!empty($request->mikrotik)) {
                $request->mikrotik = trim((string) $request->mikrotik);
            }
            if (!empty($request->olt_sn_mac)) {
                $request->olt_sn_mac = trim((string) $request->olt_sn_mac);
            }

            // Aplicar strtolower a campos tipo texto antes de validar
            if (!empty($request->servicio)) {
                $request->servicio = strtolower(trim((string) $request->servicio));
            }
            if (!empty($request->plan)) {
                $request->plan = strtolower(trim((string) $request->plan));
            }
            if (!empty($request->mikrotik)) {
                $request->mikrotik = strtolower(trim((string) $request->mikrotik));
            }
            if (!empty($request->state)) {
                $request->state = strtolower(trim((string) $request->state));
            }

            // Leer conexión - columna J en estructura unificada (o I si no hay nro contrato)
            // En la estructura unificada, conexión está siempre en la misma posición relativa
            $colConexion = $esNroContrato ? 'K' : 'J'; // Si hay nro contrato, está en K, si no, en J
            $conexionCelda = $sheet->getCell($colConexion . $row)->getValue();
            $conexionTexto = strtoupper(trim((string) $conexionCelda));

            // También verificar en la columna Simple Queue si conexión está vacía o no reconocida
            if (empty($conexionTexto) || ($conexionTexto != 'PPPOE' && $conexionTexto != 'DHCP' && $conexionTexto != 'IP ESTATICA' && $conexionTexto != 'IP ESTÁTICA' && $conexionTexto != 'VLAN')) {
                $simpleQueueCol = $esNroContrato ? 'N' : 'M'; // Simple Queue en estructura unificada
                $simpleQueueVal = $sheet->getCell($simpleQueueCol . $row)->getValue();
                $simpleQueueTexto = strtoupper(trim((string) $simpleQueueVal));
                // Si tiene "DINAMICA" o "ESTATICA", es un indicador de que es DHCP
                if ($simpleQueueTexto == 'DINAMICA' || $simpleQueueTexto == 'DINÁMICA' || $simpleQueueTexto == 'ESTATICA' || $simpleQueueTexto == 'ESTÁTICA') {
                    $conexionTexto = 'DHCP';
                    $conexionCelda = 2;
                }
            }

            if ($conexionTexto == 'PPPOE' || $conexionCelda == 1) {
                $request->conexion = 1;
            } elseif ($conexionTexto == 'DHCP' || $conexionTexto == 'DINAMICA' || $conexionTexto == 'DINÁMICA' || $conexionCelda == 2) {
                $request->conexion = 2;
            } elseif ($conexionTexto == 'IP ESTATICA' || $conexionTexto == 'IP ESTÁTICA' || $conexionCelda == 3) {
                $request->conexion = 3;
            } elseif ($conexionTexto == 'VLAN' || $conexionCelda == 4) {
                $request->conexion = 4;
            } else {
                $request->conexion = $conexionCelda;
            }

            // Leer todos los campos de la estructura unificada
            // Las columnas son: IP=I, MAC=J, Conexion=K (o J si no hay nro), Interfaz=L, Local Address=M, Simple Queue=N, etc.
            $baseCol = $esNroContrato ? 1 : 0; // Offset si hay nro contrato

            // Campos comunes que están en la misma posición relativa
            $colIP = $esNroContrato ? 'I' : 'H';
            $colMAC = $esNroContrato ? 'J' : 'I';
            $colInterfaz = $esNroContrato ? 'L' : 'K';
            $colLocalAddr = $esNroContrato ? 'M' : 'L';
            $colSimpleQueue = $esNroContrato ? 'N' : 'M';
            $colTipoTec = $esNroContrato ? 'O' : 'N';
            $colCajaNap = $esNroContrato ? 'P' : 'O';
            $colNodo = $esNroContrato ? 'Q' : 'P';
            $colAP = $esNroContrato ? 'R' : 'Q';
            $colGrupoCorte = $esNroContrato ? 'S' : 'R';
            $colFacturacion = $esNroContrato ? 'T' : 'S';
            $colDescuento = $esNroContrato ? 'U' : 'T';
            $colCanal = $esNroContrato ? 'V' : 'U';
            $colOficina = $esNroContrato ? 'W' : 'V';
            $colTecnologia = $esNroContrato ? 'X' : 'W';
            $colFecha = $esNroContrato ? 'Y' : 'X';
            $colMK = $esNroContrato ? 'Z' : 'Y';
            $colTipoContrato = $esNroContrato ? 'AA' : 'Z';
            $colProfile = $esNroContrato ? 'AB' : 'AA';
            $colIPLocal = $esNroContrato ? 'AC' : 'AB';
            $colUsuario = $esNroContrato ? 'AD' : 'AC';
            $colClave = $esNroContrato ? 'AE' : 'AD';
            $colLinea = $esNroContrato ? 'AF' : 'AE';
            $colEstrato = $esNroContrato ? 'AG' : 'AF';
            $colAddressStreet = $esNroContrato ? 'AH' : 'AG';
            $colPrecioInternet = $esNroContrato ? 'AI' : 'AH';
            $colPrecioTV = $esNroContrato ? 'AJ' : 'AI';

            // Leer todos los campos de la estructura unificada
            $request->ip = $sheet->getCell($colIP . $row)->getValue();
            $request->mac = $sheet->getCell($colMAC . $row)->getValue();
            $request->interfaz = $sheet->getCell($colInterfaz . $row)->getValue();
            $request->local_address = $sheet->getCell($colLocalAddr . $row)->getValue();
            $request->simple_queue = $sheet->getCell($colSimpleQueue . $row)->getValue();
            $request->tipo_tecnologia = $sheet->getCell($colTipoTec . $row)->getValue();
            $request->puerto_caja_nap = $sheet->getCell($colCajaNap . $row)->getValue();
            $request->nodo = $sheet->getCell($colNodo . $row)->getValue();
            $request->ap = $sheet->getCell($colAP . $row)->getValue();
            $request->grupo_corte = $sheet->getCell($colGrupoCorte . $row)->getValue();
            $request->facturacion = $sheet->getCell($colFacturacion . $row)->getValue();
            $request->descuento = $sheet->getCell($colDescuento . $row)->getValue();
            $request->canal = $sheet->getCell($colCanal . $row)->getValue();
            $request->oficina = $sheet->getCell($colOficina . $row)->getValue();
            $request->tecnologia = $sheet->getCell($colTecnologia . $row)->getValue();
            $request->created_at = $sheet->getCell($colFecha . $row)->getValue();
            $request->mk = $sheet->getCell($colMK . $row)->getValue();
            $request->tipo_contrato = $sheet->getCell($colTipoContrato . $row)->getValue();
            $request->profile = $sheet->getCell($colProfile . $row)->getValue();
            $request->local_address_pppoe = $sheet->getCell($colIPLocal . $row)->getValue();
            $request->usuario = $sheet->getCell($colUsuario . $row)->getValue();
            $request->clave = $sheet->getCell($colClave . $row)->getValue();
            $request->linea = $sheet->getCell($colLinea . $row)->getValue();
            $request->estrato = $sheet->getCell($colEstrato . $row)->getValue();
            $request->address_street = $sheet->getCell($colAddressStreet . $row)->getValue();
            $request->precio_personalizado_internet = $sheet->getCell($colPrecioInternet . $row)->getValue();
            $request->precio_personalizado_tv = $sheet->getCell($colPrecioTV . $row)->getValue();

            // Aplicar strtolower a campos tipo texto antes de validar
            if (!empty($request->grupo_corte)) {
                $request->grupo_corte = strtolower(trim((string) $request->grupo_corte));
            }
            if (!empty($request->facturacion)) {
                $request->facturacion = strtolower(trim((string) $request->facturacion));
            }
            if (!empty($request->tecnologia)) {
                $request->tecnologia = strtolower(trim((string) $request->tecnologia));
            }
            if (!empty($request->canal)) {
                $request->canal = strtolower(trim((string) $request->canal));
            }
            if (!empty($request->oficina)) {
                $request->oficina = strtolower(trim((string) $request->oficina));
            }
            if (!empty($request->puerto_caja_nap)) {
                $request->puerto_caja_nap = strtolower(trim((string) $request->puerto_caja_nap));
            }
            if (!empty($request->nodo)) {
                $request->nodo = strtolower(trim((string) $request->nodo));
            }
            if (!empty($request->ap)) {
                $request->ap = strtolower(trim((string) $request->ap));
            }
            if (!empty($request->simple_queue)) {
                $request->simple_queue = strtolower(trim((string) $request->simple_queue));
            }

            // Guardar flag para usar en el segundo bucle
            $request->esNroContrato = $esNroContrato;
            $request->nro_contrato_actualizar = $nro_contrato_actualizar;


            $error = (object) array();

            // Validar identificación y agregar al array de no encontradas si aplica
            if ($nit != "") {
                if (Contacto::where('nit', $nit)->where('status', 1)->count() == 0) {
                    // Agregar a la lista de identificaciones no encontradas en lugar de error inmediato
                    $identificacionesNoEncontradas[] = [
                        'fila' => $row,
                        'identificacion' => $nit
                    ];
                }
            }

            if ($request->mikrotik != "") {
                // Buscar en minúsculas
                $miko = Mikrotik::whereRaw('LOWER(nombre) = ?', [strtolower($request->mikrotik)])->first();
                if (!$miko) {
                    $error->mikrotik = "El mikrotik ingresado no se encuentra en nuestra base de datos";
                    $mikoId = 0;
                } else {
                    $mikoId = $miko->id;
                }
            } else {
                $mikoId = 0;
            }

            if (!$request->servicio) {
                $error->servicio = "El campo Servicio es obligatorio";
            }

            if (!empty($request->ip)) {
                $queryIp = Contrato::where('ip', $request->ip)->where('empresa', Auth::user()->empresa);
                
                // El conteo de IPs repetidas se hace por mikrotik (server_configuration_id)
                if ($mikoId > 0) {
                    $queryIp->where('server_configuration_id', $mikoId);
                }

                if ($esNroContrato && $nro_contrato_actualizar) {
                    $queryIp->where('nro', '!=', $nro_contrato_actualizar);
                }
                
                $isDuplicateInFile = false;
                // Tracking por Mikrotik en el archivo
                if (isset($ipsRegistradasEnArchivo[$mikoId][$request->ip])) {
                    $isDuplicateInFile = true;
                } else {
                    $ipsRegistradasEnArchivo[$mikoId][$request->ip] = true;
                }

                if ($queryIp->count() > 0 || $isDuplicateInFile) {
                    $ipsDuplicadas[] = [
                        'fila' => $row,
                        'ip' => $request->ip
                    ];
                }
            }

            if ($request->plan != "") {
                // Validar que el plan no sea un valor que parezca OLT SN MAC (hexadecimal de 12 caracteres)
                // Los OLT SN MAC suelen ser valores hexadecimales largos
                $planValue = trim((string) $request->plan);
                if (preg_match('/^[0-9a-f]{12,}$/i', $planValue)) {
                    $error->plan = "El valor ingresado en Plan parece ser un OLT SN MAC. Verifique que la columna Plan (F) contenga el nombre del plan, no el OLT SN MAC (E).";
                } else {
                    if(!isset($mikoId)){
                        $mikoId = 0;
                    }

                    // Buscar primero como plan de velocidad
                    $num = PlanesVelocidad::whereRaw('LOWER(name) = ?', [strtolower($planValue)])->where('mikrotik', $mikoId)->count();
                    if ($num == 0) {
                        // Fallback: buscar como item de inventario type TV
                        $inventarioTV = Inventario::whereRaw('LOWER(producto) = ?', [strtolower($planValue)])
                            ->where('empresa', Auth::user()->empresa)
                            ->where('type', 'TV')
                            ->first();
                        if (!$inventarioTV) {
                            $error->plan = "El plan de velocidad o servicio de TV '" . $planValue . "' no se encuentra en nuestra base de datos. Verifique que la columna Plan (F) contenga el nombre correcto del plan o del servicio de TV.";
                        }
                    }
                }
            }
            if (!$request->state) {
                if ($autofillState) {
                    // Auto-relleno activo
                } else {
                    if (!isset($validacionesSolucionables['estados_vacios'])) {
                        $validacionesSolucionables['estados_vacios'] = 0;
                    }
                    $validacionesSolucionables['estados_vacios']++;
                }
            }
            if ($request->conexion != 2 && !$request->ip) {
                if ($autofillIp) {
                    // Auto-relleno activo: no generar error, se asignará IP por defecto en el segundo bucle
                } else {
                    // Registrar como validación solucionable en lugar de error directo
                    if (!isset($validacionesSolucionables['ip_vacias'])) {
                        $validacionesSolucionables['ip_vacias'] = 0;
                    }
                    $validacionesSolucionables['ip_vacias']++;
                }
            }
            if (!$request->conexion) {
                $error->conexion = "El campo conexión es obligatorio";
            }
            if ($request->conexion == 2 && !$request->simple_queue) {
                $error->simple_queue = "El campo Simple Queue es obligatorio para DHCP";
            }

            // Validación condicional para PPPoE: Si simple_queue es "dinamica", ciertos campos no son obligatorios
            $isPPPoE = ($request->conexion == 1);
            $simpleQueueEsDinamica = !empty($request->simple_queue) && strtolower(trim((string) $request->simple_queue)) == 'dinamica';

            // Si es PPPoE y simple_queue NO es "dinamica", entonces estos campos son obligatorios
            if ($isPPPoE && !$simpleQueueEsDinamica) {
                if (empty($request->interfaz)) {
                    $error->interfaz = "El campo Interfaz es obligatorio para PPPoE cuando Simple Queue no es 'dinamica'";
                }
                if (empty($request->local_address_pppoe)) {
                    $error->local_address_pppoe = "El campo IP Local Address es obligatorio para PPPoE cuando Simple Queue no es 'dinamica'";
                }
                if (empty($request->local_address)) {
                    $error->local_address = "El campo Local Address / Segmento es obligatorio para PPPoE cuando Simple Queue no es 'dinamica'";
                }
            }

            if ($request->grupo_corte != "") {
                // Buscar en minúsculas
                if (GrupoCorte::whereRaw('LOWER(nombre) = ?', [strtolower($request->grupo_corte)])->where('status', 1)->count() == 0) {
                    $error->grupo_corte = "El grupo de corte ingresado no se encuentra en nuestra base de datos";
                }
            }
            if (!$request->facturacion) {
                if ($autofillFacturacion) {
                    // Auto-relleno activo
                } else {
                    if (!isset($validacionesSolucionables['facturacion_vacias'])) {
                        $validacionesSolucionables['facturacion_vacias'] = 0;
                    }
                    $validacionesSolucionables['facturacion_vacias']++;
                }
            }

            if (!$request->tecnologia) {
                $error->tecnologia = "El campo tecnologia es obligatorio";
            }
            if (!$request->mk) {
                $error->mk = "Debe indicar Si o No en el campo Cliente en Mikrotik";
            }

            if (count((array) $error) > 0) {
                $erroresRecopilados[] = [
                    'fila' => $row,
                    'errores' => (array) $error
                ];
            }
        }

        // Si hay validaciones solucionables y no se han confirmado las opciones de auto-relleno
        // Mostrar el modal PRIMERO, incluso si también hay errores duros
        if (count($validacionesSolucionables) > 0 && !$autofillIp) {
            $redirect = back()->with('validaciones_importacion', $validacionesSolucionables)
                              ->with('archivo_guardado', $nombre_imagen)
                              ->withInput();
            // Si también hay errores duros, incluirlos para que se muestren junto al modal
            if (count($erroresRecopilados) > 0) {
                $allErrors = [];
                foreach ($erroresRecopilados as $item) {
                    $allErrors[] = 'FILA ' . $item['fila'];
                    foreach ($item['errores'] as $msg) {
                        $allErrors[] = $msg;
                    }
                }
                $redirect = $redirect->withErrors($allErrors);
            }
            return $redirect;
        }

        // Si solo hay errores duros (sin validaciones solucionables), mostrarlos
        if (count($erroresRecopilados) > 0) {
            $allErrors = [];
            foreach ($erroresRecopilados as $item) {
                $allErrors[] = 'FILA ' . $item['fila'];
                foreach ($item['errores'] as $msg) {
                    $allErrors[] = $msg;
                }
            }
            return back()->withErrors($allErrors)->withInput();
        }

        $mensajeErroresAlert = '';
        if (count($identificacionesNoEncontradas) > 0) {
            $mensajeErroresAlert .= "<strong>Las siguientes identificaciones no se encuentran registradas en el sistema:</strong><br><ul style='margin-top: 10px; margin-bottom: 10px;'>";
            foreach ($identificacionesNoEncontradas as $item) {
                $mensajeErroresAlert .= "<li>Fila {$item['fila']}: <strong>{$item['identificacion']}</strong></li>";
            }
            $mensajeErroresAlert .= "</ul>";
        }

        if (count($ipsDuplicadas) > 0) {
            $numDuplicados = count($ipsDuplicadas);
            $mensajeErroresAlert .= "<strong>Las siguientes filas no fueron registradas porque las IPs ya están registradas un total de $numDuplicados:</strong><br><ul style='margin-top: 10px; margin-bottom: 10px;'>";
            foreach ($ipsDuplicadas as $item) {
                $mensajeErroresAlert .= "<li>Fila {$item['fila']}: <strong>{$item['ip']}</strong></li>";
            }
            $mensajeErroresAlert .= "</ul>";
        }

        for ($row = 4; $row <= $highestRow; $row++) {
            $valorColumnaA = $sheet->getCell("A" . $row)->getValue();
            if (empty($valorColumnaA)) {
                break;
            }

            $skipRow = false;
            foreach ($identificacionesNoEncontradas as $item) {
                if ($item['fila'] == $row) {
                    $skipRow = true;
                    break;
                }
            }
            foreach ($ipsDuplicadas as $item) {
                if ($item['fila'] == $row) {
                    $skipRow = true;
                    break;
                }
            }
            
            if ($skipRow) {
                continue;
            }

            // Usar la misma detección del encabezado que en el primer bucle
            // Si el encabezado indica que hay Nro Contrato, todas las filas se tratan como actualización
            if ($tieneNroContratoEnEncabezado) {
                $esNroContrato = true;
                $nro_contrato_actualizar = is_numeric($valorColumnaA) ? $valorColumnaA : null;
                $nit_raw = $sheet->getCell("B" . $row)->getValue(); // Si hay nro contrato, la identificación está en B
            } else {
                // Sin encabezado de Nro Contrato, tratar A como identificación
                $esNroContrato = false;
                $nro_contrato_actualizar = null;
                $nit_raw = $valorColumnaA;
            }

            // Limpiar NIT de formatos Excel (decimales) o guiones de verificación
            $nit_str = trim((string)$nit_raw);
            if (strpos($nit_str, '-') !== false) {
                $nit_parts = explode('-', $nit_str);
                $nit = trim($nit_parts[0]);
            } else {
                $nit = preg_replace('/\.0+$/', '', $nit_str);
            }

            $request                = (object) array();

            // Función helper para calcular columnas con offset
            $getCol = function($base, $offset) {
                $num = ord($base) - ord('A') + 1 + $offset;
                if ($num <= 26) {
                    return chr(ord('A') + $num - 1);
                } else {
                    $first = chr(ord('A') + (($num - 1) / 26) - 1);
                    $second = chr(ord('A') + (($num - 1) % 26));
                    return $first . $second;
                }
            };

            // Ajustar columnas según si hay nro contrato
            $offsetColumna = $esNroContrato ? 1 : 0;

            // Leer campos comunes de la estructura unificada (segundo bucle)
            // IMPORTANTE: Cuando hay Nro Contrato en A, TODAS las columnas se desplazan una posición a la derecha
            // Estructura CON Nro Contrato: A=Nro, B=Identificacion, C=Servicio, D=Serial ONU, E=OLT SN MAC, F=Plan, G=Mikrotik, H=Estado
            // Estructura SIN Nro Contrato: A=Identificacion, B=Servicio, C=Serial ONU, D=OLT SN MAC, E=Plan, F=Mikrotik, G=Estado
            $nit = $this->cleanIdentification($nit_raw);
            if ($esNroContrato) {
                // CON nro contrato: todo desplazado una columna a la derecha
                $request->servicio      = $this->repairEncoding($sheet->getCell("C" . $row)->getValue());  // C = Servicio
                $request->serial_onu    = $sheet->getCell("D" . $row)->getValue();  // D = Serial ONU
                $request->olt_sn_mac    = $sheet->getCell("E" . $row)->getValue();  // E = OLT SN MAC
                $request->plan          = $sheet->getCell("F" . $row)->getValue();  // F = Plan (NO E!)
                $request->mikrotik      = $sheet->getCell("G" . $row)->getValue();  // G = Mikrotik (NO F!)
                $request->state         = $sheet->getCell("H" . $row)->getValue();  // H = Estado
            } else {
                // SIN nro contrato: lectura normal
                $request->servicio      = $this->repairEncoding($sheet->getCell("B" . $row)->getValue());  // B = Servicio
                $request->serial_onu    = $sheet->getCell("C" . $row)->getValue();  // C = Serial ONU
                $request->olt_sn_mac    = $sheet->getCell("D" . $row)->getValue();  // D = OLT SN MAC
                $request->plan          = $sheet->getCell("E" . $row)->getValue();  // E = Plan
                $request->mikrotik      = $sheet->getCell("F" . $row)->getValue();  // F = Mikrotik
                $request->state         = $sheet->getCell("G" . $row)->getValue();  // G = Estado
            }

            // Aplicar strtolower a campos tipo texto
            if (!empty($request->servicio)) {
                $request->servicio = strtolower(trim((string) $request->servicio));
            }
            if (!empty($request->plan)) {
                $request->plan = strtolower(trim((string) $request->plan));
            }
            if (!empty($request->mikrotik)) {
                $request->mikrotik = strtolower(trim((string) $request->mikrotik));
            }
            if (!empty($request->state)) {
                $request->state = strtolower(trim((string) $request->state));
            }

            // Leer conexión - usar las mismas columnas del primer bucle
            $colConexion = $esNroContrato ? 'K' : 'J';
            $conexionCelda = $sheet->getCell($colConexion . $row)->getValue();
            $conexionTexto = strtoupper(trim((string) $conexionCelda));

            if (empty($conexionTexto) || ($conexionTexto != 'PPPOE' && $conexionTexto != 'DHCP' && $conexionTexto != 'IP ESTATICA' && $conexionTexto != 'IP ESTÁTICA' && $conexionTexto != 'VLAN')) {
                $simpleQueueCol = $esNroContrato ? 'N' : 'M';
                $simpleQueueVal = $sheet->getCell($simpleQueueCol . $row)->getValue();
                $simpleQueueTexto = strtoupper(trim((string) $simpleQueueVal));
                if ($simpleQueueTexto == 'DINAMICA' || $simpleQueueTexto == 'DINÁMICA' || $simpleQueueTexto == 'ESTATICA' || $simpleQueueTexto == 'ESTÁTICA') {
                    $conexionTexto = 'DHCP';
                    $conexionCelda = 2;
                }
            }

            if ($conexionTexto == 'PPPOE' || $conexionCelda == 1) {
                $request->conexion = 1;
            } elseif ($conexionTexto == 'DHCP' || $conexionTexto == 'DINAMICA' || $conexionTexto == 'DINÁMICA' || $conexionCelda == 2) {
                $request->conexion = 2;
            } elseif ($conexionTexto == 'IP ESTATICA' || $conexionTexto == 'IP ESTÁTICA' || $conexionCelda == 3) {
                $request->conexion = 3;
            } elseif ($conexionTexto == 'VLAN' || $conexionCelda == 4) {
                $request->conexion = 4;
            } else {
                $request->conexion = $conexionCelda;
            }

            // Leer todos los campos de la estructura unificada (usar las mismas columnas del primer bucle)
            $colIP = $esNroContrato ? 'I' : 'H';
            $colMAC = $esNroContrato ? 'J' : 'I';
            $colInterfaz = $esNroContrato ? 'L' : 'K';
            $colLocalAddr = $esNroContrato ? 'M' : 'L';
            $colSimpleQueue = $esNroContrato ? 'N' : 'M';
            $colTipoTec = $esNroContrato ? 'O' : 'N';
            $colCajaNap = $esNroContrato ? 'P' : 'O';
            $colNodo = $esNroContrato ? 'Q' : 'P';
            $colAP = $esNroContrato ? 'R' : 'Q';
            $colGrupoCorte = $esNroContrato ? 'S' : 'R';
            $colFacturacion = $esNroContrato ? 'T' : 'S';
            $colDescuento = $esNroContrato ? 'U' : 'T';
            $colCanal = $esNroContrato ? 'V' : 'U';
            $colOficina = $esNroContrato ? 'W' : 'V';
            $colTecnologia = $esNroContrato ? 'X' : 'W';
            $colFecha = $esNroContrato ? 'Y' : 'X';
            $colMK = $esNroContrato ? 'Z' : 'Y';
            $colTipoContrato = $esNroContrato ? 'AA' : 'Z';
            $colProfile = $esNroContrato ? 'AB' : 'AA';
            $colIPLocal = $esNroContrato ? 'AC' : 'AB';
            $colUsuario = $esNroContrato ? 'AD' : 'AC';
            $colClave = $esNroContrato ? 'AE' : 'AD';
            $colLinea = $esNroContrato ? 'AF' : 'AE';
            $colEstrato = $esNroContrato ? 'AG' : 'AF';
            $colAddressStreet = $esNroContrato ? 'AH' : 'AG';
            $colPrecioInternet = $esNroContrato ? 'AI' : 'AH';
            $colPrecioTV = $esNroContrato ? 'AJ' : 'AI';

            $request->ip = $sheet->getCell($colIP . $row)->getValue();
            $request->mac = $sheet->getCell($colMAC . $row)->getValue();
            $request->interfaz = $sheet->getCell($colInterfaz . $row)->getValue();
            $request->local_address = $sheet->getCell($colLocalAddr . $row)->getValue();
            $request->simple_queue = $sheet->getCell($colSimpleQueue . $row)->getValue();
            $request->tipo_tecnologia = $sheet->getCell($colTipoTec . $row)->getValue();
            $request->puerto_caja_nap = $sheet->getCell($colCajaNap . $row)->getValue();
            $request->nodo = $sheet->getCell($colNodo . $row)->getValue();
            $request->ap = $sheet->getCell($colAP . $row)->getValue();
            $request->grupo_corte = $sheet->getCell($colGrupoCorte . $row)->getValue();
            $request->facturacion = $sheet->getCell($colFacturacion . $row)->getValue();
            $request->descuento = $sheet->getCell($colDescuento . $row)->getValue();
            $request->canal = $sheet->getCell($colCanal . $row)->getValue();
            $request->oficina = $sheet->getCell($colOficina . $row)->getValue();
            $request->tecnologia = $sheet->getCell($colTecnologia . $row)->getValue();
            $request->created_at = $sheet->getCell($colFecha . $row)->getValue();
            $request->mk = $sheet->getCell($colMK . $row)->getValue();
            $request->tipo_contrato = $sheet->getCell($colTipoContrato . $row)->getValue();
            $request->profile = $sheet->getCell($colProfile . $row)->getValue();
            $request->local_address_pppoe = $sheet->getCell($colIPLocal . $row)->getValue();
            $request->usuario = $sheet->getCell($colUsuario . $row)->getValue();
            $request->clave = $sheet->getCell($colClave . $row)->getValue();
            $request->linea = $sheet->getCell($colLinea . $row)->getValue();
            $request->estrato = $sheet->getCell($colEstrato . $row)->getValue();
            $request->address_street = $sheet->getCell($colAddressStreet . $row)->getValue();
            $request->precio_personalizado_internet = $sheet->getCell($colPrecioInternet . $row)->getValue();
            $request->precio_personalizado_tv = $sheet->getCell($colPrecioTV . $row)->getValue();

            // Aplicar strtolower a campos tipo texto
            if (!empty($request->grupo_corte)) {
                $request->grupo_corte = strtolower(trim((string) $request->grupo_corte));
            }
            if (!empty($request->facturacion)) {
                $request->facturacion = strtolower(trim((string) $request->facturacion));
            }
            if (!empty($request->tecnologia)) {
                $request->tecnologia = strtolower(trim((string) $request->tecnologia));
            }
            if (!empty($request->canal)) {
                $request->canal = strtolower(trim((string) $request->canal));
            }
            if (!empty($request->oficina)) {
                $request->oficina = strtolower(trim((string) $request->oficina));
            }
            if (!empty($request->puerto_caja_nap)) {
                $request->puerto_caja_nap = strtolower(trim((string) $request->puerto_caja_nap));
            }
            if (!empty($request->nodo)) {
                $request->nodo = strtolower(trim((string) $request->nodo));
            }
            if (!empty($request->ap)) {
                $request->ap = strtolower(trim((string) $request->ap));
            }
            if (!empty($request->simple_queue)) {
                $request->simple_queue = strtolower(trim((string) $request->simple_queue));
            }

            if ($request->mikrotik != "") {
                // Buscar en minúsculas
                $mikro = Mikrotik::whereRaw('LOWER(nombre) = ?', [strtolower($request->mikrotik)])->first();
                if ($mikro) {
                    $request->mikrotik = $mikro->id;
                } else {
                    return back()->withErrors(['mikrotik' => 'El mikrotik ingresado no se encuentra en nuestra base de datos'])->withInput();
                }
            }
            $request->es_tv = false;
            if ($request->plan != "") {
                // Buscar primero como plan de velocidad
                $planesVelocidad = PlanesVelocidad::whereRaw('LOWER(name) = ?', [strtolower($request->plan)])->first();
                if ($planesVelocidad) {
                    $request->plan = $planesVelocidad->id;
                } else {
                    // Fallback: buscar como item de inventario type TV
                    $inventarioTV = Inventario::whereRaw('LOWER(producto) = ?', [strtolower($request->plan)])
                        ->where('empresa', Auth::user()->empresa)
                        ->where('type', 'TV')
                        ->first();
                    if ($inventarioTV) {
                        $request->plan = $inventarioTV->id;
                        $request->es_tv = true;
                    } else {
                        $error->plan = "El plan de velocidad o servicio de TV " . $request->plan . " ingresado no se encuentra en nuestra base de datos";
                    }
                }
            }
            if ($request->grupo_corte != "") {
                // Buscar en minúsculas
                $grupo = GrupoCorte::whereRaw('LOWER(nombre) = ?', [strtolower($request->grupo_corte)])->first();
                if ($grupo) {
                    $request->grupo_corte = $grupo->id;
                } else {
                    return back()->withErrors(['grupo_corte' => 'El grupo de corte ingresado no se encuentra en nuestra base de datos'])->withInput();
                }
            }

            if (strtolower($request->facturacion) == 'estandar') {
                $request->facturacion = 1;
            } elseif (strtolower($request->facturacion) == 'electronica') {
                $request->facturacion = 3;
            }

            if (strtolower($request->tecnologia) == 'fibra') {
                $request->tecnologia = 1;
            } elseif (strtolower($request->tecnologia) == 'inalambrica') {
                $request->tecnologia = 2;
            } elseif (strtolower($request->tecnologia) == 'cableado utp') {
                $request->tecnologia = 3;
            }

            if (strtolower($request->state) == 'habilitado') {
                $request->state = 'enabled';
            } elseif (strtolower($request->state) == 'deshabilitado') {
                $request->state = 'disabled';
            }

            $cajaNap = null;
            if ($request->puerto_caja_nap != "") {
                // Buscar en minúsculas
                $cajaNap = CajaNap::whereRaw('LOWER(nombre) = ?', [strtolower($request->puerto_caja_nap)])->first();
                if ($cajaNap) {
                    $request->puerto_caja_nap = $cajaNap->id;
                } else {
                    return back()->withErrors(['puerto_caja_nap' => 'La caja NAP ingresada no se encuentra en nuestra base de datos'])->withInput();
                }
            }

            // Procesar nodo y ap si están presentes
            if (!empty($request->nodo)) {
                $nodo_obj = Nodo::whereRaw('LOWER(nombre) = ?', [strtolower($request->nodo)])->first();
                if ($nodo_obj) {
                    $request->nodo = $nodo_obj->id;
                } else {
                    return back()->withErrors(['nodo' => 'El nodo ingresado no se encuentra en nuestra base de datos'])->withInput();
                }
            }

            if (!empty($request->ap)) {
                $ap_obj = AP::whereRaw('LOWER(nombre) = ?', [strtolower($request->ap)])->first();
                if ($ap_obj) {
                    $request->ap = $ap_obj->id;
                } else {
                    return back()->withErrors(['ap' => 'El access point ingresado no se encuentra en nuestra base de datos'])->withInput();
                }
            }

            $request->mk = (strtoupper($request->mk) == 'NO') ? 0 : 1;

            // Si hay número de contrato, buscar y actualizar contrato existente
            if ($esNroContrato && $nro_contrato_actualizar) {
                $contrato = Contrato::where('nro', $nro_contrato_actualizar)
                    ->where('empresa', Auth::user()->empresa)
                    ->first();

                if ($contrato) {
                    // Actualizar contrato existente
                    $modf = $modf + 1;
                    // Actualizar client_id si se especifica nueva identificación
                    if (!empty($nit)) {
                        $cliente = Contacto::where('nit', $nit)->where('status', 1)->first();
                        if ($cliente) {
                            $contrato->client_id = $cliente->id;
                        }
                    }
                } else {
                    // Si no existe, crear nuevo contrato con ese número
                    $contrato = new Contrato;
                    $contrato->empresa   = Auth::user()->empresa;
                    $contrato->nro       = $nro_contrato_actualizar;
                    if (!empty($nit)) {
                        $cliente = Contacto::where('nit', $nit)->where('status', 1)->first();
                        if ($cliente) {
                            $contrato->client_id = $cliente->id;
                        }
                    }
                    $create = $create + 1;
                }
            } else {
                // Crear un nuevo contrato (comportamiento original)
                $nro = Numeracion::where('empresa', 1)->first();
                $nro_contrato = $nro->contrato;

                while (true) {
                    $numero = Contrato::where('nro', $nro_contrato)->count();
                    if ($numero == 0) {
                        break;
                    }
                    $nro_contrato++;
                }

                $contrato = new Contrato;
                $contrato->empresa   = Auth::user()->empresa;
                $contrato->nro       = $nro_contrato;
                $contrato->client_id = Contacto::where('nit', $nit)->where('status', 1)->first()->id;
                $create = $create + 1;

                $nro->contrato = $nro_contrato + 1;
                $nro->save();
            }

            // Actualizar o establecer servicio
            if ($esNroContrato && $nro_contrato_actualizar) {
                // Si hay servicio nuevo, actualizarlo; si no, mantener el actual
                if (!empty($request->servicio)) {
                    $contrato->servicio = $this->normaliza($request->servicio) . '-' . $contrato->nro;
                }
            } else {
                $contrato->servicio = $this->normaliza($request->servicio) . '-' . $contrato->nro;
            }

            // Si es un servicio de TV, guardar en servicio_tv; si es plan de velocidad, guardar en plan_id
            if (isset($request->es_tv) && $request->es_tv) {
                $contrato->servicio_tv             = $request->plan;
                // No sobreescribir plan_id si ya tiene uno y estamos actualizando
                if (!$esNroContrato || !$nro_contrato_actualizar) {
                    $contrato->plan_id             = null;
                }
            } else {
                $contrato->plan_id                 = $request->plan;
            }
            $contrato->server_configuration_id = $request->mikrotik;
            
            // Auto-rellenar Estado si está vacío
            if (empty($request->state) && $autofillState) {
                $contrato->state = $autofillStateValue;
                $autofillStateCount++;
            } else {
                $contrato->state = $request->state;
            }
            // Auto-rellenar IP si está vacía y autofill está activo
            if (empty($request->ip) && $request->conexion != 2 && $autofillIp) {
                $contrato->ip = '000.000.0.00';
                $autofillIpCount++;
            } else {
                $contrato->ip = $request->ip;
            }
            $contrato->conexion                = $request->conexion;
            $contrato->simple_queue            = $request->simple_queue ?? null;
            $contrato->interfaz                = $request->interfaz ?? null;
            $contrato->local_address           = $request->local_address ?? null;
            $contrato->grupo_corte             = $request->grupo_corte;
            
            // Auto-rellenar Facturación si está vacía
            if (empty($request->facturacion) && $autofillFacturacion) {
                $contrato->facturacion = $autofillFacturacionValue;
                $autofillFacturacionCount++;
            } else {
                $contrato->facturacion = $request->facturacion;
            }
            $contrato->tecnologia              = $request->tecnologia;
            $contrato->tipo_contrato           = $request->tipo_contrato;
            $contrato->profile                 = $request->profile ?? null;

            $contrato->descuento               = $request->descuento;
            $contrato->canal                   = $request->canal;
            $contrato->oficina                 = $request->oficina;
            $contrato->nodo                    = $request->nodo ?? null;
            $contrato->ap                      = $request->ap ?? null;
            $contrato->mac_address             = $request->mac ?? null;
            $contrato->serial_onu              = $request->serial_onu;
            $contrato->olt_sn_mac              = $request->olt_sn_mac ?? null;
            $contrato->created_at              = $this->validateDateOrNow($request->created_at);
            $contrato->mk                      = $request->mk;
            $contrato->usuario                 = $request->usuario ?? null;
            $contrato->password                = $request->clave ?? null;
            $contrato->local_adress_pppoe      = $request->local_address_pppoe ?? null;
            $contrato->linea                   = $request->linea ?? null;
            $contrato->estrato                 = $request->estrato ?? null;
            $contrato->address_street          = $request->address_street ?? null;
            $contrato->precio_personalizado_internet = $request->precio_personalizado_internet ?? null;
            $contrato->precio_personalizado_tv       = $request->precio_personalizado_tv ?? null;

            // Manejar caja NAP y puerto
            if ($cajaNap != null) {
                // Si es una actualización de contrato existente
                if ($esNroContrato && $nro_contrato_actualizar && isset($contrato->id)) {
                    // Verificar si la caja NAP cambió
                    $cajaNapCambio = ($contrato->cajanap_id != $cajaNap->id);

                    if ($cajaNapCambio) {
                        // Si cambió la caja NAP, asignar un nuevo puerto disponible
                        $puertoDisponible = $cajaNap->obtenerPuertoDisponible();
                        if ($puertoDisponible === null) {
                            return back()->withErrors(['puerto_caja_nap' => 'La caja NAP ' . $cajaNap->nombre . ' no tiene puertos disponibles'])->withInput();
                        }
                        $contrato->cajanap_puerto = $puertoDisponible;
                        $contrato->cajanap_id = $cajaNap->id;
                    } else {
                        // Si la caja NAP no cambió, mantener el puerto existente
                        // No modificar cajanap_puerto ni cajanap_id
                        // Solo actualizar si se especifica explícitamente en el Excel (por ahora mantenemos el existente)
                        // Si en el futuro se quiere permitir cambiar solo el puerto, se podría agregar una columna específica
                    }
                } else {
                    // Si es un nuevo contrato, asignar un nuevo puerto disponible
                    $puertoDisponible = $cajaNap->obtenerPuertoDisponible();
                    if ($puertoDisponible === null) {
                        return back()->withErrors(['puerto_caja_nap' => 'La caja NAP ' . $cajaNap->nombre . ' no tiene puertos disponibles'])->withInput();
                    }
                    $contrato->cajanap_puerto = $puertoDisponible;
                    $contrato->cajanap_id = $cajaNap->id;
                }
            } else {
                // Si no se especifica caja NAP en el Excel y es una actualización, mantener los valores actuales
                // Si es un nuevo contrato, dejar null
                if (!($esNroContrato && $nro_contrato_actualizar && isset($contrato->id))) {
                    $contrato->cajanap_puerto = null;
                    $contrato->cajanap_id = null;
                }
                // Si es actualización y no se especifica caja NAP, no modificamos esos campos
            }

            // Solo actualizar created_at si es un nuevo contrato
            if (!($esNroContrato && $nro_contrato_actualizar && isset($contrato->id))) {
                // Ya se asignó en la línea 5944 usando validateDateOrNow
            } else {
                // Para actualizaciones, solo actualizar si viene fecha en el Excel
                if (!empty($request->created_at)) {
                    $contrato->created_at = $this->validateDateOrNow($request->created_at);
                }
                // Si no viene fecha, se mantiene la fecha original del contrato (laravel no la sobreescribe si no se asigna)
            }

            $contrato->save();
        }

        $mensaje = 'SE HA COMPLETADO EXITOSAMENTE LA CARGA DE DATOS DEL SISTEMA';

        if ($create > 0) {
            $mensaje .= ' CREADOS: ' . $create;
        }
        if ($modf > 0) {
            $mensaje .= ' MODIFICADOS: ' . $modf;
        }
        if ($autofillIpCount > 0) {
            $mensaje .= '. ' . $autofillIpCount . ' registro(s) fueron agregados con la IP 000.000.0.00 porque no fue ingresada.';
        }
        if ($autofillStateCount > 0) {
            $mensaje .= '. ' . $autofillStateCount . ' registro(s) fueron agregados con el Estado ' . ucfirst($autofillStateValue) . ' porque no fue ingresado.';
        }
        if ($autofillFacturacionCount > 0) {
            $mensaje .= '. ' . $autofillFacturacionCount . ' registro(s) fueron agregados con la Facturación ' . ucfirst($autofillFacturacionValue) . ' porque no fue ingresada.';
        }

        if ($mensajeErroresAlert != '') {
            return redirect('empresa/contratos')->with('success', $mensaje)->with('danger', $mensajeErroresAlert);
        }

        return redirect('empresa/contratos')->with('success', $mensaje);
    }

    public function cargandoTv(Request $request)
    {
        $request->validate([
            'archivo' => 'required|mimes:xlsx',
        ], [
            'archivo.mimes' => 'El archivo debe ser de extensión xlsx'
        ]);

        $create = 0;
        $modf = 0;
        $imagen = $request->file('archivo');
        $nombre_imagen = 'archivo.' . $imagen->getClientOriginalExtension();
        $path = public_path() . '/images/Empresas/Empresa' . Auth::user()->empresa;
        $imagen->move($path, $nombre_imagen);
        Ini_set('max_execution_time', 500);
        $fileWithPath = $path . "/" . $nombre_imagen;
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

        // Array para recopilar todas las identificaciones no encontradas
        $identificacionesNoEncontradas = [];

        for ($row = 4; $row <= $highestRow; $row++) {
            $request = (object) array();
            //obtengo el A4 desde donde empieza la data
            $nit_raw = $sheet->getCell("A" . $row)->getValue();
            if (empty($nit_raw)) {
                break;
            }

            // Limpiar NIT de formatos Excel (decimales) o guiones de verificación
            $nit_str = trim((string)$nit_raw);
            if (strpos($nit_str, '-') !== false) {
                $nit_parts = explode('-', $nit_str);
                $nit = trim($nit_parts[0]);
            } else {
                $nit = preg_replace('/\.0+$/', '', $nit_str);
            }

            $request->servicio      = $sheet->getCell("B" . $row)->getValue();
            $request->serial_onu    = $sheet->getCell("C" . $row)->getValue();
            $request->plan          = $sheet->getCell("D" . $row)->getValue();
            $request->mikrotik      = $sheet->getCell("E" . $row)->getValue();
            $request->state         = $sheet->getCell("F" . $row)->getValue();
            $request->ip            = $sheet->getCell("G" . $row)->getValue();
            $request->mac           = $sheet->getCell("H" . $row)->getValue();
            $request->conexion      = $sheet->getCell("I" . $row)->getValue();
            $request->interfaz      = $sheet->getCell("J" . $row)->getValue();
            $request->local_address = $sheet->getCell("K" . $row)->getValue();
            $request->nodo          = $sheet->getCell("L" . $row)->getValue();
            $request->ap            = $sheet->getCell("M" . $row)->getValue();
            $request->grupo_corte   = $sheet->getCell("N" . $row)->getValue();
            $request->facturacion   = $sheet->getCell("O" . $row)->getValue();
            $request->descuento     = $sheet->getCell("P" . $row)->getValue();
            $request->canal         = $sheet->getCell("Q" . $row)->getValue();
            $request->oficina       = $sheet->getCell("R" . $row)->getValue();
            $request->tecnologia    = $sheet->getCell("S" . $row)->getValue();
            $request->created_at    = $sheet->getCell("T" . $row)->getValue();
            $request->mk            = $sheet->getCell("U" . $row)->getValue();
            $request->profle        = $sheet->getCell("W" . $row)->getValue();
            $request->local_address_pppoe = $sheet->getCell("X" . $row)->getValue();
            $request->usuario       = $sheet->getCell("Y" . $row)->getValue();
            $request->clave         = $sheet->getCell("Z" . $row)->getValue();


            $error = (object) array();

            if ($nit != "") {
                if (Contacto::where('nit', $nit)->where('status', 1)->count() == 0) {
                    $error->nit = "La identificación indicada no se encuentra registrada para ningún cliente en el sistema";
                }
            }
            if (!$request->servicio) {
                $error->servicio = "El campo Servicio es obligatorio";
            }

            if ($request->plan != "") {
                if (Inventario::where('empresa', Auth::user()->empresa)->where('type', 'TV')->where('status', 1)->where("producto", $request->plan)->count() == 0) {
                    $error->plan = "La identificación indicada no se encuentra registrada para ningún cliente en el sistema";
                }
            }
            if (!$request->state) {
                $error->state = "El campo estado es obligatorio";
            }

            if (count((array) $error) > 0) {
                $fila["error"] = 'FILA ' . $row;
                $error = (array) $error;
                var_dump($error);
                var_dump($fila);

                array_unshift($error, $fila);
                $result = (object) $error;
                return back()->withErrors($result)->withInput();
            }
        }

        for ($row = 4; $row <= $highestRow; $row++) {
            $nit_raw = $sheet->getCell("A" . $row)->getValue();
            if (empty($nit_raw)) {
                break;
            }

            // Limpiar NIT de formatos Excel (decimales) o guiones de verificación
            $nit_str = trim((string)$nit_raw);
            if (strpos($nit_str, '-') !== false) {
                $nit_parts = explode('-', $nit_str);
                $nit = trim($nit_parts[0]);
            } else {
                $nit = preg_replace('/\.0+$/', '', $nit_str);
            }
            $request                = (object) array();
            $request->servicio      = $sheet->getCell("B" . $row)->getValue();
            $request->serial_onu    = $sheet->getCell("C" . $row)->getValue();
            $request->plan          = $sheet->getCell("D" . $row)->getValue();
            $request->mikrotik      = $sheet->getCell("E" . $row)->getValue();
            $request->state         = $sheet->getCell("F" . $row)->getValue();
            $request->ip            = $sheet->getCell("G" . $row)->getValue();
            $request->mac           = $sheet->getCell("H" . $row)->getValue();
            $request->conexion      = $sheet->getCell("I" . $row)->getValue();
            $request->interfaz      = $sheet->getCell("J" . $row)->getValue();
            $request->local_address = $sheet->getCell("K" . $row)->getValue();
            $request->nodo          = $sheet->getCell("L" . $row)->getValue();
            $request->ap            = $sheet->getCell("M" . $row)->getValue();
            $request->grupo_corte   = $sheet->getCell("N" . $row)->getValue();
            $request->facturacion   = $sheet->getCell("O" . $row)->getValue();
            $request->descuento     = $sheet->getCell("P" . $row)->getValue();
            $request->canal         = $sheet->getCell("Q" . $row)->getValue();
            $request->oficina       = $sheet->getCell("R" . $row)->getValue();
            $request->tecnologia    = $sheet->getCell("S" . $row)->getValue();
            $request->created_at    = $sheet->getCell("T" . $row)->getValue();
            $request->mk            = $sheet->getCell("U" . $row)->getValue();
            $request->profile        = $sheet->getCell("W" . $row)->getValue();
            $request->local_address_pppoe = $sheet->getCell("X" . $row)->getValue();
            $request->usuario       = $sheet->getCell("Y" . $row)->getValue();
            $request->clave         = $sheet->getCell("Z" . $row)->getValue();

            if ($request->plan != "") {
                $plan = Inventario::where('empresa', Auth::user()->empresa)->where('type', 'TV')->where('status', 1)->where("producto", $request->plan)->first();
                if ($plan) {
                    $request->plan = $plan->id;
                } else {
                    // Manejar el caso en el que no se encuentra el plan de velocidad
                    $error->plan = "El plan de TV " . $request->plan . " ingresado no se encuentra en nuestra base de datos";
                }
            }

            if ($request->state == 'Habilitado') {
                $request->state = 'enabled';
            } elseif ($request->state == 'Deshabilitado') {
                $request->state = 'disabled';
            }

            if ($request->tecnologia == 'Fibra') {
                $request->tecnologia = 1;
            } elseif ($request->tecnologia == 'Inalambrica') {
                $request->tecnologia = 2;
            } elseif ($request->tecnologia == 'Cableado UTP') {
                $request->tecnologia = 3;
            }

            $request->mk = (strtoupper($request->mk) == 'NO') ? 0 : 1;

            $contrato = Contrato::join('contactos as c', 'c.id', '=', 'contracts.client_id')->select('contracts.*', 'c.id as client_id')->where('c.nit', $nit)->where('contracts.empresa', Auth::user()->empresa)->where('contracts.status', 1)->where('c.status', 1)->first();

            if (!$contrato) {
                $nro = Numeracion::where('empresa', 1)->first();
                $nro_contrato = $nro->contrato;

                while (true) {
                    $numero = Contrato::where('nro', $nro_contrato)->count();
                    if ($numero == 0) {
                        break;
                    }
                    $nro_contrato++;
                }

                $contrato = new Contrato;
                $contrato->empresa   = Auth::user()->empresa;
                $contrato->servicio  = $this->normaliza($request->servicio) . '-' . $nro_contrato;
                $contrato->nro       = $nro_contrato;
                $contrato->client_id = Contacto::where('nit', $nit)->where('status', 1)->first()->id;
                $create = $create + 1;

                $nro->contrato = $nro_contrato + 1;
                $nro->save();
            } else {
                $modf = $modf + 1;
                $contrato->servicio  = $this->normaliza($request->servicio) . '-' . $contrato->nro;
            }

            $contrato->servicio_tv             = $request->plan;
            $contrato->state                   = $request->state;
            $contrato->serial_onu              = $request->serial_onu;
            $contrato->created_at              = $this->validateDateOrNow($request->created_at);
            $contrato->tecnologia              = $request->tecnologia;

            $contrato->save();
        }

        $mensaje = 'SE HA COMPLETADO EXITOSAMENTE LA CARGA DE DATOS DEL SISTEMA';

        if ($create > 0) {
            $mensaje .= ' CREADOS: ' . $create;
        }
        if ($modf > 0) {
            $mensaje .= ' MODIFICADOS: ' . $modf;
        }
        return redirect('empresa/contratos')->with('success', $mensaje);
    }

    public function importarMK()
    {
        $contratos = Contrato::join('planes_velocidad as p', 'p.id', '=', 'contracts.plan_id')->join('mikrotik as m', 'm.id', '=', 'contracts.server_configuration_id')->select('contracts.*', 'p.prioridad', 'p.burst_limit_subida', 'p.burst_limit_bajada', 'p.burst_threshold_subida', 'p.burst_threshold_bajada', 'p.burst_time_subida', 'p.burst_time_bajada', 'p.limit_at_subida', 'p.limit_at_bajada', 'p.upload', 'p.download', 'p.dhcp_server', 'm.amarre_mac')->where('contracts.status', 1)->where('contracts.mk', 0)->get();

        $filePath = "NetworkSoft" . date('dmY') . ".rsc";
        $file = fopen($filePath, "w");
        foreach ($contratos as $contrato) {
            $priority        = $contrato->prioridad;
            $burst_limit     = (strlen($contrato->burst_limit_subida) > 1) ? $contrato->burst_limit_subida . '/' . $contrato->burst_limit_bajada : '';
            $burst_threshold = (strlen($contrato->burst_threshold_subida) > 1) ? $contrato->burst_threshold_subida . '/' . $contrato->burst_threshold_bajada : '';
            $burst_time      = ($contrato->burst_time_subida) ? $contrato->burst_time_subida . '/' . $contrato->burst_time_bajada : '';
            $limit_at        = (strlen($contrato->limit_at_subida) > 1) ? $contrato->limit_at_subida . '/' . $contrato->limit_at_bajada : '';
            $max_limit       = $contrato->upload . '/' . $contrato->download;

            fputs($file, '/queue/simple/add name="' . $contrato->servicio . '" target=' . $contrato->ip . ' max-limit=' . $contrato->upload . '/' . $contrato->download);

            if (strlen($burst_limit) > 3) {
                fputs($file, ' burst-limit=' . $burst_limit);
            }
            if (strlen($burst_threshold) > 3) {
                fputs($file, ' burst-threshold=' . $burst_threshold);
            }
            if (strlen($burst_time) > 3) {
                fputs($file, ' burst-time=' . $burst_time);
            }
            if (strlen($priority) > 0) {
                fputs($file, ' priority=' . $priority);
            }
            if (strlen($limit_at) > 0) {
                fputs($file, ' limit-at=' . $limit_at);
            }

            fputs($file, PHP_EOL);
        }
        fclose($file);

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . basename($filePath));
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;

        foreach ($contratos as $contrato) {
            $API = new RouterosAPI();
            /*PPPOE*/
            if ($contrato->conexion == 1) {
                $API->comm(
                    "/ppp/secret/add",
                    array(
                        "name"           => $contrato->usuario,
                        "password"       => $contrato->password,
                        "profile"        => 'default',
                        "local-address"  => $contrato->ip,
                        "remote-address" => $contrato->ip,
                        "service"        => 'pppoe',
                        "comment"        => $contrato->servicio
                    )
                );
            }
            /*DHCP*/
            if ($contrato->conexion == 2) {
                if (isset($contrato->dhcp_server)) {
                    if ($contrato->simple_queue == 'dinamica') {
                        $API->comm("/ip/dhcp-server/set\n=name=" . $contrato->dhcp_server . "\n=address-pool=static-only\n=parent-queue=" . $contrato->parenta);
                        $API->comm(
                            "/ip/dhcp-server/lease/add",
                            array(
                                "comment"     => $contrato->servicio,
                                "address"     => $contrato->ip,
                                "server"      => $contrato->dhcp_server,
                                "mac-address" => $contrato->mac_address,
                                "rate-limit"  => $rate_limit
                            )
                        );
                    } elseif ($contrato->simple_queue == 'estatica') {
                        $API->comm(
                            "/ip/dhcp-server/lease/add",
                            array(
                                "comment"     => $contrato->servicio,
                                "address"     => $contrato->ip,
                                "server"      => $contrato->dhcp_server,
                                "mac-address" => $contrato->mac_address
                            )
                        );
                    }
                }
            }
            /*IP ESTÁTICA*/
            if ($contrato->conexion == 3) {
                if ($contrato->amarre_mac == 1) {
                    $API->comm(
                        "/ip/arp/add",
                        array(
                            "comment"     => $contrato->servicio,
                            "address"     => $contrato->ip,
                            "interface"   => $contrato->interfaz,
                            "mac-address" => $contrato->mac_address
                        )
                    );
                }
            }
            #QUEUE SIMPLE

            #AGREGAMOS A IP_AUTORIZADAS#
            $API->comm(
                "/ip/firewall/address-list/add",
                array(
                    "address" => $contrato->ip,
                    "list" => 'ips_autorizadas'
                )
            );
        }
    }

    public function planes_lote($contratos, $server_configuration_id, $plan_id)
    {
        $this->getAllPermissions(Auth::user()->id);

        $succ = 0;
        $fail = 0;

        $contratos = explode(",", $contratos);

        for ($i = 0; $i < count($contratos); $i++) {
            $descripcion = '';
            $contrato = Contrato::find($contratos[$i]);
            $plan     = PlanesVelocidad::find($plan_id);
            $mikrotik = Mikrotik::find($server_configuration_id);

            $API = new RouterosAPI();
            $API->port = $mikrotik->puerto_api;

            if ($contrato) {
                if ($API->connect($mikrotik->ip, $mikrotik->usuario, $mikrotik->clave)) {
                    #ELMINAMOS DEL QUEUE#
                    $queue = $API->comm(
                        "/queue/simple/getall",
                        array(
                            "?target" => $contrato->ip . '/32'
                        )
                    );

                    if ($queue) {
                        $API->comm(
                            "/queue/simple/remove",
                            array(
                                ".id" => $queue[0][".id"],
                            )
                        );
                    }
                    #ELMINAMOS DEL QUEUE#

                    $rate_limit      = '';
                    $priority        = $plan->prioridad;
                    $burst_limit     = (strlen($plan->burst_limit_subida) > 1) ? $plan->burst_limit_subida . '/' . $plan->burst_limit_bajada : '';
                    $burst_threshold = (strlen($plan->burst_threshold_subida) > 1) ? $plan->burst_threshold_subida . '/' . $plan->burst_threshold_bajada : '';
                    $burst_time      = ($plan->burst_time_subida) ? $plan->burst_time_subida . '/' . $plan->burst_time_bajada : '';
                    $limit_at        = (strlen($plan->limit_at_subida) > 1) ? $plan->limit_at_subida . '/' . $plan->limit_at_bajada : '';
                    $max_limit       = $plan->upload . '/' . $plan->download;

                    if ($max_limit) {
                        $rate_limit .= $max_limit;
                    }
                    if (strlen($burst_limit) > 3) {
                        $rate_limit .= ' ' . $burst_limit;
                    }
                    if (strlen($burst_threshold) > 3) {
                        $rate_limit .= ' ' . $burst_threshold;
                    }
                    if (strlen($burst_time) > 3) {
                        $rate_limit .= ' ' . $burst_time;
                    }
                    if ($priority) {
                        $rate_limit .= ' ' . $priority;
                    }
                    if ($limit_at) {
                        $rate_limit .= ' ' . $limit_at;
                    }

                    $API->comm(
                        "/queue/simple/add",
                        array(
                            "name"            => $contrato->servicio,
                            "target"          => $contrato->ip,
                            "max-limit"       => $plan->upload . '/' . $plan->download,
                            "burst-limit"     => $burst_limit,
                            "burst-threshold" => $burst_threshold,
                            "burst-time"      => $burst_time,
                            "priority"        => $priority,
                            "limit-at"        => $limit_at
                        )
                    );

                    $plan_old = ($contrato->plan_id) ? PlanesVelocidad::find($contrato->plan_id)->name : 'Ninguno';

                    $descripcion .= ($contrato->plan_id == $plan_id) ? '' : '<i class="fas fa-check text-success"></i> <b>Cambio Plan</b> de ' . $plan_old . ' a ' . $plan->name . '<br>';
                    $contrato->plan_id = $plan_id;
                    $contrato->save();

                    /*REGISTRO DEL LOG*/
                    if (!is_null($descripcion)) {
                        $movimiento = new MovimientoLOG;
                        $movimiento->contrato    = $contrato->id;
                        $movimiento->modulo      = 5;
                        $movimiento->descripcion = $descripcion;
                        $movimiento->created_by  = Auth::user()->id;
                        $movimiento->empresa     = Auth::user()->empresa;
                        $movimiento->save();
                    }
                    $succ++;
                } else {
                    $fail++;
                }
                $API->disconnect();
            }
        }

        return response()->json([
            'success'   => true,
            'fallidos'  => $fail,
            'correctos' => $succ,
            'plan'      => $plan->name
        ]);
    }

    function opcion_dian(Request $request)
    {
        $contrato = Contrato::find($request->contratoId);
        $contrato->opciones_dian = $request->opcionDian == 1 ? 0 : 1;
        $contrato->save();

        return true;
    }


    function morosos()
    {

        $allMorosos = [];
        $mikrotiks = Mikrotik::all();
        // dd($mikrotiks);
        foreach ($mikrotiks as $mikrotik) {
            $API = new RouterosAPI();
            $API->port = $mikrotik->puerto_api;

            //$API->debug = true;

            if ($API->connect($mikrotik->ip, $mikrotik->usuario, $mikrotik->clave)) {
                $API->write('ip/firewall/address get [find list=morosos]', true);
                $ARRAYS = $API->read();

                $allMorosos[] = $ARRAYS;
            }
        }
        return $allMorosos;
    }



    public function forzarCrm($idContrato)
    {

        // $contrato = Contrato::find($idContrato);
        $contrato = Contrato::where('client_id', $idContrato)->first();
        //crm registro
        $crm = new CRM();
        $crm->cliente = $contrato->cliente()->id;
        $crm->servidor = isset($contrato->server_configuration_id) ? $contrato->server_configuration_id : '';
        $crm->grupo_corte = isset($contrato->grupo_corte) ? $contrato->grupo_corte : '';
        $crm->estado = 0;
        if ($lastFact = $contrato->lastFactura()) {
            $crm->factura = $lastFact->id;
        }
        $crm->save();


        return back()->with('success', 'Se genero un registro CRM en la cartera');
    }

    //Metodo para obtener los contratos del cliente.
    public function json($cliente)
    {

        try {
            $contratos = Contrato::where('client_id', $cliente)
                // ->where('state','enabled')
                ->get();

            // $hoy = Carbon::now()->toDateString();
            // $hoy = "2024-01-23";
            // if(DB::table('facturas_contratos')->whereDate('created_at',$hoy)->where('contrato_nro',1932)->first()){
            //     return "se creo uno hoy";
            // }
            // else return "no se creo uno hoy";

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => "Contratos obtenidos correctamente.",
                'data' => $contratos
            ]);
        } catch (\Throwable $th) {
            $errorData = json_decode($th->getMessage(), true);
            return response()->json(['code' => 422, 'message' => $errorData]);
        }
    }

    public function rowItem(Request $request)
    {
        try {
            if (isset($request->cliente_id)) {
                
                $items = [];
                if (!empty($request->contrato_id)) {
                    if (is_array($request->contrato_id)) {
                        $contratos = Contrato::whereIn('id', $request->contrato_id)->get();
                    } else {
                        $contrato = Contrato::find($request->contrato_id);
                        if ($contrato && $contrato->factura_individual == 0) {
                            $contratos = Contrato::where('client_id', $request->cliente_id)->where('factura_individual', 0)->get();
                        } else {
                            $contratos = Contrato::where('id', $request->contrato_id)->get();
                        }
                    }

                    foreach ($contratos as $co) {
                        if ($co->plan_id) {
                            $plan = PlanesVelocidad::find($co->plan_id);
                            if ($plan && $plan->item) {
                                $item = Inventario::find($plan->item);
                                if ($item) {
                                    $item->contrato_nro = $co->nro;
                                    $items[] = $item;
                                }
                            }
                        }

                        if ($co->servicio_tv) {
                            $item = Inventario::find($co->servicio_tv);
                            if ($item) {
                                $item->contrato_nro = $co->nro;
                                $items[] = $item;
                            }
                        }

                        if ($co->servicio_otro) {
                            $item = Inventario::find($co->servicio_otro);
                            if ($item) {
                                $item->contrato_nro = $co->nro;
                                $items[] = $item;
                            }
                        }
                    }
                }

                return response()->json([
                    'status' => true,
                    'code' => 200,
                    'message' => "Items obtenidos correctamente.",
                    'data' => $items
                ]);
            }
        } catch (\Throwable $th) {
            $errorData = json_decode($th->getMessage(), true) ?? $th->getMessage();
            return response()->json(['code' => 422, 'message' => $errorData]);
        }
    }

    public function cambiarEtiqueta($etiqueta, $contrato)
    {

        $contrato =  Contrato::where('id', $contrato)->where('empresa', Auth::user()->empresa)->first();
        if ($etiqueta == 0) {
            $contrato->etiqueta_id = null;
        } else {
            $contrato->etiqueta_id = $etiqueta;
        }

        $contrato->update();
        return $contrato->etiqueta;
    }
}
