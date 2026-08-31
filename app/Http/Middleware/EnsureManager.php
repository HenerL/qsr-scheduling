<?php

namespace App\Http\Middleware;

use App\Helpers\QueryResultHelperV2;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureManager
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->user_type !== 'manager') {
            return QueryResultHelperV2::onUnauthorized('Manager access required.');
        }

        return $next($request);
    }
}
