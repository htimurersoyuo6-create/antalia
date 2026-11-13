@extends('mail.cerceve')
@section('icerik')
<p>Merhaba {{ \Illuminate\Support\Str::before($claim->name ?: $claim->user->name, ' ') }},</p>
@if($claim->status === 'approved')
<p><b>{{ $claim->hotel->name }}</b> için sahiplenme talebiniz onaylandı. Artık işletme panelinden tesis bilgilerini, odaları, fiyatları ve rezervasyonları yönetebilirsiniz.</p>
<p><a href="{{ route('panel.index') }}" style="display:inline-block;background:#f43f0b;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px">İşletme paneline git</a></p>
@else
<p><b>{{ $claim->hotel->name }}</b> için sahiplenme talebiniz şu an onaylanamadı.</p>
@endif
@if($claim->admin_note)<p style="color:#6b7280">Not: {{ $claim->admin_note }}</p>@endif
@endsection
