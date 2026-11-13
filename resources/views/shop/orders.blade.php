@extends('layouts.app')
@section('title','Siparişlerim')
@section('content')<div class="wrap page"><h1>Siparişlerim</h1>@forelse($orders as $o)<article class="card" style="padding:18px;margin:12px 0"><b>{{ $o->code }}</b> · {{ $o->hotel->name }} · {{ $o->status_label }}<p>{{ $o->items->pluck('name_snapshot')->join(', ') }} · {{ number_format($o->total,2,',','.') }} ₺</p><a class="btn" href="{{ route('shop.order',$o) }}">Siparişi görüntüle</a></article>@empty<p>Henüz siparişiniz yok.</p>@endforelse{{ $orders->links() }}</div>@endsection
