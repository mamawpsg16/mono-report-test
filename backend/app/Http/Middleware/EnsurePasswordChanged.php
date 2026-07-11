<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /**
     * Backs up the SPA router's forced-reset gate at the API layer: a user still
     * flagged must_change_password can only read their own status, reset, or log
     * out — every other endpoint is refused, even called directly (bypassing the
     * SPA). Without this the client guard alone could be sidestepped.
     */
    private const ALLOWED_PATHS = ['api/user', 'api/logout', 'api/change-password'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password && ! in_array($request->path(), self::ALLOWED_PATHS, true)) {
            abort(403, 'You must change your password before continuing.');
        }

        return $next($request);
    }
}
