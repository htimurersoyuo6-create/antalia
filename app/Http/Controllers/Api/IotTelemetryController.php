<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IotDevice;
use App\Services\IotAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IotTelemetryController extends Controller
{
    public function store(Request $request, IotAlertService $service): JsonResponse
    {
        $data = $request->validate([
            'device_uid' => 'required|string|max:80',
            'token' => 'required|string|max:120',
            'metric' => 'required|string|max:32',
            'value' => 'required|numeric',
            'recorded_at' => 'nullable|date',
            'meta' => 'nullable|array',
        ]);

        $device = IotDevice::query()
            ->where('device_uid', $data['device_uid'])
            ->where('is_active', true)
            ->first();

        if (! $device || ! hash_equals($device->token_hash, hash('sha256', $data['token']))) {
            return response()->json(['error' => 'invalid_device', 'message' => 'Cihaz doğrulanamadı.'], 401);
        }

        $log = $service->ingest($device, $data['metric'], (float) $data['value'], $data['meta'] ?? [], $data['recorded_at'] ?? null);

        return response()->json([
            'data' => [
                'id' => $log->id,
                'metric' => $log->metric,
                'value' => (float) $log->value,
                'breaching' => $device->isBreaching((float) $data['value']),
            ],
        ], 201);
    }
}
