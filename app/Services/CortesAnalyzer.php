<?php

namespace App\Services;

use App\GrupoCorte;
use App\Mikrotik;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RouterosAPI;

class CortesAnalyzer
{
    private const TTL_SUMMARY       = 300;  // 5 min
    private const TTL_PENDING       = 180;  // 3 min
    private const TTL_MK_SYNC       = 120;  // 2 min
    private const TTL_HISTORY       = 600;  // 10 min
    private const TTL_ALL_CONTRACTS = 180;  // 3 min

    // ──────────────────────────────────────────────────────────────────────────
    //  Caché
    // ──────────────────────────────────────────────────────────────────────────

    public function clearCache(int $grupoCorteId): void
    {
        Cache::forget("cortes_analyzer_all_contracts_{$grupoCorteId}");
        foreach (['summary', 'pending_internet', 'pending_tv', 'cut_internet', 'cut_tv', 'reasons'] as $key) {
            Cache::forget("cortes_analyzer_{$key}_{$grupoCorteId}");
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Resumen del grupo
    // ──────────────────────────────────────────────────────────────────────────

    public function getCorteSummary(int $grupoCorteId): array
    {
        return Cache::remember("cortes_analyzer_summary_{$grupoCorteId}", self::TTL_SUMMARY, function () use ($grupoCorteId) {
            $grupo = GrupoCorte::find($grupoCorteId);
            if (! $grupo) return [];

            $fecha      = now()->format('Y-m-d');
            $horaActual = now()->format('H:i');

            $totales = DB::table('contracts')
                ->where('grupo_corte', $grupoCorteId)
                ->where('status', 1)
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN state = 'enabled'  THEN 1 ELSE 0 END) as activos,
                    SUM(CASE WHEN state = 'disabled' THEN 1 ELSE 0 END) as cortados_internet,
                    SUM(CASE WHEN state_olt_catv = 0 THEN 1 ELSE 0 END) as cortados_tv,
                    SUM(CASE WHEN state_olt_catv = 1 THEN 1 ELSE 0 END) as tv_activos,
                    SUM(CASE WHEN (conexion = 2 OR conexion = 3) AND serial_onu IS NOT NULL THEN 1 ELSE 0 END) as con_olt,
                    SUM(CASE WHEN server_configuration_id IS NOT NULL THEN 1 ELSE 0 END) as con_mikrotik
                ")
                ->first();

            $pendientesInternet = $this->countPendingInternetCuts($grupoCorteId, $fecha);
            $pendientesTv       = $this->countPendingTvCuts($grupoCorteId, $fecha);

            $cronActivo = $grupo->status == 1
                && $grupo->fecha_suspension != 0
                && isset($grupo->nro_factura_vencida) && $grupo->nro_factura_vencida > 0
                && $grupo->hora_suspension <= $horaActual;

            $ultimoLogInternet = DB::table('cron_cortes_logs')
                ->where('grupo_corte_id', $grupoCorteId)->where('tipo', 'internet')
                ->orderByDesc('created_at')->first();

            $ultimoLogTv = DB::table('cron_cortes_logs')
                ->where('grupo_corte_id', $grupoCorteId)->where('tipo', 'tv')
                ->orderByDesc('created_at')->first();

            return [
                'grupo_corte'             => $grupo,
                'fecha_analisis'          => $fecha,
                'hora_analisis'           => $horaActual,
                'cron_activo'             => $cronActivo,
                'hora_suspension'         => $grupo->hora_suspension,
                'nro_factura_vencida'     => (int) ($grupo->nro_factura_vencida ?? 0),
                'prorroga_tv'             => (int) ($grupo->prorroga_tv ?? 0),
                'total_contratos'         => (int) ($totales->total ?? 0),
                'total_activos'           => (int) ($totales->activos ?? 0),
                'total_cortados_internet' => (int) ($totales->cortados_internet ?? 0),
                'total_cortados_tv'       => (int) ($totales->cortados_tv ?? 0),
                'total_tv_activos'        => (int) ($totales->tv_activos ?? 0),
                'total_con_olt'           => (int) ($totales->con_olt ?? 0),
                'total_con_mikrotik'      => (int) ($totales->con_mikrotik ?? 0),
                'pendientes_internet'     => $pendientesInternet,
                'pendientes_tv'           => $pendientesTv,
                'ultimo_corte_internet'   => $ultimoLogInternet,
                'ultimo_corte_tv'         => $ultimoLogTv,
            ];
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Pendientes de corte Internet
    // ──────────────────────────────────────────────────────────────────────────

    public function getPendingInternetCuts(int $grupoCorteId, ?string $fecha = null): \Illuminate\Support\Collection
    {
        $fecha    = $fecha ?? now()->format('Y-m-d');
        $cacheKey = "cortes_analyzer_pending_internet_{$grupoCorteId}_{$fecha}";

        return Cache::remember($cacheKey, self::TTL_PENDING, function () use ($grupoCorteId, $fecha) {
            return DB::table('contracts as cs')
                ->join('contactos', 'contactos.id', '=', 'cs.client_id')
                ->join('facturas_contratos as fcs', 'fcs.contrato_nro', '=', 'cs.nro')
                ->join('factura as f', 'f.id', '=', 'fcs.factura_id')
                ->leftJoin('mikrotik as mk', 'mk.id', '=', 'cs.server_configuration_id')
                ->leftJoin('promesa_pago as pp', function ($join) use ($fecha) {
                    $join->on('pp.factura', '=', 'f.id')
                         ->where('pp.vencimiento', '>=', $fecha);
                })
                ->select(
                    'cs.id as contrato_id', 'cs.nro as contrato_nro',
                    'cs.ip', 'cs.state', 'cs.usuario', 'cs.conexion',
                    'cs.serial_onu', 'cs.server_configuration_id as mikrotik_id',
                    'cs.fecha_suspension', 'cs.tipo_nosuspension',
                    'cs.fecha_desde_nosuspension', 'cs.fecha_hasta_nosuspension',
                    'mk.nombre as mikrotik_nombre', 'mk.ip as mikrotik_ip',
                    'contactos.id as cliente_id', 'contactos.nombre as cliente_nombre',
                    'contactos.nit as cliente_nit',
                    'f.id as factura_id', 'f.vencimiento',
                    DB::raw('DATEDIFF(NOW(), f.vencimiento) as dias_vencida'),
                    DB::raw('(SELECT COUNT(*) FROM factura f2
                              JOIN facturas_contratos fcs2 ON fcs2.factura_id = f2.id
                              WHERE fcs2.contrato_nro = cs.nro AND f2.estatus = 1
                              AND f2.tipo IN (1,2) AND f2.vencimiento <= NOW()) as total_facturas_vencidas')
                )
                ->where('f.estatus', 1)->whereIn('f.tipo', [1, 2])
                ->where('contactos.status', 1)->where('cs.state', 'enabled')
                ->where('cs.status', 1)->where('cs.grupo_corte', $grupoCorteId)
                ->whereNull('cs.fecha_suspension')
                ->whereDate('f.vencimiento', '<=', $fecha)
                ->whereNull('pp.id')
                ->where(function ($q) use ($fecha) {
                    $q->where('cs.tipo_nosuspension', '!=', 1)
                      ->orWhere('cs.fecha_desde_nosuspension', '>', $fecha)
                      ->orWhere('cs.fecha_hasta_nosuspension', '<', $fecha);
                })
                ->whereIn('f.id', function ($sub) {
                    $sub->selectRaw('MAX(f2.id)')->from('factura as f2')
                        ->join('facturas_contratos as fcs2', 'fcs2.factura_id', '=', 'f2.id')
                        ->whereColumn('fcs2.contrato_nro', 'cs.nro')
                        ->where('f2.estatus', 1)->whereIn('f2.tipo', [1, 2])
                        ->whereDate('f2.vencimiento', '<=', now())
                        ->groupBy('fcs2.contrato_nro');
                })
                ->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))->from('factura as f_newer')
                        ->join('facturas_contratos as fcs_newer', 'fcs_newer.factura_id', '=', 'f_newer.id')
                        ->whereColumn('fcs_newer.contrato_nro', 'cs.nro')
                        ->whereIn('f_newer.tipo', [1, 2])->where('f_newer.estatus', 0)
                        ->whereColumn('f_newer.vencimiento', '>', 'f.vencimiento');
                })
                ->orderBy('f.vencimiento', 'asc')->get();
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Pendientes de corte TV
    // ──────────────────────────────────────────────────────────────────────────

    public function getPendingTvCuts(int $grupoCorteId, ?string $fecha = null): \Illuminate\Support\Collection
    {
        $fecha    = $fecha ?? now()->format('Y-m-d');
        $cacheKey = "cortes_analyzer_pending_tv_{$grupoCorteId}_{$fecha}";

        return Cache::remember($cacheKey, self::TTL_PENDING, function () use ($grupoCorteId, $fecha) {
            $grupo      = GrupoCorte::find($grupoCorteId);
            $prorroga   = (int) ($grupo->prorroga_tv ?? 0);
            $fechaLimite = Carbon::parse($fecha)->subDays($prorroga)->format('Y-m-d');

            return DB::table('contracts as cs')
                ->join('contactos', 'contactos.id', '=', 'cs.client_id')
                ->join('facturas_contratos as fcs', 'fcs.contrato_nro', '=', 'cs.nro')
                ->join('factura as f', 'f.id', '=', 'fcs.factura_id')
                ->leftJoin('promesa_pago as pp', function ($join) use ($fecha) {
                    $join->on('pp.factura', '=', 'f.id')->where('pp.vencimiento', '>=', $fecha);
                })
                ->select(
                    'cs.id as contrato_id', 'cs.nro as contrato_nro',
                    'cs.olt_sn_mac', 'cs.serial_onu', 'cs.state_olt_catv',
                    'cs.fecha_suspension',
                    'contactos.id as cliente_id', 'contactos.nombre as cliente_nombre',
                    'contactos.nit as cliente_nit',
                    'f.id as factura_id', 'f.vencimiento',
                    DB::raw('DATEDIFF(NOW(), f.vencimiento) as dias_vencida'),
                    DB::raw("DATEDIFF(NOW(), f.vencimiento) - {$prorroga} as dias_sobre_prorroga")
                )
                ->where('f.estatus', 1)->whereIn('f.tipo', [1, 2])
                ->where('contactos.status', 1)->where('cs.status', 1)
                ->where('cs.grupo_corte', $grupoCorteId)
                ->whereNull('cs.fecha_suspension')
                ->where('cs.state_olt_catv', true)
                ->whereNotNull('cs.olt_sn_mac')
                ->whereDate('f.vencimiento', '<=', $fechaLimite)
                ->whereNull('pp.id')
                ->whereIn('f.id', function ($sub) {
                    $sub->selectRaw('MAX(f2.id)')->from('factura as f2')
                        ->join('facturas_contratos as fcs2', 'fcs2.factura_id', '=', 'f2.id')
                        ->whereColumn('fcs2.contrato_nro', 'cs.nro')
                        ->where('f2.estatus', 1)->whereIn('f2.tipo', [1, 2])
                        ->whereDate('f2.vencimiento', '<=', now())
                        ->groupBy('fcs2.contrato_nro');
                })
                ->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))->from('factura as f_newer')
                        ->join('facturas_contratos as fcs_newer', 'fcs_newer.factura_id', '=', 'f_newer.id')
                        ->whereColumn('fcs_newer.contrato_nro', 'cs.nro')
                        ->whereIn('f_newer.tipo', [1, 2])->where('f_newer.estatus', 0)
                        ->whereColumn('f_newer.vencimiento', '>', 'f.vencimiento');
                })
                ->orderBy('f.vencimiento', 'asc')->get();
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Ya cortados
    // ──────────────────────────────────────────────────────────────────────────

    public function getAlreadyCutInternet(int $grupoCorteId): \Illuminate\Support\Collection
    {
        return Cache::remember("cortes_analyzer_cut_internet_{$grupoCorteId}", self::TTL_PENDING, function () use ($grupoCorteId) {
            return DB::table('contracts as cs')
                ->join('contactos', 'contactos.id', '=', 'cs.client_id')
                ->leftJoin('mikrotik as mk', 'mk.id', '=', 'cs.server_configuration_id')
                ->leftJoin('log_movimientos as lm', function ($join) {
                    $join->on('lm.contrato', '=', 'cs.id')
                         ->where('lm.modulo', 5)
                         ->whereRaw('lm.id = (SELECT MAX(lm2.id) FROM log_movimientos lm2 WHERE lm2.contrato = cs.id AND lm2.modulo = 5)');
                })
                ->select(
                    'cs.id as contrato_id', 'cs.nro as contrato_nro',
                    'cs.ip', 'cs.usuario', 'cs.conexion', 'cs.serial_onu',
                    'cs.server_configuration_id as mikrotik_id',
                    'mk.nombre as mikrotik_nombre',
                    'contactos.id as cliente_id', 'contactos.nombre as cliente_nombre',
                    'contactos.nit as cliente_nit',
                    'lm.created_at as fecha_corte'
                )
                ->where('cs.grupo_corte', $grupoCorteId)
                ->where('cs.status', 1)->where('cs.state', 'disabled')
                ->orderBy('lm.created_at', 'desc')->get();
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Razones de bloqueo
    // ──────────────────────────────────────────────────────────────────────────

    public function getBlockedReasons(int $grupoCorteId): array
    {
        return Cache::remember("cortes_analyzer_reasons_{$grupoCorteId}", self::TTL_PENDING, function () use ($grupoCorteId) {
            $fecha = now()->format('Y-m-d');

            $base = DB::table('contracts as cs')
                ->join('contactos', 'contactos.id', '=', 'cs.client_id')
                ->join('facturas_contratos as fcs', 'fcs.contrato_nro', '=', 'cs.nro')
                ->join('factura as f', 'f.id', '=', 'fcs.factura_id')
                ->select(
                    'cs.id as contrato_id', 'cs.nro', 'cs.ip', 'cs.state',
                    'cs.fecha_suspension', 'cs.tipo_nosuspension',
                    'cs.fecha_desde_nosuspension', 'cs.fecha_hasta_nosuspension',
                    'cs.server_configuration_id', 'cs.olt_sn_mac', 'cs.serial_onu',
                    'contactos.id as cliente_id', 'contactos.nombre as cliente_nombre', 'contactos.nit as cliente_nit',
                    'f.id as factura_id', 'f.vencimiento',
                    DB::raw('DATEDIFF(NOW(), f.vencimiento) as dias_vencida')
                )
                ->where('f.estatus', 1)->whereIn('f.tipo', [1, 2])
                ->where('contactos.status', 1)->where('cs.status', 1)
                ->where('cs.state', 'enabled')->where('cs.grupo_corte', $grupoCorteId)
                ->whereDate('f.vencimiento', '<=', $fecha)
                ->whereIn('f.id', function ($sub) {
                    $sub->selectRaw('MAX(f2.id)')->from('factura as f2')
                        ->join('facturas_contratos as fcs2', 'fcs2.factura_id', '=', 'f2.id')
                        ->whereColumn('fcs2.contrato_nro', 'cs.nro')
                        ->where('f2.estatus', 1)->whereIn('f2.tipo', [1, 2])
                        ->whereDate('f2.vencimiento', '<=', now())
                        ->groupBy('fcs2.contrato_nro');
                })
                ->groupBy('cs.id', 'f.id')->get();

            $conPromesa = DB::table('promesa_pago')
                ->whereIn('factura', $base->pluck('factura_id'))
                ->where('vencimiento', '>=', $fecha)
                ->pluck('factura')->flip()->toArray();

            $reasons = [
                'promesa_pago'  => [],
                'no_suspension' => [],
                'sin_mikrotik'  => [],
                'sin_ip'        => [],
                'ya_suspendido' => [],
            ];

            foreach ($base as $row) {
                if (isset($conPromesa[$row->factura_id])) {
                    $reasons['promesa_pago'][] = $row; continue;
                }
                if ($row->fecha_suspension && $row->fecha_suspension != 0) {
                    $reasons['ya_suspendido'][] = $row; continue;
                }
                if ($row->tipo_nosuspension == 1
                    && $row->fecha_desde_nosuspension <= $fecha
                    && $row->fecha_hasta_nosuspension >= $fecha) {
                    $reasons['no_suspension'][] = $row; continue;
                }
                if (! $row->server_configuration_id && ! $row->olt_sn_mac) {
                    $reasons['sin_mikrotik'][] = $row; continue;
                }
                if ($row->server_configuration_id && ! filter_var($row->ip ?? '', FILTER_VALIDATE_IP)) {
                    $reasons['sin_ip'][] = $row;
                }
            }

            return [
                'total_bloqueados' => $base->count(),
                'razones'          => array_map(fn ($list) => count($list), $reasons),
                'detalle'          => $reasons,
            ];
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Vista unificada: todos los contratos con su estado clasificado
    // ──────────────────────────────────────────────────────────────────────────

    public function getAllContractsForCutView(int $grupoCorteId): array
    {
        return Cache::remember("cortes_analyzer_all_contracts_{$grupoCorteId}", self::TTL_ALL_CONTRACTS, function () use ($grupoCorteId) {
            $grupo = GrupoCorte::find($grupoCorteId);
            if (! $grupo) return ['contratos' => [], 'stats_internet' => [], 'stats_tv' => []];

            $fecha    = now()->format('Y-m-d');
            $prorroga = (int) ($grupo->prorroga_tv ?? 0);
            $fechaTv  = Carbon::parse($fecha)->subDays($prorroga)->format('Y-m-d');

            $subUltimaFactura = DB::raw("(
                SELECT fcs2.contrato_nro,
                    MAX(f2.id) AS ultima_factura_id,
                    MAX(f2.vencimiento) AS ultima_vencimiento,
                    MAX(DATEDIFF(NOW(), f2.vencimiento)) AS dias_vencida,
                    MAX(CASE WHEN f2.vencimiento <= '{$fecha}'   THEN 1 ELSE 0 END) AS tiene_factura_vencida,
                    MAX(CASE WHEN f2.vencimiento <= '{$fechaTv}' THEN 1 ELSE 0 END) AS tiene_factura_vencida_tv
                FROM factura f2
                INNER JOIN facturas_contratos fcs2 ON fcs2.factura_id = f2.id
                WHERE f2.estatus = 1 AND f2.tipo IN (1, 2)
                GROUP BY fcs2.contrato_nro
            ) AS uf");

            $subFechaCorte = DB::raw("(
                SELECT contrato, MAX(created_at) as fecha_corte
                FROM log_movimientos WHERE modulo = 5
                GROUP BY contrato
            ) AS lm_corte");

            $subAbsoluteFactura = DB::raw("(
                SELECT fcs2.contrato_nro,
                    MAX(f2.estatus) as abs_estatus,
                    MAX(f2.vencimiento) as abs_vencimiento
                FROM factura f2
                INNER JOIN facturas_contratos fcs2 ON fcs2.factura_id = f2.id
                WHERE f2.id = (
                    SELECT MAX(f3.id) FROM factura f3
                    INNER JOIN facturas_contratos fcs3 ON fcs3.factura_id = f3.id
                    WHERE fcs3.contrato_nro = fcs2.contrato_nro AND f3.tipo IN (1, 2)
                )
                GROUP BY fcs2.contrato_nro
            ) AS abs_f");

            $contratos = DB::table('contracts as cs')
                ->join('contactos', 'contactos.id', '=', 'cs.client_id')
                ->leftJoin('mikrotik as mk', 'mk.id', '=', 'cs.server_configuration_id')
                ->leftJoin($subUltimaFactura, 'uf.contrato_nro', '=', 'cs.nro')
                ->leftJoin($subFechaCorte, 'lm_corte.contrato', '=', 'cs.id')
                ->leftJoin($subAbsoluteFactura, 'abs_f.contrato_nro', '=', 'cs.nro')
                ->select(
                    'cs.id as contrato_id', 'cs.nro as contrato_nro',
                    'cs.ip', 'cs.state', 'cs.state_olt_catv',
                    'cs.usuario', 'cs.conexion', 'cs.serial_onu', 'cs.olt_sn_mac',
                    'cs.server_configuration_id as mikrotik_id',
                    'cs.fecha_suspension', 'cs.tipo_nosuspension',
                    'cs.fecha_desde_nosuspension', 'cs.fecha_hasta_nosuspension',
                    'mk.nombre as mikrotik_nombre', 'mk.ip as mikrotik_ip',
                    'contactos.id as cliente_id', 'contactos.nombre as cliente_nombre',
                    'contactos.nit as cliente_nit',
                    'uf.ultima_factura_id', 'uf.ultima_vencimiento', 'uf.dias_vencida',
                    'uf.tiene_factura_vencida', 'uf.tiene_factura_vencida_tv',
                    'lm_corte.fecha_corte',
                    'abs_f.abs_estatus', 'abs_f.abs_vencimiento'
                )
                ->where('cs.grupo_corte', $grupoCorteId)
                ->where('cs.status', 1)->where('contactos.status', 1)
                ->orderBy('contactos.nombre')->get();

            $facturaIds = $contratos->pluck('ultima_factura_id')->filter()->values()->toArray();
            $conPromesa = [];
            if (! empty($facturaIds)) {
                $conPromesa = DB::table('promesa_pago')
                    ->whereIn('factura', $facturaIds)->where('vencimiento', '>=', $fecha)
                    ->pluck('factura')->flip()->toArray();
            }

            $statsInternet = ['activo_ok'=>0,'pendiente_corte'=>0,'factura_antigua_vencida'=>0,
                'bloqueado_promesa'=>0,'bloqueado_no_suspension'=>0,'bloqueado_sin_mk'=>0,
                'bloqueado_sin_ip'=>0,'bloqueado_suspension'=>0,'ya_cortado'=>0];
            $statsTv = ['sin_tv'=>0,'tv_ok'=>0,'pendiente_corte_tv'=>0,'bloqueado_tv_promesa'=>0,'ya_cortado_tv'=>0];

            $result = [];
            foreach ($contratos as $c) {
                $tienePromesa     = isset($conPromesa[$c->ultima_factura_id]);
                $facturaVencida   = (bool) $c->tiene_factura_vencida;
                $facturaVencidaTv = (bool) $c->tiene_factura_vencida_tv;
                $noSuspension     = $c->tipo_nosuspension == 1
                    && $c->fecha_desde_nosuspension <= $fecha
                    && $c->fecha_hasta_nosuspension >= $fecha;

                $facturaAntigua = $facturaVencida
                    && $c->abs_estatus !== null && (int) $c->abs_estatus === 0
                    && $c->abs_vencimiento !== null
                    && $c->abs_vencimiento > $c->ultima_vencimiento;

                if ($c->state === 'disabled') {
                    $estadoInternet = 'ya_cortado';
                } elseif (! $facturaVencida) {
                    $estadoInternet = 'activo_ok';
                } elseif ($facturaAntigua) {
                    $estadoInternet = 'factura_antigua_vencida';
                } elseif ($tienePromesa) {
                    $estadoInternet = 'bloqueado_promesa';
                } elseif ($noSuspension) {
                    $estadoInternet = 'bloqueado_no_suspension';
                } elseif (! $c->mikrotik_id && ! $c->serial_onu) {
                    $estadoInternet = 'bloqueado_sin_mk';
                } elseif (! $c->ip || ! filter_var($c->ip, FILTER_VALIDATE_IP)) {
                    $estadoInternet = 'bloqueado_sin_ip';
                } elseif ($c->fecha_suspension) {
                    // El cron (getPendingInternetCuts) excluye contratos con fecha_suspension != null,
                    // así que NO son "pendiente_corte": tienen una suspensión programada/manual.
                    $estadoInternet = 'bloqueado_suspension';
                } else {
                    $estadoInternet = 'pendiente_corte';
                }

                $facturaAntiguaTv = $facturaVencidaTv
                    && $c->abs_estatus !== null && (int) $c->abs_estatus === 0
                    && $c->abs_vencimiento !== null
                    && $c->abs_vencimiento > $c->ultima_vencimiento;

                if (! $c->olt_sn_mac) {
                    $estadoTv = 'sin_tv';
                } elseif (! $c->state_olt_catv) {
                    $estadoTv = 'ya_cortado_tv';
                } elseif ($c->fecha_suspension) {
                    $estadoTv = 'tv_ok';
                } elseif (! $facturaVencidaTv) {
                    $estadoTv = 'tv_ok';
                } elseif ($facturaAntiguaTv) {
                    $estadoTv = 'tv_ok';
                } elseif ($tienePromesa) {
                    $estadoTv = 'bloqueado_tv_promesa';
                } else {
                    $estadoTv = 'pendiente_corte_tv';
                }

                $statsInternet[$estadoInternet]++;
                $statsTv[$estadoTv]++;

                $row = (array) $c;
                $row['estado_internet'] = $estadoInternet;
                $row['estado_tv']       = $estadoTv;
                $row['tiene_promesa']   = $tienePromesa;
                $row['dias_vencida']    = max(0, (int) ($c->dias_vencida ?? 0));
                $result[] = $row;
            }

            return [
                'contratos'      => $result,
                'stats_internet' => $statsInternet,
                'stats_tv'       => $statsTv,
                'total'          => count($result),
                'prorroga_tv'    => $prorroga,
            ];
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Sincronización MikroTik ↔ BD
    // ──────────────────────────────────────────────────────────────────────────

    public function getMikrotikSyncAnalysis(int $mikrotikId, int $grupoCorteId): array
    {
        $cacheKey = "cortes_mk_sync_{$mikrotikId}_{$grupoCorteId}";

        return Cache::remember($cacheKey, self::TTL_MK_SYNC, function () use ($mikrotikId, $grupoCorteId) {
            $mikrotik = Mikrotik::find($mikrotikId);
            if (! $mikrotik) return ['error' => 'MikroTik no encontrado', 'disponible' => false];

            try {
                $API = new RouterosAPI();
                $API->port = (int) $mikrotik->puerto_api;

                if (! $API->connect($mikrotik->ip, $mikrotik->usuario, $mikrotik->clave)) {
                    return ['disponible' => false, 'error' => 'No se pudo conectar a MikroTik: '.$mikrotik->ip,
                            'mikrotik' => ['id' => $mikrotikId, 'nombre' => $mikrotik->nombre, 'ip' => $mikrotik->ip]];
                }

                $rawMorosos = $API->comm('/ip/firewall/address-list/print', ['?list' => 'morosos']);

                $API->write('/ppp/secret/print', false);
                $API->write('?disabled=yes');
                $rawSecrets = $API->read();

                $API->write('/ppp/active/print');
                $rawActive = $API->read();

                $API->disconnect();

                $ipsMorososMk        = collect($rawMorosos)->where('list', 'morosos')->pluck('address')->filter()->unique()->values()->toArray();
                $secretsDisabledNames = collect($rawSecrets)->pluck('name')->filter()->unique()->values()->toArray();
                $activeSessionNames   = collect($rawActive)->pluck('name')->filter()->unique()->values()->toArray();

                $contratosCortadosBd = DB::table('contracts as cs')
                    ->join('contactos', 'contactos.id', '=', 'cs.client_id')
                    ->select('cs.id', 'cs.ip', 'cs.usuario', 'cs.conexion', 'contactos.nombre', 'contactos.nit')
                    ->where('cs.grupo_corte', $grupoCorteId)
                    ->where('cs.server_configuration_id', $mikrotikId)
                    ->where('cs.state', 'disabled')->where('cs.status', 1)->get();

                $contratosActivosBd = DB::table('contracts as cs')
                    ->select('cs.id', 'cs.ip', 'cs.usuario', 'cs.conexion')
                    ->where('cs.grupo_corte', $grupoCorteId)
                    ->where('cs.server_configuration_id', $mikrotikId)
                    ->where('cs.state', 'enabled')->where('cs.status', 1)->get();

                $ipsActivasBd  = $contratosActivosBd->pluck('ip')->filter()->unique()->values()->toArray();
                $ipsCortadasBd = $contratosCortadosBd->pluck('ip')->filter()->unique()->values()->toArray();

                $ipsMorososSinContrato       = array_values(array_diff($ipsMorososMk, array_merge($ipsCortadasBd, $ipsActivasBd)));
                $contratosCortadosSinMorosos = $contratosCortadosBd->filter(fn ($c) => $c->ip && ! in_array($c->ip, $ipsMorososMk));
                $contratosEnabledEnMorosos   = $contratosActivosBd->filter(fn ($c) => $c->ip && in_array($c->ip, $ipsMorososMk));
                $pppoeBypass                 = $contratosCortadosBd->filter(fn ($c) => $c->usuario && in_array($c->usuario, $activeSessionNames));

                return [
                    'disponible'             => true,
                    'mikrotik'               => ['id' => $mikrotikId, 'nombre' => $mikrotik->nombre, 'ip' => $mikrotik->ip],
                    'morosos_mk_count'       => count($ipsMorososMk),
                    'secrets_disabled_count' => count($secretsDisabledNames),
                    'active_sessions_count'  => count($rawActive),
                    'cortados_bd_count'      => $contratosCortadosBd->count(),
                    'inconsistencias'        => [
                        'ips_morosos_sin_contrato'   => $ipsMorososSinContrato,
                        'cortados_sin_morosos_count' => $contratosCortadosSinMorosos->count(),
                        'cortados_sin_morosos'       => $contratosCortadosSinMorosos->values(),
                        'enabled_en_morosos_count'   => $contratosEnabledEnMorosos->count(),
                        'enabled_en_morosos'         => $contratosEnabledEnMorosos->values(),
                        'pppoe_bypass_count'         => $pppoeBypass->count(),
                        'pppoe_bypass'               => $pppoeBypass->values(),
                    ],
                ];
            } catch (\Throwable $e) {
                return ['disponible' => false, 'error' => $e->getMessage(),
                        'mikrotik'   => ['id' => $mikrotikId, 'nombre' => $mikrotik->nombre, 'ip' => $mikrotik->ip]];
            }
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Historial
    // ──────────────────────────────────────────────────────────────────────────

    public function getCutHistory(int $grupoCorteId, int $limit = 30): array
    {
        return Cache::remember("cortes_analyzer_history_{$grupoCorteId}_{$limit}", self::TTL_HISTORY, function () use ($grupoCorteId, $limit) {
            $logs = DB::table('cron_cortes_logs')
                ->leftJoin('usuarios', 'usuarios.id', '=', 'cron_cortes_logs.ejecutado_por')
                ->select('cron_cortes_logs.*', DB::raw("IFNULL(usuarios.nombre, 'CRON') as ejecutado_por_nombre"))
                ->where('cron_cortes_logs.grupo_corte_id', $grupoCorteId)
                ->orderByDesc('cron_cortes_logs.created_at')->limit($limit)->get();

            $resumen = DB::table('cron_cortes_logs')
                ->where('grupo_corte_id', $grupoCorteId)
                ->selectRaw("
                    COUNT(*) as total_ejecuciones,
                    SUM(total_cortados) as total_cortados_historico,
                    SUM(total_errores) as total_errores_historico,
                    AVG(duracion_ms) as duracion_promedio_ms,
                    MAX(created_at) as ultima_ejecucion,
                    SUM(CASE WHEN tipo='internet' THEN total_cortados ELSE 0 END) as cortados_internet,
                    SUM(CASE WHEN tipo='tv' THEN total_cortados ELSE 0 END) as cortados_tv
                ")->first();

            return ['logs' => $logs, 'resumen' => $resumen];
        });
    }

    public function getCutHistoryDetail(int $logId): array
    {
        $log = DB::table('cron_cortes_logs')->find($logId);
        if (! $log) return ['error' => 'Log no encontrado'];

        $detalle = DB::table('cron_cortes_detalle as d')
            ->leftJoin('contracts as cs', 'cs.id', '=', 'd.contrato_id')
            ->leftJoin('contactos', 'contactos.id', '=', 'd.cliente_id')
            ->leftJoin('mikrotik as mk', 'mk.id', '=', 'd.mikrotik_id')
            ->select('d.*', 'cs.nro as contrato_nro',
                     'contactos.nombre as cliente_nombre', 'contactos.nit as cliente_nit',
                     'mk.nombre as mikrotik_nombre')
            ->where('d.log_id', $logId)
            ->orderBy('d.resultado')->orderBy('d.id')->get();

        return ['log' => $log, 'detalle' => $detalle, 'resumen' => $detalle->groupBy('resultado')->map->count()];
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Helpers de conteo
    // ──────────────────────────────────────────────────────────────────────────

    private function countPendingInternetCuts(int $grupoCorteId, string $fecha): int
    {
        return DB::table('contracts as cs')
            ->join('facturas_contratos as fcs', 'fcs.contrato_nro', '=', 'cs.nro')
            ->join('factura as f', 'f.id', '=', 'fcs.factura_id')
            ->join('contactos', 'contactos.id', '=', 'cs.client_id')
            ->where('f.estatus', 1)->whereIn('f.tipo', [1, 2])
            ->where('contactos.status', 1)->where('cs.state', 'enabled')
            ->where('cs.status', 1)->where('cs.grupo_corte', $grupoCorteId)
            ->whereNull('cs.fecha_suspension')
            ->whereDate('f.vencimiento', '<=', $fecha)
            ->whereNotExists(fn ($q) => $q->from('promesa_pago')
                ->whereColumn('promesa_pago.factura', 'f.id')
                ->where('promesa_pago.vencimiento', '>=', $fecha))
            ->where(fn ($q) => $q->where('cs.tipo_nosuspension', '!=', 1)
                ->orWhere('cs.fecha_desde_nosuspension', '>', $fecha)
                ->orWhere('cs.fecha_hasta_nosuspension', '<', $fecha))
            ->whereIn('f.id', fn ($sub) => $sub->selectRaw('MAX(f2.id)')->from('factura as f2')
                ->join('facturas_contratos as fcs2', 'fcs2.factura_id', '=', 'f2.id')
                ->whereColumn('fcs2.contrato_nro', 'cs.nro')
                ->where('f2.estatus', 1)->whereIn('f2.tipo', [1, 2])
                ->whereDate('f2.vencimiento', '<=', now())->groupBy('fcs2.contrato_nro'))
            ->whereNotExists(fn ($sub) => $sub->select(DB::raw(1))->from('factura as f_newer')
                ->join('facturas_contratos as fcs_newer', 'fcs_newer.factura_id', '=', 'f_newer.id')
                ->whereColumn('fcs_newer.contrato_nro', 'cs.nro')
                ->whereIn('f_newer.tipo', [1, 2])->where('f_newer.estatus', 0)
                ->whereColumn('f_newer.vencimiento', '>', 'f.vencimiento'))
            ->distinct('cs.id')->count('cs.id');
    }

    private function countPendingTvCuts(int $grupoCorteId, string $fecha): int
    {
        $grupo      = GrupoCorte::find($grupoCorteId);
        $prorroga   = (int) ($grupo->prorroga_tv ?? 0);
        $fechaLimite = Carbon::parse($fecha)->subDays($prorroga)->format('Y-m-d');

        return DB::table('contracts as cs')
            ->join('facturas_contratos as fcs', 'fcs.contrato_nro', '=', 'cs.nro')
            ->join('factura as f', 'f.id', '=', 'fcs.factura_id')
            ->join('contactos', 'contactos.id', '=', 'cs.client_id')
            ->where('f.estatus', 1)->whereIn('f.tipo', [1, 2])
            ->where('contactos.status', 1)->where('cs.status', 1)
            ->where('cs.grupo_corte', $grupoCorteId)->whereNull('cs.fecha_suspension')
            ->where('cs.state_olt_catv', true)->whereNotNull('cs.olt_sn_mac')
            ->whereDate('f.vencimiento', '<=', $fechaLimite)
            ->whereNotExists(fn ($q) => $q->from('promesa_pago')
                ->whereColumn('promesa_pago.factura', 'f.id')
                ->where('promesa_pago.vencimiento', '>=', $fecha))
            ->whereIn('f.id', fn ($sub) => $sub->selectRaw('MAX(f2.id)')->from('factura as f2')
                ->join('facturas_contratos as fcs2', 'fcs2.factura_id', '=', 'f2.id')
                ->whereColumn('fcs2.contrato_nro', 'cs.nro')
                ->where('f2.estatus', 1)->whereIn('f2.tipo', [1, 2])
                ->whereDate('f2.vencimiento', '<=', now())->groupBy('fcs2.contrato_nro'))
            ->whereNotExists(fn ($sub) => $sub->select(DB::raw(1))->from('factura as f_newer')
                ->join('facturas_contratos as fcs_newer', 'fcs_newer.factura_id', '=', 'f_newer.id')
                ->whereColumn('fcs_newer.contrato_nro', 'cs.nro')
                ->whereIn('f_newer.tipo', [1, 2])->where('f_newer.estatus', 0)
                ->whereColumn('f_newer.vencimiento', '>', 'f.vencimiento'))
            ->distinct('cs.id')->count('cs.id');
    }
}
