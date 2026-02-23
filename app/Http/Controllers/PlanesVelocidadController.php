<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Empresa;
use Carbon\Carbon;
use DB;
use App\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Validator;
use Illuminate\Validation\Rule;
use Auth;
use Session;
use App\Rules\guion;
use App\Funcion;
use App\ProductoCuenta;
use App\Mikrotik;
use App\PlanesVelocidad;
use App\Puc;
use App\Retencion;

use App\Impuesto;
use App\Model\Inventario\Inventario;
use App\Contrato;

include_once(app_path() . '/../public/routeros_api.class.php');
include_once(app_path() . '/../public/api_mt_include2.php');
include_once(app_path() . '/../public/PHPExcel/Classes/PHPExcel.php');

use routeros_api;
use RouterosAPI;
use StdClass;
use App\Campos;
use PHPExcel;
use PHPExcel_IOFactory;
use PHPExcel_Style_Alignment;
use PHPExcel_Style_Fill;
use PHPExcel_Style_Border;
use PHPExcel_Cell_DataValidation;

class PlanesVelocidadController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        view()->share(['seccion' => 'mikrotik', 'subseccion' => 'gestion_planes', 'title' => 'Planes de Velocidad', 'icon' => 'fas fa-server']);
    }

    public function getPlanesPorMikrotik($mikrotik_id)
    {
        try {
            $planes = PlanesVelocidad::where('status', 1)
                ->where('empresa', Auth::user()->empresa)
                ->where('mikrotik', $mikrotik_id)
                ->get();

            $mikrotikServer = Mikrotik::find($mikrotik_id);

            // Verificar si la empresa tiene consultas_mk = 0
            $empresa = Empresa::find(Auth::user()->empresa);
            $consultasMk = $empresa ? $empresa->consultas_mk : 1;

            // Si consultas_mk = 0, no hacer consultas a mikrotik y retornar profile vacío
            $profile = [];
            $connectionError = false;

            // Si consultas_mk = 1, hacer consulta a mikrotik (comportamiento original)
            if ($consultasMk == 1 && $mikrotikServer) {
                $API = new RouterosAPI();
                $API->port = $mikrotikServer->puerto_api;
                
                if ($API->connect($mikrotikServer->ip, $mikrotikServer->usuario, $mikrotikServer->clave)) {
                    $API->write('/ppp/profile/getall');
                    $READ = $API->read(false);
                    $profile = $API->parseResponse($READ);
                    $API->disconnect();
                } else {
                    $connectionError = true;
                }
            }

            return response()->json([
                'success' => true,
                'planes' => $planes,
                'mikrotik' => $mikrotikServer,
                'profile' => $profile,
                'connection_error' => $connectionError
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los planes: ' . $e->getMessage()
            ], 500);
        }
    }

    public function index(Request $request)
    {
        $this->getAllPermissions(Auth::user()->id);

        $tabla = Campos::join('campos_usuarios', 'campos_usuarios.id_campo', '=', 'campos.id')->where('campos_usuarios.id_modulo', 10)->where('campos_usuarios.id_usuario', Auth::user()->id)->where('campos_usuarios.estado', 1)->orderBy('campos_usuarios.orden', 'ASC')->get();
        $mikrotiks = Mikrotik::where('empresa', Auth::user()->empresa)->get();
        $planes_velocidad = PlanesVelocidad::all();
        return view('planesvelocidad.index')->with(compact('mikrotiks', 'tabla', 'planes_velocidad'));
    }

    public function planes(Request $request)
    {
        $this->getAllPermissions(auth()->user()->id);
        $modoLectura = auth()->user()->modo_lectura();
        $moneda = auth()->user()->empresa()->moneda;
        $planes = PlanesVelocidad::query();

        if ($request->filtro == true) {
            if ($request->nombre) {
                $planes->where(function ($query) use ($request) {
                    $query->orWhere('name', 'like', "%{$request->nombre}%");
                });
            }
            if ($request->name) {
                $planes->where(function ($query) use ($request) {
                    $query->orWhere('name', 'like', "%{$request->name}%");
                });
            }
            if ($request->price) {
                $planes->where(function ($query) use ($request) {
                    $query->orWhere('price', 'like', "%{$request->price}%");
                });
            }
            if ($request->download) {
                $planes->where(function ($query) use ($request) {
                    $query->orWhere('download', 'like', "%{$request->download}%");
                });
            }
            if ($request->upload) {
                $planes->where(function ($query) use ($request) {
                    $query->orWhere('upload', 'like', "%{$request->upload}%");
                });
            }
            if ($request->type) {
                if ($request->type == 'A') {
                    $type = 0;
                } else {
                    $type = $request->type;
                }
                $planes->where(function ($query) use ($type) {
                    $query->orWhere('type', $type);
                });
            }
            if ($request->mikrotik_s) {
                $planes->where(function ($query) use ($request) {
                    $query->orWhere('mikrotik', $request->mikrotik_s);
                });
                $planes->where(function ($query) use ($request) {
                    $query->orWhere('name', 'like', "%{$request->name}%");
                });
            }
            if ($request->status) {
                if ($request->status == 'A') {
                    $status = 0;
                } else {
                    $status = $request->status;
                }
                $planes->where(function ($query) use ($status) {
                    $query->orWhere('status', $status);
                });
            }
            if ($request->tipo_plan) {
                $planes->where(function ($query) use ($request) {
                    $query->orWhere('tipo_plan', $request->tipo_plan);
                });
            }
        }

        $planes->where('planes_velocidad.empresa', auth()->user()->empresa);

        return datatables()->eloquent($planes)
            ->editColumn('name', function (PlanesVelocidad $plan) {
                return "<div class='elipsis-short-300'><a href=" . route('planes-velocidad.show', $plan->id) . ">{$plan->name}</a></div>";
            })
            ->editColumn('price', function (PlanesVelocidad $plan) use ($moneda) {
                return "{$moneda} {$plan->parsear($plan->price)}";
            })
            ->editColumn('download', function (PlanesVelocidad $plan) {
                return $plan->download;
            })
            ->editColumn('upload', function (PlanesVelocidad $plan) {
                return $plan->upload;
            })
            ->editColumn('type', function (PlanesVelocidad $plan) {
                return '<span class="text-' . $plan->type(true) . '">' . $plan->type() . '</span>';
            })
            ->editColumn('mikrotik', function (PlanesVelocidad $plan) {
                $html = '';
                if ($plan->mikrotik() !== null) {
                    $html .= "<a href=" . route('mikrotik.show', $plan->mikrotik()->id) . " target='_blank'>{$plan->mikrotik()->nombre}</div></a>";
                }

                // Similarmente, verifica si mikrotik1 no es nulo antes de intentar acceder a sus propiedades
                // if ($plan->mikrotik1() !== null) {
                //     $html .= "; <a href=" . route('mikrotik.show', $plan->mikrotik1()->id) . " target='_blank'>{$plan->mikrotik1()->nombre}</div></a>";
                // }

                // if ($plan->mikrotik2() !== null) {
                //     $html .= "; <a href=" . route('mikrotik.show', $plan->mikrotik2()->id) . " target='_blank'>{$plan->mikrotik2()->nombre}</div></a>";
                // }

                // if ($plan->mikrotik3() !== null) {
                //     $html .= "; <a href=" . route('mikrotik.show', $plan->mikrotik3()->id) . " target='_blank'>{$plan->mikrotik3()->nombre}</div></a>";
                // }

                // if ($plan->mikrotik4() !== null) {
                //     $html .= "; <a href=" . route('mikrotik.show', $plan->mikrotik4()->id) . " target='_blank'>{$plan->mikrotik4()->nombre}</div></a>";
                // }
                //  return "<a href=" . route('mikrotik.show', $plan->mikrotik()->id) . " target='_blank'>{$plan->mikrotik()->nombre}</div></a>, <a href=" . route('mikrotik.show', $plan->mikrotik1()->id) . " target='_blank'>{$plan->mikrotik1()->nombre}</div></a>";
                return $html;
                return;
            })
            ->editColumn('status', function (PlanesVelocidad $plan) {
                return   '<span class="text-' . $plan->status(true) . '">' . $plan->status() . '</span>';
            })
            ->editColumn('tipo_plan', function (PlanesVelocidad $plan) {
                return $plan->tipo();
            })
            ->editColumn('nro_clientes', function (PlanesVelocidad $plan) {
                return '<span class="badge badge-success">' . $plan->uso_state('enabled') . '</span> Habilitados<br>
                            <span class="badge badge-danger mt-1">' . $plan->uso_state('disabled') . '</span> Deshabilitados';
            })
            ->addColumn('acciones', $modoLectura ?  "" : "planesvelocidad.acciones")
            ->rawColumns(['acciones', 'name', 'status', 'type', 'mikrotik', 'nro_clientes'])
            ->toJson();
    }

    public function create()
    {
        $this->getAllPermissions(Auth::user()->id);
        view()->share(['title' => 'Nuevo Plan', 'icon' => 'fas fa-server']);
        $mikrotiks = Mikrotik::where('empresa', Auth::user()->empresa)->get();
        $empresa = Auth::user()->empresa;

        //Tomar las categorias del puc que no son transaccionables.
        $cuentas = Puc::where('empresa', $empresa)
            ->where('estatus', 1)
            ->whereRaw('length(codigo) >= 6')
            ->get();
        $autoRetenciones = Retencion::where('empresa', Auth::user()->empresa)->where('estado', 1)->where('modulo', 2)->get();
        $type = '';

        return view('planesvelocidad.create')->with(compact('mikrotiks', 'cuentas', 'autoRetenciones', 'type'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|max:200',
            'price' => 'required|max:200',
            'upload' => 'required|max:200',
            'download' => 'required|max:200',
            'type' => 'required|max:200',
            'tipo_plan' => 'required|max:200',
        ]);

        if ($request->mikrotik == null) {
            return back()->with('error', 'Debe asociar la mikrotik');
        }
        $num_microtik = count($request->mikrotik);
        for ($i = 0; $i < count($request->mikrotik); $i++) {
            $inventario                = new Inventario;
            $inventario->empresa       = Auth::user()->empresa;
            $inventario->producto      = strtoupper($request->name);
            $inventario->ref           = strtoupper($request->ref);
            $inventario->precio        = $this->precision($request->price);

            $inventario->id_impuesto   = ($request->tipo_plan == 2) ? 1 : 2;
            $inventario->impuesto      = ($request->tipo_plan == 2) ? 19 : 0;

            $inventario->tipo_producto = 2;
            $inventario->unidad        = 1;
            $inventario->nro           = 0;
            $inventario->categoria     = 116;
            $inventario->lista         = 0;
            $inventario->type_autoretencion = isset($request->tipo_autoretencion) ? $request->tipo_autoretencion : null;
            $inventario->type          = 'PLAN';
            $inventario->save();

            $plan = new PlanesVelocidad;
            $plan->mikrotik = $request->mikrotik[$i];
            $plan->name = $request->name;
            $plan->price = $request->price;
            $plan->upload = $request->upload . '' . $request->inicial_upload;
            $plan->download = $request->download . '' . $request->inicial_download;
            $plan->type = $request->type;
            $plan->address_list = $request->address_list;
            $plan->created_by = Auth::user()->id;
            $plan->tipo_plan = $request->tipo_plan;
            $plan->burst_limit_subida = $request->burst_limit_subida . '' . $request->inicial_burst_limit_subida;
            $plan->burst_limit_bajada = $request->burst_limit_bajada . '' . $request->inicial_burst_limit_bajada;
            $plan->burst_threshold_subida = $request->burst_threshold_subida . '' . $request->inicial_burst_threshold_subida;
            $plan->burst_threshold_bajada = $request->burst_threshold_bajada . '' . $request->inicial_burst_threshold_bajada;
            $plan->burst_time_subida = $request->burst_time_subida;
            $plan->burst_time_bajada = $request->burst_time_bajada;
            $plan->queue_type_subida = $request->queue_type_subida;
            $plan->queue_type_bajada = $request->queue_type_bajada;
            $plan->parenta = $request->parenta;
            $plan->prioridad = $request->prioridad;
            $plan->limit_at_subida = $request->limit_at_subida . '' . $request->inicial_limit_at_subida;
            $plan->limit_at_bajada = $request->limit_at_bajada . '' . $request->inicial_limit_at_bajada;
            $plan->item = $inventario->id;
            $plan->empresa = Auth::user()->empresa;
            $plan->dhcp_server = $request->dhcp_server;
            $plan->save();


            //Desarrollo pendiente de cuentas por producto
            if ($request->cuentacontable) {
                foreach ($request->cuentacontable as $key => $value) {
                    DB::table('producto_cuentas')->insert([
                        'cuenta_id' => $value,
                        'inventario_id' => $inventario->id
                    ]);
                }
            }

            //introduccion de cuentas de productos y servicios (inv, costo, venta y dev).
            if (isset($request->inventario) && $request->inventario != 0) {
                $pr = new ProductoCuenta;
                $pr->cuenta_id = $request->inventario;
                $pr->inventario_id = $inventario->id;
                $pr->tipo = 1;
                $pr->save();
            }

            if (isset($request->costo) && $request->costo != 0) {
                $pr = new ProductoCuenta;
                $pr->cuenta_id = $request->costo;
                $pr->inventario_id = $inventario->id;
                $pr->tipo = 2;
                $pr->save();
            }

            if (isset($request->venta) && $request->venta != 0) {
                $pr = new ProductoCuenta;
                $pr->cuenta_id = $request->venta;
                $pr->inventario_id = $inventario->id;
                $pr->tipo = 3;
                $pr->save();
            }

            if (isset($request->devolucion) && $request->devolucion != 0) {
                $pr = new ProductoCuenta;
                $pr->cuenta_id = $request->devolucion;
                $pr->inventario_id = $inventario->id;
                $pr->tipo = 4;
                $pr->save();
            }

            if (isset($request->autoretencion)) {
                $pr = new ProductoCuenta;
                $pr->cuenta_id = $request->autoretencion;
                $pr->inventario_id = $inventario->id;
                $pr->tipo = 5;
                $pr->save();
            }
        }

        $mensaje = 'SE HA CREADO SATISFACTORIAMENTE EL PLAN';
        return redirect('empresa/planes-velocidad')->with('success', $mensaje)->with('mikrotik_id', $plan->id);
    }

    public function storeBack(Request $request)
    {
        if (!$request->mikrotik) {
            $arrayPost['status'] = 'error';
            $arrayPost['mensaje'] = 'Mikrotik Asociada no ha sido seleccionada';
            echo json_encode($arrayPost);
            exit;
        }
        if (!$request->name) {
            $arrayPost['status'] = 'error';
            $arrayPost['mensaje'] = 'El nombre del plan no ha sido definido';
            echo json_encode($arrayPost);
            exit;
        }
        if (!$request->price) {
            $arrayPost['status'] = 'error';
            $arrayPost['mensaje'] = 'El precio del plan no ha sido definido';
            echo json_encode($arrayPost);
            exit;
        }
        if (!$request->download) {
            $arrayPost['status'] = 'error';
            $arrayPost['mensaje'] = 'La Vel. de Descarga no ha sido definida';
            echo json_encode($arrayPost);
            exit;
        }
        if (!$request->upload) {
            $arrayPost['status'] = 'error';
            $arrayPost['mensaje'] = 'La Vel. de Subida no ha sido definida';
            echo json_encode($arrayPost);
            exit;
        }
        if (!$request->tipo_plan) {
            $arrayPost['status'] = 'error';
            $arrayPost['mensaje'] = 'El tipo de plan no ha sido definido';
            echo json_encode($arrayPost);
            exit;
        }

        for ($i = 0; $i < count($request->mikrotik); $i++) {
            $inventario                   = new Inventario;
            $inventario->empresa          = Auth::user()->empresa;
            $inventario->producto         = strtoupper($request->name);
            $inventario->ref              = strtoupper($request->name);
            $inventario->precio           = $this->precision($request->price);

            $inventario->id_impuesto      = ($request->tipo_plan == 2) ? 1 : 2;
            $inventario->impuesto         = ($request->tipo_plan == 2) ? 19 : 0;

            $inventario->tipo_producto    = 2;
            $inventario->unidad           = 1;
            $inventario->nro              = 0;
            $inventario->categoria        = 116;
            $inventario->lista            = 0;
            $inventario->type             = 'PLAN';
            $inventario->save();

            $plan                         = new PlanesVelocidad;
            $plan->mikrotik               = $request->mikrotik[$i];
            $plan->name                   = $request->name;
            $plan->price                  = $request->price;
            $plan->upload                 = $request->upload . '' . $request->inicial_upload;
            $plan->download               = $request->download . '' . $request->inicial_download;
            $plan->type                   = $request->type;
            $plan->address_list           = $request->address_list;
            $plan->created_by             = Auth::user()->id;
            $plan->tipo_plan              = $request->tipo_plan;
            $plan->burst_limit_subida     = $request->burst_limit_subida . '' . $request->inicial_burst_limit_subida;
            $plan->burst_limit_bajada     = $request->burst_limit_bajada . '' . $request->inicial_burst_limit_bajada;
            $plan->burst_threshold_subida = $request->burst_threshold_subida . '' . $request->inicial_burst_threshold_subida;
            $plan->burst_threshold_bajada = $request->burst_threshold_bajada . '' . $request->inicial_burst_threshold_bajada;
            $plan->burst_time_subida      = $request->burst_time_subida;
            $plan->burst_time_bajada      = $request->burst_time_bajada;
            $plan->queue_type_subida      = $request->queue_type_subida;
            $plan->queue_type_bajada      = $request->queue_type_bajada;
            $plan->parenta                = $request->parenta;
            $plan->prioridad              = $request->prioridad;
            $plan->limit_at_subida        = $request->limit_at_subida . '' . $request->inicial_limit_at_subida;
            $plan->limit_at_bajada        = $request->limit_at_bajada . '' . $request->inicial_limit_at_bajada;
            $plan->item                   = $inventario->id;
            $plan->empresa                = Auth::user()->empresa;
            $plan->dhcp_server            = $request->dhcp_server;
            $plan->save();
        }

        if ($plan) {
            $arrayPost['success'] = true;
            $arrayPost['id']      = PlanesVelocidad::all()->last()->id;
            $arrayPost['name']    = PlanesVelocidad::all()->last()->name;
            $arrayPost['type']    = (PlanesVelocidad::all()->last()->type == 0) ? 'Plan Queue Simple' : 'Plan PCQ';
            echo json_encode($arrayPost);
            exit;
        }
    }

    public function edit($id)
    {
        $this->getAllPermissions(Auth::user()->id);
        $empresa = Auth::user()->empresa;
        $plan = PlanesVelocidad::where('id', $id)->where('empresa', Auth::user()->empresa)->first();
        $cuentas = Puc::where('empresa', $empresa)
            ->where('estatus', 1)
            ->whereRaw('length(codigo) >= 6')
            ->get();
        $autoRetenciones = Retencion::where('empresa', Auth::user()->empresa)->where('estado', 1)->where('modulo', 2)->get();

        if ($plan) {
            $plan->ref = Inventario::Find($plan->item)->ref;
            $inventario = Inventario::find($plan->item);
            $cuentasInventario = $inventario->cuentas();
            view()->share(['title' => 'Modificar Plan', 'icon' => 'fas fa-server']);
            $mikrotiks = Mikrotik::where('empresa', Auth::user()->empresa)->get();
            return view('planesvelocidad.edit')->with(compact('plan', 'mikrotiks', 'cuentas', 'autoRetenciones', 'cuentasInventario', 'inventario'));
        }
        return redirect('empresa/planes-velocidad')->with('danger', 'No existe un registro con ese id');
    }

    public function update(Request $request, $id)
    {

        $plan = PlanesVelocidad::where('id', $id)
            ->where('empresa', Auth::user()->empresa)
            ->first();

        if ($plan) {
            $request->validate([
                'name'     => 'required|max:200',
                'price'    => 'required|max:200',
                'upload'   => 'required|max:200',
                'download' => 'required|max:200',
                'type'     => 'required|max:200',
                'mikrotik' => 'required|max:200',
            ]);

            $plan->mikrotik               = $request->mikrotik;
            // if ((!empty($request->mikrotik[1])) && (isset($request->mikrotik[1]))) {

            //     $plan->mikrotik1 = $request->mikrotik[1];
            // }
            // if (!empty($request->mikrotik[2]) && (isset($request->mikrotik[2]))) {
            //     $plan->mikrotik2 = $request->mikrotik[2];
            // }
            // if (!empty($request->mikrotik[3]) && (isset($request->mikrotik[3]))) {
            //     $plan->mikrotik3 = $request->mikrotik[3];
            // }
            // if (!empty($request->mikrotik[4]) && (isset($request->mikrotik[4]))) {
            //     $plan->mikrotik4 = $request->mikrotik[4];
            // }
            $plan->name                   = $request->name;
            $plan->price                  = $request->price;
            $plan->upload                 = $request->upload . '' . $request->inicial_upload;
            $plan->download               = $request->download . '' . $request->inicial_download;
            $plan->type                   = $request->type;
            $plan->address_list           = $request->address_list;
            $plan->updated_by             = Auth::user()->id;
            $plan->tipo_plan              = $request->tipo_plan;
            $plan->burst_limit_subida     = $request->burst_limit_subida . '' . $request->inicial_burst_limit_subida;
            $plan->burst_limit_bajada     = $request->burst_limit_bajada . '' . $request->inicial_burst_limit_bajada;
            $plan->burst_threshold_subida = $request->burst_threshold_subida . '' . $request->inicial_burst_threshold_subida;
            $plan->burst_threshold_bajada = $request->burst_threshold_bajada . '' . $request->inicial_burst_threshold_bajada;
            $plan->burst_time_subida      = $request->burst_time_subida;
            $plan->burst_time_bajada      = $request->burst_time_bajada;
            $plan->queue_type_subida      = $request->queue_type_subida;
            $plan->queue_type_bajada      = $request->queue_type_bajada;
            $plan->parenta                = $request->parenta;
            $plan->prioridad              = $request->prioridad;
            $plan->dhcp_server            = $request->dhcp_server;
            $plan->limit_at_subida        = $request->limit_at_subida . '' . $request->inicial_limit_at_subida;
            $plan->limit_at_bajada        = $request->limit_at_bajada . '' . $request->inicial_limit_at_bajada;
            $plan->save();

            // Inventario asociado al plan
            $inventario                       = Inventario::find($plan->item);
            $inventario->producto             = strtoupper($request->name);
            $inventario->ref                  = strtoupper($request->ref);
            $inventario->precio               = $this->precision($request->price);
            $inventario->id_impuesto          = ($request->tipo_plan == 2) ? 1 : 2;
            $inventario->impuesto             = ($request->tipo_plan == 2) ? 19 : 0;
            $inventario->type_autoretencion   = isset($request->tipo_autoretencion) ? $request->tipo_autoretencion : null;
            $inventario->save();

            // ================= CUENTAS CONTABLES =================
            $services = array();

            if (isset($request->inventario)) {
                array_push($services, $request->inventario);
            }
            if (isset($request->costo)) {
                array_push($services, $request->costo);
            }
            if (isset($request->venta)) {
                array_push($services, $request->venta);
            }
            if (isset($request->devolucion)) {
                array_push($services, $request->devolucion);
            }
            if (isset($request->autoretencion)) {
                array_push($services, $request->autoretencion);
            }

            if ($request->cuentacontable) {
                $request->cuentacontable = array_merge($request->cuentacontable, $services);
            } else {
                $request->cuentacontable = $services;
            }

            // eliminar duplicados
            $request->cuentacontable = array_unique($request->cuentacontable);

            //actualizando cuentas del inventario
            $insertsCuenta = array();

            if ($request->cuentacontable) {
                foreach ($request->cuentacontable as $key) {

                    // crear o recuperar relación base
                    $registro = DB::table('producto_cuentas')
                        ->where('cuenta_id', $key)
                        ->where('inventario_id', $inventario->id)
                        ->first();

                    if (!$registro) {
                        $idCuentaPro = DB::table('producto_cuentas')->insertGetId([
                            'cuenta_id'     => $key,
                            'inventario_id' => $inventario->id
                        ]);
                    } else {
                        $idCuentaPro = $registro->id;
                    }

                    $insertsCuenta[] = $idCuentaPro;

                    // actualizar tipo aquí mismo
                    $pc = ProductoCuenta::find($idCuentaPro);
                    if (!$pc) {
                        continue;
                    }

                    if (isset($request->inventario) && $request->inventario == $key && $key != 0) {
                        $pc->tipo = 1; // Inventario
                    } elseif (isset($request->costo) && $request->costo == $key && $key != 0) {
                        $pc->tipo = 2; // Costo
                    } elseif (isset($request->venta) && $request->venta == $key && $key != 0) {
                        $pc->tipo = 3; // Venta
                    } elseif (isset($request->devolucion) && $request->devolucion == $key && $key != 0) {
                        $pc->tipo = 4; // Devolución
                    } elseif (isset($request->autoretencion) && $request->autoretencion == $key && $key != 0) {
                        if ($request->tipo_autoretencion != 1) {
                            $pc->tipo = 5; // Autoretención
                        }
                    }

                    $pc->save();
                }

                if (count($insertsCuenta) > 0) {
                    DB::table('producto_cuentas')
                        ->where('inventario_id', $inventario->id)
                        ->whereNotIn('id', $insertsCuenta)
                        ->delete();
                }
            } else {
                DB::table('producto_cuentas')
                    ->where('inventario_id', $inventario->id)
                    ->delete();
            }
            $mensaje = 'SE HA MODIFICADO SATISFACTORIAMENTE EL PLAN';
            return redirect('empresa/planes-velocidad/' . $plan->id . '/aplicar-cambios')
                ->with('success', $mensaje)
                ->with('plan_id', $plan->id);
        }

        return redirect('empresa/planes-velocidad')
            ->with('danger', 'No existe un registro con ese id');
    }

    public function destroy($id)
    {
        $plan = PlanesVelocidad::where('id', $id)->where('empresa', Auth::user()->empresa)->first();
        if ($plan) {
            $itemId = $plan->item;
            $plan->delete();

            // Eliminar inventario asociado si no está en facturas
            if ($itemId) {
                $enFactura = DB::table('items_factura')->where('producto', $itemId)->exists();
                $enFacturaProv = DB::table('items_factura_proveedor')->where('producto', $itemId)->exists();
                if (!$enFactura && !$enFacturaProv) {
                    Inventario::where('id', $itemId)->delete();
                }
            }

            return redirect('empresa/planes-velocidad')->with('success', 'Se ha eliminado correctamente el Plan');
        }
        return redirect('empresa/planes-velocidad')->with('danger', 'No existe un registro con ese id');
    }

    public function show($id)
    {
        $this->getAllPermissions(Auth::user()->id);
        $plan = PlanesVelocidad::where('id', $id)->where('empresa', Auth::user()->empresa)->first();
        if ($plan) {
            $tabla = Campos::where('modulo', 2)->where('estado', 1)->where('empresa', Auth::user()->empresa)->orderBy('orden', 'asc')->get();
            view()->share(['icon' => 'fas fa-server', 'title' => 'Plan: ' . $plan->name]);
            return view('planesvelocidad.show')->with(compact('plan', 'tabla'));
        }
        return redirect('empresa/planes-velocidad')->with('danger', 'No existe un registro con ese id');
    }

    public function status($id)
    {
        $this->getAllPermissions(Auth::user()->id);
        $plan = PlanesVelocidad::where('id', $id)->where('empresa', Auth::user()->empresa)->first();
        if ($plan) {
            if ($plan->status == 1) {
                $mensaje = 'SE HA DESHABILITADO DE PLAN SATISFACTORIAMENTE';
                $plan->status = 0;
                $plan->save();
            } else {
                $mensaje = 'SE HA HABILITADO DE PLAN SATISFACTORIAMENTE';
                $plan->status = 1;
                $plan->save();
            }
            return redirect('empresa/planes-velocidad')->with('success', $mensaje);
        }
        return redirect('empresa/planes-velocidad')->with('danger', 'No existe un registro con ese id');
    }

    public function reglas($id)
    {
        $this->getAllPermissions(Auth::user()->id);
        $plan = PlanesVelocidad::where('id', $id)->where('empresa', Auth::user()->empresa)->first();
        if ($plan) {
            $mikrotik = $plan->mikrotik();
            $API = new RouterosAPI();

            $API->port = $mikrotik->puerto_api;
            $API->debug = true;

            if ($API->connect($mikrotik->ip, $mikrotik->usuario, $mikrotik->clave)) {
                #REGLA BAJADA
                $API->comm(
                    "/ip/firewall/mangle/add",
                    array(
                        "chain" => "postrouting",
                        "dst-address-list" => $plan->name,
                        "action" => "mark-packet",
                        "new-packet-mark" => strtolower(str_replace(' ', '_', $plan->name)) . "_down",
                        "passthrough" => "no",
                        "comment" => $plan->name
                    )
                );

                #REGLA SUBIDA
                $API->comm(
                    "/ip/firewall/mangle/add",
                    array(
                        "chain" => "forward",
                        "src-address-list" => $plan->name,
                        "action" => "mark-packet",
                        "new-packet-mark" => strtolower(str_replace(' ', '_', $plan->name)) . "_up",
                        "passthrough" => "no",
                        "comment" => $plan->name
                    )
                );

                #CREACI�0�7N PCQ SUBIDA
                $API->comm(
                    "/queue/type/add",
                    array(
                        "name" => strtolower(str_replace(' ', '_', $plan->name)) . "_up",
                        "kind" => "pcq",
                        "pcq-rate" => $plan->upload,
                        "pcq-classifier" => "dst-address"

                    )
                );

                #CREACI�0�7N PCQ BAJADA
                $API->comm(
                    "/queue/type/add",
                    array(
                        "name" => strtolower(str_replace(' ', '_', $plan->name)) . "_down",
                        "kind" => "pcq",
                        "pcq-rate" => $plan->download,
                        "pcq-classifier" => "src-address"
                    )
                );

                #COLA PADRE DE BAJADA
                $API->comm(
                    "/queue/tree/add",
                    array(
                        "name" => "DOWN-GLOBAL",
                        "parent" => "global"
                    )
                );

                #COLA HIJA DE BAJADA
                $API->comm(
                    "/queue/tree/add",
                    array(
                        "name" => strtolower(str_replace(' ', '_', $plan->name)) . "_down",
                        "packet-mark" => strtolower(str_replace(' ', '_', $plan->name)) . "_down",
                        "queue" => strtolower(str_replace(' ', '_', $plan->name)) . "_down",
                        "parent" => "DOWN-GLOBAL"
                    )
                );

                #COLA PADRE DE SUBIDA
                $API->comm(
                    "/queue/tree/add",
                    array(
                        "name" => "UP-GLOBAL",
                        "parent" => "global"
                    )
                );

                #COLA HIJA DE SUBIDA
                $API->comm(
                    "/queue/tree/add",
                    array(
                        "name" => strtolower(str_replace(' ', '_', $plan->name)) . "_up",
                        "packet-mark" => strtolower(str_replace(' ', '_', $plan->name)) . "_up",
                        "queue" => strtolower(str_replace(' ', '_', $plan->name)) . "_up",
                        "parent" => "UP-GLOBAL"
                    )
                );

                $API->disconnect();

                $mensaje = 'Reglas aplicadas satisfactoriamente a la Mikrotik ' . $mikrotik->nombre;
                $type = 'success';
            } else {
                $mensaje = 'Reglas no aplicadas a la Mikrotik ' . $mikrotik->nombre . ', intente nuevamente.';
                $type = 'danger';
            }
            return redirect('empresa/planes-velocidad')->with($type, $mensaje);
        }
        return redirect('empresa/planes-velocidad')->with('danger', 'No existe un registro con ese id');
    }

    public function aplicar_cambios($id)
    {
        $this->getAllPermissions(Auth::user()->id);
        $plan = PlanesVelocidad::where('id', $id)->where('empresa', Auth::user()->empresa)->first();
        if ($plan) {
            $contratos = Contrato::where('plan_id', $plan->id)->where('status', 1)->where('empresa', Auth::user()->empresa)->get();
            view()->share(['title' => "Contratos con Plan", 'icon' => 'fas fa-project-diagram', 'middel' => true]);
            return view('planesvelocidad.aplicar_cambios')->with(compact('contratos', 'plan'));
        }
        return redirect('empresa/planes-velocidad')->with('danger', 'No existe un registro con ese id');
    }

    public function aplicando_cambios($contratos)
    {
        $this->getAllPermissions(Auth::user()->id);

        $succ = 0;
        $fail = 0;

        $contratos = explode(",", $contratos);

        for ($i = 0; $i < count($contratos); $i++) {
            $contrato = Contrato::find($contratos[$i]);
            $plan     = PlanesVelocidad::find($contrato->plan_id);

            if ($plan) {
                $mikrotik = Mikrotik::find($plan->mikrotik);
                $API = new RouterosAPI();
                $API->port = $mikrotik->puerto_api;

                $rate_limit = '';
                $priority        = $plan->prioridad;
                $burst_limit     = (strlen($plan->burst_limit_subida) > 1) ? $plan->burst_limit_subida . '/' . $plan->burst_limit_bajada : '';
                $burst_threshold = (strlen($plan->burst_threshold_subida) > 1) ? $plan->burst_threshold_subida . '/' . $plan->burst_threshold_bajada : '';
                $burst_time      = ($plan->burst_time_subida) ? $plan->burst_time_subida . '/' . $plan->burst_time_bajada : '';
                $limit_at        = (strlen($plan->limit_at_subida) > 1) ? $plan->limit_at_subida . '/' . $plan->limit_at_bajada  : '';
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

                if ($API->connect($mikrotik->ip, $mikrotik->usuario, $mikrotik->clave)) {
                    $queue = $API->comm(
                        "/queue/simple/getall",
                        array(
                            "?target" => $contrato->ip . '/32'
                        )
                    );

                    if (count($queue) > 0) {
                        $API->comm(
                            "/queue/simple/set",
                            array(
                                ".id"             => $queue[0][".id"],
                                "max-limit"       => $plan->upload . '/' . $plan->download,
                                "burst-limit"     => $burst_limit,
                                "burst-threshold" => $burst_threshold,
                                "burst-time"      => $burst_time,
                                "priority"        => $priority,
                                "limit-at"        => $limit_at
                            )
                        );
                        $succ++;
                    } else {
                        $fail++;
                    }
                    $API->disconnect();
                }
            } else {
                $fail++;
            }
        }

        return response()->json([
            'success'   => true,
            'fallidos'  => $fail,
            'correctos' => $succ
        ]);
    }

    public function state_lote($planes, $state)
    {
        $this->getAllPermissions(Auth::user()->id);

        $succ = 0;
        $fail = 0;

        $planes = explode(",", $planes);

        for ($i = 0; $i < count($planes); $i++) {
            $plan = PlanesVelocidad::find($planes[$i]);
            if ($plan) {
                $plan->status = ($state == 'disabled') ? 0 : 1;
                $plan->save();
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

    public function destroy_lote($planes)
    {
        $this->getAllPermissions(Auth::user()->id);

        $succ = 0;
        $fail = 0;

        $planes = explode(",", $planes);

        for ($i = 0; $i < count($planes); $i++) {
            $plan = PlanesVelocidad::find($planes[$i]);
            if (!$plan) {
                $fail++;
                continue;
            }
            if ($plan->uso() == 0) {
                $itemId = $plan->item;
                $plan->delete();

                // Eliminar inventario asociado si no está en facturas
                if ($itemId) {
                    $enFactura = DB::table('items_factura')->where('producto', $itemId)->exists();
                    $enFacturaProv = DB::table('items_factura_proveedor')->where('producto', $itemId)->exists();
                    if (!$enFactura && !$enFacturaProv) {
                        Inventario::where('id', $itemId)->delete();
                    }
                }

                $succ++;
            } else {
                $fail++;
            }
        }

        return response()->json([
            'success'   => true,
            'fallidos'  => $fail,
            'correctos' => $succ,
            'state'     => 'eliminados'
        ]);
    }

    public function crear_en_mikrotik($planes, $mikrotik_id)
    {
        $this->getAllPermissions(Auth::user()->id);

        $succ = 0;
        $fail = 0;

        $planes = explode(",", $planes);
        $mikrotik = Mikrotik::where('id', $mikrotik_id)
            ->where('empresa', Auth::user()->empresa)
            ->first();

        if (!$mikrotik) {
            return response()->json([
                'success'   => false,
                'fallidos'  => count($planes),
                'correctos' => 0,
                'message'   => 'La Mikrotik seleccionada no existe o no pertenece a su empresa'
            ], 400);
        }

        foreach ($planes as $plan_id) {
            try {
                $planOriginal = PlanesVelocidad::where('id', $plan_id)
                    ->where('empresa', Auth::user()->empresa)
                    ->first();

                if (!$planOriginal) {
                    $fail++;
                    continue;
                }

                // Verificar si ya existe un plan con el mismo nombre en esa mikrotik
                $planExistente = PlanesVelocidad::where('name', $planOriginal->name)
                    ->where('mikrotik', $mikrotik_id)
                    ->where('empresa', Auth::user()->empresa)
                    ->first();

                if ($planExistente) {
                    $fail++;
                    continue;
                }

                // Obtener el inventario original del plan
                $inventarioOriginal = Inventario::find($planOriginal->item);
                
                if (!$inventarioOriginal) {
                    $fail++;
                    continue;
                }

                // Validar si ya existe un plan en la mikrotik destino con un inventario que tenga la misma referencia
                $planesEnMikrotik = PlanesVelocidad::where('mikrotik', $mikrotik_id)
                    ->where('empresa', Auth::user()->empresa)
                    ->get();

                $planConMismaRef = false;
                foreach ($planesEnMikrotik as $planMikrotik) {
                    $inventarioPlan = Inventario::find($planMikrotik->item);
                    if ($inventarioPlan && $inventarioPlan->ref === $inventarioOriginal->ref) {
                        $planConMismaRef = true;
                        break;
                    }
                }

                if ($planConMismaRef) {
                    $fail++;
                    continue;
                }

                // CREAR NUEVO INVENTARIO (siguiendo el mismo patrón que store)
                $inventario = new Inventario;
                $inventario->empresa = Auth::user()->empresa;
                $inventario->producto = $inventarioOriginal->producto;
                $inventario->ref = $inventarioOriginal->ref;
                $inventario->precio = $inventarioOriginal->precio;
                $inventario->id_impuesto = $inventarioOriginal->id_impuesto;
                $inventario->impuesto = $inventarioOriginal->impuesto;
                $inventario->tipo_producto = $inventarioOriginal->tipo_producto;
                $inventario->unidad = $inventarioOriginal->unidad;
                $inventario->nro = 0;
                $inventario->categoria = $inventarioOriginal->categoria;
                $inventario->lista = $inventarioOriginal->lista;
                $inventario->type_autoretencion = $inventarioOriginal->type_autoretencion;
                $inventario->type = 'PLAN';
                $inventario->save();

                // Copiar las cuentas contables del inventario original (producto_cuentas)
                $cuentasOriginales = ProductoCuenta::where('inventario_id', $inventarioOriginal->id)->get();
                foreach ($cuentasOriginales as $cuentaOriginal) {
                    $productoCuenta = new ProductoCuenta;
                    $productoCuenta->cuenta_id = $cuentaOriginal->cuenta_id;
                    $productoCuenta->inventario_id = $inventario->id;
                    $productoCuenta->tipo = $cuentaOriginal->tipo;
                    $productoCuenta->save();
                }

                // CREAR EL PLAN (después de crear el inventario)
                $plan = new PlanesVelocidad;
                $plan->mikrotik = $mikrotik_id;
                $plan->name = $planOriginal->name;
                $plan->price = $planOriginal->price;
                $plan->upload = $planOriginal->upload;
                $plan->download = $planOriginal->download;
                $plan->type = $planOriginal->type;
                $plan->address_list = $planOriginal->address_list;
                $plan->created_by = Auth::user()->id;
                $plan->tipo_plan = $planOriginal->tipo_plan;
                $plan->burst_limit_subida = $planOriginal->burst_limit_subida;
                $plan->burst_limit_bajada = $planOriginal->burst_limit_bajada;
                $plan->burst_threshold_subida = $planOriginal->burst_threshold_subida;
                $plan->burst_threshold_bajada = $planOriginal->burst_threshold_bajada;
                $plan->burst_time_subida = $planOriginal->burst_time_subida;
                $plan->burst_time_bajada = $planOriginal->burst_time_bajada;
                $plan->queue_type_subida = $planOriginal->queue_type_subida;
                $plan->queue_type_bajada = $planOriginal->queue_type_bajada;
                $plan->parenta = $planOriginal->parenta;
                $plan->prioridad = $planOriginal->prioridad;
                $plan->limit_at_subida = $planOriginal->limit_at_subida;
                $plan->limit_at_bajada = $planOriginal->limit_at_bajada;
                $plan->item = $inventario->id;
                $plan->empresa = Auth::user()->empresa;
                $plan->dhcp_server = $planOriginal->dhcp_server;
                $plan->status = $planOriginal->status;
                $plan->save();

                $succ++;
            } catch (\Exception $e) {
                \Log::error('Error al crear plan en mikrotik: ' . $e->getMessage());
                $fail++;
            }
        }

        return response()->json([
            'success'   => true,
            'fallidos'  => $fail,
            'correctos' => $succ
        ]);
    }

    // ==================== IMPORTAR PLANES DE VELOCIDAD ====================

    public function importar()
    {
        $this->getAllPermissions(Auth::user()->id);
        view()->share(['title' => 'Importar Planes de Velocidad', 'full' => true]);
        $mikrotiks = Mikrotik::where('empresa', Auth::user()->empresa)->get();
        return view('planesvelocidad.importar')->with(compact('mikrotiks'));
    }

    public function ejemploImportar()
    {
        $titulosColumnas = array(
            'Nombre', 'Referencia', 'Precio (sin puntos)', 'Vel. de Descarga', 'Unidad Descarga',
            'Vel. de Subida', 'Unidad Subida', 'Mikrotik', 'Tipo', 'IVA 19%'
        );

        $comentarios = array(
            'A' => 'Nombre del plan de velocidad. Obligatorio.',
            'B' => 'Referencia única del plan (se guarda en inventario). Obligatorio. No puede repetirse.',
            'C' => 'Precio del plan sin puntos ni separadores. Obligatorio.',
            'D' => 'Valor numérico de la velocidad de descarga. Obligatorio.',
            'E' => 'Unidad de descarga: Mbps o Kbps. Obligatorio.',
            'F' => 'Valor numérico de la velocidad de subida. Obligatorio.',
            'G' => 'Unidad de subida: Mbps o Kbps. Obligatorio.',
            'H' => 'Nombre de la mikrotik ya registrada en el sistema. Obligatorio.',
            'I' => 'Tipo de plan: queue o pcq. Obligatorio.',
            'J' => 'Indica si el plan tiene IVA 19%: si o no. Obligatorio.',
        );

        $objPHPExcel = new PHPExcel();
        $tituloReporte = "Archivo de Importación de Planes de Velocidad - " . Auth::user()->empresa()->nombre;

        $letras = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J');
        $ultimaColumna = $letras[count($titulosColumnas) - 1];

        $objPHPExcel->getProperties()->setCreator("Sistema")
            ->setLastModifiedBy("Sistema")
            ->setTitle("Importación Planes Velocidad")
            ->setSubject("Importación Planes Velocidad")
            ->setDescription("Importación Planes Velocidad")
            ->setKeywords("Importación Planes Velocidad")
            ->setCategory("Importación");

        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('A1:' . $ultimaColumna . '1');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $tituloReporte);
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('A2:' . $ultimaColumna . '2');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A2', 'Fecha ' . date('d-m-Y'));

        $estilo = array(
            'font'  => array('bold'  => true, 'size'  => 12, 'name'  => 'Times New Roman'),
            'alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER)
        );
        $objPHPExcel->getActiveSheet()->getStyle('A1:' . $ultimaColumna . '3')->applyFromArray($estilo);

        $estilo = array(
            'fill' => array(
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('rgb' => substr(Auth::user()->empresa()->color, 1))
            ),
            'font'  => array('bold'  => true, 'size'  => 12, 'name'  => 'Times New Roman', 'color' => array('rgb' => 'FFFFFF')),
            'alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER)
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
            'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_THIN)),
            'alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER)
        );
        $objPHPExcel->getActiveSheet()->getStyle('A3:' . $ultimaColumna . '3')->applyFromArray($estilo);

        for ($i = 'A'; $i <= $ultimaColumna; $i++) {
            $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension($i)->setAutoSize(TRUE);
        }

        // Agregar validaciones de lista desplegable para Unidad Descarga (E) y Unidad Subida (G)
        for ($row = 4; $row <= 100; $row++) {
            $validationE = $objPHPExcel->getActiveSheet()->getCell('E' . $row)->getDataValidation();
            $validationE->setType(PHPExcel_Cell_DataValidation::TYPE_LIST);
            $validationE->setAllowBlank(false);
            $validationE->setShowDropDown(true);
            $validationE->setFormula1('"Mbps,Kbps"');

            $validationG = $objPHPExcel->getActiveSheet()->getCell('G' . $row)->getDataValidation();
            $validationG->setType(PHPExcel_Cell_DataValidation::TYPE_LIST);
            $validationG->setAllowBlank(false);
            $validationG->setShowDropDown(true);
            $validationG->setFormula1('"Mbps,Kbps"');

            $validationI = $objPHPExcel->getActiveSheet()->getCell('I' . $row)->getDataValidation();
            $validationI->setType(PHPExcel_Cell_DataValidation::TYPE_LIST);
            $validationI->setAllowBlank(false);
            $validationI->setShowDropDown(true);
            $validationI->setFormula1('"queue,pcq"');

            $validationJ = $objPHPExcel->getActiveSheet()->getCell('J' . $row)->getDataValidation();
            $validationJ->setType(PHPExcel_Cell_DataValidation::TYPE_LIST);
            $validationJ->setAllowBlank(false);
            $validationJ->setShowDropDown(true);
            $validationJ->setFormula1('"si,no"');
        }

        $objPHPExcel->getActiveSheet()->setTitle('Planes');
        $objPHPExcel->setActiveSheetIndex(0);
        $objPHPExcel->getActiveSheet(0)->freezePane('A4');

        header("Pragma: no-cache");
        header('Content-type: application/vnd.ms-excel');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="Plantilla_Importacion_Planes_Velocidad.xlsx"');
        header('Cache-Control: max-age=0');
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
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
        $nombre_imagen = 'archivo_planes.' . $imagen->getClientOriginalExtension();
        $path = public_path() . '/images/Empresas/Empresa' . Auth::user()->empresa;
        $imagen->move($path, $nombre_imagen);
        Ini_set('max_execution_time', 500);
        $fileWithPath = $path . "/" . $nombre_imagen;

        $inputFileType = PHPExcel_IOFactory::identify($fileWithPath);
        $objReader = PHPExcel_IOFactory::createReader($inputFileType);
        $objPHPExcel = $objReader->load($fileWithPath);
        $sheet = $objPHPExcel->getSheet(0);
        $highestRow = $sheet->getHighestRow();

        for ($row = 4; $row <= $highestRow; $row++) {
            $nombre = trim($sheet->getCell("A" . $row)->getValue());
            if (empty($nombre)) {
                break;
            }

            $referencia       = trim($sheet->getCell("B" . $row)->getValue());
            $precio           = trim($sheet->getCell("C" . $row)->getValue());
            $vel_descarga     = trim($sheet->getCell("D" . $row)->getValue());
            $unidad_descarga  = trim($sheet->getCell("E" . $row)->getValue());
            $vel_subida       = trim($sheet->getCell("F" . $row)->getValue());
            $unidad_subida    = trim($sheet->getCell("G" . $row)->getValue());
            $mikrotik_nombre  = trim($sheet->getCell("H" . $row)->getValue());
            $tipo             = strtolower(trim($sheet->getCell("I" . $row)->getValue()));
            $iva              = strtolower(trim($sheet->getCell("J" . $row)->getValue()));

            // Validaciones
            if (empty($nombre) || empty($referencia) || empty($precio) || empty($vel_descarga) ||
                empty($unidad_descarga) || empty($vel_subida) || empty($unidad_subida) ||
                empty($mikrotik_nombre) || empty($tipo)) {
                $errores[] = "Fila $row: Los campos nombre, referencia, precio, velocidades, unidades, mikrotik y tipo son obligatorios.";
                continue;
            }

            // IVA es opcional, por defecto 'no'
            if (empty($iva)) {
                $iva = 'no';
            }

            // Buscar mikrotik por nombre (antes de validar ref, necesitamos el ID)
            $mikrotik = Mikrotik::where('empresa', Auth::user()->empresa)
                ->whereRaw('LOWER(nombre) LIKE ?', ['%' . strtolower($mikrotik_nombre) . '%'])
                ->first();
            if (!$mikrotik) {
                $errores[] = "Fila $row: La mikrotik '<b>$mikrotik_nombre</b>' no fue encontrada.";
                continue;
            }

            // Validar referencia única por mikrotik (la ref puede repetirse si es otra mikrotik)
            $existeRef = PlanesVelocidad::join('inventario', 'inventario.id', '=', 'planes_velocidad.item')
                ->where('planes_velocidad.empresa', Auth::user()->empresa)
                ->where('planes_velocidad.mikrotik', $mikrotik->id)
                ->where('inventario.ref', strtoupper($referencia))
                ->first();
            if ($existeRef) {
                $errores[] = "Fila $row: La referencia '<b>$referencia</b>' ya existe para la mikrotik '<b>$mikrotik_nombre</b>'.";
                continue;
            }

            // Determinar tipo (0 = queue, 1 = pcq)
            $type = ($tipo == 'pcq') ? 1 : 0;

            // Determinar tipo_plan según IVA
            $tipo_plan = ($iva == 'si') ? 2 : 1;

            // Determinar sufijos de velocidad
            $sufijo_download = (strtolower($unidad_descarga) == 'mbps') ? 'M' : 'k';
            $sufijo_upload   = (strtolower($unidad_subida) == 'mbps') ? 'M' : 'k';

            // Crear inventario
            $inventario                = new Inventario;
            $inventario->empresa       = Auth::user()->empresa;
            $inventario->producto      = strtoupper($nombre);
            $inventario->ref           = strtoupper($referencia);
            $inventario->precio        = $this->precision($precio);
            $inventario->id_impuesto   = ($tipo_plan == 2) ? 1 : 2;
            $inventario->impuesto      = ($tipo_plan == 2) ? 19 : 0;
            $inventario->tipo_producto = 2;
            $inventario->unidad        = 1;
            $inventario->nro           = 0;
            $inventario->categoria     = 116;
            $inventario->lista         = 0;
            $inventario->type          = 'PLAN';
            $inventario->save();

            // Crear plan de velocidad
            $plan              = new PlanesVelocidad;
            $plan->mikrotik    = $mikrotik->id;
            $plan->name        = $nombre;
            $plan->price       = $precio;
            $plan->download    = $vel_descarga . $sufijo_download;
            $plan->upload      = $vel_subida . $sufijo_upload;
            $plan->type        = $type;
            $plan->created_by  = Auth::user()->id;
            $plan->tipo_plan   = $tipo_plan;
            $plan->item        = $inventario->id;
            $plan->empresa     = Auth::user()->empresa;
            $plan->save();

            $create++;
        }

        // Limpiar archivo temporal
        if (file_exists($fileWithPath)) {
            unlink($fileWithPath);
        }

        if (count($errores) > 0) {
            return redirect()->route('planes-velocidad.importar')
                ->withErrors($errores)
                ->with('success', "Se importaron $create planes correctamente.");
        }

        return redirect('empresa/planes-velocidad')
            ->with('success', "Se importaron $create planes de velocidad correctamente.");
    }

    // ==================== ACTUALIZAR PLANES DE VELOCIDAD ====================

    public function actualizarMasivo()
    {
        $this->getAllPermissions(Auth::user()->id);
        view()->share(['title' => 'Actualizar Planes de Velocidad', 'full' => true]);
        $mikrotiks = Mikrotik::where('empresa', Auth::user()->empresa)->get();
        return view('planesvelocidad.actualizar')->with(compact('mikrotiks'));
    }

    public function ejemploActualizar()
    {
        $titulosColumnas = array(
            'ID', 'Nombre', 'Referencia', 'Precio (sin puntos)', 'Vel. de Descarga', 'Unidad Descarga',
            'Vel. de Subida', 'Unidad Subida', 'Mikrotik', 'Tipo', 'IVA 19%'
        );

        $comentarios = array(
            'A' => 'ID del plan para identificarlo y actualizarlo. Obligatorio. No modificar.',
            'B' => 'Nombre del plan de velocidad. Obligatorio.',
            'C' => 'Referencia única del plan (se guarda en inventario). Obligatorio.',
            'D' => 'Precio del plan sin puntos ni separadores. Obligatorio.',
            'E' => 'Valor numérico de la velocidad de descarga. Obligatorio.',
            'F' => 'Unidad de descarga: Mbps o Kbps. Obligatorio.',
            'G' => 'Valor numérico de la velocidad de subida. Obligatorio.',
            'H' => 'Unidad de subida: Mbps o Kbps. Obligatorio.',
            'I' => 'Nombre de la mikrotik ya registrada en el sistema. Obligatorio.',
            'J' => 'Tipo de plan: queue o pcq. Obligatorio.',
            'K' => 'Indica si el plan tiene IVA 19%: si o no. Obligatorio.',
        );

        $objPHPExcel = new PHPExcel();
        $tituloReporte = "Archivo de Actualización de Planes de Velocidad - " . Auth::user()->empresa()->nombre;

        $letras = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K');
        $ultimaColumna = $letras[count($titulosColumnas) - 1];

        $objPHPExcel->getProperties()->setCreator("Sistema")
            ->setLastModifiedBy("Sistema")
            ->setTitle("Actualización Planes Velocidad")
            ->setSubject("Actualización Planes Velocidad")
            ->setDescription("Actualización Planes Velocidad")
            ->setKeywords("Actualización Planes Velocidad")
            ->setCategory("Actualización");

        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('A1:' . $ultimaColumna . '1');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $tituloReporte);
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('A2:' . $ultimaColumna . '2');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A2', 'Fecha ' . date('d-m-Y'));

        $estilo = array(
            'font'  => array('bold'  => true, 'size'  => 12, 'name'  => 'Times New Roman'),
            'alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER)
        );
        $objPHPExcel->getActiveSheet()->getStyle('A1:' . $ultimaColumna . '3')->applyFromArray($estilo);

        $estilo = array(
            'fill' => array(
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('rgb' => substr(Auth::user()->empresa()->color, 1))
            ),
            'font'  => array('bold'  => true, 'size'  => 12, 'name'  => 'Times New Roman', 'color' => array('rgb' => 'FFFFFF')),
            'alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER)
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
            'borders' => array('allborders' => array('style' => PHPExcel_Style_Border::BORDER_THIN)),
            'alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER)
        );
        $objPHPExcel->getActiveSheet()->getStyle('A3:' . $ultimaColumna . '3')->applyFromArray($estilo);

        for ($i = 'A'; $i <= $ultimaColumna; $i++) {
            $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension($i)->setAutoSize(TRUE);
        }

        // Llenar con datos existentes
        $planes = PlanesVelocidad::where('empresa', Auth::user()->empresa)->get();
        $j = 4;
        foreach ($planes as $plan) {
            $mikrotikObj = $plan->mikrotik();
            $inventario = Inventario::find($plan->item);

            // Parsear download/upload para extraer número y unidad
            $downloadVal = preg_replace('/[^0-9]/', '', $plan->download);
            $downloadUnit = (strpos($plan->download, 'M') !== false) ? 'Mbps' : 'Kbps';
            $uploadVal = preg_replace('/[^0-9]/', '', $plan->upload);
            $uploadUnit = (strpos($plan->upload, 'M') !== false) ? 'Mbps' : 'Kbps';

            $tipoTexto = ($plan->type == 0) ? 'queue' : 'pcq';
            $ivaTexto = ($plan->tipo_plan == 2) ? 'si' : 'no';

            $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue("A$j", $plan->id)
                ->setCellValue("B$j", $plan->name)
                ->setCellValue("C$j", $inventario ? $inventario->ref : '')
                ->setCellValue("D$j", $plan->price)
                ->setCellValue("E$j", $downloadVal)
                ->setCellValue("F$j", $downloadUnit)
                ->setCellValue("G$j", $uploadVal)
                ->setCellValue("H$j", $uploadUnit)
                ->setCellValue("I$j", $mikrotikObj ? $mikrotikObj->nombre : '')
                ->setCellValue("J$j", $tipoTexto)
                ->setCellValue("K$j", $ivaTexto);

            $j++;
        }

        // Agregar validaciones de lista desplegable
        for ($row = 4; $row <= max(100, $j + 10); $row++) {
            $validationF = $objPHPExcel->getActiveSheet()->getCell('F' . $row)->getDataValidation();
            $validationF->setType(PHPExcel_Cell_DataValidation::TYPE_LIST);
            $validationF->setAllowBlank(false);
            $validationF->setShowDropDown(true);
            $validationF->setFormula1('"Mbps,Kbps"');

            $validationH = $objPHPExcel->getActiveSheet()->getCell('H' . $row)->getDataValidation();
            $validationH->setType(PHPExcel_Cell_DataValidation::TYPE_LIST);
            $validationH->setAllowBlank(false);
            $validationH->setShowDropDown(true);
            $validationH->setFormula1('"Mbps,Kbps"');

            $validationJ = $objPHPExcel->getActiveSheet()->getCell('J' . $row)->getDataValidation();
            $validationJ->setType(PHPExcel_Cell_DataValidation::TYPE_LIST);
            $validationJ->setAllowBlank(false);
            $validationJ->setShowDropDown(true);
            $validationJ->setFormula1('"queue,pcq"');

            $validationK = $objPHPExcel->getActiveSheet()->getCell('K' . $row)->getDataValidation();
            $validationK->setType(PHPExcel_Cell_DataValidation::TYPE_LIST);
            $validationK->setAllowBlank(false);
            $validationK->setShowDropDown(true);
            $validationK->setFormula1('"si,no"');
        }

        $objPHPExcel->getActiveSheet()->setTitle('Planes');
        $objPHPExcel->setActiveSheetIndex(0);
        $objPHPExcel->getActiveSheet(0)->freezePane('A4');

        header("Pragma: no-cache");
        header('Content-type: application/vnd.ms-excel');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="Plantilla_Actualizacion_Planes_Velocidad.xlsx"');
        header('Cache-Control: max-age=0');
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');
        exit;
    }

    public function actualizarCargando(Request $request)
    {
        $request->validate([
            'archivo' => 'required|mimes:xlsx',
        ], [
            'archivo.mimes' => 'El archivo debe ser de extensión xlsx'
        ]);

        $updated = 0;
        $errores = [];
        $imagen = $request->file('archivo');
        $nombre_imagen = 'archivo_planes_act.' . $imagen->getClientOriginalExtension();
        $path = public_path() . '/images/Empresas/Empresa' . Auth::user()->empresa;
        $imagen->move($path, $nombre_imagen);
        Ini_set('max_execution_time', 500);
        $fileWithPath = $path . "/" . $nombre_imagen;

        $inputFileType = PHPExcel_IOFactory::identify($fileWithPath);
        $objReader = PHPExcel_IOFactory::createReader($inputFileType);
        $objPHPExcel = $objReader->load($fileWithPath);
        $sheet = $objPHPExcel->getSheet(0);
        $highestRow = $sheet->getHighestRow();

        for ($row = 4; $row <= $highestRow; $row++) {
            $id = trim($sheet->getCell("A" . $row)->getValue());
            if (empty($id)) {
                break;
            }

            $nombre           = trim($sheet->getCell("B" . $row)->getValue());
            $referencia       = trim($sheet->getCell("C" . $row)->getValue());
            $precio           = trim($sheet->getCell("D" . $row)->getValue());
            $vel_descarga     = trim($sheet->getCell("E" . $row)->getValue());
            $unidad_descarga  = trim($sheet->getCell("F" . $row)->getValue());
            $vel_subida       = trim($sheet->getCell("G" . $row)->getValue());
            $unidad_subida    = trim($sheet->getCell("H" . $row)->getValue());
            $mikrotik_nombre  = trim($sheet->getCell("I" . $row)->getValue());
            $tipo             = strtolower(trim($sheet->getCell("J" . $row)->getValue()));
            $iva              = strtolower(trim($sheet->getCell("K" . $row)->getValue()));

            // Validaciones
            if (empty($nombre) || empty($referencia) || empty($precio) || empty($vel_descarga) ||
                empty($unidad_descarga) || empty($vel_subida) || empty($unidad_subida) ||
                empty($mikrotik_nombre) || empty($tipo)) {
                $errores[] = "Fila $row: Los campos nombre, referencia, precio, velocidades, unidades, mikrotik y tipo son obligatorios.";
                continue;
            }

            // IVA es opcional, por defecto 'no'
            if (empty($iva)) {
                $iva = 'no';
            }

            // Buscar plan existente
            $plan = PlanesVelocidad::where('id', $id)
                ->where('empresa', Auth::user()->empresa)
                ->first();
            if (!$plan) {
                $errores[] = "Fila $row: No se encontró un plan con ID '<b>$id</b>'.";
                continue;
            }

            // Buscar mikrotik por nombre (antes de validar ref, necesitamos el ID)
            $mikrotik = Mikrotik::where('empresa', Auth::user()->empresa)
                ->whereRaw('LOWER(nombre) LIKE ?', ['%' . strtolower($mikrotik_nombre) . '%'])
                ->first();
            if (!$mikrotik) {
                $errores[] = "Fila $row: La mikrotik '<b>$mikrotik_nombre</b>' no fue encontrada.";
                continue;
            }

            // Validar referencia única por mikrotik (la ref puede repetirse si es otra mikrotik)
            $existeRef = PlanesVelocidad::join('inventario', 'inventario.id', '=', 'planes_velocidad.item')
                ->where('planes_velocidad.empresa', Auth::user()->empresa)
                ->where('planes_velocidad.mikrotik', $mikrotik->id)
                ->where('inventario.ref', strtoupper($referencia))
                ->where('planes_velocidad.id', '!=', $id)
                ->first();
            if ($existeRef) {
                $errores[] = "Fila $row: La referencia '<b>$referencia</b>' ya existe para la mikrotik '<b>$mikrotik_nombre</b>'.";
                continue;
            }

            // Determinar tipo
            $type = ($tipo == 'pcq') ? 1 : 0;

            // Determinar tipo_plan según IVA
            $tipo_plan = ($iva == 'si') ? 2 : 1;

            // Determinar sufijos de velocidad
            $sufijo_download = (strtolower($unidad_descarga) == 'mbps') ? 'M' : 'k';
            $sufijo_upload   = (strtolower($unidad_subida) == 'mbps') ? 'M' : 'k';

            // Actualizar plan
            $plan->mikrotik   = $mikrotik->id;
            $plan->name       = $nombre;
            $plan->price      = $precio;
            $plan->download   = $vel_descarga . $sufijo_download;
            $plan->upload     = $vel_subida . $sufijo_upload;
            $plan->type       = $type;
            $plan->updated_by = Auth::user()->id;
            $plan->tipo_plan  = $tipo_plan;
            $plan->save();

            // Actualizar inventario asociado
            $inventario = Inventario::find($plan->item);
            if ($inventario) {
                $inventario->producto    = strtoupper($nombre);
                $inventario->ref         = strtoupper($referencia);
                $inventario->precio      = $this->precision($precio);
                $inventario->id_impuesto = ($tipo_plan == 2) ? 1 : 2;
                $inventario->impuesto    = ($tipo_plan == 2) ? 19 : 0;
                $inventario->save();
            }

            $updated++;
        }

        // Limpiar archivo temporal
        if (file_exists($fileWithPath)) {
            unlink($fileWithPath);
        }

        if (count($errores) > 0) {
            return redirect()->route('planes-velocidad.actualizar-masivo')
                ->withErrors($errores)
                ->with('success', "Se actualizaron $updated planes correctamente.");
        }

        return redirect('empresa/planes-velocidad')
            ->with('success', "Se actualizaron $updated planes de velocidad correctamente.");
    }
}
