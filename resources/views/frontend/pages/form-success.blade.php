@extends('frontend.layouts.content')
@section('title') Registration complete @endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/frontend/css/registration.css') }}?v={{ filemtime(base_path('assets/frontend/css/registration.css')) }}">
@endpush

@section('content')
<section class="reg-page">
   <div class="reg-hero">
      <div class="container">
         <div class="reg-hero-inner">
            <span class="reg-eyebrow"><i class="bi bi-patch-check-fill"></i> Registration complete</span>
            <h1 class="reg-hero-title">{{ \App\Support\EventSettings::get('event_name') }}</h1>
            <div class="reg-hero-meta">
               <span><i class="bi bi-geo-alt-fill"></i> {{ \App\Support\EventSettings::get('event_venue') }}</span>
               <span><i class="bi bi-calendar-event-fill"></i> {{ \App\Support\EventSettings::datesLabel() }}</span>
            </div>
         </div>
      </div>
   </div>

   <div class="container reg-container">
      <div class="row justify-content-center">
         <div class="col-lg-6 col-md-8">
            <div class="reg-card text-center p-4 p-md-5">
               @if(Session::get('code'))
                  @php
                     // Texts: Admin > Website Pages > Site-wide > Registration success message
                     $t = \App\Support\Cms::registrationSuccess();
                     $values = ['name' => Session::get('reg_name'), 'email' => Session::get('reg_email'), 'code' => Session::get('code'), 'event' => \App\Support\EventSettings::get('event_name')];
                     // Escape the admin text, then show the filled-in values in bold
                     $fill = fn ($text) => nl2br(preg_replace_callback('/\{(name|email|code|event)\}/', fn ($m) => '<b>' . e($values[$m[1]] ?? '') . '</b>', e((string) $text)));
                     $sent = Session::get('email_sent', !str_contains((string) Session::get('mailInfo'), 'could not'));
                  @endphp
                  <div class="reg-success-icon"><i class="bi bi-check-lg"></i></div>
                  <h2 class="fw-bold mb-1">{!! $fill($t['title']) !!}</h2>
                  @if($t['welcome'])<p class="text-muted mb-4">{!! $fill($t['welcome']) !!}</p>@endif

                  @if($t['show_code'])
                  <div class="reg-ticket">
                     @if($t['code_label'])<small>{{ $t['code_label'] }}</small>@endif
                     <div class="reg-ticket-code">{{ Session::get('code') }}</div>
                  </div>
                  @endif

                  @if($t['show_email'])
                  <div class="reg-mail {{ $sent ? 'ok' : 'warn' }} mb-3">
                     <i class="bi {{ $sent ? 'bi-envelope-check-fill' : 'bi-exclamation-circle-fill' }}"></i>
                     <div>{!! $fill($sent ? $t['email_sent'] : $t['email_failed']) !!}</div>
                  </div>
                  @endif

                  @if($t['note'])<p class="small text-muted mb-4">{!! $fill($t['note']) !!}</p>@endif

                  <div class="d-flex flex-wrap justify-content-center gap-2">
                     @if($t['show_download'] && Session::get('card_url'))
                     <a href="{{ Session::get('card_url') }}" target="_blank" rel="noopener" class="btn reg-btn-ghost"><i class="bi bi-download"></i> {{ $t['download_label'] ?: 'Download card' }}</a>
                     @endif
                     <a href="{{ $t['done_url_full'] ?: url('/registration') }}" class="btn reg-btn-primary">{{ $t['done_label'] ?: 'Done' }}</a>
                  </div>
               @else
                  {{-- Opened directly (e.g. page refreshed): nothing to show --}}
                  <div class="reg-success-icon is-muted"><i class="bi bi-person-badge"></i></div>
                  <h2 class="fw-bold mb-2">Register for {{ \App\Support\EventSettings::get('event_short') }}</h2>
                  <p class="text-muted mb-4">Your registration number was shown after you registered and sent to your email.</p>
                  <a href="{{ url('/registration') }}" class="btn reg-btn-primary"><i class="bi bi-pencil-square"></i> Go to registration</a>
               @endif
            </div>
         </div>
      </div>
   </div>
</section>
@endsection
