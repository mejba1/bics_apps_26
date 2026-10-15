@extends('backend.layouts.master')
@section('title')
Platform Settings
@endsection
@php
  $when = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('j M Y') : '—';
  $select = function ($name, $value, $periods) {
      $h = '<select class="form-select" name="' . $name . '">';
      foreach ($periods as $d => $label) $h .= '<option value="' . $d . '"' . ((int) $value === $d ? ' selected' : '') . '>' . e($label) . '</option>';
      return $h . '</select>';
  };
@endphp
@section('content')
<div class="pagetitle">
  <h1>Platform Settings</h1>
  <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li><li class="breadcrumb-item active">Platform Settings</li></ol></nav>
</div>
<section class="section">
  <form id="ps-form" novalidate>
  <div class="row g-3">
    <div class="col-xl-5">
      <div class="card h-100"><div class="card-body pt-3">
        <h5 class="card-title py-1"><i class="bi bi-person-vcard"></i> Participant IDs</h5>
        <label class="form-label fw-semibold" for="ps-prefix">ID prefix</label>
        <div class="input-group" style="max-width:320px"><input class="form-control text-uppercase" id="ps-prefix" name="prefix" value="{{ $s['prefix'] }}" maxlength="10"><span class="input-group-text">-{{ substr($nextCode, -6) }}</span></div>
        <div class="form-text">The next new participant gets <b id="ps-next">{{ $nextCode }}</b>. Existing IDs never change — they are printed on cards and inside QR codes and NFC chips, so their links keep working.</div>
        <div class="alert alert-light border small mt-3 mb-0"><i class="bi bi-link-45deg"></i> The web address used in profile links, QR codes and NFC cards is set in <a href="{{ route('participants.index') }}">Participant Profiles › Link settings</a>.</div>
      </div></div>
    </div>
    <div class="col-xl-7">
      <div class="card h-100"><div class="card-body pt-3">
        <h5 class="card-title py-1"><i class="bi bi-clock-history"></i> How long records are kept</h5>
        <p class="small text-muted">Older entries are removed every night. Keeping less is kinder to privacy; the audit log is usually kept for a year.</p>
        <div class="table-responsive"><table class="table align-middle mb-2">
          <thead><tr><th>Record</th><th style="width:200px">Keep for</th><th class="text-end">Now</th></tr></thead>
          <tbody>
            <tr><td><b>Audit log</b><div class="small text-muted">Admin changes and sign-ins</div></td><td>{!! $select('audit_days', $s['audit_days'], $periods) !!}</td>
              <td class="text-end small">{{ number_format($storage['audit']['rows']) }} entries<br><span class="text-muted">since {{ $when($storage['audit']['oldest']) }}</span></td></tr>
            <tr><td><b>Profile visit counts</b><div class="small text-muted">Daily totals for analytics (no visitor data)</div></td><td>{!! $select('stats_days', $s['stats_days'], $periods) !!}</td>
              <td class="text-end small">{{ number_format($storage['stats']['rows']) }} rows<br><span class="text-muted">since {{ $when($storage['stats']['oldest']) }}</span></td></tr>
            <tr><td><b>Participant sign-in history</b><div class="small text-muted">Device and IP of dashboard sign-ins</div></td><td>{!! $select('logins_days', $s['logins_days'], $periods) !!}</td>
              <td class="text-end small">{{ number_format($storage['logins']['rows']) }} sign-ins<br><span class="text-muted">since {{ $when($storage['logins']['oldest']) }}</span></td></tr>
          </tbody></table></div>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="ps-prune"><i class="bi bi-trash3"></i> Remove old entries now</button>
      </div></div>
    </div>
    <div class="col-12">
      <div class="card"><div class="card-body pt-3">
        <h5 class="card-title py-1"><i class="bi bi-lock"></i> After an event</h5>
        <div class="row g-3 align-items-center">
          <div class="col-md-5">
            <label class="form-label fw-semibold" for="ps-lock">Make an event read-only</label>
            <select class="form-select" id="ps-lock" name="lock_days">
              @foreach([0 => 'Right after its last day', 1 => '1 day after its last day', 2 => '2 days after (recommended)', 3 => '3 days after', 7 => '1 week after', 14 => '2 weeks after', 30 => '1 month after'] as $d => $label)
                <option value="{{ $d }}" @selected((int) $s['lock_days'] === $d)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-7 small text-muted">
            Archived events are read-only at once. A read-only event refuses check-in, gifts, the raffle, ID card assignment and registration changes — certificates, prize emails, printing and reports keep working.
            Self check-in always closes when the last day ends. A Super Admin can unlock an event for {{ \App\Support\EventLock::UNLOCK_HOURS }} hours to correct something.
          </div>
        </div>
      </div></div>
    </div>
    <div class="col-12">
      <div class="card"><div class="card-body pt-3">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
          <h5 class="card-title py-1 mb-0"><i class="bi bi-shield-check"></i> Spam &amp; bot protection</h5>
          <span class="badge rounded-pill {{ $blocked ? 'bg-warning-subtle text-warning-emphasis' : 'bg-success-subtle text-success-emphasis' }} border">{{ number_format($blocked) }} blocked in the last 7 days</span>
        </div>
        <div class="row g-4 mt-0">
          <div class="col-lg-5">
            <div class="small fw-semibold mb-2">Always on for every public form</div>
            <ul class="list-unstyled small mb-0 d-grid gap-2">
              <li><i class="bi bi-check-circle-fill text-success"></i> <b>Hidden trap field</b> — bots fill it in, people never see it</li>
              <li><i class="bi bi-check-circle-fill text-success"></i> <b>Time check</b> — forms sent back faster than a person can type are refused</li>
              <li><i class="bi bi-check-circle-fill text-success"></i> <b>Rate limits</b> — a few tries per minute per visitor; admin sign-in locks for 15 minutes after 5 wrong passwords</li>
              <li><i class="bi bi-check-circle-fill text-success"></i> <b>Content rules</b> — no links or code in names and institutions, at most 2 links in a contact message</li>
              <li><i class="bi bi-check-circle-fill text-success"></i> <b>CSRF tokens &amp; security headers</b> on every page; uploaded files can never run as scripts</li>
            </ul>
            <div class="small text-muted mt-2">Covers: registration, "Already joined a BICS event?", contact form, participant sign-in and admin sign-in.</div>
          </div>
          <div class="col-lg-7">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" role="switch" id="ps-disposable" name="block_disposable" {{ $guard['block_disposable'] ? 'checked' : '' }}>
              <label class="form-check-label fw-semibold" for="ps-disposable">Block disposable email addresses</label>
              <div class="form-text mt-0">New registrations and contact messages from throw-away mailboxes (mailinator, yopmail, 10minutemail …) are refused.</div>
            </div>
            <div class="fw-semibold small mb-1">"I am human" check — Cloudflare Turnstile <span class="badge {{ $turnstileOn ? 'bg-success' : 'bg-secondary' }} ms-1">{{ $turnstileOn ? 'On' : 'Off' }}</span></div>
            <div class="form-text mt-0 mb-2">Optional extra wall for heavy bot traffic. Free; usually invisible to people. Create a widget at <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener">dash.cloudflare.com › Turnstile</a> for your domain and paste its keys. Leave the site key empty to switch it off. Admin sign-in never uses it.</div>
            <div class="row g-2">
              <div class="col-md-6"><label class="form-label small" for="ps-ts-site">Site key</label><input class="form-control font-monospace" id="ps-ts-site" name="turnstile_site" value="{{ $guard['turnstile_site'] }}" maxlength="100" autocomplete="off" placeholder="0x4AAAAAAA…"></div>
              <div class="col-md-6"><label class="form-label small" for="ps-ts-secret">Secret key</label><input class="form-control font-monospace" id="ps-ts-secret" name="turnstile_secret" type="password" value="" maxlength="100" autocomplete="new-password" placeholder="{{ $guard['turnstile_secret'] !== '' ? '•••••••• saved — type to replace' : '0x4AAAAAAA…' }}"></div>
            </div>
          </div>
        </div>
      </div></div>
    </div>
  </div>
  <div class="mt-3"><button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> Save settings</button></div>
  </form>
</section>
@endsection
@push('scripts')
<script>
(function () {
  var f = document.getElementById('ps-form'), csrf = document.querySelector('meta[name="csrf-token"]').content;
  function post(url, body) {
    return fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(body || {}) })
      .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (res) { if (!res.ok) { var e = res.d.errors || {}; var k = Object.keys(e)[0]; Swal.fire('Not saved', k ? e[k][0] : (res.d.message || 'Please try again.'), 'warning'); return null; } return res.d; });
  }
  f.elements.prefix.addEventListener('input', function () { var v = f.elements.prefix.value.toUpperCase().replace(/[^A-Z]/g, ''); f.elements.prefix.value = v; document.getElementById('ps-next').textContent = (v || '…') + @json(substr($nextCode, strrpos($nextCode, '-'))); });
  f.addEventListener('submit', function (e) {
    e.preventDefault();
    var el = f.elements;
    post(@json(route('system.settings')), { prefix: el.prefix.value, audit_days: +el.audit_days.value, stats_days: +el.stats_days.value, logins_days: +el.logins_days.value, lock_days: +el.lock_days.value,
        block_disposable: el.block_disposable.checked, turnstile_site: el.turnstile_site.value.trim(), turnstile_secret: el.turnstile_secret.value.trim() })
      .then(function (d) { if (d) Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: d.message, showConfirmButton: false, timer: 3000 }).then(function () { if (d.reload) location.reload(); }); });
  });
  document.getElementById('ps-prune').addEventListener('click', function () {
    Swal.fire({ title: 'Remove old entries now?', text: 'Uses the saved periods (save first if you changed them).', icon: 'question', showCancelButton: true, confirmButtonText: 'Remove' })
      .then(function (r) { if (r.isConfirmed) post(@json(route('system.settings.prune'))).then(function (d) { if (d) Swal.fire('Done', d.message, 'success').then(function () { location.reload(); }); }); });
  });
})();
</script>
@endpush
