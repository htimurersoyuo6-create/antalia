@extends('layouts.admin')
@section('title', $user->exists ? 'Kullanıcıyı düzenle' : 'Kullanıcı ekle')
@section('heading', $user->exists ? 'Kullanıcıyı düzenle' : 'Yeni kullanıcı')
@section('sub','Hesap bilgileri ve platform erişimi')
@section('content')
<form class="a-card a-form" method="post" action="{{ $user->exists ? route('admin.users.update',$user) : route('admin.users.store') }}">
 @csrf @if($user->exists) @method('PUT') @endif
 <div class="a-field"><label>Ad soyad</label><input type="text" name="name" value="{{ old('name',$user->name) }}" required maxlength="120"></div>
 <div class="a-field"><label>E-posta <small>(isteğe bağlı)</small></label><input type="email" name="email" value="{{ old('email',$user->email) }}" maxlength="160" autocomplete="email"></div>
 <div class="a-field"><label>Telefon <small>(e-posta yoksa zorunlu)</small></label><input type="text" name="phone" value="{{ old('phone',$user->phone) }}" maxlength="30" autocomplete="tel"></div>
 <div class="a-field"><label>T.C. kimlik numarası <small>(e-posta yoksa gerekli; güvenlik için yalnızca özeti saklanır)</small></label><input type="password" name="tc_kimlik" inputmode="numeric" maxlength="11" autocomplete="off" value="" placeholder="{{ $user->identity_hash ? 'Kayıtlı · değiştirmek için yeniden girin' : '11 haneli numara' }}"></div>
 <div class="a-field"><label>Hesap türü</label><select name="role" required>@foreach(['customer'=>'Müşteri','hotel_owner'=>'Otel işletmesi','admin'=>'Yönetici'] as $k=>$v)<option value="{{ $k }}" @selected(old('role',$user->role ?: 'customer')===$k)>{{ $v }}</option>@endforeach</select></div>
 <div class="a-field"><label>{{ $user->exists ? 'Yeni parola (değiştirmeyecekseniz boş bırakın)' : 'Başlangıç parolası (en az 12 karakter)' }}</label><input type="password" name="password" {{ $user->exists ? '' : 'required' }} minlength="12" autocomplete="new-password"></div>
 <div class="a-field"><label>Parola tekrar</label><input type="password" name="password_confirmation" {{ $user->exists ? '' : 'required' }} minlength="12" autocomplete="new-password"></div>
 <div class="a-form-f"><a class="a-btn ghost" href="{{ route('admin.users.index') }}">Vazgeç</a><button class="a-btn">Kaydet</button></div>
</form>
@endsection
