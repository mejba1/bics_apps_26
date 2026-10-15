@extends('backend.layouts.master')
@section('title')
Create User
@endsection
@section('content')
<div class="pagetitle">
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="#">Home</a></li>
        <li class="breadcrumb-item active">Create User</li>
      </ol>
    </nav>
  </div><!-- End Page Title -->
  <!--====Add-Prize-Form===--->
  <section class="section mx-auto">
    <div class="row justify-content-center">
      <div class="col-lg-12">
        <div class="card">
          <div class="card-header text-white fw-bold" style="background:#1861c5"><i class="ri-user"></i> Create User</div>
          <div class="card-body mt-2">
           <form action="POST" id="creteUser" enctype="multipart/form-data">
            @csrf
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        
                        <div class="mb-1">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" name="name" id="name" class="form-control">
                            <span class="name text-danger fw-bold"></span>
                        </div>
                        <div class="mb-1">
                            <label for="email" class="form-label">Email</label>
                            <input type="text" name="email" id="email" class="form-control">
                            <span class="email text-danger fw-bold"></span>
                        </div>
                        <div class="mb-1">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" name="password" id="password" class="form-control">
                            <span class="password text-danger fw-bold"></span>
                        </div>
                        <div class="mb-1">
                            <label for="role" class="form-label">User Type</label>
                            <select class="form-select form-control"  name="role" id="role">
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                              </select>
                            <span class="role text-danger fw-bold"></span>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary btn-lg">Create User</button>
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
   <section class="user-info-section">
    <div class="row">
      <div class="col-lg-12">
        <div class="card">
          <div class="card-header text-white fw-bold" style="background:#1861c5"><i class="bi bi-briefcase-fill"></i> User INFORMATION</div>
          <div class="card-body mt-2">
            <table  id="prizeTable" class="table table-striped datatable" style="width:100%">
              <thead class="text-black">
                <tr>
                  <th scope="col">#</th>
                  <th scope="col">Name</th>
                  <th scope="col">Email</th>
                  <th scope="col">Role</th>
                  <th scope="col">Status</th>
                  <th scope="col">Action</th>
                </tr>
              </thead>
              <tbody>
                @php
                  $data = DB::table('users')->get();             
                @endphp
                @foreach($data as $data)
                <tr>
                  <td>{{ $loop->iteration }}</td>
                  <td>{{$data->name}}</td>
                  <td>{{$data->email}}</td>
                  <td>{{$data->role}}</td>
                  <td>{{$data->status}}</td>
                  <td>
                    <a href="#"><button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-id="{{ $data->id }}" data-bs-target="#editUserModal"><i class="ri-edit-box-line"></i></button></a>
                    <a href="#"><button class="btn btn-danger btn-sm deleteUserRecord" data-id="{{ $data->id }}"><i class="bi bi-archive"></i></button></a>
                    <a href="#"><button class="btn btn-warning btn-sm" data-id="{{ $data->id }}"><i class="bi bi-archive" id="warning-data"></i></button></a>
                  </td>
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
   <!-- The Modal -->
  <div class="modal fade" id="editUserModal">
    <div class="modal-dialog">
      <div class="modal-content">

        <!-- Modal Header -->
        <div class="modal-header">
          <i class="ri-edit-box-user btn-lg"></i><h4 class="modal-title"> Edit User Info</h4>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <!-- Modal body -->
        <div class="modal-body">
          Modal body..
        </div>

        <!-- Modal footer -->
        <div class="modal-footer">
          <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
        </div>

      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
  //Add Prize Info Into by Ajax
  $('#creteUser').on('submit', function(e){
      e.preventDefault();
      $.ajaxSetup({
          headers: {
              'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
        });  
        var _token = $("input[name='_token']").val();
          //alert(_token);
          var name = $("#name").val();
          var email = $("#email").val();
          var password = $("#password").val();
          var role = $("#role").val(); 
          $.ajax({
              url: "{{ url('/addUserinfo') }}",
              type: "POST",
             // data:new FormData(this),
              data: {_token:_token,name:name,email:email,password:password,role:role},
              dataType:'JSON',
              cache: false,
              beforeSend:function(){
                $('#creteUser .text-danger').text('');
              },
              error:function(xhr){
                // Show Laravel validation messages under each field
                var errors = (xhr.responseJSON && xhr.responseJSON.errors) || {};
                $.each(errors, function(field, messages){
                  $('#creteUser .' + field).text(messages[0]);
                });
              },
              success:function(data){
                console.log(data);
                Swal.fire({
                    position: 'top-end',
                    icon: 'success',
                    title: 'User Added!',
                    showConfirmButton: false,
                    timer: 2000
                  })
                  setTimeout(function() {
                      location.reload();
                  }, 2200);
              },
            });
      });
  </script>
@endpush