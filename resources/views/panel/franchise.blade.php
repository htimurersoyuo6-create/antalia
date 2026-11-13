@extends('layouts.app')
@section('title', 'Franchise yönetimi')
@section('content')
<div class="wrap page">
  <div class="crumb"><a href="{{ route('panel.index') }}">Panel</a> › Franchise</div>
  <h1>Franchise / marka yönetimi</h1>

  @if(session('ok'))<div class="alert success">{{ session('ok') }}</div>@endif
  @if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif

  @if($groups->count() > 1)
    <form method="get" class="form" style="max-width:420px;margin-bottom:16px">
      <label>Marka
        <select name="group" onchange="this.form.submit()">
          @foreach($groups as $g)
            <option value="{{ $g->id }}" @selected($group && $g->id === $group->id)>{{ $g->name }}</option>
          @endforeach
        </select>
      </label>
    </form>
  @endif

  @if(! $group)
    <section class="cardbox">
      <h2>Marka oluştur</h2>
      <p class="hint">Franchise markası oluşturarak tesislerinizi tek çatı altında yönetin, dönemsel royalti ve pazarlama payı hesaplayın.</p>
      <form method="post" action="{{ route('panel.franchise.store') }}" class="form">@csrf
        <label>Marka adı<input type="text" name="name" maxlength="160" required></label>
        <div class="row2">
          <label>Royalti oranı (%)<input type="number" name="royalty_rate" step="0.01" min="0" max="50" value="5"></label>
          <label>Pazarlama payı (%)<input type="number" name="marketing_fee_rate" step="0.01" min="0" max="50" value="1"></label>
        </div>
        <label>Marka rengi<input type="text" name="brand_color" maxlength="16" placeholder="#1f6f4a"></label>
        <label>Sözleşme notları<textarea name="terms" maxlength="5000" rows="4"></textarea></label>
        <button class="btn">Markayı oluştur</button>
      </form>
    </section>
  @else
    <section class="cardbox">
      <h2>{{ $group->name }}</h2>
      <p class="hint">
        Royalti: %{{ number_format((float)$group->royalty_rate, 2, ',', '.') }} ·
        Pazarlama payı: %{{ number_format((float)$group->marketing_fee_rate, 2, ',', '.') }} ·
        Para birimi: {{ $group->currency }}
      </p>

      <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-top:12px">
        <div class="cardbox" style="padding:12px"><div class="hint">Aktif birim</div><b style="font-size:22px">{{ $summary['units'] }}</b></div>
        <div class="cardbox" style="padding:12px"><div class="hint">Bu ay ciro</div><b style="font-size:22px">{{ number_format($summary['month_gross'], 2, ',', '.') }} TL</b></div>
        <div class="cardbox" style="padding:12px"><div class="hint">Bu ay tahakkuk</div><b style="font-size:22px">{{ number_format($summary['month_due'], 2, ',', '.') }} TL</b></div>
        <div class="cardbox" style="padding:12px"><div class="hint">Bekleyen tahsilat</div><b style="font-size:22px">{{ number_format($summary['outstanding'], 2, ',', '.') }} TL</b></div>
        <div class="cardbox" style="padding:12px"><div class="hint">Tahsil edilen</div><b style="font-size:22px">{{ number_format($summary['paid_total'], 2, ',', '.') }} TL</b></div>
      </div>
    </section>

    <section class="cardbox" style="margin-top:16px">
      <h2>Birimler</h2>
      @if($units->isEmpty())
        <p>Henüz bağlı tesis yok.</p>
      @else
        <table class="table">
          <thead><tr><th>Tesis</th><th>Katılım</th><th>Royalti oranı</th><th>Durum</th><th></th></tr></thead>
          <tbody>
          @foreach($units as $unit)
            <tr>
              <td>{{ $unit->hotel?->name }}</td>
              <td>{{ $unit->joined_at?->format('d.m.Y') ?? '-' }}</td>
              <td>%{{ number_format($unit->effectiveRate(), 2, ',', '.') }}</td>
              <td>{{ $unit->status === 'active' ? 'Aktif' : $unit->status }}</td>
              <td>
                <form method="post" action="{{ route('panel.franchise.detach', [$group, $unit]) }}" onsubmit="return confirm('Tesis markadan ayrılsın mı?')">@csrf @method('DELETE')
                  <button class="btn sm ghost">Ayrıl</button>
                </form>
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      @endif

      @if($availableHotels->isNotEmpty())
        <h3 style="margin-top:16px">Tesis bağla</h3>
        <form method="post" action="{{ route('panel.franchise.attach', $group) }}" class="form" style="max-width:520px">@csrf
          <label>Tesis
            <select name="hotel_id" required>
              @foreach($availableHotels as $hotel)
                <option value="{{ $hotel->id }}">{{ $hotel->name }}</option>
              @endforeach
            </select>
          </label>
          <label>Royalti oranı (opsiyonel, %)<input type="number" name="royalty_rate_override" step="0.01" min="0" max="50" placeholder="Marka varsayılanı"></label>
          <button class="btn">Markaya bağla</button>
        </form>
      @endif
    </section>

    <section class="cardbox" style="margin-top:16px">
      <h2>Royalti bildirimleri</h2>
      <form method="post" action="{{ route('panel.franchise.generate', $group) }}" class="form" style="max-width:520px">@csrf
        <div class="row2">
          <label>Dönem<input type="month" name="period" value="{{ $period }}" required></label>
          <label>Kesilsin mi?<select name="issue"><option value="0">Taslak</option><option value="1">Evet, bildirim kes</option></select></label>
        </div>
        <button class="btn">Royalti hesapla</button>
      </form>

      @if($statements->isEmpty())
        <p class="hint">Henüz bildirim yok.</p>
      @else
        <table class="table" style="margin-top:12px">
          <thead><tr><th>Dönem</th><th>Tesis</th><th>Ciro</th><th>Oran</th><th>Royalti</th><th>Pazarlama</th><th>Toplam</th><th>Durum</th><th></th></tr></thead>
          <tbody>
          @foreach($statements as $statement)
            <tr>
              <td>{{ $statement->period }}</td>
              <td>{{ $statement->hotel?->name }}</td>
              <td>{{ number_format((float)$statement->gross_revenue, 2, ',', '.') }} TL</td>
              <td>%{{ number_format((float)$statement->royalty_rate, 2, ',', '.') }}</td>
              <td>{{ number_format((float)$statement->royalty_amount, 2, ',', '.') }} TL</td>
              <td>{{ number_format((float)$statement->marketing_fee, 2, ',', '.') }} TL</td>
              <td><b>{{ number_format((float)$statement->total_due, 2, ',', '.') }} TL</b></td>
              <td>{{ ['draft' => 'Taslak', 'issued' => 'Kesildi', 'paid' => 'Ödendi', 'void' => 'İptal'][$statement->status] ?? $statement->status }}</td>
              <td>
                @if($statement->status === 'issued')
                  <form method="post" action="{{ route('panel.franchise.pay', [$group, $statement]) }}">@csrf
                    <button class="btn sm">Ödendi</button>
                  </form>
                @endif
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      @endif
    </section>

    <section class="cardbox" style="margin-top:16px">
      <h2>Marka denetimi</h2>
      @if($hotelOptions->isEmpty())
        <p class="hint">Denetim için önce markaya tesis bağlayın.</p>
      @else
        <form method="post" action="{{ route('panel.franchise.audit', $group) }}" class="form">@csrf
          <label>Tesis
            <select name="hotel_id" required>
              @foreach($hotelOptions as $hotel)
                <option value="{{ $hotel['id'] }}">{{ $hotel['name'] }}</option>
              @endforeach
            </select>
          </label>
          <div class="row2">
            @foreach($standards as $key => $label)
              <label>{{ $label }} (0-10)
                <input type="number" name="scores[{{ $key }}]" min="0" max="10" value="8" required>
              </label>
            @endforeach
          </div>
          <label>Notlar<textarea name="notes" maxlength="2000" rows="3"></textarea></label>
          <button class="btn">Denetimi kaydet</button>
        </form>
      @endif

      @if($audits->isNotEmpty())
        <table class="table" style="margin-top:12px">
          <thead><tr><th>Tarih</th><th>Tesis</th><th>Denetçi</th><th>Puan</th><th>Not</th></tr></thead>
          <tbody>
          @foreach($audits as $audit)
            <tr>
              <td>{{ $audit->audit_date?->format('d.m.Y') }}</td>
              <td>{{ $audit->hotel?->name }}</td>
              <td>{{ $audit->auditor?->name ?? '-' }}</td>
              <td><b>{{ $audit->score }}/100</b></td>
              <td>{{ $audit->notes }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      @endif
    </section>
  @endif
</div>
@endsection
