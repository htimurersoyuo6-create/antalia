<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppCloudSetting extends Model
{
    protected $table = 'whatsapp_cloud_settings';
    protected $guarded = [];

    protected function casts(): array
    {
        return ['access_token' => 'encrypted'];
    }

    public static function current(): self
    {
        return static::firstOrNew(['id' => 1], ['api_version' => 'v23.0', 'template_language' => 'tr_TR']);
    }

    public static function status(): array
    {
        $s = static::current();
        return [
            'ready' => filled($s->phone_number_id) && filled($s->access_token),
            'has_token' => filled($s->access_token),
            'phone_number_id' => (string) ($s->phone_number_id ?? ''),
            'api_version' => (string) ($s->api_version ?: 'v23.0'),
            'invoice_template' => (string) ($s->invoice_template ?? ''),
            'reservation_hotel_template' => (string) ($s->reservation_hotel_template ?? ''),
            'reservation_customer_template' => (string) ($s->reservation_customer_template ?? ''),
            'reservation_confirmed_template' => (string) ($s->reservation_confirmed_template ?? ''),
            'template_language' => (string) ($s->template_language ?: 'tr_TR'),
            'reservations_ready' => filled($s->reservation_hotel_template) && filled($s->reservation_customer_template) && filled($s->reservation_confirmed_template),
        ];
    }

    public static function saveFromAdmin(array $input): void
    {
        $s = static::current();
        foreach (['phone_number_id', 'access_token', 'api_version', 'invoice_template', 'reservation_hotel_template', 'reservation_customer_template', 'reservation_confirmed_template', 'template_language'] as $field) {
            $value = trim((string) ($input[$field] ?? ''));
            if ($value !== '') $s->{$field} = $value;
        }
        $s->save();
    }
}
