@extends('layouts.app')
@section('title','Sipariş ve Teslimat')
@push('head')
<style>
.shop{max-width:1020px;margin:0 auto;padding:clamp(24px,5vw,48px) 20px 72px;color:#20251f}.shop h1{font-size:clamp(30px,4vw,42px);margin:0 0 8px}.shop-intro{color:#68736b;margin:0 0 24px;line-height:1.6}.shop-card{border:1px solid #eee8e1;border-radius:20px;padding:22px;margin:16px 0;background:#fff;box-shadow:0 8px 24px #252c2508}.shop label{display:block;font-weight:650;margin:14px 0 7px}.shop input,.shop textarea,.shop select{width:100%;padding:12px 13px;border:1px solid #ddd7d0;border-radius:12px;font:inherit;background:#fff}.shop-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px}.shop-btn{background:#f3400a;color:#fff;border:0;border-radius:12px;padding:14px 20px;font:inherit;font-weight:700;cursor:pointer;text-decoration:none;display:inline-flex;justify-content:center}.shop-payment{border:1px solid #e9e3dc;border-radius:16px;padding:17px;margin:12px 0;cursor:pointer}.shop-payment:has(input:checked){border-color:#f3400a;background:#fff8f3}.shop-payment-choice{display:flex!important;align-items:center;gap:12px;margin:0!important}.shop-payment-choice input{width:20px;height:20px;accent-color:#f3400a}.shop-payment small{display:block;color:#68736b;margin:6px 0 0 32px;line-height:1.5}.shop-total{display:flex;justify-content:space-between;border-top:1px solid #eee8e1;padding-top:14px;margin-top:14px;font-size:18px;font-weight:800}.shop-alert{background:#fff8e8;border:1px solid #f5dfa3;padding:14px;border-radius:14px;color:#5b4b1f;line-height:1.6}.shop-btn:disabled{opacity:.55;cursor:not-allowed}@media(max-width:650px){.shop{padding:24px 15px 52px}.shop-card{padding:16px}.shop-grid{grid-template-columns:1fr;gap:8px}.shop-btn{width:100%}}
</style>
@endpush
@section('content')
<div class="shop">
    <h1>Sipariş bilgileri</h1>
    <p class="shop-intro">Her işletmenin ürünleri ayrı sipariş olarak hazırlanır. Teslimat ve ödeme bilgilerinizi kontrol edin.</p>
    @if($errors->any())<div class="shop-alert" role="alert">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('shop.place') }}" id="shop-checkout-form">
        @csrf
        <section class="shop-card">
            <h2>İletişim bilgileriniz</h2>
            <div class="shop-grid">
                <div><label for="buyer_name">Ad</label><input id="buyer_name" name="buyer_name" value="{{ old('buyer_name',auth()->user()->name) }}" required autocomplete="given-name"></div>
                <div><label for="buyer_surname">Soyad</label><input id="buyer_surname" name="buyer_surname" value="{{ old('buyer_surname') }}" autocomplete="family-name" data-card-required></div>
                <div><label for="buyer_phone">Telefon</label><input id="buyer_phone" name="buyer_phone" value="{{ old('buyer_phone',auth()->user()->phone) }}" required autocomplete="tel"></div>
                <div><label for="buyer_email">E-posta</label><input id="buyer_email" name="email" type="email" value="{{ old('email',auth()->user()->email) }}" autocomplete="email" data-card-required></div>
            </div>
        </section>
        @foreach($groups as $hid=>$g)
            @php($deliveryFee = $g['hotel']->shop_fulfillment!=='pickup' && (!is_numeric($g['hotel']->shop_free_delivery_min) || $g['subtotal'] < (float)$g['hotel']->shop_free_delivery_min) ? (float)$g['hotel']->shop_delivery_fee : 0)
            <section class="shop-card">
                <h2>{{ $g['hotel']->name }}</h2>
                <p>{{ collect($g['items'])->map(fn($i)=>$i['product']->name.' × '.$i['quantity'])->join(' · ') }}</p>
                <label for="fulfillment-{{ $hid }}">Teslim şekli</label>
                <select id="fulfillment-{{ $hid }}" name="orders[{{ $hid }}][fulfillment]" required>
                    @if(in_array($g['hotel']->shop_fulfillment,['pickup','both']))<option value="pickup">Otelden teslim al</option>@endif
                    @if(in_array($g['hotel']->shop_fulfillment,['delivery','both']))<option value="delivery">Adrese teslim</option>@endif
                </select>
                <div class="shop-grid">
                    <div><label>Şehir</label><input name="orders[{{ $hid }}][city]" placeholder="Adrese teslim için"></div>
                    <div><label>İlçe</label><input name="orders[{{ $hid }}][district]" placeholder="Adrese teslim için"></div>
                </div>
                <label>Teslimat adresi</label><textarea name="orders[{{ $hid }}][address]" rows="2" placeholder="Adrese teslim için"></textarea>
                <div class="shop-total"><span>Ürünler</span><span>{{ number_format($g['subtotal'],2,',','.') }} ₺</span></div>
                @if($deliveryFee>0)<div class="shop-total"><span>Tahmini teslimat</span><span>{{ number_format($deliveryFee,2,',','.') }} ₺</span></div>@endif
            </section>
        @endforeach
        <section class="shop-card">
            <h2>Ödeme yöntemi</h2>
            <label class="shop-payment"><span class="shop-payment-choice"><input type="radio" name="payment_method" value="pay_at_delivery" checked><span><b>Teslim alırken öde</b></span></span><small>Ödemeyi ürünleri teslim alırken doğrudan işletmeye yaparsınız.</small></label>
            @if($paymentAvailable)
                <label class="shop-payment"><span class="shop-payment-choice"><input type="radio" name="payment_method" value="iyzico_sandbox"><span><b>Kartla öde · iyzico test modu</b></span></span><small>Bu yalnızca sandbox testidir; gerçek para çekilmez ve işletmelere gerçek ödeme aktarılmaz.</small></label>
                <div id="iyzico-fields" hidden>
                    <div class="shop-alert">iyzico sandbox formu için fatura bilgilerinizi girin. T.C. kimlik numaranız bu uygulamada saklanmaz.</div>
                    <div class="shop-grid">
                        <div><label for="identity_number">T.C. kimlik numarası</label><input id="identity_number" name="identity_number" inputmode="numeric" autocomplete="off" maxlength="11" data-card-required></div>
                        <div><label for="buyer_city">Şehir</label><input id="buyer_city" name="buyer_city" autocomplete="address-level1" data-card-required></div>
                        <div><label for="buyer_address">Fatura adresi</label><input id="buyer_address" name="buyer_address" autocomplete="street-address" data-card-required></div>
                        <div><label for="zip_code">Posta kodu</label><input id="zip_code" name="zip_code" autocomplete="postal-code"></div>
                    </div>
                </div>
            @else
                <div class="shop-alert">Kartla ödeme sandbox ayarları henüz yapılandırılmadı. Mağaza siparişlerinde teslimatta ödeme kullanılabilir.</div>
            @endif
            <label for="customer_note">Sipariş notu (isteğe bağlı)</label><textarea id="customer_note" name="customer_note" rows="3">{{ old('customer_note') }}</textarea>
        </section>
        <button class="shop-btn" type="submit">Siparişi oluştur</button>
    </form>
</div>
@if($paymentAvailable)
<script>
(()=>{const form=document.getElementById('shop-checkout-form'), fields=document.getElementById('iyzico-fields'), radios=[...form.querySelectorAll('input[name="payment_method"]')];function sync(){const card=form.querySelector('input[name="payment_method"]:checked')?.value==='iyzico_sandbox';fields.hidden=!card;fields.querySelectorAll('[data-card-required]').forEach(el=>el.required=card);form.querySelectorAll('[data-card-required]').forEach(el=>el.required=card)}radios.forEach(el=>el.addEventListener('change',sync));sync()})();
</script>
@endif
@endsection
