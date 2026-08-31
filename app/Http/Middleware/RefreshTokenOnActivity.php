<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Personal access tokens live in localStorage, so they stay short-lived and
 * get a new expiry whenever the manager or crew is actively using the API.
 */
class RefreshTokenOnActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $minutes = (int) config('sanctum.expiration');
        $token = $request->user()?->currentAccessToken();

        if ($minutes <= 0 || !$token instanceof PersonalAccessToken || $token->expires_at === null) {
            return $response;
        }

        $refreshAfter = now()->addMinutes((int) floor($minutes / 2));

        if ($token->expires_at->gt($refreshAfter)) {
            return $response;
        }

        $token->forceFill(['expires_at' => now()->addMinutes($minutes)])->save();

        return $response;
    }
}
