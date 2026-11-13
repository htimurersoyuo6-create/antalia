@extends('layouts.app')
@php
    $baslik = $sayfa->title;
    $siteBrand = \App\Models\Setting::al('site_name', config('app.name', 'Pet Otel'));
    $govde  = (string) $sayfa->content;
    $sss    = function_exists('sayfa_sss_cikar') ? sayfa_sss_cikar($govde) : [];
    $ozet   = seo_metin($sayfa->meta_description ?: strip_tags($govde), 158);
@endphp
@section('title', $baslik . ' | ' . $siteBrand)
@section('desc', $ozet)
@section('ogtitle', $baslik)
@section('ogdesc', $ozet)
@push('head')
{!! sema_ld(sema_bolum('WebPage', [
    'name' => $baslik,
    'description' => $ozet,
    'url' => url()->current(),
    'inLanguage' => 'tr-TR',
    'dateModified' => seo_zaman($sayfa->updated_at),
])) !!}
{!! sema_kirinti([
    ['ad' => 'Ana Sayfa', 'url' => route('home')],
    ['ad' => $baslik],
]) !!}
{!! sema_sss($sss) !!}
@endpush
@section('content')
<div class="wrap static-content-page">
<header class="static-content-hero"><div class="crumb"><a href="{{ route('home') }}">Ana Sayfa</a><span aria-hidden="true">/</span><span>{{ $baslik }}</span></div><span class="static-content-kicker">{{ $siteBrand }} · BİLGİ MERKEZİ</span><h1>{{ $baslik }}</h1><p>{{ $ozet }}</p></header>
<article class="prose static-content-article">
{!! makale_guvenli_html($govde) !!}
</article>
</div>
@endsection
