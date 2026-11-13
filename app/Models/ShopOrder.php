<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ShopOrder extends Model
{
    protected $guarded=[];
    protected $casts=['items_subtotal'=>'decimal:2','shipping_fee'=>'decimal:2','total'=>'decimal:2','commission_rate'=>'decimal:2','commission_amount'=>'decimal:2','paid_at'=>'datetime','shipped_at'=>'datetime','delivered_at'=>'datetime','stock_restored_at'=>'datetime'];
    public const STATUS=['pending'=>'Onay bekliyor','confirmed'=>'Onaylandı','preparing'=>'Hazırlanıyor','shipped'=>'Kargoya verildi','delivered'=>'Teslim edildi','cancelled'=>'İptal edildi'];
    public function user(){return $this->belongsTo(User::class);}
    public function hotel(){return $this->belongsTo(Hotel::class);}
    public function items(){return $this->hasMany(ShopOrderItem::class);}
    public function getStatusLabelAttribute():string{return self::STATUS[$this->status]??$this->status;}
}
