<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FcmSubscription extends Model
{
    protected $guarded = [];

    protected $casts = ['last_sent_at' => 'datetime'];
}
