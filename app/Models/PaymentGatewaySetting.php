<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGatewaySetting extends Model
{
    protected $primaryKey = 'provider';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'merchant_id' => 'encrypted',
            'merchant_key' => 'encrypted',
            'merchant_salt' => 'encrypted',
            'test_mode' => 'boolean',
        ];
    }
}
