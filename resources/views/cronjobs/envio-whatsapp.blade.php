@extends('layouts.app')
@section('content')
<style>
.switch-container {
    display: inline-flex;
    align-items: center;
    margin-left: 15px;
    vertical-align: middle;
    background: #f8f9fa;
    padding: 8px 15px;
    border-radius: 30px;
    border: 1px solid #e9ecef;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}
.switch {
    position: relative;
    display: inline-block;
    width: 46px;
    height: 24px;
    margin-bottom: 0;
    margin-right: 10px;
}
.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #cbd5e1;
    transition: .3s cubic-bezier(0.4, 0.0, 0.2, 1);
    border-radius: 34px;
}
.slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s cubic-bezier(0.4, 0.0, 0.2, 1);
    border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
input:checked + .slider {
    background-color: #10b981;
}
input:checked + .slider:before {
    transform: translateX(22px);
}
.switch-label {
    font-weight: 600;
    color: #475569;
    font-size: 14px;
    cursor: pointer;
}
.badge-status {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    margin-left: 10px;
    transition: all 0.3s ease;
}
.badge-apagado {
    background-color: #f1f5f9;
    color: #64748b;
    border: 1px solid #e2e8f0;
}
.badge-activo {
    background-color: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
    animation: pulse-green 2s infinite;
}
@keyframes pulse-green {
    0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
    70% { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
    100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}
</style>
<input type="hidden" id="valuefecha" value="{{$request->fechas}}">
<input type="hidden" id="primera" value="{{$request->date ? $request->date['primera'] : ''}}">
<input type="hidden" id="ultima" value="{{$request->date ? $request->date['ultima'] : ''}}">

@if(Session::has('success'))
<div class="alert alert-success">
    {{Session::get('success')}}
</div>
<script type="text/javascript">
    setTimeout(function() {
        $('.alert-success').hide();
        $('.alert-danger').hide();
        $('.active_table').attr('class', ' ');
    }, 20000);
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
    }, 20000);
</script>
@endif

	<form id="form-reporte">
	<div class="row card-description">

	  	<div class="form-group col-md-4">
			<div class="row">
				<div class="col-md-12">
					<label>Fecha Factura<span class="text-danger">*</span></label>
                    @if(isset($empresa->cron_fecha_whatsapp) && $empresa->cron_fecha_whatsapp != null)
                    <input type="text" class="form-control" id="fecha" value="{{date('d-m-Y', strtotime($empresa->cron_fecha_whatsapp))}}" name="fecha" required="" >
                    @else
					<input type="text" class="form-control"  id="fecha" value="{{$request->fecha}}" name="fecha" required="" >
                    @endif
				</div>
			</div>
	  	</div>

	  	<div class="form-group col-md-8" style=" padding-top: 24px;">
        	<button type="button" id="generar" class="btn btn-outline-primary">Guardar Configuración</button>
            <button type="button" id="enviar-lote" class="btn btn-outline-info">Enviar lote de 45</button>
            <button type="button" id="reiniciar-lote" class="btn btn-outline-danger">Reiniciar envío de facturas</button>
            <div class="switch-container">
                <label class="switch">
                    <input type="checkbox" id="autoEnviarSwitch">
                    <span class="slider"></span>
                </label>
                <label for="autoEnviarSwitch" class="switch-label mb-0">Auto (5 min)</label>
                <span id="autoEnviarStatus" class="badge-status badge-apagado">Apagado</span>
            </div>
	  	</div>
	</div>

    <div class="card-description w-full pt-0 m-0">
            <div class="alert alert-info p-2 m-0" role="alert">
                <strong>Nota:</strong><br>
                <strong>></strong> reporte muestra las facturas que no han sido enviadas por WhatsApp creadas en la fecha seleccionada. <br>
                <strong>></strong> Las facturas se envían en lotes de 45 cada 15 minutos. <br>
                <strong>></strong> Si dejas configurada una fecha diferente al dia de hoy, el siguiente dia se reestablece la fecha al dia actual. <br>
                @if($totalFaltantes == 0)
                <strong>></strong> No hay facturas con fecha <strong> {{ $request->fecha }}</strong> pendientes de envío.<br>
                @else
                <strong>></strong> <strong>{{ $totalFaltantes }}</strong> facturas no se han enviado por whatsapp.<br>
                @endif
                @if($sinTelefono > 0)
                <strong>></strong> Hay un total de <strong> {{ $sinTelefono }}</strong> facturas sin número celular registrado.<br>
                @endif
                @if(Auth::user()->empresa()->cron_fecha_whatsapp != null)
                <strong>></strong> La fecha de envío configurada actualmente es: <strong>{{ date('d-m-Y', strtotime(Auth::user()->empresa()->cron_fecha_whatsapp)) }}</strong>
                @endif

        </div>
    </div>

    <input type="hidden" name="orderby"id="order_by"  value="2">
    <input type="hidden" name="order" id="order" value="1">
    <input type="hidden" id="form" value="form-reporte">

	<div class="row card-description">
		<div class="col-md-12 table-responsive">
			<table class="table table-striped table-hover " id="table-facturas">
			<thead class="thead-dark">
				<tr>
                    <th>Nro. Factura</th>
                    <th>¿Enviado?</th>
                    <th>Cliente <button type="button" class="btn btn-link no-padding orderby {{$request->orderby==1?'':'no_order'}}" campo="1" order="@if($request->orderby==1){{$request->order==1?'0':'1'}}@else 0 @endif" ><i class="fas fa-arrow-@if($request->orderby==1){{$request->order==0?'up':'down'}}@else{{'down'}} @endif"></i></button> </th>
					<th>Grupo Corte <button type="button" class="btn btn-link no-padding orderby {{$request->orderby==2?'':'no_order'}}" campo="2" order="@if($request->orderby==2){{$request->order==1?'0':'1'}}@else 0 @endif" ><i class="fas fa-arrow-@if($request->orderby==2){{$request->order==0?'up':'down'}}@else{{'down'}} @endif"></i></button></th>
					<th>Servidor <button type="button" class="btn btn-link no-padding orderby {{$request->orderby==3?'':'no_order'}}" campo="3" order="@if($request->orderby==3){{$request->order==1?'0':'1'}}@else 0 @endif" ><i class="fas fa-arrow-@if($request->orderby==3){{$request->order==0?'up':'down'}}@else{{'down'}} @endif"></i></button></th>
                    <th>Creación <button type="button" class="btn btn-link no-padding orderby {{$request->orderby==4?'':'no_order'}}" campo="4" order="@if($request->orderby==4){{$request->order==1?'0':'1'}}@else 0 @endif" ><i class="fas fa-arrow-@if($request->orderby==4){{$request->order==0?'up':'down'}}@else{{'down'}} @endif"></i></button></th>
                    <th>Vencimiento <button type="button" class="btn btn-link no-padding orderby {{$request->orderby==5?'':'no_order'}}" campo="5" order="@if($request->orderby==5){{$request->order==1?'0':'1'}}@else 0 @endif" ><i class="fas fa-arrow-@if($request->orderby==5){{$request->order==0?'up':'down'}}@else{{'down'}} @endif"></i></button></th>
	          </tr>
			</thead>
			<tbody>

				@foreach($facturas as $factura)
					<tr>
                        <td><a href="{{route('facturas.show',$factura->id)}}" target="_blank">{{$factura->codigo}}</a> </td>
                        <td>{{$factura->whatsapp == 1 ? 'Si' : 'No'}}</td>
                        <td><a href="{{route('contactos.show',$factura->cliente()->id)}}" target="_blank">{{$factura->cliente()->nombre}}  {{$factura->cliente()->apellidos()}} @if($factura->cliente()->celular) | {{$factura->cliente()->celular}}@endif</a></td>
						<td>{{$factura->grupoNombre ?? ''}}</td>
						<td>{{$factura->servidor()->nombre ?? ''}}</td>
                        <td>{{date('d-m-Y', strtotime($factura->fecha))}}</td>
                        <td>{{date('d-m-Y', strtotime($factura->vencimiento))}}</td>
					</tr>
				@endforeach
			</tbody>
		</table>
            {!! $facturas->render() !!}
	</div>
</div>
</form>
<input type="hidden" id="urlgenerar" value="{{route('cronjob.whatsapp-facturas-save')}}">
<input type="hidden" id="url-enviar-lote" value="{{route('cronjob.whatsapp-facturas-envio')}}">
<input type="hidden" id="url-reiniciar-lote" value="{{route('cronjob.whatsapp-facturas-reiniciar')}}">
@endsection

@section('scripts')
<script>
    $('#enviar-lote').on('click', function(e) {
        e.preventDefault();

        let url = $('#url-enviar-lote').val();
        $('#enviar-lote').prop('disabled', true).text('Enviando...');

        // Iniciar temporizador de 90 segundos
        const timeout = setTimeout(function() {
            // alert('La operación tardó demasiado. Recargando la página...');
            location.reload();
        }, 90000); // 90,000 milisegundos = 1 minuto 30 segundos

        $.ajax({
            url: url,
            type: 'GET',
            success: function(response) {
                clearTimeout(timeout); // Detener el timeout si responde a tiempo
                Swal.fire('Éxito', response.message || 'Los mensajes se enviaron correctamente.', 'success').then(() => {
                    location.reload();
                });
            },
            error: function(xhr, status, error) {
                clearTimeout(timeout); // Detener el timeout si hay error
                console.error(xhr.responseText);
                Swal.fire('Error', 'Ocurrió un error al enviar los mensajes.', 'error');
            },
            complete: function() {
                $('#enviar-lote').prop('disabled', false).text('Enviar lote de 45');
            }
        });
    });

    let autoEnviarInterval = null;
    let enviosAutomaticosCount = 0;

    $('#autoEnviarSwitch').on('change', function() {
        if ($(this).is(':checked')) {
            $('#autoEnviarStatus').removeClass('badge-apagado').addClass('badge-activo').text('Procesando');
            Swal.fire({
                title: 'Envío automático activado',
                text: 'Se enviarán lotes de 45 facturas cada 5 minutos mientras mantengas esta pestaña abierta.',
                type: 'info',
                timer: 4000,
                showConfirmButton: false
            });
            
            // Ejecutar la primera vez inmediatamente
            enviarLoteAutomatico();
            
            // Iniciar intervalo cada 5 minutos (300000 ms)
            autoEnviarInterval = setInterval(function() {
                enviarLoteAutomatico();
            }, 300000);
        } else {
            $('#autoEnviarStatus').removeClass('badge-activo').addClass('badge-apagado').text('Apagado');
            if (autoEnviarInterval) {
                clearInterval(autoEnviarInterval);
                autoEnviarInterval = null;
            }
            Swal.fire({
                title: 'Envío automático pausado',
                text: 'Se ha detenido el envío automático. Se realizaron ' + enviosAutomaticosCount + ' envíos.',
                type: 'warning',
                timer: 4000,
                showConfirmButton: false
            });
        }
    });

    function enviarLoteAutomatico() {
        let url = $('#url-enviar-lote').val();
        $('#enviar-lote').prop('disabled', true).text('Enviando (Automático)...');

        $.ajax({
            url: url,
            type: 'GET',
            success: function(response) {
                enviosAutomaticosCount++;
                console.log('Lote automático enviado con éxito (#'+enviosAutomaticosCount+'):', response.message || 'OK');
                // Actualizar la vista o tabla de forma dinámica para reflejar los envíos sin recargar la página.
                // Podríamos usar toastr si está disponible en el proyecto, o simplemente un pequeño aviso.
                const Toast = Swal.mixin({
                  toast: true,
                  position: 'top-end',
                  showConfirmButton: false,
                  timer: 3000
                });
                Toast.fire({
                  type: 'success',
                  title: 'Lote automático #' + enviosAutomaticosCount + ' enviado con éxito'
                });
            },
            error: function(xhr, status, error) {
                console.error('Error en lote automático:', xhr.responseText);
                const Toast = Swal.mixin({
                  toast: true,
                  position: 'top-end',
                  showConfirmButton: false,
                  timer: 3000
                });
                Toast.fire({
                  type: 'error',
                  title: 'Ocurrió un error en el envío automático'
                });
            },
            complete: function() {
                $('#enviar-lote').prop('disabled', false).text('Enviar lote de 45');
            }
        });
    }

    $('#reiniciar-lote').on('click', function() {
        Swal.fire({
        title: "¿Reiniciar envío de facturas?",
        text: "Las facturas ya enviadas el día " + $("#fecha").val() + " quedarán en estado 'No Enviadas'. (esto se hace con el fin de enviar de nuevo todas las facturas de una fecha especifica)",
        type: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Reiniciar',
    }).then((result) => {
        if (result.value) {
            $('#form-reporte').attr('action', $("#url-reiniciar-lote").val());
            $('#form-reporte').submit();
        }
    })
    });
</script>
@endsection