<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();
        if (!$user) abort(403);
        if (!in_array($user->role, $roles, true)) {
            if ($request->routeIs('admin.*')) { abort(403); }
            if ($user->role === 'customer' && $request->routeIs('panel.*')) { abort(403); }
            abort(403);
        }
        return $next($request);
    }
}
