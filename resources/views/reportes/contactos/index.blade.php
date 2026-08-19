@extends('layouts.app')
@section('content')
    <input type="hidden" id="valuefecha" value="{{$request->fechas}}">
    <form id="form-reporte">


        <div class="row card-description">
            <div class="form-group col-md-2">
                <label></label>
                <select class="form-control selectpicker" name="fechas" id="fechas">
                    <optgroup label="Presente">
                        <option value="0">Hoy</option>
                        <option value="1">Este Mes</option>
                        <option value="2">Este Año</option>
                    </optgroup>
                    <optgroup label="Anterior">
                        <option value="3">Ayer</option>
                        <option value="4">Semana Pasada</option>
                        <option value="5">Mes Anterior</option>
                        <option value="6">Año Anterior</option>
                    </optgroup>
                    <optgroup label="Manual">
                        <option value="7">Manual</option>
                    </optgroup>
                    <optgroup label="Sin filtro">
                        <option value="8">Todos</option>
                    </optgroup>
                </select>
            </div>
            <div class="form-group col-md-4">
                <div class="row">
                    <div class="col-md-6">
                        <label>Desde <span class="text-danger">*</span></label>
                        <input type="text" class="form-control"  id="desde" value="{{$request->fecha}}" name="fecha" required="" >
                    </div>
                    <div class="col-md-6">
                        <label >Hasta <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="hasta" value="{{$request->hasta}}" name="hasta" required="">
                    </div>

                </div>
            </div>
            <div class="form-group col-md-4" style="    padding-top: 2%;">
                <button type="button" id="generar" class="btn btn-outline-secondary">Generar Reporte</button>
                <button type="button" id="exportar" class="btn btn-outline-secondary">Exportar a Excel</button>
            </div>
        </div>
        <div class="row card-description">
            <div class="col-md-12 table-responsive">
                <table class="table table-striped table-hover " id="example">
                    <thead class="thead-dark">
                    <tr>
                        <th>Nombre</th>
                        <th>Identificacion</th>
                        <th>Telefono</th>
                        <th>Tipo</th>
                        <th class="text-center">Contratos</th>
                        <th class="text-center">Facturas</th>
                        <th class="text-right">Facturado</th>
                        <th class="text-right">Pagado</th>
                        <th class="text-right">N. Credito</th>
                        <th class="text-right">Saldo (Deuda)</th>
                        <th class="text-right">Vencido</th>
                        <th class="text-center">Mora</th>
                        <th class="text-right">A favor</th>
                        <th>Ultimo pago</th>
                    </tr>
                    </thead>
                    <tbody>

                    @php $totSaldo = 0; $totVencido = 0; $totFavor = 0; @endphp
                    @foreach($contactos as $contacto)
                        @php
                            $e = $estados[$contacto->id] ?? [];
                            $saldo = $e['saldo'] ?? 0;
                            $vencido = $e['saldo_vencido'] ?? 0;
                            $favor = $contacto->saldo_favor ?? 0;
                            $totSaldo += $saldo; $totVencido += $vencido; $totFavor += $favor;
                        @endphp
                        <tr>
                            <td><a href="{{route('contactos.show', $contacto['id'])}}" target="_blanck">{{$contacto['nombre']}}</a> </td>
                            <td><spam title="{{$contacto->tip_iden()}}">({{$contacto->tip_iden('mini')}})</spam> {{$contacto->nit}}</td>
                            <td>{{$contacto->telefono1}}</td>
                            <td>{{$contacto->tipo_contacto()}}</td>
                            <td class="text-center">
                                {{ $e['contratos_total'] ?? 0 }}
                                @if(($e['contratos_activos'] ?? 0) > 0)
                                    <small class="text-success">({{ $e['contratos_activos'] }} act.)</small>
                                @endif
                            </td>
                            <td class="text-center">{{ $e['facturas_total'] ?? 0 }} <small class="text-muted">({{ $e['facturas_abiertas'] ?? 0 }} ab.)</small></td>
                            <td class="text-right">${{ number_format($e['facturado'] ?? 0, 0, ',', '.') }}</td>
                            <td class="text-right">${{ number_format($e['pagado'] ?? 0, 0, ',', '.') }}</td>
                            <td class="text-right">@if(($e['notas_credito_aplicadas'] ?? 0) > 0)${{ number_format($e['notas_credito_aplicadas'], 0, ',', '.') }}@else <span class="text-muted">-</span> @endif</td>
                            <td class="text-right">
                                @if($saldo > 1)
                                    <b class="text-danger">${{ number_format($saldo, 0, ',', '.') }}</b>
                                @else
                                    <span class="text-success">Al dia</span>
                                @endif
                            </td>
                            <td class="text-right">@if($vencido > 1)<span class="text-danger">${{ number_format($vencido, 0, ',', '.') }}</span>@else <span class="text-muted">-</span> @endif</td>
                            <td class="text-center">@if(($e['dias_mora'] ?? 0) > 0)<span class="text-danger">{{ $e['dias_mora'] }}d</span>@else <span class="text-muted">-</span> @endif</td>
                            <td class="text-right">@if($favor > 0)<span class="text-info">${{ number_format($favor, 0, ',', '.') }}</span>@else <span class="text-muted">-</span> @endif</td>
                            <td>{{ !empty($e['ultimo_pago_fecha']) ? date('d-m-Y', strtotime($e['ultimo_pago_fecha'])) : '-' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    {{-- Los totales van en <tfoot>, NO en el <tbody>: DataTables
                         (que inicializa #example desde custom.js) recorre las filas
                         del cuerpo asumiendo una celda por columna, y un colspan ahí
                         lo rompe con "Cannot set properties of undefined
                         (setting '_DT_CellIndex')", dejando la tabla sin pintar. --}}
                    <tfoot class="thead-dark">
                    <tr class="font-weight-bold">
                        <th colspan="9" class="text-right">TOTALES:</th>
                        <th class="text-right text-danger">${{ number_format($totSaldo, 0, ',', '.') }}</th>
                        <th class="text-right text-danger">${{ number_format($totVencido, 0, ',', '.') }}</th>
                        <th></th>
                        <th class="text-right text-info">${{ number_format($totFavor, 0, ',', '.') }}</th>
                        <th></th>
                    </tr>
                    </tfoot>
                </table>
                <div class="text-right">
                   {{-- {{$contactos->links()}}--}}

                </div>
            </div>
        </div>
    </form>
    <input type="hidden" id="urlgenerar" value="{{route('reportes.contactos')}}">
    <input type="hidden" id="urlexportar" value="{{route('exportar.contactos')}}">
@endsection

{{-- Este script vivía dentro de @section('content'), que el layout imprime
     ANTES de cargar jQuery: se ejecutaba con $ todavía indefinido
     ("Uncaught ReferenceError: $ is not defined") y el selector «Todos» nunca
     rellenaba las fechas. En @section('scripts') el layout ya cargó jQuery. --}}
@section('scripts')
    <script>
        $(function () {
            $('#fechas').on('change', function () {
                if ($(this).val() == '8') {
                    $('#desde').val('01-01-2000');
                    $('#hasta').val(moment().format('DD-MM-YYYY'));
                }
            });
            if ($('#fechas').val() == '8') {
                $('#fechas').trigger('change');
            }
        });
    </script>
@endsection
