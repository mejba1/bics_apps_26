@extends('backend.layouts.master')
@section('title')
Website Pages
@endsection
@section('content')
<div class="pagetitle d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div>
    <h1>Website Pages</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li>
        <li class="breadcrumb-item active">Website Pages</li>
      </ol>
    </nav>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newPageModal"><i class="bi bi-file-earmark-plus"></i> New Page</button>
</div>

<section class="section">
  <div class="card">
    <div class="card-header text-white fw-bold" style="background:#1861c5"><i class="bi bi-files"></i> PAGES</div>
    <div class="card-body pt-3">
      <p class="text-muted small">Click <b>Edit content</b> to change texts and images, add new sections, or reorder them. To show a new page in the top menu, add it in <a href="{{ url('/menu') }}">Website Menu</a>.</p>
      <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr><th>Page</th><th>Address</th><th class="text-center">Sections</th><th class="text-center">Status</th><th class="text-end">Action</th></tr>
        </thead>
        <tbody>
          @foreach($pages as $page)
          <tr>
            <td class="fw-bold">
              @if($page->is_global)<i class="bi bi-globe2 text-primary"></i>@endif
              {{ $page->title }}
              @if($page->is_system && !$page->is_global)<span class="badge bg-light text-secondary border ms-1">built-in</span>@endif
            </td>
            <td class="small">
              @if($page->is_global)
                <span class="text-muted">Shown on every page</span>
              @else
                <a href="{{ $page->url }}" target="_blank">/{{ $page->slug === 'home' ? '' : $page->slug }} <i class="bi bi-box-arrow-up-right"></i></a>
              @endif
            </td>
            <td class="text-center">{{ $page->sections_count }}</td>
            <td class="text-center">
              @if($page->is_active)<span class="badge bg-success">Visible</span>@else<span class="badge bg-secondary">Hidden</span>@endif
            </td>
            <td class="text-end"><a href="{{ route('cms.edit', $page) }}" class="btn btn-primary btn-sm"><i class="bi bi-pencil-square"></i> Edit content</a></td>
          </tr>
          @endforeach
        </tbody>
      </table>
      </div>
    </div>
  </div>
</section>

<div class="modal fade" id="newPageModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="newPageForm">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-file-earmark-plus"></i> New Page</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label" for="np-title">Page title</label>
          <input type="text" class="form-control" id="np-title" name="title" required maxlength="150" placeholder="e.g. Speakers">
          <div class="invalid-feedback" data-error="title"></div>
        </div>
        <div class="mb-2">
          <label class="form-label" for="np-slug">Address (optional)</label>
          <div class="input-group">
            <span class="input-group-text">{{ url('/') }}/</span>
            <input type="text" class="form-control" id="np-slug" name="slug" maxlength="100" placeholder="speakers">
            <div class="invalid-feedback" data-error="slug"></div>
          </div>
          <div class="form-text">Small letters, numbers and dashes. Leave empty to make it from the title.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Create &amp; edit</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
  document.getElementById('np-title').addEventListener('input', function () {
    var slug = document.getElementById('np-slug');
    if (!slug.dataset.touched) slug.placeholder = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'speakers';
  });
  document.getElementById('np-slug').addEventListener('input', function () { this.dataset.touched = 1; });

  document.getElementById('newPageForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var form = this, btn = form.querySelector('[type=submit]');
    form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
    btn.disabled = true;
    fetch(@json(route('cms.store')), {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
      body: new FormData(form)
    }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (res) {
        btn.disabled = false;
        if (res.ok) { window.location = res.d.redirect; return; }
        Object.keys(res.d.errors || {}).forEach(function (k) {
          var input = form.querySelector('[name="' + k + '"]');
          if (input) input.classList.add('is-invalid');
          var msg = form.querySelector('[data-error="' + k + '"]');
          if (msg) msg.textContent = res.d.errors[k][0];
        });
      }).catch(function () { btn.disabled = false; Swal.fire('Error', 'Could not create the page. Please try again.', 'error'); });
  });
</script>
@endpush
