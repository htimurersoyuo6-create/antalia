<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FcmPushService;
use App\Models\IotDevice;
use App\Models\IotTelemetryLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class IotAlertService
{
    public function ingest(IotDevice $device, string $metric, float $value, array $meta = [], ?string $recordedAt = null): IotTelemetryLog
    {
        $log = IotTelemetryLog::create([
            'iot_device_id' => $device->id,
            'hotel_id' => $device->hotel_id,
            'metric' => $metric,
            'value' => $value,
            'unit' => $device->unit,
            'recorded_at' => $recordedAt ? \Illuminate\Support\Carbon::parse($recordedAt) : now(),
            'meta' => $meta,
        ]);

        $device->update(['last_seen_at' => now()]);

        if ($device->isBreaching($value)) {
            $this->alert($device, $metric, $value);
        }

        return $log;
    }

    private function alert(IotDevice $device, string $metric, float $value): void
    {
        $owner = $device->hotel?->owner;

        if (! $owner) {
            return;
        }

        $cacheKey = 'iot.alert.'.$device->id.'.'.$metric;

        if (! Cache::add($cacheKey, true, now()->addMinutes(15))) {
            return;
        }

        try {
            FcmPushService::sendTo($owner, [
                'title' => 'IoT alarmı: '.$device->name,
                'body' => sprintf('%s ölçümü %.2f%s ile eşik değerinin dışında.', ucfirst($metric), $value, $device->unit ? ' '.$device->unit : ''),
                'data' => [
                    'type' => 'iot_alert',
                    'device_id' => (string) $device->id,
                    'hotel_id' => (string) $device->hotel_id,
                    'metric' => $metric,
                    'value' => (string) $value,
                ],
            ]);
        } catch (Throwable $e) {
            Log::warning('iot.alert_failed', ['device' => $device->id, 'message' => $e->getMessage()]);
        }
    }
}
