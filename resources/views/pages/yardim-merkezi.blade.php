@extends('layouts.app')
@php($siteBrand = \App\Models\Setting::al('site_name', config('app.name', 'Pet Otel')))

@section('title', 'Yardım Merkezi | ' . $siteBrand)
@section('desc', $desc)
@section('ogtitle', 'Yardım Merkezi')
@section('ogdesc', $desc)
@push('head')
{!! sema_ld(sema_bolum('WebPage', [
    'name' => 'Yardım Merkezi',
    'description' => $desc,
    'url' => url()->current(),
    'inLanguage' => 'tr-TR',
])) !!}
{!! sema_kirinti([
    ['ad' => 'Ana Sayfa', 'url' => route('home')],
    ['ad' => 'Yardım Merkezi'],
]) !!}
@endpush

@section('content')
<div class="wrap help-center-page">
  <div class="crumb"><a href="{{ route('home') }}">Ana Sayfa</a> › Yardım Merkezi</div>
  <header class="help-center-hero"><span>{{ $siteBrand }} · DESTEK</span><h1>Size nasıl yardımcı olabiliriz?</h1><p>{{ $desc }}</p><a href="{{ route('contact') }}">Destek ekibine ulaşın <span aria-hidden="true">→</span></a></header>

  <section class="help-center-section">
    <h2>Başlangıç rehberleri</h2>
    <div class="hub-izgara">
      @foreach($rehberler as $r)
        <a class="hub-kutu" href="{{ $r['url'] }}">
          <b aria-hidden="true">{{ $r['ikon'] }}</b>
          <strong>{{ $r['ad'] }}</strong>
          <span>{{ $r['ozet'] }}</span>
        </a>
      @endforeach
    </div>
  </section>

  <section class="help-center-section">
    <h2>Rezervasyon ve iptal</h2>
    <div class="hub-izgara">
      @foreach($islemler as $r)
        <a class="hub-kutu" href="{{ $r['url'] }}">
          <b aria-hidden="true">{{ $r['ikon'] }}</b>
          <strong>{{ $r['ad'] }}</strong>
          <span>{{ $r['ozet'] }}</span>
        </a>
      @endforeach
    </div>
  </section>

  @if(!empty($sss))
  <section class="help-center-section help-center-faq">
    <h2>Sık sorulan sorular</h2>
    @foreach($sss as $s)
      <details class="sss">
        <summary>{{ $s['soru'] }}</summary>
        <div>{{ $s['cevap'] }}</div>
      </details>
    @endforeach
    <p><a href="{{ route('pages.show', 'sss') }}">Tüm sıkça sorulan sorular →</a></p>
  </section>
  @endif

  <section class="help-center-section">
    <h2>Yasal metinler</h2>
    <div class="hub-izgara">
      @foreach($yasal as $y)
        <a class="hub-kutu" href="{{ $y['url'] }}">
          <b aria-hidden="true">📜</b>
          <strong>{{ $y['ad'] }}</strong>
          <span>{{ $y['ozet'] }}</span>
        </a>
      @endforeach
    </div>
  </section>

  <section class="cta help-center-cta">
    <h2>Sorunuz mu var?</h2>
    <p>Rezervasyon, ödeme veya ilan değişiklikleri için bize yazın.</p>
    <a class="btn" href="{{ route('contact') }}">İletişim formu</a>
  </section>
</div>
<style>
.help-center-page{max-width:1180px;padding:22px 0 68px}.help-center-page>.crumb{margin:0 0 14px}.help-center-hero{display:grid;justify-items:start;gap:9px;margin:0 0 26px;padding:clamp(25px,4vw,44px);border:1px solid #efe5d9;border-radius:25px;background:radial-gradient(circle at 90% 5%,#eef5e9 0,transparent 34%),linear-gradient(115deg,#fff3e9,#fffdf9 60%,#f1f6ef);box-shadow:0 10px 32px #24291f08}.help-center-hero>span{color:#b9512c;font-size:9px;font-weight:900;letter-spacing:.15em}.help-center-hero h1{max-width:780px;margin:2px 0;color:#252b26;font-size:clamp(31px,4.4vw,49px);font-weight:900;line-height:1.1;letter-spacing:-.045em}.help-center-hero p{max-width:780px;margin:0;color:#68736b;font-size:14px;line-height:1.75}.help-center-hero>a{display:inline-flex;align-items:center;gap:9px;min-height:42px;margin-top:8px;padding:0 15px;border-radius:11px;background:#ef4a1b;color:#fff;font-size:11px;font-weight:850;text-decoration:none}.help-center-section{margin:22px 0 27px!important;padding:20px;border:1px solid #eae9e3;border-radius:19px;background:#fff;box-shadow:0 8px 25px #24291f06}.help-center-section>h2{margin:0 0 5px!important;color:#2c342d;font-size:17px!important;font-weight:900;letter-spacing:-.025em}.help-center-section .hub-izgara{grid-template-columns:repeat(4,minmax(0,1fr));gap:11px;margin:13px 0 0}.help-center-section .hub-kutu{grid-template-columns:42px minmax(0,1fr);column-gap:10px;min-height:95px;padding:13px;border-radius:14px;box-shadow:none}.help-center-section .hub-kutu>b{width:40px;height:40px;border-radius:12px;font-size:18px}.help-center-section .hub-kutu>strong{font-size:11px;line-height:1.35}.help-center-section .hub-kutu>span{font-size:10px;line-height:1.4}.help-center-section .hub-kutu:hover{transform:translateY(-2px);border-color:#f2cdbb;box-shadow:0 8px 18px #512c1c0a}.help-center-faq .sss{margin-top:8px;border:1px solid #edece6;border-radius:11px;background:#fbfbf9}.help-center-faq .sss summary{padding:12px 14px;color:#455047;font-size:11px;font-weight:800}.help-center-faq .sss>div{padding:0 14px 13px;color:#707a72;font-size:11px;line-height:1.65}.help-center-faq>p{margin:13px 0 0;font-size:11px;font-weight:800}.help-center-cta{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:20px 23px;border:1px solid #e4eadd;border-radius:17px;background:linear-gradient(100deg,#f4f8f1,#fffaf4)}.help-center-cta h2{margin:0;color:#2b342c;font-size:17px}.help-center-cta p{margin:4px 0 0;color:#747e75;font-size:11px}.help-center-cta .btn{min-height:40px;white-space:nowrap}
@media(max-width:900px){.help-center-section .hub-izgara{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:580px){.help-center-page{width:calc(100% - 24px);padding:16px 0 40px}.help-center-hero{padding:23px 20px;border-radius:20px}.help-center-hero h1{font-size:32px}.help-center-hero p{font-size:12px}.help-center-section{margin:14px 0 17px!important;padding:15px;border-radius:16px}.help-center-section .hub-izgara{grid-template-columns:1fr;gap:8px}.help-center-section .hub-kutu{min-height:75px}.help-center-cta{align-items:flex-start;flex-direction:column;padding:17px}.help-center-cta .btn{width:100%}}
</style>
@endsection

