<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['status' => false, 'msg' => 'Unauthenticated'], 401);
        }

        if (empty($user->email_verified_at)) {
            return response()->json(['status' => false, 'msg' => 'Email not verified'], 403);
        }

        return $next($request);
    }
}
