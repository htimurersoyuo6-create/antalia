<?php
namespace App\Http\Controllers\Shop;
use App\Http\Controllers\Controller;
use App\Models\HotelFinancialEntry;
use App\Models\ShopOrder;
use App\Models\ShopOrderPaymentAttempt;
use App\Support\IdentityNumber;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShopIyzicoPaymentController extends Controller
{
    private function options(): ?\Iyzipay\Options
    {
        $apiKey=(string)config('services.iyzico.api_key'); $secret=(string)config('services.iyzico.secret_key');
        $base=rtrim((string)config('services.iyzico.base_url'),'/');
        if(config('services.iyzico.environment')!=='sandbox'||$base!=='https://sandbox-api.iyzipay.com'||!str_starts_with($apiKey,'sandbox-')||!str_starts_with($secret,'sandbox-')) return null;
        $o=new \Iyzipay\Options(); $o->setApiKey($apiKey); $o->setSecretKey($secret); $o->setBaseUrl($base); return $o;
    }

    public function startForBatch(Request $request,string $batch,array $buyer)
    {
        $options=$this->options();
        if(!$options) return back()->withErrors(['payment'=>'iyzico sandbox anahtarları yapılandırılmamış. Kart ödemesi başlatılmadı.'])->withInput();
        $phone=PhoneNumber::normalize($buyer['buyer_phone']??'');
        if(!$phone||!IdentityNumber::isValid($buyer['identity_number']??'')) return back()->withErrors(['payment'=>'Telefon veya T.C. kimlik numarası doğrulanamadı. Sipariş oluşturulmadı.'])->withInput();
        $buyer['buyer_phone']=$phone;
        $result=DB::transaction(function()use($batch,$request){
            $orders=ShopOrder::where('checkout_batch',$batch)->orderBy('id')->lockForUpdate()->get();
            if($orders->isEmpty()||$orders->contains(fn($o)=>$o->user_id!==$request->user()->id||$o->status!=='pending'||$o->payment_status==='paid')) return ['error'=>true];
            $amount=round((float)$orders->sum('total'),2);
            if($amount<=0) return ['error'=>true];
            $a=ShopOrderPaymentAttempt::firstOrNew(['checkout_batch'=>$batch]);
            if($a->exists&&$a->status==='pending'&&$a->checkout_url&&$a->expires_at?->isFuture()) return ['url'=>$a->checkout_url];
            $a->forceFill(['id'=>$a->id?: (string)Str::uuid(),'conversation_id'=>'APO-SHOP-'.Str::upper(Str::random(20)),'token'=>null,'checkout_url'=>null,'amount'=>$amount,'currency'=>'TRY','status'=>'initializing','attempt_count'=>((int)$a->attempt_count)+1,'provider_payment_id'=>null,'error_code'=>null,'error_message'=>null,'paid_at'=>null,'expires_at'=>now()->addMinutes(30)])->save();
            return ['attempt'=>$a->fresh()];
        });
        if(!empty($result['error'])) return back()->withErrors(['payment'=>'Sipariş ödeme için uygun değil.']);
        if(!empty($result['url'])) { $request->session()->forget('shop_cart'); return redirect()->away($result['url']); }
        $attempt=$result['attempt'];
        try {
            $orders=ShopOrder::where('checkout_batch',$batch)->with('items')->get();
            $r=new \Iyzipay\Request\CreateCheckoutFormInitializeRequest();
            $r->setLocale(\Iyzipay\Model\Locale::TR); $r->setConversationId($attempt->conversation_id);
            $r->setPrice(number_format((float)$attempt->amount,2,'.','')); $r->setPaidPrice(number_format((float)$attempt->amount,2,'.',''));
            $r->setCurrency(\Iyzipay\Model\Currency::TL); $r->setBasketId($batch); $r->setPaymentGroup(\Iyzipay\Model\PaymentGroup::PRODUCT);
            $r->setCallbackUrl(route('shop.iyzico.callback',[],true)); $r->setEnabledInstallments([1,2,3,6,9,12]);
            $full=trim(($buyer['buyer_name']??'').' '.($buyer['buyer_surname']??''));
            $b=new \Iyzipay\Model\Buyer(); $b->setId((string)$request->user()->id); $b->setName($buyer['buyer_name']); $b->setSurname($buyer['buyer_surname']);
            $b->setIdentityNumber($buyer['identity_number']); $b->setEmail($buyer['email']); $b->setGsmNumber($phone); $b->setRegistrationAddress($buyer['buyer_address']);
            $b->setCity($buyer['buyer_city']); $b->setCountry('Turkey'); $b->setZipCode($buyer['zip_code']??'07000'); $b->setIp((string)$request->ip());
            $b->setRegistrationDate(optional($request->user()->created_at)->format('Y-m-d H:i:s')??now()->format('Y-m-d H:i:s')); $b->setLastLoginDate(now()->format('Y-m-d H:i:s')); $r->setBuyer($b);
            $address=new \Iyzipay\Model\Address(); $address->setContactName($full); $address->setCity($buyer['buyer_city']); $address->setCountry('Turkey'); $address->setAddress($buyer['buyer_address']); $address->setZipCode($buyer['zip_code']??'07000'); $r->setShippingAddress($address); $r->setBillingAddress($address);
            $items=[];
            foreach($orders as $order){
                foreach($order->items as $line){$i=new \Iyzipay\Model\BasketItem(); $i->setId('P'.$line->id); $i->setName(mb_substr($line->name_snapshot,0,100)); $i->setCategory1('Pet Ürünleri'); $i->setItemType(\Iyzipay\Model\BasketItemType::PHYSICAL); $i->setPrice(number_format((float)$line->line_total,2,'.','')); $items[]=$i;}
                if((float)$order->shipping_fee>0){$i=new \Iyzipay\Model\BasketItem(); $i->setId('S'.$order->id); $i->setName('Teslimat ücreti'); $i->setCategory1('Teslimat'); $i->setItemType(\Iyzipay\Model\BasketItemType::PHYSICAL); $i->setPrice(number_format((float)$order->shipping_fee,2,'.','')); $items[]=$i;}
            }
            $r->setBasketItems($items); $form=\Iyzipay\Model\CheckoutFormInitialize::create($r,$options);
            $token=(string)$form->getToken(); $signature=(string)$form->getSignature(); $url=(string)$form->getPaymentPageUrl();
            $sig=bin2hex(hash_hmac('sha256',implode(':',[$attempt->conversation_id,$token]),$options->getSecretKey(),true)); $host=strtolower((string)parse_url($url,PHP_URL_HOST));
            if($form->getStatus()!=='success'||$token===''||$signature===''||!hash_equals($sig,$signature)||parse_url($url,PHP_URL_SCHEME)!=='https'||!str_ends_with($host,'.iyzipay.com')) throw new \RuntimeException('checkout_init_failed');
            $attempt->update(['token'=>$token,'checkout_url'=>$url,'status'=>'pending']);
            $request->session()->forget('shop_cart');
            return redirect()->away($url);
        } catch(\Throwable $e) {
            $attempt->update(['status'=>'failed','error_code'=>'checkout_init_failed','error_message'=>'Sandbox ödeme formu başlatılamadı.']);
            DB::transaction(function()use($batch){foreach(ShopOrder::where('checkout_batch',$batch)->lockForUpdate()->get() as $o){MarketplaceController::restoreStock($o);$o->update(['status'=>'cancelled']);}});
            return back()->withErrors(['payment'=>'iyzico sandbox ödeme formu başlatılamadı; sipariş iptal edildi ve stok geri açıldı.']);
        }
    }

    public function callback(Request $request)
    {
        $token=(string)$request->input('token'); abort_if($token===''||strlen($token)>128,404);
        $attempt=ShopOrderPaymentAttempt::where('token',$token)->firstOrFail();
        if($attempt->status==='paid') return redirect()->route('shop.orders')->with('ok','Mağaza siparişinizin ödemesi zaten doğrulanmış.');
        $options=$this->options(); abort_unless($options,503,'iyzico sandbox yapılandırması kullanılamıyor.');
        try {$r=new \Iyzipay\Request\RetrieveCheckoutFormRequest(); $r->setLocale(\Iyzipay\Model\Locale::TR); $r->setConversationId($attempt->conversation_id); $r->setToken($token); $payment=\Iyzipay\Model\CheckoutForm::retrieve($r,$options);} catch(\Throwable) {return redirect()->route('login')->withErrors(['payment'=>'Ödeme sonucu sağlayıcıdan doğrulanamadı.']);}
        $parts=[(string)$payment->getPaymentStatus(),(string)$payment->getPaymentId(),(string)$payment->getCurrency(),(string)$payment->getBasketId(),(string)$payment->getConversationId(),(string)$payment->getPaidPrice(),(string)$payment->getPrice(),(string)$payment->getToken()];
        $sig=bin2hex(hash_hmac('sha256',implode(':',$parts),$options->getSecretKey(),true));
        $valid=(string)$payment->getSignature()!==''&&hash_equals($sig,(string)$payment->getSignature());
        $paid=$payment->getStatus()==='success'&&$payment->getPaymentStatus()==='SUCCESS'&&(string)$payment->getFraudStatus()==='1'&&(string)$payment->getToken()===$token&&(string)$payment->getConversationId()===$attempt->conversation_id&&(string)$payment->getBasketId()===$attempt->checkout_batch&&(string)$payment->getCurrency()===(string)\Iyzipay\Model\Currency::TL&&(int)round((float)$payment->getPaidPrice()*100)===(int)round((float)$attempt->amount*100)&&(int)round((float)$payment->getPrice()*100)===(int)round((float)$attempt->amount*100)&&$valid;
        $orders=[];
        DB::transaction(function()use($attempt,$payment,$paid,&$orders){
            // Match checkout start/expiry order: lock every order by id, then its payment attempt.
            $batch=ShopOrder::where('checkout_batch',$attempt->checkout_batch)->orderBy('id')->lockForUpdate()->get();
            $a=ShopOrderPaymentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail(); if($a->status==='paid')return;
            if(!$paid){$a->update(['status'=>'failed','provider_payment_id'=>(string)$payment->getPaymentId(),'error_code'=>'provider_result_invalid','error_message'=>'Ödeme sonucu doğrulanamadı.']); foreach($batch as $o){if($o->status==='pending'&&$o->payment_status!=='paid'){MarketplaceController::restoreStock($o);$o->update(['status'=>'cancelled']);}} return;}
            foreach($batch as $o){if($o->payment_status==='paid')continue;$o->update(['payment_status'=>'paid','payment_method'=>'iyzico_sandbox','paid_at'=>now()]);
                HotelFinancialEntry::firstOrCreate(['hotel_id'=>$o->hotel_id,'category'=>'Mağaza tahsilatı','description'=>'Sandbox kart tahsilatı · '.$o->code],['created_by'=>$o->user_id,'type'=>'income','amount'=>$o->total,'status'=>'pending','due_date'=>today(),'paid_at'=>null,'payment_method'=>'iyzico sandbox','reference'=>(string)$payment->getPaymentId(),'notes'=>'Sandbox test kaydı; işletme ödemesi/gerçek para transferi değildir.']);
                if((float)$o->commission_amount>0) HotelFinancialEntry::firstOrCreate(['hotel_id'=>$o->hotel_id,'category'=>'Pazaryeri komisyonu','description'=>'Pazaryeri komisyonu · '.$o->code],['created_by'=>$o->user_id,'type'=>'expense','amount'=>$o->commission_amount,'status'=>'pending','due_date'=>today(),'payment_method'=>'iyzico sandbox','reference'=>(string)$payment->getPaymentId(),'notes'=>'Sandbox test kaydı; gerçek tahsilat veya mahsup değildir.']);
                $orders[]=$o->id;
            }
            $a->update(['status'=>'paid','provider_payment_id'=>(string)$payment->getPaymentId(),'error_code'=>null,'error_message'=>null,'paid_at'=>now()]);
        });
        if($paid){foreach(ShopOrder::with(['user','hotel.owner','items'])->whereIn('id',$orders)->get() as $o)\App\Support\Bildirim::shopOrderPlaced($o);return redirect()->route('shop.orders')->with('ok','iyzico sandbox ödemesi doğrulandı; siparişler işletmelere iletildi.');}
        return redirect()->route('login')->withErrors(['payment'=>'Ödeme tamamlanmadı veya doğrulanamadı; sipariş iptal edildi ve stok iade edildi.']);
    }
}
