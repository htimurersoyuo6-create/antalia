@extends('layouts.app')
@section('title', 'Faturalarım | Antalya Pet Otel')
@section('content')
<div class="wrap page invoice-page">
  <header class="invoice-page-hero"><span class="invoice-kicker">ÖDEME BELGELERİNİZ</span><h1>Faturalarım</h1><p>Ödemesi alınmış ve onaylanmış konaklamalarınıza ait resmî belgeler burada güvenle saklanır.</p></header>
  @if($invoices->isEmpty())
    <section class="invoice-empty"><span aria-hidden="true">▤</span><h2>Henüz faturanız yok</h2><p>Ödemesi alınmış ve onaylanmış rezervasyonlarınıza ait faturalar burada görünür.</p><a class="btn ghost" href="{{ route('reservations.index') }}">Rezervasyonlarıma dön</a></section>
  @else
    <div class="invoice-list">
      @foreach($invoices as $invoice)
        <article class="invoice-list-card">
          <span class="invoice-list-icon" aria-hidden="true">▤</span>
          <div class="invoice-list-main"><span class="invoice-kicker">{{ $invoice->invoice_type === 'e_fatura' ? 'E‑FATURA' : ($invoice->invoice_type === 'e_arsiv' ? 'E‑ARŞİV FATURA' : 'FATURA') }}</span><h2>{{ $invoice->reservation->hotel->name ?? 'Pet otel' }}</h2><p>Rezervasyon {{ $invoice->reservation->code }} · {{ number_format((float)$invoice->amount,2,',','.') }} ₺</p>
            @if($invoice->isIssued())<small>{{ $invoice->invoice_number }} · {{ $invoice->issued_at?->format('d.m.Y') }}</small>@else<small>İşletmenin resmî belgeyi yüklemesi bekleniyor</small>@endif
          </div>
          <span class="invoice-status {{ $invoice->isIssued() ? 'issued' : 'pending' }}">{{ $invoice->isIssued() ? 'Belge hazır' : 'Hazırlanıyor' }}</span>
          @if($invoice->isIssued())<a class="invoice-download" href="{{ route('invoices.download',$invoice) }}">İndir <span aria-hidden="true">↓</span></a>@endif
          <a class="invoice-reservation-link" href="{{ route('reservations.show',$invoice->reservation) }}">Rezervasyon ayrıntısı →</a>
        </article>
      @endforeach
    </div>
    <div class="invoice-pagination">{{ $invoices->links() }}</div>
  @endif
</div>
@endsection
