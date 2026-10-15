@extends('backend.layouts.master')
@section('title')
{{ $event->event_short }} · Event settings
@endsection
@push('styles')
<style>
  .es-status { display: flex; flex-wrap: wrap; gap: 10px 24px; align-items: center; border-radius: 12px; padding: 14px 18px; font-weight: 600; }
  .es-status.open { background: #eaf7e5; color: #2f6b1c; }
  .es-status.upcoming { background: #fff6df; color: #7a5410; }
  .es-status.closed { background: #fdecee; color: #9b2230; }
  .es-status small { font-weight: 500; opacity: .85; }
  .es-section-title { font-size: 15px; font-weight: 800; color: var(--ad-brand); margin: 6px 0 12px; }
  .es-section-title i { margin-right: 6px; }
  .es-help { font-size: 12.5px; color: var(--ad-muted); margin-top: 4px; }
</style>
@endpush
@section('content')
<div class="pagetitle d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div>
    <h1>{{ $event->event_short }}
      @if($event->is_current)<span class="badge bg-success align-middle fs-6" title="New registrations go to this event"><i class="bi bi-broadcast"></i> Current event</span>
      @else<span class="badge bg-{{ ['draft' => 'secondary', 'published' => 'primary', 'archived' => 'dark'][$event->status] }} align-middle fs-6">{{ ucfirst($event->status) }}</span>@endif
    </h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('events.index') }}">Events</a></li>
        <li class="breadcrumb-item active">{{ $event->event_short }}</li>
      </ol>
    </nav>
  </div>
  <div class="d-flex gap-2">
    <a href="{{ route('events.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> All events</a>
    @unless($event->is_current)<button type="button" class="btn btn-success" id="es-make-current"><i class="bi bi-broadcast"></i> Make current event</button>@endunless
  </div>
</div>

<section class="section">
  <div class="es-status {{ $status['state'] }} mb-3" id="es-status">
    <span><i class="bi bi-person-check"></i> Registration: <span data-s="text">{{ $status['text'] }}</span></span>
    <span><i class="bi bi-calendar-event"></i> Event: <span data-s="event">{{ $status['event'] }}</span></span>
    <small><i class="bi bi-clock"></i> Time at the event now: <span data-s="now">{{ $status['now'] }}</span></small>
  </div>

  <form id="esForm" class="card" novalidate>
    <div class="card-header text-white fw-bold" style="background:#1861c5"><i class="bi bi-calendar2-week"></i> EVENT &amp; REGISTRATION SETTINGS · {{ $registrations }} {{ \Illuminate\Support\Str::plural('registration', $registrations) }}</div>
    <div class="card-body pt-3">
      <div class="row g-4">
        <div class="col-lg-6">
          <div class="es-section-title"><i class="bi bi-megaphone"></i>Event</div>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="es-name">Event name</label>
            <input class="form-control" id="es-name" name="event_name" value="{{ $settings['event_name'] }}" maxlength="200" required>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-sm-5">
              <label class="form-label fw-semibold" for="es-short">Short name</label>
              <input class="form-control" id="es-short" name="event_short" value="{{ $settings['event_short'] }}" maxlength="50" required>
              <div class="es-help">Shown on the verification card and emails.</div>
            </div>
            <div class="col-sm-7">
              <label class="form-label fw-semibold" for="es-venue">Venue</label>
              <input class="form-control" id="es-venue" name="event_venue" value="{{ $settings['event_venue'] }}" maxlength="255">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="es-tz">Event timezone</label>
            <select class="form-select" id="es-tz" name="event_timezone">
              @foreach($timezones as $tz)<option value="{{ $tz }}" @selected($settings['event_timezone'] === $tz)>{{ $tz }}</option>@endforeach
            </select>
            <div class="es-help">All dates below are in this timezone, so every visitor counts down to the same moment.</div>
          </div>
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label fw-semibold" for="es-start">Event starts</label>
              <input type="datetime-local" class="form-control" id="es-start" name="event_start" value="{{ $settings['event_start'] }}" required>
              <div class="es-help">Countdown target on the home page.</div>
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-semibold" for="es-end">Event ends <span class="text-muted fw-normal">(optional)</span></label>
              <input type="datetime-local" class="form-control" id="es-end" name="event_end" value="{{ $settings['event_end'] }}">
              <div class="es-help">For multi-day events (shows "28–30 October").</div>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="es-section-title"><i class="bi bi-door-open"></i>Registration window</div>
          <div class="row g-3 mb-3">
            <div class="col-sm-6">
              <label class="form-label fw-semibold" for="es-opens">Registration opens</label>
              <input type="datetime-local" class="form-control" id="es-opens" name="registration_opens" value="{{ $settings['registration_opens'] }}">
              <div class="es-help">Empty = open now.</div>
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-semibold" for="es-closes">Registration closes</label>
              <input type="datetime-local" class="form-control" id="es-closes" name="registration_closes" value="{{ $settings['registration_closes'] }}">
              <div class="es-help">Empty = never closes automatically.</div>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="es-upcoming">Message before registration opens</label>
            <textarea class="form-control" id="es-upcoming" name="registration_upcoming_message" rows="2" maxlength="500">{{ $settings['registration_upcoming_message'] }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="es-closed">Message after registration closes</label>
            <textarea class="form-control" id="es-closed" name="registration_closed_message" rows="2" maxlength="500">{{ $settings['registration_closed_message'] }}</textarea>
          </div>

          <div class="es-section-title mt-4"><i class="bi bi-gear"></i>Advanced</div>
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label fw-semibold" for="es-venue-code">Event code</label>
              <input class="form-control" id="es-venue-code" name="venue_code" value="{{ $settings['venue_code'] }}" maxlength="50" required @readonly($registrations > 0)>
              <div class="es-help">{{ $registrations > 0 ? 'Locked: registrations are saved with this code.' : 'Saved on every registration, e.g. Spain-26.' }}</div>
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-semibold" for="es-tracking">Tracking no.</label>
              <input class="form-control" id="es-tracking" name="tracking_no" value="{{ $settings['tracking_no'] }}" maxlength="50">
              <div class="es-help">Empty = code + start date.</div>
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-semibold" for="es-slug">Web address</label>
              <div class="input-group"><span class="input-group-text small">/events/</span><input class="form-control" id="es-slug" name="slug" value="{{ $event->slug }}" maxlength="120"></div>
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-semibold" for="es-status-select">Status</label>
              <select class="form-select" id="es-status-select" name="status" @disabled($event->is_current)>
                @foreach(['draft' => 'Draft (being set up)', 'published' => 'Published', 'archived' => 'Archived (past event)'] as $k => $label)
                  <option value="{{ $k }}" @selected($event->status === $k)>{{ $label }}</option>
                @endforeach
              </select>
              @if($event->is_current)<div class="es-help">The current event is always published.</div>@endif
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="card-footer d-flex flex-wrap gap-2 justify-content-between align-items-center">
      @if($event->is_current)
      <span class="small text-muted"><i class="bi bi-eye"></i> See it: <a href="{{ url('/') }}" target="_blank">home page countdown</a> · <a href="{{ url('/registration') }}" target="_blank">registration page</a> · <a href="{{ route('email-template.edit') }}">confirmation email</a></span>
      @else
      <span class="small text-muted"><i class="bi bi-info-circle"></i> Not the current event: the public form keeps registering people for {{ \App\Support\EventSettings::get('event_short') }}.</span>
      @endif
      <button type="submit" class="btn btn-success"><i class="bi bi-check2-circle"></i> Save settings</button>
    </div>
  </form>
</section>
@endsection

@push('scripts')
<script>
  (function () {
    var form = document.getElementById('esForm');
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
      form.querySelectorAll('.invalid-feedback').forEach(function (el) { el.remove(); });
      var btn = form.querySelector('[type=submit]'); btn.disabled = true;
      var data = new FormData(form); data.append('_method', 'PUT');
      fetch(@json(route('events.update', $event)), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: data })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, status: r.status, d: d }; }); })
        .then(function (res) {
          btn.disabled = false;
          if (res.ok) {
            var box = document.getElementById('es-status');
            box.className = 'es-status mb-3 ' + res.d.status.state;
            ['text', 'event', 'now'].forEach(function (k) { box.querySelector('[data-s="' + k + '"]').textContent = res.d.status[k]; });
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: res.d.message, showConfirmButton: false, timer: 2000 });
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
          var first = form.querySelector('.is-invalid'); if (first) first.focus();
          Swal.fire({ icon: 'warning', title: 'Please check', html: Object.keys(errors).map(function (k) { return errors[k][0]; }).join('<br>') || res.d.message });
        })
        .catch(function () { btn.disabled = false; Swal.fire('Error', 'Could not save. Please try again.', 'error'); });
    });

    var makeCurrent = document.getElementById('es-make-current');
    if (makeCurrent) makeCurrent.addEventListener('click', function () {
      Swal.fire({ icon: 'question', title: 'Make ' + @json($event->event_short) + ' the current event?', html: 'The public registration form, the raffle and confirmation emails switch to this event.<br>Save your changes first.', showCancelButton: true, confirmButtonText: 'Make current' })
        .then(function (r) {
          if (!r.isConfirmed) return;
          fetch(@json(route('events.current', $event)), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) { if (res.ok) location.reload(); else Swal.fire('Not changed', res.d.message || 'Please try again.', 'error'); });
        });
    });
  })();
</script>
@endpush
