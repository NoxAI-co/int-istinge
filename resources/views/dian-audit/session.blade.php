@extends('layouts.app')

@section('content')
<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .session-header {
        background: white;
        padding: 25px;
        border-radius: 20px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        margin-bottom: 30px;
        border-left: 5px solid #667eea;
    }

    .kpi-mini {
        background: white;
        border-radius: 15px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 4px 15px rgba(0,0,0,0.04);
        transition: transform 0.2s ease;
        border: 1px solid #f1f5f9;
    }

    .kpi-mini:hover {
        transform: translateY(-3px);
    }

    .kpi-mini .value {
        font-size: 1.5rem;
        font-weight: 800;
        display: block;
    }

    .kpi-mini .label {
        font-size: 0.75rem;
        text-transform: uppercase;
        color: #94a3b8;
        letter-spacing: 1px;
    }

    .integrity-container {
        background: #f8fafc;
        border-radius: 12px;
        padding: 15px;
        margin-top: 20px;
    }

    .table-container-premium {
        background: white;
        border-radius: 20px;
        padding: 25px;
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
    }

    .contract-card {
        border: 2px solid #f1f5f9;
        border-radius: 12px;
        padding: 12px;
        margin: 5px;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        flex: 1;
        min-width: 200px;
    }
    .contract-card:hover {
        border-color: #667eea;
        background: #f8fafc;
    }
    .contract-card.selected {
        border-color: #667eea;
        background: #f0f4ff;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
    }
    .contract-card.recommended {
        border-color: #2af598;
    }
    .recommendation-badge {
        position: absolute;
        top: -10px;
        right: 10px;
        background: #2af598 !important;
        color: white !important;
        font-size: 0.65rem;
        padding: 2px 8px;
        border-radius: 10px;
        font-weight: 700;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .btn-filter-group .btn {
        border-radius: 10px;
        margin-right: 5px;
        font-weight: 600;
        padding: 8px 20px;
        font-size: 0.85rem;
    }

    .modal-premium {
        border-radius: 25px;
        overflow: hidden;
        border: none;
    }

    .modal-premium .modal-header {
        background: var(--primary-gradient);
        color: white;
        border: none;
        padding: 25px;
    }

    .comparison-box {
        background: #f8fafc;
        border-radius: 15px;
        padding: 20px;
        height: 100%;
        border: 1px solid #e2e8f0;
    }
</style>

<div class="container-fluid">
    <!-- Breadcrumb-like Header -->
    <div class="session-header d-flex justify-content-between align-items-center">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb bg-transparent p-0 mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('auditoria.dian.index') }}">Auditoría</a></li>
                    <li class="breadcrumb-item active">Detalle</li>
                </ol>
            </nav>
            <h2 class="font-weight-bold text-dark mb-0">Sesión: {{ $session->periodo }}</h2>
            <small class="text-muted">Procesado el {{ $session->created_at->format('d/m/Y H:i') }} por {{ $session->user->nombres }}</small>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('auditoria.dian.pdf', $session->id) }}" class="btn btn-outline-danger btn-round mr-2">
                <i class="fas fa-file-pdf mr-1"></i> PDF Discrepancias
            </a>
            <a href="{{ route('auditoria.dian.logs', $session->id) }}" class="btn btn-outline-info btn-round mr-2">
                <i class="fas fa-history mr-1"></i> Historial Cambios
            </a>
            <a href="{{ route('auditoria.dian.index') }}" class="btn btn-light btn-round">
                <i class="fas fa-arrow-left mr-1"></i> Volver
            </a>
        </div>
    </div>

    <!-- Summary KPIs Row -->
    <div class="row mb-4">
        <div class="col-md-2 mb-2">
            <div class="kpi-mini clickable-filter" data-status="all" style="cursor:pointer">
                <span class="value text-dark">{{ $session->total_records }}</span>
                <span class="label">Total</span>
            </div>
        </div>
        <div class="col-md-2 mb-2">
            <div class="kpi-mini border-success clickable-filter" data-status="matched" style="cursor:pointer">
                <span class="value text-success">{{ $session->matched }}</span>
                <span class="label">Correctos</span>
            </div>
        </div>
        <div class="col-md-2 mb-2">
            <div class="kpi-mini border-danger clickable-filter" data-status="discrepancy" style="cursor:pointer">
                <span class="value text-danger" id="session_discrepancies">{{ $session->discrepancies }}</span>
                <span class="label">Discrepancias</span>
            </div>
        </div>
        <div class="col-md-2 mb-2">
            <div class="kpi-mini border-primary clickable-filter" data-status="corrected" style="cursor:pointer">
                <span class="value text-primary" id="session_corrected">{{ $session->corrected }}</span>
                <span class="label">Corregidos</span>
            </div>
        </div>
        <div class="col-md-2 mb-2">
            <div class="kpi-mini border-secondary clickable-filter" data-status="not_found" style="cursor:pointer">
                <span class="value text-secondary" id="session_not_found">{{ $session->not_found }}</span>
                <span class="label">No Encontrados</span>
            </div>
        </div>
        <div class="col-md-12 mt-2">
            <div class="integrity-container">
                <div class="d-flex justify-content-between mb-2">
                    <span class="font-weight-bold small text-uppercase">Integridad General</span>
                    <span class="font-weight-bold text-success" id="session_percentage">{{ $session->porcentaje_ok }}%</span>
                </div>
                <div class="progress progress-sm" style="height: 10px; border-radius: 5px;">
                    <div class="progress-bar bg-success" id="session_progress_bar" role="progressbar" style="width: {{ $session->porcentaje_ok }}%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table Section -->
    <div class="table-container-premium">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="btn-group btn-filter-group" role="group">
                <button type="button" class="btn btn-dark active btn-filter" data-status="all">Todos</button>
                <button type="button" class="btn btn-outline-success btn-filter" data-status="matched">Correctos</button>
                <button type="button" class="btn btn-outline-danger btn-filter" data-status="discrepancy">Discrepancias</button>
                <button type="button" class="btn btn-outline-primary btn-filter" data-status="corrected">Corregidos</button>
                <button type="button" class="btn btn-outline-secondary btn-filter" data-status="not_found">No Encontrados</button>
            </div>
            <div class="text-muted small"><i class="fas fa-info-circle mr-1"></i> Click en las columnas para ordenar</div>
        </div>

        <div class="table-responsive">
            <table id="table_records" class="table table-hover" style="width: 100%;">
                <thead>
                    <tr>
                        <th title="Combinación de Prefijo y Número de Folio oficial de la DIAN">Folio/Prefijo <i class="fas fa-question-circle small text-muted"></i></th>
                        <th title="Código Único de Factura Electrónica">CUFE <i class="fas fa-question-circle small text-muted"></i></th>
                        <th title="Fecha en la que se emitió el documento según la DIAN">Fecha <i class="fas fa-question-circle small text-muted"></i></th>
                        <th title="Datos del cliente receptor registrados en la DIAN">Receptor DIAN <i class="fas fa-question-circle small text-muted"></i></th>
                        <th title="Datos del cliente vinculado a esta factura en su sistema interno">Receptor Sistema <i class="fas fa-question-circle small text-muted"></i></th>
                        <th title="Valor total de la operación en pesos colombianos">Monto <i class="fas fa-question-circle small text-muted"></i></th>
                        <th title="Estado de la conciliación: Correcto, Discrepancia o Corregido">Estado <i class="fas fa-question-circle small text-muted"></i></th>
                        <th>Acción</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<!-- Premium Modal -->
<div class="modal fade" id="modalCorreccion" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content modal-premium">
            <div class="modal-header">
                <div>
                    <h4 class="modal-title font-weight-bold">Corrección Técnica</h4>
                    <p class="mb-0 opacity-75 small">Sincronización de cliente para Folio: <span id="folio_modal" class="font-weight-bold"></span></p>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="record_id_input">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="comparison-box border-success-light">
                            <h6 class="text-success font-weight-bold mb-3"><i class="fas fa-university mr-2"></i> DATA OFICIAL DIAN</h6>
                            <div class="mb-2">
                                <small class="text-muted d-block">Identificación</small>
                                <span id="nit_dian_modal" class="font-weight-bold h6"></span>
                            </div>
                            <div>
                                <small class="text-muted d-block">Razón Social</small>
                                <span id="nombre_dian_modal" class="font-weight-bold"></span>
                            </div>
                            <div class="mt-2">
                                <small class="text-muted d-block">Fecha Emisión DIAN</small>
                                <span id="fecha_dian_modal" class="font-weight-bold text-primary"></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="comparison-box border-danger-light">
                            <h6 class="text-danger font-weight-bold mb-3"><i class="fas fa-desktop mr-2"></i> DATA EN SISTEMA</h6>
                            <div class="mb-2">
                                <small class="text-muted d-block">Identificación</small>
                                <span id="nit_sistema_modal" class="font-weight-bold h6"></span>
                            </div>
                            <div>
                                <small class="text-muted d-block">Razón Social</small>
                                <span id="nombre_sistema_modal" class="font-weight-bold"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="form-group mb-4">
                    <label class="font-weight-bold text-dark"><i class="fas fa-user-check text-primary mr-1"></i> Asignar Cliente Correcto</label>
                    <select id="cliente_id_nuevo" class="form-control select2" style="width: 100%;"></select>
                </div>

                <div id="contratos_container" class="mb-4" style="display: none;">
                    <label class="font-weight-bold text-dark"><i class="fas fa-file-signature text-primary mr-1"></i> Vincular a Contrato</label>
                    <div id="contratos_list" class="row no-gutters">
                        <!-- Se poblará dinámicamente -->
                    </div>
                    <input type="hidden" id="contrato_id_nuevo">
                </div>

                <div class="form-group mb-4">
                    <label class="font-weight-bold text-dark"><i class="fas fa-comment-alt text-primary mr-1"></i> Justificación de la Enmienda</label>
                    <textarea id="motivo_correccion" class="form-control" rows="3" placeholder="Explique la razón de la inconsistencia (mín. 20 caracteres)..." style="border-radius: 12px;"></textarea>
                </div>

                <div class="alert alert-warning border-0 p-3" style="border-radius: 15px; background: #fffcf0;">
                    <div class="d-flex">
                        <i class="fas fa-info-circle text-warning mr-3 mt-1" style="font-size: 1.2rem;"></i>
                        <div class="small text-warning-darker">
                            <b>Nota de Integridad:</b> Esta modificación generará un log de auditoría inmutable vinculando su cuenta de usuario a este cambio.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light p-4">
                <button type="button" class="btn btn-link text-muted" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger btn-round px-4 py-2" onclick="aplicarCorreccion()">
                    <i class="fas fa-check-circle mr-2"></i> Aplicar Cambio Permanente
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Premium Modal Crear Factura -->
<div class="modal fade" id="modalCrearFactura" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content modal-premium">
            <div class="modal-header bg-success">
                <div>
                    <h4 class="modal-title font-weight-bold">Crear Factura Faltante</h4>
                    <p class="mb-0 opacity-75 small text-white">Generación de documento a partir de Folio: <span id="folio_crear_modal" class="font-weight-bold"></span></p>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="record_id_crear_input">
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <div class="comparison-box border-success-light">
                            <h6 class="text-success font-weight-bold mb-3"><i class="fas fa-university mr-2"></i> DATA OFICIAL DIAN A VINCULAR</h6>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <small class="text-muted d-block">Identificación</small>
                                    <span id="nit_dian_crear_modal" class="font-weight-bold h6"></span>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block">Razón Social</small>
                                    <span id="nombre_dian_crear_modal" class="font-weight-bold"></span>
                                </div>
                                <div class="col-md-6 mt-2">
                                    <small class="text-muted d-block">Fecha Emisión</small>
                                    <span id="fecha_dian_crear_modal" class="font-weight-bold text-primary"></span>
                                </div>
                                <div class="col-md-6 mt-2">
                                    <small class="text-muted d-block">Monto Reportado</small>
                                    <span id="monto_dian_crear_modal" class="font-weight-bold text-danger"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="form-group mb-4">
                    <label class="font-weight-bold text-dark"><i class="fas fa-user-check text-primary mr-1"></i> Seleccionar Cliente del Sistema</label>
                    <select id="cliente_id_crear" class="form-control select2" style="width: 100%;"></select>
                </div>

                <div id="contratos_crear_container" class="mb-4" style="display: none;">
                    <label class="font-weight-bold text-dark"><i class="fas fa-file-signature text-primary mr-1"></i> Seleccionar Contrato para los Ítems</label>
                    <div id="contratos_crear_list" class="row no-gutters">
                        <!-- Se poblará dinámicamente -->
                    </div>
                    <input type="hidden" id="contrato_id_crear">
                </div>

                <div class="alert alert-info border-0 p-3" style="border-radius: 15px; background: #f0f7ff;">
                    <div class="d-flex">
                        <i class="fas fa-info-circle text-info mr-3 mt-1" style="font-size: 1.2rem;"></i>
                        <div class="small text-info-darker">
                            <b>Acción a realizar:</b> Se creará una factura electrónica con estado "Abierta" (estatus=1) y "Emitida", asociada al CUFE indicado por la DIAN. Los ítems de la factura serán copiados del contrato que seleccione.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light p-4">
                <button type="button" class="btn btn-link text-muted" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success btn-round px-4 py-2" onclick="aplicarCreacionFactura()">
                    <i class="fas fa-plus-circle mr-2"></i> Confirmar Creación
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('js/dian-audit.js') }}"></script>
<script>
    var currentStatus = 'all';
    window.dianTable = null; // Definir globalmente para dian-audit.js
    window.auditBaseUrl = "{{ route('auditoria.dian.index') }}";

    $(document).ready(function() {
        $('[title]').tooltip(); // Inicializar tooltips de Bootstrap
        
        window.dianTable = $('#table_records').DataTable({
            processing: true,
            serverSide: true,
            pageLength: 50,
            ajax: {
                url: "{{ route('auditoria.dian.datatables', $session->id) }}",
                data: function(d) {
                    d.status = currentStatus;
                }
            },
            columns: [
                { data: 'folio', name: 'folio' },
                { data: 'cufe', name: 'cufe' },
                { data: 'fecha_emision', name: 'fecha_emision' },
                { data: 'receptor_dian', name: 'receptor_dian', orderable: false },
                { data: 'receptor_sistema', name: 'receptor_sistema', orderable: false },
                { data: 'total', name: 'total' },
                { data: 'status', name: 'status' },
                { data: 'acciones', name: 'acciones', orderable: false, searchable: false }
            ],
            language: {
                url: "//cdn.datatables.net/plug-ins/1.10.20/i18n/Spanish.json"
            },
            rowCallback: function(row, data) {
                if (data.status === 'discrepancy') {
                    $(row).addClass('table-danger-light');
                } else if (data.status === 'corrected') {
                    $(row).addClass('table-primary-light');
                }
            }
        });

        $('.btn-filter').click(function() {
            $('.btn-filter').removeClass('btn-dark active').addClass('btn-outline-secondary');
            $(this).removeClass('btn-outline-secondary').addClass('btn-dark active');
            currentStatus = $(this).data('status');
            dianTable.ajax.reload();
        });

        $('.clickable-filter').click(function() {
            var status = $(this).data('status');
            $('.btn-filter[data-status="' + status + '"]').trigger('click');
        });

        // Copiar CUFE al portapapeles
        $(document).on('click', '.copy-cufe', function() {
            var cufe = $(this).data('cufe');
            var $btn = $(this);
            
            navigator.clipboard.writeText(cufe).then(function() {
                var originalHtml = $btn.html();
                $btn.html('<i class="fas fa-check text-success"></i>');
                setTimeout(function() {
                    $btn.html(originalHtml);
                }, 2000);
            });
        });

        initClienteSearch();
        initClienteSearchCrear();
    });

    function initClienteSearchCrear() {
        $('#cliente_id_crear').select2({
            dropdownParent: $('#modalCrearFactura'),
            placeholder: 'Buscar cliente por NIT o Nombre...',
            minimumInputLength: 3,
            ajax: {
                url: "{{ route('auditoria.dian.buscar-cliente') }}",
                dataType: 'json',
                delay: 250,
                data: function (params) { return { q: params.term }; },
                processResults: function (data) { return { results: data }; },
                cache: true
            }
        });

        $('#cliente_id_crear').on('select2:select', function (e) {
            var clienteId = e.params.data.id;
            var recordId = $('#record_id_crear_input').val();
            fetchContratosCrear(clienteId, recordId);
        });
    }

    function fetchContratosCrear(clienteId, recordId) {
        $.get("{{ route('auditoria.dian.contratos-cliente') }}", { cliente_id: clienteId, record_id: recordId }, function(data) {
            var html = '';
            if(data.length === 0) {
                html = '<div class="alert alert-warning w-100">Este cliente no tiene contratos registrados. No se pueden generar los ítems.</div>';
                $('#contrato_id_crear').val('');
            } else {
                data.forEach(function(c) {
                    var badge = c.estado === 'Habilitado' ? 'success' : 'secondary';
                    var isSelected = c.recomendado ? 'selected' : '';
                    
                    html += '<div class="contract-card ' + isSelected + '" onclick="seleccionarContratoCrear(this, ' + c.id + ')">';
                    if(c.recomendado) {
                        html += '<span class="recommendation-badge">RECOMENDADO</span>';
                    }
                    html += '<div class="font-weight-bold text-dark">Contrato Nro: ' + c.nro + '</div>';
                    html += '<div class="small text-muted mb-1">Plan: ' + c.plan + '</div>';
                    html += '<span class="badge badge-' + badge + ' badge-sm">' + c.estado + '</span>';
                    html += '</div>';
                    
                    if(c.recomendado) {
                        $('#contrato_id_crear').val(c.id);
                    }
                });
                
                // Si no hubo recomendado, seleccionar el primero por defecto
                if($('#contrato_id_crear').val() === '' && data.length > 0) {
                    $('#contrato_id_crear').val(data[0].id);
                }
            }
            
            $('#contratos_crear_list').html(html);
            $('#contratos_crear_container').slideDown();
            
            // Asegurar visualmente que el primer no-recomendado quede seleccionado si no hubo recomendado
            if($('.contract-card.selected').length === 0 && $('.contract-card').length > 0) {
                $('.contract-card').first().addClass('selected');
                var firstId = $('.contract-card').first().attr('onclick').match(/\d+/)[0];
                $('#contrato_id_crear').val(firstId);
            }
        });
    }

    function seleccionarContratoCrear(element, id) {
        $('.contract-card').removeClass('selected');
        $(element).addClass('selected');
        $('#contrato_id_crear').val(id);
    }

    function abrirModalCrearFactura(id) {
        // Reset form
        $('#cliente_id_crear').empty().val(null).trigger('change');
        $('#contratos_crear_container').hide();
        $('#contrato_id_crear').val('');
        
        // Show loading state using SweetAlert or custom loading
        Swal.fire({
            title: 'Cargando...',
            text: 'Obteniendo datos de la DIAN',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading()
            }
        });

        // Obtener la información desde el endpoint existente (podemos reusar corregir)
        $.get(window.auditBaseUrl + '/corregir/' + id, function(res) {
            Swal.close();
            var r = res.record;
            
            $('#record_id_crear_input').val(r.id);
            $('#folio_crear_modal').text((r.prefijo ? r.prefijo : '') + (r.folio ? r.folio : ''));
            $('#nit_dian_crear_modal').text(r.nit_receptor_dian);
            $('#nombre_dian_crear_modal').text(r.nombre_receptor_dian);
            $('#fecha_dian_crear_modal').text(r.fecha_emision);
            $('#monto_dian_crear_modal').text('$' + new Intl.NumberFormat('es-CO').format(r.total));
            
            // Intento inteligente de preseleccionar el cliente (basado en nit_dian)
            if(r.nit_receptor_dian) {
                var cleanNit = r.nit_receptor_dian.replace(/[^0-9]/g, '');
                $.get("{{ route('auditoria.dian.buscar-cliente') }}", { q: cleanNit }, function(data) {
                    if(data && data.length > 0) {
                        var option = new Option(data[0].text, data[0].id, true, true);
                        $('#cliente_id_crear').append(option).trigger('change');
                        // Forzar la carga de contratos para el cliente preseleccionado
                        fetchContratosCrear(data[0].id, r.id);
                    }
                });
            }

            $('#modalCrearFactura').modal('show');
        }).fail(function() {
            Swal.fire('Error', 'No se pudo cargar la información', 'error');
        });
    }

    function aplicarCreacionFactura() {
        var cliente_id = $('#cliente_id_crear').val();
        var contrato_id = $('#contrato_id_crear').val();
        var record_id = $('#record_id_crear_input').val();

        if (!cliente_id) {
            Swal.fire('Atención', 'Debe seleccionar un cliente', 'warning');
            return;
        }

        if (!contrato_id) {
            Swal.fire('Atención', 'Debe seleccionar un contrato para heredar los ítems', 'warning');
            return;
        }

        Swal.fire({
            title: 'Procesando...',
            text: 'Creando factura electrónica...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading()
            }
        });

        $.ajax({
            url: window.auditBaseUrl + '/crear-factura/' + record_id,
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                cliente_id: cliente_id,
                contrato_id: contrato_id
            },
            success: function(res) {
                if (res.success) {
                    Swal.fire('¡Éxito!', res.message, 'success');
                    $('#modalCrearFactura').modal('hide');
                    window.dianTable.ajax.reload(null, false);
                    
                    // Update stats (decrease not_found, increase corrected)
                    var elNotF = $('#session_not_found');
                    var elCorr = $('#session_corrected');
                    if(elNotF.length) elNotF.text(parseInt(elNotF.text()) - 1);
                    if(elCorr.length) elCorr.text(parseInt(elCorr.text()) + 1);
                } else {
                    Swal.fire('Error', res.message || 'Error desconocido', 'error');
                }
            },
            error: function(err) {
                var msg = 'Ocurrió un error al crear la factura';
                if(err.responseJSON && err.responseJSON.message) msg = err.responseJSON.message;
                if(err.responseJSON && err.responseJSON.error) msg += '<br>' + err.responseJSON.error;
                Swal.fire('Error', msg, 'error');
            }
        });
    }
</script>
<style>
    .table-danger-light { background-color: #fff5f5 !important; }
    .table-primary-light { background-color: #f0f7ff !important; }
    .select2-container .select2-selection--single { height: 45px !important; border-radius: 12px !important; border: 1px solid #e2e8f0 !important; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 43px !important; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 43px !important; padding-left: 15px !important; }
    .btn-round { border-radius: 12px; }
</style>
@endsection
