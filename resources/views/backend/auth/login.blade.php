@php use App\Support\EventSettings as Ev; @endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex">
  <title>Admin sign-in | BICS</title>
  <script>
    (function () { // same light / dark choice as the admin panel, applied before drawing
      var t = null; try { t = localStorage.getItem('bics-admin-theme'); } catch (e) {}
      if (t !== 'light' && t !== 'dark') t = window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
      document.documentElement.setAttribute('data-theme', t);
    })();
  </script>
  <link href="{{ asset('assets/backend') }}/img/favicon.png" rel="icon">
  <link href="{{ asset('assets/backend') }}/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="{{ asset('assets/backend') }}/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="{{ asset('assets/frontend/css/auth-brand.css') }}?v={{ @filemtime(base_path('assets/frontend/css/auth-brand.css')) }}" rel="stylesheet">
  <style>
    :root { --brand: #0e4ca1; --accent: #b22e6b; --ink: #172033; --muted: #6b7385; --line: #e3e8f0; }
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; font-family: 'Inter', 'Segoe UI', system-ui, sans-serif; background: #f4f6fb; color: var(--ink); -webkit-font-smoothing: antialiased; }
    .lg-wrap { min-height: 100vh; display: grid; grid-template-columns: minmax(0, 1.08fr) minmax(0, 1fr); }
    .lg-art { position: sticky; top: 0; height: 100vh; }
    .lg-side { display: flex; flex-direction: column; min-height: 100vh; padding: 22px 32px; }
    .lg-bar { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding-right: 52px; }
    .lg-bar a { color: var(--muted); text-decoration: none; font-weight: 600; font-size: 14px; display: inline-flex; align-items: center; gap: 6px; }
    .lg-bar a:hover { color: var(--brand); }
    .lg-center { flex: 1; display: flex; align-items: center; justify-content: center; padding: 28px 0; }
    .lg-card { width: 100%; max-width: 410px; }
    .lg-ic { width: 54px; height: 54px; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #fff; margin-bottom: 18px;
      background: linear-gradient(135deg, #0a2b63, var(--brand)); box-shadow: 0 14px 28px -14px rgba(14,76,161,.9); }
    .lg-event { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid var(--line); border-radius: 13px; background: var(--panel, #fff); font-size: 13px; color: var(--muted); margin-bottom: 22px; }
    .lg-event i { color: var(--accent); font-size: 16px; }
    .lg-event b { color: var(--ink); }
    .lg-caps { display: none; font-size: 12.5px; color: #b26a00; font-weight: 600; margin: -10px 0 14px; } .lg-caps.on { display: block; }
    .lg-sec { display: flex; gap: 10px; align-items: flex-start; margin-top: 22px; padding: 12px 14px; border-radius: 13px; background: var(--soft2, #eef2f8); color: var(--muted); font-size: 13px; line-height: 1.5; }
    .lg-sec i { color: var(--brand); font-size: 16px; }
    .lg-legal { text-align: center; font-size: 12.5px; color: var(--muted); opacity: .85; }
    .lg-card h2 { font-size: 26px; font-weight: 800; margin: 0 0 6px; letter-spacing: -.01em; }
    .lg-card .lg-sub { color: var(--muted); margin-bottom: 26px; }
    .lg-label { font-weight: 600; font-size: 13.5px; margin-bottom: 6px; display: block; }
    .lg-field { position: relative; margin-bottom: 18px; }
    .lg-field > i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #9aa3b5; font-size: 17px; pointer-events: none; }
    .lg-input { width: 100%; height: 50px; border: 1.5px solid var(--line); border-radius: 12px; padding: 0 46px 0 44px; font-size: 15px; background: #fff; color: var(--ink); transition: border-color .15s, box-shadow .15s; }
    .lg-input:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 4px rgba(14, 76, 161, .12); }
    .lg-eye { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); border: 0; background: transparent; color: #8a93a6; width: 36px; height: 36px; border-radius: 8px; }
    .lg-eye:hover { background: #f1f4f9; color: var(--ink); }
    .lg-row { display: flex; align-items: center; justify-content: space-between; margin: 4px 0 22px; font-size: 14px; }
    .lg-row .form-check-input:checked { background-color: var(--brand); border-color: var(--brand); }
    .lg-btn { width: 100%; height: 50px; border: 0; border-radius: 12px; font-weight: 700; font-size: 15.5px; color: #fff; background: linear-gradient(120deg, var(--brand), #1c63c8);
      box-shadow: 0 12px 24px -12px rgba(14, 76, 161, .9); transition: transform .12s, filter .12s; }
    .lg-btn:hover { filter: brightness(1.07); transform: translateY(-1px); }
    .lg-btn:disabled { opacity: .75; transform: none; }
    .lg-alert { border-radius: 12px; padding: 12px 14px; font-size: 14px; display: flex; gap: 10px; align-items: flex-start; margin-bottom: 18px; }
    .lg-alert.err { background: #fdecee; color: #9b2230; }
    .lg-alert.ok { background: #e8f6ec; color: #1f6436; }
    .lg-theme { position: fixed; top: 16px; right: 16px; width: 40px; height: 40px; border-radius: 12px; border: 1px solid var(--line); background: #fff; color: var(--ink); display: inline-flex; align-items: center; justify-content: center; font-size: 17px; z-index: 5; }
    .lg-theme .bi-sun-fill { display: none; }
    /* Dark theme */
    html[data-theme="dark"] { --brand: #5b9bff; --ink: #e8edf6; --muted: #8b96ab; --line: #26324d; color-scheme: dark; }
    html[data-theme="dark"] body { background: #0b1222; }
    html[data-theme="dark"] .lg-input { background: #0f1829; color: var(--ink); }
    html[data-theme="dark"] .lg-input::placeholder { color: #5f6b82; }
    html[data-theme="dark"] .lg-eye:hover { background: #1c2741; color: var(--ink); }
    html[data-theme="dark"] .lg-btn { color: #06132b; background: linear-gradient(120deg, #5b9bff, #82b3ff); }
    html[data-theme="dark"] .lg-alert.err { background: rgba(255, 107, 122, .14); color: #ffb0b9; }
    html[data-theme="dark"] .lg-alert.ok { background: rgba(76, 201, 122, .14); color: #8fe3ad; }
    html[data-theme="dark"] .form-check-input { background-color: #0f1829; border-color: #3a4867; }
    html[data-theme="dark"] .lg-theme { background: #121b2e; }
    html[data-theme="dark"] .lg-theme .bi-sun-fill { display: inline; color: #f2b84b; }
    html[data-theme="dark"] .lg-theme .bi-moon-stars-fill { display: none; }
    html[data-theme="dark"] .lg-event { --panel: #0f1829; }
    html[data-theme="dark"] .lg-sec { --soft2: #111b2f; }
    html[data-theme="dark"] .lg-caps { color: #f2b84b; }
    @media (max-width: 991.98px) {
      .lg-wrap { grid-template-columns: 1fr; }
      .lg-art { position: static; height: auto; }
      .lg-side { min-height: 0; padding: 0 16px 24px; }
      .lg-bar { order: 3; justify-content: center; gap: 18px; padding: 0; margin-top: 18px; }
      .lg-center { padding: 0; margin-top: -10px; }
      .lg-card { background: #fff; border: 1px solid var(--line); border-radius: 22px; padding: 24px 20px; box-shadow: 0 18px 40px -26px rgba(16,30,60,.45); }
      html[data-theme="dark"] .lg-card { background: #0d1526; }
      .lg-legal { order: 4; margin-top: 12px; }
      .ab-top { padding-right: 50px; }
      .lg-theme { top: 12px; right: 12px; background: rgba(255,255,255,.14); border-color: rgba(255,255,255,.25); color: #fff; }
    }
  </style>
</head>
<body>
  <button type="button" class="lg-theme" id="lgTheme" aria-label="Switch light / dark mode" title="Switch light / dark mode"><i class="bi bi-moon-stars-fill"></i><i class="bi bi-sun-fill"></i></button>
  <div class="lg-wrap">
    <aside class="lg-art">@include('partials.auth-brand', ['audience' => 'admin'])</aside>

    <main class="lg-side">
      <div class="lg-bar">
        <a href="{{ url('/') }}"><i class="bi bi-arrow-left"></i> Back to website</a>
        <a href="{{ route('my.login') }}"><i class="bi bi-person-circle"></i> Participant sign-in</a>
      </div>
      <div class="lg-center">
      <div class="lg-card">
        <span class="lg-ic"><i class="bi bi-shield-lock"></i></span>
        <h2>Admin sign-in</h2>
        <p class="lg-sub">For BICS organizers and staff — every summit, one console.</p>
        @if($cur = \App\Models\Event::current())
          <div class="lg-event"><i class="bi bi-broadcast"></i><div>Current event: <b>{{ $cur->event_short }}</b>@if($cur->event_start) · {{ \Carbon\Carbon::parse($cur->event_start)->format('j M Y') }}@endif</div></div>
        @endif

        @if(Session::has('error'))
          <div class="lg-alert err" role="alert"><i class="bi bi-exclamation-circle-fill"></i><div>{{ Session::get('error') === 'Email Or Password is incorrect!' ? 'The email or password is not correct, or the account is inactive.' : Session::get('error') }}</div></div>
        @endif
        @if(Session::has('success'))
          <div class="lg-alert ok" role="status"><i class="bi bi-check-circle-fill"></i><div>{{ Session::get('success') === 'Successfully Logout !' ? 'You have been signed out.' : Session::get('success') }}</div></div>
        @endif
        @if(request('disabled'))
          <div class="lg-alert err" role="alert"><i class="bi bi-shield-lock"></i><div>Your account is inactive. Please contact an administrator.</div></div>
        @endif
        @if($errors->any() && !Session::has('error'))
          <div class="lg-alert err" role="alert"><i class="bi bi-exclamation-circle-fill"></i><div>{{ $errors->first() }}</div></div>
        @endif

        <form method="POST" action="{{ route('login.custom') }}" id="lgForm" novalidate>
          @csrf
          @include('partials.form-guard', ['form' => 'admin-login', 'captcha' => false])
          <label class="lg-label" for="email">Email</label>
          <div class="lg-field">
            <i class="bi bi-envelope"></i>
            <input type="email" name="email" id="email" class="lg-input" value="{{ old('email') }}" placeholder="you@beaconpharma.com.bd" autocomplete="username" required autofocus>
          </div>
          <label class="lg-label" for="password">Password</label>
          <div class="lg-field">
            <i class="bi bi-lock"></i>
            <input type="password" name="password" id="password" class="lg-input" placeholder="Your password" autocomplete="current-password" required>
            <button type="button" class="lg-eye" id="lgEye" aria-label="Show password"><i class="bi bi-eye"></i></button>
          </div>
          <div class="lg-caps" id="lgCaps"><i class="bi bi-capslock-fill"></i> Caps Lock is on</div>
          <div class="lg-row">
            <div class="form-check m-0">
              <input class="form-check-input" type="checkbox" name="remember" value="true" id="rememberMe">
              <label class="form-check-label" for="rememberMe">Remember me</label>
            </div>
          </div>
          <button class="lg-btn" type="submit" id="lgBtn">Sign in <i class="bi bi-arrow-right"></i></button>
        </form>
        <div class="lg-sec"><i class="bi bi-shield-check"></i><div>Forgot your password? Ask a Super Admin to reset it. After 5 wrong tries, sign-in pauses for 15 minutes.</div></div>
      </div>
      </div>
      <div class="lg-legal">© {{ date('Y') }} BICS · Organized by Beacon Pharma Ltd.</div>
    </main>
  </div>

  <script>
    document.getElementById('lgTheme').addEventListener('click', function () {
      var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', next);
      try { localStorage.setItem('bics-admin-theme', next); } catch (e) {}
    });
    (function () {
      var eye = document.getElementById('lgEye'), pw = document.getElementById('password');
      eye.addEventListener('click', function () {
        var show = pw.type === 'password';
        pw.type = show ? 'text' : 'password';
        eye.innerHTML = show ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
        eye.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        pw.focus();
      });
      ['keydown', 'keyup'].forEach(function (t) { pw.addEventListener(t, function (e) { if (e.getModifierState) document.getElementById('lgCaps').classList.toggle('on', e.getModifierState('CapsLock')); }); });
      document.getElementById('lgForm').addEventListener('submit', function (e) {
        var email = document.getElementById('email');
        if (!email.value.trim() || !pw.value) {
          e.preventDefault();
          (email.value.trim() ? pw : email).focus();
          return;
        }
        var btn = document.getElementById('lgBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Signing in…';
      });
    })();
  </script>
</body>
</html>
