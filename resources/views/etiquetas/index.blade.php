@extends('layouts.app')

@section('style')
<link rel="stylesheet" href="{{asset('css/jquery.minicolors.css')}}">
<style>

</style>
@endsection

@include('etiquetas.modals.create')
@include('etiquetas.modals.edit')

@section('boton')
<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modal-etiqueta">
    Crear nueva
</button>
@endsection

@section('content')
	@if(Session::has('success'))
		<div class="alert alert-success" >
			{{Session::get('success')}}
		</div>

		<script type="text/javascript">
			setTimeout(function(){
			    $('.alert').hide();
			    $('.active_table').attr('class', ' ');
			}, 5000);
		</script>
	@endif

	<style>
		.minicolors-theme-default {
			width: 100% !important;
			display: block !important;
		}
		.minicolors-theme-default .minicolors-input {
			width: 100% !important;
			height: auto !important;
			padding: 0.6rem 1rem 0.6rem 3rem !important;
			border-radius: 8px !important;
			border: 1px solid #ced4da !important;
			color: #495057 !important;
			font-size: 1rem !important;
		}
		.minicolors-theme-default .minicolors-swatch {
			top: 50% !important;
			transform: translateY(-50%) !important;
			left: 0.8rem !important;
			width: 26px !important;
			height: 26px !important;
			border-radius: 4px !important;
			border: none !important;
			box-shadow: 0 2px 5px rgba(0,0,0,0.1) !important;
		}
	</style>

	<div class="row card-description">
		<div class="col-md-12">
			<hr style="border-top: 1px solid #b00606; margin: .5rem 0rem;">

					<div class="table-responsive">
						<table class="table table-striped table-hover" id="example0" style="width: 100%; border: 1px solid #e9ecef;">
							<thead class="thead-dark">
								<tr>
					              <th>Nombre</th>
					              <th>Color</th>
					              <th>Creacion</th>
								  <th>Acciones</th>
					          </tr>
							</thead>
							<tbody id="data-etiquetas">
								@foreach($etiquetas as $etiqueta)
										<tr id="rw-{{ $etiqueta->id }}">
											<td class="align-middle font-weight-bold">{{$etiqueta->nombre}}</td>
											<td class="align-middle">
												<div style="display: flex; align-items: center;">
													<div style="width: 24px; height: 24px; border-radius: 50%; background-color: {{$etiqueta->color}}; box-shadow: 0 2px 5px rgba(0,0,0,0.15); margin-right: 10px; border: 1px solid rgba(0,0,0,0.05);"></div>
													<span style="color: #495057; font-weight: 500; font-family: monospace;">{{$etiqueta->color}}</span>
												</div>
											</td>
											<td class="align-middle">{{$etiqueta->created_at->format('d-m-Y')}}</td>
											<td class="align-middle">@include('etiquetas.acciones', $etiqueta)</td>
                                        </tr>
								@endforeach
							</tbody>
						</table>
					</div>
			
		</div>
	</div>

	<div class="modal fade" tabindex="-1" role="dialog" id="modal-eliminar-etiqueta">
		<div class="modal-dialog modal-dialog-centered" role="document">
			<div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
			<div class="modal-header pb-3" style="background-color: #f8f9fa; border-radius: 12px 12px 0 0; border-bottom: 2px solid #e9ecef; padding: 1.5rem;">
				<h5 class="modal-title font-weight-bold text-dark"><i class="fas fa-trash-alt mr-2 text-danger"></i>Eliminar etiqueta</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
				<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body" style="padding: 2rem 1.5rem;">
				<p class="mb-0 text-secondary" style="font-size: 1.05rem;">¿Está seguro que desea borrar esta etiqueta?</p>
                <div class="alert alert-warning mt-3 mb-0" style="border-radius: 8px;">
                    <i class="fas fa-exclamation-triangle mr-2"></i> Esta etiqueta tiene <strong><span id="count-r">0</span></strong> registros asociados que perderán esta asignación.
                </div>
			</div>
			<div class="modal-footer" style="background-color: #f8f9fa; border-top: none; border-radius: 0 0 12px 12px; padding: 1rem 1.5rem;">
                <button type="button" class="btn btn-secondary px-4" data-dismiss="modal" style="border-radius: 8px;">Cancelar</button>
				<button type="button" class="btn btn-danger px-4" id="btn-eliminar" onclick="destroyEtiqueta()" style="border-radius: 8px;"><i class="fas fa-trash-alt mr-1"></i> Eliminar</button>
			</div>
			</div>
		</div>
	</div>

@endsection

 @section('scripts')
<script src="{{ asset('js/jquery.minicolors.js') }}"></script>

<script>
$(function(){
		$('#color').minicolors();
		$('#edit-color').minicolors();
});

function destroyEtiqueta(id, facturas, confirm){

	$('#btn-eliminar').attr('onclick', `destroyEtiqueta(${id}, ${facturas}, true)`);

	if(confirm){
		$.get('{{URL::to('/')}}/empresa/etiqueta/eliminar/'+id, function(response){
			$('#rw-'+response).remove();
		});
		$('#btn-eliminar').attr('onclick', ' ');
		$('#modal-eliminar-etiqueta').modal('hide');
	}else{
		$('#modal-eliminar-etiqueta').modal('show');
		$('#count-r').html(facturas);
	}
}

</script>

@endsection

