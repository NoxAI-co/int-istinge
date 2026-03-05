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
			}, 5000);
		</script>
	@endif

    <div class="alert alert-warning" role="alert">
        <h4 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Contratos Deshabilitados sin Bloqueo</h4>
        <p>A continuación se listan los contratos que están marcados como <strong>DESHABILITADOS</strong> en el sistema, pero su IP <strong>NO aparece</strong> en la lista de morosos del Mikrotik <strong>{{ $mikrotik->nombre }}</strong>.</p>
        <p class="mb-0">Esto significa que estos clientes podrían tener servicio de internet a pesar de estar cortados en el sistema.</p>
    </div>

    <div class="row card-description">
        <div class="col-md-12">
            <div class="form-group d-flex justify-content-between align-items-center">
                <a href="{{ route('morosos.index') }}" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver a Morosos</a>
                
                @if(count($discrepancias) > 0)
                <button class="btn btn-danger btn-batch-fix" data-mikrotik="{{ $mikrotik->id }}">
                    <i class="fas fa-tools"></i> Procesar Todo el Lote (Agregar a Morosos)
                </button>
                @endif
            </div>
        </div>
    </div>

	<div class="row card-description">
		<div class="col-md-12">
			<table class="table table-striped table-hover w-100" id="tabla-discrepancias">
				<thead class="thead-dark">
					<tr>
						<th>Contrato Nro</th>
						<th>Cliente</th>
                        <th>Apellido</th>
						<th>IP</th>
                        <th>Estado Mikrotik</th>
						<th>Acciones</th>
					</tr>
				</thead>
                <tbody>
                    @foreach($discrepancias as $item)
                    <tr>
                        <td><a href="{{ route('contratos.show', $item['id']) }}" target="_blank">{{ $item['nro'] }}</a></td>
                        <td>{{ $item['cliente_nombre'] }}</td>
                        <td>{{ $item['apellido1'] }}</td>
                        <td>{{ $item['ip'] }}</td>
                        <td><span class="badge badge-danger">No Listado</span></td>
                        <td>
                            <button class="btn btn-outline-danger btn-sm btn-fix" 
                                data-contrato="{{ $item['id'] }}" 
                                data-ip="{{ $item['ip'] }}" 
                                data-mikrotik="{{ $mikrotik->id }}">
                                <i class="fas fa-lock"></i> Agregar a Morosos
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
			</table>
		</div>
	</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        $('#tabla-discrepancias').DataTable({
			responsive: true,
            "pageLength": 50,
			language: {
				'url': '{{asset("vendors/DataTables/es.json")}}'
			}
		});

        // Individual Fix
        $('.btn-fix').on('click', function() {
            var btn = $(this);
            var contratoId = btn.data('contrato');
            var ip = btn.data('ip');
            var mikrotikId = btn.data('mikrotik');

            swal({
				title: "¿Agregar a Morosos?",
				text: "Se agregará la IP " + ip + " a la lista de morosos en el Mikrotik.",
				type: "warning",
				showCancelButton: true,
				confirmButtonColor: "#d9534f",
				confirmButtonText: "Sí, bloquear",
				cancelButtonText: "Cancelar"
			}).then((result) => {
                if (result.value) {
                    // Show loading
                    swal({
                        title: 'Procesando...',
                        text: 'Espere un momento por favor',
                        onOpen: () => {
                            swal.showLoading()
                        },
                        allowOutsideClick: false,
                        allowEscapeKey: false
                    });

                    $.ajax({
                        url: '{{ route("morosos.fix.disabled") }}',
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            mikrotik_id: mikrotikId,
                            contrato_id: contratoId,
                            ip: ip
                        },
                        success: function(response) {
                            if (response.success) {
                                swal("¡Éxito!", response.message, "success").then(() => {
                                    location.reload(); 
                                });
                            } else {
                                swal("Error", response.message, "error");
                            }
                        },
                        error: function() {
                            swal("Error", "Ocurrió un error al procesar la solicitud.", "error");
                        }
                    });
                }
			});
        });

        // Batch Fix
        $('.btn-batch-fix').on('click', function() {
            var mikrotikId = $(this).data('mikrotik');

            swal({
				title: "¿Procesar Lote?",
				text: "Se agregarán TODAS las IPs listadas a morosos en el Mikrotik. Esto puede tardar unos momentos.",
				type: "warning",
				showCancelButton: true,
				confirmButtonColor: "#d9534f",
				confirmButtonText: "Sí, procesar todo",
				cancelButtonText: "Cancelar"
			}).then((result) => {
                if (result.value) {
                     // Show loading
                     swal({
                        title: 'Procesando...',
                        text: 'Espere un momento por favor',
                        onOpen: () => {
                            swal.showLoading()
                        },
                        allowOutsideClick: false,
                        allowEscapeKey: false
                    });

                    $.ajax({
                        url: '{{ route("morosos.fix.disabled.batch") }}',
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            mikrotik_id: mikrotikId
                        },
                        success: function(response) {
                            if (response.success) {
                                swal("¡Éxito!", response.message, "success").then(() => {
                                    location.reload();
                                });
                            } else {
                                swal("Info", response.message, "info");
                            }
                        },
                        error: function() {
                            swal("Error", "Ocurrió un error al procesar el lote.", "error");
                        }
                    });
                }
			});
        });
    });
</script>
@endsection
