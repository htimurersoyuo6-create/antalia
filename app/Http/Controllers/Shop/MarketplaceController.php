<?php
namespace App\Http\Controllers\Shop;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopOrderPaymentAttempt;
use App\Models\ShopProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketplaceController extends Controller
{
    private function cartData(Request $r): array { return (array)$r->session()->get('shop_cart', []); }
    public function index(Request $r) {
        $q=ShopProduct::query()->where('active',true)->where('stock','>',0)->whereHas('hotel',fn($h)=>$h->where('status','active')->where('shop_enabled',true));
        if($r->filled('q')) $q->where('name','like','%'.mb_substr($r->query('q'),0,80).'%');
        if($r->filled('category')) $q->where('category',$r->query('category'));
        $products=$q->with('hotel')->latest()->paginate(18)->withQueryString();
        $categories=ShopProduct::where('active',true)->where('stock','>',0)->whereHas('hotel',fn($h)=>$h->where('status','active')->where('shop_enabled',true))->distinct()->orderBy('category')->pluck('category');
        return view('shop.index',compact('products','categories'));
    }
    public function show(ShopProduct $product) {
        abort_unless($product->active && $product->stock>0 && $product->hotel?->status==='active' && $product->hotel?->shop_enabled,404);
        return view('shop.show',compact('product'));
    }
    public function add(Request $r, ShopProduct $product) {
        abort_unless($product->active && $product->stock>0 && $product->hotel?->shop_enabled && $product->hotel?->status==='active',404);
        $d=$r->validate(['quantity'=>'required|integer|min:1|max:20']); $cart=$this->cartData($r); $key=(string)$product->id;
        $cart[$key]=min($product->stock,(int)($cart[$key]??0)+(int)$d['quantity']); $r->session()->put('shop_cart',$cart);
        return redirect()->route('shop.cart')->with('ok','Ürün sepetinize eklendi.');
    }
    public function cart(Request $r) {
        $ids=array_map('intval',array_keys($this->cartData($r)));
        $products=ShopProduct::whereIn('id',$ids)->with('hotel')->get()->keyBy('id');
        $lines=[]; foreach($this->cartData($r) as $id=>$qty) if(isset($products[(int)$id]) && $products[(int)$id]->active && $products[(int)$id]->stock>0 && $products[(int)$id]->hotel?->status==='active' && $products[(int)$id]->hotel?->shop_enabled) $lines[]=['product'=>$products[(int)$id],'quantity'=>min((int)$qty,$products[(int)$id]->stock)];
        return view('shop.cart',compact('lines'));
    }
    public function updateCart(Request $r) {
        $d=$r->validate(['quantities'=>'required|array','quantities.*'=>'integer|min:0|max:20']); $cart=$this->cartData($r); $products=ShopProduct::whereIn('id',array_keys($d['quantities']))->get()->keyBy('id');
        foreach($d['quantities'] as $id=>$qty){$p=$products[(int)$id]??null;if(!$p||!$p->active||$p->stock<1||!$p->hotel()->where('status','active')->where('shop_enabled',true)->exists()||(int)$qty===0)unset($cart[(string)$id]);else $cart[(string)$id]=min((int)$qty,$p->stock);}
        $r->session()->put('shop_cart',$cart);return redirect()->route('shop.cart')->with('ok','Sepet güncellendi.');
    }
    public function checkout(Request $r) {
        $lines=$this->cartData($r); abort_if(!$lines,redirect()->route('shop.cart'));
        $products=ShopProduct::whereIn('id',array_map('intval',array_keys($lines)))->with('hotel')->get()->keyBy('id');
        $groups=[];foreach($lines as $id=>$qty){$p=$products[(int)$id]??null;if($p&&$p->active&&$p->hotel?->status==='active'&&$p->hotel?->shop_enabled&&$p->stock>0)$groups[$p->hotel_id]['items'][]=['product'=>$p,'quantity'=>min((int)$qty,$p->stock)];}
        abort_if(!$groups,redirect()->route('shop.cart')->with('error','Sepetinizde stokta olan ürün kalmadı.'));
        foreach($groups as $hid=>$group){$h=$group['items'][0]['product']->hotel;$groups[$hid]['hotel']=$h;$groups[$hid]['subtotal']=collect($group['items'])->sum(fn($i)=>$i['product']->price*$i['quantity']);}
        return view('shop.checkout', ['groups'=>$groups, 'paymentAvailable'=>config('services.iyzico.environment')==='sandbox' && str_starts_with((string)config('services.iyzico.api_key'),'sandbox-') && str_starts_with((string)config('services.iyzico.secret_key'),'sandbox-')]);
    }
    public function place(Request $r) {
        $identityNumber=(string)$r->input('identity_number',''); $r->request->remove('identity_number');
        $r->merge(['payment_method'=>$r->input('payment_method','pay_at_delivery')]);
        $rules=['buyer_name'=>'required|string|max:120','buyer_phone'=>'required|string|max:30','payment_method'=>'required|in:pay_at_delivery,iyzico_sandbox','customer_note'=>'nullable|string|max:1000','orders'=>'required|array','orders.*.fulfillment'=>'required|in:pickup,delivery','orders.*.city'=>'required_if:orders.*.fulfillment,delivery|nullable|string|max:100','orders.*.district'=>'required_if:orders.*.fulfillment,delivery|nullable|string|max:100','orders.*.address'=>'required_if:orders.*.fulfillment,delivery|nullable|string|max:500'];
        if($r->input('payment_method')==='iyzico_sandbox'){ $rules=array_merge($rules,['buyer_surname'=>'required|string|max:100','email'=>'required|email|max:190','buyer_address'=>'required|string|max:500','buyer_city'=>'required|string|max:100','zip_code'=>'nullable|string|max:20']); }
        $d=$r->validate($rules);
        if($d['payment_method']==='iyzico_sandbox'){ if(!(config('services.iyzico.environment')==='sandbox' && str_starts_with((string)config('services.iyzico.api_key'),'sandbox-') && str_starts_with((string)config('services.iyzico.secret_key'),'sandbox-'))) return back()->withErrors(['payment'=>'iyzico sandbox bilgileri yapılandırılmamış.'])->withInput(); if(!preg_match('/^[0-9]{11}$/D',$identityNumber)||!\App\Support\IdentityNumber::isValid($identityNumber)) return back()->withErrors(['identity_number'=>'Geçerli T.C. kimlik numarası girin.'])->withInput(); $d['identity_number']=$identityNumber; if(!\App\Support\PhoneNumber::normalize($d['buyer_phone'])) return back()->withErrors(['buyer_phone'=>'Geçerli cep telefonu girin.'])->withInput(); }
        $cart=$this->cartData($r);abort_if(!$cart,redirect()->route('shop.cart'));
        $batch=(string)Str::uuid();$made=[];
        DB::transaction(function()use($r,$d,$cart,$batch,&$made){
          $ids=array_map('intval',array_keys($cart));$products=ShopProduct::whereIn('id',$ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
          $groups=[];foreach($cart as $id=>$qty){$p=$products[(int)$id]??null;abort_unless($p&&$p->active,422,'Sepetteki bir ürün satıştan kalkmış.');$h=Hotel::whereKey($p->hotel_id)->where('status','active')->where('shop_enabled',true)->first();abort_unless($h,422,'Bir mağaza şu an sipariş alamıyor.');abort_if($p->stock<(int)$qty,422,$p->name.' için stok yetersiz.');$groups[$h->id]['hotel']=$h;$groups[$h->id]['items'][]=['product'=>$p,'quantity'=>(int)$qty];}
          foreach($groups as $hid=>$g){$h=$g['hotel'];$form=$d['orders'][$hid]??null;abort_unless($form,422,'Teslimat seçimi eksik.');$ful=$form['fulfillment'];abort_if($ful==='delivery'&&!in_array($h->shop_fulfillment,['delivery','both'],true),422,'Bu mağaza adrese teslimat sunmuyor.');abort_if($ful==='pickup'&&!in_array($h->shop_fulfillment,['pickup','both'],true),422,'Bu mağazadan teslim alma seçeneği yok.');
            $subtotal=0;foreach($g['items'] as $it)$subtotal+=(float)$it['product']->price*$it['quantity'];$fee=$ful==='delivery'&&(!is_numeric($h->shop_free_delivery_min)||$subtotal<(float)$h->shop_free_delivery_min)?(float)$h->shop_delivery_fee:0;
            $rate=max(0,min(100,(float)($h->commission_rate??0)));$order=ShopOrder::create(['user_id'=>$r->user()->id,'hotel_id'=>$h->id,'checkout_batch'=>$batch,'code'=>'APO-'.strtoupper(Str::random(8)),'status'=>'pending','payment_status'=>'unpaid','payment_method'=>$d['payment_method'],'fulfillment'=>$ful,'buyer_name'=>trim($d['buyer_name'].' '.($d['buyer_surname']??'')),'buyer_phone'=>$d['buyer_phone'],'city'=>$ful==='delivery'?$form['city']:null,'district'=>$ful==='delivery'?$form['district']:null,'delivery_address'=>$ful==='delivery'?$form['address']:null,'items_subtotal'=>$subtotal,'shipping_fee'=>$fee,'total'=>$subtotal+$fee,'commission_rate'=>$rate,'commission_amount'=>round($subtotal*$rate/100,2),'customer_note'=>$d['customer_note']??null]);
            foreach($g['items'] as $it){$p=$it['product'];$qty=$it['quantity'];$p->decrement('stock',$qty);ShopOrderItem::create(['shop_order_id'=>$order->id,'shop_product_id'=>$p->id,'name_snapshot'=>$p->name,'sku_snapshot'=>$p->sku,'image_snapshot'=>$p->image_path,'unit_price'=>$p->price,'quantity'=>$qty,'line_total'=>(float)$p->price*$qty]);}$made[]=$order;
          }
        });
        if($d['payment_method']==='iyzico_sandbox'){
            return app(ShopIyzicoPaymentController::class)->startForBatch($r,$batch,$d);
        }
        $r->session()->forget('shop_cart');
        foreach ($made as $order) {
            $order->load(['user', 'hotel.owner', 'items']);
            \App\Support\Bildirim::shopOrderPlaced($order);
        }
        return redirect()->route('shop.orders')->with('ok','Siparişiniz işletmelere iletildi. Ödeme, teslim alırken işletmeye yapılır.');
    }
    public function orders(Request $r){$orders=$r->user()->shopOrders()->with('hotel','items')->latest()->paginate(15);return view('shop.orders',compact('orders'));}
    public function order(Request $r,ShopOrder $order){abort_unless($order->user_id===$r->user()->id,404);$order->load('hotel','items');return view('shop.order',compact('order'));}
    public function cancel(Request $r, ShopOrder $order)
    {
        abort_unless($order->user_id === $r->user()->id, 404);
        DB::transaction(function () use ($order) {
            $o = ShopOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($o->status === 'pending' && !$o->paid_at, 422,
                'İşletme işleme aldıktan sonra sipariş iptal edilemez. İşletmeyle iletişime geçin.');
            $paymentInProgress = ShopOrderPaymentAttempt::query()
                ->where('checkout_batch', $o->checkout_batch)
                ->where('status', 'pending')
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->exists();
            abort_unless(!$paymentInProgress, 422,
                'Kart ödemeniz sonuçlanana kadar sipariş iptal edilemez. Ödeme başarısız olursa tekrar deneyin veya süresi dolunca iptal edin.');
            $this->restoreStock($o);
            $o->update(['status' => 'cancelled']);
        });
        $cancelled = ShopOrder::with(['user', 'hotel.owner'])->find($order->id);
        if ($cancelled) \App\Support\Bildirim::shopOrderCancelledByBuyer($cancelled);
        return back()->with('ok', 'Sipariş iptal edildi.');
    }
    public static function restoreStock(ShopOrder $order):void{if($order->stock_restored_at)return;foreach($order->items()->with('product')->get() as $i)if($i->product)$i->product->increment('stock',$i->quantity);$order->update(['stock_restored_at'=>now()]);}
}
