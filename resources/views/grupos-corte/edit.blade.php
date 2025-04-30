@extends('layouts.app')
@section('content')
<div class="alert alert-warning alert-dismissible fade show" role="alert">
	<a>Recuerda que si haces un cambio en la <strong>fecha de suspensión del grupo de corte,</strong> todas las facturas que tengan su
		<strong>fecha de vencimiento</strong> en el mismo mes que se realiza el cambio también cambiarán su fecha de vencimiento</a>
	<button type="button" class="close" data-dismiss="alert" aria-label="Close">
		<span aria-hidden="true">&times;</span>
	</button>
</div>
	<form method="POST" action="{{ route('grupos-corte.update', $grupo->id) }}" style="padding: 2% 3%;" role="form" class="forms-sample" novalidate id="form-banco" >
	    @csrf
	    <input name="_method" type="hidden" value="PATCH">
	    <div class="row">
	        <div class="col-md-3 form-group">
	            <label class="control-label">Nombre <span class="text-danger">*</span></label>
	            <input type="text" class="form-control"  id="nombre" name="nombre"  required="" value="{{$grupo->nombre}}" maxlength="200">
	            <span class="help-block error">
	                <strong>{{ $errors->first('nombre') }}</strong>
	            </span>
	        </div>
	        <div class="col-md-3 form-group">
	            <label class="control-label">Fecha de Factura <span class="text-danger">*</span></label>
	            <select class="form-control selectpicker" name="fecha_factura" id="fecha_factura" title="Seleccione" data-live-search="true" data-size="5">
	            	<option {{$grupo->fecha_factura==0?'selected':''}} value="0">No Aplica</option>
	            	@for ($i = 1; $i < 31; $i++)
	            	    <option {{$grupo->fecha_factura==$i?'selected':''}} value="{{$i}}">{{$i}}</option>
	            	@endfor
            	</select>
	            <span class="help-block error">
	                <strong>{{ $errors->first('fecha_factura') }}</strong>
	            </span>
	        </div>
	        <div class="col-md-3 form-group">
	            <label class="control-label">Fecha de Pago <span class="text-danger">*</span></label>
	            <select class="form-control selectpicker" name="fecha_pago" id="fecha_pago" title="Seleccione" data-live-search="true" data-size="5">
	            	<option {{$grupo->fecha_pago==0?'selected':''}} value="0">No Aplica</option>
	            	@for ($i = 1; $i < 31; $i++)
	            	    <option {{$grupo->fecha_pago==$i?'selected':''}} value="{{$i}}">{{$i}}</option>
	            	@endfor
            	</select>
	            <span class="help-block error">
	                <strong>{{ $errors->first('fecha_pago') }}</strong>
	            </span>
	        </div>
	        <div class="col-md-3 form-group">
	            <label class="control-label">Fecha Corte <span class="text-danger">*</span></label>
	            <select class="form-control selectpicker" name="fecha_corte" id="fecha_corte" title="Seleccione" data-live-search="true" data-size="5">
	            	<option {{$grupo->fecha_corte==0?'selected':''}} value="0">No Aplica</option>
	            	@for ($i = 1; $i < 31; $i++)
	            	    <option {{$grupo->fecha_corte==$i?'selected':''}} value="{{$i}}">{{$i}}</option>
	            	@endfor
            	</select>
	            <span class="help-block error">
	                <strong>{{ $errors->first('fecha_corte') }}</strong>
	            </span>
	        </div>
	        <div class="col-md-3 form-group">
	            <label class="control-label">Fecha Suspensión <span class="text-danger">*</span></label>
	            <select class="form-control selectpicker" name="fecha_suspension" id="fecha_suspension" title="Seleccione" data-live-search="true" data-size="5">
	            	<option {{$grupo->fecha_suspension==0?'selected':''}} value="0">No Aplica</option>
	            	@for ($i = 1; $i < 31; $i++)
	            	    <option {{$grupo->fecha_suspension==$i?'selected':''}} value="{{$i}}">{{$i}}</option>
	            	@endfor
            	</select>
	            <span class="help-block error">
	                <strong>{{ $errors->first('fecha_suspension') }}</strong>
	            </span>
	        </div>
	        <div class="col-md-3 form-group">
	            <label class="control-label">Hora de Suspensión desde:
                    <span class="text-danger">*</span>
                    <a><i data-tippy-content="Hora desde la que comienza la suspensión en el sistema. (Se hará en intervalos de 15 min por lotes de clientes)" class="icono far fa-question-circle"></i></a>
                </label>
	            <input type="text" class="timepicker form-control" id="hora_suspension" name="hora_suspension"  required="" value="{{$grupo->hora_suspension}}">
	            <span class="help-block error">
	                <strong>{{ $errors->first('hora_suspension') }}</strong>
	            </span>
	        </div>
            <div class="col-md-3 form-group">
	            <label class="control-label">Hora creción de factura desde:
                    <span class="text-danger">*</span>
                    <a><i data-tippy-content="Hora desde la que comienza la creación de facturas en el sistema. (Se hará en intervalos de 15 min por lotes de clientes)" class="icono far fa-question-circle"></i></a>
                </label>
	            <input type="text" class="timepicker-2 form-control" id="hora_creacion_factura" name="hora_creacion_factura"  required="" value="{{$grupo->hora_creacion_factura}}">
	            <span class="help-block error">
	                <strong>{{ $errors->first('hora_creacion_factura') }}</strong>
	            </span>
	        </div>
	        <div class="col-md-3 form-group">
	            <label class="control-label">Estado <span class="text-danger">*</span></label>
	            <select class="form-control selectpicker" name="status" id="status" title="Seleccione" required="">
	                <option value="1" {{ ($grupo->status == 1) ? 'selected' : '' }}>Habilitado</option>
	                <option value="0" {{ ($grupo->status == 0) ? 'selected' : '' }}>Deshabilitado</option>
	            </select>
	            <span class="help-block error">
	                <strong>{{ $errors->first('status') }}</strong>
	            </span>
	        </div>

            {{-- <div class="col-md-3 form-group">
	            <label class="control-label">Dias Prorroga suspensión TV <span class="text-danger">*</span></label>
                <a><i data-tippy-content="Si agregas un dia mayor a 0 se tomará en cuenta para darle un tiempo de espera con la ultima factura vencida para suspender la televisión." class="icono far fa-question-circle"></i></a>
                <input type="text" class="form-control"  id="prorroga_tv" name="prorroga_tv" value="{{$grupo->prorroga_tv}}" maxlength="200">
	            <span class="help-block error">
	                <strong>{{ $errors->first('status') }}</strong>
	            </span>
	        </div> --}}
	    </div>
	    <small>Los campos marcados con <span class="text-danger">*</span> son obligatorios</small>
	    <hr>
	    <div class="row" >
	        <div class="col-sm-12" style="text-align: right;  padding-top: 1%;">
	            <a href="{{route('grupos-corte.index')}}" class="btn btn-outline-secondary">Cancelar</a>
	            <button type="submit" id="submitcheck" onclick="submitLimit(this.id)" class="btn btn-success">Guardar</button>
	        </div>
	    </div>
	</form>
@endsection

@section('scripts')
    <script>
        $(document).ready(function() {
        	$('.timepicker').timepicker({
        		locale: 'es-es',
        		uiLibrary: 'bootstrap4',
        	});

            $('.timepicker-2').timepicker({
        		locale: 'es-es',
        		uiLibrary: 'bootstrap4',
        	});
        });
    </script>
@endsection
