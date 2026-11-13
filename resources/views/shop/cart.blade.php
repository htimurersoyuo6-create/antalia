@extends('layouts.app')
@section('title','Alışveriş Sepeti')
@push('head')
<style>
.shop{max-width:1050px;margin:0 auto;padding:clamp(24px,5vw,48px) 20px 72px;color:#20251f}.shop-heading{display:flex;justify-content:space-between;align-items:end;gap:16px;margin-bottom:24px}.shop-heading h1{font-size:clamp(30px,4vw,42px);margin:0}.shop-heading p{color:#68736b;margin:8px 0 0}.shop-box{border:1px solid #eee8e1;border-radius:20px;padding:16px;margin:14px 0;display:flex;gap:18px;align-items:center;background:#fff;box-shadow:0 8px 24px #252c2508}.shop-box img{width:96px;height:88px;object-fit:cover;border-radius:14px;background:#f6f3ee}.shop-box-info{flex:1;min-width:0}.shop-box-info b{font-size:18px}.shop-box-info p{color:#68736b;margin:7px 0 0}.shop-qty{display:flex;align-items:center;gap:10px;color:#59645e;font-weight:600}.shop input{width:84px;padding:11px;border:1px solid #ddd7d0;border-radius:12px;font:inherit}.shop-btn{background:#f3400a;color:white;border:0;border-radius:12px;padding:13px 19px;text-decoration:none;font:inherit;font-weight:700;display:inline-flex;justify-content:center;align-items:center;cursor:pointer}.shop-btn-secondary{background:#fff;color:#28342d;border:1px solid #e9e3dc}.shop-actions{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:22px}.shop-empty{padding:clamp(28px,6vw,56px);text-align:center;border:1px solid #eee8df;border-radius:24px;background:linear-gradient(120deg,#fff9f2,#f2f7f0);box-shadow:0 12px 34px #252c2508}.shop-empty-icon{font-size:42px}.shop-empty h2{font-size:26px;margin:12px 0 8px}.shop-empty p{color:#68736b;max-width:520px;margin:0 auto 20px;line-height:1.7}@media(max-width:600px){.shop{padding:24px 15px 52px}.shop-box{align-items:flex-start;gap:12px;padding:12px}.shop-box img{width:76px;height:76px}.shop-box-info b{font-size:16px}.shop-qty{flex-direction:column;align-items:flex-start;gap:5px}.shop-actions>*{flex:1}.shop-heading{display:block}}
</style>
@endpush
@section('content')
<div class="shop">
    <header class="shop-heading">
        <div><h1>Sepetiniz</h1><p>Ürünleri gözden geçirin, ardından teslimat ve sipariş adımına geçin.</p></div>
        <a class="shop-btn shop-btn-secondary" href="{{ route('shop.index') }}">Alışverişe devam et</a>
    </header>
    <form method="post" action="{{ route('shop.cart.update') }}">
        @csrf
        @forelse($lines as $line)
            @php($p = $line['product'])
            <article class="shop-box">
                <img src="{{ $p->image_path ? asset('storage/'.$p->image_path) : asset('img/logo-kapak.png') }}" alt="{{ $p->name }}">
                <div class="shop-box-info"><b>{{ $p->name }}</b><p>{{ $p->hotel->name }} · {{ number_format($p->price,2,',','.') }} ₺</p></div>
                <label class="shop-qty">Adet <input type="number" min="0" max="20" name="quantities[{{ $p->id }}]" value="{{ $line['quantity'] }}"></label>
            </article>
        @empty
            <section class="shop-empty">
                <div class="shop-empty-icon" aria-hidden="true">🛍️</div>
                <h2>Sepetiniz şu an boş</h2>
                <p>Pet otellerin sunduğu ürünleri keşfedin. Beğendiğiniz ürünleri sepete ekleyerek siparişinizi oluşturabilirsiniz.</p>
                <a class="shop-btn" href="{{ route('shop.index') }}">Mağazayı keşfet</a>
            </section>
        @endforelse
        @if(count($lines) > 0)
            <div class="shop-actions">
                <button class="shop-btn shop-btn-secondary" type="submit">Sepeti güncelle</button>
                <a class="shop-btn" href="{{ route('shop.checkout') }}">Teslimat ve siparişe geç</a>
            </div>
        @endif
    </form>
</div>
@endsection
