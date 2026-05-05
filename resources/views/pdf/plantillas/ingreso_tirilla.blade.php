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
            margin: 0 0 2px 0;
            font-size: 12px;
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
            margin-bottom: 5px;
        }
        .label {
            color: #000;
            font-weight: bold;
        }
        .value {
            font-weight: bold;
        }
        .desgloce {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }
        .desgloce th {
            border-bottom: 1px solid #000;
            padding: 3px 0;
            font-size: 11px;
            text-transform: uppercase;
        }
        .desgloce td {
            padding: 2px 0;
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
            margin-top: 10px;
            font-size: 10px;
            text-align: center;
        }

    </style>
    <div style="width: 100%;">
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
            @php
                $direcciones_contratos = [];
                $facturas_del_ingreso = $ingreso->ingresosFacturas();
                if ($facturas_del_ingreso) {
                    foreach ($facturas_del_ingreso as $ingresoFactura) {
                        $fact = $ingresoFactura->factura();
                        if ($fact && $fact->relationContracts) {
                            foreach ($fact->relationContracts as $contrato) {
                                if (isset($contrato->address_street) && trim($contrato->address_street) !== '') {
                                    if (!in_array($contrato->address_street, $direcciones_contratos)) {
                                        $direcciones_contratos[] = $contrato->address_street;
                                    }
                                }
                            }
                        }
                    }
                }
                $direccion_mostrar = implode(", ", $direcciones_contratos);
            @endphp
            <span class="label">Señor(es):</span> <span class="value">{{$ingreso->cliente()->nombre}} {{$ingreso->cliente()->apellidos()}}</span><br>
            @if($direccion_mostrar != "") <span class="label">Dirección:</span> <span class="value">{{$direccion_mostrar}}</span><br>
            @elseif($ingreso->cliente()->direccion) <span class="label">Dirección:</span> <span class="value">{{$ingreso->cliente()->direccion}}</span><br>@endif
            @if($ingreso->cliente()->ciudad) <span class="label">Ciudad:</span> <span class="value">{{$ingreso->cliente()->ciudad}}</span><br>@endif
            @if($ingreso->cliente()->telefono1) <span class="label">Teléfono:</span> <span class="value">{{$ingreso->cliente()->telefono1}}</span><br>@endif
            @if($ingreso->cliente()->nit) <span class="label">{{ $ingreso->cliente()->tip_iden('mini')}}:</span> <span class="value">{{$ingreso->cliente()->nit}}</span>@endif
        </p>
    </div>

    @php $factura = $ingreso->ingresofactura() ? $ingreso->ingresofactura()->factura() : null; @endphp

    <div class="info-box">
        <p>
            @if($ingreso->tipo == 1)
            <span class="label">Factura:</span> <span class="value">{{ $factura->codigo }}</span><br>
            <span class="label">Estado Factura:</span> <span class="value">@if($factura->estatus == 0) Cerrada @endif @if($factura->estatus == 1) Abierta @endif @if($factura->estatus == 2) Anulada @endif</span><br>
            @endif
            
            <span class="label">Recibo de Caja No.</span> <span class="value">{{ $ingreso->nro }}</span><br>
            <span class="label">Fecha del Pago:</span> <span class="value">{{ date('d/m/Y', strtotime($ingreso->fecha)) }}</span><br>
            <span class="label">Hora:</span> <span class="value">{{date('H:i',strtotime($ingreso->created_at))}}</span> <br>
            <span class="label">Cuenta:</span> <span class="value">{{ $ingreso->cuenta()->nombre }}</span><br>
            <span class="label">Método de Pago:</span> <span class="value">{{ $ingreso->metodo_pago() }}</span><br>
            @if(isset($saldo_inicial) && $saldo_inicial > 0)
            <span class="label">Saldo Inicial:</span> <span class="value">{{Auth::user()->empresa()->moneda}}{{App\Funcion::Parsear($saldo_inicial)}}</span><br>
            @endif
            @if($ingreso->tipo == 1 && $factura)
                <span class="label">Periodo:</span> <span class="value">{{$factura->periodoCobradoTexto()}}</span><br>
                @if($factura->porpagar() > 0)
                    <span class="label">Saldo por Pagar:</span> <span class="value">{{Auth::user()->empresa()->moneda}}{{App\Funcion::Parsear($factura->porpagar())}}</span><br>
                @endif
            @endif
            @if($ingreso->notas) <span class="label">Notas:</span> <span class="value">{{ $ingreso->notas }}</span> @endif
        </p>
    <div class="info-box">
        <table class="desgloce">
            <thead>
                <tr>
                    <th class="text-left">Ítem</th>
                    <th class="text-right">Valor</th>
                </tr>
            </thead>
            <tbody>
            @php $totalApagar = 0; @endphp
            @foreach($items as $item)
             @php 
                $totalApagar=$totalApagar+$item->precio; 
                $nombre_item = '';
                if($item->descripcion != null) {
                    $nombre_item = $item->descripcion;
                } else {
                    if(isset($item->tipo_inventario) && $item->tipo_inventario == 1) {
                        $inv = App\Model\Inventario\Inventario::find($item->producto);
                        $nombre_item = $inv ? $inv->producto : 'Producto '.$item->producto;
                    } elseif(isset($item->tipo_inventario) && $item->tipo_inventario == 2) {
                        $inv = DB::table('inventario_volatil')->where('id', $item->producto)->first();
                        $nombre_item = $inv ? $inv->producto : 'Producto '.$item->producto;
                    } elseif(isset($item->producto)) {
                        $nombre_item = $item->producto;
                    } elseif(method_exists($item, 'categoria')) {
                        $nombre_item = $item->categoria(true);
                    }
                }
             @endphp
                <tr>
                    <td class="text-left">{{$nombre_item}}</td>
                    <td class="text-right">{{$empresa->moneda}}{{App\Funcion::Parsear($item->precio)}}</td>
                </tr>
            @endforeach

            @foreach($items as $item)
                @if($item->impuesto != 0)
                @php
                $totalApagar=$totalApagar + ($item->impuesto * $item->precio) / 100 ;
                @endphp
                <tr>
                    <td class="text-left">IVA {{round($item->impuesto)}} %</td>
                    <td class="text-right">{{$empresa->moneda}}{{App\Funcion::Parsear(($item->impuesto * $item->precio) / 100 )}}</td>
                </tr>
                @endif
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="info-box">
        <table style="width: 100%; border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 3px 0;">
            <tbody>
                @if($ingreso->total()->imp)
                    @foreach($ingreso->total()->imp as $imp)
                        @if(isset($imp->total))
                            <tr>
                                <td style="width: 70%;" class="label">{{$imp->nombre}} ({{$imp->porcentaje}}%)</td>
                                <td style="width: 30%;" class="text-center value">{{Auth::user()->empresa()->moneda}}{{App\Funcion::Parsear($imp->total)}}</td>
                            </tr>
                        @endif
                    @endforeach
                @endif
                <tr>
                    <td style="width: 70%;" class="label">Monto a Pagar:</td>
                    <td style="width: 30%;" class="text-center value">{{Auth::user()->empresa()->moneda}}{{App\Funcion::Parsear($totalApagar)}} </td>
                </tr>
                <tr>
                    <td style="width: 70%;" class="label">Monto Pagado:</td>
                    <td style="width: 30%;" class="text-center value">{{Auth::user()->empresa()->moneda}}{{App\Funcion::Parsear($ingreso->pago())}} </td>
                </tr>
                @if($ingreso->tipo == 1 && $factura && $factura->porpagar() > 0)
                <tr>
                    <td style="width: 70%;" class="label">Saldo por Pagar:</td>
                    <td style="width: 30%;" class="text-center value">{{Auth::user()->empresa()->moneda}}{{App\Funcion::Parsear($factura->porpagar())}} </td>
                </tr>
                @endif
                @if($ingreso->valor_anticipo > 0)
                <tr>
                    <td style="width: 70%;" class="label">Saldo a favor generado:</td>
                    <td style="width: 30%;" class="text-center value">{{Auth::user()->empresa()->moneda}}{{App\Funcion::Parsear($ingreso->valor_anticipo)}} </td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>

    <div class="footer">
        @if(isset($resolucion->resolucion))
            <p>RESOLUCIÓN DIAN #{{$resolucion->resolucion}}<br>RANGO DEL {{$resolucion->inicioverdadero}} HASTA {{$resolucion->final}}</p>
        @endif
        <p>INTEGRA S.A.S</p>
        <p>TIRILLA IMPRESA EL {{ date('d/m/Y') }}</p>
    </div>
@endsection
