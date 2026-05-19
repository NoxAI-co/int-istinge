@extends('layouts.pdf')

@section('content')
@php
    // Variables precomputadas en el controlador (con fallback por compatibilidad).
    $cliente_pdf            = $cliente              ?? $ingreso->cliente();
    $cuenta_pdf             = $cuenta               ?? $ingreso->cuenta();
    $metodo_pago_pdf        = $metodoPago           ?? $ingreso->metodo_pago();
    $pago_total_pdf         = $pagoTotal            ?? $ingreso->pago();
    $cliente_tipiden_mini   = $clienteTipIdenMini   ?? ($cliente_pdf ? $cliente_pdf->tip_iden('mini') : '');
    $items_rendered_pdf     = $itemsRendered        ?? null;
    $retenciones_map_pdf    = $retencionPorId       ?? null;
    $factura_primary_pdf    = $facturaPrimary       ?? ($ingreso->ingresofactura() ? $ingreso->ingresofactura()->factura() : null);
    $periodo_cobrado_pdf    = $periodoCobradoTexto  ?? ($factura_primary_pdf ? $factura_primary_pdf->periodoCobradoTexto() : null);
    $contrato_asociado_nro_pdf = $contratoAsociadoNro ?? null;
    if ($contrato_asociado_nro_pdf === null) {
        if ($factura_primary_pdf) {
            $contratoAsoc = $factura_primary_pdf->contratoAsociado();
            $contrato_asociado_nro_pdf = $contratoAsoc ? $contratoAsoc->nro : 'N/A';
        } else {
            $contrato_asociado_nro_pdf = 'N/A';
        }
    }
    $porpagar_factura_pdf = $porpagarFactura ?? ($factura_primary_pdf ? $factura_primary_pdf->porpagar() : null);
@endphp
<style type="text/css">
    #watermark {
        position: fixed;
        top: 25%;
        width: 100%;
        text-align: center;
        opacity: .6;
        transform: rotate(-30deg);
        transform-origin: 50% 50%;
        z-index: 1000;
        font-size: 130px;
        color: #a5a5a5;
    }

    body {
        font-family: Helvetica, sans-serif;
        font-size: 12px;
        color: #000;
    }

    h4 {
        font-weight: bold;
        text-align: center;
        margin: 0;
        font-size: 14px;
    }

    .small {
        font-size: 10px;
        line-height: 12px;
        margin: 0;
    }

    .smalltd {
        font-size: 10px;
        line-height: 12px;
        padding-right: 2px;
    }

    a {
        color: #000;
        text-decoration: none;
    }

    th {
        background: #ccc;
    }

    td {
        padding-left: 2px;
    }

    .center {
        text-align: center;
    }

    .right {
        text-align: right;
    }

    .left {
        text-align: left;
    }

    .cajita {
        width: 100%;
        border-collapse: collapse;
        overflow: hidden;
    }

    .cajita td {
        background: #cccccc70;
        border-radius: 5px;
    }

    .titulo {
        width: 100%;
        border-collapse: collapse;
        border-radius: 0.4em;
        overflow: hidden;
    }

    .titulo td {
        border: 1px solid #9e9b9b;
    }

    .titulo th {
        border: 1px solid #ccc;
    }

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

    .text-center {
        text-align: center !important;
    }
</style>

<div style="width: 100%;">
    <div style="width: 20%; display: inline-block; vertical-align: top; text-align: center; ">
        <img src="{{asset('images/Empresas/Empresa'.$empresa->id.'/'.$empresa->logo)}}" alt="" style="width: 100%;">
    </div>
    <div style="width: 57%; text-align: center; display: inline-block;">
        <h4>{{$empresa->nombre}}
            @isset($empresa->nombre_persona_natural)
            <br>
            <small>{{$empresa->nombre_persona_natural}}</small>
            @endisset
        </h4>
        <p style="line-height: 12px;">{{$empresa->tip_iden('mini')}} {{$empresa->nit}} <br>
            {{$empresa->direccion}} <br>
            {{$empresa->telefono}}
            @if($empresa->web)
            <br>{{$empresa->web}}
            @endif
            <br> <a href="mailto:{{$empresa->email}}" target="_top">{{$empresa->email}}</a>
        </p>
    </div>
    <div style="width: 20%; display: inline-block; text-align: left; vertical-align: top; margin-top: 2%;">
        <table class="text-center cajita">
            <tr>
                <th style="padding-top: 5px;padding-bottom: 5px;">Recibo de caja @if($ingreso->tipo == 6) POS @endif</th>
            </tr>
            <tr>
                <td style="padding-top: 10px;padding-bottom: 10px; font-size: 14px; font-weight: 600;">No. {{$ingreso->nro}}</td>
            </tr>
        </table>
    </div>
</div>

<div>
    <table border="1" class="titulo">
        <tr>
            <th width="10%" class="right smalltd">SEÑOR(ES)</th>
            <td colspan="3" style="border-top: 2px solid #ccc;">@if($cliente_pdf){{$cliente_pdf->nombre}}@endif</td>
            <th width="22%" class="center" style="font-size: 8px"><b>FECHA(DD/MM/AA)</b></th>
        </tr>
        <tr>
            <th class="right smalltd">DIRECCIÓN</th>
            <td colspan="3">@if($cliente_pdf){{$cliente_pdf->direccion}}@endif</td>
            <td class="center" rowspan="4" style="font-size: 18px; border-right: 2px solid #ccc;">{{date('d/m/Y', strtotime($ingreso->fecha))}}</td>
        </tr>
        <tr>
            <th class="right smalltd">CIUDAD</th>
            <td colspan="3">@if($cliente_pdf){{$cliente_pdf->ciudad}}@endif</td>
        </tr>
        <tr>
            <th class="right smalltd">TELÉFONO</th>
            <td style="border-bottom: 2px solid #ccc;">@if($cliente_pdf){{$cliente_pdf->telefono1}}@endif</td>
            <th class="right smalltd" style="padding-right: 2px;">MÉTODO DE PAGO</th>
            <td style="border-bottom: 2px solid #ccc;">@if($cliente_pdf){{$metodo_pago_pdf}}@endif</td>
        </tr>
        <tr>
            <th class="right smalltd">@if($cliente_pdf){{$cliente_tipiden_mini}}@endif</th>
            <td style="border-bottom: 2px solid #ccc;">@if($cliente_pdf){{$cliente_pdf->nit}}@endif</td>
            <th class="right smalltd" style="padding-right: 2px;">CUENTA</th>
            <td style="border-bottom: 2px solid #ccc;">{{$cuenta_pdf->nombre}}</td>
        </tr>
    </table>
</div>

{{-- Tabla adicional con información detallada --}}
<div style="margin-top: 2%;">
    <table border="1" class="titulo">
        <thead>
            <tr>
                <th colspan="2" class="center" style="background: #ddd; padding: 5px;">INFORMACIÓN DETALLADA DEL PAGO</th>
            </tr>
        </thead>
        <tbody>
            @if($factura_primary_pdf)
                <tr>
                    <th width="15%" class="right smalltd">Periodo Cobrado:</th>
                    <td>{{$periodo_cobrado_pdf}}</td>
                </tr>
                <tr>
                    <th class="right smalltd">No. Contrato:</th>
                    <td>{{ $contrato_asociado_nro_pdf }}</td>
                </tr>
            @endif
            <tr>
                <th class="right smalltd">Monto pagado:</th>
                <td>{{ $empresa->moneda }} {{ App\Funcion::Parsear($pago_total_pdf) }}</td>
            </tr>
            @if($factura_primary_pdf && $porpagar_factura_pdf > 0)
            <tr>
                <th class="right smalltd">Saldo pendiente:</th>
                <td style="font-weight: bold;">{{ $empresa->moneda }} {{ App\Funcion::Parsear($porpagar_factura_pdf) }}</td>
            </tr>
            @endif
        </tbody>
    </table>
</div>
<div style="margin-top: 2%;">
    <table border="0" class="desgloce">
        <thead>
            <tr>
                <th width="70%" colspan="2" style="padding: 3px;" class="center smalltd">Concepto</th>
                <th style="padding: 3px;" width="15%" class="center smalltd">Valor</th>
            </tr>
        </thead>
        <tbody>
            @php $cont=0; @endphp
            @foreach($items as $idx => $item)
            @php
                $cont++;
                $rendered = $items_rendered_pdf[$idx] ?? null;
                $cat_obj = $rendered ? $rendered->categoria : (isset($item->categoria) && $item->categoria != null ? $item->categoria() : null);
                $detalle_text = $rendered ? $rendered->detalle_text : $item->detalle('Pago a');
                $pago_value   = $rendered ? $rendered->pago_value   : $item->pago();
            @endphp
            <tr>
                @if($cat_obj)
                <td colspan="2" class="left padding-left border_left @if($cont==$itemscount && $cont>6) border_bottom @endif">
                    {{$cat_obj->nombre}} - {{$cat_obj->codigo}}
                    </td>
                @else
                <td colspan="2" class="left padding-left border_left @if($cont==$itemscount && $cont>6) border_bottom @endif">{{$detalle_text}}</td>
                @endif
                <td class="right padding-right border_right @if($cont==$itemscount && $cont>6) border_bottom @endif">
                    {{$empresa->moneda}}{{App\Funcion::Parsear($pago_value)}}
                </td>
            </tr>
            @endforeach

            @if($cont<7)
                @php $cont=7-$cont; @endphp
                @for($i=1; $i<=$cont; $i++)
                <tr>
                    <td colspan="2" class="border_left @if($cont==$i) border_bottom @endif" style="height: 15px;"></td>
                    <td class="border_right @if($cont==$i) border_bottom @endif" style="height: 15px;"></td>
                </tr>
                @endfor
            @endif
        </tbody>
        <tfoot>
            <tr class="foot">
                <th colspan="3" class="smalltd">{{$ingreso->notas}}</th>
            </tr>
            <tr class="foot">
                <td width="90%"></td>
                <td class="right">SubTotal</td>
                <td class="right padding-right">{{$empresa->moneda}}{{App\Funcion::Parsear($pago_total_pdf)}}</td>
            </tr>

            @forelse($ingreso->retenciones as $retencion)
            @php
                $ret_def = $retenciones_map_pdf ? $retenciones_map_pdf->get($retencion->id_retencion) : null;
                if (!$ret_def) { $ret_def = $retencion->retencion(); }
            @endphp
            <tr class="foot">
                <td width="90%"></td>
                <td class="right">{{$ret_def->nombre ?? ''}} ({{$ret_def->porcentaje ?? ''}}%)</td>
                <td class="right padding-right">{{$empresa->moneda}} {{App\Funcion::Parsear($retencion->valor)}}</td>
            </tr>
            @empty
            @endforelse

            <tr class="foot">
                <td> </td>
                <th class="right padding-right">Total</th>
                <th class="right padding-right">{{$empresa->moneda}}{{App\Funcion::Parsear($pago_total_pdf)}} </th>
            </tr>

            @if($factura_primary_pdf && $porpagar_factura_pdf > 0)
            <tr class="foot">
                <td> </td>
                <th class="right padding-right">Saldo Pendiente</th>
                <th class="right padding-right">{{$empresa->moneda}}{{App\Funcion::Parsear($porpagar_factura_pdf)}} </th>
            </tr>
            @endif
        </tfoot>
    </table>
</div>


<div style="width: 70%; margin-top: 1%">
    <p style="text-align: justify;" class="small"></p>
    <div style="padding-top: 8%; text-align: center;">
        <div style="display: inline-block; width: 45%; border-top: 1px solid #000; margin-right: 10%;">
            <p class="small"> ELABORADO POR</p>
        </div>
        <div style="display: inline-block; width: 44%; border-top: 1px solid #000;">
            <p class="small"> ACEPTADA, FIRMA Y/O SELLO Y FECHA</p>
        </div>
    </div>
</div>

<div id="watermark">{{$ingreso->estatus==2?'ANULADA':''}}</div>

@endsection
