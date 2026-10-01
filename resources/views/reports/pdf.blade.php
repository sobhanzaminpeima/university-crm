<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Advanced Report</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #0f172a; }
        h2 { margin: 0 0 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        thead th { background: #e2e8f0; font-weight: 700; }
        tr:nth-child(even) td { background: #f8fafc; }
    </style>
</head>
<body>
<h2>Advanced Report</h2>
<table>
    <thead><tr><th>Name</th><th>Email</th><th>Stage</th><th>Country</th><th>Agent</th><th>Sub-Agent</th></tr></thead>
    <tbody>
    @foreach($rows as $s)
        <tr>
            <td>{{ $s->full_name }}</td>
            <td>{{ $s->email }}</td>
            <td>{{ $s->stage }}</td>
            <td>{{ $s->target_country }}</td>
            <td>{{ $s->agent?->name ?: '-' }}</td>
            <td>{{ $s->subAgent?->name ?: '-' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>

