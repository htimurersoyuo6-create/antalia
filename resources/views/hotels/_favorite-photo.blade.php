@if(auth()->check())
  <form class="hotel-photo-favorite-form" method="post" action="{{ route('favorites.toggle', $hotel->slug) }}">
    @csrf
    <button class="hotel-photo-favorite {{ $isFavorite ? 'is-saved' : '' }}" type="submit" aria-label="{{ $isFavorite ? 'Favorilerimden çıkar' : 'Favorilerime ekle' }}" aria-pressed="{{ $isFavorite ? 'true' : 'false' }}" title="{{ $isFavorite ? 'Favorilerimden çıkar' : 'Favorilerime ekle' }}"><x-ui-icon name="heart" size="20" /></button>
  </form>
@else
  <a class="hotel-photo-favorite" href="{{ route('login') }}" aria-label="Favorilere eklemek için giriş yap" title="Favorilere eklemek için giriş yap"><x-ui-icon name="heart" size="20" /></a>
@endif
