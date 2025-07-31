<div class="modal fade" id="ethernetModal" tabindex="-1" role="dialog" aria-labelledby="vlanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document" style="max-width: 800px">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Configure ethernet port <span id="ethernetName"></span></h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
  
        <div class="modal-body container">
          
          <div class="row mb-3 align-items-center">
            <label class="col-sm-2 col-form-label fw-bold">Status</label>
            <div class="col-sm-10">

                <input type="radio" class="btn-check" name="status" id="status1" value="enabled" autocomplete="off" checked>
                <label class="btn btn-outline-secondary me-2" for="status1">Enabled</label>
  
                <input type="radio" class="btn-check" name="status" id="status2" value="shutdown" autocomplete="off">
                <label class="btn btn-outline-secondary" for="status2">Port shutdown</label>

            </div>
          </div>
  
          <div class="row mb-3 align-items-center" id="options-mode">
            <label class="col-sm-2 col-form-label fw-bold">Mode</label>
            <div class="col-sm-10">
              <input type="radio" class="btn-check" name="mode" id="mode1" value="LAN" autocomplete="off">
              <label class="btn btn-outline-secondary me-2" for="mode1">LAN</label>
  
              <input type="radio" class="btn-check" name="mode" id="mode2" value="Access" autocomplete="off">
              <label class="btn btn-outline-secondary me-2" for="mode2">Access</label>
  
              <input type="radio" class="btn-check" name="mode" id="mode3" value="Hybrid" autocomplete="off">
              <label class="btn btn-outline-secondary me-2" for="mode3">Hybrid</label>
  
              <input type="radio" class="btn-check" name="mode" id="mode4" value="Trunk" autocomplete="off">
              <label class="btn btn-outline-secondary me-2" for="mode4">Trunk</label>
  
              <input type="radio" class="btn-check" name="mode" id="mode5" value="Transparent" autocomplete="off">
              <label class="btn btn-outline-secondary me-2" for="mode5">Transparent</label>
            </div>
          </div>

          <!-- VLAN-ID -->
        <div id="vlan-id-group" class="row mb-3 align-items-center">
            <label class="col-sm-2 col-form-label fw-bold">VLAN-ID</label>
            <div class="col-sm-10">
            <select id="vlan-id-select" class="form-control selectpicker" data-live-search="true" data-max="5">
                @foreach($details['service_ports'] as $servicePort)
                    <option value="{{ $servicePort['vlan'] }}">{{ $servicePort['vlan'] }}</option>
                @endforeach
            </select>
            </div>
        </div>
        
        <!-- Allowed VLANs list -->
        <div id="allowed-vlans-group" class="row mb-3 align-items-center">
            <label class="col-sm-2 col-form-label fw-bold">Allowed VLANs list</label>
            <div class="col-sm-10">
            <select id="allowed-vlans-select" class="form-control selectpicker" multiple data-live-search="true" data-max="5">
                @foreach($details['service_ports'] as $servicePort)
                    <option value="{{ $servicePort['vlan'] }}">{{ $servicePort['vlan'] }}</option>
                @endforeach
            </select>
            </div>
        </div>
        
        <!-- DHCP -->
        <div id="dhcp-group" class="row mb-3 align-items-center">
            <label class="col-sm-2 col-form-label fw-bold">DHCP</label>
            <div class="col-sm-10">
            <select id="dhcp-select" class="form-control selectpicker" data-live-search="true" data-max="5">
                <option value="No control">No control</option>
                <option value="From ISP">From ISP</option>
                <option value="From ONU">From ONU</option>
                <option value="Forbiden">Forbiden</option>
            </select>
            </div>
        </div>
  
  
          <div style="text-align:end">
            <button type="button" class="btn btn-secondary mt-3" data-dismiss="modal" aria-label="Cerrar">
                <i class="fas fa-times"></i>
                Cerrar
              </button>
              <button class="btn btn-primary mt-3" id="update-vlan-button" onclick="update_ethernet()">
                <i class="fas fa-check"></i>
                Actualizar
              </button>
          </div>
  
        </div>
      </div>
    </div>
  </div>
    