<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AnonymizeInactiveUsers extends Command
{
    protected $signature = 'privacy:anonymize {--days=730 : Son işlemden bu yana geçmesi gereken gün} {--force : Gerçekten uygula}';

    protected $description = 'KVKK kapsamında, uzun süre işlem yapmayan ve aktif rezervasyonu bulunmayan kullanıcıları anonimleştirir';

    public function handle(): int
    {
        $days = max(90, (int) $this->option('days'));
        $force = (bool) $this->option('force');
        $cutoff = now()->subDays($days);

        $candidates = User::query()
            ->where('role', '!=', 'admin')
            ->where('created_at', '<=', $cutoff)
            ->whereDoesntHave('reservations', function ($query): void {
                $query->whereIn('status', ['pending', 'confirmed'])
                    ->where('check_out', '>=', now()->toDateString());
            })
            ->where(function ($query) use ($cutoff): void {
                $query->whereNull('email_verified_at')->orWhere('email_verified_at', '<=', $cutoff);
            })
            ->orderBy('id')
            ->limit(500)
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('anonimlestirilecek_kullanici: 0');

            return self::SUCCESS;
        }

        $this->info('aday_sayisi: '.$candidates->count());

        if (! $force) {
            $this->warn('Dry-run. Uygulamak için --force kullanın.');

            return self::SUCCESS;
        }

        $done = 0;

        foreach ($candidates as $user) {
            DB::transaction(function () use ($user, &$done): void {
                $user->forceFill([
                    'name' => 'Anonim Misafir '.$user->id,
                    'email' => 'anonim+'.$user->id.'@antalyapetotel.invalid',
                    'phone' => null,
                    'phone_normalized' => null,
                    'password' => Hash::make(Str::random(40)),
                    'remember_token' => null,
                ])->save();

                $done++;
            });
        }

        $this->info('anonimlestirilen: '.$done);

        return self::SUCCESS;
    }
}
