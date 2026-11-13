<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class DeviceNotificationService
{
    public function register(User $user, array $data, ?string $ip = null): DeviceToken
    {
        $token = trim((string) ($data['token'] ?? ''));

        if ($token === '') {
            abort(422, 'device token gerekli');
        }

        $device = DeviceToken::query()->updateOrCreate(
            ['token' => $token],
            [
                'user_id' => $user->id,
                'platform' => in_array($data['platform'] ?? null, ['ios', 'android', 'web'], true) ? $data['platform'] : 'unknown',
                'device_id' => $data['device_id'] ?? null,
                'device_name' => $data['device_name'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'os_version' => $data['os_version'] ?? null,
                'last_ip' => $ip,
                'last_used_at' => now(),
                'revoked_at' => null,
            ]
        );

        Log::info('device_token.registered', ['user_id' => $user->id, 'platform' => $device->platform, 'device_id' => $device->device_id]);

        return $device;
    }

    public function revoke(User $user, int $id): void
    {
        DeviceToken::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        Log::info('device_token.revoked', ['user_id' => $user->id, 'device_token_id' => $id]);
    }

    public function revokeAllExcept(User $user, ?string $exceptToken = null): int
    {
        $query = DeviceToken::query()->active()->where('user_id', $user->id);

        if ($exceptToken) {
            $query->where('token', '!=', $exceptToken);
        }

        $count = $query->update(['revoked_at' => now()]);

        if ($count > 0) {
            Log::info('device_token.bulk_revoked', ['user_id' => $user->id, 'count' => $count]);
        }

        return $count;
    }

    public function sendToUser(User $user, string $title, string $body, array $data = []): int
    {
        $tokens = DeviceToken::query()->active()->where('user_id', $user->id)->pluck('token')->all();

        if ($tokens === []) {
            return 0;
        }

        Log::info('push.dispatch.logged', [
            'user_id' => $user->id,
            'count' => count($tokens),
            'title' => mb_substr($title, 0, 80),
            'body' => mb_substr($body, 0, 120),
            'data_keys' => array_keys($data),
        ]);

        return count($tokens);
    }
}
