<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Adds rel="nofollow" to external website links across rendered HTML. */
class ExternalWebsiteNofollow
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $contentType = strtolower((string) $response->headers->get('Content-Type', ''));

        if (!str_contains($contentType, 'text/html') || !method_exists($response, 'getContent')) {
            return $response;
        }

        $html = $response->getContent();
        if (!is_string($html) || $html === '') {
            return $response;
        }

        $firstPartyHost = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        $firstPartyHost = $this->normalizeHost($firstPartyHost !== '' ? $firstPartyHost : $request->getHost());
        $dofollowHosts = ['bodrumpetotel.com', 'ankarapetotel.com', 'istanbulpetotelleri.com', 'xpetotel.com'];

        $html = preg_replace_callback('/<a\b[^>]*>/i', function (array $match) use ($firstPartyHost, $dofollowHosts): string {
            $tag = $match[0];

            if (!preg_match('/\bhref\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag, $hrefMatch)) {
                return $tag;
            }

            $href = html_entity_decode((string) ($hrefMatch[1] ?? $hrefMatch[2] ?? $hrefMatch[3] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $href = trim($href);

            if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, '/')) {
                // Protocol-relative URLs begin with // and are external website links.
                if (!str_starts_with($href, '//')) {
                    return $tag;
                }
            }

            $scheme = strtolower((string) parse_url(str_starts_with($href, '//') ? 'https:' . $href : $href, PHP_URL_SCHEME));
            if (!in_array($scheme, ['http', 'https'], true)) {
                return $tag;
            }

            $urlHost = $this->normalizeHost((string) parse_url(str_starts_with($href, '//') ? 'https:' . $href : $href, PHP_URL_HOST));
            if ($urlHost === '' || $urlHost === $firstPartyHost || in_array($urlHost, $dofollowHosts, true)) {
                return $tag;
            }

            $relPattern = '/\brel\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i';
            if (preg_match($relPattern, $tag, $relMatch)) {
                $relValue = trim((string) ($relMatch[1] ?? $relMatch[2] ?? $relMatch[3] ?? ''));
                if (preg_match('/\bnofollow\b/i', $relValue)) {
                    return $tag;
                }

                $newRel = trim($relValue . ' nofollow');
                return (string) preg_replace_callback($relPattern, static fn () => 'rel="' . e($newRel) . '"', $tag, 1);
            }

            return substr($tag, 0, -1) . ' rel="nofollow">';
        }, $html);

        if (is_string($html)) {
            $response->setContent($html);
        }

        return $response;
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(rtrim(trim($host), '.'));
        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
