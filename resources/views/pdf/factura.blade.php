@extends('layouts.pdf')

@section('content')
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />

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
            font-family: "DejaVu Sans", sans-serif;
            font-size: 11px;
            color: #000;
        }
        h4{
            font-weight: bold;
            text-align: center;
            margin: 0;font-size: 14px;
        }
        .small{
            font-size: 10px;line-height: 8px;    margin: 0;
        }
        .smalltd{
            font-size: 10px;line-height: 8px; padding-right: 2px;
        }
        .medium{
            font-size: 17px;line-height: 10px;    margin: 0;
        }
        a{
            color: #000;
            text-decoration: none;
        }
        th{
            background: #ccc;
        }
        td{
            padding-left: 2px;
        }
        .center
        {
            text-align: center;
        }
        .right
        {
            text-align: right;
        }
        .left
        {
            text-align: left;
        }


        .titulo{
            width: 100%;
            border-collapse: collapse;
            border-radius: 0.4em;
            overflow: hidden;
        }
        td {
          border: 1px  solid #9e9b9b;
        }

        th {
          border: 1px  solid #ccc;
        }
        .desgloce{
            width: 100%;
            overflow: hidden;
            border-collapse: collapse;
            border-top-left-radius: 0.4em;
            border-top-right-radius: 0.4em;
        }
        .desgloce td{
            padding-top: 3px;
            border-left: 2px solid #fff;
            border-top: 2px solid #fff;
            border-bottom: 2px solid #fff;
            border-right: 2px solid #ccc;
        }
        .foot td{
            padding-top: 1px;
            border: 1px solid #fff;
            padding-right: 1%;
        }
        .foot th{
            padding: 1px;
            border-radius: unset;
        }
        .border_left{
            border-left: 3px solid #ccc !important;
        }
        .border_bottom{
            border-bottom: 5px solid #ccc !important;
        }
        .border_right{
            border-right: 3px solid #ccc !important;
        }
        .padding-right{
             padding-right:1% !important;
        }
        .padding-left{
            padding-left: 1%;
        }

    </style>

    @php
        $inventarioEmpresa = null;
        if(isset($items) && count($items) == 1){
            $invTemp = \App\Model\Inventario\Inventario::find($items[0]->producto);
            if($invTemp && isset($invTemp->nombre_empresa) && $invTemp->nombre_empresa != null && $invTemp->nombre_empresa != ''){
                $inventarioEmpresa = $invTemp;
            }
        }
    @endphp

    <div style="width: 100%;height:auto;">
        <div style="width: 30%; display: inline-block; vertical-align: top; text-align: center; height:100px !important;  margin-bottom: 2%; overflow:hidden; text-align:left;">
            @if($inventarioEmpresa && $inventarioEmpresa->imagen)
                <img src="{{asset('images/Empresas/Empresa'.$inventarioEmpresa->empresa.'/inventario/'.$inventarioEmpresa->imagen)}}" alt="" style="max-width: 100%; max-height:100px; object-fit:contain; text-align:left;">
            @else
                <img src="{{asset('images/Empresas/Empresa'.Auth::user()->empresa.'/'.Auth::user()->empresa()->logo)}}" alt="" style="max-width: 100%; max-height:100px; object-fit:contain; text-align:left;">
            @endif
        </div>
        <div style="width: 40%; text-align: center; display: inline-block;  height:auto; margin-right:45px;">
            @if($inventarioEmpresa)
                <h4>{{ $inventarioEmpresa->nombre_empresa }}</h4>
                <p style="line-height: 12px;">NIT {{ $inventarioEmpresa->nit_empresa }} @if(isset($inventarioEmpresa->dv_empresa) && $inventarioEmpresa->dv_empresa !== null) - {{ $inventarioEmpresa->dv_empresa }} @endif<br>
                    {{ isset($inventarioEmpresa->direccion_empresa) ? $inventarioEmpresa->direccion_empresa : '' }} <br>
                    <br> <a href="mailto:{{ isset($inventarioEmpresa->email_empresa) ? $inventarioEmpresa->email_empresa : '' }}" target="_top">{{ isset($inventarioEmpresa->email_empresa) ? $inventarioEmpresa->email_empresa : '' }}</a>
                </p>
            @else
                <h4>{{Auth::user()->empresa()->nombre}}</h4>
                <p style="line-height: 12px;">{{Auth::user()->empresa()->tip_iden('mini')}} {{Auth::user()->empresa()->nit}} @if(Auth::user()->empresa()->dv != null || Auth::user()->empresa()->dv === 0) - {{Auth::user()->empresa()->dv}} @endif<br>
                    {{Auth::user()->empresa()->direccion}} <br>
                    {{Auth::user()->empresa()->telefono}}
                    @if(Auth::user()->empresa()->web)
                        <br>{{Auth::user()->empresa()->web}}
                    @endif
                    <br> <a href="mailto:{{Auth::user()->empresa()->email}}" target="_top">{{Auth::user()->empresa()->email}}</a>
                </p>
            @endif

        </div>
        <div style="width: 21%; display: inline-block; text-align: left; vertical-align: top;
    margin-top: 2%;">
            <p class="medium">@if($factura->tipo == 1 && isset($codqr))Factura de Venta Electrónica @elseif($factura->tipo == 1) Factura de Venta @elseif($factura->tipo == 3) Cuenta de Cobro @endif</p>
            <h4 style="text-align: left; ">No. #{{$factura->codigo}}</h4>
            <p class="small">{{$tipo}}</p>
            <h4 style="text-align: left; ">{{Auth::user()->tipo_fac()}}</h4>
            @if($factura->ordencompra != null)
            <p class="small"># Orden Compra: {{$factura->ordencompra}}</p>
            @endif

        </div>
    </div>
    <div style="">
        <table border="0" class="titulo">
            @if(isset($codqr))
            <tr>
                <th width="10%" {{--class="right smalltd"--}} style="border: 0px solid transparent;background-color:transparent"></th>
                <td colspan="3" style="border: 0px solid transparent;background-color:transparent"></td>
                <th width="22%" class="center" style="font-size: 8px"><b>FECHA DE EXPEDICION (DD/MM/AA)</b></th>
            </tr>
             <tr>
                <th width="10%" {{--class="right smalltd"--}} style="border: 0px solid transparent;background-color:transparent"></th>
                <td colspan="3" style="border: 0px solid transparent;background-color:transparent"></td>
                <td class="center" style="border-right: 2px solid #ccc;">@if($factura->fecha_expedicion != null){{Carbon\Carbon::parse($factura->fecha_expedicion)->format('d/m/Y H:i:s')}}@else{{Carbon\Carbon::parse($factura->created_at)->format('d/m/Y H:i:s')}}@endif</td>
            </tr>
            @endif
            <tr>
                <th width="10%" class="right smalltd">SEÑOR(ES)</th>
                <td colspan="3" style="border-top: 2px solid #ccc;">{{$factura->cliente()->nombre}} {{$factura->cliente()->apellido1}} {{ $factura->cliente()->apellido2}}</td>
                <th width="22%" class="center" style="font-size: 8px"><b>FECHA DE GENERACION (DD/MM/AA)</b></th>
            </tr>
            <tr>
                <th class="right smalltd" width="10%">DIRECCION</th>
                <td colspan="">{{$factura->cliente()->direccion}}</td>
                <th class="right smalltd" width="15%" style="padding-right: 2px;">{{$factura->cliente()->tip_iden('mini')}}</th>
                <td style="border-bottom: 2px solid #ccc;" width="20%" >{{$factura->cliente()->nit }}
                    @if($factura->cliente()->dv != null)
                        - {{$factura->cliente()->dv }}
                    @endif</td>
                <td class="center" style="border-right: 2px solid #ccc;">{{--{{date('d/m/Y', strtotime($factura->fecha))}}--}}{{Carbon\Carbon::parse($factura->fecha)->format('d/m/Y')}}</td>
            </tr>

            <tr>
                <th rowspan="2" class="right smalltd">CIUDAD</th>
                <td rowspan="2">{{$factura->cliente()->municipio()->nombre}}</td>

                <th rowspan="2" class="right" style="font-size:9px">CORREO</th>
                <td rowspan="2" style="border-bottom:2px solid #ccc;">
                    {{$factura->cliente()->email}}
                </td>

                <th class="center" style="font-size:8px">
                    <b>FECHA DE VENCIMIENTO (DD/MM/AA)</b>
                </th>
            </tr>

            <tr>
                <td class="center" style="border-bottom:2px solid #ccc;">
                    {{ date('d/m/Y', strtotime($factura->vencimiento)) }}
                </td>
            </tr>
            <tr>
                <th width="10%" class="right smalltd">CELULAR</th>
                <td colspan="4" style="border-top: 2px solid #ccc;">
                    {{$factura->cliente()->celular}}
                </td>
            </tr>
        </table>
    </div>


    <div style="margin-top: 2%;">
        <table border="0" class="desgloce" >
            <colgroup>
                <col style="width: 35%">
                <col style="width: 14%">
                <col style="width: 10%">
                <col style="width: 14%">
                <col style="width: 12%">
                <col style="width: 15%">
            </colgroup>

            <thead>
                <tr>
                    <th class="center smalltd">Ítem</th>
                    <th class="center smalltd">Referencia</th>
                    <th class="center smalltd">Cantidad</th>
                    <th class="center smalltd">Precio</th>
                    <th class="center smalltd">Descuento</th>
                    <th class="center smalltd">Total</th>
                </tr>
            </thead>
            <tbody>
                @php $cont=0; @endphp
                @foreach($items as $item)

                @php $cont++; @endphp
                    <tr>
                        {{-- <td class="left padding-left border_left @if($cont==$itemscount && $cont>6) border_bottom @endif">{{$item->producto()}} @if($item->descripcion) ({{$item->descripcion}}) @endif</td> --}}
                        <td class="left padding-left border_left @if($cont==$itemscount && $cont>6) border_bottom @endif">{{ $item->producto()}} @if($item->descripcion) ({{ $item->descripcion}}) @endif</td>
                        <td class="center @if($cont==$itemscount && $cont>6) border_bottom @endif">{{$item->ref}}</td>
                        <td class="center  @if($cont==$itemscount && $cont>6) border_bottom @endif">{{round($item->cant)}}</td>
                        <td class="center padding-right  @if($cont==$itemscount && $cont>6) border_bottom @endif">{{Auth::user()->empresa()->moneda}}{{App\Funcion::Parsear($item->precio)}}</td>
                        <td class="center  @if($cont==$itemscount && $cont>6) border_bottom @endif">{{$item->desc == 0 ? '' :  $item->desc . "%"}}</td>
                        <td class="center padding-right border_right  @if($cont==$itemscount && $cont>6) border_bottom @endif">{{Auth::user()->empresa()->moneda}}{{App\Funcion::Parsear($item->total())}}</td>
                    </tr>

                @endforeach
                @if($cont<7)
                @php $cont=7-$cont; @endphp
                    @for($i=1; $i<=$cont; $i++)
                        <tr>
                            <td class="border_left @if($cont==$i) border_bottom @endif" style="height: 15px;"></td>
                            <td class=" @if($cont==$i) border_bottom @endif" style="height: 15px;"></td>
                            <td class=" @if($cont==$i) border_bottom @endif" style="height: 15px;"></td>
                            <td class=" @if($cont==$i) border_bottom @endif" style="height: 15px;"></td>
                            <td class=" @if($cont==$i) border_bottom @endif" style="height: 15px;"></td>
                            <td class="border_right @if($cont==$i) border_bottom @endif" style="height: 15px;"></td>
                        </tr>

                    @endfor

                @endif
            </tbody>

            <tfoot>
                <tr class="foot">
                    <td colspan="4" class="smalltd border_left">{{$factura->facnotas}}</td>
                    <td class="right">SubTotal</td>
                    <td class="right padding-right border_right">
                        {{Auth::user()->empresa()->moneda}}{{App\Funcion::Parsear($factura->total()->subtotal)}}
                    </td>
                </tr>

                @if($factura->total()->descuento>0)
                <tr class="foot">
                    <td colspan="4" class="smalltd border_left"></td>
                    <td class="right">Descuento</td>
                    <td class="right padding-right border_right">
                        {{Auth::user()->empresa()->moneda}}{{App\Funcion::Parsear($factura->total()->descuento)}}
                    </td>
                </tr>

                <tr class="foot">
                    <td colspan="4" class="smalltd border_left"></td>
                    <td class="right">SubTotal</td>
                    <td class="right padding-right border_right">
                        {{Auth::user()->empresa()->moneda}}{{App\Funcion::Parsear($factura->total()->resul)}}
                    </td>
                </tr>
                @endif

                @if($factura->total()->imp)
                    @foreach($factura->total()->imp as $imp)
                        @if(isset($imp->total))
                        <tr class="foot">
                            <td colspan="4" class="smalltd border_left"></td>
                            <td class="right">{{$imp->nombre}} ({{$imp->porcentaje}}%)</td>
                            <td class="right padding-right border_right">
                                {{Auth::user()->empresa()->moneda}}{{App\Funcion::Parsear($imp->total)}}
                            </td>
                        </tr>
                        @endif
                    @endforeach
                @endif

                @foreach($retenciones as $retencion)
                <tr class="foot">
                    <td colspan="4" class="smalltd border_left"></td>
                    <td class="right">{{$retencion->retencion()->nombre}} ({{$retencion->retencion()->porcentaje}}%)</td>
                    <td class="right padding-right border_right">
                        {{Auth::user()->empresa()->moneda}}{{App\Funcion::Parsear($retencion->valor)}}
                    </td>
                </tr>
                @endforeach

                <tr class="foot">
                    <td colspan="4" class="border_left border_bottom"></td>
                    <td class="right border_bottom"><b>Total</b></td>
                    <td class="right padding-right border_right border_bottom">
                        <b>{{Auth::user()->empresa()->moneda}}{{App\Funcion::Parsear($factura->total()->total)}}</b>
                    </td>
                </tr>
            </tfoot>
        </table>
    <br>
    <br>
        @if(isset($codqr))
    <p style="font-size:7px;margin-top:-20px;"><strong>cufe: </strong>{{$CUFEvr}}</p>
    @endif
    </div>
    <div style="width: 70%; margin-top: 1%">

        @if($factura->term_cond != "")
        <p style="text-align: justify;" class="small">{{$factura->term_cond}}</p>
        @else
        <p style="text-align: justify;" class="small">{{$resolucion->resolucion}}</p>
        @endif

        @if(isset($codqr))
        <div>
            <img src="data:image/png;base64, {!! base64_encode(QrCode::format('png')->size(200)->generate($codqr)) !!} ">
        </div>
        @endif
        <div style="padding-top: 8%; text-align: center;">
            <div style="display: inline-block; width: 45%; border-top: 1px solid #000;     margin-right: 10%;">
                <p class="small"> ELABORADO POR: {{$factura->vendedor()}}</p>
            </div>
            <div style="display: inline-block; width: 44%; border-top: 1px solid #000;">
                <p class="small"> ACEPTADA, FIRMA Y/O SELLO Y FECHA</p>
            </div>
        </div>
    </div>


    <div id="watermark">{{$factura->estatus==2?'ANULADA':''}}</div>
@endsection
