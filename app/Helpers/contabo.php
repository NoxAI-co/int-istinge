<?php

use App\Services\ContaboS3Service;

if (! function_exists('contabo_url')) {
    /**
     * URL estable a través del proxy interno (Route 'contabo.asset').
     * Usá esta para <img src>, <link rel="icon">, etc. — el browser pega
     * a la app y la app redirige (302) a la URL firmada de Contabo.
     *
     * Si $filename es null o vacío, devuelve '' para que el caller no truene
     * (muchos campos tipo adjunto son nullables: radicado->adjunto,
     * ingreso->adjunto_pago, asignacion->imgA..H, etc.).
     */
    function contabo_url(?string $folder, ?string $filename): string
    {
        if (empty($folder) || empty($filename)) {
            return '';
        }
        return route('contabo.asset', ['folder' => $folder, 'filename' => $filename]);
    }
}

if (! function_exists('contabo_signed_url')) {
    /**
     * URL firmada directa a Contabo (caduca). Útil cuando el destinatario es
     * un cliente HTTP que sólo va a usarla una vez (descargas, mailings, etc.).
     */
    function contabo_signed_url(?string $folder, ?string $filename, ?int $minutes = null): string
    {
        if (empty($folder) || empty($filename)) {
            return '';
        }
        return app(ContaboS3Service::class)->signedUrl($folder, $filename, $minutes);
    }
}
