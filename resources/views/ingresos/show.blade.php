@extends('layouts.app')

@section('boton')
    @if(auth()->user()->modo_lectura())
	    <div class="alert alert-warning text-left" role="alert">
	        <h4 class="alert-heading text-uppercase">Integra Colombia: Suscripción Vencida</h4>
	       <p>Si desea seguir disfrutando de nuestros servicios adquiera alguno de nuestros planes.</p>
<p>Medios de pago Nequi: 3206909290 Cuenta de ahorros Bancolombia 42081411021 CC 1001912928 Ximena Herrera representante legal. Adjunte su pago para reactivar su membresía</p>
	    </div>
	@else
	    @if($ingreso->tipo==1 || $ingreso->tipo==2)


	            @php 
	                $nombre = "Ingreso Nro." . $ingreso->nro . ".pdf";
	                if($ingreso->tipo == 1 && $ingreso->ingresofactura() && $ingreso->ingresofactura()->factura()){
	                    $nombre = "Factura No. " . $ingreso->ingresofactura()->factura()->id . ".pdf";
	                }
	            @endphp

	            <a href="{{route('ingresos.tirilla', ['id' => $ingreso->nro, 'name' => $nombre])}}" class="btn btn-outline-warning @if(Auth::user()->rol==47) btn-xl @else btn-xs @endif" title="Tirilla" target="_blank" id="btn_tirilla"><i class="fas fa-print"></i>Imprimir tirilla</a>
	        @if($ingreso->ingresofactura() && $ingreso->whatsapp == 0)
	            <a href="{{ route('ingresos.tirillawpp', ['id' => $ingreso->nro]) }}" class="btn btn-success @if(Auth::user()->rol==47) btn-xl @else btn-xs @endif" title="Tirilla" id="btn_tirilla"><i class="fab fa-whatsapp"></i>Enviar tirilla por Whatsapp</a>
	        @endif
	    @endif

	    @if($ingreso->tipo!=3)
	        @if($ingreso->tipo!=4)
		        @if(isset($_SESSION['permisos']['48']))
			        <a href="{{route('ingresos.edit',$ingreso->id)}}" class="btn btn-outline-primary btn-xs"><i class="fas fa-edit"></i>Editar</a>
			    @endif
		    @endif
		    @if(isset($_SESSION['permisos']['49']))
		        <form action="{{ route('ingresos.destroy',$ingreso->id) }}" method="post" class="delete_form" style="margin:  0;display: inline-block;" id="eliminar-ingreso{{$ingreso->id}}">
		        	{{ csrf_field() }}
		        	<input name="_method" type="hidden" value="DELETE">
		        </form>
		        <button class="btn btn-outline-danger btn-xs" type="submit" title="Eliminar" onclick="confirmar('eliminar-ingreso{{$ingreso->id}}', '¿Estas seguro que deseas eliminar el ingreso?', 'Se borrara de forma permanente');"><i class="fas fa-times"></i> Eliminar</button>
		    @endif
		@endif

		@if($ingreso->tipo!=3 && $ingreso->tipo!=4)
		    @if(isset($_SESSION['permisos']['49']))
		        <form action="{{ route('ingresos.anular',$ingreso->id) }}" method="post" class="delete_form" style="display: none;" id="anular-ingreso{{$ingreso->id}}">
		        	{{ csrf_field() }}
		        </form>
		        @if($ingreso->estatus==1)
		            <button class="btn btn-outline-info btn-xs"  type="button" title="Anular" onclick="confirmar('anular-ingreso{{$ingreso->id}}', '¿Está seguro de que desea anular el ingreso?', ' ');"><i class="fas fa-minus"></i>Anular</button>
		        @else
		            <button  class="btn btn-outline-info btn-xs" type="button" title="Abrir" onclick="confirmar('anular-ingreso{{$ingreso->id}}', '¿Está seguro de que desea abrir el ingreso?', ' ');">
		            	<i class="fas fa-unlock-alt"> Convertir a Abierta</i>
		            </button>
		        @endif
		    @endif
		@endif
    @endif

    <div class="alert alert-warning nopadding onlymovil" style="text-align: center;">
    	<button type="button" class="close" data-dismiss="alert">×</button>
    	<strong><small><i class="fas fa-angle-double-left"></i> Deslice <i class="fas fa-angle-double-right"></i></small></strong>
    </div>
@endsection

@section('content')
    {{-- Alerta de rebotes WhatsApp --}}
    @if($ingreso->cont_message_undeliverable >= 3)
        <div class="row mt-3 mx-3">
            <div class="col-md-12">
                <div class="alert alert-danger shadow-sm" role="alert" style="border-left: 5px solid #dc3545;">
                    <h5 class="alert-heading mb-1"><i class="fas fa-exclamation-circle"></i> Atención</h5>
                    La siguiente linea telefónica según nuestros análisis probablemente no tiene una linea de whatsapp activa, te recomendamos comunicarte y enviar el documento con otra alternativa.
                </div>
            </div>
        </div>
    @endif

    {{-- Bloque de Logs de WhatsApp vinculados a este ingreso --}}
    @if(isset($whatsappLogs) && $whatsappLogs->count() > 0)
        <div class="row mt-4 mx-3">
            <div class="col-md-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header d-flex justify-content-between align-items-center bg-white border-0">
                        <div>
                            <h5 class="mb-1">
                                <i class="fab fa-whatsapp text-success mr-1"></i>
                                Historial de WhatsApp de este ingreso
                            </h5>
                            <small class="text-muted">
                                Mensajes que el cliente <strong>recibió</strong> (<em>delivered</em>) o <strong>abrió y leyó</strong> (<em>read</em>).
                            </small>
                        </div>
                        <span class="badge badge-pill badge-success">
                            {{ $whatsappLogs->count() }} mensaje{{ $whatsappLogs->count() > 1 ? 's' : '' }}
                        </span>
                    </div>

                    <div class="card-body pt-0 pb-3" style="max-height: 260px; overflow-y: auto;">
                        <ul class="list-unstyled mb-0">
                            @foreach($whatsappLogs as $log)
                                <li class="media py-3 border-bottom">
                                    <div class="mr-3 text-center" style="min-width: 70px;">
                                        <div class="text-muted small mb-1">
                                            {{ \Carbon\Carbon::parse($log->created_at)->format('d/m/Y') }}
                                        </div>
                                        <div class="font-weight-bold small">
                                            {{ \Carbon\Carbon::parse($log->created_at)->format('H:i') }}
                                        </div>
                                    </div>
                                    <div class="media-body">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <div>
                                                {!! $log->estadoFormateado() !!}
                                            </div>
                                            @if($log->contacto)
                                                <small class="text-muted">
                                                    Para: {{ $log->contacto->nombre ?? '' }} {{ $log->contacto->apellido1 ?? '' }}
                                                </small>
                                            @endif
                                        </div>
                                        <div class="bg-light rounded px-3 py-2">
                                            <span class="text-dark" style="white-space: pre-line;">
                                                {{ $log->mensaje_enviado }}
                                            </span>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="row card-description">
    	<div class="col-md-12">
    		@if(Session::has('success') || Session::has('error'))
    		    @if(Session::has('success'))
    		        <div class="alert alert-success alert-view-show">
    		        	{{Session::get('success')}}
    		        </div>
    		    @endif
    		    @if(Session::has('error'))
    		        <div class="alert alert-danger alert-view-show">
    		        	{{Session::get('error')}}
    		        </div>
    		    @endif
    		    <script type="text/javascript">
    		    	setTimeout(function(){
    		    		$('.alert').hide();
    		    		$('.active_table').attr('class', ' ');
    		    	}, 5000);
    		    </script>
    		@endif
			<div class="table-responsive">
				<table class="table table-striped table-bordered table-sm info">
					<tbody>
						<tr>
							<th width="15%">Recibo de Caja</th>
							<td>{{$ingreso->nro}}</td>
						</tr>
						<tr>
							<th>Estado</th>
							<td>{{$ingreso->estatus()}}</td>
						</tr>
						<tr>
							<th>Beneficiario</th>
							<td>@if($ingreso->cliente()) <a href="{{route('contactos.show',$ingreso->cliente()->id)}}" target="_blank">{{$ingreso->cliente()->nombre}}  {{$ingreso->cliente()->apellidos()}}</a >@else {{auth()->user()->empresa()->nombre}} @endif</td>
						</tr>
						<tr>
							<th>Fecha</th>
							<td>{{date('d-m-Y', strtotime($ingreso->fecha))}}</td>
						</tr>
						<tr>
							<th>Cuenta</th>
							<td><a href="{{route('bancos.show',$ingreso->cuenta()->nro)}}" target="_blank">{{$ingreso->cuenta()->nombre}}</a></td>
						</tr>
						<tr>
							<th>Observaciones</th>
							<td>{{$ingreso->observaciones}}</td>
						</tr>
						<tr>
							<th>Notas</th>
							<td>{{$ingreso->notas}}</td>
						</tr>
						<tr>
							<th>Método de pago</th>
							<td>{{$ingreso->metodo_pago()}}</td>
						</tr>
						<tr>
							<th>Total</th>
							<td>{{Auth::user()->empresa()->moneda}} {{App\Funcion::Parsear($ingreso->pago())}}</td>
						</tr>
						@if($ingreso->adjunto_pago)
						<tr>
							<th>Soporte de Pago</th>
							<td><a href="{{asset('adjuntos/documentos/'.$ingreso->adjunto_pago)}}" target="_blank">Ver Archivo</a></td>
						</tr>
						@endif
						@if($ingreso->created_by)
						<tr>
							<th><strong>Realizado por</strong></th>
							<td>{{$ingreso->created_by()->nombres}}</td>
						</tr>
						@endif
						@if($ingreso->updated_by)
						<tr>
							<th><strong>Actualizado por</strong></th>
							<td>{{$ingreso->updated_by()->nombres}}</td>
						</tr>
						@endif
					</tbody>
				</table>
			</div>
		</div>
	</div>

    @if($ingreso->tipo==1)
        @if(count($items) == 0)
            <div class="alert alert-info" role="alert">
                <strong>Información:</strong> Este ingreso no tiene facturas asociadas.
            </div>
        @endif
        <div class="alert alert-warning nopadding onlymovil" style="text-align: center;">
			<button type="button" class="close" data-dismiss="alert">×</button>
			<strong><small><i class="fas fa-angle-double-left"></i> Deslice <i class="fas fa-angle-double-right"></i></small></strong>
		</div>
		<div class="row card-description">
			<div class="col-md-12">
				<div class="table-responsive">
					<table class="table table-striped table-bordered table-sm pagos">
						<thead>
							<tr>
								<th>Número</th>
								<th>Fecha</th>
								<th>Vencimiento</th>
								<th>Total</th>
								<th>Pagado</th>
								<th>Por Pagar</th>
								<th>Monto</th>
								<th>Retenciones</th>
								<th>Total</th>
							</tr>
						</thead>
						<tbody>
							@foreach($items as $item)
							@if($item->factura())
								<tr>
									<td><a href="{{route('facturas.show', $item->factura()->id)}}" target="_blank">{{$item->factura()->codigo}}</a></td>
									<td>{{date('d-m-Y', strtotime($item->factura()->fecha))}}</td>
									<td>{{date('d-m-Y', strtotime($item->factura()->vencimiento))}}</td>
									<td>{{Auth::user()->empresa()->moneda}} {{App\Funcion::Parsear($item->factura()->total()->total)}}</td>
									<td>{{Auth::user()->empresa()->moneda}} {{App\Funcion::Parsear($item->factura()->pagado())}}</td>
									<td>{{Auth::user()->empresa()->moneda}} {{App\Funcion::Parsear(($item->factura()->porpagar()))}}</td>
									<td>{{Auth::user()->empresa()->moneda}} {{App\Funcion::Parsear($item->pago)}}</td>
									<td class="retencion">@foreach($item->retenciones() as $retenido)
										<div class="row">
											<div class="col-md-12" >
												<div class="row">
													<div class="col-md-8 text-right"><small>{{$retenido->retencion()->nombre}} {{$retenido->retencion}}%</small></div>
													<div class="col-md-4 text-right"><small>-{{Auth::user()->empresa()->moneda}} {{App\Funcion::Parsear($retenido->valor)}}</small></div>
												</div>
											</div>
										</div>
						                @endforeach
						            </td>
									<td>
										{{Auth::user()->empresa()->moneda}} {{App\Funcion::Parsear($item->pago())}}
									</td>
								</tr>
								@endif
							@endforeach
						</tbody>

					</table>
				</div>
			</div>
		</div>
	@elseif($ingreso->tipo==2 || $ingreso->tipo==4)
		<div class="row card-description">
			<div class="col-md-12">
				<div class="table-responsive">
					<table class="table table-striped table-bordered table-sm pagos">
						<thead>
							<tr>
								<th>Categoria</th>
								<th>Valor</th>
								<th>Impuesto</th>
								<th>Cantidad</th>
								<th>Observaciones</th>
								<th>Total</th>
							</tr>
						</thead>
						<tbody>
							@foreach($items as $item)
								<tr>
									<td>{{$item->categoria()->nombre}}</td>
									<td>{{Auth::user()->empresa()->moneda}} {{App\Funcion::Parsear($item->valor)}}</td>
									<td>{{$item->impuesto()}}</td>
									<td>{{round($item->cant)}}</td>
									<td class="text-center">{{$item->descripcion}}</td>
									<td>{{Auth::user()->empresa()->moneda}} {{App\Funcion::Parsear($item->valor)}}</td>
								</tr>
							@endforeach
						</tbody>

					</table>
				</div>
			</div>
		</div>

		<div class="row card-description d-none">
		    <div class="col-md-4 offset-md-8">
		    	<table style="text-align: right;  width: 100%;" id="totales">
		    		<tr>
		    			<td width="40%">Subtotal</td>
		    			<td>{{Auth::user()->empresa()->moneda}} <span id="subtotal_categoria"> {{App\Funcion::Parsear($ingreso->total()->subtotal)}}</span></td>
		    		</tr>
		    		@php $cont=0; @endphp
		    		@if($ingreso->total()->imp)
		    		    @foreach($ingreso->total()->imp as $imp)
		    		        @if(isset($imp->total))
		    		            @php $cont+=1; @endphp
		    		            <tr id="imp{{$cont}}">
		    		            	<td>{{$imp->nombre}} ({{$imp->porcentaje}}%)</td>
		    		            	<td id="totalimp{{$cont}}">{{Auth::user()->empresa()->moneda}} {{App\Funcion::Parsear($imp->total)}}</td>
		    		            </tr>
		    		        @endif
		    		    @endforeach
		            @endif
		        </table>
		        <table style="text-align: right; width: 100%;" id="totalesreten">
		        	<tbody>
		                @php $cont=0; @endphp
		                @if($ingreso->total()->reten)
		                    @foreach($ingreso->total()->reten as $reten)
		                        @if(isset($reten->total))
		                            <tr id="retentotal{{$cont}}">
		                            	<td width="40%" style="font-size: 0.8em;">{{$reten->nombre}} ({{$reten->porcentaje}}%)</td>
		                            	<td id="retentotalvalue{{$cont}}">-{{Auth::user()->empresa()->moneda}}{{App\Funcion::Parsear($reten->total)}} </td>
		                            </tr>
		                            @php $cont+=1; @endphp
		                        @endif
		                    @endforeach
		                @endif
		            </tbody>
		        </table>
		        <hr>
		        <table style="text-align: right; font-size: 24px !important; width: 100%;">
		        	<tr>
		        		<td width="40%">TOTAL</td>
		        		<td>{{Auth::user()->empresa()->moneda}} <span id="total_categoria">{{App\Funcion::Parsear($ingreso->total()->total)}} </span></td>
		        	</tr>
		        </table>
		    </div>
		</div>
	@else
		<div class="row card-description">
			<div class="col-md-8">
				<div class="table-responsive">
					<table class="table table-striped table-bordered table-sm pagos">
						<thead>
							<tr>
								<th class="text-left">Nota débito</th>
								<th class="text-left">Fecha</th>
								<th class="text-right">Valor devuelto</th>
							</tr>
						</thead>
						<tfoot>
							<tr>
								@if(isset($ingreso->notas->nro))
									<td class="text-left">
										<a href="{{ route('notasdebito.show', $ingreso->notas->nro) }}">
											{{ $ingreso->notas->nro }}
										</a>
									</td>
								@else
									<td class="text-left">-</td>
								@endif
								<td class="text-left">{{date('d-m-Y', strtotime($ingreso->fecha))}}</td>
								<td class="text-right">{{Auth::user()->empresa()->moneda}} {{App\Funcion::Parsear($ingreso->total_debito)}}</td>
							</tr>
						</tfoot>
					</table>
				</div>
			</div>
		</div>
	@endif
@endsection

@section('scripts')
    @if(Session::has('tirilla'))
	    @if(Session::get('tirilla'))
			<script>
				$(document).ready(function() {
					$("#btn_tirilla")[0].click();
				});
			</script>
		@endif
	@endif
@endsection
