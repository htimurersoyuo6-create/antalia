@extends($admin ? 'layouts.admin' : 'layouts.app')
@if($admin)@section('heading','Özel sohbetler')@section('sub','Müşteri ve tesis arasındaki özel konuşmalar')@endif
@section('title', $admin?'Tüm tesis sohbetleri':(auth()->user()->isOwner()?'Tesis sohbetleri':'Sohbetlerim'))
@section('content')
@if(!$admin)<main class="{{ $partnerPanel ? 'owner-chat-page' : 'wrap chat-page' }}">@else<div class="chat-admin-page">@endif
<section class="detail-panel chat-box {{ $partnerPanel ? 'owner-chat-box' : '' }}">
  <div class="chat-heading {{ $partnerPanel ? 'owner-chat-heading' : '' }}"><div><span class="detail-eyebrow">{{ $admin?'YÖNETİM':(auth()->user()->isOwner()?'İŞLETME PANELİ':'ÜYE PANELİ') }}</span><h1>{{ $admin?'Tüm özel sohbetler':(auth()->user()->isOwner()?'Müşteri sohbetleri':'Tesislerle özel mesajlar') }}</h1><p>Müşteri ile tesis arasındaki yazışmalar özel olarak saklanır.</p></div><nav class="{{ $partnerPanel ? 'owner-chat-filters' : '' }}"><a class="btn ghost {{ request()->query('arsiv') !== '1' ? 'is-active' : '' }}" href="{{ request()->url() }}">Açık sohbetler</a><a class="btn ghost {{ request()->query('arsiv') === '1' ? 'is-active' : '' }}" href="?arsiv=1">Arşiv</a></nav></div>
  @php $chatGroups = $chats->getCollection()->groupBy('hotel_id'); @endphp
  <div class="chat-hotel-groups">
  @forelse($chatGroups as $hotelChats)
    <section class="chat-hotel-group"><header><span class="chat-hotel-icon" aria-hidden="true">⌂</span><div><b>{{ $hotelChats->first()->hotel->name }}</b><small>{{ $hotelChats->count() }} sohbet · {{ $admin ? 'Tesis mesajları' : 'Bu tesisle yazışmalar' }}</small></div></header>
      <div class="chat-list">@foreach($hotelChats as $chat)<a href="{{ $partnerPanel ? route('panel.hotel-chats.show',$chat) : route('hotel-chats.show',$chat) }}" class="chat-list-item"><span class="chat-list-icon">✉</span><span><b>{{ $chat->user?->name ?? $chat->guest_name }}</b><small>{{ $chat->latestMessage?->body ?? 'Mesaj yok' }}</small></span><time>{{ $chat->last_message_at?->format('d.m.Y H:i') }}</time></a>@endforeach</div>
    </section>
  @empty
    @if($partnerPanel && auth()->user()->hotels->isEmpty())<div class="owner-chat-empty"><span aria-hidden="true">⌂</span><div><b>Henüz kayıtlı tesisiniz yok</b><p>Tesisinizi ekledikten sonra müşterilerden gelen özel mesajlar burada, ilgili tesisin altında görünür.</p><a href="{{ route('panel.hotel.create') }}">Tesis ekle <span aria-hidden="true">→</span></a></div></div>@else<div class="hotel-empty-note"><span>✉</span><div><b>Henüz sohbet yok</b><p>Yeni müşteri soruları bu alanda, mesajın geldiği tesise göre listelenecek.</p></div></div>@endif
  @endforelse
  </div>
  {{ $chats->links() }}
</section>
@if(!$admin)</main>@else</div>@endif
@endsection


