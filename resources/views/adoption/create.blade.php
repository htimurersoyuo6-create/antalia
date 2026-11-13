@extends('layouts.app')
@section('title', $listing->exists ? 'Sahiplendirme ilanını düzenle' : 'Sahiplendirme ilanı ver')
@section('content')
<div class="wrap page">
  <form class="form cardbox adopt-form" method="post" enctype="multipart/form-data" action="{{ $listing->exists ? route('adoption.update',$listing) : route('adoption.store') }}">
    @csrf
    @if($listing->exists) @method('PUT') @endif
    <span class="eyebrow">GÜVENLİ SAHİPLENDİRME</span>
    <h1>{{ $listing->exists ? 'İlan bilgilerini güncelle' : 'Bir dosta yuva arayın' }}</h1>
    <p class="adopt-form-intro">{{ $listing->exists ? 'Düzenlediğiniz ilan, güvenliğiniz için yeniden incelemeye alınır.' : 'Bilgileri açık ve doğru paylaşın. İlanınız yayınlanmadan önce ekibimiz tarafından incelenir.' }}</p>
    <div class="adopt-form-grid">
      <div class="adopt-form-field"><label for="pet_name">Petin adı</label><input id="pet_name" name="pet_name" value="{{ old('pet_name',$listing->pet_name) }}" required maxlength="100">@error('pet_name')<span class="adopt-form-error">{{ $message }}</span>@enderror</div>
      <div class="adopt-form-field"><label for="species">Tür</label><select id="species" name="species" required><option value="cat" @selected(old('species',$listing->species)==='cat')>Kedi</option><option value="dog" @selected(old('species',$listing->species)==='dog')>Köpek</option><option value="other" @selected(old('species',$listing->species)==='other')>Diğer</option></select>@error('species')<span class="adopt-form-error">{{ $message }}</span>@enderror</div>
      <div class="adopt-form-field"><label for="breed">Irk</label><input id="breed" name="breed" value="{{ old('breed',$listing->breed) }}" maxlength="100"></div>
      <div class="adopt-form-field"><label for="age_months">Yaş (ay)</label><input id="age_months" name="age_months" type="number" min="0" max="600" value="{{ old('age_months',$listing->age_months) }}"></div>
      <div class="adopt-form-field"><label for="sex">Cinsiyet</label><select id="sex" name="sex"><option value="unknown" @selected(old('sex',$listing->sex)==='unknown')>Belirtilmedi</option><option value="female" @selected(old('sex',$listing->sex)==='female')>Dişi</option><option value="male" @selected(old('sex',$listing->sex)==='male')>Erkek</option></select></div>
      <div class="adopt-form-field"><label for="city">Şehir</label><input id="city" name="city" value="{{ old('city',$listing->city ?: 'Antalya') }}" required maxlength="100"></div>
      <div class="adopt-form-field"><label for="district">İlçe</label><input id="district" name="district" value="{{ old('district',$listing->district) }}" maxlength="100"></div>
      <div class="adopt-form-field full"><label class="chk"><input type="checkbox" name="vaccinated" value="1" @checked(old('vaccinated',$listing->vaccinated))> İlan sahibinin beyanına göre aşıları tamamlandı</label></div>
      <div class="adopt-form-field full"><label for="description">İlan açıklaması</label><textarea id="description" name="description" rows="7" minlength="40" maxlength="5000" required>{{ old('description',$listing->description) }}</textarea><p class="adopt-form-note">Karakterini, bakım ihtiyacını ve yeni yuvasında önemli olacak bilgileri yazın.</p>@error('description')<span class="adopt-form-error">{{ $message }}</span>@enderror</div>
      <div class="adopt-form-field full"><label for="photo">Petin fotoğrafı {{ $listing->exists ? '(isteğe bağlı)' : '(gerekli)' }}</label>
        @if($listing->photo_path)<div class="adopt-form-current-photo"><img src="{{ route('adoption.photo',$listing) }}" alt="Mevcut ilan fotoğrafı"></div><p class="adopt-form-note">Yeni bir fotoğraf seçmezseniz mevcut fotoğraf korunur.</p>@endif
        <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" @required(!$listing->exists)>
        <p class="adopt-form-note">JPG, PNG veya WebP; en fazla 5 MB. Fotoğrafınız herkese açık ilanda gösterilir.</p>@error('photo')<span class="adopt-form-error">{{ $message }}</span>@enderror</div>
      <div class="adopt-form-field full"><label for="contact_phone">İletişim telefonu (isteğe bağlı)</label><input id="contact_phone" name="contact_phone" type="tel" value="{{ old('contact_phone',$listing->contact_phone ?: auth()->user()->phone) }}" maxlength="30"><p class="adopt-form-note">Onaylanan ilanda bu numara ziyaretçiler tarafından görülebilir. Paylaşmak istemiyorsanız boş bırakın.</p>@error('contact_phone')<span class="adopt-form-error">{{ $message }}</span>@enderror</div>
    </div>
    @if(!$listing->exists) @include('partials.captcha',['captchaKey'=>'adoption']) @endif
    @if($errors->has('captcha'))<p class="adopt-form-error">{{ $errors->first('captcha') }}</p>@endif
    <div class="adopt-form-submit"><button class="btn" type="submit">{{ $listing->exists ? 'Kaydet ve yeniden incelemeye gönder' : 'İncelemeye gönder' }} <span aria-hidden="true">→</span></button><a class="btn ghost" href="{{ route('adoption.mine') }}">Vazgeç</a></div>
  </form>
</div>
@endsection
