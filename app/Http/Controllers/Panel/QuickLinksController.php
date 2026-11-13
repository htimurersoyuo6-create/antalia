<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class QuickLinksController extends Controller
{
    private const ALLOWED_LINKS = [
        'panel.index',
        'panel.reservations',
        'panel.guests',
        'panel.hotel-chats',
        'panel.reports',
        'panel.finance',
        'panel.subscriptions',
        'panel.security',
        'panel.help',
    ];

    public function update(Request $request)
    {
        $data = $request->validate([
            'quick_links' => ['nullable', 'array', 'max:5'],
            'quick_links.*' => ['required', 'string', 'distinct', 'in:'.implode(',', self::ALLOWED_LINKS)],
        ]);

        $user = $request->user();
        $user->owner_quick_links = array_values($data['quick_links'] ?? []);
        $user->save();

        return back()->with('ok', 'Hızlı erişim menünüz kaydedildi.');
    }
}
