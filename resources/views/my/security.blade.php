@extends('my.layout')
@section('title', 'Security')
@section('heading', 'Security')
@section('sub', 'How you sign in, your password and where you are signed in')
@php
  $methods = ['link' => ['bi-link-45deg', 'Email link'], 'code' => ['bi-123', 'Email code'], 'password' => ['bi-key', 'Password']];
  $maskIp = fn ($ip) => $ip ? preg_replace('/(\d+)\.(\d+)$/', '•••.•••', preg_replace('/:[0-9a-f]*:[0-9a-f]*$/i', ':••••', $ip)) : '—';
@endphp
@push('styles')
<style>
  .sum { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
  .sum div { background: var(--soft); border-radius: 14px; padding: 14px; }
  .sum span { font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; display: block; }
  .sum b { color: var(--ink); font-size: 15px; } .sum small { color: var(--muted); display: block; }
  .meter { height: 6px; border-radius: 6px; background: var(--line); overflow: hidden; margin-top: 6px; }
  .meter i { display: block; height: 100%; width: 0; transition: .25s; border-radius: 6px; }
  .rules { list-style: none; padding: 0; margin: 8px 0 0; font-size: 13px; display: grid; grid-template-columns: 1fr 1fr; gap: 2px 12px; }
  .rules li { color: var(--muted); } .rules li.ok { color: #1d6b39; } .rules li i { margin-right: 4px; }
  .pw { position: relative; } .pw .eye { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); border: 0; background: none; color: var(--muted); }
  .dev { display: flex; gap: 12px; align-items: center; padding: 12px 0; border-top: 1px solid var(--line); }
  .dev:first-child { border-top: 0; padding-top: 0; }
  .dev .ic { width: 40px; height: 40px; border-radius: 12px; background: var(--soft); color: var(--ink); display: inline-flex; align-items: center; justify-content: center; font-size: 18px; flex: none; }
  @media (max-width: 767px) { .sum { grid-template-columns: 1fr 1fr; } .rules { grid-template-columns: 1fr; } }
</style>
@endpush
@section('content')
<div class="panel">
  <div class="sum">
    <div><span>Sign-in</span><b>{{ $hasPassword ? 'Password or email code' : 'Email code' }}</b><small>{{ $hasPassword ? 'two ways to sign in' : 'no password set' }}</small></div>
    <div><span>Last sign-in</span><b>{{ $p->last_login_at ? $p->last_login_at->format('j M Y, g:i a') : '—' }}</b><small>{{ $p->last_login_at?->diffForHumans() }}</small></div>
    <div><span>Password changed</span><b>{{ $p->password_changed_at ? $p->password_changed_at->format('j M Y') : 'Never' }}</b><small>{{ $p->password_changed_at?->diffForHumans() ?? ($hasPassword ? '' : 'not set') }}</small></div>
    <div><span>Sign-in email</span><b class="text-break">{{ $p->email ?: '—' }}</b><small>ask the organizers to change it</small></div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="panel h-100">
      <div class="panel-h"><span class="ico"><i class="bi bi-key"></i></span><h2>{{ $hasPassword ? 'Change password' : 'Set a password' }}</h2></div>
      <p class="small muted">{{ $hasPassword ? 'After a change, your other devices are signed out.' : 'Optional. With a password you can sign in without waiting for an email. Emailed codes keep working too.' }}</p>
      <form id="pw-form" novalidate>
        @if($hasPassword)
        <div class="mb-3"><label class="form-label small fw-bold" for="current_password">Current password</label>
          <div class="pw"><input type="password" class="form-control" id="current_password" name="current_password" autocomplete="current-password"><button type="button" class="eye" tabindex="-1"><i class="bi bi-eye"></i></button></div></div>
        @endif
        <input type="email" class="d-none" autocomplete="username" value="{{ $p->email }}" readonly>
        <div class="mb-2"><label class="form-label small fw-bold" for="password">New password</label>
          <div class="pw"><input type="password" class="form-control" id="password" name="password" autocomplete="new-password"><button type="button" class="eye" tabindex="-1"><i class="bi bi-eye"></i></button></div>
          <div class="meter"><i id="pw-meter"></i></div>
          <ul class="rules" id="pw-rules"><li data-r="len"><i class="bi bi-circle"></i> 8 or more characters</li><li data-r="let"><i class="bi bi-circle"></i> a letter</li><li data-r="num"><i class="bi bi-circle"></i> a number</li><li data-r="mix"><i class="bi bi-circle"></i> stronger: a symbol or 12+ characters</li></ul></div>
        <div class="mb-3"><label class="form-label small fw-bold" for="password_confirmation">Repeat new password</label>
          <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" autocomplete="new-password"></div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <button class="btn btn-primary" id="pw-save">{{ $hasPassword ? 'Change password' : 'Set password' }}</button>
          @if($hasPassword)<button type="button" class="btn btn-link text-danger" id="pw-remove">Remove password</button>@endif
        </div>
      </form>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="panel h-100">
      <div class="panel-h"><span class="ico"><i class="bi bi-laptop"></i></span><h2>Recent sign-ins</h2></div>
      @forelse($logins as $l)
        <div class="dev">
          <span class="ic"><i class="bi {{ str_contains($l->device, 'Android') || str_contains($l->device, 'iPhone') ? 'bi-phone' : 'bi-laptop' }}"></i></span>
          <div class="flex-grow-1 min-w-0"><div class="fw-bold ink">{{ $l->device }} @if($l->current)<span class="pill green ms-1">This visit</span>@endif</div>
            <div class="small muted"><i class="bi {{ $methods[$l->method][0] ?? 'bi-box-arrow-in-right' }}"></i> {{ $methods[$l->method][1] ?? $l->method }} · {{ $maskIp($l->ip) }} · {{ $l->at->format('j M Y, g:i a') }}</div></div>
        </div>
      @empty
        <div class="muted small">No sign-ins recorded yet.</div>
      @endforelse
      <hr>
      <div class="d-flex flex-wrap gap-2 align-items-center">
        <button class="btn btn-outline-primary" id="signout-others"><i class="bi bi-box-arrow-right"></i> Sign out of all other devices</button>
        <span class="small muted">Lost a phone, or used a shared computer? Use this.</span>
      </div>
    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-h"><span class="ico"><i class="bi bi-shield-check"></i></span><h2>Your privacy</h2></div>
  <div class="row g-3 small">
    <div class="col-md-4"><b class="ink d-block mb-1"><i class="bi bi-eye-slash"></i> You choose what is public</b><span class="muted">Each detail on your profile has a Public / Private switch. Email and phone are private unless you make them public.</span></div>
    <div class="col-md-4"><b class="ink d-block mb-1"><i class="bi bi-graph-up"></i> Counts only</b><span class="muted">We count visits to your profile, but never record who visited.</span></div>
    <div class="col-md-4"><b class="ink d-block mb-1"><i class="bi bi-envelope-paper"></i> We never ask for your code</b><span class="muted">Organizers will never ask you for a sign-in code or password. Don't share them.</span></div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  var form = document.getElementById('pw-form'), pw = document.getElementById('password');
  form.querySelectorAll('.eye').forEach(function (b) { b.addEventListener('click', function () { var i = b.previousElementSibling; i.type = i.type === 'password' ? 'text' : 'password'; b.innerHTML = '<i class="bi ' + (i.type === 'password' ? 'bi-eye' : 'bi-eye-slash') + '"></i>'; }); });
  pw.addEventListener('input', function () {
    var v = pw.value, r = { len: v.length >= 8, let: /[A-Za-z]/.test(v), num: /\d/.test(v), mix: /[^A-Za-z0-9]/.test(v) || v.length >= 12 };
    var score = Object.keys(r).filter(function (k) { return r[k]; }).length;
    document.querySelectorAll('#pw-rules li').forEach(function (li) { var ok = r[li.dataset.r]; li.classList.toggle('ok', ok); li.querySelector('i').className = 'bi ' + (ok ? 'bi-check-circle-fill' : 'bi-circle'); });
    var m = document.getElementById('pw-meter'); m.style.width = (v ? score * 25 : 0) + '%'; m.style.background = ['#d13c4b', '#d13c4b', '#e0a325', '#2f8a4c', '#2f8a4c'][score];
  });
  function clearErr() { form.querySelectorAll('.is-invalid').forEach(function (e) { e.classList.remove('is-invalid'); }); form.querySelectorAll('.invalid-feedback').forEach(function (e) { e.remove(); }); }
  function showErr(errors) {
    Object.keys(errors || {}).forEach(function (k) {
      var i = form.querySelector('[name="' + k + '"]'); if (!i) return;
      i.classList.add('is-invalid'); var fb = document.createElement('div'); fb.className = 'invalid-feedback d-block'; fb.textContent = errors[k][0];
      (i.closest('.pw') || i).insertAdjacentElement('afterend', fb);
    });
  }
  form.addEventListener('submit', function (ev) {
    ev.preventDefault(); clearErr();
    var btn = document.getElementById('pw-save'); btn.disabled = true;
    var body = { password: pw.value, password_confirmation: form.password_confirmation.value };
    if (form.current_password) body.current_password = form.current_password.value;
    myPost(@json(route('my.password')), body).then(function (res) {
      btn.disabled = false;
      if (res.ok) { myToast(res.d.message); setTimeout(function () { location.reload(); }, 1200); return; }
      showErr(res.d.errors); myToast(res.d.message || 'Not saved.', true);
    });
  });
  var rm = document.getElementById('pw-remove');
  if (rm) rm.addEventListener('click', function () {
    clearErr();
    var cur = form.current_password.value;
    if (!cur) { showErr({ current_password: ['Enter your current password to remove it.'] }); form.current_password.focus(); return; }
    if (!confirm('Remove your password? You will sign in with emailed codes only.')) return;
    myPost(@json(route('my.password.remove')), { current_password: cur }).then(function (res) {
      if (res.ok) { myToast(res.d.message); setTimeout(function () { location.reload(); }, 1200); return; }
      showErr(res.d.errors); myToast(res.d.message || 'Not done.', true);
    });
  });
  document.getElementById('signout-others').addEventListener('click', function () {
    if (!confirm('Sign out of all other devices? This device stays signed in.')) return;
    myPost(@json(route('my.signout-others'))).then(function (res) { myToast(res.ok ? res.d.message : (res.d.message || 'Not done.'), !res.ok); });
  });
})();
</script>
@endpush
