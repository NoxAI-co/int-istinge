@extends('layouts.app')

@section('content')

<style>
    .etiqueta-auto-card {
        background: #fff;
        border: 1px solid #eef2f7;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.04);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }
    .etiqueta-auto-card h5 {
        color: #022454;
        font-weight: 700;
        margin-bottom: 0.2rem;
    }
    .etiqueta-auto-card p.desc {
        color: #6c757d;
        font-size: 0.88rem;
        margin-bottom: 0.8rem;
    }
    .accion-icon {
        font-size: 1.6rem;
        margin-right: 1rem;
        color: #022454;
    }
    .accion-row {
        display: flex;
        align-items: center;
    }
    .accion-body {
        flex: 1;
    }
</style>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex align-items-center mb-4">
            <a href="{{ route('configuracion.index') }}" class="btn btn-outline-secondary btn-sm mr-3">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
            <div>
                <h4 class="mb-0" style="color:#022454; font-weight:700;">
                    <i class="fas fa-tags mr-2 text-primary"></i> Etiquetas Automáticas en Contratos
                </h4>
                <p class="text-muted mb-0" style="font-size:0.9rem;">
                    Configura qué etiqueta se asigna automáticamente a un contrato según la acción que lo afecte.
                </p>
            </div>
        </div>

        @if(Session::has('success'))
            <div class="alert alert-success">
                <i class="fas fa-check-circle mr-2"></i> {{ Session::get('success') }}
            </div>
        @endif

        <form action="{{ route('configuracion.etiquetas_automaticas.store') }}" method="POST">
            @csrf

            @php
                $iconos = [
                    'corte_automatico'    => 'fas fa-cut',
                    'cliente_eliminado'   => 'fas fa-user-times',
                    'deshabilitar_manual' => 'fas fa-toggle-off',
                    'pago_factura'        => 'fas fa-check-circle',
                ];
                $colores = [
                    'corte_automatico'    => '#dc3545',
                    'cliente_eliminado'   => '#fd7e14',
                    'deshabilitar_manual' => '#6c757d',
                    'pago_factura'        => '#28a745',
                ];
            @endphp

            @foreach($acciones as $clave => $descripcion)
                <div class="etiqueta-auto-card">
                    <div class="accion-row">
                        <div class="accion-icon">
                            <i class="{{ $iconos[$clave] ?? 'fas fa-tag' }}"
                               style="color: {{ $colores[$clave] ?? '#022454' }}"></i>
                        </div>
                        <div class="accion-body">
                            <h5>{{ $descripcion }}</h5>
                            <p class="desc">
                                Selecciona la etiqueta que se asignará automáticamente al contrato cuando ocurra esta acción.
                                Deja en blanco para no asignar ninguna etiqueta.
                            </p>
                            <div style="max-width: 420px;">
                                <select name="etiqueta_{{ $clave }}" class="form-control">
                                    <option value="">- Sin etiqueta -</option>
                                    @foreach($etiquetas as $etiqueta)
                                        <option value="{{ $etiqueta->id }}"
                                            {{ (isset($configuraciones[$clave]) && $configuraciones[$clave] == $etiqueta->id) ? 'selected' : '' }}>
                                            {{ $etiqueta->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="mt-4">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fas fa-save mr-2"></i> Guardar Configuración
                </button>
                <a href="{{ route('configuracion.index') }}" class="btn btn-outline-secondary ml-2">Cancelar</a>
            </div>
        </form>
    </div>
</div>

@endsection
