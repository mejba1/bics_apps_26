@extends('my.layout')
@section('title', 'My profile')
@section('heading', 'My profile')
@section('sub', 'What people see when they scan your QR code or tap your card')
@section('actions')
  <a class="btn btn-soft d-none d-md-inline-flex align-items-center gap-1" href="{{ $p->url() }}" target="_blank" rel="noopener"><i class="bi bi-eye"></i> Preview public profile</a>
@endsection
@php
  $can = fn ($f) => in_array($f, $editable, true);
  $canVis = $can('visibility');
  $lock = '<span class="lk" title="Set by the organizers"><i class="bi bi-lock-fill"></i> locked</span>';
  // public / private switch for a profile field
  $vis = function ($f) use ($p, $canVis) {
      if (!array_key_exists($f, \App\Models\Participant::FIELDS)) return '';
      $on = $p->isPublic($f);
      return '<label class="vis' . ($on ? ' on' : '') . '" title="Who can see this on your public profile"><input type="checkbox" data-vis="' . $f . '"' . ($on ? ' checked' : '') . ($canVis ? '' : ' disabled') . '>'
          . '<i class="bi ' . ($on ? 'bi-eye' : 'bi-eye-slash') . '"></i><span>' . ($on ? 'Public' : 'Private') . '</span></label>';
  };
  $input = function ($f, $label, $type = 'text', $extra = '') use ($p, $can, $lock, $vis) {
      $v = e((string) $p->getAttribute($f));
      return '<div class="fld"><div class="lbl"><label for="f-' . $f . '">' . e($label) . '</label>' . (!$can($f) ? $lock : '') . $vis($f) . '</div>'
          . '<input class="form-control" id="f-' . $f . '" type="' . $type . '" data-f="' . $f . '" value="' . $v . '"' . ($can($f) ? '' : ' disabled') . ' ' . $extra . '></div>';
  };
  $photo = $p->photo && is_file(base_path($p->photo)) ? \App\Support\Thumb::url($p->photo, 480) : null;
  $logo = $p->logo && is_file(base_path($p->logo)) ? asset($p->logo) : null;
@endphp
@push('styles')
<style>
  .sec { font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); margin: 6px 0 10px; }
  .fld { margin-bottom: 14px; }
  .lbl { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; font-size: 14px; font-weight: 600; }
  .lbl label { margin: 0; }
  .lk { font-size: 11px; color: var(--muted); font-weight: 600; }
  .vis { margin-left: auto; display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 700; color: var(--muted); background: var(--soft); border-radius: 20px; padding: 2px 9px; cursor: pointer; user-select: none; }
  .vis input { display: none; }
  .vis.on { color: #1d6b39; background: #e8f5ec; }
  .vis:has(input:disabled) { cursor: default; opacity: .7; }
  .pic { display: flex; gap: 14px; align-items: center; }
  .pic .im { width: 92px; height: 92px; border-radius: 50%; background: #e9eff9; display: flex; align-items: center; justify-content: center; overflow: hidden; flex: none; color: var(--blue); font-weight: 800; font-size: 1.8rem; }
  .pic .im img { width: 100%; height: 100%; object-fit: cover; }
  .pic .im.logo { border-radius: 12px; background: var(--soft); } .pic .im.logo img { object-fit: contain; padding: 6px; }
  .savebar { position: sticky; bottom: 0; background: rgba(243,246,251,.95); backdrop-filter: blur(6px); padding: 12px 0; margin-top: 4px; display: flex; gap: 10px; align-items: center; z-index: 5; }
  .form-control:disabled { background: var(--soft); }
  @media (max-width: 991px) { .savebar { bottom: 64px; padding: 10px 0; } .savebar .btn-lg { font-size: 1rem; padding: .55rem 1rem; } }
</style>
@endpush
@section('content')
@if(!$canEdit)
  <div class="alert alert-secondary"><i class="bi bi-lock"></i> Profile editing is switched off. To change something, please contact the organizers.</div>
@else
  <div class="alert alert-info small py-2"><i class="bi bi-info-circle"></i> Choose <b>Public</b> or <b>Private</b> for each detail — private details are never shown on your profile or in your contact card. Your link and QR code stay the same whatever you change.</div>
@endif

<form id="my-form" novalidate>
  <div class="panel">
    <div class="sec">Pictures</div>
    <div class="row g-3">
      <div class="col-sm-6"><div class="pic">
        <div class="im" id="im-photo">@if($photo)<img src="{{ $photo }}" alt="">@else{{ $p->initials }}@endif</div>
        <div><div class="fw-semibold mb-1">Photo @unless($can('photo'))<span class="lk"><i class="bi bi-lock-fill"></i> locked</span>@endunless</div>
          @if($can('photo'))<div class="d-flex gap-2"><button type="button" class="btn btn-sm btn-outline-primary" data-up="photo"><i class="bi bi-upload"></i> Upload</button>
            <button type="button" class="btn btn-sm btn-outline-secondary {{ $photo ? '' : 'd-none' }}" data-rm="photo">Remove</button></div>
            <div class="small muted mt-1">A clear, recent head-and-shoulders photo. It is also printed on your card.</div>@endif</div>
      </div></div>
      <div class="col-sm-6"><div class="pic">
        <div class="im logo" id="im-logo">@if($logo)<img src="{{ $logo }}" alt="">@else<i class="bi bi-building muted"></i>@endif</div>
        <div><div class="lbl mb-1">Organization logo @unless($can('logo')){!! $lock !!}@endunless {!! $vis('logo') !!}</div>
          @if($can('logo'))<div class="d-flex gap-2"><button type="button" class="btn btn-sm btn-outline-primary" data-up="logo"><i class="bi bi-upload"></i> Upload</button>
            <button type="button" class="btn btn-sm btn-outline-secondary {{ $logo ? '' : 'd-none' }}" data-rm="logo">Remove</button></div>@endif</div>
      </div></div>
    </div>
    <input type="hidden" data-f="photo" value="{{ $p->photo }}"><input type="hidden" data-f="logo" value="{{ $p->logo }}">
    <input type="file" id="my-file" accept="image/png,image/jpeg,image/webp" class="d-none">
  </div>

  <div class="panel">
    <div class="sec">About you</div>
    <div class="row g-2">
      <div class="col-4 col-sm-2">{!! $input('title', 'Title', 'text', 'maxlength="20" placeholder="Dr"') !!}</div>
      <div class="col-8 col-sm-5">{!! $input('first_name', 'First name', 'text', 'maxlength="150" required') !!}</div>
      <div class="col-sm-5">{!! $input('last_name', 'Last name', 'text', 'maxlength="150"') !!}</div>
      <div class="col-sm-6">{!! $input('designation', 'Designation', 'text', 'maxlength="150" placeholder="Consultant Oncologist"') !!}</div>
      <div class="col-sm-6">{!! $input('organization', 'Organization', 'text', 'maxlength="190"') !!}</div>
      <div class="col-sm-6">{!! $input('department', 'Department / speciality', 'text', 'maxlength="150"') !!}</div>
      <div class="col-sm-3">{!! $input('category', 'Category', 'text', 'maxlength="100"') !!}</div>
      <div class="col-sm-3">{!! $input('country', 'Country', 'text', 'maxlength="100"') !!}</div>
      <div class="col-sm-6"><div class="fld"><div class="lbl"><label for="f-birth_date">Date of birth</label>@unless($can('birth_date')){!! $lock !!}@endunless<span class="lk ms-auto"><i class="bi bi-eye-slash"></i> always private</span></div>
        <input class="form-control" id="f-birth_date" type="date" data-f="birth_date" value="{{ $p->birth_date?->format('Y-m-d') }}" max="{{ now()->subYears(10)->format('Y-m-d') }}" min="1900-01-02" @disabled(!$can('birth_date'))>
        <div class="small muted mt-1">Only used to show your age on your dashboard. Never shown on your profile.</div></div></div>
    </div>
    @unless($can('first_name') && $can('last_name'))<div class="small muted">Your name is printed on your card and certificates, so only the organizers can change it.</div>@endunless
  </div>

  <div class="panel">
    <div class="sec">Contact</div>
    <div class="row g-2">
      <div class="col-sm-6"><div class="fld"><div class="lbl"><label>Email</label><span class="lk"><i class="bi bi-lock-fill"></i> sign-in address</span>{!! $vis('email') !!}</div><input class="form-control" value="{{ $p->email }}" disabled></div></div>
      <div class="col-sm-6">{!! $input('mobile', 'Mobile', 'tel', 'maxlength="40" placeholder="+880 1711 000000"') !!}</div>
      <div class="col-sm-6">{!! $input('whatsapp', 'WhatsApp', 'tel', 'maxlength="40" placeholder="With country code"') !!}</div>
      <div class="col-sm-6">{!! $input('phone', 'Telephone', 'tel', 'maxlength="40"') !!}</div>
      <div class="col-12">{!! $input('address', 'Office address', 'text', 'maxlength="255"') !!}</div>
      <div class="col-sm-6">{!! $input('map_url', 'Google Maps link', 'url', 'maxlength="500" placeholder="https://maps.app.goo.gl/…"') !!}</div>
      <div class="col-sm-6">{!! $input('website', 'Website', 'url', 'maxlength="255" placeholder="https://"') !!}</div>
    </div>
  </div>

  <div class="panel">
    <div class="sec d-flex align-items-center gap-2">Social media &amp; links @unless($can('socials')){!! $lock !!}@endunless {!! $vis('socials') !!}</div>
    <p class="small muted mb-2">Add as many as you like — LinkedIn, X, ResearchGate, ORCID, Google Scholar, your hospital or lab page… They appear on your profile in this order.</p>
    @include('partials.social-links-editor', ['links' => $p->socialLinks(), 'disabled' => !$can('socials'), 'id' => 'my-socials'])
  </div>

  <div class="panel">
    <div class="sec">Biography</div>
    <div class="fld"><div class="lbl"><label for="f-bio">About me</label>@unless($can('bio')){!! $lock !!}@endunless{!! $vis('bio') !!}</div>
      <textarea class="form-control" id="f-bio" data-f="bio" rows="5" maxlength="3000" @disabled(!$can('bio'))>{{ $p->bio }}</textarea></div>
    <div class="fld"><div class="lbl"><label for="f-expertise">Areas of expertise</label>@unless($can('expertise')){!! $lock !!}@endunless{!! $vis('expertise') !!}</div>
      <textarea class="form-control" id="f-expertise" data-f="expertise" rows="2" maxlength="1000" placeholder="Comma or one per line: Breast cancer, Immunotherapy" @disabled(!$can('expertise'))>{{ $p->expertise }}</textarea></div>
    <div class="fld mb-0"><div class="lbl"><span>Events I attended</span>{!! $vis('events') !!}</div></div>
  </div>

  <div class="panel">
    <div class="sec">My link</div>
    <div class="fld mb-1"><div class="lbl"><label for="f-slug">Custom link name <span class="muted fw-normal">(optional)</span></label>@unless($can('slug')){!! $lock !!}@endunless</div>
      <div class="input-group"><span class="input-group-text small">{{ \App\Support\PublicLinks::base() }}/p/</span><input class="form-control" id="f-slug" data-f="slug" value="{{ $p->slug }}" maxlength="60" placeholder="dr-nadia-karim" @disabled(!$can('slug'))></div></div>
    <div class="small muted">A short, easy-to-share link. Your permanent link <b>/p/{{ $p->code }}</b> (used by your QR code and NFC card) always keeps working.</div>
  </div>

  @if($canEdit)
  <div class="savebar"><button class="btn btn-primary btn-lg px-4" id="my-save"><i class="bi bi-check2-circle"></i> Save changes</button><a href="{{ route('my.home') }}" class="btn btn-link">Back</a><span class="small muted ms-auto d-none" id="my-dirty">Unsaved changes</span></div>
  @endif
</form>
@endsection

@push('scripts')
<script>
(function () {
  var form = document.getElementById('my-form'), csrf = document.querySelector('meta[name="csrf-token"]').content;
  var editable = @json($editable), dirty = false, upField = null;
  function mark() { dirty = true; var d = document.getElementById('my-dirty'); if (d) d.classList.remove('d-none'); }
  form.addEventListener('input', mark);

  // public / private switches
  form.querySelectorAll('.vis input').forEach(function (cb) {
    cb.addEventListener('change', function () {
      var l = cb.closest('.vis'); l.classList.toggle('on', cb.checked);
      l.querySelector('i').className = 'bi ' + (cb.checked ? 'bi-eye' : 'bi-eye-slash');
      l.querySelector('span').textContent = cb.checked ? 'Public' : 'Private'; mark();
    });
  });

  // pictures
  var file = document.getElementById('my-file');
  form.querySelectorAll('[data-up]').forEach(function (b) { b.addEventListener('click', function () { upField = b.dataset.up; file.value = ''; file.click(); }); });
  form.querySelectorAll('[data-rm]').forEach(function (b) { b.addEventListener('click', function () {
    var f = b.dataset.rm; form.querySelector('[data-f=' + f + ']').value = '';
    document.getElementById('im-' + f).innerHTML = f === 'photo' ? @json($p->initials) : '<i class="bi bi-building muted"></i>';
    b.classList.add('d-none'); mark();
  }); });
  file.addEventListener('change', function () {
    var f = file.files[0]; if (!f) return;
    if (f.size > 5 * 1024 * 1024) { myToast('The image must be smaller than 5 MB.', true); return; }
    var fd = new FormData(); fd.append('image', f); fd.append('field', upField);
    myToast('Uploading…');
    fetch(@json(route('my.upload')), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: fd })
      .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (res) {
        if (!res.ok) { var e = res.d.errors || {}; var k = Object.keys(e)[0]; myToast(k ? e[k][0] : (res.d.message || 'Not uploaded.'), true); return; }
        form.querySelector('[data-f=' + upField + ']').value = res.d.path;
        document.getElementById('im-' + upField).innerHTML = '<img src="' + res.d.url + '" alt="">';
        var rm = form.querySelector('[data-rm=' + upField + ']'); if (rm) rm.classList.remove('d-none');
        mark(); myToast('Picture ready — press Save changes');
      }).catch(function () { myToast('Not uploaded. Please try again.', true); });
  });

  // save
  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var btn = document.getElementById('my-save'); if (!btn) return;
    form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
    form.querySelectorAll('.invalid-feedback').forEach(function (el) { el.remove(); });
    var body = {};
    form.querySelectorAll('[data-f]').forEach(function (i) { if (editable.indexOf(i.dataset.f) >= 0) body[i.dataset.f] = i.value.trim() || null; });
    if (editable.indexOf('socials') >= 0) body.socials = SocialEditor.collect(document.getElementById('my-socials'));
    if (editable.indexOf('visibility') >= 0) { body.visibility = {}; form.querySelectorAll('[data-vis]').forEach(function (cb) { body.visibility[cb.dataset.vis] = cb.checked; }); }
    btn.disabled = true;
    fetch(@json(route('my.update')), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, status: r.status, d: d }; }); })
      .then(function (res) {
        btn.disabled = false;
        if (res.status === 401 || res.status === 419) { location.href = @json(route('my.login')); return; }
        if (res.ok) { dirty = false; var d = document.getElementById('my-dirty'); if (d) d.classList.add('d-none'); myToast(res.d.message); return; }
        var errors = res.d.errors || {};
        SocialEditor.errors(document.getElementById('my-socials'), errors);
        Object.keys(errors).forEach(function (k) {
          var m = k.match(/^socials\.(\w+)$/), input = m ? form.querySelector('[data-social="' + m[1] + '"]') : form.querySelector('[data-f="' + k + '"]');
          if (!input || input.type === 'hidden') return;
          input.classList.add('is-invalid');
          var fb = document.createElement('div'); fb.className = 'invalid-feedback'; fb.textContent = errors[k][0];
          (input.closest('.input-group') || input).insertAdjacentElement('afterend', fb);
          if (input.closest('.input-group')) fb.style.display = 'block';
        });
        var first = form.querySelector('.is-invalid'); if (first) first.focus();
        myToast(Object.keys(errors).length ? errors[Object.keys(errors)[0]][0] : (res.d.message || 'Not saved.'), true);
      }).catch(function () { btn.disabled = false; myToast('Not saved. Check your connection.', true); });
  });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
})();
</script>
@endpush
