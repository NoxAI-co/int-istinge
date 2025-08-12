<div class="modal fade" id="locationDetails" tabindex="-1" role="dialog" aria-labelledby="vlanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm" role="document" style="max-width: 500px">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Update location details <span id="ethernetName"></span></h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body container">

            <!-- OLT-ID -->
            <div id="olt-group" class="row mb-3 align-items-center">
                <label class="col-sm-2 col-form-label fw-bold">OLT</label>
                <div class="col-sm-10">
                <select id="olt-select" class="form-control selectpicker" data-live-search="true" data-max="5">
                </select>
                </div>
            </div>

            <!-- BOARD -->
            <div id="board-group" class="row mb-3 align-items-center">
                <label class="col-sm-2 col-form-label fw-bold">BOARD</label>
                <div class="col-sm-10">
                <select id="board-select" class="form-control selectpicker" data-live-search="true" data-max="5">
                </select>
                </div>
            </div>

            <!-- PORT -->
            <div id="port-group" class="row mb-3 align-items-center">
                <label class="col-sm-2 col-form-label fw-bold">PORT</label>
                <div class="col-sm-10">
                <select id="port-select" class="form-control selectpicker" data-live-search="true" data-max="5">
                </select>
                </div>
            </div>


            <div style="text-align:end">
                <button type="button" class="btn btn-secondary mt-3" data-dismiss="modal" aria-label="Cerrar">
                    <i class="fas fa-times"></i>
                    Cerrar
                </button>
                <button class="btn btn-primary mt-3" id="update-vlan-button" onclick="update_move_onu()">
                    <i class="fas fa-check"></i>
                    Actualizar
                </button>
            </div>

        </div>
      </div>
    </div>
  </div>
