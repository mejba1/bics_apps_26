@extends('backend.layouts.master')
@section('title')
NFC Encoding Station
@endsection
@push('styles')
<style>
  .st-wrap { max-width: 720px; margin: 0 auto; }
  .st-support { border-radius: 14px; padding: 12px 16px; font-weight: 600; }
  .st-person { display: flex; gap: 14px; align-items: center; }
  .st-av { width: 64px; height: 64px; border-radius: 50%; object-fit: cover; background: #e9eff9; color: #1861c5; font-weight: 800; font-size: 1.3rem; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 64px; }
  .st-steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin: 16px 0; }
  .st-steps div { text-align: center; padding: 10px 6px; border-radius: 12px; background: rgba(0,0,0,.04); font-weight: 700; font-size: 13px; color: #6b7489; }
  .st-steps div.ok { background: #e6f5e0; color: #2f7a1b; }
  .st-steps div.now { background: #1861c5; color: #fff; }
  .st-big { font-size: 1.15rem; padding: 18px; border-radius: 16px; font-weight: 800; width: 100%; }
  .st-live { text-align: center; padding: 22px; border-radius: 16px; background: #f3f8ff; border: 2px dashed #9cc0f2; display: none; }
  .st-live.show { display: block; }
  .st-live i { font-size: 2.6rem; color: #1861c5; animation: stp 1.3s infinite; display: inline-block; }
  @keyframes stp { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.15); } }
  .st-queue button { text-align: left; }
  .st-done { text-align: center; padding: 18px; border-radius: 16px; background: #e6f5e0; color: #2f7a1b; font-weight: 800; font-size: 1.1rem; display: none; }
  .st-done.show { display: block; }
  .st-results { position: absolute; z-index: 20; width: 100%; max-height: 320px; overflow-y: auto; }
</style>
@endpush
@section('content')
<div class="pagetitle">
  <h1>NFC Encoding Station</h1>
  <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li><li class="breadcrumb-item"><a href="{{ route('nfc.index') }}">NFC Cards</a></li><li class="breadcrumb-item active">Encoding station</li></ol></nav>
</div>
<section class="section st-wrap">
  @include('backend.pages.participants.link-warning')
  <div class="st-support mb-3" id="st-support"></div>

  <div class="card mb-3"><div class="card-body pt-3">
    <label class="form-label fw-bold" for="st-find">1 · Find the participant</label>
    <div class="position-relative">
      <input class="form-control form-control-lg" id="st-find" placeholder="Name, ID (MBR-…), email or organization" autocomplete="off">
      <div class="list-group st-results shadow d-none" id="st-results"></div>
    </div>
    <div class="small text-muted mt-2">They get a card from stock automatically (or keep the card they already have).</div>
  </div></div>

  <div class="card mb-3 d-none" id="st-card"><div class="card-body pt-3">
    <div class="st-person" id="st-person"></div>
    <div class="st-steps"><div id="s-enc">Write</div><div id="s-ver">Verify</div><div id="s-act">Activate</div></div>

    <div id="st-auto-wrap" class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="st-auto" checked><label class="form-check-label" for="st-auto">One tap: write, read back and activate (keep the card on the phone)</label></div>
    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="st-lock"><label class="form-check-label small" for="st-lock">Lock the chip after writing (read-only, cannot be undone)</label></div>

    <div id="st-actions" class="d-grid gap-2"></div>
    <div class="st-live mt-2" id="st-live"><i class="bi bi-phone-vibrate"></i><div class="fw-bold mt-2" id="st-live-text">Hold the card against the back of the phone…</div><button class="btn btn-sm btn-link" id="st-cancel">Cancel</button></div>
    <div class="st-done mt-2" id="st-done"><i class="bi bi-check-circle-fill"></i> Card ready — tapping it opens the profile.</div>
    <button class="btn btn-outline-primary w-100 mt-3" id="st-next"><i class="bi bi-arrow-right-circle"></i> Next participant</button>
  </div></div>

  <div class="card"><div class="card-body pt-3">
    <div class="d-flex justify-content-between align-items-center mb-2"><span class="fw-bold"><i class="bi bi-list-check"></i> Waiting to be encoded</span><span class="badge bg-primary" id="st-qcount">{{ $counts['to_encode'] }}</span></div>
    <div class="list-group st-queue" id="st-queue"><div class="list-group-item small text-muted">Loading…</div></div>
  </div></div>
</section>
@endsection

@include('backend.pages.nfc.webnfc')

@push('scripts')
<script>
(function () {
  'use strict';
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var urls = { find: @json(route('nfc.participants')), for: @json(url('/nfc-cards/for')), card: @json(url('/nfc-cards')), data: @json(route('nfc.data')) };
  var card = null;
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
  function $(id) { return document.getElementById(id); }
  function post(url, body) {
    return fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(body || {}) })
      .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (res) { if (!res.ok) { var e = res.d.errors || {}; var k = Object.keys(e)[0]; throw new Error(k ? e[k][0] : (res.d.message || 'Please try again.')); } return res.d; });
  }
  function act(action, body) { return post(urls.card + '/' + card.id + '/' + action, body).then(function (d) { card = d.card; draw(); return d; }); }

  $('st-support').className = 'st-support mb-3 ' + (BicsNfc.supported ? 'bg-success-subtle text-success-emphasis' : 'bg-warning-subtle text-warning-emphasis');
  $('st-support').innerHTML = BicsNfc.supported
    ? '<i class="bi bi-broadcast"></i> This phone can write NFC cards. Keep this page open and tap cards against the back of the phone.'
    : '<i class="bi bi-exclamation-triangle"></i> This browser cannot write NFC cards. Open this page in <b>Chrome on an Android phone</b> with NFC switched on — or use an external encoder: copy each card\'s link and mark it as encoded here.';
  if (!BicsNfc.supported) { $('st-auto-wrap').classList.add('d-none'); $('st-lock').closest('.form-check').classList.add('d-none'); }

  function draw() {
    var p = card.participant;
    $('st-card').classList.remove('d-none');
    $('st-person').innerHTML = (p.photo ? '<img class="st-av" src="' + esc(p.photo) + '" alt="">' : '<span class="st-av">' + esc(p.initials) + '</span>') +
      '<div><div class="fw-bold fs-5">' + esc(p.name) + '</div><div class="small text-muted">' + esc(p.code) + (p.organization ? ' · ' + esc(p.organization) : '') + '</div>' +
      '<div class="small mt-1">Card <a href="' + esc(card.show) + '" target="_blank"><b>' + esc(card.card_no) + '</b></a>' + (card.uid ? ' · <code>' + esc(card.uid) + '</code>' : '') + '</div></div>';
    var enc = card.encode_status === 'encoded', ver = card.verify_status === 'verified', on = card.status === 'active';
    $('s-enc').className = enc ? 'ok' : 'now'; $('s-ver').className = ver ? 'ok' : (enc ? 'now' : ''); $('s-act').className = on ? 'ok' : (enc ? (ver ? 'now' : '') : '');
    $('st-done').classList.toggle('show', on);
    var a = '';
    if (!on) {
      if (BicsNfc.supported) {
        if (!enc || !ver) a += '<button class="btn btn-primary st-big" data-do="write"><i class="bi bi-broadcast"></i> ' + (enc ? 'Write again' : 'Write card') + '</button>';
        if (enc && !ver) a += '<button class="btn btn-outline-primary st-big" data-do="read"><i class="bi bi-upc-scan"></i> Read back to verify</button>';
      } else {
        a += '<div class="small">Link to write (NDEF URL record):</div><div class="input-group"><input class="form-control" readonly value="' + esc(card.url) + '"><button class="btn btn-outline-primary" data-do="copy"><i class="bi bi-clipboard"></i></button></div>';
        if (!enc) a += '<button class="btn btn-primary st-big" data-do="mark"><i class="bi bi-check2"></i> I wrote it — mark encoded</button>';
        if (enc && !ver) a += '<button class="btn btn-outline-primary st-big" data-do="vmanual"><i class="bi bi-check2-all"></i> Tapped it: it opened ' + esc(card.card_no) + '</button>';
      }
      if (enc) a += '<button class="btn btn-success st-big" data-do="activate"><i class="bi bi-check-circle"></i> Activate' + (ver ? '' : ' (not verified)') + '</button>';
    }
    $('st-actions').innerHTML = a;
    $('st-actions').querySelectorAll('[data-do]').forEach(function (b) { b.addEventListener('click', function () { run(b.dataset.do); }); });
  }

  function live(on, text) { $('st-live').classList.toggle('show', on); if (text) $('st-live-text').textContent = text; $('st-actions').classList.toggle('d-none', on); }
  function fail(e) { live(false); if (e && e.name === 'AbortError') return; Swal.fire('Not done', (e && e.message && !e.name) ? e.message : BicsNfc.explain(e), 'error'); }

  function run(what) {
    if (what === 'copy') return navigator.clipboard.writeText(card.url).then(function () { Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Link copied', showConfirmButton: false, timer: 1400 }); });
    if (what === 'mark') return act('encoded', { method: 'external' }).catch(fail);
    if (what === 'vmanual') return act('verify', { manual: true }).catch(fail);
    if (what === 'activate') return act('activate').catch(fail);
    if (what === 'read') { live(true, 'Hold the card against the phone to read it…'); return BicsNfc.read().then(function (r) { return act('verify', { read_url: r.url || '', uid: r.uid }); }).then(function () { live(false); }).catch(fail); }
    if (what === 'write') {
      var lock = $('st-lock').checked;
      live(true, 'Hold the card against the back of the phone…');
      BicsNfc.write(card.url, { lock: lock })
        .then(function (r) { return act('encoded', { uid: r.uid, locked: lock, method: 'web_nfc' }); })
        .then(function () {
          if (!$('st-auto').checked) { live(false); return; }
          live(true, 'Written. Keep the card there to read it back (if nothing happens, lift it and tap again)…');
          return BicsNfc.read().then(function (r) { return act('verify', { read_url: r.url || '', uid: r.uid }); }).then(function () { return act('activate'); }).then(function () { live(false); if (navigator.vibrate) navigator.vibrate(120); loadQueue(); });
        })
        .catch(function (e) { if (e && e.name && e.name !== 'AbortError' && card) post(urls.card + '/' + card.id + '/encode_failed', { error: BicsNfc.explain(e) }).catch(function () {}); fail(e); });
    }
  }
  $('st-cancel').addEventListener('click', function () { BicsNfc.cancel(); live(false); });

  function choose(pid) {
    $('st-results').classList.add('d-none'); $('st-find').value = '';
    post(urls.for + '/' + pid).then(function (d) { card = d.card; draw(); loadQueue(); $('st-card').scrollIntoView({ behavior: 'smooth' }); }).catch(fail);
  }
  var t = null;
  $('st-find').addEventListener('input', function () {
    clearTimeout(t); var q = this.value.trim();
    if (q.length < 2) { $('st-results').classList.add('d-none'); return; }
    t = setTimeout(function () {
      fetch(urls.find + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (list) {
        $('st-results').innerHTML = list.map(function (p) {
          return '<button type="button" class="list-group-item list-group-item-action py-3" data-pid="' + p.id + '"><b>' + esc(p.name) + '</b><div class="small text-muted">' + esc(p.code) + (p.organization ? ' · ' + esc(p.organization) : '') +
            (p.card ? ' · card ' + esc(p.card.card_no) + ' (' + esc(p.card.status) + ')' : ' · no card yet') + '</div></button>';
        }).join('') || '<div class="list-group-item text-muted">No active participant found.</div>';
        $('st-results').classList.remove('d-none');
        $('st-results').querySelectorAll('[data-pid]').forEach(function (b) { b.addEventListener('click', function () { choose(b.dataset.pid); }); });
      });
    }, 250);
  });
  $('st-next').addEventListener('click', function () { card = null; $('st-card').classList.add('d-none'); $('st-find').focus(); window.scrollTo({ top: 0, behavior: 'smooth' }); });

  function loadQueue() {
    fetch(urls.data + '?filter=to_encode&per=10', { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
      $('st-qcount').textContent = d.total;
      $('st-queue').innerHTML = d.rows.map(function (c) {
        return '<button type="button" class="list-group-item list-group-item-action" data-pid="' + c.participant.id + '"><b>' + esc(c.participant.name) + '</b> <span class="small text-muted">' + esc(c.participant.code) + ' · ' + esc(c.card_no) + '</span></button>';
      }).join('') || '<div class="list-group-item small text-muted">Nothing waiting. Use "Cards for an event" on the NFC Cards page to assign cards in bulk.</div>';
      $('st-queue').querySelectorAll('[data-pid]').forEach(function (b) { b.addEventListener('click', function () { choose(b.dataset.pid); }); });
    });
  }
  loadQueue();
})();
</script>
@endpush
