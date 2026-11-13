<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LiveEditController extends Controller
{
    public function index(Request $request)
    {
        $page = $this->pagePath($request->query('page', '/'));
        $rows = DB::table('page_text_edits')->where('page_path', $page)->get(['selector', 'content']);
        $edits = $rows->reject(fn ($edit) => str_ends_with($edit->selector, '::image') || str_ends_with($edit->selector, '::background'))
            ->mapWithKeys(fn ($edit) => [$edit->selector => $edit->content]);
        $images = $rows->filter(fn ($edit) => str_ends_with($edit->selector, '::image') || str_ends_with($edit->selector, '::background'))
            ->mapWithKeys(fn ($edit) => [$edit->selector => $edit->content]);

        $layouts = DB::table('page_layout_edits')->where('page_path', $page)->get(['container_selector', 'order_json'])
            ->mapWithKeys(fn ($layout) => [$layout->container_selector => json_decode($layout->order_json, true) ?: []]);
        return response()->json(['page' => $page, 'edits' => $edits, 'images' => $images, 'layouts' => $layouts]);
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'page' => ['required', 'string', 'max:255'],
            'selector' => ['required', 'string', 'max:4000'],
            'content' => ['required', 'string', 'max:10000'],
        ]);
        $page = $this->pagePath($data['page']);
        $selector = trim($data['selector']);
        abort_if($selector === '' || str_contains($selector, '<'), 422, 'Geçersiz metin konumu.');
        $key = hash('sha256', $page.'|'.$selector);
        $now = now();

        DB::table('page_text_edits')->updateOrInsert(
            ['edit_key' => $key],
            ['page_path' => $page, 'selector' => $selector, 'content' => $data['content'],
             'edited_by' => $request->user()->id, 'updated_at' => $now, 'created_at' => $now]
        );

        return response()->json(['ok' => true, 'message' => 'Değişiklik canlıya kaydedildi.']);
    }

    public function destroy(Request $request)
    {
        $data = $request->validate([
            'page' => ['required', 'string', 'max:255'],
            'selector' => ['required', 'string', 'max:4000'],
        ]);
        $page = $this->pagePath($data['page']);
        $key = hash('sha256', $page.'|'.trim($data['selector']));
        $row = DB::table('page_text_edits')->where('page_path', $page)->where('edit_key', $key)->first();
        if ($row && (str_ends_with($row->selector, '::image') || str_ends_with($row->selector, '::background'))) {
            $this->deleteUploadedImage($row->content);
        }
        DB::table('page_text_edits')->where('page_path', $page)->where('edit_key', $key)->delete();

        return response()->json(['ok' => true, 'message' => 'Metin varsayılan haline döndürüldü.']);
    }

    public function uploadImage(Request $request)
    {
        $data = $request->validate([
            'page' => ['required', 'string', 'max:255'],
            'selector' => ['required', 'string', 'max:4000'],
            'kind' => ['nullable', 'in:image,background'],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192'],
        ]);
        $page = $this->pagePath($data['page']);
        $kind = $data['kind'] ?? 'image';
        $selector = trim($data['selector']).'::'.$kind;
        abort_if(str_contains($selector, '<'), 422, 'Geçersiz görsel konumu.');
        $key = hash('sha256', $page.'|'.$selector);
        $now = now();
        $old = DB::table('page_text_edits')->where('edit_key', $key)->value('content');
        $path = \App\Support\ImageOptimizer::storeUploaded($request->file('image'), 'public', 'live-edits', 'image');
        $url = Storage::disk('public')->url($path);
        DB::table('page_text_edits')->updateOrInsert(
            ['edit_key' => $key],
            ['page_path' => $page, 'selector' => $selector, 'content' => $url,
             'edited_by' => $request->user()->id, 'updated_at' => $now, 'created_at' => $now]
        );
        if ($old) $this->deleteUploadedImage($old);
        return response()->json(['ok' => true, 'url' => $url, 'message' => 'Görsel güncellendi.']);
    }

    public function saveLayout(Request $request)
    {
        $data = $request->validate([
            'page' => ['required', 'string', 'max:255'],
            'container' => ['nullable', 'string', 'max:4000'],
            'order' => ['nullable', 'array', 'min:1', 'max:100'],
            'order.*' => ['required_with:order', 'string', 'max:1000'],
            'layouts' => ['nullable', 'array', 'min:1', 'max:2'],
            'layouts.*.container' => ['required', 'string', 'max:4000'],
            'layouts.*.order' => ['required', 'array', 'max:100'],
            'layouts.*.order.*' => ['required', 'string', 'max:1000'],
        ]);
        $page = $this->pagePath($data['page']);
        $layouts = $data['layouts'] ?? [['container' => $data['container'] ?? null, 'order' => $data['order'] ?? null]];
        abort_if(collect($layouts)->contains(fn ($item) => empty($item['container']) || !is_array($item['order'])), 422, 'Taşıma bilgisi eksik.');
        DB::transaction(function () use ($layouts, $page, $request): void {
            foreach ($layouts as $item) {
                $container = trim($item['container']);
                $key = hash('sha256', $page.'|'.$container);
                $now = now();
                DB::table('page_layout_edits')->updateOrInsert(
                    ['container_key' => $key],
                    ['page_path' => $page, 'container_selector' => $container, 'order_json' => json_encode(array_values($item['order'])),
                     'edited_by' => $request->user()->id, 'updated_at' => $now, 'created_at' => $now]
                );
            }
        });
        return response()->json(['ok' => true, 'message' => 'Öğe sırası kaydedildi.']);
    }

    private function pagePath(string $value): string
    {
        abort_if(strlen($value) > 255 || !str_starts_with($value, '/') || str_contains($value, '?') || str_contains($value, '#'), 422, 'Geçersiz sayfa yolu.');
        return $value === '/' ? '/' : '/'.trim($value, '/');
    }

    private function deleteUploadedImage(string $url): void
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!$path || !str_starts_with($path, '/storage/live-edits/')) return;
        $filename = basename($path);
        $target = public_path('storage/live-edits/'.$filename);
        if (is_file($target)) @unlink($target);
    }
}
