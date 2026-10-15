@extends('backend.layouts.master')
@section('title')
NFC Cards
@endsection
@push('styles')
<style>
  .nc-kpi { border-radius: 12px; padding: 10px 12px; border: 1px solid rgba(0,0,0,.08); cursor: pointer; height: 100%; }
  .nc-kpi.is-on { border-color: #1861c5; background: rgba(24,97,197,.06); }
  .nc-kpi .n { font-size: 1.4rem; font-weight: 800; line-height: 1; }
  .nc-kpi .l { font-size: 12px; color: var(--ad-muted, #6b7489); margin-top: 4px; }
  .nc-no { font-family: monospace; font-weight: 700; }
  .nc-av { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; background: #e9eff9; color: #1861c5; font-weight: 800; font-size: 12px; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 34px; }
</style>
@endpush
@section('content')
<div class="pagetitle d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div>
    <h1>NFC Cards</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li><li class="breadcrumb-item active">NFC Cards</li></ol></nav>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a href="{{ route('nfc.export', $selectedEvent ? ['event' => $selectedEvent] : []) }}" class="btn btn-outline-primary" id="nc-export"><i class="bi bi-filetype-csv"></i> Export CSV</a>
    <button class="btn btn-outline-primary" id="nc-event-btn"><i class="bi bi-people"></i> Cards for an event</button>
    <button class="btn btn-outline-primary" id="nc-stock-btn"><i class="bi bi-box-seam"></i> Add blank cards</button>
    <a href="{{ route('nfc.station') }}" class="btn btn-primary"><i class="bi bi-broadcast"></i> Encoding station</a>
  </div>
</div>

<section class="section">
  @include('backend.pages.participants.link-warning')
  <div class="alert alert-info small py-2"><i class="bi bi-info-circle"></i> Each card stores its own link (<code>{{ \App\Support\PublicLinks::base() }}/n/…</code>). While the card is <b>active</b>, a tap opens its participant's profile. Lost cards can be switched off and cards can be reassigned without rewriting the chip. The QR code printed on the card is the participant's permanent profile link.</div>
  <div class="row g-2 mb-3" id="nc-kpis"></div>
  <div class="card">
    <div class="card-body pt-3">
      <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-2">
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <input type="search" class="form-control form-control-sm" id="nc-q" placeholder="Search card no., UID, participant…" style="width:280px">
          <select class="form-select form-select-sm" id="nc-event" style="width:auto" aria-label="Event"><option value="">All events</option>@foreach($events as $ev)<option value="{{ $ev->id }}" @selected((string) $ev->id === $selectedEvent)>{{ $ev->event_short }}{{ $ev->venue_code !== $ev->event_short ? ' · ' . $ev->venue_code : '' }}{{ $ev->id === $pinnedId ? ' 📌' : '' }}</option>@endforeach</select>
          <span class="badge rounded-pill {{ $selectedEvent !== '' && (int) $selectedEvent === $pinnedId ? '' : 'd-none' }}" id="nc-pinned" style="background:var(--ad-accent-50);color:var(--ad-accent);font-weight:700" title="Your pinned event — change it at the top of the page"><i class="bi bi-pin-angle-fill"></i> Pinned event</span>
        </div>
        <div class="d-flex gap-2 align-items-center" id="nc-bulk" style="visibility:hidden">
          <span class="small text-muted"><b id="nc-sel">0</b> selected</span>
          <button class="btn btn-sm btn-outline-success" data-bulk="printed"><i class="bi bi-printer"></i> Mark printed</button>
          <button class="btn btn-sm btn-outline-secondary" data-bulk="unprinted">Not printed</button>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th style="width:30px"><input type="checkbox" class="form-check-input" id="nc-all"></th><th>Card</th><th>Participant</th><th>Status</th><th class="d-none d-md-table-cell">Chip</th><th class="d-none d-lg-table-cell">Printed</th><th class="text-end">Action</th></tr></thead>
          <tbody id="nc-body"><tr><td colspan="7" class="text-center text-muted py-4">Loading…</td></tr></tbody>
        </table>
      </div>
      <div class="d-flex justify-content-between align-items-center mt-3"><span class="small text-muted" id="nc-info"></span>
        <div class="btn-group btn-group-sm"><button class="btn btn-outline-secondary" id="nc-prev"><i class="bi bi-chevron-left"></i></button><button class="btn btn-outline-secondary" disabled id="nc-page">1 / 1</button><button class="btn btn-outline-secondary" id="nc-next"><i class="bi bi-chevron-right"></i></button></div></div>
    </div>
  </div>
</section>

<div class="modal fade" id="ncStock" tabindex="-1"><div class="modal-dialog"><form class="modal-content" id="nc-stock-form">
  <div class="modal-header"><h5 class="modal-title">Add blank cards to stock</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <label class="form-label fw-semibold">How many cards?</label><input type="number" class="form-control mb-3" name="count" min="0" max="2000" value="50">
    <label class="form-label fw-semibold">Or paste chip UIDs <span class="text-muted fw-normal">(optional, one per line — from your card supplier)</span></label>
    <textarea class="form-control" name="uids" rows="4" placeholder="04:A2:3B:1C:55:80:00"></textarea>
    <div class="form-text">Each card gets a number (NFC-000001…) and its own link. UIDs can also be read later while encoding.</div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Add to stock</button></div>
</form></div></div>

<div class="modal fade" id="ncEvent" tabindex="-1"><div class="modal-dialog"><form class="modal-content" id="nc-event-form">
  <div class="modal-header"><h5 class="modal-title">Cards for everyone in an event</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <label class="form-label fw-semibold">Event</label>
    <select class="form-select" name="event_id">@foreach($events as $ev)<option value="{{ $ev->id }}" @selected($ev->id === $pinnedId)>{{ $ev->event_short }}{{ $ev->venue_code !== $ev->event_short ? ' · ' . $ev->venue_code : '' }}</option>@endforeach</select>
    <div class="form-text">Every active participant of the event who has no card yet gets one from stock (new cards are added when stock runs out). Then encode them at the encoding station.</div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Assign cards</button></div>
</form></div></div>
@endsection

@push('scripts')
<script>
(function () {
  'use strict';
  var urls = { data: @json(route('nfc.data')), stock: @json(route('nfc.stock')), event: @json(route('nfc.assign-event')), bulk: @json(route('nfc.bulk')) };
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var STATUS = @json(\App\Models\NfcCard::STATUS), ENCODE = @json(\App\Models\NfcCard::ENCODE), VERIFY = @json(\App\Models\NfcCard::VERIFY);
  var pinnedId = @json($pinnedId ? (string) $pinnedId : '');
  var state = { q: '', filter: '', event: @json($selectedEvent), page: 1 }, rows = [], selected = {}, timer = null;
  document.getElementById('nc-event').addEventListener('change', function () {
    state.event = this.value; state.page = 1;
    document.getElementById('nc-pinned').classList.toggle('d-none', !(state.event && state.event === pinnedId));
    var x = document.getElementById('nc-export'); x.href = @json(route('nfc.export')) + (state.event ? '?event=' + state.event : '');
    var u = new URL(location.href); u.searchParams.set('event', state.event || 'all'); history.replaceState(null, '', u);   // stays after a reload
    load();
  });
  var KPIS = [['', 'All cards', 'total', ''], ['available', 'In stock', 'available', ''], ['to_encode', 'To encode', 'to_encode', 'text-primary'], ['to_verify', 'To verify', 'to_verify', 'text-info'],
    ['to_activate', 'To activate', 'to_activate', 'text-warning'], ['active', 'Active', 'active', 'text-success'], ['to_print', 'Not printed', 'to_print', ''], ['off', 'Switched off', 'off', 'text-danger']];
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
  function badge(set, k) { return '<span class="badge bg-' + set[k][1] + '">' + esc(set[k][0]) + '</span>'; }
  function post(url, body) {
    return fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
      .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (res) { if (!res.ok) { var e = res.d.errors || {}; var k = Object.keys(e)[0]; Swal.fire('Not done', k ? e[k][0] : (res.d.message || 'Please try again.'), 'warning'); return null; } return res.d; });
  }
  function kpis(c) {
    document.getElementById('nc-kpis').innerHTML = KPIS.map(function (k) {
      return '<div class="col-6 col-md-3 col-xl"><div class="nc-kpi ' + (state.filter === k[0] ? 'is-on' : '') + '" data-f="' + k[0] + '"><div class="n ' + k[3] + '">' + (c[k[2]] || 0) + '</div><div class="l">' + k[1] + '</div></div></div>';
    }).join('');
    document.querySelectorAll('.nc-kpi').forEach(function (el) { el.addEventListener('click', function () { state.filter = el.dataset.f; state.page = 1; load(); }); });
  }
  function load() {
    fetch(urls.data + '?' + new URLSearchParams(state).toString(), { headers: { 'Accept': 'application/json' } }).then(function (r) { if (!r.ok) throw r.status; return r.json(); }).then(function (d) {
      rows = d.rows; kpis(d.counts);
      document.getElementById('nc-info').textContent = d.total ? 'Showing ' + d.from + '–' + d.to + ' of ' + d.total : '';
      document.getElementById('nc-page').textContent = d.page + ' / ' + Math.max(1, d.pages);
      document.getElementById('nc-prev').disabled = d.page <= 1; document.getElementById('nc-next').disabled = d.page >= d.pages;
      render();
    }).catch(function () { document.getElementById('nc-body').innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">Could not load cards. Please reload the page.</td></tr>'; });
  }
  function render() {
    document.getElementById('nc-body').innerHTML = rows.map(function (c) {
      var p = c.participant;
      return '<tr><td><input type="checkbox" class="form-check-input" data-sel="' + c.id + '" ' + (selected[c.id] ? 'checked' : '') + '></td>' +
        '<td><a class="nc-no" href="' + esc(c.show) + '">' + esc(c.card_no) + '</a>' + (c.uid ? '<div class="small text-muted"><code>' + esc(c.uid) + '</code></div>' : '') + '</td>' +
        '<td>' + (p ? '<div class="d-flex align-items-center gap-2">' + (p.photo ? '<img class="nc-av" src="' + esc(p.photo) + '" alt="">' : '<span class="nc-av">' + esc(p.initials) + '</span>') + '<div><a class="fw-semibold" href="' + esc(p.edit) + '">' + esc(p.name) + '</a><div class="small text-muted">' + esc(p.code) + '</div></div></div>' : '<span class="text-muted small">— in stock —</span>') + '</td>' +
        '<td>' + badge(STATUS, c.status) + '</td>' +
        '<td class="d-none d-md-table-cell">' + badge(ENCODE, c.encode_status) + ' ' + (c.encode_status === 'encoded' ? badge(VERIFY, c.verify_status) : '') + (c.locked ? ' <i class="bi bi-lock" title="Locked"></i>' : '') + '</td>' +
        '<td class="d-none d-lg-table-cell">' + (c.print_status === 'printed' ? '<i class="bi bi-printer text-success"></i>' : '<span class="text-muted small">No</span>') + '</td>' +
        '<td class="text-end"><a class="btn btn-sm btn-outline-primary" href="' + esc(c.show) + '">Open</a></td></tr>';
    }).join('') || '<tr><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-credit-card-2-front fs-3 d-block mb-2"></i>No cards here. Add blank cards to stock, or give cards to an event.</td></tr>';
    document.querySelectorAll('[data-sel]').forEach(function (cb) { cb.addEventListener('change', function () { if (cb.checked) selected[cb.dataset.sel] = 1; else delete selected[cb.dataset.sel]; sel(); }); });
    sel();
  }
  function sel() { var n = Object.keys(selected).length; document.getElementById('nc-sel').textContent = n; document.getElementById('nc-bulk').style.visibility = n ? 'visible' : 'hidden'; }
  document.getElementById('nc-all').addEventListener('change', function () { var on = this.checked; rows.forEach(function (c) { if (on) selected[c.id] = 1; else delete selected[c.id]; }); render(); });
  document.getElementById('nc-q').addEventListener('input', function () { clearTimeout(timer); var v = this.value; timer = setTimeout(function () { state.q = v.trim(); state.page = 1; load(); }, 300); });
  document.getElementById('nc-prev').addEventListener('click', function () { state.page--; load(); });
  document.getElementById('nc-next').addEventListener('click', function () { state.page++; load(); });
  document.querySelectorAll('[data-bulk]').forEach(function (b) { b.addEventListener('click', function () { post(urls.bulk, { ids: Object.keys(selected).map(Number), action: b.dataset.bulk }).then(function (d) { if (d) { selected = {}; Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: d.message, showConfirmButton: false, timer: 2000 }); load(); } }); }); });

  var stockModal = new bootstrap.Modal(document.getElementById('ncStock')), eventModal = new bootstrap.Modal(document.getElementById('ncEvent'));
  document.getElementById('nc-stock-btn').addEventListener('click', function () { stockModal.show(); });
  document.getElementById('nc-event-btn').addEventListener('click', function () { eventModal.show(); });
  document.getElementById('nc-stock-form').addEventListener('submit', function (e) {
    e.preventDefault(); var f = e.target;
    post(urls.stock, { count: parseInt(f.count.value || '0', 10), uids: f.uids.value }).then(function (d) { if (d) { stockModal.hide(); f.uids.value = ''; Swal.fire('Done', d.message, 'success'); state.filter = 'available'; load(); } });
  });
  document.getElementById('nc-event-form').addEventListener('submit', function (e) {
    e.preventDefault(); var btn = e.target.querySelector('[type=submit]'); btn.disabled = true;
    post(urls.event, { event_id: +e.target.event_id.value }).then(function (d) { btn.disabled = false; if (d) { eventModal.hide(); Swal.fire('Done', d.message, 'success'); state.filter = 'to_encode'; load(); } });
  });
  load();
})();
</script>
@endpush
