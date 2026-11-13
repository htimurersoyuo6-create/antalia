@extends('layouts.admin')
@section('title','Rezervasyon faturaları')
@section('heading','Rezervasyon faturaları')
@section('sub','Ödemeli rezervasyonların fatura ve teslim durumlarını izleyin.')
@section('content')
@if($errors->has('whatsapp_send'))<div class="a-alert error">{{ $errors->first('whatsapp_send') }}</div>@endif
<section class="a-card invoice-admin-card">
  <div class="a-card-h"><div><h2>Fatura kayıtları</h2><p>İşletmelerin yüklediği resmî e‑Fatura/e‑Arşiv belgeleri.</p></div><span class="a-count">{{ $invoices->total() }} kayıt</span></div>
  <div class="a-tw"><table class="a-table"><thead><tr><th>REZERVASYON / MÜŞTERİ</th><th>TESİS</th><th>BELGE</th><th>TUTAR</th><th>TESLİM</th><th>İŞLEM</th></tr></thead><tbody>
  @forelse($invoices as $invoice)
    <tr><td><b>{{ $invoice->reservation->code }}</b><small>{{ $invoice->reservation->user->name ?? 'Müşteri' }} · {{ $invoice->reservation->user->email ?? '' }}</small></td><td>{{ $invoice->hotel->name ?? '—' }}</td><td>@if($invoice->isIssued()){{ $invoice->invoice_type === 'e_fatura' ? 'e‑Fatura' : 'e‑Arşiv' }}<small>{{ $invoice->invoice_number }} · {{ $invoice->issued_at?->format('d.m.Y') }}</small>@else<span class="a-badge pending">Belge bekleniyor</span>@endif</td><td>{{ number_format((float)$invoice->amount,2,',','.') }} ₺</td><td>E-posta: {{ ['sent'=>'Gönderildi','not_configured'=>'Yapılandırılmadı','failed'=>'Başarısız','pending'=>'Bekliyor'][$invoice->email_status] ?? $invoice->email_status }}<small>WhatsApp: {{ ['sent'=>'Meta kabul etti','failed'=>'Başarısız','not_configured'=>'Yapılandırılmadı','pending'=>'Bekliyor'][$invoice->whatsapp_status] ?? $invoice->whatsapp_status }}</small></td><td>@if($invoice->isIssued())<a class="a-btn sm ghost" href="{{ route('invoices.download',$invoice) }}">Güvenli indir</a>@if($invoice->document_mime === 'application/pdf' && $invoice->whatsapp_status !== 'sent' && $whatsappReady)<form method="post" action="{{ route('admin.invoices.whatsapp',$invoice) }}" style="margin-top:8px">@csrf<label style="display:block;max-width:200px;font-size:10px;line-height:1.35"><input type="checkbox" name="whatsapp_consent" value="1" required> Müşterinin WhatsApp üzerinden fatura alma izni var</label><button class="a-btn sm" style="margin-top:5px">WhatsApp’tan gönder</button></form>@elseif($invoice->whatsapp_status === 'sent')<small>WhatsApp bildirimi Meta tarafından kabul edildi.</small>@elseif($invoice->document_mime === 'application/pdf')<small><a href="{{ route('admin.settings') }}#whatsapp-cloud-settings">WhatsApp bağlantısını yapılandır</a></small>@endif @else—@endif</td></tr>
  @empty<tr><td colspan="6" class="a-empty">Henüz fatura kaydı bulunmuyor.</td></tr>@endforelse
  </tbody></table></div>
  @if($invoices->hasPages())<div class="a-quick">{{ $invoices->links() }}</div>@endif
</section>
@endsection
