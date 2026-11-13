@extends('layouts.app')
@section('title', 'Fatura '.$invoice->reservation->code.' | Antalya Pet Otel')
@section('content')
<div class="wrap page invoice-page">
  <p class="crumb"><a href="{{ route('invoices.index') }}">Faturalarım</a> › {{ $invoice->reservation->code }}</p>
  <header class="invoice-page-hero"><span class="invoice-kicker">{{ $invoice->isIssued() ? 'RESMÎ BELGE' : 'BELGE DURUMU' }}</span><h1>{{ $invoice->reservation->hotel->name }}</h1><p>Rezervasyon {{ $invoice->reservation->code }}</p></header>
  <section class="invoice-detail-card">
    <h2>{{ $invoice->isIssued() ? ($invoice->invoice_type === 'e_fatura' ? 'e‑Fatura' : 'e‑Arşiv fatura') : 'Faturanız hazırlanıyor' }}</h2>
    @if($invoice->isIssued())
      <dl><div><dt>Fatura numarası</dt><dd>{{ $invoice->invoice_number }}</dd></div><div><dt>Düzenlenme tarihi</dt><dd>{{ $invoice->issued_at?->format('d.m.Y') }}</dd></div><div><dt>Tutar</dt><dd>{{ number_format((float)$invoice->amount,2,',','.') }} ₺</dd></div>@if($invoice->provider_uuid)<div><dt>ETTN / UUID</dt><dd>{{ $invoice->provider_uuid }}</dd></div>@endif</dl>
      <a class="btn" href="{{ route('invoices.download',$invoice) }}">Resmî belgeyi indir</a>
    @else
      <p>İşletme resmî e‑Fatura/e‑Arşiv belgesini yüklediğinde burada görüntüleyebilirsiniz.</p>
    @endif
  </section>
</div>
@endsection
