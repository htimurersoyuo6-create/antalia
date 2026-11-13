<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ShopOrderPaymentAttempt extends Model
{
 protected $guarded=[];
 public $incrementing=false;
 protected $keyType='string';
 protected $casts=['amount'=>'decimal:2','paid_at'=>'datetime','expires_at'=>'datetime'];
}
