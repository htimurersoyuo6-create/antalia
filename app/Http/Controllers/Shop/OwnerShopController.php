<?php
namespace App\Http\Controllers\Shop;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\ShopOrder;
use App\Models\ShopProduct;
use App\Models\HotelFinancialEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
class OwnerShopController extends Controller
{
 private function own(Request $r,Hotel $hotel):void{abort_unless($r->user()->isAdmin()||$hotel->owner_id===$r->user()->id,403);}
 public function products(Request $r,Hotel $hotel){$this->own($r,$hotel);$products=$hotel->shopProducts()->latest()->paginate(20);return view('panel.shop.products',compact('hotel','products'));}
 public function save(Request $r,Hotel $hotel,?ShopProduct $product=null){$this->own($r,$hotel);if($product)abort_unless($product->hotel_id===$hotel->id,404);
  $d=$r->validate(['name'=>'required|string|max:160','category'=>'required|string|max:80','sku'=>'nullable|string|max:80','description'=>'nullable|string|max:5000','price'=>'required|numeric|min:0.01|max:99999999','stock'=>'required|integer|min:0|max:1000000','active'=>'nullable|boolean','image'=>'nullable|image|mimes:jpg,jpeg,png,webp|max:5120']);
  $base=Str::slug($d['name']);$slug=$product?->slug??$base.'-'.Str::lower(Str::random(6));$d['slug']=$slug;$d['hotel_id']=$hotel->id;$d['active']=$r->boolean('active');
  if($r->hasFile('image')){$oldImagePath=$product?->image_path;$d['image_path']=\App\Support\ImageOptimizer::storeUploaded($r->file('image'),'public','shop-products/'.$hotel->id,'image');if($oldImagePath)Storage::disk('public')->delete($oldImagePath);}unset($d['image']);
  $product?$product->update($d):ShopProduct::create($d);$hotel->update(['shop_enabled'=>true]);return back()->with('ok','Ürün kaydedildi; mağazanız yayına açıldı.');
 }
 public function delete(Request $r,Hotel $hotel,ShopProduct $product){$this->own($r,$hotel);abort_unless($product->hotel_id===$hotel->id,404);$product->update(['active'=>false]);return back()->with('ok','Ürün satıştan kaldırıldı; geçmiş sipariş kayıtları korundu.');}
 public function orders(Request $r,Hotel $hotel){$this->own($r,$hotel);$orders=$hotel->shopOrders()->with('user','items')->latest()->paginate(20);return view('panel.shop.orders',compact('hotel','orders'));}
 public function updateOrder(Request $r,Hotel $hotel,ShopOrder $order){$this->own($r,$hotel);abort_unless($order->hotel_id===$hotel->id,404);$d=$r->validate(['action'=>'required|in:confirm,prepare,ship,deliver,cancel,mark_paid','carrier'=>'nullable|string|max:80','tracking_code'=>'nullable|string|max:120','tracking_url'=>'nullable|url:https|max:500','seller_note'=>'nullable|string|max:1000']);
  DB::transaction(function()use($order,$d){$o=ShopOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();$next=match($d['action']){'confirm'=>in_array($o->status,['pending'],true)?'confirmed':null,'prepare'=>in_array($o->status,['confirmed'],true)?'preparing':null,'ship'=>in_array($o->status,['preparing'],true)&&$o->fulfillment==='delivery'?'shipped':null,'deliver'=>in_array($o->status,['preparing','shipped'],true)?'delivered':null,'cancel'=>in_array($o->status,['pending','confirmed'],true)&&!$o->paid_at?'cancelled':null,'mark_paid'=>$o->status==='delivered'&&$o->payment_method==='pay_at_delivery'&&$o->payment_status!=='paid'?'paid':null};abort_unless($next,422,'Bu sipariş için bu işlem yapılamıyor.');
   if($d['action']==='cancel'){MarketplaceController::restoreStock($o);$o->update(['status'=>'cancelled','seller_note'=>$d['seller_note']??$o->seller_note]);return;}
   if($d['action']==='mark_paid'){ $o->update(['payment_status'=>'paid','paid_at'=>now(),'seller_note'=>$d['seller_note']??$o->seller_note]); if(!HotelFinancialEntry::where('hotel_id',$o->hotel_id)->where('description','Pazaryeri komisyonu · '.$o->code)->exists())HotelFinancialEntry::create(['hotel_id'=>$o->hotel_id,'type'=>'expense','category'=>'Pazaryeri komisyonu','description'=>'Pazaryeri komisyonu · '.$o->code,'amount'=>$o->commission_amount,'status'=>'pending','due_date'=>now()->toDateString()]);return;}
   $attrs=['status'=>$next,'seller_note'=>$d['seller_note']??$o->seller_note];if($d['action']==='ship'){$attrs['carrier']=$d['carrier']??null;$attrs['tracking_code']=$d['tracking_code']??null;$attrs['tracking_url']=$d['tracking_url']??null;$attrs['shipped_at']=now();}if($d['action']==='deliver')$attrs['delivered_at']=now();$o->update($attrs);
  });
  $updated=ShopOrder::with(['user','hotel.owner','items'])->find($order->id);
  if($updated) \App\Support\Bildirim::shopOrderStatus($updated,$d['action']);
  return back()->with('ok','Sipariş güncellendi.');
 }
}
