<?php
declare(strict_types=1);
namespace App\Services;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;
class VaccinationCardOcrService
{
    public function store(UploadedFile $file): array
    {
        $path = $file->store('vaccination-cards/'.date('Y/m'), 'public');
        return [
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => (string) $file->getClientMimeType(),
            'size' => (int) $file->getSize(),
        ];
    }
    public function extract(array $card): array
    {
        $result = [
            'provider' => (string) config('services.ocr.provider', 'none'),
            'extracted_at' => now()->toIso8601String(),
            'vaccines' => [],
            'raw_text' => null,
            'confidence' => null,
        ];
        $endpoint = config('services.ocr.endpoint');
        if (! $endpoint || empty($card['path'])) {
            return $result;
        }
        try {
            $file = Storage::disk('public')->path($card['path']);
            $response = Http::timeout(45)
                ->withToken((string) config('services.ocr.token'))
                ->attach('file', fopen($file, 'r'), $card['original_name'])
                ->post($endpoint);
            if (! $response->successful()) {
                Log::warning('ocr.request_failed', ['status' => $response->status(), 'body' => $response->body()]);
                return $result;
            }
            $payload = $response->json() ?? [];
            $result['raw_text'] = $payload['text'] ?? null;
            $result['confidence'] = $payload['confidence'] ?? null;
            $result['vaccines'] = $this->normalizeVaccines($payload['vaccines'] ?? $payload['items'] ?? []);
        } catch (Throwable $e) {
            Log::error('ocr.exception', ['message' => $e->getMessage()]);
        }
        return $result;
    }
    private function normalizeVaccines(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $type = strtolower((string) ($item['type'] ?? $item['name'] ?? ''));
            $date = $item['date'] ?? $item['administered_at'] ?? null;
            $normalizedType = match (true) {
                str_contains($type, 'kuduz'), str_contains($type, 'rabies') => 'rabies',
                str_contains($type, 'karma'), str_contains($type, 'combined') => 'combined',
                $type !== '' => 'other',
                default => null,
            };
            if (! $normalizedType) {
                continue;
            }
            $out[] = [
                'type' => $normalizedType,
                'label' => (string) ($item['name'] ?? $item['type'] ?? $normalizedType),
                'administered_at' => $this->parseDate($date)?->toDateString(),
                'expires_at' => $this->parseDate($item['expires_at'] ?? null)?->toDateString(),
            ];
        }
        return $out;
    }
    private function parseDate(mixed $value): ?CarbonInterface
    {
        if (! $value) {
            return null;
        }
        try {
            return \Illuminate\Support\Carbon::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }
    public function isExpiringSoon(?CarbonInterface $date, int $days = 30): bool
    {
        return $date !== null && $date->lessThanOrEqualTo(now()->addDays($days));
    }
}