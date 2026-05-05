@extends('layouts.app')

@section('content')

    <style>
        .configuracion {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
            /* Overriding row margins from bootstrap if applied */
            margin-left: 0;
            margin-right: 0;
            width: 100%;
        }

        .configuracion > div.enlaces {
            background-color: #ffffff;
            border: 1px solid #eef2f7;
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            padding: 1.8rem 1.5rem;
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            /* Overriding bootstrap col-sm-3 styles to let Grid handle it */
            max-width: none !important;
            flex: none !important;
            width: auto !important;
            margin-bottom: 0 !important;
        }

        .configuracion > div.enlaces::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 5px;
            background: linear-gradient(90deg, #022454, #007bff);
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .configuracion > div.enlaces:hover {
            transform: translateY(-6px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.08);
            border-color: #dce4ec;
        }

        .configuracion > div.enlaces:hover::before {
            opacity: 1;
        }

        .configuracion > div.enlaces h4.card-title {
            color: #022454;
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.6rem;
            letter-spacing: -0.3px;
        }

        .configuracion > div.enlaces p {
            color: #6c757d;
            font-size: 0.88rem;
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }

        .enlaces a {
            display: flex;
            align-items: center;
            padding: 0.65rem 0.5rem;
            color: #495057;
            text-decoration: none;
            font-size: 0.92rem;
            font-weight: 500;
            border-bottom: 1px solid #f1f3f5;
            transition: all 0.25s ease;
            position: relative;
            border-radius: 6px;
        }

        .enlaces a::before {
            content: "\f105";
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            margin-right: 12px;
            color: #adb5bd;
            font-size: 1rem;
            transition: all 0.25s ease;
        }

        .enlaces a:hover {
            color: #007bff;
            padding-left: 0.9rem;
            background-color: #f4f8fb;
            border-bottom-color: transparent;
        }

        .enlaces a:hover::before {
            color: #007bff;
            transform: translateX(3px);
        }
        
        .enlaces a:last-of-type {
            border-bottom: none;
        }

        .enlaces hr.nomina {
            width: 100%;
            margin: 0.8rem 0;
            border-top: 1px dashed #dee2e6;
        }

        .enlaces br {
            display: none;
        }

        .enlaces input[type="hidden"] {
            display: none;
        }

        /* --- Premium Search Bar Styles --- */
        .buscador-premium {
            position: relative;
            max-width: 700px;
            margin: 0 auto;
            border-radius: 50px;
        }

        .buscador-premium input {
            width: 100%;
            padding: 1.2rem 1.5rem 1.2rem 3.5rem;
            font-size: 1.05rem;
            color: #333;
            background-color: #ffffff;
            border: 2px solid #eef2f7;
            border-radius: 50px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.04);
            transition: all 0.3s ease;
            outline: none;
        }

        .buscador-premium:focus-within input {
            border-color: #007bff;
            box-shadow: 0 10px 35px rgba(0, 123, 255, 0.12);
            transform: translateY(-2px);
        }

        .buscador-premium input::placeholder {
            color: #9ea8b5;
        }

        .buscador-premium i {
            position: absolute;
            top: 50%;
            left: 1.5rem;
            transform: translateY(-50%);
            color: #aab3cc;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            pointer-events: none;
            z-index: 10;
        }

        .buscador-premium:focus-within i {
            color: #007bff;
            transform: translateY(-50%) translateY(-2px);
        }

        .buscador-help {
            text-align: center;
            color: #8c98a4;
            font-size: 0.88rem;
            margin-top: 1rem;
            font-weight: 500;
        }
    </style>
    <div class="row card-description">
        @if(Session::has('success'))
            <div class="col-md-12">
                <div class="alert alert-success" style="margin-top:10px;">
                    <i class="fas fa-check-circle"></i> {{ Session::get('success') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>
        @endif
        <div class="col-sm-4" style="text-align: center;">
            <img class="img-responsive"
                src="{{ asset('images/Empresas/Empresa' . Auth::user()->empresa()->id . '/' . Auth::user()->empresa()->logo) }}"
                alt="" style="max-width: 100%; max-width: 200px;">
        </div>
        <div class="col-sm-8">
            <p class="card-title"> <span class="text-primary">Empresa:</span> {{ Auth::user()->empresa()->nombre }} <br>
                <span class="text-primary">{{ Auth::user()->empresa()->tip_iden() }}:</span>
                {{ Auth::user()->empresa()->nit }}@if (Auth::user()->empresa()->dv)
                    -{{ Auth::user()->empresa()->dv }}
                @endif
                <br>
                <span class="text-primary">Tipo de Persona:</span> {{ Auth::user()->empresa()->tipo_persona() }}<br>
                <span class="text-primary">Teléfono:</span> {{ Auth::user()->empresa()->telefono }}<br>
                @if (Auth::user()->empresa()->whatsapp)
                    <span class="text-primary">Whatsapp:</span> {{ Auth::user()->empresa()->whatsapp }}<br>
                @endif
                @if (Auth::user()->empresa()->soporte)
                    <span class="text-primary">Soporte:</span> {{ Auth::user()->empresa()->soporte }}<br>
                @endif
                @if (Auth::user()->empresa()->ventas)
                    <span class="text-primary">Ventas:</span> {{ Auth::user()->empresa()->ventas }}<br>
                @endif
                @if (Auth::user()->empresa()->finanzas)
                    <span class="text-primary">Finanzas:</span> {{ Auth::user()->empresa()->finanzas }}<br>
                @endif

                <span class="text-primary">Dirección:</span> {{ Auth::user()->empresa()->direccion }}<br>
                <span class="text-primary">Correo Electrónico:</span> {{ Auth::user()->empresa()->email }}<br>
                @if (!Auth::user()->empresa()->suscripcion()->ilimitado)
                    <span class="text-primary">Suscripción Integra Colombia:</span>
                    {{ date('d-m-Y', strtotime(Auth::user()->empresa()->suscripcion()->fec_corte)) }}
            </p>
            @endif
        </div>
        <div class="col-sm-8 offset-md-2 {{ $empresa->nomina ? 'd-none' : '' }}" id="alerta_nomina">
            <div class="alert alert-info" role="alert"
                style="color: #d08f50;background-color: #d08f5026;border-color: #d08f50;">
                <h4 class="alert-heading font-weight-bold">SUGERENCIA</h4>
                <p class="mb-0">Para hacer uso del módulo de <strong>Nómina</strong>, primero debe habilitarlo en la
                    opción <strong>Nómina &gt; Habilitar nómina</strong>.</p>
            </div>
        </div>
    </div>

    @if (auth()->user()->modo_lectura())
        <div class="alert alert-warning text-left" role="alert">
            <h4 class="alert-heading text-uppercase">Integra Colombia: Suscripción Vencida</h4>
            <p>Si desea seguir disfrutando de nuestros servicios adquiera alguno de nuestros planes.</p>
            <p>Medios de pago Nequi: 3026003360 Cuenta de ahorros Bancolombia 42081411021 CC 1001912928 Ximena Herrera
                representante legal. Adjunte su pago para reactivar su membresía</p>
        </div>
    @endif
    <div class="row mb-5 mt-4">
        <div class="col-md-12">
            <div class="buscador-premium">
                <i class="fas fa-search"></i>
                <input type="text" id="buscador-configuracion" placeholder="Buscar secciones, permisos, configuraciones o palabras clave..." onkeyup="buscarConfiguracion()">
            </div>
            <div class="buscador-help">
                <i class="far fa-lightbulb mr-1"></i> Filtra rápidamente cualquiera de las opciones de configuración disponibles en el sistema
            </div>
        </div>
    </div>

    <div class="row card-description configuracion" id="grid-configuracion">
        <div class="col-sm-3 enlaces">
            <h4 class="card-title">Empresa</h4>
            <p>Completa la información de tu empresa.</p>
            <a href="{{ route('configuracion.create') }}">Empresa</a> <br>
            <a href="{{ route('usuarios.index') }}">Usuarios</a><br>
            <a href="{{ route('roles.index') }}">Tipos de Usuario</a><br>
            <a href="{{ route('configuracion.servicios') }}">Servicios</a> <br>
            <a href="{{ route('miusuario') }}">Mi perfil</a><br>
            <a href="#" data-toggle="modal" data-target="#seguridad">Seguridad</a><br>
            <a href="javascript:consultasMk()">{{ Auth::user()->empresa()->consultas_mk == 0 ? 'Habilitar':'Deshabilitar' }} consultas a la mikrotik</a><br>
			<input type="hidden" id="consultas_mk" value="{{Auth::user()->empresa()->consultas_mk}}">
        </div>

        @if (isset($_SESSION['permisos']['40']) || isset($_SESSION['permisos']['258']))
            <div class="col-sm-3 enlaces">
                <h4 class="card-title">Facturación</h4>
                <p>Configura la información que se mostrará en tus facturas de venta.</p>
                <a href="{{ route('configuracion.terminos') }}">Términos de pago</a> <br>
                <a href="{{ route('configuracion.numeraciones') }}">Numeraciones</a><br>
                <a href="{{ route('configuracion.numeraciones_dian') }}">Numeraciones DIAN</a><br>
                <a href="{{ route('configuracion.datos') }}">Datos generales</a><br>
                <a href="{{ route('vendedores.index') }}">Vendedores</a><br>
                @if (isset($_SESSION['permisos']['769']))
                    <a href="{{ route('canales.index') }}">Canales de Venta</a><br>
                @endif
                <a href="#" data-toggle="modal" data-target="#periodo_factura">Periodo de Facturación</a><br>
                <a href="#" data-toggle="modal" data-target="#formato_impresion">Formato de Impresión</a><br>
                <a href="{{ route('saldos_iniciales.importar') }}">Importar saldos iniciales</a><br>
                <a href="javascript:facturacionAutomatica()">{{ Auth::user()->empresa()->factura_auto == 0 ? 'Habilitar' : 'Deshabilitar' }}
                    Facturación Automática</a><br>
                <input type="hidden" id="facturaAuto" value="{{ Auth::user()->empresa()->factura_auto }}">
                <a href="javascript:saldoFavorAutomatico()">{{ Auth::user()->empresa()->aplicar_saldofavor == 0 ? 'Habilitar' : 'Deshabilitar' }}
                    aplicación de saldos a favor automático</a><br>
                <input type="hidden" id="saldofavAuto" value="{{ Auth::user()->empresa()->aplicar_saldofavor }}">
                <a href="javascript:prorrateo()">{{ Auth::user()->empresa()->prorrateo == 0 ? 'Habilitar' : 'Deshabilitar' }}
                    Prorrateo</a><br>
                <input type="hidden" id="prorrateoid" value="{{ Auth::user()->empresa()->prorrateo }}">
                <a href="#" data-toggle="modal" data-target="#generar_prorrateo_modal">Generar Facturas prorrateadas</a><br>
                <a href="javascript:actDescEfecty()">{{ Auth::user()->empresa()->efecty == 0 ? 'Habilitar' : 'Deshabilitar' }}
                    Efecty</a><br>
                <input type="hidden" id="efectyid" value="{{ Auth::user()->empresa()->efecty }}">
                <a href="javascript:facturacionSmsAutomatica()">{{ Auth::user()->empresa()->factura_sms_auto == 0 ? 'Habilitar' : 'Deshabilitar' }}
                    SMS automaticos</a><br>
                <a href="javascript:periodoTirilla()">{{ Auth::user()->empresa()->periodo_tirilla == 0 ? 'Habilitar' : 'Deshabilitar' }}
                        periodo en tirilla</a><br>
                <input type="hidden" id="periodoTirilla" value="{{ Auth::user()->empresa()->periodo_tirilla }}">
                <a href="javascript:envioWppIngreso()">{{ Auth::user()->empresa()->envio_wpp_ingreso == 0 ? 'Habilitar' : 'Deshabilitar' }}
                    envio de tirilla por wpp en recibo de caja</a><br>
                <input type="hidden" id="envioWppIngreso" value="{{ Auth::user()->empresa()->envio_wpp_ingreso }}">
            </div>

            <div class="col-sm-3 enlaces">
                <h4 class="card-title">Impuestos</h4>
                <p>Define aquí los tipos de impuestos y retenciones que aplicas a tus facturas de venta.</p>
                <a href="{{ route('impuestos.index') }}">Impuestos</a> <br>
                <a href="{{ route('retenciones.index') }}">Retenciones</a><br>
                <a href="{{ route('autoretenciones.index') }}">Autoretenciones</a><br>
            </div>

            <div class="col-sm-3 enlaces">
                <h4 class="card-title">Contactos</h4>
                <p>Registra aqui referencias para tus contactos.</p>
                <a href="{{ route('tiposempresa.index') }}">Tipos de Contactos</a> <br>
                <a href="{{ route('barrios.index') }}">Barrios</a> <br>
                <a href="{{ route('saldos_favor.importar') }}">Importación de saldos a favor</a> <br>
            </div>

            {{-- Agregando campos adicionales a contactos --}}
            <div class="col-sm-3 enlaces">
                <h4 class="card-title">campos adicionales a Contactos</h4>
                <p>Añade aqui campos adicionales para el registro de tus contactos.</p>
                <a href="{{ route('contact.new') }}">Añadir campos</a> <br>
            </div>
            {{-- fin del codigo --}}

            @if (isset($_SESSION['permisos']['845']))
                <div class="col-sm-3 enlaces">
                    <h4 class="card-title">Contratos</h4>
                    <p>Gestiona y organiza las configuraciones de contratos.</p>
                    <a href="#" data-toggle="modal" data-target="#config_clausula">Definir Monto de Clausula de
                        Permanencia</a><br>
                    @if (isset($_SESSION['permisos']['751']))
                        <a href="javascript:parametrosContratoDigital();">Parámetros Contrato Digital</a><br>

                        <a href="javascript:facturacionCronAbiertas()">{{ Auth::user()->empresa()->cron_fact_abiertas == 0 ? 'Habilitar' : 'Deshabilitar' }}
                            facturacion automatica fact. abiertas</a><br>
                        <input type="hidden" id="cronAbierta" value="{{ Auth::user()->empresa()->cron_fact_abiertas }}">

                        <a href="javascript:facturacionContratosOff()">{{ Auth::user()->empresa()->factura_contrato_off == 0 ? 'Habilitar':'Deshabilitar' }} facturas en contratos deshabilitados</a><br>
			            <input type="hidden" id="factura_contrato_off" value="{{Auth::user()->empresa()->factura_contrato_off}}">

                        <a href="javascript:queriesDhcpSmartolt()">{{ Auth::user()->empresa()->queries_dhcp_smartolt == 0 ? 'Habilitar':'Deshabilitar' }} Disable/Enable ONU para contratos DHCP</a><br>
			            <input type="hidden" id="queries_dhcp_smartolt" value="{{Auth::user()->empresa()->queries_dhcp_smartolt}}">

                        <a href="javascript:separarNumeracionContrato()">{{ Auth::user()->empresa()->separar_numeracion == 0 ? 'Separar':'Unificar' }} Numeración por servidor</a><br>
			            <input type="hidden" id="separar_numeracion" value="{{Auth::user()->empresa()->separar_numeracion}}">

                        {{-- Valor de reconexion generico --}}
                        <a href="javascript:reconexionGenerica()">{{ Auth::user()->empresa()->reconexion_generica == 0 ? 'Habilitar' : 'Deshabilitar' }}
                            Valor de reconexión genérico</a><br>
                        <input type="hidden" id="reconexionGenerica"
                            value="{{ Auth::user()->empresa()->reconexion_generica }}">
                        @if (Auth::user()->empresa()->reconexion_generica == 1)
                            <a href="#" data-toggle="modal" data-target="#config_reconexion">Configurar reconexion
                                genérica</a><br>
                        @endif

                        <a href="javascript:activeConnectionSecret()">{{ Auth::user()->empresa()->activeconn_secret == 0 ? 'Habilitar' : 'Deshabilitar' }}
                            consultas active connection y secret</a><br>
                        <input type="hidden" id="activeconn_secret" value="{{ Auth::user()->empresa()->activeconn_secret }}">

                        <a href="{{ route('configuracion.etiquetas_automaticas') }}">Etiquetas automáticas</a><br>

                    @endif
                </div>
            @endif

            <div class="col-sm-3 enlaces">
                <h4 class="card-title">Documentos Soporte</h4>
                <p>Configura la información de los documentos soporte por las compras que realices a sujetos no obligados a
                    expedir factura.</p>
                @if ($empresa->equivalente == 0)
                    <a href="#" onclick="docEquivalente()">Habilitar documentos soporte</a><br>
                    <input type="hidden" id="docEquivalente" value="{{ $empresa->equivalente }}">
                @else
                    <a href="#" onclick="docEquivalente()">Deshabilitar documentos soporte</a><br>
                    <input type="hidden" id="docEquivalente" value="{{ $empresa->equivalente }}">
                    <a class="doc-equivalente-class"
                        href="{{ route('configuracion.numeraciones_equivalentes') }}">Numeraciones</a><br>
                @endif
            </div>

            <div class="col-sm-3 enlaces">
                <h4 class="card-title">Categorias</h4>
                <p>Organice a su medida el plan único de cuentas.</p>
                {{-- <a href="{{route('categorias.index')}}">Gestionar Categorias</a> <br> --}}
                <a href="{{ route('puc.index') }}">Gestionar PUC</a> <br>
                <a href="{{ route('formapago.index') }}">Formas de Pago</a> <br>
                <a href="{{ route('anticipo.index') }}">Anticipos</a> <br>
                {{-- <a href="{{route('productoservicio.index')}}">Productos y Servicios</a> <br> --}}
                <a href="{{ route('saldoinicial.index') }}">Comprobantes contables</a> <br>
            </div>
        @endif

        @if (isset($_SESSION['permisos']['737']))
            <div class="col-sm-3 enlaces">
                <h4 class="card-title">Tipos de Gastos</h4>
                <p>Organice los tipos de gastos que utilizará su empresa.</p>
                @if (isset($_SESSION['permisos']['737']))
                    <a href="{{ route('tipos-gastos.index') }}">Tipos de Gastos</a> <br>
                @endif
            </div>
        @endif

        <div class="col-sm-3 enlaces">
            <h4 class="card-title">Gestión de Puertos</h4>
            <p>Configura y organiza los puertos de conexión.</p>
            <a href="{{ route('puertos-conexion.index') }}">Puertos de Conexión</a><br>
        </div>

        @if (isset($_SESSION['permisos']['752']))
            <div class="col-sm-3 enlaces">
                <h4 class="card-title">Gestión Servidor de Correo</h4>
                <p>Configura y organiza el servidor de correo externo para el envío de email y notificaciones.</p>
                <a href="{{ route('servidor-correo.index') }}">Servidor de Correo</a><br>
            </div>
        @endif

        <div class="col-sm-3 enlaces">
            <h4 class="card-title">Nómina</h4>
            <p>Gestione la nómina electrónicamente de los empleados que trabajan en su empresa.</p>
            <input type="hidden" name="estado_nomina" id="estado_nomina" value="{{ $empresa->nomina }}">
            <a id="texto_nomina"
                href="javascript:habilitarNomina()">{{ $empresa->nomina ? 'Deshabilitar' : 'Habilitar' }} nómina</a> <br>
            <a id="preferencia_pago" href="{{ route('nomina.preferecia-pago') }}"
                class="{{ $empresa->nomina ? '' : 'd-none' }}">Preferencias de pago</a> <br>
            <a id="nomina_numeracion" href="{{ route('numeraciones_nomina.index') }}"
                class="{{ $empresa->nomina ? '' : 'd-none' }}">Numeraciones</a> <br>
            <a id="nomina_calculos" href="{{ route('configuraicon.calculosnomina') }}"
                class="{{ $empresa->nomina ? '' : 'd-none' }}">Cálculos fijos</a> <br>
            {{-- <a href="#" onclick="nominaDIAN()" id="div_nominaDIAN"  class="{{$empresa->nomina ? '' : 'd-none'}}">{{ Auth::user()->empresaObj->nomina_dian == 0 ? 'Activar' : 'Desactivar' }} Nómina Electrónica por la DIAN</a><br> --}}
            <input type="hidden" id="nominaDIAN" value="{{ Auth::user()->empresaObj->nomina_dian }}">
            <a id="nomina_asistentes" href="{{ route('nomina-dian.asistente') }}"
                class="{{ $empresa->nomina ? '' : 'd-none' }}">Asistente de habilitación DIAN</a> <br>
            <hr class="nomina {{ $empresa->nomina ? '' : 'd-none' }}">
            <a id="planes_nomina" href="{{ route('nomina.suscripciones') }}"
                class="{{ $empresa->nomina ? '' : 'd-none' }}">Planes de Suscripción</a> <br>
        </div>

        @if (isset($_SESSION['permisos']['762']) || isset($_SESSION['permisos']['763']) || isset($_SESSION['permisos']['764']))
            <div class="col-sm-3 enlaces">
                <h4 class="card-title">Integraciones de Servicios</h4>
                <p>Configure cada uno de los servicios disponibles para darle uso en NetworkSoft</p>
                @if (isset($_SESSION['permisos']['762']))
                    <a href="{{ route('integracion-sms.index') }}">Mensajería</a><br>
                @endif
                @if (isset($_SESSION['permisos']['763']))
                    <a href="{{ route('integracion-pasarelas.index') }}">Pasarelas de Pago</a><br>
                @endif
                @if (isset($_SESSION['permisos']['777']))
                    <a href="{{ route('integracion-whatsapp.index') }}">WhatsApp (CallMEBot)</a><br>
                @endif
                @if (isset($_SESSION['permisos']['798']))
                    <a href="{{ route('integracion-gmaps.index') }}">Google Maps</a><br>
                @endif
                @if (isset($_SESSION['permisos']['764']) && Auth::user()->nombres == 'Desarrollo')
                    <a href="#">Troncal SIP</a><br>
                @endif
                @if (isset($_SESSION['permisos']['759']))
                    <a href="#" data-toggle="modal" data-target="#config_olt">Configurar OLT</a><br>
                @endif
                    <a href="javascript:chatIA()">{{ Auth::user()->empresa()->chat_ia == 0 ? 'Habilitar':'Deshabilitar' }} Chat IA</a><br>
			        <input type="hidden" id="chat_ia" value="{{Auth::user()->empresa()->chat_ia}}">

            </div>
        @endif

        <div class="col-sm-3 enlaces">
            <h4 class="card-title">Oficinas</h4>
            <p>Configura la información relacionada a las oficinas de tu empresa.</p>
            <a href="javascript:actDescOficina()">{{ Auth::user()->empresa()->oficina == 0 ? 'Habilitar' : 'Deshabilitar' }}
                uso de oficinas en NetworkSoft</a><br>
            <input type="hidden" id="oficinaid" value="{{ Auth::user()->empresa()->oficina }}">
        </div>

        @if (!Auth::user()->empresa()->suscripcion()->ilimitado)
            <div class="col-sm-3 enlaces">
                <h4 class="card-title">Planes</h4>
                <p>Elige el plan que quieres tener y configura cómo quieres pagarlo.</p>
                <a href="{{ route('listadoPagos.index') }}">Pagos de Suscripcion</a> <br>
                {{-- @if ($personalPlan)
                <a href="{{route('planes.personalizado')}}">Plan personalizado</a> <br>
            @endif
			<a href="{{route('PlanesPagina.index')}}">Planes</a> <br>
			<a href="{{route('PlanesPagina.index')}}">Metodos de pago</a> <br> --}}
            </div>
        @endif

        @if (isset($_SESSION['permisos']['750']))
            <div class="col-sm-3 enlaces">
                <h4 class="card-title">Organización de Tablas</h4>
                <p>Configura y organiza los campos de las tablas.</p>
                <a href="#" data-toggle="modal" data-target="#config_modulos">Organización de Tablas</a><br>
                {{-- <a href="{{route('campos.organizar', 3)}}">Inventario</a><br> --}}
                {{-- <a href="{{route('campos.organizar', 8)}}">Pagos Recurrentes</a><br> --}}
                <hr class="nomina">
                <a href="#" data-toggle="modal" data-target="#nro_registro">Configurar Nro registros a
                    mostrar</a><br>
            </div>
        @endif

        <div class="col-sm-3 enlaces">
            <h4 class="card-title">Documentación</h4>
            <p>Documentos y guías de uso NetworkSoft.</p>
            <a href="https://networksoft.online/software/images/Empresas/Empresa1/contabilidad.pdf"
                target="_blank">Contabilidad</a> <br>
            <a href="{{ asset('images/Empresas/Empresa1/Gestión Servidor De Correo.pdf') }}" target="_blank">Servidor De
                Correo</a> <br>
        </div>

        <div class="col-sm-3 enlaces">
            <h4 class="card-title">Limpieza del Sistema</h4>
            <p>Limpia los archivos temporales y caché del sistema.</p>
            <a href="javascript:limpiarCache()">Limpiar caché</a><br>
        </div>

        <div class="col-sm-3 enlaces">
            <h4 class="card-title">Configuración Siigo</h4>
            <p>Conecta y mapea la información básica para siigo.</p>
            @if (isset($_SESSION['permisos']['759']))
                <a href="#" data-toggle="modal" data-target="#config_siigo">Conexión Siigo</a><br>
                @if ($empresa->token_siigo != null || $empresa->token_siigo != '')
                    <a href="{{ route('siigo.mapeo_impuestos') }}">Impuestos - Retenciones</a><br>
                    <a href="{{ route('siigo.mapeo_vendedores') }}">Vendedores</a><br>
                    <a href="{{ route('siigo.mapeo_productos') }}">Productos</a><br>
                    <a href="javascript:pagoSiigo()">
                        {{ Auth::user()->empresa()->pago_siigo == 0 ? 'Habilitar' : 'Deshabilitar' }}
                        Enviar a siigo al crear pago
                    </a>
                    <input type="hidden" id="pagosiigo" value="{{ Auth::user()->empresa()->pago_siigo }}">
                    <br>
                    <a href="javascript:siigoEmitida()">
                        {{ (Auth::user()->empresa()->siigo_emitida ?? 0) == 0 ? 'Habilitar' : 'Deshabilitar' }}
                        Enviar a siigo con estado emitido
                    </a>
                    <input type="hidden" id="siigoemitida" value="{{ Auth::user()->empresa()->siigo_emitida ?? 0 }}">

                @elseif($empresa->pago_siigo == 1)
                <a href="javascript:pagoSiigo()">
                    {{ Auth::user()->empresa()->pago_siigo == 0 ? 'Habilitar' : 'Deshabilitar' }}
                    Enviar a siigo al crear pago
                </a>
                <br>
                <a href="javascript:siigoEmitida()">
                    {{ (Auth::user()->empresa()->siigo_emitida ?? 0) == 0 ? 'Habilitar' : 'Deshabilitar' }}
                    Enviar a siigo con estado emitido
                </a>
                <input type="hidden" id="siigoemitida" value="{{ Auth::user()->empresa()->siigo_emitida ?? 0 }}">
                @endif
            @endif
        </div>

        <div class="col-sm-3 enlaces">
            <h4 class="card-title">Configuración Whatsapp Meta</h4>
            <p>Configura la integración con WhatsApp Meta Business Account.</p>
            <a href="#" data-toggle="modal" data-target="#config_whatsapp_meta">Ingresar whatsapp business id</a><br>
            <a href="javascript:obtenerPlantillasWhatsapp()">Obtener plantillas whatsapp meta</a><br>
            <a href="#" data-toggle="modal" data-target="#config_plantilla_factura_whatsapp">Configurar plantilla por defecto para facturas</a><br>
            <a href="#" data-toggle="modal" data-target="#config_plantilla_tirilla_whatsapp">Configurar plantilla por defecto tirilla</a><br>
            <a href="javascript:registrarNumeroWhatsappMeta()">Registrar número de teléfono WhatsApp</a><br>
            <a href="javascript:suscribirseCanalWhatsapp()">Suscribirse al canal</a><br>
            <a href="{{ route('instances.index') }}">Instancia</a><br>
        </div>


    </div>

    {{-- MÓDULOS --}}
    <div class="modal fade" id="config_modulos" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Organización de Tablas</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 text-center">
                            <p>Seleccione el módulo a donde requiere hacer la configuración de la tabla</p>
                        </div>
                        <div class="col-md-6">
                            <a href="{{ route('campos.organizar', 1) }}">Contactos</a><br>
                            <a href="{{ route('campos.organizar', 2) }}">Contratos</a><br>
                            <a href="{{ route('campos.organizar', 4) }}">Factura de Venta</a><br>
                            <a href="{{ route('campos.organizar', 5) }}">Pagos / Ingresos</a><br>
                            <a href="{{ route('campos.organizar', 9) }}">Descuentos</a><br>
                            <a href="{{ route('campos.organizar', 6) }}">Factura de Proveedores</a><br>
                            <a href="{{ route('campos.organizar', 7) }}">Pagos / Egresos</a><br>
                            <a href="{{ route('campos.organizar', 18) }}">Notas de Crédito</a><br>
                            <a href="{{ route('campos.organizar', 19) }}">Cotizaciones</a><br>
                            <a href="{{ route('campos.organizar', 20) }}">Remisiones</a><br>
                        </div>
                        <div class="col-md-6">
                            <a href="{{ route('campos.organizar', 10) }}">Planes de Velocidad</a><br>
                            <a href="{{ route('campos.organizar', 11) }}">Promesas de Pago</a><br>
                            <a href="{{ route('campos.organizar', 12) }}">Radicados</a><br>
                            <a href="{{ route('campos.organizar', 13) }}">Monitor Blacklist</a><br>
                            <a href="{{ route('campos.organizar', 14) }}">Ventas Externas</a><br>
                            <a href="{{ route('campos.organizar', 15) }}">Mikrotik</a><br>
                            <a href="{{ route('campos.organizar', 16) }}">Bancos</a><br>
                            <a href="{{ route('campos.organizar', 17) }}">Oficinas</a><br>
                            <a href="{{ route('campos.organizar', 21) }}">Produtos</a><br>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    {{-- /MÓDULOS --}}

    {{-- SEGURIDAD --}}
    <div class="modal fade" id="seguridad" role="dialog">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"></h4>
                </div>
                <div class="modal-body">
                    <p>Si deseas cerrar sesión en todos los dispositivos que has iniciado sesión haz click en el enlace</p>
                    <br>
                    <a href="{{ route('home.closeallsession') }}">Cerrar sesión en todos los dispositivos</a>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    {{-- /SEGURIDAD --}}

    {{-- CONFIGURACION RECONEXION GENERICA --}}
    <div class="modal fade" id="config_reconexion" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"></h4>
                </div>
                <div class="modal-body">
                    <form method="POST" action="{{ route('configuracion.updatereconexiongenerica') }}"
                        style="padding: 2% 3%;    " role="form" class="forms-sample" novalidate id="form">
                        {{ csrf_field() }}
                        <div class="row">
                            <div class="col-md-12 form-group">
                                <label class="control-label">Días para cobro adicional</label>
                                <input type="number" class="form-control" id="dias_reconexion_generica"
                                    name="dias_reconexion_generica"
                                    value="{{ Auth::user()->empresa()->dias_reconexion_generica }}" maxlength="200">
                                <span class="help-block error">
                                    <strong>{{ $errors->first('dias_reconexion_generica') }}</strong>
                                </span>
                            </div>
                            <div class="col-md-12 form-group">
                                <label class="control-label">Precio del cobro adicional</label>
                                <input type="text" class="form-control" id="precio_reconexion_generica"
                                    name="precio_reconexion_generica" required=""
                                    value="{{ Auth::user()->empresa()->precio_reconexion_generica }}" maxlength="200">
                                <span class="help-block error">
                                    <strong>{{ $errors->first('precio_reconexion_generica') }}</strong>
                                </span>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                    <a href="javascript:updateReconexionGenerica()" class="btn btn-success">Guardar</A>
                </div>
            </div>
        </div>
    </div>
    {{-- CONFIGURACION RECONEXION GENERICA --}}

    {{-- CONFIGURACION OLT --}}
    <div class="modal fade" id="config_olt" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Configuración Smart OLT</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="{{ route('servicio.store') }}" style="padding: 2% 3%;    "
                        role="form" class="forms-sample" novalidate id="form">
                        {{ csrf_field() }}
                        <div class="row">
                            <div class="col-md-12 form-group">
                                <label class="control-label">URL (ejemplo: https://dominio.smartolt.com)</label>
                                <input type="text" class="form-control" id="adminOLT" name="adminOLT"
                                    value="{{ Auth::user()->empresa()->adminOLT }}" maxlength="200">
                                <span class="help-block error">
                                    <strong>{{ $errors->first('adminOLT') }}</strong>
                                </span>
                            </div>
                            <div class="col-md-12 form-group">
                                <label class="control-label">ApiKey Smart OLT</label>
                                <input type="text" class="form-control" id="smartOLT" name="smartOLT"
                                    required="" value="{{ Auth::user()->empresa()->smartOLT }}" maxlength="200">
                                <span class="help-block error">
                                    <strong>{{ $errors->first('smartOLT') }}</strong>
                                </span>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                    <a href="javascript:configuracionOLT()" class="btn btn-success">Guardar</A>
                </div>
            </div>
        </div>
    </div>
    {{-- /CONFIGURACION OLT --}}

    {{-- CONFIGURACION SIIGO --}}
    <div class="modal fade" id="config_siigo" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Configuración Siigo</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="{{ route('servicio.store') }}" style="padding: 2% 3%;    "
                        role="form" class="forms-sample" novalidate id="form">
                        {{ csrf_field() }}
                        <div class="row">
                            <div class="col-md-12 form-group">
                                <label class="control-label">Usuario Siigo</label>
                                <input type="text" class="form-control" id="usuario_siigo" name="usuario_siigo"
                                    required="" value="{{ Auth::user()->empresa()->usuario_siigo }}"
                                    maxlength="200">
                                <span class="help-block error">
                                    <strong>{{ $errors->first('usuario_siigo') }}</strong>
                                </span>
                            </div>
                            <div class="col-md-12 form-group">
                                <label class="control-label">API Key</label>
                                <input type="text" class="form-control" id="api_key_siigo" name="api_key_siigo"
                                    value="{{ Auth::user()->empresa()->api_key_siigo }}" maxlength="200">
                                <span class="help-block error">
                                    <strong>{{ $errors->first('api_key_siigo') }}</strong>
                                </span>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                    @if(Auth::user()->empresa()->api_key_siigo)
                    <button type="button" class="btn btn-outline-danger" onclick="quitarSiigo()">Quitar Conexión</button>
                    @endif
                    <a href="javascript:configuracionSiigo()" class="btn btn-success">Guardar</A>
                </div>
            </div>
        </div>
    </div>
    {{-- /CONFIGURACION SIIGO --}}

    {{-- CONFIGURACION WHATSAPP META --}}
    <div class="modal fade" id="config_whatsapp_meta" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Configuración Whatsapp Meta</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" style="padding: 2% 3%;" role="form" class="forms-sample" novalidate id="form_whatsapp_meta">
                        {{ csrf_field() }}
                        <div class="row">
                            <div class="col-md-12 form-group">
                                <label class="control-label">WhatsApp Business Account ID</label>
                                <input type="text" class="form-control" id="whatsapp_business_account_id" name="whatsapp_business_account_id"
                                    required="" value="{{ Auth::user()->empresa()->whatsapp_business_account_id }}"
                                    maxlength="200">
                                <span class="help-block error">
                                    <strong>{{ $errors->first('whatsapp_business_account_id') }}</strong>
                                </span>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                    <a href="javascript:guardarWhatsappBusinessId()" class="btn btn-success">Guardar</A>
                </div>
            </div>
        </div>
    </div>
    {{-- /CONFIGURACION WHATSAPP META --}}

    {{-- CONFIGURACION PLANTILLA FACTURA WHATSAPP --}}
    <div class="modal fade" id="config_plantilla_factura_whatsapp" role="dialog">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Configurar Plantilla por Defecto para Facturas</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" style="padding: 2% 3%;" role="form" class="forms-sample" novalidate id="form_plantilla_factura_whatsapp">
                        {{ csrf_field() }}
                        <div class="row">
                            <div class="col-md-12 form-group">
                                <label class="control-label">Plantilla Meta <span class="text-danger">*</span></label>
                                <select class="form-control selectpicker" id="plantilla_meta_factura" name="plantilla_id"
                                    title="Seleccione una plantilla" data-live-search="true" data-size="5"
                                    onchange="cargarPlantillaMetaFactura(this.value)">
                                    <!-- Las opciones se cargarán dinámicamente -->
                                </select>
                                <span class="help-block error">
                                    <strong id="error_plantilla_meta_factura"></strong>
                                </span>
                            </div>
                        </div>

                        <!-- Sección de parámetros dinámicos -->
                        <div class="row" id="parametros-meta-factura" style="display: none;">
                            <div class="col-md-12">
                                <hr class="my-4">
                                <h5><i class="fa fa-sliders"></i> Configuración de Parámetros Dinámicos</h5>
                                <div id="inputs-parametros-factura">
                                    <!-- Los inputs se generarán dinámicamente aquí -->
                                </div>
                            </div>
                        </div>

                        <!-- Preview del mensaje -->
                        <div class="row" id="preview-mensaje-meta-factura" style="display: none;">
                            <div class="col-md-12">
                                <!-- Aquí se mostrará la vista previa dinámicamente -->
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                    <a href="javascript:guardarPlantillaFacturaWhatsapp()" class="btn btn-success">Guardar</a>
                </div>
            </div>
        </div>
    </div>
    {{-- /CONFIGURACION PLANTILLA FACTURA WHATSAPP --}}

    {{-- CONFIGURACION PLANTILLA TIRILLA WHATSAPP --}}
    <div class="modal fade" id="config_plantilla_tirilla_whatsapp" role="dialog">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Configurar Plantilla por Defecto para Tirilla</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" style="padding: 2% 3%;" role="form" class="forms-sample" novalidate id="form_plantilla_tirilla_whatsapp">
                        {{ csrf_field() }}
                        <div class="row">
                            <div class="col-md-12 form-group">
                                <label class="control-label">Plantilla Meta <span class="text-danger">*</span></label>
                                <select class="form-control selectpicker" id="plantilla_meta_tirilla" name="plantilla_id"
                                    title="Seleccione una plantilla" data-live-search="true" data-size="5"
                                    onchange="cargarPlantillaMetaTirilla(this.value)">
                                    <!-- Las opciones se cargarán dinámicamente -->
                                </select>
                                <span class="help-block error">
                                    <strong id="error_plantilla_meta_tirilla"></strong>
                                </span>
                            </div>
                        </div>

                        <!-- Sección de parámetros dinámicos -->
                        <div class="row" id="parametros-meta-tirilla" style="display: none;">
                            <div class="col-md-12">
                                <hr class="my-4">
                                <h5><i class="fa fa-sliders"></i> Configuración de Parámetros Dinámicos</h5>
                                <div id="inputs-parametros-tirilla">
                                    <!-- Los inputs se generarán dinámicamente aquí -->
                                </div>
                            </div>
                        </div>

                        <!-- Preview del mensaje -->
                        <div class="row" id="preview-mensaje-meta-tirilla" style="display: none;">
                            <div class="col-md-12">
                                <!-- Aquí se mostrará la vista previa dinámicamente -->
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                    <a href="javascript:guardarPlantillaTirillaWhatsapp()" class="btn btn-success">Guardar</a>
                </div>
            </div>
        </div>
    </div>
    {{-- /CONFIGURACION PLANTILLA TIRILLA WHATSAPP --}}

    {{-- GENERAR PRORRATEO MASIVO --}}
    <div class="modal fade" id="generar_prorrateo_modal" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Generar Facturas Prorrateadas</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <form method="POST" action="{{ route('configuracion.generar_prorrateo') }}" role="form" class="forms-sample">
                    {{ csrf_field() }}
                    <div class="modal-body">
                        <p>Seleccionar: ¿Desde cuando desea generar el prorrateo?</p>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="control-label">Fecha Inicial <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="fecha" required="" value="{{ date('Y-m-d') }}">
                                <span class="help-block error">
                                    <strong>{{ $errors->first('fecha') }}</strong>
                                </span>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="control-label">Fecha Final <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="fecha_final" required="" value="{{ date('Y-m-d') }}">
                                <span class="help-block error">
                                    <strong>{{ $errors->first('fecha_final') }}</strong>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                        <button type="submit" class="btn btn-success">Generar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    {{-- /GENERAR PRORRATEO MASIVO --}}

    {{-- CANT REGISTRO --}}
    <div class="modal fade show" id="nro_registro" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Configurar Nro registros a mostrar</h4>
                </div>
                <div class="modal-body">
                    <p>Indique la cantidad de registro que quiere cargar por página en cada uno de los listados <a><i
                                data-tippy-content="Por defecto aparecerán 25 registros por página."
                                class="icono far fa-question-circle"></i></a></p>
                    <div class="col-sm-6 offset-sm-3">
                        <select class="form-control selectpicker" name="pageLength" id="val_pageLength" required=""
                            title="Seleccione" data-live-search="true" data-size="5">
                            <option value="10" {{ Auth::user()->empresa()->pageLength == 10 ? 'selected' : '' }}>10
                                registros P/P</option>
                            <option value="25" {{ Auth::user()->empresa()->pageLength == 25 ? 'selected' : '' }}>25
                                registros P/P</option>
                            <option value="50" {{ Auth::user()->empresa()->pageLength == 50 ? 'selected' : '' }}>50
                                registros P/P</option>
                            <option value="100" {{ Auth::user()->empresa()->pageLength == 100 ? 'selected' : '' }}>100
                                registros P/P</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-success" onclick="storePageLength()">Guardar</button>
                </div>
            </div>
        </div>
    </div>
    {{-- /CANT REGISTRO --}}

    {{-- PERIODO FACTURACIÓN --}}
    <div class="modal fade show" id="periodo_factura" tabindex="-1" role="dialog"
        aria-labelledby="exampleModalLabel">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Configurar Periodo de Facturación</h4>
                </div>
                <div class="modal-body">
                    <p>Indique el periodo de su facturación</p>
                    <div class="col-sm-6 offset-sm-3">
                        <select class="form-control selectpicker" name="periodo_facturacion" id="val_periodo_facturacion"
                            required="" title="Seleccione" data-live-search="true" data-size="5">
                            <option value="1"
                                {{ Auth::user()->empresa()->periodo_facturacion == 1 ? 'selected' : '' }}>Mes Anticipado
                            </option>
                            <option value="3"
                                {{ Auth::user()->empresa()->periodo_facturacion == 3 ? 'selected' : '' }}>Mes Actual
                            </option>
                            <option value="2"
                                {{ Auth::user()->empresa()->periodo_facturacion == 2 ? 'selected' : '' }}>Mes Vencido
                            </option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-success" onclick="storePeriodoFacturacion()">Guardar</button>
                </div>
            </div>
        </div>
    </div>
    {{-- /PERIODO FACTURACIÓN --}}

    {{-- FORMATO IMPRESION --}}
    <div class="modal fade show" id="formato_impresion" tabindex="-1" role="dialog"
        aria-labelledby="exampleModalLabel">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Configurar formato de impresión</h4>
                </div>
                <div class="modal-body">
                    <p>Indique el formato de impresión</p>
                    <div class="col-sm-6 offset-sm-3">
                        <select class="form-control selectpicker" name="formato_impresion" id="val_formato_impresion"
                            required="" title="Seleccione" data-live-search="true" data-size="5">
                            <option value="1"
                                {{ Auth::user()->empresa()->formato_impresion == 1 ? 'selected' : '' }}>Formato CRC
                            </option>
                            <option value="2"
                                {{ Auth::user()->empresa()->formato_impresion == 2 ? 'selected' : '' }}>Formato Estándar
                            </option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-success" onclick="storeFormatoImpresion()">Guardar</button>
                </div>
            </div>
        </div>
    </div>
    {{-- /FORMATO IMPRESION --}}

    {{-- CONFIGURACION CLAUSULAS --}}
    <div class="modal fade" id="config_clausula" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"></h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 form-group">
                            <label class="control-label">Indique el monto a establecer de Clausula de Permanencia</label>
                            <input type="number" class="form-control" id="clausula_permanencia"
                                name="clausula_permanencia" value="{{ Auth::user()->empresa()->clausula_permanencia }}"
                                min="0">
                            <span class="help-block error">
                                <strong>{{ $errors->first('clausula_permanencia') }}</strong>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                    <a href="javascript:configurarClausula()" class="btn btn-success">Guardar</A>
                </div>
            </div>
        </div>
    </div>
    {{-- /CONFIGURACION CLAUSULAS --}}

    {{-- /CONFIGURACION CONTRATO DIGITAL --}}
    <div class="modal fade" id="modal_parametrosContratoDigital" tabindex="-1" role="dialog">
        <div class="modal-dialog" style="max-width: 50%;">
            <div class="modal-content">
                <div class="modal-body">
                    <form method="POST" action="{{ route('asignaciones.campos_asignacion') }}" role="form"
                        class="forms-sample" id="form_contrato">
                        @csrf
                        <ul class="nav nav-pills" id="pills-tab" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="pills-asignacion-tab" data-toggle="pill"
                                    href="#pills-asignacion" role="tab" aria-controls="pills-asignacion"
                                    aria-selected="true">Asignación de Contrato</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="pills-contrato-tab" data-toggle="pill" href="#pills-contrato"
                                    role="tab" aria-controls="pills-contrato" aria-selected="false">Contrato
                                    Digital</a>
                            </li>
                        </ul>

                        <hr
                            style="border-top: 1px solid {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }}; margin: .5rem 0rem;">

                        <div class="tab-content mt-4" id="pills-tabContent">
                            <div class="tab-pane fade show active" id="pills-asignacion" role="tabpanel"
                                aria-labelledby="pills-asignacion-tab">
                                <div class="row">
                                    <div class="form-group col-md-6 offset-md-3">
                                        <label class="control-label">Campo Principal</label>
                                        <input type="text" class="form-control" name="campo_1" id="campo_1">
                                        <span class="help-block error">
                                            <strong>{{ $errors->first('campo_1') }}</strong>
                                        </span>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-3">
                                        <label class="control-label">Campo A</label>
                                        <input type="text" class="form-control" name="campo_a" id="campo_a">
                                        <span class="help-block error">
                                            <strong>{{ $errors->first('campo_a') }}</strong>
                                        </span>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label class="control-label">Campo B</label>
                                        <input type="text" class="form-control" name="campo_b" id="campo_b">
                                        <span class="help-block error">
                                            <strong>{{ $errors->first('campo_b') }}</strong>
                                        </span>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label class="control-label">Campo C</label>
                                        <input type="text" class="form-control" name="campo_c" id="campo_c">
                                        <span class="help-block error">
                                            <strong>{{ $errors->first('campo_c') }}</strong>
                                        </span>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label class="control-label">Campo D</label>
                                        <input type="text" class="form-control" name="campo_d" id="campo_d">
                                        <span class="help-block error">
                                            <strong>{{ $errors->first('campo_d') }}</strong>
                                        </span>
                                    </div>

                                    <div class="form-group col-md-3">
                                        <label class="control-label">Campo E</label>
                                        <input type="text" class="form-control" name="campo_e" id="campo_e">
                                        <span class="help-block error">
                                            <strong>{{ $errors->first('campo_e') }}</strong>
                                        </span>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label class="control-label">Campo F</label>
                                        <input type="text" class="form-control" name="campo_f" id="campo_f">
                                        <span class="help-block error">
                                            <strong>{{ $errors->first('campo_f') }}</strong>
                                        </span>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label class="control-label">Campo G</label>
                                        <input type="text" class="form-control" name="campo_g" id="campo_g">
                                        <span class="help-block error">
                                            <strong>{{ $errors->first('campo_g') }}</strong>
                                        </span>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label class="control-label">Campo H</label>
                                        <input type="text" class="form-control" name="campo_h" id="campo_h">
                                        <span class="help-block error">
                                            <strong>{{ $errors->first('campo_h') }}</strong>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="pills-contrato" role="tabpanel"
                                aria-labelledby="pills-contrato-tab">
                                <div class="row">
                                    <div class="form-group col-md-12">
                                        <label class="control-label">Contrato Digital</label>
                                        <textarea class="form-control" name="contrato_digital" id="contrato_digital" rows="6"></textarea>
                                    </div>
                                    <div class="form-group col-md-12 d-none">
                                        <label class="control-label">ANEXO 1</label>
                                        <textarea class="form-control" name="anexo_1" id="anexo_1" rows="6"></textarea>
                                    </div>
                                    <div class="form-group col-md-12 d-none">
                                        <label class="control-label">ANEXO 2</label>
                                        <textarea class="form-control" name="anexo_2" id="anexo_2" rows="6"></textarea>
                                    </div>
                                    <div class="form-group col-md-12 d-none">
                                        <label class="control-label">ANEXO 3</label>
                                        <textarea class="form-control" name="anexo_3" id="anexo_3" rows="6"></textarea>
                                    </div>
                                    <div class="form-group col-md-12 d-none">
                                        <label class="control-label">ANEXO 4</label>
                                        <textarea class="form-control" name="anexo_4" id="anexo_4" rows="6"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-sm-12" style="text-align: right;">
                                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"
                                    id="cancelar">Cancelar</button>
                                <a href="javascript:void(0);" class="btn btn-success" id="guardar">Guardar</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    {{-- /CONFIGURACION CONTRATO DIGITAL --}}
@endsection

@section('scripts')
    <script>
        function docEquivalente() {
            if ($("#docEquivalente").val() == 1) {
                $titleswal = "¿Deshabilitar documentos soporte?";
                $textswal = "Ya no podrá crear documentos soporte desde las facturas de proveedores";
                $confirmswal = "Si, Deshabilitar";
            } else {
                $titleswal = "¿Habilitar documentos soporte?";
                $textswal =
                    "Tendrá la opcion de escoger el tipo de documento equivalente desde crear facturas de proveedores.";
                $confirmswal = "Si, Habilitar";
            }

            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/empresa';
            } else {
                var url = '/empresa';
            }

            Swal.fire({
                title: $titleswal,
                text: $textswal,
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                cancelButtonText: 'Cancelar',
                confirmButtonText: $confirmswal,
            }).then((result) => {
                if (result.value) {

                    $.ajax({
                        url: url + '/configuracion/configuracion_actdesc_equivalentes',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        method: 'post',
                        data: {
                            status: $("#docEquivalente").val()
                        },
                        success: function(data) {

                            if (data == 1) {
                                Swal.fire({
                                    position: 'top-center',
                                    type: 'success',
                                    title: 'Documentos Soporte habilitados',
                                    showConfirmButton: false,
                                    timer: 2500
                                })
                                $("#docEquivalente").val(1);

                            } else {
                                Swal.fire({
                                    position: 'top-center',
                                    type: 'success',
                                    title: 'Documentos Soporte Deshabilitados',
                                    showConfirmButton: false,
                                    timer: 2500
                                })
                                $("#docEquivalente").val(0);
                            }

                            setTimeout(function() {
                                location.reload();
                            }, 2500);

                        }
                    });

                }
            })
        }

        function separarNumeracionContrato(){

        let url = `{{ route('configuracion.contrato_numeracion') }}`;

        if ($("#separar_numeracion").val() == 0) {
            $titleswal = "¿Desea separar la numeración del contrato por servidor?";
        }

        if ($("#separar_numeracion").val() == 1) {
            $titleswal = "¿Desea unificar la numeración de los contratos sin separarla por servidor?";
        }

        Swal.fire({
            title: $titleswal,
            type: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            cancelButtonText: 'Cancelar',
            confirmButtonText: 'Aceptar',
        }).then((result) => {
            if (result.value) {
                $.ajax({
                    url: url,
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    method: 'post',
                    data: { separar_numeracion: $("#separar_numeracion").val() },
                    success: function (data) {
                        console.log(data);
                        if (data == 1) {
                            Swal.fire({
                                type: 'success',
                                title: 'Nuemración de contratos configurada para separar por servidor',
                                showConfirmButton: false,
                                timer: 5000
                            })
                            $("#separar_numeracion").val(1);
                        } else {
                            Swal.fire({
                                type: 'success',
                                title: 'Numeración de contratos configurada para unificar sin separar por servidor',
                                showConfirmButton: false,
                                timer: 5000
                            })
                            $("#separar_numeracion").val(0);
                        }
                        setTimeout(function(){
                            var a = document.createElement("a");
                            a.href = window.location.pathname;
                            a.click();
                        }, 1000);
                    }
                });

            }
        })

        }

        function consultasMk(){
			let url = `{{ route('configuracion.consultas_mikrotik') }}`;

		    if ($("#consultas_mk").val() == 0) {
		        $titleswal = "¿Desea habilitar las consultas a la mikrotik?";
		    }

		    if ($("#consultas_mk").val() == 1) {
		        $titleswal = "¿Desea deshabilitar las consultas a la mikrotik?";
		    }

		    Swal.fire({
		        title: $titleswal,
		        type: 'warning',
		        showCancelButton: true,
		        confirmButtonColor: '#3085d6',
		        cancelButtonColor: '#d33',
		        cancelButtonText: 'Cancelar',
		        confirmButtonText: 'Aceptar',
		    }).then((result) => {
		        if (result.value) {
		            $.ajax({
		                url: url,
		                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
		                method: 'post',
		                data: { consultas_mk: $("#consultas_mk").val() },
		                success: function (data) {
		                    console.log(data);
		                    if (data == 1) {
		                        Swal.fire({
		                            type: 'success',
		                            title: 'Consultas mikrotik se ejecutarán.',
		                            showConfirmButton: false,
		                            timer: 5000
		                        })
		                        $("#consultas_mk").val(1);
		                    } else {
		                        Swal.fire({
		                            type: 'success',
		                            title: 'Consultas mikrotik no se ejecutarán.',
		                            showConfirmButton: false,
		                            timer: 5000
		                        })
		                        $("#consultas_mk").val(0);
		                    }
		                    setTimeout(function(){
		                    	var a = document.createElement("a");
		                    	a.href = window.location.pathname;
		                    	a.click();
		                    }, 1000);
		                }
		            });

		        }
		    })
		}

        function facturacionContratosOff() {
			if (window.location.pathname.split("/")[1] === "software") {
				var url='/software/configuracion_facturas_contratos_off';
			}else{
				var url = '/configuracion_facturas_contratos_off';
			}

		    if ($("#factura_contrato_off").val() == 0) {
		        $titleswal = "¿Desea habilitar la generación de facturas para contratos deshabilitados?";
		    }

		    if ($("#factura_contrato_off").val() == 1) {
		        $titleswal = "¿Desea deshabilitar la generación de facturas para contratos deshabilitados?";
		    }

		    Swal.fire({
		        title: $titleswal,
		        type: 'warning',
		        showCancelButton: true,
		        confirmButtonColor: '#3085d6',
		        cancelButtonColor: '#d33',
		        cancelButtonText: 'Cancelar',
		        confirmButtonText: 'Aceptar',
		    }).then((result) => {
		        if (result.value) {
		            $.ajax({
		                url: url,
		                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
		                method: 'post',
		                data: { factura_contrato_off: $("#factura_contrato_off").val() },
		                success: function (data) {
		                    console.log(data);
		                    if (data == 1) {
		                        Swal.fire({
		                            type: 'success',
		                            title: 'generación de facturas en contratos off habilitada',
		                            showConfirmButton: false,
		                            timer: 5000
		                        })
		                        $("#factura_contrato_off").val(1);
		                    } else {
		                        Swal.fire({
		                            type: 'success',
		                            title: 'generación de facturas en contratos off deshabilitada',
		                            showConfirmButton: false,
		                            timer: 5000
		                        })
		                        $("#factura_contrato_off").val(0);
		                    }
		                    setTimeout(function(){
		                    	var a = document.createElement("a");
		                    	a.href = window.location.pathname;
		                    	a.click();
		                    }, 1000);
		                }
		            });

		        }
		    })
		}



        function chatIA(){
            if (window.location.pathname.split("/")[1] === "software") {
				var url='/software/configuracion_chat_ia';
			}else{
				var url = '/configuracion_chat_ia';
			}

		    if ($("#chat_ia").val() == 0) {
		        $titleswal = "¿Desea habilitar el chat IA?";
		    }

		    if ($("#chat_ia").val() == 1) {
		        $titleswal = "¿Desea deshabilitar el chat IA?";
		    }

		    Swal.fire({
		        title: $titleswal,
		        type: 'warning',
		        showCancelButton: true,
		        confirmButtonColor: '#3085d6',
		        cancelButtonColor: '#d33',
		        cancelButtonText: 'Cancelar',
		        confirmButtonText: 'Aceptar',
		    }).then((result) => {
		        if (result.value) {
		            $.ajax({
                        url: url,
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                        method: 'post',
                        data: { chat_ia: $("#chat_ia").val() },
                        success: function (data) {
                            if (data.success == true) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Chat IA habilitado correctamente',
                                    showConfirmButton: false,
                                    timer: 5000
                                });
                                $("#chat_ia").val(1);
                            } else {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Chat IA deshabilitado correctamente',
                                    showConfirmButton: false,
                                    timer: 5000
                                });
                                $("#chat_ia").val(0);
                            }

                            setTimeout(function(){
                                var a = document.createElement("a");
                                a.href = window.location.pathname;
                                a.click();
                            }, 1000);
                        },
                        error: function (xhr, status, error) {
                            Swal.fire({
                                type: 'error',
                                title: 'Error al procesar la solicitud',
                                text: xhr.responseJSON?.message || 'Ocurrió un error inesperado.',
                                showConfirmButton: true
                            });
                        }
                    });


		        }
		    })
        }


        function storePeriodoFacturacion() {
            cargando(true);
            if (window.location.pathname.split("/")[1] === "software") {
                var url = `/software/empresa/configuracion/storePeriodoFacturacion`;
            } else if (window.location.pathname.split("/")[1] === "portal") {
                var url = `/portal/empresa/configuracion/storePeriodoFacturacion`;
            } else {
                var url = `/empresa/configuracion/storePeriodoFacturacion`;
            }
            $.ajax({
                url: url,
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    periodo_facturacion: $('#val_periodo_facturacion').val()
                },
                success: function(response) {
                    cargando(false);
                    swal({
                        title: response.title,
                        text: response.message,
                        type: response.type,
                        showConfirmButton: true,
                        confirmButtonColor: '#1A59A1',
                        confirmButtonText: 'ACEPTAR',
                    });
                    if (response.success == true) {
                        $("#periodo_factura").modal('hide');
                    }
                }
            });
        }

        function storeFormatoImpresion() {
            cargando(true);
            if (window.location.pathname.split("/")[1] === "software") {
                var url = `/software/empresa/configuracion/storeFormatoImpresion`;
            } else {
                var url = `/empresa/configuracion/storeFormatoImpresion`;
            }
            $.ajax({
                url: url,
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    formato_impresion: $('#val_formato_impresion').val()
                },
                success: function(response) {
                    cargando(false);
                    swal({
                        title: response.title,
                        text: response.message,
                        type: response.type,
                        showConfirmButton: true,
                        confirmButtonColor: '#1A59A1',
                        confirmButtonText: 'ACEPTAR',
                    });
                    if (response.success == true) {
                        $("#formato_impresion").modal('hide');
                    }
                }
            });
        }

        function storePageLength() {
            cargando(true);
            if (window.location.pathname.split("/")[1] === "software") {
                var url = `/software/empresa/configuracion/storePageLength`;
            } else {
                var url = `/empresa/configuracion/storePageLength`;
            }
            $.ajax({
                url: url,
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    pageLength: $('#val_pageLength').val()
                },
                success: function(response) {
                    cargando(false);
                    swal({
                        title: 'NRO DE REGISTROS A MOSTRAR',
                        text: response.message,
                        type: response.type,
                        showConfirmButton: true,
                        confirmButtonColor: '#1A59A1',
                        confirmButtonText: 'ACEPTAR',
                    });
                    if (response.success == true) {
                        $("#nro_registro").modal('hide');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    }
                }
            });
        }

        function facturacionAutomatica() {
            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/configuracion_facturacionAutomatica';
            } else {
                var url = '/configuracion_facturacionAutomatica';
            }

            if ($("#facturaAuto").val() == 0) {
                $titleswal = "¿Desea habilitar la facturación automática de los contratos?";
            }

            if ($("#facturaAuto").val() == 1) {
                $titleswal = "¿Desea deshabilitar la facturación automática de los contratos?";
            }

            Swal.fire({
                title: $titleswal,
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Aceptar',
            }).then((result) => {
                if (result.value) {
                    $.ajax({
                        url: url,
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        method: 'post',
                        data: {
                            status: $("#facturaAuto").val()
                        },
                        success: function(data) {
                            console.log(data);
                            if (data == 1) {
                                Swal.fire({
                                    type: 'success',
                                    title: 'Factuación automática para los contratos habilitada',
                                    showConfirmButton: false,
                                    timer: 5000
                                })
                                $("#facturaAuto").val(1);
                            } else {
                                Swal.fire({
                                    type: 'success',
                                    title: 'Factuación automática para los contratos deshabilitada',
                                    showConfirmButton: false,
                                    timer: 5000
                                })
                                $("#facturaAuto").val(0);
                            }
                            setTimeout(function() {
                                var a = document.createElement("a");
                                a.href = window.location.pathname;
                                a.click();
                            }, 1000);
                        }
                    });

                }
            })
        }

        function pagoSiigo(){
            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/configuracion_pagosiigo';
            } else {
                var url = '/configuracion_pagosiigo';
            }

            if ($("#pagosiigo").val() == 0) {
                $titleswal = "¿Desea habilitar el envio a siigo cuando se cree el pago?";
            }

            if ($("#pagosiigo").val() == 1) {
                $titleswal = "¿Desea deshabilitar el envio a siigo cuando se cree el pago?";
            }

            Swal.fire({
                title: $titleswal,
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Aceptar',
            }).then((result) => {
                if (result.value) {
                    $.ajax({
                        url: url,
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        method: 'post',
                        data: {
                            status: $("#pagosiigo").val()
                        },
                        success: function(data) {
                            console.log(data);
                            if (data == 1) {
                                Swal.fire({
                                    type: 'success',
                                    title: 'Envios a siigo cuando se cree el pago hablitado',
                                    showConfirmButton: false,
                                    timer: 5000
                                })
                                $("#facturaAuto").val(1);
                            } else {
                                Swal.fire({
                                    type: 'success',
                                    title: 'Envios a siigo cuando se cree el pago deshablitado',
                                    showConfirmButton: false,
                                    timer: 5000
                                })
                                $("#pagosiigo").val(0);
                            }

                            setTimeout(function() {
                                var a = document.createElement("a");
                                a.href = window.location.pathname;
                                a.click();
                            }, 1000);
                        }
                    });

                }
            })
        }

        function siigoEmitida(){
            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/configuracion_siigoemitida';
            } else {
                var url = '/configuracion_siigoemitida';
            }

            if ($("#siigoemitida").val() == 0) {
                $titleswal = "¿Desea habilitar el envio a siigo con el estado emitido?";
            }

            if ($("#siigoemitida").val() == 1) {
                $titleswal = "¿Desea deshabilitar el envio a siigo con el estado emitido?";
            }

            Swal.fire({
                title: $titleswal,
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Aceptar',
            }).then((result) => {
                if (result.value) {
                    $.ajax({
                        url: url,
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        method: 'post',
                        data: {
                            status: $("#siigoemitida").val()
                        },
                        success: function(data) {
                            console.log(data);
                            if (data == 1) {
                                Swal.fire({
                                    type: 'success',
                                    title: 'Envío a siigo con estado emitido habilitado',
                                    showConfirmButton: false,
                                    timer: 5000
                                })
                                $("#siigoemitida").val(1);
                            } else {
                                Swal.fire({
                                    type: 'success',
                                    title: 'Envío a siigo con estado emitido deshabilitado',
                                    showConfirmButton: false,
                                    timer: 5000
                                })
                                $("#siigoemitida").val(0);
                            }
                            setTimeout(function() {
                                var a = document.createElement("a");
                                a.href = window.location.pathname;
                                a.click();
                            }, 1000);
                        }
                    });
                }
            })
        }

        function reconexionGenerica() {
            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/configuracion_reconexiongenerica';
            } else {
                var url = '/configuracion_reconexiongenerica';
            }

            if ($("#reconexionGenerica").val() == 0) {
                $titleswal = "¿Desea habilitar la reconexión genérica?";
            }

            if ($("#reconexionGenerica").val() == 1) {
                $titleswal = "¿Desea deshabilitar el valor de reconexión genérica?";
            }

            Swal.fire({
                title: $titleswal,
                text: 'Configura los días y el valor para generar el cobro adicional sobre la factura del contrato, el sistema diariamente hará la revisión para agregarle a la última factura creada el cobro adicional si se pasa de los días.',
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Aceptar',
            }).then((result) => {
                if (result.value) {
                    $.ajax({
                        url: url,
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        method: 'post',
                        data: {
                            status: $("#reconexionGenerica").val()
                        },
                        success: function(data) {
                            console.log(data);
                            if (data == 1) {
                                Swal.fire({
                                    type: 'success',
                                    title: 'Reconexión Genérica para los contratos habilitada',
                                    showConfirmButton: false,
                                    timer: 5000
                                })
                                $("#reconexionGenerica").val(1);
                            } else {
                                Swal.fire({
                                    type: 'success',
                                    title: 'Reconexión Genérica para los contratos deshabilitada',
                                    showConfirmButton: false,
                                    timer: 5000
                                })
                                $("#reconexionGenerica").val(0);
                            }
                            setTimeout(function() {
                                var a = document.createElement("a");
                                a.href = window.location.pathname;
                                a.click();
                            }, 1000);
                        }
                    });

                }
            })
        }

        function updateReconexionGenerica() {
            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/updatereconexiongenerica';
            } else {
                var url = '/updatereconexiongenerica';
            }

            $.ajax({
                url: url,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                method: 'post',
                data: {
                    dias_reconexion_generica: $("#dias_reconexion_generica").val(),
                    precio_reconexion_generica: $("#precio_reconexion_generica").val()
                },
                success: function(data) {
                    $("#config_reconexion").modal('hide');
                    if (data == 1) {
                        Swal.fire({
                            type: 'success',
                            title: 'La configuración de la reconexión genérica ha sido registrada.',
                            text: 'Recargando la página',
                            showConfirmButton: false,
                            timer: 5000
                        })
                    } else {
                        Swal.fire({
                            type: 'error',
                            title: 'Error al actualizar la reconexión genérica',
                            text: 'Recargando la página',
                            showConfirmButton: false,
                            timer: 5000
                        })
                    }

                    setTimeout(function() {
                        var a = document.createElement("a");
                        a.href = window.location.pathname;
                        a.click();
                    }, 2000);
                }
            });
        }

        function saldoFavorAutomatico() {
            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/configuracion_aplicacionsaldosfavor';
            } else {
                var url = '/configuracion_aplicacionsaldosfavor';
            }

            if ($("#saldofavAuto").val() == 0) {
                $titleswal = "¿Desea habilitar la aplicacion de saldos a favor automaticamente?";
            }

            if ($("#saldofavAuto").val() == 1) {
                $titleswal = "¿Desea deshabilitar la facturación automática de los contratos?";
            }

            Swal.fire({
                title: $titleswal,
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Aceptar',
            }).then((result) => {
                if (result.value) {
                    $.ajax({
                        url: url,
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        method: 'post',
                        data: {
                            status: $("#saldofavAuto").val()
                        },
                        success: function(data) {
                            console.log(data);
                            if (data == 1) {
                                Swal.fire({
                                    type: 'success',
                                    title: 'Aplicación de saldos a favor automáticamente habilitada',
                                    showConfirmButton: false,
                                    timer: 5000
                                })
                                $("#saldofavAuto").val(1);
                            } else {
                                Swal.fire({
                                    type: 'success',
                                    title: 'Aplicación de saldos a favor automáticamente deshabilitada',
                                    showConfirmButton: false,
                                    timer: 5000
                                })
                                $("#saldofavAuto").val(0);
                            }
                            setTimeout(function() {
                                var a = document.createElement("a");
                                a.href = window.location.pathname;
                                a.click();
                            }, 1000);
                        }
                    });

                }
            })
        }

        function facturacionCronAbiertas() {
            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/configuracion_factcronabiertas';
            } else {
                var url = '/configuracion_factcronabiertas';
            }

            if ($("#cronAbierta").val() == 0) {
                $titleswal = "¿Desea habilitar la creación de facturas así la última factura esté abierta?";
            }

            if ($("#cronAbierta").val() == 1) {
                $titleswal = "¿Desea Deshabilitar la creación de facturas así la última factura esté abierta?";
            }

            Swal.fire({
                title: $titleswal,
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Aceptar',
            }).then((result) => {
                if (result.value) {
                    $.ajax({
                        url: url,
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        method: 'post',
                        data: {
                            status: $("#cronAbierta").val()
                        },
                        success: function(data) {
                            console.log(data);
                            if (data == 1) {
                                Swal.fire({
                                    type: 'success',
                                    title: 'Creacion de factruas actualizada correctamente.',
                                    showConfirmButton: false,
                                    timer: 5000
                                })
                                $("#cronAbierta").val(1);
                            } else {
                                Swal.fire({
                                    type: 'success',
                                    title: 'Creacion de facturas actualizada correctamente.',
                                    showConfirmButton: false,
                                    timer: 5000
                                })
                                $("#cronAbierta").val(0);
                            }
                            setTimeout(function() {
                                var a = document.createElement("a");
                                a.href = window.location.pathname;
                                a.click();
                            }, 1000);
                        }
                    });

                }
            })
        }

        function queriesDhcpSmartolt() {
            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/configuracion_queries_dhcp_smartolt';
            } else {
                var url = '/configuracion_queries_dhcp_smartolt';
            }

            var textswal = '';
            if ($("#queries_dhcp_smartolt").val() == 0) {
                $titleswal = "¿Desea habilitar Disable/Enable ONU para contratos DHCP?";
                textswal = "Esta opción es para contratos DHCP que tienen ingresado su serial onu en los planes de internet y tienen una configuración de smart OLT habilitada.";
            }

            if ($("#queries_dhcp_smartolt").val() == 1) {
                $titleswal = "¿Desea Deshabilitar Disable/Enable ONU para contratos DHCP?";
            }

            Swal.fire({
                title: $titleswal,
                text: textswal,
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Aceptar',
            }).then((result) => {
                if (result.value) {
                    $.ajax({
                        url: url,
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        method: 'post',
                        data: {
                            status: $("#queries_dhcp_smartolt").val()
                        },
                        success: function(data) {
                            if (data.success) {
                                Swal.fire({
                                    type: 'success',
                                    title: data.message,
                                    showConfirmButton: false,
                                    timer: 5000
                                });
                                if ($("#queries_dhcp_smartolt").val() == 0) {
                                    $("#queries_dhcp_smartolt").val(1);
                                } else {
                                    $("#queries_dhcp_smartolt").val(0);
                                }
                            } else {
                                Swal.fire({
                                    type: 'error',
                                    title: data.message,
                                    showConfirmButton: false,
                                    timer: 5000
                                });
                            }
                            setTimeout(function() {
                                var a = document.createElement("a");
                                a.href = window.location.pathname;
                                a.click();
                            }, 1000);
                        }
                    });

                }
            })
        }

        function activeConnectionSecret(){


            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/configuracion_activeconnection_secret';
            } else {
                var url = '/configuracion_activeconnection_secret';
            }

            if ($("#activeconn_secret").val() == 0) {
                $titleswal = "¿Desea habilitar las consultas de active connection y secret disabled al deshabilitar contratos?";
            }

            if ($("#activeconn_secret").val() == 1) {
                $titleswal = "¿Desea deshabilitar las consultas de active connection y secret disabled al deshabilitar contratos?";
            }

            Swal.fire({
                title: $titleswal,
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Aceptar',
            }).then((result) => {
                if (result.value) {
                    $.ajax({
                        url: url,
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        method: 'post',
                        data: {
                            status: $("#activeconn_secret").val()
                        },
                        success: function(data) {
                            console.log(data);
                            if (data == 1) {
                                Swal.fire({
                                    type: 'success',
                                    title: 'Consultas al deshabilitar contratos habilitadas correctamente.',
                                    showConfirmButton: false,
                                    timer: 5000
                                })
                                $("#activeconn_secret").val(1);
                            } else {
                                Swal.fire({
                                    type: 'success',
                                    title: 'Consultas al deshabilitar contratos deshabilitadas correctamente.',
                                    showConfirmButton: false,
                                    timer: 5000
                                })
                                $("#activeconn_secret").val(0);
                            }
                            setTimeout(function() {
                                var a = document.createElement("a");
                                a.href = window.location.pathname;
                                a.click();
                            }, 1000);
                        }
                    });

                }
            })
        }

        function facturacionSmsAutomatica() {
            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/configuracion_facturacionSmsAutomatica';
            } else {
                var url = '/configuracion_facturacionSmsAutomatica';
            }

            if ($("#facturaSmsAuto").val() == 0) {
                $titleswal = "¿Desea habilitar el envio de SMS automaticos?";
            }

            if ($("#facturaSmsAuto").val() == 1) {
                $titleswal = "¿Desea deshabilitar el envio de SMS automaticos?";
            }

            Swal.fire({
                title: $titleswal,
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Aceptar',
            }).then((result) => {
                if (result.value) {
                    $.ajax({
                        url: url,
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        method: 'post',
                        data: {
                            status: $("#facturaSmsAuto").val()
                        },
                        success: function(data) {
                            console.log(data);
                            if (data == 1) {
                                Swal.fire({
                                    type: 'success',
                                    title: 'Envio de sms automaticos habilitado',
                                    showConfirmButton: false,
                                    timer: 5000
                                })
                                $("#facturaAuto").val(1);
                            } else {
                                Swal.fire({
                                    type: 'success',
                                    title: 'Envio de sms automaticos deshabilitada',
                                    showConfirmButton: false,
                                    timer: 5000
                                })
                                $("#facturaAuto").val(0);
                            }
                            setTimeout(function() {
                                var a = document.createElement("a");
                                a.href = window.location.pathname;
                                a.click();
                            }, 1000);
                        }
                    });

                }
            })
        }

        function envioWppIngreso(){

            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/configuracion_envio_wpp_ingreso';
            } else {
                var url = '/configuracion_envio_wpp_ingreso';
            }

            if ($("#envioWppIngreso").val() == 0) {
                $titleswal = "¿Desea habilitar el envio de la tirilla cuando se haga el ingreso de la factura?";
            }

            if ($("#envioWppIngreso").val() == 1) {
                $titleswal = "¿Desea deshabilitar el envio de la tirilla cuando se haga el ingreso de la factura?";
            }

            Swal.fire({
                title: $titleswal,
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Aceptar',
            }).then((result) => {
                if (result.value) {
                    $.ajax({
                        url: url,
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        method: 'post',
                        data: {
                            status: $("#envioWppIngreso").val()
                        },
                        success: function(data) {
                            console.log(data);
                            if (data == 1) {
                                Swal.fire({
                                    type: 'success',
                                    title: 'Envio de tirilla por wpp cuando se haga el ingreso habilitado',
                                    showConfirmButton: false,
                                    timer: 5000
                                })
                                $("#envioWppIngreso").val(1);
                            } else {
                                Swal.fire({
                                    type: 'success',
                                    title: 'Envio de tirilla por wpp cuando se haga el ingreso deshabilitado',
                                    showConfirmButton: false,
                                    timer: 5000
                                })
                                $("#envioWppIngreso").val(0);
                            }
                            setTimeout(function() {
                                var a = document.createElement("a");
                                a.href = window.location.pathname;
                                a.click();
                            }, 1000);
                        }
                    });

                }
            })
        }

        function periodoTirilla() {
            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/configuracion_periodo_tirilla';
            } else {
                var url = '/configuracion_periodo_tirilla';
            }

            if ($("#periodoTirilla").val() == 0) {
                $titleswal = "¿Desea habilitar el campo periodo la tirilla??";
            }

            if ($("#periodoTirilla").val() == 1) {
                $titleswal = "¿Desea deshabilitar el campo periodo en la tirilla?";
            }

            Swal.fire({
                title: $titleswal,
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Aceptar',
            }).then((result) => {
                if (result.value) {
                    $.ajax({
                        url: url,
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        method: 'post',
                        data: {
                            status: $("#periodoTirilla").val()
                        },
                        success: function(data) {
                            console.log(data);
                            if (data == 1) {
                                Swal.fire({
                                    type: 'success',
                                    title: 'Periodo en la tirilla habilitado',
                                    showConfirmButton: false,
                                    timer: 5000
                                })
                                $("#facturaAuto").val(1);
                            } else {
                                Swal.fire({
                                    type: 'success',
                                    title: 'Periodo en la tirilla deshabilitado',
                                    showConfirmButton: false,
                                    timer: 5000
                                })
                                $("#facturaAuto").val(0);
                            }
                            setTimeout(function() {
                                var a = document.createElement("a");
                                a.href = window.location.pathname;
                                a.click();
                            }, 1000);
                        }
                    });

                }
            })
        }

        function limpiarCache() {
            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/configuracion_limpiarCache';
            } else {
                var url = '/configuracion_limpiarCache';
            }

            var empresa = {{ Auth::user()->empresa()->id }};
            var href = '{{ route('home') }}';

            Swal.fire({
                title: '¿Desea limpiar los archivos temporales y la caché del sistema?',
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Aceptar',
            }).then((result) => {
                if (result.value) {
                    cargando(true);
                    $.ajax({
                        url: url,
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        method: 'post',
                        data: {
                            empresa: empresa
                        },
                        success: function(data) {
                            cargando(false);
                            Swal.fire({
                                type: 'success',
                                title: 'Limpieza realizada con éxito',
                                showConfirmButton: false,
                                timer: 5000
                            });
                            setTimeout(function() {
                                var a = document.createElement("a");
                                a.href = href;
                                a.click();
                            }, 1000);
                        }
                    });

                }
            })
        }

        function configuracionOLT() {
            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/configuracion_olt';
            } else {
                var url = '/configuracion_olt';
            }

            $.ajax({
                url: url,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                method: 'post',
                data: {
                    smartOLT: $("#smartOLT").val(),
                    adminOLT: $("#adminOLT").val()
                },
                success: function(data) {
                    $("#config_olt").modal('hide');
                    if (data == 1) {
                        Swal.fire({
                            type: 'success',
                            title: 'La configuración de la OLT ha sido registrada con éxito',
                            text: 'Recargando la página',
                            showConfirmButton: false,
                            timer: 5000
                        })
                    } else {
                        Swal.fire({
                            type: 'error',
                            title: 'Error en la conexión, revise la ApiKey',
                            text: 'Recargando la página',
                            showConfirmButton: false,
                            timer: 5000
                        })
                    }

                    setTimeout(function() {
                        var a = document.createElement("a");
                        a.href = window.location.pathname;
                        a.click();
                    }, 2000);
                }
            });
        }

        function guardarWhatsappBusinessId() {
            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/configuracion/whatsapp-business-id';
            } else {
                var url = '/configuracion/whatsapp-business-id';
            }

            $.ajax({
                url: url,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                method: 'post',
                data: {
                    whatsapp_business_account_id: $("#whatsapp_business_account_id").val()
                },
                success: function(data) {
                    $("#config_whatsapp_meta").modal('hide');
                    if (data == 1) {
                        Swal.fire({
                            type: 'success',
                            title: 'La configuración de WhatsApp Meta ha sido registrada con éxito',
                            text: 'Recargando la página',
                            showConfirmButton: false,
                            timer: 5000
                        })
                    } else {
                        Swal.fire({
                            type: 'error',
                            title: 'Error al guardar la configuración',
                            text: 'Por favor intenta nuevamente'
                        })
                    }
                    setTimeout(function() {
                        var a = document.createElement("a");
                        a.href = window.location.pathname;
                        a.click();
                    }, 2000);
                },
                error: function() {
                    Swal.fire({
                        type: 'error',
                        title: 'Error al guardar la configuración',
                        text: 'Por favor intenta nuevamente'
                    })
                }
            });
        }

        function obtenerPlantillasWhatsapp() {
            Swal.fire({
                title: 'Obteniendo plantillas...',
                text: 'Por favor espera',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });

            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/configuracion/obtener-plantillas-whatsapp';
            } else {
                var url = '/configuracion/obtener-plantillas-whatsapp';
            }

            $.ajax({
                url: url,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                method: 'post',
                success: function(data) {
                    if (data.success == 1) {
                        Swal.fire({
                            type: 'success',
                            title: 'Plantillas obtenidas con éxito',
                            text: data.message || 'Las plantillas se han guardado correctamente',
                            showConfirmButton: false,
                            timer: 5000
                        })
                    } else {
                        Swal.fire({
                            type: 'error',
                            title: 'Error al obtener las plantillas',
                            text: data.message || 'Por favor verifica la configuración e intenta nuevamente'
                        })
                    }
                },
                error: function(xhr) {
                    var errorMessage = 'Error al obtener las plantillas';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    } else if (xhr.responseText) {
                        try {
                            var errorData = JSON.parse(xhr.responseText);
                            if (errorData.message) {
                                errorMessage = errorData.message;
                            }
                        } catch(e) {
                            // Mantener el mensaje por defecto
                        }
                    }
                    Swal.fire({
                        type: 'error',
                        title: 'Error',
                        text: errorMessage
                    })
                }
            });
        }

        function registrarNumeroWhatsappMeta() {
            Swal.fire({
                title: 'Registrando número...',
                text: 'Por favor espera',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });

            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/configuracion/registrar-numero-whatsapp-meta';
            } else {
                var url = '/configuracion/registrar-numero-whatsapp-meta';
            }

            $.ajax({
                url: url,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                method: 'post',
                success: function(data) {
                    if (data.success == 1) {
                        Swal.fire({
                            type: 'success',
                            title: 'Éxito',
                            text: data.message || 'El numero de teléfono ha sido habilitado',
                            showConfirmButton: true
                        })
                    } else {
                        Swal.fire({
                            type: 'error',
                            title: 'Error al registrar el número',
                            text: data.message || 'Por favor verifica la configuración e intenta nuevamente'
                        })
                    }
                },
                error: function(xhr) {
                    var errorMessage = 'Error al registrar el número';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    } else if (xhr.responseText) {
                        try {
                            var errorData = JSON.parse(xhr.responseText);
                            if (errorData.message) {
                                errorMessage = errorData.message;
                            }
                        } catch(e) {
                            // Mantener el mensaje por defecto
                        }
                    }
                    Swal.fire({
                        type: 'error',
                        title: 'Error',
                        text: errorMessage
                    })
                }
            });
        }

        function suscribirseCanalWhatsapp() {
            Swal.fire({
                title: 'Suscribiendo al canal...',
                text: 'Por favor espera',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });

            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/configuracion/suscribirse-canal-whatsapp';
            } else {
                var url = '/configuracion/suscribirse-canal-whatsapp';
            }

            $.ajax({
                url: url,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                method: 'post',
                success: function(data) {
                    if (data.success == 1) {
                        Swal.fire({
                            type: 'success',
                            title: 'Éxito',
                            text: data.message || 'Suscripción al canal exitosa',
                            showConfirmButton: true
                        })
                    } else {
                        Swal.fire({
                            type: 'error',
                            title: 'Error al suscribirse',
                            text: data.message || 'Intente nuevamente'
                        })
                    }
                },
                error: function(xhr) {
                    var errorMessage = 'Error al suscribirse al canal';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    Swal.fire({
                        type: 'error',
                        title: 'Error',
                        text: errorMessage
                    })
                }
            });
        }

        function configuracionSiigo() {
            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/siigo/configuracion_siigo';
            } else {
                var url = '/siigo/configuracion_siigo';
            }

            var usuario_siigo = encodeURIComponent($("#usuario_siigo").val());
            var api_key_siigo = encodeURIComponent($("#api_key_siigo").val());
            var fullUrl = url + '?usuario_siigo=' + usuario_siigo + '&api_key_siigo=' + api_key_siigo;

            $.ajax({
                url: fullUrl,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                method: 'get',
                success: function(data) {
                    $("#config_siigo").modal('hide');
                    if (data == 1) {
                        Swal.fire({
                            type: 'success',
                            title: 'La configuración de Siigo ha sido actualizada con éxito',
                            text: 'Recargando la página',
                            showConfirmButton: false,
                            timer: 5000
                        })
                    } else {
                        Swal.fire({
                            type: 'error',
                            title: 'Error en la conexión, revise la ApiKey',
                            text: 'Recargando la página',
                            showConfirmButton: false,
                            timer: 5000
                        })
                    }

                    setTimeout(function() {
                        var a = document.createElement("a");
                        a.href = window.location.pathname;
                        a.click();
                    }, 2000);
                }
            });
        }

        function quitarSiigo() {
            Swal.fire({
                title: '¿Desea quitar la conexión con Siigo?',
                text: "Se eliminarán las credenciales guardadas.",
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, quitar conexión',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.value) {
                    $("#usuario_siigo").val("");
                    $("#api_key_siigo").val("");
                    configuracionSiigo();
                }
            })
        }

        function prorrateo() {

            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/prorrateo';
            } else {
                var url = '/prorrateo';
            }

            if ($("#prorrateoid").val() == 0) {
                $titleswal = "¿Desea habilitar el prorrateo de las facturas?";
                text = "Al habilitar esta opción, el sistema habilitará el cobro de prorrateo para todos los contratos actuales de la empresa. Además, por defecto, los nuevos contratos se crearán con la opción de prorrateo habilitada.";
            }

            if ($("#prorrateoid").val() == 1) {
                $titleswal = "¿Desea deshabilitar el prorrateo de las facturas?";
                text = "Al deshabilitar esta opción, el sistema deshabilitará el cobro de prorrateo para todos los contratos actuales de la empresa. Además, por defecto, los nuevos contratos se crearán con la opción de prorrateo deshabilitada.";
            }

            Swal.fire({
                title: $titleswal,
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Aceptar',
                text: text
            }).then((result) => {
                if (result.value) {
                    $.ajax({
                        url: url,
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        method: 'post',
                        data: {
                            prorrateo: $("#prorrateoid").val()
                        },
                        success: function(data) {

                            if (data == 1) {
                                Swal.fire({
                                    type: 'success',
                                    title: 'Prorrateo para facturas ha sido habilitado.',
                                    showConfirmButton: false,
                                    timer: 5000
                                })
                                $("#prorrateoid").val(1);
                            } else {
                                Swal.fire({
                                    type: 'success',
                                    title: 'Prorrateo para facturas ha sido deshabilitado',
                                    showConfirmButton: false,
                                    timer: 5000
                                })
                                $("#prorrateoid").val(0);
                            }
                            location.reload();
                        }
                    });
                }
            });
        }

        function actDescEfecty() {
            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/efecty';
            } else {
                var url = '/efecty';
            }

            if ($("#efectyid").val() == 0) {
                $titleswal = "¿Desea habilitar la plataforma Efecty?";
            }

            if ($("#efectyid").val() == 1) {
                $titleswal = "¿Desea deshabilitar la plataforma Efecty?";
            }

            Swal.fire({
                title: $titleswal,
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Aceptar',
            }).then((result) => {
                if (result.value) {
                    $.ajax({
                        url: url,
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        method: 'post',
                        data: {
                            efecty: $("#efectyid").val()
                        },
                        success: function(data) {
                            console.log(data);
                            if (data == 1) {
                                Swal.fire({
                                    type: 'success',
                                    title: 'Plataforma Efecty habilitada',
                                    showConfirmButton: false,
                                    timer: 5000
                                })
                                $("#efectyid").val(1);
                            } else {
                                Swal.fire({
                                    type: 'success',
                                    title: 'Plataforma Efecty deshabilitada',
                                    showConfirmButton: false,
                                    timer: 5000
                                })
                                $("#efectyid").val(0);
                            }
                            setTimeout(function() {
                                var a = document.createElement("a");
                                a.href = window.location.pathname;
                                a.click();
                            }, 1000);
                        }
                    });

                }
            })
        }

        function habilitarNomina() {
            var estadoNomina = parseInt($('#estado_nomina').val());
            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/empresa';
            } else {
                var url = '/empresa';
            }
            Swal.fire({
                title: `¿${estadoNomina == 1 ? 'Deshabilitar' : 'Habilitar'} nómina?`,
                text: `${estadoNomina == 1 ? 'La nómina de su empresa será deshabilitada' : 'Recuerde que la nomina electrónica estará abilitada por 15 días de manera gratuita'} `,
                type: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                cancelButtonText: 'Cancelar',
                confirmButtonText: `${estadoNomina == 1 ? 'Deshabilitar' : 'Habilitar'}`,
            }).then((result) => {
                if (result.value) {
                    $.ajax({
                        url: url + '/configuracion/estado/nomina',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        method: 'post',
                        success: function(response) {
                            console.log(response);
                            if (response.success) {
                                Swal.fire({
                                    position: 'top-center',
                                    type: 'success',
                                    text: response.text,
                                    title: response.message,
                                    showConfirmButton: false,
                                    timer: 5000
                                })
                                $("#estado_nomina").val(response.nomina);
                                $("#texto_nomina").text(response.nomina == 1 ? 'Deshabilitar nómina' :
                                    'Habilitar nómina');
                                if (response.nomina == 1) {
                                    $("#preferencia_pago").removeClass('d-none');
                                    $("#nomina_numeracion").removeClass('d-none');
                                    $("#nomina_calculos").removeClass('d-none');
                                    $("#nomina").removeClass('d-none');
                                    $('#div_nominaDIAN').removeClass('d-none');
                                    $("#nomina").addClass('nav-item');
                                    $("#nomina_asistente").removeClass('d-none');
                                    $(".nomina").removeClass('d-none');
                                    $("#planes_nomina").removeClass('d-none');
                                    $("#nomina_asistentes").removeClass('d-none');
                                    $("#alerta_nomina").addClass('d-none');
                                } else {
                                    $("#preferencia_pago").addClass('d-none');
                                    $("#nomina_numeracion").addClass('d-none');
                                    $("#nomina_calculos").addClass('d-none');
                                    $("#nomina").addClass('d-none');
                                    $('#div_nominaDIAN').addClass('d-none');
                                    $("#nomina_asistente").addClass('d-none');
                                    $(".nomina").addClass('d-none');
                                    $("#planes_nomina").addClass('d-none');
                                    $("#nomina_asistentes").addClass('d-none');
                                    $("#alerta_nomina").removeClass('d-none');
                                }
                            }
                            location.reload()
                        }
                    });
                }
            })
        }

        function actDescOficina() {
            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/oficina';
            } else {
                var url = '/oficina';
            }

            if ($("#oficinaid").val() == 0) {
                $titleswal = "¿Desea habilitar el uso de oficinas?";
            }

            if ($("#oficinaid").val() == 1) {
                $titleswal = "¿Desea deshabilitar el uso de oficinas?";
            }

            Swal.fire({
                title: $titleswal,
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Aceptar',
            }).then((result) => {
                if (result.value) {
                    $.ajax({
                        url: url,
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        method: 'post',
                        data: {
                            oficina: $("#oficinaid").val()
                        },
                        success: function(data) {
                            console.log(data);
                            if (data == 1) {
                                Swal.fire({
                                    type: 'success',
                                    title: 'Uso de oficinas en NetworkSoft habilitada',
                                    showConfirmButton: false,
                                    timer: 5000
                                })
                                $("#oficinaid").val(1);
                            } else {
                                Swal.fire({
                                    type: 'success',
                                    title: 'Uso de oficinas en NetworkSoft deshabilitada',
                                    showConfirmButton: false,
                                    timer: 5000
                                })
                                $("#oficinaid").val(0);
                            }
                            setTimeout(function() {
                                var a = document.createElement("a");
                                a.href = window.location.pathname;
                                a.click();
                            }, 1000);
                        }
                    });

                }
            })
        }

        function configurarClausula() {
            cargando(true);
            if (window.location.pathname.split("/")[1] === "software") {
                var url = `/software/clausula_permanencia`;
            } else {
                var url = `/clausula_permanencia`;
            }
            $.ajax({
                url: url,
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    clausula_permanencia: $('#clausula_permanencia').val()
                },
                success: function(response) {
                    cargando(false);
                    swal({
                        title: response.message,
                        text: response.text,
                        type: response.type,
                        showConfirmButton: true,
                        confirmButtonColor: '#1A59A1',
                        confirmButtonText: 'ACEPTAR',
                    });
                    if (response.success == true) {
                        $("#config_clausula").modal('hide');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    }
                }
            });
        }

        function parametrosContratoDigital() {
            cargando(true);
            var url = 'asignaciones/config_campos_asignacion';
            $.get(url, function(data) {
                data = JSON.parse(data);
                $("#campo_a").val(data.campo_a);
                $("#campo_b").val(data.campo_b);
                $("#campo_c").val(data.campo_c);
                $("#campo_d").val(data.campo_d);
                $("#campo_e").val(data.campo_e);
                $("#campo_f").val(data.campo_f);
                $("#campo_g").val(data.campo_g);
                $("#campo_h").val(data.campo_h);
                $("#campo_1").val(data.campo_1);
                $("#contrato_digital").val(data.contrato_digital);
                $("#anexo_1").val(data.anexo_1);
                $("#anexo_2").val(data.anexo_2);
                $("#anexo_3").val(data.anexo_3);
                $("#anexo_4").val(data.anexo_4);
            });
            cargando(false);
            $('#modal_parametrosContratoDigital').modal("show");
        }

        $(document).ready(function() {
            $("#guardar").click(function(form) {
                $.post($("#form_contrato").attr('action'), $("#form_contrato").serialize(), function(data) {
                    console.log(data);
                    if (data.success == true) {
                        $('#cancelar').click();
                        $('#form_contrato').trigger("reset");
                        swal("Configuración Almacenada", "", "success");
                    } else {
                        swal('ERROR', 'Intente nuevamente', "error");
                    }
                }, 'json');
            });
        });

        // ============================================================
        // VARIABLES GLOBALES PARA PLANTILLA FACTURA WHATSAPP
        // ============================================================
        let plantillaMetaFacturaActual = null;
        let bodyTextValuesFactura = [];

        @include('includes.campos-dinamicos')

        // Cargar plantillas Meta al abrir el modal
        $('#config_plantilla_factura_whatsapp').on('show.bs.modal', function() {
            cargarPlantillasMetaDisponibles();
        });

        // Inicializar selectpicker después de que el modal se muestre
        $('#config_plantilla_factura_whatsapp').on('shown.bs.modal', function() {
            $('#plantilla_meta_factura').selectpicker('refresh');
        });

        function cargarPlantillasMetaDisponibles() {
            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/empresa/configuracion/get-plantillas-meta-factura';
            } else {
                var url = '/empresa/configuracion/get-plantillas-meta-factura';
            }

            $.ajax({
                url: url,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                method: 'get',
                success: function(data) {
                    var $select = $('#plantilla_meta_factura');
                    $select.empty();
                    $select.append('<option value="">-- Seleccione una plantilla --</option>');

                    var idPreferida = null;

                    if (data.plantillas && data.plantillas.length > 0) {
                        data.plantillas.forEach(function(plantilla) {
                            if (plantilla.preferida_cron_factura == 1) {
                                idPreferida = plantilla.id;
                            }
                            $select.append('<option value="' + plantilla.id + '">' + plantilla.title + '</option>');
                        });
                    }

                    $select.selectpicker('refresh');

                    // Si hay una plantilla preferida, cargarla automáticamente
                    if (idPreferida) {
                         $select.val(idPreferida);
                         $select.selectpicker('refresh');
                         cargarPlantillaMetaFactura(idPreferida);
                    }
                },
                error: function(xhr) {
                    console.error('Error al cargar plantillas Meta:', xhr);
                }
            });
        }

        function cargarPlantillaMetaFactura(plantillaId) {
            if (!plantillaId) {
                $('#parametros-meta-factura').hide();
                $('#preview-mensaje-meta-factura').hide();
                return;
            }

            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/empresa/configuracion/get-plantilla-meta-factura/' + plantillaId;
            } else {
                var url = '/empresa/configuracion/get-plantilla-meta-factura/' + plantillaId;
            }

            $.ajax({
                url: url,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                method: 'get',
                success: function(data) {
                    if (data.error) {
                        console.error('Error al cargar plantilla:', data.error);
                        $('#parametros-meta-factura').hide();
                        $('#preview-mensaje-meta-factura').hide();
                        return;
                    }

                    plantillaMetaFacturaActual = data;

                    // Procesar body_text para obtener los parámetros
                    if (data.body_text && Array.isArray(data.body_text) && data.body_text.length > 0) {
                        bodyTextValuesFactura = Array.isArray(data.body_text[0]) ? data.body_text[0] : [];
                    } else {
                        bodyTextValuesFactura = [];
                    }

                    // Cargar body_dinamic si existe
                    let bodyDinamicValues = [];
                    if (data.body_dinamic) {
                        try {
                            let parsedData = data.body_dinamic;

                            if (typeof parsedData === 'string') {
                                parsedData = JSON.parse(parsedData);
                            }

                            if (Array.isArray(parsedData) && parsedData.length > 0) {
                                if (Array.isArray(parsedData[0])) {
                                    bodyDinamicValues = parsedData[0];
                                } else {
                                    bodyDinamicValues = parsedData;
                                }

                                // Convertir valores antiguos de { } a [ ] si existen
                                bodyDinamicValues = bodyDinamicValues.map(function(val) {
                                    if (typeof val === 'string') {
                                        return val.replace(/\{/g, '[').replace(/\}/g, ']');
                                    }
                                    return val;
                                });
                            }
                        } catch(e) {
                            console.error('Error parsing body_dinamic:', e);
                        }
                    }

                    // Generar inputs dinámicos
                    generarInputsParametrosFactura(bodyDinamicValues);

                    // Mostrar preview inicial
                    actualizarPreviewFactura();
                },
                error: function(xhr) {
                    console.error('Error al cargar plantilla Meta:', xhr);
                    $('#parametros-meta-factura').hide();
                    $('#preview-mensaje-meta-factura').hide();
                }
            });
        }

        function generarInputsParametrosFactura(valoresDinamicos = []) {
            const $container = $('#inputs-parametros-factura');
            $container.empty();

            if (bodyTextValuesFactura.length === 0) {
                $('#parametros-meta-factura').hide();
                return;
            }

            // Generar un input por cada parámetro
            bodyTextValuesFactura.forEach(function(valorEjemplo, index) {
                const numeroParam = index + 1;
                const valorDinamico = valoresDinamicos[index] || '';

                // Crear contenedor principal
                const $paramGroup = $('<div class="parametro-meta-group mb-4 p-3 border rounded"></div>');

                // Label
                const $label = $('<label class="control-label d-block mb-2"><strong>Parámetro ' + numeroParam + '</strong> <small class="text-muted">(ejemplo: ' + valorEjemplo + ')</small></label>');

                // Contenedor del input con botones
                const $inputWrapper = $('<div class="input-group mb-2"></div>');

                // Input principal
                const $input = $('<input>', {
                    type: 'text',
                    class: 'form-control parametro-meta-input-factura',
                    'data-param-index': index,
                    placeholder: 'Escriba texto o use campos dinámicos',
                    value: valorDinamico
                });

                // Botón dropdown para agregar campos
                const $dropdownBtn = $('<button class="btn btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-plus"></i> Campos</button>');
                const $dropdownMenu = $('<ul class="dropdown-menu dropdown-menu-right"></ul>');

                // Agregar opciones al dropdown
                Object.keys(camposDinamicos).forEach(function(categoria) {
                    const $categoriaHeader = $('<li><h6 class="dropdown-header">' + categoria.charAt(0).toUpperCase() + categoria.slice(1) + '</h6></li>');
                    $dropdownMenu.append($categoriaHeader);

                    Object.keys(camposDinamicos[categoria]).forEach(function(campo) {
                        const campoKey = '[' + categoria + '.' + campo + ']';
                        const $item = $('<li><a class="dropdown-item" href="#" data-campo="' + campoKey + '" data-param-index="' + index + '">' + camposDinamicos[categoria][campo] + ' <code>' + campoKey + '</code></a></li>');
                        $dropdownMenu.append($item);
                    });
                });

                // Event listener para insertar campos
                $dropdownMenu.on('click', 'a', function(e) {
                    e.preventDefault();
                    const campo = $(this).data('campo');
                    const paramIndex = $(this).data('param-index');
                    const $targetInput = $('.parametro-meta-input-factura[data-param-index="' + paramIndex + '"]');
                    const cursorPos = $targetInput[0].selectionStart || $targetInput.val().length;
                    const textBefore = $targetInput.val().substring(0, cursorPos);
                    const textAfter = $targetInput.val().substring(cursorPos);
                    $targetInput.val(textBefore + campo + textAfter);
                    $targetInput.focus();
                    $targetInput[0].setSelectionRange(cursorPos + campo.length, cursorPos + campo.length);
                    actualizarPreviewFactura();
                });

                // Botón para limpiar
                const $clearBtn = $('<button class="btn btn-outline-danger" type="button" title="Limpiar"><i class="fa fa-times"></i></button>');
                $clearBtn.on('click', function() {
                    $input.val('');
                    actualizarPreviewFactura();
                });

                $inputWrapper.append($input);
                $inputWrapper.append($dropdownBtn);
                $inputWrapper.append($dropdownMenu);
                $inputWrapper.append($clearBtn);

                // Event listener para actualizar preview
                $input.on('input keyup', function() {
                    actualizarPreviewFactura();
                });

                // Información adicional
                const $info = $('<small class="text-muted d-block mt-2"><i class="fa fa-info-circle"></i> Puede escribir texto libre y agregar campos dinámicos desde el menú</small>');

                $paramGroup.append($label);
                $paramGroup.append($inputWrapper);
                $paramGroup.append($info);
                $container.append($paramGroup);
            });

            $('#parametros-meta-factura').show();
        }

        function actualizarPreviewFactura() {
            if (!plantillaMetaFacturaActual || !plantillaMetaFacturaActual.contenido) {
                $('#preview-mensaje-meta-factura').hide();
                return;
            }

            let contenido = plantillaMetaFacturaActual.contenido;

            // Obtener valores de los inputs
            const valoresParametros = [];
            $('.parametro-meta-input-factura').each(function() {
                let valor = $(this).val() || '';
                // Reemplazar placeholders con valores de ejemplo (solo para preview)
                valor = valor.replace(/\[contacto\.nombre\]/g, 'Juan');
                valor = valor.replace(/\[contacto\.apellido1\]/g, 'Pérez');
                valor = valor.replace(/\[contacto\.apellido2\]/g, 'González');
                valor = valor.replace(/\[factura\.fecha\]/g, '01/01/2024');
                valor = valor.replace(/\[factura\.vencimiento\]/g, '15/01/2024');
                valor = valor.replace(/\[factura\.total\]/g, '$100.000');
                valor = valor.replace(/\[factura\.porpagar\]/g, '$50.000');
                valor = valor.replace(/\[empresa\.nombre\]/g, 'Mi Empresa S.A.S.');
                valor = valor.replace(/\[empresa\.nit\]/g, '900123456-1');
                valoresParametros.push(valor);
            });

            // Reemplazar placeholders {{1}}, {{2}}, etc.
            valoresParametros.forEach(function(valor, index) {
                const numeroParam = index + 1;
                const placeholderText = '{{' + numeroParam + '}}';
                if (valor && valor.trim() !== '') {
                    contenido = contenido.replace(new RegExp('\\{\\{' + numeroParam + '\\}\\}', 'g'), valor);
                }
            });

            // Mostrar preview
            const $preview = $('#preview-mensaje-meta-factura');
            $preview.html(`
                <hr class="my-4">
                <div class="alert alert-info">
                    <strong><i class="fa fa-eye"></i> Vista Previa del Mensaje:</strong>
                    <div class="mt-3 p-3 bg-white rounded border" style="white-space: pre-wrap; font-family: monospace;">
                        ${contenido.replace(/\n/g, '<br>')}
                    </div>
                </div>
            `).show();
        }

        function guardarPlantillaFacturaWhatsapp() {
            var plantillaId = $('#plantilla_meta_factura').val();
            if (!plantillaId) {
                Swal.fire({
                    type: 'error',
                    title: 'Error',
                    text: 'Debe seleccionar una plantilla'
                });
                return;
            }

            // Obtener valores de body_dinamic
            const bodyDinamicValues = [];
            $('.parametro-meta-input-factura').each(function() {
                bodyDinamicValues.push($(this).val() || '');
            });

            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/empresa/configuracion/guardar-plantilla-factura-whatsapp';
            } else {
                var url = '/empresa/configuracion/guardar-plantilla-factura-whatsapp';
            }

            $.ajax({
                url: url,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                method: 'post',
                data: {
                    plantilla_id: plantillaId,
                    body_dinamic_params: bodyDinamicValues
                },
                success: function(data) {
                    if (data.success == 1) {
                        Swal.fire({
                            type: 'success',
                            title: 'Configuración guardada',
                            text: data.message || 'La plantilla ha sido configurada correctamente',
                            showConfirmButton: false,
                            timer: 3000
                        });
                        $('#config_plantilla_factura_whatsapp').modal('hide');
                    } else {
                        Swal.fire({
                            type: 'error',
                            title: 'Error',
                            text: data.message || 'No se pudo guardar la configuración'
                        });
                    }
                },
                error: function(xhr) {
                    var errorMessage = 'Error al guardar la configuración';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    Swal.fire({
                        type: 'error',
                        title: 'Error',
                        text: errorMessage
                    });
                }
            });
        }
        // ============================================================
        // VARIABLES GLOBALES PARA PLANTILLA TIRILLA WHATSAPP
        // ============================================================
        let plantillaMetaTirillaActual = null;
        let bodyTextValuesTirilla = [];

        // Cargar plantillas Meta al abrir el modal de tirilla
        $('#config_plantilla_tirilla_whatsapp').on('show.bs.modal', function() {
            cargarPlantillasMetaTirillaDisponibles();
        });

        // Inicializar selectpicker después de que el modal de tirilla se muestre
        $('#config_plantilla_tirilla_whatsapp').on('shown.bs.modal', function() {
            $('#plantilla_meta_tirilla').selectpicker('refresh');
        });

        function cargarPlantillasMetaTirillaDisponibles() {
            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/empresa/configuracion/get-plantillas-meta-tirilla';
            } else {
                var url = '/empresa/configuracion/get-plantillas-meta-tirilla';
            }

            $.ajax({
                url: url,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                method: 'get',
                success: function(data) {
                    var $select = $('#plantilla_meta_tirilla');
                    $select.empty();
                    $select.append('<option value="">-- Seleccione una plantilla --</option>');

                    var idPreferida = null;

                    if (data.plantillas && data.plantillas.length > 0) {
                        data.plantillas.forEach(function(plantilla) {
                            if (plantilla.preferida_tirilla == 1) {
                                idPreferida = plantilla.id;
                            }
                            $select.append('<option value="' + plantilla.id + '">' + plantilla.title + '</option>');
                        });
                    }

                    $select.selectpicker('refresh');

                    // Si hay una plantilla preferida, cargarla automáticamente
                    if (idPreferida) {
                         $select.val(idPreferida);
                         $select.selectpicker('refresh');
                         cargarPlantillaMetaTirilla(idPreferida);
                    }
                },
                error: function(xhr) {
                    console.error('Error al cargar plantillas Meta Tirilla:', xhr);
                }
            });
        }

        function cargarPlantillaMetaTirilla(plantillaId) {
            if (!plantillaId) {
                $('#parametros-meta-tirilla').hide();
                $('#preview-mensaje-meta-tirilla').hide();
                return;
            }

            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/empresa/configuracion/get-plantilla-meta-tirilla/' + plantillaId;
            } else {
                var url = '/empresa/configuracion/get-plantilla-meta-tirilla/' + plantillaId;
            }

            $.ajax({
                url: url,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                method: 'get',
                success: function(data) {
                    if (data.error) {
                        console.error('Error al cargar plantilla:', data.error);
                        $('#parametros-meta-tirilla').hide();
                        $('#preview-mensaje-meta-tirilla').hide();
                        return;
                    }

                    plantillaMetaTirillaActual = data;

                    // Procesar body_text para obtener los parámetros
                    if (data.body_text && Array.isArray(data.body_text) && data.body_text.length > 0) {
                        bodyTextValuesTirilla = Array.isArray(data.body_text[0]) ? data.body_text[0] : [];
                    } else {
                        bodyTextValuesTirilla = [];
                    }

                    // Cargar body_dinamic si existe
                    let bodyDinamicValues = [];
                    if (data.body_dinamic) {
                        try {
                            let parsedData = data.body_dinamic;

                            if (typeof parsedData === 'string') {
                                parsedData = JSON.parse(parsedData);
                            }

                            if (Array.isArray(parsedData) && parsedData.length > 0) {
                                if (Array.isArray(parsedData[0])) {
                                    bodyDinamicValues = parsedData[0];
                                } else {
                                    bodyDinamicValues = parsedData;
                                }
                                // Convertir valores antiguos de { } a [ ] si existen
                                bodyDinamicValues = bodyDinamicValues.map(function(val) {
                                    if (typeof val === 'string') {
                                        return val.replace(/\{/g, '[').replace(/\}/g, ']');
                                    }
                                    return val;
                                });
                            }
                        } catch(e) {
                            console.error('Error parsing body_dinamic:', e);
                        }
                    }

                    // Generar inputs dinámicos
                    generarInputsParametrosTirilla(bodyDinamicValues);

                    // Mostrar preview inicial
                    actualizarPreviewTirilla();
                },
                error: function(xhr) {
                    console.error('Error al cargar plantilla Meta Tirilla:', xhr);
                    $('#parametros-meta-tirilla').hide();
                    $('#preview-mensaje-meta-tirilla').hide();
                }
            });
        }

        function generarInputsParametrosTirilla(valoresDinamicos = []) {
            const $container = $('#inputs-parametros-tirilla');
            $container.empty();

            if (bodyTextValuesTirilla.length === 0) {
                $('#parametros-meta-tirilla').hide();
                return;
            }

            // Generar un input por cada parámetro
            bodyTextValuesTirilla.forEach(function(valorEjemplo, index) {
                const numeroParam = index + 1;
                const valorDinamico = valoresDinamicos[index] || '';

                // Crear contenedor principal
                const $paramGroup = $('<div class="parametro-meta-group mb-4 p-3 border rounded"></div>');

                // Label
                const $label = $('<label class="control-label d-block mb-2"><strong>Parámetro ' + numeroParam + '</strong> <small class="text-muted">(ejemplo: ' + valorEjemplo + ')</small></label>');

                // Contenedor del input con botones
                const $inputWrapper = $('<div class="input-group mb-2"></div>');

                // Input principal
                const $input = $('<input>', {
                    type: 'text',
                    class: 'form-control parametro-meta-input-tirilla',
                    'data-param-index': index,
                    placeholder: 'Escriba texto o use campos dinámicos',
                    value: valorDinamico
                });

                // Botón dropdown para agregar campos
                const $dropdownBtn = $('<button class="btn btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-plus"></i> Campos</button>');
                const $dropdownMenu = $('<ul class="dropdown-menu dropdown-menu-right"></ul>');

                // Agregar opciones al dropdown
                Object.keys(camposDinamicos).forEach(function(categoria) {
                    const $categoriaHeader = $('<li><h6 class="dropdown-header">' + categoria.charAt(0).toUpperCase() + categoria.slice(1) + '</h6></li>');
                    $dropdownMenu.append($categoriaHeader);

                    Object.keys(camposDinamicos[categoria]).forEach(function(campo) {
                        const campoKey = '[' + categoria + '.' + campo + ']';
                        const $item = $('<li><a class="dropdown-item" href="#" data-campo="' + campoKey + '" data-param-index="' + index + '">' + camposDinamicos[categoria][campo] + ' <code>' + campoKey + '</code></a></li>');
                        $dropdownMenu.append($item);
                    });
                });

                // Event listener para insertar campos
                $dropdownMenu.on('click', 'a', function(e) {
                    e.preventDefault();
                    const campo = $(this).data('campo');
                    const paramIndex = $(this).data('param-index');
                    const $targetInput = $('.parametro-meta-input-tirilla[data-param-index="' + paramIndex + '"]');
                    const cursorPos = $targetInput[0].selectionStart || $targetInput.val().length;
                    const textBefore = $targetInput.val().substring(0, cursorPos);
                    const textAfter = $targetInput.val().substring(cursorPos);
                    $targetInput.val(textBefore + campo + textAfter);
                    $targetInput.focus();
                    $targetInput[0].setSelectionRange(cursorPos + campo.length, cursorPos + campo.length);
                    actualizarPreviewTirilla();
                });

                // Botón para limpiar
                const $clearBtn = $('<button class="btn btn-outline-danger" type="button" title="Limpiar"><i class="fa fa-times"></i></button>');
                $clearBtn.on('click', function() {
                    $input.val('');
                    actualizarPreviewTirilla();
                });

                $inputWrapper.append($input);
                $inputWrapper.append($dropdownBtn);
                $inputWrapper.append($dropdownMenu);
                $inputWrapper.append($clearBtn);

                // Event listener para actualizar preview
                $input.on('input keyup', function() {
                    actualizarPreviewTirilla();
                });

                // Información adicional
                const $info = $('<small class="text-muted d-block mt-2"><i class="fa fa-info-circle"></i> Puede escribir texto libre y agregar campos dinámicos desde el menú</small>');

                $paramGroup.append($label);
                $paramGroup.append($inputWrapper);
                $paramGroup.append($info);
                $container.append($paramGroup);
            });

            $('#parametros-meta-tirilla').show();
        }

        function actualizarPreviewTirilla() {
            if (!plantillaMetaTirillaActual || !plantillaMetaTirillaActual.contenido) {
                $('#preview-mensaje-meta-tirilla').hide();
                return;
            }

            let contenido = plantillaMetaTirillaActual.contenido;

            // Obtener valores de los inputs
            const valoresParametros = [];
            $('.parametro-meta-input-tirilla').each(function() {
                let valor = $(this).val() || '';
                // Reemplazar placeholders con valores de ejemplo (solo para preview)
                valor = valor.replace(/\[contacto\.nombre\]/g, 'Juan');
                valor = valor.replace(/\[contacto\.apellido1\]/g, 'Pérez');
                valor = valor.replace(/\[contacto\.apellido2\]/g, 'González');
                valor = valor.replace(/\[factura\.fecha\]/g, '01/01/2024');
                valor = valor.replace(/\[factura\.vencimiento\]/g, '15/01/2024');
                valor = valor.replace(/\[factura\.total\]/g, '$100.000');
                valor = valor.replace(/\[factura\.porpagar\]/g, '$50.000');
                valor = valor.replace(/\[empresa\.nombre\]/g, 'Mi Empresa S.A.S.');
                valor = valor.replace(/\[empresa\.nit\]/g, '900123456-1');
                valoresParametros.push(valor);
            });

            // Reemplazar placeholders {{1}}, {{2}}, etc.
            valoresParametros.forEach(function(valor, index) {
                const numeroParam = index + 1;
                if (valor && valor.trim() !== '') {
                    contenido = contenido.replace(new RegExp('\\{\\{' + numeroParam + '\\}\\}', 'g'), valor);
                }
            });

            // Mostrar preview
            const $preview = $('#preview-mensaje-meta-tirilla');
            $preview.html(`
                <hr class="my-4">
                <div class="alert alert-info">
                    <strong><i class="fa fa-eye"></i> Vista Previa del Mensaje:</strong>
                    <div class="mt-3 p-3 bg-white rounded border" style="white-space: pre-wrap; font-family: monospace;">
                        ${contenido.replace(/\n/g, '<br>')}
                    </div>
                </div>
            `).show();
        }

        function guardarPlantillaTirillaWhatsapp() {
            var plantillaId = $('#plantilla_meta_tirilla').val();
            if (!plantillaId) {
                Swal.fire({
                    type: 'error',
                    title: 'Error',
                    text: 'Debe seleccionar una plantilla'
                });
                return;
            }

            // Obtener valores de body_dinamic
            const bodyDinamicValues = [];
            $('.parametro-meta-input-tirilla').each(function() {
                bodyDinamicValues.push($(this).val() || '');
            });

            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/empresa/configuracion/guardar-plantilla-tirilla-whatsapp';
            } else {
                var url = '/empresa/configuracion/guardar-plantilla-tirilla-whatsapp';
            }

            $.ajax({
                url: url,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                method: 'post',
                data: {
                    plantilla_id: plantillaId,
                    body_dinamic_params: bodyDinamicValues
                },
                success: function(data) {
                    if (data.success == 1) {
                        Swal.fire({
                            type: 'success',
                            title: 'Configuración guardada',
                            text: data.message || 'La plantilla ha sido configurada correctamente',
                            showConfirmButton: false,
                            timer: 3000
                        });
                        $('#config_plantilla_tirilla_whatsapp').modal('hide');
                    } else {
                        Swal.fire({
                            type: 'error',
                            title: 'Error',
                            text: data.message || 'No se pudo guardar la configuración'
                        });
                    }
                },
                error: function(xhr) {
                    var errorMessage = 'Error al guardar la configuración';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    Swal.fire({
                        type: 'error',
                        title: 'Error',
                        text: errorMessage
                    });
                }
            });
        }
        function buscarConfiguracion() {
            let input = document.getElementById('buscador-configuracion').value.toLowerCase().trim();
            let cards = document.querySelectorAll('.configuracion > .enlaces');

            cards.forEach(card => {
                let title = card.querySelector('.card-title');
                let textTitle = title ? title.innerText.toLowerCase() : '';
                let description = card.querySelector('p');
                let textDesc = description ? description.innerText.toLowerCase() : '';
                
                let links = card.querySelectorAll('a');
                
                let matchCard = textTitle.includes(input) || textDesc.includes(input);
                let anyLinkMatched = false;

                links.forEach(link => {
                    let textLink = link.innerText.toLowerCase();
                    let matchThisLink = textLink.includes(input);
                    
                    if (input === '') {
                        link.style.display = 'flex'; // restore
                    } else if (matchCard || matchThisLink) {
                        link.style.display = 'flex';
                        if (matchThisLink && !matchCard) {
                            anyLinkMatched = true;
                        }
                    } else {
                        link.style.display = 'none';
                    }
                });

                if (input === '' || matchCard || anyLinkMatched) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }
    </script>
@endsection

@section('style')
    <style>
        .nav-tabs .nav-link {
            font-size: 1em;
        }

        .nav-tabs .nav-link.active,
        .nav-tabs .nav-item.show .nav-link {
            background-color: {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }};
            color: #fff !important;
        }

        .nav-pills .nav-link.active,
        .nav-pills .show>.nav-link {
            color: #fff !important;
            background-color: {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }} !important;
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
        }
    </style>
@endsection
