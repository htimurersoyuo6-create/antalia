<?php

namespace App\Http\Middleware;

use App\Support\SecurityAudit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

class AuditWebActions
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $user = $request->user();
        if (!$user || !in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) return $response;

        $route = $request->route();
        $name = $route instanceof Route ? (string) ($route->getName() ?: $route->uri()) : $request->path();
        $resourceType = null;
        $resourceId = null;
        if ($route instanceof Route) {
            foreach ($route->parameters() as $parameter) {
                if (is_object($parameter) && method_exists($parameter, 'getKey')) {
                    $resourceType = class_basename($parameter);
                    $resourceId = (string) $parameter->getKey();
                    break;
                }
                if (is_scalar($parameter) && ctype_digit((string) $parameter)) {
                    $resourceId = (string) $parameter;
                    break;
                }
            }
        }
        try {
            \App\Models\SecurityEvent::create([
                'user_id' => $user->id,
                'actor_id' => $user->id,
                'event_type' => str_starts_with($name, 'panel.') ? 'hotel_panel_action' : 'account_action',
                'result' => $response->getStatusCode() < 400 ? 'success' : 'failed',
                'action' => mb_substr($request->method().' '.$name, 0, 180),
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'details' => ['http_status' => $response->getStatusCode()],
                'occurred_at' => now(),
            ]);
        } catch (\Throwable $e) { report($e); }
        return $response;
    }
}
