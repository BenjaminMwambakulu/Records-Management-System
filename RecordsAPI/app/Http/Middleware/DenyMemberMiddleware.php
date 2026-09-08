<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DenyMemberMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
                'data' => null,
                'errors' => null,
            ], 401);
        }

        $hasMemberRole = $user->hasRole('member');

        if ($hasMemberRole) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden',
                'data' => null,
                'errors' => null,
            ], 403);
        }

        return $next($request);
    }
}
