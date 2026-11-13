<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use Illuminate\Http\Request;

class SecurityController extends Controller
{
    public function index(Request $request)
    {
        $query = SecurityEvent::query()->with(['user:id,name,email,phone,role', 'actor:id,name,email,role']);
        if ($request->filled('q')) {
            $term = trim((string) $request->query('q'));
            $query->where(fn($q) => $q->where('ip_address', 'like', "%{$term}%")
                ->orWhere('account_hint', 'like', "%{$term}%")
                ->orWhere('action', 'like', "%{$term}%")
                ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")));
        }
        if (in_array($request->query('event'), ['login', 'logout', 'hotel_panel_action', 'account_action'], true)) $query->where('event_type', $request->query('event'));
        if (in_array($request->query('result'), ['success', 'failed', 'blocked'], true)) $query->where('result', $request->query('result'));
        $events = $query->latest('occurred_at')->paginate(50)->withQueryString();
        $stats = [
            'failed_24h' => SecurityEvent::where('event_type', 'login')->whereIn('result', ['failed', 'blocked'])->where('occurred_at', '>=', now()->subDay())->count(),
            'logins_24h' => SecurityEvent::where('event_type', 'login')->where('result', 'success')->where('occurred_at', '>=', now()->subDay())->count(),
            'actions_24h' => SecurityEvent::whereIn('event_type', ['hotel_panel_action', 'account_action'])->where('occurred_at', '>=', now()->subDay())->count(),
        ];
        return view('admin.security', compact('events', 'stats'));
    }
}
