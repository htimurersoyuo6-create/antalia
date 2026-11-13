@extends('layouts.admin')
@section('title', 'İş zekâsı panosu | Yönetim Paneli')
@section('content')
<div class="admin-stat-page">
  <header class="admin-stat-hero">
    <div>
      <span>YATIRIMCI GÖRÜNÜMÜ</span>
      <h1>İş zekâsı panosu</h1>
      <p>MRR, GMV, abonelik geliri, iptal oranı ve büyüme eğilimlerini tek ekranda izleyin.</p>
    </div>
    <div class="admin-stat-window">Veri: {{ $metrics['generated_at'] }}</div>
  </header>

  <p style="margin:0 0 14px"><a class="btn sm" href="{{ route('admin.bi.index', ['refresh' => 1]) }}">Metrikleri yenile</a></p>

  <section class="admin-stat-kpis" aria-label="İş zekâsı özetleri">
    <article><small>MRR</small><strong>{{ number_format($metrics['mrr'], 2, ',', '.') }} TL</strong><span>Yıllıklandırılmış: {{ number_format($metrics['arr'], 0, ',', '.') }} TL</span></article>
    <article><small>Son 30 gün GMV</small><strong>{{ number_format($metrics['gmv30'], 0, ',', '.') }} TL</strong><span>
      @if($metrics['gmv_change'] === null) Önceki dönem yok
      @else {{ $metrics['gmv_change'] >= 0 ? '+' : '' }}{{ number_format($metrics['gmv_change'], 1, ',', '.') }}% önceki 30 güne göre
      @endif
    </span></article>
    <article><small>Platform geliri (30g)</small><strong>{{ number_format($metrics['platform_revenue30'], 0, ',', '.') }} TL</strong><span>Komisyon {{ number_format($metrics['commission30'], 0, ',', '.') }} TL · Abonelik {{ number_format($metrics['subscription_revenue30'], 0, ',', '.') }} TL</span></article>
    <article><small>Rezervasyon (30g)</small><strong>{{ number_format($metrics['bookings30'], 0, ',', '.') }}</strong><span>Önceki dönem {{ number_format($metrics['bookings_prev'], 0, ',', '.') }}</span></article>
    <article><small>Aktif tesis</small><strong>{{ number_format($metrics['active_hotels'], 0, ',', '.') }}</strong><span>Son 30 günde {{ number_format($metrics['active_customers'], 0, ',', '.') }} yeni müşteri</span></article>
    <article><small>Abonelik iptal oranı</small><strong>%{{ number_format($metrics['churn_rate'], 1, ',', '.') }}</strong><span>Son 30 günde sona eren abonelikler</span></article>
    <article><small>Ortalama rezervasyon değeri</small><strong>{{ number_format($metrics['ltv'], 0, ',', '.') }} TL</strong><span>Ödenmiş rezervasyon ortalaması</span></article>
    <article><small>Pazaryeri GMV (30g)</small><strong>{{ number_format($metrics['marketplace_gmv30'], 0, ',', '.') }} TL</strong><span>Mağaza siparişleri</span></article>
  </section>

  <section class="cardbox" style="margin-top:18px">
    <h2>12 aylık GMV eğilimi</h2>
    <div style="display:flex;align-items:flex-end;gap:8px;height:180px;padding:12px 4px;border-bottom:1px solid #e5e7eb">
      @foreach($metrics['trend'] as $row)
        <div style="flex:1;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;height:100%" title="{{ $row['label'] }} · {{ number_format($row['gmv'], 2, ',', '.') }} TL · {{ $row['bookings'] }} rezervasyon">
          <div style="width:100%;background:linear-gradient(180deg,#1f6f4a,#3fa172);border-radius:4px 4px 0 0;height:{{ $row['height'] }}%"></div>
          <small style="margin-top:6px;font-size:10px;color:#6b7280;white-space:nowrap">{{ $row['label'] }}</small>
        </div>
      @endforeach
    </div>
  </section>

  <section class="cardbox" style="margin-top:18px">
    <h2>Son 30 günün en güçlü tesisleri</h2>
    @if($metrics['top_hotels']->isEmpty())
      <p>Bu dönemde rezervasyon verisi yok.</p>
    @else
      <table class="table">
        <thead><tr><th>#</th><th>Tesis</th><th>Rezervasyon</th><th>Ciro</th></tr></thead>
        <tbody>
        @foreach($metrics['top_hotels'] as $index => $hotel)
          <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $hotel['name'] }}</td>
            <td>{{ number_format($hotel['bookings'], 0, ',', '.') }}</td>
            <td>{{ number_format($hotel['revenue'], 0, ',', '.') }} TL</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    @endif
  </section>
</div>
@endsection
