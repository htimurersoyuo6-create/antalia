<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SmartAssistantService;
use Illuminate\Console\Command;

class SeedAssistantFaqs extends Command
{
    protected $signature = 'assistant:seed-faqs {--hotel= : Tesis kimliği (boşsa genel havuz)}';

    protected $description = 'Akıllı asistan için varsayılan soru-cevap kayıtlarını oluşturur';

    public function handle(SmartAssistantService $assistant): int
    {
        $hotelId = $this->option('hotel') ? (int) $this->option('hotel') : null;
        $created = $assistant->seedDefaults($hotelId);

        $this->info('eklenen_soru_cevap: '.$created);

        return self::SUCCESS;
    }
}
