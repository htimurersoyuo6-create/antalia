<?php
namespace App\Http\Controllers;
use App\Models\Hotel;
use App\Models\TransferZoneRate;
use Illuminate\Http\Request;
class TransferZoneRateController extends Controller
{
    public function adminIndex(Request $request)
    {
        $rates=TransferZoneRate::with('hotel')->when($request->filled('hotel_id'),fn($q)=>$q->where('hotel_id',$request->integer('hotel_id')))->orderBy('hotel_id')->orderBy('from_zone')->paginate(30)->withQueryString();
        return view('transfer-zones.admin', ['rates'=>$rates,'hotels'=>Hotel::orderBy('name')->get(['id','name']),'selectedHotelId'=>$request->integer('hotel_id') ?: null]);
    }
    public function adminStore(Request $request)
    {
        $data=$request->validate(['hotel_id'=>['nullable','integer','exists:hotels,id'],'from_zone'=>['required','string','max:120'],'to_zone'=>['required','string','max:120','different:from_zone'],'fee'=>['required','numeric','min:0','max:100000'],'is_active'=>['nullable','boolean']]);
        $data['from_zone']=trim($data['from_zone']);$data['to_zone']=trim($data['to_zone']);$data['is_active']=$request->boolean('is_active');
        $rate=TransferZoneRate::query()->where('hotel_id',$data['hotel_id']??null)->whereRaw('LOWER(from_zone)=?',[mb_strtolower($data['from_zone'])])->whereRaw('LOWER(to_zone)=?',[mb_strtolower($data['to_zone'])])->first();
        if($rate)$rate->update($data);else TransferZoneRate::create($data);
        return back()->with('ok','Sabit transfer bölge tarifesi kaydedildi.');
    }
    public function adminDestroy(TransferZoneRate $rate){$rate->delete();return back()->with('ok','Tarife silindi.');}
    public function hotelIndex(Request $request, Hotel $hotel)
    {
        abort_unless($request->user()->isAdmin() || (int)$hotel->owner_id===(int)$request->user()->id,404);
        return view('transfer-zones.hotel',['hotel'=>$hotel,'rates'=>TransferZoneRate::where('hotel_id',$hotel->id)->orderBy('from_zone')->get()]);
    }
    public function hotelStore(Request $request, Hotel $hotel)
    {
        abort_unless($request->user()->isAdmin() || (int)$hotel->owner_id===(int)$request->user()->id,404);
        $data=$request->validate(['from_zone'=>['required','string','max:120'],'to_zone'=>['required','string','max:120','different:from_zone'],'fee'=>['required','numeric','min:0','max:100000'],'is_active'=>['nullable','boolean']]);
        $data['from_zone']=trim($data['from_zone']);$data['to_zone']=trim($data['to_zone']);$data['is_active']=$request->boolean('is_active');
        $rate=TransferZoneRate::where('hotel_id',$hotel->id)->whereRaw('LOWER(from_zone)=?',[mb_strtolower($data['from_zone'])])->whereRaw('LOWER(to_zone)=?',[mb_strtolower($data['to_zone'])])->first();
        if($rate)$rate->update($data);else $hotel->transferZoneRates()->create($data);
        return back()->with('ok','Tesisin sabit bölge tarifesi kaydedildi.');
    }
    public function hotelDestroy(Request $request, Hotel $hotel, TransferZoneRate $rate)
    {
        abort_unless($request->user()->isAdmin() || (int)$hotel->owner_id===(int)$request->user()->id,404);
        abort_unless((int)$rate->hotel_id===(int)$hotel->id,404);$rate->delete();return back()->with('ok','Tarife silindi.');
    }
}
