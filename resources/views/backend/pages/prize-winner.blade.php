@extends('backend.layouts.master')
@section('title')
Prize Winners
@endsection
@push('styles')
<style>
  .pw-chip { display: inline-block; font-size: 11.5px; font-weight: 700; padding: 3px 9px; border-radius: 999px; background: rgba(24,97,197,.1); color: #1861c5; }
  .pw-events { display: flex; flex-wrap: wrap; gap: 8px; }
  .pw-ev { border: 1px solid rgba(0,0,0,.1); background: var(--bs-body-bg, #fff); border-radius: 999px; padding: 7px 14px; font-weight: 600; font-size: 14px; cursor: pointer; }
  .pw-ev span { display: inline-block; min-width: 22px; margin-left: 6px; padding: 0 7px; border-radius: 999px; background: rgba(0,0,0,.06); font-size: 12px; }
  .pw-ev.is-active { background: #1861c5; border-color: #1861c5; color: #fff; }
  .pw-ev.is-active span { background: rgba(255,255,255,.25); }
  .pw-mailbar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 16px; padding: 12px 16px; border-radius: 12px; background: rgba(24,97,197,.05); border: 1px solid rgba(24,97,197,.15); }
  .pw-prize { font-weight: 700; }
  .pw-prize small { display: block; font-weight: 400; color: var(--ad-muted, #6b7489); }
  .pw-sort { cursor: pointer; user-select: none; white-space: nowrap; }
  .pw-sort i { opacity: .35; }
  .pw-sort.is-on i { opacity: 1; }
</style>
@endpush
@section('content')
<div class="pagetitle d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div>
    <h1>Prize Winners</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li><li class="breadcrumb-item"><a href="{{ route('addPrize') }}">Raffle Setup</a></li><li class="breadcrumb-item active">Prize Winners</li></ol></nav>
  </div>
  <div class="d-flex gap-2">
    <a href="{{ route('addPrize') }}" class="btn btn-outline-secondary" id="pw-setup"><i class="bi bi-gear"></i> Raffle setup</a>
    <a href="{{ route('prize.winner.export') }}" class="btn btn-outline-primary" id="pw-export"><i class="bi bi-filetype-csv"></i> Export CSV</a>
  </div>
</div>

<section class="section">
  <div class="card">
    <div class="card-header text-white fw-bold" style="background:#1861c5"><i class="bi bi-trophy"></i> PRIZE INFORMATION</div>
    <div class="card-body pt-3">
      <div class="small fw-semibold text-muted mb-2">Event / venue</div>
      <div class="pw-events mb-3" id="pw-events" role="tablist"></div>

      <div class="pw-mailbar mb-3" id="pw-mailbar" style="display:none">
        <span class="fw-semibold"><i class="bi bi-envelope-heart"></i> Winner emails for <span id="pw-mail-event"></span></span>
        <div class="btn-group btn-group-sm" role="group" aria-label="Winner email mode">
          @foreach(['off' => ['bi-envelope-slash', 'Off'], 'auto' => ['bi-lightning-charge', 'Automatic'], 'manual' => ['bi-hand-index', 'Manual']] as $k => $m)
          <input type="radio" class="btn-check" name="pw_mode" id="pw-mode-{{ $k }}" value="{{ $k }}" autocomplete="off">
          <label class="btn btn-outline-primary" for="pw-mode-{{ $k }}" title="{{ $modes[$k] }}"><i class="bi {{ $m[0] }}"></i> {{ $m[1] }}</label>
          @endforeach
        </div>
        <button class="btn btn-sm btn-success" type="button" id="pw-send-pending"><i class="bi bi-send"></i> Email all not yet emailed (<span id="pw-pending">0</span>)</button>
        <a class="small ms-auto" id="pw-edit-text" href="#"><i class="bi bi-pencil"></i> Edit email text</a>
      </div>
      <div class="small text-muted mb-3" id="pw-allnote"><i class="bi bi-info-circle"></i> Choose an event to turn its winner emails on or off and to email winners.</div>

      <div class="d-flex flex-wrap gap-2 justify-content-between mb-2">
        <input type="search" class="form-control form-control-sm" id="pw-search" placeholder="Search winner, number, prize, country…" style="max-width:320px">
        <select class="form-select form-select-sm" id="pw-sort" style="width:auto">
          <option value="date_desc">Newest first</option>
          <option value="date_asc">Oldest first</option>
          <option value="event">Event / venue (A–Z)</option>
          <option value="order">Draw order</option>
          <option value="prize">Prize title (A–Z)</option>
          <option value="name">Winner name (A–Z)</option>
          <option value="country">Country (A–Z)</option>
          <option value="email">Email status</option>
        </select>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th class="pw-sort" data-sort="event">Event / venue <i class="bi bi-arrow-down-up"></i></th>
              <th class="pw-sort" data-sort="prize">Prize <i class="bi bi-arrow-down-up"></i></th>
              <th class="pw-sort" data-sort="name">Winner <i class="bi bi-arrow-down-up"></i></th>
              <th class="pw-sort" data-sort="country">Country <i class="bi bi-arrow-down-up"></i></th>
              <th class="pw-sort d-none d-lg-table-cell" data-sort="date_desc">Drawn <i class="bi bi-arrow-down-up"></i></th>
              <th class="pw-sort" data-sort="email">Email <i class="bi bi-arrow-down-up"></i></th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody id="pw-body"></tbody>
        </table>
      </div>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
  'use strict';
  var events = @json($events);
  var winners = @json($winners);
  var selected = @json($selected);
  var urls = { mode: @json(route('prize.winner.email-mode')), pending: @json(route('prize.winner.email-pending')), one: @json(url('/prize-winner')), setup: @json(route('addPrize')), export: @json(route('prize.winner.export')) };
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2500 });
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
  function post(url, body) {
    return fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(body || {}) })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, d: d }; }); });
  }
  function eventOf(id) { return events.filter(function (e) { return e.id === id; })[0]; }
  function cmp(a, b) { return String(a || '').localeCompare(String(b || ''), undefined, { sensitivity: 'base', numeric: true }); }
  var EMAIL_RANK = function (w) { return w.emailed_at ? 2 : (w.email_error ? 0 : 1); };

  function renderEvents() {
    var total = winners.length;
    var html = '<button type="button" class="pw-ev ' + (!selected ? 'is-active' : '') + '" data-ev="">All events<span>' + total + '</span></button>';
    events.forEach(function (e) {
      if (!e.winners && !e.prizes && !e.current) return;
      html += '<button type="button" class="pw-ev ' + (selected === e.id ? 'is-active' : '') + '" data-ev="' + e.id + '">' + esc(e.short) + (e.venue && e.venue !== e.short ? ' · ' + esc(e.venue) : '') + (e.current ? ' <i class="bi bi-broadcast" title="Current event"></i>' : '') + '<span>' + e.winners + '</span></button>';
    });
    var box = document.getElementById('pw-events');
    box.innerHTML = html;
    box.querySelectorAll('[data-ev]').forEach(function (b) {
      b.addEventListener('click', function () {
        selected = b.dataset.ev ? parseInt(b.dataset.ev, 10) : null;
        history.replaceState(null, '', selected ? '?event=' + selected : location.pathname);
        render();
      });
    });
  }

  function renderMailbar() {
    var ev = selected ? eventOf(selected) : null;
    document.getElementById('pw-mailbar').style.display = ev ? '' : 'none';
    document.getElementById('pw-allnote').style.display = ev ? 'none' : '';
    document.getElementById('pw-export').href = urls.export + (ev ? '?event=' + ev.id : '');
    document.getElementById('pw-setup').href = urls.setup + (ev ? '?event=' + ev.id : '');
    if (!ev) return;
    document.getElementById('pw-mail-event').textContent = ev.short;
    document.querySelectorAll('input[name=pw_mode]').forEach(function (r) { r.checked = r.value === ev.email_mode; });
    var pending = winners.filter(function (w) { return w.event_id === ev.id && !w.emailed_at; }).length;
    document.getElementById('pw-pending').textContent = pending;
    document.getElementById('pw-send-pending').disabled = ev.email_mode === 'off' || !pending;
    document.getElementById('pw-edit-text').href = urls.setup + '?event=' + ev.id + '#winner-email';
  }

  function sorted(list) {
    var s = document.getElementById('pw-sort').value;
    var by = {
      date_desc: function (a, b) { return cmp(b.drawn_at, a.drawn_at); },
      date_asc: function (a, b) { return cmp(a.drawn_at, b.drawn_at); },
      event: function (a, b) { return cmp(a.event, b.event) || cmp(a.order, b.order); },
      order: function (a, b) { return cmp(a.event, b.event) || (a.order || 999) - (b.order || 999); },
      prize: function (a, b) { return cmp(a.prize_type, b.prize_type); },
      name: function (a, b) { return cmp(a.name, b.name); },
      country: function (a, b) { return cmp(a.country, b.country) || cmp(a.name, b.name); },
      email: function (a, b) { return EMAIL_RANK(a) - EMAIL_RANK(b) || cmp(a.event, b.event); }
    }[s];
    document.querySelectorAll('.pw-sort').forEach(function (th) { th.classList.toggle('is-on', th.dataset.sort === s); });
    return list.slice().sort(by);
  }

  function emailCell(w, ev) {
    if (w.emailed_at) return '<span class="text-success small"><i class="bi bi-envelope-check"></i> Sent ' + esc(w.emailed_at) + '</span>';
    if (w.email_error) return '<span class="text-danger small" title="' + esc(w.email_error) + '"><i class="bi bi-envelope-exclamation"></i> Failed</span><div class="small text-muted text-truncate" style="max-width:180px">' + esc(w.email_error) + '</div>';
    if (!w.email) return '<span class="text-muted small"><i class="bi bi-envelope-dash"></i> No email address</span>';
    return '<span class="text-muted small"><i class="bi bi-envelope"></i> Not sent' + (ev && ev.email_mode === 'off' ? ' · emails off' : '') + '</span>';
  }

  function renderTable() {
    var q = document.getElementById('pw-search').value.trim().toLowerCase();
    var rows = sorted(winners.filter(function (w) {
      if (selected && w.event_id !== selected) return false;
      return !q || [w.name, w.number, w.prize, w.prize_type, w.country, w.event, w.venue, w.profession].join(' ').toLowerCase().indexOf(q) > -1;
    }));
    var body = document.getElementById('pw-body');
    body.innerHTML = rows.map(function (w) {
      var ev = eventOf(w.event_id);
      var off = !ev || ev.email_mode === 'off';
      var btn = '<button class="btn btn-sm ' + (w.emailed_at ? 'btn-outline-secondary' : 'btn-outline-success') + '" data-send="' + w.id + '" ' + (off || !w.email ? 'disabled' : '') +
        ' title="' + (off ? 'Winner emails are off for this event' : (!w.email ? 'No email address' : (w.emailed_at ? 'Send the email again' : 'Send the winner email'))) + '"><i class="bi bi-' + (w.emailed_at ? 'arrow-repeat' : 'send') + '"></i> ' + (w.emailed_at ? 'Resend' : 'Send') + '</button>';
      return '<tr>' +
        '<td><div class="fw-semibold">' + esc(w.event) + '</div>' + (w.venue && w.venue !== w.event ? '<span class="pw-chip">' + esc(w.venue) + '</span>' : '') + '</td>' +
        '<td class="pw-prize">' + esc(w.prize_type) + '<small>' + esc(w.prize) + '</small></td>' +
        '<td><div class="fw-semibold">' + esc(w.name) + '</div><div class="small text-muted">#' + esc(w.number) + (w.profession ? ' · ' + esc(w.profession) : '') + '</div></td>' +
        '<td>' + esc(w.country || '—') + '</td>' +
        '<td class="d-none d-lg-table-cell small">' + esc(w.drawn_at || '—') + (w.drawn_by ? '<div class="text-muted">by ' + esc(w.drawn_by) + '</div>' : '') + '</td>' +
        '<td>' + emailCell(w, ev) + '</td>' +
        '<td class="text-end text-nowrap">' + btn + '</td></tr>';
    }).join('') || '<tr><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-trophy fs-3 d-block mb-2"></i>No winners ' + (selected ? 'for this event yet.' : 'yet.') + '</td></tr>';
    body.querySelectorAll('[data-send]').forEach(function (b) {
      b.addEventListener('click', function () {
        var w = winners.filter(function (x) { return String(x.id) === b.dataset.send; })[0];
        Swal.fire({ icon: 'question', title: (w.emailed_at ? 'Send again to ' : 'Email ') + w.name + '?', html: esc(w.prize_type) + ' — ' + esc(w.prize) + '<br><small class="text-muted">' + esc(w.email || '') + '</small>', showCancelButton: true, confirmButtonText: 'Send' })
          .then(function (r) {
            if (!r.isConfirmed) return;
            b.disabled = true;
            post(urls.one + '/' + w.id + '/email').then(function (res) {
              if (res.d.winners) winners = res.d.winners;
              render();
              if (res.ok) toast.fire({ icon: 'success', title: res.d.message }); else Swal.fire('Not sent', res.d.message || 'Please try again.', 'error');
            });
          });
      });
    });
  }

  function render() { renderEvents(); renderMailbar(); renderTable(); }

  document.querySelectorAll('input[name=pw_mode]').forEach(function (r) {
    r.addEventListener('change', function () {
      post(urls.mode, { event_id: selected, mode: r.value }).then(function (res) {
        if (!res.ok) { Swal.fire('Not saved', res.d.message || 'Please try again.', 'error'); return render(); }
        events = res.d.events; winners = res.d.winners; render(); toast.fire({ icon: 'success', title: res.d.message });
      });
    });
  });
  document.getElementById('pw-send-pending').addEventListener('click', function () {
    var ev = eventOf(selected), btn = this;
    var n = document.getElementById('pw-pending').textContent;
    Swal.fire({ icon: 'question', title: 'Email ' + n + ' winner' + (n === '1' ? '' : 's') + '?', text: 'Every ' + ev.short + ' winner who has not received the email yet.', showCancelButton: true, confirmButtonText: 'Send' })
      .then(function (r) {
        if (!r.isConfirmed) return;
        btn.disabled = true;
        post(urls.pending, { event_id: selected }).then(function (res) {
          if (res.d.winners) winners = res.d.winners;
          render();
          if (res.ok) Swal.fire({ icon: 'success', title: 'Done', text: res.d.message }); else Swal.fire('Not sent', res.d.message || 'Please try again.', 'error');
        });
      });
  });
  document.getElementById('pw-search').addEventListener('input', renderTable);
  document.getElementById('pw-sort').addEventListener('change', renderTable);
  document.querySelectorAll('.pw-sort').forEach(function (th) {
    th.addEventListener('click', function () { document.getElementById('pw-sort').value = th.dataset.sort; renderTable(); });
  });
  if (selected && !eventOf(selected)) selected = null;
  render();
})();
</script>
@endpush
