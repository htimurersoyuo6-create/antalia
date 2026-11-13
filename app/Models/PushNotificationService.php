<?php

namespace App\Models;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription as WebPushSubscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class PushNotificationService
{
    public static function sendIncomingCall(ReservationCall $call): void
    {
        try {
            require_once __DIR__ . '/PushVendor/vendor/autoload.php';
            $credentials = DB::table('web_push_credentials')->where('id', 1)->first();
            if (!$credentials) return;

            $reservation = $call->reservation()->with('hotel')->first();
            $recipient = User::find($call->recipient_id);
            if (!$reservation || !$recipient) return;

            $subscriptions = PushSubscription::where('user_id', $recipient->id)->get();

            $push = new WebPush([
                'VAPID' => [
                    'subject' => $credentials->subject,
                    'publicKey' => $credentials->public_key,
                    'privateKey' => Crypt::decryptString($credentials->private_key_encrypted),
                ],
            ], ['TTL' => 120, 'urgency' => 'high'], new \GuzzleHttp\Client([
                'timeout' => 5,
                'connect_timeout' => 2,
                'allow_redirects' => false,
            ]), new \GuzzleHttp\Psr7\HttpFactory(), new \GuzzleHttp\Psr7\HttpFactory());

            $media = $call->media_type === 'video' ? 'görüntülü' : 'sesli';
            $url = $recipient->isOwner()
                ? route('panel.reservations.show', $reservation) . '#apo-private-call'
                : route('reservations.show', $reservation) . '#apo-private-call';
            $payloadData = [
                'title' => 'Yeni ' . $media . ' arama',
                'body' => $recipient->isOwner()
                    ? 'Müşteriniz rezervasyon için sizi arıyor. Görüşmek için dokunun.'
                    : 'Tesisiniz sizinle görüşmek istiyor. Kabul veya reddetmek için dokunun.',
                'url' => $url,
                'tag' => 'apo-call-' . $call->uuid,
                'icon' => \App\Models\Setting::al('site_logo')
                    ? '/storage/' . \App\Models\Setting::al('site_logo')
                    : '/img/paw-placeholder.svg',
            ];

            FcmPushService::sendTo($recipient, $payloadData);
            if ($subscriptions->isEmpty()) return;

            $payload = json_encode($payloadData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            foreach ($subscriptions as $saved) {
                try {
                    $subscription = WebPushSubscription::create([
                        'endpoint' => Crypt::decryptString($saved->endpoint_encrypted),
                        'publicKey' => $saved->p256dh,
                        'authToken' => Crypt::decryptString($saved->auth_key_encrypted),
                        'contentEncoding' => $saved->content_encoding ?: 'aes128gcm',
                    ]);
                    $report = $push->sendOneNotification($subscription, $payload, ['TTL' => 120, 'urgency' => 'high']);
                    if ($report->isSuccess()) {
                        $saved->forceFill(['last_sent_at' => now()])->save();
                    } elseif ($report->isSubscriptionExpired()) {
                        $saved->delete();
                    } else {
                        Log::warning('Web push delivery failed.', ['user_id' => $recipient->id]);
                    }
                } catch (Throwable $error) {
                    Log::warning('Web push delivery failed.', [
                        'user_id' => $recipient->id,
                        'exception' => get_class($error),
                    ]);
                }
            }
        } catch (Throwable $error) {
            Log::warning('Incoming call push could not be sent.', [
                'call_id' => $call->id,
                'exception' => get_class($error),
            ]);
        }
    }
}
