<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\ReservationCall;
use App\Models\ReservationCallSignal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReservationCallController extends Controller
{
    private function enabled(?int $hotelId = null): void
    {
        abort_unless(\App\Support\PlatformModules::enabled('private_calls', $hotelId), 404);
    }

    private function activeStay(Reservation $reservation): bool
    {
        return $reservation->status === 'confirmed'
            && $reservation->check_in->lte(today())
            && $reservation->check_out->gte(today());
    }

    private function authorizeReservation(Request $request, Reservation $reservation): void
    {
        $userId = (int) $request->user()->id;
        $isGuest = (int) $reservation->user_id === $userId;
        $isOwner = (int) $reservation->hotel->owner_id === $userId;
        abort_unless($isGuest || $isOwner, 404);
        abort_unless($this->activeStay($reservation), 404);
    }

    private function participant(Request $request, ReservationCall $call): void
    {
        $userId = (int) $request->user()->id;
        abort_unless(in_array($userId, [(int) $call->caller_id, (int) $call->recipient_id], true), 404);
        abort_unless($call->reservation && in_array($userId, [(int) $call->reservation->user_id, (int) $call->reservation->hotel->owner_id], true), 404);
    }

    public function start(Request $request, Reservation $reservation)
    {
        $this->enabled((int) $reservation->hotel_id);
        $this->authorizeReservation($request, $reservation);
        $data = $request->validate(['media_type' => ['required', 'in:audio,video'], 'notice_read' => ['required', 'accepted'], 'recording_consent' => ['nullable', 'boolean']]);
        $userId = (int) $request->user()->id;
        $recipientId = (int) $reservation->user_id === $userId
            ? (int) $reservation->hotel->owner_id
            : (int) $reservation->user_id;

        return DB::transaction(function () use ($reservation, $userId, $recipientId, $data) {
            Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $existing = ReservationCall::query()->where('reservation_id', $reservation->id)
                ->whereIn('status', ['ringing', 'accepted'])->where('expires_at', '>', now())->first();
            if ($existing) return response()->json(['message' => 'Bu rezervasyon için zaten bir arama sürüyor.'], 409);
            $call = ReservationCall::create([
                'uuid' => (string) Str::uuid(), 'reservation_id' => $reservation->id,
                'caller_id' => $userId, 'recipient_id' => $recipientId,
                'media_type' => $data['media_type'], 'status' => 'ringing', 'caller_consented_at' => ($data['recording_consent'] ?? false) ? now() : null, 'caller_notice_at' => now(), 'expires_at' => now()->addMinutes(2),
            ]);
            return response()->json(['call_id' => $call->uuid, 'status' => $call->status, 'expires_at' => $call->expires_at->toIso8601String()] + \App\Support\WebRtcIceServers::forUser(auth()->user()), 201)
                ->header('Cache-Control', 'no-store, private');
        });
    }

    public function active(Request $request, Reservation $reservation)
    {
        $this->enabled((int) $reservation->hotel_id);
        $this->authorizeReservation($request, $reservation);
        $userId = (int) $request->user()->id;
        $call = ReservationCall::query()->where('reservation_id', $reservation->id)
            ->whereIn('status', ['ringing', 'accepted'])->where('expires_at', '>', now())
            ->where(fn($q) => $q->where('caller_id', $userId)->orWhere('recipient_id', $userId))
            ->latest('id')->first();
        return response()->json(['call_id' => $call?->uuid])->header('Cache-Control', 'no-store, private');
    }

    public function poll(Request $request, ReservationCall $call)
    {
        $this->enabled((int) $call->reservation->hotel_id);
        $this->participant($request, $call);
        if (in_array($call->status, ['ringing', 'accepted'], true) && !$this->activeStay($call->reservation)) {
            $call->update(['status' => 'ended', 'ended_at' => now()]);
        }
        if ($call->status === 'ringing' && $call->expires_at->isPast()) {
            $call->update(['status' => 'missed', 'ended_at' => now()]);
        }
        if ($call->status === 'accepted' && $call->expires_at->isPast()) {
            $call->update(['status' => 'ended', 'ended_at' => now()]);
        }
        $after = max(0, (int) $request->query('after', 0));
        $signals = $call->signals()->where('to_user_id', $request->user()->id)->where('id', '>', $after)->orderBy('id')->limit(100)->get()->map(function ($signal) {
            $payload = null;
            if ($signal->payload_encrypted) {
                try { $payload = json_decode(Crypt::decryptString($signal->payload_encrypted), true); } catch (\Throwable $e) { report($e); }
            }
            return ['id' => $signal->id, 'type' => $signal->signal_type, 'payload' => $payload];
        });

        return response()->json([
            'id' => $call->uuid, 'status' => $call->status, 'media_type' => $call->media_type,
            'caller_id' => (int) $call->caller_id, 'current_user_id' => (int) $request->user()->id,
            'recording_allowed' => (bool) ($call->caller_consented_at && $call->recipient_consented_at),
            'expires_at' => $call->expires_at->toIso8601String(), 'signals' => $signals,
            'recording_ready' => (bool) ($call->recording && $call->recording->status === 'ready' && $call->recording->expires_at->isFuture()),
        ] + \App\Support\WebRtcIceServers::forUser($request->user()))->header('Cache-Control', 'no-store, private');
    }

    public function signal(Request $request, ReservationCall $call)
    {
        $this->enabled((int) $call->reservation->hotel_id);
        $this->participant($request, $call);
        abort_unless(in_array($call->status, ['ringing', 'accepted'], true) && $call->expires_at->isFuture(), 410);
        $data = $request->validate([
            'type' => ['required', 'in:accept,reject,offer,answer,ice,end'],
            'payload' => ['nullable', 'array'],
        ]);
        $type = $data['type'];
        $from = (int) $request->user()->id;
        $to = $from === (int) $call->caller_id ? (int) $call->recipient_id : (int) $call->caller_id;

        $payload = $data['payload'] ?? null;
        if (in_array($type, ['accept', 'reject'], true)) abort_unless($from === (int) $call->recipient_id, 403);
        if (!in_array($type, ['end', 'reject'], true)) abort_unless($this->activeStay($call->reservation), 410);
        if ($type === 'offer') abort_unless($from === (int) $call->caller_id && $call->status === 'accepted', 403);
        if ($type === 'answer') abort_unless($from === (int) $call->recipient_id && $call->status === 'accepted', 403);
        if ($type === 'ice') abort_unless($call->status === 'accepted', 403);

        if ($type === 'accept') {
            abort_unless(is_array($payload) && ($payload['notice_read'] ?? false) === true, 422, 'Arama bilgilendirmesini onaylamalısınız.');
            $recipientRecordingConsent = in_array($payload['recording_consent'] ?? false, [true, 1, '1'], true);
            $call->update(['status' => 'accepted', 'recipient_consented_at' => $recipientRecordingConsent ? now() : null, 'recipient_notice_at' => now(), 'answered_at' => now(), 'expires_at' => now()->addHours(2)]);
        }
        if ($type === 'reject') $call->update(['status' => 'rejected', 'ended_at' => now()]);
        if ($type === 'end') {
            $call->update(['status' => 'ended', 'ended_at' => now()]);
        }

        if ($payload !== null) {
            $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            abort_if($encoded === false || strlen($encoded) > 60000, 422, 'Arama sinyali boyutu sınırı aşıyor.');
        }
        ReservationCallSignal::create([
            'call_id' => $call->id, 'from_user_id' => $from, 'to_user_id' => $to,
            'signal_type' => $type,
            'payload_encrypted' => $payload === null ? null : Crypt::encryptString($encoded),
        ]);
        return response()->json(['ok' => true])->header('Cache-Control', 'no-store, private');
    }

    public function uploadChunk(Request $request, ReservationCall $call)
    {
        $this->enabled((int) $call->reservation->hotel_id);
        $this->participant($request, $call);
        abort_unless((int) $request->user()->id === (int) $call->caller_id, 403);
        abort_unless($call->status === 'accepted' && $call->caller_consented_at && $call->recipient_consented_at, 403);
        $data = $request->validate(['sequence' => ['required', 'integer', 'min:0', 'max:20000'], 'chunk' => ['required', 'file', 'max:1024']]);
        $recording = $call->recording()->firstOrCreate(['call_id' => $call->id], [
            'recorder_user_id' => $call->caller_id, 'status' => 'recording',
            'mime_type' => $data['chunk']->getMimeType() ?: 'video/webm',
            'expires_at' => now()->addDays(max(7, min(365, (int) \App\Models\Setting::al('call_recording_days', '30')))),
        ]);
        abort_unless((int) $recording->recorder_user_id === (int) $request->user()->id && $recording->status === 'recording', 403);
        abort_unless($call->status === 'accepted' || ($call->status === 'ended' && $call->ended_at?->gt(now()->subSeconds(45))), 410);
        abort_unless((int) $data['sequence'] === (int) $recording->chunk_count, 409, 'Kayıt parçası sırası uyuşmuyor.');
        $contents = file_get_contents($data['chunk']->getRealPath());
        if ($contents === false) {
            Log::error('Private call recording chunk could not be read', ['call_uuid' => $call->uuid, 'sequence' => (int) $data['sequence']]);
            abort(500, 'Kayıt parçası okunamadı.');
        }
        abort_if($recording->byte_size + strlen($contents) > 1073741824, 413, 'Arama kaydı 1 GB sınırına ulaştı.');
        $directory = 'call-recordings/'.$call->uuid;
        $path = $directory.'/'.str_pad((string) $data['sequence'], 8, '0', STR_PAD_LEFT).'.enc';
        try {
            $stored = Storage::disk('local')->put($path, Crypt::encryptString(base64_encode($contents)));
        } catch (\Throwable $e) {
            Log::error('Private call recording chunk storage failed', ['call_uuid' => $call->uuid, 'sequence' => (int) $data['sequence'], 'exception' => get_class($e)]);
            throw $e;
        }
        if (!$stored) {
            Log::error('Private call recording chunk storage returned false', ['call_uuid' => $call->uuid, 'sequence' => (int) $data['sequence']]);
            abort(500, 'Kayıt parçası güvenli depoya aktarılamadı.');
        }
        $recording->update([
            'chunk_count' => $recording->chunk_count + 1,
            'byte_size' => $recording->byte_size + strlen($contents),
            'started_at' => $recording->started_at ?? now(),
            'mime_type' => $data['chunk']->getMimeType() ?: $recording->mime_type,
        ]);
        return response()->json(['ok' => true, 'sequence' => (int) $data['sequence']])->header('Cache-Control', 'no-store, private');
    }

    public function finishRecording(Request $request, ReservationCall $call)
    {
        $this->enabled((int) $call->reservation->hotel_id);
        $this->participant($request, $call);
        abort_unless((int) $request->user()->id === (int) $call->caller_id, 403);
        $recording = $call->recording;
        abort_unless($recording && $recording->chunk_count > 0 && $recording->status === 'recording', 404);
        abort_unless(in_array($call->status, ['accepted', 'ended'], true), 410);
        $recording->update(['status' => 'ready', 'finished_at' => now()]);
        Log::info('Private call recording finalized', ['call_uuid' => $call->uuid, 'chunks' => (int) $recording->chunk_count, 'bytes' => (int) $recording->byte_size]);
        return response()->json(['ok' => true])->header('Cache-Control', 'no-store, private');
    }

    public function download(Request $request, ReservationCall $call)
    {
        $this->enabled((int) $call->reservation->hotel_id);
        $this->participant($request, $call);
        $recording = $call->recording;
        abort_unless($recording && $recording->status === 'ready' && $recording->chunk_count > 0 && $recording->expires_at->isFuture(), 404);
        $directory = 'call-recordings/'.$call->uuid;
        $chunks = (int) $recording->chunk_count;
        return response()->streamDownload(function () use ($directory, $chunks) {
            for ($sequence = 0; $sequence < $chunks; $sequence++) {
                $path = $directory.'/'.str_pad((string) $sequence, 8, '0', STR_PAD_LEFT).'.enc';
                if (!Storage::disk('local')->exists($path)) continue;
                try {
                    $plain = base64_decode(Crypt::decryptString(Storage::disk('local')->get($path)), true);
                    if ($plain !== false) echo $plain;
                } catch (\Throwable $e) { report($e); }
            }
        }, 'rezervasyon-arama-'.$call->uuid.'.webm', [
            'Content-Type' => $recording->mime_type ?: 'video/webm',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
