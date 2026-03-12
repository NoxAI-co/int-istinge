@extends('layouts.app')
@section('content')
    @if(Session::has('success'))
        <div class="alert alert-success" >
            {{Session::get('success')}}
        </div>
    @endif

    @if(Session::has('danger'))
        <div class="alert alert-danger" >
            {{Session::get('danger')}}
        </div>
    @endif

    @if(count($errors) > 0 && !$errors->has('archivo'))
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

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
                        <div class="d-flex align-items-center justify-content-center mt-2">
                            <label class="switch mb-0">
                                <input type="hidden" name="usar_fechas_corte" value="0">
                                <input type="checkbox" name="usar_fechas_corte" id="usar_fechas_corte" value="1">
                                <span class="slider round"></span>
                            </label>
                            <span class="ml-2" id="usar_fechas_corte_label">No</span>
                        </div>
                    </div>
                </div>

                {{-- Selector de mes/año (visible solo cuando usar_fechas_corte está activo) --}}
                <div class="form-group row mt-3" id="mes_factura_container" style="display: none;">
                    <div class="col-sm-12 text-center">
                        <label style="font-weight: bold;" class="mb-2">
                            <i class="fas fa-calendar-alt text-primary mr-1"></i>
                            Seleccione el mes en que se creará la factura según el grupo de corte:
                        </label>
                        <div class="d-flex align-items-center justify-content-center mt-2 gap-2" style="gap: 10px;">
                            <select name="mes_factura" id="mes_factura" class="form-control" style="max-width: 180px;">
                                <option value="1"  {{ date('n') == 1  ? 'selected' : '' }}>Enero</option>
                                <option value="2"  {{ date('n') == 2  ? 'selected' : '' }}>Febrero</option>
                                <option value="3"  {{ date('n') == 3  ? 'selected' : '' }}>Marzo</option>
                                <option value="4"  {{ date('n') == 4  ? 'selected' : '' }}>Abril</option>
                                <option value="5"  {{ date('n') == 5  ? 'selected' : '' }}>Mayo</option>
                                <option value="6"  {{ date('n') == 6  ? 'selected' : '' }}>Junio</option>
                                <option value="7"  {{ date('n') == 7  ? 'selected' : '' }}>Julio</option>
                                <option value="8"  {{ date('n') == 8  ? 'selected' : '' }}>Agosto</option>
                                <option value="9"  {{ date('n') == 9  ? 'selected' : '' }}>Septiembre</option>
                                <option value="10" {{ date('n') == 10 ? 'selected' : '' }}>Octubre</option>
                                <option value="11" {{ date('n') == 11 ? 'selected' : '' }}>Noviembre</option>
                                <option value="12" {{ date('n') == 12 ? 'selected' : '' }}>Diciembre</option>
                            </select>
                            <select name="anio_factura" id="anio_factura" class="form-control" style="max-width: 110px;">
                                @for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++)
                                    <option value="{{ $y }}" {{ date('Y') == $y ? 'selected' : '' }}>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                        <small class="text-muted mt-1 d-block">
                            Las fechas de factura, vencimiento y suspensión se calcularán usando los días del grupo de corte en el mes seleccionado.
                        </small>
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

@section('scripts')
<script>
    $('#usar_fechas_corte').change(function() {
        if ($(this).is(':checked')) {
            $('#usar_fechas_corte_label').text('Si');
            $('#mes_factura_container').slideDown(300);
        } else {
            $('#usar_fechas_corte_label').text('No');
            $('#mes_factura_container').slideUp(300);
        }
    });
</script>
@endsection
