<?php

namespace App\Http\Controllers;

use App\Barrios;
use App\Campos;
use App\Contacto;
use App\Contrato;
use App\CRM;
use App\Empresa;
use App\Etiqueta;
use App\Funcion;
use App\Mikrotik;
use App\Model\Ingresos\Factura;
use App\Model\Inventario\ListaPrecios;
use App\Oficina;
use App\TipoEmpresa;
use App\TipoIdentificacion;
use App\Vendedor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use PHPExcel;

use Exception;
use Illuminate\Support\Facades\Auth;

include_once app_path().'/../public/PHPExcel/Classes/PHPExcel.php';
use PHPExcel_IOFactory;
use PHPExcel_Style_Alignment;
use PHPExcel_Style_Border;
use PHPExcel_Style_Fill;
use RouterosAPI;

include_once app_path().'/../public/routeros_api.class.php';
use Session;

class ContactosController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
        set_time_limit(300);
        $this->middleware(function ($request, $next) {
            $this->getAllPermissions(Auth::user()->id);
            if (!isset($_SESSION['permisos']['1']) && !isset($_SESSION['permisos']['2']) && !isset($_SESSION['permisos']['3']) && !isset($_SESSION['permisos']['4']) && !isset($_SESSION['permisos']['5']) && !isset($_SESSION['permisos']['6']) && !isset($_SESSION['permisos']['7'])) {
                return redirect('empresa')->with('danger', 'No tiene permisos para acceder a este módulo');
            }
            return $next($request);
        });
        view()->share(['inicio' => 'master', 'seccion' => 'contactos', 'title' => 'Clientes', 'icon' => 'fas fa-users']);
    }

    public function index(Request $request)
    {
        $this->getAllPermissions(Auth::user()->id);
        $tabla = Campos::join('campos_usuarios', 'campos_usuarios.id_campo', '=', 'campos.id')->where('campos_usuarios.id_modulo', 1)->where('campos_usuarios.id_usuario', Auth::user()->id)->where('campos_usuarios.estado', 1)->orderBy('campos_usuarios.orden', 'ASC')->get();
        $barrios = DB::table('barrios')->where('status',1)->get();
        view()->share(['invert' => true]);

        return view('contactos.indexnew',compact('barrios'));
    }

    public function contactos(Request $request, $tipo_usuario)
    {
        $municipio = DB::table('municipios')->select('id')->where('nombre', '=', $request->municipio)->first();
        $modoLectura = auth()->user()->modo_lectura();
        $contactos = Contacto::query();
        $etiquetas = Etiqueta::where('empresa_id', auth()->user()->empresa)->get();


        if ($request->filtro == true) {
            if ($request->identificacion) {
                $contactos->where(function ($query) use ($request) {
                    $query->orWhere('nit', 'like', "%{$request->identificacion}%");
                });
            }
            if ($request->nombre) {
                $contactos->where(function ($query) use ($request) {
                    $query->orWhere('nombre', 'like', "%{$request->nombre}%");
                });
            }
            if ($request->municipio) {
                $contactos->where(function ($query) use ($municipio) {
                    $query->orWhere('fk_idmunicipio', 'like', "%{$municipio->id}%");
                });
            }
            if ($request->apellido) {
                $contactos->where(function ($query) use ($request) {
                    $query->orWhere('apellido1', 'like', "%{$request->apellido}%");
                    $query->orWhere('apellido2', 'like', "%{$request->apellido}%");
                    $query->orWhere('nombre', 'like', "%{$request->apellido}%");
                });
            }
            if ($request->celular) {
                $contactos->where(function ($query) use ($request) {
                    $query->orWhere('celular', 'like', "%{$request->celular}%");
                });
            }
            if ($request->direccion) {
                $direccion = $request->direccion;
                $direccion = explode(' ', $direccion);
                $direccion = array_reverse($direccion);

                foreach ($direccion as $dir) {
                    $dir = strtolower($dir);
                    $dir = str_replace('#', '', $dir);
                    $contactos->where(function ($query) use ($dir) {
                        $query->orWhere('direccion', 'like', "%{$dir}%");
                    });
                }
            }
            if ($request->barrio) {
                $contactos->where(function ($query) use ($request) {
                    $query->orWhere('barrio_id', $request->barrio);
                });
            }
            if ($request->vereda) {
                $contactos->where(function ($query) use ($request) {
                    $query->orWhere('vereda', 'like', "%{$request->vereda}%");
                });
            }
            if ($request->etiqueta_id) {
                $contactos->where(function ($query) use ($request) {
                    $query->orWhere('etiqueta_id', $request->etiqueta_id);
                });
            }
            if ($request->email) {
                $contactos->where(function ($query) use ($request) {
                    $query->orWhere('email', 'like', "%{$request->email}%");
                });
            }
            if ($request->serial_onu) {
                $contactos->where(function ($query) use ($request) {
                    $query->orWhere('serial_onu', 'like', "%{$request->serial_onu}%");
                });
            }
            if ($request->otra_opcion && $request->otra_opcion == "opcion_1") {
                $contactos->where(function ($query) use ($request) {
                    $query->orWhere('saldo_favor', '>', 0);
                });
            }

            if ($request->t_contrato == 1) {
                // SIN contrato
                $contactos->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('contracts')
                        ->whereRaw('contactos.id = contracts.client_id');
                });
            } elseif ($request->t_contrato == 2) {
                // CON contrato
                $contactos->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('contracts')
                        ->whereRaw('contactos.id = contracts.client_id');
                });
            }
        }


        $contactos->where('contactos.empresa', auth()->user()->empresa);
        $contactos->whereIn('tipo_contacto', [$tipo_usuario, 2]);
        $contactos->where('contactos.status', 1);

        return datatables()->eloquent($contactos)
            ->orderColumn('contrato', function ($query, $keyword) {
                 $query->orderByRaw("(SELECT nro FROM contracts WHERE client_id = contactos.id AND status = 1 ORDER BY id DESC LIMIT 1) $keyword");
            })
            ->orderColumn('ip', function ($query, $keyword) {
                 $query->orderByRaw("(SELECT ip FROM contracts WHERE client_id = contactos.id AND status = 1 ORDER BY id DESC LIMIT 1) $keyword");
            })
            ->orderColumn('state_olt_catv', function ($query, $keyword) {
                 $query->orderByRaw("(SELECT state_olt_catv FROM contracts WHERE client_id = contactos.id AND status = 1 ORDER BY id DESC LIMIT 1) $keyword");
            })
            ->orderColumn('barrio', function ($query, $keyword) {
                 $query->orderByRaw("(SELECT nombre FROM barrios WHERE barrios.id = contactos.barrio_id LIMIT 1) $keyword");
            })
            ->orderColumn('radicado', function ($query, $keyword) {
                 $query->orderByRaw("(SELECT COUNT(*) FROM radicados WHERE radicados.identificacion = contactos.nit) $keyword");
            })
            ->orderColumn('etiqueta', function ($query, $keyword) {
                 $query->orderByRaw("(SELECT nombre FROM etiquetas WHERE etiquetas.id = contactos.etiqueta_id LIMIT 1) $keyword");
            })
            ->orderColumn('fk_idmunicipio', function ($query, $keyword) {
                 $query->orderByRaw("(SELECT nombre FROM municipios WHERE municipios.id = contactos.fk_idmunicipio LIMIT 1) $keyword");
            })
             ->editColumn('serial_onu', function (Contacto $contacto) {

                 return $contacto->serial_onu;
             })
            ->editColumn('nombre', function (Contacto $contacto) {
                return '<a href='.route('contactos.show', $contacto->id).">{$contacto->nombre} {$contacto->apellidos()}</div></a>";
            })
            ->editColumn('nit', function (Contacto $contacto) {
                return "{$contacto->tip_iden('mini')} {$contacto->nit}";
            })
            ->addColumn('etiqueta', function(Contacto $contacto)use ($etiquetas){
                return view('contactos.etiqueta', compact('etiquetas','contacto'));
            })
            ->editColumn('telefono1', function (Contacto $contacto) {
                return $contacto->celular ? $contacto->celular : $contacto->telefono1;
            })
            ->editColumn('email', function (Contacto $contacto) {
                return $contacto->email;
            })
            ->editColumn('direccion', function (Contacto $contacto) {
                return $contacto->direccion;
            })
            ->editColumn('barrio', function (Contacto $contacto) {
                return $contacto->barrio()->nombre;
            })
            ->editColumn('vereda', function (Contacto $contacto) {
                return $contacto->vereda;
            })
            ->editColumn('contrato', function (Contacto $contacto) {
                return $contacto->contract();
            })
            ->editColumn('fecha_contrato', function (Contacto $contacto) {
                return ($contacto->fecha_contrato) ? date('d-m-Y g:i:s A', strtotime($contacto->fecha_contrato)) : '- - - -';
            })
            ->editColumn('fk_idmunicipio', function (Contacto $contacto) {
                return $contacto->municipio()->nombre;
            })
            ->editColumn('radicado', function (Contacto $contacto) {
                return $contacto->radicados();
            })
            ->editColumn('ip', function (Contacto $contacto) {
                if ($contacto->contract('true') != 'N/A') {
                    $puerto = $contacto->contrato()->puerto ? ':'.$contacto->contrato()->puerto->nombre : '';
                }

                return ($contacto->contract('true') == 'N/A') ? 'N/A' : '<a href="http://'.$contacto->contract('true').''.$puerto.'" target="_blank">'.$contacto->contract('true').''.$puerto.' <i class="fas fa-external-link-alt"></i></a>';
            })

            ->addColumn('state_olt_catv', function (Contacto $contacto) {
                $contrato = $contacto->contrato();
                if ($contrato && $contrato->olt_sn_mac) {
                    $estado = $contrato->state_olt_catv == 1 ? 'Habilitado' : 'Deshabilitado';
                    $color = $contrato->state_olt_catv == 1 ? 'success' : 'danger';
                    return '<span class="text-' . $color . ' font-weight-bold">' . $estado . '</span>';
                }
                return 'N/A';
            })
            ->addColumn('acciones', $modoLectura ? '' : 'contactos.acciones-contactos')
            ->rawColumns(['acciones', 'nombre', 'contrato', 'ip', 'state_olt_catv'])
            ->toJson();
    }
    public function clientes(Request $request)
    {

        $this->getAllPermissions(Auth::user()->id);
        view()->share(['title' => 'Clientes', 'subseccion' => 'clientes']);
        $busqueda = false;
        if ($request->name_1 || $request->name_2 || $request->name_3 || isset($request->name_4) || $request->name_5) {
            $busqueda = 'contactos.clientes';
        }
        $tipo = '/0';
        $tipos_empresa = TipoEmpresa::where('empresa', Auth::user()->empresa)->get();
        // $contactos = $this->busqueda($request, [0, 2]);
        $totalContactos = Contacto::where('empresa', Auth::user()->empresa)->count();
        // $contactos = Contacto::where('empresa', Auth::user()->empresa)->get();
        $contactos = DB::table('contactos')->join('municipios', 'contactos.fk_idmunicipio', '=', 'municipios.id')->select('contactos.*', 'municipios.nombre as nombre_municipio')->get();
        $tipo_usuario = 0;
        $tabla = Campos::join('campos_usuarios', 'campos_usuarios.id_campo', '=', 'campos.id')->where('campos_usuarios.id_modulo', 1)->where('campos_usuarios.id_usuario', Auth::user()->id)->where('campos_usuarios.estado', 1)->orderBy('campos_usuarios.orden', 'ASC')->get();
        $etiquetas = Etiqueta::where('empresa_id', auth()->user()->empresa)->get();
        $barrios = DB::table('barrios')->where('status',1)->get();

        view()->share(['invert' => true]);

        return view('contactos.indexnew')->with(compact('contactos', 'totalContactos', 'tipo_usuario', 'tabla', 'etiquetas','barrios'));
    }

    public function proveedores(Request $request)
    {
        $this->getAllPermissions(Auth::user()->id);
        $busqueda = false;
        if ($request->name_1 || $request->name_2 || $request->name_3 || isset($request->name_4) || $request->name_5) {
            $busqueda = 'contactos.proveedores';
        }
        $tipo = '/1';
        view()->share(['title' => 'Proveedores', 'subseccion' => 'proveedores']);
        $tipos_empresa = TipoEmpresa::where('empresa', Auth::user()->empresa)->get();
        $contactos = $this->busqueda($request, [1, 2]);
        $totalContactos = Contacto::where('empresa', Auth::user()->empresa)->count();
        $tipo_usuario = 1;
        $tabla = Campos::join('campos_usuarios', 'campos_usuarios.id_campo', '=', 'campos.id')->where('campos_usuarios.id_modulo', 1)->where('campos_usuarios.id_usuario', Auth::user()->id)->where('campos_usuarios.estado', 1)->orderBy('campos_usuarios.orden', 'ASC')->get();
        $barrios = DB::table('barrios')->where('status',1)->get();
        view()->share(['invert' => true]);

        return view('contactos.indexnew')->with(compact('contactos', 'tipo', 'request', 'busqueda', 'tipos_empresa',
        'totalContactos', 'tipo_usuario', 'tabla','barrios'));
    }

    public function busqueda($request, $tipo = false)
    {

        $this->getAllPermissions(Auth::user()->id);
        $campos = [
            'id',
            'contactos.nombre',
            'contactos.nit',
            'contactos.telefono1',
            'contactos.tipo_contacto',
            'te.nombre',
        ];
        if (! $request->orderby) {
            $request->orderby = 0;
            $request->order = 1;
        }
        $orderby = $campos[$request->orderby];
        $order = $request->order == 1 ? 'DESC' : 'ASC';
        $appends = ['orderby' => $request->orderby, 'order' => $request->order];
        $contactos = Contacto::join('tipos_empresa as te', 'te.id', '=', 'contactos.tipo_empresa')
            ->leftJoin('vendedores as v', 'contactos.vendedor', '=', 'v.id')
            ->select(
                'contactos.*',
                'te.nombre as tipo_emp',
                DB::raw('v.nombre as nombrevendedor', 'count(contactos.id) as total')
            )
            ->where('contactos.empresa', Auth::user()->empresa)->where('lectura', 0);
        if ($request->name_1) {
            $appends['name_1'] = $request->name_1;
            $contactos = $contactos->where('contactos.nombre', 'like', '%'.$request->name_1.'%');
        }
        if ($request->name_2) {
            $appends['name_2'] = $request->name_2;
            $contactos = $contactos->where('contactos.nit', 'like', '%'.$request->name_2.'%');
        }
        if ($request->name_3) {
            $appends['name_3'] = $request->name_3;
            $contactos = $contactos->where('contactos.telefono1', 'like', '%'.$request->name_3.'%');
        }
        if (isset($request->name_4)) {
            $appends['name_4'] = $request->name_4;
            $contactos = $contactos->where('contactos.tipo_contacto', $request->name_4);
        }
        if ($tipo) {
            $contactos = $contactos->whereIn('contactos.tipo_contacto', $tipo);
        }
        if ($request->name_5) {
            $appends['name_5'] = $request->name_5;
            $contactos = $contactos->where('contactos.tipo_empresa', $request->name_5);
        }
        if ($request->name_6) {
            $appends['name_6'] = $request->name_6;
            $contactos = $contactos->where('v.nombre', 'like', '%'.$request->name_6.'%');
        }
        $contactos = $contactos->OrderBy($orderby, $order)->paginate(25)->appends($appends);

         return $contactos;
    }

    public function show($id)
    {
        if (!isset($_SESSION['permisos']['4'])) {
            return redirect()->back()->with('danger', 'No cuenta con los permisos necesarios para realizar esta acción');
        }
        $this->getAllPermissions(Auth::user()->id);

        $contacto = Contacto::join('tipos_identificacion AS I', 'I.id', '=', 'contactos.tip_iden')->where('contactos.id', $id)->where('contactos.empresa', Auth::user()->empresa)->select('contactos.*', 'I.identificacion')->first();

        if ($contacto) {
            if ($contacto->tipo_contacto == 0) {
                view()->share(['title' => $contacto->nombre.' '.$contacto->apellidos(), 'subseccion' => 'clientes', 'middel' => true]);
            } else {
                view()->share(['title' => $contacto->nombre.' '.$contacto->apellidos(), 'subseccion' => 'proveedores', 'middel' => true]);
            }

            $user_app = DB::table('usuarios_app')->where('id_cliente', $contacto->id)->where('status', 1)->first();
            $contratos = Contrato::where('client_id', $contacto->id)->get();

            return view('contactos.show')->with(compact('contacto', 'id', 'user_app', 'contratos'));
        }

        return redirect('empresa/contactos')->with('danger', 'CLIENTE NO ENCONTRADO, INTENTE NUEVAMENTE');
    }

    public function create()
    {
        $this->getAllPermissions(Auth::user()->id);
        $identificaciones = TipoIdentificacion::all();
        $paises = DB::table('pais')->whereIn('codigo', ['CO','VE'])->get();
        $departamentos = DB::table('departamentos')->get();
        $oficinas = (Auth::user()->oficina && Auth::user()->empresa()->oficina) ? Oficina::where('id', Auth::user()->oficina)->get() : Oficina::where('empresa', Auth::user()->empresa)->where('status', 1)->get();
        $barrios = DB::table('barrios')->where('status',1)->get();
        view()->share(['icon' => '', 'title' => 'Nuevo Contacto', 'subseccion' => 'clientes', 'middel' => true]);

        return view('contactos.create')->with(compact('identificaciones', 'paises', 'departamentos', 'oficinas','barrios'));
    }

    public function asociarBarrio(Request $request){
        $name = $request->nombre;
        $id = "";
        $user = Auth::user()->id;

        if(!DB::table('barrios')->where('nombre',strtolower($name))->first()){

            $id = DB::table('barrios')->insertGetId([
                'nombre' => strtolower($name),
                'created_by' => $user
            ]);
        }

        return response()->json(['nombre' => $request->nombre, 'id' => $id]);
    }

    public function createp()
    {
        $this->getAllPermissions(Auth::user()->id);
        $identificaciones = TipoIdentificacion::all();

        $vendedores = Vendedor::where('empresa', Auth::user()->empresa)
            ->where('estado', 1)
            ->get();

        $listas = ListaPrecios::where('empresa', Auth::user()->empresa)
            ->where('status', 1)
            ->get();

        $tipos_empresa = TipoEmpresa::where('empresa', Auth::user()->empresa)
            ->get();

        $paises = DB::table('pais')->whereIn('codigo', ['CO','VE'])->get();

        $departamentos = DB::table('departamentos')->get();

        $oficinas = (Auth::user()->oficina && Auth::user()->empresa()->oficina) ? Oficina::where('id', Auth::user()->oficina)->get() : Oficina::where('empresa', Auth::user()->empresa)->where('status', 1)->get();
        
        $barrios = DB::table('barrios')->where('status',1)->get();

        view()->share(['title' => 'Nuevo Proveedor', 'subseccion' => 'proveedores', 'middel' => true]);

        return view('contactos.createp')->with(compact(
            'identificaciones',
            'tipos_empresa',
            'vendedores',
            'listas',
            'paises',
            'departamentos',
            'oficinas',
            'barrios'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'tipo_contacto' => 'required',
        ]);
        $contacto = Contacto::where('nit', $request->nit)->where('status', 1)->where('empresa', Auth::user()->empresa)->first();

        if ($contacto) {
            $errors = (object) [];
            $errors->nit = 'La Identificación esta registrada para otro contacto';

            return back()->withErrors($errors)->withInput();
        }

        $contacto = new Contacto;
        $contacto->empresa = Auth::user()->empresa;
        $contacto->tip_iden = $request->tip_iden;
        $contacto->dv = $request->dvoriginal;
        $contacto->nit = $request->nit;
        $contacto->nombre = $request->nombre;
        $contacto->apellido1 = $request->apellido1;
        $contacto->apellido2 = $request->apellido2;
        $contacto->ciudad = ucwords(mb_strtolower($request->ciudad));
        $contacto->barrio_id = $request->barrio_id;
        $contacto->vereda = $request->vereda;
        $contacto->direccion = $request->direccion;
        $contacto->email = mb_strtolower($request->email);
        $contacto->telefono1 = $request->telefono1;
        $contacto->telefono2 = $request->telefono2;
        $contacto->fax = $request->fax;
        $contacto->celular = $request->celular;
        $contacto->observaciones = $request->observaciones;
        $contacto->tipo_contacto = count($request->tipo_contacto) == 2 ? 2 : $request->tipo_contacto[0];
        $contacto->plan_velocidad    = 0;
        $contacto->costo_instalacion = "a";

        $contacto->fk_idpais = $request->pais;
        $contacto->fk_iddepartamento = $request->departamento;
        $contacto->fk_idmunicipio = $request->municipio;
        $contacto->cod_postal = $request->cod_postal;
        if (empty($request->boton_emision)) {
            // Asignar el valor "1" si está vacío o no está definido
            $request->boton_emision = 0;
        }
        $contacto->boton_emision = $request->boton_emision;
        //nuevos cmapos agregados
        $contacto->monitoreo = $request->monitoreo;
        $contacto->refiere = $request->refiere;
        $contacto->combo_int_tv = $request->combo_int_tv;
        $contacto->referencia_1 = $request->referencia_1;
        $contacto->referencia_2 = $request->referencia_2;
        $contacto->cierra_venta = $request->cierra_venta;
        $contacto->factura_est_elec = $request->factura_est_elec ?? 0;

        if ($request->tipo_persona == null) {
            $contacto->responsableiva = 2;
        } else {
            $contacto->responsableiva = $request->responsable;
        }

        if($request->tip_iden == 6){
            $contacto->tipo_persona = 2; // tipo persona juridica
        }else{
            $contacto->tipo_persona = 1; // tipo persona natural
        }

        $contacto->tipo_empresa = $request->tipo_empresa;
        $contacto->lista_precio = $request->lista_precio;
        $contacto->vendedor = $request->vendedor;
        $contacto->oficina = $request->oficina;
        $contacto->feliz_cumpleanos = $request->feliz_cumpleanos ? date("Y-m-d", strtotime($request->feliz_cumpleanos)) : '';

        $contacto->save();

        if ($contacto->tipo_contacto == 0) {
            $mensaje = 'SE HA CREADO SATISFACTORIAMENTE EL CLIENTE';

            return redirect('empresa/contactos/clientes')->with('success', $mensaje);
        } else {
            $mensaje = 'SE HA CREADO SATISFACTORIAMENTE EL PROVEEDOR';

            return redirect('empresa/contactos/proveedores')->with('success', $mensaje);
        }
    }

    public function storeBack(Request $request)
    {
        $contacto = Contacto::where('nit', $request->nit)->where('empresa', Auth::user()->empresa)->first();
        if ($contacto) {
            $arrayPost['status'] = 'error';
            $arrayPost['mensaje'] = 'La Identificación esta registrada para otro contacto';
            echo json_encode($arrayPost);
            exit;
        }

        if (! $request->tipo_contacto) {
            $arrayPost['status'] = 'error';
            $arrayPost['mensaje'] = 'El Tipo de Contacto es requerido';
            echo json_encode($arrayPost);
            exit;
        }

        $contacto = new Contacto;
        $contacto->empresa = Auth::user()->empresa;
        $contacto->tip_iden = $request->tip_iden;
        $contacto->dv = $request->dvoriginal;
        $contacto->nit = $request->nit;
        $contacto->nombre = $request->nombre;
        $contacto->apellido1 = $request->apellido1;
        $contacto->apellido2 = $request->apellido2;
        $contacto->ciudad = ucwords(mb_strtolower($request->ciudad));
        $contacto->barrio = $request->barrio;
        $contacto->vereda = $request->vereda;
        $contacto->direccion = $request->direccion;
        $contacto->email = mb_strtolower($request->email);
        $contacto->telefono1 = $request->telefono1;
        $contacto->telefono2 = $request->telefono2;
        $contacto->fax = $request->fax;
        $contacto->celular = $request->celular;
        $contacto->tipo_contacto = count($request->tipo_contacto) == 2 ? 2 : $request->tipo_contacto[0];
        $contacto->observaciones = $request->observaciones;

        $contacto->fk_idpais = $request->pais;
        $contacto->fk_iddepartamento = $request->departamento;
        $contacto->fk_idmunicipio = $request->municipio;
        $contacto->cod_postal = $request->cod_postal;
        $contacto->oficina = $request->oficina;

        if ($request->tipo_persona == null) {
            $contacto->responsableiva = 2;
        } else {
            $contacto->responsableiva = $request->responsable;
        }

        if($request->tip_iden == 6){
            $contacto->tipo_persona = 2; // tipo persona juridica
        }else{
            $contacto->tipo_persona = 1; // tipo persona natural
        }

        $contacto->save();

        $contacId = Contacto::all()->last()->id;
        $contac = Contacto::all()->last()->nombre;
        $contacNit = Contacto::all()->last()->nit;

        if ($contacto) {
            $arrayPost['status'] = 'OK';
            $arrayPost['id'] = $contacId;
            $arrayPost['contacto'] = $contac;
            $arrayPost['nit'] = $contacNit;
            echo json_encode($arrayPost);
            exit;
        }
    }

    public function edit($id)
    {
        $this->getAllPermissions(Auth::user()->id);
        $contacto = Contacto::where('id', $id)->where('empresa', Auth::user()->empresa)->first();

        if ($contacto) {
            $identificaciones = TipoIdentificacion::all();
            $paises = DB::table('pais')->whereIn('codigo', ['CO','VE'])->get();
            $departamentos = DB::table('departamentos')->get();

            $vendedores = Vendedor::where('empresa', Auth::user()->empresa)->where('estado', 1)->get();
            $listas = ListaPrecios::where('empresa', Auth::user()->empresa)->where('status', 1)->get();
            $tipos_empresa = TipoEmpresa::where('empresa', Auth::user()->empresa)->get();
            $oficinas = (Auth::user()->oficina && Auth::user()->empresa()->oficina) ? Oficina::where('id', Auth::user()->oficina)->get() : Oficina::where('empresa', Auth::user()->empresa)->where('status', 1)->get();
            $barrios = DB::table('barrios')->where('status',1)->get();

            session(['url_search' => url()->previous()]);

            if ($contacto->tipo_contacto == 0) {
                view()->share(['title' => 'Editar: '.$contacto->nombre.' '.$contacto->apellidos(), 'subseccion' => 'clientes', 'middel' => true, 'icon' => '']);
            } else {
                view()->share(['title' => 'Editar: '.$contacto->nombre.' '.$contacto->apellidos(), 'subseccion' => 'proveedores', 'middel' => true, 'icon' => '']);
            }

            return view('contactos.edit')->with(compact('contacto', 'identificaciones', 'paises', 'departamentos',
             'vendedores', 'listas', 'tipos_empresa', 'oficinas','barrios'));
        }

        return redirect('empresa/contactos')->with('danger', 'CLIENTE NO ENCONTRADO, INTENTE NUEVAMENTE');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'tipo_contacto' => 'required',
        ]);
        $empresa = Auth::user()->empresa;
        $error = Contacto::where('nit', $request->nit)->where('tip_iden', $request->tip_iden)
            ->where('id', '<>', $id)
            ->where('empresa', $empresa)
            ->where('status', 1)
            ->first();

        if ($error) {
            $errors = (object) [];
            $errors->nit = 'La Identificación esta registrada para otro contacto';

            return back()->withErrors($errors)->withInput();
        }
        $contacto = Contacto::where('id', $id)->where('empresa', $empresa)->first();
        if ($contacto) {
            $contacto->empresa = $empresa;
            $contacto->tip_iden = $request->tip_iden;
            $contacto->dv = $request->dvoriginal;
            $contacto->nit = $request->nit;
            $contacto->ciudad = ucwords(mb_strtolower($request->ciudad));
            $contacto->nombre = $request->nombre;
            $contacto->apellido1 = $request->apellido1;
            $contacto->apellido2 = $request->apellido2;
            $contacto->barrio_id = $request->barrio_id;
            $contacto->vereda = $request->vereda;
            $contacto->direccion = $request->direccion;
            $contacto->email = mb_strtolower($request->email);
            $contacto->telefono1 = $request->telefono1;
            $contacto->telefono2 = $request->telefono2;
            $contacto->fax = $request->fax;
            $contacto->celular = $request->celular;
            $contacto->observaciones = $request->observaciones;
            $contacto->serial_onu = $request->serial_onu;
            $contacto->tipo_contacto = count($request->tipo_contacto) == 2 ? 2 : $request->tipo_contacto[0];
            $contacto->fk_idpais = $request->pais;
            $contacto->fk_iddepartamento = $request->departamento;
            $contacto->fk_idmunicipio = $request->municipio;
            $contacto->cod_postal = $request->cod_postal;
            $contacto->tipo_empresa = $request->tipo_empresa;
            $contacto->lista_precio = $request->lista_precio;
            $contacto->vendedor = $request->vendedor;
            $contacto->oficina = $request->oficina;
            $contacto->router = $request->router;
            $contacto->boton_emision = $request->boton_emision;
            //nuevos cmapos agregados
            $contacto->monitoreo = $request->monitoreo;
            $contacto->refiere = $request->refiere;
            $contacto->combo_int_tv = $request->combo_int_tv;
            $contacto->referencia_1 = $request->referencia_1;
            $contacto->referencia_2 = $request->referencia_2;
            $contacto->cierra_venta = $request->cierra_venta;
            $contacto->factura_est_elec = $request->factura_est_elec ?? 0;
            $contacto->plan_velocidad    = 0;
            $contacto->costo_instalacion = "a";
            $contacto->feliz_cumpleanos = $request->feliz_cumpleanos ? date("Y-m-d", strtotime($request->feliz_cumpleanos)) : '';

            $contacto->save();

            $contrato = Contrato::where('client_id', $contacto->id)->where('status', 1)->first();

            if ($contrato) {
                $mikrotik = Mikrotik::find($contrato->server_configuration_id);
                $servicio = $this->normaliza($contacto->nombre.' '.$contacto->apellido1.' '.$contacto->apellido2).'-'.$contrato->nro;
                if ($mikrotik) {
                    $API = new RouterosAPI();
                    $API->port = $mikrotik->puerto_api;
                    //$API->debug = true;

                    if ($API->connect($mikrotik->ip, $mikrotik->usuario, $mikrotik->clave)) {
                        /*PPPOE*/
                        if ($contrato->conexion == 1) {
                            $API->comm("ppp/secrets\n=find\n=name=$contrato->servicio\n=[set\n=remote-address=$request->ip]");
                        }

                        /*DHCP*/
                        if ($contrato->conexion == 2) {

                        }

                        /*IP ESTÁTICA*/
                        if ($contrato->conexion == 3) {
                            $name = $API->comm('/queue/simple/getall', [
                                '?comment' => $contrato->servicio,
                            ]
                            );

                            if ($name) {
                                $API->comm('/queue/simple/set', [
                                    '.id' => $name[0]['.id'],
                                    'name' => $servicio,       // NOMBRE CLIENTE
                                    'comment' => $servicio,       // NOMBRE CLIENTE
                                ]
                                );
                            }
                        }

                        /*VLAN*/
                        if ($contrato->conexion == 4) {

                        }
                    }

                    $contrato->servicio = $servicio;
                    $contrato->save();
                    $API->disconnect();
                }
            }

            if ($contacto->tipo_contacto == 0) {
                $mensaje = 'SE HA MODIFICADO SATISFACTORIAMENTE EL CLIENTE';

                return redirect('empresa/contactos/clientes')->with('success', $mensaje);
            } else {
                $mensaje = 'SE HA MODIFICADO SATISFACTORIAMENTE EL PROVEEDOR';

                return redirect('empresa/contactos/proveedores')->with('success', $mensaje);
            }
        }

        return redirect('empresa/contactos')->with('danger', 'CLIENTE NO ENCONTRADO, INTENTE NUEVAMENTE');
    }

    public function destroy($id)
    {
        $contacto = Contacto::where('id', $id)->where('empresa', Auth::user()->empresa)->first();
        $contrato = Contrato::where('client_id', $contacto->id)->first();
        $empresa = Empresa::find(1);
        if ($contacto) {
            $tipo_usuario = $contacto->tipo_usuario;
            $contacto->status = 0;
            $contacto->save();
            if ($contrato) {
                $contrato->status = 0;
                $contrato->state = 'disabled';
                $contrato->save();

                // Etiqueta automática: contrato deshabilitado por eliminación de cliente
                \App\Traits\AplicaEtiquetaAutomatica::aplicarEtiquetaAutomatica(
                    $contrato->id,
                    Auth::user()->empresa,
                    \App\EtiquetaAutomaticaContrato::MODULO_CONTRATOS,
                    \App\EtiquetaAutomaticaContrato::CLIENTE_ELIMINADO
                );
            }
            $mensaje = 'SE HA ELIMINADO EL CLIENTE Y SU CONTRATO RELACIONADO';

            $tipo_usuario = ($tipo_usuario == 0) ? 'clientes' : 'proveedores';

            return redirect('empresa/contactos/'.$tipo_usuario)->with('success', $mensaje);
        } else {
            return redirect('empresa/contactos')->with('danger', 'CLIENTE NO ENCONTRADO, INTENTE NUEVAMENTE');
        }
    }

    /*
    * Generar un json con los datos del contacto
    */
    public function json($id = false, $type = false)
    {

        if (! $id) {
            $contactos = Contacto::where('empresa', Auth::user()->empresa)->whereIn('tipo_contacto', [0, 2])->get();
            if ($contactos) {
                return json_encode($contactos);
            }
        }

        $contacto = Contacto::join('contracts as cs', 'contactos.id', '=', 'cs.client_id')->where('contactos.id', $id)->first();
        if (isset($contacto->plan_id)) {
            if ($contacto->plan_id) {
                $contacto = DB::select("SELECT C.id, C.nombre, C.boton_emision ,C.apellido1, C.apellido2, C.nit, C.tip_iden, C.telefono1, C.celular, C.estrato,
                C.saldo_favor, C.saldo_favor2 ,CS.public_id as contrato, CS.facturacion, I.id as plan,
                 GC.fecha_corte, GC.fecha_suspension, CS.servicio_tv, CS.servicio_otro, C.factura_est_elec FROM contactos AS C INNER JOIN contracts AS CS ON (C.id = CS.client_id)
                 INNER JOIN planes_velocidad AS P ON (P.id = CS.plan_id) INNER JOIN inventario AS I ON (I.id = P.item)
                 INNER JOIN grupos_corte AS GC ON (GC.id = CS.grupo_corte)
                 WHERE C.status = '1' AND  C.id = '".$id."'");
            }
        }

        if ($contacto) {
            return json_encode($contacto);
        } else {
            return json_encode(Contacto::find($id));
        }
    }

    /*
    * Generar un archivo xml de los contactos
    */
    public function exportar($tipo = 2)
    {
        $objPHPExcel = new PHPExcel();
        $tituloReporte = 'Reporte de Contactos de '.Auth::user()->empresa()->nombre;
        $titulosColumnas = ['Nombres', 'Apellido1', 'Apellido2', 'Tipo de identificacion', 'Identificacion', 'DV', 'Pais', 'Departamento', 'Municipio', 'Codigo postal', 'Telefono', 'Celular', 'Direccion', 'Verada/Corregimiento', 'Barrio', 'Ciudad', 'Correo Electronico', 'Observaciones', 'Tipo de Contacto', 'Contrato', 'Saldo a favor', 'Etiqueta', 'Total Debe']; // /// NUEVA COLUMNA: Total Debe
        $letras = range('A', 'Z');

        $objPHPExcel->getProperties()->setCreator('Sistema')
            ->setLastModifiedBy('Sistema')
            ->setTitle('Reporte Excel Contactos')
            ->setSubject('Reporte Excel Contactos')
            ->setDescription('Reporte de Contactos')
            ->setKeywords('reporte Contactos')
            ->setCategory('Reporte excel');

        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('A1:D1');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $tituloReporte);
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('A2:C2');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A2', 'Fecha '.date('d-m-Y'));

        $estilo = ['font' => ['bold' => true, 'size' => 12, 'name' => 'Times New Roman'], 'alignment' => ['horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER]];
        // /// Actualizado para incluir todas las columnas dinámicamente
        $lastColumnHeader = $letras[count($titulosColumnas) - 1];
        $objPHPExcel->getActiveSheet()->getStyle('A1:'.$lastColumnHeader.'3')->applyFromArray($estilo);

        $estilo = [
            'fill' => [
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => ['rgb' => substr(Auth::user()->empresa()->color, 1)],
            ],
            'font' => [
                'bold' => true,
                'size' => 12,
                'name' => 'Times New Roman',
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'alignment' => [
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
            ],
        ];

        $lastColumn = $letras[count($titulosColumnas) - 1];
        $objPHPExcel->getActiveSheet()->getStyle("A3:{$lastColumn}3")->applyFromArray($estilo);

        for ($i = 0; $i < count($titulosColumnas); $i++) {
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($letras[$i].'3', utf8_decode($titulosColumnas[$i]));
        }

        $i = 4;

        // /// ============================================================
        // /// INICIO: CÁLCULO OPTIMIZADO DE SALDOS PENDIENTES POR CLIENTE
        // /// ============================================================
        // /// Esta sección calcula el total que debe cada cliente en facturas,
        // /// descontando pagos, abonos y notas crédito.
        // /// Si algo falla, eliminar desde aquí hasta el comentario "FIN"
        // /// ============================================================

        try {
            // /// Subconsulta optimizada: Calcula saldo pendiente por cliente
            // /// Usa agregaciones SQL para evitar N+1 queries
            $saldosPendientes = DB::table('factura as f')
                ->join('items_factura as itemsf', 'itemsf.factura', '=', 'f.id')
                // /// Subconsulta para sumar pagos (excluyendo ingresos anulados)
                ->leftJoin(DB::raw("(
                    SELECT
                        ing_fact.factura,
                        COALESCE(SUM(ing_fact.pago), 0) as total_pagado
                    FROM ingresos_factura as ing_fact
                    INNER JOIN ingresos as i ON i.id = ing_fact.ingreso
                    WHERE i.estatus <> 2
                    GROUP BY ing_fact.factura
                ) as pagos"), 'pagos.factura', '=', 'f.id')
                // /// Subconsulta para sumar notas crédito
                ->leftJoin(DB::raw("(
                    SELECT
                        nf.factura,
                        COALESCE(SUM(nf.pago), 0) as total_notas_credito
                    FROM notas_factura as nf
                    GROUP BY nf.factura
                ) as notas"), 'notas.factura', '=', 'f.id')
                ->where('f.empresa', Auth::user()->empresa)
                ->where('f.estatus', '<>', 2) // Excluir facturas anuladas
                ->select('f.cliente')
                // /// Cálculo del saldo: Total facturado - Pagos - Notas crédito
                ->selectRaw('
                    COALESCE(SUM(
                        (
                            (ROUND(itemsf.precio * itemsf.cant)) -
                            IF(itemsf.desc > 0, (itemsf.precio * itemsf.cant) * (itemsf.desc / 100), 0)
                        ) *
                        IF(itemsf.impuesto > 0, 1 + (itemsf.impuesto / 100), 1)
                    ), 0) -
                    COALESCE(SUM(COALESCE(pagos.total_pagado, 0)), 0) -
                    COALESCE(SUM(COALESCE(notas.total_notas_credito, 0)), 0) as saldo_pendiente
                ')
                ->groupBy('f.cliente')
                ->pluck('saldo_pendiente', 'cliente')
                ->toArray();
        } catch (\Exception $e) {
            // /// Si falla la consulta, inicializar array vacío para evitar errores
            Log::error('Error al calcular saldos pendientes en exportación de contactos', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $saldosPendientes = [];
        }

        // /// ============================================================
        // /// FIN: CÁLCULO OPTIMIZADO DE SALDOS PENDIENTES POR CLIENTE
        // /// ============================================================

        // 🔹 Cargar contactos con JOIN a etiquetas
        $contactos = Contacto::leftJoin('etiquetas', 'etiquetas.id', '=', 'contactos.etiqueta_id')
            ->where('contactos.empresa', Auth::user()->empresa)
            ->where('contactos.status', 1)
            ->select('contactos.*', 'etiquetas.nombre as etiqueta_nombre')
            ->get();

        if ($tipo != 2) {
            $contactos = $contactos->whereIn('tipo_contacto', [$tipo, 2]);
        }

        foreach ($contactos as $contacto) {
            // /// Obtener saldo pendiente del cliente (0 si no tiene facturas pendientes)
            $saldoPendiente = isset($saldosPendientes[$contacto->id])
                ? max(0, round($saldosPendientes[$contacto->id], 0)) // Asegurar que no sea negativo
                : 0;

            $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue($letras[0].$i, $contacto->nombre)
                ->setCellValue($letras[1].$i, $contacto->apellido1)
                ->setCellValue($letras[2].$i, $contacto->apellido2)
                ->setCellValue($letras[3].$i, $contacto->tip_iden())
                ->setCellValue($letras[4].$i, $contacto->nit)
                ->setCellValue($letras[5].$i, $contacto->dv)
                ->setCellValue($letras[6].$i, $contacto->pais()->nombre)
                ->setCellValue($letras[7].$i, $contacto->departamento()->nombre)
                ->setCellValue($letras[8].$i, $contacto->municipio()->nombre)
                ->setCellValue($letras[9].$i, $contacto->cod_postal)
                ->setCellValue($letras[10].$i, $contacto->telefono1)
                ->setCellValue($letras[11].$i, $contacto->celular)
                ->setCellValue($letras[12].$i, $contacto->direccion)
                ->setCellValue($letras[13].$i, $contacto->vereda)
                ->setCellValue($letras[14].$i, $contacto->barrio)
                ->setCellValue($letras[15].$i, $contacto->ciudad)
                ->setCellValue($letras[16].$i, $contacto->email)
                ->setCellValue($letras[17].$i, $contacto->observaciones)
                ->setCellValue($letras[18].$i, $contacto->tipo_contacto())
                ->setCellValue($letras[19].$i, strip_tags($contacto->contract() ?? 'N/A'))
                ->setCellValue($letras[20].$i, $contacto->saldo_favor)
                ->setCellValue($letras[21].$i, $contacto->etiqueta_nombre ?? 'Sin etiqueta')
                ->setCellValue($letras[22].$i, $saldoPendiente); // /// NUEVA COLUMNA: Total Debe

            $i++;
        }

        // ✅ Estilo general
        $estilo = [
            'font' => ['size' => 12, 'name' => 'Times New Roman'],
            'borders' => [
                'allborders' => [
                    'style' => PHPExcel_Style_Border::BORDER_THIN,
                ],
            ],
            'alignment' => ['horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER]
        ];
        // /// Actualizado para incluir nueva columna (W -> X)
        $objPHPExcel->getActiveSheet()->getStyle('A3:'.$letras[count($titulosColumnas) - 1].$i)->applyFromArray($estilo);

        // ✅ Estilo especial para columna Contrato (U)
        $objPHPExcel->getActiveSheet()->getStyle($letras[20].'4:'.$letras[20].($i-1))->applyFromArray([
            'font' => ['size' => 12, 'name' => 'Times New Roman'],
            'alignment' => [
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                'vertical'   => PHPExcel_Style_Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allborders' => [
                    'style' => PHPExcel_Style_Border::BORDER_THIN,
                ],
            ]
        ]);
        $objPHPExcel->getActiveSheet()->getStyle($letras[20].'4:'.$letras[20].($i-1))->getAlignment()->setWrapText(true);
        $objPHPExcel->getActiveSheet()->getColumnDimension($letras[20])->setAutoSize(true);

        // /// Actualizado para incluir todas las columnas incluyendo la nueva
        for ($j = 'A'; $j <= $letras[count($titulosColumnas) - 1]; $j++) {
            $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension($j)->setAutoSize(true);
        }

        $objPHPExcel->getActiveSheet()->setTitle('Reporte de Contactos');
        $objPHPExcel->setActiveSheetIndex(0);

        $objPHPExcel->getActiveSheet(0)->freezePane('A5');
        $objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0, 4);
        $objPHPExcel->setActiveSheetIndex(0);

        header('Pragma: no-cache');
        header('Content-type: application/vnd.ms-excel');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="Reporte_Contactos.xlsx"');
        header('Cache-Control: max-age=0');
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');
        exit;
    }



    /**
     * Vista para importar los contactos
     *
     * @return view
     */
    public function importar()
    {
        $this->getAllPermissions(Auth::user()->id);
        view()->share(['title' => 'Importar Contactos desde Excel', 'subseccion' => 'clientes']);

        $identificaciones = TipoIdentificacion::all();

        return view('contactos.importar')->with(compact('identificaciones'));
    }

    /**
     * Registrar o modificar los datos del contacto
     *
     * @return redirect
     */
    public function cargando(Request $request)
    {
        try {
            DB::beginTransaction();

            // Validación inicial del archivo
            $validator = Validator::make($request->all(), [
                'archivo' => 'required|mimes:xlsx',
            ], [
                'archivo.required' => 'Debe seleccionar un archivo para importar',
                'archivo.mimes'    => 'El archivo debe ser de extensión xlsx',
            ]);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            }

            $create      = 0;
            $modf        = 0;
            $modificados = [];

            $imagen        = $request->file('archivo');
            $nombre_imagen = 'archivo.'.$imagen->getClientOriginalExtension();
            $path          = public_path().'/images/Empresas/Empresa'.Auth::user()->empresa;
            $imagen->move($path, $nombre_imagen);

            ini_set('max_execution_time', 500);

            $fileWithPath  = $path.'/'.$nombre_imagen;
            $inputFileType = PHPExcel_IOFactory::identify($fileWithPath);
            $objReader     = PHPExcel_IOFactory::createReader($inputFileType);
            $objPHPExcel   = $objReader->load($fileWithPath);
            $sheet         = $objPHPExcel->getSheet(0);
            $highestRow    = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestColumn();

            for ($row = 4; $row <= $highestRow; $row++) {
                $req = (object) [];

                $nombre = $sheet->getCell('A'.$row)->getValue();
                if (empty($nombre)) {
                    break;
                }

                $req->nombre            = $this->repairEncoding($nombre);
                $req->apellido1         = $this->repairEncoding($sheet->getCell('B'.$row)->getValue());
                $req->apellido2         = $this->repairEncoding($sheet->getCell('C'.$row)->getValue());
                $req->tip_iden          = $sheet->getCell('D'.$row)->getValue();
                $req->nit               = $this->cleanIdentification($sheet->getCell('E'.$row)->getValue(), $req->tip_iden);
                $req->dv                = $sheet->getCell('F'.$row)->getValue();
                $req->fk_idpais         = $sheet->getCell('G'.$row)->getValue();
                $req->fk_iddepartamento = $sheet->getCell('H'.$row)->getValue();
                $req->fk_idmunicipio    = $sheet->getCell('I'.$row)->getValue();
                $req->codigopostal      = $sheet->getCell('J'.$row)->getValue();
                $req->telefono1         = $sheet->getCell('K'.$row)->getValue(); // Teléfono1
                $req->telefono2         = $sheet->getCell('L'.$row)->getValue(); // Teléfono2 (NO obligatorio)
                $req->celular           = $this->cleanCelular($sheet->getCell('M'.$row)->getValue()); // Celular
                $req->direccion         = $this->repairEncoding($sheet->getCell('N'.$row)->getValue());
                $req->vereda            = $this->repairEncoding($sheet->getCell('O'.$row)->getValue());
                $req->barrio            = $this->repairEncoding($sheet->getCell('P'.$row)->getValue());
                $req->ciudad            = $this->repairEncoding($sheet->getCell('Q'.$row)->getValue());
                $req->email             = $this->repairEncoding($sheet->getCell('R'.$row)->getValue()); // Correo1
                $req->email2            = $sheet->getCell('S'.$row)->getValue(); // Correo2 (NO obligatorio)
                $req->observaciones     = $this->repairEncoding($sheet->getCell('T'.$row)->getValue());
                $req->tipo_contacto     = $sheet->getCell('U'.$row)->getValue();
                $req->estrato           = $sheet->getCell('V'.$row)->getValue();

                $error = (object) [];

                if (! $req->tip_iden) {
                    $error->tip_iden = 'El campo Tipo de identificación es obligatorio';
                }
                if (! $req->celular && ! $req->telefono1) {
                    // $error->celular = 'Debe indicar un nro celular o de teléfono';
                }
                if (! $req->tipo_contacto) {
                    $error->tipo_contacto = 'El campo Tipo de Contacto es obligatorio';
                }

                if ($req->fk_idpais != '') {
                    if (DB::table('pais')->where('nombre', $req->fk_idpais)->count() == 0) {
                        $error->fk_idpais = 'El nombre del pais ingresado no se encuentra en nuestra base de datos';
                    }
                }

                if ($req->fk_iddepartamento != '') {
                    if (DB::table('departamentos')->where('nombre', 'like', '%'.$req->fk_iddepartamento.'%')->count() == 0) {
                        $error->fk_iddepartamento = 'El nombre del departamento ingresado no se encuentra en nuestra base de datos';
                    }
                }

                if ($req->fk_idmunicipio != '') {
                    if (DB::table('municipios')->where('nombre', 'like', '%'.$req->fk_idmunicipio.'%')->count() == 0) {
                        $error->fk_idmunicipio = 'El nombre del municipio ingresado no se encuentra en nuestra base de datos';
                    }
                }

                if (count((array) $error) > 0) {
                    $fila['error'] = 'FILA '.$row;
                    $error         = (array) $error;

                    Log::error('Error en importación de contactos', [
                        'fila'    => $row,
                        'errores' => $error,
                        'datos'   => (array) $req
                    ]);

                    array_unshift($error, $fila);
                    $result = (object) $error;

                    return back()->withErrors($result)->withInput();
                }
            }

            $tipo = 2;
            $tipo_identifi = 1;

            for ($row = 4; $row <= $highestRow; $row++) {
                $tipo = 2;
                $tipo_identifi = 1;

                $nombre = $sheet->getCell('A'.$row)->getValue();
                if (empty($nombre)) {
                    break;
                }

                $req                    = (object) [];
                $req->nombre            = $this->repairEncoding($nombre);
                $req->apellido1         = $this->repairEncoding($sheet->getCell('B'.$row)->getValue());
                $req->apellido2         = $this->repairEncoding($sheet->getCell('C'.$row)->getValue());
                $req->tip_iden          = $sheet->getCell('D'.$row)->getValue();
                $req->nit               = $this->cleanIdentification($sheet->getCell('E'.$row)->getValue(), $req->tip_iden);
                $req->dv                = $sheet->getCell('F'.$row)->getValue();
                $req->fk_idpais         = $sheet->getCell('G'.$row)->getValue();
                $req->fk_iddepartamento = $sheet->getCell('H'.$row)->getValue();
                $req->fk_idmunicipio    = $sheet->getCell('I'.$row)->getValue();
                $req->codigopostal      = $sheet->getCell('J'.$row)->getValue();
                $req->telefono1         = $sheet->getCell('K'.$row)->getValue();
                $req->telefono2         = $sheet->getCell('L'.$row)->getValue();
                $req->celular           = $this->cleanCelular($sheet->getCell('M'.$row)->getValue());
                $req->direccion         = $this->repairEncoding($sheet->getCell('N'.$row)->getValue());
                $req->vereda            = $this->repairEncoding($sheet->getCell('O'.$row)->getValue());
                $req->barrio            = $this->repairEncoding($sheet->getCell('P'.$row)->getValue());
                $req->ciudad            = $this->repairEncoding($sheet->getCell('Q'.$row)->getValue());
                $req->email             = $this->repairEncoding($sheet->getCell('R'.$row)->getValue());
                $req->email2            = $sheet->getCell('S'.$row)->getValue();
                $req->observaciones     = $this->repairEncoding($sheet->getCell('T'.$row)->getValue());
                $req->tipo_contacto     = $sheet->getCell('U'.$row)->getValue();
                $req->estrato           = $sheet->getCell('V'.$row)->getValue();

                // Tipo de contacto → numérico
                if (strtolower($req->tipo_contacto) == 'cliente') {
                    $tipo = 0;
                } elseif (strtolower($req->tipo_contacto) == 'proveedor') {
                    $tipo = 1;
                }
                $req->tipo_contacto = $tipo;

                // Conversión pais, dpto, municipio a IDs/códigos
                if ($req->fk_idpais != '') {
                    $req->fk_idpais = DB::table('pais')->where('nombre', $req->fk_idpais)->first()->codigo;
                }

                if ($req->fk_idmunicipio != '') {
                    $municipio = DB::table('municipios')->where('nombre', $req->fk_idmunicipio)->first();
                    if ($municipio) {
                        $req->fk_idmunicipio = $municipio->id;
                        if ($req->fk_iddepartamento == '') {
                            $req->fk_iddepartamento = $municipio->departamento_id;
                        }
                    }
                }

                if ($req->fk_iddepartamento != '' && !is_numeric($req->fk_iddepartamento)) {
                    $req->fk_iddepartamento = DB::table('departamentos')->where('nombre', $req->fk_iddepartamento)->first()->id;
                }

                // Tipo identificación
                $tipo_identifi_arr = TipoIdentificacion::where('identificacion', 'like', '%'.$req->tip_iden.'%')->first();
                if ($tipo_identifi_arr) {
                    $tipo_identifi = $tipo_identifi_arr->id;
                }
                $req->tip_iden = $tipo_identifi;

                // Buscar contacto existente por NIT
                $contacto = Contacto::where('nit', $req->nit)
                    ->where('empresa', Auth::user()->empresa)
                    ->where('status', 1)
                    ->first();

                if (! $contacto) {
                    $contacto          = new Contacto;
                    $contacto->empresa = Auth::user()->empresa;
                    $contacto->nit     = $req->nit;
                    $create++;
                } else {
                    $modf++;
                    $modificados[] = $req->nit;
                }
                // Asignación de campos
                $contacto->nombre        = ucwords(mb_strtolower($req->nombre));
                $contacto->apellido1     = ucwords(mb_strtolower($req->apellido1));
                $contacto->apellido2     = ucwords(mb_strtolower($req->apellido2));
                $contacto->tip_iden      = $req->tip_iden;
                $contacto->ciudad        = ucwords(mb_strtolower($req->ciudad));
                $contacto->direccion     = ucwords(mb_strtolower($req->direccion));
                $contacto->vereda        = ucwords(mb_strtolower($req->vereda));
                
                if($req->barrio){
                    $barrio = Barrios::where('nombre', $req->barrio)->first();
                    if (!$barrio) {
                        $barrio = new Barrios;
                        $barrio->nombre = ucwords(mb_strtolower($req->barrio));
                        $barrio->status = 1;
                        $barrio->created_by = Auth::user()->id;
                        $barrio->save();
                    }
                    $contacto->barrio_id = $barrio->id;
                }
                
                $contacto->barrio        = ucwords(mb_strtolower($req->barrio));
                $contacto->email         = mb_strtolower($req->email);
                $contacto->email2        = $req->email2 ? mb_strtolower($req->email2) : null;
                $contacto->telefono1     = $req->telefono1;
                $contacto->telefono2     = $req->telefono2 ?: null;
                $contacto->celular       = $req->celular;
                $contacto->tipo_contacto = $req->tipo_contacto;
                $contacto->observaciones = ucwords(mb_strtolower($req->observaciones));
                $contacto->fk_idpais     = $req->fk_idpais;
                $contacto->fk_iddepartamento = $req->fk_iddepartamento;
                $contacto->fk_idmunicipio    = $req->fk_idmunicipio;
                $contacto->cod_postal    = $req->codigopostal;
                $contacto->estrato       = $req->estrato;
                $contacto->feliz_cumpleanos = '';
                $contacto->tipo_persona  = $contacto->tip_iden == 6 ? 2 : 1;

                if ($req->dv) {
                    $contacto->dv = $req->dv;
                }

                $contacto->save();
            }

            $mensaje = 'SE HA COMPLETADO EXITOSAMENTE LA CARGA DE DATOS DEL SISTEMA';

            if ($create > 0) {
                $mensaje .= ' CREADOS: '.$create;
            }
            if ($modf > 0) {
                $mensaje .= ' MODIFICADOS: '.$modf;
                if (!empty($modificados)) {
                    $mensaje .= ' (Identificaciones: ' . implode(', ', $modificados) . ')';
                }
            }
            DB::commit();
            if ($modf > 0) {
                return redirect('empresa/contactos/clientes')->with('warning_persistent', $mensaje);
            }
            return redirect('empresa/contactos/clientes')->with('success', $mensaje);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error en proceso de importación de contactos', [
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'usuario' => Auth::user()->id ?? 'No definido'
            ]);

            $errorMessage = 'Error al procesar el archivo: ' . $e->getMessage();
            return redirect()->back()->with('error', $errorMessage)->withInput();
        }
    }


    /*
    * Retorna una archivo xml con las columnas especificas
    * para cargar
    */
    public function ejemplo(){
        $objPHPExcel = new PHPExcel();
        $tituloReporte = 'Reporte de Contactos de '.Auth::user()->empresa()->nombre;

        // 🔹 Nuevas columnas agregadas y renombradas
        $titulosColumnas = [
            'Nombres', 'Apellido1', 'Apellido2', 'Tipo de identificacion', 'Identificacion',
            'DV', 'Pais', 'Departamento', 'Municipio', 'Codigo postal',
            'Telefono1', 'Telefono2', // 🆕 aquí el cambio
            'Celular', 'Direccion', 'Corregimiento/Vereda', 'Barrio', 'Ciudad',
            'Correo Electronico1', 'Correo Electronico2', // 🆕 aquí el cambio
            'Observaciones', 'Tipo de Contacto', 'Estrato'
        ];

        $letras = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V'];

        $objPHPExcel->getProperties()
            ->setCreator('Sistema')
            ->setLastModifiedBy('Sistema')
            ->setTitle('Reporte Excel Contactos')
            ->setSubject('Reporte Excel Contactos')
            ->setDescription('Reporte de Contactos')
            ->setKeywords('reporte Contactos')
            ->setCategory('Reporte excel');

        // Encabezados
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('A1:V1');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $tituloReporte);
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('A2:V2');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A2', 'Fecha '.date('d-m-Y'));

        // Estilos
        $estiloTitulo = [
            'font' => ['bold' => true, 'size' => 12, 'name' => 'Times New Roman'],
            'alignment' => ['horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER]
        ];
        $objPHPExcel->getActiveSheet()->getStyle('A1:V3')->applyFromArray($estiloTitulo);

        // 🔹 Color del encabezado
        $estiloEncabezado = [
            'fill' => [
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => ['rgb' => substr(Auth::user()->empresa()->color, 1)],
            ],
            'font' => [
                'bold' => true,
                'size' => 12,
                'name' => 'Times New Roman',
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'alignment' => ['horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER],
        ];
        $objPHPExcel->getActiveSheet()->getStyle('A3:V3')->applyFromArray($estiloEncabezado);

        // 🔹 Imprimir títulos
        for ($i = 0; $i < count($titulosColumnas); $i++) {
            $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue($letras[$i].'3', utf8_decode($titulosColumnas[$i]));
        }

        $j = 4; // inicia contenido

        /* EJEMPLO PARA CUANDO ACTIVES EL LLENADO:
        foreach($contactos as $contacto){
            $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue('A'.$j, $contacto->nombre)
                ->setCellValue('B'.$j, $contacto->apellido1)
                ->setCellValue('C'.$j, $contacto->apellido2)
                ->setCellValue('D'.$j, $contacto->tip_iden())
                ->setCellValue('E'.$j, $contacto->nit)
                ->setCellValue('F'.$j, $contacto->dv)
                ->setCellValue('G'.$j, $contacto->pais()->nombre)
                ->setCellValue('H'.$j, $contacto->departamento()->nombre)
                ->setCellValue('I'.$j, $contacto->municipio()->nombre)
                ->setCellValue('J'.$j, $contacto->cod_postal)
                ->setCellValue('K'.$j, $contacto->telefono1) // Telefono1
                ->setCellValue('L'.$j, $contacto->telefono2) // Telefono2
                ->setCellValue('M'.$j, $contacto->celular)
                ->setCellValue('N'.$j, $contacto->direccion)
                ->setCellValue('O'.$j, $contacto->vereda)
                ->setCellValue('P'.$j, $contacto->barrio)
                ->setCellValue('Q'.$j, $contacto->ciudad)
                ->setCellValue('R'.$j, $contacto->email)       // Correo1
                ->setCellValue('S'.$j, $contacto->email2)      // Correo2 (si existe)
                ->setCellValue('T'.$j, $contacto->observaciones)
                ->setCellValue('U'.$j, $contacto->tipo_contacto())
                ->setCellValue('V'.$j, $contacto->estrato);
            $j++;
        }
        */

        // Bordes y tamaño
        $estiloCeldas = [
            'font' => ['size' => 12, 'name' => 'Times New Roman'],
            'borders' => ['allborders' => ['style' => PHPExcel_Style_Border::BORDER_THIN]],
            'alignment' => ['horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER],
        ];
        $objPHPExcel->getActiveSheet()->getStyle('A3:V'.$j)->applyFromArray($estiloCeldas);

        // Ajuste de ancho
        for ($i = 'A'; $i <= 'V'; $i++) {
            $objPHPExcel->getActiveSheet()->getColumnDimension($i)->setAutoSize(true);
        }

        // Nombre hoja
        $objPHPExcel->getActiveSheet()->setTitle('Reporte de Contactos');

        // Congelar encabezado
        $objPHPExcel->getActiveSheet()->freezePane('A4');

        // Salida
        header('Pragma: no-cache');
        header('Content-type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="Archivo_Importacion_Contactos.xlsx"');
        header('Cache-Control: max-age=0');

        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');
        exit;
    }

    public function contactoModal()
    {
        $identificaciones = TipoIdentificacion::all();
        $vendedores = Vendedor::where('empresa', Auth::user()->empresa)->where('estado', 1)->get();
        $listas = ListaPrecios::where('empresa', Auth::user()->empresa)->where('status', 1)->get();
        $tipos_empresa = TipoEmpresa::where('empresa', Auth::user()->empresa)->get();
        $prefijos = DB::table('prefijos_telefonicos')->get();
        $paises = DB::table('pais')->get();
        $departamentos = DB::table('departamentos')->get();

        return view('contactos.modal.modal')->with(compact('identificaciones', 'paises', 'departamentos', 'tipos_empresa', 'prefijos', 'vendedores', 'listas'));
    }

    public function searchMunicipality(Request $request)
    {
        $municipios = DB::table('municipios')->where('departamento_id', $request->departamento_id)->get();

        return response()->json($municipios);
    }

    public function getDataClient($id)
    {
        $identificaciones = TipoIdentificacion::all();
        $contacto = Contacto::where('id', $id)->where('empresa', Auth::user()->empresa)->first();
        $paises = DB::table('pais')->get();
        $departamentos = DB::table('departamentos')->get();

        return view('contactos.modal.updatedatos', compact('contacto', 'paises', 'departamentos', 'identificaciones'));
    }

    public function updatedirection(Request $request)
    {
        $contacto = Contacto::where('id', $request->cliente_id)->where('empresa', Auth::user()->empresa)->first();
        if ($request->cod_postal != null) {
            $contacto->cod_postal = $request->cod_postal;
        }
        if ($request->pais != null) {
            $contacto->fk_idpais = $request->pais;
        }
        if ($request->departamento != null) {
            $contacto->fk_iddepartamento = $request->departamento;
        }
        if ($request->municipio != null) {
            $contacto->fk_idmunicipio = $request->municipio;
        }
        if ($request->direccion != null) {
            $contacto->direccion = $request->direccion;
        }
        if ($request->nit != null) {
            $contacto->nit = $request->nit;
        }
        if ($request->dv != null) {
            $contacto->dv = $request->dv;
        }
        if ($contacto->email == null) {
            $contacto->email = $request->email;
        }
        if ($contacto->tip_iden != 6) { //-- Si es diferente del nit entra
            if ($request->responsable == '') {
                $contacto->responsableiva = 2; //-- No responsable de iva
            }

            $contacto->tipo_persona = 1; //-- Persona natural

        } else {
            $contacto->tipo_persona = 2; //-- Persona juridica
            if ($request->responsable != null) {
                $contacto->responsableiva = $request->responsable;
            }
        }
        $contacto->save();

        return response()->json($contacto);
    }

    public function modalGuiaEnvio($facturaid, $clienteid)
    {
        $prefijos = DB::table('prefijos_telefonicos')->get();
        $identificaciones = TipoIdentificacion::all();
        $paises = DB::table('pais')->get();
        $departamentos = DB::table('departamentos')->get();
        $transportadoras = DB::table('transportadoras')->get();

        //1 data de guia_envio_factura, 2= data de guia_envio_contacto
        $tipo = 0;

        if (DB::table('guia_envio_factura')->where('factura_id', $facturaid)->count() > 0) {
            $guia_envio = DB::table('guia_envio_factura')->where('factura_id', $facturaid)->first();
            $tipo = 1;
        } else {
            if (DB::table('guia_envio_contacto')->where('contacto_id', $clienteid)->count() > 0) {
                $guia_envio = DB::table('guia_envio_contacto')->where('contacto_id', $clienteid)->first();
                $tipo = 2;
            } else {
                $guia_envio = null;
            }
        }

        return view('contactos.modal.guiaenvio', compact('prefijos', 'identificaciones', 'paises', 'departamentos', 'transportadoras', 'guia_envio', 'facturaid', 'tipo'));
    }

    public function desasociar($id)
    {
        DB::table('usuarios_app')->where('id_cliente', $id)->delete();

        return redirect('empresa/contactos/clientes')->with('success', 'Cliente Desasociado de la APP');
    }

    public function eliminarAdjunto($id, $archivo)
    {
        $contacto = Contacto::where('id', $id)->where('empresa', Auth::user()->empresa)->first();
        if ($contacto) {
            switch ($archivo) {
                case 'imgA':
                    $contacto->imgA = null;
                    break;
                case 'imgB':
                    $contacto->imgB = null;
                    break;
                case 'imgC':
                    $contacto->imgC = null;
                    break;
                case 'imgD':
                    $contacto->imgD = null;
                    break;
                default:
                    break;
            }
            $contacto->save();

            return response()->json([
                'success' => true,
                'type' => 'success',
                'title' => 'Archivo Adjunto Eliminado',
                'text' => '',
            ]);
        }

        return response()->json([
            'success' => false,
            'type' => 'error',
            'title' => 'Archivo no eliminado',
            'text' => 'Inténtelo Nuevamente',
        ]);
    }

    public function updateFechaIsp($id, Request $request)
    {

        if (! $request->fecha_isp) {
            return false;
        }

        $contacto = Contacto::where('id', $id)->where('empresa', auth()->user()->empresa)->first();
        $contacto->fecha_isp = Carbon::parse($request->fecha_isp)->format('Y-m-d');
        $contacto->update();

        return response()->json([
            'succes' => true,
            'fecha_isp' => date('d-m-Y', strtotime($contacto->fecha_isp)),
        ]);
    }

    public function editSaldo($contactoId)
    {
        $contacto = Contacto::find($contactoId);
        if ($contacto) {
            return response()->json($contacto);
        }
    }

    public function storeSaldo(Request $request)
    {
        $contacto = Contacto::find($request->contactoId);

        //Programacion para guardar registro de quien hizo el cambio en tabla de historial.
        DB::table('log_saldos')->insert([
            'id_contacto' => $contacto->id,
            'accion' => 'modificó el saldo anterior: '.Funcion::Parsear($contacto->saldo_favor).' al actual: '.$request->saldo_favor,
            'created_by' => Auth::user()->id,
            'fecha' => Carbon::now()->format('Y-m-d'),
            'created_at' => Carbon::now(),
        ]);

        $contacto->saldo_favor = $request->saldo_favor;
        $contacto->save();

        return response()->json($contacto);
    }

    public function historialSaldo($contactoId)
    {
        $historial = DB::table('log_saldos')->join('usuarios as u', 'u.id', 'log_saldos.created_by')
            ->select('log_saldos.*', 'u.nombres as nombre')->where('id_contacto', $contactoId)->get();

        return response()->json($historial);
    }

    //funcion que redirecciona
    public function cambiares($id)
    {
        //##################################################################
        $count = DB::table('crm')->where('cliente', $id)->count();
        if ($count != 0) {
            return redirect('/empresa/crm');
        } else {
            $fec = Carbon::create(date('Y'), date('m'), date('d'))->format('Y-m-d');
            $t = DB::table('factura')
                ->where('cliente', $id)
                ->selectRaw('MAX(pago_oportuno) AS fechamax')->get();
            //sacar la factura el numero una vez obtenido la fecha maxima
            $numfac = DB::table('factura')
                ->where('pago_oportuno', $t[0]->fechamax)
                ->where('cliente', $id)
                ->select('id as idfac')->get();
            //##################################################
            //validar la fecha para que deje guardar en el crm
            if ($fec > $t[0]->fechamax) {
                //si la factura esta vencida entonces verificar el cmr y sino registrar
                $datos = DB::table('contactos')->where('id', $id)->get();
                $Reg = new CRM();
                $Reg->cliente = $datos[0]->id;
                $Reg->estado = 0;
                $Reg->factura = $numfac[0]->idfac;
                $Reg->notificacion = 0;
                $Reg->grupo_corte = 1;
                $Reg->save();

                return redirect('/empresa/crm');
            } else {
                Session::flash('novence', 'Esta factura aun no vence');

                return back();
            }

        }

    }

    //metodo para añadir mas campos al formulario de contacto
    public function indexcampos(){

         $modoLectura = auth()->user()->modo_lectura();
         $this->getAllPermissions(Auth::user()->id);
         $identificaciones = TipoIdentificacion::all();
         $paises = DB::table('pais')->where('codigo', 'CO')->get();
         $departamentos = DB::table('departamentos')->get();
         $oficinas = (Auth::user()->oficina && Auth::user()->empresa()->oficina) ? Oficina::where('id', Auth::user()->oficina)->get() : Oficina::where('empresa', Auth::user()->empresa)->where('status', 1)->get();

         view()->share(['icon' => '', 'title' => 'Añadir campos a Contacto', 'subseccion' => 'clientes', 'middel' => true]);

         return view('contactos.newcamposcreatep')->with(compact('identificaciones', 'paises', 'departamentos', 'oficinas'));
       }

    //    metodo para ya crear los campos en base de datos
    public function newcampos(Request $request){
        dd($request);
    }

    public function clientes_contratos(Request $request, $id = null){
        $id = $id ?: $request->id;
        
        // Buscamos los contratos del cliente filtrando por la empresa del usuario autenticado
        $contratos = Contrato::where('client_id', $id)
            ->where('empresa', Auth::user()->empresa)
            ->get();

        // Fallback en caso de que Eloquent tenga algún problema con scopes o relaciones
        if ($contratos->count() == 0) {
            $contratos = DB::table('contracts')
                ->where('client_id', $id)
                ->where('empresa', Auth::user()->empresa)
                ->get();
        }

        return response()->json($contratos);
    }

    public function cambiarEtiqueta($etiqueta, $contacto){

        $contacto =  Contacto::where('id', $contacto)->where('empresa', Auth::user()->empresa)->first();

        if($etiqueta == 0){
            $contacto->etiqueta_id = null;
        }else{
            $contacto->etiqueta_id = $etiqueta;
        }

        $contacto->update();
        return $contacto->etiqueta;
    }

    //Genera un registro en el crm por el cliente escogido
    public function createCrm($id){

        $contacto = Contacto::Find($id);
        $ultimaFactura = Factura::where('estatus','<>',2)->where('cliente',$contacto->id)->orderBy('id','desc')->first();
        $contrato = Contrato::where('client_id',$contacto->id)->first();

        if($ultimaFactura && $contrato){

            $crm = CRM::where('cliente', $contacto->id)->whereIn('estado', [0, 3])->delete();
            $crm = new CRM();
            $crm->cliente = $contacto->id;
            $crm->factura = $ultimaFactura->id;
            $crm->estado = 0;
            $crm->servidor = isset($contrato->server_configuration_id) ? $contrato->server_configuration_id : '';
            $crm->grupo_corte = isset($contrato->grupo_corte) ? $contrato->grupo_corte : '';
            $crm->save();

        }else{
            return redirect()->back()->with('danger','El contacto no tiene contrato o facturas asociadas.');
        }

        return redirect()->back()->with('success','Se ha generado un registro crm correctamente.');

    }

    public function importarSaldos()
    {
        $this->getAllPermissions(Auth::user()->id);
        view()->share(['title' => 'Importar Saldos a Favor', 'subseccion' => 'clientes']);
        return view('contactos.importar_saldos');
    }

    public function ejemploSaldos()
    {
        $objPHPExcel = new PHPExcel();
        $empresa = Auth::user()->empresa();
        $tituloReporte = 'PLANTILLA DE IMPORTACIÓN: SALDOS A FAVOR';
        $subTitulo = 'Empresa: ' . $empresa->nombre;

        // Propiedades del documento
        $objPHPExcel->getProperties()
            ->setCreator('NetworkSoft')
            ->setTitle('Plantilla Importación Saldos');

        // Configuración de la hoja
        $sheet = $objPHPExcel->setActiveSheetIndex(0);
        $sheet->setTitle('Saldos a Favor');

        // Estilos
        $estiloTitulo = [
            'font' => ['bold' => true, 'size' => 12, 'name' => 'Arial'],
            'alignment' => ['horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER]
        ];

        $estiloSubtitulo = [
            'font' => ['bold' => false, 'size' => 11, 'name' => 'Arial'],
            'alignment' => ['horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER]
        ];

        $estiloEncabezado = [
            'fill' => [
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => ['rgb' => substr($empresa->color, 1) ?: '007bff'],
            ],
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'alignment' => [
                'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allborders' => ['style' => PHPExcel_Style_Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
            ],
        ];

        $estiloDatos = [
            'font' => ['size' => 11, 'name' => 'Arial'],
            'alignment' => ['horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER],
            'borders' => [
                'allborders' => ['style' => PHPExcel_Style_Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']],
            ],
        ];

        // Títulos
        $sheet->mergeCells('A1:B1');
        $sheet->setCellValue('A1', $tituloReporte);
        $sheet->getStyle('A1')->applyFromArray($estiloTitulo);

        $sheet->mergeCells('A2:B2');
        $sheet->setCellValue('A2', $subTitulo . ' - Fecha: ' . date('d-m-Y'));
        $sheet->getStyle('A2')->applyFromArray($estiloSubtitulo);

        // Encabezados de Columnas
        $sheet->setCellValue('A3', 'Nro Identificación');
        $sheet->setCellValue('B3', 'Saldo a Favor');
        $sheet->getStyle('A3:B3')->applyFromArray($estiloEncabezado);
        $sheet->getRowDimension('3')->setRowHeight(25);

        // Ejemplo de datos (Fila 4)
        $sheet->setCellValue('A4', '123456789');
        $sheet->setCellValue('B4', '50000');
        $sheet->getStyle('A4:B4')->applyFromArray($estiloDatos);

        // Ajuste de columnas fija para legibilidad
        $sheet->getColumnDimension('A')->setWidth(35);
        $sheet->getColumnDimension('B')->setWidth(35);

        // Bloqueo de paneles (congelar encabezado)
        $sheet->freezePane('A4');

        header('Pragma: no-cache');
        header('Content-type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="Plantilla_Importacion_Saldos.xlsx"');
        header('Cache-Control: max-age=0');

        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');
        exit;
    }

    public function cargandoSaldos(Request $request)
    {
        try {
            if (!$request->hasFile('archivo')) {
                return back()->with('error', 'Debe seleccionar un archivo para importar');
            }

            $imagen = $request->file('archivo');
            $nombre_imagen = 'saldos_' . time() . '.' . $imagen->getClientOriginalExtension();
            $path = public_path() . '/images/Empresas/Empresa' . Auth::user()->empresa;
            $imagen->move($path, $nombre_imagen);

            ini_set('max_execution_time', 500);

            $fileWithPath = $path . '/' . $nombre_imagen;
            $inputFileType = PHPExcel_IOFactory::identify($fileWithPath);
            $objReader = PHPExcel_IOFactory::createReader($inputFileType);
            $objPHPExcel = $objReader->load($fileWithPath);
            $sheet = $objPHPExcel->getSheet(0);
            $highestRow = $sheet->getHighestRow();

            $procesados = 0;
            $errores = [];

            DB::beginTransaction();

            for ($row = 4; $row <= $highestRow; $row++) {
                $nit = $sheet->getCell('A' . $row)->getValue();
                $nuevo_saldo = $sheet->getCell('B' . $row)->getValue();

                if (empty($nit)) continue;

                $contacto = Contacto::where('nit', $nit)
                    ->where('empresa', Auth::user()->empresa)
                    ->first();

                if ($contacto) {
                    $saldo_anterior = $contacto->saldo_favor;
                    
                    // Registro en log_saldos
                    DB::table('log_saldos')->insert([
                        'id_contacto' => $contacto->id,
                        'accion' => 'modificó el saldo anterior: ' . Funcion::Parsear($saldo_anterior) . ' al actual: ' . $nuevo_saldo,
                        'created_by' => Auth::user()->id,
                        'fecha' => Carbon::now()->format('Y-m-d'),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);

                    $contacto->saldo_favor = $nuevo_saldo;
                    $contacto->save();
                    $procesados++;
                } else {
                    $errores[] = "Fila $row: Contacto con NIT $nit no encontrado.";
                }
            }

            DB::commit();
            
            // Eliminar archivo temporal
            if (file_exists($fileWithPath)) unlink($fileWithPath);

            if ($procesados > 0) {
                $msg = "Se han actualizado $procesados saldos correctamente.";
                if (count($errores) > 0) {
                    return redirect('empresa/configuracion')->with('success', $msg)->withErrors($errores);
                }
                return redirect('empresa/configuracion')->with('success', $msg);
            } else {
                return back()->withErrors($errores)->with('error', 'No se procesó ningún registro.');
            }

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error en importación de saldos: ' . $e->getMessage());
            return back()->with('error', 'Ocurrió un error al procesar el archivo: ' . $e->getMessage());
        }
    }
}
