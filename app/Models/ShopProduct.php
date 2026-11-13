<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ShopProduct extends Model
{
    protected $guarded=[];
    protected $casts=['active'=>'boolean','price'=>'decimal:2','stock'=>'integer'];
    public function hotel(){return $this->belongsTo(Hotel::class);}
    public function orderItems(){return $this->hasMany(ShopOrderItem::class,'shop_product_id');}
    public function getRouteKeyName(): string{return 'slug';}
}
