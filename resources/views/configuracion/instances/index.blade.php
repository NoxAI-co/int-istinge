@extends('layouts.app')

@section('content')
    @if(Session::has('success'))
        <div class="alert alert-success">
            {{Session::get('success')}}
        </div>
        <script type="text/javascript">
            setTimeout(function(){
                $('.alert').hide();
            }, 5000);
        </script>
    @endif

    @if(Session::has('danger'))
        <div class="alert alert-danger">
            {{Session::get('danger')}}
        </div>
        <script type="text/javascript">
            setTimeout(function(){
                $('.alert').hide();
            }, 5000);
        </script>
    @endif

    <div class="row card-description">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0">Instancias WhatsApp Meta</h4>
                @if(!auth()->user()->modo_lectura())
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modalCrearInstancia">
                        <i class="fas fa-plus"></i> Agregar nueva instancia
                    </button>
                @endif
            </div>

            <table class="table table-striped table-hover" id="tablaInstancias">
                <thead class="thead-dark">
                    <tr>
                        <th>UUID</th>
                        <th>Phone Number ID</th>
                        <th>WABA ID</th>
                        <th>Estado</th>
                        <th>Activo</th>
                        <th>Fecha Creación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($instances as $instance)
                        <tr>
                            <td>{{ $instance->uuid }}</td>
                            <td>{{ $instance->phone_number_id }}</td>
                            <td>{{ $instance->waba_id }}</td>
                            <td>
                                <span class="badge badge-{{ $instance->status === 'PAIRED' ? 'success' : 'warning' }}">
                                    {{ $instance->status }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-{{ $instance->activo ? 'success' : 'secondary' }}">
                                    {{ $instance->activo ? 'Sí' : 'No' }}
                                </span>
                            </td>
                            <td>{{ $instance->created_at ? $instance->created_at->format('d/m/Y H:i') : '-' }}</td>
                            <td>
                                @if(!auth()->user()->modo_lectura())
                                    <button type="button" class="btn btn-outline-primary btn-sm" 
                                            onclick="editarInstancia({{ $instance->id }}, '{{ $instance->phone_number_id }}', '{{ $instance->waba_id }}', '{{ $instance->access_token }}')" 
                                            title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm" 
                                            onclick="eliminarInstancia({{ $instance->id }})" 
                                            title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center">No hay instancias registradas</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal para crear instancia -->
    <div class="modal fade" id="modalCrearInstancia" tabindex="-1" role="dialog" aria-labelledby="modalCrearInstanciaLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCrearInstanciaLabel">Agregar Nueva Instancia</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="formCrearInstancia">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="control-label">Phone Number ID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="phone_number_id" name="phone_number_id" required maxlength="255">
                            <span class="help-block error">
                                <strong id="error_phone_number_id"></strong>
                            </span>
                        </div>
                        <div class="form-group">
                            <label class="control-label">WABA ID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="waba_id" name="waba_id" required maxlength="255">
                            <span class="help-block error">
                                <strong id="error_waba_id"></strong>
                            </span>
                        </div>
                        <div class="form-group">
                            <label class="control-label">Token de Acceso</label>
                            <input type="text" class="form-control" id="access_token" name="access_token" value="EAAadz7kv9dkBP345r2ZApR9Q7NciJ3pGx1WGtZCS1XkUZBviwshfD2LdXSHqEPrZBYd3Y9qwf5ZA0loxncC6N4CzYu1T62qMYBqyGhrczcsYkndpjyGOc7hTAJtZCTCHoY0tAjCof9w382TAaDVjqYYZCoeOxnLxNm5hRjkinZA8RCxZAJ8z2lzqDwHmzKbla7AZDZD">
                        </div>
                        <div id="mensajeError" class="alert alert-danger d-none"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btnGuardarInstancia">
                            <i class="fas fa-save"></i> Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para editar instancia -->
    <div class="modal fade" id="modalEditarInstancia" tabindex="-1" role="dialog" aria-labelledby="modalEditarInstanciaLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarInstanciaLabel">Editar Instancia</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="formEditarInstancia">
                    @csrf
                    <input type="hidden" id="edit_instance_id" name="id">
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="control-label">Phone Number ID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_phone_number_id" name="phone_number_id" required maxlength="255">
                        </div>
                        <div class="form-group">
                            <label class="control-label">WABA ID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_waba_id" name="waba_id" required maxlength="255">
                        </div>
                        <div class="form-group">
                            <label class="control-label">Token de Acceso</label>
                            <input type="text" class="form-control" id="edit_access_token" name="access_token">
                        </div>
                        <div id="mensajeErrorEdit" class="alert alert-danger d-none"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btnActualizarInstancia">
                            <i class="fas fa-save"></i> Actualizar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        // Inicializar DataTable si hay instancias
        @if(count($instances) > 0)
            $('#tablaInstancias').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json"
                },
                "order": [[5, "desc"]]
            });
        @endif

        // Manejar envío del formulario
        $('#formCrearInstancia').on('submit', function(e) {
            e.preventDefault();
            
            // Limpiar errores anteriores
            $('.error strong').text('');
            $('#mensajeError').addClass('d-none').text('');
            
            // Deshabilitar botón
            $('#btnGuardarInstancia').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Guardando...');
            
            var formData = {
                phone_number_id: $('#phone_number_id').val(),
                waba_id: $('#waba_id').val(),
                access_token: $('#access_token').val(),
                _token: $('input[name="_token"]').val()
            };

            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/empresa/configuracion/instances';
            } else {
                var url = '/empresa/configuracion/instances';
            }

            $.ajax({
                url: url,
                method: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: 'Éxito',
                            text: response.message,
                            type: 'success',
                            showConfirmButton: false,
                            timer: 2000
                        }).then(function() {
                            location.reload();
                        });
                    } else {
                        mostrarError(response.message);
                    }
                },
                error: function(xhr) {
                    var errorMessage = 'Error al crear la instancia';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    } else if (xhr.responseText) {
                        try {
                            var error = JSON.parse(xhr.responseText);
                            if (error.message) {
                                errorMessage = error.message;
                            }
                        } catch(e) {
                            errorMessage = 'Error al procesar la respuesta del servidor';
                        }
                    }
                    mostrarError(errorMessage);
                },
                complete: function() {
                    $('#btnGuardarInstancia').prop('disabled', false).html('<i class="fas fa-save"></i> Guardar');
                }
            });
        });

        function mostrarError(mensaje) {
            $('#mensajeError').removeClass('d-none').text(mensaje);
            Swal.fire({
                title: 'Error',
                text: mensaje,
                type: 'error',
                confirmButtonText: 'Aceptar'
            });
        }

        // Limpiar formulario al cerrar modal
        $('#modalCrearInstancia').on('hidden.bs.modal', function() {
            $('#formCrearInstancia')[0].reset();
            $('.error strong').text('');
            $('#mensajeError').addClass('d-none').text('');
        });

        // Manejar envío del formulario de edición
        $('#formEditarInstancia').on('submit', function(e) {
            e.preventDefault();
            
            $('#mensajeErrorEdit').addClass('d-none').text('');
            $('#btnActualizarInstancia').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Actualizando...');
            
            var id = $('#edit_instance_id').val();
            var formData = {
                phone_number_id: $('#edit_phone_number_id').val(),
                waba_id: $('#edit_waba_id').val(),
                access_token: $('#edit_access_token').val(),
                _token: $('input[name="_token"]').val(),
                _method: 'PUT'
            };

            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/empresa/configuracion/instances/' + id;
            } else {
                var url = '/empresa/configuracion/instances/' + id;
            }

            $.ajax({
                url: url,
                method: 'POST', // Usamos POST con _method=PUT
                data: formData,
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: 'Éxito',
                            text: response.message,
                            type: 'success',
                            showConfirmButton: false,
                            timer: 2000
                        }).then(function() {
                            location.reload();
                        });
                    } else {
                        $('#mensajeErrorEdit').removeClass('d-none').text(response.message);
                    }
                },
                error: function(xhr) {
                    var errorMessage = 'Error al actualizar la instancia';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    $('#mensajeErrorEdit').removeClass('d-none').text(errorMessage);
                },
                complete: function() {
                    $('#btnActualizarInstancia').prop('disabled', false).html('<i class="fas fa-save"></i> Actualizar');
                }
            });
        });
    });

    function editarInstancia(id, phoneNumber, wabaId, accessToken) {
        $('#edit_instance_id').val(id);
        $('#edit_phone_number_id').val(phoneNumber);
        $('#edit_waba_id').val(wabaId);
        $('#edit_access_token').val(accessToken);
        $('#modalEditarInstancia').modal('show');
    }

    function eliminarInstancia(id) {
        Swal.fire({
            title: '¿Está seguro?',
            text: "Esta acción no se puede revertir",
            type: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.value) {
                if (window.location.pathname.split("/")[1] === "software") {
                    var url = '/software/empresa/configuracion/instances/' + id;
                } else {
                    var url = '/empresa/configuracion/instances/' + id;
                }

                $.ajax({
                    url: url,
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                title: 'Eliminado',
                                text: response.message,
                                type: 'success',
                                showConfirmButton: false,
                                timer: 2000
                            }).then(function() {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'Error',
                                text: response.message,
                                type: 'error',
                                confirmButtonText: 'Aceptar'
                            });
                        }
                    },
                    error: function(xhr) {
                        var errorMessage = 'Error al eliminar la instancia';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }
                        Swal.fire({
                            title: 'Error',
                            text: errorMessage,
                            type: 'error',
                            confirmButtonText: 'Aceptar'
                        });
                    }
                });
            }
        });
    }
</script>
@endsection

