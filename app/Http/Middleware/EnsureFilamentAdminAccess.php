<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFilamentAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        // If not logged in, let Filament's Authenticate middleware handle it
        if (! $user) {
            return $next($request);
        }

        // Only allow admin/staff
        if (! in_array($user->role, ['admin', 'staff'], true)) {
            auth()->logout();

            // You can redirect or abort. Abort is cleaner for admin panel.
            abort(403, 'You are not allowed to access the admin panel.');
        }

        return $next($request);
    }
}