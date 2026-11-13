@extends('layouts.app')
@section('title', 'Şube yönetimi · '.$root->name)
@section('content')
<main class="wrap" style="max-width:1080px;margin:34px auto 64px;padding:0 18px">
  <p><a href="{{ route('panel.hotel.edit', $root) }}">← {{ $root->name }} paneli</a></p>
  <header style="padding:24px;border-radius:20px;background:linear-gradient(120deg,#f5f3ed,#fff);margin:16px 0 22px">
    <span style="font-size:.78rem;letter-spacing:.08em;color:#73816f">ÇOKLU LOKASYON</span>
    <h1 style="margin:.35rem 0">Şube yönetimi</h1>
    <p style="margin:0;color:#59635a">Bu zincirin ana tesisi: <strong>{{ $root->name }}</strong>. Yalnızca size ait tesisleri bağlayabilirsiniz.</p>
  </header>
  @if(session('success'))<div role="status" class="alert alert-success">{{ session('success') }}</div>@endif
  @if($errors->any())<div role="alert" class="alert alert-danger">{{ $errors->first() }}</div>@endif
  <section class="box" style="padding:22px;border:1px solid #e9e6df;border-radius:16px;margin-bottom:20px">
    <h2 style="font-size:1.15rem">Bağlı şubeler <span style="color:#687568">({{ $branches->count() }})</span></h2>
    @forelse($branches as $branch)
      <article style="display:flex;justify-content:space-between;gap:18px;align-items:center;padding:15px 0;border-bottom:1px solid #eee">
        <div><strong>{{ $branch->name }}</strong><div style="color:#667067">{{ $branch->district?->name }}, {{ $branch->city?->name }} · <a href="{{ route('hotels.show', $branch->slug) }}">İlanı görüntüle</a></div></div>
        <form method="post" action="{{ route('panel.branches.detach', [$root, $branch]) }}" onsubmit="return confirm('Bu tesisi zincirden ayırmak istiyor musunuz? Tesis ve rezervasyon kayıtları korunur.')">@csrf @method('DELETE')<button class="btn btn-outline-secondary" type="submit">Zincirden ayır</button></form>
      </article>
    @empty<p>Henüz bağlı şube yok.</p>@endforelse
  </section>
  <section class="box" style="padding:22px;border:1px solid #e9e6df;border-radius:16px">
    <h2 style="font-size:1.15rem">Mevcut tesisinizi şube olarak ekleyin</h2>
    @if($availableHotels->isEmpty())<p>Bağlanabilecek başka bir tesisiniz yok. Yeni tesis kaydı oluşturduktan sonra burada zincire ekleyebilirsiniz.</p>
    @else
      <form method="post" action="{{ route('panel.branches.attach', $root) }}" style="display:flex;flex-wrap:wrap;gap:12px;align-items:end">@csrf
        <label style="flex:1;min-width:250px">Tesis seçin<select class="form-select" name="branch_id" required><option value="">Seçiniz</option>@foreach($availableHotels as $candidate)<option value="{{ $candidate->id }}">{{ $candidate->name }} — {{ $candidate->district?->name }}, {{ $candidate->city?->name }}</option>@endforeach</select></label>
        <button class="btn btn-primary" type="submit">Şube olarak bağla</button>
      </form>
    @endif
  </section>
</main>
@endsection
