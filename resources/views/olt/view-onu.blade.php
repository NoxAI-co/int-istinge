@extends('layouts.app')
<style>
    body {
        font-family: Arial, sans-serif;
        margin: 20px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }
    table, th, td {
        border: 1px solid #ddd;
    }
    th, td {
        padding: 10px;
        text-align: center;
    }
    th {
        background-color: #f2f2f2;
    }
    a {
        color: #0073ea;
        text-decoration: none;
    }
    a:hover {
        text-decoration: underline;
    }
    .buttons {
        margin-top: 20px;
        text-align: center;
    }
    .buttons button {
        margin: 5px;
        padding: 10px 20px;
        border: none;
        cursor: pointer;
        /* font-size: 14px; */
    }
    .btn-reboot, .btn-resync, .btn-restore, .btn-disable {
        background-color: #ffcc00;
        color: #333;
    }
    .btn-delete {
        background-color: #e74c3c;
        color: #fff;
    }

    .font-pair, .font-pair ul, .font-pair table{
        font-size:13px;
    }

    .xp7jhwk{
        padding-bottom: 2px !important;
    }

    /* Estilos para los ul li */
    .list-unstyled ul {
            list-style-type: none; /* Elimina los puntos de la lista */
            padding: 0;
            margin: 0;
        }

        .list-unstyled li {
            display: flex; /* Organiza cada elemento en fila */
            justify-content: space-between; /* Separa título y valor */
            padding: 4px 0; /* Espaciado entre filas */
            border-bottom: 1px solid #ddd; /* Línea divisoria opcional */
        }

        .list-unstyled li span.title {
            font-weight: bold; /* Hace los títulos en negrita */
            color: #333;
            flex: 0 0 30%; /* Asigna un 30% del espacio al título */
            text-align: right;
        }

        .list-unstyled li span.title-2 {
            font-weight: bold; /* Hace los títulos en negrita */
            color: #333;
            flex: 0 0 15%; /* Asigna un 30% del espacio al título */
            text-align: right;
        }


        .list-unstyled li span.value {
            color: #007bff; /* Color azul similar al ejemplo */
            flex: 1; /* El valor ocupa el espacio restante */
            margin-left: 10px;
        }

        pre{
            padding: 0px;
            font-size: 14px;
            line-height: 14px;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .value.strong{
            font-weight: bold;
            color: #333;
        }

</style>
@section('content')
<div class="mt-0 font-pair">
    <div class="card">
        <div class="card-header bg-primary text-white">
            ONU Status Overview
        </div>

        <div class="card-body container">
            <div class="row">
                <!-- OLT Details -->
                <div class="col-md-6">
                    <ul class="list-unstyled">
                        <li><span class="title">OLT:</span>
                            <span class="value strong"><a href="#" onclick="modalMoveOnu()">{{ $details['olt_name'] }}</a></span>
                        </li>
                        <li><span class="title">Board:</span>
                            <span class="value strong"><a href="#" onclick="modalMoveOnu()">{{ $details['board'] }}</a></span>
                        </li>
                        <li><span class="title">Port:</span>
                            <span class="value strong"><a href="#" onclick="modalMoveOnu()">{{ $details['port'] }}</a></span>
                        </li>
                        <li><span class="title">ONU:</span>
                            <span class="value">{{ $details['pon_type'] . "/" . $details['board'] . "/" .
                            $details['port'] . ":" . $details['onu'] }}</span>
                        </li>
                        <li><span class="title">SN:</span>
                            <span class="value"><a href="#">{{ $details['sn'] }}</a></span>
                        </li>
                        <li><span class="title">ONU Type:</span>
                            <span class="value"><a href="#">{{ $details['onu_type_name'] }}</a></span>
                        </li>
                        <li><span class="title">Zone:</span>
                            <span class="value strong"><a href="#" onclick="modalLocationDetails()">{{ $details['zone_name'] }}</a></span>
                        </li>
                        <li><span class="title">ODB (Splitter):</span>
                            <span class="value">None</span>
                        </li>
                        <li><span class="title">Name:</span>
                            <span class="value">{{ $details['name'] }}</span>
                        </li>
                        <li><span class="title">Address or Comment:</span>
                            <span class="value">{{ $details['address'] }}</span>
                        </li>
                        <li><span class="title">Contact:</span>
                            <span class="value">{{ $details['contact'] }}</span>
                        </li>
                        <li><span class="title">Authorization Date:</span>
                            {{-- <span class="value"><a href="#">History</a></span> --}}
                        </li>
                        <li><span class="title">ONU External ID:</span>
                            {{-- <span class="value"><a href="#">{{ $details['unique_external_id'] }}</a></span> --}}
                            <span class="value">{{ $details['unique_external_id'] }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Status and Signals -->
                <div class="col-md-6">
                    <img src="{{ $image_onu_type }}" alt="wifi modem" class="img-fluid mb-4">
                    <ul class="list-unstyled">
                        <li><span class="title">Status:</span>

                            <span class="value">
                                {{ $onuStatus['onu_status'] }}

                                @switch($onuStatus['onu_status'])
                                    @case('Online')
                                        <i class="fas fa-globe-americas" style="color:#4db14b"></i>
                                        @break
                                    @case('Power fail')
                                        <i class="fas fa-plug" style="color:#6e7175"></i>
                                        @break
                                    @case('LOS')
                                        <i class="fas fa-unlink" style="color:#ff0000"></i>
                                        @break
                                    @case('Offline')
                                        <i class="fas fa-globe-americas" style="color:gray"></i>
                                        @break
                                    @case('Admin Disabled')
                                        <i class="fas fa-ban" style="color:gray"></i>
                                        @break

                                    @default

                                @endswitch

                                ({{ $diferenciaHoras }})

                            </span>

                        </li>

                        <li><span class="title">ONU/OLT Rx Signal:</span>
                            <span class="value" style="display:inline-flex">
                                <span id="onu_signal_value">{{ $onlySignal['onu_signal_value'] }}</span>
                                @switch($onlySignal['onu_signal'])
                                    @case('Very good')
                                    @case('Good')
                                        <i class="fas fa-signal" style="color:#4db14b; margin-left:4px;margin-top:2px"></i>
                                        @break

                                        @case('Warning')
                                        <i class="fas fa-signal" style="color:darkorange; margin-left:4px;margin-top:2px"></i>
                                        @break

                                        @case('Critical')
                                        <i class="fas fa-signal" style="color:red; margin-left:4px;margin-top:2px"></i>
                                        @break
                                    @default

                                @endswitch

                                <span id="distance" style="margin-left:5px;margin-top:0px">
                                    <div id="preloader" style="display: block;">
                                        <img src="https://i.gifer.com/ZZ5H.gif" alt="Cargando..." style="width:10px;" />
                                    </div>
                                </span>
                            </span>
                        </li>
                        <li><span class="title">Attached VLANs:</span>
                            <span class="value">
                                <a href="#" onclick="openVlanModal('{{ $details['olt_id'] }}', {{ json_encode(array_column($details['service_ports'], 'vlan')) }})">
                                {{ $details['vlan'] }}
                                @foreach($details['service_ports'] as $servicePort)
                                    @if($servicePort['vlan'] != $details['vlan'])
                                        , {{ $servicePort['vlan'] }}
                                    @endif
                                @endforeach
                            </a></span>
                        </li>
                        <li><span class="title">ONU Mode:</span> <span class="value">{{ $details['mode'] }} - WAN vlan: {{ $details['vlan'] }}</span>
                        </li>
                        <li><span class="title">TR069:</span>
                            <span class="value">{{ $details['tr069_profile'] }}</span>
                        </li>
                        <li><span class="title">Mgmt IP:</span>
                            <span class="value">{{ $details['mgmt_ip_mode'] }}</span>
                        </li>
                        <li><span class="title">WAN Setup Mode:</span>
                            {{-- <span class="value"><a href="#">Setup via ONU webpage</a></span>  --}}
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Buttons -->
            <div class="row">
                <div class="col-md-12">
                    <ul class="list-unstyled">
                        <li>
                          <span class="title-2">Status:</span>
                          {{-- segunda version --}}
                          <span class="value">
                            {{-- <span class="badge badge-info ml-1">Prontamente!</span></a> --}}

                                <button class="btn btn-primary" onclick="getFullstatus()">Get Status
                                    <div id="preloader-status" style="display: none;width: 19px;">
                                        <img src="https://i.gifer.com/ZZ5H.gif" alt="Cargando..." style="width:18px;" />
                                    </div>
                                </button>
                                <button class="btn btn-primary" onclick="showRunningConfig()">Show Running-Config
                                    <div id="preloader-show-running" style="display: none;width: 19px;">
                                        <img src="https://i.gifer.com/ZZ5H.gif" alt="Cargando..." style="width:18px;" />
                                    </div>
                                </button>
                                {{-- <button class="btn btn-primary">SW Info</button>
                                <button class="btn btn-success">LIVE!</button> --}}

                                <div class="container" id="full-status-div" style="display:none;">

                                    <pre id="pre-text"></pre>
                                </div>

                                <div class="container" id="show-running-div" style="display:none;">

                                    <pre id="pre-text-running"></pre>
                                </div>
                           </span>

                        </li>
                    </ul>
                </div>
            </div>

            <!-- Traffic and Signal Charts -->
            <div class="mt-4">
                <h5>Traffic/Signal</h5>
                <div class="row">
                    <div class="col-md-6">
                        <img src="{{ $onu_traffic_graph }}" alt="Daily Traffic Graph" class="img-fluid">
                    </div>
                    <div class="col-md-6">
                        <img src="{{ $onu_signal_graph }}" alt="Weekly Signal Graph" class="img-fluid" >
                    </div>
                </div>
            </div>

            <div class="col-md-12">
            <ul class="list-unstyled mt-4">
                <li>
                    <span class="title-2">Speed Profiles:</span>
                    <span class="value">
                        <span class="badge badge-info ml-1">Prontamente!</span></a>
                        {{-- <table>
                            <thead>
                                <tr>
                                    <th>Service-port ID</th>
                                    <th>User-VLAN</th>
                                    <th>Download</th>
                                    <th>Upload</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{ 129 }}</td>
                                    <td>{{ 200 }}</td>
                                    <td>{{ "1G" }}</td>
                                    <td>{{ "1G" }}</td>
                                    <td><a href="#">+ Configure</a></td>
                                </tr>
                            </tbody>
                        </table> --}}
                    </span>
                </li>
            </ul>
        </div>


            <div class="col-md-12">
                <ul class="list-unstyled">
                    <li>
                        <span class="title-2">Ethernet Ports:</span>
                        <span class="value">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Port</th>
                                        <th>Admin State</th>
                                        <th>Mode</th>
                                        <th>DHCP</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($ethernetPorts as $port)
                                    <tr>
                                        <td>{{ $port['port'] }}</td>
                                        <td>{{ $port['admin_state'] }}</td>
                                        <td>
                                            @if($port['mode'] == "Access")
                                            {{ $port['mode'] }} VLAN: {{ $port['vlan'] }}
                                            @elseif($port['mode'] == "Hybrid")
                                            {{$port['mode']}}: Def-VLAN {{$port['vlan']}} + TAG {{$port['allowed_vlans']}}
                                            @elseif ($port['mode'] == "Trunk")
                                            {{$port['mode']}} VLANs: {{$port['allowed_vlans']}}
                                            @else
                                            {{ $port['mode'] }}
                                            @endif
                                        </td>
                                        <td>{{ $port['dhcp'] }}</td>
                                        <td>
                                            {{-- <span class="badge badge-info ml-1">Prontamente!</span></a> --}}
                                            <a href="#" onclick="
                                            openModalEthernet(
                                                                `{{$port['port']}}`,
                                                                `{{$port['admin_state']}}`,
                                                                `{{$port['mode']}}`,
                                                                `{{$port['dhcp']}}`
                                                            )"
                                            >+ Configure</a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </span>
                    </li>

                    <li>
                        <span class="title-2">VoIP Service:</span>
                        <span class="value">Disabled</span>
                    </li>
                    <li>
                        <span class="title-2">IPTV:</span>
                        <span class="value">Inactive</span>
                    </li>
                    <li>
                        <span class="title-2">CATV:</span>
                        <span class="value">Not supported by ONU-Type.</span>
                    </li>
                        {{-- <h5>VoIP Service: <span style="color: #0073ea;">Disabled</span></h5>
                        <h5>IPTV: <span style="color: #0073ea;">Inactive</span></h5>
                        <h5>CATV: <em>Not supported by ONU-Type.</em></h5> --}}

                    </li>
                </ul>
            </div>

        <div class="col-md-12">
            <ul class="list-unstyled">
                <li>
                    <span class="title-2"></span>
                    <span class="value">
                        <div class="buttons text-left">
                            <button class="btn-reboot" onclick="reboot_onu(`{{ $details['sn'] }}`)">Reboot</button>
                            <button class="btn-resync" onclick="resync_config(`{{ $details['sn'] }}`)">Resync config</button>
                            <button class="btn-restore" onclick="restore_factory_defaults(`{{ $details['sn'] }}`)">Restore defaults</button>
                            @if($onuStatus['onu_status'] == "Offline")
                            <button class="btn-disable" onclick="enable_onu(`{{ $details['sn'] }}`)">Enable ONU</button>
                            @else
                            <button class="btn-disable" onclick="disable_onu(`{{ $details['sn'] }}`)">Disable ONU</button>
                            @endif
                            <button class="btn-delete" onclick="delete_onu(`{{$details['sn']}}`,`{{$details['olt_id']}}`)">Delete</button>
                        </div>
                    </span>
                </li>
            </ul>
        </div>

        </div>
    </div>
</div>

{{-- Modal que se llenara siempre --}}
<div class="modal fade" id="vlanModal" tabindex="-1" role="dialog" aria-labelledby="vlanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Detalles de VLAN</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <select id="vlan-select" class="form-control selectpicker" data-live-search="true"
          multiple data-size="5" title="Selecicone Vlan" onchange="">
            <option>Cargando...</option>
          </select>

          <span>
            <p class="mt-2">Select from the list all the VLANs that will be used on this ONU.
                After changing the VLANs list, go to the Ethernet ports settings and assign VLANs as desired.</p>
          </span>

          <div style="text-align:end">
            <button type="button" class="btn btn-secondary mt-3" data-dismiss="modal" aria-label="Cerrar">
                <i class="fas fa-times"></i>
                Cerrar
              </button>
              <button class="btn btn-primary mt-3" id="update-vlan-button" onclick="update_vlan()">
                <i class="fas fa-check"></i>
                Actualizar
              </button>
          </div>

        </div>
      </div>
    </div>
  </div>

  @include('olt.modals.ethernet-port')
  @include('olt.modals.move-onu')
  @include('olt.modals.location-details')

@endsection

@section('scripts')
<script>

    let initialVlans = [];     // VLANs originales
    let add_vlans = [];        // VLANs añadidas
    let remove_vlans = [];     // VLANs eliminadas

    // FUNCIONES VLAN ATACCHED.
    function openVlanModal(oltId, selectedVlans = []) {
        initialVlans = [...selectedVlans]; // Guardamos copia original
        add_vlans = [];
        remove_vlans = [];

        const url = window.location.pathname.split("/")[1] === "software"
            ? '/software/Olt/vlan-oltid'
            : '/Olt/vlan-oltid';

        $.ajax({
            url: url + "/" + oltId,
            method: 'GET',
            success: function(response) {
                const select = $('#vlan-select');
                select.empty();
                const data = response.response;

                data.forEach(function(item) {
                    const name = item.vlan + (item.description ? " - " + item.description : "");
                    const isSelected = selectedVlans.includes(item.vlan);
                    select.append(new Option(name, item.vlan, isSelected, isSelected));
                });

                select.selectpicker('refresh');
                $('#vlanModal').modal('show');
            },
            error: function() {
                alert('Error al cargar datos');
            }
        });
    }

    $('#vlan-select').on('changed.bs.select', function (e, clickedIndex, isSelected, previousValue) {
        const currentVlans = $(this).val() || []; // siempre un array

        add_vlans = currentVlans.filter(vlan => !initialVlans.includes(vlan));
        remove_vlans = initialVlans.filter(vlan => !currentVlans.includes(vlan));
    });

    function update_vlan(){

        const sn = "{{ $details['sn'] }}"; // obtener desde Blade

        const url = window.location.pathname.split("/")[1] === "software"
            ? '/software/Olt/update-vlan'
            : '/Olt/update-vlan';

        msg_procesando();

        $.ajax({
            url: url,
            method: 'post',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: {
                sn: sn,
                add_vlans: add_vlans,
                remove_vlans: remove_vlans
            },
            success: function(response) {

                if(response.status == true){
                    Swal.fire({
                        title: 'VLANs actualizadas correctamente!',
                        type: 'success',
                        showConfirmButton: false,
                        timer: 1500
                    });
                    $('#vlanModal').modal('hide');
                    location.reload(); // Recargar la página para reflejar los cambios
                } else {
                    Swal.close();
                    alert("Hubo un error comuniquese con soporte.");
                }
            }
        });
    }
    // FUNCIONES VLAN ATACCHED.

    // FUNCIONES ETHERNET PORTS
    function openModalEthernet(name, adminState, mode, dhcp) {

        console.log(name);
        console.log(adminState);
        console.log(mode);
        console.log(dhcp);

        $("#ethernetName").text(name)

        $("#ethernetModal").modal('show');

    }
    // FUNCIONES ETHERNET PORTS


    $(document).ready(function() {
        // Función que realiza la petición AJAX cada 30 segundos
        function refreshDistance() {

            let sn = `{{ $details['sn'] }}`
            if (window.location.pathname.split("/")[1] === "software") {
            var url='/software/Olt/get-full-status/' + sn;
            }else{
                var url = '/Olt/get-full-status/' + sn;
            }

            $.ajax({
                url: url,
                type: 'GET',
                dataType: 'json',
                beforeSend: function() {
                    $('#preloader').show();
                    // $('#distance').hide();
                },
                success: function(response) {

                    let rx = "undefined";
                    let ry = "undefined";
                    let distance = "-";

                    if(response && response['full_status_json']['ONU details']['ONT distance(m)']){
                        distance = response['full_status_json']['ONU details']['ONT distance(m)'];
                    }

                    if(response && response['full_status_json']['Optical status']['Rx optical power(dBm)']){
                        rx = response['full_status_json']['Optical status']['Rx optical power(dBm)'];
                        ry = response['full_status_json']['Optical status']['OLT Rx ONT optical power(dBm)'];
                    }

                    if(response && response['full_status_json']['ONU details']['ONU Distance']){
                        distance = response['full_status_json']['ONU details']['ONU Distance'];
                    }

                    if(response && response['full_status_json']['Optical status']['ONU Rx']){
                        rx = response['full_status_json']['Optical status']['ONU Rx'];
                        ry = response['full_status_json']['Optical status']['OLT Rx'];
                    }

                    $('#distance').text("(" + distance + "m)");
                    $('#onu_signal_value').text(rx + " / " + ry);

                },
                error: function(xhr, status, error) {
                    $('#distance').text('Error al obtener datos');
                },
                complete: function() {
                    $('#preloader').hide();
                    $('#distance').show();
                }
            });
        }

        setTimeout(function() {
            refreshDistance();  // Primer refresco después de 2 segundos
        }, 2000); // 2000 ms = 2 segundos

        setInterval(refreshDistance, 30000);
    });

    function getFullstatus(){

        let sn = `{{ $details['sn'] }}`
        if (window.location.pathname.split("/")[1] === "software") {
            var url='/software/Olt/get-full-status/' + sn;
        }else{
            var url = '/Olt/get-full-status/' + sn;
        }

        $.ajax({
                url: url,
                type: 'GET',
                dataType: 'json',
                beforeSend: function() {

                    $("#full-status-div").show();
                    $("#preloader-status").css('display','inline');
                    $('#preloader-status').show();

                },
                success: function(response) {

                    var text = response.full_status_info;
                    var formattedText = text.replace(/\n/g, "<br>");
                    $("#pre-text").html(formattedText);

                },
                error: function(xhr, status, error) {
                    $('#full-status-div').text('Error al obtener datos');
                },
                complete: function() {
                    $('#preloader-status').hide();
                }
            });
    }

    function showRunningConfig(){
        let sn = `{{ $details['sn'] }}`
        if (window.location.pathname.split("/")[1] === "software") {
            var url='/software/Olt/show-running-config/' + sn;
        }else{
            var url = '/Olt/show-running-config/' + sn;
        }

        $.ajax({
                url: url,
                type: 'GET',
                dataType: 'json',
                beforeSend: function() {

                    // $("#full-status-div").style('display','block');
                    $("#show-running-div").show();
                    $('#preloader-show-running').show();
                    $("#preloader-show-running").css('display','inline')

                },
                success: function(response) {
                    var text = response.running_config;
                    var formattedText = text.replace(/\n/g, "<br>");
                    $("#pre-text-running").html(formattedText);
                },
                error: function(xhr, status, error) {
                    $('#show-running-div').text('Error al obtener datos');
                },
                complete: function() {
                    $('#preloader-show-running').hide();
                }
            });

    }

    function reboot_onu(sn){
        if (window.location.pathname.split("/")[1] === "software") {
            var url='/software/Olt/reboot-onu';
        }else{
            var url = '/Olt/reboot-onu';
        }

        Swal.fire({
        title: '¿Reiniciar dispositivo?',
        text: "Se reiniciará este dispositivo",
        type: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Si, reiniciar'
        }).then((result) => {
            if (result.value) {

                //procesando solicitud
                msg_procesando();

            $.ajax({
                url: url,
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                method: 'post',
                data: {sn},
                success: function (data) {
                    if(data.status == 200){
                        Swal.fire({
                            title: 'Dispositivo reiniciado correctamente!',
                            type: 'success',
                            showConfirmButton: false,
                            allowOutsideClick: false,
                        });
                        let url = `{{ route('olt.view-onu') }}?sn=${sn}`;
                        window.location.href = url;
                    }else{
                        Swal.close();
                        alert("Hubo un error comuniquese con soporte.")
                    }
                }
            });
            }
        })
    }

    function resync_config(sn){
        if (window.location.pathname.split("/")[1] === "software") {
            var url='/software/Olt/resync-config-onu';
        }else{
            var url = '/Olt/resync-config-onu';
        }

        Swal.fire({
        title: '¿Resincronizar la onu?',
        text: "La ONU será resincronizada",
        type: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Si, resincronizar'
        }).then((result) => {
            if (result.value) {

                msg_procesando();

            $.ajax({
                url: url,
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                method: 'post',
                data: {sn},
                success: function (data) {
                    if(data.status == 200){
                        Swal.fire({
                            title: 'ONU resincronizada correctamente!',
                            type: 'success',
                            showConfirmButton: false,
                            allowOutsideClick: false,
                        });
                        let url = `{{ route('olt.view-onu') }}?sn=${sn}`;
                        window.location.href = url;
                    }else{
                        Swal.close();
                        alert("Hubo un error comuniquese con soporte.")
                    }
                }
            });
            }
        })
    }

    function restore_factory_defaults(sn){
        if (window.location.pathname.split("/")[1] === "software") {
            var url='/software/Olt/restore-defaults';
        }else{
            var url = '/Olt/restore-defaults';
        }

        Swal.fire({
        title: '¿Restaurar a los valores predeterminados de fábrica?',
        text: "Esto borrará la configuración realizada por el usuario, incluido el SSID y la contraseña de WiFi, el reenvío de puertos, etc. Después de restaurar el estado predeterminado, la ONU aplicará automáticamente la configuración configurada en esta aplicación.",
        type: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Si, restaurar de fábrica'
        }).then((result) => {
            if (result.value) {

                msg_procesando();

            $.ajax({
                url: url,
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                method: 'post',
                data: {sn},
                success: function (data) {
                    if(data.status == 200){
                        Swal.fire({
                            title: 'ONU restaurada correctamente!',
                            type: 'success',
                            showConfirmButton: false,
                            allowOutsideClick: false,
                        });
                        let url = `{{ route('olt.view-onu') }}?sn=${sn}`;
                        window.location.href = url;
                    }else{
                        Swal.close();
                        alert("Hubo un error comuniquese con soporte.")
                    }
                }
            });
            }
        })
    }

    function disable_onu(sn){
        if (window.location.pathname.split("/")[1] === "software") {
            var url='/software/Olt/disable-onu';
        }else{
            var url = '/Olt/disable-onu';
        }

        Swal.fire({
        title: '¿Desactivar ONU?',
        text: "Esto cerrará administrativamente todos los servicios en esta ONU. ¿Continuar?",
        type: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Si, desactivar ONU'
        }).then((result) => {
            if (result.value) {

                msg_procesando();

            $.ajax({
                url: url,
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                method: 'post',
                data: {sn},
                success: function (data) {
                    if(data.status == 200){
                        Swal.fire({
                            title: 'ONU desactivada correctamente!',
                            type: 'success',
                            showConfirmButton: false,
                            allowOutsideClick: false,
                        });
                        let url = `{{ route('olt.view-onu') }}?sn=${sn}`;
                        window.location.href = url;
                    }else{
                        Swal.close();
                        alert("Hubo un error comuniquese con soporte.")
                    }
                }
            });
            }
        })
    }

    function enable_onu(sn){
        if (window.location.pathname.split("/")[1] === "software") {
            var url='/software/Olt/enable-onu';
        }else{
            var url = '/Olt/disable-onu';
        }

        Swal.fire({
        title: 'Activar ONU?',
        text: "Esto habilitará los servicios en esta ONU. ¿Continuar?",
        type: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Si, activar ONU'
        }).then((result) => {
            if (result.value) {

                msg_procesando();

            $.ajax({
                url: url,
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                method: 'post',
                data: {sn},
                success: function (data) {
                    if(data.status == 200){
                        Swal.fire({
                            title: 'ONU activada correctamente!',
                            type: 'success',
                            showConfirmButton: false,
                            allowOutsideClick: false,
                        });
                        let url = `{{ route('olt.view-onu') }}?sn=${sn}`;
                        window.location.href = url;
                    }else{
                        Swal.close();
                        alert("Hubo un error comuniquese con soporte.")
                    }
                }
            });
            }
        })
    }

    function delete_onu(sn, olt_id){
        if (window.location.pathname.split("/")[1] === "software") {
            var url='/software/Olt/delete-onu';
        }else{
            var url = '/Olt/delete-onu';
        }

        Swal.fire({
        title: '¿Eliminar ONU?',
        text: "¿Estás seguro de que quieres eliminar este dispositivo?",
        type: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Si, eliminar ONU'
        }).then((result) => {
            if (result.value) {

                msg_procesando();

            $.ajax({
                url: url,
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                method: 'post',
                data: {sn},
                success: function (data) {
                    if(data.status == 200){
                        Swal.fire({
                            title: 'ONU eliminada correctamente!',
                            type: 'success',
                            showConfirmButton: false,
                            allowOutsideClick: false,
                        });
                        let url = `{{ route('olt.unconfigured') }}?olt=${olt_id}`;
                        window.location.href = url;
                    }else{
                        Swal.close();
                        alert("Hubo un error comuniquese con soporte.")
                    }
                }
            });
            }
        })
    }

    function msg_procesando(){
        Swal.fire({
            title: 'Cargando...',
            text: 'Por favor espera mientras se procesa la solicitud.',
            type: 'info',
            showConfirmButton: false,
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading(); // Muestra el preloader de carga
            }
        });
    }

 </script>

{{-- Scripts de Ethernet ports --}}
<script>
    function toggleFields() {
        const status = $('input[name="status"]:checked').val();
        const mode = $('input[name="mode"]:checked').val();

        // Ocultar todos los grupos
        $('#vlan-id-group').hide();
        $('#allowed-vlans-group').hide();
        $('#dhcp-group').hide();
        // Si el puerto está apagado, no mostramos nada
        if (status === 'shutdown'){
            $('#options-mode').hide();
            return;
        } else{
            $('#options-mode').show();
        }

        // Mostrar según el modo seleccionado
        switch (mode) {
            case 'LAN':
            case 'Transparent':
                $('#dhcp-group').show();
                break;
            case 'Access':
                $('#dhcp-group').show();
                $('#vlan-id-group').show();
                break;
            case 'Hybrid':
                $('#dhcp-group').show();
                $('#vlan-id-group').show();
                $('#allowed-vlans-group').show();
                break;
            case 'Trunk':
                $('#vlan-id-group').show();
                $('#allowed-vlans-group').show();
                break;
        }
    }

    // Cuando cambian los radios
    $(document).on('change', 'input[name="status"], input[name="mode"]', function () {
        toggleFields();
    });

    // Cuando se abre el modal
    $('#ethernetModal').on('shown.bs.modal', function () {
        toggleFields();
    });

    function update_ethernet(){

        let status = $('input[name="status"]:checked').val();
        let mode = $('input[name="mode"]:checked').val();

        let dhcp = $('#dhcp-select').val();
        let allowedVlans = $("#allowed-vlans-select").val();
        let vlanId = $('#vlan-id-select').val();

        let ethernetPort = $("#ethernetName").text();

        if (window.location.pathname.split("/")[1] === "software") {
            var url='/software/Olt/update-ethernet-port';
        }else{
            var url = '/Olt/update-ethernet-port';
        }

        $.ajax({
            url: url,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            method: 'post',
            data: {
                ethernet_port: ethernetPort,
                status: status,
                mode: mode,
                dhcp: dhcp,
                allowed_vlans: allowedVlans,
                vlan: vlanId,
                sn: `{{ $details['sn'] }}`
            },
            success: function (data) {
                if(data.status == true){
                    Swal.fire({
                        title: 'Ethernet actualizado correctamente!',
                        type: 'success',
                        showConfirmButton: false,
                        allowOutsideClick: false,
                    });
                    $('#ethernetModal').modal('hide');
                    location.reload(); // Recargar la página para reflejar los cambios

                }else{
                    Swal.close();
                    alert("Hubo un error comuniquese con soporte.")
                }
            }
        });


    }
</script>

{{-- Scripts MOVE ONU --}}
<script>

    function modalLocationDetails(){

        var url = `{{ route('olt.get-modal-location') }}`

        // Mostrar preloader antes de enviar
        Swal.fire({
            title: 'Cargando información...',
            type:'info',
            allowOutsideClick: false
        });

        // Mostrar modal
        $("#locationDetails").modal('show');

    }

    function modalMoveOnu() {

        var url = `{{ route('olt.get-modal-onu') }}`

        // Mostrar preloader antes de enviar
        Swal.fire({
            title: 'Cargando información...',
            type:'info',
            allowOutsideClick: false
        });

        $.ajax({
            url: url,
            method: 'GET',
            data: {
                sn: `{{ $details['sn'] }}`,
                olt_id: `{{ $details['olt_id'] }}`
            },
            success: function(response) {
                console.log(response);

                const selectOlt = $('#olt-select');
                const selectBoard = $('#board-select');
                const selectPort = $('#port-select');

                selectOlt.empty();
                selectBoard.empty();
                selectPort.empty();

                const dataOlt = response.olts.response;
                const dataBoard = response.boards;

                // Llenar OLTs
                if(dataOlt.length > 0){
                    dataOlt.forEach(function(item) {
                        const option = new Option(item.name, item.id);
                        if (item.id == `{{ $details['olt_id'] }}`) {
                            option.selected = true;
                        }
                        selectOlt.append(option);
                    });
                    selectOlt.selectpicker('refresh');
                }

                // Llenar Boards
                if(dataBoard.length > 0){
                    dataBoard.forEach(function(item) {
                        const option = new Option(`Slot ${item.slot}`, item.slot);
                        if (item.slot == `{{ $details['board'] }}`) {
                            option.selected = true;
                        }
                        selectBoard.append(option);
                    });
                    selectBoard.selectpicker('refresh');
                }

                // Obtener board seleccionada
                const selectedBoardSlot = `{{ $details['board'] }}`;
                const selectedBoard = dataBoard.find(b => b.slot == selectedBoardSlot);

                if (selectedBoard && selectedBoard.ports) {
                    const portCount = parseInt(selectedBoard.ports);
                    for (let i = 0; i < portCount; i++) {
                        const option = new Option(i, i);
                        if (i == `{{ $details['port'] }}`) {
                            option.selected = true;
                        }
                        selectPort.append(option);
                    }
                    selectPort.selectpicker('refresh');
                }

                // Mostrar modal
                $("#moveOnuModal").modal('show');
            },
            error: function() {
                alert('Error al cargar OLTs');
            }
        });
    }


    function update_move_onu() {

    let board = $('#board-select').val();
    let port = $('#port-select').val();
    let olt = $('#olt-select').val();
    let sn = `{{ $details['sn'] }}`;

    if (window.location.pathname.split("/")[1] === "software") {
        var url = '/software/Olt/move-onu-modal';
    } else {
        var url = '/Olt/move-onu-modal';
    }

    // Mostrar preloader antes de enviar
    Swal.fire({
        title: 'Moviendo ONU...',
        type:'info',
        html: 'Por favor espera mientras se actualiza la información.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    $.ajax({
        url: url,
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        method: 'post',
        data: {
            sn: sn,
            board: board,
            port: port,
            olt_id: olt
        },
        success: function (data) {
            if (data.status == 200) {
                Swal.fire({
                    title: '¡ONU movida correctamente!',
                    type: 'success',
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    timer: 2000
                });
                $('#moveOnuModal').modal('hide');
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                Swal.close();
                alert("Hubo un error, comuníquese con soporte.");
            }
        },
        error: function () {
            Swal.close();
            alert("Error en la conexión con el servidor.");
        }
    });
}


</script>
@endsection