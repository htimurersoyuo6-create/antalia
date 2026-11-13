@extends('layouts.app')
@section('title', 'Rezervasyon '.$res->code.' – Panel')
@section('content')
<style>
.reservation-detail-page{max-width:1180px;padding-top:24px;padding-bottom:64px;color:#29352d}
.reservation-detail-hero{position:relative;isolation:isolate;overflow:hidden;margin:0 0 16px;padding:clamp(20px,3.5vw,34px);border:1px solid #eee2d5;border-radius:24px;background:radial-gradient(circle at 94% 10%,#e9f3e8 0,transparent 34%),linear-gradient(115deg,#fff2e8 0%,#fffdfa 56%,#f2f7f0 100%);box-shadow:0 12px 32px #29352d0a}
.reservation-detail-hero:after{position:absolute;z-index:-1;right:8%;top:-150px;width:300px;height:300px;border:1px solid #f0e4d8;border-radius:50%;box-shadow:0 0 0 22px #fff5,0 0 0 48px #fff3;content:""}
.reservation-detail-crumb{display:flex;flex-wrap:wrap;gap:7px;margin:0 0 24px;padding:0;color:#8a938b;font-size:11px;font-weight:650;line-height:1.6}
.reservation-detail-crumb a{color:#65736a;text-decoration:none}.reservation-detail-crumb a:hover{color:#d64b24}
.reservation-detail-title-row{display:flex;align-items:flex-start;justify-content:space-between;gap:14px}
.reservation-detail-kicker{display:block;margin-bottom:7px;color:#b8502c;font-size:9px;font-weight:900;letter-spacing:.15em;text-transform:uppercase}
.reservation-detail-title-row h1{margin:0;color:#28342d;font-size:clamp(25px,4.2vw,38px);font-weight:900;line-height:1.13;letter-spacing:-.045em;overflow-wrap:anywhere}
.reservation-detail-code{display:inline-flex;align-items:center;flex:none;margin-top:3px;padding:8px 11px;border:1px solid #eadfD3;border-radius:11px;background:#ffffffc9;color:#5b675e;font-size:11px;font-weight:850;letter-spacing:.035em}
.reservation-detail-status{display:flex;flex-wrap:wrap;gap:8px;margin-top:17px}
.reservation-detail-status .st{display:inline-flex;align-items:center;min-height:32px;padding:6px 11px;border-radius:999px;font-size:10px;font-weight:850}
.reservation-detail-payment{display:inline-flex;align-items:center;gap:7px;min-height:32px;padding:6px 11px;border:1px solid #e8e8df;border-radius:999px;background:#ffffffc9;color:#68736b;font-size:10px;font-weight:750}
.reservation-detail-payment b{color:#354138}
.reservation-overview{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px;margin:0 0 18px}
.reservation-overview-card{display:grid;grid-template-columns:40px minmax(0,1fr);align-items:start;gap:11px;min-width:0;padding:16px;border:1px solid #e8eae3;border-radius:17px;background:#fff;box-shadow:0 7px 20px #28342d08}
.reservation-overview-icon{display:grid;place-items:center;width:40px;height:40px;border-radius:13px;background:#fff0e8;color:#d94a20;font-size:18px}
.reservation-overview-copy{min-width:0}.reservation-overview-label{display:block;margin:1px 0 5px;color:#849087;font-size:9px;font-weight:850;letter-spacing:.09em;text-transform:uppercase}
.reservation-overview-value{display:block;color:#344138;font-size:14px;font-weight:800;line-height:1.45;overflow-wrap:anywhere}
.reservation-overview-copy p{margin:4px 0 0;color:#6c786f;font-size:11px;line-height:1.55;overflow-wrap:anywhere}
.reservation-overview-copy a{color:#546b5c;text-decoration:none}.reservation-overview-copy a:hover{color:#d94a20;text-decoration:underline}
.reservation-overview-card.is-total{background:linear-gradient(135deg,#fffaf5,#fff)}.reservation-overview-card.is-total .reservation-overview-icon{background:#eaf5ed;color:#267448}.reservation-overview-card.is-total .reservation-overview-value{font-size:20px;font-weight:900;letter-spacing:-.03em}
.reservation-detail-note{margin:0 0 18px;padding:14px 16px;border:1px solid #eee6d9;border-radius:14px;background:#fffaf1;color:#655b4b;font-size:12px;line-height:1.65;overflow-wrap:anywhere}.reservation-detail-note b{color:#403b31}
.reservation-detail-page>section.cardbox{margin-top:16px!important;border:1px solid #e8e9e3;border-radius:18px;background:#fff;box-shadow:0 8px 24px #28342d07}
.reservation-detail-page>section.cardbox>h2{color:#303d34;font-size:18px;font-weight:850;letter-spacing:-.02em}
@media(max-width:520px){.reservation-detail-page{padding-top:15px;padding-bottom:44px}.reservation-detail-hero{margin-bottom:12px;padding:18px 16px;border-radius:19px}.reservation-detail-crumb{margin-bottom:17px;font-size:10px}.reservation-detail-title-row{display:block}.reservation-detail-title-row h1{font-size:26px}.reservation-detail-code{margin-top:10px}.reservation-detail-status{margin-top:13px}.reservation-overview{gap:9px}.reservation-overview-card{grid-template-columns:34px minmax(0,1fr);gap:8px;padding:12px 10px;border-radius:14px}.reservation-overview-icon{width:34px;height:34px;border-radius:11px;font-size:16px}.reservation-overview-value{font-size:12px}.reservation-overview-copy p{font-size:10px}.reservation-overview-card.is-total .reservation-overview-value{font-size:17px}}

.call-recordings-card{overflow:hidden!important;padding:0!important;background:linear-gradient(145deg,#fff 15%,#f7faf6)!important}
.call-recordings-heading{display:flex;align-items:center;gap:12px;padding:18px 20px;border-bottom:1px solid #e9ece5;background:linear-gradient(100deg,#fff8f1,#f6faf5)}
.call-recordings-heading>span{display:grid;place-items:center;flex:none;width:42px;height:42px;border-radius:13px;background:#eaf4ed;color:#347653;font-size:20px}
.call-recordings-heading h2{margin:0!important;color:#2e3d33!important;font-size:17px!important;font-weight:850!important}
.call-recordings-heading p{margin:4px 0 0;color:#748078;font-size:11px;line-height:1.5}
.call-recordings-table-scroll{width:100%;overflow-x:auto;overscroll-behavior-x:contain}
.call-recordings-table{width:100%;min-width:560px;border-collapse:collapse;text-align:left}
.call-recordings-table th{padding:11px 17px;background:#f7f8f5;color:#818b82;font-size:9px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;white-space:nowrap}
.call-recordings-table td{padding:13px 17px;border-top:1px solid #edf0ea;color:#465249;font-size:11px;line-height:1.45;vertical-align:middle}
.call-recordings-table tbody tr:hover{background:#fbfcf9}
.call-recordings-kind{display:inline-flex;padding:5px 9px;border-radius:999px;background:#eef5ef;color:#42694e;font-size:10px;font-weight:800;white-space:nowrap}
.call-recordings-date{color:#303e35;font-weight:800;white-space:nowrap}
.call-recordings-expiry{color:#778178;white-space:nowrap}
.call-record-download{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:34px;padding:7px 10px;border:1px solid #e6e9e3;border-radius:10px;background:#fff;color:#bd4b27;font-size:10px;font-weight:800;text-decoration:none;white-space:nowrap}
.call-record-download:hover{border-color:#efc3af;background:#fff9f5}
.pet-health-label{display:flex;align-items:center;gap:7px}.pet-health-label svg{width:16px;height:16px;flex:none;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;color:#df5429}.pet-health-unregistered{display:inline-flex;align-items:center;gap:6px;width:max-content;padding:4px 8px;border:1px solid #e7e9e4;border-radius:999px;background:#f5f6f3;color:#768078!important;font-size:11px;font-weight:750}.pet-health-unregistered svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:1.5;stroke-linecap:round;stroke-linejoin:round}
</style>
<div class="wrap page reservation-detail-page">
<header class="reservation-detail-hero">
  <nav class="reservation-detail-crumb" aria-label="Sayfa konumu"><a href="{{ route('panel.index') }}">Panel</a><span aria-hidden="true">›</span><a href="{{ route('panel.reservations') }}">Rezervasyonlar</a><span aria-hidden="true">›</span><span>{{ $res->code }}</span></nav>
  <div class="reservation-detail-title-row"><div><span class="reservation-detail-kicker">Rezervasyon ayrıntısı</span><h1>{{ $res->hotel->name }}</h1></div><span class="reservation-detail-code">{{ $res->code }}</span></div>
  <div class="reservation-detail-status"><span class="st {{ $res->status }}">{{ $res->status_label }}</span><span class="reservation-detail-payment">Ödeme <b>{{ $res->payment_status === 'paid' ? 'Alındı' : 'Otelde ödenecek' }}</b></span></div>
</header>
<section class="reservation-overview" aria-label="Rezervasyon özeti">
  <article class="reservation-overview-card"><span class="reservation-overview-icon" aria-hidden="true">♙</span><div class="reservation-overview-copy"><span class="reservation-overview-label">Misafir</span><strong class="reservation-overview-value">{{ $res->user->name }}</strong><p>@if($res->user->phone)<a href="tel:{{ preg_replace('/[^0-9+]/','',$res->user->phone) }}">{{ $res->user->phone }}</a>@endif @if($res->user->phone && $res->user->email) · @endif @if($res->user->email){{ $res->user->email }}@endif</p></div></article>
  <article class="reservation-overview-card"><span class="reservation-overview-icon" aria-hidden="true">🐾</span><div class="reservation-overview-copy"><span class="reservation-overview-label">Konaklayan pet</span><strong class="reservation-overview-value">{{ $res->pet->name }}</strong><p>{{ $res->pet->species_label }} · {{ $res->pet->size_label }} · {{ $res->pet->vaccinated ? 'Aşılı' : 'Aşı bilgisi yok' }}@if($res->pet->vaccination_expires_at) · Geçerlilik {{ $res->pet->vaccination_expires_at->format('d.m.Y') }}@endif</p></div></article>
  <article class="reservation-overview-card"><span class="reservation-overview-icon" aria-hidden="true">⌂</span><div class="reservation-overview-copy"><span class="reservation-overview-label">Oda ve konaklama</span><strong class="reservation-overview-value">{{ $res->room->name }}@if($res->roomUnit) · {{ $res->roomUnit->name }}@endif</strong><p>{{ $res->check_in->format('d.m.Y') }} → {{ $res->check_out->format('d.m.Y') }} · {{ $res->nights }} gece @if($res->early_departure_status === 'approved') · Erken çıkış onaylandı @endif</p></div></article>
  <article class="reservation-overview-card is-total"><span class="reservation-overview-icon" aria-hidden="true">₺</span><div class="reservation-overview-copy"><span class="reservation-overview-label">Rezervasyon tutarı</span><strong class="reservation-overview-value">{{ number_format($res->total_price, 0, ',', '.') }} ₺</strong><p>{{ $res->payment_status === 'paid' ? 'Tahsilat alındı' : 'Konaklama sırasında ödenecek' }}</p></div></article>
</section>
@if($res->extras->isNotEmpty() || (float) $res->discount_total > 0 || $res->customer_note || $res->pet->notes)
<section class="reservation-detail-note">@if($res->extras->isNotEmpty())<div><b>Ek hizmetler:</b> {{ $res->extras->map(fn($e) => $e->name.($e->quantity > 1 ? ' × '.$e->quantity : ''))->implode(', ') }}</div>@endif @if((float) $res->discount_total > 0)<div><b>Kampanya indirimi:</b> −{{ number_format($res->discount_total, 0, ',', '.') }} ₺</div>@endif @if($res->customer_note)<div><b>Misafir notu:</b> {{ $res->customer_note }}</div>@endif @if($res->pet->notes)<div><b>Pet hakkında not:</b> {{ $res->pet->notes }}</div>@endif</section>
@endif


@if(\App\Support\PlatformModules::enabled('private_calls', (int) $res->hotel_id))
  @if($res->status === 'confirmed' && $res->check_in->lte(today()) && $res->check_out->gte(today()))
    <div id="apo-private-call">
    @include('reservations.partials.private-call',['activeUrl'=>route('panel.reservations.calls.active',$res),'startUrl'=>route('panel.reservations.calls.start',$res),'pollUrl'=>route('panel.reservations.calls.poll',['call'=>'CALL_UUID']),'signalUrl'=>route('panel.reservations.calls.signal',['call'=>'CALL_UUID']),'recordingUrl'=>route('panel.reservations.calls.recording.chunk',['call'=>'CALL_UUID']),'finishUrl'=>route('panel.reservations.calls.recording.finish',['call'=>'CALL_UUID']),'retentionDays'=>(int)\App\Models\Setting::al('call_recording_days','30')])
    </div>
  @else
    <section class="cardbox" id="apo-private-call"><h2>🔒 Özel sesli ve görüntülü görüşme</h2>
      @if($res->status === 'pending')
        <p>Görüşme, tesis rezervasyonu onayladıktan ve konaklama başlangıç tarihinde açılır.</p>
      @elseif($res->status === 'confirmed' && $res->check_in->gt(today()))
        <p>Rezervasyon onaylandı. Görüşme {{ $res->check_in->format('d.m.Y') }} tarihinde konaklama başladığında burada açılacak.</p>
      @else
        <p>Canlı görüşme yalnızca onaylı ve devam eden konaklama süresinde kullanılabilir.</p>
      @endif
    </section>
  @endif
@endif

@if(in_array($res->status,['pending','confirmed'],true) && $res->check_out->gte(today()))
<section class="cardbox pet-reservation-health"><div class="pet-reservation-health-heading"><span>✚</span><div><h2>{{ $res->pet->name }} · Bakım ve sağlık bilgileri</h2><p>Bu bilgiler yalnızca bu rezervasyonu alan tesisin yetkili panelinde gösterilir.</p></div></div>
<div class="pet-reservation-health-grid">
<div><b class="pet-health-label"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2M9.5 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM17 8h5m-2.5-2.5v5"/></svg>Acil durumda aranacak kişiler</b>@if($res->pet->emergency_contact_name)<span>{{ $res->pet->emergency_contact_name }}@if($res->pet->emergency_contact_relationship) · {{ $res->pet->emergency_contact_relationship }}@endif</span>@else<span class="pet-health-unregistered"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="8"/><path d="m4.3 15.7 11.4-11.4M7.2 8a2.8 2.8 0 1 1 4.8 2M6.5 15a3.5 3.5 0 0 1 6.2-2.2"/></svg>Kayıtsız</span>@endif @if($res->pet->emergency_contact_phone)<a href="tel:{{ preg_replace('/[^0-9+]/','',$res->pet->emergency_contact_phone) }}">{{ $res->pet->emergency_contact_phone }}</a>@endif @if($res->pet->emergency_contact_2_name)<span>{{ $res->pet->emergency_contact_2_name }}@if($res->pet->emergency_contact_2_relationship) · {{ $res->pet->emergency_contact_2_relationship }}@endif</span>@if($res->pet->emergency_contact_2_phone)<a href="tel:{{ preg_replace('/[^0-9+]/','',$res->pet->emergency_contact_2_phone) }}">{{ $res->pet->emergency_contact_2_phone }}</a>@endif @endif</div>
<div><b class="pet-health-label"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3v5a4 4 0 0 0 8 0V3M4 3h4m4 0h4M10 12v2a5 5 0 0 0 10 0v-1M17.5 10.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Z"/></svg>Veteriner hekimi</b><span>{{ $res->pet->vet_name ?: 'Bilgi eklenmemiş' }}@if($res->pet->vet_clinic) · {{ $res->pet->vet_clinic }}@endif</span>@if($res->pet->vet_phone)<a href="tel:{{ preg_replace('/[^0-9+]/','',$res->pet->vet_phone) }}">{{ $res->pet->vet_phone }}</a>@endif @if($res->pet->vet_address)<small>{{ $res->pet->vet_address }}</small>@endif</div></div>
<div class="pet-reservation-vaccines"><b class="pet-health-label"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14 4 6 6M12 6l6 6m-4-4-9 9a2.1 2.1 0 0 0 3 3l9-9M5 17l2 2m-5 3 4-4M3 7l4 4m-2-6 4 4"/></svg>Aşı takvimi</b>@forelse($res->pet->vaccinations->sortBy('next_due_at') as $vaccine)<div><span>{{ $vaccine->vaccine_name }}</span><small>Uygulandı: {{ $vaccine->administered_at?->format('d.m.Y') ?? 'Belirtilmemiş' }} · Sonraki tarih: {{ $vaccine->next_due_at?->format('d.m.Y') ?? 'Belirtilmemiş' }}</small></div>@empty<p class="hint">Müşteri aşı takvimi bilgisi eklememiş.</p>@endforelse</div>
@if($res->pet->medicalDocuments->where('share_with_active_hotel', true)->isNotEmpty() && in_array($res->status,['pending','confirmed'],true) && $res->check_out->gte(today()))<div class="pet-reservation-documents"><b>Müşterinin izin verdiği sağlık belgeleri</b>@foreach($res->pet->medicalDocuments->where('share_with_active_hotel', true) as $document)<a href="{{ route('panel.reservations.pet-document',[$res,$document]) }}">▤ {{ $document->title }} · Güvenli görüntüle</a>@endforeach</div>@endif
<p class="hint">Aşı tarihleri müşteri tarafından kaydedilmiştir; uygulama ve sağlık kararlarını veteriner hekimle doğrulayın.</p></section>
@endif

@if($res->payment_status === 'paid' && in_array($res->status, ['confirmed','completed'], true))
<section id="invoice" class="cardbox invoice-owner-card">
  <div class="invoice-card-heading"><span aria-hidden="true">▤</span><div><h2>Resmî fatura</h2><p>Ödeme alınmış rezervasyon için e‑Fatura veya e‑Arşiv belgesini buradan paylaşabilirsiniz.</p></div></div>
  @if($res->invoice && $res->invoice->isIssued())
    <div class="invoice-issued"><div><b>{{ $res->invoice->invoice_type === 'e_fatura' ? 'e‑Fatura' : 'e‑Arşiv' }} · {{ $res->invoice->invoice_number }}</b><small>{{ $res->invoice->issued_at?->format('d.m.Y') }} tarihinde kaydedildi</small></div><a class="btn sm ghost" href="{{ route('invoices.download',$res->invoice) }}">Belgeyi indir</a></div>
    <p class="hint">Müşteri belgesini kendi panelinden indirebilir. E-posta: {{ ['sent'=>'Gönderildi','not_configured'=>'E-posta servisi yapılandırılmadı','failed'=>'Gönderilemedi','pending'=>'Bekliyor'][$res->invoice->email_status] ?? $res->invoice->email_status }} · WhatsApp: {{ $res->invoice->whatsapp_status === 'sent' ? 'Gönderildi' : 'API yapılandırılmadı' }}</p>
  @else
    <p class="invoice-pending-note">Henüz resmî belge yüklenmedi. E‑Fatura sağlayıcınızdan düzenlediğiniz PDF/XML dosyasını ekleyin. Bu alan tek başına yasal fatura düzenlemez.</p>
    <form class="invoice-upload-form" method="post" enctype="multipart/form-data" action="{{ route('panel.reservations.invoice.upload',$res) }}">
      @csrf
      <label>Belge türü<select name="invoice_type" required><option value="e_fatura">e‑Fatura</option><option value="e_arsiv">e‑Arşiv fatura</option></select></label>
      <label>Fatura numarası<input name="invoice_number" maxlength="100" required placeholder="Örn. ABC20260000001"></label>
      <label>Düzenlenme tarihi<input type="date" name="issued_at" max="{{ today()->toDateString() }}" required></label>
      <label>Sağlayıcı UUID <span>(isteğe bağlı)</span><input name="provider_uuid" maxlength="100" placeholder="ETTN / UUID"></label>
      <label class="invoice-file">Resmî belge · PDF veya XML<input type="file" name="document" accept="application/pdf,.pdf,application/xml,text/xml,.xml" required><small>En fazla 10 MB. Belge müşteri ve yetkili işletme hesabınca güvenli biçimde görüntülenir.</small></label>
      <button class="btn" type="submit">Faturayı kaydet ve müşteriye ilet</button>
    </form>
  @endif
</section>
@endif

@if($res->reservationTransfer || $res->grooming_option_id || $res->has_insurance || $res->points_redeemed)
<div class="cardbox"><h2>Ödeme ve rezervasyon ekleri</h2> @if(auth()->user()?->isAdmin())<a href="{{ route('admin.transfer-zones.index') }}">Genel transfer bölge tarifelerini yönet</a>@else <a href="{{ route('panel.transfer-zones.index',$res->hotel) }}">Tesis transfer bölge tarifelerini yönet</a>@endif
@if($res->reservationTransfer)<p><b>Pet Taxi:</b> {{ $res->reservationTransfer->transfer_type }} · {{ $res->reservationTransfer->status }}</p><p><b>Rota:</b> {{ $res->reservationTransfer->pickup_address }} → {{ $res->reservationTransfer->dropoff_address }} · {{ number_format((float)$res->reservationTransfer->transfer_fee,2,',','.') }} ₺</p>@if($res->reservationTransfer->distance_km)<p><b>Yol mesafesi:</b> {{ number_format((float)$res->reservationTransfer->distance_km,2,',','.') }} km</p>@endif @if($res->reservationTransfer->vehicle_type)<p><b>Araç:</b> {{ ['sedan'=>'Standart araç','minivan'=>'Minivan','van'=>'Geniş araç'][$res->reservationTransfer->vehicle_type]??$res->reservationTransfer->vehicle_type }}</p>@endif @if($res->reservationTransfer->pickup_datetime)<p><b>Alınma:</b> {{ $res->reservationTransfer->pickup_datetime->format('d.m.Y H:i') }}</p>@endif @if($res->reservationTransfer->dropoff_datetime)<p><b>Dönüş / bırakılma:</b> {{ $res->reservationTransfer->dropoff_datetime->format('d.m.Y H:i') }}</p>@endif @endif
@if($res->groomingOption)<p><b>Kuaför:</b> {{ $res->groomingOption->title }} · {{ number_format((float)$res->grooming_fee,2,',','.') }} ₺</p>@endif @if($res->has_insurance)<p><b>Sigorta eki:</b> {{ number_format((float)$res->insurance_fee,2,',','.') }} ₺ · sağlayıcı payı {{ number_format((float)$res->insurance_provider_amount,2,',','.') }} ₺</p>@endif @if($res->points_redeemed)<p><b>PotiPuan:</b> {{ $res->points_redeemed }} kullanıldı / {{ number_format((float)$res->points_discount,2,',','.') }} ₺</p>@endif
</div>
@endif
@if($res->early_departure_status === 'requested')
<div class="cardbox"><h2>ERKEN ÇIKIŞ TALEBİ</h2><p><b>Talep edilen tarih:</b> {{ \Illuminate\Support\Carbon::parse($res->early_departure_date)->format('d.m.Y') }}</p>@if($res->early_departure_note)<p><b>Müşteri notu:</b> {{ $res->early_departure_note }}</p>@endif<p class="hint">Onay, çıkış tarihini ve gece bazlı ücretleri günceller. Ödenmiş rezervasyonda iade/mahsup işletme tarafından ayrıca tamamlanmalıdır.</p><form method="post" action="{{ route('panel.reservations.early-departure.decide',$res) }}">@csrf @method('PATCH')<label>Yanıt notu</label><input name="hotel_note" maxlength="1000"><button class="btn" name="action" value="approve">Onayla ve tarihleri güncelle</button> <button class="btn danger" name="action" value="reject">Reddet</button></form></div>
@elseif($res->early_departure_status === 'approved')
<div class="cardbox"><b class="st confirmed">ERKEN ÇIKIŞ ONAYLANDI</b><p>Güncel çıkış: {{ $res->check_out->format('d.m.Y') }} · {{ $res->nights }} gece</p>@if((float)$res->early_departure_refund_amount > 0)<p>İşletmenin tamamlaması gereken iade/mahsup: {{ number_format((float)$res->early_departure_refund_amount,2,',','.') }} ₺</p>@endif</div>
@endif
@if(($res->transfer_status ?? 'not_requested') !== 'not_requested')
<div class="cardbox transfer-panel">
<h2>Pet transfer ve konum paylaşımı</h2>
<p><b>Durum:</b> {{ ['requested'=>'İşletme onayı bekliyor','in_progress'=>'Transfer sürüyor','completed'=>'Tamamlandı','cancelled'=>'İptal edildi'][$res->transfer_status] ?? $res->transfer_status }}</p>
<p><b>Alınacağı adres:</b> {{ $res->transfer_pickup_address }} @if($res->transfer_pickup_lat)<a target="_blank" rel="noopener" href="https://www.openstreetmap.org/directions?to={{ $res->transfer_pickup_lat }},{{ $res->transfer_pickup_lng }}">Haritada aç</a>@endif</p>
@if($res->transfer_return_address)<p><b>Bırakılacağı adres:</b> {{ $res->transfer_return_address }} @if($res->transfer_return_lat)<a target="_blank" rel="noopener" href="https://www.google.com/maps?q={{ $res->transfer_return_lat }},{{ $res->transfer_return_lng }}">Haritada aç</a>@endif</p>@endif
<p><b>Ücret:</b> {{ number_format((float)$res->transfer_total,2,',','.') }} ₺ · Tahmini mesafe {{ $res->transfer_estimated_km }} km (işletme tarafından teyit edilmeli)</p>@if($res->petTransfer)<p><b>Alınış zamanı:</b> {{ $res->petTransfer->pickup_at->format('d.m.Y H:i') }} · <b>Araç:</b> {{ $res->petTransfer->vehicle_type }} · <b>Varış:</b> {{ $res->petTransfer->dropoff_address }}</p>@endif
@if($res->transfer_status==='requested' && $res->status==='confirmed')
<form method="post" action="{{ route('panel.reservations.transfer.start',$res) }}">@csrf<button class="btn">Transferi başlat ve konum paylaş</button></form>
@elseif($res->transfer_status==='in_progress')
<div id="transfer-gps-status" class="hint">Bu sayfa açıkken ve konum izni verildiğinde konum misafirle paylaşılır.</div>
<form method="post" action="{{ route('panel.reservations.transfer.complete',$res) }}">@csrf<button class="btn">Transferi tamamla</button></form>
<input type="hidden" id="transfer-csrf" value="{{ csrf_token() }}">
<script>
(function(){var out=document.getElementById('transfer-gps-status'),token=document.getElementById('transfer-csrf').value;if(!navigator.geolocation){out.textContent='Bu cihaz canlı konum paylaşımını desteklemiyor.';return;}var lastSent=0;function send(p){if(Date.now()-lastSent<15000)return;lastSent=Date.now();fetch(@json(route('panel.reservations.transfer.location.update',$res)),{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':token,'Accept':'application/json'},body:JSON.stringify({lat:p.coords.latitude,lng:p.coords.longitude})}).then(function(r){if(!r.ok)throw 0;out.textContent='Canlı konum paylaşılıyor · '+new Date().toLocaleTimeString('tr-TR')}).catch(function(){out.textContent='Konum gönderilemedi; bağlantıyı kontrol edin.'})}navigator.geolocation.watchPosition(send,function(){out.textContent='Konum izni kapalı. Tarayıcıdan konum izni verin.'},{enableHighAccuracy:true,maximumAge:5000,timeout:15000});})();
</script>
@endif
</div>
@endif
@if(\App\Support\PlatformModules::enabled('live_camera', (int) $res->hotel_id) && $res->hotel->camera_enabled && $res->hotel->camera_embed_url && $res->status === 'confirmed' && $res->check_in->lte(today()) && $res->check_out->gt(today()))
<section class="cardbox camera-owner-card" style="margin-top:16px">
<h2>🔒 Bu misafire özel canlı kamera</h2><p class="hint">Yayın erişimi yalnızca rezervasyon sahibi hesabına açıktır. Erişim kodunun kendisi saklanmaz; sistemde güvenli özeti tutulur. Yeni kod oluşturduğunuzda müşteriye bu rezervasyonun özel sohbetinden iletin.</p>
@if(session('camera_access_code'))<div class="camera-one-time-code"><b>Müşteriye özel yeni kod</b><code>{{ session('camera_access_code') }}</code><small>Bu kod yalnızca bu kez gösterilir. Müşteriye özel sohbetten gönderin.</small></div>@endif
@if($res->cameraAccess?->enabled)<p><span class="st confirmed">YAYIN AÇIK</span> · Kod son yenilenme: {{ $res->cameraAccess->updated_at?->format('d.m.Y H:i') }}</p>@else<p><span class="st pending">YAYIN KAPALI</span></p>@endif
<div class="camera-owner-actions">
@if(!$res->cameraAccess?->enabled)<form method="post" action="{{ route('panel.reservations.camera-access',$res) }}">@csrf @method('PATCH')<button class="btn" name="action" value="enable">Yayını aç ve erişim kodu üret</button></form>
@else<form method="post" action="{{ route('panel.reservations.camera-access',$res) }}">@csrf @method('PATCH')<button class="btn ghost" name="action" value="rotate">Müşteri kodunu yenile</button></form><form method="post" action="{{ route('panel.reservations.camera-access',$res) }}">@csrf @method('PATCH')<button class="btn danger" name="action" value="disable">Yayını durdur</button></form>@endif
</div></section>
<style>.camera-owner-card{padding:18px}.camera-owner-actions{display:flex;flex-wrap:wrap;gap:9px;margin-top:12px}.camera-owner-actions form{margin:0}.camera-one-time-code{display:grid;gap:5px;margin:12px 0;padding:13px;border:1px solid #d4ead9;border-radius:12px;background:#f2faf3}.camera-one-time-code b{font-size:11px}.camera-one-time-code code{width:max-content;padding:8px 11px;border-radius:8px;background:#fff;color:#174b2c;font-size:18px;font-weight:900;letter-spacing:.1em}.camera-one-time-code small{color:#68776b;font-size:10px}</style>
@endif

@php
$savedCallRecordings = $res->calls->filter(fn ($call) => $call->recording && $call->recording->status === 'ready' && $call->recording->expires_at->isFuture());
@endphp
@if($savedCallRecordings->isNotEmpty())
<section class="cardbox call-recordings-card" aria-labelledby="call-recordings-title">
  <header class="call-recordings-heading"><span aria-hidden="true">▤</span><div><h2 id="call-recordings-title">Güvenli görüşme kayıtları</h2><p>Kayıtlara yalnızca bu rezervasyonun tarafları erişebilir. Saklama süresi dolduğunda kayıtlar silinir.</p></div></header>
  <div class="call-recordings-table-scroll" role="region" aria-label="Görüşme kayıt tablosu" tabindex="0">
    <table class="call-recordings-table"><thead><tr><th scope="col">Tarih ve saat</th><th scope="col">Görüşme türü</th><th scope="col">Saklama sonu</th><th scope="col">Güvenli kayıt</th></tr></thead><tbody>
    @foreach($savedCallRecordings as $savedCall)<tr><td><span class="call-recordings-date">{{ $savedCall->created_at->format('d.m.Y · H:i') }}</span></td><td><span class="call-recordings-kind">{{ $savedCall->media_type==='video'?'Görüntülü':'Sesli' }}</span></td><td><span class="call-recordings-expiry">{{ $savedCall->recording->expires_at->format('d.m.Y') }}</span></td><td><a class="call-record-download" href="{{ route('panel.reservations.calls.recording.download',['call'=>$savedCall->uuid]) }}">↓ Kayıt dosyasını indir</a></td></tr>@endforeach
    </tbody></table>
  </div>
</section>
@endif

@if(\App\Support\PlatformModules::enabled('stay_updates', (int) $res->hotel_id))
<section class="cardbox" style="margin-top:16px">
<h2>Konaklama güncellemeleri</h2>
@if($res->updates->isEmpty())
  <p class="hint">Henüz misafirle paylaşılmış güncelleme yok.</p>
@else
  @foreach($res->updates->reverse() as $update)
    <article style="padding:12px 0;border-top:1px solid #e7e7e7">
      <strong>{{ $update->headline }}</strong>
      <small class="hint">{{ $update->created_at->format('d.m.Y H:i') }}</small>
      @if($update->body)<p style="white-space:pre-line">{{ $update->body }}</p>@endif
      @if($update->photo_path)<a href="{{ route('reservations.updates.photo', [$res, $update]) }}"><img src="{{ route('reservations.updates.photo', [$res, $update]) }}" alt="{{ $update->headline }}" loading="lazy" style="display:block;max-width:100%;max-height:420px;object-fit:contain;border-radius:12px;margin-top:10px"></a>@endif
      @if($update->video_path)<video controls preload="metadata" playsinline style="display:block;width:min(100%,680px);max-height:420px;border-radius:12px;background:#17211a;margin-top:10px"><source src="{{ route('reservations.updates.photo', [$res, $update]) }}">Tarayıcınız bu videoyu oynatamıyor.</video>@endif
    </article>
  @endforeach
@endif
@if($res->status === 'confirmed' && $res->check_in->lte(today()) && $res->check_out->gt(today()))
  <style>
  .stay-update-form{display:grid;gap:16px;margin-top:18px;padding:clamp(16px,2.4vw,24px);border:1px solid #e8e9e2;border-radius:18px;background:linear-gradient(145deg,#fffdfa,#f8faf7)}
  .stay-update-form-heading{display:flex;align-items:flex-start;gap:12px;padding-bottom:14px;border-bottom:1px solid #eceee8}
  .stay-update-form-heading>span{display:grid;place-items:center;flex:none;width:42px;height:42px;border-radius:13px;background:#fff0e8;color:#df4c22;font-size:20px}
  .stay-update-form-heading h3{margin:1px 0 4px;color:#2d3931;font-size:17px;font-weight:850}
  .stay-update-form-heading p{margin:0;color:#707b73;font-size:12px;line-height:1.55}
  .stay-update-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
  .stay-update-field{display:grid;align-content:start;gap:7px;min-width:0;color:#39443c;font-size:12px;font-weight:750;line-height:1.4}
  .stay-update-field input,.stay-update-field textarea{box-sizing:border-box;width:100%;min-width:0;margin:0;padding:12px 13px;border:1px solid #dfe4dc;border-radius:12px;background:#fff;color:#28342d;font-family:inherit;font-size:14px;font-weight:500;line-height:1.5}
  .stay-update-field input[type=file]{padding:9px;background:#fff;font-size:12px}
  .stay-update-field input:focus,.stay-update-field textarea:focus{outline:3px solid #ffebe2;border-color:#ed7954}
  .stay-update-field textarea{resize:vertical}
  .stay-update-field small{color:#78827a;font-size:10px;font-weight:500}
  .stay-update-help{margin:0;padding:11px 13px;border:1px solid #e8eee6;border-radius:12px;background:#f3f8f2;color:#68746a;font-size:11px;line-height:1.65}
  .stay-update-form>.btn{justify-self:start;min-height:44px;padding:11px 19px}
  @media(max-width:640px){.stay-update-fields{grid-template-columns:1fr}.stay-update-form{gap:13px;padding:15px}.stay-update-form>.btn{width:100%;justify-self:stretch}}
  </style>
  <div class="stay-template-save"><strong>İşletmenizin hazır yanıtları</strong><div class="stay-template-buttons">@foreach(($updateTemplates ?? collect()) as $template)<button type="button" class="btn sm ghost" data-stay-title="{{ $template->title }}" data-stay-body="{{ $template->body }}">{{ $template->title }}</button><form method="post" action="{{ route('panel.reservations.templates.delete',$template->id) }}">@csrf @method('DELETE')<button class="btn sm danger" title="Şablonu sil" type="submit">×</button></form>@endforeach</div><form method="post" action="{{ route('panel.reservations.templates.store',$res) }}">@csrf<label>Yeni şablon başlığı<input name="title" required maxlength="120" placeholder="Bahçede oyun zamanı"></label><label>Hazır mesaj<textarea name="body" required maxlength="1000" rows="2" placeholder="Şu anda bahçede oyun oynuyor."></textarea></label><button class="btn sm ghost" type="submit">Şablonu kaydet</button></form></div>
  <script>document.addEventListener('click',e=>{const b=e.target.closest('[data-stay-title]');if(!b)return;const h=document.getElementById('update-headline'),body=document.getElementById('update-body');if(h)h.value=b.dataset.stayTitle||'';if(body)body.value=b.dataset.stayBody||'';});</script>
  <style>.stay-template-save{display:grid;gap:9px;margin:12px 0;padding:14px;border:1px solid #e5e9e2;border-radius:14px;background:#f8faf7}.stay-template-save label{display:grid;gap:5px;font-size:12px;font-weight:700}.stay-template-save input,.stay-template-save textarea{padding:9px;border:1px solid #dfe4dc;border-radius:9px;font:inherit}.stay-template-buttons{display:flex;flex-wrap:wrap;gap:7px}.stay-template-buttons form{display:flex}</style>
  <form class="stay-update-form" method="post" enctype="multipart/form-data" action="{{ route('panel.reservations.updates.store', $res) }}">
    @csrf
    <div class="stay-update-form-heading"><span aria-hidden="true">✦</span><div><h3>Misafire durum veya fotoğraf gönder</h3><p>Konaklama sırasında paylaştığınız güncelleme misafirin rezervasyon panelinde görünür.</p></div></div>
    <div class="stay-update-fields">
      <label class="stay-update-field" for="update-headline">Başlık<input id="update-headline" name="headline" maxlength="120" required placeholder="Örn. Günlük yürüyüş tamamlandı"></label>
      <label class="stay-update-field" for="update-body">Kısa açıklama<textarea id="update-body" name="body" maxlength="1500" rows="3" placeholder="Misafirin bilmesi gereken kısa bilgi"></textarea></label>
      <label class="stay-update-field" for="update-photo">Fotoğraf <small>İsteğe bağlı · JPEG, PNG veya WebP</small><input id="update-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp"></label>
      <label class="stay-update-field" for="update-video">Video <small>İsteğe bağlı · MP4, MOV veya WebM</small><input id="update-video" type="file" name="video" accept="video/mp4,video/webm,video/quicktime,.mp4,.mov,.webm"></label>
    </div>
    <p class="stay-update-help">Fotoğraflar en fazla 5 MB, videolar en fazla 20 MB. Tek güncellemede fotoğraf veya video seçin. Medyayı yalnızca bu rezervasyonun misafiri ve işletmeniz görüntüleyebilir.</p>
    <button class="btn" type="submit">Güncellemeyi paylaş</button>
  </form>
  <form class="care-report-form" method="post" enctype="multipart/form-data" action="{{ route('panel.reservations.updates.store', $res) }}">
    @csrf
    <input type="hidden" name="update_type" value="care_report">
    <h3>Günlük bakım raporu</h3>
    <p class="hint">Yalnızca kaydettiğiniz bilgileri seçin. Rapor, bu rezervasyonun misafir panelinde görünür.</p>
    <div class="care-report-grid">
      <label>Ruh hali<select name="mood"><option value="">Bilgi ekle</option><option value="happy">Keyfi yerinde</option><option value="energetic">Enerjik</option><option value="calm">Sakin</option><option value="resting">Dinleniyor</option><option value="eating">Yemek yiyor</option></select></label>
      <label>İştah<select name="appetite"><option value="">Bilgi ekle</option><option value="good" @selected(old('appetite')==='good')>İyi</option><option value="partial" @selected(old('appetite')==='partial')>Biraz yedi</option><option value="poor" @selected(old('appetite')==='poor')>İştahsız</option></select></label>
      <label>Su tüketimi<select name="water"><option value="">Bilgi ekle</option><option value="normal" @selected(old('water')==='normal')>Normal</option><option value="low" @selected(old('water')==='low')>Az içti</option><option value="watch" @selected(old('water')==='watch')>Takip edilmeli</option></select></label>
      <label>Hareket<select name="activity"><option value="">Bilgi ekle</option><option value="playful" @selected(old('activity')==='playful')>Yürüyüş / oyun yaptı</option><option value="calm" @selected(old('activity')==='calm')>Sakin geçti</option><option value="rest" @selected(old('activity')==='rest')>Dinlendi</option></select></label>
      <label>Tuvalet<select name="toilet"><option value="">Bilgi ekle</option><option value="normal" @selected(old('toilet')==='normal')>Normal</option><option value="watch" @selected(old('toilet')==='watch')>Takip edilmeli</option><option value="not_observed" @selected(old('toilet')==='not_observed')>Gözlemlenmedi</option></select></label>
      <label>İlaç<select name="medication"><option value="">Bilgi ekle</option><option value="given" @selected(old('medication')==='given')>Uygulandı</option><option value="not_due" @selected(old('medication')==='not_due')>Saati gelmedi</option><option value="not_planned" @selected(old('medication')==='not_planned')>İlaç planı yok</option></select></label>
    </div>
    <label for="care-report-note">Ek not (isteğe bağlı)</label>
    <textarea id="care-report-note" name="body" maxlength="1500" rows="2" placeholder="Günün önemli bir ayrıntısı"></textarea>
    <label for="care-report-photo">Fotoğraf (isteğe bağlı)</label>
    <input id="care-report-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp">
    <label for="care-report-video">Video (isteğe bağlı)</label><input id="care-report-video" type="file" name="video" accept="video/mp4,video/webm,video/quicktime,.mp4,.mov,.webm">
    <button class="btn" type="submit">Bakım raporunu paylaş</button>
  </form>
@endif
</section>
@endif

<section class="cardbox" style="margin-top:16px">
<h2>Misafir değerlendirmesi</h2>
@if($res->guestReview)
  <p><strong>{{ $res->guestReview->rating }}/5</strong>@if($res->guestReview->title) · {{ $res->guestReview->title }}@endif</p>
  @if($res->guestReview->comment)<p style="white-space:pre-line">{{ $res->guestReview->comment }}</p>@endif
@elseif($res->status === 'completed' && !$res->check_out->gt(today()))
  <p class="hint">Konaklama tamamlandığı için misafir deneyimi hakkında değerlendirme bırakabilirsiniz.</p>
  <form method="post" action="{{ route('panel.reservations.guest-review.store', $res) }}">
    @csrf
    <label for="guest-rating">Puan</label>
    <select id="guest-rating" name="rating" required><option value="">Puan seçin</option>@for($puan=5;$puan>=1;$puan--)<option value="{{ $puan }}">{{ $puan }} / 5</option>@endfor</select>
    <label for="guest-review-title">Başlık (isteğe bağlı)</label>
    <input id="guest-review-title" name="title" maxlength="150">
    <label for="guest-review-comment">Not (isteğe bağlı)</label>
    <textarea id="guest-review-comment" name="comment" maxlength="1500" rows="3"></textarea>
    <button class="btn" type="submit">Değerlendirmeyi gönder</button>
  </form>
@else
  <p class="hint">Değerlendirme konaklama tamamlandıktan sonra açılır.</p>
@endif
</section>

<section class="cardbox" style="margin-top:16px">
<h2>Konaklama uzatma talepleri</h2>
@if($res->extensions->isEmpty())<p class="hint">Henüz uzatma talebi yok.</p>@endif
@foreach($res->extensions as $extension)
  <article style="border-top:1px solid #e7e7e7;padding:12px 0">
    <b>{{ $extension->requester->name ?? $res->user->name }}</b> · {{ $extension->current_check_out->format('d.m.Y') }} → {{ $extension->requested_check_out->format('d.m.Y') }} · {{ $extension->added_nights }} gece
    <p>Tahmini ek tutar: {{ number_format((float)$extension->quoted_total,2,',','.') }} ₺ · {{ \App\Models\ReservationExtension::STATUS[$extension->status] ?? $extension->status }}</p>
    @if($extension->customer_note)<p>Misafir notu: {{ $extension->customer_note }}</p>@endif
    @if($extension->hotel_note)<p>Yanıt notunuz: {{ $extension->hotel_note }}</p>@endif
    @if($extension->status === 'pending')
      <form method="post" action="{{ route('panel.reservations.extensions.decide',[$res,$extension]) }}">@csrf
        <label>Misafire not (isteğe bağlı)</label><textarea name="hotel_note" maxlength="1000"></textarea>
        <button class="btn" name="action" value="approve">Onayla ve tarihleri güncelle</button>
        <button class="btn danger" name="action" value="reject">Reddet</button>
      </form>
    @endif
  </article>
@endforeach
@if($res->payment_status === 'paid')<p class="hint">Ödemesi alınmış rezervasyon. Sistem çevrimiçi uzatmayı kapalı tutar; ücret/iade hesabını işletmeyle ayrıca tamamlayın.</p>@endif
</section>

<section class="cardbox" style="margin-top:16px"><h2>Rezervasyon ek hizmet talepleri</h2>
@if($res->addonOrders->isEmpty())<p class="hint">Bu rezervasyon için ek ürün veya hizmet talebi yok.</p>@endif
@foreach($res->addonOrders as $order)
<article style="border-top:1px solid #e7e7e7;padding:14px 0"><b>#{{ $order->id }} · {{ $order->customer->name }}</b> · {{ \App\Models\ReservationAddonOrder::STATUS[$order->status] ?? $order->status }}
<ul>@foreach($order->items as $item)<li>{{ $item->name_snapshot }} · {{ $item->quantity }} × {{ number_format((float)$item->unit_price,2,',','.') }} ₺ = {{ number_format((float)$item->line_total,2,',','.') }} ₺</li>@endforeach</ul>
<p><b>Toplam:</b> {{ number_format((float)$order->amount,2,',','.') }} ₺ · <b>Tahsilat:</b> {{ $order->payment_status==='paid'?'Ödendi':($order->payment_status==='cancelled'?'İptal edildi':'Bekliyor') }}</p>
@if($order->customer_note)<p>Müşteri notu: {{ $order->customer_note }}</p>@endif @if($order->hotel_note)<p>Yanıt notu: {{ $order->hotel_note }}</p>@endif
@if($order->status==='pending')<form method="post" action="{{ route('panel.reservations.addons.decide',[$res,$order]) }}">@csrf @method('PATCH')<label>Yanıt / fiyatlandırma notu</label><textarea name="hotel_note" maxlength="1000"></textarea><button class="btn sm" name="action" value="approve">Onayla ve cari kayda al</button> <button class="btn sm danger" name="action" value="reject">Reddet</button></form>
@elseif($order->status==='approved' && $order->payment_status==='unpaid')<p class="hint">Tahsilat, onaydan sonra cari defterde bekleyen gelir olarak açıldı.</p><a class="btn sm ghost" href="{{ route('panel.finance',['status'=>'pending']) }}">Cari tahsilatı aç</a>
@elseif($order->status==='approved' && $order->payment_status==='paid')<form method="post" action="{{ route('panel.reservations.addons.complete',[$res,$order]) }}">@csrf @method('PATCH')<button class="btn sm">Teslim edildi / uygulandı</button></form>@endif
</article>
@endforeach
<p class="hint">Kart tahsilatı henüz etkin değil. Müşteri kartından ödeme alınmış gibi kayıt oluşturmayın; tahsilatı doğruladıktan sonra cari defterde ödendi olarak işaretleyin.</p>
</section>

@include('reservations._mesajlar', ['res' => $res, 'action' => route('panel.reservations.message', $res), 'benId' => auth()->id(), 'pollUrl' => route('panel.reservations.messages', $res), 'chatTitle' => 'Misafir rezervasyonu ile güvenli mesajlaşma', 'chatSubtitle' => 'Misafir: '.$res->user->name.' · Konaklayan pet: '.$res->pet->name.' · Oda: '.$res->room->name.($res->roomUnit ? ' · '.$res->roomUnit->name : '').' · Konaklama: '.$res->check_in->format('d.m.Y').'–'.$res->check_out->format('d.m.Y')])

@include('reservations._tutanak', ['res' => $res, 'musteri' => false])
@php
  $var = $res->records->pluck('type')->all();
  $sira = !in_array('checkin', $var, true) ? 'checkin' : (!in_array('checkout', $var, true) ? 'checkout' : null);
@endphp
@if($sira && in_array($res->status, ['confirmed', 'completed'], true) && !($sira === 'checkin' && $res->check_in->isFuture()))
<form class="form" method="post" action="{{ route('panel.reservations.record', $res) }}" style="margin-top:14px">@csrf
  <h3>{{ \App\Models\ReservationRecord::TYPES[$sira] }} hazırla</h3>
  <input type="hidden" name="type" value="{{ $sira }}">
  <label>Not <small>(sağlık durumu, getirilen eşyalar, ilaç/beslenme bilgisi, hasar vb.)</small></label>
  <textarea name="note" maxlength="1500" style="min-height:90px"></textarea>
  <button class="btn">Kaydet ve misafire gönder</button>
</form>
@endif
</div>
@endsection
