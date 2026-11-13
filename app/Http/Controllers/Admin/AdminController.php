<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\City;
use App\Models\District;
use App\Models\Hotel;
use App\Models\HotelChat;
use App\Models\Post;
use App\Models\Page;
use App\Models\Reservation;
use App\Models\ReservationInvoice;
use App\Models\WhatsAppCloudSetting;
use App\Models\HotelClaim;
use App\Models\HotelSubscription;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    public function statistics()
    {
        $from = now()->startOfMonth()->subMonths(11);
        $to = now()->endOfMonth();
        $driver = DB::connection()->getDriverName();
        $monthSql = $driver === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";

        $userRows = DB::table('users')->selectRaw("{$monthSql} AS period, role, COUNT(*) AS total")
            ->whereNull('archived_at')->whereNull('blacklisted_at')
            ->whereBetween('created_at', [$from, $to])->groupBy('period', 'role')->get()
            ->keyBy(fn ($row) => $row->period.'|'.$row->role);
        $reservationRows = DB::table('reservations')->selectRaw("{$monthSql} AS period, status, COUNT(*) AS total")
            ->whereBetween('created_at', [$from, $to])->groupBy('period', 'status')->get()
            ->groupBy('period');
        $receiptMonthSql = $driver === 'sqlite'
            ? "strftime('%Y-%m', paid_at)"
            : "DATE_FORMAT(paid_at, '%Y-%m')";
        $receiptRows = DB::table('hotel_subscriptions')->selectRaw("{$receiptMonthSql} AS period, COUNT(*) AS total, COALESCE(SUM(amount), 0) AS amount")
            ->where('payment_status', 'paid')->whereNotNull('paid_at')->whereBetween('paid_at', [$from, $to])
            ->groupBy('period')->get()->keyBy('period');

        $monthly = collect();
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $period = $cursor->format('Y-m');
            $reservationStatus = $reservationRows->get($period, collect())->keyBy('status');
            $receipt = $receiptRows->get($period);
            $monthly->push([
                'period' => $period,
                'label' => $cursor->format('m/Y'),
                'customers' => (int) ($userRows->get($period.'|customer')->total ?? 0),
                'partners' => (int) ($userRows->get($period.'|hotel_owner')->total ?? 0),
                'requests' => (int) $reservationStatus->sum('total'),
                'confirmed' => (int) ($reservationStatus->get('confirmed')->total ?? 0),
                'completed' => (int) ($reservationStatus->get('completed')->total ?? 0),
                'receipts' => (int) ($receipt->total ?? 0),
                'revenue' => (float) ($receipt->amount ?? 0),
            ]);
            $cursor->addMonth();
        }
        $maxAccounts = max(1, (int) $monthly->max(fn ($row) => $row['customers'] + $row['partners']));
        $maxRequests = max(1, (int) $monthly->max('requests'));
        $maxRevenue = max(1, (float) $monthly->max('revenue'));
        $monthly = $monthly->map(function ($row) use ($maxAccounts, $maxRequests, $maxRevenue) {
            $row['account_height'] = max(2, round((($row['customers'] + $row['partners']) / $maxAccounts) * 100));
            $row['request_height'] = max(2, round(($row['requests'] / $maxRequests) * 100));
            $row['revenue_height'] = max(2, round(($row['revenue'] / $maxRevenue) * 100));
            return $row;
        });

        $hotelStatuses = Hotel::query()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $subscriptionStatuses = HotelSubscription::query()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $topHotels = DB::table('reservations')->join('hotels', 'hotels.id', '=', 'reservations.hotel_id')
            ->select('hotels.id', 'hotels.name')->selectRaw('COUNT(reservations.id) AS reservation_count')
            ->where('hotels.status', 'active')->whereIn('reservations.status', ['confirmed', 'completed'])
            ->whereBetween('reservations.check_in', [$from->toDateString(), $to->toDateString()])
            ->groupBy('hotels.id', 'hotels.name')->orderByDesc('reservation_count')->limit(8)->get();

        $stats = [
            'customers' => User::where('role', 'customer')->whereNull('archived_at')->whereNull('blacklisted_at')->count(),
            'partners' => User::where('role', 'hotel_owner')->whereNull('archived_at')->whereNull('blacklisted_at')->count(),
            'reservations' => Reservation::count(),
            'pending_reservations' => Reservation::where('status', 'pending')->count(),
            'active_hotels' => (int) ($hotelStatuses['active'] ?? 0),
            'pending_hotels' => (int) ($hotelStatuses['pending'] ?? 0),
            'suspended_hotels' => (int) ($hotelStatuses['suspended'] ?? 0),
            'active_subscriptions' => HotelSubscription::current()->count(),
            'payment_waiting' => (int) ($subscriptionStatuses['awaiting_payment'] ?? 0) + (int) ($subscriptionStatuses['pending_review'] ?? 0),
            'open_chats' => HotelChat::where('status', 'open')->count(),
        ];
        $reservationStatuses = Reservation::query()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');

        $visitFrom = today()->subDays(29);
        $visitTo = today();
        $visitRows = DB::table('site_analytics_daily')->where('dimension', 'all')
            ->whereBetween('stat_date', [$visitFrom->toDateString(), $visitTo->toDateString()])
            ->get()->keyBy(fn ($row) => (string) $row->stat_date);
        $dailyVisits = collect();
        $visitCursor = $visitFrom->copy();
        while ($visitCursor->lte($visitTo)) {
            $day = $visitCursor->toDateString();
            $dailyVisits->push(['date' => $day, 'label' => $visitCursor->format('d/m'), 'views' => (int) ($visitRows->get($day)->page_views ?? 0)]);
            $visitCursor->addDay();
        }
        $maxDailyViews = max(1, (int) $dailyVisits->max('views'));
        $dailyVisits = $dailyVisits->map(function ($row) use ($maxDailyViews) {
            $row['height'] = max(2, (int) round(($row['views'] / $maxDailyViews) * 100));
            return $row;
        });
        $visitorStats = [
            'last_30_days' => (int) $dailyVisits->sum('views'),
            'today' => (int) ($visitRows->get(today()->toDateString())->page_views ?? 0),
            'average' => round($dailyVisits->sum('views') / 30, 1),
        ];
        $topPages = DB::table('site_analytics_daily')->where('dimension', 'path')
            ->whereBetween('stat_date', [$visitFrom->toDateString(), $visitTo->toDateString()])
            ->select('label')->selectRaw('SUM(page_views) AS total_views')->groupBy('label')
            ->orderByDesc('total_views')->limit(8)->get();
        $visitSources = DB::table('site_analytics_daily')->where('dimension', 'source')
            ->whereBetween('stat_date', [$visitFrom->toDateString(), $visitTo->toDateString()])
            ->select('label')->selectRaw('SUM(page_views) AS total_views')->groupBy('label')
            ->orderByDesc('total_views')->limit(6)->get();
        $visitDevices = DB::table('site_analytics_daily')->where('dimension', 'device')
            ->whereBetween('stat_date', [$visitFrom->toDateString(), $visitTo->toDateString()])
            ->select('label')->selectRaw('SUM(page_views) AS total_views')->groupBy('label')
            ->orderByDesc('total_views')->get();

        $leadRows = DB::table('site_analytics_daily')
            ->whereIn('dimension', ['hotel_view', 'hotel_click', 'booking_request'])
            ->whereBetween('stat_date', [$visitFrom->toDateString(), $visitTo->toDateString()])
            ->selectRaw('dimension, label, SUM(page_views) AS total')->groupBy('dimension', 'label')->get();
        $hotelLeads = [];
        foreach ($leadRows as $leadRow) {
            if ($leadRow->dimension === 'hotel_click') {
                [$leadSlug, $leadAction] = array_pad(explode('|', (string) $leadRow->label, 2), 2, '');
                if (in_array($leadAction, ['phone', 'whatsapp'], true)) $hotelLeads[$leadSlug][$leadAction] = (int) $leadRow->total;
                continue;
            }
            $hotelLeads[$leadRow->label][$leadRow->dimension === 'hotel_view' ? 'views' : 'requests'] = (int) $leadRow->total;
        }
        $leadNames = \App\Models\Hotel::query()->whereIn('slug', array_keys($hotelLeads))->pluck('name', 'slug');
        $hotelLeads = collect($hotelLeads)->map(fn ($v, $slug) => [
            'name' => $leadNames[$slug] ?? $slug, 'views' => $v['views'] ?? 0, 'phone' => $v['phone'] ?? 0,
            'whatsapp' => $v['whatsapp'] ?? 0, 'requests' => $v['requests'] ?? 0,
        ])->sortByDesc(fn ($r) => ($r['phone'] + $r['whatsapp'] + $r['requests']) * 1000000 + $r['views'])->take(15)->values();

        return view('admin.statistics', compact('from', 'to', 'monthly', 'stats', 'topHotels', 'reservationStatuses', 'visitFrom', 'visitTo', 'dailyVisits', 'visitorStats', 'topPages', 'visitSources', 'visitDevices', 'hotelLeads'));
    }

    public function crm()
    {
        return view('admin.crm',[
            'stats'=>['customers'=>User::where('role','customer')->whereNull('archived_at')->whereNull('blacklisted_at')->count(),'owners'=>User::where('role','hotel_owner')->whereNull('archived_at')->whereNull('blacklisted_at')->count(),'pending'=>Reservation::where('status','pending')->count(),'contact'=>ContactMessage::count()],
            'reservations'=>Reservation::with(['hotel:id,name,slug','user:id,name,email','room:id,name'])->latest()->paginate(15),
            'recentMessages'=>ContactMessage::latest()->limit(8)->get(),
        ]);
    }

    public function index()
    {
        return $this->renderPage('genel');
    }

    public function section(string $section)
    {
        abort_unless(in_array($section, ['bekleyen', 'sahiplenme', 'oteller', 'kullanicilar', 'blog', 'mesajlar', 'yorumlar', 'rezervasyonlar'], true), 404);
        return $this->renderPage($section);
    }

    private function renderPage(string $section)
    {
        $data = ['section' => $section];
        switch ($section) {
            case 'genel':
                $data['stats'] = [
                    'users' => User::whereNull('archived_at')->whereNull('blacklisted_at')->count(),
                    'reservations' => Reservation::count(),
                    'hotels' => Hotel::active()->count(),
                    'pending' => Hotel::where('status', 'pending')->count(),
                    'pending_reservations' => Reservation::where('status', 'pending')->count(),
                    'open_chats' => \App\Models\HotelChat::where('status', 'open')->count(),
                    'posts' => Post::count(),
                ];
                break;
            case 'rezervasyonlar':
                $rq = Reservation::with(['hotel:id,name,slug', 'user:id,name,email', 'room:id,name', 'pet:id,name', 'reservationTransfer']);
                if (array_key_exists((string) request('durum'), Reservation::STATUS)) $rq->where('status', request('durum'));
                if ($hotelId = filter_var(request('otel'), FILTER_VALIDATE_INT)) $rq->where('hotel_id', $hotelId);
                $term = trim((string) request('q', ''));
                if ($term !== '') $rq->where(function ($query) use ($term) {
                    $query->where('code', 'like', '%'.$term.'%')
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%')->orWhere('phone', 'like', '%'.$term.'%'))
                        ->orWhereHas('hotel', fn ($hotel) => $hotel->where('name', 'like', '%'.$term.'%'))
                        ->orWhereHas('pet', fn ($pet) => $pet->where('name', 'like', '%'.$term.'%'));
                });
                $data['filterHotels'] = Hotel::orderBy('name')->get(['id', 'name']);
                $data['reservations'] = $rq->latest()->paginate(25)->withQueryString();
                break;
            case 'bekleyen':
                $data['pending'] = Hotel::where('status', 'pending')->with(['owner', 'district'])->get();
                break;
            case 'sahiplenme':
                $data['claims'] = HotelClaim::with(['hotel:id,name,slug,owner_id', 'user:id,name,email,phone'])->where('status', 'pending')->oldest()->get();
                break;
            case 'oteller':
                $hq = Hotel::with(['district', 'owner'])->latest();
                if (in_array(request('durum'), ['active','pending','suspended'], true)) $hq->where('status', request('durum'));
                $data['hotels'] = $hq->get();
                break;
            case 'kullanicilar':
                $uq = User::withCount(['hotels','reservations','pets']);
                $term = trim((string) request('q',''));
                if ($term !== '') $uq->where(fn($w)=>$w->where('name','like','%'.$term.'%')->orWhere('email','like','%'.$term.'%')->orWhere('phone','like','%'.$term.'%'));
                if (request('durum')==='arsiv') $uq->whereNotNull('archived_at');
                elseif (request('durum')==='kara-liste') $uq->whereNotNull('blacklisted_at');
                else $uq->whereNull('archived_at')->whereNull('blacklisted_at');
                if (in_array(request('rol'),['customer','hotel_owner','admin'],true)) $uq->where('role',request('rol'));
                $data['users'] = $uq->latest()->paginate(25)->withQueryString();
                $data['userCounts'] = ['active'=>User::whereNull('archived_at')->whereNull('blacklisted_at')->count(),'archived'=>User::whereNotNull('archived_at')->count(),'blacklisted'=>User::whereNotNull('blacklisted_at')->count()];
                break;
            case 'blog':
                $posts = Post::query();
                $term = trim((string) request('q', ''));
                if ($term !== '') $posts->where(fn ($query) => $query->where('title', 'like', '%'.$term.'%')->orWhere('slug', 'like', '%'.$term.'%'));
                match (request('durum')) {
                    'yayinda' => $posts->whereNotNull('published_at')->where('published_at', '<=', now()),
                    'zamanli' => $posts->where('published_at', '>', now()),
                    'taslak' => $posts->whereNull('published_at'),
                    default => null,
                };
                $data['posts'] = $posts->latest()->paginate(25)->withQueryString();
                break;
            case 'mesajlar':
                $messages = ContactMessage::query();
                $term = trim((string) request('q', ''));
                if ($term !== '') $messages->where(fn ($query) => $query
                    ->where('name', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%')
                    ->orWhere('phone', 'like', '%'.$term.'%')
                    ->orWhere('message', 'like', '%'.$term.'%'));
                $data['messages'] = $messages->latest()->paginate(25)->withQueryString();
                break;
            case 'yorumlar':
                $data['reviews'] = Review::with(['hotel:id,name,slug', 'user:id,name'])->orderByDesc('id')->limit(30)->get();
                break;
        }
        return view('admin.index', $data);
    }

    public function userForm(?User $user = null)
    {
        return view('admin.user-form', ['user'=>$user ?? new User()]);
    }

    public function userSave(Request $r, ?User $user = null)
    {
        // Do not flash the identity number or password back into the session.
        $safe = $r->except(['tc_kimlik', 'password', 'password_confirmation', '_token']);
        $r->merge([
            'email' => trim((string) $r->input('email', '')) ?: null,
            'phone' => trim((string) $r->input('phone', '')) ?: null,
            'tc_kimlik' => trim((string) $r->input('tc_kimlik', '')) ?: null,
        ]);
        $emailUnique = \Illuminate\Validation\Rule::unique('users', 'email');
        if ($user?->exists) $emailUnique->ignore($user->id);
        $emailRules = ['nullable', 'email', 'max:160', $emailUnique];
        $identityRequired = !$r->filled('email') && (!$user?->exists || !$user->identity_hash);
        $rules = [
            'name' => 'required|string|max:120',
            'email' => $emailRules,
            'phone' => 'nullable|string|max:30',
            'tc_kimlik' => [
                $identityRequired ? 'required' : 'nullable',
                'digits:11',
                function ($attribute, $value, $fail) {
                    if ($value !== null && !\App\Support\IdentityNumber::isValid($value)) $fail('T.C. kimlik numarasını kontrol edin.');
                },
            ],
            'role' => 'required|in:customer,hotel_owner,admin',
            'password' => $user ? 'nullable|string|min:12|confirmed' : 'required|string|min:12|confirmed',
        ];
        $validator = \Illuminate\Support\Facades\Validator::make($r->all(), $rules);
        if ($validator->fails()) return back()->withErrors($validator)->withInput($safe);
        $d = $validator->validated();

        $phoneNormalized = \App\Support\PhoneNumber::normalize($d['phone'] ?? null);
        if (!empty($d['phone']) && !$phoneNormalized) return back()->withErrors(['phone' => 'Geçerli bir telefon numarası girin.'])->withInput($safe);
        if (empty($d['email']) && !$phoneNormalized) return back()->withErrors(['phone' => 'E-posta kullanmayan hesaplarda geçerli telefon numarası zorunludur.'])->withInput($safe);
        if ($phoneNormalized && User::where('phone_normalized', $phoneNormalized)->when($user?->exists, fn ($q) => $q->where('id', '!=', $user->id))->exists()) {
            return back()->withErrors(['phone' => 'Bu telefon numarası başka bir hesapta kullanılıyor.'])->withInput($safe);
        }
        $identityHash = !empty($d['tc_kimlik']) ? \App\Support\IdentityNumber::hash($d['tc_kimlik']) : null;
        if ($identityHash && User::where('identity_hash', $identityHash)->when($user?->exists, fn ($q) => $q->where('id', '!=', $user->id))->exists()) {
            return back()->withErrors(['tc_kimlik' => 'Bu kimlik numarası başka bir hesapta kullanılıyor.'])->withInput($safe);
        }

        $user ??= new User();
        if ($user->exists && $user->id === $r->user()->id && $d['role'] !== 'admin') return back()->withErrors(['role' => 'Kendi yönetici yetkinizi düşüremezsiniz.'])->withInput($safe);
        if ($user->exists && $user->role === 'admin' && $d['role'] !== 'admin' && User::where('role', 'admin')->count() <= 1) return back()->withErrors(['role' => 'Son yönetici hesabı başka role geçirilemez.'])->withInput($safe);
        if ($user->exists && $user->hotels()->exists() && !in_array($d['role'], ['hotel_owner', 'admin'], true)) return back()->withErrors(['role' => 'Otele bağlı kullanıcı işletme hesabı olarak kalmalı.'])->withInput($safe);

        $user->name = $d['name'];
        $user->email = !empty($d['email']) ? mb_strtolower(trim($d['email'])) : null;
        $user->phone = $d['phone'] ?? null;
        $user->phone_normalized = $phoneNormalized;
        if ($identityHash) $user->identity_hash = $identityHash;
        $user->role = $d['role'];
        if (!empty($d['password'])) $user->password = $d['password'];
        $user->save();
        return redirect()->route('admin.users.index')->with('ok', $user->wasRecentlyCreated ? 'Kullanıcı hesabı oluşturuldu.' : 'Kullanıcı bilgileri güncellendi.');
    }

    public function userArchive(Request $r, User $user)
    {
        abort_if($user->id===$r->user()->id,403,'Kendi hesabınızı arşivleyemezsiniz.');
        if($user->role==='admin' && User::where('role','admin')->whereNull('archived_at')->count()<=1) return back()->withErrors(['user'=>'Son yönetici hesabı arşivlenemez.']);
        $user->archived_at=now();$user->save();return back()->with('ok','Hesap arşivlendi; rezervasyon ve geçmiş kayıtları korundu.');
    }

    public function userRestore(User $user)
    {
        $user->archived_at=null;$user->save();return back()->with('ok','Hesap arşivden çıkarıldı.');
    }

    public function userMessage(Request $request, User $user)
    {
        abort_if($user->role === 'admin', 422, 'Yönetici hesapları bu mesaj akışını kullanamaz.');
        $data = $request->validate(['body' => 'required|string|min:2|max:3000']);
        $hotelId = $user->isOwner()
            ? Hotel::where('owner_id', $user->id)->orderBy('id')->value('id')
            : Reservation::where('user_id', $user->id)->latest('id')->value('hotel_id');
        if (!$hotelId && !$user->isOwner()) {
            $hotelId = HotelChat::where('user_id', $user->id)->latest('id')->value('hotel_id');
        }
        if (!$hotelId) return back()->withErrors(['message' => 'Bu kullanıcıya özel mesaj göndermek için bağlı bir otel sohbeti veya rezervasyonu bulunamadı.']);

        $chat = HotelChat::where('hotel_id', $hotelId)->where('user_id', $user->id)->where('status', 'open')->latest('id')->first();
        if (!$chat) $chat = HotelChat::create([
            'hotel_id' => $hotelId, 'user_id' => $user->id, 'guest_name' => $user->name,
            'status' => 'open', 'last_message_at' => now(),
        ]);
        $chat->messages()->create([
            'sender_user_id' => $request->user()->id, 'sender_type' => 'admin', 'body' => trim($data['body']),
        ]);
        $chat->update(['last_message_at' => now()]);
        return back()->with('ok', 'Yönetici mesajı kullanıcının özel sohbet kutusuna gönderildi.');
    }

    public function userBlacklist(Request $r, User $user)
    {
        abort_if($user->id===$r->user()->id || $user->role==='admin',403);
        $d=$r->validate(['reason'=>'required|string|max:500']);$user->blacklisted_at=now();$user->blacklist_reason=$d['reason'];$user->save();return back()->with('ok','Hesap kara listeye alındı.');
    }

    public function userUnblacklist(User $user)
    {
        $user->blacklisted_at=null;$user->blacklist_reason=null;$user->save();return back()->with('ok','Kara liste kaydı kaldırıldı.');
    }

    public function userDelete(Request $r, User $user)
    {
        abort_if($user->id === $r->user()->id, 403, 'Kendi hesabınızı silemezsiniz.');

        $deleted = DB::transaction(function () use ($user) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $activeAdmins = User::query()->where('role', 'admin')->whereNull('archived_at')->whereNull('blacklisted_at')->lockForUpdate()->get(['id']);
            if ($locked->role === 'admin' && $activeAdmins->count() <= 1) return false;
            if (\App\Models\AdoptionListing::where('user_id', $locked->id)->exists()) return false;

            $references = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->select(['TABLE_NAME', 'COLUMN_NAME'])
                ->where('REFERENCED_TABLE_SCHEMA', DB::connection()->getDatabaseName())
                ->where('REFERENCED_TABLE_NAME', 'users')->get();
            foreach ($references as $reference) {
                if (DB::table($reference->TABLE_NAME)->where($reference->COLUMN_NAME, $locked->id)->exists()) return false;
            }

            $locked->delete();
            return true;
        });

        if (!$deleted) return back()->withErrors(['user' => 'Bu hesap son yönetici veya geçmiş kayıtlarına bağlı. Kalıcı silme engellendi; geçmişi korumak için hesabı arşivleyin.']);
        return redirect()->route('admin.users.index')->with('ok', 'Bağlı kaydı olmayan kullanıcı hesabı kalıcı olarak silindi.');
    }

    public function hotelForm(?Hotel $hotel = null)
    {
        return view('admin.hotel-form', [
            'hotel' => $hotel ?? new Hotel(),
            'cities' => City::with('districts')->orderBy('name')->get(),
            'owners' => User::orderBy('name')->get(['id', 'name', 'email', 'role']),
            'modules' => \App\Support\PlatformModules::all(),
            'hotelModules' => $hotel?->exists ? \App\Models\HotelFeatureFlag::where('hotel_id', $hotel->id)->get()->keyBy('module_key') : collect(),
        ]);
    }

    public function hotelModulesSave(Request $request, Hotel $hotel)
    {
        $keys = array_keys(\App\Support\PlatformModules::MODULES);
        $data = $request->validate([
            'modules' => ['required', 'array'],
            'modules.*' => ['required', 'in:inherit,on,off'],
        ]);
        foreach ($keys as $key) {
            $value = $data['modules'][$key] ?? 'inherit';
            if ($value === 'inherit') {
                \App\Models\HotelFeatureFlag::where('hotel_id', $hotel->id)->where('module_key', $key)->delete();
                continue;
            }
            \App\Models\HotelFeatureFlag::updateOrCreate(
                ['hotel_id' => $hotel->id, 'module_key' => $key],
                ['enabled' => $value === 'on', 'updated_by' => $request->user()->id]
            );
        }
        return back()->with('ok', 'Tesis modül yetkileri güncellendi. Genel ayarlardaki kapalı modüller, tesis istisnasından bağımsız olarak kapalı kalır.');
    }

    public function hotelSave(Request $r, ?Hotel $hotel = null)
    {
        $d = $r->validate([
            'name' => 'required|string|max:160',
            'slug' => ['nullable', 'string', 'max:190', \Illuminate\Validation\Rule::unique('hotels', 'slug')->ignore($hotel?->id)],
            'owner_id' => 'required|exists:users,id',
            'city_id' => 'required|exists:cities,id',
            'district_id' => 'nullable|exists:districts,id',
            'description' => 'nullable|string|max:12000',
            'policies' => 'nullable|string|max:12000',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:255',
            'license_no' => 'nullable|string|max:80',
            'status' => 'required|in:pending,active,suspended',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'accepts_dogs' => 'nullable|boolean', 'accepts_cats' => 'nullable|boolean',
            'has_daycare' => 'nullable|boolean', 'verify_license' => 'nullable|boolean',
        ]);
        if (!empty($d['district_id']) && !District::whereKey($d['district_id'])->where('city_id', $d['city_id'])->exists()) {
            return back()->withErrors(['district_id' => 'Seçilen ilçe seçilen şehre ait değil.'])->withInput();
        }
        $hotel ??= new Hotel();
        $ownerHasPlan = \App\Models\HotelSubscription::current()->where('requested_by', $d['owner_id'])->exists();
        $hotelHasPlan = $hotel->exists && \App\Models\HotelSubscription::current()->where('hotel_id', $hotel->id)->exists();
        if ($d['status'] === 'active' && (!$hotel->exists || $hotel->status !== 'active') && !$ownerHasPlan && !$hotelHasPlan) {
            return back()->withErrors(['status' => 'Yeni bir tesisi yayına almadan önce ücretli abonelik talebini ödeme kaydıyla etkinleştirin.'])->withInput();
        }
        $slug = Str::slug($d['slug'] ?: $d['name']);
        $base = $slug; $n = 2;
        while (Hotel::where('slug', $slug)->when($hotel->exists, fn($q) => $q->where('id', '!=', $hotel->id))->exists()) $slug = $base.'-'.$n++;
        $hotel->fill([
            'name' => $d['name'], 'slug' => $slug, 'owner_id' => $d['owner_id'],
            'city_id' => $d['city_id'], 'district_id' => $d['district_id'] ?? null,
            'description' => $d['description'] ?? null, 'policies' => $d['policies'] ?? null,
            'phone' => $d['phone'] ?? null, 'address' => $d['address'] ?? null,
            'license_no' => $d['license_no'] ?? null, 'status' => $d['status'],
            'commission_rate' => $d['commission_rate'] ?? null,
            'accepts_dogs' => $r->boolean('accepts_dogs'), 'accepts_cats' => $r->boolean('accepts_cats'),
            'has_daycare' => $r->boolean('has_daycare'),
        ]);
        if ($r->boolean('verify_license')) $hotel->license_verified_at = $hotel->license_verified_at ?? now();
        $hotel->save();
        $owner = User::find($hotel->owner_id);
        if ($owner && $owner->role === 'customer') { $owner->role = 'hotel_owner'; $owner->save(); }
        return redirect()->route('admin.hotels.index')->with('ok', 'Otel kaydedildi.');
    }

    public function hotelArchive(Hotel $hotel)
    {
        $hotel->update(['status' => 'suspended']);
        return back()->with('ok', 'Otel arşivlendi ve yayından kaldırıldı; rezervasyon geçmişi korundu.');
    }

    public function listingApplicationStatus(Request $request, ContactMessage $contactMessage)
    {
        abort_unless(str_starts_with((string) $contactMessage->message, 'ÜCRETSİZ TESİS LİSTELEME BAŞVURUSU'), 404);
        $data = $request->validate([
            'listing_status' => 'required|in:received,reviewing,need_info,approved,rejected',
            'listing_note' => 'required_if:listing_status,need_info|nullable|string|max:2000',
        ]);
        $previousStatus = $contactMessage->listing_status ?? 'received';
        $previousNote = trim((string) $contactMessage->listing_note);
        $data['listing_note'] = trim((string) ($data['listing_note'] ?? '')) ?: null;
        $contactMessage->update($data);
        $changed = $previousStatus !== $data['listing_status'] || $previousNote !== (string) $data['listing_note'];
        $emailSent = null;
        if ($changed) {
            $labels = [
                'received' => 'Ekibe iletildi',
                'reviewing' => 'İnceleniyor',
                'need_info' => 'Ek bilgi bekleniyor',
                'approved' => 'Listeleme için uygun',
                'rejected' => 'Başvuru uygun bulunmadı',
            ];
            preg_match('/^Tesis adı: (.+)$/m', (string) $contactMessage->message, $match);
            $hotelName = $match[1] ?? 'Tesis başvurunuz';
            $statusText = $labels[$data['listing_status']];
            $extra = $data['listing_status'] === 'need_info' && $data['listing_note']
                ? ' İstenen ek bilgi: '.$data['listing_note']
                : '';
            $hasOwnerAccount = User::query()->where('email', $contactMessage->email)->where('role', 'hotel_owner')->exists();
            $emailSent = \App\Support\Bildirim::gonder(
                $contactMessage->email,
                'Ücretsiz tesis başvurunuz güncellendi · '.$hotelName,
                'mail.bildirim',
                [
                    'baslik' => 'Tesis başvurunuzun durumu güncellendi',
                    'metin' => $hotelName.' başvurunuzun yeni durumu: '.$statusText.'.'.$extra,
                    'link' => $hasOwnerAccount ? route('partner.listing.status') : route('contact'),
                    'linkEtiket' => $hasOwnerAccount ? 'Başvuru durumunu görüntüle' : 'İletişim bilgilerini görüntüle',
                ]
            );
        }
        $message = !$changed
            ? 'Başvuru durumu ve açıklaması değişmedi.'
            : (($emailSent && config('mail.default') !== 'log')
                ? 'Başvuru güncellendi ve başvuru sahibine e-posta gönderildi.'
                : (config('mail.default') === 'log'
                    ? 'Başvuru güncellendi; sunucu e-posta gönderimini log modunda tuttuğu için başvuru sahibine e-posta gitmedi.'
                    : 'Başvuru güncellendi; e-posta gönderilemedi.'));
        return back()->with('ok', $message);
    }

    public function deleteMessage(ContactMessage $contactMessage)
    {
        $contactMessage->delete();
        return back()->with('ok', 'İletişim mesajı silindi.');
    }

    public function hotelStatus(Request $r, Hotel $hotel)
    {
        $d = $r->validate(['status' => 'required|in:pending,active,suspended', 'verify_license' => 'nullable|boolean']);
        if ($d['status'] === 'active' && $hotel->status !== 'active'
            && !\App\Models\HotelSubscription::current()->where('hotel_id', $hotel->id)->exists()
            && !\App\Models\HotelSubscription::current()->where('requested_by', $hotel->owner_id)->exists()) {
            return back()->withErrors(['status' => 'Tesisi yayına almak için ücretli abonelik ödemesini önce Abonelik ve planlar alanından kaydedin.']);
        }
        $hotel->status = $d['status'];
        // An explicit admin status change takes precedence over the
        // subscription expiry marker and must not be auto-undone later.
        if (\Illuminate\Support\Facades\Schema::hasColumn('hotels', 'subscription_suspended')) {
            $hotel->subscription_suspended = false;
        }
        if ($r->boolean('verify_license')) $hotel->license_verified_at = now();
        $hotel->save();
        return back()->with('ok', 'Otel durumu güncellendi.');
    }

    /** Yorum moderasyonu: yayinla / gizle, ardindan otelin puan ortalamasi yenilenir. */
    public function reviewStatus(Request $r, Review $review)
    {
        $d = $r->validate(['status' => 'required|in:published,hidden']);
        $review->status = $d['status'];
        $review->save();
        Hotel::find($review->hotel_id)?->refreshRating();
        return back()->with('ok', $d['status'] === 'published' ? 'Yorum yayınlandı.' : 'Yorum gizlendi.');
    }

    /** Sahiplenme talebini atomik biçimde karara bağla ve gerekirse otel hesabını devret. */
    public function claimDecide(Request $r, HotelClaim $claim)
    {
        $d = $r->validate(['action' => 'required|in:approve,reject', 'admin_note' => 'nullable|string|max:500']);
        $claimId = $claim->id;
        $hotelId = $claim->hotel_id;
        $claim = \Illuminate\Support\Facades\DB::transaction(function () use ($claimId, $hotelId, $d, $r) {
            // Onaylarda önce oteli kilitle: iki ayrı talep aynı anda sahipliği alamaz.
            $hotel = null;
            if ($d['action'] === 'approve') {
                $hotel = Hotel::whereKey($hotelId)->with('owner')->lockForUpdate()->firstOrFail();
            }
            $claim = HotelClaim::whereKey($claimId)->lockForUpdate()->firstOrFail();
            if ($claim->status !== 'pending') {
                throw \Illuminate\Validation\ValidationException::withMessages(['claim' => 'Bu talep zaten sonuçlandırılmış.']);
            }
            if ($d['action'] === 'approve') {
                if (!$hotel->sahipsizMi()) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['claim' => 'Bu otel zaten başka bir hesaba bağlı; talep onaylanamaz.']);
                }
                $user = User::whereKey($claim->user_id)->lockForUpdate()->firstOrFail();
                $hotel->owner_id = $user->id;
                if ($claim->license_no && !$hotel->license_no) $hotel->license_no = $claim->license_no;
                $hotel->save();
                if ($user->role !== 'admin') { $user->role = 'hotel_owner'; $user->save(); }
                HotelClaim::where('hotel_id', $hotel->id)->where('id', '!=', $claim->id)->where('status', 'pending')
                    ->update(['status' => 'rejected', 'admin_note' => 'Başka bir talep onaylandı.', 'decided_at' => now(), 'decided_by' => $r->user()->id]);
                $claim->status = 'approved';
            } else {
                $claim->status = 'rejected';
            }
            $claim->admin_note = $d['admin_note'] ?? null;
            $claim->decided_at = now();
            $claim->decided_by = $r->user()->id;
            $claim->save();
            return $claim->fresh(['hotel', 'user']);
        });
        try { \App\Support\Bildirim::sahiplenmeSonuc($claim); } catch (\Throwable $e) { report($e); }
        return back()->with('ok', $d['action'] === 'approve' ? 'Talep onaylandı; otel kullanıcıya devredildi.' : 'Talep reddedildi.');
    }

    public function menus()
    {
        $defaults = [
            'header_menu' => "Pet Otel|/kedi-kopek-otelleri\nKedi Oteli|/kedi-otelleri\nKöpek Oteli|/kopek-otelleri\nFiyatlar|/fiyatlar\nMağaza|/magaza\nBlog|/blog\nSahiplendir|/sahiplendir\nForum|/forum\nOtelini Kaydet|/partner",
            'footer_explore' => "Antalya Pet Otelleri|/kedi-kopek-otelleri\nKöpek Otelleri|/kopek-otelleri\nKedi Otelleri|/kedi-otelleri\nBlog|/blog\nSahiplendir|/sahiplendir\nForum|/forum\nGünlük Fiyatlar|/fiyatlar",
            'footer_corporate' => "Hakkımızda|/sayfa/hakkimizda\nNasıl Çalışır?|/sayfa/nasil-calisir\nSıkça Sorulan Sorular|/sayfa/sss\nOtel Sahibi Ol|/partner\nİletişim|/iletisim",
            'footer_legal' => "Gizlilik Politikası|/gizlilik\nKullanım Koşulları|/kullanim-kosullari\nİptal ve İade|/iptal-iade\nMesafeli Satış Politikası|/mesafeli-satis-politikasi\nÇerez Politikası|/cerez-politikasi",
        ];
        $groups = [];
        foreach ($defaults as $key => $default) {
            $raw = Setting::al($key, $default) ?: $default;
            $groups[$key] = collect(preg_split('/\r?\n/', $raw) ?: [])->filter()->map(function ($line) {
                $parts = explode('|', $line, 2);
                return ['label' => trim($parts[0] ?? ''), 'url' => trim($parts[1] ?? '')];
            })->values()->all();
        }
        $pages = Page::query()->orderBy('title')->get(['title','slug'])->map(fn($page)=>['label'=>$page->title,'url'=>'/sayfa/'.$page->slug]);
        $availablePages = collect([
            ['label'=>'Ana sayfa','url'=>'/'], ['label'=>'Pet otelleri','url'=>'/kedi-kopek-otelleri'],
            ['label'=>'Köpek otelleri','url'=>'/kopek-otelleri'],
            ['label'=>'Kedi otelleri','url'=>'/kedi-otelleri'],
            ['label'=>'Fiyat rehberi','url'=>'/fiyatlar'], ['label'=>'Blog','url'=>'/blog'],
            ['label'=>'İletişim','url'=>'/iletisim'], ['label'=>'Mağaza','url'=>'/magaza'],
            ['label'=>'Forum','url'=>'/forum'], ['label'=>'Sahiplendirme','url'=>'/sahiplendir'],
            ['label'=>'İşletme başvurusu','url'=>'/partner'],
        ])->concat($pages)->unique('url')->values();
        return view('admin.menus', ['groups'=>$groups,'availablePages'=>$availablePages,
            'footerDescription'=>Setting::al('footer_description','Antalya’da kedi ve köpek konaklaması sunan tesisleri; oda tipi, günlük fiyat, giriş-çıkış saatleri ve konaklama kurallarıyla birlikte karşılaştırın.'),
            'footerBookingNote'=>Setting::al('footer_booking_note','Rezervasyon talebi ilgili tesise iletilir; ödeme otelde yapılır.'),
            'footerCopyright'=>Setting::al('footer_copyright','© {year} Antalya Pet Otel · antalyapetotel.com')]);
    }

    public function menusSave(Request $request)
    {
        $data = $request->validate([
            'header_menu'=>'required|array|max:30','header_menu.*.label'=>'required|string|max:100','header_menu.*.url'=>'required|string|max:500',
            'footer_explore'=>'nullable|array|max:30','footer_explore.*.label'=>'required|string|max:100','footer_explore.*.url'=>'required|string|max:500',
            'footer_corporate'=>'nullable|array|max:30','footer_corporate.*.label'=>'required|string|max:100','footer_corporate.*.url'=>'required|string|max:500',
            'footer_legal'=>'nullable|array|max:30','footer_legal.*.label'=>'required|string|max:100','footer_legal.*.url'=>'required|string|max:500',
            'footer_description'=>'nullable|string|max:1200','footer_booking_note'=>'nullable|string|max:500','footer_copyright'=>'nullable|string|max:200',
        ]);
        $save=[];
        foreach (['header_menu','footer_explore','footer_corporate','footer_legal'] as $group) {
            $lines=[];
            foreach (($data[$group] ?? []) as $item) {
                $url=trim($item['url']);
                if (!str_starts_with($url,'/') && !preg_match('~^https://~i',$url)) return back()->withErrors([$group=>'Bağlantı adresi / ile başlamalı veya https:// adresi olmalıdır.'])->withInput();
                if (str_starts_with($url,'//') || str_starts_with($url,'/\\')) return back()->withErrors([$group=>'Geçersiz bağlantı adresi.'])->withInput();
                $lines[]=trim($item['label']).'|'.$url;
            }
            $save[$group]=implode("\n",$lines);
        }
        foreach (['footer_description','footer_booking_note','footer_copyright'] as $key) $save[$key]=$data[$key] ?? '';
        Setting::kaydet($save);
        return redirect()->route('admin.menus')->with('ok','Üst menü ve altbilgi bağlantıları kaydedildi.');
    }

    public function settings()
    {
        $v = [];
        foreach (Setting::ALANLAR as $k) $v[$k] = Setting::al($k, '');
        $v['header_menu'] = $v['header_menu'] ?: "Pet Otel|/kedi-kopek-otelleri\nKedi Oteli|/kedi-otelleri\nKöpek Oteli|/kopek-otelleri\nFiyatlar|/fiyatlar\nMağaza|/magaza\nBlog|/blog\nSahiplendir|/sahiplendir\nForum|/forum\nOtelini Kaydet|/partner";
        $v['footer_description'] = $v['footer_description'] ?: 'Antalya’da kedi ve köpek konaklaması sunan tesisleri; oda tipi, günlük fiyat, giriş-çıkış saatleri ve konaklama kurallarıyla birlikte karşılaştırın.';
        $v['footer_booking_note'] = $v['footer_booking_note'] ?: 'Rezervasyon talebi ilgili tesise iletilir; ödeme otelde yapılır.';
        $v['footer_explore'] = $v['footer_explore'] ?: "Antalya Pet Otelleri|/kedi-kopek-otelleri\nKöpek Otelleri|/kopek-otelleri\nKedi Otelleri|/kedi-otelleri\nBlog|/blog\nSahiplendir|/sahiplendir\nForum|/forum\nGünlük Fiyatlar|/fiyatlar";
        $v['footer_corporate'] = $v['footer_corporate'] ?: "Hakkımızda|/sayfa/hakkimizda\nNasıl Çalışır?|/sayfa/nasil-calisir\nSıkça Sorulan Sorular|/sayfa/sss\nOtel Sahibi Ol|/partner\nİletişim|/iletisim";
        $v['footer_legal'] = $v['footer_legal'] ?: "Gizlilik Politikası|/gizlilik\nKullanım Koşulları|/kullanim-kosullari\nİptal ve İade|/iptal-iade\nMesafeli Satış Politikası|/mesafeli-satis-politikasi\nÇerez Politikası|/cerez-politikasi";
        $v['footer_copyright'] = $v['footer_copyright'] ?: '© {year} Antalya Pet Otel · antalyapetotel.com';
        $v['call_recording_days'] = $v['call_recording_days'] ?: '30';
        $v['home_hero_prefix'] = $v['home_hero_prefix'] ?: 'Minik dostunuz için';
        $v['home_hero_highlight'] = $v['home_hero_highlight'] ?: 'doğru pet oteli';
        $v['home_hero_suffix'] = $v['home_hero_suffix'] ?: 'bulun.';
        $v['home_hero_description'] = $v['home_hero_description'] ?: 'Lisanslı ve güvenilir tesisleri karşılaştırın. Dostunuz için uygun yeri kolayca seçin.';
        $v['home_hotels_title'] = $v['home_hotels_title'] ?: 'Antalya’daki tüm pet otelleri';
        $v['home_hotels_description'] = $v['home_hotels_description'] ?: 'Fiyat, konum ve oda özelliklerini karşılaştırıp dostunuz için en uygun tesisi bulun.';
        $media = collect(Storage::disk('public')->files('site-media'))->filter(fn ($path) => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg','jpeg','png','webp'], true))->values();
        $defaultHomeSections = [
            'hero' => 'Hero, arama ve durum kartları', 'hotels' => 'Pet otel listesi',
            'features' => 'Neden Antalya Pet Otel', 'steps' => 'Nasıl çalışır',
            'pawlike' => 'Pet dostu özellik bandı', 'rooms' => 'Öne çıkan oda tipleri',
            'blog' => 'Son yazılar', 'districts' => 'İlçe bağlantıları',
            'faq' => 'Sıkça sorulan sorular', 'cta' => 'İşletme kayıt çağrısı',
        ];
        $savedHomeSections = json_decode((string) Setting::al('home_sections', ''), true) ?: [];
        $savedOrder = array_values(array_intersect($savedHomeSections['order'] ?? [], array_keys($defaultHomeSections)));
        $homeSectionOrder = array_values(array_unique(array_merge($savedOrder, array_diff(array_keys($defaultHomeSections), $savedOrder))));
        $homeSectionVisible = array_key_exists('visible', $savedHomeSections)
            ? array_values(array_intersect($savedHomeSections['visible'], array_keys($defaultHomeSections)))
            : array_keys($defaultHomeSections);
        $homeSections = collect($homeSectionOrder)->map(fn ($key) => ['key' => $key, 'label' => $defaultHomeSections[$key]])->all();
        return view('admin.ayarlar', ['v' => $v, 'media' => $media, 'homeSections' => $homeSections, 'homeSectionOrder' => $homeSectionOrder, 'homeSectionVisible' => $homeSectionVisible, 'paytrStatus' => \App\Support\PaytrSettings::status(), 'modules' => \App\Support\PlatformModules::all(), 'whatsappStatus' => WhatsAppCloudSetting::status()]);
    }

    public function settingsSave(Request $r)
    {
        $d = $r->validate([
            'site_name' => 'required|string|max:80',
            'site_tagline' => 'nullable|string|max:160',
            'meta_title' => 'nullable|string|max:70',
            'meta_description' => 'nullable|string|max:320',
            'home_hero_prefix' => 'nullable|string|max:100',
            'home_hero_highlight' => 'nullable|string|max:100',
            'home_hero_suffix' => 'nullable|string|max:100',
            'home_hero_description' => 'nullable|string|max:500',
            'home_hero_image' => 'nullable|string|max:255',
            'home_hotels_title' => 'nullable|string|max:160',
            'home_hotels_description' => 'nullable|string|max:320',
            'home_sections_order' => 'nullable|string|max:300',
            'home_sections_visible' => 'nullable|array',
            'home_sections_visible.*' => 'in:hero,hotels,features,steps,pawlike,rooms,blog,districts,faq,cta',
            'site_phone' => 'nullable|string|max:30',
            'site_email' => 'nullable|email|max:160',
            'social_whatsapp' => 'nullable|string|max:30',
            'site_address' => 'nullable|string|max:200',
            'social_instagram' => 'nullable|url|max:200',
            'social_facebook' => 'nullable|url|max:200',
            'header_menu' => 'nullable|string|max:5000',
            'footer_description' => 'nullable|string|max:1200',
            'footer_booking_note' => 'nullable|string|max:500',
            'footer_explore' => 'nullable|string|max:5000',
            'footer_corporate' => 'nullable|string|max:5000',
            'footer_legal' => 'nullable|string|max:5000',
            'footer_copyright' => 'nullable|string|max:200',
            'subscription_payment_instructions' => 'nullable|string|max:2000',
            'module_guest_crm' => 'nullable|boolean',
            'module_stay_updates' => 'nullable|boolean',
            'module_live_camera' => 'nullable|boolean',
            'module_private_calls' => 'nullable|boolean',
            'call_recording_days' => 'required|integer|min:7|max:365',
            'paytr_merchant_id' => 'nullable|string|max:120',
            'paytr_merchant_key' => 'nullable|string|max:120',
            'paytr_merchant_salt' => 'nullable|string|max:120',
            'paytr_test_mode' => 'nullable|in:0,1',
            'whatsapp_phone_number_id' => 'nullable|string|max:80',
            'whatsapp_access_token' => 'nullable|string|max:3000',
            'whatsapp_api_version' => ['nullable','string','regex:/^v[0-9]+\.[0-9]+$/','max:16'],
            'whatsapp_invoice_template' => 'nullable|string|alpha_dash|max:100',
            'whatsapp_reservation_hotel_template' => 'nullable|string|alpha_dash|max:100',
            'whatsapp_reservation_customer_template' => 'nullable|string|alpha_dash|max:100',
            'whatsapp_reservation_confirmed_template' => 'nullable|string|alpha_dash|max:100',
            'whatsapp_template_language' => ['nullable','string','regex:/^[a-z]{2}(?:_[A-Z]{2})?$/','max:16'],
        ]);
        if (!empty($d['home_hero_image'])) {
            $heroPath = $d['home_hero_image'];
            abort_unless(Str::startsWith($heroPath, 'site-media/') && Storage::disk('public')->exists($heroPath)
                && str_starts_with((string) Storage::disk('public')->mimeType($heroPath), 'image/'), 422);
        }
        $paytrInput = [
            'merchant_id' => $d['paytr_merchant_id'] ?? '',
            'merchant_key' => $d['paytr_merchant_key'] ?? '',
            'merchant_salt' => $d['paytr_merchant_salt'] ?? '',
        ];
        $paytrTouched = collect($paytrInput)->contains(fn ($value) => trim((string) $value) !== '') || array_key_exists('paytr_test_mode', $d);
        if ($paytrTouched && \App\Support\PaytrSettings::environmentReady()) {
            return back()->withErrors(['paytr' => 'PayTR sunucu gizli ayarlarından yönetiliyor. Yönetim ekranındaki değerleri değiştirmek için önce sunucu ortam ayarlarını kaldırın.'])->withInput($r->except(['paytr_merchant_id', 'paytr_merchant_key', 'paytr_merchant_salt']));
        }
        if ($paytrTouched) {
            try {
                \App\Support\PaytrSettings::saveFromAdmin($paytrInput + ['test_mode' => $d['paytr_test_mode'] ?? null]);
            } catch (\Throwable $e) {
                report($e);
                return back()->withErrors(['paytr' => 'PayTR ayarları güvenli biçimde kaydedilemedi.'])->withInput($r->except(['paytr_merchant_id', 'paytr_merchant_key', 'paytr_merchant_salt']));
            }
        }
        $whatsappInput = [];
        foreach (['phone_number_id','access_token','api_version','invoice_template','reservation_hotel_template','reservation_customer_template','reservation_confirmed_template','template_language'] as $field) {
            $key = 'whatsapp_' . $field;
            if (!empty($d[$key])) $whatsappInput[$field] = $d[$key];
            unset($d[$key]);
        }
        if ($whatsappInput) {
            try { WhatsAppCloudSetting::saveFromAdmin($whatsappInput); }
            catch (\Throwable $e) { report($e); return back()->withErrors(['whatsapp' => 'WhatsApp ayarları güvenli biçimde kaydedilemedi.'])->withInput($r->except(['whatsapp_access_token'])); }
        }
        unset($d['paytr_merchant_id'], $d['paytr_merchant_key'], $d['paytr_merchant_salt'], $d['paytr_test_mode'], $d['home_sections_order'], $d['home_sections_visible']);
        $sectionKeys = ['hero','hotels','features','steps','pawlike','rooms','blog','districts','faq','cta'];
        $requestedOrder = array_values(array_intersect(array_filter(explode(',', (string) $r->input('home_sections_order', ''))), $sectionKeys));
        $requestedOrder = array_values(array_unique(array_merge($requestedOrder, array_diff($sectionKeys, $requestedOrder))));
        $requestedVisible = array_values(array_intersect($r->input('home_sections_visible', []), $sectionKeys));
        foreach (array_keys(\App\Support\PlatformModules::MODULES) as $module) {
            $key = 'module_' . $module;
            $d[$key] = $r->boolean($key) ? '1' : '0';
        }
        Setting::kaydet($d);
        Setting::kaydet(['home_sections' => json_encode(['order' => $requestedOrder, 'visible' => $requestedVisible], JSON_UNESCAPED_UNICODE)]);
        return back()->with('ok', 'Ayarlar kaydedildi.');
    }

    public function whatsappConnectionTest()
    {
        try {
            $result = \App\Support\WhatsAppCloudApi::checkConnection();
            $name = (string) data_get($result, 'verified_name', 'WhatsApp Business');
            $number = (string) data_get($result, 'display_phone_number', '');
            return back()->with('whatsapp_check', 'Bağlantı doğrulandı: ' . $name . ($number ? ' · ' . $number : ''));
        } catch (\Throwable $e) {
            return back()->withErrors(['whatsapp_check' => 'Bağlantı doğrulanamadı: ' . mb_substr($e->getMessage(), 0, 240)]);
        }
    }

    public function sendInvoiceWhatsApp(Request $request, ReservationInvoice $invoice)
    {
        $request->validate(['whatsapp_consent' => 'accepted']);
        abort_unless($invoice->isIssued(), 409, 'Önce düzenlenmiş bir fatura yüklenmelidir.');
        abort_if($invoice->whatsapp_status === 'sent', 409, 'Bu fatura WhatsApp üzerinden daha önce gönderilmiş.');
        $invoice->loadMissing(['reservation.user']);
        $recipient = (string) ($invoice->reservation->user->phone ?? '');
        try {
            \App\Support\WhatsAppCloudApi::sendInvoiceDocument($invoice, $recipient);
            $invoice->update(['whatsapp_status' => 'sent', 'whatsapp_sent_at' => now()]);
            return back()->with('ok', 'Fatura Meta WhatsApp API’sine iletildi. Bu durum Meta’nın mesajı kabul ettiğini gösterir.');
        } catch (\Throwable $e) {
            $invoice->update(['whatsapp_status' => 'failed']);
            return back()->withErrors(['whatsapp_send' => 'WhatsApp gönderimi başarısız: ' . mb_substr($e->getMessage(), 0, 240)]);
        }
    }

    public function media()
    {
        $items = collect(Storage::disk('public')->files('site-media'))->filter(function ($path) {
            return str_starts_with((string) Storage::disk('public')->mimeType($path), 'image/');
        })->map(fn ($path) => [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'size' => Storage::disk('public')->size($path),
            'updated' => Storage::disk('public')->lastModified($path),
        ])->sortByDesc('updated')->values();
        return view('admin.media', ['items' => $items]);
    }

    public function mediaStore(Request $request)
    {
        $data = $request->validate(['image' => 'required|image|mimes:jpg,jpeg,png,webp|max:8192']);
        $file = $data['image'];
        \App\Support\ImageOptimizer::storeUploaded($file, 'public', 'site-media/' . now()->format('Y/m'), 'image');
        return redirect()->route('admin.media')->with('ok', 'Görsel medya kütüphanesine eklendi.');
    }

    public function pages()
    {
        return view('admin.pages', ['pages' => Page::query()->orderBy('title')->paginate(20)]);
    }

    public function pageForm(?Page $page = null)
    {
        $media = collect(Storage::disk('public')->files('site-media'))->filter(fn ($path) => str_starts_with((string) Storage::disk('public')->mimeType($path), 'image/'))->values();
        return view('admin.page', ['page' => $page ?? new Page(), 'media' => $media]);
    }

    public function pageSave(Request $request, ?Page $page = null)
    {
        $data = $request->validate([
            'title' => 'required|string|max:180',
            'slug' => [$page?->exists ? 'nullable' : 'required', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', \Illuminate\Validation\Rule::unique('pages', 'slug')->ignore($page?->id)],
            'content' => 'required|string|max:50000',
            'meta_description' => 'nullable|string|max:320',
        ]);
        $page ??= new Page();
        if (!$page->exists) $page->slug = $data['slug'];
        $page->title = $data['title'];
        $page->content = $data['content'];
        $page->meta_description = $data['meta_description'] ?? null;
        $page->save();
        return redirect()->route('admin.pages.index')->with('ok', 'Bilgi sayfası kaydedildi.');
    }

    public function postForm(?Post $post = null)
    {
        $media = collect(Storage::disk('public')->files('site-media'))->filter(fn ($path) => str_starts_with((string) Storage::disk('public')->mimeType($path), 'image/'))->values();
        return view('admin.post', ['post' => $post ?? new Post(), 'media' => $media]);
    }

    public function postSave(Request $r, ?Post $post = null)
    {
        $d = $r->validate(['title' => 'required|string|max:200', 'excerpt' => 'nullable|string|max:300', 'body' => 'required|string', 'publish' => 'nullable|boolean']);
        $post ??= new Post();
        if (!$post->exists) {
            $slug = Str::slug($d['title']); $base = $slug; $i = 2;
            while (Post::where('slug', $slug)->exists()) $slug = $base.'-'.$i++;
            $post->slug = $slug;
        }
        $post->fill(['title' => $d['title'], 'excerpt' => $d['excerpt'] ?? null, 'body' => $d['body']]);
        $post->published_at = $r->boolean('publish') ? ($post->published_at ?? now()) : null;
        $post->save();
        return redirect()->route('admin.index')->with('ok', 'Yazı kaydedildi.');
    }

    public function postDelete(Post $post) { $post->delete(); return back()->with('ok', 'Yazı silindi.'); }
}
