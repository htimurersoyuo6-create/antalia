@extends('layouts.app')
@section('title','7/24 Acil Veteriner ve Nöbetçi Klinikler | Antalya')
@section('content')
<div class="wrap page"><h1>7/24 açık acil veteriner klinikleri</h1><p>Harita yalnızca 7/24 hizmet verdiği kaydedilmiş klinikleri gösterir. Acil durumda yola çıkmadan önce telefonla ulaşılabilirliği doğrulayın.</p>@if(auth()->user()?->isAdmin())<p><a href="{{ route('admin.veterinaries.index') }}">Klinik çalışma saati ve harita kayıtlarını yönet</a></p>@endif
<button id="locate" class="btn">Konumuma göre sırala</button>
@if($searchHotelId)<button id="hotel-locate" class="btn" data-hotel-id="{{ $searchHotelId }}">Otel konumuna göre sırala</button>@endif
<p id="message" role="status" aria-live="polite">{{ $clinics->count() }} adet 7/24 klinik listeleniyor.</p>
<div id="vet-map" style="height:380px;border-radius:16px;margin:18px 0"></div><div id="clinics" class="cardbox" style="padding:16px">
@foreach($clinics as $clinic)<article style="padding:12px;border-bottom:1px solid #eee"><h2>{{ $clinic['name'] }} <small>24 saat</small></h2><p>{{ $clinic['district'] }} · {{ $clinic['address'] }}</p><a href="{{ $clinic['call_url'] }}">Acil ara {{ $clinic['phone'] }}</a> · <a target="_blank" rel="noopener" href="{{ $clinic['maps_url'] }}">OpenStreetMap rotası</a></article>@endforeach
</div></div>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(function(){var map=L.map('vet-map').setView([36.8969,30.7133],10),layer=L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(map),markers=L.featureGroup().addTo(map),btn=document.getElementById('locate'),hotelBtn=document.getElementById('hotel-locate'),msg=document.getElementById('message'),list=document.getElementById('clinics');
function show(rows){list.textContent='';markers.clearLayers();rows.forEach(function(c){var el=document.createElement('article'),h=document.createElement('h2'),p=document.createElement('p'),call=document.createElement('a'),maps=document.createElement('a');el.style.cssText='padding:12px;border-bottom:1px solid #eee';h.textContent=c.name+' · 24 saat';p.textContent=(c.district||'')+' · '+c.address+(c.distance_km!==null?' · '+c.distance_km+' km':'');call.href=c.call_url;call.textContent='Acil ara '+(c.phone||'');maps.href=c.maps_url;maps.target='_blank';maps.rel='noopener';maps.textContent='OpenStreetMap rotası';el.append(h,p,call,document.createTextNode(' · '),maps);list.appendChild(el);L.marker([c.latitude,c.longitude]).addTo(markers).bindPopup(c.name+' · 24 saat')});if(rows.length)map.fitBounds(markers.getBounds().pad(.15));msg.textContent=rows.length?rows.length+' adet 7/24 klinik listeleniyor.':'Bu bölgede kayıtlı, koordinatlı 7/24 klinik bulunamadı.'}
function load(params){msg.textContent='7/24 klinikler aranıyor…';fetch('{{ route('vets.nearby') }}?format=json&'+params.toString(),{headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(d){show(d.clinics||[])}).catch(function(){msg.textContent='Klinik listesi alınamadı.'})}
btn.onclick=function(){if(!navigator.geolocation){msg.textContent='Cihaz konumu kullanılamıyor.';return}msg.textContent='Konum alınıyor…';navigator.geolocation.getCurrentPosition(function(pos){var q=new URLSearchParams({lat:pos.coords.latitude,lng:pos.coords.longitude});load(q)},function(){msg.textContent='Konum izni alınamadı.'},{enableHighAccuracy:true,timeout:12000})};
if(hotelBtn)hotelBtn.onclick=function(){load(new URLSearchParams({hotel_id:hotelBtn.dataset.hotelId}))};
show(@json($clinics->values()));
})();
</script>
@endsection
