@extends('layouts.app')

@section('style')
    <style>
        .bg-th {
            background: {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }} !important;
            border-color: {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }} !important;
            color: #fff !important;
        }

        .table .thead-light th {
            color: #fff !important;
            background-color: {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }} !important;
            border-color: {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }} !important;
        }

        .nav-tabs .nav-link {
            font-size: 1em;
        }

        .nav-tabs .nav-link.active,
        .nav-tabs .nav-item.show .nav-link {
            background-color: {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }};
            color: #fff !important;
            box-shadow: 2px 2px 10px #797979;
        }

        .nav-pills .nav-link.active,
        .nav-pills .show>.nav-link {
            color: #fff !important;
            background-color: {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }} !important;
            box-shadow: 2px 2px 10px #797979;
        }

        .nav-pills .nav-link {
            font-weight: 700 !important;
        }

        .nav-pills .nav-link {
            color: {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }} !important;
            background-color: #f9f9f9 !important;
            margin: 2px;
            border: 1px solid {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }};
            transition: 0.4s;
        }

        .nav-pills .nav-link:hover {
            color: #fff !important;
            background-color: {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }} !important;
            box-shadow: 2px 2px 10px #797979;
        }

        table.dataTable td.dataTables_empty,
        table.dataTable th.dataTables_empty {
            text-align: center;
            color: red;
            font-weight: 900;
        }

        .card-adj:hover {
            box-shadow: 2px 2px 10px #797979;
        }

        .btn.btn-icons {
            border-radius: 50%;
        }

        .readonly {
            border: 0 !important;
        }

        .dropdown-header>span {
            font-weight: 500;
        }

        .input-group-prepend .input-group-text {
            background: #f9f9f9;
            border-color: #dee4e6;
            font-size: 0.9rem;
        }


    </style>
@endsection

@section('content')
    @if (Session::has('danger'))
        <div class="alert alert-danger">
            {{ Session::get('danger') }}
        </div>

        <script type="text/javascript">
            setTimeout(function() {
                $('.alert').hide();
                $('.active_table').attr('class', ' ');
            }, 100000);
        </script>
    @endif

    <form method="POST" action="{{ route('contratos.store') }}" style="padding: 2% 3%;" role="form" class="forms-sample"
        novalidate id="form-contrato" enctype="multipart/form-data">
        @csrf

        <div class="row card-description p-0">
            <div class="col-md-12 mt-3">
                <ul class="nav nav-pills" id="myTab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="info-tab" data-toggle="tab" href="#info" role="tab"
                            aria-controls="info" aria-selected="false">INFORMACIÓN PRINCIPAL</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="internet-tab" data-toggle="tab" href="#internet" role="tab"
                            aria-controls="internet" aria-selected="false">SERVICIO DE INTERNET</a>
                    </li>
                    @if (count($servicios) > 0)
                        <li class="nav-item">
                            <a class="nav-link" id="television-tab" data-toggle="tab" href="#television" role="tab"
                                aria-controls="television" aria-selected="false">SERVICIO DE TELEVISIÓN</a>
                        </li>
                    @endif
                    <li class="nav-item">
                        <a class="nav-link" id="television-tab" data-toggle="tab" href="#otrositems" role="tab"
                            aria-controls="television" aria-selected="false">OTROS ITEMS A FACTURAR</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="adicionales-tab" data-toggle="tab" href="#adicionales" role="tab"
                            aria-controls="adicionales" aria-selected="false">OPCIONES ADICIONALES</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="adjuntos-tab" data-toggle="tab" href="#adjuntos" role="tab"
                            aria-controls="adjuntos" aria-selected="false">ARCHIVOS ADJUNTOS</a>
                    </li>
                </ul>
                <hr
                    style="border-top: 1px solid {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }}; margin: .5rem 0rem 2rem;">
                <div class="tab-content fact-table" id="myTabContent">
                    <div class="tab-pane fade show active" id="info" role="tabpanel" aria-labelledby="info-tab">
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label class="control-label">Cliente <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <select class="form-control selectpicker" name="client_id" id="client_id" required=""
                                        title="Seleccione" data-live-search="true" data-size="5"
                                        onchange="getContracts(this.value)">
                                        @foreach ($clientes as $client)
                                            <option value="{{ $client->id }}"
                                                {{ old('client_id') == $client->id ? 'selected' : '' }}
                                                {{ $cliente == $client->id ? 'selected' : '' }}>{{ $client->nombre }}
                                                {{ $client->apellido1 }} {{ $client->apellido2 }} - {{ $client->nit }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="input-group-append">
                                        <a href="#" data-toggle="modal" data-target="#contactoModal"
                                            class="btn btn-outline-success btn-sm">
                                            <i class="fas fa-plus" style="margin: 2px;"></i>
                                        </a>
                                    </div>
                                </div>
                                <span class="help-block error">
                                    <strong>{{ $errors->first('client_id') }}</strong>
                                </span>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="control-label">Grupo de Corte <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <select class="form-control selectpicker" name="grupo_corte" id="grupo_corte_s"
                                        required="" title="Seleccione" data-live-search="true" data-size="5">
                                        @foreach ($grupos as $grupo)
                                            <option value="{{ $grupo->id }}"
                                                {{ old('grupo_corte') == $grupo->id ? 'selected' : '' }}>{{ $grupo->nombre }}
                                                (Corte {{ $grupo->fecha_corte }} - Suspensión
                                                {{ $grupo->fecha_suspension }})</option>
                                        @endforeach
                                    </select>
                                    <div class="input-group-append">
                                        <a href="#" data-toggle="modal" data-target="#grupoModal"
                                            class="btn btn-outline-success btn-sm">
                                            <i class="fas fa-plus" style="margin: 2px;"></i>
                                        </a>
                                    </div>
                                </div>
                                <span class="help-block error">
                                    <strong>{{ $errors->first('grupo_corte') }}</strong>
                                </span>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="control-label">Tipo Factura <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <select class="form-control selectpicker" name="facturacion" id="facturacion"
                                        required="" title="Seleccione" data-live-search="true" data-size="5">
                                        <option value="1" {{ old('facturacion') == 1 ? 'selected' : '' }}>Facturación
                                            Estándar</option>
                                        <option value="3" {{ old('facturacion') == 3 ? 'selected' : '' }}>Facturación
                                            Electrónica</option>
                                    </select>
                                </div>
                                <span class="help-block error">
                                    <strong>{{ $errors->first('facturacion') }}</strong>
                                </span>
                            </div>
                            <div class="col-md-4 form-group" id="div_facturacion">
                                <label class="control-label">Facturación Individual <span class="text-danger">*</span>
                                    <a><i data-tippy-content="Indicar si desea crear una factura general con los otros contratos o crear individualmente"
                                            class="icono far fa-question-circle"></i></a></label>
                                <div class="input-group">
                                    <select class="form-control selectpicker" name="factura_individual"
                                        id="factura_individual" required="" title="Seleccione"
                                        data-live-search="true" data-size="5">
                                        <option value="1" {{ old('factura_individual') == 1 ? 'selected' : '' }}>Si
                                        </option>
                                        <option value="0" {{ old('factura_individual') == 0 ? 'selected' : '' }}>No
                                        </option>
                                    </select>
                                </div>
                                <span class="help-block error">
                                    <strong>{{ $errors->first('factura_individual') }}</strong>
                                </span>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="control-label">¿Aplicar contrato de permanencia? <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <select class="form-control selectpicker" id="contrato_permanencia"
                                        name="contrato_permanencia" required="" title="Seleccione"
                                        data-live-search="true" data-size="5">
                                        <option value="1" {{ old('contrato_permanencia') == 1 ? 'selected' : '' }}>Si
                                        </option>
                                        <option value="0" {{ old('contrato_permanencia') == 0 ? 'selected' : '' }}>No
                                        </option>
                                    </select>
                                </div>
                                <span class="help-block error">
                                    <strong>{{ $errors->first('contrato_permanencia') }}</strong>
                                </span>
                            </div>
                            <div class="col-md-4 form-group {{ old('contrato_permanencia') == 1 ? '' : 'd-none' }}"
                                id="div_meses">
                                <label class="control-label">Meses del contrato de permanencia <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <select class="form-control selectpicker" id="contrato_permanencia_meses"
                                        name="contrato_permanencia_meses" required="" title="Seleccione"
                                        data-live-search="true" data-size="5">
                                        <option value="3" {{ old('contrato_permanencia_meses') == 3 ? 'selected' : '' }}>3
                                            meses</option>
                                        <option value="6" {{ old('contrato_permanencia_meses') == 6 ? 'selected' : '' }}>6
                                            meses</option>
                                        <option value="9" {{ old('contrato_permanencia_meses') == 9 ? 'selected' : '' }}>9
                                            meses</option>
                                        <option value="12" {{ old('contrato_permanencia_meses') == 12 ? 'selected' : '' }}>
                                            12 meses</option>
                                    </select>
                                </div>
                                <span class="help-block error">
                                    <strong>{{ $errors->first('contrato_permanencia_meses') }}</strong>
                                </span>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="control-label">Coordenadas GPS <a><i
                                            data-tippy-content="Arrastre el pin para indicar las coordenadas deseadas."
                                            class="icono far fa-question-circle"></i></a></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="us2-lat" name="latitude"
                                        value="{{ old('latitude') }}">
                                    <input type="text" class="form-control" id="us2-lon" name="longitude"
                                        value="{{ old('longitude') }}">
                                    <div class="input-group-prepend">
                                        <button class="btn btn-outline-success btn-sm" type="button" data-toggle="modal"
                                            data-target="#modal-gps" style="border-radius: 0 5px 5px 0;">
                                            <i class="fas fa-map-marked-alt" style="margin: 2px;"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>


                            <div class="col-md-4 form-group" id="div_direccion">
                                <label class="control-label">¿Usar la misma dirección del contacto? <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <select class="form-control selectpicker" id="direccion" name="direccion"
                                        required="" title="Seleccione" data-live-search="true" data-size="5"
                                        onchange="getDireccion(this.value)" required="">
                                        <option value="SI" {{ old('direccion') == 'SI' ? 'selected' : '' }}>Si</option>
                                        <option value="NO" {{ old('direccion') == 'NO' ? 'selected' : '' }}>No</option>
                                    </select>
                                </div>
                                <span class="help-block error">
                                    <strong>{{ $errors->first('direccion') }}</strong>
                                </span>
                            </div>

                            <div class="col-md-4 form-group" id="div_address_street">
                                <label class="control-label">Dirección de Instalación <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="hidden" class="form-control" id="input_direccion">
                                    <input type="text" class="form-control" name="address_street" id="address_street"
                                        value="{{ old('address_street') }}">
                                    <span class="help-block error">
                                        <strong>{{ $errors->first('address_street') }}</strong>
                                    </span>
                                </div>
                            </div>

                            @if (Auth::user()->empresa()->oficina)
                                <div class="form-group col-md-4">
                                    <label class="control-label">Oficina Asociada <span
                                            class="text-danger">*</span></label>
                                    <select class="form-control selectpicker" name="oficina" id="oficina"
                                        required="" title="Seleccione" data-live-search="true" data-size="5">
                                        @foreach ($oficinas as $oficina)
                                            <option value="{{ $oficina->id }}"
                                                {{ $oficina->id == auth()->user()->oficina ? 'selected' : '' }}>
                                                {{ $oficina->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="col-md-4 form-group">
                                <label class="control-label">Usuario Wifi <a><i
                                            data-tippy-content="Arrastre el pin para indicar las coordenadas deseadas."
                                            class="icono far fa-question-circle"></i></a></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="us2-lat" name="usuario_wifi"
                                        value="">
                                </div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="control-label">Contraseña Wifi <a><i
                                            data-tippy-content="Arrastre el pin para indicar las coordenadas deseadas."
                                            class="icono far fa-question-circle"></i></a></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="us2-lat" name="contrasena_wifi"
                                        value="">
                                </div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="control-label">Linea</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="linea" id="linea"
                                        value="{{ old('linea') }}">
                                    <span class="help-block error">
                                        <strong>{{ $errors->first('linea') }}</strong>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="control-label">Estrato</label>
                                <select class="form-control selectpicker" id="estrato" name="estrato" title="Seleccione" data-live-search="true" data-size="5">
                                    <option value="1" {{ old('estrato') == 1 ? 'selected' : '' }}>1</option>
                                    <option value="2" {{ old('estrato') == 2 ? 'selected' : '' }}>2</option>
                                    <option value="3" {{ old('estrato') == 3 ? 'selected' : '' }}>3</option>
                                    <option value="4" {{ old('estrato') == 4 ? 'selected' : '' }}>4</option>
                                    <option value="5" {{ old('estrato') == 5 ? 'selected' : '' }}>5</option>
                                    <option value="6" {{ old('estrato') == 6 ? 'selected' : '' }}>6</option>
                                    <option value="7" {{ old('estrato') == 7 ? 'selected' : '' }}>7</option>
                                </select>
                                <span class="help-block error">
                                    <strong>{{ $errors->first('estrato') }}</strong>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="internet" role="tabpanel" aria-labelledby="internet-tab">
                        <div class="row">

                            <div class="col-md-4 form-group">
                                <label class="control-label">Servidor <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <select class="form-control selectpicker" name="server_configuration_id"
                                        id="server_configuration_id" required="" title="Seleccione"
                                        data-live-search="true" data-size="5" onchange="getPlanes(this.value, typeof consultasMk !== 'undefined' ? consultasMk : 1);">
                                        @foreach ($servidores as $servidor)
                                            <option value="{{ $servidor->id }}"
                                                {{ old('server_configuration_id') == $servidor->id ? 'selected' : '' }}>
                                                {{ $servidor->nombre }} - {{ $servidor->ip }}</option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" id="servidor" value="{{ old('server_configuration_id') }}">
                                </div>
                                <span class="help-block error">
                                    <strong>{{ $errors->first('server_configuration_id') }}</strong>
                                </span>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="control-label">Plan <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <select class="form-control selectpicker" name="plan_id" id="plan_id"
                                        required="" title="Seleccione" data-live-search="true" data-size="5">

                                    </select>
                                    <div class="input-group-append">
                                        <a href="#" data-toggle="modal" data-target="#planModal"
                                            class="btn btn-outline-success btn-sm">
                                            <i class="fas fa-plus" style="margin: 2px;"></i>
                                        </a>
                                    </div>
                                </div>
                                <span class="help-block error">
                                    <strong>{{ $errors->first('plan_id') }}</strong>
                                </span>
                            </div>

                            <div class="col-md-4 form-group">
                                <label class="control-label">Tipo Conexión <span class="text-danger">*</span></label>
                                <select class="form-control selectpicker" id="conexion" name="conexion" required=""
                                    title="Seleccione" data-live-search="true" data-size="4"
                                    onchange="interfazChange();">
                                    <option value="1" {{ old('conexion') == 1 ? 'selected' : '' }}>PPPOE</option>
                                    <option value="2" {{ old('conexion') == 2 ? 'selected' : '' }}>DHCP</option>
                                    <option value="3" {{ old('conexion') == 3 ? 'selected' : '' }}>IP Estática</option>
                                    <option value="4" {{ old('conexion') == 4 ? 'selected' : '' }}>VLAN</option>
                                </select>
                                <input type="hidden" name="amarre_mac" id="amarre_mac">
                                <input type="hidden" name="conexion_bd" id="conexion_bd"
                                    value="{{ old('conexion') }}">
                            </div>

                            <div class="col-md-4 form-group d-none" id="div_simple_queue">
                                <label class="control-label">Simple Queue <span class="text-danger">*</span></label>
                                <select class="form-control selectpicker" id="simple_queue" name="simple_queue"
                                    required="" title="Seleccione" data-live-search="true" data-size="5" onchange="toggleCamposDHCP();">
                                    <option value="dinamica" {{ old('simple_queue') == 'dinamica' ? 'selected' : '' }}>
                                        Dinámica</option>
                                    <option value="estatica" {{ old('simple_queue') == 'estatica' ? 'selected' : '' }}>
                                        Estática</option>
                                </select>
                            </div>

                            <div class="col-md-4 form-group d-none" id="div_name_vlan">
                                <label class="control-label">Nombre VLAN <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="name_vlan" id="name_vlan"
                                        value="{{ old('name_vlan') }}">
                                    <span class="help-block error">
                                        <strong>{{ $errors->first('name_vlan') }}</strong>
                                    </span>
                                </div>
                            </div>

                            <div class="col-md-4 form-group d-none" id="div_id_vlan">
                                <label class="control-label">ID VLAN <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="id_vlan" id="id_vlan"
                                        min="1" max="4095" value="{{ old('id_vlan') }}">
                                    <span class="help-block error">
                                        <strong>{{ $errors->first('id_vlan') }}</strong>
                                    </span>
                                </div>
                            </div>

                            <div class="col-md-4 form-group {{ old('conexion') == 3 ? '' : 'd-none' }}" id="div_interfaz">
                                <label class="control-label">Interfaz de Conexión <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <select class="form-control selectpicker" name="interfaz" id="interfaz"
                                        required="" title="Seleccione" data-live-search="true" data-size="5">

                                    </select>
                                    <input type="hidden" name="interfaz_bd" id="interfaz_bd"
                                        value="{{ old('interfaz') }}">
                                    <span class="help-block error">
                                        <strong>{{ $errors->first('interfaz') }}</strong>
                                    </span>
                                </div>
                            </div>

                            <div class="col-md-4 form-group" id="div_segmento_ip">
                                <label class="control-label" id="div_local_address">Segmento de IP<span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="hidden" id="segmento_bd" name="segmento_bd"
                                        value="{{ old('segmento_bd') }}">
                                    <select class="form-control selectpicker" name="local_address" id="local_address"
                                        required="" title="Seleccione" data-live-search="true" data-size="5">

                                    </select>
                                    <span class="help-block error">
                                        <strong>{{ $errors->first('local_address') }}</strong>
                                    </span>
                                </div>
                            </div>

                            <div class="col-md-4 form-group" id="div_direccion_ip">
                                <label class="control-label" id="div_ip">Dirección IP (Remote Address)<span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="ip" id="ip"
                                        onkeypress="return event.charCode >= 48 && event.charCode <=57 || event.charCode==46 || event.charCode==47"
                                        value="{{ old('ip') }}">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-success btn-sm" type="button" id="searchIP"
                                            style="border-radius: 0 5px 5px 0;"><i class="fa fa-search"
                                                style="margin: 2px;"></i></button>
                                    </div>
                                    <span class="help-block error">
                                        <strong>{{ $errors->first('ip') }}</strong>
                                    </span>
                                </div>
                            </div>

                            <div class="col-md-4 form-group d-none" id="new_segmento">
                                <label class="control-label">Segmento de IP</label>
                                <input type="text" class="form-control" name="local_address_new"
                                    id="local_address_new"
                                    onkeypress="return event.charCode >= 48 && event.charCode <=57 || event.charCode==46 || event.charCode==47">
                            </div>

                            <div class="col-md-4 form-group d-none" id="local_adress">
                                <label class="control-label">Dirección IP (Local Address)</label>
                                <input type="text" class="form-control" name="direccion_local_address"
                                    id="local_address"
                                    onkeypress="return event.charCode >= 48 && event.charCode <=57 || event.charCode==46 || event.charCode==47">
                            </div>


                            <div class="col-md-4 form-group d-none" id="div_profile">
                                <label class="control-label">Profile<span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <select class="form-control selectpicker" name="profile" id="div_profile_select"
                                        required="" title="Seleccione" data-live-search="true" data-size="5">

                                    </select>
                                </div>
                                <span class="help-block error">
                                    <strong>{{ $errors->first('div_profile_select') }}</strong>
                                </span>
                            </div>

                            <div class="col-md-4 form-group d-none" id="new_ip">
                                <label class="control-label" id="ip_new">Dirección IP (Remote Address) <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="ip_new" id="ip_new" readonly
                                        required=""
                                        onkeypress="return event.charCode >= 48 && event.charCode <=57 || event.charCode==46"
                                        value="{{ old('ip_new') }}">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-success btn-sm" type="button" id="searchIP2"><i
                                                class="fa fa-search" style="margin: 2px;"></i></button>
                                    </div>
                                    <span class="help-block error">
                                        <strong>{{ $errors->first('ip_new') }}</strong>
                                    </span>
                                </div>
                            </div>

                            <div class="col-md-4 form-group {{ old('conexion') == 1 ? '' : 'd-none' }}" id="div_usuario">
                                <label class="control-label">Usuario <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="usuario" id="usuario"
                                        value="{{ old('usuario') }}">
                                    <span class="help-block error">
                                        <strong>{{ $errors->first('usuario') }}</strong>
                                    </span>
                                </div>
                            </div>

                            <div class="col-md-4 form-group {{ old('conexion') == 1 ? '' : 'd-none' }}" id="div_password">
                                <label class="control-label">Contraseña <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="password" id="password"
                                        value="{{ old('password') }}">
                                    <span class="help-block error">
                                        <strong>{{ $errors->first('password') }}</strong>
                                    </span>
                                </div>
                            </div>

                            <div class="col-md-4 form-group">
                                <label class="control-label">Tipo de Tecnología <span class="text-danger">*</span></label>
                                <select class="form-control selectpicker" id="tecnologia" name="tecnologia"
                                    required="" title="Seleccione" onchange="visibilidad(this)">
                                    <option value="1">Fibra</option>
                                    <option value="2">Inalámbrico</option>
                                    <option value="3">Cableado UTP</option>
                                </select>
                                <span class="help-block error">
                                    <strong>{{ $errors->first('tecnologia') }}</strong>
                                </span>
                            </div>
                            <div class="col-md-4 form-group d-none" id="ip_receptora">
                                <label class="control-label">Ip Receptora</label>
                                <input type="text" class="form-control" name="ip_receptora" id="ip_receptora">
                                <span class="help-block error">
                                    <strong>{{ $errors->first('ip_receptora') }}</strong>
                                </span>
                            </div>

                            <div class="col-md-4 form-group d-none" id="puerto_receptor">
                                <label class="control-label ">Puerto Receptor</label>
                                <input type="text" class="form-control" name="puerto_receptor" id="puerto_receptor">
                                <span class="help-block error">
                                    <strong>{{ $errors->first('puerto_receptor') }}</strong>
                                </span>
                            </div>
                            <div class="col-md-4 form-group d-none" id="div_ap">
                                <label class="control-label">Access Point Asociado <span
                                        class="text-danger">*</span></label>
                                <select class="form-control selectpicker" id="ap" name="ap"
                                    title="Seleccione" data-live-search="true" data-size="5">
                                    @foreach ($nodos as $nodo)
                                        <optgroup label="NODO {{ $nodo->nombre }}">
                                            @foreach ($aps as $ap)
                                                @if ($ap->nodo == $nodo->id)
                                                    <option id="{{ $ap->id }}" value="{{ $ap->id }}"
                                                        {{ old('ap') == $ap->id ? 'selected' : '' }}>{{ $ap->nombre }}
                                                    </option>
                                                @endif
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                                <span class="help-block error">
                                    <strong>{{ $errors->first('ap') }}</strong>
                                </span>
                            </div>

                            <div class="col-md-4 form-group d-none">
                                <label class="control-label">¿Aplicar reuso? <span class="text-danger">*</span></label>
                                <select class="form-control selectpicker" id="rehuso" name="rehuso" required=""
                                    title="Seleccione">
                                    <option value="SI">Si</option>
                                    <option value="NO">No</option>
                                </select>
                                <span class="help-block error">
                                    <strong>{{ $errors->first('rehuso') }}</strong>
                                </span>
                            </div>

                            <div class="col-md-4 form-group d-none" id="div_rehuso_aplicar">
                                <label class="control-label">Reuso a aplicar<span class="text-danger">*</span></label>
                                <div class="input-group mb-2">
                                    <div class="input-group-prepend">
                                        <div class="input-group-text" style="background: #f3f5f6;border-color: #dee4e6;">
                                            1:</div>
                                    </div>
                                    <input type="text" class="form-control" name="rehuso_aplicar"
                                        id="rehuso_aplicar">
                                </div>
                                <span class="help-block error">
                                    <strong>{{ $errors->first('rehuso_aplicar') }}</strong>
                                </span>
                            </div>

                            <div class="col-md-4 form-group" id="div_mac">
                                <label class="control-label">Dirección MAC</label>
                                <div class="input-group">
                                    <input type="text" class="form-control mac_address" name="mac_address"
                                        id="mac_address" value="{{ old('mac_address') }}">
                                    <span class="help-block error">
                                        <strong>{{ $errors->first('mac_address') }}</strong>
                                    </span>
                                </div>
                            </div>

                            <div class="col-md-4 form-group">
                                <label class="control-label">Puerto de Conexión</label>
                                <div class="input-group">
                                    <select class="form-control selectpicker" name="puerto_conexion" id="puerto_conexion"
                                        required="" title="Seleccione" data-live-search="true" data-size="5">
                                        @foreach ($puertos as $puerto)
                                            <option value="{{ $puerto->id }}"
                                                {{ old('puerto_conexion') == $puerto->id ? 'selected' : '' }}>
                                                {{ $puerto->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <span class="help-block error">
                                    <strong>{{ $errors->first('puerto_conexion') }}</strong>
                                </span>
                            </div>

                            <div class="col-md-4 form-group">
                                <label class="control-label">Caja NAP</label>
                                <div class="input-group">
                                    <select class="form-control selectpicker" name="cajanap_id" id="cajanap_id" title="Seleccione" data-live-search="true" data-size="5"
                                    onchange="cargarPuertosNap()">
                                        <option value="">Ninguna</option>
                                        @foreach($cajasNaps as $cajaNap)
                                            <option value="{{$cajaNap->id}}" {{old('cajanap_id') == $cajaNap->id ? 'selected':''}}>{{$cajaNap->nombre}}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <span class="help-block error">
                                    <strong>{{ $errors->first('cajanap_id') }}</strong>
                                </span>
                            </div>

                            <div class="col-md-4 form-group" id="div_puerto_nap" style="display:none;">
                                <label class="control-label">Puerto Caja NAP</label>
                                <div class="input-group">
                                    <select class="form-control selectpicker" name="cajanap_puerto" id="cajanap_puerto" title="Seleccione" data-live-search="true" data-size="5">
                                        <option value="">Seleccione un puerto</option>
                                    </select>
                                </div>
                                <span class="help-block error">
                                    <strong>{{ $errors->first('cajanap_puerto') }}</strong>
                                </span>
                            </div>

                            <div class="col-md-3 form-group d-none">
                                <label class="control-label">Marca Router</label>
                                <select class="form-control selectpicker" id="marca_router" name="marca_router"
                                    title="Seleccione">
                                    @foreach ($marcas as $marca)
                                        <option value="{{ $marca->id }}"
                                            {{ old('marca_router') == $marca->id ? 'selected' : '' }}>{{ $marca->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                                <span class="help-block error">
                                    <strong>{{ $errors->first('marca_router') }}</strong>
                                </span>
                            </div>

                            <div class="col-md-3 form-group d-none">
                                <label class="control-label">Modelo Router</label>
                                <input type="text" class="form-control" id="modelo_router" name="modelo_router"
                                    value="{{ old('modelo_router') }}">
                                <span class="help-block error">
                                    <strong>{{ $errors->first('modelo_router') }}</strong>
                                </span>
                            </div>

                            <div class="col-md-3 form-group d-none">
                                <label class="control-label">Marca Antena</label>
                                <select class="form-control selectpicker" id="marca_antena" name="marca_antena"
                                    title="Seleccione">
                                    @foreach ($marcas as $marca)
                                        <option value="{{ $marca->id }}"
                                            {{ old('marca_antena') == $marca->id ? 'selected' : '' }}>{{ $marca->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                                <span class="help-block error">
                                    <strong>{{ $errors->first('marca_antena') }}</strong>
                                </span>
                            </div>

                            <div class="col-md-3 form-group d-none">
                                <label class="control-label">Modelo Antena</label>
                                <input type="text" class="form-control" id="modelo_antena" name="modelo_antena"
                                    value="{{ old('modelo_antena') }}">
                                <span class="help-block error">
                                    <strong>{{ $errors->first('modelo_antena') }}</strong>
                                </span>
                            </div>

                            <div class="col-md-4 form-group">
                                <label class="control-label">Serial ONU</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="serial_onu" id="serial_onu"
                                        value="{{ old('serial_onu') }}">
                                    <span class="help-block error">
                                        <strong>{{ $errors->first('serial_onu') }}</strong>
                                    </span>
                                </div>
                            </div>
                            <!--
                                 <div class="col-md-4 form-group">
                                    <label class="control-label">Serial Modem</label>
                                      <div class="input-group">
                                        <input type="text" class="form-control" name="serial_moden" id="serial_moden" value="">
                                        <span class="help-block error">
                                            <strong></strong>
                                        </span>
                                    </div>
                                </div>
                                 <div class="col-md-4 form-group">
                                    <label class="control-label">Tipo Modem</label>
                                      <div class="input-group">
                                        <input type="text" class="form-control" name="tipo_moden" id="tipo_moden" value="">
                                        <span class="help-block error">
                                            <strong></strong>
                                        </span>
                                    </div>
                                </div>-->

                            <div class="form-group col-md-4">
                                <label class="control-label">¿Agregar iva al servicio de internet? <a><i
                                            data-tippy-content="Decida si la factura que genere este contrato llevará iva"
                                            class="icono far fa-question-circle"></i></a></label>
                                <div class="d-flex align-items-center">
                                    <label class="switch mb-0">
                                        <input type="hidden" name="iva_factura" value="0">
                                        <input type="checkbox" name="iva_factura" id="iva_factura" value="1">
                                        <span class="slider round"></span>
                                    </label>
                                    <span class="ml-2" id="iva_factura_label">No</span>
                                </div>
                                <span class="help-block error">
                                    <strong></strong>
                                </span>
                            </div>


                        </div>
                    </div>
                    @if (count($servicios) > 0)
                        <div class="tab-pane fade" id="television" role="tabpanel" aria-labelledby="television-tab">
                            <div class="row">
                                <div class="col-md-4 form-group">
                                    <label class="control-label">Servicio de Televisión</label>
                                    <select class="form-control selectpicker" name="servicio_tv" id="servicio_tv"
                                        title="Seleccione" data-live-search="true" data-size="5">
                                        @foreach ($servicios as $servicio)
                                            <option value="{{ $servicio->id }}"
                                                {{ old('servicio_tv') == $servicio->id ? 'selected' : '' }}>
                                                {{ $servicio->producto }} - ({{ Auth::user()->empresa()->moneda }}
                                                {{ App\Funcion::Parsear($servicio->precio) }})</option>
                                        @endforeach
                                    </select>
                                    <span style="color: red;">
                                        <strong>{{ $errors->first('servicio_tv') }}</strong>
                                    </span>
                                </div>

                                <div class="col-md-4 form-group">
                                    <label class="control-label font-weight-bold">SN / MAC</label>
                                    <input type="text" class="form-control" id="olt_sn_mac" name="olt_sn_mac"
                                        maxlength="200">
                                    <span class="help-block error">
                                        <strong>{{ $errors->first('olt_sn_mac') }}</strong>
                                    </span>
                                </div>

                                <div class="col-md-4 form-group">
                                    <label class="control-label font-weight-bold">
                                        Estado del catv
                                        <a><i data-tippy-content="Elige el estado en el que estará el catv en el smart olt"
                                                class="icono far fa-question-circle"></i></a>
                                    </label>
                                    <select class="form-control selectpicker" name="state_olt_catv" id="state_olt_catv"
                                        title="Seleccione" data-live-search="true" data-size="2">
                                        <option value="1">HABILITADO</option>
                                        <option value="0">DESHABILITADO</option>
                                    </select>
                                    <span class="help-block error">
                                        <strong>{{ $errors->first('state_olt_catv') }}</strong>
                                    </span>
                                </div>

                            </div>
                        </div>
                    @endif
                    <div class="tab-pane fade" id="otrositems" role="tabpanel" aria-labelledby="otrositems-tab">
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label class="control-label">Otros ítems</label>
                                <select class="form-control selectpicker" name="servicio_otro" id="servicio_otro"
                                    title="Seleccione" data-live-search="true" data-size="5">
                                    @foreach ($serviciosOtros as $servicioOtro)
                                        <option value="{{ $servicioOtro->id }}"
                                            {{ old('servicio_otro') == $servicioOtro->id ? 'selected' : '' }}>
                                            {{ $servicioOtro->producto }} - ({{ Auth::user()->empresa()->moneda }}
                                            {{ App\Funcion::Parsear($servicioOtro->precio) }})</option>
                                    @endforeach
                                </select>
                                <span style="color: red;">
                                    <strong>{{ $errors->first('servicio_otro') }}</strong>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="adjuntos" role="tabpanel" aria-labelledby="adjuntos-tab">
                        <div class="row">
                            <div class="col-md-3 form-group">
                                <label class="control-label">Referencia A</label>
                                <input type="text" class="form-control" id="referencia_a" name="referencia_a"
                                    value="{{ old('referencia_a') }}">
                                <span style="color: red;">
                                    <strong>{{ $errors->first('referencia_a') }}</strong>
                                </span>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="control-label">Referencia B</label>
                                <input type="text" class="form-control" id="referencia_b" name="referencia_b"
                                    value="{{ old('referencia_b') }}">
                                <span style="color: red;">
                                    <strong>{{ $errors->first('referencia_b') }}</strong>
                                </span>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="control-label">Referencia C</label>
                                <input type="text" class="form-control" id="referencia_c" name="referencia_c"
                                    value="{{ old('referencia_c') }}">
                                <span style="color: red;">
                                    <strong>{{ $errors->first('referencia_c') }}</strong>
                                </span>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="control-label">Referencia D</label>
                                <input type="text" class="form-control" id="referencia_d" name="referencia_d"
                                    value="{{ old('referencia_d') }}">
                                <span style="color: red;">
                                    <strong>{{ $errors->first('referencia_d') }}</strong>
                                </span>
                            </div>

                            <div class="col-md-3 form-group">
                                <label class="control-label">Adjunto A</label>
                                <input type="file" class="form-control" id="adjunto_a" name="adjunto_a"
                                    value="{{ old('adjunto_a') }}"
                                    accept=".jpg, .jpeg, .png, .pdf, .JPG, .JPEG, .PNG, .PDF">
                                <span style="color: red;">
                                    <strong>{{ $errors->first('adjunto_a') }}</strong>
                                </span>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="control-label">Adjunto B</label>
                                <input type="file" class="form-control" id="adjunto_b" name="adjunto_b"
                                    value="{{ old('adjunto_b') }}"
                                    accept=".jpg, .jpeg, .png, .pdf, .JPG, .JPEG, .PNG, .PDF">
                                <span style="color: red;">
                                    <strong>{{ $errors->first('adjunto_b') }}</strong>
                                </span>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="control-label">Adjunto C</label>
                                <input type="file" class="form-control" id="adjunto_c" name="adjunto_c"
                                    value="{{ old('adjunto_c') }}"
                                    accept=".jpg, .jpeg, .png, .pdf, .JPG, .JPEG, .PNG, .PDF">
                                <span style="color: red;">
                                    <strong>{{ $errors->first('adjunto_c') }}</strong>
                                </span>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="control-label">Adjunto D</label>
                                <input type="file" class="form-control" id="adjunto_d" name="adjunto_d"
                                    value="{{ old('adjunto_d') }}"
                                    accept=".jpg, .jpeg, .png, .pdf, .JPG, .JPEG, .PNG, .PDF">
                                <span style="color: red;">
                                    <strong>{{ $errors->first('adjunto_d') }}</strong>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="adicionales" role="tabpanel" aria-labelledby="adicionales-tab">
                        <div class="row">

                            <div class="col-md-4 form-group">
                                <label class="control-label">Descuento %<a><i
                                            data-tippy-content="El descuento que se indique, se reflejará en la facturación recurrente del contrato"
                                            class="icono far fa-question-circle"></i></a></label>
                                <div class="input-group mb-2">
                                    <input type="number" class="form-control" id="descuento" name="descuento"
                                        required="" value="{{ old('descuento') }}"
                                        onkeypress="return event.charCode >= 48 && event.charCode <=57" min="0"
                                        max="100">
                                    <div class="input-group-prepend">
                                        <div class="input-group-text font-weight-bold"><i class="fas fa-percentage"></i>
                                        </div>
                                    </div>
                                </div>
                                <span style="color: red;">
                                    <strong>{{ $errors->first('descuento') }}</strong>
                                </span>
                            </div>

                            <div class="col-md-4 form-group">
                                <label class="control-label">Descuento $<a><i
                                            data-tippy-content="El descuento que se indique, se reflejará en la facturación recurrente del contrato pero en pesos"
                                            class="icono far fa-question-circle"></i></a></label>
                                <div class="input-group mb-2">
                                    <input type="number" class="form-control" id="descuento_pesos"
                                        name="descuento_pesos" value="" min='0'>
                                    <div class="input-group-prepend">
                                        <div class="input-group-text font-weight-bold"><i
                                                class="far fa-money-bill-alt"></i></div>
                                    </div>
                                </div>
                                <span style="color: red;">
                                    <strong>{{ $errors->first('descuento_pesos') }}</strong>
                                </span>
                            </div>


                            @php
                                $today = now()->format('Y-m-d');
                                $fechaHastaDesc = old('fecha_hasta_desc', $contrato->fecha_hasta_desc ?? '');
                            @endphp

                            <div class="col-md-4 form-group">
                                <label class="control-label">
                                    Fecha Hasta - Desc.
                                    <a><i data-tippy-content="Ingresa hasta qué fecha el descuento aplicará."
                                            class="icono far fa-question-circle"></i></a>
                                </label>
                                <input type="date" class="form-control" id="fecha_hasta_desc" name="fecha_hasta_desc"
                                    value="{{ $fechaHastaDesc }}" min="{{ $today }}">
                            </div>

                            <div class="col-md-4 form-group">
                                <label class="control-label">Habilitar Prorrateo <a><i
                                            data-tippy-content="Habilitar o deshabilitar el cobro de prorrateo para este contrato, esta acción habilitada genera de inmediato una factura prorrateada."
                                            class="icono far fa-question-circle"></i></a></label>
                                <div class="d-flex align-items-center">
                                    <label class="switch mb-0">
                                        <input type="hidden" name="prorrateo" value="0">
                                        <input type="checkbox" name="prorrateo" id="prorrateo" value="1" {{ Auth::user()->empresa()->prorrateo == 1 ? 'checked' : '' }}>
                                        <span class="slider round"></span>
                                    </label>
                                    <span class="ml-2" id="prorrateo_label">{{ Auth::user()->empresa()->prorrateo == 1 ? 'Habilitado' : 'Deshabilitado' }}</span>
                                </div>
                                <span style="color: red;">
                                    <strong>{{ $errors->first('prorrateo') }}</strong>
                                </span>
                            </div>

                            <div class="col-md-4 form-group">
                                <label class="control-label">Canal de Venta <a><i
                                            data-tippy-content="Seleccione el canal de venta del contrato"
                                            class="icono far fa-question-circle"></i></a></label>
                                <div class="input-group mb-2">
                                    <select class="form-control selectpicker" name="canal" id="canal"
                                        title="Seleccione" data-live-search="true" data-size="5">
                                        @foreach ($canales as $canal)
                                            <option value="{{ $canal->id }}"
                                                {{ old('canal') == $canal->id ? 'selected' : '' }}>{{ $canal->nombre }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <span style="color: red;">
                                        <strong>{{ $errors->first('canal') }}</strong>
                                    </span>
                                </div>
                            </div>

                            <div class="col-md-4 form-group">
                                <label class="control-label">Vendedor <a><i
                                            data-tippy-content="Seleccione el vendedor del contrato"
                                            class="icono far fa-question-circle"></i></a></label>
                                <div class="input-group mb-2">
                                    <select class="form-control selectpicker" name="vendedor" id="vendedor"
                                        title="Seleccione" data-live-search="true" data-size="5">
                                        @foreach ($vendedores as $vendedor)
                                            <option value="{{ $vendedor->id }}"
                                                {{ old('vendedor') == $vendedor->id ? 'selected' : '' }}>
                                                {{ $vendedor->nombre }}</option>
                                        @endforeach
                                    </select>
                                    <span style="color: red;">
                                        <strong>{{ $errors->first('vendedor') }}</strong>
                                    </span>
                                </div>
                            </div>


                            <div class="col-md-4 form-group">
                                <label class="control-label">¿Cobro de Reconexión?</label>
                                <div class="input-group mb-2">
                                    <select class="form-control selectpicker" name="reconexion" id="reconexion"
                                        title="Seleccione">
                                        <option value="1">Si</option>
                                        <option value="0">No</option>
                                    </select>
                                    <span style="color: red;">
                                        <strong>{{ $errors->first('reconexion') }}</strong>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-4 form-group d-none" id="div_reconexion">
                                <label class="control-label">Monto de Reconexión</label>
                                <div class="input-group mb-2">
                                    <input type="number" class="form-control" id="costo_reconexion"
                                        name="costo_reconexion" value="{{ old('costo_reconexion') }}"
                                        onkeypress="return event.charCode >= 48 && event.charCode <=57" min="0">
                                    <span style="color: red;">
                                        <strong>{{ $errors->first('costo_reconexion') }}</strong>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="control-label">Tipo de Contrato</label>
                                <div class="input-group mb-2">
                                    <select class="form-control selectpicker" name="tipo_contrato" id="tipo_contrato"
                                        title="Seleccione">
                                        <option value="instalacion" selected>Instalación</option>
                                        <option value="reconexion">Reconexión</option>
                                        <option value="cambio titular del servicio">Cambio titular del servicio</option>
                                    </select>
                                    <span style="color: red;">
                                        <strong>{{ $errors->first('tipo_contrato') }}</strong>
                                    </span>
                                </div>
                            </div>

                            <div class="form-group col-md-4">
                                <label class="control-label">¿Agregar fechas de No suspensión? <a><i
                                            data-tippy-content="Decida si este contrato tendrá un rango de fechas donde si tiene facturas abiertas no lo suspenderá (esto solo pasará en el rango escogido)"
                                            class="icono far fa-question-circle"></i></a></label>
                                <div class="d-flex align-items-center">
                                    <label class="switch mb-0">
                                        <input type="hidden" name="tipo_suspension_no" value="0">
                                        <input type="checkbox" name="tipo_suspension_no" id="tipo_suspension_no" value="1">
                                        <span class="slider round"></span>
                                    </label>
                                    <span class="ml-2" id="tipo_suspension_no_label">No</span>
                                </div>
                                <span class="help-block error">
                                    <strong></strong>
                                </span>
                            </div>

                            <div class="form-group col-md-4">
                                <label class="control-label">¿Crear factura el primer mes del contrato? <a><i
                                            data-tippy-content="Elige si deseas que se genere factura al usuario el primer mes con el contrato en la fecha de facturación segun el grupo de corte"
                                            class="icono far fa-question-circle"></i></a></label>
                                <div class="d-flex align-items-center">
                                    <label class="switch mb-0">
                                        <input type="hidden" name="fact_primer_mes" value="0">
                                        <input type="checkbox" name="fact_primer_mes" id="fact_primer_mes" value="1">
                                        <span class="slider round"></span>
                                    </label>
                                    <span class="ml-2" id="fact_primer_mes_label">Si</span>
                                </div>
                                <span class="help-block error">
                                    <strong></strong>
                                </span>
                            </div>

                            <div class="col-md-4 form-group cls-nosuspension d-none" id="div_fecha_desde_nosuspension">
                                <label class="control-label">Fecha desde no suspensión</label>
                                <input type="date" class="form-control" id="fecha_desde_nosuspension"
                                    value="" name="fecha_desde_nosuspension" required>
                            </div>

                            <div class="col-md-4 form-group cls-nosuspension d-none" id="div_fecha_hasta_nosuspension">
                                <label class="control-label">Fecha hasta no suspensión</label>
                                <input type="date" class="form-control" id="fecha_hasta_nosuspension"
                                    value="" name="fecha_hasta_nosuspension" required>
                            </div>

                            <div class="form-group col-md-12">
                                <label class="control-label">Observaciones</label>
                                <textarea class="form-control" name="observaciones">{{ old('observaciones') }}</textarea>
                                <span class="help-block error">
                                    <strong>{{ $errors->first('observaciones') }}</strong>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <small>Los campos marcados con <span class="text-danger">*</span> son obligatorios</small>
        <hr>

        <div class="row">
            <div class="col-sm-12" style="text-align: right;  padding-top: 1%;">
                <a href="{{ route('contratos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" id="submitcheck" onclick="submitLimit(this.id)"
                    class="btn btn-success">Guardar</button>
            </div>
        </div>
    </form>

    <div class="modal fade" id="contactoModal" role="dialog">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-body px-0">
                    @include('contactos.modal.modal')
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="grupoModal" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body px-0">
                    @include('grupos-corte.modal')
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="planModal" role="dialog">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-body px-0">
                    @include('planesvelocidad.modal')
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-ips" role="dialog">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-body px-0">
                    <div class="row" style="text-align: center;">
                        <div class="col-md-12">
                            <h3>DIRECCIONES IP DISPONIBLES</h3>
                            <hr>
                        </div>
                    </div>
                    <div class="row" style="text-align: center;" id="row_ip">

                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-gps" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body px-0">
                    <div class="row" style="text-align: center;">
                        <div class="col-md-12">
                            <p><span class="font-weight-bold text-uppercase">Arrastre el pin para indicar las coordenadas
                                    deseadas.</span></p>
                        </div>
                    </div>
                    <div class="row" style="text-align: center;">
                        <span class="d-none">
                            Location: <input type="text" id="us2-address" style="width: 200px" />
                            Radius: <input type="text" id="us2-radius" />
                        </span>
                        <center>
                            <div id="us2"
                                style="width: 465px; height: 400px; position: relative; overflow: hidden; margin: 0 30px;">
                            </div>
                        </center>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            console.log('CREATE BLADE JS LOADED');

            // --- Definición de funciones auxiliares ---
            function visibilidad(selectElement) {
                var selectedOption = selectElement.value;
                // Mostrar los inputs inalámbricos si la opción es igual a 2
                var isWireless = (selectedOption == 2);
                var puerto = document.getElementById('puerto_receptor');
                var ip = document.getElementById('ip_receptora');
                
                if(puerto) puerto.classList.toggle('d-none', !isWireless);
                if(ip) ip.classList.toggle('d-none', !isWireless);
            }

            function toggleMeses() {
                const selectPermanencia = document.getElementById("contrato_permanencia");
                const divMeses = document.getElementById("div_meses");
                if (selectPermanencia && divMeses) {
                    if (selectPermanencia.value === "1") {
                        divMeses.classList.remove("d-none");
                    } else {
                        divMeses.classList.add("d-none");
                    }
                }
            }

            // --- Inicialización ---

            // Visibilidad Tecnología
            var tecnologiaSelect = document.getElementById('tecnologia');
            if (tecnologiaSelect) {
                visibilidad(tecnologiaSelect);
                // Bind change event manually if not handled by attribute
                $('#tecnologia').on('change', function() {
                    visibilidad(this);
                });
            }

            // Permanencia
            const selectPermanencia = document.getElementById("contrato_permanencia");
            if(selectPermanencia) {
                toggleMeses();
                $('#contrato_permanencia').on('change', toggleMeses);
            }

            // Mascara MAC
            $('#mac_address').mask('AA:AA:AA:AA:AA:AA', {
                'translation': {
                    A: { pattern: /[0-9a-fA-F]/ }
                },
            });

            // ToggleCamposDHCP
            if(typeof toggleCamposDHCP === 'function') {
                toggleCamposDHCP();
            }

            // --- Toggle Switch Handlers (Direct Binding + Delegation Backup) ---
            
            function updateProrrateo() {
                var isChecked = $('#prorrateo').is(':checked');
                $('#prorrateo_label').text(isChecked ? 'Habilitado' : 'Deshabilitado');
            }
            $('#prorrateo').change(updateProrrateo);
            // Ejecutar al inicio
            updateProrrateo();


            function updateIva() {
                 var isChecked = $('#iva_factura').is(':checked');
                $('#iva_factura_label').text(isChecked ? 'Si' : 'No');
            }
            $('#iva_factura').change(updateIva);
            updateIva();


            function updateFactPrimerMes() {
                var isChecked = $('#fact_primer_mes').is(':checked');
                $('#fact_primer_mes_label').text(isChecked ? 'Si' : 'No');
            }
            $('#fact_primer_mes').change(updateFactPrimerMes);
            updateFactPrimerMes();


            function updateSuspension() {
                var isChecked = $('#tipo_suspension_no').is(':checked');
                $('#tipo_suspension_no_label').text(isChecked ? 'Si' : 'No');
                
                if(isChecked) {
                    $('#div_fecha_desde_nosuspension').removeClass('d-none');
                    $('#div_fecha_hasta_nosuspension').removeClass('d-none');
                } else {
                    $('#div_fecha_desde_nosuspension').addClass('d-none');
                    $('#div_fecha_hasta_nosuspension').addClass('d-none');
                }
            }
            $('#tipo_suspension_no').change(updateSuspension);
            updateSuspension();

            console.log('Create JS Initialization Complete');
        });
        
        // Función global para cargar puertos NAP (se mantiene fuera del ready si es llamada por onclick en HTML, aunque lo ideal es que esté dentro)
        // Se deja global por si acaso se llama desde algún otro lado
        function cargarPuertosNap() {
            var cajaNapId = $('#cajanap_id').val();

            if (!cajaNapId || cajaNapId == '') {
                $('#div_puerto_nap').hide();
                $('#cajanap_puerto').val('').selectpicker('refresh');
                return;
            }

            if (window.location.pathname.split("/")[1] === "software") {
				var url='/software/caja-naps/' + cajaNapId + '/puertos-disponibles';
			}else{
				var url = '/caja-naps/' + cajaNapId + '/puertos-disponibles';
			}

            $.ajax({
                url: url,
                method: 'GET',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(data) {
                    $('#cajanap_puerto').empty();
                    $('#cajanap_puerto').append('<option value="">Seleccione un puerto</option>');

                    if (data.puertos_disponibles && data.puertos_disponibles.length > 0) {
                        $.each(data.puertos_disponibles, function(index, puerto) {
                            $('#cajanap_puerto').append('<option value="' + puerto + '">Puerto ' + puerto + '</option>');
                        });
                        $('#div_puerto_nap').show();
                    } else {
                        $('#div_puerto_nap').hide();
                        Swal.fire({
                            title: 'Sin puertos disponibles',
                            text: 'Esta caja NAP no tiene puertos disponibles',
                            type: 'warning',
                            showConfirmButton: false,
                            timer: 3000
                        });
                    }

                    $('#cajanap_puerto').selectpicker('refresh');
                },
                error: function() {
                    $('#div_puerto_nap').hide();
                    Swal.fire({
                        title: 'Error',
                        text: 'No se pudieron cargar los puertos disponibles',
                        type: 'error',
                        showConfirmButton: false,
                        timer: 3000
                    });
                }
            });
        }
    </script>
@endsection
