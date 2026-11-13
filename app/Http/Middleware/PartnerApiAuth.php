<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\PartnerHotel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PartnerApiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['error' => 'missing_token', 'message' => 'Bearer token gerekli.'], 401);
        }

        $partner = PartnerHotel::query()
            ->where('api_key_hash', hash('sha256', $token))
            ->where('is_active', true)
            ->first();

        if (! $partner) {
            return response()->json(['error' => 'invalid_token', 'message' => 'Geçersiz veya pasif partner anahtarı.'], 401);
        }

        $request->attributes->set('partner', $partner);

        return $next($request);
    }
}
