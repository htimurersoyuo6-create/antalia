#!/usr/bin/env python3
"""Fiyat-al sayfası için iki küçük, tekrar çalıştırılabilir (idempotent) ekleme yapar:
1) routes/web.php  -> /fiyat-al rotası
2) resources/views/hotels/index.blade.php -> liste başlığına bağlantı
Kullanım: python3 ekle.py /www/wwwroot/antalyapetotel.com"""
import sys, re, os
root = (sys.argv[1] if len(sys.argv) > 1 else '/www/wwwroot/antalyapetotel.com').rstrip('/')

def read(p):
    with open(os.path.join(root, p), encoding='utf-8') as f: return f.read()
def write(p, s):
    with open(os.path.join(root, p), 'w', encoding='utf-8') as f: f.write(s)

ROUTE = "Route::get('/fiyat-al', [\\App\\Http\\Controllers\\QuoteController::class, 'index'])->name('quote.index');\n"
s = read('routes/web.php')
if 'quote.index' in s:
    print('routes/web.php: zaten ekli')
else:
    m = re.search(r"^Route::post\('/analitik/iletisim'.*\n", s, re.M) or re.search(r"^Route::get\('/\{slug\}'.*\n", s, re.M)
    if not m:
        sys.exit("routes/web.php: ekleme noktası bulunamadı; şu satırı /{slug} rotasından ÖNCE elle ekleyin:\n" + ROUTE)
    pos = m.end() if 'analitik' in m.group(0) else m.start()
    write('routes/web.php', s[:pos] + ROUTE + s[pos:]); print('routes/web.php: rota eklendi')

p = 'resources/views/hotels/index.blade.php'
s = read(p)
OLD = '<span>{{ $adet }} sonuç</span>'
NEW = OLD + "\n        <a class=\"apt-chip\" href=\"{{ route('quote.index') }}\">Birden fazla otelden fiyat al</a>"
if 'quote.index' in s:
    print(p + ': zaten ekli')
elif s.count(OLD) == 1:
    write(p, s.replace(OLD, NEW)); print(p + ': bağlantı eklendi')
else:
    print(p + ": eklenecek yer bulunamadı (sorun değil; sayfa /fiyat-al adresinden yine çalışır). İsterseniz elle ekleyin:\n" + NEW)
