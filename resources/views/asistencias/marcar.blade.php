<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Marcar Asistencia - {{$usuario->nombres}}</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .card-custom {
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: none;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        
        .user-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #fff;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .user-avatar-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-size: 2.5rem;
            font-weight: bold;
            border: 5px solid #fff;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .btn-custom {
            border-radius: 50px;
            padding: 15px 40px;
            font-weight: 600;
            font-size: 1.1rem;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        
        .btn-ingreso {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            color: white;
        }
        
        .btn-salida {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            border: none;
            color: white;
        }
        
        .status-badge {
            border-radius: 25px;
            padding: 8px 20px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .time-display {
            font-size: 2.5rem;
            font-weight: 300;
            color: #495057;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .date-display {
            font-size: 1.2rem;
            color: #6c757d;
            font-weight: 500;
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
        }
        
        .info-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            padding: 20px;
            margin: 10px 0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .loading-spinner {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card card-custom">
                    <div class="card-body text-center p-5">
                        <!-- Usuario Info -->
                        <div class="mb-4">
                            @if($usuario->image)
                                <img src="{{asset('storage/avatars/'.$usuario->image)}}" 
                                     alt="{{$usuario->nombres}}" 
                                     class="user-avatar mx-auto d-block mb-3">
                            @else
                                <div class="user-avatar-placeholder mx-auto d-flex align-items-center justify-content-center mb-3">
                                    {{substr($usuario->nombres, 0, 2)}}
                                </div>
                            @endif
                            
                            <h2 class="h3 mb-2 text-dark">{{$usuario->nombres}}</h2>
                            <p class="text-muted mb-1">{{$usuario->email}}</p>
                            @if($usuario->cedula)
                                <p class="text-muted small">Cédula: {{$usuario->cedula}}</p>
                            @endif
                        </div>

                        <!-- Fecha y Hora -->
                        <div class="info-card mb-4">
                            <div class="time-display" id="current-time"></div>
                            <div class="date-display" id="current-date"></div>
                        </div>

                        <!-- Estado actual -->
                        @if($ultimoRegistro)
                            <div class="info-card mb-4">
                                <h6 class="text-muted mb-2">Último registro:</h6>
                                <span class="status-badge 
                                    @if($ultimoRegistro->tipo == 'ingreso') bg-success @else bg-warning @endif
                                    text-white">
                                    <i class="fas fa-{{$ultimoRegistro->tipo == 'ingreso' ? 'sign-in-alt' : 'sign-out-alt'}}"></i>
                                    {{ucfirst($ultimoRegistro->tipo)}} - {{$ultimoRegistro->hora}}
                                </span>
                            </div>
                        @endif

                        <!-- Botón de marcación -->
                        <div class="mb-4">
                            <button type="button" 
                                    id="btn-marcar" 
                                    class="btn btn-custom 
                                        @if($proximoTipo == 'ingreso') btn-ingreso pulse @else btn-salida @endif"
                                    data-tipo="{{$proximoTipo}}">
                                <i class="fas fa-{{$proximoTipo == 'ingreso' ? 'sign-in-alt' : 'sign-out-alt'}} me-2"></i>
                                Marcar {{ucfirst($proximoTipo)}}
                            </button>
                            
                            <div class="loading-spinner mt-3">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                                <p class="mt-2 text-muted">Registrando asistencia...</p>
                            </div>
                        </div>

                        <!-- Información adicional -->
                        <div class="row text-start">
                            <div class="col-12">
                                <div class="alert alert-info alert-dismissible fade show" role="alert">
                                    <h6><i class="fas fa-info-circle"></i> Información:</h6>
                                    <ul class="mb-0 small">
                                        <li>Tu ubicación y dispositivo serán registrados</li>
                                        <li>Solo puedes marcar desde dispositivos autorizados</li>
                                        <li>En caso de error, contacta al administrador</li>
                                    </ul>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        $(document).ready(function() {
            // Actualizar reloj en tiempo real
            function updateClock() {
                const now = new Date();
                const timeOptions = { 
                    hour: '2-digit', 
                    minute: '2-digit', 
                    second: '2-digit',
                    hour12: false
                };
                const dateOptions = { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                };
                
                $('#current-time').text(now.toLocaleTimeString('es-ES', timeOptions));
                $('#current-date').text(now.toLocaleDateString('es-ES', dateOptions));
            }
            
            // Actualizar cada segundo
            updateClock();
            setInterval(updateClock, 1000);
            
            // Configurar CSRF token para Ajax
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            
            // Manejar click del botón de marcación
            $('#btn-marcar').click(function() {
                const tipo = $(this).data('tipo');
                const btn = $(this);
                
                // Mostrar confirmación
                Swal.fire({
                    title: '¿Confirmar marcación?',
                    text: `¿Estás seguro de marcar ${tipo}?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: tipo === 'ingreso' ? '#28a745' : '#ffc107',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: `Sí, marcar ${tipo}`,
                    cancelButtonText: 'Cancelar',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        marcarAsistencia(tipo, btn);
                    }
                });
            });
            
            function marcarAsistencia(tipo, btn) {
                // Deshabilitar botón y mostrar loading
                btn.prop('disabled', true);
                $('.loading-spinner').show();
                btn.hide();
                
                $.ajax({
                    url: '{{route("asistencias.marcar.post", $token)}}',
                    method: 'POST',
                    data: {
                        tipo: tipo,
                        _token: '{{csrf_token()}}'
                    },
                    success: function(response) {
                        if(response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Éxito!',
                                text: response.mensaje,
                                timer: 3000,
                                timerProgressBar: true,
                                showConfirmButton: false,
                                allowOutsideClick: false
                            }).then(() => {
                                // Actualizar estado en el navbar si existe la función
                                if (window.opener && window.opener.actualizarEstadoAsistencia) {
                                    window.opener.actualizarEstadoAsistencia();
                                }
                                
                                // Recargar la página para mostrar el nuevo estado
                                window.location.reload();
                            });
                        } else {
                            throw new Error(response.error || 'Error desconocido');
                        }
                    },
                    error: function(xhr) {
                        console.error('Error:', xhr);
                        
                        let errorMsg = 'Error al registrar asistencia';
                        if(xhr.responseJSON && xhr.responseJSON.error) {
                            errorMsg = xhr.responseJSON.error;
                        } else if(xhr.responseText) {
                            try {
                                const errorResponse = JSON.parse(xhr.responseText);
                                errorMsg = errorResponse.message || errorResponse.error || errorMsg;
                            } catch(e) {
                                // Si no es JSON válido, usar mensaje por defecto
                            }
                        }
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: errorMsg,
                            confirmButtonColor: '#dc3545'
                        });
                        
                        // Restaurar botón
                        $('.loading-spinner').hide();
                        btn.show();
                        btn.prop('disabled', false);
                    }
                });
            }
            
            // Prevenir doble envío
            let isSubmitting = false;
            $('#btn-marcar').click(function(e) {
                if (isSubmitting) {
                    e.preventDefault();
                    return false;
                }
            });
        });
    </script>
</body>
</html>
