@extends('layouts.admin')
@section('title','Forum moderasyonu')
@section('heading','Forum moderasyonu')
@section('sub','Yeni konuları ve yanıtları inceleyin; uygun olanları yayına alın.')
@section('content')
<div class="a-moderation-grid">
  <section class="a-card a-moderation-section">
    <header><div><span class="a-settings-kicker">TOPLULUK</span><h2>Bekleyen konular</h2><p>Onayladığınızda forumda herkese görünür.</p></div><span class="a-moderation-count">{{ $topics->total() }}</span></header>
    @forelse($topics as $topic)
      <article class="a-moderation-item"><div class="a-moderation-copy"><span class="a-moderation-tag">{{ $topic->category }}</span><h3>{{ $topic->title }}</h3><p>{{ $topic->body }}</p><small>{{ $topic->user->name }} · {{ $topic->created_at->format('d.m.Y H:i') }}</small></div><div class="a-moderation-actions"><form method="post" action="{{ route('admin.forum.topic',$topic) }}">@csrf @method('PATCH')<button class="a-btn primary" name="status" value="active">Yayına al</button></form><form method="post" action="{{ route('admin.forum.topic',$topic) }}">@csrf @method('PATCH')<button class="a-btn" name="status" value="rejected">Reddet</button></form></div></article>
    @empty<div class="a-empty"><span>✓</span><h3>Bekleyen konu yok</h3><p>Yeni paylaşımlar burada listelenecek.</p></div>@endforelse
    <div class="a-pagination">{{ $topics->links() }}</div>
  </section>
  <section class="a-card a-moderation-section">
    <header><div><span class="a-settings-kicker">YORUMLAR</span><h2>Bekleyen yanıtlar</h2><p>Yanıtı, bağlı olduğu konu ve yazarıyla birlikte inceleyin.</p></div><span class="a-moderation-count">{{ $replies->total() }}</span></header>
    @forelse($replies as $reply)
      <article class="a-moderation-item"><div class="a-moderation-copy"><span class="a-moderation-tag">{{ $reply->topic->title }}</span><p>{{ $reply->body }}</p><small>{{ $reply->user->name }} · {{ $reply->created_at->format('d.m.Y H:i') }}</small></div><div class="a-moderation-actions"><form method="post" action="{{ route('admin.forum.reply',$reply) }}">@csrf @method('PATCH')<button class="a-btn primary" name="status" value="active">Yayına al</button></form><form method="post" action="{{ route('admin.forum.reply',$reply) }}">@csrf @method('PATCH')<button class="a-btn" name="status" value="rejected">Reddet</button></form></div></article>
    @empty<div class="a-empty"><span>✓</span><h3>Bekleyen yanıt yok</h3><p>Yeni yanıtlar burada listelenecek.</p></div>@endforelse
    <div class="a-pagination">{{ $replies->links() }}</div>
  </section>
</div>
@endsection
