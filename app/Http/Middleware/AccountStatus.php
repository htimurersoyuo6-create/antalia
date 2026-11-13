<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class AccountStatus
{
 public function handle(Request $request, Closure $next)
 {
  $u=$request->user();
  if($u && ($u->archived_at || $u->blacklisted_at)){
   Auth::logout();$request->session()->invalidate();$request->session()->regenerateToken();
   return redirect()->route('login')->withErrors(['email'=>'Bu hesabın erişimi geçici olarak kapalı. Yönetim ekibiyle iletişime geçin.']);
  }
  return $next($request);
 }
}
