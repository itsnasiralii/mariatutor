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
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        if (! Auth::check()) {
            return redirect()->route('login')->with('warning', 'Please sign in to access this page.');
        }

        $user = Auth::user();

        if (! $user->active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Your account has been deactivated. Please contact support.');
        }

        if (! in_array((string) $user->role, $roles, true)) {
            abort(403, 'Unauthorized access for your account role.');
        }

        return $next($request);
    }
}
