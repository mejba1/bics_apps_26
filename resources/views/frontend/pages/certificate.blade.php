@extends('frontend.layouts.content')
@section('title')Certificate {{ $cert->number }}@endsection
@section('meta')
      <meta name="robots" content="noindex, nofollow">
@endsection
@push('styles')
<style>
   .cv-card { max-width: 720px; margin: 0 auto; background: #fff; border: 1px solid var(--bx-line); border-radius: 26px; box-shadow: var(--bx-shadow-lg); overflow: hidden; }
   .cv-top { background: linear-gradient(120deg, var(--bx-navy), var(--bx-blue) 60%, var(--bx-magenta)); color: #fff; padding: 30px 32px; display: flex; gap: 18px; align-items: center; }
   .cv-badge { width: 64px; height: 64px; border-radius: 20px; background: rgba(255,255,255,.15); display: flex; align-items: center; justify-content: center; font-size: 32px; flex: 0 0 64px; }
   .cv-top h1 { color: #fff; font-size: 1.5rem; font-weight: 800; margin: 0 0 4px; }
   .cv-top p { margin: 0; color: rgba(255,255,255,.8); }
   .cv-body { padding: 28px 32px 32px; }
   .cv-row { display: grid; grid-template-columns: 170px 1fr; gap: 6px 18px; padding: 12px 0; border-bottom: 1px solid var(--bx-soft-2); }
   .cv-row span { color: var(--bx-muted); font-weight: 600; }
   .cv-row b { color: var(--bx-ink); }
   @media (max-width: 575.98px) { .cv-row { grid-template-columns: 1fr; } .cv-top { flex-direction: column; align-items: flex-start; } }
</style>
@endpush
@section('content')
<section class="bx-section is-soft">
   <div class="container">
      <div class="cv-card">
         <div class="cv-top">
            <div class="cv-badge"><i class="bi bi-patch-check-fill"></i></div>
            <div>
               <h1>Genuine certificate of participation</h1>
               <p>Issued by the Beacon International Cancer Summit</p>
            </div>
         </div>
         <div class="cv-body">
            <div class="cv-row"><span>Awarded to</span><b>{{ trim(($r->title ? $r->title . ' ' : '') . $r->firstname . ' ' . $r->lastname) }}</b></div>
            <div class="cv-row"><span>Event</span><b>{{ $event->event_name }}</b></div>
            @if($event->datesLabel())<div class="cv-row"><span>Dates</span><b>{{ $event->datesLabel() }}</b></div>@endif
            @if($event->event_venue)<div class="cv-row"><span>Venue</span><b>{{ $event->event_venue }}</b></div>@endif
            <div class="cv-row"><span>Certificate number</span><b>{{ $cert->number }}</b></div>
            <div class="cv-row"><span>Issued</span><b>{{ \Carbon\Carbon::parse($cert->issued_at)->format('j F Y') }}</b></div>
            <div class="d-flex flex-wrap gap-2 mt-4">
               <a class="bx-btn bx-btn-primary" href="{{ route('certificate.pdf', $cert->token) }}"><i class="bi bi-download"></i> Download certificate (PDF)</a>
               <a class="bx-btn bx-btn-outline" href="{{ route('home') }}">Visit the BICS website</a>
            </div>
         </div>
      </div>
   </div>
</section>
@endsection
