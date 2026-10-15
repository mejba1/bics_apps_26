@extends('backend.layouts.master')
@section('title')
Backup & Export
@endsection
@push('styles')
<style>
  .bk-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 20px; }
  .bk-stat { background: var(--ad-surface); border: 1px solid var(--ad-line); border-radius: 14px; padding: 14px 16px; }
  .bk-stat .l { font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--ad-muted); }
  .bk-stat .v { font-size: 22px; font-weight: 800; color: var(--ad-ink); margin-top: 2px; word-break: break-all; }
  .bk-card { background: var(--ad-surface); border: 1px solid var(--ad-line); border-radius: 14px; margin-bottom: 20px; }
  .bk-card > h2 { font-size: 13px; font-weight: 800; letter-spacing: .6px; text-transform: uppercase; color: var(--ad-brand); margin: 0; padding: 14px 18px; border-bottom: 1px solid var(--ad-line); display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; }
  .bk-card > h2 span { display: flex; align-items: center; gap: 8px; }
  .bk-card > .body { padding: 16px 18px; }
  .bk-help { font-size: 13px; color: var(--ad-muted); }
  .bk-tables { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 4px 14px; max-height: 260px; overflow: auto; border: 1px solid var(--ad-line); border-radius: 10px; padding: 10px 12px; }
  .bk-tables label { display: flex; justify-content: space-between; gap: 8px; font-size: 13.5px; padding: 3px 0; cursor: pointer; color: var(--ad-ink); }
  .bk-tables label small { color: var(--ad-muted); }
  .bk-list td { vertical-align: middle; }
  .bk-list .name { font-weight: 600; color: var(--ad-ink); word-break: break-all; }
  .bk-tag { font-size: 11.5px; font-weight: 700; padding: 2px 8px; border-radius: 999px; background: var(--ad-chip); color: var(--ad-muted); text-transform: capitalize; white-space: nowrap; }
  .bk-tag.auto { background: var(--ad-amber-50); color: var(--ad-amber); }
  .bk-tag.manual { background: var(--ad-green-50); color: var(--ad-green); }
  .bk-danger { border-color: var(--ad-red); }
  .bk-danger > h2 { color: var(--ad-red); background: var(--ad-red-50); border-radius: 14px 14px 0 0; }
  .bk-empty { text-align: center; color: var(--ad-muted); padding: 26px !important; }
</style>
@endpush
@section('content')
@php
  $size = fn ($b) => $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, round($b / 1024)) . ' KB';
  $backupsJson = array_map(fn ($b) => $b + ['size' => $size($b['bytes']), 'date' => \App\Http\Controllers\BackupController::when($b['time'])], $backups);
@endphp
<div class="pagetitle">
  <h1>Backup &amp; Export</h1>
  <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li><li class="breadcrumb-item active">Backup &amp; Export</li></ol></nav>
</div>

<section class="section">
  <div class="bk-stats">
    <div class="bk-stat"><div class="l">Database</div><div class="v" style="font-size:17px">{{ $database }}</div></div>
    <div class="bk-stat"><div class="l">Tables</div><div class="v">{{ count($tables) }}</div></div>
    <div class="bk-stat"><div class="l">Rows</div><div class="v">{{ number_format($totalRows) }}</div></div>
    <div class="bk-stat"><div class="l">Size</div><div class="v">{{ $size($totalBytes) }}</div></div>
    <div class="bk-stat"><div class="l">Last backup</div><div class="v" style="font-size:15px">{{ $backups ? \App\Http\Controllers\BackupController::when($backups[0]['time']) : 'Never' }}</div></div>
  </div>

  <div class="row g-4">
    <div class="col-xl-7">
      {{-- Backups on the server --}}
      <div class="bk-card">
        <h2><span><i class="bi bi-archive"></i> Backups</span>
          <span><button type="button" class="btn btn-sm btn-success" id="bkCreate"><i class="bi bi-plus-circle"></i> Back up now</button></span></h2>
        <div class="table-responsive">
          <table class="table bk-list mb-0">
            <thead><tr><th>File</th><th>Created</th><th>Size</th><th class="text-end">Actions</th></tr></thead>
            <tbody id="bkList"></tbody>
          </table>
        </div>
        <div class="body bk-help pt-2"><i class="bi bi-shield-check"></i> Backups are stored outside the public website folder. A safety backup is made automatically before every restore or import (the newest 10 are kept).</div>
      </div>

      {{-- Export --}}
      <form class="bk-card" method="POST" action="{{ route('backups.create') }}" id="bkExport">
        @csrf
        <input type="hidden" name="download" value="1">
        <h2><span><i class="bi bi-cloud-download"></i> Export</span>
          <span class="d-flex gap-2"><button type="button" class="btn btn-sm btn-light" id="bkAll">Select all</button><button type="button" class="btn btn-sm btn-light" id="bkNone">None</button></span></h2>
        <div class="body">
          <p class="bk-help mt-0">Download the whole database, or only the tables you tick (nothing ticked = everything). The file can be imported here or in phpMyAdmin.</p>
          <div class="bk-tables">
            @foreach($tables as $t)
              <label><span><input type="checkbox" class="form-check-input me-1" name="tables[]" value="{{ $t['name'] }}"> {{ $t['name'] }}</span><small>{{ number_format($t['rows']) }}</small></label>
            @endforeach
          </div>
          <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between mt-3">
            <div class="d-flex gap-3">
              <label class="form-check mb-0"><input class="form-check-input" type="radio" name="format" value="gz" checked> <span class="form-check-label">.sql.gz <small class="text-muted">(compressed)</small></span></label>
              <label class="form-check mb-0"><input class="form-check-input" type="radio" name="format" value="sql"> <span class="form-check-label">.sql</span></label>
            </div>
            <div class="d-flex gap-2 flex-wrap">
              <a class="btn btn-outline-secondary" href="{{ route('backups.uploads') }}" id="bkZip" title="Photos, ID cards, gallery, attachments"><i class="bi bi-file-zip"></i> Uploaded files (ZIP)</a>
              <button class="btn btn-primary"><i class="bi bi-download"></i> Export &amp; download</button>
            </div>
          </div>
          <p class="bk-help mb-0 mt-2"><i class="bi bi-info-circle"></i> Registration lists for Excel are in <a href="{{ route('reports.index') }}">Reports</a>.</p>
        </div>
      </form>
    </div>

    <div class="col-xl-5">
      @if($canRestore)
      <form class="bk-card bk-danger" id="bkImport" novalidate>
        <h2><span><i class="bi bi-cloud-upload"></i> Import database</span></h2>
        <div class="body">
          <p class="bk-help mt-0"><b>Replaces data</b> with the tables in the file (an .sql or .sql.gz export from here or phpMyAdmin). A safety backup is made first, so you can go back.</p>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="bk-file">SQL file <span class="text-muted fw-normal">(max {{ $maxUploadMb }} MB)</span></label>
            <input class="form-control" type="file" id="bk-file" name="file" accept=".sql,.gz,application/sql,application/gzip">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="bk-pass">Your password</label>
            <input class="form-control" type="password" id="bk-pass" name="password" autocomplete="current-password">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="bk-confirm">Type <code>REPLACE</code> to confirm</label>
            <input class="form-control" id="bk-confirm" name="confirm" autocomplete="off">
          </div>
          <button class="btn btn-danger w-100"><i class="bi bi-cloud-upload"></i> Import and replace data</button>
        </div>
      </form>
      @else
      <div class="bk-card"><div class="body bk-help"><i class="bi bi-lock"></i> Restoring and importing a database replace data, so only a <b>Super Admin</b> can do it.</div></div>
      @endif

      <div class="bk-card">
        <h2><span><i class="bi bi-lightbulb"></i> Tips</span></h2>
        <div class="body bk-help">
          <ul class="ps-3 mb-0">
            <li class="mb-2">Back up before big changes (new event, bulk emails, imports).</li>
            <li class="mb-2">Download a backup now and then and keep it off the server.</li>
            <li class="mb-2">A full backup = database export + uploaded files ZIP.</li>
            <li>Importing users / roles tables can change who can sign in. You will be asked to sign in again if your account is not in the imported data.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script>
  (function () {
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    var canRestore = @json($canRestore);
    var base = @json(url('/system/backups'));
    var backups = @json($backupsJson);
    var esc = function (t) { var d = document.createElement('div'); d.textContent = t == null ? '' : t; return d.innerHTML; };
    var post = function (url, fd, method) {
      if (method) fd.append('_method', method);
      return fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: fd })
        .then(function (r) { return r.json().catch(function () { return { message: 'Server error (' + r.status + ').' }; }).then(function (d) { return { ok: r.ok, d: d }; }); });
    };
    var toast = function (icon, title) { Swal.fire({ toast: true, position: 'top-end', icon: icon, title: title, showConfirmButton: false, timer: 3000 }); };

    function render() {
      var body = document.getElementById('bkList');
      if (!backups.length) { body.innerHTML = '<tr><td colspan="4" class="bk-empty"><i class="bi bi-inbox"></i> No backups yet. Click "Back up now".</td></tr>'; return; }
      body.innerHTML = backups.map(function (b) {
        var auto = /^before/.test(b.label);
        return '<tr><td><div class="name">' + esc(b.name) + '</div><span class="bk-tag ' + (auto ? 'auto' : (b.label === 'manual' ? 'manual' : '')) + '">' + esc(b.label) + '</span></td>'
          + '<td class="text-nowrap">' + esc(b.date) + '</td><td class="text-nowrap">' + esc(b.size) + '</td>'
          + '<td class="text-end text-nowrap">'
          + '<a class="btn btn-sm btn-outline-primary" href="' + base + '/' + encodeURIComponent(b.name) + '/download" title="Download"><i class="bi bi-download"></i></a> '
          + (canRestore ? '<button class="btn btn-sm btn-outline-warning bk-restore" data-name="' + esc(b.name) + '" title="Restore this backup"><i class="bi bi-arrow-counterclockwise"></i></button> ' : '')
          + '<button class="btn btn-sm btn-outline-danger bk-del" data-name="' + esc(b.name) + '" title="Delete"><i class="bi bi-trash"></i></button></td></tr>';
      }).join('');
    }

    function afterImport(res) {
      if (!res.ok) {
        if (res.d.backups) { backups = res.d.backups; render(); }
        Swal.fire({ icon: 'error', title: 'Not completed', html: esc(res.d.message) + (res.d.detail ? '<pre class="text-start small mt-2" style="white-space:pre-wrap">' + esc(res.d.detail) + '</pre>' : '') });
        return false;
      }
      backups = res.d.backups; render();
      Swal.fire({ icon: 'success', title: 'Done', text: res.d.message }).then(function () {
        location.href = res.d.relogin ? @json(url('/login')) : location.href;
      });
      return true;
    }

    document.getElementById('bkCreate').addEventListener('click', function () {
      var btn = this; btn.disabled = true; var label = btn.innerHTML;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Backing up…';
      post(base, new FormData()).then(function (res) {
        btn.disabled = false; btn.innerHTML = label;
        if (!res.ok) { Swal.fire('Backup failed', res.d.message, 'error'); return; }
        backups = res.d.backups; render(); toast('success', 'Backup created');
      });
    });

    document.getElementById('bkList').addEventListener('click', function (e) {
      var del = e.target.closest('.bk-del'), restore = e.target.closest('.bk-restore');
      if (del) {
        Swal.fire({ icon: 'warning', title: 'Delete this backup?', text: del.dataset.name, showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#d33' }).then(function (r) {
          if (!r.isConfirmed) return;
          post(base + '/' + encodeURIComponent(del.dataset.name), new FormData(), 'DELETE').then(function (res) {
            if (!res.ok) { Swal.fire('Error', res.d.message, 'error'); return; }
            backups = res.d.backups; render(); toast('success', res.d.message);
          });
        });
      }
      if (restore) {
        Swal.fire({
          icon: 'warning', title: 'Restore this backup?',
          html: '<p class="mb-2"><b>' + esc(restore.dataset.name) + '</b></p><p class="small text-muted">The tables in this backup replace the current data. A safety backup of the current data is made first.</p>'
            + '<input type="password" id="rsPass" class="form-control mb-2" placeholder="Your password" autocomplete="current-password">'
            + '<input id="rsConfirm" class="form-control" placeholder="Type REPLACE to confirm" autocomplete="off">',
          showCancelButton: true, confirmButtonText: 'Restore', confirmButtonColor: '#d33', showLoaderOnConfirm: true,
          allowOutsideClick: function () { return !Swal.isLoading(); },
          preConfirm: function () {
            var fd = new FormData(); fd.append('password', document.getElementById('rsPass').value); fd.append('confirm', document.getElementById('rsConfirm').value);
            return post(base + '/' + encodeURIComponent(restore.dataset.name) + '/restore', fd).then(function (res) {
              if (!res.ok && res.d.errors) { var er = res.d.errors; Swal.showValidationMessage(er ? er[Object.keys(er)[0]][0] : res.d.message); return false; }
              return res;
            });
          }
        }).then(function (r) { if (r.isConfirmed && r.value) afterImport(r.value); });
      }
    });

    // Select all / none
    document.getElementById('bkAll').addEventListener('click', function () { document.querySelectorAll('#bkExport [name="tables[]"]').forEach(function (c) { c.checked = true; }); });
    document.getElementById('bkNone').addEventListener('click', function () { document.querySelectorAll('#bkExport [name="tables[]"]').forEach(function (c) { c.checked = false; }); });
    document.getElementById('bkExport').addEventListener('submit', function () { toast('info', 'Preparing the export…'); setTimeout(function () { location.reload(); }, 2500); });
    document.getElementById('bkZip').addEventListener('click', function () { toast('info', 'Preparing the ZIP. Large photo folders can take a minute…'); });

    // Import
    var imp = document.getElementById('bkImport');
    if (imp) imp.addEventListener('submit', function (e) {
      e.preventDefault();
      imp.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
      imp.querySelectorAll('.invalid-feedback').forEach(function (el) { el.remove(); });
      var file = imp.file.files[0];
      if (!file) { imp.file.classList.add('is-invalid'); return; }
      Swal.fire({
        icon: 'warning', title: 'Import ' + esc(file.name) + '?', text: 'Tables in this file replace the current data. A safety backup is made first.',
        showCancelButton: true, confirmButtonText: 'Import now', confirmButtonColor: '#d33'
      }).then(function (r) {
        if (!r.isConfirmed) return;
        Swal.fire({ title: 'Importing…', html: 'Making a safety backup, then importing. Please keep this page open.', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
        post(@json(route('backups.import')), new FormData(imp)).then(function (res) {
          if (!res.ok && res.d.errors) {
            Swal.close();
            Object.keys(res.d.errors).forEach(function (k) {
              var input = imp.querySelector('[name="' + k + '"]'); if (!input) return;
              input.classList.add('is-invalid');
              var fb = document.createElement('div'); fb.className = 'invalid-feedback'; fb.textContent = res.d.errors[k][0];
              input.insertAdjacentElement('afterend', fb);
            });
            return;
          }
          if (afterImport(res)) imp.reset();
        }).catch(function () { Swal.fire('Error', 'The connection was interrupted. Check the backups list before trying again.', 'error'); });
      });
    });

    render();
  })();
</script>
@endpush
