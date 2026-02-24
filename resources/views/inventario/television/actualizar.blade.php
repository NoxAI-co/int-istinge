@extends('layouts.app')

@section('content')
	<div class="card-body">
        <p>Esta opción permite actualizar los productos de inventario de televisión existentes de forma masiva desde un archivo Excel. El archivo debe contener el <b>ID</b> del producto en la primera columna para poder identificarlo.</p>
        <h4>Tome en cuenta las siguientes reglas para cargar la data</h4>
        <ul>
            <li class="ml-3">
                <form action="{{ route('inventario.television.ejemplo-actualizar') }}" method="post" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-download"></i> Descargar Archivo Excel con Datos Actuales
                    </button>
                </form>
                <small class="text-muted ml-2">El archivo viene pre-llenado con los productos actuales de televisión</small>
            </li>

            <li class="ml-3">Verifique que el comienzo de la data sea a partir de la fila 4.</li>
            <li class="ml-3">La primera columna (<b>ID</b>) es obligatoria y <b>no debe modificarse</b>. Se usa para identificar el producto a actualizar.</li>
            <li class="ml-3">Los campos obligatorios son <b>ID, Nombre del Producto, Referencia, Impuesto (19 o 0), Precio de Venta, Tipo</b>.</li>
            <li class="ml-3">La <b>Referencia</b> no puede repetirse con la de otro producto.</li>
            <li class="ml-3">Los precios deben ir <b>sin puntos</b> ni separadores de miles.</li>
            <li class="ml-3">El campo <b>Impuesto</b> puede ser: <b>19</b> (IVA 19%) o <b>0</b> (sin impuesto).</li>
            <li class="ml-3">Los tipos disponibles son: <b>MATERIAL, MODEMS, HERRAMIENTA, OFICINA, TV, SERVICIO</b>.</li>
        </ul>
    </div>

    <div class="row justify-content-center mt-3">
        <div class="col-md-10">
            <div class="card-body">
                <form action="{{ route('inventario.television.actualizar-cargando') }}" method="post" enctype="multipart/form-data" id="importForm">
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
                            <a href="{{ route('inventario.television') }}" class="btn btn-outline-secondary">
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
            var interval = setInterval(function() {
                progress += Math.random() * 15;
                if (progress > 90) progress = 90;
                progressBar.style.width = progress + '%';
                progressText.textContent = Math.round(progress) + '%';

                if (progress < 30) {
                    progressMessage.textContent = 'Leyendo archivo...';
                } else if (progress < 60) {
                    progressMessage.textContent = 'Validando datos...';
                } else {
                    progressMessage.textContent = 'Actualizando productos de televisión...';
                }
            }, 300);
        });
    </script>
@endsection
