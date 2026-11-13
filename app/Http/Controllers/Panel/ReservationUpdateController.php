<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ReservationUpdateController extends Controller
{

    public function storeTemplate(Request $request, Reservation $reservation)
    {
        abort_unless((int)$reservation->hotel->owner_id === (int)$request->user()->id,403);
        abort_unless(\App\Support\PlatformModules::enabled('stay_updates',(int)$reservation->hotel_id),404);
        $data=$request->validate(['title'=>'required|string|max:120','body'=>'required|string|max:1000']);
        \Illuminate\Support\Facades\DB::table('hotel_stay_update_templates')->insert(['hotel_id'=>$reservation->hotel_id,'created_by'=>$request->user()->id,'title'=>trim($data['title']),'body'=>trim($data['body']),'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('ok','Hazır yanıt kaydedildi.');
    }

    public function deleteTemplate(Request $request, int $template)
    {
        $row=\Illuminate\Support\Facades\DB::table('hotel_stay_update_templates')->where('id',$template)->first();abort_unless($row,404);
        abort_unless(\App\Models\Hotel::where('id',$row->hotel_id)->where('owner_id',$request->user()->id)->exists(),403);
        \Illuminate\Support\Facades\DB::table('hotel_stay_update_templates')->where('id',$template)->delete();return back()->with('ok','Hazır yanıt silindi.');
    }

    public function store(Request $request, Reservation $reservation)
    {
        abort_unless($reservation->hotel->owner_id === $request->user()->id, 403);
        abort_unless(\App\Support\PlatformModules::enabled('stay_updates', (int) $reservation->hotel_id), 404);

        if ($reservation->status !== 'confirmed'
            || $reservation->check_in->gt(today())
            || $reservation->check_out->lte(today())) {
            return back()->withErrors(['update' => 'Güncelleme yalnızca devam eden konaklama için paylaşılabilir.']);
        }

        $isCareReport = $request->input('update_type') === 'care_report';
        $data = $request->validate([
            'update_type' => ['nullable', 'in:message,care_report'],
            'headline' => [$isCareReport ? 'nullable' : 'required', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:1500'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'video' => ['nullable', 'file', 'mimes:mp4,mov,webm', 'max:20480'],
            'appetite' => ['nullable', 'in:good,partial,poor'],
            'water' => ['nullable', 'in:normal,low,watch'],
            'activity' => ['nullable', 'in:playful,calm,rest'],
            'toilet' => ['nullable', 'in:normal,watch,not_observed'],
            'medication' => ['nullable', 'in:given,not_due,not_planned'],
            'mood' => ['nullable', 'in:happy,energetic,calm,resting,eating'],
        ]);

        if ($isCareReport) {
            $choices = [
                'appetite' => ['İştah' => ['good' => 'İyi', 'partial' => 'Biraz yedi', 'poor' => 'İştahsız']],
                'water' => ['Su tüketimi' => ['normal' => 'Normal', 'low' => 'Az içti', 'watch' => 'Takip edilmeli']],
                'activity' => ['Hareket' => ['playful' => 'Yürüyüş / oyun yaptı', 'calm' => 'Sakin geçti', 'rest' => 'Dinlendi']],
                'toilet' => ['Tuvalet' => ['normal' => 'Normal', 'watch' => 'Takip edilmeli', 'not_observed' => 'Gözlemlenmedi']],
                'medication' => ['İlaç' => ['given' => 'Uygulandı', 'not_due' => 'Saati gelmedi', 'not_planned' => 'İlaç planı yok']],
            ];
            $lines = [];
            $moods=['happy'=>'Keyfi yerinde','energetic'=>'Enerjik','calm'=>'Sakin','resting'=>'Dinleniyor','eating'=>'Yemek yiyor'];
            if (!empty($data['mood'])) $lines[]='Ruh hali: '.$moods[$data['mood']];
            foreach ($choices as $field => $groups) {
                $value = $data[$field] ?? null;
                if ($value === null) continue;
                $label = array_key_first($groups);
                $lines[] = $label . ': ' . ($groups[$label][$value] ?? '');
            }

            if (!$lines && trim((string) ($data['body'] ?? '')) === '' && !$request->hasFile('photo') && !$request->hasFile('video')) {
                return back()->withInput()->withErrors(['care_report' => 'Rapor için en az bir bakım bilgisi, not veya fotoğraf ekleyin.']);
            }

            $data['headline'] = 'Günlük bakım raporu · ' . now()->format('d.m.Y');
            $data['body'] = implode("\n", $lines);
            $dayUpdates=$reservation->updates()->whereDate('created_at',today())->where('headline','not like','Günlük bakım raporu%')->orderBy('created_at')->get(['headline','body']);
            if($dayUpdates->isNotEmpty()){$lines[]='';$lines[]='Gün içinde paylaşılan güncellemeler:';foreach($dayUpdates as $dayUpdate){$lines[]='• '.$dayUpdate->created_at->format('H:i').' · '.$dayUpdate->headline.($dayUpdate->body?' — '.$dayUpdate->body:'');}}
            $data['body'] = implode("\n", $lines);
            if (trim((string) ($request->input('body') ?? '')) !== '') {
                $data['body'] .= ($data['body'] !== '' ? "\n\n" : '') . 'İşletme notu: ' . trim($request->input('body'));
            }
        }

        if ($request->hasFile('photo') && $request->hasFile('video')) {
            return back()->withInput()->withErrors(['media' => 'Her güncellemede fotoğraf veya video dosyalarından yalnızca birini seçin.']);
        }

        $photoPath = null;
        $videoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = \App\Support\ImageOptimizer::storeUploaded($request->file('photo'), 'local', 'reservation-updates/'.$reservation->id, 'photo');
            if (!$photoPath) {
                return back()->withInput()->withErrors(['photo' => 'Fotoğraf güvenli biçimde kaydedilemedi.']);
            }
        }
        if ($request->hasFile('video')) {
            $videoPath = $request->file('video')->store('reservation-updates/'.$reservation->id, 'local');
            if (!$videoPath) {
                return back()->withInput()->withErrors(['video' => 'Video güvenli biçimde kaydedilemedi.']);
            }
        }

        try {
            $update = $reservation->updates()->create([
                'created_by' => $request->user()->id,
                'headline' => trim($data['headline']),
                'body' => isset($data['body']) ? trim($data['body']) : null,
                'photo_path' => $photoPath,
                'video_path' => $videoPath,
                'mood' => $data['mood'] ?? null,
            ]);
        } catch (Throwable $exception) {
            foreach ([$photoPath, $videoPath] as $path) if ($path) Storage::disk('local')->delete($path);
            throw $exception;
        }

        \App\Support\Bildirim::konaklamaGuncellemesi($reservation, $update);

        return back()->with('ok', 'Konaklama güncellemesi misafirle paylaşıldı.');
    }
}
