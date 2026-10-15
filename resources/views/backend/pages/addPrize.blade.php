@extends('backend.layouts.master')
@section('title')
Add Prize
@endsection
@section('content')
<div class="pagetitle">
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="#">Home</a></li>
        <li class="breadcrumb-item active">Add Prize</li>
      </ol>
    </nav>
  </div><!-- End Page Title -->
  <!--====Add-Prize-Form===--->
  <section class="section mx-auto">
    <div class="row justify-content-center">
      <div class="col-lg-12">
        <div class="card">
          <div class="card-header text-white fw-bold" style="background:#1861c5"><i class="ri-user-4-fill"></i> ADD PRIZE</div>
          <div class="card-body mt-2">
           <form action="POST" id="prizeForm" enctype="multipart/form-data">
            @csrf
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="mb-1">
                            <label for="prizeVenue" class="form-label">Raffle Draw Venue</label>
                            <input type="text" name="prizeVenue" id="prizeVenue" placeholder="exp. Algeria" value="Spain" class="form-control">
                            <span class="prizeVenue text-danger fw-bold"></span>
                        </div>
                        <div class="mb-1">
                            <label for="prizeName" class="form-label">Prize Name</label>
                            <input type="text" name="prize" id="prize" placeholder="exp. MacBook Air" class="form-control">
                            <span class="prizeName text-danger fw-bold"></span>
                        </div>
                        <div class="mb-1">
                            <label for="prizeType" class="form-label">Prize Type</label>
                            <select class="form-select form-control"  name="prizeType" id="prizeType">
                                <option value="" selected="" disabled="">-- Select Prize Type-- </option>
                                <option value="First Prize">First Prize</option>
                                <option value="Second Prize">Second Prize</option>
                                <option value="Third Prize">Third Prize</option>
                                <option value="Fourth Prize">Fourth Prize</option>
                                <option value="Fifth Prize">Fifth Prize</option>
                                <option value="six Prize">Six Prize</option>
                                <option value="Seven Prize">Seven Prize</option>
                                <option value="Eight Prize">Eight Prize</option>
                                <option value="Nine Prize">Nine Prize</option>
                                <option value="Ten Prize">Ten Prize</option>
                              </select>
                            <span class="prizeType text-danger fw-bold"></span>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary btn-lg">Add Prize</button>
                            {{-- <input type="submit" value="Submit" class="btn btn-success"> --}}
                        </div>
                    </div>
                </div>
              </form>
          </div>
        </div>
      </div>
    </div>
  </section>
   <!--====END-Add-Prize-Form===--->

   <!--====Prize-Information-Details-table===--->
   <section class="addprize-section">
    <div class="row">
      <div class="col-lg-12">
        <div class="card">
          <div class="card-header text-white fw-bold" style="background:#1861c5"><i class="bi bi-briefcase-fill"></i> PRIZE INFORMATION</div>
          <div class="card-body mt-2">
            <table  id="prizeTable" class="table table-striped datatable" style="width:100%">
              <thead class="text-black">
                <tr>
                  <th scope="col">#</th>
                  <th scope="col">Prize Name</th>
                  <th scope="col">Prize Type</th>
                  @can('raffle.prizes')
                  <th scope="col">Prize Venue</th>
                  <th scope="col">Status</th>
                  <th scope="col">Action</th>
                  @endif
                </tr>
              </thead>
              <tbody>
                @php
                  $prize = DB::table('prizes')->get();             
                @endphp
                @foreach ($prize as $prize)
                <tr>
                  <td>{{ $loop->iteration }}</td>
                  <td>{{$prize->prize}}</td>
                  <td>{{$prize->prize_type}}</td> 
                  @can('raffle.prizes')
                  <td>{{$prize->prize_venue}}</td>
                  <td>{{$prize->prize_status}}</td>
                  <td>
                    <a href="#"><button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-id="{{ $prize->id }}" data-bs-target="#editPrizeModal"><i class="ri-edit-box-line"></i></button></a>
                    <a href="#"><button class="btn btn-danger btn-sm deletePrizeRecord" data-id="{{ $prize->id }}"><i class="bi bi-archive"></i></button></a>
                    <a href="#"><button class="btn btn-warning btn-sm" data-id="{{ $prize->id }}"><i class="bi bi-archive" id="warning-data"></i></button></a>
                  </td>
                  @endif
                </tr>                
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
   <section>
   <!--====Prize-Information-Details-table===--->
@endsection