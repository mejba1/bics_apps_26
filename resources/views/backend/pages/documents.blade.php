@extends('backend.layouts.master')
@section('title', 'Documents')
@php $base = rtrim(url('/'), '/') . '/'; @endphp
@push('styles')
<style>
  .doc-card { border: 1px solid var(--ad-line); border-radius: 18px; background: var(--ad-surface); padding: 16px 18px; display: flex; gap: 16px; align-items: center; flex-wrap: wrap; }
  .doc-card + .doc-card { margin-top: 12px; }
  .doc-card.off { opacity: .7; }
  .doc-ic { width: 54px; height: 64px; border-radius: 10px; background: #fdecee; color: #c0392b; display: inline-flex; align-items: center; justify-content: center; font-size: 1.7rem; flex: none; position: relative; }
  .doc-ic small { position: absolute; bottom: 5px; font-size: 9px; font-weight: 800; letter-spacing: .05em; }
  .doc-tx { flex: 1; min-width: 240px; }
  .doc-tx h3 { font-size: 1.05rem; font-weight: 800; margin: 0 0 4px; color: var(--ad-ink); display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
  .doc-link { display: inline-flex; align-items: center; gap: 6px; font-family: Consolas, ui-monospace, monospace; font-size: 13.5px; background: var(--ad-brand-50); color: var(--ad-brand); border-radius: 8px; padding: 3px 9px; text-decoration: none; word-break: break-all; }
  .doc-meta { font-size: 12.5px; color: var(--ad-muted); margin-top: 6px; display: flex; flex-wrap: wrap; gap: 4px 14px; }
  .doc-acts { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
  .doc-empty { text-align: center; padding: 50px 16px; border: 2px dashed var(--ad-line); border-radius: 18px; color: var(--ad-muted); background: var(--ad-surface); }
  .doc-empty .big { font-size: 2.8rem; } .doc-empty b { color: var(--ad-ink); display: block; font-size: 1.1rem; margin: 6px 0 4px; }
  .doc-drop { border: 2px dashed var(--ad-line); border-radius: 14px; padding: 18px; text-align: center; color: var(--ad-muted); cursor: pointer; transition: border-color .15s, background .15s; }
  .doc-drop:hover, .doc-drop.over { border-color: var(--ad-brand); background: var(--ad-brand-50); }
  .doc-drop i { font-size: 1.8rem; color: #c0392b; } .doc-drop b { color: var(--ad-ink); display: block; }
  .doc-prefix { font-family: Consolas, monospace; font-size: 13px; }
  .dm .modal-content { border: 0; border-radius: 20px; background: var(--ad-surface); }
  .dm .modal-header { background: none !important; } .dm .modal-title { color: var(--ad-ink) !important; }
  html[data-theme="dark"] .dm .btn-close { filter: invert(1); }
</style>
@endpush
@section('content')
<div class="pagetitle d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div>
    <h1>Documents</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li><li class="breadcrumb-item">Website</li><li class="breadcrumb-item active">Documents</li></ol></nav>
  </div>
  <button class="btn btn-primary" data-new><i class="bi bi-cloud-arrow-up"></i> Upload a PDF</button>
</div>
<section class="section">
  <div class="alert alert-info small py-2"><i class="bi bi-info-circle"></i> Each PDF gets its own short link, e.g. <code>{{ $base }}Itenary-2026</code> — share it, print its QR code, or link it from the website menu. <b>Replace the PDF</b> any time: the link stays the same.</div>
  @forelse($docs as $d)
    @php
      $url = $base . $d->slug; $size = $d->size >= 1048576 ? number_format($d->size / 1048576, 1) . ' MB' : max(1, round($d->size / 1024)) . ' KB';
      $json = ['id' => $d->id, 'title' => $d->title, 'slug' => $d->slug, 'description' => $d->description, 'active' => (bool) $d->active, 'allow_download' => (bool) $d->allow_download, 'file' => $d->original_name];
    @endphp
    <div class="doc-card {{ $d->active ? '' : 'off' }}">
      <span class="doc-ic"><i class="bi bi-file-earmark-pdf-fill"></i><small>PDF</small></span>
      <div class="doc-tx">
        <h3>{{ $d->title }} @if(!$d->active)<span class="badge bg-secondary">Off</span>@endif</h3>
        <a class="doc-link" href="{{ $url }}" target="_blank" rel="noopener"><i class="bi bi-link-45deg"></i>{{ preg_replace('#^https?://#', '', $url) }}</a>
        <div class="doc-meta">
          <span><i class="bi bi-file-earmark"></i> {{ $d->original_name }} · {{ $size }}</span>
          <span><i class="bi bi-eye"></i> {{ number_format($d->views) }} views</span>
          <span><i class="bi bi-download"></i> {{ number_format($d->downloads) }} downloads</span>
          <span><i class="bi bi-clock-history"></i> updated {{ \Carbon\Carbon::parse($d->file_updated_at ?? $d->updated_at)->format('j M Y, H:i') }}{{ $d->by ? ' by ' . $d->by : '' }}</span>
        </div>
      </div>
      <div class="doc-acts">
        <div class="form-check form-switch m-0 me-1" title="Link on / off"><input class="form-check-input" type="checkbox" role="switch" data-toggle="{{ $d->id }}" @checked($d->active) aria-label="Link on or off"></div>
        <button class="btn btn-sm btn-outline-primary" data-copy="{{ $url }}"><i class="bi bi-copy"></i> Copy link</button>
        <div class="btn-group btn-group-sm"><button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-qr-code"></i> QR</button>
          <ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="{{ route('documents.qr', [$d->id, 'png']) }}">PNG (print, slides)</a></li><li><a class="dropdown-item" href="{{ route('documents.qr', [$d->id, 'svg']) }}">SVG (vector)</a></li></ul></div>
        <button class="btn btn-sm btn-outline-secondary" data-edit='@json($json)'><i class="bi bi-arrow-repeat"></i> Replace / edit</button>
        <button class="btn btn-sm btn-outline-danger" data-del="{{ $d->id }}" data-name="{{ $d->slug }}" title="Delete" aria-label="Delete"><i class="bi bi-trash3"></i></button>
      </div>
    </div>
  @empty
    <div class="doc-empty"><div class="big">📄</div><b>No documents yet</b>Upload a PDF (e.g. the itinerary) and give it a short link like <code>Itenary-2026</code>.<br>
      <button class="btn btn-primary mt-3" data-new><i class="bi bi-cloud-arrow-up"></i> Upload a PDF</button></div>
  @endforelse
</section>

<div class="modal fade dm" id="docModal" tabindex="-1" aria-labelledby="dm-t" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><form class="modal-content" id="docForm" novalidate>
  <div class="modal-header border-0"><h5 class="modal-title fw-bold" id="dm-t">Upload a PDF</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <div class="modal-body pt-0">
    <input type="hidden" name="id">
    <label class="doc-drop mb-3" id="doc-drop" for="doc-file">
      <i class="bi bi-file-earmark-pdf"></i><b id="doc-file-name">Choose the PDF or drop it here</b>
      <span class="small">PDF only · up to {{ $maxMb }} MB</span>
      <input type="file" id="doc-file" name="file" accept="application/pdf,.pdf" hidden>
    </label>
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label fw-semibold" for="doc-title">Title</label><input class="form-control" id="doc-title" name="title" maxlength="160" placeholder="BICS 2026 Itinerary" required></div>
      <div class="col-md-6"><label class="form-label fw-semibold" for="doc-slug">Link</label>
        <div class="input-group"><span class="input-group-text doc-prefix">{{ preg_replace('#^https?://#', '', $base) }}</span><input class="form-control doc-prefix" id="doc-slug" name="slug" maxlength="80" placeholder="Itenary-2026" required></div>
        <div class="form-text">Letters, numbers, - or _ (no spaces). Not case-sensitive for visitors.</div></div>
      <div class="col-12"><label class="form-label fw-semibold" for="doc-desc">Short description <span class="text-muted fw-normal">(optional)</span></label><input class="form-control" id="doc-desc" name="description" maxlength="500" placeholder="Programme, venue map and transport for 28 October 2026"></div>
      <div class="col-12 d-flex gap-4 flex-wrap">
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="doc-active" name="active" checked><label class="form-check-label" for="doc-active">Link is live</label></div>
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="doc-dl" name="allow_download" checked><label class="form-check-label" for="doc-dl">Show a Download button</label></div>
      </div>
    </div>
  </div>
  <div class="modal-footer border-0 pt-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> <span>Upload</span></button></div>
</form></div></div>
@endsection
@push('scripts')
<script>
(function () {
  'use strict';
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var R = { save: @json(route('documents.save')), base: @json(url('documents')) };
  var modal = new bootstrap.Modal(document.getElementById('docModal')), f = document.getElementById('docForm'), slugTouched = false;
  function $(id) { return document.getElementById(id); }
  function toast(m, i) { Swal.fire({ toast: true, position: 'top-end', icon: i || 'success', title: m, showConfirmButton: false, timer: 3000 }); }
  function slugify(t) { return t.normalize('NFKD').replace(/[̀-ͯ]/g, '').trim().replace(/[^A-Za-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80); }
  function open(d) {
    f.reset(); f.id.value = d ? d.id : ''; slugTouched = !!d;
    f.title.value = d ? d.title : ''; f.slug.value = d ? d.slug : ''; f.description.value = d && d.description ? d.description : '';
    f.active.checked = d ? d.active : true; f.allow_download.checked = d ? d.allow_download : true;
    $('doc-file-name').textContent = d ? 'Current: ' + (d.file || 'PDF') + ' — choose a new PDF to replace it (optional)' : 'Choose the PDF or drop it here';
    $('dm-t').textContent = d ? 'Edit /' + d.slug : 'Upload a PDF';
    f.querySelector('[type=submit] span').textContent = d ? 'Save' : 'Upload';
    modal.show(); setTimeout(function () { (d ? f.title : $('doc-file')).focus(); }, 300);
  }
  document.querySelectorAll('[data-new]').forEach(function (b) { b.addEventListener('click', function () { open(null); }); });
  document.querySelectorAll('[data-edit]').forEach(function (b) { b.addEventListener('click', function () { open(JSON.parse(b.dataset.edit)); }); });
  f.slug.addEventListener('input', function () { slugTouched = true; f.slug.value = f.slug.value.replace(/\s+/g, '-').replace(/[^A-Za-z0-9_-]/g, ''); });
  f.title.addEventListener('input', function () { if (!slugTouched) f.slug.value = slugify(f.title.value); });
  function picked(file) {
    if (!file) return;
    if (!/\.pdf$/i.test(file.name) && file.type !== 'application/pdf') { Swal.fire('PDF only', 'Please choose a PDF file.', 'warning'); return; }
    var dt = new DataTransfer(); dt.items.add(file); $('doc-file').files = dt.files;
    $('doc-file-name').textContent = file.name + ' · ' + (file.size / 1048576).toFixed(1) + ' MB';
    if (!f.title.value) { f.title.value = file.name.replace(/\.pdf$/i, '').replace(/[_-]+/g, ' '); if (!slugTouched) f.slug.value = slugify(f.title.value); }
  }
  $('doc-file').addEventListener('change', function () { picked(this.files[0]); });
  var drop = $('doc-drop');
  ['dragenter', 'dragover'].forEach(function (t) { drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.add('over'); }); });
  ['dragleave', 'drop'].forEach(function (t) { drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.remove('over'); }); });
  drop.addEventListener('drop', function (e) { picked(e.dataTransfer.files[0]); });
  f.addEventListener('submit', function (e) {
    e.preventDefault();
    var fd = new FormData(f); fd.set('active', f.active.checked ? 1 : 0); fd.set('allow_download', f.allow_download.checked ? 1 : 0);
    if (!$('doc-file').files.length) fd.delete('file');
    var btn = f.querySelector('[type=submit]'); btn.disabled = true; var old = btn.innerHTML; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Uploading…';
    fetch(R.save, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: fd })
      .then(function (r) { return r.json().catch(function () { return { message: r.status === 413 ? 'The file is too large for the server.' : 'Please try again.' }; }).then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (res) {
        btn.disabled = false; btn.innerHTML = old;
        if (!res.ok) { var er = res.d.errors || {}, k = Object.keys(er)[0]; return Swal.fire('Not saved', k ? er[k][0] : (res.d.message || 'Please try again.'), 'warning'); }
        modal.hide();
        Swal.fire({ icon: 'success', title: 'Done', html: res.d.message + '<br><a href="' + res.d.url + '" target="_blank" rel="noopener">Open the link</a>' }).then(function () { location.reload(); });
      }).catch(function () { btn.disabled = false; btn.innerHTML = old; Swal.fire('Not saved', 'Check your connection and try again.', 'warning'); });
  });
  document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () { (navigator.clipboard ? navigator.clipboard.writeText(b.dataset.copy) : Promise.reject()).then(function () { toast('Link copied'); }, function () { prompt('Copy this link:', b.dataset.copy); }); });
  });
  document.querySelectorAll('[data-toggle]').forEach(function (c) {
    c.addEventListener('change', function () {
      fetch(R.base + '/' + c.dataset.toggle + '/toggle', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ active: c.checked }) })
        .then(function (r) { return r.json(); }).then(function (d) { toast(d.message, c.checked ? 'success' : 'info'); c.closest('.doc-card').classList.toggle('off', !c.checked); });
    });
  });
  document.querySelectorAll('[data-del]').forEach(function (b) {
    b.addEventListener('click', function () {
      Swal.fire({ title: 'Delete /' + b.dataset.name + '?', text: 'The PDF is removed and the link stops working.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#d33' })
        .then(function (r) { if (r.isConfirmed) fetch(R.base + '/' + b.dataset.del, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } }).then(function () { location.reload(); }); });
    });
  });
})();
</script>
@endpush
