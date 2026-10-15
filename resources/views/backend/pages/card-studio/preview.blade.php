@extends('backend.layouts.master')
@section('title')
Card previews · Card Design Studio
@endsection
@push('styles')
<style>
  .pv-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
  .pv-item { background: var(--ad-surface); border: 1px solid var(--ad-line); border-radius: 14px; overflow: hidden; }
  .pv-cards { background: var(--ad-subtle); padding: 16px; display: flex; gap: 10px; justify-content: center; align-items: center; min-height: 240px; }
  .pv-cards svg { filter: drop-shadow(0 4px 10px rgba(16,26,46,.18)); }
  .pv-meta { padding: 10px 12px; font-size: 13px; }
  .pv-tags { position: relative; }
  .pv-tag { display: inline-flex; align-items: center; gap: 4px; background: #e7effb; color: var(--ad-brand); border-radius: 20px; padding: 2px 4px 2px 10px; font-size: 12px; margin: 2px; }
  .pv-tag button { border: 0; background: none; color: inherit; padding: 0 4px; }
  .pv-list { position: absolute; z-index: 20; background: var(--ad-surface); border: 1px solid var(--ad-line); border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,.12); max-height: 300px; overflow: auto; display: none; width: 340px; }
  .pv-list button { display: block; width: 100%; text-align: left; border: 0; background: none; padding: 6px 10px; font-size: 13px; }
  .pv-list button:hover { background: var(--ad-hover); }
</style>
@endpush
@section('content')
<div class="pagetitle d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div>
    <h1>Card previews</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li><li class="breadcrumb-item"><a href="{{ route('card-studio.index') }}">Card Design Studio</a></li><li class="breadcrumb-item active">Previews</li></ol></nav>
  </div>
</div>

<section class="section">
  <div class="card"><div class="card-body pt-3">
    <form class="row g-2 align-items-end" id="pv-form" method="get">
      <div class="col-md-3">
        <label class="form-label small fw-semibold mb-1">Event</label>
        <select class="form-select form-select-sm" name="event">@foreach($events as $e)<option value="{{ $e->id }}" @selected($event && $event->id === $e->id)>{{ $e->event_short }}{{ $e->is_current ? ' (current)' : '' }}</option>@endforeach</select>
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold mb-1">Template</label>
        <select class="form-select form-select-sm" name="template"><option value="">Each participant's own (automatic)</option>@foreach($all as $t)<option value="{{ $t->id }}" @selected($only && $only->id === $t->id)>{{ $t->name }}</option>@endforeach</select>
      </div>
      <div class="col-md-6">
        <label class="form-label small fw-semibold mb-1">Participants <span class="text-muted fw-normal">(empty = the first 12)</span></label>
        <div class="pv-tags">
          <div id="pv-tags"></div>
          <input class="form-control form-control-sm" id="pv-q" placeholder="Add a participant: name, ID, email…" autocomplete="off">
          <div class="pv-list" id="pv-list"></div>
        </div>
        <input type="hidden" name="ids" id="pv-ids" value="{{ implode(',', $ids) }}">
      </div>
      <div class="col-12 d-flex gap-2"><button class="btn btn-sm btn-primary"><i class="bi bi-arrow-repeat"></i> Show previews</button>
        <div class="form-check form-switch ms-2 align-self-center"><input class="form-check-input" type="checkbox" id="pv-fields"><label class="form-check-label small" for="pv-fields">Highlight empty fields</label></div></div>
    </form>
  </div></div>

  <div class="card mb-3"><div class="card-body pt-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
      <div class="d-flex align-items-center gap-3 flex-wrap">
        <div class="form-check m-0"><input class="form-check-input" type="checkbox" id="pv-all"><label class="form-check-label small fw-semibold" for="pv-all">Select all shown</label></div>
        <span class="small text-muted"><b id="pv-sel-n">0</b> selected</span>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-sm btn-primary" id="pv-dl-sel" disabled><i class="bi bi-download"></i> Download selected</button>
        <button type="button" class="btn btn-sm btn-outline-primary" id="pv-dl-all"><i class="bi bi-file-earmark-zip"></i> Download all shown</button>
      </div>
    </div>
    @include('backend.pages.card-studio.download-panel', ['id' => 'cdl'])
  </div></div>

  <div class="pv-grid" id="pv-grid"></div>
</section>
@endsection

@push('scripts')
@php
  $tagData = $cards->filter(function ($c) use ($ids) { return in_array($c['data']['id'], $ids); })->map(function ($c) { return ['id' => $c['data']['id'], 'name' => $c['name'], 'code' => $c['code']]; })->values();
@endphp
@include('backend.pages.card-studio.assets')
<script>
(function () {
  var cards = @json($cards), icons = @json($icons), base = @json(url('/') . '/');
  window.CARD_ICONS = icons;
  var picked = {};
  var form = document.getElementById('pv-form');
  // the template / event the previews were made with (not unsaved changes in the form)
  function ctx() { return { templateId: @json($only ? $only->id : null), eventId: @json($event ? $event->id : null), label: 'cards' }; }
  function cardItem(c) { var t = templates[c.template]; return t ? { id: c.data.id, name: c.name, code: c.code, design: t.design, data: c.data } : null; }
  function syncSel() {
    var n = Object.keys(picked).length;
    document.getElementById('pv-sel-n').textContent = n;
    document.getElementById('pv-dl-sel').disabled = !n;
    document.getElementById('pv-dl-sel').innerHTML = '<i class="bi bi-download"></i> Download selected' + (n ? ' (' + n + ')' : '');
    var all = document.getElementById('pv-all'), k = cards.filter(function (c) { return picked[c.data.id]; }).length;
    all.checked = cards.length > 0 && k === cards.length; all.indeterminate = k > 0 && k < cards.length;
  }
  function runFor(list) {
    var items = list.map(cardItem).filter(Boolean);
    CardDownloads.run(items, items.map(function (i) { return i.id; }), ctx());
  }
  var templates = {}; @json($templates).forEach(function (t) { templates[t.id] = t; });
  var tags = @json($tagData);
  var studio = @json(url('card-studio')) + '/';
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

  function usedFields(design) {
    var keys = {};
    ['front', 'back'].forEach(function (s) { (design[s].elements || []).forEach(function (e) { if (e.type === 'text' && !e.hidden) (e.text || '').replace(/\{\{\s*([a-z_]+)\s*\}\}/g, function (m, k) { keys[k] = 1; }); }); });
    return Object.keys(keys);
  }

  function draw() {
    var grid = document.getElementById('pv-grid'), hl = document.getElementById('pv-fields').checked;
    grid.innerHTML = '';
    if (!cards.length) { grid.innerHTML = '<div class="text-muted">No participants to show.</div>'; return; }
    cards.forEach(function (c) {
      var t = templates[c.template], item = document.createElement('div');
      item.className = 'pv-item';
      var box = document.createElement('div'); box.className = 'pv-cards'; item.appendChild(box);
      if (t) {
        var h = t.design.height > t.design.width ? 200 : 125;
        ['front', 'back'].forEach(function (s) {
          if (s === 'back' && !t.design.double_sided) return;
          box.appendChild(CardRenderer.render(t.design, s, c.data, { icons: icons, base: base, trim: true, widthPx: Math.round(h * t.design.width / t.design.height) }));
        });
      } else box.innerHTML = '<span class="text-muted small">No template</span>';
      var empty = t ? usedFields(t.design).filter(function (k) { return !c.data.values[k]; }) : [];
      var meta = document.createElement('div'); meta.className = 'pv-meta';
      meta.innerHTML = '<div class="d-flex justify-content-between gap-2"><div><b>' + esc(c.name) + '</b> <span class="text-muted">' + esc(c.code) + '</span></div>' +
        (t ? '<a class="small" href="' + studio + t.id + '">' + esc(t.name) + '</a>' : '') + '</div>' +
        (hl && empty.length ? '<div class="small text-warning-emphasis mt-1"><i class="bi bi-exclamation-circle"></i> Empty: ' + empty.map(esc).join(', ') + '</div>' : '') +
        (hl && !c.data.photo ? '<div class="small text-muted"><i class="bi bi-person-bounding-box"></i> No photo (initials shown)</div>' : '');
      if (t) {
        var dl = document.createElement('div'); dl.className = 'd-flex justify-content-between align-items-center gap-2 mt-2 flex-wrap';
        dl.innerHTML = '<div class="form-check m-0"><input class="form-check-input" type="checkbox" data-pick="' + c.data.id + '"' + (picked[c.data.id] ? ' checked' : '') + ' id="pk' + c.data.id + '"><label class="form-check-label small" for="pk' + c.data.id + '">Select</label></div>' +
          '<div class="cdl-one" role="group" aria-label="Download ' + esc(c.name) + '">' + ['png', 'jpg', 'pdf', 'eps'].map(function (f) { return '<button type="button" data-one="' + f + '" title="Download ' + f.toUpperCase() + '">' + f.toUpperCase() + '</button>'; }).join('') + '</div>';
        dl.querySelector('[data-pick]').addEventListener('change', function () { if (this.checked) picked[c.data.id] = 1; else delete picked[c.data.id]; syncSel(); });
        dl.querySelectorAll('[data-one]').forEach(function (b) { b.addEventListener('click', function () { CardDownloads.one(cardItem(c), c.data.id, b.dataset.one, ctx()); }); });
        meta.appendChild(dl);
      }
      item.appendChild(meta);
      grid.appendChild(item);
    });
  }

  // participant picker
  function syncTags() {
    document.getElementById('pv-tags').innerHTML = tags.map(function (p, i) { return '<span class="pv-tag">' + esc(p.name) + ' <span class="opacity-75">' + esc(p.code) + '</span><button type="button" data-rm="' + i + '">&times;</button></span>'; }).join('');
    document.getElementById('pv-ids').value = tags.map(function (p) { return p.id; }).join(',');
    document.querySelectorAll('[data-rm]').forEach(function (b) { b.addEventListener('click', function () { tags.splice(+b.dataset.rm, 1); syncTags(); }); });
  }
  var timer, q = document.getElementById('pv-q'), list = document.getElementById('pv-list');
  q.addEventListener('input', function () {
    clearTimeout(timer); var v = q.value.trim();
    if (v.length < 2) { list.style.display = 'none'; return; }
    timer = setTimeout(function () {
      fetch(@json(route('card-studio.participants')) + '?q=' + encodeURIComponent(v), { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (rows) {
        list.innerHTML = rows.map(function (p, i) { return '<button type="button" data-i="' + i + '"><b>' + esc(p.name) + '</b> <span class="text-muted">' + esc(p.code) + '</span>' + (p.category ? ' · ' + esc(p.category) : '') + '</button>'; }).join('') || '<div class="p-2 small text-muted">No participants found.</div>';
        list.style.display = 'block';
        list.querySelectorAll('[data-i]').forEach(function (b) { b.addEventListener('mousedown', function (ev) {
          ev.preventDefault(); var p = rows[+b.dataset.i];
          if (!tags.some(function (t) { return t.id === p.id; }) && tags.length < 60) tags.push({ id: p.id, name: p.name, code: p.code });
          q.value = ''; list.style.display = 'none'; syncTags();
        }); });
      });
    }, 250);
  });
  q.addEventListener('blur', function () { setTimeout(function () { list.style.display = 'none'; }, 150); });
  q.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') ev.preventDefault(); });
  document.getElementById('pv-fields').addEventListener('change', draw);
  document.getElementById('pv-all').addEventListener('change', function () { var on = this.checked; cards.forEach(function (c) { if (on) picked[c.data.id] = 1; else delete picked[c.data.id]; }); draw(); syncSel(); });
  document.getElementById('pv-dl-sel').addEventListener('click', function () { runFor(cards.filter(function (c) { return picked[c.data.id]; })); });
  document.getElementById('pv-dl-all').addEventListener('click', function () { runFor(cards); });
  syncTags();

  Promise.all(Object.keys(templates).map(function (id) { return CardRenderer.ready(templates[id].design); })).then(draw);
})();
</script>
@endpush
