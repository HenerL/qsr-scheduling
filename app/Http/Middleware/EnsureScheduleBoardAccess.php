<?php

namespace App\Http\Middleware;

use App\Helpers\QueryResultHelperV2;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureScheduleBoardAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return QueryResultHelperV2::onUnauthorized('Schedule board access required.');
        }

        if ($user->user_type === 'manager' || $user->isCrewTeamLeader()) {
            return $next($request);
        }

        return QueryResultHelperV2::onUnauthorized('Schedule board access required.');
    }
}
