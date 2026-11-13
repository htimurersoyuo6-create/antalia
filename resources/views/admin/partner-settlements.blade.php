@extends('layouts.admin')
@section('title', 'Partner mutabakatı | Yönetim Paneli')
@section('content')
<div class="wrap page">
  <div class="crumb"><a href="{{ route('admin.statistics') }}">Yönetim</a> › Partner mutabakatı</div>
  <h1>Partner komisyon mutabakatı</h1>

  @if(session('ok'))<div class="alert success">{{ session('ok') }}</div>@endif
  @if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif

  <section class="cardbox">
    <form method="get" class="form" style="max-width:360px">
      <label>Dönem<input type="month" name="period" value="{{ $period }}" onchange="this.form.submit()"></label>
    </form>
    <p style="margin:12px 0 0">
      <b>{{ $totals['count'] }}</b> mutabakat ·
      Brüt: <b>{{ number_format($totals['gross_cents'] / 100, 2, ',', '.') }} TL</b> ·
      Komisyon: <b>{{ number_format($totals['commission_cents'] / 100, 2, ',', '.') }} TL</b> ·
      Net: <b>{{ number_format($totals['net_cents'] / 100, 2, ',', '.') }} TL</b> ·
      Bekleyen: <b>{{ number_format($totals['outstanding_cents'] / 100, 2, ',', '.') }} TL</b>
    </p>
    <form method="post" action="{{ route('admin.partner-settlements.generate') }}" class="form" style="max-width:420px;margin-top:12px">@csrf
      <input type="hidden" name="period" value="{{ $period }}">
      <input type="hidden" name="issue" value="1">
      <button class="btn">Tüm partnerler için oluştur / yenile</button>
    </form>
  </section>

  <section class="cardbox" style="margin-top:16px">
    <h2>{{ $period }} dönemi</h2>
    <table class="table">
      <thead><tr><th>Partner otel</th><th>Komisyon oranı</th><th>Rezervasyon</th><th>Brüt</th><th>Komisyon</th><th>Net</th><th>Durum</th><th></th></tr></thead>
      <tbody>
      @foreach($rows as $row)
        @php($settlement = $row['settlement'])
        <tr>
          <td>{{ $row['partner']->name }}</td>
          <td>%{{ number_format((float)$row['partner']->commission_rate, 2, ',', '.') }}</td>
          <td>{{ $settlement ? $settlement->booking_count : 0 }}</td>
          <td>{{ number_format(($settlement->gross_cents ?? 0) / 100, 2, ',', '.') }} TL</td>
          <td>{{ number_format(($settlement->commission_cents ?? 0) / 100, 2, ',', '.') }} TL</td>
          <td>{{ number_format(($settlement->net_cents ?? 0) / 100, 2, ',', '.') }} TL</td>
          <td>{{ $settlement ? (['draft' => 'Taslak', 'issued' => 'Kesildi', 'paid' => 'Ödendi'][$settlement->status] ?? $settlement->status) : '—' }}</td>
          <td>
            @if($settlement && $settlement->status === 'issued')
              <form method="post" action="{{ route('admin.partner-settlements.pay', $settlement) }}">@csrf
                <button class="btn sm">Ödendi</button>
              </form>
            @elseif(! $settlement)
              <form method="post" action="{{ route('admin.partner-settlements.generate') }}">@csrf
                <input type="hidden" name="period" value="{{ $period }}">
                <input type="hidden" name="partner_id" value="{{ $row['partner']->id }}">
                <input type="hidden" name="issue" value="1">
                <button class="btn sm ghost">Oluştur</button>
              </form>
            @endif
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </section>
</div>
@endsection
