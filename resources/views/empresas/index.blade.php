@extends('layouts.app')
@section('boton')
	@if(!isset($inactivas))
		<a href="{{route('empresas.create')}}" class="btn btn-primary btn-sm" ><i class="fas fa-plus"></i> Nueva Empresa</a>
		<a href="{{route('empresas.inactivas')}}" class="btn btn-outline-light btn-sm" >Empresas Inactivas</a>
	@endif

@endsection
@section('content')
	@if(Session::has('success'))
		<div class="alert alert-success" style="margin-left: 2%;margin-right: 2%;">
			{{Session::get('success')}}
		</div>
	@endif
	<div class="row card-description">
		<div class="col-md-12">
			<table class="table table-striped table-hover" id="example">
			<thead class="thead-dark">
				<tr>
				  <th>ID</th>
	              <th>Logo</th>
	              <th>Tipo de identificación</th>
	              <th>Identificación</th>
				  <th>Nombre</th>
				  <th>Dian</th>
				  <th>Emitidas</th>
	              <th>Tipo de Persona</th>
	              <th>Telefono</th>
	              <th>Correo Electrónico</th>
				  <th>Usuario</th>
				  <th>Fecha Creación</th>
				  @if(Auth::user()->rol == 1)
				  <th>Suscripción</th>
				  @endif
				  <th>Acciones</th>
	          </tr>
			</thead>
			<tbody>
				@foreach($empresas as $empresa)
					<tr @if($empresa->id==Session::get('empresa_id')) class="active" @endif>
						<td>{{$empresa->id}}</td>
						<td>@if($empresa->logo)
	                        <div class="project-wrapper" style=" width: 50%;">
	                          <div class="project">
	                            <div class="photo-wrapper">
	                                <div class="photo" style="background: #fff; padding-left: 20%;"><img class="img-contenida" src="{{ contabo_url(env('LOGOS_FOLDER', 'logos'), 'logo.png') }}" alt="">
	                                </div>
	                                <div class="overlay"></div>
	                            </div>
	                          </div>
	                      </div>
	                      @else
	                      Sin Logo
	                      @endif
						</td>
						<td>{{$empresa->tip_iden()}}</td>
						<td>{{$empresa->nit}}</td>
						<td>{{$empresa->nombre}}</td>
						<td>@if($empresa->estado_dian == 1) <strong class="text-success">Activo</strong> @else <strong class="text-danger">Innactivo</strong> @endif</td>
						<td>{{$empresa->totalEmissions()}}</td>
                        <td>{{$empresa->tipo_persona()}}</td>
                        <td>{{$empresa->telefono}}</td>
                        <td>{{$empresa->email}}</td>
                        <td>{{$empresa->usuario()?$empresa->usuario()->username:'- - -'}}</td>
						<td>{{date('d-m-Y h:m a', strtotime($empresa->created_at))}}</td>
						@if(Auth::user()->rol == 1)
						<td>
							@if(isset($empresa->is_subscription_active) && $empresa->is_subscription_active)
								<strong class="text-success">Activa</strong>
							@else
								<strong class="text-danger">Suspendida</strong>
							@endif
						</td>
						@endif
						<td><a href="{{route('empresas.edit',$empresa->id)}}" class="btn btn-outline-primary btn-icons"><i class="fas fa-edit"></i></a>
							@if($empresa->status==1)
								<form action="{{ route('empresas.desactivar',$empresa->id) }}" method="post" class="delete_form" style="margin:  0;display: inline-block;" id="desactivar-empresa{{$empresa->id}}">
            						{{ csrf_field() }}
        						</form>
            					<button class="btn btn-outline-danger  btn-icons" type="button" title="Desactivar" onclick="confirmar('desactivar-empresa{{$empresa->id}}', '¿Estas seguro que deseas desactivar la empresa?', 'Se enviara a empresas inactivas');"><i class="fas fa-power-off"></i></button>
    						@else
    							<form action="{{ route('empresas.activar',$empresa->id) }}" method="post" class="delete_form" style="margin:  0;display: inline-block;" id="activar-empresa{{$empresa->id}}">
            						{{ csrf_field() }}

        						</form>
        						<button class="btn btn-outline-success  btn-icons" type="submit" title="Activar" onclick="confirmar('activar-empresa{{$empresa->id}}', '¿Estas seguro que deseas activar la empresa?');"><i class="fas fa-power-off"></i></button>
    						@endif
                            <a title="Ingresar" class="btn btn-outline-success  btn-icons" href="{{route('empresas.ingresar', $empresa->email)}}"><i class="fas fa-sign-in-alt"></i></a>
                            @if(Auth::user()->rol == 1)
                            <button class="btn btn-outline-warning btn-icons btn-cambiar-suscripcion"
                                type="button"
                                title="Cambiar Estado Suscripción"
                                data-empresa-id="{{$empresa->id}}"
                                data-empresa-nombre="{{$empresa->nombre}}"
                                data-estado-actual="{{isset($empresa->is_subscription_active) && $empresa->is_subscription_active ? '1' : '0'}}">
                                <i class="fas fa-credit-card"></i>
                            </button>
                            @endif
						</td>
					</tr>
				@endforeach
			</tbody>
		</table>
		</div>
	</div>
@endsection

@section('scripts')
@if(Auth::user()->rol == 1)
<script>
    $(document).ready(function() {
        console.log('Script de cambiar suscripción cargado');

        $(document).on('click', '.btn-cambiar-suscripcion', function(e) {
            e.preventDefault();
            e.stopPropagation();

            console.log('Botón de cambiar suscripción clickeado');

            const empresaId = $(this).data('empresa-id');
            const empresaNombre = $(this).data('empresa-nombre');
            const estadoActual = $(this).data('estado-actual') == '1';

            console.log('Datos:', {empresaId: empresaId, empresaNombre: empresaNombre, estadoActual: estadoActual});

            const estadoActualTexto = estadoActual ? 'Activa' : 'Suspendida';
            const nuevoEstado = !estadoActual;
            const nuevoEstadoTexto = nuevoEstado ? 'habilitar' : 'suspender';

            Swal.fire({
                title: 'Cambiar Estado de Suscripción',
                html: '<p>Empresa: <strong>' + empresaNombre + '</strong></p>' +
                      '<p>Estado actual: <strong>' + estadoActualTexto + '</strong></p>' +
                      '<p>¿Desea ' + nuevoEstadoTexto + ' la suscripción de esta empresa?</p>' +
                      '<input type="password" id="password_suscripcion" class="swal2-input" placeholder="Ingrese la contraseña" autocomplete="new-password" required>',
                type: 'question',
                showCancelButton: true,
                confirmButtonText: nuevoEstadoTexto.charAt(0).toUpperCase() + nuevoEstadoTexto.slice(1),
                cancelButtonText: 'Cancelar',
                confirmButtonColor: nuevoEstado ? '#28a745' : '#dc3545',
                cancelButtonColor: '#6c757d',
                focusConfirm: false,
                onOpen: function() {
                    const passwordInput = document.getElementById('password_suscripcion');
                    if (passwordInput) {
                        passwordInput.setAttribute('autocomplete', 'new-password');
                        passwordInput.setAttribute('autocapitalize', 'off');
                        passwordInput.setAttribute('autocorrect', 'off');
                        passwordInput.setAttribute('spellcheck', 'false');
                        passwordInput.value = ''; // Limpiar cualquier valor previo
                        setTimeout(function() {
                            passwordInput.focus(); // Enfocar el input después de un pequeño delay
                        }, 100);
                        console.log('Modal abierto, input de contraseña configurado');
                    }
                },
                preConfirm: function() {
                    const password = document.getElementById('password_suscripcion').value;
                    if (!password) {
                        Swal.showValidationMessage('La contraseña es obligatoria');
                        return false;
                    }
                    return password;
                }
            }).then(function(result) {
                console.log('Resultado del modal completo:', result);
                console.log('result.value:', result ? result.value : 'undefined');
                console.log('result.isConfirmed:', result ? result.isConfirmed : 'undefined');
                console.log('result.dismiss:', result ? result.dismiss : 'undefined');

                // Verificar si el usuario confirmó - si tiene value y no tiene dismiss, se confirmó
                if (result && result.value !== undefined && result.value !== null && !result.dismiss) {
                    const password = result.value;

                    console.log('Usuario confirmó con contraseña, procesando...');
                    console.log('Contraseña recibida, empresaId:', empresaId, 'nuevoEstado:', nuevoEstado);

                    // Mostrar loading
                    Swal.fire({
                        title: 'Procesando...',
                        text: 'Por favor espere',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        onOpen: function() {
                            Swal.showLoading();
                        }
                    });

                    const url = '{{ route("empresas.cambiar_estado_suscripcion", ":id") }}'.replace(':id', empresaId);
                    console.log('URL de la petición:', url);

                    // Realizar petición AJAX
                    $.ajax({
                        url: url,
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            password: password,
                            estado: nuevoEstado ? 1 : 0
                        },
                        beforeSend: function() {
                            console.log('Enviando petición AJAX...');
                        },
                        success: function(response) {
                            console.log('Respuesta exitosa:', response);

                            Swal.close();

                            if (response && response.success) {
                                Swal.fire({
                                    title: 'Éxito',
                                    text: response.message || 'Operación realizada exitosamente',
                                    type: 'success',
                                    confirmButtonText: 'Aceptar'
                                }).then(function() {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    title: 'Error',
                                    text: response.message || 'Ha ocurrido un error',
                                    type: 'error',
                                    confirmButtonText: 'Aceptar'
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            console.log('Error en AJAX:', xhr);
                            console.log('Status:', status);
                            console.log('Error:', error);

                            Swal.close();

                            let mensaje = 'Ha ocurrido un error al procesar la solicitud';

                            if (xhr.responseJSON) {
                                console.log('Response JSON:', xhr.responseJSON);

                                if (xhr.responseJSON.message) {
                                    mensaje = xhr.responseJSON.message;
                                } else if (xhr.responseJSON.errors) {
                                    // Si hay errores de validación
                                    const errors = xhr.responseJSON.errors;
                                    const errorMessages = [];
                                    for (let key in errors) {
                                        if (Array.isArray(errors[key])) {
                                            errorMessages.push(errors[key][0]);
                                        } else {
                                            errorMessages.push(errors[key]);
                                        }
                                    }
                                    mensaje = errorMessages.join('<br>');
                                }
                            } else if (xhr.responseText) {
                                console.log('Response Text:', xhr.responseText);
                                mensaje = xhr.responseText.substring(0, 500); // Limitar a 500 caracteres
                            }

                            Swal.fire({
                                title: 'Error',
                                html: mensaje,
                                type: 'error',
                                confirmButtonText: 'Aceptar'
                            });
                        }
                    });
                } else if (result && result.dismiss) {
                    console.log('Usuario canceló la operación o cerró el modal:', result.dismiss);
                } else {
                    console.log('Resultado inesperado:', result);
                }
            });
        });
    });
</script>
@endif
@endsection
