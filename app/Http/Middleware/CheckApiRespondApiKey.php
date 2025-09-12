<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckApiRespondApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $clientIp   = $request->ip();                       // respeta TrustProxies si lo configuras
        $userAgent  = (string) $request->header('User-Agent', '');
        $path       = $request->path();
        $method     = $request->method();
        $authUserId = optional($request->user())->id;       // si hay usuario autenticado

        // 1) Falta el header
        if (! $request->hasHeader('Api-Respond-Key')) {
            Log::warning('RespondKey: missing header', [
                'ip'        => $clientIp,
                'user_agent'=> $userAgent,
                'path'      => $path,
                'method'    => $method,
                'user_id'   => $authUserId,
            ]);

            return response()->json([
                'status' => false,
                'errors' => ['authorization' => ['Missing Api-Respond-Key header']],
            ], 401);
        }

        // 2) Header presente pero inválido
        $expected = (string) config('app.API_RESPONSE_KEY', '');
        $provided = (string) $request->header('Api-Respond-Key', '');

        // Evita timing attacks
        $valid = ($expected !== '' && hash_equals($expected, $provided));

        if (! $valid) {
            Log::warning('RespondKey: invalid key attempt', [
                'ip'        => $clientIp,
                'user_agent'=> $userAgent,
                'path'      => $path,
                'method'    => $method,
                'user_id'   => $authUserId,
                // NO logueamos la clave enviada para no guardar secretos por error
            ]);

            return response()->json([
                'status' => false,
                'errors' => ['authorization' => ['Forbidden']],
            ], 403);
        }

        return $next($request);
    }
}
