<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CookiePreferenceController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'choice' => 'required|in:accepted,rejected',
            'return_to' => 'nullable|string|max:2048',
        ]);

        $returnTo = $this->localReturnPath($data['return_to'] ?? null);
        $cookie = cookie(
            'analytics_consent',
            $data['choice'],
            60 * 24 * 180,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax',
        );

        return redirect()->to($returnTo, 303)->withCookie($cookie);
    }

    private function localReturnPath(?string $returnTo): string
    {
        if (!is_string($returnTo)
            || !str_starts_with($returnTo, '/')
            || str_starts_with($returnTo, '//')
            || str_contains($returnTo, '\\')
            || preg_match('/[\r\n]/', $returnTo)) {
            return route('home');
        }

        $parts = parse_url($returnTo);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
            return route('home');
        }

        $path = (string) ($parts['path'] ?? '/');
        if (!str_starts_with($path, '/') || str_starts_with($path, '//')
            || $path === parse_url(route('cookie-preferences.analytics'), PHP_URL_PATH)) {
            return route('home');
        }

        return $path . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }
}
