@extends('layouts.app')
@section('title', 'Destek Fonu · '.$pet->name)
@section('content')
<div class="wrap page">
  <div class="crumb"><a href="{{ route('pets.show', $pet) }}">{{ $pet->name }}</a> › Destek Fonu</div>
  <h1>{{ $pet->name }} için destek fonu</h1>

  @if(session('ok'))<div class="alert success">{{ session('ok') }}</div>@endif
  @if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif

  <section class="cardbox">
    <h2>Bakiye</h2>
    <p style="font-size:28px;font-weight:700;margin:0 0 8px">{{ number_format($balanceCents / 100, 2, ',', '.') }} TL</p>
    <p style="margin:0;color:#666">Bu bakiye, sahiplendirme sonrası ilk konaklama masraflarında kullanılabilir.</p>
  </section>

  <section class="cardbox" style="margin-top:16px">
    <h2>Fona katkı ekle</h2>
    <form method="post" action="{{ route('pets.fund.store', $pet) }}" class="form">@csrf
      <div class="row2">
        <label>Tutar (TL)<input type="number" name="amount" step="0.01" min="1" max="100000" required></label>
        <label>Durum<select name="status"><option value="confirmed">Onaylı (tahsil edildi)</option><option value="pending">Bekliyor</option></select></label>
      </div>
      <label>Not<input type="text" name="note" maxlength="200" placeholder="Örn. sahiplendirme hediyesi"></label>
      <button class="btn">Kaydet</button>
    </form>
  </section>

  <section class="cardbox" style="margin-top:16px">
    <h2>Hareketler</h2>
    @if($entries->isEmpty())
      <p>Henüz hareket yok.</p>
    @else
      <table class="table">
        <thead><tr><th>Tarih</th><th>Tür</th><th>Tutar</th><th>Durum</th><th>Not</th></tr></thead>
        <tbody>
        @foreach($entries as $entry)
          <tr>
            <td>{{ $entry->created_at?->format('d.m.Y H:i') }}</td>
            <td>{{ \App\Models\PetFundEntry::TYPES[$entry->type] ?? $entry->type }}</td>
            <td>{{ number_format($entry->amount_cents / 100, 2, ',', '.') }} TL</td>
            <td>{{ $entry->status === 'confirmed' ? 'Onaylı' : 'Bekliyor' }}</td>
            <td>{{ $entry->note }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    @endif
  </section>
</div>
@endsection
