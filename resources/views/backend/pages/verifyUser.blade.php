@extends('backend.layouts.master')
@section('title')
Verify User
@endsection
@section('content')
<div class="pagetitle">
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="#">Home</a></li>
        <li class="breadcrumb-item active">Verify User</li>
      </ol>
    </nav>
  </div><!-- End Page Title -->
  <section class="section user-verify">
    <div class="row">
      <div class="col-12">
        
      </div>
      <div class="col-12">
      <div class="card recent-sales">
        <div class="card-header text-white fw-bold" style="background:#0e4ca1"><i class="bi bi-person-lines-fill"></i> Verify User</div>
        <div class="card-body mt-3">
        <table class="table datatable nowrap verifyUser" style="width:100%">
          <thead class="text-black">
          <tr>
            <th scope="col">Reg. No</th>
            <th scope="col">Name</th>
            <th scope="col">Contact</th>
            <th scope="col">Photo</th>
            <th scope="col">Action</th>
          </tr>
          </thead>
          <tbody>
          @php 
           $data =  DB::table('registrations')->get();
          @endphp
          @foreach ($data as $data)
          <tr>
           <td><span class="fw-bold" style="color:#123b87">{{ $data->qr_code }}</span><br>
              <span class="text-muted" style="font-size:12px">{{ $data->created_at }}
            </td>
            <td><span class="dt-title">{{ $data->firstname.' '.$data->lastname }}</span><br/>
              <span class="text-muted" style="font-size:12px">{{ $data->profession }} |
                @if($data->specialty)
                  {{ $data->specialty}}
                  @else
                  {{ 'N/A' }}
                @endif
              </span>
            </td>
            <td><span class="dt-title">{{ $data->country }}</span><br/>
              <span class="text-muted" style="font-size:12px">{{ $data->email }} <br/>
                  {{ $data->phone}}
              </span>
            </td>
            <td>
              @if($data->image == 'no-photo.png')
               <span class="text-muted">{{ 'No photo' }}</span>
             @else
                <img class="card-img-top" src="{{ asset('upload/images/').'/'.$data->image }}" alt="Bics" style="width: 90px;height: 90px;">
              @endif
            </td>

            <td>
              @if ($data->verify_status == "Yes")
              <span class="dt-title"></span><i class="bi bi-patch-check-fill" style="font-size: 1.6em;color:#303F9F"></i></span><br/>
              <span class="text-muted" style="font-size:12px">{{ $data->verify_at }}
              @else
              <a href="#"><i class="bi bi-check-lg fw-bold" style="font-size:28px;color:#123b87"></i></a>
              @endif
             </td>
          </tr>
          @endforeach
          </tbody>
        </table>
        </div>
      </div>
      </div>
    </div>
  </section>
@endsection