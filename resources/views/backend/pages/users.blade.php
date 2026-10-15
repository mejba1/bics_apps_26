@extends('backend.layouts.master')
@section('title')
Users
@endsection
@push('styles')
<style>
  .us-avatar { width: 36px; height: 36px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; font-size: 13px; background: linear-gradient(135deg, #0e4ca1, #b22e6b); flex-shrink: 0; }
  .us-role { font-weight: 600; }
  .us-role.super { background: #fdecf3 !important; color: #b22e6b !important; }
  .us-row.is-off td { opacity: .55; }
</style>
@endpush
@section('content')
<div class="pagetitle d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div>
    <h1>Users</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li><li class="breadcrumb-item active">Users</li></ol></nav>
  </div>
  <div class="d-flex gap-2">
    @can('roles.manage')<a href="{{ route('roles.index') }}" class="btn btn-outline-secondary"><i class="bi bi-shield-lock"></i> Roles &amp; Permissions</a>@endcan
    <button class="btn btn-primary" id="us-add"><i class="bi bi-person-plus"></i> Add user</button>
  </div>
</div>

<section class="section">
  <div class="card">
    <div class="card-header text-white fw-bold d-flex justify-content-between align-items-center" style="background:#1861c5">
      <span><i class="bi bi-people"></i> ADMIN PANEL USERS</span>
      <input type="search" class="form-control form-control-sm" id="us-search" placeholder="Search name, email or role…" style="max-width:240px">
    </div>
    <div class="card-body pt-3">
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>User</th><th>Role</th><th>Status</th><th class="d-none d-md-table-cell">Added</th><th class="text-end">Action</th></tr></thead>
          <tbody id="us-body"></tbody>
        </table>
      </div>
    </div>
  </div>
</section>

<div class="modal fade" id="usModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="usForm" novalidate>
      <div class="modal-header">
        <h5 class="modal-title" id="usTitle">Add user</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-semibold" for="us-name">Full name</label>
          <input class="form-control" id="us-name" name="name" maxlength="255" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold" for="us-email">Email (used to log in)</label>
          <input type="email" class="form-control" id="us-email" name="email" maxlength="255" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold" for="us-password" id="us-password-label">Password</label>
          <div class="input-group">
            <input type="text" class="form-control" id="us-password" name="password" minlength="8" maxlength="100" autocomplete="new-password">
            <button type="button" class="btn btn-outline-secondary" id="us-generate" title="Generate a strong password"><i class="bi bi-magic"></i> Generate</button>
          </div>
          <div class="form-text" id="us-password-help">At least 8 characters. Share it with the person securely.</div>
        </div>
        <div class="row g-3">
          <div class="col-sm-7">
            <label class="form-label fw-semibold" for="us-role">Role</label>
            <select class="form-select" id="us-role" name="role_id" required>
              @foreach($roles as $role)
                <option value="{{ $role->id }}" data-super="{{ $role->is_super ? 1 : 0 }}" @disabled($role->is_super && !$canGrantSuper)>{{ $role->name }}{{ $role->is_super ? ' (full access)' : '' }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-sm-5">
            <label class="form-label fw-semibold" for="us-status">Status</label>
            <select class="form-select" id="us-status" name="status">
              <option value="active">Active</option>
              <option value="inactive">Inactive (cannot log in)</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary" id="us-save">Save</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  'use strict';
  var users = @json($users);
  var base = @json(url('/users'));
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var modal = new bootstrap.Modal(document.getElementById('usModal'));
  var form = document.getElementById('usForm');
  var editing = null;
  var toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
  function initials(n) { return n.trim().split(/\s+/).slice(0, 2).map(function (w) { return w[0].toUpperCase(); }).join(''); }

  function render() {
    var q = document.getElementById('us-search').value.trim().toLowerCase();
    var body = document.getElementById('us-body');
    body.innerHTML = '';
    users.filter(function (u) { return !q || (u.name + ' ' + u.email + ' ' + u.role_name).toLowerCase().indexOf(q) > -1; })
      .forEach(function (u) {
        var tr = document.createElement('tr');
        tr.className = 'us-row' + (u.status === 'active' ? '' : ' is-off');
        tr.innerHTML =
          '<td><div class="d-flex align-items-center gap-2"><span class="us-avatar">' + esc(initials(u.name)) + '</span><div><div class="fw-semibold">' + esc(u.name) +
            (u.is_me ? ' <span class="badge bg-info text-dark">You</span>' : '') + '</div><div class="small text-muted">' + esc(u.email) + '</div></div></div></td>' +
          '<td><span class="badge bg-light text-dark border us-role' + (u.is_super ? ' super' : '') + '">' + (u.is_super ? '<i class="bi bi-star-fill"></i> ' : '') + esc(u.role_name) + '</span></td>' +
          '<td>' + (u.status === 'active' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>') + '</td>' +
          '<td class="d-none d-md-table-cell small text-muted">' + esc(u.created || '') + '</td>' +
          '<td class="text-end text-nowrap"><button class="btn btn-sm btn-outline-primary us-edit" title="Edit"><i class="bi bi-pencil"></i></button> ' +
            (u.is_me ? '' : '<button class="btn btn-sm btn-outline-danger us-del" title="Delete"><i class="bi bi-trash"></i></button>') + '</td>';
        tr.querySelector('.us-edit').addEventListener('click', function () { open(u); });
        var del = tr.querySelector('.us-del');
        if (del) del.addEventListener('click', function () { remove(u); });
        body.appendChild(tr);
      });
    if (!body.children.length) body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No users found.</td></tr>';
  }

  function open(u) {
    editing = u || null;
    form.reset();
    form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
    form.querySelectorAll('.invalid-feedback').forEach(function (el) { el.remove(); });
    document.getElementById('usTitle').textContent = u ? 'Edit ' + u.name : 'Add user';
    document.getElementById('us-password-label').textContent = u ? 'New password (optional)' : 'Password';
    document.getElementById('us-password-help').textContent = u ? 'Leave empty to keep the current password.' : 'At least 8 characters. Share it with the person securely.';
    form.name.value = u ? u.name : '';
    form.email.value = u ? u.email : '';
    // New users start with the first ordinary role (never Super Admin by accident)
    form.role_id.value = u ? u.role_id : (form.role_id.querySelector('option[data-super="0"]:not([disabled])') || form.role_id.options[0]).value;
    form.status.value = u ? u.status : 'active';
    // Own account: role and status are locked (no self lock-out)
    form.role_id.disabled = !!(u && u.is_me);
    form.status.disabled = !!(u && u.is_me);
    modal.show();
    setTimeout(function () { form.name.focus(); }, 300);
  }

  document.getElementById('us-generate').addEventListener('click', function () {
    var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%';
    var a = new Uint32Array(14); crypto.getRandomValues(a);
    form.password.value = Array.prototype.map.call(a, function (n) { return chars[n % chars.length]; }).join('');
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
    form.querySelectorAll('.invalid-feedback').forEach(function (el) { el.remove(); });
    var body = { name: form.name.value, email: form.email.value, password: form.password.value || null,
      role_id: editing && editing.is_me ? editing.role_id : form.role_id.value, status: editing && editing.is_me ? 'active' : form.status.value };
    var btn = document.getElementById('us-save'); btn.disabled = true;
    fetch(editing ? base + '/' + editing.id : base, {
      method: editing ? 'PUT' : 'POST',
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (res) {
        btn.disabled = false;
        if (!res.ok) {
          var errors = res.d.errors || {};
          Object.keys(errors).forEach(function (k) {
            var input = form.querySelector('[name="' + k + '"]');
            var fb = document.createElement('div'); fb.className = 'invalid-feedback d-block'; fb.textContent = errors[k][0];
            if (input) { input.classList.add('is-invalid'); (input.closest('.input-group') || input).insertAdjacentElement('afterend', fb); }
          });
          if (!Object.keys(errors).length) Swal.fire('Not saved', res.d.message || 'Please try again.', 'error');
          return;
        }
        var u = res.d.user;
        var i = users.findIndex(function (x) { return x.id === u.id; });
        if (i > -1) users[i] = u; else users.push(u);
        users.sort(function (a, b) { return a.name.localeCompare(b.name); });
        modal.hide(); render(); toast.fire({ icon: 'success', title: res.d.message });
      }).catch(function () { btn.disabled = false; Swal.fire('Error', 'Could not save. Please try again.', 'error'); });
  });

  function remove(u) {
    Swal.fire({ icon: 'warning', title: 'Delete ' + u.name + '?', text: 'They will no longer be able to log in. Tip: set them Inactive instead to keep the account.', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#d33' })
      .then(function (r) {
        if (!r.isConfirmed) return;
        fetch(base + '/' + u.id, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } })
          .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
          .then(function (res) {
            if (!res.ok) { var e = res.d.errors || {}; Swal.fire('Not deleted', (e[Object.keys(e)[0]] || [res.d.message])[0], 'warning'); return; }
            users = users.filter(function (x) { return x.id !== u.id; }); render(); toast.fire({ icon: 'success', title: 'User deleted' });
          });
      });
  }

  document.getElementById('us-add').addEventListener('click', function () { open(null); });
  document.getElementById('us-search').addEventListener('input', render);
  render();
})();
</script>
@endpush
