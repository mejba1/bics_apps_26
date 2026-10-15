@extends('backend.layouts.master')
@section('title')
Gallery
@endsection
@push('styles')
<style>
  .gm-album { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border: 1px solid var(--ad-line); border-radius: 12px; margin-bottom: 8px; background: var(--ad-surface); cursor: pointer; transition: border-color .15s, box-shadow .15s; }
  .gm-album:hover { border-color: #c9d6ea; }
  .gm-album.is-selected { border-color: #1861c5; box-shadow: 0 0 0 .18rem rgba(24,97,197,.15); }
  .gm-album.is-off .gm-album-title { opacity: .5; text-decoration: line-through; }
  .gm-cover { width: 52px; height: 52px; border-radius: 9px; object-fit: cover; background: var(--ad-chip); flex-shrink: 0; display: grid; place-items: center; color: var(--ad-muted); font-size: 20px; }
  .gm-album-title { font-weight: 700; color: var(--ad-ink); line-height: 1.25; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; word-break: break-word; }
  .gm-album-count { font-size: 12.5px; color: var(--ad-muted); }
  .gm-handle { cursor: grab; color: var(--ad-muted); font-size: 1.15rem; }
  .gm-ghost { opacity: .4; }
  .gm-drop { border: 2px dashed #c5d3e8; border-radius: 16px; background: var(--ad-subtle); padding: 26px 16px; text-align: center; cursor: pointer; transition: all .15s; }
  .gm-drop:hover, .gm-drop.is-drag { border-color: #1861c5; background: var(--ad-brand-50); }
  .gm-drop i { font-size: 2.2rem; color: var(--ad-brand); }
  .gm-queue { margin-top: 10px; }
  .gm-qitem { display: flex; align-items: center; gap: 10px; font-size: 13px; padding: 4px 0; }
  .gm-qitem .progress { flex: 1; height: 7px; }
  .gm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; margin-top: 16px; }
  .gm-photo { border: 1px solid var(--ad-line); border-radius: 12px; overflow: hidden; background: var(--ad-surface); position: relative; }
  .gm-photo img { display: block; width: 100%; aspect-ratio: 4 / 3; object-fit: cover; background: var(--ad-chip); cursor: grab; }
  .gm-photo-actions { position: absolute; top: 6px; right: 6px; display: flex; gap: 4px; opacity: 0; transition: opacity .15s; }
  .gm-photo:hover .gm-photo-actions, .gm-photo:focus-within .gm-photo-actions { opacity: 1; }
  .gm-photo-actions .btn { padding: 2px 7px; border-radius: 7px; box-shadow: 0 2px 6px rgba(0,0,0,.2); }
  .gm-caption { border: 0; border-top: 1px solid var(--ad-line-soft); width: 100%; padding: 7px 9px; font-size: 13px; outline: none; }
  .gm-caption:focus { background: var(--ad-subtle); }
  .gm-num { position: absolute; top: 6px; left: 6px; background: rgba(0,0,0,.55); color: #fff; font-size: 11px; font-weight: 700; border-radius: 6px; padding: 1px 6px; }
  .gm-empty { text-align: center; color: var(--ad-muted); padding: 30px 10px; }
  .gm-saved { animation: gmSaved 1s ease; }
  @keyframes gmSaved { 0% { background: #e3f6dc; } 100% { background: var(--ad-surface); } }
  @media (hover: none) { .gm-photo-actions { opacity: 1; } }
</style>
@endpush
@section('content')
<div class="pagetitle d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div>
    <h1>Gallery</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li><li class="breadcrumb-item active">Gallery</li></ol></nav>
  </div>
  <a href="{{ url('/gallery') }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-eye"></i> View gallery</a>
</div>

<section class="section">
  <div class="row g-3">
    {{-- Albums --}}
    <div class="col-lg-4">
      <div class="card mb-0">
        <div class="card-header text-white fw-bold" style="background:#1861c5"><i class="bi bi-collection"></i> ALBUMS</div>
        <div class="card-body pt-3">
          <form id="gm-new-album" class="d-flex gap-2 mb-2">
            <input type="text" name="title" class="form-control" placeholder="New album, e.g. Spain 2026" maxlength="150" required>
            <button class="btn btn-primary text-nowrap"><i class="bi bi-plus-lg"></i> Create</button>
          </form>
          <p class="small text-muted mb-3">Each album becomes a filter button on the gallery page. Drag <i class="bi bi-grip-vertical"></i> to change the order.</p>
          <div id="gm-albums"></div>
        </div>
      </div>
    </div>

    {{-- Photos of the selected album --}}
    <div class="col-lg-8">
      <div class="card mb-0">
        <div class="card-header text-white fw-bold d-flex justify-content-between align-items-center" style="background:#1861c5">
          <span><i class="bi bi-images"></i> <span id="gm-album-heading">PHOTOS</span></span>
          <span class="badge bg-light text-dark" id="gm-photo-count"></span>
        </div>
        <div class="card-body pt-3" id="gm-photos-panel">
          <div class="gm-empty" id="gm-no-album"><i class="bi bi-arrow-left-circle fs-2 d-block mb-2"></i>Create or choose an album to add photos.</div>
          <div id="gm-album-tools" class="d-none">
            <input type="file" id="gm-files" accept="image/jpeg,image/png,image/webp" multiple class="d-none">
            <div class="gm-drop" id="gm-drop" tabindex="0" role="button" aria-label="Upload photos">
              <i class="bi bi-cloud-arrow-up"></i>
              <div class="fw-bold mt-1">Drop photos here or click to choose</div>
              <div class="small text-muted">JPG, PNG or WEBP · many at once · large photos are resized automatically</div>
            </div>
            <div class="gm-queue" id="gm-queue"></div>
            <p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle"></i> Drag photos to reorder. Type a caption under a photo (saved automatically). Hover a photo to move or delete it.</p>
            <div class="gm-grid" id="gm-grid"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script>
  window.GM = {
    albums: @json($albums),
    base: @json(url('/gallery-manager'))
  };
</script>
<script src="{{ asset('assets/backend/js/gallery-manager.js') }}?v={{ filemtime(base_path('assets/backend/js/gallery-manager.js')) }}"></script>
@endpush
