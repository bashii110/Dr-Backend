<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        if (!$request->user() || !in_array($request->user()->type, $roles)) {
            return response()->json([
                'status'  => 403,
                'message' => 'Access denied. Insufficient permissions.',
            ], 403);
        }

        return $next($request);
    }
}