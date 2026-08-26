<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        'software/empresa/asignaciones/*/imprimir',

        // Meta postea aquí sin sesión ni token CSRF. Al vivir en routes/web.php
        // la ruta caía dentro del grupo `web`, así que TODO webhook entrante
        // recibía 419 y se descartaba: ni mensajes de clientes ni estados de
        // entrega llegaban nunca. La autenticidad se comprueba en el controlador
        // con la firma X-Hub-Signature-256, no con CSRF.
        'webhooks/whatsapp',
    ];
}
