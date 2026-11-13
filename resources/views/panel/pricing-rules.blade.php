@extends('layouts.app')
@section('title', 'Dinamik fiyatlandırma')
@section('content')
<div class="wrap page">
  <div class="crumb"><a href="{{ route('panel.index') }}">Panel</a> › Dinamik fiyatlandırma</div>
  <h1>RevPAR / sezon fiyat kuralları</h1>
  @if(session('ok'))<div class="alert success">{{ session('ok') }}</div>@endif
  @if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif
  @if($hotels->count() > 1)
  <form method="get" class="form" style="max-width:420px;margin-bottom:16px">
    <label>Otel<select name="hotel" onchange="this.form.submit()">@foreach($hotels as $h)<option value="{{ $h->id }}" @selected($h->id===$hotel->id)>{{ $h->name }}</option>@endforeach</select></label>
  </form>
  @endif
  <section class="cardbox">
    <h2>Yeni kural · {{ $hotel->name }}</h2>
    <form method="post" action="{{ route('panel.pricing.store') }}" class="form">@csrf
      <input type="hidden" name="hotel_id" value="{{ $hotel->id }}">
      <div class="row2"><label>Başlangıç<input type="date" name="start_date" required></label><label>Bitiş<input type="date" name="end_date" required></label></div>
      <div class="row2"><label>Minimum gece<input type="number" name="min_nights" min="1" max="30" value="1"></label><label>Temel çarpan (%)<input type="number" name="base_multiplier_percent" min="50" max="300" value="100" required></label></div>
      <div class="row2"><label>Ek zam (%)<input type="number" name="surcharge_percent" min="-50" max="200" step="0.01" value="0"></label><label>Aktif<select name="is_active"><option value="1">Evet</option><option value="0">Hayır</option></select></label></div>
      <button class="btn">Kuralı kaydet</button>
    </form>
  </section>
  <section class="cardbox" style="margin-top:16px">
    <h2>Kurallar</h2>
    <table class="table"><tr><th>Dönem</th><th>Min. gece</th><th>Çarpan</th><th>Zam</th><th>Durum</th><th></th></tr>
      @forelse($rules as $rule)
      <tr><td>{{ $rule->start_date->format('d.m.Y') }} – {{ $rule->end_date->format('d.m.Y') }}</td><td>{{ $rule->min_nights }}</td><td>%{{ $rule->base_multiplier_percent }}</td><td>%{{ rtrim(rtrim(number_format((float)$rule->surcharge_percent,2,',','.'),'0'),',') }}</td><td>{{ $rule->is_active ? 'Aktif' : 'Pasif' }}</td><td><form method="post" action="{{ route('panel.pricing.destroy', $rule) }}">@csrf @method('DELETE')<button class="btn sm danger">Sil</button></form></td></tr>
      @empty
      <tr><td colspan="6">Kural yok. Varsayılan fiyatlar kullanılıyor.</td></tr>
      @endforelse
    </table>
  </section>
</div>
@endsection
