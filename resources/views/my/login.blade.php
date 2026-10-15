@extends('my.layout')
@section('title', 'Sign in')
@php
  $usePassword = request('with') === 'password' || $errors->has('password');
  $reg = \App\Support\EventSettings::registration();
  $regOpen = $reg['state'] === 'open';
  $current = \App\Support\EventSettings::get('event_short');
  // "Open Gmail" style shortcut for the address the code went to
  $mailApp = null;
  if ($sentTo) {
      $domain = strtolower(substr(strrchr($sentTo, '@'), 1));
      $mailApp = match (true) {
          in_array($domain, ['gmail.com', 'googlemail.com']) => ['Gmail', 'https://mail.google.com/mail/u/0/#search/BICS', 'bi-google'],
          in_array($domain, ['outlook.com', 'hotmail.com', 'live.com', 'msn.com']) || str_starts_with($domain, 'hotmail.') || str_starts_with($domain, 'outlook.') => ['Outlook', 'https://outlook.live.com/mail/0/', 'bi-microsoft'],
          str_starts_with($domain, 'yahoo.') || $domain === 'ymail.com' => ['Yahoo Mail', 'https://mail.yahoo.com/', 'bi-envelope-paper'],
          in_array($domain, ['icloud.com', 'me.com', 'mac.com']) => ['iCloud Mail', 'https://www.icloud.com/mail', 'bi-apple'],
          default => null,
      };
  }
@endphp
@push('styles')
<style>
  .li-ic { width: 54px; height: 54px; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #fff; margin-bottom: 18px;
    background: linear-gradient(135deg, var(--blue), var(--magenta)); box-shadow: 0 14px 28px -14px rgba(178,46,107,.8); }
  .li-ic.is-mail { background: linear-gradient(135deg, #1f8fb3, var(--blue)); }
  .auth-box h1 { font-size: 1.75rem; font-weight: 800; letter-spacing: -.02em; margin-bottom: 6px; }
  .li-sub { color: var(--muted); margin-bottom: 22px; font-size: 15px; }
  .li-sub b { color: var(--ink); }
  .li-alert { display: flex; gap: 10px; align-items: flex-start; border-radius: 13px; padding: 11px 13px; font-size: 14px; margin-bottom: 18px; }
  .li-alert.err { background: #fdecee; color: #9b2230; } .li-alert.info { background: #eaf2fd; color: #164a8f; }
  .li-alert i { font-size: 16px; margin-top: 1px; }

  .seg { position: relative; display: grid; grid-template-columns: 1fr 1fr; background: #edf1f8; border-radius: 14px; padding: 4px; margin-bottom: 20px; }
  .seg button { position: relative; z-index: 1; border: 0; background: none; border-radius: 10px; padding: 10px 8px; font-weight: 700; font-size: 14px; color: var(--muted); display: inline-flex; align-items: center; justify-content: center; gap: 7px; transition: color .15s; }
  .seg button.on { color: var(--ink); }
  .seg .seg-pill { position: absolute; z-index: 0; top: 4px; bottom: 4px; left: 4px; width: calc(50% - 4px); background: #fff; border-radius: 10px; box-shadow: 0 2px 10px -4px rgba(16,30,60,.3); transition: transform .22s cubic-bezier(.3,.7,.3,1); }
  .seg[data-on="password"] .seg-pill { transform: translateX(100%); }

  .li-label { display: block; font-weight: 700; font-size: 13.5px; color: var(--ink); margin-bottom: 7px; }
  .li-field { position: relative; margin-bottom: 16px; }
  .li-field > i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #98a1b4; font-size: 17px; pointer-events: none; }
  .li-input { width: 100%; height: 52px; border: 1.5px solid var(--line); border-radius: 13px; padding: 0 48px 0 45px; font-size: 15.5px; background: #fff; color: var(--ink); transition: border-color .15s, box-shadow .15s; }
  .li-input:focus { outline: none; border-color: var(--blue); box-shadow: 0 0 0 4px rgba(14,76,161,.12); }
  .li-input.is-invalid { border-color: #d64545; }
  .li-err { color: #c0392b; font-size: 13px; font-weight: 600; margin: -8px 0 14px; }
  .li-eye { position: absolute; right: 7px; top: 50%; transform: translateY(-50%); border: 0; background: none; width: 38px; height: 38px; border-radius: 10px; color: #8a93a6; }
  .li-eye:hover { background: var(--soft); color: var(--ink); }
  .li-caps { display: none; font-size: 12.5px; color: #b26a00; font-weight: 600; margin: -8px 0 12px; } .li-caps.on { display: block; }

  .li-btn { width: 100%; height: 52px; border: 0; border-radius: 13px; font-weight: 700; font-size: 15.5px; color: #fff; display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    background: linear-gradient(120deg, var(--blue), var(--blue-2)); box-shadow: 0 14px 26px -14px rgba(14,76,161,.95); transition: transform .12s, filter .12s; }
  .li-btn:hover { filter: brightness(1.07); transform: translateY(-1px); } .li-btn:disabled { opacity: .7; transform: none; }
  .li-btn-ghost { width: 100%; height: 48px; border-radius: 13px; border: 1.5px solid var(--line); background: #fff; color: var(--ink); font-weight: 700; display: inline-flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; }
  .li-btn-ghost:hover { border-color: #c9d4e6; color: var(--blue); }

  .li-note { display: flex; gap: 10px; align-items: flex-start; font-size: 13px; color: var(--muted); margin-top: 14px; line-height: 1.5; }
  .li-note i { color: var(--green); font-size: 16px; }

  .otp { display: flex; gap: 9px; justify-content: space-between; margin-bottom: 16px; }
  .otp input { width: 100%; max-width: 56px; height: 62px; border: 1.5px solid var(--line); border-radius: 14px; text-align: center; font-size: 1.6rem; font-weight: 800; font-family: Consolas, ui-monospace, monospace; color: var(--ink); background: var(--soft); transition: border-color .15s, box-shadow .15s, background .15s; }
  .otp input:focus { outline: none; border-color: var(--blue); background: #fff; box-shadow: 0 0 0 4px rgba(14,76,161,.12); }
  .otp.is-invalid input { border-color: #d64545; }
  .li-links { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-top: 16px; font-size: 14px; }
  .li-links a, .li-links button { color: var(--blue); font-weight: 600; text-decoration: none; background: none; border: 0; padding: 0; }
  .li-links button:disabled { color: var(--muted); }

  .li-or { display: flex; align-items: center; gap: 12px; color: #9aa2b4; font-size: 12.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; margin: 26px 0 16px; }
  .li-or::before, .li-or::after { content: ""; flex: 1; border-top: 1px solid var(--line); }
  .li-new { display: flex; gap: 13px; align-items: center; padding: 14px; border-radius: 16px; border: 1px solid var(--line); background: linear-gradient(135deg, #fff, #fbf3f7); text-decoration: none; color: inherit; transition: border-color .15s, transform .15s; }
  .li-new:hover { border-color: #e6c3d4; transform: translateY(-1px); }
  .li-new > i:first-child { flex: none; width: 42px; height: 42px; border-radius: 12px; background: #f6e7ef; color: var(--magenta); display: inline-flex; align-items: center; justify-content: center; font-size: 1.2rem; }
  .li-new b { display: block; color: var(--ink); font-size: 14.5px; } .li-new span { font-size: 13px; color: var(--muted); }
  .li-new .bi-arrow-right { margin-left: auto; color: var(--muted); }
</style>
@endpush
@section('content')
  @if(session('notice'))<div class="li-alert info"><i class="bi bi-info-circle-fill"></i><div>{{ session('notice') }}</div></div>@endif
  @if($errors->has('form'))<div class="li-alert err" role="alert"><i class="bi bi-exclamation-circle-fill"></i><div>{{ $errors->first('form') }}</div></div>@endif

  @if(!$portal)
    <span class="li-ic"><i class="bi bi-door-closed"></i></span>
    <h1>Sign-in is paused</h1>
    <p class="li-sub">The participant portal is switched off right now. Please contact the organizers if you need to change your profile.</p>
    <a class="li-btn-ghost" href="{{ url('/contact-us') }}"><i class="bi bi-envelope"></i> Contact the organizers</a>

  @elseif($sentTo)
    <span class="li-ic is-mail"><i class="bi bi-envelope-paper-heart"></i></span>
    <h1>Check your inbox</h1>
    <p class="li-sub">If <b>{{ $sentTo }}</b> belongs to a BICS participant, we've emailed a 6-digit code and a sign-in link. They work for {{ \App\Support\ParticipantAccess::LINK_MINUTES }} minutes.</p>
    <form method="post" action="{{ route('my.login.code') }}" id="li-code-form" novalidate>
      @csrf
      <input type="hidden" name="email" value="{{ $sentTo }}">
      <input type="hidden" name="code" id="li-code">
      @include('partials.form-guard', ['form' => 'my-login', 'captcha' => false])
      <label class="li-label" for="li-otp-0">Enter the code</label>
      <div class="otp @error('code') is-invalid @enderror" id="li-otp">
        @for($i = 0; $i < 6; $i++)<input type="text" inputmode="numeric" maxlength="1" id="li-otp-{{ $i }}" aria-label="Digit {{ $i + 1 }}" @if($i === 0) autocomplete="one-time-code" autofocus @else autocomplete="off" @endif>@endfor
      </div>
      @error('code')<div class="li-err" style="margin-top:-6px">{{ $message }}</div>@enderror
      <button class="li-btn" id="li-code-btn"><i class="bi bi-box-arrow-in-right"></i> Sign in</button>
    </form>
    @if($mailApp)
      <a class="li-btn-ghost mt-2" href="{{ $mailApp[1] }}" target="_blank" rel="noopener"><i class="bi {{ $mailApp[2] }}"></i> Open {{ $mailApp[0] }}</a>
    @endif
    <form method="post" action="{{ route('my.send') }}" id="li-resend-form" class="d-none">
      @csrf
      <input type="hidden" name="email" value="{{ $sentTo }}">
      @include('partials.form-guard', ['form' => 'my-login', 'captcha' => false])
    </form>
    <div class="li-links">
      <button type="button" id="li-resend" disabled>Resend code</button>
      <a href="{{ route('my.login') }}">Use another email</a>
    </div>
    <div class="li-note"><i class="bi bi-info-circle" style="color:var(--blue)"></i><div>No email after a minute? Check spam or promotions. Use the address you registered with at any BICS summit.</div></div>

  @else
    <span class="li-ic"><i class="bi bi-person-badge"></i></span>
    <h1>Sign in to BICS</h1>
    <p class="li-sub">Use the email you registered with at <b>any</b> BICS summit.</p>

    <div class="seg" role="tablist" aria-label="Sign-in method" data-on="{{ $usePassword ? 'password' : 'code' }}">
      <span class="seg-pill" aria-hidden="true"></span>
      <button type="button" role="tab" id="tab-code" aria-controls="pane-code" aria-selected="{{ $usePassword ? 'false' : 'true' }}" class="{{ $usePassword ? '' : 'on' }}" data-tab="code"><i class="bi bi-envelope"></i> Email code</button>
      <button type="button" role="tab" id="tab-password" aria-controls="pane-password" aria-selected="{{ $usePassword ? 'true' : 'false' }}" class="{{ $usePassword ? 'on' : '' }}" data-tab="password"><i class="bi bi-key"></i> Password</button>
    </div>

    <form method="post" action="{{ route('my.send') }}" id="pane-code" role="tabpanel" aria-labelledby="tab-code" data-pane="code" class="{{ $usePassword ? 'd-none' : '' }}" novalidate>
      @csrf
      <label class="li-label" for="email">Email address</label>
      <div class="li-field"><i class="bi bi-at"></i>
        <input type="email" class="li-input @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" autocomplete="email" required placeholder="you@hospital.org" {{ $usePassword ? '' : 'autofocus' }}></div>
      @error('email')<div class="li-err">{{ $message }}</div>@enderror
      @include('partials.form-guard', ['form' => 'my-login'])
      <button class="li-btn"><i class="bi bi-send"></i> Email me a sign-in code</button>
      <div class="li-note"><i class="bi bi-shield-check"></i><div>No password needed — we send a one-time code and link that work for {{ \App\Support\ParticipantAccess::LINK_MINUTES }} minutes.</div></div>
    </form>

    <form method="post" action="{{ route('my.login.password') }}" id="pane-password" role="tabpanel" aria-labelledby="tab-password" data-pane="password" class="{{ $usePassword ? '' : 'd-none' }}" novalidate>
      @csrf
      <label class="li-label" for="pemail">Email address</label>
      <div class="li-field"><i class="bi bi-at"></i>
        <input type="email" class="li-input" id="pemail" name="email" value="{{ old('email') }}" autocomplete="username" required placeholder="you@hospital.org"></div>
      <label class="li-label" for="password">Password</label>
      <div class="li-field"><i class="bi bi-lock"></i>
        <input type="password" class="li-input @error('password') is-invalid @enderror" id="password" name="password" autocomplete="current-password" required {{ $usePassword ? 'autofocus' : '' }}>
        <button type="button" class="li-eye" data-show aria-label="Show password"><i class="bi bi-eye"></i></button></div>
      <div class="li-caps" id="li-caps"><i class="bi bi-capslock-fill"></i> Caps Lock is on</div>
      @error('password')<div class="li-err">{{ $message }}</div>@enderror
      @include('partials.form-guard', ['form' => 'my-login'])
      <button class="li-btn"><i class="bi bi-box-arrow-in-right"></i> Sign in</button>
      <div class="li-note"><i class="bi bi-info-circle" style="color:var(--blue)"></i><div>Set a password on the Security page after signing in with a code. Forgot it? Use <a href="#" data-goto="code">Email code</a>.</div></div>
    </form>

    <div class="li-or">New to BICS?</div>
    @if($regOpen)
      <a class="li-new" href="{{ url('/registration') }}"><i class="bi bi-person-plus"></i><div><b>Register for {{ $current }}</b><span>Your participant account is created with your registration.</span></div><i class="bi bi-arrow-right"></i></a>
    @else
      <a class="li-new" href="{{ url('/') }}"><i class="bi bi-globe2"></i><div><b>Explore the summit</b><span>Your account is created when you register for a BICS summit.</span></div><i class="bi bi-arrow-right"></i></a>
    @endif
  @endif
@endsection
@push('scripts')
<script>
(function () {
  /* sign-in method tabs */
  var seg = document.querySelector('.seg');
  function pick(tab) {
    if (!seg) return;
    seg.dataset.on = tab;
    seg.querySelectorAll('[data-tab]').forEach(function (x) { var on = x.dataset.tab === tab; x.classList.toggle('on', on); x.setAttribute('aria-selected', on ? 'true' : 'false'); });
    document.querySelectorAll('[data-pane]').forEach(function (f) { f.classList.toggle('d-none', f.dataset.pane !== tab); });
    // carry the typed email over
    var from = document.getElementById(tab === 'code' ? 'pemail' : 'email'), to = document.getElementById(tab === 'code' ? 'email' : 'pemail');
    if (from && to && !to.value) to.value = from.value;
    var first = document.querySelector('[data-pane="' + tab + '"] input[type=email]');
    if (first) (first.value && tab === 'password' ? document.getElementById('password') : first).focus();
  }
  document.querySelectorAll('[data-tab]').forEach(function (b) { b.addEventListener('click', function () { pick(b.dataset.tab); }); });
  document.querySelectorAll('[data-goto]').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); pick(a.dataset.goto); }); });
  if (seg) seg.addEventListener('keydown', function (e) { if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') { e.preventDefault(); pick(seg.dataset.on === 'code' ? 'password' : 'code'); seg.querySelector('.on').focus(); } });

  /* password: show / hide, caps lock */
  var show = document.querySelector('[data-show]'), pw = document.getElementById('password');
  if (show) show.addEventListener('click', function () { var hidden = pw.type === 'password'; pw.type = hidden ? 'text' : 'password'; show.innerHTML = '<i class="bi ' + (hidden ? 'bi-eye-slash' : 'bi-eye') + '"></i>'; show.setAttribute('aria-label', hidden ? 'Hide password' : 'Show password'); pw.focus(); });
  if (pw) ['keydown', 'keyup'].forEach(function (t) { pw.addEventListener(t, function (e) { if (e.getModifierState) document.getElementById('li-caps').classList.toggle('on', e.getModifierState('CapsLock')); }); });

  /* busy buttons (no double submits) */
  document.querySelectorAll('.auth-box form').forEach(function (f) { f.addEventListener('submit', function (e) { var b = f.querySelector('.li-btn'); if (b) setTimeout(function () { if (e.defaultPrevented) return; b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Please wait…'; }, 0); }); });

  /* the 6-digit code: type, paste, backspace; sends itself when complete */
  var boxes = Array.prototype.slice.call(document.querySelectorAll('#li-otp input'));
  if (boxes.length) {
    var form = document.getElementById('li-code-form'), hidden = document.getElementById('li-code');
    var value = function () { return boxes.map(function (b) { return b.value; }).join(''); };
    var done = function () { hidden.value = value(); if (hidden.value.length === 6) { form.requestSubmit ? form.requestSubmit() : form.submit(); } };
    var fill = function (digits, from) { digits.split('').slice(0, 6 - from).forEach(function (d, k) { boxes[from + k].value = d; }); boxes[Math.min(5, from + digits.length - 1)].focus(); };
    boxes.forEach(function (b, i) {
      b.addEventListener('input', function () {
        var d = b.value.replace(/\D/g, '');
        if (d.length > 1) fill(d, i); else { b.value = d; if (d && i < 5) boxes[i + 1].focus(); }
        document.getElementById('li-otp').classList.remove('is-invalid');
        done();
      });
      b.addEventListener('keydown', function (e) {
        if (e.key === 'Backspace' && !b.value && i > 0) { boxes[i - 1].value = ''; boxes[i - 1].focus(); e.preventDefault(); }
        if (e.key === 'ArrowLeft' && i > 0) boxes[i - 1].focus();
        if (e.key === 'ArrowRight' && i < 5) boxes[i + 1].focus();
      });
      b.addEventListener('paste', function (e) {
        var t = ((e.clipboardData || window.clipboardData).getData('text') || '').replace(/\D/g, '').slice(0, 6);
        if (!t) return; e.preventDefault(); fill(t, 0); done();
      });
    });
    form.addEventListener('submit', function (e) { hidden.value = value(); if (hidden.value.length !== 6) { e.preventDefault(); document.getElementById('li-otp').classList.add('is-invalid'); boxes[value().length < 6 ? value().length : 0].focus(); } });

    // resend after 60 seconds
    var rb = document.getElementById('li-resend'), left = 60;
    (function tick() { rb.textContent = left > 0 ? 'Resend code (' + left + 's)' : 'Resend code'; rb.disabled = left > 0; if (left-- > 0) setTimeout(tick, 1000); })();
    rb.addEventListener('click', function () { rb.disabled = true; rb.textContent = 'Sending…'; document.getElementById('li-resend-form').submit(); });
  }
})();
</script>
@endpush
