@extends('layouts.app')

@section('content')
	<div class="card-body">
        <p>Esta opción permite actualizar contratos.</p>
        <h4>Tome en cuenta las siguientes reglas para cargar la data</h4>
        <ul>
            <li class="ml-3">
                <form action="{{ route('contratos.data_ejemplo') }}" method="post" class="d-inline-flex align-items-center">
                    @csrf
                    <label for="conexion" class="mr-2 mb-0"><strong>Seleccione tipo de conexión:</strong></label>
                    <select name="conexion" id="conexion" class="form-control form-control-sm mr-2" style="width: auto;" required>
                        <option value="1">PPPoE</option>
                        <option value="2">DHCP</option>
                        <option value="3">IP Estática</option>
                        <option value="4">VLAN</option>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-download"></i> Descargar Archivo Excel de Ejemplo
                    </button>
                </form>
                <small class="text-muted d-block mt-2">El archivo incluirá todos los contratos del tipo de conexión seleccionado con la estructura unificada</small>
            </li>

            <li class="ml-3">Verifique que el comienzo de la data sea a partir de la fila 4.</li>
            <li class="ml-3">La columna A debe contener el <b>nro contrato</b> para identificar el contrato a actualizar. Si el contrato no existe, se creará uno nuevo.</li>
            <li class="ml-3">Los campos obligatorios son <b>Nro Contrato, Identificacion, Servicio, Mikrotik, Plan Internet o Plan Television (al menos uno), Estado, IP, Conexion, Interfaz, Segmento, Grupo de Corte, Facturacion, Tecnologia</b>.</li>

            <li class="ml-3">Las mikrotik disponibles son los siguientes:
                <div class="col-md-6 my-2">
                    <div class="table-responsive">
                        <table class="table table-striped importar text-center" style="border: solid 2px {{Auth::user()->empresa()->color}} !important;">
                            <thead><tr style="background-color: {{Auth::user()->empresa()->color}} !important; color: #fff;"><th>Mikrotik</th></tr></thead>
                            <tbody>
                                @foreach($mikrotiks as $mikrotik)
                                <tr>
                                    <td>{{$mikrotik->nombre}}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </li>

            <li class="ml-3">Los planes de Internet disponibles son los siguientes:
                <div class="col-md-6 my-2">
                    <div class="table-responsive">
                        <table class="table table-striped importar text-center" style="border: solid 2px {{Auth::user()->empresa()->color}} !important;">
                            <thead><tr style="background-color: {{Auth::user()->empresa()->color}} !important; color: #fff;"><th>Planes de Internet</th></tr></thead>
                            <tbody>
                                @foreach($planes as $plan)
                                <tr>
                                    <td>{{$plan->name}}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </li>

            <li class="ml-3">Los planes de Televisión disponibles son los siguientes:
                <div class="col-md-6 my-2">
                    <div class="table-responsive">
                        <table class="table table-striped importar text-center" style="border: solid 2px {{Auth::user()->empresa()->color}} !important;">
                            <thead><tr style="background-color: {{Auth::user()->empresa()->color}} !important; color: #fff;"><th>Planes de Televisión</th></tr></thead>
                            <tbody>
                                @forelse($serviciosTV as $tv)
                                <tr>
                                    <td>{{$tv->producto}}</td>
                                </tr>
                                @empty
                                <tr><td class="text-muted">No hay planes de TV registrados</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </li>

            <li class="ml-3">Los estados disponibles son los siguientes:
                <div class="col-md-6 my-2">
                    <div class="table-responsive">
                        <table class="table table-striped importar text-center" style="border: solid 2px {{Auth::user()->empresa()->color}} !important;">
                            <thead><tr style="background-color: {{Auth::user()->empresa()->color}} !important; color: #fff;"><th>Estados</th></tr></thead>
                            <tbody><tr><td>Habilitado</td></tr><tr><td>Deshabilitado</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </li>

            <li class="ml-3">Los tipos de conexion disponibles son los siguientes:
                <div class="col-md-6 my-2">
                    <div class="table-responsive">
                        <table class="table table-striped importar text-center" style="border: solid 2px {{Auth::user()->empresa()->color}} !important;">
                            <thead><tr style="background-color: {{Auth::user()->empresa()->color}} !important; color: #fff;"><th>Conexion</th></tr></thead>
                            <tbody><tr><td>IP Estatica</td></tr><tr><td>PPPOE</td></tr><tr><td>DHCP</td></tr><tr><td>VLAN</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </li>

            <li class="ml-3">Los grupos de corte disponibles son los siguientes:
                <div class="col-md-6 my-2">
                    <div class="table-responsive">
                        <table class="table table-striped importar text-center" style="border: solid 2px {{Auth::user()->empresa()->color}} !important;">
                            <thead><tr style="background-color: {{Auth::user()->empresa()->color}} !important; color: #fff;"><th>Grupos de Corte</th></tr></thead>
                            <tbody>
                                @foreach($grupos as $grupo)
                                <tr>
                                    <td>{{$grupo->nombre}}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </li>

            <li class="ml-3">Los tipos de facturación disponibles son los siguientes:
                <div class="col-md-6 my-2">
                    <div class="table-responsive">
                        <table class="table table-striped importar text-center" style="border: solid 2px {{Auth::user()->empresa()->color}} !important;">
                            <thead><tr style="background-color: {{Auth::user()->empresa()->color}} !important; color: #fff;"><th>Facturacion</th></tr></thead>
                            <tbody><tr><td>Estandar</td></tr><tr><td>Electronica</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </li>

            <li class="ml-3">Los tipos de tecnología disponibles son los siguientes:
                <div class="col-md-6 my-2">
                    <div class="table-responsive">
                        <table class="table table-striped importar text-center" style="border: solid 2px {{Auth::user()->empresa()->color}} !important;">
                            <thead><tr style="background-color: {{Auth::user()->empresa()->color}} !important; color: #fff;"><th>Tecnología</th></tr></thead>
                            <tbody><tr><td>Fibra</td></tr><tr><td>Inalambrico</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </li>

            <li class="ml-3">No debe dejar linea por medio entre registros.</li>
            <li class="ml-3">El sistema comprobará si nro de identificación está registrado, de ser asi modificara el registro con los nuevos valores del documento Excel que se cargue.</li>
            <li class="ml-3">El archivo debe ser extensión <b>.xlsx</b></li>
        </ul>

        <div class="card mt-4">
            <div class="card-header" style="background-color: {{Auth::user()->empresa()->color}} !important; color: #fff;">
                <h5 class="mb-0"><i class="fas fa-file-upload"></i> Cargar Archivo Excel</h5>
            </div>
            <div class="card-body">
                <form id="importForm" method="POST" action="{{ route('contratos.importar_cargando') }}" role="form" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="form-group col-md-8 offset-md-2">
                            <label class="control-label"><i class="fas fa-file-excel"></i> Seleccione el archivo Excel <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="archivo" name="archivo" required accept=".xlsx, .XLSX">
                                    <label class="custom-file-label" for="archivo">Seleccionar archivo...</label>
                                </div>
                            </div>
                            <small class="form-text text-muted">Solo se aceptan archivos con extensión .xlsx</small>
                            @if($errors->has('archivo'))
                                <span class="help-block text-danger">
                                    <strong>{{ $errors->first('archivo') }}</strong>
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            @if(count($errors) > 0)
                            <div class="alert alert-danger">
                                <h6><i class="fas fa-exclamation-triangle"></i> Errores encontrados:</h6>
                                <ul class="mb-0">
                                    @foreach($errors->all() as $error)
                                    <li>{!! $error !!}</li>
                                    @endforeach
                                </ul>
                            </div>
                            @endif
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-12 text-right">
                            <a href="{{route('contratos.index')}}" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-success" id="submitBtn">
                                <i class="fas fa-upload"></i> Cargar Archivo
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Overlay de Configuración de Auto-Relleno -->
    @if(session('validaciones_importacion'))
    <div id="autofillOverlay" style="display: flex; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); z-index: 10000; justify-content: center; align-items: center;">
        <div style="background: #fff; border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.3); max-width: 550px; width: 90%; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden;">
            <div style="background-color: {{Auth::user()->empresa()->color}}; color: #fff; padding: 15px 20px;">
                <h5 style="margin: 0; font-size: 1.1rem;">
                    <i class="fas fa-exclamation-triangle"></i> Configuración de Importación
                </h5>
            </div>
            <div style="padding: 20px; overflow-y: auto; flex-grow: 1;">
                <p style="margin-bottom: 15px;">Se encontraron registros con campos vacíos que pueden ser auto-rellenados. Configure las opciones:</p>

                @if(isset(session('validaciones_importacion')['ip_vacias']))
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 15px; margin-bottom: 10px; border: 1px solid #ffc107; border-radius: 8px; background-color: #fff3cd;">
                    <div style="flex: 1;">
                        <strong><i class="fas fa-network-wired"></i> IP vacía</strong>
                        <p style="margin: 5px 0 0 0; color: #6c757d; font-size: 0.85rem;">
                            Se encontraron <strong>{{ session('validaciones_importacion')['ip_vacias'] }}</strong> registro(s) sin IP.
                            <br>Si activa esta opción, se asignará la IP <code>000.000.0.00</code> por defecto.
                        </p>
                    </div>
                    <div style="margin-left: 15px;">
                        <label class="autofill-toggle" style="position: relative; display: inline-block; width: 50px; height: 26px; margin: 0;">
                            <input type="checkbox" class="autofill-switch" id="switchAutofillIp" data-field="autofill_ip" checked style="opacity: 0; width: 0; height: 0;">
                            <span class="autofill-slider"></span>
                        </label>
                    </div>
                </div>
                @endif

                <!-- Switch: Estado vacío -->
                @if(isset(session('validaciones_importacion')['estados_vacios']))
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 15px; margin-bottom: 10px; border: 1px solid #ffc107; border-radius: 8px; background-color: #fff3cd;">
                    <div style="flex: 1;">
                        <strong><i class="fas fa-toggle-on"></i> Estado obligatorio</strong>
                        <p style="margin: 5px 0 0 0; color: #6c757d; font-size: 0.85rem;">
                            Se encontraron <strong>{{ session('validaciones_importacion')['estados_vacios'] }}</strong> registro(s) sin Estado.
                            <br>Si activa esta opción, puede elegir el estado por defecto.
                        </p>
                        <div id="stateValueContainer" style="margin-top: 10px;">
                            <select id="selectAutofillStateValue" class="form-control form-control-sm" style="width: 150px;">
                                <option value="habilitado">Habilitado</option>
                                <option value="deshabilitado">Deshabilitado</option>
                            </select>
                        </div>
                    </div>
                    <div style="margin-left: 15px;">
                        <label class="autofill-toggle" style="position: relative; display: inline-block; width: 50px; height: 26px; margin: 0;">
                            <input type="checkbox" class="autofill-switch" id="switchAutofillState" data-field="autofill_state" checked style="opacity: 0; width: 0; height: 0;" onchange="document.getElementById('stateValueContainer').style.display = this.checked ? 'block' : 'none'">
                            <span class="autofill-slider"></span>
                        </label>
                    </div>
                </div>
                @endif

                <!-- Switch: Facturación vacía -->
                @if(isset(session('validaciones_importacion')['facturacion_vacias']))
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 15px; margin-bottom: 10px; border: 1px solid #ffc107; border-radius: 8px; background-color: #fff3cd;">
                    <div style="flex: 1;">
                        <strong><i class="fas fa-file-invoice-dollar"></i> Facturación obligatoria</strong>
                        <p style="margin: 5px 0 0 0; color: #6c757d; font-size: 0.85rem;">
                            Se encontraron <strong>{{ session('validaciones_importacion')['facturacion_vacias'] }}</strong> registro(s) sin Facturación.
                            <br>Si activa esta opción, puede elegir el tipo por defecto.
                        </p>
                        <div id="facturacionValueContainer" style="margin-top: 10px;">
                            <select id="selectAutofillFacturacionValue" class="form-control form-control-sm" style="width: 150px;">
                                <option value="estandar">Estandar</option>
                                <option value="electronica">Electronica</option>
                            </select>
                        </div>
                    </div>
                    <div style="margin-left: 15px;">
                        <label class="autofill-toggle" style="position: relative; display: inline-block; width: 50px; height: 26px; margin: 0;">
                            <input type="checkbox" class="autofill-switch" id="switchAutofillFacturacion" data-field="autofill_facturacion" checked style="opacity: 0; width: 0; height: 0;" onchange="document.getElementById('facturacionValueContainer').style.display = this.checked ? 'block' : 'none'">
                            <span class="autofill-slider"></span>
                        </label>
                    </div>
                </div>
                @endif

                @if(count($errors) > 0)
                <div style="margin-top: 15px; padding: 10px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 6px; font-size: 0.85rem;">
                    <strong><i class="fas fa-info-circle"></i> Nota:</strong> También se encontraron errores que requieren corrección manual en el archivo Excel. Estos se mostrarán después de continuar.
                </div>
                @endif
            </div>
            <div style="padding: 15px 20px; border-top: 1px solid #dee2e6; text-align: right; background: #f8f9fa;">
                <button type="button" onclick="document.getElementById('autofillOverlay').style.display='none'" class="btn btn-outline-secondary" style="margin-right: 8px;">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="button" onclick="submitAutofillForm()" class="btn btn-success">
                    <i class="fas fa-check"></i> Continuar Importación
                </button>
            </div>
        </div>
    </div>
    @endif

    <!-- Form oculto para re-enviar con opciones de auto-relleno -->
    <form id="autofillForm" method="POST" action="{{ route('contratos.importar_cargando') }}" style="display: none;">
        @csrf
        <input type="hidden" name="archivo_guardado" value="{{ session('archivo_guardado', '') }}">
    </form>

    <!-- Preloader con progreso -->
    <div id="preloader" style="display: none;">
        <div class="preloader-overlay">
            <div class="preloader-content">
                <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="sr-only">Cargando...</span>
                </div>
                <h5>Procesando archivo...</h5>
                <div class="progress mt-3" style="width: 300px; height: 25px;">
                    <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%">
                        <span id="progressText">0%</span>
                    </div>
                </div>
                <p class="mt-3 mb-0" id="progressMessage">Iniciando procesamiento...</p>
            </div>
        </div>
    </div>

    <style>
        .preloader-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            display: flex; justify-content: center; align-items: center;
        }
        .preloader-content {
            background-color: #fff; padding: 30px; border-radius: 10px;
            text-align: center; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .progress-bar { font-weight: bold; color: #fff; }
        .autofill-toggle input:checked + .autofill-slider { background-color: #28a745; }
        .autofill-toggle input:checked + .autofill-slider:before { transform: translateX(24px); }
        .autofill-slider {
            position: absolute; cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #ccc; transition: 0.3s; border-radius: 26px;
        }
        .autofill-slider:before {
            position: absolute; content: "";
            height: 20px; width: 20px; left: 3px; bottom: 3px;
            background-color: white; transition: 0.3s; border-radius: 50%;
        }
    </style>

    <script>
        document.getElementById('archivo').addEventListener('change', function(e) {
            var fileName = e.target.files[0].name;
            var nextSibling = e.target.nextElementSibling;
            nextSibling.innerText = fileName;
        });

        document.getElementById('importForm').addEventListener('submit', function(e) {
            var preloader = document.getElementById('preloader');
            var progressBar = document.getElementById('progressBar');
            var progressText = document.getElementById('progressText');
            var progressMessage = document.getElementById('progressMessage');
            var submitBtn = document.getElementById('submitBtn');

            preloader.style.display = 'block';
            submitBtn.disabled = true;

            var progress = 0;
            setInterval(function() {
                progress += Math.random() * 15;
                if (progress > 90) progress = 90;
                progressBar.style.width = progress + '%';
                progressText.textContent = Math.round(progress) + '%';
                if (progress < 30) progressMessage.textContent = 'Leyendo archivo...';
                else if (progress < 60) progressMessage.textContent = 'Validando datos...';
                else if (progress < 90) progressMessage.textContent = 'Procesando registros...';
            }, 300);
        });

        function submitAutofillForm() {
            var form = document.getElementById('autofillForm');
            var switches = document.querySelectorAll('.autofill-switch');
            switches.forEach(function(sw) {
                var fieldName = sw.getAttribute('data-field');
                var value = sw.checked ? 1 : 0;
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = fieldName;
                input.value = value;
                form.appendChild(input);
            });

            // Si el switch de estado está marcado, recoger el valor seleccionado
            var switchState = document.getElementById('switchAutofillState');
            if (switchState && switchState.checked) {
                var stateVal = document.getElementById('selectAutofillStateValue').value;
                var inputVal = document.createElement('input');
                inputVal.type = 'hidden';
                inputVal.name = 'autofill_state_value';
                inputVal.value = stateVal;
                form.appendChild(inputVal);
            }

            // Si el switch de facturacion está marcado, recoger el valor seleccionado
            var switchFact = document.getElementById('switchAutofillFacturacion');
            if (switchFact && switchFact.checked) {
                var factVal = document.getElementById('selectAutofillFacturacionValue').value;
                var inputValF = document.createElement('input');
                inputValF.type = 'hidden';
                inputValF.name = 'autofill_facturacion_value';
                inputValF.value = factVal;
                form.appendChild(inputValF);
            }

            var overlay = document.getElementById('autofillOverlay');
            if (overlay) overlay.style.display = 'none';
            document.getElementById('preloader').style.display = 'block';
            form.submit();
        }
    </script>
@endsection
