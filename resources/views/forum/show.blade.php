@extends('layouts.app')
@section('title', $topic->title . ' – Pet Dostları Forumu')
@section('desc', \Illuminate\Support\Str::limit(strip_tags($topic->meta_description ?: $topic->body), 158))
@section('ogtype', 'article')
@section('ogtitle', $topic->title)
@section('ogdesc', \Illuminate\Support\Str::limit(strip_tags($topic->meta_description ?: $topic->body), 158))
@section('canonical', route('forum.show', $topic))
@section('content')
<div class="wrap page forum-page forum-detail-page">
  <nav class="forum-crumbs" aria-label="Site yolu">
    <a href="{{ url('/') }}">Ana Sayfa</a>
    <span>/</span>
    <a href="{{ route('forum.index') }}">Forum</a>
    <span>/</span>
    <span aria-current="page">{{ $topic->title }}</span>
  </nav>

  <a class="forum-back-link" href="{{ route('forum.index') }}">← Foruma dön</a>

  <article class="cardbox forum-post">
    @php
      $authorUser = $topic->user;
      $authorUrl = ($authorUser && !empty($authorUser->username))
          ? route('profile.show', $authorUser->username)
          : null;
      $wordCount = count(preg_split('/\s+/u', trim(strip_tags($topic->body)), -1, PREG_SPLIT_NO_EMPTY));
      $readMinutes = max(1, (int) ceil($wordCount / 200));
    @endphp
    <span class="chip">{{ $topic->category }}</span>
    <h1>{{ $topic->title }}</h1>
    <p class="forum-post-meta">
      @if($authorUrl)
        <a href="{{ $authorUrl }}" rel="author">{{ $authorUser->name }}</a>
      @else
        <span>{{ $authorUser->name ?? 'Üye' }}</span>
      @endif
      <span aria-hidden="true">·</span>
      <time datetime="{{ $topic->created_at->toAtomString() }}">{{ $topic->created_at->format('d.m.Y H:i') }}</time>
      <span aria-hidden="true">·</span>
      <span>{{ number_format($topic->views) }} görüntülenme</span>
      <span aria-hidden="true">·</span>
      <span>{{ $readMinutes }} dk okuma</span>
    </p>
    <div class="forum-body">{!! $topic->body !!}</div>
  </article>

  <section class="forum-replies" aria-labelledby="forum-replies-title">
    <div class="forum-section-heading">
      <div>
        <span class="forum-card-label">TOPLULUKTAN</span>
        <h2 id="forum-replies-title">Yanıtlar <span>({{ $replies->total() }})</span></h2>
      </div>
    </div>
    <div class="forum-reply-list">
      @forelse($replies as $reply)
        <article class="cardbox forum-reply">
          <div class="forum-reply-heading">
            @if($reply->user && !empty($reply->user->username))
              <b><a href="{{ route('profile.show', $reply->user->username) }}" rel="author">{{ $reply->user->name }}</a></b>
            @else
              <b>{{ $reply->user->name ?? 'Üye' }}</b>
            @endif
            <time datetime="{{ $reply->created_at->toAtomString() }}">{{ $reply->created_at->format('d.m.Y H:i') }}</time>
          </div>
          <div class="forum-reply-body">{!! $reply->body !!}</div>
        </article>
      @empty
        <div class="forum-empty-reply">Henüz yanıt yok. İlk yanıtı siz yazın.</div>
      @endforelse
    </div>
    {{ $replies->links() }}
  </section>

  @auth
    <form class="form cardbox forum-compose" method="post" action="{{ route('forum.reply', $topic) }}">
      @csrf
      <span class="forum-card-label">SOHBETE KATIL</span>
      <h2>Yanıt yaz</h2>
      <label for="forum-reply-body">Mesajınız</label>
      <textarea id="forum-reply-body" name="body" rows="5" required minlength="5" maxlength="5000">{{ old('body') }}</textarea>
      @include('partials.captcha', ['captchaKey' => 'forum-reply'])
      <button class="btn" type="submit">Yanıtı moderasyona gönder</button>
    </form>
  @else
    <p class="forum-login-note">Yanıt yazmak için <a href="{{ route('login') }}">giriş yapın</a>.</p>
  @endauth
</div>
@endsection

@push('head')
<script type="application/ld+json">
{
  "@@context": "https://schema.org",
  "@type": "ForumPosting",
  "headline": @json($topic->title),
  "author": {
    "@type": "Person",
    "name": @json($topic->user->name ?? 'Üye'),
    "url": @json($authorUrl ?? 'https://antalyapetotel.com/forum'),
    "knowsAbout": ["Antalya", "pet bakımı", "veteriner", "pet otel"]
  },
  "datePublished": @json($topic->created_at->toIso8601String()),
  "dateModified": @json($topic->updated_at->toIso8601String()),
  "articleSection": @json($topic->category),
  "url": @json(route('forum.show', $topic)),
  "mainEntityOfPage": {"@type": "WebPage", "@id": @json(route('forum.show', $topic))},
  "publisher": {"@type": "Organization", "name": "Antalya Pet Otel", "url": "https://antalyapetotel.com"}
}
</script>
<script type="application/ld+json">
{
  "@@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    {"@type": "ListItem", "position": 1, "name": "Ana Sayfa", "item": "https://antalyapetotel.com/"},
    {"@type": "ListItem", "position": 2, "name": "Pet Dostları Forumu", "item": @json(route('forum.index'))},
    {"@type": "ListItem", "position": 3, "name": @json($topic->title), "item": @json(route('forum.show', $topic))}
  ]
}
</script>
@endpush
