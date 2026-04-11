@extends('layouts.app')

@section('boton')
    @if(auth()->user()->modo_lectura())
	    <div class="alert alert-warning text-left" role="alert">
	        <h4 class="alert-heading text-uppercase">Integra Colombia: Suscripción Vencida</h4>
	       <p>Si desea seguir disfrutando de nuestros servicios adquiera alguno de nuestros planes.</p>
<p>Medios de pago Nequi: 3206909290 Cuenta de ahorros Bancolombia 42081411021 CC 1001912928 Ximena Herrera representante legal. Adjunte su pago para reactivar su membresía</p>
	    </div>
	@else
	    @if($contrato->cs_status==1)
	        <form action="{{ route('contratos.state',$contrato->id) }}" method="post" class="delete_form" style="margin:0;display: inline-block;" id="cambiar-state{{$contrato->id}}">
		       @csrf
		    </form>
		    <form action="{{ route('contratos.destroy',$contrato->id) }}" method="post" class="delete_form" style="margin:  0;display: inline-block;" id="eliminar-contrato{{$contrato->id}}">
		        @csrf
		        <input name="_method" type="hidden" value="DELETE">
		    </form>
		    <form action="{{ route('contratos.destroy_to_mk',$contrato->id) }}" method="post" class="delete_form" style="margin:0;display: inline-block;" id="eliminar-contrato-mk{{$contrato->id}}">
		       @csrf
		    </form>
		    <form action="{{ route('contratos.destroy_to_networksoft',$contrato->id) }}" method="get" class="delete_form" style="margin:  0;display: inline-block;" id="eliminar-contrato-ns-{{$contrato->id}}">
		        @csrf
		        <input name="_method" type="hidden" value="DELETE">
		    </form>
	        <div class="btn-group" role="group" aria-label="Button group with nested dropdown">
	            <div class="btn-group" role="group">
	                <button id="btnGroupDrop1" type="button" class="btn btn-dark dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
	                    Acciones del Contrato
	                </button>
	                <div class="dropdown-menu" aria-labelledby="btnGroupDrop1">
	                    @if(isset($_SESSION['permisos']['406']))
	                    <a href="{{ route('contratos.edit',$contrato->id )}}"  class="dropdown-item" title="Editar Contrato"><i class="fas fa-edit"></i></i> Editar Contrato</a>
	                    @endif
	                    @if($contrato->plan_id)
	                    @if(isset($_SESSION['permisos']['407']))
	                    <button @if($contrato->state == 'enabled') class="dropdown-item" title="Deshabilitar Contrato" @else  class="dropdown-item" title="Habilitar Contrato" @endif type="submit" onclick="confirmar('cambiar-state{{$contrato->id}}', '¿Está seguro que desea @if($contrato->state == 'enabled') deshabilitar @else habilitar @endif contrato?', '');"><i class="fas fa-file-signature"></i>@if($contrato->state == 'enabled') Deshabilitar Contrato @else Habilitar Contrato @endif</button>
	                    @endif
	                    <a href="{{ route('contratos.grafica',$contrato->id )}}" class="dropdown-item" title="Gráfica de Conexión" onclick="cargando('true');"><i class="fas fa-chart-area"></i> Gráfica de Conexión</a>
	                    <a href="{{ route('contratos.grafica_consumo',$contrato->id )}}" class="dropdown-item" title="Gráfica de Consumo" onclick="cargando('true');"><i class="fas fa-chart-line"></i> Gráfica de Consumo</a>
	                    <a href="{{ route('contratos.conexion',$contrato->id )}}" class="dropdown-item" title="Ping de Conexión" onclick="cargando('true');"><i class="fas fa-plug"></i> Ping de Conexión</a>
	                    @endif
	                    <a href="{{ route('contratos.log',$contrato->id )}}" class="dropdown-item" title="Log del Contrato" onclick="cargando('true');"><i class="fas fa-clipboard-list"></i> Log del Contrato</a>
	                    @if(isset($_SESSION['permisos']['439']))
	                    <button class="dropdown-item" type="submit" title="Eliminar" onclick="confirmar('eliminar-contrato{{$contrato->id}}', '¿Está seguro que desea eliminar el contrato?', 'Se borrara de forma permanente');"><i class="fas fa-times"></i> Eliminar Contrato</button>
	                    {{-- <button class="dropdown-item" type="submit" title="Eliminar de NetworkSoft" onclick="confirmar('eliminar-contrato-ns-{{$contrato->id}}', '¿Está seguro que desea eliminar el contrato solo de NetworkSoft?', 'Se borrara de forma permanente');"><i class="fas fa-times"></i> Eliminar Contrato de NetworkSoft</button> --}}
	                    @endif
	                    @if(isset($_SESSION['permisos']['440']))
	                    <button class="dropdown-item d-none" type="submit" title="Eliminar del Mikrotik" onclick="confirmar('eliminar-contrato-mk{{$contrato->id}}', '¿Está seguro que desea eliminar el contrato del Mikrotik?', 'Se borrara de forma permanente');"><i class="fas fa-times-circle"></i> Eliminar Contrato del Mikrotik</button>
	                    @endif
	                </div>
	            </div>
	        </div>
	    @endif
	@endif
@endsection

@section('content')
	<style>
		body > div.container-scroller > div > div > div.content-wrapper > div > div > div > div.row.card-description > div > div > table > tbody > tr:nth-child(10) > td > img{
			width: 547px;
			height: 297px;
			border-radius: 0%;
		}
		.bg-th{
	        background: {{Auth::user()->rol > 1 ? Auth::user()->empresa()->color:''}} !important;
	        border-color: {{Auth::user()->rol > 1 ? Auth::user()->empresa()->color:''}} !important;
	        color: #fff !important;
	    }
	</style>

    @if(Session::has('success'))
		<div class="alert alert-success" >
			{{Session::get('success')}}
		</div>

		<script type="text/javascript">
			setTimeout(function(){
			    $('.alert').hide();
			    $('.active_table').attr('class', ' ');
			}, 10000);
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
			}, 5000);
		</script>
	@endif

	<div class="row card-description">
		@if($contrato->plan_id)
		<div class="col-md-12">
			<div class="table-responsive">
				<table class="table table-striped table-bordered table-sm info">
					<tbody>
						<tr>
							<th class="bg-th text-center" colspan="2" style="font-size: 1em;"><strong>SERVICIO DE INTERNET</strong></th>
						</tr>
						<tr>
							<th width="20%">Nro. Contrato</th>
							<td>{{ $contrato->nro }}</td>
						</tr>
						<tr>
							<th>Nombre Servicio</th>
							<td>{{ $contrato->servicio }}</td>
						</tr>
						@if($contrato->serial_onu)
						<tr>
							<th>Serial ONU</th>
							<td>{{ $contrato->serial_onu }}</td>
						</tr>
						@endif
						@if($contrato->linea)
						<tr>
							<th>Línea</th>
							<td>{{ $contrato->linea }}</td>
						</tr>
						@endif
						<tr>
							<th>Estrato</th>
							<td>{{ $contrato->estrato ?? 'N/A' }}</td>
						</tr>
						@if($contrato->tecnologia)
						<tr>
							<th>Tecnología</th>
							<td>{{ $contrato->tecnologia() }}</td>
						</tr>
						@endif
						@if($contrato->conexion)
						<tr>
							<th>Tipo de Conexión</th>
							<td><strong>{{ $contrato->conexion() }}</strong></td>
						</tr>
						@endif
						@if($contrato->grupo_corte)
						<tr>
							<th>Grupo de Corte</th>
							<td><a href="{{ route('grupos-corte.show',$contrato->grupo_corte()->id )}}" target="_blank"><strong>{{ $contrato->grupo_corte()->nombre }}</strong></a> (CORTE {{ $contrato->grupo_corte()->fecha_corte }} - SUSPENSIÓN {{ $contrato->grupo_corte()->fecha_suspension }})</td>
						</tr>
						@endif
						@if($contrato->fecha_suspension)
						<tr>
							<th>Fecha de Suspensión</th>
							<td>El día <strong>{{ $contrato->fecha_suspension }}</strong> del mes</td>
						</tr>
						@endif
						<tr>
							<th>Estado Contrato</th>
							<td>
							    <strong class="text-{{$contrato->status('true')}}">{{$contrato->status()}}</strong>
							</td>
						</tr>
						@if($contrato->tipo_contrato)
						<tr>
							<th>Tipo Contrato</th>
							<td>
							    {{ucfirst($contrato->tipo_contrato)}}
							</td>
						</tr>
						@endif
						<tr>
							<th>Dirección IP</th>
							<td><a href="http://{{ $contrato->ip }}{{ $contrato->puerto ? ':'.$contrato->puerto->nombre : '' }}" target="_blank">{{ $contrato->ip }}{{ $contrato->puerto ? ':'.$contrato->puerto->nombre : '' }} <i class="fas fa-external-link-alt"></i></a></td>
						</tr>
						@if($contrato->address_street)
						<tr>
							<th>Dirección de Instalación</th>
							<td>{{ $contrato->address_street }}</td>
						</tr>
						@endif
						@if($contrato->latitude && $contrato->longitude)
						@php
						    $url = 'https://www.google.com/maps/search/'.$contrato->latitude.','.$contrato->longitude.'?hl=es';
						 @endphp
						<tr>
							<th>Dirección GPS</th>
							<td>({{$contrato->latitude}} {{$contrato->longitude}}) <a href="{{ $url }}" target="_blank">Ver en Google Maps <i class="fas fa-external-link-alt"></i></a></td>
						</tr>
						@endif
						@if($contrato->puerto_conexion)
						<tr>
							<th>Puerto de Conexión</th>
							<td>{{ $contrato->puerto->nombre }}</td>
						</tr>
						@endif
						@if($contrato->ip_new)
						<tr>
							<th>Dirección IP</th>
							<td>{{ $contrato->ip_new }}</td>
						</tr>
						@endif
						@if($contrato->mac_address)
						<tr>
							<th>Dirección MAC</th>
							<td>{{ $contrato->mac_address }}</td>
						</tr>
						@endif
						@if($contrato->interfaz)
						<tr>
							<th>Interfaz</th>
							<td>{{ $contrato->interfaz }}</td>
						</tr>
						@endif
						@if($contrato->marca_antena)
						<tr>
							<th>Antena</th>
							<td>{{ $contrato->marca_antena()->nombre }} @if($contrato->modelo_antena) - {{$contrato->modelo_antena}} @endif</td>
						</tr>
						@endif
						@if($contrato->marca_router)
						<tr>
							<th>Router</th>
							<td>{{ $contrato->marca_router()->nombre }} @if($contrato->modelo_router) - {{$contrato->modelo_router}} @endif</td>
						</tr>
						@endif
						@if($contrato->nodo)
						<tr>
							<th>Nodo Asociado</th>
							@if($contrato->nodo())
							<td><a href="{{ route('nodos.show',$contrato->nodo()->id )}}" target="_blank"><strong>{{ $contrato->nodo()->nombre }}</strong></a></td>
						@endif
						</tr>
						@endif
						@if($contrato->ap)
						<tr>
							<th>Access Point Asociado</th>
							<td><a href="{{ route('access-point.show',$contrato->ap()->id )}}" target="_blank"><strong>{{ $contrato->ap()->nombre }}</strong></a></td>
						</tr>
						@endif
						@if($contrato->server_configuration_id)
						<tr>
							<th>Servidor Asociado</th>
							<td><a href="{{ route('mikrotik.show',$contrato->server_configuration_id )}}" target="_blank"><strong>{{ $contrato->servidor()->nombre }}</strong></a></td>
						</tr>
						@endif
						<tr>
							<th>Tipo de Facturación</th>
							<td>{{ $contrato->facturacion() }}</td>
						</tr>
						<tr>
							<th>Plan Contratado</th>
							<td><a href="{{route('planes-velocidad.show',$contrato->plan_id)}}" target="_blank"><strong>{{ $contrato->plan()->name }}</strong></a></td>
						</tr>
						@if(!$contrato->precio_personalizado_internet)
						<tr>
							<th>Precio Plan</th>
							<td>{{ Auth::user()->empresa()->moneda }} {{ App\Funcion::Parsear($contrato->plan()->price) }}</td>
						</tr>
						@endif
						@if($contrato->precio_personalizado_internet)
						<tr>
							<th>Precio Personalizado Internet</th>
							<td>{{ Auth::user()->empresa()->moneda }} {{ App\Funcion::Parsear($contrato->precio_personalizado_internet) }}</td>
						</tr>
						@endif
						@if($servicio_otro)
						<tr>
							<th>Otro servicio</th>
							<td><a href="{{route('inventario.show', $servicio_otro->id)}}" target="_blank"><strong>{{ $servicio_otro->producto }}</strong></a></td>
						</tr>
						<tr>
							<th>Valor otro servicio</th>
							<td>{{ Auth::user()->empresa()->moneda }} {{ number_format($servicio_otro->precio, 0, ',', '.') }}</td>
						</tr>
						@endif
						@if($contrato->costo_reconexion>0)
						<tr>
							<th>Costo de Reconexión</th>
							<td>{{ Auth::user()->empresa()->moneda }} {{ App\Funcion::Parsear($contrato->costo_reconexion) }}</td>
						</tr>
						@endif
						@if($contrato->descuento)
						<tr>
							<th>Descuento</th>
							<td>{{ $contrato->descuento }}%</td>
						</tr>
						@endif
						@if($contrato->contrato_permanencia)
						<tr>
							<th>Contrato de Permanencia</th>
							<td>{{ $contrato->contrato_permanencia('completa') }}</td>
						</tr>
						@endif
						@if($contrato->factura_individual)
						<tr>
							<th>Facturación Individual</th>
							<td>{{ $contrato->factura_individual == 1 ?'Si':'No' }}</td>
						</tr>
						@endif
						@if($contrato->adjunto_a)
						<tr>
							<th>{{ $contrato->referencia_a }}</th>
							<td><a href="{{asset('../software/adjuntos/documentos/'.$contrato->adjunto_a)}}" target="_blank"><strong>Ver {{ $contrato->referencia_a }}</strong></a></td>
						</tr>
						@endif
						@if($contrato->adjunto_b)
						<tr>
							<th>{{ $contrato->referencia_b }}</th>
							<td><a href="{{asset('../software/adjuntos/documentos/'.$contrato->adjunto_b)}}" target="_blank"><strong>Ver {{ $contrato->referencia_b }}</strong></a></td>
						</tr>
						@endif
						@if($contrato->adjunto_c)
						<tr>
							<th>{{ $contrato->referencia_c }}</th>
							<td><a href="{{asset('../software/adjuntos/documentos/'.$contrato->adjunto_c)}}" target="_blank"><strong>Ver {{ $contrato->referencia_c }}</strong></a></td>
						</tr>
						@endif
						@if($contrato->adjunto_d)
						<tr>
							<th>{{ $contrato->referencia_d }}</th>
							<td><a href="{{asset('../software/adjuntos/documentos/'.$contrato->adjunto_d)}}" target="_blank"><strong>Ver {{ $contrato->referencia_d }}</strong></a></td>
						</tr>
						@endif
						@if($contrato->oficina())
						<tr>
							<th>Oficina Asociada</th>
							<td>{{ $contrato->oficina()->nombre }}</td>
						</tr>
						@endif
						@if($contrato->vendedor)
						<tr>
							<th>Vendedor</th>
							<td>{{ $contrato->vendedor()->nombre }}</td>
						</tr>
						@endif
						@if($contrato->canal)
						<tr>
							<th>Canal de Venta</th>
							<td>{{ $contrato->canal()->nombre }}</td>
						</tr>
						@endif
						@if($contrato->observaciones)
						<tr>
							<th>Observaciones</th>
							<td>{{ $contrato->observaciones }}</td>
						</tr>
						@endif
						@if($contrato->creador)
						<tr>
							<th>Contrato Registrado por</th>
							<td>{{ $contrato->creador }}</td>
						</tr>
						@endif
						<tr>
							<th>Contrato Registrado el</th>
							<td>
								<span id="fecha-registro-display">{{date('d-m-Y g:i:s A', strtotime($contrato->created_at))}}</span>
								@if(isset($_SESSION['permisos']['406']))
									<a href="javascript:abrirModalEditarFecha({{$contrato->id}}, '{{date('d-m-Y H:i:s', strtotime($contrato->created_at))}}')" style="font-size: 0.8em;margin-left: 10px;" title="Editar fecha">
										<i class="fas fa-pencil-alt"></i>
									</a>
								@endif
							</td>
						</tr>
                        @if($contrato->fechaDesconexion() != null)
                        <tr>
							<th>Fecha desconexión contrato</th>
							<td>{{$contrato->fechaDesconexion()}}</td>
						</tr>
                        @endif
					</tbody>
				</table>
			</div>
		</div>
		@endif

		@if($contrato->servicio_tv)
		<div class="col-md-12">
			<div class="table-responsive">
				<table class="table table-striped table-bordered table-sm info">
					<tbody>
						<tr>
							<th class="bg-th text-center" colspan="2" style="font-size: 1em;"><strong>SERVICIO DE TELEVISIÓN</strong></th>
						</tr>
						@if($contrato->vendedor)
						<tr>
							<th>Vendedor</th>
							<td>{{ $contrato->vendedor()->nombre }}</td>
						</tr>
						@endif
						@if($contrato->canal)
						<tr>
							<th>Canal de Venta</th>
							<td>{{ $contrato->canal()->nombre }}</td>
						</tr>
						@endif
						<tr>
							<th width="20%">Plan Contratado</th>
							<td><a href="{{route('inventario.show',$contrato->servicio_tv)}}" target="_blank"><strong>{{ $inventario->producto }}</strong></a></td>
						</tr>
						<tr>
							<th>Precio del Plan Contratado</th>
							<td>{{ Auth::user()->empresa()->moneda }} {{ App\Funcion::Parsear($inventario->precio) }}</td>
						</tr>
						@if($contrato->precio_personalizado_tv)
						<tr>
							<th>Precio Personalizado TV</th>
							<td>{{ Auth::user()->empresa()->moneda }} {{ App\Funcion::Parsear($contrato->precio_personalizado_tv) }}</td>
						</tr>
						@endif
						@if($contrato->costo_reconexion>0)
						<tr>
							<th>Costo de Reconexión</th>
							<td>{{ Auth::user()->empresa()->moneda }} {{ App\Funcion::Parsear($contrato->costo_reconexion) }}</td>
						</tr>
						@endif
						@if($contrato->descuento)
						<tr>
							<th>Descuento</th>
							<td>{{ $contrato->descuento }}%</td>
						</tr>
						@endif
						@if($contrato->contrato_permanencia)
						<tr>
							<th>Contrato de Permanencia</th>
							<td>{{ $contrato->contrato_permanencia('completa') }}</td>
						</tr>
						@endif
						@if($contrato->tipo_contrato)
						<tr>
							<th>Tipo Contrato</th>
							<td>
							    {{ucfirst($contrato->tipo_contrato)}}
							</td>
						</tr>
						@endif
                        <tr>
							<th>Contrato Registrado el</th>
							<td>
								<span id="fecha-registro-display-tv">{{date('d-m-Y g:i:s A', strtotime($contrato->created_at))}}</span>
								@if(isset($_SESSION['permisos']['406']))
									<a href="javascript:abrirModalEditarFecha({{$contrato->id}}, '{{date('d-m-Y H:i:s', strtotime($contrato->created_at))}}')" style="font-size: 0.8em;margin-left: 10px;" title="Editar fecha">
										<i class="fas fa-pencil-alt"></i>
									</a>
								@endif
							</td>
						</tr>
                        @if($contrato->fechaDesconexion() != null)
                        <tr>
							<th>Fecha desconexión contrato</th>
							<td>{{$contrato->fechaDesconexion()}}</td>
						</tr>
                        @endif
					</tbody>
				</table>
			</div>
		</div>
		@endif

		<div class="col-md-12">
			<div class="table-responsive">
				<table class="table table-striped table-bordered table-sm info mt-2">
					<tbody>
						<tr>
							<th class="bg-th text-center" colspan="2" style="font-size: 1em;"><strong>CLIENTE ASOCIADO AL CONTRATO</strong></th>
						</tr>
						<tr>
							<th width="20%">Nombre Cliente</th>
							<td><a href="{{ route('contactos.show',$contrato->id_cliente )}}" target="_blank"><strong>{{ $contrato->nombre }} {{ $contrato->apellido1 }} {{ $contrato->apellido2 }}</strong></a></td></td>
						</tr>
						@if($contrato->nit)
						<tr>
							<th>Cédula Cliente</th>
							<td>{{ $contrato->nit }}</td>
						</tr>
						@endif
						@if($contrato->celular || $contrato->telefono1)
						<tr>
							<th>Nro Teléfono</th>
							<td>@if($contrato->celular) {{ $contrato->celular }} @else {{ $contrato->telefono1 }} @endif</td>
						</tr>
						@endif
						@if($contrato->email)
						<tr>
							<th>Correo Electrónico</th>
							<td>{{ $contrato->email }}</td>
						</tr>
						@endif
						@if($contrato->barrio)
						<tr>
							<th>Barrio</th>
							<td>{{ $contrato->barrio }}</td>
						</tr>
						@endif
						@if($contrato->direccion)
						<tr>
							<th>Dirección</th>
							<td>{{ $contrato->direccion }}</td>
						</tr>
						@endif
						<tr>
								<th>Contrato Registrado el</th>
							<td>
								<span id="fecha-registro-display">{{date('d-m-Y g:i:s A', strtotime($contrato->created_at))}}</span>
								@if(isset($_SESSION['permisos']['406']))
									<a href="javascript:abrirModalEditarFecha({{$contrato->id}}, '{{date('d-m-Y H:i:s', strtotime($contrato->created_at))}}')" style="font-size: 0.8em;margin-left: 10px;" title="Editar fecha">
										<i class="fas fa-pencil-alt"></i>
									</a>
								@endif
							</td>
						</tr>
						
					</tbody>
				</table>
			</div>
		</div>
	</div>

	{{-- Modal para editar fecha de registro --}}
	@if(isset($_SESSION['permisos']['406']))
	<div class="modal fade" id="modalEditarFecha" role="dialog" data-backdrop="static" data-keyboard="false">
		<div class="modal-dialog modal-sm">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal">&times;</button>
					<h4 class="modal-title">Editar Fecha de Registro</h4>
				</div>
				<div class="modal-body" style="padding: 20px;">
					<form id="form-editar-fecha">
						<input type="hidden" id="contrato-id-fecha">
						<div class="form-group">
							<label class="control-label">Fecha <span class="text-danger">*</span></label>
							<input type="date" class="form-control" id="fecha-registro" required>
							<span class="help-block error" style="margin-top: 5px;">
								<strong id="error-fecha" style="font-size: 0.85em;"></strong>
							</span>
						</div>
						<div class="form-group" style="margin-bottom: 0;">
							<label class="control-label">Hora <span class="text-danger">*</span></label>
							<input type="time" class="form-control" id="hora-registro" required step="1">
						</div>
					</form>
				</div>
				<div class="modal-footer" style="padding: 10px 20px;">
					<button type="button" class="btn btn-sm btn-default" data-dismiss="modal">Cancelar</button>
					<button type="button" class="btn btn-sm btn-success" onclick="guardarFechaRegistro()">Guardar</button>
				</div>
			</div>
		</div>
	</div>
	@endif
@endsection

@section('scripts')
@if(isset($_SESSION['permisos']['406']))
<script>
	function abrirModalEditarFecha(contratoId, fechaActual) {
		$('#contrato-id-fecha').val(contratoId);

		// Separar fecha y hora
		var partes = fechaActual.split(' ');
		var fecha = partes[0]; // formato: dd-mm-yyyy
		var hora = partes[1] || '00:00:00'; // formato: HH:mm:ss

		// Convertir fecha de dd-mm-yyyy a yyyy-mm-dd para input type="date"
		var fechaParts = fecha.split('-');
		var fechaFormatoDate = fechaParts[2] + '-' + fechaParts[1] + '-' + fechaParts[0];

		// Convertir hora de HH:mm:ss a HH:mm para input type="time"
		var horaFormatoTime = hora.substring(0, 5); // Toma solo HH:mm

		$('#fecha-registro').val(fechaFormatoDate);
		$('#hora-registro').val(horaFormatoTime);
		$('#error-fecha').text('');
		$('#modalEditarFecha').modal('show');
	}

	function guardarFechaRegistro() {
		var contratoId = $('#contrato-id-fecha').val();
		var fecha = $('#fecha-registro').val(); // formato: yyyy-mm-dd
		var hora = $('#hora-registro').val(); // formato: HH:mm

		if (!fecha) {
			$('#error-fecha').text('La fecha es requerida');
			return;
		}

		if (!hora) {
			$('#error-fecha').text('La hora es requerida');
			return;
		}

		// Convertir fecha de yyyy-mm-dd a dd-mm-yyyy
		var fechaParts = fecha.split('-');
		var fechaFormato = fechaParts[2] + '-' + fechaParts[1] + '-' + fechaParts[0];

		// Agregar segundos a la hora si no los tiene
		var horaCompleta = hora;
		if (hora.split(':').length === 2) {
			horaCompleta = hora + ':00';
		}

		// Combinar fecha y hora en formato dd-mm-yyyy HH:mm:ss
		var fechaCompleta = fechaFormato + ' ' + horaCompleta;

		cargando(true);

		$.ajax({
			url: '{{ route("contratos.actualizar-fecha") }}',
			method: 'POST',
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
			},
			data: {
				contrato_id: contratoId,
				fecha: fechaCompleta
			},
			success: function(response) {
				cargando(false);
				if (response.success) {
					// Formatear fecha para mostrar
					var fechaFormateada = formatearFecha(response.fecha);
					$('#fecha-registro-display').text(fechaFormateada);
					$('#fecha-registro-display-tv').text(fechaFormateada);
					$('#modalEditarFecha').modal('hide');

					Swal.fire({
						position: 'top-center',
						type: 'success',
						title: 'Fecha actualizada correctamente',
						showConfirmButton: false,
						timer: 2000
					});
				} else {
					$('#error-fecha').text(response.message || 'Error al actualizar la fecha');
				}
			},
			error: function(xhr) {
				cargando(false);
				var errorMessage = 'Error al actualizar la fecha';
				if (xhr.responseJSON && xhr.responseJSON.message) {
					errorMessage = xhr.responseJSON.message;
				}
				$('#error-fecha').text(errorMessage);

				Swal.fire({
					position: 'top-center',
					type: 'error',
					title: 'Error',
					text: errorMessage,
					showConfirmButton: true
				});
			}
		});
	}

	function formatearFecha(fecha) {
		// Convertir de formato Y-m-d H:i:s a d-m-Y g:i:s A
		var date = new Date(fecha);
		var day = String(date.getDate()).padStart(2, '0');
		var month = String(date.getMonth() + 1).padStart(2, '0');
		var year = date.getFullYear();
		var hours = date.getHours();
		var minutes = String(date.getMinutes()).padStart(2, '0');
		var seconds = String(date.getSeconds()).padStart(2, '0');
		var ampm = hours >= 12 ? 'PM' : 'AM';
		hours = hours % 12;
		hours = hours ? hours : 12;
		hours = String(hours).padStart(2, '0');

		return day + '-' + month + '-' + year + ' ' + hours + ':' + minutes + ':' + seconds + ' ' + ampm;
	}

</script>
@endif
@endsection