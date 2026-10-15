@extends('backend.layouts.master')
@section('title', 'Social icons')
@push('styles')
<style>
  .si-card { border: 1px solid var(--ad-line); border-radius: 18px; background: var(--ad-surface); }
  .si-h { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 16px 18px 6px; flex-wrap: wrap; }
  .si-h h3 { font-size: 1rem; font-weight: 800; margin: 0; color: var(--ad-ink); } .si-h small { color: var(--ad-muted); }
  .si-b { padding: 8px 18px 18px; }
  .si-row { display: flex; gap: 12px; align-items: center; padding: 12px 0; border-top: 1px solid var(--ad-line-soft); }
  .si-row:first-child { border-top: 0; }
  .si-ic { width: 44px; height: 44px; border-radius: 13px; flex: none; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 1.2rem; overflow: hidden; background: var(--c, #475569); }
  .si-ic img { width: 100%; height: 100%; object-fit: cover; }
  .si-tx { flex: 1; min-width: 0; } .si-tx b { color: var(--ad-ink); display: block; } .si-tx span { font-size: 12.5px; color: var(--ad-muted); }
  .si-dom { display: inline-block; font-family: Consolas, monospace; font-size: 12px; background: var(--ad-chip); color: var(--ad-text); border-radius: 6px; padding: 1px 7px; margin: 3px 4px 0 0; }
  .si-n { font-size: 12px; color: var(--ad-muted); white-space: nowrap; }
  .si-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px; }
  .si-mini { display: flex; gap: 8px; align-items: center; padding: 8px; border: 1px solid var(--ad-line); border-radius: 12px; font-size: 13px; color: var(--ad-text); }
  .si-mini .si-ic { width: 30px; height: 30px; font-size: .9rem; border-radius: 9px; }
  .si-empty { text-align: center; color: var(--ad-muted); padding: 30px 10px; } .si-empty .big { font-size: 2.4rem; }
  .si-pick { display: grid; grid-template-columns: repeat(10, 1fr); gap: 5px; max-height: 190px; overflow: auto; padding: 2px; }
  .si-pick button { aspect-ratio: 1; border: 1.5px solid var(--ad-line); border-radius: 9px; background: var(--ad-surface); color: var(--ad-text); display: inline-flex; align-items: center; justify-content: center; padding: 0; }
  .si-pick button.on { border-color: var(--ad-brand); background: var(--ad-brand-50); color: var(--ad-brand); }
  .si-colors { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
  .si-colors button { width: 28px; height: 28px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 0 0 1px var(--ad-line); padding: 0; }
  .si-colors button.on { box-shadow: 0 0 0 2px var(--ad-brand); }
  .si-preview { display: flex; gap: 12px; align-items: center; padding: 12px; border-radius: 14px; background: var(--ad-bg); border: 1px dashed var(--ad-line); }
  .si-modal .modal-content { border-radius: 20px; border: 0; background: var(--ad-surface); }
  .si-modal .modal-header { background: none !important; } .si-modal .modal-title { color: var(--ad-ink) !important; }
  html[data-theme="dark"] .si-modal .btn-close { filter: invert(1); }
  @media (max-width: 575.98px) { .si-pick { grid-template-columns: repeat(7, 1fr); } }
</style>
@endpush
@section('content')
<div class="pagetitle d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div>
    <h1>Social icons</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li><li class="breadcrumb-item"><a href="{{ route('participants.index') }}">Participants</a></li><li class="breadcrumb-item active">Social icons</li></ol></nav>
  </div>
  <button class="btn btn-primary" data-new><i class="bi bi-plus-lg"></i> Add a platform</button>
</div>

<section class="section">
  <div class="alert alert-info small py-2"><i class="bi bi-info-circle"></i> When someone adds a link in <b>Social media &amp; links</b> (admin or participant dashboard), its icon is chosen from the web address. Add platforms here for sites that are not built in — e.g. <b>Kaggle</b>, your <b>hospital</b> website, a <b>journal</b>. Everyone can also pick a different icon or colour for a single link by clicking its icon.</div>
  <div class="row g-3">
    <div class="col-lg-7">
      <div class="si-card">
        <div class="si-h"><div><h3>Your platforms</h3><small>Checked first, before the built-in ones</small></div></div>
        <div class="si-b" id="si-list">
          @forelse($custom as $p)
            @php $img = !empty($p['image']) && is_file(base_path($p['image'])) ? asset($p['image']) . '?v=' . filemtime(base_path($p['image'])) : null; @endphp
            <div class="si-row">
              <span class="si-ic" style="--c: {{ $p['color'] }}">@if($img)<img src="{{ $img }}" alt="">@else<i class="bi {{ $p['icon'] }}"></i>@endif</span>
              <div class="si-tx"><b>{{ $p['name'] }}</b>@foreach($p['domains'] as $d)<span class="si-dom">{{ $d }}</span>@endforeach</div>
              <span class="si-n">{{ $usage[$p['type']] ?? 0 }} {{ \Illuminate\Support\Str::plural('link', $usage[$p['type']] ?? 0) }}</span>
              <button class="btn btn-sm btn-outline-secondary" data-edit='@json($p + ['image_url' => $img])' title="Edit"><i class="bi bi-pencil"></i></button>
              <button class="btn btn-sm btn-outline-danger" data-del="{{ $p['type'] }}" data-name="{{ $p['name'] }}" title="Remove"><i class="bi bi-trash3"></i></button>
            </div>
          @empty
            <div class="si-empty"><div class="big">🔗</div><b style="color:var(--ad-ink)">No platforms added yet</b><br>Add one for any site you want a proper icon for.</div>
          @endforelse
        </div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="si-card">
        <div class="si-h"><div><h3>Built in</h3><small>Recognised automatically</small></div></div>
        <div class="si-b"><div class="si-grid">
          @foreach($builtin as $type => [$name, $icon, $color])
            <div class="si-mini"><span class="si-ic" style="--c: {{ $color }}"><i class="bi {{ $icon }}"></i></span><span class="text-truncate">{{ $name }}</span><span class="si-n ms-auto">{{ $usage[$type] ?? 0 }}</span></div>
          @endforeach
        </div></div>
      </div>
    </div>
  </div>
</section>

<div class="modal fade si-modal" id="si-modal" tabindex="-1" aria-labelledby="si-title" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg"><form class="modal-content" id="si-form" novalidate>
    <div class="modal-header border-0"><h5 class="modal-title fw-bold" id="si-title">Add a platform</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body pt-0">
      <input type="hidden" name="type">
      <div class="row g-3">
        <div class="col-md-5">
          <label class="form-label fw-semibold" for="si-name">Name</label>
          <input class="form-control" id="si-name" name="name" maxlength="40" placeholder="e.g. Kaggle" required>
        </div>
        <div class="col-md-7">
          <label class="form-label fw-semibold" for="si-domains">Web addresses</label>
          <input class="form-control" id="si-domains" name="domains" maxlength="500" placeholder="kaggle.com, kaggle.io" required>
          <div class="form-text">Links on these sites (and their sub-domains) get this icon.</div>
        </div>
        <div class="col-md-7">
          <label class="form-label fw-semibold">Icon</label>
          <div class="si-pick" id="si-icons">@foreach($icons as $k => $label)<button type="button" data-icon="{{ $k }}" title="{{ $label }}" aria-label="{{ $label }}"><i class="bi {{ $k }}"></i></button>@endforeach</div>
          <label class="form-label fw-semibold mt-3" for="si-logo">…or upload a logo <span class="text-muted fw-normal">(PNG / JPG / WebP, square, max 1 MB)</span></label>
          <input class="form-control" type="file" id="si-logo" name="logo" accept="image/png,image/jpeg,image/webp">
          <div class="form-check mt-2 d-none" id="si-remove-wrap"><input class="form-check-input" type="checkbox" id="si-remove" name="remove_logo" value="1"><label class="form-check-label small" for="si-remove">Remove the uploaded logo (use the icon)</label></div>
        </div>
        <div class="col-md-5">
          <label class="form-label fw-semibold">Colour</label>
          <div class="si-colors" id="si-colors">@foreach($colors as $c)<button type="button" data-color="{{ $c }}" style="background:{{ $c }}" title="{{ $c }}" aria-label="Colour {{ $c }}"></button>@endforeach
            <input type="color" class="form-control form-control-color" id="si-color" name="color" value="#475569" title="Any colour"></div>
          <label class="form-label fw-semibold mt-3">Preview</label>
          <div class="si-preview"><span class="si-ic" id="si-pv-ic"><i class="bi bi-link-45deg"></i></span><div class="si-tx"><b id="si-pv-name">Platform</b><span id="si-pv-dom">example.com</span></div></div>
        </div>
      </div>
    </div>
    <div class="modal-footer border-0 pt-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> Save</button></div>
  </form></div>
</div>
@endsection
@push('scripts')
<script>
(function () {
  'use strict';
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var R = { save: @json(route('participants.social-icons.save')), del: @json(url('participants/social-icons')) };
  var modal = new bootstrap.Modal(document.getElementById('si-modal')), f = document.getElementById('si-form');
  var state = { icon: 'bi-link-45deg', image: null };
  function $(id) { return document.getElementById(id); }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

  function preview() {
    var ic = $('si-pv-ic'), file = $('si-logo').files[0];
    ic.style.setProperty('--c', f.color.value);
    if (file) { var u = URL.createObjectURL(file); ic.innerHTML = '<img src="' + u + '" alt="">'; }
    else if (state.image && !$('si-remove').checked) ic.innerHTML = '<img src="' + esc(state.image) + '" alt="">';
    else ic.innerHTML = '<i class="bi ' + esc(state.icon) + '"></i>';
    $('si-pv-name').textContent = f.name.value.trim() || 'Platform';
    $('si-pv-dom').textContent = f.domains.value.trim() || 'example.com';
    document.querySelectorAll('#si-icons [data-icon]').forEach(function (b) { b.classList.toggle('on', b.dataset.icon === state.icon); });
    document.querySelectorAll('#si-colors [data-color]').forEach(function (b) { b.classList.toggle('on', b.dataset.color === f.color.value.toLowerCase()); });
  }
  function open(p) {
    f.reset();
    f.type.value = p ? p.type : '';
    f.name.value = p ? p.name : ''; f.domains.value = p ? p.domains.join(', ') : '';
    f.color.value = p ? p.color : '#475569';
    state.icon = p && p.icon ? p.icon : 'bi-link-45deg'; state.image = p ? p.image_url : null;
    $('si-remove-wrap').classList.toggle('d-none', !state.image);
    $('si-title').textContent = p ? 'Edit ' + p.name : 'Add a platform';
    preview(); modal.show();
    setTimeout(function () { f.name.focus(); }, 300);
  }
  document.querySelectorAll('[data-new]').forEach(function (b) { b.addEventListener('click', function () { open(null); }); });
  document.querySelectorAll('[data-edit]').forEach(function (b) { b.addEventListener('click', function () { open(JSON.parse(b.dataset.edit)); }); });
  document.querySelectorAll('#si-icons [data-icon]').forEach(function (b) { b.addEventListener('click', function () { state.icon = b.dataset.icon; preview(); }); });
  document.querySelectorAll('#si-colors [data-color]').forEach(function (b) { b.addEventListener('click', function () { f.color.value = b.dataset.color; preview(); }); });
  ['input', 'change'].forEach(function (t) { f.addEventListener(t, preview); });

  f.addEventListener('submit', function (e) {
    e.preventDefault();
    var fd = new FormData(f); fd.set('icon', state.icon); if (!$('si-remove').checked) fd.delete('remove_logo');
    if (!$('si-logo').files.length) fd.delete('logo');
    var btn = f.querySelector('[type=submit]'); btn.disabled = true;
    fetch(R.save, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: fd })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (res) {
        btn.disabled = false;
        if (!res.ok) { var er = res.d.errors || {}, k = Object.keys(er)[0]; return Swal.fire('Not saved', k ? er[k][0] : (res.d.message || 'Please try again.'), 'warning'); }
        modal.hide(); Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: res.d.message, showConfirmButton: false, timer: 2500 });
        setTimeout(function () { location.reload(); }, 900);
      }).catch(function () { btn.disabled = false; Swal.fire('Not saved', 'Check your connection and try again.', 'warning'); });
  });
  document.querySelectorAll('[data-del]').forEach(function (b) {
    b.addEventListener('click', function () {
      Swal.fire({ title: 'Remove ' + b.dataset.name + '?', text: 'Links to it keep working; they show the standard link icon again.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Remove', confirmButtonColor: '#d33' })
        .then(function (r) {
          if (!r.isConfirmed) return;
          fetch(R.del + '/' + b.dataset.del, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } })
            .then(function (x) { return x.json(); }).then(function (d) { Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: d.message, showConfirmButton: false, timer: 2200 }); setTimeout(function () { location.reload(); }, 800); });
        });
    });
  });
})();
</script>
@endpush
