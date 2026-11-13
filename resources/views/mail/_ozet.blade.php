<table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:8px;margin:14px 0;font-size:14px">
<tr><td style="color:#6b7280;width:38%">Rezervasyon kodu</td><td><b>{{ $res->code }}</b></td></tr>
<tr><td style="color:#6b7280">Otel</td><td>{{ $res->hotel->name }}</td></tr>
<tr><td style="color:#6b7280">Oda tipi</td><td>{{ $res->room->name ?? '—' }}</td></tr>
<tr><td style="color:#6b7280">Evcil hayvan</td><td>{{ $res->pet->name ?? '—' }}</td></tr>
<tr><td style="color:#6b7280">Giriş – Çıkış</td><td>{{ $res->check_in->format('d.m.Y') }} – {{ $res->check_out->format('d.m.Y') }} ({{ $res->nights }} gece)</td></tr>
@if($res->extras->isNotEmpty())<tr><td style="color:#6b7280">Ek hizmetler</td><td>@foreach($res->extras as $ex){{ $ex->name }}@if($ex->quantity > 1) × {{ $ex->quantity }}@endif ({{ number_format($ex->total, 0, ',', '.') }} ₺)@if(!$loop->last), @endif @endforeach</td></tr>@endif
@if((float) $res->discount_total > 0)<tr><td style="color:#6b7280">Kampanya indirimi</td><td>−{{ number_format((float) $res->discount_total, 0, ',', '.') }} ₺</td></tr>@endif
<tr><td style="color:#6b7280">Toplam</td><td><b>{{ number_format((float) $res->total_price, 0, ',', '.') }} ₺</b> (ödeme otelde)</td></tr>
</table>
