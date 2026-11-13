{{-- TASLAK METİN: yayına almadan önce bir hukuk danışmanıyla gözden geçirin. --}}
@extends('layouts.app')
@section('title', 'Hakkımızda | Pet Otel')
@section('content')
<div class="wrap page"><article class="prose"><h1>Hakkımızda</h1>
<p>Pet Otel, Antalya’daki pet sahiplerinin güvenilir, lisanslı ve şeffaf fiyatlı kedi–köpek otellerini tek yerden bulup karşılaştırabilmesi için kurulmuştur. {{ \App\Models\Setting::al('booking_requests_enabled', '0') === '1' ? 'Çevrimiçi rezervasyon talepleri, sahipliği doğrulanmış işletmeler için kullanılabilir.' : 'Çevrimiçi rezervasyon talepleri şu anda alınmamaktadır.' }}</p>
<p>Otellerin işletme belgeleri incelenir, yalnızca konaklamayı tamamlayan misafirler yorum yapabilir. Amacımız tatilde dostunuzun güvende olduğunu bilmenizi sağlamaktır.</p>
</article></div>
@endsection
