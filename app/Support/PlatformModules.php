<?php

namespace App\Support;

use App\Models\Setting;

class PlatformModules
{
    public const MODULES = [
        'guest_crm' => ['title' => 'Misafir CRM ve cari', 'description' => 'Tesis bazında müşteri profili, konaklama geçmişi, cari özeti ve işletme notları.'],
        'stay_updates' => ['title' => 'Konaklama durum güncellemeleri', 'description' => 'Aktif konaklamada durum mesajı, bakım raporu, fotoğraf ve video paylaşımı.'],
        'live_camera' => ['title' => 'Rezervasyona özel canlı kamera', 'description' => 'Konaklama süresince müşteri hesabı ve tesise özel erişim koduyla kamera yayını.'],
        'private_calls' => ['title' => 'Özel sesli ve görüntülü arama', 'description' => 'Rezervasyon tarafları arasında izinli bire bir WebRTC görüşmesi ve saklama süresi sınırlı kayıt.'],
    ];

    public static function enabled(string $module, ?int $hotelId = null): bool
    {
        if (!array_key_exists($module, self::MODULES)) return false;
        if (Setting::al('module_' . $module, '1') !== '1') return false;
        if ($hotelId) {
            $override = \App\Models\HotelFeatureFlag::query()->where('hotel_id', $hotelId)->where('module_key', $module)->value('enabled');
            if ($override !== null) return (bool) $override;
        }
        return true;
    }

    public static function all(): array
    {
        return collect(self::MODULES)->map(fn ($definition, $key) => $definition + [
            'key' => $key,
            'enabled' => self::enabled($key),
        ])->all();
    }
}
