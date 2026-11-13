@extends('layouts.app')
@section('title', 'Başvuru durumunu görüntüleyin | Antalya Pet Otel')
@section('content')
<main class="wrap partner-listing-page">
  <section class="partner-listing-card partner-login-gate" role="status">
    <span class="partner-eyebrow"><i></i> İŞLETME BAŞVURULARI</span>
    <h1>Başvurularınızı görmek için giriş yapın</h1>
    <p class="partner-listing-intro">Başvuru durumu yalnızca işletme hesabınızla görüntülenebilir. Giriş yaptıktan sonra bu sayfaya geri döneceksiniz.</p>
    <a class="partner-submit" href="{{ route('login', ['next' => 'tesis-basvurularim']) }}">İşletme hesabımla giriş yap <span aria-hidden="true">→</span></a>
    @if($errors->any())<div class="partner-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if(session('ok'))<div class="partner-listing-success" role="status"><span>{{ session('ok') }}</span></div>@endif
    <section class="partner-recovery-box"><h2>Takip bağlantınız kayboldu mu?</h2><p>Başvuruda kullandığınız e-posta adresine yeni bir özel bağlantı gönderelim.</p><form method="post" action="{{ route('partner.listing.recover') }}" class="partner-recovery-form">@csrf<label>E-posta adresi<input type="email" name="email" value="{{ old('email') }}" maxlength="160" autocomplete="email" required></label><div class="partner-listing-trap" aria-hidden="true"><label>Web sitesi<input name="company_website" tabindex="-1" autocomplete="off"></label></div>@include('partials.captcha',['captchaKey'=>'listing-status-recovery'])<button class="partner-submit" type="submit">Takip bağlantısını e-postama gönder <span aria-hidden="true">→</span></button></form></section>
    <p class="partner-listing-footnote">Henüz işletme hesabınız yoksa <a href="{{ route('partner.create') }}">işletme hesabı oluşturabilirsiniz</a>.</p>
  </section>
</main>
@endsection
