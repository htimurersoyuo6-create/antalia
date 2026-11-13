<?php
namespace App\Http\Controllers;

use App\Models\HotelFinancialEntry;
use App\Models\Reservation;
use App\Models\ReservationPaymentAttempt;
use App\Support\IdentityNumber;
use App\Support\PhoneNumber;
use App\Services\CommissionSettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IyzicoReservationPaymentController extends Controller
{
    private function options(): ?\Iyzipay\Options
    {
        $apiKey = (string) config('services.iyzico.api_key');
        $secretKey = (string) config('services.iyzico.secret_key');
        $baseUrl = rtrim((string) config('services.iyzico.base_url'), '/');
        if (config('services.iyzico.environment') !== 'sandbox'
            || $baseUrl !== 'https://sandbox-api.iyzipay.com'
            || !str_starts_with($apiKey, 'sandbox-')
            || !str_starts_with($secretKey, 'sandbox-')) {
            return null;
        }
        $options = new \Iyzipay\Options();
        $options->setApiKey($apiKey);
        $options->setSecretKey($secretKey);
        $options->setBaseUrl($baseUrl);
        return $options;
    }

    public function start(Request $request, Reservation $reservation)
    {
        abort_unless($reservation->user_id === $request->user()->id, 404);
        if ($reservation->status !== 'confirmed' || !$reservation->check_out->gt(today())) {
            return back()->withErrors(['payment' => 'Yalnızca onaylı ve devam eden rezervasyonlar ödenebilir.']);
        }
        if ($reservation->payment_status === 'paid') {
            return back()->with('ok', 'Bu rezervasyonun ödemesi zaten alınmış.');
        }
        $options = $this->options();
        if (!$options) {
            return back()->withErrors(['payment' => 'iyzico sandbox anahtarları yapılandırılmamış. Ödeme başlatılmadı.']);
        }
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
        if (!IdentityNumber::isValid($data['identity_number'])) {
            return back()->withErrors(['identity_number' => 'Geçerli bir T.C. kimlik numarası girin.']);
        }
        $gsm = PhoneNumber::normalize($data['buyer_phone']);
        if (!$gsm) {
            return back()->withErrors(['buyer_phone' => 'Geçerli bir cep telefonu numarası girin.']);
        }

        $attemptResult = DB::transaction(function () use ($reservation, $request) {
            $lockedReservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            if ($lockedReservation->payment_status === 'paid') return ['paid' => true];
            if ($lockedReservation->status !== 'confirmed' || !$lockedReservation->check_out->gt(today()) || (float) $lockedReservation->total_price <= 0) {
                return ['unavailable' => true];
            }
            $attempt = ReservationPaymentAttempt::query()->where('reservation_id', $lockedReservation->id)->lockForUpdate()->first();
            if ($attempt && $attempt->status === 'paid') return ['paid' => true];
            if ($attempt && $attempt->status === 'pending') {
                return $attempt->checkout_url ? ['url' => $attempt->checkout_url] : ['initializing' => true];
            }
            if ($attempt && $attempt->status === 'initializing') return ['initializing' => true];
            $attempt ??= new ReservationPaymentAttempt(['reservation_id' => $lockedReservation->id]);
            $attempt->forceFill([
                'user_id' => $request->user()->id,
                'conversation_id' => 'APO-' . Str::upper(Str::random(24)),
                'token' => null,
                'checkout_url' => null,
                'amount' => $lockedReservation->total_price,
                'currency' => 'TRY',
                'status' => 'initializing',
                'attempt_count' => (int) $attempt->attempt_count + 1,
                'provider_payment_id' => null,
                'error_code' => null,
                'error_message' => null,
                'paid_at' => null,
            ])->save();
            return ['attempt' => $attempt->fresh()];
        });
        if (!empty($attemptResult['paid'])) return back()->with('ok', 'Bu rezervasyonun ödemesi zaten alınmış.');
        if (!empty($attemptResult['unavailable'])) return back()->withErrors(['payment' => 'Rezervasyon şu anda ödemeye uygun değil.']);
        if (!empty($attemptResult['url'])) return redirect()->away($attemptResult['url']);
        $attempt = $attemptResult['attempt'];

        try {
            $requestData = new \Iyzipay\Request\CreateCheckoutFormInitializeRequest();
            $requestData->setLocale(\Iyzipay\Model\Locale::TR);
            $requestData->setConversationId($attempt->conversation_id);
            $requestData->setPrice(number_format((float) $attempt->amount, 2, '.', ''));
            $requestData->setPaidPrice(number_format((float) $attempt->amount, 2, '.', ''));
            $requestData->setCurrency(\Iyzipay\Model\Currency::TL);
            $requestData->setBasketId($reservation->code);
            $requestData->setPaymentGroup(\Iyzipay\Model\PaymentGroup::PRODUCT);
            $requestData->setCallbackUrl(route('payments.iyzico.callback', [], true));
            $requestData->setEnabledInstallments([1, 2, 3, 6, 9, 12]);

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
            $requestData->setBuyer($buyer);

            $address = new \Iyzipay\Model\Address();
            $address->setContactName($data['buyer_name'] . ' ' . $data['buyer_surname']);
            $address->setCity($data['city']);
            $address->setCountry('Turkey');
            $address->setAddress($data['address']);
            $address->setZipCode($data['zip_code'] ?? '07000');
            $requestData->setShippingAddress($address);
            $requestData->setBillingAddress($address);

            $item = new \Iyzipay\Model\BasketItem();
            $item->setId((string) $reservation->code);
            $item->setName(mb_substr('Pet otel konaklaması · ' . $reservation->hotel->name, 0, 100));
            $item->setCategory1('Pet Otel');
            $item->setCategory2('Konaklama');
            $item->setItemType(\Iyzipay\Model\BasketItemType::VIRTUAL);
            $item->setPrice(number_format((float) $attempt->amount, 2, '.', ''));
            $requestData->setBasketItems([$item]);

            $form = \Iyzipay\Model\CheckoutFormInitialize::create($requestData, $options);
            $token = (string) $form->getToken();
            $signature = (string) $form->getSignature();
            $expectedSignature = bin2hex(hash_hmac('sha256', implode(':', [$attempt->conversation_id, $token]), $options->getSecretKey(), true));
            $paymentUrl = (string) $form->getPaymentPageUrl();
            $host = strtolower((string) parse_url($paymentUrl, PHP_URL_HOST));
            if ($form->getStatus() !== 'success' || $token === '' || $signature === ''
                || !hash_equals($expectedSignature, $signature)
                || parse_url($paymentUrl, PHP_URL_SCHEME) !== 'https'
                || !str_ends_with($host, '.iyzipay.com')) {
                $attempt->update(['status' => 'failed', 'error_code' => 'checkout_init_failed', 'error_message' => 'Sandbox ödeme formu başlatılamadı.']);
                return back()->withErrors(['payment' => 'iyzico sandbox ödeme formu başlatılamadı.']);
            }
            $attempt->update(['token' => $token, 'checkout_url' => $paymentUrl, 'status' => 'pending']);
            return redirect()->away($paymentUrl);
        } catch (\Throwable) {
            $attempt->update(['status' => 'failed', 'error_code' => 'checkout_init_error', 'error_message' => 'Sandbox bağlantısı kurulamadı.']);
            return back()->withErrors(['payment' => 'iyzico sandbox bağlantısı kurulamadı. Lütfen daha sonra tekrar deneyin.']);
        }
    }

    public function callback(Request $request)
    {
        $token = (string) $request->input('token');
        abort_if($token === '' || strlen($token) > 128, 404);
        $attempt = ReservationPaymentAttempt::query()->where('token', $token)->firstOrFail();
        $reservation = Reservation::with('hotel')->findOrFail($attempt->reservation_id);
        if ($attempt->status === 'paid') return redirect()->route('reservations.show', $reservation);
        if ($attempt->status !== 'pending') return redirect()->route('reservations.show', $reservation)->withErrors(['payment' => 'Bu ödeme denemesi artık etkin değil.']);
        $options = $this->options();
        abort_unless($options, 503, 'iyzico sandbox yapılandırması kullanılamıyor.');

        try {
            $retrieve = new \Iyzipay\Request\RetrieveCheckoutFormRequest();
            $retrieve->setLocale(\Iyzipay\Model\Locale::TR);
            $retrieve->setConversationId($attempt->conversation_id);
            $retrieve->setToken($token);
            $payment = \Iyzipay\Model\CheckoutForm::retrieve($retrieve, $options);
        } catch (\Throwable) {
            return redirect()->route('reservations.show', $reservation)->withErrors(['payment' => 'Ödeme sonucu sağlayıcıdan doğrulanamadı.']);
        }

        $signatureParts = [
            (string) $payment->getPaymentStatus(), (string) $payment->getPaymentId(),
            (string) $payment->getCurrency(), (string) $payment->getBasketId(),
            (string) $payment->getConversationId(), (string) $payment->getPaidPrice(),
            (string) $payment->getPrice(), (string) $payment->getToken(),
        ];
        $expectedSignature = bin2hex(hash_hmac('sha256', implode(':', $signatureParts), $options->getSecretKey(), true));
        $signed = (string) $payment->getSignature() !== '' && hash_equals($expectedSignature, (string) $payment->getSignature());
        $paid = $payment->getStatus() === 'success'
            && $payment->getPaymentStatus() === 'SUCCESS'
            && in_array((string) $payment->getFraudStatus(), ['1'], true)
            && (string) $payment->getToken() === $token
            && (string) $payment->getConversationId() === $attempt->conversation_id
            && (string) $payment->getBasketId() === (string) $reservation->code
            && (string) $payment->getCurrency() === (string) \Iyzipay\Model\Currency::TL
            && (int) round((float) $payment->getPaidPrice() * 100) === (int) round((float) $attempt->amount * 100)
            && (int) round((float) $payment->getPrice() * 100) === (int) round((float) $attempt->amount * 100)
            && $signed;

        $settlementService = app(CommissionSettlementService::class);

        DB::transaction(function () use ($attempt, $reservation, $paid, $payment, $settlementService) {
            // Keep the same reservation -> payment-attempt lock order as checkout initialization.
            $lockedReservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $lockedAttempt = ReservationPaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($lockedAttempt->status === 'paid') return;
            $amountStillMatches = (int) round((float) $lockedReservation->total_price * 100) === (int) round((float) $lockedAttempt->amount * 100);
            if (!$paid || $lockedAttempt->status !== 'pending' || !$amountStillMatches || $lockedReservation->payment_status === 'paid') {
                $lockedAttempt->update([
                    'status' => 'failed',
                    'provider_payment_id' => $payment->getPaymentId(),
                    'error_code' => $paid ? 'already_paid_or_amount_changed' : 'provider_result_invalid',
                    'error_message' => $paid ? 'Rezervasyon zaten ödenmiş veya ödeme tutarı değişmiş.' : 'Sağlayıcı sonucu doğrulanamadı.',
                ]);
                return;
            }
            $lockedAttempt->update([
                'status' => 'paid',
                'provider_payment_id' => (string) $payment->getPaymentId(),
                'error_code' => null,
                'error_message' => null,
                'paid_at' => now(),
            ]);
            $lockedReservation->update(['payment_status' => 'paid', 'payment_method' => 'iyzico_sandbox']);
            $lockedReservation->reservationTransfer()->where('status', 'pending')->update(['status' => 'confirmed']);
            $transaction = $settlementService->recordSuccessfulPayment(
                $lockedReservation,
                $lockedAttempt,
                'iyzico_sandbox',
                (string) $payment->getPaymentId(),
                ['conversation_id' => $lockedAttempt->conversation_id],
            );
            HotelFinancialEntry::updateOrCreate(
                ['reservation_id' => $lockedReservation->id, 'category' => 'Rezervasyon tahsilatı'],
                [
                    'hotel_id' => $lockedReservation->hotel_id,
                    'created_by' => $lockedReservation->user_id,
                    'type' => 'income',
                    'description' => 'Rezervasyon net hakedişi · ' . $lockedReservation->code,
                    'amount' => $transaction->hotel_payout,
                    'status' => 'paid',
                    'due_date' => $lockedReservation->check_in->toDateString(),
                    'paid_at' => now(),
                    'payment_method' => 'iyzico sandbox',
                    'reference' => (string) $payment->getPaymentId(),
                    'notes' => 'Brüt: ' . number_format((float) $transaction->gross_amount, 2, ',', '.') . ' TL · Platform komisyonu (%' . number_format((float) $transaction->commission_percentage, 2, ',', '.') . '): ' . number_format((float) $transaction->commission_amount, 2, ',', '.') . ' TL.',
                ]
            );
        });

        if ($paid && $attempt->fresh()->status === 'paid' && $reservation->fresh()->payment_status === 'paid') {
            $reservation->refresh();
            \App\Models\ReservationInvoice::ensurePending($reservation);
        }
        if ($paid) return redirect()->route('reservations.show', $reservation)->with('ok', 'iyzico sandbox ödemesi doğrulandı ve rezervasyona işlendi.');
        return redirect()->route('reservations.show', $reservation)->withErrors(['payment' => 'Ödeme tamamlanmadı veya doğrulanamadı; rezervasyon ödeme durumu değiştirilmedi.']);
    }
}
