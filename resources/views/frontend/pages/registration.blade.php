@extends('frontend.layouts.content')
@section('title') Registration @endsection

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tom-select/2.3.1/css/tom-select.bootstrap5.min.css">
<link rel="stylesheet" href="{{ asset('assets/frontend/css/registration.css') }}?v={{ filemtime(base_path('assets/frontend/css/registration.css')) }}">
@endpush

@section('content')
@php
   $old = fn ($k, $d = '') => old($k, $d);
   $err = fn ($k) => $errors->first($k);
@endphp
<section class="reg-page">
   {{-- ===== Event banner ===== --}}
   <div class="reg-hero">
      <div class="container">
         <div class="reg-hero-inner">
            <span class="reg-eyebrow"><i class="bi bi-person-badge"></i> Delegate registration</span>
            <h1 class="reg-hero-title">{{ \App\Support\EventSettings::get('event_name') }}</h1>
            {{-- while registration is open, venue / dates / closing time are in the event card below --}}
            @if($registration['state'] !== 'open')
            <div class="reg-hero-meta">
               <span><i class="bi bi-geo-alt-fill"></i> {{ \App\Support\EventSettings::get('event_venue') }}</span>
               <span><i class="bi bi-calendar-event-fill"></i> {{ \App\Support\EventSettings::datesLabel() }}</span>
            </div>
            @endif
         </div>
      </div>
   </div>

   <div class="container reg-container">
      <div class="row justify-content-center">
         <div class="col-xl-9 col-lg-10">
            @if($registration['state'] !== 'open')
            {{-- ===== Registration not open: opens later / closed (Admin > Events (current event)) ===== --}}
            <div class="reg-card reg-status text-center p-4 p-md-5">
               @if($registration['state'] === 'upcoming')
                  <div class="reg-success-icon is-muted"><i class="bi bi-hourglass-split"></i></div>
                  <h2 class="fw-bold mb-2">Registration opens soon</h2>
                  <p class="text-muted mb-1">{{ \App\Support\EventSettings::get('registration_upcoming_message') }}</p>
                  <p class="fw-bold mb-4"><i class="bi bi-calendar-check"></i> Opens {{ $registration['opens']->format('l, j F Y \a\t H:i') }} <span class="text-muted fw-normal">({{ $registration['opens']->format('T') }})</span></p>
                  <div class="reg-status-clock" data-target="{{ $registration['opens']->toIso8601String() }}">
                     <div><b data-u="d">0</b><span>Days</span></div><div><b data-u="h">00</b><span>Hours</span></div>
                     <div><b data-u="m">00</b><span>Minutes</span></div><div><b data-u="s">00</b><span>Seconds</span></div>
                  </div>
               @else
                  <div class="reg-success-icon is-muted"><i class="bi bi-lock-fill"></i></div>
                  <h2 class="fw-bold mb-2">Registration closed</h2>
                  <p class="text-muted mb-4">{{ \App\Support\EventSettings::get('registration_closed_message') }}</p>
                  <a href="{{ url('/') }}" class="btn reg-btn-primary"><i class="bi bi-house"></i> Back to home</a>
               @endif
            </div>
            @else
            @php
               $evStart = \App\Support\EventSettings::date('event_start');
               $evClose = $registration['closes'];
            @endphp
            {{-- ===== Event at a glance ===== --}}
            <div class="reg-event">
               <div class="reg-event-top">
                  <div class="min-w-0">
                     <span class="reg-event-badge"><span class="dot"></span> Event registration open</span>
                     <h2 class="reg-event-name">{{ \App\Support\EventSettings::get('event_name') }}</h2>
                     @if(\App\Support\EventSettings::get('event_venue'))<div class="reg-event-venue"><i class="bi bi-geo-alt"></i> {{ \App\Support\EventSettings::get('event_venue') }}</div>@endif
                  </div>
                  <div class="reg-event-chips">
                     @if($evStart)<span class="reg-chip"><i class="bi bi-calendar-event"></i> Event starts: <b>{{ $evStart->format('M j, Y') }}</b></span>@endif
                     @if($evClose)<span class="reg-chip is-warn"><i class="bi bi-clock"></i> Registration closes: <b>{{ $evClose->format('M j, Y') }}</b></span>@endif
                  </div>
               </div>
               @if($evStart && $evStart->isFuture())
               <div class="reg-countdown" data-target="{{ $evStart->toIso8601String() }}" aria-label="Time until the event starts">
                  <div><b data-u="d">0</b><span>Days</span></div><div><b data-u="h">00</b><span>Hours</span></div>
                  <div><b data-u="m">00</b><span>Minutes</span></div><div><b data-u="s">00</b><span>Seconds</span></div>
               </div>
               @endif
            </div>

            {{-- ===== New or returning ===== --}}
            <div class="reg-choice" role="radiogroup" aria-label="How would you like to register?">
               <button type="button" class="reg-option is-on" data-mode="new" role="radio" aria-checked="true">
                  <span class="reg-option-ic"><i class="bi bi-person-plus"></i></span>
                  <span class="reg-option-t">New registration</span>
                  <span class="reg-option-d">First time with BICS? Fill in the form to reserve your place.</span>
                  <span class="reg-option-check"><i class="bi bi-check-lg"></i></span>
               </button>
               <button type="button" class="reg-option" data-mode="returning" role="radio" aria-checked="false">
                  <span class="reg-option-ic"><i class="bi bi-award"></i></span>
                  <span class="reg-option-t">Already joined a BICS event?</span>
                  <span class="reg-option-d">Get a one-time code by email and register in seconds with your saved details.</span>
                  <span class="reg-option-check"><i class="bi bi-check-lg"></i></span>
               </button>
            </div>

            {{-- ===== Returning participant: find → code → filled form ===== --}}
            <div class="reg-card reg-back-card" id="reg-returning" hidden>
               <div class="reg-back-head">
                  <span class="reg-back-ic"><i class="bi bi-person-check"></i></span>
                  <div><h3>Welcome back</h3><p id="rt-sub">Enter the email you registered with, your participant ID (e.g. MBR-000123) or an earlier registration number.</p></div>
               </div>
               <form class="reg-back-body" id="rt-find" novalidate>
                  <label class="reg-label" for="rt-lookup">Email, participant ID or registration number</label>
                  <div class="reg-back-row">
                     <input type="text" class="form-control" id="rt-lookup" placeholder="name@hospital.org  ·  MBR-000123  ·  123456" autocomplete="email" maxlength="190" required>
                     <button class="btn reg-btn-primary" id="rt-send"><i class="bi bi-envelope"></i> Send code</button>
                  </div>
                  <div class="reg-back-err" id="rt-find-err" hidden></div>
                  @include('partials.form-guard', ['form' => 'returning'])
               </form>
               <form class="reg-back-body" id="rt-code" novalidate hidden>
                  <label class="reg-label" for="rt-otp">Enter the 6-digit code sent to <b id="rt-to"></b></label>
                  <div class="reg-otp" id="rt-otp-boxes">
                     @for($i = 0; $i < 6; $i++)<input type="text" inputmode="numeric" maxlength="1" class="reg-otp-box" aria-label="Digit {{ $i + 1 }}" @if($i === 0) id="rt-otp" autocomplete="one-time-code" @endif>@endfor
                  </div>
                  <div class="reg-back-err" id="rt-code-err" hidden></div>
                  <div class="reg-back-row mt-3">
                     <button class="btn reg-btn-primary" id="rt-verify"><i class="bi bi-shield-check"></i> Verify &amp; continue</button>
                     <span class="reg-back-links"><button type="button" class="reg-linkbtn" id="rt-resend" disabled>Resend code</button> · <button type="button" class="reg-linkbtn" id="rt-change">Use something else</button></span>
                  </div>
                  <p class="reg-back-hint"><i class="bi bi-info-circle"></i> The code expires in 20 minutes. Check your spam folder if it does not arrive.</p>
               </form>
               <div class="reg-back-body" id="rt-done" hidden></div>
            </div>

            <div class="reg-welcome" id="rt-welcome" hidden></div>

            <div id="reg-new">
            <div class="reg-card">
               {{-- ===== Stepper ===== --}}
               <ol class="reg-steps" aria-label="Registration steps">
                  <li class="reg-step is-active" data-step="1"><span class="reg-step-dot">1</span><span class="reg-step-label">Personal</span></li>
                  <li class="reg-step" data-step="2"><span class="reg-step-dot">2</span><span class="reg-step-label">Professional</span></li>
                  <li class="reg-step" data-step="3"><span class="reg-step-dot">3</span><span class="reg-step-label">Photo &amp; ID</span></li>
                  <li class="reg-step" data-step="4"><span class="reg-step-dot">4</span><span class="reg-step-label">Review</span></li>
               </ol>
               <div class="reg-progress"><div class="reg-progress-bar" style="width:25%"></div></div>

               @if($errors->any())
               <div class="alert alert-danger reg-alert mx-3 mx-md-4 mt-3 mb-0" role="alert">
                  <strong><i class="bi bi-exclamation-triangle-fill"></i> Please fix the following:</strong>
                  <ul class="mb-0 mt-1">
                     @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
                  </ul>
               </div>
               @endif

               <form id="regForm" method="POST" action="{{ url('saveRegisterInfo') }}" enctype="multipart/form-data" novalidate>
                  @csrf
                  {{-- Spam trap: hidden from people --}}
                  {{-- Neutral name + no label so browser autofill never fills it; the form script also clears it before sending --}}
                  <div class="reg-hp" aria-hidden="true"><input type="text" name="reg_hp_check" id="reg-hp" value="" tabindex="-1" autocomplete="off" data-lpignore="true" data-1p-ignore data-form-type="other"></div>
                  @include('partials.form-guard', ['form' => 'registration', 'captcha' => false])
                  <input type="hidden" name="countryCode" id="reg-countryCode" value="{{ $old('countryCode') }}">
                  <input type="hidden" name="keep_photo" id="reg-keep-photo" value="1">

                  {{-- ================= STEP 1 : PERSONAL ================= --}}
                  <fieldset class="reg-panel is-active" data-step="1">
                     <legend class="reg-panel-title"><i class="bi bi-person-circle"></i> Personal details</legend>
                     <p class="reg-panel-sub">Tell us who you are. Fields marked <span class="req">*</span> are required.</p>

                     <div class="mb-3">
                        <span class="reg-label">Title <span class="req">*</span></span>
                        <div class="reg-chips" role="radiogroup" aria-label="Title">
                           @foreach($titles as $t)
                           <input type="radio" class="btn-check" name="title" id="reg-title-{{ $loop->index }}" value="{{ $t }}" @checked($old('title', 'Dr') === $t) required>
                           <label class="reg-chip" for="reg-title-{{ $loop->index }}">{{ $t }}</label>
                           @endforeach
                        </div>
                        <div class="reg-error" data-error="title">{{ $err('title') }}</div>
                     </div>

                     <div class="row g-3">
                        <div class="col-md-6">
                           <label class="reg-label" for="reg-firstname">First name <span class="req">*</span></label>
                           <div class="reg-input"><i class="bi bi-person"></i>
                              <input type="text" class="form-control @error('firstname') is-invalid @enderror" id="reg-firstname" name="firstname" value="{{ $old('firstname') }}" placeholder="e.g. Sarah" autocomplete="given-name" maxlength="100" required>
                           </div>
                           <div class="reg-error" data-error="firstname">{{ $err('firstname') }}</div>
                        </div>
                        <div class="col-md-6">
                           <label class="reg-label" for="reg-lastname">Last name <span class="req">*</span></label>
                           <div class="reg-input"><i class="bi bi-person"></i>
                              <input type="text" class="form-control @error('lastname') is-invalid @enderror" id="reg-lastname" name="lastname" value="{{ $old('lastname') }}" placeholder="e.g. Rahman" autocomplete="family-name" maxlength="100" required>
                           </div>
                           <div class="reg-error" data-error="lastname">{{ $err('lastname') }}</div>
                        </div>

                        <div class="col-md-6">
                           <span class="reg-label">Gender <span class="req">*</span></span>
                           <div class="reg-segment" role="radiogroup" aria-label="Gender">
                              <input type="radio" class="btn-check" name="gender" id="reg-male" value="Male" @checked($old('gender', 'Male') === 'Male') required>
                              <label for="reg-male"><i class="bi bi-gender-male"></i> Male</label>
                              <input type="radio" class="btn-check" name="gender" id="reg-female" value="Female" @checked($old('gender') === 'Female')>
                              <label for="reg-female"><i class="bi bi-gender-female"></i> Female</label>
                           </div>
                           <div class="reg-error" data-error="gender">{{ $err('gender') }}</div>
                        </div>
                        <div class="col-md-6">
                           @php
                              $dob = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $old('birthday'), $m) ? ['y' => (int) $m[1], 'm' => (int) $m[2], 'd' => (int) $m[3]] : ['y' => null, 'm' => null, 'd' => null];
                              $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                           @endphp
                           <span class="reg-label" id="reg-dob-label">Date of birth <span class="req">*</span></span>
                           {{-- Real value sent to the server (YYYY-MM-DD), filled from the three lists --}}
                           <input type="hidden" id="reg-birthday" name="birthday" value="{{ $old('birthday') }}">
                           <div class="reg-dob @error('birthday') is-invalid @enderror" id="reg-dob" role="group" aria-labelledby="reg-dob-label">
                              <select class="form-select" id="reg-dob-d" aria-label="Day" autocomplete="bday-day">
                                 <option value="">Day</option>
                                 @for($d = 1; $d <= 31; $d++)<option value="{{ $d }}" @selected($dob['d'] === $d)>{{ $d }}</option>@endfor
                              </select>
                              <select class="form-select" id="reg-dob-m" aria-label="Month" autocomplete="bday-month">
                                 <option value="">Month</option>
                                 @foreach($months as $i => $name)<option value="{{ $i + 1 }}" @selected($dob['m'] === $i + 1)>{{ $name }}</option>@endforeach
                              </select>
                              <select class="form-select" id="reg-dob-y" aria-label="Year" autocomplete="bday-year">
                                 <option value="">Year</option>
                                 @for($y = now()->year - 16; $y >= 1920; $y--)<option value="{{ $y }}" @selected($dob['y'] === $y)>{{ $y }}</option>@endfor
                              </select>
                           </div>
                           <div class="reg-hint" id="reg-age" hidden></div>
                           <div class="reg-error" data-error="birthday">{{ $err('birthday') }}</div>
                        </div>

                        <div class="col-md-6">
                           <label class="reg-label" for="reg-country">Country <span class="req">*</span></label>
                           <select id="reg-country" name="country" class="@error('country') is-invalid @enderror" placeholder="Search your country…" autocomplete="country-name" required>
                              <option value="">Search your country…</option>
                              @foreach($countries as $c)
                              <option value="{{ $c['name'] }}" data-dial="{{ $c['dial'] }}" @selected($old('country') === $c['name'])>{{ $c['name'] }}</option>
                              @endforeach
                           </select>
                           <div class="reg-error" data-error="country">{{ $err('country') }}</div>
                        </div>
                        <div class="col-md-6">
                           <label class="reg-label" for="reg-phone">Mobile number <span class="req">*</span></label>
                           <div class="reg-input reg-phone">
                              <span class="reg-dial" id="reg-dial">{{ $old('countryCode') ?: '+ ··' }}</span>
                              <input type="tel" class="form-control @error('phone') is-invalid @enderror" id="reg-phone" name="phone" value="{{ $old('phone') }}" placeholder="1712 345678" inputmode="tel" autocomplete="tel-national" maxlength="20" required>
                           </div>
                           <div class="reg-error" data-error="phone">{{ $err('phone') }}</div>
                        </div>

                        <div class="col-12">
                           <label class="reg-label" for="reg-email">Email address <span class="req">*</span></label>
                           <div class="reg-input"><i class="bi bi-envelope"></i>
                              <input type="email" class="form-control @error('email') is-invalid @enderror" id="reg-email" name="email" value="{{ $old('email') }}" placeholder="name@hospital.org" autocomplete="email" maxlength="190" required>
                           </div>
                           <div class="reg-hint"><i class="bi bi-info-circle"></i> Your confirmation and registration number will be sent here.</div>
                           <div class="reg-error" data-error="email">{{ $err('email') }}</div>
                        </div>
                     </div>
                  </fieldset>

                  {{-- ================= STEP 2 : PROFESSIONAL ================= --}}
                  <fieldset class="reg-panel" data-step="2">
                     <legend class="reg-panel-title"><i class="bi bi-hospital"></i> Professional details</legend>
                     <p class="reg-panel-sub">Help us understand your role in cancer care.</p>

                     <div class="row g-3">
                        <div class="col-md-6">
                           <label class="reg-label" for="reg-profession">Profession <span class="req">*</span></label>
                           <div class="reg-input"><i class="bi bi-briefcase"></i>
                              <select class="form-select @error('profession') is-invalid @enderror" id="reg-profession" name="profession" required>
                                 <option value="" disabled @selected(!$old('profession'))>Select profession</option>
                                 @foreach($professions as $p)
                                 <option value="{{ $p->profession }}" data-id="{{ $p->id }}" @selected($old('profession') === $p->profession)>{{ $p->profession }}</option>
                                 @endforeach
                              </select>
                           </div>
                           <div class="reg-error" data-error="profession">{{ $err('profession') }}</div>
                        </div>
                        <div class="col-md-6">
                           <label class="reg-label" for="reg-specialty">Speciality</label>
                           <div class="reg-input"><i class="bi bi-diagram-3"></i>
                              <select class="form-select" id="reg-specialty" name="specialty" data-old="{{ $old('specialty') }}" disabled>
                                 <option value="">Select a profession first</option>
                              </select>
                           </div>
                           <div class="reg-error" data-error="specialty">{{ $err('specialty') }}</div>
                        </div>
                        <div class="col-12">
                           <label class="reg-label" for="reg-designation">Designation</label>
                           <div class="reg-input"><i class="bi bi-award"></i>
                              <input type="text" class="form-control" id="reg-designation" name="designation" value="{{ $old('designation') }}" placeholder="e.g. Consultant, Medical Oncology" autocomplete="organization-title" maxlength="150">
                           </div>
                           <div class="reg-error" data-error="designation">{{ $err('designation') }}</div>
                        </div>
                        <div class="col-md-6">
                           <label class="reg-label" for="reg-institute">Institution name <span class="req">*</span></label>
                           <div class="reg-input"><i class="bi bi-building"></i>
                              <input type="text" class="form-control @error('institute') is-invalid @enderror" id="reg-institute" name="institute" value="{{ $old('institute') }}" placeholder="Hospital / university / company" autocomplete="organization" maxlength="190" required>
                           </div>
                           <div class="reg-error" data-error="institute">{{ $err('institute') }}</div>
                        </div>
                        <div class="col-md-6">
                           <label class="reg-label" for="reg-institute-address">Institution address</label>
                           <div class="reg-input"><i class="bi bi-geo-alt"></i>
                              <input type="text" class="form-control" id="reg-institute-address" name="institute_address" value="{{ $old('institute_address') }}" placeholder="City, country" maxlength="255">
                           </div>
                           <div class="reg-error" data-error="institute_address">{{ $err('institute_address') }}</div>
                        </div>
                     </div>
                  </fieldset>

                  {{-- ================= STEP 3 : PHOTO & ID ================= --}}
                  <fieldset class="reg-panel" data-step="3">
                     <legend class="reg-panel-title"><i class="bi bi-camera"></i> Photo &amp; identification</legend>
                     <p class="reg-panel-sub">Your photo is printed on your verification card. We crop and resize it automatically.</p>

                     <div class="row g-4 align-items-start">
                        <div class="col-md-5">
                           <div class="reg-photo" id="reg-photo">
                              <input type="file" id="reg-image" name="image" accept="image/jpeg,image/png,image/webp" class="reg-photo-input">
                              <label for="reg-image" class="reg-drop" id="reg-drop">
                                 <span class="reg-drop-avatar" id="reg-avatar"><i class="bi bi-person-bounding-box"></i></span>
                                 <span class="reg-drop-title">Upload your photo</span>
                                 <span class="reg-drop-sub">Drag &amp; drop or <u>browse</u><br>JPG, PNG or WEBP</span>
                              </label>
                              <div class="reg-photo-actions d-none" id="reg-photo-actions">
                                 <button type="button" class="btn btn-sm btn-outline-primary" id="reg-adjust"><i class="bi bi-crop"></i> Adjust</button>
                                 <button type="button" class="btn btn-sm btn-outline-secondary" id="reg-change"><i class="bi bi-arrow-repeat"></i> Change</button>
                                 <button type="button" class="btn btn-sm btn-outline-danger" id="reg-remove" aria-label="Remove photo"><i class="bi bi-trash"></i></button>
                              </div>
                           </div>
                           <div class="reg-error text-center" data-error="image">{{ $err('image') }}</div>
                        </div>
                        <div class="col-md-7">
                           <ul class="reg-tips">
                              <li><i class="bi bi-check2-circle"></i> Recent, front-facing photo</li>
                              <li><i class="bi bi-check2-circle"></i> Plain background, good light</li>
                              <li><i class="bi bi-check2-circle"></i> Auto-cropped to a square — use <b>Adjust</b> to fine-tune</li>
                           </ul>
                           <div class="row g-3 mt-1">
                              <div class="col-sm-6">
                                 <label class="reg-label" for="reg-idtype">ID type</label>
                                 <div class="reg-input"><i class="bi bi-shield-check"></i>
                                    <select class="form-select" id="reg-idtype" name="identification_type">
                                       <option value="">Select (optional)</option>
                                       @foreach($idTypes as $t)
                                       <option value="{{ $t }}" @selected($old('identification_type') === $t)>{{ $t }}</option>
                                       @endforeach
                                    </select>
                                 </div>
                              </div>
                              <div class="col-sm-6">
                                 <label class="reg-label" for="reg-idno" id="reg-idno-label">ID number</label>
                                 <div class="reg-input"><i class="bi bi-hash"></i>
                                    <input type="text" class="form-control" id="reg-idno" name="identification_no" value="{{ $old('identification_no') }}" placeholder="Optional" maxlength="50" autocomplete="off">
                                 </div>
                                 <div class="reg-error" data-error="identification_no">{{ $err('identification_no') }}</div>
                              </div>
                           </div>
                        </div>
                     </div>
                  </fieldset>

                  {{-- ================= STEP 4 : REVIEW ================= --}}
                  <fieldset class="reg-panel" data-step="4">
                     <legend class="reg-panel-title"><i class="bi bi-clipboard-check"></i> Review &amp; submit</legend>
                     <p class="reg-panel-sub">Please check your details before submitting.</p>

                     <div class="reg-review" id="reg-review"></div>

                     <div class="form-check reg-consent mt-4">
                        <input class="form-check-input" type="checkbox" id="reg-consent" required>
                        <label class="form-check-label" for="reg-consent">I confirm that the information above is correct and agree that BICS may contact me about this event.</label>
                        <div class="reg-error" data-error="consent"></div>
                     </div>
                  </fieldset>

                  {{-- ===== Navigation ===== --}}
                  @include('partials.form-guard', ['form' => 'registration', 'only' => 'captcha'])
                  <div class="reg-nav">
                     <button type="button" class="btn reg-btn-ghost" id="reg-back" hidden><i class="bi bi-arrow-left"></i> Back</button>
                     <span class="reg-nav-count" id="reg-count">Step 1 of 4</span>
                     <button type="button" class="btn reg-btn-primary" id="reg-next">Continue <i class="bi bi-arrow-right"></i></button>
                     <button type="submit" class="btn reg-btn-primary reg-btn-submit" id="reg-submit" hidden><i class="bi bi-send-check"></i> Complete registration</button>
                  </div>
               </form>
            </div>
            </div>
            @endif
            <p class="reg-footnote"><i class="bi bi-lock-fill"></i> Your information is used only for BICS event registration and verification.</p>
         </div>
      </div>
   </div>
</section>

{{-- ===== Crop dialog ===== --}}
<div class="modal fade" id="cropModal" tabindex="-1" aria-labelledby="cropModalTitle" aria-hidden="true">
   <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content reg-modal">
         <div class="modal-header">
            <h5 class="modal-title" id="cropModalTitle"><i class="bi bi-crop"></i> Adjust your photo</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
         </div>
         <div class="modal-body">
            <div class="reg-crop-wrap"><img id="reg-crop-img" alt="Photo to crop"></div>
            <div class="reg-crop-tools">
               <button type="button" class="btn btn-light" data-crop="zoom-in" title="Zoom in"><i class="bi bi-zoom-in"></i></button>
               <button type="button" class="btn btn-light" data-crop="zoom-out" title="Zoom out"><i class="bi bi-zoom-out"></i></button>
               <button type="button" class="btn btn-light" data-crop="rotate" title="Rotate"><i class="bi bi-arrow-clockwise"></i></button>
               <button type="button" class="btn btn-light" data-crop="reset" title="Reset"><i class="bi bi-bootstrap-reboot"></i></button>
            </div>
            <p class="text-muted small text-center mb-0">Drag the photo to position your face inside the circle.</p>
         </div>
         <div class="modal-footer">
            <button type="button" class="btn reg-btn-ghost" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn reg-btn-primary" id="reg-crop-apply"><i class="bi bi-check2"></i> Use this photo</button>
         </div>
      </div>
   </div>
</div>
@endsection

@push('scripts')
@php
   $successTexts = \App\Support\Cms::registrationSuccess();
@endphp
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/tom-select/2.3.1/js/tom-select.complete.min.js"></script>
<script>
   window.REG = {
      specialityUrl: @json(url('/speciality')),
      // Speciality lists per profession (Admin > Form Options)
      specialities: @json($specialities),
      home: @json(url('/')),
      hasServerErrors: @json($errors->any()),
      errorFields: @json(array_keys($errors->messages())),
      // Success pop-up texts: Admin > Website Pages > Site-wide > Registration success message
      success: @json($successTexts),
      eventName: @json(\App\Support\EventSettings::get('event_name')),
      returning: {
         send: @json(route('registration.returning.send')), verify: @json(route('registration.returning.verify')),
         submit: @json(route('registration.returning.submit')), reset: @json(route('registration.returning.reset')),
      },
   };
</script>
<script>
   // "Registration opens in" countdown (only on the upcoming-registration card); reloads when it opens
   (function () {
      var c = document.querySelector('.reg-status-clock');
      if (!c) return;
      var pad = function (n) { return (n < 10 ? '0' : '') + n; };
      (function tick() {
         var left = Math.floor((new Date(c.dataset.target) - new Date()) / 1000);
         if (left <= 0) { location.reload(); return; }
         c.querySelector('[data-u=d]').textContent = Math.floor(left / 86400);
         c.querySelector('[data-u=h]').textContent = pad(Math.floor(left % 86400 / 3600));
         c.querySelector('[data-u=m]').textContent = pad(Math.floor(left % 3600 / 60));
         c.querySelector('[data-u=s]').textContent = pad(left % 60);
         setTimeout(tick, 1000);
      })();
   })();
</script>
<script src="{{ asset('assets/frontend/js/registration.js') }}?v={{ filemtime(base_path('assets/frontend/js/registration.js')) }}"></script>
<script src="{{ asset('assets/frontend/js/registration-returning.js') }}?v={{ @filemtime(base_path('assets/frontend/js/registration-returning.js')) }}"></script>
@endpush
