@extends('backend.layouts.master')
@section('title')
{{ $template->name }} · Card Design Studio
@endsection
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/backend/card-studio/studio.css') }}?v={{ @filemtime(base_path('assets/backend/card-studio/studio.css')) }}">
@endpush
@section('content')
<div class="cs-app" id="cs-app">
  {{-- toolbar --}}
  <div class="cs-bar">
    <a href="{{ route('card-studio.index') }}" class="btn btn-sm btn-light" title="All templates"><i class="bi bi-arrow-left"></i></a>
    <input class="form-control form-control-sm cs-title" id="cs-name" value="{{ $template->name }}" maxlength="120" title="Template name">
    <div class="btn-group btn-group-sm" id="cs-sides">
      <button class="btn btn-outline-primary active" data-side="front">Front</button>
      <button class="btn btn-outline-primary" data-side="back">Back</button>
    </div>
    <div class="cs-sep"></div>
    <div class="btn-group btn-group-sm" title="Undo / redo">
      <button class="btn btn-light" id="cs-undo" title="Undo (Ctrl+Z)"><i class="bi bi-arrow-counterclockwise"></i></button>
      <button class="btn btn-light" id="cs-redo" title="Redo (Ctrl+Y)"><i class="bi bi-arrow-clockwise"></i></button>
    </div>
    <div class="btn-group btn-group-sm">
      <button class="btn btn-light" id="cs-zoom-out" title="Zoom out (-)"><i class="bi bi-dash-lg"></i></button>
      <button class="btn btn-light cs-zoom" id="cs-zoom-label" title="Fit to screen (0)">100%</button>
      <button class="btn btn-light" id="cs-zoom-in" title="Zoom in (+)"><i class="bi bi-plus-lg"></i></button>
      <button class="btn btn-light" id="cs-zoom-real" title="Actual size">1:1</button>
    </div>
    <button class="btn btn-sm btn-light" id="cs-guides" title="Bleed, cut line and safe area"><i class="bi bi-bounding-box-circles"></i> Guides</button>
    <div class="cs-data">
      <select class="form-select form-select-sm" id="cs-data-mode" title="What the fields show">
        <option value="sample">Sample person</option>
        <option value="placeholders">Show field names</option>
        <option value="participant">A participant…</option>
      </select>
      <div class="cs-search d-none" id="cs-search-wrap">
        <input class="form-control form-control-sm" id="cs-search" placeholder="Search name, ID, email…" autocomplete="off">
        <div class="cs-search-list" id="cs-search-list"></div>
      </div>
    </div>
    <div class="ms-auto d-flex gap-2 align-items-center">
      <span class="small text-muted d-none d-xl-inline" id="cs-status"></span>
      <button class="btn btn-sm btn-light" id="cs-versions-btn" title="Version history"><i class="bi bi-clock-history"></i> <span class="d-none d-lg-inline">Versions</span></button>
      <button class="btn btn-sm btn-light" id="cs-preview-btn" title="Preview"><i class="bi bi-eye"></i> <span class="d-none d-lg-inline">Preview</span></button>
      <div class="btn-group btn-group-sm">
        <button class="btn btn-primary" id="cs-save" title="Save (Ctrl+S)"><i class="bi bi-cloud-check"></i> Save</button>
        <button class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown"></button>
        <ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item" id="cs-save-note"><i class="bi bi-chat-left-text"></i> Save with a note…</button></li></ul>
      </div>
    </div>
  </div>

  <div class="cs-body">
    {{-- left: add + layers --}}
    <aside class="cs-left">
      <div class="cs-panel-h">Add</div>
      <div class="cs-add">
        <button data-add="text"><i class="bi bi-fonts"></i>Text</button>
        <div class="dropdown">
          <button data-bs-toggle="dropdown" class="w-100"><i class="bi bi-person-lines-fill"></i>Field</button>
          <ul class="dropdown-menu cs-fields">
            @foreach($placeholders as $k => $label)<li><button class="dropdown-item" data-add-field="{{ $k }}">{{ $label }} <code class="ms-1 small">{{ '{'.'{'.$k.'}'.'}' }}</code></button></li>@endforeach
          </ul>
        </div>
        <button data-add="photo"><i class="bi bi-person-square"></i>Photo</button>
        <button data-add="logo"><i class="bi bi-building"></i>Org. logo</button>
        <button data-add="image"><i class="bi bi-image"></i>Image</button>
        <button data-add="qr"><i class="bi bi-qr-code"></i>QR code</button>
        <button data-add="rect"><i class="bi bi-square"></i>Box</button>
        <button data-add="ellipse"><i class="bi bi-circle"></i>Circle</button>
        <button data-add="line"><i class="bi bi-slash-lg"></i>Line</button>
        <button data-add="icon"><i class="bi bi-star"></i>Icon</button>
      </div>
      <div class="cs-panel-h d-flex justify-content-between align-items-center">Layers <span class="small text-muted fw-normal" id="cs-layer-count"></span></div>
      <div class="cs-layers" id="cs-layers"></div>
      <div class="cs-help small text-muted">
        <div><kbd>Shift</kbd> + click: select more · drag on empty space to select an area</div>
        <div><kbd>Alt</kbd> while dragging: no snapping · <kbd>Shift</kbd>: straight / keep proportions</div>
        <div><kbd>←↑→↓</kbd> nudge · <kbd>Ctrl</kbd>+<kbd>D</kbd> duplicate · <kbd>Del</kbd> remove</div>
      </div>
    </aside>

    {{-- canvas --}}
    <div class="cs-center">
      <div class="cs-canvas" id="cs-canvas">
        <div class="cs-stage" id="cs-stage">
          <div class="cs-svg" id="cs-svg"></div>
          <div class="cs-overlay" id="cs-overlay"></div>
        </div>
      </div>
      <div class="cs-legend small"><span class="lg-bleed"></span> Bleed (cut off) <span class="lg-trim"></span> Cut line <span class="lg-safe"></span> Safe area — keep text inside <span class="ms-auto" id="cs-pos"></span></div>
    </div>

    {{-- right: properties --}}
    <aside class="cs-right" id="cs-props"></aside>
  </div>
</div>

<div class="cs-narrow alert alert-warning m-3">The Card Design Studio needs a larger screen (a laptop or desktop).</div>

<input type="file" id="cs-file" accept="image/png,image/jpeg,image/webp" class="d-none">

{{-- versions --}}
<div class="modal fade" id="csVersions" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Version history</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body p-0"><div class="list-group list-group-flush" id="cs-version-list"></div></div>
  <div class="modal-footer small text-muted justify-content-start">Opening a version puts it in the editor. Save to make it the current design (the newer versions stay in the history).</div>
</div></div></div>

{{-- preview --}}
<div class="modal fade" id="csPreview" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Preview</h5>
    <div class="form-check form-switch ms-4 mb-0"><input class="form-check-input" type="checkbox" id="cs-pv-marks"><label class="form-check-label small" for="cs-pv-marks">Print view (bleed + crop marks)</label></div>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body cs-pv" id="cs-pv"></div>
</div></div></div>
@endsection

@push('scripts')
@include('backend.pages.card-studio.assets')
<script>
window.CardStudio = {
  template: @json(['id' => $template->id, 'name' => $template->name, 'version' => $template->version]),
  design: @json($template->design),
  versions: @json($versions),
  icons: @json($icons),
  fonts: @json($fonts),
  placeholders: @json($placeholders),
  sizes: @json($sizes),
  sample: @json($sample),
  base: @json(url('/') . '/'),
  urls: {
    save: @json(route('card-studio.update', $template)),
    version: @json(url('card-studio/' . $template->id . '/versions')) + '/',
    upload: @json(route('card-studio.upload')),
    participants: @json(route('card-studio.participants')),
    data: @json(url('card-studio/data')) + '/'
  }
};
</script>
<script src="{{ asset('assets/backend/card-studio/studio.js') }}?v={{ @filemtime(base_path('assets/backend/card-studio/studio.js')) }}"></script>
@endpush
