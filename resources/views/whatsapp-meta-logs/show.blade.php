@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0"><i class="fas fa-file-alt"></i> Detalle del Log #{{ $log->id }}</h4>
        </div>
        <div class="card-body">
            <div class="row">
                <!-- Información Principal -->
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-info-circle"></i> Información General</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">ID:</th>
                                    <td>{{ $log->id }}</td>
                                </tr>
                                <tr>
                                    <th>Estado:</th>
                                    <td>{!! $log->estadoFormateado() !!}</td>
                                </tr>
                                @if($log->remote_id)
                                <tr>
                                    <th>Meta ID (Remote):</th>
                                    <td><small class="text-muted">{{ $log->remote_id }}</small></td>
                                </tr>
                                @endif
                                @if($log->wamid)
                                <tr>
                                    <th>WAMID:</th>
                                    <td><small class="text-muted">{{ $log->wamid }}</small></td>
                                </tr>
                                @endif
                                <tr>
                                    <th>Fecha/Hora:</th>
                                    <td>{{ \Carbon\Carbon::parse($log->created_at)->format('d/m/Y H:i:s') }}</td>
                                </tr>
                                <tr>
                                    <th>Empresa:</th>
                                    <td>{{ $log->empresaObj ? $log->empresaObj->nombre : '-' }}</td>
                                </tr>
                                @if($log->usuarioEnvio)
                                <tr>
                                    <th>Enviado por:</th>
                                    <td>{{ $log->usuarioEnvio->nombres }}</td>
                                </tr>
                                @else
                                <tr>
                                    <th>Enviado por:</th>
                                    <td><span class="badge badge-secondary">Sistema (Cron)</span></td>
                                </tr>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Información de Contacto y Plantilla -->
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-user"></i> Contacto y Plantilla</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                @if($log->contacto)
                                <tr>
                                    <th width="40%">Cliente:</th>
                                    <td>
                                        <a href="{{ route('contactos.show', $log->contacto_id) }}">
                                            {{ $log->contacto->nombre }} {{ $log->contacto->apellido1 }} {{ $log->contacto->apellido2 }}
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <th>NIT:</th>
                                    <td>{{ $log->contacto->nit }}</td>
                                </tr>
                                <tr>
                                    <th>Celular:</th>
                                    <td>{{ $log->contacto->celular ?? '-' }}</td>
                                </tr>
                                @else
                                <tr>
                                    <td colspan="2" class="text-center text-muted">No hay contacto asociado</td>
                                </tr>
                                @endif
                                @if($log->plantilla)
                                <tr>
                                    <th>Plantilla:</th>
                                    <td>
                                        <strong>{{ $log->plantilla->title }}</strong><br>
                                        <small class="text-muted">{{ $log->plantilla->tipo() }}</small>
                                    </td>
                                </tr>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Información de Factura -->
            @if($log->factura)
            <div class="row">
                <div class="col-md-12">
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-file-invoice"></i> Información de Factura</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <th width="20%">Código:</th>
                                    <td>
                                        <a href="{{ route('facturas.show', $log->factura_id) }}">
                                            {{ $log->factura->codigo }}
                                        </a>
                                    </td>
                                    <th width="20%">Fecha:</th>
                                    <td>{{ \Carbon\Carbon::parse($log->factura->fecha)->format('d/m/Y') }}</td>
                                </tr>
                                <tr>
                                    <th>Estado:</th>
                                    <td>
                                        @if($log->factura->emitida == 1)
                                            <span class="badge badge-success">Emitida</span>
                                        @else
                                            <span class="badge badge-warning">No Emitida</span>
                                        @endif
                                    </td>
                                    <th>Total:</th>
                                    <td>${{ number_format($log->factura->total()->total ?? 0, 2, ',', '.') }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Mensaje Enviado -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-comment"></i> Mensaje Enviado</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <pre style="white-space: pre-wrap; word-wrap: break-word; margin: 0;">{{ $log->mensaje_enviado ?? 'No disponible' }}</pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Respuesta de la API -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-code"></i> Respuesta de la API</h5>
                        </div>
                        <div class="card-body">
                            @if($log->error_message)
                                <div class="alert alert-danger mb-3">
                                    <strong>Mensaje de Error:</strong>
                                    <pre style="white-space: pre-wrap; word-wrap: break-word; margin: 0; background: transparent; border: none; padding: 0; color: inherit; font-family: inherit;">{{ $log->error_message }}</pre>
                                </div>
                            @endif

                            <h6 class="font-weight-bold">Response Completo:</h6>
                            @if($responseJson)
                                <pre style="background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto;"><code>{{ json_encode($responseJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</code></pre>
                            @else
                                <div class="alert alert-warning">
                                    <pre style="white-space: pre-wrap; word-wrap: break-word; margin: 0;">{{ $log->response ?? 'No hay respuesta disponible' }}</pre>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botón de cierre -->
            <div class="row">
                <div class="col-md-12 text-center">
                    <button type="button" class="btn btn-secondary" onclick="window.close()">
                        <i class="fas fa-times"></i> Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Si se abre en ventana nueva, ajustar el tamaño
    if (window.opener) {
        document.body.style.margin = '0';
        document.body.style.padding = '10px';
    }
</script>
@endsection
