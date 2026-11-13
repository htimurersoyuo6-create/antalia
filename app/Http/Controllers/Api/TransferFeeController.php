<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Services\PetTransferPricingService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
class TransferFeeController extends Controller
{
    public function calculateFee(Request $request, PetTransferPricingService $pricing)
    {
        $data = $request->validate([
            'hotel_slug' => ['required','string','max:191'],
            'transfer_type' => ['required','in:one_way_pickup,one_way_dropoff,round_trip'],
            'pickup_address' => ['required','string','max:500'],
            'return_address' => ['nullable','required_if:transfer_type,round_trip','string','max:500'],
        ]);
        $hotel = Hotel::active()->where('slug',$data['hotel_slug'])->firstOrFail();
        try {
            $quote = $pricing->quote($hotel,$data['pickup_address'],$data['transfer_type'],$data['return_address'] ?? null);
            return response()->json([
                'ok'=>true,'distance_km'=>$quote['distance_km'],'transfer_fee'=>$quote['fee'],
                'pricing_source'=>$quote['pricing_source'],'currency'=>'TRY',
                'quote_token'=>\Illuminate\Support\Facades\Crypt::encryptString(json_encode([
                    'hotel_id'=>$hotel->id,'type'=>$data['transfer_type'],
                    'address_hash'=>hash('sha256', mb_strtolower(trim($data['pickup_address']))),
                    'return_hash'=>hash('sha256', mb_strtolower(trim((string)($data['return_address']??'')))),
                    'fee_cents'=>(int)round((float)$quote['fee']*100),'expires_at'=>now()->addMinutes(15)->timestamp,
                ],JSON_THROW_ON_ERROR)),
            ])->header('Cache-Control','no-store');
        } catch (ValidationException $e) {
            return response()->json(['ok'=>false,'message'=>collect($e->errors())->flatten()->first()],422)->header('Cache-Control','no-store');
        }
    }
}
