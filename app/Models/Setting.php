<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Site ayarlari. Tablo: settings (skey, svalue). Eski sistemden kalan tablo
 * oldugu icin anahtar kolon `skey`, zaman damgasi yok.
 */
class Setting extends Model
{
    protected $table = 'settings';
    protected $primaryKey = 'skey';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];

    public const ALANLAR = [
        'site_name', 'site_tagline', 'meta_title', 'meta_description',
        'home_sections', 'home_hero_prefix', 'home_hero_highlight', 'home_hero_suffix', 'home_hero_description', 'home_hero_image', 'home_hotels_title', 'home_hotels_description', 'site_phone', 'site_email',
        'social_whatsapp', 'site_address', 'social_instagram', 'social_facebook',
        'header_menu', 'footer_description', 'footer_booking_note', 'footer_explore', 'footer_corporate', 'footer_legal', 'footer_copyright', 'subscription_payment_instructions',
        'module_guest_crm', 'module_stay_updates', 'module_live_camera', 'module_private_calls', 'call_recording_days',
    ];

    /** Tum ayarlar (10 dk onbellek). DB'ye ulasilamazsa bos dizi doner; site cokmesin. */
    public static function tum(): array
    {
        try {
            return Cache::remember('site_ayarlari', 600, fn () => static::query()->pluck('svalue', 'skey')->all());
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function al(string $anahtar, ?string $varsayilan = null): ?string
    {
        $v = static::tum()[$anahtar] ?? null;
        return ($v === null || trim((string) $v) === '') ? $varsayilan : (string) $v;
    }

    public static function kaydet(array $degerler): void
    {
        foreach ($degerler as $k => $v) {
            if (!in_array($k, self::ALANLAR, true)) continue;
            static::query()->updateOrInsert(['skey' => $k], ['svalue' => $v === null ? '' : trim((string) $v)]);
        }
        Cache::forget('site_ayarlari');
    }

    /** "0551 192 09 62" / "+90 (551) 192 09 62" -> "+905511920962" */
    public static function telE164(?string $tel = null): string
    {
        $d = preg_replace('/\D+/', '', $tel ?? (string) static::al('site_phone', '05511920962'));
        if (str_starts_with($d, '90')) return '+' . $d;
        if (str_starts_with($d, '0'))  return '+90' . substr($d, 1);
        return '+90' . $d;
    }
}
