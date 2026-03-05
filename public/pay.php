<?php
include "include/conexion.php";
?>
<!DOCTYPE html>
<html lang="es-CO">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Meta OpenGraph -->
    <meta property="og:site_name" content="" />
    <meta property="og:site" content="" />
    <meta property="og:title" content="" />
    <meta property="og:description" content="" />
    <meta property="og:image" content="/assets/images/logo.png" />
    <meta property="og:url" content="" />
    <meta name="twitter:card" content="summary_large_image">

    <title><?=utf8_encode($empresa['nombre']);?> | Pagos en Línea</title>

    <!-- Fuentes y CSS base -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="./css/bootstrap.min.css" rel="stylesheet">
    <link href="./css/fontawesome-all.min.css" rel="stylesheet">
    <link href="./software/vendors/sweetalert2/sweetalert2.min.css" rel="stylesheet" />
    <link href="./css/aos.min.css" rel="stylesheet">
    <link href="./css/swiper.css" rel="stylesheet">
    <!-- <link href="./css/style.css" rel="stylesheet"> -->

    <link rel="icon" href="../software/images/Empresas/Empresa1/favicon.png">

    <style>
        :root {
            --primary-blue: #193388;
            --primary-blue-dark: #12245F;
            --primary-blue-light: #4A63A6;
        
            --accent-cyan: #00A7B5;
            --accent-cyan-light: #C4D82E;
            --accent-lime: #D4E157;
        
            --navy-secondary: #2E4178;
        
            --background-light: #F0F8F9;
            --background-white: #FFFFFF;
        
            --text-dark: #1A1A1A;
            --text-gray: #6B7280;
        
            --border-silver: #E9ECEF;
        }
        /* ======= RESET BÁSICO / LAYOUT ======= */
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #FFF8F3 0%, #FFFFFF 50%, #FFE8DD 100%);
            background-attachment: fixed;
            color: #1f2933;
            min-height: 100vh;
        }

        .navbar {
            background: rgba(87, 212, 255, 0.1);
            box-shadow: 0 2px 8px rgba(0, 167, 181, 0.05);
        }

        .navbar .nav-link {
            font-weight: 500;
            color: #1a1a1a !important;
        }

        .navbar .nav-link:hover {
            color: var(--rivertel-cyan) !important;
        }

        .navbar-brand img {
            max-height: 80px;
        }

        /* ======= HEADER ======= */
        .ex-header {
            padding: 140px 0 60px;
            background: linear-gradient(135deg, #122761 0%, #193689 50%, #4A6FD1 100%);
            color: #ffffff;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .ex-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: repeating-linear-gradient(45deg,
                    transparent,
                    transparent 10px,
                    rgba(255, 255, 255, 0.03) 10px,
                    rgba(255, 255, 255, 0.03) 20px);
        }

        .ex-header h1 {
            font-weight: 700;
            letter-spacing: 0.02em;
            position: relative;
            z-index: 1;
        }

        .ex-header p {
            position: relative;
            z-index: 1;
        }

        /* ======= CONTENIDO PRINCIPAL ======= */
        .main-section {
            padding: 40px 0 80px;
            position: relative;
        }

        .payment-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 40px 35px;
            margin: 0 auto;
            max-width: 980px;
            box-shadow:
                0 20px 60px rgba(246, 141, 62, 0.15),
                0 0 0 1px rgba(246, 141, 62, 0.05);
        }

        .payment-card h2 {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--latinnet-dark);
        }

        .payment-card p.lead {
            font-size: 0.95rem;
            color: var(--latinnet-text-gray);
        }

        .form-label {
            font-weight: 500;
            color: var(--latinnet-dark);
            margin-bottom: 8px;
        }

        .form-control {
            border-radius: 12px;
            border: 2px solid #E5E7EB;
            padding: 12px 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--latinnet-orange);
            box-shadow: 0 0 0 4px rgba(246, 141, 62, 0.1);
            outline: none;
        }

        .btn-main {
            background: linear-gradient(135deg, #122761 0%, #193689 50%, #4A6FD1 100%);
            border: none;
            color: #fff !important;
            border-radius: 50px;
            padding: 14px 40px;
            font-weight: 600;
            font-size: 1rem;
            box-shadow: 0 10px 25px rgba(246, 141, 62, 0.3);
            transition: all 0.3s ease;
            text-transform: none;
        }

        .btn-main:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(246, 141, 62, 0.4);
        }

        .btn-main:active {
            transform: translateY(0);
        }

        /* ======= TARJETAS DE FACTURA ======= */
        .contratos-wrapper {
            margin-bottom: 25px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .contrato-card {
            background: var(--latinnet-light-bg);
            border: 2px solid #B8E6EA;
            border-radius: 14px;
            padding: 16px 20px;
            text-align: left;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 180px;
            display: inline-flex;
            flex-direction: column;
            justify-content: center;
        }

        .contrato-card .numero {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--latinnet-dark);
            margin-bottom: 6px;
        }

        .contrato-card .valor {
            font-size: 1rem;
            color: var(--latinnet-orange);
            font-weight: 600;
            margin: 0;
        }

        .contrato-card:hover {
            border-color: var(--latinnet-orange);
            box-shadow: 0 8px 20px rgba(246, 141, 62, 0.2);
            transform: translateY(-2px);
            background: #ffffff;
        }

        .contrato-card.selected {
            background: linear-gradient(135deg, #122761 0%, #193689 50%, #4A6FD1 100%);
            color: white;
            border-color: transparent;
            box-shadow: 0 10px 25px rgba(246, 141, 62, 0.35);
        }

        .contrato-card.selected .numero {
            color: #ffffff;
        }

        .contrato-card.selected .valor {
            color: #ffffff;
        }

        /* ======= TABLA DE DETALLE ======= */
        .info.table {
            font-size: 0.9rem;
            border-radius: 12px;
            overflow: hidden;
        }

        .info th {
            background: var(--latinnet-light-bg);
            border-top: none;
            font-weight: 600;
            color: var(--latinnet-dark);
            padding: 14px;
        }

        .info td {
            vertical-align: middle;
            padding: 14px;
        }

        .info tbody tr:first-child th {
            background: linear-gradient(135deg, #122761 0%, #193689 50%, #4A6FD1 100%);
            color: white;
            font-size: 0.95rem;
        }

        /* ======= BOTONES DE PASARELA ======= */
        .gateway-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
        }

        .gateway-buttons .btn-main {
            min-width: 220px;
        }

        /* ======= LOADER OVERLAY ======= */
        .loader {
            position: fixed;
            inset: 0;
            width: 100%;
            height: 100%;
            z-index: 9999;
            background: rgba(246, 141, 62, 0.25);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
        }

        .loader::before {
            content: "";
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: #ffffff url('software/images/loader.gif') center center no-repeat;
            background-size: 60%;
            box-shadow: 0 15px 40px rgba(246, 141, 62, 0.4);
        }

        /* Nav item "Pagos en Línea" resaltado */
        #navbarsExampleDefault>ul>li:nth-child(2)>a {
            cursor: pointer;
            color: var(--latinnet-orange) !important;
            font-weight: 600;
        }

        hr {
            border-color: #B8E6EA;
            opacity: 0.5;
        }

        /* ======= FOOTER STYLES ======= */
        .location {
            background: linear-gradient(135deg, #122761 0%, #193689 50%, #4A6FD1 100%);
            position: relative;
            overflow: hidden;
        }

        .location::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: repeating-linear-gradient(45deg,
                    transparent,
                    transparent 10px,
                    rgba(255, 255, 255, 0.03) 10px,
                    rgba(255, 255, 255, 0.03) 20px);
        }

        .location .container {
            position: relative;
            z-index: 1;
        }

        .location h2 {
            font-weight: 700;
            font-size: 1.5rem;
            letter-spacing: 0.02em;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .location h6 {
            font-weight: 600;
            font-size: 0.85rem;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.95);
        }

        .location p {
            font-size: 0.95rem;
            margin: 0;
            color: rgba(255, 255, 255, 0.9);
        }

        .location i {
            color: var(--rivertel-lime-light) !important;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
        }

        .location .col-lg-3 {
            margin-bottom: 20px;
        }

        .footer {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            padding: 20px 0;
        }

        .footer p {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
        }

        .footer a {
            color: var(--rivertel-lime-light) !important;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .footer a:hover {
            color: var(--latinnet-orange-light);
            text-decoration: underline;
        }

        /* WhatsApp Button */
        .whatsapp {
            position: fixed;
            right: 25px;
            bottom: 20px;
            z-index: 999;
        }

        .whatsapp img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.4);
            transition: all 0.3s ease;
        }

        .whatsapp:hover img {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(37, 211, 102, 0.6);
        }

        /* ======= RESPONSIVE ======= */
        @media (max-width: 768px) {
            .payment-card {
                padding: 25px 20px;
                border-radius: 16px;
            }

            .ex-header {
                padding: 120px 0 50px;
            }

            .contratos-wrapper {
                justify-content: center;
            }

            .gateway-buttons {
                flex-direction: column;
                align-items: stretch;
            }

            .gateway-buttons .btn-main {
                width: 100%;
            }

            .location h2 {
                font-size: 1.2rem;
            }

            .location .col-lg-3 {
                margin-bottom: 25px;
            }

            .whatsapp {
                right: 15px;
                bottom: 15px;
            }

            .whatsapp img {
                width: 50px;
                height: 50px;
            }
        }
    </style>
</head>

<body>
    <div class="loader"></div>

    <!-- NAVBAR -->
    <nav id="navbar" class="navbar navbar-expand-lg navbar-light" aria-label="Main navigation">
        <div class="container">
            <a class="navbar-brand logo-image" href="">
                <img src="../software/images/Empresas/Empresa1/logo.png" alt="Logo">
            </a>
            <button class="navbar-toggler p-0 border-0" type="button" id="navbarSideCollapse" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="navbar-collapse offcanvas-collapse" id="navbarsExampleDefault">
                <ul class="navbar-nav ms-auto navbar-nav-scroll">
                    <li class="nav-item">
                        <a class="nav-link" aria-current="page" href="">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#pago">Pagos en Línea</a>
                    </li>
                </ul>
                <span class="nav-item social-icons d-none">
                    <span class="fa-stack">
                        <a href="#your-link">
                            <i class="fas fa-circle fa-stack-2x"></i>
                            <i class="fab fa-facebook-f fa-stack-1x"></i>
                        </a>
                    </span>
                    <span class="fa-stack">
                        <a href="#your-link">
                            <i class="fas fa-circle fa-stack-2x"></i>
                            <i class="fab fa-twitter fa-stack-1x"></i>
                        </a>
                    </span>
                </span>
            </div>
        </div>
    </nav>

    <!-- HEADER -->
    <header class="ex-header">
        <div class="container">
            <h1>Portal de Pagos <?=utf8_encode($empresa['nombre']);?></h1>
            <p class="mt-3 mb-0" style="opacity:.95; font-size: 1.05rem;">
                Comunicación sin límite - Ingresa tus datos para continuar con el pago seguro
            </p>
        </div>
    </header>
    <!-- SECCIÓN PRINCIPAL -->
    <div class="ex-basic-1 pt-5 pb-5">
        <div class="container">
            <!-- FORMULARIO DE CONSULTA -->
            <div class="row" id="form-factura0">
                <div class="col-lg-6 offset-lg-3 text-justify">
                    <div class="mb-5 mb-lg-0" data-aos="fade-up" data-aos-delay="300">
                        <form>
                            <div class="col-12 pb-2 d-none">
                                <input class="form-control" type="text" name="nombre" id="nombre" placeholder="Nombre Completo" title="Nombre Completo" required="" autocomplete="off">
                            </div>
                            <div class="col-12 pb-2">
                                <input class="form-control" type="text" name="identificacion" id="identificacion" placeholder="Identificación" title="Identificación" required="" autocomplete="off">
                            </div>
                        </form>
                        <div class="col-12 py-2">
                            <center><button class="btn btn-main" style="color: var(--secondary);" onclick="contratos();">Consultar</button></center>
                        </div>
                    </div>
                </div>
            </div>
            <!-- RESULTADOS -->
            <div class="row" style="display: none;" id="mensaje">
                <div class="col-lg-8 offset-lg-2">

                    <div id="contratosContainer">

                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-sm info">
                            <tbody>
                                <tr>
                                    <th width="25%">DATOS GENERALES</th>
                                    <th></th>
                                </tr>
                                <tr>
                                    <th>Cliente</th>
                                    <td><span id="resul_cliente"></span></td>
                                </tr>
                                <tr>
                                    <th>Identificación</th>
                                    <td><span id="resul_identificacion"></span></td>
                                </tr>
                                <tr>
                                    <th>N° Factura</th>
                                    <td><span id="resul_factura"></span></td>
                                </tr>
                                <tr>
                                    <th>Emisión</th>
                                    <td><span id="resul_emision"></span></td>
                                </tr>
                                <tr>
                                    <th>Vencimiento</th>
                                    <td><span id="resul_vencimiento"></span></td>
                                </tr>
                                <tr>
                                    <th>Precio</th>
                                    <td><span id="resul_price"></span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <center>
                        <hr>
                        <form action="https://checkout.wompi.co/p/" method="GET" id="form-wompi" class="d-none">
                            <input type="hidden" name="public-key" id="public_key_wompi" />
                            <input type="hidden" name="currency" value="COP" />
                            <input type="hidden" name="amount-in-cents" id="amount-in-cents" />
                            <input type="hidden" name="reference" id="reference" />
                            <input type="hidden" name="redirect-url" id="redirect_url_wompi" />
                            <input type="hidden" name="signature:integrity" id="signature_integrity_wompi" />

                            <button class="btn btn-success" type="submit">Pagar con Wompi</button>
                        </form>
                        <button class="btn btn-main d-none" style="color: var(--secondary);" onclick="confirmar('form-wompi', 'WOMPI');" id="btn_wompi">Pagar con Wompi</button>

                        <form method="post" action="https://checkout.payulatam.com/ppp-web-gateway-payu/" id="form-payu" class="d-none">
                            <input id="merchantId" name="merchantId" type="hidden" value="">
                            <input id="accountId" name="accountId" type="hidden" value="">
                            <input id="description" name="description" type="hidden" value="">
                            <input id="referenceCode" name="referenceCode" type="hidden" value="">
                            <input id="amount" name="amount" type="hidden" value="">
                            <input id="tax" name="tax" type="hidden" value="0">
                            <input id="taxReturnBase" name="taxReturnBase" type="hidden" value="0">
                            <input id="currency" name="currency" type="hidden" value="COP">
                            <input id="signature" name="signature" type="hidden" value="">
                            <input id="test" name="test" type="hidden" value="1">
                            <input id="buyerFullName" name="buyerFullName" type="hidden" value="">
                            <input id="telephone" name="telephone" type="hidden" value="">
                            <input id="buyerEmail" name="buyerEmail" type="hidden" value="">
                            <input id="responseUrl" name="responseUrl" type="hidden" value="">
                            <input id="confirmationUrl" name="confirmationUrl" type="hidden" value="">
                            <input name="Submit" type="submit" value="Enviar">
                        </form>
                        <button class="btn btn-main d-none" style="color: var(--secondary);" onclick="confirmar('form-payu', 'PayU');" id="btn_payu">Pagar con PayU</button>

                        <form id="form-epayco" class="d-none">
                            <script
                                src="https://checkout.epayco.co/checkout.js"
                                class="epayco-button"
                                data-epayco-currency="cop"
                                data-epayco-country="co"
                                data-epayco-test="true"
                                data-epayco-external="true"
                                data-epayco-response="https://ejemplo.com/respuesta.html"
                                data-epayco-confirmation="https://ejemplo.com/confirmacion"
                                data-epayco-methodconfirmation="post"
                                id="script_epayco">
                            </script>
                        </form>
                        <button class="btn btn-main d-none" style="color: var(--secondary);" onclick="confirmar('form-epayco', 'ePayco');" id="btn_epayco">Pagar con ePayco</button>

                        <button class="btn btn-main d-none" style="color: var(--secondary);" onclick="confirmar('form-combopay', 'ComboPay');" id="btn_combopay">Pagar con ComboPay</button>
                        <a class="d-none" id="a_combopay"></a>
                    </center>
                </div>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <!-- Location -->
    <section class="location text-light py-5" id="contact">
        <div class="container" data-aos="zoom-in">
            <div class="row">
                <div class="col-lg-12 align-items-center text-center pb-4">
                    <h2 class="py-2">CONTÁCTANOS <?=utf8_encode($empresa['nombre']);?></h2>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-3 d-flex align-items-center">
                    <div class="p-2"><i class="fas fa-map-marker-alt fa-3x"></i></div>
                    <div class="ms-2">
                        <h6>UBICACIÓN</h6>
                        <p><?=utf8_encode($empresa['direccion']);?></p>
                    </div>
                </div>
                <div class="col-lg-3 d-flex align-items-center">
                    <div class="p-2"><i class="fas fa-phone-alt fa-3x"></i></div>
                    <div class="ms-2">
                        <h6>TELÉFONO</h6>
                        <p><?=utf8_encode($empresa['telefono']);?></p>
                    </div>
                </div>
                <div class="col-lg-3 d-flex align-items-center">
                    <div class="p-2"><i class="far fa-envelope fa-3x"></i></div>
                    <div class="ms-2">
                        <h6>CORREO</h6>
                        <p><?=utf8_encode($empresa['email']);?></p>
                    </div>
                </div>
                <div class="col-lg-3 d-flex align-items-center">
                    <div class="p-2"><i class="far fa-clock fa-3x"></i></div>
                    <div class="ms-2">
                        <h6>HORARIO</h6>
                        <p>Lunes a Viernes<br>8:00 AM - 6:00 PM</p>
                    </div>
                </div>
            </div> <!-- end of row -->
        </div> <!-- end of container -->
    </section> <!-- end of location -->

    <!-- Footer -->
    <section class="footer text-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 py-md-4 text-center">
                    <div class="align-items-center">
                        <p class="text-center mb-0">Copyright &copy;<script>
                                document.write(new Date().getFullYear());
                            </script> Todos los derechos reservados | Desarrollado por <a href="https://integracolombia.com/" target="_blank">Integra Colombia S.A.S</a></p>
                    </div>
                </div>
            </div> <!-- end of row -->
        </div> <!-- end of container -->
    </section> <!-- end of footer -->

    <div class="whatsapp text-left">
        <a href="https://api.whatsapp.com/send?phone=573180983544&text=Hola,%20estoy%20interesado%20en%20el%20servicio%20de%20Internet." target="_blank" title="Contáctame por WhatsApp">
            <img src="./assets/images/whatsapp.png" alt="WhatsApp" />
        </a>
    </div>

    <!-- Messenger plugin de chat Code -->
    <div id="fb-root"></div>

    <!-- Your plugin de chat code -->
    <div id="fb-customer-chat" class="fb-customerchat">
    </div>
    <!-- Scripts -->
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
    <script src="./js/bootstrap.min.js"></script><!-- Bootstrap framework -->
    <script src="./js/purecounter.min.js"></script> <!-- Purecounter counter for statistics numbers -->
    <script src="./js/swiper.min.js"></script><!-- Swiper for image and text sliders -->
    <script src="./js/aos.js"></script><!-- AOS on Animation Scroll -->
    <script src="./js/script.js"></script> <!-- Custom scripts -->
    <script src="./software/vendors/sweetalert2/sweetalert2.min.js"></script>
    <script src="js/jquery.md5.js"></script>

    <script>
        function cargando(abierta) {
            if (abierta) {
                $(".loader").show();
            } else {
                $(".loader").hide();
            }
        }

        function number_format(number, decimals, dec_point, thousands_sep) {
            number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
            var n = !isFinite(+number) ? 0 : +number,
                prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
                sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
                dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
                s = '',
                toFixedFix = function(n, prec) {
                    var k = Math.pow(10, prec);
                    return '' + Math.round(n * k) / k;
                };
            s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
            if (s[0].length > 3) {
                s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
            }
            if ((s[1] || '').length < prec) {
                s[1] = s[1] || '';
                s[1] += new Array(prec - s[1].length + 1).join('0');
            }
            return s.join(dec);
        }

        function confirmar(form, mensaje, submensaje = '¿Desea continuar?') {
            Swal.fire({
                type: 'question',
                title: 'Será redireccionado a la pasarela de pago ' + mensaje,
                text: submensaje,
                confirmButtonText: 'Aceptar',
                cancelButtonText: 'Cancelar',
                showCancelButton: true,
                confirmButtonColor: '#00ce68',
                cancelButtonColor: '#d33',
            }).then((result) => {
                if (result.value) {
                    cargando(true);
                    if (form == 'form-epayco') {
                        $(".epayco-button-render").click();
                    } else if (form == 'form-combopay') {
                        $("#a_combopay")[0].click();
                    } else {
                        document.getElementById(form).submit();
                    }
                }
            })
        }

        function contratos() {
            if ($("#identificacion").val() == '') {
                Swal.fire({
                    title: 'Disculpa, llena los campos con la información solicitada para completar la consulta',
                    icon: 'error',
                    timer: 5000
                });
                return false;
            }

            $.ajax({
                url: '/software/factura/' + $("#identificacion").val(),
                beforeSend: function() {
                    cargando(true);
                },
                success: function(data) {
                    cargando(false);
                    $('#mensaje').removeAttr('style');
                    let contratosContainer = $("#contratosContainer");
                    contratosContainer.empty(); // Limpiar antes de agregar nuevos contratos

                    if (!data.contrato || data.contrato.length === 0) {
                        contratosContainer.html('<p class=" text-center mb-0">No hay contrato asociado al cliente.</p>')
                            .css('display', 'block')
                            .css('min-height', '50px')
                            .css('color', '#F54927');
                        return;
                    }

                    data.contrato.forEach(contrato => {
                        console.log(contrato)
                        let contratoCard = `
                        <div class="contrato-card" onclick="consultar('${contrato.facturaId}', '${contrato.nit}')">
                            <p class="numero">Factura #${contrato.factura}</p>
                            <p class="valor">$${contrato.price.toLocaleString()}</p>
                        </div>
                    `;
                        contratosContainer.append(contratoCard);
                    });
                },
                error: function() {
                    cargando(false);
                    Swal.fire({
                        title: 'Error al obtener los contratos',
                        icon: 'error',
                        timer: 5000
                    });
                }
            });
        }


        function consultar(facturaId, identificacion) {

            $.ajax({
                url: '/software/factura/' + identificacion + '/' + facturaId,
                beforeSend: function() {
                    cargando(true);
                },
                success: function(data) {
                    if (data.contrato) {
                        $('#form-factura0').hide();
                        $('#mensaje').removeAttr('style');

                        var fullname = data.contrato.nombre;
                        if (data.contrato.apellido1) {
                            fullname = fullname + ' ' + data.contrato.apellido1;
                        }
                        if (data.contrato.apellido1) {
                            if (data.contrato.apellido2 != null) {
                                fullname = fullname + ' ' + data.contrato.apellido2;
                            }
                        }

                        $("#resul_cliente").text(fullname);
                        $("#resul_identificacion").text(data.contrato.nit);
                        $("#resul_factura").text(data.contrato.factura);
                        $("#resul_emision").text(data.contrato.emision);
                        $("#resul_vencimiento").text(data.contrato.vencimiento);
                        $("#resul_price").text(number_format(data.contrato.price, '2', ',', '.'));

                        $(".contrato-card").removeClass("selected");
                        $(`.contrato-card:contains('${facturaId}')`).addClass("selected");
                    } else {
                        Swal.fire({
                            title: 'No existe ninguna factura pendiente, relacionada con el cliente indicado',
                            type: 'error',
                            showCancelButton: false,
                            showConfirmButton: false,
                            cancelButtonColor: '#d33',
                            cancelButtonText: 'Cancelar',
                            timer: 10000
                        });
                    }

                    if (data.contrato) {
                        var str = window.location.hostname;
                        $.each(data.pasarelas, function(index, value) {
                            if (value.nombre === 'WOMPI') {

                                    const reference = '<?=$nom_empresa;?>-' + data.contrato.factura;
                                    const amountInCents = parseInt(parseFloat(data.contrato.price) * 100);
                                    const currency = 'COP';
                                    const integritySecret = value.integrity; // prod_integrity_xxxxx
                                
                                    $("#reference").val(reference);
                                    $("#amount-in-cents").val(amountInCents);
                                    $("#public_key_wompi").val(value.api_key);
                                    $("#redirect_url_wompi").val('https://' + str + '/wompi.php');
                                
                                    // 🧠 CADENA PARA EL HASH
                                    const integrityString = reference + amountInCents + currency + integritySecret;
                                
                                    // 🔐 GENERAR FIRMA
                                    sha256(integrityString).then(signature => {
                                        $("#signature_integrity_wompi").val(signature);
                                        $("#btn_wompi").removeClass('d-none');
                                    });
                                
                                }else if (value.nombre == 'PayU') {
                                var amount = (parseFloat(data.contrato.price) * 1);
                                $("#merchantId").val(value.merchantId);
                                $("#accountId").val(value.accountId);
                                $("#description").val('Factura ' + data.contrato.factura);
                                $("#referenceCode").val('<?= $nom_empresa; ?>-' + data.contrato.factura);
                                $("#amount").val(amount);
                                $("#tax").val(number_format(data.contrato.price, '2', ',', '.'));
                                $("#buyerFullName").val(data.cliente.nombre);
                                $("#buyerEmail").val(data.cliente.email);
                                $("#telephone").val(data.cliente.celular);
                                $("#responseUrl").val('https://' + str + '/payu.php');
                                $("#confirmationUrl").val('https://' + str + '/software/api/pagos/payu');
                                $("#btn_payu").removeClass('d-none');

                                $("#signature").val($.md5(value.api_key + "~" + value.merchantId + "~<?= $nom_empresa; ?>-" + data.contrato.factura + "~" + amount * 1 + "~COP"));
                            } else if (value.nombre == 'ePayco') {
                                var amount = (parseFloat(data.contrato.price) * 1);
                                $("#script_epayco").attr('data-epayco-key', value.api_key)
                                    .attr('data-epayco-amount', amount)
                                    .attr('data-epayco-name', '<?= $nom_empresa; ?>-' + data.contrato.factura)
                                    .attr('data-epayco-description', '<?= $nom_empresa; ?>-' + data.contrato.factura)
                                    .attr('data-epayco-email-billing', data.cliente.email)
                                    .attr('data-epayco-name-billing', data.cliente.nombre)
                                    .attr('data-epayco-address-billing', data.cliente.direccion)
                                    .attr('data-epayco-mobilephone-billing', data.cliente.celular)
                                    .attr('data-epayco-number-doc-billing', data.cliente.nit)
                                    .attr('data-epayco-response', 'https://' + str + '/epayco.php')
                                    .attr('data-epayco-confirmation', 'https://' + str + '/software/api/pagos/epayco');
                                $("#btn_epayco").removeClass('d-none');
                            } 
                            else if(value.nombre == 'ComboPay'){
                                    var token = {
                                        "url": "https://intercaldas.online/software/api/token-combopay?client_id="+value.accountId+"&client_secret="+value.merchantId+"&user="+value.user+"&pass="+value.pass,
                                        "method": "POST",
                                        "timeout": 0,
                                    };

                                    $.ajax(token).done(function (response) {
                                    if(response.access_token){
                                        var amount = parseFloat(data.contrato.price);
                                        var tip_iden = (data.contrato.tip_iden == 3 || data.contrato.tip_iden == 4) ? 'CC' : 'NIT';

                                        var linkData = {
                                            access_token: response.access_token,
                                            data: {
                                                value: amount,
                                                description: data.contrato.factura,
                                                invoice: "<?=$nom_empresa;?>-" + data.contrato.factura,
                                                url_data_return: "https://"+str+"/software/api/pagos/combopay",
                                                url_client_redirect: "https://"+str+"/pay.php",
                                                name: fullname,
                                                document_type: tip_iden,
                                                customer_phone_number: data.contrato.celular,
                                                document: data.contrato.nit,
                                                customer_address: data.contrato.direccion
                                            }
                                        };

                                        $.ajax({
                                            url: "https://intercaldas.online/software/api/combopay/payment-link",
                                            method: "POST",
                                            data: JSON.stringify(linkData),
                                            contentType: "application/json",
                                            success: function (res) {
                                                if (res.payment_link) {
                                                    $("#btn_combopay").removeClass('d-none');
                                                    $("#a_combopay").attr('href', res.payment_link);
                                                }
                                            }
                                        });
                                    }
                                });
                            }
                        });
                    }
                    cargando(false);
                },
                error: function(data) {
                    Swal.fire({
                        title: 'Disculpe, estamos presentando problemas al tratar de enviar el formulario, intentelo más tarde.',
                        type: 'error',
                        showCancelButton: false,
                        showConfirmButton: false,
                        cancelButtonColor: '#d33',
                        cancelButtonText: 'Cancelar',
                        timer: 5000
                    });
                    cargando(false);
                }
            });
        }
        
        async function sha256(message) {
            const encoder = new TextEncoder();
            const data = encoder.encode(message);
            const hashBuffer = await crypto.subtle.digest('SHA-256', data);
            const hashArray = Array.from(new Uint8Array(hashBuffer));
            return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
        }
    </script>
</body>

</html>