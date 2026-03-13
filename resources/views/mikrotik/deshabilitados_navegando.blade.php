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
        <h4 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Contratos Deshabilitados con Posible Acceso (Navegando)</h4>
        <p>Este reporte muestra los contratos que están en estado <strong>deshabilitado</strong> pero cuyas IPs <strong>NO aparecen</strong> en la lista de morosos de la Mikrotik seleccionada.</p>
        <p class="mb-0">Esto implica que el cliente podría seguir teniendo acceso a internet. Puede utilizar esta herramienta para agregar las IPs faltantes a la lista de morosos, ya sea individualmente o marcando la Mikrotik completa.</p>
    </div>

    <div class="row card-description">
        <div class="col-md-12">
            <div class="form-group d-flex align-items-end">
                <div class="col-md-6">
                    <label for="mikrotik_id">Seleccione Mikrotik</label>
                    <select class="form-control selectpicker" id="mikrotik_id" name="mikrotik_id" data-live-search="true" title="Seleccione una opción">
                        @foreach($mikrotiks as $mikrotik)
                            <option value="{{ $mikrotik->id }}" {{ $loop->first ? 'selected' : '' }}>{{ $mikrotik->nombre }} - {{ $mikrotik->ip }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 text-right">
                    <button class="btn btn-danger btn-batch-fix" title="Procesar Todo el Lote (Agregar a Morosos)">
                        <i class="fas fa-tools"></i> Procesar Todo el Lote
                    </button>
                </div>
            </div>
        </div>
    </div>

	<div class="row card-description">
		<div class="col-md-12">
			<table class="table table-striped table-hover w-100" id="tabla-deshabilitados">
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
			</table>
		</div>
	</div>
@endsection

@section('scripts')
<script>
    var tabla = null;
    var showContratoUrl = "{{ route('contratos.show', ['contrato' => '_id_']) }}";

    $(document).ready(function() {
        // Initialize DataTable
		tabla = $('#tabla-deshabilitados').DataTable({
			responsive: true,
			serverSide: false,
			processing: true,
			searching: true,
            "pageLength": 50,
			language: {
				'url': '{{asset("vendors/DataTables/es.json")}}'
			},
			ajax: {
                url: '{{ route("morosos.check.disabled") }}',
                data: function (d) {
                    d.mikrotik_id = $('#mikrotik_id').val();
                },
                dataSrc: function (json) {
                    if (!json.success || !json.data) {
                        return [];
                    }
                    return json.data;
                }
            },
			columns: [
				{
                    data: 'nro',
                    render: function(data, type, row) {
                        if (row.id) {
                            var url = showContratoUrl.replace('_id_', row.id);
                            return '<a href="'+url+'" target="_blank">' + data + '</a>';
                        }
                        return data;
                    }
                },
				{data: 'cliente_nombre'},
                {data: 'apellido1'},
                {data: 'ip'},
                {
                    data: null,
                    render: function(data, type, row) {
                        return '<span class="badge badge-danger">No Listado</span>';
                    }
                },
				{
					data: null,
					render: function(data, type, row) {
						if (row.id) {
							return '<button class="btn btn-outline-danger btn-sm btn-fix" data-ip="'+row.ip+'" data-contrato="'+row.id+'" title="Agregar a Morosos"><i class="fas fa-lock"></i> Agregar a Morosos</button>';
						}
						return '';
					}
				}
			]
		});

        $('#mikrotik_id').on('change', function() {
            tabla.ajax.reload();
        });

		$(document).on('click', '.btn-fix', function() {
			var ip = $(this).data('ip');
			var contratoId = $(this).data('contrato');
			var mikrotikId = $('#mikrotik_id').val();

            if (!mikrotikId) {
                swal("Error", "Por favor seleccione una Mikrotik", "error");
                return;
            }

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
								swal("¡Éxito!", response.message, "success");
								tabla.ajax.reload();
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

        $(document).on('click', '.btn-batch-fix', function() {
            var mikrotikId = $('#mikrotik_id').val();
            var mikrotikNombre = $('#mikrotik_id option:selected').text();

            if (!mikrotikId) {
                swal("Error", "Por favor seleccione una Mikrotik", "error");
                return;
            }

            var rowCount = tabla.data().count();
            if (rowCount === 0) {
                swal("Info", "No hay contratos para procesar en esta Mikrotik.", "info");
                return;
            }

			swal({
				title: "¿Procesar Lote?",
				text: "Se agregarán TODAS las IPs mostradas en la tabla (" + rowCount + " registros) a la lista de morosos de " + mikrotikNombre + ". Esto puede tardar unos momentos.",
				type: "warning",
				showCancelButton: true,
				confirmButtonColor: "#d9534f",
				confirmButtonText: "Sí, procesar todo",
				cancelButtonText: "Cancelar"
			}).then((result) => {
				if (result.value) {
					swal({
						title: 'Procesando...',
						text: 'Procesando el lote completo, por favor espere',
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
								swal("¡Éxito!", response.message, "success");
								tabla.ajax.reload();
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
