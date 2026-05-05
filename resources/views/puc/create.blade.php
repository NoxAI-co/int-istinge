<form method="POST" action="{{ route('puc.store') }}" role="form" class="forms-sample" novalidate id="form-categoria-store">
    <div class="modal-header">
        <h5 class="modal-title" id="modal-small-CenterTitle">Nueva categoría</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <div class="modal-body">
        <div class="row">
            <div class="col-md-6 form-group">
                <label class="control-label">Asociada</label>
                <input type="text" class="form-control" disabled value="{{$categoria->nombre}} - {{$categoria->codigo}}">
            </div>
            <div class="col-md-6 form-group">
                <label class="control-label">Nombre <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="nombre" name="nombre" required value="{{old('nombre')}}" maxlength="200">
                <span class="help-block error">
                    <strong>{{ $errors->first('nombre') }}</strong>
                </span>
            </div>
        </div>

        <input type="hidden" name="asociado" value="{{$categoria->nro}}">
        {{ csrf_field() }}

        <div class="row">
            <div class="col-md-6 form-group">
                <label class="control-label">Código</label>
                <input type="text" class="form-control" id="codigo" name="codigo" value="{{old('codigo')}}" maxlength="50">
                <span class="help-block error">
                    <strong>{{ $errors->first('codigo') }}</strong>
                </span>
            </div>
            <div class="col-md-6 form-group">
                <label class="control-label">Descripción</label>
                <textarea class="form-control form-control-sm" name="descripcion">{{old('descripcion')}}</textarea>
                <span class="help-block error">
                    <strong>{{ $errors->first('descripcion') }}</strong>
                </span>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 form-group">
                <label class="control-label">¿Tercero?<span class="text-danger">*</span></label>
                <select class="form-control" name="tercero" id="tercero" title="¿Tercero?">
                    <option value="">Seleccione</option>
                    <option value="1">Sí</option>
                    <option value="0">No</option>
                </select>
                <span class="help-block error">
                    <strong>{{ $errors->first('tercero') }}</strong>
                </span>
            </div>
            <div class="col-md-6 form-group">
                <label class="control-label">Grupo<span class="text-danger">*</span></label>
                <select class="form-control" name="grupo" id="grupo" title="Grupo">
                    <option value="0" readonly>Seleccione grupo</option>
                    @foreach($grupos as $grupo)
                        <option value="{{$grupo->id}}">{{$grupo->nombre}}</option>
                    @endforeach
                </select>
                <span class="help-block error">
                    <strong>{{ $errors->first('grupo') }}</strong>
                </span>
            </div>
        </div>
 
        <div class="row">
            <div class="col-md-6 form-group">
                <label class="control-label">Tipo<span class="text-danger">*</span></label>
                <select class="form-control" name="tipo" id="tipo" title="Tipo">
                    <option value="0" readonly>Seleccione tipo</option>
                    @foreach($tipos as $tipo)
                        <option value="{{$tipo->id}}">{{$tipo->nombre}}</option>
                    @endforeach
                </select>
                <span class="help-block error">
                    <strong>{{ $errors->first('tipo') }}</strong>
                </span>
            </div>
            <div class="col-md-6 form-group">
                <label class="control-label">Balance<span class="text-danger">*</span></label>
                <select class="form-control" name="balance" id="balance" required title="Balance">
                    <option value="0" readonly>Seleccione balance</option>
                    @foreach($balances as $balance)
                        <option value="{{$balance->id}}">{{$balance->nombre}}</option>
                    @endforeach
                </select>
                <span class="help-block error">
                    <strong>{{ $errors->first('balance') }}</strong>
                </span>
            </div>
        </div>

        <small>Los campos marcados con <span class="text-danger">*</span> son obligatorios</small>
    </div>

    <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
        {{-- quitamos onclick para evitar duplicar llamadas --}}
        <button type="button" class="btn btn-success" id="btnStore">Guardar</button>
    </div>
</form>


<script>
    /*
    Este script usa delegación de eventos para funcionar incluso
    cuando el modal se inserta por AJAX.
    Usa fetch() y muestra alert() (sin sweetalert).
    */

    (function() {
        // refrescar selectpicker si existe
        function refreshSelects() {
            if (typeof $ !== 'undefined') {
                try {
                    $("#tercero").selectpicker && $("#tercero").selectpicker('refresh');
                    $("#grupo").selectpicker && $("#grupo").selectpicker('refresh');
                    $("#tipo").selectpicker && $("#tipo").selectpicker('refresh');
                    $("#balance").selectpicker && $("#balance").selectpicker('refresh');
                } catch(e) { /* no crítico */ }
            }
        }

        // función para enviar via AJAX
        async function enviarFormularioAjax() {
            const form = document.getElementById('form-categoria-store');
            if (!form) return alert('Formulario no encontrado.');

            // limpiar errores visuales
            document.querySelectorAll('#form-categoria-store .is-invalid').forEach(el => el.classList.remove('is-invalid'));

            // validación cliente rápida
            const erroresCliente = [];
            const nombre = form.querySelector('[name="nombre"]');
            const tercero = form.querySelector('[name="tercero"]');
            const grupo = form.querySelector('[name="grupo"]');
            const tipo = form.querySelector('[name="tipo"]');
            const balance = form.querySelector('[name="balance"]');

            if (!nombre || !nombre.value.trim()) {
                erroresCliente.push('El campo Nombre es obligatorio.');
                nombre && nombre.classList.add('is-invalid');
            }
            if (!tercero || tercero.value === "") {
                erroresCliente.push('Debe seleccionar si es Tercero o no.');
                tercero && tercero.classList.add('is-invalid');
            }
            if (!grupo || grupo.value === "0") {
                erroresCliente.push('Debe seleccionar un Grupo.');
                grupo && grupo.classList.add('is-invalid');
            }
            if (!tipo || tipo.value === "0") {
                erroresCliente.push('Debe seleccionar un Tipo.');
                tipo && tipo.classList.add('is-invalid');
            }
            if (!balance || balance.value === "0") {
                erroresCliente.push('Debe seleccionar un Balance.');
                balance && balance.classList.add('is-invalid');
            }

            if (erroresCliente.length) {
                alert('Faltan campos obligatorios:\n\n' + erroresCliente.map(m => '- ' + m).join('\n'));
                return;
            }

            const url = form.action;
            const tokenMeta = document.querySelector('meta[name="csrf-token"]');
            const token = tokenMeta ? tokenMeta.getAttribute('content') : '';

            const formData = new FormData(form);

            try {
                const resp = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token
                    },
                    body: formData
                });

                if (resp.status === 201 || resp.ok) {
                    // éxito
                    let json = null;
                    try { json = await resp.json(); } catch(e){/*no JSON*/}

                    alert((json && json.message) ? json.message : 'Se ha creado satisfactoriamente la categoría.');
                    // cerrar modal y refrescar
                    if (typeof $ !== 'undefined') $('.modal').modal('hide');
                    window.location.reload();
                    return;
                }

                if (resp.status === 422 || resp.status === 409) {
                    let json = null;
                    try { json = await resp.json(); } catch(e){ json = null; }
                    const mensajes = [];

                    if (json && json.errors) {
                        for (const key in json.errors) {
                            const m = Array.isArray(json.errors[key]) ? json.errors[key].join(' ') : json.errors[key];
                            mensajes.push(m);
                            // marcar input
                            const input = form.querySelector('[name="' + key + '"]');
                            if (input) input.classList.add('is-invalid');
                        }
                    } else if (json && json.message) {
                        mensajes.push(json.message);
                    } else {
                        mensajes.push('Error al validar los datos.');
                    }

                    alert('Faltan campos o hay errores:\n\n' + mensajes.join('\n'));
                    return;
                }

                // otros errores
                const texto = await resp.text().catch(()=>null);
                console.error('Respuesta inesperada', resp.status, texto);
                alert('Ocurrió un error en el servidor. Código: ' + resp.status);
            } catch (err) {
                console.error(err);
                alert('No fue posible conectarse al servidor: ' + err.message);
            }
        }

        // delegación: escucha clicks a nivel documento para el botón #btnStore
        document.addEventListener('click', function(e) {
            const target = e.target;
            if (!target) return;
            // por si se hace click en el <i> o <span> dentro del botón:
            const btn = target.closest ? target.closest('#btnStore') : (target.id === 'btnStore' ? target : null);
            if (btn) {
                e.preventDefault();
                enviarFormularioAjax();
            }
        });

        // refrescar selects cuando el fragmento se inyecte
        // (si el modal se carga por AJAX, esto ayuda)
        /* document.addEventListener('DOMNodeInserted', function(e) {
            refreshSelects();
        }); */

        // intento inmediato de refresh (por si el create se carga directamente)
        setTimeout(refreshSelects, 100);

    })();
</script>

