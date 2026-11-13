@extends('mail.cerceve')
@section('icerik')
<p><b>Siteden yeni mesaj</b></p>
<p>{{ $msg->name }} · {{ $msg->email }}@if($msg->phone) · {{ $msg->phone }}@endif</p>
<p style="white-space:pre-line;border-left:3px solid #f43f0b;padding-left:12px">{{ $msg->message }}</p>
<p style="color:#6b7280">Yanıtlamak için bu e-postaya cevap verebilirsiniz.</p>
@endsection
