<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\HotelSubscription;
use App\Models\HotelSubscriptionPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SubscriptionPaymentController extends Controller
{
    private function ready(): bool
    {
        return \App\Support\PaytrSettings::ready();
    }

    public function start(Request $request, HotelSubscription $subscription)
    {
        abort_unless($subscription->requested_by === $request->user()->id
            || $subscription->hotel()->where('owner_id', $request->user()->id)->exists(), 404);
        abort_unless(in_array($subscription->status, ['awaiting_payment', 'pending_review'], true)
            && $subscription->payment_status !== 'paid', 409);
        if (!$this->ready()) {
            return back()->withErrors(['payment' => 'Online kart ödemesi henüz yapılandırılmadı. Havale seçeneğini kullanabilir veya yönetim ekibine başvurabilirsiniz.']);
        }

        $address = trim((string) ($subscription->hotel?->address ?? $request->user()->address ?? ''));
        $data = $request->validate(['billing_address' => 'nullable|string|max:400']);
        if (($data['billing_address'] ?? '') !== '') $address = trim($data['billing_address']);
        if ($address === '') return back()->withErrors(['billing_address' => 'Kart ödemesi için fatura adresi girin.']);

        $payment = DB::transaction(function () use ($subscription) {
            $locked = HotelSubscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            if (!in_array($locked->status, ['awaiting_payment', 'pending_review'], true) || $locked->payment_status === 'paid') return null;
            $locked->update(['requested_payment_method' => 'card']);
            $pending = $locked->payments()->where('status', 'pending')->latest()->first();
            if ($pending) return $pending;
            return $locked->payments()->create([
                'merchant_oid' => 'APOS' . now()->format('ymd') . Str::upper(Str::random(24)),
                'amount_kurus' => (int) round((float) $locked->amount * 100),
                'status' => 'initializing',
                'test_mode' => (bool) \App\Support\PaytrSettings::credentials()['test_mode'],
            ]);
        });
        if (!$payment) return back()->withErrors(['payment' => 'Bu abonelik ödemeye uygun değil veya daha önce sonuçlandı.']);
        if ($payment->status === 'pending' && $payment->provider_payment_id) {
            return redirect()->away('https://www.paytr.com/odeme/guvenli/' . rawurlencode($payment->provider_payment_id));
        }

        $credentials = \App\Support\PaytrSettings::credentials();
        $merchantId = (string) $credentials['merchant_id'];
        $merchantKey = (string) $credentials['merchant_key'];
        $merchantSalt = (string) $credentials['merchant_salt'];
        $testMode = $payment->test_mode ? '1' : '0';
        $amount = (string) $payment->amount_kurus;
        $basket = base64_encode(json_encode([[mb_substr('Antalya Pet Otel aboneliği · ' . $subscription->plan->name, 0, 80), number_format((float) $subscription->amount, 2, '.', ''), 1]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $fields = [
            'merchant_id' => $merchantId,
            'user_ip' => substr((string) $request->ip(), 0, 39),
            'merchant_oid' => $payment->merchant_oid,
            'email' => $request->user()->email,
            'payment_type' => 'card',
            'payment_amount' => $amount,
            'currency' => 'TL',
            'test_mode' => $testMode,
            'non_3d' => '0',
            'merchant_ok_url' => route('panel.subscriptions.pay.return', absolute: true),
            'merchant_fail_url' => route('panel.subscriptions.pay.return', absolute: true),
            'user_name' => mb_substr($request->user()->name, 0, 60),
            'user_address' => mb_substr($address, 0, 400),
            'user_phone' => mb_substr((string) $request->user()->phone, 0, 20),
            'user_basket' => $basket,
            'no_installment' => '1',
            'max_installment' => '0',
            'timeout_limit' => '30',
            'debug_on' => app()->environment('production') ? '0' : '1',
            'lang' => 'tr',
        ];
        $hashString = $fields['merchant_id'] . $fields['user_ip'] . $fields['merchant_oid'] . $fields['email'] . $fields['payment_amount'] . $fields['user_basket'] . $fields['no_installment'] . $fields['max_installment'] . $fields['currency'] . $fields['test_mode'];
        $fields['paytr_token'] = base64_encode(hash_hmac('sha256', $hashString . $merchantSalt, $merchantKey, true));

        try {
            $response = Http::asForm()->connectTimeout(5)->timeout(20)->post('https://www.paytr.com/odeme/api/get-token', $fields)->throw()->json();
        } catch (\Throwable) {
            $payment->update(['status' => 'failed', 'error_code' => 'provider_unreachable']);
            return back()->withErrors(['payment' => 'Kart ödeme sayfasına şu anda bağlanılamadı. Biraz sonra yeniden deneyin.']);
        }
        if (($response['status'] ?? null) !== 'success' || empty($response['token']) || !preg_match('/^[A-Za-z0-9._-]{8,512}$/D', (string) $response['token'])) {
            $payment->update(['status' => 'failed', 'error_code' => 'provider_rejected']);
            return back()->withErrors(['payment' => 'Kart ödeme sayfası sağlayıcı tarafından başlatılamadı. Ödeme alınmadı.']);
        }
        $payment->update(['status' => 'pending', 'provider_payment_id' => (string) $response['token']]);
        return redirect()->away('https://www.paytr.com/odeme/guvenli/' . rawurlencode((string) $response['token']));
    }

    /** Provider server-to-server callback. Never activate based on the browser redirect. */
    public function notify(Request $request)
    {
        $oid = (string) $request->input('merchant_oid');
        $status = (string) $request->input('status');
        $total = (string) $request->input('total_amount');
        $received = (string) $request->input('hash');
        if ($oid === '' || strlen($oid) > 64 || $total === '' || $received === '') return response('Invalid', 400);

        $credentials = \App\Support\PaytrSettings::credentials();
        $expected = base64_encode(hash_hmac('sha256', $oid . (string) $credentials['merchant_salt'] . $status . $total, (string) $credentials['merchant_key'], true));
        if (!hash_equals($expected, $received)) return response('Invalid', 400);

        $payment = HotelSubscriptionPayment::with('subscription.hotel')->where('merchant_oid', $oid)->first();
        if (!$payment) return response('Invalid', 404);
        if (in_array($payment->status, ['paid', 'test_paid', 'failed'], true)) return response('OK', 200);

        $callbackTest = $request->exists('test_mode')
            ? (string) $request->input('test_mode') === '1'
            : (bool) $payment->test_mode;
        $configuredTest = (bool) $credentials['test_mode'];
        $success = $status === 'success'
            && (string) $request->input('payment_type') === 'card'
            && (string) $request->input('currency') === 'TL'
            && (int) $request->input('payment_amount', -1) === (int) $payment->amount_kurus
            && (int) $total === (int) $payment->amount_kurus
            && $callbackTest === $configuredTest
            && $callbackTest === (bool) $payment->test_mode;

        DB::transaction(function () use ($payment, $success, $callbackTest, $request) {
            $lockedPayment = HotelSubscriptionPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $subscription = HotelSubscription::whereKey($lockedPayment->hotel_subscription_id)->lockForUpdate()->firstOrFail();
            if ($lockedPayment->status !== 'pending') return;
            if (!$success || !in_array($subscription->status, ['awaiting_payment', 'pending_review'], true)) {
                $lockedPayment->update(['status' => 'failed', 'error_code' => 'payment_not_approved']);
                return;
            }

            // A test transaction can exercise the callback but must never publish
            // a production hotel or mark a real subscription as paid.
            if ($callbackTest) {
                $lockedPayment->update(['status' => 'test_paid']);
                return;
            }

            // For an account-level first purchase, carry forward only that
            // owner's previous subscription period; hotel renewals stay scoped.
            $startsAt = HotelSubscription::where('status', 'active')->where('payment_status', 'paid')->where('ends_at', '>', now())
                ->when($subscription->hotel_id, fn ($q) => $q->where('hotel_id', $subscription->hotel_id), fn ($q) => $q->where('requested_by', $subscription->requested_by))
                ->max('ends_at');
            $startsAt = $startsAt ? \Illuminate\Support\Carbon::parse($startsAt) : now();
            $endsAt = $startsAt->copy()->add($subscription->billing_cycle === 'yearly' ? 'year' : 'month');
            $lockedPayment->update(['status' => 'paid', 'paid_at' => now(), 'error_code' => null]);
            $subscription->update([
                'status' => 'active', 'payment_status' => 'paid', 'payment_method' => 'paytr',
                'payment_reference' => $lockedPayment->merchant_oid, 'paid_at' => now(),
                'starts_at' => $startsAt, 'ends_at' => $endsAt,
            ]);
            if ($subscription->hotel_id) Hotel::whereKey($subscription->hotel_id)->where(function ($q) {
                $q->where('status', 'pending')->orWhere(function ($s) {
                    $s->where('status', 'suspended')->where('subscription_suspended', true);
                });
            })->update(['status' => 'active', 'subscription_suspended' => false]);
        });

        return response('OK', 200);
    }

    public function returned(Request $request)
    {
        return redirect()->route('panel.subscriptions')->with('ok', 'Ödeme sonucu PayTR’ın güvenli sunucu bildirimiyle doğrulanıyor. Doğrulanınca aboneliğiniz otomatik etkinleşir.');
    }
}
