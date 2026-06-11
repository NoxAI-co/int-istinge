@extends('layouts.app')

@section('boton')
<a href="{{ route('grupos-corte.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-backward"></i> Regresar</a>
<a href="{{ route('grupos-corte.show', $grupo->id) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-eye"></i> Ver Grupo</a>
@endsection

@section('styles')
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
    --bg-main: #0a0a0a;
    --bg-card: #121212;
    --border-color: #262626;
    --text-main: #f5f5f5;
    --text-muted: #a3a3a3;
    --text-dark-muted: #525252;
    
    --color-blue: #3b82f6;
    --bg-blue-dim: rgba(59, 130, 246, 0.1);
    
    --color-red: #ef4444;
    --bg-red-dim: rgba(239, 68, 68, 0.1);
    
    --color-orange: #f97316;
    --bg-orange-dim: rgba(249, 115, 22, 0.1);
    
    --color-purple: #a855f7;
    --bg-purple-dim: rgba(168, 85, 247, 0.1);
    
    --color-cyan: #06b6d4;
    --bg-cyan-dim: rgba(6, 182, 212, 0.1);

    --color-green: #10b981;
    --bg-green-dim: rgba(16, 185, 129, 0.1);

    --font-family: 'Inter', sans-serif;
}

body {
    background-color: var(--bg-main) !important;
    color: var(--text-main) !important;
    font-family: var(--font-family);
}

.analisis-cortes-container {
    padding: 1rem 2rem;
}

/* Page Header */
.page-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-main);
    margin: 0;
}
.page-subtitle {
    font-size: 0.85rem;
    color: var(--text-muted);
}

/* KPI Cards */
.kpi-container {
    display: flex;
    gap: 1rem;
    overflow-x: auto;
    padding-bottom: 1rem;
    margin-bottom: 1rem;
}
.kpi-container::-webkit-scrollbar { height: 6px; }
.kpi-container::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 4px; }

.kpi-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1.25rem;
    min-width: 220px;
    flex: 1;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}
.kpi-content {
    display: flex;
    flex-direction: column;
}
.kpi-title {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--text-muted);
    letter-spacing: 0.5px;
    text-transform: uppercase;
    margin-bottom: 0.5rem;
}
.kpi-value {
    font-size: 2.2rem;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 0.5rem;
}
.kpi-subtitle {
    font-size: 0.75rem;
    color: var(--text-dark-muted);
    font-weight: 500;
}

.kpi-icon-wrap {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

/* Colors for KPIs */
.kpi-blue .kpi-value { color: var(--color-blue); }
.kpi-blue .kpi-icon-wrap { background: var(--bg-blue-dim); color: var(--color-blue); }

.kpi-red .kpi-value { color: var(--color-red); }
.kpi-red .kpi-icon-wrap { background: var(--bg-red-dim); color: var(--color-red); }

.kpi-orange .kpi-value { color: var(--color-orange); }
.kpi-orange .kpi-icon-wrap { background: var(--bg-orange-dim); color: var(--color-orange); }

.kpi-purple .kpi-value { color: var(--color-purple); }
.kpi-purple .kpi-icon-wrap { background: var(--bg-purple-dim); color: var(--color-purple); }

.kpi-cyan .kpi-value { color: var(--color-cyan); }
.kpi-cyan .kpi-icon-wrap { background: var(--bg-cyan-dim); color: var(--color-cyan); }


/* Tabs Navigation (Pill style) */
.nav-pills-custom {
    background: #141414;
    border-radius: 12px;
    display: inline-flex;
    padding: 0.25rem;
    margin-bottom: 1.5rem;
    border: 1px solid var(--border-color);
}
.nav-pills-custom .nav-link {
    color: var(--text-muted);
    font-weight: 500;
    font-size: 0.85rem;
    padding: 0.6rem 1.2rem;
    border-radius: 8px;
    transition: all 0.2s;
}
.nav-pills-custom .nav-link:hover {
    color: var(--text-main);
}
.nav-pills-custom .nav-link.active {
    background: #262626;
    color: var(--text-main);
}
.nav-pills-custom .nav-link i {
    margin-right: 6px;
    font-size: 1rem;
    color: var(--color-blue);
}
.nav-pills-custom .nav-link.active i {
    color: var(--color-blue);
}

/* Sub-filters (Image 3) */
.filters-row {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 1rem;
    align-items: center;
    flex-wrap: wrap;
}
.filter-pill {
    background: transparent;
    border: 1px solid var(--border-color);
    border-radius: 20px;
    padding: 0.35rem 0.8rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 6px;
}
.filter-pill:hover, .filter-pill.active {
    background: #262626;
    color: var(--text-main);
}
.filter-pill .count {
    color: var(--text-dark-muted);
}
.filter-pill.active .count {
    color: var(--text-muted);
}
.filter-pill .dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
}
.dot-green { background: var(--color-green); }
.dot-orange { background: var(--color-orange); }
.dot-grey { background: var(--text-dark-muted); }

/* Buttons */
.btn-green-glow {
    background: #059669;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 0.6rem 1.2rem;
    font-size: 0.85rem;
    font-weight: 600;
    box-shadow: 0 0 15px rgba(16, 185, 129, 0.4);
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}
.btn-green-glow:hover {
    background: #10b981;
    box-shadow: 0 0 20px rgba(16, 185, 129, 0.6);
    color: white;
}

.btn-red-glow {
    background: #dc2626;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 0.6rem 1.2rem;
    font-size: 0.85rem;
    font-weight: 600;
    box-shadow: 0 0 15px rgba(239, 68, 68, 0.4);
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}
.btn-red-glow:hover {
    background: #ef4444;
    box-shadow: 0 0 20px rgba(239, 68, 68, 0.6);
    color: white;
}

/* Search Bar */
.search-container {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
}
.search-input {
    background: transparent;
    border: none;
    color: var(--text-main);
    width: 100%;
    outline: none;
    font-size: 0.9rem;
}
.search-input::placeholder {
    color: var(--text-dark-muted);
}

/* Tables */
.table-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    overflow: hidden;
}
.table {
    width: 100%;
    margin-bottom: 0;
    color: var(--text-main);
}
.table th {
    background: transparent;
    color: var(--text-dark-muted);
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    border-top: none;
    border-bottom: 1px solid var(--border-color);
    padding: 1rem;
}
.table td {
    padding: 1rem;
    vertical-align: middle;
    border-top: 1px solid #1a1a1a;
    font-size: 0.85rem;
}
.table tbody tr:hover {
    background: #171717;
}

.client-name {
    font-weight: 600;
    color: var(--text-main);
    display: block;
}
.client-doc {
    font-size: 0.75rem;
    color: var(--text-dark-muted);
}
.ip-address {
    font-family: 'JetBrains Mono', monospace;
    font-weight: 600;
    color: var(--text-main);
    display: block;
}
.pppoe-user {
    font-size: 0.75rem;
    color: var(--text-muted);
}
.olt-info {
    font-size: 0.7rem;
    color: var(--color-blue);
    font-weight: 600;
}

.badge-status {
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid transparent;
}
.badge-status::before {
    content: '';
    width: 6px; height: 6px; border-radius: 50%;
}
.badge-status.suspendidos { background: rgba(255,255,255,0.05); border-color: var(--border-color); color: var(--text-muted); }
.badge-status.suspendidos::before { background: var(--text-dark-muted); }

.badge-status.aldia { background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.2); color: var(--color-green); }
.badge-status.aldia::before { background: var(--color-green); }

.badge-status.promesa { background: rgba(6, 182, 212, 0.1); border-color: rgba(6, 182, 212, 0.2); color: var(--color-cyan); }
.badge-status.promesa::before { background: var(--color-cyan); }

.badge-status.mora { background: rgba(249, 115, 22, 0.1); border-color: rgba(249, 115, 22, 0.2); color: var(--color-orange); }
.badge-status.mora::before { background: var(--color-orange); }


/* Modals */
.modal-content { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-main); }
.modal-header { border-bottom: 1px solid var(--border-color); }
.modal-footer { border-top: 1px solid var(--border-color); }
.close { color: var(--text-main); text-shadow: none; opacity: 1; }
.close:hover { color: var(--color-red); }
</style>
@endsection

@section('content')
<div class="container-fluid analisis-cortes-container">
    
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title">Análisis de Cortes: {{ $grupo->nombre }}</h1>
            <div class="page-subtitle mt-1">Corte: {{ $grupo->fecha_corte }} | Suspensión: {{ $grupo->fecha_suspension }}</div>
        </div>
        <div>
            <input type="date" id="fecha-ref" class="form-control form-control-sm d-inline-block w-auto mr-2" style="background:#121212; border:1px solid #262626; color:#a3a3a3; border-radius:8px;" value="{{ date('Y-m-d') }}">
            <button id="btn-apply-date" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;"><i class="fas fa-sync-alt"></i></button>
        </div>
    </div>

    {{-- KPIs (Image 1 replica) --}}
    <div class="kpi-container" id="kpi-row">
        <div class="kpi-card kpi-blue">
            <div class="kpi-content">
                <div class="kpi-title">TOTAL CONTRATOS</div>
                <div class="kpi-value" id="kpi-total">0</div>
                <div class="kpi-subtitle"><span id="kpi-activos">0</span> activos</div>
            </div>
            <div class="kpi-icon-wrap"><i class="fas fa-user-friends"></i></div>
        </div>
        <div class="kpi-card kpi-red">
            <div class="kpi-content">
                <div class="kpi-title">PENDIENTES INTERNET</div>
                <div class="kpi-value" id="kpi-pendientes">0</div>
                <div class="kpi-subtitle">por cortar ahora</div>
            </div>
            <div class="kpi-icon-wrap"><i class="fas fa-wifi"></i></div>
        </div>
        <div class="kpi-card kpi-orange">
            <div class="kpi-content">
                <div class="kpi-title">CORTADOS INTERNET</div>
                <div class="kpi-value" id="kpi-cortados">0</div>
                <div class="kpi-subtitle">state=disabled</div>
            </div>
            <div class="kpi-icon-wrap"><i class="fas fa-ban"></i></div>
        </div>
        <div class="kpi-card kpi-purple">
            <div class="kpi-content">
                <div class="kpi-title">PENDIENTES TV</div>
                <div class="kpi-value" id="kpi-pendientes-tv">0</div>
                <div class="kpi-subtitle">prorroga: 0d</div>
            </div>
            <div class="kpi-icon-wrap"><i class="fas fa-tv"></i></div>
        </div>
        <div class="kpi-card kpi-purple">
            <div class="kpi-content">
                <div class="kpi-title">CORTADOS TV</div>
                <div class="kpi-value" id="kpi-cortados-tv">0</div>
                <div class="kpi-subtitle">state_olt_catv=0</div>
            </div>
            <div class="kpi-icon-wrap"><i class="fas fa-eye-slash"></i></div>
        </div>
        <div class="kpi-card kpi-cyan">
            <div class="kpi-content">
                <div class="kpi-title">CON OLT</div>
                <div class="kpi-value" id="kpi-olt">0</div>
                <div class="kpi-subtitle"><span id="kpi-mk">0</span> con MikroTik</div>
            </div>
            <div class="kpi-icon-wrap"><i class="fas fa-network-wired"></i></div>
        </div>
    </div>

    {{-- Tabs (Image 2 replica) --}}
    <ul class="nav nav-pills-custom" id="main-tabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="tab-contratos-link" data-toggle="tab" href="#tab-contratos">
                <i class="fas fa-wifi"></i> Internet
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-tv-link" data-toggle="tab" href="#tab-tv">
                <i class="fas fa-tv" style="color:var(--color-purple);"></i> Televisión
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-historial-link" data-toggle="tab" href="#tab-historial">
                <i class="fas fa-history" style="color:var(--text-muted);"></i> Historial
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-mk-link" data-toggle="tab" href="#tab-mk">
                <i class="fas fa-server" style="color:var(--color-cyan);"></i> Sync MK
            </a>
        </li>
    </ul>

    <div class="tab-content" id="main-tab-content">
        {{-- TAB CONTRATOS (Image 3 replica) --}}
        <div class="tab-pane fade show active" id="tab-contratos" role="tabpanel">
            
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="filters-row mb-0">
                    <div class="filter-pill active" data-filter="todos">
                        Todos <span class="count" id="count-todos">0</span>
                    </div>
                    <div class="filter-pill" data-filter="aldia">
                        <div class="dot dot-green"></div> Al día <span class="count" id="count-aldia">0</span>
                    </div>
                    <div class="filter-pill" data-filter="mora">
                        <div class="dot dot-orange"></div> En mora <span class="count" id="count-mora">0</span>
                    </div>
                    <div class="filter-pill" data-filter="suspendidos">
                        <div class="dot dot-grey"></div> Suspendidos <span class="count" id="count-suspendidos">0</span>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button id="btn-habilitar-cortados" class="btn-green-glow">
                        <i class="fas fa-power-off"></i> Habilitar grupo cortado (<span id="btn-count-cortados">0</span>)
                    </button>
                    <button id="btn-ejecutar-corte" class="btn-red-glow" style="display:none;">
                        <i class="fas fa-ban"></i> Ejecutar Corte Internet (<span id="btn-count-pendientes">0</span>)
                    </button>
                </div>
            </div>

            <div class="search-container">
                <input type="text" class="search-input" id="search-contratos" placeholder="Buscar cliente, NIT, contrato, IP, usuario PPPoE...">
            </div>

            <div class="table-card">
                <table class="table" id="tabla-internet">
                    <thead>
                        <tr>
                            <th>CLIENTE</th>
                            <th>CONTRATO</th>
                            <th>ESTADO</th>
                            <th>IP / ACCESO</th>
                            <th>MIKROTIK</th>
                            <th>ÚLT. FACTURA</th>
                            <th>VENCIMIENTO</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-internet">
                        <tr><td colspan="7" class="text-center py-5 text-muted">Cargando contratos...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- TAB TV --}}
        <div class="tab-pane fade" id="tab-tv" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="m-0 text-white font-weight-bold">Contratos TV Pendientes</h5>
                <button id="btn-ejecutar-corte-tv" class="btn-red-glow">
                    <i class="fas fa-ban"></i> Ejecutar Corte TV
                </button>
            </div>
            <div class="table-card">
                <table class="table" id="tabla-tv">
                    <thead>
                        <tr>
                            <th>CLIENTE</th>
                            <th>CONTRATO</th>
                            <th>SERIAL ONU</th>
                            <th>FACTURA</th>
                            <th>VALOR</th>
                            <th>VENCIMIENTO</th>
                            <th>ESTADO</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-tv">
                        <tr><td colspan="7" class="text-center py-5 text-muted">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- TAB HISTORIAL --}}
        <div class="tab-pane fade" id="tab-historial" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="m-0 text-white font-weight-bold">Historial de Ejecución</h5>
                <button id="btn-refresh-historial" class="btn btn-sm" style="background:#262626; color:white; border-radius:8px;">
                    <i class="fas fa-sync-alt"></i> Actualizar
                </button>
            </div>
            <div class="table-card">
                <table class="table" id="tabla-historial">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>TIPO</th>
                            <th>PROCESADOS</th>
                            <th>CORTADOS</th>
                            <th>OMITIDOS</th>
                            <th>ERRORES</th>
                            <th>DURACIÓN</th>
                            <th>EJECUTADO POR</th>
                            <th>FECHA</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="tbody-historial">
                        <tr><td colspan="10" class="text-center py-5 text-muted">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- TAB MK SYNC --}}
        <div class="tab-pane fade" id="tab-mk" role="tabpanel">
            <div class="d-flex align-items-end gap-3 mb-4" style="background:#121212; border:1px solid #262626; padding:1.5rem; border-radius:12px;">
                <div style="min-width: 300px;">
                    <label class="small font-weight-bold text-muted mb-2">Seleccionar MikroTik</label>
                    <select id="select-mikrotik" class="form-control" style="background:#0a0a0a; border:1px solid #262626; color:white; border-radius:8px;">
                        <option value="">— Seleccione —</option>
                        @foreach($mikrotiks as $mk)
                        <option value="{{ $mk->id }}">{{ $mk->nombre }} ({{ $mk->ip }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <button id="btn-analizar-mk" class="btn btn-primary" style="background:#3b82f6; border:none; border-radius:8px;">
                        <i class="fas fa-search"></i> Analizar
                    </button>
                </div>
                <div class="ml-auto">
                    <button id="btn-solucionar-lote" class="btn btn-warning" style="border-radius:8px;" disabled>
                        <i class="fas fa-magic"></i> Solucionar Lote
                    </button>
                </div>
            </div>

            <div id="mk-sync-result" class="d-none">
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="kpi-card" style="border-top: 3px solid var(--color-green);">
                            <div class="kpi-content">
                                <div class="kpi-title">MOROSOS OK</div>
                                <div class="kpi-value" style="color:var(--color-green);" id="mk-ok-count">0</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="kpi-card" style="border-top: 3px solid var(--color-red);">
                            <div class="kpi-content">
                                <div class="kpi-title">CORTADOS SIN MOROSOS MK</div>
                                <div class="kpi-value" style="color:var(--color-red);" id="mk-faltantes-count">0</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="kpi-card" style="border-top: 3px solid var(--color-orange);">
                            <div class="kpi-content">
                                <div class="kpi-title">EN MOROSOS MK SIN CORTE</div>
                                <div class="kpi-value" style="color:var(--color-orange);" id="mk-extra-count">0</div>
                            </div>
                        </div>
                    </div>
                </div>

                <ul class="nav nav-pills-custom" id="mk-subtabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#mk-tab-faltantes">Faltantes en MK <span class="badge badge-danger ml-1" id="badge-faltantes">0</span></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#mk-tab-extra">Extras en MK <span class="badge badge-warning ml-1" id="badge-extra">0</span></a>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="mk-tab-faltantes">
                        <div class="table-card">
                            <table class="table">
                                <thead><tr><th>CONTRATO</th><th>CLIENTE</th><th>IP</th><th>FACTURA</th><th>VALOR</th></tr></thead>
                                <tbody id="tbody-mk-faltantes"><tr><td colspan="5" class="text-center py-4 text-muted">Sin datos</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="mk-tab-extra">
                        <div class="table-card">
                            <table class="table">
                                <thead><tr><th>IP</th><th>LISTA MK</th><th>COMENTARIO</th></tr></thead>
                                <tbody id="tbody-mk-extra"><tr><td colspan="3" class="text-center py-4 text-muted">Sin datos</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div id="mk-sync-empty" class="text-center py-5" style="border:1px dashed #262626; border-radius:12px;">
                <span class="text-muted">Seleccione un MikroTik y haga clic en Analizar.</span>
            </div>
        </div>

    </div>
</div>

{{-- Modals from original logic but styled dark --}}
{{-- Modal: Confirmar corte internet --}}
<div class="modal fade" id="modal-confirmar-corte" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom:1px solid #ef4444;">
                <h5 class="modal-title font-weight-bold text-white"><i class="fas fa-ban" style="color:#ef4444;"></i> Confirmar Corte Internet</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Se ejecutará el corte para <strong id="modal-corte-count" style="color:#ef4444; font-size:1.2rem;">0</strong> contratos pendientes.</p>
                <div class="form-group mt-3 mb-0">
                    <label class="small text-muted">Fecha de corte</label>
                    <input type="date" id="modal-fecha-corte" class="form-control" style="background:#0a0a0a; border:1px solid #262626; color:white; border-radius:8px;">
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-sm" style="background:#262626; color:white; border-radius:8px;" data-dismiss="modal">Cancelar</button>
                <button type="button" id="btn-confirmar-corte" class="btn-red-glow">Ejecutar Corte</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Confirmar corte TV --}}
<div class="modal fade" id="modal-confirmar-corte-tv" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom:1px solid #ef4444;">
                <h5 class="modal-title text-white font-weight-bold"><i class="fas fa-ban" style="color:#ef4444;"></i> Confirmar Corte TV</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Se ejecutará el corte de TV para <strong id="modal-corte-tv-count" style="color:#ef4444; font-size:1.2rem;">0</strong> contrato(s).</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-sm" style="background:#262626; color:white; border-radius:8px;" data-dismiss="modal">Cancelar</button>
                <button type="button" id="btn-confirmar-corte-tv" class="btn-red-glow">Ejecutar Corte TV</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Habilitar cortados --}}
<div class="modal fade" id="modal-habilitar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom:1px solid #10b981;">
                <h5 class="modal-title font-weight-bold text-white"><i class="fas fa-power-off" style="color:#10b981;"></i> Habilitar Cortados</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>¿Desea habilitar todos los contratos actualmente en estado cortado?</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-sm" style="background:#262626; color:white; border-radius:8px;" data-dismiss="modal">Cancelar</button>
                <button type="button" id="btn-confirmar-habilitar" class="btn-green-glow">Confirmar</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Detalle historial --}}
<div class="modal fade" id="modal-log-detail" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title font-weight-bold text-white">Detalle de ejecución</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="table-card">
                    <table class="table">
                        <thead>
                            <tr><th>Contrato</th><th>Cliente</th><th>IP</th><th>Tipo</th><th>Resultado</th><th>Método</th><th>Descripción</th></tr>
                        </thead>
                        <tbody id="log-detail-body">
                            <tr><td colspan="7" class="text-center py-4 text-muted">Cargando…</td></tr>
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
    history:          '{{ route("grupos-corte.corte-history",         ["id" => $grupo->id]) }}',
    historyDetail:    '{{ route("grupos-corte.corte-history-detail",  ["logId" => "LOGID_PH"]) }}',
    mkSync:           '{{ route("grupos-corte.mk-sync") }}',
    solucionarLote:   '{{ route("grupos-corte.solucionar-discrepancia-lote") }}',
    ejecutarInternet: '{{ route("grupos-corte.ejecutar-corte-internet") }}',
    ejecutarStream:   '{{ route("grupos-corte.ejecutar-corte-internet-stream") }}',
    ejecutarTv:       '{{ route("grupos-corte.ejecutar-corte-tv") }}',
    habilitarCortados:'{{ route("grupos-corte.habilitar-cortados-internet") }}',
};

var globalContracts = [];
var currentFilter = 'todos';

function getSelectedFecha() { return document.getElementById('fecha-ref').value; }
function fmt(n) { return (n === null || n === undefined) ? '—' : new Intl.NumberFormat('es-CO').format(n); }

function getStatusBadge(estado) {
    if(!estado) return '<span class="badge-status suspendidos">Desconocido</span>';
    estado = estado.toLowerCase();
    if(estado === 'cortado' || estado === 'suspendido' || estado === 'bloqueado') return '<span class="badge-status suspendidos">SUSPENDIDOS</span>';
    if(estado === 'pagado' || estado === 'al dia' || estado === 'aldia' || estado === 'activo') return '<span class="badge-status aldia">AL DÍA</span>';
    if(estado === 'promesa') return '<span class="badge-status promesa">PROMESA</span>';
    if(estado === 'abierta' || estado === 'mora' || estado === 'en mora') return '<span class="badge-status mora">EN MORA</span>';
    return '<span class="badge-status suspendidos">'+estado.toUpperCase()+'</span>';
}

function normalizeStateForFilter(estado) {
    if(!estado) return 'suspendidos';
    estado = estado.toLowerCase();
    if(estado === 'cortado' || estado === 'suspendido' || estado === 'bloqueado') return 'suspendidos';
    if(estado === 'pagado' || estado === 'al dia' || estado === 'aldia' || estado === 'activo') return 'aldia';
    if(estado === 'abierta' || estado === 'mora' || estado === 'en mora') return 'mora';
    return 'otros';
}

function renderContractsTable() {
    var query = $('#search-contratos').val().toLowerCase();
    var filtered = globalContracts.filter(function(c) {
        var matchFilter = false;
        var s = normalizeStateForFilter(c.estado_contrato);
        if(currentFilter === 'todos') matchFilter = true;
        else if(currentFilter === 'aldia' && s === 'aldia') matchFilter = true;
        else if(currentFilter === 'mora' && s === 'mora') matchFilter = true;
        else if(currentFilter === 'suspendidos' && s === 'suspendidos') matchFilter = true;

        var matchSearch = true;
        if(query) {
            var str = (c.cliente_nombre + ' ' + c.contrato_nro + ' ' + c.ip + ' ' + c.cliente_nit).toLowerCase();
            matchSearch = str.indexOf(query) !== -1;
        }
        return matchFilter && matchSearch;
    });

    var rows = '';
    if(filtered.length === 0) {
        rows = '<tr><td colspan="7" class="text-center py-5 text-muted">No hay contratos para mostrar</td></tr>';
    } else {
        filtered.forEach(function(c) {
            rows += '<tr>' +
                '<td><span class="client-name">'+(c.cliente_nombre||'—')+'</span><span class="client-doc">'+(c.cliente_nit||'')+'</span></td>' +
                '<td>'+(c.contrato_nro||'—')+'</td>' +
                '<td>'+getStatusBadge(c.estado_contrato)+'</td>' +
                '<td><span class="ip-address">'+(c.ip||'0.0.0.0')+'</span><span class="pppoe-user">PPPoE: '+(c.pppoe_user||'N/A')+'</span><div class="olt-info">OLT: '+(c.olt_name||'N/A')+'</div></td>' +
                '<td><span style="font-size:0.8rem;color:#a3a3a3;">'+(c.mikrotik_nombre||'—')+'</span></td>' +
                '<td>'+(c.factura_codigo||'—')+'</td>' +
                '<td style="color:#a3a3a3; font-size:0.8rem;">'+(c.fecha_vencimiento||'—')+'</td>' +
            '</tr>';
        });
    }
    $('#tbody-internet').html(rows);
}

function loadAllContracts() {
    $('#tbody-internet').html('<tr><td colspan="7" class="text-center py-5 text-muted">Cargando contratos...</td></tr>');
    $.getJSON(URLS.allContracts)
        .done(function(res) {
            globalContracts = res.data || res || [];
            
            // Count for filters
            var countTotal = globalContracts.length;
            var countAlDia = 0;
            var countMora = 0;
            var countSuspendidos = 0;

            globalContracts.forEach(function(c) {
                var s = normalizeStateForFilter(c.estado_contrato);
                if(s === 'aldia') countAlDia++;
                else if(s === 'mora') countMora++;
                else if(s === 'suspendidos') countSuspendidos++;
            });

            $('#count-todos').text(countTotal);
            $('#count-aldia').text(countAlDia);
            $('#count-mora').text(countMora);
            $('#count-suspendidos').text(countSuspendidos);

            // Set specific btn counts
            $('#btn-count-cortados').text(countSuspendidos);

            renderContractsTable();
        });
}

function loadSummary() {
    $.getJSON(URLS.summary)
        .done(function(d) {
            $('#kpi-total').text(fmt(d.total_contratos));
            $('#kpi-activos').text(fmt(d.al_dia)); // aprox
            $('#kpi-pendientes').text(fmt(d.pendientes_corte));
            $('#kpi-cortados').text(fmt(d.ya_cortados));
            
            $('#kpi-pendientes-tv').text(fmt(d.pendientes_corte_tv || 0));
            $('#kpi-cortados-tv').text(fmt(d.ya_cortados_tv || 0));
            $('#kpi-olt').text(fmt(d.total_contratos));
            $('#kpi-mk').text(fmt(d.total_contratos));
            
            // For buttons
            $('#btn-count-pendientes').text(d.pendientes_corte);
            if(d.pendientes_corte > 0) {
                $('#btn-ejecutar-corte').show();
            } else {
                $('#btn-ejecutar-corte').hide();
            }
        });
}

function loadTv() {
    var fecha = getSelectedFecha();
    $('#tbody-tv').html('<tr><td colspan="7" class="text-center py-5 text-muted">Cargando...</td></tr>');
    $.getJSON(URLS.pendingTv + '?grupo_id=' + GRUPO_ID + '&fecha=' + fecha)
        .done(function(res) {
            var rows = '';
            var list = res.data || [];
            $('#modal-corte-tv-count').text(list.length);
            if (!list.length) {
                rows = '<tr><td colspan="7" class="text-center py-5 text-muted">Sin pendientes de corte TV</td></tr>';
            } else {
                list.forEach(function(c, i) {
                    rows += '<tr>' +
                        '<td><span class="client-name">'+(c.cliente_nombre||'—')+'</span></td>' +
                        '<td>'+(c.contrato_nro||'—')+'</td>' +
                        '<td><span class="ip-address">'+(c.serial_onu||'—')+'</span></td>' +
                        '<td>'+(c.factura_codigo||'—')+'</td>' +
                        '<td>$'+fmt(c.factura_total)+'</td>' +
                        '<td style="color:#a3a3a3; font-size:0.8rem;">'+(c.fecha_vencimiento||'—')+'</td>' +
                        '<td>'+getStatusBadge(c.estado_contrato)+'</td>' +
                    '</tr>';
                });
            }
            $('#tbody-tv').html(rows);
        });
}

function loadHistorial() {
    $('#tbody-historial').html('<tr><td colspan="10" class="text-center py-5 text-muted">Cargando...</td></tr>');
    $.getJSON(URLS.history)
        .done(function(data) {
            var rows = '';
            if (!data.length) {
                rows = '<tr><td colspan="10" class="text-center py-5 text-muted">Sin historial</td></tr>';
            } else {
                data.forEach(function(h) {
                    var tipoBadge = h.tipo === 'internet'
                        ? '<span style="color:var(--color-blue); font-weight:600; font-size:0.8rem;">INTERNET</span>'
                        : '<span style="color:var(--color-purple); font-weight:600; font-size:0.8rem;">TV</span>';
                    var duracion = h.duracion_ms ? (h.duracion_ms / 1000).toFixed(1) + 's' : '—';
                    rows += '<tr>' +
                        '<td style="color:#a3a3a3;">#' + h.id + '</td>' +
                        '<td>' + tipoBadge + '</td>' +
                        '<td>' + (h.total_procesados || 0) + '</td>' +
                        '<td style="color:var(--color-red); font-weight:bold;">' + (h.total_cortados || 0) + '</td>' +
                        '<td>' + (h.total_omitidos || 0) + '</td>' +
                        '<td style="color:var(--color-orange);">' + (h.total_errores || 0) + '</td>' +
                        '<td style="color:#a3a3a3;">' + duracion + '</td>' +
                        '<td style="color:#a3a3a3; font-size:0.75rem;">' + (h.ejecutado_por_nombre || 'CRON') + '</td>' +
                        '<td style="color:#525252; font-size:0.75rem;">' + (h.created_at || '—') + '</td>' +
                        '<td><button class="btn btn-sm btn-ver-detalle" data-id="' + h.id + '" style="background:#262626;color:white;border-radius:6px;"><i class="fas fa-eye"></i></button></td>' +
                    '</tr>';
                });
            }
            $('#tbody-historial').html(rows);
        });
}

/* Event Listeners */
$('.filter-pill').on('click', function() {
    $('.filter-pill').removeClass('active');
    $(this).addClass('active');
    currentFilter = $(this).data('filter');
    renderContractsTable();
});

$('#search-contratos').on('keyup', function() {
    renderContractsTable();
});

$('#btn-apply-date').on('click', function() {
    loadAllContracts(); loadTv(); loadSummary();
});

$('#btn-refresh').on('click', function() {
    loadAllContracts(); loadTv(); loadSummary(); loadHistorial();
});

$('#btn-refresh-historial').on('click', loadHistorial);

$('a[href="#tab-historial"]').on('shown.bs.tab', function() {
    loadHistorial();
});

/* MK Sync */
$('#btn-analizar-mk').on('click', function() {
    var mkId = $('#select-mikrotik').val();
    if (!mkId) { alert('Seleccione un MikroTik.'); return; }
    var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    $('#mk-sync-empty').addClass('d-none');
    $('#mk-sync-result').addClass('d-none');
    $.ajax({
        url: URLS.mkSync,
        method: 'POST',
        data: { _token: csrfToken, mikrotik_id: mkId, grupo_id: GRUPO_ID },
    }).done(function(d) {
        if (!d.disponible) {
            $('#mk-sync-empty').removeClass('d-none').html('<div class="text-danger">No disponible</div>');
            return;
        }
        var faltantes = d.inconsistencias && d.inconsistencias.cortados_sin_morosos ? d.inconsistencias.cortados_sin_morosos : [];
        var extra     = d.inconsistencias && d.inconsistencias.morosos_sin_corte    ? d.inconsistencias.morosos_sin_corte    : [];

        $('#mk-ok-count').text(d.resumen ? (d.resumen.en_morosos_ok || 0) : 0);
        $('#mk-faltantes-count').text(faltantes.length);
        $('#mk-extra-count').text(extra.length);
        $('#badge-faltantes').text(faltantes.length);
        $('#badge-extra').text(extra.length);

        var rowsF = '';
        if (!faltantes.length) {
            rowsF = '<tr><td colspan="5" class="text-center py-4 text-muted">Sin discrepancias</td></tr>';
        } else {
            faltantes.forEach(function(c) {
                rowsF += '<tr><td>'+(c.nro||c.id)+'</td><td>'+(c.cliente_nombre||'—')+'</td><td><span class="ip-address">'+(c.ip||'—')+'</span></td><td>'+(c.factura_codigo||'—')+'</td><td>$'+fmt(c.factura_total)+'</td></tr>';
            });
        }
        $('#tbody-mk-faltantes').html(rowsF);

        var rowsE = '';
        if (!extra.length) {
            rowsE = '<tr><td colspan="3" class="text-center py-4 text-muted">Sin extras</td></tr>';
        } else {
            extra.forEach(function(e) {
                rowsE += '<tr><td><span class="ip-address">'+(e.address||'—')+'</span></td><td><span style="color:var(--color-orange);">'+(e.list||'—')+'</span></td><td style="color:#a3a3a3;">'+(e.comment||'—')+'</td></tr>';
            });
        }
        $('#tbody-mk-extra').html(rowsE);

        $('#mk-sync-result').removeClass('d-none');
        $('#btn-solucionar-lote').prop('disabled', faltantes.length === 0);
    }).fail(function() {
        $('#mk-sync-empty').removeClass('d-none').html('<div class="text-danger">Error al analizar MikroTik.</div>');
    }).always(function() {
        $btn.prop('disabled', false).html('<i class="fas fa-search"></i> Analizar');
    });
});

$('#btn-solucionar-lote').on('click', function() {
    var mkId = $('#select-mikrotik').val();
    if (!mkId || !confirm('¿Ejecutar?')) return;
    var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
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
        $btn.prop('disabled', false).html('<i class="fas fa-magic"></i> Solucionar Lote');
    });
});

/* Executes */
$('#btn-ejecutar-corte').on('click', function() {
    $('#modal-fecha-corte').val(getSelectedFecha());
    $('#modal-corte-count').text($('#btn-count-pendientes').text());
    $('#modal-confirmar-corte').modal('show');
});
$('#btn-confirmar-corte').on('click', function() {
    $('#modal-confirmar-corte').modal('hide');
    var fecha = $('#modal-fecha-corte').val();
    
    var es = new EventSource(URLS.ejecutarStream + '?grupo_id=' + GRUPO_ID + '&fecha=' + fecha);
    es.onmessage = function(e) {
        var d = JSON.parse(e.data);
        if (d.done) {
            es.close();
            alert('Corte completado. Cortados: ' + d.cortados + ' | Errores: ' + d.errores);
            loadAllContracts(); loadSummary();
            return;
        }
    };
    es.onerror = function() {
        es.close();
        alert('Error en el stream.');
    };
});

$('#btn-ejecutar-corte-tv').on('click', function() {
    $('#modal-confirmar-corte-tv').modal('show');
});
$('#btn-confirmar-corte-tv').on('click', function() {
    $('#modal-confirmar-corte-tv').modal('hide');
    $.ajax({
        url: URLS.ejecutarTv, method: 'POST', data: { _token: csrfToken, grupo_id: GRUPO_ID, fecha: getSelectedFecha() },
    }).done(function(r) {
        alert('Completado. Cortados: ' + r.cortados);
        loadTv(); loadSummary();
    });
});

$('#btn-habilitar-cortados').on('click', function() {
    $('#modal-habilitar').modal('show');
});
$('#btn-confirmar-habilitar').on('click', function() {
    $('#modal-habilitar').modal('hide');
    $.ajax({
        url: URLS.habilitarCortados, method: 'POST', data: { _token: csrfToken, grupo_id: GRUPO_ID },
    }).done(function(r) {
        alert('Habilitados: ' + r.habilitados);
        loadAllContracts(); loadSummary();
    });
});

$(document).on('click', '.btn-ver-detalle', function() {
    var logId = $(this).data('id');
    $('#log-detail-body').html('<tr><td colspan="7" class="text-center py-4 text-muted">Cargando...</td></tr>');
    $('#modal-log-detail').modal('show');
    $.getJSON(URLS.historyDetail.replace('LOGID_PH', logId)).done(function(data) {
        var rows = '';
        var list = data.detalle || data || [];
        if (!list.length) {
            rows = '<tr><td colspan="7" class="text-center py-4 text-muted">Sin detalle</td></tr>';
        } else {
            list.forEach(function(r) {
                var res = r.resultado === 'cortado' ? '<span style="color:var(--color-green);">cortado</span>' : (r.resultado === 'error' ? '<span style="color:var(--color-red);">error</span>' : '<span>'+r.resultado+'</span>');
                rows += '<tr>' +
                    '<td style="color:#a3a3a3;">'+(r.contrato_nro||r.contrato_id||'—')+'</td>' +
                    '<td>'+(r.cliente_nombre||'—')+'</td>' +
                    '<td><span class="ip-address">'+(r.ip||'—')+'</span></td>' +
                    '<td>'+(r.tipo||'—')+'</td>' +
                    '<td>'+res+'</td>' +
                    '<td style="color:#a3a3a3; font-size:0.8rem;">'+(r.metodo||'—')+'</td>' +
                    '<td style="color:#a3a3a3; font-size:0.8rem;">'+(r.descripcion || r.error_detalle || '—')+'</td>' +
                '</tr>';
            });
        }
        $('#log-detail-body').html(rows);
    });
});

$(document).ready(function() {
    loadSummary();
    loadAllContracts();
    loadTv();
});
</script>
@endsection
