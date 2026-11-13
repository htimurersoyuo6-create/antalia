<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use App\Models\SocialAccount;
use Illuminate\Database\QueryException;
class AuthController extends Controller
{
    public function showRegister() { return view('auth.register'); }
    public function register(Request $r)
    {
        $safe=$r->except(['tc_kimlik','password','password_confirmation','_token']);
        if (!\App\Support\Captcha::check($r, 'register')) return back()->withErrors(['captcha'=>'Güvenlik kontrolü başarısız. Yeni doğrulamayı tamamlayın.'])->withInput($safe);
        $validator=Validator::make($r->all(),[
            'name'=>'required|string|max:120','email'=>'nullable|email|max:160|unique:users,email','phone'=>'nullable|string|max:30',
            'address'=>'nullable|string|max:500','password'=>'required|string|min:8|confirmed','kvkk'=>'accepted',
        ],[
            'required'=>'Bu alan zorunludur.','required_without'=>'E-posta kullanmıyorsanız :attribute alanı zorunludur.',
            'string'=>'Bu alan metin olmalıdır.','email.email'=>'Geçerli bir e-posta adresi girin.',
            'email.unique'=>'Bu e-posta adresiyle bir hesap zaten var. Giriş yapmayı deneyin veya farklı bir e-posta kullanın.',
            'email.max'=>'E-posta adresi en fazla :max karakter olabilir.','phone.max'=>'Telefon numarası en fazla :max karakter olabilir.',
            'digits'=>'Bu alan :digits rakamdan oluşmalıdır.','address.max'=>'Adres en fazla :max karakter olabilir.',
            'password.min'=>'Şifre en az :min karakter olmalıdır.','password.confirmed'=>'Şifre alanları eşleşmiyor.',
            'kvkk.accepted'=>'Kayıt olmak için Gizlilik / KVKK metnini kabul etmelisiniz.',
        ],[
            'name'=>'ad soyad','email'=>'e-posta','phone'=>'cep telefonu',
            'address'=>'adres','password'=>'şifre','kvkk'=>'Gizlilik / KVKK metni',
        ]);
        if($validator->fails())return back()->withErrors($validator)->withInput($safe);
        $data=$validator->validated();
        $email=isset($data['email'])&&trim($data['email'])!==''?mb_strtolower(trim($data['email'])):null;
        $phone=\App\Support\PhoneNumber::normalize($data['phone']??null);
        if(!$email&&!$phone)return back()->withErrors(['phone'=>'E-posta kullanmıyorsanız geçerli bir telefon numarası zorunludur.'])->withInput($safe);
        if($phone&&User::where('phone_normalized',$phone)->exists())return back()->withErrors(['phone'=>'Bu telefon numarası başka bir hesapta kullanılıyor.'])->withInput($safe);
        $identityHash=null;
        if(!$email)return app(PhoneRegistrationController::class)->start($r,$data,(string)$phone,(string)$identityHash,$safe);
        $user=new User(['name'=>$data['name'],'email'=>$email,'phone'=>$data['phone']??null,'address'=>$data['address']??null,'password'=>$data['password']]);
        $user->phone_normalized=$phone;$user->identity_hash=$identityHash;$user->role='customer';
        try{$user->save();}catch(QueryException $e){if($e->getCode()==='23000')return back()->withErrors(['email'=>'Bu bilgilerle bir hesap zaten mevcut. Giriş yapmayı deneyin.'])->withInput($safe);throw $e;}
        Auth::login($user);$r->session()->regenerate();\App\Support\SecurityAudit::record($r,'login','success',$user->id,'Hesap oluşturma ve oturum açma',null,$user->id);
        return redirect()->intended(route('pets.create'))->with('ok','Hoş geldiniz! Önce dostunuzun profilini oluşturalım.');
    }
    public function showLogin(Request $request)
    {
        if ($request->query('next') === 'tesis-basvurularim') {
            $request->session()->put('url.intended', route('partner.listing.status'));
        }
        return view('auth.login');
    }
    public function login(Request $r)
    {
        $cred=$r->validate(['identifier'=>'required|string|max:160','password'=>'required|string']);$identifier=trim($cred['identifier']);
        $account=filter_var($identifier,FILTER_VALIDATE_EMAIL)?User::where('email',mb_strtolower($identifier))->first():(($phone=\App\Support\PhoneNumber::normalize($identifier))?User::where('phone_normalized',$phone)->first():null);
        if($account&&($account->archived_at||$account->blacklisted_at)){\App\Support\SecurityAudit::failedLogin($r,$account->id,$identifier,'blocked');return back()->withErrors(['identifier'=>'Bu hesabın erişimi geçici olarak kapalı.'])->onlyInput('identifier');}
        if(!$account||!Auth::attempt(['id'=>$account->getAuthIdentifier(),'password'=>$cred['password']],$r->boolean('remember'))){\App\Support\SecurityAudit::failedLogin($r,$account?->id,$identifier,'failed');return back()->withErrors(['identifier'=>'E-posta/telefon veya şifre hatalı.'])->onlyInput('identifier');}
        $r->session()->regenerate();$user=Auth::user();\App\Support\SecurityAudit::record($r,'login','success',$user->id,'Başarılı giriş',null,$user->id);$default=match($user->role){'admin'=>route('admin.index'),'hotel_owner'=>route('panel.index'),default=>route('reservations.index')};return redirect()->intended($default);
    }
    private function socialProviderConfigured(string $provider): bool
    {
        $config = config('services.' . $provider, []);
        return filled($config['client_id'] ?? null) && filled($config['client_secret'] ?? null) && filled($config['redirect'] ?? null);
    }

    private function socialProviderAllowed(string $provider): bool
    {
        return in_array($provider, ['google', 'apple'], true);
    }

    public function redirectToProvider(string $provider)
    {
        abort_unless($this->socialProviderAllowed($provider), 404);
        if (!$this->socialProviderConfigured($provider)) {
            return redirect()->route('login')->withErrors(['social' => ucfirst($provider) . ' ile giriş henüz yapılandırılmadı.']);
        }

        try {
            $driver = Socialite::driver($provider);
            return $provider === 'apple'
                ? $driver->stateless()->cookieNonce()->redirect()
                : $driver->redirect();
        } catch (\Throwable $e) {
            Log::warning('Social sign-in flow failed', ['provider' => $provider, 'exception' => get_class($e)]);
            return redirect()->route('login')->withErrors(['social' => 'Güvenli giriş şu anda başlatılamıyor.']);
        }
    }

    public function googleCallback(Request $r)
    {
        return $this->handleProviderCallback($r, 'google');
    }

    public function appleCallback(Request $r)
    {
        return $this->handleProviderCallback($r, 'apple');
    }

    public function handleProviderCallback(Request $r, string $provider)
    {
        abort_unless($this->socialProviderAllowed($provider), 404);
        if (!$this->socialProviderConfigured($provider)) {
            return redirect()->route('login')->withErrors(['social' => ucfirst($provider) . ' ile giriş henüz yapılandırılmadı.']);
        }

        try {
            $driver = Socialite::driver($provider);
            $identity = $provider === 'apple'
                ? $driver->stateless()->cookieNonce()->user()
                : $driver->user();
            $providerId = trim((string) $identity->getId());
            if ($providerId === '') {
                throw new \RuntimeException('Provider returned no account identifier.');
            }

            $raw = is_array($identity->user ?? null) ? $identity->user : [];
            $email = mb_strtolower(trim((string) ($identity->getEmail() ?? '')));
            $emailVerified = filter_var($raw['email_verified'] ?? $raw['verified_email'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $displayName = trim((string) ($identity->getName() ?? ''));
            if ($displayName === '') {
                $displayName = $email !== '' ? (strstr($email, '@', true) ?: 'Pet Dostu') : 'Pet Dostu';
            }

            $resolved = DB::transaction(function () use ($provider, $providerId, $email, $emailVerified, $displayName) {
                // Apple sends email/name only on the first consent. Existing links must resolve by signed provider subject.
                $linked = SocialAccount::where('provider', $provider)->where('provider_user_id', $providerId)->first();
                if ($linked) {
                    $user = $linked->user;
                    if (!$user) throw new \DomainException('Bağlı hesap bulunamadı. Lütfen destek ekibiyle iletişime geçin.');
                    if ($user->archived_at || $user->blacklisted_at) throw new \DomainException('Bu hesabın erişimi geçici olarak kapalı.');
                    return ['user' => $user];
                }

                // A verified email is required only when creating or linking an account for the first time.
                if (!$emailVerified || !filter_var($email, FILTER_VALIDATE_EMAIL)) return ['email_required' => true];
                $user = User::where('email', $email)->first();
                if ($user && ($user->archived_at || $user->blacklisted_at)) {
                    throw new \DomainException('Bu hesabın erişimi geçici olarak kapalı.');
                }
                if (!$user) return ['user' => null];
                if (!$user->email_verified_at) {
                    $user->email_verified_at = now();
                    $user->save();
                }
                $user->socialAccounts()->create([
                    'provider' => $provider,
                    'provider_user_id' => $providerId,
                    'provider_email' => $email,
                ]);
                return ['user' => $user];
            });
            if (!empty($resolved['email_required'])) {
                return redirect()->route('login')->withErrors(['social' => 'Yeni hesap bağlantısı için sağlayıcının doğrulanmış e-posta adresi gerekir.']);
            }
            $user = $resolved['user'] ?? null;

            if (!$user) {
                $r->session()->put('social_registration_pending', [
                    'provider' => $provider,
                    'provider_user_id' => $providerId,
                    'email' => $email,
                    'name' => mb_substr($displayName, 0, 120),
                    'created_at' => time(),
                ]);
                return redirect()->route('social.register');
            }
            if ($user->archived_at || $user->blacklisted_at) {
                return redirect()->route('login')->withErrors(['social' => 'Bu hesabın erişimi geçici olarak kapalı.']);
            }

            Auth::login($user);
            \App\Support\SecurityAudit::record($r,'login','success',$user->id,'Google ile giriş',null,$user->id);
            $r->session()->regenerate();
            $default = match ($user->role) {
                'admin' => route('admin.index'),
                'hotel_owner' => route('panel.index'),
                default => route('reservations.index'),
            };
            return redirect()->intended($default);
        } catch (\DomainException $e) {
            return redirect()->route('login')->withErrors(['social' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::warning('Social sign-in callback failed', ['provider' => $provider, 'exception' => get_class($e)]);
            return redirect()->route('login')->withErrors(['social' => 'Hesap doğrulanamadı. Lütfen tekrar deneyin veya e-posta/telefon ve şifrenizle giriş yapın.']);
        }
    }

    public function showSocialRegistration(Request $r)
    {
        $pending = $r->session()->get('social_registration_pending');
        if (!is_array($pending) || empty($pending['provider_user_id']) || empty($pending['email']) || time() - (int) ($pending['created_at'] ?? 0) > 900) {
            $r->session()->forget('social_registration_pending');
            return redirect()->route('login')->withErrors(['social' => 'Kayıt oturumunun süresi doldu. Lütfen sosyal giriş adımını yeniden başlatın.']);
        }

        return view('auth.social-register', ['pending' => $pending]);
    }

    public function completeSocialRegistration(Request $r)
    {
        if (!\App\Support\Captcha::check($r, 'social-register')) {
            return back()->withErrors(['captcha' => 'Güvenlik kontrolü başarısız. Yeni doğrulamayı tamamlayın.']);
        }
        $r->validate(['kvkk' => 'accepted']);
        $pending = $r->session()->get('social_registration_pending');
        if (!is_array($pending) || empty($pending['provider']) || empty($pending['provider_user_id']) || empty($pending['email']) || time() - (int) ($pending['created_at'] ?? 0) > 900) {
            $r->session()->forget('social_registration_pending');
            return redirect()->route('login')->withErrors(['social' => 'Kayıt oturumunun süresi doldu. Lütfen sosyal giriş adımını yeniden başlatın.']);
        }

        try {
            $user = DB::transaction(function () use ($pending) {
                $linked = SocialAccount::where('provider', $pending['provider'])->where('provider_user_id', $pending['provider_user_id'])->first();
                if ($linked) {
                    return $linked->user;
                }

                $user = User::where('email', $pending['email'])->first();
                if ($user && ($user->archived_at || $user->blacklisted_at)) {
                    throw new \DomainException('Bu hesabın erişimi geçici olarak kapalı.');
                }
                if (!$user) {
                    $user = new User();
                    $user->name = $pending['name'];
                    $user->email = $pending['email'];
                    $user->email_verified_at = now();
                    $user->password = Str::random(64);
                    $user->role = 'customer';
                    $user->save();
                } elseif (!$user->email_verified_at) {
                    $user->email_verified_at = now();
                    $user->save();
                }

                $user->socialAccounts()->create([
                    'provider' => $pending['provider'],
                    'provider_user_id' => $pending['provider_user_id'],
                    'provider_email' => $pending['email'],
                ]);
                return $user;
            });

            if (!$user || $user->archived_at || $user->blacklisted_at) {
                return redirect()->route('login')->withErrors(['social' => 'Bu hesabın erişimi geçici olarak kapalı.']);
            }
            $r->session()->forget('social_registration_pending');
            Auth::login($user);
            \App\Support\SecurityAudit::record($r,'login','success',$user->id,'Apple ile giriş',null,$user->id);
            $r->session()->regenerate();
            return redirect()->intended(route('reservations.index'));
        } catch (\DomainException $e) {
            return back()->withErrors(['social' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::warning('Social sign-up completion failed', ['exception' => get_class($e)]);
            return back()->withErrors(['social' => 'Hesap oluşturulamadı. Lütfen yeniden deneyin veya e-posta/telefon ve şifrenizle kayıt olun.']);
        }
    }

    public function logout(Request $r){\App\Support\SecurityAudit::record($r,'logout','success',$r->user()?->id,'Güvenli çıkış',null,$r->user()?->id);Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return redirect()->route('home');}

}
