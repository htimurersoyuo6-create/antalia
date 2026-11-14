<?php

namespace App\Http\Controllers;

use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\User;

class ProfileController extends Controller
{
    public function show(string $profile)
    {
        $user = User::where('username', $profile)->firstOrFail();

        $published = function ($q) {
            $q->where('status', 'active')
              ->where(function ($w) {
                  $w->whereNull('publish_at')->orWhere('publish_at', '<=', now());
              });
        };

        $topics = ForumTopic::where('user_id', $user->id)
            ->tap($published)
            ->withCount(['replies' => fn ($x) => $x->where('status', 'active')])
            ->orderByRaw('COALESCE(publish_at, created_at) DESC')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $stats = [
            'topics' => ForumTopic::where('user_id', $user->id)->tap($published)->count(),
            'replies' => ForumReply::where('user_id', $user->id)
                ->where('status', 'active')->count(),
        ];

        return view('profile.show', [
            'profile' => $user,
            'topics' => $topics,
            'stats' => $stats,
        ]);
    }
}
