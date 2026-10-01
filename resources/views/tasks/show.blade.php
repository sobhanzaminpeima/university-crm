@extends('layouts.app')

@section('content')
<div class="page-shell">
<div class="card">
    <h2 style="margin-top:0;">Task #{{ $task->id }}</h2>
    <p><strong>Title:</strong> {{ $task->title }}</p>
    <p><strong>Description:</strong><br>{{ $task->description ?: '-' }}</p>
    <p><strong>Assigned To:</strong> {{ $assignee?->name ?: '#'.$task->assigned_to }}</p>
    <p><strong>Created By:</strong> {{ $creator?->name ?: '-' }}</p>
    <p><strong>Student:</strong> {{ $student?->full_name ?: '-' }}</p>
    <p><strong>Priority:</strong> {{ ucfirst($task->priority) }}</p>
    <p><strong>Status:</strong> {{ ucfirst(str_replace('_', ' ', $task->status)) }}</p>
    <p><strong>Deadline:</strong> {{ $task->deadline ?: '-' }}</p>
    <p><strong>Created:</strong> {{ $task->created_at }}</p>
    <div style="margin-top:8px;"><a class="tab" href="/tasks">Back to Tasks</a></div>
</div>
 </div>
@endsection
