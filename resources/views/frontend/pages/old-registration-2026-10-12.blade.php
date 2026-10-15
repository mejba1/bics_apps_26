@extends('frontend.layouts.content') @section('title') Registration @endsection @section('content')
<style>
   .files input {
    outline: 2px dashed #92b0b3;
    outline-offset: -10px;
    -webkit-transition: outline-offset .15s ease-in-out, background-color .15s linear;
    transition: outline-offset .15s ease-in-out, background-color .15s linear;
    padding: 120px 0px 85px 35%;
    text-align: center !important;
    margin: 0;
    width: 100% !important;
}
.files input:focus{     outline: 2px dashed #92b0b3;  outline-offset: -10px;
    -webkit-transition: outline-offset .15s ease-in-out, background-color .15s linear;
    transition: outline-offset .15s ease-in-out, background-color .15s linear; border:1px solid #92b0b3;
 }
.files{ position:relative}
.files:after {  pointer-events: none;
    position: absolute;
    top: 60px;
    left: 0;
    width: 50px;
    right: 0;
    height: 56px;
    content: "";
    /* background-image: url(https://image.flaticon.com/icons/png/128/109/109612.png); */
    display: block;
    margin: 0 auto;
    background-size: 100%;
    background-repeat: no-repeat;
}
.files:before {
    position: absolute;
    bottom: 10px;
    left: 0;  pointer-events: none;
    width: 100%;
    right: 0;
    height: 57px;
    content: " or drag it here. ";
    display: block;
    margin: 0 auto;
    color: #2ea591;
    font-weight: 600;
    text-transform: capitalize;
    text-align: center;
}
.frame{
    width:60px;
    height:60px;
}
/*=====Form Design======*/
.form-group {
    margin-bottom: 1rem;
}
label{
    color: #8c8f94 !important;
    font-weight: bold !important;
}
.fa{
    color: #69707a;
}
.text-bics-reg{
    font-size: 13px;
    color: #0e4ca1;
}
</style>

<div class="blog-sidebar-wrapper mt-3 mb-5">
    <div class="container position-relative pt-10">
        <div class="row justify-content-center">
            <div class="col-md-9">
                <div class="card">
                 <div class="card-body text-center text-bics fw-bold">
                     <p style="color:#f69018;font-weight: 700;text-transform: uppercase;font-weight: 700;font-size:18px">4 <sup style="text-transform: lowercase;">th</sup> Beacon International Cancer Summit 2026</p>
                     <p style="color: #000000;font-weight: 700;font-weight: 700;font-size:13px ">Venue: Feria Valencia, 46035 Valencia, Spain</p>
                      <p style="color: #000000;font-size:13px" class="text-center">Dates: 28 Octobar 2026</p>
                 </div>
                  <!--<img src="{{ asset('assets/frontend') }}/images/registration-header.jpg" alt="logo" height="85">-->
                </div>
            </div>
            <div class="col-md-9 mt-1">
                <div class="card">
                <!--<span class="shape"></span>-->
                    <div class="card-header text-bics-reg"><i class="fas fa-info-circle fa-lg"></i> &nbsp;REGISTRATION FORM</div>
                    <div class="card-body">
                        @if(Session::get('success'))
                            <div class="alert alert-success alert-dismissible fade show">
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                <strong>Success!</strong> {{ Session::get('success') }}
                            </div>
                        @endif
         
                        @if(Session::get('fail'))
                            <div class="alert alert-danger">
                            {{ Session::get('fail') }}
                        </div>
                        @endif
                        <form id="msform"  method="POST" action="{{ url('saveRegisterInfo') }}" enctype="multipart/form-data">
                            @csrf                          
                            <div class="row">
                                <div class="col-xs-6 col-sm-6 col-md-6">
                                    <label>Country <span style="color: red;">*</span></label>
                                    <div class="form-group">
                                       <span class="text-danger fw-bold">@error('country') {{$message}} @enderror</span>
                                        <div class="input-group input-group-merge input-group-alternative">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-globe"></i></span>
                                            </div>
                                            <select class="form-select" id="country" name="country">
                                                <option>Select Country</option>
                                            @php
                                                $country = DB::table('dropdownvalue')->get();
                                                foreach ($country as $country){ 
                                            @endphp
                                            <option data-id="{{'+'.$country->dialCode}}" value="{{$country->countryName}}" @if (old('country') === $country->countryName) selected @endif>{{ $country->countryName }}</option>
                                            @php  } @endphp
                                        </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xs-6 col-sm-6 col-md-6">
                                    <label>Title <span style="color: red;">*</span></label>
                                    <div class="form-group">
                                       <span class="text-danger fw-bold">@error('title') {{$message}} @enderror</span>
                                        <div class="input-group input-group-merge input-group-alternative">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-user-md" aria-hidden="true"></i></span>
                                            </div>
                                            <select class="form-select" id="title" name="title">
                                                
                                                @php
                                                $title = DB::table('dropdownvalue')->limit(3)->get();
                                                foreach ($title as $title){ 
                                            @endphp
                                            <option  value="{{$title->title}}" @if (old('title') === $title->title) selected @endif>{{ $title->title }}</option>
                                            @php  } @endphp
                                                
                                              
                                        </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-xs-6 col-sm-6 col-md-6">
                                    <label class="firstname" id="firstname">First Name <span style="color: red;">*</span></label>
                                    <div class="form-group">
                                        <div class="input-group input-group-merge input-group-alternative">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-user-circle"></i></span>
                                            </div>
                                            <input type="text" name="firstname" id="firstname" value="{{ old('firstname') }}" class="form-control input-sm" placeholder="First Name"/>
                                        </div>
                                        <span class="text-danger fw-bold">@error('firstname') {{$message}} @enderror</span>
                                    </div>
                                </div>
                                <div class="col-xs-6 col-sm-6 col-md-6">
                                    <label class="lastname" id="lastname">Last Name <span style="color: red;">*</span></label>
                                    <div class="form-group">
                                        <div class="input-group input-group-merge input-group-alternative">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-user-circle"></i></span>
                                            </div>
                                            <input type="text" name="lastname" id="lastname" value="{{ old('lastname') }}" class="form-control input-sm" placeholder="Last Name"/>
                                        </div>
                                        <span class="text-danger fw-bold">@error('lastname') {{$message}} @enderror</span>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-xs-6 col-sm-6 col-md-6">
                                    <label class="birthday">Date of Birth <span style="color: red;">*</span></label>
                                    <div class="form-group">
                                        <div class="input-group input-group-merge input-group-alternative">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-calendar-alt"></i></span>
                                            </div>
                                            <input class="form-control col-xs-12" value="{{ old('birthday') }}" name="birthday" id="birthday" placeholder="Month / Day / Year"/>
                                        </div>
										<span class="text-danger fw-bold">@error('birthday') {{$message}} @enderror</span>
                                    </div>
                                </div>
                                <div class="col-xs-6 col-sm-6 col-md-6">
                                    <div class="d-flex flex-row bd-highlight mb-3 bics-radio">
                                        <div class="p-2 bd-highlight">
                                            <div class="custom-control custom-radio custom-control-inline">
                                                <label class="custom-control-label lebel-title-sub hide-form" for="Male">Male</label>
                                                <input type="radio" class="custom-control-input" name="gender" value="Male" {{ old('gender') == "Male" ? 'checked' : '' }} id="Male" checked="checked"/>
                                            </div>
                                        </div>
                                        <div class="p-2 bd-highlight">
                                            <div class="custom-control custom-radio custom-control-inline">
                                                <label class="custom-control-label lebel-title-sub hide-form" for="Female">Female</label>
                                                <input type="radio" class="custom-control-input" name="gender" value="Female" {{ old('gender') == "Female" ? 'checked' : '' }} id="Female"  />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-2">
								<div class="col-xs-6 col-sm-6 col-md-6">
                                    <label class="email">Email <span style="color: red;">*</span></label>
                                    <div class="form-group mb-3">
                                        <div class="input-group input-group-merge input-group-alternative">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-envelope"></i></span>
                                            </div>
                                            <input type="email" name="email" id="email" value="{{old('email')}}" class="form-control" placeholder="Enter Email"/>
                                        </div>
                                       <span class="text-danger fw-bold">@error('email') {{$message}} @enderror</span>
                                    </div>
                                </div>
                                <div class="col-xs-6 col-sm-6 col-md-6">
                                    <label class="phone">Phone <span style="color: red;">*</span></label>
                                    <div class="form-group mb-3">
                                        <div class="input-group input-group-merge input-group-alternative">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text" id="setIdValue"><i class="fa fa-phone"></i></span>
                                                <input type="hidden" id="countryCode" name="countryCode" value="">
                                            </div>
                                            <input type="number" name="phone" value="{{old('phone')}}" id="phone" value="" class="form-control" placeholder="Enter Phone Number">
                                        </div>
                                        <span class="text-danger fw-bold">@error('phone') {{$message}} @enderror</span>
                                    </div>
                                </div>
                            </div>
							<div class="row">
                                <div class="col-xs-6 col-sm-6 col-md-6">
                                    <label class="lebel-title profession">Profession <span style="color: red;">*</span></label>
                                    <div class="form-group">
                                        <div class="input-group input-group-merge input-group-alternative">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-book"></i></span>
                                            </div>
                                           <select class="form-select" id="profession"  name="profession">
                                               <option value="" selected="" disabled="">Select Profession </option>
                                                @php
                                                 $profession = DB::table('professions')->get();
                                                foreach ($profession as $profession){ 
                                                @endphp
                                                <option  value="{{$profession->profession}}" @if (old('profession') === $profession->profession) selected @endif>{{ $profession->profession }}</option>
                                                @php  } @endphp
                                                
                                            </select>
                                        </div>
                                        <span class="text-danger fw-bold">@error('profession') {{$message}} @enderror</span>
                                    </div>
                                </div>
								<div class="col-xs-6 col-sm-6 col-md-6">
                                    <label class="lebel-title specialty">Speciality <span style="color: red;">*</span></label>
                                    <div class="form-group">
                                        <div class="input-group input-group-merge input-group-alternative">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-list-ul"></i></span>
                                            </div>
                                            <select class="form-select" id="specialty" name="specialty">
                                                <option value="" selected="" disabled="">Select Speciality </option>
                                               
                                            </select>
                                        </div>
                                        <span class="text-danger fw-bold">@error('specialty') {{$message}} @enderror</span>
                                    </div>
                                </div>
                            </div>
							<div class="row">
                               <!-- <div class="col-xs-6 col-sm-6 col-md-6">
                                    <label class="pharmacist">Pharmacist<span style="color: red;">*</span></label>
                                    <div class="form-group">
                                        <div class="input-group input-group-merge input-group-alternative">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-user"></i></span>
                                            </div>
                                            <select class="form-select" id="pharmacist" name="pharmacist" tabindex="-1" aria-hidden="true">
                                                <option value="" selected="" disabled="">Select Pharmacist Type </option>
                                              @php
                                                $pharmacist = DB::table('dropdownvalue')->limit(5)->get();
                                                foreach ($pharmacist as $pharmacist){ 
                                                @endphp
                                                <option  value="{{$pharmacist->pharmacist}}" @if (old('pharmacist') === $pharmacist->pharmacist) selected @endif>{{ $pharmacist->pharmacist }}</option>
                                                @php  } @endphp
                                            </select>
                                        </div>
                                        <span class="text-danger fw-bold">@error('pharmacist') {{$message}} @enderror</span>
                                    </div>
                                </div>-->
                                <div class="col-xs-12 col-sm-12 col-md-12">
                                    <label class="designation">Designation</label>
                                    <div class="form-group">
                                        <div class="input-group input-group-merge input-group-alternative">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-certificate"></i></span>
                                            </div>
                                            <input type="text" name="designation" id="designation" class="form-control input-sm" placeholder="Current designation" value="{{old('designation')}}"/>
                                        </div>
                                        <span class="text-danger fw-bold">@error('designation') {{$message}} @enderror</span>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-xs-6 col-sm-6 col-md-6">
                                    <label class="institute"> Name of Institution <span style="color: red;">*</span></label>
                                    <div class="form-group">
                                        <div class="input-group input-group-merge input-group-alternative">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-building"></i></span>
                                            </div>
                                            <input type="text" name="institute" id="institute" class="form-control input-sm" value="{{old('institute')}}">
                                        </div>
                                        <span class="text-danger fw-bold">@error('institute') {{$message}} @enderror</span>
                                    </div>
                                </div>
                                <div class="col-xs-6 col-sm-6 col-md-6">
                                    <label class="institute_address">Institution Address </label>
                                    <div class="form-group">
                                        <div class="input-group input-group-merge input-group-alternative">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-map-marker"></i></span>
                                            </div>
                                            <input type="text" name="institute_address" id="institute_address" class="form-control input-sm"  value="{{old('institute_address')}}"/>
                                        </div>
                                        <span class="text-danger fw-bold">@error('institute_address') {{$message}} @enderror</span>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-xs-6 col-sm-6 col-md-6">
                                    <label class="identification">Identification Type </label>
                                    <div class="form-group">
                                        <div class="input-group input-group-merge input-group-alternative">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-user-shield"></i></span>
                                            </div>
                                            <select class="form-select data-control mb-3" name="identification_type" id="identification_type" value="{{ old('identification_type') }}" data-placeholder="Choose one (with searchbox)" tabindex="-1" aria-hidden="true">
                                                <option value="" selected="" disabled="">Select Type </option>
                                                 @php 
                                                $identification_type = DB::table('dropdownvalue')->limit(4)->get();
                                                foreach ($identification_type as $identification){ 
                                                @endphp
                                                <option  data-id="{{$identification->identification_type. "*"}}" value="{{$identification->identification_type}}" @if (old('identification_type') === $identification->identification_type) selected @endif>{{ $identification->identification_type }}</option>
                                                @php  } @endphp
                                            
                                            </select>
                                        </div>
                                         <span class="text-danger fw-bold">@error('identification_type') {{$message}} @enderror</span>
                                    </div>
                                </div>
                                <div class="col-xs-6 col-sm-6 col-md-6">
                                    <label class="Identify" id="setIdtype">Identify No </label>
                                    <div class="input-group input-group-merge input-group-alternative">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-passport"></i></span>
                                        </div>
                                        <input type="text" name="identification_no" id="identification_no"  value="{{ old('identification_no') }}" class="form-control input-sm" placeholder="XXXXXXXXXXXXXX"/>
                                    </div>
                                     <span class="text-danger fw-bold">@error('identification_no') {{$message}} @enderror</span>
                                </div>
                            </div>
                            <div class="row">
                              <div class="col-md-9">
                                   <div class="form-group files1">
                                     <label>Your Photo <br/><span style="font-size:10px;color:#eda444;font-weight:bold">Max Size 1MB, (300X300)Px, (jpg,png only)</span></label>
                                     <input type="file" class="form-control" name="image" value="{{ old('image') }}" onchange="preview()">
                                     <span class="text-danger fw-bold">@error('image') {{$message}} @enderror</span>
                                   </div> 
                                </div>
                                <div class="col-md-3 col-sm-2">
                                    <img id="frame" src="" class="img-fluid img-circle img-responsive"/>       
                                </div>
                            </div>
                            <div class="col-12 mt-3">
                                <div class="text-center">
                                    <button class="btn btn-primary bd-0 submit-btn btn-lg" type="submit"><i class="bi bi-check"></i> SUBMIT</button>
                                    <!--<button class="btn btn-danger bd-0 bics-danger-btn" onclick="window.location.reload()"><i class="bi bi-arrow-clockwise"></i> &nbsp;RESET</span></button>-->
                                </div>
                            </div>
                            
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
