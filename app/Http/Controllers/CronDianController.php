<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Empresa;
use App\NumeracionFactura;
use App\Model\Ingresos\Factura;
use App\Services\BTWService;
use App\Builders\JsonBuilders\InvoiceJsonBuilder;
use App\Contacto;

class CronDianController extends Controller
{
    /**
     * Logger diario para el módulo DIAN.
     */
    protected $dianLog;

    public function __construct()
    {
        $this->dianLog = Log::channel('single');

        // Configurar un canal personalizado con archivo diario
        try {
            $logFile = storage_path('logs/cron-dian-' . date('Y-m-d') . '.log');
            $this->dianLog = new \Monolog\Logger('cron-dian');
            $handler = new \Monolog\Handler\StreamHandler($logFile, \Monolog\Logger::DEBUG);
            $handler->setFormatter(new \Monolog\Formatter\LineFormatter(null, null, true, true));
            $this->dianLog->pushHandler($handler);
        } catch (\Exception $e) {
            $this->dianLog = Log::channel('single');
        }
    }

    // =========================================================================
    // VISTA BLADE
    // =========================================================================
    public function vista()
    {
        if (!\Auth::check()) {
            return redirect()->route('login');
        }

        $this->getAllPermissions(\Auth::user()->id);

        $empresa = Empresa::find(1);

        $numeracion = NumeracionFactura::where('empresa', 1)
            ->where('estado', 1)
            ->where('tipo', 2)
            ->where('preferida', 1)
            ->first();

        $pendientes = Factura::where('empresa', 1)
            ->where('tipo', 2)
            ->where('emitida', 0)
            ->when($empresa->fecha_inicio_emision_dian, function ($q) use ($empresa) {
                return $q->where('fecha', '>=', $empresa->fecha_inicio_emision_dian);
            })
            ->where(function ($q) {
                $q->whereNull('dian_response')->orWhere('dian_response', '');
            })
            ->count();

        view()->share([
            'seccion'    => 'cronjobs',
            'title'      => 'Emisiones DIAN',
            'icon'       => 'fas fa-file-invoice',
            'subseccion' => 'emisiones-dian',
        ]);

        return view('cronjobs.emisiones-dian', compact('empresa', 'numeracion', 'pendientes'));
    }

    // =========================================================================
    // ENDPOINT POLLING — /api/cron-dian/estado
    // =========================================================================
    public function estado()
    {
        // Ejecución activa
        $logActual = DB::table('cron_dian_logs')
            ->where('estado', 'ejecutando')
            ->where('inicio_ejecucion', '>=', Carbon::now()->subMinutes(20))
            ->orderBy('id', 'desc')
            ->first();

        $ejecucionActiva = !is_null($logActual);

        // Empresa
        $empresa = Empresa::find(1);

        // Pendientes total
        $pendientesTotal = Factura::where('empresa', 1)
            ->where('tipo', 2)
            ->where('emitida', 0)
            ->when($empresa && $empresa->fecha_inicio_emision_dian, function ($q) use ($empresa) {
                return $q->where('fecha', '>=', $empresa->fecha_inicio_emision_dian);
            })
            ->where(function ($q) {
                $q->whereNull('dian_response')->orWhere('dian_response', '');
            })
            ->count();

        // Última ejecución completada
        $ultimaEjecucion = DB::table('cron_dian_logs')
            ->whereIn('estado', ['completado', 'parcial', 'error'])
            ->orderBy('id', 'desc')
            ->first();

        // Alertas sin resolver
        $alertas = DB::table('cron_dian_alertas_numeracion')
            ->where('resuelta', 0)
            ->orderBy('id', 'desc')
            ->get();

        // Progreso
        $progresoPorcentaje = 0;
        $detallesActuales = [];

        if ($ejecucionActiva && $logActual) {
            $totalAEmitir = $logActual->total_a_emitir ?: 1;
            $procesadas = $logActual->total_emitidas + $logActual->total_fallidas + $logActual->total_alertas_numeracion;
            $progresoPorcentaje = min(100, round(($procesadas / $totalAEmitir) * 100));

            $detallesActuales = DB::table('cron_dian_detalle')
                ->where('log_id', $logActual->id)
                ->orderBy('id', 'desc')
                ->limit(20)
                ->get();
        }

        return response()->json([
            'ejecucion_activa'    => $ejecucionActiva,
            'log_actual'          => $logActual,
            'pendientes_total'    => $pendientesTotal,
            'ultima_ejecucion'    => $ultimaEjecucion,
            'alertas_numeracion'  => $alertas,
            'progreso_porcentaje' => $progresoPorcentaje,
            'detalles_actuales'   => $detallesActuales,
            'emision_automatica'  => $empresa ? $empresa->emision_automatica : 0,
        ]);
    }

    // =========================================================================
    // DATATABLES HISTÓRICO — /api/cron-dian/logs
    // =========================================================================
    public function logs(Request $request)
    {
        $query = DB::table('cron_dian_logs')
            ->orderBy('id', 'desc');

        // Filtros opcionales
        if ($request->estado) {
            $query->where('estado', $request->estado);
        }
        if ($request->creado_por) {
            $query->where('creado_por', $request->creado_por);
        }
        if ($request->fecha_desde) {
            $query->where('inicio_ejecucion', '>=', $request->fecha_desde . ' 00:00:00');
        }
        if ($request->fecha_hasta) {
            $query->where('inicio_ejecucion', '<=', $request->fecha_hasta . ' 23:59:59');
        }

        return datatables()->of($query)
            ->addColumn('duracion', function ($row) {
                if ($row->fin_ejecucion && $row->inicio_ejecucion) {
                    $inicio = Carbon::parse($row->inicio_ejecucion);
                    $fin = Carbon::parse($row->fin_ejecucion);
                    $diff = $inicio->diffInSeconds($fin);
                    return gmdate('H:i:s', $diff);
                }
                return '-';
            })
            ->rawColumns(['duracion'])
            ->make(true);
    }

    // =========================================================================
    // DETALLE DE UN LOG — /api/cron-dian/detalle/{log_id}
    // =========================================================================
    public function detalle($logId)
    {
        $log = DB::table('cron_dian_logs')->where('id', $logId)->first();

        if (!$log) {
            return response()->json(['error' => 'Log no encontrado'], 404);
        }

        $detalles = DB::table('cron_dian_detalle')
            ->where('log_id', $logId)
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'log'      => $log,
            'detalles' => $detalles,
        ]);
    }

    // =========================================================================
    // ALERTAS NUMERACIÓN — /api/cron-dian/alertas-numeracion
    // =========================================================================
    public function alertasNumeracion()
    {
        $alertas = DB::table('cron_dian_alertas_numeracion as a')
            ->leftJoin('numeraciones_facturas as n', 'n.id', '=', 'a.numeracion_id')
            ->select('a.*', 'n.prefijo', 'n.inicio', 'n.final', 'n.desde', 'n.hasta', 'n.nombre as numeracion_nombre')
            ->where('a.resuelta', 0)
            ->orderBy('a.id', 'desc')
            ->get();

        return response()->json($alertas);
    }

    // =========================================================================
    // RESOLVER ALERTA — POST /api/cron-dian/resolver-alerta/{id}
    // =========================================================================
    public function resolverAlerta($id)
    {
        DB::table('cron_dian_alertas_numeracion')
            ->where('id', $id)
            ->update(['resuelta' => 1, 'updated_at' => now()]);

        return response()->json(['status' => 'ok', 'mensaje' => 'Alerta marcada como resuelta']);
    }

    // =========================================================================
    // EJECUCIÓN MANUAL — POST /api/cron-dian/ejecutar-manual
    // =========================================================================
    public function ejecutarManual(Request $request)
    {
        // Disparar el cronjob como manual
        return $this->ejecutar(new Request(['manual' => 1]));
    }

    // =========================================================================
    // CONFIGURAR FECHA DE EMISIÓN — POST /api/cron-dian/configurar-fecha
    // =========================================================================
    public function guardarConfiguracion(Request $request)
    {
        $empresa = Empresa::find(1);
        if ($empresa) {
            $empresa->fecha_inicio_emision_dian = $request->fecha_inicio;
            $empresa->save();
            return response()->json(['status' => 'ok', 'mensaje' => 'Configuración guardada correctamente.']);
        }
        return response()->json(['status' => 'error', 'mensaje' => 'Empresa no encontrada.'], 404);
    }

    // =========================================================================
    // CRONJOB PRINCIPAL — GET /emision-factura-dian
    // =========================================================================
    public function ejecutar(Request $request = null)
    {
        set_time_limit(0);

        if (!$request) {
            $request = request();
        }

        $esManual = $request->input('manual', 0) == 1;
        $creadoPor = $esManual ? 'manual' : 'automatico';

        $this->dianLog->info("=== INICIO EJECUCIÓN CRON DIAN ({$creadoPor}) ===");

        // ─── PASO 1: VERIFICAR LOCK (MUTEX) ───
        $lockActivo = DB::table('cron_dian_logs')
            ->where('estado', 'ejecutando')
            ->where('inicio_ejecucion', '>=', Carbon::now()->subMinutes(20))
            ->first();

        if ($lockActivo) {
            $this->dianLog->warning("Ejecución bloqueada. Lock activo: log_id={$lockActivo->id}");
            return response()->json([
                'status'  => 'bloqueado',
                'mensaje' => 'Hay una ejecución en curso',
                'log_id'  => $lockActivo->id,
            ]);
        }

        // ─── PASO 2: VERIFICAR EMPRESA ───
        $empresa = Empresa::find(1);

        if (!$empresa) {
            $this->dianLog->error("Empresa id=1 no encontrada");
            return response()->json(['status' => 'error', 'mensaje' => 'Empresa no encontrada']);
        }

        if (!$empresa->emision_automatica) {
            $this->dianLog->info("Emisión automática desactivada para la empresa");
            return response()->json(['status' => 'inactivo', 'mensaje' => 'Emisión automática desactivada']);
        }

        if (!$empresa->estado_dian) {
            $this->dianLog->error("Empresa no autorizada en DIAN");
            return response()->json(['status' => 'error', 'mensaje' => 'Empresa no autorizada en DIAN']);
        }

        // ─── PASO 3: CREAR LOG DE EJECUCIÓN ───
        $lockToken = (string) Str::uuid();
        $logId = DB::table('cron_dian_logs')->insertGetId([
            'empresa_id'       => 1,
            'inicio_ejecucion' => Carbon::now(),
            'estado'           => 'ejecutando',
            'total_a_emitir'   => 0,
            'total_emitidas'   => 0,
            'total_fallidas'   => 0,
            'total_alertas_numeracion' => 0,
            'lock_token'       => $lockToken,
            'creado_por'       => $creadoPor,
            'created_at'       => Carbon::now(),
            'updated_at'       => Carbon::now(),
        ]);

        $this->dianLog->info("Log creado: id={$logId}, token={$lockToken}");

        // ─── PASO 4: VALIDAR NUMERACIÓN ───
        $numeracion = NumeracionFactura::where('empresa', 1)
            ->where('estado', 1)
            ->where('tipo', 2)
            ->where('preferida', 1)
            ->first();

        // 4A: Sin numeración activa
        if (!$numeracion) {
            $this->registrarAlertaNumeracion(1, 0, 'sin_numeracion', null, null, 0);

            DB::table('cron_dian_logs')->where('id', $logId)->update([
                'estado'        => 'error',
                'observaciones' => 'Sin numeración DIAN activa (tipo=2, estado=1, preferida=1)',
                'fin_ejecucion' => Carbon::now(),
                'total_alertas_numeracion' => 1,
                'updated_at'    => Carbon::now(),
            ]);

            $this->dianLog->error("Sin numeración DIAN activa");
            return response()->json(['status' => 'error', 'mensaje' => 'Sin numeración DIAN activa', 'log_id' => $logId]);
        }

        // 4C: Fecha de vigencia
        $hoy = Carbon::now()->format('Y-m-d');
        if ($numeracion->desde && $numeracion->hasta) {
            if ($hoy < $numeracion->desde || $hoy > $numeracion->hasta) {
                $this->registrarAlertaNumeracion(1, $numeracion->id, 'fecha_vencida', null, null, 0);

                DB::table('cron_dian_logs')->where('id', $logId)->update([
                    'estado'                   => 'error',
                    'observaciones'            => "Numeración vencida. Vigencia: {$numeracion->desde} a {$numeracion->hasta}",
                    'fin_ejecucion'            => Carbon::now(),
                    'total_alertas_numeracion'  => 1,
                    'updated_at'               => Carbon::now(),
                ]);

                $this->dianLog->error("Numeración vencida: {$numeracion->desde} - {$numeracion->hasta}");
                return response()->json(['status' => 'error', 'mensaje' => 'Numeración DIAN vencida', 'log_id' => $logId]);
            }
        }

        // 4D-E: Verificar rango
        $maxNroEmitido = Factura::where('numeracion', $numeracion->id)
            ->where('emitida', 1)
            ->max('nro');

        $rangoSuperado = false;
        if ($maxNroEmitido && $maxNroEmitido >= $numeracion->final) {
            $rangoSuperado = true;
            $facturasAfectadas = Factura::where('empresa', 1)
                ->where('tipo', 2)
                ->where('emitida', 0)
                ->where('numeracion', $numeracion->id)
                ->count();

            $this->registrarAlertaNumeracion(
                1,
                $numeracion->id,
                'rango_superado',
                $maxNroEmitido,
                $numeracion->final,
                $facturasAfectadas
            );

            $this->dianLog->warning("Rango superado: max={$maxNroEmitido}, limite={$numeracion->final}, afectadas={$facturasAfectadas}");
        }

        // ─── PASO 5: OBTENER LOTE DE FACTURAS A EMITIR ───
        $facturas = Factura::where('empresa', 1)
            ->where('tipo', 2)
            ->where('emitida', 0)
            ->when($empresa->fecha_inicio_emision_dian, function ($q) use ($empresa) {
                return $q->where('fecha', '>=', $empresa->fecha_inicio_emision_dian);
            })
            ->where(function ($q) {
                $q->whereNull('dian_response')->orWhere('dian_response', '');
            })
            ->orderBy('id', 'asc')
            ->limit(50)
            ->get();

        $totalAEmitir = $facturas->count();

        DB::table('cron_dian_logs')->where('id', $logId)->update([
            'total_a_emitir' => $totalAEmitir,
            'updated_at'     => Carbon::now(),
        ]);

        $this->dianLog->info("Facturas encontradas para emitir: {$totalAEmitir}");

        if ($totalAEmitir === 0) {
            $estado = $rangoSuperado ? 'parcial' : 'completado';
            DB::table('cron_dian_logs')->where('id', $logId)->update([
                'estado'        => $estado,
                'fin_ejecucion' => Carbon::now(),
                'observaciones' => 'No hay facturas pendientes de emisión',
                'updated_at'    => Carbon::now(),
            ]);

            return response()->json([
                'status'  => 'ok',
                'mensaje' => 'No hay facturas pendientes de emisión',
                'log_id'  => $logId,
            ]);
        }

        // ─── PASO 6: PROCESAR CADA FACTURA DEL LOTE ───
        $totalEmitidas = 0;
        $totalFallidas = 0;
        $totalAlertasNum = $rangoSuperado ? 1 : 0;
        $totalOmitidas = 0;

        $modoBTW = env('BTW_TEST_MODE') == 1 ? 'test' : 'prod';

        $resolucion = NumeracionFactura::where('empresa', 1)
            ->where('num_equivalente', 0)
            ->where('nomina', 0)
            ->where('preferida', 1)
            ->where('tipo', 2)
            ->first();

        foreach ($facturas as $index => $factura) {
            $tiempoInicio = microtime(true);

            // ── 5a: Verificar anti-duplicado por código ──
            $duplicado = Factura::where('codigo', $factura->codigo)
                ->where('emitida', 1)
                ->where('id', '!=', $factura->id)
                ->exists();

            if ($duplicado) {
                DB::table('cron_dian_detalle')->insert([
                    'log_id'          => $logId,
                    'factura_id'      => $factura->id,
                    'factura_codigo'  => $factura->codigo,
                    'numeracion_id'   => $factura->numeracion,
                    'estado'          => 'duplicado_detectado',
                    'intento'         => 0,
                    'mensaje'         => "Código {$factura->codigo} ya existe con emitida=1 en otra factura",
                    'procesado_en'    => Carbon::now(),
                    'created_at'      => Carbon::now(),
                    'updated_at'      => Carbon::now(),
                ]);
                $totalOmitidas++;
                $this->dianLog->warning("DUPLICADO: factura_id={$factura->id}, codigo={$factura->codigo}");
                continue;
            }

            // ── 5b: Verificar numeración válida tipo=2 ──
            if ($factura->numeracion) {
                $numFactura = NumeracionFactura::where('id', $factura->numeracion)
                    ->where('tipo', 2)
                    ->first();

                if (!$numFactura) {
                    DB::table('cron_dian_detalle')->insert([
                        'log_id'          => $logId,
                        'factura_id'      => $factura->id,
                        'factura_codigo'  => $factura->codigo,
                        'numeracion_id'   => $factura->numeracion,
                        'estado'          => 'omitida_numeracion',
                        'intento'         => 0,
                        'mensaje'         => "Numeración id={$factura->numeracion} no es tipo=2 DIAN",
                        'procesado_en'    => Carbon::now(),
                        'created_at'      => Carbon::now(),
                        'updated_at'      => Carbon::now(),
                    ]);
                    $totalOmitidas++;
                    $totalAlertasNum++;
                    continue;
                }

                // ── 5c: Verificar que nro no supere el final ──
                if ($factura->nro && $numFactura->final && $factura->nro > $numFactura->final) {
                    $this->registrarAlertaNumeracion(
                        1,
                        $numFactura->id,
                        'rango_superado',
                        $factura->nro,
                        $numFactura->final,
                        1
                    );

                    DB::table('cron_dian_detalle')->insert([
                        'log_id'          => $logId,
                        'factura_id'      => $factura->id,
                        'factura_codigo'  => $factura->codigo,
                        'numeracion_id'   => $factura->numeracion,
                        'estado'          => 'omitida_numeracion',
                        'intento'         => 0,
                        'mensaje'         => "Nro {$factura->nro} supera el límite {$numFactura->final}",
                        'procesado_en'    => Carbon::now(),
                        'created_at'      => Carbon::now(),
                        'updated_at'      => Carbon::now(),
                    ]);
                    $totalOmitidas++;
                    $totalAlertasNum++;
                    continue;
                }
            }

            // ── 6a: Registrar detalle pendiente ──
            $detalleId = DB::table('cron_dian_detalle')->insertGetId([
                'log_id'          => $logId,
                'factura_id'      => $factura->id,
                'factura_codigo'  => $factura->codigo,
                'numeracion_id'   => $factura->numeracion,
                'estado'          => 'pendiente',
                'intento'         => 1,
                'procesado_en'    => Carbon::now(),
                'created_at'      => Carbon::now(),
                'updated_at'      => Carbon::now(),
            ]);

            // ── 6b–6f: Emitir factura via BTW con retry ──
            $exitosa = false;
            $intentos = 0;
            $cufe = null;
            $mensajeDetalle = '';

            for ($intento = 1; $intento <= 2; $intento++) {
                $intentos = $intento;

                if ($intento === 2) {
                    $this->dianLog->info("Reintentando factura id={$factura->id} (intento 2) en 3s...");
                    sleep(3);
                }

                try {
                    $resultado = $this->emitirFacturaBTW($factura, $empresa, $resolucion, $modoBTW);

                    if ($resultado['success']) {
                        $exitosa = true;
                        $cufe = $resultado['cufe'];
                        $mensajeDetalle = 'OK';
                        break;
                    } else {
                        $mensajeDetalle = $resultado['mensaje'] ?? 'Error desconocido en BTW';
                    }
                } catch (\Throwable $e) {
                    $mensajeDetalle = 'Exception: ' . $e->getMessage();
                    $this->dianLog->error("Error emitiendo factura id={$factura->id}: {$mensajeDetalle}");
                }
            }

            $tiempoFin = microtime(true);
            $tiempoMs = (int)(($tiempoFin - $tiempoInicio) * 1000);

            if ($exitosa) {
                // ── 6d: Reconectar y guardar ──
                try {
                    DB::reconnect();

                    Factura::where('id', $factura->id)->update([
                        'emitida'         => 1,
                        'uuid'            => $cufe,
                        'emision_cronjob' => 1,
                        'fecha_expedicion' => Carbon::now(),
                    ]);
                } catch (\Exception $e) {
                    // Si falla, reconectar y reintentar
                    if (strpos($e->getMessage(), 'Server has gone away') !== false ||
                        strpos($e->getMessage(), '2006') !== false) {
                        DB::reconnect();
                        Factura::where('id', $factura->id)->update([
                            'emitida'         => 1,
                            'uuid'            => $cufe,
                            'emision_cronjob' => 1,
                            'fecha_expedicion' => Carbon::now(),
                        ]);
                    } else {
                        $this->dianLog->error("Error guardando factura id={$factura->id}: {$e->getMessage()}");
                    }
                }

                try {
                    // Instanciar BTW para enviar el correo
                    $btwService = new BTWService();
                    $mensajeCorreo = '';
                    
                    if($modoBTW == 'prod'){
                        $mensajeCorreo = \App\Http\Controllers\Controller::sendPdfEmailBTW(
                            $btwService, 
                            $factura, 
                            $factura->clienteObj, 
                            $empresa, 
                            1
                        );
                    }

                    // ── 6e: Actualizar detalle con resultado correo ──
                    $mensajeFinal = $mensajeDetalle;
                    if ($mensajeCorreo != '') {
                        $mensajeFinal .= " | Correo: " . $mensajeCorreo;
                    }

                    DB::table('cron_dian_detalle')->where('id', $detalleId)->update([
                        'estado'            => 'emitida',
                        'cufe'              => $cufe,
                        'intento'           => $intentos,
                        'mensaje'           => $mensajeFinal,
                        'tiempo_respuesta_ms' => $tiempoMs,
                        'updated_at'        => Carbon::now(),
                    ]);

                    $totalEmitidas++;
                    $this->dianLog->info("EMITIDA: id={$factura->id}, codigo={$factura->codigo}, cufe={$cufe}, tiempo={$tiempoMs}ms, correo={$mensajeCorreo}");
                } catch (\Exception $e) {
                    // Si falla el envío de correo o la actualización del detalle
                    // Aseguramos que el estado de emisión no se pierda en el log final
                    DB::table('cron_dian_detalle')->where('id', $detalleId)->update([
                        'estado'            => 'emitida',
                        'cufe'              => $cufe,
                        'intento'           => $intentos,
                        'mensaje'           => $mensajeDetalle . " | Error en envío de correo: " . $e->getMessage(),
                        'tiempo_respuesta_ms' => $tiempoMs,
                        'updated_at'        => Carbon::now(),
                    ]);
                    $totalEmitidas++;
                    $this->dianLog->error("EMITIDA pero error en Correo: id={$factura->id}: {$e->getMessage()}");
                }
            } else {
                // ── 6f: Fallida ──
                DB::table('cron_dian_detalle')->where('id', $detalleId)->update([
                    'estado'            => 'fallida',
                    'intento'           => $intentos,
                    'mensaje'           => $mensajeDetalle,
                    'tiempo_respuesta_ms' => $tiempoMs,
                    'updated_at'        => Carbon::now(),
                ]);

                $totalFallidas++;
                $this->dianLog->error("FALLIDA: id={$factura->id}, codigo={$factura->codigo}, mensaje={$mensajeDetalle}");
            }

            // ── 6h: Flush parcial cada 5 facturas ──
            if (($index + 1) % 5 === 0) {
                DB::table('cron_dian_logs')->where('id', $logId)->update([
                    'total_emitidas'           => $totalEmitidas,
                    'total_fallidas'           => $totalFallidas,
                    'total_alertas_numeracion'  => $totalAlertasNum,
                    'updated_at'               => Carbon::now(),
                ]);
            }

            // ── 6g: Sleep entre facturas ──
            if ($index < $totalAEmitir - 1) {
                sleep(1);
            }
        }

        // ─── PASO 7: FINALIZAR ───
        $estadoFinal = ($totalFallidas === 0 && $totalOmitidas === 0) ? 'completado' : 'parcial';

        if ($totalEmitidas === 0 && $totalFallidas > 0) {
            $estadoFinal = 'error';
        }

        DB::table('cron_dian_logs')->where('id', $logId)->update([
            'estado'                   => $estadoFinal,
            'fin_ejecucion'            => Carbon::now(),
            'total_emitidas'           => $totalEmitidas,
            'total_fallidas'           => $totalFallidas,
            'total_alertas_numeracion'  => $totalAlertasNum,
            'observaciones'            => "Emitidas: {$totalEmitidas}, Fallidas: {$totalFallidas}, Omitidas: {$totalOmitidas}",
            'updated_at'               => Carbon::now(),
        ]);

        $this->dianLog->info("=== FIN EJECUCIÓN: {$estadoFinal} | Emitidas: {$totalEmitidas} | Fallidas: {$totalFallidas} | Omitidas: {$totalOmitidas} ===");

        return response()->json([
            'status'          => $estadoFinal,
            'log_id'          => $logId,
            'total_a_emitir'  => $totalAEmitir,
            'total_emitidas'  => $totalEmitidas,
            'total_fallidas'  => $totalFallidas,
            'total_omitidas'  => $totalOmitidas,
        ]);
    }

    // =========================================================================
    // EMITIR UNA FACTURA VIA BTW (lógica extraída de FacturasController)
    // =========================================================================
    private function emitirFacturaBTW($factura, $empresa, $resolucion, $modoBTW)
    {
        // Lock pesimista: SELECT FOR UPDATE dentro de transacción
        return DB::transaction(function () use ($factura, $empresa, $resolucion, $modoBTW) {

            // Re-verificar que no fue emitida (concurrencia)
            $facturaLock = Factura::where('id', $factura->id)->lockForUpdate()->first();

            if (!$facturaLock || $facturaLock->emitida == 1) {
                return ['success' => false, 'mensaje' => 'Factura ya emitida o no encontrada (lock)'];
            }

            // Verificar anti-duplicado dentro de la transacción
            $duplicado = Factura::where('codigo', $facturaLock->codigo)
                ->where('emitida', 1)
                ->where('id', '!=', $facturaLock->id)
                ->exists();

            if ($duplicado) {
                return ['success' => false, 'mensaje' => "Código duplicado detectado: {$facturaLock->codigo}"];
            }

            $cliente = $facturaLock->clienteObj;

            if (!$cliente) {
                return ['success' => false, 'mensaje' => 'Cliente no encontrado para la factura'];
            }

            // Validar NIT sin guiones
            if ($cliente->nit && strpos($cliente->nit, '-') !== false) {
                return ['success' => false, 'mensaje' => 'El documento del cliente contiene guiones'];
            }

            $operacionCodigo = "10";
            if ($facturaLock->tipo_operacion == 2) {
                $operacionCodigo = "09";
            }

            // Validación de dia 00 en vencimiento
            if (substr($facturaLock->vencimiento, -2) == '00' || $facturaLock->vencimiento < Carbon::now()->format("Y-m-d")) {
                $anoMes = substr($facturaLock->vencimiento, 0, 7);
                $fecha = Carbon::createFromFormat('Y-m', $anoMes)->endOfMonth();
                $facturaLock->vencimiento = $fecha->toDateString();
                $facturaLock->save();
            }

            // Validación de dia 00 en suspension
            if ($facturaLock->suspension && (substr($facturaLock->suspension, -2) == '00' || $facturaLock->suspension < Carbon::now()->format("Y-m-d"))) {
                $anoMes = substr($facturaLock->suspension, 0, 7);
                $fecha = Carbon::createFromFormat('Y-m', $anoMes)->endOfMonth();
                $facturaLock->suspension = $fecha->toDateString();
                $facturaLock->save();
            }

            // Actualizar fecha de emisión
            $facturaLock->fecha = Carbon::now()->format('Y-m-d');
            $facturaLock->save();

            // Construir JSON
            $jsonInvoiceHead = InvoiceJsonBuilder::buildFromHeadInvoice($facturaLock, $resolucion, $modoBTW, $operacionCodigo);
            $jsonInvoiceDetails = InvoiceJsonBuilder::buildFromDetails($facturaLock, $resolucion, $modoBTW);
            $jsonInvoiceCompany = InvoiceJsonBuilder::buildFromCompany($empresa, $modoBTW);
            $jsonInvoiceCustomer = InvoiceJsonBuilder::buildFromCustomer($cliente, $empresa, $modoBTW, $facturaLock);
            $jsonInvoiceTaxes = InvoiceJsonBuilder::buildFromTaxes(false, $facturaLock, $empresa, $modoBTW);

            $fullJson = InvoiceJsonBuilder::buildFullInvoice([
                'head'      => $jsonInvoiceHead,
                'details'   => $jsonInvoiceDetails,
                'company'   => $jsonInvoiceCompany,
                'customer'  => $jsonInvoiceCustomer,
                'taxes'     => $jsonInvoiceTaxes,
                'mode'      => $modoBTW,
                'btw_login' => $empresa->btw_login,
                'software'  => 2,
            ]);

            // Enviar a BTW
            $btw = new BTWService();
            $response = (object) $btw->sendInvoiceBTW($fullJson);

            // Evaluar respuesta
            if (isset($response->status) && $response->status == 'success') {
                return [
                    'success'  => true,
                    'cufe'     => $response->cufe ?? null,
                    'response' => $response,
                ];
            }

            // Evaluar error 500 con CUFE DIAN embebido (factura ya emitida en DIAN)
            if (isset($response->statusCode) && $response->statusCode == 500) {
                $resArr = json_decode(json_encode($response), true);
                $mensaje = $resArr['th']['btw_response'] ?? '';
                $cufeDian = null;

                if (preg_match('/CUFE DIAN:\s*([a-f0-9]{96})/i', $mensaje, $match)) {
                    $cufeDian = $match[1];
                }

                if ($cufeDian) {
                    return [
                        'success'  => true,
                        'cufe'     => $cufeDian,
                        'response' => $response,
                    ];
                }

                return [
                    'success' => false,
                    'mensaje' => $resArr['th']['btw_response'] ?? 'Error 500 en BTW',
                ];
            }

            // Error genérico
            $mensajeError = 'Error desconocido';
            if (isset($response->success) && $response->success == false) {
                if (isset($response->result) && isset($response->result->descResponseDian)) {
                    $mensajeError = $response->result->descResponseDian;
                } elseif (isset($response->message)) {
                    $mensajeError = $response->message;
                }
            } elseif (isset($response->errorMessage)) {
                $mensajeError = $response->errorMessage;
            }

            // Guardar respuesta DIAN en la factura para debugging
            try {
                DB::reconnect();
                $facturaLock->dian_response = json_encode($response);
                $facturaLock->save();
            } catch (\Exception $e) {
                // Silenciar error de guardado
            }

            return ['success' => false, 'mensaje' => $mensajeError];
        });
    }

    // =========================================================================
    // HELPER: Registrar alerta de numeración
    // =========================================================================
    private function registrarAlertaNumeracion($empresaId, $numeracionId, $tipo, $nroUltimo, $nroLimite, $cantidadAfectadas)
    {
        // Verificar si ya existe una alerta sin resolver del mismo tipo
        $existente = DB::table('cron_dian_alertas_numeracion')
            ->where('empresa_id', $empresaId)
            ->where('numeracion_id', $numeracionId)
            ->where('tipo_alerta', $tipo)
            ->where('resuelta', 0)
            ->first();

        if ($existente) {
            // Actualizar la existente
            DB::table('cron_dian_alertas_numeracion')
                ->where('id', $existente->id)
                ->update([
                    'nro_ultimo_usado'          => $nroUltimo,
                    'nro_limite'                => $nroLimite,
                    'cantidad_facturas_afectadas' => $cantidadAfectadas,
                    'updated_at'                => Carbon::now(),
                ]);
        } else {
            DB::table('cron_dian_alertas_numeracion')->insert([
                'empresa_id'                  => $empresaId,
                'numeracion_id'               => $numeracionId,
                'tipo_alerta'                 => $tipo,
                'nro_ultimo_usado'            => $nroUltimo,
                'nro_limite'                  => $nroLimite,
                'cantidad_facturas_afectadas' => $cantidadAfectadas,
                'resuelta'                    => 0,
                'created_at'                  => Carbon::now(),
                'updated_at'                  => Carbon::now(),
            ]);
        }
    }
}
