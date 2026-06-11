@extends('layouts.app')

@section('boton')
<a href="{{ route('grupos-corte.index') }}" class="btn btn-outline-danger btn-sm"><i class="fas fa-backward"></i> Regresar</a>
<a href="{{ route('grupos-corte.show', $grupo->id) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-eye"></i> Ver Grupo</a>
<a href="{{ route('grupos-corte.analisis-ciclo', $grupo->id) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-chart-bar"></i> Análisis Ciclos</a>
<button id="btn-refresh" class="btn btn-info btn-sm text-white"><i class="fas fa-sync-alt"></i> Actualizar</button>
@endsection

@section('styles')
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
    --primary: #4e73df;
    --success: #1cc88a;
    --info: #36b9cc;
    --warning: #f6c23e;
    --danger: #e74a3b;
    --secondary: #858796;
    --dark: #5a5c69;
    --light: #f8f9fc;
    --font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

.analisis-cortes-container {
    font-family: var(--font-family);
    color: #5a5c69;
}

/* Page Header */
.page-header {
    background: #fff;
    padding: 1.5rem 2rem;
    border-radius: 1rem;
    box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.03);
    margin-bottom: 2rem;
    border-left: 5px solid var(--danger);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}
.page-title {
    font-size: 1.6rem;
    font-weight: 800;
    color: #3a3b45;
    margin: 0;
}
.page-subtitle {
    font-size: 0.9rem;
    color: #858796;
    font-weight: 500;
}

/* Filters */
.filters-card {
    background: #fff;
    border-radius: 1rem;
    padding: 1rem 1.5rem;
    box-shadow: 0 0.25rem 1rem rgba(0,0,0,0.02);
    margin-bottom: 2rem;
    display: flex;
    align-items: flex-end;
    gap: 1rem;
}

/* KPI Cards */
.kpi-card {
    border: none;
    border-radius: 1rem;
    background: #fff;
    box-shadow: 0 0.25rem 1rem rgba(0, 0, 0, 0.04);
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    position: relative;
    overflow: hidden;
    height: 100%;
}
.kpi-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.08);
}
.kpi-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; height: 4px;
}
.kpi-card.total::before { background: linear-gradient(90deg, #858796, #6c757d); }
.kpi-card.paid::before { background: linear-gradient(90deg, #1cc88a, #20c997); }
.kpi-card.unpaid::before { background: linear-gradient(90deg, #e74a3b, #dc3545); }
.kpi-card.cut::before { background: linear-gradient(90deg, #f6c23e, #fd7e14); }
.kpi-card.pending::before { background: linear-gradient(90deg, #f6c23e, #ffc107); }
.kpi-card.promise::before { background: linear-gradient(90deg, #36b9cc, #17a2b8); }

.kpi-icon {
    position: absolute;
    right: -10px;
    bottom: -15px;
    font-size: 5rem;
    opacity: 0.05;
    transform: rotate(-15deg);
    transition: all 0.3s ease;
}
.kpi-card:hover .kpi-icon {
    transform: rotate(0deg) scale(1.1);
    opacity: 0.08;
}

.kpi-number { font-size: 2.2rem; font-weight: 800; letter-spacing: -1px; margin-bottom: 0; line-height: 1.2; }
.kpi-label  { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #a3a6b4; letter-spacing: 0.5px; }

/* Badges */
.badge { padding: 0.5em 0.8em; border-radius: 50rem; font-weight: 600; font-size: 0.7rem; letter-spacing: 0.3px; }
.badge-estado-pagado   { background-color: rgba(28, 200, 138, 0.15); color: #159666; }
.badge-estado-abierta  { background-color: rgba(231, 74, 59, 0.15); color: #b7382c; }
.badge-estado-cortado  { background-color: rgba(246, 194, 62, 0.15); color: #c49629; }
.badge-estado-promesa  { background-color: rgba(54, 185, 204, 0.15); color: #2a8b99; }
.badge-estado-bloqueado{ background-color: rgba(133, 135, 150, 0.15); color: #626470; }

/* Buttons */
.btn { border-radius: 0.5rem; font-weight: 600; transition: all 0.2s; letter-spacing: 0.3px; }
.btn-sm { padding: 0.4rem 0.8rem; font-size: 0.8rem; }
.btn-primary { background: linear-gradient(90deg, #4e73df, #224abe); border: none; box-shadow: 0 4px 10px rgba(78,115,223,0.2); }
.btn-danger { background: linear-gradient(90deg, #e74a3b, #be2617); border: none; box-shadow: 0 4px 10px rgba(231,74,59,0.2); }
.btn-success { background: linear-gradient(90deg, #1cc88a, #138f62); border: none; box-shadow: 0 4px 10px rgba(28,200,138,0.2); }
.btn-warning { background: linear-gradient(90deg, #f6c23e, #d8a00b); border: none; color: #fff; box-shadow: 0 4px 10px rgba(246,194,62,0.2); }
.btn-info { background: linear-gradient(90deg, #36b9cc, #258391); border: none; box-shadow: 0 4px 10px rgba(54,185,204,0.2); }
.btn-outline-secondary { border: 1px solid #eaecf4; color: #858796; background: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
.btn:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(0,0,0,0.15); color: #fff; }
.btn-outline-secondary:hover { background: #f8f9fc; color: #5a5c69; border-color: #d1d3e2; }

/* Tabs */
.nav-tabs { border-bottom: 2px solid #eaecf4; border-radius: 1rem 1rem 0 0; background: #fff; padding: 0.5rem 1rem 0; }
.nav-tabs .nav-link { border: none; color: #858796; font-weight: 600; padding: 1rem 1.5rem; position: relative; font-size: 0.9rem; transition: all 0.2s; border-radius: 0.5rem 0.5rem 0 0; }
.nav-tabs .nav-link:hover { color: #4e73df; background: #f8f9fc; }
.nav-tabs .nav-link.active { color: #4e73df; background: transparent; font-weight: 700; }
.nav-tabs .nav-link.active::after { content: ''; position: absolute; bottom: -2px; left: 0; right: 0; height: 3px; background: #4e73df; border-radius: 3px 3px 0 0; }

.tab-content-wrapper { background: #fff; border-radius: 0 0 1rem 1rem; box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.03); padding: 2rem; border: 1px solid #eaecf4; border-top: none; }

/* Tables */
.table-responsive { border-radius: 0.75rem; border: 1px solid #eaecf4; overflow: hidden; }
.table { margin-bottom: 0; }
.table th { background-color: #f8f9fc; color: #5a5c69; font-weight: 700; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.8px; border-bottom: 2px solid #eaecf4; padding: 1rem; border-top: none; }
.table td { vertical-align: middle; padding: 1rem; border-top: 1px solid #eaecf4; color: #5a5c69; font-size: 0.85rem; }
.table tbody tr { transition: background-color 0.2s; }
.table tbody tr:hover { background-color: #f8f9fc; }
code { background: rgba(78, 115, 223, 0.1); color: #4e73df; padding: 0.2rem 0.4rem; border-radius: 0.3rem; font-size: 0.8rem; font-family: 'SFMono-Regular', Consolas, "Liberation Mono", Menlo, monospace; }

/* Progress bar */
.progress { border-radius: 50rem; height: 0.8rem; background-color: #eaecf4; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05); }
.progress-bar-animated { animation: progress-bar-stripes 1s linear infinite; }

/* Modals */
.modal-content { border-radius: 1rem; border: none; box-shadow: 0 1rem 3rem rgba(0,0,0,0.1); }
.modal-header { border-bottom: 1px solid #eaecf4; border-radius: 1rem 1rem 0 0; padding: 1.5rem; }
.modal-body { padding: 1.5rem; }
.modal-footer { border-top: 1px solid #eaecf4; border-radius: 0 0 1rem 1rem; padding: 1.5rem; }

/* Subtabs Mk Sync */
#mk-subtabs { padding: 0; background: transparent; border-radius: 0; margin-bottom: 1rem; }
#mk-subtabs .nav-link { padding: 0.5rem 1rem; }
#mk-subtabs .nav-link.active::after { height: 2px; }

</style>
@endsection

@section('content')
<div class="container-fluid analisis-cortes-container">
    
    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-cut text-danger mr-2"></i> Análisis de Cortes
            </h1>
            <div class="page-subtitle mt-1">
                <strong>{{ $grupo->nombre }}</strong> &bull; Corte: día {{ $grupo->fecha_corte }} &bull; Suspensión: día {{ $grupo->fecha_suspension }}
            </div>
        </div>
        <div>
            <button id="btn-clear-cache" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-eraser mr-1"></i> Limpiar Caché
            </button>
        </div>
    </div>

    {{-- Filters --}}
    <div class="filters-card">
        <div class="form-group mb-0">
            <label class="small font-weight-bold text-muted mb-1 text-uppercase">Fecha de referencia</label>
            <input type="date" id="fecha-ref" class="form-control form-control-sm" style="border-radius:0.5rem; padding:0.4rem 0.8rem; font-weight:500;" value="{{ date('Y-m-d') }}">
        </div>
        <div>
            <button id="btn-apply-date" class="btn btn-sm btn-primary">
                <i class="fas fa-filter mr-1"></i> Aplicar Filtro
            </button>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="row mb-4" id="kpi-row">
        <div class="col-6 col-lg-2 col-md-4 mb-3">
            <div class="kpi-card total">
                <div class="card-body p-4">
                    <i class="fas fa-file-contract kpi-icon text-secondary"></i>
                    <div class="kpi-label">Total Contratos</div>
                    <div class="kpi-number text-secondary" id="kpi-total">—</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2 col-md-4 mb-3">
            <div class="kpi-card paid">
                <div class="card-body p-4">
                    <i class="fas fa-check-circle kpi-icon text-success"></i>
                    <div class="kpi-label">Al día</div>
                    <div class="kpi-number text-success" id="kpi-pagados">—</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2 col-md-4 mb-3">
            <div class="kpi-card unpaid">
                <div class="card-body p-4">
                    <i class="fas fa-exclamation-circle kpi-icon text-danger"></i>
                    <div class="kpi-label">En mora</div>
                    <div class="kpi-number text-danger" id="kpi-mora">—</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2 col-md-4 mb-3">
            <div class="kpi-card cut">
                <div class="card-body p-4">
                    <i class="fas fa-power-off kpi-icon text-warning"></i>
                    <div class="kpi-label">Ya cortados</div>
                    <div class="kpi-number text-warning" id="kpi-cortados">—</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2 col-md-4 mb-3">
            <div class="kpi-card pending">
                <div class="card-body p-4">
                    <i class="fas fa-clock kpi-icon text-warning"></i>
                    <div class="kpi-label">Pendientes corte</div>
                    <div class="kpi-number text-warning" id="kpi-pendientes">—</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2 col-md-4 mb-3">
            <div class="kpi-card promise">
                <div class="card-body p-4">
                    <i class="fas fa-handshake kpi-icon text-info"></i>
                    <div class="kpi-label">Con promesa</div>
                    <div class="kpi-number text-info" id="kpi-promesas">—</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Progress bar (corte en progreso) --}}
    <div id="progress-bar-wrap" class="mb-4" style="display:none;">
        <div class="d-flex justify-content-between small mb-2 font-weight-bold">
            <span id="progress-label" class="text-danger">Ejecutando corte…</span>
            <span id="progress-count" class="text-secondary">0 / 0</span>
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
                <i class="fas fa-wifi mr-1"></i> Internet
                <span class="badge badge-danger ml-2" id="badge-internet" style="vertical-align: text-bottom;">0</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-tv-link" data-toggle="tab" href="#tab-tv">
                <i class="fas fa-tv mr-1"></i> Televisión
                <span class="badge badge-warning text-dark ml-2" id="badge-tv" style="vertical-align: text-bottom;">0</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-historial-link" data-toggle="tab" href="#tab-historial">
                <i class="fas fa-history mr-1"></i> Historial
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-mk-link" data-toggle="tab" href="#tab-mk">
                <i class="fas fa-network-wired mr-1"></i> Sync MikroTik
            </a>
        </li>
    </ul>

    <div class="tab-content-wrapper" id="main-tab-content">
        {{-- TAB INTERNET --}}
        <div class="tab-pane fade show active" id="tab-internet" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div class="d-flex align-items-center">
                    <div class="icon-circle bg-danger text-white mr-3" style="width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 10px rgba(231,74,59,0.2);">
                        <i class="fas fa-wifi"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 font-weight-bold text-dark">Pendientes de corte</h5>
                        <small class="text-muted"><span id="internet-count" class="font-weight-bold text-danger">0</span> contratos encontrados</small>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button id="btn-habilitar-cortados" class="btn btn-success btn-sm px-3">
                        <i class="fas fa-check-circle mr-1"></i> Habilitar Cortados
                    </button>
                    <button id="btn-ejecutar-corte" class="btn btn-danger btn-sm px-3">
                        <i class="fas fa-cut mr-1"></i> Ejecutar Corte Internet
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover" id="tabla-internet">
                    <thead>
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
                        <tr><td colspan="9" class="text-center text-muted py-4"><i class="fas fa-circle-notch fa-spin mr-2"></i> Cargando…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- TAB TV --}}
        <div class="tab-pane fade" id="tab-tv" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div class="d-flex align-items-center">
                    <div class="icon-circle bg-warning text-white mr-3" style="width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 10px rgba(246,194,62,0.2);">
                        <i class="fas fa-tv"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 font-weight-bold text-dark">Pendientes de corte TV</h5>
                        <small class="text-muted"><span id="tv-count" class="font-weight-bold text-warning">0</span> contratos encontrados</small>
                    </div>
                </div>
                <button id="btn-ejecutar-corte-tv" class="btn btn-warning btn-sm text-white px-3">
                    <i class="fas fa-cut mr-1"></i> Ejecutar Corte TV
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover" id="tabla-tv">
                    <thead>
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
                        <tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-circle-notch fa-spin mr-2"></i> Cargando…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- TAB HISTORIAL --}}
        <div class="tab-pane fade" id="tab-historial" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="font-weight-bold text-dark mb-0">Historial de cortes ejecutados</h5>
                <button id="btn-refresh-historial" class="btn btn-sm btn-outline-secondary px-3">
                    <i class="fas fa-sync-alt mr-1"></i> Actualizar
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover" id="tabla-historial">
                    <thead>
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
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-historial">
                        <tr><td colspan="10" class="text-center text-muted py-4"><i class="fas fa-circle-notch fa-spin mr-2"></i> Cargando…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- TAB MK SYNC --}}
        <div class="tab-pane fade" id="tab-mk" role="tabpanel">
            <div class="d-flex align-items-end mb-4 gap-3 bg-light p-3 rounded" style="border: 1px solid #eaecf4;">
                <div style="min-width: 300px;">
                    <label class="small font-weight-bold text-muted text-uppercase mb-1">Seleccionar MikroTik</label>
                    <select id="select-mikrotik" class="form-control form-control-sm" style="border-radius:0.5rem; height: calc(1.5em + 0.8rem + 2px);">
                        <option value="">— Seleccione MikroTik —</option>
                        @foreach($mikrotiks as $mk)
                        <option value="{{ $mk->id }}">{{ $mk->nombre }} ({{ $mk->ip }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <button id="btn-analizar-mk" class="btn btn-sm btn-info px-3">
                        <i class="fas fa-search mr-1"></i> Analizar
                    </button>
                </div>
                <div class="ml-auto">
                    <button id="btn-solucionar-lote" class="btn btn-sm btn-warning text-white px-3" disabled>
                        <i class="fas fa-magic mr-1"></i> Solucionar Lote
                    </button>
                </div>
            </div>

            <div id="mk-sync-result" class="d-none mt-4">
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="kpi-card h-100" style="border-left: 4px solid var(--success);">
                            <div class="card-body p-3">
                                <div class="font-weight-bold text-success mb-1" style="font-size:1.8rem; line-height:1;" id="mk-ok-count">0</div>
                                <div class="small font-weight-bold text-uppercase text-muted">Morosos OK</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="kpi-card h-100" style="border-left: 4px solid var(--danger);">
                            <div class="card-body p-3">
                                <div class="font-weight-bold text-danger mb-1" style="font-size:1.8rem; line-height:1;" id="mk-faltantes-count">0</div>
                                <div class="small font-weight-bold text-uppercase text-muted">Cortados sin morosos en MK</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="kpi-card h-100" style="border-left: 4px solid var(--warning);">
                            <div class="card-body p-3">
                                <div class="font-weight-bold text-warning mb-1" style="font-size:1.8rem; line-height:1;" id="mk-extra-count">0</div>
                                <div class="small font-weight-bold text-uppercase text-muted">En morosos MK sin corte</div>
                            </div>
                        </div>
                    </div>
                </div>

                <ul class="nav nav-tabs" id="mk-subtabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#mk-tab-faltantes">
                            <i class="fas fa-exclamation-circle text-danger mr-1"></i> Faltantes en MK
                            <span class="badge badge-danger ml-2" id="badge-faltantes">0</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#mk-tab-extra">
                            <i class="fas fa-question-circle text-warning mr-1"></i> Extras en MK
                            <span class="badge badge-warning text-white ml-2" id="badge-extra">0</span>
                        </a>
                    </li>
                </ul>
                <div class="tab-content" style="border: 1px solid #eaecf4; border-top: none; border-radius: 0 0 0.5rem 0.5rem; padding: 1.5rem;">
                    <div class="tab-pane fade show active" id="mk-tab-faltantes">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr><th>Contrato</th><th>Cliente</th><th>IP</th><th>Factura</th><th>Valor</th></tr>
                                </thead>
                                <tbody id="tbody-mk-faltantes">
                                    <tr><td colspan="5" class="text-center text-muted py-3">Sin datos</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="mk-tab-extra">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr><th>IP</th><th>Lista MK</th><th>Comentario</th></tr>
                                </thead>
                                <tbody id="tbody-mk-extra">
                                    <tr><td colspan="3" class="text-center text-muted py-3">Sin datos</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div id="mk-sync-empty" class="text-muted text-center py-5 rounded" style="background:#f8f9fc; border:2px dashed #eaecf4;">
                <i class="fas fa-network-wired fa-3x mb-3 text-secondary" style="opacity:0.3;"></i><br>
                <strong>Seleccione un MikroTik y haga clic en Analizar.</strong>
            </div>
        </div>

    </div>
</div>

{{-- Modal: Confirmar corte internet --}}
<div class="modal fade" id="modal-confirmar-corte" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white border-0" style="border-radius:1rem 1rem 0 0;">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-cut mr-2"></i> Confirmar Corte Internet</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p class="font-weight-bold">Se ejecutará el corte de servicio para <span id="modal-corte-count" class="text-danger font-weight-bolder" style="font-size:1.2rem;">0</span> contrato(s) pendiente(s).</p>
                <div class="alert alert-warning border-0" style="background: rgba(246,194,62,0.15); color: #b98a12; border-radius:0.5rem;">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Esta acción moverá las IPs a la lista <code>morosos</code> en MikroTik y deshabilitará los contratos en el sistema.
                </div>
                <div class="form-group mt-3 mb-0">
                    <label class="small font-weight-bold text-uppercase text-muted">Fecha de corte</label>
                    <input type="date" id="modal-fecha-corte" class="form-control form-control-sm" style="border-radius:0.5rem; padding:1rem 0.8rem;">
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary btn-sm px-4" data-dismiss="modal">Cancelar</button>
                <button type="button" id="btn-confirmar-corte" class="btn btn-danger btn-sm px-4">
                    <i class="fas fa-cut mr-1"></i> Ejecutar Corte
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Confirmar corte TV --}}
<div class="modal fade" id="modal-confirmar-corte-tv" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning border-0" style="border-radius:1rem 1rem 0 0;">
                <h5 class="modal-title text-white font-weight-bold"><i class="fas fa-tv mr-2"></i> Confirmar Corte TV</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p class="font-weight-bold">Se ejecutará el corte de TV para <span id="modal-corte-tv-count" class="text-warning font-weight-bolder" style="font-size:1.2rem;">0</span> contrato(s).</p>
                <div class="alert alert-warning border-0" style="background: rgba(246,194,62,0.15); color: #b98a12; border-radius:0.5rem;">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Esta acción deshabilitará las ONUs en SmartOLT.
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary btn-sm px-4" data-dismiss="modal">Cancelar</button>
                <button type="button" id="btn-confirmar-corte-tv" class="btn btn-warning text-white btn-sm px-4">
                    <i class="fas fa-tv mr-1"></i> Ejecutar Corte TV
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Habilitar cortados --}}
<div class="modal fade" id="modal-habilitar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white border-0" style="border-radius:1rem 1rem 0 0;">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-check-circle mr-2"></i> Habilitar Cortados</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p class="font-weight-bold">¿Desea habilitar todos los contratos actualmente en estado cortado en este grupo?</p>
                <p class="text-muted small">Se quitarán de la lista <code>morosos</code> y se agregarán a <code>ips_autorizadas</code> en MikroTik.</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary btn-sm px-4" data-dismiss="modal">Cancelar</button>
                <button type="button" id="btn-confirmar-habilitar" class="btn btn-success btn-sm px-4">
                    <i class="fas fa-check-circle mr-1"></i> Confirmar y Habilitar
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
                <h5 class="modal-title font-weight-bold text-dark"><i class="fas fa-list text-primary mr-2"></i> Detalle de Ejecución</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
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
                            <tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-circle-notch fa-spin mr-2"></i> Cargando…</td></tr>
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
    $('#tbody-internet').html('<tr><td colspan="9" class="text-center text-muted py-4"><i class="fas fa-circle-notch fa-spin mr-2"></i> Cargando…</td></tr>');
    $.getJSON(URLS.pendingInternet + '?grupo_id=' + GRUPO_ID + '&fecha=' + fecha)
        .done(function(res) {
            var rows = '';
            var list = res.data || [];
            $('#internet-count').text(list.length);
            $('#badge-internet').text(list.length);
            $('#modal-corte-count').text(list.length);
            if (!list.length) {
                rows = '<tr><td colspan="9" class="text-center text-success py-4"><i class="fas fa-check-circle mr-2" style="font-size:1.5rem;"></i><br><span class="font-weight-bold mt-2 d-inline-block">Sin pendientes de corte</span></td></tr>';
            } else {
                list.forEach(function(c, i) {
                    rows += '<tr>' +
                        '<td><span class="text-muted font-weight-bold">' + (i+1) + '</span></td>' +
                        '<td class="font-weight-bold text-dark">' + (c.contrato_nro || '—') + '</td>' +
                        '<td>' + (c.cliente_nombre || '—') + '</td>' +
                        '<td><code>' + (c.ip || '—') + '</code></td>' +
                        '<td class="small">' + (c.mikrotik_nombre || '—') + '</td>' +
                        '<td class="small"><span class="badge" style="background:#f8f9fc;color:#5a5c69;border:1px solid #eaecf4;">' + (c.factura_codigo || '—') + '</span></td>' +
                        '<td class="font-weight-bold text-dark">$' + fmt(c.factura_total) + '</td>' +
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
    $('#tbody-tv').html('<tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-circle-notch fa-spin mr-2"></i> Cargando…</td></tr>');
    $.getJSON(URLS.pendingTv + '?grupo_id=' + GRUPO_ID + '&fecha=' + fecha)
        .done(function(res) {
            var rows = '';
            var list = res.data || [];
            $('#tv-count').text(list.length);
            $('#badge-tv').text(list.length);
            $('#modal-corte-tv-count').text(list.length);
            if (!list.length) {
                rows = '<tr><td colspan="8" class="text-center text-success py-4"><i class="fas fa-check-circle mr-2" style="font-size:1.5rem;"></i><br><span class="font-weight-bold mt-2 d-inline-block">Sin pendientes de corte TV</span></td></tr>';
            } else {
                list.forEach(function(c, i) {
                    rows += '<tr>' +
                        '<td><span class="text-muted font-weight-bold">' + (i+1) + '</span></td>' +
                        '<td class="font-weight-bold text-dark">' + (c.contrato_nro || '—') + '</td>' +
                        '<td>' + (c.cliente_nombre || '—') + '</td>' +
                        '<td><code>' + (c.serial_onu || '—') + '</code></td>' +
                        '<td class="small"><span class="badge" style="background:#f8f9fc;color:#5a5c69;border:1px solid #eaecf4;">' + (c.factura_codigo || '—') + '</span></td>' +
                        '<td class="font-weight-bold text-dark">$' + fmt(c.factura_total) + '</td>' +
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
    $('#tbody-historial').html('<tr><td colspan="10" class="text-center text-muted py-4"><i class="fas fa-circle-notch fa-spin mr-2"></i> Cargando…</td></tr>');
    $.getJSON(URLS.history)
        .done(function(data) {
            var rows = '';
            if (!data.length) {
                rows = '<tr><td colspan="10" class="text-center text-muted py-4">Sin historial</td></tr>';
            } else {
                data.forEach(function(h) {
                    var tipoBadge = h.tipo === 'internet'
                        ? '<span class="badge" style="background:rgba(54,185,204,0.1);color:#36b9cc;">Internet</span>'
                        : '<span class="badge" style="background:rgba(246,194,62,0.1);color:#d8a00b;">TV</span>';
                    var duracion = h.duracion_ms ? (h.duracion_ms / 1000).toFixed(1) + 's' : '—';
                    rows += '<tr>' +
                        '<td class="font-weight-bold">#' + h.id + '</td>' +
                        '<td>' + tipoBadge + '</td>' +
                        '<td>' + (h.total_procesados || 0) + '</td>' +
                        '<td class="text-danger font-weight-bold">' + (h.total_cortados || 0) + '</td>' +
                        '<td>' + (h.total_omitidos || 0) + '</td>' +
                        '<td class="text-danger">' + (h.total_errores || 0) + '</td>' +
                        '<td>' + duracion + '</td>' +
                        '<td class="small">' + (h.ejecutado_por_nombre || 'CRON') + '</td>' +
                        '<td class="small text-muted">' + (h.created_at || '—') + '</td>' +
                        '<td><button class="btn btn-sm btn-outline-primary btn-ver-detalle" data-id="' + h.id + '" style="padding:0.2rem 0.5rem;"><i class="fas fa-eye"></i></button></td>' +
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
    var $btn = $(this).prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin mr-1"></i> Analizando…');
    $('#mk-sync-empty').addClass('d-none');
    $('#mk-sync-result').addClass('d-none');
    $.ajax({
        url: URLS.mkSync,
        method: 'POST',
        data: { _token: csrfToken, mikrotik_id: mkId, grupo_id: GRUPO_ID },
    }).done(function(d) {
        if (!d.disponible) {
            $('#mk-sync-empty').removeClass('d-none').html('<div class="alert alert-danger border-0" style="background:rgba(231,74,59,0.1);color:#e74a3b;">' + (d.error || 'No disponible') + '</div>');
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
            rowsF = '<tr><td colspan="5" class="text-center text-success py-4"><i class="fas fa-check-circle mr-2" style="font-size:1.5rem;"></i><br><span class="font-weight-bold mt-2 d-inline-block">Sin discrepancias</span></td></tr>';
        } else {
            faltantes.forEach(function(c) {
                rowsF += '<tr><td class="font-weight-bold">'+(c.nro||c.id)+'</td><td>'+(c.cliente_nombre||'—')+'</td><td><code>'+(c.ip||'—')+'</code></td><td class="small"><span class="badge" style="background:#f8f9fc;color:#5a5c69;border:1px solid #eaecf4;">'+(c.factura_codigo||'—')+'</span></td><td class="font-weight-bold">$' + fmt(c.factura_total) + '</td></tr>';
            });
        }
        $('#tbody-mk-faltantes').html(rowsF);

        // Extra
        var rowsE = '';
        if (!extra.length) {
            rowsE = '<tr><td colspan="3" class="text-center text-success py-4"><i class="fas fa-check-circle mr-2" style="font-size:1.5rem;"></i><br><span class="font-weight-bold mt-2 d-inline-block">Sin extras</span></td></tr>';
        } else {
            extra.forEach(function(e) {
                rowsE += '<tr><td><code>'+(e.address||'—')+'</code></td><td><span class="badge badge-warning text-white">'+(e.list||'—')+'</span></td><td class="small">'+(e.comment||'—')+'</td></tr>';
            });
        }
        $('#tbody-mk-extra').html(rowsE);

        $('#mk-sync-result').removeClass('d-none');
        $('#btn-solucionar-lote').prop('disabled', faltantes.length === 0);
    }).fail(function() {
        $('#mk-sync-empty').removeClass('d-none').html('<div class="alert alert-danger border-0" style="background:rgba(231,74,59,0.1);color:#e74a3b;">Error al analizar MikroTik.</div>');
    }).always(function() {
        $btn.prop('disabled', false).html('<i class="fas fa-search mr-1"></i> Analizar');
    });
});

$('#btn-solucionar-lote').on('click', function() {
    var mkId = $('#select-mikrotik').val();
    if (!mkId || !confirm('¿Agregar a morosos en MikroTik todos los contratos cortados con discrepancia?')) return;
    var $btn = $(this).prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin mr-1"></i> Procesando…');
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
        $btn.prop('disabled', false).html('<i class="fas fa-magic mr-1"></i> Solucionar Lote');
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
    $('#progress-label').text('Ejecutando corte internet…');
    $('#progress-count').text('0 / ?');

    var total = parseInt($('#internet-count').text()) || 0;
    var done = 0;

    var es = new EventSource(URLS.ejecutarStream + '?grupo_id=' + GRUPO_ID + '&fecha=' + fecha);
    es.onmessage = function(e) {
        var d = JSON.parse(e.data);
        if (d.done) {
            es.close();
            $('#progress-bar-wrap').fadeOut();
            alert('Corte completado. Cortados: ' + d.cortados + ' | Errores: ' + d.errores);
            loadInternet();
            loadSummary();
            if ($('#tab-historial-link').hasClass('active') || $('#tab-historial').hasClass('show')) loadHistorial();
            return;
        }
        done = d.progreso;
        var pct = total > 0 ? Math.round(done / total * 100) : 0;
        $('#progress-bar').css('width', pct + '%');
        $('#progress-count').text(done + ' / ' + d.total + ' (' + pct + '%)');
    };
    es.onerror = function() {
        es.close();
        $('#progress-bar-wrap').fadeOut();
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
    var $btn = $('#btn-ejecutar-corte-tv').prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin mr-1"></i> Ejecutando…');
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
        $btn.prop('disabled', false).html('<i class="fas fa-cut mr-1"></i> Ejecutar Corte TV');
    });
});

/* ── Habilitar cortados ────────────────────────────────── */
$('#btn-habilitar-cortados').on('click', function() {
    $('#modal-habilitar').modal('show');
});

$('#btn-confirmar-habilitar').on('click', function() {
    $('#modal-habilitar').modal('hide');
    var $btn = $('#btn-habilitar-cortados').prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin mr-1"></i> Procesando…');
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
        $btn.prop('disabled', false).html('<i class="fas fa-check-circle mr-1"></i> Habilitar Cortados');
    });
});

/* ── Historial detail ──────────────────────────────────── */
$(document).on('click', '.btn-ver-detalle', function() {
    var logId = $(this).data('id');
    $('#log-detail-body').html('<tr><td colspan="7" class="text-center py-4"><i class="fas fa-circle-notch fa-spin mr-2"></i> Cargando…</td></tr>');
    $('#modal-log-detail').modal('show');
    $.getJSON(URLS.historyDetail.replace('LOGID_PH', logId))
        .done(function(data) {
            var rows = '';
            var list = data.detalle || data || [];
            if (!list.length) {
                rows = '<tr><td colspan="7" class="text-center text-muted py-4">Sin detalle</td></tr>';
            } else {
                list.forEach(function(r) {
                    var res = r.resultado === 'cortado'
                        ? '<span class="badge" style="background:rgba(28,200,138,0.15);color:#1cc88a;">cortado</span>'
                        : (r.resultado === 'error'
                            ? '<span class="badge" style="background:rgba(231,74,59,0.15);color:#e74a3b;">error</span>'
                            : '<span class="badge" style="background:rgba(133,135,150,0.15);color:#858796;">'+(r.resultado||'—')+'</span>');
                    rows += '<tr>' +
                        '<td class="font-weight-bold">'+(r.contrato_nro||r.contrato_id||'—')+'</td>' +
                        '<td>'+(r.cliente_nombre||'—')+'</td>' +
                        '<td><code>'+(r.ip||'—')+'</code></td>' +
                        '<td><span class="badge" style="background:#f8f9fc;color:#5a5c69;border:1px solid #eaecf4;">'+(r.tipo||'—')+'</span></td>' +
                        '<td>'+res+'</td>' +
                        '<td class="small text-muted">'+(r.metodo||'—')+'</td>' +
                        '<td class="small">'+(r.descripcion || r.error_detalle || '—')+'</td>' +
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
    $icon.removeClass('fa-filter').addClass('fa-circle-notch fa-spin');
    loadInternet(); loadTv(); loadSummary();
    setTimeout(function(){ $icon.removeClass('fa-circle-notch fa-spin').addClass('fa-filter'); }, 1000);
});

$('#btn-refresh-historial').on('click', function(){
    var $icon = $(this).find('i');
    $icon.addClass('fa-spin');
    loadHistorial();
    setTimeout(function(){ $icon.removeClass('fa-spin'); }, 1000);
});

$('#btn-clear-cache').on('click', function() {
    var $icon = $(this).find('i');
    $icon.removeClass('fa-eraser').addClass('fa-circle-notch fa-spin');
    $.ajax({
        url: URLS.limpiarCache,
        method: 'POST',
        data: { _token: csrfToken, grupo_id: GRUPO_ID },
    }).done(function() {
        loadSummary(); loadInternet(); loadTv();
    }).always(function() {
        setTimeout(function(){ $icon.removeClass('fa-circle-notch fa-spin').addClass('fa-eraser'); }, 1000);
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
