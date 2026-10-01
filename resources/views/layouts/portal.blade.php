<!doctype html>
<html lang="en"
      data-theme="{{ request()->cookie('theme', 'figma-light') }}"
      data-font-scale="{{ request()->cookie('font_scale', 'base') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Student Portal</title>
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
<div class="layout portal-layout">
    <div class="sidebar-backdrop" id="portalSidebarBackdrop" onclick="closePortalSidebar()"></div>
    <aside class="sidebar">
        <h1>Student Portal</h1>
        <a class="nav-link {{ request()->is('portal/dashboard') ? 'active' : '' }}" href="/portal/dashboard">Dashboard</a>
        <a class="nav-link {{ request()->is('portal/applications') ? 'active' : '' }}" href="/portal/applications">My Applications</a>
        <a class="nav-link {{ request()->is('portal/documents') ? 'active' : '' }}" href="/portal/documents">My Documents</a>
        <a class="nav-link {{ request()->is('portal/universities') ? 'active' : '' }}" href="/portal/universities">Universities</a>
        <a class="nav-link {{ request()->is('portal/messages') ? 'active' : '' }}" href="/portal/messages">Messages</a>
        <form method="POST" action="/portal/logout" style="margin-top:16px;">
            @csrf
            <button type="submit" class="secondary" style="width:100%;">Logout</button>
        </form>
    </aside>
    <main class="main">
        <div class="portal-mobile-topbar">
            <button type="button" class="mobile-menu-btn" onclick="togglePortalSidebar()">☰ Menu</button>
            <strong>Student Portal</strong>
        </div>
        <div class="topbar">
            <div style="display:flex;flex-direction:column;">
                <strong>Student Workspace</strong>
                <span class="footer-note">Track your admission progress in real time</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <a class="tab" href="/portal/dashboard">Home</a>
                <button class="icon-btn" title="Toggle Theme" onclick="toggleTheme()">&#9790;</button>
            </div>
        </div>
        @if(session('success'))
            <div class="card" style="border-color:#22c55e;margin-bottom:10px;">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <dialog id="portalErrorDialog" open class="card" style="border-color:#ef4444;margin-bottom:10px;max-width:760px;width:100%;background:#fff7f7;">
                <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;">
                    <div>
                        <h3 style="margin:0;color:#b91c1c;">Request Error</h3>
                        <p class="footer-note" style="margin:6px 0 0 0;">Please review the message and try again.</p>
                    </div>
                    <button type="button" class="secondary" onclick="document.getElementById('portalErrorDialog').close()">Close</button>
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
    <nav class="mobile-bottom-nav portal-bottom-nav" aria-label="Student mobile navigation">
        <a class="{{ request()->is('portal/dashboard') ? 'active' : '' }}" href="/portal/dashboard"><span>Home</span></a>
        <a class="{{ request()->is('portal/applications') ? 'active' : '' }}" href="/portal/applications"><span>Apps</span></a>
        <a class="{{ request()->is('portal/documents') ? 'active' : '' }}" href="/portal/documents"><span>Docs</span></a>
        <a class="{{ request()->is('portal/universities') ? 'active' : '' }}" href="/portal/universities"><span>Unis</span></a>
        <button type="button" onclick="togglePortalSidebar()">More</button>
    </nav>
</div>
<script>
    function togglePortalSidebar() {
        const open = document.body.classList.toggle('sidebar-open');
        const backdrop = document.getElementById('portalSidebarBackdrop');
        if (backdrop) {
            backdrop.classList.toggle('show', open);
        }
    }

    function closePortalSidebar() {
        document.body.classList.remove('sidebar-open');
        const backdrop = document.getElementById('portalSidebarBackdrop');
        if (backdrop) {
            backdrop.classList.remove('show');
        }
    }
    document.querySelectorAll('.sidebar .nav-link').forEach(function (link) {
        link.addEventListener('click', closePortalSidebar);
    });
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
</script>
</body>
</html>
