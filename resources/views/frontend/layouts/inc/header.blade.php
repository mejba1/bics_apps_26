@php
   // Menu: Admin > Website Menu. Event details and registration state: Admin > Events (current event).
   use App\Support\EventSettings as Ev;
   $menu = \App\Models\MenuItem::tree();
   $reg = Ev::registration();
   $footer = \App\Models\Page::globalContent('footer') ?? \App\Support\PageSeed::footer();
   $email = \App\Support\Cms::lines($footer['emails'] ?? '')[1] ?? \App\Support\Cms::lines($footer['emails'] ?? '')[0] ?? null;
   $regLabel = ['open' => 'Registration open', 'upcoming' => 'Registration opens soon', 'closed' => 'Registration closed'][$reg['state']];
   // participant dashboard (/my): sign-in button, or "My profile" once signed in
   $portal = \App\Support\ParticipantAccess::settings()['portal'];
   $me = $portal ? Auth::guard('participant')->user() : null;
@endphp
<a class="visually-hidden-focusable position-absolute top-0 start-0 m-2 btn btn-light" href="#main">Skip to content</a>
<div class="bx-topbar">
   <div class="container">
      <div class="bx-topbar-meta">
         <span><i class="bi bi-calendar-event"></i>{{ Ev::get('event_short') }} · {{ Ev::datesLabel() }}</span>
         @if(Ev::get('event_venue'))<span class="bx-topbar-extra"><i class="bi bi-geo-alt"></i>{{ Ev::get('event_venue') }}</span>@endif
      </div>
      <div class="bx-topbar-meta">
         @if($email)<a class="bx-topbar-extra" href="mailto:{{ $email }}"><i class="bi bi-envelope"></i>{{ $email }}</a>@endif
         <span class="bx-pill {{ $reg['state'] }}"><span class="dot"></span>{{ $regLabel }}</span>
      </div>
   </div>
</div>
<header class="bx-header">
   <div class="container">
      <a class="bx-logo" href="{{ route('home') }}" aria-label="BICS home"><img src="{{ asset('assets/frontend/images/logo.png') }}" alt="BICS — Stronger Together" width="160" height="52"></a>
      <nav class="bx-nav" aria-label="Main">
         <ul>
            @foreach($menu as $item)
            <li>
               <a href="{{ $item->href }}" class="{{ $item->is_current ? 'is-active' : '' }}" @if($item->is_current) aria-current="page" @endif @if($item->new_tab) target="_blank" rel="noopener" @endif>
                  {{ $item->title }}@if($item->children->isNotEmpty())<i class="bi bi-chevron-down bx-caret" aria-hidden="true"></i>@endif
               </a>
               @if($item->children->isNotEmpty())
               <ul class="bx-sub">
                  @foreach($item->children as $child)
                  <li><a href="{{ $child->href }}" @if($child->new_tab) target="_blank" rel="noopener" @endif>{{ $child->title }}</a></li>
                  @endforeach
               </ul>
               @endif
            </li>
            @endforeach
         </ul>
      </nav>
      <div class="bx-header-cta">
         @if($me)
         <a class="bx-btn bx-btn-outline bx-btn-sm bx-me" href="{{ route('my.home') }}" title="My participant profile" aria-label="My profile"><span class="bx-me-av" aria-hidden="true">{{ $me->initials ?: 'Me' }}</span><span>My profile</span></a>
         @elseif($portal)
         <a class="bx-btn bx-btn-outline bx-btn-sm" href="{{ route('my.login') }}" title="Participant login — your profile, ID card and certificates" aria-label="Participant login"><i class="bi bi-person-circle"></i><span>Participant login</span></a>
         @endif
         <a class="bx-btn bx-btn-accent bx-btn-sm" href="{{ route('registration') }}"><i class="bi bi-person-plus"></i><span>{{ $reg['state'] === 'closed' ? 'Registration' : 'Register now' }}</span></a>
         <button class="bx-burger" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="bx-drawer"><i class="bi bi-list"></i></button>
      </div>
   </div>
   <div class="bx-brandbar" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
</header>

<div class="bx-drawer" id="bx-drawer" aria-hidden="true">
   <div class="bx-drawer-backdrop" data-bx-close></div>
   <div class="bx-drawer-panel" role="dialog" aria-modal="true" aria-label="Menu">
      <div class="bx-drawer-head">
         <img src="{{ asset('assets/frontend/images/logo.png') }}" alt="BICS">
         <button class="bx-drawer-close" type="button" data-bx-close aria-label="Close menu"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="bx-drawer-body">
         <ul>
            @foreach($menu as $item)
            <li>
               @if($item->children->isNotEmpty())
                  <button class="bx-acc" type="button" aria-expanded="false">{{ $item->title }} <i class="bi bi-chevron-down"></i></button>
                  <ul class="bx-drawer-sub">
                     @foreach($item->children as $child)
                     <li><a href="{{ $child->href }}" @if($child->new_tab) target="_blank" rel="noopener" @endif>{{ $child->title }}</a></li>
                     @endforeach
                  </ul>
               @else
                  <a href="{{ $item->href }}" @if($item->new_tab) target="_blank" rel="noopener" @endif>{{ $item->title }}</a>
               @endif
            </li>
            @endforeach
         </ul>
      </div>
      <div class="bx-drawer-foot">
         <span class="bx-pill {{ $reg['state'] }}" style="justify-self:start"><span class="dot"></span>{{ $regLabel }}</span>
         <a class="bx-btn bx-btn-accent" href="{{ route('registration') }}"><i class="bi bi-person-plus"></i> Register now</a>
         @if($me)
         <a class="bx-btn bx-btn-outline" href="{{ route('my.home') }}"><i class="bi bi-person-badge"></i> My profile</a>
         @elseif($portal)
         <a class="bx-btn bx-btn-outline" href="{{ route('my.login') }}"><i class="bi bi-person-circle"></i> Participant login</a>
         <span class="bx-drawer-note">Your profile, QR code, ID card and certificates</span>
         @endif
      </div>
   </div>
</div>
