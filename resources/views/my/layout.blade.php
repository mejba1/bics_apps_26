{{-- Participant dashboard (/my): app shell — sidebar on desktop, bottom tabs on phones; split-screen shell when signed out --}}
@php
  $me = Auth::guard('participant')->user();
  $eventShort = \App\Support\EventSettings::get('event_short') ?: 'BICS';
  $eventName = \App\Support\EventSettings::get('event_name');
  $nav = [
      ['my.home', 'Overview', 'bi-grid-1x2', 'bi-grid-1x2-fill'],
      ['my.events', 'My events', 'bi-calendar-event', 'bi-calendar-event-fill'],
      ['my.card', 'Card & QR', 'bi-qr-code', 'bi-qr-code'],
      ['my.edit', 'Profile', 'bi-person', 'bi-person-fill'],
      ['my.security', 'Security', 'bi-shield-lock', 'bi-shield-lock-fill'],
  ];
  $avatar = $me && $me->photo && is_file(base_path($me->photo)) ? \App\Support\Thumb::url($me->photo, 480) : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex, nofollow">
  <meta name="referrer" content="no-referrer">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="theme-color" content="#0a2350">
  <title>@yield('title') · {{ $me ? $eventShort : 'BICS' }}</title>
  <link rel="icon" href="{{ asset('assets/frontend/images/logo.png') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    :root {
      --blue: #0e4ca1; --blue-2: #1d63c9; --navy: #0a2350; --magenta: #b22e6b; --green: #2f8a4c; --teal: #1f8fb3; --gold: #c98a12;
      --ink: #0f1a2e; --text: #3a4458; --muted: #6b7489; --line: #e7ebf3; --soft: #f3f6fb; --card: #ffffff;
      --radius: 18px; --shadow: 0 1px 2px rgba(16,30,60,.04), 0 12px 32px -20px rgba(16,30,60,.28);
      --bs-body-font-family: "Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", sans-serif; --bs-body-color: var(--text);
      --bs-primary: #0e4ca1; --bs-primary-rgb: 14,76,161; --bs-link-color: #0e4ca1; --bs-link-hover-color: #0b3f86; --bs-border-radius: 10px;
    }
    body { background: var(--soft); -webkit-font-smoothing: antialiased; }
    h1, h2, h3, .ink { color: var(--ink); }
    .btn { font-weight: 600; border-radius: 11px; }
    .btn-primary { --bs-btn-bg: var(--blue); --bs-btn-border-color: var(--blue); --bs-btn-hover-bg: #0b3f86; --bs-btn-hover-border-color: #0b3f86; --bs-btn-active-bg: #0a3877; --bs-btn-disabled-bg: var(--blue); }
    .btn-outline-primary { --bs-btn-color: var(--blue); --bs-btn-border-color: #c7d6ee; --bs-btn-hover-bg: var(--blue); --bs-btn-hover-border-color: var(--blue); }
    .btn-soft { background: #edf2fb; color: var(--blue); border: 0; } .btn-soft:hover { background: #dfe8f8; color: var(--blue); }
    .form-control, .form-select { border-color: #dbe2ee; padding: .55rem .8rem; }
    .form-control:focus, .form-select:focus { border-color: #8fb0e3; box-shadow: 0 0 0 .2rem rgba(14,76,161,.12); }
    .form-check-input:checked { background-color: var(--blue); border-color: var(--blue); }
    .muted { color: var(--muted); }
    .min-w-0 { min-width: 0; }
    .row > [class*=col] > .panel.h-100 { margin-bottom: 0; }
    .row.g-3 + .panel, .row.g-3 + .row { margin-top: 18px; }

    /* app shell */
    .shell { display: flex; min-height: 100vh; }
    .side { width: 256px; flex: none; background: linear-gradient(180deg, var(--navy) 0%, #0d2f68 100%); color: #cfd9ec; position: sticky; top: 0; height: 100vh; display: flex; flex-direction: column; padding: 18px 14px; }
    .side .brand { display: flex; align-items: center; gap: 10px; padding: 4px 6px 18px; color: #fff; font-weight: 800; text-decoration: none; }
    .side .brand img { height: 30px; background: #fff; border-radius: 9px; padding: 4px 7px; }
    .side .me { display: flex; gap: 10px; align-items: center; background: rgba(255,255,255,.07); border-radius: 14px; padding: 10px; margin-bottom: 16px; }
    .side .me b { color: #fff; font-size: 14px; display: block; line-height: 1.2; }
    .side .me small { font-size: 12px; opacity: .75; }
    .av-sm { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; flex: none; background: #e9eff9; color: var(--blue); font-weight: 800; display: inline-flex; align-items: center; justify-content: center; font-size: 14px; }
    .side nav a { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 12px; color: #cfd9ec; text-decoration: none; font-weight: 600; font-size: 14.5px; margin-bottom: 2px; }
    .side nav a i { font-size: 18px; width: 20px; text-align: center; }
    .side nav a:hover { background: rgba(255,255,255,.07); color: #fff; }
    .side nav a.on { background: #fff; color: var(--navy); box-shadow: 0 8px 20px -12px rgba(0,0,0,.6); }
    .side nav a.on i { color: var(--blue); }
    .side .foot { margin-top: auto; display: grid; gap: 6px; }
    .side .foot a, .side .foot button { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 12px; color: #cfd9ec; text-decoration: none; font-size: 14px; font-weight: 600; background: none; border: 0; width: 100%; text-align: left; }
    .side .foot a:hover, .side .foot button:hover { background: rgba(255,255,255,.07); color: #fff; }
    .side .stripe { display: flex; height: 3px; border-radius: 3px; overflow: hidden; margin: 12px 6px 4px; } .stripe i { flex: 1; }
    .stripe i:nth-child(1) { background: #3b7be0; } .stripe i:nth-child(2) { background: var(--magenta); } .stripe i:nth-child(3) { background: #5d9b3a; } .stripe i:nth-child(4) { background: #3f97bf; }
    .main { flex: 1; min-width: 0; }
    .topbar { display: flex; align-items: center; gap: 12px; padding: 22px 32px 6px; }
    .topbar h1 { font-size: 1.55rem; font-weight: 800; margin: 0; letter-spacing: -.01em; }
    .topbar .sub { color: var(--muted); font-size: 14px; margin-top: 2px; }
    .content { padding: 14px 32px 48px; max-width: 1180px; }
    .mobilebar, .tabs { display: none; }

    .panel { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 20px; margin-bottom: 18px; border: 1px solid rgba(231,235,243,.7); }
    .panel-h { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
    .panel-h h2 { font-size: 1.02rem; font-weight: 800; margin: 0; }
    .panel-h .ico { width: 34px; height: 34px; border-radius: 10px; background: #edf2fb; color: var(--blue); display: inline-flex; align-items: center; justify-content: center; font-size: 17px; }
    .panel-h .more { margin-left: auto; font-size: 13.5px; font-weight: 700; text-decoration: none; }
    .pill { display: inline-flex; align-items: center; gap: 5px; border-radius: 30px; padding: 3px 10px; font-size: 12px; font-weight: 700; white-space: nowrap; }
    .pill.blue { background: #e8f0fc; color: var(--blue); } .pill.green { background: #e5f4ea; color: #1d6b39; } .pill.amber { background: #fff3dc; color: #8a5a00; }
    .pill.red { background: #fde8eb; color: #a3243a; } .pill.gray { background: #eef1f6; color: #5b6475; } .pill.magenta { background: #f8e6ee; color: var(--magenta); } .pill.gold { background: #fff4d6; color: #8a5a00; }

    .my-toast { position: fixed; left: 50%; bottom: 24px; transform: translateX(-50%) translateY(20px); background: var(--ink); color: #fff; padding: 11px 18px; border-radius: 14px; font-weight: 600; font-size: 14px; opacity: 0; transition: .25s; z-index: 2000; max-width: calc(100% - 32px); box-shadow: 0 14px 30px -12px rgba(0,0,0,.5); pointer-events: none; }
    .my-toast.on { opacity: 1; transform: translateX(-50%) translateY(0); }
    .my-toast.err { background: #b42335; }

    @media (max-width: 991px) {
      .side { display: none; }
      .mobilebar { display: flex; align-items: center; gap: 10px; position: sticky; top: 0; z-index: 50; background: rgba(10,35,80,.97); backdrop-filter: blur(8px); color: #fff; padding: 10px 16px; padding-top: max(10px, env(safe-area-inset-top)); }
      .mobilebar img { height: 26px; background: #fff; border-radius: 8px; padding: 3px 6px; }
      .mobilebar b { font-weight: 800; }
      .mobilebar .av-sm { width: 34px; height: 34px; margin-left: auto; }
      .topbar { padding: 18px 16px 2px; } .topbar h1 { font-size: 1.3rem; }
      .content { padding: 12px 16px 100px; }
      .panel { padding: 16px; border-radius: 16px; }
      .tabs { display: flex; position: fixed; bottom: 0; left: 0; right: 0; z-index: 60; background: #fff; border-top: 1px solid var(--line); padding: 6px 4px; padding-bottom: max(6px, env(safe-area-inset-bottom)); box-shadow: 0 -8px 24px -18px rgba(16,30,60,.4); }
      .tabs a { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 2px; text-decoration: none; color: #7a8396; font-size: 11px; font-weight: 700; padding: 4px 0; border-radius: 10px; }
      .tabs a i { font-size: 20px; }
      .tabs a.on { color: var(--blue); }
      .my-toast { bottom: 84px; }
    }

    /* signed-out shell: brand panel (partials/auth-brand) + form */
    .auth { min-height: 100vh; display: grid; grid-template-columns: minmax(0, 1.08fr) minmax(0, 1fr); background: #fff; }
    .auth-art { position: sticky; top: 0; height: 100vh; }
    .auth-side { display: flex; flex-direction: column; min-height: 100vh; padding: 22px 32px; }
    .auth-bar { display: flex; align-items: center; justify-content: space-between; gap: 10px; font-size: 14px; }
    .auth-bar a { color: var(--muted); text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
    .auth-bar a:hover { color: var(--blue); }
    .auth-form { flex: 1; display: flex; align-items: center; justify-content: center; padding: 28px 0; }
    .auth-box { width: 100%; max-width: 430px; }
    .auth-legal { text-align: center; font-size: 12.5px; color: #8a92a6; }
    .auth-legal a { color: inherit; }
    @media (max-width: 991.98px) {
      .auth { grid-template-columns: 1fr; background: var(--soft); }
      .auth-art { position: static; height: auto; }
      .auth-side { min-height: 0; padding: 0 16px 24px; }
      .auth-bar { order: 3; justify-content: center; gap: 18px; margin-top: 18px; }
      .auth-form { padding: 0; margin-top: -10px; }
      .auth-box { background: #fff; border-radius: 22px; padding: 24px 20px; box-shadow: 0 18px 40px -26px rgba(16,30,60,.45); border: 1px solid var(--line); }
      .auth-legal { order: 4; margin-top: 12px; }
    }
  </style>
  @unless($me)<link rel="stylesheet" href="{{ asset('assets/frontend/css/auth-brand.css') }}?v={{ @filemtime(base_path('assets/frontend/css/auth-brand.css')) }}">@endunless
  @stack('styles')
</head>
<body>
@if($me)
  <div class="shell">
    <aside class="side">
      <a class="brand" href="{{ route('my.home') }}"><img src="{{ asset('assets/frontend/images/logo.png') }}" alt=""><span>My {{ $eventShort }}</span></a>
      <div class="me">
        @if($avatar)<img class="av-sm" src="{{ $avatar }}" alt="">@else<span class="av-sm">{{ $me->initials }}</span>@endif
        <div class="min-w-0 flex-grow-1"><b class="text-truncate" title="{{ $me->name }}">{{ $me->name }}</b><small>{{ $me->code }}</small></div>
      </div>
      <nav>
        @foreach($nav as [$route, $label, $icon, $iconOn])
          <a href="{{ route($route) }}" class="{{ request()->routeIs($route) ? 'on' : '' }}"><i class="bi {{ request()->routeIs($route) ? $iconOn : $icon }}"></i>{{ $label }}</a>
        @endforeach
      </nav>
      <div class="foot">
        <div class="stripe"><i></i><i></i><i></i><i></i></div>
        <a href="{{ $me->url() }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> My public profile</a>
        <form method="post" action="{{ route('my.logout') }}" class="m-0">@csrf<button><i class="bi bi-box-arrow-left"></i> Sign out</button></form>
      </div>
    </aside>
    <div class="main">
      <div class="mobilebar">
        <img src="{{ asset('assets/frontend/images/logo.png') }}" alt=""><b>My {{ $eventShort }}</b>
        <div class="dropdown ms-auto">
          <button class="btn p-0 border-0" data-bs-toggle="dropdown" aria-label="Account">@if($avatar)<img class="av-sm" src="{{ $avatar }}" alt="">@else<span class="av-sm">{{ $me->initials }}</span>@endif</button>
          <ul class="dropdown-menu dropdown-menu-end shadow">
            <li class="px-3 py-2 small"><b class="ink">{{ $me->name }}</b><br><span class="muted">{{ $me->code }}</span></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="{{ $me->url() }}" target="_blank"><i class="bi bi-box-arrow-up-right"></i> My public profile</a></li>
            <li><form method="post" action="{{ route('my.logout') }}" class="m-0">@csrf<button class="dropdown-item"><i class="bi bi-box-arrow-left"></i> Sign out</button></form></li>
          </ul>
        </div>
      </div>
      <div class="topbar"><div><h1>@yield('heading')</h1>@hasSection('sub')<div class="sub">@yield('sub')</div>@endif</div><div class="ms-auto d-flex gap-2">@yield('actions')</div></div>
      <div class="content">@yield('content')</div>
    </div>
  </div>
  <nav class="tabs">
    @foreach($nav as [$route, $label, $icon, $iconOn])
      <a href="{{ route($route) }}" class="{{ request()->routeIs($route) ? 'on' : '' }}"><i class="bi {{ request()->routeIs($route) ? $iconOn : $icon }}"></i>{{ $label === 'My events' ? 'Events' : ($label === 'Card & QR' ? 'Card' : $label) }}</a>
    @endforeach
  </nav>
@else
  <div class="auth">
    <aside class="auth-art">@include('partials.auth-brand', ['audience' => 'participant'])</aside>
    <main class="auth-side">
      <div class="auth-bar">
        <a href="{{ url('/') }}"><i class="bi bi-arrow-left"></i> Back to website</a>
        <a href="{{ url('/contact-us') }}"><i class="bi bi-life-preserver"></i> Need help?</a>
      </div>
      <div class="auth-form"><div class="auth-box">@yield('content')</div></div>
      <div class="auth-legal">Protected sign-in · We never share your email.</div>
    </main>
  </div>
@endif
  <div class="my-toast" id="my-toast" role="status" aria-live="polite"></div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    window.myToast = function (msg, err) { var t = document.getElementById('my-toast'); t.textContent = msg; t.classList.toggle('err', !!err); t.classList.add('on'); clearTimeout(t._h); t._h = setTimeout(function () { t.classList.remove('on'); }, 2800); };
    window.myPost = function (url, body) {
      return fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(body || {}) })
        .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { if (r.status === 401 || r.status === 419) { location.href = @json(route('my.login')); } return { ok: r.ok, status: r.status, d: d }; }); });
    };
  </script>
  @stack('scripts')
</body>
</html>
