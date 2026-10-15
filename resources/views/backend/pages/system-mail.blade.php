@extends('backend.layouts.master')
@section('title')
Email Server
@endsection
@push('styles')
<style>
  .sm-status { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 22px; border-radius: 14px; padding: 14px 18px; margin-bottom: 20px; border: 1px solid var(--ad-line); background: var(--ad-surface); }
  .sm-status .dot { width: 10px; height: 10px; border-radius: 50%; background: var(--ad-amber); box-shadow: 0 0 0 4px var(--ad-amber-50); }
  .sm-status.panel .dot { background: var(--ad-green); box-shadow: 0 0 0 4px var(--ad-green-50); }
  .sm-status b { color: var(--ad-ink); }
  .sm-status span { color: var(--ad-muted); font-size: 13.5px; }
  .sm-card { background: var(--ad-surface); border: 1px solid var(--ad-line); border-radius: 14px; margin-bottom: 18px; }
  .sm-card > h2 { font-size: 13px; font-weight: 800; letter-spacing: .6px; text-transform: uppercase; color: var(--ad-brand); margin: 0; padding: 14px 18px; border-bottom: 1px solid var(--ad-line); display: flex; gap: 8px; align-items: center; }
  .sm-card > .body { padding: 16px 18px; }
  .sm-card .form-label { font-weight: 600; font-size: 13.5px; margin-bottom: 4px; }
  .sm-help { font-size: 12.5px; color: var(--ad-muted); margin-top: 4px; }
  .sm-switch { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 14px 18px; border-bottom: 1px solid var(--ad-line); }
  .sm-switch .t { font-weight: 700; color: var(--ad-ink); }
  .sm-switch .d { font-size: 13px; color: var(--ad-muted); }
  .sm-switch .form-switch { margin: 0; padding-left: 3em; min-height: 0; }
  .sm-switch .form-check-input { width: 2.8em; height: 1.5em; margin-left: -3em; margin-top: 0; cursor: pointer; }
  .sm-presets { display: flex; flex-wrap: wrap; gap: 6px; }
  .sm-presets button { border: 1px solid var(--ad-line); background: var(--ad-chip); color: var(--ad-ink); border-radius: 999px; padding: 4px 12px; font-size: 13px; font-weight: 600; }
  .sm-presets button:hover, .sm-presets button.on { border-color: var(--ad-brand); color: var(--ad-brand); }
  .sm-tip { display: none; margin-top: 10px; font-size: 13px; padding: 9px 12px; border-radius: 10px; background: var(--ad-brand-50); color: var(--ad-ink); }
  .sm-tip.on { display: block; }
  .sm-result { display: none; margin-top: 12px; padding: 11px 13px; border-radius: 10px; font-size: 13.5px; }
  .sm-result.ok { display: block; background: var(--ad-green-50); color: var(--ad-green); }
  .sm-result.err { display: block; background: var(--ad-red-50); color: var(--ad-red); }
  .sm-result code { display: block; margin-top: 6px; white-space: pre-wrap; font-size: 11.5px; color: inherit; opacity: .85; }
  .sm-pw { position: relative; }
  .sm-pw .btn { position: absolute; right: 4px; top: 50%; transform: translateY(-50%); border: 0; color: var(--ad-muted); }
</style>
@endpush
@section('content')
<div class="pagetitle">
  <h1>Email Server (SMTP)</h1>
  <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li><li class="breadcrumb-item active">Email Server</li></ol></nav>
</div>

<section class="section">
  <div class="sm-status {{ $effective['source'] }}" id="smStatus">
    <i class="dot"></i>
    <div><b data-e="title">{{ $effective['source'] === 'panel' ? 'Using the settings below' : 'Using the server settings from the .env file' }}</b><br>
      <span data-e="line">{{ $effective['host'] }}:{{ $effective['port'] }}{{ $effective['encryption'] ? ' · ' . strtoupper($effective['encryption']) : '' }} · from {{ $effective['from_name'] }} &lt;{{ $effective['from_address'] }}&gt;</span></div>
  </div>

  <div class="row g-4">
    <div class="col-xl-7">
      <form id="smForm" class="sm-card" novalidate autocomplete="off">
        <div class="sm-switch">
          <div><div class="t">Use these settings for all emails</div><div class="d">Off: the .env file is used, as before. Test first, then switch on.</div></div>
          <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="enabled" value="1" aria-label="Use these settings" @checked($s['enabled'] === '1')></div>
        </div>
        <div class="body">
          <label class="form-label">Quick setup</label>
          <div class="sm-presets" id="smPresets">
            @foreach($presets as $k => [$label, $host, $port, $enc, $tip])
              <button type="button" data-host="{{ $host }}" data-port="{{ $port }}" data-enc="{{ $enc }}" data-tip="{{ $tip }}">{{ $label }}</button>
            @endforeach
          </div>
          <div class="sm-tip" id="smTip"></div>

          <div class="row g-3 mt-1">
            <div class="col-md-6">
              <label class="form-label" for="sm-host">SMTP host</label>
              <input class="form-control" id="sm-host" name="host" value="{{ $s['host'] }}" placeholder="smtp.gmail.com" maxlength="190">
            </div>
            <div class="col-6 col-md-3">
              <label class="form-label" for="sm-port">Port</label>
              <input class="form-control" id="sm-port" name="port" type="number" min="1" max="65535" value="{{ $s['port'] }}" placeholder="587">
            </div>
            <div class="col-6 col-md-3">
              <label class="form-label" for="sm-enc">Encryption</label>
              <select class="form-select" id="sm-enc" name="encryption">
                <option value="tls" @selected($s['encryption'] === 'tls')>TLS</option>
                <option value="ssl" @selected($s['encryption'] === 'ssl')>SSL</option>
                <option value="none" @selected($s['encryption'] === '' && $s['host'] !== '')>None</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="sm-user">Username</label>
              <input class="form-control" id="sm-user" name="username" value="{{ $s['username'] }}" placeholder="you@example.com" maxlength="190" autocomplete="off">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="sm-pass">Password</label>
              <div class="sm-pw">
                <input class="form-control" id="sm-pass" name="password" type="password" maxlength="255" autocomplete="new-password" placeholder="{{ $hasPassword ? '•••••••• saved (leave empty to keep)' : 'SMTP password or app password' }}">
                <button type="button" class="btn btn-sm" id="smEye" aria-label="Show password"><i class="bi bi-eye"></i></button>
              </div>
              <div class="sm-help" id="smPwHelp">@if($hasPassword)Stored encrypted. <a href="#" id="smClearPw">Remove saved password</a>@else Stored encrypted. @endif</div>
              <input type="hidden" name="clear_password" value="0">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="sm-from">Sender email (From)</label>
              <input class="form-control" id="sm-from" name="from_address" type="email" value="{{ $s['from_address'] }}" placeholder="info@beaconpharma.com.bd" maxlength="190">
              <div class="sm-help">Must belong to this mailbox or domain, or emails may land in spam.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="sm-name">Sender name</label>
              <input class="form-control" id="sm-name" name="from_name" value="{{ $s['from_name'] }}" placeholder="BICS 2026" maxlength="120">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="sm-reply">Reply-to <span class="text-muted fw-normal">(optional)</span></label>
              <input class="form-control" id="sm-reply" name="reply_to" type="email" value="{{ $s['reply_to'] }}" placeholder="Where attendees' replies go" maxlength="190">
            </div>
          </div>
        </div>
        <div class="d-flex justify-content-end gap-2 px-3 pb-3">
          <button type="submit" class="btn btn-success"><i class="bi bi-check2-circle"></i> Save settings</button>
        </div>
      </form>
    </div>

    <div class="col-xl-5">
      <div class="sm-card">
        <h2><i class="bi bi-plug"></i> Test the connection</h2>
        <div class="body">
          <p class="sm-help" style="margin-top:0">Sends a short test email with the values in the form, even before you save. Nothing changes for attendees.</p>
          <label class="form-label" for="sm-to">Send test to</label>
          <div class="d-flex gap-2">
            <input class="form-control" id="sm-to" type="email" value="{{ auth()->user()->email }}">
            <button type="button" class="btn btn-primary text-nowrap" id="smTest"><i class="bi bi-send"></i> Send test</button>
          </div>
          <div class="sm-result" id="smResult" role="status"></div>
        </div>
      </div>
      <div class="sm-card">
        <h2><i class="bi bi-info-circle"></i> Good to know</h2>
        <div class="body small" style="color:var(--ad-muted)">
          <ul class="mb-0 ps-3">
            <li class="mb-2">Gmail: turn on 2-step verification, then create an <b>App Password</b> and use it here. The normal Gmail password does not work.</li>
            <li class="mb-2">Port <b>587</b> uses <b>TLS</b>; port <b>465</b> uses <b>SSL</b>.</li>
            <li class="mb-2">Many hosts block outgoing SMTP; if the test cannot connect, ask the host or use their own mail server (cPanel).</li>
            <li>All emails use this server: confirmations, re-sends from the dashboard, and test emails from Email Template.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script>
  (function () {
    var form = document.getElementById('smForm');
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    var esc = function (t) { var d = document.createElement('div'); d.textContent = t == null ? '' : t; return d.innerHTML; };
    var post = function (url, fd, method) {
      if (method) fd.append('_method', method);
      return fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: fd })
        .then(function (r) { return r.json().catch(function () { return { message: 'Server error (' + r.status + ').' }; }).then(function (d) { return { ok: r.ok, d: d }; }); });
    };
    function clearErrors() {
      form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
      form.querySelectorAll('.invalid-feedback').forEach(function (el) { el.remove(); });
    }
    function showErrors(errs) {
      Object.keys(errs || {}).forEach(function (k) {
        var input = form.querySelector('[name="' + k + '"]'); if (!input) return;
        input.classList.add('is-invalid');
        var fb = document.createElement('div'); fb.className = 'invalid-feedback d-block'; fb.textContent = errs[k][0];
        (input.closest('.sm-pw') || input).insertAdjacentElement('afterend', fb);
      });
    }

    // Presets
    document.querySelectorAll('#smPresets button').forEach(function (b) {
      b.addEventListener('click', function () {
        document.querySelectorAll('#smPresets button').forEach(function (x) { x.classList.toggle('on', x === b); });
        form.host.value = b.dataset.host; form.port.value = b.dataset.port; form.encryption.value = b.dataset.enc;
        var tip = document.getElementById('smTip'); tip.textContent = b.dataset.tip; tip.classList.add('on');
        if (b.dataset.host.indexOf('yourdomain') !== -1) { form.host.focus(); form.host.select(); }
      });
    });
    // Show / hide password
    document.getElementById('smEye').addEventListener('click', function () {
      var p = form.password, show = p.type === 'password';
      p.type = show ? 'text' : 'password'; this.innerHTML = '<i class="bi ' + (show ? 'bi-eye-slash' : 'bi-eye') + '"></i>';
    });
    var clearLink = document.getElementById('smClearPw');
    if (clearLink) clearLink.addEventListener('click', function (e) {
      e.preventDefault(); form.clear_password.value = '1'; form.password.placeholder = 'Saved password will be removed when you save';
      document.getElementById('smPwHelp').textContent = 'The saved password will be removed when you save.';
    });

    // Save
    form.addEventListener('submit', function (e) {
      e.preventDefault(); clearErrors();
      var btn = form.querySelector('[type=submit]'); btn.disabled = true;
      post(@json(route('system.mail.update')), new FormData(form), 'PUT').then(function (res) {
        btn.disabled = false;
        if (!res.ok) { showErrors(res.d.errors); Swal.fire({ icon: 'warning', title: 'Please check', text: res.d.message }); return; }
        var e = res.d.effective, box = document.getElementById('smStatus');
        box.className = 'sm-status ' + e.source;
        box.querySelector('[data-e=title]').textContent = e.source === 'panel' ? 'Using the settings below' : 'Using the server settings from the .env file';
        box.querySelector('[data-e=line]').textContent = e.host + ':' + e.port + (e.encryption ? ' · ' + e.encryption.toUpperCase() : '') + ' · from ' + e.from_name + ' <' + e.from_address + '>';
        form.password.value = ''; form.clear_password.value = '0';
        form.password.placeholder = res.d.hasPassword ? '•••••••• saved (leave empty to keep)' : 'SMTP password or app password';
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: res.d.message, showConfirmButton: false, timer: 2600 });
      }).catch(function () { btn.disabled = false; Swal.fire('Error', 'Could not save. Please try again.', 'error'); });
    });

    // Test
    document.getElementById('smTest').addEventListener('click', function () {
      clearErrors();
      var btn = this, result = document.getElementById('smResult'), label = btn.innerHTML;
      var fd = new FormData(form); fd.append('to', document.getElementById('sm-to').value);
      btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending…';
      result.className = 'sm-result';
      post(@json(route('system.mail.test')), fd).then(function (res) {
        btn.disabled = false; btn.innerHTML = label;
        if (res.ok) { result.className = 'sm-result ok'; result.innerHTML = '<i class="bi bi-check-circle"></i> ' + esc(res.d.message); return; }
        showErrors(res.d.errors);
        result.className = 'sm-result err';
        result.innerHTML = '<i class="bi bi-x-circle"></i> ' + esc(res.d.message) + (res.d.detail ? '<code>' + esc(res.d.detail) + '</code>' : '');
      }).catch(function () { btn.disabled = false; btn.innerHTML = label; result.className = 'sm-result err'; result.textContent = 'No answer from the server (timeout).'; });
    });
  })();
</script>
@endpush
