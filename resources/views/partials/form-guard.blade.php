{{-- Bot / spam protection for a public form (App\Support\FormGuard). Usage: @include('partials.form-guard', ['form' => 'contact'])
     Optional: 'captcha' => false leaves out the Turnstile widget here; 'only' => 'captcha' renders just the widget
     (for long forms: fields at the top, the widget next to the submit button). --}}
@if(($only ?? null) !== 'captcha')
<div style="position:absolute!important;left:-9999px!important;top:auto;width:1px;height:1px;overflow:hidden" aria-hidden="true">
   <label>Leave this empty <input type="text" name="{{ \App\Support\FormGuard::HONEYPOT }}" value="" tabindex="-1" autocomplete="off" data-lpignore="true" data-1p-ignore data-form-type="other"></label>
</div>
<input type="hidden" name="{{ \App\Support\FormGuard::TOKEN }}" value="{{ \App\Support\FormGuard::token($form) }}">
@endif
@if(($captcha ?? true) && \App\Support\FormGuard::turnstileOn())
<div class="cf-turnstile fg-captcha" data-sitekey="{{ \App\Support\FormGuard::settings()['turnstile_site'] }}" data-size="flexible" data-theme="light" style="margin:12px 0;max-width:420px"></div>
@once
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endonce
@endif
@once
<script>
  // After an AJAX submit the Turnstile answer is used up: get a fresh one so the form can be sent again
  window.FormGuard = window.FormGuard || {
    reset: function (form) { if (window.turnstile && form) form.querySelectorAll('.cf-turnstile').forEach(function (w) { try { turnstile.reset(w); } catch (e) {} }); },
    fields: function (form) {
      var out = {};
      if (form) form.querySelectorAll('[name="{{ \App\Support\FormGuard::HONEYPOT }}"],[name="{{ \App\Support\FormGuard::TOKEN }}"],[name="cf-turnstile-response"]').forEach(function (i) { out[i.name] = i.value; });
      return out;
    }
  };
</script>
@endonce
