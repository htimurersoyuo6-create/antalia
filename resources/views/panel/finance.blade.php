@extends('layouts.app')
@section('title', 'Cari ve Muhasebe | Antalya Pet Otel')
@section('content')
<div class="wrap page finance-page">
  <header class="finance-hero">
    <div class="crumb"><a href="{{ route('panel.index') }}">İşletme paneli</a><span aria-hidden="true">›</span> Cari ve muhasebe</div>
    <span class="finance-eyebrow">İŞLETME FİNANSI</span>
    <h1>Cari ve muhasebe</h1>
    <p>Gelir, gider ve bekleyen tahsilatlarınızı tesis bazında tek ekranda izleyin.</p>
    <small>Bu kayıt defteri ödeme kuruluşu veya banka ekstresi yerine geçmez.</small>
  </header>
  <p class="finance-period-note">{{ !empty($filters['from']) || !empty($filters['to']) ? 'Özet, seçtiğiniz kayıt tarihlerini kapsar.' : 'Özet, tüm kayıt tarihlerini kapsar.' }}</p>
  @if($errors->any())<div class="cardbox finance-error" role="alert">{{ $errors->first() }}</div>@endif
  <div class="finance-summary-grid">
    <article class="finance-stat income"><span aria-hidden="true">↗</span><div><strong>{{ number_format((float)$summary['income'],2,',','.') }} ₺</strong><small>Tahsil edilen gelir</small></div></article>
    <article class="finance-stat pending"><span aria-hidden="true">◷</span><div><strong>{{ number_format((float)$summary['pending_income'],2,',','.') }} ₺</strong><small>Bekleyen gelir</small></div></article>
    <article class="finance-stat expense"><span aria-hidden="true">↘</span><div><strong>{{ number_format((float)$summary['expenses'],2,',','.') }} ₺</strong><small>Ödenen gider</small></div></article>
    <article class="finance-stat pending"><span aria-hidden="true">◷</span><div><strong>{{ number_format((float)$summary['pending_expenses'],2,',','.') }} ₺</strong><small>Bekleyen gider</small></div></article>
    <article class="finance-stat net"><span aria-hidden="true">₺</span><div><strong>{{ number_format((float)$summary['income']-(float)$summary['expenses'],2,',','.') }} ₺</strong><small>Net nakit akışı</small></div></article>
  </div>
  <section class="cardbox finance-panel">
    <header class="finance-panel-heading"><span aria-hidden="true">＋</span><div><h2>Cari kayıt ekle</h2><p>Gerçekleşen veya bekleyen gelir ve giderleri kaydedin.</p></div></header>
    @if($hotels->isEmpty())<p>Bu hesapta kayıtlı bir işletme bulunamadı.</p>@else
    <form method="post" action="{{ route('panel.finance.store') }}" class="finance-form-grid">@csrf
      <label>İşletme<select name="hotel_id" required>@foreach($hotels as $hotel)<option value="{{ $hotel->id }}" @selected(old('hotel_id')==$hotel->id)>{{ $hotel->name }}</option>@endforeach</select></label>
      <label>Kayıt türü<select name="type" required><option value="income">Gelir / tahsilat</option><option value="expense" @selected(old('type')==='expense')>Gider / ödeme</option></select></label>
      <label>Kategori<input name="category" maxlength="80" placeholder="Konaklama, mama, bakım…" value="{{ old('category') }}" required></label>
      <label>Açıklama<input name="description" maxlength="180" value="{{ old('description') }}" required></label>
      <label>Tutar (₺)<input type="number" name="amount" min="0.01" max="9999999999" step="0.01" value="{{ old('amount') }}" required></label>
      <label>Durum<select name="status"><option value="pending">Bekliyor</option><option value="paid" @selected(old('status')==='paid')>Ödendi / tahsil edildi</option></select></label>
      <label>Vade tarihi<input type="date" name="due_date" value="{{ old('due_date') }}"></label>
      <label>Ödeme yöntemi<input name="payment_method" maxlength="60" placeholder="Nakit, havale…"></label>
      <label>Referans / belge no<input name="reference" maxlength="100"></label>
      <label class="wide">Not<textarea name="notes" maxlength="2000" rows="2"></textarea></label>
      <div><button class="btn" type="submit">Kaydı ekle</button></div>
    </form>@endif
  </section>
  <form method="get" action="{{ route('panel.finance') }}" class="cardbox finance-filters">
    <label>İşletme<select name="hotel_id"><option value="">Tüm işletmeler</option>@foreach($hotels as $hotel)<option value="{{ $hotel->id }}" @selected(($filters['hotel_id']??'')==$hotel->id)>{{ $hotel->name }}</option>@endforeach</select></label>
    <label>Tür<select name="type"><option value="">Tümü</option><option value="income" @selected(($filters['type']??'')==='income')>Gelir</option><option value="expense" @selected(($filters['type']??'')==='expense')>Gider</option></select></label>
    <label>Durum<select name="status"><option value="">Tümü</option><option value="pending" @selected(($filters['status']??'')==='pending')>Bekliyor</option><option value="paid" @selected(($filters['status']??'')==='paid')>Ödendi</option><option value="cancelled" @selected(($filters['status']??'')==='cancelled')>İptal edildi</option></select></label>
    <label>Kayıt tarihi · başlangıç<input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
    <label>Kayıt tarihi · bitiş<input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
    <button class="btn ghost" type="submit">Filtrele</button>
    <a class="btn ghost" href="{{ route('panel.finance.export', request()->query()) }}">CSV raporu indir</a>
  </form>
  <section class="cardbox finance-panel finance-history"><header class="finance-panel-heading"><span aria-hidden="true">▤</span><div><h2>İşlem geçmişi</h2><p>Filtrelediğiniz cari hareketler ve kayıt durumları.</p></div></header><div class="finance-table-wrap"><table class="table"><thead><tr><th>Tarih / vade</th><th>İşletme / rezervasyon</th><th>Tür / kategori</th><th>Açıklama</th><th>Tutar</th><th>Durum</th><th>İşlem</th></tr></thead><tbody>
  @forelse($entries as $entry)
  <tr><td>{{ $entry->created_at->format('d.m.Y') }}<small>@if($entry->due_date) Vade {{ $entry->due_date->format('d.m.Y') }} @endif</small></td><td>{{ $entry->hotel->name }}@if($entry->reservation)<small>{{ $entry->reservation->code }}</small>@endif</td><td>{{ $entry->type==='income'?'Gelir':'Gider' }}<small>{{ $entry->category }}</small></td><td>{{ $entry->description }}@if($entry->reference)<small>Ref: {{ $entry->reference }}</small>@endif @if($entry->notes)<details><summary>Notu göster</summary>{{ $entry->notes }}</details>@endif</td><td>{{ number_format((float)$entry->amount,2,',','.') }} ₺</td><td>{{ ['pending'=>'Bekliyor','paid'=>'Ödendi','cancelled'=>'İptal edildi'][$entry->status]??$entry->status }}<small>{{ $entry->paid_at?->format('d.m.Y H:i') }}</small></td><td>
  @if($entry->status==='pending')
  @if(!str_starts_with($entry->category, 'Rezervasyon uzatma #'))<details><summary>Düzenle</summary><form method="post" action="{{ route('panel.finance.update',$entry) }}">@csrf @method('PUT')<label>Kategori<input name="category" value="{{ $entry->category }}" maxlength="80" required></label><label>Açıklama<input name="description" value="{{ $entry->description }}" maxlength="180" required></label><label>Tutar<input type="number" name="amount" min="0.01" step="0.01" value="{{ $entry->amount }}" required></label><label>Vade<input type="date" name="due_date" value="{{ $entry->due_date?->toDateString() }}"></label><label>Ödeme yöntemi<input name="payment_method" value="{{ $entry->payment_method }}" maxlength="60"></label><label>Referans<input name="reference" value="{{ $entry->reference }}" maxlength="100"></label><label>Not<textarea name="notes" maxlength="2000">{{ $entry->notes }}</textarea></label><button class="btn sm">Kaydet</button></form></details>@endif
  <form method="post" action="{{ route('panel.finance.settle',$entry) }}">@csrf @method('PATCH')<input type="hidden" name="action" value="pay"><input name="payment_method" maxlength="60" placeholder="Tahsilat yöntemi"><button class="btn sm">Ödendi olarak işaretle</button></form>
  @if(!str_starts_with($entry->category, 'Rezervasyon uzatma #'))<form method="post" action="{{ route('panel.finance.settle',$entry) }}">@csrf @method('PATCH')<input type="hidden" name="action" value="cancel"><button class="btn sm danger">İptal et</button></form>@endif
  @endif</td></tr>
  @empty<tr><td colspan="7">Bu filtrelerde cari kayıt bulunmuyor.</td></tr>@endforelse
  </tbody></table></div>{{ $entries->links() }}</section>
</div>
<style>
.finance-page{width:calc(100% - 40px);max-width:1220px;margin:0 auto;padding:28px 0 58px;color:#29322c}
.finance-hero{padding:24px 28px;margin:0 0 17px;border:1px solid #eee4d9;border-radius:22px;background:linear-gradient(112deg,#fff5ec,#fff 61%,#f0f5ed);box-shadow:0 8px 24px rgba(34,39,32,.045)}
.finance-hero .crumb{display:flex;align-items:center;gap:8px;margin:0 0 17px;color:#818981;font-size:12px}.finance-hero .crumb a{color:#68746b;text-decoration:none}.finance-eyebrow{color:#b94b22;font-size:10px;font-weight:900;letter-spacing:.13em}.finance-hero h1{margin:5px 0 7px;color:#252b25;font-size:clamp(25px,4vw,36px);font-weight:900;letter-spacing:-.04em}.finance-hero p{margin:0;color:#616e65;font-size:15px;line-height:1.6}.finance-hero>small{display:block;margin-top:6px;color:#858e87;font-size:11px}.finance-period-note{margin:0 0 12px;color:#758078;font-size:12px}.finance-error{margin-bottom:14px;padding:13px 16px;border-color:#efcdbf;background:#fff6f2;color:#943a22}
.finance-summary-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:11px}.finance-stat{display:flex;align-items:center;gap:11px;min-width:0;min-height:94px;padding:15px;border:1px solid #e8e8e1;border-radius:16px;background:#fff;box-shadow:0 5px 18px rgba(32,38,32,.035)}.finance-stat>span,.finance-panel-heading>span{display:grid;place-items:center;flex:none;width:38px;height:38px;border-radius:12px;background:#edf6ef;color:#34815a;font-size:19px;font-weight:850}.finance-stat.pending>span{background:#fff4e3;color:#9b6a16}.finance-stat.expense>span{background:#fff0e9;color:#bf4b25}.finance-stat.net>span{background:#eef3ff;color:#4568a4}.finance-stat>div{min-width:0}.finance-stat strong,.finance-stat small{display:block}.finance-stat strong{color:#29342d;font-size:clamp(17px,1.8vw,24px);font-weight:900;letter-spacing:-.035em;overflow-wrap:anywhere}.finance-stat small{margin-top:4px;color:#707b73;font-size:11px;line-height:1.35}
.finance-panel{margin-top:16px;padding:21px;border:1px solid #e8e8e1;border-radius:18px;background:#fff;box-shadow:0 7px 22px rgba(32,38,32,.035)}.finance-panel-heading{display:flex;align-items:center;gap:11px;margin-bottom:17px}.finance-panel-heading>span{background:#fff0e7;color:#c84b21}.finance-panel-heading h2{margin:0;color:#303831;font-size:18px;font-weight:850}.finance-panel-heading p{margin:3px 0 0;color:#788179;font-size:12px}
.finance-form-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.finance-form-grid label,.finance-filters label{display:grid;gap:6px;min-width:0;color:#48534b;font-size:12px;font-weight:750}.finance-form-grid input,.finance-form-grid select,.finance-form-grid textarea,.finance-filters input,.finance-filters select{width:100%;min-width:0;min-height:42px;padding:9px 11px;border:1px solid #dfe5de;border-radius:10px;background:#fff;color:#303a33;font:inherit;font-size:13px}.finance-form-grid input:focus,.finance-form-grid select:focus,.finance-form-grid textarea:focus,.finance-filters input:focus,.finance-filters select:focus{outline:3px solid #ffede5;border-color:#ed6943}.finance-form-grid .wide{grid-column:1/-1}.finance-form-grid button{min-height:40px}
.finance-filters{display:grid;grid-template-columns:repeat(5,minmax(0,1fr)) auto auto;gap:10px;align-items:end;margin-top:14px;padding:16px;border:1px solid #e8e8e1;border-radius:16px;background:#fdfdfb}.finance-filters .btn{min-height:42px;display:inline-flex;align-items:center;justify-content:center;white-space:nowrap}.finance-table-wrap{max-width:100%;overflow-x:auto;border:1px solid #eeece6;border-radius:12px}.finance-history .table{width:100%;min-width:900px;border-collapse:collapse}.finance-history .table th{background:#f6f8f5;color:#647068;font-size:10px;letter-spacing:.07em;white-space:nowrap}.finance-history .table td{color:#424d45;font-size:12px;vertical-align:top}.finance-history .table td small{display:block;margin-top:4px;color:#858e87}.finance-history details{margin-top:5px}.finance-history details form{display:grid;gap:8px;min-width:180px;padding:10px;border:1px solid #ece9e3;border-radius:10px;background:#fafaf8}.finance-history details input,.finance-history details textarea{max-width:100%;padding:7px;border:1px solid #dfe5de;border-radius:8px;font:inherit}.finance-history td form{margin:5px 0}.finance-history td .btn{margin:2px;padding:7px 9px;font-size:11px}
@media(max-width:1050px){.finance-summary-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.finance-filters{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:760px){.finance-page{width:calc(100% - 28px);padding:20px 0 42px}.finance-hero{padding:21px 19px;border-radius:18px}.finance-summary-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.finance-stat{min-height:82px;padding:12px}.finance-stat>span{width:34px;height:34px}.finance-form-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.finance-filters{grid-template-columns:repeat(2,minmax(0,1fr))}.finance-panel{padding:16px}}
@media(max-width:480px){.finance-page{width:calc(100% - 22px);padding-top:15px}.finance-hero{padding:18px 16px}.finance-hero .crumb{margin-bottom:12px}.finance-hero p{font-size:13px}.finance-summary-grid{grid-template-columns:1fr 1fr}.finance-stat{gap:8px;padding:10px}.finance-stat strong{font-size:16px}.finance-stat small{font-size:10px}.finance-form-grid,.finance-filters{grid-template-columns:1fr}.finance-form-grid .wide{grid-column:auto}.finance-panel-heading h2{font-size:16px}}
</style>
@endsection
