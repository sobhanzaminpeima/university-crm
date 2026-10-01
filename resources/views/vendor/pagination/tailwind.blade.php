@if ($paginator->hasPages())
    <nav class="crm-pagination" role="navigation" aria-label="Pagination Navigation">
        <div class="crm-pagination__summary">
            Showing {{ $paginator->firstItem() }}-{{ $paginator->lastItem() }} of {{ $paginator->total() }}
        </div>

        <div class="crm-pagination__controls">
            @if ($paginator->onFirstPage())
                <span class="crm-page-btn disabled" aria-disabled="true">Prev</span>
            @else
                <a class="crm-page-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">Prev</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="crm-page-btn disabled" aria-disabled="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="crm-page-btn active" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="crm-page-btn" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="crm-page-btn" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span class="crm-page-btn disabled" aria-disabled="true">Next</span>
            @endif
        </div>
    </nav>
@endif
