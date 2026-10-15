<aside id="sidebar" class="sidebar">
    <ul class="sidebar-nav" id="sidebar-nav">
      <li class="nav-item">
        <a class="nav-link " href="{{ url('/home') }}">
          <i class="bi bi-grid"></i>
          <span>Dashboard</span>
        </a>
      </li><!-- End Dashboard Nav -->
      <li class="nav-item">
        <a class="nav-link collapsed" href="https://bics.org/registration" target="_blank">
          <i class="bi bi-person-plus"></i>
          <span>Add New Register</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link collapsed" href="{{ url('/verify-user') }}">
          <i class="bi bi-person-check-fill"></i><span>User Verify</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link collapsed" href="{{ url('/gift-distribute') }}">
          <i class="bi bi-gift-fill"></i><span>Gift Distribute</span>
        </a>
      </li>

      <!--====Raffle Draw System=====-->
      <hr/>
      <li class="nav-heading">Raffle Draw</li>
      
      <li class="nav-item">
        <a class="nav-link collapsed {{ (request()->is('add-prize')) ? 'active':'' }}" data-bs-target="#raffle" data-bs-toggle="collapse" href="#">
          <i class="ri-user-4-fill"></i><span>Raffle Draw</span><i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <ul id="raffle" class="nav-content collapse " data-bs-parent="#sidebar-nav">
          <li>
            <a href="{{ url('/add-prize') }}" >
              <i class="bi bi-arrow-right"></i><span>Add Prize </span>
            </a>
          </li>
          <li>
            <a href="{{ url('/prize-winner') }}">
              <i class="bi bi-arrow-right"></i><span>Prize Winner</span>
            </a>
          </li>
          <li>
            <a href="{{ url('/raffle') }}" target="_blank">
              <i class="bi bi-arrow-right"></i><span>Start Raffle</span>
            </a>
          </li>
        </ul>
      </li>
      <!--====Raffle Draw End=======-->
      
      <!-- End Components Nav -->
      {{-- <hr/>
      <li class="nav-heading">REPORT</li>
      
      <li class="nav-item">
        <a class="nav-link collapsed" data-bs-target="#report" data-bs-toggle="collapse" href="#">
          <i class="bi bi-bar-chart"></i><span>Report</span><i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <ul id="report" class="nav-content collapse " data-bs-parent="#sidebar-nav">
          <li>
            <a href="#">
              <i class="bi bi-circle"></i><span>Report Download </span>
            </a>
          </li>
          <li>
            <a href="#">
              <i class="bi bi-circle"></i><span>All Report</span>
            </a>
          </li>
        </ul>
      </li> --}}

      <!-- End Report -->
      
      <hr/>
      <li class="nav-heading">ACCOUNT MANAGEMENT</li>
      <li class="nav-item">
        <a class="nav-link collapsed" href="#">
          <i class="bi bi-person"></i>
          <span>My Profile</span>
        </a>
      </li>
      {{-- <li class="nav-item">
        <a class="nav-link collapsed" href="#">
          <i class="bi bi-gear"></i>
          <span>Account Setting</span>
        </a>
      </li> --}}
      <!-- End Register Page Nav -->
      <form action="{{ route('logout') }}" method="POST">
        @csrf
        @method('DELETE')
      <li class="nav-item">
        <a class="nav-link collapsed" >
          <i class="bi bi-box-arrow-in-right"></i>
          <button type="submit" class="btn">Logout</button>
        </a>
      </li><!-- End Login Page Nav -->
    </ul>
  </form>
  </aside><!-- End Sidebar-->