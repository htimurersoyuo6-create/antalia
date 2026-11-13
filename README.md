# Pet Otel – Laravel uygulaması (antalyapetotel.com)

Antalya pet otelleri için rezervasyon platformu: otel arama/listeleme, üyelik, evcil hayvan profilleri,
rezervasyon talebi (müsaitlik + sezon fiyatı), otel paneli, yorumlar, blog, yönetim paneli, sitemap.

## Gereksinimler
- **PHP 8.4+** (composer.lock Symfony 8.1 içerir; PHP 8.3'te `composer install` platform hatası verir)
- MariaDB/MySQL, Composer, `php-fileinfo`, `php-mbstring`

## Kurulum (SSH) — YALNIZCA SIFIRDAN / BOŞ SUNUCUDA

> **UYARI – canlı (production) sitede bu bloğu topluca yapıştırmayın.**
> `cp .env.example .env` mevcut `.env` dosyasını ezer (APP_KEY ve DB şifresi gider),
> `key:generate` oturumları ve şifreli verileri geçersiz kılar,
> `db:seed --class=PetOtelSeeder` canlı veritabanına ÖRNEK veri ekler.
> Canlı güncellemede yalnızca `composer install`, `migrate --force` ve önbellek adımlarını uygulayın.

Sunucuda `php` komutu eski bir sürüme (ör. 8.1) gidebilir; her zaman tam yolu kullanın:
`PHP=/www/server/php/84/bin/php` ve `$PHP /usr/bin/composer install ...` / `$PHP artisan ...`.
Önce `$PHP -v` ile 8.4+ olduğunu doğrulayın.

    PHP=/www/server/php/84/bin/php
    $PHP /usr/bin/composer install --no-dev --optimize-autoloader
    cp .env.example .env && $PHP artisan key:generate        # SADECE ilk kurulumda
    # .env: APP_ENV=production, APP_DEBUG=false, APP_URL=https://antalyapetotel.com,
    #       DB_CONNECTION=mariadb, DB_DATABASE, DB_USERNAME, DB_PASSWORD, APP_LOCALE=tr
    #       Optional separate adoption database: DB_ADOPTION_URL or DB_ADOPTION_HOST,
    #       DB_ADOPTION_PORT, DB_ADOPTION_DATABASE, DB_ADOPTION_USERNAME, DB_ADOPTION_PASSWORD
    $PHP artisan migrate --force
    $PHP artisan db:seed --class=PetOtelSeeder      # SADECE ilk kurulum: ÖRNEK veriler (otel + blog)
    $PHP artisan storage:link                         # otel kapak fotoğrafları için
    $PHP artisan petotel:admin sizin@epostaniz.com    # yönetici hesabı (şifreyi sorar)
    chown -R www:www storage bootstrap/cache && chmod -R 775 storage bootstrap/cache
    $PHP artisan config:cache && $PHP artisan route:cache && $PHP artisan view:cache

Site kökü `public/` klasörü olmalı. `.env` dosyasını asla paylaşmayın / arşive koymayın.

## Zamanlanmış görevler (cron) ve loglar
Laravel scheduler için sunucuda **tek** cron satırı olmalı. PHP yolunu tam yazın; yalın `php`
komutu redis eklentisi olmayan bir PHP'ye gidebilir ve `Class "Redis" not found` hatasıyla
tüm zamanlanmış görevleri durdurur:

    * * * * * cd /www/wwwroot/antalyapetotel.com && /www/server/php/84/bin/php artisan schedule:run >/dev/null 2>&1

- Cron'un kullandığı PHP'de eklenti kontrolü: `/www/server/php/84/bin/php -m | grep -i redis`
- `.env` içinde `LOG_STACK=daily`, `LOG_LEVEL=warning`, `LOG_DAILY_DAYS=7` kullanın (log dosyası diski doldurmasın).
- `schedule:run` çıktısını dosyaya yönlendirmeyin; yönlendirecekseniz logrotate ekleyin.
- `.env` içinde `QUEUE_CONNECTION=database` kullanılıyor; uygulama kodu Redis'e bağlı değil, phpredis eklentisi şart değil
  (yalnızca `QUEUE_CONNECTION`/`CACHE_STORE`/`SESSION_DRIVER` değerlerinden biri `redis` yapılırsa gerekir).
- Kuyruk için cron her dakika kısa ömürlü bir worker başlatır (`/etc/cron.d/antalyapetotel`, `queue:work database --stop-when-empty --max-time=50`, `flock` ile tek kopya).

## Roller ve adresler
- Misafir: `/` `/ara` `/pet-otel/{slug}` `/blog` `/iletisim`
- Müşteri: `/kayit` `/giris` `/evcil-hayvanlarim` `/rezervasyonlarim`
- Otel sahibi: `/partner` (başvuru) → `/panel` (otel bilgisi, odalar, sezon fiyatı, rezervasyon onayı)
- Admin: `/yonetim` (otel onayı + lisans doğrulama, blog yazıları, iletişim mesajları)
- Kayıp pet ilanları: `/kayip-patiler` (yalnızca sahibinin açıkça herkese açtığı ve hâlâ aktif ilanlar)

## Rezervasyon akışı
Müşteri talep oluşturur (`pending`) → otel onaylar/reddeder → konaklama sonrası "Çıkış yaptı" (`completed`)
→ müşteri yorum yazar (yalnızca tamamlanmış rezervasyon). Ödeme şimdilik **otelde** (`pay_at_hotel`);
online ödeme için PayTR/iyzico entegrasyonu `ReservationController@store` sonrasına eklenebilir.

## Test
Testler yerelde / CI'da çalıştırın. Canlı sunucuda **çalıştırmayın**: `config:cache` açıkken `phpunit.xml`'deki
sqlite ayarları devre dışı kalır ve `RefreshDatabase` kullanan testler canlı veritabanını silebilir.

    php artisan config:clear && php artisan test
