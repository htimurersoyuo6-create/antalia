@php
    $tip = $h->turEtiketi();
    $sadeceKedi = $tip === 'kedi';
    $photoUrl = $h->primaryPhotoUrl();
    $hasPhoto = $photoUrl !== null;
    $temsil = $h->hasCoverImage() && str_contains((string) $h->cover_path, 'cover-generated.');
    $rozet = ['kedi' => 'Kedi Oteli', 'kopek' => 'Köpek Oteli', 'ikisi' => 'Kedi ve Köpek', 'diger' => 'Pet Oteli'][$tip];
    $kayitli = $isFavorite ?? false;

    $webpUrl = null;
    if ($h->cover_path) {
        $w = preg_replace('~\.jpe?g$~i', '.webp', $h->cover_path);
        if ($w !== $h->cover_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($w)) {
            $webpUrl = asset('storage/' . $w);
        }
    } else {
        $yz = public_path('img/otel/' . $h->slug . '.webp');
        if (is_file($yz)) {
            $webpUrl = asset('img/otel/' . $h->slug . '.webp');
        }
    }

    $amenities = json_decode((string) $h->amenities, true);
    $amenities = is_array($amenities)
        ? $amenities
        : array_filter(array_map('trim', explode(',', (string) $h->amenities)));
    $amenities = array_slice(array_values(array_filter($amenities)), 0, 3);
    $amenities = array_map(
        static fn ($amenity) => \Illuminate\Support\Str::lower(trim((string) $amenity)) === 'özel oda' ? 'Özel oda' : $amenity,
        $amenities
    );
@endphp
<article class="apt-lcard">
  @auth
    <form method="post" action="{{ route('favorites.toggle', $h->slug) }}" class="apt-lcard__favform">
      @csrf
      <button class="apt-lcard__fav {{ $kayitli ? 'is-saved' : '' }}" type="submit"
              aria-pressed="{{ $kayitli ? 'true' : 'false' }}"
              aria-label="{{ $kayitli ? 'Favorilerimden çıkar' : 'Favorilerime ekle' }}">
        <span aria-hidden="true">{{ $kayitli ? '♥' : '♡' }}</span>
      </button>
    </form>
  @else
    <a class="apt-lcard__fav" href="{{ route('login') }}" aria-label="Favorilere eklemek için giriş yapın">
      <span aria-hidden="true">♡</span>
    </a>
  @endauth

  <a class="apt-lcard__media" href="{{ route('hotels.show', $h->slug) }}"
     aria-label="{{ $h->name }} detay sayfası">
    @if($hasPhoto)
      <picture>
        @if($webpUrl)<source srcset="{{ $webpUrl }}" type="image/webp">@endif
        <img src="{{ $photoUrl }}"
             alt="{{ $h->name }} - {{ $h->district?->name ?? 'Antalya' }} pet otel{{ $temsil ? ', temsili kapak görseli' : '' }}"
             fetchpriority="low" decoding="async" @if(isset($loop) && $loop->index >= 3) loading="lazy" @endif width="600" height="450">
      </picture>
    @else
      <span class="apt-lcard__placeholder" role="img" aria-label="{{ $h->name }} için tesis fotoğrafı henüz paylaşılmadı"><x-ui-icon name="camera" size="34" /></span>
    @endif
    <span class="apt-lcard__kind">{{ $rozet }}</span>
    @if($temsil)<span class="apt-lcard__flag">Temsili görsel</span>@endif
    @if($h->rooms_min_base_price)
      <span class="apt-lcard__price">{{ number_format($h->rooms_min_base_price, 0, ',', '.') }} ₺<small>/gece</small></span>
    @endif
  </a>

  <div class="apt-lcard__body">
    <h3 class="apt-lcard__title">
      <x-ui-icon name="paw" size="14" class="apt-lcard__paw" />
      <a href="{{ route('hotels.show', $h->slug) }}">{{ $h->name }}</a>
    </h3>

    <p class="apt-lcard__meta">
      <span><x-ui-icon name="pin" size="12" /> {{ $h->district?->name ?? 'Antalya' }}</span>
      @if($h->rating_count)
        <span class="apt-lcard__rate">★ {{ number_format($h->rating_avg, 1) }} ({{ $h->rating_count }})</span>
      @endif
      <span aria-label="Kabul edilen hayvan türleri">
        @if(in_array($tip, ['kopek', 'ikisi'], true))<x-ui-icon name="dog" size="12" /> Köpek @endif
        @if(in_array($tip, ['kedi', 'ikisi'], true))<x-ui-icon name="cat" size="12" /> Kedi @endif
      </span>
    </p>

    <p class="apt-lcard__verification">
      @if($h->license_verified_at)
        <span class="apt-verification-badge is-verified" aria-label="İşletme ruhsat bilgisi platform tarafından doğrulandı">✓ Ruhsat doğrulandı</span>
      @else
        <span class="apt-verification-badge is-unverified" aria-label="İşletme ruhsat bilgisi platform tarafından doğrulanmadı">Ruhsat kontrol edilmedi</span>
      @endif
    </p>

    @if($h->description)
      <p class="apt-lcard__desc">{{ \Illuminate\Support\Str::limit(strip_tags($h->description), 105) }}</p>
    @endif

    @if($amenities)
      <div class="apt-lcard__amenities">
        @foreach($amenities as $amenity)<span>{{ $amenity }}</span>@endforeach
      </div>
    @endif
  </div>

  <div class="apt-lcard__foot">
    <a class="apt-lcard__cta" href="{{ route('hotels.show', $h->slug) }}">Detayları incele</a>
    @if($h->phone)
      <a class="apt-lcard__quick" data-hotel-slug="{{ $h->slug }}" target="_blank" rel="noopener"
         href="https://wa.me/{{ \App\Support\PhoneNumber::whatsapp($h->phone) }}?text={{ rawurlencode('Merhaba ' . $h->name . ', tesisiniz hakkında bilgi almak istiyorum.') }}"
         aria-label="{{ $h->name }} işletmesine WhatsApp ile yazın">
        <x-ui-icon name="whatsapp" size="18" />
      </a>
      <a class="apt-lcard__quick" data-hotel-slug="{{ $h->slug }}" data-hotel-phone-call
         href="tel:{{ preg_replace('/[^0-9+]/', '', $h->phone) }}"
         aria-label="{{ $h->name }} tesisini ara">☎</a>
    @endif
  </div>
</article>
