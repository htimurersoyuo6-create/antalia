@extends('layouts.admin')
@section('title', 'Bilgi sayfaları')
@section('heading', 'Bilgi sayfaları')
@section('sub', 'Hakkımızda, sık sorulan sorular ve yasal içerikler gibi site sayfalarını yönetin.')
@section('actions')<a class="a-btn" href="{{ route('admin.pages.create') }}">+ Sayfa ekle</a>@endsection
@section('content')
<section class="a-card">
  <div class="a-card-h"><div><h2>Yayınlanan sayfalar</h2><p>Sayfalar herkese açık olarak kendi adreslerinde görünür.</p></div><span class="a-count">{{ $pages->total() }}</span></div>
  <div class="a-tw"><table class="a-table"><thead><tr><th>Sayfa</th><th>Adres</th><th>Son düzenleme</th><th class="r">İşlem</th></tr></thead><tbody>
  @forelse($pages as $item)<tr><td><b>{{ $item->title }}</b><small>{{ \Illuminate\Support\Str::limit(strip_tags($item->content), 110) }}</small></td><td><a href="{{ route('pages.show', $item->slug) }}" target="_blank" rel="noopener">/sayfa/{{ $item->slug }}</a></td><td>{{ $item->updated_at?->format('d.m.Y H:i') ?? '—' }}</td><td class="r"><a class="a-btn sm ghost" href="{{ route('admin.pages.edit', $item) }}">Düzenle</a></td></tr>
  @empty<tr><td colspan="4" class="a-empty">Henüz düzenlenebilir bilgi sayfası yok.</td></tr>@endforelse
  </tbody></table></div>{{ $pages->links() }}
</section>
@endsection
