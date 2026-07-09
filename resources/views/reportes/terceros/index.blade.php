@extends('layouts.app')
@section('content')
<input type="hidden" id="valuefecha" value="{{$request->fechas}}">
<input type="hidden" id="primera" value="{{$request->date ? $request->date['primera'] : ''}}">
<input type="hidden" id="ultima" value="{{$request->date ? $request->date['ultima'] : ''}}">

<form id="form-reporte">
    <div class="row card-description">
        <div class="form-group col-md-2">
            <label>Fechas</label>
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
                    <option value="7">Manual</option>
                </optgroup>
                <optgroup label="Todas">
                    <option value="8">Todas</option>
                </optgroup>
            </select>
        </div>
        <div class="form-group col-md-4">
            <div class="row">
                <div class="col-md-6">
                    <label>Desde <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="desde" value="{{$request->fecha}}" name="fecha">
                </div>
                <div class="col-md-6">
                    <label>Hasta <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="hasta" value="{{$request->hasta}}" name="hasta">
                </div>
            </div>
        </div>
        <div class="form-group col-md-6" style="padding-top: 24px;">
            <button type="button" id="generar" class="btn btn-outline-primary">Generar Reporte</button>
            <button type="button" id="exportar" class="btn btn-outline-success">Exportar a Excel</button>
        </div>
    </div>

    <input type="hidden" name="orderby" id="order_by" value="contactos.nombre">
    <input type="hidden" name="order" id="order" value="1">
    <input type="hidden" id="form" value="form-reporte">

    <div class="row card-description">
        <div class="col-md-12 table-responsive">
            <table class="table table-striped table-hover" id="table-facturas">
                <thead class="thead-dark">
                    <tr>
                        <th>Tipo ID</th>
                        <th>NIT</th>
                        <th>DV</th>
                        <th>Nombre / Razón Social</th>
                        <th>Teléfono</th>
                        <th>Email</th>
                        <th>Municipio</th>
                        <th>Departamento</th>
                        <th>Dirección</th> {{-- Dirección antes --}}
                        <th>Ingresos Brutos</th>
                        <th>Último Plan Facturado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($contactos as $c)
                        <tr>
                            <td>{{$c->tip_iden}}</td>
                            <td>{{$c->nit}}</td>
                            <td>{{$c->dv}}</td>
                            <td>{{$c->nombre}} {{$c->apellido1}} {{$c->apellido2}}</td>
                            <td>{{$c->telefono1 ?? $c->telefono2 ?? $c->celular}}</td>
                            <td>{{$c->email}}</td>
                            <td>{{$c->municipio}}</td>
                            <td>{{$c->departamento}}</td>
                            <td>{{$c->direccion}}</td>
                            <td>{{Auth::user()->empresa()->moneda}} {{App\Funcion::Parsear($c->ingresosBrutos)}}</td>
                            <td>{{$c->ultimo_plan ?? '—'}}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {!! $contactos->render() !!}
        </div>
    </div>
</form>

<input type="hidden" id="urlgenerar" value="{{route('reportes.terceros')}}">
<input type="hidden" id="urlexportar" value="{{route('exportar.terceros')}}">
@endsection
