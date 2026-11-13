<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\ReservationInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ReservationInvoiceController extends Controller
{
    public function customerIndex(Request $request)
    {
        $invoices = ReservationInvoice::whereHas('reservation', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with(['reservation.hotel'])->latest()->paginate(20);
        return view('invoices.index', compact('invoices'));
    }

    public function customerShow(Request $request, ReservationInvoice $invoice)
    {
        abort_unless((int) $invoice->reservation()->value('user_id') === (int) $request->user()->id, 404);
        return view('invoices.show', ['invoice' => $invoice->load('reservation.hotel')]);
    }

    public function download(Request $request, ReservationInvoice $invoice)
    {
        $invoice->loadMissing(['reservation', 'hotel']);
        $user = $request->user();
        $allowed = $user->isAdmin()
            || (int) $invoice->reservation->user_id === (int) $user->id
            || (int) $invoice->hotel->owner_id === (int) $user->id;
        abort_unless($allowed && $invoice->isIssued(), 404);
        abort_unless(Storage::disk('local')->exists($invoice->document_path), 404);
        $extension = $invoice->document_mime === 'application/pdf' ? 'pdf' : 'xml';
        return Storage::disk('local')->download($invoice->document_path, 'fatura-'.$invoice->invoice_number.'.'.$extension, [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function upload(Request $request, Reservation $reservation)
    {
        abort_unless((int) $reservation->hotel->owner_id === (int) $request->user()->id, 403);
        abort_unless(in_array($reservation->status, ['confirmed', 'completed'], true) && $reservation->payment_status === 'paid', 409, 'Fatura yalnızca ödemesi alınmış ve onaylanmış rezervasyon için eklenebilir.');
        $invoice = ReservationInvoice::ensurePending($reservation);
        abort_if($invoice && $invoice->isIssued(), 409, 'Bu rezervasyon için resmi belge zaten yüklendi. Düzeltme belgesi için mali müşavirinize başvurun.');
        $data = $request->validate([
            'invoice_type' => 'required|in:e_fatura,e_arsiv',
            'invoice_number' => 'required|string|max:100',
            'provider_uuid' => 'nullable|string|max:100',
            'issued_at' => 'required|date|before_or_equal:today',
            'document' => 'required|file|mimes:pdf,xml|max:10240',
        ]);
        $file = $request->file('document');
        $path = $file->store('reservation-invoices/'.$reservation->id, 'local');
        abort_unless($path, 500, 'Fatura dosyası kaydedilemedi.');

        $invoice ??= new ReservationInvoice(['reservation_id' => $reservation->id]);
        $invoice->fill([
            'hotel_id' => $reservation->hotel_id, 'uploaded_by' => $request->user()->id,
            'status' => 'issued', 'invoice_type' => $data['invoice_type'],
            'invoice_number' => trim($data['invoice_number']), 'provider_uuid' => $data['provider_uuid'] ?? null,
            'issued_at' => $data['issued_at'], 'amount' => $reservation->total_price, 'currency' => 'TRY',
            'document_path' => $path, 'document_mime' => $file->getMimeType(),
            'email_status' => 'pending', 'email_error' => null, 'whatsapp_status' => 'not_configured',
        ]);
        $invoice->save();

        $customerEmail = $reservation->user()->value('email');
        if (!$customerEmail) {
            $invoice->update(['email_status' => 'failed', 'email_error' => 'Müşterinin e-posta adresi kayıtlı değil.']);
        } elseif (in_array(config('mail.default'), ['log', 'array'], true)) {
            $invoice->update(['email_status' => 'not_configured', 'email_error' => 'Canlı e-posta servisi yapılandırılmamış.']);
        } else {
            try {
                $absolutePath = Storage::disk('local')->path($path);
                Mail::raw('Merhaba, '.$reservation->code.' numaralı rezervasyonunuza ait resmi fatura belgesi ektedir. Bu ileti otomatik bilgilendirme amaçlıdır.', function ($message) use ($customerEmail, $absolutePath, $data) {
                    $message->to($customerEmail)->subject('Rezervasyon faturanız · '.$data['invoice_number'])
                        ->attach($absolutePath, ['as' => 'fatura-'.$data['invoice_number'].'.'.(str_ends_with(strtolower($absolutePath), '.xml') ? 'xml' : 'pdf')]);
                });
                $invoice->update(['email_status' => 'sent', 'email_sent_at' => now(), 'email_error' => null]);
            } catch (\Throwable $error) {
                report($error);
                $invoice->update(['email_status' => 'failed', 'email_error' => mb_substr($error->getMessage(), 0, 490)]);
            }
        }

        return back()->with('ok', 'Resmi fatura kaydedildi. E-posta durumu: '.($invoice->email_status === 'sent' ? 'gönderildi.' : ($invoice->email_status === 'not_configured' ? 'e-posta servisi yapılandırılmadı.' : 'gönderim bekliyor/başarısız.')));
    }

    public function ownerIndex(Request $request)
    {
        $data=$request->validate(['q'=>'nullable|string|max:120','from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d','status'=>'nullable|in:pending,issued']);
        $hotelIds=$request->user()->hotels()->pluck('id');
        $query=ReservationInvoice::query()->whereIn('hotel_id',$hotelIds)->with(['reservation.user:id,name,email','hotel:id,name']);
        if(!empty($data['q'])){$term=trim($data['q']);$query->where(function($q)use($term){$q->where('invoice_number','like','%'.$term.'%')->orWhereHas('reservation.user',fn($u)=>$u->where('name','like','%'.$term.'%'))->orWhereHas('reservation',fn($r)=>$r->where('code','like','%'.$term.'%'));});}
        if(!empty($data['from']))$query->whereRaw('DATE(COALESCE(issued_at, created_at)) >= ?',[$data['from']]); if(!empty($data['to']))$query->whereRaw('DATE(COALESCE(issued_at, created_at)) <= ?',[$data['to']]); if(!empty($data['status']))$query->where('status',$data['status']);
        $invoices=$query->latest()->paginate(25)->withQueryString();$overdueCount=ReservationInvoice::whereIn('hotel_id',$hotelIds)->where('status','pending')->where('created_at','<=',now()->subDays(7))->count();
        return view('panel.invoices',compact('invoices','overdueCount','data'));
    }

    public function adminIndex()
    {
        $invoices = ReservationInvoice::with(['reservation.user', 'hotel'])->latest()->paginate(30);
        $whatsappStatus = \App\Models\WhatsAppCloudSetting::status();
        $whatsappReady = $whatsappStatus['ready'] && filled($whatsappStatus['invoice_template']);
        return view('admin.invoices', compact('invoices', 'whatsappReady'));
    }
}
