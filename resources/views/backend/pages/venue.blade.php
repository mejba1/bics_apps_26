@extends('backend.layouts.master')
@section('title')
   Dashboard
@endsection
@section('content')
<style>
  tbody, td, tfoot, th, thead, tr {
    border-color: inherit;
    border-style: solid;
    border-width: 0;
    border: 1px solid #f0f0f0;
}

/* Country scrolling  */
  .overflow-auto {
      height: 210px;  
  }
</style>
<div class="pagetitle">
    {{-- <h1>Dashboard</h1> --}}
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="#">Home</a></li>
        <li class="breadcrumb-item active">Dashboard</li>
      </ol>
      <div class="dropdown">
  <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
    BICS Venue
  </button>
  
  <ul class="dropdown-menu">
    @php
      $venue = DB::table('registrations')
                ->select('venue', DB::raw('COUNT(*) as count'))
                 ->groupBy('venue')
                ->get();
         @endphp
         <!--<li><a class="dropdown-item" href="https://bics.org/home">All Venue</a></li>-->
        @foreach($venue as $venue)
          <li><a class="dropdown-item" href="{{ url('/venue/details/'.$venue->venue) }}">{{ $venue->venue }}</a></li>
    @endforeach
  </ul>
</div>
<h3 class="text-danger">{{ $data->first()->venue ?? 'Venue not found'  }}</h3>
    </nav>
  </div><!-- End Page Title -->
  <section class="section dashboard">
    <div class="row">
      <!-- Left side columns -->
          <!-- Sales Card -->
          <div class="col-xxl-3 col-md-3">
            <div class="card info-card sales-card">
              <div class="filter">
                <a class="icon" href="#" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></a>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
    
                  @can('registrations.export')<li><a class="dropdown-item" href="{{ route('createPdfByVenue', ['venue' => $data->first()->venue]) }}">Report as PDF <i class="bi bi-cloud-arrow-down-fill text-danger"></i></a></li>@endcan
                </ul>
              </div>
              <div class="card-body">
                <h5 class="card-title">BICS <span>| Registers</span></h5>
                <div class="d-flex align-items-center">
                  <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                    <i class="bi bi-people-fill"></i>
                  </div>
                  <div class="ps-3">
                    <h6>{{ $data->count() }}</h6>
                    <!--<span class="text-muted small pt-2 ps-1">{{ $data->first()->venue ?? 'Venue not found'  }}</span>-->
                  </div>
                </div>
              </div>
            </div>
          </div><!-- End Sales Card -->
          <div class="col-xxl-3 col-md-3">
            <div class="card info-card country-card">
              <div class="filter">
                <a class="icon" href="#" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></a>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                  <li><a class="dropdown-item" href="#">Download</a></li>
                </ul>
              </div>
              <div class="card-body">
                <h5 class="card-title">Country <span>| Registers </span></h5>
                <div class="d-flex align-items-center">
                  <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                    <i class="bi bi-flag-fill"></i>
                  </div>
                  <div class="ps-3">
                    <h6>{{  $countryData }}</h6>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- Revenue Card -->
          <div class="col-xxl-3 col-md-3">
            <div class="card info-card revenue-card">
              <div class="filter">
                <a class="icon" href="#" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></a>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                  <li><a class="dropdown-item" href="#">Download</a></li>
                </ul>
              </div>
              <div class="card-body">
                <h5 class="card-title">Verified <span>| Registers</span></h5>

                <div class="d-flex align-items-center">
                  <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                    <i class="bi bi-person-check-fill"></i>
                  </div>
                  <div class="ps-3">
                    <h6>{{ $verifiedRegister }}</h6>
                 
                  </div>
                </div>
              </div>

            </div>
          </div>
          <!-- Pending Card -->
          <div class="col-xxl-3 col-md-3">
            <div class="card info-card  pending-card">
              <div class="filter">
                <a class="icon" href="#" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></a>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                 
                  <li><a class="dropdown-item" href="#">Download</a></li>
                </ul>
              </div>
              <div class="card-body">
                <h5 class="card-title">Activate <span>| Registers</span></h5>

                <div class="d-flex align-items-center">
                  <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                    <i class="bi bi-pentagon-half"></i>
                  </div>
                  <div class="ps-3">
                    <h6>{{ 
                      // DB::table('registrations')
                      // ->where('verify_status', 'No')
                      // ->count('country');    
                      '0'                 
                    }}</h6>
                 
                  </div>
                </div>
              </div>

            </div>
          </div>
          <!-- Pending Card End -->
      </div>
	  <div class="row">
      <!-- Left side columns -->
          <!-- Sales Card -->
          <div class="col-xxl-3 col-md-3">
            <div class="card info-card prize-card">
              <div class="filter">
                <a class="icon" href="#" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></a>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                  <li><a class="dropdown-item" href="#">Report Download</a></li>
                </ul>
              </div>
              <div class="card-body">
                <h5 class="card-title">Prize <span>| Remaining </span></h5>
                <div class="d-flex align-items-center">
                  
                  <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                    <i class="bi bi-award"></i>
                  </div>
                  <div class="ps-3">
                  
                    <h6>{{ $prizeDistributed.' / '.$prizeItem    }}</h6>
                    {{-- <span class="text-success small pt-1 fw-bold">All Info</span> <span class="text-muted small pt-2 ps-1">New</span> --}}
                  </div>
                </div>
              </div>
            </div>
          </div><!-- End Sales Card -->
          <div class="col-xxl-3 col-md-3">
            <div class="card info-card winner-card">
              <div class="filter">
                <a class="icon" href="#" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></a>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                  <li><a class="dropdown-item" href="#">Download</a></li>
                </ul>
              </div>
              <div class="card-body">
                <h5 class="card-title">Winner <span>| Prize </span></h5>
                <div class="d-flex align-items-center">
                  <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                    <i class="bi bi-trophy-fill"></i>
                  </div>
                  <div class="ps-3">
                    <h6>{{ $winnersData }}</h6>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- gift Card -->
          <div class="col-xxl-3 col-md-3">
            <div class="card info-card gift-card">
              <div class="filter">
                <a class="icon" href="#" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></a>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                  <li><a class="dropdown-item" href="#">Download</a></li>
                </ul>
              </div>
              <div class="card-body">
                <h5 class="card-title">Gift <span>| Distributed</span></h5>

                <div class="d-flex align-items-center">
                  <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                    <i class="bi bi-gift-fill"></i>
                  </div>
                  <div class="ps-3">
                    <h6>{{ $giftApproved }}</h6>
                 
                  </div>
                </div>
              </div>

            </div>
          </div>
          <!-- Pending Card -->
          <div class="col-xxl-3 col-md-3">
            <div class="card">
              <div class="card-header text-white fw-bold" style="background:#18c2c5"><i class="bi bi-globe"></i> REGISTER Country<br/><span class="text-dark">Venue of {{ $data->first()->venue }}</span></div>
              <div class="card-body mt-2 overflow-auto">
                <ul class="list-group">
                 
                    @foreach($countryCounts as $countryCount)
                      <li class="list-group-item d-flex justify-content-between align-items-center">
                        {{ $countryCount->country }}<span class="badge bg-primary rounded-pill"><a href="{{ route('countryVenueWiseReport', ['country' => $countryCount->country, 'venue' => $data->first()->venue]) }}" class="text-white">{{ $countryCount->count }}</a></span>
                      </li>
                    @endforeach
                </ul>
              </div>
            </div>
          </div>
          <!-- Pending Card End -->
      </div>
        <!-- Report -->
      {{-- <div class="row justify-content-center">
          <div class="col-md-4">
            <div class="card">
              <div class="card-header text-white fw-bold" style="background:#18c2c5"><i class="bi bi-globe"></i> REGISTER Country</div>
              <div class="card-body mt-2">
                <ul class="list-group overflow-auto">
                  @php
                    $countryCounts = DB::table('registrations')
                      ->select('country', DB::raw('COUNT(*) as count'))
                      ->groupBy('country')
                      ->orderBy('count', 'desc')
                      ->get();
                     @endphp
                    @foreach($countryCounts as $countryCount)
                      <li class="list-group-item d-flex justify-content-between align-items-center">
                        {{ $countryCount->country }}<span class="badge bg-primary rounded-pill">{{ $countryCount->count }}</span>
                      </li>
                    @endforeach
                </ul>
              </div>
            </div>
            
          </div>
       </div> --}}
        <!-- END- Report-->
        <!-- Register Infromation -->
        @can('registrations.list')
        <div class="row">
          <div class="col-12">
            <div class="card recent-sales">
              <div class="filter">
                <a class="icon" href="#" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></a>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                  <li class="dropdown-header text-start">
                    <h6>Filter</h6>
                  </li>
                  <li><a class="dropdown-item" href="#">Today</a></li>
                  <li><a class="dropdown-item" href="#">This Month</a></li>
                </ul>
              </div>
              <div class="card-header text-white fw-bold" style="background:#1861c5"><i class="bi bi-briefcase-fill"></i> REGISTER INFORMATION'S</div>
              <div class="card-body mt-3">
                <table class="table datatable nowrap" style="width:100%">
                  <thead class="text-black">
                    <tr>
                      <th scope="col">#</th>
                      <th scope="col">Name</th>
                      <th scope="col">Gender</th>
                      <th scope="col">Profession</th>
                      <th scope="col">Speciality</th>
                      <th scope="col">Reg. No</th>
                      <th scope="col">Country</th>
                      <th scope="col">Email</th>
                      <th scope="col">Email Send</th>
                      {{-- <th scope="col">Qrcode</th> --}}
                      <th scope="col">Status</th>
                      <th scope="col">Action</th>
                    </tr>
                  </thead>
                  <tbody>

                    @foreach ($data as $data)
                    <tr>
                      <td>{{ $loop->iteration }}</td>
                      <td>
                        <p>{{ $data->firstname.' '.$data->lastname }}<br/>
                        <span style="font-size:10px;color:rgb(108, 104, 104)">{{ $data->designation }}</span>|
                        <span style="font-size:10px;color:rgb(108, 104, 104)">
                          @if($data->specialty)
                          {{ $data->specialty}}
                          @else
                          {{ 'N/A' }}
                          @endif
                        </span>
                      </p>
                      </td>
                      <td>{{ $data->gender }}</td>
                      <td>{{ $data->profession}}</td>
                      @if($data->specialty)
                      <td>{{ $data->specialty}}</td>
                      @else
                      <td>{{ 'N/A' }}</td>
                      @endif

                      <th scope="row">@can('registrations.export')<a href="{{ url('/download-profile-Pdf/'.$data->id) }}">{{ $data->qr_code }} 1<i class="bi bi-cloud-arrow-down"></i></a>@else{{ $data->qr_code }}@endcan</th>
                      <td>{{ $data->country }}</td>
                      <td>{{ $data->email }}</td>
                      <td>{{ $data->email_notification }}</td>
                      {{-- <td>
                        <img class="card-img-top" src="{{ $data->qr_img_path }}" alt="Bics" style="width: 80px;height: 80px;">
                        @if($data->image == 'no-photo.png')
                            {{ ' ' }}
                       @else
                          <img class="card-img-top" src="{{ asset('upload/images/').'/'.$data->image }}" alt="Bics" style="width: 90px;height: 90px;">
                        @endif
                      </td> --}}
                      @if($data->verify_status == "Yes")
                      <td><span class="badge bg-success">{{ "Verified" }}</span></td>
                      @endif
                      @if($data->verify_status == "No")
                      <td><span class="badge bg-warning">{{ "Pending" }}</span></td>
                      @endif
                      <td>
                        <a href="#"><button class="btn btn-success btn-sm"><i class="bi bi-pencil-square"></i></button></a>
                        @can('registrations.delete')<a href="#"><button class="btn btn-danger btn-sm deleteRecord" data-id="{{ $data->id }}"><i class="bi bi-archive"></i></button></a>@endcan
                        @can('registrations.export')<a href="{{ url('/download-profile-Pdf/'.$data->id) }}"><button class="btn btn-warning btn-sm"><i class="bi bi-cloud-arrow-down"></i></button></a>@endcan
                      </td>
                    </tr>
                    @endforeach
                    
                  </tbody>
                </table>
              </div>
            </div>
          </div><!-- End Recent Sales -->
    </div>

    @endif
    
  </section>
  @endsection