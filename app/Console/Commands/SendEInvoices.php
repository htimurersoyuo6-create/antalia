<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ReservationInvoice;
use App\Services\EInvoiceService;
use Illuminate\Console\Command;
use Throwable;

class SendEInvoices extends Command
{
    protected $signature = 'efatura:send {invoice? : Fatura kimliği} {--retry : Başarısız faturaları yeniden dene}';

    protected $description = 'Kesilmiş faturaları e-Fatura sağlayıcısına gönderir';

    public function handle(EInvoiceService $service): int
    {
        $query = ReservationInvoice::query()->whereNotNull('invoice_number');

        if ($id = $this->argument('invoice')) {
            $query->whereKey((int) $id);
        } elseif ($this->option('retry')) {
            $query->whereIn('e_invoice_status', ['failed', 'none']);
        } else {
            $query->where('e_invoice_status', 'none');
        }

        $sent = 0;
        $failed = 0;

        $query->orderBy('id')->limit(200)->each(function (ReservationInvoice $invoice) use ($service, &$sent, &$failed): void {
            try {
                $service->send($invoice);
                $sent++;
            } catch (Throwable $e) {
                $failed++;
                $this->error('Fatura '.$invoice->id.': '.$e->getMessage());
            }
        });

        $this->info("gonderilen: {$sent} · hatali: {$failed}");

        return self::SUCCESS;
    }
}
