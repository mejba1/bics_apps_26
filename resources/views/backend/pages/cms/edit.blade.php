@extends('backend.layouts.master')
@section('title')
{{ $page->title }} - Edit Page
@endsection
@push('styles')
<style>
  .pb-item { cursor: default; transition: box-shadow .15s, border-color .15s; border: 1px solid #e3e7ef; }
  .pb-item.active { border-color: #1861c5; box-shadow: 0 0 0 .15rem rgba(24,97,197,.2); }
  .pb-item.is-hidden .pb-item-title { opacity: .55; }
  .pb-handle { cursor: grab; color: var(--ad-muted); font-size: 1.25rem; }
  .pb-handle:active { cursor: grabbing; }
  .pb-ghost { opacity: .4; background: var(--ad-brand-50); }
  .pb-editor-card { position: sticky; top: 80px; }
  .pb-editor-body { max-height: calc(100vh - 230px); overflow-y: auto; }
  .pb-image-preview { width: 100%; max-height: 140px; object-fit: contain; background: var(--ad-chip); border: 1px dashed #ccd3df; border-radius: 6px; }
  .pb-image-preview.empty { height: 70px; }
  .pb-rep-item > .card-header { background: var(--ad-subtle); cursor: pointer; padding: .4rem .6rem; }
  .pb-rep-thumb { width: 42px; height: 30px; object-fit: cover; border-radius: 3px; background: #e9edf4; }
  .pb-type-card { cursor: pointer; transition: transform .1s, border-color .1s; border: 1px solid #e3e7ef; }
  .pb-type-card:hover, .pb-type-card:focus { border-color: #1861c5; transform: translateY(-2px); outline: none; }
  .pb-code { font-family: Consolas, Monaco, monospace; font-size: 13px; }
  @media (max-width: 1199px) { .pb-editor-card { position: static; } .pb-editor-body { max-height: none; } }
</style>
@endpush
@section('content')
<div class="pagetitle d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div>
    <h1>{{ $page->title }}</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('cms.index') }}">Website Pages</a></li>
        <li class="breadcrumb-item active">{{ $page->title }}</li>
      </ol>
    </nav>
  </div>
  <div class="d-flex flex-wrap gap-2">
    @unless($page->is_global)
    <a href="{{ $page->url }}" target="_blank" class="btn btn-outline-secondary" id="pb-view"><i class="bi bi-eye"></i> View page</a>
    @endunless
    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#pageSettingsModal"><i class="bi bi-gear"></i> Page settings</button>
    @unless($page->is_global)
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSectionModal"><i class="bi bi-plus-lg"></i> Add section</button>
    @endunless
  </div>
</div>

<section class="section">
  <div class="row g-3">
    <!--====Section-List===--->
    <div class="col-xl-5">
      <div class="card mb-0">
        <div class="card-header text-white fw-bold" style="background:#1861c5"><i class="bi bi-layers"></i> SECTIONS <span class="fw-normal small">(top to bottom)</span></div>
        <div class="card-body pt-3">
          @unless($page->is_global)
          <p class="text-muted small mb-2"><i class="bi bi-grip-vertical"></i> Drag to reorder &nbsp;·&nbsp; <i class="bi bi-eye"></i> show/hide &nbsp;·&nbsp; <i class="bi bi-files"></i> duplicate</p>
          @endunless
          <div id="pb-list"></div>
          <div id="pb-empty" class="text-center text-muted py-4 d-none">
            <i class="bi bi-inbox fs-1 d-block"></i>
            This page has no sections yet.<br>
            <button class="btn btn-primary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#addSectionModal"><i class="bi bi-plus-lg"></i> Add the first section</button>
          </div>
        </div>
      </div>
    </div>

    <!--====Section-Editor===--->
    <div class="col-xl-7">
      <div class="card pb-editor-card mb-0">
        <div class="card-header text-white fw-bold d-flex justify-content-between align-items-center" style="background:#1861c5">
          <span><i class="bi bi-pencil-square"></i> <span id="pb-editor-title">EDIT SECTION</span></span>
          <span id="pb-dirty" class="badge bg-warning text-dark d-none">Unsaved changes</span>
        </div>
        <div class="card-body pt-3 pb-editor-body" id="pb-editor">
          <div class="text-center text-muted py-5" id="pb-editor-placeholder">
            <i class="bi bi-hand-index fs-1 d-block mb-2"></i>
            Choose a section on the left to edit its content.
          </div>
        </div>
        <div class="card-footer d-none text-end" id="pb-editor-footer">
          <button class="btn btn-outline-secondary" id="pb-cancel">Discard changes</button>
          <button class="btn btn-success" id="pb-save"><i class="bi bi-check2-circle"></i> Save section</button>
        </div>
      </div>
    </div>
  </div>
</section>

<!--====Add-Section-Modal===--->
@unless($page->is_global)
<div class="modal fade" id="addSectionModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-plus-square"></i> Add a section</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small">The new section is added at the bottom of the page. You can drag it anywhere afterwards.</p>
        <div class="row g-3">
          @foreach($types as $key => $type)
          <div class="col-md-6">
            <div class="card pb-type-card h-100 mb-0" tabindex="0" data-type="{{ $key }}">
              <div class="card-body py-3 d-flex gap-3">
                <i class="bi {{ $type['icon'] }} fs-2 text-primary"></i>
                <div>
                  <div class="fw-bold">{{ $type['label'] }}</div>
                  <div class="small text-muted">{{ $type['description'] }}</div>
                </div>
              </div>
            </div>
          </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</div>
@endunless

<!--====Page-Settings-Modal===--->
<div class="modal fade" id="pageSettingsModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="pageSettingsForm">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-gear"></i> Page settings</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="_method" value="PUT">
        <div class="mb-3">
          <label class="form-label" for="ps-title">Page name</label>
          <input type="text" class="form-control" id="ps-title" name="title" value="{{ $page->title }}" required maxlength="150">
        </div>
        @unless($page->is_global)
        <div class="mb-3">
          <label class="form-label" for="ps-slug">Address</label>
          <div class="input-group">
            <span class="input-group-text">{{ url('/') }}/</span>
            <input type="text" class="form-control" id="ps-slug" name="slug" value="{{ $page->slug === 'home' ? '' : $page->slug }}" maxlength="100" @disabled($page->is_system)>
          </div>
          @if($page->is_system)<div class="form-text">Built-in pages keep their address.</div>@endif
        </div>
        <div class="mb-3">
          <label class="form-label" for="ps-meta-title">Browser tab / Google title</label>
          <input type="text" class="form-control" id="ps-meta-title" name="meta_title" value="{{ $page->meta_title }}" maxlength="255" placeholder="{{ $page->title }}">
          <div class="form-text">Shown as "<i>title</i> | Beacon International Cancer Summit".</div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="ps-meta-desc">Google description</label>
          <textarea class="form-control" id="ps-meta-desc" name="meta_description" rows="3" maxlength="500">{{ $page->meta_description }}</textarea>
        </div>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="ps-active" name="is_active" value="1" @checked($page->is_active) @disabled($page->slug === 'home')>
          <label class="form-check-label" for="ps-active">Page is visible on the website</label>
        </div>
        @endunless
      </div>
      <div class="modal-footer">
        @unless($page->is_system)
        <button type="button" class="btn btn-outline-danger me-auto" id="pb-delete-page"><i class="bi bi-trash"></i> Delete page</button>
        @endunless
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save settings</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script>
  window.PB = {
    isGlobal: @json($page->is_global),
    sections: @json($sections),
    types: @json($allTypes),
    baseUrl: @json(url('/') . '/'),
    urls: {
      addSection: @json(route('cms.sections.store', $page)),
      reorder: @json(route('cms.sections.reorder', $page)),
      upload: @json(route('cms.upload')),
      page: @json(route('cms.update', $page)),
      deletePage: @json(route('cms.destroy', $page)),
      tinymceCss: @json(asset('assets/frontend/css/bootstrap.min.css'))
    }
  };
</script>
<script src="{{ asset('assets/backend/js/page-builder.js') }}?v={{ filemtime(base_path('assets/backend/js/page-builder.js')) }}"></script>
@endpush
