<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ShopOrderItem extends Model
{
    protected $guarded=[];
    protected $casts=['unit_price'=>'decimal:2','line_total'=>'decimal:2','quantity'=>'integer'];
    public function order(){return $this->belongsTo(ShopOrder::class,'shop_order_id');}
    public function product(){return $this->belongsTo(ShopProduct::class,'shop_product_id');}
}
