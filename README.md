# Pet Otel – Laravel uygulaması (antalyapetotel.com)

Antalya pet otelleri için rezervasyon platformu: otel arama/listeleme, üyelik, evcil hayvan profilleri,
rezervasyon talebi (müsaitlik + sezon fiyatı), otel paneli, yorumlar, blog, yönetim paneli, sitemap.

## Arama ve trafik analizi

- `GOOGLE_SITE_VERIFICATION`, Google Search Console doğrulama kodunu herkese açık sayfaların metadata alanına ekler.
- `GOOGLE_ANALYTICS_ID`, isteğe bağlı GA4 ölçüm kodudur (`G-...`). GA4, ziyaretçi analiz çerezlerini açıkça kabul etmeden yüklenmez; giriş yapılmış, yönetici ve otel paneli sayfalarında etiket hiçbir zaman yüklenmez. Üçüncü taraf analitiğini kapalı tutmak için değişkeni tanımlamayın.
- Birinci taraf toplu trafik ölçümü IP adresi, hesap kimliği veya analiz çerezi kaydetmez; Do Not Track ve Global Privacy Control istekleri ölçüm dışı bırakılır.
- Köpek boyutu/ırk grubu seçenekleri küçük, orta ve büyük oda boyutlarını kullanır; sunulan ırklar yaklaşık ve yaygın örneklerdir, eksiksiz veya veterinerce doğrulanmış bir ırk kataloğu değildir. Pet profillerinde listede olmayan ırklar serbest metinle girilebilir.

## Gereksinimler
- **PHP 8.4+** (composer.lock Symfony 8.1 içerir; PHP 8.3'te `composer install` platform hatası verir)
- MariaDB/MySQL, Composer, `php-fileinfo`, `php-mbstring`

## Kurulum (SSH) — YALNIZCA SIFIRDAN / BOŞ SUNUCUDA

> **UYARI – canlı (production) sitede bu bloğu topluca yapıştırmayın.**
> `cp .env.example .env` mevcut `.env` dosyasını ezer (APP_KEY ve DB şifresi gider),
> `key:generate` oturumları ve şifreli verileri geçersiz kılar,
> `db:seed --class=PetOtelSeeder` canlı veritabanına ÖRNEK veri ekler.
> Canlı güncellemede bu bloğu çalıştırmayın. Önce uygulama, yapılandırma ve tüm veritabanlarının geri yüklenebilir yedeğini alın; her DB bağlantısını ve ilgili migration durumunu doğrulayın. Herhangi bir DB bağlantısı/izni doğrulanamıyorsa migration veya kod yayını yapmayın. Özellikle ayrı adoption DB hazır değilken migration'ı varsayılan DB'ye yönlendirmeyin.

Sunucuda `php` komutu beklenenden farklı bir sürüme gidebilir. Önce `command -v php` ve `php -v` ile gerçek ikiliyi doğrulayın; gerekiyorsa hosting panelinden öğrendiğiniz tam yolu `PHP=/mutlak/yol/php` olarak ayarlayın. Composer ve Artisan komutlarında aynı doğrulanmış ikiliyi kullanın.

    PHP="$(command -v php)"
    "$PHP" -v
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

    * * * * * cd /www/wwwroot/antalyapetotel.com && /mutlak/yol/ile/dogruladiginiz/php artisan schedule:run >/dev/null 2>&1

- Cron'un kullandığı PHP'de eklenti kontrolü: `/mutlak/yol/ile/dogruladiginiz/php -m | grep -i redis`
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
