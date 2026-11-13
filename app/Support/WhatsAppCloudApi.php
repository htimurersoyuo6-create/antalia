<?php

namespace App\Support;

use App\Models\ReservationInvoice;
use App\Models\WhatsAppCloudSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class WhatsAppCloudApi
{
    private static function baseUrl(WhatsAppCloudSetting $settings): string
    {
        $version = preg_replace('/[^v0-9.]/', '', (string) ($settings->api_version ?: 'v23.0'));
        return 'https://graph.facebook.com/'.($version ?: 'v23.0');
    }

    public static function checkConnection(): array
    {
        $settings = WhatsAppCloudSetting::current();
        if (!filled($settings->phone_number_id) || !filled($settings->access_token)) {
            throw new RuntimeException('Önce telefon numarası kimliğini ve erişim anahtarını kaydedin.');
        }

        $response = Http::withToken($settings->access_token)->acceptJson()->timeout(15)
            ->get(self::baseUrl($settings).'/'.rawurlencode($settings->phone_number_id), [
                'fields' => 'display_phone_number,verified_name,quality_rating',
            ]);

        if (!$response->successful()) {
            $message = (string) data_get($response->json(), 'error.message', 'Meta bağlantısı doğrulanamadı.');
            throw new RuntimeException(mb_substr($message, 0, 240));
        }

        return $response->json();
    }

    public static function sendInvoiceDocument(ReservationInvoice $invoice, string $recipient): string
    {
        $settings = WhatsAppCloudSetting::current();
        if (!filled($settings->phone_number_id) || !filled($settings->access_token)) {
            throw new RuntimeException('WhatsApp Cloud API ayarları eksik.');
        }
        if (!filled($settings->invoice_template)) {
            throw new RuntimeException('Önce Meta’da onaylı, belge başlıklı fatura şablonunun adını kaydedin.');
        }
        if (!$invoice->isIssued() || $invoice->document_mime !== 'application/pdf') {
            throw new RuntimeException('WhatsApp üzerinden yalnızca düzenlenmiş PDF faturalar gönderilebilir.');
        }

        $digits = \App\Support\PhoneNumber::whatsapp($recipient);
        if (strlen($digits) < 10 || strlen($digits) > 15) {
            throw new RuntimeException('Müşterinin WhatsApp için geçerli telefon numarası bulunamadı.');
        }

        $path = Storage::disk('local')->path($invoice->document_path);
        if (!is_file($path)) throw new RuntimeException('Fatura dosyası sunucuda bulunamadı.');

        $base = self::baseUrl($settings);
        $media = Http::withToken($settings->access_token)->acceptJson()->timeout(30)
            ->attach('file', file_get_contents($path), 'fatura-'.$invoice->invoice_number.'.pdf', ['Content-Type' => 'application/pdf'])
            ->post($base.'/'.rawurlencode($settings->phone_number_id).'/media', ['messaging_product' => 'whatsapp']);

        if (!$media->successful() || !filled($media->json('id'))) {
            $message = (string) data_get($media->json(), 'error.message', 'Fatura WhatsApp’a yüklenemedi.');
            throw new RuntimeException(mb_substr($message, 0, 240));
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $digits,
            'type' => 'template',
            'template' => [
                'name' => $settings->invoice_template,
                'language' => ['code' => $settings->template_language ?: 'tr_TR'],
                'components' => [
                    ['type' => 'header', 'parameters' => [[
                        'type' => 'document',
                        'document' => ['id' => $media->json('id'), 'filename' => 'fatura-'.$invoice->invoice_number.'.pdf'],
                    ]]],
                    ['type' => 'body', 'parameters' => [
                        ['type' => 'text', 'text' => (string) $invoice->reservation->code],
                        ['type' => 'text', 'text' => (string) $invoice->invoice_number],
                    ]],
                ],
            ],
        ];

        $sent = Http::withToken($settings->access_token)->acceptJson()->timeout(20)
            ->post($base.'/'.rawurlencode($settings->phone_number_id).'/messages', $payload);

        if (!$sent->successful()) {
            $message = (string) data_get($sent->json(), 'error.message', 'WhatsApp mesajı gönderilemedi.');
            throw new RuntimeException(mb_substr($message, 0, 240));
        }

        return (string) data_get($sent->json(), 'messages.0.id', '');
    }

    public static function queueReservationNotifications(int $reservationId, string $event): void
    {
        app()->terminating(function () use ($reservationId, $event) {
            try { self::sendReservationNotifications($reservationId, $event); }
            catch (\Throwable $e) { report($e); }
        });
    }

    private static function sendReservationNotifications(int $reservationId, string $event): void
    {
        $settings = WhatsAppCloudSetting::current();
        if (!filled($settings->phone_number_id) || !filled($settings->access_token)) return;
        $reservation = \App\Models\Reservation::with(['hotel','user','room','pet'])->find($reservationId);
        if (!$reservation) return;
        $hotel = $reservation->hotel;
        $events = [];
        if ($event === 'requested') {
            $hotelPhone = $hotel->whatsapp ?: $hotel->phone;
            if (filled($hotelPhone)) $events[] = ['event'=>'reservation_requested_hotel','type'=>'hotel','phone'=>$hotelPhone,'template'=>$settings->reservation_hotel_template];
            if ($reservation->whatsapp_consent_at && filled($reservation->user?->phone)) $events[] = ['event'=>'reservation_requested_customer','type'=>'customer','phone'=>$reservation->user->phone,'template'=>$settings->reservation_customer_template];
        } elseif ($event === 'confirmed' && $reservation->whatsapp_consent_at && filled($reservation->user?->phone)) {
            $events[] = ['event'=>'reservation_confirmed_customer','type'=>'customer','phone'=>$reservation->user->phone,'template'=>$settings->reservation_confirmed_template];
        }
        foreach ($events as $item) {
            if (!filled($item['template'])) continue;
            $digits = \App\Support\PhoneNumber::whatsapp($item['phone']);
            if (strlen($digits) < 10 || strlen($digits) > 15) continue;
            $row = \App\Models\ReservationWhatsAppNotification::firstOrCreate(
                ['reservation_id'=>$reservation->id,'event'=>$item['event'],'recipient_type'=>$item['type']],
                ['recipient'=>$digits,'status'=>'pending']
            );
            if ($row->status === 'sent') continue;
            $row->update(['recipient'=>$digits,'status'=>'sending','error'=>null]);
            try {
                $terms = trim(strip_tags(html_entity_decode((string) $hotel->policies, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                $terms = preg_replace('/\s+/u',' ',$terms) ?: '';
                if ($terms === '') $terms = 'Tesis kuralları ve iptal koşulları için işletmeyle iletişime geçin.';
                $petType = match($reservation->pet?->species) {'dog'=>'Köpek','cat'=>'Kedi',default=>'Evcil hayvan'};
                $roomName = (string) ($reservation->room?->name ?: 'Oda bilgisi');
                $period = $reservation->check_in->format('d.m.Y').' - '.$reservation->check_out->format('d.m.Y');
                $vars = [$item['type']==='hotel'?(string)$reservation->user?->name:(string)$reservation->user?->name,(string)$hotel->name,(string)$reservation->code,$period,$petType.' · '.$roomName,number_format((float)$reservation->total_price,2,',','.').' TL',mb_substr($terms,0,900)];
                $base = self::baseUrl($settings);
                $response = Http::withToken($settings->access_token)->acceptJson()->timeout(20)->post($base.'/'.rawurlencode($settings->phone_number_id).'/messages',[
                    'messaging_product'=>'whatsapp','to'=>$digits,'type'=>'template','template'=>[
                        'name'=>$item['template'],'language'=>['code'=>$settings->template_language ?: 'tr_TR'],
                        'components'=>[['type'=>'body','parameters'=>array_map(fn($text)=>['type'=>'text','text'=>$text],$vars)]]
                    ]
                ]);
                if (!$response->successful()) throw new RuntimeException(mb_substr((string)data_get($response->json(),'error.message','Meta mesajı kabul etmedi.'),0,240));
                $row->update(['status'=>'sent','message_id'=>(string)data_get($response->json(),'messages.0.id',''),'sent_at'=>now()]);
            } catch (\Throwable $e) {
                $row->update(['status'=>'failed','error'=>mb_substr($e->getMessage(),0,1000)]);
                report($e);
            }
        }
    }
}
