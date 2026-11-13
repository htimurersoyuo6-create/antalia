<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class LocalModelTestController extends Controller
{
    public function index(): View
    {
        return view('admin.local-model-test');
    }

    public function chat(Request $request)
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
        ]);

        $page = Page::slugda('sss');
        if (!$page || !function_exists('sayfa_sss_cikar')) {
            Log::error('Local FAQ test could not load the published FAQ source.');
            return response()->json(['message' => 'SSS kaynağı şu anda yüklenemiyor. Yönetici günlüklerini kontrol edin.'], 503);
        }

        $faqs = sayfa_sss_cikar((string) $page->content);
        $question = $this->normalize($validated['question']);
        $best = null;
        $bestScore = 0.0;

        foreach (config('local_faq_test', []) as $entry) {
            $source = null;
            foreach ($faqs as $faq) {
                if ($this->normalize((string) ($faq['soru'] ?? '')) === $this->normalize((string) $entry['question'])) {
                    $source = $faq;
                    break;
                }
            }
            if ($source === null) continue;

            $score = 0.0;
            foreach ($entry['keywords'] as $keyword) {
                $needle = $this->normalize((string) $keyword);
                if ($needle !== '' && str_contains($question, $needle)) {
                    $score += str_contains($needle, ' ') ? 2.0 : 1.5;
                }
            }


            if ($score > $bestScore) {
                $bestScore = $score;
                $best = ['faq' => $source, 'package' => $entry];
            }
        }

        if ($best === null || $bestScore < 1.5) {
            return response()->json([
                'answer' => 'Bu soru yayınlanmış SSS içeriğinde net olarak yanıtlanmıyor. Doğru bilgi için seçtiğiniz tesisle doğrudan iletişime geçin.',
                'matched_question' => null,
            ]);
        }

        return response()->json([
            'answer' => (string) ($best['package']['answer'] ?? $best['faq']['cevap']),
            'matched_question' => (string) $best['faq']['soru'],
        ]);
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = strtr($text, [
            'ı' => 'i', 'ş' => 's', 'ğ' => 'g', 'ü' => 'u', 'ö' => 'o', 'ç' => 'c',
            'â' => 'a', 'î' => 'i', 'û' => 'u',
        ]);
        $text = preg_replace('~[^a-z0-9\\s]+~', ' ', $text) ?? $text;

        return trim(preg_replace('~\\s+~', ' ', $text) ?? $text);
    }
}
