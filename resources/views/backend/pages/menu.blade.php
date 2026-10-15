@extends('backend.layouts.master')
@section('title')
Website Menu
@endsection
@section('content')
<div class="pagetitle">
    <h1>Website Menu</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li>
        <li class="breadcrumb-item active">Website Menu</li>
      </ol>
    </nav>
</div><!-- End Page Title -->

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif
@if($errors->any())
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-octagon me-1"></i> {{ $errors->first() }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<section class="section">
  <div class="row">
    <!--====Add-Menu-Form===--->
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header text-white fw-bold" style="background:#1861c5"><i class="bi bi-plus-circle"></i> ADD MENU ITEM</div>
        <div class="card-body mt-3">
          <form method="POST" action="{{ route('menu.store') }}">
            @csrf
            <div class="mb-2">
              <label class="form-label" for="add-title">Title</label>
              <input type="text" name="title" id="add-title" class="form-control" value="{{ old('title') }}" required maxlength="100">
            </div>
            <div class="mb-2">
              <label class="form-label" for="add-url">Link</label>
              <input type="text" name="url" id="add-url" class="form-control" value="{{ old('url', '#') }}" required maxlength="255">
              <div class="form-text">Site page: <code>gallery</code>, home: <code>/</code>, other site: <code>https://…</code>, no link: <code>#</code></div>
            </div>
            <div class="mb-2">
              <label class="form-label" for="add-parent">Show under</label>
              <select name="parent_id" id="add-parent" class="form-select">
                <option value="">— Top level —</option>
                @foreach($menus as $menu)
                  <option value="{{ $menu->id }}" @selected(old('parent_id') == $menu->id)>{{ $menu->title }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="is_active" value="1" id="add-active" checked>
              <label class="form-check-label" for="add-active">Visible on website</label>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="new_tab" value="1" id="add-newtab">
              <label class="form-check-label" for="add-newtab">Open in new tab</label>
            </div>
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Add Item</button>
          </form>
        </div>
      </div>
    </div>

    <!--====Menu-Tree===--->
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header text-white fw-bold" style="background:#1861c5"><i class="bi bi-list-nested"></i> MENU ITEMS</div>
        <div class="card-body mt-2">
          <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead>
              <tr>
                <th>Title</th>
                <th>Link</th>
                <th class="text-center">Order</th>
                <th class="text-center">Visible</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              @forelse($menus as $menu)
                @include('backend.pages.partials.menu-row', ['item' => $menu, 'first' => $loop->first, 'last' => $loop->last])
                @foreach($menu->children as $child)
                  @include('backend.pages.partials.menu-row', ['item' => $child, 'first' => $loop->first, 'last' => $loop->last])
                @endforeach
              @empty
                <tr><td colspan="5" class="text-center text-muted">No menu items yet. Add one on the left.</td></tr>
              @endforelse
            </tbody>
          </table>
          </div>
          <p class="text-muted small mb-0"><i class="bi bi-info-circle"></i> Hidden items (and everything under a hidden item) are not shown on the website. Deleting an item also deletes its sub-menu.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!--====Edit-Menu-Modal===--->
<div class="modal fade" id="editMenuModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" id="editMenuForm" class="modal-content">
      @csrf
      @method('PUT')
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Menu Item</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label" for="edit-title">Title</label>
          <input type="text" name="title" id="edit-title" class="form-control" required maxlength="100">
        </div>
        <div class="mb-2">
          <label class="form-label" for="edit-url">Link</label>
          <input type="text" name="url" id="edit-url" class="form-control" required maxlength="255">
        </div>
        <div class="mb-2">
          <label class="form-label" for="edit-parent">Show under</label>
          <select name="parent_id" id="edit-parent" class="form-select">
            <option value="">— Top level —</option>
            @foreach($menus as $menu)
              <option value="{{ $menu->id }}">{{ $menu->title }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="is_active" value="1" id="edit-active">
          <label class="form-check-label" for="edit-active">Visible on website</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="new_tab" value="1" id="edit-newtab">
          <label class="form-check-label" for="edit-newtab">Open in new tab</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
  // Fill the edit dialog from the clicked row
  document.querySelectorAll('.edit-menu').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var d = btn.dataset;
      document.getElementById('editMenuForm').action = d.action;
      document.getElementById('edit-title').value = d.title;
      document.getElementById('edit-url').value = d.url;
      document.getElementById('edit-active').checked = d.active === '1';
      document.getElementById('edit-newtab').checked = d.newtab === '1';
      var parent = document.getElementById('edit-parent');
      parent.value = d.parent || '';
      // An item cannot be placed under itself
      Array.prototype.forEach.call(parent.options, function (o) { o.disabled = o.value === d.id; });
    });
  });
</script>
@endpush
