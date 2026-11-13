<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Services\SmartAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantApiController extends Controller
{
    public function answerFromWeb(Request $request, SmartAssistantService $assistant): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'hotel_slug' => ['nullable', 'string', 'max:255'],
        ]);

        $hotel = null;
        if (filled($data['hotel_slug'] ?? null)) {
            $hotel = Hotel::query()
                ->where('slug', $data['hotel_slug'])
                ->where('status', 'active')
                ->first();
            abort_if(! $hotel, 404, 'Tesis bulunamadı.');
        }

        $result = $assistant->answer($hotel, $data['question'], $request->user(), 'web');

        return response()->json([
            'answer' => $result['answer'],
            'confidence' => $result['confidence'],
            'handoff' => $result['handoff'],
        ]);
    }

    public function answer(Request $request, SmartAssistantService $assistant): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:2000'],
            'hotel_id' => ['nullable', 'integer', 'exists:hotels,id'],
        ]);

        $hotel = ! empty($data['hotel_id'])
            ? Hotel::query()->where('id', (int) $data['hotel_id'])->where('status', 'active')->first()
            : null;

        $result = $assistant->answer($hotel, $data['question'], $request->user(), 'mobile');

        return response()->json([
            'answer' => $result['answer'],
            'confidence' => $result['confidence'],
            'handoff' => $result['handoff'],
        ]);
    }
}
