@extends('layouts.app')


@section('boton')
    @if(auth()->user()->modo_lectura())
	    <div class="alert alert-warning text-left" role="alert">
	        <h4 class="alert-heading text-uppercase">Integra Colombia: Suscripción Vencida</h4>
	       <p>Si desea seguir disfrutando de nuestros servicios adquiera alguno de nuestros planes.</p>
<p>Medios de pago Nequi: 3206909290 Cuenta de ahorros Bancolombia 42081411021 CC 1001912928 Ximena Herrera representante legal. Adjunte su pago para reactivar su membresía</p>
	    </div>
	@else
        <a href="javascript:abrirFiltrador()" class="btn btn-info btn-sm my-1" id="boton-filtrar"><i class="fas fa-search"></i>Filtrar</a>
		<?php if(isset($_SESSION['permisos']['853'])){ ?>
		<a href="{{route('facturas.create-electronica')}}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Nueva Factura Electrónica</a>
		<?php } ?>
    @endif
@endsection

@section('content')
    @if(Session::has('success'))
        <div class="alert alert-success">
        	{{Session::get('success')}}
        </div>
        <script type="text/javascript">
        	setTimeout(function() {
        		$('.alert').hide();
        		$('.active_table').attr('class', ' ');
        	}, 5000);
        </script>
    @endif

    @if (session('message_denied_btw'))
        <div class="alert alert-danger">
            {!! session('message_denied_btw') !!}
        </div>
    @endif

	@if(Session::has('error'))
	<div class="alert alert-danger" >
		{{Session::get('error')}}
	</div>

	<script type="text/javascript">
		setTimeout(function(){
			$('.alert').hide();
			$('.active_table').attr('class', ' ');
		}, 8000);
	</script>
	@endif

    @if(Session::has('message_denied'))
	    <div class="alert alert-danger" role="alert">
	    	{{Session::get('message_denied')}}
	    	@if(Session::get('errorReason'))<br> <strong>Razon(es): <br></strong>
	    	    @if(is_string(Session::get('errorReason')))
	    	        {{Session::get('errorReason')}}
	    	    @elseif (count(Session::get('errorReason')) >= 1)
	    	        @php $cont = 0 @endphp
	    	        @foreach(Session::get('errorReason') as $error)
	    	            @php $cont = $cont + 1; @endphp
	    	            {{$cont}} - {{$error}} <br>
	    	        @endforeach
	    	    @endif
	    	@endif
	    	<button type="button" class="close" data-dismiss="alert" aria-label="Close">
	    		<span aria-hidden="true">&times;</span>
	    	</button>
	    </div>
	@endif

	@if(Session::has('message_success'))
	    <div class="alert alert-success" role="alert">
	    	{{Session::get('message_success')}}
	    	<button type="button" class="close" data-dismiss="alert" aria-label="Close">
	    		<span aria-hidden="true">&times;</span>
	    	</button>
	    </div>
	@endif

    @if(isset($dianFecthSync) && $dianFecthSync)

         <div class="alert alert-warning alert-dismissible fade show" role="alert">
             <button type="button" class="close d-none d-md-block" data-dismiss="alert" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
            </button>
            <button type="button" class="btn btn-warning btn-sm w-100 d-block d-md-none mb-2" data-dismiss="alert">
                Cerrar
            </button>
             <strong>⚠️ Volver a emitir facturas: {{ $dianFecthSync }} para revalidar el estado de emisión</strong><br>
        </div>

    @endif

    @if(isset($reporteFaltantes) && !empty($reporteFaltantes['faltantes']) && 1==2)
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <button type="button" class="close d-none d-md-block" data-dismiss="alert" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
        </button>
        <button type="button" class="btn btn-warning btn-sm w-100 d-block d-md-none mb-2" data-dismiss="alert">
            Cerrar
        </button>
        <strong>⚠️ Consecutivos faltantes detectados</strong><br>
        Prefijo: <b>{{ $reporteFaltantes['prefijo'] }}</b><br>
        Rango: <b>{{ $reporteFaltantes['inicio'] }} - {{ $reporteFaltantes['final'] }}</b><br>
        Último usado: <b>{{ $reporteFaltantes['ultimo_usado'] }}</b><br>
        Faltantes:
        <span class="text-danger">
            @if(count($reporteFaltantes['faltantes']) > 100)
                {{ implode(', ', array_slice($reporteFaltantes['faltantes'], 0, 100)) }}
                y {{ count($reporteFaltantes['faltantes']) - 100 }} más.
            @else
                {{ implode(', ', $reporteFaltantes['faltantes']) }}
            @endif
        </span><br>
        @if(isset($_SESSION['permisos']['43']))
        <button type="button" class="btn btn-primary btn-sm mt-2" onclick="$('#modalRenumerar').modal('show');">
            <i class="fas fa-sort-numeric-down"></i> Renumerar facturas no emitidas
        </button>
        @else
        <span>Por favor crea manualmente facturas con estos consecutivos.</span>
        @endif
    </div>
    @else
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <button type="button" class="close d-none d-md-block" data-dismiss="alert" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
        </button>
        <button type="button" class="btn btn-info btn-sm w-100 d-block d-md-none mb-2" data-dismiss="alert">
            Cerrar
        </button>
        <span>✅ No tienes consecutivos salteados.</span>
    </div>
    @endif


	<div class="container-fluid d-none" id="form-filter">
		<fieldset>
            <legend>Filtro de Búsqueda</legend>
			<div class="card shadow-sm border-0">
				<div class="card-body pb-3 pt-2" style="background: #f9f9f9;">
					<div class="row">
						<div class="col-md-2 pl-1 pt-1">
							<input type="text" placeholder="Nro" id="codigo" class="form-control rounded">
						</div>
						<div class="col-md-2 pl-1 pt-1">
							<select title="Cliente" class="form-control rounded selectpicker" id="cliente" data-size="5" data-live-search="true">
								@foreach ($clientes as $cliente)
									<option value="{{ $cliente->id}}">{{ $cliente->nombre}} {{$cliente->apellido1}} {{$cliente->apellido2}} - {{ $cliente->nit}}</option>
								@endforeach
							</select>
						</div>
						<div class="col-md-2 pl-1 pt-1">
							<select title="Municipio" class="form-control rounded selectpicker" id="municipio" data-size="5" data-live-search="true">
								@foreach ($municipios as $municipio)
									<option value="{{ $municipio->id}}">{{ $municipio->nombre}}</option>
								@endforeach
							</select>
						</div>
                        <div class="col-md-2 pl-1 pt-1">
                            <select title="barrio" class="form-control rounded selectpicker" id="barrio" data-size="5" data-live-search="true">
								@foreach ($barrios as $barrio)
									<option value="{{ $barrio->id}}">{{ $barrio->nombre}}</option>
								@endforeach
							</select>
                        </div>
                         <div class="col-md-3 pl-1 pt-1">
                            <select title="Factura prorrateada" class="form-control rounded selectpicker" id="prorrateo">
                                <option value="">Ambas</option>
                                <option value="1">Si</option>
                                <option value="0">No</option>
                            </select>
                        </div>

                        <div class="col-md-2 pl-1 pt-1 position-relative">
                            <input type="date" id="desde" name="desde" class="form-control rounded" autocomplete="off">
                            <label for="desde" class="placeholder">Desde</label>
                        </div>
                        <div class="col-md-2 pl-1 pt-1 position-relative">
                            <input type="date" id="hasta" name="hasta" class="form-control rounded" autocomplete="off">
                            <label for="hasta" class="placeholder">Hasta</label>
                        </div>
						<div class="col-md-2 pl-1 pt-1">
							<select title="Servidor" class="form-control rounded selectpicker" id="servidor">
								@foreach ($servidores as $servidor)
								<option value="{{ $servidor->id}}">{{ $servidor->nombre}}</option>
								@endforeach
							</select>
						</div>
						<div class="col-md-2 pl-1 pt-1">
							<select title="Estado" class="form-control rounded selectpicker" id="estado">
								<option value="1">Abierta</option>
								<option value="A">Cerrada</option>
								<option value="2">Anulada</option>
							</select>
						</div>
						<div class="col-md-2 pl-1 pt-1">
							<select title="Emisión" class="form-control rounded selectpicker" id="emision">
								<option value="1">Emitida</option>
								<option value="0">No emitida</option>
								<option value="2">Error emisión</option>
							</select>
						</div>
                        <div class="col-md-2 pl-1 pt-1">
							<select title="Grupo Corte" class="form-control rounded selectpicker" name="grupos_corte" id="grupos_corte" multiple data-live-search="true">
                                @foreach($grupos_corte as $grupo)
                                    <option value="{{$grupo->id}}">{{$grupo->nombre}}</option>
                                @endforeach
							</select>
						</div>
						<div class="col-md-3 pl-1 pt-1">
							<select title="Planes" class="form-control rounded selectpicker" id="plan" name="plan[]" data-size="5" data-live-search="true" multiple>
								@foreach ($planes as $p)
									<option value="{{ $p->id }}">{{ $p->name }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-md-3 pl-1 pt-1">
							<select title="Planes de TV" class="form-control rounded selectpicker" id="plan_tv" name="plan_tv[]" data-size="5" data-live-search="true" multiple>
								@foreach ($planestv as $ptv)
									<option value="{{ $ptv->id }}">{{ $ptv->producto }}</option>
								@endforeach
							</select>
						</div>
                        @if ($empresa->token_siigo != null || $empresa->token_siigo != '')
                        <div class="col-md-2 pl-1 pt-1">
							<select title="¿Envía a siigo?" class="form-control rounded selectpicker" name="fact_siigo" id="fact_siigo" multiple data-live-search="true">
                                    <option value="1">Si</option>
                                    <option value="0">No</option>
							</select>
						</div>
                        @endif
                        <div class="col-md-3 pl-1 pt-1" style="display: flex; align-items: center;">
							<select title="Otras opciones" class="form-control rounded selectpicker" id="otras_opciones" data-size="5" data-toggle="tooltip" data-placement="top" title="" style="flex: 1;">
								<option value="">Otras opciones</option>
								<option value="ultimas_contratos">Últimas facturas por contratos</option>
								<option value="clientes_multiples_facturas">Clientes con más de 1 factura</option>
							</select>
							<span id="tooltip_clientes_multiples" style="display: none; margin-left: 5px;">
								<a><i data-tippy-content="Si el cliente tiene más de una factura (a partir de 2 facturas) saldrán en la tabla, usa las fechas desde - hasta para obtener mayor precisión y saber si un cliente se le generó varias veces la facturación en un mes." class="icono far fa-question-circle"></i></a>
							</span>
						</div>
						<div class="col-md-3 pl-1 pt-1">
							<select title="¿Enviado al correo?" class="form-control rounded selectpicker" id="correo" multiple data-live-search="false">
								<option value="1">Si</option>
								<option value="0">No</option>
								<option value="400">Error envío</option>
							</select>
						</div>
					</div>

					<div class="row">
						<div class="col-md-12 pl-1 pt-1 text-center">
							<a href="javascript:limpiarFiltrador()" class="btn btn-icons ml-1 btn-outline-danger rounded btn-sm p-1" title="Limpiar parámetros de busqueda"><i class="fas fa-times"></i></a>
							<a href="javascript:void(0)" id="filtrar" class="btn btn-icons btn-outline-info rounded btn-sm p-1" title="Iniciar busqueda avanzada"><i class="fas fa-search"></i></a>
							<a href="javascript:exportar()" class="btn btn-icons mr-1 btn-outline-success rounded btn-sm p-1" title="Exportar"><i class="fas fa-file-excel"></i></a>
						</div>
					</div>
				</div>
			</div>
		</fieldset>
	</div>

	<div class="row card-description">
		<div class="col-md-12">
    		<div class="container-filtercolumn form-inline">
    			<div class="dropdown mr-1">
                    <button class="btn btn-warning dropdown-toggle" type="button" id="dropdownOtrasAcciones" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Otras Acciones
                    </button>
                    <div class="dropdown-menu" aria-labelledby="dropdownOtrasAcciones">
                        @if(isset($_SESSION['permisos']['750']))
                        <a class="dropdown-item" href="{{route('campos.organizar', 4)}}"><i class="fas fa-table"></i> Organizar Tabla</a>
                        @endif
                        @if(Auth::user()->empresa()->efecty == 1)
                        <a class="dropdown-item" href="{{route('facturas.downloadefecty')}}"><i class="fas fa-cloud-download-alt"></i> Descargar Archivo Efecty</a>
                        @endif
                        @if(isset($_SESSION['permisos']['774']))
                        <a class="dropdown-item" href="{{route('promesas-pago.index')}}"><i class="fas fa-calendar"></i> Ver Promesas de Pago</a>
                        @endif
                    </div>
                </div>
				<div class="dropdown mr-1">
                    <button class="btn btn-warning dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Acciones en Lote
                    </button>
                    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                        <a class="dropdown-item" href="javascript:void(0)" id="btn_emitir"><i class="fas fa-server"></i> Emitir Facturas en Lote</a>
                        <a class="dropdown-item" href="javascript:void(0)" id="btn_convertir_estandar"><i class="fas fa-exchange-alt"></i> Convertir a facturas estándar en Lote</a>
                        <a class="dropdown-item" href="javascript:void(0)" id="btn_siigo"><i class="fas fa-server"></i> Enviar a Siigo en lote</a>
                        <a class="dropdown-item" href="javascript:void(0)" id="btn_imp_fac"><i class="fas fa-file-excel"></i> Imprimir facturas</a>
                        <a class="dropdown-item" href="javascript:void(0)" id="btn_enviar_correo"><i class="fas fa-envelope"></i> Enviar al correo</a>
                        <a class="dropdown-item" href="javascript:void(0)" id="btn_enviar_whatsapp_lote"><i class="fab fa-whatsapp"></i> Enviar por WhatsApp en lote</a>
                        @if(isset($_SESSION['permisos']['855']))
                        <a class="dropdown-item text-danger" href="javascript:void(0)" id="btn_eliminar"><i class="fas fa-trash"></i> Eliminar facturas en lote</a>
                        @endif
                    </div>
                </div>
			</div>
		</div>
		<div class="col-md-12">
			<table class="table table-striped table-hover w-100" id="tabla-facturas">
				<thead class="thead-dark">
					<tr>
						@foreach($tabla as $campo)
    					    <th>{{$campo->nombre}}</th>
    					@endforeach
						<th>Acciones</th>
					</tr>
				</thead>
			</table>
		</div>
	</div>

	<div class="modal fade" id="promesaPago" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">GENERAR PROMESA DE PAGO</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div id="div_promesa"></div>
            </div>
        </div>
    </div>

    {{-- Modal Renumerar Consecutivos --}}
    @if(isset($reporteFaltantes) && !empty($reporteFaltantes['faltantes']))
    <div class="modal fade" id="modalRenumerar" role="dialog" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Renumerar facturas no emitidas</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Se renumerarán las facturas <strong>no emitidas</strong> (emitida = 0) y <strong>sin intentos de emisión DIAN</strong> que coincidan con los filtros actuales de la tabla.
                        Las facturas <strong>emitidas</strong> nunca serán modificadas.
                    </div>
                    <div class="form-group">
                        <label>Prefijo</label>
                        <input type="text" class="form-control" value="{{ $reporteFaltantes['prefijo'] }}" readonly>
                    </div>
                    <div class="form-group">
                        <label>Desde número <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="renumerar-desde" value="{{ $reporteFaltantes['faltantes'][0] ?? '' }}" min="1">
                        <small class="text-muted">Se sugiere el primer consecutivo faltante: <strong>{{ $reporteFaltantes['faltantes'][0] ?? 'N/A' }}</strong></small>
                    </div>
                    <div id="error-renumerar" class="alert alert-danger" style="display:none;"></div>
                    <div id="resultado-renumerar" class="alert alert-success" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="btn-renumerar" onclick="confirmarRenumerar()">
                        <i class="fas fa-check"></i> Renumerar
                    </button>
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                </div>
            </div>
        </div>
    </div>
    @endif
    {{--/Modal Renumerar Consecutivos --}}
@endsection

@section('style')
<style>
    .placeholder {
        position: absolute;
        top: 50%;
        left: 10px;
        transform: translateY(-50%);
        color: #6c757d;
        pointer-events: none;
        transition: all 0.2s ease-in-out;
    }
    input:focus + .placeholder,
    input:not(:placeholder-shown) + .placeholder {
        top: 5px;
        font-size: 12px;
        color: #495057;
    }
</style>
@endsection

@section('scripts')
<script>
	var tabla = $('#tabla-facturas');

	// Inicializar tooltips
	$(document).ready(function() {
		$('[data-toggle="tooltip"]').tooltip();
	});

	window.addEventListener('load', function() {
		var tabla = $('#tabla-facturas').DataTable({
			responsive: true,
			serverSide: true,
			processing: true,
			searching: false,
			language: {
				'url': '{{asset("vendors/DataTables/es.json")}}'
			},
			order: [
				[4, "DESC"],[0, "DESC"]
			],
			"pageLength": {{ Auth::user()->empresa()->pageLength }},
			ajax: '{{url("/facturas-electronicas")}}',
			headers: {
				'X-CSRF-TOKEN': '{{csrf_token()}}'
			},
			@if(isset($_SESSION['permisos']['830']))
			select: true,
            select: {
                style: 'multi',
            },
            dom: 'Blfrtip',
            buttons: [{
                text: '<i class="fas fa-check"></i> Seleccionar todos',
                action: function() {
                    tabla.rows({
                        page: 'current'
                    }).select();
                }
            },
            {
                text: '<i class="fas fa-times"></i> Deseleccionar todos',
                action: function() {
                    tabla.rows({
                        page: 'current'
                    }).deselect();
                }
            }],
            @endif
			columns: [
				@foreach($tabla as $campo)
                {data: '{{$campo->campo}}'},
                @endforeach
				{data: 'acciones'},
			]
		});

		// Establecer valores por defecto para desde y hasta (2025-2026)
		$('#desde').val('2025-01-01');
		$('#hasta').val('2026-12-31');

		tabla.on('preXhr.dt', function(e, settings, data) {
			data.codigo = $('#codigo').val();
			data.corte = $('#corte').val();
			data.prorrateo = $('#prorrateo').val();
			data.cliente = $('#cliente').val();
			data.municipio = $('#municipio').val();
			data.vendedor = $('#vendedor').val();
            data.barrio = $('#barrio').val();
			// data.creacion = $('#creacion').val();
			// data.vencimiento = $('#vencimiento').val();
			data.desde = $('#desde').val();
			data.hasta = $('#hasta').val();
			data.comparador = $('#comparador').val();
			data.total = $('#total').val();
			data.servidor = $('#servidor').val();
			data.estado = $('#estado').val();
			data.grupos_corte = $('#grupos_corte').val();
			data.fact_siigo = $('#fact_siigo').val();
			data.emision = $('#emision').val();
			data.correo = $('#correo').val();
			data.plan = $('#plan').val();
			data.plan_tv = $('#plan_tv').val();
			data.otras_opciones = $('#otras_opciones').val();
			data.filtro = true;
		});

		$('#filtrar').on('click', function(e) {
			getDataTable();
			return false;
		});

		$('#form-filter').on('keypress', function(e) {
			if (e.which == 13) {
				getDataTable();
				return false;
			}
		});

		$('#codigo').on('keyup',function(e) {
            if(e.which > 32 || e.which == 8) {
                getDataTable();
                return false;
            }
        });

		$('#cliente, #municipio, #estado, #correo, #creacion, #vencimiento, #desde, #hasta, #barrio, #grupos_corte, #plan, #plan_tv, #fact_siigo, #emision, #prorrateo, #servidor').on('change',function() {
            getDataTable();
            return false;
        });

		// Inicializar tooltip después de que selectpicker esté listo
		$('#otras_opciones').on('loaded.bs.select', function() {
			$(this).tooltip({
				placement: 'top',
				trigger: 'hover'
			});
		});

		// Manejar tooltip y ejecución automática cuando cambie la selección
		$('#otras_opciones').on('changed.bs.select', function() {
			var selectedValue = $(this).val();

			// Mostrar/ocultar icono de tooltip según la opción seleccionada
			if (selectedValue === 'clientes_multiples_facturas') {
				$('#tooltip_clientes_multiples').show();
				// Reinicializar tippy para el nuevo elemento visible
				// tippy se inicializa globalmente con .icono, pero necesitamos reinicializarlo para elementos dinámicos
				if (typeof tippy !== 'undefined') {
					setTimeout(function() {
						var iconElement = document.querySelector('#tooltip_clientes_multiples .icono');
						if (iconElement && !iconElement._tippy) {
							tippy(iconElement, {
								content(reference) {
									return reference.getAttribute('data-tippy-content');
								},
								animation: 'perspective',
								arrow: true,
								arrowType: 'sharp',
								interactive: true,
								allowHTML: true
							});
						}
					}, 100);
				}
			} else {
				$('#tooltip_clientes_multiples').hide();
			}

			// Ejecutar filtro automáticamente si hay una opción seleccionada
			if (selectedValue) {
				getDataTable();
			}
		});

		$('.vencimiento').datepicker({
			locale: 'es-es',
      		uiLibrary: 'bootstrap4',
			format: 'yyyy-mm-dd' ,
		});

		$('.creacion').datepicker({
			locale: 'es-es',
      		uiLibrary: 'bootstrap4',
			format: 'yyyy-mm-dd' ,
		});

		$('#tabla-facturas tbody').on('click', 'tr', function () {
			var table = $('#tabla-facturas').DataTable();
			var nro = table.rows('.selected').data().length;

			if(table.rows('.selected').data().length >= 0){
				$("#btn_emitir").removeClass('disabled d-none');
                $("#btn_imp_fac").removeClass('disabled d-none');
                $("#btn_convertir_estandar").removeClass('disabled d-none');
			}else{
				$("#btn_emitir").addClass('disabled d-none');
                $("#btn_imp_fac").removeClass('disabled d-none');
                $("#btn_convertir_estandar").addClass('disabled d-none');
			}
        });

        $('#btn_emitir').on('click', function(e) {
            var table = $('#tabla-facturas').DataTable();
            var nro = table.rows('.selected').data().length;

            if (nro <= 0) {
                swal({
                    title: 'ERROR',
                    html: 'Para ejecutar esta acción, debe al menos seleccionar una factura electrónica',
                    type: 'error',
                });
                return false;
            }

            var facturas = [];
            for (i = 0; i < nro; i++) {
                facturas.push(table.rows('.selected').data()[i]['id']);
            }

            swal({
                title: '¿Desea realizar la emisión de ' + nro + ' facturas electrónicas?',
                text: 'Esto puede demorar unos minutos. Al Aceptar, no podrá cancelar el proceso',
                type: 'question',
                showCancelButton: true,
                confirmButtonColor: '#00ce68',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Aceptar',
                cancelButtonText: 'Cancelar',
            }).then((result) => {
                if (result.value) {
                    cargando(true);

                    var url = window.location.pathname.split("/")[1] === "software" ?
                        `/software/empresa/facturas/emisionmasivaxml/` + facturas :
                        `/empresa/facturas/emisionmasivaxml/` + facturas;

                    $.ajax({
                        url: url,
                        method: 'GET',
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                        success: function(data) {
                            cargando(false);

                            if(data.success == false){
                                let html = data.text;
                                if(data.detalles_errores && data.detalles_errores.length > 0){
                                    html += '<br><br><ul style="text-align: left;">';
                                    data.detalles_errores.forEach(function(error) {
                                        html += '<li>' + error + '</li>';
                                    });
                                    html += '</ul>';
                                }

                                swal({
                                    title: 'ERROR DE VALIDACIÓN',
                                    html: html,
                                    type: 'error',
                                    showConfirmButton: true,
                                    confirmButtonColor: '#d33',
                                    confirmButtonText: 'ACEPTAR',
                                });
                            } else {
                                swal({
                                    title: 'PROCESO REALIZADO',
                                    html: data.text,
                                    type: 'success',
                                    showConfirmButton: true,
                                    confirmButtonColor: '#1A59A1',
                                    confirmButtonText: 'ACEPTAR',
                                });
                            }
                            getDataTable();
                        },
                        error: function(xhr) {
                            cargando(false);
                            if (xhr.status === 500) {
                                swal({
                                    title: 'INFO',
                                    html: 'Se han emitido algunas facturas, vuelve a emitir otro lote si quedan facturas pendientes.',
                                    type: 'info',
                                    showConfirmButton: true,
                                    confirmButtonColor: '#d33',
                                    confirmButtonText: 'Recargar Página',
                                }).then(() => {
                                    location.reload();
                                });
                            }
                        }
                    });
                }
            });
            console.log(facturas);
        });

        $('#btn_convertir_estandar').on('click', function(e) {
            var table = $('#tabla-facturas').DataTable();
            var nro = table.rows('.selected').data().length;

            if (nro <= 0) {
                swal({
                    title: 'ERROR',
                    html: 'Para ejecutar esta acción, debe al menos seleccionar una factura electrónica',
                    type: 'error',
                });
                return false;
            }

            var facturas = [];
            for (i = 0; i < nro; i++) {
                facturas.push(table.rows('.selected').data()[i]['id']);
            }

            swal({
                title: '¿Desea convertir ' + nro + ' facturas electrónicas a estándar?',
                text: 'Esto puede demorar unos minutos. Al Aceptar, no podrá cancelar el proceso',
                type: 'question',
                showCancelButton: true,
                confirmButtonColor: '#00ce68',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Aceptar',
                cancelButtonText: 'Cancelar',
            }).then((result) => {
                if (result.value) {
                    cargando(true);

                    var url = window.location.pathname.split("/")[1] === "software" ?
                        `/software/empresa/facturas/conversionmasiva-estandar/` + facturas.join(',') :
                        `/empresa/facturas/conversionmasiva-estandar/` + facturas.join(',');

                    $.ajax({
                        url: url,
                        method: 'GET',
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                        success: function(data) {
                            cargando(false);

                            if(data.success == false){
                                swal({
                                    title: 'ERROR',
                                    html: data.message,
                                    type: 'error',
                                    showConfirmButton: true,
                                    confirmButtonColor: '#d33',
                                    confirmButtonText: 'ACEPTAR',
                                });
                                return false;
                            }else{
                                swal({
                                title: 'PROCESO REALIZADO',
                                html: data.text,
                                type: 'success',
                                showConfirmButton: true,
                                confirmButtonColor: '#1A59A1',
                                confirmButtonText: 'ACEPTAR',
                            });
                            }
                            getDataTable();
                        },
                        error: function(xhr) {
                            cargando(false);
                            if (xhr.status === 500) {
                                swal({
                                    title: 'INFO',
                                    html: 'Se han convertido algunas facturas, vuelve a convertir otro lote si quedan facturas pendientes.',
                                    type: 'info',
                                    showConfirmButton: true,
                                    confirmButtonColor: '#d33',
                                    confirmButtonText: 'Recargar Página',
                                }).then(() => {
                                    location.reload();
                                });
                            }
                        }
                    });
                }
            });
            console.log(facturas);
        });

        $('#btn_siigo').on('click', function(e) {
            var table = $('#tabla-facturas').DataTable();
            var nro = table.rows('.selected').data().length;

            if (nro <= 0) {
                swal({
                    title: 'ERROR',
                    html: 'Para ejecutar esta acción, debe al menos seleccionar una factura electrónica',
                    type: 'error',
                });
                return false;
            }

            var facturas = [];
            for (i = 0; i < nro; i++) {
                facturas.push(table.rows('.selected').data()[i]['id']);
            }

            swal({
                title: '¿Desea enviar ' + nro + ' facturas a Siigo?',
                text: 'Esto puede demorar unos minutos. Al Aceptar, no podrá cancelar el proceso',
                type: 'question',
                showCancelButton: true,
                confirmButtonColor: '#00ce68',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Aceptar',
                cancelButtonText: 'Cancelar',
            }).then((result) => {
                if (result.value) {
                    cargando(true);

                    var url = window.location.pathname.split("/")[1] === "software" ?
                        `/software/empresa/facturas/enviomasivosiigo/` + facturas :
                        `/empresa/facturas/enviomasivosiigo/` + facturas;
                    $.ajax({
                        url: url,
                        method: 'GET',
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                        success: function(data) {
                        cargando(false);

                        if (data.success == false) {
                            swal({
                                title: 'ERROR',
                                html: data.message,
                                type: 'error',
                                confirmButtonColor: '#d33',
                                confirmButtonText: 'ACEPTAR',
                            });
                            return false;
                        } else {
                            let html = '<ul>';
                            data.resultados.forEach(function(res) {
                                if (res.resultado.status === 200) {
                                    html += `<li style="color:green;">Factura ${res.codigo}: ${res.resultado.message}</li>`;
                                } else {
                                    html += `<li style="color:red;">Factura ${res.codigo}: ${res.resultado.error}</li>`;
                                }
                            });
                            html += '</ul>';

                            swal({
                                title: 'PROCESO REALIZADO',
                                html: html,
                                type: 'success',
                                confirmButtonColor: '#1A59A1',
                                confirmButtonText: 'ACEPTAR',
                            });
                        }
                        getDataTable();
                    },
                        error: function(xhr) {
                            cargando(false);
                            if (xhr.status === 500) {
                                swal({
                                    title: 'INFO',
                                    html: 'Se han enviado algunas facturas a siigo, vuelve a enviar otro lote.',
                                    type: 'info',
                                    showConfirmButton: true,
                                    confirmButtonColor: '#d33',
                                    confirmButtonText: 'Recargar Página',
                                }).then(() => {
                                    location.reload();
                                });
                            }
                        }
                    });
                }
            });
            console.log(facturas);
        });

        $('#btn_imp_fac').on('click', function(e) {
			var table = $('#tabla-facturas').DataTable();
			var nro = table.rows('.selected').data().length;

			if(nro <= 0){
				swal({
					title: 'ERROR',
					html: 'Para ejecutar esta acción, debe al menos seleccionar una factura.',
					type: 'error',
				});
				return false;
			}

			var facturas = [];
			for (i = 0; i < nro; i++) {
				facturas.push(table.rows('.selected').data()[i]['id']);
			}

			swal({
				title: '¿Desea imprimir '+nro+' facturas?',
				text: 'Esto puede demorar unos minutos. Al Aceptar, no podrá cancelar el proceso',
				type: 'question',
				showCancelButton: true,
				confirmButtonColor: '#00ce68',
				cancelButtonColor: '#d33',
				confirmButtonText: 'Aceptar',
				cancelButtonText: 'Cancelar',
			}).then((result) => {
				if (result.value) {
					cargando(true);

					const baseUrl = "{{ url('empresa/facturas/impresionmasiva') }}";
					const url = `${baseUrl}/${facturas.join(',')}`;
					window.open(url, '_blank');

					cargando(false);

					swal({
						title: 'PROCESO REALIZADO',
						html: 'Las facturas están siendo generadas en una nueva pestaña.',
						type: 'success',
						showConfirmButton: true,
						confirmButtonColor: '#1A59A1',
						confirmButtonText: 'ACEPTAR',
					});
				}
			})
		});

        $('#btn_eliminar').on('click', function(e) {
            var table = $('#tabla-facturas').DataTable();
            var nro = table.rows('.selected').data().length;

            if(nro <= 0){
                swal({
                    title: 'ERROR',
                    html: 'Para ejecutar esta acción, debe al menos seleccionar una factura.',
                    type: 'error',
                });
                return false;
            }

            var facturas = [];
            for (i = 0; i < nro; i++) {
                facturas.push(table.rows('.selected').data()[i]['id']);
            }

            swal({
                title: '⚠️ ACCIÓN PELIGROSA',
                html: '¿Desea eliminar ' + nro + ' facturas electrónicas?<br><br>' +
                      '<strong style="color: #d33;">Esta acción es irreversible y no se puede retroceder.</strong><br><br>' +
                      '<strong>IMPORTANTE:</strong> Recuerde que después de eliminar estas facturas, debe acomodar el siguiente número de facturación en numeraciones para que no queden saltos de facturas.',
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
            }).then((result) => {
                if (result.value) {
                    cargando(true);

                    var url = window.location.pathname.split("/")[1] === "software" ?
                        `/software/empresa/facturas/eliminarmasiva/` + facturas.join(',') :
                        `/empresa/facturas/eliminarmasiva/` + facturas.join(',');

                    $.ajax({
                        url: url,
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                        success: function(data) {
                            cargando(false);

                            if(data.success == false){
                                swal({
                                    title: 'ERROR',
                                    html: data.message || data.text || 'Ocurrió un error al eliminar las facturas',
                                    type: 'error',
                                    showConfirmButton: true,
                                    confirmButtonColor: '#d33',
                                    confirmButtonText: 'ACEPTAR',
                                });
                                return false;
                            }else{
                                let html = data.text || 'Proceso de eliminación masiva terminado';
                                if(data.errores && data.errores.length > 0){
                                    html += '<br><br><strong>Facturas que no pudieron eliminarse:</strong><ul>';
                                    data.errores.forEach(function(error) {
                                        html += '<li>' + error + '</li>';
                                    });
                                    html += '</ul>';
                                }

                                swal({
                                    title: 'PROCESO REALIZADO',
                                    html: html,
                                    type: 'success',
                                    showConfirmButton: true,
                                    confirmButtonColor: '#1A59A1',
                                    confirmButtonText: 'ACEPTAR',
                                });
                            }
                            getDataTable();
                        },
                        error: function(xhr) {
                            cargando(false);
                            let errorMessage = 'No se pudo eliminar las facturas. Por favor, inténtelo de nuevo más tarde.';
                            if(xhr.responseJSON && xhr.responseJSON.message){
                                errorMessage = xhr.responseJSON.message;
                            }
                            swal({
                                title: 'ERROR',
                                html: errorMessage,
                                type: 'error',
                                showConfirmButton: true,
                                confirmButtonColor: '#d33',
                                confirmButtonText: 'ACEPTAR',
                            });
                        }
                    });
                }
            });
        });

        // Enviar al correo en lote
        $('#btn_enviar_correo').on('click', function(e) {
            var table = $('#tabla-facturas').DataTable();
            var nro = table.rows('.selected').data().length;

            if (nro <= 0) {
                swal({
                    title: 'ERROR',
                    html: 'Para ejecutar esta acción, debe al menos seleccionar una factura electrónica',
                    type: 'error',
                });
                return false;
            }

            var facturas = [];
            for (var i = 0; i < nro; i++) {
                facturas.push(table.rows('.selected').data()[i]['id']);
            }

            swal({
                title: '¿Desea enviar ' + nro + ' facturas al correo?',
                text: 'Esto puede demorar unos minutos. Las facturas que ya fueron enviadas serán omitidas.',
                type: 'question',
                showCancelButton: true,
                confirmButtonColor: '#00ce68',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Aceptar',
                cancelButtonText: 'Cancelar',
            }).then((result) => {
                if (result.value) {
                    cargando(true);

                    var url = window.location.pathname.split("/")[1] === "software" ?
                        `/software/empresa/facturas/enviomasivocorreo/` + facturas.join(',') :
                        `/empresa/facturas/enviomasivocorreo/` + facturas.join(',');

                    $.ajax({
                        url: url,
                        method: 'GET',
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                        success: function(data) {
                            cargando(false);

                            if (data.success == false) {
                                swal({
                                    title: 'ERROR',
                                    html: data.message || 'Ocurrió un error al enviar los correos',
                                    type: 'error',
                                    confirmButtonColor: '#d33',
                                    confirmButtonText: 'ACEPTAR',
                                });
                                return false;
                            }

                            // Construir HTML de estadísticas
                            var html = '<div style="text-align: left;">';
                            html += '<p><strong>Total de facturas procesadas:</strong> ' + data.total + '</p>';
                            html += '<p style="color: green;"><strong>Enviadas exitosamente:</strong> ' + data.enviados + '</p>';
                            html += '<p style="color: #f0ad4e;"><strong>Omitidas (ya enviadas):</strong> ' + data.omitidos + '</p>';
                            html += '<p style="color: red;"><strong>Errores:</strong> ' + data.errores + '</p>';

                            if (data.detalle && data.detalle.length > 0) {
                                html += '<hr><strong>Detalle:</strong><ul style="max-height: 200px; overflow-y: auto;">';
                                data.detalle.forEach(function(item) {
                                    var color = item.estado == 'enviado' ? 'green' : (item.estado == 'omitido' ? '#f0ad4e' : 'red');
                                    html += '<li style="color: ' + color + ';">' + item.codigo + ': ' + item.mensaje + '</li>';
                                });
                                html += '</ul>';
                            }
                            html += '</div>';

                            swal({
                                title: 'ESTADÍSTICAS DE ENVÍO',
                                html: html,
                                type: data.errores > 0 ? 'warning' : 'success',
                                showConfirmButton: true,
                                confirmButtonColor: '#1A59A1',
                                confirmButtonText: 'ACEPTAR',
                            });

                            getDataTable();
                        },
                        error: function(xhr) {
                            cargando(false);
                            swal({
                                title: 'ERROR',
                                html: 'Ocurrió un error al procesar el envío masivo de correos.',
                                type: 'error',
                                showConfirmButton: true,
                                confirmButtonColor: '#d33',
                                confirmButtonText: 'ACEPTAR',
                            });
                        }
                    });
                }
            });
        });

        $('#btn_enviar_whatsapp_lote').on('click', function(e) {
            var table = $('#tabla-facturas').DataTable();
            var nro = table.rows('.selected').data().length;

            if(nro <= 0){
                swal({
                    title: 'ERROR',
                    html: 'Para ejecutar esta acción, debe al menos seleccionar una factura.',
                    type: 'error',
                });
                return false;
            }

            var facturas = [];
            for (var i = 0; i < nro; i++) {
                facturas.push(table.rows('.selected').data()[i]['id']);
            }

            swal({
                title: '¿Desea enviar ' + nro + ' facturas por WhatsApp Meta?',
                text: 'El proceso se ejecutará en pequeños lotes para no saturar el servidor.',
                type: 'question',
                showCancelButton: true,
                confirmButtonColor: '#00ce68',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Aceptar',
                cancelButtonText: 'Cancelar',
            }).then((result) => {
                if (result.value) {
                    let chunkSize = 5;
                    let arrayChunks = [];
                    for (let i = 0; i < facturas.length; i += chunkSize) {
                        arrayChunks.push(facturas.slice(i, i + chunkSize));
                    }
                    
                    let totalChunks = arrayChunks.length;
                    let currentChunk = 0;
                    
                    let acumulado = {
                        enviados: 0,
                        errores: 0,
                        omitidos: 0,
                        detalle: []
                    };
                    
                    var baseUrl = window.location.pathname.split("/")[1] === "software" ?
                        `/software/empresa/facturas/whatsapp_lote` :
                        `/empresa/facturas/whatsapp_lote`;

                    function processNextChunk() {
                        if (currentChunk >= totalChunks) {
                            cargando(false);
                            let html = `<p><strong>Enviados:</strong> ${acumulado.enviados} | <strong>Errores:</strong> ${acumulado.errores}</p>`;
                            if (acumulado.detalle && acumulado.detalle.length > 0) {
                                html += '<ul style="max-height: 200px; overflow-y: auto; text-align: left; font-size: 13px;">';
                                acumulado.detalle.forEach(function(res) {
                                    let color = res.estado === 'enviado' ? 'green' : (res.estado === 'omitido' ? 'orange' : 'red');
                                    html += `<li style="color:${color};"><strong>${res.codigo}:</strong> ${res.mensaje}</li>`;
                                });
                                html += '</ul>';
                            }

                            swal({
                                title: 'PROCESO DE WHATSAPP FINALIZADO',
                                html: html,
                                type: 'success',
                                confirmButtonColor: '#1A59A1',
                                confirmButtonText: 'ACEPTAR',
                            });
                            getDataTable();
                            return;
                        }

                        cargando(true);
                        swal({
                            title: 'PROCESANDO...',
                            text: `Enviando lote ${currentChunk + 1} de ${totalChunks}... por favor no cierre esta ventana.`,
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            onOpen: () => {
                                swal.showLoading();
                            }
                        });


                        $.ajax({
                            url: baseUrl,
                            method: 'POST',
                            data: { facturas: arrayChunks[currentChunk] },
                            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                            success: function(data) {
                                if (data.success) {
                                    acumulado.enviados += (data.enviados || 0);
                                    acumulado.errores += (data.errores || 0);
                                    acumulado.omitidos += (data.omitidos || 0);
                                    if(data.detalle) {
                                        acumulado.detalle = acumulado.detalle.concat(data.detalle);
                                    }
                                } else {
                                    acumulado.errores += arrayChunks[currentChunk].length;
                                    acumulado.detalle.push({codigo: 'Lote', estado: 'error', mensaje: (data.message || 'Error en respuesta del servidor')});
                                }
                                currentChunk++;
                                processNextChunk();
                            },
                            error: function(xhr) {
                                acumulado.errores += arrayChunks[currentChunk].length;
                                acumulado.detalle.push({codigo: 'Lote', estado: 'error', mensaje: 'Error HTTP procesando lote'});
                                currentChunk++;
                                processNextChunk();
                            }
                        });
                    }
                    
                    processNextChunk();
                }
            });
        });

        $('#btn_enviar_whatsapp_lote').on('click', function(e) {
            var table = $('#tabla-facturas').DataTable();
            var nro = table.rows('.selected').data().length;

            if(nro <= 0){
                swal({
                    title: 'ERROR',
                    html: 'Para ejecutar esta acción, debe al menos seleccionar una factura.',
                    type: 'error',
                });
                return false;
            }

            var facturas = [];
            for (var i = 0; i < nro; i++) {
                facturas.push(table.rows('.selected').data()[i]['id']);
            }

            swal({
                title: '¿Desea enviar ' + nro + ' facturas por WhatsApp Meta?',
                text: 'El proceso se ejecutará en pequeños lotes para no saturar el servidor.',
                type: 'question',
                showCancelButton: true,
                confirmButtonColor: '#00ce68',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Aceptar',
                cancelButtonText: 'Cancelar',
            }).then((result) => {
                if (result.value) {
                    let chunkSize = 5;
                    let arrayChunks = [];
                    for (let i = 0; i < facturas.length; i += chunkSize) {
                        arrayChunks.push(facturas.slice(i, i + chunkSize));
                    }
                    
                    let totalChunks = arrayChunks.length;
                    let currentChunk = 0;
                    
                    let acumulado = {
                        enviados: 0,
                        errores: 0,
                        omitidos: 0,
                        detalle: []
                    };
                    
                    var baseUrl = window.location.pathname.split("/")[1] === "software" ?
                        `/software/empresa/facturas/whatsapp_lote` :
                        `/empresa/facturas/whatsapp_lote`;

                    function processNextChunk() {
                        if (currentChunk >= totalChunks) {
                            cargando(false);
                            let html = `<p><strong>Enviados:</strong> ${acumulado.enviados} | <strong>Errores:</strong> ${acumulado.errores}</p>`;
                            if (acumulado.detalle && acumulado.detalle.length > 0) {
                                html += '<ul style="max-height: 200px; overflow-y: auto; text-align: left; font-size: 13px;">';
                                acumulado.detalle.forEach(function(res) {
                                    let color = res.estado === 'enviado' ? 'green' : (res.estado === 'omitido' ? 'orange' : 'red');
                                    html += `<li style="color:${color};"><strong>${res.codigo}:</strong> ${res.mensaje}</li>`;
                                });
                                html += '</ul>';
                            }

                            swal({
                                title: 'PROCESO DE WHATSAPP FINALIZADO',
                                html: html,
                                type: 'success',
                                confirmButtonColor: '#1A59A1',
                                confirmButtonText: 'ACEPTAR',
                            });
                            getDataTable();
                            return;
                        }

                        cargando(true);
                        swal({
                            title: 'PROCESANDO...',
                            text: `Enviando lote ${currentChunk + 1} de ${totalChunks}... por favor no cierre esta ventana.`,
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            onOpen: () => {
                                swal.showLoading();
                            }
                        });


                        $.ajax({
                            url: baseUrl,
                            method: 'POST',
                            data: { facturas: arrayChunks[currentChunk] },
                            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                            success: function(data) {
                                if (data.success) {
                                    acumulado.enviados += (data.enviados || 0);
                                    acumulado.errores += (data.errores || 0);
                                    acumulado.omitidos += (data.omitidos || 0);
                                    if(data.detalle) {
                                        acumulado.detalle = acumulado.detalle.concat(data.detalle);
                                    }
                                } else {
                                    acumulado.errores += arrayChunks[currentChunk].length;
                                    acumulado.detalle.push({codigo: 'Lote', estado: 'error', mensaje: (data.message || 'Error en respuesta del servidor')});
                                }
                                currentChunk++;
                                processNextChunk();
                            },
                            error: function(xhr) {
                                acumulado.errores += arrayChunks[currentChunk].length;
                                acumulado.detalle.push({codigo: 'Lote', estado: 'error', mensaje: 'Error HTTP procesando lote'});
                                currentChunk++;
                                processNextChunk();
                            }
                        });
                    }
                    
                    processNextChunk();
                }
            });
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
		$('#codigo').val('');
		$('#corte').val('').selectpicker('refresh');
		$('#cliente').val('').selectpicker('refresh');
		$('#municipio').val('').selectpicker('refresh');
        $('#barrio').val('').selectpicker('refresh');
		$('#vendedor').val('').selectpicker('refresh');
		// $('#creacion').val('');
		// $('#vencimiento').val('');
		$('#desde').val('2025-01-01');
		$('#hasta').val('2026-12-31');
		$('#comparador').val('').selectpicker('refresh');
		$('#total').val('');
		$('#estado').val('').selectpicker('refresh');
		$('#grupos_corte').val('').selectpicker('refresh');
		$('#plan').val('').selectpicker('refresh');
		$('#plan_tv').val('').selectpicker('refresh');
		$('#fact_siigo').val('').selectpicker('refresh');
		$('#correo').val('').selectpicker('refresh');
		$('#otras_opciones').val('').selectpicker('refresh');
		$('#servidor').val('').selectpicker('refresh');
		$('#emision').val('').selectpicker('refresh');
		$('#prorrateo').val('').selectpicker('refresh');
		$('#form-filter').addClass('d-none');
		$('#boton-filtrar').html('<i class="fas fa-search"></i> Filtrar');
		getDataTable();
	}

	function exportar() {
        window.location.href = window.location.pathname+'/exportar?codigo='+$('#codigo').val()+'&cliente='+$('#cliente').val()+'&municipio='+$('#municipio').val()+'&barrio='+$('#barrio').val()+'&desde='+$('#desde').val()+'&hasta='+$('#hasta').val()+'&grupos_corte='+$('#grupos_corte').val()+'&fact_siigo='+$('#fact_siigo').val()+'&otras_opciones='+$('#otras_opciones').val()+'&estado='+$('#estado').val()+'&prorrateo='+$('#prorrateo').val()+'&tipo=2';
	}

	// ===== Renumerar Consecutivos =====
	function confirmarRenumerar() {
		var desde = $('#renumerar-desde').val();
		if (!desde || desde < 1) {
			$('#error-renumerar').text('Debe ingresar un número válido').show();
			return;
		}
		$('#error-renumerar').hide();

		Swal.fire({
			title: '¿Está seguro?',
			html: 'Se renumerarán las facturas <strong>no emitidas</strong> que coincidan con los filtros actuales, comenzando desde el número <strong>' + desde + '</strong>.<br><br>Esta acción no se puede deshacer.',
			type: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#3085d6',
			cancelButtonColor: '#d33',
			confirmButtonText: 'Sí, renumerar',
			cancelButtonText: 'Cancelar'
		}).then((result) => {
			if (result.value) {
				ejecutarRenumeracion(desde);
			}
		});
	}

	function ejecutarRenumeracion(desde) {
		$('#btn-renumerar').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Procesando...');
		$('#error-renumerar').hide();
		$('#resultado-renumerar').hide();

		$.ajax({
			url: '{{ route("facturas.renumerar-consecutivos") }}',
			method: 'POST',
			data: {
				_token: '{{ csrf_token() }}',
				desde: desde,
				// Enviar todos los filtros actuales de la tabla
				codigo: $('#codigo').val(),
				corte: $('#corte').val(),
				prorrateo: $('#prorrateo').val(),
				cliente: $('#cliente').val(),
				municipio: $('#municipio').val(),
				vendedor: $('#vendedor').val(),
				barrio: $('#barrio').val(),
				desde_fecha: $('#desde').val(),
				hasta_fecha: $('#hasta').val(),
				comparador: $('#comparador').val(),
				total: $('#total').val(),
				servidor: $('#servidor').val(),
				estado: $('#estado').val(),
				grupos_corte: $('#grupos_corte').val(),
				fact_siigo: $('#fact_siigo').val(),
				emision: $('#emision').val(),
				correo: $('#correo').val(),
				otras_opciones: $('#otras_opciones').val()
			},
			success: function(data) {
				$('#btn-renumerar').prop('disabled', false).html('<i class="fas fa-check"></i> Renumerar');
				if (data.success) {
					$('#resultado-renumerar').html('<strong>' + data.modificadas + '</strong> facturas renumeradas correctamente.').show();
					Swal.fire({
						type: 'success',
						title: data.modificadas + ' facturas renumeradas',
						text: 'Se recargará la página para actualizar los datos.',
						showConfirmButton: true
					}).then(() => {
						window.location.reload();
					});
				} else {
					$('#error-renumerar').text(data.message).show();
				}
			},
			error: function(xhr) {
				$('#btn-renumerar').prop('disabled', false).html('<i class="fas fa-check"></i> Renumerar');
				var msg = 'Error al renumerar las facturas';
				if (xhr.responseJSON) {
					if (xhr.responseJSON.message) msg = xhr.responseJSON.message;
					else if (xhr.responseJSON.error) msg = xhr.responseJSON.error;
				}
				$('#error-renumerar').text(msg).show();
			}
		});
	}
</script>
@endsection
