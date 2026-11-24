<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Advanced Student Report</title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        body { font-family: DejaVu Sans, "Segoe UI", Arial, sans-serif; font-size: 11px; color: #1f2933; margin: 0; }
        .head { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #0a4f7a; padding-bottom: 6px; margin-bottom: 10px; }
        h1 { margin: 0; font-size: 18px; color: #0a4f7a; }
        .meta { color: #627d98; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { border: 1px solid #d9e2ec; padding: 5px 7px; text-align: left; vertical-align: top; }
        thead th { background: #0a4f7a; color: #fff; font-weight: 700; }
        tbody tr:nth-child(even) td { background: #f3f7fb; }
        .toolbar { margin: 0 0 12px; }
        .toolbar button { padding: 8px 14px; border-radius: 8px; border: 1px solid #0a4f7a; background: #0a4f7a; color: #fff; cursor: pointer; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
@if(!empty($printable))
    <div class="toolbar"><button type="button" onclick="window.print()">Print / Save as PDF</button></div>
@endif
<div class="head">
    <h1>Advanced Student Report</h1>
    <div class="meta">Generated {{ now()->format('Y-m-d H:i') }} · {{ number_format(count($rows)) }} rows</div>
</div>
<table>
    <thead><tr><th>Name</th><th>Email</th><th>Stage</th><th>Country</th><th>Agent</th><th>Sub-Agent</th><th>Created</th></tr></thead>
    <tbody>
    @forelse($rows as $s)
        <tr>
            <td>{{ $s->full_name }}</td>
            <td>{{ $s->email }}</td>
            <td>{{ ucfirst(str_replace('_', ' ', (string) $s->stage)) }}</td>
            <td>{{ $s->target_country ?: '-' }}</td>
            <td>{{ $s->agent?->name ?: '-' }}</td>
            <td>{{ $s->subAgent?->name ?: '-' }}</td>
            <td>{{ $s->created_at?->format('Y-m-d') }}</td>
        </tr>
    @empty
        <tr><td colspan="7">No students match the selected filters.</td></tr>
    @endforelse
    </tbody>
</table>
@if(!empty($printable))
    <script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });</script>
@endif
</body>
</html>
