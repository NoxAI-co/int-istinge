@extends('layouts.app')

@section('content')
<style>
    /* Google Fonts */
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap');

    :root {
        --primary: #4e73df;
        --success: #1cc88a;
        --info: #36b9cc;
        --warning: #f6c23e;
        --danger: #e74a3b;
        --dark: #2e3b4e;
        --glass: rgba(255, 255, 255, 0.9);
        --glass-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
    }

    body {
        font-family: 'Outfit', sans-serif;
        background-color: #f8f9fc;
    }

    /* Premium Cards */
    .card-premium {
        background: var(--glass);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 15px;
        box-shadow: var(--glass-shadow);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        margin-bottom: 1.5rem;
    }

    .card-premium:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 40px 0 rgba(31, 38, 135, 0.2);
    }

    .card-title-premium {
        font-weight: 700;
        color: var(--dark);
        letter-spacing: -0.5px;
    }

    /* KPI Section */
    .kpi-card {
        padding: 1.5rem;
        border-radius: 15px;
        color: white;
        overflow: hidden;
        position: relative;
    }

    .kpi-card i {
        position: absolute;
        right: -10px;
        bottom: -10px;
        font-size: 5rem;
        opacity: 0.1;
        transform: rotate(-15deg);
    }

    .bg-gradient-primary { background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); }
    .bg-gradient-success { background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%); }
    .bg-gradient-danger { background: linear-gradient(135deg, #e74a3b 0%, #be2617 100%); }
    .bg-gradient-warning { background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%); }

    .kpi-value {
        font-size: 2.5rem;
        font-weight: 700;
        line-height: 1;
    }

    .kpi-label {
        font-size: 0.9rem;
        text-transform: uppercase;
        font-weight: 600;
        opacity: 0.8;
    }

    /* Progress Section */
    .progress-premium {
        height: 35px;
        border-radius: 50px;
        background-color: #eaecf4;
        box-shadow: inset 0 2px 5px rgba(0,0,0,0.1);
        overflow: hidden;
    }

    .progress-bar-premium {
        border-radius: 50px;
        font-weight: 700;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        transition: width 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    /* Console Logger Style */
    .console-logger {
        background: #1e1e1e;
        color: #d4d4d4;
        border-radius: 10px;
        font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
        padding: 15px;
        max-height: 400px;
        overflow-y: auto;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }

    .console-line {
        margin-bottom: 5px;
        border-bottom: 1px solid #333;
        padding-bottom: 5px;
        font-size: 0.85rem;
    }

    .console-timestamp { color: #888; margin-right: 10px; }
    .console-status { font-weight: bold; text-transform: uppercase; margin-right: 10px; }
    .status-emitida { color: #4ec9b0; }
    .status-fallida { color: #f44747; }
    .status-pendiente { color: #ce9178; }
    .status-omitida_numeracion { color: #dcdcaa; }

    /* Badge Styles */
    .badge-pill-custom {
        padding: 0.4em 1em;
        border-radius: 50px;
        font-weight: 600;
    }

    /* Custom Scrollbar */
    ::-webkit-scrollbar { width: 8px; }
    ::-webkit-scrollbar-track { background: #f1f1f1; }
    ::-webkit-scrollbar-thumb { background: #ccc; border-radius: 10px; }
    ::-webkit-scrollbar-thumb:hover { background: #aaa; }

    /* Floating UI Elements */
    .btn-floating {
        border-radius: 50px;
        padding: 0.6rem 1.5rem;
        font-weight: 600;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }

    .btn-floating:hover {
        transform: translateY(-2px) scale(1.02);
        box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    }
</style>

<div class="container-fluid">
    {{-- ═══════════════════════════════════════════════════════════════
         PANEL DE CABECERA
         ═══════════════════════════════════════════════════════════════ --}}
    <div class="row align-items-center mb-4">
        <div class="col-md-7">
            <h2 class="card-title-premium mb-1">Emisiones Automáticas DIAN</h2>
            <p class="text-muted"><i class="fas fa-info-circle"></i> Gestiona y monitorea el proceso de facturación electrónica en tiempo real.</p>
        </div>
        <div class="col-md-5 text-right">
            <button id="btn-ejecutar" class="btn btn-success btn-floating mr-2">
                <i class="fas fa-rocket mr-1"></i> Ejecutar Ahora
            </button>
            <button id="btn-refresh" class="btn btn-white btn-floating border">
                <i class="fas fa-sync-alt mr-1"></i> Sincronizar
            </button>
        </div>
    </div>

    {{-- Info Empresa & Numeración --}}
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card card-premium bg-white">
                <div class="card-body p-4">
                    <div class="row text-center text-md-left">
                        <div class="col-md-3 border-right mb-3 mb-md-0">
                            <h6 class="text-muted font-weight-bold mb-2">EMPRESA</h6>
                            <h5 class="mb-1 text-truncate" title="{{ $empresa->nombre ?? 'N/A' }}">{{ $empresa->nombre ?? 'N/A' }}</h5>
                            <small class="text-primary font-weight-bold">NIT: {{ $empresa->nit ?? 'N/A' }}</small>
                        </div>
                        <div class="col-md-3 border-right mb-3 mb-md-0">
                            <h6 class="text-muted font-weight-bold mb-2">RESOLUCIÓN</h6>
                            <h5 class="mb-1">{{ $numeracion->nombre ?? ($numeracion->prefijo ?? 'Sin activa') }}</h5>
                            <small class="text-muted">Rango: {{ $numeracion->inicioverdadero ?? $numeracion->inicio }} - {{ $numeracion->final }}</small>
                        </div>
                        <div class="col-md-2 border-right mb-3 mb-md-0">
                            <h6 class="text-muted font-weight-bold mb-2">VIGENCIA</h6>
                            @if($numeracion && $numeracion->desde && $numeracion->hasta)
                                <h5 class="mb-1">{{ date('d M, Y', strtotime($numeracion->hasta)) }}</h5>
                                <small class="text-muted">Expira en {{ \Carbon\Carbon::parse($numeracion->hasta)->diffForHumans() }}</small>
                            @else
                                <h5 class="mb-1 text-danger">No configurada</h5>
                            @endif
                        </div>
                        <div class="col-md-2 border-right mb-3 mb-md-0">
                            <h6 class="text-muted font-weight-bold mb-2">EMITIR DESDE</h6>
                            <input type="date" id="fecha_inicio_emision_dian" class="form-control form-control-sm border-0 bg-light rounded shadow-none w-100" value="{{ $empresa->fecha_inicio_emision_dian }}" title="Solo se emitirán facturas de esta fecha en adelante" style="max-width: 140px; margin: 0 auto 0 0;">
                            <small class="text-muted d-block mt-1" id="save-date-feedback" style="min-height: 18px;"></small>
                        </div>
                        <div class="col-md-2 text-center">
                            <h6 class="text-muted font-weight-bold mb-2">PENDIENTES</h6>
                            <h2 class="text-warning font-weight-bold mb-0" id="pendientes-header">{{ $pendientes }}</h2>
                            <small class="text-muted">Facturas tipo=2</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         ESTADO Y KPIs
         ═══════════════════════════════════════════════════════════════ --}}
    <div class="row mb-3" id="kpi-section" style="display:none;">
        <div class="col-md-3 mb-3">
            <div class="card kpi-card bg-gradient-primary shadow">
                <div class="kpi-label">Lote Total</div>
                <div class="kpi-value" id="kpi-total">0</div>
                <i class="fas fa-boxes"></i>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card kpi-card bg-gradient-success shadow">
                <div class="kpi-label">Exitosas</div>
                <div class="kpi-value" id="kpi-emitidas">0</div>
                <i class="fas fa-check-double"></i>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card kpi-card bg-gradient-danger shadow">
                <div class="kpi-label">Fallidas</div>
                <div class="kpi-value" id="kpi-fallidas">0</div>
                <i class="fas fa-times-circle"></i>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card kpi-card bg-gradient-warning shadow">
                <div class="kpi-label">Alertas</div>
                <div class="kpi-value" id="kpi-alertas">0</div>
                <i class="fas fa-exclamation-triangle"></i>
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
            <div class="card card-premium">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="card-title-premium mb-0"><i class="fas fa-sync fa-spin mr-2"></i> PROGRESO DE EMISIÓN</h6>
                        <span id="progress-percentage-label" class="badge badge-success">0%</span>
                    </div>
                    <div class="progress progress-premium">
                        <div class="progress-bar progress-bar-premium bg-success progress-bar-striped progress-bar-animated"
                             id="progress-bar" role="progressbar" style="width: 0%">
                        </div>
                    </div>
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
            <div class="card card-premium h-100">
                <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-terminal mr-1"></i> MONITOR EN VIVO</h6>
                    <small class="text-muted" id="console-status-text">ESPERANDO...</small>
                </div>
                <div class="card-body pt-0">
                    <div class="console-logger" id="detalle-tbody">
                        <div class="console-line">Ready to monitor...</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Alertas de Numeración --}}
        <div class="col-lg-4 mb-3" id="alertas-section" style="display:none;">
            <div class="card card-premium border-left-warning h-100">
                <div class="card-header bg-transparent border-0">
                    <h6 class="m-0 font-weight-bold text-warning"><i class="fas fa-bell mr-1"></i> ALERTAS DE RESOLUCIÓN</h6>
                </div>
                <div class="card-body pt-0">
                    <div id="alertas-container">
                        {{-- Inyectado dinámicamente --}}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         HISTORIAL DE EJECUCIONES (DataTables)
         ═══════════════════════════════════════════════════════════════ --}}
    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card card-premium shadow">
                <div class="card-header bg-white border-0 py-3">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="m-0 font-weight-bold text-dark"><i class="fas fa-history mr-2"></i> Historial de Ciclos</h5>
                        </div>
                        <div class="col text-right">
                             <div class="form-row justify-content-end">
                                <div class="col-auto">
                                    <select id="filtro-estado" class="form-control form-control-sm border-0 bg-light rounded-pill px-3 shadow-none">
                                        <option value="">Todos los estados</option>
                                        <option value="completado">Completados</option>
                                        <option value="parcial">Parciales</option>
                                        <option value="error">Errores</option>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <input type="date" id="filtro-desde" class="form-control form-control-sm border-0 bg-light rounded-pill px-3 shadow-none">
                                </div>
                             </div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-custom" id="table-historial" style="width:100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>FECHA Y HORA</th>
                                    <th>DURACIÓN</th>
                                    <th>ESTADO</th>
                                    <th>METRICAS</th>
                                    <th>ORIGEN</th>
                                    <th></th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL DETALLE PREMIUM --}}
<div class="modal fade" id="modalDetalle" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
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
<script>
$(function() {
    var csrfToken = $('meta[name="csrf-token"]').attr('content');
    var pollingInterval = null;
    var baseUrl = window.location.origin + '/empresa/';

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
                return '<div class="progress" style="height: 5px; width: 80px; margin-bottom: 2px;">'+
                        '<div class="progress-bar bg-success" style="width:'+ perc +'%"></div>'+
                       '</div>'+
                       '<small class="text-muted">' + row.total_emitidas + '/' + row.total_a_emitir + '</small>';
            }},
            { data: 'creado_por', name: 'creado_por', render: function(d) {
                return d === 'manual' ? '<span class="badge badge-light border text-info"><i class="fas fa-user-edit mr-1"></i>MAN</span>' : '<span class="badge badge-light border text-muted"><i class="fas fa-robot mr-1"></i>AUTO</span>';
            }},
            { data: 'id', orderable: false, searchable: false, render: function(d) {
                return '<button class="btn btn-sm btn-link text-primary btn-ver-detalle" data-id="'+d+'"><i class="fas fa-chevron-right fa-lg"></i></button>';
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
            if (data.ultima_ejecucion) {
                var ue = data.ultima_ejecucion;
                var cls = ue.estado === 'completado' ? 'alert-success border-left-success' : (ue.estado === 'error' ? 'alert-danger border-left-danger' : 'alert-warning border-left-warning');
                $('#estado-msg').attr('class', 'alert ' + cls + ' shadow-sm p-3 mb-0');
                $('#estado-texto').html('Ciclo finalizado (' + ue.estado + '). Facturas procesadas: ' + ue.total_emitidas + ' exitosas, ' + ue.total_fallidas + ' fallidas.');
                $('#console-status-text').text('INACTIVO');
            } else {
                $('#estado-msg').attr('class', 'alert alert-light border-left-secondary shadow-sm p-3 mb-0');
                $('#estado-texto').html('Sistema listo. No hay ejecuciones activas.');
            }
            $('#progreso-section').fadeOut();
            if (pollingInterval) { clearInterval(pollingInterval); pollingInterval = null; }
        }

        $('#pendientes-header').text(data.pendientes_total);

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
            customClass: { popup: 'rounded-xl' }
        }).then(function(result) {
            if (result.value) {
                $('#btn-ejecutar').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Iniciando...');
                $.ajax({
                    url: baseUrl + 'api/cron-dian/ejecutar-manual',
                    type: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    success: function() { fetchEstado(); dtHistorial.ajax.reload(); },
                    error: function() { Swal.fire('Error', 'No se pudo iniciar.', 'error'); },
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
        var cls = { completado: 'success', parcial: 'warning', error: 'danger', ejecutando: 'info', emitida: 'success', fallida: 'danger' };
        return '<span class="badge badge-pill badge-pill-custom badge-'+(cls[estado]||'secondary')+'">'+estado.toUpperCase()+'</span>';
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

    $('#btn-refresh').on('click', function() { fetchEstado(); dtHistorial.ajax.reload(); });
    fetchEstado(); setInterval(fetchEstado, 10000);
});
</script>
@endsection
