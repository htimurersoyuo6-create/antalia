@extends('layouts.app')
@section('title', 'Tedarik Pazarı')
@section('content')
<div class="wrap page">
  <div class="crumb"><a href="{{ route('panel.index') }}">Panel</a> › Tedarik Pazarı</div>
  <h1>Tedarik Pazarı (B2B)</h1>

  @if(session('ok'))<div class="alert success">{{ session('ok') }}</div>@endif
  @if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif

  @if($hotels->count() > 1)
  <form method="get" class="form" style="max-width:420px;margin-bottom:16px">
    <label>Otel<select name="hotel" onchange="this.form.submit()">@foreach($hotels as $h)<option value="{{ $h->id }}" @selected($h->id===$hotel->id)>{{ $h->name }}</option>@endforeach</select></label>
  </form>
  @endif

  <section class="cardbox">
    <h2>Ürünler · {{ $hotel->name }}</h2>
    @if($products->isEmpty())
      <p>Şu anda B2B olarak satışa açık ürün bulunmuyor.</p>
    @else
      <form method="post" action="{{ route('panel.supply.store') }}">@csrf
        <table class="table">
          <thead><tr><th>Ürün</th><th>Tedarikçi</th><th>Kategori</th><th>Fiyat</th><th>Min. Adet</th><th>Stok</th><th>Adet</th></tr></thead>
          <tbody>
          @foreach($products as $product)
            <tr>
              <td>{{ $product->name }}<input type="hidden" name="items[{{ $loop->index }}][product_id]" value="{{ $product->id }}"></td>
              <td>{{ $product->supplier ?? '-' }}</td>
              <td>{{ $product->category }}</td>
              <td>{{ number_format((float)$product->price, 2, ',', '.') }} TL</td>
              <td>{{ $product->min_order_qty }}</td>
              <td>{{ $product->stock }}</td>
              <td><input type="number" name="items[{{ $loop->index }}][quantity]" min="{{ $product->min_order_qty }}" max="{{ $product->stock }}" value="0" style="width:80px"></td>
            </tr>
          @endforeach
          </tbody>
        </table>
        <label>Not<input type="text" name="note" maxlength="500" placeholder="Sipariş notu"></label>
        <button class="btn">Siparişi oluştur (fatura ile)</button>
      </form>
    @endif
  </section>

  <section class="cardbox" style="margin-top:16px">
    <h2>Son siparişlerim</h2>
    @if($orders->isEmpty())
      <p>Henüz sipariş yok.</p>
    @else
      <table class="table">
        <thead><tr><th>Kod</th><th>Tarih</th><th>Adet</th><th>Tutar</th><th>Durum</th><th>Ödeme</th></tr></thead>
        <tbody>
        @foreach($orders as $order)
          <tr>
            <td>{{ $order->code }}</td>
            <td>{{ $order->created_at?->format('d.m.Y H:i') }}</td>
            <td>{{ $order->items_count }}</td>
            <td>{{ number_format((float)$order->total, 2, ',', '.') }} TL</td>
            <td>{{ \App\Models\ShopOrder::STATUS[$order->status] ?? $order->status }}</td>
            <td>{{ $order->payment_status === 'unpaid' ? 'Ödenmedi' : $order->payment_status }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    @endif
  </section>
</div>
@endsection
