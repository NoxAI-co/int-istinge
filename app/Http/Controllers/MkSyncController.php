<?php

namespace App\Http\Controllers;

use App\Contrato;
use App\GrupoCorte;
use App\Mikrotik;
use App\PlanesVelocidad;
use App\Services\MikrotikContratoSyncService;
use Auth;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

include_once(app_path() . '/../public/routeros_api.class.php');
use RouterosAPI;

/**
 * Sincronización masiva de contratos a MikroTik por lotes, con progreso en vivo.
 *
 * Flujo: se arma una ORDEN (mk_sync_lotes) con TODOS los contratos que aplican al
 * filtro (mikrotik + estado + grupo de corte + plan) y sus renglones (mk_sync_items).
 * Luego un procesador avanza en TANDAS cortas — reutilizando UNA sola conexión al
 * router por tanda — y actualiza el progreso. El frontend dispara las tandas y
 * refleja el %, y el cron puede empujar órdenes pendientes de forma desatendida.
 *
 * NO define permisos propios: vive bajo el módulo Cron Jobs (permiso 861 del menú).
 */
class MkSyncController extends Controller
{
    /** Contratos procesados por tanda. Corto → cada request dura segundos (evita timeouts). */
    const CHUNK = 12;

    /** Tandas máximas que procesa el cron por orden en una corrida (para no colgarlo). */
    const CRON_MAX_CHUNKS = 25;

    /** @var MikrotikContratoSyncService */
    private $sync;

    public function __construct()
    {
        $this->sync = new MikrotikContratoSyncService();
    }

    // ─── Página ───────────────────────────────────────────────────────────────

    public function index()
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->getAllPermissions(Auth::user()->id);

        $empresa = Auth::user()->empresa;

        $mikrotiks = Mikrotik::where('empresa', $empresa)->orderBy('nombre')->get(['id', 'nombre', 'ip', 'status']);
        $grupos    = GrupoCorte::where('empresa', $empresa)->orderBy('nombre')->get(['id', 'nombre']);
        $planes    = PlanesVelocidad::where('empresa', $empresa)->orderBy('name')->get(['id', 'name']);

        // En BDs legacy las tablas del módulo pueden no existir todavía (se crean con
        // la migración o con fix-legacy-columns.sh). Se avisa en pantalla en vez de
        // reventar con "1146 Table doesn't exist".
        $sinTablas = ! $this->tablasListas();
        $activo    = null;
        $historial = collect();

        if (! $sinTablas) {
            // Orden activa (si la hay) para reenganchar la barra de progreso al entrar.
            $loteActivo = DB::table('mk_sync_lotes')->where('empresa', $empresa)
                ->whereIn('estado', ['pendiente', 'ejecutando'])
                ->orderBy('id', 'desc')->first();
            $activo = $loteActivo ? $this->progreso($loteActivo->id) : null;

            $historial = DB::table('mk_sync_lotes as l')
                ->leftJoin('mikrotik as m', 'm.id', '=', 'l.mikrotik_id')
                ->where('l.empresa', $empresa)
                ->orderBy('l.id', 'desc')->limit(15)
                ->get(['l.id', 'l.estado', 'l.total', 'l.correctos', 'l.fallidos', 'l.created_at', 'l.fin', 'm.nombre as mikrotik_nombre']);
        }

        view()->share([
            'seccion'    => 'cronjobs',
            'title'      => 'Sincronización Masiva MikroTik',
            'icon'       => 'fas fa-broadcast-tower',
            'subseccion' => 'cronjobs-sincronizacion-mikrotik',
        ]);

        return view('cronjobs.sincronizacion-mikrotik', compact('mikrotiks', 'grupos', 'planes', 'activo', 'historial', 'sinTablas'));
    }

    /** ¿Existen las tablas del módulo? (BDs legacy pueden no tenerlas aún). */
    private function tablasListas()
    {
        return Schema::hasTable('mk_sync_lotes') && Schema::hasTable('mk_sync_items');
    }

    // ─── Query de contratos por filtro ────────────────────────────────────────

    private function contratosQuery($empresa, array $f)
    {
        $q = DB::table('contracts')
            ->where('empresa', $empresa)
            ->where('server_configuration_id', $f['mikrotik_id'])
            ->whereNotNull('ip')->where('ip', '<>', '');

        if (isset($f['status']) && $f['status'] !== '' && $f['status'] !== null && $f['status'] !== 'all') {
            $q->where('status', (int) $f['status']);
        }
        if (! empty($f['grupo_corte'])) {
            $q->where('grupo_corte', $f['grupo_corte']);
        }
        if (! empty($f['plan_id'])) {
            $q->where('plan_id', $f['plan_id']);
        }

        return $q;
    }

    private function filtrosDeRequest(Request $request)
    {
        return [
            'mikrotik_id' => (int) $request->input('mikrotik_id'),
            'status'      => $request->input('status'),
            'grupo_corte' => $request->input('grupo_corte'),
            'plan_id'     => $request->input('plan_id'),
        ];
    }

    // ─── Previsualizar: cuántos contratos aplican ─────────────────────────────

    public function previsualizar(Request $request)
    {
        $request->validate(['mikrotik_id' => 'required|integer']);
        $empresa = Auth::user()->empresa;
        $total = $this->contratosQuery($empresa, $this->filtrosDeRequest($request))->count();

        return response()->json(['success' => true, 'total' => $total]);
    }

    // ─── Crear la orden ───────────────────────────────────────────────────────

    public function crear(Request $request)
    {
        $request->validate(['mikrotik_id' => 'required|integer']);
        if (! $this->tablasListas()) {
            return response()->json(['success' => false, 'message' => 'El módulo aún no está instalado en esta base de datos (faltan las tablas mk_sync_*).']);
        }
        $empresa = Auth::user()->empresa;
        $filtros = $this->filtrosDeRequest($request);

        $mikrotik = Mikrotik::where('empresa', $empresa)->find($filtros['mikrotik_id']);
        if (! $mikrotik) {
            return response()->json(['success' => false, 'message' => 'MikroTik no encontrada.']);
        }

        // Una sola orden activa por MikroTik a la vez.
        $enCurso = DB::table('mk_sync_lotes')->where('empresa', $empresa)
            ->where('mikrotik_id', $filtros['mikrotik_id'])
            ->whereIn('estado', ['pendiente', 'ejecutando'])->first();
        if ($enCurso) {
            return response()->json([
                'success' => false,
                'message' => 'Ya hay una sincronización en curso para esta MikroTik.',
                'lote_id' => $enCurso->id,
            ]);
        }

        $contratos = $this->contratosQuery($empresa, $filtros)->get(['id', 'nro', 'ip']);
        if ($contratos->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No hay contratos que coincidan con el filtro (con IP asignada).']);
        }

        $ahora = date('Y-m-d H:i:s');

        $loteId = DB::table('mk_sync_lotes')->insertGetId([
            'empresa'     => $empresa,
            'mikrotik_id' => $filtros['mikrotik_id'],
            'estado'      => 'pendiente',
            'total'       => $contratos->count(),
            'procesados'  => 0,
            'correctos'   => 0,
            'fallidos'    => 0,
            'filtros'     => json_encode($filtros),
            'created_by'  => Auth::id(),
            'created_at'  => $ahora,
            'updated_at'  => $ahora,
        ]);

        foreach ($contratos->chunk(500) as $chunk) {
            $rows = [];
            foreach ($chunk as $c) {
                $rows[] = [
                    'lote_id'      => $loteId,
                    'contrato_id'  => $c->id,
                    'contrato_nro' => $c->nro,
                    'ip'           => $c->ip,
                    'estado'       => 'pendiente',
                    'mensaje'      => null,
                    'intentos'     => 0,
                    'created_at'   => $ahora,
                    'updated_at'   => $ahora,
                ];
            }
            DB::table('mk_sync_items')->insert($rows);
        }

        return response()->json([
            'success' => true,
            'lote_id' => $loteId,
            'total'   => $contratos->count(),
        ]);
    }

    // ─── Procesar una tanda ───────────────────────────────────────────────────

    public function procesar(Request $request)
    {
        @set_time_limit(0);
        @ignore_user_abort(true);

        $empresa = Auth::user()->empresa;
        $lote = DB::table('mk_sync_lotes')->where('empresa', $empresa)
            ->where('id', (int) $request->input('lote_id'))->first();
        if (! $lote) {
            return response()->json(['success' => false, 'message' => 'Orden no encontrada.']);
        }
        if (in_array($lote->estado, ['completado', 'cancelado'])) {
            return response()->json(['success' => true, 'done' => true, 'progreso' => $this->progreso($lote->id)]);
        }

        return response()->json($this->procesarTanda($lote->id, $empresa, Auth::id()));
    }

    /**
     * Procesa UNA tanda de la orden con lock anti-concurrencia (frontend + cron).
     * Devuelve el progreso y flags {done, busy, connected}.
     */
    private function procesarTanda($loteId, $empresa, $userId)
    {
        // ── Lock: reclamar la orden si está libre o el lock venció ──
        $token = (string) Str::uuid();
        $ahora = date('Y-m-d H:i:s');
        $vence = date('Y-m-d H:i:s', strtotime('+90 seconds'));

        $claimed = DB::table('mk_sync_lotes')->where('id', $loteId)
            ->whereIn('estado', ['pendiente', 'ejecutando'])
            ->where(function ($w) use ($ahora) {
                $w->whereNull('lock_expires')->orWhere('lock_expires', '<', $ahora);
            })
            ->update(['lock_token' => $token, 'lock_expires' => $vence, 'updated_at' => $ahora]);

        if (! $claimed) {
            // Otra tanda la tiene tomada (o ya terminó): devolver progreso sin tocar.
            return ['success' => true, 'busy' => true, 'progreso' => $this->progreso($loteId)];
        }

        try {
            DB::table('mk_sync_lotes')->where('id', $loteId)->update([
                'estado' => 'ejecutando',
                'inicio' => DB::raw('COALESCE(inicio, NOW())'),
            ]);

            $items = DB::table('mk_sync_items')->where('lote_id', $loteId)
                ->where('estado', 'pendiente')->orderBy('id')->limit(self::CHUNK)->get();

            if ($items->isEmpty()) {
                $this->finalizarSiCompleto($loteId);

                return ['success' => true, 'done' => true, 'progreso' => $this->progreso($loteId)];
            }

            $lote = DB::table('mk_sync_lotes')->where('id', $loteId)->first();
            $mikrotik = Mikrotik::where('empresa', $empresa)->find($lote->mikrotik_id);
            if (! $mikrotik) {
                return ['success' => false, 'message' => 'MikroTik no encontrada.', 'progreso' => $this->progreso($loteId)];
            }
            if (isset($mikrotik->status) && ! $mikrotik->status) {
                return ['success' => false, 'connected' => false,
                    'message' => "La MikroTik «{$mikrotik->nombre}» está marcada como desconectada.",
                    'progreso' => $this->progreso($loteId)];
            }

            // ── UNA sola conexión para toda la tanda ──
            $API = new RouterosAPI();
            $API->port    = (int) $mikrotik->puerto_api;
            $API->timeout = 4;

            if (! $API->connect($mikrotik->ip, $mikrotik->usuario, $mikrotik->clave)) {
                // Sin conexión: NO marcamos los ítems (siguen pendientes) para reintentar.
                return ['success' => false, 'connected' => false,
                    'message' => "No se pudo conectar a la MikroTik «{$mikrotik->nombre}» ({$mikrotik->ip}). Reintentando…",
                    'progreso' => $this->progreso($loteId)];
            }

            foreach ($items as $item) {
                $contrato = Contrato::find($item->contrato_id);
                if (! $contrato) {
                    $res = ['ok' => false, 'mensaje' => 'Contrato no existe'];
                } else {
                    $res = $this->sync->enviar($contrato, $mikrotik, $API, $userId, $empresa);
                }
                DB::table('mk_sync_items')->where('id', $item->id)->update([
                    'estado'     => $res['ok'] ? 'ok' : 'error',
                    'mensaje'    => $res['ok'] ? null : $res['mensaje'],
                    'intentos'   => $item->intentos + 1,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $API->disconnect();

            $this->recalcularContadores($loteId);
            $this->finalizarSiCompleto($loteId);

            return ['success' => true, 'done' => false, 'connected' => true, 'progreso' => $this->progreso($loteId)];
        } finally {
            // Liberar el lock.
            DB::table('mk_sync_lotes')->where('id', $loteId)->where('lock_token', $token)
                ->update(['lock_expires' => null]);
        }
    }

    private function recalcularContadores($loteId)
    {
        $c = DB::table('mk_sync_items')->where('lote_id', $loteId)
            ->selectRaw("SUM(estado='ok') as ok, SUM(estado='error') as err, SUM(estado='pendiente') as pend")
            ->first();
        DB::table('mk_sync_lotes')->where('id', $loteId)->update([
            'correctos'  => (int) $c->ok,
            'fallidos'   => (int) $c->err,
            'procesados' => (int) $c->ok + (int) $c->err,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function finalizarSiCompleto($loteId)
    {
        $pend = DB::table('mk_sync_items')->where('lote_id', $loteId)->where('estado', 'pendiente')->count();
        if ($pend === 0) {
            $this->recalcularContadores($loteId);
            DB::table('mk_sync_lotes')->where('id', $loteId)->update([
                'estado' => 'completado', 'fin' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    // ─── Estado (polling / reenganche) ────────────────────────────────────────

    public function estado(Request $request)
    {
        $empresa = Auth::user()->empresa;
        $loteId = (int) $request->input('lote_id');
        $existe = DB::table('mk_sync_lotes')->where('empresa', $empresa)->where('id', $loteId)->exists();
        if (! $existe) {
            return response()->json(['success' => false, 'message' => 'Orden no encontrada.']);
        }

        return response()->json(['success' => true, 'progreso' => $this->progreso($loteId)]);
    }

    private function progreso($loteId)
    {
        $l = DB::table('mk_sync_lotes')->where('id', $loteId)->first();
        if (! $l) {
            return null;
        }
        $pendientes = max(0, $l->total - $l->procesados);
        $pct = $l->total > 0 ? (int) round($l->procesados / $l->total * 100) : 0;

        // Últimos errores para mostrar el detalle.
        $errores = DB::table('mk_sync_items')->where('lote_id', $loteId)->where('estado', 'error')
            ->orderBy('updated_at', 'desc')->limit(50)
            ->get(['contrato_nro', 'ip', 'mensaje']);

        return [
            'lote_id'    => $l->id,
            'estado'     => $l->estado,
            'total'      => (int) $l->total,
            'procesados' => (int) $l->procesados,
            'correctos'  => (int) $l->correctos,
            'fallidos'   => (int) $l->fallidos,
            'pendientes' => $pendientes,
            'porcentaje' => $pct,
            'errores'    => $errores,
        ];
    }

    // ─── Reintentar solo los fallidos ─────────────────────────────────────────

    public function reintentar(Request $request)
    {
        $empresa = Auth::user()->empresa;
        $lote = DB::table('mk_sync_lotes')->where('empresa', $empresa)
            ->where('id', (int) $request->input('lote_id'))->first();
        if (! $lote) {
            return response()->json(['success' => false, 'message' => 'Orden no encontrada.']);
        }

        $reset = DB::table('mk_sync_items')->where('lote_id', $lote->id)->where('estado', 'error')
            ->update(['estado' => 'pendiente', 'mensaje' => null, 'updated_at' => date('Y-m-d H:i:s')]);
        if ($reset === 0) {
            return response()->json(['success' => false, 'message' => 'No hay contratos fallidos para reintentar.']);
        }

        $this->recalcularContadores($lote->id);
        DB::table('mk_sync_lotes')->where('id', $lote->id)->update([
            'estado' => 'pendiente', 'fin' => null, 'lock_expires' => null, 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return response()->json(['success' => true, 'reintentar' => $reset, 'progreso' => $this->progreso($lote->id)]);
    }

    // ─── Cancelar ─────────────────────────────────────────────────────────────

    public function cancelar(Request $request)
    {
        $empresa = Auth::user()->empresa;
        $upd = DB::table('mk_sync_lotes')->where('empresa', $empresa)
            ->where('id', (int) $request->input('lote_id'))
            ->whereIn('estado', ['pendiente', 'ejecutando'])
            ->update(['estado' => 'cancelado', 'fin' => date('Y-m-d H:i:s'), 'lock_expires' => null, 'updated_at' => date('Y-m-d H:i:s')]);

        return response()->json(['success' => (bool) $upd]);
    }

    // ─── Cron: empujar órdenes pendientes de forma desatendida ────────────────

    public function cronProcesar()
    {
        @set_time_limit(0);
        @ignore_user_abort(true);

        if (! Schema::hasTable('mk_sync_lotes') || ! Schema::hasTable('mk_sync_items')) {
            return response('mk-sync: sin tablas', 200);
        }

        $lotes = DB::table('mk_sync_lotes')->whereIn('estado', ['pendiente', 'ejecutando'])
            ->orderBy('id')->get();

        $procesadasTandas = 0;
        foreach ($lotes as $lote) {
            for ($i = 0; $i < self::CRON_MAX_CHUNKS; $i++) {
                $r = $this->procesarTanda($lote->id, $lote->empresa, $lote->created_by);
                $procesadasTandas++;
                // Cortar si terminó, si otra instancia la tiene tomada, o si el router no conecta.
                if (! empty($r['done']) || ! empty($r['busy']) || (isset($r['connected']) && $r['connected'] === false)) {
                    break;
                }
            }
        }

        return response("mk-sync: {$procesadasTandas} tanda(s) procesada(s) en " . count($lotes) . ' orden(es)', 200);
    }
}
