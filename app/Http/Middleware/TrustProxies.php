<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Fideloper\Proxy\TrustProxies as Middleware;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * Todo el tráfico entra por el contenedor de Caddy, que es lo único
     * publicado en el host (80/443); los contenedores de aplicación sólo son
     * alcanzables desde la red `web` de Docker. Con esto en null, Laravel
     * ignoraba X-Forwarded-For y $request->ip() devolvía la IP interna de
     * Caddy (172.18.0.x) para TODAS las peticiones, así que cualquier lista
     * blanca de IPs era inservible: o no coincidía nunca, o al poner la IP de
     * Caddy dejaba pasar a internet entero.
     *
     * Se confía sólo en la subred de Docker: nadie de fuera puede llegar
     * directo a los contenedores para falsificar la cabecera.
     *
     * @var array|string|null
     */
    protected $proxies = '172.18.0.0/16';

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers = Request::HEADER_X_FORWARDED_ALL;
}
