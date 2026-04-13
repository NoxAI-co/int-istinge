
<a href="{{ route('facturasp.showid', $id) }}" class="btn btn-outline-info btn-icons"
    title="Ver factura de proveedor {{ $nro }}">
    <i class="far fa-eye"></i>
</a>
@php
    $sw = 0;
    $empresa = Auth::user()->empresa;
    $empresaObj = Auth::user()->empresa();
    $ultimaFactura = App\Model\Gastos\FacturaProveedores::where('factura_proveedores.empresa', $empresa)
        ->orderby('nro', 'desc')
        ->first();
@endphp

@if ($estatus == 1)
    <a href="{{ route('facturasp.edit', $id) }}" class="btn btn-outline-primary btn-icons"
        title="Editar factura de proveedor {{ $nro }}">
        <i class="fas fa-edit"></i>
    </a>
    @php $sw = 1; @endphp
@endif

@if (($tipo == 1 && $estatus == 1) ||
    ($estatus == 5 && $tipo == 1) ||
    (config('app.entorno') == 2 && $empresa == 128))
    @if ($modo == 2)
        <a href="{{ route('pagos-remisiones-proveedores.create', ['cliente' => $proveedor, 'factura' => $id]) }}"
            class="btn btn-outline-primary btn-icons" title="Agregar pago a remision de proveedor {{ $nro }}"><i class="fas fa-money-bill"></i></a>
    @else
        <a href="{{ route('pagos.create_id', ['cliente' => $proveedor, 'factura' => $id]) }}"
            class="btn btn-outline-primary btn-icons" title="Agregar pago a factura de proveedor {{ $nro }}"><i class="fas fa-money-bill"></i></a>
    @endif

    <a href="{{ route('facturasp.imprimir.nombre', ['id' => $id, 'name' => 'Factura Proveedor No. ' . $nro . '.pdf']) }}"
        class="btn btn-outline-primary btn-icons d-none d-lg-inline-flex" title="Imprimir factura de proveedor {{ $nro }}" target="_blank"><i
            class="fas fa-print"></i></a>

    @if ($estatus == 1)
        @if ($ultimaFactura == null || ($ultimaFactura && $ultimaFactura->id == $id))
            <button class="btn btn-outline-danger  btn-icons" type="submit" title="Eliminar factura de proveedor {{ $nro }}"
                onclick="confirmar('eliminar-factura{{ $id }}', 'Estas seguro que deseas eliminar la factura de compra?', 'Se borrara de forma permanente');"><i
                    class="fas fa-times"></i></button>
        @endif
    @endif

    <form action="{{ route('facturasp.destroy', $id) }}" method="post" class="delete_form"
        style="margin:  0;display: inline-block;" id="eliminar-factura{{ $id }}">
        @csrf
        <input name="_method" type="hidden" value="DELETE">
    </form>
@else
    <a href="{{ route('facturasp.imprimir.nombre', ['id' => $id, 'name' => 'Factura Proveedor No. ' . $nro . '.pdf']) }}"
        class="btn btn-outline-primary btn-icons" title="Imprimir factura de proveedor {{ $nro }}"><i class="fas fa-print"></i></a>
@endif

@if ($emitida == 0 &&
    Auth::user()->empresaObj->estado_dian == 1 && $codigo_dian != null)

    @if($empresaObj->proveedor == 2)
    <a href="#" class="btn btn-outline-primary btn-icons" title="Emitir factura de proveedor... {{ $nro }}"
        onclick="validateDian({{ $id }}, '{{ route('json.dian-documento-soporte', $id) }}', '{{ $codigo_dian }}', {{ 0 }}, {{ 1 }})"><i
        class="fas fa-sitemap"></i>
    </a>

    @else
    <a href="#" class="btn btn-outline-primary btn-icons" title="Emitir factura de proveedor {{ $nro }}"
        onclick="validateDian({{ $id }}, '{{ route('xml.facturaproveedor', $id) }}', '{{ $codigo_dian }}', {{ 0 }}, {{ 1 }})"><i
        class="fas fa-sitemap"></i>
    </a>
    @endif

@endif

<form action="{{ route('facturap.anular', $id) }}" method="POST" class="delete_form" style="display: none;"
    id="anular-facturap{{ $id }}">
    @csrf
</form>
@if ($estatus == 1)
    <button class="btn btn-outline-danger  btn-icons" type="button" title="Anular factura de proveedor {{ $nro }}"
        onclick="confirmar('anular-facturap{{ $id }}', 'Estas seguro de que desea anular la factura?', ' ');"><i
            class="fas fa-minus"></i></button>
@elseif($estatus == 7)
    <button class="btn btn-outline-success  btn-icons" type="submit" title="Abrir factura de proveedor {{ $nro }}"
        onclick="confirmar('anular-facturap{{ $id }}', 'Estas seguro de que desea abrir la factura?', ' ');"><i
            class="fas fa-unlock-alt"></i></button>
@endif



<a href="{{route('facturasp.showmovimiento', $id)}}" class="btn btn-outline-info btn-icons" title="Ver movimientos"><i class="far fa-sticky-note"></i></a>
