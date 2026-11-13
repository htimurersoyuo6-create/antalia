<?php

namespace App\Http\Controllers;

use App\Models\SecurityEvent;
use Illuminate\Http\Request;

class SecurityController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $events = SecurityEvent::query()->where('user_id', $user->id)->latest('occurred_at')->paginate(40)->withQueryString();
        $lastLogin = SecurityEvent::query()->where('user_id', $user->id)->where('event_type', 'login')->where('result', 'success')->latest('occurred_at')->first();
        $failedCount = SecurityEvent::query()->where('user_id', $user->id)->where('event_type', 'login')->whereIn('result', ['failed', 'blocked'])->where('occurred_at', '>=', now()->subDays(30))->count();
        return view('security.index', compact('events', 'lastLogin', 'failedCount'));
    }
}
