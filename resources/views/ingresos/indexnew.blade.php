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
        <a href="{{route('ingresos.create')}}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Nuevo Ingreso</a>
        <a href="{{route('ingresos.importar')}}" class="btn btn-success btn-sm"><i class="fas fa-file-excel"></i> Importar Pagos</a>
        @php
            // Detectamos en qué posición visible está la columna `nro` para
            // saber qué celda transformar a input cuando se entra a modo
            // edición. Si el usuario la ocultó en "Organizar Tabla", $nroIndex
            // queda en null y los botones de edición no se muestran.
            $nroIndex = null;
            foreach ($tabla as $idx => $campo) {
                if ($campo->campo === 'nro') { $nroIndex = $idx; break; }
            }
        @endphp
        @if($nroIndex !== null)
            <button type="button" id="btn-editar-nros" class="btn btn-warning btn-sm">
                <i class="fas fa-pen"></i> Editar Nros
            </button>
            <button type="button" id="btn-guardar-nros" class="btn btn-success btn-sm d-none">
                <i class="fas fa-save"></i> Guardar cambios
            </button>
            <button type="button" id="btn-cancelar-nros" class="btn btn-secondary btn-sm d-none">
                <i class="fas fa-times"></i> Cancelar
            </button>
        @endif
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

    @if(Session::has('danger'))
        <div class="alert alert-danger">
        	{{Session::get('danger')}}
        </div>
        <script type="text/javascript">
        	setTimeout(function() {
        		$('.alert').hide();
        		$('.active_table').attr('class', ' ');
        	}, 5000);
        </script>
    @endif

	<div class="container-fluid d-none" id="form-filter">
		<fieldset>
            <legend>Filtro de Búsqueda</legend>
			<div class="card shadow-sm border-0">
				<div class="card-body pb-3 pt-2" style="background: #f9f9f9;">
					<div class="row">
						<div class="col-md-2 pl-1 pt-1">
							<input type="text" placeholder="Nro" id="numero" class="form-control rounded">
						</div>
						<div class="col-md-2 pl-1 pt-1">
							<input type="text" placeholder="Comprobante Pago" id="comprobante_pago" class="form-control rounded">
						</div>
                        <div class="col-md-2 pl-1 pt-1">
                            <select class="form-control rounded selectpicker" id="status" title="Anulados" data-size="5" data-live-search="true">
                                <option value="2">Anulados</option>
                                <!-- Añade más opciones aquí según sea necesario -->
                            </select>
                        </div>
                        <div class="col-md-2 pl-1 pt-1">
							<input type="text" placeholder="Estado" id="comprobante_pago" class="form-control rounded">
						</div>
						<div class="col-md-2 pl-1 pt-1">
							<select class="form-control rounded selectpicker" id="cliente" title="Cliente" data-size="5" data-live-search="true">
								@foreach ($clientes as $cliente)
								<option value="{{ $cliente->id }}">{{ $cliente->nombre }} {{$cliente->apellido1}} {{$cliente->apellido2}} - {{ $cliente->nit }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-md-2 pl-1 pt-1">
							<input type="text" placeholder="Fecha" id="fecha-pago-dp" class="form-control rounded dp-fecha-i">
						</div>
						<div class="col-md-2 pl-1 pt-1">
							<select id="banco" class="form-control rounded selectpicker m-0 p-0" title="Cuenta" data-width="150px" data-size="5" data-live-search="true">
								@php $tipos_cuentas=\App\Banco::tipos();@endphp
								@foreach($tipos_cuentas as $tipo_cuenta)
									<optgroup label="{{$tipo_cuenta['nombre']}}">
										@foreach($bancos as $cuenta)
										    @if($cuenta->tipo_cta==$tipo_cuenta['nro'])
										        <option value="{{$cuenta->id}}">{{$cuenta->nombre}}</option>
										    @endif
										@endforeach
									</optgroup>
								@endforeach
							</select>
						</div>
						<div class="col-md-2 pl-1 pt-1">
							<select class="form-control rounded selectpicker" id="metodo" title="Método de Pago" data-size="5" data-live-search="true">
								@foreach($metodos as $metodo)
								    <option value="{{$metodo->id}}">{{$metodo->metodo}}</option>
								@endforeach
							</select>
						</div>
						<div class="col-md-2 pl-1 pt-1">
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
    		<div class="container-filtercolumn">
    			@if(isset($_SESSION['permisos']['750']))
    			<a href="{{route('campos.organizar', 5)}}" class="btn btn-warning btn-sm mr-1"><i class="fas fa-table"></i> Organizar Tabla</a>
    			@endif
    			@if(Auth::user()->empresa()->efecty == 1)
    			<a href="{{route('ingresos.efecty')}}" class="btn btn-warning btn-sm" style="background: #938B16; border: solid #938B16 1px;"><i class="fas fa-upload"></i> Cargar Archivo Efecty TXT</a>
    			<a href="{{route('ingresos.efecty_xlsx')}}" class="btn btn-success btn-sm d-none"><i class="fas fa-upload"></i> Cargar Archivo Efecty XLXS</a>
    			@endif
			</div>
		</div>
		<div class="col-md-12">
			<table class="table table-striped table-hover nowrap w-100" id="tabla-ingresos">
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

@endsection

@section('scripts')
	<script>
		window.addEventListener('load',
			function() {

				$('#tabla-ingresos').DataTable({
					responsive: true,
					serverSide: true,
					processing: true,
					searching: false,
					language: {
						'url': '{{asset("vendors/DataTables/es.json")}}'
					},
					order: [
						[0, "desc"]
					],
					"pageLength": {{ Auth::user()->empresa()->pageLength }},
					ajax: '{{url("/ingresos")}}',
					headers: {
						'X-CSRF-TOKEN': '{{csrf_token()}}'
					},
					columns: [
						@foreach($tabla as $campo)
						{data: '{{$campo->campo}}'},
						@endforeach
						{data: 'acciones'},
					]
				});

				tabla = $('#tabla-ingresos');

				tabla.on('preXhr.dt', function(e, settings, data) {
					data.numero = $('#numero').val();
					data.comprobante_pago = $('#comprobante_pago').val();
                    data.status = $('#status').val();
					data.cliente = $('#cliente').val();
					data.fecha = $('#fecha-pago-dp').val();
					data.estado = $('#estado').val();
					data.banco = $('#banco').val();
					data.metodo = $('#metodo').val();
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

				$('#numero').on('keyup',function(e) {
		            if(e.which > 32 || e.which == 8) {
		                getDataTable();
		                return false;
		            }
		        });

		        $('#cliente, #banco, #metodo, #fecha-pago-dp').on('change',function() {
		            getDataTable();
		            return false;
		        });


				setTimeout(() => {
								jQuery('.dp-fecha-i').datepicker({
									uiLibrary: 'bootstrap4',
									iconsLibrary: 'fontawesome',
									locale: 'es-es',
									uiLibrary: 'bootstrap4',
									format: 'yyyy-mm-dd'
								});
				}, 1000);

				// ── Modo "Editar Nros" ────────────────────────────────────
				@if($nroIndex !== null)
				const NRO_COL_INDEX = {{ $nroIndex }};

				$('#btn-editar-nros').on('click', function () {
					var dt = tabla.DataTable();
					var rows = dt.rows({ page: 'current' }).nodes();
					$.each(rows, function (_, tr) {
						var $tr      = $(tr);
						var $celda   = $tr.children('td').eq(NRO_COL_INDEX);

						// Preferimos los data-attrs del <tr> (los pone setRowAttr
						// en el controller). Como fallback, parseamos la celda:
						// el renderer arma <a href=".../ingresos/{id}">{nro}</a>,
						// así que el text() de la celda es el nro y el href trae
						// el id. Esto deja al feature operativo aunque el deploy
						// del backend todavía no haya corrido.
						var idIng   = $tr.attr('data-ingreso-id') || '';
						var nroOrig = $tr.attr('data-nro-original') || '';
						if (!nroOrig) { nroOrig = $celda.text().trim(); }
						if (!idIng) {
							var href = $celda.find('a').attr('href') || '';
							var m = href.match(/\/ingresos\/(\d+)/);
							if (m) { idIng = m[1]; }
						}
						if (!idIng || !nroOrig) { return; }

						// Guardamos el HTML original para poder restaurarlo si
						// el usuario cancela sin guardar.
						$celda.data('original-html', $celda.html());
						// type="text" + inputmode="numeric" en lugar de
						// type="number": evita los spinners del navegador (que
						// comen ~20px de ancho del input y dejan visible solo
						// los primeros 2-3 dígitos en columnas estrechas como
						// "Nro") sin perder el teclado numérico en mobile.
						$celda
							.css({ 'min-width': '110px', 'padding': '4px 6px' })
							.html(
								'<input type="text" inputmode="numeric" pattern="[0-9]*" ' +
								'class="form-control form-control-sm nro-edit-input" ' +
								'data-ingreso-id="' + idIng + '" ' +
								'data-nro-original="' + nroOrig + '" ' +
								'value="' + nroOrig + '" ' +
								'style="width:100%; min-width:90px; text-align:right;">'
							);
					});
					$('#btn-editar-nros').addClass('d-none');
					$('#btn-guardar-nros, #btn-cancelar-nros').removeClass('d-none');

					// Ajustar el ancho de columnas del DataTable después de
					// inyectar inputs (si no, la columna queda con el ancho
					// calculado antes del cambio y los inputs siguen apretados).
					tabla.DataTable().columns.adjust();
				});

				$('#btn-cancelar-nros').on('click', function () {
					tabla.DataTable().ajax.reload(null, false);
					$('#btn-editar-nros').removeClass('d-none');
					$('#btn-guardar-nros, #btn-cancelar-nros').addClass('d-none');
				});

				$('#btn-guardar-nros').on('click', function () {
					var cambios = [];
					$('.nro-edit-input').each(function () {
						var $i      = $(this);
						var nuevo   = ($i.val() || '').toString().trim();
						var orig    = ($i.data('nro-original') || '').toString();
						if (nuevo !== '' && nuevo !== orig) {
							cambios.push({ id: $i.data('ingreso-id'), nro: nuevo });
						}
					});

					if (cambios.length === 0) {
						Swal.fire({
							icon: 'info',
							title: 'Sin cambios',
							text: 'No modificaste ningún Nro.',
							timer: 1800,
							showConfirmButton: false
						});
						$('#btn-cancelar-nros').click();
						return;
					}

					Swal.fire({
						title: '¿Guardar cambios?',
						text: 'Se van a actualizar ' + cambios.length + ' ingreso(s).',
						icon: 'question',
						showCancelButton: true,
						confirmButtonText: 'Sí, guardar',
						cancelButtonText: 'Cancelar'
					}).then(function (res) {
						if (!res.value) return;

						var $btn = $('#btn-guardar-nros');
						$btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Guardando...');

						$.ajax({
							url: '{{ route("ingresos.actualizarNros") }}',
							method: 'POST',
							data: {
								_token: '{{ csrf_token() }}',
								cambios: cambios
							},
							success: function (resp) {
								$btn.prop('disabled', false).html('<i class="fas fa-save"></i> Guardar cambios');
								$('#btn-guardar-nros, #btn-cancelar-nros').addClass('d-none');
								$('#btn-editar-nros').removeClass('d-none');
								tabla.DataTable().ajax.reload(null, false);
								Swal.fire({
									icon: resp.success ? 'success' : 'warning',
									title: resp.success ? 'Listo' : 'Atención',
									text: resp.message || 'Operación completada.',
									timer: 2200,
									showConfirmButton: false
								});
							},
							error: function (xhr) {
								$btn.prop('disabled', false).html('<i class="fas fa-save"></i> Guardar cambios');
								var msg = (xhr.responseJSON && xhr.responseJSON.message)
									? xhr.responseJSON.message
									: 'No se pudo guardar. Reintentá.';
								Swal.fire({ icon: 'error', title: 'Error', text: msg });
							}
						});
					});
				});
				@endif

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
		    $('#numero').val('');
		    $('#comprobante_pago').val('');
            $('#status').val('');
			$('#cliente').val('').selectpicker('refresh');
			$('#fecha-pago-dp').val('');
			$('#estado').val('').selectpicker('refresh');
			$('#banco').val('').selectpicker('refresh');
			$('#metodo').val('').selectpicker('refresh');
			$('#form-filter').addClass('d-none');
			$('#boton-filtrar').html('<i class="fas fa-search"></i> Filtrar');
			getDataTable();
		}
	</script>
@endsection
