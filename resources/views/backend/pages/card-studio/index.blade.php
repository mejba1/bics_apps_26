@extends('backend.layouts.master')
@section('title')
Card Design Studio
@endsection
@push('styles')
<style>
  .cs-tile { border-radius: 14px; border: 1px solid var(--ad-line); background: var(--ad-surface); height: 100%; display: flex; flex-direction: column; transition: box-shadow .15s, transform .15s; }
  .cs-tile:hover { box-shadow: 0 10px 30px rgba(16,26,46,.10); transform: translateY(-2px); }
  .cs-thumbs { background: var(--ad-subtle); border-radius: 14px 14px 0 0; padding: 18px 12px; display: flex; gap: 12px; justify-content: center; align-items: center; min-height: 220px; cursor: pointer; }
  .cs-thumbs svg { filter: drop-shadow(0 4px 10px rgba(16,26,46,.18)); border-radius: 6px; }
  .cs-meta { padding: 12px 14px; flex: 1; display: flex; flex-direction: column; gap: 6px; }
  .cs-name { font-weight: 700; color: var(--ad-ink); }
  .cs-badges .badge { font-weight: 600; }
  .cs-rule { font-size: 13px; color: var(--ad-muted); }
  .cs-pick { border: 2px solid var(--ad-line); border-radius: 12px; padding: 10px; cursor: pointer; text-align: center; height: 100%; }
  .cs-pick.is-on { border-color: var(--ad-brand); background: rgba(91,155,255,.10); }
  .cs-pick svg { filter: drop-shadow(0 2px 6px rgba(16,26,46,.15)); }
</style>
@endpush
@section('content')
<div class="pagetitle d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div>
    <h1>Card Design Studio</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li><li class="breadcrumb-item active">Card Design Studio</li></ol></nav>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a href="{{ route('card-studio.preview') }}" class="btn btn-outline-primary"><i class="bi bi-people"></i> Preview with participants</a>
    <button class="btn btn-primary" id="cs-new-btn"><i class="bi bi-plus-lg"></i> New template</button>
  </div>
</div>

<section class="section">
  <div class="alert alert-info small py-2 d-flex gap-2"><i class="bi bi-info-circle"></i>
    <div>Each participant gets one template: the one set for <b>their event and category</b>, otherwise the one for <b>their event</b>, otherwise the <b>default</b> template.
      Designs are in millimetres with a print bleed, and use the participant's details, photo and QR code automatically. Every save keeps a version you can go back to.</div>
  </div>
  <div class="row g-3" id="cs-grid"></div>
</section>

{{-- New template --}}
<div class="modal fade" id="csNew" tabindex="-1"><div class="modal-dialog modal-lg"><form class="modal-content" id="cs-new-form">
  <div class="modal-header"><h5 class="modal-title">New card template</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <label class="form-label fw-semibold">Name</label>
    <input class="form-control mb-3" name="name" maxlength="120" required placeholder="e.g. BICS 2026 Speaker badge">
    <label class="form-label fw-semibold">Start from</label>
    <div class="row g-2 mb-2" id="cs-starts">
      @foreach($presets as $key => $label)
      <div class="col-6 col-md-3"><div class="cs-pick" data-from="preset" data-value="{{ $key }}"><div class="cs-pick-thumb" data-preset="{{ $key }}"></div><div class="small fw-semibold mt-2">{{ $label }}</div><div class="small text-muted">Starter design</div></div></div>
      @endforeach
      <div class="col-6 col-md-3"><div class="cs-pick" data-from="blank"><div class="py-4"><i class="bi bi-file-earmark fs-1 text-muted"></i></div><div class="small fw-semibold">Blank card</div>
        <select class="form-select form-select-sm mt-1" name="size">@foreach($sizes as $k => $s)<option value="{{ $k }}">{{ $s[0] }}</option>@endforeach</select></div></div>
      <div class="col-6 col-md-3"><div class="cs-pick" data-from="copy"><div class="py-4"><i class="bi bi-files fs-1 text-muted"></i></div><div class="small fw-semibold">Copy a template</div>
        <select class="form-select form-select-sm mt-1" name="copy_id">@foreach($templates as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select></div></div>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create &amp; open studio</button></div>
</form></div></div>

{{-- Where the template is used --}}
<div class="modal fade" id="csSet" tabindex="-1"><div class="modal-dialog"><form class="modal-content" id="cs-set-form">
  <div class="modal-header"><h5 class="modal-title">Template settings</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <label class="form-label fw-semibold">Name</label>
    <input class="form-control mb-3" name="name" maxlength="120" required>
    <label class="form-label fw-semibold">Use for event <span class="text-muted fw-normal">(optional)</span></label>
    <select class="form-select mb-3" name="event_id"><option value="">— Not tied to an event —</option>@foreach($events as $e)<option value="{{ $e->id }}">{{ $e->event_short }}{{ $e->is_current ? ' (current)' : '' }}</option>@endforeach</select>
    <div id="cs-cat-wrap">
      <label class="form-label fw-semibold">… and only for category <span class="text-muted fw-normal">(optional)</span></label>
      <input class="form-control mb-1" name="category" list="cs-cats" maxlength="100" placeholder="e.g. Speaker, Delegate, Volunteer">
      <datalist id="cs-cats">@foreach($categories as $c)<option value="{{ $c }}">@endforeach</datalist>
      <div class="form-text mb-3">Leave empty to use it for everyone at the event.</div>
    </div>
    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_default" id="cs-default"><label class="form-check-label" for="cs-default">Default template <span class="text-muted">(used when nothing else matches)</span></label></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
</form></div></div>
@endsection

@push('scripts')
@php
  $tplData = $templates->map(function ($t) {
      return ['id' => $t->id, 'name' => $t->name, 'event_id' => $t->event_id, 'event' => $t->event->event_short ?? null, 'category' => $t->category,
          'is_default' => $t->is_default, 'version' => $t->version, 'updated' => $t->updated_at ? $t->updated_at->diffForHumans() : '', 'design' => $t->design];
  })->values();
  $presetDesigns = collect(\App\Support\CardPresets::all())->map(function ($p) { return $p[1]; });
@endphp
@include('backend.pages.card-studio.assets')
<script>
(function () {
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var base = @json(url('/') . '/');
  var icons = null;
  var sample = @json($sample);
  var templates = @json($tplData);
  var presets = @json($presetDesigns);
  var sizes = @json($sizes);
  var urls = { store: @json(route('card-studio.store')), edit: @json(url('card-studio')) + '/' };

  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function req(method, url, body) {
    return fetch(url, { method: method, headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : undefined })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (res) { if (!res.ok) { var e = res.d.errors || {}; var k = Object.keys(e)[0]; Swal.fire('Not done', k ? e[k][0] : (res.d.message || 'Please try again.'), 'warning'); return null; } return res.d; });
  }
  function sizeName(d) {
    for (var k in sizes) if (Math.abs(sizes[k][1] - d.width) < 0.05 && Math.abs(sizes[k][2] - d.height) < 0.05) return sizes[k][0].split(' (')[0];
    return d.width + ' × ' + d.height + ' mm';
  }
  function thumb(design, side, height) {
    var px = height * design.width / design.height;
    return CardRenderer.render(design, side, sample, { icons: icons, base: base, trim: true, widthPx: Math.round(px) });
  }

  function rule(t) {
    if (t.event && t.category) return '<i class="bi bi-funnel"></i> ' + esc(t.event) + ' · ' + esc(t.category) + ' only';
    if (t.event) return '<i class="bi bi-calendar-event"></i> Everyone at ' + esc(t.event);
    if (t.is_default) return '<i class="bi bi-globe2"></i> Everyone without a more specific template';
    return '<i class="bi bi-pause-circle"></i> Not in use (not default, no event)';
  }

  function render() {
    var grid = document.getElementById('cs-grid');
    grid.innerHTML = templates.map(function (t) {
      return '<div class="col-sm-6 col-xl-4"><div class="cs-tile">' +
        '<div class="cs-thumbs" data-open="' + t.id + '" title="Open in the studio" id="cs-th-' + t.id + '"></div>' +
        '<div class="cs-meta"><div class="d-flex justify-content-between gap-2"><div class="cs-name">' + esc(t.name) + '</div>' +
        '<div class="dropdown"><button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button><ul class="dropdown-menu dropdown-menu-end">' +
        '<li><a class="dropdown-item" href="' + urls.edit + t.id + '"><i class="bi bi-pencil-square"></i> Open in studio</a></li>' +
        '<li><button class="dropdown-item" data-set="' + t.id + '"><i class="bi bi-sliders"></i> Settings (event, category, default)</button></li>' +
        '<li><button class="dropdown-item" data-dup="' + t.id + '"><i class="bi bi-files"></i> Duplicate</button></li>' +
        '<li><a class="dropdown-item" href="' + @json(route('card-studio.preview')) + '?template=' + t.id + '"><i class="bi bi-people"></i> Preview with participants</a></li>' +
        '<li><hr class="dropdown-divider"></li><li><button class="dropdown-item text-danger" data-del="' + t.id + '"' + (t.is_default ? ' disabled' : '') + '><i class="bi bi-trash"></i> Delete</button></li></ul></div></div>' +
        '<div class="cs-badges d-flex flex-wrap gap-1">' + (t.is_default ? '<span class="badge bg-primary">Default</span>' : '') +
        (t.event ? '<span class="badge bg-info-subtle text-info-emphasis">' + esc(t.event) + '</span>' : '') + (t.category ? '<span class="badge bg-warning-subtle text-warning-emphasis">' + esc(t.category) + '</span>' : '') +
        '<span class="badge bg-light text-dark">' + esc(sizeName(t.design)) + '</span><span class="badge bg-light text-dark">' + (t.design.double_sided ? 'Two-sided' : 'One-sided') + '</span></div>' +
        '<div class="cs-rule">' + rule(t) + '</div>' +
        '<div class="small text-muted mt-auto">Version ' + t.version + ' · updated ' + esc(t.updated || '') + '</div></div></div></div>';
    }).join('') || '<div class="col-12 text-center text-muted py-5">No templates yet.</div>';
    templates.forEach(function (t) {
      var box = document.getElementById('cs-th-' + t.id), h = t.design.height > t.design.width ? 180 : 120;
      box.appendChild(thumb(t.design, 'front', h));
      if (t.design.double_sided) box.appendChild(thumb(t.design, 'back', h));
    });
    grid.querySelectorAll('[data-open]').forEach(function (b) { b.addEventListener('click', function () { location.href = urls.edit + b.dataset.open; }); });
    grid.querySelectorAll('[data-set]').forEach(function (b) { b.addEventListener('click', function () { openSettings(+b.dataset.set); }); });
    grid.querySelectorAll('[data-dup]').forEach(function (b) { b.addEventListener('click', function () {
      req('POST', urls.edit + b.dataset.dup + '/duplicate').then(function (d) { if (d) location.href = d.edit; });
    }); });
    grid.querySelectorAll('[data-del]').forEach(function (b) { b.addEventListener('click', function () {
      var t = templates.find(function (x) { return x.id === +b.dataset.del; });
      Swal.fire({ title: 'Delete "' + t.name + '"?', text: 'The template and all its versions will be removed.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#d33' })
        .then(function (r) { if (r.isConfirmed) req('DELETE', urls.edit + t.id).then(function (d) { if (d) { templates = templates.filter(function (x) { return x.id !== t.id; }); render(); } }); });
    }); });
  }

  // settings
  var setModal = new bootstrap.Modal(document.getElementById('csSet')), setId = null, sf = document.getElementById('cs-set-form');
  function catVisible() { document.getElementById('cs-cat-wrap').style.display = sf.elements.event_id.value ? '' : 'none'; }
  sf.elements.event_id.addEventListener('change', catVisible);
  function openSettings(id) {
    var t = templates.find(function (x) { return x.id === id; }); setId = id;
    sf.elements.name.value = t.name; sf.elements.event_id.value = t.event_id || ''; sf.elements.category.value = t.category || ''; sf.elements.is_default.checked = !!t.is_default;
    catVisible(); setModal.show();
  }
  sf.addEventListener('submit', function (ev) {
    ev.preventDefault();
    req('POST', urls.edit + setId + '/settings', { name: sf.elements.name.value, event_id: sf.elements.event_id.value ? +sf.elements.event_id.value : null,
      category: sf.elements.event_id.value ? sf.elements.category.value : null, is_default: sf.elements.is_default.checked })
      .then(function (d) { if (d) { setModal.hide(); Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: d.message, showConfirmButton: false, timer: 2500 }).then(function () {}); setTimeout(function () { location.reload(); }, 900); } });
  });

  // new template
  var newModal = new bootstrap.Modal(document.getElementById('csNew')), nf = document.getElementById('cs-new-form'), pick = null;
  document.getElementById('cs-new-btn').addEventListener('click', function () { newModal.show(); });
  document.querySelectorAll('.cs-pick').forEach(function (p) {
    p.addEventListener('click', function () { document.querySelectorAll('.cs-pick').forEach(function (x) { x.classList.toggle('is-on', x === p); }); pick = p; });
  });
  nf.addEventListener('submit', function (ev) {
    ev.preventDefault();
    if (!pick) { Swal.fire('Choose a start', 'Pick a starter design, a blank card or a template to copy.', 'info'); return; }
    var body = { name: nf.elements.name.value, from: pick.dataset.from };
    if (body.from === 'preset') body.preset = pick.dataset.value;
    if (body.from === 'blank') body.size = nf.elements.size.value;
    if (body.from === 'copy') body.copy_id = +nf.elements.copy_id.value;
    req('POST', urls.store, body).then(function (d) { if (d) location.href = d.edit; });
  });

  fetch(@json(asset('assets/backend/card-studio/icons.json'))).then(function (r) { return r.json(); }).then(function (j) { icons = j; })
    .catch(function () { icons = {}; })
    .then(function () { return CardRenderer.ready({ front: { elements: [] } }); })
    .then(function () {
      return Promise.all(templates.map(function (t) { return CardRenderer.ready(t.design); }));
    })
    .then(function () {
      render();
      document.querySelectorAll('[data-preset]').forEach(function (box) { var d = presets[box.dataset.preset]; box.appendChild(thumb(d, 'front', d.height > d.width ? 110 : 70)); });
    });
})();
</script>
@endpush
