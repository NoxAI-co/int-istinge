<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="" />
    <meta name="author" content="">
    <meta name="keyword" content="">
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ $title }}</title>

    <link rel="shortcut icon" href="{{ contabo_url(env('LOGOS_FOLDER', 'logos'), 'favicon.png') }}" />
    <link rel="stylesheet" href="{{ asset('gritter/css/jquery.gritter.css') }}">
    <link rel="stylesheet" href="{{ asset('vendors/iconfonts/mdi/css/materialdesignicons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendors/css/vendor.bundle.base.css') }}">
    <link rel="stylesheet" href="{{ asset('vendors/css/vendor.bundle.addons.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('vendors/DataTables/datatables.min.css') }}" />
    <link rel="stylesheet" type="text/css"
        href="{{ asset('vendors/bootstrap-selectpicker/css/bootstrap-select.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('vendors/fontawesome/css/all.css') }}" />
    <link rel="stylesheet" type="text/css" href="{{ asset('vendors/flag-icon-css/css/flag-icon.min.css') }}" />
    <link rel="stylesheet" type="text/css" href="{{ asset('vendors/sweetalert2/sweetalert2.min.css') }}" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins">
    <link rel="stylesheet" href="{{ asset('vendors/bootstrap-datepicker/css/gijgo.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendors/morris/morris.css') }}">
    <link rel="stylesheet" href="{{ asset('vendors/profile-picture/profile-picture.css') }}">
    <link rel="stylesheet" href="{{ asset('vendors/dropify/dropify.css') }}">
    <link rel="stylesheet" href="{{ asset('vendors/dropzone/dropzone.css') }}">
    <link rel="stylesheet" href="{{ asset('vendors/light-gallery/css/lightgallery.css') }}">
    <link rel="stylesheet" href="{{ asset('vendors/autocomplete/jquery.auto-complete.css') }}">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/documentacion.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css">

    <style>
        /* Modal de bienvenida */
        .chat-ia-modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .chat-ia-modal-content {
            position: fixed;
            bottom: 90px;
            right: 25px;
            background-color: white;
            padding: 30px 25px;
            border-radius: 16px;
            width: 340px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
            text-align: center;
        }

        .chat-ia-modal-close {
            position: absolute;
            right: 15px;
            top: 10px;
            color: #aaa;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }

        .chat-ia-modal-close:hover {
            color: #000;
        }

        .chat-ia-welcome-emoji {
            font-size: 64px;
            margin-bottom: 15px;
        }

        .chat-ia-welcome-title {
            color: #333;
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .chat-ia-btn-primary,
        .chat-ia-btn-secondary {
            width: 100%;
            padding: 12px 20px;
            margin: 8px 0;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .chat-ia-btn-primary {
            background-color: #0066a1;
            color: white;
        }

        .chat-ia-btn-primary:hover {
            background-color: #004f7f;
        }

        .chat-ia-btn-secondary {
            background-color: #f0f0f0;
            color: #333;
        }

        .chat-ia-btn-secondary:hover {
            background-color: #e0e0e0;
        }

        /* Widget de chat */
        .chat-ia-widget {
            position: fixed;
            bottom: 90px;
            right: 25px;
            width: 380px;
            height: 500px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 40px rgba(0, 0, 0, 0.16);
            display: flex;
            flex-direction: column;
            z-index: 9999;
            transition: all 0.3s ease;
        }

        .chat-ia-widget.hidden {
            display: none;
        }

        .chat-ia-header {
            background: linear-gradient(135deg, #003f7f 100%, #003f7f 100%);
            color: white;
            padding: 16px 20px;
            border-radius: 12px 12px 0 0;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chat-ia-header button {
            background: transparent;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            line-height: 1;
        }

        .chat-ia-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #f9f9f9;
        }

        .chat-ia-bubble {
            margin-bottom: 12px;
            padding: 10px 14px;
            border-radius: 18px;
            max-width: 75%;
            word-wrap: break-word;
            font-size: 14px;
            line-height: 1.4;
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .chat-ia-bubble.user {
            background: #0066a1;
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 4px;
        }

        .chat-ia-bubble.bot {
            background: white;
            color: #333;
            margin-right: auto;
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .chat-ia-input-container {
            display: flex;
            padding: 15px;
            background: white;
            border-top: 1px solid #e0e0e0;
            border-radius: 0 0 12px 12px;
        }

        .chat-ia-input-container input {
            flex: 1;
            border: 1px solid #ddd;
            border-radius: 20px;
            padding: 10px 15px;
            font-size: 14px;
            outline: none;
        }

        .chat-ia-input-container input:focus {
            border-color: #0066a1;
        }

        .chat-ia-input-container button {
            background: #0066a1;
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            margin-left: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .chat-ia-input-container button:hover {
            background: #004f7f;
            transform: scale(1.05);
        }

        /* Responsive */
        @media (max-width: 768px) {

            .chat-ia-widget,
            .chat-ia-modal-content {
                right: 15px;
                width: calc(100% - 30px);
                max-width: 380px;
            }
        }

        .ai-icon-bubble {
            width: 60px;
            height: 60px;
            background: #022454;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .ai-icon-bubble i {
            font-size: 35px;
            color: white;
        }

        .alerta-whatsapp {
            background: #506de300 !important;
        }

        .alerta-whatsapp img {
            height: 50px;
            border-radius: 50%;
        }

        .paper {
            margin: 0px 25px 30px 25px;
            padding-top: 5%;
        }

        .paper:before {
            top: 0px;
            right: 0px;
            border-color: #f9fafd #f9f9f9 #eaedf7 #eaedf7;
        }

        .sidebar {
            background: {{ Auth::user()->username == 'desarrollo' ? '#022454' : (isset(Auth::user()->empresa()->color) ? Auth::user()->empresa()->color : '#022454') }};
        }

        .configuracion>div {
            border: 4px solid {{ Auth::user()->username == 'desarrollo' ? '#022454' : (isset(Auth::user()->empresa()->color) ? Auth::user()->empresa()->color : '#022454') }};
        }

        .configuracion h4 {
            color: #000;
        }

        .text-primary {
            color: {{ Auth::user()->username == 'desarrollo' ? '#022454' : (isset(Auth::user()->empresa()->color) ? Auth::user()->empresa()->color : '#022454') }};
        }

        .configuracion>div>a {
            color: {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }};
        }

        .form-radio label input+.input-helper:after {
            background: {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }};
        }

        .notice-info {
            border-color: {{ Auth::user()->username == 'desarrollo' ? '#022454' : (isset(Auth::user()->empresa()->color) ? Auth::user()->empresa()->color : '#022454') }};
        }

        .btn-link {
            color: {{ Auth::user()->username == 'desarrollo' ? '#022454' : (isset(Auth::user()->empresa()->color) ? Auth::user()->empresa()->color : '#022454') }};
        }

        .sidebar .nav .sub-menu .nav-item .nav-link:hover,
        #sidebar>ul>li>a:hover {
            color: #c7c7c7;
        }

        .nav-pills .nav-link.active,
        .nav-pills .show>.nav-link {
            color: #fff;
            background-color: {{ Auth::user()->username == 'desarrollo' ? '#022454' : (isset(Auth::user()->empresa()->color) ? Auth::user()->empresa()->color : '#022454') }};
        }

        .nav-tabs .nav-link.active,
        .nav-tabs .nav-item.show .nav-link {
            color: #fff;
            background-color: {{ Auth::user()->username == 'desarrollo' ? '#022454' : (isset(Auth::user()->empresa()->color) ? Auth::user()->empresa()->color : '#022454') }};
            border-color: #dee2e6 #dee2e6 #fff;
        }

        .card-notificacion {
            border-radius: 20px;
            background: #fff !important;
            border: solid 2px {{ Auth::user()->username == 'desarrollo' ? '#022454' : (isset(Auth::user()->empresa()->color) ? Auth::user()->empresa()->color : '#022454') }};
        }

        .card-notificacion:hover {
            border-radius: 20px;
            background: {{ Auth::user()->username == 'desarrollo' ? '#022454' : (isset(Auth::user()->empresa()->color) ? Auth::user()->empresa()->color : '#022454') }};
            border: solid 2px #fff;
        }

        .bg-th {
            background: {{ Auth::user()->username == 'desarrollo' ? '#022454' : (isset(Auth::user()->empresa()->color) ? Auth::user()->empresa()->color : '#022454') }};
            border-color: {{ Auth::user()->username == 'desarrollo' ? '#022454' : (isset(Auth::user()->empresa()->color) ? Auth::user()->empresa()->color : '#022454') }};
            color: #fff !important;
        }

        .table-bordered {
            border: 2px solid {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }} !important;
        }

        .table.table-bordered th {
            color: #fff;
            background-color: {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }};
            border-color: {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }};
        }

        .table .thead-dark th {
            color: #fff;
            background-color: {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }};
            border-color: {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }};
        }

        table.dataTable thead .sorting_asc:before,
        table.dataTable thead .sorting_desc:after {
            display: block !important;
            color: #ffffff;
        }

        .page-item.active .page-link {
            background-color: {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }};
            border-color: {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }};
        }

        .page-item.active .page-link {
            background-color: {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }};
            border-color: {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }};
        }

        .page-item.disabled .page-link {
            color: {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }};
            border-color: {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }};
        }

        .page-link {
            color: {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }};
            border: 1px solid {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }};
        }

        .card-counter.primary:hover,
        .card-counter.success:hover,
        .card-counter.danger:hover {
            background-color: #4f4f4f;
        }

        .page-link:hover {
            color: #ffffff;
            text-decoration: none;
            background-color: {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }};
            border-color: {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }};
        }

        .stretch-card {
            border: 1px solid #a6b6bd52 !important;
            border-radius: 3px;
        }

        .content-wrapper {
            background: #fff;
        }

        .card {
            background: #c2c2c21a !important;
        }

        .img-gafica {
            border: solid 1px {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }};
            border-radius: 10px;
        }

        .btn-system {
            color: #fff;
            background-color: {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }};
            border-color: {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }};
        }

        .btn-system:hover,
        .btn-system:active {
            color: #fff;
            background-color: #333;
            border-color: #333;
        }

        .min_max_70 {
            min-height: 70px;
            max-height: 145px;
        }

        #form-filter {
            padding-left: 1.5rem !important;
            padding-right: 1.5rem !important;
        }

        #form-filter>div,
        #form-filterG>div {
            border: solid 1px {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }} !important;
            padding: 2% 1%;
        }

        .whatsapp {
            position: fixed;
            right: 5px;
            /*Margen derecho*/
            bottom: 5px;
            /*Margen abajo*/
            z-index: 999;
            cursor: pointer;
        }


        .whatsapp img {
            width: 60px;
            /*Alto del icono*/
            height: 60px;
            /*Ancho del icono*/
        }

        .whatsapp:hover {
            opacity: 0.7 !important;
            filter: alpha(opacity=70) !important;
        }

        .select2-container--default .select2-selection--multiple {
            border: 1px solid #dee4e6;
            border-radius: 2px;
        }

        .Cerrada-emitida span {
            font-size: 0.8em;
            padding: 1%;
            font-weight: bold;
            color: #FFF;
            text-transform: uppercase;
            text-align: center;
            line-height: 20px;
            transform: rotate(-45deg);
            -webkit-transform: rotate(-45deg);
            width: 79%;
            display: block;
            background: #79A70A;
            background: linear-gradient(#00CE68 0%, #00CE68 100%);
            box-shadow: 0 3px 10px -5px rgba(0, 0, 0, 1);
            position: absolute;
            top: 19%;
            left: -36px;
        }

        .Cerrada-emitida span::before {
            content: "";
            position: absolute;
            left: 0px;
            top: 100%;
            z-index: -1;
            border-left: 3px solid #00CE68;
            border-right: 3px solid transparent;
            border-bottom: 3px solid transparent;
            border-top: 3px solid #00CE68;
        }

        .Cerrada-emitida span::after {
            content: "";
            position: absolute;
            right: 0px;
            top: 100%;
            z-index: -1;
            border-left: 3px solid transparent;
            border-right: 3px solid #00CE68;
            border-bottom: 3px solid transparent;
            border-top: 3px solid #00CE68;
        }

        .Abierta-no span,
        .Abierta-emitida span {
            font-size: 0.8em;
            padding: 1%;
            font-weight: bold;
            color: #FFF;
            text-transform: uppercase;
            text-align: center;
            line-height: 20px;
            transform: rotate(-45deg);
            -webkit-transform: rotate(-45deg);
            width: 79%;
            display: block;
            background: #e65251;
            background: linear-gradient(#e65251 0%, #e65251 100%);
            box-shadow: 0 3px 10px -5px rgba(0, 0, 0, 1);
            position: absolute;
            top: 19%;
            left: -36px;
        }

        .Abierta-no span::before,
        .Abierta-emitida span::before {
            content: "";
            position: absolute;
            left: 0px;
            top: 100%;
            z-index: -1;
            border-left: 3px solid #e65251;
            border-right: 3px solid transparent;
            border-bottom: 3px solid transparent;
            border-top: 3px solid #e65251;
        }

        .Abierta-no span::after,
        .Abierta-emitida span::after {
            content: "";
            position: absolute;
            right: 0px;
            top: 100%;
            z-index: -1;
            border-left: 3px solid transparent;
            border-right: 3px solid #e65251;
            border-bottom: 3px solid transparent;
            border-top: 3px solid #e65251;
        }

        .form-group label {
            font-weight: 500;
        }

        .btn-none,
        .btn-none: hover {
            background-color: transparent;
            border-color: transparent;
        }

        fieldset {
            border-width: 1px;
            border-style: double;
            border-color: {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }};
        }

        legend {
            width: auto;
            padding: 0% 2%;
            font-size: 1rem;
            color: #fff;
            background: {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }};
            border-radius: 5px;
            text-transform: uppercase;
        }

        div.dataTables_wrapper div.dataTables_length select {
            width: 60px;
        }

        .border,
        .loader-demo-box {
            border: 1px solid #dee4e6 !important;
        }

        .gj-picker-bootstrap [role=header] {
            background: {{ Auth::user()->rol > 1 ? Auth::user()->empresa()->color : '' }};
            color: #AAA;
        }

        .gj-picker-bootstrap {
            border: 0;
            border-radius: 20px;
        }

        #tabla-facturas_wrapper .dt-buttons,
        #tabla-contratos_wrapper .dt-buttons,
        #tabla-planes_wrapper .dt-buttons,
        #tabla-mikrotiks_wrapper .dt-buttons,
        #tabla-nodos_wrapper .dt-buttons,
        #tabla-aps_wrapper .dt-buttons,
        #tabla-grupos_wrapper .dt-buttons,
        #tabla-bancos_wrapper .dt-buttons,
        #tabla-wifis_wrapper .dt-buttons,
        #table_sin_gestionar_wrapper .dt-buttons,
        #table_sin_gestionarG_wrapper .dt-buttons,
        #tabla-ventas-externas_wrapper .dt-buttons {
            float: right !important;
        }

        #tabla-contratos_length,
        #tabla-planes_length,
        #tabla-mikrotiks_length,
        #tabla-nodos_length,
        #tabla-aps_length,
        #tabla-grupos_length,
        #tabla-bancos_length,
        #tabla-wifis_length,
        #table_sin_gestionar_length,
        #table_sin_gestionarG_length,
        #tabla-ventas-externas_length {
            margin: 1% 0 !important;
        }

        #tabla-facturas_wrapper .dt-buttons button,
        #tabla-contratos_wrapper .dt-buttons button,
        #tabla-planes_wrapper .dt-buttons button,
        #tabla-mikrotiks_wrapper .dt-buttons button,
        #tabla-nodos_wrapper .dt-buttons button,
        #tabla-aps_wrapper .dt-buttons button,
        #tabla-grupos_wrapper .dt-buttons button,
        #tabla-bancos_wrapper .dt-buttons button,
        #tabla-wifis_wrapper .dt-buttons button,
        #table_sin_gestionar_wrapper .dt-buttons button,
        #table_sin_gestionarG_wrapper .dt-buttons button,
        #tabla-ventas-externas_wrapper .dt-buttons button {
            color: #fff !important;
            background-color: #00ce68 !important;
            border-color: #00ce68 !important;
        }

        #tabla-facturas_wrapper .dt-buttons button:hover,
        #tabla-contratos_wrapper .dt-buttons button:hover,
        #tabla-planes_wrapper .dt-buttons button:hover,
        #tabla-mikrotiks_wrapper .dt-buttons button:hover,
        #tabla-nodos_wrapper .dt-buttons button:hover,
        #tabla-aps_wrapper .dt-buttons button:hover,
        #tabla-grupos_wrapper .dt-buttons button:hover,
        #tabla-bancos_wrapper .dt-buttons button:hover,
        #tabla-wifis_wrapper .dt-buttons button:hover,
        #table_sin_gestionar_wrapper .dt-buttons button:hover,
        #table_sin_gestionarG_wrapper .dt-buttons button:hover,
        #tabla-ventas-externas_wrapper .dt-buttons button:hover {
            color: #fff !important;
            background-color: #218838 !important;
            border-color: #1e7e34 !important;
        }

        #tabla-facturas_wrapper .dt-buttons button:nth-child(2),
        #tabla-contratos_wrapper .dt-buttons button:nth-child(2),
        #tabla-planes_wrapper .dt-buttons button:nth-child(2),
        #tabla-mikrotiks_wrapper .dt-buttons button:nth-child(2),
        #tabla-nodos_wrapper .dt-buttons button:nth-child(2),
        #tabla-aps_wrapper .dt-buttons button:nth-child(2),
        #tabla-grupos_wrapper .dt-buttons button:nth-child(2),
        #tabla-bancos_wrapper .dt-buttons button:nth-child(2),
        #tabla-wifis_wrapper .dt-buttons button:nth-child(2),
        #table_sin_gestionar_wrapper .dt-buttons button:nth-child(2),
        #table_sin_gestionarG_wrapper .dt-buttons button:nth-child(2),
        #tabla-ventas-externas_wrapper .dt-buttons button:nth-child(2) {
            color: #fff !important;
            background-color: #e65251 !important;
            border-color: #e65251 !important;
        }

        #tabla-facturas_wrapper .dt-buttons button:nth-child(2),
        #tabla-contratos_wrapper .dt-buttons button:nth-child(2):hover,
        #tabla-planes_wrapper .dt-buttons button:nth-child(2):hover,
        #tabla-mikrotiks_wrapper .dt-buttons button:nth-child(2):hover,
        #tabla-nodos_wrapper .dt-buttons button:nth-child(2):hover,
        #tabla-aps_wrapper .dt-buttons button:nth-child(2):hover,
        #tabla-grupos_wrapper .dt-buttons button:nth-child(2):hover,
        #tabla-bancos_wrapper .dt-buttons button:nth-child(2):hover,
        #tabla-wifis_wrapper .dt-buttons button:nth-child(2):hover,
        #table_sin_gestionar_wrapper .dt-buttons button:nth-child(2):hover,
        #table_sin_gestionarG_wrapper .dt-buttons button:nth-child(2):hover,
        #tabla-ventas-externas_wrapper .dt-buttons button:nth-child(2):hover {
            color: #fff !important;
            background-color: #c82333 !important;
            border-color: #bd2130 !important;
        }

        div.dataTables_wrapper div.dataTables_paginate {
            text-align: -webkit-center !important;
        }

        /* Toggle Switch Styles */
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
        }

        input:checked + .slider {
            background-color: #28a745;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        .slider.round {
            border-radius: 24px;
        }

        .slider.round:before {
            border-radius: 50%;
        }

        /* Modern Radio Button Styles */
        .custom-radio {
            display: inline-flex;
            align-items: center;
            cursor: pointer;
            margin-right: 15px;
        }

        .custom-radio input[type="radio"] {
            display: none;
        }

        .custom-radio .radio-btn {
            width: 20px;
            height: 20px;
            border: 2px solid #ccc;
            border-radius: 50%;
            margin-right: 8px;
            position: relative;
            transition: all 0.3s ease;
        }

        .custom-radio input[type="radio"]:checked + .radio-btn {
            border-color: #007bff;
        }

        .custom-radio input[type="radio"]:checked + .radio-btn::after {
            content: '';
            width: 10px;
            height: 10px;
            background-color: #007bff;
            border-radius: 50%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .custom-radio .radio-label {
            font-weight: 500;
            color: #333;
        }
    </style>
    @yield('style')
</head>

<body>
    @if (Auth::user()->online === 0)
        @php
            Auth::logout();
            return Redirect::to('login');
        @endphp
    @endif

    <div id="contenedor_carga">
        <img id="carga" src="{{ asset('images/gif-tuerca.gif') }}" onerror="this.style.display='none'; document.getElementById('contenedor_carga').style.display='none';" onload="setTimeout(function(){ var contenedor = document.getElementById('contenedor_carga'); if(contenedor) { contenedor.style.visibility = 'hidden'; contenedor.style.opacity = '0'; } }, 100);">
    </div>
    <div class="loader"></div>
    <div class="container-scroller">
        <!-- partial:partials/_navbar.html -->
        @include('layouts.includes.navbar')
        <!-- partial -->
        <div class="container-fluid page-body-wrapper">
            <!-- partial:partials/_sidebar.html -->
            <nav class="sidebar sidebar-offcanvas" id="sidebar">
                <ul class="nav">
                    <li class="nav-item nav-profile">
                        <div class="nav-link" style="padding: 6% !important">
                            <div class="user-wrapper">
                                <div class="profile-image">
                                    @if (Auth::user()->image)
                                        <img src="{{ asset('images/Empresas/Empresa' . Auth::user()->empresa . '/usuarios/' . Auth::user()->image) }}"
                                            onerror="this.src='{{ asset('images/no-user-image.png') }}'"
                                            alt="profile image">
                                    @else
                                        <img src="{{ asset('images/no-user-image.png') }}" alt="profile image">
                                    @endif
                                </div>
                                <div class="text-wrapper">
                                    <p style="text-transform:capitalize;" class="profile-name">
                                        {{ Auth::user()->nombres }}</p>
                                    @if (Auth::user()->empresa())
                                        <input type="hidden" value="{{ Auth::user()->empresa()->precision }}"
                                            id="precision">
                                        <input type="hidden" value="{{ Auth::user()->empresa()->sep_dec }}"
                                            id="sep_dec">
                                        <input type="hidden"
                                            value="{{ Auth::user()->empresa()->sep_dec == '.' ? ',' : '.' }}"
                                            id="sep_miles">
                                    @endif
                                    <div>
                                        <small class="designation">{{ Auth::user()->roles->rol }}</small>
                                        <span class="status-indicator online"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                    <li class="nav-item" id="">
                        @if (Auth::user()->rol == 4)
                            <a class="nav-link" href="{{ route('tecnico.dashboard') }}">
                            @else
                                <a class="nav-link" href="{{ route('home') }}">
                        @endif
                        <i class="menu-icon fas fa-home"></i>
                        <span class="menu-title">Inicio</span>
                        </a>

                    </li>
                    @include('layouts.includes.menu')
                </ul>
            </nav>
            <!-- partial -->
            <div class="main-panel body-oscuro" @if (isset($requestBack)) style="width:100%" @endif>
                <div class="content-wrapper body-oscuro2"">
                    <div class="grid-margin stretch-card">
                        <div class="card top-radius body-oscuro">
                            <div class="body-card body-oscuro">
                                <div class="row xp7jhwk" style="padding: 2%;">
                                    @php
                                        $col_md1 = 7;
                                        $col_md2 = 5;
                                        if (isset($invert)) {
                                            $col_md1 = 4;
                                            $col_md2 = 8;
                                        }
                                        if (isset($middel)) {
                                            $col_md1 = 6;
                                            $col_md2 = 6;
                                        }
                                        if (isset($precice)) {
                                            $col_md1 = 5;
                                            $col_md2 = 7;
                                        }
                                        if (isset($minus_dere)) {
                                            $col_md1 = 2;
                                            $col_md2 = 10;
                                        }

                                    @endphp
                                    <div class="col-md-{{ $col_md1 }} p-md-0 p-4">
                                        <h3 id="titulo" class="body-oscuro"><i class="{{ $icon }}"></i>
                                            {{ isset($title_sub) ? $title_sub : $title }}</h3>
                                    </div>
                                    @if (isset($subtitleR))
                                        <h3 style="text-align: right" class="w-100">
                                            {{ $subtitleR ? $subtitleR : '' }}</h3>
                                    @endif
                                    @if (isset($cancelEditCot))
                                        <a href="{{ $cancelEditCot }}"
                                            style="position:absolute; right:3px; top:3px; padding:5px">
                                            <h3 style="text-align: right" class="w-100"
                                                style="color:red; font-size:33px;">X</h3>
                                        </a>
                                    @endif
                                    <div class="col-md-{{ $col_md2 }}" style="text-align: right;">
                                        @yield('boton')
                                    </div>
                                </div>

                                <!-- Funcion para generar el imprimir -->
                                @if (Session::has('print'))
                                    @if (Session::get('print'))
                                        <input type="hidden" id="imprimir"
                                            value="{{ route('facturas.imprimir', Session::get('print')) }}">
                                    @endif
                                @endif

                                @if (Session::has('print_pos'))
                                    @if (Session::get('print_pos'))
                                        <input type="hidden" id="imprimir"
                                            value="{{ route('facturas.tirilla', ['id' => Session::get('print_pos'), 'name' => "Factura No. Session::get('print_pos')"]) }}">
                                    @endif
                                @endif


                                @if (Session::has('cannot-access-module'))
                                    <div class="alert alert-danger">
                                        {{ Session::get('cannot-access-module') }}
                                    </div>

                                    <script type="text/javascript">
                                        setTimeout(function() {
                                            $('.alert').hide();
                                            $('.active_table').attr('class', ' ');
                                        }, 5000);
                                    </script>
                                @endif

                                <main id="app">
                                    @yield('content')
                                </main>
                            </div>
                        </div>
                    </div>


                </div>
                <!-- Modal Small-->
                <div class="modal fade" id="modal-small" tabindex="-1" role="dialog"
                    aria-labelledby="modal-small-CenterTitle" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content" id="modal-small-div">
                            <div class="modal-header">
                                <h5 class="modal-title" id="exampleModalLongTitle">Modal title</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                ...
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                                <button type="button" class="btn btn-primary">Save changes</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal -->
                <div class="modal fade" id="exampleModal" tabindex="-1" role="dialog"
                    aria-labelledby="exampleModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="exampleModalLabel">Modal title</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                ...
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                                <button type="button" class="btn btn-primary">Save changes</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- NOTIFICACIONES -->
                <input type="hidden" name="nro_notificacionesP" id="nro_notificacionesP" value="0">
                <audio id="play_notificacion" preload="auto" tabindex="0" controls="" class="d-none">
                    <source src="{{ asset('images/alerta.mp3') }}">
                </audio>
                <div class="modal fade" id="modalNotificacionP" role="dialog">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header p-0">
                                <center><img src="{{ contabo_url(env('LOGOS_FOLDER', 'logos'), 'logo.png') }}" style="width:15%"
                                        class="m-2"></center>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"
                                    style="margin: -10px;">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body" id="modal-bodyP">

                            </div>
                        </div>
                    </div>
                </div>

                <!-- NOTIFICACIONES -->
                <input type="hidden" name="nro_notificacionesW" id="nro_notificacionesW" value="0">
                <audio id="play_notificacion" preload="auto" tabindex="0" controls="" class="d-none">
                    <source src="{{ asset('images/alerta.mp3') }}">
                </audio>
                <div class="modal fade" id="modalNotificacionW" role="dialog">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header p-0">
                                <center><img src="{{ contabo_url(env('LOGOS_FOLDER', 'logos'), 'logo.png') }}" style="width:15%"
                                        class="m-2"></center>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"
                                    style="margin: -10px;">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body" id="modal-bodyW">

                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Suscripción Vencida -->
                @if (Auth::user()->empresa() && Auth::user()->empresa()->activo_mensaje == 1)
                    <div class="modal fade" id="modalSuscripcion" tabindex="-1" role="dialog"
                        aria-labelledby="modalSuscripcionLabel" aria-hidden="true" data-backdrop="static"
                        data-keyboard="false">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header bg-danger text-white">
                                    <h4 class="modal-title text-uppercase">Integra Colombia: Suscripción Vencida</h4>
                                    {{-- <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                         <span aria-hidden="true">&times;</span>
                                     </button> --}}
                                </div>
                                <div class="modal-body">
                                    <p>Tu suscripción ha vencido. Para mantener el acceso a nuestros servicios,
                                        recuerda realizar tu pago a la brevedad.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- NOTIFICACIONES -->
                <input type="hidden" name="nro_notificacionesR" id="nro_notificacionesR" value="0">
                <audio id="play_notificacion" preload="auto" tabindex="0" controls="" class="d-none">
                    <source src="{{ asset('images/alerta.mp3') }}">
                </audio>
                <div class="modal fade" id="modalNotificacionR" role="dialog">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header p-0">
                                <center><img src="{{ contabo_url(env('LOGOS_FOLDER', 'logos'), 'logo.png') }}" style="width:15%"
                                        class="m-2"></center>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"
                                    style="margin: -10px;">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body" id="modal-bodyR">

                            </div>
                        </div>
                    </div>
                </div>

                <!-- NOTIFICACIONES -->
                <input type="hidden" name="nro_notificacionesT" id="nro_notificacionesT" value="0">
                <audio id="play_notificacion" preload="auto" tabindex="0" controls="" class="d-none">
                    <source src="{{ asset('images/alerta.mp3') }}">
                </audio>
                <div class="modal fade" id="modalNotificacionT" role="dialog">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header p-0">
                                <center><img src="{{ contabo_url(env('LOGOS_FOLDER', 'logos'), 'logo.png') }}" style="width:15%"
                                        class="m-2"></center>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"
                                    style="margin: -10px;">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body" id="modal-bodyT">

                            </div>
                        </div>
                    </div>
                </div>

                <!-- NOTIFICACIONES -->
                <input type="hidden" name="nro_notificaciones" id="nro_notificaciones" value="0">
                <audio id="play_notificacion" preload="auto" tabindex="0" controls="" class="d-none">
                    <source src="{{ asset('images/alerta.mp3') }}">
                </audio>
                <div class="modal fade" id="modalNotificacion" role="dialog">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header p-0">
                                <center><img src="{{ contabo_url(env('LOGOS_FOLDER', 'logos'), 'logo.png') }}" style="width:15%"
                                        class="m-2"></center>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"
                                    style="margin: -10px;">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body" id="modal-bodyc">

                            </div>
                        </div>
                    </div>
                </div>

                <!-- content-wrapper ends -->
                <!-- partial:partials/_footer.html -->
                <footer class="footer">
                    <div class="container-fluid clearfix">
                        <span class="text-muted d-block text-center text-sm-left d-sm-inline-block">Todos los
                            derechos reservados {{ config('app.name') }}.
                            <a href="http://www.bootstrapdash.com/" target="_blank"></a></span>
                        <span class="float-none float-sm-right d-block mt-1 mt-sm-0 text-center">Realizado por:
                            Integra.

                            {{-- <i class="mdi mdi-heart text-danger"></i> --}}
                        </span>
                    </div>
                </footer>
                <!-- partial -->
            </div>
            <!-- Modal de bienvenida -->
            <div id="chat-ia-welcome-modal" class="chat-ia-modal">
                <div class="chat-ia-modal-content">
                    <span class="chat-ia-modal-close">&times;</span>
                    <div class="chat-ia-welcome-emoji">👋</div>
                    <h3 class="chat-ia-welcome-title">Hola. soy el asistente de IA,<br>puedes preguntarme
                        cualquier duda sobre <br>Integra.</h3>
                    <button class="chat-ia-btn-primary" id="chat-ia-start-btn">Chatear ahora</button>
                    <button class="chat-ia-btn-secondary" id="chat-ia-dismiss-btn">No requiero asesoría</button>
                </div>
            </div>

            <!-- Widget de chat -->
            <div id="chat-ia-widget" class="chat-ia-widget hidden">
                <div class="chat-ia-header">
                    <span>Soporte IA</span>
                    <button id="chat-ia-close-widget">&times;</button>
                </div>
                <div class="chat-ia-messages" id="chat-ia-messages">
                    <div class="chat-ia-bubble bot">¡Hola! ¿En qué puedo ayudarte hoy?</div>
                </div>
                <div class="chat-ia-input-container">
                    <input type="text" id="chat-ia-input" placeholder="Escribe tu mensaje..." />
                    <button id="chat-ia-send-btn"><i class="fas fa-paper-plane"></i></button>
                </div>
            </div>

            <!-- Botón flotante (tu actual) -->
            <div class="whatsapp" id="chat-ia-float-btn">
                <div class="ai-icon-bubble">
                    <i class="fas fa-robot"></i>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Función para ocultar el loader
        function ocultarLoader() {
            var contenedor = document.getElementById('contenedor_carga');
            if (contenedor) {
                contenedor.style.visibility = 'hidden';
                contenedor.style.opacity = '0';
                contenedor.style.display = 'none';
            }
        }

        // Ocultar cuando el DOM esté listo
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', ocultarLoader);
        } else {
            ocultarLoader();
        }

        // Ocultar cuando la ventana termine de cargar
        window.addEventListener('load', ocultarLoader);

        // Timeout de seguridad: ocultar después de 3 segundos máximo
        setTimeout(ocultarLoader, 3000);
    </script>
    <!-- container-scroller -->

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script>

    <!-- plugins:js -->
    <script src="{{ asset('vendors/js/vendor.bundle.base.js') }}"></script>
    {{-- <script src="{{asset('vendors/js/vendor.bundle.addons.js')}}"></script> --}}
    <!-- endinject -->
    <!-- Plugin js for this page-->
    <!-- End plugin js for this page-->
    <!-- inject:js -->
    <script src="{{ asset('js/off-canvas.js') }}"></script>
    <script src="{{ asset('js/misc.js') }}"></script>
    <script src="{{ asset('js/dashboard.js') }}"></script>
    <script src="{{ asset('js/chart.js') }}"></script>
    <script type="text/javascript" src="{{ asset('vendors/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('js/CollapsibleLists.js') }}"></script>
    <script type="text/javascript" src="{{ asset('vendors/bootstrap-selectpicker/js/bootstrap-select.min.js') }}"></script>
    <script src="{{ asset('vendors/bootstrap-datepicker/js/gijgo.min.js') }}"></script>
    <script src="{{ asset('vendors/bootstrap-datepicker/js/messages/messages.es-es.min.js') }}"></script>
    <!-- Custom js for this page-->
    <script type="text/javascript" src="{{ asset('vendors/validation/jquery.validate.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('vendors/validation/localization/messages_es.js') }}"></script>
    <script type="text/javascript" src="{{ asset('js/jquery.mask.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('vendors/sweetalert2/sweetalert2.min.js') }}"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/raphael/2.1.0/raphael-min.js"></script>
    <script type="text/javascript" src="{{ asset('vendors/morris/morris.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('vendors/sortable/jquery.sortable.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('vendors/autocomplete/jquery.auto-complete.js') }}"></script>
    <script type="text/javascript" src="{{ asset('vendors/profile-picture/profile-picture.js') }}"></script>
    <script type="text/javascript" src="{{ asset('vendors/dropify/dropify.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
    <script src="{{ asset('js/paginicio/planes.js') }}"></script>
    <!-- Dropzone Plugin Js -->
    <script src="{{ asset('vendors/dropzone/dropzone.js') }}"></script>
    <!-- Light Gallery Plugin Js -->
    <script src="{{ asset('vendors/light-gallery/js/lightgallery-all.js') }}"></script>
    <!-- endinject -->
    <script src="{{ asset('js/moment.js') }}"></script>
    <script src="{{ asset('js/function.js') }}?v={{ file_exists(public_path('js/function.js')) ? filemtime(public_path('js/function.js')) : '1' }}">
    </script>
    <script src="{{ asset('js/custom.js') }}?v={{ file_exists(public_path('js/custom.js')) ? filemtime(public_path('js/custom.js')) : '1' }}">
    </script>
    <script src="{{ asset('js/dian.js') }}?v={{ file_exists(public_path('js/dian.js')) ? filemtime(public_path('js/dian.js')) : '1' }}">
    </script>
    <!--<script type="text/javascript" src='https://maps.google.com/maps/api/js?sensor=false&libraries=places'></script>-->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBL1KlgUU3ml--hP_mhfOeCNkp1EJ-WAcs"></script>
    <script type="text/javascript" src="{{ asset('js/locationpicker.jquery.js') }}"></script>

    <script src="//cdn.datatables.net/plug-ins/1.12.1/sorting/ip-address.js"></script>

    <script src="https://cdn.socket.io/4.3.1/socket.io.min.js"></script>
    <script src="{{ asset('gritter/js/jquery.gritter.min.js') }}"></script>
    <div class="d-none" id="audioContainer"></div>
    <script type="text/javascript">
        const audioElement = new Audio('{{ asset('images/alerta.mp3') }}');
        const audioElementA = new Audio('{{ asset('images/asig.mp3') }}');
        var _token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        @php
            use Illuminate\Support\Facades\DB;
            use Illuminate\Support\Facades\Auth;
            $instancia = DB::table('instancia')->first();

        @endphp



        function alertawhat(data) {
            $.gritter.add({
                title: data.nombre,
                text: data.mensaje,
                image: '<img src="' + data.avatar + '" >',
                sticky: false,
                time: 7000,
                class_name: 'alerta-whatsapp'
            });
        }

        $(document).ready(function() {

            $("#{{ $seccion }}").addClass("active");
            if ($("#{{ $seccion }}").find('.sub-menu').length) {
                $("#{{ $seccion }}").find('.collapse').addClass('show');
            }
            @if (isset($subseccion))
                $("#{{ $subseccion }}").addClass("active");
            @endif

            // Muestra la alerta solo si la suscripción ha caducado (excepto para usuarios rol 1)
            @if (Auth::check() &&
                    Auth::user()->rol != 1 &&
                    Auth::user()->empresaObj &&
                    isset(Auth::user()->empresaObj->is_subscription_active) &&
                    !Auth::user()->empresaObj->is_subscription_active)
                Swal.fire({
                    title: 'Suscripción Expirada',
                    html: 'Su suscripción ha expirado. Por favor, pague su mensualidad para continuar.',
                    type: 'warning',
                    showConfirmButton: true,
                    confirmButtonText: 'Volver al Login',
                    confirmButtonColor: '#3085d6',
                    showCancelButton: false,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    allowEnterKey: false,
                }).then(function(result) {
                    if (result.value) {
                        // Hacer logout y redirigir al login
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '{{ route("logout") }}';

                        const csrfToken = document.createElement('input');
                        csrfToken.type = 'hidden';
                        csrfToken.name = '_token';
                        csrfToken.value = '{{ csrf_token() }}';

                        form.appendChild(csrfToken);
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            @endif
        });
    </script>

    <!-- End custom js for this page-->
    <script src="{{ asset('vendors/documentacion/index.all.min.js') }}"></script>
    <script src="{{ asset('vendors/documentacion/popper.min.js') }}"></script>

    <script type="text/javascript" src="{{ asset('js/paginicio/floating-wpp.min.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('css/floating-wpp.min.css') }}">
    <script src="{{ asset('vendors/ckeditor/ckeditor.js') }}"></script>
    <script>
        tippy('.icono', {
            content: 'global content',
            animation: 'perspective',
            arrow: true,
            arrowType: 'sharp',
            interactive: true,
        })
    </script>

    <script type="text/javascript">
        $(document).on("mouseup", function(e) {
            if ($("#sidebar").hasClass('active')) {
                var container = $("#sidebar");
                if (!container.is(e.target) && container.has(e.target).length === 0) {
                    container.removeClass('active');
                }
            }
        });
    </script>
    @if (\Illuminate\Support\Facades\Auth::user()->rol == 4)
        <script>
            function errorCallback(error) {
                console.log("Error al obtener la ubicación: ", error);
            }

            function sendPosition(position) {
                const lat = position.coords.latitude;
                const lon = position.coords.longitude;

                console.log({
                    latitude: lat,
                    longitude: lon,
                })

                // Enviar la posición al servidor con AJAX
                $.ajax({
                    url: '{{ route('tecnico.saveLocation') }}', // Ruta en Laravel que maneja la localización
                    type: 'POST',
                    data: {
                        latitude: lat,
                        longitude: lon,
                        _token: '{{ csrf_token() }}' // Incluye el token CSRF para la validación
                    },
                    error: function(error) {
                        console.log("Error al guardar la localización:", error);
                    }
                });
            }

            $(document).ready(function() {
                if (navigator.geolocation) {
                    navigator.geolocation.watchPosition(sendPosition, errorCallback, {
                        enableHighAccuracy: false,
                        maximumAge: 0,
                        timeout: 30000
                    });
                } else {
                    console.log("Geolocalización no es soportada por este navegador.");
                }
            });
        </script>
    @endif
    @yield('scripts')
    <!-- Test Chat-IA webhook -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const floatBtn = document.getElementById('chat-ia-float-btn');
            const welcomeModal = document.getElementById('chat-ia-welcome-modal');
            const chatWidget = document.getElementById('chat-ia-widget');
            const startBtn = document.getElementById('chat-ia-start-btn');
            const dismissBtn = document.getElementById('chat-ia-dismiss-btn');
            const closeModal = document.querySelector('.chat-ia-modal-close');
            const closeWidget = document.getElementById('chat-ia-close-widget');
            const sendBtn = document.getElementById('chat-ia-send-btn');
            const input = document.getElementById('chat-ia-input');
            const messages = document.getElementById('chat-ia-messages');
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            const chatId = '{{ auth()->check() ? 'user-' . auth()->id() : 'guest-' . session()->getId() }}';

            // Abrir modal de bienvenida al hacer clic en el botón flotante
            floatBtn.addEventListener('click', function() {
                // Si el widget ya está abierto, solo lo cerramos
                if (!chatWidget.classList.contains('hidden')) {
                    chatWidget.classList.add('hidden');
                    return;
                }

                // Si el widget está cerrado y el usuario aún no aceptó, mostramos modal
                // Puedes guardar un flag en memoria para no mostrar el modal siempre
                const alreadyAccepted = window.localStorage.getItem('chatIaAccepted') === '1';

                if (alreadyAccepted) {
                    chatWidget.classList.remove('hidden');
                    input.focus();
                } else {
                    welcomeModal.style.display = 'block';
                }
            });

            // Cerrar modal
            closeModal.addEventListener('click', function() {
                welcomeModal.style.display = 'none';
            });

            dismissBtn.addEventListener('click', function() {
                welcomeModal.style.display = 'none';
            });

            // Iniciar chat
            startBtn.addEventListener('click', function() {
                welcomeModal.style.display = 'none';
                chatWidget.classList.remove('hidden');
                window.localStorage.setItem('chatIaAccepted', '1');
                input.focus();
            });


            // Cerrar widget
            closeWidget.addEventListener('click', function() {
                chatWidget.classList.add('hidden');
            });

            // Agregar mensaje a la UI
            function addMessage(text, type) {
                const bubble = document.createElement('div');
                bubble.className = 'chat-ia-bubble ' + type;
                bubble.textContent = text;
                messages.appendChild(bubble);
                messages.scrollTop = messages.scrollHeight;
                return bubble;
            }

            // Enviar mensaje
            async function sendMessage() {
                const text = input.value.trim();
                const sessionId =
                    '{{ auth()->check() ? 'user-' . auth()->id() : 'guest-' . session()->getId() }}';
                if (!text) return;

                addMessage(text, 'user');
                input.value = '';

                const thinkingBubble = addMessage('Escribiendo...', 'bot');

                try {
                    const response = await fetch('{{ route('chatia.chatIaWebhook') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token
                        },
                        body: JSON.stringify({
                            content: text,
                            session_id: sessionId
                        })
                    });

                    const data = await response.json();
                    messages.removeChild(thinkingBubble);

                    if (data.ok) {
                        const reply = data.reply || 'Respuesta recibida';
                        addMessage(reply, 'bot');
                    } else {
                        addMessage('❌' + (data.error || 'Error al procesar la solicitud'), 'bot');
                    }
                } catch (error) {
                    messages.removeChild(thinkingBubble);
                    addMessage('❌ Error de conexión', 'bot');
                    console.error('Error:', error);
                }
            }

            sendBtn.addEventListener('click', sendMessage);
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') sendMessage();
            });

            // Cerrar modal al hacer clic fuera
            window.addEventListener('click', function(e) {
                if (e.target === welcomeModal) {
                    welcomeModal.style.display = 'none';
                }
            });
        });
    </script>


    <script>
        // Check subscription status
        function checkSubscriptionStatus() {
            $.ajax({
                url: '{{ route('suscripciones.validate') }}',
                method: 'GET',
                success: function(response) {
                    console.log(response);
                    if (response.showModal) {
                        $('#modalSuscripcion').modal('show');
                    }
                }
            });
        }

        // Check on page load
        $(document).ready(function() {
            checkSubscriptionStatus();
        });
    </script>

    <!-- Script de respaldo para asistencias - Solo se ejecuta si el script del navbar no se ejecutó -->
    <script>
        // Esperar a que el DOM esté completamente cargado
        $(document).ready(function() {
            console.log('Layout principal cargado - verificando asistencias...');

            // Dar tiempo a que se ejecute el script del navbar
            setTimeout(function() {
                // Solo ejecutar si no se inicializó desde el navbar
                if (typeof window.asistenciaInicializada === 'undefined' || !window
                    .asistenciaInicializada) {
                    console.log(
                        'Sistema de asistencias no inicializado, ejecutando desde layout principal...');

                    // Solo ejecutar si existe el botón de asistencia
                    if ($('#btn-asistencia').length > 0) {
                        console.log('Botón de asistencia encontrado, inicializando...');

                        if (window.location.pathname.split("/")[1] === "software") {
                            var url = '/software/empresa/asistencias/estado-actual';
                        } else {
                            var url = '/empresa/asistencias/estado-actual';
                        }
                        // Verificar estado de asistencia
                        function verificarEstadoAsistenciaBackup() {
                            $.ajax({
                                url: url,
                                method: 'GET',
                                dataType: 'json',
                                timeout: 10000,
                                success: function(response) {
                                    console.log('Estado asistencia (backup):', response);

                                    const texto = $('#texto-asistencia');
                                    const icono = $('#icono-asistencia');
                                    const boton = $('#btn-asistencia');

                                    if (icono.length === 0) return;

                                    if (response.ultimo_registro) {
                                        const estado = response.ultimo_registro.tipo;

                                        if (estado === 'ingreso') {
                                            icono.css('color', '#28a745');
                                            texto
                                                .hide(); // Ocultar texto cuando está en el trabajo
                                            boton.attr('title',
                                                'En el trabajo - Último ingreso: ' +
                                                response.ultimo_registro.hora);
                                            boton.removeClass('btn-pulse');
                                        } else {
                                            icono.css('color', '#ffc107');
                                            texto.show().css('color', '#ffc107').text(
                                                'Marcar Ingreso');
                                            boton.attr('title',
                                                'Fuera del trabajo - Última salida: ' +
                                                response.ultimo_registro.hora);
                                            boton.removeClass('btn-pulse');
                                        }
                                    } else {
                                        icono.css('color', '#6c757d');
                                        texto.show().css('color', '#6c757d').text(
                                            'Marcar Ingreso');
                                        boton.attr('title',
                                            'Sin registros hoy - Haz clic para marcar ingreso'
                                        );
                                        boton.addClass('btn-pulse');
                                    }

                                    console.log('Estado actualizado (backup)');

                                    // Marcar como inicializado
                                    window.asistenciaInicializada = true;
                                    window.actualizarEstadoAsistencia =
                                        verificarEstadoAsistenciaBackup;
                                },
                                error: function(xhr, status, error) {
                                    console.log('Error en backup de asistencias:', status,
                                        error);
                                }
                            });
                        }

                        // Ejecutar verificación
                        verificarEstadoAsistenciaBackup();

                        // Configurar tooltip
                        $('#btn-asistencia').tooltip({
                            placement: 'bottom',
                            trigger: 'hover'
                        });
                    }
                } else {
                    console.log('Sistema de asistencias ya inicializado desde navbar');
                }

                // Forzar actualización si existe la función global
                if (typeof window.actualizarEstadoAsistencia === 'function') {
                    console.log('Actualizando estado desde función global...');
                    window.actualizarEstadoAsistencia();
                }
            }, 1500); // Esperar 1.5 segundos antes de ejecutar el respaldo
        });
    </script>
</body>

</html>
