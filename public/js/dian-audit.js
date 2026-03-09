$(document).ready(function () {
    // Note: DataTables initialization is handled within the session view to pass the sessionId
});

function abrirModalCorreccion(recordId) {
    $.get(buildRoute('auditoria.facturas.corregir', recordId), function (data) {
        const record = data.record;
        $('#record_id_input').val(record.id);
        $('#folio_modal').text(record.folio_completo || 'N/A');

        // Populate DIAN data
        $('#nit_dian_modal').text(record.nit_receptor_dian);
        $('#nombre_dian_modal').text(record.nombre_receptor_dian);
        $('#fecha_dian_modal').text(record.fecha_emision || 'N/A');

        // Populate System data
        $('#nit_sistema_modal').text(record.nit_receptor_sistema || 'N/A');
        $('#nombre_sistema_modal').text(record.nombre_receptor_sistema || 'N/A');

        // Clear previous selection
        $('#cliente_id_nuevo').val(null).trigger('change');
        $('#contratos_container').hide();
        $('#contratos_list').empty();
        $('#contrato_id_nuevo').val('');
        $('#motivo_correccion').val('');

        $('#modalCorreccion').modal('show');
    });
}

function aplicarCorreccion() {
    const recordId = $('#record_id_input').val();
    const clienteId = $('#cliente_id_nuevo').val();
    const contratoId = $('#contrato_id_nuevo').val();
    const motivo = $('#motivo_correccion').val();

    if (!clienteId) {
        Swal.fire('Atención', 'Por favor seleccione el cliente correcto.', 'warning');
        return;
    }

    if (!contratoId && $('#contratos_container').is(':visible')) {
        // Si hay contratos visibles pero no seleccionó nada
        if ($('#contratos_list .contract-card').length > 0) {
            Swal.fire('Atención', 'Por favor seleccione un contrato para vincular la factura.', 'warning');
            return;
        }
    }


    Swal.fire({
        title: '¿Está seguro?',
        text: 'Esta acción actualizará la factura y su vinculación contractual de forma permanente.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, aplicar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: buildRoute('auditoria.facturas.aplicar', recordId),
                method: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    cliente_id_nuevo: clienteId,
                    contrato_id: contratoId,
                    motivo: motivo
                },
                success: function (response) {
                    if (response.success) {
                        $('#modalCorreccion').modal('hide');
                        if (window.dianTable) {
                            window.dianTable.ajax.reload(null, false);
                        }

                        // Update real-time stats
                        if (response.session_stats) {
                            $('#session_corrected').text(response.session_stats.corrected);
                            $('#session_discrepancies').text(response.session_stats.discrepancies);
                            $('#session_percentage').text(response.session_stats.percentage + '%');
                            $('#session_progress_bar').css('width', response.session_stats.percentage + '%');
                        }

                        Swal.fire('Éxito', response.message, 'success');
                    }
                },
                error: function (xhr) {
                    const error = xhr.responseJSON ? xhr.responseJSON.message : 'Error desconocido';
                    Swal.fire('Error', error, 'error');
                }
            });
        }
    });
}

// Helper to build routes (assuming a global object or simple replacement)
function buildRoute(name, id) {
    // Usar la base URL dinámica definida en el blade, o fallback a la raíz
    const baseUrl = window.auditBaseUrl || '/auditoria/facturas';
    if (name === 'auditoria.facturas.corregir') return `${baseUrl}/corregir/${id}`;
    if (name === 'auditoria.facturas.aplicar') return `${baseUrl}/aplicar/${id}`;
    if (name === 'auditoria.facturas.buscar-cliente') return `${baseUrl}/buscar-cliente`;
    if (name === 'auditoria.facturas.contratos-cliente') return `${baseUrl}/contratos-cliente`;
    return baseUrl;
}

// Select2 or autocomplete for searching clients
function initClienteSearch() {
    $('#cliente_id_nuevo').select2({
        placeholder: "Buscar por NIT o Nombre...",
        minimumInputLength: 3,
        dropdownParent: $('#modalCorreccion'), // Fix for Bootstrap Modals
        ajax: {
            url: buildRoute('auditoria.facturas.buscar-cliente'),
            dataType: 'json',
            delay: 300,
            data: function (params) {
                return { q: params.term };
            },
            processResults: function (data) {
                return { results: data };
            },
            cache: true
        }
    }).on('select2:select', function (e) {
        const clienteId = e.params.data.id;
        loadContratos(clienteId);
    });
}

function loadContratos(clienteId) {
    const recordId = $('#record_id_input').val();
    const $container = $('#contratos_container');
    const $list = $('#contratos_list');

    $list.html('<div class="col-12 text-center p-3"><i class="fas fa-spinner fa-spin mr-2"></i> Cargando contratos...</div>');
    $container.fadeIn();

    $.get(buildRoute('auditoria.facturas.contratos-cliente'), {
        cliente_id: clienteId,
        record_id: recordId
    }, function (contratos) {
        $list.empty();
        if (contratos.length === 0) {
            $list.html('<div class="col-12 alert alert-info m-2">Este cliente no tiene contratos registrados.</div>');
            $('#contrato_id_nuevo').val('');
            return;
        }

        contratos.forEach(c => {
            const recomendadoClass = c.recomendado ? 'recommended' : '';
            const badge = c.recomendado ? '<div class="recommendation-badge"><i class="fas fa-star mr-1"></i> RECOMENDADO</div>' : '';

            let infoFacturaHtml = '';
            if (c.factura_mes) {
                const icon = c.factura_mes.emitida ? 'fa-check-circle text-success' : 'fa-times-circle text-danger';
                const label = c.factura_mes.emitida ? 'Electrónica' : 'No Electrónica';
                infoFacturaHtml = `
                    <div class="mt-2 pt-2 border-top small">
                        <i class="fas fa-file-invoice text-muted mr-1"></i> Factura: <b>${c.factura_mes.codigo}</b><br>
                        <i class="fas ${icon}"></i> <b>${label}</b> (${c.factura_mes.estatus})
                    </div>
                `;
            }

            const card = `
                <div class="contract-card ${recomendadoClass}" data-id="${c.id}">
                    ${badge}
                    <div class="font-weight-bold text-primary">CP-${c.nro}</div>
                    <div class="small text-muted"><b>Plan:</b> ${c.plan}</div>
                    <div class="small"><span class="badge badge-${c.status === 'Habilitado' ? 'success' : 'danger'}">${c.estado}</span></div>
                    ${infoFacturaHtml}
                </div>
            `;
            $list.append(card);
        });

        // Seleccionar automáticamente el recomendado
        const $recomendado = $list.find('.contract-card.recommended');
        if ($recomendado.length > 0) {
            $recomendado.first().addClass('selected');
            $('#contrato_id_nuevo').val($recomendado.first().data('id'));
        }
    });
}

// Event handler for selecting a contract card
$(document).on('click', '.contract-card', function () {
    $('.contract-card').removeClass('selected');
    $(this).addClass('selected');
    $('#contrato_id_nuevo').val($(this).data('id'));
});
