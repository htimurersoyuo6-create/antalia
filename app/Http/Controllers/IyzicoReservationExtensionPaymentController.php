<?php

namespace App\Http\Controllers;

use App\Models\HotelFinancialEntry;
use App\Models\Reservation;
use App\Models\ReservationExtension;
use App\Models\ReservationExtensionPaymentAttempt;
use App\Support\IdentityNumber;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IyzicoReservationExtensionPaymentController extends Controller
{
    private function options(): ?\Iyzipay\Options
    {
        $apiKey = (string) config('services.iyzico.api_key');
        $secret = (string) config('services.iyzico.secret_key');
        $base = rtrim((string) config('services.iyzico.base_url'), '/');
        if (config('services.iyzico.environment') !== 'sandbox'
            || $base !== 'https://sandbox-api.iyzipay.com'
            || !str_starts_with($apiKey, 'sandbox-')
            || !str_starts_with($secret, 'sandbox-')) return null;
        $options = new \Iyzipay\Options();
        $options->setApiKey($apiKey);
        $options->setSecretKey($secret);
        $options->setBaseUrl($base);
        return $options;
    }

    public function start(Request $request, Reservation $reservation, ReservationExtension $extension)
    {
        abort_unless($reservation->user_id === $request->user()->id, 404);
        abort_unless($extension->reservation_id === $reservation->id, 404);
        $extension->load('financialEntry');
        if ($extension->status !== 'approved'
            || $reservation->payment_status !== 'paid'
            || !$extension->financialEntry
            || $extension->financialEntry->status === 'paid') {
            return back()->withErrors(['payment' => 'Bu uzatma ödemeye uygun değil veya zaten tahsil edilmiş.']);
        }
        $options = $this->options();
        if (!$options) return back()->withErrors(['payment' => 'iyzico sandbox anahtarları yapılandırılmamış. Ödeme başlatılmadı.']);

        $data = $request->validate([
            'buyer_name' => 'required|string|max:100',
            'buyer_surname' => 'required|string|max:100',
            'email' => 'required|email|max:190',
            'identity_number' => ['required', 'string', 'regex:/^[0-9]{11}$/D'],
            'buyer_phone' => 'required|string|max:24',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'zip_code' => 'nullable|string|max:20',
        ]);
        if (!IdentityNumber::isValid($data['identity_number'])) return back()->withErrors(['identity_number' => 'Geçerli bir T.C. kimlik numarası girin.'])->withInput();
        $gsm = PhoneNumber::normalize($data['buyer_phone']);
        if (!$gsm) return back()->withErrors(['buyer_phone' => 'Geçerli bir cep telefonu girin.'])->withInput();

        $result = DB::transaction(function () use ($reservation, $extension, $request) {
            $lockedReservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $lockedExtension = ReservationExtension::query()->lockForUpdate()->findOrFail($extension->id);
            if ($lockedReservation->user_id !== $request->user()->id
                || $lockedReservation->payment_status !== 'paid'
                || $lockedExtension->status !== 'approved'
                || !$lockedExtension->financialEntry()->where('status', 'pending')->exists()) return ['unavailable' => true];

            $attempt = ReservationExtensionPaymentAttempt::query()
                ->where('reservation_extension_id', $lockedExtension->id)->lockForUpdate()->first();
            if ($attempt && $attempt->status === 'paid') return ['paid' => true];
            if ($attempt && $attempt->status === 'pending') {
                return $attempt->checkout_url ? ['url' => $attempt->checkout_url] : ['initializing' => true];
            }
            if ($attempt && $attempt->status === 'initializing') return ['initializing' => true];
            $attempt ??= new ReservationExtensionPaymentAttempt(['reservation_extension_id' => $lockedExtension->id]);
            $attempt->forceFill([
                'user_id' => $request->user()->id,
                'conversation_id' => 'APO-EXT-' . Str::upper(Str::random(20)),
                'token' => null,
                'checkout_url' => null,
                'amount' => $lockedExtension->quoted_total,
                'currency' => 'TRY',
                'status' => 'initializing',
                'attempt_count' => (int) $attempt->attempt_count + 1,
                'provider_payment_id' => null,
                'error_code' => null,
                'error_message' => null,
                'paid_at' => null,
            ])->save();
            return ['attempt' => $attempt->fresh(), 'reservation' => $lockedReservation, 'extension' => $lockedExtension];
        });

        if (!empty($result['paid'])) return back()->with('ok', 'Uzatma ödemesi zaten alınmış.');
        if (!empty($result['unavailable'])) return back()->withErrors(['payment' => 'Uzatma ödemesi şu anda uygun değil.']);
        if (!empty($result['url'])) return redirect()->away($result['url']);
        if (!empty($result['initializing'])) return back()->withErrors(['payment' => 'Ödeme başlatma işlemi sürüyor. Birkaç dakika sonra tekrar deneyin.']);
        $attempt = $result['attempt'];
        $lockedReservation = $result['reservation'];
        $lockedExtension = $result['extension'];
        $basketId = $lockedReservation->code . '-EXT-' . $lockedExtension->id;

        try {
            $checkout = new \Iyzipay\Request\CreateCheckoutFormInitializeRequest();
            $checkout->setLocale(\Iyzipay\Model\Locale::TR);
            $checkout->setConversationId($attempt->conversation_id);
            $price = number_format((float) $attempt->amount, 2, '.', '');
            $checkout->setPrice($price);
            $checkout->setPaidPrice($price);
            $checkout->setCurrency(\Iyzipay\Model\Currency::TL);
            $checkout->setBasketId($basketId);
            $checkout->setPaymentGroup(\Iyzipay\Model\PaymentGroup::PRODUCT);
            $checkout->setCallbackUrl(route('payments.iyzico.extension.callback', [], true));
            $checkout->setEnabledInstallments([1, 2, 3, 6, 9, 12]);

            $buyer = new \Iyzipay\Model\Buyer();
            $buyer->setId((string) $request->user()->id);
            $buyer->setName($data['buyer_name']);
            $buyer->setSurname($data['buyer_surname']);
            $buyer->setIdentityNumber($data['identity_number']);
            $buyer->setEmail($data['email']);
            $buyer->setGsmNumber($gsm);
            $buyer->setRegistrationAddress($data['address']);
            $buyer->setCity($data['city']);
            $buyer->setCountry('Turkey');
            $buyer->setZipCode($data['zip_code'] ?? '07000');
            $buyer->setIp((string) $request->ip());
            $buyer->setRegistrationDate(optional($request->user()->created_at)->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'));
            $buyer->setLastLoginDate(now()->format('Y-m-d H:i:s'));
            $checkout->setBuyer($buyer);

            $address = new \Iyzipay\Model\Address();
            $address->setContactName($data['buyer_name'] . ' ' . $data['buyer_surname']);
            $address->setCity($data['city']);
            $address->setCountry('Turkey');
            $address->setAddress($data['address']);
            $address->setZipCode($data['zip_code'] ?? '07000');
            $checkout->setShippingAddress($address);
            $checkout->setBillingAddress($address);

            $item = new \Iyzipay\Model\BasketItem();
            $item->setId((string) $basketId);
            $item->setName(mb_substr('Rezervasyon uzatma · ' . $lockedReservation->hotel->name, 0, 100));
            $item->setCategory1('Pet Otel');
            $item->setCategory2('Konaklama uzatma');
            $item->setItemType(\Iyzipay\Model\BasketItemType::VIRTUAL);
            $item->setPrice($price);
            $checkout->setBasketItems([$item]);

            $form = \Iyzipay\Model\CheckoutFormInitialize::create($checkout, $options);
            $token = (string) $form->getToken();
            $signature = (string) $form->getSignature();
            $expected = bin2hex(hash_hmac('sha256', implode(':', [$attempt->conversation_id, $token]), $options->getSecretKey(), true));
            $url = (string) $form->getPaymentPageUrl();
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if ($form->getStatus() !== 'success' || $token === '' || $signature === ''
                || !hash_equals($expected, $signature) || parse_url($url, PHP_URL_SCHEME) !== 'https'
                || !str_ends_with($host, '.iyzipay.com')) throw new \RuntimeException('checkout_init_invalid');
            $attempt->update(['token' => $token, 'checkout_url' => $url, 'status' => 'pending']);
            return redirect()->away($url);
        } catch (\Throwable) {
            $attempt->update(['status' => 'failed', 'error_code' => 'checkout_init_failed', 'error_message' => 'Sandbox ödeme formu başlatılamadı.']);
            return back()->withErrors(['payment' => 'iyzico sandbox ödeme formu başlatılamadı.']);
        }
    }

    public function callback(Request $request)
    {
        $token = (string) $request->input('token');
        abort_if($token === '' || strlen($token) > 128, 404);
        $attempt = ReservationExtensionPaymentAttempt::query()->where('token', $token)->firstOrFail();
        $extension = ReservationExtension::with('reservation.hotel', 'financialEntry')->findOrFail($attempt->reservation_extension_id);
        $reservation = $extension->reservation;
        if ($attempt->status === 'paid') return redirect()->route('reservations.show', $reservation);
        if ($attempt->status !== 'pending') return redirect()->route('reservations.show', $reservation)->withErrors(['payment' => 'Bu uzatma ödeme denemesi artık etkin değil.']);
        $options = $this->options();
        abort_unless($options, 503, 'iyzico sandbox yapılandırması kullanılamıyor.');

        try {
            $retrieve = new \Iyzipay\Request\RetrieveCheckoutFormRequest();
            $retrieve->setLocale(\Iyzipay\Model\Locale::TR);
            $retrieve->setConversationId($attempt->conversation_id);
            $retrieve->setToken($token);
            $payment = \Iyzipay\Model\CheckoutForm::retrieve($retrieve, $options);
        } catch (\Throwable) {
            return redirect()->route('reservations.show', $reservation)->withErrors(['payment' => 'Uzatma ödeme sonucu sağlayıcıdan doğrulanamadı.']);
        }

        $basketId = $reservation->code . '-EXT-' . $extension->id;
        $signatureParts = [(string) $payment->getPaymentStatus(), (string) $payment->getPaymentId(), (string) $payment->getCurrency(), (string) $payment->getBasketId(), (string) $payment->getConversationId(), (string) $payment->getPaidPrice(), (string) $payment->getPrice(), (string) $payment->getToken()];
        $expected = bin2hex(hash_hmac('sha256', implode(':', $signatureParts), $options->getSecretKey(), true));
        $valid = $payment->getStatus() === 'success'
            && $payment->getPaymentStatus() === 'SUCCESS'
            && (string) $payment->getFraudStatus() === '1'
            && (string) $payment->getToken() === $token
            && (string) $payment->getConversationId() === $attempt->conversation_id
            && (string) $payment->getBasketId() === $basketId
            && (string) $payment->getCurrency() === (string) \Iyzipay\Model\Currency::TL
            && (int) round((float) $payment->getPaidPrice() * 100) === (int) round((float) $attempt->amount * 100)
            && (int) round((float) $payment->getPrice() * 100) === (int) round((float) $attempt->amount * 100)
            && (string) $payment->getSignature() !== ''
            && hash_equals($expected, (string) $payment->getSignature());

        DB::transaction(function () use ($reservation, $extension, $attempt, $payment, $valid) {
            $lockedReservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $lockedExtension = ReservationExtension::query()->lockForUpdate()->findOrFail($extension->id);
            $lockedAttempt = ReservationExtensionPaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($lockedAttempt->status === 'paid') return;
            $entry = HotelFinancialEntry::query()->whereKey($lockedExtension->hotel_financial_entry_id)->lockForUpdate()->first();
            $matches = $lockedAttempt->status === 'pending'
                && $lockedReservation->payment_status === 'paid'
                && $lockedExtension->status === 'approved'
                && $entry && $entry->status === 'pending'
                && (int) round((float) $entry->amount * 100) === (int) round((float) $lockedAttempt->amount * 100);
            if (!$valid || !$matches) {
                $lockedAttempt->update(['status' => 'failed', 'provider_payment_id' => (string) $payment->getPaymentId(), 'error_code' => 'provider_result_invalid', 'error_message' => 'Ödeme sonucu veya cari tutar doğrulanamadı.']);
                return;
            }
            $lockedAttempt->update(['status' => 'paid', 'provider_payment_id' => (string) $payment->getPaymentId(), 'error_code' => null, 'error_message' => null, 'paid_at' => now()]);
            $entry->update(['status' => 'paid', 'paid_at' => now(), 'payment_method' => 'iyzico sandbox', 'reference' => (string) $payment->getPaymentId(), 'notes' => trim(($entry->notes ? $entry->notes . ' ' : '') . 'Sandbox kart ödemesi doğrulandı; gerçek para transferi değildir.')]);
        });

        $attempt->refresh();
        if ($attempt->status === 'paid') return redirect()->route('reservations.show', $reservation)->with('ok', 'Uzatma ödemesi doğrulandı ve ayrı tahsilat olarak işlendi.');
        return redirect()->route('reservations.show', $reservation)->withErrors(['payment' => 'Ödeme tamamlanmadı veya doğrulanamadı; uzatma tahsilat durumu değiştirilmedi.']);
    }
}
