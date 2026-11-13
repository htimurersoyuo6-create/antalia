@extends('layouts.admin')
@section('title', 'Medya kütüphanesi')
@section('heading', 'Medya kütüphanesi')
@section('sub', 'Görsellerinizi yükleyin, önizleyin ve ana sayfa gibi içeriklerde kullanın.')
@section('content')
<form class="a-card a-form" method="post" action="{{ route('admin.media.store') }}" enctype="multipart/form-data">@csrf
<div class="a-card-h"><div><h2>Görsel yükle</h2><p>JPG, PNG veya WebP · En fazla 8 MB. Yüklenen görseller web için küçültülür.</p></div></div>
<div class="a-field"><label for="image">Görsel dosyası</label><input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp" required></div><div class="a-form-f"><span></span><button class="a-btn">Yükle</button></div>
</form>
<section class="a-card" style="margin-top:18px"><div class="a-card-h"><div><h2>Yüklenen görseller</h2><p>Bir görseli ana sayfada kullanmak için Site ayarları → Ana sayfa bölümünden seçin.</p></div><span class="a-count">{{ $items->count() }}</span></div>
@if($items->isEmpty())<div class="a-empty">Henüz medya yüklenmedi.</div>@else<div class="a-media-grid">@foreach($items as $item)<article class="a-media-item"><a href="{{ $item['url'] }}" target="_blank" rel="noopener"><img src="{{ $item['url'] }}" alt="" loading="lazy"></a><div><b>{{ basename($item['path']) }}</b><small>{{ number_format($item['size']/1024, 0, ',', '.') }} KB · {{ date('d.m.Y H:i', $item['updated']) }}</small><button type="button" class="a-btn sm ghost" data-copy-url="{{ $item['url'] }}">Görsel adresini kopyala</button></div></article>@endforeach</div>@endif
</section>
<style>.a-media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:14px}.a-media-item{overflow:hidden;border:1px solid #e7e8e1;border-radius:14px;background:#fff}.a-media-item>a{display:block;height:155px;background:#f3f4f0}.a-media-item img{width:100%;height:100%;object-fit:cover}.a-media-item>div{display:grid;gap:7px;padding:12px}.a-media-item b{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px}.a-media-item small{color:#788078;font-size:10px}.a-media-item button{justify-self:start}@media(max-width:560px){.a-media-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.a-media-item>a{height:120px}}</style>
<script>document.addEventListener('click',async e=>{const b=e.target.closest('[data-copy-url]');if(!b)return;try{await navigator.clipboard.writeText(b.dataset.copyUrl);b.textContent='Kopyalandı';setTimeout(()=>b.textContent='Görsel adresini kopyala',1500)}catch(_){prompt('Görsel adresi',b.dataset.copyUrl)}})</script>
@endsection
