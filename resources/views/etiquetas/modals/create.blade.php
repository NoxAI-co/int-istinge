

  <!-- Modal -->
  <div class="modal fade" id="modal-etiqueta" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
        <div class="modal-header pb-3" style="background-color: #f8f9fa; border-radius: 12px 12px 0 0; border-bottom: 2px solid #e9ecef; padding: 1.5rem;">
          <h5 class="modal-title font-weight-bold text-dark" id="exampleModalLongTitle"><i class="fas fa-tag mr-2 text-primary"></i>Crear nueva etiqueta</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body" style="padding: 2rem 1.5rem;">
            <form action="{{ route('etiqueta.store') }}" id="store-etiqueta">
                <div class="form-group mb-4">
                    <label for="nombre" class="form-label" style="font-weight: 600; color: #495057;">Nombre de la etiqueta <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" id="nombre" placeholder="Ej. Cliente VIP, Prospecto..." class="form-control" style="border-radius: 8px; padding: 0.6rem 1rem; color: #495057;">
                </div>
                <div class="form-group mb-0">
                    <label for="color" class="form-label" style="font-weight: 600; color: #495057;">Color representativo <span class="text-danger">*</span></label>
                    <div style="margin-top: 5px;">
                        <input type="text" name="color" id="color" placeholder="#000000" autocomplete="off" style="border-radius: 4px; border: 1px solid #ced4da; padding: 0.5rem 1rem; color: #495057;">
                    </div>
                    <small class="form-text" style="color: #6c757d; margin-top: 10px; font-size: 0.85rem;">Seleccione un color que represente esta etiqueta de forma visual en el sistema.</small>
                </div>
            </form>
        </div>
        <div class="modal-footer" style="background-color: #f8f9fa; border-top: none; border-radius: 0 0 12px 12px; padding: 1rem 1.5rem;">
          <button type="button" class="btn btn-secondary px-4" data-dismiss="modal" style="border-radius: 8px;">Cancelar</button>
          <button type="button" class="btn btn-primary px-4" onclick="guardarEtiqueta()" style="border-radius: 8px;"><i class="fas fa-save mr-1"></i> Guardar Etiqueta</button>
        </div>
      </div>
    </div>
  </div>


  <script>



    function guardarEtiqueta(){

        var _token = $('meta[name="csrf-token"]').attr('content');

        if(!(nombre = $('#nombre').val())){
            return '';
        }

        if(!(color = $('#color').val())){
            return '';
        }

        let data = {
            method: 'POST',
            _token: _token,
            nombre: nombre,
            color: color,
        }

        $.post($('#store-etiqueta').attr('action'), data, function(response){
           let etiqueta = response;
          $('#modal-etiqueta').modal('hide');
          $('#data-etiquetas').prepend(`
          <tr id="rw-${etiqueta.id}">
            <td class="align-middle font-weight-bold">${etiqueta.nombre}</td>
            <td class="align-middle">
                <div style="display: flex; align-items: center;">
                    <div style="width: 24px; height: 24px; border-radius: 50%; background-color: ${etiqueta.color}; box-shadow: 0 2px 5px rgba(0,0,0,0.15); margin-right: 10px; border: 1px solid rgba(0,0,0,0.05);"></div>
                    <span style="color: #495057; font-weight: 500; font-family: monospace;">${etiqueta.color}</span>
                </div>
            </td>
            <td class="align-middle">${etiqueta.fecha}</td>
            <td class="align-middle">${etiqueta.acciones}</td>
          </tr>
          `);

          $('#nombre').val('');
          $('#color').minicolors('value', '');

        });

    }

  </script>
