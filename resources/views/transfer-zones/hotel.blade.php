@extends('layouts.app')
@section('title',$hotel->name.' · transfer bölge tarifeleri')
@section('content')
<main class="wrap page" style="max-width:900px;margin:auto;padding:32px 16px"><h1>{{ $hotel->name }} · Transfer tarifeleri</h1>
@if(session('ok'))<p role="status">{{ session('ok') }}</p>@endif @if($errors->any())<div role="alert">{{ $errors->first() }}</div>@endif
<p>Bu tesis tarifeleri genel tarifelerin önüne geçer. Bölge adını müşterinin adresinde yazdığı şekilde kullanın.</p>
<form method="post" action="{{ route('panel.transfer-zones.store',$hotel) }}" style="display:flex;flex-wrap:wrap;gap:12px;margin:20px 0;padding:16px;border:1px solid #ddd;border-radius:12px">@csrf
<label>Başlangıç bölgesi<input name="from_zone" required maxlength="120" placeholder="Muratpaşa"></label><label>Varış bölgesi<input name="to_zone" required maxlength="120" placeholder="Lara"></label><label>Tek yön ücret (₺)<input name="fee" type="number" min="0" max="100000" step="0.01" required></label><label><input type="checkbox" name="is_active" value="1" checked> Aktif</label><button type="submit">Tarife kaydet</button></form>
<table style="width:100%"><thead><tr><th>Başlangıç → varış</th><th>Ücret</th><th>Durum</th><th></th></tr></thead><tbody>@forelse($rates as $rate)<tr><td>{{ $rate->from_zone }} → {{ $rate->to_zone }}</td><td>{{ number_format((float)$rate->fee,2,',','.') }} ₺</td><td>{{ $rate->is_active?'Aktif':'Kapalı' }}</td><td><form method="post" action="{{ route('panel.transfer-zones.destroy',[$hotel,$rate]) }}" onsubmit="return confirm('Bu tarife silinsin mi?')">@csrf @method('DELETE')<button>Sil</button></form></td></tr>@empty<tr><td colspan="4">Henüz tesis tarifesi eklenmedi.</td></tr>@endforelse</tbody></table></main>
@endsection
