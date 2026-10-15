@extends('backend.layouts.master')
@section('title')
Participants
@endsection
@push('styles')
<style>
  .pt-kpi { border-radius: 12px; padding: 12px 14px; border: 1px solid rgba(0,0,0,.08); cursor: pointer; transition: border-color .15s; }
  .pt-kpi.is-on { border-color: #1861c5; background: rgba(24,97,197,.06); }
  .pt-kpi .n { font-size: 1.5rem; font-weight: 800; line-height: 1; }
  .pt-kpi .l { font-size: 12.5px; color: var(--ad-muted, #6b7489); margin-top: 4px; }
  .pt-av { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; flex: 0 0 40px; background: #e9eff9; color: #1861c5; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; }
  .pt-code { font-family: monospace; font-size: 12px; background: rgba(24,97,197,.08); color: #1861c5; border-radius: 6px; padding: 1px 6px; }
  .pt-ev { font-size: 11px; font-weight: 700; background: rgba(178,46,107,.09); color: #b22e6b; border-radius: 999px; padding: 2px 8px; margin: 1px; display: inline-block; }
  .pt-sort { cursor: pointer; user-select: none; white-space: nowrap; }
  .pt-row.is-off td { opacity: .6; }
</style>
@endpush
@section('content')
<div class="pagetitle d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div>
    <h1>Participants</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li><li class="breadcrumb-item active">Participants</li></ol></nav>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="{{ route('participants.social-icons') }}"><i class="bi bi-share"></i> Social icons</a>
    <button class="btn btn-outline-secondary" id="pt-portal"><i class="bi bi-person-gear"></i> Dashboard settings</button>
    @if(auth()->user()->isSuperAdmin())<button class="btn btn-outline-secondary" id="pt-links"><i class="bi bi-link-45deg"></i> Link settings</button>@endif
    <a href="#" class="btn btn-outline-primary" id="pt-export"><i class="bi bi-filetype-csv"></i> Export CSV</a>
    <a href="{{ route('participants.create') }}" class="btn btn-primary"><i class="bi bi-person-plus"></i> Add participant</a>
  </div>
</div>

<section class="section">
  @include('backend.pages.participants.link-warning')
  <div class="row g-2 mb-3">
    @foreach(['' => ['All', $counts['all'], ''], 'active' => ['Active profiles', $counts['active'], 'text-success'], 'suspended' => ['Suspended', $counts['suspended'], 'text-warning'], 'trashed' => ['Trash', $counts['trashed'], 'text-danger']] as $k => [$label, $n, $cls])
    <div class="col-6 col-md-3"><div class="pt-kpi {{ $k === '' ? 'is-on' : '' }}" data-status="{{ $k }}"><div class="n {{ $cls }}">{{ number_format($n) }}</div><div class="l">{{ $label }}</div></div></div>
    @endforeach
  </div>

  <div class="card">
    <div class="card-body pt-3">
      <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-2">
        <div class="d-flex flex-wrap gap-2">
          <input type="search" class="form-control form-control-sm" id="pt-q" placeholder="Search name, ID, email, organization…" style="width:280px">
          <select class="form-select form-select-sm" id="pt-event" style="width:auto" aria-label="Event"><option value="">All events</option>@foreach($events as $ev)<option value="{{ $ev->id }}" @selected((string) $ev->id === $selectedEvent)>{{ $ev->event_short }}{{ $ev->venue_code !== $ev->event_short ? ' · ' . $ev->venue_code : '' }}{{ $ev->id === $pinnedId ? ' 📌' : '' }}</option>@endforeach</select>
          <span class="badge rounded-pill align-self-center {{ $selectedEvent !== '' && (int) $selectedEvent === $pinnedId ? '' : 'd-none' }}" id="pt-pinned" style="background:var(--ad-accent-50);color:var(--ad-accent);font-weight:700" title="Your pinned event — change it at the top of the page"><i class="bi bi-pin-angle-fill"></i> Pinned event</span>
          <select class="form-select form-select-sm" id="pt-photo" style="width:auto"><option value="">Photo: any</option><option value="yes">With photo</option><option value="no">Without photo</option></select>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center" id="pt-bulk" style="visibility:hidden">
          <span class="small text-muted"><b id="pt-selcount">0</b> selected</span>
          <button class="btn btn-sm btn-outline-success" data-bulk="activate"><i class="bi bi-eye"></i> Activate</button>
          <button class="btn btn-sm btn-outline-warning" data-bulk="suspend"><i class="bi bi-eye-slash"></i> Suspend</button>
          @can('participants.delete')
          <button class="btn btn-sm btn-outline-danger" data-bulk="delete"><i class="bi bi-trash"></i> Delete</button>
          <button class="btn btn-sm btn-outline-secondary" data-bulk="restore"><i class="bi bi-arrow-counterclockwise"></i> Restore</button>
          @endcan
        </div>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th style="width:30px"><input type="checkbox" class="form-check-input" id="pt-all"></th>
              <th class="pt-sort" data-sort="name">Participant <i class="bi bi-arrow-down-up small"></i></th>
              <th class="pt-sort d-none d-md-table-cell" data-sort="organization">Organization <i class="bi bi-arrow-down-up small"></i></th>
              <th class="d-none d-lg-table-cell">Events</th>
              <th class="text-end d-none d-md-table-cell">Views</th>
              <th>Status</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody id="pt-body"><tr><td colspan="7" class="text-center text-muted py-4">Loading…</td></tr></tbody>
        </table>
      </div>
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
        <span class="small text-muted" id="pt-info"></span>
        <div class="d-flex gap-2 align-items-center">
          <select class="form-select form-select-sm" id="pt-per" style="width:auto"><option>25</option><option>50</option><option>100</option></select>
          <div class="btn-group btn-group-sm"><button class="btn btn-outline-secondary" id="pt-prev"><i class="bi bi-chevron-left"></i></button><button class="btn btn-outline-secondary" disabled id="pt-page">1 / 1</button><button class="btn btn-outline-secondary" id="pt-next"><i class="bi bi-chevron-right"></i></button></div>
        </div>
      </div>
    </div>
  </div>
</section>
@php $links = \App\Support\PublicLinks::settings(); @endphp
<div class="modal fade" id="ptLinks" tabindex="-1"><div class="modal-dialog"><form class="modal-content" id="pt-links-form" novalidate>
  <div class="modal-header"><h5 class="modal-title">Link settings</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <p class="small text-muted">The web address used in profile links, QR codes, vCards and NFC card links.</p>
    <label class="d-flex gap-2 border rounded-3 p-2 mb-2"><input class="form-check-input mt-1" type="radio" name="mode" value="auto" @checked($links['mode'] === 'auto')>
      <span><b>Automatic</b> — the address this site runs on<br><small class="text-muted">Now: <code>{{ rtrim(url('/'), '/') }}</code>. On the server it becomes the server address by itself.</small></span></label>
    <label class="d-flex gap-2 border rounded-3 p-2"><input class="form-check-input mt-1" type="radio" name="mode" value="custom" @checked($links['mode'] === 'custom')>
      <span class="flex-grow-1"><b>Custom address</b> — always use this address<br>
        <input type="url" class="form-control form-control-sm mt-1" name="base_url" value="{{ $links['base_url'] }}" placeholder="https://bics.org"></span></label>
    <div class="alert alert-light border small mt-3 mb-0"><i class="bi bi-info-circle"></i> Printed QR codes and written NFC chips keep the address they were made with. Choose the final address before printing or encoding real cards.</div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
</form></div></div>
@php $access = \App\Support\ParticipantAccess::settings(); @endphp
<div class="modal fade" id="ptPortal" tabindex="-1"><div class="modal-dialog modal-lg"><form class="modal-content" id="pt-portal-form">
  <div class="modal-header"><h5 class="modal-title">Participant dashboard</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <p class="small text-muted">Participants sign in at <a href="{{ route('my.login') }}" target="_blank">{{ route('my.login') }}</a> with their registered email — they get a one-time link and code by email (no passwords). There they see their profile, QR code, card status and visits, and can edit what you allow.</p>
    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="portal" id="pp-portal" @checked($access['portal'])><label class="form-check-label" for="pp-portal"><b>Participants can sign in</b> to their dashboard</label></div>
    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="edit" id="pp-edit" @checked($access['edit'])><label class="form-check-label" for="pp-edit"><b>Participants can edit</b> their profile <span class="text-muted">(each profile also has its own switch)</span></label></div>
    <div class="fw-semibold small mb-1">Locked for everyone <span class="text-muted fw-normal">— only admins can change these</span></div>
    <div class="row g-1">
      @foreach(\App\Support\ParticipantAccess::EDITABLE as $k => $label)
      <div class="col-sm-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="locked" value="{{ $k }}" id="pl-{{ $k }}" @checked(in_array($k, $access['locked'], true))><label class="form-check-label small" for="pl-{{ $k }}">{{ $label }}</label></div></div>
      @endforeach
    </div>
    <div class="small text-muted mt-2">Email can never be changed by participants (it is how they sign in and links their registrations). Name and category are locked by default because they are printed on cards and certificates.</div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
</form></div></div>
@endsection

@push('scripts')
<script>
(function () {
  'use strict';
  var urls = { data: @json(route('participants.data')), bulk: @json(route('participants.bulk')), export: @json(route('participants.export')) };
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2500 });
  var pinnedId = @json($pinnedId ? (string) $pinnedId : '');
  var state = { q: '', event: @json($selectedEvent), photo: '', status: '', sort: 'code', dir: 'asc', page: 1, per: 25 };
  var rows = [], selected = {}, timer = null;
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
  function params(extra) { var p = Object.assign({}, state, extra || {}); return Object.keys(p).filter(function (k) { return p[k] !== '' && p[k] != null; }).map(function (k) { return k + '=' + encodeURIComponent(p[k]); }).join('&'); }

  function load() {
    document.getElementById('pt-export').href = urls.export + '?' + params();
    fetch(urls.data + '?' + params(), { headers: { 'Accept': 'application/json' } }).then(function (r) { if (!r.ok) throw r.status; return r.json(); }).then(function (d) {
      rows = d.rows;
      if (d.counts) document.querySelectorAll('.pt-kpi').forEach(function (k) { var n = d.counts[k.dataset.status || 'all']; if (n != null) k.querySelector('.n').textContent = Number(n).toLocaleString(); });
      document.getElementById('pt-info').textContent = d.total ? 'Showing ' + d.from + '–' + d.to + ' of ' + d.total.toLocaleString() : 'No participants';
      document.getElementById('pt-page').textContent = d.page + ' / ' + Math.max(1, d.pages);
      document.getElementById('pt-prev').disabled = d.page <= 1;
      document.getElementById('pt-next').disabled = d.page >= d.pages;
      render();
    }).catch(function (s) {
      document.getElementById('pt-body').innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">Could not load participants' + (s === 401 || s === 419 ? ': please log in again.' : '. Please reload the page.') + '</td></tr>';
    });
  }
  function render() {
    var body = document.getElementById('pt-body');
    body.innerHTML = rows.map(function (p) {
      var status = p.trashed ? '<span class="badge bg-danger">In trash</span>' : (p.status === 'active' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-warning text-dark">Suspended</span>');
      return '<tr class="pt-row ' + (p.status !== 'active' || p.trashed ? 'is-off' : '') + '">' +
        '<td><input type="checkbox" class="form-check-input" data-sel="' + p.id + '" ' + (selected[p.id] ? 'checked' : '') + '></td>' +
        '<td><div class="d-flex align-items-center gap-2">' + (p.photo ? '<img class="pt-av" src="' + esc(p.photo) + '" alt="">' : '<span class="pt-av">' + esc(p.initials) + '</span>') +
          '<div><a class="fw-semibold" href="' + esc(p.edit) + '">' + esc(p.name) + '</a><div class="small text-muted"><span class="pt-code">' + esc(p.code) + '</span> ' + esc(p.designation || '') + '</div></div></div></td>' +
        '<td class="d-none d-md-table-cell small">' + esc(p.organization || '—') + (p.country ? '<div class="text-muted">' + esc(p.country) + '</div>' : '') + '</td>' +
        '<td class="d-none d-lg-table-cell">' + p.events.map(function (e) { return '<span class="pt-ev">' + esc(e) + '</span>'; }).join('') + '</td>' +
        '<td class="text-end d-none d-md-table-cell">' + p.views + '</td>' +
        '<td>' + status + '</td>' +
        '<td class="text-end text-nowrap">' +
          (p.status === 'active' && !p.trashed ? '<a class="btn btn-sm btn-outline-secondary" href="' + esc(p.url) + '" target="_blank" title="Open public profile"><i class="bi bi-box-arrow-up-right"></i></a> ' : '') +
          '<a class="btn btn-sm btn-outline-primary" href="' + esc(p.edit) + '" title="Edit"><i class="bi bi-pencil"></i></a></td></tr>';
    }).join('') || '<tr><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-people fs-3 d-block mb-2"></i>No participants match.</td></tr>';
    body.querySelectorAll('[data-sel]').forEach(function (cb) { cb.addEventListener('change', function () { if (cb.checked) selected[cb.dataset.sel] = true; else delete selected[cb.dataset.sel]; sel(); }); });
    document.getElementById('pt-all').checked = rows.length && rows.every(function (p) { return selected[p.id]; });
    sel();
  }
  function sel() {
    var n = Object.keys(selected).length;
    document.getElementById('pt-selcount').textContent = n;
    document.getElementById('pt-bulk').style.visibility = n ? 'visible' : 'hidden';
  }
  function reload(resetPage) { if (resetPage) state.page = 1; load(); }

  document.getElementById('pt-q').addEventListener('input', function () { clearTimeout(timer); var v = this.value; timer = setTimeout(function () { state.q = v.trim(); reload(true); }, 300); });
  document.getElementById('pt-event').addEventListener('change', function () {
    state.event = this.value;
    document.getElementById('pt-pinned').classList.toggle('d-none', !(state.event && state.event === pinnedId));
    var u = new URL(location.href); u.searchParams.set('event', state.event || 'all'); history.replaceState(null, '', u);   // stays after a reload
    reload(true);
  });
  document.getElementById('pt-photo').addEventListener('change', function () { state.photo = this.value; reload(true); });
  document.getElementById('pt-per').addEventListener('change', function () { state.per = this.value; reload(true); });
  document.getElementById('pt-prev').addEventListener('click', function () { state.page--; load(); });
  document.getElementById('pt-next').addEventListener('click', function () { state.page++; load(); });
  document.querySelectorAll('.pt-kpi').forEach(function (k) {
    k.addEventListener('click', function () {
      document.querySelectorAll('.pt-kpi').forEach(function (x) { x.classList.toggle('is-on', x === k); });
      state.status = k.dataset.status; selected = {}; reload(true);
    });
  });
  document.querySelectorAll('.pt-sort').forEach(function (th) {
    th.addEventListener('click', function () { state.dir = state.sort === th.dataset.sort && state.dir === 'asc' ? 'desc' : 'asc'; state.sort = th.dataset.sort; reload(true); });
  });
  document.getElementById('pt-all').addEventListener('change', function () { var on = this.checked; rows.forEach(function (p) { if (on) selected[p.id] = true; else delete selected[p.id]; }); render(); });
  document.querySelectorAll('[data-bulk]').forEach(function (b) {
    b.addEventListener('click', function () {
      var ids = Object.keys(selected).map(Number), action = b.dataset.bulk;
      var words = { activate: 'Activate', suspend: 'Suspend', delete: 'Move to trash', restore: 'Restore' };
      Swal.fire({ icon: action === 'delete' ? 'warning' : 'question', title: words[action] + ' ' + ids.length + ' participant' + (ids.length > 1 ? 's' : '') + '?',
        text: action === 'suspend' ? 'Their public profiles will show "not available". QR codes and NFC cards keep the same address.' : (action === 'delete' ? 'They can be restored from the Trash.' : ''),
        showCancelButton: true, confirmButtonText: words[action], confirmButtonColor: action === 'delete' ? '#d33' : undefined })
        .then(function (r) {
          if (!r.isConfirmed) return;
          fetch(urls.bulk, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ ids: ids, action: action }) })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) { if (!res.ok) return Swal.fire('Not done', res.d.message || 'Please try again.', 'warning'); selected = {}; toast.fire({ icon: 'success', title: res.d.message }); load(); });
        });
    });
  });
  var linksBtn = document.getElementById('pt-links');
  if (linksBtn) {
    var linksModal = new bootstrap.Modal(document.getElementById('ptLinks'));
    linksBtn.addEventListener('click', function () { linksModal.show(); });
    document.getElementById('pt-links-form').addEventListener('submit', function (e) {
      e.preventDefault(); var f = e.target;
      fetch(@json(route('participants.link-settings')), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ mode: f.querySelector('input[name=mode]:checked').value, base_url: f.base_url.value.trim() }) })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
        .then(function (res) {
          if (!res.ok) { var er = res.d.errors || {}; var k = Object.keys(er)[0]; return Swal.fire('Not saved', k ? er[k][0] : (res.d.message || 'Please try again.'), 'warning'); }
          linksModal.hide(); Swal.fire({ icon: 'success', title: 'Saved', text: res.d.message }).then(function () { location.reload(); });
        });
    });
  }
  var portalModal = new bootstrap.Modal(document.getElementById('ptPortal'));
  document.getElementById('pt-portal').addEventListener('click', function () { portalModal.show(); });
  document.getElementById('pt-portal-form').addEventListener('submit', function (e) {
    e.preventDefault(); var f = e.target;
    fetch(@json(route('participants.dashboard-settings')), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ portal: f.portal.checked, edit: f.edit.checked, locked: Array.prototype.map.call(f.querySelectorAll('[name=locked]:checked'), function (c) { return c.value; }) }) })
      .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (res) { if (!res.ok) return Swal.fire('Not saved', res.d.message || 'Please try again.', 'warning'); portalModal.hide(); toast.fire({ icon: 'success', title: res.d.message }); });
  });
  load();
})();
</script>
@endpush
