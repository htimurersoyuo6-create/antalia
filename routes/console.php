<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command("inspire", function () {
    $this->comment(Illuminate\Foundation\Inspiring::quote());
})->purpose("Display an inspiring quote");

Schedule::command("shop:expire-payments")->everyFiveMinutes()->withoutOverlapping();

// Publish due blog drafts automatically; the command enforces daily limits and spacing.
Schedule::command("petotel:blog-yayinla")
    ->everyFiveMinutes()
    ->between("07:00", "22:00")
    ->withoutOverlapping()
    // Yayin kararlarini (on-time / catch-up / kota) denetlenebilir tut.
    ->appendOutputTo(base_path("logs/blog-publish.log"));

Schedule::command("hotel-subscriptions:expire")->everyFiveMinutes()->withoutOverlapping();

// Privately encrypted WebRTC evidence is deleted after the configurable retention period.
Schedule::call(function () {
    \App\Models\ReservationCallRecording::query()->where("expires_at", "<=", now())->with("call:id,uuid")->chunkById(100, function ($rows) {
        foreach ($rows as $recording) {
            if ($recording->call?->uuid) \Illuminate\Support\Facades\Storage::disk("local")->deleteDirectory("call-recordings/".$recording->call->uuid);
            $recording->delete();
        }
    });
    \App\Models\ReservationCall::query()->where("created_at", "<", now()->subDays(90))->chunkById(100, function ($calls) {
        foreach ($calls as $call) {
            \Illuminate\Support\Facades\Storage::disk("local")->deleteDirectory("call-recordings/".$call->uuid);
            $call->delete();
        }
    });
})->dailyAt("03:20")->name("purge-expired-private-call-data")->withoutOverlapping();

// Retry pending inquiry reminders until email delivery succeeds.
Schedule::command('reservations:remind-pending')
    ->everyFifteenMinutes()
    ->name('remind-hotels-about-pending-inquiries')
    ->withoutOverlapping();

// Retain only aggregate visitor statistics for the latest 25 months.
Schedule::call(function () {
    \Illuminate\Support\Facades\DB::table("site_analytics_daily")->where("stat_date", "<", today()->subMonths(25)->toDateString())->delete();
})->dailyAt("03:35")->name("purge-old-site-analytics")->withoutOverlapping();


// At end of each stay day, deliver one consolidated report containing updates already sent that day.
Schedule::call(function () {
    \App\Models\Reservation::with(["hotel","user","pet"])->where("status","confirmed")->whereDate("check_in","<=",today())->whereDate("check_out",">",today())->chunkById(100,function($reservations){
        foreach($reservations as $reservation){
            $updates=$reservation->updates()->whereDate("created_at",today())->where("headline","not like","Günlük bakım raporu%")->orderBy("created_at")->get(["headline","body","created_at"]);if($updates->isEmpty())continue;
            $updateLines=$updates->map(fn($u)=>$u->created_at->format("H:i")." · ".$u->headline.($u->body?" — ".$u->body:""));
            $body=mb_substr("Gün içinde paylaşılan güncellemeler:\n".$updateLines->implode("\n"),0,12000);
            $report=$reservation->updates()->whereDate("created_at",today())->where("headline","like","Günlük bakım raporu%")->first();
            if($report){$existing=(string)$report->body;$missing=$updateLines->reject(fn($line)=>str_contains($existing,$line));if($missing->isEmpty())continue;$report->update(["body"=>mb_substr(rtrim($existing)."\n".$missing->implode("\n"),0,12000)]);}else{$report=$reservation->updates()->create(["created_by"=>$reservation->hotel->owner_id,"headline"=>"Günlük bakım raporu · ".today()->format("d.m.Y"),"body"=>$body]);}
            \App\Support\Bildirim::konaklamaGuncellemesi($reservation,$report);
        }
    });
})->dailyAt("23:50")->name("daily-care-reports")->withoutOverlapping();

Illuminate\Support\Facades\Schedule::command('pet:tasks-overdue')->everyFiveMinutes();

Illuminate\Support\Facades\Schedule::command('vaccinations:scan-expiry')->dailyAt('09:00');

Illuminate\Support\Facades\Schedule::command('flights:sync-transfers')->everyFifteenMinutes()->withoutOverlapping();
Illuminate\Support\Facades\Schedule::command('images:webp-variants --limit=300')->dailyAt('03:40')->withoutOverlapping();

Illuminate\Support\Facades\Schedule::command('webhooks:retry --limit=100')->everyTenMinutes()->withoutOverlapping();

Illuminate\Support\Facades\Schedule::command('privacy:anonymize --force')->monthlyOn(1, '04:20')->withoutOverlapping();
Illuminate\Support\Facades\Schedule::command('franchise:royalties --issue')->monthlyOn(5, '05:10')->withoutOverlapping();
Illuminate\Support\Facades\Schedule::command('partner:settle --issue')->monthlyOn(6, '05:20')->withoutOverlapping();
Illuminate\Support\Facades\Schedule::command('forecast:demand --days=90')->dailyAt('04:30')->withoutOverlapping();
Illuminate\Support\Facades\Schedule::command('device-tokens:prune --days=30')->dailyAt('03:15')->withoutOverlapping();
