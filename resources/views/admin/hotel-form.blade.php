@extends('layouts.admin')
@php($editing = $hotel->exists)
@section('title', $editing ? 'Otel düzenle' : 'Otel ekle')
@section('heading', $editing ? 'Otel düzenle' : 'Yeni otel ekle')
@section('sub', 'Otel bilgilerini ve yayın durumunu yönetin.')
@section('actions')<a class="a-btn ghost" href="{{ route('admin.hotels.index') }}">← Otel listesi</a>
@endsection
@section('content')
<section class="a-card a-form-card"><form method="post" action="{{ $editing ? route('admin.hotels.update', $hotel) : route('admin.hotels.store') }}">
@csrf @if($editing) @method('PUT') @endif
<div class="a-card-h"><div><h2>{{ $editing ? 'Temel bilgiler' : 'Otel bilgileri' }}</h2><p>Yıldızlı alanlar zorunludur.</p></div></div>
<div class="a-form-grid">
<label class="a-field">Otel adı *<input name="name" required maxlength="160" value="{{ old('name',$hotel->name) }}"></label>
<label class="a-field">Adres adı (URL)<input name="slug" maxlength="190" value="{{ old('slug',$hotel->slug) }}" placeholder="Boş bırakılırsa addan oluşturulur"></label>
<label class="a-field">İşletme sahibi *<select name="owner_id" required><option value="">Kullanıcı seçin</option>@foreach($owners as $o)<option value="{{ $o->id }}" @selected((string)old('owner_id',$hotel->owner_id)===(string)$o->id)>{{ $o->name }} — {{ $o->email }} ({{ $o->role }})</option>@endforeach</select></label>
<label class="a-field">Şehir *<select name="city_id" required><option value="">Şehir seçin</option>@foreach($cities as $c)<option value="{{ $c->id }}" @selected((string)old('city_id',$hotel->city_id)===(string)$c->id)>{{ $c->name }}</option>@endforeach</select></label>
<label class="a-field">İlçe<select name="district_id"><option value="">İlçe seçin</option>@foreach($cities as $c)@foreach($c->districts as $d)<option value="{{ $d->id }}" data-city="{{ $c->id }}" @selected((string)old('district_id',$hotel->district_id)===(string)$d->id)>{{ $c->name }} / {{ $d->name }}</option>@endforeach @endforeach</select></label>
<label class="a-field">Telefon<input name="phone" maxlength="30" value="{{ old('phone',$hotel->phone) }}"></label>
<label class="a-field wide">Açık adres<input name="address" maxlength="255" value="{{ old('address',$hotel->address) }}"></label>
<label class="a-field">Ruhsat numarası<input name="license_no" maxlength="80" value="{{ old('license_no',$hotel->license_no) }}"></label>
<label class="a-field">Komisyon oranı (%)<input type="number" name="commission_rate" min="0" max="100" step="0.01" value="{{ old('commission_rate',$hotel->commission_rate) }}"></label>
<label class="a-field">Yayın durumu<select name="status" required>@foreach(['pending'=>'Onay bekliyor','active'=>'Yayında','suspended'=>'Askıda / arşivde'] as $k=>$v)<option value="{{ $k }}" @selected(old('status',$hotel->status ?: 'pending')===$k)>{{ $v }}</option>@endforeach</select></label>
<label class="a-field wide">Açıklama<textarea name="description" rows="5">{{ old('description',$hotel->description) }}</textarea></label>
<label class="a-field wide">İşletme politikaları<textarea name="policies" rows="4">{{ old('policies',$hotel->policies) }}</textarea></label>
</div>
<div class="a-check-grid"><label><input type="checkbox" name="accepts_dogs" value="1" @checked(old('accepts_dogs',$hotel->exists ? $hotel->accepts_dogs : true))> Köpek kabul eder</label><label><input type="checkbox" name="accepts_cats" value="1" @checked(old('accepts_cats',$hotel->accepts_cats))> Kedi kabul eder</label><label><input type="checkbox" name="has_daycare" value="1" @checked(old('has_daycare',$hotel->has_daycare))> Gündüz bakım hizmeti</label><label><input type="checkbox" name="verify_license" value="1" @checked(old('verify_license',(bool)$hotel->license_verified_at))> Ruhsatı doğrulandı</label></div>
<div class="a-form-footer"><a class="a-btn ghost" href="{{ route('admin.hotels.index') }}">Vazgeç</a><button class="a-btn">{{ $editing ? 'Değişiklikleri kaydet' : 'Oteli oluştur' }}</button></div>
</form></section>
@if($editing)
<section class="a-card a-form-card" style="margin-top:18px"><form method="post" action="{{ route('admin.hotels.modules',$hotel) }}">
@csrf @method('PATCH')
<div class="a-card-h"><div><h2>Tesis özellik modülleri</h2><p>Bu tesiste özellikleri ayrı açıp kapatın. “Genel ayarı kullan” seçeneği site genelindeki modül anahtarını izler.</p></div></div>
<div class="a-form-grid">
@foreach($modules as $key=>$module)
<label class="a-field">{{ $module['title'] }}<small>{{ $module['description'] }}</small><select name="modules[{{ $key }}]">
<option value="inherit" @selected(!isset($hotelModules[$key]))>Genel ayarı kullan ({{ $module['enabled'] ? 'açık' : 'kapalı' }})</option>
<option value="on" @selected(isset($hotelModules[$key]) && $hotelModules[$key]->enabled)>Bu tesiste açık</option>
<option value="off" @selected(isset($hotelModules[$key]) && !$hotelModules[$key]->enabled)>Bu tesiste kapalı</option>
</select></label>
@endforeach
</div>
<div class="a-form-footer"><button class="a-btn">Modül yetkilerini kaydet</button></div>
</form></section>
@endif
<script>
const city=document.querySelector('[name="city_id"]'), district=document.querySelector('[name="district_id"]');
function filterDistricts(){for(const o of district.options){if(!o.value)continue;const show=o.dataset.city===city.value;o.hidden=!show;if(!show&&o.selected)district.value='';}}
city.addEventListener('change',filterDistricts);filterDistricts();
</script>
@endsection
