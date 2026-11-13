@extends('layouts.app')
@section('title','Canlı kamera | '.$res->hotel->name)
@section('content')
<div class="wrap page camera-page"><div class="crumb" style="padding-top:0"><a href="{{ route('reservations.show',$res) }}">Rezervasyon</a> › Canlı kamera</div>
<h1>{{ $res->pet->name }} · Güvenli canlı kamera</h1><p class="sub">{{ $res->hotel->name }} · Yalnızca bu hesaba ve rezervasyona tanımlanmış yayın.</p>
@if(!$cameraEnabled)
<section class="cardbox"><h2>Yayın şu anda kapalı</h2><p class="hint">Tesis canlı yayını geçici olarak durdurmuş olabilir. Ayrıntı için tesisle rezervasyon sohbetinden iletişime geçin.</p></section>
@elseif(!$authorized)
<section class="cardbox camera-code-card"><span aria-hidden="true">🔒</span><h2>Size özel erişim kodu</h2><p class="hint">Kodu tesisin bu rezervasyona özel sohbetinden alın. Kamera yayını sadece oturumunuz açıkken ve konaklama tarihleriniz arasında izlenebilir.</p>
<form method="post" action="{{ route('reservations.camera.verify', $res) }}">@csrf<label for="camera-access-code">Erişim kodu</label><input id="camera-access-code" name="access_code" autocomplete="one-time-code" maxlength="40" required placeholder="Örn. AB12-3456"><button class="btn" type="submit">Güvenli yayını aç</button></form>@error('access_code')<p class="error">{{ $message }}</p>@enderror</section>
@else
<div class="camera-frame"><iframe src="{{ $cameraUrl }}" title="{{ $res->hotel->name }} · {{ $res->pet->name }} canlı kamera" loading="eager" referrerpolicy="no-referrer" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen sandbox="allow-scripts allow-same-origin allow-presentation"></iframe></div>
<p class="hint">Yayına erişim rezervasyonunuza özeldir. İşletme yayını istediği zaman durdurabilir veya erişim kodunu yenileyebilir.</p>
@endif
<a class="btn ghost" href="{{ route('reservations.show',$res) }}">Rezervasyona dön</a></div>
<style>.camera-code-card{max-width:540px;margin:20px auto;padding:26px}.camera-code-card>span{display:grid;place-items:center;width:45px;height:45px;border-radius:14px;background:#fff0e8;font-size:22px}.camera-code-card h2{margin:14px 0 5px}.camera-code-card form{display:grid;gap:9px;margin-top:17px}.camera-code-card label{font-size:12px;font-weight:800}.camera-code-card input{min-height:45px;padding:0 13px;border:1px solid #e1e5de;border-radius:11px;font:inherit;letter-spacing:.08em}.camera-code-card .btn{justify-self:start}.camera-code-card .error{color:#b42318;font-size:12px}</style>
@endsection
