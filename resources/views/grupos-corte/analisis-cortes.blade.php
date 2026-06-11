@extends('layouts.app')

@section('boton')
<a href="{{ route('grupos-corte.index') }}" class="btn btn-outline-danger btn-sm"><i class="fas fa-backward"></i> Regresar al Listado</a>
<a href="{{ route('grupos-corte.show', $grupo->id) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-eye"></i> Ver Grupo</a>
<a href="{{ route('grupos-corte.analisis-ciclo', $grupo->id) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-chart-bar"></i> Análisis de Ciclos</a>
<button id="btn-refresh" class="btn btn-outline-info btn-sm"><i class="fas fa-sync-alt"></i> Actualizar</button>
@endsection

@section('styles')
<style>
.kpi-card {
    border-left: 5px solid;
    transition: transform .2s;
    box-shadow: 0 .125rem .25rem rgba(0,0,0,.075);
}
.kpi-card:hover { transform: translateY(-3px); }
.kpi-card.total    { border-left-color: #6c757d; }
.kpi-card.paid     { border-left-color: #28a745; }
.kpi-card.unpaid   { border-left-color: #dc3545; }
.kpi-card.cut      { border-left-color: #fd7e14; }
.kpi-card.pending  { border-left-color: #ffc107; }
.kpi-card.promise  { border-left-color: #17a2b8; }

.kpi-number { font-size: 2rem; font-weight: 700; }
.kpi-label  { font-size: .75rem; text-transform: uppercase; color: #888; }

.badge-estado-pagado   { background: #28a745; color: #fff; }
.badge-estado-abierta  { background: #dc3545; color: #fff; }
.badge-estado-cortado  { background: #fd7e14; color: #fff; }
.badge-estado-promesa  { background: #17a2b8; color: #fff; }
.badge-estado-bloqueado{ background: #6c757d; color: #fff; }

#progress-bar-wrap { display: none; }
.progress-bar-animated { animation: progress-bar-stripes .6s linear infinite; }

.mk-sync-table th { background: #343a40; color: #fff; }
.tab-content { padding-top: 1rem; }

#log-detail-body td { font-size: .83rem; }
</style>
@endsection

@section('content')
<div class="container-fluid">
    {{-- Header info --}}
    <div class="row mb-3">
        <div class="col-12">
            <h5 class="mb-0">
                <i class="fas fa-cut text-danger"></i>
                Análisis de Cortes — <strong>{{ $grupo->nombre }}</strong>
            </h5>
            <small class="text-muted">
                Corte día {{ $grupo->fecha_corte }} | Suspensión día {{ $grupo->fecha_suspension }}
            </small>
        </div>
    </div>

    {{-- Filtro fecha --}}
    <div class="row mb-3 align-items-end">
        <div class="col-md-3">
            <label class="small font-weight-bold">Fecha de referencia</label>
            <input type="date" id="fecha-ref" class="form-control form-control-sm" value="{{ date('Y-m-d') }}">
        </div>
        <div class="col-md-2">
            <button id="btn-apply-date" class="btn btn-sm btn-primary mt-4">
                <i class="fas fa-filter"></i> Aplicar
            </button>
        </div>
        <div class="col-md-7 text-right">
            <button id="btn-clear-cache" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-eraser"></i> Limpiar Caché
            </button>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="row mb-4" id="kpi-row">
        <div class="col-6 col-md-2 mb-3">
            <div class="card kpi-card total h-100">
                <div class="card-body py-2 px-3">
                    <div class="kpi-number text-secondary" id="kpi-total">—</div>
                    <div class="kpi-label">Total contratos</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2 mb-3">
            <div class="card kpi-card paid h-100">
                <div class="card-body py-2 px-3">
                    <div class="kpi-number text-success" id="kpi-pagados">—</div>
                    <div class="kpi-label">Al día</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2 mb-3">
            <div class="card kpi-card unpaid h-100">
                <div class="card-body py-2 px-3">
                    <div class="kpi-number text-danger" id="kpi-mora">—</div>
                    <div class="kpi-label">En mora</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2 mb-3">
            <div class="card kpi-card cut h-100">
                <div class="card-body py-2 px-3">
                    <div class="kpi-number text-warning" id="kpi-cortados">—</div>
                    <div class="kpi-label">Ya cortados</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2 mb-3">
            <div class="card kpi-card pending h-100">
                <div class="card-body py-2 px-3">
                    <div class="kpi-number" style="color:#ffc107" id="kpi-pendientes">—</div>
                    <div class="kpi-label">Pendientes corte</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2 mb-3">
            <div class="card kpi-card promise h-100">
                <div class="card-body py-2 px-3">
                    <div class="kpi-number text-info" id="kpi-promesas">—</div>
                    <div class="kpi-label">Con promesa</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Progress bar (corte en progreso) --}}
    <div id="progress-bar-wrap" class="mb-3">
        <div class="d-flex justify-content-between small mb-1">
            <span id="progress-label">Ejecutando corte…</span>
            <span id="progress-count">0 / 0</span>
        </div>
        <div class="progress">
            <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-danger"
                 role="progressbar" style="width:0%"></div>
        </div>
    </div>

    {{-- Tabs --}}
    <ul class="nav nav-tabs" id="main-tabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="tab-internet-link" data-toggle="tab" href="#tab-internet">
                <i class="fas fa-wifi"></i> Internet
                <span class="badge badge-danger ml-1" id="badge-internet">0</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-tv-link" data-toggle="tab" href="#tab-tv">
                <i class="fas fa-tv"></i> Televisión
                <span class="badge badge-danger ml-1" id="badge-tv">0</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-historial-link" data-toggle="tab" href="#tab-historial">
                <i class="fas fa-history"></i> Historial
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-mk-link" data-toggle="tab" href="#tab-mk">
                <i class="fas fa-network-wired"></i> Sync MikroTik
            </a>
        </li>
    </ul>

    <div class="tab-content border border-top-0 p-3 bg-white" id="main-tab-content">

        {{-- TAB INTERNET --}}
        <div class="tab-pane fade show active" id="tab-internet" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <strong class="text-danger">Pendientes de corte Internet:</strong>
                    <span id="internet-count" class="badge badge-danger">0</span>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button id="btn-ejecutar-corte" class="btn btn-danger btn-sm">
                        <i class="fas fa-cut"></i> Ejecutar Corte Internet
                    </button>
                    <button id="btn-habilitar-cortados" class="btn btn-success btn-sm">
                        <i class="fas fa-check-circle"></i> Habilitar Cortados
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered" id="tabla-internet">
                    <thead class="thead-dark">
                        <tr>
                            <th>#</th>
                            <th>Contrato</th>
                            <th>Cliente</th>
                            <th>IP</th>
                            <th>MikroTik</th>
                            <th>Factura</th>
                            <th>Valor</th>
                            <th>Vencimiento</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-internet">
                        <tr><td colspan="9" class="text-center text-muted py-3">Cargando…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- TAB TV --}}
        <div class="tab-pane fade" id="tab-tv" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                <div>
                    <strong class="text-warning">Pendientes de corte TV:</strong>
                    <span id="tv-count" class="badge badge-warning">0</span>
                </div>
                <button id="btn-ejecutar-corte-tv" class="btn btn-warning btn-sm text-dark">
                    <i class="fas fa-cut"></i> Ejecutar Corte TV
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered" id="tabla-tv">
                    <thead class="thead-dark">
                        <tr>
                            <th>#</th>
                            <th>Contrato</th>
                            <th>Cliente</th>
                            <th>Serial ONU</th>
                            <th>Factura</th>
                            <th>Valor</th>
                            <th>Vencimiento</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-tv">
                        <tr><td colspan="8" class="text-center text-muted py-3">Cargando…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- TAB HISTORIAL --}}
        <div class="tab-pane fade" id="tab-historial" role="tabpanel">
            <div class="d-flex justify-content-between mb-3">
                <strong>Historial de cortes ejecutados</strong>
                <button id="btn-refresh-historial" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-sync-alt"></i> Actualizar
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered" id="tabla-historial">
                    <thead class="thead-dark">
                        <tr>
                            <th>ID</th>
                            <th>Tipo</th>
                            <th>Procesados</th>
                            <th>Cortados</th>
                            <th>Omitidos</th>
                            <th>Errores</th>
                            <th>Duración</th>
                            <th>Ejecutado por</th>
                            <th>Fecha</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="tbody-historial">
                        <tr><td colspan="10" class="text-center text-muted py-3">Cargando…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- TAB MK SYNC --}}
        <div class="tab-pane fade" id="tab-mk" role="tabpanel">
            <div class="row mb-3">
                <div class="col-md-5">
                    <label class="small font-weight-bold">Seleccionar MikroTik</label>
                    <select id="select-mikrotik" class="form-control form-control-sm">
                        <option value="">— Seleccione —</option>
                        @foreach($mikrotiks as $mk)
                        <option value="{{ $mk->id }}">{{ $mk->nombre }} ({{ $mk->ip }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button id="btn-analizar-mk" class="btn btn-sm btn-info mr-2">
                        <i class="fas fa-search"></i> Analizar
                    </button>
                    <button id="btn-solucionar-lote" class="btn btn-sm btn-warning text-dark" disabled>
                        <i class="fas fa-magic"></i> Solucionar Discrepancias en Lote
                    </button>
                </div>
            </div>

            <div id="mk-sync-result" class="d-none">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="card border-success">
                            <div class="card-body py-2 px-3">
                                <div class="font-weight-bold text-success" id="mk-ok-count">0</div>
                                <small class="text-muted">Morosos OK (en MK y en sistema)</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-danger">
                            <div class="card-body py-2 px-3">
                                <div class="font-weight-bold text-danger" id="mk-faltantes-count">0</div>
                                <small class="text-muted">Cortados sin morosos en MK</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-warning">
                            <div class="card-body py-2 px-3">
                                <div class="font-weight-bold text-warning" id="mk-extra-count">0</div>
                                <small class="text-muted">En morosos MK sin corte sistema</small>
                            </div>
                        </div>
                    </div>
                </div>

                <ul class="nav nav-tabs" id="mk-subtabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#mk-tab-faltantes">
                            Cortados sin morosos <span class="badge badge-danger" id="badge-faltantes">0</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#mk-tab-extra">
                            Extra en morosos <span class="badge badge-warning" id="badge-extra">0</span>
                        </a>
                    </li>
                </ul>
                <div class="tab-content border border-top-0 p-2 bg-white">
                    <div class="tab-pane show active" id="mk-tab-faltantes">
                        <div class="table-responsive mt-2">
                            <table class="table table-sm table-bordered mk-sync-table">
                                <thead>
                                    <tr><th>Contrato</th><th>Cliente</th><th>IP</th><th>Factura</th><th>Valor</th></tr>
                                </thead>
                                <tbody id="tbody-mk-faltantes">
                                    <tr><td colspan="5" class="text-center text-muted">Sin datos</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane" id="mk-tab-extra">
                        <div class="table-responsive mt-2">
                            <table class="table table-sm table-bordered mk-sync-table">
                                <thead>
                                    <tr><th>IP</th><th>Lista MK</th><th>Comentario</th></tr>
                                </thead>
                                <tbody id="tbody-mk-extra">
                                    <tr><td colspan="3" class="text-center text-muted">Sin datos</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div id="mk-sync-empty" class="text-muted text-center py-4">
                Seleccione un MikroTik y haga clic en Analizar.
            </div>
        </div>

    </div>{{-- /tab-content --}}
</div>{{-- /container --}}

{{-- Modal: Confirmar corte internet --}}
<div class="modal fade" id="modal-confirmar-corte" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-cut"></i> Confirmar Corte Internet</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Se ejecutará el corte de servicio para <strong id="modal-corte-count">0</strong> contrato(s) pendiente(s).</p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Esta acción moverá las IPs a la lista <code>morosos</code> en MikroTik y deshabilitará los contratos.
                </div>
                <div class="form-group">
                    <label class="small font-weight-bold">Fecha de corte</label>
                    <input type="date" id="modal-fecha-corte" class="form-control form-control-sm">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="button" id="btn-confirmar-corte" class="btn btn-danger btn-sm">
                    <i class="fas fa-cut"></i> Ejecutar Corte
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Confirmar corte TV --}}
<div class="modal fade" id="modal-confirmar-corte-tv" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title text-dark"><i class="fas fa-tv"></i> Confirmar Corte TV</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Se ejecutará el corte de TV para <strong id="modal-corte-tv-count">0</strong> contrato(s).</p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Esta acción deshabilitará las ONUs en SmartOLT.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="button" id="btn-confirmar-corte-tv" class="btn btn-warning btn-sm text-dark">
                    <i class="fas fa-tv"></i> Ejecutar Corte TV
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Habilitar cortados --}}
<div class="modal fade" id="modal-habilitar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-check-circle"></i> Habilitar Cortados</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>¿Desea habilitar todos los contratos actualmente en estado cortado en este grupo?</p>
                <p class="text-muted small">Se quitarán de <code>morosos</code> y se agregarán a <code>ips_autorizadas</code> en MikroTik.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="button" id="btn-confirmar-habilitar" class="btn btn-success btn-sm">
                    <i class="fas fa-check-circle"></i> Habilitar
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Detalle historial --}}
<div class="modal fade" id="modal-log-detail" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-list"></i> Detalle de ejecución</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-bordered">
                        <thead class="thead-dark">
                            <tr>
                                <th>Contrato</th>
                                <th>Cliente</th>
                                <th>IP</th>
                                <th>Tipo</th>
                                <th>Resultado</th>
                                <th>Método</th>
                                <th>Descripción</th>
                            </tr>
                        </thead>
                        <tbody id="log-detail-body">
                            <tr><td colspan="7" class="text-center text-muted">Cargando…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
var GRUPO_ID = {{ $grupo->id }};
var csrfToken = '{{ csrf_token() }}';
var URLS = {
    summary:          '{{ route("grupos-corte.corte-summary",         ["id" => $grupo->id]) }}',
    allContracts:     '{{ route("grupos-corte.all-contracts",         ["id" => $grupo->id]) }}',
    pendingInternet:  '{{ route("grupos-corte.corte-pending-internet") }}',
    pendingTv:        '{{ route("grupos-corte.corte-pending-tv") }}',
    blockedReasons:   '{{ route("grupos-corte.blocked-reasons",       ["id" => $grupo->id]) }}',
    history:          '{{ route("grupos-corte.corte-history",         ["id" => $grupo->id]) }}',
    historyDetail:    '{{ route("grupos-corte.corte-history-detail",  ["logId" => "LOGID_PH"]) }}',
    mkSync:           '{{ route("grupos-corte.mk-sync") }}',
    solucionarLote:   '{{ route("grupos-corte.solucionar-discrepancia-lote") }}',
    limpiarCache:     '{{ route("grupos-corte.limpiar-cache-cortes") }}',
    ejecutarInternet: '{{ route("grupos-corte.ejecutar-corte-internet") }}',
    ejecutarStream:   '{{ route("grupos-corte.ejecutar-corte-internet-stream") }}',
    ejecutarTv:       '{{ route("grupos-corte.ejecutar-corte-tv") }}',
    habilitarCortados:'{{ route("grupos-corte.habilitar-cortados-internet") }}',
};

function getSelectedFecha() {
    return document.getElementById('fecha-ref').value;
}

function fmt(n) {
    if (n === null || n === undefined) return '—';
    return new Intl.NumberFormat('es-CO').format(n);
}

function badgeEstado(estado) {
    var map = {
        'pagado':    'badge-estado-pagado',
        'abierta':   'badge-estado-abierta',
        'cortado':   'badge-estado-cortado',
        'promesa':   'badge-estado-promesa',
        'bloqueado': 'badge-estado-bloqueado',
    };
    var cls = map[estado] || 'badge-secondary';
    return '<span class="badge ' + cls + '">' + (estado || '—') + '</span>';
}

/* ── KPIs ──────────────────────────────────────────────── */
function loadSummary() {
    $.getJSON(URLS.summary)
        .done(function(d) {
            $('#kpi-total').text(fmt(d.total_contratos));
            $('#kpi-pagados').text(fmt(d.al_dia));
            $('#kpi-mora').text(fmt(d.en_mora));
            $('#kpi-cortados').text(fmt(d.ya_cortados));
            $('#kpi-pendientes').text(fmt(d.pendientes_corte));
            $('#kpi-promesas').text(fmt(d.con_promesa));
        });
}

/* ── Internet tab ──────────────────────────────────────── */
function loadInternet() {
    var fecha = getSelectedFecha();
    $('#tbody-internet').html('<tr><td colspan="9" class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin"></i> Cargando…</td></tr>');
    $.getJSON(URLS.pendingInternet + '?grupo_id=' + GRUPO_ID + '&fecha=' + fecha)
        .done(function(res) {
            var rows = '';
            var list = res.data || [];
            $('#internet-count').text(list.length);
            $('#badge-internet').text(list.length);
            $('#modal-corte-count').text(list.length);
            if (!list.length) {
                rows = '<tr><td colspan="9" class="text-center text-success py-3"><i class="fas fa-check-circle"></i> Sin pendientes de corte</td></tr>';
            } else {
                list.forEach(function(c, i) {
                    rows += '<tr>' +
                        '<td>' + (i+1) + '</td>' +
                        '<td>' + (c.contrato_nro || '—') + '</td>' +
                        '<td>' + (c.cliente_nombre || '—') + '</td>' +
                        '<td><code>' + (c.ip || '—') + '</code></td>' +
                        '<td class="small">' + (c.mikrotik_nombre || '—') + '</td>' +
                        '<td class="small">' + (c.factura_codigo || '—') + '</td>' +
                        '<td>$' + fmt(c.factura_total) + '</td>' +
                        '<td class="small">' + (c.fecha_vencimiento || '—') + '</td>' +
                        '<td>' + badgeEstado(c.estado_contrato) + '</td>' +
                    '</tr>';
                });
            }
            $('#tbody-internet').html(rows);
        });
}

/* ── TV tab ────────────────────────────────────────────── */
function loadTv() {
    var fecha = getSelectedFecha();
    $('#tbody-tv').html('<tr><td colspan="8" class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin"></i> Cargando…</td></tr>');
    $.getJSON(URLS.pendingTv + '?grupo_id=' + GRUPO_ID + '&fecha=' + fecha)
        .done(function(res) {
            var rows = '';
            var list = res.data || [];
            $('#tv-count').text(list.length);
            $('#badge-tv').text(list.length);
            $('#modal-corte-tv-count').text(list.length);
            if (!list.length) {
                rows = '<tr><td colspan="8" class="text-center text-success py-3"><i class="fas fa-check-circle"></i> Sin pendientes de corte TV</td></tr>';
            } else {
                list.forEach(function(c, i) {
                    rows += '<tr>' +
                        '<td>' + (i+1) + '</td>' +
                        '<td>' + (c.contrato_nro || '—') + '</td>' +
                        '<td>' + (c.cliente_nombre || '—') + '</td>' +
                        '<td><code>' + (c.serial_onu || '—') + '</code></td>' +
                        '<td class="small">' + (c.factura_codigo || '—') + '</td>' +
                        '<td>$' + fmt(c.factura_total) + '</td>' +
                        '<td class="small">' + (c.fecha_vencimiento || '—') + '</td>' +
                        '<td>' + badgeEstado(c.estado_contrato) + '</td>' +
                    '</tr>';
                });
            }
            $('#tbody-tv').html(rows);
        });
}

/* ── Historial tab ─────────────────────────────────────── */
function loadHistorial() {
    $('#tbody-historial').html('<tr><td colspan="10" class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin"></i> Cargando…</td></tr>');
    $.getJSON(URLS.history)
        .done(function(data) {
            var rows = '';
            if (!data.length) {
                rows = '<tr><td colspan="10" class="text-center text-muted py-3">Sin historial</td></tr>';
            } else {
                data.forEach(function(h) {
                    var tipoBadge = h.tipo === 'internet'
                        ? '<span class="badge badge-info">internet</span>'
                        : '<span class="badge badge-warning text-dark">tv</span>';
                    var duracion = h.duracion_ms ? (h.duracion_ms / 1000).toFixed(1) + 's' : '—';
                    rows += '<tr>' +
                        '<td>' + h.id + '</td>' +
                        '<td>' + tipoBadge + '</td>' +
                        '<td>' + (h.total_procesados || 0) + '</td>' +
                        '<td class="text-danger font-weight-bold">' + (h.total_cortados || 0) + '</td>' +
                        '<td>' + (h.total_omitidos || 0) + '</td>' +
                        '<td class="text-danger">' + (h.total_errores || 0) + '</td>' +
                        '<td>' + duracion + '</td>' +
                        '<td class="small">' + (h.ejecutado_por_nombre || 'CRON') + '</td>' +
                        '<td class="small">' + (h.created_at || '—') + '</td>' +
                        '<td><button class="btn btn-xs btn-outline-secondary btn-ver-detalle" data-id="' + h.id + '"><i class="fas fa-eye"></i></button></td>' +
                    '</tr>';
                });
            }
            $('#tbody-historial').html(rows);
        });
}

/* ── MK Sync ───────────────────────────────────────────── */
$('#btn-analizar-mk').on('click', function() {
    var mkId = $('#select-mikrotik').val();
    if (!mkId) { alert('Seleccione un MikroTik.'); return; }
    var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Analizando…');
    $('#mk-sync-empty').addClass('d-none');
    $('#mk-sync-result').addClass('d-none');
    $.ajax({
        url: URLS.mkSync,
        method: 'POST',
        data: { _token: csrfToken, mikrotik_id: mkId, grupo_id: GRUPO_ID },
    }).done(function(d) {
        if (!d.disponible) {
            $('#mk-sync-empty').removeClass('d-none').html('<div class="alert alert-danger">' + (d.error || 'No disponible') + '</div>');
            return;
        }
        var faltantes = d.inconsistencias && d.inconsistencias.cortados_sin_morosos ? d.inconsistencias.cortados_sin_morosos : [];
        var extra     = d.inconsistencias && d.inconsistencias.morosos_sin_corte    ? d.inconsistencias.morosos_sin_corte    : [];

        $('#mk-ok-count').text(d.resumen ? (d.resumen.en_morosos_ok || 0) : 0);
        $('#mk-faltantes-count').text(faltantes.length);
        $('#mk-extra-count').text(extra.length);
        $('#badge-faltantes').text(faltantes.length);
        $('#badge-extra').text(extra.length);

        // Faltantes
        var rowsF = '';
        if (!faltantes.length) {
            rowsF = '<tr><td colspan="5" class="text-center text-success">Sin discrepancias</td></tr>';
        } else {
            faltantes.forEach(function(c) {
                rowsF += '<tr><td>'+(c.nro||c.id)+'</td><td>'+(c.cliente_nombre||'—')+'</td><td><code>'+(c.ip||'—')+'</code></td><td class="small">'+(c.factura_codigo||'—')+'</td><td>$'+fmt(c.factura_total)+'</td></tr>';
            });
        }
        $('#tbody-mk-faltantes').html(rowsF);

        // Extra
        var rowsE = '';
        if (!extra.length) {
            rowsE = '<tr><td colspan="3" class="text-center text-success">Sin extras</td></tr>';
        } else {
            extra.forEach(function(e) {
                rowsE += '<tr><td><code>'+(e.address||'—')+'</code></td><td>'+(e.list||'—')+'</td><td class="small">'+(e.comment||'—')+'</td></tr>';
            });
        }
        $('#tbody-mk-extra').html(rowsE);

        $('#mk-sync-result').removeClass('d-none');
        $('#btn-solucionar-lote').prop('disabled', faltantes.length === 0);
    }).fail(function() {
        $('#mk-sync-empty').removeClass('d-none').html('<div class="alert alert-danger">Error al analizar MikroTik.</div>');
    }).always(function() {
        $btn.prop('disabled', false).html('<i class="fas fa-search"></i> Analizar');
    });
});

$('#btn-solucionar-lote').on('click', function() {
    var mkId = $('#select-mikrotik').val();
    if (!mkId || !confirm('¿Agregar a morosos en MikroTik todos los contratos cortados con discrepancia?')) return;
    var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Procesando…');
    $.ajax({
        url: URLS.solucionarLote,
        method: 'POST',
        data: { _token: csrfToken, mikrotik_id: mkId, grupo_id: GRUPO_ID },
    }).done(function(r) {
        alert(r.message || 'Listo');
        $('#btn-analizar-mk').trigger('click');
    }).fail(function(xhr) {
        alert('Error: ' + (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error desconocido'));
    }).always(function() {
        $btn.prop('disabled', false).html('<i class="fas fa-magic"></i> Solucionar Discrepancias en Lote');
    });
});

/* ── Execute corte internet ────────────────────────────── */
$('#btn-ejecutar-corte').on('click', function() {
    $('#modal-fecha-corte').val(getSelectedFecha());
    $('#modal-confirmar-corte').modal('show');
});

$('#btn-confirmar-corte').on('click', function() {
    $('#modal-confirmar-corte').modal('hide');
    var fecha = $('#modal-fecha-corte').val();

    $('#progress-bar-wrap').show();
    $('#progress-bar').css('width', '0%');
    $('#progress-label').text('Ejecutando corte internet…');
    $('#progress-count').text('0 / ?');

    var total = parseInt($('#internet-count').text()) || 0;
    var done = 0;

    var es = new EventSource(URLS.ejecutarStream + '?grupo_id=' + GRUPO_ID + '&fecha=' + fecha);
    es.onmessage = function(e) {
        var d = JSON.parse(e.data);
        if (d.done) {
            es.close();
            $('#progress-bar-wrap').hide();
            alert('Corte completado. Cortados: ' + d.cortados + ' | Errores: ' + d.errores);
            loadInternet();
            loadSummary();
            if ($('#tab-historial-link').hasClass('active') || $('#tab-historial').hasClass('show')) loadHistorial();
            return;
        }
        done = d.progreso;
        var pct = total > 0 ? Math.round(done / total * 100) : 0;
        $('#progress-bar').css('width', pct + '%').text(pct + '%');
        $('#progress-count').text(done + ' / ' + d.total);
    };
    es.onerror = function() {
        es.close();
        $('#progress-bar-wrap').hide();
        alert('Error en el stream de corte. Revise el historial.');
        loadHistorial();
    };
});

/* ── Execute corte TV ──────────────────────────────────── */
$('#btn-ejecutar-corte-tv').on('click', function() {
    $('#modal-confirmar-corte-tv').modal('show');
});

$('#btn-confirmar-corte-tv').on('click', function() {
    $('#modal-confirmar-corte-tv').modal('hide');
    var $btn = $('#btn-ejecutar-corte-tv').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Ejecutando…');
    $.ajax({
        url: URLS.ejecutarTv,
        method: 'POST',
        data: { _token: csrfToken, grupo_id: GRUPO_ID, fecha: getSelectedFecha() },
    }).done(function(r) {
        alert('Corte TV completado. Cortados: ' + r.cortados + ' | Errores: ' + r.errores);
        loadTv();
        loadSummary();
    }).fail(function(xhr) {
        alert('Error: ' + (xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Error desconocido'));
    }).always(function() {
        $btn.prop('disabled', false).html('<i class="fas fa-cut"></i> Ejecutar Corte TV');
    });
});

/* ── Habilitar cortados ────────────────────────────────── */
$('#btn-habilitar-cortados').on('click', function() {
    $('#modal-habilitar').modal('show');
});

$('#btn-confirmar-habilitar').on('click', function() {
    $('#modal-habilitar').modal('hide');
    var $btn = $('#btn-habilitar-cortados').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Procesando…');
    $.ajax({
        url: URLS.habilitarCortados,
        method: 'POST',
        data: { _token: csrfToken, grupo_id: GRUPO_ID },
    }).done(function(r) {
        alert('Habilitados: ' + r.habilitados + ' | Errores: ' + r.errores);
        loadInternet();
        loadSummary();
    }).fail(function() {
        alert('Error al habilitar contratos.');
    }).always(function() {
        $btn.prop('disabled', false).html('<i class="fas fa-check-circle"></i> Habilitar Cortados');
    });
});

/* ── Historial detail ──────────────────────────────────── */
$(document).on('click', '.btn-ver-detalle', function() {
    var logId = $(this).data('id');
    $('#log-detail-body').html('<tr><td colspan="7" class="text-center"><i class="fas fa-spinner fa-spin"></i> Cargando…</td></tr>');
    $('#modal-log-detail').modal('show');
    $.getJSON(URLS.historyDetail.replace('LOGID_PH', logId))
        .done(function(data) {
            var rows = '';
            var list = data.detalle || data || [];
            if (!list.length) {
                rows = '<tr><td colspan="7" class="text-center text-muted">Sin detalle</td></tr>';
            } else {
                list.forEach(function(r) {
                    var res = r.resultado === 'cortado'
                        ? '<span class="badge badge-success">cortado</span>'
                        : (r.resultado === 'error'
                            ? '<span class="badge badge-danger">error</span>'
                            : '<span class="badge badge-secondary">'+(r.resultado||'—')+'</span>');
                    rows += '<tr>' +
                        '<td class="small">'+(r.contrato_nro||r.contrato_id||'—')+'</td>' +
                        '<td class="small">'+(r.cliente_nombre||'—')+'</td>' +
                        '<td><code class="small">'+(r.ip||'—')+'</code></td>' +
                        '<td>'+(r.tipo||'—')+'</td>' +
                        '<td>'+res+'</td>' +
                        '<td class="small">'+(r.metodo||'—')+'</td>' +
                        '<td class="small">'+(r.descripcion || r.error_detalle || '—')+'</td>' +
                    '</tr>';
                });
            }
            $('#log-detail-body').html(rows);
        });
});

/* ── Misc ──────────────────────────────────────────────── */
$('#btn-refresh').on('click', function() {
    loadSummary(); loadInternet(); loadTv();
});

$('#btn-apply-date').on('click', function() {
    loadInternet(); loadTv(); loadSummary();
});

$('#btn-refresh-historial').on('click', loadHistorial);

$('#btn-clear-cache').on('click', function() {
    $.ajax({
        url: URLS.limpiarCache,
        method: 'POST',
        data: { _token: csrfToken, grupo_id: GRUPO_ID },
    }).done(function() {
        loadSummary(); loadInternet(); loadTv();
    });
});

$('a[href="#tab-historial"]').on('shown.bs.tab', function() {
    loadHistorial();
});

/* ── Init ──────────────────────────────────────────────── */
$(document).ready(function() {
    loadSummary();
    loadInternet();
    loadTv();
});
</script>
@endsection
