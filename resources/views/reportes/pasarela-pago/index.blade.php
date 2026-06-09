@extends('layouts.app')
@section('content')
    <input type="hidden" id="valuefecha" value="{{$request->fechas}}">
    <div class="row card-description">
        <div class="col-md-12 ">
            <p class="card-description">Este reporte muestra las transacciones realizadas por los clientes a través de la pasarela de pagos Combo Pay o afines.</p>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h5 class="card-title text-white">Transacciones</h5>
                    <p class="card-text text-white" style="font-size: 24px;">{{ $totales['count'] }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h5 class="card-title text-white">Total Recaudado</h5>
                    <p class="card-text text-white" style="font-size: 24px;">{{ $moneda }}{{ App\Funcion::Parsear($totales['total']) }}</p>
                </div>
            </div>
        </div>
    </div>

    <form id="form-reporte">
        <div class="row card-description">
            <div class="form-group col-md-2">
                <label>Rango de Fechas</label>
                <select class="form-control selectpicker" name="fechas" id="fechas">
                    <optgroup label="Presente">
                        <option value="0">Hoy</option>
                        <option value="1">Este Mes</option>
                        <option value="2">Este Año</option>
                    </optgroup>
                    <optgroup label="Anterior">
                        <option value="3">Ayer</option>
                        <option value="4">Semana Pasada</option>
                        <option value="5">Mes Anterior</option>
                        <option value="6">Año Anterior</option>
                    </optgroup>
                    <optgroup label="Manual">
                        <option value="7" {{$request->fechas == 7 ? 'selected' : ''}}>Manual</option>
                    </optgroup>
                    <optgroup label="Todas">
                        <option value="8" {{$request->fechas == 8 ? 'selected' : ''}}>Todas</option>
                    </optgroup>
                </select>
            </div>
            <div class="form-group col-md-4">
                <div class="row">
                    <div class="col-md-6">
                        <label>Desde <span class="text-danger">*</span></label>
                        <input type="text" class="form-control"  id="desde" value="{{$request->fecha}}" name="fecha" required="" >
                    </div>
                    <div class="col-md-6">
                        <label >Hasta <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="hasta" value="{{$request->hasta}}" name="hasta" required="">
                    </div>
                </div>
            </div>
            <div class="form-group col-md-4" style=" padding-top: 24px;">
                <button type="button" id="generar" class="btn btn-outline-primary">Filtrar Reporte</button>
            </div>
        </div>

        <div class="row card-description">
            <div class="col-md-12 table-responsive">
                <table class="table table-striped table-hover " id="table-pasarela">
                    <thead class="thead-dark">
                    <tr>
                        <th>Fecha</th>
                        <th>Descripción</th>
                        <th>Monto</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ date('d-m-Y H:i A', strtotime($row->fecha)) }}</td>
                            <td>{{ $row->descripcion }}</td>
                            <td class="text-success font-weight-bold">+ {{ $moneda }}{{ App\Funcion::Parsear($row->saldo) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center">No se encontraron movimientos en este rango de fechas.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
                <div class="text-right">
                    {{ $rows->links() }}
                </div>
            </div>
        </div>
    </form>
    <input type="hidden" id="urlgenerar" value="{{route('reportes.pasarela_pago')}}">
@endsection
