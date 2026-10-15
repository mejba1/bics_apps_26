{{-- One menu for everyone: each item shows only if the user's role has the permission (Admin > Roles & Permissions) --}}
@php
   $is = fn (...$p) => request()->is(...$p);
   $nav = fn ($active) => 'nav-link' . ($active ? '' : ' collapsed');
@endphp
<aside id="sidebar" class="sidebar">
  <ul class="sidebar-nav" id="sidebar-nav">

    @can('registrations.view')
    <li class="nav-item">
      <a class="{{ $nav($is('home')) }}" href="{{ url('/home') }}"><i class="bi bi-grid"></i><span>Dashboard</span></a>
    </li>
    @endcan

    @if(Gate::any(['attendees.verify', 'attendees.gifts']))
    <li class="nav-item">
      <a class="{{ $nav($is('verify-user', 'gift-distribute', 'checkin-desk*')) }}" data-bs-target="#nav-attendees" data-bs-toggle="collapse" href="#">
        <i class="bi bi-person-lines-fill"></i><span>Attendee Activities</span><i class="bi bi-chevron-down ms-auto"></i>
      </a>
      <ul id="nav-attendees" class="nav-content collapse {{ $is('verify-user', 'gift-distribute', 'checkin-desk*') ? 'show' : '' }}" data-bs-parent="#sidebar-nav">
        @can('attendees.verify')<li><a href="{{ route('checkin.desk') }}" class="{{ $is('checkin-desk*') ? 'active' : '' }}"><i class="bi bi-qr-code-scan"></i><span>Self Check-in</span></a></li>@endcan
        @can('attendees.verify')<li><a href="{{ url('/verify-user') }}" class="{{ $is('verify-user') ? 'active' : '' }}"><i class="bi bi-person-check-fill"></i><span>Verify Attendee</span></a></li>@endcan
        @can('attendees.gifts')<li><a href="{{ url('/gift-distribute') }}" class="{{ $is('gift-distribute') ? 'active' : '' }}"><i class="bi bi-gift-fill"></i><span>Gift Distribution</span></a></li>@endcan
      </ul>
    </li>
    @endif

    @can('registrations.create')
    <li class="nav-item">
      <a class="{{ $nav($is('registrations/new')) }}" href="{{ route('registrations.create') }}"><i class="bi bi-person-plus"></i><span>New Registration</span></a>
    </li>
    @elsecan('registrations.view')
    <li class="nav-item">
      <a class="nav-link collapsed" href="{{ url('/registration') }}" target="_blank"><i class="bi bi-person-plus"></i><span>Public Registration Form</span></a>
    </li>
    @endcan

    @php $raffleHost = \App\Support\Raffle::isHost(Auth::user()); @endphp
    @if(Gate::allows('raffle.prizes') || $raffleHost)
    <li class="nav-heading">Raffle Draw</li>
    <li class="nav-item">
      <a class="{{ $nav($is('add-prize', 'prize-winner')) }}" data-bs-target="#nav-raffle" data-bs-toggle="collapse" href="#">
        <i class="ri-user-4-fill"></i><span>Raffle Draw</span><i class="bi bi-chevron-down ms-auto"></i>
      </a>
      <ul id="nav-raffle" class="nav-content collapse {{ $is('add-prize', 'prize-winner') ? 'show' : '' }}" data-bs-parent="#sidebar-nav">
        @can('raffle.prizes')
        <li><a href="{{ url('/add-prize') }}" class="{{ $is('add-prize') ? 'active' : '' }}"><i class="bi bi-arrow-right"></i><span>Raffle Setup</span></a></li>
        <li><a href="{{ url('/prize-winner') }}" class="{{ $is('prize-winner') ? 'active' : '' }}"><i class="bi bi-arrow-right"></i><span>Prize Winners</span></a></li>
        @endcan
        @if($raffleHost)
        <li><a href="{{ url('/raffle') }}" target="_blank"><i class="bi bi-arrow-right"></i><span>Start Raffle</span></a></li>
        @endif
      </ul>
    </li>
    @endif

    @if(Gate::any(['registrations.export', 'reports.analytics']))
    <li class="nav-heading">Reports</li>
    @can('registrations.export')
    <li class="nav-item">
      <a class="{{ $nav($is('reports')) }}" href="{{ route('reports.index') }}"><i class="bi bi-file-earmark-bar-graph"></i><span>Reports</span></a>
    </li>
    <li class="nav-item">
      <a class="nav-link collapsed" href="{{ route('reports.download', ['venue' => 'pinned']) }}" title="All registrations of your pinned event"><i class="bi bi-pin-angle"></i><span>{{ \App\Support\PinnedEvent::get() }} (PDF)</span></a>
    </li>
    @endcan
    @can('reports.analytics')
    <li class="nav-item"><a class="{{ $nav($is('analytics*')) }}" href="{{ route('analytics.index') }}"><i class="bi bi-graph-up-arrow"></i><span>Participant Analytics</span></a></li>
    @endcan
    @endif

    @if(Gate::any(['participants.manage', 'nfc.manage', 'cards.design', 'cards.print']))
    <li class="nav-heading">Participants</li>
    @can('participants.manage')
    <li class="nav-item"><a class="{{ $nav($is('participants*') && !$is('participants/values*')) }}" href="{{ route('participants.index') }}"><i class="bi bi-person-vcard"></i><span>Participant Profiles</span></a></li>
    <li class="nav-item"><a class="{{ $nav($is('participants/values*')) }}" href="{{ route('participants.values') }}"><i class="bi bi-buildings"></i><span>Organizations &amp; Categories</span></a></li>
    @endcan
    @can('nfc.manage')
    <li class="nav-item"><a class="{{ $nav($is('nfc-cards*') && !$is('nfc-cards/station')) }}" href="{{ route('nfc.index') }}"><i class="bi bi-credit-card-2-front"></i><span>NFC Cards</span></a></li>
    <li class="nav-item"><a class="{{ $nav($is('nfc-cards/station')) }}" href="{{ route('nfc.station') }}"><i class="bi bi-broadcast"></i><span>NFC Encoding Station</span></a></li>
    @endcan
    @can('cards.design')
    <li class="nav-item"><a class="{{ $nav($is('card-studio*')) }}" href="{{ route('card-studio.index') }}"><i class="bi bi-palette"></i><span>Card Design Studio</span></a></li>
    @endcan
    @can('cards.print')
    <li class="nav-item"><a class="{{ $nav($is('card-print*')) }}" href="{{ route('card-print.index') }}"><i class="bi bi-printer"></i><span>Card Printing</span></a></li>
    @endcan
    @endif

    @can('birthdays.manage')
    @php $bdToday = rescue(fn () => \App\Support\Birthdays::on(\App\Support\Birthdays::now())->count(), 0, false); @endphp
    <li class="nav-heading">Engagement</li>
    <li class="nav-item"><a class="{{ $nav($is('birthdays*')) }}" href="{{ route('birthdays.index') }}"><i class="bi bi-cake2"></i><span>Birthdays</span>@if($bdToday)<span class="badge rounded-pill ms-auto" style="background:#b22e6b" title="{{ $bdToday }} birthday{{ $bdToday === 1 ? '' : 's' }} today">{{ $bdToday }}</span>@endif</a></li>
    @endcan

    @if(Gate::any(['certificates.manage', 'idcards.assign']))
    <li class="nav-heading">Certificates &amp; ID Cards</li>
    @can('certificates.manage')
    <li class="nav-item"><a class="{{ $nav($is('certificates*')) }}" href="{{ route('certificates.index') }}"><i class="bi bi-award"></i><span>Certificates</span></a></li>
    @endcan
    @can('idcards.assign')
    <li class="nav-item"><a class="{{ $nav($is('id-cards*')) }}" href="{{ route('idcards.index') }}"><i class="bi bi-person-badge"></i><span>ID Cards</span></a></li>
    @endcan
    @endif

    @if(Gate::any(['event.settings', 'form.options']))
    <li class="nav-heading">Registration</li>
    @can('event.settings')
    <li class="nav-item"><a class="{{ $nav($is('events*', 'event-settings*')) }}" href="{{ route('events.index') }}"><i class="bi bi-calendar2-week"></i><span>Events</span></a></li>
    <li class="nav-item"><a class="{{ $nav($is('email-template*')) }}" href="{{ route('email-template.edit') }}"><i class="bi bi-envelope-open"></i><span>Email Template</span></a></li>
    @endcan
    @can('form.options')
    <li class="nav-item"><a class="{{ $nav($is('form-options')) }}" href="{{ route('form-options.index') }}"><i class="bi bi-ui-checks"></i><span>Form Options</span></a></li>
    @endcan
    @endif

    @if(Gate::any(['website.gallery', 'website.pages', 'website.menu']))
    <li class="nav-heading">Website</li>
    @can('website.gallery')
    <li class="nav-item"><a class="{{ $nav($is('gallery-manager*')) }}" href="{{ route('gallery.index') }}"><i class="bi bi-images"></i><span>Gallery</span></a></li>
    @endcan
    @can('website.pages')
    <li class="nav-item"><a class="{{ $nav($is('cms*')) }}" href="{{ route('cms.index') }}"><i class="bi bi-file-earmark-richtext"></i><span>Website Pages</span></a></li>
    <li class="nav-item"><a class="{{ $nav($is('documents*')) }}" href="{{ route('documents.index') }}"><i class="bi bi-file-earmark-pdf"></i><span>Documents (PDF links)</span></a></li>
    @endcan
    @can('website.menu')
    <li class="nav-item"><a class="{{ $nav($is('menu')) }}" href="{{ url('/menu') }}"><i class="bi bi-list-nested"></i><span>Website Menu</span></a></li>
    @endcan
    @endif

    @if(Gate::any(['users.manage', 'roles.manage']))
    <li class="nav-heading">Administration</li>
    @can('users.manage')
    <li class="nav-item"><a class="{{ $nav($is('users')) }}" href="{{ route('users.index') }}"><i class="bi bi-people"></i><span>Users</span></a></li>
    @endcan
    @can('roles.manage')
    <li class="nav-item"><a class="{{ $nav($is('roles')) }}" href="{{ route('roles.index') }}"><i class="bi bi-shield-lock"></i><span>Roles &amp; Permissions</span></a></li>
    @endcan
    @endif

    @if(Gate::any(['system.mail', 'system.backup', 'audit.view', 'system.settings']))
    <li class="nav-heading">System</li>
    @can('system.mail')
    <li class="nav-item"><a class="{{ $nav($is('system/mail')) }}" href="{{ route('system.mail') }}"><i class="bi bi-hdd-network"></i><span>Email Server (SMTP)</span></a></li>
    @endcan
    @can('system.backup')
    <li class="nav-item"><a class="{{ $nav($is('system/backups*')) }}" href="{{ route('backups.index') }}"><i class="bi bi-server"></i><span>Backup &amp; Export</span></a></li>
    @endcan
    @can('audit.view')
    <li class="nav-item"><a class="{{ $nav($is('system/audit*')) }}" href="{{ route('audit.index') }}"><i class="bi bi-journal-text"></i><span>Audit Log</span></a></li>
    @endcan
    @can('system.settings')
    <li class="nav-item"><a class="{{ $nav($is('system/settings*')) }}" href="{{ route('system.settings.index') }}"><i class="bi bi-sliders"></i><span>Platform Settings</span></a></li>
    @endcan
    @endif

    <li class="nav-heading">My Account</li>
    <li class="nav-item"><a class="{{ $nav($is('account')) }}" href="{{ route('account') }}"><i class="bi bi-person-circle"></i><span>My Account</span></a></li>
    <li class="nav-item">
      <form action="{{ route('logout') }}" method="POST" class="m-0">
        @csrf
        @method('DELETE')
        <button type="submit" class="nav-link collapsed w-100 border-0 bg-transparent text-start"><i class="bi bi-box-arrow-right"></i><span>Logout</span></button>
      </form>
    </li>
  </ul>
</aside>
