@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
<rss version="2.0">
  <channel>
    <title>{{ config('app.name', 'AntalyaPetOtel') }} - Forum</title>
    <link>{{ route('forum.index') }}</link>
    <description>Published discussions about pet care, health, nutrition and pet hotels.</description>
    <language>tr-TR</language>
    @foreach($topics as $topic)
      <item>
        <title>{{ $topic->title }}</title>
        <link>{{ route('forum.show', $topic->slug) }}</link>
        <guid isPermaLink="true">{{ route('forum.show', $topic->slug) }}</guid>
        <pubDate>{{ optional($topic->created_at)->toRfc822String() }}</pubDate>
        <description>{{ \Illuminate\Support\Str::limit(strip_tags($topic->body), 1000) }}</description>
      </item>
    @endforeach
  </channel>
</rss>
