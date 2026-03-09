@extends('layouts.app')

@section('content')
<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .logs-header {
        background: white;
        padding: 25px;
        border-radius: 20px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        margin-bottom: 30px;
        border-left: 5px solid #17a2b8;
    }

    .table-container-premium {
        background: white;
        border-radius: 20px;
        padding: 25px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.03);
    }

    .btn-round { border-radius: 12px; }
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="logs-header d-flex justify-content-between align-items-center">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb bg-transparent p-0 mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('auditoria.facturas') }}">Auditoría</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('auditoria.facturas.session', $session->id) }}">Detalle</a></li>
                    <li class="breadcrumb-item active">Logs</li>
                </ol>
            </nav>
            <h2 class="font-weight-bold text-dark mb-0">Traceability: {{ $session->periodo }}</h2>
            <small class="text-muted"><i class="fas fa-history mr-1"></i> Histórico de modificaciones manuales</small>
        </div>
        <a href="{{ route('auditoria.facturas.session', $session->id) }}" class="btn btn-light btn-round">
            <i class="fas fa-arrow-left mr-1"></i> Volver al Detalle
        </a>
    </div>

    <div class="table-container-premium">
        <div class="table-responsive">
            <table class="table table-hover" id="table_logs">
                <thead>
                    <tr>
                        <th class="border-top-0">Documento</th>
                        <th class="border-top-0">Estado Previo</th>
                        <th class="border-top-0">Estado Nuevo</th>
                        <th class="border-top-0">Justificación Técnica</th>
                        <th class="border-top-0">Responsable</th>
                        <th class="border-top-0">Marca Temporal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($session->records->where('status', 'corrected') as $record)
                        @foreach($record->logs as $log)
                        <tr>
                            <td>
                                <span class="font-weight-bold text-dark">#{{ $log->folio }}</span>
                                <small class="text-muted d-block text-truncate" style="max-width: 120px;" title="{{ $log->cufe }}">{{ $log->cufe }}</small>
                            </td>
                            <td>
                                <div class="bg-light p-2 rounded small border-left border-secondary">
                                    <b class="d-block">{{ $log->nit_anterior }}</b>
                                    <span class="text-muted">{{ $log->nombre_anterior }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="bg-primary-light p-2 rounded small border-left border-primary" style="background: #f0f7ff;">
                                    <b class="d-block text-primary">{{ $log->nit_nuevo }}</b>
                                    <span class="text-primary">{{ $log->nombre_nuevo }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="small" title="{{ $log->motivo }}">
                                    {{ Str::limit($log->motivo, 100) }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-xs bg-info text-white rounded-circle text-center mr-2" style="width: 24px; height: 24px; line-height: 24px; font-size: 0.7rem;">
                                        {{ substr($log->user->nombres, 0, 1) }}
                                    </div>
                                    <span class="small">{{ explode(' ', $log->user->nombres)[0] }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="small text-muted d-block">{{ $log->created_at->format('d/m/Y') }}</span>
                                <span class="small text-muted">{{ $log->created_at->format('H:i:s') }}</span>
                            </td>
                        </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        $('#table_logs').DataTable({
            language: { url: "//cdn.datatables.net/plug-ins/1.10.20/i18n/Spanish.json" },
            order: [[5, 'desc']]
        });
    });
</script>
@endsection
