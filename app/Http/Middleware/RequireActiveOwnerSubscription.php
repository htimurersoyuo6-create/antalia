<?php

namespace App\Http\Middleware;

use App\Models\HotelSubscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveOwnerSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user || !$user->isOwner() || $user->isAdmin() || $request->isMethodSafe()) return $next($request);
        if (str_starts_with((string) $request->route()?->getName(), 'panel.subscriptions.')) return $next($request);

        $active = HotelSubscription::current()->where('requested_by', $user->id)->exists();
        if (!$active) {
            $hotelIds = $user->hotels()->pluck('id');
            $active = $hotelIds->isNotEmpty() && HotelSubscription::current()->whereIn('hotel_id', $hotelIds)->exists();
        }
        if (!$active) {
            return redirect()->route('panel.subscriptions')->with('subscription_required', 'Tesis eklemek veya işletmede canlı kayıt oluşturmak için önce ücretli aboneliğinizi etkinleştirin. Demo panelini incelemeye devam edebilirsiniz.');
        }

        return $next($request);
    }
}
