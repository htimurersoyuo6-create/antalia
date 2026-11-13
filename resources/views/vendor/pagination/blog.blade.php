@if ($paginator->hasPages())
<nav class="blog-pagination" role="navigation" aria-label="Blog sayfaları">
    <p class="blog-pagination-summary">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} / {{ $paginator->total() }} yazı</p>
    <div class="blog-pagination-controls">
        @if ($paginator->onFirstPage())
            <span class="blog-page-step disabled" aria-disabled="true"><span class="blog-page-chevron" aria-hidden="true">‹</span> Önceki</span>
        @else
            <a class="blog-page-step" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Önceki sayfa"><span class="blog-page-chevron" aria-hidden="true">‹</span> Önceki</a>
        @endif
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="blog-page-ellipsis" aria-hidden="true">…</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="blog-page-number current" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="blog-page-number" href="{{ $url }}" aria-label="Sayfa {{ $page }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach
        @if ($paginator->hasMorePages())
            <a class="blog-page-step" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Sonraki sayfa">Sonraki <span class="blog-page-chevron" aria-hidden="true">›</span></a>
        @else
            <span class="blog-page-step disabled" aria-disabled="true">Sonraki <span class="blog-page-chevron" aria-hidden="true">›</span></span>
        @endif
    </div>
</nav>
@endif
