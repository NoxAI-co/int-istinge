@extends('layouts.app')

@section('content')
<div class="row card-description">
    <div class="col-md-12 mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="font-weight-bold text-uppercase">Resumen de Nómina</h4>
            <p class="text-muted">Seleccione los periodos que desea exportar de forma masiva.</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary" id="btn-export-bulk">
                <i class="fas fa-file-export mr-1"></i> Exportar Seleccionados
            </button>
        </div>
    </div>
    
    <div class="col-md-12">
        <form id="form-bulk-export" action="{{ route('reportes.nomina_resumen_export') }}" method="POST">
            @csrf
            <div class="table-responsive">
                <table class="table table-striped table-hover shadow-sm" id="table-reporte-nomina">
                    <thead class="thead-dark">
                        <tr>
                            <th style="width: 5%" class="text-center no-sort">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="check-all">
                                    <label class="custom-control-label" for="check-all"></label>
                                </div>
                            </th>
                            <th style="width: 25%">Periodo</th>
                            <th style="width: 15%">Año</th>
                            <th style="width: 15%" class="text-center">Empleados</th>
                            <th style="width: 40%" class="text-center">Exportar Individualmente</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($periodos as $periodo)
                        @php
                            $nominaRef = App\Model\Nomina\Nomina::where('year', $periodo->year)
                                ->where('periodo', $periodo->periodo)
                                ->where('fk_idempresa', auth()->user()->empresa)
                                ->first();
                            $miniPeriodos = $nominaRef ? $nominaRef->nominaperiodos : collect([]);
                        @endphp
                        <tr>
                            <td class="text-center align-middle">
                                @if($miniPeriodos->count() == 1)
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" name="periodos[]" class="custom-control-input check-period" 
                                               id="check-{{ $periodo->year }}-{{ $periodo->periodo }}-{{ $miniPeriodos->first()->periodo }}" 
                                               value="{{ $periodo->year }}-{{ $periodo->periodo }}-{{ $miniPeriodos->first()->periodo }}">
                                        <label class="custom-control-label" for="check-{{ $periodo->year }}-{{ $periodo->periodo }}-{{ $miniPeriodos->first()->periodo }}"></label>
                                    </div>
                                @else
                                    <i class="fas fa-layer-group text-muted" title="Múltiples quincenas"></i>
                                @endif
                            </td>
                            <td class="align-middle font-weight-bold">
                                {{ $periodo->periodo() }}
                            </td>
                            <td class="align-middle">{{ $periodo->year }}</td>
                            <td class="align-middle text-center">
                                <span class="badge badge-info px-3 py-2">
                                    <i class="fas fa-users mr-1"></i> {{ $periodo->empleados() }}
                                </span>
                            </td>
                            <td class="align-middle text-center">
                                @foreach($miniPeriodos as $mp)
                                    <div class="d-inline-flex align-items-center mx-2 mb-1">
                                        @if($miniPeriodos->count() > 1)
                                            <div class="custom-control custom-checkbox mr-2">
                                                <input type="checkbox" name="periodos[]" class="custom-control-input check-period" 
                                                       id="check-{{ $periodo->year }}-{{ $periodo->periodo }}-{{ $mp->periodo }}" 
                                                       value="{{ $periodo->year }}-{{ $periodo->periodo }}-{{ $mp->periodo }}">
                                                <label class="custom-control-label" for="check-{{ $periodo->year }}-{{ $periodo->periodo }}-{{ $mp->periodo }}"></label>
                                            </div>
                                        @endif
                                        <a href="{{ route('nomina.resumenExcel', ['periodo' => $periodo->periodo, 'year' => $periodo->year, 'tipo' => $mp->periodo]) }}" 
                                           class="btn btn-outline-success btn-sm" 
                                           data-toggle="tooltip" 
                                           title="Descargar Excel - {{ $mp->periodo == 1 && $miniPeriodos->count() > 1 ? '1ra Quincena' : ($mp->periodo == 2 ? '2da Quincena' : 'Mes') }}">
                                            <i class="fas fa-file-excel mr-1"></i> 
                                            @if($miniPeriodos->count() > 1)
                                                {{ $mp->periodo }}° Q.
                                            @else
                                                Excel
                                            @endif
                                        </a>
                                    </div>
                                @endforeach
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        var table = $('#table-reporte-nomina').DataTable({
            "language": {
                "url": "https://cdn.datatables.net/plug-ins/1.10.16/i18n/Spanish.json"
            },
            "order": [[2, "desc"], [1, "desc"]],
            "pageLength": 12,
            "columnDefs": [
                { "targets": 'no-sort', "orderable": false }
            ],
            "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
        });
        
        $('[data-toggle="tooltip"]').tooltip();

        // Handle check-all
        $('#check-all').on('click', function() {
            var rows = table.rows({ 'search': 'applied' }).nodes();
            $('input[type="checkbox"].check-period', rows).prop('checked', this.checked);
        });

        // Handle bulk export button
        $('#btn-export-bulk').on('click', function() {
            var selected = [];
            // Usamos table.rows().nodes() para capturar incluso los que no están en la página actual
            $('input[type="checkbox"].check-period:checked', table.rows().nodes()).each(function() {
                selected.push($(this).val());
            });

            if (selected.length === 0) {
                Swal.fire("Atención", "Debe seleccionar al menos un periodo para exportar.", "warning");
                return;
            }

            Swal.fire({
                title: "¿Exportar " + selected.length + " periodos?",
                text: "Se generará un único archivo Excel con toda la información consolidada.",
                type: "info",
                showCancelButton: true,
                confirmButtonClass: "btn-primary",
                confirmButtonText: "Sí, exportar",
                cancelButtonText: "Cancelar"
            }).then((result) => {
                if (result.value) {
                    // Creamos un formulario dinámico para enviar los datos por POST
                    var form = $('#form-bulk-export');
                    form.find('.dynamic-hidden').remove(); // Limpiar previos
                    
                    selected.forEach(function(val) {
                        form.append('<input type="hidden" class="dynamic-hidden" name="periodos[]" value="' + val + '">');
                    });
                    
                    form.submit();
                }
            });
        });
    });
</script>

<style>
    .table thead th {
        border-bottom: none;
        letter-spacing: 0.5px;
        font-weight: 600;
    }
    .badge-info {
        background-color: #17a2b8;
        font-size: 0.9rem;
    }
    .btn-outline-success:hover {
        color: #fff;
        background-color: #28a745;
        border-color: #28a745;
    }
    .custom-checkbox .custom-control-label::before {
        border-radius: .25rem;
    }
</style>
@endsection
