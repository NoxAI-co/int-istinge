@extends('layouts.app')

@section('styles')

@endsection

@section('boton')
    @if(auth()->user()->modo_lectura())
        <div class="alert alert-warning text-left" role="alert">
            <h4 class="alert-heading text-uppercase">Integra Colombia: Suscripción Vencida</h4>
           <p>Si desea seguir disfrutando de nuestros servicios adquiera alguno de nuestros planes.</p>
<p>Medios de pago Nequi: 3206909290 Cuenta de ahorros Bancolombia 42081411021 CC 1001912928 Ximena Herrera representante legal. Adjunte su pago para reactivar su membresía</p>
        </div>
    @else
        <a href="{{route('saldoinicial.create')}}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Nuevo Comprobante</a>
        <a href="javascript:abrirFiltrador()" class="btn btn-info btn-sm my-1" id="boton-filtrar"><i class="fas fa-search"></i>Filtrar</a>
    @endif
@endsection

@section('content')

    @if(Session::has('success'))
        <div class="alert alert-success" style="margin-left: 2%;margin-right: 2%;">
	    {{Session::get('success')}}
        </div>
        <script type="text/javascript">
            setTimeout(function() {
                $('.alert').hide();
                $('.active_table').attr('class', ' ');
            }, 5000);
        </script>
    @endif
    
    @if(Session::has('danger'))
        <div class="alert alert-danger" style="margin-left: 2%;margin-right: 2%;">
	    {{Session::get('danger')}}
        </div>
        <script type="text/javascript">
            setTimeout(function() {
                $('.alert').hide();
                $('.active_table').attr('class', ' ');
            }, 5000);
        </script>
    @endif

    @if(isset($_SESSION['permisos']['814']))
        <div class="container-fluid">
        	<div class="row card-description" style="padding: 1% 1%; margin-bottom: 0;">
        		<div class="col-md-12 text-right">
        			<a href="{{route('contactos.importar')}}" class="btn btn-outline-success btn-sm"><i class="fas fa-file-upload"></i> Importar Contactos</a>
        		</div>
        	</div>
        </div>
    @endif

	@if((isset($tipo_usuario) && $tipo_usuario == 1 && isset($_SESSION['permisos']['3'])) || (isset($tipo_usuario) && $tipo_usuario == 0 && isset($_SESSION['permisos']['2'])))
	<div class="container-fluid d-none" id="form-filter">
		<fieldset>
			<legend>Filtro de Búsqueda</legend>
			<div class="card shadow-sm border-0 mb-3" style="background: #ffffff00 !important;">
				<div class="card-body py-0">
					<div class="row">
						<div class="col-md-2 pl-1 pt-1">
							<input type="text" placeholder="Nro" id="nro" class="form-control rounded">
						</div>
						<div class="col-md-3 pl-1 pt-1">
							<select title="Tipo de comprobante" class="form-control rounded selectpicker" id="tipo_comprobante" data-size="5" data-live-search="true">
								@foreach($tipos as $tipo)
									<option value="{{$tipo->nro}}">{{$tipo->nro}} - {{$tipo->nombre}}</option>
								@endforeach
							</select>
						</div>
						<div class="col-md-2 pl-1 pt-1">
							<input type="text" placeholder="Código cuenta" id="codigo_cuenta" class="form-control rounded">
						</div>
						<div class="col-md-3 pl-1 pt-1">
							<select title="Cliente" class="form-control rounded selectpicker" id="cliente" data-size="5" data-live-search="true">
								@foreach($contactos as $contacto)
									<option value="{{$contacto->id}}">{{$contacto->nombre}} {{$contacto->apellidos()}}</option>
								@endforeach
							</select>
						</div>
						<div class="col-md-2 pl-1 pt-1">
							<input type="text" placeholder="Fecha" id="fecha" class="form-control rounded datepicker">
						</div>
						<div class="col text-left">
							<a href="javascript:cerrarFiltrador()" class="btn btn-icons ml-1 btn-outline-danger rounded btn-sm p-1 float-right" title="Limpiar parámetros de busqueda"><i class="fas fa-times"></i></a>
							<a href="javascript:void(0)" id="filtrar" class="btn btn-icons btn-outline-info rounded btn-sm p-1 float-right" title="Iniciar busqueda avanzada"><i class="fas fa-search"></i></a>
						</div>
					</div>
				</div>
			</div>
		</fieldset>
	</div>

	<div class="row card-description">
		<div class="col-md-12">
			<table class="table table-striped table-hover w-100" id="tabla-contactos">
				<thead class="thead-dark">
					<tr>
						<th>nro</th>
						<th>tipo</th>
						<th>codigo</th>
						<th>cliente</th>
						<th>Detalle</th>
						<th>debito</th>
						<th>credito</th>
						<th>Acciones</th>
					</tr>
				</thead>
			</table>
		</div>
	</div>
	@endif
@endsection

@section('scripts')
<script>
    var tabla = null;
    window.addEventListener('load',
    function() {

		$('#tabla-contactos').DataTable({
			responsive: true,
			serverSide: true,
			processing: true,
			searching: false,
			language: {
				'url': '{{asset("vendors/DataTables/es.json")}}'
			},
			order: [
				[0, "asc"]
			],
			"pageLength": {{ Auth::user()->empresa()->pageLength }},
			ajax: '{{url("/saldos")}}',
			headers: {
				'X-CSRF-TOKEN': '{{csrf_token()}}'
			},
			columns: [
			    {data: 'nro'},
				{data: 'tipo_comprobante'},
				{data: 'codigo_cuenta'},
				{data: 'cliente'},
				{data: 'detalle'},
				{data: 'debito'},
				{data: 'credito'},
				{data: 'acciones'},
			]
		});


        tabla = $('#tabla-contactos');

        tabla.on('preXhr.dt', function(e, settings, data) {
            data.nro = $('#nro').val();
            data.tipo_comprobante = $('#tipo_comprobante').val();
            data.codigo_cuenta = $('#codigo_cuenta').val();
            data.cliente = $('#cliente').val();
            data.fecha = $('#fecha').val();
            data.filtro = true;
        });

        $('#filtrar').on('click', function(e) {
            getDataTable();
            return false;
        });

        $('#form-filter').on('keypress',function(e) {
            if(e.which == 13) {
                getDataTable();
                return false;
            }
        });
    });

	function getDataTable() {
		tabla.DataTable().ajax.reload();
	}

	function abrirFiltrador() {
		if ($('#form-filter').hasClass('d-none')) {
			$('#boton-filtrar').html('<i class="fas fa-times"></i> Cerrar');
			$('#form-filter').removeClass('d-none');
		} else {
			$('#boton-filtrar').html('<i class="fas fa-search"></i> Filtrar');
			cerrarFiltrador();
		}
	}

	function cerrarFiltrador() {
		$('#nro').val('');
		$('#tipo_comprobante').val('').selectpicker('refresh');
		$('#codigo_cuenta').val('');
		$('#cliente').val('').selectpicker('refresh');
		$('#fecha').val('');
		$('#form-filter').addClass('d-none');
		$('#boton-filtrar').html('<i class="fas fa-search"></i> Filtrar');
		getDataTable();
	}
</script>
@endsection
