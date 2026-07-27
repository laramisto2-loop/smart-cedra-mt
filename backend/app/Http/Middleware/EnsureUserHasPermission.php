<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(
        Request $request,
        Closure $next,
        string $permission
    ): Response {
        $user = $request->user();

        abort_if(
            $user === null,
            Response::HTTP_UNAUTHORIZED,
            'Unauthenticated.'
        );

        abort_unless(
            $user->hasPermission($permission),
            Response::HTTP_FORBIDDEN,
            'You do not have permission to perform this action.'
        );

        return $next($request);
    }
}
