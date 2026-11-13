@extends('layouts.app')
@section('title', $pet->name.' · Sağlık dosyası | Antalya Pet Otel')
@section('content')
<div class="wrap page pet-health-page">
  <a class="pet-health-back" href="{{ route('pets.index') }}">← Evcil hayvanlarım</a>
  <header class="pet-health-hero"><div><span class="customer-eyebrow">{{ $pet->species_label }} · SAĞLIK DOSYASI</span><h1>{{ $pet->name }} için sağlık takvimi</h1><p>Pasaport, aşı karnesi ve veteriner kayıtlarını güvenli bir yerde saklayın.</p></div><span class="pet-health-hero-icon">✚</span></header>
  @if(session('ok'))<div class="pet-health-flash">{{ session('ok') }}</div>@endif
  @if($errors->any())<div class="pet-health-errors">{{ $errors->first() }}</div>@endif
  <div class="pet-health-grid">
    <section class="pet-health-card"><div class="pet-health-card-head"><span class="pet-health-icon">▤</span><div><h2>Pasaport ve belgeler</h2><p>PDF veya fotoğraf · en fazla 10 MB</p></div></div>
      <form class="pet-health-form" method="post" enctype="multipart/form-data" action="{{ route('pets.health.documents.store',$pet) }}">@csrf
        <label>Belge adı<input name="title" required maxlength="120" placeholder="Örn. Aşı karnesi"></label>
        <label>Belge türü<select name="document_type"><option value="health_card">Sağlık / aşı karnesi</option><option value="passport">Evcil hayvan pasaportu</option><option value="vaccination_record">Aşı belgesi</option><option value="other">Diğer sağlık belgesi</option></select></label>
        <label>Dosya seçin<input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.webp" required></label>
        <label class="pet-health-consent"><input type="checkbox" name="share_with_active_hotel" value="1"> Rezervasyon tarihlerimde bu belgeyi ilgili tesise göster</label>
        <p class="pet-health-privacy">Belge şifreli özel depoda tutulur ve herkese açık bağlantı oluşturulmaz. Paylaşım varsayılan olarak kapalıdır; seçerseniz yalnızca petinizi alan ve tarihi henüz geçmemiş rezervasyonun tesisi belgeyi görebilir.</p>
        <button class="btn">Belgeyi güvenle ekle</button>
      </form>
      <div class="pet-health-documents">@forelse($documents as $document)<article class="pet-health-document"><span>▧</span><div><b>{{ $document->title }}</b><small>{{ ['health_card'=>'Sağlık / aşı karnesi','passport'=>'Evcil hayvan pasaportu','vaccination_record'=>'Aşı belgesi','other'=>'Diğer'][$document->document_type] ?? 'Sağlık belgesi' }} · {{ number_format($document->size_bytes / 1024,0) }} KB · {{ $document->share_with_active_hotel ? 'İzinli tesise paylaşılır' : 'Özel' }}</small></div><a href="{{ route('pets.health.documents.download',$document) }}">Görüntüle</a><form method="post" action="{{ route('pets.health.documents.delete',$document) }}" onsubmit="return confirm('Bu belge silinsin mi?')">@csrf @method('DELETE')<button aria-label="Belgeyi sil">×</button></form></article>@empty<p class="pet-health-empty">Henüz belge eklenmemiş.</p>@endforelse</div>
    </section>
    <section class="pet-health-card"><div class="pet-health-card-head"><span class="pet-health-icon is-orange">♡</span><div><h2>Aşı takvimi</h2><p>Son uygulamayı ve sonraki hatırlatma tarihini kaydedin.</p></div></div>
      <form class="pet-health-form pet-vaccine-form" method="post" action="{{ route('pets.health.vaccinations.store',$pet) }}">@csrf
        <label>Aşı / uygulama adı<input name="vaccine_name" required maxlength="120" placeholder="Örn. Kuduz"></label>
        <label>Aşı türü<select name="vaccine_type" required><option value="rabies">Kuduz</option><option value="combined">Karma</option><option value="other">Diğer</option></select></label>
        <div class="pet-health-two"><label>Uygulandığı tarih<input type="date" name="administered_at"></label><label>Sonraki hatırlatma<input type="date" name="next_due_at"></label></div>
        <div class="pet-health-two"><label>Klinik (isteğe bağlı)<input name="clinic_name" maxlength="160"></label><label>Veteriner (isteğe bağlı)<input name="vet_name" maxlength="120"></label></div>
        <label>Not / seri no (isteğe bağlı)<textarea name="notes" maxlength="1000" rows="2" placeholder="Eklemek istediğiniz kısa not"></textarea></label>
        <label class="pet-health-consent"><input type="checkbox" name="reminder_enabled" value="1" checked> Tarih yaklaşınca panelimde hatırlat</label>
        <button class="btn">Aşı kaydı ekle</button>
      </form>
      <div class="pet-vaccination-list">@forelse($vaccinations as $vaccine)<article class="pet-vaccination {{ $vaccine->reminder_state === 'overdue' ? 'is-overdue' : ($vaccine->reminder_state === 'upcoming' ? 'is-upcoming' : '') }}"><div class="pet-vaccine-datebox">{{ $vaccine->next_due_at?->format('d') ?? '—' }}<small>{{ $vaccine->next_due_at?->format('M Y') ?? 'Tarih yok' }}</small></div><div class="pet-vaccine-info"><b>{{ $vaccine->vaccine_name }}</b><small>@if($vaccine->administered_at)Uygulandı {{ $vaccine->administered_at->format('d.m.Y') }} · @endif{{ $vaccine->clinic_name ?: 'Klinik bilgisi eklenmemiş' }}</small><span>{{ $vaccine->reminder_state === 'overdue' ? 'Tarih geçti · veterinerinizle görüşün' : ($vaccine->reminder_state === 'upcoming' ? 'Yaklaşıyor · '.$vaccine->next_due_at->format('d.m.Y') : ($vaccine->next_due_at ? 'Planlandı' : 'Sonraki tarih eklenmedi')) }}</span><details class="pet-vaccine-edit"><summary>Kaydı düzenle</summary><form method="post" action="{{ route('pets.health.vaccinations.update',$vaccine) }}">@csrf @method('PUT')<label>Aşı adı<input name="vaccine_name" required value="{{ $vaccine->vaccine_name }}"></label><div class="pet-health-two"><label>Uygulama tarihi<input type="date" name="administered_at" value="{{ $vaccine->administered_at?->format('Y-m-d') }}"></label><label>Hatırlatma tarihi<input type="date" name="next_due_at" value="{{ $vaccine->next_due_at?->format('Y-m-d') }}"></label></div><label>Klinik<input name="clinic_name" value="{{ $vaccine->clinic_name }}"></label><label>Not<textarea name="notes" rows="2">{{ $vaccine->notes }}</textarea></label><label class="pet-health-consent"><input type="checkbox" name="reminder_enabled" value="1" @checked($vaccine->reminder_enabled)> Hatırlatmayı açık tut</label><button class="btn sm">Değişiklikleri kaydet</button></form></details></div><form method="post" action="{{ route('pets.health.vaccinations.delete',$vaccine) }}" onsubmit="return confirm('Aşı kaydı silinsin mi?')">@csrf @method('DELETE')<button aria-label="Aşı kaydını sil">×</button></form></article>@empty<p class="pet-health-empty">Henüz aşı kaydı yok. İlk kaydınızı yukarıdan ekleyebilirsiniz.</p>@endforelse</div>
      <p class="pet-health-disclaimer">Takvim yalnızca sizin girdiğiniz tarihlere göre hatırlatma yapar; veteriner önerisi veya sağlık tanısı yerine geçmez.</p>
    </section>
  </div>
  <section class="pet-health-tip"><b>Sağlık dosyanız sizin kontrolünüzde</b><span>Belgeler özel depoda korunur. Tesise gösterim seçeneğini her belge için ayrıca siz açarsınız.</span></section>
</div>

    <section class="pet-health-card">
      <div class="pet-health-card-head"><span class="pet-health-icon is-green">◎</span><div><h2>Aşı kartı OCR</h2><p>Karnenin fotoğrafını yükleyin; tarihler otomatik okunur ve doğrulama kuyruğuna alınır.</p></div></div>
      <form class="pet-health-form" method="post" enctype="multipart/form-data" action="{{ route('pets.health.vaccination-card.store', $pet) }}">@csrf
        <label>Aşı kartı görseli veya PDF<input type="file" name="card" accept="image/*,application/pdf" required></label>
        <button class="btn sm">Kartı yükle ve oku</button>
      </form>
    </section>
    <section class="pet-health-card">
      <div class="pet-health-card-head"><span class="pet-health-icon is-orange">!</span><div><h2>Kayıp pet bildirimi</h2><p>Konumunuzu paylaşın; çevredeki otel sahiplerine ve bölgedeki misafirlere anlık bildirim gider.</p></div></div>
      <form class="pet-health-form" method="post" action="{{ route('pets.lost-alerts.store', $pet) }}">@csrf
        <div class="pet-health-two">
          <label>Enlem<input name="lat" inputmode="decimal" placeholder="36.8969" required></label>
          <label>Boylam<input name="lng" inputmode="decimal" placeholder="30.7133" required></label>
        </div>
        <label>Yarıçap (metre)<input type="number" name="radius_m" min="500" max="50000" value="5000"></label>
        <label>Kısa not<textarea name="note" rows="2" maxlength="500" placeholder="Tasma rengi, son görülme yeri..."></textarea></label>
        <button class="btn sm" onclick="return confirm('Kayıp pet uyarısı tüm bölgeye gönderilecek. Onaylıyor musunuz?')">Uyarıyı yayınla</button>
      </form>
    </section>

@endsection
