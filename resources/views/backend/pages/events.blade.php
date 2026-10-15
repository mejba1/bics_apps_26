@extends('backend.layouts.master')
@section('title')
Events
@endsection
@push('styles')
<style>
  .ev-row.is-current td { background: #f3faef; }
  .ev-row.is-archived td { opacity: .7; }
  .ev-name { font-weight: 700; }
  .ev-lock { display: inline-flex; align-items: center; gap: 5px; font-size: 11.5px; font-weight: 700; padding: 3px 8px; border-radius: 7px; margin-left: 4px; vertical-align: 1px; white-space: nowrap; }
  .ev-lock.ro { background: var(--ad-chip, #eef1f6); color: var(--ad-muted, #6b7385); }
  .ev-lock.open { background: var(--ad-amber-50, #fdf3e2); color: var(--ad-amber, #b26a00); }
  .ev-lock.soon { background: var(--ad-brand-50, #eaf2fd); color: var(--ad-brand, #0e4ca1); }
  .ev-lock.live { background: #b22e6b; color: #fff; }
  .ev-code { font-family: monospace; font-size: 12px; background: #f1f3f8; border-radius: 4px; padding: 1px 6px; }
  .ev-reg { font-weight: 600; font-size: 12px; }
  .ev-reg.open { color: #2f6b1c; } .ev-reg.upcoming { color: #7a5410; } .ev-reg.closed { color: #9b2230; }
  .ev-help { font-size: 12.5px; color: var(--ad-muted); margin-top: 4px; }
</style>
@endpush
@section('content')
<div class="pagetitle d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div>
    <h1>Events</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li><li class="breadcrumb-item active">Events</li></ol></nav>
  </div>
  <button class="btn btn-primary" id="ev-add"><i class="bi bi-calendar-plus"></i> Create event</button>
</div>

<section class="section">
  <div class="alert alert-info small py-2"><i class="bi bi-broadcast"></i> The <b>current event</b> is the one the public registration form, the raffle and confirmation emails use. Create the next event as a draft, set it up, then make it current when registration should switch.</div>
  <div class="card">
    <div class="card-header text-white fw-bold d-flex justify-content-between align-items-center" style="background:#1861c5">
      <span><i class="bi bi-calendar2-week"></i> ALL EVENTS</span>
      <input type="search" class="form-control form-control-sm" id="ev-search" placeholder="Search events…" style="max-width:240px">
    </div>
    <div class="card-body pt-3">
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>Event</th><th class="d-none d-md-table-cell">Dates</th><th>Registration</th><th class="text-end">People</th><th>Status</th><th class="text-end">Action</th></tr></thead>
          <tbody id="ev-body"></tbody>
        </table>
      </div>
    </div>
  </div>
</section>

<div class="modal fade" id="evModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" id="evForm" novalidate>
      <div class="modal-header">
        <h5 class="modal-title">Create event</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-semibold" for="ev-copy">Start from</label>
          <select class="form-select" id="ev-copy"><option value="">Blank event</option></select>
          <div class="ev-help">Copies the timezone, venue and registration messages of an earlier event. Name, code and dates are always new.</div>
        </div>
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label fw-semibold" for="ev-name">Event name</label>
            <input class="form-control" id="ev-name" name="event_name" maxlength="200" required placeholder="e.g. 5th Beacon International Cancer Summit 2027">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold" for="ev-short">Short name</label>
            <input class="form-control" id="ev-short" name="event_short" maxlength="50" required placeholder="e.g. BICS 2027">
          </div>
          <div class="col-md-8">
            <label class="form-label fw-semibold" for="ev-venue">Venue</label>
            <input class="form-control" id="ev-venue" name="event_venue" maxlength="255" placeholder="Hall, city, country">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold" for="ev-code">Event code</label>
            <input class="form-control" id="ev-code" name="venue_code" maxlength="50" required placeholder="e.g. Dubai-27">
            <div class="ev-help">Saved on each registration. Letters, numbers, - or _.</div>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold" for="ev-tz">Timezone</label>
            <select class="form-select" id="ev-tz" name="event_timezone">
              @foreach($timezones as $tz)<option value="{{ $tz }}" @selected($tz === $defaultTimezone)>{{ $tz }}</option>@endforeach
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold" for="ev-start">Event starts</label>
            <input type="datetime-local" class="form-control" id="ev-start" name="event_start" required>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold" for="ev-end">Event ends <span class="text-muted fw-normal">(optional)</span></label>
            <input type="datetime-local" class="form-control" id="ev-end" name="event_end">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold" for="ev-opens">Registration opens</label>
            <input type="datetime-local" class="form-control" id="ev-opens" name="registration_opens">
            <div class="ev-help">Empty = open as soon as it is current.</div>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold" for="ev-closes">Registration closes</label>
            <input type="datetime-local" class="form-control" id="ev-closes" name="registration_closes">
            <div class="ev-help">Empty = never closes automatically.</div>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold" for="ev-status">Status</label>
            <select class="form-select" id="ev-status" name="status">
              <option value="draft">Draft (being set up)</option>
              <option value="published">Published</option>
            </select>
          </div>
        </div>
        <input type="hidden" name="registration_closed_message">
        <input type="hidden" name="registration_upcoming_message">
        <div class="form-check mt-3">
          <input class="form-check-input" type="checkbox" id="ev-current" name="make_current" value="1">
          <label class="form-check-label" for="ev-current"><b>Make it the current event now</b> — new registrations go to this event straight away.</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary" id="ev-save"><i class="bi bi-check2-circle"></i> Create event</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  'use strict';
  var events = @json($events);
  var base = @json(url('/events'));
  var isSuper = @json(auth()->user()->isSuperAdmin());
  var UNLOCK_H = @json(\App\Support\EventLock::UNLOCK_HOURS);
  function lockBadge(e) {
    var L = e.lock || {};
    if (L.locked) return '<span class="ev-lock ro" title="' + esc(L.reason) + ' Check-in, gifts, the raffle and registration changes are closed."><i class="bi bi-lock-fill"></i> Read-only</span>';
    if (L.unlocked) return '<span class="ev-lock open" title="Unlocked by ' + esc(L.unlockedBy || 'a Super Admin') + '"><i class="bi bi-unlock-fill"></i> Unlocked until ' + esc(L.unlockedUntil) + '</span>';
    if (L.locksAt) return '<span class="ev-lock soon" title="' + esc(L.reason) + '"><i class="bi bi-hourglass-split"></i> Read-only from ' + esc(L.locksAt) + '</span>';
    if (L.phase === 'live') return '<span class="ev-lock live"><i class="bi bi-broadcast-pin"></i> Live now</span>';
    return '';
  }
  function lockButton(e) {
    var L = e.lock || {};
    if (!isSuper) return '';
    if (L.locked) return '<button class="btn btn-sm btn-outline-warning ev-act" data-act="unlock" title="Unlock for ' + UNLOCK_H + ' hours to correct something"><i class="bi bi-unlock"></i> Unlock</button> ';
    if (L.unlocked) return '<button class="btn btn-sm btn-outline-secondary ev-act" data-act="lock" title="Make it read-only again now"><i class="bi bi-lock"></i> Lock</button> ';
    return '';
  }
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var modal = new bootstrap.Modal(document.getElementById('evModal'));
  var form = document.getElementById('evForm');
  var toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2500 });
  var STATUS = { draft: ['secondary', 'Draft'], published: ['primary', 'Published'], archived: ['dark', 'Archived'] };
  var REG = { open: 'Open', upcoming: 'Not open yet', closed: 'Closed' };
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

  function request(url, method, body) {
    return fetch(url, {
      method: method,
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: body ? JSON.stringify(body) : null
    }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); });
  }
  function firstError(d) { var e = d.errors || {}; var k = Object.keys(e)[0]; return k ? e[k][0] : (d.message || 'Please try again.'); }

  function render() {
    var q = document.getElementById('ev-search').value.trim().toLowerCase();
    var body = document.getElementById('ev-body');
    body.innerHTML = '';
    events.filter(function (e) { return !q || (e.event_name + ' ' + e.event_short + ' ' + e.venue_code + ' ' + (e.event_venue || '')).toLowerCase().indexOf(q) > -1; })
      .forEach(function (e) {
        var tr = document.createElement('tr');
        tr.className = 'ev-row' + (e.is_current ? ' is-current' : '') + (e.status === 'archived' ? ' is-archived' : '');
        var st = STATUS[e.status] || STATUS.draft;
        var actions = lockButton(e) + '<a class="btn btn-sm btn-outline-primary" href="' + e.edit_url + '" title="Edit"><i class="bi bi-pencil"></i></a> ' +
          '<button class="btn btn-sm btn-outline-secondary ev-copy" title="Create a new event from this one"><i class="bi bi-copy"></i></button> ' +
          '<div class="btn-group"><button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" title="More"><i class="bi bi-three-dots"></i></button><ul class="dropdown-menu dropdown-menu-end">' +
            (e.is_current ? '' : '<li><a class="dropdown-item ev-act" data-act="current" href="#"><i class="bi bi-broadcast"></i> Make current event</a></li>') +
            '<li><a class="dropdown-item" href="' + e.dashboard_url + '"><i class="bi bi-grid"></i> Dashboard</a></li>' +
            (e.is_current ? '' :
              (e.status !== 'published' ? '<li><a class="dropdown-item ev-act" data-act="published" href="#"><i class="bi bi-eye"></i> Publish</a></li>' : '') +
              (e.status !== 'draft' ? '<li><a class="dropdown-item ev-act" data-act="draft" href="#"><i class="bi bi-pencil-square"></i> Move to draft</a></li>' : '') +
              (e.status !== 'archived' ? '<li><a class="dropdown-item ev-act" data-act="archived" href="#"><i class="bi bi-archive"></i> Archive</a></li>' : '') +
              (e.registrations ? '' : '<li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-danger ev-act" data-act="delete" href="#"><i class="bi bi-trash"></i> Delete</a></li>')) +
          '</ul></div>';
        tr.innerHTML =
          '<td><div class="ev-name">' + esc(e.event_short) + (e.is_current ? ' <span class="badge bg-success"><i class="bi bi-broadcast"></i> Current</span>' : '') + lockBadge(e) + '</div>' +
            '<div class="small text-muted">' + esc(e.event_name) + '</div><span class="ev-code">' + esc(e.venue_code) + '</span>' +
            (e.event_venue ? ' <span class="small text-muted"><i class="bi bi-geo-alt"></i> ' + esc(e.event_venue) + '</span>' : '') + '</td>' +
          '<td class="d-none d-md-table-cell small">' + (esc(e.dates) || '<span class="text-muted">—</span>') + '</td>' +
          '<td><span class="ev-reg ' + e.registration_state + '">' + (e.is_current ? REG[e.registration_state] : '<span class="text-muted fw-normal">' + (e.status === 'archived' ? 'Finished' : 'Not current') + '</span>') + '</span></td>' +
          '<td class="text-end"><b>' + e.registrations + '</b>' + (e.checked_in ? '<div class="small text-muted">' + e.checked_in + ' checked in</div>' : '') + '</td>' +
          '<td><span class="badge bg-' + st[0] + '">' + st[1] + '</span></td>' +
          '<td class="text-end text-nowrap">' + actions + '</td>';
        tr.querySelector('.ev-copy').addEventListener('click', function () { open(e); });
        tr.querySelectorAll('.ev-act').forEach(function (a) { a.addEventListener('click', function (ev) { ev.preventDefault(); act(e, a.dataset.act); }); });
        body.appendChild(tr);
      });
    if (!body.children.length) body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No events found.</td></tr>';
  }

  function act(e, what) {
    var cfg = {
      current: { title: 'Make ' + e.event_short + ' the current event?', text: 'The public registration form, the raffle and confirmation emails switch to this event.', btn: 'Make current', url: base + '/' + e.id + '/current', method: 'POST' },
      delete: { title: 'Delete ' + e.event_short + '?', text: 'It has no registrations. This cannot be undone.', btn: 'Delete', url: base + '/' + e.id, method: 'DELETE', danger: true },
      archived: { title: 'Archive ' + e.event_short + '?', text: 'Its registrations and reports stay available.', btn: 'Archive', url: base + '/' + e.id + '/status', method: 'POST', body: { status: 'archived' } },
      draft: { url: base + '/' + e.id + '/status', method: 'POST', body: { status: 'draft' } },
      published: { url: base + '/' + e.id + '/status', method: 'POST', body: { status: 'published' } },
      unlock: { title: 'Unlock ' + e.event_short + ' for ' + UNLOCK_H + ' hours?', text: (e.lock && e.lock.reason ? e.lock.reason + ' ' : '') + 'Check-in, gifts, the raffle and registrations can be changed again until it locks by itself. This is recorded in the audit log.', btn: 'Unlock', url: base + '/' + e.id + '/unlock', method: 'POST' },
      lock: { title: 'Lock ' + e.event_short + ' again?', text: 'Its data becomes read-only again right away.', btn: 'Lock', url: base + '/' + e.id + '/lock', method: 'POST' }
    }[what];
    var run = function () {
      request(cfg.url, cfg.method, cfg.body).then(function (res) {
        if (!res.ok) { Swal.fire('Not changed', firstError(res.d), 'warning'); return; }
        events = res.d.events; render(); toast.fire({ icon: 'success', title: res.d.message });
      }).catch(function () { Swal.fire('Error', 'Please try again.', 'error'); });
    };
    if (!cfg.title) return run();
    Swal.fire({ icon: cfg.danger ? 'warning' : 'question', title: cfg.title, text: cfg.text, showCancelButton: true, confirmButtonText: cfg.btn, confirmButtonColor: cfg.danger ? '#d33' : undefined })
      .then(function (r) { if (r.isConfirmed) run(); });
  }

  // Copy-from list
  var copy = document.getElementById('ev-copy');
  events.forEach(function (e) { var o = document.createElement('option'); o.value = e.id; o.textContent = e.event_short + ' (' + e.venue_code + ')'; copy.appendChild(o); });
  function applyCopy() {
    var src = events.find(function (e) { return String(e.id) === copy.value; });
    form.event_venue.value = src ? (src.event_venue || '') : '';
    if (src && src.event_timezone) form.event_timezone.value = src.event_timezone;
    form.registration_closed_message.value = src ? (src.registration_closed_message || '') : '';
    form.registration_upcoming_message.value = src ? (src.registration_upcoming_message || '') : '';
  }
  copy.addEventListener('change', applyCopy);

  function clearErrors() {
    form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
    form.querySelectorAll('.invalid-feedback').forEach(function (el) { el.remove(); });
  }
  function open(from) {
    form.reset(); clearErrors();
    copy.value = from ? from.id : '';
    applyCopy();
    codeTouched = false;
    modal.show();
    setTimeout(function () { form.event_name.focus(); }, 300);
  }

  // Suggest the event code from the short name ("BICS 2027" -> "BICS-2027") until it is typed by hand
  var codeTouched = false;
  form.venue_code.addEventListener('input', function () { codeTouched = true; });
  form.event_short.addEventListener('input', function () {
    if (codeTouched) return;
    form.venue_code.value = form.event_short.value.trim().replace(/[^A-Za-z0-9_\-]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 50);
  });

  form.addEventListener('submit', function (ev) {
    ev.preventDefault(); clearErrors();
    var body = {};
    new FormData(form).forEach(function (v, k) { body[k] = v; });
    body.make_current = form.make_current.checked;
    if (body.make_current) body.status = 'published';
    var btn = document.getElementById('ev-save'); btn.disabled = true;
    request(base, 'POST', body).then(function (res) {
      btn.disabled = false;
      if (!res.ok) {
        var errors = res.d.errors || {};
        Object.keys(errors).forEach(function (k) {
          var input = form.querySelector('[name="' + k + '"]');
          if (!input || input.type === 'hidden') return;
          input.classList.add('is-invalid');
          var fb = document.createElement('div'); fb.className = 'invalid-feedback'; fb.textContent = errors[k][0];
          input.insertAdjacentElement('afterend', fb);
        });
        if (!form.querySelector('.is-invalid')) Swal.fire('Not saved', firstError(res.d), 'error');
        return;
      }
      modal.hide();
      // Continue on the event's settings page (email texts, messages, etc.)
      Swal.fire({ icon: 'success', title: res.d.message, text: 'Opening the event settings…', timer: 1400, showConfirmButton: false })
        .then(function () { location.href = res.d.edit_url; });
    }).catch(function () { btn.disabled = false; Swal.fire('Error', 'Could not save. Please try again.', 'error'); });
  });

  document.getElementById('ev-add').addEventListener('click', function () { open(null); });
  document.getElementById('ev-search').addEventListener('input', render);
  render();
})();
</script>
@endpush
