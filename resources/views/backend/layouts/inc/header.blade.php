  <style type="text/css">
      
      .blinking-icon {
  animation: blink 1.1s infinite;
}

@keyframes blink {
  0% {
    opacity: 1;
    color: gold;
  }
  50% {
    opacity: 0;
    color: red;
  }
  100% {
    opacity: 1;
    color: gold;
  }
}

  </style>
  <!-- ======= Header ======= -->
  <header id="header" class="header fixed-top d-flex align-items-center">
    <div class="d-flex align-items-center justify-content-between">
      <a href="{{ url('/home') }}" class="logo d-flex align-items-center">
        <img src="{{ asset('assets/backend') }}/img/logo.png" alt="">
      </a>
      <i class="bi bi-list toggle-sidebar-btn"></i>
    </div><!-- End Logo -->

    {{-- <div class="search-bar">
      <form class="search-form d-flex align-items-center" method="POST" action="#">
        <input type="text" name="query" placeholder="Search" title="Enter search keyword">
        <button type="submit" title="Search"><i class="bi bi-search"></i></button>
      </form>
    </div> --}}
    <!-- End Search Bar -->

    <nav class="header-nav ms-auto">
      <ul class="d-flex align-items-center">

        <!--<li class="nav-item d-block d-lg-block">
          <a class="nav-link nav-icon search-bar-toggle " href="#">
            <i class="bi bi-search"></i>
          </a>
        </li>-->
         {{-- Pinned event: every admin page opens on it (App\Support\PinnedEvent) --}}
         @php
           $pinVenue = \App\Support\PinnedEvent::get();
           $pinList = rescue(fn () => \App\Support\PinnedEvent::venues(), collect(), false);
           $pinNow = $pinList->firstWhere('venue', $pinVenue);
         @endphp
         <li class="nav-item dropdown me-2">
          <button type="button" class="ad-header-pill ad-pin-pill" data-bs-toggle="dropdown" aria-expanded="false" title="Pinned event — every admin page opens on it">
            <i class="bi bi-pin-angle-fill"></i><span class="ad-pin-name">{{ $pinNow->name ?? $pinVenue }}</span><i class="bi bi-chevron-down small"></i>
          </button>
          <div class="dropdown-menu dropdown-menu-end ad-pin-menu">
            <div class="ad-pin-head"><b>Pinned event</b><small>Every admin page opens on this event. New registrations still go to the current event.</small></div>
            @foreach($pinList as $v)
              <button type="button" class="ad-pin-item {{ $v->venue === $pinVenue ? 'is-on' : '' }}" data-pin="{{ $v->venue }}">
                <span class="ad-pin-dot"><i class="bi {{ $v->venue === $pinVenue ? 'bi-pin-angle-fill' : 'bi-circle' }}"></i></span>
                <span class="ad-pin-txt"><b>{{ $v->name }}</b><small>{{ number_format($v->total) }} {{ \Illuminate\Support\Str::plural('registration', $v->total) }}@if($v->name !== $v->venue) · {{ $v->venue }}@endif</small></span>
                @if($v->locked)<span class="ad-pin-badge is-muted" title="Ended or archived: read-only"><i class="bi bi-lock-fill"></i> Read-only</span>
                @elseif(($v->phase ?? '') === 'live')<span class="ad-pin-badge is-live">Live</span>
                @elseif($v->is_current)<span class="ad-pin-badge">Current</span>
                @elseif($v->status === 'draft')<span class="ad-pin-badge is-muted">Draft</span>@endif
              </button>
            @endforeach
          </div>
         </li>
         <li class="nav-item me-2 d-none d-sm-block">{{-- on phones: "Visit website" is in the account menu --}}
          <a class="ad-header-pill is-outline" href="{{ url('/') }}" target="_blank" rel="noopener" title="Open the public website in a new tab ({{ preg_replace('#^https?://#', '', rtrim(url('/'), '/')) }})">
            <i class="bi bi-globe2"></i><span class="d-none d-md-inline">Visit website</span><i class="bi bi-box-arrow-up-right small d-none d-md-inline"></i>
          </a>
         </li>
         <li class="nav-item me-2">
          <button type="button" class="ad-theme-toggle" id="adThemeToggle" title="Switch to dark mode" aria-label="Switch to dark mode">
            <i class="bi bi-moon-stars-fill"></i><i class="bi bi-sun-fill"></i>
          </button>
         </li>
         @if(\App\Support\Raffle::isHost(Auth::user()) && !\App\Support\EventLock::isLocked(\App\Support\Raffle::event()))
         <li class="nav-item me-2">
          <a class="ad-header-pill" href="{{ url('/raffle') }}" target="_blank" title="Open the raffle draw in a new tab">
            <i class="bi bi-trophy"></i><span class="d-none d-sm-inline">Start Raffle Draw</span>
          </a>
        </li>
        @endif
        <!-- End Search Icon-->
     {{-- Notifications (App\Support\AdminNotifications): what this person's permissions cover, for their pinned event --}}
     @php [$ntItems, $ntUnread] = rescue(fn () => \App\Support\AdminNotifications::forUser(Auth::user()), [[], 0], false); @endphp
        <li class="nav-item dropdown me-2">
          <button type="button" class="ad-bell" id="adBell" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
            aria-label="Notifications{{ $ntUnread ? ' — ' . $ntUnread . ' new' : '' }}" title="Notifications">
            <i class="bi {{ $ntUnread ? 'bi-bell-fill' : 'bi-bell' }}"></i>
            @if($ntUnread)<span class="ad-bell-count" id="adBellCount">{{ $ntUnread > 9 ? '9+' : $ntUnread }}</span>@endif
          </button>
          <div class="dropdown-menu dropdown-menu-end ad-nt-menu" aria-labelledby="adBell">
            <div class="ad-nt-head"><b>Notifications</b>
              <span>@if($ntUnread){{ $ntUnread }} new @else You're all caught up @endif</span></div>
            <div class="ad-nt-list">
              @forelse(array_slice($ntItems, 0, 8) as $n)
                <a class="ad-nt-item {{ $n['new'] ? 'is-new' : '' }}" href="{{ $n['url'] }}">
                  <span class="ad-nt-ic tone-{{ $n['tone'] }}"><i class="bi {{ $n['icon'] }}"></i></span>
                  <span class="ad-nt-tx"><b>{{ $n['title'] }}</b><small>{{ \Illuminate\Support\Str::limit($n['text'], 90) }}</small></span>
                  <span class="ad-nt-at">{{ \App\Support\AdminNotifications::ago($n['at']) }}</span>
                </a>
              @empty
                <div class="ad-nt-empty"><i class="bi bi-bell-slash"></i><b>Nothing new</b><span>Registrations, check-ins and website messages show up here.</span></div>
              @endforelse
            </div>
            <a class="ad-nt-all" href="{{ route('notifications.index') }}">View all notifications <i class="bi bi-arrow-right"></i></a>
          </div>
        </li>
        <li class="nav-item dropdown pe-3">

          <a class="nav-link nav-profile d-flex align-items-center pe-0" href="#" data-bs-toggle="dropdown">
            {{-- Initials of the signed-in user --}}
            @php $initials = collect(explode(' ', trim(Auth::user()->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode(''); @endphp
            <span class="rounded-circle d-inline-flex align-items-center justify-content-center fw-bold text-white" style="width:36px;height:36px;background:linear-gradient(135deg,#0e4ca1,#b22e6b);font-size:14px">{{ $initials }}</span>
            
            <span class="d-none d-md-block dropdown-toggle ps-2">{{ Auth::user()->name }}</span>
          </a><!-- End Profile Iamge Icon -->

          <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile">
            <li class="dropdown-header">
              <h6>{{ Auth::user()->name }}</h6>
              <span>{{ Auth::user()->role_name }}</span>
            </li>
            <li>
              <hr class="dropdown-divider">
            </li>

            <li>
              <a class="dropdown-item d-flex align-items-center" href="{{ route('account') }}">
                <i class="bi bi-person"></i>
                <span>My Account</span>
              </a>
            </li>
            <li>
              <a class="dropdown-item d-flex align-items-center" href="{{ url('/') }}" target="_blank" rel="noopener">
                <i class="bi bi-globe2"></i>
                <span>Visit website</span>
              </a>
            </li>
            <li>
              <hr class="dropdown-divider">
            </li>
            @can('users.manage')
            <li>
              <a class="dropdown-item d-flex align-items-center" href="{{ route('users.index') }}">
                <i class="bi bi-people"></i>
                <span>Users</span>
              </a>
            </li>
            @endcan
            <li>
              <hr class="dropdown-divider">
            </li>
              <hr class="dropdown-divider">
            </li>
            <form action="{{ route('logout') }}" method="POST">
              @csrf
              @method('DELETE')
            <li>
              <a class="dropdown-item d-flex align-items-center" href="">
                <i class="bi bi-box-arrow-right"></i>
                <button type="submit" class="btn">Logout</button>
              </a>
            
            </li>
          </form>
          </ul><!-- End Profile Dropdown Items -->
        </li><!-- End Profile Nav -->

      </ul>
    </nav><!-- End Icons Navigation -->

  </header><!-- End Header -->