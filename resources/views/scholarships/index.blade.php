@extends('layouts.app')

@section('content')
<div class="card">
    <div class="toolbar">
        <form method="GET" action="/scholarships" class="toolbar grow scholarship-filter">
            <input id="global-search" name="q" placeholder="Search title/description" value="{{ $q }}">
            <select name="country">
                <option value="">All countries</option>
                @foreach($countryOptions as $countryOption)
                    <option value="{{ $countryOption }}" {{ ($country ?? '') === $countryOption ? 'selected' : '' }}>{{ $countryOption }}</option>
                @endforeach
            </select>
            <select name="university_id">
                <option value="">All universities</option>
                @foreach($universities as $u)
                    <option value="{{ $u->id }}" {{ (int)($universityId ?? 0) === (int)$u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
            <input type="date" name="date_from" value="{{ $dateFrom ?? '' }}" title="From date" aria-label="From date">
            <input type="date" name="date_to" value="{{ $dateTo ?? '' }}" title="To date" aria-label="To date">
            <select name="sort">
                <option value="created_desc" {{ ($sort ?? 'created_desc') === 'created_desc' ? 'selected' : '' }}>Newest first</option>
                <option value="created_asc" {{ ($sort ?? '') === 'created_asc' ? 'selected' : '' }}>Oldest first</option>
                <option value="title_asc" {{ ($sort ?? '') === 'title_asc' ? 'selected' : '' }}>Name A-Z</option>
                <option value="title_desc" {{ ($sort ?? '') === 'title_desc' ? 'selected' : '' }}>Name Z-A</option>
                <option value="university_asc" {{ ($sort ?? '') === 'university_asc' ? 'selected' : '' }}>University A-Z</option>
            </select>
            <select name="per_page" class="per-page-select" title="Items per page" aria-label="Items per page" onchange="this.form.submit()">
                @foreach([15, 50, 100] as $size)
                    <option value="{{ $size }}" {{ (int)($perPage ?? 50) === $size ? 'selected' : '' }}>{{ $size }}</option>
                @endforeach
            </select>
            <button type="submit" class="icon-action" title="Search" aria-label="Search">&#128269;</button>
            <a class="secondary icon-action" href="/scholarships" title="Reset" aria-label="Reset">&#8634;</a>
        </form>
        <button class="icon-action" title="Add scholarship" aria-label="Add scholarship" onclick="document.getElementById('addScholarship').showModal()">+</button>
    </div>
    <table class="table-compact">
        <thead><tr><th>Title</th><th>University</th><th>Discount</th><th>Description</th><th>Action</th></tr></thead>
        <tbody>
        @foreach($scholarships as $s)
            <tr>
                <td>{{ $s->title }}</td>
                <td>{{ $uniMap[$s->university_id]->name ?? ('#'.$s->university_id) }}</td>
                <td>{{ number_format((float) $s->discount_percentage, 2) }}%</td>
                <td>{{ $s->description ?: '-' }}</td>
                <td style="display:flex;gap:6px;">
                    <button class="secondary icon-action" type="button" title="Edit" aria-label="Edit" onclick="document.getElementById('editScholarship{{ $s->id }}').showModal()">&#9998;</button>
                    <form method="POST" action="/scholarships/{{ $s->id }}" onsubmit="return confirm('Delete scholarship?')">
                        @csrf
                        @method('DELETE')
                        <button class="secondary icon-action danger-action" title="Delete" aria-label="Delete">&#128465;</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="pagination-wrap">{{ $scholarships->links() }}</div>
</div>

<dialog id="addScholarship" class="card" style="max-width:760px;">
    <h3 style="margin-top:0;">Add Scholarship</h3>
    <form method="POST" action="/scholarships">
        @csrf
        <div class="grid-4">
            <select name="university_id" required>
                @foreach($universities as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </select>
            <input name="title" placeholder="Scholarship title" required>
            <input name="discount_percentage" type="number" step="0.01" min="0" max="100" placeholder="Discount %" required>
        </div>
        <textarea name="description" rows="4" style="width:100%;margin-top:8px;" placeholder="Description"></textarea>
        <div style="margin-top:10px;display:flex;gap:8px;">
            <button type="submit">Save</button>
            <button type="button" class="secondary" onclick="document.getElementById('addScholarship').close()">Cancel</button>
        </div>
    </form>
</dialog>

@foreach($scholarships as $s)
<dialog id="editScholarship{{ $s->id }}" class="card" style="max-width:760px;">
    <h3 style="margin-top:0;">Edit Scholarship</h3>
    <form method="POST" action="/scholarships/{{ $s->id }}">
        @csrf
        @method('PUT')
        <div class="grid-4">
            <select name="university_id" required>
                @foreach($universities as $u)
                    <option value="{{ $u->id }}" {{ (int) $s->university_id === (int) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
            <input name="title" value="{{ $s->title }}" required>
            <input name="discount_percentage" type="number" step="0.01" min="0" max="100" value="{{ $s->discount_percentage }}" required>
        </div>
        <textarea name="description" rows="4" style="width:100%;margin-top:8px;">{{ $s->description }}</textarea>
        <div style="margin-top:10px;display:flex;gap:8px;">
            <button type="submit">Save Changes</button>
            <button type="button" class="secondary" onclick="document.getElementById('editScholarship{{ $s->id }}').close()">Cancel</button>
        </div>
    </form>
</dialog>
@endforeach
@endsection
