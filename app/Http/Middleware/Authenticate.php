<?php

namespace App\Http\Middleware;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    protected function unauthenticated($request, array $guards)
    {
        if ($request->expectsJson()) {
            throw new AuthenticationException('Unauthenticated.', $guards);
        }

        if ($request->isMethod('GET')) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        throw new AuthenticationException(
            'Unauthenticated.',
            $guards,
            route('login')
        );
    }
}
