<?php

namespace App\Support;

final class PhoneNumber
{
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if (str_starts_with($digits, '0090')) $digits = substr($digits, 4);
        elseif (str_starts_with($digits, '90') && strlen($digits) === 12) $digits = substr($digits, 2);
        if (str_starts_with($digits, '0') && strlen($digits) === 11) $digits = substr($digits, 1);
        if (strlen($digits) !== 10 || !str_starts_with($digits, '5')) return null;
        return '+90' . $digits;
    }

    public static function whatsapp(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if (str_starts_with($digits, '0090')) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }
        if (!str_starts_with($digits, '90')) {
            $digits = '90' . $digits;
        }
        return $digits;
    }
}
