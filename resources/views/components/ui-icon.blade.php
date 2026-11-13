@props(['name', 'size' => 20, 'class' => ''])
<svg class="ui-icon {{ $class }}" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
@switch($name)
  @case('home')
    <path d="m3 10 9-7 9 7"/><path d="M5 9v11h14V9M9 20v-6h6v6"/>
    @break
  @case('sparkles')
    <path d="m12 3 1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3Z"/><path d="m19 15 .8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8L19 15Z"/>
    @break
  @case('clock')
    <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
    @break
  @case('help')
    <circle cx="12" cy="12" r="9"/><path d="M9.6 9a2.5 2.5 0 0 1 4.8 1c0 1.7-2.4 2.2-2.4 4"/><path d="M12 17.5h.01"/>
    @break
  @case('pin')
    <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>
    @break
  @case('book')
    <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5v-16Z"/><path d="M4 6v15M8 7h8M8 11h7"/>
    @break
  @case('building')
    <path d="M4 21V5l8-3 8 3v16M2 21h20"/><path d="M9 8h1M14 8h1M9 12h1M14 12h1M10 21v-4h4v4"/>
    @break
  @case('star')
    <path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9L12 3Z"/>
    @break
  @case('map')
    <path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3V6Z"/><path d="M9 3v15M15 6v15"/>
    @break
  @case('camera')
    <path d="M4 7h3l1.5-2h7L17 7h3a1 1 0 0 1 1 1v11H3V8a1 1 0 0 1 1-1Z"/><circle cx="12" cy="13" r="3.5"/>
    @break
  @case('stethoscope')
    <path d="M6 4v5a4 4 0 0 0 8 0V4M4 4h4M12 4h4M14 11v2a4 4 0 0 0 8 0v-1"/><circle cx="18" cy="10" r="2"/><circle cx="18" cy="17" r="2"/>
    @break
  @case('ball')
    <circle cx="12" cy="12" r="9"/><path d="m8.5 4 2 4.5L7 12l-4-.5M15.5 4l-2 4.5L17 12l4-.5M7 12l2 5 3 4M17 12l-2 5-3 4"/>
    @break
  @case('van')
    <path d="M3 6h11v11H3zM14 10h4l3 3v4h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/><path d="M17 10v3h4"/>
    @break
  @case('video')
    <rect x="3" y="6" width="13" height="12" rx="2"/><path d="m16 10 5-3v10l-5-3"/><path d="M6 3v1M13 3v1"/>
    @break
  @case('dog')
    <path d="M7 8 4 5 3 11a4 4 0 0 0 2 3.5V18a3 3 0 0 0 3 3h8a3 3 0 0 0 3-3v-3.5a4 4 0 0 0 2-3.5l-1-6-3 3a8 8 0 0 0-10 0Z"/><circle cx="9" cy="13" r=".7"/><circle cx="15" cy="13" r=".7"/><path d="M10 17c1.2 1 2.8 1 4 0"/>
    @break
  @case('cat')
    <path d="M5 9 4 4l5 3a8 8 0 0 1 6 0l5-3-1 5a8 8 0 1 1-14 0Z"/><circle cx="9" cy="12" r=".7"/><circle cx="15" cy="12" r=".7"/><path d="m12 14 1 1h-2l1-1ZM12 15v2M8 15l-4-1M8 17l-4 1M16 15l4-1M16 17l4 1"/>
    @break
  @case('currency')
    <circle cx="12" cy="12" r="9"/><text x="12" y="16.5" text-anchor="middle" font-size="13" font-family="system-ui,sans-serif" font-weight="700" fill="currentColor" stroke="none">₺</text>
    @break
  @case('paw')
    <ellipse cx="12" cy="15.7" rx="5.1" ry="4.1"/><ellipse cx="5.7" cy="10.2" rx="1.8" ry="2.3"/><ellipse cx="10" cy="6.7" rx="1.8" ry="2.3"/><ellipse cx="14.3" cy="6.7" rx="1.8" ry="2.3"/><ellipse cx="18.5" cy="10.2" rx="1.8" ry="2.3"/>
    @break
  @case('check')
    <path d="m5 12 4.5 4.5L19 7"/>
    @break
  @case('phone')
    <path d="M5 4h4l2 5-2.5 1.7a15 15 0 0 0 4.8 4.8L15 13l5 2v4a2 2 0 0 1-2 2C10 21 3 14 3 6a2 2 0 0 1 2-2Z"/>
    @break
  @case('message')
    <path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5 8.9 8.9 0 0 1-4-.9L3 21l1.9-5.1a8.9 8.9 0 0 1-.9-4A8.5 8.5 0 1 1 21 11.5Z"/><path d="M8 11h8M8 15h5"/>
    @break
  @case('whatsapp')
    <path d="M20.5 11.5a8.5 8.5 0 0 1-12.6 7.4L3 20l1.2-4.6a8.5 8.5 0 1 1 16.3-3.9Z"/><path d="M8 8.2c.2-.5.5-.7.9-.7h.6l1 2.4-1 1c.5 1.2 1.4 2.1 2.6 2.6l1-1 2.4 1v.6c0 .4-.2.7-.7.9-1.1.4-2.8-.1-4.5-1.8C8.3 11.5 7.7 9.3 8 8.2Z"/>
    @break
  @case('heart')
    <path d="M20.8 8.8c0 5.2-8.8 10.2-8.8 10.2S3.2 14 3.2 8.8A4.8 4.8 0 0 1 12 6.1a4.8 4.8 0 0 1 8.8 2.7Z"/>
    @break
  @default
    <circle cx="12" cy="12" r="8"/>
@endswitch
</svg>
