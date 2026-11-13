@extends('mail.cerceve')
@section('icerik')
<p>Merhaba {{ \Illuminate\Support\Str::before($res->user->name, ' ') }},</p>
<p><b>{{ $res->hotel->name }}</b> için rezervasyon talebiniz alındı. Otel talebinizi inceleyip onayladığında size yeniden e-posta göndereceğiz.</p>
@include('mail._ozet')
@if($res->customer_note)<p style="color:#6b7280">Notunuz: {{ $res->customer_note }}</p>@endif
<p><a href="{{ route('reservations.show', $res) }}" style="display:inline-block;background:#f43f0b;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px">Rezervasyonu görüntüle</a></p>
@endsection
