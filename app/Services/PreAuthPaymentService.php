<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PreauthBlock;
use App\Models\Reservation;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PreAuthPaymentService
{
    public function block(Reservation $reservation, int $amountCents, array $card, array $buyer): PreauthBlock
    {
        $options = $this->options();

        if (! $options) {
            throw new RuntimeException('iyzico kimlik bilgileri yapılandırılmamış.');
        }

        $price = number_format($amountCents / 100, 2, '.', '');
        $request = new \Iyzipay\Request\CreateBasicPaymentRequest();
        $request->setLocale('tr');
        $request->setConversationId('preauth-'.$reservation->id.'-'.now()->timestamp);
        $request->setPrice($price);
        $request->setPaidPrice($price);
        $request->setCurrency('TRY');
        $request->setInstallment(1);
        $request->setPaymentChannel('WEB');
        $request->setPaymentGroup('PRODUCT');
        $request->setCallbackUrl(route('panel.care.preauth.callback', $reservation));
        $request->setPaymentCard($this->card($card));
        $request->setBuyer($this->buyer($buyer));
        $request->setShippingAddress($this->address($buyer));
        $request->setBillingAddress($this->address($buyer));

        try {
            $response = \Iyzipay\Model\BasicPaymentPreAuth::create($request, $options);
        } catch (Throwable $e) {
            Log::error('preauth.exception', ['reservation' => $reservation->id, 'message' => $e->getMessage()]);

            throw new RuntimeException('Ön otorizasyon isteği başarısız: '.$e->getMessage(), 0, $e);
        }

        if ($response->getStatus() !== 'success') {
            Log::warning('preauth.rejected', ['reservation' => $reservation->id, 'error' => $response->getErrorMessage()]);

            throw new RuntimeException('iyzico ön otorizasyon reddedildi: '.(string) $response->getErrorMessage());
        }

        return PreauthBlock::create([
            'reservation_id' => $reservation->id,
            'hotel_id' => $reservation->hotel_id,
            'provider' => 'iyzico',
            'payment_id' => (string) $response->getPaymentId(),
            'preauth_id' => (string) $response->getPaymentId(),
            'amount_cents' => $amountCents,
            'currency' => 'TRY',
            'status' => 'blocked',
            'blocked_at' => now(),
            'reason' => $reservation->customer_note,
        ]);
    }

    public function capture(PreauthBlock $block, ?int $amountCents = null): PreauthBlock
    {
        if ($block->status !== 'blocked') {
            throw new RuntimeException('Yalnızca bloke durumdaki ön otorizasyonlar tahsil edilebilir.');
        }

        $options = $this->options();

        if (! $options) {
            throw new RuntimeException('iyzico kimlik bilgileri yapılandırılmamış.');
        }

        $amount = $amountCents ?? $block->amount_cents;
        $request = new \Iyzipay\Request\CreatePaymentPostAuthRequest();
        $request->setLocale('tr');
        $request->setConversationId('preauth-capture-'.$block->id);
        $request->setPaymentId($block->payment_id);
        $request->setPaidPrice(number_format($amount / 100, 2, '.', ''));
        $request->setCurrency('TRY');

        try {
            $response = \Iyzipay\Model\BasicPaymentPostAuth::create($request, $options);
        } catch (Throwable $e) {
            Log::error('preauth.capture_exception', ['block' => $block->id, 'message' => $e->getMessage()]);

            throw new RuntimeException('Ön otorizasyon tahsilat isteği başarısız: '.$e->getMessage(), 0, $e);
        }

        if ($response->getStatus() !== 'success') {
            Log::warning('preauth.capture_failed', ['block' => $block->id, 'error' => $response->getErrorMessage()]);

            throw new RuntimeException('Ön otorizasyon tahsil edilemedi: '.(string) $response->getErrorMessage());
        }

        $block->update(['status' => 'captured', 'released_at' => now()]);

        return $block->refresh();
    }

    public function release(PreauthBlock $block, ?int $byUserId = null): PreauthBlock
    {
        if ($block->status === 'blocked') {
            $block->update([
                'status' => 'released',
                'released_at' => now(),
                'released_by' => $byUserId,
            ]);
        }

        return $block->refresh();
    }

    private function options(): ?\Iyzipay\Options
    {
        $apiKey = (string) config('services.iyzico.api_key');
        $secretKey = (string) config('services.iyzico.secret_key');
        $baseUrl = rtrim((string) config('services.iyzico.base_url'), '/');

        if ($apiKey === '' || $secretKey === '' || $baseUrl === '') {
            return null;
        }

        $options = new \Iyzipay\Options();
        $options->setApiKey($apiKey);
        $options->setSecretKey($secretKey);
        $options->setBaseUrl($baseUrl);

        return $options;
    }

    private function card(array $card): \Iyzipay\Model\PaymentCard
    {
        $paymentCard = new \Iyzipay\Model\PaymentCard();
        $paymentCard->setCardHolderName((string) ($card['holder'] ?? ''));
        $paymentCard->setCardNumber((string) ($card['number'] ?? ''));
        $paymentCard->setExpireMonth((string) ($card['expire_month'] ?? ''));
        $paymentCard->setExpireYear((string) ($card['expire_year'] ?? ''));
        $paymentCard->setCvc((string) ($card['cvc'] ?? ''));
        $paymentCard->setRegisterCard(0);

        return $paymentCard;
    }

    private function buyer(array $buyer): \Iyzipay\Model\Buyer
    {
        $model = new \Iyzipay\Model\Buyer();
        $model->setId((string) ($buyer['id'] ?? ''));
        $model->setName((string) ($buyer['name'] ?? ''));
        $model->setSurname((string) ($buyer['surname'] ?? ''));
        $model->setEmail((string) ($buyer['email'] ?? ''));
        $model->setIdentityNumber((string) ($buyer['identity'] ?? '11111111111'));
        $model->setRegistrationAddress((string) ($buyer['address'] ?? 'Antalya'));
        $model->setCity((string) ($buyer['city'] ?? 'Antalya'));
        $model->setCountry((string) ($buyer['country'] ?? 'Türkiye'));
        $model->setIp((string) ($buyer['ip'] ?? request()->ip()));

        return $model;
    }

    private function address(array $buyer): \Iyzipay\Model\Address
    {
        $model = new \Iyzipay\Model\Address();
        $model->setContactName((string) ($buyer['name'] ?? 'Misafir'));
        $model->setCity((string) ($buyer['city'] ?? 'Antalya'));
        $model->setCountry((string) ($buyer['country'] ?? 'Türkiye'));
        $model->setAddress((string) ($buyer['address'] ?? 'Antalya'));

        return $model;
    }
}
