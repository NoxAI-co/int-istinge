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

    <div class="alert alert-info" role="alert">
        <h4 class="alert-heading"><i class="fas fa-info-circle"></i> Informe de Discrepancias en Morosos</h4>
        <p>Este reporte cruza la información obtenida directamente de la lista de morosos configurada en su Mikrotik con la base de datos del sistema.</p>
        <p class="mb-2">Si observa un cliente marcado como <span class="badge badge-success">PAGADA (Discrepancia)</span>, significa que su última factura en el sistema ya ha sido pagada, pero su IP sigue en la lista de morosos del Mikrotik. Esto puede deberse a una interrupción en la comunicación con el router al momento del pago o una sobrecarga momentánea. <strong>No es un error crítico</strong>, pero le sugerimos verificar el estado del servicio del cliente.</p>
        <hr>
        <h5 class="mb-2"><strong>Significado de los Estados del Sistema:</strong></h5>
        <ul class="mb-0">
            <li><span class="badge badge-success">PAGADA (Discrepancia)</span>: La última factura válida generada está <strong>pagada</strong>, pero el cliente sigue bloqueado en el Mikrotik.</li>
            <li><span class="badge badge-danger">En Mora</span>: La última factura válida generada no ha sido pagada (está abierta).</li>
            <li><span class="badge badge-dark"><i class="fas fa-user-slash"></i> Deshabilitado</span>: El contrato del cliente se encuentra <strong>Deshabilitado</strong> en el sistema (este estado se muestra junto al de la factura).</li>
            <li><span class="badge badge-warning">Anulada</span>: La última factura está anulada y no hay más facturas válidas anteriores (Raro). (<strong>Nota:</strong> El sistema ahora ignora facturas anuladas al buscar si el cliente está al día).</li>
            <li><span class="badge badge-secondary">Sin Facturas</span>: El cliente no tiene ninguna factura válida generada en el sistema.</li>
        </ul>
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
                <div class="col-md-6">
                    <button class="btn btn-primary btn-batch-sacar" title="Solucionar Discrepancias en Lote">
                        <i class="fas fa-magic"></i> Solucionar Discrepancias en Lote
                    </button>
                </div>
            </div>
        </div>
    </div>

	<div class="row card-description">
		<div class="col-md-12">
			<table class="table table-striped table-hover w-100" id="tabla-morosos">
				<thead class="thead-dark">
					<tr>
						<th>IP</th>
						<th>Cliente (Sistema)</th>
						<th>Comentario Mikrotik</th>
						<th>Estado Sistema</th>
						<th>Fecha Creación</th>
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
        // Initialize DataTable with empty data initially or wait for selection
		tabla = $('#tabla-morosos').DataTable({
			responsive: true,
			serverSide: false,
			processing: true,
			searching: true,
            "pageLength": 50,
			language: {
				'url': '{{asset("vendors/DataTables/es.json")}}'
			},
			order: [
				[4, "desc"]
			],
			ajax: {
                url: '{{ route("morosos.listar") }}',
                data: function (d) {
                    d.mikrotik_id = $('#mikrotik_id').val();
                },
                dataSrc: function (json) {
                    if (!json.success) {
                        return [];
                    }
                    return json.data;
                }
            },
			columns: [
				{data: 'ip'},
                {
                    data: 'contrato',
                    render: function(data, type, row) {
                        if (data) {
                            var url = showContratoUrl.replace('_id_', data.id);
                            return '<a href="'+url+'" target="_blank">' + data.nombre_cliente + ' (Contrato: ' + data.nro + ')</a>';
                        }
                        return '<span class="text-muted">No asociado</span>';
                    }
                },
				{
                    data: 'comentario',
                    render: function(data) {
                        return data ? data : '<span class="text-muted">N/A</span>';
                    }
                },
                {
                    data: 'estado_sistema',
                    render: function(data, type, row) {
                        let extraBadge = '';
                        if (row.contrato && row.contrato.state === 'disabled') {
                            extraBadge = ' <span class="badge badge-dark" data-toggle="tooltip" title="Contrato deshabilitado en el sistema"><i class="fas fa-user-slash"></i> Deshabilitado</span>';
                        }

                        if (row.tiene_discrepancia) {
                            return '<span class="badge badge-success" data-toggle="tooltip" title="' + row.mensaje_discrepancia + '">PAGADA (Discrepancia) <i class="fas fa-exclamation-triangle"></i></span>' + extraBadge;
                        } else if (data == 'En Mora') {
                            return '<span class="badge badge-danger">En Mora</span>' + extraBadge;
                        } else if (data == 'Sin Facturas') {
                            return '<span class="badge badge-secondary">Sin Facturas</span>' + extraBadge;
                        } else if (data == 'Anulada') {
                            return '<span class="badge badge-warning">Anulada</span>' + extraBadge;
                        } else {
                            return '<span class="badge badge-light">' + data + '</span>' + extraBadge;
                        }
                    }
                },
				{data: 'fecha_creacion'},
				{
					data: null,
					render: function(data, type, row) {
						let btn = '';
						if (row.contrato) {
							btn = '<button class="btn btn-outline-primary btn-sm btn-sacar" data-ip="'+row.ip+'" data-contrato="'+row.contrato.id+'" title="Sacar de Morosos"><i class="fas fa-check"></i> Sacar de Morosos</button>';
						} else {
							btn = '<button class="btn btn-outline-secondary btn-sm btn-sacar" data-ip="'+row.ip+'" data-contrato="" title="Sacar IP de Morosos"><i class="fas fa-eraser"></i> Sacar IP</button>';
						}
						return btn;
					}
				}
			]
		});

        /*$('#mikrotik_id').on('change', function() {
            tabla.ajax.reload();
        });*/

		$(document).on('click', '.btn-sacar', function() {
			var ip = $(this).data('ip');
			var contratoId = $(this).data('contrato');
			var mikrotikId = $('#mikrotik_id').val();

			swal({
				title: "¿Estás seguro?",
				text: "Se eliminará la IP " + ip + " de la lista de morosos en Mikrotik y se activará el contrato.",
				type: "warning",
				showCancelButton: true,
				confirmButtonColor: "#DD6B55",
				confirmButtonText: "Sí, sacar de morosos",
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
						url: '{{ route("morosos.sacar") }}',
						type: 'POST',
						data: {
							_token: '{{ csrf_token() }}',
							ip: ip,
							contrato_id: contratoId,
							mikrotik_id: mikrotikId
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

        $(document).on('click', '.btn-batch-sacar', function() {
            var mikrotikId = $('#mikrotik_id').val();
            var mikrotikNombre = $('#mikrotik_id option:selected').text();

            if (!mikrotikId) {
                swal("Error", "Por favor seleccione una Mikrotik", "error");
                return;
            }

			swal({
				title: "¿Estás seguro?",
				text: "Se buscarán todos los clientes con estado 'PAGADA (Discrepancia)' en " + mikrotikNombre + " y se procesarán automáticamente para sacarlos de morosos y activar sus contratos.",
				type: "warning",
				showCancelButton: true,
				confirmButtonColor: "#5cb85c",
				confirmButtonText: "Sí, procesar en lote",
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
						url: '{{ route("morosos.sacar.masivo") }}',
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

    // Check for Disabled Discrepancies
    function checkDisabledDiscrepancy(callback = null) {
        var mikrotikId = $('#mikrotik_id').val();
        var mikrotikNombre = $('#mikrotik_id option:selected').text();
        
        if (!mikrotikId) {
            $('#div-alert-disabled').remove();
            if (callback) callback();
            return;
        }

        $.ajax({
            url: '{{ route("morosos.check.disabled") }}',
            type: 'GET',
            data: { mikrotik_id: mikrotikId },
            success: function(response) {
                $('#div-alert-disabled').remove();
                
                if (response.success && response.count > 0) {
                    var html = `
                        <div class="alert alert-warning mt-3" id="div-alert-disabled" role="alert">
                            <strong><i class="fas fa-exclamation-circle"></i> Atención:</strong> 
                            Hay <strong>${response.count}</strong> contratos deshabilitados del servidor <strong>${mikrotikNombre}</strong> que NO aparecen en la lista de morosos (IPs no bloqueadas).
                            <br><br>
                            <a href="{{ route('morosos.discrepancias.disabled') }}?mikrotik_id=${mikrotikId}" class="btn btn-warning btn-sm">
                                <i class="fas fa-eye"></i> Ver y Corregir estos ${response.count} contratos
                            </a>
                        </div>
                    `;
                    // Insert after the existing info alert
                    $('.alert-info').after(html);
                }
                if (callback) callback();
            },
            error: function() {
                if (callback) callback();
            }
        });
    }

        // Call check when Mikrotik changes
        $('#mikrotik_id').on('change', function() {
            cargando(true);
            var reloaded = false;
            var checked = false;

            function checkFinish() {
                if (reloaded && checked) {
                    cargando(false);
                }
            }

            tabla.ajax.reload(function() {
                reloaded = true;
                checkFinish();
            });

            checkDisabledDiscrepancy(function() {
                checked = true;
                checkFinish();
            });
        });

        // Initial check if one is selected
        if ($('#mikrotik_id').val()) {
            checkDisabledDiscrepancy();
        }
    });
</script>
@endsection
