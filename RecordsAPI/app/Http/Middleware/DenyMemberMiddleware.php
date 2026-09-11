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

        // Superadmins and admins are never restricted by member denial
        if ($user->hasAnyRole(['superadmin', 'admin'], 'logto')) {
            return $next($request);
        }

        // Allow any user who has an elevated role beyond regular member/alumni
        $hasElevatedRole = $user->roles()
            ->whereNotIn('name', ['member', 'alumni'])
            ->exists();

        if ($hasElevatedRole) {
            return $next($request);
        }

        $hasMemberRole = $user->hasRole('member', 'logto');

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
