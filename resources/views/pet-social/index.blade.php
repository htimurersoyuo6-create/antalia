@extends('layouts.app')
@section('title', 'Evcil dostlar topluluğu')
@section('content')
<main class="wrap" style="max-width:1020px;margin:30px auto 64px;padding:0 18px">
  <header style="padding:26px;border-radius:22px;background:linear-gradient(120deg,#f4f1e8,#fff);margin-bottom:20px"><span style="font-size:.78rem;letter-spacing:.08em;color:#74806f">TOPLULUK</span><h1 style="margin:.3rem 0">Pet Akışı</h1><p style="margin:0;color:#586158">Sahiplerinin toplulukla paylaşmayı seçtiği anlar. Paylaşımlar yayımlanmadan önce incelenir.</p></header>
  @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
  @auth
  <details style="padding:18px;border:1px solid #e7e4dc;border-radius:16px;margin-bottom:22px"><summary style="font-weight:700;cursor:pointer">Bir anı paylaş</summary>
    <form method="post" action="{{ route('pet-social.store') }}" enctype="multipart/form-data" style="display:grid;gap:12px;margin-top:16px">@csrf
      <label>Fotoğraf <input class="form-control" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required></label>
      <label>Evcil dost (isteğe bağlı)<select class="form-select" name="pet_id"><option value="">Seçmeden devam et</option>@foreach($myPets as $pet)<option value="{{ $pet->id }}">{{ $pet->name }}</option>@endforeach</select></label>
      <label>Açıklama<textarea class="form-control" name="caption" maxlength="500" rows="3">{{ old('caption') }}</textarea></label>
      <label style="display:flex;gap:9px;align-items:flex-start"><input type="checkbox" name="public_consent" value="1" required style="margin-top:5px"><span>Bu fotoğrafın onaylandıktan sonra herkesin görebileceği Pet Akışı’nda yayımlanmasına izin veriyorum. Rezervasyon güncellemelerim buraya otomatik eklenmez.</span></label>
      @error('photo')<p class="text-danger">{{ $message }}</p>@enderror @error('public_consent')<p class="text-danger">Açık paylaşım izni gerekli.</p>@enderror
      <button class="btn btn-primary" type="submit">İncelemeye gönder</button>
    </form>
  </details>
  @else<p><a href="{{ route('login') }}">Giriş yaparak</a> kendi fotoğrafınızı paylaşabilir ve gönderileri beğenebilirsiniz.</p>@endauth
  <section aria-label="Onaylanmış topluluk paylaşımları" style="display:grid;gap:18px">
    @forelse($posts as $post)
      <article style="border:1px solid #e8e6df;border-radius:18px;overflow:hidden;background:#fff">
        <div style="padding:14px 18px"><strong>{{ $post->pet?->name ?: $post->user->name }}</strong><span style="float:right;color:#70776f">{{ $post->created_at->format('d.m.Y') }}</span></div>
        <img src="{{ asset('storage/'.$post->photo_path) }}" alt="{{ $post->pet?->name ?: 'Evcil hayvan' }} paylaşımı" loading="lazy" style="display:block;width:100%;max-height:620px;object-fit:cover">
        <div style="padding:16px 18px">@if($post->caption)<p style="white-space:pre-line">{{ $post->caption }}</p>@endif
          <div style="display:flex;gap:16px;align-items:center;color:#5c655d"><span>{{ $post->likes_count }} beğeni</span><span>{{ $post->comments_count }} yorum</span></div>
          @auth<div style="display:flex;gap:10px;align-items:center;margin-top:12px"><form method="post" action="{{ route('pet-social.like',$post) }}">@csrf<button class="btn btn-sm btn-outline-secondary" type="submit">{{ in_array($post->id,$likedIds) ? 'Beğeniyi kaldır' : 'Beğen' }}</button></form>
            @if((int)$post->user_id === (int)auth()->id())<form method="post" action="{{ route('pet-social.destroy',$post) }}" onsubmit="return confirm('Paylaşım silinsin mi?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">Sil</button></form>@endif</div>
            <form method="post" action="{{ route('pet-social.comment',$post) }}" style="display:flex;gap:8px;margin-top:12px">@csrf<input class="form-control" name="body" maxlength="500" placeholder="Yorum yaz" required><button class="btn btn-sm btn-outline-primary" type="submit">Gönder</button></form>
          @endauth
          @if($post->comments->isNotEmpty())<div style="margin-top:12px">@foreach($post->comments as $comment)<p style="margin:5px 0"><strong>{{ $comment->user->name }}</strong> {{ $comment->body }}</p>@endforeach</div>@endif
        </div>
      </article>
    @empty<p>Henüz yayımlanmış paylaşım yok.</p>@endforelse
  </section>
  <div style="margin-top:24px">{{ $posts->links() }}</div>
</main>
@endsection
