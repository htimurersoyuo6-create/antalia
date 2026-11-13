@extends('layouts.app')
@section('title', 'Bakım operasyonu | '.$reservation->code)
@section('content')
<div class="wrap page">
  <div class="crumb"><a href="{{ route('panel.reservations') }}">Rezervasyonlar</a> › {{ $reservation->code }}</div>
  <h1>{{ $reservation->pet->name }} · Bakım operasyonu</h1>
  <p class="hint">{{ $reservation->hotel->name }} · {{ $reservation->check_in->format('d.m.Y') }} – {{ $reservation->check_out->format('d.m.Y') }}</p>
  @if(session('ok'))<div class="alert success">{{ session('ok') }}</div>@endif
  @if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif
  <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px">
    <section class="cardbox"><h2>Günlük bakım karnesi</h2><form method="post" action="{{ route('panel.care.report',$reservation) }}" class="form">@csrf
      <label>Tarih<input type="date" name="report_date" value="{{ today()->toDateString() }}" required></label>
      <label>Beslenme<select name="feeding_status"><option value="not_recorded">Kayıt yok</option><option value="all">Tam yedi</option><option value="half">Kısmen yedi</option><option value="none">Yemedi</option></select></label>
      <label>Mama gramı<input type="number" name="feeding_grams" min="0" max="10000"></label>
      <label>Tuvalet<select name="toilet_status"><option value="not_recorded">Kayıt yok</option><option value="normal">Normal</option><option value="diarrhea">İshal</option><option value="constipated">Kabızlık</option><option value="none">Yapmadı</option></select></label>
      <label>Ruh hali<select name="mood_status"><option value="not_recorded">Kayıt yok</option><option value="happy">Mutlu</option><option value="playful">Oyuncu</option><option value="anxious">Endişeli</option><option value="aggressive">Agresif</option><option value="lethargic">Halsiz</option></select></label>
      <label>Not<textarea name="notes" maxlength="4000"></textarea></label><button class="btn">Bakım karnesini kaydet</button>
    </form></section>
    <section class="cardbox"><h2>Teslim tutanağı</h2><form method="post" action="{{ route('panel.care.belongings',$reservation) }}" class="form">@csrf
      <label>İşlem<select name="phase"><option value="check_in">Check-in teslimi</option><option value="check_out">Check-out teslimi</option></select></label>
      <label>Mama ve özel beslenme<textarea name="food_details" maxlength="4000"></textarea></label>
      <label>Günlük mama gramı<input type="number" name="food_daily_gram" min="0" max="10000"></label>
      <label>İlaçlar (JSON veya kısa açıklama)<textarea name="medications[]" maxlength="1000"></textarea></label>
      <label>Eşyalar (JSON veya kısa açıklama)<textarea name="accessories[]" maxlength="1000"></textarea></label>
      <label>İmza verisi (varsa)<textarea name="check_in_signature" maxlength="200000"></textarea></label><button class="btn">Tutanağı kaydet</button>
    </form></section>
  </div>
  <section class="cardbox" style="margin-top:16px"><h2>Personel görevleri</h2><form method="post" action="{{ route('panel.care.task',$reservation) }}" class="form"><div class="row2"><label>Görev türü<select name="task_type"><option value="feeding">Mama</option><option value="medication">İlaç</option><option value="walk">Gezdirme</option><option value="cleaning">Temizlik</option></select></label><label>Zaman<input type="datetime-local" name="scheduled_at" required></label></div><label>Başlık<input name="title" maxlength="160" required></label><label>Not<textarea name="notes" maxlength="2000"></textarea></label><button class="btn">Görev oluştur</button></form>
  <div style="margin-top:18px">@forelse($tasks as $task)<article style="padding:12px;border-top:1px solid #e8ece7"><b>{{ $task->title }}</b> · {{ $task->task_type }} · {{ $task->scheduled_at->format('d.m.Y H:i') }} · {{ $task->status }} @if($task->status==='pending')<form method="post" action="{{ route('panel.care.task.complete',$task) }}" style="display:inline">@csrf @method('PATCH')<button class="btn sm ghost">Tamamlandı</button></form>@endif</article>@empty<p class="hint">Henüz görev oluşturulmadı.</p>@endforelse</div></section>
</div>
@include('panel.partials.preauth', ['reservation' => $reservation])
@include('panel.partials.stay-extras', ['reservation' => $reservation])
@endsection