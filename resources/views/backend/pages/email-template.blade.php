@extends('backend.layouts.master')
@section('title')
Email Template
@endsection
@push('styles')
<style>
  .et-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 22px; align-items: start; }
  @media (max-width: 1199.98px) { .et-grid { grid-template-columns: 1fr; } }
  .et-card { background: var(--ad-surface); border: 1px solid var(--ad-line); border-radius: 14px; margin-bottom: 18px; }
  .et-card > h2 { font-size: 13px; font-weight: 800; letter-spacing: .6px; text-transform: uppercase; color: var(--ad-brand); margin: 0; padding: 14px 18px; border-bottom: 1px solid var(--ad-line); display: flex; align-items: center; gap: 8px; }
  .et-card > .body { padding: 16px 18px; }
  .et-card label.form-label { font-weight: 600; font-size: 13.5px; margin-bottom: 4px; }
  .et-help { font-size: 12.5px; color: var(--ad-muted); margin-top: 4px; }
  .et-toggle { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--ad-line); }
  .et-toggle:last-child { border-bottom: 0; }
  .et-toggle .t { font-weight: 600; font-size: 14px; }
  .et-toggle .d { font-size: 12.5px; color: var(--ad-muted); }
  .et-toggle .form-switch { margin: 0; min-height: 0; padding-left: 2.8em; }
  .et-toggle .form-check-input { width: 2.6em; height: 1.4em; margin-left: -2.8em; margin-top: 0; cursor: pointer; }
  .et-chips { display: flex; flex-wrap: wrap; gap: 6px; }
  .et-chip { border: 1px solid var(--ad-line); background: var(--ad-chip); color: var(--ad-ink); border-radius: 999px; padding: 3px 10px; font-size: 12.5px; font-family: ui-monospace, Consolas, monospace; cursor: pointer; }
  .et-chip:hover { border-color: var(--ad-brand); color: var(--ad-brand); }
  .et-color { display: flex; align-items: center; gap: 10px; }
  .et-color input[type=color] { width: 46px; height: 38px; padding: 3px; border-radius: 8px; border: 1px solid var(--ad-line); background: var(--ad-input-bg); cursor: pointer; }
  .et-banner { border: 1px solid var(--ad-line); border-radius: 10px; overflow: hidden; background: var(--ad-input-bg); }
  .et-banner img { display: block; width: 100%; max-height: 140px; object-fit: cover; }
  .et-banner .none { padding: 26px; text-align: center; color: var(--ad-muted); font-size: 13px; }
  /* Preview */
  .et-preview { position: sticky; top: 80px; }
  .et-preview .bar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between; margin-bottom: 10px; }
  .et-subject { background: var(--ad-surface); border: 1px solid var(--ad-line); border-radius: 12px; padding: 10px 14px; margin-bottom: 10px; font-size: 13px; color: var(--ad-muted); }
  .et-subject b { color: var(--ad-ink); font-size: 14px; }
  .et-subject .att { margin-top: 6px; display: flex; flex-wrap: wrap; gap: 6px; }
  .et-subject .att span { background: var(--ad-chip); color: var(--ad-ink); border-radius: 6px; padding: 2px 8px; font-size: 12px; }
  .et-frame-wrap { background: #eef2f8; border: 1px solid var(--ad-line); border-radius: 14px; overflow: hidden; display: flex; justify-content: center; }
  .et-frame-wrap iframe { width: 100%; height: calc(100vh - 250px); min-height: 520px; border: 0; background: #eef2f8; transition: width .2s; }
  .et-frame-wrap.mobile iframe { width: 390px; }
  .et-seg { display: inline-flex; border: 1px solid var(--ad-line); border-radius: 10px; overflow: hidden; }
  .et-seg button { border: 0; background: var(--ad-surface); color: var(--ad-muted); padding: 6px 12px; font-size: 13px; font-weight: 600; }
  .et-seg button.on { background: var(--ad-brand); color: #fff; }
  .et-savebar { position: sticky; bottom: 0; z-index: 5; display: flex; gap: 10px; align-items: center; justify-content: space-between; flex-wrap: wrap; background: var(--ad-surface); border: 1px solid var(--ad-line); border-radius: 14px; padding: 12px 16px; box-shadow: var(--ad-shadow-lg); margin-bottom: 18px; }
  .et-dirty { font-size: 13px; color: var(--ad-muted); }
  .et-dirty.on { color: var(--ad-amber); font-weight: 700; }
  /* Confirmation email attachments */
  .ea-intro { color: var(--ad-muted); font-size: 13.5px; max-width: 720px; }
  .ea-total { font-size: 12.5px; font-weight: 600; color: var(--ad-muted); white-space: nowrap; }
  .ea-total .bar { display: inline-block; width: 90px; height: 6px; border-radius: 3px; background: var(--ad-line); vertical-align: middle; margin-left: 6px; overflow: hidden; }
  .ea-total .bar span { display: block; height: 100%; background: var(--ad-green); border-radius: 3px; }
  .ea-total.warn .bar span { background: var(--ad-amber); }
  .ea-list { list-style: none; margin: 0; padding: 0; }
  .ea-item { display: flex; align-items: center; gap: 14px; padding: 12px 14px; border: 1px solid var(--ad-line); border-radius: 10px; background: var(--ad-surface); margin-bottom: 10px; transition: opacity .15s; }
  .ea-item.off { opacity: .6; }
  .ea-item.off .ea-icon { filter: grayscale(1); }
  .ea-icon { flex: 0 0 42px; height: 42px; border-radius: 10px; display: grid; place-items: center; font-size: 20px; background: var(--ad-red-50); color: var(--ad-red); }
  .ea-icon.image { background: var(--ad-teal-50); color: var(--ad-teal); }
  .ea-body { flex: 1 1 auto; min-width: 0; }
  .ea-title { border: 1px solid transparent; background: transparent; color: var(--ad-ink); font-weight: 700; font-size: 15px; padding: 2px 6px; margin-left: -7px; border-radius: 6px; width: 100%; max-width: 360px; }
  .ea-title:hover { border-color: var(--ad-line); }
  .ea-title:focus { border-color: var(--ad-brand); background: var(--ad-input-bg); outline: 0; }
  .ea-meta { font-size: 12.5px; color: var(--ad-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .ea-meta .missing { color: var(--ad-red); font-weight: 700; }
  .ea-actions { display: flex; align-items: center; gap: 4px; flex: 0 0 auto; }
  .ea-actions .btn { width: 34px; height: 34px; padding: 0; display: inline-grid; place-items: center; border-radius: 8px; color: var(--ad-muted); border: 0; }
  .ea-actions .btn:hover { background: var(--ad-hover); color: var(--ad-ink); }
  .ea-actions .btn.del:hover { color: var(--ad-red); background: var(--ad-red-50); }
  .ea-actions .btn:disabled { opacity: .3; }
  .ea-actions .form-switch { margin: 0 6px 0 2px; padding-left: 2.6em; min-height: 0; }
  .ea-actions .form-switch .form-check-input { width: 2.4em; height: 1.3em; cursor: pointer; margin-left: -2.6em; margin-top: 0; }
  .ea-empty { text-align: center; color: var(--ad-muted); padding: 22px; border: 1px dashed var(--ad-line); border-radius: 10px; margin-bottom: 10px; }
  .ea-add { border: 1px dashed var(--ad-line); border-radius: 12px; padding: 14px; }
  .ea-drop { position: relative; display: block; margin: 0; border: 1.5px dashed var(--ad-line); border-radius: 8px; padding: 7px 12px; color: var(--ad-muted); font-size: 13.5px; cursor: pointer; background: var(--ad-input-bg); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .ea-drop.has { color: var(--ad-ink); border-style: solid; border-color: var(--ad-brand); }
  .ea-drop.drag { border-color: var(--ad-brand); background: var(--ad-brand-50); }
  .ea-drop.is-invalid { border-color: var(--ad-red); }
  .ea-drop input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
  .ea-add .invalid-feedback { display: block; }
  @media (max-width: 575.98px) {
    .ea-item { flex-wrap: wrap; gap: 10px 12px; }
    .ea-body { flex: 1 1 calc(100% - 56px); }
    .ea-meta { white-space: normal; }
    .ea-actions { width: 100%; justify-content: flex-end; border-top: 1px solid var(--ad-line); padding-top: 8px; }
  }
</style>
@endpush
@section('content')
<div class="pagetitle">
  <h1>Email Template</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li>
      <li class="breadcrumb-item active">Email Template</li>
    </ol>
  </nav>
</div>

<section class="section">
  <div class="et-grid">
    {{-- Editor --}}
    <div>
      <form id="etForm" novalidate>
        <div class="et-card">
          <h2><i class="bi bi-braces"></i> Placeholders</h2>
          <div class="body">
            <div class="et-help mb-2" style="margin-top:0">Click to insert into the field you are editing. They are replaced with each attendee's details. Use <code>**text**</code> for <b>bold</b>.</div>
            <div class="et-chips">
              @foreach($placeholders as $ph => $desc)<button type="button" class="et-chip" data-ph="{{ $ph }}" title="{{ $desc }}">{{ $ph }}</button>@endforeach
            </div>
          </div>
        </div>

        <div class="et-card">
          <h2><i class="bi bi-envelope"></i> Inbox</h2>
          <div class="body">
            <div class="mb-3">
              <label class="form-label" for="et-subject">Subject</label>
              <input class="form-control" id="et-subject" name="subject" maxlength="200" value="{{ $tpl['subject'] }}" required>
            </div>
            <div>
              <label class="form-label" for="et-preheader">Preview text</label>
              <input class="form-control" id="et-preheader" name="preheader" maxlength="255" value="{{ $tpl['preheader'] }}">
              <div class="et-help">The grey line shown after the subject in most inboxes.</div>
            </div>
          </div>
        </div>

        <div class="et-card">
          <h2><i class="bi bi-type-h1"></i> Greeting</h2>
          <div class="body">
            <div class="row g-3 mb-3">
              <div class="col-sm-5">
                <label class="form-label" for="et-badge">Badge</label>
                <input class="form-control" id="et-badge" name="badge" maxlength="80" value="{{ $tpl['badge'] }}">
              </div>
              <div class="col-sm-7">
                <label class="form-label" for="et-heading">Heading</label>
                <input class="form-control" id="et-heading" name="heading" maxlength="150" value="{{ $tpl['heading'] }}">
              </div>
            </div>
            <div>
              <label class="form-label" for="et-intro">Message</label>
              <textarea class="form-control" id="et-intro" name="intro" rows="3" maxlength="2000">{{ $tpl['intro'] }}</textarea>
            </div>
          </div>
        </div>

        <div class="et-card">
          <h2><i class="bi bi-ticket-detailed"></i> Registration number</h2>
          <div class="body">
            <div class="row g-3">
              <div class="col-sm-5">
                <label class="form-label" for="et-pin-label">Label</label>
                <input class="form-control" id="et-pin-label" name="pin_label" maxlength="80" value="{{ $tpl['pin_label'] }}">
              </div>
              <div class="col-sm-7">
                <label class="form-label" for="et-pin-note">Note under the number</label>
                <input class="form-control" id="et-pin-note" name="pin_note" maxlength="255" value="{{ $tpl['pin_note'] }}">
              </div>
            </div>
          </div>
        </div>

        <div class="et-card">
          <h2><i class="bi bi-layout-text-sidebar"></i> Sections</h2>
          <div class="body py-1">
            <div class="et-toggle"><div><div class="t">Event date &amp; venue</div><div class="d">From the current event (Admin &gt; Events).</div></div>
              <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="show_event" value="1" aria-label="Show event date and venue" @checked($tpl['show_event'] === '1')></div></div>
            <div class="et-toggle"><div><div class="t">"Registered as" profession</div><div class="d">Shown inside the event box.</div></div>
              <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="show_profession" value="1" aria-label="Show profession" @checked($tpl['show_profession'] === '1')></div></div>
            <div class="et-toggle"><div style="flex:1"><div class="t">Verification card button</div>
                <input class="form-control form-control-sm mt-2" name="button_text" maxlength="80" value="{{ $tpl['button_text'] }}" aria-label="Button text" placeholder="Button text"></div>
              <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="show_button" value="1" aria-label="Show verification card button" @checked($tpl['show_button'] === '1')></div></div>
          </div>
        </div>

        <div class="et-card">
          <h2><i class="bi bi-list-ol"></i> Steps</h2>
          <div class="body">
            <div class="mb-3">
              <label class="form-label" for="et-steps-title">Title</label>
              <input class="form-control" id="et-steps-title" name="steps_title" maxlength="120" value="{{ $tpl['steps_title'] }}">
            </div>
            <div class="mb-3">
              <label class="form-label" for="et-steps">Steps <span class="text-muted fw-normal">(one per line, numbered automatically)</span></label>
              <textarea class="form-control" id="et-steps" name="steps" rows="4" maxlength="3000">{{ $tpl['steps'] }}</textarea>
              <div class="et-help"><code>{attachments}</code> becomes "Attached to this email: <b>Invitation card</b> and <b>Itinerary</b>." That line is left out when nothing is attached.</div>
            </div>
            <div>
              <label class="form-label" for="et-closing">Closing message <span class="text-muted fw-normal">(optional)</span></label>
              <textarea class="form-control" id="et-closing" name="closing" rows="2" maxlength="2000" placeholder="e.g. We look forward to welcoming you in Spain.">{{ $tpl['closing'] }}</textarea>
            </div>
          </div>
        </div>

        <div class="et-card">
          <h2><i class="bi bi-palette"></i> Design</h2>
          <div class="body">
            <div class="row g-3 mb-3">
              <div class="col-sm-6">
                <label class="form-label" for="et-brand">Main colour</label>
                <div class="et-color"><input type="color" id="et-brand" name="brand_color" value="{{ $tpl['brand_color'] }}"><span class="small text-muted">Number box, button, footer</span></div>
              </div>
              <div class="col-sm-6">
                <label class="form-label" for="et-accent">Accent colour</label>
                <div class="et-color"><input type="color" id="et-accent" name="accent_color" value="{{ $tpl['accent_color'] }}"><span class="small text-muted">Gradient highlight</span></div>
              </div>
            </div>
            <div class="et-toggle" style="border-bottom:0;padding-top:0"><div><div class="t">Banner image</div><div class="d">Wide image near the bottom (about 1200 × 400 px).</div></div>
              <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="show_banner" value="1" aria-label="Show banner" @checked($tpl['show_banner'] === '1')></div></div>
            <div class="et-banner mb-2" id="etBanner">
              @if($tpl['banner'])<img src="{{ asset($tpl['banner']) }}" alt="Email banner">@else<div class="none">No banner</div>@endif
            </div>
            <label class="btn btn-sm btn-outline-primary mb-0"><i class="bi bi-upload"></i> Upload new banner<input type="file" id="etBannerFile" accept="image/jpeg,image/png,image/gif" hidden></label>
          </div>
        </div>

        <div class="et-card">
          <h2><i class="bi bi-layout-text-window-reverse"></i> Footer</h2>
          <div class="body">
            <div class="mb-3">
              <label class="form-label" for="et-footer-title">Footer heading</label>
              <input class="form-control" id="et-footer-title" name="footer_title" maxlength="150" value="{{ $tpl['footer_title'] }}">
              <div class="et-help">Followed by the contact emails and phones from the website footer (Website Pages).</div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="et-footer-note">Organizer line</label>
              <input class="form-control" id="et-footer-note" name="footer_note" maxlength="200" value="{{ $tpl['footer_note'] }}">
            </div>
            <div>
              <label class="form-label" for="et-bottom">Small print</label>
              <input class="form-control" id="et-bottom" name="bottom_note" maxlength="255" value="{{ $tpl['bottom_note'] }}">
            </div>
          </div>
        </div>

        <div class="et-savebar">
          <span class="et-dirty" id="etDirty"><i class="bi bi-check2-circle"></i> All changes saved</span>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary" id="etReset"><i class="bi bi-arrow-counterclockwise"></i> Restore original</button>
            <button type="submit" class="btn btn-success"><i class="bi bi-check2-circle"></i> Save template</button>
          </div>
        </div>
      </form>

    {{-- Files attached to every registration confirmation email --}}
    <div class="card" id="eaCard">
      <div class="card-header text-white fw-bold" style="background:#1861c5"><i class="bi bi-paperclip"></i> CONFIRMATION EMAIL ATTACHMENTS</div>
      <div class="card-body pt-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <div class="ea-intro">Every <b>active</b> file below is attached to the email each attendee receives after registering (also when you resend it): invitation card, itinerary, venue map…</div>
          <div class="ea-total" id="eaTotal"></div>
        </div>

        <ul class="ea-list" id="eaList"></ul>
        <div class="ea-empty" id="eaEmpty" hidden><i class="bi bi-inbox"></i> No attachments yet. The email is sent without files.</div>

        <form class="ea-add" id="eaAdd" novalidate>
          <div class="fw-bold mb-2"><i class="bi bi-plus-circle"></i> Add an attachment</div>
          <div class="row g-2 align-items-start">
            <div class="col-md-4">
              <input class="form-control" name="title" maxlength="120" placeholder="Name, e.g. Itinerary" aria-label="Attachment name">
            </div>
            <div class="col-md-5">
              <label class="ea-drop" id="eaDrop"><i class="bi bi-cloud-arrow-up"></i> <span>Choose or drop a PDF, JPG or PNG (max {{ \App\Models\EmailAttachment::MAX_KB / 1024 }} MB)</span>
                <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
              </label>
            </div>
            <div class="col-md-3 d-grid">
              <button class="btn btn-primary" type="submit"><i class="bi bi-paperclip"></i> Attach to email</button>
            </div>
          </div>
        </form>
      </div>
      <div class="card-footer small text-muted">
        <i class="bi bi-info-circle"></i> Attendees receive the files as e.g. <code>{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::slug(\App\Support\EventSettings::get('event_short', 'BICS'))) }}-Itinerary.pdf</code>. Changes here apply immediately.
      </div>
    </div>
    </div>

    {{-- Live preview --}}
    <div class="et-preview">
      <div class="bar">
        <div class="et-seg" role="group" aria-label="Preview size">
          <button type="button" class="on" data-size="desktop"><i class="bi bi-display"></i> Desktop</button>
          <button type="button" data-size="mobile"><i class="bi bi-phone"></i> Mobile</button>
        </div>
        <button type="button" class="btn btn-sm btn-primary" id="etTest"><i class="bi bi-send"></i> Send test email</button>
      </div>
      <div class="et-subject" id="etSubject"></div>
      <div class="et-frame-wrap" id="etFrameWrap"><iframe id="etFrame" title="Email preview"></iframe></div>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script>
  // Email template editor: live preview (debounced), save, restore, banner upload, test email
  (function () {
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    var form = document.getElementById('etForm');
    var frame = document.getElementById('etFrame');
    var dirtyEl = document.getElementById('etDirty');
    var lastField = null, timer = null, dirty = false, seq = 0;
    var esc = function (t) { var d = document.createElement('div'); d.textContent = t == null ? '' : t; return d.innerHTML; };
    var toast = function (icon, title) { Swal.fire({ toast: true, position: 'top-end', icon: icon, title: title, showConfirmButton: false, timer: 2200 }); };

    function post(url, body, method) {
      if (method) body.append('_method', method);
      return fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: body });
    }
    function setDirty(on) {
      dirty = on;
      dirtyEl.className = 'et-dirty' + (on ? ' on' : '');
      dirtyEl.innerHTML = on ? '<i class="bi bi-pencil"></i> Unsaved changes' : '<i class="bi bi-check2-circle"></i> All changes saved';
    }

    function refresh() {
      var my = ++seq;
      post(@json(route('email-template.preview')), new FormData(form)).then(function (r) { return r.text(); }).then(function (html) {
        if (my !== seq) return;
        var y = frame.contentWindow ? frame.contentWindow.scrollY : 0;
        frame.srcdoc = html;
        frame.onload = function () { try { frame.contentWindow.scrollTo(0, y); } catch (e) {} };
        // Subject line + attachments, as the inbox shows them
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var atts = (window.etAttachments || []).filter(function (a) { return a.is_active && !a.missing; });
        document.getElementById('etSubject').innerHTML = '<div><b>' + esc(doc.title) + '</b></div>'
          + (atts.length ? '<div class="att">' + atts.map(function (a) { return '<span><i class="bi bi-paperclip"></i> ' + esc(a.download_name) + '</span>'; }).join('') + '</div>' : '<div class="small">No attachments</div>');
      });
    }
    function schedule() { clearTimeout(timer); timer = setTimeout(refresh, 350); }
    document.addEventListener('ea:changed', schedule);

    form.addEventListener('input', function () { setDirty(true); schedule(); });
    form.addEventListener('change', function () { setDirty(true); schedule(); });
    form.addEventListener('focusin', function (e) { if (e.target.matches('input[type=text], input:not([type]), textarea')) lastField = e.target; });

    // Placeholder chips insert at the cursor
    document.querySelectorAll('.et-chip').forEach(function (chip) {
      chip.addEventListener('mousedown', function (e) { e.preventDefault(); });
      chip.addEventListener('click', function () {
        var f = lastField || document.getElementById('et-intro');
        var ph = chip.dataset.ph, s = f.selectionStart != null ? f.selectionStart : f.value.length, en = f.selectionEnd != null ? f.selectionEnd : s;
        f.value = f.value.slice(0, s) + ph + f.value.slice(en);
        f.focus(); f.setSelectionRange(s + ph.length, s + ph.length);
        f.dispatchEvent(new Event('input', { bubbles: true }));
      });
    });

    // Desktop / mobile preview
    document.querySelectorAll('.et-seg button').forEach(function (b) {
      b.addEventListener('click', function () {
        document.querySelectorAll('.et-seg button').forEach(function (x) { x.classList.toggle('on', x === b); });
        document.getElementById('etFrameWrap').classList.toggle('mobile', b.dataset.size === 'mobile');
      });
    });

    // Save
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
      form.querySelectorAll('.invalid-feedback').forEach(function (el) { el.remove(); });
      var btn = form.querySelector('[type=submit]'); btn.disabled = true;
      post(@json(route('email-template.update')), new FormData(form), 'PUT')
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
        .then(function (res) {
          btn.disabled = false;
          if (res.ok) { setDirty(false); toast('success', res.d.message); return; }
          var errs = res.d.errors || {};
          Object.keys(errs).forEach(function (k) {
            var input = form.querySelector('[name="' + k + '"]'); if (!input) return;
            input.classList.add('is-invalid');
            var fb = document.createElement('div'); fb.className = 'invalid-feedback'; fb.textContent = errs[k][0];
            input.insertAdjacentElement('afterend', fb);
          });
          var first = form.querySelector('.is-invalid'); if (first) first.focus();
          Swal.fire({ icon: 'warning', title: 'Please check', html: Object.keys(errs).map(function (k) { return esc(errs[k][0]); }).join('<br>') || esc(res.d.message) });
        })
        .catch(function () { btn.disabled = false; Swal.fire('Error', 'Could not save. Please try again.', 'error'); });
    });

    // Restore the original wording
    document.getElementById('etReset').addEventListener('click', function () {
      Swal.fire({ icon: 'question', title: 'Restore the original email?', text: 'All texts, colours and the banner go back to the original design. Attachments are not changed.', showCancelButton: true, confirmButtonText: 'Restore' })
        .then(function (r) {
          if (!r.isConfirmed) return;
          post(@json(route('email-template.reset')), new FormData()).then(function (r) { return r.json(); }).then(function (d) {
            Object.keys(d.tpl).forEach(function (k) {
              var el = form.querySelector('[name="' + k + '"]'); if (!el) return;
              if (el.type === 'checkbox') el.checked = d.tpl[k] === '1'; else el.value = d.tpl[k];
            });
            document.getElementById('etBanner').innerHTML = d.tpl.banner ? '<img src="' + esc(@json(url('/')) + '/' + d.tpl.banner) + '" alt="Email banner">' : '<div class="none">No banner</div>';
            setDirty(false); refresh(); toast('success', d.message);
          });
        });
    });

    // Banner upload (saved immediately)
    document.getElementById('etBannerFile').addEventListener('change', function () {
      var f = this.files[0]; if (!f) return;
      var fd = new FormData(); fd.append('banner', f);
      Swal.fire({ title: 'Uploading…', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
      post(@json(route('email-template.banner')), fd).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); }).then(function (res) {
        if (!res.ok) { Swal.fire({ icon: 'warning', title: 'Not uploaded', text: (res.d.errors && res.d.errors.banner ? res.d.errors.banner[0] : res.d.message) }); return; }
        document.getElementById('etBanner').innerHTML = '<img src="' + esc(res.d.url) + '" alt="Email banner">';
        form.querySelector('[name=show_banner]').checked = true;
        toast('success', res.d.message); refresh();
      }).catch(function () { Swal.fire('Error', 'Upload failed.', 'error'); });
      this.value = '';
    });

    // Test email
    document.getElementById('etTest').addEventListener('click', function () {
      var send = function () {
        Swal.fire({
          title: 'Send a test email', input: 'email', inputValue: @json(auth()->user()->email ?? ''), inputLabel: 'The saved template is sent with sample details and all active attachments.',
          showCancelButton: true, confirmButtonText: 'Send', showLoaderOnConfirm: true,
          preConfirm: function (email) {
            var fd = new FormData(); fd.append('email', email);
            return post(@json(route('email-template.test')), fd).then(function (r) { return r.json().then(function (d) { if (!r.ok) throw new Error((d.errors && d.errors.email ? d.errors.email[0] : d.message) || 'Could not send.'); return d; }); })
              .catch(function (err) { Swal.showValidationMessage(err.message); });
          }
        }).then(function (r) { if (r.isConfirmed && r.value) toast('success', r.value.message); });
      };
      if (!dirty) return send();
      Swal.fire({ icon: 'info', title: 'Save first?', text: 'The test email uses the saved template. Save your changes now?', showCancelButton: true, confirmButtonText: 'Save & continue' })
        .then(function (r) {
          if (!r.isConfirmed) return;
          post(@json(route('email-template.update')), new FormData(form), 'PUT').then(function (res) {
            if (res.ok) { setDirty(false); send(); } else Swal.fire('Please check', 'Some fields are not valid. Fix them and save first.', 'warning');
          });
        });
    });

    window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
    refresh();
  })();

  // Confirmation email attachments (AJAX)
  (function () {
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    var items = @json($attachments);
    var limitMb = {{ \App\Models\EmailAttachment::MAX_TOTAL_MB }};
    var base = @json(url('/email-template/attachments'));
    var list = document.getElementById('eaList');
    var esc = function (t) { var d = document.createElement('div'); d.textContent = t == null ? '' : t; return d.innerHTML; };
    var toast = function (icon, title) { Swal.fire({ toast: true, position: 'top-end', icon: icon, title: title, showConfirmButton: false, timer: 2200 }); };
    var fmt = function (b) { return b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; };

    function send(url, body, method) {
      if (method) body.append('_method', method);
      return fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: body })
        .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, status: r.status, d: d }; }); });
    }
    function firstError(d) {
      var e = d.errors || {}, k = Object.keys(e)[0];
      return k ? e[k][0] : (d.message || 'Something went wrong. Please try again.');
    }
    function showTotal() {
      var el = document.getElementById('eaTotal');
      var active = items.filter(function (i) { return i.is_active && !i.missing; });
      var bytes = active.reduce(function (s, i) { return s + (+i.bytes || 0); }, 0);
      if (!items.length) { el.innerHTML = ''; return; }
      var pct = Math.min(100, Math.round(bytes / (limitMb * 1048576) * 100));
      el.className = 'ea-total' + (pct > 75 ? ' warn' : '');
      el.innerHTML = active.length + ' file' + (active.length === 1 ? '' : 's') + ' attached · ' + (bytes ? fmt(bytes) : '0 KB') + ' of ' + limitMb + ' MB'
        + '<span class="bar" title="Email size limit"><span style="width:' + pct + '%"></span></span>';
    }

    function render() {
      list.innerHTML = items.map(function (a, i) {
        return '<li class="ea-item' + (a.is_active ? '' : ' off') + '" data-id="' + a.id + '">'
          + '<div class="ea-icon ' + a.type + '"><i class="bi ' + (a.type === 'image' ? 'bi-file-earmark-image' : 'bi-file-earmark-pdf') + '"></i></div>'
          + '<div class="ea-body">'
          +   '<input class="ea-title" value="' + esc(a.title) + '" maxlength="120" aria-label="Attachment name" title="Click to rename">'
          +   '<div class="ea-meta">' + (a.missing ? '<span class="missing"><i class="bi bi-exclamation-triangle"></i> File missing, please replace it</span> · ' : '')
          +     esc(a.size) + ' · sent as <b>' + esc(a.download_name) + '</b>' + (a.original_name ? ' · uploaded: ' + esc(a.original_name) : '') + '</div>'
          + '</div>'
          + '<div class="ea-actions">'
          +   '<div class="form-check form-switch" title="' + (a.is_active ? 'Attached to emails' : 'Not attached') + '"><input class="form-check-input ea-active" type="checkbox" role="switch" aria-label="Attach to emails"' + (a.is_active ? ' checked' : '') + '></div>'
          +   '<button type="button" class="btn ea-up" title="Move up" aria-label="Move up"' + (i === 0 ? ' disabled' : '') + '><i class="bi bi-arrow-up"></i></button>'
          +   '<button type="button" class="btn ea-down" title="Move down" aria-label="Move down"' + (i === items.length - 1 ? ' disabled' : '') + '><i class="bi bi-arrow-down"></i></button>'
          +   (a.missing ? '' : '<a class="btn" href="' + esc(a.url) + '" target="_blank" title="Open" aria-label="Open"><i class="bi bi-box-arrow-up-right"></i></a>')
          +   '<label class="btn mb-0" title="Replace file" aria-label="Replace file"><i class="bi bi-arrow-repeat"></i><input type="file" class="ea-replace" hidden accept=".pdf,.jpg,.jpeg,.png"></label>'
          +   '<button type="button" class="btn del ea-del" title="Delete" aria-label="Delete"><i class="bi bi-trash"></i></button>'
          + '</div></li>';
      }).join('');
      document.getElementById('eaEmpty').hidden = items.length > 0;
      showTotal();
      window.etAttachments = items;
      document.dispatchEvent(new Event('ea:changed'));  // refresh the email preview
    }

    function idOf(el) { return +el.closest('.ea-item').dataset.id; }
    function find(id) { return items.filter(function (x) { return x.id === id; })[0]; }

    function update(id, fields) {
      var fd = new FormData();
      Object.keys(fields).forEach(function (k) { fd.append(k, fields[k]); });
      return send(base + '/' + id, fd).then(function (res) {
        if (!res.ok) { render(); Swal.fire({ icon: 'warning', title: 'Not saved', text: firstError(res.d) }); return; }
        items = items.map(function (x) { return x.id === id ? res.d.item : x; });
        render(); toast('success', res.d.message);
      }).catch(function () { render(); Swal.fire('Error', 'Could not save. Please check your connection.', 'error'); });
    }

    list.addEventListener('change', function (e) {
      var t = e.target;
      if (t.classList.contains('ea-active')) update(idOf(t), { is_active: t.checked ? 1 : 0 });
      if (t.classList.contains('ea-replace') && t.files[0]) {
        Swal.fire({ title: 'Uploading…', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
        update(idOf(t), { file: t.files[0] });
      }
    });
    list.addEventListener('keydown', function (e) {
      if (e.target.classList.contains('ea-title') && e.key === 'Enter') { e.preventDefault(); e.target.blur(); }
    });
    list.addEventListener('focusout', function (e) {
      var t = e.target; if (!t.classList.contains('ea-title')) return;
      var a = find(idOf(t)), v = t.value.trim();
      if (!v) { t.value = a.title; return; }
      if (v !== a.title) update(a.id, { title: v });
    });
    list.addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b) return;
      var id = idOf(b), i = items.indexOf(find(id));
      if (b.classList.contains('ea-up') || b.classList.contains('ea-down')) {
        var j = b.classList.contains('ea-up') ? i - 1 : i + 1;
        var tmp = items[i]; items[i] = items[j]; items[j] = tmp; render();
        var fd = new FormData(); items.forEach(function (x) { fd.append('ids[]', x.id); });
        send(base + '/order', fd).then(function (res) { if (!res.ok) toast('error', 'Order not saved'); });
      }
      if (b.classList.contains('ea-del')) {
        Swal.fire({ icon: 'warning', title: 'Delete "' + esc(find(id).title) + '"?', text: 'It will no longer be attached to confirmation emails, and the file is removed from the server.',
          showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#d33' })
          .then(function (r) {
            if (!r.isConfirmed) return;
            send(base + '/' + id, new FormData(), 'DELETE').then(function (res) {
              if (!res.ok) { Swal.fire('Error', firstError(res.d), 'error'); return; }
              items = items.filter(function (x) { return x.id !== id; }); render(); toast('success', res.d.message);
            });
          });
      }
    });

    // Add form: name + file (picked or dropped)
    var add = document.getElementById('eaAdd'), drop = document.getElementById('eaDrop'), fileInput = add.querySelector('[name=file]');
    var dropText = drop.querySelector('span').textContent;
    function showFile() {
      var f = fileInput.files[0], title = add.querySelector('[name=title]');
      drop.classList.toggle('has', !!f);
      drop.querySelector('span').textContent = f ? f.name + ' (' + fmt(f.size) + ')' : dropText;
      if (f && !title.value.trim()) title.value = f.name.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ').replace(/\s+/g, ' ').trim();
    }
    fileInput.addEventListener('change', showFile);
    ['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function () { drop.classList.add('drag'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function () { drop.classList.remove('drag'); }); });

    add.addEventListener('submit', function (e) {
      e.preventDefault();
      add.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
      add.querySelectorAll('.invalid-feedback').forEach(function (el) { el.remove(); });
      var btn = add.querySelector('[type=submit]'), label = btn.innerHTML;
      btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Uploading…';
      send(base, new FormData(add)).then(function (res) {
        btn.disabled = false; btn.innerHTML = label;
        if (!res.ok) {
          var errs = res.d.errors || {};
          Object.keys(errs).forEach(function (k) {
            var target = k === 'file' ? drop : add.querySelector('[name="' + k + '"]'); if (!target) return;
            target.classList.add('is-invalid');
            var fb = document.createElement('div'); fb.className = 'invalid-feedback'; fb.textContent = errs[k][0];
            target.insertAdjacentElement('afterend', fb);
          });
          if (!Object.keys(errs).length) Swal.fire({ icon: 'warning', title: 'Not uploaded', text: firstError(res.d) });
          return;
        }
        items.push(res.d.item); render(); add.reset(); showFile(); toast('success', res.d.message);
      }).catch(function () { btn.disabled = false; btn.innerHTML = label; Swal.fire('Error', 'Upload failed. Please check your connection and the file size.', 'error'); });
    });

    render();
  })();
</script>
@endpush
