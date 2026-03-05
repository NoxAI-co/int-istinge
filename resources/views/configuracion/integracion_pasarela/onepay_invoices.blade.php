@extends('layouts.app')

@section('boton')
    <a href="{{ route('integracion-pasarelas.show', $servicio->id) }}" class="btn btn-outline-danger btn-sm">
        <i class="fas fa-backward"></i> Regresar
    </a>
@endsection

@section('content')

@if($error)
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle"></i> <strong>Error al cargar facturas OnePay:</strong> {{ $error }}
    </div>
@endif

{{-- FILTROS --}}
<div class="card mb-3">
    <div class="card-header bg-dark text-white py-2">
        <i class="fas fa-filter"></i> <strong>Filtros</strong>
    </div>
    <div class="card-body py-2">
        <form method="GET" action="{{ route('integracion-pasarelas.onepay-invoices', $servicio->id) }}" id="form-filtros">
            <div class="row">
                <div class="col-md-2">
                    <label class="small mb-1">ID Factura</label>
                    <input type="text" name="filter_id" class="form-control form-control-sm"
                           value="{{ $filters['filter_id'] ?? '' }}" placeholder="UUID exacto">
                </div>
                <div class="col-md-2">
                    <label class="small mb-1">Estado</label>
                    <select name="filter_status" class="form-control form-control-sm">
                        <option value="">-- Todos --</option>
                        @foreach(['CREATED','NOTIFIED','PAID','CANCELLED','EXPIRED'] as $st)
                        <option value="{{ $st }}" {{ ($filters['filter_status'] ?? '') == $st ? 'selected' : '' }}>
                            {{ $st }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="small mb-1">Referencia</label>
                    <input type="text" name="filter_reference" class="form-control form-control-sm"
                           value="{{ $filters['filter_reference'] ?? '' }}" placeholder="Referencia exacta">
                </div>
                <div class="col-md-2">
                    <label class="small mb-1">Provider ID</label>
                    <input type="text" name="filter_provider_id" class="form-control form-control-sm"
                           value="{{ $filters['filter_provider_id'] ?? '' }}" placeholder="Provider ID">
                </div>
                <div class="col-md-2">
                    <label class="small mb-1">Ordenar por</label>
                    <select name="sort" class="form-control form-control-sm">
                        <option value="-created_at" {{ ($filters['sort'] ?? '') == '-created_at' ? 'selected' : '' }}>Más recientes primero</option>
                        <option value="created_at"  {{ ($filters['sort'] ?? '') == 'created_at'  ? 'selected' : '' }}>Más antiguos primero</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-sm btn-primary mr-1">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <a href="{{ route('integracion-pasarelas.onepay-invoices', $servicio->id) }}" class="btn btn-sm btn-secondary">
                        <i class="fas fa-undo"></i> Limpiar
                    </a>
                </div>
            </div>
            {{-- Mantener la página en el filtro --}}
            <input type="hidden" name="page" value="1">
        </form>
    </div>
</div>

{{-- TABLA --}}
<div class="row card-description">
    <div class="col-md-12">
        <table class="table table-striped table-hover table-bordered table-sm" id="onepay-invoices-table">
            <thead class="thead-dark">
                <tr>
                    <th>ID OnePay</th>
                    <th>Nombre</th>
                    <th>Provider</th>
                    <th>Provider ID</th>
                    <th>Referencia</th>
                    <th>Estado</th>
                    <th>Observaciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $invoice)
                <tr>
                    <td><small class="text-muted">{{ $invoice['id'] ?? '-' }}</small></td>
                    <td>{{ $invoice['name'] ?? '-' }}</td>
                    <td>{{ $invoice['provider'] ?? '-' }}</td>
                    <td>{{ $invoice['provider_id'] ?? '-' }}</td>
                    <td>{{ $invoice['reference'] ?? '-' }}</td>
                    <td>
                        @php
                            $status = $invoice['status'] ?? '';
                            $statuses = [
                                'PAID'      => 'badge-success',
                                'CREATED'   => 'badge-primary',
                                'NOTIFIED'  => 'badge-info',
                                'CANCELLED' => 'badge-danger',
                                'EXPIRED'   => 'badge-secondary',
                            ];
                            $badgeClass = $statuses[$status] ?? 'badge-light';
                        @endphp
                        <span class="badge {{ $badgeClass }}">{{ $status ?: '-' }}</span>
                    </td>
                    <td>{{ $invoice['remarks'] ?? '-' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                        No se encontraron facturas con los filtros aplicados.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        {{-- PAGINACIÓN INDEPENDIENTE DE METADATOS --}}
        @php
            $currentPage = (int)($meta['current_page'] ?? $filters['page'] ?? 1);
            $hasMoreItems = count($invoices) > 0;
            // Si la API devuelve un total de páginas exacto lo usamos, sino habilitamos "Siguiente" condicionalmente
            $lastPage = (int)($meta['last_page'] ?? 999999);
        @endphp
        
        <div class="d-flex justify-content-between align-items-center mt-2">
            <div class="text-muted small">
                @if(isset($meta['total']) && isset($meta['last_page']))
                    Mostrando <strong>{{ count($invoices) }}</strong> de <strong>{{ $meta['total'] }}</strong> facturas
                    &mdash; Página <strong>{{ $currentPage }}</strong>
                    de <strong>{{ $meta['last_page'] }}</strong>
                @else
                    Página <strong>{{ $currentPage }}</strong> 
                    (Mostrando <strong>{{ count($invoices) }}</strong> facturas en esta vista)
                @endif
            </div>
            
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    {{-- Botón Anterior --}}
                    @if($currentPage > 1)
                    <li class="page-item">
                        <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $currentPage - 1]) }}">
                            <i class="fas fa-chevron-left"></i> Anterior
                        </a>
                    </li>
                    @else
                    <li class="page-item disabled">
                        <span class="page-link"><i class="fas fa-chevron-left"></i> Anterior</span>
                    </li>
                    @endif

                    <li class="page-item active">
                        <span class="page-link">{{ $currentPage }}</span>
                    </li>

                    {{-- Botón Siguiente --}}
                    @if($currentPage < $lastPage && $hasMoreItems)
                    <li class="page-item">
                        <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $currentPage + 1]) }}">
                            Siguiente <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                    @else
                    <li class="page-item disabled">
                        <span class="page-link">Siguiente <i class="fas fa-chevron-right"></i></span>
                    </li>
                    @endif
                </ul>
            </nav>
        </div>

    </div>
</div>

@endsection

@section('scripts')
<script>
$(document).ready(function () {
    $('#onepay-invoices-table').DataTable({
        paging:   false,    // La paginación la maneja el servidor/PHP
        ordering: false,    // El orden lo maneja la API (sort param)
        info:     false,
        searching: true,    // Búsqueda local rápida dentro de la página actual
        language: {
            search:      "Buscar en esta página:",
            zeroRecords: "No hay coincidencias",
            infoEmpty:   "Sin resultados",
            emptyTable:  "Sin datos disponibles",
        },
        dom: '<"d-flex justify-content-between align-items-center mb-2"f>rt',
        columnDefs: [
            { targets: 0, className: 'text-nowrap' },
            { targets: 5, className: 'text-center' },
        ]
    });
});
</script>
@endsection
