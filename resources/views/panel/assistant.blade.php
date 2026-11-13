@extends('layouts.app')
@section('title', 'Akıllı asistan')
@section('content')
<div class="wrap page">
  <div class="crumb"><a href="{{ route('panel.index') }}">Panel</a> › Akıllı asistan</div>
  <h1>SSS tabanlı misafir asistanı</h1>
  <div class="cardbox" style="margin:0 0 16px;border-color:#f0dfc8;background:#fffaf0;color:#596477;line-height:1.55">
    Yanıtlar aktif soru-cevap kayıtlarıyla eşleştirilir; bu sürüm üretken bir yapay zekâ modeline bağlı değildir. Eşleşmeyen sorular otomatik olarak işletmeye iletilmez; ziyaretçiye tesis iletişim seçenekleri gösterilir.
  </div>

  @if(session('ok'))<div class="alert success">{{ session('ok') }}</div>@endif
  @if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif

  @if($hotels->count() > 1)
    <form method="get" class="form" style="max-width:420px;margin-bottom:16px">
      <label>Otel
        <select name="hotel" onchange="this.form.submit()">
          @foreach($hotels as $h)
            <option value="{{ $h->id }}" @selected($h->id === $hotel->id)>{{ $h->name }}</option>
          @endforeach
        </select>
      </label>
    </form>
  @endif

  <div class="grid3" style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px">
    <div class="cardbox"><small>Son 30 gün soru</small><div style="font-size:24px;font-weight:700">{{ $stats['total_30d'] }}</div></div>
    <div class="cardbox"><small>İnsana devredilen</small><div style="font-size:24px;font-weight:700">{{ $stats['handoff_30d'] }}</div></div>
    <div class="cardbox"><small>Devir oranı</small><div style="font-size:24px;font-weight:700">%{{ number_format((float)$stats['handoff_rate'], 1, ',', '.') }}</div></div>
  </div>

  <section class="cardbox">
    <h2>Asistanı test et</h2>
    <form method="post" action="{{ route('panel.assistant.test') }}" class="form">@csrf
      <input type="hidden" name="hotel_id" value="{{ $hotel->id }}">
      <label>Soru
        <textarea name="question" rows="2" required placeholder="Örn. check-in saat kaçta?">{{ old('question') }}</textarea>
      </label>
      <button class="btn">Yanıt al</button>
    </form>

    @if($result)
      <div class="alert {{ $result['handoff'] ? 'error' : 'success' }}" style="margin-top:12px">
        <b>Yanıt:</b> {{ $result['answer'] }}<br>
        <small>Güven: %{{ number_format((float)$result['confidence'] * 100, 0, ',', '.') }} · {{ $result['handoff'] ? 'İnsana devredildi' : 'SSS eşleşti' }}</small>
      </div>
    @endif
  </section>

  <section class="cardbox">
    <h2>Soru-cevap havuzu</h2>
    <form method="post" action="{{ route('panel.assistant.faq.store') }}" class="form">@csrf
      <input type="hidden" name="hotel_id" value="{{ $hotel->id }}">
      <div class="row2">
        <label>Kapsam
          <select name="scope">
            <option value="hotel">Bu otel</option>
            @if(auth()->user()->isAdmin())<option value="global">Genel havuz</option>@endif
          </select>
        </label>
        <label>Kategori<input type="text" name="category" maxlength="60" value="{{ old('category') }}" placeholder="genel"></label>
      </div>
      <label>Soru<input type="text" name="question" maxlength="255" required value="{{ old('question') }}"></label>
      <label>Yanıt<textarea name="answer" rows="3" required>{{ old('answer') }}</textarea></label>
      <label>Anahtar kelimeler (virgülle)<input type="text" name="keywords" maxlength="500" value="{{ old('keywords') }}" placeholder="check-in, giriş, saat"></label>
      <div style="display:flex;gap:8px">
        <button class="btn">Kaydet</button>
      </div>
    </form>

    <form method="post" action="{{ route('panel.assistant.seed') }}" style="margin-top:10px">@csrf
      <input type="hidden" name="hotel_id" value="{{ $hotel->id }}">
      <button class="btn ghost">Varsayılan 10 soruyu yükle</button>
    </form>

    <table class="table" style="margin-top:12px">
      <thead><tr><th>Kapsam</th><th>Soru</th><th>Yanıt</th><th>Durum</th><th></th></tr></thead>
      <tbody>
      @forelse($faqs as $faq)
        <tr>
          <td>{{ $faq->hotel_id === null ? 'Genel' : 'Otel' }}</td>
          <td>{{ $faq->question }}</td>
          <td>{{ \Illuminate\Support\Str::limit($faq->answer, 80) }}</td>
          <td>{{ $faq->is_active ? 'Aktif' : 'Pasif' }}</td>
          <td style="white-space:nowrap">
            <form method="post" action="{{ route('panel.assistant.faq.toggle', $faq) }}" style="display:inline">@csrf
              <button class="btn sm ghost">{{ $faq->is_active ? 'Kapat' : 'Aç' }}</button>
            </form>
            <form method="post" action="{{ route('panel.assistant.faq.destroy', $faq) }}" style="display:inline" onsubmit="return confirm('Silinsin mi?')">@csrf @method('DELETE')
              <button class="btn sm ghost">Sil</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="5">Henüz kayıt yok.</td></tr>
      @endforelse
      </tbody>
    </table>
  </section>

  <section class="cardbox">
    <h2>Son görüşmeler</h2>
    <table class="table">
      <thead><tr><th>Tarih</th><th>Kanal</th><th>Soru</th><th>Güven</th><th>Devir</th></tr></thead>
      <tbody>
      @forelse($interactions as $interaction)
        <tr>
          <td>{{ $interaction->created_at->format('d.m.Y H:i') }}</td>
          <td>{{ $interaction->channel }}</td>
          <td>{{ \Illuminate\Support\Str::limit($interaction->question, 70) }}</td>
          <td>%{{ number_format((float)$interaction->confidence * 100, 0, ',', '.') }}</td>
          <td>{{ $interaction->handoff ? 'Evet' : 'Hayır' }}</td>
        </tr>
      @empty
        <tr><td colspan="5">Görüşme yok.</td></tr>
      @endforelse
      </tbody>
    </table>
  </section>
</div>
@endsection
