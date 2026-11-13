<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MobileApiController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:80'],
        ]);

        $user = \App\Models\User::query()->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Kimlik bilgileri hatalı.']);
        }

        if (! method_exists($user, 'createToken')) {
            return response()->json(['error' => 'api_tokens_unavailable'], 503);
        }

        $token = $user->createToken($data['device_name'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
        ]);
    }

    public function reservations(Request $request): JsonResponse
    {
        $items = Reservation::query()
            ->with(['hotel:id,name,slug,city_id', 'pet:id,name,species'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('check_in')
            ->limit(50)
            ->get()
            ->map(fn (Reservation $reservation) => [
                'code' => $reservation->code,
                'status' => $reservation->status,
                'hotel' => $reservation->hotel?->name,
                'pet' => $reservation->pet?->name,
                'check_in' => $reservation->check_in?->toDateString(),
                'check_out' => $reservation->check_out?->toDateString(),
                'total' => (float) $reservation->total_price,
                'payment_status' => $reservation->payment_status,
            ]);

        return response()->json(['data' => $items]);
    }

    public function pets(Request $request): JsonResponse
    {
        $items = $request->user()->pets()
            ->orderBy('name')
            ->get(['id', 'name', 'species', 'breed', 'microchip_number', 'vaccinated'])
            ->map(fn ($pet) => [
                'id' => $pet->id,
                'name' => $pet->name,
                'species' => $pet->species,
                'breed' => $pet->breed,
                'microchip_number' => $pet->microchip_number,
                'vaccinated' => (bool) $pet->vaccinated,
            ]);

        return response()->json(['data' => $items]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }
}
