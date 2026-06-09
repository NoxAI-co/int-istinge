@extends('layouts.app')

@section('content')

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
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

    <div class="row">
        {{-- Listado de archivos .log --}}
        <div class="col-lg-4 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title"><i class="fa fa-file-alt"></i> Archivos de Log</h4>

                    @if(count($logs) > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Archivo</th>
                                        <th>Tamaño</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($logs as $log)
                                        <tr @if($seleccionado == $log['nombre']) class="table-active" @endif>
                                            <td>
                                                <a href="{{ route('master.logs.index', ['archivo' => $log['nombre']]) }}">
                                                    {{ $log['nombre'] }}
                                                </a>
                                                <br>
                                                <small class="text-muted">{{ $log['modificado'] }}</small>
                                            </td>
                                            <td>{{ $log['tamano'] }}</td>
                                            <td>
                                                <a href="{{ route('master.logs.descargar', ['archivo' => $log['nombre']]) }}"
                                                   class="btn btn-sm btn-outline-primary" title="Descargar">
                                                    <i class="fa fa-download"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted">No se encontraron archivos .log en la carpeta de logs.</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Contenido del archivo seleccionado --}}
        <div class="col-lg-8 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">
                            @if($seleccionado)
                                Contenido: {{ $seleccionado }}
                            @else
                                Contenido del log
                            @endif
                        </h4>
                        @if($seleccionado)
                            <form action="{{ route('master.logs.vaciar') }}" method="POST"
                                  onsubmit="return confirm('¿Seguro que deseas vaciar el archivo {{ $seleccionado }}? Esta acción no se puede deshacer.');">
                                @csrf
                                <input type="hidden" name="archivo" value="{{ $seleccionado }}">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="fa fa-trash-alt"></i> Vaciar log
                                </button>
                            </form>
                        @endif
                    </div>
                    <hr>

                    @if($seleccionado)
                        @if(!empty($truncado))
                            <div class="alert alert-warning" role="alert">
                                <i class="fa fa-exclamation-triangle"></i>
                                El archivo pesa <strong>{{ $tamanoTotal }}</strong>.
                                Solo se muestran los últimos <strong>{{ $tamanoMostrado }}</strong>
                                (cola del log) para que el navegador no se cuelgue.
                                Para ver el contenido completo usa el botón
                                <i class="fa fa-download"></i> <strong>Descargar</strong>.
                            </div>
                        @endif
                        <pre style="max-height: 70vh; overflow:auto; background:#1e1e1e; color:#d4d4d4; padding:15px; border-radius:4px; font-size:12px; white-space:pre-wrap; word-wrap:break-word;">{{ $contenido !== '' ? $contenido : 'El archivo está vacío.' }}</pre>
                    @else
                        <p class="text-muted">Selecciona un archivo de la lista para ver su contenido.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

@endsection
