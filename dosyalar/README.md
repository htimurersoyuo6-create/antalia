# Pet Otel – Laravel uygulaması (antalyapetotel.com)

Antalya pet otelleri için rezervasyon platformu: otel arama/listeleme, üyelik, evcil hayvan profilleri,
rezervasyon talebi (müsaitlik + sezon fiyatı), otel paneli, yorumlar, blog, yönetim paneli, sitemap.

## Gereksinimler
- **PHP 8.4+** (composer.lock Symfony 8.1 içerir; PHP 8.3'te `composer install` platform hatası verir)
- MariaDB/MySQL, Composer, `php-fileinfo`, `php-mbstring`

## Kurulum (SSH)
    composer install --no-dev --optimize-autoloader
    cp .env.example .env && php artisan key:generate
    # .env: APP_ENV=production, APP_DEBUG=false, APP_URL=https://antalyapetotel.com,
    #       DB_CONNECTION=mariadb, DB_DATABASE, DB_USERNAME, DB_PASSWORD, APP_LOCALE=tr
    php artisan migrate --force
    php artisan db:seed --class=PetOtelSeeder      # yalnızca ÖRNEK veriler (otel + blog); canlıda gerçek veriyle değiştirin
    php artisan storage:link                         # otel kapak fotoğrafları için
    php artisan petotel:admin sizin@epostaniz.com    # yönetici hesabı (şifreyi sorar)
    chown -R www:www storage bootstrap/cache && chmod -R 775 storage bootstrap/cache
    php artisan config:cache && php artisan route:cache && php artisan view:cache

Site kökü `public/` klasörü olmalı. `.env` dosyasını asla paylaşmayın / arşive koymayın.

## Zamanlanmış görevler (cron) ve loglar
Laravel scheduler için sunucuda **tek** cron satırı olmalı. PHP yolunu tam yazın; yalın `php`
komutu redis eklentisi olmayan bir PHP'ye gidebilir ve `Class "Redis" not found` hatasıyla
tüm zamanlanmış görevleri durdurur:

    * * * * * cd /www/wwwroot/antalyapetotel.com && /www/server/php/84/bin/php artisan schedule:run >/dev/null 2>&1

- Cron'un kullandığı PHP'de eklenti kontrolü: `/www/server/php/84/bin/php -m | grep -i redis`
- `.env` içinde `LOG_STACK=daily`, `LOG_LEVEL=warning`, `LOG_DAILY_DAYS=7` kullanın (log dosyası diski doldurmasın).
- `schedule:run` çıktısını dosyaya yönlendirmeyin; yönlendirecekseniz logrotate ekleyin.
- `QUEUE_CONNECTION=redis` olduğu için kalıcı bir `queue:work` süreci (Supervisor) gerekir.

## Roller ve adresler
- Misafir: `/` `/ara` `/pet-otel/{slug}` `/blog` `/iletisim`
- Müşteri: `/kayit` `/giris` `/evcil-hayvanlarim` `/rezervasyonlarim`
- Otel sahibi: `/partner` (başvuru) → `/panel` (otel bilgisi, odalar, sezon fiyatı, rezervasyon onayı)
- Admin: `/yonetim` (otel onayı + lisans doğrulama, blog yazıları, iletişim mesajları)

## Rezervasyon akışı
Müşteri talep oluşturur (`pending`) → otel onaylar/reddeder → konaklama sonrası "Çıkış yaptı" (`completed`)
→ müşteri yorum yazar (yalnızca tamamlanmış rezervasyon). Ödeme şimdilik **otelde** (`pay_at_hotel`);
online ödeme için PayTR/iyzico entegrasyonu `ReservationController@store` sonrasına eklenebilir.

## Test
    php artisan test
