<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً'
            ], 401);
        }
        /** @var \App\Models\User $user */

        if (! $user->can($permission)) {
            return response()->json([
                'success' => false,
                'message' => 'ليس لديك الصلاحية الكافية',
                'required_permission' => $permission,
                'your_permissions' => $user->getAllPermissions()->pluck('name')
            ], 403);
        }

        return $next($request);
    }
}
