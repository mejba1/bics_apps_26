{{-- Read-only banner for an ended / archived event (App\Support\EventLock). Usage: @include('partials.event-lock', ['event' => $event]) --}}
@php
  $L = \App\Support\EventLock::state($event ?? null);
  $isSuper = auth()->check() && auth()->user()->isSuperAdmin();
@endphp
@if(!empty($L['phase']) && ($L['locked'] || $L['unlocked'] || $L['locksAt']))
@once
@push('styles')
<style>
  .ev-lock { display: flex; gap: 14px; align-items: center; flex-wrap: wrap; border-radius: 16px; padding: 14px 16px; margin-bottom: 16px; border: 1px solid; }
  .ev-lock.is-locked { background: var(--ad-chip); border-color: var(--ad-line); }
  .ev-lock.is-open { background: var(--ad-amber-50); border-color: rgba(194,124,14,.35); }
  .ev-lock.is-soon { background: var(--ad-brand-50); border-color: rgba(14,76,161,.25); }
  .ev-lock .ic { width: 42px; height: 42px; border-radius: 12px; flex: none; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; }
  .ev-lock.is-locked .ic { background: var(--ad-surface); color: var(--ad-muted); } .ev-lock.is-open .ic { background: #fff; color: var(--ad-amber); } .ev-lock.is-soon .ic { background: #fff; color: var(--ad-brand); }
  .ev-lock .tx { flex: 1; min-width: 220px; } .ev-lock .tx b { color: var(--ad-ink); display: block; } .ev-lock .tx span { font-size: 13px; color: var(--ad-muted); }
  /* pages switch their action buttons off with this class */
  .is-readonly [data-lockable] { display: none !important; }
</style>
@endpush
@endonce
<div class="ev-lock {{ $L['locked'] ? 'is-locked' : ($L['unlocked'] ? 'is-open' : 'is-soon') }}" role="status">
  @if($L['locked'])
    <span class="ic"><i class="bi bi-lock-fill"></i></span>
    <div class="tx"><b>{{ $L['reason'] }} This page is read-only.</b>
      <span>Check-in, gifts, the raffle and registration changes are closed. Certificates, prize emails, printing and reports still work.</span></div>
    @if($isSuper)<button type="button" class="btn btn-sm btn-outline-secondary" data-ev-unlock="{{ $L['event']->id }}"><i class="bi bi-unlock"></i> Unlock for {{ \App\Support\EventLock::UNLOCK_HOURS }} hours</button>@endif
  @elseif($L['unlocked'])
    <span class="ic"><i class="bi bi-unlock-fill"></i></span>
    <div class="tx"><b>{{ $L['event']->event_short }} is unlocked for corrections until {{ $L['unlockedUntil'] }}.</b>
      <span>{{ $L['reason'] }} Unlocked by {{ $L['unlockedBy'] ?: 'a Super Admin' }} — changes are possible again for now.</span></div>
    @if($isSuper)<button type="button" class="btn btn-sm btn-outline-secondary" data-ev-lock="{{ $L['event']->id }}"><i class="bi bi-lock"></i> Lock again now</button>@endif
  @else
    <span class="ic"><i class="bi bi-hourglass-split"></i></span>
    <div class="tx"><b>{{ $L['reason'] }} Changes close on {{ $L['locksAt'] }}.</b>
      <span>After that this event becomes read-only. Finish check-in corrections, gifts and the raffle before then.</span></div>
  @endif
</div>
@once
@push('scripts')
<script>
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-ev-unlock],[data-ev-lock]'); if (!b) return;
    var unlock = !!b.dataset.evUnlock, id = b.dataset.evUnlock || b.dataset.evLock;
    Swal.fire({ title: unlock ? 'Unlock this event for {{ \App\Support\EventLock::UNLOCK_HOURS }} hours?' : 'Lock this event again?',
      text: unlock ? 'Check-in, gifts, the raffle and registrations can be changed again until it locks by itself. This is recorded in the audit log.' : 'Its data becomes read-only again right away.',
      icon: 'question', showCancelButton: true, confirmButtonText: unlock ? 'Unlock' : 'Lock' })
      .then(function (r) {
        if (!r.isConfirmed) return;
        fetch(@json(url('/events')) + '/' + id + (unlock ? '/unlock' : '/lock'), { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' } })
          .then(function (x) { return x.json().then(function (d) { return { ok: x.ok, d: d }; }); })
          .then(function (res) { if (!res.ok) return Swal.fire('Not done', res.d.message || 'Please try again.', 'warning'); location.reload(); });
      });
  });
</script>
@endpush
@endonce
@endif
