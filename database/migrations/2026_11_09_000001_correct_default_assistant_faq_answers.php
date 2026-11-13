<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $answers = [
            'Check-in ve check-out saatleri nedir?' => [
                'Check-in saatimiz 14:00, check-out saatimiz 12:00. Erken giriş ve geç çıkış müsaitliğe göre ücretli olarak talep edilebilir.',
                'Giriş ve çıkış saatleri tesise ve oda tipine göre değişebilir. Seçtiğiniz tesisin sayfasında saat bilgisi varsa orada yer alır; kesin bilgi için rezervasyon öncesinde tesisle iletişime geçin.',
            ],
            'Aşı zorunluluğu var mı?' => [
                'Kuduz ve karma aşılarının güncel olması gerekir. Aşı karnesini veya veteriner kaydını girişte paylaşmanız yeterli.',
                'Aşı ve sağlık koşulları her tesisin kabul politikasına göre değişebilir. Rezervasyondan önce aşı karnenizi tesisle paylaşarak güncel gereklilikleri teyit edin.',
            ],
            'Mama ve beslenme nasıl yapılıyor?' => [
                'Kendi mamasını getirebilirsiniz; günlük beslenme planı teslim tutanağında kayıt altına alınır. Mama sağlamıyorsanız tesis maması kullanılır.',
                'Beslenme düzeni ve mama sağlama koşulları tesise göre değişebilir. Evcil dostunuzun kullandığı mamayı ve beslenme notlarını rezervasyon talebinizde belirtip tesisle teyit edin.',
            ],
            'İlaç kullanımı yapılıyor mu?' => [
                'Reçeteli ilaçlar saat ve doz bilgisiyle teslim alınır, günlük bakım karnesinde kayıt altına alınır.',
                'İlaç uygulaması her tesiste sunulmayabilir. Rezervasyondan önce ilaç adı, doz ve saat bilgisini tesisle paylaşarak hizmetin uygunluğunu teyit edin.',
            ],
            'Canlı kamera erişimi nasıl çalışır?' => [
                'Konaklama sırasında panelden güvenli erişim kodu oluşturarak yayına bağlanabilirsiniz.',
                'Canlı kamera erişimi tesis tarafından sunuluyorsa tesis detayında belirtilir. Bu hizmet tüm tesislerde bulunmayabilir; konaklama öncesinde ilgili işletmeye danışın.',
            ],
            'Transfer hizmeti var mı?' => [
                'Havalimanı transferi talep üzerine ücretlidir; mesafe ve evcil hayvan sayısına göre fiyat hesaplanır.',
                'Transfer hizmeti ve ücretleri tesise ve güzergâha göre değişir. Seçtiğiniz tesise tarih ve konum bilgisiyle sorarak kullanılabilirliği teyit edin.',
            ],
            'İptal ve iade koşulları nedir?' => [
                'İptal koşulları seçtiğiniz oda ve tarihe göre değişir; rezervasyon detayında görünen politika geçerlidir.',
                'İptal ve iade koşulları tesis, oda ve tarihe göre değişebilir. Rezervasyon talebinizi göndermeden önce ilgili tesisin koşullarını kontrol edin ve işletmeyle teyit edin.',
            ],
            'Ödeme nasıl yapılır?' => [
                'Online ödeme, havale veya tesis içi ödeme seçenekleri sunulur. Fatura talep etmeniz halinde e-Fatura gönderilir.',
                'Ödeme yöntemi ve tahsilat koşulları rezervasyona göre değişebilir. Rezervasyon ekranında ve tesisle yazışmanızda belirtilen koşulları kontrol edin; ödeme bilgilerinizi paylaşmadan önce işletmeyi doğrulayın.',
            ],
            'Tıraş ve bakım hizmeti var mı?' => [
                'Tıraş, tırnak kesimi ve bakım hizmetleri tesisin sunduğu seçeneklere göre ek ücretle yapılır.',
                'Tıraş ve bakım hizmetleri her tesiste sunulmayabilir; hizmet kapsamı ve ücreti değişebilir. Rezervasyon öncesinde ilgili tesisle teyit edin.',
            ],
            'Acil durumda ne yapılıyor?' => [
                'Acil durumda önce kayıtlı veterinerinize, ulaşılamazsa tesisin anlaşmalı 7/24 veterinerine haber verilir ve size bilgi verilir.',
                'Acil durum planı ve veteriner desteği tesisten tesise değişebilir. Rezervasyon öncesinde tesisin acil durum protokolünü ve size nasıl haber vereceğini sorun.',
            ],
        ];

        foreach ($answers as $question => [$old, $new]) {
            DB::table('assistant_faqs')
                ->whereNull('hotel_id')
                ->where('question', $question)
                ->where('answer', $old)
                ->update(['answer' => $new, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Keep the corrected, non-promissory answers if this migration is rolled back.
    }
};
