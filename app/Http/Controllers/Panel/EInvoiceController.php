<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Jobs\SendEInvoice;
use App\Models\ReservationInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EInvoiceController extends Controller
{
    public function send(Request $request, ReservationInvoice $invoice): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $user->isAdmin() || (int) $invoice->hotel->owner_id === (int) $user->id,
            403
        );

        if (! $invoice->invoice_number) {
            return back()->withErrors(['e_invoice' => 'Önce fatura numarası kesilmelidir.']);
        }

        SendEInvoice::dispatch((int) $invoice->id);

        return back()->with('ok', 'e-Fatura gönderimi kuyruğa alındı.');
    }
}
