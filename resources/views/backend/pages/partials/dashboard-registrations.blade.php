{{--
  Dashboard › All registrations (one event, or every event). Rendered by JS from $data (rows of "registrations"):
  filters, search, sort, paging — the confirmation-email, ID-card, record-PDF and delete buttons keep their old classes
  (.sendEmail / .deleteRecord) so the existing handlers keep working.
--}}
@php
  $u = auth()->user();
  $ids = $data->pluck('id');
  $ckins = DB::table('checkins as c')->leftJoin('users as u', 'u.id', '=', 'c.user_id')->whereIn('c.registration_id', $ids)->get(['c.registration_id', 'c.created_at', 'c.method', 'u.name as by'])->keyBy('registration_id');
  $gifts = DB::table('gift as g')->leftJoin('users as u', 'u.id', '=', 'g.user_id')->whereIn('g.reg_id', $ids)->get(['g.reg_id', 'g.create_at', 'u.name as by'])->keyBy('reg_id');
  $pcodes = DB::table('participants')->whereIn('id', $data->pluck('participant_id')->filter())->pluck('code', 'id');
  $certs = DB::table('certificates')->whereIn('registration_id', $ids)->get(['registration_id', 'number', 'token', 'emailed_at', 'assigned_at'])->keyBy('registration_id');
  $fmt = fn ($t) => $t ? \Carbon\Carbon::parse($t)->format('j M Y, H:i') : null;
  $regRows = $data->map(function ($r) use ($ckins, $gifts, $pcodes, $certs, $fmt) {
      $c = $ckins[$r->id] ?? null; $g = $gifts[$r->id] ?? null; $ce = $certs[$r->id] ?? null;
      $dob = \App\Support\BirthDate::parse($r->birthday);
      $img = $r->image && $r->image !== 'no-photo.png' && is_file(base_path('upload/images/' . $r->image)) ? \App\Support\Thumb::url('upload/images/' . $r->image, 480) : null;
      return [
          'id' => $r->id, 'name' => trim(($r->title ? $r->title . ' ' : '') . $r->firstname . ' ' . $r->lastname),
          'initials' => mb_strtoupper(mb_substr((string) $r->firstname, 0, 1) . mb_substr((string) $r->lastname, 0, 1)),
          'number' => (string) $r->qr_code, 'role' => $r->designation ?: '', 'profession' => (string) $r->profession, 'specialty' => (string) $r->specialty,
          'country' => (string) $r->country, 'email' => (string) $r->email, 'phone' => trim(($r->phone ?? '')), 'gender' => (string) $r->gender, 'venue' => (string) $r->venue,
          'sent' => $r->email_notification === 'Yes', 'in' => $r->verify_status === 'Yes', 'gift' => $r->gift === 'Approved', 'card' => !empty($r->idcard_assigned_at),
          'status' => (string) $r->status, 'photo' => $img,
          'date' => \Carbon\Carbon::parse($r->created_at)->format('j M Y'), 'ts' => strtotime($r->created_at),
          // details window
          'title' => (string) $r->title, 'first' => (string) $r->firstname, 'last' => (string) $r->lastname, 'photo_big' => $img,
          'dob' => $dob ? \Carbon\Carbon::parse($dob)->format('j F Y') : ($r->birthday ?: null), 'age' => $dob ? \App\Support\BirthDate::age($dob) : null,
          'institute' => (string) $r->institute, 'institute_address' => (string) $r->institute_address, 'institute_phone' => (string) ($r->institute_phone ?? ''),
          'id_type' => (string) $r->identification_type, 'id_no' => (string) $r->identification_no, 'pharmacist' => (string) ($r->pharmacist ?? ''),
          'tracking' => (string) ($r->trackingNo ?? ''), 'onbehalf' => (string) ($r->onbehalf ?? ''), 'registered_at' => $fmt($r->created_at),
          'in_at' => $c ? $fmt($c->created_at) : ($r->verify_status === 'Yes' ? $r->verify_at : null), 'in_how' => $c->method ?? null, 'in_by' => $c->by ?? null,
          'gift_at' => $g ? $fmt($g->create_at) : null, 'gift_by' => $g->by ?? null, 'card_at' => $fmt($r->idcard_assigned_at),
          'pcode' => $r->participant_id ? ($pcodes[$r->participant_id] ?? null) : null,
          'cert' => $ce && $ce->assigned_at ? ['no' => $ce->number, 'url' => url('/certificate/' . $ce->token), 'emailed' => $fmt($ce->emailed_at)] : null,
      ];
  })->values();
  $notSent = $regRows->filter(fn ($r) => !$r['sent'] && $r['email'])->count();
  $rgEvent = $venue !== 'all' ? \App\Models\Event::forVenue($venue) : null;
  // emails, check-in, gifts and deleting: the current event only (or one a Super Admin unlocked)
  $rgLocked = \App\Support\EventLock::isLocked($rgEvent) || ($venue !== 'all' && !\App\Support\EventLock::isCurrentOrUnlocked($rgEvent));
  $openVenues = \App\Support\EventLock::openVenues();
  $curEvent = \App\Models\Event::current();
  $regBoot = ['rows' => $regRows, 'all' => $venue === 'all', 'event' => $rgEvent?->id, 'locked' => $rgLocked, 'open' => $openVenues,
      'can' => ['resend' => $u->can('registrations.resend'), 'export' => $u->can('registrations.export'), 'delete' => $u->can('registrations.delete'),
                'verify' => $u->can('attendees.verify') && $rgEvent && !$rgLocked, 'gift' => $u->can('attendees.gifts') && $rgEvent && !$rgLocked],
      'r' => ['idcard' => url('/registrations/ID/id-card'), 'record' => url('/download-profile-Pdf/ID'), 'send' => url('/registrations/ID/send-email'),
              'del' => url('/delete_register/ID'), 'verify' => route('attendees.bulk', 'verify'), 'gift' => route('attendees.bulk', 'gift'), 'profile' => url('/p/CODE')]];
@endphp
@push('styles')
<style>
  .rg { border-radius: 22px; overflow: hidden; background: var(--ad-surface); border: 1px solid var(--ad-line); box-shadow: 0 1px 2px rgba(16,30,60,.04), 0 18px 40px -30px rgba(14,40,90,.45); margin-bottom: 1.5rem; scroll-margin-top: 80px; }
  .rg-head { position: relative; display: flex; flex-wrap: wrap; gap: 14px 20px; align-items: center; justify-content: space-between; padding: 20px 22px; color: #fff;
    background: radial-gradient(420px 200px at 100% 0%, rgba(178,46,107,.55), transparent 70%), linear-gradient(120deg, #0a2350, #0e4ca1 60%, #1d63c9); }
  .rg-title { display: flex; gap: 14px; align-items: center; min-width: 0; }
  .rg-title .ic { width: 50px; height: 50px; border-radius: 15px; background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.25); display: inline-flex; align-items: center; justify-content: center; font-size: 1.4rem; flex: none; }
  .rg-title h2 { color: #fff; font-size: 1.3rem; font-weight: 800; margin: 0; letter-spacing: -.01em; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
  .rg-title h2 .ev { font-size: .85rem; font-weight: 800; background: #fff; color: #0e4ca1; border-radius: 999px; padding: 3px 11px; letter-spacing: .02em; }
  .rg-title p { margin: 3px 0 0; color: rgba(255,255,255,.8); font-size: 13.5px; }
  .rg-head .btn { border-radius: 11px; font-weight: 600; }
  .rg-head .btn-ghost { background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.3); color: #fff; }
  .rg-head .btn-ghost:hover { background: rgba(255,255,255,.22); color: #fff; }
  .rg-head .btn-white { background: #fff; color: #0e4ca1; border: 0; }
  .rg-bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; padding: 14px 22px; border-bottom: 1px solid var(--ad-line); }
  .rg-search { position: relative; flex: 1 1 260px; max-width: 360px; }
  .rg-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--ad-muted); }
  .rg-search input { padding-left: 36px; border-radius: 12px; height: 40px; }
  .rg-pills { display: flex; flex-wrap: wrap; gap: 6px; }
  .rg-pills button { border: 1px solid var(--ad-line); background: var(--ad-surface); color: var(--ad-text); border-radius: 999px; padding: 6px 12px; font-size: 13px; font-weight: 600; display: inline-flex; gap: 6px; align-items: center; }
  .rg-pills button .n { font-size: 11.5px; background: var(--ad-chip); color: var(--ad-muted); border-radius: 999px; padding: 0 7px; font-weight: 700; }
  .rg-pills button.on { background: var(--ad-ink); border-color: var(--ad-ink); color: #fff; } .rg-pills button.on .n { background: rgba(255,255,255,.2); color: #fff; }
  html[data-theme="dark"] .rg-pills button.on { background: var(--ad-brand); border-color: var(--ad-brand); color: #06132b; }
  .rg-sort { width: auto; border-radius: 12px; height: 40px; }
  .rg-th, .rg-row { display: grid; grid-template-columns: 22px minmax(0, 2.2fr) 96px minmax(0, 1.4fr) minmax(0, 1fr) minmax(0, 1.5fr) 168px; gap: 14px; align-items: center; padding: 12px 22px; }
  .rg-all .rg-th, .rg-all .rg-row { grid-template-columns: 22px minmax(0, 2.2fr) 96px minmax(0, 1.3fr) minmax(0, 1fr) minmax(0, 1.5fr) 168px; }
  .rg-cb { width: 18px; height: 18px; margin: 0; cursor: pointer; }
  .rg-row { cursor: pointer; } .rg-row.is-sel { background: var(--ad-brand-50); }
  .rg-row .rg-person b { transition: color .15s; } .rg-row:hover .rg-person b { color: var(--ad-brand); }
  .rg-bulk { position: sticky; top: 70px; z-index: 6; display: none; align-items: center; gap: 8px; flex-wrap: wrap; margin: 10px 22px 0; padding: 10px 14px; border-radius: 14px;
    background: var(--ad-ink); color: #fff; box-shadow: 0 14px 30px -18px rgba(0,0,0,.6); }
  html[data-theme="dark"] .rg-bulk { background: #1d2a44; }
  .rg-bulk.on { display: flex; } .rg-bulk b { font-size: 14px; } .rg-bulk .sp { flex: 1; }
  .rg-bulk .btn { border-radius: 10px; font-weight: 600; } .rg-bulk .lnk { color: #c9d6ee; text-decoration: none; font-size: 13px; background: none; border: 0; }
  /* details window */
  .rgm .modal-content { border: 0; border-radius: 22px; overflow: hidden; background: var(--ad-surface); }
  .rgm-hero { position: relative; display: flex; gap: 18px; align-items: center; padding: 22px 24px; color: #fff; background: radial-gradient(420px 200px at 100% 0%, rgba(178,46,107,.55), transparent 70%), linear-gradient(120deg, #0a2350, #0e4ca1); }
  .rgm-hero .ph { width: 92px; height: 92px; border-radius: 50%; object-fit: cover; border: 4px solid #fff; background: #fff; flex: none; display: inline-flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 800; color: #0e4ca1; }
  .rgm-hero h3 { color: #fff; font-size: 1.35rem; font-weight: 800; margin: 0 0 4px; }
  .rgm-hero .sub { color: rgba(255,255,255,.85); font-size: 14px; }
  .rgm-hero .num { display: inline-block; margin-top: 8px; font-family: Consolas, monospace; font-weight: 800; background: #fff; color: #0e4ca1; border-radius: 8px; padding: 2px 10px; }
  .rgm-hero .btn-close { position: absolute; top: 14px; right: 14px; width: 34px; height: 34px; padding: 0; border-radius: 10px; opacity: 1; filter: invert(1) grayscale(1) brightness(2); background-color: rgba(0,0,0,.25); background-size: 12px; }
  .rgm-hero .btn-close:hover { background-color: rgba(0,0,0,.4); }
  .rgm-body { padding: 18px 24px 8px; }
  .rgm-sec { font-size: 11.5px; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; color: #b22e6b; margin: 6px 0 8px; display: flex; gap: 8px; align-items: center; }
  .rgm-sec::after { content: ""; flex: 1; border-top: 1px solid var(--ad-line); }
  .rgm-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 18px; margin-bottom: 14px; }
  .rgm-f span { display: block; font-size: 11.5px; font-weight: 700; color: var(--ad-muted); text-transform: uppercase; letter-spacing: .03em; }
  .rgm-f b { color: var(--ad-ink); font-weight: 600; word-break: break-word; font-size: 14px; }
  .rgm-f b.none { color: var(--ad-muted); font-weight: 400; }
  .rgm-steps { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; margin-bottom: 14px; }
  .rgm-step { border: 1px solid var(--ad-line); border-radius: 12px; padding: 10px; }
  .rgm-step i { font-size: 1.1rem; } .rgm-step b { display: block; color: var(--ad-ink); font-size: 13px; margin-top: 2px; } .rgm-step small { color: var(--ad-muted); font-size: 11.5px; display: block; }
  .rgm-step.ok { background: var(--ad-green-50); border-color: transparent; } .rgm-step.ok i { color: var(--ad-green); }
  .rgm-foot { display: flex; flex-wrap: wrap; gap: 8px; justify-content: space-between; align-items: center; padding: 12px 24px 20px; }
  @media (max-width: 575.98px) { .rgm-grid { grid-template-columns: 1fr; } .rgm-steps { grid-template-columns: 1fr 1fr; } .rgm-hero { flex-direction: column; text-align: center; } }
  .rg-th { font-size: 11.5px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--ad-muted); background: var(--ad-bg); border-bottom: 1px solid var(--ad-line); padding-top: 10px; padding-bottom: 10px; }
  .rg-row { border-bottom: 1px solid var(--ad-line-soft); transition: background .15s; }
  .rg-row:hover { background: var(--ad-hover); }
  .rg-person { display: flex; gap: 12px; align-items: center; min-width: 0; }
  .rg-av { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; flex: none; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-size: 14px; background: var(--ad-brand-50); color: var(--ad-brand); }
  img.rg-av { cursor: zoom-in; }
  .rg-person b { color: var(--ad-ink); display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 14.5px; }
  .rg-sub { font-size: 12.5px; color: var(--ad-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; }
  .rg-num { font-family: Consolas, ui-monospace, monospace; font-weight: 800; color: var(--ad-brand); background: var(--ad-brand-50); border-radius: 8px; padding: 3px 8px; font-size: 13px; justify-self: start; }
  .rg-chips { display: flex; flex-wrap: wrap; gap: 4px; }
  .rg-chip { font-size: 11.5px; font-weight: 700; padding: 3px 8px; border-radius: 7px; display: inline-flex; gap: 4px; align-items: center; white-space: nowrap; }
  .rg-chip.ok { background: var(--ad-green-50); color: var(--ad-green); } .rg-chip.warn { background: var(--ad-amber-50); color: var(--ad-amber); }
  .rg-chip.mute { background: var(--ad-chip); color: var(--ad-muted); } .rg-chip.gift { background: rgba(178,46,107,.12); color: #b22e6b; } .rg-chip.card { background: var(--ad-teal-50); color: var(--ad-teal); }
  .rg-acts { display: flex; gap: 4px; justify-content: flex-end; }
  .rg-acts .btn { width: 34px; height: 34px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; }
  .rg-foot { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; padding: 14px 22px; }
  .rg-foot small { color: var(--ad-muted); }
  .rg-pager { display: flex; gap: 4px; align-items: center; }
  .rg-pager button { min-width: 36px; height: 36px; border-radius: 10px; border: 1px solid var(--ad-line); background: var(--ad-surface); color: var(--ad-text); font-weight: 600; font-size: 13px; }
  .rg-pager button.on { background: var(--ad-brand); border-color: var(--ad-brand); color: #fff; }
  .rg-pager button:disabled { opacity: .4; }
  .rg-empty { text-align: center; padding: 44px 10px; color: var(--ad-muted); } .rg-empty .big { font-size: 2.6rem; } .rg-empty b { color: var(--ad-ink); display: block; margin-top: 6px; }
  .rg-hide-md { }
  @media (max-width: 1199.98px) { .rg-th, .rg-row, .rg-all .rg-th, .rg-all .rg-row { grid-template-columns: 22px minmax(0, 2fr) 96px minmax(0, 1.4fr) minmax(0, 1.5fr) 168px; } .rg-col-country { display: none; } }
  @media (max-width: 767.98px) {
    .rg-th { display: none; }
    .rg-row, .rg-all .rg-row { grid-template-columns: 22px minmax(0, 1fr) auto; gap: 8px 12px; padding: 14px 16px; }
    .rg-row .rg-col-prof, .rg-row .rg-col-country { display: none; }
    .rg-row .rg-col-num { grid-row: 1; grid-column: 3; }
    .rg-row .rg-chips, .rg-row .rg-acts { grid-column: 2 / -1 !important; }
    .rg-bulk { margin: 10px 16px 0; }
    .rg-row .rg-chips { grid-column: 1 / -1; }
    .rg-row .rg-acts { grid-column: 1 / -1; justify-content: flex-start; }
    .rg-head, .rg-bar, .rg-foot { padding-left: 16px; padding-right: 16px; }
  }
</style>
@endpush

<section class="rg {{ $venue === 'all' ? 'rg-all' : '' }}" id="all-registrations" aria-labelledby="rg-title">
  <div class="rg-head">
    <div class="rg-title">
      <span class="ic"><i class="bi bi-people-fill"></i></span>
      <div>
        <h2 id="rg-title">All registrations <span class="ev">{{ $label }}</span>
          @if($venue !== 'all' && $rgLocked)<span class="ev" style="background:rgba(255,255,255,.16);color:#fff;border:1px solid rgba(255,255,255,.35)" title="Emails, check-in, gifts and changes are only possible for the current event{{ $curEvent ? ' (' . $curEvent->event_short . ')' : '' }}"><i class="bi bi-eye"></i> Previous event · view only</span>@endif</h2>
        <p><b id="rg-total">{{ number_format($regRows->count()) }}</b> {{ \Illuminate\Support\Str::plural('person', $regRows->count()) }} registered @if($venue !== 'all')· {{ $regRows->where('in', true)->count() }} checked in @endif</p>
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      @can('registrations.resend')
        @if($venue !== 'all' && $notSent && !$rgLocked)
          <button type="button" data-lockable class="btn btn-sm btn-ghost" id="sendPending" data-venue="{{ $venue }}" data-count="{{ $notSent }}"><i class="bi bi-envelope-exclamation"></i> Email {{ $notSent }} not sent</button>
        @endif
      @endcan
      @can('registrations.export')
        <a href="{{ $venue === 'all' ? route('createPdf') : route('createPdfByVenue', ['venue' => $venue]) }}" class="btn btn-sm btn-white"><i class="bi bi-file-earmark-pdf"></i> PDF report</a>
      @endcan
      @can('registrations.create')<a href="{{ route('registrations.create') }}" class="btn btn-sm btn-ghost"><i class="bi bi-person-plus"></i> Add</a>@endcan
    </div>
  </div>

  <div class="rg-bar">
    <div class="d-flex flex-wrap gap-2 align-items-center flex-grow-1">
      <label class="rg-search"><i class="bi bi-search"></i><input type="search" class="form-control" id="rg-q" placeholder="Search name, reg. no., email, phone, country…" autocomplete="off" aria-label="Search registrations"></label>
      <div class="rg-pills" role="group" aria-label="Show">
        <button type="button" class="on" data-rf="">All <span class="n" data-rn=""></span></button>
        <button type="button" data-rf="in">Checked in <span class="n" data-rn="in"></span></button>
        <button type="button" data-rf="out">Not checked in <span class="n" data-rn="out"></span></button>
        <button type="button" data-rf="unsent">Email not sent <span class="n" data-rn="unsent"></span></button>
        <button type="button" data-rf="gift">Gift given <span class="n" data-rn="gift"></span></button>
      </div>
    </div>
    <select class="form-select form-select-sm rg-sort" id="rg-sort" aria-label="Sort">
      <option value="new">Newest first</option><option value="old">Oldest first</option><option value="name">Name A–Z</option><option value="num">Registration no.</option><option value="country">Country</option>
    </select>
  </div>

  <div class="rg-bulk" id="rg-bulk" role="region" aria-label="Selected registrations">
    <i class="bi bi-check2-square"></i><b><span id="rg-sel-n">0</span> selected</b>
    <button type="button" class="lnk" id="rg-sel-page">Select this page</button><button type="button" class="lnk" id="rg-sel-all">Select all <span id="rg-sel-all-n"></span></button><button type="button" class="lnk" id="rg-sel-clear">Clear</button>
    <span class="sp"></span>
    <button type="button" class="btn btn-sm btn-success d-none" data-bulk="checkin"><i class="bi bi-check2-circle"></i> Check in</button>
    <button type="button" class="btn btn-sm d-none" style="background:#b22e6b;color:#fff" data-bulk="give"><i class="bi bi-gift"></i> Give gifts</button>
    <button type="button" class="btn btn-sm btn-light d-none" data-bulk="email"><i class="bi bi-envelope"></i> Send emails</button>
    <button type="button" class="btn btn-sm btn-light" data-bulk="csv"><i class="bi bi-filetype-csv"></i> Export CSV</button>
    <button type="button" class="btn btn-sm btn-danger d-none" data-bulk="delete"><i class="bi bi-trash"></i> Delete</button>
  </div>

  <div class="rg-th" aria-hidden="true">
    <span><input type="checkbox" class="form-check-input rg-cb" id="rg-all-cb" aria-label="Select all on this page" title="Select all on this page"></span><span>Attendee</span><span>Reg. no.</span><span class="rg-col-prof">Profession</span><span class="rg-col-country">Country</span><span>Status</span><span class="text-end">Actions</span>
  </div>
  <div id="rg-list" role="list"></div>

  <div class="rg-foot">
    <small id="rg-info"></small>
    <div class="d-flex align-items-center gap-2">
      <select class="form-select form-select-sm" id="rg-per" style="width:auto" aria-label="Rows per page"><option>10</option><option selected>25</option><option>50</option><option>100</option></select>
      <div class="rg-pager" id="rg-pager"></div>
    </div>
  </div>
</section>

{{-- details window --}}
<div class="modal fade rgm" id="rgModal" tabindex="-1" aria-labelledby="rgm-name" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
    <div class="rgm-hero"><span id="rgm-photo"></span><div style="min-width:0"><h3 id="rgm-name"></h3><div class="sub" id="rgm-sub"></div><span class="num" id="rgm-num"></span></div>
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body rgm-body" id="rgm-body"></div>
    <div class="rgm-foot">
      <div class="d-flex gap-2"><button type="button" class="btn btn-sm btn-outline-secondary" id="rgm-prev" aria-label="Previous person"><i class="bi bi-chevron-left"></i></button><button type="button" class="btn btn-sm btn-outline-secondary" id="rgm-next" aria-label="Next person"><i class="bi bi-chevron-right"></i></button><span class="small text-muted align-self-center" id="rgm-pos"></span></div>
      <div class="d-flex gap-2 flex-wrap" id="rgm-acts"></div>
    </div>
  </div></div>
</div>

@push('scripts')
<script>
(function () {
  'use strict';
  var B = @json($regBoot), rows = B.rows, filter = '', page = 1, sel = {}, shownNow = [];
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  function $(id) { return document.getElementById(id); }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  var tests = { '': function () { return true; }, in: function (r) { return r.in; }, out: function (r) { return !r.in; }, unsent: function (r) { return !r.sent && r.email; }, gift: function (r) { return r.gift; } };
  function counts() { document.querySelectorAll('[data-rn]').forEach(function (n) { n.textContent = rows.filter(tests[n.dataset.rn]).length; }); }
  function list() {
    var q = $('rg-q').value.trim().toLowerCase(), s = $('rg-sort').value;
    var l = rows.filter(tests[filter]).filter(function (r) { return !q || [r.name, r.number, r.email, r.phone, r.country, r.profession, r.role].join(' ').toLowerCase().indexOf(q) > -1; });
    var by = { new: function (a, b) { return b.ts - a.ts; }, old: function (a, b) { return a.ts - b.ts; }, name: function (a, b) { return a.name.localeCompare(b.name); },
      num: function (a, b) { return a.number.localeCompare(b.number, undefined, { numeric: true }); }, country: function (a, b) { return a.country.localeCompare(b.country) || a.name.localeCompare(b.name); } }[s];
    return l.sort(by);
  }
  function chips(r) {
    return '<div class="rg-chips">' +
      (r.in ? '<span class="rg-chip ok"><i class="bi bi-patch-check-fill"></i> Checked in</span>' : '<span class="rg-chip mute">Registered</span>') +
      (r.email ? (r.sent ? '<span class="rg-chip ok" data-email-status="' + r.id + '"><i class="bi bi-envelope-check"></i> Email sent</span>' : '<span class="rg-chip warn" data-email-status="' + r.id + '"><i class="bi bi-envelope-exclamation"></i> Not sent</span>') : '<span class="rg-chip mute">No email</span>') +
      (r.gift ? '<span class="rg-chip gift"><i class="bi bi-gift-fill"></i> Gift</span>' : '') +
      (r.card ? '<span class="rg-chip card"><i class="bi bi-person-badge"></i> ID card</span>' : '') +
      (r.status && r.status !== 'Approved' ? '<span class="rg-chip warn">' + esc(r.status) + '</span>' : '') + '</div>';
  }
  function rowOpen(r) { return B.all ? B.open.indexOf(r.venue) > -1 : !B.locked; }
  function acts(r) {
    var h = '', open = rowOpen(r);
    if (B.can.resend && open) h += '<button type="button" data-lockable class="btn btn-sm btn-outline-success sendEmail" data-id="' + r.id + '" data-email="' + esc(r.email) + '" data-name="' + esc(r.name) + '" data-sent="' + (r.sent ? 1 : 0) + '" title="' + (r.sent ? 'Send the confirmation email again' : 'Send the confirmation email') + '" aria-label="Email ' + esc(r.name) + '"><i class="bi bi-envelope"></i></button>';
    if (B.can.export) h += '<a href="' + B.r.idcard.replace('ID', r.id) + '" target="_blank" class="btn btn-sm btn-outline-secondary" title="ID card" aria-label="ID card of ' + esc(r.name) + '"><i class="bi bi-person-badge"></i></a>' +
      '<a href="' + B.r.record.replace('ID', r.id) + '" class="btn btn-sm btn-outline-primary" title="Attendee record (PDF)" aria-label="Record of ' + esc(r.name) + '"><i class="bi bi-file-earmark-person"></i></a>';
    if (B.can.delete && open) h += '<button data-lockable class="btn btn-sm btn-outline-danger deleteRecord" data-id="' + r.id + '" data-name="' + esc(r.name) + '" title="Delete" aria-label="Delete ' + esc(r.name) + '"><i class="bi bi-trash"></i></button>';
    return '<div class="rg-acts">' + h + '</div>';
  }
  function row(r) {
    var pic = r.photo ? '<img class="rg-av" src="' + esc(r.photo) + '" alt="" loading="lazy" data-zoom="' + esc(r.photo) + '">' : '<span class="rg-av">' + esc(r.initials) + '</span>';
    return '<div class="rg-row' + (sel[r.id] ? ' is-sel' : '') + '" role="listitem" data-open="' + r.id + '"><input type="checkbox" class="form-check-input rg-cb" data-sel="' + r.id + '"' + (sel[r.id] ? ' checked' : '') + ' aria-label="Select ' + esc(r.name) + '">' +
      '<div class="rg-person">' + pic + '<div style="min-width:0"><b>' + esc(r.name) + '</b><span class="rg-sub" data-email-cell="' + r.id + '">' + esc(r.email || r.role || '—') + '</span>' +
        (B.all ? '<span class="rg-sub">' + esc(r.venue) + ' · ' + esc(r.date) + '</span>' : '<span class="rg-sub">Registered ' + esc(r.date) + '</span>') + '</div></div>' +
      '<span class="rg-num rg-col-num">' + esc(r.number) + '</span>' +
      '<div class="rg-col-prof" style="min-width:0"><span class="rg-sub" style="color:var(--ad-ink);font-weight:600">' + esc(r.profession || '—') + '</span><span class="rg-sub">' + esc(r.specialty || r.role || '') + '</span></div>' +
      '<div class="rg-col-country" style="min-width:0"><span class="rg-sub" style="color:var(--ad-ink)">' + esc(r.country || '—') + '</span><span class="rg-sub">' + esc(r.phone || '') + '</span></div>' +
      chips(r) + acts(r) + '</div>';
  }
  function render() {
    var l = list(), per = +$('rg-per').value, pages = Math.max(1, Math.ceil(l.length / per));
    page = Math.min(page, pages);
    var from = (page - 1) * per, slice = l.slice(from, from + per);
    shownNow = slice;
    $('rg-list').innerHTML = slice.length ? slice.map(row).join('')
      : '<div class="rg-empty"><div class="big">' + (rows.length ? '🔍' : '📭') + '</div><b>' + (rows.length ? 'Nobody matches' : 'No registrations yet') + '</b>' + (rows.length ? 'Try another search or filter.' : 'New registrations appear here as they come in.') + '</div>';
    $('rg-info').textContent = l.length ? 'Showing ' + (from + 1) + '–' + (from + slice.length) + ' of ' + l.length + (l.length !== rows.length ? ' (filtered from ' + rows.length + ')' : '') : '';
    var p = $('rg-pager'), h = '<button type="button" data-pg="' + (page - 1) + '"' + (page <= 1 ? ' disabled' : '') + ' aria-label="Previous page"><i class="bi bi-chevron-left"></i></button>';
    var lo = Math.max(1, page - 2), hi = Math.min(pages, lo + 4); lo = Math.max(1, hi - 4);
    for (var i = lo; i <= hi; i++) h += '<button type="button" data-pg="' + i + '" class="' + (i === page ? 'on' : '') + '">' + i + '</button>';
    h += '<button type="button" data-pg="' + (page + 1) + '"' + (page >= pages ? ' disabled' : '') + ' aria-label="Next page"><i class="bi bi-chevron-right"></i></button>';
    p.innerHTML = h;
    syncSel();
  }

  /* ---------- selection ---------- */
  function picked() { return rows.filter(function (r) { return sel[r.id]; }); }
  function syncSel() {
    var n = Object.keys(sel).length, l = list();
    $('rg-bulk').classList.toggle('on', n > 0);
    $('rg-sel-n').textContent = n;
    $('rg-sel-all-n').textContent = '(' + l.length + ')';
    $('rg-sel-all').classList.toggle('d-none', n >= l.length);
    var k = shownNow.filter(function (r) { return sel[r.id]; }).length, cb = $('rg-all-cb');
    cb.checked = shownNow.length > 0 && k === shownNow.length; cb.indeterminate = k > 0 && k < shownNow.length;
    var ps = picked();
    function show(name, on, count) { var b = document.querySelector('[data-bulk="' + name + '"]'); b.classList.toggle('d-none', !on); if (count !== undefined) { b.disabled = !count; } }
    show('checkin', B.can.verify, ps.filter(function (r) { return !r.in; }).length);
    show('give', B.can.gift, ps.filter(function (r) { return !r.gift; }).length);
    var openPs = ps.filter(rowOpen);
    show('email', B.can.resend && (B.all ? openPs.length > 0 || !ps.length : !B.locked), openPs.filter(function (r) { return r.email; }).length);
    show('delete', B.can.delete && (B.all ? openPs.length > 0 || !ps.length : !B.locked), openPs.length);
  }
  $('rg-all-cb').addEventListener('change', function () { var on = this.checked; shownNow.forEach(function (r) { if (on) sel[r.id] = 1; else delete sel[r.id]; }); render(); });
  $('rg-sel-page').addEventListener('click', function () { shownNow.forEach(function (r) { sel[r.id] = 1; }); render(); });
  $('rg-sel-all').addEventListener('click', function () { list().forEach(function (r) { sel[r.id] = 1; }); render(); });
  $('rg-sel-clear').addEventListener('click', function () { sel = {}; render(); });
  function toast(m, i) { Swal.fire({ toast: true, position: 'top-end', icon: i || 'success', title: m, showConfirmButton: false, timer: 3000 }); }
  function req(url, method, body) {
    return fetch(url, { method: method, headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : null })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, d: d }; }); });
  }
  function confirmList(title, people, btn, danger) {
    return Swal.fire({ title: title, html: people.slice(0, 8).map(function (r) { return esc(r.name); }).join('<br>') + (people.length > 8 ? '<br>… and ' + (people.length - 8) + ' more' : ''),
      icon: danger ? 'warning' : 'question', showCancelButton: true, confirmButtonText: btn, confirmButtonColor: danger ? '#d13c4b' : undefined });
  }
  /** one request per person, with a progress bar; resolves with [ok, failed] */
  function oneByOne(people, title, call) {
    var ok = 0, bad = [], i = 0;
    Swal.fire({ title: title, html: '<div class="progress" style="height:10px"><div class="progress-bar" id="rg-pb" style="width:0"></div></div><div class="small mt-2" id="rg-pn">0 of ' + people.length + '</div>', allowOutsideClick: false, showConfirmButton: false });
    function next() {
      if (i >= people.length) return Promise.resolve([ok, bad]);
      var r = people[i++];
      return call(r).then(function (res) { if (res.ok) ok++; else bad.push(r.name + ': ' + (res.d.message || 'failed')); }, function () { bad.push(r.name + ': no connection'); })
        .then(function () { var pb = document.getElementById('rg-pb'); if (pb) pb.style.width = Math.round(i / people.length * 100) + '%'; var pn = document.getElementById('rg-pn'); if (pn) pn.textContent = i + ' of ' + people.length; return next(); });
    }
    return next();
  }
  function csv(people) {
    var cols = [['Reg. no.', 'number'], ['Title', 'title'], ['First name', 'first'], ['Last name', 'last'], ['Email', 'email'], ['Phone', 'phone'], ['Country', 'country'], ['Gender', 'gender'],
      ['Date of birth', 'dob'], ['Profession', 'profession'], ['Speciality', 'specialty'], ['Designation', 'role'], ['Institution', 'institute'], ['Institution address', 'institute_address'],
      ['ID type', 'id_type'], ['ID number', 'id_no'], ['Event', 'venue'], ['Registered', 'registered_at'], ['Status', 'status'], ['Email sent', 'sent'], ['Checked in', 'in_at'], ['Gift', 'gift_at'], ['Participant ID', 'pcode']];
    var q = function (v) { v = v === true ? 'Yes' : (v === false ? 'No' : (v == null ? '' : String(v))); return '"' + v.replace(/"/g, '""') + '"'; };
    var text = '\ufeff' + cols.map(function (c) { return q(c[0]); }).join(',') + '\r\n' + people.map(function (r) { return cols.map(function (c) { return q(r[c[1]]); }).join(','); }).join('\r\n');
    var a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([text], { type: 'text/csv;charset=utf-8' }));
    a.download = 'registrations-' + (B.all ? 'all' : (rows[0] && rows[0].venue || 'event')) + '-' + people.length + '.csv'; document.body.appendChild(a); a.click(); setTimeout(function () { a.remove(); }, 1000);
  }
  document.querySelectorAll('[data-bulk]').forEach(function (b) {
    b.addEventListener('click', function () {
      var ps = picked(), act = b.dataset.bulk;
      if (act === 'csv') { csv(ps); return toast(ps.length + ' rows exported'); }
      if (act === 'checkin' || act === 'give') {
        var todo = ps.filter(function (r) { return act === 'checkin' ? !r.in : !r.gift; });
        return confirmList((act === 'checkin' ? 'Check in ' : 'Give gifts to ') + todo.length + ' ' + (todo.length === 1 ? 'person' : 'people') + '?', todo, act === 'checkin' ? 'Check in' : 'Give gifts').then(function (x) {
          if (!x.isConfirmed) return;
          req(act === 'checkin' ? B.r.verify : B.r.gift, 'POST', { event: B.event, ids: todo.map(function (r) { return r.id; }), action: act }).then(function (res) {
            if (!res.ok) return Swal.fire('Not done', res.d.message || 'Please try again.', 'warning');
            var done = {}; (res.d.ids || []).forEach(function (id) { done[id] = 1; });
            todo.forEach(function (r) { if (!done[r.id]) return; if (act === 'checkin') { r.in = true; r.in_at = r.in_at || 'just now'; r.in_how = r.in_how || 'desk'; } else { r.gift = true; r.gift_at = r.gift_at || 'just now'; } });
            sel = {}; counts(); render(); toast(res.d.message);
          });
        });
      }
      if (act === 'email') {
        var withMail = ps.filter(function (r) { return r.email && rowOpen(r); });
        return confirmList('Send the confirmation email to ' + withMail.length + ' ' + (withMail.length === 1 ? 'person' : 'people') + '?', withMail, 'Send').then(function (x) {
          if (!x.isConfirmed) return;
          oneByOne(withMail, 'Sending emails…', function (r) { return req(B.r.send.replace('ID', r.id), 'POST', { email: r.email }).then(function (res) { if (res.ok) r.sent = true; return res; }); }).then(function (out) {
            sel = {}; counts(); render();
            Swal.fire(out[1].length ? { icon: 'warning', title: out[0] + ' sent, ' + out[1].length + ' failed', html: out[1].slice(0, 6).map(esc).join('<br>') } : { icon: 'success', title: out[0] + ' emails sent' });
          });
        });
      }
      if (act === 'delete') {
        ps = ps.filter(rowOpen);
        return confirmList('Delete ' + ps.length + ' ' + (ps.length === 1 ? 'registration' : 'registrations') + '?', ps, 'Delete permanently', true).then(function (x) {
          if (!x.isConfirmed) return;
          oneByOne(ps, 'Deleting…', function (r) { return req(B.r.del.replace('ID', r.id), 'DELETE'); }).then(function (out) {
            Swal.fire(out[1].length ? { icon: 'warning', title: out[0] + ' deleted, ' + out[1].length + ' not', html: out[1].slice(0, 6).map(esc).join('<br>') } : { icon: 'success', title: out[0] + ' deleted' })
              .then(function () { location.reload(); });
          });
        });
      }
    });
  });

  /* ---------- details window ---------- */
  var modal = null, openId = null;
  function f(label, v) { return '<div class="rgm-f"><span>' + esc(label) + '</span>' + (v ? '<b>' + esc(v) + '</b>' : '<b class="none">—</b>') + '</div>'; }
  function step(ok, icon, title, text) { return '<div class="rgm-step' + (ok ? ' ok' : '') + '"><i class="bi ' + icon + '"></i><b>' + esc(title) + '</b><small>' + esc(text) + '</small></div>'; }
  function openDetails(id) {
    var r = rows.filter(function (x) { return x.id === id; })[0]; if (!r) return;
    openId = id;
    $('rgm-photo').innerHTML = r.photo_big ? '<img class="ph" src="' + esc(r.photo_big) + '" alt="">' : '<span class="ph">' + esc(r.initials) + '</span>';
    $('rgm-name').textContent = r.name;
    $('rgm-sub').textContent = [r.role, r.institute, r.country].filter(Boolean).join(' · ');
    $('rgm-num').textContent = 'No. ' + r.number;
    var how = { self: 'own phone', kiosk: 'kiosk', desk: 'help desk' }[r.in_how] || '';
    $('rgm-body').innerHTML =
      '<div class="rgm-steps">' +
        step(true, 'bi-person-check-fill', 'Registered', r.registered_at || r.date) +
        step(r.sent, r.sent ? 'bi-envelope-check-fill' : 'bi-envelope', r.sent ? 'Email sent' : 'Email not sent', r.email ? '' : 'no email address') +
        step(r.in, r.in ? 'bi-patch-check-fill' : 'bi-clock', r.in ? 'Checked in' : 'Not checked in', r.in ? [r.in_at, how, r.in_by && 'by ' + r.in_by].filter(Boolean).join(' · ') : '') +
        step(r.gift, 'bi-gift-fill', r.gift ? 'Gift given' : 'No gift yet', r.gift ? [r.gift_at, r.gift_by && 'by ' + r.gift_by].filter(Boolean).join(' · ') : '') +
      '</div>' +
      '<div class="rgm-sec"><i class="bi bi-person"></i> Personal</div><div class="rgm-grid">' +
        f('Title', r.title) + f('Name', r.first + ' ' + r.last) + f('Gender', r.gender) + f('Date of birth', r.dob ? r.dob + (r.age ? ' (' + r.age + ')' : '') : '') + f('Country', r.country) + f('Participant ID', r.pcode) + '</div>' +
      '<div class="rgm-sec"><i class="bi bi-telephone"></i> Contact</div><div class="rgm-grid">' + f('Email', r.email) + f('Mobile', r.phone) + '</div>' +
      '<div class="rgm-sec"><i class="bi bi-briefcase"></i> Professional</div><div class="rgm-grid">' +
        f('Profession', r.profession) + f('Speciality', r.specialty) + f('Designation', r.role) + f('Institution', r.institute) + f('Institution address', r.institute_address) + (r.institute_phone ? f('Institution phone', r.institute_phone) : '') + (r.pharmacist ? f('Pharmacist', r.pharmacist) : '') + '</div>' +
      '<div class="rgm-sec"><i class="bi bi-card-text"></i> Registration</div><div class="rgm-grid">' +
        f('Event', r.venue) + f('Registration no.', r.number) + f('Status', r.status) + f('Registered', r.registered_at) + f('ID document', [r.id_type, r.id_no].filter(Boolean).join(' · ')) +
        f('ID card', r.card ? 'Assigned ' + (r.card_at || '') : 'Not assigned') + f('Certificate', r.cert ? 'No. ' + r.cert.no + (r.cert.emailed ? ' · emailed ' + r.cert.emailed : '') : '') +
        (r.tracking ? f('Tracking no.', r.tracking) : '') + (r.onbehalf ? f('Registered by', r.onbehalf) : '') + '</div>';
    var a = '';
    if (r.pcode) a += '<a class="btn btn-sm btn-outline-secondary" target="_blank" href="' + B.r.profile.replace('CODE', r.pcode) + '"><i class="bi bi-person-vcard"></i> Profile</a>';
    if (r.cert) a += '<a class="btn btn-sm btn-outline-secondary" target="_blank" href="' + esc(r.cert.url) + '"><i class="bi bi-award"></i> Certificate</a>';
    if (B.can.export) a += '<a class="btn btn-sm btn-outline-secondary" target="_blank" href="' + B.r.idcard.replace('ID', r.id) + '"><i class="bi bi-person-badge"></i> ID card</a><a class="btn btn-sm btn-outline-primary" href="' + B.r.record.replace('ID', r.id) + '"><i class="bi bi-file-earmark-person"></i> Record PDF</a>';
    if (B.can.resend && rowOpen(r) && r.email) a += '<button type="button" class="btn btn-sm btn-success sendEmail" data-id="' + r.id + '" data-email="' + esc(r.email) + '" data-name="' + esc(r.name) + '" data-sent="' + (r.sent ? 1 : 0) + '"><i class="bi bi-envelope"></i> ' + (r.sent ? 'Resend email' : 'Send email') + '</button>';
    $('rgm-acts').innerHTML = a;
    var l = list(), at = l.findIndex(function (x) { return x.id === id; });
    $('rgm-prev').disabled = at <= 0; $('rgm-next').disabled = at < 0 || at >= l.length - 1;
    $('rgm-pos').textContent = at > -1 ? (at + 1) + ' of ' + l.length : '';
    modal = modal || bootstrap.Modal.getOrCreateInstance($('rgModal'));
    modal.show();
  }
  function step2(d) { var l = list(), at = l.findIndex(function (x) { return x.id === openId; }); if (l[at + d]) openDetails(l[at + d].id); }
  $('rgm-prev').addEventListener('click', function () { step2(-1); });
  $('rgm-next').addEventListener('click', function () { step2(1); });
  $('rgModal').addEventListener('keydown', function (e) { if (e.key === 'ArrowLeft') step2(-1); if (e.key === 'ArrowRight') step2(1); });
  // an email sent from the window closes it so the confirmation dialog is in front
  $('rgm-acts').addEventListener('click', function (e) { if (e.target.closest('.sendEmail') && modal) modal.hide(); });
  var t; $('rg-q').addEventListener('input', function () { clearTimeout(t); t = setTimeout(function () { page = 1; render(); }, 150); });
  $('rg-sort').addEventListener('change', function () { page = 1; render(); });
  $('rg-per').addEventListener('change', function () { page = 1; render(); });
  $('rg-pager').addEventListener('click', function (e) { var b = e.target.closest('[data-pg]'); if (!b || b.disabled) return; page = +b.dataset.pg; render(); document.getElementById('all-registrations').scrollIntoView({ behavior: 'smooth', block: 'start' }); });
  document.querySelectorAll('[data-rf]').forEach(function (b) { b.addEventListener('click', function () {
    filter = b.dataset.rf; page = 1; document.querySelectorAll('[data-rf]').forEach(function (x) { x.classList.toggle('on', x === b); }); render();
  }); });
  $('rg-list').addEventListener('click', function (e) {
    var z = e.target.closest('[data-zoom]'); if (z) return Swal.fire({ imageUrl: z.dataset.zoom, imageAlt: '', showConfirmButton: false, showCloseButton: true, width: 420 });
    if (e.target.closest('[data-sel], .rg-acts, a, button')) return;   // checkbox and action buttons do their own thing
    var row = e.target.closest('[data-open]'); if (row) openDetails(+row.dataset.open);
  });
  $('rg-list').addEventListener('change', function (e) {
    var c = e.target.closest('[data-sel]'); if (!c) return;
    if (c.checked) sel[c.dataset.sel] = 1; else delete sel[c.dataset.sel];
    c.closest('.rg-row').classList.toggle('is-sel', c.checked); syncSel();
  });
  // the confirmation-email handler (below) tells us when someone was emailed
  window.DbRegs = { markSent: function (id, email) { rows.forEach(function (r) { if (r.id === +id) { r.sent = true; if (email) r.email = email; } }); counts(); render(); } };
  counts(); render();
})();
</script>
@endpush
