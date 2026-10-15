@extends('backend.layouts.master')
@section('title')
Form Options
@endsection
@push('styles')
<style>
  .fo-tabs .nav-link { font-weight: 600; color: var(--ad-text); }
  .fo-tabs .nav-link.active { color: var(--ad-brand); }
  .fo-tabs .badge { font-weight: 600; }
  .fo-add { display: flex; gap: 8px; margin-bottom: 12px; }
  .fo-add .form-control { min-width: 0; }
  .fo-list { list-style: none; padding: 0; margin: 0; max-height: 62vh; overflow-y: auto; border: 1px solid var(--ad-line); border-radius: 10px; }
  .fo-row { display: flex; align-items: center; gap: 8px; padding: 6px 10px; border-bottom: 1px solid var(--ad-line-soft); background: var(--ad-surface); }
  .fo-row:last-child { border-bottom: 0; }
  .fo-row.is-off .fo-label { color: var(--ad-muted); text-decoration: line-through; }
  .fo-row.is-selected { background: var(--ad-brand-50); box-shadow: inset 3px 0 0 #1861c5; }
  .fo-handle { cursor: grab; color: var(--ad-muted); font-size: 1.15rem; }
  .fo-label { border: 1px solid transparent; background: transparent; padding: 4px 8px; border-radius: 6px; flex: 1; min-width: 0; font-weight: 500; }
  .fo-label:hover { border-color: #dfe5ee; }
  .fo-label:focus { border-color: #1861c5; background: var(--ad-surface); outline: none; box-shadow: 0 0 0 .15rem rgba(24,97,197,.15); }
  .fo-dial { width: 92px; border: 1px solid var(--ad-line); border-radius: 6px; padding: 4px 8px; font-variant-numeric: tabular-nums; }
  .fo-count { font-size: 12px; color: var(--ad-muted); white-space: nowrap; }
  .fo-row .form-switch { margin: 0; padding-left: 2.4em; }
  .fo-ghost { opacity: .45; background: var(--ad-brand-50); }
  .fo-saved { animation: foSaved 1s ease; }
  @keyframes foSaved { 0% { background: #e3f6dc; } 100% { background: var(--ad-surface); } }
  .fo-empty { padding: 18px; text-align: center; color: var(--ad-muted); }
</style>
@endpush
@section('content')
<div class="pagetitle">
  <h1>Registration Form Options</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li>
      <li class="breadcrumb-item active">Form Options</li>
    </ol>
  </nav>
</div>

<section class="section">
  <div class="card">
    <div class="card-header text-white fw-bold" style="background:#1861c5"><i class="bi bi-ui-checks"></i> DROP-DOWN VALUES ON THE REGISTRATION FORM</div>
    <div class="card-body pt-3">
      <p class="text-muted small mb-3">
        <i class="bi bi-info-circle"></i> Changes are saved immediately. Click a name to rename it, drag <i class="bi bi-grip-vertical"></i> to reorder,
        switch off to hide it from the form. Past registrations keep the value they chose.
        <a href="{{ url('/registration') }}" target="_blank">Open registration form <i class="bi bi-box-arrow-up-right"></i></a>
      </p>

      <ul class="nav nav-tabs fo-tabs" role="tablist">
        @foreach($lists as $key => $list)
        <li class="nav-item" role="presentation">
          <button class="nav-link @if($loop->first) active @endif" data-bs-toggle="tab" data-bs-target="#tab-{{ $key }}" type="button" role="tab">
            {{ $list[0] }} <span class="badge bg-light text-dark border" data-count="{{ $key }}"></span>
          </button>
        </li>
        @endforeach
      </ul>

      <div class="tab-content pt-3">
        {{-- Titles / ID types / Countries --}}
        @foreach(['title' => 'e.g. Dr', 'id_type' => 'e.g. Passport number', 'country' => 'Country name'] as $key => $placeholder)
        <div class="tab-pane fade @if($key === 'title') show active @endif" id="tab-{{ $key }}" role="tabpanel">
          <p class="small text-muted mb-2">{{ $lists[$key][1] }}</p>
          <form class="fo-add" data-list="{{ $key }}">
            <input type="text" class="form-control" name="label" placeholder="{{ $placeholder }}" maxlength="150" required>
            @if($key === 'country')<input type="text" class="form-control" name="extra" placeholder="+34" style="max-width:110px" maxlength="10">@endif
            <button class="btn btn-primary text-nowrap"><i class="bi bi-plus-lg"></i> Add</button>
          </form>
          @if($key === 'country')
          <input type="search" class="form-control form-control-sm mb-2" id="fo-country-search" placeholder="Search countries…">
          @endif
          <ul class="fo-list" data-list="{{ $key }}"></ul>
        </div>
        @endforeach

        {{-- Professions & specialities --}}
        <div class="tab-pane fade" id="tab-profession" role="tabpanel">
          <p class="small text-muted mb-2">{{ $lists['profession'][1] }} Select a profession to edit its specialities.</p>
          <div class="row g-3">
            <div class="col-lg-6">
              <h6 class="fw-bold">Professions</h6>
              <form class="fo-add" data-list="profession">
                <input type="text" class="form-control" name="label" placeholder="e.g. Radiologist" maxlength="150" required>
                <button class="btn btn-primary text-nowrap"><i class="bi bi-plus-lg"></i> Add</button>
              </form>
              <ul class="fo-list" data-list="profession"></ul>
            </div>
            <div class="col-lg-6">
              <h6 class="fw-bold">Specialities of <span id="fo-spec-title" class="text-primary">—</span></h6>
              <form class="fo-add" data-list="speciality" id="fo-spec-form">
                <input type="text" class="form-control" name="label" placeholder="e.g. Medical Oncologist" maxlength="150" required disabled>
                <button class="btn btn-primary text-nowrap" disabled><i class="bi bi-plus-lg"></i> Add</button>
              </form>
              <ul class="fo-list" data-list="speciality"></ul>
              <p class="small text-muted mt-2 mb-0">A profession without specialities shows "Not applicable" on the form.</p>
            </div>
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
  window.FO = {
    options: @json($options),
    urls: {
      store: @json(route('form-options.store')),
      reorder: @json(route('form-options.reorder')),
      base: @json(url('/form-options'))
    }
  };
</script>
<script src="{{ asset('assets/backend/js/form-options.js') }}?v={{ filemtime(base_path('assets/backend/js/form-options.js')) }}"></script>
@endpush
