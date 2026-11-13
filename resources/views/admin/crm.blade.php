@extends('layouts.admin')
@section('title','CRM ve misafir ilişkileri')
@section('heading','CRM ve misafir ilişkileri')
@section('sub','Müşteriler, işletmeler, rezervasyonlar ve gelen iletişim talepleri tek ekranda.')
@section('actions')<a class="a-btn" href="{{ route('admin.users.create') }}">+ Kullanıcı ekle</a>@endsection
@section('content')
<section class="a-kpis">
 <a class="a-kpi" href="{{ route('admin.users.index',['rol'=>'customer']) }}"><span class="k-ic t">👤</span><div><small>Aktif müşteriler</small><b>{{ $stats['customers'] }}</b><span class="a-kpi-go">Müşteri kayıtları →</span></div></a>
 <a class="a-kpi" href="{{ route('admin.users.index',['rol'=>'hotel_owner']) }}"><span class="k-ic b">🏨</span><div><small>İşletme hesapları</small><b>{{ $stats['owners'] }}</b><span class="a-kpi-go">İşletmeleri görüntüle →</span></div></a>
 <a class="a-kpi" href="{{ route('admin.reservations.index') }}"><span class="k-ic a">📅</span><div><small>Yanıt bekleyen rezervasyon</small><b>{{ $stats['pending'] }}</b><span class="a-kpi-go">Rezervasyonları aç →</span></div></a>
 <a class="a-kpi" href="{{ route('admin.messages') }}"><span class="k-ic b">✉</span><div><small>İletişim talepleri</small><b>{{ $stats['contact'] }}</b><span class="a-kpi-go">Gelen kutusunu aç →</span></div></a>
</section>
<section class="a-card"><div class="a-card-h"><div><h2>Son rezervasyon ilişkileri</h2><p>Müşteri ve işletme arasındaki son işlem hareketleri.</p></div><a class="a-btn sm ghost" href="{{ route('admin.reservations.index') }}">Tüm rezervasyonlar</a></div>
<div class="a-tw"><table class="a-table"><thead><tr><th>Müşteri</th><th>İşletme / oda</th><th>Tarih</th><th>Durum</th><th class="r">İlişki</th></tr></thead><tbody>
@forelse($reservations as $res)<tr><td><b>{{ $res->user?->name }}</b><small>{{ $res->user?->email }} · {{ $res->code }}</small></td><td><b>{{ $res->hotel?->name }}</b><small>{{ $res->room?->name }}</small></td><td>{{ $res->check_in?->format('d.m.Y') }} – {{ $res->check_out?->format('d.m.Y') }}<small>{{ number_format((float)$res->total_price,0,',','.') }} ₺</small></td><td><span class="a-badge {{ $res->status==='confirmed'?'active':($res->status==='pending'?'pending':'muted') }}">{{ $res->status_label }}</span></td><td class="r"><a class="a-btn sm ghost" href="{{ route('admin.users.edit',$res->user) }}">Müşteri CRM kaydı</a></td></tr>@empty<tr><td colspan="5" class="a-empty">Henüz rezervasyon yok.</td></tr>@endforelse
</tbody></table></div>{{ $reservations->links() }}</section>
<section class="a-card"><div class="a-card-h"><div><h2>Yeni iletişim talepleri</h2><p>Yanıtlanması gereken müşteri adayları ve site mesajları.</p></div><a class="a-btn sm ghost" href="{{ route('admin.messages') }}">Gelen kutusu</a></div><div class="a-msgs">
@forelse($recentMessages as $m)<article class="a-msg"><span class="a-av sm">{{ mb_strtoupper(mb_substr($m->name,0,1)) }}</span><div><header><b>{{ $m->name }}</b><a href="mailto:{{ $m->email }}">{{ $m->email }}</a><time>{{ $m->created_at?->format('d.m.Y H:i') }}</time></header><p>{{ \Illuminate\Support\Str::limit($m->message,220) }}</p></div></article>@empty<div class="a-empty">Yeni iletişim talebi yok.</div>@endforelse
</div></section>
@endsection
