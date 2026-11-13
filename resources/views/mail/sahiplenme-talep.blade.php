@extends('mail.cerceve')
@section('icerik')
<p><b>Yeni işletme sahiplenme talebi</b></p>
<p>Otel: <b>{{ $claim->hotel->name }}</b></p>
<p>Talep eden: {{ $claim->name ?: $claim->user->name }} ({{ $claim->relationship }}) · {{ $claim->user->email }} · {{ $claim->contact_phone }}@if($claim->license_no) · Belge no: {{ $claim->license_no }}@endif</p>
<p style="white-space:pre-line;border-left:3px solid #f43f0b;padding-left:12px">{{ $claim->message }}</p>
<p><a href="{{ route('admin.claims.index') }}" style="display:inline-block;background:#f43f0b;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px">Yönetim panelinde incele</a></p>
@endsection
