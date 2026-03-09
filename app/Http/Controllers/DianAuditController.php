<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DianAuditSession;
use App\DianAuditRecord;
use App\DianAuditLog;
use App\Model\Ingresos\Factura;
use App\Contacto;
use App\NumeracionFactura;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Yajra\DataTables\Facades\DataTables;
use Barryvdh\DomPDF\Facade as PDF;
use App\Contrato;
use App\FacturaContratos;

class DianAuditController extends Controller
{
    // Configuracion de nombres de columna de la tabla factura
    const COL_ID = 'id';
    const COL_CODIGO = 'codigo';
    const COL_PREFIJO = 'prefijo';
    const COL_CUFE = 'uuid'; // En este sistema se usa uuid para el CUFE
    const COL_NIT = 'nit_cliente';
    const COL_NOMBRE = 'nombre_cliente';
    const COL_CLIENTE_FK = 'cliente';

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $this->getAllPermissions(Auth::user()->id);
        view()->share(['seccion' => 'auditoria', 'subseccion' => 'auditoria-facturas', 'icon' => 'fas fa-user-secret', 'title' => 'Auditoría DIAN']);
        
        $kpis = [
            'total_sesiones' => DianAuditSession::count(),
            'discrepancias_activas' => DianAuditRecord::where('status', 'discrepancy')->count(),
            'total_corregidas' => DianAuditSession::sum('corrected'),
            'monto_riesgo' => DianAuditSession::sum('monto_total_discrepancia')
        ];

        $sesiones = DianAuditSession::with('user')->orderBy('created_at', 'desc')->paginate(10);

        return view('dian-audit.index', compact('kpis', 'sesiones'));
    }

    public function create()
    {
        $this->getAllPermissions(Auth::user()->id);
        view()->share(['seccion' => 'auditoria', 'subseccion' => 'auditoria-facturas', 'icon' => 'fas fa-upload', 'title' => 'Cargar Reporte DIAN']);
        return view('dian-audit.create');
    }

    public function upload(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv|max:20480',
            'periodo' => 'required|string|max:100'
        ]);

        $file = $request->file('archivo');
        $originalName = $file->getClientOriginalName();
        $path = $file->store('dian-audits', 'local');

        $session = DianAuditSession::create([
            'filename' => $path,
            'original_filename' => $originalName,
            'uploaded_by' => Auth::id(),
            'periodo' => $request->periodo,
            'status' => 'processing'
        ]);

        try {
            $this->procesarArchivoDian($session);
            return redirect()->route('auditoria.facturas.session', $session->id)
                ->with('success', 'Archivo procesado correctamente.');
        } catch (\Exception $e) {
            $session->update([
                'status' => 'error',
                'error_message' => $e->getMessage()
            ]);
            return redirect()->back()->with('error', 'Error al procesar el archivo: ' . $e->getMessage());
        }
    }

    private function procesarArchivoDian(DianAuditSession $session)
    {
        $path = storage_path('app/' . $session->filename);
        $spreadsheet = IOFactory::load($path);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        // Columnas esperadas segun requerimiento:
        // Tipo [0] | CUFE [1] | Folio [2] | Prefijo [3] | ... | Receptor NIT [11] | Receptor Nombre [12] | ... | Total [29]
        
        $total_records = 0;
        $matched = 0;
        $discrepancies = 0;
        $not_found = 0;
        $monto_total_discrepancia = 0;

        // Saltamos la primera fila (encabezados)
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty($row[1])) continue; // Si no hay CUFE, saltar

            $cufe = trim($row[1]);
            $folio = trim($row[2]);
            $prefijo = trim($row[3]);
            $nit_dian = $this->normalizarNit($row[11]);
            $nombre_dian = trim($row[12]);
            $total = $this->parseMonto($row[29]);
            $fecha = $this->parseFecha($row[7]);

            // Buscar factura en sistema
            $factura = Factura::where(self::COL_CUFE, $cufe)->first();
            
            if (!$factura && !empty($folio)) {
                // Intento alternativo por prefijo + folio (ej: FE10) o solo folio
                $codigo_completo = $prefijo . $folio;
                $factura = Factura::where(self::COL_CODIGO, $codigo_completo)
                    ->orWhere(self::COL_CODIGO, $folio)
                    ->first();
            }

            $status = 'not_found';
            $nit_sistema = null;
            $nombre_sistema = null;
            $cliente_id_sistema = null;
            $factura_id = null;

            if ($factura) {
                $factura_id = $factura->id;
                $cliente = $factura->cliente();
                $nit_sistema = $this->normalizarNit($cliente->nit);
                $nombre_sistema = $cliente->nombre;
                $cliente_id_sistema = $cliente->id;

                if ($nit_dian === $nit_sistema) {
                    $status = 'matched';
                    $matched++;
                } else {
                    $status = 'discrepancy';
                    $discrepancies++;
                    $monto_total_discrepancia += $total;
                }
            } else {
                $not_found++;
            }

            DianAuditRecord::create([
                'session_id' => $session->id,
                'tipo_documento' => $row[0],
                'cufe' => $cufe,
                'folio' => $folio,
                'prefijo' => $prefijo,
                'nit_receptor_dian' => $row[11],
                'nombre_receptor_dian' => $row[12],
                'nit_emisor' => $row[9],
                'nombre_emisor' => $row[10],
                'fecha_emision' => $fecha,
                'total' => $total,
                'status' => $status,
                'factura_id' => $factura_id,
                'nit_receptor_sistema' => $factura ? $nit_sistema : null,
                'nombre_receptor_sistema' => $factura ? $nombre_sistema : null,
                'cliente_id_sistema' => $cliente_id_sistema
            ]);

            $total_records++;
        }

        $session->update([
            'total_records' => $total_records,
            'matched' => $matched,
            'discrepancies' => $discrepancies,
            'not_found' => $not_found,
            'monto_total_discrepancia' => $monto_total_discrepancia,
            'status' => 'completed'
        ]);
    }

    public function session($id)
    {
        $this->getAllPermissions(Auth::user()->id);
        $session = DianAuditSession::with('user')->findOrFail($id);
        view()->share(['seccion' => 'auditoria', 'subseccion' => 'auditoria-facturas', 'icon' => 'fas fa-file-invoice-dollar', 'title' => 'Detalle de Auditoría: ' . $session->periodo]);
        return view('dian-audit.session', compact('session'));
    }

    public function datatables(Request $request, $sessionId)
    {
        $query = DianAuditRecord::where('session_id', $sessionId);

        if ($request->status && $request->status != 'all') {
            $query->where('status', $request->status);
        }

        return DataTables::of($query)
            ->filterColumn('folio', function($query, $keyword) {
                $sql = "CONCAT(COALESCE(prefijo, ''), COALESCE(folio, '')) LIKE ?";
                $query->whereRaw($sql, ["%{$keyword}%"]);
            })
            ->editColumn('folio', function($row) {
                return $row->folio_completo;
            })
            ->editColumn('cufe', function($row) {
                return '<div class="d-flex align-items-center">
                            <span class="text-truncate small mr-1" style="max-width: 80px;" title="'.$row->cufe.'">'.$row->cufe.'</span>
                            <button type="button" class="btn btn-xs btn-link p-0 copy-cufe" data-cufe="'.$row->cufe.'" title="Copiar CUFE completo">
                                <i class="far fa-copy text-muted"></i>
                            </button>
                        </div>';
            })
            ->editColumn('status', function($row) {
                return $row->status_badge;
            })
            ->addColumn('receptor_dian', function($row) {
                return "<b>$row->nit_receptor_dian</b><br><small>$row->nombre_receptor_dian</small>";
            })
            ->addColumn('receptor_sistema', function($row) {
                if (!$row->factura_id) return '<span class="text-muted">No vinculada</span>';
                $class = ($row->status == 'discrepancy') ? 'text-danger font-weight-bold' : '';
                return "<span class='$class'><b>$row->nit_receptor_sistema</b><br><small>$row->nombre_receptor_sistema</small></span>";
            })
            ->editColumn('total', function($row) {
                return $row->total_formatted;
            })
            ->addColumn('acciones', function($row) {
                $btn = '';
                if ($row->status == 'discrepancy') {
                    $btn .= '<button onclick="abrirModalCorreccion('.$row->id.')" class="btn btn-xs btn-danger"><i class="fas fa-edit"></i> Corregir</button>';
                } elseif ($row->status == 'corrected') {
                    $btn .= '<button onclick="verHistorial('.$row->id.')" class="btn btn-xs btn-info"><i class="fas fa-history"></i> Historial</button>';
                }
                return $btn;
            })
            ->rawColumns(['cufe', 'status', 'receptor_dian', 'receptor_sistema', 'acciones'])
            ->make(true);
    }

    public function corregir($recordId)
    {
        $record = DianAuditRecord::with(['factura', 'cliente'])->findOrFail($recordId);
        $logs = DianAuditLog::where('audit_record_id', $recordId)->orderBy('created_at', 'desc')->take(3)->get();
        
        return response()->json([
            'record' => $record,
            'logs' => $logs
        ]);
    }

    public function aplicarCorreccion(Request $request, $recordId)
    {
        $request->validate([
            'cliente_id_nuevo' => 'required|exists:contactos,id',
            'motivo' => 'nullable|string'
        ]);

        $record = DianAuditRecord::findOrFail($recordId);
        $factura = Factura::findOrFail($record->factura_id);
        $clienteNuevo = Contacto::findOrFail($request->cliente_id_nuevo);

        DB::transaction(function() use ($record, $factura, $clienteNuevo, $request) {
            // Respaldar datos anteriores para el log
            $nitAnterior = $factura->{self::COL_NIT} ?? '';
            $nombreAnterior = $factura->{self::COL_NOMBRE} ?? '';
            $clienteIdAnterior = $factura->{self::COL_CLIENTE_FK};

            // Actualizar factura
            $factura->update([
                self::COL_NIT => $clienteNuevo->nit,
                self::COL_NOMBRE => $clienteNuevo->nombre . ' ' . $clienteNuevo->apellido1 . ' ' . $clienteNuevo->apellido2,
                self::COL_CLIENTE_FK => $clienteNuevo->id
            ]);

            // Manejo de Contrato si se proporciona
            if ($request->contrato_id) {
                $contratoNuevo = Contrato::findOrFail($request->contrato_id);
                
                // Borrar relación previa en facturas_contratos para esta factura
                DB::table('facturas_contratos')->where('factura_id', $factura->id)->delete();
                
                // Crear nueva relación
                DB::table('facturas_contratos')->insert([
                    'factura_id' => $factura->id,
                    'contrato_nro' => $contratoNuevo->nro
                ]);

                // Actualizar columna contrato_id en factura si existe (compatibilidad)
                $columns = DB::connection()->getSchemaBuilder()->getColumnListing('factura');
                if (in_array('contrato_id', $columns)) {
                    $factura->contrato_id = $contratoNuevo->id;
                    $factura->save();
                }
            }

            // Crear Log Inmutable
            DianAuditLog::create([
                'audit_record_id' => $record->id,
                'session_id' => $record->session_id,
                'factura_id' => $factura->id,
                'folio' => $record->folio,
                'cufe' => $record->cufe,
                'nit_anterior' => $nitAnterior,
                'nombre_anterior' => $nombreAnterior,
                'cliente_id_anterior' => $clienteIdAnterior,
                'nit_nuevo' => $clienteNuevo->nit,
                'nombre_nuevo' => $clienteNuevo->nombre . ' ' . $clienteNuevo->apellido1 . ' ' . $clienteNuevo->apellido2,
                'cliente_id_nuevo' => $clienteNuevo->id,
                'motivo' => $request->motivo . ($request->contrato_id ? " [Re-asociación de Contrato]" : ""),
                'usuario_id' => Auth::id(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Actualizar Record
            $record->update(['status' => 'corrected']);

            // Actualizar Contadores de Sesion
            $session = $record->session;
            $session->increment('corrected');
            $session->decrement('discrepancies');
            // Opcional: descontar del monto en riesgo
            $session->monto_total_discrepancia -= $record->total;
            $session->save();
        });

        $session = DianAuditRecord::findOrFail($recordId)->session;

        return response()->json([
            'success' => true,
            'message' => 'Corrección aplicada y vinculación de contrato actualizada.',
            'nuevo_estado_badge' => '<span class="badge badge-primary">Corregido</span>',
            'session_stats' => [
                'corrected' => $session->corrected,
                'discrepancies' => $session->discrepancies,
                'percentage' => $session->porcentaje_ok
            ]
        ]);
    }

    public function buscarCliente(Request $request)
    {
        $q = $request->q;
        $clientes = Contacto::where('nit', 'like', "%$q%")
            ->orWhere('nombre', 'like', "%$q%")
            ->orWhere('apellido1', 'like', "%$q%")
            ->orWhere('empresa', 'like', "%$q%")
            ->take(10)
            ->get(['id', 'nit', 'nombre', 'apellido1', 'apellido2', 'empresa']);

        $res = [];
        foreach ($clientes as $c) {
            $nombre = trim($c->nombre . ' ' . $c->apellido1 . ' ' . $c->apellido2);
            if (empty($nombre) || $nombre == '1') { // Fallback if name is weird or empty
                $nombre = $c->empresa;
            }
            $res[] = [
                'id' => $c->id,
                'text' => "[$c->nit] $nombre"
            ];
        }

        return response()->json($res);
    }

    public function getContratosCliente(Request $request)
    {
        $clienteId = $request->cliente_id;
        $recordId = $request->record_id;

        $record = DianAuditRecord::findOrFail($recordId);
        $fecha = Carbon::parse($record->fecha_emision);
        $mes = $fecha->month;
        $anio = $fecha->year;

        $contratos = Contrato::where('client_id', $clienteId)->get();
        
        $res = [];
        foreach ($contratos as $c) {
            // Inteligencia: Buscar si el contrato ya tiene factura en el mes del reporte DIAN
            $facturaMes = $c->facturas()
                ->whereMonth('fecha', $mes)
                ->whereYear('fecha', $anio)
                ->whereIn('estatus', [0, 1])
                ->first();

            $infoFactura = null;
            if ($facturaMes) {
                $infoFactura = [
                    'codigo' => $facturaMes->codigo,
                    'emitida' => $facturaMes->emitida == 1,
                    'estatus' => $facturaMes->estatus == 1 ? 'Abierta' : 'Cerrada'
                ];
            }

            $res[] = [
                'id' => $c->id,
                'nro' => $c->nro,
                'plan' => $c->plan() ? $c->plan()->name : 'N/A',
                'estado' => $c->status(),
                'recomendado' => !$facturaMes,
                'factura_mes' => $infoFactura
            ];
        }

        return response()->json($res);
    }

    public function logsSesion($sessionId)
    {
        $this->getAllPermissions(Auth::user()->id);
        $session = DianAuditSession::findOrFail($sessionId);
        view()->share(['seccion' => 'auditoria', 'subseccion' => 'auditoria-facturas', 'icon' => 'fas fa-history', 'title' => 'Log de Correcciones: ' . $session->periodo]);
        return view('dian-audit.logs', compact('session'));
    }

    public function exportarDiscrepanciasPdf($sessionId)
    {
        $session = DianAuditSession::with(['records' => function($q) {
            $q->whereIn('status', ['discrepancy', 'corrected']);
        }])->findOrFail($sessionId);

        $empresa = Auth::user()->empresaObj;
        
        $pdf = PDF::loadView('dian-audit.partials.pdf_discrepancias', compact('session', 'empresa'));
        return $pdf->download("Auditoria_DIAN_P{$session->id}.pdf");
    }

    public function destroy($id)
    {
        $session = DianAuditSession::findOrFail($id);
        
        try {
            DB::beginTransaction();
            
            // Eliminar registros hijos (los logs se eliminan por cascada o manualmente si no hay FK)
            foreach($session->records as $record) {
                $record->logs()->delete();
                $record->delete();
            }
            
            // Eliminar archivo del storage si existe
            if (Storage::exists($session->filename)) {
                Storage::delete($session->filename);
            }
            
            $session->delete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Sesión de auditoría eliminada correctamente.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la sesión: ' . $e->getMessage()
            ], 500);
        }
    }

    // --- Helpers ---

    private function normalizarNit($nit)
    {
        // Quitar puntos, guiones, espacios
        $nit = preg_replace('/[^0-9]/', '', $nit);
        // Si el NIT tiene mas de 9 digitos, podria ser con DV al final, a veces se quita para comparar
        // pero aqui haremos comparacion de lo que quede numerico.
        return $nit;
    }

    private function parseMonto($monto)
    {
        // Formatos posibles: " 7.333,00 ", "7,333.00", etc.
        // Asumiendo formato colombiano: punto miles, coma decimales
        $monto = trim($monto);
        // Si hay coma y punto, asumimos formato CO (1.000,00)
        if (strpos($monto, '.') !== false && strpos($monto, ',') !== false) {
            $monto = str_replace('.', '', $monto);
            $monto = str_replace(',', '.', $monto);
        } else {
            // Si solo hay uno, intentamos adivinar. Si hay coma y 2 decimales, es decimal.
            // Pero en CO muchas veces 55.000 no tiene decimales.
            // Si termina en ,XX o .XX es probable que sea decimal.
            if (preg_match('/[,\.][0-9]{2}$/', $monto)) {
                $monto = str_replace(',', '.', $monto);
                // Si despues de reemplazar queda mas de un punto, el primero era miles.
                if (substr_count($monto, '.') > 1) {
                    $pos = strpos($monto, '.');
                    $monto = substr($monto, 0, $pos) . substr($monto, $pos + 1);
                }
            } else {
                // Sin decimales claros, quitar cualquier separador para tratar como entero
                $monto = str_replace(['.', ',', ' ', '$'], '', $monto);
            }
        }
        return (float) $monto;
    }

    private function parseFecha($fecha)
    {
        try {
            return Carbon::parse($fecha)->format('Y-m-d');
        } catch (\Exception $e) {
            return date('Y-m-d');
        }
    }
}
