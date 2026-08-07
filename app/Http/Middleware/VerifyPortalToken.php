<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Protege el módulo master-api (peticiones del Integra Portal).
 * Réplica del middleware homónimo de integra2.0: Bearer == PORTAL_MASTER_TOKEN
 * y, si hay cuerpo, X-Integra-Signature == HMAC-SHA256(cuerpo, token).
 */
class VerifyPortalToken
{
    public function handle(Request $request, Closure $next)
    {
        // Vía config (NO env()): el contenedor corre `config:cache` al arrancar
        // y env() devolvería null en runtime -> 503 permanente.
        $token = (string) config('services.portal.token');

        if ($token === '') {
            return response()->json(['message' => 'master-api no configurado (falta PORTAL_MASTER_TOKEN).'], 503);
        }

        if (! hash_equals($token, (string) $request->bearerToken())) {
            return response()->json(['message' => 'No autorizado.'], 401);
        }

        $body = (string) $request->getContent();
        if ($body !== '') {
            $firma = (string) $request->header('X-Integra-Signature');
            if (! hash_equals(hash_hmac('sha256', $body, $token), $firma)) {
                return response()->json(['message' => 'Firma inválida.'], 401);
            }
        }

        return $next($request);
    }
}
