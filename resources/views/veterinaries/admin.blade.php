@extends('layouts.app')
@section('title','Acil veteriner kayıtları')
@section('content')
<main class="wrap page" style="max-width:1100px;margin:auto;padding:32px 16px"><h1>Acil veteriner kayıtları</h1>
@if(session('ok'))<p role="status">{{ session('ok') }}</p>@endif @if($errors->any())<div role="alert">{{ $errors->first() }}</div>@endif
<p>7/24 bilgisini yalnızca kliniğin güncel ve doğrulanmış çalışma saatlerine göre işaretleyin. Harita sadece yayınlanan, koordinatı girilmiş ve 7/24 olarak doğrulanmış kayıtları listeler.</p>
@foreach($clinics as $clinic)<form method="post" action="{{ route('admin.veterinaries.update',$clinic) }}" style="padding:16px;margin:12px 0;border:1px solid #ddd;border-radius:12px">@csrf @method('PATCH')
<h2>{{ $clinic->name }}</h2><p>{{ $clinic->district?->name }} · {{ $clinic->address }} · {{ $clinic->phone }}</p>
<div style="display:flex;gap:16px;flex-wrap:wrap"><label>Enlem<input type="number" name="lat" step="any" min="-90" max="90" value="{{ $clinic->lat }}"></label><label>Boylam<input type="number" name="lng" step="any" min="-180" max="180" value="{{ $clinic->lng }}"></label>
<label><input type="checkbox" name="is_24h" value="1" @checked($clinic->is_24h)> 7/24 açık olduğu doğrulandı</label><label><input type="checkbox" name="is_published" value="1" @checked($clinic->is_published)> Yayında</label><button type="submit">Kaydet</button></div></form>@endforeach
{{ $clinics->links() }}</main>
@endsection
