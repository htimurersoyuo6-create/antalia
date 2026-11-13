(function () {
  var form = document.getElementById('fiyatForm');
  if (!form) return;
  var MAX = 5;
  var list = document.getElementById('fiyatOteller');
  var err = document.getElementById('fiyatHata');
  var sonuc = document.getElementById('fiyatSonuc');
  var onizleme = document.getElementById('fiyatOnizleme');
  var linkler = document.getElementById('fiyatLinkler');

  function bugun() {
    var d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }
  form.giris.min = bugun();
  form.cikis.min = bugun();

  function secili() { return Array.prototype.slice.call(list.querySelectorAll('input[name=otel]:checked')); }

  function filtrele() {
    var tur = form.tur.value, ilce = form.ilce.value;
    Array.prototype.forEach.call(list.querySelectorAll('li[data-ilce]'), function (li) {
      var uygun = (li.getAttribute('data-' + tur) === '1') && (!ilce || li.getAttribute('data-ilce') === ilce);
      li.hidden = !uygun;
      if (!uygun) { var c = li.querySelector('input'); if (c) c.checked = false; }
    });
    Array.prototype.forEach.call(form.querySelectorAll('[data-only]'), function (el) { el.hidden = el.getAttribute('data-only') !== tur; });
    var b = form.boyut; if (b && form.tur.value !== 'kopek') b.value = '';
  }
  form.tur.addEventListener('change', filtrele);
  form.ilce.addEventListener('change', filtrele);
  list.addEventListener('change', function (e) {
    if (e.target && e.target.name === 'otel' && secili().length > MAX) { e.target.checked = false; hata('En fazla ' + MAX + ' tesis seçebilirsiniz.'); } else { hata(''); }
  });
  filtrele();

  function hata(m) { err.textContent = m; err.hidden = !m; }
  function tr(v) { if (!v) return ''; var p = v.split('-'); return p[2] + '.' + p[1] + '.' + p[0]; }
  function temizle(s) { return String(s || '').replace(/[\r\n]+/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 140); }

  function mesaj(adi) {
    var tur = form.tur.value === 'kedi' ? 'kedi' : 'köpek';
    var adet = Math.max(1, Math.min(5, parseInt(form.adet.value, 10) || 1));
    var g = tr(form.giris.value), c = tr(form.cikis.value), tarih = '';
    if (g && c) tarih = g + ' - ' + c + ' tarihleri arasında ';
    else if (g) tarih = g + ' tarihinden itibaren ';
    var s = 'Merhaba' + (adi ? ' ' + adi : '') + ', ' + tarih + adet + ' ' + tur + ' için konaklama müsaitliği ve gecelik fiyat bilgisi almak istiyorum.';
    if (form.tur.value === 'kopek' && form.boyut.value) s += ' Köpeğin boyutu: ' + form.boyut.value + '.';
    var not = temizle(form.not.value);
    if (not) s += ' Not: ' + not + '.';
    return s + ' Antalya Pet Otel üzerinden ulaşıyorum.';
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var s = secili();
    if (!s.length) { hata('Lütfen en az bir tesis seçin.'); return; }
    if (s.length > MAX) { hata('En fazla ' + MAX + ' tesis seçebilirsiniz.'); return; }
    if (form.giris.value && form.cikis.value && form.cikis.value <= form.giris.value) { hata('Çıkış tarihi giriş tarihinden sonra olmalı.'); return; }
    hata('');
    onizleme.textContent = mesaj('');
    while (linkler.firstChild) linkler.removeChild(linkler.firstChild);
    s.forEach(function (c) {
      var li = document.createElement('li');
      var ad = document.createElement('span'); ad.className = 'fiyat-al__ad'; ad.textContent = c.getAttribute('data-name');
      var a = document.createElement('a');
      a.className = 'hotel-whatsapp-link fiyat-al__wa';
      a.setAttribute('data-hotel-slug', c.value);
      a.target = '_blank'; a.rel = 'noopener';
      a.href = 'https://wa.me/' + c.getAttribute('data-wa') + '?text=' + encodeURIComponent(mesaj(c.getAttribute('data-name')));
      a.textContent = "WhatsApp'tan yaz";
      li.appendChild(ad); li.appendChild(a); linkler.appendChild(li);
    });
    sonuc.hidden = false;
    sonuc.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
})();
