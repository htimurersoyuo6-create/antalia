<?php
namespace App\Services;
use App\Models\Hotel;
use App\Models\TransferZoneRate;
use App\Models\District;
use Illuminate\Validation\ValidationException;
class PetTransferPricingService
{
    public function __construct(private OsrmRoutingService $routing){}
    public function quote(Hotel $hotel,string $address,string $type,?string $returnAddress=null):array
    {
        $hotel->loadMissing(['district','city']);
        if(!$hotel->transfer_enabled)throw ValidationException::withMessages(['transfer_requested'=>'Bu tesis transfer sunmuyor.']);
        if($hotel->transfer_mode!=='paid')return ['distance_km'=>null,'fee'=>0.0,'pricing_source'=>'free'];
        $type=$type==='one_way'?'one_way_pickup':$type;
        if(!in_array($type,['one_way_pickup','one_way_dropoff','round_trip'],true))throw ValidationException::withMessages(['transfer_type'=>'Transfer yönünü seçin.']);
        $address=trim($address);
        if($address==='')throw ValidationException::withMessages(['transfer_pickup_address'=>'Transfer adresini girin.']);
        if($type==='round_trip'&&trim((string)$returnAddress)==='')throw ValidationException::withMessages(['transfer_return_address'=>'Gidiş dönüş için dönüş adresini girin.']);
        $hotelAddress=trim(implode(', ',array_filter([$hotel->address,$hotel->district?->name,$hotel->city?->name,'Antalya','Türkiye'])));
        if($hotelAddress==='')throw ValidationException::withMessages(['transfer_requested'=>'Tesis adresi eksik; transfer fiyatı hesaplanamıyor.']);
        $legs=$type==='one_way_dropoff'?[[$hotelAddress,$address]]:[[$address,$hotelAddress]];
        if($type==='round_trip')$legs[]=[$hotelAddress,trim((string)$returnAddress)];
        $totalKm=0.0;$fixedTotal=0.0;$fixedCount=0;
        foreach($legs as [$from,$to]){
            try{$a=$this->routing->geocode($from);$b=$this->routing->geocode($to);$totalKm+=$this->routing->drivingDistanceKm($a,$b);}
            catch(ValidationException $e){$rate=$this->findZoneRate($hotel,$from,$to);if(!$rate)throw $e;$fixedTotal+=(float)$rate->fee;$fixedCount++;}
        }
        $distanceKm=round($totalKm,2);
        if($fixedCount){$routedLegs=count($legs)-$fixedCount;$fee=$fixedTotal+($routedLegs?(float)config('booking.transfer_base_fee',150)+$distanceKm*(float)config('booking.transfer_per_km',15):0);return ['distance_km'=>$distanceKm?:null,'fee'=>round($fee,2),'pricing_source'=>'district_matrix'];}
        return ['distance_km'=>$distanceKm,'fee'=>round((float)config('booking.transfer_base_fee',150)+$distanceKm*(float)config('booking.transfer_per_km',15),2),'pricing_source'=>'osrm'];
    }
    private function findZoneRate(Hotel $hotel,string $fromAddress,string $toAddress):?TransferZoneRate
    {
        $from=$this->zoneInAddress($fromAddress,$hotel);$to=$this->zoneInAddress($toAddress,$hotel);if(!$from||!$to)return null;
        $query=fn($a,$b)=>TransferZoneRate::query()->where('is_active',true)->where(fn($q)=>$q->whereNull('hotel_id')->orWhere('hotel_id',$hotel->id))->whereRaw('LOWER(from_zone)=?',[mb_strtolower($a)])->whereRaw('LOWER(to_zone)=?',[mb_strtolower($b)])->orderByRaw('hotel_id IS NULL')->first();
        return $query($from,$to)?:$query($to,$from);
    }
    private function zoneInAddress(string $address,Hotel $hotel):?string
    {
        $normalized=$this->normalize($address);
        $zones=TransferZoneRate::query()->where('is_active',true)->where(fn($q)=>$q->whereNull('hotel_id')->orWhere('hotel_id',$hotel->id))->get(['from_zone','to_zone'])->flatMap(fn($r)=>[$r->from_zone,$r->to_zone])->merge(District::query()->pluck('name'))->filter()->unique();
        foreach($zones as $zone){$needle=$this->normalize((string)$zone);if($needle!==''&&str_contains($normalized,$needle))return (string)$zone;}return null;
    }
    private function normalize(string $value):string
    {
        $value=mb_strtolower(trim($value),'UTF-8');$ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value);return preg_replace('/[^a-z0-9]+/',' ',$ascii===false?$value:$ascii)??'';
    }
}
