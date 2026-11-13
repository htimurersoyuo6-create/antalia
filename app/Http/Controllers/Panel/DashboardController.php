<?php
namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\HotelSubscription;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $r)
    {
        $activeSubscription = HotelSubscription::current()->where('requested_by', $r->user()->id)->with('plan')->latest('ends_at')->first();
        $hotels = $r->user()->hotels()->withCount('rooms')->with('rooms:id,hotel_id,base_price,updated_at')->with('rooms.priceRules')->get();
        $hotels->each(function ($hotel) {
            $photos = json_decode((string) $hotel->photos, true);
            $demoCover = filled($hotel->cover_path) && str_contains((string) $hotel->cover_path, 'cover-generated.');
            $hasRealCover = filled($hotel->cover_path) && !$demoCover;
            $hasGalleryPhotos = is_array($photos) && collect($photos)->contains(fn ($photo) => filled($photo) && !str_contains((string) $photo, 'cover-generated.'));
            $hasPhotos = $hasRealCover || $hasGalleryPhotos;
            $steps = [
                ['label' => 'Açıklama', 'done' => filled($hotel->description)],
                ['label' => 'Telefon ve adres', 'done' => filled($hotel->phone) && filled($hotel->address)],
                ['label' => 'Gerçek tesis fotoğrafı', 'done' => $hasPhotos],
                ['label' => 'Oda tipi', 'done' => $hotel->rooms->isNotEmpty()],
                ['label' => 'Oda fiyatı', 'done' => $hotel->rooms->contains(fn ($room) => (float) $room->base_price > 0)],
                ['label' => 'Konaklama kuralları', 'done' => filled($hotel->policies)],
            ];
            $hotel->setAttribute('profile_completion', ['steps' => $steps, 'percent' => (int) round(100 * collect($steps)->where('done', true)->count() / count($steps)), 'demo_cover' => $demoCover]);
            $latestRoomInfoAt = $hotel->rooms->flatMap(fn ($room) => collect([$room->updated_at])->merge($room->priceRules->pluck('updated_at')))->filter()->max();
            $hotel->setAttribute('room_info_updated_at', $latestRoomInfoAt);
            $confirmedAt = $hotel->room_info_confirmed_at;
            $infoConfirmed = $confirmedAt
                && (!$latestRoomInfoAt || $confirmedAt->greaterThanOrEqualTo($latestRoomInfoAt))
                && $confirmedAt->greaterThanOrEqualTo(now()->subDays(30));
            $hotel->setAttribute('room_info_is_confirmed', (bool) $infoConfirmed);
            $hotel->setAttribute('room_info_confirmation_status', !$hotel->rooms->isNotEmpty() ? 'missing'
                : (!$confirmedAt ? 'unconfirmed'
                    : (($latestRoomInfoAt && $latestRoomInfoAt->greaterThan($confirmedAt)) ? 'changed'
                        : ($confirmedAt->lt(now()->subDays(30)) ? 'stale' : 'confirmed'))));
        });
        if (!$activeSubscription && $hotels->isNotEmpty()) {
            $activeSubscription = HotelSubscription::current()->whereIn('hotel_id', $hotels->pluck('id'))->with('plan')->latest('ends_at')->first();
        }
        $canUseLive = $r->user()->isAdmin() || (bool) $activeSubscription;
        // Keep real property and reservation records visible in read-only demo mode.
        // Write routes remain protected by the active-subscription middleware.
        $ids = $hotels->pluck('id');
        $latestSubscriptions = HotelSubscription::whereIn('hotel_id', $ids)->with('plan')->latest()->get()->groupBy('hotel_id')->map(fn ($rows) => $rows->first());
        $overdueInvoices = \App\Models\ReservationInvoice::whereIn('hotel_id',$ids)->where('status','pending')->where('created_at','<=',now()->subDays(7))->count();
        $stats = [
            'pending' => Reservation::whereIn('hotel_id', $ids)->where('status', 'pending')->count(),
            'upcoming' => Reservation::whereIn('hotel_id', $ids)->where('status', 'confirmed')->where('check_in', '>=', today())->count(),
            'in_house' => Reservation::whereIn('hotel_id', $ids)->where('status', 'confirmed')->where('check_in', '<=', today())->where('check_out', '>', today())->count(),
        ];
        $ilis = ['hotel', 'room', 'pet', 'user'];
        $bugun = [
            'giris'  => Reservation::with($ilis)->whereIn('hotel_id', $ids)->where('status', 'confirmed')->whereDate('check_in', today())->get(),
            'cikis'  => Reservation::with($ilis)->whereIn('hotel_id', $ids)->where('status', 'confirmed')->whereDate('check_out', today())->get(),
            'icerde' => Reservation::with($ilis)->whereIn('hotel_id', $ids)->where('status', 'confirmed')->whereDate('check_in', '<', today())->whereDate('check_out', '>', today())->get(),
        ];
        return view('panel.index', compact('hotels', 'stats', 'bugun', 'latestSubscriptions', 'activeSubscription', 'canUseLive', 'overdueInvoices'));
    }

    public function statistics(Request $r)
    {
        $hotels = $r->user()->hotels()->withCount('rooms')->get();
        $hotelTraffic = \Illuminate\Support\Facades\DB::table('site_analytics_daily')->where('dimension', 'hotel_view')->whereDate('stat_date', '>=', today()->subDays(29))->whereIn('label', $hotels->pluck('slug'))->selectRaw('label, sum(page_views) as views')->groupBy('label')->pluck('views', 'label');
        $hotelTrafficToday = \Illuminate\Support\Facades\DB::table('site_analytics_daily')->where('dimension', 'hotel_view')->whereDate('stat_date', today())->whereIn('label', $hotels->pluck('slug'))->selectRaw('label, sum(page_views) as views')->groupBy('label')->pluck('views', 'label');
        $hotelSearchResults = \Illuminate\Support\Facades\DB::table('site_analytics_daily')->where('dimension', 'search_result')->whereDate('stat_date', '>=', today()->subDays(29))->whereIn('label', $hotels->pluck('slug'))->selectRaw('label, sum(page_views) as views')->groupBy('label')->pluck('views', 'label');
        $hotelBookingRequests = \Illuminate\Support\Facades\DB::table('site_analytics_daily')->where('dimension', 'booking_request')->whereDate('stat_date', '>=', today()->subDays(29))->whereIn('label', $hotels->pluck('slug'))->selectRaw('label, sum(page_views) as views')->groupBy('label')->pluck('views', 'label');
        $hotelContactClicks = [];
        foreach (\Illuminate\Support\Facades\DB::table('site_analytics_daily')->where('dimension', 'hotel_click')->whereDate('stat_date', '>=', today()->subDays(29))->selectRaw('label, sum(page_views) as views')->groupBy('label')->get() as $clickRow) {
            [$clickSlug, $clickAction] = array_pad(explode('|', (string) $clickRow->label, 2), 2, '');
            if ($hotels->contains('slug', $clickSlug)) $hotelContactClicks[$clickSlug][$clickAction] = (int) $clickRow->views;
        }
        $hotelTrafficBreakdown = [];
        foreach ($hotels as $hotel) {
            $prefix = $hotel->slug . '|';
            $hotelTrafficBreakdown[$hotel->slug] = [];
            $hotelTrafficBreakdown[$hotel->slug]['Gün'] = \Illuminate\Support\Facades\DB::table('site_analytics_daily')->where('dimension', 'hotel_view')->where('label', $hotel->slug)->whereDate('stat_date', '>=', today()->subDays(13))->orderByDesc('stat_date')->get(['stat_date', 'page_views']);
            foreach (['Saat' => 'hotel_hour', 'Kaynak' => 'hotel_source', 'Cihaz' => 'hotel_device'] as $key => $dimension) {
                $hotelTrafficBreakdown[$hotel->slug][$key] = \Illuminate\Support\Facades\DB::table('site_analytics_daily')->where('dimension', $dimension)->whereDate('stat_date', '>=', today()->subDays(29))->where('label', 'like', $prefix . '%')->selectRaw('SUBSTRING(label, ?) as item, SUM(page_views) as views', [mb_strlen($prefix) + 1])->groupBy('item')->orderByDesc('views')->limit($key === 'Saat' ? 24 : 8)->get();
            }
        }

        return view('panel.statistics', compact('hotels', 'hotelTraffic', 'hotelTrafficToday', 'hotelTrafficBreakdown', 'hotelSearchResults', 'hotelBookingRequests', 'hotelContactClicks'));
    }
}
