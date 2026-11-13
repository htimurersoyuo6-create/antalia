@extends('layouts.admin')
@section('title', $post->exists ? 'Yazıyı düzenle' : 'Yeni yazı')
@section('heading', $post->exists ? 'Yazıyı düzenle' : 'Yeni blog yazısı')
@section('sub', 'Başlık, özet ve içerik. Yayınla işaretlenmezse taslak olarak kaydedilir.')
@section('actions')<a class="a-btn ghost" href="{{ route('admin.index') }}#blog">← Listeye dön</a>
@endsection
@section('content')
<form class="a-card a-form" method="post" action="{{ $post->exists ? route('admin.posts.update',$post) : route('admin.posts.store') }}">@csrf @if($post->exists)@method('PUT')@endif
<div class="a-field"><label for="title">Başlık</label><input id="title" type="text" name="title" value="{{ old('title',$post->title) }}" required></div>
<div class="a-field"><label for="excerpt">Özet <span>(en fazla 300 karakter)</span></label><input id="excerpt" type="text" name="excerpt" value="{{ old('excerpt',$post->excerpt) }}" maxlength="300"></div>
<p class="hint" role="note"><strong>Yayın öncesi kısa kontrol:</strong> İçeriği gerçek kullanıcı sorusunu yanıtlayacak şekilde özgünleştirin. Fiyat, mevzuat, sağlık veya tesis hizmeti iddialarını güncel ve güvenilir kaynakla doğrulayın; doğrulanmayan uzmanlık, deneyim, yorum veya garanti ifadeleri eklemeyin.</p>
<div class="a-field blog-editor-field"><label for="blog-editor">İçerik</label>
<div class="blog-editor-toolbar" role="toolbar" aria-label="Yazı biçimlendirme">
<button type="button" data-cmd="formatBlock" data-value="<h2>">Başlık</button><button type="button" data-cmd="formatBlock" data-value="<h3>">Alt başlık</button><button type="button" data-cmd="formatBlock" data-value="<p>">Paragraf</button><span></span><button type="button" data-cmd="bold"><b>K</b></button><button type="button" data-cmd="italic"><i>İ</i></button><button type="button" data-cmd="insertUnorderedList">• Liste</button><button type="button" data-cmd="insertOrderedList">1. Liste</button><button type="button" data-cmd="formatBlock" data-value="<blockquote>">Alıntı</button><button type="button" id="blog-link-button">Bağlantı</button></div>
<div class="a-inl" style="margin:8px 0"><select id="blog-media-select"><option value="">Medya kütüphanesinden görsel seçin</option>@foreach($media as $asset)<option value="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($asset) }}">{{ basename($asset) }}</option>@endforeach</select><button type="button" class="a-btn sm ghost" id="blog-image-button">İçeriğe görsel ekle</button><a class="a-btn sm ghost" href="{{ route('admin.media') }}">Görsel yükle</a></div>
<div id="blog-editor" class="post-content-editor" contenteditable="true" role="textbox" aria-multiline="true">{!! makale_guvenli_html(old('body', $post->body), $post->title) !!}</div>
<textarea id="body" name="body" hidden>{{ old('body',$post->body) }}</textarea>
<small class="post-editor-hint">Metin düzenleyicide HTML yazmanız gerekmez. Başlık, liste ve bağlantı düğmelerini kullanın; güvenli olmayan HTML yayımlanırken temizlenir.</small></div>
<script>
(()=>{const editor=document.getElementById('blog-editor'),source=document.getElementById('body'),form=source.form;
document.querySelector('.blog-editor-toolbar').addEventListener('click',e=>{const b=e.target.closest('button');if(!b)return;editor.focus();if(b.id==='blog-link-button'){const url=prompt('Bağlantı adresi (https://, mailto: veya site içi /adres):');if(url&&/^(https?:\/\/|mailto:|\/)/i.test(url))document.execCommand('createLink',false,url);return;}document.execCommand(b.dataset.cmd,false,b.dataset.value||null);});
document.getElementById('blog-image-button').addEventListener('click',()=>{const select=document.getElementById('blog-media-select'),src=select.value;if(!src){select.focus();return}const figure=document.createElement('figure'),image=document.createElement('img'),caption=document.createElement('figcaption');image.src=src;image.alt=prompt('Görsel için kısa açıklama (boş bırakabilirsiniz):')||'';image.loading='lazy';figure.append(image,caption);editor.focus();document.execCommand('insertHTML',false,figure.outerHTML);select.value=''});form.addEventListener('submit',()=>{source.value=editor.innerHTML;});})();
</script>
<div class="a-form-f"><label class="a-chk"><input type="checkbox" name="publish" value="1" @checked(old('publish',$post->published_at))> Yayınla</label><div class="a-inl"><a class="a-btn ghost" href="{{ route('admin.index') }}#blog">Vazgeç</a><button class="a-btn">Kaydet</button></div></div>
</form>
@endsection
