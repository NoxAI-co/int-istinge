@extends('layouts.app')

@section('content')
<style>
    /* Premium Design System */
    :root {
        --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --success-gradient: linear-gradient(135deg, #2af598 0%, #009efd 100%);
        --danger-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        --warning-gradient: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
        --glass-bg: rgba(255, 255, 255, 0.9);
    }

    .audit-header {
        background: var(--primary-gradient);
        padding: 40px 20px;
        margin: -20px -20px 30px -20px;
        color: white;
        border-radius: 0 0 30px 30px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }

    .audit-card {
        border: none;
        border-radius: 15px;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        overflow: hidden;
        margin-bottom: 25px;
        background: white;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    }

    .audit-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0,0,0,0.1);
    }

    .kpi-card {
        padding: 25px;
        position: relative;
        color: white;
        min-height: 140px;
    }

    .kpi-card .icon {
        position: absolute;
        right: 20px;
        bottom: 10px;
        font-size: 3.5rem;
        opacity: 0.2;
        transition: transform 0.3s ease;
    }

    .audit-card:hover .icon {
        transform: scale(1.1) rotate(-10deg);
    }

    .kpi-value {
        font-size: 1.8rem;
        font-weight: 800;
        margin-bottom: 5px;
        display: block;
    }

    .kpi-label {
        font-size: 0.9rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 1px;
        opacity: 0.9;
    }

    .bg-gradient-blue { background: var(--primary-gradient); }
    .bg-gradient-green { background: var(--success-gradient); }
    .bg-gradient-red { background: var(--danger-gradient); }
    .bg-gradient-gold { background: var(--warning-gradient); }

    .session-table-container {
        background: white;
        border-radius: 20px;
        padding: 20px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.03);
    }

    .table thead th {
        background: #f8fafc;
        border-top: none;
        text-transform: uppercase;
        font-size: 0.75rem;
        font-weight: 700;
        color: #64748b;
        letter-spacing: 1px;
        padding: 15px;
    }

    .table td {
        vertical-align: middle;
        padding: 15px;
        border-top: 1px solid #f1f5f9;
        font-size: 0.9rem;
    }

    .badge-premium {
        padding: 6px 12px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.75rem;
    }

    .btn-premium {
        background: var(--primary-gradient);
        border: none;
        padding: 10px 25px;
        border-radius: 12px;
        font-weight: 600;
        color: white;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }

    .btn-premium:hover {
        transform: scale(1.05);
        color: white;
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
    }
</style>

<div class="container-fluid">
    <!-- Premium Header -->
    <div class="audit-header d-flex justify-content-between align-items-center">
        <div>
            <h1 class="font-weight-bold mb-1">Centro de Auditoría DIAN</h1>
            <p class="mb-0 opacity-75">Control de integridad y conciliación de facturación electrónica</p>
        </div>
        <div>
            <a href="{{ route('auditoria.dian.create') }}" class="btn btn-premium">
                <i class="fas fa-plus-circle mr-2"></i> Nueva Carga de Reporte
            </a>
        </div>
    </div>

    <!-- Modern KPI Cards -->
    <div class="row">
        <div class="col-lg-3 col-6">
            <div class="audit-card bg-gradient-blue">
                <div class="kpi-card text-center">
                    <span class="kpi-value">{{ $kpis['total_sesiones'] }}</span>
                    <span class="kpi-label">Sesiones</span>
                    <div class="icon"><i class="fas fa-layer-group"></i></div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="audit-card bg-gradient-green">
                <div class="kpi-card text-center">
                    <span class="kpi-value">{{ number_format(App\DianAuditRecord::where('status', 'matched')->count()) }}</span>
                    <span class="kpi-label">Facturas OK</span>
                    <div class="icon"><i class="fas fa-check-double"></i></div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="audit-card bg-gradient-red">
                <div class="kpi-card text-center">
                    <span class="kpi-value">{{ $kpis['discrepancias_activas'] }}</span>
                    <span class="kpi-label">Discrepancias</span>
                    <div class="icon"><i class="fas fa-bell"></i></div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="audit-card bg-gradient-gold">
                <div class="kpi-card text-center">
                    <span class="kpi-label text-white">Monto en Riesgo</span>
                    <span class="kpi-value text-white" style="font-size: 1.4rem;">COP {{ number_format($kpis['monto_riesgo'], 0, ',', '.') }}</span>
                    <div class="icon"><i class="fas fa-shield-alt"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Unified Table Area -->
    <div class="session-table-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="font-weight-bold text-dark m-0"><i class="fas fa-history mr-2 text-primary"></i> Historial de Auditorías</h4>
            <div class="text-muted small">Mostrando últimas sesiones procesadas</div>
        </div>
        
        <div class="table-responsive">
            <table class="table hover">
                <thead>
                    <tr>
                        <th>Período</th>
                        <th>Detalle Archivo</th>
                        <th>Responsable</th>
                        <th>Registro Temporal</th>
                        <th class="text-center">Total</th>
                        <th class="text-center">Estado</th>
                        <th class="text-right">Operaciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sesiones as $s)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-light p-2 rounded mr-3 text-primary"><i class="fas fa-calendar-alt"></i></div>
                                <div>
                                    <span class="font-weight-bold d-block">{{ $s->periodo }}</span>
                                    <small class="text-muted">ID: #{{ $s->id }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="text-truncate d-inline-block" style="max-width: 150px;" title="{{ $s->original_filename }}">
                                <i class="far fa-file-excel text-success mr-1"></i> {{ $s->original_filename }}
                            </span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm bg-info text-white rounded-circle text-center mr-2" style="width: 25px; height: 25px; line-height: 25px; font-size: 0.7rem;">
                                    {{ substr($s->user->nombres, 0, 1) }}
                                </div>
                                <span>{{ explode(' ', $s->user->nombres)[0] }}</span>
                            </div>
                        </td>
                        <td>
                            <span class="text-muted"><i class="far fa-clock mr-1"></i> {{ $s->created_at->diffForHumans() }}</span>
                        </td>
                        <td class="text-center">
                            <span class="badge badge-light p-2 px-3 border">{{ $s->total_records }} regs</span>
                        </td>
                        <td class="text-center">
                            {!! str_replace('badge ', 'badge badge-premium ', $s->status_badge) !!}
                        </td>
                        <td class="text-right d-flex justify-content-end">
                            <a href="{{ route('auditoria.dian.session', $s->id) }}" class="btn btn-sm btn-outline-primary btn-round px-3 mr-2">
                                <i class="fas fa-search-plus mr-1"></i> Gestionar
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-round px-2" onclick="eliminarSesion({{ $s->id }})">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $sesiones->links() }}
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function eliminarSesion(id) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: "Esta acción eliminará permanentemente el historial de esta auditoría y sus logs asociados.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f5576c',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Mostrar cargando
                Swal.fire({
                    title: 'Eliminando...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    url: "{{ route('auditoria.dian.index') }}/eliminar/" + id,
                    method: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}',
                        _method: 'DELETE' // Refuerzo para algunos servidores
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire(
                                'Eliminado',
                                response.message,
                                'success'
                            ).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function(xhr) {
                        const error = xhr.responseJSON ? xhr.responseJSON.message : 'Error interno del servidor';
                        Swal.fire('Error', error, 'error');
                    }
                });
            }
        });
    }
</script>
<style>
    .btn-round { border-radius: 12px; }
</style>
@endsection
