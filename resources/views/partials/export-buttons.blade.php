{{--
    Consistent export actions. Pass any of: $xlsx, $csv, $pdf (URLs). Optional $note.
    The current filters should already be encoded in each URL.
--}}
<div class="export-actions" role="group" aria-label="Export">
    <span class="export-label">Export</span>
    @isset($xlsx)
        <a class="btn-export btn-export-primary" href="{{ $xlsx }}" data-no-loading="true" title="Download as Excel workbook (.xlsx)">
            <span aria-hidden="true">⬇</span> Excel (.xlsx)
        </a>
    @endisset
    @isset($csv)
        <a class="btn-export" href="{{ $csv }}" title="Download as CSV (UTF-8)">CSV</a>
    @endisset
    @isset($pdf)
        <a class="btn-export" href="{{ $pdf }}" title="PDF" target="_blank" rel="noopener">{{ $pdfLabel ?? 'PDF' }}</a>
    @endisset
    <span class="footer-note export-note">{{ $note ?? 'Exports include all rows matching the current filters.' }}</span>
</div>
