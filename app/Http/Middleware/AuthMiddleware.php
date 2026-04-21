<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            if (!$request->header('Authorization')) {
                return response()->json([
                    'status' => 'unauthenticated',
                    'message' => 'Missing token'
                ], 401);
            }

            return response()->json([
                'status' => 'unauthenticated',
                'message' => 'Invalid token'
            ], 401);
        }

        $user = $request->user();
        if ($user->role === 'blocked') {
            return response()->json([
                'status' => 'blocked',
                'message' => 'User blocked',
                'reason' => 'You have been blocked by an administrator'
            ], 403);
        }

        return $next($request);
    }
}
