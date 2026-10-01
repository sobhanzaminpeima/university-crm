@extends('layouts.portal')

@section('content')
<div class="card">
    <h2 style="margin-top:0;">My Documents</h2>
    <p class="footer-note">Documents are uploaded by your assigned agent. You can view status and download files here.</p>
    <table>
        <thead><tr><th>Type</th><th>File</th><th>Status</th></tr></thead>
        <tbody>
        @foreach($documents as $d)
            <tr>
                <td>{{ $d->label }}</td>
                <td>
                    @if(!$d->is_missing)
                        <a href="/portal/documents/{{ $d->id }}/view" target="_blank">Preview</a>
                        <span class="footer-note">|</span>
                        <a href="/portal/documents/{{ $d->id }}/view?download=1" target="_blank">Download</a>
                    @else
                        <span class="footer-note">Missing</span>
                    @endif
                </td>
                <td>
                    @if($d->is_missing)
                        <span class="badge rejected">Missing</span>
                    @elseif($d->status === 'verified')
                        <span class="badge enrolled">Verified</span>
                    @else
                        <span class="badge applied">Uploaded</span>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="card" style="margin-top:12px;">
        <h3>Acceptance Letters</h3>
        <table>
            <thead><tr><th>Title</th><th>Date</th><th>File</th></tr></thead>
            <tbody>
            @forelse($offerLetters as $letter)
                @php($meta = json_decode((string) $letter->ocr_json, true) ?: [])
                <tr>
                    <td>{{ $meta['title'] ?? 'Acceptance Letter' }}</td>
                    <td>{{ $meta['letter_date'] ?? '-' }}</td>
                    <td>
                        <a href="/portal/documents/{{ $letter->id }}/view" target="_blank">Preview</a>
                        <span class="footer-note">|</span>
                        <a href="/portal/documents/{{ $letter->id }}/view?download=1" target="_blank">Download</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3">No acceptance letter has been shared yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
