@extends('layouts.app')

@section('content')
<style>
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap');

    :root {
        --mks-primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --mks-success-gradient: linear-gradient(135deg, #2af598 0%, #009efd 100%);
        --mks-danger-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        --mks-warning-gradient: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
        --mks-glass-shadow: 0 10px 40px rgba(0,0,0,0.03);
    }

    .mks-wrap { font-family: 'Outfit', sans-serif; }

    .mks-header {
        background: var(--mks-primary-gradient);
        padding: 40px 30px;
        margin: -20px -20px 30px -20px;
        color: white;
        border-radius: 0 0 30px 30px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }

    .mks-container {
        background: white;
        border-radius: 20px;
        padding: 25px;
        box-shadow: var(--mks-glass-shadow);
        margin-bottom: 30px;
    }

    .mks-card {
        border: none;
        border-radius: 15px;
        overflow: hidden;
        margin-bottom: 25px;
        background: white;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    }

    .mks-kpi {
        padding: 25px;
        position: relative;
        color: white;
        min-height: 130px;
    }
    .mks-kpi .icon { position: absolute; right: 20px; bottom: 10px; font-size: 3.5rem; opacity: 0.2; }
    .mks-kpi-value { font-size: 2.2rem; font-weight: 800; display: block; line-height: 1; margin-bottom: 5px; }
    .mks-kpi-label { font-size: 0.85rem; font-weight: 500; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; }

    .mks-bg-blue  { background: var(--mks-primary-gradient); }
    .mks-bg-green { background: var(--mks-success-gradient); }
    .mks-bg-red   { background: var(--mks-danger-gradient); }
    .mks-bg-gold  { background: var(--mks-warning-gradient); }

    .mks-progress { height: 14px; border-radius: 50px; background-color: #f1f5f9; margin-top: 10px; }
    .mks-progress .progress-bar {
        border-radius: 50px;
        background: var(--mks-success-gradient);
        box-shadow: 0 4px 10px rgba(0, 158, 253, 0.3);
        transition: width 0.5s ease;
    }
    .mks-progress.is-error .progress-bar { background: var(--mks-danger-gradient); }
    .mks-progress.is-done  .progress-bar { background: var(--mks-success-gradient); }

    .mks-btn {
        border-radius: 12px;
        padding: 10px 22px;
        font-weight: 600;
        border: none;
        color: #fff;
    }
    .mks-btn-primary { background: var(--mks-primary-gradient); }
    .mks-btn-success { background: var(--mks-success-gradient); }
    .mks-btn:disabled { opacity: .5; }

    .mks-badge { border-radius: 50px; padding: 5px 14px; font-weight: 700; font-size: .75rem; letter-spacing: .5px; }
    .mks-badge-pendiente  { background: #fff3cd; color: #856404; }
    .mks-badge-ejecutando { background: #cfe2ff; color: #084298; }
    .mks-badge-completado { background: #d1e7dd; color: #0f5132; }
    .mks-badge-cancelado  { background: #f8d7da; color: #842029; }

    .mks-errores { max-height: 300px; overflow-y: auto; }
    .mks-hint { font-size: .8rem; color: #6c757d; }
</style>

<div class="mks-wrap">

    {{-- ═══════════════ HEADER ═══════════════ --}}
    <div class="mks-header d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h2 class="font-weight-bold mb-1"><i class="fas fa-broadcast-tower mr-2"></i> Sincronización Masiva MikroTik</h2>
            <p class="mb-0 opacity-75">Envía todos los contratos de una MikroTik en lotes optimizados, con monitoreo en vivo.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <button id="mks-btn-nueva" class="btn btn-outline-light border-0" style="border-radius:12px; padding:10px 18px; display:none;">
                <i class="fas fa-plus mr-1"></i> Nueva sincronización
            </button>
        </div>
    </div>

    <div id="mks-alerta" class="alert alert-danger shadow-sm" style="display:none; border-left:5px solid #e74a3b;"></div>

    @if(!empty($sinTablas))
        <div class="alert alert-warning shadow-sm" style="border-left:5px solid #f6c23e;">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            El módulo aún no está instalado en esta base de datos: faltan las tablas <code>mk_sync_lotes</code> / <code>mk_sync_items</code>.
            Ejecute las migraciones (<code>php artisan migrate</code>) o <code>./fix-legacy-columns.sh</code> para habilitarlo.
        </div>
    @endif

    {{-- ═══════════════ CONFIGURACIÓN ═══════════════ --}}
    <div class="mks-container" id="mks-config" style="margin-top:-30px; position:relative; z-index:10;">
        <h5 class="font-weight-bold text-dark mb-3"><i class="fas fa-sliders-h mr-2 text-primary"></i> Configuración de la sincronización</h5>

        <div class="row">
            <div class="col-md-3 mb-3">
                <label class="text-muted font-weight-bold small uppercase">MikroTik (obligatorio)</label>
                <select id="mks-mikrotik" class="form-control">
                    <option value="">Selecciona la MikroTik…</option>
                    @foreach($mikrotiks as $mk)
                        <option value="{{ $mk->id }}">{{ $mk->nombre }} ({{ $mk->ip }}){{ $mk->status ? '' : ' — desconectada' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label class="text-muted font-weight-bold small uppercase">Estado del contrato</label>
                <select id="mks-estado" class="form-control">
                    <option value="1">Activos</option>
                    <option value="0">Inactivos</option>
                    <option value="all">Todos</option>
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label class="text-muted font-weight-bold small uppercase">Grupo de corte (opcional)</label>
                <select id="mks-grupo" class="form-control">
                    <option value="">Todos los grupos</option>
                    @foreach($grupos as $g)
                        <option value="{{ $g->id }}">{{ $g->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label class="text-muted font-weight-bold small uppercase">Plan de velocidad (opcional)</label>
                <select id="mks-plan" class="form-control">
                    <option value="">Todos los planes</option>
                    @foreach($planes as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center bg-light rounded p-3 mt-2">
            <div id="mks-preview-texto" class="text-muted">
                Selecciona una MikroTik para ver cuántos contratos se enviarán.
            </div>
            <button id="mks-btn-iniciar" class="btn mks-btn mks-btn-primary" disabled @if(!empty($sinTablas)) data-bloqueado="1" @endif>
                <i class="fas fa-paper-plane mr-1"></i> Iniciar sincronización
            </button>
        </div>
        <div class="mks-hint mt-2">
            <i class="fas fa-info-circle"></i> Solo se incluyen contratos <b>con IP asignada</b> que apunten a la MikroTik seleccionada.
            El proceso se ejecuta en tandas cortas reutilizando una sola conexión al router; puedes pausarlo y reanudarlo cuando quieras.
        </div>
    </div>

    {{-- ═══════════════ PROGRESO ═══════════════ --}}
    <div id="mks-progreso" style="display:none;">

        <div class="mks-container">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                <h5 class="font-weight-bold text-dark mb-0">
                    <i class="fas fa-tasks mr-2 text-primary"></i> Orden #<span id="mks-lote-id">—</span>
                </h5>
                <span id="mks-estado-badge" class="mks-badge mks-badge-pendiente">Pendiente</span>
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted small"><span id="mks-procesados">0</span> / <span id="mks-total-barra">0</span> contratos</span>
                <span id="mks-porcentaje" class="font-weight-bold h5 mb-0">0%</span>
            </div>
            <div class="progress mks-progress" id="mks-progress-wrap">
                <div class="progress-bar" id="mks-progress-bar" role="progressbar" style="width:0%"></div>
            </div>

            <div id="mks-conn-msg" class="text-warning font-weight-bold small mt-2" style="display:none;">
                <i class="fas fa-spinner fa-spin mr-1"></i> <span></span>
            </div>

            <div class="mt-4 d-flex flex-wrap" style="gap:10px;">
                <button id="mks-btn-pausar"    class="btn btn-outline-secondary" style="border-radius:12px; display:none;"><i class="fas fa-pause mr-1"></i> Pausar</button>
                <button id="mks-btn-reanudar"  class="btn mks-btn mks-btn-primary" style="display:none;"><i class="fas fa-play mr-1"></i> Reanudar</button>
                <button id="mks-btn-cancelar"  class="btn btn-outline-danger" style="border-radius:12px; display:none;"><i class="fas fa-times mr-1"></i> Cancelar</button>
                <button id="mks-btn-reintentar" class="btn mks-btn mks-btn-success" style="display:none;"><i class="fas fa-redo mr-1"></i> Reintentar fallidos</button>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-3 col-md-6">
                <div class="mks-card mks-bg-blue">
                    <div class="mks-kpi">
                        <span class="mks-kpi-label">Total</span>
                        <span class="mks-kpi-value" id="mks-kpi-total">0</span>
                        <div class="icon"><i class="fas fa-boxes"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="mks-card mks-bg-green">
                    <div class="mks-kpi">
                        <span class="mks-kpi-label">Correctos</span>
                        <span class="mks-kpi-value" id="mks-kpi-ok">0</span>
                        <div class="icon"><i class="fas fa-check-double"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="mks-card mks-bg-red">
                    <div class="mks-kpi">
                        <span class="mks-kpi-label">Fallidos</span>
                        <span class="mks-kpi-value" id="mks-kpi-err">0</span>
                        <div class="icon"><i class="fas fa-times-circle"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="mks-card mks-bg-gold">
                    <div class="mks-kpi">
                        <span class="mks-kpi-label">Pendientes</span>
                        <span class="mks-kpi-value" id="mks-kpi-pend">0</span>
                        <div class="icon"><i class="fas fa-hourglass-half"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mks-container" id="mks-errores-box" style="display:none; padding:0; overflow:hidden; border-top:4px solid #e74a3b;">
            <div class="p-4 bg-white border-bottom">
                <h5 class="m-0 font-weight-bold text-dark"><i class="fas fa-exclamation-triangle mr-2 text-danger"></i> Contratos con error</h5>
                <p class="text-muted small mb-0">Últimos 50 renglones fallidos de esta orden.</p>
            </div>
            <div class="table-responsive mks-errores">
                <table class="table table-hover table-sm mb-0">
                    <thead class="thead-light">
                        <tr><th>Contrato</th><th>IP</th><th>Motivo</th></tr>
                    </thead>
                    <tbody id="mks-errores-tbody"></tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ═══════════════ HISTORIAL ═══════════════ --}}
    <div class="mks-container" style="padding:0; overflow:hidden; border-top:4px solid #667eea;">
        <div class="p-4 bg-white border-bottom">
            <h5 class="m-0 font-weight-bold text-dark"><i class="fas fa-history mr-2 text-primary"></i> Historial reciente</h5>
            <p class="text-muted small mb-0">Últimas 15 órdenes de sincronización.</p>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>#</th><th>MikroTik</th><th>Estado</th>
                        <th class="text-right">Total</th><th class="text-right">OK</th><th class="text-right">Fallidos</th>
                        <th>Creada</th><th>Fin</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($historial as $h)
                        <tr>
                            <td class="font-weight-bold">{{ $h->id }}</td>
                            <td>{{ $h->mikrotik_nombre ?? '—' }}</td>
                            <td><span class="mks-badge mks-badge-{{ $h->estado }}">{{ ucfirst($h->estado) }}</span></td>
                            <td class="text-right">{{ $h->total }}</td>
                            <td class="text-right text-success font-weight-bold">{{ $h->correctos }}</td>
                            <td class="text-right text-danger font-weight-bold">{{ $h->fallidos }}</td>
                            <td class="small text-muted">{{ $h->created_at ? date('d-m-Y H:i', strtotime($h->created_at)) : '—' }}</td>
                            <td class="small text-muted">{{ $h->fin ? date('d-m-Y H:i', strtotime($h->fin)) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">Aún no se ha ejecutado ninguna sincronización.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
$(function () {
    var csrfToken   = $('meta[name="csrf-token"]').attr('content');
    var URL_PREVIEW = '{{ route('mk-sync.previsualizar') }}';
    var URL_CREAR   = '{{ route('mk-sync.crear') }}';
    var URL_PROC    = '{{ route('mk-sync.procesar') }}';
    var URL_ESTADO  = '{{ route('mk-sync.estado') }}';
    var URL_RETRY   = '{{ route('mk-sync.reintentar') }}';
    var URL_CANCEL  = '{{ route('mk-sync.cancelar') }}';

    var MAX_CONN_FAILS = 5;   // reintentos ante router caído antes de detenerse
    var paused  = false;
    var running = false;
    var loteId  = null;

    var ESTADOS = {
        pendiente:  'Pendiente',
        ejecutando: 'Ejecutando',
        completado: 'Completado',
        cancelado:  'Cancelado'
    };

    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': csrfToken } });

    if ($.fn.select2) {
        $('#mks-mikrotik, #mks-grupo, #mks-plan, #mks-estado').select2({ width: '100%' });
    }

    function filtros() {
        return {
            mikrotik_id: $('#mks-mikrotik').val(),
            status:      $('#mks-estado').val(),
            grupo_corte: $('#mks-grupo').val(),
            plan_id:     $('#mks-plan').val()
        };
    }

    function error(msg) {
        if (!msg) { $('#mks-alerta').hide().text(''); return; }
        $('#mks-alerta').html('<i class="fas fa-exclamation-circle mr-1"></i> ' + msg).show();
    }

    function sleep(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

    // POST como Promise NATIVA: el bundle del layout trae dos versiones de jQuery
    // y la vieja (1.9) no devuelve promesas compatibles con `await`.
    function post(url, data) {
        return new Promise(function (resolve, reject) {
            $.ajax({
                url: url, type: 'POST', dataType: 'json', data: data,
                headers: { 'X-CSRF-TOKEN': csrfToken }
            }).done(resolve).fail(reject);
        });
    }

    // ─── Previsualizar ──────────────────────────────────────────────────────
    var previewTotal = 0;
    var bloqueado = $('#mks-btn-iniciar').data('bloqueado') === 1; // faltan las tablas mk_sync_*

    function previsualizar() {
        if (bloqueado) { return; }
        var mk = $('#mks-mikrotik').val();
        if (!mk) {
            previewTotal = 0;
            $('#mks-preview-texto').html('Selecciona una MikroTik para ver cuántos contratos se enviarán.');
            $('#mks-btn-iniciar').prop('disabled', true);
            return;
        }
        $('#mks-preview-texto').html('<i class="fas fa-spinner fa-spin mr-1"></i> Contando…');
        $.post(URL_PREVIEW, filtros())
            .done(function (d) {
                previewTotal = (d && d.success) ? d.total : 0;
                $('#mks-preview-texto').html('Se sincronizarán <b class="h5 text-primary">' + previewTotal + '</b> contrato(s).');
                $('#mks-btn-iniciar').prop('disabled', previewTotal === 0);
            })
            .fail(function () {
                $('#mks-preview-texto').html('<span class="text-danger">No se pudo calcular el total.</span>');
                $('#mks-btn-iniciar').prop('disabled', true);
            });
    }

    $('#mks-mikrotik, #mks-estado, #mks-grupo, #mks-plan').on('change', previsualizar);

    // ─── Pintar progreso ────────────────────────────────────────────────────
    function pintar(p) {
        if (!p) { return; }
        loteId = p.lote_id;

        $('#mks-config').hide();
        $('#mks-progreso').show();
        $('#mks-btn-nueva').toggle(p.estado === 'completado' || p.estado === 'cancelado');

        $('#mks-lote-id').text(p.lote_id);
        $('#mks-procesados').text(p.procesados);
        $('#mks-total-barra').text(p.total);
        $('#mks-porcentaje').text(p.porcentaje + '%');
        $('#mks-progress-bar').css('width', p.porcentaje + '%');
        $('#mks-progress-wrap')
            .toggleClass('is-error', p.estado === 'cancelado')
            .toggleClass('is-done', p.estado === 'completado');

        $('#mks-estado-badge')
            .attr('class', 'mks-badge mks-badge-' + p.estado)
            .text(ESTADOS[p.estado] || p.estado);

        $('#mks-kpi-total').text(p.total);
        $('#mks-kpi-ok').text(p.correctos);
        $('#mks-kpi-err').text(p.fallidos);
        $('#mks-kpi-pend').text(p.pendientes);

        var activa    = (p.estado === 'pendiente' || p.estado === 'ejecutando');
        var terminado = (p.estado === 'completado' || p.estado === 'cancelado');

        $('#mks-btn-pausar').toggle(activa && running);
        $('#mks-btn-reanudar').toggle(activa && !running);
        $('#mks-btn-cancelar').toggle(activa);
        $('#mks-btn-reintentar').toggle(terminado && p.fallidos > 0);

        // Errores
        var errores = p.errores || [];
        if (errores.length) {
            var html = '';
            for (var i = 0; i < errores.length; i++) {
                var e = errores[i];
                html += '<tr>' +
                    '<td class="font-weight-bold">#' + (e.contrato_nro || '—') + '</td>' +
                    '<td class="text-muted small">' + (e.ip || '—') + '</td>' +
                    '<td class="text-danger small">' + (e.mensaje || '—') + '</td>' +
                    '</tr>';
            }
            $('#mks-errores-tbody').html(html);
            $('#mks-errores-box').show();
        } else {
            $('#mks-errores-tbody').empty();
            $('#mks-errores-box').hide();
        }
    }

    function connMsg(msg) {
        if (!msg) { $('#mks-conn-msg').hide(); return; }
        $('#mks-conn-msg').show().find('span').text(msg);
    }

    // ─── Motor: dispara tandas hasta terminar ───────────────────────────────
    async function drive(id) {
        if (running) { return; }
        running = true;
        paused  = false;
        error('');
        $('#mks-btn-pausar').show();
        $('#mks-btn-reanudar').hide();

        var connFails = 0;

        while (!paused) {
            var d;
            try {
                d = await post(URL_PROC, { lote_id: id });
            } catch (ex) {
                connFails++;
                if (connFails > MAX_CONN_FAILS) { error('Error de red al procesar. Puedes reanudar.'); break; }
                await sleep(3000);
                continue;
            }

            if (d.progreso) { pintar(d.progreso); }

            if (d.done || (d.progreso && (d.progreso.estado === 'completado' || d.progreso.estado === 'cancelado'))) { break; }

            if (d.success === false && d.connected === undefined) {
                error(d.message || 'No se pudo procesar la orden.');
                break;
            }

            if (d.connected === false) {
                connFails++;
                connMsg(d.message || 'MikroTik no responde. Reintentando…');
                if (connFails > MAX_CONN_FAILS) { error(d.message || 'No se pudo conectar a la MikroTik.'); break; }
                await sleep(3000);
                continue;
            }

            connFails = 0;
            connMsg('');

            if (d.busy) { await sleep(1500); continue; }
            await sleep(120); // pequeño respiro entre tandas
        }

        running = false;
        connMsg('');
        refrescarEstado();
    }

    function refrescarEstado() {
        if (!loteId) { return; }
        $.get(URL_ESTADO, { lote_id: loteId }).done(function (d) {
            if (d.success) { pintar(d.progreso); }
        });
    }

    // ─── Acciones ───────────────────────────────────────────────────────────
    $('#mks-btn-iniciar').on('click', function () {
        var $b = $(this).prop('disabled', true);
        error('');
        $.post(URL_CREAR, filtros())
            .done(function (d) {
                if (!d.success) {
                    error(d.message || 'No se pudo crear la orden.');
                    if (d.lote_id) { // ya había una en curso: reengancharse
                        loteId = d.lote_id;
                        $.get(URL_ESTADO, { lote_id: d.lote_id }).done(function (e) {
                            if (e.success) { pintar(e.progreso); drive(d.lote_id); }
                        });
                    }
                    $b.prop('disabled', false);
                    return;
                }
                pintar({
                    lote_id: d.lote_id, estado: 'pendiente', total: d.total, procesados: 0,
                    correctos: 0, fallidos: 0, pendientes: d.total, porcentaje: 0, errores: []
                });
                drive(d.lote_id);
            })
            .fail(function () {
                error('No se pudo iniciar la sincronización.');
                $b.prop('disabled', false);
            });
    });

    $('#mks-btn-pausar').on('click', function () {
        paused = true;
        $(this).hide();
        $('#mks-btn-reanudar').show();
    });

    $('#mks-btn-reanudar').on('click', function () {
        if (loteId) { drive(loteId); }
    });

    $('#mks-btn-cancelar').on('click', function () {
        if (!loteId) { return; }
        paused = true;
        $.post(URL_CANCEL, { lote_id: loteId }).always(refrescarEstado);
    });

    $('#mks-btn-reintentar').on('click', function () {
        if (!loteId) { return; }
        $.post(URL_RETRY, { lote_id: loteId }).done(function (d) {
            if (d.success) { pintar(d.progreso); drive(loteId); }
            else { error(d.message || 'No hay fallidos para reintentar.'); }
        });
    });

    $('#mks-btn-nueva').on('click', function () {
        loteId = null;
        error('');
        connMsg('');
        $('#mks-progreso').hide();
        $('#mks-btn-nueva').hide();
        $('#mks-config').show();
        $('#mks-btn-iniciar').prop('disabled', previewTotal === 0);
    });

    // ─── Reenganche: si al entrar hay una orden activa, seguirla ────────────
    @if($activo)
        var activo = @json($activo);
        pintar(activo);
        if (activo.estado === 'pendiente' || activo.estado === 'ejecutando') {
            drive(activo.lote_id);
        }
    @endif
});
</script>
@endsection
