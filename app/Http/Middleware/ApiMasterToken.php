<?php

namespace App\Http\Middleware;

use Closure;

class ApiMasterToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $token = $request->bearerToken();
        $masterToken = '%V$40jbESKk9@';

        if (!$token || $token !== $masterToken) {
            return response()->json([
                'status' => 401,
                'message' => 'No Autorizado. Token inválido.'
            ], 401);
        }

        return $next($request);
    }
}
