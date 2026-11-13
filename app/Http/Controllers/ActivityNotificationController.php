<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\HotelChatMessage;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ActivityNotificationController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $sinceValue = $request->session()->get('apo_activity_cursor');
        $now = now();
        $previousUserId = $request->session()->get('apo_activity_user_id');
        if ((int) $previousUserId !== (int) $user->id) $sinceValue = null;
        $request->session()->put('apo_activity_user_id', $user->id);
        $request->session()->put('apo_activity_cursor', $now->toIso8601String());
        $incomingCall = null;
        if (!$user->isAdmin() && \App\Support\PlatformModules::enabled('private_calls')) {
            $call = \App\Models\ReservationCall::query()->where('recipient_id', $user->id)->where('status', 'ringing')
                ->where('expires_at', '>', now())->whereHas('reservation.hotel', fn ($hotel) => $hotel->whereIn('id', \App\Models\Hotel::query()->pluck('id')->filter(fn ($id) => \App\Support\PlatformModules::enabled('private_calls', (int) $id))) )->with('reservation:id,code')->latest('id')->first();
            if ($call) $incomingCall = [
                'url' => $user->isOwner() ? route('panel.reservations.show', $call->reservation) : route('reservations.show', $call->reservation),
                'label' => ($call->media_type === 'video' ? 'Görüntülü' : 'Sesli') . ' arama · Rezervasyon ' . $call->reservation->code,
            ];
        }
        if (!$sinceValue) return response()->json(['messages' => 0, 'reservations' => 0, 'stay_updates' => 0, 'incoming_call' => $incomingCall]);

        $since = Carbon::parse($sinceValue);
        $messages = HotelChatMessage::query()
            ->where('created_at', '>', $since)
            ->whereHas('chat', function ($chat) use ($user) {
                if ($user->isAdmin()) return;
                $chat->where(function ($scope) use ($user) {
                    $scope->where('user_id', $user->id)
                        ->orWhereHas('hotel', fn ($hotel) => $hotel->where('owner_id', $user->id));
                });
            })
            ->whereIn('sender_type', $user->isAdmin() ? ['customer', 'guest', 'owner'] : ($user->isOwner() ? ['customer', 'guest', 'admin'] : ['owner', 'admin']))
            ->count();

        $reservations = 0;
        $stayUpdates = 0;
        if ($user->isAdmin()) {
            $reservations = Reservation::where('status', 'pending')->where('created_at', '>', $since)->count();
        } elseif ($user->isOwner()) {
            $reservations = Reservation::where('status', 'pending')->where('created_at', '>', $since)
                ->whereHas('hotel', fn ($hotel) => $hotel->where('owner_id', $user->id))->count();
        } else {
            $stayUpdates = \App\Models\ReservationUpdate::query()->where('created_at', '>', $since)
                ->whereHas('reservation', fn ($reservation) => $reservation->where('user_id', $user->id))->count();
        }

        return response()->json(['messages' => $messages, 'reservations' => $reservations, 'stay_updates' => $stayUpdates, 'incoming_call' => $incomingCall])
            ->header('Cache-Control', 'no-store, private');
    }
}
