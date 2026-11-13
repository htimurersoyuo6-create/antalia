<?php
namespace App\Http\Controllers;
use App\Models\PendingPhoneRegistration;
use App\Models\User;
use App\Services\NetgsmOtpSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
class PhoneRegistrationController extends Controller
{
    public function start(Request $request, array $data, string $phone, string $identityHash, array $safe)
    {
        $sender = app(NetgsmOtpSender::class);
        if (!$sender->configured()) return back()->withErrors(['phone'=>'Telefonla kayıt için SMS doğrulaması henüz yapılandırılmamış. Lütfen e-posta ile kayıt olun veya daha sonra tekrar deneyin.'])->withInput($safe);
        try {
            $pending = DB::connection()->transaction(function () use ($data, $phone, $identityHash) {
                $existing = User::where('phone_normalized',$phone);
                if ($identityHash) $existing->orWhere('identity_hash',$identityHash);
                if ($existing->exists()) return null;
                $pendingQuery = PendingPhoneRegistration::where('phone_normalized',$phone);
                if ($identityHash) $pendingQuery->orWhere('identity_hash',$identityHash);
                $pending = $pendingQuery->lockForUpdate()->first();
                if ($pending && ($pending->phone_normalized !== $phone || $pending->identity_hash !== $identityHash)) return null;
                if ($pending && $pending->last_sent_at && $pending->last_sent_at->gt(now()->subSeconds(60))) return $pending;
                $pending ??= new PendingPhoneRegistration(['id'=>(string)Str::uuid()]);
                $code = (string)random_int(100000,999999);
                $pending->forceFill([
                    'phone_normalized'=>$phone,
                    'identity_hash'=>$identityHash,
                    'registration_payload'=>[
                        'name'=>$data['name'],
                        'phone'=>$data['phone'],
                        'phone_normalized'=>$phone,
                        'identity_hash'=>$identityHash,
                        'address'=>$data['address']??null,
                        'password_hash'=>Hash::make($data['password']),
                    ],
                    'code_hash'=>Hash::make($code),
                    'delivery_status'=>'sending',
                    'attempts'=>0,
                    'expires_at'=>now()->addMinutes(10),
                    'last_sent_at'=>now(),
                ])->save();
                return ['record'=>$pending->fresh(),'code'=>$code];
            });
        } catch (QueryException) {
            return back()->withErrors(['phone'=>'Bu telefon numarasıyla işlem zaten başlatılmış. Giriş yapmayı deneyin.'])->withInput($safe);
        }
        if (!$pending) return back()->withErrors(['phone'=>'Bu telefon numarası başka bir hesapta kullanılıyor.'])->withInput($safe);
        if ($pending instanceof PendingPhoneRegistration) {
            $request->session()->put('pending_phone_registration_id',$pending->id);
            if ($pending->delivery_status !== 'sent') return redirect()->route('register.phone.verify')->withErrors(['sms'=>'Önceki SMS gönderilemedi. Yeniden kod istemek için 60 saniye bekleyin.']);
            return redirect()->route('register.phone.verify')->with('ok','Doğrulama kodu kısa süre önce gönderildi. Mesajı kontrol edin.');
        }
        $record = $pending['record'];
        $request->session()->put('pending_phone_registration_id',$record->id);
        $sent = $sender->send($phone,$pending['code']);
        $record->update(['delivery_status'=>$sent?'sent':'failed']);
        return $sent ? redirect()->route('register.phone.verify')->with('ok','Telefonunuza doğrulama kodu gönderildi.') : redirect()->route('register.phone.verify')->withErrors(['sms'=>'SMS gönderimi tamamlanamadı. Sağlayıcı ayarlarını kontrol edip yeniden gönderme süresini bekleyin.']);
    }
    public function show(Request $request)
    {
        $pending = $this->pending($request);
        if (!$pending) return redirect()->route('register')->withErrors(['phone'=>'Telefon kayıt oturumunun süresi doldu. Lütfen yeniden başlayın.']);
        $digits = preg_replace('/\D+/','',$pending->phone_normalized);
        $maskedPhone = '+'.substr($digits,0,4).' '.str_repeat('•',max(0,strlen($digits)-7)).' '.substr($digits,-3);
        return view('auth.phone-verify',compact('pending','maskedPhone'));
    }
    public function verify(Request $request)
    {
        $request->validate(['code'=>'required|digits:6']);
        $pending = $this->pending($request);
        if (!$pending) return redirect()->route('register')->withErrors(['phone'=>'Telefon kayıt oturumunun süresi doldu. Lütfen yeniden başlayın.']);
        try {
            $result = DB::connection()->transaction(function () use ($pending,$request) {
                $row = PendingPhoneRegistration::whereKey($pending->id)->lockForUpdate()->first();
                if (!$row || $row->expires_at->isPast()) { if($row)$row->delete(); return ['error'=>'Kodun süresi doldu. Yeniden kayıt başlatın.']; }
                if ($row->delivery_status !== 'sent') return ['error'=>'SMS henüz gönderilemedi. SMS sağlayıcı ayarlarını kontrol edin.'];
                if ($row->attempts >= 5) { $row->delete(); return ['error'=>'Çok fazla hatalı deneme yapıldı. Lütfen yeniden kayıt başlatın.']; }
                if (!Hash::check((string)$request->input('code'),$row->code_hash)) { $row->increment('attempts'); if ($row->attempts >= 5) $row->delete(); return ['error'=>$row->attempts >= 5 ? 'Çok fazla hatalı deneme yapıldı. Lütfen yeniden kayıt başlatın.' : 'Doğrulama kodu hatalı.']; }
                $payload = $row->registration_payload;
                $existing = User::where('phone_normalized',$row->phone_normalized);
                if ($row->identity_hash) $existing->orWhere('identity_hash',$row->identity_hash);
                if ($existing->exists()) { $row->delete(); return ['error'=>'Bu telefon numarasıyla hesap zaten oluşturulmuş. Giriş yapmayı deneyin.']; }
                $user = new User();
                $user->name=$payload['name']; $user->email=null; $user->phone=$payload['phone']; $user->address=$payload['address'];
                $user->phone_normalized=$row->phone_normalized; $user->identity_hash=$row->identity_hash;
                $user->password=$payload['password_hash']; $user->role='customer'; $user->phone_verified_at=now(); $user->save();
                $row->delete();
                return ['user'=>$user];
            });
        } catch (QueryException) {
            return back()->withErrors(['code'=>'Bu telefon numarasıyla hesap zaten mevcut. Giriş yapmayı deneyin.']);
        }
        if (!empty($result['error'])) return back()->withErrors(['code'=>$result['error']]);
        $request->session()->forget('pending_phone_registration_id');
        Auth::login($result['user']); $request->session()->regenerate(); \App\Support\SecurityAudit::record($request,'login','success',$result['user']->id,'Telefon ile hesap oluşturma ve giriş',null,$result['user']->id);
        return redirect()->intended(route('pets.create'))->with('ok','Telefonunuz doğrulandı. Hoş geldiniz!');
    }
    public function resend(Request $request)
    {
        $pending=$this->pending($request);
        if(!$pending)return redirect()->route('register')->withErrors(['phone'=>'Telefon kayıt oturumunun süresi doldu. Lütfen yeniden başlayın.']);
        if($pending->last_sent_at && $pending->last_sent_at->gt(now()->subSeconds(60))) return back()->withErrors(['code'=>'Yeni kod istemeden önce 60 saniye bekleyin.']);
        $sender=app(NetgsmOtpSender::class);
        if(!$sender->configured())return back()->withErrors(['code'=>'SMS sağlayıcı ayarları yapılandırılmamış.']);
        $code=(string)random_int(100000,999999);
        $pending->update(['code_hash'=>Hash::make($code),'delivery_status'=>'sending','attempts'=>0,'expires_at'=>now()->addMinutes(10),'last_sent_at'=>now()]);
        $sent=$sender->send($pending->phone_normalized,$code);
        $pending->update(['delivery_status'=>$sent?'sent':'failed']);
        return $sent ? back()->with('ok','Yeni doğrulama kodu gönderildi.') : back()->withErrors(['sms'=>'SMS gönderilemedi. Daha sonra tekrar deneyin.']);
    }
    private function pending(Request $request): ?PendingPhoneRegistration
    {
        $id=$request->session()->get('pending_phone_registration_id');
        if(!$id)return null;
        $row=PendingPhoneRegistration::find($id);
        if(!$row || $row->expires_at->isPast()) { if($row)$row->delete(); $request->session()->forget('pending_phone_registration_id'); return null; }
        return $row;
    }
}
