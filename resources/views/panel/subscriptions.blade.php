@extends('layouts.app')
@section('title', 'Tesis abonelikleri')
@section('desc', 'Tesisleriniz için ücretli abonelik planlarını ve ödeme durumlarını yönetin.')
@section('content')
<div class="wrap subscription-page">
  <header class="subscription-hero"><span>İŞLETME HESABI</span><h1>Tesis abonelikleri</h1><p>Her tesis için plan ve ödeme dönemini ayrı izleyin. Kart tahsilatı PayTR’dan doğrulanınca otomatik etkinleşir; havale yönetim tarafından dekont referansı ile doğrulanır.</p></header>
  @if(session('subscription_required'))<div class="subscription-gate-message"><b>Canlı işletme kullanımı için abonelik gerekir</b><span>{{ session('subscription_required') }}</span></div>@endif
  @if(session('subscription_limit'))<div class="subscription-gate-message"><b>Plan sınırı</b><span>{{ session('subscription_limit') }}</span></div>@endif
  @if(!$hotels->count())
    @php($accountSubscription = $accountSubscriptions->first())
    <section class="subscription-onboarding">
      @if($activeSubscription)
        <div class="subscription-onboarding-head"><span class="subscription-onboarding-icon">✓</span><div><small>ABONELİĞİNİZ ETKİN</small><h2>Canlı işletme araçları açıldı</h2><p>{{ $activeSubscription->plan?->name }} planı · {{ $activeSubscription->ends_at?->format('d.m.Y') }} tarihine kadar</p></div></div>
        @if($canAddHotel)<a class="subscription-add-hotel" href="{{ route('panel.hotel.create') }}">İlk tesisinizi ekleyin <span aria-hidden="true">→</span></a>@else<div class="subscription-alert"><b>Tesis sınırı dolu</b><span>Yeni tesis eklemek için planınızı yükseltin.</span></div>@endif
      @else
        <div class="subscription-onboarding-head"><span class="subscription-onboarding-icon">₺</span><div><small>DEMO HESABINDAN CANLIYA</small><h2>Aboneliğinizi seçin ve tesisi açın</h2><p>30 günlük ücretsiz deneme veya ücretli plan ile gerçek işletme araçlarını kullanmaya başlayın.</p></div></div>
        @if($trialAvailable && !$accountSubscription && $plans->count())
          <form class="subscription-trial-form" method="post" action="{{ route('panel.subscriptions.trial') }}">@csrf
            <div><b>30 gün ücretsiz kullanın</b><span>Deneme süresince seçtiğiniz planın tesis ve canlı işletme özellikleri açılır. Süre sonunda ücretli plana geçmeniz gerekir.</span></div>
            <label>Deneme planı<select name="plan_id" required>@foreach($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }} · {{ $plan->hotel_limit }} tesis</option>@endforeach</select></label>
            <button type="submit">Ücretsiz denemeyi başlat <span aria-hidden="true">→</span></button>
          </form>
        @elseif(!$trialAvailable)
          <p class="subscription-trial-used">Bu hesap veya bağlı tesislerinizde daha önce abonelik ya da deneme kaydı bulunuyor. Canlı özellikler için ücretli planla devam edin.</p>
        @endif
        @if($accountSubscription && in_array($accountSubscription->status,['awaiting_payment','pending_review'],true))
          <div class="subscription-alert pending"><b>Ödeme bekleniyor · {{ $accountSubscription->plan?->name }}</b><span>{{ $accountSubscription->requested_payment_method === 'card' ? 'Güvenli kart ödemesini tamamlayın. Gerçek tahsilat doğrulanınca tesis ekleme açılır.' : 'Havale/EFT ödemeniz yönetimce doğrulanınca aboneliğiniz etkinleşir.' }}</span></div>
          @if($accountSubscription->payments?->contains('status','test_paid'))<div class="subscription-alert pending"><b>Test ödemesi alındı</b><span>Bu işlem gerçek tahsilat değildir; canlı sistemi açmaz.</span></div>@endif
          @if($accountSubscription->status === 'awaiting_payment' && $accountSubscription->payment_status !== 'paid' && $cardPaymentsEnabled)
            <form class="subscription-card-pay" method="post" action="{{ route('panel.subscriptions.pay.paytr',$accountSubscription) }}">@csrf<label>Fatura adresi<input name="billing_address" maxlength="400" value="{{ old('billing_address',auth()->user()->address) }}" required placeholder="Mahalle, cadde, bina no, ilçe / il"></label><button type="submit">PayTR ile güvenli kart ödemesi · {{ number_format((float)$accountSubscription->amount,2,',','.') }} ₺</button><small>Kart bilgileriniz bu sitede saklanmaz.</small></form>
          @endif
        @elseif($plans->count())
          <form class="subscription-request subscription-account-purchase" method="post" action="{{ route('panel.subscriptions.purchase') }}">@csrf
            <label>Abonelik planı<select name="plan_id" required>@foreach($plans as $plan)<option value="{{ $plan->id }}" data-yearly="{{ $plan->yearly_price ? '1' : '0' }}">{{ $plan->name }} · {{ number_format((float)$plan->monthly_price,0,',','.') }} ₺ / ay @if($plan->yearly_price)· {{ number_format((float)$plan->yearly_price,0,',','.') }} ₺ / yıl @endif · {{ $plan->hotel_limit }} tesis</option>@endforeach</select></label>
            <label>Ödeme dönemi<select name="billing_cycle" required><option value="monthly">Aylık</option>@if($plans->contains(fn($plan)=>(float)$plan->yearly_price>0))<option value="yearly">Yıllık</option>@endif</select></label>
            <label>Ödeme yöntemi<select name="requested_payment_method" required><option value="bank_transfer">Havale / EFT</option><option value="card" @disabled(!$cardPaymentsEnabled)>Kredi / banka kartı{{ $cardPaymentsEnabled ? ' · PayTR' : ' · yapılandırılıyor' }}</option></select></label>
            <button type="submit">Ücretli aboneliği başlat</button>
          </form>
          <p class="subscription-onboarding-foot">Tesis bilgisi bu adımda istenmez. Havale yönetimce doğrulanır; kartla ödemede gerçek tahsilat sağlayıcı bildirimiyle doğrulanır.</p>
        @else
          <div class="subscription-note"><b>Abonelik planları hazırlanıyor</b><p>Planlar erişilebilir olduğunda buradan seçim yapabilirsiniz. Bu sırada demo panelini kullanmaya devam edebilirsiniz.</p></div>
        @endif
      @endif
    </section>
  @endif
  @if(!$plans->count())<section class="subscription-note"><b>Planlar hazırlanıyor</b><p>Yönetim ekibi ücretli planları tanımladıktan sonra buradan abonelik talebi oluşturabilirsiniz. Mevcut tesis durumunuz bu sayfayı açmanızla değişmez.</p></section>@endif
  @if($plans->count())<section class="subscription-payment"><b>Havale / EFT bilgisi</b><p>{!! nl2br(e(\App\Models\Setting::al('subscription_payment_instructions', 'Banka bilgileri henüz tanımlanmadı. Yönetim ekibiyle iletişime geçin.'))) !!}</p></section>@endif
  <div class="subscription-hotel-list">
  @forelse($hotels as $hotel)
    @php($last = $hotel->subscriptions->first())
    <article class="subscription-hotel-card">
      <div class="subscription-hotel-head"><div><small>TESİS</small><h2>{{ $hotel->name }}</h2></div><span class="subscription-state {{ $last?->status ?? 'none' }}">@switch($last?->status)@case('active') @if($last->starts_at?->isFuture()) Başlayacak: {{ $last->starts_at?->format('d.m.Y') }} @else Etkin @endif · {{ $last->ends_at?->format('d.m.Y') }} tarihine kadar @break @case('awaiting_payment') Ödeme bekliyor @break @case('pending_review') İncelemede @break @case('expired') Süresi dolmuş @break @case('cancelled') İptal edildi @break @default Abonelik yok @endswitch</span></div>
      @if($last)<p class="subscription-last">{{ $last->plan?->name }} · {{ $last->billing_cycle === 'yearly' ? 'Yıllık' : 'Aylık' }} · {{ number_format((float)$last->amount, 2, ',', '.') }} ₺ @if($last->payment_status === 'paid')· Ödendi @endif</p>@endif
      @if($hotel->subscription_suspended || $last?->status === 'expired')<div class="subscription-alert"><b>Abonelik süresi doldu</b><span>Tesisiniz geçici olarak yayından kaldırıldı. Ödeme doğrulandığında otomatik yeniden etkinleşir.</span></div>@elseif($last && in_array($last->status,['awaiting_payment','pending_review'],true))<div class="subscription-alert pending"><b>Ödeme bekleniyor</b><span>@if($last->requested_payment_method==='card') Kart ödemesini tamamlayın; gerçek ödeme PayTR tarafından doğrulanınca tesis hesabınız açılır. @else Havale/EFT sonrası dekont veya işlem referansı ile yönetime bildirin. @endif</span></div>@endif
      @if($last?->payments?->contains('status','test_paid'))<div class="subscription-alert pending"><b>Test ödemesi alındı</b><span>Test işlemi gerçek tahsilat değildir ve tesisi etkinleştirmez. Canlı PayTR yapılandırmasıyla ödeme yapmanız gerekir.</span></div>@endif
      @if($last && in_array($last->status,['awaiting_payment','pending_review'],true) && $last->payment_status!=='paid' && $cardPaymentsEnabled)
        <form class="subscription-card-pay" method="post" action="{{ route('panel.subscriptions.pay.paytr',$last) }}">@csrf<label>Fatura adresi<input name="billing_address" maxlength="400" value="{{ old('billing_address',$hotel->address) }}" required placeholder="Mahalle, cadde, bina no, ilçe / il"></label><button type="submit">PayTR ile güvenli kart ödemesi · {{ number_format((float)$last->amount,2,',','.') }} ₺</button><small>Kart bilgileriniz bu sitede saklanmaz.</small></form>
      @endif
      @if($plans->count())
      <form class="subscription-request" method="post" action="{{ route('panel.subscriptions.request', $hotel) }}">@csrf
        <label>Ücretli plan<select name="plan_id" required>@foreach($plans as $plan)<option value="{{ $plan->id }}" data-yearly="{{ $plan->yearly_price ? '1' : '0' }}">{{ $plan->name }} · {{ number_format((float)$plan->monthly_price,0,',','.') }} ₺ / ay @if($plan->yearly_price)· {{ number_format((float)$plan->yearly_price,0,',','.') }} ₺ / yıl @endif</option>@endforeach</select></label>
        <label>Ödeme dönemi<select name="billing_cycle" required><option value="monthly">Aylık</option>@if($plans->contains(fn($plan) => (float)$plan->yearly_price > 0))<option value="yearly">Yıllık</option>@endif</select></label>
        <label>Ödeme yöntemi<select name="requested_payment_method" required><option value="bank_transfer">Havale / EFT</option><option value="card" @disabled(!$cardPaymentsEnabled)>Kredi / banka kartı{{ $cardPaymentsEnabled ? ' · PayTR' : ' · yapılandırılıyor' }}</option></select></label>
        <button type="submit" @disabled($last && in_array($last->status,['awaiting_payment','pending_review'],true))>Abonelik talebi oluştur</button>
      </form>
      @endif
    </article>
  @empty @endforelse
  </div>
</div>
<script>
document.querySelectorAll('.subscription-request').forEach(function(form){var plan=form.querySelector('[name="plan_id"]'),cycle=form.querySelector('[name="billing_cycle"]');function sync(){var annual=plan.selectedOptions[0]?.dataset.yearly==='1',year=cycle.querySelector('[value="yearly"]');if(year){year.disabled=!annual;if(!annual&&cycle.value==='yearly')cycle.value='monthly'}}plan.addEventListener('change',sync);sync()});
</script>
<style>
.subscription-page{padding:38px 0 72px;max-width:1100px}.subscription-hero{padding:34px 38px;border:1px solid #efe6dc;border-radius:26px;background:linear-gradient(115deg,#fff5eb,#f4f8f1);margin-bottom:26px}.subscription-hero>span,.subscription-hotel-head small{font-size:.76rem;letter-spacing:.12em;font-weight:800;color:#e34a19}.subscription-hero h1{font-size:clamp(2rem,4vw,3rem);margin:8px 0;color:#242b26}.subscription-hero p,.subscription-note p{max-width:760px;color:#626c65;line-height:1.7;margin:0}.subscription-note,.subscription-hotel-card{border:1px solid #e7e9e3;background:white;border-radius:20px;padding:24px;box-shadow:0 10px 30px #2630270a}.subscription-note b{font-size:1.1rem}.subscription-note a{display:inline-block;margin-top:12px}.subscription-hotel-list{display:grid;gap:18px}.subscription-hotel-head{display:flex;align-items:center;justify-content:space-between;gap:16px}.subscription-hotel-head h2{font-size:1.35rem;margin:5px 0}.subscription-state{border-radius:999px;padding:8px 13px;font-size:.85rem;font-weight:700;background:#f2f4f1;color:#47514a}.subscription-state.active{background:#e8f6ed;color:#177341}.subscription-state.awaiting_payment,.subscription-state.pending_review{background:#fff3df;color:#9b5900}.subscription-last{color:#59635c;margin:7px 0 18px}.subscription-request{display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:12px;align-items:end;border-top:1px solid #edf0eb;padding-top:18px}.subscription-request label,.subscription-card-pay label{display:grid;gap:6px;font-weight:650;color:#3e4741}.subscription-request select,.subscription-card-pay input{min-height:46px;border:1px solid #dfe5df;border-radius:11px;background:#fff;padding:0 12px;font:inherit}.subscription-request button,.subscription-card-pay button{min-height:46px;padding:0 20px;border:0;border-radius:11px;background:#f04412;color:#fff;font-weight:750;cursor:pointer}.subscription-request button:disabled{opacity:.5;cursor:not-allowed}.subscription-alert{display:grid;gap:3px;padding:13px 15px;border-radius:12px;background:#fff0eb;color:#8c341d;margin:12px 0 16px;font-size:.86rem}.subscription-alert.pending{background:#fff7e9;color:#855818}.subscription-card-pay{display:grid;grid-template-columns:1fr auto;gap:10px;align-items:end;padding:15px;margin:12px 0 18px;border:1px solid #e7e9e3;border-radius:15px;background:#fafbf9}.subscription-card-pay small{grid-column:1/-1;color:#7b857d;font-size:.75rem}.subscription-payment{border:1px solid #dfe9dc;background:#f4f8f1;border-radius:16px;padding:17px 20px;margin-bottom:18px}.subscription-payment p{margin:6px 0 0;line-height:1.65;color:#4b584e}@media(max-width:700px){.subscription-page{padding:22px 0 46px}.subscription-hero{padding:25px 22px;border-radius:19px}.subscription-hotel-head{align-items:flex-start;flex-direction:column}.subscription-request,.subscription-card-pay{grid-template-columns:1fr}.subscription-request button,.subscription-card-pay button{width:100%}}
.subscription-gate-message{display:grid;gap:4px;margin:-9px 0 18px;padding:15px 17px;border:1px solid #f0d5ac;border-radius:14px;background:#fff9ec;color:#76511a;font-size:12px;line-height:1.55}.subscription-gate-message b{font-size:13px}.subscription-onboarding{margin-bottom:22px;padding:24px;border:1px solid #e9e9e2;border-radius:21px;background:#fff;box-shadow:0 10px 30px #2630270a}.subscription-onboarding-head{display:flex;align-items:center;gap:13px;margin-bottom:18px}.subscription-onboarding-icon{display:grid;place-items:center;flex:none;width:44px;height:44px;border-radius:14px;background:#edf6ef;color:#21835c;font-size:22px;font-weight:900}.subscription-onboarding-head small{color:#a65931;font-size:9px;font-weight:900;letter-spacing:.12em}.subscription-onboarding-head h2{margin:3px 0;color:#29312a;font-size:21px;letter-spacing:-.03em}.subscription-onboarding-head p{margin:0;color:#737d75;font-size:11px;line-height:1.55}.subscription-account-purchase{grid-template-columns:1.4fr 1fr 1.2fr auto;border-top:1px solid #edf0eb;padding-top:17px}.subscription-onboarding-foot{margin:12px 0 0;color:#788178;font-size:10px;line-height:1.6}.subscription-add-hotel{display:inline-flex;align-items:center;gap:9px;min-height:44px;padding:0 17px;border-radius:11px;background:#ed4a1b;color:white;font-size:12px;font-weight:850;text-decoration:none}.subscription-page .subscription-state.none{background:#f3f2ed}.subscription-page .subscription-request select:focus,.subscription-page .subscription-card-pay input:focus{outline:3px solid #fff0e8;border-color:#ed7049}
@media(max-width:760px){.subscription-page{width:calc(100% - 28px)}.subscription-onboarding{padding:18px}.subscription-account-purchase{grid-template-columns:1fr}.subscription-onboarding-head{align-items:flex-start}.subscription-onboarding-head h2{font-size:18px}}
</style>
@endsection
