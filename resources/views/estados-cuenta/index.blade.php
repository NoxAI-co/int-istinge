@extends('layouts.app')

{{--
    Estados de cuenta: la relación económica completa de UN cliente.

    Gemelo del módulo de integra2.0 y sobre la MISMA base de datos, así que las
    cifras tienen que coincidir peso por peso entre los dos sistemas. Ambos
    consumen EstadoCuentaClienteService.
--}}

@section('content')
@php
    $m = $moneda ?? '$';
    $money = function ($v) use ($m) { return $m.' '.number_format(round((float) $v), 0, ',', '.'); };
    $fdate = function ($v) { return $v ? date('d/m/Y', strtotime($v)) : '—'; };
    $r = $datos ? $datos['resumen'] : null;
@endphp

<style>
    .ec-kpi { border: 1px solid #e3e6ea; border-radius: 10px; padding: 14px 16px; background: #fff; height: 100%; }
    .ec-kpi .lbl { font-size: 10px; text-transform: uppercase; letter-spacing: .8px; color: #8a94a6; font-weight: 700; }
    .ec-kpi .val { font-size: 22px; font-weight: 700; margin-top: 4px; }
    .ec-kpi .nota { font-size: 11px; color: #8a94a6; margin-top: 2px; }
    .ec-kpi.deuda  { border-color: #f3c2c2; background: #fdf6f6; }
    .ec-kpi.deuda .val { color: #c0392b; }
    .ec-kpi.bien   { border-color: #bfe3cf; background: #f5fbf7; }
    .ec-kpi.bien .val { color: #1e8449; }
    .ec-kpi.alerta { border-color: #f5dda6; background: #fffbf2; }
    .ec-kpi.alerta .val { color: #b9770e; }

    .ec-card { border: 1px solid #e3e6ea; border-radius: 10px; background: #fff; margin-bottom: 18px; }
    .ec-card > .hd { padding: 11px 16px; border-bottom: 1px solid #eef0f3; font-weight: 700; font-size: 13px; }
    .ec-card > .hd small { font-weight: 400; color: #8a94a6; margin-left: 6px; }
    .ec-card > .bd { padding: 14px 16px; }
    .ec-card > .bd.tight { padding: 0; }
    .ec-scroll { max-height: 330px; overflow-y: auto; }

    #ec-resultados { position: absolute; z-index: 1000; width: 100%; background: #fff;
        border: 1px solid #e3e6ea; border-radius: 8px; margin-top: 4px; max-height: 320px;
        overflow-y: auto; box-shadow: 0 8px 24px rgba(0,0,0,.10); display: none; }
    #ec-resultados .item { padding: 9px 14px; cursor: pointer; border-bottom: 1px solid #f2f4f7; }
    #ec-resultados .item:last-child { border-bottom: 0; }
    #ec-resultados .item:hover { background: #f6f8fb; }
    #ec-resultados .item .n { font-weight: 600; font-size: 13px; }
    #ec-resultados .item .s { font-size: 11px; color: #8a94a6; }

    .ec-badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; border: 1px solid; }
    .b-verde { color:#1e8449; border-color:#bfe3cf; background:#f5fbf7; }
    .b-rojo  { color:#c0392b; border-color:#f3c2c2; background:#fdf6f6; }
    .b-ambar { color:#b9770e; border-color:#f5dda6; background:#fffbf2; }
    .b-gris  { color:#5d6d7e; border-color:#d5dbe1; background:#f7f9fa; }
    .b-azul  { color:#2471a3; border-color:#bcd9ee; background:#f4f9fd; }
    .ec-vacio { padding: 26px 16px; text-align: center; color: #8a94a6; font-size: 13px; }
    table.ec-tabla { width: 100%; font-size: 13px; margin: 0; }
    table.ec-tabla thead th { background: #f7f9fb; font-size: 10.5px; text-transform: uppercase;
        letter-spacing: .5px; color: #8a94a6; padding: 8px 12px; border-bottom: 1px solid #eef0f3; }
    table.ec-tabla td { padding: 8px 12px; border-bottom: 1px solid #f2f4f7; }
    table.ec-tabla .num { text-align: right; }
</style>

<div class="row">
    <div class="col-md-12">
        <h4 class="mb-1"><i class="fas fa-wallet text-primary"></i> Estados de Cuenta</h4>
        <p class="text-muted" style="font-size:13px">Toda la relación económica de un cliente con la empresa</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

{{-- Buscador: consulta al servidor, no la lista completa de clientes --}}
<div class="row mb-3">
    <div class="col-md-12" style="position:relative">
        <input type="text" id="ec-buscar" class="form-control" autocomplete="off"
            placeholder="{{ $datos ? $datos['cliente']['nombre'].' — buscar otro cliente…' : 'Buscar por nombre, cédula, celular o número de contrato…' }}">
        <div id="ec-resultados"></div>
    </div>
</div>

@if(!$datos)
    <div class="ec-card">
        <div class="ec-vacio" style="padding:56px 16px">
            <i class="fas fa-users fa-2x d-block mb-2" style="opacity:.3"></i>
            <b>Busca un cliente para empezar</b><br>
            <span style="font-size:12px">Puedes buscarlo por nombre, cédula, celular o número de contrato.</span>
        </div>
    </div>
@else
    {{-- Ficha + acciones --}}
    <div class="ec-card">
        <div class="bd">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-1">{{ $datos['cliente']['nombre'] }}</h5>
                    <div class="text-muted" style="font-size:12px">
                        CC/NIT {{ $datos['cliente']['identificacion'] }}
                        @if($datos['cliente']['celular']) · {{ $datos['cliente']['celular'] }} @endif
                        @if($datos['cliente']['direccion']) · {{ $datos['cliente']['direccion'] }} @endif
                    </div>
                </div>
                <div class="col-md-6 text-right">
                    <a class="btn btn-sm btn-outline-secondary"
                       href="{{ route('estados-cuenta.pdf', $datos['cliente']['id']) }}?desde={{ $desde }}&hasta={{ $hasta }}">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                    <a class="btn btn-sm btn-outline-secondary"
                       href="{{ route('estados-cuenta.excel', $datos['cliente']['id']) }}?desde={{ $desde }}&hasta={{ $hasta }}">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                    <form method="POST" action="{{ route('estados-cuenta.whatsapp', $datos['cliente']['id']) }}"
                          style="display:inline" onsubmit="return confirm('¿Enviar el estado de cuenta al WhatsApp de {{ addslashes($datos['cliente']['nombre']) }}?')">
                        @csrf
                        <input type="hidden" name="desde" value="{{ $desde }}">
                        <input type="hidden" name="hasta" value="{{ $hasta }}">
                        <button type="submit" class="btn btn-sm btn-outline-success">
                            <i class="fab fa-whatsapp"></i> WhatsApp
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Período: acota SOLO el detalle --}}
    <div class="ec-card">
        <div class="bd">
            <form method="GET" action="{{ route('estados-cuenta.index') }}" class="form-inline">
                <input type="hidden" name="cliente" value="{{ $datos['cliente']['id'] }}">
                <span class="text-muted mr-2" style="font-size:12px">Detalle desde</span>
                <input type="date" name="desde" value="{{ $desde }}" class="form-control form-control-sm mr-2">
                <span class="text-muted mr-2" style="font-size:12px">hasta</span>
                <input type="date" name="hasta" value="{{ $hasta }}" class="form-control form-control-sm mr-2">
                <button class="btn btn-sm btn-primary">Aplicar</button>
                <span class="text-muted ml-3" style="font-size:11px">Las cifras de arriba son históricas y no cambian con estas fechas.</span>
            </form>
        </div>
    </div>

    {{-- Las cinco cifras --}}
    <div class="row mb-3">
        <div class="col-md col-6 mb-2">
            <div class="ec-kpi {{ $r['saldo'] > 1 ? 'deuda' : '' }}">
                <div class="lbl">Adeudado</div>
                <div class="val">{{ $money($r['saldo']) }}</div>
                <div class="nota">{{ $r['facturas_abiertas'] }} factura{{ $r['facturas_abiertas'] == 1 ? '' : 's' }} sin saldar</div>
            </div>
        </div>
        <div class="col-md col-6 mb-2">
            <div class="ec-kpi {{ $r['saldo_vencido'] > 1 ? 'alerta' : '' }}">
                <div class="lbl">Vencido</div>
                <div class="val">{{ $money($r['saldo_vencido']) }}</div>
                <div class="nota">{{ $r['dias_mora'] > 0 ? $r['dias_mora'].' días de mora' : 'Sin mora' }}</div>
            </div>
        </div>
        <div class="col-md col-6 mb-2">
            <div class="ec-kpi bien">
                <div class="lbl">Pagado histórico</div>
                <div class="val">{{ $money($r['pagado_historico']) }}</div>
                <div class="nota">{{ $r['ultimo_pago_fecha'] ? 'Último pago '.$fdate($r['ultimo_pago_fecha']) : 'Sin pagos registrados' }}</div>
            </div>
        </div>
        <div class="col-md col-6 mb-2">
            <div class="ec-kpi {{ $r['saldo_favor'] > 1 ? 'bien' : '' }}">
                <div class="lbl">Saldo a favor</div>
                <div class="val">{{ $money($r['saldo_favor']) }}</div>
                <div class="nota">{{ $r['saldo_favor'] > 1 ? 'Se aplica en la próxima factura' : 'Sin saldo disponible' }}</div>
            </div>
        </div>
        <div class="col-md col-6 mb-2">
            <div class="ec-kpi">
                <div class="lbl">Facturado</div>
                <div class="val">{{ $money($r['facturado']) }}</div>
                <div class="nota">{{ $r['facturas_total'] }} facturas · desde {{ $fdate($r['primera_factura']) }}</div>
            </div>
        </div>
    </div>

    {{-- Gráficas --}}
    <div class="row">
        <div class="col-md-8">
            <div class="ec-card">
                <div class="hd">Facturado vs. pagado <small>últimos 12 meses</small></div>
                <div class="bd"><canvas id="ec-serie" height="105"></canvas></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="ec-card">
                <div class="hd">Composición de lo facturado</div>
                <div class="bd"><canvas id="ec-comp" height="210"></canvas></div>
            </div>
        </div>
    </div>

    {{-- Facturas --}}
    <div class="ec-card">
        <div class="hd">Facturas del período <small>{{ count($datos['facturas']) }}</small></div>
        <div class="bd tight">
            @if(count($datos['facturas']) == 0)
                <div class="ec-vacio">Sin facturas en el período seleccionado.</div>
            @else
            <div class="ec-scroll">
                <table class="ec-tabla">
                    <thead><tr>
                        <th>Factura</th><th>Fecha</th><th>Vence</th><th>Estado</th><th>Pagada el</th>
                        <th class="num">Total</th><th class="num">Pagado</th><th class="num">Saldo</th>
                    </tr></thead>
                    <tbody>
                    @foreach($datos['facturas'] as $f)
                        @php
                            $e = strtolower($f['estado']);
                            $cls = strpos($e,'anulada')===0 ? 'b-rojo'
                                 : (strpos($e,'acreditada')===0 ? 'b-gris'
                                 : (strpos($e,'cerrada')===0 ? 'b-verde'
                                 : (strpos($e,'abonada')===0 ? 'b-azul' : 'b-ambar')));
                        @endphp
                        <tr>
                            <td><b>{{ $f['codigo'] }}</b></td>
                            <td class="text-muted">{{ $fdate($f['fecha']) }}</td>
                            <td class="{{ $f['vencida'] ? 'text-danger font-weight-bold' : 'text-muted' }}">{{ $fdate($f['vencimiento']) }}</td>
                            <td><span class="ec-badge {{ $cls }}">{{ $f['estado'] }}</span></td>
                            <td class="text-muted">{{ $fdate($f['fecha_pago']) }}</td>
                            <td class="num">{{ $money($f['total']) }}</td>
                            <td class="num text-success">{{ $money($f['pagado']) }}</td>
                            <td class="num {{ $f['saldo'] > 1 ? 'text-danger font-weight-bold' : 'text-muted' }}">{{ $money($f['saldo']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    {{-- Pagos · contratos · desconexiones --}}
    <div class="row">
        <div class="col-md-4">
            <div class="ec-card">
                <div class="hd">Pagos recibidos <small>{{ count($datos['pagos']) }}</small></div>
                <div class="bd tight">
                    @if(count($datos['pagos']) == 0)
                        <div class="ec-vacio">Sin pagos en el período.</div>
                    @else
                    <div class="ec-scroll"><table class="ec-tabla"><tbody>
                        @foreach($datos['pagos'] as $p)
                        <tr>
                            <td>
                                <b>{{ $fdate($p['fecha']) }}</b><br>
                                <span class="text-muted" style="font-size:11px">{{ $p['caja'] ?: 'Sin caja' }} · {{ $p['facturas'] }}</span>
                            </td>
                            <td class="num text-success font-weight-bold">{{ $money($p['monto']) }}</td>
                        </tr>
                        @endforeach
                    </tbody></table></div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="ec-card">
                <div class="hd">Contratos <small>{{ count($datos['contratos']) }}</small></div>
                <div class="bd tight">
                    @if(count($datos['contratos']) == 0)
                        <div class="ec-vacio">Sin contratos.</div>
                    @else
                    <div class="ec-scroll"><table class="ec-tabla"><tbody>
                        @foreach($datos['contratos'] as $c)
                        @php
                            $cc = $c['estado']=='Habilitado' ? 'b-verde'
                                : ($c['estado']=='Pausado' ? 'b-ambar'
                                : ($c['estado']=='Retirado' ? 'b-gris' : 'b-rojo'));
                        @endphp
                        <tr>
                            <td>
                                <b>#{{ $c['nro'] }}</b><br>
                                <span class="text-muted" style="font-size:11px">{{ $c['plan'] ?: 'Sin plan' }}@if($c['ip']) · {{ $c['ip'] }}@endif</span>
                            </td>
                            <td class="num"><span class="ec-badge {{ $cc }}">{{ $c['estado'] }}</span></td>
                        </tr>
                        @endforeach
                    </tbody></table></div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="ec-card">
                <div class="hd">Desconexiones <small>{{ count($datos['desconexiones']) }}</small></div>
                <div class="bd tight">
                    @if(count($datos['desconexiones']) == 0)
                        <div class="ec-vacio">Nunca se le ha cortado el servicio.</div>
                    @else
                    <div class="ec-scroll"><table class="ec-tabla"><tbody>
                        @foreach($datos['desconexiones'] as $d)
                        <tr>
                            <td class="text-muted">Contrato #{{ $d['contrato'] }}</td>
                            <td class="num">{{ $fdate($d['fecha']) }}</td>
                        </tr>
                        @endforeach
                    </tbody></table></div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
@endsection

@section('scripts')
{{-- En @section('scripts') y no en el contenido: el layout carga jQuery al final,
     así que un script en el cuerpo se ejecuta antes de que $ exista. --}}
<script>
$(function () {
    var $input = $('#ec-buscar'), $res = $('#ec-resultados'), t = null;

    $input.on('input', function () {
        var q = $.trim($(this).val());
        clearTimeout(t);
        if (q.length < 2) { $res.hide().empty(); return; }
        // 300 ms de respiro: hay empresas con miles de clientes y no tiene
        // sentido consultar en cada tecla.
        t = setTimeout(function () {
            $.getJSON('{{ route('estados-cuenta.buscar') }}', { q: q }, function (data) {
                $res.empty();
                if (!data || !data.length) {
                    $res.append('<div class="item text-muted">Ningún cliente coincide.</div>').show();
                    return;
                }
                $.each(data, function (i, c) {
                    $('<div class="item">')
                        .append($('<div class="n">').text(c.nombre))
                        .append($('<div class="s">').text(c.identificacion + (c.celular ? ' · ' + c.celular : '')))
                        .on('click', function () {
                            window.location = '{{ route('estados-cuenta.index') }}?cliente=' + c.id
                                + '&desde={{ $desde }}&hasta={{ $hasta }}';
                        })
                        .appendTo($res);
                });
                $res.show();
            });
        }, 300);
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('#ec-buscar, #ec-resultados').length) $res.hide();
    });

@if($datos)
    var serie = @json($datos['serie']);
    new Chart(document.getElementById('ec-serie'), {
        type: 'bar',
        data: {
            labels: serie.map(function (s) { return s.etiqueta; }),
            datasets: [
                { label: 'Facturado', data: serie.map(function (s) { return s.facturado; }), backgroundColor: '#6366f1' },
                { label: 'Pagado',    data: serie.map(function (s) { return s.pagado; }),    backgroundColor: '#10b981' }
            ]
        },
        options: { responsive: true, maintainAspectRatio: true,
            scales: { yAxes: [{ ticks: { beginAtZero: true } }] } }
    });

    var comp = [
        { l: 'Pagado',     v: {{ max(0, round($r['pagado'])) }},                   c: '#10b981' },
        { l: 'Acreditado', v: {{ max(0, round($r['notas_credito_aplicadas'])) }},  c: '#94a3b8' },
        { l: 'Pendiente',  v: {{ max(0, round($r['saldo'])) }},                    c: '#f43f5e' }
    ].filter(function (x) { return x.v > 0; });

    if (comp.length) {
        new Chart(document.getElementById('ec-comp'), {
            type: 'doughnut',
            data: {
                labels: comp.map(function (x) { return x.l; }),
                datasets: [{ data: comp.map(function (x) { return x.v; }),
                             backgroundColor: comp.map(function (x) { return x.c; }) }]
            },
            options: { responsive: true, maintainAspectRatio: true, legend: { position: 'bottom' } }
        });
    }
@endif
});
</script>
@endsection
