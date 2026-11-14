@extends('layouts.app')
@section('title', $profile->name . ' – Antalya Pet Dostları Forumu')
@section('desc', $profile->name . ' profil sayfası: Antalya pet sahipleri forumunda açtığı ' . $stats['topics'] . ' konu ve ' . $stats['replies'] . ' yanıt. Yerel deneyimler, semt notları ve bakım tecrübeleri.')
@section('canonical', route('profile.show', $profile->username))
@section('content')
<div class="wrap page profile-page">
  <nav class="forum-crumbs" aria-label="Site yolu">
    <a href="{{ url('/') }}">Ana Sayfa</a>
    <span>/</span>
    <a href="{{ route('forum.index') }}">Forum</a>
    <span>/</span>
    <span aria-current="page">{{ $profile->name }}</span>
  </nav>

  <header class="cardbox profile-head">
    <div class="profile-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($profile->name, 0, 1)) }}</div>
    <div class="profile-identity">
      <span class="forum-card-label">TOPLULUK ÜYESİ</span>
      <h1>{{ $profile->name }}</h1>
      <p class="forum-post-meta">
        <span>{{ '@'.$profile->username }}</span>
        @if($profile->created_at)
          <span>· Üyelik: {{ $profile->created_at->format('d.m.Y') }}</span>
        @endif
        <span>· Antalya</span>
      </p>
      <p class="profile-bio">
        Antalya Pet Dostları Forumu'nda kendi deneyimini paylaşan bir pet sahibi.
        Açtığı konular semt semt yaşanmış tecrübeleri, fiyat notlarını ve bakım
        pratiklerini içeriyor.
      </p>
      <dl class="profile-stats">
        <div><dt>Konu</dt><dd>{{ number_format($stats['topics']) }}</dd></div>
        <div><dt>Yanıt</dt><dd>{{ number_format($stats['replies']) }}</dd></div>
      </dl>
    </div>
  </header>

  <section class="forum-replies" aria-labelledby="profile-topics-title">
    <div class="forum-section-heading">
      <div>
        <span class="forum-card-label">AÇTIĞI KONULAR</span>
        <h2 id="profile-topics-title">{{ $profile->name }} <span>({{ number_format($stats['topics']) }})</span></h2>
      </div>
    </div>

    @php($categoryLabels = ['pet-bakimi' => 'Pet bakımı', 'saglik' => 'Sağlık ve veteriner', 'egitim' => 'Eğitim ve davranış', 'beslenme' => 'Beslenme', 'pet-otelleri' => 'Pet otelleri', 'genel' => 'Genel sohbet'])
    <div class="profile-topic-list">
      @forelse($topics as $t)
        <article class="cardbox profile-topic">
          <span class="chip">{{ $categoryLabels[$t->category] ?? 'Genel sohbet' }}</span>
          <h3><a href="{{ route('forum.show', $t->slug) }}">{{ $t->title }}</a></h3>
          @if($t->meta_description)
            <p>{{ $t->meta_description }}</p>
          @endif
          <p class="forum-post-meta">
            @php($tDate = \Illuminate\Support\Carbon::parse($t->publish_at ?? $t->created_at))
            <time datetime="{{ $tDate->toAtomString() }}">{{ $tDate->format('d.m.Y') }}</time>
            <span>· {{ number_format($t->replies_count) }} yanıt</span>
          </p>
        </article>
      @empty
        <div class="forum-empty-reply">Bu üyenin henüz yayınlanmış konusu yok.</div>
      @endforelse
    </div>
    {{ $topics->links() }}
  </section>
</div>
@endsection

@push('head')
<script type="application/ld+json">
{
  "@@context": "https://schema.org",
  "@type": "Person",
  "name": @json($profile->name),
  "url": @json(route('profile.show', $profile->username)),
  "description": @json('Antalya pet sahipleri forumunda deneyim paylaşan üye.'),
  "knowsAbout": ["Antalya", "pet bakımı", "veteriner", "pet otel", "kedi", "köpek"],
  "memberOf": {
    "@type": "Organization",
    "name": "Antalya Pet Otel",
    "url": "https://antalyapetotel.com"
  }
}
</script>
@endpush
