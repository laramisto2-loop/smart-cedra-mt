<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsPlatformAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user?->isPlatformAdministrator(),
            403,
            'Platform administrator access is required.'
        );

        return $next($request);
    }
}
