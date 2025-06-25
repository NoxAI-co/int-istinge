@extends('layouts.app')

@section('style')
<link href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.css" rel="stylesheet">
<style>
    .notice {
        padding: 15px;
        background-color: #fafafa;
        border-left: 6px solid #7f7f84;
        margin-bottom: 10px;
        -webkit-box-shadow: 0 5px 8px -6px rgba(0, 0, 0, .2);
        -moz-box-shadow: 0 5px 8px -6px rgba(0, 0, 0, .2);
        box-shadow: 0 0 0 0 rgba(0, 0, 0, 0);
    }

    .notice-sm {
        padding: 10px;
        font-size: 80%;
    }

    .notice-lg {
        padding: 35px;
        font-size: large;
    }

    .notice-success {
        border-color: #80D651;
    }

    .notice-success>strong {
        color: #80D651;
    }

    .notice-info {
        border-color: #267eb5;
    }

    .notice-info>strong {
        color: #45ABCD;
    }

    .notice-warning {
        border-color: #FEAF20;
    }

    .notice-warning>strong {
        color: #FEAF20;
    }

    .notice-danger {
        border-color: #d73814;
    }

    .notice-danger>strong {
        color: #d73814;
    }

    .ai-icon-container {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: linear-gradient(135deg, #6e3fc3, #31a2f0);
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 0 15px rgba(110, 63, 195, 0.3);
        position: relative;
    }

    .ai-icon {
        font-size: 24px;
        color: white;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% {
            transform: scale(1);
            opacity: 1;
        }

        50% {
            transform: scale(1.2);
            opacity: 0.8;
        }

        100% {
            transform: scale(1);
            opacity: 1;
        }
    }

    .card-counter {
        box-shadow: 2px 2px 10px #797979;
        margin: 5px;
        padding: 20px 10px;
        background-color: #fff;
        height: 100px;
        border-radius: 5px;
        transition: .1s linear all;
    }

    .card-counter.primary {
        background-color: #007bff;
        color: #FFF;
    }

    .card-counter.danger {
        background-color: #ef5350;
        color: #FFF;
    }

    .card-counter.success {
        background-color: #66bb6a;
        color: #FFF;
    }

    .card-counter.info {
        background-color: #26c6da;
        color: #FFF;
    }

    .card-counter i {
        font-size: 5em;
        opacity: 0.2;
    }

    .card-counter .count-numbers {
        position: absolute;
        right: 35px;
        top: 20px;
        font-size: 32px;
        display: block;
    }

    .card-counter .count-name {
        position: absolute;
        right: 10px;
        top: 65px;
        font-style: italic;
        text-transform: capitalize;
        opacity: 0.5;
        display: block;
        font-size: 18px;
    }

    hr {
        margin-top: 1rem;
        margin-bottom: 1rem;
        border: 0;

        border-top: 1px solid {
                {
                Auth: :user()->rol > 1 ? Auth::user()->empresa()->color:''
            }
        }

        ;
    }

    /* Estilos adicionales para el dashboard */
    .dash-card {
        background: white;
        border-radius: 4px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
        margin-bottom: 20px;
        padding: 20px;
    }

    .dash-card-header {
        border-bottom: 1px solid #eee;
        margin-bottom: 15px;
        padding-bottom: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .dash-card-header h4 {
        margin: 0;
        color: #333;
    }

    .trend-indicator {
        display: flex;
        align-items: center;
        font-size: 12px;
        margin-top: 5px;
    }

    .trend-up {
        color: #1cc88a;
    }

    .trend-down {
        color: #e74a3b;
    }

    .trend-indicator i {
        margin-right: 4px;
    }

    .metric-small {
        font-size: 14px;
        color: #666;
        margin-top: 5px;
    }

    .graph-container {
        height: 250px;
        width: 100%;
        position: relative;
        margin-top: 15px;
    }

    /* Morris chart specific styles */
    #contractStatus,
    #billingStatus,
    #ticketStatus,
    #catvStatus {
        width: 100%;
        height: 250px;
    }

    .morris-hover {
        position: absolute;
        z-index: 1000;
    }

    .morris-hover.morris-default-style {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 4px;
        padding: 8px;
        border: solid 1px rgba(0, 0, 0, 0.1);
    }

    /* Estilos adicionales para atajos rápidos */
    .shortcuts-section {
        background: #f8f9fc;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 30px;
    }

    .card-counter {
        position: relative;
        display: grid;
        grid-template-columns: auto 1fr;
        grid-gap: 10px;
        align-items: center;
        padding: 15px;
        margin-bottom: 15px;
        min-height: 80px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        overflow: hidden;
    }

    .card-counter:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    .card-counter i {
        grid-row: span 2;
        font-size: 2em;
        opacity: 0.9;
        margin-right: 5px;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
    }

    .card-counter .count-numbers {
        font-size: 16px;
        font-weight: 600;
        line-height: 1.2;
        margin: 0;
        padding: 0;
        text-align: right;
        width: 100%;
    }

    .card-counter .count-name {
        font-size: 14px;
        line-height: 1.2;
        margin: 0;
        padding: 0;
        opacity: 0.95;
        font-weight: 500;
        text-align: right;
        width: 100%;
    }

    .card-counter.primary {
        background-color: #007bff;
        color: white;
    }

    .card-counter.success {
        background-color: #28a745;
        color: white;
    }

    .card-counter.warning {
        background-color: #f6c23e;
        color: white;
    }

    .card-counter.info {
        background-color: #17a2b8;
        color: white;
    }

    /* Media queries para responsividad */
    @media (max-width: 1200px) {
        .card-counter {
            padding: 12px;
        }

        .card-counter i {
            font-size: 1.8em;
            width: 35px;
        }
    }

    @media (max-width: 992px) {
        .col-lg-2 {
            flex: 0 0 33.333333%;
            max-width: 33.333333%;
        }
    }

    @media (max-width: 768px) {
        .col-lg-2 {
            flex: 0 0 50%;
            max-width: 50%;
        }

        .card-counter {
            min-height: 70px;
        }
    }

    @media (max-width: 576px) {
        .col-lg-2 {
            flex: 0 0 100%;
            max-width: 100%;
        }

        .card-counter {
            grid-template-columns: 40px 1fr;
            padding: 10px;
        }

        .card-counter i {
            font-size: 1.6em;
            width: 30px;
        }
    }

    /* Estilos para Estado General del Sistema */
    .detailed-section {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
    }

    .section-header {
        padding: 20px 25px;
        border-bottom: 1px solid #eef2f7;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .section-header h4 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: #2c3e50;
    }

    .section-header i {
        margin-right: 10px;
        color: #5e72e4;
    }

    .notice.notice-info {
        background: #f8fafc;
        border-left: 4px solid #5e72e4;
        border-radius: 4px;
        padding: 15px 20px;
        margin-bottom: 20px;
        transition: all 0.3s ease;
    }

    .notice.notice-info:hover {
        background: #eef2f7;
    }

    .notice h4 {
        color: #2c3e50;
        font-size: 16px;
        font-weight: 600;
        margin: 0;
    }

    .btn.btn-dark,
    .btn.btn-danger,
    .btn.btn-success,
    .btn.btn-info {
        border-radius: 8px;
        padding: 10px 15px;
        transition: all 0.2s ease;
        border: none;
        position: relative;
        overflow: hidden;
        min-height: 70px;
        display: grid;
        grid-template-rows: auto auto;
        align-items: center;
        text-align: center;
        background-color: rgba(255, 255, 255, 0.05);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .btn.btn-dark {
        background-color: #343a40;
        color: white;
    }

    .btn.btn-danger {
        background-color: #dc3545;
        color: white;
    }

    .btn.btn-success {
        background-color: #28a745;
        color: white;
    }

    .btn.btn-info {
        background-color: #17a2b8;
        color: white;
    }

    .btn.btn-dark:hover,
    .btn.btn-danger:hover,
    .btn.btn-success:hover,
    .btn.btn-info:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        opacity: 0.95;
    }

    .btn i {
        font-size: 18px;
        margin-bottom: 5px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .btn .count-numbers {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 2px;
    }

    .btn .count-name {
        font-size: 12px;
        font-weight: 500;
        opacity: 0.9;
        text-transform: uppercase;
    }

    .collapse-icon {
        transition: transform 0.3s ease;
    }

    .collapsed .collapse-icon {
        transform: rotate(180deg);
    }

    .section-body {
        padding: 20px;
    }
</style>
@endsection

@section('content')
@if(auth()->user()->modo_lectura())
<div class="alert alert-warning text-left" role="alert">
    <h4 class="alert-heading text-uppercase">Integra Colombia: Suscripción Vencida</h4>
    <p>Si desea seguir disfrutando de nuestros servicios adquiera alguno de nuestros planes.</p>
    <p>Medios de pago Nequi: 3026003360 Cuenta de ahorros Bancolombia 42081411021 CC 1001912928 Ximena Herrera representante legal. Adjunte su pago para reactivar su membresía</p>
</div>
@endif

@if(isset($_SESSION['permisos']['113']) && Auth::user()->rol != 8)
<div class="row">
    <!-- Header Dashboard -->
    <div class="col-12">
        <div class="dash-card mb-4">
            <div class="dash-card-header">
                <div class="d-flex align-items-center">
                    <div class="ai-icon-container mr-3">
                        <i class="fas fa-robot ai-icon"></i>
                    </div>
                    <div>
                        <h4>Dashboard General</h4>
                        <p class="text-muted mb-0">Resumen de actividad del sistema</p>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- Nueva sección de atajos -->
    <div class="col-12">
        <div class="shortcuts-section">
            <h5 class="shortcuts-title mb-4">
                <i class="fas fa-bolt mr-2"></i>Atajos
            </h5>
            <div class="row justify-content-center">
                @if(isset($_SESSION['permisos']['2']))
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <a href="{{route('contactos.create')}}" class="text-decoration-none d-block">
                        <div class="card-counter success">
                            <i class="fas fa-users"></i>
                            <span class="count-numbers">Nuevo</span>
                            <span class="count-name">Cliente</span>
                        </div>
                    </a>
                </div>
                @endif @if(isset($_SESSION['permisos']['411']))
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <a href="{{route('contratos.create')}}" class="text-decoration-none d-block">
                        <div class="card-counter info">
                            <i class="fas fa-file-contract"></i>
                            <span class="count-numbers">Nuevo</span>
                            <span class="count-name">Contrato</span>
                        </div>
                    </a>
                </div>
                @endif @if(isset($_SESSION['permisos']['202']))
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <a href="{{route('radicados.create')}}" class="text-decoration-none d-block">
                        <div class="card-counter warning" style="background-color: #f6c23e; color: white;">
                            <i class="fas fa-ticket-alt"></i>
                            <span class="count-numbers">Nuevo</span>
                            <span class="count-name">Radicado</span>
                        </div>
                    </a>
                </div>
                @endif @if(isset($_SESSION['permisos']['42']))
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <a href="{{route('facturas.create')}}" class="text-decoration-none d-block">
                        <div class="card-counter primary">
                            <i class="fas fa-file-invoice-dollar"></i>
                            <span class="count-numbers">Nueva</span>
                            <span class="count-name">Factura</span>
                        </div>
                    </a>
                </div>
                @endif @if(isset($_SESSION['permisos']['420']))
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <a href="{{route('facturas.create-electronica')}}" class="text-decoration-none d-block">
                        <div class="card-counter success">
                            <i class="fas fa-file-invoice"></i>
                            <span class="count-numbers">Nueva</span>
                            <span class="count-name">Factura Electrónica</span>
                        </div>
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div> <!-- Nueva sección Detalles por Sección -->
    <div class="col-12">
        <div class="detailed-section">
            <div class="section-header collapsed" data-toggle="collapse" href="#detailedSectionContent" role="button" aria-expanded="false" style="cursor: pointer;">
                <div class="d-flex justify-content-between align-items-center w-100">
                    <div class="d-flex align-items-center">
                        <div>
                            <h4><i class="fas fa-chart-pie"></i>Accesos Rápidos</h4>
                            <small class="text-muted">Métricas y estadísticas por área</small>
                        </div>
                    </div>
                    <div>
                        <i class="fas fa-chevron-down collapse-icon"></i>
                    </div>
                </div>
            </div>

            <div class="collapse" id="detailedSectionContent">
                <!-- CONTRATOS -->
                <div class="section-body">
                    <div class="notice notice-info">
                        <h4 class="float-left">CONTRATOS</h4>
                        <hr class="mt-4">
                    </div>
                    <div id="welcomeContratos">
                        <div class="row justify-content-center">
                            <div class="col-md-3">
                                <a href="{{ isset($_SESSION['permisos']['411']) ? route('contratos.index') : 'javascript:void' }}">
                                    <div class="btn btn-dark w-100">
                                        <i class="fas fa-file-contract"></i>
                                        <span class="count-numbers">{{ $contra_factura }}</span>
                                        <span class="count-name">Registrados</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="{{ isset($_SESSION['permisos']['411']) ? route('contratos.enabled') : 'javascript:void' }}">
                                    <div class="btn btn-dark w-100">
                                        <i class="fas fa-file-contract"></i>
                                        <span class="count-numbers">{{ $contra_ena }}</span>
                                        <span class="count-name">Habilitados</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="{{ isset($_SESSION['permisos']['411']) ? route('contratos.disabled') : 'javascript:void' }}">
                                    <div class="btn btn-dark w-100">
                                        <i class="fas fa-file-contract"></i>
                                        <span class="count-numbers">{{ $contra_disa }}</span>
                                        <span class="count-name">Deshabilitados</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TELEVISIÓN CATV -->
                <div class="card-body">
                    <div class="notice notice-info">
                        <h4 class="float-left">TELEVISIÓN CATV</h4>
                        <hr class="mt-4">
                    </div>
                    <div id="welcomeCatv">
                        <div class="row justify-content-center">
                            <div class="col-md-3">
                                <a href="{{ isset($_SESSION['permisos']['40']) ? route('inventario.television') : 'javascript:void' }}">
                                    <div class="btn btn-danger w-100">
                                        <i class="fas fa-file-contract"></i>
                                        <span class="count-numbers">{{ $contratosCatv }}</span>
                                        <span class="count-name">Registrados</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="{{ isset($_SESSION['permisos']['40']) ? route('inventario.television') : 'javascript:void' }}">
                                    <div class="btn btn-danger w-100">
                                        <i class="fas fa-file-contract"></i>
                                        <span class="count-numbers">{{ $contratosCatvEnabled }}</span>
                                        <span class="count-name">Habilitados</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="{{ isset($_SESSION['permisos']['40']) ? route('inventario.television') : 'javascript:void' }}">
                                    <div class="btn btn-danger w-100">
                                        <i class="fas fa-file-contract"></i>
                                        <span class="count-numbers">{{ $contratosCatvDisabled }}</span>
                                        <span class="count-name">Deshabilitados</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FACTURACIÓN -->
                <div class="card-body">
                    <div class="notice notice-info">
                        <h4 class="float-left">FACTURACIÓN</h4>
                        <hr class="mt-4">
                    </div>
                    <div id="welcomeFacturacion">
                        <div class="row justify-content-center">
                            <div class="col-md-3">
                                <a href="{{ isset($_SESSION['permisos']['40']) ? route('facturas.index') : 'javascript:void' }}">
                                    <div class="btn btn-success w-100">
                                        <i class="fas fa-file-invoice-dollar"></i>
                                        <span class="count-numbers">{{ $factura }}</span>
                                        <span class="count-name">Generados</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="{{ isset($_SESSION['permisos']['40']) ? route('facturas.tipo', 'cerradas') : 'javascript:void' }}">
                                    <div class="btn btn-success w-100">
                                        <i class="fas fa-file-invoice-dollar"></i>
                                        <span class="count-numbers">{{ $factura_cerrada }}</span>
                                        <span class="count-name">Cerradas</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="{{ isset($_SESSION['permisos']['40']) ? route('facturas.tipo', 'abiertas') : 'javascript:void' }}">
                                    <div class="btn btn-success w-100">
                                        <i class="fas fa-file-invoice-dollar"></i>
                                        <span class="count-numbers">{{ $factura_abierta }}</span>
                                        <span class="count-name">Abiertas</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div> <!-- RADICADOS -->
                <div class="card-body">
                    <div class="notice notice-info">
                        <h4 class="float-left">RADICADOS</h4>
                        <hr class="mt-4">
                    </div>
                    <div id="welcomeRadicados">
                        <div class="row justify-content-center">
                            <div class="col-md-3">
                                <a href="{{ isset($_SESSION['permisos']['201']) ? route('radicados.index') : 'javascript:void' }}">
                                    <div class="btn btn-info w-100">
                                        <i class="fas fa-ticket-alt"></i>
                                        <span class="count-numbers">{{ $radicados }}</span>
                                        <span class="count-name">Generados</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="{{ isset($_SESSION['permisos']['201']) ? route('radicados.index', ['estado' => 'Solventado']) : 'javascript:void' }}">
                                    <div class="btn btn-info w-100">
                                        <i class="fas fa-ticket-alt"></i>
                                        <span class="count-numbers">{{ $radicados_solventado }}</span>
                                        <span class="count-name">Solventados</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="{{ isset($_SESSION['permisos']['201']) ? route('radicados.index') : 'javascript:void' }}">
                                    <div class="btn btn-info w-100">
                                        <i class="fas fa-ticket-alt"></i>
                                        <span class="count-numbers">{{ $radicados_pendiente }}</span>
                                        <span class="count-name">Pendientes</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div> <!-- Cierre de detailed-section-content -->
        </div> <!-- Cierre de detailed-section -->
    </div> <!-- Cierre de col-12 -->
    <!-- Métricas y gráficas -->
    <div class="col-md-3">
        <div class="dash-card">
            <i class="fas fa-file-contract float-right text-primary" style="font-size: 24px;"></i>
            <h3>{{ $contra_ena + $contra_disa }}</h3>
            <div>Contratos Totales</div>
            <div class="metric-small">
                <span>{{ $contra_ena }} Habilitados</span> |
                <span>{{ $contra_disa }} Deshabilitados</span>
            </div>
        </div>
    </div>

    <!-- Resumen de Facturación -->
    <div class="col-md-3">
        <div class="dash-card">
            <i class="fas fa-file-invoice-dollar float-right text-success" style="font-size: 24px;"></i>
            <h3>{{ $factura }}</h3>
            <div>Facturas Totales</div>
            <div class="metric-small">
                <span>{{ $factura_cerrada }} Cerradas</span> |
                <span>{{ $factura_abierta }} Abiertas</span>
            </div>
        </div>
    </div>

    <!-- Resumen de Radicados -->
    <div class="col-md-3">
        <div class="dash-card">
            <i class="fas fa-ticket-alt float-right text-warning" style="font-size: 24px;"></i>
            <h3>{{ $radicados }}</h3>
            <div>Radicados Totales</div>
            <div class="metric-small">
                <span>{{ $radicados_solventado }} Solventados</span> |
                <span>{{ $radicados_pendiente }} Pendientes</span>
            </div>
        </div>
    </div>

    <!-- Resumen de CATV -->
    <div class="col-md-3">
        <div class="dash-card">
            <i class="fas fa-tv float-right text-info" style="font-size: 24px;"></i>
            <h3>{{ $contratosCatv }}</h3>
            <div>Contratos CATV</div>
            <div class="metric-small">
                <span>{{ $contratosCatvEnabled }} Habilitados</span> |
                <span>{{ $contratosCatvDisabled }} Deshabilitados</span>
            </div>
        </div>
    </div>
    <!-- Gráficas en línea horizontal -->

    <div class="col-md-3">
        <div class="dash-card">
            <div class="dash-card-header">
                <h4>Estado de Contratos</h4>
            </div>
            <div id="contractStatus" style="height: 200px; padding: 0; position: relative;"></div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="dash-card">
            <div class="dash-card-header">
                <h4>Estado de Facturación</h4>
            </div>
            <div id="billingStatus" style="height: 200px; padding: 0; position: relative;"></div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="dash-card">
            <div class="dash-card-header">
                <h4>Estado de Radicados</h4>
            </div>
            <div id="ticketStatus" style="height: 200px; padding: 0; position: relative;"></div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="dash-card">
            <div class="dash-card-header">
                <h4>Estado CATV</h4>
            </div>
            <div id="catvStatus" style="height: 200px; padding: 0; position: relative;"></div>
        </div>
    </div>


    <div class="col-md-12">
        <div class="dash-card">
            <div class="dash-card-header">
                <h4>Tendencias Mensuales</h4>
            </div>
            <div id="monthlyTrends" style="height: 250px; padding: 0; position: relative;"></div>
        </div>
    </div>

    <!-- Estadísticas de Facturación -->
    <div class="col-12">
        <div class="dash-card mb-4">
            <div class="dash-card-header">
                <div>
                    <h4>Resumen de Facturación</h4>
                    <p class="text-muted mb-0">Estado actual de facturación</p>
                </div>
            </div>
            <div class="row mt-4">
                <!-- Total Facturado -->
                <div class="col-md-6 col-lg-3 mb-4">
                    <div class="card-counter primary">
                        <i class="fas fa-dollar-sign"></i>
                        <div>
                            <span class="count-numbers">{{ number_format($total_facturado, 2) }}</span>
                            <span class="count-name">Total Facturado</span>
                        </div>
                    </div>
                </div>

                <!-- Facturas Electrónicas -->
                <div class="col-md-6 col-lg-3 mb-4">
                    <div class="card-counter info">
                        <i class="fas fa-file-invoice"></i>
                        <div>
                            <span class="count-numbers">{{ $facturas_electronicas }}</span>
                            <span class="count-name">Facturas Electrónicas</span>
                        </div>
                    </div>
                </div>

                <!-- Facturas Pendientes -->
                <div class="col-md-6 col-lg-3 mb-4">
                    <div class="card-counter warning">
                        <i class="fas fa-clock"></i>
                        <div>
                            <span class="count-numbers">{{ $factura_abierta }}</span>
                            <span class="count-name">Facturas Pendientes</span>
                        </div>
                    </div>
                </div>

                <!-- Facturas Pagadas -->
                <div class="col-md-6 col-lg-3 mb-4">
                    <div class="card-counter success">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <span class="count-numbers">{{ $factura_cerrada }}</span>
                            <span class="count-name">Facturas Pagadas</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Morris === 'undefined') {
            console.error('Morris.js no está cargado');
            return;
        }

        // Estado de Contratos
        new Morris.Donut({
            element: 'contractStatus',
            data: [{
                    label: 'Habilitados',
                    value: @json($contra_ena)
                },
                {
                    label: 'Deshabilitados',
                    value: @json($contra_disa)
                }
            ],
            colors: ['#1cc88a', '#e74a3b'],
            labelColor: '#3d4651',
            backgroundColor: '#fff',
            resize: true,
            formatter: function(value, data) {
                return value;
            }
        });

        // Estado de Facturación
        new Morris.Donut({
            element: 'billingStatus',
            data: [{
                    label: 'Cerradas',
                    value: @json($factura_cerrada)
                },
                {
                    label: 'Abiertas',
                    value: @json($factura_abierta)
                }
            ],
            colors: ['#1cc88a', '#e74a3b'],
            labelColor: '#3d4651',
            backgroundColor: '#fff',
            resize: true,
            formatter: function(value, data) {
                return value;
            }
        });

        // Estado de Radicados
        new Morris.Donut({
            element: 'ticketStatus',
            data: [{
                    label: 'Solventados',
                    value: @json($radicados_solventado)
                },
                {
                    label: 'Pendientes',
                    value: @json($radicados_pendiente)
                }
            ],
            colors: ['#1cc88a', '#e74a3b'],
            labelColor: '#3d4651',
            backgroundColor: '#fff',
            resize: true,
            formatter: function(value, data) {
                return value;
            }
        });

        // Estado CATV
        new Morris.Donut({
            element: 'catvStatus',
            data: [{
                    label: 'Habilitados',
                    value: @json($contratosCatvEnabled)
                },
                {
                    label: 'Deshabilitados',
                    value: @json($contratosCatvDisabled)
                }
            ],
            colors: ['#1cc88a', '#e74a3b'],
            labelColor: '#3d4651',
            backgroundColor: '#fff',
            resize: true,
            formatter: function(value, data) {
                return value;
            }
        }); // Gráfica de tendencias mensuales
        new Morris.Line({
            element: 'monthlyTrends',
            data: [{
                    month: 'Ene',
                    contratos: @json($contra_ena),
                    facturas: @json($factura)
                },
                {
                    month: 'Feb',
                    contratos: @json($contra_ena),
                    facturas: @json($factura)
                },
                {
                    month: 'Mar',
                    contratos: @json($contra_ena),
                    facturas: @json($factura)
                },
                {
                    month: 'Abr',
                    contratos: @json($contra_ena),
                    facturas: @json($factura)
                },
                {
                    month: 'May',
                    contratos: @json($contra_ena),
                    facturas: @json($factura)
                },
                {
                    month: 'Jun',
                    contratos: @json($contra_ena),
                    facturas: @json($factura)
                }
            ],
            xkey: 'month',
            ykeys: ['contratos', 'facturas'],
            labels: ['Contratos Activos', 'Facturas Generadas'],
            lineColors: ['#4e73df', '#1cc88a'],
            pointSize: 4,
            hideHover: false,
            resize: true,
            lineWidth: 3,
            smooth: false,
            pointFillColors: ['#fff', '#fff'],
            pointStrokeColors: ['#4e73df', '#1cc88a'],
            gridLineColor: '#e3e6f0',
            gridTextColor: '#858796',
            gridTextSize: 12,
            gridTextFamily: 'Open Sans',
            parseTime: false,
            behaveLikeLine: true,
            yLabelFormat: function(y) {
                return Math.round(y);
            }
        });

        // Ajuste de estilos para mejorar la visualización
        document.querySelectorAll('.morris-hover').forEach(function(el) {
            el.style.backgroundColor = '#fff';
        });
    });
</script>

@endif

@if(Auth::user()->rol == 8)
<div class="row card-description">
    <form action="https://checkout.wompi.co/p/" method="GET" id="form-wompi" class="d-none">
        <input type="hidden" name="public-key" value="{{env('WOMPI_KEY')}}" />
        <input type="hidden" name="currency" value="COP" />
        <input type="hidden" name="amount-in-cents" id="amount-in-cents" />
        <input type="hidden" name="reference" value="{{str_replace(' ', '_', Auth::user()->nombres)}}<?php echo '_IST_' . rand(); ?>" />
        <input type="hidden" name="redirect-url" value="https://istingenieria.online/RecargaWompi" />
        <button class="btn btn-success" type="submit" disabled>Pagar con Wompi</button>
    </form>

    <div class="col-md-4 offset-md-4" style="text-align:center;">
        <div class="contact-form">
            <h4>RECARGA SALDO CON WOMPI</h4>
            <input type="number" min="1" class="form-control my-3" id="recarga" value="0">
            <button class="btn btn-success" type="submit" onclick="confirmarp('form-wompi');" disabled>RECARGAR</button>
        </div>
    </div>
</div>

<script>
    function confirmarp(form, mensaje = "Lo vamos a redirigir a la pasarela de pago WOMPI para realizar la recarga", submensaje = '¿Desea continuar?', confirmar = 'Si') {
        if ($("#buyerFullName").val() != '') {
            swal({
                title: mensaje,
                text: submensaje,
                type: 'question',
                showCancelButton: true,
                confirmButtonColor: '#00ce68',
                cancelButtonColor: '#d33',
                confirmButtonText: confirmar,
                cancelButtonText: 'No',
            }).then((result) => {
                if (result.value) {
                    /*cargando(true);

                    $.ajax({
                        url: 'bk_equipos.php',
                        type: 'POST',
                        data: infoEmpleado,
                        contentType: false,
                        processData: false,
                        success: function(data) {
                            data = JSON.parse(data);

                            if (data['success'] == true) {
                                var monto_pago = $("#recarga").val();
                                $("#amount-in-cents").val(monto_pago+'00');
                                document.getElementById(form).submit();
                                cargando(true);
                            }
                        }
                    });
                    */
                }
            });
        } else {
            swal({
                title: 'Debe llenar la información solicitada',
                text: submensaje,
                type: 'warning',
                showCancelButton: true,
                showConfirmButton: false,
                cancelButtonColor: '#00ce68',
                cancelButtonText: 'Aceptar',
            })
        }
    }
</script>
@endif

<input type="hidden" id="simbolo" value="$">
@endsection