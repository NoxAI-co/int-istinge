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
		@if($contrato->ip && $contrato->plan_id)
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
							<th width="30%">Nro. Contrato</th>
							<td>{{ $contrato->nro }}</td>
						</tr>
						<tr>
							<th>Estado Contrato</th>
							<td>
							    <strong class="text-{{$contrato->status('true')}}">{{$contrato->status()}}</strong>
							</td>
						</tr>
						<tr>
							<th>Tipo Contrato</th>
							<td>{{ ucfirst($contrato->tipo_contrato) ?? 'N/A' }}</td>
						</tr>
						<tr>
							<th>Dirección IP</th>
							<td><a href="http://{{ $contrato->ip }}{{ $contrato->puerto ? ':'.$contrato->puerto->nombre : '' }}" target="_blank">{{ $contrato->ip }}{{ $contrato->puerto ? ':'.$contrato->puerto->nombre : '' }} <i class="fas fa-external-link-alt"></i></a></td>
						</tr>
						<tr>
							<th>Dirección IP Secundaria</th>
							<td>{{ $contrato->ip_new ?? 'N/A' }}</td>
						</tr>
						<tr>
							<th>Tipo de Conexión</th>
							<td>
								@if($contrato->conexion == 1) PPPOE
								@elseif($contrato->conexion == 2) DHCP
								@elseif($contrato->conexion == 3) IP Estática
								@elseif($contrato->conexion == 4) VLAN
								@else N/A @endif
							</td>
						</tr>
						<tr>
							<th>Tecnología</th>
							<td>
								@if($contrato->tecnologia == 1) Fibra
								@elseif($contrato->tecnologia == 2) Inalámbrica
								@elseif($contrato->tecnologia == 3) Cableado UTP
								@else N/A @endif
							</td>
						</tr>
						<tr>
							<th>Usuario PPPOE/Hotspot</th>
							<td>{{ $contrato->usuario ?? 'N/A' }}</td>
						</tr>
						<tr>
							<th>Contraseña PPPOE/Hotspot</th>
							<td>{{ $contrato->password ?? 'N/A' }}</td>
						</tr>
						<tr>
							<th>Segmento de IP / Local Address</th>
							<td>{{ $contrato->local_address ?? $contrato->local_adress_pppoe ?? 'N/A' }}</td>
						</tr>
						<tr>
							<th>Segmento de IP Secundario</th>
							<td>{{ $contrato->local_address_new ?? 'N/A' }}</td>
						</tr>
						<tr>
							<th>Segmento de IP Terciario</th>
							<td>{{ $contrato->local_address_new_2 ?? 'N/A' }}</td>
						</tr>
						<tr>
							<th>Profile</th>
							<td>{{ $contrato->profile ?? 'N/A' }}</td>
						</tr>
						<tr>
							<th>ID VLAN</th>
							<td>{{ $contrato->id_vlan ?? 'N/A' }}</td>
						</tr>
						<tr>
							<th>Nombre VLAN</th>
							<td>{{ $contrato->name_vlan ?? 'N/A' }}</td>
						</tr>
						<tr>
							<th>Simple Queue</th>
							<td>{{ ucfirst($contrato->simple_queue) ?? 'N/A' }}</td>
						</tr>
						<tr>
							<th>Interfaz</th>
							<td>{{ $contrato->interfaz ?? 'N/A' }}</td>
						</tr>
						<tr>
							<th>Dirección MAC</th>
							<td>{{ $contrato->mac_address ?? 'N/A' }}</td>
						</tr>
						<tr>
							<th>Serial ONU</th>
							<td>{{ $contrato->serial_onu ?? 'N/A' }}</td>
						</tr>
						<tr>
							<th>Línea</th>
							<td>{{ $contrato->linea ?? 'N/A' }}</td>
						</tr>
						<tr>
							<th>Estrato</th>
							<td>{{ $contrato->estrato ?? 'N/A' }}</td>
						</tr>
						<tr>
							<th>Dirección de Instalación</th>
							<td>{{ $contrato->address_street ?? 'N/A' }}</td>
						</tr>
						@php
						    $url = ($contrato->latitude && $contrato->longitude) ? 'https://www.google.com/maps/search/'.$contrato->latitude.','.$contrato->longitude.'?hl=es' : null;
						 @endphp
						<tr>
							<th>Dirección GPS</th>
							<td>
								@if($url)
									({{$contrato->latitude}} {{$contrato->longitude}}) <a href="{{ $url }}" target="_blank">Ver en Google Maps <i class="fas fa-external-link-alt"></i></a>
								@else
									N/A
								@endif
							</td>
						</tr>
						<tr>
							<th>Usuario WiFi</th>
							<td>{{ $contrato->usuario_wifi ?? 'N/A' }}</td>
						</tr>
						<tr>
							<th>Contraseña WiFi</th>
							<td>{{ $contrato->contrasena_wifi ?? 'N/A' }}</td>
						</tr>
						<tr>
							<th>Puerto de Conexión</th>
							<td>{{ $contrato->puerto->nombre ?? 'N/A' }}</td>
						</tr>
						<tr>
							<th>Antena</th>
							<td>
								@if($contrato->marca_antena)
									{{ $contrato->marca_antena()->nombre }} @if($contrato->modelo_antena) - {{$contrato->modelo_antena}} @endif
								@else
									N/A
								@endif
							</td>
						</tr>
						<tr>
							<th>Router</th>
							<td>
								@if($contrato->marca_router)
									{{ $contrato->marca_router()->nombre }} @if($contrato->modelo_router) - {{$contrato->modelo_router}} @endif
								@else
									N/A
								@endif
							</td>
						</tr>
						<tr>
							<th>Tipo Módem</th>
							<td>{{ $contrato->tipo_moden ?? 'N/A' }}</td>
						</tr>
						<tr>
							<th>Serial Módem</th>
							<td>{{ $contrato->serial_moden ?? 'N/A' }}</td>
						</tr>
						<tr>
							<th>IP Receptora</th>
							<td>{{ $contrato->ip_receptora ?? 'N/A' }}</td>
						</tr>
						<tr>
							<th>Puerto Receptor</th>
							<td>{{ $contrato->puerto_receptor ?? 'N/A' }}</td>
						</tr>
						<tr>
							<th>Nodo Asociado</th>
							<td>
								@if($contrato->nodo())
									<a href="{{ route('nodos.show',$contrato->nodo()->id )}}" target="_blank"><strong>{{ $contrato->nodo()->nombre }}</strong></a>
								@else
									N/A
								@endif
							</td>
						</tr>
						<tr>
							<th>Access Point Asociado</th>
							<td>
								@if($contrato->ap())
									<a href="{{ route('access-point.show',$contrato->ap()->id )}}" target="_blank"><strong>{{ $contrato->ap()->nombre }}</strong></a>
								@else
									N/A
								@endif
							</td>
						</tr>
						<tr>
							<th>Servidor Asociado</th>
							<td>
								@if($contrato->servidor())
									<a href="{{ route('mikrotik.show',$contrato->server_configuration_id )}}" target="_blank"><strong>{{ $contrato->servidor()->nombre }}</strong></a>
								@else
									N/A
								@endif
							</td>
						</tr>
						<tr>
							<th>Caja NAP</th>
							<td>{{ $contrato->cajanap() ? $contrato->cajanap()->nombre : 'N/A' }}</td>
						</tr>
						<tr>
							<th>Puerto Caja NAP</th>
							<td>{{ $contrato->cajanap_puerto ?? 'N/A' }}</td>
						</tr>
						<tr>
							<th>Tipo de Facturación</th>
							<td>{{ $contrato->facturacion() }}</td>
						</tr>
						<tr>
							<th>Grupo de Corte</th>
							<td>{{ $contrato->grupo_corte() ? $contrato->grupo_corte()->nombre : 'N/A' }}</td>
						</tr>
						<tr>
							<th>Facturación Individual</th>
							<td>{{ $contrato->factura_individual == 1 ?'Si':'No' }}</td>
						</tr>
						<tr>
							<th>Iva Aplicado</th>
							<td>{{ $contrato->iva_factura == 1 ?'Si':'No' }}</td>
						</tr>
						<tr>
							<th>Prorrateo</th>
							<td>{{ $contrato->prorrateo == 1 ?'Si':'No' }}</td>
						</tr>
						<tr>
							<th>Facturar Primer Mes</th>
							<td>{{ $contrato->fact_primer_mes == 1 ?'Si':'No' }}</td>
						</tr>
						<tr>
							<th>Sincronización Pagos Siigo</th>
							<td>{{ $contrato->pago_siigo_contrato == 1 ?'Si':'No' }}</td>
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
						@if($contrato->rd_item_vencimiento == 1)
						<tr>
							<th>Vencimiento Ítem Otro</th>
							<td>{{ $contrato->dt_item_hasta ?? 'N/A' }}</td>
						</tr>
						@endif
						@endif
						<tr>
							<th>Costo de Reconexión</th>
							<td>{{ Auth::user()->empresa()->moneda }} {{ App\Funcion::Parsear($contrato->costo_reconexion) }}</td>
						</tr>
						<tr>
							<th>Descuento</th>
							<td>
								@if($contrato->descuento) {{ $contrato->descuento }}% @endif
								@if($contrato->descuento && $contrato->descuento_pesos) - @endif
								@if($contrato->descuento_pesos) {{ Auth::user()->empresa()->moneda }} {{ App\Funcion::Parsear($contrato->descuento_pesos) }} @endif
								@if(!$contrato->descuento && !$contrato->descuento_pesos) N/A @endif
							</td>
						</tr>
						@if($contrato->fecha_hasta_desc)
						<tr>
							<th>Descuento Válido Hasta</th>
							<td>{{ date('d-m-Y', strtotime($contrato->fecha_hasta_desc)) }}</td>
						</tr>
						@endif
						<tr>
							<th>Contrato de Permanencia</th>
							<td>{{ $contrato->contrato_permanencia == 1 ? 'Si' : 'No' }} @if($contrato->contrato_permanencia == 1 && $contrato->contrato_permanencia_meses) ({{ $contrato->contrato_permanencia_meses }} meses) @endif</td>
						</tr>
						<tr>
							<th>Fecha de Suspensión Personalizada</th>
							<td>{{ $contrato->fecha_suspension ?? 'Ninguna' }}</td>
						</tr>
						<tr>
							<th>No Suspensión Automática</th>
							<td>{{ $contrato->tipo_nosuspension == 1 ? 'Habilitado' : 'Deshabilitado' }}</td>
						</tr>
						@if($contrato->tipo_nosuspension == 1)
						<tr>
							<th>Fecha Desde No Suspensión</th>
							<td>{{ $contrato->fecha_desde_nosuspension ?? 'N/A' }}</td>
						</tr>
						<tr>
							<th>Fecha Hasta No Suspensión</th>
							<td>{{ $contrato->fecha_hasta_nosuspension ?? 'N/A' }}</td>
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
						<tr>
							<th>Oficina Asociada</th>
							<td>{{ $contrato->oficina() ? $contrato->oficina()->nombre : 'N/A' }}</td>
						</tr>
						<tr>
							<th>Vendedor</th>
							<td>{{ $contrato->vendedor() ? $contrato->vendedor()->nombre : 'N/A' }}</td>
						</tr>
						<tr>
							<th>Canal de Venta</th>
							<td>{{ $contrato->canal() ? $contrato->canal()->nombre : 'N/A' }}</td>
						</tr>
						<tr>
							<th>Observaciones</th>
							<td>{{ $contrato->observaciones ?? 'N/A' }}</td>
						</tr>
						<tr>
							<th>Contrato Registrado por</th>
							<td>{{ $contrato->creador ?? 'N/A' }}</td>
						</tr>
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
						<tr>
							<th>Vendedor</th>
							<td>{{ $contrato->vendedor() ? $contrato->vendedor()->nombre : 'N/A' }}</td>
						</tr>
						<tr>
							<th>Canal de Venta</th>
							<td>{{ $contrato->canal() ? $contrato->canal()->nombre : 'N/A' }}</td>
						</tr>
						<tr>
							<th width="20%">Plan Contratado</th>
							<td><a href="{{route('inventario.show',$contrato->servicio_tv)}}" target="_blank"><strong>{{ $inventario->producto }}</strong></a></td>
						</tr>
						<tr>
							<th>SN / MAC</th>
							<td>{{ $contrato->olt_sn_mac ?? 'N/A' }}</td>
						</tr>
						<tr>
							<th>Estado CATV</th>
							<td><strong class="text-{{ $contrato->state_olt_catv == 1 ? 'success' : 'danger' }}">{{ $contrato->state_olt_catv == 1 ? 'Habilitado' : 'Deshabilitado' }}</strong></td>
						</tr>
						<tr>
							<th>Precio del Plan Contratado</th>
							<td>{{ Auth::user()->empresa()->moneda }} {{ App\Funcion::Parsear($inventario->precio) }}</td>
						</tr>
						<tr>
							<th>Precio Personalizado TV</th>
							<td>{{ $contrato->precio_personalizado_tv ? Auth::user()->empresa()->moneda . ' ' . App\Funcion::Parsear($contrato->precio_personalizado_tv) : 'N/A' }}</td>
						</tr>
						<tr>
							<th>Costo de Reconexión</th>
							<td>{{ Auth::user()->empresa()->moneda }} {{ App\Funcion::Parsear($contrato->costo_reconexion) }}</td>
						</tr>
						<tr>
							<th>Descuento</th>
							<td>{{ $contrato->descuento ? $contrato->descuento . '%' : 'N/A' }}</td>
						</tr>
						<tr>
							<th>Contrato de Permanencia</th>
							<td>{{ $contrato->contrato_permanencia == 1 ? 'Si' : 'No' }}</td>
						</tr>
						<tr>
							<th>Tipo Contrato</th>
							<td>{{ ucfirst($contrato->tipo_contrato) ?? 'N/A' }}</td>
						</tr>
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
