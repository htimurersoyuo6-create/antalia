<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Telefon / WhatsApp tiklamalarini anonim ve toplu olarak sayar.
 * site_analytics_daily tablosuna dimension=hotel_click, label="slug|eylem" yazar.
 * IP, kullanici veya cerez saklanmaz. DNT / GPC, bot ve giris yapmis kullanici sayilmaz.
 */
class AnalyticsController extends Controller
{
    private const ACTIONS = ['phone', 'whatsapp'];

    public function contact(Request $request)
    {
        $empty = response()->noContent();

        if ($request->user() || $request->header('DNT') === '1' || $request->header('Sec-GPC') === '1') return $empty;

        $ua = strtolower((string) $request->userAgent());
        if ($ua === '' || preg_match('/bot|crawler|spider|slurp|headless|preview|monitor|uptime|feedfetcher/i', $ua)) return $empty;

        $site = $request->header('Sec-Fetch-Site');
        if ($site !== null && !in_array($site, ['same-origin', 'same-site', 'none'], true)) return $empty;
        $origin = (string) $request->header('Origin', '');
        if ($origin !== '') {
            $host = strtolower((string) parse_url($origin, PHP_URL_HOST));
            $own = strtolower($request->getHost());
            if ($host !== $own && $host !== 'www.' . $own && 'www.' . $host !== $own) return $empty;
        }

        $slug = strtolower(trim((string) $request->input('hotel', '')));
        $action = strtolower(trim((string) $request->input('action', '')));
        if (!preg_match('/^[a-z0-9-]{1,150}$/', $slug) || !in_array($action, self::ACTIONS, true)) return $empty;

        $known = Cache::remember('analytics.hotel_slugs', now()->addMinutes(10), fn () => Hotel::active()->pluck('slug')->all());
        if (!in_array($slug, $known, true)) return $empty;

        try {
            $now = now();
            $date = today()->toDateString();
            $label = $slug . '|' . $action;
            DB::table('site_analytics_daily')->insertOrIgnore([
                'stat_date' => $date, 'dimension' => 'hotel_click', 'label' => $label,
                'page_views' => 0, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('site_analytics_daily')->where('stat_date', $date)
                ->where('dimension', 'hotel_click')->where('label', $label)->increment('page_views');
        } catch (\Throwable $e) {
            report($e);
        }

        return $empty;
    }
}
