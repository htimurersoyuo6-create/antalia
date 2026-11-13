@if(auth()->check() && auth()->user()->role === 'admin')
<link rel="stylesheet" href="{{ asset('css/admin-live-editor.css') }}?v={{ @filemtime(public_path('css/admin-live-editor.css')) }}">
<div data-live-edit-ui data-active="false" data-no-live-edit
     data-load-url="{{ route('admin.live-edits.index') }}"
     data-save-url="{{ route('admin.live-edits.save') }}"
     data-delete-url="{{ route('admin.live-edits.destroy') }}"
     data-upload-url="{{ route('admin.live-edits.image') }}"
     data-layout-url="{{ route('admin.live-edits.layout') }}"
     data-csrf="{{ csrf_token() }}">
  <button type="button" data-live-toggle aria-pressed="false">✎ Sayfayı düzenle</button>
  <button type="button" data-live-reset disabled title="Seçili metni varsayılan haline döndür">↺ Varsayılan</button>
  <button type="button" data-live-move aria-pressed="false" disabled>↕ Gelişmiş taşıma</button>
  <button type="button" data-live-image disabled>▧ Görsel değiştir</button>
  <input type="file" data-live-file accept="image/jpeg,image/png,image/webp,image/gif" hidden>
  <span data-live-status role="status" aria-live="polite">Düzenleyici hazırlanıyor…</span>
  <button type="button" data-live-collapse aria-expanded="true" aria-label="Canlı düzenleme çubuğunu küçült" title="Çubuğu küçült">−</button>
</div>
<script src="{{ asset('js/admin-live-editor.js') }}?v={{ @filemtime(public_path('js/admin-live-editor.js')) }}" defer></script>
@endif
