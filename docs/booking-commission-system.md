# Rezervasyon ve komisyon sistemi

Bu uygulama Laravel ve MySQL kullanır. Mevcut şema içindeki `hotels`, `room_types` ve `reservations` tabloları rezervasyon kaynağıdır. `room_types`, bu projedeki oda tablosunun adıdır; her satır bir satılabilir oda tipi/fiyatıdır ve fiziksel kapasite `room_units` ile tutulur.

## Şema

- `hotels`: tesis sahibi, konum ve tesis ayarları.
- `room_types`: `hotel_id`, `base_price`, hayvan türü ve oda bilgisi.
- `room_units`: oda tipinin satılabilir fiziksel kapasitesi.
- `reservations`: kullanıcı, tesis, oda, giriş/çıkış, toplam tutar ve ödeme durumu.
- `reservation_payment_attempts`: gateway başlatma ve callback doğrulama kaydı.
- `transactions`: başarılı tahsilat için değişmez finansal anlık görüntü.

`transactions` alanları:

| Alan | Amaç |
| --- | --- |
| `gross_amount` | Müşteriden alınan toplam tutar |
| `commission_percentage` | Tahsilat anındaki platform oranı |
| `commission_amount` | Platform payı |
| `hotel_payout` | Otele aktarılacak net tutar |
| `gateway`, `gateway_payment_id` | Sağlayıcı ve sağlayıcı ödeme referansı |
| `reservation_id` | Her rezervasyonda tek başarılı tahsilat; callback idempotency anahtarı |

## Akış

1. `BookingService::quote()` seçilen tarihler için gecelik ücretleri, indirimleri ve ekstraları hesaplar.
2. Rezervasyon oluşturulurken `BookingService::availableUnit()` aynı oda birimine çakışan rezervasyon olmadığını kontrol eder.
3. `IyzicoReservationPaymentController::start()` toplam tutarı `reservation_payment_attempts` kaydına sabitler ve iyzico sandbox checkout formunu başlatır.
4. `callback()` sağlayıcının imzasını, tutarı, para birimini, sepet kodunu ve fraud sonucunu doğrular.
5. Ödeme doğrulanınca `CommissionSettlementService::recordSuccessfulPayment()` komisyonu hesaplar, `transactions` tablosuna kaydeder ve otel finans kaydını net hakedişle günceller.

## Komisyon yapılandırması

`.env` dosyasına örneğin şu değer eklenir:

```dotenv
PLATFORM_COMMISSION_PERCENTAGE=15
```

Oran, ödeme başarılı olduğu anda işlem satırına yazılır. Sonradan ortam değişkeni değiştirilse bile önceki işlemlerin komisyonu değişmez.

## İdempotency ve güvenlik

- `transactions.reservation_id` ve `reservation_payment_attempt_id` benzersizdir.
- Callback, `Reservation` ve ödeme denemesi satırlarını kilit altında günceller.
- Aynı gateway callback'i yeniden gelse bile `firstOrCreate()` ikinci komisyon veya ikinci otel hakedişi üretmez.
- Kart bilgisi sistemde saklanmaz. Callback metadata alanında yalnızca güvenli, ödeme sağlayıcısı referansları tutulur.

## Temel dosyalar

- `app/Services/BookingService.php`: fiyat ve müsaitlik hesaplama.
- `app/Http/Controllers/IyzicoReservationPaymentController.php`: iyzico sandbox ödeme başlatma ve doğrulama.
- `app/Services/CommissionSettlementService.php`: komisyon/hakediş hesaplama.
- `app/Models/PaymentTransaction.php`: finansal işlem modeli.
- `database/migrations/2026_10_16_131500_create_transactions_table.php`: MySQL şeması.
