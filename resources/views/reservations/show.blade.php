@extends('layouts.app')
@section('title', 'Rezervasyon '.$res->code.' | Pet Otel')
@section('content')
<div class="wrap page guest-reservation-detail">
<div class="crumb guest-reservation-crumb"><a href="{{ route('reservations.index') }}"><span aria-hidden="true">←</span> Rezervasyonlarım</a><span aria-hidden="true">›</span><span>{{ $res->code }}</span></div>
<header class="guest-reservation-hero">
  <div class="guest-reservation-hero-main">
    <span class="guest-reservation-kicker"><span aria-hidden="true">▣</span> Rezervasyon ayrıntısı</span>
    <h1>{{ $res->hotel->name }}</h1>
    <div class="guest-reservation-badges"><span class="st {{ $res->status }}">{{ $res->status_label }}</span><span class="guest-reservation-pay"><span aria-hidden="true">{{ $res->payment_status === 'paid' ? '✓' : '₺' }}</span> {{ $res->payment_status === 'paid' ? 'Ödeme alındı' : 'Ödeme tesiste yapılır' }}</span>@if($res->extensions->contains(fn($e) => $e->financialEntry && $e->financialEntry->status === 'pending'))<span class="guest-reservation-pay is-pending"><span aria-hidden="true">◷</span> Uzatma ücreti bekliyor</span>@endif</div>
  </div>
  <div class="guest-reservation-code"><small>Rezervasyon kodu</small><strong>{{ $res->code }}</strong></div>
</header>
@php
if ($res->status === 'pending') {
    $guestNextStep = ['⌛', 'Otel yanıtı bekleniyor', 'Talebiniz '.($res->created_at?->diffForHumans() ?? 'şimdi').' iletildi. İşletme yanıt verdiğinde durum burada güncellenir.'];
} elseif ($res->status === 'confirmed' && $res->check_in->gt(today())) {
    $guestNextStep = ['📅', 'Rezervasyonunuz onaylandı', 'Giriş tarihiniz '.$res->check_in->format('d.m.Y').'. Rezervasyon ayrıntılarınız ve otelle güvenli mesajlaşma aşağıda.'];
} elseif ($res->status === 'confirmed' && $res->check_out->gt(today())) {
    $guestNextStep = ['🐾', 'Konaklamanız devam ediyor', 'Günlük bakım güncellemelerini ve işletmeden gelen mesajları bu sayfadan takip edebilirsiniz.'];
} elseif ($res->status === 'confirmed') {
    $guestNextStep = ['◷', 'Çıkış işlemi bekleniyor', 'Planlanan çıkış tarihi geçti. İşletme çıkış işlemini tamamladığında rezervasyon durumunuz güncellenecek.'];
} elseif ($res->status === 'completed') {
    $guestNextStep = ['✓', 'Konaklama tamamlandı', 'Rezervasyon ve ödeme belgenizi aşağıdaki bölümlerden inceleyebilirsiniz.'];
} elseif ($res->status === 'rejected') {
    $guestNextStep = ['!', 'Rezervasyon talebi kabul edilmedi', 'Başka bir tesis seçerek yeni bir rezervasyon talebi oluşturabilirsiniz.'];
} else {
    $guestNextStep = ['↗', 'Rezervasyon iptal edildi', 'Yeni bir konaklama planlamak için uygun tesisleri inceleyebilirsiniz.'];
}
@endphp
<aside class="guest-reservation-next-step" role="status"><span aria-hidden="true">{{ $guestNextStep[0] }}</span><div><strong>{{ $guestNextStep[1] }}</strong><p>{{ $guestNextStep[2] }}</p>@if($res->status === 'pending' && $res->created_at)<small class="guest-reservation-wait" data-pending-wait data-age-seconds="{{ max(0, $res->created_at->diffInSeconds(now())) }}">Yanıt bekleme süresi: {{ $res->created_at->diffForHumans() }}</small><small class="guest-reservation-reminder">4 saati aşan yanıtsız taleplerde, en geç 15 dakika içinde işletmeye hatırlatma gönderilir.</small>@endif</div></aside>
<style>
.guest-reservation-next-step{display:grid;grid-template-columns:40px minmax(0,1fr);align-items:start;gap:12px;margin:14px 0;padding:15px 17px;border:1px solid #e4eae3;border-radius:16px;background:linear-gradient(115deg,#f3f8f3,#fffaf6);color:#34463b}.guest-reservation-next-step>span{display:grid;place-items:center;width:38px;height:38px;border-radius:12px;background:#e7f1e8;color:#33734d;font-size:18px;font-weight:800}.guest-reservation-next-step strong{display:block;color:#33443a;font-size:14px}.guest-reservation-next-step p{margin:4px 0 0;color:#718078;font-size:12px;line-height:1.6}
.guest-reservation-detail{max-width:1080px;padding-top:22px;padding-bottom:56px;color:#29352d}
.guest-reservation-crumb{display:flex;align-items:center;gap:9px;margin:0 0 16px;color:#88948b;font-size:12px}.guest-reservation-crumb a{display:inline-flex;align-items:center;gap:7px;color:#68766d;text-decoration:none;font-weight:700}.guest-reservation-crumb a:hover{color:#ef4a0b}
.guest-reservation-hero{display:flex;align-items:center;justify-content:space-between;gap:20px;margin:0 0 14px;padding:clamp(20px,3vw,30px);border:1px solid #eee3d6;border-radius:22px;background:radial-gradient(circle at 95% 5%,#eaf3e9 0,transparent 32%),linear-gradient(115deg,#fff1e6 0%,#fffdfa 55%,#f1f6ef 100%);box-shadow:0 10px 30px rgba(39,53,44,.06)}
.guest-reservation-kicker{display:flex;align-items:center;gap:7px;margin-bottom:8px;color:#b74c27;font-size:10px;font-weight:900;letter-spacing:.13em;text-transform:uppercase}.guest-reservation-kicker span{display:grid;place-items:center;width:25px;height:25px;border-radius:8px;background:#ffebe0;color:#f04b0d;font-size:14px}
.guest-reservation-hero h1{margin:0;color:#29362e;font-size:clamp(24px,4vw,36px);line-height:1.15;letter-spacing:-.035em}.guest-reservation-badges{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-top:15px}.guest-reservation-badges .st{display:inline-flex;align-items:center;min-height:31px;padding:6px 11px;border-radius:999px;font-size:11px;font-weight:800}.guest-reservation-pay{display:inline-flex;align-items:center;gap:6px;min-height:31px;padding:6px 11px;border:1px solid #e8e9df;border-radius:999px;background:#ffffffc9;color:#526157;font-size:11px;font-weight:750}.guest-reservation-pay>span{color:#268154;font-weight:900}.guest-reservation-pay.is-pending>span{color:#bb6c22}
.guest-reservation-code{flex:none;min-width:150px;padding:12px 15px;border:1px solid #e9e0d6;border-radius:14px;background:rgba(255,255,255,.72)}.guest-reservation-code small,.guest-reservation-code strong{display:block}.guest-reservation-code small{margin-bottom:5px;color:#859087;font-size:10px}.guest-reservation-code strong{color:#38463d;font-size:15px;letter-spacing:.06em}
.guest-reservation-summary{margin:0 0 16px;padding:20px;border:1px solid #e6eae3;border-radius:20px;background:#fff;box-shadow:0 9px 28px rgba(39,53,44,.055)}.guest-reservation-section-heading{display:flex;align-items:center;gap:11px;margin:0 0 15px}.guest-reservation-section-heading>span{display:grid;place-items:center;width:40px;height:40px;border-radius:13px;background:#fff0e8;color:#e94b12;font-size:18px}.guest-reservation-section-heading h2{margin:0;color:#2c3a31;font-size:18px;font-weight:850;letter-spacing:-.025em}.guest-reservation-section-heading p{margin:3px 0 0;color:#7b877f;font-size:11px}
.guest-reservation-facts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.guest-reservation-fact{display:flex;align-items:flex-start;gap:11px;min-width:0;padding:13px;border:1px solid #edf0ea;border-radius:14px;background:#fbfcfa}.guest-reservation-fact-icon{display:grid;place-items:center;flex:none;width:35px;height:35px;border-radius:11px;background:#eef4ed;color:#348052;font-size:16px}.guest-reservation-fact small,.guest-reservation-fact strong,.guest-reservation-fact .guest-reservation-muted{display:block}.guest-reservation-fact small,.guest-reservation-inline-note small,.guest-reservation-notes small{margin-bottom:4px;color:#89938b;font-size:10px;font-weight:650}.guest-reservation-fact strong{color:#344239;font-size:13px;line-height:1.45;overflow-wrap:anywhere}.guest-reservation-fact strong a{color:inherit;text-decoration:none}.guest-reservation-fact strong a:hover{color:#e94b12}.guest-reservation-fact .guest-reservation-muted{margin-top:3px;color:#77847b;font-size:11px;font-weight:500}.guest-reservation-fact-total{border-color:#f2ded2;background:linear-gradient(110deg,#fff8f3,#fff)}.guest-reservation-fact-total .guest-reservation-fact-icon{background:#ffede2;color:#e94b12}.guest-reservation-fact-total strong{font-size:17px;color:#303c33}
.guest-reservation-inline-note{display:flex;gap:10px;margin-top:11px;padding:12px 14px;border-radius:13px;background:#f4f8f3;color:#435148}.guest-reservation-inline-note>span{color:#d86730}.guest-reservation-inline-note small,.guest-reservation-inline-note strong{display:block}.guest-reservation-inline-note strong{font-size:12px;line-height:1.6}.guest-reservation-notes{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:11px}.guest-reservation-notes p{display:flex;gap:9px;margin:0;padding:11px;border:1px solid #edf0ea;border-radius:12px;color:#56645a;font-size:12px;line-height:1.5;overflow-wrap:anywhere}.guest-reservation-notes p>span:first-child{flex:none}.guest-reservation-notes small{display:block}
.guest-reservation-detail>section.cardbox{margin-top:14px!important;padding:20px;border:1px solid #e7ebe4;border-radius:18px;background:#fff;box-shadow:0 8px 24px rgba(39,53,44,.045)}.guest-reservation-detail>section.cardbox h2,.guest-reservation-detail>.cardbox h2{color:#2d3a31;font-size:18px;letter-spacing:-.02em}.guest-reservation-detail>section.cardbox h2:before{content:"✦";display:inline-grid;place-items:center;width:30px;height:30px;margin-right:9px;border-radius:10px;background:#fff0e8;color:#e94b12;font-size:13px;vertical-align:middle}
@media(max-width:600px){.guest-reservation-detail{padding-top:14px;padding-bottom:38px}.guest-reservation-crumb{margin-bottom:11px;font-size:11px}.guest-reservation-hero{display:block;padding:18px 16px;border-radius:18px}.guest-reservation-hero h1{font-size:25px}.guest-reservation-badges{gap:6px;margin-top:12px}.guest-reservation-code{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:13px;padding:9px 11px}.guest-reservation-code small{margin:0}.guest-reservation-code strong{font-size:13px}.guest-reservation-summary{padding:15px 13px;border-radius:17px}.guest-reservation-facts{grid-template-columns:1fr;gap:8px}.guest-reservation-fact{padding:10px}.guest-reservation-fact-icon{width:32px;height:32px}.guest-reservation-fact-dates{grid-column:auto}.guest-reservation-notes{grid-template-columns:1fr}.guest-reservation-detail>section.cardbox{padding:15px 13px;border-radius:16px}.guest-reservation-next-step{gap:10px;padding:13px;border-radius:14px}.guest-reservation-next-step strong{font-size:13px}.guest-reservation-next-step p{font-size:11px}}
</style><script>(function(){document.querySelectorAll('[data-pending-wait]').forEach(function(el){var age=Number(el.dataset.ageSeconds||0),start=Date.now()-age*1000;function draw(){var seconds=Math.max(0,Math.floor((Date.now()-start)/1000)),days=Math.floor(seconds/86400),hours=Math.floor((seconds%86400)/3600),minutes=Math.floor((seconds%3600)/60);var value=days?days+' gün '+hours+' sa '+minutes+' dk':hours?hours+' sa '+minutes+' dk':minutes+' dk';el.textContent='Yanıt bekleme süresi: '+value;}draw();window.setInterval(draw,60000);});})();</script>@if(\App\Support\PlatformModules::enabled('live_camera', (int) $res->hotel_id) && $res->status === 'confirmed' && $res->check_in->lte(today()) && $res->check_out->gt(today()) && $res->hotel->camera_enabled && $res->hotel->camera_embed_url)
<div class="cardbox"><b>Ek hizmetler</b><p class="hint">Rezervasyonunuza yeni evcil hayvan ekleyin veya geç çıkış talep edin.</p><a class="btn" href="{{ route('reservations.extras', $res) }}">Ek hizmetleri yönet</a></div>
<div class="cardbox camera-access"><b>🔒 Size özel canlı kamera</b><p class="hint">Yayın yalnızca hesabınıza giriş yaptıktan ve tesisin size ilettiği rezervasyona özel erişim kodunu girdikten sonra açılır.</p><a class="btn" href="{{ route('reservations.camera',$res) }}">Güvenli yayını aç</a></div>
@endif
@if(\App\Support\PlatformModules::enabled('private_calls', (int) $res->hotel_id))
  @if($res->status === 'confirmed' && $res->check_in->lte(today()) && $res->check_out->gte(today()))
  <div id="apo-private-call">
  @include('reservations.partials.private-call',['activeUrl'=>route('reservations.calls.active',$res),'startUrl'=>route('reservations.calls.start',$res),'pollUrl'=>route('reservations.calls.poll',['call'=>'CALL_UUID']),'signalUrl'=>route('reservations.calls.signal',['call'=>'CALL_UUID']),'recordingUrl'=>route('reservations.calls.recording.chunk',['call'=>'CALL_UUID']),'finishUrl'=>route('reservations.calls.recording.finish',['call'=>'CALL_UUID']),'retentionDays'=>(int)\App\Models\Setting::al('call_recording_days','30')])
  </div>
  @elseif(in_array($res->status,['pending','confirmed'],true))
  <section class="cardbox" id="apo-private-call"><h2>🔒 Özel sesli ve görüntülü görüşme</h2>
    @if($res->status === 'pending')
      <p>Tesis rezervasyonu onayladıktan ve konaklama başlangıç tarihinde görüşme burada açılır.</p>
    @elseif($res->check_in->gt(today()))
      <p>Rezervasyonunuz onaylandı. Görüşme {{ $res->check_in->format('d.m.Y') }} tarihinde konaklama başladığında açılacak.</p>
    @else
      <p>Görüşme, yalnızca onaylı ve devam eden konaklama süresince kullanılabilir.</p>
    @endif
  </section>
  @endif
@endif
@php($savedCallRecordings=$res->calls->filter(fn($call)=>$call->recording && $call->recording->status==='ready' && $call->recording->expires_at->isFuture()))
@if($savedCallRecordings->isNotEmpty())<section class="cardbox"><h2>Güvenli görüşme kayıtları</h2><p class="hint">Kayıtlar yalnızca bu rezervasyonun iki tarafınca erişilebilir; {{ (int)\App\Models\Setting::al('call_recording_days','30') }} günlük saklama süresi dolunca silinir.</p>@foreach($savedCallRecordings as $savedCall)<p>{{ $savedCall->created_at->format('d.m.Y H:i') }} · {{ $savedCall->media_type==='video'?'Görüntülü':'Sesli' }} görüşme · <a href="{{ route('reservations.calls.recording.download',['call'=>$savedCall->uuid]) }}">Şifreli arşivden indir</a> · Son gün: {{ $savedCall->recording->expires_at->format('d.m.Y') }}</p>@endforeach</section>@endif
@if(in_array($res->status, ['pending', 'confirmed'], true))
<section class="cardbox waiver-card"><h2>Dijital onaylar</h2><p class="hint">Acil veteriner müdahalesi ve KVKK onaylarını rezervasyonunuza güvenli biçimde ekleyebilirsiniz.</p>
@foreach(['veterinary_intervention'=>'Acil veteriner müdahalesi','aggressive_pet'=>'Özel davranış / güvenlik beyanı','kvkk'=>'KVKK veri işleme onayı'] as $type => $label)
@if(!$res->waivers->contains('waiver_type', $type))
<form method="post" action="{{ route('reservations.waiver.store', $res) }}" class="form waiver-form" data-waiver-form>@csrf<input type="hidden" name="waiver_type" value="{{ $type }}"><input type="hidden" name="signature_data" data-signature-data><label class="chk"><input type="checkbox" name="accepted" value="1" required><span>{{ $label }} metnini okudum ve kabul ediyorum.</span></label>@if($type === 'veterinary_intervention')<label>Acil müdahale bütçe limiti (TL)<input type="number" name="emergency_budget" min="0" max="1000000" step="0.01" placeholder="İsteğe bağlı"></label>@endif<div><canvas data-signature width="520" height="140" style="width:100%;max-width:520px;height:140px;border:1px solid #dfe6df;border-radius:12px;background:#fff;touch-action:none"></canvas><button type="button" class="btn ghost" data-clear-signature>İmzayı temizle</button></div><button class="btn" data-submit-signature disabled>{{ $label }} imzasını kaydet</button></form>
@endif
@endforeach
</section>
<script>(function(){document.querySelectorAll('[data-waiver-form]').forEach(function(form){var c=form.querySelector('[data-signature]'),ctx=c.getContext('2d'),hidden=form.querySelector('[data-signature-data]'),submit=form.querySelector('[data-submit-signature]'),drawing=false;ctx.strokeStyle='#263b30';ctx.lineWidth=2;function pos(e){var r=c.getBoundingClientRect(),p=e.touches?e.touches[0]:e;return{x:(p.clientX-r.left)*c.width/r.width,y:(p.clientY-r.top)*c.height/r.height}}function start(e){drawing=true;ctx.beginPath();var p=pos(e);ctx.moveTo(p.x,p.y);e.preventDefault()}function move(e){if(!drawing)return;var p=pos(e);ctx.lineTo(p.x,p.y);ctx.stroke();submit.disabled=false;e.preventDefault()}function end(){drawing=false;hidden.value=c.toDataURL('image/png')}c.addEventListener('mousedown',start);c.addEventListener('mousemove',move);c.addEventListener('mouseup',end);c.addEventListener('mouseleave',end);c.addEventListener('touchstart',start,{passive:false});c.addEventListener('touchmove',move,{passive:false});c.addEventListener('touchend',end);form.querySelector('[data-clear-signature]').addEventListener('click',function(){ctx.clearRect(0,0,c.width,c.height);hidden.value='';submit.disabled=true})})})();</script>
@endif
<section class="guest-reservation-summary" aria-labelledby="guest-reservation-summary-title">
  <div class="guest-reservation-section-heading"><span aria-hidden="true">▦</span><div><h2 id="guest-reservation-summary-title">Konaklama özeti</h2><p>Rezervasyonunuzun temel bilgileri</p></div></div>
  <div class="guest-reservation-facts">
    <article class="guest-reservation-fact"><span class="guest-reservation-fact-icon" aria-hidden="true">▣</span><div><small>Oda tipi</small><strong>{{ $res->room->name }}</strong></div></article>
    <article class="guest-reservation-fact"><span class="guest-reservation-fact-icon" aria-hidden="true">🐾</span><div><small>Konaklayacak dost</small><strong>{{ $res->pet->name }} <span class="guest-reservation-muted">· {{ $res->pet->species_label }}</span></strong></div></article>
    <article class="guest-reservation-fact guest-reservation-fact-dates"><span class="guest-reservation-fact-icon" aria-hidden="true">📅</span><div><small>Giriş – çıkış</small><strong>{{ $res->check_in->format('d.m.Y') }} <span aria-hidden="true">→</span> {{ $res->check_out->format('d.m.Y') }}</strong><span class="guest-reservation-muted">{{ $res->nights }} gece</span>@if($res->early_departure_status === 'approved') <span class="st confirmed">Erken çıkış</span> @endif</div></article>
    <article class="guest-reservation-fact guest-reservation-fact-total"><span class="guest-reservation-fact-icon" aria-hidden="true">₺</span><div><small>Rezervasyon toplamı</small><strong>{{ number_format($res->total_price,2,',','.') }} ₺</strong>@if((float) $res->discount_total > 0)<span class="guest-reservation-muted">{{ number_format($res->discount_total,0,',','.') }} ₺ indirim uygulandı</span>@endif</div></article>
    @if($res->hotel->phone)<article class="guest-reservation-fact"><span class="guest-reservation-fact-icon" aria-hidden="true">☎</span><div><small>Tesis telefonu</small><strong><a href="tel:{{ preg_replace('/[^0-9+]/', '', $res->hotel->phone) }}">{{ $res->hotel->phone }}</a></strong></div></article>@endif
  </div>
  @if($res->extras->isNotEmpty())<div class="guest-reservation-inline-note"><span aria-hidden="true">✦</span><div><small>Ek hizmetler</small><strong>@foreach($res->extras as $ex){{ $ex->name }}@if($ex->quantity > 1) × {{ $ex->quantity }}@endif ({{ number_format($ex->total,0,',','.') }} ₺)@if(!$loop->last) · @endif @endforeach</strong></div></div>@endif
  @if($res->customer_note || $res->hotel_note)<div class="guest-reservation-notes">@if($res->customer_note)<p><span aria-hidden="true">💬</span><span><small>Notunuz</small>{{ $res->customer_note }}</span></p>@endif @if($res->hotel_note)<p><span aria-hidden="true">🏨</span><span><small>Tesis notu</small>{{ $res->hotel_note }}</span></p>@endif</div>@endif
</section>
@if($res->payment_status === 'paid' && in_array($res->status, ['confirmed','completed'], true))
<section class="cardbox invoice-customer-card">
  <div class="invoice-card-heading"><span aria-hidden="true">▤</span><div><h2>Fatura ve ödeme belgesi</h2><p>Ödemeniz alınan rezervasyonun resmî e‑Fatura/e‑Arşiv belgesi.</p></div></div>
  @if($res->invoice && $res->invoice->isIssued())
    <div class="invoice-issued"><div><b>{{ $res->invoice->invoice_type === 'e_fatura' ? 'e‑Fatura' : 'e‑Arşiv fatura' }} · {{ $res->invoice->invoice_number }}</b><small>Düzenlenme tarihi: {{ $res->invoice->issued_at?->format('d.m.Y') }}</small></div><a class="btn" href="{{ route('invoices.download',$res->invoice) }}">Faturayı indir</a></div>
  @else
    <p class="invoice-pending-note">Tesis resmî fatura belgesini henüz yüklemedi. Belge hazır olduğunda bu sayfada ve <a href="{{ route('invoices.index') }}"><u>Faturalarım</u></a> bölümünde görüntülenip indirilebilir.</p>
  @endif
</section>
@endif
@if($res->status === 'confirmed' && $res->check_out->gt(today()))
<section class="cardbox" style="margin-top:16px">
  <h2>Ödeme tesiste yapılır</h2>
  <p>Rezervasyonunuzu çevrimiçi yönetin. Konaklama ücretini doğrudan tesiste ödersiniz; bu sitede kart bilgisi istenmez ve ödeme alınmaz.</p>
</section>
@endif

@if(($res->transfer_status ?? 'not_requested') !== 'not_requested')
<div class="cardbox transfer-panel"><h2>Pet transfer</h2>
<p><b>Durum:</b> {{ ['requested'=>'İşletme onayı bekliyor','in_progress'=>'Transfer sürüyor','completed'=>'Tamamlandı','cancelled'=>'İptal edildi'][$res->transfer_status] ?? $res->transfer_status }}</p>
<p><b>Alınacağı adres:</b> {{ $res->transfer_pickup_address }}</p>@if($res->transfer_return_address)<p><b>Dönüş adresi:</b> {{ $res->transfer_return_address }}</p>@endif
<p><b>Ücret:</b> {{ number_format((float)$res->transfer_total,2,',','.') }} ₺</p>@if($res->petTransfer)<p><b>Alınış zamanı:</b> {{ $res->petTransfer->pickup_at->format('d.m.Y H:i') }}</p><p><b>Araç:</b> {{ $res->petTransfer->vehicle_type }}</p><p><b>Varış adresi:</b> {{ $res->petTransfer->dropoff_address }}</p>@endif
@if($res->transfer_status==='in_progress')
<p id="transfer-location-text" class="hint">İşletme canlı konum paylaşımını başlatınca burada görebilirsiniz.</p><a id="transfer-map-link" class="btn ghost" target="_blank" rel="noopener" hidden>Aracı haritada izle</a>
<script>(function(){var label=document.getElementById('transfer-location-text'),link=document.getElementById('transfer-map-link'),url=@json(route('reservations.transfer.location',$res));function poll(){fetch(url,{headers:{'Accept':'application/json'},cache:'no-store'}).then(function(r){return r.json()}).then(function(d){if(d.active){label.textContent='Canlı konum · son güncelleme '+d.updated_at;link.href='https://www.openstreetmap.org/?mlat='+d.lat+'&mlon='+d.lng+'#map=16/'+d.lat+'/'+d.lng;link.hidden=false}else{label.textContent='Konum paylaşımı şu anda kapalı veya güncel değil.';link.hidden=true}}).catch(function(){label.textContent='Konum bilgisi alınamadı.'})}poll();setInterval(poll,10000)})();</script>
@endif
</div>
@endif
@if($res->reservationTransfer || $res->grooming_option_id || $res->has_insurance || $res->points_redeemed)
<div class="cardbox"><h2>Rezervasyon ek hizmetleri</h2>
@if($res->reservationTransfer)<p><b>Pet Taxi:</b> {{ ['one_way_pickup'=>'Adresten tesise','one_way_dropoff'=>'Tesisten adrese','round_trip'=>'Gidiş dönüş'][$res->reservationTransfer->transfer_type]??$res->reservationTransfer->transfer_type }} · {{ $res->reservationTransfer->status }}</p>
<p><b>Rota:</b> {{ $res->reservationTransfer->pickup_address }} → {{ $res->reservationTransfer->dropoff_address }} · {{ number_format((float)$res->reservationTransfer->transfer_fee,2,',','.') }} ₺</p>
@if($res->reservationTransfer->distance_km)<p><b>Yol mesafesi:</b> {{ number_format((float)$res->reservationTransfer->distance_km,2,',','.') }} km</p>@endif
@if($res->reservationTransfer->vehicle_type)<p><b>Araç:</b> {{ ['sedan'=>'Standart araç','minivan'=>'Minivan','van'=>'Geniş araç'][$res->reservationTransfer->vehicle_type]??$res->reservationTransfer->vehicle_type }}</p>@endif
@if($res->reservationTransfer->pickup_datetime)<p><b>Alınma:</b> {{ $res->reservationTransfer->pickup_datetime->format('d.m.Y H:i') }}</p>@endif
@if($res->reservationTransfer->dropoff_datetime)<p><b>Dönüş / bırakılma:</b> {{ $res->reservationTransfer->dropoff_datetime->format('d.m.Y H:i') }}</p>@endif
@endif
@if($res->groomingOption)<p><b>Kuaför:</b> {{ $res->groomingOption->title }} · {{ number_format((float)$res->grooming_fee,2,',','.') }} ₺</p>@endif
@if($res->has_insurance)<p><b>Sigorta eki:</b> {{ number_format((float)$res->insurance_fee,2,',','.') }} ₺</p>@endif
@if($res->points_redeemed)<p><b>PotiPuan:</b> {{ $res->points_redeemed }} puan kullanıldı (−{{ number_format((float)$res->points_discount,2,',','.') }} ₺)</p>@endif
</div>
@endif
@if($res->early_departure_status === 'requested')
<div class="cardbox"><b>Erken çıkış talebi işletme yanıtını bekliyor</b><p>Talep edilen tarih: {{ \Illuminate\Support\Carbon::parse($res->early_departure_date)->format('d.m.Y') }}</p><p class="hint">İşletme onaylayana kadar mevcut rezervasyon tarihiniz geçerlidir.</p></div>
@elseif($res->early_departure_status === 'approved')
<div class="cardbox"><b class="st confirmed">ERKEN ÇIKIŞ ONAYLANDI</b><p>Çıkış tarihiniz {{ $res->check_out->format('d.m.Y') }} olarak güncellendi.</p>@if((float)$res->early_departure_refund_amount > 0)<p class="hint">Ödenmiş tutar için {{ number_format((float)$res->early_departure_refund_amount,2,',','.') }} ₺ iade/mahsup işletmeyle ayrıca tamamlanmalıdır.</p>@endif</div>
@elseif($res->early_departure_status === 'rejected')
<div class="cardbox"><b>Erken çıkış talebi onaylanmadı</b><p class="hint">Mevcut rezervasyon tarihiniz değişmedi. İşletmeyle mesajlaşarak görüşebilirsiniz.</p></div>
@endif
@if($res->status === 'confirmed' && $res->check_out->gt(today()) && $res->early_departure_status !== 'approved')
<form class="form cardbox" method="post" action="{{ route('reservations.early-departure',$res) }}">@csrf<h2>Erken çıkış talebi</h2><p class="hint">Talebiniz işletmeye iletilir. Tarih ancak işletme onayladığında değişir.</p><label>Talep edilen yeni çıkış tarihi</label><input type="date" name="early_departure_date" min="{{ max(today()->toDateString(), $res->check_in->copy()->addDay()->toDateString()) }}" max="{{ $res->check_out->copy()->subDay()->toDateString() }}" required><label>İşletmeye not (isteğe bağlı)</label><textarea name="early_departure_note" maxlength="1000"></textarea><button class="btn">Talebi işletmeye gönder</button></form>
@endif
@if($res->canBeCancelled())
<form method="post" action="{{ route('reservations.cancel', $res) }}" onsubmit="return confirm('Rezervasyon iptal edilsin mi?')">@csrf<button class="btn danger">Rezervasyonu iptal et</button></form>
<p class="hint">İptal koşulları için <a href="{{ route('pages.show','iptal-iade') }}"><u>İptal ve İade</u></a> sayfasına bakın.</p>
@endif
@if($res->canBeReviewed())
<form class="form" method="post" action="{{ route('reviews.store', $res) }}">@csrf<h3>Konaklamayı değerlendir</h3>
<label>Puan</label><select name="rating">@for($i=5;$i>=1;$i--)<option value="{{ $i }}">{{ str_repeat('★',$i) }}</option>@endfor</select>
<label>Yorum</label><textarea name="comment"></textarea><button class="btn">Yorumu gönder</button></form>
@elseif($res->review)<div class="cardbox"><b class="stars">{{ str_repeat('★',$res->review->rating) }}</b><p>{{ $res->review->comment }}</p></div>@endif
@include('reservations._tutanak', ['res' => $res, 'musteri' => true])
<section class="cardbox" style="margin-top:16px">
<h2>Konaklama güncellemeleri</h2>
@if($res->updates->isEmpty())
  <p class="hint">İşletme konaklama sırasında bilgi veya fotoğraf paylaştığında burada görünür.</p>
@else
  @foreach($res->updates->reverse() as $update)
    <article style="padding:12px 0;border-top:1px solid #e7e7e7">
      <strong>{{ $update->headline }}</strong>@if($update->mood)<span class="mood-chip">{{ ['happy'=>'Keyfi yerinde','energetic'=>'Enerjik','calm'=>'Sakin','resting'=>'Dinleniyor','eating'=>'Yemek yiyor'][$update->mood]??$update->mood }}</span>@endif
      <small class="hint">{{ $update->created_at->format('d.m.Y H:i') }}</small>
      @if($update->body)<p style="white-space:pre-line">{{ $update->body }}</p>@endif
      @if($update->photo_path)<a href="{{ route('reservations.updates.photo', [$res, $update]) }}"><img src="{{ route('reservations.updates.photo', [$res, $update]) }}" alt="{{ $update->headline }}" loading="lazy" style="display:block;max-width:100%;max-height:420px;object-fit:contain;border-radius:12px;margin-top:10px"></a>@endif
      @if($update->video_path)<video controls preload="metadata" playsinline style="display:block;width:min(100%,680px);max-height:420px;border-radius:12px;background:#17211a;margin-top:10px"><source src="{{ route('reservations.updates.photo', [$res, $update]) }}">Tarayıcınız bu videoyu oynatamıyor.</video>@endif
    </article>
  @endforeach
@endif
</section>

@if($res->guestReview)
<div class="cardbox" style="margin-top:16px">
  <h2>İşletmenin konaklama değerlendirmeniz</h2>
  <p><strong>{{ $res->guestReview->rating }}/5</strong>@if($res->guestReview->title) · {{ $res->guestReview->title }}@endif</p>
  @if($res->guestReview->comment)<p style="white-space:pre-line">{{ $res->guestReview->comment }}</p>@endif
</div>
@endif

<section class="cardbox" style="margin-top:16px">
<h2>Konaklamayı uzat</h2>
@if($res->extensions->isNotEmpty())
  @foreach($res->extensions as $extension)
    <article style="border-top:1px solid #e7e7e7;padding:12px 0">
      <b>{{ $extension->current_check_out->format('d.m.Y') }} → {{ $extension->requested_check_out->format('d.m.Y') }}</b>
      · {{ $extension->added_nights }} gece · {{ number_format((float)$extension->quoted_total,2,',','.') }} ₺ · {{ \App\Models\ReservationExtension::STATUS[$extension->status] ?? $extension->status }}
      @if($extension->status === 'approved')
        · {{ $extension->financialEntry ? ('Ek ücret: '.($extension->financialEntry->status === 'paid' ? 'Tahsil edildi' : 'İşletme tahsilatı bekleniyor')) : 'Rezervasyon toplamına eklendi' }}
      @endif
      @if($extension->status === 'approved' && $res->payment_status === 'paid' && $extension->financialEntry && $extension->financialEntry->status === 'pending')
        <div class="extension-online-payment">
          <h3>Uzatma bedelini güvenli öde</h3><a class="btn" href="{{ route('reservations.extensions.payment', [$res, $extension]) }}">Uzatma bedelini güvenli öde</a>
          <p class="hint">iyzico sandbox anahtarları yapılandırılmadı. Ödeme başlatılmadı.</p>
          <p class="hint">Konaklama uzatma talebiniz onaylandığında ek tutar rezervasyonunuza eklenir. Ödeme doğrudan tesiste yapılır; çevrimiçi kart bilgisi alınmaz.</p>
        </div>
      @endif
      @if($extension->customer_note)<p>Notunuz: {{ $extension->customer_note }}</p>@endif
      @if($extension->hotel_note)<p>İşletme notu: {{ $extension->hotel_note }}</p>@endif
    </article>
  @endforeach
@endif
@if($res->status === 'confirmed' && in_array($res->payment_status, ['unpaid', 'paid'], true) && $res->check_out->gt(today()) && !$res->extensions->contains('status','pending'))
  <p class="hint">Yeni tarihiniz, işletme onayladıktan sonra geçerli olur. Onaylanan ek tutar işletmenin cari hesabına ayrı borç olarak eklenir; işletme tahsil ettiğinde bu sayfada güncellenir.</p>
  <form class="form" method="post" action="{{ route('reservations.extensions.store',$res) }}">@csrf
    <label>Yeni çıkış tarihi</label><input type="date" name="requested_check_out" min="{{ $res->check_out->copy()->addDay()->toDateString() }}" max="{{ $res->check_in->copy()->addDays(60)->toDateString() }}" required>
    <label>İşletmeye not (isteğe bağlı)</label><textarea name="customer_note" maxlength="1000"></textarea>
    <button class="btn">Uzatma talebi gönder</button>
  </form>
@endif
</section>

<section class="cardbox" style="margin-top:16px"><h2>Rezervasyonunuza ek ürün ve hizmetler</h2>
@if($res->addonOrders->isNotEmpty())
@foreach($res->addonOrders as $order)
<article style="border-top:1px solid #e7e7e7;padding:14px 0"><b>Talep #{{ $order->id }}</b> · {{ \App\Models\ReservationAddonOrder::STATUS[$order->status] ?? $order->status }}
<ul>@foreach($order->items as $item)<li>{{ $item->name_snapshot }} · {{ $item->quantity }} {{ \App\Models\HotelExtra::UNITS[$item->unit_snapshot] ?? 'adet' }} · {{ number_format((float)$item->line_total,2,',','.') }} ₺</li>@endforeach</ul>
@if($order->customer_note)<p>Notunuz: {{ $order->customer_note }}</p>@endif
@if($order->status === 'approved')<p><b>Ek tutar:</b> {{ number_format((float)$order->amount,2,',','.') }} ₺ · <b>Tahsilat:</b> {{ $order->payment_status === 'paid' ? 'Ödendi' : 'İşletme tahsilatı bekleniyor' }}</p>@endif
@if($order->hotel_note)<p class="hint">İşletme notu: {{ $order->hotel_note }}</p>@endif
</article>
@endforeach
@endif
@if($res->status === 'confirmed' && $res->check_out->gt(today()) && $res->hotel->extras->isNotEmpty())
<p class="hint">İşletmenin onaylayıp fiyatladığı ürün/hizmetler talep olarak iletilir. Onaylanınca tutar cari deftere eklenir; bu sayfada kart çekimi yapılmaz.</p>
<form class="form" method="post" action="{{ route('reservations.addons.store',$res) }}">@csrf
<input type="hidden" name="request_token" value="{{ old('request_token', (string)\Illuminate\Support\Str::uuid()) }}">
@foreach($res->hotel->extras as $extra)
<label class="chk" style="align-items:flex-start"><input type="number" name="items[{{ $extra->id }}]" min="0" max="20" step="1" value="{{ old('items.'.$extra->id,0) }}" aria-label="{{ $extra->name }} adedi"><span><b>{{ $extra->name }}</b> · {{ \App\Models\HotelExtra::CATEGORIES[$extra->category] ?? 'Hizmet' }} · {{ number_format((float)$extra->price,2,',','.') }} ₺ / {{ \App\Models\HotelExtra::UNITS[$extra->unit] ?? $extra->unit }} @if($extra->description)<small>{{ $extra->description }}</small>@endif</span></label>
@endforeach
<label>İşletmeye not (isteğe bağlı)</label><textarea name="customer_note" maxlength="1000">{{ old('customer_note') }}</textarea><button class="btn">Ek hizmet talebi gönder</button></form>
@elseif($res->status === 'confirmed' && $res->check_out->gt(today()))<p class="hint">Bu işletme şu an rezervasyon sonrası ek ürün/hizmet sunmuyor.</p>@endif
</section>

@include('reservations._mesajlar', ['res' => $res, 'action' => route('reservations.message', $res), 'benId' => auth()->id(), 'pollUrl' => route('reservations.messages', $res)])
</div>
@endsection

