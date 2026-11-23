<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with('warning', 'Please sign in to access this page.');
        }

        $user = Auth::user();

        if (!in_array($user->role, $roles)) {
            // Super Admin always has access to admin routes
            if ($user->isSuperAdmin() && in_array('admin', $roles)) {
                return $next($request);
            }

            abort(403, 'Unauthorized access for your account role.');
        }

        return $next($request);
    }
}
