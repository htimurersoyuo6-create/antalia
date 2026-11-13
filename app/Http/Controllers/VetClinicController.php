<?php
namespace App\Http\Controllers;
use App\Models\District;
use App\Models\Hotel;
use App\Models\Veterinary;
use Illuminate\Http\Request;
class VetClinicController extends Controller
{
    public function adminIndex(Request $request)
    {
        abort_unless($request->user()?->isAdmin(),404);
        return view('veterinaries.admin',['clinics'=>Veterinary::with('district')->orderBy('name')->paginate(30)]);
    }
    public function adminUpdate(Request $request, Veterinary $clinic)
    {
        abort_unless($request->user()?->isAdmin(),404);
        $data=$request->validate([
            'lat'=>['nullable','numeric','between:-90,90'],
            'lng'=>['nullable','numeric','between:-180,180'],
            'is_24h'=>['nullable','boolean'],'is_published'=>['nullable','boolean'],
        ]);
        if(($data['lat']??null)===null xor ($data['lng']??null)===null)return back()->withInput()->withErrors(['lat'=>'Harita konumu için enlem ve boylam birlikte girilmelidir.']);
        if($request->boolean('is_24h')&&(($data['lat']??null)===null||($data['lng']??null)===null))return back()->withInput()->withErrors(['lat'=>'7/24 klinik haritada gösterilebilmesi için enlem ve boylam gereklidir.']);
        $clinic->update(['lat'=>$data['lat']??null,'lng'=>$data['lng']??null,'is_24h'=>$request->boolean('is_24h'),'is_published'=>$request->boolean('is_published')]);
        return back()->with('ok','Klinik kaydı güncellendi.');
    }

    public function nearby(Request $request)
    {
        $data=$request->validate([
            'lat'=>['nullable','numeric','between:-90,90'],
            'lng'=>['nullable','numeric','between:-180,180'],
            'district'=>['nullable','string','max:100'],
            'hotel_id'=>['nullable','integer','exists:hotels,id'],
        ]);
        if((($data['lat']??null)!==null) xor (($data['lng']??null)!==null)) return response()->json(['message'=>'Lat ve lng birlikte gönderilmelidir.'],422);
        $searchHotelId=null;
        if(!empty($data['hotel_id'])){
            $hotel=Hotel::findOrFail((int)$data['hotel_id']);
            abort_unless($request->user() && ($request->user()->isAdmin() || (int)$hotel->owner_id===(int)$request->user()->id),404);
            $searchHotelId=(int)$hotel->id;
            if(!isset($data['lat'],$data['lng']) && is_numeric($hotel->lat) && is_numeric($hotel->lng)){
                $data['lat']=(float)$hotel->lat;$data['lng']=(float)$hotel->lng;
            }
        } elseif(!isset($data['lat'],$data['lng']) && $request->user()?->role==='hotel_owner') {
            $hotel=$request->user()->hotels()->whereNotNull('lat')->whereNotNull('lng')->first();
            if($hotel){$searchHotelId=(int)$hotel->id;$data['lat']=(float)$hotel->lat;$data['lng']=(float)$hotel->lng;}
        }
        $districtId=null;$district=trim((string)($data['district']??''));
        if($district!==''){
            $districtId=District::where('slug',$district)->orWhere('name',$district)->value('id');
            if(!$districtId)return $request->expectsJson()?response()->json(['clinics'=>[]]):view('veterinaries.nearby',['clinics'=>collect(),'district'=>$district,'searchHotelId'=>$searchHotelId]);
        }
        // Emergency list shows clinics explicitly recorded as 24/7; no non-24h listings.
        $query=Veterinary::published()->where('is_24h',true)->with('district:id,name,slug')->whereNotNull('lat')->whereNotNull('lng')->orderBy('name');
        if($districtId)$query->where('district_id',$districtId);
        $rows=$query->get()->map(function($clinic)use($data){
            $distance=null;
            if(isset($data['lat'],$data['lng'])){
                $dLat=deg2rad((float)$clinic->lat-(float)$data['lat']);$dLng=deg2rad((float)$clinic->lng-(float)$data['lng']);
                $a=sin($dLat/2)**2+cos(deg2rad((float)$data['lat']))*cos(deg2rad((float)$clinic->lat))*sin($dLng/2)**2;
                $distance=round(6371.0088*2*atan2(sqrt($a),sqrt(1-$a)),2);
            }
            $lat=(float)$clinic->lat;$lng=(float)$clinic->lng;
            return ['id'=>$clinic->id,'name'=>$clinic->name,'district'=>$clinic->district?->name,'address'=>$clinic->address,'phone'=>$clinic->phone,'is_24_7'=>true,'latitude'=>$lat,'longitude'=>$lng,'distance_km'=>$distance,'url'=>route('vets.show',$clinic->slug),'maps_url'=>'https://www.openstreetmap.org/directions?to='.rawurlencode($lat.','.$lng),'call_url'=>$clinic->telHref()];
        })->sortBy(fn($clinic)=>$clinic['distance_km']??PHP_FLOAT_MAX)->values();
        if($request->expectsJson()||$request->query('format')==='json')return response()->json(['clinics'=>$rows,'origin'=>isset($data['lat'],$data['lng'])?['lat'=>$data['lat'],'lng'=>$data['lng']]:null])->header('Cache-Control','no-store');
        return view('veterinaries.nearby',['clinics'=>$rows,'district'=>$district,'searchHotelId'=>$searchHotelId]);
    }
}
