@extends('layouts.app')

@section('content')
<div class="two-col">
    <div class="card">
        <h3>Study Fields</h3>
        <form method="POST" action="/settings/study-fields" class="toolbar">
            @csrf
            <input name="name" placeholder="e.g. Computer Science" required>
            <button type="submit">Add Field</button>
        </form>
        <form method="POST" action="/settings/study-fields/sync" style="margin:8px 0;">
            @csrf
            <button class="secondary" type="submit">Sync From Programs</button>
        </form>
        <form method="POST" action="/settings/study-fields/bulk-delete" onsubmit="return confirm('Delete selected study fields?')" style="margin:8px 0;">
            @csrf
            <div style="display:flex;gap:8px;align-items:center;">
                <label style="display:flex;align-items:center;gap:6px;">
                    <input type="checkbox" id="studyFieldsSelectAll">
                    <span>Select all</span>
                </label>
                <button class="secondary" type="submit">Delete Selected</button>
            </div>
            <table class="table-compact" style="margin-top:8px;">
                <thead><tr><th>Select</th><th>Name</th><th>Source</th><th>Action</th></tr></thead>
                <tbody>
                @forelse($fields as $field)
                    <tr>
                        <td><input type="checkbox" class="study-field-item" name="ids[]" value="{{ $field->id }}"></td>
                        <td>
                            <form method="POST" action="/settings/study-fields/{{ $field->id }}" style="display:flex;gap:8px;">
                                @csrf @method('PUT')
                                <input name="name" value="{{ $field->name }}" required>
                                <button type="submit" class="secondary">Save</button>
                            </form>
                        </td>
                        <td><span class="badge">{{ $fieldSources[$field->name] ?? 'Manual / Unknown' }}</span></td>
                        <td>
                            <form method="POST" action="/settings/study-fields/{{ $field->id }}" onsubmit="return confirm('Delete field?')">
                                @csrf @method('DELETE')
                                <button class="secondary">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4">No fields yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </form>
        <form method="POST" action="/settings/study-fields" onsubmit="return confirm('Delete all study fields?')" style="margin:8px 0;">
            @csrf @method('DELETE')
            <button class="secondary" type="submit">Delete All Fields</button>
        </form>
    </div>

    <div class="card">
        <h3>Intake Terms</h3>
        <form method="POST" action="/settings/intake-terms" class="toolbar">
            @csrf
            <input name="name" placeholder="e.g. 2026-27 Spring" required>
            <button type="submit">Add Intake</button>
        </form>
        <table class="table-compact">
            <thead><tr><th>Name</th><th>Action</th></tr></thead>
            <tbody>
            @forelse($intakes as $intake)
                <tr>
                    <td>{{ $intake->name }}</td>
                    <td>
                        <form method="POST" action="/settings/intake-terms/{{ $intake->id }}" onsubmit="return confirm('Delete intake?')">
                            @csrf @method('DELETE')
                            <button class="secondary">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="2">No intake terms yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAll = document.getElementById('studyFieldsSelectAll');
    const items = Array.from(document.querySelectorAll('.study-field-item'));
    if (!selectAll || !items.length) return;
    selectAll.addEventListener('change', function () {
        items.forEach((el) => { el.checked = selectAll.checked; });
    });
    items.forEach((el) => {
        el.addEventListener('change', function () {
            const selected = items.filter((x) => x.checked).length;
            selectAll.checked = selected === items.length;
            selectAll.indeterminate = selected > 0 && selected < items.length;
        });
    });
});
</script>
@endsection
