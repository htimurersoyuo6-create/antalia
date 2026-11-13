<?php
namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\HotelSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PartnerController extends Controller
{
    public function portal()
    {
        return view('partner.portal');
    }

    public function create()
    {
        if (auth()->check()) {
            $user = auth()->user();
            if ($user->isAdmin()) return redirect()->route('admin.index');
            if ($user->isOwner()) return redirect()->route('panel.subscriptions');

            return redirect()->route('pets.index')->with('ok', 'İşletme başvurusu için işletme hesabıyla devam edin.');
        }
        $plans = SubscriptionPlan::where('active', true)->orderBy('sort_order')->orderBy('monthly_price')->get();
        $cardPaymentsEnabled = \App\Support\PaytrSettings::ready();
        $cardPaymentTestMode = $cardPaymentsEnabled && (bool) \App\Support\PaytrSettings::credentials()['test_mode'];
        return view('partner.create', compact('plans', 'cardPaymentsEnabled', 'cardPaymentTestMode'));
    }

    public function store(Request $r)
    {
        if (!\App\Support\Captcha::check($r, 'partner')) return back()->withErrors(['captcha' => 'Güvenlik kontrolü başarısız. Yeni doğrulamayı tamamlayın.'])->withInput();

        $d = $r->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:160|unique:users,email',
            'phone' => 'required|string|max:30',
            'password' => 'required|string|min:8|confirmed',
            'plan_id' => 'nullable|exists:subscription_plans,id',
            'billing_cycle' => 'nullable|in:monthly,yearly',
            'requested_payment_method' => 'nullable|in:bank_transfer,card',
            'kvkk' => 'accepted',
            'subscription_terms' => 'accepted',
        ]);
        $plan = null;
        $amount = null;
        if (!empty($d['plan_id'])) {
            if (empty($d['billing_cycle']) || empty($d['requested_payment_method'])) {
                return back()->withErrors(['plan_id' => 'Paket seçtiğinizde faturalama dönemi ve ödeme yöntemini de seçin.'])->withInput();
            }
            $plan = SubscriptionPlan::query()->whereKey($d['plan_id'])->where('active', true)->firstOrFail();
            $amount = $d['billing_cycle'] === 'yearly' ? $plan->yearly_price : $plan->monthly_price;
            if ($d['billing_cycle'] === 'yearly' && (!$amount || (float) $amount <= 0)) {
                return back()->withErrors(['billing_cycle' => 'Seçtiğiniz plan yıllık faturalamayı desteklemiyor.'])->withInput();
            }
            if (!$amount || (float) $amount <= 0) {
                return back()->withErrors(['plan_id' => 'Yalnızca ücretli abonelik planlarıyla kayıt oluşturabilirsiniz.'])->withInput();
            }
            if ($d['requested_payment_method'] === 'card' && !\App\Support\PaytrSettings::ready()) {
                return back()->withErrors(['requested_payment_method' => 'Online kart ödemesi şu anda yapılandırılmıyor. Havale/EFT seçeneğini kullanabilirsiniz.'])->withInput();
            }
        }

        $user = DB::transaction(function () use ($d, $plan, $amount) {
            $u = new User(['name' => $d['name'], 'email' => $d['email'], 'phone' => $d['phone'], 'password' => $d['password']]);
            $u->role = 'hotel_owner';
            $u->save();
            if ($plan) {
                HotelSubscription::create([
                    'hotel_id' => null,
                    'plan_id' => $plan->id,
                    'requested_by' => $u->id,
                    'billing_cycle' => $d['billing_cycle'],
                    'requested_payment_method' => $d['requested_payment_method'],
                    'amount' => $amount,
                    'status' => $d['requested_payment_method'] === 'card' ? 'awaiting_payment' : 'pending_review',
                    'payment_status' => 'unpaid',
                ]);
            }
            return $u;
        });
        Auth::login($user);
        \App\Support\SecurityAudit::record($r,'login','success',$user->id,'İş ortağı kaydı ve giriş',null,$user->id);
        $r->session()->regenerate();
        $message = !$plan
            ? 'Ücretsiz demo işletme hesabınız oluşturuldu. Paneli inceleyebilir ve uygun olduğunuzda 30 günlük denemenizi veya ücretli aboneliğinizi başlatabilirsiniz. Canlı işlemler abonelik etkinleşince açılır.'
            : ($d['requested_payment_method'] === 'card'
            ? 'İşletme hesabınız ve abonelik talebiniz oluşturuldu. Paneli demo olarak inceleyebilirsiniz; PayTR’dan gerçek tahsilat doğrulanınca tesis ekleme ve canlı işlemler açılır.'
            : 'İşletme hesabınız oluşturuldu. Paneli demo olarak inceleyebilirsiniz; havale/EFT yönetim tarafından doğrulanınca tesis ekleme ve canlı işlemler açılır.');
        return redirect()->route('panel.subscriptions')->with('ok', $message);
    }
    public function listingApplication()
    {
        $cities = \App\Models\City::query()->orderBy('name')->get(['id', 'name']);
        return view('partner.listing-application', compact('cities'));
    }

    public function listingApplicationStatus(Request $request)
    {
        $user = $request->user();
        $accessToken = trim((string) $request->query('t', ''));
        if (!$user && $accessToken === '') {
            $response = response()->view('partner.listing-login-required', [], 200);
            $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
            $response->headers->set('Referrer-Policy', 'no-referrer');
            return $response;
        }
        if ($accessToken !== '') {
            abort_unless(preg_match('/^[A-Za-z0-9]{64}$/', $accessToken) === 1, 404);
            $applications = ContactMessage::query()
                ->where('listing_token_hash', hash('sha256', $accessToken))
                ->where('listing_token_expires_at', '>', now())
                ->where('message', 'like', 'ÜCRETSİZ TESİS LİSTELEME BAŞVURUSU%')
                ->limit(1)->get();
            abort_if($applications->isEmpty(), 404);
        } else {
            abort_unless($user && $user->role === 'hotel_owner', 403);
            $applications = ContactMessage::query()
                ->where('email', $user->email)
                ->where('message', 'like', 'ÜCRETSİZ TESİS LİSTELEME BAŞVURUSU%')
                ->latest()->limit(20)->get();
        }
        $applications = $applications
            ->map(function ($application) {
                preg_match('/^Tesis adı: (.+)$/m', (string) $application->message, $match);
                $application->hotel_name = $match[1] ?? 'Tesis başvurusu';
                $application->listing_status_label = [
                    'received' => 'Ekibe iletildi',
                    'reviewing' => 'İnceleniyor',
                    'need_info' => 'Ek bilgi bekleniyor',
                    'approved' => 'Listeleme için uygun',
                    'rejected' => 'Başvuru uygun bulunmadı',
                ][$application->listing_status ?? 'received'];
                return $application;
            });
        $response = response()->view('partner.listing-status', compact('applications'));
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        return $response;
    }

    public function recoverListingStatus(Request $request)
    {
        if (!\App\Support\Captcha::check($request, 'listing-status-recovery')) {
            return back()->withErrors(['captcha' => 'Güvenlik kontrolü başarısız. Yeni doğrulamayı tamamlayın.'])->withInput();
        }
        if ($request->filled('company_website')) return back()->with('ok', 'Bu e-posta adresiyle başvuru varsa takip bağlantısı gönderildi.');
        $data = $request->validate(['email' => 'required|email|max:160']);
        $email = mb_strtolower(trim($data['email']));
        $rateKey = 'listing-status-recovery:'.hash('sha256', $email);
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($rateKey, 2)) {
            return back()->with('ok', 'Bu e-posta adresiyle başvuru varsa takip bağlantısı gönderildi.');
        }
        \Illuminate\Support\Facades\RateLimiter::hit($rateKey, 3600);

        $application = ContactMessage::query()
            ->where('email', $email)
            ->where('message', 'like', 'ÜCRETSİZ TESİS LİSTELEME BAŞVURUSU%')
            ->latest()->first();
        if ($application) {
            $token = Str::random(64);
            $oldHash = $application->listing_token_hash;
            $oldExpiry = $application->listing_token_expires_at;
            $application->update([
                'listing_token_hash' => hash('sha256', $token),
                'listing_token_expires_at' => now()->addMonths(6),
            ]);
            preg_match('/^Tesis adı: (.+)$/m', (string) $application->message, $match);
            $hotelName = $match[1] ?? 'Tesis başvurunuz';
            $sent = \App\Support\Bildirim::gonder(
                $email,
                'Tesis başvuru takip bağlantınız · '.$hotelName,
                'mail.bildirim',
                [
                    'baslik' => 'Başvurunuz için yeni takip bağlantısı',
                    'metin' => $hotelName.' başvurunuzun güncel durumunu görüntülemek için aşağıdaki özel bağlantıyı kullanın. Bağlantı 6 ay geçerlidir.',
                    'link' => route('partner.listing.status', ['t' => $token]),
                    'linkEtiket' => 'Başvuru durumunu görüntüle',
                ]
            );
            if (!$sent || config('mail.default') === 'log') {
                $application->update([
                    'listing_token_hash' => $oldHash,
                    'listing_token_expires_at' => $oldExpiry,
                ]);
            }
        }
        return back()->with('ok', 'Bu e-posta adresiyle başvuru varsa takip bağlantısı gönderildi. Gelen kutunuzu ve istenmeyen e-posta klasörünü kontrol edin.');
    }

    public function storeListingApplication(Request $request)
    {
        if (!\App\Support\Captcha::check($request, 'partner-listing')) {
            return back()->withErrors(['captcha' => 'Güvenlik kontrolü başarısız. Yeni doğrulamayı tamamlayın.'])->withInput();
        }
        if ($request->filled('company_website')) return back();
        $data = $request->validate([
            'name' => 'required|string|max:120', 'email' => 'required|email|max:160',
            'phone' => 'required|string|max:30', 'hotel_name' => 'required|string|max:160',
            'city' => 'required|string|max:80', 'district' => 'nullable|string|max:100',
            'website' => 'nullable|url|max:200', 'pet_types' => 'required|array|min:1',
            'pet_types.*' => 'required|in:dog,cat', 'note' => 'nullable|string|max:2000',
            'kvkk' => 'accepted',
        ], ['pet_types.required' => 'En az bir hayvan türü seçin.', 'pet_types.min' => 'En az bir hayvan türü seçin.']);
        $types = collect($data['pet_types'])->unique()->map(fn ($type) => $type === 'dog' ? 'Köpek' : 'Kedi')->implode(', ');
        $message = implode("\n", array_filter([
            'ÜCRETSİZ TESİS LİSTELEME BAŞVURUSU',
            'Tesis adı: '.$data['hotel_name'],
            'Konum: '.$data['city'].(!empty($data['district']) ? ' / '.$data['district'] : ''),
            'Kabul edilen dostlar: '.$types, 'Web sitesi: '.($data['website'] ?? 'Belirtilmedi'),
            'Başvuru notu: '.(trim((string)($data['note'] ?? '')) ?: 'Belirtilmedi'),
            'Başvuru kanalı: Ücretsiz tesis başvuru formu',
        ]));
        $accessToken = Str::random(64);
        $lead = ContactMessage::create([
            'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'], 'message' => $message,
            'listing_token_hash' => hash('sha256', $accessToken),
            'listing_token_expires_at' => now()->addMonths(6),
        ]);
        try { \App\Support\Bildirim::iletisim($lead); } catch (\Throwable $exception) { report($exception); }
        return redirect()->route('partner.listing.status', ['t' => $accessToken])->with('ok', 'Ücretsiz tesis başvurunuz alındı. Bu özel bağlantıyı başvurunuzun durumunu izlemek için saklayın. Bağlantı 6 ay geçerlidir.');
    }

}
