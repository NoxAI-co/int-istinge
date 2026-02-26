@extends('layouts.app')
@section('content')
	<style>
	    .card-title {
	        font-size: 1.5rem;
	        margin-bottom: 2rem;
	    }
	    .instrucciones {
	        background-color: #f8f9fa;
	        padding: 20px;
	        border-radius: 5px;
	        margin-bottom: 20px;
	    }
	    .instrucciones ul {
	        padding-left: 20px;
	    }
	</style>

    <form method="POST" action="{{ route('saldos_iniciales.importar_cargando') }}" role="form" enctype="multipart/form-data">
        @csrf
        <div class="row card-description">
            <div class="col-md-4 offset-md-4">
                <div class="form-group row">
                    <label class="col-sm-12 col-form-label text-center">Seleccione el archivo por favor <span class="text-danger">*</span></label>
                    <div class="col-sm-12">
                        <input type="file" class="dropify" accept=".xlsx" name="archivo" id="archivo" required>
                        <span class="help-block error">
                            <strong>{{ $errors->first('archivo') }}</strong>
                        </span>
                    </div>
                </div>

                <div class="form-group row mt-4">
                    <div class="col-sm-12 text-center">
                        <label for="usar_fechas_corte" style="font-weight: bold; cursor:pointer;" class="mb-2">
                            ¿Desea crear la factura que va a importar relacionada con las fechas del grupo de corte que tenga el cliente registrado en el contrato?
                        </label>
                        <div class="custom-control custom-switch mt-2">
                            <input type="checkbox" class="custom-control-input" id="usar_fechas_corte" name="usar_fechas_corte" value="1">
                            <label class="custom-control-label" for="usar_fechas_corte">Sí / No</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-12 text-center mt-3">
                <hr>
                <button type="submit" class="btn btn-success">Importar Saldos Iniciales</button>
                <a href="{{route('configuracion.index')}}" class="btn btn-outline-light">Cancelar</a>
            </div>
        </div>
    </form>

    <div class="row mt-5">
        <div class="col-md-12">
            <div class="instrucciones">
                <h4 class="mb-3">Instrucciones de Importación</h4>
                <p>Para importar saldos iniciales, por favor siga estas instrucciones:</p>
                <ul>
                    <li>Descargue la plantilla de importación haciendo clic en el siguiente botón.</li>
                    <li>Complete la plantilla con la información solicitada. Los campos con (*) son obligatorios.</li>
                    <li><strong>Identificación:</strong> NIT o Cédula del cliente ya registrado en el sistema.</li>
                    <li><strong>Tipo factura:</strong> Seleccione "Estandar" o "Electronica" según corresponda.</li>
                    <li><strong>Fechas:</strong> Formato dd/mm/aaaa. Si selecciona la opción de arriba (usar fechas del grupo de corte), los campos de fecha de su excel serán ignorados.</li>
                    <li>Guarde el archivo y súbalo en este formulario.</li>
                </ul>
                
                <form action="{{ route('saldos_iniciales.ejemplo') }}" method="POST" class="mt-3">
                    @csrf
                    <button type="submit" class="btn btn-warning"><i class="fas fa-file-excel"></i> Descargar Plantilla</button>
                </form>
            </div>
        </div>
    </div>
@endsection
