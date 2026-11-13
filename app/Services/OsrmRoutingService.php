<?php
namespace App\Services;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
class OsrmRoutingService
{
    public function geocode(string $address): array
    {
        $address=trim($address);
        if($address===''||mb_strlen($address)>500) throw ValidationException::withMessages(['pickup_address'=>'Geçerli bir adres girin.']);
        return Cache::remember('pet_osm_geocode:'.sha1(mb_strtolower($address,'UTF-8')),now()->addDays(30),function() use($address) {
            $lock=Cache::lock('pet_osm_nominatim_global',20);
            try {
                $lock->block(30);
                $response=Http::acceptJson()->withHeaders(['User-Agent'=>'AntalyaPetOtel/1.0 (+https://www.antalyapetotel.com/iletisim)','Accept-Language'=>'tr-TR,tr;q=0.9,en;q=0.5'])
                    ->timeout(8)->get('https://nominatim.openstreetmap.org/search',['q'=>$address.', Antalya, Türkiye','format'=>'jsonv2','limit'=>1,'addressdetails'=>1]);
                usleep(1000000);
                $row=$response->successful()?$response->json('0'):null;
                if(!is_array($row)||!is_numeric($row['lat']??null)||!is_numeric($row['lon']??null)) throw new \RuntimeException('Nominatim did not find the address.');
                return ['lat'=>(float)$row['lat'],'lng'=>(float)$row['lon']];
            } catch(\Throwable $e) {
                Log::notice('OSM address lookup failed',['message'=>$e->getMessage()]);
                throw ValidationException::withMessages(['pickup_address'=>'Adres haritada bulunamadı. Adrese ilçe/mahalle ekleyin veya sabit bölge tarifesi tanımlayın.']);
            } finally { optional($lock)->release(); }
        });
    }
    public function drivingDistanceKm(array $origin,array $destination):float
    {
        $url=rtrim((string)config('booking.transfer_osrm_url'),'/').'/route/v1/driving/'.$origin['lng'].','.$origin['lat'].';'.$destination['lng'].','.$destination['lat'];
        return (float)Cache::remember('pet_osm_route:'.sha1($url),now()->addHours(12),function()use($url){
            try {
                $response=Http::acceptJson()->withHeaders(['User-Agent'=>'AntalyaPetOtel/1.0 (+https://www.antalyapetotel.com/iletisim)'])->timeout(10)->get($url,['overview'=>'false','alternatives'=>'false','steps'=>'false']);
                $meters=$response->json('routes.0.distance');
                if(!$response->successful()||!is_numeric($meters)||(float)$meters<=0)throw new \RuntimeException('OSRM route unavailable.');
                return round((float)$meters/1000,2);
            }catch(\Throwable $e){
                Log::notice('OSRM route lookup failed',['message'=>$e->getMessage()]);
                throw ValidationException::withMessages(['pickup_address'=>'Araç rotası hesaplanamadı. Adresleri kontrol edin veya sabit bölge tarifesi tanımlayın.']);
            }
        });
    }
}
