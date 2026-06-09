<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ForceCurrentUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        URL::forceRootUrl($request->getSchemeAndHttpHost());
        URL::forceScheme($request->getScheme());

        config([
            'session.domain' => $this->sessionDomainFor($request),
            'session.secure' => $request->isSecure(),
            'session.same_site' => 'lax',
            'session.path' => '/',
        ]);

        return $next($request);
    }

    private function sessionDomainFor(Request $request): ?string
    {
        $host = strtolower($request->getHost());

        if ($host === 'localhost' || preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $host)) {
            return null;
        }

        if (Str::startsWith($host, 'www.')) {
            $host = Str::after($host, 'www.');
        }

        return '.'.$host;
    }
}
