@extends('layouts.app')
@section('title', 'Gelir ve Doluluk Raporları | Antalya Pet Otel')
@section('content')
<div class="wrap page owner-reports">
  <header class="owner-reports-hero"><nav aria-label="Konum"><a href="{{ route('panel.index') }}">İş Ortağı Paneli</a><span>›</span><span>Raporlar</span></nav><span class="owner-report-kicker">İŞLETME PERFORMANSI</span><h1>Gelir ve doluluk raporları</h1><p>Konaklama, tahsilat ve oda kullanımını seçtiğiniz tarihler için tek ekranda değerlendirin.</p></header>

  <form method="get" action="{{ route('panel.reports') }}" class="owner-report-filters">
    <div><span class="owner-report-kicker">RAPOR DÖNEMİ</span><strong>Tarih aralığını seçin</strong><small>En fazla 366 günlük aralık seçilebilir.</small></div>
    <label>Başlangıç tarihi<input type="date" name="from" value="{{ $from->toDateString() }}" required></label>
    <label>Bitiş tarihi<input type="date" name="to" value="{{ $to->toDateString() }}" required></label>
    <button class="btn" type="submit">Raporu güncelle <span aria-hidden="true">→</span></button>
  </form>

  @if($errors->any())<p class="hint" role="alert">{{ $errors->first() }}</p>@endif

  <div class="owner-report-stats">
    <article><span>◷</span><small>Konaklama</small><strong>{{ number_format($reservationCount,0,',','.') }}</strong><em>Seçilen tarihlerle kesişen</em></article>
    <article><span>₺</span><small>Rezervasyon değeri</small><strong>{{ number_format($bookingValue, 2, ',', '.') }} ₺</strong><em>Konaklama toplam tutarı</em></article>
    <article><span>↗</span><small>Kayıtlı tahsilat</small><strong>{{ number_format($paidValue, 2, ',', '.') }} ₺</strong><em>Sistemde ödendi görünen</em></article>
    <article><span>▦</span><small>Konaklama gecesi</small><strong>{{ number_format($bookedNights,0,',','.') }}</strong><em>{{ $roomCount }} kullanılabilir oda</em></article>
    <article><span>%</span><small>Tahmini doluluk</small><strong>{{ $occupancy === null ? '—' : number_format($occupancy, 1, ',', '.') . '%' }}</strong><em>{{ $periodDays }} günlük dönem</em></article>
  </div>

  <aside class="owner-report-explainer"><span aria-hidden="true">i</span><p>Doluluk, seçili dönemdeki onaylı ve tamamlanmış oda gecelerinin kullanılabilir oda kapasitesine oranıdır. Oda sayısı geçmişte değiştiyse bu oran yaklaşık değer verir. Tahsilat yalnızca sistemde “ödendi” görünen kayıtları kapsar.</p></aside>

  <section class="owner-report-trend" aria-labelledby="owner-report-trend-title">
    <header><div><span class="owner-report-kicker">REZERVASYON AKIŞI</span><h2 id="owner-report-trend-title">Aylık talep ve onay trendi</h2><p>Rezervasyonun oluşturulduğu aya göre; seçili tarih aralığındaki talepler.</p></div><div class="owner-trend-legend"><span><i></i>Tüm talepler</span><span><i></i>Onaylanan / tamamlanan</span></div></header>
    @if($monthlyTrend->sum('requested') === 0)
      <p class="hint">Seçilen dönemde henüz rezervasyon talebi yok.</p>
    @else
      <div class="owner-trend-chart" role="img" aria-label="Aylık rezervasyon talebi ve onay grafiği">
        @foreach($monthlyTrend as $month)
          <div class="owner-trend-month"><div class="owner-trend-bars"><span class="owner-trend-bar requests" style="height:{{ $month['request_height'] }}%" title="{{ $month['requested'] }} talep"></span><span class="owner-trend-bar booked" style="height:{{ $month['booked_height'] }}%" title="{{ $month['booked'] }} onaylı veya tamamlanmış"></span></div><b>{{ $month['requested'] }}</b><small>{{ $month['label'] }}</small></div>
        @endforeach
      </div>
    @endif
    <div class="owner-trend-statuses">
      @foreach(['pending'=>'Bekleyen','confirmed'=>'Onaylanan','completed'=>'Tamamlanan','cancelled'=>'İptal','rejected'=>'Reddedilen'] as $key=>$label)
        <span>{{ $label }} <b>{{ number_format((int)($bookingStatuses[$key] ?? 0),0,',','.') }}</b></span>
      @endforeach
    </div>
  </section>
  <style>
    .owner-report-trend{margin:22px 0;padding:24px;border:1px solid #e6e9e3;border-radius:20px;background:#fff;box-shadow:0 8px 24px #23352b08}.owner-report-trend>header{display:flex;justify-content:space-between;align-items:flex-start;gap:16px}.owner-report-trend h2{margin:5px 0;font-size:1.25rem;color:#26352d}.owner-report-trend p{margin:0;color:#728078;font-size:.86rem}.owner-trend-legend{display:flex;gap:14px;flex-wrap:wrap;color:#68766d;font-size:.75rem}.owner-trend-legend span{display:flex;align-items:center;gap:6px}.owner-trend-legend i{width:9px;height:9px;border-radius:50%;background:#f04a1c}.owner-trend-legend span+span i{background:#3e9071}.owner-trend-chart{display:grid;grid-template-columns:repeat(auto-fit,minmax(44px,1fr));gap:8px;align-items:end;min-height:170px;margin-top:22px;padding:10px 0 0;border-bottom:1px solid #e7ebe6}.owner-trend-month{display:grid;grid-template-rows:112px auto auto;justify-items:center;gap:5px;min-width:0}.owner-trend-bars{height:100%;width:100%;display:flex;align-items:end;justify-content:center;gap:4px;border-bottom:1px solid #edf0ec}.owner-trend-bar{display:block;width:min(17px,35%);min-height:3px;border-radius:5px 5px 0 0;background:#f04a1c}.owner-trend-bar.booked{background:#3e9071}.owner-trend-month>b{font-size:.76rem;color:#33443a}.owner-trend-month small{font-size:.67rem;color:#879188}.owner-trend-statuses{display:flex;gap:8px;flex-wrap:wrap;margin-top:18px}.owner-trend-statuses span{display:flex;gap:7px;align-items:center;padding:7px 10px;border-radius:999px;background:#f4f6f3;color:#68766d;font-size:.74rem}.owner-trend-statuses b{color:#26352d}@media(max-width:600px){.owner-report-trend{padding:17px}.owner-report-trend>header{display:grid}.owner-trend-chart{grid-template-columns:repeat(auto-fit,minmax(38px,1fr));gap:4px}.owner-trend-month small{font-size:.6rem}}
  </style>
  <section class="owner-report-table-card">
    <header><div><span class="owner-report-kicker">KAYIT DETAYI</span><h2>Dönemdeki rezervasyonlar</h2><p>Rezervasyon ve konaklama kayıtlarını tek tek inceleyin.</p></div><span class="owner-report-count">{{ number_format($reservationCount,0,',','.') }} kayıt</span></header>
    @if($reservations->isEmpty())
      <p class="hint">Bu tarih aralığında onaylı veya tamamlanmış konaklama yok.</p>
    @else
      <div class="owner-report-table-scroll"><table>
        <thead><tr><th>Tarih</th><th>Rezervasyon</th><th>Tesis / oda</th><th>Misafir / dost</th><th>Tutar</th><th>Durum</th></tr></thead>
        <tbody>
        @foreach($reservations as $reservation)
          <tr style="border-top:1px solid #e7e7e7">
            <td><span class="owner-report-date">{{ $reservation->check_in->format('d.m.Y') }}</span><small>{{ $reservation->check_out->format('d.m.Y') }} çıkış</small></td>
            <td><a class="owner-report-code" href="{{ route('panel.reservations.show', $reservation) }}">{{ $reservation->code }} <span aria-hidden="true">↗</span></a></td>
            <td><strong>{{ $reservation->hotel->name }}</strong><small>{{ $reservation->room->name }}</small></td>
            <td><strong>{{ $reservation->user->name }}</strong><small>{{ $reservation->pet->name }}</small></td>
            <td class="owner-report-amount">{{ number_format((float) $reservation->total_price, 2, ',', '.') }} ₺</td>
            <td><span class="owner-report-status {{ $reservation->status }}">{{ $reservation->status === 'completed' ? 'Tamamlandı' : 'Onaylandı' }}</span></td>
          </tr>
        @endforeach
        </tbody>
      </table></div>
      @if($reservationCount > $reservations->count())<p class="owner-report-limit">Tabloda ilk 100 kayıt gösteriliyor; üstteki özet seçilen dönemin tüm kayıtlarını kapsar.</p>@endif
    @endif
  </section>
</div>
@endsection
