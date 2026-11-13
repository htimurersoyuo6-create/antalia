<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ReservationInvoice;
use App\Services\EInvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendEInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(public int $invoiceId)
    {
    }

    public function handle(EInvoiceService $service): void
    {
        $invoice = ReservationInvoice::query()->find($this->invoiceId);

        if (! $invoice) {
            return;
        }

        $service->send($invoice);
    }
}
