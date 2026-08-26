<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Autentica las peticiones del puente externo de WhatsApp (whatsive).
 *
 * Las rutas /api/whatsapp/{action} y /api/uploadfile estaban completamente
 * abiertas: el grupo `api` de Laravel 7 es throttle + bindings, sin auth. Con
 * ellas se podía escribir en chats_whatsapp y subir archivos sin credenciales.
 *
 * Se admiten dos mecanismos porque no todos los puentes pueden mandar cabeceras
 * propias: un token compartido o una lista de IPs. Basta cumplir uno.
 * Si no hay ninguno configurado se rechaza — no configurar nada no puede
 * significar "deja pasar a cualquiera".
 */
class VerifyWhatsappBridge
{
    public function handle(Request $request, Closure $next)
    {
        $token = config('services.whatsapp_bridge.token');
        $allowlist = array_filter(array_map('trim', explode(',', (string) config('services.whatsapp_bridge.ips'))));

        if (empty($token) && empty($allowlist)) {
            Log::error('❌ Puente WhatsApp: sin WHATSAPP_BRIDGE_TOKEN ni WHATSAPP_BRIDGE_IPS configurados, se rechaza la petición');

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (!empty($token) && $this->tokenValido($request, $token)) {
            return $next($request);
        }

        if (!empty($allowlist) && in_array($request->ip(), $allowlist, true)) {
            return $next($request);
        }

        Log::warning('⚠️ Puente WhatsApp: petición rechazada', [
            'ip'     => $request->ip(),
            'ruta'   => $request->path(),
            'token'  => $request->header('X-Whatsapp-Token') !== null || $request->input('token') !== null,
        ]);

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * El token puede venir por cabecera o como campo del formulario: el puente
     * es de terceros y no siempre permite añadir cabeceras.
     */
    private function tokenValido(Request $request, string $esperado): bool
    {
        foreach ([$request->header('X-Whatsapp-Token'), $request->input('token')] as $recibido) {
            if (is_string($recibido) && $recibido !== '' && hash_equals($esperado, $recibido)) {
                return true;
            }
        }

        return false;
    }
}
