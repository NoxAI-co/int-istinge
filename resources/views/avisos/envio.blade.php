@extends('layouts.app')
@section('content')
    @if(Session::has('success'))
		<div class="alert alert-success" >
			{{Session::get('success')}}
		</div>

		<script type="text/javascript">
			setTimeout(function(){
			    $('.alert').hide();
			    $('.active_table').attr('class', ' ');
			}, 5000);
		</script>
	@endif
	@if(Session::has('danger'))
		<div class="alert alert-danger" >
			{{Session::get('danger')}}
		</div>

		<script type="text/javascript">
			setTimeout(function(){
			    $('.alert').hide();
			    $('.active_table').attr('class', ' ');
			}, 10000);
		</script>
	@endif
	<form method="POST" action="{{ route('avisos.envio_aviso') }}" style="padding: 2% 3%;" role="form" class="forms-sample" novalidate id="form-retencion">
	    @csrf
	    <input type="hidden" value="{{$opcion}}" name="type">
	    <div class="row">


	        <div class="col-md-3 form-group">
	            <label class="control-label">Plantilla <span class="text-danger">*</span></label>

				<!-- UN SOLO SELECT DINÁMICO -->
				<select name="plantilla" id="plantilla_dinamico" class="form-control selectpicker" title="Seleccione" data-live-search="true" data-size="5" required onchange="cargarPlantillaSeleccionada()">
					<!-- Las opciones se cargarán dinámicamente con JavaScript -->
        	        @foreach($plantillas as $plantilla)
        	        <option {{old('plantilla')==$plantilla->id?'selected':''}} value="{{$plantilla->id}}" data-tipo="{{$plantilla->tipo == 3 ? 'meta' : 'normal'}}">{{$plantilla->title}}</option>
        	        @endforeach
        	    </select>

        	    <span class="help-block error">
        	        <strong>{{ $errors->first('plantilla') }}</strong>
        	    </span>
        	</div>

			<!-- Filtros Adicionales en Tarjeta Secundaria -->
            <div class="col-md-12 mt-3">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-filter"></i> Filtros de Selección</h6>
                    </div>
                    <div class="card-body row">
                        @if(isset($servidores))
                        <div class="col-md-3 form-group">
                            <label class="control-label">Servidor</label>
                            <select name="servidor" id="servidor" class="form-control selectpicker filtros-dinamicos" onchange="refreshClient()" title="Todos los Servidores" data-live-search="true" data-size="5">
                                <option value="">Todos los Servidores</option>
                                @foreach($servidores as $servidor)
                                <option {{old('servidor')==$servidor->id?'selected':''}} value="{{$servidor->id}}">{{$servidor->nombre}}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif

                        @if(isset($gruposCorte))
                        <div class="col-md-3 form-group">
                            <label class="control-label">Grupo corte</label>
                            <select name="corte" id="corte" class="form-control selectpicker filtros-dinamicos" onchange="refreshClient()" title="Todos los Grupos" data-live-search="true" data-size="5">
                                <option value="">Todos los Grupos</option>
                                @foreach($gruposCorte as $corte)
                                <option {{old('corte')==$corte->id?'selected':''}} value="{{$corte->id}}">{{$corte->nombre}}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif

                        @if(isset($barrios))
                        <div class="col-md-3 form-group">
                            <label class="control-label">Barrio</label>
                            <select name="barrio" id="barrio" class="form-control selectpicker filtros-dinamicos" onchange="refreshClient()" title="Todos los Barrios" data-live-search="true" data-size="5">
                                <option value="">Todos los Barrios</option>
                                @foreach($barrios as $barrio)
                                <option value="{{$barrio}}">{{$barrio}}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif

                        <div class="col-md-3 form-group">
                            <label class="control-label">Estado de Contrato</label>
                            <select name="estado_contrato" id="estado_contrato" class="form-control selectpicker filtros-dinamicos" onchange="refreshClient()" title="Todos los Estados">
                                <option value="">Todos los Estados</option>
                                <option value="enabled">Habilitados</option>
                                <option value="disabled">Deshabilitados</option>
                            </select>
                        </div>

                        <div class="col-md-3 form-group">
                            <label class="control-label" style="display:block;">Solo facturas abiertas</label>
                            <div class="d-flex align-items-center mt-2">
                                <label class="switch mb-0">
                                    <input type="checkbox" class="filtros-dinamicos" id="isAbierta" name="isAbierta" value="true" onchange="refreshClient()">
                                    <span class="slider round"></span>
                                </label>
                                <span class="ml-2" id="isAbierta_label">No</span>
                            </div>
                        </div>

                        <div class="col-md-3 form-group d-flex align-items-end">
                            <div class="alert alert-info py-2 px-3 mb-0 w-100">
                                <strong>Destinatarios:</strong> <span id="client_count">0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        	<div class="col-md-12 form-group mt-4" id="seleccion_manual">
	            <label class="control-label">Selección manual de clientes</label>
        	    <select name="contrato[]" id="contrato_sms" class="form-control selectpicker" title="Seleccione" data-live-search="true" data-size="5" required multiple data-actions-box="true" data-select-all-text="Todos" data-deselect-all-text="Ninguno">
        	        @php $estados=\App\Contrato::tipos();@endphp
        	        @foreach($estados as $estado)
        	        <optgroup label="{{$estado['nombre']}}">
        	            @foreach($contratos as $contrato)
        	                @if($contrato->state==$estado['state'])
        	                    <option class="{{$contrato->state}}
									grupo-{{ $contrato->grupo_corte()->id ?? 'no' }}
									servidor-{{ $contrato->servidor()->id ?? 'no' }}
									barrio-{{ strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $contrato->c_barrio ?? 'no')) }}
									factura-{{ $contrato->factura_id != null ?  'si' : 'no'}}
                                    "
									value="{{$contrato->id}}" {{$contrato->client_id==$id?'selected':''}}
									data-nombre="{{ $contrato->c_nombre }} {{ $contrato->c_apellido1 }} {{ $contrato->c_apellido2 }}"
									data-nit="{{ $contrato->c_nit }}"
									data-nro="{{ $contrato->nro }}"
									data-grupo-corte="{{ $contrato->grupo_corte_nombre ?? 'Sin grupo' }}"
									data-factura-codigo="{{ $contrato->factura_codigo ?? '' }}"
								>
									{{$contrato->c_nombre}} {{ $contrato->c_apellido1 }}
									{{ $contrato->c_apellido2 }} - {{$contrato->c_nit}}
									(contrato: {{ $contrato->nro }})
								</option>

        	                @endif
        	            @endforeach
        	        </optgroup>
        	        @endforeach
        	    </select>
        	    <span class="help-block error">
        	        <strong>{{ $errors->first('cliente') }}</strong>
        	    </span>
        	</div>

			{{-- DataTable de clientes seleccionados --}}
			<div class="col-md-12 mt-3" id="tabla-seleccionados-container" style="display:none;">
				<div class="card border-0 shadow-sm">
					<div class="card-header" style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); color: white; border-radius: 8px 8px 0 0;">
						<div class="d-flex justify-content-between align-items-center">
							<h6 class="mb-0"><i class="fab fa-whatsapp mr-2"></i>Clientes Seleccionados para Envío</h6>
							<span class="badge badge-light" id="badge_seleccionados" style="font-size: 0.9em; color:#000000">0</span>
						</div>
					</div>
					<div class="card-body p-0">
						<table id="tabla-seleccionados" class="table table-striped table-hover mb-0" style="width:100%">
							<thead class="thead-light">
								<tr>
									<th>Cliente</th>
									<th>Nro Contrato</th>
									<th>Grupo de Corte</th>
									<th>Cód. Factura</th>
									<th class="text-center" style="width: 90px;">Acciones</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
				</div>
			</div>


			<!-- Removed isAbierta here because it's now in the filters row -->

			<!-- Sección de parámetros para plantillas Meta -->
			<div class="col-md-12" id="parametros-meta" style="display: none;">
				<hr class="my-4">
				<h5><i class="fa fa-sliders"></i> Configuración de Parámetros Dinámicos</h5>
				<div id="inputs-parametros">
					<!-- Los inputs se generarán dinámicamente aquí -->
				</div>
			</div>

			<!-- Preview del mensaje -->
			<div class="col-md-12" id="preview-mensaje-meta" style="display: none;">
				<!-- Aquí se mostrará la vista previa dinámicamente -->
			</div>
       </div>

	   <small>Los campos marcados con <span class="text-danger">*</span> son obligatorios</small>

	   <hr>

	   <div class="row" >
	       <div class="col-sm-12" style="text-align: right;  padding-top: 1%;">
	           <a href="{{route('avisos.index')}}" class="btn btn-outline-secondary">Cancelar</a>
			   @if($opcion == 'whatsapp')
	           <button type="button" id="btn-enviar-batch" onclick="iniciarEnvioBatch()" class="btn btn-success btn-lg" style="background: linear-gradient(135deg, #25D366, #128C7E); border: none; box-shadow: 0 4px 15px rgba(37,211,102,0.3);">
				   <i class="fab fa-whatsapp mr-1"></i> Enviar Notificaciones
			   </button>
			   @else
	           <button type="submit" id="submitcheck" onclick="submitLimit(this.id); alert_swal();" class="btn btn-success">Guardar</button>
			   @endif
	       </div>
	   </div>
    </form>

	{{-- Modal de Progreso --}}
	<div class="modal fade" id="modalProgreso" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
		<div class="modal-dialog modal-dialog-centered" role="document">
			<div class="modal-content" style="border: none; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
				<div class="modal-header" style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); border: none; padding: 28px 30px;">
					<h5 class="modal-title text-white" style="font-weight: 600; font-size: 1.2em;">
						<i class="fab fa-whatsapp mr-2" style="font-size: 1.3em;"></i>Enviando Notificaciones
					</h5>
				</div>
				<div class="modal-body" style="padding: 30px;">
					<div class="text-center mb-4">
						<div class="spinner-container mb-3">
							<div class="whatsapp-spinner"></div>
						</div>
						<p class="text-muted mb-1" id="progreso-texto" style="font-size: 1.05em;">Preparando envío...</p>
						<p class="text-muted mb-0" style="font-size: 0.9em;" id="progreso-lote">Lote 0 de 0</p>
					</div>
					<div class="progress" style="height: 24px; border-radius: 12px; background-color: #e9ecef; overflow: hidden;">
						<div class="progress-bar" role="progressbar" id="progreso-bar"
							style="width: 0%; background: linear-gradient(90deg, #25D366, #128C7E); transition: width 0.4s ease; border-radius: 12px; font-weight: 600; font-size: 0.85em;"
							aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
					</div>
					<div class="row mt-4 text-center">
						<div class="col-4">
							<div class="p-3 rounded" style="background: #f0fdf4;">
								<h4 class="mb-0 text-success" id="progreso-enviados" style="font-weight: 700;">0</h4>
								<small class="text-muted">Enviados</small>
							</div>
						</div>
						<div class="col-4">
							<div class="p-3 rounded" style="background: #fef2f2;">
								<h4 class="mb-0 text-danger" id="progreso-fallidos" style="font-weight: 700;">0</h4>
								<small class="text-muted">Fallidos</small>
							</div>
						</div>
						<div class="col-4">
							<div class="p-3 rounded" style="background: #eff6ff;">
								<h4 class="mb-0 text-info" id="progreso-total" style="font-weight: 700;">0</h4>
								<small class="text-muted">Total</small>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	{{-- Modal de Reporte Final --}}
	<div class="modal fade" id="modalReporte" tabindex="-1" role="dialog">
		<div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
			<div class="modal-content" style="border: none; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
				<div class="modal-header" id="reporte-header" style="border: none; padding: 28px 30px;">
					<h5 class="modal-title text-white" style="font-weight: 600; font-size: 1.2em;">
						<i class="fas fa-chart-bar mr-2"></i> Reporte de Envío
					</h5>
					<button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 1;">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<div class="modal-body" style="padding: 30px;">
					{{-- Resumen --}}
					<div class="row mb-4">
						<div class="col-md-4">
							<div class="card border-0 text-center" style="background: linear-gradient(135deg, #f0fdf4, #dcfce7); border-radius: 12px;">
								<div class="card-body py-4">
									<i class="fas fa-check-circle text-success mb-2" style="font-size: 2em;"></i>
									<h3 class="mb-0 text-success" id="reporte-exitosos" style="font-weight: 700;">0</h3>
									<p class="text-muted mb-0">Enviados Exitosamente</p>
								</div>
							</div>
						</div>
						<div class="col-md-4">
							<div class="card border-0 text-center" style="background: linear-gradient(135deg, #fef2f2, #fecaca); border-radius: 12px;">
								<div class="card-body py-4">
									<i class="fas fa-times-circle text-danger mb-2" style="font-size: 2em;"></i>
									<h3 class="mb-0 text-danger" id="reporte-fallidos" style="font-weight: 700;">0</h3>
									<p class="text-muted mb-0">Fallidos</p>
								</div>
							</div>
						</div>
						<div class="col-md-4">
							<div class="card border-0 text-center" style="background: linear-gradient(135deg, #eff6ff, #dbeafe); border-radius: 12px;">
								<div class="card-body py-4">
									<i class="fas fa-paper-plane text-info mb-2" style="font-size: 2em;"></i>
									<h3 class="mb-0 text-info" id="reporte-total" style="font-weight: 700;">0</h3>
									<p class="text-muted mb-0">Total Procesados</p>
								</div>
							</div>
						</div>
					</div>

					{{-- Tabla de resultados --}}
					<div class="table-responsive">
						<table id="tabla-reporte" class="table table-striped table-hover" style="width:100%">
							<thead class="thead-dark">
								<tr>
									<th>#</th>
									<th>Cliente</th>
									<th>Nro Contrato</th>
									<th>Teléfono</th>
									<th>Estado</th>
									<th>Detalle</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
				</div>
				<div class="modal-footer" style="border-top: 1px solid #eee; padding: 15px 30px;">
					<button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
					<button type="button" class="btn btn-success" onclick="exportarReporte()">
						<i class="fas fa-file-excel mr-1"></i> Exportar Reporte
					</button>
				</div>
			</div>
		</div>
	</div>
@endsection

@section('scripts')
<script type="text/javascript">
	// ============================================================
	// VARIABLES GLOBALES PARA PLANTILLAS META
	// ============================================================
	let plantillaMetaActual = null;
	let bodyTextValues = [];
	let tablaSeleccionados = null;

	@include('includes.campos-dinamicos')

	$(document).ready(function() {
		const plantillaId = $('#plantilla_dinamico').val();
		if (plantillaId) {
			cargarPlantillaSeleccionada();
		}
        refreshClient();

		// Inicializar DataTable de seleccionados
		tablaSeleccionados = $('#tabla-seleccionados').DataTable({
			language: {
				url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json',
				emptyTable: 'No hay clientes seleccionados'
			},
			pageLength: 10,
			lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Todos"]],
			order: [[0, 'asc']],
			columns: [
				{ data: 'nombre' },
				{ data: 'nro' },
				{ data: 'grupo_corte' },
				{ data: 'factura_codigo' },
				{
					data: 'id',
					className: 'text-center',
					orderable: false,
					searchable: false,
					render: function(data) {
						return '<button type="button" class="btn btn-sm btn-outline-danger btn-quitar-seleccion" data-id="' + data + '" title="Quitar selección">' +
							'<i class="fas fa-times"></i></button>';
					}
				}
			],
			drawCallback: function() {
				$('#badge_seleccionados').text(this.api().rows().count());
			}
		});

		// Evento quitar selección desde DataTable
		$('#tabla-seleccionados').on('click', '.btn-quitar-seleccion', function() {
			let contratoId = $(this).data('id');
			// Deseleccionar del selectpicker
			$('#contrato_sms option[value="' + contratoId + '"]').prop('selected', false);
			$('#contrato_sms').selectpicker('refresh');
			// Actualizar tabla
			sincronizarTablaSeleccionados();
			// Actualizar contador
			let count = $("#contrato_sms option:selected").length;
			$('#client_count').text(count);
		});

		// Escuchar cambios en el selectpicker para sincronizar con DataTable
		$('#contrato_sms').on('changed.bs.select', function() {
			sincronizarTablaSeleccionados();
		});
	});

	function sincronizarTablaSeleccionados() {
		if (!tablaSeleccionados) return;

		tablaSeleccionados.clear();

		let seleccionados = $('#contrato_sms option:selected');
		let filas = [];

		seleccionados.each(function() {
			let $opt = $(this);
			filas.push({
				id: $opt.val(),
				nombre: ($opt.data('nombre') || $opt.text()).trim(),
				nro: $opt.data('nro') || '',
				grupo_corte: $opt.data('grupo-corte') || 'Sin grupo',
				factura_codigo: $opt.data('factura-codigo') || '—'
			});
		});

		if (filas.length > 0) {
			tablaSeleccionados.rows.add(filas).draw();
			$('#tabla-seleccionados-container').slideDown(300);
		} else {
			tablaSeleccionados.draw();
			$('#tabla-seleccionados-container').slideUp(300);
		}

		$('#badge_seleccionados').text(filas.length);
	}

	// ============================================================
	// ENVÍO POR LOTES (BATCH)
	// ============================================================
	const BATCH_SIZE = 10;
	let batchResults = [];
	let batchCancelled = false;

	function iniciarEnvioBatch() {
		// Validar plantilla
		let plantillaId = $('#plantilla_dinamico').val();
		if (!plantillaId) {
			Swal.fire({ icon: 'warning', title: 'Atención', text: 'Debe seleccionar una plantilla' });
			return;
		}

		// Obtener contratos seleccionados
		let contratos = $('#contrato_sms').val();
		if (!contratos || contratos.length === 0) {
			Swal.fire({ icon: 'warning', title: 'Atención', text: 'Debe seleccionar al menos un cliente' });
			return;
		}

		// Confirmar envío
		Swal.fire({
			icon: 'question',
			title: '¿Confirmar envío?',
			html: 'Se enviarán <strong>' + contratos.length + '</strong> notificaciones por WhatsApp.<br><small class="text-muted">El envío se procesará en lotes de ' + BATCH_SIZE + ' para evitar sobrecargar el servidor.</small>',
			showCancelButton: true,
			confirmButtonColor: '#25D366',
			cancelButtonColor: '#6c757d',
			confirmButtonText: '<i class="fab fa-whatsapp mr-1"></i> Enviar',
			cancelButtonText: 'Cancelar'
		}).then((result) => {
			if (result.value) {
				ejecutarEnvioBatch(contratos, plantillaId);
			}
		});
	}

	async function ejecutarEnvioBatch(contratos, plantillaId) {
		batchResults = [];
		batchCancelled = false;

		// Preparar body_dinamic params
		let bodyDinamicParams = [];
		$('.parametro-meta-input').each(function() {
			bodyDinamicParams.push($(this).val() || '');
		});

		let bodyDinamic = null;
		if (bodyDinamicParams.length > 0) {
			bodyDinamic = JSON.stringify([bodyDinamicParams]);
		}

		// Dividir contratos en lotes
		let lotes = [];
		for (let i = 0; i < contratos.length; i += BATCH_SIZE) {
			lotes.push(contratos.slice(i, i + BATCH_SIZE));
		}

		let totalProcesados = 0;
		let totalExitosos = 0;
		let totalFallidos = 0;

		// Mostrar modal de progreso
		$('#progreso-bar').css('width', '0%').text('0%');
		$('#progreso-texto').text('Preparando envío...');
		$('#progreso-lote').text('Lote 0 de ' + lotes.length);
		$('#progreso-enviados').text('0');
		$('#progreso-fallidos').text('0');
		$('#progreso-total').text(contratos.length);
		$('#modalProgreso').modal('show');

		let csrfToken = $('meta[name="csrf-token"]').attr('content');

		for (let i = 0; i < lotes.length; i++) {
			if (batchCancelled) break;

			$('#progreso-texto').text('Enviando lote ' + (i + 1) + ' de ' + lotes.length + '...');
			$('#progreso-lote').text('Procesando ' + lotes[i].length + ' mensajes');

			try {
				let response = await $.ajax({
					url: '{{ route("avisos.envio_aviso_batch") }}',
					method: 'POST',
					headers: { 'X-CSRF-TOKEN': csrfToken },
					contentType: 'application/json',
					data: JSON.stringify({
						contratos: lotes[i],
						plantilla_id: plantillaId,
						body_dinamic_params: bodyDinamicParams,
						body_dinamic: bodyDinamic,
						is_first_batch: (i === 0)
					}),
					timeout: 120000 // 2 min per batch
				});

				if (response.error) {
					// Error global del lote
					lotes[i].forEach(function(cId) {
						batchResults.push({
							contrato_id: cId,
							contrato_nro: 'N/A',
							cliente: 'Error en lote',
							telefono: '',
							status: 'error',
							message_id: null,
							error: response.error
						});
						totalFallidos++;
					});
				} else if (response.results) {
					response.results.forEach(function(r) {
						batchResults.push(r);
						if (r.status === 'success') {
							totalExitosos++;
						} else {
							totalFallidos++;
						}
					});
				}

			} catch (err) {
				// Error de red o timeout
				lotes[i].forEach(function(cId) {
					batchResults.push({
						contrato_id: cId,
						contrato_nro: 'N/A',
						cliente: 'Error de conexión',
						telefono: '',
						status: 'error',
						message_id: null,
						error: err.statusText || 'Error de conexión con el servidor'
					});
					totalFallidos++;
				});
			}

			totalProcesados += lotes[i].length;
			let porcentaje = Math.round((totalProcesados / contratos.length) * 100);
			$('#progreso-bar').css('width', porcentaje + '%').text(porcentaje + '%');
			$('#progreso-enviados').text(totalExitosos);
			$('#progreso-fallidos').text(totalFallidos);

			// Pequeña pausa entre lotes para no saturar la API
			if (i < lotes.length - 1) {
				await new Promise(resolve => setTimeout(resolve, 500));
			}
		}

		// Ocultar progreso y mostrar reporte
		$('#modalProgreso').modal('hide');

		setTimeout(function() {
			mostrarReporte(totalExitosos, totalFallidos, batchResults);
		}, 500);
	}

	function mostrarReporte(exitosos, fallidos, resultados) {
		let total = exitosos + fallidos;

		// Color del header según resultado
		if (fallidos === 0) {
			$('#reporte-header').css('background', 'linear-gradient(135deg, #25D366 0%, #128C7E 100%)');
		} else if (exitosos === 0) {
			$('#reporte-header').css('background', 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)');
		} else {
			$('#reporte-header').css('background', 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)');
		}

		$('#reporte-exitosos').text(exitosos);
		$('#reporte-fallidos').text(fallidos);
		$('#reporte-total').text(total);

		// Destruir DataTable anterior si existe
		if ($.fn.DataTable.isDataTable('#tabla-reporte')) {
			$('#tabla-reporte').DataTable().destroy();
			$('#tabla-reporte tbody').empty();
		}

		// Llenar tabla de reporte
		let tbody = '';
		resultados.forEach(function(r, idx) {
			let badgeClass = r.status === 'success' ? 'badge-success' : 'badge-danger';
			let badgeIcon = r.status === 'success' ? 'fa-check' : 'fa-times';
			let badgeText = r.status === 'success' ? 'Enviado' : 'Error';
			let detalle = '';
			if (r.status === 'success') {
				detalle = '<span class="text-success"><i class="fas fa-check-circle mr-1"></i>Mensaje aceptado</span>';
			} else {
				detalle = '<span class="text-danger" title="' + (r.error || '').replace(/"/g, '&quot;') + '"><i class="fas fa-exclamation-circle mr-1"></i>' + (r.error || 'Error desconocido') + '</span>';
			}

			tbody += '<tr>';
			tbody += '<td>' + (idx + 1) + '</td>';
			tbody += '<td>' + (r.cliente || '') + '</td>';
			tbody += '<td>' + (r.contrato_nro || '') + '</td>';
			tbody += '<td>' + (r.telefono || '') + '</td>';
			tbody += '<td><span class="badge ' + badgeClass + '" style="padding: 6px 12px; font-size: 0.85em;"><i class="fas ' + badgeIcon + ' mr-1"></i>' + badgeText + '</span></td>';
			tbody += '<td>' + detalle + '</td>';
			tbody += '</tr>';
		});

		$('#tabla-reporte tbody').html(tbody);

		// Inicializar DataTable del reporte
		$('#tabla-reporte').DataTable({
			language: {
				url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
			},
			pageLength: 25,
			lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Todos"]],
			order: [[4, 'asc'], [0, 'asc']], // Errores primero
			dom: 'Bfrtip',
			buttons: []
		});

		$('#modalReporte').modal('show');
	}

	function exportarReporte() {
		if (!batchResults || batchResults.length === 0) return;

		let csv = 'No.,Cliente,Nro Contrato,Teléfono,Estado,Error\n';
		batchResults.forEach(function(r, idx) {
			let error = (r.error || '').replace(/"/g, '""');
			csv += (idx + 1) + ',"' + (r.cliente || '') + '","' + (r.contrato_nro || '') + '","' + (r.telefono || '') + '","' + r.status + '","' + error + '"\n';
		});

		let blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
		let url = URL.createObjectURL(blob);
		let link = document.createElement('a');
		link.setAttribute('href', url);
		link.setAttribute('download', 'reporte_whatsapp_' + new Date().toISOString().slice(0, 10) + '.csv');
		document.body.appendChild(link);
		link.click();
		document.body.removeChild(link);
	}

	// ============================================================
	// PLANTILLAS META (sin cambios)
	// ============================================================

	function cargarPlantillaSeleccionada() {
		const plantillaId = $('#plantilla_dinamico').val();
		const tipo = $('#plantilla_dinamico option:selected').data('tipo');

		if (tipo === 'meta') {
			cargarPlantillaMeta(plantillaId);
		} else {
			$('#parametros-meta').hide();
			$('#preview-mensaje-meta').hide();
			plantillaMetaActual = null;
			bodyTextValues = [];
		}
	}

	function cargarPlantillaMeta(plantillaId) {
		if (!plantillaId) return;

		var url = '{{ route("avisos.get-plantilla-meta", ":id") }}'.replace(':id', plantillaId);

		$.ajax({
			url: url,
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
			},
			method: 'get',
			success: function(data) {
				if (data.error) {
					console.error('Error al cargar plantilla:', data.error);
					$('#parametros-meta').hide();
					$('#preview-mensaje-meta').hide();
					return;
				}

				plantillaMetaActual = data;

				// Procesar body_text para obtener los parámetros
				if (data.body_text && Array.isArray(data.body_text) && data.body_text.length > 0) {
					bodyTextValues = Array.isArray(data.body_text[0]) ? data.body_text[0] : [];
				} else {
					bodyTextValues = [];
				}

				// Cargar body_dinamic si existe
				let bodyDinamicValues = [];
				if (data.body_dinamic) {
					try {
						let parsedData = data.body_dinamic;

						// Si es string, parsearlo
						if (typeof parsedData === 'string') {
							parsedData = JSON.parse(parsedData);
						}

						// Verificar que sea un array con la estructura correcta [["valor1", "valor2", ...]]
						if (Array.isArray(parsedData) && parsedData.length > 0) {
							// Tomar el primer elemento que es el array de parámetros
							if (Array.isArray(parsedData[0])) {
								bodyDinamicValues = parsedData[0];
							} else {
								// Si no tiene la estructura anidada, usar directamente
								bodyDinamicValues = parsedData;
							}

							// Convertir valores antiguos de { } a [ ] si existen
							bodyDinamicValues = bodyDinamicValues.map(function(val) {
								if (typeof val === 'string') {
									return val.replace(/\{/g, '[').replace(/\}/g, ']');
								}
								return val;
							});
						}
					} catch(e) {
						console.error('Error parsing body_dinamic:', e);
						console.error('Data recibida:', data.body_dinamic);
					}
				}

				// Generar inputs dinámicos
				generarInputsParametros(bodyDinamicValues);

				// Mostrar preview inicial
				actualizarPreview();
			},
			error: function(xhr) {
				console.error('Error al cargar plantilla Meta:', xhr);
				$('#parametros-meta').hide();
				$('#preview-mensaje-meta').hide();
			}
		});
	}

	function generarInputsParametros(valoresDinamicos = []) {
		const $container = $('#inputs-parametros');
		$container.empty();

		if (bodyTextValues.length === 0) {
			$('#parametros-meta').hide();
			return;
		}

		// Generar un input por cada parámetro
		bodyTextValues.forEach(function(valorEjemplo, index) {
			const numeroParam = index + 1;
			const valorDinamico = valoresDinamicos[index] || '';

			// Crear contenedor principal con mejor diseño
			const $paramGroup = $('<div class="parametro-meta-group mb-4 p-3 border rounded"></div>');

			// Label
			const $label = $('<label class="control-label d-block mb-2"><strong>Parámetro ' + numeroParam + '</strong> <small class="text-muted">(ejemplo: ' + valorEjemplo + ')</small></label>');

			// Contenedor del input con botones
			const $inputWrapper = $('<div class="input-group mb-2"></div>');

			// Input principal
			const $input = $('<input>', {
				type: 'text',
				class: 'form-control parametro-meta-input',
				name: 'body_dinamic_params[]',
				'data-param-index': index,
				placeholder: 'Escriba texto o use campos dinámicos',
				value: valorDinamico
			});

			// Botón dropdown para agregar campos
			const $dropdownBtn = $('<button class="btn btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-plus"></i> Campos</button>');
			const $dropdownMenu = $('<ul class="dropdown-menu dropdown-menu-right"></ul>');

			// Agregar opciones al dropdown
			Object.keys(camposDinamicos).forEach(function(categoria) {
				const $categoriaHeader = $('<li><h6 class="dropdown-header">' + categoria.charAt(0).toUpperCase() + categoria.slice(1) + '</h6></li>');
				$dropdownMenu.append($categoriaHeader);

				Object.keys(camposDinamicos[categoria]).forEach(function(campo) {
					const campoKey = '[' + categoria + '.' + campo + ']';
					const $item = $('<li><a class="dropdown-item" href="#" data-campo="' + campoKey + '" data-param-index="' + index + '">' + camposDinamicos[categoria][campo] + ' <code>' + campoKey + '</code></a></li>');
					$dropdownMenu.append($item);
				});
			});

			// Agregar event listener para insertar campos
			$dropdownMenu.on('click', 'a', function(e) {
				e.preventDefault();
				const campo = $(this).data('campo');
				const paramIndex = $(this).data('param-index');
				const $targetInput = $('.parametro-meta-input[data-param-index="' + paramIndex + '"]');
				const cursorPos = $targetInput[0].selectionStart || $targetInput.val().length;
				const textBefore = $targetInput.val().substring(0, cursorPos);
				const textAfter = $targetInput.val().substring(cursorPos);
				$targetInput.val(textBefore + campo + textAfter);
				$targetInput.focus();
				$targetInput[0].setSelectionRange(cursorPos + campo.length, cursorPos + campo.length);
				actualizarPreview();
			});

			// Botón para limpiar
			const $clearBtn = $('<button class="btn btn-outline-danger" type="button" title="Limpiar"><i class="fa fa-times"></i></button>');
			$clearBtn.on('click', function() {
				$input.val('');
				actualizarPreview();
			});

			$inputWrapper.append($input);
			$inputWrapper.append($dropdownBtn);
			$inputWrapper.append($dropdownMenu);
			$inputWrapper.append($clearBtn);

			// Event listener para actualizar preview
			$input.on('input keyup', function() {
				actualizarPreview();
			});

			// Información adicional
			const $info = $('<small class="text-muted d-block mt-2"><i class="fa fa-info-circle"></i> Puede escribir texto libre y agregar campos dinámicos desde el menú</small>');

			$paramGroup.append($label);
			$paramGroup.append($inputWrapper);
			$paramGroup.append($info);
			$container.append($paramGroup);
		});

		$('#parametros-meta').show();
	}

	function actualizarPreview() {
		if (!plantillaMetaActual || !plantillaMetaActual.contenido) {
			$('#preview-mensaje-meta').hide();
			return;
		}

		let contenido = plantillaMetaActual.contenido;

		// Obtener valores de los inputs
		const valoresParametros = [];
		$('.parametro-meta-input').each(function() {
			let valor = $(this).val() || '';
			// Reemplazar placeholders con valores de ejemplo (solo para preview)
			valor = valor.replace(/\[contacto\.nombre\]/g, 'Juan');
			valor = valor.replace(/\[contacto\.apellido1\]/g, 'Pérez');
			valor = valor.replace(/\[contacto\.apellido2\]/g, 'González');
			valor = valor.replace(/\[factura\.fecha\]/g, '01/01/2024');
			valor = valor.replace(/\[factura\.vencimiento\]/g, '15/01/2024');
			valor = valor.replace(/\[factura\.total\]/g, '$100.000');
			valor = valor.replace(/\[factura\.porpagar\]/g, '$50.000');
			valor = valor.replace(/\[empresa\.nombre\]/g, 'Mi Empresa S.A.S.');
			valor = valor.replace(/\[empresa\.nit\]/g, '900123456-1');
			valoresParametros.push(valor);
		});

		// Reemplazar placeholders {{1}}, {{2}}, etc.
		valoresParametros.forEach(function(valor, index) {
			const numeroParam = index + 1;
			const placeholderText = '{{' + numeroParam + '}}';
			// Si el valor está vacío, mantener el placeholder original como {{1}}, {{2}}, etc.
			if (!valor || valor.trim() === '') {
				// No reemplazar, mantener el placeholder original
			} else {
				contenido = contenido.replace(new RegExp('\\{\\{' + numeroParam + '\\}\\}', 'g'), valor);
			}
		});

		// Mostrar preview con mejor diseño
		const $preview = $('#preview-mensaje-meta');
		$preview.html(`
			<hr class="my-4">
			<div class="alert alert-info">
				<strong><i class="fa fa-eye"></i> Vista Previa del Mensaje:</strong>
				<div class="mt-3 p-3 bg-white rounded border" style="white-space: pre-wrap; font-family: monospace;">
					${contenido.replace(/\n/g, '<br>')}
				</div>
			</div>
		`).show();
	}

	// Guardar body_dinamic antes de enviar (para SMS/EMAIL forms)
	$('#form-retencion').on('submit', function(e) {
		if (plantillaMetaActual && plantillaMetaActual.tipo == 3) {
			const bodyDinamicValues = [];
			$('.parametro-meta-input').each(function() {
				bodyDinamicValues.push($(this).val() || '');
			});

			// Crear input hidden con el JSON
			$('#body_dinamic_json').remove();
			$('<input>').attr({
				type: 'hidden',
				id: 'body_dinamic_json',
				name: 'body_dinamic',
				value: JSON.stringify([bodyDinamicValues])
			}).appendTo(this);
		}
	});

	// ============================================================
	// EVENT LISTENER PARA CAMBIO DE PLANTILLA
	// ============================================================
	$(document).on('change', '#plantilla_dinamico', function() {
		cargarPlantillaSeleccionada();
	});

	// ============================================================
	// CORREGIR DOBLE SCROLLBAR EN SELECTPICKER
	// ============================================================
	function corregirScrollbarSelects() {
		$('.bootstrap-select').each(function() {
			var $dropdown = $(this).find('.dropdown-menu');
			if ($dropdown.length && !$dropdown.closest('#parametros-meta').length) {
				// Remover overflow del contenedor externo - forzar con !important usando attr
				$dropdown.attr('style', function(i, style) {
					return (style || '') + ' overflow: visible !important; max-height: none !important; overflow-x: visible !important; overflow-y: visible !important;';
				});
			}
		});
	}

	$(document).ready(function() {
		// Esperar a que bootstrap-select se inicialice
		setTimeout(corregirScrollbarSelects, 200);
		// También corregir después de un tiempo adicional
		setTimeout(corregirScrollbarSelects, 500);
	});

	// También corregir cuando se abre un select
	$(document).on('shown.bs.select', '.bootstrap-select', function() {
		var $dropdown = $(this).find('.dropdown-menu');
		if ($dropdown.length && !$dropdown.closest('#parametros-meta').length) {
			// Forzar con attr para asegurar que se aplique
			$dropdown.attr('style', function(i, style) {
				return (style || '') + ' overflow: visible !important; max-height: none !important; overflow-x: visible !important; overflow-y: visible !important;';
			});
		}
	});

	// Corregir después de refresh de selectpicker
	$(document).on('refreshed.bs.select', '.bootstrap-select', function() {
		setTimeout(corregirScrollbarSelects, 50);
	});
</script>

<style>
	.parametro-meta-group {
		background-color: #f8f9fa;
		transition: all 0.3s ease;
	}

	.parametro-meta-group:hover {
		background-color: #e9ecef;
		box-shadow: 0 2px 4px rgba(0,0,0,0.1);
	}

	.parametro-meta-input {
		font-family: 'Courier New', monospace;
	}

	.input-group .btn {
		border-left: none;
	}

	.input-group .form-control:focus {
		z-index: 3;
	}

	/* Solo aplicar overflow al dropdown de parámetros meta, no a los selectpicker */
	#parametros-meta .dropdown-menu {
		max-height: 300px;
		overflow-y: auto;
	}

	.dropdown-item code {
		background-color: #f8f9fa;
		padding: 2px 4px;
		border-radius: 3px;
		font-size: 0.85em;
		margin-left: 5px;
	}

	#parametros-meta {
		margin-top: 20px;
		margin-bottom: 20px;
	}

	#parametros-meta h5 {
		margin-bottom: 20px;
		color: #495057;
		font-weight: 600;
	}

	/* Eliminar doble scrollbar en selectpicker - el dropdown-menu externo NO debe tener overflow */
	.bootstrap-select .dropdown-menu {
		overflow: visible !important;
		max-height: none !important;
		overflow-x: visible !important;
		overflow-y: visible !important;
	}

	/* WhatsApp Spinner */
	.whatsapp-spinner {
		width: 50px;
		height: 50px;
		border: 4px solid #e9ecef;
		border-top: 4px solid #25D366;
		border-radius: 50%;
		animation: spin 1s linear infinite;
		margin: 0 auto;
	}

	@keyframes spin {
		0% { transform: rotate(0deg); }
		100% { transform: rotate(360deg); }
	}

	/* DataTable styling */
	#tabla-seleccionados-container .card {
		border-radius: 12px;
		overflow: hidden;
	}

	#tabla-seleccionados thead th {
		font-size: 0.85em;
		text-transform: uppercase;
		letter-spacing: 0.5px;
		padding: 12px 15px;
		border: none;
	}

	#tabla-seleccionados tbody td {
		padding: 10px 15px;
		vertical-align: middle;
		font-size: 0.9em;
	}

	.btn-quitar-seleccion {
		border-radius: 50%;
		width: 30px;
		height: 30px;
		padding: 0;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		transition: all 0.2s ease;
	}

	.btn-quitar-seleccion:hover {
		background-color: #dc3545;
		color: white;
		transform: scale(1.1);
	}

	/* Report modal styling */
	#tabla-reporte thead th {
		font-size: 0.85em;
		text-transform: uppercase;
		letter-spacing: 0.5px;
	}

	/* Btn enviar styling */
	#btn-enviar-batch {
		transition: all 0.3s ease;
		border-radius: 8px;
		padding: 10px 24px;
		font-weight: 600;
	}

	#btn-enviar-batch:hover {
		transform: translateY(-2px);
		box-shadow: 0 8px 25px rgba(37,211,102,0.4) !important;
	}
</style>

<script type="text/javascript">

	// ============================================================
	// RESTO DEL CÓDIGO ORIGINAL (sin cambios)
	// ============================================================
	var ultimoVencimiento = null;

	window.addEventListener('load', function() {
        $('#barrio').on('keyup',function(e) {
        	if(e.which > 32 || e.which == 8) {
        		// Viejo ajax call ignorado
        	}
        });

        // Filtro local con refreshClient() ya implementado


    });

    // Nuevo refreshClient() para filtrado local total
    function refreshClient(){
        // Obtener valores de los filtros
        let servidor = $('#servidor').val() || '';
        let grupoCorte = $('#corte').val() || '';
        let barrio = $('#barrio').val() || '';
        let estadoContrato = $('#estado_contrato').val() || '';
        let factAbierta = $('#isAbierta').is(":checked");
        let tipoSaldo = $('#opciones_saldo').val() || '';
        let valorSaldo = parseFloat($('#valor_saldo').val());

        // Actualizar label del switch
        $('#isAbierta_label').text(factAbierta ? 'Sí' : 'No');

        // Deseleccionar todas las opciones primero
        $("#contrato_sms option:selected").prop("selected", false);
        $("#contrato_sms option").prop("selected", false); // asegura que todas esten deseleccionadas en DOM real

        // Filtrar las opciones que cumplen las condiciones
        let $opcionesCandidatas = $("#contrato_sms option");

        $opcionesCandidatas = $opcionesCandidatas.filter(function() {
            let $opt = $(this);
            let match = true;

            // Filtro por servidor
            if (servidor && !$opt.hasClass('servidor-' + servidor)) {
                match = false;
            }

            // Filtro por grupo de corte
            if (grupoCorte && !$opt.hasClass('grupo-' + grupoCorte)) {
                match = false;
            }

            // Filtro por barrio
            if (barrio) {
                // Como el barrio puede tener espacios, lo filtramos limpiando con regex en JS,
                // aseguramos buscar la cadena limpia o verificar match exacto si lo guardamos en un data-barrio.
                // Aquí usamos class para consistencia: class="barrio-nombre_limpio"
                let barrioLimpio = barrio.toLowerCase().replace(/[^a-z0-9]/g, '');
                if (!$opt.hasClass('barrio-' + barrioLimpio)) {
                    match = false;
                }
            }

            // Filtro por estado contrato
            if (estadoContrato && !$opt.hasClass(estadoContrato)) {
                match = false;
            }

            // Filtro por facturas abiertas
            if (factAbierta && !$opt.hasClass('factura-si')) {
                match = false;
            }

            // Filtro de saldos (lógica existente mantenida)
            if (tipoSaldo && !isNaN(valorSaldo)) {
                let saldo = parseFloat($opt.data('saldo') || 0);
                saldo = Math.round(saldo);
                switch (tipoSaldo) {
                    case 'mayor_a':
                        if (!(saldo > valorSaldo)) match = false;
                        break;
                    case 'mayor_igual':
                        if (!(saldo >= valorSaldo)) match = false;
                        break;
                    case 'igual_a':
                        if (!(saldo === valorSaldo)) match = false;
                        break;
                    case 'menor_a':
                        if (!(saldo < valorSaldo)) match = false;
                        break;
                    case 'menor_igual':
                        if (!(saldo <= valorSaldo)) match = false;
                        break;
                }
            }

            return match;
        });

        // Marcar seleccionadas las opciones filtradas
        $opcionesCandidatas.prop('selected', true);

        // Refrescar el plugin de Bootstrap Select
        $('#contrato_sms').selectpicker('refresh');

        // Actualizar contador de clientes
        let count = $("#contrato_sms option:selected").length;
        $('#client_count').text(count);

		// Sincronizar con DataTable
		sincronizarTablaSeleccionados();
    }


    function alert_swal(){
    	Swal.fire({
    		type: 'info',
    		title: 'ENVIANDO NOTIFICACIONES',
    		text: 'Este proceso puede demorar varios minutos',
    		showConfirmButton: false,
    	})
    }

</script>
@endsection
