<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AssistantFaq;
use App\Models\AssistantInteraction;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SmartAssistantService
{
    private const STOPWORDS = [
        'bir', 've', 'ile', 'için', 'bu', 'şu', 'o', 'mi', 'mı', 'mu', 'mü',
        'ne', 'çok', 'daha', 'ama', 'veya', 'gibi', 'kadar', 'sonra', 'önce',
        'ben', 'sen', 'biz', 'siz', 'var', 'yok', 'olan', 'olarak', 'ise',
        'icin', 'cok', 'daha', 'veya', 'sonra', 'once', 'nedir', 'mi', 'mı', 'mu', 'mü',
    ];

    public function answer(?Hotel $hotel, string $question, ?User $user = null, string $channel = 'web'): array
    {
        $normalized = $this->normalize($question);
        $tokens = $this->tokens($normalized);

        $faqs = AssistantFaq::query()
            ->where('is_active', true)
            ->where(function ($query) use ($hotel): void {
                $query->whereNull('hotel_id');

                if ($hotel) {
                    $query->orWhere('hotel_id', $hotel->id);
                }
            })
            ->orderByDesc('hotel_id')
            ->orderBy('sort_order')
            ->get();

        $threshold = (float) config('services.assistant.threshold', 0.25);
        $best = null;
        $bestScore = 0.0;

        foreach ($faqs as $faq) {
            $keywords = array_filter((array) ($faq->keywords ?? []));

            if ($keywords === []) {
                $keywords = $this->tokens($this->normalize($faq->question));
            }

            $score = 0.0;

            foreach ($keywords as $keyword) {
                $needle = $this->normalize((string) $keyword);

                if ($needle !== '' && str_contains($normalized, $needle)) {
                    $score += 2.0;
                }
            }

            $questionTokens = $this->tokens($this->normalize($faq->question));

            foreach ($tokens as $token) {
                if (in_array($token, $questionTokens, true)) {
                    $score += 1.0;
                }
            }

            $confidence = $score / max(1.0, count($tokens) * 2.0 + 1.0);

            if ($confidence > $bestScore) {
                $bestScore = $confidence;
                $best = $faq;
            }
        }

        $confidence = round(min(1.0, $bestScore), 2);
        $handoff = $best === null || $confidence < $threshold;

        $answer = $handoff
            ? (string) config('services.assistant.fallback', 'Bu konuda ekibimiz size en doğru bilgiyi verecek. Sorunuzu tesise iletiyorum, kısa süre içinde dönüş yapılacak.')
            : (string) $best->answer;

        $interaction = AssistantInteraction::create([
            'hotel_id' => $hotel?->id,
            'user_id' => $user?->id,
            'channel' => $channel,
            'question' => mb_substr($question, 0, 2000),
            'answer' => mb_substr($answer, 0, 4000),
            'matched_faq_id' => $handoff ? null : $best->id,
            'confidence' => $confidence,
            'handoff' => $handoff,
        ]);

        Log::info('assistant.answer', [
            'interaction_id' => $interaction->id,
            'hotel_id' => $hotel?->id,
            'confidence' => $confidence,
            'handoff' => $handoff,
        ]);

        return [
            'answer' => $answer,
            'confidence' => $confidence,
            'handoff' => $handoff,
            'faq_id' => $handoff ? null : $best->id,
            'interaction_id' => $interaction->id,
        ];
    }

    public function seedDefaults(?int $hotelId = null): int
    {
        $defaults = [
            ['Check-in ve check-out saatleri nedir?', 'Giriş ve çıkış saatleri tesise ve oda tipine göre değişebilir. Seçtiğiniz tesisin sayfasında saat bilgisi varsa orada yer alır; kesin bilgi için rezervasyon öncesinde tesisle iletişime geçin.', ['check-in', 'checkin', 'giriş', 'çıkış', 'saat', 'erken', 'geç']],
            ['Aşı zorunluluğu var mı?', 'Aşı ve sağlık koşulları her tesisin kabul politikasına göre değişebilir. Rezervasyondan önce aşı karnenizi tesisle paylaşarak güncel gereklilikleri teyit edin.', ['aşı', 'kuduz', 'karma', 'karne', 'vaccine']],
            ['Mama ve beslenme nasıl yapılıyor?', 'Beslenme düzeni ve mama sağlama koşulları tesise göre değişebilir. Evcil dostunuzun kullandığı mamayı ve beslenme notlarını rezervasyon talebinizde belirtip tesisle teyit edin.', ['mama', 'beslenme', 'yemek', 'su', 'gram']],
            ['İlaç kullanımı yapılıyor mu?', 'İlaç uygulaması her tesiste sunulmayabilir. Rezervasyondan önce ilaç adı, doz ve saat bilgisini tesisle paylaşarak hizmetin uygunluğunu teyit edin.', ['ilaç', 'reçete', 'doz', 'tedavi']],
            ['Canlı kamera erişimi nasıl çalışır?', 'Canlı kamera erişimi tesis tarafından sunuluyorsa tesis detayında belirtilir. Bu hizmet tüm tesislerde bulunmayabilir; konaklama öncesinde ilgili işletmeye danışın.', ['kamera', 'canlı', 'yayın', 'izleme']],
            ['Transfer hizmeti var mı?', 'Transfer hizmeti ve ücretleri tesise ve güzergâha göre değişir. Seçtiğiniz tesise tarih ve konum bilgisiyle sorarak kullanılabilirliği teyit edin.', ['transfer', 'havalimanı', 'ulaşım', 'servis']],
            ['İptal ve iade koşulları nedir?', 'İptal ve iade koşulları tesis, oda ve tarihe göre değişebilir. Rezervasyon talebinizi göndermeden önce ilgili tesisin koşullarını kontrol edin ve işletmeyle teyit edin.', ['iptal', 'iade', 'vazgeçme', 'politika']],
            ['Ödeme nasıl yapılır?', 'Ödeme yöntemi ve tahsilat koşulları rezervasyona göre değişebilir. Rezervasyon ekranında ve tesisle yazışmanızda belirtilen koşulları kontrol edin; ödeme bilgilerinizi paylaşmadan önce işletmeyi doğrulayın.', ['ödeme', 'kredi kartı', 'havale', 'fatura', 'taksit']],
            ['Tıraş ve bakım hizmeti var mı?', 'Tıraş ve bakım hizmetleri her tesiste sunulmayabilir; hizmet kapsamı ve ücreti değişebilir. Rezervasyon öncesinde ilgili tesisle teyit edin.', ['tıraş', 'kuaför', 'bakım', 'tırnak', 'yıkama']],
            ['Acil durumda ne yapılıyor?', 'Acil durum planı ve veteriner desteği tesisten tesise değişebilir. Rezervasyon öncesinde tesisin acil durum protokolünü ve size nasıl haber vereceğini sorun.', ['acil', 'veteriner', 'hasta', 'yaralanma', '7/24']],
        ];

        $created = 0;

        foreach ($defaults as $index => [$question, $answer, $keywords]) {
            $exists = AssistantFaq::query()
                ->where('hotel_id', $hotelId)
                ->where('question', $question)
                ->exists();

            if ($exists) {
                continue;
            }

            AssistantFaq::create([
                'hotel_id' => $hotelId,
                'question' => $question,
                'answer' => $answer,
                'keywords' => $keywords,
                'category' => 'genel',
                'is_active' => true,
                'sort_order' => $index,
            ]);

            $created++;
        }

        return $created;
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = strtr($text, [
            'ı' => 'i', 'İ' => 'i', 'ş' => 's', 'Ş' => 's', 'ğ' => 'g', 'Ğ' => 'g',
            'ü' => 'u', 'Ü' => 'u', 'ö' => 'o', 'Ö' => 'o', 'ç' => 'c', 'Ç' => 'c',
            'â' => 'a', 'î' => 'i', 'û' => 'u',
        ]);
        $text = preg_replace('~[^a-z0-9\s]+~', ' ', $text) ?? $text;

        return trim(preg_replace('~\s+~', ' ', $text) ?? $text);
    }

    private function tokens(string $normalized): array
    {
        $tokens = preg_split('~\s+~', $normalized) ?: [];

        $tokens = array_values(array_unique(array_filter($tokens, function (string $token): bool {
            return mb_strlen($token) >= 3 && ! in_array($token, self::STOPWORDS, true);
        })));

        return array_slice($tokens, 0, 30);
    }
}
