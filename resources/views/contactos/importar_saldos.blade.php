@extends('layouts.app')	

@section('content')
	<div class="card-body">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        @if(session('warning_persistent'))
            <div class="alert alert-warning" role="alert" style="border-left: 4px solid #ffc107;">
                <strong>¡Atención!</strong> {{ session('warning_persistent') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close" style="margin-top: -5px;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        <p>Esta opción permite actualizar masivamente los saldos a favor de sus contactos mediante un archivo Excel.</p>
        <h4>Tome en cuenta las siguientes reglas para cargar la data</h4>
        <ul>
            <li class="ml-3">La primera columna debe ser <b>Nro Identificacion</b> y la segunda <b>Saldo a Favor</b>. <small>Haga clic <a href="{{ route('saldos_favor.ejemplo') }}"><b>aquí</b></a> para descargar el archivo Excel de ejemplo.</small></li>
            <li class="ml-3">Verifique que el comienzo de los datos sea a partir de la fila 4 (respetando el encabezado del ejemplo).</li>
            <li class="ml-3">El Nro Identificacion debe ser el de un contacto registrado y existente en el sistema.</li>
            <li class="ml-3">El archivo debe tener extensión <b>.xlsx</b></li>
        </ul>

        <form method="POST" action="{{ route('saldos_favor.cargando') }}" role="form" enctype="multipart/form-data">
            @csrf
            <div class="row">
                <div class="form-group col-md-6 offset-md-3">
                    <label class="control-label">Archivo <span class="text-danger">*</span></label>
                    <input type="file" class="form-control" name="archivo" required="" accept=".xlsx, .XLSX">
                    <span class="help-block">
                        <strong>{{ $errors->first('archivo') }}</strong>
                    </span>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    @if(count($errors) > 0)
                    <div class="alert alert-danger">
                        <ul>
                            @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-12 text-right">
                    <a href="/empresa/configuracion" class="btn btn-outline-light" >Cancelar</a>
                    <button type="submit" class="btn btn-success">Importar Saldos</button>
                </div>
            </div>
        </form>
    </div>
@endsection
