<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\LoginHistory;
use App\Services\DeviceNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileSecurityController extends Controller
{
    public function devices(Request $request): JsonResponse
    {
        $devices = DeviceToken::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->get(['id', 'platform', 'device_name', 'app_version', 'last_used_at', 'revoked_at', 'created_at']);

        return response()->json(['devices' => $devices]);
    }

    public function registerDevice(Request $request, DeviceNotificationService $service): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:191'],
            'platform' => ['nullable', 'in:ios,android,web,unknown'],
            'device_id' => ['nullable', 'string', 'max:64'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'app_version' => ['nullable', 'string', 'max:32'],
            'os_version' => ['nullable', 'string', 'max:32'],
        ]);

        $device = $service->register($request->user(), $data, $request->ip());

        return response()->json(['ok' => true, 'device_id' => $device->id], 201);
    }

    public function revokeDevice(Request $request, int $device, DeviceNotificationService $service): JsonResponse
    {
        $service->revoke($request->user(), $device);

        return response()->json(['ok' => true]);
    }

    public function revokeOthers(Request $request, DeviceNotificationService $service): JsonResponse
    {
        $except = $request->input('token');
        $count = $service->revokeAllExcept($request->user(), is_string($except) ? $except : null);

        return response()->json(['ok' => true, 'revoked' => $count]);
    }

    public function loginHistory(Request $request): JsonResponse
    {
        $history = LoginHistory::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get(['ip', 'guard', 'successful', 'reason', 'user_agent', 'created_at']);

        return response()->json(['history' => $history]);
    }
}
