@extends('backend.layouts.master')
@section('title')
{{ $card->card_no }} · NFC card
@endsection
@push('styles')
<style>
  .nf-steps { display: grid; gap: 12px; }
  .nf-step { border: 1px solid rgba(0,0,0,.08); border-radius: 14px; padding: 14px 16px; display: grid; grid-template-columns: 40px 1fr; gap: 12px; }
  .nf-step .n { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-weight: 800; background: rgba(0,0,0,.05); color: #6b7489; }
  .nf-step.done .n { background: #e6f5e0; color: #2f7a1b; }
  .nf-step.now { border-color: #1861c5; box-shadow: 0 0 0 3px rgba(24,97,197,.1); }
  .nf-step.now .n { background: #1861c5; color: #fff; }
  .nf-step h3 { font-size: 1rem; font-weight: 800; margin: 0 0 4px; }
  .nf-step p { margin: 0 0 8px; color: var(--ad-muted, #6b7489); font-size: 14px; }
  .nf-url { font-family: monospace; font-size: 13px; background: rgba(24,97,197,.06); border-radius: 8px; padding: 8px 10px; word-break: break-all; }
  .nf-live { border-radius: 12px; padding: 12px 14px; background: #f3f8ff; border: 1px dashed #9cc0f2; display: none; }
  .nf-live.show { display: block; }
  .nf-pulse { display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #1861c5; margin-right: 8px; animation: nfp 1.2s infinite; }
  @keyframes nfp { 0% { box-shadow: 0 0 0 0 rgba(24,97,197,.6); } 100% { box-shadow: 0 0 0 12px rgba(24,97,197,0); } }
  .nf-tl { list-style: none; padding: 0; margin: 0; }
  .nf-tl li { display: grid; grid-template-columns: 32px 1fr; gap: 10px; padding: 8px 0; border-bottom: 1px solid rgba(0,0,0,.05); font-size: 14px; }
  .nf-tl li i { width: 32px; height: 32px; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: rgba(24,97,197,.08); color: #1861c5; }
  .nf-av { width: 54px; height: 54px; border-radius: 50%; object-fit: cover; background: #e9eff9; color: #1861c5; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; }
  .nf-results { position: absolute; z-index: 20; width: 100%; max-height: 260px; overflow-y: auto; }
</style>
@endpush
@section('content')
@php
   use App\Models\NfcCard as N;
   $p = $card->participant;
   $off = in_array($card->status, ['lost', 'damaged', 'deactivated'], true);
   $step = !$p ? 1 : ($card->encode_status !== 'encoded' ? 2 : ($card->verify_status !== 'verified' && $card->status !== 'active' ? 3 : ($card->status !== 'active' ? 4 : 5)));
   $badge = fn ($set, $k) => '<span class="badge bg-' . $set[$k][1] . '">' . $set[$k][0] . '</span>';
@endphp
<div class="pagetitle d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div>
    <h1>{{ $card->card_no }} {!! $badge(N::STATUS, $card->status) !!}</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li><li class="breadcrumb-item"><a href="{{ route('nfc.index') }}">NFC Cards</a></li><li class="breadcrumb-item active">{{ $card->card_no }}</li></ol></nav>
  </div>
  <div class="d-flex gap-2"><a href="{{ route('nfc.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> All cards</a><a href="{{ route('nfc.station') }}" class="btn btn-outline-primary"><i class="bi bi-broadcast"></i> Encoding station</a></div>
</div>

<section class="section">
  @include('backend.pages.participants.link-warning')
  @if($card->url_written && $card->url_written !== $card->url())
  <div class="alert alert-danger small py-2"><i class="bi bi-exclamation-octagon"></i> This chip was written with <code>{{ $card->url_written }}</code>, but links now use <code>{{ $card->url() }}</code>. {{ $card->locked ? 'The chip is locked, so replace the card.' : 'Switch the card off and write it again.' }}</div>
  @endif
  @if($replaces || $replacedBy)
  <div class="alert alert-info small py-2">
    @if($replaces)<i class="bi bi-arrow-repeat"></i> This card replaces <a href="{{ route('nfc.show', $replaces->id) }}">{{ $replaces->card_no }}</a> ({{ N::STATUS[$replaces->status][0] }}). @endif
    @if($replacedBy)<i class="bi bi-arrow-repeat"></i> Replaced by <a href="{{ route('nfc.show', $replacedBy->id) }}">{{ $replacedBy->card_no }}</a>. @endif
  </div>
  @endif
  <div class="row g-4">
    <div class="col-xl-7">
      {{-- Participant --}}
      <div class="card mb-3"><div class="card-body pt-3">
        <div class="fw-bold mb-2"><i class="bi bi-person"></i> Participant</div>
        @if($p)
        <div class="d-flex align-items-center gap-3">
          @if($p->photo && is_file(base_path($p->photo)))<img class="nf-av" src="{{ \App\Support\Thumb::url($p->photo, 480) }}" alt="">@else<span class="nf-av">{{ $p->initials }}</span>@endif
          <div class="flex-grow-1"><a class="fw-bold" href="{{ route('participants.edit', $p->id) }}">{{ $p->name }}</a><div class="small text-muted">{{ $p->code }} · {{ $p->organization }}</div></div>
          <a class="btn btn-sm btn-outline-secondary" href="{{ $p->url() }}" target="_blank"><i class="bi bi-box-arrow-up-right"></i> Profile</a>
        </div>
        @elseif(!$off)
        <div class="position-relative">
          <input class="form-control" id="nf-find" placeholder="Search participant: name, ID (MBR-…), email, organization" autocomplete="off">
          <div class="list-group nf-results shadow-sm d-none" id="nf-results"></div>
        </div>
        <div class="small text-muted mt-1">A participant can have one card in use; for a second card use "Replace card" on their current card.</div>
        @else
        <div class="small text-muted">No participant.</div>
        @endif
      </div></div>

      {{-- Workflow --}}
      @unless($off)
      <div class="nf-steps mb-3">
        <div class="nf-step {{ $step > 1 ? 'done' : ($step === 1 ? 'now' : '') }}"><span class="n">{!! $step > 1 ? '<i class="bi bi-check-lg"></i>' : '1' !!}</span><div><h3>Assign</h3><p>{{ $p ? 'Assigned to ' . $p->name . '.' : 'Choose the participant above.' }}</p></div></div>

        <div class="nf-step {{ $step > 2 ? 'done' : ($step === 2 ? 'now' : '') }}"><span class="n">{!! $step > 2 ? '<i class="bi bi-check-lg"></i>' : '2' !!}</span><div>
          <h3>Write the link to the chip {!! $card->encode_status !== 'not_encoded' ? $badge(N::ENCODE, $card->encode_status) : '' !!}</h3>
          <p>The card stores its own link. It opens {{ $p ? $p->name . "'s" : 'the participant\'s' }} profile while the card is active.</p>
          <div class="nf-url mb-2" id="nf-url">{{ $card->url() }}</div>
          @if($card->status === 'active')<div class="small text-muted">To rewrite this chip, switch the card off first.</div>@endif
          @if($p && $card->status !== 'active')
          <div class="webnfc-yes d-none">
            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="nf-lock"><label class="form-check-label small" for="nf-lock">Lock the chip (read-only) after writing — <b>cannot be undone</b></label></div>
            <button class="btn btn-primary" id="nf-write"><i class="bi bi-broadcast"></i> Write with this phone</button>
          </div>
          <div class="webnfc-no">
            <div class="alert alert-light border small py-2 mb-2"><i class="bi bi-info-circle"></i> Writing from the browser needs <b>Chrome on an Android phone</b> with NFC (open this page there). Or use an external encoder app / USB writer: write the link above as an <b>NDEF URL record</b>, then confirm below.</div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
              <button class="btn btn-sm btn-outline-primary" type="button" id="nf-copy"><i class="bi bi-clipboard"></i> Copy link</button>
              <input class="form-control form-control-sm" id="nf-uid" placeholder="Chip UID (optional, e.g. 04:A2:3B:…)" style="max-width:240px" value="{{ $card->uid }}">
              <button class="btn btn-sm btn-success" type="button" id="nf-mark-encoded"><i class="bi bi-check2"></i> Mark as encoded</button>
            </div>
            <details class="small mt-2"><summary>QR of the link (for encoder apps)</summary><img src="data:image/svg+xml;base64,{{ base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(160)->margin(1)->generate($card->url())) }}" alt="" class="mt-2"></details>
          </div>
          <div class="nf-live mt-2" id="nf-live-write"><span class="nf-pulse"></span><b>Hold the card against the back of the phone…</b> <button class="btn btn-sm btn-link" type="button" data-cancel>Cancel</button></div>
          @endif
        </div></div>

        <div class="nf-step {{ $step > 3 ? 'done' : ($step === 3 ? 'now' : '') }}"><span class="n">{!! $step > 3 ? '<i class="bi bi-check-lg"></i>' : '3' !!}</span><div>
          <h3>Verify {!! $card->verify_status !== 'not_verified' ? $badge(N::VERIFY, $card->verify_status) : '' !!}</h3>
          <p>Read the card back to check it holds the right link.</p>
          @if($card->encode_status === 'encoded' && $card->status !== 'active')
          <button class="btn btn-outline-primary webnfc-yes d-none" id="nf-verify"><i class="bi bi-upc-scan"></i> Read card with this phone</button>
          <div class="webnfc-no small">Tap the card with any phone: it should open the "card not activated yet" page showing <b>{{ $card->card_no }}</b> (when you are logged in).
            <button class="btn btn-sm btn-outline-success ms-1" id="nf-verify-manual"><i class="bi bi-check2"></i> It opened {{ $card->card_no }}</button></div>
          <div class="nf-live mt-2" id="nf-live-read"><span class="nf-pulse"></span><b>Hold the card against the back of the phone…</b> <button class="btn btn-sm btn-link" type="button" data-cancel>Cancel</button></div>
          @endif
        </div></div>

        <div class="nf-step {{ $step > 4 ? 'done' : ($step === 4 ? 'now' : '') }}"><span class="n">{!! $step > 4 ? '<i class="bi bi-check-lg"></i>' : '4' !!}</span><div>
          <h3>Activate</h3>
          <p>{{ $card->status === 'active' ? 'Active since ' . optional($card->activated_at)->format('j M Y, H:i') . '. Tapping the card opens the profile.' : 'Switch the card on: from then on a tap opens the profile.' }}</p>
          @if($card->status !== 'active' && $p && $card->encode_status === 'encoded')<button class="btn btn-success" data-act="activate"><i class="bi bi-check-circle"></i> Activate card</button>@if($card->verify_status !== 'verified')<span class="small text-warning-emphasis ms-2"><i class="bi bi-exclamation-triangle"></i> not verified yet</span>@endif @endif
        </div></div>
      </div>
      @endunless

      {{-- History --}}
      <div class="card"><div class="card-body pt-3">
        <div class="fw-bold mb-2"><i class="bi bi-clock-history"></i> History</div>
        <ul class="nf-tl">
          @foreach($card->logs as $l)
          @php $lab = \App\Models\NfcCardLog::LABELS[$l->action] ?? [ucfirst($l->action), 'bi-dot']; @endphp
          <li><i class="bi {{ $lab[1] }}"></i><div><b>{{ $lab[0] }}</b>@if($l->details) — {{ $l->details }}@endif<div class="small text-muted">{{ $l->created_at->format('j M Y, H:i') }}{{ $l->user ? ' · ' . $l->user->name : '' }}</div></div></li>
          @endforeach
        </ul>
      </div></div>
    </div>

    <div class="col-xl-5">
      <div class="card mb-3"><div class="card-body pt-3">
        <div class="fw-bold mb-2"><i class="bi bi-credit-card-2-front"></i> Card</div>
        <table class="table table-sm mb-0 small">
          <tr><th class="text-muted fw-normal">Status</th><td>{!! $badge(N::STATUS, $card->status) !!}</td></tr>
          <tr><th class="text-muted fw-normal">Chip</th><td>{!! $badge(N::ENCODE, $card->encode_status) !!} {!! $badge(N::VERIFY, $card->verify_status) !!} @if($card->locked)<span class="badge bg-dark"><i class="bi bi-lock"></i> Locked</span>@endif</td></tr>
          <tr><th class="text-muted fw-normal">UID</th><td><code>{{ $card->uid ?: '—' }}</code></td></tr>
          <tr><th class="text-muted fw-normal">Encoded</th><td>{{ optional($card->encoded_at)->format('j M Y, H:i') ?: '—' }}</td></tr>
          <tr><th class="text-muted fw-normal">Printed</th><td>{!! $badge(N::PRINT, $card->print_status) !!} <button class="btn btn-sm btn-link p-0 ms-1" data-act="printed" data-printed="{{ $card->print_status === 'printed' ? 0 : 1 }}">{{ $card->print_status === 'printed' ? 'Mark not printed' : 'Mark printed' }}</button></td></tr>
        </table>
      </div></div>

      @if($p || $card->status === 'deactivated')
      <div class="card mb-3 border-danger-subtle"><div class="card-body pt-3">
        <div class="fw-bold mb-2 text-danger"><i class="bi bi-shield-exclamation"></i> Lost, damaged or changes</div>
        @if(in_array($card->status, ['assigned', 'active'], true))
        <div class="row g-2 align-items-end mb-2">
          <div class="col-7"><label class="form-label small mb-1">Reason</label><select class="form-select form-select-sm" id="nf-reason"><option value="lost">Lost</option><option value="damaged">Damaged</option><option value="deactivated">Other (switch off)</option></select></div>
          <div class="col-5"><button class="btn btn-sm btn-outline-danger w-100" data-act="deactivate"><i class="bi bi-power"></i> Switch off</button></div>
        </div>
        <button class="btn btn-sm btn-warning w-100 mb-2" data-act="replace"><i class="bi bi-arrow-repeat"></i> Replace card (switch this off + new card for the same person)</button>
        <div class="small text-muted">{{ $stock }} blank {{ \Illuminate\Support\Str::plural('card', $stock) }} in stock; a new one is added automatically if needed.</div>
        @endif
        @if($card->status === 'assigned')<button class="btn btn-sm btn-outline-secondary w-100 mt-2" data-act="unassign"><i class="bi bi-box-arrow-in-left"></i> Unassign (back to stock)</button>@endif
        @if($card->status === 'deactivated')
        <div class="small text-muted mb-2">Deactivated cards can be reassigned to someone else — no need to rewrite the chip (it stores the card's own link).</div>
        <button class="btn btn-sm btn-outline-secondary w-100 mb-2" data-act="unassign"><i class="bi bi-box-arrow-in-left"></i> Back to stock</button>
        @if($p && $card->encode_status === 'encoded')<button class="btn btn-sm btn-outline-success w-100" data-act="activate"><i class="bi bi-check-circle"></i> Re-activate for {{ $p->name }}</button>@endif
        @endif
        @if(in_array($card->status, ['lost', 'damaged'], true))<div class="small text-muted">This card is {{ $card->status }} and stays switched off. Its history is kept.</div>@endif
      </div></div>
      @endif

      <div class="card"><div class="card-body pt-3">
        <div class="fw-bold mb-2"><i class="bi bi-chat-left-text"></i> Note</div>
        <textarea class="form-control form-control-sm mb-2" id="nf-note" rows="2" maxlength="500" placeholder="e.g. Given at desk 2">{{ $card->notes }}</textarea>
        <button class="btn btn-sm btn-outline-primary" data-act="note">Save note</button>
      </div></div>
    </div>
  </div>
</section>
@endsection

@include('backend.pages.nfc.webnfc')

@push('scripts')
<script>
(function () {
  'use strict';
  var base = @json(url('/nfc-cards/' . $card->id));
  var cardUrl = @json($card->url());
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
  function act(action, body, confirmText) {
    var go = function () {
      return fetch(base + '/' + action, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(body || {}) })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
        .then(function (res) {
          if (!res.ok) { var e = res.d.errors || {}; var k = Object.keys(e)[0]; Swal.fire('Not done', k ? e[k][0] : (res.d.message || 'Please try again.'), 'warning'); return false; }
          Swal.fire({ icon: 'success', title: res.d.message, timer: 1400, showConfirmButton: false }).then(function () { location.href = res.d.redirect || location.href; });
          return true;
        });
    };
    if (!confirmText) return go();
    return Swal.fire({ icon: 'question', title: confirmText, showCancelButton: true, confirmButtonText: 'Yes' }).then(function (r) { if (r.isConfirmed) return go(); });
  }

  // Show the Web NFC buttons only where the browser supports them
  document.querySelectorAll('.webnfc-yes').forEach(function (el) { el.classList.toggle('d-none', !BicsNfc.supported); });
  document.querySelectorAll('.webnfc-no').forEach(function (el) { el.classList.toggle('d-none', BicsNfc.supported); });
  document.querySelectorAll('[data-cancel]').forEach(function (b) { b.addEventListener('click', function () { BicsNfc.cancel(); document.querySelectorAll('.nf-live').forEach(function (l) { l.classList.remove('show'); }); }); });

  var write = document.getElementById('nf-write');
  if (write) write.addEventListener('click', function () {
    var live = document.getElementById('nf-live-write'); live.classList.add('show'); write.disabled = true;
    BicsNfc.write(cardUrl, { lock: document.getElementById('nf-lock').checked })
      .then(function (r) { live.classList.remove('show'); return act('encoded', { uid: r.uid, locked: document.getElementById('nf-lock').checked, method: 'web_nfc' }); })
      .catch(function (e) {
        live.classList.remove('show'); write.disabled = false;
        if (e && e.name === 'AbortError') return;
        fetch(base + '/encode_failed', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ error: BicsNfc.explain(e) }) });
        Swal.fire('Not written', BicsNfc.explain(e), 'error');
      });
  });
  var verify = document.getElementById('nf-verify');
  if (verify) verify.addEventListener('click', function () {
    var live = document.getElementById('nf-live-read'); live.classList.add('show');
    BicsNfc.read().then(function (r) { live.classList.remove('show'); act('verify', { read_url: r.url || '', uid: r.uid }); })
      .catch(function (e) { live.classList.remove('show'); if (e && e.name !== 'AbortError') Swal.fire('Not read', BicsNfc.explain(e), 'error'); });
  });
  var manual = document.getElementById('nf-verify-manual');
  if (manual) manual.addEventListener('click', function () { act('verify', { manual: true }, 'Confirm: tapping the card opened ' + @json($card->card_no) + '?'); });
  var marked = document.getElementById('nf-mark-encoded');
  if (marked) marked.addEventListener('click', function () { act('encoded', { uid: document.getElementById('nf-uid').value, method: 'external' }, 'Confirm the link was written to the chip?'); });
  var copy = document.getElementById('nf-copy');
  if (copy) copy.addEventListener('click', function () { navigator.clipboard.writeText(cardUrl).then(function () { Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Link copied', showConfirmButton: false, timer: 1500 }); }); });

  document.querySelectorAll('[data-act]').forEach(function (b) {
    b.addEventListener('click', function () {
      var a = b.dataset.act;
      if (a === 'activate') return act('activate', {});
      if (a === 'deactivate') { var r = document.getElementById('nf-reason').value; return act('deactivate', { reason: r }, 'Switch this card off (' + r + ')? Tapping it will show "no longer active".'); }
      if (a === 'replace') { var rr = document.getElementById('nf-reason').value; return act('replace', { reason: rr }, 'Switch this card off (' + rr + ') and give the same person a new card?'); }
      if (a === 'unassign') return act('unassign', {}, 'Put this card back in stock?');
      if (a === 'printed') return act('printed', { printed: b.dataset.printed === '1' });
      if (a === 'note') return act('note', { note: document.getElementById('nf-note').value });
    });
  });

  // Assign: participant search
  var find = document.getElementById('nf-find'), results = document.getElementById('nf-results'), t = null;
  if (find) find.addEventListener('input', function () {
    clearTimeout(t); var q = find.value.trim();
    if (q.length < 2) { results.classList.add('d-none'); return; }
    t = setTimeout(function () {
      fetch(@json(route('nfc.participants')) + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (list) {
        results.innerHTML = list.map(function (p) {
          return '<button type="button" class="list-group-item list-group-item-action" data-pid="' + p.id + '" ' + (p.card ? 'disabled' : '') + '><b>' + esc(p.name) + '</b> <span class="small text-muted">' + esc(p.code) + (p.organization ? ' · ' + esc(p.organization) : '') + '</span>' +
            (p.card ? '<div class="small text-warning-emphasis">Has card ' + esc(p.card.card_no) + ' — use Replace on that card</div>' : '') + '</button>';
        }).join('') || '<div class="list-group-item small text-muted">No active participant found.</div>';
        results.classList.remove('d-none');
        results.querySelectorAll('[data-pid]').forEach(function (b) { b.addEventListener('click', function () { act('assign', { participant_id: +b.dataset.pid }); }); });
      });
    }, 250);
  });
})();
</script>
@endpush
