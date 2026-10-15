@extends('backend.layouts.master')
@section('title')
Card Printing
@endsection
@push('styles')
<style>
  .cp-step { display: inline-flex; width: 24px; height: 24px; border-radius: 50%; background: var(--ad-brand); color: #fff; font-weight: 800; font-size: 12px; align-items: center; justify-content: center; margin-right: 6px; }
  .cp-scope { display: flex; flex-wrap: wrap; gap: 6px; }
  .cp-scope input { display: none; }
  .cp-scope label { border: 1px solid var(--ad-line); border-radius: 20px; padding: 4px 12px; font-size: 13px; cursor: pointer; color: var(--ad-text); }
  .cp-scope input:checked + label { background: rgba(91,155,255,.16); border-color: var(--ad-brand); color: var(--ad-brand); font-weight: 600; }
  .cp-tags { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 4px; }
  .cp-tag { display: inline-flex; align-items: center; gap: 4px; background: rgba(91,155,255,.16); color: var(--ad-brand); border-radius: 20px; padding: 2px 4px 2px 10px; font-size: 12px; }
  .cp-tag button { border: 0; background: none; color: inherit; padding: 0 4px; }
  .cp-search { position: relative; }
  .cp-list { position: absolute; z-index: 20; left: 0; right: 0; background: var(--ad-surface); border: 1px solid var(--ad-line); border-radius: 8px; box-shadow: var(--ad-shadow-lg); max-height: 300px; overflow: auto; display: none; }
  .cp-list button { display: block; width: 100%; text-align: left; border: 0; background: none; padding: 6px 10px; font-size: 13px; color: var(--ad-text); }
  .cp-list button:hover { background: var(--ad-hover); }
  .cp-sum { display: flex; flex-wrap: wrap; gap: 8px; }
  .cp-sum button { border: 1px solid var(--ad-line); background: var(--ad-surface); border-radius: 10px; padding: 6px 12px; font-size: 13px; color: var(--ad-text); }
  .cp-sum button.on { border-color: var(--ad-brand); background: rgba(91,155,255,.10); }
  .cp-sum b { font-size: 1.1rem; margin-right: 4px; }
  .cp-rows { max-height: 460px; overflow: auto; }
  .cp-rows td { vertical-align: top; font-size: 13px; }
  .cp-iss { display: block; font-size: 12px; }
  .cp-iss.error { color: var(--ad-red); } .cp-iss.warn { color: var(--ad-amber); } .cp-iss.info { color: var(--ad-muted); }
  .cp-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 4px; }
  .cp-dot.ok { background: var(--ad-green); } .cp-dot.warn { background: var(--ad-amber); } .cp-dot.error { background: var(--ad-red); }
  .cp-layout { border: 2px solid var(--ad-line); border-radius: 12px; padding: 12px; cursor: pointer; height: 100%; }
  .cp-layout.on { border-color: var(--ad-brand); background: rgba(91,155,255,.08); }
  .cp-layout i { font-size: 1.4rem; color: var(--ad-brand); }
  .cp-sheet { background: var(--ad-subtle); border-radius: 10px; padding: 10px; display: flex; gap: 14px; align-items: center; }
  .cp-sheet svg { flex: none; }
  .cp-pv { display: flex; gap: 20px; justify-content: center; flex-wrap: wrap; background: var(--ad-subtle); padding: 20px; border-radius: 10px; }
  .cp-pv svg { filter: drop-shadow(0 6px 18px rgba(16,26,46,.2)); }
  tr.cp-click { cursor: pointer; }
  tr.cp-off td { opacity: .5; }
</style>
@endpush
@section('content')
<div class="pagetitle d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div>
    <h1>Card Printing</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li><li class="breadcrumb-item active">Card Printing</li></ol></nav>
  </div>
  @can('cards.design')<a href="{{ route('card-studio.index') }}" class="btn btn-outline-primary"><i class="bi bi-palette"></i> Card Design Studio</a>@endcan
</div>

<section class="section">
  @include('backend.pages.participants.link-warning')
  <div class="row g-3">
    <div class="col-xl-5">
      {{-- 1. who --}}
      <div class="card"><div class="card-body pt-3">
        <h5 class="card-title py-1"><span class="cp-step">1</span>Who to print</h5>
        <form id="cp-who">
          <div class="row g-2">
            <div class="col-sm-6"><label class="form-label small fw-semibold mb-1">Event</label>
              <select class="form-select form-select-sm" name="event_id"><option value="">All events</option>@foreach($events as $e)<option value="{{ $e->id }}" @selected($e->id === ($pinnedId ?? null))>{{ $e->event_short }}{{ $e->is_current ? ' (current)' : '' }}</option>@endforeach</select></div>
            <div class="col-sm-6"><label class="form-label small fw-semibold mb-1">Category</label>
              <select class="form-select form-select-sm" name="category"><option value="">All categories</option>@foreach($categories as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach</select></div>
            <div class="col-12"><label class="form-label small fw-semibold mb-1">Card template</label>
              <select class="form-select form-select-sm" name="template_id"><option value="">Each participant's own (event / category / default)</option>@foreach($templates as $t)<option value="{{ $t->id }}">{{ $t->name }}{{ $t->is_default ? ' (default)' : '' }}</option>@endforeach</select></div>
            <div class="col-12"><label class="form-label small fw-semibold mb-1">Which participants</label>
              <div class="cp-scope">
                <input type="radio" name="scope" value="all" id="sc-all" checked><label for="sc-all">Everyone</label>
                <input type="radio" name="scope" value="not_printed" id="sc-np"><label for="sc-np">Not printed yet</label>
                <input type="radio" name="scope" value="with_card" id="sc-wc"><label for="sc-wc">With an NFC card</label>
                <input type="radio" name="scope" value="no_card" id="sc-nc"><label for="sc-nc">Without an NFC card</label>
              </div></div>
            <div class="col-12"><label class="form-label small fw-semibold mb-1">Or pick participants <span class="text-muted fw-normal">(then only these are printed)</span></label>
              <div class="cp-tags" id="cp-tags"></div>
              <div class="cp-search"><input class="form-control form-control-sm" id="cp-q" placeholder="Search name, ID, email, organization…" autocomplete="off"><div class="cp-list" id="cp-list"></div></div></div>
          </div>
          <button class="btn btn-primary mt-3 w-100" id="cp-check-btn"><i class="bi bi-list-check"></i> Check participants</button>
        </form>
      </div></div>

      {{-- 3. output --}}
      <div class="card"><div class="card-body pt-3">
        <h5 class="card-title py-1"><span class="cp-step">3</span>Print files</h5>
        <form id="cp-out">
          <div class="row g-2 mb-2">
            <div class="col-6"><div class="cp-layout on" data-layout="card"><i class="bi bi-credit-card-2-front"></i><div class="fw-semibold small mt-1">One card per page</div><div class="small text-muted">For card printers (Evolis, Zebra, Fargo…) and print shops</div></div></div>
            <div class="col-6"><div class="cp-layout" data-layout="sheet"><i class="bi bi-grid-3x3-gap"></i><div class="fw-semibold small mt-1">Sheets of cards</div><div class="small text-muted">Several cards on A4 / A3 paper, cut along crop marks</div></div></div>
          </div>
          <div id="cp-sheet-opts" class="d-none">
            <div class="row g-2">
              <div class="col-sm-6"><label class="form-label small fw-semibold mb-1">Paper</label><select class="form-select form-select-sm" name="paper">@foreach($papers as $k => $p)<option value="{{ $k }}">{{ $p[0] }}</option>@endforeach</select></div>
              <div class="col-3 cp-custom d-none"><label class="form-label small fw-semibold mb-1">Width mm</label><input type="number" class="form-control form-control-sm" name="paper_w" value="210" min="50" max="1000"></div>
              <div class="col-3 cp-custom d-none"><label class="form-label small fw-semibold mb-1">Height mm</label><input type="number" class="form-control form-control-sm" name="paper_h" value="297" min="50" max="1000"></div>
              <div class="col-sm-3 col-6"><label class="form-label small fw-semibold mb-1">Gap mm</label><input type="number" class="form-control form-control-sm" name="gap" value="{{ $defaults['gap'] }}" min="0" max="30" step="0.5"></div>
              <div class="col-sm-3 col-6"><label class="form-label small fw-semibold mb-1">Margin mm</label><input type="number" class="form-control form-control-sm" name="margin" value="{{ $defaults['margin'] }}" min="0" max="40" step="0.5"></div>
              <div class="col-12"><label class="form-label small fw-semibold mb-1">Two-sided printing flips on the</label>
                <select class="form-select form-select-sm" name="flip"><option value="long">Long edge (usual)</option><option value="short">Short edge</option></select></div>
            </div>
            <div class="cp-sheet mt-2" id="cp-sheet-info"></div>
          </div>
          <div class="row g-2 mt-1">
            <div class="col-sm-6"><label class="form-label small fw-semibold mb-1">Sides</label>
              <select class="form-select form-select-sm" name="sides"><option value="both">Front and back</option><option value="front">Front only</option><option value="back">Back only</option></select></div>
            <div class="col-sm-6"><label class="form-label small fw-semibold mb-1">Batch name <span class="text-muted fw-normal">(optional)</span></label>
              <input class="form-control form-control-sm" name="name" maxlength="120" placeholder="e.g. Speakers — day 1"></div>
          </div>
          <div class="d-flex flex-wrap gap-3 mt-2">
            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="bleed" id="cp-bleed" checked><label class="form-check-label small" for="cp-bleed">Include bleed</label></div>
            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="marks" id="cp-marks" checked><label class="form-check-label small" for="cp-marks">Crop marks</label></div>
            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="zip" id="cp-zip" @disabled(!$zip)><label class="form-check-label small" for="cp-zip">Also a ZIP with one PDF per participant</label></div>
          </div>
          <div class="small text-muted mt-2">Vector PDF at exact size: text, shapes and QR codes stay sharp at any resolution; photos are embedded at 350 dpi. A CSV list maps every card to its participant, page and NFC link.</div>
          <button class="btn btn-success mt-3 w-100" id="cp-make" disabled><i class="bi bi-printer"></i> <span>Check participants first</span></button>
        </form>
      </div></div>

      {{-- 4. download as files --}}
      <div class="card"><div class="card-body pt-3">
        <h5 class="card-title py-1"><span class="cp-step">4</span>Download cards as files</h5>
        <p class="small text-muted mb-2">For a print shop or designer: every ticked participant's card as PNG, JPG, PDF or EPS (CMYK) — one ZIP.</p>
        @include('backend.pages.card-studio.download-panel', ['id' => 'cdl'])
        <button type="button" class="btn btn-outline-primary mt-3 w-100" id="cp-dl" disabled><i class="bi bi-file-earmark-zip"></i> <span>Check participants first</span></button>
      </div></div>
    </div>

    {{-- 2. check --}}
    <div class="col-xl-7">
      <div class="card"><div class="card-body pt-3">
        <h5 class="card-title py-1"><span class="cp-step">2</span>Check before printing</h5>
        <div id="cp-empty" class="text-center text-muted py-5"><i class="bi bi-person-check fs-2 d-block mb-2"></i>Choose who to print and press <b>Check participants</b>.<br>Missing photos, empty fields, names that are too long and possible duplicates are listed here.</div>
        <div id="cp-result" class="d-none">
          <div class="cp-sum mb-2" id="cp-sum"></div>
          <div class="d-flex justify-content-between align-items-center mb-1 small">
            <span class="text-muted">Click a row to preview the card. Untick anyone you don't want in this batch.</span>
            <span><a href="#" id="cp-all">Tick all</a> · <a href="#" id="cp-none">none</a></span>
          </div>
          <div class="cp-rows border rounded"><table class="table table-sm mb-0"><thead><tr><th style="width:28px"></th><th>Participant</th><th class="d-none d-md-table-cell">Template · card</th><th>Checks</th></tr></thead><tbody id="cp-body"></tbody></table></div>
        </div>
      </div></div>

      {{-- history --}}
      <div class="card"><div class="card-body pt-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
          <h5 class="card-title py-1 mb-0"><i class="bi bi-clock-history"></i> Print jobs</h5>
          @if($pinnedId)
          <div class="btn-group btn-group-sm" role="group" aria-label="Which print jobs">
            <button type="button" class="btn btn-outline-secondary active" data-jobs="pinned"><i class="bi bi-pin-angle-fill"></i> {{ optional($events->firstWhere('id', $pinnedId))->event_short }}</button>
            <button type="button" class="btn btn-outline-secondary" data-jobs="all">All events</button>
          </div>
          @endif
        </div>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
          <thead><tr><th>Batch</th><th>Cards</th><th class="d-none d-lg-table-cell">Layout</th><th>Files</th><th>Printed</th><th class="text-end"></th></tr></thead>
          <tbody id="cp-jobs"></tbody>
        </table></div>
      </div></div>
    </div>
  </div>
</section>

<div class="modal fade" id="cpPreview" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="cp-pv-title">Preview</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><div class="cp-pv" id="cp-pv"></div><div class="small mt-2" id="cp-pv-issues"></div></div>
  <div class="modal-footer justify-content-between">
    <span class="small text-muted"><i class="bi bi-download"></i> Download this card <span class="d-none d-sm-inline">(options from step 4)</span></span>
    <div class="d-flex gap-2 flex-wrap" id="cp-pv-dl">
      <button type="button" class="btn btn-sm btn-outline-primary" data-pv-dl="png" disabled>PNG</button>
      <button type="button" class="btn btn-sm btn-outline-primary" data-pv-dl="jpg" disabled>JPG</button>
      <button type="button" class="btn btn-sm btn-outline-primary" data-pv-dl="pdf" disabled>PDF</button>
      <button type="button" class="btn btn-sm btn-outline-primary" data-pv-dl="eps" disabled>EPS <small>CMYK</small></button>
    </div>
  </div>
</div></div></div>
@endsection

@push('scripts')
@include('backend.pages.card-studio.assets')
<script>
(function () {
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var urls = { check: @json(route('card-print.check')), store: @json(route('card-print.store')), search: @json(route('card-print.search')),
    base: @json(url('card-print')) + '/', data: @json(url('card-studio/data')) + '/', asset: @json(url('/') . '/') };
  var papers = @json($papers), icons = @json($icons);
  window.CARD_ICONS = icons;
  var pvItem = null;   // the card shown in the preview window
  var jobs = @json($jobs);
  var picked = [], rows = [], templates = {}, ticked = {}, filter = 'all', layout = 'card', checkedWith = null;
  var who = document.getElementById('cp-who'), out = document.getElementById('cp-out');
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function req(method, url, body) {
    return fetch(url, { method: method, headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : undefined })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (res) { if (!res.ok) { var e = res.d.errors || {}; var k = Object.keys(e)[0]; Swal.fire('Not done', k ? e[k][0] : (res.d.message || 'Please try again.'), 'warning'); if (res.d.jobs) { jobs = res.d.jobs; renderJobs(); } return null; } return res.d; })
      .catch(function () { Swal.fire('Not done', 'Check your connection and try again.', 'warning'); return null; });
  }
  function toast(m) { Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: m, showConfirmButton: false, timer: 2500 }); }
  function whoBody() {
    return { event_id: who.event_id.value ? +who.event_id.value : null, category: who.category.value || null, template_id: who.template_id.value ? +who.template_id.value : null,
      scope: who.querySelector('[name=scope]:checked').value, picked: picked.map(function (p) { return p.id; }) };
  }

  /* picking */
  function renderTags() {
    document.getElementById('cp-tags').innerHTML = picked.map(function (p, i) { return '<span class="cp-tag">' + esc(p.name) + ' <span class="opacity-75">' + esc(p.code) + '</span><button type="button" data-rm="' + i + '">&times;</button></span>'; }).join('');
    document.querySelectorAll('[data-rm]').forEach(function (b) { b.addEventListener('click', function () { picked.splice(+b.dataset.rm, 1); renderTags(); stale(); }); });
  }
  var timer, q = document.getElementById('cp-q'), list = document.getElementById('cp-list');
  q.addEventListener('input', function () {
    clearTimeout(timer); var v = q.value.trim();
    if (v.length < 2) { list.style.display = 'none'; return; }
    timer = setTimeout(function () {
      fetch(urls.search + '?q=' + encodeURIComponent(v), { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (res) {
        list.innerHTML = res.map(function (p, i) { return '<button type="button" data-i="' + i + '"><b>' + esc(p.name) + '</b> <span class="text-muted">' + esc(p.code) + '</span> ' + esc(p.organization || '') + '</button>'; }).join('') || '<div class="p-2 small text-muted">No participants found.</div>';
        list.style.display = 'block';
        list.querySelectorAll('[data-i]').forEach(function (b) { b.addEventListener('mousedown', function (ev) {
          ev.preventDefault(); var p = res[+b.dataset.i];
          if (!picked.some(function (x) { return x.id === p.id; })) picked.push(p);
          q.value = ''; list.style.display = 'none'; renderTags(); stale();
        }); });
      });
    }, 250);
  });
  q.addEventListener('blur', function () { setTimeout(function () { list.style.display = 'none'; }, 150); });
  q.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') ev.preventDefault(); });
  who.addEventListener('change', stale);

  /* check */
  function stale() { if (rows.length) { checkedWith = null; updateMake(); } }
  who.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var btn = document.getElementById('cp-check-btn'); btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Checking…';
    var body = whoBody();
    req('POST', urls.check, body).then(function (d) {
      btn.disabled = false; btn.innerHTML = '<i class="bi bi-list-check"></i> Check participants';
      if (!d) return;
      rows = d.rows; templates = d.templates; ticked = {}; filter = 'all'; checkedWith = body;
      rows.forEach(function (r) { if (r.level !== 'error') ticked[r.id] = 1; });
      document.getElementById('cp-empty').classList.toggle('d-none', rows.length > 0);
      document.getElementById('cp-result').classList.toggle('d-none', !rows.length);
      if (!rows.length) document.getElementById('cp-empty').innerHTML = '<i class="bi bi-search fs-2 d-block mb-2"></i>No participants match.';
      renderRows(); sheetInfo(); updateMake();
    });
  });

  function renderRows() {
    var n = { all: rows.length, ok: 0, warn: 0, error: 0 };
    rows.forEach(function (r) { n[r.level]++; });
    document.getElementById('cp-sum').innerHTML = [['all', 'participants', ''], ['ok', 'ready', 'ok'], ['warn', 'to look at', 'warn'], ['error', 'cannot print', 'error']].map(function (s) {
      return '<button type="button" data-f="' + s[0] + '" class="' + (filter === s[0] ? 'on' : '') + '">' + (s[2] ? '<span class="cp-dot ' + s[2] + '"></span>' : '') + '<b>' + n[s[0]] + '</b>' + s[1] + '</button>';
    }).join('');
    document.querySelectorAll('[data-f]').forEach(function (b) { b.addEventListener('click', function () { filter = b.dataset.f; renderRows(); }); });
    document.getElementById('cp-body').innerHTML = rows.filter(function (r) { return filter === 'all' || r.level === filter; }).map(function (r) {
      var off = !ticked[r.id];
      return '<tr class="cp-click' + (off ? ' cp-off' : '') + '" data-row="' + r.id + '"><td><input type="checkbox" class="form-check-input" data-tick="' + r.id + '"' + (off ? '' : ' checked') + (r.level === 'error' ? ' disabled' : '') + '></td>' +
        '<td><span class="cp-dot ' + r.level + '"></span><b>' + esc(r.name) + '</b><div class="small text-muted">' + esc(r.code) + (r.organization ? ' · ' + esc(r.organization) : '') + (r.category ? ' · ' + esc(r.category) : '') + '</div></td>' +
        '<td class="d-none d-md-table-cell small">' + esc(r.template || '—') + '<div class="text-muted">' + (r.card_no ? '<i class="bi bi-credit-card-2-front"></i> ' + esc(r.card_no) : 'No NFC card') + '</div></td>' +
        '<td>' + (r.issues.length ? r.issues.map(function (i) { return '<span class="cp-iss ' + i[0] + '">' + esc(i[1]) + '</span>'; }).join('') : '<span class="cp-iss text-success">All good</span>') + '</td></tr>';
    }).join('') || '<tr><td colspan="4" class="text-center text-muted py-3">Nobody here.</td></tr>';
    document.querySelectorAll('[data-tick]').forEach(function (cb) {
      cb.addEventListener('click', function (ev) { ev.stopPropagation(); });
      cb.addEventListener('change', function () { if (cb.checked) ticked[cb.dataset.tick] = 1; else delete ticked[cb.dataset.tick]; cb.closest('tr').classList.toggle('cp-off', !cb.checked); updateMake(); });
    });
    document.querySelectorAll('[data-row]').forEach(function (tr) { tr.addEventListener('click', function () { preview(+tr.dataset.row); }); });
  }
  document.getElementById('cp-all').addEventListener('click', function (ev) { ev.preventDefault(); rows.forEach(function (r) { if (r.level !== 'error') ticked[r.id] = 1; }); renderRows(); updateMake(); });
  document.getElementById('cp-none').addEventListener('click', function (ev) { ev.preventDefault(); ticked = {}; renderRows(); updateMake(); });

  var pvModal = new bootstrap.Modal(document.getElementById('cpPreview'));
  /* downloads as files (PNG / JPG / EPS drawn here, PDF from the server) */
  function dlCtx() { return { templateId: checkedWith && checkedWith.template_id, eventId: checkedWith && checkedWith.event_id, label: 'cards' }; }
  document.getElementById('cp-dl').addEventListener('click', function () {
    var ids = rows.filter(function (r) { return ticked[r.id]; }).map(function (r) { return r.id; });
    if (!ids.length) return;
    var b = this; b.disabled = true;
    CardDownloads.fetchItems(ids, dlCtx()).then(function (items) { return CardDownloads.run(items, ids, dlCtx()); })
      .catch(function (e) { Swal.fire('Not downloaded', e.message, 'warning'); }).then(function () { b.disabled = false; });
  });
  document.querySelectorAll('[data-pv-dl]').forEach(function (b) {
    b.addEventListener('click', function () { if (pvItem) CardDownloads.one(pvItem, pvItem.id, b.dataset.pvDl, dlCtx()); });
  });

  function preview(id) {
    var r = rows.find(function (x) { return x.id === id; }), t = templates[r.template_id];
    document.getElementById('cp-pv-title').textContent = r.name + ' · ' + r.code;
    document.getElementById('cp-pv-issues').innerHTML = r.issues.map(function (i) { return '<span class="cp-iss ' + i[0] + '">' + esc(i[1]) + '</span>'; }).join('');
    var box = document.getElementById('cp-pv'); box.innerHTML = t ? '<div class="text-muted small">Loading…</div>' : '<div class="text-muted">No template.</div>';
    pvModal.show();
    if (!t) return;
    var ev = checkedWith && checkedWith.event_id ? '?event=' + checkedWith.event_id : '';
    pvItem = null; document.querySelectorAll('[data-pv-dl]').forEach(function (b) { b.disabled = true; });
    fetch(urls.data + id + ev, { headers: { Accept: 'application/json' } }).then(function (res) { return res.json(); }).then(function (data) {
      pvItem = { id: id, name: r.name, code: r.code, design: t.design, data: data, template: r.template_id };
      document.querySelectorAll('[data-pv-dl]').forEach(function (b) { b.disabled = false; });
      return CardRenderer.ready(t.design).then(function () {
        box.innerHTML = '';
        var h = t.design.height > t.design.width ? 340 : 220;
        (t.design.double_sided ? ['front', 'back'] : ['front']).forEach(function (s) {
          box.appendChild(CardRenderer.render(t.design, s, data, { icons: icons, base: urls.asset, trim: true, widthPx: Math.round(h * t.design.width / t.design.height) }));
        });
      });
    });
  }

  /* output settings */
  document.querySelectorAll('.cp-layout').forEach(function (c) { c.addEventListener('click', function () {
    layout = c.dataset.layout;
    document.querySelectorAll('.cp-layout').forEach(function (x) { x.classList.toggle('on', x === c); });
    document.getElementById('cp-sheet-opts').classList.toggle('d-none', layout !== 'sheet');
    if (layout === 'sheet' && +out.gap.value < 3 && out.marks.checked) out.gap.value = 6;
    sheetInfo();
  }); });
  out.addEventListener('input', sheetInfo);
  out.paper.addEventListener('change', function () {
    var p = papers[out.paper.value];
    document.querySelectorAll('.cp-custom').forEach(function (e) { e.classList.toggle('d-none', out.paper.value !== 'custom'); });
    if (p[1]) { out.paper_w.value = p[1]; out.paper_h.value = p[2]; }
    sheetInfo();
  });
  function settings() {
    return { layout: layout, paper: out.paper.value, paper_w: +out.paper_w.value, paper_h: +out.paper_h.value, gap: +out.gap.value, margin: +out.margin.value, flip: out.flip.value,
      sides: out.sides.value, bleed: out.bleed.checked, marks: out.marks.checked, zip: out.zip.checked };
  }
  /** Same sum as CardPrint::grid(): how many cards fit on the sheet, with a small picture. */
  function sheetInfo() {
    var box = document.getElementById('cp-sheet-info');
    if (layout !== 'sheet') return;
    var ts = Object.keys(templates).map(function (k) { return templates[k].design; });
    if (!ts.length) { box.innerHTML = '<span class="small text-muted">Check participants to see how many cards fit on a sheet.</span>'; return; }
    var s = settings(), b = s.bleed ? Math.max.apply(null, ts.map(function (d) { return d.bleed; })) : 0;
    var cw = Math.max.apply(null, ts.map(function (d) { return d.width; })) + 2 * b, ch = Math.max.apply(null, ts.map(function (d) { return d.height; })) + 2 * b;
    var best = [0, 0, false];
    [false, true].forEach(function (land) {
      var pw = land ? s.paper_h : s.paper_w, ph = land ? s.paper_w : s.paper_h;
      var c = Math.floor((pw - 2 * s.margin + s.gap) / (cw + s.gap)), r = Math.floor((ph - 2 * s.margin + s.gap) / (ch + s.gap));
      if (c * r > best[0] * best[1]) best = [c, r, land];
    });
    var cols = best[0], rws = best[1], pw = best[2] ? s.paper_h : s.paper_w, ph = best[2] ? s.paper_w : s.paper_h;
    if (!cols || !rws) { box.innerHTML = '<span class="small text-danger">The card does not fit on this paper.</span>'; return; }
    var k = 90 / Math.max(pw, ph), gw = cols * cw + (cols - 1) * s.gap, gh = rws * ch + (rws - 1) * s.gap, x0 = (pw - gw) / 2, y0 = (ph - gh) / 2, cells = '';
    for (var r = 0; r < rws; r++) for (var c = 0; c < cols; c++) cells += '<rect x="' + (x0 + c * (cw + s.gap)) * k + '" y="' + (y0 + r * (ch + s.gap)) * k + '" width="' + cw * k + '" height="' + ch * k + '" rx="1" fill="#5b9bff" opacity=".55"/>';
    var n = Object.keys(ticked).length, sheets = Math.ceil(n / (cols * rws)) || 0;
    box.innerHTML = '<svg width="' + pw * k + '" height="' + ph * k + '"><rect width="100%" height="100%" fill="#fff" stroke="#c9d2e0"/>' + cells + '</svg>' +
      '<div class="small"><b>' + cols * rws + ' cards per sheet</b> (' + cols + ' × ' + rws + (best[2] ? ', landscape' : '') + ')<br>' + n + ' cards → ' + sheets + ' sheet' + (sheets === 1 ? '' : 's') +
      (s.sides === 'both' ? ' (' + sheets * 2 + ' pages with backs)' : '') + '<br><span class="text-muted">Print at 100 % / "actual size", not "fit to page".</span></div>';
  }
  function updateMake() {
    var n = Object.keys(ticked).length, btn = document.getElementById('cp-make');
    var dl = document.getElementById('cp-dl');
    if (dl) { dl.disabled = !checkedWith || !n; dl.querySelector('span').textContent = !checkedWith ? (rows.length ? 'Check again — the choice changed' : 'Check participants first') : 'Download ' + n + ' card' + (n === 1 ? '' : 's'); }
    btn.disabled = !checkedWith || !n;
    btn.querySelector('span').textContent = !checkedWith ? (rows.length ? 'Check again — the choice changed' : 'Check participants first') : 'Make print files (' + n + ' card' + (n === 1 ? '' : 's') + ')';
    sheetInfo();
  }
  out.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var btn = document.getElementById('cp-make'); btn.disabled = true; btn.querySelector('span').textContent = 'Making files…';
    var ids = rows.filter(function (r) { return ticked[r.id]; }).map(function (r) { return r.id; });
    req('POST', urls.store, { ids: ids, event_id: checkedWith.event_id, template_id: checkedWith.template_id, settings: settings(), name: out.elements['name'].value }).then(function (d) {
      updateMake();
      if (!d) return;
      jobs = d.jobs; renderJobs();
      var f = d.job.files;
      Swal.fire({ icon: 'success', title: 'Print files ready', html: esc(d.message) + (d.job.notes ? '<div class="small text-warning mt-2">' + esc(d.job.notes) + '</div>' : '') +
        '<div class="d-flex gap-2 justify-content-center mt-3">' + (d.job.view ? '<a class="btn btn-primary" target="_blank" href="' + d.job.view + '"><i class="bi bi-eye"></i> Open PDF</a>' : '') +
        (f.pdf ? '<a class="btn btn-outline-primary" href="' + f.pdf + '">PDF</a>' : '') + (f.csv ? '<a class="btn btn-outline-primary" href="' + f.csv + '">CSV</a>' : '') + (f.zip ? '<a class="btn btn-outline-primary" href="' + f.zip + '">ZIP</a>' : '') + '</div>' +
        '<div class="small text-muted mt-3">After printing, mark the batch as printed in Print jobs.</div>', showConfirmButton: false, showCloseButton: true });
    });
  });

  /* history */
  var pinnedEvent = @json($pinnedId), jobsScope = pinnedEvent ? 'pinned' : 'all';
  document.querySelectorAll('[data-jobs]').forEach(function (b) {
    b.addEventListener('click', function () { jobsScope = b.dataset.jobs; document.querySelectorAll('[data-jobs]').forEach(function (x) { x.classList.toggle('active', x === b); }); renderJobs(); });
  });
  function renderJobs() {
    var shown = jobs.filter(function (j) { return jobsScope === 'all' || j.event_id === pinnedEvent; });
    if (!shown.length) { document.getElementById('cp-jobs').innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">' + (jobs.length ? 'No print jobs for this event yet — see "All events".' : 'No print jobs yet.') + '</td></tr>'; return; }
    document.getElementById('cp-jobs').innerHTML = shown.map(function (j) {
      var files = Object.keys(j.files).map(function (k) { return '<a class="btn btn-sm btn-outline-primary py-0 px-2" href="' + j.files[k] + '">' + k.toUpperCase() + '</a>'; }).join(' ');
      return '<tr><td><b>' + esc(j.name) + '</b><div class="small text-muted">#' + j.id + ' · ' + esc(j.at) + (j.by ? ' · ' + esc(j.by) : '') + (j.event ? ' · ' + esc(j.event) : '') + '</div>' +
        '<div class="small text-muted">' + esc(j.template) + '</div>' + (j.notes ? '<div class="small text-warning">' + esc(j.notes) + '</div>' : '') + '</td>' +
        '<td>' + j.count + '<div class="small text-muted">' + j.pages + ' pages</div></td><td class="d-none d-lg-table-cell small">' + esc(j.layout) + '</td>' +
        '<td class="text-nowrap">' + (j.status === 'failed' ? '<span class="badge bg-danger">Failed</span>' : (j.view ? '<a class="btn btn-sm btn-light py-0 px-2" target="_blank" href="' + j.view + '" title="Open"><i class="bi bi-eye"></i></a> ' : '') + files) + '</td>' +
        '<td>' + (j.printed ? '<span class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-check2"></i> ' + esc(j.printed) + '</span>' : '<span class="small text-muted">No</span>') + '</td>' +
        '<td class="text-end"><div class="dropdown"><button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button><ul class="dropdown-menu dropdown-menu-end">' +
        (j.status !== 'failed' ? '<li><button class="dropdown-item" data-printed="' + j.id + '" data-on="' + (j.printed ? 0 : 1) + '"><i class="bi ' + (j.printed ? 'bi-arrow-counterclockwise' : 'bi-check2-square') + '"></i> ' + (j.printed ? 'Mark as not printed' : 'Mark as printed') + '</button></li>' : '') +
        '<li><button class="dropdown-item" data-reprint="' + j.id + '"><i class="bi bi-arrow-repeat"></i> Print these again…</button></li>' +
        '<li><hr class="dropdown-divider"></li><li><button class="dropdown-item text-danger" data-deljob="' + j.id + '"><i class="bi bi-trash"></i> Delete job &amp; files</button></li></ul></div></td></tr>';
    }).join('') || '<tr><td colspan="6" class="text-center text-muted py-4">No print jobs yet.</td></tr>';
    document.querySelectorAll('[data-printed]').forEach(function (b) { b.addEventListener('click', function () {
      req('POST', urls.base + b.dataset.printed + '/printed', { printed: b.dataset.on === '1' }).then(function (d) { if (d) { jobs = d.jobs; renderJobs(); toast(d.message); } });
    }); });
    document.querySelectorAll('[data-deljob]').forEach(function (b) { b.addEventListener('click', function () {
      Swal.fire({ title: 'Delete this print job?', text: 'Its files are deleted. Participants it marked as printed count as not printed again.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#d33' })
        .then(function (r) { if (r.isConfirmed) req('DELETE', urls.base + b.dataset.deljob).then(function (d) { if (d) { jobs = d.jobs; renderJobs(); toast(d.message); } }); });
    }); });
    document.querySelectorAll('[data-reprint]').forEach(function (b) { b.addEventListener('click', function () {
      fetch(urls.base + b.dataset.reprint + '/participants', { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
        // load the batch: same people, same settings → check → make
        picked = d.ids.map(function (id) { return { id: id, name: '#' + id, code: '' }; });
        who.event_id.value = d.event_id || ''; who.template_id.value = d.template_id || '';
        var s = d.settings;
        document.querySelector('.cp-layout[data-layout="' + s.layout + '"]').click();
        ['paper', 'paper_w', 'paper_h', 'gap', 'margin', 'flip', 'sides'].forEach(function (k) { if (out[k] && s[k] !== undefined) out[k].value = s[k]; });
        out.bleed.checked = !!s.bleed; out.marks.checked = !!s.marks; out.zip.checked = !!s.zip && !out.zip.disabled; out.elements['name'].value = d.name;
        document.querySelectorAll('.cp-custom').forEach(function (e) { e.classList.toggle('d-none', s.paper !== 'custom'); });
        renderTags();
        document.querySelector('#cp-tags').innerHTML = '<span class="cp-tag">' + d.ids.length + ' participants from job #' + b.dataset.reprint + ' <button type="button" id="cp-clear-picked">&times;</button></span>';
        document.getElementById('cp-clear-picked').addEventListener('click', function () { picked = []; renderTags(); stale(); });
        window.scrollTo({ top: 0, behavior: 'smooth' });
        who.requestSubmit();
      });
    }); });
  }
  renderJobs();
})();
</script>
@endpush
