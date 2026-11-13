@extends('mail.cerceve')
@section('icerik')
<p><b>{{ $baslik }}</b></p>
<p>Müşteri: {{ $res->user->name }} · {{ $res->user->email }}@if($res->user->phone) · {{ $res->user->phone }}@endif</p>
@include('mail._ozet')
@if($res->customer_note)<p style="color:#6b7280">Müşteri notu: {{ $res->customer_note }}</p>@endif
<p><a href="{{ route('panel.reservations') }}" style="display:inline-block;background:#f43f0b;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px">Panelde aç</a></p>
@endsection
