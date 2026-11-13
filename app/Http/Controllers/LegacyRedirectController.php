<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/** Eski adresleri kanonik adrese 301 ile yonlendirir; sorgu parametrelerini korur. */
class LegacyRedirectController extends Controller
{
    public function hotel(Request $request, string $slug)
    {
        return redirect()->to(url('/' . $slug) . $this->query($request), 301);
    }

    public function forumTopic(Request $request, string $slug)
    {
        return redirect()->to(url('/forum/' . $slug) . $this->query($request), 301);
    }

    private function query(Request $request): string
    {
        $qs = $request->getQueryString();
        return $qs ? '?' . $qs : '';
    }
}
