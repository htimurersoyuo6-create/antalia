@extends('mail.cerceve')
@section('icerik')
<p>Merhaba {{ \Illuminate\Support\Str::before($res->user->name, ' ') }},</p>
@if($res->status === 'confirmed')
<p><b>Rezervasyonunuz onaylandı.</b> Giriş gününde evcil dostunuzla birlikte otele gelebilirsiniz.</p>
@elseif($res->status === 'rejected')
<p><b>Üzgünüz, otel bu talebi onaylayamadı.</b> Başka tarih veya başka bir otel için yeniden deneyebilirsiniz.</p>
@elseif($res->status === 'completed')
<p><b>Konaklamanız tamamlandı.</b> Deneyiminizi değerlendirerek diğer pet sahiplerine yardımcı olabilirsiniz.</p>
@else
<p>Rezervasyon durumunuz: <b>{{ $etiket }}</b></p>
@endif
@include('mail._ozet')
@if($res->hotel_note)<p style="color:#6b7280">Otelin notu: {{ $res->hotel_note }}</p>@endif
<p><a href="{{ route('reservations.show', $res) }}" style="display:inline-block;background:#f43f0b;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px">Rezervasyonu görüntüle</a></p>
@endsection
