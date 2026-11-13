<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
class PointWalletController extends Controller {
    public function index(Request $request) {
        $entries=$request->user()->pointTransactions()->with('reservation:id,code')->latest()->paginate(30);
        $tier = app(\App\Services\VipTierService::class)->forUser($request->user());
        return view('points.wallet', ['entries'=>$entries,'balance'=>(int)$request->user()->reward_points,'tier'=>$tier]);
    }
}
