<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Mail;
use Validator;
use Illuminate\Validation\Rule;
use Auth;
use DB;
use Session;

use App\Mikrotik;
use App\Empresa;
use App\Http\Controllers\CronController;
use App\Contrato;
use App\GrupoCorte;
use App\Campos;
use App\Model\Ingresos\Factura;
use App\Model\Ingresos\ItemsFactura;
use App\Contacto;
use App\Services\BillingCycleAnalyzer;
use App\NumeracionFactura;

class GruposCorteController extends Controller
{
    public function __construct(){
        $this->middleware('auth');
        set_time_limit(300);
        view()->share(['inicio' => 'master', 'seccion' => 'zonas', 'subseccion' => 'grupo_corte', 'title' => 'Grupos de Corte', 'icon' => 'fas fa-project-diagram']);
    }

    public function index(Request $request){
        $this->getAllPermissions(Auth::user()->id);
        return view('grupos-corte.index');
    }

    public function grupos(Request $request){
        $modoLectura = auth()->user()->modo_lectura();
        $grupos = GrupoCorte::query()
            ->where('empresa', Auth::user()->empresa);
        if ($request->filtro == true) {
            if($request->nombre){
                $grupos->where(function ($query) use ($request) {
                    $query->orWhere('nombre', 'like', "%{$request->nombre}%");
                });
            }
            if($request->fecha_factura){
                $grupos->where(function ($query) use ($request) {
                    $query->orWhere('fecha_factura', 'like', "%{$request->fecha_factura}%");
                });
            }
            if($request->fecha_pago){
                $grupos->where(function ($query) use ($request) {
                    $query->orWhere('fecha_pago', 'like', "%{$request->fecha_pago}%");
                });
            }
            if($request->fecha_corte){
                $grupos->where(function ($query) use ($request) {
                    $query->orWhere('fecha_corte', 'like', "%{$request->fecha_corte}%");
                });
            }
            if($request->fecha_suspension){
                $grupos->where(function ($query) use ($request) {
                    $query->orWhere('fecha_suspension', 'like', "%{$request->fecha_suspension}%");
                });
            }
            if($request->status >= 0){
                $grupos->where(function ($query) use ($request) {
                    $query->orWhere('status', 'like', "%{$request->status}%");
                });
            }
        }

        return datatables()->eloquent($grupos)
            ->editColumn('id', function (GrupoCorte $grupo) {
                return $grupo->id;
            })
            ->editColumn('nombre', function (GrupoCorte $grupo) {
                return "<a href=" . route('grupos-corte.show', $grupo->id) . ">{$grupo->nombre}</div></a>";
            })
            ->editColumn('fecha_factura', function (GrupoCorte $grupo) {
                return ($grupo->fecha_factura == 0) ? 'No aplica' : $grupo->fecha_factura;
            })
            ->editColumn('fecha_pago', function (GrupoCorte $grupo) {
                return ($grupo->fecha_pago == 0) ? 'No aplica' : $grupo->fecha_pago;
            })
            ->editColumn('fecha_corte', function (GrupoCorte $grupo) {
                return ($grupo->fecha_corte == 0) ? 'No aplica' : $grupo->fecha_corte;
            })
            ->editColumn('fecha_suspension', function (GrupoCorte $grupo) {
                return ($grupo->fecha_suspension == 0) ? 'No aplica' : $grupo->fecha_suspension;
            })
            ->editColumn('hora_suspension', function (GrupoCorte $grupo) {
                return date('g:i A', strtotime($grupo->hora_suspension));
            })
            ->editColumn('status', function (GrupoCorte $grupo) {
                return "<span class='text-{$grupo->status("true")}'><strong>{$grupo->status()}</strong></span>";
            })
            ->addColumn('acciones', $modoLectura ?  "" : "grupos-corte.acciones")
            ->rawColumns(['acciones', 'nombre', 'id', 'status'])
            ->toJson();
    }

    public function create(){
        $this->getAllPermissions(Auth::user()->id);
        view()->share(['title' => 'Nuevo Grupo de Corte']);
        return view('grupos-corte.create');
    }

    public function store(Request $request){
        $request->validate([
            'nombre' => 'required|max:250',
            'fecha_corte' => 'required|numeric',
            'fecha_suspension' => 'required|numeric',
            'fecha_factura' => 'required|numeric',
            'fecha_pago' => 'required|numeric',
            'hora_suspension' => 'required',
            'periodo_facturacion' => 'required|numeric|in:1,2,3',
        ]);

        $hora_suspension = explode(":", $request->hora_suspension);
        $hora_suspension_limit = $hora_suspension[0]+4;
        $hora_suspension_limit = $hora_suspension_limit.':'.$hora_suspension[1];

        $grupo = new GrupoCorte;
        $grupo->nombre = $request->nombre;
        $grupo->fecha_factura = $request->fecha_factura;
        $grupo->fecha_pago = $request->fecha_pago;
        $grupo->fecha_corte = $request->fecha_corte;
        $grupo->fecha_suspension = $request->fecha_suspension;
        $grupo->hora_suspension = $request->hora_suspension;
        $grupo->hora_suspension_limit = $hora_suspension_limit;
        $grupo->hora_creacion_factura = $request->hora_creacion_factura;
        $grupo->status = $request->status;
        $grupo->prorroga_tv = $request->prorroga_tv ?? 0;
        $grupo->periodo_facturacion = $request->periodo_facturacion;
        $grupo->created_by = Auth::user()->id;
        $grupo->empresa = Auth::user()->empresa;
        $grupo->mes_siguiente = $request->mes_siguiente ?? 0;
        $grupo->save();

        $mensaje='SE HA CREADO SATISFACTORIAMENTE EL GRUPO DE CORTE';
        return redirect('empresa/grupos-corte')->with('success', $mensaje);
    }

    public function storeBack(Request $request){
        $hora_suspension = explode(":", $request->hora_suspension);
        $hora_suspension_limit = $hora_suspension[0]+4;
        $hora_suspension_limit = $hora_suspension_limit.':'.$hora_suspension[1];

        $grupo                   = new GrupoCorte;
        $grupo->nombre           = $request->nombre;
        $grupo->fecha_factura    = $request->fecha_factura;
        $grupo->fecha_pago       = $request->fecha_pago;
        $grupo->fecha_corte      = $request->fecha_corte;
        $grupo->fecha_suspension = $request->fecha_suspension;
        $grupo->hora_suspension  = $request->hora_suspension;
        $grupo->hora_suspension_limit = $hora_suspension_limit;
        $grupo->prorroga_tv = $request->prorroga_tv;
        $grupo->periodo_facturacion = $request->periodo_facturacion ?? 1;
        $grupo->status           = $request->status;
        $grupo->created_by       = Auth::user()->id;
        $grupo->empresa          = Auth::user()->empresa;
        $grupo->mes_siguiente    = $request->mes_siguiente ?? 0;
        $grupo->save();

        if ($grupo) {
            $arrayPost['success']    = true;
            $arrayPost['id']         = GrupoCorte::all()->last()->id;
            $arrayPost['suspension'] = GrupoCorte::all()->last()->fecha_suspension;
            $arrayPost['corte']      = GrupoCorte::all()->last()->fecha_corte;
            $arrayPost['nombre']     = GrupoCorte::all()->last()->nombre;
            echo json_encode($arrayPost);
            exit;
        }
    }

    public function show($id){
        $this->getAllPermissions(Auth::user()->id);
        $grupo = GrupoCorte::find($id);

        if ($grupo) {
            $contratos = Contrato::where('grupo_corte', $grupo->id)->where('empresa', Auth::user()->empresa)->count();
            $tabla = Campos::where('modulo', 2)->where('estado', 1)->where('empresa', Auth::user()->empresa)->orderBy('orden', 'asc')->get();
            view()->share(['title' => $grupo->nombre]);
            return view('grupos-corte.show')->with(compact('grupo', 'contratos', 'tabla'));
        }
        return redirect('empresa/grupos-corte')->with('danger', 'GRUPO DE CORTE NO ENCONTRADO, INTENTE NUEVAMENTE');
    }

    public function edit($id){
        $this->getAllPermissions(Auth::user()->id);
        $grupo = GrupoCorte::find($id);

        if ($grupo) {
            view()->share(['title' => 'Editar: '.$grupo->nombre]);
            return view('grupos-corte.edit')->with(compact('grupo'));
        }
        return redirect('empresa/grupos-corte')->with('danger', 'GRUPO DE CORTE NO ENCONTRADO, INTENTE NUEVAMENTE');
    }

    public function update(Request $request, $id){
        $request->validate([
            'nombre' => 'required|max:250',
            'fecha_corte' => 'required|numeric',
            'fecha_suspension' => 'required|numeric',
            'fecha_factura' => 'required|numeric',
            'fecha_pago' => 'required|numeric',
            'hora_suspension' => 'required',
            'periodo_facturacion' => 'required|numeric|in:1,2,3',
        ]);

        $grupo = GrupoCorte::find($id);

        if ($grupo) {
            $hora_suspension = explode(":", $request->hora_suspension);
            $hora_suspension_limit = $hora_suspension[0]+4;
            $hora_suspension_limit = $hora_suspension_limit.':'.$hora_suspension[1];

            //Si es diferente es por que hubo un cambio y vamos a actualizar la fecha de suspension de las ultimas facturas creadas
            if($grupo->fecha_suspension != $request->fecha_suspension){

                // 1. Identificar la fecha de emisión del último lote de facturas para este grupo
                $ultimaFecha = Factura::join('facturas_contratos as fc', 'fc.factura_id', '=', 'factura.id')
                    ->join('contracts as c', 'c.nro', '=', 'fc.contrato_nro')
                    ->where('c.grupo_corte', $grupo->id)
                    ->max('factura.fecha');

                if ($ultimaFecha) {
                    // 2. Obtener únicamente las facturas pertenecientes a ese último lote
                    $facturasGrupo = Factura::join('facturas_contratos as fc', 'fc.factura_id', '=', 'factura.id')
                        ->join('contracts as c', 'c.nro', '=' ,'fc.contrato_nro')
                        ->select('factura.*')
                        ->where('factura.fecha', $ultimaFecha)
                        ->where('c.grupo_corte', $grupo->id)
                        ->groupBy('factura.id')
                        ->get();

                    foreach($facturasGrupo as $fg){
                        // 3. Conservar el mes y año originales del vencimiento para evitar
                        // dañar facturas que se emitieron para el mes siguiente.
                        if ($fg->vencimiento) {
                            $vencimientoOriginal = Carbon::parse($fg->vencimiento);
                            $year = $vencimientoOriginal->format('Y');
                            $month = $vencimientoOriginal->format('m');
                            
                            // Asegurar que el día no exceda el máximo de días del mes (ej. Febrero 30 -> 28/29)
                            $ultimoDiaMes = Carbon::createFromDate($year, $month, 1)->endOfMonth()->day;
                            $diaSuspension = $request->fecha_suspension > $ultimoDiaMes ? $ultimoDiaMes : $request->fecha_suspension;

                            $nuevaFecha = $year . "-" . $month . "-" . str_pad($diaSuspension, 2, '0', STR_PAD_LEFT);

                            $fg->vencimiento = $nuevaFecha;
                            $fg->suspension = $nuevaFecha;
                            $fg->save();
                        }
                    }
                }
            }

            $grupo->nombre           = $request->nombre;
            $grupo->fecha_factura    = $request->fecha_factura;
            $grupo->fecha_pago       = $request->fecha_pago;
            $grupo->fecha_corte      = $request->fecha_corte;
            $grupo->fecha_suspension = $request->fecha_suspension;
            $grupo->hora_suspension  = $request->hora_suspension;
            $grupo->hora_suspension_limit = $hora_suspension_limit;
            $grupo->hora_creacion_factura = $request->hora_creacion_factura;
            $grupo->status                = $request->status;
            $grupo->prorroga_tv           = $request->prorroga_tv;
            $grupo->periodo_facturacion  = $request->periodo_facturacion;
            $grupo->updated_by            = Auth::user()->id;
            $grupo->nro_factura_vencida = $request->nro_factura_vencida;
            $grupo->mes_siguiente = $request->mes_siguiente ?? 0;
            $grupo->save();

            $mensaje='SE HA MODIFICADO SATISFACTORIAMENTE EL GRUPO DE CORTE';
            return redirect('empresa/grupos-corte')->with('success', $mensaje);
        }
        return redirect('empresa/grupos-corte')->with('danger', 'GRUPO DE CORTE NO ENCONTRADO, INTENTE NUEVAMENTE');
    }

    public function destroy($id){
        $grupo = GrupoCorte::find($id);

        if($grupo){
            $grupo->delete();
            $mensaje = 'SE HA ELIMINADO EL GRUPO DE CORTE CORRECTAMENTE';
            return redirect('empresa/grupos-corte')->with('success', $mensaje);
        }else{
            return redirect('empresa/grupos-corte')->with('danger', 'GRUPO DE CORTE NO ENCONTRADO, INTENTE NUEVAMENTE');
        }
    }

    public function act_des($id){
        $grupo = GrupoCorte::find($id);

        if($grupo){
            if($grupo->status == 0){
                $grupo->status = 1;
                $mensaje = 'SE HA HABILITADO EL GRUPO DE CORTE CORRECTAMENTE';
            }else{
                $grupo->status = 0;
                $mensaje = 'SE HA DESHABILITADO EL GRUPO DE CORTE CORRECTAMENTE';
            }
            $grupo->save();
            return redirect('empresa/grupos-corte')->with('success', $mensaje);
        }else{
            return redirect('empresa/grupos-corte')->with('danger', 'GRUPO DE CORTE NO ENCONTRADO, INTENTE NUEVAMENTE');
        }
    }

    public function state_lote($grupos, $state){
        $this->getAllPermissions(Auth::user()->id);

        $succ = 0; $fail = 0;

        $grupos = explode(",", $grupos);

        for ($i=0; $i < count($grupos) ; $i++) {
            $grupo = GrupoCorte::find($grupos[$i]);

            if($grupo){
                if($state == 'disabled'){
                    $grupo->status = 0;
                }elseif($state == 'enabled'){
                    $grupo->status = 1;
                }
                $grupo->save();
                $succ++;
            }else{
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

    public function destroy_lote($grupos){
        $this->getAllPermissions(Auth::user()->id);

        $succ = 0; $fail = 0;

        $grupos = explode(",", $grupos);

        for ($i=0; $i < count($grupos) ; $i++) {
            $grupo = GrupoCorte::find($grupos[$i]);
            if ($grupo->uso()==0) {
                $grupo->delete();
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

    public function opcion_masiva(){
        $this->getAllPermissions(Auth::user()->id);
        view()->share(['title' => 'Opciones Masivas a Contratos']);
        $grupos_corte = GrupoCorte::get();
        return view('grupos-corte.opcionmasiva',compact('grupos_corte'));
    }

    public function gruposOpcionesMasivas(Request $request){
        $modoLectura = auth()->user()->modo_lectura();
        $grupos = GrupoCorte::query()
            ->where('empresa', Auth::user()->empresa);
        if ($request->filtro == true) {
            if($request->nombre){
                $grupos->where(function ($query) use ($request) {
                    $query->orWhere('nombre', 'like', "%{$request->nombre}%");
                });
            }
            if($request->fecha_factura){
                $grupos->where(function ($query) use ($request) {
                    $query->orWhere('fecha_factura', 'like', "%{$request->fecha_factura}%");
                });
            }
            if($request->fecha_pago){
                $grupos->where(function ($query) use ($request) {
                    $query->orWhere('fecha_pago', 'like', "%{$request->fecha_pago}%");
                });
            }
            if($request->fecha_corte){
                $grupos->where(function ($query) use ($request) {
                    $query->orWhere('fecha_corte', 'like', "%{$request->fecha_corte}%");
                });
            }
            if($request->fecha_suspension){
                $grupos->where(function ($query) use ($request) {
                    $query->orWhere('fecha_suspension', 'like', "%{$request->fecha_suspension}%");
                });
            }
            if($request->status >= 0){
                $grupos->where(function ($query) use ($request) {
                    $query->orWhere('status', 'like', "%{$request->status}%");
                });
            }
        }

        return datatables()->eloquent($grupos)
            ->editColumn('id', function (GrupoCorte $grupo) {
                return $grupo->id;
            })
            ->editColumn('nombre', function (GrupoCorte $grupo) {
                return "<a href=" . route('grupos-corte.show', $grupo->id) . ">{$grupo->nombre}</div></a>";
            })
            ->editColumn('fecha_factura', function (GrupoCorte $grupo) {
                return ($grupo->fecha_factura == 0) ? 'No aplica' : $grupo->fecha_factura;
            })
            ->editColumn('fecha_pago', function (GrupoCorte $grupo) {
                return ($grupo->fecha_pago == 0) ? 'No aplica' : $grupo->fecha_pago;
            })
            ->editColumn('fecha_corte', function (GrupoCorte $grupo) {
                return ($grupo->fecha_corte == 0) ? 'No aplica' : $grupo->fecha_corte;
            })
            ->editColumn('fecha_suspension', function (GrupoCorte $grupo) {
                return ($grupo->fecha_suspension == 0) ? 'No aplica' : $grupo->fecha_suspension;
            })
            ->editColumn('hora_suspension', function (GrupoCorte $grupo) {
                return date('g:i A', strtotime($grupo->hora_suspension));
            })
            ->editColumn('status', function (GrupoCorte $grupo) {
                return "<span class='text-{$grupo->status("true")}'><strong>{$grupo->status()}</strong></span>";
            })
            ->addColumn('acciones', $modoLectura ?  "" : "grupos-corte.acciones")
            ->rawColumns(['acciones', 'nombre', 'id', 'status'])
            ->toJson();
    }

    public function estadosGruposCorte($grupo = null, $fecha = null){

        $this->getAllPermissions(Auth::user()->id);

        view()->share(['inicio' => 'master', 'seccion' => 'zonas', 'subseccion' => 'estados_corte', 'title' => 'Estados de corte', 'icon' => 'fas fa-project-diagram']);

        if($grupo == 'all'){
            $grupo = null;
        }

        if(!$fecha){
            $fecha = date('Y-m-d');
        }

        if($grupo != null){
            $grupoSeleccionado = GrupoCorte::find($grupo);
            $fecha =  date('Y-m').'-'.$grupoSeleccionado->fecha_suspension;
            $fecha = Carbon::create($fecha)->format('Y-m-d');
        }

        $swGrupo = 1; //masivo
        // $grupos_corte = GrupoCorte::where('fecha_suspension', date('d') * 1)->where('hora_suspension','<=', date('H:i'))->where('hora_suspension_limit','>=', date('H:i'))->where('status', 1)->count();
        $grupos_corte = GrupoCorte::where('hora_suspension','<=', date('H:i'))->where('hora_suspension_limit','>=', date('H:i'))->where('status', 1)->where('fecha_suspension','!=',0)->get();
        $perdonados = 0;


        if(false){
            $grupos_corte_array = array();
            foreach($grupos_corte as $grupo){
                array_push($grupos_corte_array,$grupo->id);
            }

            $contactos = Contacto::join('factura as f','f.cliente','=','contactos.id')->
                 join('facturas_contratos as fc','fc.factura_id','=','f.id')->
                 join('contracts as cs' ,'cs.nro','=','fc.contrato_nro')->
                join('grupos_corte as gp', 'gp.id', '=', 'cs.grupo_corte')->
                select('gp.nombre as grupo', 'gp.id as idGrupo', 'contactos.id', 'contactos.nombre', 'contactos.nit', 'f.id as factura', 'f.codigo', 'f.estatus', 'f.suspension', 'cs.state', 'cs.id as contrato_id')->
                where('f.estatus',1)->
                whereIn('f.tipo', [1,2])->
                where('f.vencimiento', $fecha)->
                where('contactos.status',1)->
                where('cs.state','enabled')->
                whereIn('cs.grupo_corte',$grupos_corte_array)->
                where('cs.fecha_suspension', null);

                if($grupo){
                    $contactos->where('gp.id', $grupo);
                }

                $contactos = $contactos->get()->all();
                $swGrupo = 1; //masivo
        }else{
            $contactos = Contacto::join('factura as f','f.cliente','=','contactos.id')->
            join('facturas_contratos as fc','fc.factura_id','=','f.id')->
            join('contracts as cs' ,'cs.nro','=','fc.contrato_nro')->
            join('grupos_corte as gp', 'gp.id', '=', 'cs.grupo_corte')->
            select('gp.nombre as grupo', 'gp.id as idGrupo', 'contactos.id', 'contactos.nombre', 'contactos.nit', 'f.id as factura', 'f.estatus', 'f.suspension', 'f.codigo', 'cs.state', 'cs.id as contrato_id')->
            where('f.estatus',1)->
            whereIn('f.tipo', [1,2])->
            where('f.vencimiento', $fecha)->
            where('contactos.status',1)->
            where('cs.state','enabled')->
            where('cs.fecha_suspension', null);

            if($grupo){
                $contactos->where('gp.id', $grupo);
            }


            $contactos = $contactos->get()->all();
           // dd($contactos);
            $swGrupo = 0; // personalizado
        }

        if($contactos){
            foreach ($contactos as $key => $contacto) {
                $contrato = Contrato::find($contacto->contrato_id);
                $promesaExtendida = DB::table('promesa_pago')->where('factura', $contacto->factura)->where('vencimiento', '>=', $fecha)->count();
                if($promesaExtendida > 0){
                    unset($contactos[$key]);
                    $perdonados++;
                }
            }
        }

        $contactos = collect($contactos);
        $totalFacturas = $contactos->count();
        $contactos = $contactos->groupBy('idGrupo');
        $gruposFaltantes = GrupoCorte::whereIn('id', $contactos->keys())->get();

        $grupos_corte = GrupoCorte::get();

        $facturasCortadas = Factura::select('factura.*', 'contactos.nombre as nombreCliente', 'gp.nombre as nombreGrupo', 'gp.hora_suspension', 'gp.id as idGrupo')->
                                     join('contactos', 'contactos.id', '=', 'factura.cliente')->
                                     join('facturas_contratos as fc','fc.factura_id','=','factura.id') ->
                                     join('contracts as cs' ,'cs.nro','=','fc.contrato_nro')->
                                     join('grupos_corte as gp', 'gp.id', '=', 'cs.grupo_corte')->
                                     where('vencimiento', $fecha)->
                                     where('estatus', 1)->
                                     whereIn('tipo', [1,2])->
                                     where('cs.state','disabled');

        if($grupo){
            $facturasCortadas = $facturasCortadas->where('gp.id', $grupo);
        }


        $facturasCortadas = $facturasCortadas->groupBy('factura.id')->
                                     orderby('id', 'desc')->
                                     get();



        $facturasGeneradas = Factura::select('factura.*', 'contactos.nombre as nombreCliente', 'gp.nombre as nombreGrupo', 'gp.hora_suspension', 'gp.id as idGrupo')->
                                     join('contactos', 'contactos.id', '=', 'factura.cliente')->
                                     join('facturas_contratos as fc','fc.factura_id','=','factura.id') ->
                                     join('contracts as cs','cs.nro','=','fc.contrato_nro')->
                                     join('grupos_corte as gp', 'gp.id', '=', 'cs.grupo_corte')->
                                     where('vencimiento', $fecha)->
                                     whereIn('tipo', [1,2])->
                                     where('factura.facturacion_automatica', 1);

        if($grupo){
            $facturasGeneradas = $facturasGeneradas->where('gp.id', $grupo);
        }


        $facturasGeneradas =  $facturasGeneradas->groupBy('factura.id')->
                                     orderby('id', 'desc')->
                                     get();



        $request = request();

        $cantidadContratos = Contrato::select('contracts.id')
                                        ->join('grupos_corte', 'grupos_corte.id', '=', 'contracts.grupo_corte')
                                        ->where('grupos_corte.fecha_suspension', Carbon::create($fecha)->format('d') * 1)
                                        ->where('grupos_corte.status', 1);


                                        if($grupo){
                                            $cantidadContratos->where('grupos_corte.id', $grupo);
                                        }

        $cantidadContratos = $cantidadContratos->count();

        return view('grupos-corte.estados', compact('contactos', 'gruposFaltantes', 'perdonados', 'grupo', 'fecha', 'totalFacturas', 'grupos_corte', 'facturasCortadas', 'request', 'facturasGeneradas', 'cantidadContratos'));
    }

    /**
     * Vista principal de análisis de ciclos de facturación
     */
    public function analisisCiclo($idGrupo, $periodo = null)
    {
        $this->getAllPermissions(Auth::user()->id);
        
        view()->share([
            'inicio' => 'master', 
            'seccion' => 'zonas', 
            'subseccion' => 'analisis_ciclo', 
            'title' => 'Análisis de Ciclos de Facturación', 
            'icon' => 'fas fa-chart-bar'
        ]);

        $grupo = GrupoCorte::find($idGrupo);
        
        if (!$grupo) {
            return redirect('empresa/grupos-corte')->with('danger', 'GRUPO DE CORTE NO ENCONTRADO');
        }

        // Si no se especifica período, usar el mes actual
        if (!$periodo) {
            $periodo = Carbon::now()->format('Y-m');
        }

        // Actualizar facturas automáticas que deberían ser del mes
        $this->fixFacturasMesManual($idGrupo, $periodo);

        $analyzer = new BillingCycleAnalyzer();
        
        // Obtener estadísticas del ciclo
        $cycleStats = $analyzer->getCycleStats($idGrupo, $periodo);
        
        // Obtener datos históricos para gráficas (últimos 6 meses)
        $historicalData = $analyzer->getHistoricalData($idGrupo, 6);
        
        // Calcular métricas comparativas
        $promedioFacturas = count($historicalData) > 0 
            ? round(collect($historicalData)->avg('generadas'), 2) 
            : 0;
        
        // Variación vs mes anterior
        $variacionMesAnterior = 0;
        if (count($historicalData) >= 2) {
            $mesActual = end($historicalData);
            $mesAnterior = $historicalData[count($historicalData) - 2];
            
            if ($mesAnterior['generadas'] > 0) {
                $variacionMesAnterior = round(
                    (($mesActual['generadas'] - $mesAnterior['generadas']) / $mesAnterior['generadas']) * 100, 
                    2
                );
            }
        }

        // Obtener lista completa de grupos para la navegación (incluye deshabilitados)
        $grupos = GrupoCorte::where('empresa', Auth::user()->empresa)
            ->orderBy('status', 'desc') // Habilitados primero
            ->orderBy('nombre')
            ->get();

        // Empresa
        $empresa = Empresa::find(Auth::user()->empresa);

        // Obtener contratos deshabilitados elegibles para habilitación
        $contratosDeshabilitados = $this->getContratosDeshabilitadosElegibles($idGrupo);

        return view('grupos-corte.analisis-ciclo', compact(
            'grupo', 
            'periodo', 
            'cycleStats', 
            'historicalData', 
            'promedioFacturas', 
            'variacionMesAnterior',
            'grupos',
            'empresa',
            'contratosDeshabilitados'
        ));
    }

    /**
     * API: Obtiene lista de ciclos disponibles para el selector
     */
    public function getCiclosDisponibles($idGrupo)
    {
        $analyzer = new BillingCycleAnalyzer();
        $ciclos = $analyzer->getAvailableCycles($idGrupo);
        
        return response()->json([
            'success' => true,
            'ciclos' => $ciclos
        ]);
    }

    /**
     * API: Obtiene datos completos de un ciclo específico
     */
    public function getCycleData($idGrupo, $periodo)
    {
        $analyzer = new BillingCycleAnalyzer();
        
        // Estadísticas del ciclo
        $cycleStats = $analyzer->getCycleStats($idGrupo, $periodo);
        
        // Datos históricos
        $historicalData = $analyzer->getHistoricalData($idGrupo, 6);
        
        // Métricas comparativas
        $promedioFacturas = count($historicalData) > 0 
            ? round(collect($historicalData)->avg('generadas'), 2) 
            : 0;
        
        $variacionMesAnterior = 0;
        if (count($historicalData) >= 2) {
            $ultimoIndice = count($historicalData) - 1;
            $mesActual = $historicalData[$ultimoIndice];
            $mesAnterior = $historicalData[$ultimoIndice - 1];
            
            if ($mesAnterior['generadas'] > 0) {
                $variacionMesAnterior = round(
                    (($mesActual['generadas'] - $mesAnterior['generadas']) / $mesAnterior['generadas']) * 100, 
                    2
                );
            }
        }
        
        return response()->json([
            'success' => true,
            'cycleStats' => $cycleStats,
            'historicalData' => $historicalData,
            'metricas' => [
                'promedio_facturas' => $promedioFacturas,
                'variacion_mes_anterior' => $variacionMesAnterior
            ]
        ]);
    }

    /**
     * Habilitar la facturación para contratos OFF
     */
    public function habilitarFacturacionOff()
    {
        $empresa = Empresa::find(1);
        $empresa->factura_contrato_off = 1;
        $empresa->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Configuración de empresa actualizada: Ahora se permiten facturas en contratos deshabilitados.'
        ]);
    }

    /**
     * Generar facturas faltantes de forma manual
     */
    public function generarFacturasFaltantes(Request $request)
    {
        $idGrupo = $request->idGrupo;
        $periodo = $request->periodo;
        
        if (!$idGrupo || !$periodo) {
            return response()->json(['success' => false, 'message' => 'Faltan parámetros requeridos.'], 400);
        }

        $grupo = GrupoCorte::find($idGrupo);
        if (!$grupo) {
            return response()->json(['success' => false, 'message' => 'Grupo no encontrado.'], 404);
        }

        list($year, $month) = explode('-', $periodo);
        $dia = $grupo->fecha_factura;
        if ($dia == 0) $dia = 1;
        
        $ultimoDiaMes = Carbon::create($year, $month, 1)->endOfMonth()->day;
        if ($dia > $ultimoDiaMes) $dia = $ultimoDiaMes;
        
        $fechaRef = Carbon::create($year, $month, $dia)->format('Y-m-d');
        
        try {
            CronController::CrearFactura($fechaRef, $idGrupo);
            
            // Invalidar caché
            // Invalidar caché usando el analyzer (Re- aplicado)
            $analyzer = new \App\Services\BillingCycleAnalyzer();
            $analyzer->clearCycleCache($idGrupo, $periodo);
            
            return response()->json([
                'success' => true, 
                'message' => 'Proceso de generación de facturas finalizado para el grupo ' . $grupo->nombre
            ]);
        } catch (\Exception $e) {
            Log::error("Error en generación manual de facturas: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false, 
                'message' => 'Ocurrió un error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar configuración de la empresa de forma genérica
     */
    public function updateEmpresaConfig(Request $request)
    {
        $field = $request->field;
        $value = $request->value;
        
        $validFields = [
            'factura_auto', 
            'aplicar_saldofavor', 
            'cron_fact_abiertas', 
            'factura_contrato_off', 
            'prorrateo',
            'contrato_factura_pro'
        ];
        
        if (!in_array($field, $validFields)) {
            return response()->json(['success' => false, 'message' => 'Campo no válido.'], 400);
        }
        
        $empresa = Empresa::find(Auth::user()->empresa);
        $empresa->$field = $value;
        $empresa->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Configuración actualizada correctamente.'
        ]);
    }

    /**
     * Marcar en lote las facturas manuales como "Factura del Mes"
     */
    public function marcarFacturasMesLote(Request $request)
    {
        $idGrupo = $request->idGrupo;
        $periodo = $request->periodo;
        
        if (!$idGrupo || !$periodo) {
            return response()->json(['success' => false, 'message' => 'Faltan parámetros requeridos.'], 400);
        }

        try {
            $analyzer = new \App\Services\BillingCycleAnalyzer();
            $marcadas = $analyzer->marcarFacturasMesLote($idGrupo, $periodo);
            
            // Invalidar caché
            $analyzer->clearCycleCache($idGrupo, $periodo);
            
            return response()->json([
                'success' => true, 
                'message' => "Se han vinculado {$marcadas} facturas manuales al ciclo actual correctamente."
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error en marcado manual de facturas: " . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Ocurrió un error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar contratos para que generen factura en su primer ciclo
     */
    public function actualizarContratosPrimerMes(Request $request)
    {
        $idGrupo = $request->idGrupo;
        $periodo = $request->periodo;
        
        if (!$idGrupo || !$periodo) {
            return response()->json(['success' => false, 'message' => 'Faltan parámetros requeridos.'], 400);
        }

        try {
            $analyzer = new \App\Services\BillingCycleAnalyzer();
            $marcados = $analyzer->actualizarContratosPrimerMes($idGrupo, $periodo);
            
            // Invalidar caché
            $analyzer->clearCycleCache($idGrupo, $periodo);
            
            return response()->json([
                'success' => true, 
                'message' => "Se han actualizado {$marcados} contratos para que generen factura en el primer mes del ciclo."
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error actualizando contratos primer mes: " . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Ocurrió un error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar facturas duplicadas de manera segura
     */
    public function eliminarFacturaDuplicada(Request $request)
    {
        $facturaId = $request->factura_id;

        if (!$facturaId) {
            return response()->json([
                'success' => false,
                'message' => 'ID de factura requerido'
            ], 400);
        }

        try {
            $factura = Factura::find($facturaId);
            
            if (!$factura) {
                return response()->json([
                    'success' => false,
                    'message' => 'Factura no encontrada'
                ], 404);
            }

            // Validar que la factura no tenga pagos asociados
            if ($factura->pagado() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar una factura con pagos asociados'
                ], 400);
            }

            // Eliminar dependencias
            DB::table('facturas_contratos')->where('factura_id', $factura->id)->delete();
            ItemsFactura::where('factura', $factura->id)->delete();
            DB::table('crm')->where('factura', $factura->id)->delete();

            // Eliminar factura en OnePay si existe
            if ($factura->onepay_invoice_id) {
                try {
                    $empresa_id = \Illuminate\Support\Facades\Auth::user() ? \Illuminate\Support\Facades\Auth::user()->empresa : $factura->empresa;
                    $onePayService = new \App\Services\OnePayService($empresa_id);
                    $onePayService->deleteInvoice($factura);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Error al eliminar factura en OnePay: ' . $e->getMessage(), [
                        'factura_id' => $factura->id,
                        'empresa_id' => $empresa_id
                    ]);
                }
            }

            // Eliminar factura
            $factura->delete();

            // Limpiar caché
            if ($request->has('idGrupo') && $request->has('periodo')) {
                $analyzer = new \App\Services\BillingCycleAnalyzer();
                $analyzer->clearCycleCache($request->idGrupo, $request->periodo);
            }

            return response()->json([
                'success' => true,
                'message' => 'Factura eliminada correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar factura duplicada: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar masivamente todas las facturas duplicadas de un ciclo
     */
    public function eliminarMasivoDuplicados(Request $request)
    {
        $idGrupo = $request->idGrupo;
        $periodo = $request->periodo;
        $contratoId = $request->contrato_id; // Opcional: eliminar solo duplicados de un contrato específico

        if (!$idGrupo || !$periodo) {
            return response()->json([
                'success' => false,
                'message' => 'Faltan parámetros requeridos'
            ], 400);
        }

        try {
            $analyzer = new \App\Services\BillingCycleAnalyzer();
            $cycleStats = $analyzer->getCycleStats($idGrupo, $periodo);
            
            if (!isset($cycleStats['duplicates_analysis']) || 
                $cycleStats['duplicates_analysis']['total_excedentes'] == 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'No se encontraron facturas duplicadas para eliminar',
                    'eliminadas' => 0
                ]);
            }

            $eliminadas = 0;
            $noPudoEliminar = 0;
            $contratos_duplicados = $cycleStats['duplicates_analysis']['contratos_duplicados'];

            // Si se especifica un contrato, filtrar solo ese
            if ($contratoId) {
                $contratos_duplicados = array_filter($contratos_duplicados, function($dup) use ($contratoId) {
                    return $dup['contrato_id'] == $contratoId;
                });
            }

            foreach ($contratos_duplicados as $dup) {
                // Ordenar facturas para determinar cuál conservar (Índice 0)
                // Criterios:
                // 1. Estatus: Preferir facturas NO anuladas (estatus != 2)
                // 2. Fecha: Preferir la más reciente
                // 3. ID: En caso de empate, preferir ID mayor
                $facturas = collect($dup['facturas'])->sort(function($a, $b) {
                    $aAnulada = (isset($a['estatus']) && $a['estatus'] == 2);
                    $bAnulada = (isset($b['estatus']) && $b['estatus'] == 2);
                    
                    // Si una está anulada y la otra no, la NO anulada va primero
                    if ($aAnulada !== $bAnulada) {
                        return $aAnulada ? 1 : -1;
                    }
                    
                    // Si ambas tienen mismo status, la más reciente va primero
                    if ($a['fecha'] != $b['fecha']) {
                        return ($a['fecha'] > $b['fecha']) ? -1 : 1;
                    }
                    
                    return $b['id'] - $a['id'];
                })->values();
                
                // Saltar la primera (más reciente, la mantenemos)
                for ($i = 1; $i < $facturas->count(); $i++) {
                    $facturaId = $facturas[$i]['id'];
                    $factura = Factura::find($facturaId);
                    
                    if (!$factura) {
                        continue;
                    }

                    // Validar que no tenga pagos
                    if ($factura->pagado() > 0) {
                        $noPudoEliminar++;
                        continue;
                    }

                    // Eliminar dependencias
                    DB::table('facturas_contratos')->where('factura_id', $factura->id)->delete();
                    ItemsFactura::where('factura', $factura->id)->delete();
                    DB::table('crm')->where('factura', $factura->id)->delete();

                    // Eliminar factura en OnePay si existe
                    if ($factura->onepay_invoice_id) {
                        try {
                            $empresa_id = \Illuminate\Support\Facades\Auth::user() ? \Illuminate\Support\Facades\Auth::user()->empresa : $factura->empresa;
                            $onePayService = new \App\Services\OnePayService($empresa_id);
                            $onePayService->deleteInvoice($factura);
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error('Error al eliminar factura en OnePay: ' . $e->getMessage(), [
                                'factura_id' => $factura->id,
                                'empresa_id' => $empresa_id
                            ]);
                        }
                    }

                    // Eliminar factura
                    $factura->delete();
                    $eliminadas++;
                }
            }

            // Limpiar caché
            $analyzer->clearCycleCache($idGrupo, $periodo);

            $mensaje = "Se eliminaron {$eliminadas} facturas duplicadas correctamente";
            if ($noPudoEliminar > 0) {
                $mensaje .= ". {$noPudoEliminar} facturas no se pudieron eliminar por tener pagos asociados";
            }

            return response()->json([
                'success' => true,
                'message' => $mensaje,
                'eliminadas' => $eliminadas,
                'no_pudieron_eliminar' => $noPudoEliminar
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar masivamente duplicados: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Limpiar caché de un ciclo específico
     */
    public function limpiarCacheCiclo(Request $request)
    {
        $idGrupo = $request->idGrupo;
        $periodo = $request->periodo;
        
        if (!$idGrupo || !$periodo) {
            return response()->json([
                'success' => false,
                'message' => 'Faltan parámetros requeridos'
            ], 400);
        }
        
        $analyzer = new \App\Services\BillingCycleAnalyzer();
        $analyzer->clearCycleCache($idGrupo, $periodo);
        
        return response()->json([
            'success' => true,
            'message' => 'Caché limpiado correctamente'
        ]);
    }

    /**
     * DataTables para facturas generadas (Server Side)
     */
    public function datatableGeneratedInvoices(Request $request)
    {
        $idGrupo = $request->grupo_id;
        $periodo = $request->periodo;
        // Obtener término de búsqueda
        $search = $request->input('search.value');

        $analyzer = new \App\Services\BillingCycleAnalyzer();
        // Obtener el query builder (Union), pasando el término de búsqueda para que se aplique internamente
        $query = $analyzer->getGeneratedInvoicesQuery($idGrupo, $periodo, $search);

        if (!$query) {
             return response()->json([
                'draw' => intval($request->draw),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => []
            ]);
        }

        // Para filtrar/ordenar sobre un UNION en Laravel, usamos un subquery
        $sql = $query->toSql();
        $bindings = $query->getBindings();
        
        $wrappedQuery = DB::table(DB::raw("({$sql}) as sub"))
            ->mergeBindings($query->getQuery()); // Trick for eloquent union bindings

        // Nota: Como el filtro de búsqueda se aplica dentro del query (por performance y corrección lógica),
        // totalRecords aquí reflejará los registros YA filtrados. 
        // Si se requeriera el total real sin filtro, habría que ejecutar el query sin search, pero sería doble costo.
        $filteredRecords = $wrappedQuery->count();
        $totalRecords = $filteredRecords;

        // Búsqueda (ELIMINADA: Se maneja dentro del servicio para soportar UNION correctamente)


        // Ordenamiento
        // Nota: 'total' (índice 7) se calcula en PHP, no se puede ordenar por SQL fácilmente.
        $columns = ['codigo', 'nombre_cliente', 'nit_cliente', 'contrato_nro', 'fecha', 'vencimiento', 'factura_mes_manual', null, 'whatsapp', 'estatus'];
        if ($request->has('order') && isset($request->order[0])) {
            $colIndex = $request->order[0]['column'];
            $dir = $request->order[0]['dir'];
            if (isset($columns[$colIndex]) && $columns[$colIndex] !== null) {
                $wrappedQuery->orderBy($columns[$colIndex], $dir);
            }
        } else {
            $wrappedQuery->orderBy('fecha', 'desc');
        }

        // Paginación
        $start = $request->start ?? 0;
        $length = $request->length ?? 10;
        
        $data = $wrappedQuery->skip($start)->take($length)->get();

        $mappedData = [];
        foreach ($data as $row) {
            // Calcular total desde el modelo
            // Nota: Esto hace 1 query extra por fila, pero es aceptable para paginación (10-25 items)
            $facturaModel = \App\Model\Ingresos\Factura::find($row->id);
            $total = $facturaModel ? ($facturaModel->totalAPI(1)->total ?? 0) : 0;

            // Procesar columnas
            
            // Fecha formato
            $fecha = \Carbon\Carbon::parse($row->fecha)->format('d-m-Y');
            
            // Vencimiento con alerta
            $venc = \Carbon\Carbon::parse($row->vencimiento);
            $isOverdue = $venc->isPast() || $venc->isToday();
            $vencHtml = $isOverdue ? '<span class="text-danger font-weight-bold">'.$venc->format('d-m-Y').'</span>' : $venc->format('d-m-Y');
            
            // Factura Mes Manual
            $mesManualHtml = ($row->factura_mes_manual == 1) 
                ? '<div class="text-center"><span class="badge badge-success">Si</span></div>' 
                : '<div class="text-center"><span class="badge badge-danger">No</span></div>';

            // Whatsapp
            $wppHtml = ($row->whatsapp == 1) 
                ? '<i class="fab fa-whatsapp text-success fa-lg" title="Enviado"></i>'
                : '<i class="fab fa-whatsapp text-secondary fa-lg" title="No enviado"></i>';
                
            // Estado
            $estadoHtml = ($row->estatus == 1)
                ? '<span class="badge badge-success">Abierta</span>'
                : '<span class="badge badge-secondary">Cerrada</span>';
                
            // Acciones
            $accionesHtml = '<a href="'.route('facturas.show', $row->id).'" target="_blank" class="btn btn-sm btn-outline-info"><i class="fas fa-eye"></i></a>';

            $mappedData[] = [
                '<a href="'.route('facturas.show', $row->id).'" target="_blank" class="font-weight-bold">'.($row->codigo ?? $row->nro).'</a>',
                '<a href="'.route('contactos.show', $row->cliente).'" target="_blank">'.$row->nombre_cliente.'</a>',
                $row->nit_cliente,
                $row->contrato_nro,
                $fecha,
                $vencHtml,
                $mesManualHtml,
                '$' . number_format($total, 0, ',', '.'),
                '<div class="text-center">'.$wppHtml.'</div>',
                $estadoHtml,
                $accionesHtml
            ];
        }

        return response()->json([
            'draw' => intval($request->draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $mappedData
        ]);
    }

    /**
     * Actualiza factura_mes_manual = 1 para facturas automáticas del grupo y periodo
     * que no tengan el flag establecido.
     */
    private function fixFacturasMesManual($idGrupo, $periodo)
    {
        try {
            // Asegurar que el periodo sea válido
            $fecha = Carbon::createFromFormat('Y-m', $periodo);
            
            // Obtener IDs de facturas a actualizar
            // Buscamos por fecha de factura en el mes, O por fecha de suspensión en el mes
            // Esto cubre facturas generadas a finales del mes anterior para el ciclo actual
            $ids = Factura::join('facturas_contratos as fc', 'fc.factura_id', '=', 'factura.id')
                ->join('contracts', 'contracts.nro', '=', 'fc.contrato_nro')
                ->where('contracts.grupo_corte', $idGrupo)
                ->where(function($query) use ($fecha) {
                    $query->where(function($q) use ($fecha) {
                        $q->whereYear('factura.fecha', $fecha->year)
                          ->whereMonth('factura.fecha', $fecha->month);
                    })
                    ->orWhere(function($q) use ($fecha) {
                        $q->whereYear('factura.suspension', $fecha->year)
                          ->whereMonth('factura.suspension', $fecha->month);
                    });
                })
                ->where('factura.facturacion_automatica', 1)
                ->whereNull('factura.factura_mes_manual')
                ->groupBy('factura.id')
                ->pluck('factura.id');
            
            if ($ids->count() > 0) {
                Factura::whereIn('id', $ids)->update(['factura_mes_manual' => 1]);
                
                // Limpiar caché
                $analyzer = new \App\Services\BillingCycleAnalyzer();
                $analyzer->clearCycleCache($idGrupo, $periodo);
            }
                
        } catch (\Exception $e) {
            Log::error("Error updating factura_mes_manual: " . $e->getMessage());
        }
    }
    /**
     * Eliminar todas las facturas de un ciclo de facturación
     */
    public function eliminarFacturasCiclo(Request $request)
    {
        $idGrupo = $request->idGrupo;
        $periodo = $request->periodo;
        $empresa = Auth::user()->empresa;

        if (!$idGrupo || !$periodo) {
            return response()->json(['success' => false, 'message' => 'Faltan parámetros requeridos.'], 400);
        }

        try {
            DB::beginTransaction();

            $analyzer = new BillingCycleAnalyzer();
            // Obtenemos las facturas usando el query builder del analyzer
            $facturas = $analyzer->getGeneratedInvoicesQuery($idGrupo, $periodo)->get()
                ->where('factura_mes_manual', 1)
                ->where('facturacion_automatica', 1);

            if ($facturas->count() == 0) {
                return response()->json(['success' => false, 'message' => 'No se encontraron facturas para eliminar en este ciclo.'], 400);
            }

            // 1. Procesamiento y Eliminación
            $count = 0;
            $errores = [];
            $tiposNumeracionAfectados = [];

            foreach ($facturas as $f) {
                $factura = Factura::find($f->id);
                if (!$factura) continue;

                // Validaciones de bloqueo
                $bloqueo = null;
                if ($factura->emitida == 1) {
                    $bloqueo = "Factura {$factura->codigo} ya fue emitida a la DIAN.";
                } elseif ($factura->pagado() != 0) {
                    $bloqueo = "Factura {$factura->codigo} tiene pagos registrados.";
                }

                if ($bloqueo) {
                    $errores[] = $bloqueo;
                    continue;
                }

                // Guardar tipo para reset de numeración
                if ($factura->numeracion && !in_array($factura->numeracion, $tiposNumeracionAfectados)) {
                    $tiposNumeracionAfectados[] = $factura->numeracion;
                }

                // Borrado de dependencias
                DB::table('facturas_contratos')->where('factura_id', $factura->id)->delete();
                ItemsFactura::where('factura', $factura->id)->delete();
                DB::table('crm')->where('factura', $factura->id)->delete();
                
                // Eliminar factura en OnePay si existe
                if ($factura->onepay_invoice_id) {
                    try {
                        $onePayService = new \App\Services\OnePayService($empresa);
                        $onePayService->deleteInvoice($factura);
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error('Error al eliminar factura en OnePay: ' . $e->getMessage(), [
                            'factura_id' => $factura->id,
                            'empresa_id' => $empresa
                        ]);
                    }
                }
                
                // Borrado de la factura
                $factura->delete();
                $count++;
            }

            if ($count == 0 && count($errores) > 0) {
                DB::rollBack();
                // Retornar solo los primeros 5 errores para no saturar
                $mensaje = "No se pudo eliminar ninguna factura. Se encontraron " . count($errores) . " bloqueos. <br>" . implode("<br>", array_slice($errores, 0, 5));
                if (count($errores) > 5) $mensaje .= "<br>...";
                
                return response()->json(['success' => false, 'message' => $mensaje], 400);
            }

            // 2. Reset de Numeración
            if ($count > 0) {
                $numeracionesAfectadas = array_unique($tiposNumeracionAfectados);
                
                foreach ($numeracionesAfectadas as $numeracionId) {
                    $numeracion = NumeracionFactura::find($numeracionId);
                    if ($numeracion) {
                        $prefijo = $numeracion->prefijo ?? '';
                        
                        // Buscar el máximo número (consecutivo) actualmente en uso para esta resolución
                        // Se extrae del campo 'codigo' quitando el prefijo
                        $maxNumero = Factura::where('empresa', $empresa)
                            ->where('numeracion', $numeracionId)
                            ->selectRaw("MAX(CAST(REPLACE(codigo, ?, '') AS UNSIGNED)) as max_nro", [$prefijo])
                            ->value('max_nro');
                        
                        if ($maxNumero) {
                             $numeracion->inicio = $maxNumero + 1;
                             $numeracion->save();
                        }
                    }
                }
            }

            DB::commit();

            // Limpiar caché
            $analyzer->clearCycleCache($idGrupo, $periodo);

            $mensajeFinal = "Se eliminaron {$count} facturas correctamente.";
            if (count($errores) > 0) {
                $mensajeFinal .= " Se saltaron " . count($errores) . " facturas por presentar bloqueos.";
            }

            return response()->json([
                'success' => true,
                'message' => $mensajeFinal
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error eliminando ciclo: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error fatal: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtener contratos deshabilitados elegibles para habilitación
     * (status = 1, state = 'disabled', con última factura cerrada o sin facturas)
     */
    private function getContratosDeshabilitadosElegibles($idGrupo)
    {
        $empresa = Auth::user()->empresa;
        
        // Query optimizada: obtener contratos con su última factura en una sola consulta
        // Aseguramos que el cliente exista con el inner join a contactos
        $contratos = DB::table('contracts')
            ->join('contactos as c', 'c.id', '=', 'contracts.client_id')
            ->leftJoin(DB::raw('(SELECT f1.contrato_id, f1.id as factura_id, f1.codigo as factura_codigo, f1.fecha as factura_fecha, f1.estatus as factura_estatus FROM factura f1 INNER JOIN (SELECT contrato_id, MAX(id) as max_id FROM factura GROUP BY contrato_id) f2 ON f1.id = f2.max_id) as uf'), 'contracts.id', '=', 'uf.contrato_id')
            ->where('contracts.grupo_corte', $idGrupo)
            ->where('contracts.empresa', $empresa)
            ->where('contracts.status', 1)
            ->where('contracts.state', 'disabled')
            ->select('contracts.id', 'contracts.nro', 'contracts.client_id', 'contracts.servicio', 'uf.factura_id', 'uf.factura_codigo', 'uf.factura_fecha', 'uf.factura_estatus')
            ->get();

        $conFacturaCerrada = [];
        $sinFactura = [];

        foreach ($contratos as $contrato) {
            if (!$contrato->factura_id) {
                $sinFactura[] = [
                    'id' => $contrato->id,
                    'nro' => $contrato->nro,
                    'cliente_id' => $contrato->client_id,
                    'servicio' => $contrato->servicio
                ];
            } elseif ($contrato->factura_estatus == 0) {
                $conFacturaCerrada[] = [
                    'id' => $contrato->id,
                    'nro' => $contrato->nro,
                    'cliente_id' => $contrato->client_id,
                    'servicio' => $contrato->servicio,
                    'ultima_factura' => [
                        'id' => $contrato->factura_id,
                        'codigo' => $contrato->factura_codigo,
                        'fecha' => $contrato->factura_fecha
                    ]
                ];
            }
        }

        return [
            'con_factura_cerrada' => $conFacturaCerrada,
            'sin_factura' => $sinFactura,
            'total' => count($conFacturaCerrada) + count($sinFactura)
        ];
    }

    /**
     * API: Habilitar masivamente contratos deshabilitados con última factura cerrada o sin facturas
     */
    public function habilitarContratosDeshabilitados(Request $request)
    {
        $idGrupo = $request->idGrupo;

        if (!$idGrupo) {
            return response()->json(['success' => false, 'message' => 'Falta el ID del grupo.'], 400);
        }

        try {
            $datos = $this->getContratosDeshabilitadosElegibles($idGrupo);

            if ($datos['total'] == 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'No hay contratos elegibles para habilitar.',
                    'habilitados' => 0
                ]);
            }

            $habilitados = 0;
            $errores = 0;
            $empresa = Auth::user()->empresa();

            // Combinar ambas listas
            $contratosParaHabilitar = array_merge(
                $datos['con_factura_cerrada'],
                $datos['sin_factura']
            );

            foreach ($contratosParaHabilitar as $contratoData) {
                $contrato = Contrato::find($contratoData['id']);
                
                if (!$contrato) {
                    $errores++;
                    continue;
                }

                // Habilitar el contrato (simplificado, sin interacción MikroTik)
                // El método state() del ContratosController es un toggle y requiere conexión MikroTik
                // Aquí hacemos una habilitación directa solo a nivel de base de datos
                if ($empresa && $empresa->consultas_mk == 1 && $contrato->server_configuration_id) {
                    // Si la empresa tiene integración MikroTik, usamos el proceso completo
                    // pero lo hacemos directo aquí para evitar redirecciones HTTP
                    $mikrotik = \App\Mikrotik::find($contrato->server_configuration_id);
                    
                    if ($mikrotik) {
                        $API = new \PEAR2\Net\RouterOS\Client($mikrotik->ip, $mikrotik->usuario, $mikrotik->clave);
                        
                        try {
                            // Intentar eliminar de morosos
                            $request = new \PEAR2\Net\RouterOS\Request('/ip/firewall/address-list/print');
                            $request->setArgument('?address', $contrato->ip);
                            $request->setArgument('?list', 'morosos');
                            
                            $responses = $API->sendSync($request);
                            
                            foreach ($responses as $response) {
                                if ($response->getType() === \PEAR2\Net\RouterOS\Response::TYPE_DATA) {
                                    $removeRequest = new \PEAR2\Net\RouterOS\Request('/ip/firewall/address-list/remove');
                                    $removeRequest->setArgument('.id', $response->getProperty('.id'));
                                    $API->sendSync($removeRequest);
                                }
                            }

                            // Agregar a IPs autorizadas
                            $addRequest = new \PEAR2\Net\RouterOS\Request('/ip/firewall/address-list/add');
                            $addRequest->setArgument('address', $contrato->ip);
                            $addRequest->setArgument('list', 'ips_autorizadas');
                            $API->sendSync($addRequest);
                        } catch (\Exception $mkException) {
                            Log::warning("Error MikroTik al habilitar contrato {$contrato->nro}: " . $mkException->getMessage());
                        }
                    }
                }

                // Actualizar estado en BD
                $contrato->state = 'enabled';
                $contrato->save();

                // Registrar en log
                $movimiento = new \App\MovimientoLOG;
                $movimiento->contrato = $contrato->id;
                $movimiento->modulo = 5;
                $movimiento->descripcion = '<i class="fas fa-check text-success"></i> <b>Habilitación Masiva</b> desde Análisis de Ciclo<br>';
                $movimiento->created_by = Auth::user()->id;
                $movimiento->empresa = Auth::user()->empresa;
                $movimiento->save();

                $habilitados++;
            }

            return response()->json([
                'success' => true,
                'message' => "Se habilitaron {$habilitados} contratos correctamente" . ($errores > 0 ? ". {$errores} contratos no pudieron ser procesados." : "."),
                'habilitados' => $habilitados,
                'errores' => $errores
            ]);

        } catch (\Exception $e) {
            Log::error("Error habilitando contratos masivamente: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }
}
