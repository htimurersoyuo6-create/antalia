<section class="wrap" id="konaklama-magaza" style="margin-top:16px">
  <div class="cardbox">
    <h2>Konaklama içi mağaza</h2>
    @php($stayProducts = $reservation->hotel->shopProducts()->where('active', true)->whereIn('audience', ['b2c', 'both'])->orderBy('name')->get())
    @if($stayProducts->isEmpty())
      <p class="hint">Bu otel için aktif mağaza ürünü bulunmuyor.</p>
    @else
      <form method="post" action="{{ route('panel.care.instay.store', $reservation) }}" class="form">@csrf
        <div class="row2">
          <label>Ürün
            <select name="product_id" required>
              @foreach($stayProducts as $product)
                <option value="{{ $product->id }}">{{ $product->name }} — {{ number_format((float) $product->price, 2, ',', '.') }} TL</option>
              @endforeach
            </select>
          </label>
          <label>Adet<input type="number" name="quantity" value="1" min="1" max="20" required></label>
        </div>
        <button class="btn">Hesaba ekle</button>
      </form>
      <form method="post" action="{{ route('panel.care.instay.complete', $reservation) }}" style="margin-top:10px">@csrf
        <button class="btn sm ghost">Siparişi teslim edildi olarak işaretle</button>
      </form>
    @endif
  </div>
</section>
