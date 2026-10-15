@extends('backend.layouts.master')
@section('title')
My Account
@endsection
@section('content')
<div class="pagetitle">
  <h1>My Account</h1>
  <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li><li class="breadcrumb-item active">My Account</li></ol></nav>
</div>

<section class="section">
  <div class="row g-3">
    <div class="col-lg-7">
      <form class="card mb-0" id="acForm" novalidate>
        <div class="card-header text-white fw-bold" style="background:#1861c5"><i class="bi bi-person-circle"></i> MY DETAILS</div>
        <div class="card-body pt-3">
          <div class="mb-3">
            <label class="form-label fw-semibold" for="ac-name">Full name</label>
            <input class="form-control" id="ac-name" name="name" value="{{ $user->name }}" maxlength="255" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="ac-email">Email (used to log in)</label>
            <input type="email" class="form-control" id="ac-email" name="email" value="{{ $user->email }}" maxlength="255" required>
          </div>
          <hr>
          <p class="fw-semibold mb-2">Change password <span class="text-muted fw-normal small">(optional)</span></p>
          <div class="mb-3">
            <label class="form-label" for="ac-current">Current password</label>
            <input type="password" class="form-control" id="ac-current" name="current_password" autocomplete="current-password">
          </div>
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label" for="ac-new">New password</label>
              <input type="password" class="form-control" id="ac-new" name="password" minlength="8" autocomplete="new-password">
            </div>
            <div class="col-sm-6">
              <label class="form-label" for="ac-confirm">Repeat new password</label>
              <input type="password" class="form-control" id="ac-confirm" name="password_confirmation" autocomplete="new-password">
            </div>
          </div>
        </div>
        <div class="card-footer text-end"><button class="btn btn-success" id="ac-save"><i class="bi bi-check2-circle"></i> Save</button></div>
      </form>
    </div>
    <div class="col-lg-5">
      <div class="card mb-0">
        <div class="card-header text-white fw-bold" style="background:#1861c5"><i class="bi bi-shield-check"></i> MY ACCESS</div>
        <div class="card-body pt-3">
          <p class="mb-2">Role: <span class="badge bg-primary">{{ $user->role_name }}</span></p>
          @forelse($groups as $group => $names)
            <div class="fw-bold small text-primary mt-2">{{ $group }}</div>
            <ul class="small mb-1 ps-3">@foreach($names as $n)<li>{{ $n }}</li>@endforeach</ul>
          @empty
            <p class="text-muted small">No permissions yet. Ask a Super Admin to give your role access.</p>
          @endforelse
        </div>
      </div>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script>
  document.getElementById('acForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var form = this, btn = document.getElementById('ac-save');
    form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
    form.querySelectorAll('.invalid-feedback').forEach(function (el) { el.remove(); });
    btn.disabled = true;
    var data = new FormData(form); data.append('_method', 'PUT');
    fetch(@json(route('account.update')), { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }, body: data })
      .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (res) {
        btn.disabled = false;
        if (res.ok) {
          ['current_password', 'password', 'password_confirmation'].forEach(function (n) { form[n].value = ''; });
          Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: res.d.message, showConfirmButton: false, timer: 2200 });
          return;
        }
        var errors = res.d.errors || {};
        Object.keys(errors).forEach(function (k) {
          var input = form.querySelector('[name="' + k + '"]');
          if (!input) return;
          input.classList.add('is-invalid');
          var fb = document.createElement('div'); fb.className = 'invalid-feedback'; fb.textContent = errors[k][0];
          input.insertAdjacentElement('afterend', fb);
        });
      }).catch(function () { btn.disabled = false; Swal.fire('Error', 'Could not save. Please try again.', 'error'); });
  });
</script>
@endpush
