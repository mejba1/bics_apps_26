@extends('backend.layouts.master')
@section('title')
New Registration
@endsection
@push('styles')
<style>
  .nr-section { font-size: 12px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--ad-muted); margin: 6px 0 12px; display: flex; align-items: center; gap: 8px; }
  .nr-section::after { content: ""; flex: 1; height: 1px; background: var(--ad-line); }
  .nr-req { color: var(--ad-red); }
  /* date of birth: day · month · year (no locale-dependent date box) */
  .nr-dob { display: grid; grid-template-columns: 0.8fr 1.1fr 1fr; gap: 6px; }
  .nr-dob select { padding-left: .6rem; padding-right: 1.6rem; }
  .nr-dob.is-invalid select { border-color: var(--ad-red); }
  .nr-dob-hint { font-size: 12.5px; margin-top: 5px; color: var(--ad-muted); display: flex; align-items: center; gap: 6px; min-height: 18px; }
  .nr-dob-hint.ok { color: var(--ad-green); font-weight: 600; } .nr-dob-hint.bad { color: var(--ad-red); font-weight: 600; }
  .nr-seg { display: flex; gap: 6px; flex-wrap: wrap; }
  .nr-seg input { position: absolute; opacity: 0; pointer-events: none; }
  .nr-seg label { padding: 7px 14px; border-radius: 999px; border: 1.5px solid var(--ad-line); background: var(--ad-surface); font-weight: 600; font-size: 13.5px; cursor: pointer; margin: 0; color: var(--ad-text); transition: all .15s; }
  .nr-seg input:checked + label { background: var(--ad-brand); border-color: var(--ad-brand); color: #fff; }
  html[data-theme="dark"] .nr-seg input:checked + label { color: #06132b; }
  .nr-seg input:focus-visible + label { outline: 3px solid rgba(14, 76, 161, .35); outline-offset: 2px; }
  .nr-photo { width: 120px; height: 120px; border-radius: 50%; border: 2px dashed var(--ad-line); background: var(--ad-subtle) center/cover no-repeat; display: flex; align-items: center; justify-content: center; color: var(--ad-muted); font-size: 34px; cursor: pointer; flex-shrink: 0; transition: border-color .15s; }
  .nr-photo:hover { border-color: var(--ad-brand); }
  .nr-photo.has-photo { border-style: solid; }
  .nr-photo.has-photo i { display: none; }
  .nr-options { background: var(--ad-subtle); border: 1px solid var(--ad-line); border-radius: 12px; padding: 12px 14px; }
  .nr-dial { min-width: 76px; justify-content: center; font-weight: 700; color: var(--ad-brand); }
  .nr-recent li { display: flex; justify-content: space-between; gap: 10px; padding: 9px 0; border-bottom: 1px solid var(--ad-line-soft); font-size: 13.5px; }
  .nr-recent li:last-child { border-bottom: 0; }
  .nr-code { font-weight: 800; color: var(--ad-ink); font-variant-numeric: tabular-nums; }
  .nr-sticky { position: sticky; top: 84px; }
  .nr-error { color: var(--ad-red); font-size: 12.5px; font-weight: 600; margin-top: 4px; }
  .nr-error:empty { display: none; }
</style>
@endpush
@section('content')
<div class="ad-page-head">
  <div>
    <h1>New registration</h1>
    <p class="ad-sub">Register an attendee for <b>{{ \App\Support\EventSettings::get('event_short') }}</b> ({{ $venue }}) — for example on-site at the venue.</p>
  </div>
  <a href="{{ url('/registration') }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-box-arrow-up-right"></i> Public form</a>
</div>

<div class="row g-3">
  <div class="col-xl-8">
    @php $nrEvent = \App\Models\Event::current(); $nrLocked = \App\Support\EventLock::isLocked($nrEvent); @endphp
    @include('partials.event-lock', ['event' => $nrEvent])
    <form class="card mb-0" id="nrForm" novalidate>
    <fieldset @disabled($nrLocked)>
      <div class="card-body">
        <div class="nr-section">Personal details</div>
        <div class="row g-3">
          <div class="col-12">
            <span class="form-label d-block">Title <span class="nr-req">*</span></span>
            <div class="nr-seg" role="radiogroup" aria-label="Title">
              @foreach($titles as $t)
                <input type="radio" name="title" id="nr-t{{ $loop->index }}" value="{{ $t }}" @checked($loop->first)><label for="nr-t{{ $loop->index }}">{{ $t }}</label>
              @endforeach
            </div>
            <div class="nr-error" data-error="title"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="nr-first">First name <span class="nr-req">*</span></label>
            <input class="form-control" id="nr-first" name="firstname" maxlength="100" autocomplete="off" required>
            <div class="nr-error" data-error="firstname"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="nr-last">Last name <span class="nr-req">*</span></label>
            <input class="form-control" id="nr-last" name="lastname" maxlength="100" autocomplete="off" required>
            <div class="nr-error" data-error="lastname"></div>
          </div>
          <div class="col-md-4">
            <span class="form-label d-block">Gender <span class="nr-req">*</span></span>
            <div class="nr-seg">
              <input type="radio" name="gender" id="nr-m" value="Male" checked><label for="nr-m"><i class="bi bi-gender-male"></i> Male</label>
              <input type="radio" name="gender" id="nr-f" value="Female"><label for="nr-f"><i class="bi bi-gender-female"></i> Female</label>
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label" id="nr-dob-label" for="nr-dob-d">Date of birth <span class="nr-req">*</span></label>
            <input type="hidden" id="nr-dob" name="birthday">
            <div class="nr-dob" id="nr-dob-box" role="group" aria-labelledby="nr-dob-label">
              <select class="form-select" id="nr-dob-d" aria-label="Day"><option value="">Day</option>@for($d = 1; $d <= 31; $d++)<option value="{{ $d }}">{{ $d }}</option>@endfor</select>
              <select class="form-select" id="nr-dob-m" aria-label="Month"><option value="">Month</option>@foreach(['January','February','March','April','May','June','July','August','September','October','November','December'] as $i => $mn)<option value="{{ $i + 1 }}" title="{{ $mn }}">{{ substr($mn, 0, 3) }}</option>@endforeach</select>
              <select class="form-select" id="nr-dob-y" aria-label="Year"><option value="">Year</option>@for($y = (int) date('Y') - 10; $y >= 1920; $y--)<option value="{{ $y }}">{{ $y }}</option>@endfor</select>
            </div>
            <div class="nr-dob-hint" id="nr-dob-hint" aria-live="polite"><i class="bi bi-calendar3"></i> Day · Month · Year</div>
            <div class="nr-error" data-error="birthday"></div>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="nr-country">Country <span class="nr-req">*</span></label>
            <select class="form-select" id="nr-country" name="country" required>
              <option value="">Choose…</option>
              @foreach($countries as $c)<option value="{{ $c['name'] }}" data-dial="{{ $c['dial'] }}">{{ $c['name'] }}</option>@endforeach
            </select>
            <div class="nr-error" data-error="country"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="nr-email">Email <span class="nr-req">*</span></label>
            <input type="email" class="form-control" id="nr-email" name="email" maxlength="190" autocomplete="off" required>
            <div class="nr-error" data-error="email"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="nr-phone">Mobile <span class="nr-req">*</span></label>
            <div class="input-group">
              <span class="input-group-text nr-dial" id="nr-dial">+ ··</span>
              <input type="tel" class="form-control" id="nr-phone" name="phone" maxlength="20" inputmode="tel" required>
            </div>
            <div class="nr-error" data-error="phone"></div>
          </div>
        </div>

        <div class="nr-section mt-4">Professional details</div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="nr-prof">Profession <span class="nr-req">*</span></label>
            <select class="form-select" id="nr-prof" name="profession" required>
              <option value="">Choose…</option>
              @foreach($professions as $p)<option value="{{ $p['name'] }}" data-id="{{ $p['id'] }}">{{ $p['name'] }}</option>@endforeach
            </select>
            <div class="nr-error" data-error="profession"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="nr-spec">Speciality</label>
            <select class="form-select" id="nr-spec" name="specialty" disabled><option value="">Choose a profession first</option></select>
            <div class="nr-error" data-error="specialty"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="nr-inst">Institution <span class="nr-req">*</span></label>
            <input class="form-control" id="nr-inst" name="institute" maxlength="190" required>
            <div class="nr-error" data-error="institute"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="nr-desig">Designation</label>
            <input class="form-control" id="nr-desig" name="designation" maxlength="150" placeholder="e.g. Consultant">
          </div>
          <div class="col-12">
            <label class="form-label" for="nr-addr">Institution address</label>
            <input class="form-control" id="nr-addr" name="institute_address" maxlength="255">
          </div>
        </div>

        <div class="nr-section mt-4">Photo &amp; ID <span class="fw-normal text-lowercase" style="letter-spacing:0">(optional)</span></div>
        <div class="row g-3 align-items-center">
          <div class="col-md-4 d-flex align-items-center gap-3">
            <input type="file" id="nr-image" name="image" accept="image/jpeg,image/png" class="d-none">
            <div class="nr-photo" id="nr-photo" role="button" tabindex="0" aria-label="Choose a photo"><i class="bi bi-camera"></i></div>
            <div class="small text-muted">Click to add a photo<br><a href="#" id="nr-photo-remove" class="d-none">Remove</a></div>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="nr-idtype">ID type</label>
            <select class="form-select" id="nr-idtype" name="identification_type"><option value="">None</option>@foreach($idTypes as $t)<option>{{ $t }}</option>@endforeach</select>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="nr-idno">ID number</label>
            <input class="form-control" id="nr-idno" name="identification_no" maxlength="50">
          </div>
          <div class="col-12"><div class="nr-error" data-error="image"></div></div>
        </div>

        <div class="nr-options mt-4">
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" id="nr-send" name="send_email" value="1" checked>
            <label class="form-check-label" for="nr-send"><b>Email the confirmation</b> (registration number, card and invitation)</label>
          </div>
          <div class="form-check form-switch m-0">
            <input class="form-check-input" type="checkbox" id="nr-checkin" name="checked_in" value="1">
            <label class="form-check-label" for="nr-checkin"><b>Mark as checked in</b> (they are here now)</label>
          </div>
        </div>
      </div>
      <div class="card-footer d-flex justify-content-between align-items-center">
        <button type="reset" class="btn btn-outline-secondary" id="nr-reset">Clear</button>
        <button type="submit" class="btn btn-primary btn-lg" id="nr-submit"><i class="bi bi-person-plus"></i> Register attendee</button>
      </div>
    </fieldset>
    </form>
  </div>

  <div class="col-xl-4">
    <div class="nr-sticky">
      <div class="card">
        <div class="card-header"><i class="bi bi-clock-history"></i> Latest for {{ $venue }}</div>
        <div class="card-body pt-2 pb-2">
          <ul class="list-unstyled nr-recent mb-0" id="nr-recent">
            @forelse($recent as $r)
              <li><span>{{ trim($r->title . ' ' . $r->firstname . ' ' . $r->lastname) }}<small class="d-block text-muted">{{ $r->country }}</small></span><span class="nr-code">{{ $r->qr_code }}</span></li>
            @empty
              <li class="text-muted" data-empty>No registrations yet.</li>
            @endforelse
          </ul>
        </div>
      </div>
      <div class="card mb-0">
        <div class="card-body small text-muted">
          <p class="mb-2"><i class="bi bi-info-circle text-primary"></i> The same checks as the public form apply (one registration per email, values from Form Options).</p>
          <p class="mb-0"><i class="bi bi-unlock text-primary"></i> This form works even when public registration is closed.</p>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  'use strict';
  var form = document.getElementById('nrForm');
  var professions = @json($professions);
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var photoFile = null;
  var $ = function (id) { return document.getElementById(id); };
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

  // Country -> dial code
  $('nr-country').addEventListener('change', function () { var o = this.selectedOptions[0]; $('nr-dial').textContent = (o && o.dataset.dial) || '+ ··'; });
  $('nr-phone').addEventListener('input', function () { this.value = this.value.replace(/[^0-9\s\-]/g, ''); });

  /* date of birth: three dropdowns → hidden "YYYY-MM-DD"; impossible days (31 Feb) are greyed out */
  var MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  function dob() {
    var d = +$('nr-dob-d').value, m = +$('nr-dob-m').value, y = +$('nr-dob-y').value, hint = $('nr-dob-hint');
    var days = new Date(y || 2000, m || 1, 0).getDate();   // days in that month (29 Feb in leap years)
    Array.prototype.forEach.call($('nr-dob-d').options, function (o) { if (o.value) o.disabled = +o.value > days; });
    if (d > days) { $('nr-dob-d').value = ''; d = 0; }
    $('nr-dob-box').classList.remove('is-invalid');
    if (d && m && y) {
      var iso = y + '-' + ('0' + m).slice(-2) + '-' + ('0' + d).slice(-2), today = new Date(), age = today.getFullYear() - y - ((today.getMonth() + 1 < m || (today.getMonth() + 1 === m && today.getDate() < d)) ? 1 : 0);
      $('nr-dob').value = iso;
      hint.className = 'nr-dob-hint ok'; hint.innerHTML = '<i class="bi bi-check-circle-fill"></i> ' + d + ' ' + MONTHS[m - 1] + ' ' + y + ' · age ' + age;
    } else {
      $('nr-dob').value = '';
      hint.className = 'nr-dob-hint'; hint.innerHTML = '<i class="bi bi-calendar3"></i> ' + (d || m || y ? 'Choose ' + [!d && 'day', !m && 'month', !y && 'year'].filter(Boolean).join(', ') : 'Day · Month · Year');
    }
  }
  ['nr-dob-d', 'nr-dob-m', 'nr-dob-y'].forEach(function (id) { $(id).addEventListener('change', dob); });
  form.addEventListener('reset', function () { setTimeout(dob, 0); });

  // Profession -> specialities
  $('nr-prof').addEventListener('change', function () {
    var p = professions.find(function (x) { return x.name === $('nr-prof').value; });
    var spec = $('nr-spec'), list = p ? p.specialities : [];
    spec.innerHTML = '<option value="">' + (list.length ? 'Choose…' : 'Not applicable') + '</option>' + list.map(function (s) { return '<option>' + esc(s) + '</option>'; }).join('');
    spec.disabled = !list.length;
  });

  // Photo
  var photo = $('nr-photo'), input = $('nr-image');
  photo.addEventListener('click', function () { input.click(); });
  photo.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
  input.addEventListener('change', function () {
    photoFile = input.files[0] || null;
    if (!photoFile) return;
    photo.style.backgroundImage = 'url(' + URL.createObjectURL(photoFile) + ')';
    photo.classList.add('has-photo'); $('nr-photo-remove').classList.remove('d-none');
  });
  $('nr-photo-remove').addEventListener('click', function (e) { e.preventDefault(); clearPhoto(); });
  function clearPhoto() { photoFile = null; input.value = ''; photo.style.backgroundImage = ''; photo.classList.remove('has-photo'); $('nr-photo-remove').classList.add('d-none'); }
  $('nr-reset').addEventListener('click', function () { setTimeout(function () { clearPhoto(); clearErrors(); $('nr-dial').textContent = '+ ··'; $('nr-prof').dispatchEvent(new Event('change')); }, 0); });

  function clearErrors() {
    form.querySelectorAll('.nr-error').forEach(function (e) { e.textContent = ''; });
    form.querySelectorAll('.is-invalid').forEach(function (e) { e.classList.remove('is-invalid'); });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    clearErrors();
    var data = new FormData(form);
    data.set('countryCode', $('nr-dial').textContent.trim());
    data.set('send_email', $('nr-send').checked ? '1' : '0');
    data.set('checked_in', $('nr-checkin').checked ? '1' : '0');
    data.delete('image'); if (photoFile) data.append('image', photoFile);
    var btn = $('nr-submit'); btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Registering…';
    fetch(@json(route('registrations.store')), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: data })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, status: r.status, d: d }; }); })
      .then(function (res) {
        btn.disabled = false; btn.innerHTML = '<i class="bi bi-person-plus"></i> Register attendee';
        if (!res.ok) {
          var errors = res.d.errors || {}, first = null;
          Object.keys(errors).forEach(function (k) {
            var box = form.querySelector('[data-error="' + k + '"]'); if (box) box.textContent = errors[k][0];
            var el = form.querySelector('[name="' + k + '"]'); if (el && el.type !== 'radio') { el.classList.add('is-invalid'); first = first || el; }
          });
          if (first) { first.scrollIntoView({ behavior: 'smooth', block: 'center' }); first.focus({ preventScroll: true }); }
          Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: Object.keys(errors).length ? 'Please fix the highlighted fields' : (res.d.message || 'Not saved'), showConfirmButton: false, timer: 2600 });
          return;
        }
        var d = res.d;
        // Add to the "latest" list
        var list = $('nr-recent'), empty = list.querySelector('[data-empty]'); if (empty) empty.remove();
        var li = document.createElement('li'); li.innerHTML = '<span>' + esc(d.name) + '<small class="d-block text-muted">' + esc($('nr-country').value) + '</small></span><span class="nr-code">' + esc(d.code) + '</span>';
        list.prepend(li); if (list.children.length > 5) list.lastElementChild.remove();
        Swal.fire({
          icon: 'success', title: 'Registered!',
          html: '<p class="mb-2"><b>' + esc(d.name) + '</b></p>' +
                '<div style="font-size:34px;font-weight:800;letter-spacing:6px;margin:6px 0">' + esc(d.code) + '</div>' +
                '<p class="small mb-0">' + (d.email_sent ? '<i class="bi bi-envelope-check text-success"></i> Confirmation emailed to ' + esc(d.email) : '<i class="bi bi-envelope-slash"></i> No email sent') +
                (d.checked_in ? '<br><i class="bi bi-person-check text-success"></i> Marked as checked in' : '') + '</p>',
          showDenyButton: !!(d.admin_card_url || d.card_url), denyButtonText: '<i class="bi bi-download"></i> Card PDF',
          confirmButtonText: 'Register another',
          preDeny: function () { window.open(d.admin_card_url || d.card_url, '_blank'); return false; }
        }).then(function () { form.reset(); clearPhoto(); $('nr-dial').textContent = '+ ··'; $('nr-prof').dispatchEvent(new Event('change')); $('nr-first').focus(); });
      })
      .catch(function () { btn.disabled = false; btn.innerHTML = '<i class="bi bi-person-plus"></i> Register attendee'; Swal.fire('Error', 'Could not reach the server. Please try again.', 'error'); });
  });
})();
</script>
@endpush
