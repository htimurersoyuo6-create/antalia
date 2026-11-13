<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Event;
use SocialiteProviders\Apple\Provider as AppleProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        RateLimiter::for('hotel-chat-start', static function (Request $request): Limit {
            $user = $request->user();
            $key = $user
                ? 'user:' . $user->getAuthIdentifier()
                : 'guest:' . $request->getHost() . '|' . $request->ip();

            return Limit::perMinute(8)->by($key);
        });

        Event::listen(SocialiteWasCalled::class, static function (SocialiteWasCalled $event): void {
            $event->extendSocialite('apple', AppleProvider::class);
        });

        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        View::composer('hotels._card', static function ($view): void {
            $hotel = $view->getData()['h'] ?? null;
            $favoriteIds = request()->attributes->get('favorite_hotel_ids');
            if ($favoriteIds === null) {
                $favoriteIds = auth()->check()
                    ? auth()->user()->favoriteHotels()->pluck('hotels.id')->map(static fn ($id) => (int) $id)->all()
                    : [];
                request()->attributes->set('favorite_hotel_ids', $favoriteIds);
            }
            $view->with('isFavorite', $hotel && in_array((int) $hotel->id, $favoriteIds, true));
        });
    }
}
