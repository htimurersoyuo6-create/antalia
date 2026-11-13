@extends('layouts.app')
@section('title', 'Favori Pet Otellerim | Antalya Pet Otel')
@section('content')
<div class="wrap page favorites-page">
  <div class="crumb"><a href="{{ route('home') }}">Ana Sayfa</a> › Favori otellerim</div>
  <header class="favorites-heading"><div><span class="customer-eyebrow">KAYDETTİĞİNİZ TESİSLER</span><h1>Favori pet otellerim</h1><p>Karşılaştırmak istediğiniz tesisler burada dursun.</p></div><a class="btn ghost" href="{{ route('hotels.all') }}">Otellere göz at</a></header>
  @if($hotels->isEmpty())
    <div class="customer-empty favorites-empty"><span>♡</span><h2>Henüz favori oteliniz yok</h2><p>Otel kartındaki kalbe dokunarak beğendiğiniz tesisleri burada saklayabilirsiniz.</p><a class="btn" href="{{ route('hotels.all') }}">Pet otellerini keşfet</a></div>
  @else
    <p class="favorites-count">{{ $hotels->total() }} tesis kaydedildi</p>
    <div class="grid favorite-hotels-grid">@foreach($hotels as $h)@include('hotels._card', ['h' => $h])@endforeach</div>
    @if($hotels->hasPages())<div class="blog-pagination">{{ $hotels->onEachSide(1)->links() }}</div>@endif
  @endif
</div>
@endsection
