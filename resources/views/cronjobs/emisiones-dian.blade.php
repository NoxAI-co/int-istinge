@extends('layouts.app')

@section('content')
<style>
    /* Google Fonts */
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap');

    /* Premium Design System */
    :root {
        --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --success-gradient: linear-gradient(135deg, #2af598 0%, #009efd 100%);
        --danger-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        --warning-gradient: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
        --primary: #667eea;
        --success: #009efd;
        --info: #36b9cc;
        --warning: #f6c23e;
        --danger: #e74a3b;
        --dark: #2e3b4e;
        --glass: rgba(255, 255, 255, 0.9);
        --glass-shadow: 0 10px 40px rgba(0,0,0,0.03);
    }

    body {
        font-family: 'Outfit', sans-serif;
        background-color: #f8f9fc;
    }

    /* Header System */
    .audit-header {
        background: var(--primary-gradient);
        padding: 40px 30px;
        margin: -20px -20px 30px -20px;
        color: white;
        border-radius: 0 0 30px 30px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }

    /* Premium Cards */
    .audit-card {
        border: none;
        border-radius: 15px;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        overflow: hidden;
        margin-bottom: 25px;
        background: white;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    }

    .audit-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0,0,0,0.1);
    }

    .kpi-card {
        padding: 25px;
        position: relative;
        color: white;
        min-height: 140px;
    }

    .kpi-card .icon {
        position: absolute;
        right: 20px;
        bottom: 10px;
        font-size: 3.5rem;
        opacity: 0.2;
        transition: transform 0.3s ease;
    }

    .audit-card:hover .icon {
        transform: scale(1.1) rotate(-10deg);
    }

    .kpi-value {
        font-size: 2.2rem;
        font-weight: 800;
        margin-bottom: 5px;
        display: block;
        line-height: 1;
    }

    .kpi-label {
        font-size: 0.85rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 1px;
        opacity: 0.9;
    }

    .bg-gradient-blue { background: var(--primary-gradient); }
    .bg-gradient-green { background: var(--success-gradient); }
    .bg-gradient-red { background: var(--danger-gradient); }
    .bg-gradient-gold { background: var(--warning-gradient); }

    /* Containers */
    .premium-container {
        background: white;
        border-radius: 20px;
        padding: 25px;
        box-shadow: var(--glass-shadow);
        margin-bottom: 30px;
    }

    /* Progress Section */
    .progress-premium {
        height: 12px;
        border-radius: 50px;
        background-color: #f1f5f9;
        margin-top: 10px;
    }

    .progress-bar-premium {
        border-radius: 50px;
        background: var(--success-gradient);
        box-shadow: 0 4px 10px rgba(0, 158, 253, 0.3);
        transition: width 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    /* Console Logger Style */
    .console-logger {
        background: #1e293b;
        color: #e2e8f0;
        border-radius: 12px;
        font-family: 'Fira Code', 'Consolas', monospace;
        padding: 20px;
        max-height: 400px;
        overflow-y: auto;
        box-shadow: inset 0 2px 10px rgba(0,0,0,0.2);
    }

    .console-line {
        margin-bottom: 8px;
        border-bottom: 1px solid rgba(255,255,255,0.05);
        padding-bottom: 8px;
        font-size: 0.85rem;
        display: flex;
        flex-wrap: wrap;
    }

    .console-timestamp { color: #94a3b8; margin-right: 12px; font-weight: 500; }
    .console-status { font-weight: 700; text-transform: uppercase; margin-right: 12px; padding: 2px 8px; border-radius: 4px; font-size: 0.7rem; }
    .status-emitida { background: rgba(34, 197, 94, 0.2); color: #4ade80; }
    .status-fallida { background: rgba(239, 68, 68, 0.2); color: #f87171; }
    .status-pendiente { background: rgba(234, 179, 8, 0.2); color: #fbbf24; }
    
    /* Buttons */
    .btn-premium {
        background: var(--primary-gradient);
        border: none;
        padding: 12px 25px;
        border-radius: 12px;
        font-weight: 600;
        color: white;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }

    .btn-premium:hover {
        transform: scale(1.05);
        color: white;
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
    }

    .btn-premium-success {
        background: var(--success-gradient);
        box-shadow: 0 4px 15px rgba(42, 245, 152, 0.3);
    }

    .btn-premium-success:hover {
        box-shadow: 0 6px 20px rgba(42, 245, 152, 0.5);
    }

    /* Table System */
    .table-premium thead th {
        background: #f8fafc;
        border: none;
        text-transform: uppercase;
        font-size: 0.75rem;
        font-weight: 700;
        color: #64748b;
        letter-spacing: 1px;
        padding: 15px;
    }

    .table-premium td {
        vertical-align: middle;
        padding: 18px 15px;
        border-top: 1px solid #f1f5f9;
        font-size: 0.9rem;
    }

    .badge-premium {
        padding: 6px 12px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
    }

</style>

<div class="container-fluid">
    {{-- ═══════════════════════════════════════════════════════════════
         PANEL DE CABECERA
         ═══════════════════════════════════════════════════════════════ --}}
    <!-- Premium Header -->
    <div class="audit-header d-flex justify-content-between align-items-center">
        <div>
            <h1 class="font-weight-bold mb-1">Emisiones Automáticas DIAN</h1>
            <p class="mb-0 opacity-75">Gestión y monitoreo del proceso de facturación electrónica en tiempo real</p>
        </div>
        <div class="d-flex align-items-center">
            <div class="bg-white p-2 rounded-pill mr-3 d-flex align-items-center shadow-sm px-3">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="switch-emision" {{ $empresa->emision_automatica ? 'checked' : '' }}>
                    <label class="custom-control-label text-dark font-weight-bold" for="switch-emision" style="cursor: pointer;">Emisión Automática</label>
                </div>
            </div>
            <button id="btn-refresh" class="btn btn-outline-light border-0 mr-2" style="border-radius: 12px; padding: 10px 15px;">
                <i class="fas fa-sync-alt"></i>
            </button>
            <button id="btn-ejecutar" class="btn btn-premium btn-premium-success shadow-none">
                <i class="fas fa-rocket mr-2"></i> Ejecutar Ahora
            </button>
        </div>
    </div>

    {{-- Info Empresa & Numeración --}}
    <div class="premium-container" style="margin-top: -30px; position: relative; z-index: 10;">
        <div class="row text-center text-md-left align-items-center">
            <div class="col-md-3 border-right mb-3 mb-md-0">
                <div class="d-flex align-items-center justify-content-center justify-content-md-start">
                    <div class="bg-light p-3 rounded-circle mr-3 text-primary"><i class="fas fa-building fa-lg"></i></div>
                    <div>
                        <h6 class="text-muted font-weight-bold mb-0 small uppercase">Empresa</h6>
                        <h5 class="mb-0 text-truncate font-weight-bold" style="max-width: 180px;">{{ $empresa->nombre ?? 'N/A' }}</h5>
                        <small class="text-primary font-weight-bold">NIT: {{ $empresa->nit ?? 'N/A' }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 border-right mb-3 mb-md-0">
                <div class="d-flex align-items-center justify-content-center justify-content-md-start">
                    <div class="bg-light p-3 rounded-circle mr-3 text-info"><i class="fas fa-file-invoice-dollar fa-lg"></i></div>
                    <div>
                        <h6 class="text-muted font-weight-bold mb-0 small uppercase">Resolución</h6>
                        <h5 class="mb-0 font-weight-bold">{{ $numeracion->nombre ?? ($numeracion->prefijo ?? 'Sin activa') }}</h5>
                        <small class="text-muted">Rango: {{ $numeracion->inicioverdadero ?? $numeracion->inicio }} - {{ $numeracion->final }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 border-right mb-3 mb-md-0 text-center">
                <h6 class="text-muted font-weight-bold mb-1 small uppercase">Emitir Desde</h6>
                <div class="d-inline-block position-relative">
                    <input type="date" id="fecha_inicio_emision_dian" class="form-control form-control-sm border-0 bg-light rounded-pill px-3 py-1 shadow-none font-weight-bold" value="{{ $empresa->fecha_inicio_emision_dian }}" style="width: auto;">
                    <div id="save-date-feedback" class="position-absolute w-100 text-center" style="bottom: -20px; font-size: 0.7rem;"></div>
                </div>
            </div>
            <div class="col-md-2 border-right mb-3 mb-md-0 text-center">
                <h6 class="text-muted font-weight-bold mb-1 small uppercase">Vigencia</h6>
                @if($numeracion && $numeracion->hasta)
                    <h5 class="mb-0 font-weight-bold">{{ date('d M, Y', strtotime($numeracion->hasta)) }}</h5>
                    <small class="text-muted">{{ \Carbon\Carbon::parse($numeracion->hasta)->diffForHumans() }}</small>
                @else
                    <h5 class="mb-0 text-danger font-weight-bold">No definida</h5>
                @endif
            </div>
            <div class="col-md-2 text-center">
                <h6 class="text-muted font-weight-bold mb-0 small uppercase">Pendientes</h6>
                <h2 class="text-warning font-weight-bold mb-0" id="pendientes-header" style="font-size: 2.5rem;" title="Procesables: {{ $pendientesProcesables }} / Total: {{ $pendientesTotal }}">{{ $pendientesProcesables }}</h2>
                <small class="text-muted font-weight-bold uppercase" style="font-size: 0.65rem;">De {{ $pendientesTotal }} Totales</small>
            </div>
        </div>
    </div>


    {{-- ═══════════════════════════════════════════════════════════════
         ESTADO Y KPIs
         ═══════════════════════════════════════════════════════════════ --}}
    <div class="row" id="kpi-section" style="display:none;">
        <div class="col-lg-3 col-md-6">
            <div class="audit-card bg-gradient-blue">
                <div class="kpi-card">
                    <span class="kpi-label">Lote Total</span>
                    <span class="kpi-value" id="kpi-total">0</span>
                    <div class="icon"><i class="fas fa-boxes"></i></div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="audit-card bg-gradient-green">
                <div class="kpi-card">
                    <span class="kpi-label">Exitosas</span>
                    <span class="kpi-value" id="kpi-emitidas">0</span>
                    <div class="icon"><i class="fas fa-check-double"></i></div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="audit-card bg-gradient-red">
                <div class="kpi-card">
                    <span class="kpi-label">Fallidas</span>
                    <span class="kpi-value" id="kpi-fallidas">0</span>
                    <div class="icon"><i class="fas fa-times-circle"></i></div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="audit-card bg-gradient-gold">
                <div class="kpi-card">
                    <span class="kpi-label">Alertas</span>
                    <span class="kpi-value" id="kpi-alertas">0</span>
                    <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
            </div>
        </div>
    </div>


    {{-- Estado de ejecución alerta interactiva --}}
    <div class="row mb-4" id="estado-ejecucion-container">
        <div class="col-md-12">
            <div id="estado-msg" class="alert alert-light border-left-info shadow-sm p-3 mb-0" style="display: flex; align-items: center;">
                <div class="spinner-grow spinner-grow-sm text-info mr-3 d-none" id="estado-spinner" role="status"></div>
                <div id="estado-texto" class="font-weight-bold text-dark">Consultando estado del sistema...</div>
            </div>
        </div>
    </div>

    {{-- Barra de Progreso --}}
    <div class="row mb-4" id="progreso-section" style="display:none;">
        <div class="col-md-12">
            <div class="premium-container py-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <h6 class="font-weight-bold mb-0 text-dark uppercase small"><i class="fas fa-spinner fa-spin mr-2 text-primary"></i> Progreso de Emisión en Lote</h6>
                    <span id="progress-percentage-label" class="badge-premium bg-gradient-green text-white">0%</span>
                </div>
                <div class="progress progress-premium">
                    <div class="progress-bar progress-bar-premium" id="progress-bar" role="progressbar" style="width: 0%"></div>
                </div>
            </div>
        </div>
    </div>


    {{-- ═══════════════════════════════════════════════════════════════
         ALERTAS Y CONSOLA (2 COLUMNAS)
         ═══════════════════════════════════════════════════════════════ --}}
    <div class="row mb-4">
        {{-- Consola en tiempo real --}}
        <div class="col-lg-8 mb-3" id="detalle-section" style="display:none;">
            <div class="premium-container h-100 mb-0">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="font-weight-bold text-dark m-0"><i class="fas fa-terminal mr-2 text-primary"></i> Monitor de Eventos</h6>
                    <span class="badge badge-premium bg-light text-primary" id="console-status-text">ESPERANDO</span>
                </div>
                <div class="console-logger" id="detalle-tbody">
                    <div class="console-line">
                        <span class="console-timestamp">[{{ date('H:i:s') }}]</span>
                        <span>Listo para monitorear...</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Alertas de Numeración --}}
        <div class="col-lg-4 mb-3" id="alertas-section" style="display:none;">
            <div class="premium-container border-left border-warning h-100 mb-0">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="font-weight-bold text-warning m-0"><i class="fas fa-bell mr-2"></i> Alertas de Resolución</h6>
                    <i class="fas fa-exclamation-circle text-warning opacity-50"></i>
                </div>
                <div id="alertas-container">
                    {{-- Inyectado dinámicamente --}}
                </div>
            </div>
        </div>
    </div>


    {{-- ═══════════════════════════════════════════════════════════════
         FACTURAS PENDIENTES DE EMISIÓN (DataTables)
         ═══════════════════════════════════════════════════════════════ --}}
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="premium-container" style="padding: 0; overflow: hidden; border-top: 4px solid var(--warning);">
                <div class="p-4 bg-white border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="m-0 font-weight-bold text-dark"><i class="fas fa-clock mr-2 text-warning"></i> Facturas Pendientes de Emisión</h5>
                        <p class="text-muted small mb-0">Listado de facturas que esperan ser enviadas a la DIAN</p>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-premium" id="table-pendientes" style="width:100%">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Total</th>
                                <th>Válida para Cron</th>
                                <th class="text-right">Acción</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         HISTORIAL DE EJECUCIONES (DataTables)
         ═══════════════════════════════════════════════════════════════ --}}
    <div class="row mb-5">
        <div class="col-md-12">
            <div class="premium-container" style="padding: 0; overflow: hidden;">
                <div class="p-4 bg-white border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="m-0 font-weight-bold text-dark"><i class="fas fa-history mr-2 text-primary"></i> Historial de Ciclos de Emisión</h5>
                        <p class="text-muted small mb-0">Trazabilidad completa de las ejecuciones automáticas y manuales</p>
                    </div>
                    <div class="d-flex align-items-center">
                        <select id="filtro-estado" class="form-control form-control-sm border-0 bg-light rounded-pill px-3 mr-2 shadow-none" style="height: 38px;">
                            <option value="">Filtro: Todos</option>
                            <option value="completado">Completados</option>
                            <option value="parcial">Parciales</option>
                            <option value="error">Errores</option>
                            <option value="finalizado_incompleto">Incompletos</option>
                        </select>
                        <input type="date" id="filtro-desde" class="form-control form-control-sm border-0 bg-light rounded-pill px-3 shadow-none" style="height: 38px;">
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-premium" id="table-historial" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID Ciclo</th>
                                <th>Fecha y Hora</th>
                                <th>Duración</th>
                                <th>Estado</th>
                                <th>Progreso</th>
                                <th>Origen</th>
                                <th class="text-right">Acción</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL DETALLE PREMIUM --}}
<div class="modal fade" id="modalDetalle" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" style="max-width: 90%;" role="document">
        <div class="modal-content shadow-lg border-0 rounded-lg">
            <div class="modal-header bg-dark text-white border-0">
                <h5 class="modal-title font-weight-bold">
                    <i class="fas fa-search-plus mr-2"></i> Análisis del Lote #<span id="modal-log-id"></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body bg-light p-4">
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="p-3 bg-white rounded shadow-sm text-center">
                            <small class="text-muted d-block uppercase font-weight-bold">INICIO</small>
                            <span id="modal-inicio" class="h6 mb-0"></span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-white rounded shadow-sm text-center">
                            <small class="text-muted d-block uppercase font-weight-bold">FIN</small>
                            <span id="modal-fin" class="h6 mb-0"></span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-white rounded shadow-sm text-center border-left border-info" id="modal-status-card">
                            <small class="text-muted d-block uppercase font-weight-bold">ESTADO</small>
                            <span id="modal-estado" class="h6 mb-0"></span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-white rounded shadow-sm text-center">
                            <small class="text-muted d-block uppercase font-weight-bold">MÉTODO</small>
                            <span id="modal-creado" class="h6 mb-0"></span>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                         <h6 class="font-weight-bold mb-3"><i class="fas fa-comment-alt mr-1"></i> Feedback Post-Procesamiento</h6>
                         <p id="modal-obs" class="mb-0 text-muted"></p>
                    </div>
                </div>

                <div class="table-responsive rounded bg-white p-2">
                    <table class="table table-hover table-sm">
                        <thead class="thead-light">
                            <tr>
                                <th>Factura</th>
                                <th>Estado</th>
                                <th>CUFE / Motivo</th>
                                <th>Intentos</th>
                                <th>Ms</th>
                            </tr>
                        </thead>
                        <tbody id="modal-detalles-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function() {
    var csrfToken = $('meta[name="csrf-token"]').attr('content');
    var pollingInterval = null;
    var baseUrl = '{{ url('/empresa') }}/';

    // ─── DataTable Pendientes ───
    var dtPendientes = $('#table-pendientes').DataTable({
        processing: true,
        serverSide: true,
        ajax: baseUrl + 'api/cron-dian/pendientes',
        columns: [
            { data: 'codigo', name: 'codigo', className: 'font-weight-bold' },
            { data: 'fecha', name: 'fecha' },
            { data: 'nombre_cliente', name: 'contactos.nombre', render: function(d, t, row) {
                return '<strong>' + (row.nit_cliente || '') + '</strong><br><small class="text-muted">' + (d || 'Sin nombre') + '</small>';
            }},
            { data: 'total', name: 'total', render: function(d) { return '$ ' + d; } },
            { data: 'numeracion_match', name: 'numeracion_match', render: function(d) {
                var cls = d === 'SI' ? 'badge-success' : 'badge-danger';
                return '<span class="badge ' + cls + '">' + d + '</span>';
            }},
            { data: 'acciones', name: 'acciones', orderable: false, searchable: false, className: 'text-right' }
        ],
        order: [[1, 'desc']],
        pageLength: 5,
        dom: '<"top"f>rt<"bottom"lip><"clear">',
        language: { "url": "//cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json" }
    });

    // ─── DataTable Historial ───
    var dtHistorial = $('#table-historial').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: baseUrl + 'api/cron-dian/logs',
            data: function(d) {
                d.estado = $('#filtro-estado').val();
                d.creado_por = $('#filtro-creado').val();
                d.fecha_desde = $('#filtro-desde').val();
            }
        },
        columns: [
            { data: 'id', name: 'id', className: 'font-weight-bold' },
            { data: 'inicio_ejecucion', name: 'inicio_ejecucion', render: function(d) { return formatFecha(d); } },
            { data: 'duracion', name: 'duracion', orderable: false, className: 'text-muted' },
            { data: 'estado', name: 'estado', render: function(d) { return badgeEstado(d); } },
            { data: 'id', name: 'id', orderable: false, render: function(d, t, row) {
                var total = row.total_a_emitir || 1;
                var perc = (row.total_emitidas / total * 100).toFixed(0);
                return '<div class="progress" style="height: 6px; width: 100px; margin-bottom: 4px; border-radius: 10px; background: #f1f5f9;">'+
                        '<div class="progress-bar bg-success" style="width:'+ perc +'%; border-radius: 10px; background: var(--success-gradient) !important;"></div>'+
                       '</div>'+
                       '<small class="text-muted font-weight-bold">' + row.total_emitidas + ' de ' + row.total_a_emitir + '</small>';
            }},
            { data: 'creado_por', name: 'creado_por', render: function(d) {
                return d === 'manual' ? '<span class="badge-premium bg-light text-primary border"><i class="fas fa-user-edit mr-1"></i>Manual</span>' : '<span class="badge-premium bg-light text-muted border"><i class="fas fa-robot mr-1"></i>Auto</span>';
            }},
            { data: 'id', orderable: false, searchable: false, render: function(d) {
                return '<button class="btn btn-sm btn-outline-primary btn-ver-detalle border-0" style="border-radius: 10px;" data-id="'+d+'"><i class="fas fa-eye fa-lg"></i></button>';
            }}
        ],
        order: [[0, 'desc']],
        pageLength: 10,
        dom: '<"top"f>rt<"bottom"lip><"clear">',
        language: {
            "sProcessing":     "Procesando...",
            "sLengthMenu":     "Mostrar _MENU_ registros",
            "sZeroRecords":    "No se encontraron resultados",
            "sEmptyTable":     "Ningún dato disponible en esta tabla",
            "sInfo":           "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
            "sInfoEmpty":      "Mostrando registros del 0 al 0 de un total de 0 registros",
            "sInfoFiltered":   "(filtrado de un total de _MAX_ registros)",
            "sInfoPostFix":    "",
            "sSearch":         "Buscar:",
            "sUrl":            "",
            "sInfoThousands":  ",",
            "sLoadingRecords": "Cargando...",
            "oPaginate": {
                "sFirst":    "Primero",
                "sLast":     "Último",
                "sNext":     "Siguiente",
                "sPrevious": "Anterior"
            },
            "oAria": {
                "sSortAscending":  ": Activar para ordenar la columna de manera ascendente",
                "sSortDescending": ": Activar para ordenar la columna de manera descendente"
            },
            "buttons": {
                "copy": "Copiar",
                "colvis": "Visibilidad"
            }
        }
    });

    $('#filtro-estado, #filtro-desde').on('change', function() { dtHistorial.ajax.reload(); });

    // ─── Polling ───
    function fetchEstado() {
        $.getJSON(baseUrl + 'api/cron-dian/estado', function(data) {
            actualizarUI(data);
        }).fail(function() {
            $('#estado-texto').text('Servidor no responde...');
        });
    }

    function actualizarUI(data) {
        if (data.ejecucion_activa && data.log_actual) {
            $('#estado-spinner').removeClass('d-none');
            $('#estado-msg').removeClass('alert-light border-left-info alert-success alert-danger').addClass('alert-info');
            $('#estado-texto').html('Lote #' + data.log_actual.id + ' en proceso. Analizando facturas...');
            $('#console-status-text').text('EJECUTANDO...');

            $('#kpi-section').fadeIn();
            $('#kpi-total').text(data.log_actual.total_a_emitir);
            $('#kpi-emitidas').text(data.log_actual.total_emitidas);
            $('#kpi-fallidas').text(data.log_actual.total_fallidas);
            $('#kpi-alertas').text(data.log_actual.total_alertas_numeracion);

            $('#progreso-section').fadeIn();
            $('#progress-bar').css('width', data.progreso_porcentaje + '%');
            $('#progress-percentage-label').text(data.progreso_porcentaje + '%');

            if (data.detalles_actuales && data.detalles_actuales.length > 0) {
                $('#detalle-section').fadeIn();
                renderDetalles(data.detalles_actuales);
            }
            if (!pollingInterval) pollingInterval = setInterval(fetchEstado, 4000);
        } else {
            $('#estado-spinner').addClass('d-none');
            
            // Sincronizar switch de emisión
            if (data.emision_automatica != undefined) {
                $('#switch-emision').prop('checked', data.emision_automatica == 1);
            }

            if (data.emision_automatica == 0) {
                $('#estado-msg').attr('class', 'alert alert-warning border-left-warning shadow-sm p-3 mb-0');
                $('#estado-texto').html('<i class="fas fa-exclamation-triangle mr-2"></i> <strong>Emisión Automática Desactivada:</strong> El sistema no procesará facturas automáticamente hasta que habilites la opción.');
                $('#console-status-text').text('DESACTIVADO');
            } else if (data.ultima_ejecucion) {
                var ue = data.ultima_ejecucion;
                var cls = ue.estado === 'completado' ? 'alert-success border-left-success' : (ue.estado === 'error' ? 'alert-danger border-left-danger' : 'alert-warning border-left-warning');
                $('#estado-msg').attr('class', 'alert ' + cls + ' shadow-sm p-3 mb-0');
                $('#estado-texto').html('Ciclo #' + ue.id + ' finalizado (' + ue.estado + '). Facturas procesadas: ' + ue.total_emitidas + ' exitosas, ' + ue.total_fallidas + ' fallidas.');
                $('#console-status-text').text('INACTIVO');
            } else {
                $('#estado-msg').attr('class', 'alert alert-light border-left-secondary shadow-sm p-3 mb-0');
                $('#estado-texto').html('Sistema listo. No hay ejecuciones activas.');
            }
            $('#progreso-section').fadeOut();
            
            // Si no hay ejecución activa, bajar la frecuencia del polling
            if (pollingInterval) { 
                clearInterval(pollingInterval); 
                pollingInterval = setInterval(fetchEstado, 15000); 
            }
        }

        $('#pendientes-header').text(data.pendientes_procesables || 0);
        $('#pendientes-header').attr('title', 'Procesables: ' + (data.pendientes_procesables || 0) + ' / Total: ' + (data.pendientes_total || 0));
        if (typeof dtPendientes !== 'undefined') dtPendientes.ajax.reload(null, false);

        // Alertas
        if (data.alertas_numeracion && data.alertas_numeracion.length > 0) {
            $('#alertas-section').fadeIn();
            var html = '';
            data.alertas_numeracion.forEach(function(a) {
                html += '<div class="alert alert-light border border-warning shadow-sm mb-2 p-2" style="font-size:0.85rem">'+
                        '<strong><i class="fas fa-exclamation-triangle text-warning"></i> ' + (a.tipo_alerta === 'rango_superado' ? 'RANGO SUPERADO' : 'FECHA VENCIDA') + '</strong><br>'+
                        '<small class="text-muted">ID: '+ (a.numeracion_id || 'N/A') +' | Afectadas: '+ a.cantidad_facturas_afectadas +'</small>'+
                        '<div class="text-right mt-1"><button class="btn btn-xs btn-outline-success border-0 py-0 btn-resolver-alerta" data-id="'+a.id+'">RESOLVER</button></div>'+
                        '</div>';
            });
            $('#alertas-container').html(html);
        } else { $('#alertas-section').fadeOut(); }
    }

    function renderDetalles(detalles) {
        var html = '';
        detalles.forEach(function(d) {
            var timestamp = new Date().toLocaleTimeString();
            html = '<div class="console-line">'+
                    '<span class="console-timestamp">['+timestamp+']</span>'+
                    '<span class="console-status status-'+d.estado+'">'+d.estado+'</span>'+
                    '<span>Factura: <strong>'+d.factura_codigo+'</strong> - '+ (d.mensaje || 'Procesando...') +'</span>'+
                    '</div>' + html;
        });
        $('#detalle-tbody').html(html);
    }

    // ─── Acciones ───
    $('#btn-ejecutar').on('click', function() {
        Swal.fire({
            title: 'Ejecutar Emisión',
            text: '¿Deseas iniciar el lote de emisión de facturas DIAN?',
            imageUrl: 'https://cdn-icons-png.flaticon.com/512/3067/3067260.png',
            imageWidth: 80,
            showCancelButton: true,
            confirmButtonColor: '#1cc88a',
            confirmButtonText: 'Sí, despegar',
            cancelButtonText: 'Ahora no',
            background: '#fff',
            customClass: { popup: ['rounded-xl'] }
        }).then(function(result) {
            if (result.value) {
                $('#btn-ejecutar').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Iniciando...');
                $.ajax({
                    url: baseUrl + 'api/cron-dian/ejecutar-manual',
                    type: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    success: function(res) { 
                        if (res.status == 'inactivo') {
                            Swal.fire({
                                title: 'Emisión Desactivada',
                                text: 'La opción de emisión automática está desactivada. Por favor actívala en el panel superior para poder procesar facturas.',
                                icon: 'warning',
                                confirmButtonColor: '#667eea'
                            });
                        } else {
                            fetchEstado(); 
                            dtHistorial.ajax.reload(); 
                            if (typeof dtPendientes !== 'undefined') dtPendientes.ajax.reload();
                        }
                    },
                    error: function() { Swal.fire('Error', 'No se pudo iniciar el proceso.', 'error'); },
                    complete: function() { $('#btn-ejecutar').prop('disabled', false).html('<i class="fas fa-rocket mr-1"></i> Ejecutar Ahora'); }
                });
            }
        });
    });

    // ─── Guardar Fecha Desde ───
    $('#fecha_inicio_emision_dian').on('change', function() {
        var fecha = $(this).val();
        $('#save-date-feedback').html('<i class="fas fa-spinner fa-spin text-info"></i><span class="ml-1 text-info">Guardando...</span>').show();
        $.ajax({
            url: baseUrl + 'api/cron-dian/configurar-fecha',
            type: 'POST',
            data: { fecha_inicio: fecha },
            headers: { 'X-CSRF-TOKEN': csrfToken },
            success: function(res) {
                $('#save-date-feedback').html('<i class="fas fa-check text-success"></i><span class="ml-1 text-success">Actualizado</span>');
                setTimeout(function(){ $('#save-date-feedback').fadeOut(500, function(){ $(this).html('').show(); }); }, 2000);
                fetchEstado(); // Refresca pendientes
            },
            error: function() {
                $('#save-date-feedback').html('<i class="fas fa-times text-danger"></i><span class="ml-1 text-danger">Error</span>');
            }
        });
    });

    $(document).on('click', '.btn-ver-detalle', function() {
        var logId = $(this).data('id');
        $.getJSON(baseUrl + 'api/cron-dian/detalle/' + logId, function(data) {
            $('#modal-log-id').text(data.log.id);
            $('#modal-inicio').text(formatFechaSimple(data.log.inicio_ejecucion));
            $('#modal-fin').text(data.log.fin_ejecucion ? formatFechaSimple(data.log.fin_ejecucion) : 'N/A');
            $('#modal-estado').html(badgeEstado(data.log.estado));
            $('#modal-creado').text(data.log.creado_por.toUpperCase());
            $('#modal-obs').text(data.log.observaciones || 'Sin incidencias reportadas.');

            var html = '';
            data.detalles.forEach(function(d) {
                html += '<tr>'+
                        '<td class="font-weight-bold">'+d.factura_codigo+'</td>'+
                        '<td>'+badgeEstado(d.estado)+'</td>'+
                        '<td><small class="text-muted">'+(d.cufe ? d.cufe : (d.mensaje || '-'))+'</small></td>'+
                        '<td>'+d.intento+'</td>'+
                        '<td>'+(d.tiempo_respuesta_ms ? d.tiempo_respuesta_ms+'ms' : '-')+'</td>'+
                        '</tr>';
            });
            $('#modal-detalles-tbody').html(html);
            $('#modalDetalle').modal('show');
        });
    });

    $(document).on('click', '.btn-resolver-alerta', function() {
        var id = $(this).data('id');
        var card = $(this).closest('.alert');
        $.ajax({
            url: baseUrl + 'api/cron-dian/resolver-alerta/' + id,
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            success: function() { card.fadeOut(); }
        });
    });

    function badgeEstado(estado) {
        if (!estado) {
            return '<span class="badge-premium bg-light text-dark border">INCOMPLETO</span>';
        }

        var cls = { 
            completado: 'bg-gradient-green text-white', 
            parcial: 'bg-gradient-gold text-white', 
            error: 'bg-gradient-red text-white', 
            ejecutando: 'bg-gradient-blue text-white', 
            emitida: 'bg-gradient-green text-white', 
            fallida: 'bg-gradient-red text-white',
            finalizado_incompleto: 'bg-gradient-red text-white'
        };

        var texto = estado.toUpperCase().replace(/_/g, ' ');
        if (estado === 'finalizado_incompleto') {
            texto = 'INCOMPLETO';
        }

        return '<span class="badge-premium '+(cls[estado]||'bg-light text-dark border')+'">'+texto+'</span>';
    }

    function formatFecha(str) {
        if (!str) return '-';
        var d = new Date(str);
        return '<span class="text-dark font-weight-bold">' + d.toLocaleDateString() + '</span> <span class="text-muted">' + d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) + '</span>';
    }
    function formatFechaSimple(str) {
        if (!str) return '-';
        return new Date(str).toLocaleString();
    }

    // ─── Toggle Emisión Automática ───
    $('#switch-emision').on('change', function() {
        var status = $(this).is(':checked') ? 1 : 0;
        var label = $(this).next('label');
        
        $.ajax({
            url: baseUrl + 'api/cron-dian/toggle-emision',
            type: 'POST',
            data: { status: status },
            headers: { 'X-CSRF-TOKEN': csrfToken },
            success: function(res) {
                if (res.status == 'ok') {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    });
                    Toast.fire({
                        icon: 'success',
                        title: res.mensaje
                    });
                    fetchEstado(); // Actualizar banners
                }
            },
            error: function() {
                Swal.fire('Error', 'No se pudo actualizar la configuración.', 'error');
                // Revertir switch
                $('#switch-emision').prop('checked', !status);
            }
        });
    });

    $('#btn-refresh').on('click', function() { 
        fetchEstado(); 
        dtHistorial.ajax.reload(); 
        if (typeof dtPendientes !== 'undefined') dtPendientes.ajax.reload();
    });
    fetchEstado(); 
    pollingInterval = setInterval(fetchEstado, 15000);
});
</script>
@endsection
