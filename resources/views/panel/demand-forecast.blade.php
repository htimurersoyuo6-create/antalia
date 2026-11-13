@extends('layouts.app')
@section('title', 'Talep tahmini')
@section('content')
<div class="wrap page">
  <div class="crumb"><a href="{{ route('panel.index') }}">Panel</a> › Talep tahmini</div>
  <h1>AI destekli talep tahmini</h1>

  @if(session('ok'))<div class="alert success">{{ session('ok') }}</div>@endif
  @if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif

  @if($hotels->count() > 1)
    <form method="get" class="form" style="max-width:420px;margin-bottom:16px">
      <label>Otel
        <select name="hotel" onchange="this.form.submit()">
          @foreach($hotels as $h)
            <option value="{{ $h->id }}" @selected($h->id === $hotel->id)>{{ $h->name }}</option>
          @endforeach
        </select>
      </label>
    </form>
  @endif

  <section class="cardbox">
    <h2>{{ $hotel->name }} · önümüzdeki 30 gün</h2>
    <p class="hint">
      Doluluk, son 7 günlük rezervasyon hızı ve geçmiş sezonluk yoğunluk birlikte değerlendirilir.
      Önerilen çarpan 1,00 = mevcut taban fiyat.
      @if($lastGenerated) Son hesaplama: {{ \Illuminate\Support\Carbon::parse($lastGenerated)->format('d.m.Y H:i') }} @endif
    </p>

    <form method="post" action="{{ route('panel.forecast.recalculate') }}" class="form" style="max-width:420px;margin:12px 0">@csrf
      <input type="hidden" name="hotel_id" value="{{ $hotel->id }}">
      <div class="row2">
        <label>Ufuk (gün)<input type="number" name="days" min="7" max="180" value="90"></label>
        <label>&nbsp;<button class="btn">Yeniden hesapla</button></label>
      </div>
    </form>

    @if($forecasts->isEmpty())
      <p>Tahmin üretilemedi.</p>
    @else
      <table class="table">
        <thead><tr><th>Tarih</th><th>Doluluk</th><th>Rezervasyon hızı</th><th>Sezon indeksi</th><th>Önerilen çarpan</th><th>Güven</th><th></th></tr></thead>
        <tbody>
        @foreach($forecasts as $forecast)
          <tr>
            <td>{{ $forecast->forecast_date->format('d.m.Y') }} · {{ ['Pzt','Sal','Çar','Per','Cum','Cmt','Paz'][$forecast->forecast_date->dayOfWeekIso - 1] }}</td>
            <td>%{{ number_format((float)$forecast->occupancy_rate, 1, ',', '.') }}</td>
            <td>{{ number_format((float)$forecast->booking_pace, 2, ',', '.') }}x</td>
            <td>{{ number_format((float)$forecast->season_index, 2, ',', '.') }}</td>
            <td><b>{{ number_format((float)$forecast->suggested_multiplier, 2, ',', '.') }}x</b></td>
            <td>%{{ number_format((float)$forecast->confidence * 100, 0, ',', '.') }}</td>
            <td>
              <form method="post" action="{{ route('panel.forecast.apply', $forecast) }}">@csrf
                <button class="btn sm ghost">Fiyata uygula</button>
              </form>
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
      <p class="hint">“Fiyata uygula” ilgili gün için %50–300 aralığında tek günlük bir fiyat kuralı oluşturur.</p>
    @endif
  </section>
</div>
@endsection
