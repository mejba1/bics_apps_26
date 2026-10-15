@extends('backend.layouts.master')
@section('title')
Raffle Setup
@endsection
@push('styles')
<style>
  .rs-kpi { border-radius: 14px; padding: 16px 18px; background: var(--bs-body-bg, #fff); border: 1px solid rgba(0,0,0,.08); height: 100%; }
  .rs-kpi .n { font-size: 1.8rem; font-weight: 800; line-height: 1; }
  .rs-kpi .l { color: var(--ad-muted, #6b7489); font-size: 13px; margin-top: 6px; }
  .rs-host { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 10px; border: 1px solid rgba(0,0,0,.08); margin-bottom: 8px; cursor: pointer; }
  .rs-host:has(input:checked) { border-color: #1861c5; background: rgba(24,97,197,.06); }
  .rs-host input { width: 18px; height: 18px; }
  .rs-host small { color: var(--ad-muted, #6b7489); }
  .rs-order { width: 34px; height: 34px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; background: rgba(24,97,197,.1); color: #1861c5; }
  .rs-won { color: #2f7a1b; font-weight: 600; }
  .rs-rule { display: flex; gap: 12px; padding: 12px 14px; border-radius: 12px; border: 1px solid rgba(0,0,0,.08); margin-bottom: 10px; cursor: pointer; }
  .rs-rule:has(input:checked) { border-color: #1861c5; background: rgba(24,97,197,.06); }
  .rs-orderbar { padding: 12px 14px; border-radius: 12px; background: rgba(24,97,197,.05); border: 1px solid rgba(24,97,197,.15); }
</style>
@endpush
@section('content')
<div class="pagetitle d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div>
    <h1>Raffle Setup @if($event)<span class="text-muted fw-normal fs-5">· {{ $event->event_short }}</span>@endif</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li><li class="breadcrumb-item active">Raffle Setup</li></ol></nav>
  </div>
  <div class="d-flex flex-wrap gap-2 align-items-center">
    @if($events->count())
    <form method="GET" action="{{ route('addPrize') }}" class="d-flex align-items-center gap-2">
      <label class="small fw-semibold text-nowrap" for="rs-event"><i class="bi bi-calendar-event"></i> Event</label>
      <select class="form-select" id="rs-event" name="event" onchange="this.form.submit()" style="min-width:220px">
        @foreach($events as $ev)
        <option value="{{ $ev->id }}" @selected($event && $event->id === $ev->id)>{{ $ev->event_short }}{{ $ev->venue_code !== $ev->event_short ? ' · ' . $ev->venue_code : '' }}{{ $ev->is_current ? ' (current)' : '' }}</option>
        @endforeach
      </select>
    </form>
    @endif
    <a href="{{ route('prize.winner', $event ? ['event' => $event->id] : []) }}" class="btn btn-outline-secondary"><i class="bi bi-trophy"></i> Winners</a>
    <a href="{{ route('raffle.stage') }}" target="_blank" class="btn btn-success {{ $data['isHost'] && $current && $event && $current->id === $event->id ? '' : 'disabled' }}" id="rs-open" @unless($data['isHost']) aria-disabled="true" title="Only assigned hosts can open the draw" @endunless><i class="bi bi-play-circle"></i> Open draw screen</a>
  </div>
</div>

@if(!$event)
<div class="alert alert-warning">There is no current event. Create one in <a href="{{ route('events.index') }}">Admin › Events</a> first.</div>
@else
@php $rsLocked = \App\Support\EventLock::isLocked($event); @endphp
<section class="section">
  @include('partials.event-lock', ['event' => $event])
  <fieldset @disabled($rsLocked) class="{{ $rsLocked ? 'rs-locked' : '' }}">
  @if($current && $current->id !== $event->id)
  <div class="alert alert-warning small py-2 mb-3"><i class="bi bi-exclamation-triangle"></i> You are setting up <b>{{ $event->event_short }}</b> ({{ $event->venue_code }}). The draw screen always runs for the <b>current event</b> ({{ $current->event_short }}). To draw this event, make it current in <a href="{{ route('events.index') }}">Admin › Events</a>.</div>
  @else
  <div class="alert alert-info small py-2 mb-3"><i class="bi bi-info-circle"></i> Setting up <b>{{ $event->event_name }}</b> ({{ $event->venue_code }}), the current event. The draw picks winners at random on the server; a person becomes a winner only when the host clicks <b>Confirm winner</b>.</div>
  @endif

  <div class="row g-3 mb-3">
    <div class="col-sm-4"><div class="rs-kpi"><div class="n" id="k-prizes">0</div><div class="l">Prizes awarded</div></div></div>
    <div class="col-sm-4"><div class="rs-kpi"><div class="n" id="k-pool">0</div><div class="l">People who can still win</div></div></div>
    <div class="col-sm-4"><div class="rs-kpi"><div class="n" id="k-hosts">0</div><div class="l">Assigned draw hosts</div></div></div>
  </div>

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card h-100">
        <div class="card-header text-white fw-bold" style="background:#1861c5"><i class="bi bi-gift"></i> PRIZES · DRAW ORDER</div>
        <div class="card-body pt-3">
          <form class="row g-2 align-items-end mb-3" id="rs-add" novalidate>
            <div class="col-md-4">
              <label class="form-label fw-semibold small mb-1" for="rs-type">Prize title</label>
              <input class="form-control" id="rs-type" name="prize_type" list="rs-types" maxlength="60" placeholder="e.g. First Prize" required>
              <datalist id="rs-types">@foreach(['Grand Prize', 'First Prize', 'Second Prize', 'Third Prize', 'Fourth Prize', 'Fifth Prize', 'Special Prize'] as $t)<option value="{{ $t }}">@endforeach</datalist>
            </div>
            <div class="col-md-5">
              <label class="form-label fw-semibold small mb-1" for="rs-name">Prize</label>
              <input class="form-control" id="rs-name" name="prize" maxlength="190" placeholder="e.g. iPad Pro 11" required>
            </div>
            <div class="col-md-3"><button class="btn btn-primary w-100" type="submit"><i class="bi bi-plus-lg"></i> Add prize</button></div>
          </form>
          <div class="rs-orderbar mb-3">
            <span class="fw-semibold small me-1"><i class="bi bi-sort-numeric-down"></i> Draw order</span>
            <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Draw order">
              @foreach(['last_first' => ['bi-sort-numeric-up-alt', 'Last prize first'], 'first_first' => ['bi-sort-numeric-down', 'First prize first'], 'custom' => ['bi-arrow-down-up', 'Custom']] as $k => $o)
              <input type="radio" class="btn-check" name="order_mode" id="om-{{ $k }}" value="{{ $k }}" autocomplete="off">
              <label class="btn btn-outline-primary" for="om-{{ $k }}" title="{{ $orderOptions[$k] }}"><i class="bi {{ $o[0] }}"></i> {{ $o[1] }}</label>
              @endforeach
            </div>
            <div class="small text-muted mt-2" id="rs-order-help"></div>
          </div>
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead><tr><th style="width:56px">#</th><th>Prize</th><th>Winner</th><th class="text-end">Action</th></tr></thead>
              <tbody id="rs-prizes"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4 d-flex flex-column gap-3">
      <div class="card mb-0">
        <div class="card-header text-white fw-bold" style="background:#1861c5"><i class="bi bi-person-badge"></i> DRAW HOSTS</div>
        <div class="card-body pt-3">
          <p class="small text-muted">Only these people can open the draw screen and draw winners for {{ $event->event_short }}.@unless($isSuper) Only a Super Admin can change this list.@endunless</p>
          <input type="search" class="form-control form-control-sm mb-2" id="rs-host-search" placeholder="Search users…">
          <div id="rs-hosts" style="max-height:300px;overflow-y:auto">
            @foreach($users as $u)
            <label class="rs-host" data-q="{{ strtolower($u->name . ' ' . $u->email) }}">
              <input type="checkbox" value="{{ $u->id }}" @disabled(!$isSuper)>
              <span><b>{{ $u->name }}</b><br><small>{{ $u->email }}</small></span>
            </label>
            @endforeach
          </div>
          @if($isSuper)<button class="btn btn-primary w-100 mt-2" id="rs-save-hosts" type="button"><i class="bi bi-check2-circle"></i> Save hosts</button>@endif
        </div>
      </div>

      <div class="card mb-0">
        <div class="card-header text-white fw-bold" style="background:#1861c5"><i class="bi bi-display"></i> DRAW SCREEN</div>
        <form class="card-body pt-3" id="rs-screen" novalidate>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch" id="sc-prizes" name="show_prizes">
            <label class="form-check-label fw-semibold" for="sc-prizes">Show the prize list on the right</label>
            <div class="small text-muted">Off: the draw uses the full screen width.</div>
          </div>
          <label class="form-label fw-semibold mb-1" for="sc-countdown">Countdown before the winner is shown</label>
          <div class="input-group mb-1">
            <input type="number" class="form-control" id="sc-countdown" name="countdown" min="0" max="60" step="1">
            <span class="input-group-text">seconds</span>
          </div>
          <div class="d-flex flex-wrap gap-1 mb-1">
            @foreach([0, 5, 10, 15, 20, 30] as $sec)<button type="button" class="btn btn-sm btn-outline-secondary" data-sec="{{ $sec }}">{{ $sec ? $sec . 's' : 'Off' }}</button>@endforeach
          </div>
          <div class="small text-muted mb-3">0 = no countdown (short spin only).</div>
          <label class="form-label fw-semibold mb-1" for="sc-shuffle">While drawing, the numbers show</label>
          <select class="form-select mb-3" id="sc-shuffle" name="shuffle">
            @foreach($shuffleOptions as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
          </select>
          <div class="es-sound border-top pt-3 mb-3">
            <div class="fw-bold mb-2"><i class="bi bi-music-note-beamed"></i> Sounds</div>

            <label class="form-label fw-semibold mb-1" for="sc-draw-sound">While drawing</label>
            <div class="input-group mb-1">
              <select class="form-select" id="sc-draw-sound" name="draw_sound"></select>
              <button class="btn btn-outline-secondary" type="button" data-preview="draw" title="Listen"><i class="bi bi-play-fill"></i></button>
            </div>
            <div class="d-flex align-items-center gap-2 mb-1">
              <i class="bi bi-volume-down text-muted"></i><input type="range" class="form-range" name="draw_volume" min="0" max="100" step="5"><span class="small text-muted rs-vol" data-for="draw_volume"></span>
            </div>
            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="sc-draw-loop" name="draw_loop"><label class="form-check-label small" for="sc-draw-loop">Repeat until the winner is shown</label></div>

            <div class="form-check form-switch mb-1"><input class="form-check-input" type="checkbox" role="switch" id="sc-tick" name="tick"><label class="form-check-label fw-semibold" for="sc-tick">Countdown tick each second</label></div>
            <div class="d-flex align-items-center gap-2 mb-3">
              <i class="bi bi-volume-down text-muted"></i><input type="range" class="form-range" name="tick_volume" min="0" max="100" step="5"><span class="small text-muted rs-vol" data-for="tick_volume"></span>
              <button class="btn btn-sm btn-outline-secondary" type="button" data-preview="tick" title="Listen"><i class="bi bi-play-fill"></i></button>
            </div>

            <label class="form-label fw-semibold mb-1" for="sc-win-sound">When the winner is shown</label>
            <div class="input-group mb-1">
              <select class="form-select" id="sc-win-sound" name="win_sound"></select>
              <button class="btn btn-outline-secondary" type="button" data-preview="win" title="Listen"><i class="bi bi-play-fill"></i></button>
            </div>
            <div class="d-flex align-items-center gap-2 mb-3">
              <i class="bi bi-volume-down text-muted"></i><input type="range" class="form-range" name="win_volume" min="0" max="100" step="5"><span class="small text-muted rs-vol" data-for="win_volume"></span>
            </div>

            <div class="rounded-3 p-2" style="background:rgba(24,97,197,.05);border:1px dashed rgba(24,97,197,.3)">
              <div class="small fw-semibold mb-1"><i class="bi bi-upload"></i> Add your own sound</div>
              <input type="text" class="form-control form-control-sm mb-1" id="snd-name" maxlength="60" placeholder="Name (e.g. Drum roll)">
              <div class="input-group input-group-sm">
                <input type="file" class="form-control" id="snd-file" accept=".mp3,.wav,.ogg,.m4a,audio/*">
                <button class="btn btn-outline-primary" type="button" id="snd-upload">Upload</button>
              </div>
              <div class="small text-muted mt-1">MP3, WAV, OGG or M4A, up to 8 MB. Shared by all events.</div>
              <ul class="list-unstyled small mb-0 mt-2" id="snd-list"></ul>
            </div>
          </div>
          <button class="btn btn-primary w-100" type="submit"><i class="bi bi-check2-circle"></i> Save draw screen</button>
          <div class="small text-muted mt-2"><i class="bi bi-arrow-repeat"></i> An open draw screen picks up changes within a few seconds.</div>
        </form>
      </div>

      <div class="card mb-0">
        <div class="card-header text-white fw-bold" style="background:#1861c5"><i class="bi bi-people"></i> WHO CAN WIN</div>
        <div class="card-body pt-3">
          @foreach($eligibilityOptions as $k => $label)
          <label class="rs-rule"><input class="form-check-input mt-1" type="radio" name="rule" value="{{ $k }}"><span><b>{{ $label }}</b><br><small class="text-muted">{{ ['registered' => 'Everyone with a registration for this event, except people who already won.', 'checked_in' => 'Use when the draw is held at the venue: people must be checked in at the registration desk.', 'list' => 'Any numbers you type, paste or import (Excel / CSV) — registered or not. Manage the list below.'][$k] ?? '' }}</small></span></label>
          @endforeach
          <div class="small text-muted mt-2" id="rs-skipped"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mt-3" id="my-list" style="display:none">
    <div class="card-header text-white fw-bold d-flex flex-wrap justify-content-between align-items-center gap-2" style="background:#1861c5">
      <span><i class="bi bi-list-ol"></i> MY LIST OF NUMBERS · ONLY THESE CAN WIN</span>
      <a href="{{ route('raffle.entries.template') }}" class="btn btn-sm btn-light"><i class="bi bi-download"></i> Excel / CSV template</a>
    </div>
    <div class="card-body pt-3">
      <div class="row g-3 mb-3">
        <div class="col-4"><div class="rs-kpi"><div class="n" id="ml-total">0</div><div class="l">Numbers on the list</div></div></div>
        <div class="col-4"><div class="rs-kpi"><div class="n text-success" id="ml-ok">0</div><div class="l">Registered for this event</div></div></div>
        <div class="col-4"><div class="rs-kpi"><div class="n text-warning" id="ml-bad">0</div><div class="l">Not registered (win by number)</div></div></div>
      </div>
      <div class="row g-3">
        <div class="col-lg-4">
          <form id="ml-one" novalidate>
            <label class="form-label fw-semibold mb-1" for="ml-number">Add one by one</label>
            <div class="input-group">
              <input class="form-control form-control-lg" id="ml-number" inputmode="numeric" autocomplete="off" placeholder="e.g. 716124" maxlength="12">
              <button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg"></i> Add</button>
            </div>
            <div class="small text-muted mt-1" id="ml-last">Press Enter after each number.</div>
          </form>
        </div>
        <div class="col-lg-4">
          <form id="ml-paste" novalidate>
            <label class="form-label fw-semibold mb-1" for="ml-text">Paste many</label>
            <textarea class="form-control" id="ml-text" rows="3" placeholder="716124, 484704, 205954 … (any separator, or one per line)"></textarea>
            <button class="btn btn-outline-primary btn-sm mt-2" type="submit"><i class="bi bi-clipboard-plus"></i> Add all</button>
          </form>
        </div>
        <div class="col-lg-4">
          <form id="ml-import" novalidate>
            <label class="form-label fw-semibold mb-1" for="ml-file">Import Excel or CSV</label>
            <input type="file" class="form-control" id="ml-file" accept=".xlsx,.csv,.txt">
            <div class="small text-muted mt-1">Numbers in one column with a header like "Number". Optional columns: Name, Email, Country. Old .xls: save as .xlsx first.</div>
            <button class="btn btn-outline-primary btn-sm mt-2" type="submit"><i class="bi bi-file-earmark-arrow-up"></i> Import</button>
          </form>
        </div>
      </div>
      <hr>
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <input type="search" class="form-control form-control-sm" id="ml-search" placeholder="Search number or name…" style="max-width:260px">
        <div class="d-flex gap-2">
          <select class="form-select form-select-sm" id="ml-filter" style="width:auto">
            <option value="">All</option><option value="ok">Registered</option><option value="bad">Not registered (by number)</option><option value="won">Already won</option>
          </select>
          <button class="btn btn-sm btn-outline-danger" type="button" id="ml-clear"><i class="bi bi-trash"></i> Clear list</button>
        </div>
      </div>
      <div class="table-responsive" style="max-height:420px;overflow-y:auto">
        <table class="table table-sm align-middle mb-0">
          <thead class="sticky-top bg-body"><tr><th>Number</th><th>Person</th><th>Status</th><th class="d-none d-md-table-cell">How added</th><th></th></tr></thead>
          <tbody id="ml-body"></tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card mt-3" id="winner-email">
    <div class="card-header text-white fw-bold" style="background:#1861c5"><i class="bi bi-envelope-heart"></i> WINNER EMAIL</div>
    <div class="card-body pt-3">
      <div class="row g-4">
        <form class="col-lg-6" id="rs-email" novalidate>
          <label class="form-label fw-semibold mb-1">Sending</label>
          @foreach($emailModes as $k => $label)
          <label class="rs-rule mb-2"><input class="form-check-input mt-1" type="radio" name="mode" value="{{ $k }}"><span><b>{{ $label }}</b><br><small class="text-muted">{{ ['off' => 'Winners are not emailed.', 'auto' => 'Sent in the background right after "Confirm winner", so the stage never waits.', 'manual' => 'Use the envelope button next to each winner in the prize list.'][$k] }}</small></span></label>
          @endforeach
          <div id="rs-email-fields">
            <div class="mb-3 mt-3">
              <label class="form-label fw-semibold mb-1" for="em-subject">Subject</label>
              <input class="form-control" id="em-subject" name="subject" maxlength="200" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold mb-1" for="em-heading">Heading</label>
              <input class="form-control" id="em-heading" name="heading" maxlength="200">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold mb-1" for="em-intro">Message</label>
              <textarea class="form-control" id="em-intro" name="intro" rows="3" maxlength="2000"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold mb-1" for="em-collect">How to collect the prize <span class="text-muted fw-normal">(one step per line)</span></label>
              <textarea class="form-control" id="em-collect" name="collect" rows="3" maxlength="2000"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold mb-1" for="em-closing">Closing line</label>
              <input class="form-control" id="em-closing" name="closing" maxlength="1000">
            </div>
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" role="switch" id="em-bcc" name="bcc_organiser">
              <label class="form-check-label" for="em-bcc">Send a copy (BCC) to the organiser{{ $organiser ? ' — ' . $organiser : '' }}</label>
            </div>
            <details class="small text-muted mb-3">
              <summary>Placeholders you can use</summary>
              <div class="mt-2">@foreach($emailPlaceholders as $ph => $desc)<code>{{ $ph }}</code> {{ $desc }}<br>@endforeach Use <code>**text**</code> for bold.</div>
            </details>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> Save winner email</button>
            <button class="btn btn-outline-secondary" type="button" id="em-test"><i class="bi bi-send"></i> Send test to {{ Auth::user()->email }}</button>
          </div>
        </form>
        <div class="col-lg-6">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-semibold"><i class="bi bi-eye"></i> Live preview</span>
            <span class="small text-muted">Updates as you type</span>
          </div>
          <iframe id="em-preview" title="Winner email preview" style="width:100%;height:720px;border:1px solid rgba(0,0,0,.1);border-radius:12px;background:#eef2f8"></iframe>
        </div>
      </div>
    </div>
  </div>
  </fieldset>
</section>
@endif

<div class="modal fade" id="rsEdit" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="rsEditForm" novalidate>
      <div class="modal-header"><h5 class="modal-title">Edit prize</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label fw-semibold" for="rse-type">Prize title</label><input class="form-control" id="rse-type" name="prize_type" list="rs-types" maxlength="60" required></div>
        <div class="mb-1"><label class="form-label fw-semibold" for="rse-name">Prize</label><input class="form-control" id="rse-name" name="prize" maxlength="190" required></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
@if($event)
<script>
(function () {
  'use strict';
  var data = @json($data);
  var isSuper = @json($isSuper);
  var base = @json(url('/raffle-setup'));
  var EVENT_ID = @json($event->id);
  var IS_CURRENT = @json($current && $current->id === $event->id);
  function withEvent(u) { return u + (u.indexOf('?') < 0 ? '?' : '&') + 'event=' + EVENT_ID; }
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2200 });
  var editModal = new bootstrap.Modal(document.getElementById('rsEdit'));
  var editing = null;
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
  function send(url, method, body) {
    return fetch(withEvent(url), { method: method, headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : null })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (res) {
        if (!res.ok) { var e = res.d.errors || {}; var k = Object.keys(e)[0]; Swal.fire('Not saved', k ? e[k][0] : (res.d.message || 'Please try again.'), 'warning'); return null; }
        data = res.d.data; render(); toast.fire({ icon: 'success', title: res.d.message }); return res.d;
      });
  }

  function render() {
    var won = data.prizes.filter(function (p) { return p.winner_id; }).length;
    document.getElementById('k-prizes').textContent = won + ' / ' + data.prizes.length;
    document.getElementById('k-pool').textContent = data.pool;
    document.getElementById('k-hosts').textContent = data.hosts.length;
    document.querySelectorAll('#rs-hosts input').forEach(function (cb) { cb.checked = data.hosts.indexOf(parseInt(cb.value, 10)) > -1; });
    document.querySelectorAll('input[name=rule]').forEach(function (r) { r.checked = r.value === data.eligibility; });
    document.querySelectorAll('input[name=order_mode]').forEach(function (r) { r.checked = r.value === data.order; });
    fillEmail();
    var listOn = data.eligibility === 'list';
    document.getElementById('my-list').style.display = listOn ? '' : 'none';
    document.getElementById('ml-total').textContent = data.entries.total;
    document.getElementById('ml-ok').textContent = data.entries.registered;
    document.getElementById('ml-bad').textContent = data.entries.unknown;
    if (listOn && !entriesLoaded) loadEntries();
    fillScreen(false);
    document.getElementById('rs-order-help').textContent = {
      last_first: 'Drawn from the smallest prize up to the biggest (e.g. Fifth → Fourth → … → First → Grand). Set automatically from the prize titles.',
      first_first: 'Drawn from the biggest prize down (e.g. Grand → First → Second → …). Set automatically from the prize titles.',
      custom: 'Drawn from top to bottom. Use the arrows to change the order.'
    }[data.order] + ' Titles without a number (e.g. "Special Prize") count as the smallest prizes.';
    document.getElementById('rs-skipped').innerHTML = data.skipped ? '<i class="bi bi-person-dash"></i> ' + data.skipped + ' drawn but not present (left out of this draw). <a href="#" id="rs-reset">Let them be drawn again</a>' : '';
    var reset = document.getElementById('rs-reset');
    if (reset) reset.addEventListener('click', function (e) { e.preventDefault(); send(base + '/reset-skipped', 'POST'); });
    var open = document.getElementById('rs-open');
    open.classList.toggle('disabled', !data.isHost || !IS_CURRENT);

    var body = document.getElementById('rs-prizes');
    body.innerHTML = '';
    if (!data.prizes.length) { body.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No prizes yet. Add the first one above.</td></tr>'; return; }
    data.prizes.forEach(function (p, i) {
      var tr = document.createElement('tr');
      tr.innerHTML = '<td><span class="rs-order">' + (i + 1) + '</span></td>' +
        '<td><div class="fw-bold">' + esc(p.prize_type) + '</div><div class="small text-muted">' + esc(p.prize) + '</div></td>' +
        '<td>' + (p.winner_id ? '<div class="rs-won"><i class="bi bi-trophy-fill"></i> ' + esc(p.winners_name) + '</div><div class="small text-muted">#' + esc(p.winners_number) + (p.winners_country ? ' · ' + esc(p.winners_country) : '') + '</div>' + emailBadge(p) : '<span class="badge bg-light text-dark border">Not drawn yet</span>') + '</td>' +
        '<td class="text-end text-nowrap">' +
          '<button class="btn btn-sm btn-outline-secondary" data-a="up" title="Draw earlier" ' + (i ? '' : 'disabled') + '><i class="bi bi-arrow-up"></i></button> ' +
          '<button class="btn btn-sm btn-outline-secondary" data-a="down" title="Draw later" ' + (i < data.prizes.length - 1 ? '' : 'disabled') + '><i class="bi bi-arrow-down"></i></button> ' +
          '<button class="btn btn-sm btn-outline-primary" data-a="edit" title="Edit"><i class="bi bi-pencil"></i></button> ' +
          (p.winner_id && data.email.mode !== 'off' ? '<button class="btn btn-sm btn-outline-success" data-a="email" title="' + (p.emailed_at ? 'Send the winner email again' : 'Send the winner email') + '"><i class="bi bi-envelope' + (p.emailed_at ? '-check' : '') + '"></i></button> ' : '') +
          (p.winner_id ? (isSuper ? '<button class="btn btn-sm btn-outline-warning" data-a="undo" title="Undo winner"><i class="bi bi-arrow-counterclockwise"></i></button>' : '')
                       : '<button class="btn btn-sm btn-outline-danger" data-a="del" title="Remove"><i class="bi bi-trash"></i></button>') +
        '</td>';
      tr.querySelectorAll('[data-a]').forEach(function (b) { b.addEventListener('click', function () { act(p, i, b.dataset.a); }); });
      body.appendChild(tr);
    });
  }

  // ---- My list of numbers ----
  var entries = [], entriesLoaded = false;
  var SOURCE = { manual: 'Typed', paste: 'Pasted', import: 'Imported' };
  function loadEntries() {
    entriesLoaded = true;
    fetch(withEvent(base + '/entries'), { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); })
      .then(function (d) { entries = d.entries; renderEntries(); });
  }
  function renderEntries() {
    var q = document.getElementById('ml-search').value.trim().toLowerCase();
    var f = document.getElementById('ml-filter').value;
    var rows = entries.filter(function (e) {
      if (q && (e.number + ' ' + (e.name || '')).toLowerCase().indexOf(q) < 0) return false;
      if (f === 'ok') return e.registered; if (f === 'bad') return !e.registered; if (f === 'won') return e.won;
      return true;
    });
    var body = document.getElementById('ml-body');
    body.innerHTML = rows.slice(0, 1000).map(function (e) {
      return '<tr><td class="fw-bold" style="font-variant-numeric:tabular-nums">' + esc(e.number) + '</td>' +
        '<td>' + (e.name ? esc(e.name) + (e.country ? ' <span class="text-muted small">· ' + esc(e.country) + '</span>' : '') : '<span class="text-muted small">—</span>') +
          (!e.registered ? '<div class="small text-warning-emphasis"><i class="bi bi-hash"></i> Not a registration — wins by number' + (e.email ? ' · <i class="bi bi-envelope"></i> ' + esc(e.email) : '') + '</div>' : '') + '</td>' +
        '<td>' + (e.won ? '<span class="badge bg-success"><i class="bi bi-trophy"></i> Won</span>' : (e.checked_in ? '<span class="badge bg-info text-dark">Checked in</span>' : '<span class="badge bg-light text-dark border">Can win</span>')) + '</td>' +
        '<td class="d-none d-md-table-cell small text-muted">' + (SOURCE[e.source] || '') + '</td>' +
        '<td class="text-end"><button class="btn btn-sm btn-link text-danger p-0" data-id="' + e.id + '" title="Remove"><i class="bi bi-x-circle"></i></button></td></tr>';
    }).join('') || '<tr><td colspan="5" class="text-center text-muted py-4">' + (entries.length ? 'No numbers match.' : 'The list is empty. Add numbers above.') + '</td></tr>';
    if (rows.length > 1000) body.insertAdjacentHTML('beforeend', '<tr><td colspan="5" class="text-center small text-muted">Showing 1,000 of ' + rows.length + '. Use search to find a number.</td></tr>');
    body.querySelectorAll('[data-id]').forEach(function (b) {
      b.addEventListener('click', function () {
        send(base + '/entries/' + b.dataset.id, 'DELETE').then(function (ok) { if (ok) { entries = entries.filter(function (e) { return String(e.id) !== b.dataset.id; }); renderEntries(); } });
      });
    });
  }
  function afterAdd(ok) { if (ok) loadEntries(); return ok; }
  document.getElementById('ml-one').addEventListener('submit', function (e) {
    e.preventDefault();
    var input = document.getElementById('ml-number'), v = input.value.trim();
    if (!v) return input.focus();
    send(base + '/entries', 'POST', { numbers: v }).then(function (ok) {
      if (!ok) return;
      afterAdd(ok); input.value = ''; input.focus();
      document.getElementById('ml-last').innerHTML = '<i class="bi bi-check2"></i> Last added: <b>' + esc(v) + '</b>';
    });
  });
  document.getElementById('ml-paste').addEventListener('submit', function (e) {
    e.preventDefault();
    var t = document.getElementById('ml-text');
    send(base + '/entries', 'POST', { numbers: t.value }).then(function (ok) { if (ok) { t.value = ''; afterAdd(ok); } });
  });
  document.getElementById('ml-import').addEventListener('submit', function (e) {
    e.preventDefault();
    var file = document.getElementById('ml-file').files[0];
    if (!file) return Swal.fire('Choose a file', 'Pick an Excel (.xlsx) or CSV file first.', 'info');
    var fd = new FormData(); fd.append('file', file);
    var btn = e.target.querySelector('button'); btn.disabled = true;
    fetch(withEvent(base + '/entries/import'), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: fd })
      .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (res) {
        btn.disabled = false;
        if (!res.ok) { var er = res.d.errors || {}; var k = Object.keys(er)[0]; return Swal.fire('Not imported', k ? er[k][0] : (res.d.message || 'Please try again.'), 'warning'); }
        data = res.d.data; render(); loadEntries(); document.getElementById('ml-file').value = '';
        Swal.fire({ icon: 'success', title: 'Imported', text: res.d.message });
      }).catch(function () { btn.disabled = false; Swal.fire('Not imported', 'Please try again.', 'error'); });
  });
  document.getElementById('ml-search').addEventListener('input', renderEntries);
  document.getElementById('ml-filter').addEventListener('change', renderEntries);
  document.getElementById('ml-clear').addEventListener('click', function () {
    if (!entries.length) return;
    Swal.fire({ icon: 'warning', title: 'Clear the whole list?', text: entries.length + ' numbers will be removed.', showCancelButton: true, confirmButtonText: 'Clear list', confirmButtonColor: '#d33' })
      .then(function (r) { if (r.isConfirmed) send(base + '/entries/clear', 'POST').then(function (ok) { if (ok) { entries = []; renderEntries(); } }); });
  });

  // ---- Winner email ----
  var emailForm = document.getElementById('rs-email');
  var emailFilled = false;
  function fillEmail() {
    if (emailFilled) return; // keep what the admin is typing
    emailFilled = true;
    var e = data.email;
    emailForm.querySelectorAll('input[name=mode]').forEach(function (r) { r.checked = r.value === e.mode; });
    ['subject', 'heading', 'intro', 'collect', 'closing'].forEach(function (k) { emailForm[k].value = e[k] || ''; });
    emailForm.bcc_organiser.checked = e.bcc_organiser === '1';
    toggleEmailFields(); preview();
  }
  function emailValues() {
    var m = emailForm.querySelector('input[name=mode]:checked');
    return { mode: m ? m.value : 'off', subject: emailForm.subject.value, heading: emailForm.heading.value, intro: emailForm.intro.value,
      collect: emailForm.collect.value, closing: emailForm.closing.value, bcc_organiser: emailForm.bcc_organiser.checked };
  }
  function toggleEmailFields() {
    var off = emailValues().mode === 'off';
    document.getElementById('rs-email-fields').style.opacity = off ? '.55' : '';
    document.getElementById('em-test').disabled = false;
  }
  var previewTimer = null;
  function preview() {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(function () {
      fetch(withEvent(base + '/email/preview'), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' }, body: JSON.stringify(emailValues()) })
        .then(function (r) { return r.ok ? r.text() : Promise.reject(r.status); })
        .then(function (html) { document.getElementById('em-preview').srcdoc = html; })
        .catch(function (status) {
          document.getElementById('em-preview').srcdoc = '<p style="font-family:sans-serif;color:#6b7489;padding:24px;text-align:center">' +
            (status === 419 ? 'Your session has expired. Reload the page to see the preview.' : 'The preview could not be loaded. Please try again.') + '</p>';
        });
    }, 350);
  }
  emailForm.addEventListener('input', function () { toggleEmailFields(); preview(); });
  emailForm.addEventListener('change', function () { toggleEmailFields(); preview(); });
  emailForm.addEventListener('submit', function (e) {
    e.preventDefault();
    send(base + '/email', 'POST', emailValues());
  });
  document.getElementById('em-test').addEventListener('click', function () {
    var btn = this; btn.disabled = true;
    var v = emailValues(); if (v.mode === 'off') v.mode = 'manual';
    fetch(withEvent(base + '/email/test'), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(v) })
      .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (res) {
        btn.disabled = false;
        if (res.ok) toast.fire({ icon: 'success', title: res.d.message });
        else { var e = res.d.errors || {}; var k = Object.keys(e)[0]; Swal.fire('Not sent', k ? e[k][0] : (res.d.message || 'Please try again.'), 'error'); }
      }).catch(function () { btn.disabled = false; Swal.fire('Not sent', 'Please try again.', 'error'); });
  });

  // ---- Draw screen (incl. sounds) ----
  var screenForm = document.getElementById('rs-screen');
  var screenFilled = false;
  function soundSelects() {
    ['draw_sound', 'win_sound'].forEach(function (name) {
      var sel = screenForm[name], keep = sel.value;
      sel.innerHTML = Object.keys(data.soundOptions).map(function (k) { return '<option value="' + esc(k) + '">' + esc(data.soundOptions[k]) + '</option>'; }).join('');
      if (keep && data.soundOptions[keep]) sel.value = keep;
    });
    document.getElementById('snd-list').innerHTML = data.sounds.map(function (s) {
      return '<li class="d-flex justify-content-between align-items-center py-1"><span><i class="bi bi-music-note"></i> ' + esc(s.name) + '</span>' +
        '<button type="button" class="btn btn-sm btn-link text-danger p-0" data-del-sound="' + esc(s.id) + '" title="Remove"><i class="bi bi-x-circle"></i></button></li>';
    }).join('');
    document.querySelectorAll('[data-del-sound]').forEach(function (b) {
      b.addEventListener('click', function () {
        Swal.fire({ icon: 'warning', title: 'Remove this sound?', text: 'Events using it go back to the default sound.', showCancelButton: true, confirmButtonText: 'Remove', confirmButtonColor: '#d33' })
          .then(function (r) { if (r.isConfirmed) send(base + '/sounds/' + b.dataset.delSound, 'DELETE').then(function (ok) { if (ok) soundSelects(); }); });
      });
    });
  }
  function showVolumes() {
    screenForm.querySelectorAll('.rs-vol').forEach(function (el) { el.textContent = screenForm[el.dataset.for].value + '%'; });
  }
  function fillScreen(force) {
    if (screenFilled && !force) return;
    screenFilled = true;
    var sc = data.screen;
    soundSelects();
    screenForm.show_prizes.checked = !!sc.show_prizes;
    screenForm.countdown.value = sc.countdown;
    screenForm.shuffle.value = sc.shuffle;
    screenForm.draw_sound.value = sc.draw_sound; screenForm.draw_volume.value = sc.draw_volume; screenForm.draw_loop.checked = !!sc.draw_loop;
    screenForm.tick.checked = !!sc.tick; screenForm.tick_volume.value = sc.tick_volume;
    screenForm.win_sound.value = sc.win_sound; screenForm.win_volume.value = sc.win_volume;
    showVolumes();
  }
  screenForm.addEventListener('input', showVolumes);
  screenForm.querySelectorAll('[data-sec]').forEach(function (b) { b.addEventListener('click', function () { screenForm.countdown.value = b.dataset.sec; }); });
  screenForm.addEventListener('submit', function (e) {
    e.preventDefault();
    stopPreview();
    send(base + '/screen', 'POST', {
      show_prizes: screenForm.show_prizes.checked, countdown: parseInt(screenForm.countdown.value || '0', 10), shuffle: screenForm.shuffle.value,
      draw_sound: screenForm.draw_sound.value, draw_volume: parseInt(screenForm.draw_volume.value, 10), draw_loop: screenForm.draw_loop.checked,
      tick: screenForm.tick.checked, tick_volume: parseInt(screenForm.tick_volume.value, 10),
      win_sound: screenForm.win_sound.value, win_volume: parseInt(screenForm.win_volume.value, 10)
    });
  });

  // Listen before saving
  var previewAudio = new Audio(), previewCtx = null;
  var SOUND_URLS = @json(collect(\App\Support\Raffle::BUILTIN_SOUNDS)->map(fn ($s) => asset($s[1])));
  var UPLOAD_BASE = @json(asset('upload/raffle-sounds'));
  function soundUrl(key) {
    if (SOUND_URLS[key]) return SOUND_URLS[key];
    var up = data.sounds.filter(function (s) { return 'u:' + s.id === key; })[0];
    return up ? UPLOAD_BASE + '/' + up.file : null;
  }
  function stopPreview() { try { previewAudio.pause(); } catch (e) {} }
  screenForm.querySelectorAll('[data-preview]').forEach(function (b) {
    b.addEventListener('click', function () {
      stopPreview();
      var kind = b.dataset.preview;
      if (kind === 'tick') {
        previewCtx = previewCtx || new (window.AudioContext || window.webkitAudioContext)();
        var vol = parseInt(screenForm.tick_volume.value, 10) / 100 * 0.5;
        [0, 1, 2, 3].forEach(function (i) {
          var o = previewCtx.createOscillator(), g = previewCtx.createGain(), t = previewCtx.currentTime + i * 0.5, last = i === 3;
          o.frequency.value = last ? 1320 : 880; g.gain.setValueAtTime(Math.max(vol, 0.0001), t); g.gain.exponentialRampToValueAtTime(0.0001, t + (last ? 0.22 : 0.12));
          o.connect(g); g.connect(previewCtx.destination); o.start(t); o.stop(t + 0.25);
        });
        return;
      }
      var url = soundUrl(screenForm[kind + '_sound'].value);
      if (!url) return toast.fire({ icon: 'info', title: 'No sound selected' });
      previewAudio.src = url;
      previewAudio.volume = parseInt(screenForm[kind + '_volume'].value, 10) / 100;
      previewAudio.currentTime = 0;
      previewAudio.play().catch(function () {});
      setTimeout(stopPreview, 8000); // a short preview is enough
    });
  });
  document.getElementById('snd-upload').addEventListener('click', function () {
    var file = document.getElementById('snd-file').files[0], btn = this;
    if (!file) return Swal.fire('Choose a sound file', 'MP3, WAV, OGG or M4A.', 'info');
    var fd = new FormData(); fd.append('sound', file); fd.append('name', document.getElementById('snd-name').value);
    btn.disabled = true;
    fetch(withEvent(base + '/sounds'), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: fd })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (res) {
        btn.disabled = false;
        if (!res.ok) { var er = res.d.errors || {}; var k = Object.keys(er)[0]; return Swal.fire('Not uploaded', k ? er[k][0] : (res.d.message || 'Please try again.'), 'warning'); }
        data = res.d.data; soundSelects(); render();
        document.getElementById('snd-file').value = ''; document.getElementById('snd-name').value = '';
        toast.fire({ icon: 'success', title: res.d.message });
      }).catch(function () { btn.disabled = false; Swal.fire('Not uploaded', 'Please try again.', 'error'); });
  });

  function emailBadge(p) {
    if (p.emailed_at) return '<div class="small text-success mt-1"><i class="bi bi-envelope-check"></i> Emailed ' + esc(p.emailed_at.slice(0, 16)) + '</div>';
    if (p.email_error) return '<div class="small text-danger mt-1" title="' + esc(p.email_error) + '"><i class="bi bi-envelope-exclamation"></i> Email failed</div>';
    return data.email.mode === 'off' ? '' : '<div class="small text-muted mt-1"><i class="bi bi-envelope"></i> Not emailed</div>';
  }

  function act(p, i, a) {
    if (a === 'email') {
      return Swal.fire({ icon: 'question', title: (p.emailed_at ? 'Send again to ' : 'Email ') + p.winners_name + '?', text: p.prize_type + ' — ' + p.prize, showCancelButton: true, confirmButtonText: 'Send' })
        .then(function (r) {
          if (!r.isConfirmed) return;
          fetch(withEvent(base + '/prizes/' + p.id + '/email'), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) {
              if (res.d.data) { data = res.d.data; render(); }
              if (res.ok) toast.fire({ icon: 'success', title: res.d.message }); else Swal.fire('Not sent', res.d.message || 'Please try again.', 'error');
            });
        });
    }
    if (a === 'up' || a === 'down') {
      var ids = data.prizes.map(function (x) { return x.id; });
      var j = a === 'up' ? i - 1 : i + 1;
      ids[i] = ids[j]; ids[j] = p.id;
      return send(base + '/prizes/order', 'POST', { ids: ids });
    }
    if (a === 'edit') { editing = p; document.getElementById('rse-type').value = p.prize_type; document.getElementById('rse-name').value = p.prize; editModal.show(); return; }
    if (a === 'del') {
      return Swal.fire({ icon: 'warning', title: 'Remove ' + p.prize_type + '?', text: p.prize, showCancelButton: true, confirmButtonText: 'Remove', confirmButtonColor: '#d33' })
        .then(function (r) { if (r.isConfirmed) send(base + '/prizes/' + p.id, 'DELETE'); });
    }
    if (a === 'undo') {
      return Swal.fire({ icon: 'warning', title: 'Undo this winner?', html: esc(p.winners_name) + ' will no longer be the winner of <b>' + esc(p.prize_type) + '</b>, and the prize will be drawn again.', showCancelButton: true, confirmButtonText: 'Undo winner', confirmButtonColor: '#d33' })
        .then(function (r) { if (r.isConfirmed) send(base + '/prizes/' + p.id + '/undo', 'POST'); });
    }
  }

  document.getElementById('rs-add').addEventListener('submit', function (e) {
    e.preventDefault();
    var f = e.target;
    send(base + '/prizes', 'POST', { prize_type: f.prize_type.value, prize: f.prize.value }).then(function (ok) { if (ok) { f.reset(); f.prize_type.focus(); } });
  });
  document.getElementById('rsEditForm').addEventListener('submit', function (e) {
    e.preventDefault();
    send(base + '/prizes/' + editing.id, 'PUT', { prize_type: document.getElementById('rse-type').value, prize: document.getElementById('rse-name').value }).then(function (ok) { if (ok) editModal.hide(); });
  });
  document.querySelectorAll('input[name=rule]').forEach(function (r) { r.addEventListener('change', function () { send(base + '/eligibility', 'POST', { rule: r.value }); }); });
  document.querySelectorAll('input[name=order_mode]').forEach(function (r) { r.addEventListener('change', function () { send(base + '/order-mode', 'POST', { mode: r.value }).then(function (ok) { if (!ok) render(); }); }); });
  document.getElementById('rs-host-search').addEventListener('input', function () {
    var q = this.value.trim().toLowerCase();
    document.querySelectorAll('#rs-hosts .rs-host').forEach(function (l) { l.style.display = !q || l.dataset.q.indexOf(q) > -1 ? '' : 'none'; });
  });
  var saveHosts = document.getElementById('rs-save-hosts');
  if (saveHosts) saveHosts.addEventListener('click', function () {
    var ids = Array.prototype.map.call(document.querySelectorAll('#rs-hosts input:checked'), function (c) { return parseInt(c.value, 10); });
    send(base + '/hosts', 'POST', { user_ids: ids });
  });
  render();
})();
</script>
@endif
@endpush
