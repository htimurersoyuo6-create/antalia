<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
class NetgsmOtpSender
{
    public function configured(): bool
    {
        $c = config('services.netgsm');
        return filled($c['usercode'] ?? null) && filled($c['password'] ?? null) && filled($c['header'] ?? null);
    }
    public function send(string $e164Phone, string $code): bool
    {
        if (!$this->configured()) return false;
        $c = config('services.netgsm');
        $digits = preg_replace('/\D+/', '', $e164Phone);
        if (str_starts_with($digits, '90')) $digits = substr($digits, 2);
        if (strlen($digits) !== 10 || $digits[0] !== '5') return false;
        try {
            $response = Http::acceptJson()->asJson()->withBasicAuth((string)$c['usercode'], (string)$c['password'])
                ->timeout(10)->post('https://api.netgsm.com.tr/sms/rest/v2/otp', [
                    'msgheader' => (string)$c['header'],
                    'msg' => 'code: '.$code,
                    'no' => $digits,
                ]);
            $body = $response->json();
            if ($response->successful() && is_array($body) && (string)($body['code'] ?? '') === '00') return true;
            Log::warning('Netgsm OTP request rejected', ['http_status'=>$response->status(), 'provider_code'=>is_array($body)?($body['code']??null):null]);
        } catch (\Throwable $e) {
            Log::warning('Netgsm OTP request failed', ['exception'=>get_class($e)]);
        }
        return false;
    }
}
