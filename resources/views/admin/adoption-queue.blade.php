@extends('layouts.admin')
@section('title', 'Sahiplendirme onayı')
@section('heading', 'Sahiplendirme ilanları')
@section('sub', 'Yayına alınmadan önce bekleyen ilanları inceleyin.')
@section('content')
<div class="a-card adoption-queue">
  @if($listings->isEmpty())
    <div class="a-empty"><span>🐾</span><h2>Bekleyen ilan yok</h2><p>Yeni sahiplendirme ilanları bu alanda görünür.</p></div>
  @else
    <div class="a-table-wrap"><table class="a-table"><thead><tr><th>Fotoğraf</th><th>Dost</th><th>Konum</th><th>İlan sahibi</th><th>Detay</th><th>İşlem</th></tr></thead><tbody>
    @foreach($listings as $listing)
      <tr><td>@if($listing->photo_path)<a class="adoption-queue-photo" href="{{ route('adoption.photo',$listing) }}" target="_blank" rel="noopener"><img src="{{ route('adoption.photo',$listing) }}" alt="{{ $listing->pet_name }}"></a>@else<span class="adoption-queue-photo">🐾</span>@endif</td><td><strong>{{ $listing->pet_name }}</strong><small>{{ \App\Models\Pet::SPECIES[$listing->species] ?? 'Diğer' }}{{ $listing->breed ? ' · '.$listing->breed : '' }}</small><small>{{ $listing->age_label }} · {{ $listing->sex_label ?? 'Cinsiyet belirtilmemiş' }} · {{ $listing->vaccinated ? 'Aşılı' : 'Aşı bilgisi yok' }}</small></td>
      <td>{{ $listing->city }}{{ $listing->district ? ' · '.$listing->district : '' }}</td>
      <td>{{ $listing->user?->name ?? 'Hesap bulunamadı' }}<small>{{ $listing->user?->email }}</small></td>
      <td class="adoption-description">{{ \Illuminate\Support\Str::limit($listing->description, 150) }}</td>
      <td><div class="adoption-actions"><form method="post" action="{{ route('admin.adoption.moderate',$listing) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="active"><button class="a-btn primary" type="submit">Yayına al</button></form><form method="post" action="{{ route('admin.adoption.moderate',$listing) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="rejected"><button class="a-btn" type="submit">Reddet</button></form></div></td></tr>
    @endforeach
    </tbody></table></div>
    <div class="a-pagination">{{ $listings->links() }}</div>
  @endif
</div>
@endsection
