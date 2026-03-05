@extends('layouts.app')

@section('content')
	<div class="card-body">
        <p>Esta opción permite importar pagos a facturas de forma masiva desde un archivo Excel.</p>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Al descargar la plantilla, el sistema incluirá automáticamente todas las <b>facturas abiertas</b> con su identificación y código, además de un <b>monto a pagar tentativo</b> basado en el saldo pendiente actual de cada factura.
        </div>
        <h4>Tome en cuenta las siguientes reglas para cargar la data</h4>
        <ul>
            <li class="ml-3">
                <form action="{{ route('ingresos.ejemplo-importar') }}" method="post" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-download"></i> Descargar Archivo Excel de Ejemplo
                    </button>
                </form>
                <small class="text-muted ml-2">El archivo incluye los campos necesarios para la importación</small>
            </li>

            <li class="ml-3">Verifique que el comienzo de la data sea a partir de la fila 4.</li>
            <li class="ml-3">Los campos obligatorios son <b>Identificacion, codigo factura, monto a pagar, cuenta, metodo de pago, fecha, observaciones, forma de pago</b>.</li>
            <li class="ml-3">El campo <b>cuenta</b> debe ser el nombre exacto de la cuenta de banco destino.</li>
            <li class="ml-3">El campo <b>metodo de pago</b> debe ser el nombre exacto del método (ej: Efectivo, Consignacion, etc).</li>
            <li class="ml-3">El campo <b>forma de pago</b> debe ser una de las referencias o nombres de forma de pago.</li>
            <li class="ml-3">El formato de la <b>fecha</b> debe ser YYYY-MM-DD.</li>

            <li class="ml-3 mt-3">Las Cuentas / Bancos disponibles son las siguientes:
                <div class="col-md-6 my-2">
                    <div class="table-responsive">
                        <table class="table table-striped importar text-center" style="border: solid 2px {{Auth::user()->empresa()->color}} !important;">
                            <thead><tr style="background-color: {{Auth::user()->empresa()->color}} !important; color: #fff;"><th>Cuenta</th></tr></thead>
                            <tbody>
                                @foreach($bancos as $banco)
                                <tr>
                                    <td>{{$banco->nombre}}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </li>

            <li class="ml-3 mt-3">Los Métodos de Pago disponibles son los siguientes:
                <div class="col-md-6 my-2">
                    <div class="table-responsive">
                        <table class="table table-striped importar text-center" style="border: solid 2px {{Auth::user()->empresa()->color}} !important;">
                            <thead><tr style="background-color: {{Auth::user()->empresa()->color}} !important; color: #fff;"><th>Método de Pago</th></tr></thead>
                            <tbody>
                                @foreach($metodos as $metodo)
                                <tr>
                                    <td>{{$metodo->metodo}}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </li>

            <li class="ml-3 mt-3">Las Formas de Pago disponibles son las siguientes (Código Puc):
                <div class="col-md-6 my-2">
                    <div class="table-responsive">
                        <table class="table table-striped importar text-center" style="border: solid 2px {{Auth::user()->empresa()->color}} !important;">
                            <thead><tr style="background-color: {{Auth::user()->empresa()->color}} !important; color: #fff;"><th>Forma de Pago (Código - Nombre)</th></tr></thead>
                            <tbody>
                                @foreach($formas as $forma)
                                <tr>
                                    <td>{{$forma->codigo}} - {{$forma->nombre}}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </li>
        </ul>
    </div>

    <div class="row justify-content-center mt-3">
        <div class="col-md-10">
            <div class="card-body">
                <form action="{{route('ingresos.importar-cargando')}}" method="post" enctype="multipart/form-data" id="importForm">
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
                            @if(session('success'))
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> {{ session('success') }}
                            </div>
                            @endif
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
                            <a href="{{route('ingresos.index')}}" class="btn btn-outline-secondary">
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
    </style>

    <script>
        // Actualizar label del input file
        document.getElementById('archivo').addEventListener('change', function(e) {
            var fileName = e.target.files[0].name;
            var nextSibling = e.target.nextElementSibling;
            nextSibling.innerText = fileName;
        });

        // Manejar envío del formulario
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

            // Simular progreso
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
                    progressMessage.textContent = 'Importando pagos...';
                }
            }, 300);
        });
    </script>
@endsection
