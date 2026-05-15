@extends('layouts.app')
@section('content')
<input type="hidden" id="valuefecha" value="{{$request->fechas}}">
<input type="hidden" id="primera" value="{{$request->date ? $request->date['primera'] : ''}}">
<input type="hidden" id="ultima" value="{{$request->date ? $request->date['ultima'] : ''}}">

	<form id="form-reporte">

	<div class="row card-description">
		<div class="form-group col-md-2">
		    {{-- <label>Tipo</label>
		    <select class="form-control selectpicker" name="tipo">
		    	<option value="1" {{1==$request->tipo?'selected':''}}>Emitidas</option>
		    	<option value="0" {{0==$request->tipo?'selected':''}}>No emitidas</option>
		    </select> --}}
	  	</div>
		<div class="form-group col-md-2">
		    <label></label>
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
					<input type="text" class="form-control"  id="desde" value="{{$request->fecha}}" name="fecha" required="" >
				</div>
				<div class="col-md-6">
					<label >Hasta <span class="text-danger">*</span></label>
	  				<input type="text" class="form-control" id="hasta" value="{{$request->hasta}}" name="hasta" required="">
				</div>

			</div>
	  	</div>
	  	<div class="form-group col-md-4" style=" padding-top: 24px;">
			{{-- <label>Incluir facturas</label>
			<i data-tippy-content="Incluir las facturas generadas el 30 del mes pasado. (el mes pasado se toma con la opción 'desde' escogida.)" class="icono far fa-question-circle" tabindex="0"></i>
		    <select class="form-control selectpicker" name="incluir">
		    	<option value="1" {{1==$request->tipo?'selected':''}}>Incluir facturas del 30 del mes pasado</option>
		    	<option value="0" {{2==$request->tipo?'selected':''}}>No Incluir</option>
		    </select> --}}
			<button type="button" id="generar" class="btn btn-outline-primary">Generar Reporte</button>
        	<button type="button" id="exportar" class="btn btn-outline-success">Exportar a Excel</button>
	  	</div>
	</div>

	{{-- <div class="row card-description">
		<div class="form-group col-md-4">
        	<button type="button" id="generar" class="btn btn-outline-primary">Generar Reporte</button>
        	<button type="button" id="exportar" class="btn btn-outline-success">Exportar a Excel</button>
	  	</div>
	</div> --}}

    <input type="hidden" name="orderby"id="order_by"  value="2">
    <input type="hidden" name="order" id="order" value="1">
    <input type="hidden" id="form" value="form-reporte">

	<div class="row card-description">
		<div class="col-md-12 table-responsive">
			<table class="table table-striped table-hover " id="table-facturas">
				<thead class="thead-dark">
					<tr>
						<th>Nivel</th>
						<th>Transaccional</th>
						<th>Código Cuenta</th>
						<th>Nombre Cuenta</th>
						<th>Identificación</th>
						<th>Sucursal</th>
						<th>Nombre Tercero</th>
						<th>Saldo Inicial</th>
						<th>Movimiento Débito</th>
						<th>Movimiento Crédito</th>
						<th>Saldo Final</th>
					</tr>
				</thead>
				<tbody>
					@foreach($movimientosContables as $mov)
						<tr>
							<td>{{$mov->nivel}}</td>
							<td>{{$mov->transaccional}}</td>
							<td>{{$mov->codigo_cuenta}}</td>
							<td>{{$mov->cuentacontable}}</td>
							<td>{{$mov->identificacion_tercero}}</td>
							<td>{{$mov->sucursal}}</td>
							<td>{{$mov->tercero_nombre}}</td>
							<td>{{Auth::user()->empresa()->moneda}} {{App\Funcion::Parsear($mov->saldo_inicial)}}</td>
							<td>{{Auth::user()->empresa()->moneda}} {{App\Funcion::Parsear($mov->totaldebito)}}</td>
							<td>{{Auth::user()->empresa()->moneda}} {{App\Funcion::Parsear($mov->totalcredito)}}</td>
							<td>{{Auth::user()->empresa()->moneda}} {{App\Funcion::Parsear($mov->saldo_final)}}</td>
						</tr>
					@endforeach
				</tbody>

			</table>
            {!! $movimientosContables->render() !!}
		</div>
	</div>
</form>
<input type="hidden" id="urlgenerar" value="{{route('reportes.balance')}}">
<input type="hidden" id="urlexportar" value="{{route('exportar.balance')}}">
@endsection
