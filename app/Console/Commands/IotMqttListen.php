<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\IotDevice;
use App\Services\IotAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use Throwable;

class IotMqttListen extends Command
{
    protected $signature = 'iot:listen {--host=} {--port=1883} {--topic=petotel/+/telemetry}';

    protected $description = 'MQTT üzerinden ESP32 sensör telemetrisini dinler ve eşik aşımı alarmı üretir';

    public function handle(IotAlertService $alert): int
    {
        $host = (string) ($this->option('host') ?: config('services.mqtt.host', '127.0.0.1'));
        $port = (int) ($this->option('port') ?: config('services.mqtt.port', 1883));
        $topic = (string) $this->option('topic');
        $clientId = 'petotel-'.gethostname().'-'.getmypid();

        $settings = (new ConnectionSettings())
            ->setConnectTimeout(10)
            ->setKeepAliveInterval(30)
            ->setReconnectAutomatically(true)
            ->setMaxReconnectAttempts(10);

        $username = (string) config('services.mqtt.username', '');
        $password = (string) config('services.mqtt.password', '');

        if ($username !== '') {
            $settings = $settings->setUsername($username);
        }

        if ($password !== '') {
            $settings = $settings->setPassword($password);
        }

        $this->info("MQTT dinleniyor: {$host}:{$port} · {$topic}");

        try {
            $mqtt = new MqttClient($host, $port, $clientId);
            $mqtt->connect($settings, true);

            $mqtt->subscribe($topic, function (string $receivedTopic, string $message) use ($alert): void {
                $this->handleMessage($alert, $receivedTopic, $message);
            }, 0);

            $mqtt->loop(true);

            $mqtt->disconnect();
        } catch (Throwable $e) {
            Log::error('iot.mqtt_failed', ['message' => $e->getMessage()]);
            $this->error('MQTT bağlantısı başarısız: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function handleMessage(IotAlertService $alert, string $topic, string $message): void
    {
        $segments = explode('/', $topic);
        $deviceUid = $segments[1] ?? null;

        if (! $deviceUid) {
            return;
        }

        $payload = json_decode($message, true);

        if (! is_array($payload) || ! isset($payload['value']) || ! is_numeric($payload['value'])) {
            Log::notice('iot.invalid_payload', ['topic' => $topic]);

            return;
        }

        $device = IotDevice::query()->where('device_uid', $deviceUid)->where('is_active', true)->first();

        if (! $device) {
            Log::notice('iot.unknown_device', ['uid' => $deviceUid]);

            return;
        }

        try {
            $alert->ingest(
                $device,
                (string) ($payload['metric'] ?? $device->type),
                (float) $payload['value'],
                is_array($payload['meta'] ?? null) ? $payload['meta'] : [],
                isset($payload['recorded_at']) ? (string) $payload['recorded_at'] : null,
            );
        } catch (Throwable $e) {
            Log::warning('iot.ingest_failed', ['device' => $device->id, 'message' => $e->getMessage()]);
        }
    }
}
