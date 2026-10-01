@extends('layouts.app')

@section('content')
<div class="page-shell">
<div class="panel-head">
    <h1 class="panel-title">Health & Backup</h1>
    <a class="secondary icon-action" href="/health/backup" title="Download tenant backup" aria-label="Download tenant backup">&#8681;</a>
</div>

<div class="grid-4">
    <div class="card"><h3>Database</h3><div class="metric">{{ $dbOk ? 'OK' : 'FAIL' }}</div></div>
    <div class="card"><h3>Storage</h3><div class="metric">{{ $storageOk ? 'OK' : 'FAIL' }}</div></div>
    <div class="card"><h3>Environment</h3><div class="metric">{{ $appEnv }}</div></div>
    <div class="card"><h3>Backup Tables</h3><div class="metric">{{ count($backupTables ?? []) }}</div></div>
</div>

<div class="card backup-panel">
    <div class="panel-head">
        <h3 style="margin:0;">Tenant Backup</h3>
        <span class="badge">{{ $appDebug ? 'Debug ON' : 'Debug OFF' }}</span>
    </div>
    <p class="footer-note">Backup includes tenant-owned CRM data such as students, applications, documents, universities, programs, finance, tasks, messages, and available catalog tables.</p>
    <div class="action-cluster" style="margin-top:10px;">
        <a class="secondary" href="/health/backup" style="text-decoration:none;padding:10px 14px;border-radius:10px;">Download JSON Backup</a>
    </div>
    @if(!empty($backupTables))
        <div class="backup-table-list">
            @foreach($backupTables as $table)
                <span class="badge">{{ $table }}</span>
            @endforeach
        </div>
    @endif
</div>

<div class="card backup-panel">
    <h3 style="margin-top:0;">Restore Backup</h3>
    <form method="POST" action="/health/restore" enctype="multipart/form-data">
        @csrf
        <div class="two-col">
            <input type="file" name="backup_file" accept=".json,.txt">
            <button type="submit">Restore Backup</button>
        </div>
        <textarea name="backup_json" rows="8" placeholder="Or paste backup JSON here" style="width:100%;margin-top:10px;"></textarea>
    </form>
</div>
</div>
@endsection

