{{-- Self check-in at the venue (poster QR → /checkin/{slug}/{token}). Settings: Admin › Attendee Activities › Self Check-in. --}}
@php
  $tz = \App\Support\CheckIn::tz($event);
  $start = $event->event_start ? \Carbon\Carbon::parse($event->event_start, $tz) : null;
  $extraLabel = ['last_name' => 'Last name', 'email' => 'Email address', 'birth_year' => 'Year of birth'][$s['extra']] ?? null;
  $extraType = ['last_name' => 'text', 'email' => 'email', 'birth_year' => 'number'][$s['extra']] ?? 'text';
  $cfg = ['action' => $action, 'find' => $findAction, 'seconds' => $s['seconds'], 'kiosk' => $kiosk, 'sound' => (bool) $s['sound'], 'location' => (bool) $s['location'], 'extra' => $s['extra']];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex, nofollow">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="theme-color" content="#0a2350">
  <title>Check in · {{ $event->event_short }}</title>
  <link rel="icon" href="{{ asset('assets/frontend/images/logo.png') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    :root { --blue: #0e4ca1; --navy: #0a2350; --magenta: #b22e6b; --green: #1f9d55; --amber: #c27c0e; --red: #c0392b; --ink: #0f1a2e; --muted: #6b7489; --line: #e3e8f2; }
    * { box-sizing: border-box; }
    html, body { margin: 0; min-height: 100%; }
    body { font-family: "Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--ink); -webkit-font-smoothing: antialiased; min-height: 100vh; min-height: 100dvh;
      background: radial-gradient(700px 420px at 100% 0%, rgba(178,46,107,.45), transparent 60%), radial-gradient(600px 420px at 0% 100%, rgba(31,143,179,.35), transparent 60%), linear-gradient(160deg, #071a3d, #0e4ca1);
      display: flex; flex-direction: column; align-items: center; padding: 18px 16px 28px; }
    .ci-top { width: 100%; max-width: 460px; display: flex; align-items: center; gap: 12px; color: #fff; margin-bottom: 18px; }
    .ci-logo { background: #fff; border-radius: 12px; padding: 6px 10px; display: inline-flex; flex: none; } .ci-logo img { height: 30px; display: block; }
    .ci-ev b { display: block; font-size: 1.05rem; font-weight: 800; line-height: 1.2; }
    .ci-ev span { font-size: 12.5px; opacity: .8; }
    .ci-card { width: 100%; max-width: 460px; background: #fff; border-radius: 26px; padding: 26px 22px 22px; box-shadow: 0 30px 60px -30px rgba(0,0,0,.6); }
    .ci-ic { width: 58px; height: 58px; border-radius: 18px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.6rem; color: #fff; margin-bottom: 14px;
      background: linear-gradient(135deg, var(--blue), var(--magenta)); box-shadow: 0 14px 26px -14px rgba(178,46,107,.9); }
    h1 { font-size: 1.6rem; font-weight: 800; margin: 0 0 6px; letter-spacing: -.02em; }
    .ci-sub { color: var(--muted); margin: 0 0 20px; font-size: 15px; line-height: 1.5; }
    label { display: block; font-weight: 700; font-size: 13.5px; margin-bottom: 7px; }
    .ci-num { width: 100%; height: 66px; border: 2px solid var(--line); border-radius: 16px; text-align: center; font-size: 2rem; font-weight: 800; letter-spacing: .25em;
      font-family: Consolas, ui-monospace, monospace; color: var(--ink); background: #f5f7fc; transition: border-color .15s, box-shadow .15s, background .15s; }
    .ci-num::placeholder { color: #c3cad8; letter-spacing: .2em; }
    .ci-in { width: 100%; height: 54px; border: 2px solid var(--line); border-radius: 14px; padding: 0 16px; font-size: 1.05rem; font-family: inherit; color: var(--ink); background: #f5f7fc; }
    .ci-num:focus, .ci-in:focus { outline: none; border-color: var(--blue); background: #fff; box-shadow: 0 0 0 4px rgba(14,76,161,.14); }
    .ci-field { margin-bottom: 16px; }
    .ci-btn { width: 100%; height: 58px; border: 0; border-radius: 16px; font-weight: 800; font-size: 1.1rem; color: #fff; font-family: inherit; display: inline-flex; align-items: center; justify-content: center; gap: 10px;
      background: linear-gradient(120deg, var(--blue), #1d63c9); box-shadow: 0 16px 30px -16px rgba(14,76,161,1); cursor: pointer; transition: transform .12s, filter .12s; }
    .ci-btn:active { transform: scale(.98); } .ci-btn:disabled { opacity: .7; }
    .ci-err { display: none; gap: 10px; align-items: flex-start; background: #fdecee; color: #9b2230; border-radius: 14px; padding: 12px 14px; font-size: 14.5px; font-weight: 600; margin-bottom: 16px; line-height: 1.45; }
    .ci-err.on { display: flex; animation: shake .35s; } .ci-err i { font-size: 18px; }
    .ci-err a { color: inherit; font-weight: 800; }
    @keyframes shake { 0%,100% { transform: none; } 25% { transform: translateX(-6px); } 75% { transform: translateX(6px); } }
    .ci-help { display: flex; justify-content: space-between; gap: 10px; margin-top: 16px; font-size: 14px; flex-wrap: wrap; }
    .ci-link { background: none; border: 0; padding: 0; color: var(--blue); font-weight: 700; font-family: inherit; font-size: 14px; cursor: pointer; }
    .ci-note { display: flex; gap: 8px; align-items: flex-start; font-size: 13px; color: var(--muted); margin-top: 14px; line-height: 1.45; } .ci-note i { color: var(--blue); }
    .ci-find { display: none; margin-top: 16px; padding-top: 16px; border-top: 1px dashed var(--line); } .ci-find.on { display: block; }
    .ci-ok { display: none; background: #e8f6ee; color: #1b6b3d; border-radius: 14px; padding: 12px 14px; font-size: 14px; font-weight: 600; margin-top: 12px; } .ci-ok.on { display: block; }
    .ci-closed { text-align: center; padding: 10px 0; } .ci-closed .big { font-size: 3rem; } .ci-closed p { color: var(--muted); }
    .ci-foot { color: rgba(255,255,255,.6); font-size: 12px; margin-top: 18px; text-align: center; }

    /* result (full screen) */
    .ci-result { position: fixed; inset: 0; z-index: 50; display: none; flex-direction: column; align-items: center; justify-content: center; padding: 24px 20px; text-align: center; color: #fff; overflow: hidden; }
    .ci-result.on { display: flex; animation: pop .35s ease; }
    .ci-result.ok { background: radial-gradient(600px 400px at 50% 0%, rgba(255,255,255,.18), transparent 60%), linear-gradient(160deg, #0b7a43, #1f9d55 55%, #0e4ca1); }
    .ci-result.again { background: radial-gradient(600px 400px at 50% 0%, rgba(255,255,255,.18), transparent 60%), linear-gradient(160deg, #8a5a08, #c27c0e 55%, #0a2350); }
    @keyframes pop { from { opacity: 0; transform: scale(1.03); } to { opacity: 1; transform: none; } }
    .ci-badge { display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.35); border-radius: 999px; padding: 7px 14px; font-weight: 800; font-size: 14px; letter-spacing: .02em; margin-bottom: 22px; }
    .ci-photo { width: 168px; height: 168px; border-radius: 50%; object-fit: cover; border: 6px solid #fff; box-shadow: 0 20px 40px -18px rgba(0,0,0,.6); background: #fff; }
    .ci-initials { width: 168px; height: 168px; border-radius: 50%; border: 6px solid #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 3.4rem; font-weight: 800; background: rgba(255,255,255,.2); }
    .ci-welcome { font-size: clamp(1.6rem, 6vw, 2.4rem); font-weight: 800; line-height: 1.15; margin: 22px 0 8px; letter-spacing: -.02em; max-width: 640px; }
    .ci-name { font-size: 1.2rem; font-weight: 700; opacity: .95; }
    .ci-meta { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin: 14px 0 6px; }
    .ci-meta span { background: rgba(255,255,255,.16); border-radius: 10px; padding: 6px 12px; font-size: 14px; font-weight: 600; }
    .ci-msg { font-size: 15.5px; opacity: .9; max-width: 460px; margin-top: 10px; line-height: 1.5; }
    .ci-ring { position: absolute; top: 18px; right: 18px; width: 46px; height: 46px; }
    .ci-ring circle { fill: none; stroke-width: 4; } .ci-ring .bg { stroke: rgba(255,255,255,.25); } .ci-ring .fg { stroke: #fff; stroke-linecap: round; transform: rotate(-90deg); transform-origin: 50% 50%; }
    .ci-ring text { fill: #fff; font-size: 15px; font-weight: 800; text-anchor: middle; dominant-baseline: central; }
    .ci-close { position: absolute; top: 18px; left: 18px; border: 0; background: rgba(255,255,255,.18); color: #fff; width: 42px; height: 42px; border-radius: 12px; font-size: 18px; cursor: pointer; }
    .confetti { position: absolute; top: -12px; width: 9px; height: 14px; border-radius: 2px; opacity: .95; animation: fall linear forwards; }
    @keyframes fall { to { transform: translateY(110vh) rotate(720deg); } }
    @media (prefers-reduced-motion: reduce) { .confetti { display: none; } .ci-result.on { animation: none; } }

    /* after the welcome: a small "you're in" card (own phone) */
    .ci-done { display: none; text-align: center; } .ci-done.on { display: block; }
    .ci-done .tick { width: 64px; height: 64px; border-radius: 50%; background: #e8f6ee; color: var(--green); display: inline-flex; align-items: center; justify-content: center; font-size: 2rem; margin-bottom: 10px; }
    .ci-mini { display: flex; gap: 12px; align-items: center; text-align: left; border: 1px solid var(--line); border-radius: 16px; padding: 12px; margin: 16px 0 6px; }
    .ci-mini img, .ci-mini .av { width: 54px; height: 54px; border-radius: 50%; object-fit: cover; flex: none; }
    .ci-mini .av { background: #eef3fb; color: var(--blue); display: inline-flex; align-items: center; justify-content: center; font-weight: 800; }
    .ci-mini b { display: block; } .ci-mini span { color: var(--muted); font-size: 13.5px; }
    body.kiosk .ci-card { max-width: 560px; padding: 34px 30px; } body.kiosk .ci-num { height: 84px; font-size: 2.6rem; }
  </style>
</head>
<body class="{{ $kiosk ? 'kiosk' : '' }}">
  <header class="ci-top">
    <span class="ci-logo"><img src="{{ asset('assets/frontend/images/logo.png') }}" alt="BICS"></span>
    <div class="ci-ev"><b>{{ $event->event_name }}</b><span>@if($start){{ $start->format('j F Y') }} · @endif{{ $event->event_venue }}</span></div>
  </header>

  <main class="ci-card">
    @if(!$open)
      <div class="ci-closed">
        <div class="big">🕘</div>
        <h1>Check-in is closed</h1>
        <p>{{ $closedMessage }}</p>
      </div>
    @else
      <div id="ci-form-wrap">
        <span class="ci-ic"><i class="bi bi-qr-code-scan"></i></span>
        <h1>Welcome! Let's check you in</h1>
        <p class="ci-sub">Enter your <b>registration number</b> — it's in your confirmation email and on your ID card.</p>
        <div class="ci-err" id="ci-err" role="alert"><i class="bi bi-exclamation-circle-fill"></i><div id="ci-err-t"></div></div>
        <form id="ci-form" novalidate autocomplete="off">
          <div class="ci-field">
            <label for="ci-number">Registration number</label>
            <input class="ci-num" id="ci-number" name="number" inputmode="numeric" pattern="[0-9]*" maxlength="12" placeholder="000000" required {{ $kiosk ? 'autofocus' : '' }}>
          </div>
          @if($extraLabel)
          <div class="ci-field">
            <label for="ci-extra">{{ $extraLabel }}</label>
            <input class="ci-in" id="ci-extra" name="extra" type="{{ $extraType }}" @if($s['extra'] === 'birth_year') inputmode="numeric" min="1900" max="{{ date('Y') }}" placeholder="e.g. 1980" @elseif($s['extra'] === 'email') autocomplete="email" placeholder="you@example.com" @else autocomplete="family-name" placeholder="As on your registration" @endif required>
          </div>
          @endif
          <button class="ci-btn" id="ci-go" type="submit"><i class="bi bi-box-arrow-in-right"></i> Check in</button>
        </form>
        @if($s['location'])<div class="ci-note"><i class="bi bi-geo-alt-fill"></i><div>Your phone will ask for your location — check-in only works at the venue.</div></div>@endif
        <div class="ci-help">
          @if($s['forgot'])<button type="button" class="ci-link" id="ci-forgot">Forgot your number?</button>@endif
          <span style="color:var(--muted)">Need help? Visit the help desk.</span>
        </div>
        @if($s['forgot'])
        <form class="ci-find" id="ci-find" novalidate>
          <label for="ci-email">Your email address</label>
          <input class="ci-in" id="ci-email" type="email" autocomplete="email" placeholder="The email you registered with" required>
          <button class="ci-btn" type="submit" style="height:50px;font-size:1rem;margin-top:12px;background:#0f1a2e"><i class="bi bi-envelope"></i> Email me my number</button>
          <div class="ci-ok" id="ci-find-ok"></div>
        </form>
        @endif
      </div>

      <div class="ci-done" id="ci-done">
        <span class="tick"><i class="bi bi-check-lg"></i></span>
        <h1>You're checked in</h1>
        <p class="ci-sub" id="ci-done-t" style="margin-bottom:0"></p>
        <div class="ci-mini" id="ci-mini"></div>
        <button type="button" class="ci-link" id="ci-another" style="margin-top:10px">Check in someone else</button>
      </div>
    @endif
  </main>
  <div class="ci-foot">Beacon International Cancer Summit · Self check-in</div>

  <div class="ci-result" id="ci-result" role="dialog" aria-live="assertive">
    <button type="button" class="ci-close" id="ci-close" aria-label="Close"><i class="bi bi-x-lg"></i></button>
    <svg class="ci-ring" viewBox="0 0 46 46" aria-hidden="true"><circle class="bg" cx="23" cy="23" r="20"/><circle class="fg" id="ci-ring" cx="23" cy="23" r="20" stroke-dasharray="125.66" stroke-dashoffset="0"/><text x="23" y="23" id="ci-count"></text></svg>
    <div class="ci-badge" id="ci-badge"></div>
    <div id="ci-pic"></div>
    <div class="ci-welcome" id="ci-welcome"></div>
    <div class="ci-name" id="ci-name"></div>
    <div class="ci-meta" id="ci-meta"></div>
    <div class="ci-msg" id="ci-msg"></div>
  </div>

<script>
(function () {
  'use strict';
  var C = @json($cfg);
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var form = document.getElementById('ci-form');
  if (!form) return;
  function $(id) { return document.getElementById(id); }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  var num = $('ci-number'), btn = $('ci-go');

  num.addEventListener('input', function () { num.value = num.value.replace(/\D/g, ''); hideErr(); });
  function showErr(html) { var e = $('ci-err'); $('ci-err-t').innerHTML = html; e.classList.remove('on'); void e.offsetWidth; e.classList.add('on'); }
  function hideErr() { $('ci-err').classList.remove('on'); }
  function busy(on) { btn.disabled = on; btn.innerHTML = on ? '<span style="display:inline-block;width:20px;height:20px;border:3px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .8s linear infinite"></span> Checking…' : '<i class="bi bi-box-arrow-in-right"></i> Check in'; }
  var st = document.createElement('style'); st.textContent = '@keyframes spin{to{transform:rotate(360deg)}}'; document.head.appendChild(st);

  function beep(ok) {
    if (!C.sound) return;
    try {
      var a = new (window.AudioContext || window.webkitAudioContext)(), t = a.currentTime;
      (ok ? [660, 880, 1320] : [440, 330]).forEach(function (f, i) {
        var o = a.createOscillator(), g = a.createGain(); o.type = 'sine'; o.frequency.value = f; o.connect(g); g.connect(a.destination);
        g.gain.setValueAtTime(0.0001, t + i * .12); g.gain.exponentialRampToValueAtTime(0.25, t + i * .12 + .02); g.gain.exponentialRampToValueAtTime(0.0001, t + i * .12 + .2);
        o.start(t + i * .12); o.stop(t + i * .12 + .22);
      });
    } catch (e) {}
  }
  function confetti() {
    var r = $('ci-result'), cols = ['#ffd27a', '#ff7aa8', '#7fd3f7', '#ffffff', '#b8f2a0'];
    for (var i = 0; i < 70; i++) {
      var c = document.createElement('i'); c.className = 'confetti';
      c.style.left = Math.random() * 100 + 'vw'; c.style.background = cols[i % cols.length];
      c.style.animationDuration = (2.2 + Math.random() * 2.5) + 's'; c.style.animationDelay = (Math.random() * .6) + 's';
      r.appendChild(c); setTimeout(function (el) { el.remove(); }.bind(null, c), 5500);
    }
  }

  function location() {
    return new Promise(function (resolve) {
      if (!C.location) return resolve({});
      if (!navigator.geolocation) return resolve({});
      navigator.geolocation.getCurrentPosition(function (p) { resolve({ lat: p.coords.latitude, lng: p.coords.longitude, acc: Math.round(p.coords.accuracy || 0) }); },
        function () { resolve({}); }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
    });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault(); hideErr();
    var n = num.value.trim();
    if (n.length < 4) { showErr('Please enter your registration number.'); num.focus(); return; }
    var extra = $('ci-extra');
    if (extra && !extra.value.trim()) { showErr('Please fill in your ' + extra.previousElementSibling.textContent.toLowerCase() + '.'); extra.focus(); return; }
    busy(true);
    location().then(function (loc) {
      var body = Object.assign({ number: n, extra: extra ? extra.value.trim() : '', kiosk: C.kiosk ? 1 : 0 }, loc);
      return fetch(C.action, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(body) });
    }).then(function (r) {
      if (r.status === 419) { location_reload(); return null; }
      return r.json().catch(function () { return { message: 'Something went wrong. Please try again.' }; });
    }).then(function (d) {
      busy(false);
      if (!d) return;
      if (d.status === 'checked_in' || d.status === 'already') return show(d);
      beep(false);
      var msg = esc(d.message || 'Please try again.');
      if (d.register) msg += ' <a href="' + esc(d.register) + '">Register now</a>';
      showErr(msg);
      (d.status === 'extra' && $('ci-extra') ? $('ci-extra') : num).focus();
    }).catch(function () { busy(false); showErr('No connection. Please check your internet and try again.'); });
  });
  function location_reload() { busy(false); showErr('This page expired. Reloading…'); setTimeout(function () { window.location.reload(); }, 1200); }

  var timer = null;
  function show(d) {
    var p = d.profile, ok = d.status === 'checked_in', r = $('ci-result');
    r.className = 'ci-result on ' + (ok ? 'ok' : 'again');
    $('ci-badge').innerHTML = ok ? '<i class="bi bi-check-circle-fill"></i> Checked in · ' + esc(d.at) : '<i class="bi bi-info-circle-fill"></i> Already checked in at ' + esc(d.at) + ' · ' + esc(d.day);
    $('ci-pic').innerHTML = p.photo ? '<img class="ci-photo" src="' + esc(p.photo) + '" alt="">' : '<span class="ci-initials">' + esc(p.initials) + '</span>';
    $('ci-welcome').textContent = ok ? p.welcome : 'Welcome back, ' + p.name.split(' ').slice(-2).join(' ') + '!';
    $('ci-name').textContent = p.name;
    $('ci-meta').innerHTML = [p.profession, p.organization, p.country ? '📍 ' + p.country : null].filter(Boolean).map(function (x) { return '<span>' + esc(x) + '</span>'; }).join('');
    $('ci-msg').textContent = ok ? p.message : 'You only need to check in once. Enjoy the summit!';
    beep(ok); if (ok) confetti();
    // countdown, then close
    var left = C.seconds, ring = $('ci-ring'), full = 125.66;
    clearInterval(timer);
    function tick() {
      $('ci-count').textContent = left;
      ring.style.strokeDashoffset = String(full * (1 - left / C.seconds));
      if (left-- <= 0) finish(d);
    }
    tick(); timer = setInterval(tick, 1000);
  }
  function finish(d) {
    clearInterval(timer);
    $('ci-result').className = 'ci-result';
    if (C.kiosk) { form.reset(); num.focus(); return; }   // next person
    var p = d.profile;
    $('ci-form-wrap').style.display = 'none';
    $('ci-done-t').textContent = (d.status === 'checked_in' ? 'Checked in at ' : 'You checked in at ') + d.at + ' · ' + d.day + '. You don\'t need to do it again.';
    $('ci-mini').innerHTML = (p.photo ? '<img src="' + esc(p.photo) + '" alt="">' : '<span class="av">' + esc(p.initials) + '</span>') +
      '<div><b>' + esc(p.name) + '</b><span>' + esc([p.profession, p.country].filter(Boolean).join(' · ')) + '</span></div>';
    $('ci-done').classList.add('on');
  }
  $('ci-close').addEventListener('click', function () { finish(window.__last || {}); });
  // keep the last result for the close button
  var _show = show; show = function (d) { window.__last = d; _show(d); };
  $('ci-another').addEventListener('click', function () { $('ci-done').classList.remove('on'); $('ci-form-wrap').style.display = ''; form.reset(); num.focus(); });

  var fb = $('ci-forgot');
  if (fb) {
    fb.addEventListener('click', function () { $('ci-find').classList.toggle('on'); $('ci-email').focus(); });
    $('ci-find').addEventListener('submit', function (e) {
      e.preventDefault();
      var em = $('ci-email').value.trim(), ok = $('ci-find-ok');
      if (!/^\S+@\S+\.\S+$/.test(em)) { ok.textContent = 'Please enter a valid email address.'; ok.classList.add('on'); return; }
      fetch(C.find, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ email: em }) })
        .then(function (r) { return r.json(); }).then(function (d) { ok.textContent = d.message || 'Please try again.'; ok.classList.add('on'); })
        .catch(function () { ok.textContent = 'No connection. Please try again.'; ok.classList.add('on'); });
    });
  }
})();
</script>
</body>
</html>
