<?php
namespace App\Console\Commands;
use App\Http\Controllers\Shop\MarketplaceController;
use App\Models\ShopOrder;
use App\Models\ShopOrderPaymentAttempt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
class ExpireShopPayments extends Command
{
    protected $signature='shop:expire-payments';
    protected $description='Süresi dolan iyzico sandbox mağaza ödemelerini iptal eder ve stoğu iade eder';
    public function handle(): int
    {
        $expired=0;
        ShopOrderPaymentAttempt::query()->where('status','pending')->whereNotNull('expires_at')->where('expires_at','<=',now())->chunkById(100,function($rows)use(&$expired){
            foreach($rows as $candidate){
                $done=DB::transaction(function()use($candidate){
                    // Match checkout start/callback: lock orders in a stable order, then the attempt.
                    $orders=ShopOrder::where('checkout_batch',$candidate->checkout_batch)->orderBy('id')->lockForUpdate()->get();
                    $attempt=ShopOrderPaymentAttempt::whereKey($candidate->id)->lockForUpdate()->first();
                    if(!$attempt||$attempt->status!=='pending'||!$attempt->expires_at||$attempt->expires_at->isFuture()) return false;
                    foreach($orders as $order){
                        if($order->payment_status==='paid') return false;
                    }
                    foreach($orders as $order){
                        if($order->status==='pending'){MarketplaceController::restoreStock($order);$order->update(['status'=>'cancelled']);}
                    }
                    $attempt->update(['status'=>'expired','token'=>null,'checkout_url'=>null,'error_code'=>'checkout_expired','error_message'=>'Ödeme süresi doldu.']);
                    return true;
                });
                if($done)$expired++;
            }
        });
        $this->info("Süresi dolan ödeme: {$expired}");
        return self::SUCCESS;
    }
}
