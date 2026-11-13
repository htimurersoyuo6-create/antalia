<?php
namespace App\Http\Controllers\Panel;
use App\Http\Controllers\Controller;
use App\Models\DailyReport;
use App\Models\PetTask;
use App\Models\Reservation;
use App\Models\ReservationBelonging;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class CareOperationsController extends Controller
{
    private function reservation(Request $request, Reservation $reservation): Reservation
    {
        abort_unless((int) $reservation->hotel->owner_id === (int) $request->user()->id || $request->user()->isAdmin(), 403);
        return $reservation;
    }
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $hotelIds = $user->isAdmin() ? null : \App\Models\Hotel::where('owner_id',$user->id)->pluck('id');
        $query = PetTask::with(['reservation.pet','reservation.hotel'])->where('status','pending');
        if ($hotelIds !== null) {
            $query->whereIn('hotel_id',$hotelIds);
        }
        $overdue = (clone $query)->where('scheduled_at','<',now())->orderBy('scheduled_at')->limit(20)->get();
        $upcoming = (clone $query)->where('scheduled_at','>=',now())->orderBy('scheduled_at')->limit(20)->get();
        $reportsQuery = DailyReport::with(['reservation.pet','hotel']);
        if ($hotelIds !== null) {
            $reportsQuery->whereIn('hotel_id',$hotelIds);
        }
        $reports = $reportsQuery->latest()->limit(15)->get();
        return view('panel.care-dashboard', compact('overdue','upcoming','reports'));
    }

    public function show(Request $request, Reservation $reservation)
    {
        $this->reservation($request, $reservation);
        return view('panel.care-operations', [
            'reservation' => $reservation->load(['hotel', 'pet']),
            'reports' => $reservation->dailyReports()->latest('report_date')->get(),
            'belongings' => $reservation->belongings,
            'tasks' => $reservation->tasks()->latest('scheduled_at')->get(),
            'preauthBlocks' => $reservation->preauthBlocks()->latest()->get(),
        ]);
    }

    public function report(Request $request, Reservation $reservation)
    {
        $this->reservation($request, $reservation);
        $data=$request->validate(['report_date'=>'required|date','feeding_status'=>'required|in:all,half,none,not_recorded','feeding_grams'=>'nullable|integer|min:0|max:10000','toilet_status'=>'required|in:normal,diarrhea,constipated,none,not_recorded','mood_status'=>'required|in:happy,playful,anxious,aggressive,lethargic,not_recorded','notes'=>'nullable|string|max:4000','photos'=>'nullable|array|max:8','photos.*'=>'string|max:255']);
        DailyReport::updateOrCreate(['reservation_id'=>$reservation->id,'report_date'=>$data['report_date']],$data+['hotel_id'=>$reservation->hotel_id,'pet_id'=>$reservation->pet_id,'staff_id'=>$request->user()->id]);
        return back()->with('ok','Günlük bakım karnesi kaydedildi.');
    }
    public function belongings(Request $request, Reservation $reservation)
    {
        $this->reservation($request,$reservation);
        $data=$request->validate(['food_details'=>'nullable|string|max:4000','food_daily_gram'=>'nullable|integer|min:0|max:10000','medications'=>'nullable|array','accessories'=>'nullable|array','check_in_photos'=>'nullable|array|max:8','check_out_photos'=>'nullable|array|max:8','check_in_signature'=>'nullable|string|max:200000','check_out_signature'=>'nullable|string|max:200000','phase'=>'required|in:check_in,check_out']);
        $phase=$data['phase']; unset($data['phase']); $data[$phase.'_by']=$request->user()->id; $data[$phase.'_at']=now();
        ReservationBelonging::updateOrCreate(['reservation_id'=>$reservation->id],$data+['hotel_id'=>$reservation->hotel_id,'pet_id'=>$reservation->pet_id]);
        return back()->with('ok','Teslim tutanağı kaydedildi.');
    }
    public function task(Request $request, Reservation $reservation)
    {
        $this->reservation($request,$reservation);
        $data=$request->validate(['task_type'=>'required|in:feeding,medication,walk,cleaning','title'=>'required|string|max:160','notes'=>'nullable|string|max:2000','scheduled_at'=>'required|date','assigned_to'=>'nullable|integer|exists:users,id']);
        PetTask::create($data+['hotel_id'=>$reservation->hotel_id,'reservation_id'=>$reservation->id,'pet_id'=>$reservation->pet_id]);
        return back()->with('ok','Personel görevi oluşturuldu.');
    }
    public function completeTask(Request $request, PetTask $task)
    {
        abort_unless((int)$task->hotel->owner_id === (int)$request->user()->id || $request->user()->isAdmin(),403);
        abort_unless($task->status==='pending',409);
        $task->update(['status'=>'completed','completed_at'=>now(),'completed_by'=>$request->user()->id]);
        return back()->with('ok','Görev tamamlandı olarak işaretlendi.');
    }
}