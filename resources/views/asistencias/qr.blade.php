@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <i class="fas fa-qrcode"></i> Código QR - {{$usuario->nombres}}
                </h4>
            </div>
            <div class="card-body text-center">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-4">
                            <h5>Información del Empleado</h5>
                            <div class="user-info">
                                @if($usuario->image)
                                    <img src="{{asset('storage/avatars/'.$usuario->image)}}" 
                                         alt="{{$usuario->nombres}}" 
                                         class="rounded-circle mb-3" 
                                         style="width: 100px; height: 100px; object-fit: cover;">
                                @else
                                    <div class="rounded-circle mx-auto mb-3 bg-primary d-flex align-items-center justify-content-center text-white" 
                                         style="width: 100px; height: 100px; font-size: 24px;">
                                        {{substr($usuario->nombres, 0, 2)}}
                                    </div>
                                @endif
                                <h5>{{$usuario->nombres}}</h5>
                                <p class="text-muted">{{$usuario->email}}</p>
                                @if($usuario->cedula)
                                    <p class="text-muted">Cédula: {{$usuario->cedula}}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-4">
                            <h5>Código QR Personal</h5>
                            <div class="qr-container bg-white p-3 rounded border">
                                {!! $qrCode !!}
                            </div>
                            <p class="text-muted mt-2 small">
                                Escanea este código con tu celular para marcar asistencia
                            </p>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> Instrucciones de uso:</h6>
                            <ol class="text-left mb-0">
                                <li>Abre la cámara de tu celular o una aplicación de lectura QR</li>
                                <li>Escanea el código QR mostrado arriba</li>
                                <li>Se abrirá una página web donde podrás marcar tu ingreso o salida</li>
                                <li>El sistema detectará automáticamente si debes marcar ingreso o salida</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-12">
                        <div class="btn-group" role="group">
                            @if(!isset($desdeNavbar) || !$desdeNavbar)
                                <a href="{{route('asistencias.index')}}" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Volver a la lista
                                </a>
                            @endif
                            <button type="button" class="btn btn-primary" onclick="window.print()">
                                <i class="fas fa-print"></i> Imprimir QR
                            </button>
                            <button type="button" class="btn btn-success" id="compartir-qr">
                                <i class="fas fa-share"></i> Compartir URL
                            </button>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6>URL directa para marcar asistencia:</h6>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="url-asistencia" value="{{$url}}" readonly>
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-primary" type="button" id="copiar-url">
                                            <i class="fas fa-copy"></i> Copiar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Copiar URL al portapapeles
    $('#copiar-url').click(function() {
        var urlInput = document.getElementById('url-asistencia');
        urlInput.select();
        urlInput.setSelectionRange(0, 99999); // Para móviles
        
        try {
            document.execCommand('copy');
            Swal.fire({
                icon: 'success',
                title: '¡Copiado!',
                text: 'URL copiada al portapapeles',
                timer: 2000,
                showConfirmButton: false
            });
        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo copiar la URL'
            });
        }
    });
    
    // Compartir QR
    $('#compartir-qr').click(function() {
        if (navigator.share) {
            navigator.share({
                title: 'Código QR - Control de Asistencias',
                text: 'Usa este enlace para marcar tu asistencia: {{$usuario->nombres}}',
                url: '{{$url}}'
            }).then(() => {
                console.log('URL compartida correctamente');
            }).catch((error) => {
                console.log('Error al compartir:', error);
                // Fallback a copiar al portapapeles
                $('#copiar-url').click();
            });
        } else {
            // Fallback para navegadores que no soportan Web Share API
            $('#copiar-url').click();
        }
    });
});
</script>

<style>
@media print {
    .btn-group, .alert, .card-header {
        display: none !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    
    .user-info {
        page-break-after: avoid;
    }
    
    .qr-container {
        page-break-inside: avoid;
    }
}

.qr-container svg {
    max-width: 100%;
    height: auto;
}
</style>
@endsection
