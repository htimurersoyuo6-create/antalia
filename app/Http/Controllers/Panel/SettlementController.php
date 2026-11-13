<?php
namespace App\Http\Controllers\Panel;
use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class SettlementController extends Controller
{
    public function index(Request $request)
    {
        $hotelIds=$request->user()->hotels()->pluck('id');
        $filters=$request->validate([
            'hotel_id'=>['nullable','integer',Rule::in($hotelIds->all())],
            'payout_status'=>['nullable',Rule::in(['pending','processing','paid'])],
            'start_date'=>['nullable','date_format:Y-m-d'],
            'end_date'=>['nullable','date_format:Y-m-d','after_or_equal:start_date'],
        ]);
        $base=PaymentTransaction::query()->whereIn('hotel_id',$hotelIds)->where('status','succeeded')
            ->when($filters['hotel_id']??null,fn($q,$id)=>$q->where('hotel_id',$id))
            ->when($filters['payout_status']??null,fn($q,$s)=>$q->where('payout_status',$s))
            ->when($filters['start_date']??null,fn($q,$d)=>$q->whereDate('paid_at','>=',$d))
            ->when($filters['end_date']??null,fn($q,$d)=>$q->whereDate('paid_at','<=',$d));
        $summary=[
            'earned'=>(clone $base)->sum('hotel_payout'),
            'pending'=>(clone $base)->whereIn('payout_status',['pending','processing'])->sum('hotel_payout'),
            'completed'=>(clone $base)->where('payout_status','paid')->sum('hotel_payout'),
        ];
        $transactions=(clone $base)->with(['hotel:id,name','reservation:id,code,check_in,check_out'])->latest('paid_at')->paginate(25)->withQueryString();
        $hotels=$request->user()->hotels()->orderBy('name')->get(['id','name']);
        return view('hotel.finance.index',compact('transactions','summary','hotels','filters'));
    }
}
