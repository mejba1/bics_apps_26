@extends('backend.layouts.master')
@section('title')
Audit Log
@endsection
@push('styles')
<style>
  .au-row { cursor: pointer; }
  .au-row td { vertical-align: top; font-size: 13.5px; }
  .au-when { white-space: nowrap; color: var(--ad-muted); font-size: 12.5px; }
  .au-act { font-weight: 600; color: var(--ad-ink); }
  .au-area { display: inline-block; font-size: 11.5px; font-weight: 700; border-radius: 6px; padding: 1px 7px; background: var(--ad-chip); color: var(--ad-muted); }
  .au-user { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; }
  .au-user i { width: 26px; height: 26px; border-radius: 50%; background: rgba(91,155,255,.16); color: var(--ad-brand); display: inline-flex; align-items: center; justify-content: center; font-style: normal; font-size: 11px; font-weight: 800; }
  .au-detail td { background: var(--ad-subtle); font-size: 12.5px; }
  .au-kv { display: grid; grid-template-columns: minmax(120px, 220px) 1fr; gap: 3px 12px; }
  .au-kv span { color: var(--ad-muted); } .au-kv code { color: var(--ad-ink); white-space: pre-wrap; word-break: break-word; }
  .au-bad { color: var(--ad-red); font-weight: 700; }
</style>
@endpush
@section('content')
<div class="pagetitle d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div>
    <h1>Audit Log</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li><li class="breadcrumb-item active">Audit Log</li></ol></nav>
  </div>
  <a class="btn btn-outline-primary" id="au-export" href="{{ route('audit.export') }}"><i class="bi bi-filetype-csv"></i> Export CSV</a>
</div>
<section class="section">
  <div class="alert alert-info small py-2"><i class="bi bi-info-circle"></i> Every change made in the admin area is recorded here with who made it, the record and the fields sent (passwords and other secrets are never stored), plus admin sign-ins and failed sign-ins. Entries are kept for {{ $keep ? $keep . ' days' : 'ever' }} (Platform settings). Participants' own profile edits are in each profile's history.</div>
  <div class="card"><div class="card-body pt-3">
    <form class="row g-2 align-items-end mb-3" id="au-f">
      <div class="col-md-3"><label class="form-label small mb-1">Search</label><input type="search" class="form-control form-control-sm" name="q" placeholder="Action, record, name, IP…"></div>
      <div class="col-md-2 col-6"><label class="form-label small mb-1">User</label><select class="form-select form-select-sm" name="user"><option value="">Everyone</option>@foreach($users as $u)<option value="{{ $u->user_id }}">{{ $u->user_name }}</option>@endforeach</select></div>
      <div class="col-md-2 col-6"><label class="form-label small mb-1">Area</label><select class="form-select form-select-sm" name="area"><option value="">All areas</option>@foreach($areas as $a)<option>{{ $a }}</option>@endforeach</select></div>
      <div class="col-md-2 col-6"><label class="form-label small mb-1">From</label><input type="date" class="form-control form-control-sm" name="from"></div>
      <div class="col-md-2 col-6"><label class="form-label small mb-1">To</label><input type="date" class="form-control form-control-sm" name="to"></div>
      <div class="col-md-1"><div class="form-check form-switch mb-1"><input class="form-check-input" type="checkbox" name="problems" id="au-p"><label class="form-check-label small" for="au-p">Refused only</label></div></div>
    </form>
    <div class="table-responsive"><table class="table table-sm mb-0">
      <thead><tr><th>When</th><th>User</th><th>Action</th><th class="d-none d-lg-table-cell">Record</th><th class="d-none d-md-table-cell">IP address</th></tr></thead>
      <tbody id="au-body"><tr><td colspan="5" class="text-center text-muted py-4">Loading…</td></tr></tbody>
    </table></div>
    <div class="d-flex justify-content-between align-items-center mt-3"><span class="small text-muted" id="au-info"></span>
      <div class="btn-group btn-group-sm"><button class="btn btn-outline-secondary" id="au-prev"><i class="bi bi-chevron-left"></i></button><button class="btn btn-outline-secondary" disabled id="au-page">1 / 1</button><button class="btn btn-outline-secondary" id="au-next"><i class="bi bi-chevron-right"></i></button></div></div>
  </div></div>
</section>
@endsection
@push('scripts')
<script>
(function () {
  var f = document.getElementById('au-f'), page = 1, pages = 1, timer, rows = [];
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function params() {
    var p = new URLSearchParams();
    ['q', 'user', 'area', 'from', 'to'].forEach(function (k) { if (f.elements[k].value) p.set(k, f.elements[k].value); });
    if (f.elements.problems.checked) p.set('problems', 1);
    return p;
  }
  function load() {
    var p = params(); p.set('page', page);
    document.getElementById('au-export').href = @json(route('audit.export')) + '?' + params().toString();
    fetch(@json(route('audit.data')) + '?' + p.toString(), { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
      rows = d.rows; page = d.page; pages = d.pages;
      document.getElementById('au-info').textContent = d.total.toLocaleString() + ' entries';
      document.getElementById('au-page').textContent = page + ' / ' + pages;
      document.getElementById('au-prev').disabled = page <= 1; document.getElementById('au-next').disabled = page >= pages;
      document.getElementById('au-body').innerHTML = rows.map(function (r, i) {
        var bad = r.status >= 400 || r.action === 'Failed sign-in';
        return '<tr class="au-row" data-i="' + i + '"><td class="au-when" title="' + esc(r.at) + '">' + esc(r.at) + '<br><small>' + esc(r.ago) + '</small></td>' +
          '<td>' + (r.user ? '<span class="au-user"><i>' + esc(r.user.split(' ').map(function (w) { return w[0]; }).join('').slice(0, 2).toUpperCase()) + '</i>' + esc(r.user) + '</span>' : '<span class="text-muted">—</span>') + '</td>' +
          '<td><span class="au-area">' + esc(r.area) + '</span> <span class="au-act ' + (bad ? 'au-bad' : '') + '">' + esc(r.action) + '</span>' + (r.status === 403 ? ' <span class="badge bg-danger">refused</span>' : '') +
          (r.subject ? '<div class="small text-muted d-lg-none">' + esc(r.subject) + '</div>' : '') + '</td>' +
          '<td class="d-none d-lg-table-cell small">' + esc(r.subject || '') + '</td><td class="d-none d-md-table-cell small text-muted">' + esc(r.ip || '') + '</td></tr>';
      }).join('') || '<tr><td colspan="5" class="text-center text-muted py-4">No entries.</td></tr>';
      document.querySelectorAll('.au-row').forEach(function (tr) { tr.addEventListener('click', function () { toggle(tr); }); });
    });
  }
  function fmt(v) { return v === null ? '—' : typeof v === 'object' ? JSON.stringify(v, null, 1) : String(v); }
  function toggle(tr) {
    var next = tr.nextElementSibling;
    if (next && next.classList.contains('au-detail')) { next.remove(); return; }
    var r = rows[+tr.dataset.i], d = r.details || {};
    var kv = Object.keys(d).map(function (k) { return '<span>' + esc(k) + '</span><code>' + esc(fmt(d[k])) + '</code>'; }).join('');
    var row = document.createElement('tr'); row.className = 'au-detail';
    row.innerHTML = '<td colspan="5"><div class="au-kv">' + (kv || '<span>Details</span><code>—</code>') +
      '<span>Request</span><code>' + esc((r.method || '') + ' ' + (r.route || '')) + (r.status ? ' → ' + r.status : '') + '</code><span>Browser</span><code>' + esc(r.agent || '—') + '</code></div></td>';
    tr.insertAdjacentElement('afterend', row);
  }
  f.addEventListener('input', function (e) { clearTimeout(timer); timer = setTimeout(function () { page = 1; load(); }, e.target.name === 'q' ? 300 : 0); });
  f.addEventListener('submit', function (e) { e.preventDefault(); });
  document.getElementById('au-prev').addEventListener('click', function () { page--; load(); });
  document.getElementById('au-next').addEventListener('click', function () { page++; load(); });
  load();
})();
</script>
@endpush
