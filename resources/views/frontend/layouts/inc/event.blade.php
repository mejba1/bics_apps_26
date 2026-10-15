@php
   // Editable in Admin > Pages > Site-wide > Event sidebar & countdown
   $ev = \App\Models\Page::globalContent('event_sidebar') ?? \App\Support\PageSeed::eventSidebar();
@endphp
{{-- Event start (with timezone) from Admin > Events (current event); used by every countdown on the site --}}
<script>window.BICS_EVENT = @json(\App\Support\EventSettings::forBrowser()); window.BICS_EVENT_DATE = window.BICS_EVENT.eventStart || '';</script>
<div class="schedule-sidebar">
    <div class="schedule-sidebar-wrapper">
       <div class="sb-toggle-icon">
          <i class="bi bi-x-lg"></i>
       </div>
       <div class="sb-next-event">
          <h3><img src="{{ asset('assets/frontend') }}/images/logo.png" alt="logo" height="85"> {{ $ev['year_text'] ?? '' }}</h3>
          <div class="sb-next-event-wrap" id="timer">
             <div class="sb-single-count">
                <h3 id="days">00</h3>
                <p>Days</p>
             </div>
             <div class="sb-single-count">
                <h3 id="hours">00</h3>
                <p>Hour</p>
             </div>
             <div class="sb-single-count">
                <h3 id="minutes">00</h3>
                <p>Miniute</p>
             </div>
             <div class="sb-single-count">
                <h3 id="seconds">00</h3>
                <p>Secoend</p>
             </div>
             <div class="col-xl-3 col-2 d-none d-xl-block mx-auto mt-5">
               <div class="nav-right h-100 d-flex align-items-center justify-content-end">
                  <ul class="d-flex align-items-center nav-right-list">
                     @if(!empty($ev['button_text']))
                     <li class="nav-btn">
                        <a class="primary-btn-fill bics-btn" href="{{ \App\Support\Cms::link($ev['button_url'] ?? '') }}"> {{ $ev['button_text'] }}</a>
                     </li>
                     @endif
                  </ul>
               </div>
            </div>
          </div>
          
       </div>
    </div>
 </div>