<?php

namespace App\Models;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PushNotificationController extends Controller
{
    public function serviceWorker()
    {
        $script = <<<'JS'
self.addEventListener('push', event => {
  let data = {};
  try { data = event.data ? event.data.json() : {}; } catch (_) {}
  const title = data.title || 'Antalya Pet Otel';
  const options = {
    body: data.body || 'Yeni bir bildiriminiz var.',
    icon: data.icon || '/img/paw-placeholder.svg',
    badge: '/img/paw-placeholder.svg',
    tag: data.tag || 'apo-notification',
    renotify: true,
    data: { url: data.url || '/' },
    actions: [{ action: 'open', title: 'Görüşmeyi aç' }]
  };
  event.waitUntil(self.registration.showNotification(title, options));
});
self.addEventListener('notificationclick', event => {
  event.notification.close();
  const target = new URL(event.notification.data && event.notification.data.url || '/', self.location.origin);
  if (target.origin !== self.location.origin) target.pathname = '/';
  event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
    for (const client of list) {
      if ('focus' in client) return client.navigate(target.href).then(() => client.focus());
    }
    return clients.openWindow(target.href);
  }));
});
JS;
        $brand = json_encode(\App\Models\Setting::al('site_name', config('app.name', 'Pet Otel')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $script = str_replace("'Antalya Pet Otel'", $brand ?: '"Pet Otel"', $script);
        $logo = \App\Models\Setting::al('site_logo');
        if ($logo) $script = str_replace("'/img/paw-placeholder.svg'", "'/storage/" . str_replace("'", '', $logo) . "'", $script);

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Service-Worker-Allowed' => '/',
            'Cache-Control' => 'no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function fcmServiceWorker()
    {
        $firebaseConfig = json_encode([
            'apiKey' => 'AIzaSyD2juxOU-C4fJusQ00Ln68AyPuQePo9igQ',
            'authDomain' => 'ankara-pet-otel-notifications.firebaseapp.com',
            'projectId' => 'ankara-pet-otel-notifications',
            'storageBucket' => 'ankara-pet-otel-notifications.firebasestorage.app',
            'messagingSenderId' => '156527773540',
            'appId' => '1:156527773540:web:cee3e3c504f06c49c51c71',
        ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $script = <<<'JS'
importScripts('https://www.gstatic.com/firebasejs/12.19.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/12.19.0/firebase-messaging-compat.js');
firebase.initializeApp(__FIREBASE_CONFIG__);
firebase.messaging().onBackgroundMessage(payload => {
  const data = payload.data || {};
  return self.registration.showNotification(data.title || 'Yeni bildiriminiz var', {
    body: data.body || 'Görüşmek için dokunun.',
    icon: data.icon || '/img/paw-placeholder.svg',
    badge: '/img/paw-placeholder.svg',
    tag: data.tag || 'apo-notification',
    renotify: true,
    data: { url: data.url || '/' },
    actions: [{ action: 'open', title: 'Görüşmeyi aç' }]
  });
});
self.addEventListener('notificationclick', event => {
  event.notification.close();
  const target = new URL(event.notification.data && event.notification.data.url || '/', self.location.origin);
  if (target.origin !== self.location.origin) target.pathname = '/';
  event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
    for (const client of list) {
      if ('focus' in client) return client.navigate(target.href).then(() => client.focus());
    }
    return clients.openWindow(target.href);
  }));
});
JS;
        $script = str_replace('__FIREBASE_CONFIG__', $firebaseConfig ?: '{}', $script);
        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Service-Worker-Allowed' => '/',
            'Cache-Control' => 'no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function publicKey(Request $request)
    {
        abort_unless($request->user() && !$request->user()->isAdmin(), 403);
        $key = DB::table('web_push_credentials')->where('id', 1)->value('public_key');
        return response()->json(['public_key' => $key])->header('Cache-Control', 'no-store, private');
    }

    public function fcmConfig(Request $request)
    {
        abort_unless($request->user() && !$request->user()->isAdmin(), 403);
        return response()->json([
            'firebase' => [
                'apiKey' => 'AIzaSyD2juxOU-C4fJusQ00Ln68AyPuQePo9igQ',
                'authDomain' => 'ankara-pet-otel-notifications.firebaseapp.com',
                'projectId' => 'ankara-pet-otel-notifications',
                'storageBucket' => 'ankara-pet-otel-notifications.firebasestorage.app',
                'messagingSenderId' => '156527773540',
                'appId' => '1:156527773540:web:cee3e3c504f06c49c51c71',
            ],
            'vapid_key' => 'BFhBeEuA-ZAYPZIMcBnGwH8VEj972R7_74RmVgM7ihQAfUKmm3AEse3DpANwxRTo3qH74D2JgCzPsThknyoAoMo',
        ])->header('Cache-Control', 'no-store, private');
    }

    public function storeFcm(Request $request)
    {
        abort_unless($request->user() && !$request->user()->isAdmin(), 403);
        $data = $request->validate(['token' => ['required', 'string', 'max:4096']]);
        \App\Models\FcmSubscription::updateOrCreate(
            ['token_hash' => hash('sha256', $data['token'])],
            [
                'user_id' => $request->user()->id,
                'token_encrypted' => Crypt::encryptString($data['token']),
                'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
            ],
        );
        return response()->json(['saved' => true], 201)->header('Cache-Control', 'no-store, private');
    }

    public function destroyFcm(Request $request)
    {
        abort_unless($request->user() && !$request->user()->isAdmin(), 403);
        $data = $request->validate(['token' => ['required', 'string', 'max:4096']]);
        \App\Models\FcmSubscription::where('user_id', $request->user()->id)
            ->where('token_hash', hash('sha256', $data['token']))
            ->delete();
        return response()->json(['removed' => true])->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request)
    {
        abort_unless($request->user() && !$request->user()->isAdmin(), 403);
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'in:aes128gcm,aesgcm'],
        ]);

        $endpoint = $data['endpoint'];
        $parts = parse_url($endpoint);
        $host = strtolower($parts['host'] ?? '');
        $allowedHosts = [
            'fcm.googleapis.com',
            'android.googleapis.com',
            'push.services.mozilla.com',
            'updates.push.services.mozilla.com',
            'web.push.apple.com',
        ];
        $validHost = false;
        foreach ($allowedHosts as $allowed) {
            if ($host === $allowed || Str::endsWith($host, '.' . $allowed)) {
                $validHost = true;
                break;
            }
        }
        abort_unless(($parts['scheme'] ?? '') === 'https' && $validHost, 422, 'Bu bildirim sağlayıcısı desteklenmiyor.');

        PushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $endpoint)],
            [
                'user_id' => $request->user()->id,
                'endpoint_encrypted' => Crypt::encryptString($endpoint),
                'p256dh' => $data['keys']['p256dh'],
                'auth_key_encrypted' => Crypt::encryptString($data['keys']['auth']),
                'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
                'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
            ],
        );

        return response()->json(['saved' => true], 201)->header('Cache-Control', 'no-store, private');
    }

    public function destroy(Request $request)
    {
        abort_unless($request->user() && !$request->user()->isAdmin(), 403);
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2048']]);
        PushSubscription::where('user_id', $request->user()->id)
            ->where('endpoint_hash', hash('sha256', $data['endpoint']))
            ->delete();
        return response()->json(['removed' => true]);
    }
}
