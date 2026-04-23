@extends('layouts.app')

@section('style')
<style>
    .card-sensibilizacion {
        border-radius: 15px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        border: none;
        transition: transform 0.3s;
    }
    .card-sensibilizacion:hover {
        transform: translateY(-5px);
    }
    .preview-image-container {
        width: 100%;
        max-width: 400px;
        height: 300px;
        border: 2px dashed #ccc;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        margin-bottom: 15px;
        background-color: #f8f9fa;
        position: relative;
    }
    .preview-image-container img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }
    .stat-badge {
        font-size: 1.2rem;
        padding: 10px 20px;
        border-radius: 50px;
    }
    .progress-animated {
        height: 25px;
        border-radius: 15px;
        font-weight: bold;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <!-- Panel de Configuración e Imagen -->
        <div class="col-md-5">
            <div class="card card-sensibilizacion mb-4">
                <div class="card-header bg-primary text-white" style="border-radius: 15px 15px 0 0;">
                    <h5 class="mb-0"><i class="fas fa-image mr-2"></i>Imagen de Sensibilización</h5>
                </div>
                <div class="card-body">
                    <form id="form-upload-image" enctype="multipart/form-data">
                        @csrf
                        <div class="preview-image-container" id="image-preview">
                            @if($imageUrl)
                                <img src="{{ $imageUrl }}" alt="Preview">
                            @else
                                <div class="text-center text-muted">
                                    <i class="fas fa-cloud-upload-alt fa-3x mb-2"></i>
                                    <p>Sin imagen cargada<br><small>1280x960px (JPG/PNG)</small></p>
                                </div>
                            @endif
                        </div>
                        <div id="url-display" class="mb-3" style="{{ $imageUrl ? '' : 'display:none;' }}">
                            <label class="font-weight-bold mb-1"><i class="fas fa-link mr-1"></i>URL Pública:</label>
                            <div class="input-group">
                                <input type="text" class="form-control form-control-sm bg-light" id="image-public-url" value="{{ $imageUrl ? asset('images/sensibilizacion.png') : '' }}" readonly>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-copy-url" title="Copiar URL">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="image-input">Seleccionar nueva imagen</label>
                            <input type="file" class="form-control-file" id="image-input" name="image" accept="image/jpeg,image/png">
                            <small class="text-muted">Se redimensiona automáticamente a 1280x960px.</small>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block" id="btn-upload">
                            <i class="fas fa-upload mr-1"></i> Subir Imagen
                        </button>
                    </form>
                </div>
            </div>

            <div class="card card-sensibilizacion">
                <div class="card-header bg-dark text-white" style="border-radius: 15px 15px 0 0;">
                    <h5 class="mb-0"><i class="fas fa-cog mr-2"></i>Configuración del Envío</h5>
                </div>
                <div class="card-body">
                    <form id="form-campaign-config">
                        <div class="form-group">
                            <label for="template-select">Template de WhatsApp</label>
                            <select class="form-control" id="template-select" name="template">
                                <option value="sensibilizacion_primera_comunicacion">Sensibilización Primera Comunicación</option>
                                <option value="sensibilizacion_primera_comunicacion_v2">Sensibilización Primera Comunicación v2</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="optional-1">Nombre de la Compañía (optional_1)</label>
                            <input type="text" class="form-control" id="optional-1" name="optional_1" value="{{ Auth::user()->empresa() ? Auth::user()->empresa()->nombre : '' }}" placeholder="Ej: Mi Empresa">
                            <small class="text-muted">Este nombre aparecerá en el cuerpo del mensaje.</small>
                        </div>
                        <hr>
                        <div id="stats-container" class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Contactos Elegibles:</span>
                                <span class="badge badge-success stat-badge" id="count-valid">0</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Invalidos/Duplicados:</span>
                                <span class="badge badge-warning" id="count-invalid">0</span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-success btn-lg btn-block" id="btn-send-campaign" disabled>
                            <i class="fab fa-whatsapp mr-2"></i> Iniciar Envío Masivo
                        </button>
                    </form>
                </div>
            </div>

            <!-- Envío de Prueba -->
            <div class="card card-sensibilizacion mt-4">
                <div class="card-header bg-warning text-dark" style="border-radius: 15px 15px 0 0;">
                    <h5 class="mb-0"><i class="fas fa-vial mr-2"></i>Envío de Prueba</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Envía un mensaje de prueba a un número específico antes del envío masivo.</p>
                    <div class="form-group mb-2">
                        <label for="test-phone">Número de prueba</label>
                        <input type="text" class="form-control" id="test-phone" placeholder="Ej: 3218404118 ó +573218404118">
                    </div>
                    <button type="button" class="btn btn-warning btn-block" id="btn-send-test">
                        <i class="fas fa-paper-plane mr-1"></i> Enviar Prueba
                    </button>
                    <div id="test-result" class="mt-3" style="display:none;"></div>
                </div>
            </div>
        </div>

        <!-- Panel de Contactos e Historial -->
        <div class="col-md-7">
            <div class="card card-sensibilizacion mb-4">
                <div class="card-header bg-info text-white" style="border-radius: 15px 15px 0 0;">
                    <h5 class="mb-0"><i class="fas fa-list-ul mr-2"></i>Vista Previa de Destinatarios</h5>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-sm table-striped table-hover" id="table-destinatarios">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>NIT</th>
                                <th>Original</th>
                                <th>Enviar a</th>
                            </tr>
                        </thead>
                        <tbody id="destinatarios-list">
                            <tr><td colspan="4" class="text-center">Cargando contactos...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card card-sensibilizacion">
                <div class="card-header bg-secondary text-white" style="border-radius: 15px 15px 0 0;">
                    <h5 class="mb-0"><i class="fas fa-history mr-2"></i>Historial de Campañas</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="table-history">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Template</th>
                                    <th>Total</th>
                                    <th>Enviados</th>
                                    <th>Fallidos</th>
                                    <th>Creado por</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Progreso -->
<div class="modal fade" id="modal-progress" data-backdrop="static" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-body text-center p-5">
                <h4 class="mb-4">Enviando Campaña...</h4>
                <div class="progress mb-3 progress-animated">
                    <div id="campaign-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                </div>
                <p id="progress-text" class="mb-0">Procesando lotes...</p>
                <div id="results-summary" class="mt-4 d-none">
                    <div class="alert alert-success">
                        <h5 class="alert-heading">Campaña Completada!</h5>
                        <p id="summary-text"></p>
                    </div>
                    <button type="button" class="btn btn-primary" data-dismiss="modal">Entendido</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    let contactosValidos = [];

    $(document).ready(function() {
        loadContactos();
        initHistoryTable();

        // Subir Imagen
        $('#form-upload-image').on('submit', function(e) {
            e.preventDefault();
            let formData = new FormData(this);
            
            $('#btn-upload').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Subiendo...');

            $.ajax({
                url: "{{ route('integrapay.sensibilizacion.upload') }}",
                type: "POST",
                data: formData,
                contentType: false,
                processData: false,
                success: function(response) {
                    if(response.success) {
                        $('#image-preview').html('<img src="' + response.url + '" alt="Preview">');
                        // Mostrar la URL pública (sin query string de cache)
                        let cleanUrl = response.url.split('?')[0];
                        $('#image-public-url').val(cleanUrl);
                        $('#url-display').show();
                        swal("Éxito", response.message, "success");
                    } else {
                        swal("Error", response.message, "error");
                    }
                },
                error: function(xhr) {
                    let msg = xhr.responseJSON ? xhr.responseJSON.message : "Error al subir la imagen";
                    swal("Error", msg, "error");
                },
                complete: function() {
                    $('#btn-upload').prop('disabled', false).html('<i class="fas fa-upload mr-1"></i> Subir Imagen');
                }
            });
        });

        // Copiar URL al portapapeles
        $('#btn-copy-url').on('click', function() {
            var input = document.getElementById('image-public-url');
            input.select();
            document.execCommand('copy');
            $(this).html('<i class="fas fa-check"></i>');
            setTimeout(() => { $(this).html('<i class="fas fa-copy"></i>'); }, 1500);
        });

        // Envío de Prueba
        $('#btn-send-test').on('click', function() {
            let phone = $('#test-phone').val().trim();
            if(!phone) {
                swal("Atención", "Ingresa un número de teléfono para la prueba.", "warning");
                return;
            }

            let template = $('#template-select').val();
            let optional1 = $('#optional-1').val();

            $('#btn-send-test').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Enviando...');
            $('#test-result').hide();

            $.ajax({
                url: "{{ route('integrapay.sensibilizacion.test') }}",
                type: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    phone: phone,
                    template: template,
                    optional_1: optional1
                },
                success: function(response) {
                    if(response.success) {
                        $('#test-result').html('<div class="alert alert-success mb-0"><i class="fas fa-check-circle mr-1"></i> ' + response.message + '<br><small class="text-muted">Enviado a: <b>' + response.phone + '</b></small></div>').show();
                    } else {
                        $('#test-result').html('<div class="alert alert-danger mb-0"><i class="fas fa-times-circle mr-1"></i> ' + response.message + '</div>').show();
                    }
                },
                error: function(xhr) {
                    let msg = xhr.responseJSON ? xhr.responseJSON.message : "Error al enviar la prueba";
                    $('#test-result').html('<div class="alert alert-danger mb-0"><i class="fas fa-times-circle mr-1"></i> ' + msg + '</div>').show();
                },
                complete: function() {
                    $('#btn-send-test').prop('disabled', false).html('<i class="fas fa-paper-plane mr-1"></i> Enviar Prueba');
                }
            });
        });

        // Enviar Campaña
        $('#btn-send-campaign').on('click', function() {
            if(contactosValidos.length === 0) return;

            swal({
                title: "¿Estás seguro?",
                text: "Se enviará la campaña a " + contactosValidos.length + " contactos vía WhatsApp.",
                type: "warning",
                showCancelButton: true,
                confirmButtonColor: "#28a745",
                confirmButtonText: "Sí, iniciar envío",
                cancelButtonText: "Cancelar",
                closeOnConfirm: true
            }, function() {
                startCampaignExecution();
            });
        });
    });

    function loadContactos() {
        $('#destinatarios-list').html('<tr><td colspan="4" class="text-center"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando contactos...</td></tr>');
        
        $.get("{{ route('integrapay.sensibilizacion.contactos') }}", function(response) {
            contactosValidos = response.validos;
            $('#count-valid').text(response.total_validos);
            $('#count-invalid').text(response.total_invalidos);
            
            if(response.total_validos > 0) {
                $('#btn-send-campaign').prop('disabled', false);
            }

            let html = '';
            response.validos.forEach(c => {
                html += `<tr>
                    <td>${c.nombre}</td>
                    <td>${c.nit}</td>
                    <td><small>${c.celular_original}</small></td>
                    <td><span class="text-success font-weight-bold">${c.celular_formateado}</span></td>
                </tr>`;
            });

            if(html === '') html = '<tr><td colspan="4" class="text-center text-muted">No se encontraron contactos con celular válido.</td></tr>';
            $('#destinatarios-list').html(html);
        });
    }

    function initHistoryTable() {
        $('#table-history').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('integrapay.sensibilizacion.history') }}",
            columns: [
                { data: 'campaign_date', name: 'campaign_date' },
                { data: 'template', name: 'template' },
                { data: 'total', name: 'total' },
                { data: 'sent', name: 'sent' },
                { data: 'failed', name: 'failed' },
                { data: 'created_by_name', name: 'created_by_name' }
            ],
            language: {
                url: "//cdn.datatables.net/plug-ins/1.10.21/i18n/Spanish.json"
            },
            order: [[0, 'desc']]
        });
    }

    function startCampaignExecution() {
        $('#modal-progress').modal('show');
        $('#results-summary').addClass('d-none');
        $('#campaign-progress-bar').css('width', '0%').text('0%').removeClass('bg-danger').addClass('bg-success');
        
        let template = $('#template-select').val();
        let optional1 = $('#optional-1').val();
        let total = contactosValidos.length;
        let batchSize = 250;
        let phones = contactosValidos.map(c => c.celular_formateado);
        
        // Simplemente enviamos todo al backend y el backend maneja los lotes
        // Pero para mostrar progreso real, el backend enviará respuesta parcial? No, laravel es síncrono.
        // Simulamos envío por lotes desde aquí para control de progreso visual real
        
        sendNextBatch(0, phones, template, optional1, {sent: 0, failed: 0});
    }

    function sendNextBatch(startIndex, allPhones, template, optional1, accumulated) {
        let batchSize = 250;
        let chunk = allPhones.slice(startIndex, startIndex + batchSize);
        let progress = Math.round((startIndex / allPhones.length) * 100);
        
        $('#campaign-progress-bar').css('width', progress + '%').text(progress + '%');
        $('#progress-text').text(`Enviando números del ${startIndex + 1} al ${Math.min(startIndex + batchSize, allPhones.length)} de ${allPhones.length}...`);

        $.ajax({
            url: "{{ route('integrapay.sensibilizacion.send') }}",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                template: template,
                optional_1: optional1,
                contactos: chunk
            },
            success: function(response) {
                accumulated.sent += response.results.total_sent;
                accumulated.failed += response.results.total_failed;
                
                let nextIndex = startIndex + batchSize;
                if(nextIndex < allPhones.length) {
                    sendNextBatch(nextIndex, allPhones, template, optional1, accumulated);
                } else {
                    finishCampaign(accumulated);
                }
            },
            error: function() {
                accumulated.failed += chunk.length;
                let nextIndex = startIndex + batchSize;
                if(nextIndex < allPhones.length) {
                    sendNextBatch(nextIndex, allPhones, template, optional1, accumulated);
                } else {
                    finishCampaign(accumulated);
                }
            }
        });
    }

    function finishCampaign(results) {
        $('#campaign-progress-bar').css('width', '100%').text('100%');
        $('#progress-text').text('Proceso completado.');
        $('#summary-text').html(`Se procesaron <b>${results.sent + results.failed}</b> contactos.<br>Exitosos: <span class="text-success"><b>${results.sent}</b></span><br>Error: <span class="text-danger"><b>${results.failed}</b></span>`);
        $('#results-summary').removeClass('d-none');
        $('#table-history').DataTable().ajax.reload();
    }
</script>
@endsection
