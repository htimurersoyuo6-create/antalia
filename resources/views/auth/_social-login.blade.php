@php
    $socialProviders = [
        'google' => ['label' => 'Google ile devam et', 'icon' => 'G'],
        'apple' => ['label' => 'Apple ile devam et', 'icon' => '●'],
    ];
    $enabledSocialProviders = array_filter($socialProviders, fn ($item, $provider) => filled(config("services.$provider.client_id")) && filled(config("services.$provider.client_secret")), ARRAY_FILTER_USE_BOTH);
@endphp
@if(count($enabledSocialProviders))
  <div class="social-auth" aria-label="Sosyal hesapla giriş">
    @foreach($enabledSocialProviders as $provider => $item)
      <a class="social-auth-button" href="{{ route('social.redirect', $provider) }}"><span aria-hidden="true">{{ $item['icon'] }}</span>{{ $item['label'] }}</a>
    @endforeach
    <div class="social-auth-divider"><span>veya e-posta / telefon</span></div>
  </div>
@endif
