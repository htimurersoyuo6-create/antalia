@extends('layouts.app')
@section('title', 'Ek hizmetler · '.$reservation->code)
@section('content')
<div class="wrap page">
  <div class="crumb"><a href="{{ route('reservations.show', $reservation) }}">{{ $reservation->code }}</a> › Ek hizmetler</div>
  <h1>Ek hizmetler</h1>

  @if(session('ok'))<div class="alert success">{{ session('ok') }}</div>@endif
  @if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif

  <section class="cardbox">
    <h2>Evcil hayvanlar</h2>
    <p class="hint">Bu rezervasyona en fazla {{ \App\Services\MultiPetService::MAX_PETS }} evcil hayvan ekleyebilirsiniz. Ek hayvan ücreti: {{ number_format((float)$reservation->hotel->extra_pet_fee, 2, ',', '.') }} TL</p>

    @if($links->isEmpty())
      <p>Henüz evcil hayvan eklenmedi.</p>
    @else
      <table class="table">
        <thead><tr><th>Ad</th><th>Tür</th><th>Ücret</th><th></th></tr></thead>
        <tbody>
        @foreach($links as $link)
          <tr>
            <td>{{ $link->pet?->name }}</td>
            <td>{{ $link->pet?->species }}</td>
            <td>{{ number_format((float)$link->fee, 2, ',', '.') }} TL</td>
            <td>
              <form method="post" action="{{ route('reservations.extras.pets.remove', [$reservation, $link]) }}">@csrf @method('DELETE')
                <button class="btn sm ghost">Çıkar</button>
              </form>
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    @endif

    @if($pets->isNotEmpty())
      <form method="post" action="{{ route('reservations.extras.pets.store', $reservation) }}" class="form" style="max-width:420px;margin-top:12px">@csrf
        <label>Evcil hayvan<select name="pet_id" required>@foreach($pets as $pet)<option value="{{ $pet->id }}">{{ $pet->name }} ({{ $pet->species }})</option>@endforeach</select></label>
        <button class="btn">Rezervasyona ekle</button>
      </form>
    @else
      <p class="hint">Önce <a href="{{ route('pets.create') }}">evcil hayvan profili</a> oluşturun.</p>
    @endif
  </section>

  <section class="cardbox" style="margin-top:16px">
    <h2>Geç çıkış</h2>
    @php($status = $reservation->late_checkout_status ?? 'none')
    <p>
      Durum:
      <b>
      @switch($status)
        @case('requested') Değerlendirmede @break
        @case('approved') Onaylandı — {{ $reservation->late_checkout_until?->format('d.m.Y H:i') }} @break
        @case('declined') Reddedildi @break
        @default Talep edilmedi
      @endswitch
      </b>
      @if($status === 'approved' && $reservation->late_checkout_fee)
        · Ücret: {{ number_format((float)$reservation->late_checkout_fee, 2, ',', '.') }} TL
      @endif
    </p>

    @if($status === 'none' && (int)$reservation->hotel->late_checkout_hours > 0)
      <form method="post" action="{{ route('reservations.extras.late-checkout', $reservation) }}" class="form" style="max-width:420px">@csrf
        <label>Kaç saat?<select name="hours">@for($i=1;$i<=min(6,(int)$reservation->hotel->late_checkout_hours);$i++)<option value="{{ $i }}">{{ $i }} saat</option>@endfor</select></label>
        <p class="hint">Ücret: {{ number_format((float)$reservation->hotel->late_checkout_fee, 2, ',', '.') }} TL (onay sonrası hesaba eklenir)</p>
        <button class="btn">Geç çıkış talep et</button>
      </form>
    @elseif((int)$reservation->hotel->late_checkout_hours === 0)
      <p class="hint">Tesis geç çıkış hizmeti sunmuyor.</p>
    @endif
  </section>
</div>
@endsection
