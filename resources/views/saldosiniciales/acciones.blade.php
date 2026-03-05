<a href="{{route('saldoinicial.show',$nro)}}" class="btn btn-outline-info btn-icons" title="Ver movimientos"><i class="far fa-sticky-note"></i></a>
<a href="{{route('saldoinicial.edit',$nro)}}" class="btn btn-outline-primary btn-icons" title="Editar"><i class="fas fa-edit"></i></a>
<form action="{{ route('saldoinicial.destroy',$nro) }}" method="post" class="delete_form" style="margin:  0;display: inline-block;" id="eliminar-comprobante{{$nro}}">
	@csrf
	<input name="_method" type="hidden" value="DELETE">
</form>
<button class="btn btn-outline-danger  btn-icons" type="button" title="Eliminar" onclick="confirmar('eliminar-comprobante{{$nro}}', '¿Está seguro de que desea eliminar el asiento contable?', 'Se borrará de forma permanente');"><i class="fas fa-times"></i></button>