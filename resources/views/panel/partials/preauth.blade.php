<section class="wrap" id="on-otorizasyon" style="margin-top:16px">
  <div class="cardbox">
    <h2>Ön otorizasyon (kart blokajı)</h2>
    @if($errors->has('preauth'))<div class="alert error">{{ $errors->first('preauth') }}</div>@endif
    @php($blocks = $preauthBlocks ?? $reservation->preauthBlocks()->latest()->get())
    @forelse($blocks as $block)
      <article style="padding:12px;border-top:1px solid #e8ece7">
        <b>{{ number_format($block->amount_cents / 100, 2, ',', '.') }} {{ $block->currency === 'TRY' ? 'TL' : $block->currency }}</b>
        · {{ ['blocked' => 'Bloke', 'captured' => 'Tahsil edildi', 'released' => 'Serbest bırakıldı'][$block->status] ?? $block->status }}
        · {{ $block->blocked_at?->format('d.m.Y H:i') }}
        @if($block->status === 'blocked')
          <form method="post" action="{{ route('panel.care.preauth.capture', $block) }}" style="display:inline">@csrf<button class="btn sm">Tahsil et</button></form>
          <form method="post" action="{{ route('panel.care.preauth.release', $block) }}" style="display:inline">@csrf<button class="btn sm ghost">Serbest bırak</button></form>
        @endif
      </article>
    @empty
      <p class="hint">Bu rezervasyon için ön otorizasyon blokajı yok.</p>
    @endforelse
    <form method="post" action="{{ route('panel.care.preauth.store', $reservation) }}" class="form" autocomplete="off" style="margin-top:14px">@csrf
      <label>Tutar (TL)<input type="number" name="amount" min="1" max="1000000" step="0.01" required></label>
      <label>Kart sahibi<input name="holder" maxlength="120" required></label>
      <label>Kart numarası<input name="number" inputmode="numeric" pattern="[0-9]{12,19}" maxlength="19" autocomplete="off" required></label>
      <div class="row2">
        <label>Ay<input name="expire_month" inputmode="numeric" pattern="[0-9]{1,2}" maxlength="2" required></label>
        <label>Yıl<input name="expire_year" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" required></label>
        <label>CVC<input name="cvc" inputmode="numeric" pattern="[0-9]{3,4}" maxlength="4" autocomplete="off" required></label>
      </div>
      <button class="btn">Blokaj oluştur</button>
    </form>
  </div>
</section>
