<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps customers and role-less accounts out of the admin area.
 * This is only the front door: every admin action still needs its own permission.
 */
class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if(
            $user === null || $user->isCustomerOnly() || $user->getRoleNames()->isEmpty(),
            403
        );

        return $next($request);
    }
}
