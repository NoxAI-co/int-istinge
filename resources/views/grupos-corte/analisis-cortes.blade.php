@extends('layouts.app')

@section('boton')
<a href="{{ route('grupos-corte.index') }}" class="btn btn-outline-danger btn-sm"><i class="fas fa-backward"></i> Regresar</a>
<a href="{{ route('grupos-corte.show', $grupo->id) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-eye"></i> Ver Grupo</a>
<a href="{{ route('grupos-corte.analisis-ciclo', $grupo->id) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-chart-bar"></i> Análisis Ciclos</a>
<button id="btn-refresh" class="btn btn-info btn-sm text-white"><i class="fas fa-sync-alt"></i> Actualizar</button>
@endsection

@section('styles')
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root {
    --bg-dark: #07090F;
    --card-bg: rgba(16, 22, 36, 0.7);
    --border-color: rgba(0, 240, 255, 0.15);
    --text-primary: #E2E8F0;
    --text-muted: #64748B;
    
    --neon-cyan: #00F0FF;
    --neon-purple: #8A2BE2;
    --neon-green: #00FF66;
    --neon-red: #FF003C;
    --neon-yellow: #FFEA00;
    --neon-orange: #FF6600;

    --font-family: 'Outfit', -apple-system, sans-serif;
    --font-mono: 'JetBrains Mono', monospace;
}

body {
    background-color: var(--bg-dark) !important;
    background-image: 
        radial-gradient(circle at 15% 50%, rgba(0, 240, 255, 0.03), transparent 25%),
        radial-gradient(circle at 85% 30%, rgba(138, 43, 226, 0.03), transparent 25%);
    background-attachment: fixed;
    color: var(--text-primary) !important;
}

/* Overall Container Override */
.analisis-cortes-container {
    font-family: var(--font-family);
    color: var(--text-primary);
}

/* Page Header */
.page-header {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    box-shadow: 0 0 20px rgba(0, 240, 255, 0.05);
    backdrop-filter: blur(12px);
    border-radius: 0.75rem;
    padding: 1.5rem 2rem;
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-left: 4px solid var(--neon-cyan);
}
.page-title {
    color: var(--text-primary);
    font-size: 1.75rem;
    font-weight: 700;
    margin: 0;
    text-shadow: 0 0 10px rgba(0, 240, 255, 0.3);
}
.page-subtitle {
    color: var(--neon-cyan);
    font-size: 0.9rem;
    letter-spacing: 0.5px;
    margin-top: 0.2rem;
}

/* Filters Card */
.filters-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 0.75rem;
    padding: 1rem 1.5rem;
    margin-bottom: 2rem;
    display: flex;
    align-items: flex-end;
    gap: 1rem;
    backdrop-filter: blur(12px);
}
.filters-card label {
    color: var(--text-muted);
}
.filters-card input[type="date"] {
    background-color: rgba(0,0,0,0.5);
    border: 1px solid var(--border-color);
    color: var(--neon-cyan);
    border-radius: 0.25rem;
    font-family: var(--font-mono);
}
.filters-card input[type="date"]::-webkit-calendar-picker-indicator {
    filter: invert(1) sepia(1) saturate(5) hue-rotate(175deg);
}

/* KPI Cards */
.kpi-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 0.75rem;
    padding: 1.5rem;
    height: 100%;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
    backdrop-filter: blur(12px);
}
.kpi-card:hover {
    transform: translateY(-5px);
    border-color: var(--neon-cyan);
    box-shadow: 0 8px 25px rgba(0, 240, 255, 0.15);
}
.kpi-card::after {
    content: '';
    position: absolute;
    top: 0; left: 0; width: 100%; height: 2px;
}
.kpi-card.total::after { background: var(--text-primary); box-shadow: 0 0 10px var(--text-primary); }
.kpi-card.paid::after { background: var(--neon-green); box-shadow: 0 0 10px var(--neon-green); }
.kpi-card.unpaid::after { background: var(--neon-red); box-shadow: 0 0 10px var(--neon-red); }
.kpi-card.cut::after { background: var(--neon-orange); box-shadow: 0 0 10px var(--neon-orange); }
.kpi-card.pending::after { background: var(--neon-yellow); box-shadow: 0 0 10px var(--neon-yellow); }
.kpi-card.promise::after { background: var(--neon-cyan); box-shadow: 0 0 10px var(--neon-cyan); }

.kpi-icon {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    display: block;
}
.kpi-card.total .kpi-icon { color: var(--text-primary); }
.kpi-card.paid .kpi-icon { color: var(--neon-green); text-shadow: 0 0 10px var(--neon-green); }
.kpi-card.unpaid .kpi-icon { color: var(--neon-red); text-shadow: 0 0 10px var(--neon-red); }
.kpi-card.cut .kpi-icon { color: var(--neon-orange); text-shadow: 0 0 10px var(--neon-orange); }
.kpi-card.pending .kpi-icon { color: var(--neon-yellow); text-shadow: 0 0 10px var(--neon-yellow); }
.kpi-card.promise .kpi-icon { color: var(--neon-cyan); text-shadow: 0 0 10px var(--neon-cyan); }

.kpi-number {
    font-family: var(--font-mono);
    font-size: 2.2rem;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 0.2rem;
}
.kpi-label {
    font-size: 0.75rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
}

/* Tabs */
.nav-tabs {
    border-bottom: 1px solid var(--border-color);
}
.nav-tabs .nav-link {
    background: transparent;
    border: none;
    color: var(--text-muted);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 1rem 1.5rem;
    border-bottom: 3px solid transparent;
    transition: all 0.3s ease;
    border-radius: 0;
}
.nav-tabs .nav-link:hover {
    color: var(--text-primary);
    border-bottom: 3px solid rgba(0, 240, 255, 0.5);
    background: rgba(0,240,255,0.02);
}
.nav-tabs .nav-link.active {
    color: var(--neon-cyan);
    border-bottom: 3px solid var(--neon-cyan);
    text-shadow: 0 0 10px rgba(0, 240, 255, 0.5);
    background: rgba(0, 240, 255, 0.05);
}

.tab-content-wrapper {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-top: none;
    border-radius: 0 0 0.75rem 0.75rem;
    padding: 2rem;
    backdrop-filter: blur(12px);
}

/* Tables */
.table-responsive {
    border-radius: 0.5rem;
    border: 1px solid rgba(255,255,255,0.05);
}
.table {
    color: var(--text-primary);
    margin-bottom: 0;
}
.table th {
    background-color: rgba(0,0,0,0.4);
    color: var(--text-muted);
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 1px;
    border-top: none !important;
    border-bottom: 1px solid var(--border-color) !important;
    padding: 1rem;
}
.table td {
    padding: 1rem;
    vertical-align: middle;
    border-top: 1px solid rgba(255,255,255,0.05) !important;
    font-size: 0.9rem;
}
.table tbody tr {
    transition: all 0.2s ease;
}
.table tbody tr:hover {
    background-color: rgba(0, 240, 255, 0.05);
}
code {
    font-family: var(--font-mono);
    color: var(--neon-cyan);
    background: rgba(0, 240, 255, 0.1);
    padding: 0.2rem 0.4rem;
    border-radius: 0.25rem;
    font-size: 0.85rem;
}

/* Badges */
.badge {
    padding: 0.4em 0.7em;
    border-radius: 0.25rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    font-size: 0.7rem;
}
.badge-estado-pagado   { background-color: rgba(0, 255, 102, 0.15); color: var(--neon-green); border: 1px solid var(--neon-green); }
.badge-estado-abierta  { background-color: rgba(255, 0, 60, 0.15); color: var(--neon-red); border: 1px solid var(--neon-red); }
.badge-estado-cortado  { background-color: rgba(255, 102, 0, 0.15); color: var(--neon-orange); border: 1px solid var(--neon-orange); }
.badge-estado-promesa  { background-color: rgba(0, 240, 255, 0.15); color: var(--neon-cyan); border: 1px solid var(--neon-cyan); }
.badge-estado-bloqueado{ background-color: rgba(255, 255, 255, 0.1); color: var(--text-primary); border: 1px solid var(--text-muted); }

/* Buttons Overrides */
.analisis-cortes-container .btn {
    border-radius: 0.25rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    transition: all 0.3s ease;
    font-size: 0.75rem;
    padding: 0.5rem 1rem;
}
.analisis-cortes-container .btn-primary { background: rgba(0, 240, 255, 0.1); color: var(--neon-cyan); border: 1px solid var(--neon-cyan); box-shadow: 0 0 10px rgba(0, 240, 255, 0.2); }
.analisis-cortes-container .btn-primary:hover { background: var(--neon-cyan); color: #000; box-shadow: 0 0 20px var(--neon-cyan); }

.analisis-cortes-container .btn-danger { background: rgba(255, 0, 60, 0.1); color: var(--neon-red); border: 1px solid var(--neon-red); box-shadow: 0 0 10px rgba(255, 0, 60, 0.2); }
.analisis-cortes-container .btn-danger:hover { background: var(--neon-red); color: #000; box-shadow: 0 0 20px var(--neon-red); }

.analisis-cortes-container .btn-success { background: rgba(0, 255, 102, 0.1); color: var(--neon-green); border: 1px solid var(--neon-green); box-shadow: 0 0 10px rgba(0, 255, 102, 0.2); }
.analisis-cortes-container .btn-success:hover { background: var(--neon-green); color: #000; box-shadow: 0 0 20px var(--neon-green); }

.analisis-cortes-container .btn-warning { background: rgba(255, 234, 0, 0.1); color: var(--neon-yellow); border: 1px solid var(--neon-yellow); box-shadow: 0 0 10px rgba(255, 234, 0, 0.2); }
.analisis-cortes-container .btn-warning:hover { background: var(--neon-yellow); color: #000; box-shadow: 0 0 20px var(--neon-yellow); }

.analisis-cortes-container .btn-info { background: rgba(138, 43, 226, 0.1); color: var(--neon-purple); border: 1px solid var(--neon-purple); box-shadow: 0 0 10px rgba(138, 43, 226, 0.2); }
.analisis-cortes-container .btn-info:hover { background: var(--neon-purple); color: #fff; box-shadow: 0 0 20px var(--neon-purple); }

.analisis-cortes-container .btn-outline-secondary { background: transparent; color: var(--text-muted); border: 1px solid var(--text-muted); }
.analisis-cortes-container .btn-outline-secondary:hover { background: rgba(255,255,255,0.1); color: var(--text-primary); border-color: var(--text-primary); }

/* Progress bar */
.progress { background-color: rgba(255, 0, 60, 0.1); height: 0.8rem; border-radius: 0.25rem; border: 1px solid rgba(255, 0, 60, 0.3); }
.progress-bar { background-color: var(--neon-red); box-shadow: 0 0 10px var(--neon-red); }

/* Modals */
.modal-content { background: var(--bg-dark); border: 1px solid var(--neon-cyan); border-radius: 0.5rem; box-shadow: 0 0 30px rgba(0, 240, 255, 0.15); color: var(--text-primary); }
.modal-header { border-bottom: 1px solid rgba(255,255,255,0.1); }
.modal-footer { border-top: 1px solid rgba(255,255,255,0.1); }
.close { color: var(--text-primary); text-shadow: none; opacity: 1; }
.close:hover { color: var(--neon-red); }
.modal-header.bg-danger { background: rgba(255, 0, 60, 0.1) !important; border-bottom: 1px solid var(--neon-red); color: var(--neon-red) !important; }
.modal-header.bg-warning { background: rgba(255, 234, 0, 0.1) !important; border-bottom: 1px solid var(--neon-yellow); color: var(--neon-yellow) !important; }
.modal-header.bg-success { background: rgba(0, 255, 102, 0.1) !important; border-bottom: 1px solid var(--neon-green); color: var(--neon-green) !important; }
.alert-warning { background: rgba(255, 234, 0, 0.05); border: 1px solid var(--neon-yellow); color: var(--neon-yellow); }

/* Select fields */
.analisis-cortes-container select.form-control {
    background-color: rgba(0,0,0,0.5);
    border: 1px solid var(--border-color);
    color: var(--neon-cyan);
    border-radius: 0.25rem;
    font-family: var(--font-mono);
}
.analisis-cortes-container select.form-control:focus {
    background-color: rgba(0,0,0,0.8);
    border-color: var(--neon-cyan);
    color: var(--neon-cyan);
    box-shadow: 0 0 10px rgba(0, 240, 255, 0.2);
}
.analisis-cortes-container select.form-control option {
    background-color: var(--bg-dark);
    color: var(--text-primary);
}

/* MK Sync specific */
.mk-sync-container {
    background: rgba(0,0,0,0.3);
    border: 1px solid rgba(255,255,255,0.05);
    padding: 1rem;
    border-radius: 0.5rem;
}
#mk-subtabs { border-bottom: 1px solid rgba(255,255,255,0.05); margin-bottom: 1rem; }

</style>
@endsection

@section('content')
<div class="container-fluid analisis-cortes-container">
    
    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-satellite-dish mr-2" style="color: var(--neon-red);"></i> Análisis de Cortes / SYS-CORE
            </h1>
            <div class="page-subtitle">
                OBJETIVO: <strong>{{ strtoupper($grupo->nombre) }}</strong> | CORTE: {{ $grupo->fecha_corte }} | SUSPENSIÓN: {{ $grupo->fecha_suspension }}
            </div>
        </div>
        <div>
            <button id="btn-clear-cache" class="btn btn-outline-secondary">
                <i class="fas fa-terminal mr-1"></i> PURGAR CACHÉ
            </button>
        </div>
    </div>

    {{-- Filters --}}
    <div class="filters-card">
        <div class="form-group mb-0">
            <label class="small font-weight-bold mb-1 text-uppercase">TIMESTAMP DE REFERENCIA</label>
            <input type="date" id="fecha-ref" class="form-control form-control-sm px-3 py-2" value="{{ date('Y-m-d') }}">
        </div>
        <div>
            <button id="btn-apply-date" class="btn btn-primary">
                <i class="fas fa-microchip mr-1"></i> APLICAR
            </button>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="row mb-4" id="kpi-row">
        <div class="col-6 col-lg-2 col-md-4 mb-3">
            <div class="kpi-card total">
                <i class="fas fa-network-wired kpi-icon"></i>
                <div class="kpi-number text-white" id="kpi-total">0</div>
                <div class="kpi-label">Total Contratos</div>
            </div>
        </div>
        <div class="col-6 col-lg-2 col-md-4 mb-3">
            <div class="kpi-card paid">
                <i class="fas fa-shield-check kpi-icon"></i>
                <div class="kpi-number" style="color: var(--neon-green);" id="kpi-pagados">0</div>
                <div class="kpi-label">Al día (SECURE)</div>
            </div>
        </div>
        <div class="col-6 col-lg-2 col-md-4 mb-3">
            <div class="kpi-card unpaid">
                <i class="fas fa-exclamation-triangle kpi-icon"></i>
                <div class="kpi-number" style="color: var(--neon-red);" id="kpi-mora">0</div>
                <div class="kpi-label">En mora (WARNING)</div>
            </div>
        </div>
        <div class="col-6 col-lg-2 col-md-4 mb-3">
            <div class="kpi-card cut">
                <i class="fas fa-power-off kpi-icon"></i>
                <div class="kpi-number" style="color: var(--neon-orange);" id="kpi-cortados">0</div>
                <div class="kpi-label">Cortados (OFFLINE)</div>
            </div>
        </div>
        <div class="col-6 col-lg-2 col-md-4 mb-3">
            <div class="kpi-card pending">
                <i class="fas fa-hourglass-half kpi-icon"></i>
                <div class="kpi-number" style="color: var(--neon-yellow);" id="kpi-pendientes">0</div>
                <div class="kpi-label">Pénd. Corte (TARGET)</div>
            </div>
        </div>
        <div class="col-6 col-lg-2 col-md-4 mb-3">
            <div class="kpi-card promise">
                <i class="fas fa-handshake kpi-icon"></i>
                <div class="kpi-number" style="color: var(--neon-cyan);" id="kpi-promesas">0</div>
                <div class="kpi-label">Con promesa (HOLD)</div>
            </div>
        </div>
    </div>

    {{-- Progress bar (corte en progreso) --}}
    <div id="progress-bar-wrap" class="mb-4" style="display:none; background: rgba(0,0,0,0.5); padding: 1rem; border-radius: 0.5rem; border: 1px solid var(--neon-red);">
        <div class="d-flex justify-content-between small mb-2 font-weight-bold">
            <span id="progress-label" style="color: var(--neon-red); text-transform: uppercase; letter-spacing: 1px;">EJECUTANDO OPERACIÓN DE CORTE...</span>
            <span id="progress-count" style="font-family: var(--font-mono); color: var(--neon-red);">0 / 0</span>
        </div>
        <div class="progress">
            <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated"
                 role="progressbar" style="width:0%"></div>
        </div>
    </div>

    {{-- Tabs --}}
    <ul class="nav nav-tabs" id="main-tabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="tab-internet-link" data-toggle="tab" href="#tab-internet">
                <i class="fas fa-globe mr-1"></i> Red Internet
                <span class="badge ml-2" style="background: rgba(255,0,60,0.2); color: var(--neon-red); border: 1px solid var(--neon-red);" id="badge-internet">0</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-tv-link" data-toggle="tab" href="#tab-tv">
                <i class="fas fa-tv mr-1"></i> Red TV
                <span class="badge ml-2" style="background: rgba(255,234,0,0.2); color: var(--neon-yellow); border: 1px solid var(--neon-yellow);" id="badge-tv">0</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-historial-link" data-toggle="tab" href="#tab-historial">
                <i class="fas fa-database mr-1"></i> Logs de Sist.
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-mk-link" data-toggle="tab" href="#tab-mk">
                <i class="fas fa-server mr-1"></i> Sync MikroTik
            </a>
        </li>
    </ul>

    <div class="tab-content-wrapper" id="main-tab-content">
        {{-- TAB INTERNET --}}
        <div class="tab-pane fade show active" id="tab-internet" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div class="d-flex align-items-center">
                    <div>
                        <h5 class="mb-0 font-weight-bold" style="color: var(--neon-red); text-transform: uppercase; letter-spacing: 1px;">TARGETS: INTERNET</h5>
                        <small style="color: var(--text-muted);"><span id="internet-count" style="font-family: var(--font-mono); color: var(--text-primary);">0</span> nodos identificados</small>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button id="btn-habilitar-cortados" class="btn btn-success">
                        <i class="fas fa-unlock mr-1"></i> ENABLE OFFLINE
                    </button>
                    <button id="btn-ejecutar-corte" class="btn btn-danger">
                        <i class="fas fa-bolt mr-1"></i> EXECUTE CUT (NET)
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table" id="tabla-internet">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Contrato</th>
                            <th>Cliente</th>
                            <th>IP Addr</th>
                            <th>Router MK</th>
                            <th>Factura</th>
                            <th>Valor</th>
                            <th>Vencimiento</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-internet">
                        <tr><td colspan="9" class="text-center py-4" style="color: var(--neon-cyan);"><i class="fas fa-circle-notch fa-spin mr-2"></i> ESCANEANDO RED...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- TAB TV --}}
        <div class="tab-pane fade" id="tab-tv" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div class="d-flex align-items-center">
                    <div>
                        <h5 class="mb-0 font-weight-bold" style="color: var(--neon-yellow); text-transform: uppercase; letter-spacing: 1px;">TARGETS: TELEVISIÓN</h5>
                        <small style="color: var(--text-muted);"><span id="tv-count" style="font-family: var(--font-mono); color: var(--text-primary);">0</span> nodos identificados</small>
                    </div>
                </div>
                <button id="btn-ejecutar-corte-tv" class="btn btn-warning">
                    <i class="fas fa-bolt mr-1"></i> EXECUTE CUT (TV)
                </button>
            </div>
            <div class="table-responsive">
                <table class="table" id="tabla-tv">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Contrato</th>
                            <th>Cliente</th>
                            <th>Serial ONU</th>
                            <th>Factura</th>
                            <th>Valor</th>
                            <th>Vencimiento</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-tv">
                        <tr><td colspan="8" class="text-center py-4" style="color: var(--neon-cyan);"><i class="fas fa-circle-notch fa-spin mr-2"></i> ESCANEANDO RED...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- TAB HISTORIAL --}}
        <div class="tab-pane fade" id="tab-historial" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="font-weight-bold mb-0" style="color: var(--neon-purple); text-transform: uppercase; letter-spacing: 1px;">LOGS DE EJECUCIÓN</h5>
                <button id="btn-refresh-historial" class="btn btn-outline-secondary">
                    <i class="fas fa-sync-alt mr-1"></i> REFRESH LOGS
                </button>
            </div>
            <div class="table-responsive">
                <table class="table" id="tabla-historial">
                    <thead>
                        <tr>
                            <th>PID</th>
                            <th>Tipo</th>
                            <th>Targets</th>
                            <th>Success</th>
                            <th>Ignored</th>
                            <th>Errors</th>
                            <th>Time</th>
                            <th>User</th>
                            <th>Timestamp</th>
                            <th>Trace</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-historial">
                        <tr><td colspan="10" class="text-center py-4" style="color: var(--neon-cyan);"><i class="fas fa-circle-notch fa-spin mr-2"></i> FETCHING LOGS...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- TAB MK SYNC --}}
        <div class="tab-pane fade" id="tab-mk" role="tabpanel">
            <div class="mk-sync-container mb-4 d-flex align-items-end gap-3 flex-wrap">
                <div style="min-width: 300px;">
                    <label class="small font-weight-bold text-muted text-uppercase mb-1">ROUTER MIKROTIK</label>
                    <select id="select-mikrotik" class="form-control">
                        <option value="">— SELECT ROUTER —</option>
                        @foreach($mikrotiks as $mk)
                        <option value="{{ $mk->id }}">{{ $mk->nombre }} ({{ $mk->ip }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <button id="btn-analizar-mk" class="btn btn-info">
                        <i class="fas fa-radar mr-1"></i> SCAN MK
                    </button>
                </div>
                <div class="ml-auto">
                    <button id="btn-solucionar-lote" class="btn btn-warning" disabled>
                        <i class="fas fa-wrench mr-1"></i> AUTO-FIX
                    </button>
                </div>
            </div>

            <div id="mk-sync-result" class="d-none">
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="kpi-card text-center" style="border-color: var(--neon-green);">
                            <div class="font-weight-bold mb-1" style="color: var(--neon-green); font-size: 2.5rem; font-family: var(--font-mono);" id="mk-ok-count">0</div>
                            <div class="small font-weight-bold text-uppercase" style="color: var(--text-primary); letter-spacing: 1px;">SYNC OK</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="kpi-card text-center" style="border-color: var(--neon-red);">
                            <div class="font-weight-bold mb-1" style="color: var(--neon-red); font-size: 2.5rem; font-family: var(--font-mono);" id="mk-faltantes-count">0</div>
                            <div class="small font-weight-bold text-uppercase" style="color: var(--text-primary); letter-spacing: 1px;">MISSING IN MK</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="kpi-card text-center" style="border-color: var(--neon-yellow);">
                            <div class="font-weight-bold mb-1" style="color: var(--neon-yellow); font-size: 2.5rem; font-family: var(--font-mono);" id="mk-extra-count">0</div>
                            <div class="small font-weight-bold text-uppercase" style="color: var(--text-primary); letter-spacing: 1px;">GHOSTS IN MK</div>
                        </div>
                    </div>
                </div>

                <ul class="nav nav-tabs" id="mk-subtabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#mk-tab-faltantes">
                            <i class="fas fa-exclamation-triangle" style="color: var(--neon-red);"></i> MISSING
                            <span class="badge ml-2" style="background: rgba(255,0,60,0.2); color: var(--neon-red); border: 1px solid var(--neon-red);" id="badge-faltantes">0</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#mk-tab-extra">
                            <i class="fas fa-ghost" style="color: var(--neon-yellow);"></i> GHOSTS
                            <span class="badge ml-2" style="background: rgba(255,234,0,0.2); color: var(--neon-yellow); border: 1px solid var(--neon-yellow);" id="badge-extra">0</span>
                        </a>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="mk-tab-faltantes">
                        <div class="table-responsive mt-3">
                            <table class="table mb-0">
                                <thead>
                                    <tr><th>Contrato</th><th>Cliente</th><th>IP Addr</th><th>Factura</th><th>Valor</th></tr>
                                </thead>
                                <tbody id="tbody-mk-faltantes">
                                    <tr><td colspan="5" class="text-center py-4 text-muted">AWAITING SCAN...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="mk-tab-extra">
                        <div class="table-responsive mt-3">
                            <table class="table mb-0">
                                <thead>
                                    <tr><th>IP Addr</th><th>Lista MK</th><th>Comentario</th></tr>
                                </thead>
                                <tbody id="tbody-mk-extra">
                                    <tr><td colspan="3" class="text-center py-4 text-muted">AWAITING SCAN...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div id="mk-sync-empty" class="text-center py-5" style="border: 1px dashed var(--border-color); border-radius: 0.5rem; background: rgba(0,0,0,0.2);">
                <i class="fas fa-radar fa-3x mb-3" style="color: var(--neon-cyan); opacity:0.5;"></i><br>
                <strong style="color: var(--text-muted); letter-spacing: 1px; font-family: var(--font-mono);">AWAITING TARGET SELECTION</strong>
            </div>
        </div>

    </div>
</div>

{{-- Modal: Confirmar corte internet --}}
<div class="modal fade" id="modal-confirmar-corte" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger">
                <h5 class="modal-title font-weight-bold" style="font-family: var(--font-mono); letter-spacing: 1px;"><i class="fas fa-exclamation-triangle mr-2"></i> CONFIRM EXECUTION</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Targets to disable: <span id="modal-corte-count" style="color: var(--neon-red); font-size: 1.5rem; font-family: var(--font-mono); font-weight: bold;">0</span></p>
                <div class="alert alert-warning" style="font-family: var(--font-mono); font-size: 0.85rem;">
                    [WARNING] IPs will be moved to 'morosos' list. Network access will be dropped.
                </div>
                <div class="form-group mt-3 mb-0">
                    <label class="small font-weight-bold text-uppercase text-muted">EXECUTION TIMESTAMP</label>
                    <input type="date" id="modal-fecha-corte" class="form-control" style="background: rgba(0,0,0,0.5); border: 1px solid var(--neon-red); color: var(--neon-red); border-radius: 0.25rem;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">ABORT</button>
                <button type="button" id="btn-confirmar-corte" class="btn btn-danger">
                    <i class="fas fa-bolt mr-1"></i> PROCEED
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Confirmar corte TV --}}
<div class="modal fade" id="modal-confirmar-corte-tv" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title font-weight-bold" style="font-family: var(--font-mono); letter-spacing: 1px;"><i class="fas fa-exclamation-triangle mr-2"></i> CONFIRM EXECUTION (TV)</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Targets to disable: <span id="modal-corte-tv-count" style="color: var(--neon-yellow); font-size: 1.5rem; font-family: var(--font-mono); font-weight: bold;">0</span></p>
                <div class="alert alert-warning" style="font-family: var(--font-mono); font-size: 0.85rem;">
                    [WARNING] SmartOLT ONUs will be disabled. Signal will be dropped.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">ABORT</button>
                <button type="button" id="btn-confirmar-corte-tv" class="btn btn-warning">
                    <i class="fas fa-bolt mr-1"></i> PROCEED
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Habilitar cortados --}}
<div class="modal fade" id="modal-habilitar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success">
                <h5 class="modal-title font-weight-bold" style="font-family: var(--font-mono); letter-spacing: 1px;"><i class="fas fa-unlock mr-2"></i> ENABLE NETWORK</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Do you want to enable all currently offline targets in this group?</p>
                <p class="text-muted small" style="font-family: var(--font-mono);">Targets will be removed from 'morosos' list.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">ABORT</button>
                <button type="button" id="btn-confirmar-habilitar" class="btn btn-success">
                    <i class="fas fa-check mr-1"></i> EXECUTE
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Detalle historial --}}
<div class="modal fade" id="modal-log-detail" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title font-weight-bold" style="color: var(--neon-purple); font-family: var(--font-mono); letter-spacing: 1px;"><i class="fas fa-terminal mr-2"></i> SYSTEM LOG TRACE</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Target</th>
                                <th>Cliente</th>
                                <th>IP Addr</th>
                                <th>Protocol</th>
                                <th>Result</th>
                                <th>Method</th>
                                <th>Stack/Msg</th>
                            </tr>
                        </thead>
                        <tbody id="log-detail-body">
                            <tr><td colspan="7" class="text-center py-4" style="color: var(--neon-purple);"><i class="fas fa-circle-notch fa-spin mr-2"></i> DECRYPTING LOGS...</td></tr>
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
    return new Intl.NumberFormat('en-US').format(n);
}

function badgeEstado(estado) {
    var map = {
        'pagado':    'badge-estado-pagado',
        'abierta':   'badge-estado-abierta',
        'cortado':   'badge-estado-cortado',
        'promesa':   'badge-estado-promesa',
        'bloqueado': 'badge-estado-bloqueado',
    };
    var cls = map[estado] || 'badge-estado-bloqueado';
    return '<span class="badge ' + cls + '">' + (estado || 'UNKNOWN') + '</span>';
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
    $('#tbody-internet').html('<tr><td colspan="9" class="text-center py-4" style="color: var(--neon-cyan);"><i class="fas fa-circle-notch fa-spin mr-2"></i> ESCANEANDO RED...</td></tr>');
    $.getJSON(URLS.pendingInternet + '?grupo_id=' + GRUPO_ID + '&fecha=' + fecha)
        .done(function(res) {
            var rows = '';
            var list = res.data || [];
            $('#internet-count').text(list.length);
            $('#badge-internet').text(list.length);
            $('#modal-corte-count').text(list.length);
            if (!list.length) {
                rows = '<tr><td colspan="9" class="text-center py-4" style="color: var(--neon-green); font-family: var(--font-mono);"><i class="fas fa-check-square mr-2"></i> NO TARGETS FOUND. NETWORK SECURE.</td></tr>';
            } else {
                list.forEach(function(c, i) {
                    rows += '<tr>' +
                        '<td style="color: var(--text-muted); font-family: var(--font-mono);">[' + (i+1) + ']</td>' +
                        '<td style="color: var(--text-primary); font-family: var(--font-mono);">' + (c.contrato_nro || '—') + '</td>' +
                        '<td>' + (c.cliente_nombre || '—') + '</td>' +
                        '<td><code>' + (c.ip || '—') + '</code></td>' +
                        '<td style="color: var(--neon-cyan); font-family: var(--font-mono); font-size: 0.8rem;">' + (c.mikrotik_nombre || '—') + '</td>' +
                        '<td style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-muted);">' + (c.factura_codigo || '—') + '</td>' +
                        '<td style="color: var(--text-primary); font-family: var(--font-mono); font-weight: bold;">$' + fmt(c.factura_total) + '</td>' +
                        '<td style="color: var(--text-muted); font-size: 0.85rem;">' + (c.fecha_vencimiento || '—') + '</td>' +
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
    $('#tbody-tv').html('<tr><td colspan="8" class="text-center py-4" style="color: var(--neon-cyan);"><i class="fas fa-circle-notch fa-spin mr-2"></i> ESCANEANDO RED...</td></tr>');
    $.getJSON(URLS.pendingTv + '?grupo_id=' + GRUPO_ID + '&fecha=' + fecha)
        .done(function(res) {
            var rows = '';
            var list = res.data || [];
            $('#tv-count').text(list.length);
            $('#badge-tv').text(list.length);
            $('#modal-corte-tv-count').text(list.length);
            if (!list.length) {
                rows = '<tr><td colspan="8" class="text-center py-4" style="color: var(--neon-green); font-family: var(--font-mono);"><i class="fas fa-check-square mr-2"></i> NO TARGETS FOUND. NETWORK SECURE.</td></tr>';
            } else {
                list.forEach(function(c, i) {
                    rows += '<tr>' +
                        '<td style="color: var(--text-muted); font-family: var(--font-mono);">[' + (i+1) + ']</td>' +
                        '<td style="color: var(--text-primary); font-family: var(--font-mono);">' + (c.contrato_nro || '—') + '</td>' +
                        '<td>' + (c.cliente_nombre || '—') + '</td>' +
                        '<td><code>' + (c.serial_onu || '—') + '</code></td>' +
                        '<td style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-muted);">' + (c.factura_codigo || '—') + '</td>' +
                        '<td style="color: var(--text-primary); font-family: var(--font-mono); font-weight: bold;">$' + fmt(c.factura_total) + '</td>' +
                        '<td style="color: var(--text-muted); font-size: 0.85rem;">' + (c.fecha_vencimiento || '—') + '</td>' +
                        '<td>' + badgeEstado(c.estado_contrato) + '</td>' +
                    '</tr>';
                });
            }
            $('#tbody-tv').html(rows);
        });
}

/* ── Historial tab ─────────────────────────────────────── */
function loadHistorial() {
    $('#tbody-historial').html('<tr><td colspan="10" class="text-center py-4" style="color: var(--neon-cyan);"><i class="fas fa-circle-notch fa-spin mr-2"></i> FETCHING LOGS...</td></tr>');
    $.getJSON(URLS.history)
        .done(function(data) {
            var rows = '';
            if (!data.length) {
                rows = '<tr><td colspan="10" class="text-center py-4 text-muted" style="font-family: var(--font-mono);">NO SYSTEM LOGS FOUND</td></tr>';
            } else {
                data.forEach(function(h) {
                    var tipoBadge = h.tipo === 'internet'
                        ? '<span class="badge" style="background:rgba(0,240,255,0.15);color:var(--neon-cyan);border:1px solid var(--neon-cyan);">NET</span>'
                        : '<span class="badge" style="background:rgba(255,234,0,0.15);color:var(--neon-yellow);border:1px solid var(--neon-yellow);">TV</span>';
                    var duracion = h.duracion_ms ? (h.duracion_ms / 1000).toFixed(1) + 's' : '—';
                    rows += '<tr>' +
                        '<td style="font-family: var(--font-mono); color: var(--neon-purple);">[' + h.id + ']</td>' +
                        '<td>' + tipoBadge + '</td>' +
                        '<td style="font-family: var(--font-mono);">' + (h.total_procesados || 0) + '</td>' +
                        '<td style="color: var(--neon-red); font-family: var(--font-mono); font-weight: bold;">' + (h.total_cortados || 0) + '</td>' +
                        '<td style="font-family: var(--font-mono);">' + (h.total_omitidos || 0) + '</td>' +
                        '<td style="color: var(--neon-orange); font-family: var(--font-mono);">' + (h.total_errores || 0) + '</td>' +
                        '<td style="font-family: var(--font-mono);">' + duracion + '</td>' +
                        '<td style="font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-muted);">' + (h.ejecutado_por_nombre || 'SYS_CRON') + '</td>' +
                        '<td style="font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-muted);">' + (h.created_at || '—') + '</td>' +
                        '<td><button class="btn btn-primary btn-sm btn-ver-detalle" data-id="' + h.id + '"><i class="fas fa-terminal"></i></button></td>' +
                    '</tr>';
                });
            }
            $('#tbody-historial').html(rows);
        });
}

/* ── MK Sync ───────────────────────────────────────────── */
$('#btn-analizar-mk').on('click', function() {
    var mkId = $('#select-mikrotik').val();
    if (!mkId) { alert('ERROR: NO ROUTER SELECTED'); return; }
    var $btn = $(this).prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin mr-1"></i> SCANNING...');
    $('#mk-sync-empty').addClass('d-none');
    $('#mk-sync-result').addClass('d-none');
    $.ajax({
        url: URLS.mkSync,
        method: 'POST',
        data: { _token: csrfToken, mikrotik_id: mkId, grupo_id: GRUPO_ID },
    }).done(function(d) {
        if (!d.disponible) {
            $('#mk-sync-empty').removeClass('d-none').html('<div class="alert alert-danger" style="background:rgba(255,0,60,0.1); border:1px solid var(--neon-red); color:var(--neon-red);">' + (d.error || 'UNAVAILABLE') + '</div>');
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
            rowsF = '<tr><td colspan="5" class="text-center py-4" style="color: var(--neon-green); font-family: var(--font-mono);"><i class="fas fa-check-square mr-2"></i> ALL TARGETS PRESENT IN MK.</td></tr>';
        } else {
            faltantes.forEach(function(c) {
                rowsF += '<tr><td style="font-family: var(--font-mono);">'+(c.nro||c.id)+'</td><td>'+(c.cliente_nombre||'—')+'</td><td><code>'+(c.ip||'—')+'</code></td><td style="font-family: var(--font-mono); color: var(--text-muted); font-size: 0.85rem;">'+(c.factura_codigo||'—')+'</td><td style="font-family: var(--font-mono);">$'+fmt(c.factura_total)+'</td></tr>';
            });
        }
        $('#tbody-mk-faltantes').html(rowsF);

        // Extra
        var rowsE = '';
        if (!extra.length) {
            rowsE = '<tr><td colspan="3" class="text-center py-4" style="color: var(--neon-green); font-family: var(--font-mono);"><i class="fas fa-check-square mr-2"></i> NO GHOST TARGETS IN MK.</td></tr>';
        } else {
            extra.forEach(function(e) {
                rowsE += '<tr><td><code>'+(e.address||'—')+'</code></td><td><span class="badge" style="background: rgba(255,234,0,0.15); border: 1px solid var(--neon-yellow); color: var(--neon-yellow);">'+(e.list||'—')+'</span></td><td style="font-family: var(--font-mono); color: var(--text-muted); font-size: 0.85rem;">'+(e.comment||'—')+'</td></tr>';
            });
        }
        $('#tbody-mk-extra').html(rowsE);

        $('#mk-sync-result').removeClass('d-none');
        $('#btn-solucionar-lote').prop('disabled', faltantes.length === 0);
    }).fail(function() {
        $('#mk-sync-empty').removeClass('d-none').html('<div class="alert alert-danger" style="background:rgba(255,0,60,0.1); border:1px solid var(--neon-red); color:var(--neon-red);">SCAN FAILED. CHECK CONNECTION.</div>');
    }).always(function() {
        $btn.prop('disabled', false).html('<i class="fas fa-radar mr-1"></i> SCAN MK');
    });
});

$('#btn-solucionar-lote').on('click', function() {
    var mkId = $('#select-mikrotik').val();
    if (!mkId || !confirm('SYS-WARNING: Execute AUTO-FIX adding missing targets to MK?')) return;
    var $btn = $(this).prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin mr-1"></i> FIXING...');
    $.ajax({
        url: URLS.solucionarLote,
        method: 'POST',
        data: { _token: csrfToken, mikrotik_id: mkId, grupo_id: GRUPO_ID },
    }).done(function(r) {
        alert(r.message || 'SYS-MSG: FIX APPLIED');
        $('#btn-analizar-mk').trigger('click');
    }).fail(function(xhr) {
        alert('SYS-ERR: ' + (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'UNKNOWN ERROR'));
    }).always(function() {
        $btn.prop('disabled', false).html('<i class="fas fa-wrench mr-1"></i> AUTO-FIX');
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

    $('#progress-bar-wrap').fadeIn();
    $('#progress-bar').css('width', '0%');
    $('#progress-label').text('EXECUTING CUT ROUTINE...');
    $('#progress-count').text('0 / ?');

    var total = parseInt($('#internet-count').text()) || 0;
    var done = 0;

    var es = new EventSource(URLS.ejecutarStream + '?grupo_id=' + GRUPO_ID + '&fecha=' + fecha);
    es.onmessage = function(e) {
        var d = JSON.parse(e.data);
        if (d.done) {
            es.close();
            $('#progress-bar-wrap').fadeOut();
            alert('ROUTINE COMPLETED. SUCCESS: ' + d.cortados + ' | ERRORS: ' + d.errores);
            loadInternet();
            loadSummary();
            if ($('#tab-historial-link').hasClass('active') || $('#tab-historial').hasClass('show')) loadHistorial();
            return;
        }
        done = d.progreso;
        var pct = total > 0 ? Math.round(done / total * 100) : 0;
        $('#progress-bar').css('width', pct + '%');
        $('#progress-count').text(done + ' / ' + d.total + ' [' + pct + '%]');
    };
    es.onerror = function() {
        es.close();
        $('#progress-bar-wrap').fadeOut();
        alert('STREAM ERROR DETECTED. CHECK SYSTEM LOGS.');
        loadHistorial();
    };
});

/* ── Execute corte TV ──────────────────────────────────── */
$('#btn-ejecutar-corte-tv').on('click', function() {
    $('#modal-confirmar-corte-tv').modal('show');
});

$('#btn-confirmar-corte-tv').on('click', function() {
    $('#modal-confirmar-corte-tv').modal('hide');
    var $btn = $('#btn-ejecutar-corte-tv').prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin mr-1"></i> EXECUTING...');
    $.ajax({
        url: URLS.ejecutarTv,
        method: 'POST',
        data: { _token: csrfToken, grupo_id: GRUPO_ID, fecha: getSelectedFecha() },
    }).done(function(r) {
        alert('ROUTINE TV COMPLETED. SUCCESS: ' + r.cortados + ' | ERRORS: ' + r.errores);
        loadTv();
        loadSummary();
    }).fail(function(xhr) {
        alert('SYS-ERR: ' + (xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'UNKNOWN ERROR'));
    }).always(function() {
        $btn.prop('disabled', false).html('<i class="fas fa-bolt mr-1"></i> EXECUTE CUT (TV)');
    });
});

/* ── Habilitar cortados ────────────────────────────────── */
$('#btn-habilitar-cortados').on('click', function() {
    $('#modal-habilitar').modal('show');
});

$('#btn-confirmar-habilitar').on('click', function() {
    $('#modal-habilitar').modal('hide');
    var $btn = $('#btn-habilitar-cortados').prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin mr-1"></i> ENABLING...');
    $.ajax({
        url: URLS.habilitarCortados,
        method: 'POST',
        data: { _token: csrfToken, grupo_id: GRUPO_ID },
    }).done(function(r) {
        alert('ENABLE ROUTINE FINISHED. SUCCESS: ' + r.habilitados + ' | ERRORS: ' + r.errores);
        loadInternet();
        loadSummary();
    }).fail(function() {
        alert('SYS-ERR: FAILED TO ENABLE TARGETS.');
    }).always(function() {
        $btn.prop('disabled', false).html('<i class="fas fa-unlock mr-1"></i> ENABLE OFFLINE');
    });
});

/* ── Historial detail ──────────────────────────────────── */
$(document).on('click', '.btn-ver-detalle', function() {
    var logId = $(this).data('id');
    $('#log-detail-body').html('<tr><td colspan="7" class="text-center py-4" style="color: var(--neon-purple);"><i class="fas fa-circle-notch fa-spin mr-2"></i> DECRYPTING TRACE...</td></tr>');
    $('#modal-log-detail').modal('show');
    $.getJSON(URLS.historyDetail.replace('LOGID_PH', logId))
        .done(function(data) {
            var rows = '';
            var list = data.detalle || data || [];
            if (!list.length) {
                rows = '<tr><td colspan="7" class="text-center py-4 text-muted" style="font-family: var(--font-mono);">TRACE EMPTY</td></tr>';
            } else {
                list.forEach(function(r) {
                    var res = r.resultado === 'cortado'
                        ? '<span class="badge" style="background:rgba(0,255,102,0.15);color:var(--neon-green);border:1px solid var(--neon-green);">SUCCESS</span>'
                        : (r.resultado === 'error'
                            ? '<span class="badge" style="background:rgba(255,0,60,0.15);color:var(--neon-red);border:1px solid var(--neon-red);">ERROR</span>'
                            : '<span class="badge" style="background:rgba(255,255,255,0.1);color:var(--text-primary);border:1px solid var(--text-muted);">'+(r.resultado||'UNKNOWN')+'</span>');
                    rows += '<tr>' +
                        '<td style="font-family: var(--font-mono);">['+(r.contrato_nro||r.contrato_id||'—')+']</td>' +
                        '<td>'+(r.cliente_nombre||'—')+'</td>' +
                        '<td><code>'+(r.ip||'—')+'</code></td>' +
                        '<td><span class="badge" style="background:rgba(0,240,255,0.1);color:var(--neon-cyan);border:1px solid var(--neon-cyan);">'+(r.tipo||'—')+'</span></td>' +
                        '<td>'+res+'</td>' +
                        '<td style="font-family: var(--font-mono); color: var(--text-muted); font-size: 0.85rem;">'+(r.metodo||'—')+'</td>' +
                        '<td style="font-family: var(--font-mono); font-size: 0.85rem;">'+(r.descripcion || r.error_detalle || '—')+'</td>' +
                    '</tr>';
                });
            }
            $('#log-detail-body').html(rows);
        });
});

/* ── Misc ──────────────────────────────────────────────── */
$('#btn-refresh').on('click', function() {
    var $icon = $(this).find('i');
    $icon.addClass('fa-spin');
    loadSummary(); loadInternet(); loadTv();
    setTimeout(function(){ $icon.removeClass('fa-spin'); }, 1000);
});

$('#btn-apply-date').on('click', function() {
    var $icon = $(this).find('i');
    $icon.removeClass('fa-microchip').addClass('fa-circle-notch fa-spin');
    loadInternet(); loadTv(); loadSummary();
    setTimeout(function(){ $icon.removeClass('fa-circle-notch fa-spin').addClass('fa-microchip'); }, 1000);
});

$('#btn-refresh-historial').on('click', function(){
    var $icon = $(this).find('i');
    $icon.addClass('fa-spin');
    loadHistorial();
    setTimeout(function(){ $icon.removeClass('fa-spin'); }, 1000);
});

$('#btn-clear-cache').on('click', function() {
    var $icon = $(this).find('i');
    $icon.removeClass('fa-terminal').addClass('fa-circle-notch fa-spin');
    $.ajax({
        url: URLS.limpiarCache,
        method: 'POST',
        data: { _token: csrfToken, grupo_id: GRUPO_ID },
    }).done(function() {
        loadSummary(); loadInternet(); loadTv();
    }).always(function() {
        setTimeout(function(){ $icon.removeClass('fa-circle-notch fa-spin').addClass('fa-terminal'); }, 1000);
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
