@extends('backend.layouts.master')
@section('title')
{{ $p->exists ? $p->name : 'Add participant' }}
@endsection
@push('styles')
<style>
  .pf-sec { font-size: 13px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #1861c5; margin: 22px 0 10px; display: flex; align-items: center; gap: 8px; }
  .pf-sec:first-child { margin-top: 0; }
  .pf-vis { display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px; font-weight: 700; padding: 2px 8px; border-radius: 999px; cursor: pointer; user-select: none; border: 1px solid; margin-left: 6px; }
  .pf-vis input { display: none; }
  .pf-vis, .pf-vis span, .pf-vis i { font-size: 11.5px !important; line-height: 1.4 !important; }
  .pf-vis.pub { color: #2f7a1b; border-color: #bfe3b3; background: #eef9ea; }
  .pf-vis.priv { color: #6b7489; border-color: rgba(0,0,0,.12); background: rgba(0,0,0,.03); }
  .pf-img { display: flex; align-items: center; gap: 12px; }
  .pf-img .pv { width: 84px; height: 84px; border-radius: 50%; object-fit: cover; background: #e9eff9; display: flex; align-items: center; justify-content: center; color: #1861c5; font-weight: 800; font-size: 1.5rem; flex: 0 0 84px; overflow: hidden; }
  .pf-img .pv img { width: 100%; height: 100%; object-fit: cover; }
  .pf-img.logo .pv { border-radius: 12px; width: 120px; flex-basis: 120px; background: #fff; border: 1px solid rgba(0,0,0,.1); }
  .pf-img.logo .pv img { object-fit: contain; padding: 6px; }
  .pf-side { position: sticky; top: 80px; }
  .pf-qr { width: 160px; height: 160px; background: #fff; border-radius: 12px; border: 1px solid rgba(0,0,0,.08); padding: 6px; }
  .pf-url { font-family: monospace; font-size: 12.5px; word-break: break-all; background: rgba(24,97,197,.06); border-radius: 8px; padding: 8px 10px; }
  .pf-stat { text-align: center; border: 1px solid rgba(0,0,0,.08); border-radius: 10px; padding: 8px 4px; }
  .pf-stat b { display: block; font-size: 1.2rem; }
  .pf-stat span { font-size: 11px; color: var(--ad-muted, #6b7489); }
</style>
@endpush
@section('content')
@php
   $vis = fn ($k) => $p->isPublic($k);
   $toggle = fn ($k) => '<label class="pf-vis ' . ($vis($k) ? 'pub' : 'priv') . '" title="Shown on the public profile?"><input type="checkbox" data-vis="' . $k . '" ' . ($vis($k) ? 'checked' : '') . '><i class="bi ' . ($vis($k) ? 'bi-eye' : 'bi-eye-slash') . '"></i><span>' . ($vis($k) ? 'Public' : 'Private') . '</span></label>';
@endphp
<div class="pagetitle d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div>
    <h1>{{ $p->exists ? $p->name : 'Add participant' }} @if($p->exists)<span class="badge bg-light text-primary border fs-6 align-middle">{{ $p->code }}</span>@endif</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li><li class="breadcrumb-item"><a href="{{ route('participants.index') }}">Participants</a></li><li class="breadcrumb-item active">{{ $p->exists ? $p->code : 'New' }}</li></ol></nav>
  </div>
  <div class="d-flex gap-2">
    <a href="{{ route('participants.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> All participants</a>
    @if($p->exists && $p->status === 'active' && !$p->trashed())<a href="{{ $p->url() }}" target="_blank" class="btn btn-outline-primary"><i class="bi bi-box-arrow-up-right"></i> View profile</a>@endif
  </div>
</div>
@if($p->exists && $p->trashed())<div class="alert alert-danger">This participant is in the trash: the profile is not available. Restore it from the Participants list (Trash).</div>@endif

<section class="section">
  @include('backend.pages.participants.link-warning')
  <div class="row g-4">
    <div class="col-xl-8">
      <form class="card" id="pf-form" novalidate>
        <div class="card-body pt-3">
          <div class="pf-sec"><i class="bi bi-person-vcard"></i> Identity</div>
          <div class="row g-3">
            <div class="col-sm-2"><label class="form-label small mb-1">Title</label><input class="form-control" name="title" value="{{ $p->title }}" maxlength="20" placeholder="Dr"></div>
            <div class="col-sm-5"><label class="form-label small mb-1">First name <span class="text-danger">*</span></label><input class="form-control" name="first_name" value="{{ $p->first_name }}" maxlength="150" required></div>
            <div class="col-sm-5"><label class="form-label small mb-1">Last name</label><input class="form-control" name="last_name" value="{{ $p->last_name }}" maxlength="150"></div>
            <div class="col-sm-6"><label class="form-label small mb-1">Designation / title {!! $toggle('designation') !!}</label><input class="form-control" name="designation" value="{{ $p->designation }}" maxlength="150" placeholder="Consultant Oncologist"></div>
            <div class="col-sm-6"><label class="form-label small mb-1">Organization {!! $toggle('organization') !!}</label><input class="form-control" name="organization" value="{{ $p->organization }}" maxlength="190"></div>
            <div class="col-sm-4"><label class="form-label small mb-1">Department / speciality {!! $toggle('department') !!}</label><input class="form-control" name="department" value="{{ $p->department }}" maxlength="150"></div>
            <div class="col-sm-4"><label class="form-label small mb-1">Category {!! $toggle('category') !!}</label><input class="form-control" name="category" value="{{ $p->category }}" maxlength="100" placeholder="Delegate, Speaker…"></div>
            <div class="col-sm-4"><label class="form-label small mb-1">Country {!! $toggle('country') !!}</label><input class="form-control" name="country" value="{{ $p->country }}" maxlength="100"></div>
          </div>

          <div class="pf-sec"><i class="bi bi-image"></i> Photo &amp; logo</div>
          <div class="row g-3">
            <div class="col-md-6">
              <div class="pf-img" data-img="photo">
                <input type="hidden" name="photo" value="{{ $p->photo }}">
                <div class="pv">@if($p->photo && is_file(base_path($p->photo)))<img src="{{ asset($p->photo) }}" alt="">@else{{ $p->initials ?: '?' }}@endif</div>
                <div><div class="small fw-semibold mb-1">Profile photo <span class="text-muted fw-normal">(always public)</span></div>
                  <label class="btn btn-sm btn-outline-primary mb-0"><i class="bi bi-upload"></i> Upload<input type="file" accept=".png,.jpg,.jpeg,.webp" hidden></label>
                  <button type="button" class="btn btn-sm btn-outline-secondary" data-clear>Remove</button></div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="pf-img logo" data-img="logo">
                <input type="hidden" name="logo" value="{{ $p->logo }}">
                <div class="pv">@if($p->logo && is_file(base_path($p->logo)))<img src="{{ asset($p->logo) }}" alt="">@else<i class="bi bi-building text-muted"></i>@endif</div>
                <div><div class="small fw-semibold mb-1">Organization logo {!! $toggle('logo') !!}</div>
                  <label class="btn btn-sm btn-outline-primary mb-0"><i class="bi bi-upload"></i> Upload<input type="file" accept=".png,.jpg,.jpeg,.webp" hidden></label>
                  <button type="button" class="btn btn-sm btn-outline-secondary" data-clear>Remove</button></div>
              </div>
            </div>
          </div>

          <div class="pf-sec"><i class="bi bi-telephone"></i> Contact <span class="text-muted fw-normal text-lowercase small">— choose what is public</span></div>
          <div class="row g-3">
            <div class="col-sm-6"><label class="form-label small mb-1">Email {!! $toggle('email') !!}</label><input type="email" class="form-control" name="email" value="{{ $p->email }}" maxlength="190"><div class="form-text">Also links this person's event registrations.</div></div>
            <div class="col-sm-6"><label class="form-label small mb-1">Mobile {!! $toggle('mobile') !!}</label><input class="form-control" name="mobile" value="{{ $p->mobile }}" maxlength="40" placeholder="+880 1712 345678"></div>
            <div class="col-sm-6"><label class="form-label small mb-1">Telephone {!! $toggle('phone') !!}</label><input class="form-control" name="phone" value="{{ $p->phone }}" maxlength="40"></div>
            <div class="col-sm-6"><label class="form-label small mb-1">WhatsApp {!! $toggle('whatsapp') !!}</label><input class="form-control" name="whatsapp" value="{{ $p->whatsapp }}" maxlength="40" placeholder="With country code, e.g. +8801712345678"></div>
            <div class="col-sm-6"><label class="form-label small mb-1">Office address {!! $toggle('address') !!}</label><input class="form-control" name="address" value="{{ $p->address }}" maxlength="255"></div>
            <div class="col-sm-6"><label class="form-label small mb-1">Google Maps link {!! $toggle('map_url') !!}</label><input type="url" class="form-control" name="map_url" value="{{ $p->map_url }}" maxlength="500" placeholder="Empty = map of the address"></div>
            <div class="col-sm-6"><label class="form-label small mb-1">Website {!! $toggle('website') !!}</label><input type="url" class="form-control" name="website" value="{{ $p->website }}" maxlength="255" placeholder="https://"></div>
          </div>

          <div class="pf-sec"><i class="bi bi-share"></i> Social media &amp; links {!! $toggle('socials') !!}</div>
          @include('partials.social-links-editor', ['links' => $p->exists ? $p->socialLinks() : [], 'id' => 'pf-socials'])

          <div class="pf-sec"><i class="bi bi-file-person"></i> About</div>
          <div class="mb-3"><label class="form-label small mb-1">Biography {!! $toggle('bio') !!}</label><textarea class="form-control" name="bio" rows="4" maxlength="3000">{{ $p->bio }}</textarea></div>
          <div class="mb-3"><label class="form-label small mb-1">Areas of expertise {!! $toggle('expertise') !!} <span class="text-muted">(comma or one per line)</span></label><textarea class="form-control" name="expertise" rows="2" maxlength="1000" placeholder="Breast cancer, Immunotherapy, Clinical trials">{{ $p->expertise }}</textarea></div>
          <div class="mb-1"><label class="form-label small mb-1">Events attended {!! $toggle('events') !!}</label><div class="form-text mt-0">Shows the BICS events this person registered for.</div></div>

          <div class="pf-sec"><i class="bi bi-ui-checks-grid"></i> Extra fields</div>
          <div id="pf-custom"></div>
          <button type="button" class="btn btn-sm btn-outline-primary" id="pf-add-custom"><i class="bi bi-plus-lg"></i> Add field</button>

          <div class="pf-sec"><i class="bi bi-gear"></i> Settings</div>
          <div class="mb-3">
            <label class="form-label small mb-1" for="pf-slug">Custom link name <span class="text-muted">(optional, for sharing)</span></label>
            <div class="input-group"><span class="input-group-text small">{{ \App\Support\PublicLinks::base() }}/p/</span><input class="form-control" id="pf-slug" name="slug" value="{{ $p->slug }}" maxlength="60" placeholder="dr-nadia-karim"></div>
            <div class="form-text">The permanent link {{ $p->exists ? '/p/' . $p->code : '/p/MBR-…' }} always keeps working. QR codes and NFC cards use the permanent link, so changing this name never breaks a printed card.</div>
          </div>
          <div class="row g-3 align-items-end">
            <div class="col-sm-5"><label class="form-label small mb-1">Profile status</label>
              <select class="form-select" name="status"><option value="active" @selected($p->status !== 'suspended')>Active — profile is public</option><option value="suspended" @selected($p->status === 'suspended')>Suspended — profile hidden</option></select></div>
            <div class="col-sm-7"><div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="pf-edit" name="editing_enabled" @checked($p->editing_enabled ?? true)><label class="form-check-label" for="pf-edit">Participant may edit their own profile <span class="text-muted">(at {{ route('my.login') }})</span></label></div></div>
          </div>
          <div class="mt-3">
            <div class="small fw-semibold mb-1">Also lock for this participant <span class="text-muted fw-normal">({{ $access['edit'] ? 'fields ticked in Dashboard settings are locked for everyone' : 'editing is switched off for everyone in Dashboard settings' }})</span></div>
            <div class="row g-1">
              @foreach($editableList as $k => $label)
              @php $global = in_array($k, $access['locked'], true); @endphp
              <div class="col-sm-4 col-6"><div class="form-check"><input class="form-check-input" type="checkbox" data-lock="{{ $k }}" id="lk-{{ $k }}" @checked($global || in_array($k, $p->locked_fields ?? [], true)) @disabled($global)><label class="form-check-label small {{ $global ? 'text-muted' : '' }}" for="lk-{{ $k }}">{{ $label }}</label></div></div>
              @endforeach
            </div>
          </div>
        </div>
        <div class="card-footer d-flex gap-2">
          <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> {{ $p->exists ? 'Save changes' : 'Create participant' }}</button>
          <a href="{{ route('participants.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>

    <div class="col-xl-4">
      <div class="pf-side">
        @if($p->exists)
        <div class="card mb-3"><div class="card-body pt-3">
          <div class="fw-bold mb-2"><i class="bi bi-qr-code"></i> Profile link &amp; QR</div>
          <div class="small text-muted mb-1">Permanent link (QR / NFC)</div>
          <div class="pf-url mb-2" id="pf-url">{{ $p->url() }}</div>
          @if($p->slug)<div class="small text-muted mb-1">Custom link</div><div class="pf-url mb-2">{{ $p->shareUrl() }}</div>@endif
          <div class="d-flex gap-2 mb-3"><button class="btn btn-sm btn-outline-primary" type="button" id="pf-copy"><i class="bi bi-clipboard"></i> Copy</button>
            @if($p->status === 'active' && !$p->trashed())<a class="btn btn-sm btn-outline-secondary" href="{{ $p->url() }}" target="_blank"><i class="bi bi-box-arrow-up-right"></i> Open</a>@endif</div>
          <div class="d-flex gap-3 align-items-center">
            <img class="pf-qr" src="{{ route('profile.qr', [$p->code, 'svg']) }}" alt="QR code" onerror="this.style.opacity=.25">
            <div class="d-grid gap-1 small">
              <a class="btn btn-sm btn-outline-secondary" href="{{ route('profile.qr', [$p->code, 'png']) }}?download=1&size=1200"><i class="bi bi-filetype-png"></i> PNG</a>
              <a class="btn btn-sm btn-outline-secondary" href="{{ route('profile.qr', [$p->code, 'svg']) }}?download=1"><i class="bi bi-filetype-svg"></i> SVG</a>
              <a class="btn btn-sm btn-outline-secondary" href="{{ route('profile.qr', [$p->code, 'eps']) }}?download=1"><i class="bi bi-vector-pen"></i> EPS</a>
              <a class="btn btn-sm btn-outline-secondary" href="{{ route('profile.vcard', $p->code) }}"><i class="bi bi-person-vcard"></i> vCard</a>
            </div>
          </div>
          <div class="small text-muted mt-2"><i class="bi bi-shield-check"></i> The link and QR code never change when the profile is edited.@if($p->status !== 'active') QR downloads work while the profile is active.@endif</div>
        </div></div>

        <div class="card mb-3"><div class="card-body pt-3">
          <div class="fw-bold mb-2"><i class="bi bi-graph-up"></i> Profile activity</div>
          <div class="row g-2">
            @foreach(['view_link' => 'Link visits', 'view_qr' => 'QR scans', 'view_nfc' => 'NFC taps', 'vcard' => 'Contacts saved*', 'click_share' => 'Shares started*', 'qr_download' => 'QR downloads'] as $k => $l)
            <div class="col-4"><div class="pf-stat"><b>{{ $stats[$k] ?? 0 }}</b><span>{{ $l }}</span></div></div>
            @endforeach
          </div>
          <div class="small text-muted mt-2">* button presses: the platform cannot know if the visitor finished saving or sharing.</div>
        </div></div>

        @if($canNfc)
        <div class="card mb-3"><div class="card-body pt-3">
          <div class="fw-bold mb-2"><i class="bi bi-credit-card-2-front"></i> NFC card</div>
          @php $inUse = $cards->first(fn ($c) => in_array($c->status, \App\Models\NfcCard::IN_USE, true)); @endphp
          @if($inUse)
            <div class="d-flex justify-content-between align-items-center"><a class="fw-bold" href="{{ route('nfc.show', $inUse->id) }}">{{ $inUse->card_no }}</a>
              <span><span class="badge bg-{{ \App\Models\NfcCard::STATUS[$inUse->status][1] }}">{{ \App\Models\NfcCard::STATUS[$inUse->status][0] }}</span> <span class="badge bg-{{ \App\Models\NfcCard::ENCODE[$inUse->encode_status][1] }}">{{ \App\Models\NfcCard::ENCODE[$inUse->encode_status][0] }}</span></span></div>
          @elseif($p->status === 'active' && !$p->trashed())
            <div class="small text-muted mb-2">No card in use.</div>
            <button type="button" class="btn btn-sm btn-primary" id="pf-give-card"><i class="bi bi-plus-lg"></i> Give a card</button>
          @else
            <div class="small text-muted">Activate the profile to give a card.</div>
          @endif
          @foreach($cards->filter(fn ($c) => !in_array($c->status, \App\Models\NfcCard::IN_USE, true)) as $old)
            <div class="small text-muted mt-1"><a href="{{ route('nfc.show', $old->id) }}">{{ $old->card_no }}</a> · {{ \App\Models\NfcCard::STATUS[$old->status][0] }}</div>
          @endforeach
        </div></div>
        @endif

        <div class="card mb-3"><div class="card-body pt-3">
          <div class="fw-bold mb-2"><i class="bi bi-person-gear"></i> Participant dashboard</div>
          <div class="small mb-2">{!! $p->last_login_at ? 'Last signed in <b>' . e($p->last_login_at->diffForHumans()) . '</b>' : '<span class="text-muted">Has not signed in yet.</span>' !!}</div>
          <div class="small mb-2">Password: {!! $p->password ? '<b>set</b>' . ($p->password_changed_at ? ', changed ' . e($p->password_changed_at->diffForHumans()) : '') : '<span class="text-muted">not set (signs in with emailed codes)</span>' !!}</div>
          @if($access['portal'] && $p->status === 'active' && !$p->trashed() && $p->email)
            <button type="button" class="btn btn-sm btn-outline-primary" id="pf-send-login"><i class="bi bi-envelope"></i> Email a sign-in link</button>
            <div class="small text-muted mt-1">Sends a one-time link and code to {{ $p->email }}.</div>
          @elseif(!$access['portal'])
            <div class="small text-muted">Participant sign-in is switched off (Participants › Dashboard settings).</div>
          @endif
          @if($changes->count())
          <div class="small fw-semibold mt-3 mb-1">Recent changes</div>
          @foreach($changes as $c)
            @php $fieldsChanged = array_keys(json_decode($c->changes, true) ?: []); @endphp
            <div class="small border-top py-1"><span class="badge {{ $c->source === 'participant' ? 'bg-info-subtle text-info-emphasis' : 'bg-light text-dark' }}">{{ $c->source === 'participant' ? 'Participant' : ($c->user ?: 'Admin') }}</span>
              <span class="text-muted">{{ \Carbon\Carbon::parse($c->created_at)->format('j M, H:i') }}</span><br>{{ implode(', ', array_map(fn ($f) => \App\Support\ParticipantAccess::EDITABLE[$f] ?? ucfirst(str_replace('_', ' ', $f)), $fieldsChanged)) }}</div>
          @endforeach
          @endif
        </div></div>

        <div class="card"><div class="card-body pt-3">
          <div class="fw-bold mb-2"><i class="bi bi-calendar-event"></i> Event registrations</div>
          @forelse($registrations as $r)
            <div class="small border-bottom py-2">
              <div class="d-flex justify-content-between"><span><b>{{ $r->event_short ?: $r->venue }}</b> · #{{ $r->qr_code }}</span><span class="{{ $r->verify_status === 'Yes' ? 'text-success' : 'text-muted' }}">{{ $r->verify_status === 'Yes' ? 'Checked in' : 'Registered' }}</span></div>
              @if($r->event_id)
              <div class="d-flex justify-content-between align-items-center mt-1 gap-2">
                <span class="text-muted"><i class="bi bi-person-badge"></i>
                  @if($r->idcard_assigned_at) ID card · <span class="text-primary fw-semibold">on dashboard</span> @else ID card · not assigned @endif</span>
                @if($canIdCard)
                  <button type="button" class="btn btn-sm py-0 {{ $r->idcard_assigned_at ? 'btn-outline-danger' : 'btn-outline-primary' }}" data-idcard="{{ $r->id }}" data-event="{{ $r->event_id }}" data-on="{{ $r->idcard_assigned_at ? 0 : 1 }}">
                    {{ $r->idcard_assigned_at ? 'Remove' : 'Assign ID card' }}</button>
                @endif
              </div>
              <div class="d-flex justify-content-between align-items-center mt-1 gap-2">
                <span class="text-muted"><i class="bi bi-award"></i>
                  @if($r->cert && $r->cert->assigned_at) Certificate {{ $r->cert->number }} · <span class="text-primary fw-semibold">on dashboard</span>
                  @elseif($r->cert) Certificate {{ $r->cert->number }} · not assigned
                  @else No certificate yet @endif</span>
                @if($canCert)
                  <button type="button" class="btn btn-sm py-0 {{ $r->cert && $r->cert->assigned_at ? 'btn-outline-danger' : 'btn-outline-primary' }}" data-cert="{{ $r->id }}" data-event="{{ $r->event_id }}" data-on="{{ $r->cert && $r->cert->assigned_at ? 0 : 1 }}">
                    {{ $r->cert && $r->cert->assigned_at ? 'Remove' : 'Assign certificate' }}</button>
                @endif
              </div>
              @endif
            </div>
          @empty
            <div class="small text-muted">No event registrations yet.</div>
          @endforelse
        </div></div>
        @else
        <div class="card"><div class="card-body pt-3 small text-muted"><i class="bi bi-info-circle"></i> When you create the participant, BICS gives them a permanent ID (e.g. MBR-000302), a public profile link and a QR code.</div></div>
        @endif
      </div>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
  'use strict';
  var form = document.getElementById('pf-form');
  var saveUrl = @json($p->exists ? route('participants.update', $p->id) : route('participants.store'));
  var method = @json($p->exists ? 'PUT' : 'POST');
  var uploadUrl = @json(route('participants.upload'));
  var ASSET = @json(rtrim(asset('/'), '/'));
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var custom = @json($p->custom_fields ?? []);
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

  // Public / private switches
  form.querySelectorAll('.pf-vis input').forEach(function (cb) {
    cb.addEventListener('change', function () {
      var l = cb.parentElement; l.classList.toggle('pub', cb.checked); l.classList.toggle('priv', !cb.checked);
      l.querySelector('i').className = 'bi ' + (cb.checked ? 'bi-eye' : 'bi-eye-slash'); l.querySelector('span').textContent = cb.checked ? 'Public' : 'Private';
    });
  });

  // Photo / logo
  form.querySelectorAll('[data-img]').forEach(function (box) {
    var hidden = box.querySelector('input[type=hidden]'), pv = box.querySelector('.pv');
    box.querySelector('input[type=file]').addEventListener('change', function () {
      var f = this.files[0]; if (!f) return; var input = this;
      var fd = new FormData(); fd.append('image', f);
      fetch(uploadUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: fd })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
        .then(function (res) {
          input.value = '';
          if (!res.ok) { var e = res.d.errors || {}; var k = Object.keys(e)[0]; return Swal.fire('Not uploaded', k ? e[k][0] : (res.d.message || 'Please try again.'), 'warning'); }
          hidden.value = res.d.path; pv.innerHTML = '<img src="' + esc(res.d.url) + '" alt="">';
        });
    });
    box.querySelector('[data-clear]').addEventListener('click', function () { hidden.value = ''; pv.innerHTML = box.dataset.img === 'photo' ? '?' : '<i class="bi bi-building text-muted"></i>'; });
  });

  // Extra fields
  function renderCustom() {
    var wrap = document.getElementById('pf-custom');
    wrap.innerHTML = custom.map(function (f, i) {
      return '<div class="row g-2 mb-2 align-items-center" data-i="' + i + '"><div class="col-4"><input class="form-control form-control-sm" data-k="label" value="' + esc(f.label) + '" placeholder="Label (e.g. Licence no.)" maxlength="60"></div>' +
        '<div class="col-5"><input class="form-control form-control-sm" data-k="value" value="' + esc(f.value) + '" placeholder="Value" maxlength="255"></div>' +
        '<div class="col-2"><div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" data-k="public" ' + (f.public ? 'checked' : '') + '><label class="form-check-label small">Public</label></div></div>' +
        '<div class="col-1 text-end"><button type="button" class="btn btn-sm btn-link text-danger p-0" data-del><i class="bi bi-x-circle"></i></button></div></div>';
    }).join('') || '<div class="small text-muted mb-2">No extra fields. Use them for anything else (licence number, booth, hotel…).</div>';
    wrap.querySelectorAll('[data-i]').forEach(function (row) {
      var i = +row.dataset.i;
      row.querySelectorAll('[data-k]').forEach(function (inp) { inp.addEventListener('input', function () { custom[i][inp.dataset.k] = inp.type === 'checkbox' ? inp.checked : inp.value; }); inp.addEventListener('change', function () { if (inp.type === 'checkbox') custom[i].public = inp.checked; }); });
      row.querySelector('[data-del]').addEventListener('click', function () { custom.splice(i, 1); renderCustom(); });
    });
    document.getElementById('pf-add-custom').disabled = custom.length >= 15;
  }
  document.getElementById('pf-add-custom').addEventListener('click', function () { custom.push({ label: '', value: '', public: true }); renderCustom(); });
  renderCustom();

  // Save
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
    form.querySelectorAll('.invalid-feedback').forEach(function (el) { el.remove(); });
    var f = form.elements, body = { socials: {}, visibility: {}, custom_fields: custom };
    ['title', 'first_name', 'last_name', 'designation', 'organization', 'department', 'category', 'country', 'photo', 'logo', 'email', 'mobile', 'phone', 'whatsapp', 'address', 'map_url', 'website', 'bio', 'expertise', 'status', 'slug'].forEach(function (k) { body[k] = f[k].value.trim() || null; });
    body.status = f.status.value; body.editing_enabled = f.editing_enabled.checked;
    body.locked_fields = Array.prototype.filter.call(form.querySelectorAll('[data-lock]'), function (c) { return c.checked && !c.disabled; }).map(function (c) { return c.dataset.lock; });
    body.socials = SocialEditor.collect(document.getElementById('pf-socials'));
    form.querySelectorAll('[data-vis]').forEach(function (cb) { body.visibility[cb.dataset.vis] = cb.checked; });
    var btn = form.querySelector('[type=submit]'); btn.disabled = true;
    fetch(saveUrl, { method: method, headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
      .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (res) {
        btn.disabled = false;
        if (res.ok) {
          if (res.d.redirect) { Swal.fire({ icon: 'success', title: 'Participant created', text: res.d.message, timer: 1800, showConfirmButton: false }).then(function () { location.href = res.d.redirect; }); return; }
          return Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: res.d.message, showConfirmButton: false, timer: 2500 });
        }
        var errors = res.d.errors || {};
        SocialEditor.errors(document.getElementById('pf-socials'), errors);
        Object.keys(errors).forEach(function (k) {
          var name = k.replace(/\.(\w+)$/, '[$1]').replace(/^socials\[(\w+)\]$/, 'socials[$1]');
          var input = form.querySelector('[name="' + name + '"]') || form.querySelector('[name="' + k + '"]');
          if (!input) return;
          input.classList.add('is-invalid');
          var fb = document.createElement('div'); fb.className = 'invalid-feedback'; fb.textContent = errors[k][0];
          (input.closest('.input-group') || input).insertAdjacentElement('afterend', fb);
        });
        var first = form.querySelector('.is-invalid'); if (first) first.focus();
        Swal.fire('Please check', Object.keys(errors).map(function (k) { return errors[k][0]; }).join('<br>') || res.d.message || 'Not saved.', 'warning');
      }).catch(function () { btn.disabled = false; Swal.fire('Error', 'Could not save. Please try again.', 'error'); });
  });

  // ID card of one registration: assign to / remove from the participant's dashboard
  document.querySelectorAll('[data-idcard]').forEach(function (b) { b.addEventListener('click', function () {
    var on = b.dataset.on === '1';
    Swal.fire({ icon: 'question', title: on ? 'Assign the ID card?' : 'Remove the ID card from the dashboard?', text: on ? 'It will show in this participant\'s dashboard under this event.' : 'It no longer shows on the dashboard.',
      showCancelButton: true, confirmButtonText: on ? 'Assign' : 'Remove', confirmButtonColor: on ? undefined : '#d33' }).then(function (r) {
      if (!r.isConfirmed) return;
      b.disabled = true;
      fetch(@json(route('idcards.assign')) + '?event=' + b.dataset.event, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ ids: [+b.dataset.idcard], assign: on }) })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
        .then(function (res) { if (!res.ok) { b.disabled = false; return Swal.fire('Not done', res.d.message || 'Please try again.', 'warning'); } Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: res.d.message, showConfirmButton: false, timer: 1800 }).then(function () { location.reload(); }); });
    });
  }); });

  // certificate of one registration: assign to / remove from the participant's dashboard
  document.querySelectorAll('[data-cert]').forEach(function (b) { b.addEventListener('click', function () {
    var on = b.dataset.on === '1';
    Swal.fire({ icon: 'question', title: on ? 'Assign the certificate?' : 'Remove from the dashboard?', text: on ? 'It will show in this participant\'s dashboard under this event. No email is sent.' : 'The certificate stays valid but no longer shows on the dashboard.',
      showCancelButton: true, confirmButtonText: on ? 'Assign' : 'Remove', confirmButtonColor: on ? undefined : '#d33' }).then(function (r) {
      if (!r.isConfirmed) return;
      b.disabled = true;
      fetch(@json(route('certificates.assign')) + '?event=' + b.dataset.event, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ ids: [+b.dataset.cert], assign: on }) })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
        .then(function (res) { if (!res.ok) { b.disabled = false; return Swal.fire('Not done', res.d.message || 'Please try again.', 'warning'); } Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: res.d.message, showConfirmButton: false, timer: 1800 }).then(function () { location.reload(); }); });
    });
  }); });

  var sendLogin = document.getElementById('pf-send-login');
  if (sendLogin) sendLogin.addEventListener('click', function () {
    sendLogin.disabled = true;
    fetch(@json($p->exists ? route('participants.send-login', $p->id) : ''), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } })
      .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (res) { sendLogin.disabled = false; res.ok ? Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: res.d.message, showConfirmButton: false, timer: 3000 }) : Swal.fire('Not sent', res.d.message || 'Please try again.', 'warning'); })
      .catch(function () { sendLogin.disabled = false; });
  });

  var give = document.getElementById('pf-give-card');
  if (give) give.addEventListener('click', function () {
    give.disabled = true;
    fetch(@json($p->exists ? route('nfc.for', $p->id) : ''), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } })
      .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (res) { if (res.ok) location.href = res.d.card.show; else { give.disabled = false; var e = res.d.errors || {}; var k = Object.keys(e)[0]; Swal.fire('Not done', k ? e[k][0] : (res.d.message || 'Please try again.'), 'warning'); } });
  });

  var copy = document.getElementById('pf-copy');
  if (copy) copy.addEventListener('click', function () { navigator.clipboard.writeText(document.getElementById('pf-url').textContent.trim()).then(function () { Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Link copied', showConfirmButton: false, timer: 1500 }); }); });
})();
</script>
@endpush
