<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        /* Remember the incoming state: logout invalidates the session inside
         * the controller, so checking only afterwards would miss its redirect. */
        $wasAuthenticated = auth()->check();
        $response = $next($request);
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(self), camera=(self), microphone=(self)',
            'Strict-Transport-Security' => 'max-age=15552000',
        ];

        foreach ($headers as $name => $value) {
            if (!$response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        if ($wasAuthenticated || auth()->check() || $request->routeIs('logout') || $request->boolean('signed_out')) {
            $response->headers->set('Cache-Control', 'private, no-store, no-cache, max-age=0, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');
            $vary = $response->headers->get('Vary');
            if ($vary === null || !str_contains(strtolower($vary), 'cookie')) {
                $response->headers->set('Vary', trim(($vary ?? '') . ', Cookie', ', '));
            }
        }

        return $response;
    }
}
