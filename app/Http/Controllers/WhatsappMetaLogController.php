<?php

namespace App\Http\Controllers;

use App\WhatsappMetaLog;
use App\Plantilla;
use App\Contacto;
use App\Model\Ingresos\Factura;
use App\Model\Ingresos\Ingreso;
use App\Contrato;
use App\Instance;
use App\Services\WhatsAppMessageSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WhatsappMetaLogController extends Controller
{
    /**
     * @var WhatsAppMessageSyncService
     */
    protected $syncService;

    public function __construct(WhatsAppMessageSyncService $syncService){
        $this->middleware('auth');
        $this->syncService = $syncService;
        view()->share(['seccion' => 'Meta', 'title' => 'Meta', 'icon' =>'fas fa-plus', 'subseccion' => 'logs']);
    }


    public function index()
    {
        $this->getAllPermissions(Auth::user()->id);

        view()->share(['title' => 'Logs de Envío WhatsApp Meta', 'icon' => 'fas fa-file-alt']);

        // Obtener plantillas tipo 3 para el filtro
        $plantillas = Plantilla::where('tipo', 3)
            ->where('status', 1)
            ->where('empresa', Auth::user()->empresa)
            ->orderBy('title', 'ASC')
            ->get();

        // Fechas predeterminadas (mes actual)
        $fechaDesde = Carbon::now()->startOfMonth()->format('Y-m-d');
        $fechaHasta = Carbon::now()->endOfMonth()->format('Y-m-d');

        // Obtener contactos para el filtro
        $contactos = Contacto::where('empresa', Auth::user()->empresa)
            ->where('status', 1)
            ->orderBy('nombre', 'ASC')
            ->orderBy('apellido1', 'ASC')
            // ->limit(500)
            ->get(['id', 'nombre', 'apellido1', 'apellido2', 'nit']);

        return view('whatsapp-meta-logs.index', compact('plantillas', 'fechaDesde', 'fechaHasta', 'contactos'));
    }

    public function datatable(Request $request)
    {
        $this->getAllPermissions(Auth::user()->id);

        $empresaId = Auth::user()->empresa;
        $empresa = Auth::user()->empresa();

        // Antes de construir el datatable, sincronizar con la API central para el rango de fechas solicitado
        try {
            // Buscar una instancia activa asociada a esta empresa
            $instance = Instance::where('company_id', $empresaId)
                ->whereNotNull('phone_number_id')
                ->first();

            if ($instance && $empresa && $empresa->nit) {
                $fechaDesde = $request->get('fecha_desde') ?: Carbon::now()->startOfMonth()->format('Y-m-d');
                $fechaHasta = $request->get('fecha_hasta') ?: Carbon::now()->endOfMonth()->format('Y-m-d');

                $this->syncService->syncForInstanceAndCompany(
                    $instance,
                    (int) $empresa->nit,
                    $fechaDesde,
                    $fechaHasta
                );
            }
        } catch (\Exception $e) {
            // No interrumpir el datatable si falla la sincronización, solo loguear
            \Log::error('Error sincronizando whatsapp_meta_logs desde datatable', [
                'empresa_id' => $empresaId,
                'error' => $e->getMessage(),
            ]);
        }

        // Construir query
        $logs = WhatsappMetaLog::select(
                'log_meta.*',
                'contactos.nombre as contacto_nombre',
                'contactos.apellido1 as contacto_apellido1',
                'contactos.apellido2 as contacto_apellido2',
                'plantillas.title as plantilla_title',
                DB::raw('COALESCE(factura.codigo, factura_old.codigo) as factura_codigo'),
                DB::raw('COALESCE(factura.emitida, factura_old.emitida) as factura_emitida'),
                'ingresos.nro as ingreso_nro',
                'contracts.nro as contrato_nro',
                'usuarios.nombres as usuario_nombres'
            )
            ->leftJoin('contactos', 'log_meta.contacto_id', '=', 'contactos.id')
            ->leftJoin('plantillas', 'log_meta.plantilla_id', '=', 'plantillas.id')
            ->leftJoin('factura', 'log_meta.incoming_invoice_id', '=', 'factura.id')
            ->leftJoin('factura as factura_old', 'log_meta.factura_id', '=', 'factura_old.id')
            ->leftJoin('ingresos', 'log_meta.incoming_payment_id', '=', 'ingresos.id')
            ->leftJoin('contracts', 'log_meta.incoming_contract_id', '=', 'contracts.id')
            ->leftJoin('usuarios', 'log_meta.enviado_por', '=', 'usuarios.id')
            ->where('log_meta.empresa', $empresaId);

        // Filtro por plantilla
        if ($request->has('plantilla_id') && $request->plantilla_id != '') {
            $logs->where('log_meta.plantilla_id', $request->plantilla_id);
        }

        // Filtro por cliente
        if ($request->has('contacto_id') && $request->contacto_id != '') {
            $logs->where('log_meta.contacto_id', $request->contacto_id);
        }

        // Filtro por fecha desde
        if ($request->has('fecha_desde') && $request->fecha_desde != '') {
            $logs->whereDate('log_meta.created_at', '>=', $request->fecha_desde);
        } else {
            // Predeterminado: inicio del mes actual
            $logs->whereDate('log_meta.created_at', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'));
        }

        // Filtro por fecha hasta
        if ($request->has('fecha_hasta') && $request->fecha_hasta != '') {
            $logs->whereDate('log_meta.created_at', '<=', $request->fecha_hasta);
        } else {
            // Predeterminado: fin del mes actual
            $logs->whereDate('log_meta.created_at', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'));
        }

        // Filtro por factura emitida
        if ($request->has('factura_emitida') && $request->factura_emitida != 'ambas') {
            if ($request->factura_emitida == 'si') {
                $logs->whereNotNull('log_meta.factura_id')
                    ->where('factura.emitida', 1);
            } elseif ($request->factura_emitida == 'no') {
                $logs->where(function($query) {
                    $query->whereNull('log_meta.factura_id')
                          ->orWhere('factura.emitida', '!=', 1);
                });
            }
        }

        return datatables()->eloquent($logs)
            ->editColumn('id', function ($log) {
                return $log->id;
            })
            ->editColumn('created_at', function ($log) {
                return Carbon::parse($log->created_at)->format('d/m/Y H:i:s');
            })
            ->editColumn('contacto', function ($log) {
                if ($log->contacto_id) {
                    $nombre = trim(($log->contacto_nombre ?? '') . ' ' . ($log->contacto_apellido1 ?? '') . ' ' . ($log->contacto_apellido2 ?? ''));
                    return '<a href="' . route('contactos.show', $log->contacto_id) . '">' . $nombre . '</a>';
                }
                return '-';
            })
            ->editColumn('plantilla', function ($log) {
                return $log->plantilla_title ?? '-';
            })
            ->editColumn('factura', function ($log) {
                // Mostrar "Documento" con el número correspondiente según el tipo
                if ($log->incoming_invoice_id && $log->factura_codigo) {
                    $url = route('facturas.show', $log->incoming_invoice_id);
                    return '<a href="' . $url . '">Factura: ' . htmlspecialchars($log->factura_codigo) . '</a>';
                } elseif ($log->incoming_payment_id && $log->ingreso_nro) {
                    // Usar la ruta correcta para ingresos (puede ser ingresos/{id} o similar)
                    $url = url('/empresa/ingresos/' . $log->incoming_payment_id);
                    return '<a href="' . $url . '">Ingreso: ' . htmlspecialchars($log->ingreso_nro) . '</a>';
                } elseif ($log->incoming_contract_id && $log->contrato_nro) {
                    // Usar la ruta correcta para contratos
                    $url = url('/empresa/contratos/' . $log->incoming_contract_id);
                    return '<a href="' . $url . '">Contrato: ' . htmlspecialchars($log->contrato_nro) . '</a>';
                } elseif ($log->factura_id && $log->factura_codigo) {
                    // Fallback para factura_id antiguo
                    $url = route('facturas.show', $log->factura_id);
                    return '<a href="' . $url . '">Factura: ' . htmlspecialchars($log->factura_codigo) . '</a>';
                }
                return '-';
            })
            ->editColumn('status', function ($log) {
                return $log->estadoFormateado();
            })
            ->editColumn('mensaje_enviado', function ($log) {
                return '<span title="' . htmlspecialchars($log->mensaje_enviado ?? '') . '">' . htmlspecialchars($log->mensajeTruncado(80)) . '</span>';
            })
            ->addColumn('acciones', function ($log) {
                return view('whatsapp-meta-logs.acciones', compact('log'));
            })
            ->rawColumns(['contacto', 'factura', 'status', 'mensaje_enviado', 'acciones'])
            ->toJson();
    }

    public function show($id)
    {
        $this->getAllPermissions(Auth::user()->id);

        $log = WhatsappMetaLog::with(['factura', 'contacto', 'plantilla', 'usuarioEnvio', 'empresaObj'])
            ->findOrFail($id);

        // Verificar que el log pertenezca a la empresa del usuario
        if ($log->empresa != Auth::user()->empresa) {
            abort(403, 'No tienes permiso para ver este log.');
        }

        // Formatear respuesta JSON
        $responseJson = null;
        if ($log->response) {
            $responseJson = json_decode($log->response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $responseJson = $log->response; // Si no es JSON válido, mostrar como texto
            }
        }

        return view('whatsapp-meta-logs.show', compact('log', 'responseJson'));
    }

    public function limpiarFiltros()
    {
        // Retornar las fechas predeterminadas (mes actual)
        return response()->json([
            'success' => true,
            'fecha_desde' => Carbon::now()->startOfMonth()->format('Y-m-d'),
            'fecha_hasta' => Carbon::now()->endOfMonth()->format('Y-m-d'),
            'plantilla_id' => '',
            'contacto_id' => '',
            'factura_emitida' => 'ambas'
        ]);
    }

    public function getContactos(Request $request)
    {
        $this->getAllPermissions(Auth::user()->id);

        $empresaId = Auth::user()->empresa;
        $search = $request->get('search', '');

        $contactos = Contacto::where('empresa', $empresaId)
            ->where('status', 1)
            ->where(function($query) use ($search) {
                if (!empty($search)) {
                    $query->where('nombre', 'like', "%{$search}%")
                        ->orWhere('apellido1', 'like', "%{$search}%")
                        ->orWhere('nit', 'like', "%{$search}%");
                }
            })
            ->orderBy('nombre', 'ASC')
            ->orderBy('apellido1', 'ASC')
            ->limit(100)
            ->get(['id', 'nombre', 'apellido1', 'apellido2', 'nit']);

        $result = [];
        foreach ($contactos as $contacto) {
            $nombre = trim(($contacto->nombre ?? '') . ' ' . ($contacto->apellido1 ?? '') . ' ' . ($contacto->apellido2 ?? ''));
            $result[] = [
                'id' => $contacto->id,
                'nombre' => $nombre,
                'nit' => $contacto->nit ?? ''
            ];
        }

        return response()->json($result);
    }
}
