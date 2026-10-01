@if ($paginator->hasPages())
    <nav class="crm-pagination" role="navigation" aria-label="Pagination Navigation">
        <div class="crm-pagination__controls">
            @if ($paginator->onFirstPage())
                <span class="crm-page-btn disabled" aria-disabled="true">Prev</span>
            @else
                <a class="crm-page-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">Prev</a>
            @endif

            @if ($paginator->hasMorePages())
                <a class="crm-page-btn" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span class="crm-page-btn disabled" aria-disabled="true">Next</span>
            @endif
        </div>
    </nav>
@endif
