<nav class="navbar default-layout col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
    <div class="text-center navbar-brand-wrapper d-flex align-items-top justify-content-center">
        <a class="navbar-brand brand-logo" href="{{route('home')}}">
            @if(Auth::user()->rol != 1)
            <img class="img-contenida" src="{{asset('images/Empresas/Empresa'.Auth::user()->empresa.'/'.Auth::user()->empresa()->logo)}}" alt="logo" onerror="this.src='{{asset('../images/logo1.png')}}'" style="height: 50px;" />
            @else
            <img class="img-contenida" src="{{asset('images/Empresas/Empresa1/logo.png')}}" alt="logo" style="height: 50px;" />
            @endif
        </a>
        <a class="navbar-brand brand-logo-mini" href="{{route('home')}}">
            <img class="img-contenida" src="{{asset('images/Empresas/Empresa'.Auth::user()->empresa.'/favicon.png')}}" alt="logo" style="width: 75px !important;" />
        </a>
    </div>
    <div class="navbar-menu-wrapper d-flex align-items-center">
        <ul class="navbar-nav navbar-nav-right d-block">
            @if(Auth::user()->rol > 1 && auth()->user()->rol == 8)
            <li class="nav-item dropdown d-none d-inline-block">
                <span id="div_ganancia" class="d-block mt-1 mb-1 d-lg-inline-flex font-weight-bold text-white small saldo" idUser="{{auth()->user()->id}}"" style=" background: @if(Auth::user()->ganancia == 0) #fc2919 @else #55de4c @endif;padding: 10px 20px;border-radius: 15px;">GANANCIA: {{Auth::user()->empresa()->moneda}}{{ App\Funcion::Parsear(Auth::user()->ganancia) }}</span>
                <span id="div_saldo" class="d-block mt-1 mb-1 d-lg-inline-flex font-weight-bold text-white small" style="background: @if(Auth::user()->saldo == 0) #fc2919 @else #55de4c @endif;padding: 10px 20px;border-radius: 15px;">SALDO: {{Auth::user()->empresa()->moneda}}{{ App\Funcion::Parsear(Auth::user()->saldo) }}</span>
            </li>
            @endif

            {{-- Botón de Control de Asistencias --}}
            <li class="nav-item d-none d-lg-inline-block">
                <a class="nav-link" href="{{route('asistencias.mi-qr')}}" id="btn-asistencia" style="position: relative;">
                    <i class="fas fa-clock" id="icono-asistencia" style="font-size: 18px;"></i>
                    <span class="d-none d-xl-inline ml-1" id="texto-asistencia">Marcar Asistencia</span>
                    <!-- Badge removido, solo usamos el ícono con colores -->
                </a>
            </li>

            <li class="nav-item dropdown d-none d-xl-inline-block">
                <a class="nav-link dropdown-toggle" id="UserDropdown" href="#" data-toggle="dropdown" aria-expanded="false">
                    <span class="profile-text" style="text-transform:capitalize;">{{Auth::user()->nombres}}</span>
                    @if(Auth::user()->image)
                    <img src="{{asset('images/Empresas/Empresa'.Auth::user()->empresa.'/usuarios/'.Auth::user()->image)}}" onerror="this.src='{{asset('images/no-user-image.png')}}'" alt="profile image" class="img-xs rounded-circle">
                    @else
                    <img src="{{asset('images/no-user-image.png')}}" class="img-xs rounded-circle" alt="profile image">
                    @endif
                </a>
                <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="UserDropdown">
                    @if(isset($_SESSION['permisos']['220']))
                    <a class="dropdown-item mt-2" href="{{route('miusuario')}}">Mi perfil</a>
                    @endif
                    <a class="dropdown-item" data-toggle="modal" data-target="#servidoresModal">
                        Servidores
                    </a>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">{{ csrf_field() }}</form>
                    <a class="dropdown-item" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        Cerrar sesión
                    </a>
                </div>
            </li>
        </ul>
        <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
            <span class="mdi mdi-menu"></span>
        </button>
    </div>
</nav>

<div class="modal fade" id="modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="modal-title">INTERCAMBIO</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="modal-body">

            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="servidoresModal" tabindex="-1" aria-labelledby="servidoresModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="servidoresModalLabel">Servidores disponibles</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            @php $user = Auth::user(); @endphp
            <div class="modal-body">
                <p>Estos son los servidores que el usuario: <b>{{ $user->nombres }}</b> tiene acceso a la información.</p>
                @if($user->servidores->count() > 0)
                <ul class="list-group">
                    @foreach($user->servidores as $servidor)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        {{ $servidor->nombre ?? 'Servidor sin nombre' }}
                        <span class="badge bg-primary rounded-pill">IP: {{ $servidor->id }}</span>
                    </li>
                    @endforeach
                </ul>
                @else
                <p class="text-muted">No tienes servidores asociados actualmente.</p>
                @endif
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>

        </div>
    </div>
</div>


<!-- Script de asistencias directamente en el navbar -->
<script>
    // Variable global para controlar si ya se inicializó
    window.asistenciaInicializada = false;

    // Función para verificar si jQuery está disponible y inicializar
    function initAsistencia() {
        console.log('initAsistencia llamada. Document ready:', document.readyState);
        console.log('jQuery disponible:', typeof $ !== 'undefined');
        console.log('URL actual:', window.location.pathname);

        if (typeof $ === 'undefined') {
            console.log('jQuery no disponible, reintentando en 500ms...');
            setTimeout(initAsistencia, 500);
            return;
        }

        // Verificar que el DOM esté listo
        if (document.readyState === 'loading') {
            console.log('DOM aún cargando, esperando...');
            setTimeout(initAsistencia, 200);
            return;
        }

        console.log('Inicializando sistema de asistencias...');

        // Verificar estado de asistencia actual
        function verificarEstadoAsistencia() {
            console.log('Verificando estado de asistencia...');

            // Verificar que los elementos del DOM existen antes de hacer la petición
            const texto = $('#texto-asistencia');
            const icono = $('#icono-asistencia');
            const boton = $('#btn-asistencia');

            console.log('Elementos encontrados:');
            console.log('Texto:', texto.length);
            console.log('Icono:', icono.length);
            console.log('Botón:', boton.length);

            if (icono.length === 0) {
                console.log('Botón de asistencia no encontrado en esta página, reintentando en 1s...');
                setTimeout(verificarEstadoAsistencia, 1000);
                return;
            }

            if (window.location.pathname.split("/")[1] === "software") {
                var url = '/software/empresa/asistencias/estado-actual';
            } else {
                var url = '/empresa/asistencias/estado-actual';
            }

            $.ajax({
                url: url,
                method: 'GET',
                dataType: 'json',
                timeout: 10000,
                success: function(response) {
                    console.log('Estado asistencia recibido:', response);

                    // Volver a obtener los elementos por si acaso
                    const texto = $('#texto-asistencia');
                    const icono = $('#icono-asistencia');
                    const boton = $('#btn-asistencia');

                    if (icono.length === 0) {
                        console.log('Elementos perdidos después de AJAX');
                        return;
                    }

                    if (response.ultimo_registro) {
                        const estado = response.ultimo_registro.tipo;
                        console.log('Estado encontrado:', estado);

                        if (estado === 'ingreso') {
                            // EN EL TRABAJO - Verde (solo ícono, sin texto)
                            icono.css('color', '#28a745');
                            texto.show().css('color', '#28a745').text('Marcar Salida');
                            boton.attr('title', 'En el trabajo - Último ingreso: ' + response.ultimo_registro.hora);
                            boton.removeClass('btn-pulse');
                        } else {
                            // FUERA DEL TRABAJO - Amarillo
                            icono.css('color', '#ffc107');
                            texto.show().css('color', '#ffc107').text('Marcar Ingreso');
                            boton.attr('title', 'Fuera del trabajo - Última salida: ' + response.ultimo_registro.hora);
                            boton.addClass('btn-pulse');
                        }
                    } else {
                        // SIN REGISTROS - Gris con pulso
                        icono.css('color', '#6c757d');
                        texto.show().css('color', '#6c757d').text('Marcar Asistencia');
                        boton.attr('title', 'Sin registros hoy - Haz clic para marcar asistencia');
                        boton.addClass('btn-pulse');
                    }

                    console.log('Estado actualizado correctamente');
                },
                error: function(xhr, status, error) {
                    console.log('Error al verificar estado:', status, error, xhr.responseText);

                    // En caso de error, color por defecto
                    if ($('#icono-asistencia').length > 0) {
                        $('#icono-asistencia').css('color', '');
                    }
                }
            });
        }

        // Función global para llamar desde otras páginas
        window.actualizarEstadoAsistencia = verificarEstadoAsistencia;

        // Marcar como inicializado
        window.asistenciaInicializada = true;

        // Verificar estado al cargar con un pequeño delay
        setTimeout(verificarEstadoAsistencia, 500);

        // Configurar tooltip si el botón existe (con delay para asegurar que esté en el DOM)
        setTimeout(function() {
            if ($('#btn-asistencia').length > 0) {
                $('#btn-asistencia').tooltip({
                    placement: 'bottom',
                    trigger: 'hover'
                });
            }
        }, 1000);

        // Evento del saldo (código existente)
        $('.saldo').off('click').on('click', function() {
            if (typeof cargando === 'function') cargando('true');
            $('#form-ganancia').trigger("reset");
            var url = '/software/empresa/configuracion/gananciaUsuario';
            var _token = $('meta[name="csrf-token"]').attr('content');
            $("#modal-title").html($(this).attr('title'));
            $.post(url, {
                id: $(this).attr('idUser'),
                _token: _token
            }, function(resul) {
                $("#modal-body").html(resul);
                $('.loader').removeAttr('style').attr('style', 'display:none');
            });
            $('#modal').modal("show");
        });
    }

    // Función para verificar si ya se ejecutó en esta página
    function yaSeEjecuto() {
        return window.asistenciaInicializada &&
            typeof window.actualizarEstadoAsistencia === 'function';
    }

    // Ejecutar múltiples veces para asegurar compatibilidad
    console.log('Script de asistencias cargado');

    // 1. Si el documento ya está listo
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        console.log('Documento ya listo, ejecutando inmediatamente');
        setTimeout(initAsistencia, 100);
    } else {
        // 2. Esperar a que el documento esté listo
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOMContentLoaded disparado');
            setTimeout(initAsistencia, 100);
        });
    }

    // 3. Con jQuery si está disponible
    if (typeof $ !== 'undefined') {
        $(document).ready(function() {
            console.log('jQuery document ready disparado');
            setTimeout(initAsistencia, 200);
        });
    } else {
        // 4. Esperar a que jQuery esté disponible
        var checkJQuery = setInterval(function() {
            if (typeof $ !== 'undefined') {
                clearInterval(checkJQuery);
                console.log('jQuery finalmente disponible');
                $(document).ready(function() {
                    setTimeout(initAsistencia, 200);
                });
            }
        }, 100);
    }

    // 5. Forzar ejecución después de 2 segundos si no se ha ejecutado
    setTimeout(function() {
        if (!yaSeEjecuto()) {
            console.log('Forzando ejecución después de 2 segundos');
            initAsistencia();
        }
    }, 2000);

    // Verificar cuando cambie de página
    window.addEventListener('popstate', function() {
        console.log('popstate detectado');
        window.asistenciaInicializada = false;
        setTimeout(initAsistencia, 100);
    });

    // Verificar cuando el usuario regrese a la pestaña
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden && typeof window.actualizarEstadoAsistencia === 'function') {
            console.log('Pestaña visible de nuevo, actualizando estado');
            setTimeout(window.actualizarEstadoAsistencia, 500);
        }
    });

    // Función global para forzar reinicialización
    window.reiniciarAsistencia = function() {
        console.log('Reiniciando asistencia manualmente');
        window.asistenciaInicializada = false;
        setTimeout(initAsistencia, 100);
    };

    // Función global para debug
    window.debugAsistencia = function() {
        console.log('=== DEBUG ASISTENCIA ===');
        console.log('jQuery disponible:', typeof $ !== 'undefined');
        console.log('Inicializada:', window.asistenciaInicializada);
        console.log('Badge existe:', $('#estado-asistencia').length > 0);
        console.log('Función global existe:', typeof window.actualizarEstadoAsistencia === 'function');
        console.log('URL actual:', window.location.pathname);
        console.log('Document ready state:', document.readyState);
    };
</script>

<style>
    /* Efecto de pulso para el botón cuando no hay registros */
    .btn-pulse {
        animation: btn-pulse-animation 2s infinite ease-in-out;
    }

    @keyframes btn-pulse-animation {
        0% {
            transform: scale(1);
            opacity: 1;
        }

        50% {
            transform: scale(1.05);
            opacity: 0.8;
        }

        100% {
            transform: scale(1);
            opacity: 1;
        }
    }
</style>