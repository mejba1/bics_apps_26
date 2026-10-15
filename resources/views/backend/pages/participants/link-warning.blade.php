{{-- Shown while profile / NFC links point to this computer (Link settings: automatic on localhost) --}}
@if(\App\Support\PublicLinks::isLocal())
<div class="alert alert-warning small py-2 d-flex flex-wrap align-items-center gap-2"><i class="bi bi-exclamation-triangle"></i>
  <span>Links currently use <b>{{ \App\Support\PublicLinks::base() }}</b> (this computer). Fine for testing — but <b>do not print QR codes or encode NFC cards for real</b> until the site runs on the server (links then switch to the server address automatically), or set a custom address in Link settings.</span>
</div>
@endif
