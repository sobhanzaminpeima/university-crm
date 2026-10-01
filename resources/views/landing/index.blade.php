<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Smart CRM for Student Recruitment Agencies">
    <title>Vertue CRM SaaS</title>
    <style>
        :root{
            --bg:#f5f8ff;
            --card:#ffffff;
            --text:#0f172a;
            --muted:#475569;
            --line:#dbe3f1;
            --brand:#2563eb;
            --brand-2:#7c3aed;
            --dark:#0b1220;
        }
        *{box-sizing:border-box}
        body{
            margin:0;
            font-family:Inter,Segoe UI,Arial,sans-serif;
            color:var(--text);
            background:
                radial-gradient(circle at 10% 0%, rgba(37,99,235,.10), transparent 30%),
                radial-gradient(circle at 90% 0%, rgba(124,58,237,.09), transparent 28%),
                var(--bg);
        }
        .wrap{width:min(1160px,calc(100vw - 32px));margin:0 auto}
        .top{
            position:sticky;top:0;z-index:40;
            background:rgba(245,248,255,.86);
            border-bottom:1px solid var(--line);
            backdrop-filter:blur(10px);
        }
        .top-inner{
            min-height:72px;display:flex;align-items:center;justify-content:space-between;
        }
        .brand{font-weight:800;letter-spacing:.04em}
        .nav{display:flex;align-items:center;gap:10px}
        .nav a{text-decoration:none;color:#334155;font-weight:500}
        .btn{
            display:inline-flex;align-items:center;justify-content:center;
            padding:10px 16px;border-radius:12px;text-decoration:none;font-weight:700;
            border:1px solid transparent;transition:.2s ease;
        }
        .btn:hover{transform:translateY(-1px)}
        .btn-login{background:#fff;color:#0f172a;border-color:#cbd5e1}
        .btn-main{background:linear-gradient(135deg,var(--brand),var(--brand-2));color:#fff}
        .hero{
            margin-top:22px;border-radius:24px;padding:76px 28px 86px;
            background:linear-gradient(130deg,#0f1b36,#1f3f95 46%,#5f3fc5);
            color:#fff;position:relative;overflow:hidden;
            box-shadow:0 22px 40px rgba(11,18,32,.24);
        }
        .hero:before,.hero:after{
            content:"";position:absolute;border-radius:999px;filter:blur(2px)
        }
        .hero:before{width:340px;height:340px;right:-110px;top:-120px;background:rgba(56,189,248,.19)}
        .hero:after{width:260px;height:260px;left:-90px;bottom:-110px;background:rgba(167,139,250,.23)}
        .hero h1{margin:0 auto;max-width:860px;text-align:center;font-size:clamp(32px,5.4vw,62px);line-height:1.02}
        .hero p{margin:16px auto 0;max-width:760px;text-align:center;color:#dbeafe;font-size:clamp(16px,2vw,21px)}
        .hero-cta{margin-top:24px;display:flex;justify-content:center;gap:10px;flex-wrap:wrap}
        .section{padding:44px 0}
        .title{text-align:center;margin:0 0 12px;font-size:clamp(26px,3.5vw,42px)}
        .sub{text-align:center;color:var(--muted);max-width:760px;margin:0 auto 20px}
        .grid{
            display:grid;gap:14px;
            grid-template-columns:repeat(3,minmax(0,1fr));
        }
        .card{
            background:var(--card);border:1px solid var(--line);border-radius:16px;padding:18px;
            box-shadow:0 10px 22px rgba(15,23,42,.05);
        }
        .card h3{margin:0;font-size:18px}
        .pricing{display:grid;gap:14px;grid-template-columns:repeat(4,minmax(0,1fr))}
        .price-card{display:flex;flex-direction:column}
        .amount{font-size:34px;font-weight:800;margin:8px 0 0}
        .duration{color:var(--muted);margin-top:4px}
        .price-card ul{margin:10px 0 12px 16px;padding:0;min-height:140px;color:#334155}
        .price-card li{margin-bottom:6px}
        .steps{display:grid;gap:12px;grid-template-columns:repeat(4,minmax(0,1fr))}
        .step{font-weight:700}
        footer{
            margin-top:26px;background:var(--dark);color:#cbd5e1;border-top:1px solid rgba(148,163,184,.18)
        }
        .footer-inner{
            min-height:68px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap
        }
        .footer-links{display:flex;gap:14px;flex-wrap:wrap}
        .footer-links a{color:#cbd5e1;text-decoration:none}
        .menu-btn{display:none}
        @media (max-width:1020px){
            .pricing{grid-template-columns:repeat(2,minmax(0,1fr))}
            .grid{grid-template-columns:repeat(2,minmax(0,1fr))}
            .steps{grid-template-columns:repeat(2,minmax(0,1fr))}
        }
        @media (max-width:700px){
            .menu-btn{display:inline-flex}
            .nav-links{
                display:none;position:absolute;left:16px;right:16px;top:74px;padding:12px;background:#fff;border:1px solid var(--line);border-radius:14px;
                flex-direction:column;gap:8px;
            }
            .nav-links.show{display:flex}
            .grid,.pricing,.steps{grid-template-columns:1fr}
            .hero{padding:56px 18px 64px}
            .footer-inner{padding:10px 0}
        }
    </style>
</head>
<body>
    <header class="top">
        <div class="wrap top-inner">
            <div class="brand">Vertue CRM</div>
            <nav class="nav">
                <button class="btn btn-login menu-btn" type="button" onclick="toggleNav()">Menu</button>
                <div id="navLinks" class="nav-links">
                    <a href="#features">Features</a>
                    <a href="#pricing">Pricing</a>
                    <a href="#how">How It Works</a>
                    <a href="/contact">Contact</a>
                </div>
                <a class="btn btn-login" href="/login">Login</a>
                <a class="btn btn-main" href="/register">Get Started</a>
            </nav>
        </div>
    </header>

    <main class="wrap">
        <section class="hero">
            <h1>Smart CRM for Student Recruitment Agencies</h1>
            <p>Manage students, universities, agents and applications in one powerful platform.</p>
            <div class="hero-cta">
                <a class="btn btn-main" href="/register">Get Started</a>
                <a class="btn btn-login" href="/login">Login</a>
            </div>
        </section>

        <section id="features" class="section">
            <h2 class="title">Core SaaS Features</h2>
            <p class="sub">Built for admissions teams that need speed, control and visibility across the full student pipeline.</p>
            <div class="grid">
                @foreach(['Student Management','Agent & Sub-Agent System','Application Tracking','Advanced Search','Reports & PDF Export','Multi-Currency','Messaging System'] as $feature)
                    <article class="card"><h3>{{ $feature }}</h3></article>
                @endforeach
            </div>
        </section>

        <section id="pricing" class="section">
            <h2 class="title">Pricing & Plans</h2>
            <p class="sub">Dynamic packages from your SaaS panel. Update once, reflect everywhere.</p>
            <div class="pricing">
                @forelse($plans as $plan)
                    <article class="card price-card">
                        <h3>{{ $plan->name }}</h3>
                        <p class="amount">{{ $plan->currency }} {{ number_format((float)$plan->price,0) }}</p>
                        <p class="duration">{{ $plan->duration_months }} month(s)</p>
                        <ul>
                            @foreach(($plan->features_json ?? []) as $f)
                                <li>{{ $f }}</li>
                            @endforeach
                        </ul>
                        <a class="btn btn-main" href="/register?plan_id={{ $plan->id }}">Select Plan</a>
                    </article>
                @empty
                    <article class="card"><h3>No active plans found.</h3></article>
                @endforelse
            </div>
        </section>

        <section id="how" class="section">
            <h2 class="title">How It Works</h2>
            <div class="steps">
                @foreach(['Register Company','Choose Plan','Setup Team','Start Managing Students'] as $step)
                    <article class="card step">{{ $loop->iteration }}. {{ $step }}</article>
                @endforeach
            </div>
        </section>
    </main>

    <footer>
        <div class="wrap footer-inner">
            <div>© {{ date('Y') }} Vertue CRM</div>
            <div class="footer-links">
                <a href="/login">Login</a>
                <a href="/register">Register</a>
                <a href="/privacy-policy">Privacy Policy</a>
                <a href="/contact">Contact</a>
            </div>
        </div>
    </footer>

    <script>
        function toggleNav() {
            const el = document.getElementById('navLinks');
            if (!el) return;
            el.classList.toggle('show');
        }
    </script>
</body>
</html>

