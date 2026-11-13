<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FcmPushService
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private const PROJECT_ID = 'ankara-pet-otel-notifications';

    public static function sendTo(User $recipient, array $message): void
    {
        $subscriptions = FcmSubscription::where('user_id', $recipient->id)->get();
        if ($subscriptions->isEmpty()) return;

        try {
            $accessToken = self::accessToken();
            foreach ($subscriptions as $subscription) {
                try {
                    $token = Crypt::decryptString($subscription->token_encrypted);
                    $response = Http::withToken($accessToken)
                        ->acceptJson()
                        ->timeout(8)
                        ->post('https://fcm.googleapis.com/v1/projects/' . self::PROJECT_ID . '/messages:send', [
                            'message' => [
                                'token' => $token,
                                'data' => [
                                    'title' => (string) ($message['title'] ?? 'Yeni bildiriminiz var'),
                                    'body' => (string) ($message['body'] ?? 'Görüşmek için dokunun.'),
                                    'url' => (string) ($message['url'] ?? '/'),
                                    'tag' => (string) ($message['tag'] ?? 'apo-notification'),
                                    'icon' => (string) ($message['icon'] ?? '/img/paw-placeholder.svg'),
                                    'source' => 'ankara-pet-otel',
                                ],
                                'webpush' => ['headers' => ['TTL' => '120', 'Urgency' => 'high']],
                            ],
                        ]);

                    if ($response->successful()) {
                        $subscription->forceFill(['last_sent_at' => now()])->save();
                        continue;
                    }

                    $errorCode = data_get($response->json(), 'error.details.0.errorCode');
                    if ($response->status() === 404 || $errorCode === 'UNREGISTERED') {
                        $subscription->delete();
                    } else {
                        Log::warning('FCM delivery failed.', [
                            'user_id' => $recipient->id,
                            'status' => $response->status(),
                            'error_code' => $errorCode,
                        ]);
                    }
                } catch (Throwable $error) {
                    Log::warning('FCM delivery failed.', [
                        'user_id' => $recipient->id,
                        'exception' => get_class($error),
                    ]);
                }
            }
        } catch (Throwable $error) {
            Log::error('FCM access token could not be obtained.', ['exception' => get_class($error)]);
        }
    }

    private static function accessToken(): string
    {
        $encryptedToken = Cache::remember('fcm.oauth.access_token.encrypted.ankara', now()->addMinutes(50), function (): string {
            $path = storage_path('app/private/firebase-service-account.json');
            if (!is_file($path) || !is_readable($path)) {
                throw new \RuntimeException('FCM service account is not available.');
            }

            $serviceAccount = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
            if (($serviceAccount['project_id'] ?? null) !== self::PROJECT_ID
                || empty($serviceAccount['client_email'])
                || empty($serviceAccount['private_key'])
                || empty($serviceAccount['token_uri'])) {
                throw new \RuntimeException('FCM service account configuration is invalid.');
            }

            $now = time();
            $encode = static fn (array $data): string => rtrim(strtr(base64_encode(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
            $unsigned = $encode(['alg' => 'RS256', 'typ' => 'JWT']) . '.' . $encode([
                'iss' => $serviceAccount['client_email'],
                'scope' => self::SCOPE,
                'aud' => $serviceAccount['token_uri'],
                'iat' => $now,
                'exp' => $now + 3600,
            ]);

            $privateKey = openssl_pkey_get_private($serviceAccount['private_key']);
            if (!$privateKey || !openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                throw new \RuntimeException('FCM service account signing failed.');
            }

            $assertion = $unsigned . '.' . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
            $response = Http::asForm()->acceptJson()->timeout(10)->post($serviceAccount['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);
            if (!$response->successful() || !$response->json('access_token')) {
                throw new \RuntimeException('FCM OAuth token request failed.');
            }

            return Crypt::encryptString($response->json('access_token'));
        });

        return Crypt::decryptString($encryptedToken);
    }
}
