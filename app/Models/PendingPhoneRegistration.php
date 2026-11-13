<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PendingPhoneRegistration extends Model
{
    protected $guarded = [];
    protected $casts = [
        'registration_payload' => 'encrypted:array',
        'expires_at' => 'datetime',
        'last_sent_at' => 'datetime',
    ];
}
