@extends('layouts.app')

@section('title', 'Mi suscripción Integra')

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-lg-9">

        <div class="mb-3">
            <h4 class="mb-1"><i class="fas fa-credit-card text-primary"></i> Mi suscripción Integra</h4>
            <p class="text-muted mb-0" style="font-size:.9rem;">
                Adjunta aquí el comprobante de tu pago mensual del software. El equipo de Integra lo revisa
                y el estado aparece en el historial.
            </p>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul></div>
        @endif

        @unless ($configurado)
            <div class="alert alert-warning">
                El envío al portal aún no está configurado en esta instancia. Contacta a soporte de Integra.
            </div>
        @endunless

        {{-- Formulario --}}
        <div class="card mb-4">
            <div class="card-header font-weight-bold text-uppercase" style="font-size:.8rem;">Enviar comprobante</div>
            <div class="card-body">
                <form method="POST" action="{{ route('mi-suscripcion.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label style="font-size:.8rem;">Mes que pagas *</label>
                            <input type="month" name="periodo" class="form-control" value="{{ old('periodo', date('Y-m')) }}" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label style="font-size:.8rem;">Valor pagado</label>
                            <input type="number" min="0" name="valor" class="form-control" value="{{ old('valor') }}" placeholder="Ej: 80000">
                        </div>
                        <div class="form-group col-md-6">
                            <label style="font-size:.8rem;">Referencia / Nro. transacción</label>
                            <input type="text" name="referencia" class="form-control" value="{{ old('referencia') }}" placeholder="Opcional">
                        </div>
                        <div class="form-group col-md-6">
                            <label style="font-size:.8rem;">Comprobante (imagen o PDF) *</label>
                            <input type="file" name="archivo" class="form-control-file" accept="image/jpeg,image/png,image/webp,application/pdf" required>
                        </div>
                        <div class="form-group col-12">
                            <label style="font-size:.8rem;">Observaciones</label>
                            <input type="text" name="observaciones" class="form-control" value="{{ old('observaciones') }}" placeholder="Opcional">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" {{ $configurado ? '' : 'disabled' }}>
                        <i class="fas fa-paper-plane"></i> Enviar comprobante
                    </button>
                </form>
            </div>
        </div>

        {{-- Historial --}}
        <div class="card">
            <div class="card-header font-weight-bold text-uppercase" style="font-size:.8rem;">Historial</div>
            <div class="card-body p-0">
                @if ($comprobantes->isEmpty())
                    <p class="text-muted p-3 mb-0">Aún no has enviado comprobantes.</p>
                @else
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr style="font-size:.75rem;" class="text-uppercase text-muted">
                                <th class="pl-3">Mes</th><th>Valor</th><th>Referencia</th><th>Enviado</th><th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($comprobantes as $c)
                                <tr style="font-size:.85rem;">
                                    <td class="pl-3 text-capitalize">{{ \Carbon\Carbon::parse($c->periodo)->translatedFormat('F Y') }}</td>
                                    <td>{{ $c->valor !== null ? '$'.number_format($c->valor, 0, ',', '.') : '—' }}</td>
                                    <td>{{ $c->referencia ?: '—' }}</td>
                                    <td>{{ $c->created_at->format('d-m-Y') }}</td>
                                    <td>
                                        @if ($c->estado === 'aprobado')
                                            <span class="badge badge-success">Aprobado</span>
                                        @elseif ($c->estado === 'rechazado')
                                            <span class="badge badge-danger" title="{{ $c->motivo_rechazo }}">Rechazado</span>
                                            @if ($c->motivo_rechazo)
                                                <div class="text-danger" style="font-size:.75rem;">{{ $c->motivo_rechazo }} — adjunta uno nuevo.</div>
                                            @endif
                                        @else
                                            <span class="badge badge-warning">En revisión</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
