<?php

namespace App\Support;

use App\Models\PaymentGatewaySetting;

class PaytrSettings
{
    public static function environmentReady(): bool
    {
        return filled(config('services.paytr.merchant_id'))
            && filled(config('services.paytr.merchant_key'))
            && filled(config('services.paytr.merchant_salt'));
    }

    public static function credentials(): array
    {
        if (self::environmentReady()) {
            return [
                'merchant_id' => (string) config('services.paytr.merchant_id'),
                'merchant_key' => (string) config('services.paytr.merchant_key'),
                'merchant_salt' => (string) config('services.paytr.merchant_salt'),
                'test_mode' => (bool) config('services.paytr.test_mode'),
            ];
        }

        try {
            $stored = PaymentGatewaySetting::query()->whereKey('paytr')->first();
            if ($stored && filled($stored->merchant_id) && filled($stored->merchant_key) && filled($stored->merchant_salt)) {
                return [
                    'merchant_id' => $stored->merchant_id,
                    'merchant_key' => $stored->merchant_key,
                    'merchant_salt' => $stored->merchant_salt,
                    'test_mode' => (bool) $stored->test_mode,
                ];
            }
        } catch (\Throwable) {
            // Keep card payments disabled if encrypted settings cannot be read.
        }

        return [
            'merchant_id' => (string) config('services.paytr.merchant_id', ''),
            'merchant_key' => (string) config('services.paytr.merchant_key', ''),
            'merchant_salt' => (string) config('services.paytr.merchant_salt', ''),
            'test_mode' => (bool) config('services.paytr.test_mode', true),
        ];
    }

    public static function ready(): bool
    {
        $credentials = self::credentials();
        return filled($credentials['merchant_id']) && filled($credentials['merchant_key']) && filled($credentials['merchant_salt']);
    }

    public static function status(): array
    {
        $credentials = self::credentials();
        return [
            'ready' => self::ready(),
            'test_mode' => $credentials['test_mode'],
            'environment_managed' => self::environmentReady(),
        ];
    }

    public static function saveFromAdmin(array $input): void
    {
        if (self::environmentReady()) {
            throw new \LogicException('PayTR bilgileri sunucu ortamından yönetiliyor.');
        }

        $setting = PaymentGatewaySetting::firstOrNew(['provider' => 'paytr']);
        foreach (['merchant_id', 'merchant_key', 'merchant_salt'] as $field) {
            $value = trim((string) ($input[$field] ?? ''));
            if ($value !== '') $setting->{$field} = $value;
        }
        if (array_key_exists('test_mode', $input) && $input['test_mode'] !== null) $setting->test_mode = (bool) $input['test_mode'];
        if (!$setting->test_mode && (!filled($setting->merchant_id) || !filled($setting->merchant_key) || !filled($setting->merchant_salt))) {
            throw new \InvalidArgumentException('Canlı moda geçmek için üç PayTR mağaza bilgisi de kayıtlı olmalıdır.');
        }
        $setting->save();
    }
}
