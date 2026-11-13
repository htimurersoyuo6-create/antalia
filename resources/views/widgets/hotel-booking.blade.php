<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>{{ $hotel->name }} için tarih seçin | Antalya Pet Otel</title>
  <style>
    :root{font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;color:#242b28;background:#fff}
    *{box-sizing:border-box}
    body{margin:0;padding:8px;background:transparent}
    .widget{max-width:480px;margin:0 auto;padding:20px;border:1px solid #eaded4;border-radius:16px;background:#fff;box-shadow:0 8px 24px rgba(43,38,33,.08)}
    .brand{display:flex;align-items:center;gap:9px;margin:0 0 5px;color:#a63a18;font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}
    .brand-mark{display:grid;place-items:center;width:28px;height:28px;border-radius:9px;background:#fff0e8;font-size:15px;letter-spacing:0}
    h1{margin:8px 0 4px;color:#242b28;font-size:20px;line-height:1.25;letter-spacing:-.025em}
    .intro{margin:0 0 15px;color:#69736e;font-size:13px;line-height:1.5}
    .dates{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    label{display:grid;gap:5px;color:#4c5852;font-size:12px;font-weight:700}
    input{width:100%;min-height:43px;padding:9px 10px;border:1px solid #d9dfda;border-radius:9px;background:#fff;color:#242b28;font:inherit;font-size:14px}
    input:focus{outline:3px solid #fce1d3;border-color:#f63c07}
    button{width:100%;min-height:45px;margin-top:12px;border:0;border-radius:10px;background:#f63c07;color:#fff;font:inherit;font-size:14px;font-weight:750;cursor:pointer}
    button:hover{background:#d93406}button:focus-visible{outline:3px solid #f9b89e;outline-offset:2px}
    .foot{margin:10px 0 0;color:#7a827d;font-size:11px;line-height:1.45;text-align:center}
    @media(max-width:360px){.widget{padding:15px}.dates{gap:7px}h1{font-size:18px}}
  </style>
</head>
<body>
  <main class="widget">
    <p class="brand"><span class="brand-mark" aria-hidden="true">🐾</span> Antalya Pet Otel</p>
    <h1>{{ $hotel->name }}</h1>
    <p class="intro">Konaklama tarihlerinizi seçin; odaları ve tesis bilgilerini görün.</p>
    @if($hasRooms)
      <form method="get" action="{{ route('hotels.show', $hotel->slug) }}" target="_top">
        <div class="dates">
          <label for="widget-arrival">Giriş tarihi
            <input id="widget-arrival" type="date" name="giris" value="{{ $arrival }}" min="{{ today()->toDateString() }}" required>
          </label>
          <label for="widget-departure">Çıkış tarihi
            <input id="widget-departure" type="date" name="cikis" value="{{ $departure }}" min="{{ today()->addDay()->toDateString() }}" required>
          </label>
        </div>
        <button type="submit">Tarihleri seçip odaları gör <span aria-hidden="true">→</span></button>
      </form>
    @else
      <p class="intro">Bu tesis henüz oda seçeneği paylaşmadı. Tesis bilgilerini inceleyebilirsiniz.</p>
      <a class="details-link" href="{{ route('hotels.show', $hotel->slug) }}" target="_top">Tesis sayfasına git →</a>
    @endif
    <p class="foot">{{ \App\Models\Setting::al('booking_requests_enabled', '0') === '1' ? 'Tarihleri seçip tesis bilgilerini inceleyin. Çevrimiçi talepler yalnızca sahibi tanımlı ve rezervasyon özelliği etkin tesislerde alınır.' : 'Çevrimiçi rezervasyon talepleri şu anda alınmıyor. Planınızı tesisle doğrudan teyit edin.' }}</p>
  </main>
  <script>
  (function(){var start=document.getElementById('widget-arrival'),end=document.getElementById('widget-departure');if(!start||!end)return;function nextDay(value){var d=new Date(value+'T00:00:00');d.setDate(d.getDate()+1);return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0')}function sync(){var min=nextDay(start.value);end.min=min;if(!end.value||end.value<min)end.value=min}start.addEventListener('change',sync);sync()})();
  </script>
</body>
</html>
