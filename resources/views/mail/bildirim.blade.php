@extends('mail.cerceve')
@section('icerik')
<p><b>{{ $baslik }}</b></p>
<p style="white-space:pre-line;border-left:3px solid #f43f0b;padding-left:12px">{{ $metin }}</p>
<p><a href="{{ $link }}" style="display:inline-block;background:#f43f0b;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px">{{ $linkEtiket }}</a></p>
@endsection
