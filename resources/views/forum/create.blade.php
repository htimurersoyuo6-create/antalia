@extends('layouts.app')
@section('title','Forumda konu aç')
@section('content')
<div class="wrap page forum-page forum-compose-page">
  <a class="forum-back-link" href="{{ route('forum.index') }}">← Foruma dön</a>
  <form class="form cardbox forum-compose" method="post" action="{{ route('forum.store') }}">
    @csrf
    <span class="forum-card-label">TOPLULUĞA KATIL</span>
    <h1>Yeni konu aç</h1>
    <p class="forum-compose-intro">Sorunuzu veya deneyiminizi paylaşın. Konunuz yayınlanmadan önce topluluk güvenliği için incelenir.</p>
    <label for="forum-category">Kategori</label>
    <select id="forum-category" name="category" required>
      @foreach($categories as $key=>$label)
        <option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>
      @endforeach
    </select>
    <label for="forum-title">Konu başlığı</label>
    <input id="forum-title" name="title" required minlength="8" maxlength="180" value="{{ old('title') }}" autocomplete="off">
    <label for="forum-body">Mesajınız</label>
    <textarea id="forum-body" name="body" rows="8" required minlength="30" maxlength="10000">{{ old('body') }}</textarea>
    @include('partials.captcha',['captchaKey'=>'forum-topic'])
    <button class="btn" type="submit">Moderasyona gönder</button>
  </form>
</div>
@endsection
