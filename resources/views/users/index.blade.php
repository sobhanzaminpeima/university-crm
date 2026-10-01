@extends('layouts.app')

@php($authUser = request()->attributes->get('auth_user'))
@php($allowedRoles = $manageableRoleSlugs ?? [])
@php($scopeModules = [
    'students' => 'Students',
    'universities' => 'Universities',
    'applications' => 'Applications',
    'tasks' => 'Tasks',
    'messages' => 'Messages',
    'finance' => 'Finance',
    'student_requests' => 'Student Requests',
    'scholarships' => 'Scholarships',
])

@section('content')
<div class="card">
    <div class="toolbar">
        <form method="GET" action="/agents" class="toolbar grow">
            <select name="per_page" class="per-page-select" title="Items per page" aria-label="Items per page" onchange="this.form.submit()">
                @foreach([15, 50, 100] as $size)
                    <option value="{{ $size }}" {{ (int)($perPage ?? 50) === $size ? 'selected' : '' }}>{{ $size }}</option>
                @endforeach
            </select>
        </form>
        <button onclick="document.getElementById('addUser').showModal()">
            + Add {{ $authUser->role_slug === 'agent' ? 'Sub-Agent' : 'Admin/Agent/Sub-Agent' }}
        </button>
    </div>
    <table class="table-compact">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Active</th><th>Actions</th></tr></thead>
        <tbody>
        @foreach($users as $u)
            <tr>
                <td><a href="/agents/{{ $u->id }}">{{ $u->name }}</a></td>
                <td>{{ $u->email }}</td>
                <td>{{ $u->role_slug }}</td>
                <td>{{ $u->is_active ? 'Yes' : 'No' }}</td>
                <td style="display:flex;gap:6px;flex-wrap:wrap;">
                    <button class="secondary" type="button" onclick="document.getElementById('editUser{{ $u->id }}').showModal()">Edit</button>
                    @if($canManagePermissions && in_array($u->role_slug, $manageablePermissionRoleSlugs ?? [], true))
                        <button class="secondary" type="button" onclick="document.getElementById('userPerm{{ $u->id }}').showModal()">Permissions</button>
                    @endif
                    @if((int)$u->id !== (int)$authUser->id)
                        <form method="POST" action="/agents/{{ $u->id }}" onsubmit="return confirm('Delete user?')">@csrf @method('DELETE')<button class="secondary">Delete</button></form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="pagination-wrap">{{ $users->links() }}</div>
</div>

@if($canManagePermissions)
@if($authUser->role_slug === 'super_admin')
<div class="card" style="margin-top:12px;">
    <h3>Role Permission Manager</h3>
    <p class="footer-note">
        @if($authUser->role_slug === 'super_admin')
            Configure module access for Admin, Agent and Sub-Agent. Use <strong>Own</strong> for agent-only data and <strong>Global</strong> for tenant-wide access.
        @else
            Enable or disable Telegram Bot access for Agent and Sub-Agent roles.
        @endif
    </p>

    @foreach(['admin' => 'Admin', 'agent' => 'Agent', 'sub_agent' => 'Sub-Agent'] as $roleSlug => $roleLabel)
        @continue(!in_array($roleSlug, $manageablePermissionRoleSlugs ?? [], true))
        <form method="POST" action="/agents/roles/permissions" style="margin-top:12px;border:1px solid #dbeafe;border-radius:10px;padding:10px;">
            @csrf
            <input type="hidden" name="role_slug" value="{{ $roleSlug }}">
            <h4 style="margin:0 0 8px 0;">{{ $roleLabel }} Permissions</h4>

            @if($authUser->role_slug === 'super_admin')
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:8px;margin-bottom:10px;">
                @foreach($scopeModules as $moduleKey => $moduleLabel)
                    @php($viewKey = $moduleKey . '.view')
                    @php($viewAllKey = $moduleKey . '.view_all')
                    @php($rolePerms = $rolePermissionMap[$roleSlug] ?? [])
                    @php($hasView = in_array($viewKey, $rolePerms, true))
                    @php($isGlobal = in_array($viewAllKey, $rolePerms, true))
                    @if(collect($permissions)->contains(fn ($p) => $p->key === $viewKey))
                    <div class="tab" style="display:flex;flex-direction:column;gap:6px;">
                        <strong style="font-size:12px;">{{ $moduleLabel }}</strong>
                        <label style="font-size:12px;">
                            <input type="radio" name="scope_{{ $moduleKey }}" value="none" {{ !$hasView ? 'checked' : '' }}>
                            No access
                        </label>
                        <label style="font-size:12px;">
                            <input type="radio" name="scope_{{ $moduleKey }}" value="own" {{ $hasView && !$isGlobal ? 'checked' : '' }}>
                            Own
                        </label>
                        <label style="font-size:12px;">
                            <input type="radio" name="scope_{{ $moduleKey }}" value="global" {{ $hasView && $isGlobal ? 'checked' : '' }}>
                            Global
                        </label>
                    </div>
                    @endif
                @endforeach
            </div>
            @endif

            <div class="tabs" style="display:flex;flex-wrap:wrap;gap:8px;">
                @foreach($permissions as $permission)
                    @continue(!in_array($permission->key, $manageablePermissionKeys ?? [], true))
                    <label class="tab" style="cursor:pointer;">
                        <input
                            type="checkbox"
                            name="permissions[]"
                            value="{{ $permission->key }}"
                            data-perm-key="{{ $permission->key }}"
                            {{ in_array($permission->key, $rolePermissionMap[$roleSlug] ?? [], true) ? 'checked' : '' }}>
                        {{ $permission->key }}
                    </label>
                @endforeach
            </div>
            <button type="submit" style="margin-top:10px;">Save {{ $roleLabel }} Permissions</button>
        </form>
    @endforeach
</div>
@endif
@endif

<dialog id="addUser" class="card" style="max-width:560px;">
    <h3 style="margin-top:0;">Add User</h3>
    <form method="POST" action="/agents">
        @csrf
        <input type="hidden" name="_modal" value="addUser">
        <input name="name" placeholder="Name" required style="width:100%;margin-bottom:8px;">
        <input name="email" placeholder="Email" type="email" required style="width:100%;margin-bottom:8px;">
        <input name="password" placeholder="Password" type="password" required style="width:100%;margin-bottom:8px;">
        <select name="role_slug" style="width:100%;margin-bottom:10px;">
            @foreach($allowedRoles as $roleSlug)
                <option value="{{ $roleSlug }}">{{ strtoupper($roleSlug) }}</option>
            @endforeach
        </select>
        <div style="display:flex;gap:8px;">
            <button type="submit">Create user</button>
            <button type="button" class="secondary" onclick="document.getElementById('addUser').close()">Cancel</button>
        </div>
    </form>
</dialog>

@foreach($users as $u)
<dialog id="editUser{{ $u->id }}" class="card" style="max-width:560px;">
    <h3 style="margin-top:0;">Edit User</h3>
    <form method="POST" action="/agents/{{ $u->id }}">
        @csrf
        @method('PUT')
        <input name="name" value="{{ $u->name }}" required style="width:100%;margin-bottom:8px;">
        <input name="email" type="email" value="{{ $u->email }}" required style="width:100%;margin-bottom:8px;">
        <input name="password" type="password" placeholder="Leave blank to keep password" style="width:100%;margin-bottom:8px;">
        <select name="role_slug" style="width:100%;margin-bottom:8px;">
            @foreach($allowedRoles as $roleSlug)
                <option value="{{ $roleSlug }}" {{ $u->role_slug === $roleSlug ? 'selected' : '' }}>{{ strtoupper($roleSlug) }}</option>
            @endforeach
        </select>
        <select name="is_active" style="width:100%;margin-bottom:8px;">
            <option value="1" {{ (int) $u->is_active === 1 ? 'selected' : '' }}>Active</option>
            <option value="0" {{ (int) $u->is_active === 0 ? 'selected' : '' }}>Inactive</option>
        </select>
        <div style="display:flex;gap:8px;">
            <button type="submit">Save</button>
            <button type="button" class="secondary" onclick="document.getElementById('editUser{{ $u->id }}').close()">Cancel</button>
        </div>
    </form>
</dialog>

@if($canManagePermissions && in_array($u->role_slug, $manageablePermissionRoleSlugs ?? [], true))
<dialog id="userPerm{{ $u->id }}" class="card" style="max-width:760px;">
    <h3 style="margin-top:0;">User Permission Overrides: {{ $u->name }}</h3>
    <p class="footer-note">These overrides take priority over role permissions. Use Allow or Deny per permission.</p>
    <form method="POST" action="/agents/{{ $u->id }}/permissions">
        @csrf
        <div class="tabs" style="display:flex;flex-wrap:wrap;gap:8px;">
            @foreach($permissions as $permission)
                @continue(!in_array($permission->key, $manageablePermissionKeys ?? [], true))
                @php($state = $userPermissionMap[$u->id][$permission->key] ?? '')
                <div class="tab" style="display:flex;flex-direction:column;gap:6px;min-width:220px;">
                    <strong style="font-size:12px;">{{ $permission->key }}</strong>
                    <label style="font-size:12px;">
                        <input type="checkbox" name="allow[]" value="{{ $permission->key }}" {{ $state === 'allow' ? 'checked' : '' }}>
                        Allow
                    </label>
                    <label style="font-size:12px;">
                        <input type="checkbox" name="deny[]" value="{{ $permission->key }}" {{ $state === 'deny' ? 'checked' : '' }}>
                        Deny
                    </label>
                </div>
            @endforeach
        </div>
        <div style="display:flex;gap:8px;margin-top:10px;">
            <button type="submit">Save Overrides</button>
            <button type="button" class="secondary" onclick="document.getElementById('userPerm{{ $u->id }}').close()">Cancel</button>
        </div>
    </form>
</dialog>
@endif
@endforeach

<script>
document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('form[action="/agents/roles/permissions"]');
    forms.forEach((form) => {
        const radios = form.querySelectorAll('input[type="radio"][name^="scope_"]');
        radios.forEach((radio) => {
            radio.addEventListener('change', function () {
                const moduleKey = this.name.replace('scope_', '');
                const view = form.querySelector('input[data-perm-key="' + moduleKey + '.view"]');
                const viewAll = form.querySelector('input[data-perm-key="' + moduleKey + '.view_all"]');
                if (!view) return;
                if (this.value === 'none') {
                    view.checked = false;
                    if (viewAll) viewAll.checked = false;
                } else if (this.value === 'own') {
                    view.checked = true;
                    if (viewAll) viewAll.checked = false;
                } else if (this.value === 'global') {
                    view.checked = true;
                    if (viewAll) viewAll.checked = true;
                }
            });
        });
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = "{{ old('_modal') }}";
    if (modal === 'addUser' && document.getElementById('addUser')) {
        document.getElementById('addUser').showModal();
    }
});
</script>
@endsection
