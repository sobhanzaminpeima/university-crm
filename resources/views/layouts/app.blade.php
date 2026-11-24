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
<a class="skip-link" href="#main-content">Skip to main content</a>
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
    @php
        $isSuper = $authUser && $authUser->role_slug === 'super_admin';
        // Navigation grouped by job-to-be-done. Each item: [label, href, active patterns, excluded patterns, visible, icon].
        $navGroups = [
            ['key' => 'admissions', 'label' => 'Admissions', 'open' => true, 'items' => [
                ['Students', '/students', ['students*'], [], $can('students.view'), 'users'],
                ['Applications', '/applications', ['applications*'], [], $can('applications.view'), 'file'],
                ['Pipeline Board', '/pipeline', ['pipeline*'], [], $can('students.view'), 'columns'],
                ['Student Requests', '/student-requests', ['student-requests*'], [], $can('student_requests.view'), 'inbox'],
                ['Intake Calendar', '/calendar', ['calendar*'], [], $can('applications.view'), 'calendar'],
            ]],
            ['key' => 'catalog', 'label' => 'Universities & Programs', 'open' => true, 'items' => [
                ['Universities', '/universities', ['universities*'], [], $can('universities.view'), 'building'],
                ['Program Search', '/search', ['search*'], [], $can('students.view'), 'search'],
                ['Scholarships', '/scholarships', ['scholarships*'], [], $can('scholarships.view'), 'award'],
                ['Study Catalogs', '/settings/study-catalogs', ['settings/study-catalogs*'], [], $can('study_fields.view'), 'book'],
            ]],
            ['key' => 'communication', 'label' => 'Work & Communication', 'open' => true, 'items' => [
                ['Tasks', '/tasks', ['tasks*'], [], $can('tasks.view'), 'check'],
                ['Messages', '/messages', ['messages*'], [], $can('messages.view'), 'chat'],
                ['Notifications', '/notifications', ['notifications*'], [], (bool) $authUser, 'bell'],
                ['Telegram Bot', '/telegram', ['telegram*'], [], $can('telegram.use'), 'send'],
            ]],
            ['key' => 'insights', 'label' => 'Finance & Reports', 'open' => false, 'items' => [
                ['Finance', '/finance', ['finance*'], [], $can('finance.view'), 'wallet'],
                ['Advanced Reports', '/reports/advanced', ['reports*'], [], $can('reports.view'), 'chart'],
                ['Agent Performance', '/agents/performance', ['agents/performance*'], [], $can('agent_performance.view'), 'trend'],
            ]],
            ['key' => 'team', 'label' => 'Team & Automation', 'open' => false, 'items' => [
                ['Agents & Roles', '/agents', ['agents*'], ['agents/performance*'], $can('users.view'), 'shield'],
                ['Templates', '/templates', ['templates*'], [], $can('settings.update'), 'mail'],
                ['Automation Rules', '/automation-rules', ['automation-rules*'], [], $can('settings.update'), 'zap'],
            ]],
            ['key' => 'system', 'label' => 'System', 'open' => false, 'items' => [
                ['Settings', '/settings', ['settings*'], ['settings/study-catalogs*'], $can('settings.view'), 'gear'],
                ['API Tokens', '/api-tokens', ['api-tokens*'], [], $can('settings.update'), 'key'],
                ['Health Checks', '/health', ['health*'], [], $can('settings.view'), 'pulse'],
                ['Audit Logs', '/audit-logs', ['audit-logs*'], [], $can('audit.view'), 'list'],
            ]],
            ['key' => 'platform', 'label' => 'SaaS Platform', 'open' => false, 'items' => [
                ['Tenants', '/saas/tenants', ['saas/tenants*'], [], $isSuper, 'globe'],
                ['Packages', '/saas/packages', ['saas/packages*'], [], $isSuper, 'box'],
            ]],
        ];
        $navActive = function (array $patterns, array $exclude): bool {
            foreach ($exclude as $ex) {
                if (request()->is($ex)) return false;
            }
            foreach ($patterns as $pattern) {
                if (request()->is($pattern)) return true;
            }
            return false;
        };
        $navIcons = [
            'home' => 'M3 10.5 12 3l9 7.5V21h-6v-6H9v6H3z',
            'users' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75',
            'file' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M8 13h8M8 17h5',
            'columns' => 'M3 3h5v18H3zM10 3h5v12h-5zM17 3h4v8h-4z',
            'inbox' => 'M22 12h-6l-2 3h-4l-2-3H2M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11',
            'calendar' => 'M3 6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zM16 2v4M8 2v4M3 10h18',
            'building' => 'M3 21h18M5 21V7l7-4 7 4v14M9 9h1M14 9h1M9 13h1M14 13h1M9 17h1M14 17h1',
            'search' => 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4.35-4.35',
            'award' => 'M12 15a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM8.21 13.89 7 23l5-3 5 3-1.21-9.12',
            'book' => 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20V2H6.5A2.5 2.5 0 0 0 4 4.5v15zM4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5',
            'check' => 'M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11',
            'chat' => 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z',
            'bell' => 'M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0',
            'send' => 'M22 2 11 13M22 2l-7 20-4-9-9-4z',
            'wallet' => 'M20 7H5a2 2 0 0 1 0-4h13v4M3 5v14a2 2 0 0 0 2 2h15V7M16 14h.01',
            'chart' => 'M3 3v18h18M7 15l4-4 3 3 5-6',
            'trend' => 'M23 6l-9.5 9.5-5-5L1 18M17 6h6v6',
            'shield' => 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z',
            'mail' => 'M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zM22 6l-10 7L2 6',
            'zap' => 'M13 2 3 14h9l-1 8 10-12h-9z',
            'gear' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-2.82 1.17V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 7 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 3.6 15a1.65 1.65 0 0 0-1.51-1H2a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 3.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6 1.65 1.65 0 0 0 10 3.09V3a2 2 0 1 1 4 0v.09A1.65 1.65 0 0 0 15 4.6a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z',
            'key' => 'M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.78 7.78 5.5 5.5 0 0 1 7.78-7.78zM15.5 7.5l3 3L22 7l-3-3',
            'pulse' => 'M22 12h-4l-3 9L9 3l-3 9H2',
            'list' => 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
            'globe' => 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zM2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z',
            'box' => 'M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16zM3.27 6.96 12 12.01l8.73-5.05M12 22.08V12',
        ];
        $navIcon = fn (string $name) => '<svg class="nav-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="'.($navIcons[$name] ?? $navIcons['list']).'"/></svg>';
    @endphp
    <aside class="sidebar" id="appSidebar" aria-label="Main navigation">
        <h1>Vertue CRM</h1>
        <nav class="side-nav">
            <a class="nav-link {{ request()->is('dashboard') ? 'active' : '' }}" href="/dashboard" @if(request()->is('dashboard')) aria-current="page" @endif>{!! $navIcon('home') !!}<span>Dashboard</span></a>
            @foreach($navGroups as $group)
                @php
                    $visibleItems = array_values(array_filter($group['items'], fn ($item) => $item[4]));
                    $groupActive = collect($visibleItems)->contains(fn ($item) => $navActive($item[2], $item[3]));
                @endphp
                @continue(count($visibleItems) === 0)
                <details class="menu-group" data-nav-group="{{ $group['key'] }}" data-default-open="{{ $group['open'] ? '1' : '0' }}" @if($group['open'] || $groupActive) open @endif @if($groupActive) data-has-active="1" @endif>
                    <summary><span>{{ $group['label'] }}</span><svg class="menu-caret" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></summary>
                    @foreach($visibleItems as $item)
                        @php $isActive = $navActive($item[2], $item[3]); @endphp
                        <a class="nav-link {{ $isActive ? 'active' : '' }}" href="{{ $item[1] }}" @if($isActive) aria-current="page" @endif>{!! $navIcon($item[5]) !!}<span>{{ $item[0] }}</span></a>
                    @endforeach
                </details>
            @endforeach
        </nav>
        <div class="sidebar-footer">
            @if($authUser)
                <div class="sidebar-user"><strong>{{ $authUser->name }}</strong><span>{{ ucwords(str_replace('_', ' ', (string) $authUser->role_slug)) }}</span></div>
            @endif
            <form method="POST" action="/logout">
                @csrf
                <button type="submit" class="secondary" style="width:100%;">Log out</button>
            </form>
        </div>
    </aside>
    <main class="main" id="main-content" tabindex="-1">
        <div class="topbar">
            <div style="display:flex;flex-direction:column;">
                <button type="button" class="mobile-menu-btn" onclick="toggleSidebar()" aria-controls="appSidebar" aria-expanded="false">☰ Menu</button>
                <strong>{{ $title ?? 'CRM Workspace' }}</strong>
                <span class="footer-note">Multi-tenant admissions CRM</span>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                <form method="GET" action="/search" class="toolbar topbar-search" role="search" style="margin:0;">
                    <label for="topbar-search-input" class="sr-only">Search the CRM</label>
                    <input id="topbar-search-input" type="search" name="q" value="{{ request()->is('search*') ? request('q') : '' }}" placeholder="Search students, applications, universities… (press /)" aria-keyshortcuts="/">
                    <button type="submit" class="secondary">Search</button>
                </form>
            </div>
            <div class="topbar-actions">
                <a class="icon-btn" title="Advanced Search" aria-label="Advanced search" href="/search"><span aria-hidden="true">🔎</span></a>
                <button type="button" class="icon-btn" id="notifToggle" title="Notifications" aria-label="Notifications{{ $headerUnread > 0 ? ' ('.$headerUnread.' unread)' : '' }}" aria-controls="notifPanel" aria-expanded="false" onclick="toggleNotificationPanel()">
                    <span aria-hidden="true">🔔</span>
                    @if($headerUnread > 0)
                        <span class="notif-dot" aria-hidden="true">{{ $headerUnread }}</span>
                    @endif
                </button>
                <button type="button" class="icon-btn" title="Toggle theme" aria-label="Toggle light or dark theme" onclick="toggleTheme()"><span aria-hidden="true">🌓</span></button>
            </div>
        </div>

        <div id="notifPanel" class="notif-panel hidden" hidden role="region" aria-label="Recent notifications">
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
            <div id="toastSuccess" class="toast toast-success" role="status" aria-live="polite">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div id="globalErrorDialog" class="card form-error-summary" role="alert" tabindex="-1">
                <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;">
                    <div>
                        <h3 style="margin:0;">Some information needs your attention</h3>
                        <p class="footer-note" style="margin:6px 0 0 0;">Nothing was saved. Please correct the following and try again.</p>
                    </div>
                    <button type="button" class="secondary" aria-label="Dismiss message" onclick="document.getElementById('globalErrorDialog').remove()">Close</button>
                </div>
                <ul>
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
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
        <button type="button" onclick="toggleSidebar()" aria-controls="appSidebar" aria-expanded="false">More</button>
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

    function setNotificationPanel(open) {
        const panel = document.getElementById('notifPanel');
        const toggle = document.getElementById('notifToggle');
        if (!panel) return;
        panel.classList.toggle('hidden', !open);
        panel.hidden = !open;
        if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    function toggleNotificationPanel() {
        const panel = document.getElementById('notifPanel');
        if (panel) setNotificationPanel(panel.hidden);
    }

    function syncSidebarToggles(open) {
        document.querySelectorAll('[aria-controls="appSidebar"]').forEach(function (btn) {
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    function toggleSidebar() {
        const open = document.body.classList.toggle('sidebar-open');
        const backdrop = document.getElementById('sidebarBackdrop');
        if (backdrop) {
            backdrop.classList.toggle('show', open);
        }
        syncSidebarToggles(open);
    }

    function closeSidebar() {
        document.body.classList.remove('sidebar-open');
        const backdrop = document.getElementById('sidebarBackdrop');
        if (backdrop) {
            backdrop.classList.remove('show');
        }
        syncSidebarToggles(false);
    }

    // Remember which sidebar groups the user collapsed/expanded (the active page's group always opens).
    (function () {
        let saved = {};
        try { saved = JSON.parse(localStorage.getItem('navGroups') || '{}') || {}; } catch (e) { saved = {}; }
        document.querySelectorAll('details[data-nav-group]').forEach(function (group) {
            const key = group.dataset.navGroup;
            if (group.dataset.hasActive !== '1' && Object.prototype.hasOwnProperty.call(saved, key)) {
                group.open = !!saved[key];
            }
            group.addEventListener('toggle', function () {
                saved[key] = group.open;
                try { localStorage.setItem('navGroups', JSON.stringify(saved)); } catch (e) {}
            });
        });
    })();

    // Keyboard support: "/" focuses global search, Escape closes overlays.
    document.addEventListener('keydown', function (e) {
        const t = e.target;
        const typing = t && (t.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName));
        if (e.key === '/' && !typing && !e.ctrlKey && !e.metaKey && !e.altKey) {
            const search = document.getElementById('topbar-search-input');
            if (search) {
                e.preventDefault();
                search.focus();
            }
        } else if (e.key === 'Escape') {
            const panel = document.getElementById('notifPanel');
            if (panel && !panel.hidden) {
                setNotificationPanel(false);
                const toggle = document.getElementById('notifToggle');
                if (toggle) toggle.focus();
            }
            if (document.body.classList.contains('sidebar-open')) closeSidebar();
        }
    });

    (function () {
        const summary = document.getElementById('globalErrorDialog');
        if (summary && !document.querySelector('dialog[open]')) summary.focus();
    })();

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
