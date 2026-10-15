@extends('backend.layouts.master')
@section('title')
Roles & Permissions
@endsection
@push('styles')
<style>
  .rl-item { border: 1px solid var(--ad-line); border-radius: 12px; padding: 10px 12px; margin-bottom: 8px; cursor: pointer; background: var(--ad-surface); transition: border-color .15s, box-shadow .15s; }
  .rl-item:hover { border-color: #c9d6ea; }
  .rl-item.is-selected { border-color: #1861c5; box-shadow: 0 0 0 .18rem rgba(24,97,197,.15); }
  .rl-name { font-weight: 700; color: var(--ad-ink); }
  .rl-meta { font-size: 12.5px; color: var(--ad-muted); }
  .rl-group { border: 1px solid var(--ad-line); border-radius: 12px; margin-bottom: 12px; overflow: hidden; }
  .rl-group-head { display: flex; justify-content: space-between; align-items: center; background: var(--ad-subtle); padding: 8px 12px; font-weight: 800; color: var(--ad-brand); font-size: 14px; }
  .rl-perm { display: flex; gap: 10px; padding: 9px 12px; border-top: 1px solid var(--ad-line-soft); margin: 0; cursor: pointer; }
  .rl-perm:hover { background: var(--ad-hover); }
  .rl-perm .form-check-input { margin-top: .2rem; flex-shrink: 0; width: 1.1em; height: 1.1em; }
  .rl-perm-name { font-weight: 600; color: var(--ad-ink); font-size: 14px; }
  .rl-perm-help { font-size: 12.5px; color: var(--ad-muted); }
  .rl-perm.is-locked { opacity: .55; cursor: not-allowed; }
  .rl-super-note { background: #fdecf3; color: #8d2155; border-radius: 10px; padding: 10px 14px; font-size: 14px; }
</style>
@endpush
@section('content')
<div class="pagetitle d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div>
    <h1>Roles &amp; Permissions</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li><li class="breadcrumb-item active">Roles &amp; Permissions</li></ol></nav>
  </div>
  @can('users.manage')<a href="{{ route('users.index') }}" class="btn btn-outline-secondary"><i class="bi bi-people"></i> Users</a>@endcan
</div>

<section class="section">
  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card mb-0">
        <div class="card-header text-white fw-bold" style="background:#1861c5"><i class="bi bi-shield-lock"></i> ROLES</div>
        <div class="card-body pt-3">
          <button class="btn btn-primary w-100 mb-3" id="rl-new"><i class="bi bi-plus-lg"></i> New role</button>
          <div id="rl-list"></div>
          <p class="small text-muted mb-0 mt-2"><i class="bi bi-info-circle"></i> Assign roles to people on the <a href="{{ route('users.index') }}">Users</a> page.</p>
        </div>
      </div>
    </div>
    <div class="col-lg-8">
      <form class="card mb-0" id="rl-form" novalidate>
        <div class="card-header text-white fw-bold d-flex justify-content-between align-items-center" style="background:#1861c5">
          <span><i class="bi bi-ui-checks-grid"></i> <span id="rl-heading">PERMISSIONS</span></span>
          <span class="badge bg-light text-dark" id="rl-count"></span>
        </div>
        <div class="card-body pt-3">
          <div class="row g-3 mb-3">
            <div class="col-md-5">
              <label class="form-label fw-semibold" for="rl-name">Role name</label>
              <input class="form-control" id="rl-name" name="name" maxlength="100" required>
            </div>
            <div class="col-md-7">
              <label class="form-label fw-semibold" for="rl-desc">Description</label>
              <input class="form-control" id="rl-desc" name="description" maxlength="255" placeholder="What is this role for?">
            </div>
          </div>
          <div class="rl-super-note mb-3 d-none" id="rl-super-note"><i class="bi bi-star-fill"></i> <b>Super Admin</b> always has every permission, including future ones. It cannot be changed or deleted.</div>
          <div id="rl-perms">
            @foreach($groups as $group => $items)
            <div class="rl-group" data-group="{{ $loop->index }}">
              <div class="rl-group-head">
                <span>{{ $group }}</span>
                <label class="small fw-semibold text-dark m-0" style="cursor:pointer"><input type="checkbox" class="form-check-input me-1 rl-all"> Select all</label>
              </div>
              @foreach($items as $key => [$name, $help])
              @php $locked = !$isSuper && !in_array($key, $mine, true); @endphp
              <label class="rl-perm {{ $locked ? 'is-locked' : '' }}" @if($locked) title="You can only give permissions you have yourself" @endif>
                <input type="checkbox" class="form-check-input rl-perm-box" value="{{ $key }}" @disabled($locked) data-locked="{{ $locked ? 1 : 0 }}">
                <span><span class="rl-perm-name">{{ $name }}</span><br><span class="rl-perm-help">{{ $help }}</span></span>
              </label>
              @endforeach
            </div>
            @endforeach
          </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
          <button type="button" class="btn btn-outline-danger" id="rl-delete"><i class="bi bi-trash"></i> Delete role</button>
          <button type="submit" class="btn btn-success" id="rl-save"><i class="bi bi-check2-circle"></i> Save role</button>
        </div>
      </form>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
  'use strict';
  var roles = @json($roles);
  var base = @json(url('/roles'));
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var form = document.getElementById('rl-form');
  var boxes = Array.prototype.slice.call(document.querySelectorAll('.rl-perm-box'));
  var current = null; // null = new role
  var dirty = false;
  var toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

  function renderList() {
    var list = document.getElementById('rl-list');
    list.innerHTML = '';
    roles.forEach(function (r) {
      var el = document.createElement('div');
      el.className = 'rl-item' + (current && current.id === r.id ? ' is-selected' : '');
      el.innerHTML = '<div class="rl-name">' + (r.is_super ? '<i class="bi bi-star-fill text-warning"></i> ' : '') + esc(r.name) + '</div>' +
        '<div class="rl-meta">' + (r.is_super ? 'All permissions' : r.permissions.length + ' permission' + (r.permissions.length === 1 ? '' : 's')) +
        ' · ' + r.users + ' user' + (r.users === 1 ? '' : 's') + '</div>' +
        (r.description ? '<div class="rl-meta mt-1">' + esc(r.description) + '</div>' : '');
      el.addEventListener('click', function () { confirmLeave(function () { select(r); }); });
      list.appendChild(el);
    });
  }

  function confirmLeave(next) {
    if (!dirty) return next();
    Swal.fire({ icon: 'warning', title: 'Discard unsaved changes?', showCancelButton: true, confirmButtonText: 'Discard', confirmButtonColor: '#d33' })
      .then(function (r) { if (r.isConfirmed) { dirty = false; next(); } });
  }

  function select(r) {
    current = r;
    var locked = !!(r && r.is_super);
    document.getElementById('rl-heading').textContent = r ? r.name.toUpperCase() : 'NEW ROLE';
    form.name.value = r ? r.name : '';
    form.description.value = r ? (r.description || '') : '';
    form.name.disabled = form.description.disabled = locked;
    boxes.forEach(function (b) {
      b.checked = r ? r.permissions.indexOf(b.value) > -1 : false;
      b.disabled = locked || b.dataset.locked === '1';
    });
    document.querySelectorAll('.rl-all').forEach(function (a) { a.disabled = locked; });
    document.getElementById('rl-super-note').classList.toggle('d-none', !locked);
    document.getElementById('rl-save').classList.toggle('d-none', locked);
    document.getElementById('rl-delete').classList.toggle('d-none', !r || locked);
    syncAll(); renderList(); dirty = false;
    if (!r) setTimeout(function () { form.name.focus(); }, 50);
  }

  function syncAll() {
    document.querySelectorAll('.rl-group').forEach(function (g) {
      var own = Array.prototype.filter.call(g.querySelectorAll('.rl-perm-box'), function (b) { return !b.disabled; });
      var all = g.querySelector('.rl-all');
      all.checked = own.length > 0 && own.every(function (b) { return b.checked; });
      all.indeterminate = !all.checked && own.some(function (b) { return b.checked; });
    });
    var n = boxes.filter(function (b) { return b.checked; }).length;
    document.getElementById('rl-count').textContent = current && current.is_super ? 'All permissions' : n + ' selected';
  }

  document.querySelectorAll('.rl-all').forEach(function (all) {
    all.addEventListener('change', function () {
      all.closest('.rl-group').querySelectorAll('.rl-perm-box').forEach(function (b) { if (!b.disabled) b.checked = all.checked; });
      dirty = true; syncAll();
    });
  });
  boxes.forEach(function (b) { b.addEventListener('change', function () { dirty = true; syncAll(); }); });
  form.addEventListener('input', function () { dirty = true; });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (current && current.is_super) return;
    var body = { name: form.name.value, description: form.description.value,
      permissions: boxes.filter(function (b) { return b.checked; }).map(function (b) { return b.value; }) };
    var btn = document.getElementById('rl-save'); btn.disabled = true;
    fetch(current ? base + '/' + current.id : base, {
      method: current ? 'PUT' : 'POST',
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (res) {
        btn.disabled = false;
        if (!res.ok) {
          var errs = res.d.errors || {};
          Swal.fire({ icon: 'warning', title: 'Not saved', html: Object.keys(errs).map(function (k) { return esc(errs[k][0]); }).join('<br>') || esc(res.d.message) });
          return;
        }
        var role = res.d.role;
        var i = roles.findIndex(function (x) { return x.id === role.id; });
        if (i > -1) roles[i] = role; else roles.push(role);
        dirty = false; select(role); toast.fire({ icon: 'success', title: res.d.message });
      }).catch(function () { btn.disabled = false; Swal.fire('Error', 'Could not save. Please try again.', 'error'); });
  });

  document.getElementById('rl-delete').addEventListener('click', function () {
    if (!current) return;
    Swal.fire({ icon: 'warning', title: 'Delete role "' + current.name + '"?', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#d33' })
      .then(function (r) {
        if (!r.isConfirmed) return;
        fetch(base + '/' + current.id, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } })
          .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
          .then(function (res) {
            if (!res.ok) { var e = res.d.errors || {}; Swal.fire('Not deleted', (e[Object.keys(e)[0]] || [res.d.message])[0], 'warning'); return; }
            roles = roles.filter(function (x) { return x.id !== current.id; });
            dirty = false; select(roles[0] || null); toast.fire({ icon: 'success', title: 'Role deleted' });
          });
      });
  });

  document.getElementById('rl-new').addEventListener('click', function () { confirmLeave(function () { select(null); }); });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  select(roles.find(function (r) { return !r.is_super; }) || roles[0] || null);
})();
</script>
@endpush
