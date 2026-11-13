<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Eski sistemden kalan sisen/kullanilmayan verileri temizler.
 *
 * VARSAYILAN: yalnizca ne silinecegini SAYAR (kuru calisma). Gercekten silmek icin --uygula.
 * Once veritabani yedegi alin.
 */
class Temizle extends Command
{
    protected $signature = 'petotel:temizle
        {--uygula : Gercekten sil (verilmezse yalnizca sayar)}
        {--gun=90 : page_views ve search_queries icin saklanacak gun sayisi}';

    protected $description = 'Eski sistemden kalan istatistik, bildirim ve ornek otel verilerini temizler';

    public function handle(): int
    {
        $uygula = (bool) $this->option('uygula');
        $sinir = now()->subDays(max(7, (int) $this->option('gun')));
        $this->info($uygula ? 'SILME MODU' : 'Kuru calisma (hicbir sey silinmez). Silmek icin --uygula ekleyin.');

        foreach (['page_views', 'search_queries'] as $tbl) {
            if (!Schema::hasTable($tbl)) continue;
            $q = DB::table($tbl)->where('created_at', '<', $sinir);
            $this->satir("$tbl ({$sinir->toDateString()} oncesi)", (clone $q)->count(), $uygula ? fn () => $this->parcaliSil($tbl, $sinir) : null);
        }

        if (Schema::hasTable('notifications')) {
            // Laravel uygulamasi bu tabloyu kullanmiyor; icindekiler eski abonelik/odeme sisteminden.
            $tur = ['sub_orphan', 'sub_renewal', 'payment', 'booking'];
            $q = DB::table('notifications')->whereIn('type', $tur);
            $this->satir('notifications (eski sistem turleri)', (clone $q)->count(), $uygula ? fn () => $q->delete() : null);
        }

        $ornek = DB::table('hotels')->where('slug', 'like', 'ornek-%')->where('status', 'pending')
            ->whereNotExists(fn ($s) => $s->select(DB::raw(1))->from('reservations')->whereColumn('reservations.hotel_id', 'hotels.id'));
        $ids = (clone $ornek)->pluck('id');
        $this->satir('ornek oteller (slug ornek-*, pending, rezervasyonsuz)', $ids->count(), $uygula ? function () use ($ids) {
            DB::transaction(function () use ($ids) {
                DB::table('price_rules')->whereIn('room_type_id', DB::table('room_types')->whereIn('hotel_id', $ids)->select('id'))->delete();
                DB::table('room_types')->whereIn('hotel_id', $ids)->delete();
                DB::table('hotels')->whereIn('id', $ids)->delete();
            });
        } : null);

        return self::SUCCESS;
    }

    private function parcaliSil(string $tbl, $sinir): void
    {
        do {
            $n = DB::table($tbl)->where('created_at', '<', $sinir)->limit(5000)->delete();
        } while ($n > 0);
    }

    private function satir(string $ad, int $adet, ?\Closure $sil): void
    {
        if ($sil && $adet > 0) $sil();
        $this->line(sprintf('  %-60s %8d %s', $ad, $adet, $sil ? 'silindi' : 'silinecek'));
    }
}
