<?php
namespace App\Http\Controllers\Panel;
use App\Http\Controllers\Controller;
use App\Models\GroomingOption;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class GroomingController extends Controller {
    public function index(Request $request){$ids=$request->user()->hotels()->pluck('id');return view('panel.grooming', ['hotels'=>$request->user()->hotels()->orderBy('name')->get(['id','name']),'options'=>GroomingOption::whereIn('hotel_id',$ids)->with('hotel:id,name')->latest()->paginate(30)]);}
    public function store(Request $request){$ids=$request->user()->hotels()->pluck('id');$d=$request->validate(['hotel_id'=>['required','integer',Rule::in($ids->all())],'title'=>['required','string','max:120'],'price'=>['required','numeric','gt:0','max:100000'],'pet_type'=>['required',Rule::in(['cat','dog','both'])]]);GroomingOption::create($d+['is_active'=>true]);return back()->with('ok','Kuaför hizmeti checkout seçeneklerine eklendi.');}
    public function destroy(Request $request,GroomingOption $option){abort_unless($option->hotel_id && $request->user()->hotels()->whereKey($option->hotel_id)->exists(),404);$option->update(['is_active'=>false]);return back()->with('ok','Kuaför seçeneği pasifleştirildi.');}
}
