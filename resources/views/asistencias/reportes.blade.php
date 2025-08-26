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
                        <h4 class="card-title">Reportes de Asistencias</h4>
                        <p class="card-description">Consulta y exporta los registros de asistencia</p>
                    </div>
                    <div class="col-md-6 text-right">
                        <div class="btn-group" role="group">
                            <a href="{{route('asistencias.index')}}" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Volver
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="card mt-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="fas fa-filter"></i> Filtros de búsqueda
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="{{route('asistencias.reportes')}}">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="usuario_id">Empleado</label>
                                        <select name="usuario_id" id="usuario_id" class="form-control">
                                            <option value="">Todos los empleados</option>
                                            @foreach($empleados as $empleado)
                                                <option value="{{$empleado->id}}" 
                                                    {{$usuarioId == $empleado->id ? 'selected' : ''}}>
                                                    {{$empleado->nombres}}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="fecha_inicio">Fecha inicio</label>
                                        <input type="date" 
                                               name="fecha_inicio" 
                                               id="fecha_inicio" 
                                               class="form-control" 
                                               value="{{$fechaInicio}}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="fecha_fin">Fecha fin</label>
                                        <input type="date" 
                                               name="fecha_fin" 
                                               id="fecha_fin" 
                                               class="form-control" 
                                               value="{{$fechaFin}}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <div class="d-flex">
                                            <button type="submit" class="btn btn-primary mr-2">
                                                <i class="fas fa-search"></i> Buscar
                                            </button>
                                            <a href="{{route('asistencias.reportes')}}" class="btn btn-secondary">
                                                <i class="fas fa-times"></i> Limpiar
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Botón de exportar -->
                <div class="row mt-3 mb-3">
                    <div class="col-12">
                        <form method="GET" action="{{route('asistencias.exportar')}}" class="d-inline">
                            <input type="hidden" name="usuario_id" value="{{$usuarioId}}">
                            <input type="hidden" name="fecha_inicio" value="{{$fechaInicio}}">
                            <input type="hidden" name="fecha_fin" value="{{$fechaFin}}">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-file-excel"></i> Exportar a Excel
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Tabla de resultados -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Empleado</th>
                                <th>Fecha</th>
                                <th>Hora</th>
                                <th>Tipo</th>
                                <th>IP Address</th>
                                <th>Dispositivo</th>
                                <th>Observaciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($asistencias as $asistencia)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            @if($asistencia->usuario->image)
                                                <img src="{{asset('storage/avatars/'.$asistencia->usuario->image)}}" 
                                                     alt="{{$asistencia->usuario->nombres}}" 
                                                     class="rounded-circle mr-2" 
                                                     style="width: 30px; height: 30px; object-fit: cover;">
                                            @else
                                                <div class="rounded-circle mr-2 bg-primary d-flex align-items-center justify-content-center text-white" 
                                                     style="width: 30px; height: 30px; font-size: 12px;">
                                                    {{substr($asistencia->usuario->nombres, 0, 2)}}
                                                </div>
                                            @endif
                                            <div>
                                                <strong>{{$asistencia->usuario->nombres}}</strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{$asistencia->fecha}}</td>
                                    <td>{{$asistencia->hora}}</td>
                                    <td>
                                        @if($asistencia->tipo == 'ingreso')
                                            <span class="badge badge-success">
                                                <i class="fas fa-sign-in-alt"></i> Ingreso
                                            </span>
                                        @else
                                            <span class="badge badge-warning">
                                                <i class="fas fa-sign-out-alt"></i> Salida
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <small class="text-muted">{{$asistencia->ip_address}}</small>
                                    </td>
                                    <td>
                                        <small class="text-muted">{{$asistencia->dispositivo_info}}</small>
                                    </td>
                                    <td>
                                        @if($asistencia->observaciones)
                                            <small class="text-muted">{{$asistencia->observaciones}}</small>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="fas fa-search fa-2x mb-2"></i>
                                            <p>No se encontraron registros con los filtros aplicados</p>
                                            <small>Intenta ajustar los filtros de búsqueda</small>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                @if($asistencias->hasPages())
                    <div class="d-flex justify-content-center mt-4">
                        {{ $asistencias->appends(request()->query())->links() }}
                    </div>
                @endif

                <!-- Resumen estadístico -->
                @if($asistencias->count() > 0)
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="fas fa-chart-pie"></i> Resumen del período
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <h4 class="text-primary">{{$asistencias->total()}}</h4>
                                                <small class="text-muted">Total registros</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <h4 class="text-success">{{$asistencias->where('tipo', 'ingreso')->count()}}</h4>
                                                <small class="text-muted">Ingresos</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <h4 class="text-warning">{{$asistencias->where('tipo', 'salida')->count()}}</h4>
                                                <small class="text-muted">Salidas</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <h4 class="text-info">{{$empleados->count()}}</h4>
                                                <small class="text-muted">Empleados activos</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Auto-submit form when dates change
    $('#fecha_inicio, #fecha_fin').change(function() {
        // Optional: auto submit when dates change
        // $(this).closest('form').submit();
    });
    
    // Set today as default end date if empty
    if (!$('#fecha_fin').val()) {
        $('#fecha_fin').val(new Date().toISOString().split('T')[0]);
    }
    
    // Set start of month as default start date if empty
    if (!$('#fecha_inicio').val()) {
        const now = new Date();
        const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
        $('#fecha_inicio').val(startOfMonth.toISOString().split('T')[0]);
    }
});
</script>
@endsection
