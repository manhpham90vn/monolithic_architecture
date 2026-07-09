<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Bán vé sự kiện')</title>
    <style>
        :root { --bg:#f6f7f9; --card:#fff; --ink:#1a1a2e; --muted:#6b7280; --line:#e5e7eb; --brand:#4f46e5; --ok:#059669; --bad:#dc2626; --warn:#d97706; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: ui-sans-serif, system-ui, "Segoe UI", Roboto, sans-serif; background:var(--bg); color:var(--ink); line-height:1.5; }
        a { color:var(--brand); text-decoration:none; }
        a:hover { text-decoration:underline; }
        header { background:var(--card); border-bottom:1px solid var(--line); }
        .wrap { max-width:960px; margin:0 auto; padding:0 20px; }
        nav { display:flex; align-items:center; gap:20px; height:60px; }
        nav .brand { font-weight:700; font-size:18px; color:var(--ink); }
        nav .spacer { flex:1; }
        main { max-width:960px; margin:28px auto; padding:0 20px; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:20px; margin-bottom:18px; }
        h1 { font-size:24px; margin:0 0 16px; }
        h2 { font-size:19px; margin:0 0 12px; }
        .muted { color:var(--muted); }
        .btn { display:inline-block; background:var(--brand); color:#fff; padding:10px 16px; border-radius:8px; border:0; font-size:15px; cursor:pointer; }
        .btn:hover { text-decoration:none; opacity:.92; }
        .btn.secondary { background:#eef2ff; color:var(--brand); }
        .btn.ghost { background:transparent; color:var(--muted); border:1px solid var(--line); }
        input, select { width:100%; padding:9px 11px; border:1px solid var(--line); border-radius:8px; font-size:15px; background:#fff; }
        label { display:block; font-weight:600; font-size:14px; margin:0 0 6px; }
        .field { margin-bottom:14px; }
        .grid { display:grid; gap:16px; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); }
        .badge { display:inline-block; padding:2px 9px; border-radius:999px; font-size:12px; font-weight:600; }
        .badge.ok { background:#d1fae5; color:var(--ok); }
        .badge.bad { background:#fee2e2; color:var(--bad); }
        .badge.warn { background:#fef3c7; color:var(--warn); }
        .badge.info { background:#e0e7ff; color:var(--brand); }
        .alert { padding:12px 14px; border-radius:8px; margin-bottom:16px; font-size:14px; }
        .alert.ok { background:#d1fae5; color:#065f46; }
        .alert.bad { background:#fee2e2; color:#991b1b; }
        table { width:100%; border-collapse:collapse; }
        th, td { text-align:left; padding:10px 8px; border-bottom:1px solid var(--line); }
        .row { display:flex; align-items:center; gap:12px; }
        .between { display:flex; align-items:center; justify-content:space-between; gap:12px; }
        .qty { width:72px; }
        .price { font-variant-numeric: tabular-nums; }
        form.inline { display:inline; }
        .ticket-qr svg { width:180px; height:180px; }
    </style>
</head>
<body>
<header>
    <div class="wrap">
        <nav>
            <a class="brand" href="{{ route('events.index') }}">🎟️ EventTix</a>
            <a href="{{ route('events.index') }}">Sự kiện</a>
            @auth
                <a href="{{ route('tickets.index') }}">Vé của tôi</a>
                @can('check-in')
                    <a href="{{ route('checkin.create') }}">Soát vé</a>
                @endcan
            @endauth
            <span class="spacer"></span>
            @auth
                <span class="muted">{{ auth()->user()->name }}</span>
                <form class="inline" method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn ghost" type="submit">Đăng xuất</button>
                </form>
            @else
                <a href="{{ route('login') }}">Đăng nhập</a>
                <a class="btn secondary" href="{{ route('register') }}">Đăng ký</a>
            @endauth
        </nav>
    </div>
</header>

<main>
    @if (session('status'))
        <div class="alert ok">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert bad">
            <ul style="margin:0; padding-left:18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>
</body>
</html>
