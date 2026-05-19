@extends('layouts.app')

@section('style')
    <style>
        .mapping-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .table-mapping thead th {
            background-color: #f8f9fa;
            color: #495057;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            padding: 1rem;
            border-bottom: 2px solid #dee2e6;
        }

        .table-mapping tbody td {
            padding: 1.2rem 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f1f1;
        }

        .product-name-container {
            max-width: 350px;
            display: block;
        }

        .product-name {
            font-weight: 500;
            color: #333;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 0.95rem;
        }

        .product-ref {
            font-size: 0.75rem;
            color: #888;
            display: block;
            margin-top: 2px;
        }

        .select-container {
            min-width: 300px;
        }

        .btn-save-container {
            background: #fff;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 -5px 15px rgba(0,0,0,0.02);
            margin-top: 1rem;
        }

        .card-mapping {
            border: none;
            background: transparent;
        }

        /* Ajuste para el selectpicker */
        .bootstrap-select > .btn {
            border: 1px solid #ced4da !important;
            background-color: #fff !important;
            padding: 0.6rem 1rem !important;
            font-size: 0.9rem !important;
        }
    </style>
@endsection

@section('content')
    @if (Session::has('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ Session::get('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <div class="container-fluid">
        <form method="POST" action="{{ route('siigo.save_productos') }}" role="form" class="forms-sample" novalidate id="form-termino">
            {{ csrf_field() }}

            <div class="mapping-container">
                <div class="table-responsive">
                    <table class="table table-mapping table-hover">
                        <thead>
                            <tr>
                                <th width="45%">Producto en Sistema</th>
                                <th width="55%">Producto en Siigo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($productos as $producto)
                                <tr>
                                    <td>
                                        <div class="product-name-container" title="{{ $producto->producto }}">
                                            <span class="product-name">{{ $producto->producto }}</span>
                                            <span class="product-ref">REF: {{ $producto->ref ?: 'N/A' }}</span>
                                            <input name="productos[]" type="hidden" value="{{ $producto->id }}">
                                        </div>
                                    </td>
                                    <td>
                                        <div class="select-container">
                                            <select class="form-control selectpicker" data-live-search="true" data-width="100%" name="siigo_productos[]">
                                                <option value="0" {{ $producto->siigo_id == 0 || $producto->siigo_id == null ? 'selected' : '' }}>
                                                    -- Seleccionar Producto Siigo --
                                                </option>
                                                @foreach ($productosSiigo as $prodSiigo)
                                                    <option value="{{ $prodSiigo['id']  . "|" . $prodSiigo['code'] }}"
                                                        {{ $producto->siigo_id == $prodSiigo['id'] ? 'selected' : '' }}
                                                        data-subtext="COD: {{ $prodSiigo['code'] }}">
                                                        {{ $prodSiigo['name'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="btn-save-container">
                <div class="row">
                    <div class="col-sm-12 text-right">
                        <a href="{{ route('configuracion.index') }}" class="btn btn-outline-secondary px-4 mr-2">Cancelar</a>
                        <button type="submit" class="btn btn-success px-5 font-weight-bold">Guardar Mapeo</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

