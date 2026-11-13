<?php
namespace App\Support;

use App\Models\ContactMessage;
use App\Models\Reservation;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * E-posta bildirimleri.
 *
 * Kural: posta gonderimi HICBIR ZAMAN asil islemi (rezervasyon, iletisim formu)
 * bozmaz. SMTP kapaliysa ya da hata verirse yalnizca log'a uyari yazilir.
 * .env icinde MAIL_MAILER=log iken e-postalar gonderilmez, storage/logs'a yazilir.
 */
class Bildirim
{
    public static function gonder(string|array|null $alici, string $konu, string $view, array $veri = [], ?string $yanitAdresi = null): bool
    {
        $alici = array_values(array_unique(array_filter(
            array_map('strtolower', (array) $alici),
            fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)
        )));
        if (!$alici) return false;

        $veri += ['siteAdi' => Setting::al('site_name', 'Antalya Pet Otel')];
        try {
            Mail::send($view, $veri, function ($m) use ($alici, $konu, $yanitAdresi) {
                $m->to($alici)->subject($konu);
                if ($yanitAdresi && filter_var($yanitAdresi, FILTER_VALIDATE_EMAIL)) $m->replyTo($yanitAdresi);
            });
            return true;
        } catch (\Throwable $e) {
            Log::warning('E-posta gonderilemedi (' . $konu . '): ' . $e->getMessage());
            return false;
        }
    }

    /** Otel sahibi + yonetici (kopya). Tum oteller henuz tek hesapta oldugu icin yonetici de bilgilenir. */
    private static function otelAlicilari(Reservation $res): array
    {
        return array_filter([$res->hotel?->owner?->email, Setting::al('site_email')]);
    }

    public static function rezervasyonAlindi(Reservation $res): bool
    {
        $res->loadMissing(['hotel.owner', 'room', 'pet', 'user', 'extras']);
        $customerSent = self::gonder($res->user->email, 'Rezervasyon talebiniz alındı · ' . $res->code, 'mail.rezervasyon-musteri', ['res' => $res]);
        $hotelSent = self::gonder(self::otelAlicilari($res), 'Yeni rezervasyon talebi · ' . $res->hotel->name, 'mail.rezervasyon-otel',
            ['res' => $res, 'baslik' => 'Yeni rezervasyon talebi'], $res->user->email);

        return $customerSent && $hotelSent && !in_array((string) config('mail.default'), ['log', 'array'], true);
    }

    public static function rezervasyonYanitiHatirlatmasi(Reservation $res): bool
    {
        $res->loadMissing(['hotel.owner', 'room', 'pet', 'user']);
        $alici = self::otelMuhatap($res);
        return self::gonder($alici, 'Yanıt bekleyen rezervasyon talebi · ' . $res->code, 'mail.bildirim', [
            'baslik' => 'Rezervasyon talebine yanıt bekleniyor',
            'metin' => $res->user->name . ' · ' . $res->pet->name . ' · ' . $res->check_in->format('d.m.Y') . '–' . $res->check_out->format('d.m.Y') . ' tarihleri için gönderilen talep 4 saattir yanıt bekliyor.',
            'link' => route('panel.reservations.show', $res), 'linkEtiket' => 'Talebi yanıtla',
        ], $res->user->email);
    }

    public static function rezervasyonIptalEdildi(Reservation $res): void
    {
        $res->loadMissing(['hotel.owner', 'room', 'pet', 'user', 'extras']);
        self::gonder(self::otelAlicilari($res), 'Rezervasyon iptal edildi · ' . $res->code, 'mail.rezervasyon-otel',
            ['res' => $res, 'baslik' => 'Müşteri rezervasyonu iptal etti'], $res->user->email);
    }

    /** Otel onayladi / reddetti / tamamladi -> musteriye. */
    public static function rezervasyonDurum(Reservation $res): void
    {
        $res->loadMissing(['hotel', 'room', 'pet', 'user', 'extras']);
        $etiket = Reservation::STATUS[$res->status] ?? $res->status;
        self::gonder($res->user->email, 'Rezervasyonunuz: ' . $etiket . ' · ' . $res->code, 'mail.rezervasyon-durum', ['res' => $res, 'etiket' => $etiket]);
    }

    /** New marketplace order: buyer gets confirmation and the hotel gets an alert. */
    public static function shopOrderPlaced(\App\Models\ShopOrder $order): void
    {
        $order->loadMissing(['user', 'hotel.owner', 'items']);
        $items = $order->items->map(fn ($item) => $item->name_snapshot . ' × ' . $item->quantity)->join(', ');
        self::gonder($order->user?->email, 'Sipariş alındı · ' . $order->code, 'mail.bildirim', [
            'baslik' => 'Siparişiniz işletmeye iletildi',
            'metin' => $order->hotel->name . ' işletmesine verdiğiniz ' . $order->code . ' numaralı sipariş alındı. Ürünler: ' . $items . '. Toplam: ' . number_format((float) $order->total, 2, ',', '.') . ' ₺. Ödeme teslim sırasında işletmeye yapılır.',
            'link' => route('shop.order', $order),
            'linkEtiket' => 'Siparişimi görüntüle',
        ]);

        $recipients = array_filter([$order->hotel->owner?->email, Setting::al('site_email')]);
        self::gonder($recipients, 'Yeni mağaza siparişi · ' . $order->code, 'mail.bildirim', [
            'baslik' => 'Mağazanıza yeni sipariş geldi',
            'metin' => $order->buyer_name . ' · ' . $order->buyer_phone . ' · ' . $items . ' · ' . number_format((float) $order->total, 2, ',', '.') . ' ₺. Teslimat ve müşteri ayrıntılarını güvenli işletme panelinden inceleyin.',
            'link' => route('panel.shop.seller.orders', $order->hotel),
            'linkEtiket' => 'Siparişi yönet',
        ]);
    }

    /** Let the hotel know when the buyer cancels a still-pending order. */
    public static function shopOrderCancelledByBuyer(\App\Models\ShopOrder $order): void
    {
        $order->loadMissing(['user', 'hotel.owner']);
        $recipients = array_filter([$order->hotel->owner?->email, Setting::al('site_email')]);
        self::gonder($recipients, 'Müşteri siparişi iptal etti · ' . $order->code, 'mail.bildirim', [
            'baslik' => 'Müşteri bekleyen siparişini iptal etti',
            'metin' => $order->user?->name . ' · ' . $order->code . ' numaralı siparişi iptal etti. Ürün stoğu tekrar satışa açıldı.',
            'link' => route('panel.shop.seller.orders', $order->hotel),
            'linkEtiket' => 'Siparişleri yönet',
        ]);
    }

    /** Notify the customer after the hotel changes order progress or confirms collection. */
    public static function shopOrderStatus(\App\Models\ShopOrder $order, string $action): void
    {
        $labels = [
            'confirm' => 'İşletme siparişinizi onayladı.',
            'prepare' => 'Siparişiniz hazırlanıyor.',
            'ship' => 'Siparişiniz kargoya verildi.',
            'deliver' => 'Siparişiniz teslim edildi.',
            'cancel' => 'İşletme siparişinizi iptal etti.',
            'mark_paid' => 'Sipariş ödemeniz işletme tarafından alındı.',
        ];
        if (!isset($labels[$action])) return;
        $order->loadMissing(['user', 'hotel']);
        $text = $labels[$action] . ' Sipariş: ' . $order->code . ' · ' . $order->hotel->name . '.';
        if ($action === 'ship' && $order->carrier) $text .= ' Kargo: ' . $order->carrier . '.';
        if ($action === 'ship' && $order->tracking_code) $text .= ' Takip kodu: ' . $order->tracking_code . '.';
        self::gonder($order->user?->email, 'Sipariş durumu güncellendi · ' . $order->code, 'mail.bildirim', [
            'baslik' => 'Mağaza siparişiniz güncellendi',
            'metin' => $text,
            'link' => route('shop.order', $order),
            'linkEtiket' => 'Sipariş ayrıntılarını görüntüle',
        ]);
    }

    public static function sahiplenmeTalebi(\App\Models\HotelClaim $claim): void
    {
        $claim->loadMissing(['hotel', 'user']);
        $to = Setting::al('site_email');
        if ($to) self::gonder($to, 'Yeni işletme sahiplenme talebi · ' . $claim->hotel->name, 'mail.sahiplenme-talep', ['claim' => $claim], $claim->user->email);
    }

    public static function sahiplenmeSonuc(\App\Models\HotelClaim $claim): void
    {
        $claim->loadMissing(['hotel', 'user']);
        $durum = $claim->status === 'approved' ? 'onaylandı' : 'reddedildi';
        self::gonder($claim->user->email, 'İşletme talebiniz ' . $durum . ' · ' . $claim->hotel->name, 'mail.sahiplenme-sonuc', ['claim' => $claim]);
    }

    /** Otel tarafi muhatap adresi: gercek sahip varsa o, yer tutucu hesaptaysa yonetici. */
    private static function otelMuhatap(Reservation $res): array
    {
        $res->loadMissing('hotel.owner');
        return $res->hotel->sahipsizMi() ? array_filter([Setting::al('site_email')]) : array_filter([$res->hotel->owner?->email]);
    }

    public static function yeniMesaj(Reservation $res, \App\Models\ReservationMessage $msg, string $kimden): void
    {
        $res->loadMissing(['hotel.owner', 'user']);
        if ($kimden === 'musteri') {
            self::gonder(self::otelMuhatap($res), 'Yeni mesaj · ' . $res->code, 'mail.bildirim',
                ['baslik' => 'Misafirden yeni mesaj', 'metin' => $res->user->name . ': ' . $msg->body,
                 'link' => route('panel.reservations.show', $res), 'linkEtiket' => 'Konuşmayı aç'], $res->user->email);
        } else {
            self::gonder($res->user->email, 'Otelden yeni mesaj · ' . $res->code, 'mail.bildirim',
                ['baslik' => $res->hotel->name . ' size mesaj gönderdi', 'metin' => $msg->body,
                 'link' => route('reservations.show', $res), 'linkEtiket' => 'Yanıtla']);
        }
    }

    public static function tutanak(Reservation $res, \App\Models\ReservationRecord $rec): void
    {
        $res->loadMissing(['hotel', 'user']);
        self::gonder($res->user->email, $rec->type_label . ' onayınızı bekliyor · ' . $res->code, 'mail.bildirim',
            ['baslik' => $rec->type_label . ' hazırlandı', 'metin' => $res->hotel->name . ' tesisi ' . mb_strtolower($rec->type_label) . ' hazırladı. Lütfen kontrol edip onaylayın.',
             'link' => route('reservations.show', $res), 'linkEtiket' => 'Tutanağı incele']);
    }

    public static function iletisim(ContactMessage $msg): void
    {
        $to = Setting::al('site_email');
        if ($to) self::gonder($to, 'Yeni iletişim mesajı · ' . $msg->name, 'mail.iletisim', ['msg' => $msg], $msg->email);
    }

    public static function konaklamaGuncellemesi(Reservation $res, \App\Models\ReservationUpdate $update): void
    {
        $res->loadMissing(['hotel', 'user']);
        $metin = $update->body ?: 'İşletme konaklama sürecinizle ilgili yeni bir güncelleme paylaştı.';
        if ($update->photo_path) $metin .= ' Fotoğraf eklendi.';
        if ($update->video_path) $metin .= ' Video eklendi.';
        self::gonder($res->user->email, 'Konaklama güncellemesi · ' . $res->code, 'mail.bildirim', [
            'baslik' => $res->hotel->name . ' konaklamanızla ilgili güncelleme paylaştı',
            'metin' => $update->headline . ' · ' . $metin,
            'link' => route('reservations.show', $res),
            'linkEtiket' => 'Güncellemeyi görüntüle',
        ]);
    }


    public static function misafirDegerlendirildi(Reservation $res, \App\Models\HotelGuestReview $review): void
    {
        $res->loadMissing(['hotel', 'user']);
        $metin = $review->title ?: 'İşletme konaklama deneyiminiz hakkında değerlendirme bıraktı.';
        if ($review->comment) $metin .= ' · ' . $review->comment;
        self::gonder($res->user->email, 'Konaklama değerlendirmesi · ' . $res->code, 'mail.bildirim', [
            'baslik' => $res->hotel->name . ' konaklama deneyiminizi değerlendirdi',
            'metin' => $metin . ' (' . $review->rating . '/5)',
            'link' => route('reservations.show', $res),
            'linkEtiket' => 'Değerlendirmeyi görüntüle',
        ]);
    }


    public static function uzatmaTalebi(Reservation $res, \App\Models\ReservationExtension $extension): void
    {
        $res->loadMissing(['hotel', 'user']);
        self::gonder(self::otelMuhatap($res), 'Rezervasyon uzatma talebi · ' . $res->code, 'mail.bildirim', [
            'baslik' => 'Misafir konaklamayı uzatmak istiyor',
            'metin' => $res->user->name . ' · ' . $extension->current_check_out->format('d.m.Y') . ' yerine ' . $extension->requested_check_out->format('d.m.Y') . ' tarihine kadar kalmak istiyor. Tahmini ek tutar: ' . number_format((float) $extension->quoted_total, 2, ',', '.') . ' ₺.',
            'link' => route('panel.reservations.show', $res),
            'linkEtiket' => 'Talebi incele',
        ]);
    }

    public static function uzatmaDurumu(Reservation $res, \App\Models\ReservationExtension $extension): void
    {
        $res->loadMissing(['hotel', 'user']);
        $accepted = $extension->status === 'approved';
        $metin = $accepted
            ? 'Yeni çıkış tarihiniz ' . $extension->requested_check_out->format('d.m.Y') . ' olarak onaylandı. Ek tutar: ' . number_format((float) $extension->quoted_total, 2, ',', '.') . ' ₺.'
                . ($res->payment_status === 'paid'
                    ? ' Ek ücret işletmenin cari hesabına eklendi; tahsilat işletme panelinden işlenir.'
                    : ' Ek tutar rezervasyon toplamına eklendi; ödeme tesiste alınır.')
            : 'İşletme uzatma talebinizi onaylayamadı.';
        if ($extension->hotel_note) $metin .= ' Not: ' . $extension->hotel_note;
        self::gonder($res->user->email, 'Konaklama uzatma talebi · ' . $res->code, 'mail.bildirim', [
            'baslik' => $accepted ? 'Uzatma talebiniz onaylandı' : 'Uzatma talebiniz yanıtlandı',
            'metin' => $metin,
            'link' => route('reservations.show', $res),
            'linkEtiket' => 'Rezervasyonu görüntüle',
        ]);
    }

}
