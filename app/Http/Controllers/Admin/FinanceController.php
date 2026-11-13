<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class FinanceController extends Controller
{
    private function filtered(array $filters)
    {
        return PaymentTransaction::query()->where('status','succeeded')
            ->when($filters['hotel_id'] ?? null, fn($q,$id)=>$q->where('hotel_id',$id))
            ->when($filters['payout_status'] ?? null, fn($q,$s)=>$q->where('payout_status',$s))
            ->when($filters['start_date'] ?? null, fn($q,$d)=>$q->whereDate('paid_at','>=',$d))
            ->when($filters['end_date'] ?? null, fn($q,$d)=>$q->whereDate('paid_at','<=',$d));
    }
    public function index(Request $request)
    {
        $filters=$request->validate([
            'hotel_id'=>['nullable','integer','exists:hotels,id'],
            'payout_status'=>['nullable',Rule::in(['pending','processing','paid'])],
            'start_date'=>['nullable','date_format:Y-m-d'],
            'end_date'=>['nullable','date_format:Y-m-d','after_or_equal:start_date'],
        ]);
        $base=$this->filtered($filters);
        $summary=['gross'=>(clone $base)->sum('gross_amount'),'commission'=>(clone $base)->sum('commission_amount'),'payout'=>(clone $base)->sum('hotel_payout'),'insurance_provider'=>(clone $base)->sum('insurance_provider_amount')];
        $transactions=(clone $base)->with(['hotel:id,name','reservation:id,code,vip_tier','user:id,name,email'])->latest('paid_at')->paginate(30)->withQueryString();
        $hotels=Hotel::orderBy('name')->get(['id','name']);
        return view('admin.finance.index',compact('transactions','summary','hotels','filters'));
    }
    public function markAsPaid(Request $request, PaymentTransaction $transaction)
    {
        $data=$request->validate(['payout_reference'=>['required','string','min:3','max:120']]);
        $error=DB::transaction(function() use($transaction,$data){
            $locked=PaymentTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            if($locked->status!=='succeeded') return 'Başarılı olmayan tahsilat için hakediş ödenemez.';
            if($locked->payout_status==='paid') return 'Bu hakediş daha önce ödendi.';
            $locked->update(['payout_status'=>'paid','payout_at'=>now(),'payout_reference'=>trim($data['payout_reference'])]);
            return null;
        });
        return $error ? back()->withErrors(['payout_reference'=>$error]) : back()->with('ok','Otellere hakediş ödendi olarak kaydedildi.');
    }
}
