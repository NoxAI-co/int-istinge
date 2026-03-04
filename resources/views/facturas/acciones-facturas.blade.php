@if(Auth::user()->rol==8)
    @if($factura->estatus==1)
        <a href="{{route('ingresos.create_id', ['cliente'=>$factura->cliente, 'factura'=>$factura->id])}}" class="btn btn-outline-primary btn-xl" title="Agregar pago"><i class="fas fa-money-bill"></i></a>
    @endif
    @if($empresa->tirilla && $factura->estatus==0)
        <a href="{{route('facturas.tirilla', ['id' => $factura->id, 'name'=> 'Factura No.'.$factura->id.'.pdf'])}}" target="_blank" class="btn btn-outline-warning btn-xl"title="Imprimir tirilla"><i class="fas fa-file-invoice"></i></a>
    @endif
@else
    <a href="{{route('facturas.show',$factura->id)}}" class="btn btn-outline-info btn-icons" title="Ver"><i class="far fa-eye"></i></a>
    <a href="{{route('facturas.imprimir',['id' => $factura->id, 'name'=> 'Factura No. '.$factura->codigo.'.pdf'])}}" target="_blank" class="btn btn-outline-primary btn-icons"title="Imprimir"><i class="fas fa-print"></i></a>
    @if($factura->estatus==0)
        <a href="{{route('facturas.tirilla', ['id' => $factura->id, 'name'=> 'Factura No.'.$factura->id.'.pdf'])}}" target="_blank" class="btn btn-outline-warning btn-icons"title="Imprimir tirilla"><i class="fas fa-file-invoice"></i></a>
    @endif
	@if($factura->estatus==1)
		<a href="{{route('ingresos.create_id', ['cliente'=>$factura->cliente, 'factura'=>$factura->id])}}" class="btn btn-outline-primary btn-icons" title="Agregar pago"><i class="fas fa-money-bill"></i></a>
        @if(isset($_SESSION['permisos']['775']))
            <a href="javascript:modificarPromesa('{{$factura->id}}')" class="btn btn-outline-danger btn-icons promesa" idfactura="{{$factura->id}}" title="Promesa de Pago">
                <i class="fas fa-calendar"></i>
            </a>
        @endif
	@endif
	@if(($factura->estatus==1 || (isset($factura->total()->total) && $factura->total()->total == 0)) && ($factura->emitida != 1) && isset($_SESSION['permisos']['43']))
        <a href="{{route('facturas.edit',$factura->id)}}"  class="btn btn-outline-primary btn-icons" title="Editar"><i class="fas fa-edit"></i></a>
    @endif
	<form action="{{ route('factura.anular',$factura->id) }}" method="POST" class="delete_form" style="display: none;" id="anular-factura{{$factura->id}}">
		{{ csrf_field() }}
	</form>
	@if(isset($_SESSION['permisos']['43']))
		@if($factura->estatus == 1)
			<button class="btn btn-outline-danger  btn-icons" type="button" title="Anular" onclick="confirmar('anular-factura{{$factura->id}}', '¿Está seguro de que desea anular la factura?', ' ');"><i class="fas fa-minus"></i></button>
		@elseif($factura->estatus==2)
	    	<button class="btn btn-outline-success  btn-icons" type="submit" title="Abrir" onclick="confirmar('anular-factura{{$factura->id}}', '¿Está seguro de que desea abrir la factura?', ' ');"><i class="fas fa-unlock-alt"></i></button>
		@endif
	@endif
	@if($factura->emailcliente)
		<a href="{{route('facturas.enviar',$factura->id)}}" class="btn btn-outline-success btn-icons" title="Enviar"><i class="far fa-envelope"></i></a>
	@endif
	@if($factura->celularcliente)
	    @if($factura->estatus==1)
           @if($factura->mensaje==0)
		        <a href="{{route('facturas.mensaje',$factura->id)}}" class="btn btn-outline-success btn-icons" title="Enviar SMS"><i class="fas fa-mobile-alt"></i></a>
            @else
                <a href="#" class="btn btn-danger btn-icons disabled" disabled title="SMS Enviado"><i class="fas fa-mobile-alt"></i></a>
	        @endif
	        <a href="{{route('facturas.whatsapp',$factura->id)}}" class="btn btn-outline-success btn-icons" title="Enviar Vía WhatsApp"><i class="fab fa-whatsapp"></i></a>
	    @endif
	@endif

    @if ($factura->emitida == 0 && $empresa->estado_dian == 1 && $factura->tipo == 2)
        @if($empresa->proveedor == 1 || $empresa->proveedor == null)
        <a href="#" class="btn btn-outline-primary btn-icons" title="Emitir Factura de venta. {{ $factura->codigo }}"
            onclick="validateDian({{ $factura->id }}, '{{ route('xml.factura', $factura->id) }}', '{{ $factura->codigo }}')">
            <i class="fas fa-sitemap"></i>
        </a>
        @elseif($empresa->proveedor == 2)
        <a href="#" class="btn btn-outline-primary btn-icons" title="Emitir Factura de venta {{ $factura->codigo }}"
            onclick="validateDian({{ $factura->id }}, '{{ route('json.dian-factura', $factura->id) }}', '{{ $factura->codigo }}')">
            <i class="fas fa-sitemap"></i>
        </a>
        @endif
    @endif
    @if($factura->emitida == 0 && isset($_SESSION['permisos']['43']))
        <a href="#" class="btn btn-outline-warning btn-icons" title="Editar Número de Factura"
            onclick="abrirModalEditarCodigoLista({{$factura->id}}, '{{$factura->codigo}}', {{$factura->numeracion}}); return false;">
            <i class="fas fa-hashtag"></i>
        </a>
    @endif
    @if(!isset($_SESSION['permisos']['857']))
	    <a href="{{route('facturas.showmovimiento',$factura->id)}}" class="btn btn-outline-info btn-icons" title="Ver movimientos"><i class="far fa-sticky-note"></i></a>
	@endif
    @if(($factura->tipo == 1 && isset($opciones_dian) && $factura->opciones_dian == 1) && !isset($_SESSION['permisos']['857']))
	    <a onclick="convertirElectronica('{{$factura->codigo}}','{{route('facturas.convertirelectronica',$factura->id)}}')" class="btn btn-outline-info btn-icons" title="Convertir a electrónica"><i class="fas fa-exchange-alt"></i></a>
	@endif

    @if($factura->api_key_siigo != null && ($factura->siigo_id == "" || $factura->siigo_id == null))
    <a
    {{-- href="{{route('siigo.create_invoice',$id)}}" --}}
    href="#"
    onclick="showModalSiigo({{$factura->id}},`{{$factura->codigo}}`,`{{$factura->fecha}}`,`{{$factura->nombrecliente}}`)"
    class="btn btn-outline-info btn-icons" title="Enviar a Siigo">
        <i class="fas fa-file-import"></i>
    </a>
    @endif
    {{-- COMENTADO POR QUE SOLO ES DE LINKGROUP --}}
    {{--
    <a href="#" onclick="showModalDescuento({{ $id }})" class="btn btn-outline-info btn-icons" title="Aplicar descuento">
        <i class="fas fa-percent"></i>
    </a>
    --}}
    {{-- COMENTADO POR QUE SOLO ES DE LINKGROUP --}}

@endif

<script>
	function convertirElectronica(codigo,url){
		Swal.fire({
        title: '¿Desea convertir la factura: ' + codigo + ' a electrónica?',
        text: "No podrás retroceder esta acción",
        type: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Si, convertir'
    }).then((result) => {
        if (result.value) {
            window.location.href = url;
        }
    })
	}
</script>
