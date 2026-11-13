<?php
namespace Database\Seeders;
use App\Models\{City, Hotel, Post, RoomType, User};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PetOtelSeeder extends Seeder
{
    public function run(): void
    {
        $city = City::firstOrCreate(['slug' => 'antalya'], ['name' => 'Antalya']);
        foreach (['Muratpaşa','Konyaaltı','Kepez','Döşemealtı','Aksu','Alanya','Manavgat','Kemer'] as $n) {
            $city->districts()->firstOrCreate(['slug' => Str::slug($n)], ['name' => $n]);
        }
        $owner = User::firstOrCreate(['email' => 'ornek@antalyapetotel.com'], [
            'name' => 'Örnek Sahip', 'password' => Hash::make(Str::random(16)), 'role' => 'hotel_owner',
        ]);
        // ÖRNEK veriler: gerçek otellerle değiştirilecek
        $samples = [
            ['Örnek Köpek Oteli A', 'Muratpaşa', 1, 0, 4.9, 180, 1000],
            ['Örnek Kedi Oteli B', 'Konyaaltı', 0, 1, 4.8, 97, 600],
            ['Örnek Pet Resort C', 'Kemer', 1, 1, 4.8, 120, 1350],
        ];
        foreach ($samples as [$name, $ilce, $dog, $cat, $rate, $cnt, $price]) {
            $h = Hotel::firstOrCreate(['slug' => Str::slug($name)], [
                'owner_id' => $owner->id, 'name' => $name, 'city_id' => $city->id,
                'district_id' => $city->districts()->where('name', $ilce)->value('id'),
                'description' => 'Örnek açıklama. Gerçek otel bilgisi panelden girilecek.',
                'accepts_dogs' => $dog, 'accepts_cats' => $cat, 'status' => 'active',
                'rating_avg' => $rate, 'rating_count' => $cnt,
            ]);
            if ($h->rooms()->count() === 0) {
                RoomType::create(['hotel_id' => $h->id, 'name' => 'Standart Oda',
                    'pet_type' => $dog ? 'dog' : 'cat', 'base_price' => $price]);
            }
        }
        $this->posts();
    }

    private function posts(): void
    {
        $posts = [
            ['Pet otele bırakmadan önce kontrol listesi', 'Dostunuzu otele emanet etmeden önce hazırlamanız gerekenler.',
             "Aşı karnesi güncel mi? Çoğu otel kuduz, karma aşı ve parazit uygulamalarının güncel olmasını ister.\n\nAlışık olduğu mama, ilaç ve oyuncaklarını yanına verin; mama değişimi sindirim sorunlarına yol açabilir.\n\nOtelin ruhsatını, günlük bakım programını ve acil durumda çalıştığı veteriner bilgisini sorun.\n\nMümkünse kısa bir deneme konaklaması veya tanışma ziyareti ile başlayın."],
            ['İyi bir pet otel nasıl seçilir?', 'Lisans, hijyen, personel ve şeffaflık: dikkat edilecek beş ölçüt.',
             "1) Geçerli işletme ruhsatı.\n2) Temiz, havalandırılan ve güvenli ortak alanlar.\n3) Hayvan başına yeterli personel.\n4) Günlük fotoğraf/video bilgilendirmesi.\n5) Şeffaf fiyat ve net iptal koşulları.\n\nPet Otel üzerinde listelenen oteller bu ölçütlere göre incelenir."],
        ];
        foreach ($posts as [$title, $ex, $body]) {
            Post::firstOrCreate(['slug' => Str::slug($title)], ['title' => $title, 'excerpt' => $ex, 'body' => $body, 'published_at' => now()]);
        }
    }
}