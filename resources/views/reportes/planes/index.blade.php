@extends('layouts.app')
@section('content')
    <input type="hidden" id="valuefecha" value="{{$request->fechas}}">
    <div class="row card-description">
        <div class="col-md-12 ">
            <p  class="card-description">Este reporte muestra la distribución de suscriptores y facturación electrónica por cada plan de velocidad en un rango de fechas determinado.</p>
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
                <button type="button" id="generar" class="btn btn-outline-primary">Generar Reporte</button>
                <button type="button" id="exportar" class="btn btn-outline-success">Exportar a Excel</button>
            </div>
        </div>

        <div class="row card-description">
            <div class="col-md-12 table-responsive">
                <table class="table table-striped table-hover " id="table-reporte-planes">
                    <thead class="thead-dark">
                    <tr>
                        <th>Plan</th>
                        <th>Tipo</th>
                        <th>Municipio</th>
                        <th>Subida</th>
                        <th>Bajada</th>
                        <th>Estrato</th>
                        <th>Precio</th>
                        <th>Suscriptores</th>
                        <th>Facturados Electrónicamente</th>
                    </tr>
                    </thead>
                    <tbody>
                    @php 
                        $totalSusc = 0; 
                        $totalElec = 0; 
                    @endphp
                    @foreach($planes as $plan)
                        @php 
                            $totalSusc += $plan->suscriptores; 
                            $totalElec += $plan->facturados_electronicamente; 
                        @endphp
                        <tr>
                            <td>{{$plan->nombre_plan}}</td>
                            <td>{{$plan->type == 'TV' ? 'Televisión' : 'Internet'}}</td>
                            <td>{{$municipio}}</td>
                            <td>{{App\Funcion::parseSpeed($plan->subida)}}</td>
                            <td>{{App\Funcion::parseSpeed($plan->bajada)}}</td>
                            <td></td>
                            <td>{{Auth::user()->empresa()->moneda}}{{App\Funcion::Parsear($plan->precio)}}</td>
                            <td>{{$plan->suscriptores}}</td>
                            <td>{{$plan->facturados_electronicamente}}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot class="thead-dark">
                        <tr>
                            <th colspan="7" class="text-right">TOTALES</th>
                            <th>{{$totalSusc}}</th>
                            <th>{{$totalElec}}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </form>
    <input type="hidden" id="urlgenerar" value="{{route('reportes.reportePlanes')}}">
    <input type="hidden" id="urlexportar" value="{{route('exportar.reportePlanes')}}">
@endsection
