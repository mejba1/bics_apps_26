/**
 * /registration — "Already joined a BICS event?": find the person → one-time code by email → the 4-step form is
 * filled with their saved details (identity fields locked) and submits to /registration/returning/submit.
 * Uses window.REGFORM from registration.js.
 */
(function () {
  'use strict';
  var REG = window.REG || {}, R = REG.returning;
  var choice = document.querySelector('.reg-choice');
  if (!choice || !R) return;
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || (document.querySelector('#regForm [name=_token]') || {}).value;
  function $(id) { return document.getElementById(id); }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  var panelNew = $('reg-new'), panelBack = $('reg-returning'), welcome = $('rt-welcome');
  var filled = false, resendTimer = null;

  function post(url, body) {
    return fetch(url, { method: 'POST', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(body || {}) })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { status: r.status, ok: r.ok, d: d }; }); });
  }
  function err(id, msg) { var e = $(id); e.hidden = !msg; e.innerHTML = msg ? '<i class="bi bi-exclamation-circle-fill"></i> ' + esc(msg) : ''; }
  function busy(btn, on, label) { btn.disabled = on; if (on) { btn.dataset.label = btn.innerHTML; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> ' + label; } else if (btn.dataset.label) btn.innerHTML = btn.dataset.label; }

  /* --- choosing new / returning --- */
  function setMode(mode) {
    choice.querySelectorAll('.reg-option').forEach(function (b) { var on = b.dataset.mode === mode; b.classList.toggle('is-on', on); b.setAttribute('aria-checked', on ? 'true' : 'false'); });
    if (mode === 'new') {
      if (filled) {   // details of a returning participant are in the form: start a clean form
        post(R.reset).then(function () { location.href = location.pathname; });
        return;
      }
      panelBack.hidden = true; panelNew.hidden = false;
    } else {
      panelNew.hidden = !filled; panelBack.hidden = filled;
      if (!filled) setTimeout(function () { $('rt-lookup').focus(); }, 50);
    }
  }
  choice.querySelectorAll('.reg-option').forEach(function (b) { b.addEventListener('click', function () { setMode(b.dataset.mode); }); });

  /* --- step 1: find & send a code --- */
  function sendCode(btn) {
    var q = $('rt-lookup').value.trim();
    err('rt-find-err', '');
    if (!q) { err('rt-find-err', 'Enter your email, participant ID or registration number.'); $('rt-lookup').focus(); return; }
    busy(btn, true, 'Sending…');
    var guard = window.FormGuard ? window.FormGuard.fields($('rt-find')) : {};
    guard.lookup = q;
    post(R.send, guard).then(function (res) {
      busy(btn, false);
      if (window.FormGuard) window.FormGuard.reset($('rt-find'));
      if (!res.ok) {
        var msg = (res.d.errors && res.d.errors.lookup && res.d.errors.lookup[0]) || res.d.message || 'Please try again.';
        if (res.status === 429 && !res.d.message) msg = 'Too many attempts. Please wait a minute and try again.';
        if ($('rt-code').hidden) err('rt-find-err', msg); else err('rt-code-err', msg);
        return;
      }
      $('rt-to').textContent = res.d.to;
      $('rt-find').hidden = true; $('rt-code').hidden = false;
      $('rt-sub').textContent = 'Check your inbox — we just emailed you a one-time code.';
      boxes.forEach(function (b) { b.value = ''; });
      boxes[0].focus();
      startResendTimer();
    }).catch(function () { busy(btn, false); err('rt-find-err', 'Could not connect. Please try again.'); });
  }
  $('rt-find').addEventListener('submit', function (e) { e.preventDefault(); sendCode($('rt-send')); });
  function startResendTimer() {
    var left = 60, b = $('rt-resend');
    clearInterval(resendTimer);
    b.disabled = true;
    function paint() {
      b.textContent = left > 0 ? 'Resend code (' + left + 's)' : 'Resend code';
      if (left <= 0) { clearInterval(resendTimer); b.disabled = false; }
      left--;
    }
    paint();
    resendTimer = setInterval(paint, 1000);
  }
  $('rt-resend').addEventListener('click', function () { err('rt-code-err', ''); sendCode(this); });
  $('rt-change').addEventListener('click', function () {
    $('rt-code').hidden = true; $('rt-find').hidden = false; err('rt-code-err', '');
    $('rt-sub').textContent = 'Enter the email you registered with, your participant ID (e.g. MBR-000123) or an earlier registration number.';
    $('rt-lookup').select();
  });

  /* --- step 2: the code (6 boxes: type, paste, backspace) --- */
  var boxes = Array.prototype.slice.call(document.querySelectorAll('.reg-otp-box'));
  function code() { return boxes.map(function (b) { return b.value; }).join(''); }
  boxes.forEach(function (b, i) {
    b.addEventListener('input', function () {
      var digits = b.value.replace(/\D/g, '');
      if (digits.length > 1) {   // pasted / autofilled several digits
        digits.split('').slice(0, 6 - i).forEach(function (d, k) { boxes[i + k].value = d; });
        boxes[Math.min(5, i + digits.length - 1)].focus();
      } else {
        b.value = digits;
        if (digits && i < 5) boxes[i + 1].focus();
      }
      err('rt-code-err', '');
      if (code().length === 6) verify();
    });
    b.addEventListener('keydown', function (e) {
      if (e.key === 'Backspace' && !b.value && i > 0) { boxes[i - 1].focus(); boxes[i - 1].value = ''; e.preventDefault(); }
      if (e.key === 'ArrowLeft' && i > 0) boxes[i - 1].focus();
      if (e.key === 'ArrowRight' && i < 5) boxes[i + 1].focus();
    });
    b.addEventListener('paste', function (e) {
      var t = ((e.clipboardData || window.clipboardData).getData('text') || '').replace(/\D/g, '').slice(0, 6);
      if (!t) return;
      e.preventDefault();
      t.split('').forEach(function (d, k) { if (boxes[k]) boxes[k].value = d; });
      boxes[Math.min(5, t.length - 1)].focus();
      if (t.length === 6) verify();
    });
  });
  var verifying = false;
  function verify() {
    if (verifying) return;
    var c = code();
    if (c.length !== 6) { err('rt-code-err', 'Enter all 6 digits.'); return; }
    verifying = true;
    var btn = $('rt-verify'); busy(btn, true, 'Checking…');
    post(R.verify, { code: c }).then(function (res) {
      verifying = false; busy(btn, false);
      if (!res.ok) {
        if (res.d.restart) { $('rt-change').click(); err('rt-find-err', res.d.message); return; }
        err('rt-code-err', res.d.message || 'That code is not right.');
        boxes.forEach(function (b) { b.value = ''; }); boxes[0].focus();
        return;
      }
      clearInterval(resendTimer);
      if (res.d.already) return showAlready(res.d);
      showForm(res.d);
    }).catch(function () { verifying = false; busy(btn, false); err('rt-code-err', 'Could not connect. Please try again.'); });
  }
  $('rt-code').addEventListener('submit', function (e) { e.preventDefault(); verify(); });

  /* --- results --- */
  function showAlready(d) {
    $('rt-code').hidden = true;
    $('rt-sub').textContent = 'Good news — nothing more to do.';
    var done = $('rt-done');
    done.innerHTML = '<div class="reg-already"><i class="bi bi-patch-check-fill"></i><div><b>' + esc(d.name) + ', you are already registered.</b>' +
      '<div>Your registration number: <span class="reg-already-code">' + esc(d.code) + '</span></div>' +
      (d.dashboard ? '<a class="btn reg-btn-primary mt-3" href="' + esc(d.dashboard) + '"><i class="bi bi-person-badge"></i> Open my participant dashboard</a>' : '') + '</div></div>';
    done.hidden = false;
  }
  function showForm(d) {
    var p = d.data;
    filled = true;
    panelBack.hidden = true;
    var ev = (p.events || []).length ? ' from ' + p.events.slice(0, 3).join(', ') : '';
    welcome.innerHTML = '<span class="reg-welcome-ic">' + (p.photo_url ? '<img src="' + esc(p.photo_url) + '" alt="">' : '<i class="bi bi-person-check"></i>') + '</span>' +
      '<div><b>Welcome back, ' + esc(p.name) + '!</b><div>We filled in your details' + esc(ev) + '. Check them, update anything that changed, and submit. ' +
      'Your name, email, date of birth and gender stay as before <i class="bi bi-lock-fill"></i>.</div></div>' +
      '<span class="reg-welcome-id">' + esc(p.participant_code) + '</span>';
    welcome.hidden = false;
    panelNew.hidden = false;
    window.REGFORM.setAction(R.submit);
    window.REGFORM.fill(p, d.locked || []);
    welcome.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  /* --- event countdown (to the start of the event) --- */
  var cd = document.querySelector('.reg-countdown');
  if (cd) {
    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
    (function tick() {
      var left = Math.max(0, Math.floor((new Date(cd.dataset.target) - new Date()) / 1000));
      cd.querySelector('[data-u=d]').textContent = Math.floor(left / 86400);
      cd.querySelector('[data-u=h]').textContent = pad(Math.floor(left % 86400 / 3600));
      cd.querySelector('[data-u=m]').textContent = pad(Math.floor(left % 3600 / 60));
      cd.querySelector('[data-u=s]').textContent = pad(left % 60);
      if (left > 0) setTimeout(tick, 1000);
    })();
  }
})();
