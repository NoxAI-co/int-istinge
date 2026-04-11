@extends('layouts.pdf')

@section('content')
    <style type="text/css">
        /**
        * Define the width, height, margins and position of the watermark.
        **/#watermark {
            position: fixed; top: 25%;
            width: 100%; text-align:
                    center; opacity: .6;
            transform: rotate(-30deg);
            transform-origin: 50% 50%;
            z-index: 1000;
            font-size: 130px;
            color: #a5a5a5;
        }

        body{
            font-family: Helvetica, sans-serif;
            font-size: 11px;
            color: #000;
            line-height: 1.2;
            font-weight: bold;
        }
        h4{
            font-weight: bold;
            text-align: center;
            margin: 0 0 5px 0;
            font-size: 14px;
        }
        .text-center{
            text-align: center;
        }
        .text-right{
            text-align: right;
        }
        .text-left{
            text-align: left;
        }
        .font-weight-bold{
            font-weight: bold;
        }
        .header-info p {
            margin: 0;
            font-size: 11px;
            line-height: 1.3;
        }
        .info-box {
            width: 100%;
            text-align: center;
            margin-bottom: 10px;
        }
        .label {
            color: #444;
        }
        .value {
            font-weight: bold;
        }
        .desgloce {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .desgloce th {
            border-bottom: 1px solid #000;
            padding: 5px 0;
            font-size: 11px;
            text-transform: uppercase;
        }
        .desgloce td {
            padding: 4px 0;
            font-size: 11px;
        }
        .totals-table {
            width: 100%;
            margin-top: 5px;
            border-top: 1px solid #000;
            padding-top: 5px;
        }
        .totals-table td {
            padding: 2px 0;
        }
        .footer {
            margin-top: 20px;
            font-size: 10px;
            text-align: center;
        }

    </style>
    <div class="info-box header-info">
        <h4>{{Auth::user()->empresa()->nombre}}</h4>
        <p>{{Auth::user()->empresa()->tip_iden('mini')}} {{Auth::user()->empresa()->nit}}</p>
        <p>{{Auth::user()->empresa()->direccion}}</p>
        <p>{{Auth::user()->empresa()->telefono}}</p>
        @if(Auth::user()->empresa()->web)
            <p>{{Auth::user()->empresa()->web}}</p>
        @endif
        <p>{{Auth::user()->empresa()->email}}</p>
    </div>

    <div class="info-box">
        <p>
            <span class="label">Señor(es):</span> <span class="value">{{$factura->cliente()->nombre}} {{$factura->cliente()->apellidos()}}</span><br>
            @if(isset($data['Contrato']['direccion_instalacion']) && $data['Contrato']['direccion_instalacion'])
                <span class="label">Dirección:</span> <span class="value">{{$data['Contrato']['direccion_instalacion']}}</span><br>
            @elseif($factura->cliente()->direccion) 
                <span class="label">Dirección:</span> <span class="value">{{$factura->cliente()->direccion}}</span><br>
            @endif
            @if($factura->cliente()->ciudad) 
                <span class="label">Ciudad:</span> <span class="value">{{$factura->cliente()->ciudad}}</span><br>
            @endif
            @if($factura->cliente()->telefono1) <span class="label">Teléfono:</span> <span class="value">{{$factura->cliente()->telefono1}}</span><br>@endif
            @if($factura->cliente()->nit) <span class="label">{{ $factura->cliente()->tip_iden('mini')}}:</span> <span class="value">{{$factura->cliente()->nit}}</span>@endif
        </p>
    </div>

    <div class="info-box">
        <p>
            <span class="label">@if($factura->tipo == 1 || $factura->tipo == 2) Factura de Venta No. @elseif($factura->tipo == 3) Cuenta de Cobro No. @endif</span> <span class="value">{{$factura->codigo}}</span><br>
            <span class="label">Fecha Expedición:</span> <span class="value">{{date('d/m/Y', strtotime($factura->fecha))}}</span><br>
            <span class="label">Fecha Vencimiento:</span> <span class="value">{{date('d/m/Y', strtotime($factura->vencimiento))}}</span><br>
            <span class="label">Estado:</span> <span class="value">@if($factura->estatus == 0) Cerrada @endif @if($factura->estatus == 1) Abierta @endif @if($factura->estatus == 2) Anulada @endif</span><br>

            @if($ingreso != null)
                <span class="label">Recibo de Caja No.</span> <span class="value">{{ $ingreso->nro }}</span><br>
                <span class="label">Fecha del Pago:</span> <span class="value">{{ date('d/m/Y', strtotime($ingreso->ingreso()->fecha)) }}</span><br>
                <span class="label">Cuenta:</span> <span class="value">{{ $ingreso->ingreso()->cuenta()->nombre }}</span><br>
                <span class="label">Método de Pago:</span> <span class="value">{{ $ingreso->ingreso()->metodo_pago() }}</span><br>
                <span class="label">Periodo:</span> <span class="value">{{$factura->periodoCobrado('true')}}</span><br>
                @if($ingreso->ingreso()->notas) <span class="label">Notas:</span> <span class="value">{{ $ingreso->ingreso()->notas }}</span> @endif
            @endif
        </p>
    </div>

    <br>

    <div class="info-box">
        <table class="desgloce">
            <thead>
                <tr>
                    <th class="text-left">Ítem</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
            @foreach($items as $item)
                <tr>
                    <td class="text-left">{{strtolower($item->producto())}}</td>
                    <td class="text-right">{{Auth::user()->empresa()->moneda}}{{App\Funcion::Parsear($item->total())}}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <br>

    <div class="info-box">
        <table style="width: 100%; border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 5px 0;">
            <tbody>
                @if($factura->total()->imp)
                    @foreach($factura->total()->imp as $imp)
                        @if(isset($imp->total))
                            <tr>
                                <td style="width: 70%;" class="label">{{$imp->nombre}} ({{$imp->porcentaje}}%)</td>
                                <td style="width: 30%;" class="text-center value">{{Auth::user()->empresa()->moneda}}{{App\Funcion::Parsear($imp->total)}}</td>
                            </tr>
                        @endif
                    @endforeach
                @endif
                @if($ingreso != null)
                <tr>
                    <td style="width: 70%;" class="label">Monto a Pagar:</td>
                    <td style="width: 30%;" class="text-center value">{{Auth::user()->empresa()->moneda}}{{App\Funcion::Parsear($ingreso->ingreso()->pago())}} </td>
                </tr>
                <tr>
                    <td style="width: 70%;" class="label">Monto Pagado:</td>
                    <td style="width: 30%;" class="text-center value">{{Auth::user()->empresa()->moneda}}{{App\Funcion::Parsear($ingreso->ingreso()->pago() + $ingreso->ingreso()->valor_anticipo)}} </td>
                </tr>
                @if($factura->porpagar() > 0)
                <tr>
                    <td style="width: 70%;" class="label">Saldo por Pagar:</td>
                    <td style="width: 30%;" class="text-center value">{{Auth::user()->empresa()->moneda}}{{App\Funcion::Parsear($factura->porpagar())}} </td>
                </tr>
                @endif
                @if($ingreso->ingreso()->valor_anticipo > 0)
                <tr>
                    <td style="width: 70%;" class="label">Saldo a favor generado:</td>
                    <td style="width: 30%;" class="text-center value">{{Auth::user()->empresa()->moneda}}{{App\Funcion::Parsear($ingreso->ingreso()->valor_anticipo)}} </td>
                </tr>
                @endif
                @else
                <tr>
                    <td style="width: 100%;" class="text-right label">Pagado con saldo a favor</td>
                </tr>
                @if($factura->porpagar() > 0)
                <tr>
                    <td style="width: 70%;" class="label">Saldo por Pagar:</td>
                    <td style="width: 30%;" class="text-center value">{{Auth::user()->empresa()->moneda}}{{App\Funcion::Parsear($factura->porpagar())}} </td>
                </tr>
                @endif
                @endif
            </tbody>
        </table>
    </div>

    <br>

    <div class="footer">
        <p>RESOLUCIÓN DIAN #{{$resolucion->resolucion}}<br>RANGO DEL {{$resolucion->inicioverdadero}} HASTA {{$resolucion->final}}</p>
        <p>Integra Colombia</p>
        <p>Network Ingeniería S.A.S</p>
        <p>TIRILLA IMPRESA EL {{ date('d/m/Y') }}</p>
    </div>
@endsection
