{{-- $res->records yuklu; $musteri: true ise onay dugmesi gorunur --}}
@if($res->records->isNotEmpty())
<h3 style="margin-top:22px">Giriş / çıkış tutanakları</h3>
@foreach($res->records as $rec)
<div class="cardbox">
  <b>{{ $rec->type_label }}</b>
  @if($rec->note)<p style="white-space:pre-line">{{ $rec->note }}</p>@endif
  <p class="hint">Otel imzası: {{ $rec->hotel_signed_at?->format('d.m.Y H:i') ?? '—' }} ·
    Misafir onayı: {{ $rec->customer_signed_at?->format('d.m.Y H:i') ?? 'bekleniyor' }}</p>
  @if($musteri && !$rec->customer_signed_at)
    <form method="post" action="{{ route('reservations.record.approve', [$res, $rec]) }}">@csrf<button class="btn sm">Tutanağı onaylıyorum</button></form>
  @endif
</div>
@endforeach
@endif
