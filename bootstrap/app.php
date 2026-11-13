<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            at: [
                '173.245.48.0/20',
            '103.21.244.0/22',
            '103.22.200.0/22',
            '103.31.4.0/22',
            '141.101.64.0/18',
            '108.162.192.0/18',
            '190.93.240.0/20',
            '188.114.96.0/20',
            '197.234.240.0/22',
            '198.41.128.0/17',
            '162.158.0.0/15',
            '104.16.0.0/13',
            '104.24.0.0/14',
            '172.64.0.0/13',
            '131.0.72.0/22',
            '2400:cb00::/32',
            '2606:4700::/32',
            '2803:f800::/32',
            '2405:b500::/32',
            '2405:8100::/32',
            '2a06:98c0::/29',
            '2c0f:f248::/32',
            ],
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT,
        );
        $middleware->alias(['role' => \App\Http\Middleware\EnsureRole::class, 'owner.subscription' => \App\Http\Middleware\RequireActiveOwnerSubscription::class]);
        $middleware->validateCsrfTokens(except: ['odeme/iyzico/callback', 'odeme/iyzico-magaza/callback', 'odeme/iyzico-uzatma/callback', 'odeme/paytr/abonelik-bildirimi', 'giris/apple/callback', 'analitik/iletisim']);
        $middleware->appendToGroup('web', \App\Http\Middleware\AccountStatus::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\AuditWebActions::class);
        /* Herkese acik GET sayfalari icin onbellek (03.10.2026) */
        $middleware->appendToGroup('web', \App\Http\Middleware\ExternalWebsiteNofollow::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\PublicCache::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\SecurityHeaders::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\TrackPublicSiteVisit::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash('whatsapp_access_token');
        $exceptions->render(function (\Illuminate\Database\QueryException $exception, Request $request) {
            $isAdoptionRoute = $request->is('sahiplendir', 'sahiplendir/*')
                || $request->is('yonetim/sahiplendirme', 'yonetim/sahiplendirme/*');

            if ($exception->getConnectionName() !== 'adoption' || ! $isAdoptionRoute) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json(
                    ['message' => 'Sahiplendirme hizmeti geçici olarak kullanılamıyor.'],
                    503,
                    ['Retry-After' => '300'],
                );
            }

            return response()->view('errors.adoption-unavailable', [], 503, [
                'Retry-After' => '300',
            ]);
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

