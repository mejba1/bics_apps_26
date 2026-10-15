@extends('backend.layouts.master')
@section('title')
ID Cards
@endsection
@push('styles')
<style>
  .ic-kpi { border: 1px solid var(--ad-line); border-radius: 12px; padding: 10px 12px; height: 100%; background: var(--ad-surface); }
  .ic-kpi .n { font-size: 1.4rem; font-weight: 800; line-height: 1; color: var(--ad-ink); }
  .ic-kpi .l { font-size: 12px; color: var(--ad-muted); margin-top: 4px; }
  .ic-rows td { font-size: 13.5px; }
</style>
@endpush
@section('content')
<div class="pagetitle d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div>
    <h1>ID Cards</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li><li class="breadcrumb-item active">ID Cards</li></ol></nav>
  </div>
  <select class="form-select" style="width:auto" onchange="location.href='{{ route('idcards.index') }}?event=' + this.value">
    @foreach($events as $ev)<option value="{{ $ev->id }}" @selected($ev->id === $event->id)>{{ $ev->event_short }}{{ $ev->venue_code !== $ev->event_short ? ' · ' . $ev->venue_code : '' }}{{ $ev->is_current ? ' (current)' : '' }}</option>@endforeach
  </select>
</div>

<section class="section {{ \App\Support\EventLock::isLocked($event) ? 'is-readonly' : '' }}">
  @include('partials.event-lock', ['event' => $event])
  <div class="alert alert-info small py-2"><i class="bi bi-info-circle"></i> An assigned ID card appears in the participant's dashboard under <b>{{ $event->event_short }}</b> (My events), ready to open or download. Nothing is emailed. Participants sign in with the email on their profile; people without a profile or email only get the card from you.</div>
  <div class="row g-2 mb-3" id="ic-kpis"></div>
  <div class="card"><div class="card-body pt-3">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-2">
      <div class="d-flex flex-wrap gap-2">
        <input type="search" class="form-control form-control-sm" id="ic-q" placeholder="Search name, email, number…" style="width:240px">
        <select class="form-select form-select-sm" id="ic-f" style="width:auto">
          <option value="">Everyone registered</option><option value="approved">Approved</option><option value="todo">Approved, not assigned yet</option>
          <option value="assigned">Assigned</option><option value="unassigned">Not assigned</option><option value="checked">Checked in</option>
        </select>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <div class="btn-group btn-group-sm">
          <button class="btn btn-primary" id="ic-assign" disabled data-lockable><i class="bi bi-person-badge"></i> Assign selected (<span>0</span>)</button>
          <button class="btn btn-outline-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" id="ic-more" disabled data-lockable></button>
          <ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item text-danger" id="ic-remove"><i class="bi bi-person-dash"></i> Remove selected from dashboard</button></li></ul>
        </div>
        <button class="btn btn-sm btn-success" id="ic-all"><i class="bi bi-check2-all"></i> Assign to all approved</button>
      </div>
    </div>
    <div class="table-responsive" style="max-height:640px;overflow-y:auto">
      <table class="table table-sm align-middle mb-0 ic-rows">
        <thead class="sticky-top bg-body"><tr><th style="width:30px"><input type="checkbox" class="form-check-input" id="ic-sel-all"></th><th>Attendee</th><th>Registration</th><th class="d-none d-md-table-cell">Dashboard access</th><th>ID card</th><th class="text-end">Action</th></tr></thead>
        <tbody id="ic-body"><tr><td colspan="6" class="text-center text-muted py-4">Loading…</td></tr></tbody>
      </table>
    </div>
  </div></div>
</section>
@endsection
@push('scripts')
<script>
(function () {
  var EVENT = @json($event->id), SHORT = @json($event->event_short), canPdf = @json(auth()->user()->can('registrations.export'));
  var csrf = document.querySelector('meta[name="csrf-token"]').content, rows = [], selected = {};
  var toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2600 });
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function load() {
    fetch(@json(route('idcards.data')) + '?event=' + EVENT, { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) { rows = d.rows; render(); });
  }
  function visible() {
    var s = document.getElementById('ic-q').value.trim().toLowerCase(), f = document.getElementById('ic-f').value;
    return rows.filter(function (r) {
      if (s && [r.name, r.email, r.pin, r.country, r.profile].join(' ').toLowerCase().indexOf(s) < 0) return false;
      if (f === 'approved') return r.status === 'Approved'; if (f === 'todo') return r.status === 'Approved' && !r.assigned_at;
      if (f === 'assigned') return !!r.assigned_at; if (f === 'unassigned') return !r.assigned_at; if (f === 'checked') return r.checked_in;
      return true;
    });
  }
  function kpis() {
    var k = [['Registered', rows.length, ''], ['Approved', rows.filter(function (r) { return r.status === 'Approved'; }).length, 'text-success'],
      ['ID cards assigned', rows.filter(function (r) { return r.assigned_at; }).length, 'text-primary'],
      ['Approved, not assigned', rows.filter(function (r) { return r.status === 'Approved' && !r.assigned_at; }).length, 'text-warning'],
      ['Can sign in to dashboard', rows.filter(function (r) { return r.can_sign_in; }).length, 'text-info'], ['Checked in', rows.filter(function (r) { return r.checked_in; }).length, '']];
    document.getElementById('ic-kpis').innerHTML = k.map(function (x) { return '<div class="col-6 col-md-2"><div class="ic-kpi"><div class="n ' + x[2] + '">' + x[1] + '</div><div class="l">' + x[0] + '</div></div></div>'; }).join('');
  }
  function render() {
    kpis();
    var st = { Approved: 'bg-success', Pending: 'bg-warning text-dark', Rejected: 'bg-danger' };
    document.getElementById('ic-body').innerHTML = visible().slice(0, 3000).map(function (r) {
      return '<tr><td><input type="checkbox" class="form-check-input" data-sel="' + r.id + '"' + (selected[r.id] ? ' checked' : '') + '></td>' +
        '<td><div class="fw-semibold">' + esc(r.name) + '</div><div class="small text-muted">' + esc(r.email || '') + (r.country ? ' · ' + esc(r.country) : '') + '</div></td>' +
        '<td><span class="badge ' + (st[r.status] || 'bg-secondary') + '">' + esc(r.status) + '</span> <span class="small text-muted">#' + esc(r.pin) + '</span>' + (r.checked_in ? '<div class="small text-success"><i class="bi bi-check2-circle"></i> Checked in</div>' : '') + '</td>' +
        '<td class="d-none d-md-table-cell small">' + (r.profile ? (r.can_sign_in ? '<span class="text-success"><i class="bi bi-person-check"></i> ' + esc(r.profile) + '</span>' : '<span class="text-warning"><i class="bi bi-exclamation-circle"></i> ' + esc(r.profile) + ' · no email</span>') : '<span class="text-muted">No profile</span>') + '</td>' +
        '<td>' + (r.assigned_at ? '<span class="badge bg-primary-subtle text-primary-emphasis"><i class="bi bi-person-badge"></i> Assigned</span><div class="small text-muted">' + esc(r.assigned_at) + '</div>' : '<span class="small text-muted">Not assigned</span>') + '</td>' +
        '<td class="text-end text-nowrap">' + (canPdf ? '<a class="btn btn-sm btn-outline-secondary" href="' + esc(r.pdf) + '" target="_blank" title="Open ID card"><i class="bi bi-file-earmark-person"></i></a> ' : '') +
          '<button data-lockable class="btn btn-sm ' + (r.assigned_at ? 'btn-outline-danger' : 'btn-outline-primary') + '" data-one="' + r.id + '" data-on="' + (r.assigned_at ? 0 : 1) + '">' + (r.assigned_at ? 'Remove' : 'Assign') + '</button></td></tr>';
    }).join('') || '<tr><td colspan="6" class="text-center text-muted py-4">Nobody here.</td></tr>';
    document.querySelectorAll('[data-sel]').forEach(function (cb) { cb.addEventListener('change', function () { if (cb.checked) selected[cb.dataset.sel] = 1; else delete selected[cb.dataset.sel]; sel(); }); });
    document.querySelectorAll('[data-one]').forEach(function (b) { b.addEventListener('click', function () { send({ ids: [+b.dataset.one], assign: b.dataset.on === '1' }); }); });
    sel();
  }
  function sel() {
    var n = Object.keys(selected).length, a = document.getElementById('ic-assign');
    a.querySelector('span').textContent = n; a.disabled = !n; document.getElementById('ic-more').disabled = !n;
  }
  function send(body, confirm) {
    function go() {
      body.event = EVENT;
      fetch(@json(route('idcards.assign')) + '?event=' + EVENT, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
        .then(function (res) { if (!res.ok) return Swal.fire('Not done', res.d.message || 'Please try again.', 'warning'); toast.fire({ icon: 'success', title: res.d.message }); if (confirm) selected = {}; load(); });
    }
    if (!confirm) return go();
    Swal.fire(confirm).then(function (r) { if (r.isConfirmed) go(); });
  }
  document.getElementById('ic-assign').addEventListener('click', function () {
    var ids = Object.keys(selected).map(Number), pending = rows.filter(function (r) { return selected[r.id] && r.status !== 'Approved'; }).length;
    send({ ids: ids, assign: true }, { icon: 'question', title: 'Assign ' + ids.length + ' ID card' + (ids.length > 1 ? 's' : '') + '?', html: 'They appear in each participant\'s dashboard under <b>' + esc(SHORT) + '</b>.' + (pending ? '<div class="small text-warning mt-2">' + pending + ' of them are not approved.</div>' : ''), showCancelButton: true, confirmButtonText: 'Assign' });
  });
  document.getElementById('ic-remove').addEventListener('click', function () {
    var ids = Object.keys(selected).map(Number);
    send({ ids: ids, assign: false }, { icon: 'warning', title: 'Remove ' + ids.length + ' ID card' + (ids.length > 1 ? 's' : '') + ' from dashboards?', showCancelButton: true, confirmButtonText: 'Remove', confirmButtonColor: '#d33' });
  });
  document.getElementById('ic-all').addEventListener('click', function () {
    var n = rows.filter(function (r) { return r.status === 'Approved' && !r.assigned_at; }).length;
    if (!n) return Swal.fire('Nothing to assign', 'Every approved attendee already has their ID card.', 'info');
    send({ all_approved: true, assign: true }, { icon: 'question', title: 'Assign ' + n + ' ID card' + (n > 1 ? 's' : '') + '?', text: 'Every approved attendee of ' + SHORT + ' who does not have one yet.', showCancelButton: true, confirmButtonText: 'Assign all' });
  });
  document.getElementById('ic-sel-all').addEventListener('change', function () { var on = this.checked; visible().forEach(function (r) { if (on) selected[r.id] = 1; else delete selected[r.id]; }); render(); });
  document.getElementById('ic-q').addEventListener('input', render);
  document.getElementById('ic-f').addEventListener('change', render);
  load();
})();
</script>
@endpush
