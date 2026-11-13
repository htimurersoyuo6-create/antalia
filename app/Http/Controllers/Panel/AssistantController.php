<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\AssistantFaq;
use App\Models\AssistantInteraction;
use App\Models\Hotel;
use App\Services\SmartAssistantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssistantController extends Controller
{
    public function index(Request $request): View
    {
        $hotels = $this->hotelsFor($request->user());
        $hotel = $hotels->firstWhere('id', (int) ($request->query('hotel') ?: $hotels->first()?->id));
        abort_if(! $hotel, 404);

        $faqs = AssistantFaq::query()
            ->where(function ($query) use ($hotel): void {
                $query->whereNull('hotel_id')->orWhere('hotel_id', $hotel->id);
            })
            ->orderByDesc('hotel_id')
            ->orderBy('sort_order')
            ->get();

        $interactions = AssistantInteraction::query()
            ->where('hotel_id', $hotel->id)
            ->latest()
            ->limit(50)
            ->get();

        $since = now()->subDays(30);
        $total = AssistantInteraction::query()->where('hotel_id', $hotel->id)->where('created_at', '>=', $since)->count();
        $handoffs = AssistantInteraction::query()->where('hotel_id', $hotel->id)->where('created_at', '>=', $since)->where('handoff', true)->count();

        return view('panel.assistant', [
            'hotels' => $hotels,
            'hotel' => $hotel,
            'faqs' => $faqs,
            'interactions' => $interactions,
            'stats' => [
                'total_30d' => $total,
                'handoff_30d' => $handoffs,
                'handoff_rate' => $total > 0 ? round(($handoffs / $total) * 100, 1) : 0.0,
            ],
            'result' => session('assistant_result'),
        ]);
    }

    public function storeFaq(Request $request): RedirectResponse
    {
        $hotels = $this->hotelsFor($request->user());

        $data = $request->validate([
            'hotel_id' => ['nullable', 'integer'],
            'scope' => ['required', 'in:global,hotel'],
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:4000'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'category' => ['nullable', 'string', 'max:60'],
        ]);

        $hotelId = null;

        if ($data['scope'] === 'hotel') {
            $hotel = $hotels->firstWhere('id', (int) ($data['hotel_id'] ?? 0));
            abort_if(! $hotel, 403);
            $hotelId = $hotel->id;
        } elseif (! $request->user()->isAdmin()) {
            abort(403, 'Genel havuz yalnızca platform yöneticisi tarafından düzenlenebilir.');
        }

        $keywords = collect(explode(',', (string) ($data['keywords'] ?? '')))
            ->map(fn (string $keyword) => trim($keyword))
            ->filter()
            ->values()
            ->all();

        AssistantFaq::create([
            'hotel_id' => $hotelId,
            'question' => $data['question'],
            'answer' => $data['answer'],
            'keywords' => $keywords,
            'category' => $data['category'] ?? null,
            'is_active' => true,
            'sort_order' => (int) AssistantFaq::query()->where('hotel_id', $hotelId)->max('sort_order') + 1,
        ]);

        return back()->with('ok', 'Soru-cevap kaydı eklendi.');
    }

    public function toggleFaq(Request $request, AssistantFaq $faq): RedirectResponse
    {
        $this->authorizeFaq($request, $faq);

        $faq->forceFill(['is_active' => ! $faq->is_active])->save();

        return back()->with('ok', 'Kayıt durumu güncellendi.');
    }

    public function destroyFaq(Request $request, AssistantFaq $faq): RedirectResponse
    {
        $this->authorizeFaq($request, $faq);

        $faq->delete();

        return back()->with('ok', 'Soru-cevap kaydı silindi.');
    }

    public function test(Request $request, SmartAssistantService $assistant): RedirectResponse
    {
        $data = $request->validate([
            'hotel_id' => ['required', 'integer'],
            'question' => ['required', 'string', 'max:2000'],
        ]);

        $hotel = $this->hotelsFor($request->user())->firstWhere('id', (int) $data['hotel_id']);
        abort_if(! $hotel, 403);

        $result = $assistant->answer($hotel, $data['question'], $request->user(), 'panel-test');

        return back()->with('assistant_result', $result);
    }

    public function seedDefaults(Request $request, SmartAssistantService $assistant): RedirectResponse
    {
        $hotel = $this->hotelsFor($request->user())->firstWhere('id', (int) $request->input('hotel_id'));
        abort_if(! $hotel, 403);

        $created = $assistant->seedDefaults($hotel->id);

        return back()->with('ok', $created.' varsayılan soru-cevap kaydı eklendi.');
    }

    private function authorizeFaq(Request $request, AssistantFaq $faq): void
    {
        $user = $request->user();

        if ($faq->hotel_id === null) {
            abort_unless($user->isAdmin(), 403);

            return;
        }

        abort_unless($this->hotelsFor($user)->contains('id', $faq->hotel_id), 403);
    }

    private function hotelsFor($user)
    {
        return $user->isAdmin() ? Hotel::query()->orderBy('name')->get() : $user->hotels()->orderBy('name')->get();
    }
}
