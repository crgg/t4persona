<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BasicAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = config('app.LOGS_USER');
        $pass = config('app.LOGS_PASSWORD');

        $ip        = $request->ip();
        $userAgent = (string) $request->userAgent();
        $path      = (string) $request->path();

        if ($request->getUser() !== (string) $user || $request->getPassword() !== (string) $pass) {

            Log::warning('LOGS_BASIC_AUTH_DENIED', [
                'ip'         => $ip,
                'path'       => $path,
                'user_agent' => $userAgent,
                'basic_user' => $request->getUser(), // lo que intentó (si existe)
            ]);

            return response('Unauthorized', 401, [
                'WWW-Authenticate' => 'Basic realm="Logs"',
            ]);
        }

        Log::info('LOGS_BASIC_AUTH_OK', [
            'ip'         => $ip,
            'path'       => $path,
            'user_agent' => $userAgent,
            'basic_user' => $request->getUser(),
        ]);

        return $next($request);
    }
}
