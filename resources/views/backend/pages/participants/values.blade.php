@extends('backend.layouts.master')
@section('title')
Organizations &amp; Categories
@endsection
@push('styles')
<style>
  .pv-tabs .nav-link { font-weight: 600; }
  .pv-bar { position: sticky; top: 70px; z-index: 5; background: var(--ad-surface); border: 1px solid var(--ad-brand); border-radius: 12px; padding: 10px 12px; box-shadow: var(--ad-shadow-lg); }
  .pv-group td { background: rgba(242,184,75,.07); }
  .pv-group-first td { border-top: 2px solid rgba(242,184,75,.6) !important; }
  .pv-val { font-weight: 600; color: var(--ad-ink); word-break: break-word; }
</style>
@endpush
@section('content')
<div class="pagetitle d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div>
    <h1>Organizations &amp; Categories</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li><li class="breadcrumb-item"><a href="{{ route('participants.index') }}">Participants</a></li><li class="breadcrumb-item active">Organizations &amp; Categories</li></ol></nav>
  </div>
</div>
<section class="section">
  <div class="alert alert-info small py-2"><i class="bi bi-info-circle"></i> People type the same organization in many ways. Tick the variants, choose the correct spelling and press <b>Merge</b> — every profile using them is updated (cards, reports and filters then group them together). Each change is kept in the profile history and the audit log.</div>
  <ul class="nav nav-tabs pv-tabs mb-3">
    @foreach($fields as $k => $label)<li class="nav-item"><a class="nav-link {{ $field === $k ? 'active' : '' }}" href="{{ route('participants.values', ['field' => $k]) }}">{{ $label }}</a></li>@endforeach
  </ul>
  <div class="card"><div class="card-body pt-3">
    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
      <input type="search" class="form-control form-control-sm" id="pv-q" placeholder="Search…" style="width:260px">
      <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" id="pv-variants"><label class="form-check-label small" for="pv-variants">Only spelling variants <span class="badge bg-warning text-dark" id="pv-vcount">0</span></label></div>
      <span class="small text-muted ms-auto" id="pv-info"></span>
    </div>
    <div class="pv-bar d-none mb-2" id="pv-bar">
      <div class="d-flex flex-wrap gap-2 align-items-center">
        <b class="small"><span id="pv-sel">0</span> selected</b>
        <span class="small text-muted">→ change to</span>
        <input class="form-control form-control-sm" id="pv-to" style="max-width:340px" placeholder="Correct spelling">
        <button class="btn btn-sm btn-primary" id="pv-merge"><i class="bi bi-intersect"></i> Merge / rename</button>
        <button class="btn btn-sm btn-link" id="pv-clear">Clear selection</button>
      </div>
    </div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
      <thead><tr><th style="width:32px"></th><th>Value</th><th class="text-end" style="width:110px">Participants</th><th style="width:100px"></th></tr></thead>
      <tbody id="pv-body"><tr><td colspan="4" class="text-center text-muted py-4">Loading…</td></tr></tbody>
    </table></div>
  </div></div>
</section>
@endsection
@push('scripts')
<script>
(function () {
  var field = @json($field), csrf = document.querySelector('meta[name="csrf-token"]').content;
  var rows = [], sel = {}, onlyVariants = false, q = '';
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function load() {
    fetch(@json(route('participants.values.data')) + '?field=' + field, { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
      rows = d.rows; sel = {};
      var groups = {}; rows.forEach(function (r) { if (r.variants > 1) groups[r.group] = 1; });
      document.getElementById('pv-vcount').textContent = Object.keys(groups).length;
      document.getElementById('pv-info').textContent = rows.length + ' different · ' + d.empty + ' profiles without a value';
      render();
    });
  }
  function render() {
    var list = rows.filter(function (r) { return (!onlyVariants || r.variants > 1) && (!q || r.value.toLowerCase().indexOf(q) >= 0); });
    if (onlyVariants) list.sort(function (a, b) { return a.group < b.group ? -1 : a.group > b.group ? 1 : b.n - a.n; });
    var last = null;
    document.getElementById('pv-body').innerHTML = list.map(function (r) {
      var cls = r.variants > 1 && onlyVariants ? 'pv-group' + (r.group !== last ? ' pv-group-first' : '') : ''; last = r.group;
      return '<tr class="' + cls + '"><td><input type="checkbox" class="form-check-input" data-v="' + esc(r.value) + '"' + (sel[r.value] ? ' checked' : '') + '></td>' +
        '<td><span class="pv-val">' + esc(r.value) + '</span>' + (r.variants > 1 ? ' <span class="badge bg-warning-subtle text-warning-emphasis" title="Spelled in ' + r.variants + ' ways">' + r.variants + ' variants</span>' : '') + '</td>' +
        '<td class="text-end">' + r.n + '</td><td class="text-end"><button class="btn btn-sm btn-light py-0 text-nowrap" data-rename="' + esc(r.value) + '"><i class="bi bi-pencil"></i> Rename</button></td></tr>';
    }).join('') || '<tr><td colspan="4" class="text-center text-muted py-4">' + (onlyVariants ? 'No spelling variants found.' : 'Nothing here.') + '</td></tr>';
    document.querySelectorAll('[data-v]').forEach(function (cb) { cb.addEventListener('change', function () { if (cb.checked) sel[cb.dataset.v] = 1; else delete sel[cb.dataset.v]; bar(); }); });
    document.querySelectorAll('[data-rename]').forEach(function (b) { b.addEventListener('click', function () { sel = {}; sel[b.dataset.rename] = 1; render(); bar(); document.getElementById('pv-to').value = b.dataset.rename; document.getElementById('pv-to').focus(); }); });
    bar();
  }
  function bar() {
    var keys = Object.keys(sel);
    document.getElementById('pv-bar').classList.toggle('d-none', !keys.length);
    document.getElementById('pv-sel').textContent = keys.length;
    var to = document.getElementById('pv-to');
    if (keys.length && (!to.value || !sel.hasOwnProperty(to.value) && to.dataset.auto === '1')) {
      // suggest the most used of the selected spellings
      var best = rows.filter(function (r) { return sel[r.value]; }).sort(function (a, b) { return b.n - a.n; })[0];
      to.value = best ? best.value : ''; to.dataset.auto = '1';
    }
  }
  document.getElementById('pv-to').addEventListener('input', function () { this.dataset.auto = '0'; });
  document.getElementById('pv-q').addEventListener('input', function () { q = this.value.trim().toLowerCase(); render(); });
  document.getElementById('pv-variants').addEventListener('change', function () { onlyVariants = this.checked; render(); });
  document.getElementById('pv-clear').addEventListener('click', function () { sel = {}; document.getElementById('pv-to').value = ''; render(); });
  document.getElementById('pv-merge').addEventListener('click', function () {
    var from = Object.keys(sel), to = document.getElementById('pv-to').value.trim();
    var n = rows.filter(function (r) { return sel[r.value]; }).reduce(function (s, r) { return s + r.n; }, 0);
    Swal.fire({ title: to ? 'Change to "' + to + '"?' : 'Clear this value?', html: n + ' participant profile' + (n === 1 ? '' : 's') + ' will be updated:<br><small class="text-muted">' + from.map(esc).join('<br>') + '</small>',
      icon: 'question', showCancelButton: true, confirmButtonText: 'Update ' + n }).then(function (r) {
      if (!r.isConfirmed) return;
      fetch(@json(route('participants.organizations')), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ field: field, from: from, to: to }) })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
        .then(function (res) {
          if (!res.ok) { var e = res.d.errors || {}; var k = Object.keys(e)[0]; return Swal.fire('Not done', k ? e[k][0] : (res.d.message || 'Please try again.'), 'warning'); }
          Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: res.d.message, showConfirmButton: false, timer: 2500 });
          document.getElementById('pv-to').value = ''; load();
        });
    });
  });
  load();
})();
</script>
@endpush
