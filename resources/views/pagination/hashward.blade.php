@if ($paginator->hasPages())
    <nav class="sb-pagination" role="navigation" aria-label="Pagination">
        @if ($paginator->total() > 0)
            <p class="sb-pagination-summary">
                Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}
            </p>
        @endif
        <div class="sb-pagination-inner">
            @if ($paginator->onFirstPage())
                <span class="sb-pagination-control is-disabled" aria-disabled="true" aria-label="Previous page">
                    <x-heroicon-o-chevron-left class="sb-pagination-icon" aria-hidden="true" />
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="sb-pagination-control" rel="prev" aria-label="Previous page">
                    <x-heroicon-o-chevron-left class="sb-pagination-icon" aria-hidden="true" />
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="sb-pagination-ellipsis" aria-hidden="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="sb-pagination-page is-active" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="sb-pagination-page">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="sb-pagination-control" rel="next" aria-label="Next page">
                    <x-heroicon-o-chevron-right class="sb-pagination-icon" aria-hidden="true" />
                </a>
            @else
                <span class="sb-pagination-control is-disabled" aria-disabled="true" aria-label="Next page">
                    <x-heroicon-o-chevron-right class="sb-pagination-icon" aria-hidden="true" />
                </span>
            @endif
        </div>
    </nav>
@endif
