@extends('layouts.app')

@section('content')
<h1>Advanced Reports</h1>
<div class="card">
    <form method="GET" action="/reports/advanced" class="grid-4">
        <label class="field"><span>Created from</span><input type="date" name="from" value="{{ $from }}"></label>
        <label class="field"><span>Created to</span><input type="date" name="to" value="{{ $to }}"></label>
        <label class="field"><span>Agent</span><select name="agent_id">
            <option value="">All Agents</option>
            @foreach($agents as $a)
                <option value="{{ $a->id }}" @selected((string)$agentId === (string)$a->id)>{{ $a->name }}</option>
            @endforeach
        </select></label>
        <label class="field"><span>Target country</span><input name="country" value="{{ $country }}" placeholder="Any country"></label>
        <div class="field" style="justify-content:flex-end;flex-direction:row;gap:8px;align-items:flex-end;">
            <button type="submit">Apply filters</button>
            @if($from || $to || $agentId || $country)
                <a class="tab" href="/reports/advanced">Clear</a>
            @endif
        </div>
    </form>
    @include('partials.export-buttons', [
        'xlsx' => '/reports/advanced/export-excel?'.http_build_query(request()->query()),
        'csv' => '/reports/advanced/export-csv?'.http_build_query(request()->query()),
        'pdf' => '/reports/advanced/export-pdf?'.http_build_query(request()->query()),
        'pdfLabel' => class_exists(\Dompdf\Dompdf::class) ? 'PDF' : 'Print / PDF',
    ])
</div>
<div class="card" style="margin-top:12px;">
    <table>
        <thead><tr><th>Name</th><th>Email</th><th>Stage</th><th>Country</th><th>Agent</th><th>Sub-Agent</th></tr></thead>
        <tbody>
        @foreach($students as $s)
            <tr>
                <td>{{ $s->full_name }}</td>
                <td>{{ $s->email }}</td>
                <td>{{ ucfirst(str_replace('_', ' ', $s->stage)) }}</td>
                <td>{{ $s->target_country }}</td>
                <td>{{ $s->agent?->name ?: '-' }}</td>
                <td>{{ $s->subAgent?->name ?: '-' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div style="margin-top:8px;">{{ $students->links() }}</div>
</div>
@endsection
