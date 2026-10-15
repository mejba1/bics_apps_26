@php
   use App\Support\Cms;
   $f = \App\Models\Page::globalContent('footer') ?? \App\Support\PageSeed::footer();
   $countries = \App\Models\FormOption::tableReady() ? \App\Models\FormOption::active('country')->pluck('label') : collect();
   $topics = ['Registration', 'Scientific programme', 'Speaking at BICS', 'Partnership & sponsorship', 'Travel & visa letter', 'Media', 'Other'];
@endphp
<section {!! Cms::sectionAttrs($c, 'soft') !!}>
   <div class="container">
      <div class="row g-5">
         <div class="col-lg-5">
            <div class="bx-head bx-reveal" style="margin-bottom:28px">
               <div class="bx-eyebrow">{{ ($c['eyebrow'] ?? '') ?: 'Get in touch' }}</div>
               <h2 class="bx-title">{{ ($c['form_title'] ?? '') && strtoupper($c['form_title']) !== 'CONTACT FORM' ? $c['form_title'] : 'We’re here to help' }}</h2>
               <p class="bx-lead">{{ ($c['intro'] ?? '') ?: 'Questions about registration, the programme, partnerships or travel? Send us a message and the BICS team will reply by email.' }}</p>
            </div>
            <div class="bx-info-list bx-grid-cards">
               @if(Cms::lines($f['emails'] ?? ''))
               <div class="bx-info bx-reveal"><span class="bx-icon"><i class="bi bi-envelope"></i></span><div><h4>Email</h4><p>@foreach(Cms::lines($f['emails']) as $e)<a href="mailto:{{ $e }}">{{ $e }}</a><br>@endforeach</p></div></div>
               @endif
               @if(Cms::lines($f['phones'] ?? ''))
               <div class="bx-info bx-reveal"><span class="bx-icon"><i class="bi bi-telephone"></i></span><div><h4>Phone</h4><p>@foreach(Cms::lines($f['phones']) as $p)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $p) }}">{{ $p }}</a><br>@endforeach</p></div></div>
               @endif
               @if(!empty($f['address']))
               <div class="bx-info bx-reveal"><span class="bx-icon"><i class="bi bi-building"></i></span><div><h4>Organiser’s office</h4><p>{!! nl2br(e($f['address'])) !!}</p></div></div>
               @endif
               <div class="bx-info bx-reveal"><span class="bx-icon"><i class="bi bi-clock"></i></span><div><h4>Response time</h4><p>We usually reply within two working days.</p></div></div>
            </div>
         </div>
         <div class="col-lg-7">
            <form class="bx-prose-card bx-reveal" id="bx-contact" action="{{ route('contact.send') }}" method="POST" novalidate>
               @csrf
               <div class="row g-3">
                  <div class="col-md-6">
                     <label class="form-label fw-semibold" for="ct-name">Full name <span class="text-danger">*</span></label>
                     <input class="form-control form-control-lg" id="ct-name" name="name" maxlength="150" autocomplete="name" required>
                  </div>
                  <div class="col-md-6">
                     <label class="form-label fw-semibold" for="ct-email">Email <span class="text-danger">*</span></label>
                     <input type="email" class="form-control form-control-lg" id="ct-email" name="email" maxlength="190" autocomplete="email" required>
                  </div>
                  <div class="col-md-6">
                     <label class="form-label fw-semibold" for="ct-country">Country</label>
                     <select class="form-select form-select-lg" id="ct-country" name="country" autocomplete="country-name">
                        <option value="">Select country</option>
                        @foreach($countries as $country)<option>{{ $country }}</option>@endforeach
                     </select>
                  </div>
                  <div class="col-md-6">
                     <label class="form-label fw-semibold" for="ct-phone">Phone <span class="text-muted fw-normal">(optional)</span></label>
                     <input type="tel" class="form-control form-control-lg" id="ct-phone" name="phone" maxlength="40" autocomplete="tel">
                  </div>
                  <div class="col-12">
                     <label class="form-label fw-semibold" for="ct-topic">Topic <span class="text-danger">*</span></label>
                     <select class="form-select form-select-lg" id="ct-topic" name="subject" required>
                        @foreach($topics as $t)<option>{{ $t }}</option>@endforeach
                     </select>
                  </div>
                  <div class="col-12">
                     <label class="form-label fw-semibold" for="ct-message">Message <span class="text-danger">*</span></label>
                     <textarea class="form-control form-control-lg" id="ct-message" name="message" rows="5" minlength="10" maxlength="3000" required></textarea>
                  </div>
                  {{-- Spam trap: people never see or fill this --}}
                  <div class="d-none" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
                  @include('partials.form-guard', ['form' => 'contact'])
                  <div class="col-12 d-flex flex-wrap align-items-center justify-content-between gap-3 mt-2">
                     <span class="small text-muted"><i class="bi bi-shield-lock"></i> We only use your details to answer you.</span>
                     <button class="bx-btn bx-btn-primary" type="submit"><i class="bi bi-send"></i> Send message</button>
                  </div>
               </div>
               <div class="alert alert-success mt-4 mb-0 d-none" role="status" data-ok></div>
            </form>
         </div>
      </div>
   </div>
</section>
@once
@push('scripts')
<script>
(function () {
   var form = document.getElementById('bx-contact');
   if (!form) return;
   form.addEventListener('submit', function (e) {
      e.preventDefault();
      form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
      form.querySelectorAll('.invalid-feedback').forEach(function (el) { el.remove(); });
      var btn = form.querySelector('[type=submit]'); btn.disabled = true;
      fetch(form.action, { method: 'POST', headers: { 'Accept': 'application/json' }, body: new FormData(form) })
         .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
         .then(function (res) {
            btn.disabled = false;
            if (window.FormGuard) window.FormGuard.reset(form);
            if (res.ok) {
               var ok = form.querySelector('[data-ok]');
               ok.textContent = res.d.message; ok.classList.remove('d-none');
               form.reset();
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
            var first = form.querySelector('.is-invalid');
            if (first) first.focus();
            else Swal.fire('Not sent', res.d.message || 'Please try again in a moment.', 'error');
         })
         .catch(function () { btn.disabled = false; Swal.fire('Not sent', 'Please check your connection and try again.', 'error'); });
   });
})();
</script>
@endpush
@endonce
