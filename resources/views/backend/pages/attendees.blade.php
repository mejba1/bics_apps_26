@extends('backend.layouts.master')
{{-- Verify Attendee ($mode = verify) and Gift Distribution ($mode = gift): one event — the pinned one, or ?event={id}. --}}
@php
  $isGift = $mode === 'gift';
  $atRo = \App\Support\EventLock::isLocked($event) || !\App\Support\EventLock::isCurrentOrUnlocked($event);   // current event only
  $title = $isGift ? 'Gift Distribution' : 'Verify Attendee';
  $done = $rows->filter(fn ($r) => $isGift ? $r['gift'] : $r['in'])->count();
  $total = $rows->count();
  $pct = $total ? round($done / $total * 100) : 0;
  $base = $isGift ? url('/gift-distribute') : url('/verify-user');
@endphp
@section('title', $title)
@push('styles')
<style>
  .at-ev { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
  .at-chip { display: inline-flex; align-items: center; gap: 7px; font-weight: 700; font-size: 14px; padding: 7px 12px; border-radius: 999px; }
  .at-chip.pin { background: var(--ad-accent-50); color: var(--ad-accent); } .at-chip.other { background: var(--ad-amber-50); color: var(--ad-amber); }
  .at-kpi { border: 1px solid var(--ad-line); border-radius: 16px; padding: 14px 16px; background: var(--ad-surface); height: 100%; }
  .at-kpi .n { font-size: 1.7rem; font-weight: 800; line-height: 1; color: var(--ad-ink); } .at-kpi .l { font-size: 12.5px; color: var(--ad-muted); margin-top: 5px; }
  .at-bar { height: 10px; border-radius: 999px; background: var(--ad-chip); overflow: hidden; margin-top: 12px; }
  .at-bar i { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, {{ $isGift ? '#b22e6b, #e0679a' : '#1f9d55, #3fc27a' }}); transition: width .5s; }
  .at-card { border: 1px solid var(--ad-line); border-radius: 18px; background: var(--ad-surface); }
  .at-tools { display: flex; flex-wrap: wrap; gap: 10px; justify-content: space-between; align-items: center; padding: 16px 18px 8px; }
  .at-seg { display: inline-flex; background: var(--ad-chip); border-radius: 10px; padding: 3px; }
  .at-seg button { border: 0; background: none; padding: 6px 12px; border-radius: 8px; font-size: 13px; font-weight: 600; color: var(--ad-muted); }
  .at-seg button.on { background: var(--ad-surface); color: var(--ad-ink); box-shadow: 0 1px 4px rgba(0,0,0,.12); }
  .at-row { display: grid; grid-template-columns: 22px 56px minmax(0, 1.6fr) minmax(0, 1.2fr) 210px; gap: 14px; align-items: center; padding: 12px 18px; border-top: 1px solid var(--ad-line-soft); }
  .at-row:hover { background: var(--ad-hover); }
  .at-av { width: 52px; height: 52px; border-radius: 50%; object-fit: cover; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; background: var(--ad-brand-50); color: var(--ad-brand); cursor: zoom-in; }
  .at-who b { color: var(--ad-ink); display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .at-num { font-family: Consolas, ui-monospace, monospace; font-weight: 800; color: var(--ad-brand); font-size: 13.5px; }
  .at-sub { font-size: 12.5px; color: var(--ad-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .at-act { text-align: right; }
  .at-done { display: inline-flex; flex-direction: column; align-items: flex-end; gap: 2px; }
  .at-done .t { display: inline-flex; gap: 6px; align-items: center; font-weight: 700; font-size: 13px; padding: 5px 10px; border-radius: 9px; background: {{ $isGift ? 'rgba(178,46,107,.12)' : 'var(--ad-green-50)' }}; color: {{ $isGift ? '#b22e6b' : 'var(--ad-green)' }}; }
  .at-done small { color: var(--ad-muted); font-size: 11.5px; }
  .at-undo { border: 0; background: none; color: var(--ad-muted); font-size: 12px; padding: 0; text-decoration: underline; text-underline-offset: 2px; }
  .at-undo:hover { color: var(--ad-red); }
  .at-flag { font-size: 11px; font-weight: 800; padding: 2px 7px; border-radius: 6px; margin-left: 6px; vertical-align: 1px; }
  .at-flag.in { background: var(--ad-green-50); color: var(--ad-green); } .at-flag.out { background: var(--ad-chip); color: var(--ad-muted); }
  .at-flag.warn { background: var(--ad-amber-50); color: var(--ad-amber); }
  .at-empty { text-align: center; padding: 40px 10px; color: var(--ad-muted); } .at-empty .big { font-size: 2.4rem; }
  .at-more { text-align: center; padding: 14px; border-top: 1px solid var(--ad-line-soft); }
  .at-row.flash { animation: atFlash 1.6s ease; } @keyframes atFlash { 0% { background: {{ $isGift ? 'rgba(178,46,107,.18)' : 'rgba(31,157,85,.2)' }}; } 100% { background: transparent; } }
  .at-ro .at-row { grid-template-columns: 56px minmax(0, 1.6fr) minmax(0, 1.2fr) 210px; }
  .at-row.is-sel { background: var(--ad-brand-50); }
  .at-cb { width: 18px; height: 18px; cursor: pointer; margin: 0; }
  .at-bulk { position: sticky; top: 70px; z-index: 5; display: none; align-items: center; gap: 10px; flex-wrap: wrap; margin: 0 18px 8px; padding: 10px 14px; border-radius: 14px;
    background: var(--ad-ink); color: #fff; box-shadow: 0 14px 30px -18px rgba(0,0,0,.6); }
  .at-bulk.on { display: flex; animation: atIn .18s ease; } @keyframes atIn { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: none; } }
  html[data-theme="dark"] .at-bulk { background: #1d2a44; }
  .at-bulk b { font-size: 14px; } .at-bulk .sp { flex: 1; }
  .at-bulk .btn-link { color: #c9d6ee; text-decoration: none; font-size: 13px; }
  .at-selall { display: inline-flex; align-items: center; gap: 7px; font-size: 13px; font-weight: 600; color: var(--ad-muted); cursor: pointer; user-select: none; }
  @media (max-width: 991.98px) { .at-row { grid-template-columns: 22px 52px minmax(0, 1fr) auto; } .at-ro .at-row { grid-template-columns: 52px minmax(0, 1fr) auto; } .at-row .at-contact { display: none; } }
  @media (max-width: 575.98px) { .at-row { grid-template-columns: 22px 46px minmax(0, 1fr); } .at-ro .at-row { grid-template-columns: 46px minmax(0, 1fr); } .at-row .at-act { grid-column: 1 / -1; text-align: left; } .at-done { align-items: flex-start; } }
</style>
@endpush
@section('content')
<div class="pagetitle d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div>
    <h1>{{ $title }}</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li><li class="breadcrumb-item">Attendee Activities</li><li class="breadcrumb-item active">{{ $title }}</li></ol></nav>
  </div>
  <div class="at-ev">
    @if($pinned)
      <span class="at-chip pin" title="Your pinned event (change it at the top of the page)"><i class="bi bi-pin-angle-fill"></i> {{ $event->event_short }}</span>
    @else
      <span class="at-chip other" title="You are looking at another event; your pinned event is unchanged"><i class="bi bi-eye"></i> Viewing {{ $event->event_short }}</span>
      <button type="button" class="btn btn-sm btn-outline-secondary" data-pin="{{ $event->venue_code }}"><i class="bi bi-pin-angle"></i> Pin it</button>
    @endif
    <select class="form-select form-select-sm" style="width:auto" aria-label="Show another event" onchange="location.href='{{ $base }}' + (this.value ? '?event=' + this.value : '')">
      @foreach($events as $e)<option value="{{ $e->id }}" @selected($e->id === $event->id)>{{ $e->event_short }}{{ $e->is_current ? ' (current)' : '' }}</option>@endforeach
    </select>
  </div>
</div>

<section class="section">
  @include('partials.event-lock', ['event' => $event])
  @if($atRo && !\App\Support\EventLock::isLocked($event))
    <div class="alert alert-light border d-flex gap-2 align-items-center small"><i class="bi bi-eye fs-5"></i><div><b>{{ $event->event_short }} is a previous event — view only.</b> {{ $isGift ? 'Gifts' : 'Check-in' }} can only be changed for the current event{{ ($c = \App\Models\Event::current()) ? ' (' . $c->event_short . ')' : '' }}.</div></div>
  @endif
  <div class="row g-3 mb-3">
    <div class="col-md-6"><div class="at-kpi">
      <div class="d-flex justify-content-between align-items-end">
        <div><div class="n"><span id="at-done">{{ $done }}</span> <small style="font-size:1rem;color:var(--ad-muted)">/ {{ $total }}</small></div><div class="l">{{ $isGift ? 'Gifts handed out' : 'Checked in' }} · {{ $event->event_short }}</div></div>
        <b style="font-size:1.4rem;color:{{ $isGift ? '#b22e6b' : 'var(--ad-green)' }}"><span id="at-pct">{{ $pct }}</span>%</b>
      </div>
      <div class="at-bar"><i id="at-bar" style="width:{{ $pct }}%"></i></div>
    </div></div>
    <div class="col-6 col-md-3"><div class="at-kpi"><div class="n" id="at-left">{{ $total - $done }}</div><div class="l">{{ $isGift ? 'Still to receive a gift' : 'Not checked in yet' }}</div></div></div>
    <div class="col-6 col-md-3"><div class="at-kpi">
      @if($isGift)
        <div class="n">{{ $rows->where('in', '!=', null)->where('gift', false)->count() }}</div><div class="l">Checked in, no gift yet</div>
      @else
        <div class="n">{{ $total }}</div><div class="l">Registered for {{ $event->event_short }}</div>
      @endif
    </div></div>
  </div>

  <div class="at-card {{ $atRo ? 'at-ro' : '' }}">
    <div class="at-tools">
      <div class="d-flex flex-wrap gap-2 align-items-center">
        @unless($atRo)<label class="at-selall" title="Select everyone shown"><input type="checkbox" class="form-check-input at-cb" id="at-all"> All shown</label>@endunless
        <input type="search" class="form-control form-control-sm" id="at-q" placeholder="Name, registration no., email, phone…" style="width:260px" autocomplete="off">
        <div class="at-seg" role="group" aria-label="Show">
          <button type="button" class="on" data-f="">All</button>
          <button type="button" data-f="todo">{{ $isGift ? 'No gift yet' : 'Not checked in' }}</button>
          <button type="button" data-f="done">{{ $isGift ? 'Gift given' : 'Checked in' }}</button>
          @if($isGift)<button type="button" data-f="ready">Checked in, no gift</button>@endif
        </div>
      </div>
      <small class="text-muted" id="at-count"></small>
    </div>
    <div class="at-bulk" id="at-bulk" role="region" aria-label="Selected people">
      <i class="bi bi-check2-square"></i><b><span id="at-sel-n">0</span> selected</b>
      <button type="button" class="btn btn-link btn-sm p-0" id="at-sel-clear">Clear</button>
      <span class="sp"></span>
      @if($isGift)
        <button type="button" class="btn btn-sm btn-light" data-bulk="ungive"><i class="bi bi-arrow-counterclockwise"></i> Undo gifts</button>
        <button type="button" class="btn btn-sm" style="background:#b22e6b;color:#fff" data-bulk="give"><i class="bi bi-gift-fill"></i> Give gifts to <span class="n">0</span></button>
      @else
        <button type="button" class="btn btn-sm btn-light" data-bulk="uncheck"><i class="bi bi-arrow-counterclockwise"></i> Undo check-ins</button>
        <button type="button" class="btn btn-sm btn-success" data-bulk="checkin"><i class="bi bi-check2-circle"></i> Check in <span class="n">0</span></button>
      @endif
    </div>
    <div id="at-list"></div>
    <div class="at-more d-none" id="at-more"><button class="btn btn-sm btn-outline-secondary" id="at-more-b">Show more</button></div>
  </div>
</section>
@endsection
@push('scripts')
@php
  $boot = ['rows' => $rows, 'mode' => $mode, 'event' => $event->id, 'locked' => $atRo,
    'r' => ['bulk' => route('attendees.bulk', $mode), 'in' => route('checkin.manual'), 'out' => route('checkin.undo'), 'give' => url('/gift-distribute/ID/give'), 'ungive' => url('/gift-distribute/ID/undo')]];
@endphp
<script>
(function () {
  'use strict';
  var B = @json($boot), gift = B.mode === 'gift', rows = B.rows, shown = 100, filter = '';
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  function $(id) { return document.getElementById(id); }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function toast(m, i) { Swal.fire({ toast: true, position: 'top-end', icon: i || 'success', title: m, showConfirmButton: false, timer: 2600 }); }
  function post(url, body) {
    return fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(body || {}) })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (res) { if (!res.ok) { Swal.fire('Not done', res.d.message || 'Please try again.', 'warning'); return null; } return res.d; });
  }
  var isDone = function (r) { return gift ? r.gift : !!r.in; };
  var sel = {};   // selected registration ids
  function selCount() { return Object.keys(sel).length; }
  function syncBulk() {
    var n = selCount(), bar = $('at-bulk'); if (!bar) return;
    bar.classList.toggle('on', n > 0);
    $('at-sel-n').textContent = n;
    var picked = rows.filter(function (r) { return sel[r.id]; });
    var todo = picked.filter(function (r) { return !isDone(r); }).length, done = n - todo;
    bar.querySelectorAll('[data-bulk]').forEach(function (b) {
      var undo = b.dataset.bulk === 'uncheck' || b.dataset.bulk === 'ungive';
      b.disabled = undo ? !done : !todo;
      var c = b.querySelector('.n'); if (c) c.textContent = todo;
    });
    var all = $('at-all'); if (all) { var shownIds = list().slice(0, shown).map(function (r) { return r.id; }); var k = shownIds.filter(function (id) { return sel[id]; }).length;
      all.checked = shownIds.length > 0 && k === shownIds.length; all.indeterminate = k > 0 && k < shownIds.length; }
  }

  function act(r) {
    if (B.locked) {   // ended / archived event: status only, no actions
      if (gift) return r.gift ? '<span class="at-done"><span class="t"><i class="bi bi-gift-fill"></i> Given' + (r.gift_at ? ' · ' + esc(r.gift_at) : '') + '</span>' + (r.gift_by ? '<small>by ' + esc(r.gift_by) + '</small>' : '') + '</span>' : '<span class="at-sub">No gift</span>';
      return r.in ? '<span class="at-done"><span class="t"><i class="bi bi-patch-check-fill"></i> Checked in · ' + esc(r.in) + '</span>' + (r.in_by ? '<small>by ' + esc(r.in_by) + '</small>' : '') + '</span>' : '<span class="at-sub">Did not check in</span>';
    }
    if (gift) {
      if (r.gift) return '<span class="at-done"><span class="t"><i class="bi bi-gift-fill"></i> Given' + (r.gift_at ? ' · ' + esc(r.gift_at) : '') + '</span>' +
        (r.gift_by ? '<small>by ' + esc(r.gift_by) + '</small>' : '') + '<button class="at-undo" data-undo="' + r.id + '">Undo</button></span>';
      return '<button class="btn btn-sm" style="background:#b22e6b;color:#fff" data-do="' + r.id + '"><i class="bi bi-gift"></i> Give gift</button>';
    }
    if (r.in) {
      var how = { self: 'own phone', kiosk: 'kiosk', desk: 'help desk' }[r.in_how] || '';
      return '<span class="at-done"><span class="t"><i class="bi bi-patch-check-fill"></i> Checked in · ' + esc(r.in) + '</span>' +
        '<small>' + esc(how) + (r.in_by ? ' · by ' + esc(r.in_by) : '') + '</small><button class="at-undo" data-undo="' + r.id + '">Undo</button></span>';
    }
    return '<button class="btn btn-sm btn-success" data-do="' + r.id + '"><i class="bi bi-check2-circle"></i> Check in</button>';
  }
  function rowHtml(r) {
    var pic = r.photo ? '<img class="at-av" src="' + esc(r.photo) + '" alt="" loading="lazy" data-zoom="' + esc(r.photo) + '">' : '<span class="at-av" style="cursor:default">' + esc(r.initials) + '</span>';
    var flag = '';
    if (gift) flag = r.in ? '<span class="at-flag in">Checked in</span>' : '<span class="at-flag out">Not checked in</span>';
    else if (r.status && r.status !== 'Approved') flag = '<span class="at-flag warn">' + esc(r.status) + '</span>';
    var cb = B.locked ? '' : '<input type="checkbox" class="form-check-input at-cb" data-sel="' + r.id + '"' + (sel[r.id] ? ' checked' : '') + ' aria-label="Select ' + esc(r.name) + '">';
    return '<div class="at-row' + (sel[r.id] ? ' is-sel' : '') + '" data-row="' + r.id + '">' + cb + pic +
      '<div class="at-who"><b>' + esc(r.name) + flag + '</b><div class="at-sub"><span class="at-num">' + esc(r.number) + '</span> · ' + esc(r.profession || '—') + '</div></div>' +
      '<div class="at-contact"><div class="at-sub" style="color:var(--ad-ink);font-weight:600">' + esc(r.country || '') + '</div><div class="at-sub">' + esc(r.email || '') + (r.phone ? ' · ' + esc(r.phone) : '') + '</div></div>' +
      '<div class="at-act">' + act(r) + '</div></div>';
  }
  function list() {
    var q = $('at-q').value.trim().toLowerCase();
    return rows.filter(function (r) {
      if (filter === 'done' && !isDone(r)) return false;
      if (filter === 'todo' && isDone(r)) return false;
      if (filter === 'ready' && !(r.in && !r.gift)) return false;
      if (q && (r.name + ' ' + r.number + ' ' + (r.email || '') + ' ' + (r.phone || '') + ' ' + (r.country || '')).toLowerCase().indexOf(q) < 0) return false;
      return true;
    });
  }
  function render() {
    var l = list();
    $('at-count').textContent = l.length + ' of ' + rows.length;
    $('at-list').innerHTML = l.length ? l.slice(0, shown).map(rowHtml).join('') : '<div class="at-empty"><div class="big">' + (rows.length ? '🔍' : '📭') + '</div>' + (rows.length ? 'Nobody matches.' : 'No registrations for this event yet.') + '</div>';
    $('at-more').classList.toggle('d-none', l.length <= shown);
    syncBulk();
  }
  function totals() {
    var d = rows.filter(isDone).length, p = rows.length ? Math.round(d / rows.length * 100) : 0;
    $('at-done').textContent = d; $('at-pct').textContent = p; $('at-bar').style.width = p + '%'; $('at-left').textContent = rows.length - d;
  }
  var t; $('at-q').addEventListener('input', function () { clearTimeout(t); t = setTimeout(function () { shown = 100; render(); }, 150); });
  document.querySelectorAll('[data-f]').forEach(function (b) {
    b.addEventListener('click', function () { filter = b.dataset.f; shown = 100; document.querySelectorAll('[data-f]').forEach(function (x) { x.classList.toggle('on', x === b); }); render(); });
  });
  $('at-more-b').addEventListener('click', function () { shown += 100; render(); });

  function find(id) { return rows.filter(function (r) { return r.id === id; })[0]; }
  function now() { var d = new Date(); return d.getDate() + ' ' + d.toLocaleString('en', { month: 'short' }) + ', ' + ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2); }
  function refreshRow(r) { var el = document.querySelector('[data-row="' + r.id + '"]'); if (el) { el.outerHTML = rowHtml(r); var n = document.querySelector('[data-row="' + r.id + '"]'); if (n) n.classList.add('flash'); } totals(); syncBulk(); }

  /* ---- selection ---- */
  $('at-list').addEventListener('change', function (e) {
    var c = e.target.closest('[data-sel]'); if (!c) return;
    if (c.checked) sel[c.dataset.sel] = 1; else delete sel[c.dataset.sel];
    c.closest('.at-row').classList.toggle('is-sel', c.checked); syncBulk();
  });
  var allBox = $('at-all');
  if (allBox) allBox.addEventListener('change', function () {
    list().slice(0, shown).forEach(function (r) { if (allBox.checked) sel[r.id] = 1; else delete sel[r.id]; });
    render();
  });
  if ($('at-sel-clear')) $('at-sel-clear').addEventListener('click', function () { sel = {}; render(); });
  document.querySelectorAll('[data-bulk]').forEach(function (b) {
    b.addEventListener('click', function () {
      var action = b.dataset.bulk, undo = action === 'uncheck' || action === 'ungive';
      var picked = rows.filter(function (r) { return sel[r.id] && (undo ? isDone(r) : !isDone(r)); });
      if (!picked.length) return;
      var verb = { checkin: 'Check in', uncheck: 'Undo the check-in of', give: 'Give gifts to', ungive: 'Undo the gifts of' }[action];
      Swal.fire({ title: verb + ' ' + picked.length + ' ' + (picked.length === 1 ? 'person' : 'people') + '?', html: picked.slice(0, 6).map(function (r) { return esc(r.name); }).join('<br>') + (picked.length > 6 ? '<br>… and ' + (picked.length - 6) + ' more' : ''),
        icon: undo ? 'warning' : 'question', showCancelButton: true, confirmButtonText: undo ? 'Undo' : 'Yes, ' + verb.toLowerCase(), confirmButtonColor: undo ? '#d33' : undefined })
        .then(function (x) {
          if (!x.isConfirmed) return;
          b.disabled = true;
          post(B.r.bulk, { event: B.event, ids: picked.map(function (r) { return r.id; }), action: action }).then(function (d) {
            b.disabled = false; if (!d) return;
            var t = now();
            picked.forEach(function (r) {
              if (action === 'checkin') { r.in = r.in || t; r.in_how = r.in_how || 'desk'; r.in_by = r.in_by || 'you'; }
              if (action === 'uncheck') { r.in = r.in_how = r.in_by = null; }
              if (action === 'give') { r.gift = true; r.gift_at = r.gift_at || t; r.gift_by = r.gift_by || 'you'; }
              if (action === 'ungive') { r.gift = false; r.gift_at = r.gift_by = null; }
            });
            sel = {}; render(); totals(); toast(d.message, undo ? 'info' : 'success');
          });
        });
    });
  });
  $('at-list').addEventListener('click', function (e) {
    var z = e.target.closest('[data-zoom]');
    if (z) return Swal.fire({ imageUrl: z.dataset.zoom, imageAlt: '', showConfirmButton: false, showCloseButton: true, width: 420 });
    var b = e.target.closest('[data-do]'), u = e.target.closest('[data-undo]');
    if (b) {
      var r = find(+b.dataset.do); b.disabled = true;
      var p = gift ? post(B.r.give.replace('ID', r.id)) : post(B.r.in, { event: B.event, registration_id: r.id });
      p.then(function (d) {
        if (!d) { b.disabled = false; return; }
        if (gift) { r.gift = true; r.gift_at = now(); r.gift_by = 'you'; } else { r.in = now(); r.in_how = 'desk'; r.in_by = 'you'; }
        refreshRow(r); toast(d.message);
      });
    }
    if (u) {
      var r2 = find(+u.dataset.undo);
      Swal.fire({ title: gift ? 'Undo this gift?' : 'Undo this check-in?', text: r2.name, icon: 'warning', showCancelButton: true, confirmButtonText: 'Undo', confirmButtonColor: '#d33' }).then(function (x) {
        if (!x.isConfirmed) return;
        var p2 = gift ? post(B.r.ungive.replace('ID', r2.id)) : post(B.r.out, { event: B.event, registration_id: r2.id });
        p2.then(function (d) { if (!d) return; if (gift) { r2.gift = false; r2.gift_at = r2.gift_by = null; } else { r2.in = r2.in_how = r2.in_by = null; } refreshRow(r2); toast(d.message, 'info'); });
      });
    }
  });
  render();
})();
</script>
@endpush
