@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-file-alt"></i> Logs de Envío WhatsApp Meta</h4>
            </div>
            <div class="card-body">
                <!-- Filtros -->
                <div class="row mb-3" id="filtros-container">
                    <div class="col-md-3">
                        <label class="control-label">Plantilla</label>
                        <select class="form-control selectpicker" id="plantilla_id" name="plantilla_id" data-size="5" data-live-search="true" title="Todas las plantillas">
                            <option value="">Todas las plantillas</option>
                            @foreach($plantillas as $plantilla)
                                <option value="{{ $plantilla->id }}">{{ $plantilla->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="control-label">Cliente</label>
                        <select class="form-control selectpicker" id="contacto_id" name="contacto_id" data-size="5" data-live-search="true" title="Todos los clientes">
                            <option value="">Todos los clientes</option>
                            @foreach($contactos as $contacto)
                                @php
                                    $nombre = trim(($contacto->nombre ?? '') . ' ' . ($contacto->apellido1 ?? '') . ' ' . ($contacto->apellido2 ?? ''));
                                @endphp
                                <option value="{{ $contacto->id }}">{{ $nombre }} - {{ $contacto->nit ?? '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="control-label">Fecha Desde</label>
                        <input type="text" class="form-control" id="fecha_desde" name="fecha_desde" value="{{ $fechaDesde }}" placeholder="dd-mm-yyyy">
                    </div>
                    <div class="col-md-2">
                        <label class="control-label">Fecha Hasta</label>
                        <input type="text" class="form-control" id="fecha_hasta" name="fecha_hasta" value="{{ $fechaHasta }}" placeholder="dd-mm-yyyy">
                    </div>
                    <div class="col-md-2">
                        <label class="control-label">Factura Emitida</label>
                        <select class="form-control" id="factura_emitida" name="factura_emitida">
                            <option value="ambas">Ambas</option>
                            <option value="si">Sí</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label class="control-label">Estados</label>
                        <select class="form-control selectpicker" id="estados" name="estados[]" multiple data-size="5" data-selected-text-format="count > 2" title="Todos los estados">
                            <option value="delivered">Entregado</option>
                            <option value="failed">Fallido</option>
                            <option value="read">Leído</option>
                            <option value="sent">Enviado</option>
                            <option value="success">Éxito</option>
                        </select>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <button type="button" class="btn btn-primary" id="btn-filtrar" onclick="aplicarFiltros()">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                        <button type="button" class="btn btn-secondary" id="btn-limpiar" onclick="limpiarFiltros()">
                            <i class="fas fa-redo"></i> Limpiar Filtros
                        </button>
                    </div>
                </div>

                <!-- Tabla DataTables -->
                <div class="table-responsive">
                    <table id="tabla-logs" class="table table-striped table-bordered table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fecha/Hora</th>
                                <th>Cliente</th>
                                <th>Plantilla</th>
                                <th>Documento</th>
                                <th>Estado</th>
                                <th>Mensaje</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    var tabla = null;

    $(document).ready(function() {
        // Inicializar datepickers
        $('#fecha_desde').datepicker({
            uiLibrary: 'bootstrap4',
            iconsLibrary: 'fontawesome',
            locale: 'es-es',
            format: 'yyyy-mm-dd',
            change: function(e) {
                aplicarFiltros();
            }
        });

        $('#fecha_hasta').datepicker({
            uiLibrary: 'bootstrap4',
            iconsLibrary: 'fontawesome',
            locale: 'es-es',
            format: 'yyyy-mm-dd',
            change: function(e) {
                aplicarFiltros();
            }
        });

        // Inicializar DataTable
        tabla = $('#tabla-logs').DataTable({
            responsive: true,
            serverSide: true,
            processing: true,
            searching: false,
            language: {
                'url': '{{asset("vendors/DataTables/es.json")}}'
            },
            ordering: true,
            order: [[0, "desc"]],
            "pageLength": {{ Auth::user()->empresa()->pageLength }},
            ajax: {
                url: '{{ url("empresa/whatsapp-meta-logs/datatable") }}',
                type: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: function(d) {
                    d.plantilla_id = $('#plantilla_id').val();
                    d.contacto_id = $('#contacto_id').val();
                    d.fecha_desde = $('#fecha_desde').val();
                    d.fecha_hasta = $('#fecha_hasta').val();
                    d.factura_emitida = $('#factura_emitida').val();
                    d.estados = $('#estados').val(); // Array de estados seleccionados
                }
            },
            columns: [
                { data: 'id' },
                { data: 'created_at' },
                { data: 'contacto' },
                { data: 'plantilla' },
                { data: 'factura' },
                { data: 'status' },
                { data: 'mensaje_enviado' },
                { data: 'acciones', orderable: false, searchable: false }
            ]
        });
    });


    function aplicarFiltros() {
        if (tabla) {
            tabla.ajax.reload();
        }
    }

    function limpiarFiltros() {
        var url = window.location.pathname.split("/")[1] === "software"
            ? '/software/empresa/whatsapp-meta-logs/limpiar-filtros'
            : '/empresa/whatsapp-meta-logs/limpiar-filtros';

        $.ajax({
            url: url,
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                $('#plantilla_id').val('');
                $('#contacto_id').val('');
                $('#fecha_desde').val(response.fecha_desde);
                $('#fecha_hasta').val(response.fecha_hasta);
                $('#factura_emitida').val('ambas');
                $('#estados').val(null).trigger('change');

                $('#plantilla_id').selectpicker('refresh');
                $('#contacto_id').selectpicker('refresh');
                $('#estados').selectpicker('refresh');

                if (tabla) {
                    tabla.ajax.reload();
                }
            },
            error: function() {
                console.error('Error al limpiar filtros');
            }
        });
    }

    function verLog(id) {
        var baseUrl = window.location.pathname.split("/")[1] === "software"
            ? '/software/empresa/whatsapp-meta-logs/'
            : '/empresa/whatsapp-meta-logs/';
        window.open(baseUrl + id, '_blank', 'width=800,height=600,scrollbars=yes');
    }
</script>
@endsection
