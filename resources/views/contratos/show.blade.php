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
	        background: {{Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '#0d47a1'}} !important;
	        border-color: {{Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '#0d47a1'}} !important;
	        color: #fff !important;
	    }
        .card-premium {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            background: #fff;
            margin-bottom: 2rem;
            overflow: hidden;
        }
        .card-header-premium {
            background: {{Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '#0d47a1'}};
            color: white;
            padding: 1.25rem 1.5rem;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .table-premium {
            margin-bottom: 0;
        }
        .table-premium th {
            width: 35%;
            background-color: #fcfcfc;
            color: #444;
            font-weight: 600;
            padding: 12px 20px !important;
            border-bottom: 1px solid #f0f0f0 !important;
            border-right: 1px solid #f0f0f0 !important;
        }
        .table-premium td {
            padding: 12px 20px !important;
            color: #555;
            border-bottom: 1px solid #f0f0f0 !important;
        }
        .table-premium tr:last-child th, .table-premium tr:last-child td {
            border-bottom: none !important;
        }
        .badge-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
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
        <div class="col-md-10 offset-md-1">
            <div class="card card-premium">
                <div class="card-header-premium">
                    <span><i class="fas fa-info-circle mr-2"></i> Información Detallada del Contrato</span>
                    <span class="badge badge-light" style="color: {{Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '#0d47a1'}}"> #{{ $contrato->nro }}</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-premium">
                            <tbody>
                                <tr>
                                    <th>Nro. Contrato</th>
                                    <td><strong>{{ $contrato->nro }}</strong></td>
                                </tr>
                                <tr>
                                    <th>Nombre Servicio</th>
                                    <td>{{ $contrato->servicio }}</td>
                                </tr>
                                <tr>
                                    <th>Estrato</th>
                                    <td>{{ $contrato->estrato ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Tecnología</th>
                                    <td>{{ $contrato->tecnologia() }}</td>
                                </tr>
                                <tr>
                                    <th>Tipo de Conexión</th>
                                    <td><strong>{{ $contrato->conexion() }}</strong></td>
                                </tr>
                                <tr>
                                    <th>Grupo de Corte</th>
                                    <td>
                                        @if($contrato->grupo_corte())
                                            <a href="{{ route('grupos-corte.show',$contrato->grupo_corte()->id )}}" target="_blank">
                                                <strong>{{ $contrato->grupo_corte()->nombre }}</strong>
                                            </a> 
                                            <span class="text-muted ml-1">(CORTE {{ $contrato->grupo_corte()->fecha_corte }} - SUSPENSIÓN {{ $contrato->grupo_corte()->fecha_suspension }})</span>
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Estado Contrato</th>
                                    <td>
                                        <span class="badge-status bg-{{$contrato->status('true')}} text-white">
                                            {{$contrato->status()}}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Tipo Contrato</th>
                                    <td>{{ ucfirst($contrato->tipo_contrato) ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Dirección IP</th>
                                    <td>
                                        <a href="http://{{ $contrato->ip }}{{ $contrato->puerto ? ':'.$contrato->puerto->nombre : '' }}" target="_blank">
                                            {{ $contrato->ip }}{{ $contrato->puerto ? ':'.$contrato->puerto->nombre : '' }} 
                                            <i class="fas fa-external-link-alt ml-1" style="font-size: 0.8em;"></i>
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Dirección de Instalación</th>
                                    <td>{{ $contrato->address_street ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Dirección GPS</th>
                                    <td>
                                        @php
                                            $url_gps = ($contrato->latitude && $contrato->longitude) ? 'https://www.google.com/maps/search/'.$contrato->latitude.','.$contrato->longitude.'?hl=es' : null;
                                        @endphp
                                        @if($url_gps)
                                            <span class="text-muted">({{$contrato->latitude}}, {{$contrato->longitude}})</span>
                                            <a href="{{ $url_gps }}" target="_blank" class="ml-2">
                                                Ver en Google Maps <i class="fas fa-map-marker-alt"></i>
                                            </a>
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Servidor Asociado</th>
                                    <td>
                                        @if($contrato->servidor())
                                            <a href="{{ route('mikrotik.show',$contrato->server_configuration_id )}}" target="_blank">
                                                <strong>{{ $contrato->servidor()->nombre }}</strong>
                                            </a>
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Tipo de Facturación</th>
                                    <td>{{ $contrato->facturacion() }}</td>
                                </tr>
                                <tr>
                                    <th>Plan Contratado</th>
                                    <td>
                                        @if($contrato->plan_id)
                                            <a href="{{route('planes-velocidad.show',$contrato->plan_id)}}" target="_blank">
                                                <strong>{{ $contrato->plan()->name }}</strong>
                                            </a>
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Precio Plan</th>
                                    <td>
                                        @if($contrato->precio_personalizado_internet)
                                            <span class="text-primary font-weight-bold" title="Precio Personalizado">
                                                {{ Auth::user()->empresa()->moneda }} {{ App\Funcion::Parsear($contrato->precio_personalizado_internet) }}
                                            </span>
                                        @else
                                            {{ Auth::user()->empresa()->moneda }} {{ App\Funcion::Parsear($contrato->plan()->price) }}
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Otro servicio</th>
                                    <td>
                                        @if($servicio_otro)
                                            <a href="{{route('inventario.show', $servicio_otro->id)}}" target="_blank">
                                                <strong>{{ $servicio_otro->producto }}</strong>
                                            </a>
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Valor otro servicio</th>
                                    <td>
                                        @if($servicio_otro)
                                            {{ Auth::user()->empresa()->moneda }} {{ number_format($servicio_otro->precio, 0, ',', '.') }}
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Contrato de Permanencia</th>
                                    <td>
                                        {{ $contrato->contrato_permanencia == 1 ? 'Si' : 'No' }} 
                                        @if($contrato->contrato_permanencia == 1 && $contrato->contrato_permanencia_meses) 
                                            <span class="text-muted ml-1">({{ $contrato->contrato_permanencia_meses }} meses)</span> 
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Facturación Individual</th>
                                    <td>
                                        <span class="badge badge-{{ $contrato->factura_individual == 1 ? 'info' : 'secondary' }}">
                                            {{ $contrato->factura_individual == 1 ? 'Si' : 'No' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Contrato Registrado por</th>
                                    <td>{{ $contrato->creador ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Contrato Registrado el</th>
                                    <td>
                                        <span id="fecha-registro-display" class="font-weight-bold">
                                            {{date('d-m-Y g:i:s A', strtotime($contrato->created_at))}}
                                        </span>
                                        @if(isset($_SESSION['permisos']['406']))
                                            <a href="javascript:abrirModalEditarFecha({{$contrato->id}}, '{{date('d-m-Y H:i:s', strtotime($contrato->created_at))}}')" 
                                               class="ml-2 text-primary" title="Editar fecha">
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
