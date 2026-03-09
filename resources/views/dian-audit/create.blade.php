@extends('layouts.app')

@section('content')
<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .upload-container {
        padding: 40px 0;
    }

    .upload-card {
        border: none;
        border-radius: 25px;
        box-shadow: 0 15px 40px rgba(0,0,0,0.1);
        overflow: hidden;
    }

    .card-premium-header {
        background: var(--primary-gradient);
        color: white;
        padding: 30px;
        text-align: center;
    }

    .drop-zone {
        border: 2px dashed #e2e8f0;
        border-radius: 15px;
        padding: 40px;
        text-align: center;
        transition: all 0.3s ease;
        background: #f8fafc;
        cursor: pointer;
    }

    .drop-zone:hover, .drop-zone.dragover {
        border-color: #667eea;
        background: #f1f5f9;
    }

    .icon-upload {
        font-size: 3rem;
        color: #667eea;
        margin-bottom: 20px;
    }

    .form-premium .form-control {
        border-radius: 12px;
        padding: 12px 20px;
        height: auto;
        border: 1px solid #e2e8f0;
    }

    .btn-premium {
        background: var(--primary-gradient);
        border: none;
        padding: 12px 30px;
        border-radius: 12px;
        font-weight: 600;
        color: white;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }

    .btn-premium:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        color: white;
    }
</style>

<div class="container upload-container">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card upload-card">
                <div class="card-premium-header">
                    <h3 class="font-weight-bold mb-1">Cargar Reporte DIAN</h3>
                    <p class="mb-0 opacity-75">Sincronice sus archivos oficiales con el sistema</p>
                </div>

                <form action="{{ route('auditoria.facturas.upload') }}" method="POST" enctype="multipart/form-data" id="uploadForm" class="form-premium">
                    @csrf
                    <div class="card-body p-4">
                        <div class="mb-4">
                            <label class="font-weight-bold text-dark mb-2">Período Fiscal</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light border-right-0"><i class="fas fa-calendar-alt text-primary"></i></span>
                                </div>
                                <input type="text" name="periodo" class="form-control border-left-0" id="periodo" placeholder="Ej: Marzo 2026" required>
                            </div>
                            <small class="text-muted">Este nombre servirá para identificar la sesión en el historial.</small>
                        </div>

                        <div class="form-group mb-4">
                            <label class="font-weight-bold text-dark mb-2">Archivo del Portal DIAN</label>
                            <div class="drop-zone" id="dropZone">
                                <div class="icon-upload"><i class="fas fa-cloud-upload-alt"></i></div>
                                <h5 class="font-weight-bold text-dark">Click para seleccionar o arrastra aquí</h5>
                                <p class="text-muted small">Acepta XLSX, XLS o CSV (Máx. 20MB)</p>
                                <div id="fileNameDisplay" class="font-weight-bold text-primary mt-2"></div>
                                <input type="file" name="archivo" id="archivo" hidden required accept=".xlsx,.xls,.csv">
                            </div>
                        </div>

                        <div class="alert alert-light border mb-0 p-3" style="border-radius: 15px;">
                            <div class="d-flex align-items-center">
                                <div class="bg-warning-light p-2 rounded-circle mr-3">
                                    <i class="fas fa-lightbulb text-warning"></i>
                                </div>
                                <div class="small">
                                    <strong>Recomendación:</strong> Verifique que las columnas <b>CUFE</b>, <b>Folio</b> y <b>Receptor NIT</b> estén presentes en la primera hoja del archivo.
                                </div>
                            </div>
                        </div>

                        <div id="processingInfo" style="display: none;" class="text-center mt-4">
                            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                                <span class="sr-only">Procesando...</span>
                            </div>
                            <h5 class="mt-3 text-primary font-weight-bold">Analizando estructura y cruzando datos...</h5>
                            <p class="text-muted">Esto puede tardar unos segundos según el número de facturas.</p>
                        </div>
                    </div>

                    <div class="card-footer bg-white p-4 text-right border-top-0">
                        <a href="{{ route('auditoria.facturas') }}" class="btn btn-link text-muted mr-3">Cancelar</a>
                        <button type="submit" class="btn btn-premium" id="btnSubmit">
                            <i class="fas fa-rocket mr-2"></i> Iniciar Auditoría
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@section('scripts')
<script>
    $(document).ready(function () {
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('archivo');
        const fileNameDisplay = document.getElementById('fileNameDisplay');

        dropZone.addEventListener('click', () => fileInput.click());

        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                fileNameDisplay.textContent = 'Archivo seleccionado: ' + this.files[0].name;
            }
        });

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            if (e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                fileNameDisplay.textContent = 'Archivo arrastrado: ' + e.dataTransfer.files[0].name;
            }
        });

        $('#uploadForm').on('submit', function() {
            $('#btnSubmit').attr('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Procesando...');
            $('#processingInfo').fadeIn();
            $('.form-premium input, .drop-zone').css('opacity', '0.5');
        });
    });
</script>
@endsection
@endsection
