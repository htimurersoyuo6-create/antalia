<?php
namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\HotelChat;
use App\Models\HotelChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HotelChatController extends Controller
{
    private function owns(Request $request, Hotel $hotel): bool
    {
        return $request->user()?->isAdmin() || (int) $hotel->owner_id === (int) $request->user()?->id;
    }

    private function visible(Request $request, HotelChat $chat): bool
    {
        $user = $request->user();
        if ($request->routeIs('panel.*')) return $user && $user->isOwner() && (int) $chat->hotel->owner_id === (int) $user->id;
        return ($user && ($user->isAdmin() || (int) $chat->hotel->owner_id === (int) $user->id || (int) $chat->user_id === (int) $user->id))
            || (!$user && $chat->user_id === null && is_string($token = $request->cookie('apo_chat_' . $chat->id) ?? $request->session()->get('private_hotel_chats.' . $chat->id)) && hash_equals((string) $chat->guest_session_hash, hash('sha256', $token)));
    }

    public function create(Request $request, string $slug)
    {
        $hotel = Hotel::where('slug', $slug)->where('status', 'active')->firstOrFail();
        return response()->view('chats.create', compact('hotel'))->header('Cache-Control', 'no-store, private')->header('Vary', 'Cookie');
    }

    public function start(Request $request, string $slug)
    {
        $hotel = Hotel::where('slug', $slug)->where('status', 'active')->firstOrFail();
        if (!\App\Support\Captcha::check($request, 'hotel-chat')) return back()->withErrors(['captcha' => 'Güvenlik kontrolü başarısız. Yeni doğrulamayı tamamlayın.'])->withInput();
        $data = $request->validate(['name' => ($request->user() ? 'nullable' : 'required') . '|string|max:120', 'phone' => 'nullable|string|max:30', 'body' => 'required|string|min:2|max:3000']);
        $user = $request->user();
        $chat = HotelChat::create([
            'hotel_id' => $hotel->id, 'user_id' => $user?->id,
            'guest_session_hash' => $user ? null : hash('sha256', $token = Str::random(64)),
            'guest_name' => $user?->name ?? trim($data['name']), 'guest_phone' => $data['phone'] ?? null,
            'status' => 'open', 'last_message_at' => now(),
        ]);
        if (!$user) $request->session()->put('private_hotel_chats.' . $chat->id, $token);
        $chat->messages()->create(['sender_user_id' => $user?->id, 'sender_type' => $user ? 'customer' : 'guest', 'body' => trim($data['body'])]);
        $response = redirect()->route('hotel-chats.show', $chat)->with('ok', 'Mesajınız özel sohbet olarak iletildi.');
        if (!$user) $response->withCookie(cookie('apo_chat_' . $chat->id, $token, 525600, '/', null, app()->environment('production'), true, false, 'Lax'));
        return $response;
    }

    public function show(Request $request, HotelChat $chat)
    {
        abort_unless($this->visible($request, $chat), 404);
        $chat->load(['hotel:id,name,owner_id', 'user:id,name', 'messages.sender:id,name']);
        // The /panel route is the partner inbox even when an admin account
        // also owns hotels; never render the admin shell from that route.
        $admin = ($request->user()?->isAdmin() ?? false) && !$request->routeIs('panel.*');
        $partnerPanel = $request->routeIs('panel.*');
        return response()->view('chats.show', compact('chat', 'admin', 'partnerPanel'))->header('Cache-Control', 'no-store, private')->header('Vary', 'Cookie');
    }

    public function poll(Request $request, HotelChat $chat)
    {
        abort_unless($this->visible($request, $chat), 404);
        $after = max(0, (int) $request->query('after', 0));
        $rows = $chat->messages()->where('id', '>', $after)->with('sender:id,name')->limit(100)->get();
        return response()->json(['messages' => $rows->map(fn ($m) => [
            'id' => $m->id, 'sender' => $m->sender?->name ?? ($m->sender_type === 'owner' ? 'Tesis yetkilisi' : ($m->sender_type === 'admin' ? 'Antalya Pet Otel' : ($m->sender_type === 'guest' ? $chat->guest_name : 'Müşteri'))),
            'body' => $m->body, 'created_at' => $m->created_at?->format('d.m.Y H:i'), 'mine' => $m->sender_user_id && $m->sender_user_id === $request->user()?->id,
        ])])->header('Cache-Control', 'no-store, private');
    }

    public function send(Request $request, HotelChat $chat)
    {
        abort_unless($this->visible($request, $chat), 404);
        abort_if($chat->status === 'archived', 403, 'Arşivlenmiş sohbete yeni mesaj gönderilemez.');
        $data = $request->validate(['body' => 'required|string|min:1|max:3000']);
        $user = $request->user();
        $sender = $user?->isAdmin() && !$request->routeIs('panel.*') ? 'admin' : (($user && (int) $chat->hotel->owner_id === (int) $user->id) ? 'owner' : ($user ? 'customer' : 'guest'));
        $chat->messages()->create(['sender_user_id' => $user?->id, 'sender_type' => $sender, 'body' => trim($data['body'])]);
        $chat->update(['last_message_at' => now()]);
        return back()->with('ok', 'Mesaj gönderildi.');
    }

    public function archive(Request $request, HotelChat $chat)
    {
        abort_unless($this->visible($request, $chat), 404);
        $chat->update(['status' => 'archived', 'archived_at' => now()]);
        return back()->with('ok', 'Sohbet silinmeden arşive taşındı.');
    }

    public function inbox(Request $request)
    {
        $user = $request->user();
        $partnerContext = $request->routeIs('panel.*');
        $isAdmin = $user->isAdmin() && !$partnerContext;
        $isOwner = $partnerContext ? $user->isOwner() : ($user->isOwner() && !$user->isAdmin());
        $isCustomer = $user->role === 'customer';
        abort_unless($isAdmin || $isOwner || $isCustomer, 403);

        $hotelIds = $isOwner ? Hotel::where('owner_id', $user->id)->pluck('id') : collect();
        $chats = HotelChat::with(['hotel:id,name', 'user:id,name', 'latestMessage'])
            ->when(!$isAdmin, function ($query) use ($isOwner, $hotelIds, $user) {
                return $isOwner
                    ? $query->whereIn('hotel_id', $hotelIds)
                    : $query->where('user_id', $user->id);
            })
            ->when($request->query('arsiv') !== '1', fn ($query) => $query->where('status', 'open'))
            ->when($request->query('arsiv') === '1', fn ($query) => $query->where('status', 'archived'))
            ->orderByDesc('last_message_at')->paginate(30);

        $admin = $isAdmin;
        $partnerPanel = $partnerContext;
        return response()->view('chats.inbox', compact('chats', 'admin', 'partnerPanel'))
            ->header('Cache-Control', 'no-store, private')->header('Vary', 'Cookie');
    }
}
