<?php

namespace App\Http\Controllers;

use App\Models\Pet;
use App\Models\PetSocialComment;
use App\Models\PetSocialPost;
use App\Models\PetSocialPostLike;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class PetSocialFeedController extends Controller
{
    public function index(Request $request)
    {
        $posts = PetSocialPost::publiclyVisible()->with(['user:id,name', 'pet:id,name,species'])
            ->withCount(['likes', 'comments'])->with(['comments' => fn ($q) => $q->with('user:id,name')->latest()->limit(3)])
            ->latest()->paginate(18)->withQueryString();
        $myPets = $request->user() ? Pet::where('user_id', $request->user()->id)->orderBy('name')->get(['id', 'name']) : collect();
        $likedIds = $request->user() ? PetSocialPostLike::where('user_id', $request->user()->id)->whereIn('pet_social_post_id', $posts->pluck('id'))->pluck('pet_social_post_id')->all() : [];

        return view('pet-social.index', compact('posts', 'myPets', 'likedIds'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'pet_id' => ['nullable', 'integer', 'exists:pets,id'],
            'caption' => ['nullable', 'string', 'max:500'],
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'public_consent' => ['accepted'],
        ]);
        if (!empty($data['pet_id'])) {
            abort_unless(Pet::whereKey($data['pet_id'])->where('user_id', $request->user()->id)->exists(), 404);
        }
        $path = $request->file('photo')->store('pet-social', 'public');
        PetSocialPost::create([
            'user_id' => $request->user()->id,
            'pet_id' => $data['pet_id'] ?? null,
            'photo_path' => $path,
            'caption' => trim((string) ($data['caption'] ?? '')) ?: null,
            'public_consent_at' => now(),
            'status' => 'pending',
        ]);

        return back()->with('status', 'Paylaşımınız moderasyon kuyruğuna gönderildi. Onaylanınca akışta görünür.');
    }

    public function toggleLike(Request $request, PetSocialPost $post)
    {
        abort_unless($post->status === 'approved' && $post->public_consent_at, 404);
        DB::transaction(function () use ($request, $post) {
            $like = PetSocialPostLike::where('pet_social_post_id', $post->id)->where('user_id', $request->user()->id)->lockForUpdate()->first();
            $like ? $like->delete() : PetSocialPostLike::create(['pet_social_post_id' => $post->id, 'user_id' => $request->user()->id]);
        });
        return back();
    }

    public function comment(Request $request, PetSocialPost $post)
    {
        abort_unless($post->status === 'approved' && $post->public_consent_at, 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:500']]);
        PetSocialComment::create(['pet_social_post_id' => $post->id, 'user_id' => $request->user()->id, 'body' => trim($data['body'])]);
        return back()->with('status', 'Yorum eklendi.');
    }

    public function destroy(Request $request, PetSocialPost $post)
    {
        abort_unless((int) $post->user_id === (int) $request->user()->id || $request->user()->isAdmin(), 404);
        Storage::disk('public')->delete($post->photo_path);
        $post->delete();
        return back()->with('status', 'Paylaşım kaldırıldı.');
    }

    public function moderation()
    {
        $posts = PetSocialPost::with(['user:id,name,email', 'pet:id,name'])->latest()->paginate(30);
        return view('pet-social.moderation', compact('posts'));
    }

    public function moderate(Request $request, PetSocialPost $post)
    {
        $data = $request->validate(['status' => ['required', 'in:approved,rejected']]);
        $post->update(['status' => $data['status'], 'moderated_by' => $request->user()->id, 'moderated_at' => now()]);
        if ($data['status'] === 'rejected') {
            Storage::disk('public')->delete($post->photo_path);
            $post->delete();
        }
        return back()->with('status', $data['status'] === 'approved' ? 'Paylaşım yayınlandı.' : 'Paylaşım reddedildi ve fotoğrafı silindi.');
    }
}
