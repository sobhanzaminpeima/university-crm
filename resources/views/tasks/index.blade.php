@extends('layouts.app')

@section('content')
<div class="page-shell">
<div class="card">
    <div class="toolbar">
        <form method="GET" action="/tasks" style="display:flex;gap:8px;flex-wrap:wrap;">
            <select name="status">
                <option value="">All Statuses</option>
                @foreach(['todo' => 'To Do', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'blocked' => 'Blocked'] as $key => $label)
                    <option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <select name="priority">
                <option value="">All Priorities</option>
                @foreach(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'] as $key => $label)
                    <option value="{{ $key }}" {{ $priority === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            @if($canViewAllTasks)
                <select name="assigned_to">
                    <option value="">All Assignees</option>
                    @foreach($agents as $agent)
                        <option value="{{ $agent->id }}" {{ $assignedTo === (string) $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                    @endforeach
                </select>
            @endif
            <select name="per_page" class="per-page-select" title="Items per page" aria-label="Items per page" onchange="this.form.submit()">
                @foreach([15, 50, 100] as $size)
                    <option value="{{ $size }}" {{ (int)($perPage ?? 50) === $size ? 'selected' : '' }}>{{ $size }}</option>
                @endforeach
            </select>
            <button type="submit">Filter</button>
        </form>
        <button onclick="document.getElementById('addTask').showModal()">+ New Task</button>
    </div>

    <table class="table-compact">
        <thead>
        <tr><th>Title</th><th>Student</th><th>Assigned To</th><th>Created By</th><th>Priority</th><th>Status</th><th>Deadline</th><th>Action</th></tr>
        </thead>
        <tbody>
        @forelse($tasks as $task)
            @php
                $isCreator = (int)($task->created_by_user_id ?? 0) === (int)$currentUserId;
                $isAssignee = (int)$task->assigned_to === (int)$currentUserId;
                $canManageTask = $canViewAllTasks || $isCreator || (!isset($task->created_by_user_id) && $isAssignee);
                $canCompleteTask = $canViewAllTasks || $isCreator || $isAssignee;
            @endphp
            <tr>
                <td>{{ $task->title }}</td>
                <td>{{ optional($students->firstWhere('id', $task->student_id))->full_name ?: '-' }}</td>
                <td>{{ $userMap[$task->assigned_to]->name ?? ('#'.$task->assigned_to) }}</td>
                <td>{{ !empty($task->created_by_user_id) ? ($userMap[$task->created_by_user_id]->name ?? ('#'.$task->created_by_user_id)) : '-' }}</td>
                <td>{{ ucfirst($task->priority) }}</td>
                <td>{{ ucfirst(str_replace('_', ' ', $task->status)) }}</td>
                <td>{{ $task->deadline ? \Illuminate\Support\Carbon::parse($task->deadline)->format('Y-m-d H:i') : '-' }}</td>
                <td style="display:flex;gap:6px;flex-wrap:wrap;">
                    <a class="tab" href="/tasks/{{ $task->id }}">View</a>
                    @if($task->status !== 'completed' && $canCompleteTask)
                        <form method="POST" action="/tasks/{{ $task->id }}/complete">
                            @csrf
                            <button type="submit">Complete</button>
                        </form>
                    @endif
                    @if($canManageTask)
                        <button type="button" class="secondary" onclick="document.getElementById('editTask{{ $task->id }}').showModal()">Edit</button>
                        <form method="POST" action="/tasks/{{ $task->id }}" onsubmit="return confirm('Delete task?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="secondary">Delete</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="9">No tasks found.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="pagination-wrap">{{ $tasks->links() }}</div>
</div>

<dialog id="addTask" class="card" style="max-width:900px;">
    <h3 style="margin-top:0;">Create Task</h3>
    <form method="POST" action="/tasks">
        @csrf
        @include('tasks.partials.form', ['task' => null, 'students' => $students, 'agents' => $agents])
        <div style="margin-top:10px;display:flex;gap:8px;">
            <button type="submit">Save</button>
            <button type="button" class="secondary" onclick="document.getElementById('addTask').close()">Cancel</button>
        </div>
    </form>
</dialog>

@foreach($tasks as $task)
    @php
        $canManageTask = $canViewAllTasks || (int)($task->created_by_user_id ?? 0) === (int)$currentUserId || (!isset($task->created_by_user_id) && (int)$task->assigned_to === (int)$currentUserId);
    @endphp
    @continue(!$canManageTask)
    <dialog id="editTask{{ $task->id }}" class="card" style="max-width:900px;">
        <h3 style="margin-top:0;">Edit Task</h3>
        <form method="POST" action="/tasks/{{ $task->id }}">
            @csrf
            @method('PUT')
            @include('tasks.partials.form', ['task' => $task, 'students' => $students, 'agents' => $agents])
            <div style="margin-top:10px;display:flex;gap:8px;">
                <button type="submit">Update</button>
                <button type="button" class="secondary" onclick="document.getElementById('editTask{{ $task->id }}').close()">Cancel</button>
            </div>
        </form>
    </dialog>
@endforeach
 </div>
@endsection
