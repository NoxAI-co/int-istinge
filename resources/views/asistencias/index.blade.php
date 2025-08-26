@extends('layouts.app')

@section('boton')
    @if(Auth::user()->modo_lectura())
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <strong>¡Atención!</strong> Te encuentras en modo de solo lectura.
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif
@endsection

@section('content')
<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h4 class="card-title">Control de Asistencias - Empleados</h4>
                        <p class="card-description">Lista de empleados para control de ingreso y salida</p>
                    </div>
                    <div class="col-md-6 text-right">
                        <div class="btn-group" role="group">
                            <a href="{{route('asistencias.reportes')}}" class="btn btn-outline-primary">
                                <i class="fas fa-chart-line"></i> Ver Reportes
                            </a>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Instrucciones:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Cada empleado puede generar su código QR personal para marcar asistencia</li>
                                <li>También puede marcar asistencia directamente desde esta página</li>
                                <li>El sistema registra la fecha, hora, IP y dispositivo usado</li>
                                <li>Se alternan automáticamente entre "Ingreso" y "Salida"</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Empleado</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Estado Hoy</th>
                                <th>Último Registro</th>
                                <th>Horas Trabajadas</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($empleados as $empleado)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            @if($empleado->image)
                                                <img src="{{asset('storage/avatars/'.$empleado->image)}}" 
                                                     alt="{{$empleado->nombres}}" 
                                                     class="rounded-circle mr-2" 
                                                     style="width: 40px; height: 40px; object-fit: cover;">
                                            @else
                                                <div class="rounded-circle mr-2 bg-primary d-flex align-items-center justify-content-center text-white" 
                                                     style="width: 40px; height: 40px; font-size: 14px;">
                                                    {{substr($empleado->nombres, 0, 2)}}
                                                </div>
                                            @endif
                                            <div>
                                                <strong>{{$empleado->nombres}}</strong>
                                                @if($empleado->cedula)
                                                    <br><small class="text-muted">{{$empleado->cedula}}</small>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{$empleado->email}}</td>
                                    <td>
                                        <span class="badge badge-secondary">{{$empleado->nombre_rol}}</span>
                                    </td>
                                    <td>
                                        @if($empleado->ultimo_registro_hoy)
                                            @if($empleado->ultimo_registro_hoy->tipo == 'ingreso')
                                                <span class="badge badge-success">
                                                    <i class="fas fa-sign-in-alt"></i> En el trabajo
                                                </span>
                                            @else
                                                <span class="badge badge-warning">
                                                    <i class="fas fa-sign-out-alt"></i> Fuera del trabajo
                                                </span>
                                            @endif
                                        @else
                                            <span class="badge badge-light">Sin registros hoy</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($empleado->ultimo_registro_hoy)
                                            <small>
                                                {{ucfirst($empleado->ultimo_registro_hoy->tipo)}} a las 
                                                {{$empleado->ultimo_registro_hoy->hora}}
                                            </small>
                                        @else
                                            <small class="text-muted">-</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if($empleado->horas_trabajadas_hoy > 0)
                                            <span class="badge badge-info">{{$empleado->horas_trabajadas_hoy}} hrs</span>
                                        @else
                                            <span class="text-muted">0 hrs</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <!-- Botón QR -->
                                            <a href="{{route('asistencias.qr', $empleado->id)}}" 
                                               class="btn btn-outline-primary btn-sm" 
                                               title="Generar código QR">
                                                <i class="fas fa-qrcode"></i>
                                            </a>

                                            <!-- Botones de marcación rápida -->
                                            @if(!$empleado->marco_ingreso_hoy || ($empleado->ultimo_registro_hoy && $empleado->ultimo_registro_hoy->tipo == 'salida'))
                                                <button class="btn btn-success btn-sm marcar-asistencia" 
                                                        data-usuario="{{$empleado->id}}" 
                                                        data-tipo="ingreso"
                                                        title="Marcar ingreso">
                                                    <i class="fas fa-sign-in-alt"></i>
                                                </button>
                                            @endif

                                            @if($empleado->ultimo_registro_hoy && $empleado->ultimo_registro_hoy->tipo == 'ingreso')
                                                <button class="btn btn-warning btn-sm marcar-asistencia" 
                                                        data-usuario="{{$empleado->id}}" 
                                                        data-tipo="salida"
                                                        title="Marcar salida">
                                                    <i class="fas fa-sign-out-alt"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="fas fa-users fa-2x mb-2"></i>
                                            <p>No hay empleados registrados en el sistema</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
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
$(document).ready(function() {
    // Manejar marcación de asistencia
    $('.marcar-asistencia').click(function(e) {
        e.preventDefault();
        
        var usuario_id = $(this).data('usuario');
        var tipo = $(this).data('tipo');
        var btn = $(this);
        
        Swal.fire({
            title: '¿Confirmar marcación?',
            text: '¿Está seguro de marcar ' + tipo + ' para este empleado?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#007bff',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, marcar ' + tipo,
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            console.log('Resultado de confirmación:', result);
            if (result.value) {
                console.log('Confirmación aceptada, iniciando AJAX...');
                console.log('Usuario ID:', usuario_id);
                console.log('Tipo:', tipo);
                
                btn.prop('disabled', true);
                btn.html('<i class="fas fa-spinner fa-spin"></i>');
                
                $.ajax({
                    url: '{{route("asistencias.marcar.admin")}}',
                    method: 'POST',
                    data: {
                        usuario_id: usuario_id,
                        tipo: tipo,
                        _token: '{{csrf_token()}}'
                    },
                    beforeSend: function() {
                        console.log('Enviando petición AJAX...');
                    },
                    success: function(response) {
                        console.log('Respuesta exitosa:', response);
                        if(response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Éxito!',
                                text: response.mensaje,
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            console.log('Error en respuesta:', response);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.error || 'Error al registrar asistencia'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.log('Error AJAX:', xhr, status, error);
                        console.log('Status:', xhr.status);
                        console.log('Response Text:', xhr.responseText);
                        
                        var errorMsg = 'Error al registrar asistencia';
                        if(xhr.responseJSON && xhr.responseJSON.error) {
                            errorMsg = xhr.responseJSON.error;
                        } else if(xhr.responseText) {
                            errorMsg = 'Error del servidor: ' + xhr.status;
                        }
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: errorMsg
                        });
                    },
                    complete: function() {
                        console.log('Petición AJAX completada');
                        btn.prop('disabled', false);
                        if(tipo === 'ingreso') {
                            btn.html('<i class="fas fa-sign-in-alt"></i>');
                        } else {
                            btn.html('<i class="fas fa-sign-out-alt"></i>');
                        }
                    }
                });
            } else {
                console.log('Confirmación cancelada');
            }
        });
    });
    
    // Auto-refresh cada 5 minutos
    setInterval(function() {
        location.reload();
    }, 300000); // 5 minutos
});
</script>
@endsection
