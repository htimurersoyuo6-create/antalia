@extends('layouts.app')
@section('title','Pet Dostları Forumu')
@section('desc','Evcil hayvan bakımı, sağlık, beslenme ve pet otelleri hakkında deneyim paylaşın; topluluğa katılın.')
@section('content')
<div class="wrap page forum-page">
  <section class="forum-hero">
    <div class="forum-hero-copy">
      <span class="eyebrow">BİRLİKTE DAHA BİLGİLİ, DAHA MUTLU</span>
      <h1>Pet Dostları Forumu</h1>
      <p>Deneyimlerinizi paylaşın, güvenilir öneriler alın ve dostlarımız için birlikte öğrenin.</p>
      <div class="forum-hero-actions">
        <a class="btn" href="{{ auth()->check()?route('forum.create'):route('login') }}">Yeni konu aç <span aria-hidden="true">→</span></a>
        <span class="forum-live-count"><strong>{{ number_format($topics->total()) }}</strong> yayınlanan konu</span>
        <a class="chip" href="{{ route('forum.rss') }}">RSS</a>
      </div>
    </div>
    <aside class="forum-welcome-card">
      <span class="forum-card-icon" aria-hidden="true">✳</span>
      <span class="forum-card-label">TOPLULUĞA KATIL</span>
      <h2>İyi bir soru, başka bir dosta da yardımcı olur.</h2>
      <ul>
        <li><span>01</span> Konunu doğru başlıkta paylaş</li>
        <li><span>02</span> Deneyimini nazikçe aktar</li>
        <li><span>03</span> Yayın öncesi moderasyonla güvende kal</li>
      </ul>
    </aside>
  </section>
  <nav class="forum-cats" aria-label="Forum kategorileri">
    <a class="chip" href="{{ route('forum.index') }}" @if(!request('category')) aria-current="page" @endif>Tüm konular</a>
    @foreach($categories as $key=>$label)
      <a class="chip" href="{{ route('forum.index',['category'=>$key]) }}" @if(request('category')===$key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
  </nav>
  <form class="forum-search" method="get" role="search">
    @if(request('category'))<input type="hidden" name="category" value="{{ request('category') }}">@endif
    <label class="sr-only" for="forum-q">Forum konularında ara</label>
    <input id="forum-q" name="q" value="{{ request('q') }}" placeholder="Örn. kedi bakımı, beslenme, pet oteli">
    <button class="btn ghost" type="submit">Konularda ara</button>
  </form>
  <div class="forum-list">
    @forelse($topics as $topic)
      <a class="cardbox forum-topic" href="{{ route('forum.show',$topic) }}">
        <div><span class="chip">{{ $categories[$topic->category]??'Genel sohbet' }}</span><h2>{{ $topic->title }}</h2><p>{{ \Illuminate\Support\Str::limit(strip_tags($topic->body),170) }}</p><small>{{ $topic->user->name }} · {{ $topic->created_at->format('d.m.Y') }}</small></div>
        <span class="forum-count"><strong>{{ $topic->replies_count }}</strong><small>yanıt</small></span>
      </a>
    @empty
      <section class="forum-empty">
        <div class="forum-empty-art" aria-hidden="true"><span>🐾</span><i></i><i></i></div>
        <div class="forum-empty-copy">
          <span class="forum-card-label">{{ request('q') ? 'ARAMA SONUCU' : 'YENİ TOPLULUK' }}</span>
          <h2>{{ request('q') ? 'Bu aramayla eşleşen konu bulamadık.' : 'İlk güzel sohbeti sen başlat.' }}</h2>
          <p>{{ request('q') ? 'Daha kısa bir kelime deneyebilir veya tüm konulara dönebilirsiniz.' : 'Henüz yayınlanmış bir konu yok. Aklındaki soruyu paylaş; ekibimiz güvenli bir topluluk için her yeni konuyu inceler.' }}</p>
          <div class="forum-hero-actions">
            @if(request('q'))<a class="btn ghost" href="{{ route('forum.index') }}">Tüm konulara dön</a>@endif
            <a class="btn" href="{{ auth()->check()?route('forum.create'):route('login') }}">{{ request('q') ? 'Yeni konu aç' : 'İlk konuyu aç' }} <span aria-hidden="true">→</span></a>
          </div>
          @unless(request('q'))<div class="forum-prompt-list"><span>Fikir arıyorsanız</span><b>Yeni bir pet sahibi için ilk hafta</b><b>Günlük bakımda size yardımcı olan rutinler</b><b>Antalya’da pet dostu hizmet önerileri</b></div>@endunless
        </div>
      </section>
    @endforelse
  </div>
  {{ $topics->links() }}
</div>
@endsection