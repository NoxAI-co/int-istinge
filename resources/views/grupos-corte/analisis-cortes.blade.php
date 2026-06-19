@extends('layouts.app')

@section('boton')
<a href="{{ route('grupos-corte.index') }}" class="btn btn-sm" style="background:#fff;color:#374151;border:1px solid #dee2e6;border-radius:8px;"><i class="fas fa-backward"></i> Regresar</a>
<a href="{{ route('grupos-corte.show', $grupo->id) }}" class="btn btn-sm" style="background:#fff;color:#374151;border:1px solid #dee2e6;border-radius:8px;"><i class="fas fa-eye"></i> Ver Grupo</a>
<a href="{{ route('grupos-corte.analisis-ciclo', $grupo->id) }}" class="btn btn-sm" style="background:#fff;color:#374151;border:1px solid #dee2e6;border-radius:8px;"><i class="fas fa-chart-bar"></i> Ciclos</a>
<button id="btn-refresh" class="btn btn-sm" style="background:#fff;color:#374151;border:1px solid #dee2e6;border-radius:8px;"><i class="fas fa-sync-alt"></i> Actualizar</button>
@endsection

@section('style')
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════════════════
   OVERRIDE LAYOUT WHITES — scoped to .ac-dark parent
   ═══════════════════════════════════════════════════════════ */
.ac-dark,
.ac-dark .content-wrapper,
.ac-dark .main-panel,
.ac-dark .grid-margin,
.ac-dark .stretch-card,
.ac-dark .card,
.ac-dark .body-card,
.ac-dark .body-oscuro,
.ac-dark .body-oscuro2 {
    background: #f4f5f7 !important;
    border-color: #f1f3f5 !important;
}
.ac-dark #titulo,
.ac-dark .xp7jhwk h3 {
    color: #1f2937 !important;
}
.ac-dark .xp7jhwk {
    border-bottom: 1px solid #e5e7eb !important;
}

/* ═══════════════════════════════════════════════════════════
   DESIGN SYSTEM
   ═══════════════════════════════════════════════════════════ */
.ac-view {
    font-family: 'Inter', sans-serif;
    color: #1f2937;
    padding: 0 0.5rem;
}

/* ── Header ─────────────────────────────────────────────── */
.ac-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e5e7eb;
}
.ac-header-title {
    font-size: 1.15rem;
    font-weight: 600;
    color: #111827;
}
.ac-header-sub {
    font-size: 0.8rem;
    color: #6b7280;
    margin-top: 2px;
}
.ac-header-right {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.ac-date-input {
    background: #ffffff !important;
    border: 1px solid #dee2e6 !important;
    color: #6b7280 !important;
    border-radius: 8px !important;
    padding: 0.35rem 0.7rem !important;
    font-size: 0.8rem !important;
    font-family: 'Inter', sans-serif !important;
}
.ac-date-input::-webkit-calendar-picker-indicator { filter: invert(0); }
.ac-btn-sm {
    background: #ffffff;
    border: 1px solid #dee2e6;
    color: #6b7280;
    border-radius: 8px;
    padding: 0.4rem 0.75rem;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.15s;
}
.ac-btn-sm:hover { background: #dee2e6; color: #1f2937; }

/* ── KPI Row (matches screenshot 1) ─────────────────────── */
.ac-kpi-row {
    display: flex;
    gap: 0.75rem;
    overflow-x: auto;
    margin-bottom: 1.5rem;
    padding-bottom: 0.25rem;
}
.ac-kpi-row::-webkit-scrollbar { height: 4px; }
.ac-kpi-row::-webkit-scrollbar-thumb { background: #dee2e6; border-radius: 4px; }
.ac-kpi {
    background: #ffffff;
    border: 1px solid #dee2e6;
    border-radius: 14px;
    padding: 1.1rem 1.25rem;
    min-width: 165px;
    flex: 1;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    transition: border-color 0.2s, box-shadow 0.2s;
    box-shadow: 0 1px 2px rgba(16,24,40,0.04);
}
.ac-kpi:hover { border-color: #cbd5e1; box-shadow: 0 4px 12px rgba(16,24,40,0.08); }
.ac-kpi-title {
    font-size: 0.6rem;
    font-weight: 700;
    color: #6b7280;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    margin-bottom: 0.5rem;
    line-height: 1.3;
}
.ac-kpi-value {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 0.35rem;
}
.ac-kpi-sub {
    font-size: 0.7rem;
    color: #9ca3af;
    font-weight: 500;
}
.ac-kpi-icon {
    width: 36px; height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}

/* ── Tabs (pill style — matches screenshot 2) ────────────── */
.ac-tabs-wrap {
    margin-bottom: 1.5rem;
}
.ac-tabs {
    display: inline-flex;
    background: #f1f3f5;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 4px;
    list-style: none;
    margin: 0;
}
.ac-tabs .nav-item { margin: 0; }
.ac-tabs .nav-link {
    background: transparent !important;
    border: none !important;
    color: #6b7280 !important;
    font-weight: 500;
    font-size: 0.85rem;
    padding: 0.55rem 1.1rem;
    border-radius: 8px;
    transition: all 0.15s;
    display: flex;
    align-items: center;
    gap: 6px;
}
.ac-tabs .nav-link:hover { color: #374151 !important; }
.ac-tabs .nav-link.active {
    background: #ffffff !important;
    color: #111827 !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.ac-tabs .nav-link.active::after { display: none !important; }

/* ── Filter pills (matches screenshot 3 top) ─────────────── */
.ac-filters {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.ac-pill {
    background: transparent;
    border: 1px solid #dee2e6;
    border-radius: 20px;
    padding: 0.3rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.15s;
    display: flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
}
.ac-pill:hover, .ac-pill.active { background: #e5e7eb; color: #1f2937; border-color: #adb5bd; }
.ac-pill .dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
.dot-green { background: #22c55e; }
.dot-orange { background: #f97316; }
.dot-grey { background: #9ca3af; }
.dot-red { background: #ef4444; }

/* ── Action buttons ──────────────────────────────────────── */
.ac-btn-green {
    background: #059669;
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 0.55rem 1.1rem;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 0 14px rgba(16, 185, 129, 0.35);
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.ac-btn-green:hover { background: #10b981; box-shadow: 0 0 20px rgba(16, 185, 129, 0.5); color: #fff; }
.ac-btn-red {
    background: #dc2626;
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 0.55rem 1.1rem;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 0 14px rgba(239, 68, 68, 0.35);
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.ac-btn-red:hover { background: #ef4444; box-shadow: 0 0 20px rgba(239, 68, 68, 0.5); color: #fff; }

/* ── Search ──────────────────────────────────────────────── */
.ac-search {
    background: #ffffff;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 0.7rem 1rem;
    margin-bottom: 1rem;
}
.ac-search input {
    background: transparent;
    border: none;
    color: #1f2937;
    width: 100%;
    outline: none;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
}
.ac-search input::placeholder { color: #adb5bd; }

/* ── Table (matches screenshot 3) ────────────────────────── */
.ac-table-wrap {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(16,24,40,0.05);
}
.ac-table {
    width: 100%;
    border-collapse: collapse;
    color: #1f2937;
    margin: 0;
}
.ac-table thead th {
    background: transparent;
    color: #9ca3af;
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 0.9rem 1rem;
    border-bottom: 1px solid #e5e7eb;
    border-top: none;
    white-space: nowrap;
}
.ac-table tbody td {
    padding: 0.85rem 1rem;
    border-top: 1px solid #f1f3f5;
    font-size: 0.85rem;
    vertical-align: middle;
}
.ac-table tbody tr:hover { background: #f1f3f5; }

.ac-client-name { font-weight: 600; color: #111827; display: block; }
.ac-client-doc { font-size: 0.7rem; color: #9ca3af; }
.ac-ip { font-weight: 600; color: #1f2937; display: block; }
.ac-pppoe { font-size: 0.7rem; color: #6b7280; }
.ac-olt { font-size: 0.65rem; color: #3b82f6; font-weight: 600; }
.ac-dim { color: #6b7280; font-size: 0.8rem; }
.ac-days-red { color: #ef4444; font-weight: 700; font-size: 0.85rem; }

/* Badges */
.ac-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 0.3rem 0.7rem;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    white-space: nowrap;
}
.ac-badge::before { content:''; width:6px; height:6px; border-radius:50%; }
.ac-badge-green { background: rgba(34,197,94,0.1); color: #22c55e; border: 1px solid rgba(34,197,94,0.2); }
.ac-badge-green::before { background: #22c55e; }
.ac-badge-grey { background: #f1f3f5; color: #6b7280; border: 1px solid #dee2e6; }
.ac-badge-grey::before { background: #9ca3af; }
.ac-badge-orange { background: rgba(249,115,22,0.1); color: #fb923c; border: 1px solid rgba(249,115,22,0.2); }
.ac-badge-orange::before { background: #f97316; }
.ac-badge-cyan { background: rgba(6,182,212,0.1); color: #22d3ee; border: 1px solid rgba(6,182,212,0.2); }
.ac-badge-cyan::before { background: #06b6d4; }
.ac-badge-red { background: rgba(239,68,68,0.1); color: #f87171; border: 1px solid rgba(239,68,68,0.2); }
.ac-badge-red::before { background: #ef4444; }
.ac-badge-blue { background: rgba(59,130,246,0.1); color: #60a5fa; border: 1px solid rgba(59,130,246,0.2); }
.ac-badge-blue::before { background: #3b82f6; }

/* ── Modals ──────────────────────────────────────────────── */
.ac-dark .modal-content { background: #ffffff !important; border: 1px solid #dee2e6; border-radius: 12px; color: #1f2937; }
.ac-dark .modal-header { border-bottom: 1px solid #dee2e6; }
.ac-dark .modal-footer { border-top: 1px solid #dee2e6; }
.ac-dark .close { color: #1f2937; text-shadow: none; opacity: 1; }
.ac-dark .alert-warning { background: rgba(234,179,8,0.08); border: 1px solid rgba(234,179,8,0.2); color: #eab308; }

/* ── Empty state ─────────────────────────────────────────── */
.ac-empty { text-align: center; padding: 3rem 1rem; color: #adb5bd; }
.ac-empty i { font-size: 2.5rem; margin-bottom: 1rem; display: block; }

/* ── MK Sync section ─────────────────────────────────────── */
.ac-mk-bar {
    background: #ffffff;
    border: 1px solid #dee2e6;
    border-radius: 12px;
    padding: 1.25rem;
    display: flex;
    align-items: flex-end;
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}
.ac-mk-bar select {
    background: #f4f5f7 !important;
    border: 1px solid #dee2e6 !important;
    color: #6b7280 !important;
    border-radius: 8px !important;
}
.ac-mk-bar label { color: #9ca3af; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; }

/* Progress */
.ac-progress-wrap {
    display: none;
    background: #ffffff;
    border: 1px solid #dee2e6;
    border-radius: 12px;
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
}
.ac-progress-wrap .progress { background: rgba(239,68,68,0.1); height: 8px; border-radius: 4px; }
.ac-progress-wrap .progress-bar { background: #ef4444; box-shadow: 0 0 8px rgba(239,68,68,0.5); }

/* Responsive */
@media (max-width: 768px) {
    .ac-kpi-row { flex-wrap: nowrap; }
    .ac-kpi { min-width: 160px; }
}
</style>
@endsection

@section('content')
<div class="ac-dark">
<div class="ac-view">

    {{-- Header --}}
    <div class="ac-header">
        <div>
            <div class="ac-header-title">Análisis de Cortes — {{ $grupo->nombre }}</div>
            <div class="ac-header-sub">Corte día {{ $grupo->fecha_corte }} · Suspensión día {{ $grupo->fecha_suspension }}</div>
        </div>
        <div class="ac-header-right">
            <input type="date" id="fecha-ref" class="ac-date-input" value="{{ date('Y-m-d') }}">
            <button id="btn-apply-date" class="ac-btn-sm"><i class="fas fa-sync-alt"></i></button>
            <button id="btn-clear-cache" class="ac-btn-sm"><i class="fas fa-eraser"></i> Caché</button>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="ac-kpi-row" id="kpi-row">
        <div class="ac-kpi">
            <div>
                <div class="ac-kpi-title">Total Contratos</div>
                <div class="ac-kpi-value" style="color:#1f2937;" id="kpi-total">0</div>
                <div class="ac-kpi-sub"><span id="kpi-activos">0</span> activos</div>
            </div>
            <div class="ac-kpi-icon" style="background:rgba(99,102,241,0.1);color:#818cf8;"><i class="fas fa-user-friends"></i></div>
        </div>
        <div class="ac-kpi">
            <div>
                <div class="ac-kpi-title">Pendientes<br>Internet</div>
                <div class="ac-kpi-value" style="color:#ef4444;" id="kpi-pend-inet">0</div>
                <div class="ac-kpi-sub">por cortar ahora</div>
            </div>
            <div class="ac-kpi-icon" style="background:rgba(239,68,68,0.1);color:#f87171;"><i class="fas fa-wifi"></i></div>
        </div>
        <div class="ac-kpi">
            <div>
                <div class="ac-kpi-title">Cortados<br>Internet</div>
                <div class="ac-kpi-value" style="color:#f97316;" id="kpi-cut-inet">0</div>
                <div class="ac-kpi-sub">state=disabled</div>
            </div>
            <div class="ac-kpi-icon" style="background:rgba(249,115,22,0.1);color:#fb923c;"><i class="fas fa-ban"></i></div>
        </div>
        <div class="ac-kpi">
            <div>
                <div class="ac-kpi-title">Pendientes<br>TV</div>
                <div class="ac-kpi-value" style="color:#a855f7;" id="kpi-pend-tv">0</div>
                <div class="ac-kpi-sub">prorroga: <span id="kpi-prorroga">0</span>d</div>
            </div>
            <div class="ac-kpi-icon" style="background:rgba(168,85,247,0.1);color:#c084fc;"><i class="fas fa-tv"></i></div>
        </div>
        <div class="ac-kpi">
            <div>
                <div class="ac-kpi-title">Cortados<br>TV</div>
                <div class="ac-kpi-value" style="color:#a855f7;" id="kpi-cut-tv">0</div>
                <div class="ac-kpi-sub">state_olt_catv=0</div>
            </div>
            <div class="ac-kpi-icon" style="background:rgba(168,85,247,0.1);color:#c084fc;"><i class="fas fa-eye-slash"></i></div>
        </div>
        <div class="ac-kpi">
            <div>
                <div class="ac-kpi-title">Con OLT</div>
                <div class="ac-kpi-value" style="color:#06b6d4;" id="kpi-olt">0</div>
                <div class="ac-kpi-sub"><span id="kpi-mk">0</span> con MikroTik</div>
            </div>
            <div class="ac-kpi-icon" style="background:rgba(6,182,212,0.1);color:#22d3ee;"><i class="fas fa-network-wired"></i></div>
        </div>
    </div>

    {{-- Progress --}}
    <div class="ac-progress-wrap" id="progress-bar-wrap">
        <div class="d-flex justify-content-between mb-2" style="font-size:0.8rem;">
            <span id="progress-label" style="color:#ef4444;font-weight:600;">Ejecutando corte...</span>
            <span id="progress-count" style="color:#6b7280;">0 / 0</span>
        </div>
        <div class="progress"><div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%"></div></div>
    </div>

    {{-- Tabs --}}
    <div class="ac-tabs-wrap">
        <ul class="ac-tabs nav" id="main-tabs" role="tablist">
            <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab-internet"><i class="fas fa-wifi" style="color:#3b82f6;"></i> Internet</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-tv"><i class="fas fa-tv" style="color:#a855f7;"></i> Televisión</a></li>
            <li class="nav-item"><a class="nav-link" id="tab-historial-link" data-toggle="tab" href="#tab-historial"><i class="fas fa-clock" style="color:#6b7280;"></i> Historial</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-mk"><i class="fas fa-server" style="color:#06b6d4;"></i> Sync MK</a></li>
        </ul>
    </div>

    <div class="tab-content" id="main-tab-content">

        {{-- ── TAB INTERNET ──────────────────────────────────── --}}
        <div class="tab-pane fade show active" id="tab-internet" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:0.75rem;">
                <div class="ac-filters">
                    <div class="ac-pill active" data-filter="todos">Todos <span id="f-todos" style="margin-left:2px;">0</span></div>
                    <div class="ac-pill" data-filter="aldia"><span class="dot dot-green"></span> Al día <span id="f-aldia">0</span></div>
                    <div class="ac-pill" data-filter="mora"><span class="dot dot-orange"></span> Fact. antigua <span id="f-antigua">0</span></div>
                    <div class="ac-pill" data-filter="suspendidos"><span class="dot dot-grey"></span> Suspendidos <span id="f-susp">0</span></div>
                </div>
                <div class="d-flex" style="gap:0.5rem;">
                    <button id="btn-habilitar-cortados" class="ac-btn-green"><i class="fas fa-power-off"></i> Habilitar grupo cortado (<span id="btn-count-cortados">0</span>)</button>
                    <button id="btn-ejecutar-corte" class="ac-btn-red" style="display:none;"><i class="fas fa-ban"></i> Ejecutar Corte (<span id="btn-count-pend">0</span>)</button>
                </div>
            </div>

            <div class="ac-search"><input type="text" id="search-contratos" placeholder="Buscar cliente, NIT, contrato, IP, usuario PPPoE..."></div>

            <div class="ac-table-wrap">
                <table class="ac-table" id="tabla-internet">
                    <thead><tr>
                        <th>Cliente</th><th>Contrato</th><th>Estado</th><th>IP / Acceso</th><th>MikroTik</th><th>Últ. Factura</th><th>Días Vencida</th>
                    </tr></thead>
                    <tbody id="tbody-internet">
                        <tr><td colspan="7" class="text-center py-5" style="color:#adb5bd;">Cargando contratos...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── TAB TV ────────────────────────────────────────── --}}
        <div class="tab-pane fade" id="tab-tv" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span style="font-weight:600;color:#a855f7;">Pendientes de Corte TV</span>
                <button id="btn-ejecutar-corte-tv" class="ac-btn-red"><i class="fas fa-ban"></i> Ejecutar Corte TV</button>
            </div>
            <div class="ac-table-wrap">
                <table class="ac-table" id="tabla-tv">
                    <thead><tr><th>Cliente</th><th>Contrato</th><th>Serial ONU</th><th>Factura</th><th>Valor</th><th>Vencimiento</th><th>Estado</th></tr></thead>
                    <tbody id="tbody-tv"><tr><td colspan="7" class="text-center py-5" style="color:#adb5bd;">Cargando...</td></tr></tbody>
                </table>
            </div>
        </div>

        {{-- ── TAB HISTORIAL ─────────────────────────────────── --}}
        <div class="tab-pane fade" id="tab-historial" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span style="font-weight:600;color:#1f2937;">Historial de Ejecución</span>
                <button id="btn-refresh-historial" class="ac-btn-sm"><i class="fas fa-sync-alt"></i> Actualizar</button>
            </div>
            <div class="ac-table-wrap">
                <table class="ac-table" id="tabla-historial">
                    <thead><tr><th>ID</th><th>Tipo</th><th>Procesados</th><th>Cortados</th><th>Omitidos</th><th>Errores</th><th>Duración</th><th>Ejecutó</th><th>Fecha</th><th></th></tr></thead>
                    <tbody id="tbody-historial"><tr><td colspan="10" class="text-center py-5" style="color:#adb5bd;">Cargando...</td></tr></tbody>
                </table>
            </div>
        </div>

        {{-- ── TAB MK SYNC ───────────────────────────────────── --}}
        <div class="tab-pane fade" id="tab-mk" role="tabpanel">
            <div class="ac-mk-bar">
                <div style="min-width:280px;">
                    <label class="d-block mb-1">Router MikroTik</label>
                    <select id="select-mikrotik" class="form-control form-control-sm">
                        <option value="">— Seleccione —</option>
                        @foreach($mikrotiks as $mk)
                        <option value="{{ $mk->id }}">{{ $mk->nombre }} ({{ $mk->ip }})</option>
                        @endforeach
                    </select>
                </div>
                <button id="btn-analizar-mk" class="ac-btn-sm" style="background:#3b82f6;color:#fff;border-color:#3b82f6;"><i class="fas fa-search"></i> Analizar</button>
                <div style="margin-left:auto;"><button id="btn-solucionar-lote" class="ac-btn-sm" disabled><i class="fas fa-magic"></i> Solucionar Lote</button></div>
            </div>

            <div id="mk-sync-result" class="d-none">
                <div class="row mb-3">
                    <div class="col-md-4"><div class="ac-kpi"><div><div class="ac-kpi-title">Morosos OK</div><div class="ac-kpi-value" style="color:#22c55e;" id="mk-ok-count">0</div></div></div></div>
                    <div class="col-md-4"><div class="ac-kpi"><div><div class="ac-kpi-title">Cortados sin morosos MK</div><div class="ac-kpi-value" style="color:#ef4444;" id="mk-faltantes-count">0</div></div></div></div>
                    <div class="col-md-4"><div class="ac-kpi"><div><div class="ac-kpi-title">En morosos MK sin corte</div><div class="ac-kpi-value" style="color:#f97316;" id="mk-extra-count">0</div></div></div></div>
                </div>
                <ul class="ac-tabs nav mb-3" id="mk-subtabs">
                    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#mk-tab-faltantes">Faltantes <span class="badge badge-danger ml-1" id="badge-faltantes">0</span></a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#mk-tab-extra">Extras <span class="badge badge-warning ml-1" id="badge-extra">0</span></a></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="mk-tab-faltantes">
                        <div class="ac-table-wrap"><table class="ac-table"><thead><tr><th>Contrato</th><th>Cliente</th><th>IP</th><th>Factura</th><th>Valor</th></tr></thead><tbody id="tbody-mk-faltantes"><tr><td colspan="5" class="text-center py-4" style="color:#adb5bd;">Sin datos</td></tr></tbody></table></div>
                    </div>
                    <div class="tab-pane fade" id="mk-tab-extra">
                        <div class="ac-table-wrap"><table class="ac-table"><thead><tr><th>IP</th><th>Lista MK</th><th>Comentario</th></tr></thead><tbody id="tbody-mk-extra"><tr><td colspan="3" class="text-center py-4" style="color:#adb5bd;">Sin datos</td></tr></tbody></table></div>
                    </div>
                </div>
            </div>
            <div id="mk-sync-empty" class="ac-empty"><i class="fas fa-server"></i>Seleccione un MikroTik y haga clic en Analizar.</div>
        </div>

    </div>{{-- /tab-content --}}
</div>{{-- /ac-view --}}
</div>{{-- /ac-dark --}}

{{-- Modals --}}
<div class="ac-dark">
<div class="modal fade" id="modal-confirmar-corte" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" style="color:#ef4444;font-weight:700;"><i class="fas fa-ban mr-2"></i>Confirmar Corte Internet</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
    <div class="modal-body"><p>Se ejecutará el corte para <strong id="modal-corte-count" style="color:#ef4444;font-size:1.3rem;">0</strong> contratos pendientes.</p><div class="alert alert-warning"><i class="fas fa-exclamation-triangle mr-1"></i> IPs serán movidas a la lista <code style="color:#eab308;">morosos</code> en MikroTik.</div><div class="form-group mt-3 mb-0"><label class="small" style="color:#6b7280;">Fecha de corte</label><input type="date" id="modal-fecha-corte" class="ac-date-input w-100"></div></div>
    <div class="modal-footer"><button type="button" class="ac-btn-sm" data-dismiss="modal">Cancelar</button><button type="button" id="btn-confirmar-corte" class="ac-btn-red">Ejecutar Corte</button></div>
</div></div></div>

<div class="modal fade" id="modal-confirmar-corte-tv" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" style="color:#a855f7;font-weight:700;"><i class="fas fa-tv mr-2"></i>Confirmar Corte TV</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
    <div class="modal-body"><p>Se ejecutará el corte de TV para <strong id="modal-corte-tv-count" style="color:#a855f7;font-size:1.3rem;">0</strong> contrato(s).</p><div class="alert alert-warning"><i class="fas fa-exclamation-triangle mr-1"></i> ONUs serán deshabilitadas en SmartOLT.</div></div>
    <div class="modal-footer"><button type="button" class="ac-btn-sm" data-dismiss="modal">Cancelar</button><button type="button" id="btn-confirmar-corte-tv" class="ac-btn-red">Ejecutar Corte TV</button></div>
</div></div></div>

<div class="modal fade" id="modal-habilitar" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" style="color:#22c55e;font-weight:700;"><i class="fas fa-power-off mr-2"></i>Habilitar Cortados</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
    <div class="modal-body"><p>¿Habilitar todos los contratos cortados de este grupo?</p></div>
    <div class="modal-footer"><button type="button" class="ac-btn-sm" data-dismiss="modal">Cancelar</button><button type="button" id="btn-confirmar-habilitar" class="ac-btn-green">Confirmar</button></div>
</div></div></div>

<div class="modal fade" id="modal-log-detail" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" style="font-weight:700;">Detalle de ejecución</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
    <div class="modal-body"><div class="ac-table-wrap"><table class="ac-table"><thead><tr><th>Contrato</th><th>Cliente</th><th>IP</th><th>Tipo</th><th>Resultado</th><th>Método</th><th>Descripción</th></tr></thead><tbody id="log-detail-body"><tr><td colspan="7" class="text-center py-4" style="color:#adb5bd;">Cargando…</td></tr></tbody></table></div></div>
</div></div></div>
</div>

@endsection

@section('scripts')
<script>
(function(){
    // Apply dark class to layout wrappers
    var cw = document.querySelector('.content-wrapper');
    if(cw) cw.classList.add('ac-dark');
    var mp = document.querySelector('.main-panel');
    if(mp) mp.classList.add('ac-dark');
    var sc = document.querySelector('.stretch-card');
    if(sc) sc.classList.add('ac-dark');

    var GRUPO_ID = {{ $grupo->id }};
    var csrfToken = '{{ csrf_token() }}';
    var URLS = {
        summary:          '{{ route("grupos-corte.corte-summary",         ["id" => $grupo->id]) }}',
        allContracts:     '{{ route("grupos-corte.all-contracts",         ["id" => $grupo->id]) }}',
        pendingTv:        '{{ route("grupos-corte.corte-pending-tv") }}',
        history:          '{{ route("grupos-corte.corte-history",         ["id" => $grupo->id]) }}',
        historyDetail:    '{{ route("grupos-corte.corte-history-detail",  ["logId" => "LOGID_PH"]) }}',
        mkSync:           '{{ route("grupos-corte.mk-sync") }}',
        solucionarLote:   '{{ route("grupos-corte.solucionar-discrepancia-lote") }}',
        limpiarCache:     '{{ route("grupos-corte.limpiar-cache-cortes") }}',
        ejecutarStream:   '{{ route("grupos-corte.ejecutar-corte-internet-stream") }}',
        ejecutarTv:       '{{ route("grupos-corte.ejecutar-corte-tv") }}',
        habilitarCortados:'{{ route("grupos-corte.habilitar-cortados-internet") }}',
    };

    var allContracts = [];
    var currentFilter = 'todos';

    function gf() { return document.getElementById('fecha-ref').value; }
    function fmt(n) { return (n==null||n==undefined)?'—':new Intl.NumberFormat('es-CO').format(n); }

    function badge(estado) {
        if(!estado) return '<span class="ac-badge ac-badge-grey">—</span>';
        var m = {
            'activo_ok':'ac-badge-green', 'pendiente_corte':'ac-badge-red',
            'factura_antigua_vencida':'ac-badge-orange', 'ya_cortado':'ac-badge-grey',
            'bloqueado_promesa':'ac-badge-cyan', 'bloqueado_no_suspension':'ac-badge-blue',
            'bloqueado_sin_mk':'ac-badge-orange', 'bloqueado_sin_ip':'ac-badge-orange',
        };
        var labels = {
            'activo_ok':'AL DÍA', 'pendiente_corte':'PEND. CORTE',
            'factura_antigua_vencida':'FACT. ANTIGUA', 'ya_cortado':'SUSPENDIDOS',
            'bloqueado_promesa':'PROMESA', 'bloqueado_no_suspension':'NO SUSP.',
            'bloqueado_sin_mk':'SIN MK', 'bloqueado_sin_ip':'SIN IP',
        };
        var cls = m[estado] || 'ac-badge-grey';
        var lbl = labels[estado] || estado.toUpperCase();
        return '<span class="ac-badge '+cls+'">'+lbl+'</span>';
    }

    function filterGroup(estado) {
        if(!estado) return 'suspendidos';
        if(estado==='activo_ok') return 'aldia';
        if(estado==='factura_antigua_vencida') return 'mora';
        if(estado==='ya_cortado') return 'suspendidos';
        return 'otros'; // pendiente_corte, bloqueado_* go to 'otros' but shown in 'todos'
    }

    function renderTable() {
        var q = ($('#search-contratos').val()||'').toLowerCase();
        var filtered = allContracts.filter(function(c) {
            var g = filterGroup(c.estado_internet);
            var fOk = currentFilter==='todos' || g===currentFilter;
            var sOk = true;
            if(q) {
                var s = ((c.cliente_nombre||'')+ ' '+(c.cliente_nit||'')+' '+(c.contrato_nro||'')+' '+(c.ip||'')+' '+(c.usuario||'')).toLowerCase();
                sOk = s.indexOf(q) !== -1;
            }
            return fOk && sOk;
        });

        if(!filtered.length) {
            $('#tbody-internet').html('<tr><td colspan="7" class="text-center py-5" style="color:#adb5bd;">No hay contratos para mostrar</td></tr>');
            return;
        }
        var h='';
        filtered.forEach(function(c){
            var dias = c.dias_vencida > 0 ? '<span class="ac-days-red">'+c.dias_vencida+'d</span>' : '<span class="ac-dim">—</span>';
            h += '<tr>'+
                '<td><span class="ac-client-name">'+(c.cliente_nombre||'—')+'</span><span class="ac-client-doc">'+(c.cliente_nit||'')+'</span></td>'+
                '<td>'+(c.contrato_nro||'—')+'</td>'+
                '<td>'+badge(c.estado_internet)+'</td>'+
                '<td><span class="ac-ip">'+(c.ip||'0.0.0.0')+'</span><span class="ac-pppoe">PPPoE: '+(c.usuario||'N/A')+'</span>'+(c.olt_sn_mac?'<div class="ac-olt">OLT: '+c.olt_sn_mac+'</div>':'')+'</td>'+
                '<td class="ac-dim">'+(c.mikrotik_nombre||'—')+'</td>'+
                '<td class="ac-dim">'+(c.ultima_vencimiento||'—')+'</td>'+
                '<td>'+dias+'</td>'+
            '</tr>';
        });
        $('#tbody-internet').html(h);
    }

    function loadAll() {
        $('#tbody-internet').html('<tr><td colspan="7" class="text-center py-5" style="color:#adb5bd;"><i class="fas fa-circle-notch fa-spin mr-2"></i>Cargando contratos...</td></tr>');
        $.getJSON(URLS.allContracts).done(function(res) {
            allContracts = res.contratos || [];
            var stats = res.stats_internet || {};

            var total = allContracts.length;
            var aldia = stats.activo_ok || 0;
            var antigua = stats.factura_antigua_vencida || 0;
            var susp = stats.ya_cortado || 0;

            $('#f-todos').text(total);
            $('#f-aldia').text(aldia);
            $('#f-antigua').text(antigua);
            $('#f-susp').text(susp);
            $('#btn-count-cortados').text(susp);
            $('#btn-count-pend').text(stats.pendiente_corte || 0);

            if((stats.pendiente_corte||0) > 0) $('#btn-ejecutar-corte').show(); else $('#btn-ejecutar-corte').hide();

            renderTable();
        }).fail(function(){ $('#tbody-internet').html('<tr><td colspan="7" class="text-center py-5" style="color:#ef4444;">Error al cargar contratos</td></tr>'); });
    }

    function loadSummary() {
        $.getJSON(URLS.summary).done(function(d) {
            $('#kpi-total').text(fmt(d.total_contratos));
            $('#kpi-activos').text(fmt(d.total_activos));
            $('#kpi-pend-inet').text(fmt(d.pendientes_internet));
            $('#kpi-cut-inet').text(fmt(d.total_cortados_internet));
            $('#kpi-pend-tv').text(fmt(d.pendientes_tv));
            $('#kpi-cut-tv').text(fmt(d.total_cortados_tv));
            $('#kpi-olt').text(fmt(d.total_con_olt));
            $('#kpi-mk').text(fmt(d.total_con_mikrotik));
            $('#kpi-prorroga').text(d.prorroga_tv || 0);
            $('#modal-corte-count').text(d.pendientes_internet);
        });
    }

    function loadTv() {
        var f = gf();
        $('#tbody-tv').html('<tr><td colspan="7" class="text-center py-5" style="color:#adb5bd;"><i class="fas fa-circle-notch fa-spin mr-2"></i>Cargando...</td></tr>');
        $.getJSON(URLS.pendingTv+'?grupo_id='+GRUPO_ID+'&fecha='+f).done(function(res){
            var list=res.data||[]; $('#modal-corte-tv-count').text(list.length);
            if(!list.length){ $('#tbody-tv').html('<tr><td colspan="7" class="text-center py-5" style="color:#22c55e;"><i class="fas fa-check-circle mr-2"></i>Sin pendientes de corte TV</td></tr>'); return; }
            var h='';
            list.forEach(function(c){ h+='<tr><td><span class="ac-client-name">'+(c.cliente_nombre||'—')+'</span></td><td>'+(c.contrato_nro||'—')+'</td><td style="color:#06b6d4;">'+(c.serial_onu||'—')+'</td><td class="ac-dim">'+(c.factura_codigo||'—')+'</td><td style="font-weight:600;">$'+fmt(c.factura_total)+'</td><td class="ac-dim">'+(c.fecha_vencimiento||'—')+'</td><td>'+badge(c.estado_contrato==='cortado'?'ya_cortado':'pendiente_corte')+'</td></tr>'; });
            $('#tbody-tv').html(h);
        });
    }

    function loadHist() {
        $('#tbody-historial').html('<tr><td colspan="10" class="text-center py-5" style="color:#adb5bd;"><i class="fas fa-circle-notch fa-spin mr-2"></i>Cargando...</td></tr>');
        $.getJSON(URLS.history).done(function(res){
            var data = res.logs || res || [];
            if(!data.length){ $('#tbody-historial').html('<tr><td colspan="10" class="text-center py-5" style="color:#adb5bd;">Sin historial</td></tr>'); return; }
            var h='';
            data.forEach(function(r){
                var tip = r.tipo==='internet'?'<span class="ac-badge ac-badge-blue">NET</span>':'<span class="ac-badge ac-badge-cyan">TV</span>';
                var dur = r.duracion_ms?(r.duracion_ms/1000).toFixed(1)+'s':'—';
                h+='<tr><td class="ac-dim">#'+r.id+'</td><td>'+tip+'</td><td>'+(r.total_procesados||0)+'</td><td style="color:#ef4444;font-weight:700;">'+(r.total_cortados||0)+'</td><td>'+(r.total_omitidos||0)+'</td><td style="color:#f97316;">'+(r.total_errores||0)+'</td><td class="ac-dim">'+dur+'</td><td class="ac-dim" style="font-size:0.75rem;">'+(r.ejecutado_por_nombre||'CRON')+'</td><td class="ac-dim" style="font-size:0.75rem;">'+(r.created_at||'—')+'</td><td><button class="ac-btn-sm btn-ver-detalle" data-id="'+r.id+'"><i class="fas fa-eye"></i></button></td></tr>';
            });
            $('#tbody-historial').html(h);
        });
    }

    /* Filters */
    $(document).on('click','.ac-pill',function(){ $('.ac-pill').removeClass('active'); $(this).addClass('active'); currentFilter=$(this).data('filter'); renderTable(); });
    $('#search-contratos').on('keyup', renderTable);

    /* Refresh */
    $('#btn-apply-date, #btn-refresh').on('click',function(){ loadAll(); loadTv(); loadSummary(); });
    $('#btn-refresh-historial').on('click', loadHist);
    $('a[href="#tab-historial"]').on('shown.bs.tab', loadHist);
    $('#btn-clear-cache').on('click',function(){ $.ajax({url:URLS.limpiarCache,method:'POST',data:{_token:csrfToken,grupo_id:GRUPO_ID}}).done(function(){loadAll();loadTv();loadSummary();}); });

    /* MK Sync */
    $('#btn-analizar-mk').on('click',function(){
        var mkId=$('#select-mikrotik').val(); if(!mkId){alert('Seleccione un MikroTik.');return;}
        var $b=$(this).prop('disabled',true).html('<i class="fas fa-spinner fa-spin"></i>');
        $('#mk-sync-empty').addClass('d-none'); $('#mk-sync-result').addClass('d-none');
        $.ajax({url:URLS.mkSync,method:'POST',data:{_token:csrfToken,mikrotik_id:mkId,grupo_id:GRUPO_ID}}).done(function(d){
            if(!d.disponible){ $('#mk-sync-empty').removeClass('d-none').html('<div style="color:#ef4444;padding:1rem;">'+(d.error||'No disponible')+'</div>'); return; }
            var f=d.inconsistencias&&d.inconsistencias.cortados_sin_morosos?d.inconsistencias.cortados_sin_morosos:[];
            var e=d.inconsistencias&&d.inconsistencias.morosos_sin_corte?d.inconsistencias.morosos_sin_corte:[];
            $('#mk-ok-count').text(d.resumen?(d.resumen.en_morosos_ok||0):0); $('#mk-faltantes-count').text(f.length); $('#mk-extra-count').text(e.length);
            $('#badge-faltantes').text(f.length); $('#badge-extra').text(e.length);
            var rf=''; if(!f.length){rf='<tr><td colspan="5" class="text-center py-4" style="color:#22c55e;">Sin discrepancias</td></tr>';}else{f.forEach(function(c){rf+='<tr><td>'+(c.nro||c.id)+'</td><td>'+(c.cliente_nombre||'—')+'</td><td class="ac-ip">'+(c.ip||'—')+'</td><td class="ac-dim">'+(c.factura_codigo||'—')+'</td><td>$'+fmt(c.factura_total)+'</td></tr>';});}
            $('#tbody-mk-faltantes').html(rf);
            var re=''; if(!e.length){re='<tr><td colspan="3" class="text-center py-4" style="color:#22c55e;">Sin extras</td></tr>';}else{e.forEach(function(x){re+='<tr><td class="ac-ip">'+(x.address||'—')+'</td><td style="color:#f97316;">'+(x.list||'—')+'</td><td class="ac-dim">'+(x.comment||'—')+'</td></tr>';});}
            $('#tbody-mk-extra').html(re);
            $('#mk-sync-result').removeClass('d-none'); $('#btn-solucionar-lote').prop('disabled',f.length===0);
        }).fail(function(){$('#mk-sync-empty').removeClass('d-none').html('<div style="color:#ef4444;padding:1rem;">Error al analizar.</div>');}).always(function(){$b.prop('disabled',false).html('<i class="fas fa-search"></i> Analizar');});
    });
    $('#btn-solucionar-lote').on('click',function(){
        var mkId=$('#select-mikrotik').val(); if(!mkId||!confirm('¿Ejecutar?'))return;
        var $b=$(this).prop('disabled',true).html('<i class="fas fa-spinner fa-spin"></i>');
        $.ajax({url:URLS.solucionarLote,method:'POST',data:{_token:csrfToken,mikrotik_id:mkId,grupo_id:GRUPO_ID}}).done(function(r){alert(r.message||'Listo');$('#btn-analizar-mk').trigger('click');}).fail(function(x){alert('Error: '+(x.responseJSON&&x.responseJSON.message?x.responseJSON.message:'Desconocido'));}).always(function(){$b.prop('disabled',false).html('<i class="fas fa-magic"></i> Solucionar Lote');});
    });

    /* Corte Internet */
    $('#btn-ejecutar-corte').on('click',function(){ $('#modal-fecha-corte').val(gf()); $('#modal-confirmar-corte').modal('show'); });
    $('#btn-confirmar-corte').on('click',function(){
        $('#modal-confirmar-corte').modal('hide');
        var f=$('#modal-fecha-corte').val();
        $('#progress-bar-wrap').fadeIn(); $('#progress-bar').css('width','0%'); $('#progress-label').text('Ejecutando corte...'); $('#progress-count').text('0 / ?');
        var total=parseInt($('#btn-count-pend').text())||0;
        var es=new EventSource(URLS.ejecutarStream+'?grupo_id='+GRUPO_ID+'&fecha='+f);
        es.onmessage=function(e){var d=JSON.parse(e.data);if(d.done){es.close();$('#progress-bar-wrap').fadeOut();alert('Corte completado. Cortados: '+d.cortados+' | Errores: '+d.errores);loadAll();loadSummary();return;}var pct=total>0?Math.round(d.progreso/total*100):0;$('#progress-bar').css('width',pct+'%');$('#progress-count').text(d.progreso+' / '+d.total);};
        es.onerror=function(){es.close();$('#progress-bar-wrap').fadeOut();alert('Error en el stream.');};
    });

    /* Corte TV */
    $('#btn-ejecutar-corte-tv').on('click',function(){ $('#modal-confirmar-corte-tv').modal('show'); });
    $('#btn-confirmar-corte-tv').on('click',function(){
        $('#modal-confirmar-corte-tv').modal('hide');
        var $b=$('#btn-ejecutar-corte-tv').prop('disabled',true).html('<i class="fas fa-spinner fa-spin"></i>');
        $.ajax({url:URLS.ejecutarTv,method:'POST',data:{_token:csrfToken,grupo_id:GRUPO_ID,fecha:gf()}}).done(function(r){alert('Completado. Cortados: '+r.cortados);loadTv();loadSummary();}).always(function(){$b.prop('disabled',false).html('<i class="fas fa-ban"></i> Ejecutar Corte TV');});
    });

    /* Habilitar */
    $('#btn-habilitar-cortados').on('click',function(){ $('#modal-habilitar').modal('show'); });
    $('#btn-confirmar-habilitar').on('click',function(){
        $('#modal-habilitar').modal('hide');
        var $b=$('#btn-habilitar-cortados').prop('disabled',true).html('<i class="fas fa-spinner fa-spin"></i>');
        $.ajax({url:URLS.habilitarCortados,method:'POST',data:{_token:csrfToken,grupo_id:GRUPO_ID}}).done(function(r){alert('Habilitados: '+r.habilitados+' | Errores: '+r.errores);loadAll();loadSummary();}).always(function(){$b.prop('disabled',false).html('<i class="fas fa-power-off"></i> Habilitar grupo cortado (<span id="btn-count-cortados">0</span>)');});
    });

    /* Historial detail */
    $(document).on('click','.btn-ver-detalle',function(){
        var logId=$(this).data('id');
        $('#log-detail-body').html('<tr><td colspan="7" class="text-center py-4" style="color:#adb5bd;">Cargando…</td></tr>');
        $('#modal-log-detail').modal('show');
        $.getJSON(URLS.historyDetail.replace('LOGID_PH',logId)).done(function(data){
            var list=data.detalle||data||[]; if(!list.length){$('#log-detail-body').html('<tr><td colspan="7" class="text-center py-4" style="color:#adb5bd;">Sin detalle</td></tr>');return;}
            var h='';
            list.forEach(function(r){
                var res=r.resultado==='cortado'?'<span class="ac-badge ac-badge-green">OK</span>':(r.resultado==='error'?'<span class="ac-badge ac-badge-red">ERROR</span>':'<span class="ac-badge ac-badge-grey">'+(r.resultado||'—')+'</span>');
                h+='<tr><td>'+(r.contrato_nro||r.contrato_id||'—')+'</td><td>'+(r.cliente_nombre||'—')+'</td><td class="ac-ip">'+(r.ip||'—')+'</td><td>'+(r.tipo||'—')+'</td><td>'+res+'</td><td class="ac-dim" style="font-size:0.75rem;">'+(r.metodo||'—')+'</td><td class="ac-dim" style="font-size:0.75rem;">'+(r.descripcion||r.error_detalle||'—')+'</td></tr>';
            });
            $('#log-detail-body').html(h);
        });
    });

    /* Init */
    $(document).ready(function(){ loadSummary(); loadAll(); loadTv(); });
})();
</script>
@endsection
