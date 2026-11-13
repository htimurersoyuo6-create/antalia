<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrackPublicSiteVisit
{
    private const PRIVATE_PREFIXES = [
        'panel', 'yonetim', 'giris', 'kayit', 'cikis', 'hesap', 'rezervasyonlarim',
        'evcil-hayvanlarim', 'pet-saglik-belgesi', 'pet-asi-takvimi', 'sohbet',
        'sohbetlerim', 'sepet', 'odeme', 'api', 'storage', 'up',
    ];

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if (!$this->shouldTrack($request, $response)) return $response;

        try {
            $date = today()->toDateString();
            $path = '/'.trim($request->path(), '/');
            if ($path === '/') $path = '/';
            $this->increment($date, 'all', 'all');
            $this->increment($date, 'path', mb_substr($path, 0, 191));
            foreach ((array) $request->attributes->get('analytics_search_result_slugs', []) as $slug) {
                $this->increment($date, 'search_result', mb_substr((string) $slug, 0, 191));
            }
            $this->increment($date, 'device', $this->device($request->userAgent()));
            $hour=now()->format('H:00');$device=$this->device($request->userAgent());
            $this->increment($date,'hour',$hour);
            $hotelSlug=$request->attributes->get('analytics_hotel_slug');
            if(!$hotelSlug&&preg_match('~^pet-otel/([a-z0-9-]+)$~i',trim($request->path(),'/'),$m))$hotelSlug=$m[1];
            if($hotelSlug){
                $slug=mb_substr(strtolower((string)$hotelSlug),0,150);$this->increment($date,'hotel_view',$slug);$this->increment($date,'hotel_hour',$slug.'|'.$hour);$this->increment($date,'hotel_device',$slug.'|'.$device);
                $src=$this->source($request);if($src!==null)$this->increment($date,'hotel_source',$slug.'|'.mb_substr($src,0,35));
            }

            $source = $this->source($request);
            if ($source !== null) $this->increment($date, 'source', mb_substr($source, 0, 191));
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $response;
    }

    private function shouldTrack(Request $request, $response): bool
    {
        if (!$request->isMethod('GET') || $request->user()) return false;
        if ($request->header('DNT') === '1' || $request->header('Sec-GPC') === '1') return false;
        if (!str_contains(strtolower((string) $request->header('Accept')), 'text/html')) return false;
        if (method_exists($response, 'getStatusCode') && $response->getStatusCode() !== 200) return false;
        if (!str_starts_with(strtolower((string) $response->headers->get('Content-Type')), 'text/html')) return false;

        $userAgent = strtolower((string) $request->userAgent());
        if ($userAgent === '' || preg_match('/bot|crawler|spider|slurp|headless|preview|monitor|uptime|feedfetcher/i', $userAgent)) return false;

        $path = trim($request->path(), '/');
        if ($path === '') return true;
        $first = explode('/', $path)[0];
        return !in_array($first, self::PRIVATE_PREFIXES, true);
    }

    private function increment(string $date, string $dimension, string $label): void
    {
        $now = now();
        DB::table('site_analytics_daily')->insertOrIgnore([
            'stat_date' => $date,
            'dimension' => $dimension,
            'label' => $label,
            'page_views' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('site_analytics_daily')->where('stat_date', $date)
            ->where('dimension', $dimension)->where('label', $label)->increment('page_views');
    }

    private function source(Request $request): ?string
    {
        $referer = (string) $request->header('Referer', '');
        if ($referer === '') return 'Doğrudan';
        $host = strtolower((string) parse_url($referer, PHP_URL_HOST));
        if ($host === '') return null;
        $siteHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if ($host === $siteHost || $host === 'www.'.$siteHost) return null;
        return preg_match('/^[a-z0-9.-]{1,180}$/', $host) ? $host : 'Diğer dış kaynak';
    }

    private function device(?string $userAgent): string
    {
        $ua = strtolower((string) $userAgent);
        if (preg_match('/ipad|tablet|kindle|silk/', $ua)) return 'Tablet';
        if (preg_match('/mobile|iphone|android/', $ua)) return 'Mobil';
        return 'Masaüstü';
    }
}
