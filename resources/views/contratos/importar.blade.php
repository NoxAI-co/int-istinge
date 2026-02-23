@extends('layouts.app')

@section('content')
	<div class="card-body">
        <p>Esta opción permite crear nuevos contratos y/o modificarlos por el nro de identificación que posea el cliente que ya se encuentre registrado en el sistema.</p>
        <h4>Tome en cuenta las siguientes reglas para cargar la data</h4>
        <ul>
            <li class="ml-3">
                <form action="{{ route('contratos.ejemplo') }}" method="post" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-download"></i> Descargar Archivo Excel de Ejemplo
                    </button>
                </form>
                <small class="text-muted ml-2">El archivo incluye todos los campos unificados para cualquier tipo de conexión</small>
            </li>

            {{-- <li class="ml-3">Verifique que el orden de las columnas en su documento sea correcto. <small>Si no lo conoce haga clic <a href="{{ route('contratos.ejemplo') }}"><b>aqui</b></a> para descargar archivo Excel de ejemplo.</small></li> --}}
            <li class="ml-3">Verifique que el comienzo de la data sea a partir de la fila 4.</li>
            <li class="ml-3">Los campos obligatorios son <b>Identificacion, Servicio, Mikrotik, Plan, Estado, IP, Conexion, Interfaz, Segmento, Grupo de Corte, Facturacion, Tecnologia</b>.</li>

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

            <li class="ml-3">Los planes de velocidad disponibles son los siguientes:
                <div class="col-md-6 my-2">
                    <div class="table-responsive">
                        <table class="table table-striped importar text-center" style="border: solid 2px {{Auth::user()->empresa()->color}} !important;">
                            <thead><tr style="background-color: {{Auth::user()->empresa()->color}} !important; color: #fff;"><th>Planes</th></tr></thead>
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
                            <tbody><tr><td>Fibra</td></tr><tr><td>Inalambrico</td></tr><tr><td>Cableado UTP</td></tr></tbody>
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

    <!-- Modal de Configuración de Auto-Relleno -->
    <div class="modal fade" id="modalAutofill" tabindex="-1" role="dialog" aria-labelledby="modalAutofillLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header" style="background-color: {{Auth::user()->empresa()->color}}; color: #fff;">
                    <h5 class="modal-title" id="modalAutofillLabel">
                        <i class="fas fa-exclamation-triangle"></i> Configuración de Importación
                    </h5>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Se encontraron registros con campos vacíos que pueden ser auto-rellenados. Configure las opciones:</p>

                    <!-- Switch: IP vacía -->
                    @if(session('validaciones_importacion') && isset(session('validaciones_importacion')['ip_vacias']))
                    <div class="d-flex align-items-center justify-content-between p-3 mb-2 border rounded" style="background-color: #fff3cd;">
                        <div>
                            <strong><i class="fas fa-network-wired"></i> IP vacía</strong>
                            <p class="mb-0 text-muted small">
                                Se encontraron <strong>{{ session('validaciones_importacion')['ip_vacias'] }}</strong> registro(s) sin IP.
                                <br>Si activa esta opción, se asignará la IP <code>000.000.0.00</code> por defecto.
                            </p>
                        </div>
                        <div class="custom-control custom-switch ml-3">
                            <input type="checkbox" class="custom-control-input autofill-switch" id="switchAutofillIp" data-field="autofill_ip" checked>
                            <label class="custom-control-label" for="switchAutofillIp"></label>
                        </div>
                    </div>
                    @endif

                    {{-- EXTENSIBLE: Agregar más switches aquí para futuras validaciones solucionables --}}
                    {{-- Ejemplo:
                    @if(session('validaciones_importacion') && isset(session('validaciones_importacion')['otro_campo']))
                    <div class="d-flex align-items-center justify-content-between p-3 mb-2 border rounded" style="background-color: #fff3cd;">
                        <div>
                            <strong><i class="fas fa-icon"></i> Otro Campo</strong>
                            <p class="mb-0 text-muted small">Descripción...</p>
                        </div>
                        <div class="custom-control custom-switch ml-3">
                            <input type="checkbox" class="custom-control-input autofill-switch" id="switchAutofillOtro" data-field="autofill_otro">
                            <label class="custom-control-label" for="switchAutofillOtro"></label>
                        </div>
                    </div>
                    @endif
                    --}}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="btnCancelAutofill">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-success" id="btnConfirmAutofill">
                        <i class="fas fa-check"></i> Continuar Importación
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Form oculto para re-enviar con opciones de auto-relleno -->
    <form id="autofillForm" method="POST" action="{{ route('contratos.importar_cargando') }}" style="display: none;">
        @csrf
        <input type="hidden" name="archivo_guardado" id="archivoGuardado" value="{{ session('archivo_guardado', '') }}">
        <!-- Los hidden inputs de autofill se agregan dinámicamente -->
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
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .preloader-content {
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .progress-bar {
            font-weight: bold;
            color: #fff;
        }
        .custom-switch .custom-control-label::before {
            width: 2.5rem;
            height: 1.5rem;
            border-radius: 1rem;
        }
        .custom-switch .custom-control-label::after {
            width: 1.2rem;
            height: 1.2rem;
            border-radius: 50%;
        }
        .custom-switch .custom-control-input:checked ~ .custom-control-label::after {
            transform: translateX(1rem);
        }
    </style>

    <script>
        // Actualizar label del input file
        document.getElementById('archivo').addEventListener('change', function(e) {
            var fileName = e.target.files[0].name;
            var nextSibling = e.target.nextElementSibling;
            nextSibling.innerText = fileName;
        });

        // Manejar envío del formulario principal
        document.getElementById('importForm').addEventListener('submit', function(e) {
            var form = this;
            var preloader = document.getElementById('preloader');
            var progressBar = document.getElementById('progressBar');
            var progressText = document.getElementById('progressText');
            var progressMessage = document.getElementById('progressMessage');
            var submitBtn = document.getElementById('submitBtn');

            // Mostrar preloader
            preloader.style.display = 'block';
            submitBtn.disabled = true;

            // Simular progreso (ya que el procesamiento es síncrono)
            var progress = 0;
            var interval = setInterval(function() {
                progress += Math.random() * 15;
                if (progress > 90) {
                    progress = 90;
                }
                progressBar.style.width = progress + '%';
                progressText.textContent = Math.round(progress) + '%';
                
                if (progress < 30) {
                    progressMessage.textContent = 'Leyendo archivo...';
                } else if (progress < 60) {
                    progressMessage.textContent = 'Validando datos...';
                } else if (progress < 90) {
                    progressMessage.textContent = 'Procesando registros...';
                }
            }, 300);
        });

        // Mostrar modal si hay validaciones solucionables
        @if(session('validaciones_importacion'))
        $(document).ready(function() {
            $('#modalAutofill').modal('show');
        });
        @endif

        // Botón Cancelar del modal
        document.getElementById('btnCancelAutofill').addEventListener('click', function() {
            $('#modalAutofill').modal('hide');
        });

        // Botón Continuar del modal: re-enviar con opciones
        document.getElementById('btnConfirmAutofill').addEventListener('click', function() {
            var form = document.getElementById('autofillForm');
            
            // Recoger estado de cada switch y agregar como hidden input
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

            // Ocultar modal y mostrar preloader
            $('#modalAutofill').modal('hide');
            document.getElementById('preloader').style.display = 'block';

            // Enviar formulario
            form.submit();
        });
    </script>
@endsection
