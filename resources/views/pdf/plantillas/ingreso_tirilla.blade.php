@extends('layouts.pdf')

@section('content')
<style type="text/css">
    /**
        * Define the width, height, margins and position of the watermark.
        **/
    #watermark {
        position: fixed;
        top: 25%;
        width: 100%;
        text-align:
            center;
        opacity: .6;
        transform: rotate(-30deg);
        transform-origin: 50% 50%;
        z-index: 1000;
        font-size: 130px;
        color: #a5a5a5;
    }

    body {
        font-family: Helvetica, Arial, sans-serif;
        font-size: 15px;
        color: #000;
        background: #fff;
        font-weight: bold;
    }

    h4 {
        font-weight: bold;
        text-align: center;
        margin: 0 0 8px 0;
        font-size: 17px;
        letter-spacing: 0.25px;
        color: #000;
    }

    .small {
        font-size: 13px;
        line-height: 12px;
        margin: 0;
    }

    .smalltd {
        font-size: 13px;
        line-height: 12px;
        padding-right: 2px;
    }

    .medium {
        font-size: 20px;
        line-height: 14px;
        margin: 0;
    }

    a {
        color: #000;
        text-decoration: none;
    }

    /* th{
            background: #ccc;
        }
        td{
            padding-left: 2px;
        }*/
    .center {
        text-align: center;
    }

    .right {
        text-align: right;
    }

    .left {
        text-align: left;
    }


    .titulo {
        width: 100%;
        border-collapse: collapse;
        border-radius: 0.4em;
        overflow: hidden;
    }

    /*  td {
            border: 1px  solid #9e9b9b;
        }

        th {
            border: 1px  solid #ccc;
        }*/
    .desgloce {
        width: 100%;
        overflow: hidden;
        border-collapse: collapse;
        border-top-left-radius: 0.4em;
        border-top-right-radius: 0.4em;
    }

    .desgloce td {
        padding-top: 3px;
        border-left: 2px solid #fff;
        border-top: 2px solid #fff;
        border-bottom: 2px solid #fff;
        border-right: 2px solid #ccc;
    }

    .foot td {
        padding-top: 3px;
        border: 1px solid #fff;
        padding-right: 1%;
    }

    .foot th {
        padding: 2px;
        border-radius: unset;
    }

    .border_left {
        border-left: 3px solid #ccc !important;
    }

    .border_bottom {
        border-bottom: 5px solid #ccc !important;
    }

    .border_right {
        border-right: 3px solid #ccc !important;
    }

    .padding-right {
        padding-right: 1% !important;
    }

    .padding-left {
        padding-left: 1%;
    }

    .tirilla-section {
        margin-bottom: 6px;
        padding: 0;
    }
    .tirilla-section:last-of-type {
        margin-bottom: 0 !important;
    }

    .tirilla-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
        margin: 0;
        padding: 0;
    }

    .tirilla-table th,
    .tirilla-table td {
        padding: 6px 4px 6px 4px;
        border: none;
    }

    .tirilla-table th {
        border-bottom: 1px solid #888;
        font-weight: bold;
        background: none;
        font-size: 14px;
        text-align: left;
        color: #000;
    }

    .tirilla-table td {
        border-bottom: 1px solid #eee;
        font-size: 14px;
        color: #000;
        font-weight: bold;
    }

    .tirilla-table tr:last-child td {
        border-bottom: none;
    }

    .item-col {
        text-align: left;
        width: 60%;
        word-break: break-all;
    }

    .valor-col {
        text-align: right !important;
        width: 40%;
        white-space: nowrap;
    }

    .tirilla-label {
        color: #000;
        font-weight: bold;
        font-size: 14px;
    }

    .tirilla-total {
        font-weight: bold;
        font-size: 15px;
        border-top: 1px solid #888;
        border-bottom: 1px solid #888;
        background: none;
    }

    .tirilla-hr {
        border: none;
        border-top: 1px solid #e0e0e0;
        margin: 6px 0 4px 0;
    }
</style>
<div class="tirilla-section" style="text-align: center;">
    <img src="{{asset('images/Empresas/Empresa'.$empresa->id.'/'.$empresa->logo)}}" alt="Logo" style="max-width: 180px; max-height:90px; object-fit:contain; margin-bottom: 2px;">
    <h4>{{Auth::user()->empresa()->nombre}}</h4>
    <div style="font-size:12px; line-height:13px; font-weight: bold; color: #000;">
        {{Auth::user()->empresa()->tip_iden('mini')}} {{Auth::user()->empresa()->nit}}<br>
        {{Auth::user()->empresa()->direccion}}<br>
        {{Auth::user()->empresa()->telefono}}<br>
        @if(Auth::user()->empresa()->web)
        {{Auth::user()->empresa()->web}}<br>
        @endif
        <span style="font-size:10px; font-weight: bold;">{{Auth::user()->empresa()->email}}</span>
    </div>
</div>
<hr class="tirilla-hr">
<div class="tirilla-section" style="text-align: left;">
    <span class="tirilla-label">Señor(es):</span> {{$ingreso->cliente()->nombre}} {{$ingreso->cliente()->apellidos()}}<br>
    @if($ingreso->cliente()->direccion) <span class="tirilla-label">Dirección:</span> {{$ingreso->cliente()->direccion}}<br>@endif
    @if($ingreso->cliente()->ciudad) <span class="tirilla-label">Ciudad:</span> {{$ingreso->cliente()->ciudad}}<br>@endif
    @if($ingreso->cliente()->telefono1) <span class="tirilla-label">Teléfono:</span> {{$ingreso->cliente()->telefono1}}<br>@endif
    @if($ingreso->cliente()->nit) <span class="tirilla-label">{{ $ingreso->cliente()->tip_iden('mini')}}:</span> {{$ingreso->cliente()->nit}}<br>@endif
</div>
<hr class="tirilla-hr">
<div class="tirilla-section" style="text-align: left;">
    @if($ingreso->tipo == 1 || $ingreso->tipo == 2) <span class="tirilla-label">Ingreso:</span> @elseif($ingreso->tipo == 3) <span class="tirilla-label">Cuenta de Cobro:</span> @endif <b>No. {{$ingreso->nro}}</b><br>
    <span class="tirilla-label">Fecha Expedición:</span> {{date('d/m/Y', strtotime($ingreso->fecha))}}<br>
    <span class="tirilla-label">Fecha Vencimiento:</span> {{date('d/m/Y', strtotime($ingreso->ingresofactura()->factura()->vencimiento))}}<br>
    <span class="tirilla-label">Estado:</span> @if($ingreso->ingresofactura()->factura()->estatus == 0) Cerrada @endif @if($ingreso->ingresofactura()->factura()->estatus == 1) Abierta @endif @if($ingreso->ingresofactura()->factura()->estatus == 2) Anulada @endif<br>
    <span class="tirilla-label">Recibo de Caja:</span> <b>No. {{ $ingreso->nro }}</b><br>
    <span class="tirilla-label">Fecha del Pago:</span> {{ date('d/m/Y', strtotime($ingreso->fecha)) }}<br>
    <span class="tirilla-label">Cuenta:</span> {{ $ingreso->cuenta()->nombre }}<br>
    <span class="tirilla-label">Método de Pago:</span> {{ $ingreso->metodo_pago() }}<br>
    @if(isset(Auth::user()->empresa()->periodo_tirilla) && Auth::user()->empresa()->periodo_tirilla == 1)
    <span class="tirilla-label">Periodo:</span> {{$ingreso->ingresofactura()->factura()->periodoCobradoTexto()}}<br>
    @endif
    @if($ingreso->notas) <span class="tirilla-label">Notas:</span> {{ $ingreso->notas }} @endif
</div>
<hr class="tirilla-hr">
<div class="tirilla-section">
    <table class="tirilla-table">
        <thead>
            <tr>
                <th class="item-col">Ítem</th>
                <th class="valor-col">Valor</th>
            </tr>
        </thead>
        <tbody>
            @php $totalApagar = 0; @endphp
            @foreach($items as $item)
            @php $totalApagar=$totalApagar+$item->precio; @endphp
            <tr>
                <td class="item-col">{{$item->ref}}</td>
                <td class="valor-col">{{$empresa->moneda}}{{App\Funcion::Parsear($item->precio)}}</td>
            </tr>
            @endforeach
            @foreach($items as $item)
            @if($item->impuesto != 0)
            @php
            $totalApagar=$totalApagar + ($item->impuesto * $item->precio) / 100 ;
            @endphp
            <tr>
                <td class="item-col">IVA {{round($item->impuesto)}} %</td>
                <td class="valor-col">{{$empresa->moneda}}{{App\Funcion::Parsear(($item->impuesto * $item->precio) / 100 )}}</td>
            </tr>
            @endif
            @endforeach
        </tbody>
    </table>
</div>
<hr class="tirilla-hr">
<div class="tirilla-section">
    <table class="tirilla-table">
        <tbody>
            @if($ingreso->total()->imp)
            @foreach($ingreso->total()->imp as $imp)
            @if(isset($imp->total))
            <tr>
                <td class="item-col">{{$imp->nombre}} ({{$imp->porcentaje}}%)</td>
                <td class="valor-col">{{Auth::user()->empresa()->moneda}}{{App\Funcion::Parsear($imp->total)}}</td>
            </tr>
            @endif
            @endforeach
            @endif
            <tr class="tirilla-total">
                <td class="item-col">Monto a Pagar:</td>
                <td class="valor-col">{{Auth::user()->empresa()->moneda}}{{App\Funcion::Parsear($totalApagar)}} </td>
            </tr>
            <tr>
                <td class="item-col">Monto Pagado:</td>
                <td class="valor-col">{{Auth::user()->empresa()->moneda}}{{App\Funcion::Parsear($ingreso->pago())}} </td>
            </tr>
            @if($ingreso->total()->total - $ingreso->pago() > 0)
            <tr>
                <td class="item-col">Monto Pendiente:</td>
                <td class="valor-col">{{Auth::user()->empresa()->moneda}}{{App\Funcion::Parsear($ingreso->pago() - $ingreso->pagado())}} </td>
            </tr>
            @endif
            @if($ingreso->valor_anticipo > 0)
            <tr>
                <td class="item-col">Saldo a favor generado:</td>
                <td class="valor-col">{{Auth::user()->empresa()->moneda}}{{App\Funcion::Parsear($ingreso->valor_anticipo)}} </td>
            </tr>
            @endif
        </tbody>
    </table>
</div>
<hr class="tirilla-hr">
<div class="tirilla-section" style="text-align: center;">
    <div style="font-size:9px; color:#000; font-weight: bold;">
        @if(isset($resolucion->resolucion))
        RESOLUCIÓN DIAN #{{$resolucion->resolucion}}<br>RANGO DEL {{$resolucion->inicioverdadero}} HASTA {{$resolucion->final}}.<br>
        @endif
        INTEGRA S.A.S<br>
        <b>TIRILLA IMPRESA EL {{ date('d/m/Y') }}</b>
    </div>
</div>
@endsection