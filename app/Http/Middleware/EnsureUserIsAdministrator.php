<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== 'administrador' || ! $user->is_active) {
            abort(403, 'No tienes permisos para acceder a este sistema.');
        }

        return $next($request);
    }
}
