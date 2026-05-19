@extends('layouts.app')
@section('content')
    <input type="hidden" id="valuefecha" value="{{$request->fechas}}">
    <input type="hidden" id="primera" value="{{$request->date ? $request->date['primera'] : ''}}">
    <input type="hidden" id="ultima" value="{{$request->date ? $request->date['ultima'] : ''}}">
    <p class="card-description">Libro oficial de ventas: consulte el detalle de las facturas de venta en un período y expórtelo a Excel.</p>
    <form id="form-reporte">
        <div class="row card-description">
            <div class="form-group col-md-2">
                <label>Rango</label>
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
                    <optgroup label="Todas">
                        <option value="8">Todas</option>
                    </optgroup>
                </select>
            </div>
            <div class="form-group col-md-4">
                <div class="row">
                    <div class="col-md-6">
                        <label>Desde <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="desde" value="{{$request->fecha}}" name="fecha" required>
                    </div>
                    <div class="col-md-6">
                        <label>Hasta <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="hasta" value="{{$request->hasta}}" name="hasta" required>
                    </div>
                </div>
            </div>
            <div class="form-group col-md-4" style="padding-top: 24px;">
                <button type="button" id="generar" class="btn btn-outline-primary">Generar Reporte</button>
                <button type="button" id="exportar" class="btn btn-outline-success">Exportar a Excel</button>
            </div>
        </div>

        <div class="row card-description">
            <div class="col-md-12">
                <div style="background-color:#000c34; color:#fff; text-align:center; padding:14px 6px; border:1px solid #000c34;">
                    <h3 style="margin:0; font-weight:bold;">Libro oficial de ventas</h3>
                    <div style="font-weight:bold;">{{ $empresa ? $empresa->nombre : '' }}</div>
                    <div style="font-weight:bold;">{{ $empresa ? $empresa->nit : '' }}</div>
                    <div style="font-weight:bold;">
                        @php
                            $mesesEs = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
                            $fIni = isset($dates['inicio']) ? strtotime($dates['inicio']) : null;
                            $fFin = isset($dates['fin']) ? strtotime($dates['fin']) : null;
                        @endphp
                        @if($fIni && $fFin)
                            De {{ $mesesEs[(int)date('n', $fIni)] }} {{ date('d', $fIni) }} {{ date('Y', $fIni) }}
                            a {{ $mesesEs[(int)date('n', $fFin)] }} {{ date('d', $fFin) }} {{ date('Y', $fFin) }}
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-12 table-responsive">
                <table class="table table-striped table-hover" id="table-libro-ventas">
                    <thead class="thead-dark">
                        <tr>
                            <th>Comprobante</th>
                            <th>Fecha elaboración</th>
                            <th>Identificación</th>
                            <th>Sucursal</th>
                            <th>Nombre tercero</th>
                            <th>Factura</th>
                            <th class="text-right">Base gravada</th>
                            <th class="text-right">Base exenta</th>
                            <th class="text-right">IVA</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr @if($row->estatus == 2) style="color:#d9534f;" @endif>
                                <td>{{ $row->comprobante }}</td>
                                <td>{{ $row->fecha_elaboracion }}</td>
                                <td>{{ $row->identificacion }}</td>
                                <td>{{ $row->sucursal }}</td>
                                <td>{{ $row->nombre_tercero }}</td>
                                <td>{{ $row->factura_proveedor }}</td>
                                <td class="text-right">{{ App\Funcion::Parsear($row->base_gravada) }}</td>
                                <td class="text-right">{{ App\Funcion::Parsear($row->base_exenta) }}</td>
                                <td class="text-right">{{ App\Funcion::Parsear($row->iva) }}</td>
                                <td class="text-right">{{ App\Funcion::Parsear($row->total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="thead-dark">
                        <tr>
                            <th colspan="6" class="text-right">Total general</th>
                            <th class="text-right">{{ App\Funcion::Parsear($totales['base_gravada']) }}</th>
                            <th class="text-right">{{ App\Funcion::Parsear($totales['base_exenta']) }}</th>
                            <th class="text-right">{{ App\Funcion::Parsear($totales['iva']) }}</th>
                            <th class="text-right">{{ App\Funcion::Parsear($totales['total']) }}</th>
                        </tr>
                    </tfoot>
                </table>
                <div class="text-center" style="margin-top:6px; font-style:italic;">
                    Procesado en: {{ $mesesEs[(int)date('n')] }} {{ date('d Y H:i') }}
                </div>
            </div>
        </div>
    </form>
    <input type="hidden" id="urlgenerar" value="{{route('reportes.libroVentas')}}">
    <input type="hidden" id="urlexportar" value="{{route('exportar.libroVentas')}}">
@endsection
