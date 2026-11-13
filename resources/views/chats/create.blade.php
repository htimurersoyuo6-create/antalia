@extends('layouts.app')
@section('title', $hotel->name.' ile özel mesajlaşma')
@section('content')
<main class="wrap chat-page"><p><a href="{{ route('hotels.show',$hotel->slug) }}">← {{ $hotel->name }} sayfasına dön</a></p><section class="detail-panel chat-box"><span class="detail-eyebrow">ÖZEL TESİS MESAJI</span><h1>{{ $hotel->name }} işletmesine sorun</h1><p>Mesajınız yalnızca siz ve bu tesisin yetkilileri tarafından görülebilir. Sohbetler silinmez; arşivlenir.</p>
<form method="post" action="{{ route('hotel-chats.start',$hotel->slug) }}" class="chat-form">@csrf
@guest<label>Adınız<input name="name" required maxlength="120" autocomplete="name" value="{{ old('name') }}"></label>@endguest
<label>Telefon (isteğe bağlı)<input name="phone" maxlength="30" autocomplete="tel" value="{{ old('phone') }}"></label>
<label>Mesajınız<textarea name="body" required minlength="2" maxlength="3000" rows="5" placeholder="Tesis, odalar veya hizmetler hakkında sorun..."></textarea></label>
@include('partials.captcha',['captchaKey'=>'hotel-chat'])
@if($errors->has('captcha'))<p class="error">{{ $errors->first('captcha') }}</p>@endif
<button class="btn" type="submit">Özel sohbeti başlat</button></form></section></main>
@endsection
