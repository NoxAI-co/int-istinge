@extends('layouts.pdf')

@section('content')
{{--
    Estado de cuenta del cliente.

    Esta plantilla la ve el CLIENTE: es la que se imprime y la que se le manda por
    WhatsApp. A propósito NO lleva desconexiones ni notas crédito: mandarle a
    alguien el conteo de sus cortes sin contexto es una invitación al reclamo, y
    esa información se queda en la pantalla interna.
--}}
@php
    $r = $resumen;
    $m = $moneda ?? '$';
    $money = fn ($v) => $m.' '.number_format((float) $v, 0, ',', '.');
    $fecha = fn ($v) => $v ? date('d/m/Y', strtotime((string) $v)) : '—';
@endphp

<style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 10px; color: #1a1a1a; margin: 0; }
    .cab { width: 100%; border-bottom: 2px solid {{ $color ?? '#334155' }}; padding-bottom: 8px; margin-bottom: 12px; }
    .cab td { vertical-align: top; }
    .empresa { font-size: 15px; font-weight: bold; color: {{ $color ?? '#334155' }}; }
    .sub { font-size: 9px; color: #555; }
    .doc { text-align: right; }
    .doc .tit { font-size: 13px; font-weight: bold; letter-spacing: 1px; }

    .bloque { margin-bottom: 12px; }
    .bloque .lbl { font-size: 8px; text-transform: uppercase; letter-spacing: .8px; color: #666; }

    table.datos { width: 100%; border-collapse: collapse; }
    table.datos td { padding: 2px 0; font-size: 10px; }
    table.datos td.k { color: #666; width: 90px; }

    table.cifras { width: 100%; border-collapse: separate; border-spacing: 5px 0; margin-bottom: 14px; }
    table.cifras td { border: 1px solid #d5d9de; padding: 7px 8px; width: 25%; }
    table.cifras .lbl { font-size: 7.5px; text-transform: uppercase; letter-spacing: .7px; color: #666; }
    table.cifras .val { font-size: 13px; font-weight: bold; padding-top: 2px; }
    .deuda { color: #b91c1c; }
    .bien  { color: #15803d; }

    table.det { width: 100%; border-collapse: collapse; margin-top: 4px; }
    table.det th { background: {{ $color ?? '#334155' }}; color: #fff; font-size: 8.5px; text-transform: uppercase;
                   letter-spacing: .5px; padding: 5px 6px; text-align: left; }
    table.det td { border-bottom: 1px solid #e3e6ea; padding: 5px 6px; font-size: 9px; }
    table.det td.num { text-align: right; }
    table.det tr.tot td { border-top: 1.5px solid #333; border-bottom: none; font-weight: bold; padding-top: 6px; }

    h3 { font-size: 11px; margin: 14px 0 2px; padding-bottom: 3px; border-bottom: 1px solid #d5d9de; }
    .pie { margin-top: 18px; padding-top: 6px; border-top: 1px solid #d5d9de; font-size: 8px; color: #666; text-align: center; }
    .vacio { padding: 8px 0; font-size: 9px; color: #777; }
</style>

<table class="cab">
    <tr>
        <td>
            @if(! empty($logo))
                <img src="{{ $logo }}" style="max-height: 46px; max-width: 170px;">
            @else
                <div class="empresa">{{ $empresa->nombre ?? '' }}</div>
            @endif
            <div class="sub">
                {{ $empresa->nombre ?? '' }}@if(! empty($empresa->nit)) · NIT {{ $empresa->nit }}@endif<br>
                {{ $empresa->direccion ?? '' }}@if(! empty($empresa->telefono)) · Tel. {{ $empresa->telefono }}@endif
            </div>
        </td>
        <td class="doc">
            <div class="tit">ESTADO DE CUENTA</div>
            <div class="sub">
                Generado el {{ date('d/m/Y') }}<br>
                Detalle del {{ $fecha($desde) }} al {{ $fecha($hasta) }}
            </div>
        </td>
    </tr>
</table>

<div class="bloque">
    <div class="lbl">Cliente</div>
    <table class="datos">
        <tr>
            <td class="k">Nombre</td><td><b>{{ $cliente['nombre'] }}</b></td>
            <td class="k">Identificación</td><td>{{ $cliente['identificacion'] }}</td>
        </tr>
        <tr>
            <td class="k">Dirección</td><td>{{ $cliente['direccion'] ?: '—' }}</td>
            <td class="k">Celular</td><td>{{ $cliente['celular'] ?: '—' }}</td>
        </tr>
    </table>
</div>

{{-- Las cuatro cifras que responden "cómo estoy con la empresa". --}}
<table class="cifras">
    <tr>
        <td>
            <div class="lbl">Saldo pendiente</div>
            <div class="val {{ $r['saldo'] > 1 ? 'deuda' : '' }}">{{ $money($r['saldo']) }}</div>
        </td>
        <td>
            <div class="lbl">Vencido</div>
            <div class="val {{ $r['saldo_vencido'] > 1 ? 'deuda' : '' }}">{{ $money($r['saldo_vencido']) }}</div>
        </td>
        <td>
            <div class="lbl">Total pagado</div>
            <div class="val bien">{{ $money($r['pagado_historico']) }}</div>
        </td>
        <td>
            <div class="lbl">Saldo a favor</div>
            <div class="val {{ $r['saldo_favor'] > 1 ? 'bien' : '' }}">{{ $money($r['saldo_favor']) }}</div>
        </td>
    </tr>
</table>

<h3>Facturas</h3>
@if(count($facturas) === 0)
    <p class="vacio">No hay facturas en el período seleccionado.</p>
@else
    <table class="det">
        <thead>
            <tr>
                <th>Factura</th><th>Fecha</th><th>Vence</th><th>Estado</th><th>Pagada el</th>
                <th style="text-align:right">Total</th><th style="text-align:right">Pagado</th><th style="text-align:right">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @foreach($facturas as $f)
                <tr>
                    <td><b>{{ $f['codigo'] }}</b></td>
                    <td>{{ $fecha($f['fecha']) }}</td>
                    <td>{{ $fecha($f['vencimiento']) }}</td>
                    <td>{{ $f['estado'] }}</td>
                    <td>{{ $fecha($f['fecha_pago']) }}</td>
                    <td class="num">{{ $money($f['total']) }}</td>
                    <td class="num">{{ $money($f['pagado']) }}</td>
                    <td class="num {{ $f['saldo'] > 1 ? 'deuda' : '' }}">{{ $money($f['saldo']) }}</td>
                </tr>
            @endforeach
            <tr class="tot">
                <td colspan="5">Totales del período</td>
                <td class="num">{{ $money(collect($facturas)->sum('total')) }}</td>
                <td class="num">{{ $money(collect($facturas)->sum('pagado')) }}</td>
                <td class="num">{{ $money(collect($facturas)->sum('saldo')) }}</td>
            </tr>
        </tbody>
    </table>
@endif

<h3>Pagos recibidos</h3>
@if(count($pagos) === 0)
    <p class="vacio">No se registraron pagos en el período seleccionado.</p>
@else
    <table class="det">
        <thead>
            <tr><th>Recibo</th><th>Fecha</th><th>Medio</th><th>Aplicado a</th><th style="text-align:right">Monto</th></tr>
        </thead>
        <tbody>
            @foreach($pagos as $p)
                <tr>
                    <td><b>{{ $p['nro'] }}</b></td>
                    <td>{{ $fecha($p['fecha']) }}</td>
                    <td>{{ $p['caja'] ?: '—' }}</td>
                    <td>{{ $p['facturas'] }}</td>
                    <td class="num bien">{{ $money($p['monto']) }}</td>
                </tr>
            @endforeach
            <tr class="tot">
                <td colspan="4">Total pagado en el período</td>
                <td class="num">{{ $money(collect($pagos)->sum('monto')) }}</td>
            </tr>
        </tbody>
    </table>
@endif

<h3>Servicios contratados</h3>
@if(count($contratos) === 0)
    <p class="vacio">Sin contratos registrados.</p>
@else
    <table class="det">
        <thead><tr><th>Contrato</th><th>Plan</th><th>Estado</th></tr></thead>
        <tbody>
            @foreach($contratos as $c)
                <tr>
                    <td><b>#{{ $c['nro'] }}</b></td>
                    <td>{{ $c['plan'] ?: '—' }}</td>
                    <td>{{ $c['estado'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="pie">
    Este documento es un resumen informativo de su cuenta a la fecha de generación y no constituye una factura.<br>
    Si ya realizó un pago que no aparece reflejado, comuníquese con nosotros.
</div>
@endsection
