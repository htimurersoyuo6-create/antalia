<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PartnerHotel;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class IssuePartnerApiKey extends Command
{
    protected $signature = 'partner:issue-key {name : Partner otel adı} {--commission=10 : Komisyon yüzdesi} {--email=} {--city=Antalya}';

    protected $description = 'B2B partner oteli oluşturur ve bir kerelik API anahtarı üretir';

    public function handle(): int
    {
        $name = (string) $this->argument('name');
        $plain = 'ph_'.Str::random(40);

        $partner = PartnerHotel::query()->create([
            'name' => $name,
            'email' => $this->option('email'),
            'city' => (string) $this->option('city'),
            'commission_rate' => (float) $this->option('commission'),
            'api_key_hash' => hash('sha256', $plain),
            'api_key_last4' => substr($plain, -4),
            'is_active' => true,
        ]);

        $this->info('Partner oluşturuldu: #'.$partner->id.' '.$partner->name);
        $this->warn('API anahtarı (yalnızca bir kez gösterilir): '.$plain);

        return self::SUCCESS;
    }
}
