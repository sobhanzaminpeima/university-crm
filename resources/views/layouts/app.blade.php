<!doctype html>
<html lang="en"
      data-theme="{{ request()->cookie('theme', 'figma-light') }}"
      data-font-scale="{{ request()->cookie('font_scale', request()->attributes->get('auth_user')->font_scale ?? 'base') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Vertue CRM</title>
    <script>
        (function () {
            const html = document.documentElement;
            const readCookie = function (key) {
                return (document.cookie.match(new RegExp('(?:^|;\\s*)' + key + '=([^;]+)')) || [])[1];
            };
            const normalizeTheme = function (value) {
                if (value === 'light') return 'figma-light';
                if (value === 'dark') return 'figma-dark';
                if (['figma-light', 'figma-dark', 'classic-light', 'classic-dark'].includes(value || '')) return value;
                return 'figma-light';
            };
            const normalizeScale = function (value) {
                return ['sm', 'base', 'lg'].includes(value || '') ? value : 'base';
            };

            const theme = normalizeTheme(readCookie('theme') || localStorage.getItem('theme') || html.getAttribute('data-theme'));
            const scale = normalizeScale(readCookie('font_scale') || localStorage.getItem('font_scale') || html.getAttribute('data-font-scale'));
            html.setAttribute('data-theme', theme);
            html.setAttribute('data-font-scale', scale);
            localStorage.setItem('theme', theme);
            localStorage.setItem('font_scale', scale);
            document.cookie = 'theme=' + theme + ';path=/;max-age=31536000;samesite=lax';
            document.cookie = 'font_scale=' + scale + ';path=/;max-age=31536000;samesite=lax';
        })();
    </script>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
@php
    $authUser = request()->attributes->get('auth_user');
    $headerNotifications = collect();
    $headerUnread = 0;
    if ($authUser) {
        $headerNotifications = \App\Models\Notification::query()
            ->forTenant($authUser->tenant_id, $authUser->role_slug)
            ->where('user_id', $authUser->id)
            ->latest('id')
            ->limit(6)
            ->get();
        $headerUnread = $headerNotifications->whereNull('read_at')->count();
    }
    $can = function (string $permission) use ($authUser): bool {
        if (!$authUser) {
            return false;
        }
        if ($authUser->role_slug === 'super_admin') {
            return true;
        }
        return $authUser->hasPermission($permission);
    };
@endphp
<div class="layout">
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeSidebar()"></div>
    <aside class="sidebar">
        <h1>Vertue CRM</h1>
        <a class="nav-link {{ request()->is('dashboard') ? 'active' : '' }}" href="/dashboard">Dashboard</a>
        <details class="menu-group" open>
            <summary>Admissions</summary>
            @if($can('students.view'))
                <a class="nav-link {{ request()->is('students*') ? 'active' : '' }}" href="/students">Students</a>
            @endif
            @if($can('applications.view'))
                <a class="nav-link {{ request()->is('applications*') ? 'active' : '' }}" href="/applications">Applications</a>
            @endif
            @if($can('students.view'))
                <a class="nav-link {{ request()->is('pipeline*') ? 'active' : '' }}" href="/pipeline">Pipeline Board</a>
            @endif
            @if($can('applications.view'))
                <a class="nav-link {{ request()->is('calendar*') ? 'active' : '' }}" href="/calendar">Intake Calendar</a>
            @endif
            @if($can('student_requests.view'))
                <a class="nav-link {{ request()->is('student-requests*') ? 'active' : '' }}" href="/student-requests">Student Requests</a>
            @endif
            @if($can('universities.view'))
                <a class="nav-link {{ request()->is('universities*') ? 'active' : '' }}" href="/universities">Universities</a>
            @endif
            @if($can('scholarships.view'))
                <a class="nav-link {{ request()->is('scholarships*') ? 'active' : '' }}" href="/scholarships">Scholarships</a>
            @endif
        </details>
        <details class="menu-group" open>
            <summary>Operations</summary>
            @if($can('tasks.view'))
                <a class="nav-link {{ request()->is('tasks*') ? 'active' : '' }}" href="/tasks">Tasks</a>
            @endif
            @if($can('messages.view'))
                <a class="nav-link {{ request()->is('messages*') ? 'active' : '' }}" href="/messages">Messages</a>
            @endif
            @if($can('finance.view'))
                <a class="nav-link {{ request()->is('finance*') ? 'active' : '' }}" href="/finance">Finance</a>
            @endif
            @if($can('users.view'))
                <a class="nav-link {{ request()->is('agents*') ? 'active' : '' }}" href="/agents">Agents & Roles</a>
            @endif
            @if($can('agent_performance.view'))
                <a class="nav-link {{ request()->is('agents/performance*') ? 'active' : '' }}" href="/agents/performance">Agent Performance</a>
            @endif
        </details>
        @if($can('reports.view'))
            <a class="nav-link {{ request()->is('reports/advanced*') ? 'active' : '' }}" href="/reports/advanced">Advanced Reports</a>
        @endif
        @if($can('audit.view'))
            <a class="nav-link {{ request()->is('audit-logs*') ? 'active' : '' }}" href="/audit-logs">Audit Logs</a>
        @endif
        @if($can('settings.update'))
            <a class="nav-link {{ request()->is('templates*') ? 'active' : '' }}" href="/templates">Templates</a>
            <a class="nav-link {{ request()->is('automation-rules*') ? 'active' : '' }}" href="/automation-rules">Automation Rules</a>
            <a class="nav-link {{ request()->is('api-tokens*') ? 'active' : '' }}" href="/api-tokens">API Tokens</a>
        @endif
        @if($can('study_fields.view'))
            <a class="nav-link {{ request()->is('settings/study-catalogs*') ? 'active' : '' }}" href="/settings/study-catalogs">Study Catalogs</a>
        @endif
        @if($can('settings.view'))
            <a class="nav-link {{ request()->is('health*') ? 'active' : '' }}" href="/health">Health Checks</a>
        @endif
        @if($authUser && $authUser->role_slug === 'super_admin')
            <a class="nav-link {{ request()->is('saas/tenants*') ? 'active' : '' }}" href="/saas/tenants">SaaS Tenants</a>
            <a class="nav-link {{ request()->is('saas/packages*') ? 'active' : '' }}" href="/saas/packages">SaaS Packages</a>
        @endif
        @if($can('settings.view'))
            <a class="nav-link {{ request()->is('settings*') ? 'active' : '' }}" href="/settings">Settings</a>
        @endif
        @if($can('telegram.use'))
            <a class="nav-link {{ request()->is('telegram*') ? 'active' : '' }}" href="/telegram">Telegram Bot</a>
        @endif
        <form method="POST" action="/logout" style="margin-top:16px;">
            @csrf
            <button type="submit" class="secondary" style="width:100%;">Logout</button>
        </form>
    </aside>
    <main class="main">
        <div class="topbar">
            <div style="display:flex;flex-direction:column;">
                <button type="button" class="mobile-menu-btn" onclick="toggleSidebar()">☰ Menu</button>
                <strong>{{ $title ?? 'CRM Workspace' }}</strong>
                <span class="footer-note">Multi-tenant admissions CRM</span>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                <form method="GET" action="/search" class="toolbar" style="margin:0;">
                    <input id="global-search" name="q" placeholder="Search students, applications, universities..." style="min-width:320px;">
                    <button type="submit" class="secondary">Search</button>
                </form>
            </div>
            <div class="topbar-actions">
                <a class="icon-btn" title="Advanced Search" href="/search">🔎</a>
                <button class="icon-btn" title="Notifications" onclick="toggleNotificationPanel()">
                    🔔
                    @if($headerUnread > 0)
                        <span class="notif-dot">{{ $headerUnread }}</span>
                    @endif
                </button>
                <button class="icon-btn" title="Toggle Theme" onclick="toggleTheme()">🌓</button>
            </div>
        </div>

        <div id="notifPanel" class="notif-panel hidden" hidden>
            <div class="notif-panel-head">
                <strong>Notifications</strong>
                <a href="/notifications" class="tab">Open all</a>
            </div>
            @forelse($headerNotifications as $n)
                @php
                    $meta = json_decode((string) $n->meta_json, true) ?: [];
                    $target = null;
                    if (!empty($meta['student_request_id'])) {
                        $target = '/student-requests/'.$meta['student_request_id'];
                    } elseif (!empty($meta['student_id'])) {
                        $target = '/students/'.$meta['student_id'];
                    } elseif (!empty($meta['message_id'])) {
                        $target = '/messages'.(!empty($meta['student_id']) ? '?student_id='.$meta['student_id'] : '');
                    }
                @endphp
                <div class="notif-item {{ $n->read_at ? '' : 'unread' }}">
                    <div><strong>{{ $n->title }}</strong></div>
                    <div class="footer-note">{{ $n->body }}</div>
                    @if($target)
                        <div style="margin-top:6px;"><a class="tab" href="{{ $target }}">Open</a></div>
                    @endif
                    @if(!$n->read_at)
                        <form method="POST" action="/notifications/{{ $n->id }}/read">
                            @csrf
                            <button class="secondary">Mark read</button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="footer-note">No notifications.</div>
            @endforelse
        </div>

        @if(session('success'))
            <div id="toastSuccess" class="toast toast-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <dialog id="globalErrorDialog" open class="card" style="border-color:#ef4444;margin-bottom:10px;max-width:760px;width:100%;background:#fff7f7;">
                <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;">
                    <div>
                        <h3 style="margin:0;color:#b91c1c;">System Error</h3>
                        <p class="footer-note" style="margin:6px 0 0 0;">Please review the following and try again.</p>
                    </div>
                    <button type="button" class="secondary" onclick="document.getElementById('globalErrorDialog').close()">Close</button>
                </div>
                <ul style="margin:10px 0 0 18px;color:#991b1b;line-height:1.7;">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </dialog>
        @endif

        @yield('content')
    </main>
    <nav class="mobile-bottom-nav" aria-label="Mobile navigation">
        <a class="{{ request()->is('dashboard') ? 'active' : '' }}" href="/dashboard"><span>Home</span></a>
        @if($can('students.view'))
            <a class="{{ request()->is('students*') ? 'active' : '' }}" href="/students"><span>Students</span></a>
        @endif
        @if($can('applications.view'))
            <a class="{{ request()->is('applications*') ? 'active' : '' }}" href="/applications"><span>Apps</span></a>
        @endif
        @if($can('tasks.view'))
            <a class="{{ request()->is('tasks*') ? 'active' : '' }}" href="/tasks"><span>Tasks</span></a>
        @endif
        @if($authUser && $authUser->role_slug === 'super_admin')
            <a class="{{ request()->is('saas*') ? 'active' : '' }}" href="/saas/tenants"><span>SaaS</span></a>
        @elseif($can('finance.view'))
            <a class="{{ request()->is('finance*') ? 'active' : '' }}" href="/finance"><span>Finance</span></a>
        @endif
        <button type="button" onclick="toggleSidebar()">More</button>
    </nav>
</div>
<script>
    function toggleTheme() {
        const html = document.documentElement;
        const current = html.getAttribute('data-theme') || 'figma-light';
        let next = 'figma-dark';
        if (current === 'figma-dark') next = 'figma-light';
        else if (current === 'classic-light') next = 'classic-dark';
        else if (current === 'classic-dark') next = 'classic-light';
        html.setAttribute('data-theme', next);
        localStorage.setItem('theme', next);
        document.cookie = 'theme=' + next + ';path=/;max-age=31536000;samesite=lax';
    }

    function toggleNotificationPanel() {
        const panel = document.getElementById('notifPanel');
        if (panel) {
            panel.classList.toggle('hidden');
            panel.hidden = panel.classList.contains('hidden');
        }
    }

    function toggleSidebar() {
        const open = document.body.classList.toggle('sidebar-open');
        const backdrop = document.getElementById('sidebarBackdrop');
        if (backdrop) {
            backdrop.classList.toggle('show', open);
        }
    }

    function closeSidebar() {
        document.body.classList.remove('sidebar-open');
        const backdrop = document.getElementById('sidebarBackdrop');
        if (backdrop) {
            backdrop.classList.remove('show');
        }
    }

    document.querySelectorAll('.sidebar .nav-link').forEach(function (link) {
        link.addEventListener('click', closeSidebar);
    });

    (function () {
        const toast = document.getElementById('toastSuccess');
        if (toast) {
            requestAnimationFrame(function () { toast.classList.add('show'); });
            setTimeout(function () {
                toast.classList.remove('show');
                setTimeout(function () { toast.remove(); }, 250);
            }, 4000);
        }
    })();

    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        const btn = form.querySelector('button[type="submit"]');
        if (!btn || btn.disabled) return;
        if (form.dataset.noLoading === 'true') return;
        btn.dataset.originalText = btn.dataset.originalText || btn.innerHTML;
        btn.disabled = true;
        btn.classList.add('is-loading');
        btn.innerHTML = '<span class="btn-spinner"></span> ' + btn.dataset.originalText;
        setTimeout(function () {
            if (btn.isConnected) {
                btn.disabled = false;
                btn.classList.remove('is-loading');
                btn.innerHTML = btn.dataset.originalText;
            }
        }, 8000);
    }, true);
</script>
</body>
</html>
