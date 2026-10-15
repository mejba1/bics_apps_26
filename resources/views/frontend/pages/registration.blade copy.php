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
    background-image: url(https://image.flaticon.com/icons/png/128/109/109612.png);
    display: block;
    margin: 0 auto;
    background-size: 100%;
    background-repeat: no-repeat;
}
.color input{ background-color:#f1f1f1;}
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
</style>

<div class="blog-sidebar-wrapper mt-3 mb-5">
    <div class="container position-relative pt-10">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow-lg rounded-10">
                <span class="shape"></span>
                    <div class="card-header text-bics"><i class="fas fa-info-circle fa-lg"></i> &nbsp;BICS REGISTRATION FORM</div>
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
                                <div class="col-xs-12 col-sm-12 col-md-12">
                                    <label class="lebel-title">Country <span style="color: red;">*</span></label>
                                    <div class="form-group">
                                        <span class="text-danger">@error('country') {{$message}} @enderror</span>
                                        <div class="input-group input-group-merge input-group-alternative">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-globe"></i></span>
                                            </div>
                                            <select class="form-select" id="country" name="country" required>
                                                @include('frontend.layouts.inc.country')
                                            </select>
                                        </div>
                                    </div>
                                    
                                </div>
                                
                            </div>
                            
                            <div class="row">
                                <div class="col-xs-6 col-sm-6 col-md-6">
                                    <label class="fname">First Name <span style="color: red;">*</span></label>
                                    <div class="form-group">
                                        <div class="input-group input-group-merge input-group-alternative">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-user"></i></span>
                                            </div>
                                            <input type="text" name="firstname" id="firstname" value="{{ old('firstname') }}" class="form-control input-sm" placeholder="First Name" minlength="4" data-v-message="Please enter minimum 4 characters" required />
                                        </div>
                                        <span class="text-danger">@error('firstname') {{$message}} @enderror</span>
                                    </div>
                                </div>
                                <div class="col-xs-6 col-sm-6 col-md-6">
                                    <label class="lname">Last Name <span style="color: red;">*</span></label>
                                    <div class="form-group">
                                        <div class="input-group input-group-merge input-group-alternative">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-user"></i></span>
                                            </div>
                                            <input type="text" name="lastname" id="lastname" value="{{ old('lastname') }}" class="form-control input-sm" placeholder="Last Name" minlength="4" data-v-message="Please enter minimum 4 characters" required />
                                        </div>
                                        <span class="text-danger">@error('lastname') {{$message}} @enderror</span>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-xs-6 col-sm-6 col-md-6">
                                    <label class="dob">Date of Birth <span style="color: red;">*</span></label>
                                    <div class="form-group">
                                        <div class="input-group input-group-merge input-group-alternative">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-calendar-alt"></i></span>
                                            </div>
                                            <input class="form-control col-xs-12" value="{{ old('birthday') }}" name="birthday" id="birthday" placeholder="Month / Day / Year"  required="required" />
                                        </div>
										<span class="text-danger">@error('birthday') {{$message}} @enderror</span>
                                    </div>
                                </div>
                                <div class="col-xs-6 col-sm-6 col-md-6">
                                    <div class="d-flex flex-row bd-highlight mb-3 bics-radio">
                                        <div class="p-2 bd-highlight">
                                            <div class="custom-control custom-radio custom-control-inline">
                                                <label class="custom-control-label lebel-title-sub hide-form" for="Male">Male</label>
                                                <input type="radio" class="custom-control-input" name="gender" id="Male" value="Male" />
                                            </div>
                                        </div>
                                        <div class="p-2 bd-highlight">
                                            <div class="custom-control custom-radio custom-control-inline">
                                                <label class="custom-control-label lebel-title-sub hide-form" for="Female">Female</label>
                                                <input type="radio" class="custom-control-input" name="gender" id="Female" value="Female" />
                                            </div>
                                        </div>
                                        <div class="p-2 bd-highlight">
                                            <div class="custom-control custom-radio custom-control-inline">
                                                <label class="custom-control-label lebel-title-sub hide-form" for="Other">Other</label>
                                                <input type="radio" class="custom-control-input" name="gender" id="Other" value="Other" />
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
                                            <input type="email" name="email" id="email" value="{{old('email')}}" class="form-control" placeholder="Enter Email" maxlength="80" size="30" required/>
                                        </div>
                                       <span class="text-danger">@error('email') {{$message}} @enderror</span>
                                    </div>
                                </div>
                                <div class="col-xs-6 col-sm-6 col-md-6">
                                    <label class="phone">Phone <span style="color: red;">*</span></label>
                                    <div class="form-group">
                                        <div class="input-group input-group-merge input-group-alternative">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text" id="setIdValue"><i class="fa fa-phone"></i></span>
                                            </div>
                                            <input type="number" name="phone" id="phone" placeholder="valid phone number" value={{ old('phone') }} data-v-min="10" pattern="[0-9]+" title="Only number." class="form-control input-sm" required />
                                        </div>
										 <span class="text-danger">@error('phone') {{$message}} @enderror</span>
                                    </div>
                                </div>
                            </div>
							<div class="row">
                                <div class="col-xs-6 col-sm-6 col-md-6">
                                    <label class="lebel-title">Profession <span style="color: red;">*</span></label>
                                    <div class="form-group">
                                        <div class="input-group input-group-merge input-group-alternative">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-user"></i></span>
                                            </div>
                                            <select class="form-select" id="profession" value="{{ old('profession') }}" name="profession" tabindex="-1" aria-hidden="true" required>
                                                <option value="" selected="" disabled="">Select Profession </option>
                                                <option value="Medical Doctor">Medical Doctor</option>
                                                <option value="Pharmachist">Pharmachist</option>
                                                <option value="Pathologist">Pathologist</option>
                                                <option value="Nurse">Nurse</option>
                                                <option value="Procurement">Procurement</option>
                                                <option value="Others">Others</option>
                                            </select>
                                        </div>
                                        <span class="text-danger">@error('profession') {{$message}} @enderror</span>
                                    </div>
                                </div>
								<div class="col-xs-6 col-sm-6 col-md-6">
                                    <label class="lebel-title">Specialty <span style="color: red;">*</span></label>
                                    <div class="form-group">
                                        <div class="input-group input-group-merge input-group-alternative">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-user"></i></span>
                                            </div>
                                            <select class="form-select" id="specialty" value="{{ old('specialty') }}" name="specialty" tabindex="-1" aria-hidden="true" required>
                                                <option value="" selected="" disabled="">Select Specialty </option>
                                                <option value="Medical Oncologist">Medical Oncologist</option>
                                                <option value="Clinical Oncologist">Clinical Oncologist</option>
                                                <option value="Rediation Oncologist">Rediation Oncologist</option>
                                                <option value="Guneoncologist">Guneoncologist</option>
                                                <option value="Hematologist">Hematologist</option>
                                                <option value="Gastroenterologist">Gastroenterologist</option>
                                                <option value="Urologist">Urologist</option>
                                                <option value="Oncology Surgeon">Oncology Surgeon</option>
                                                <option value="Others">Others</option>
                                            </select>
                                        </div>
                                        <span class="text-danger">@error('specialty') {{$message}} @enderror</span>
                                    </div>
                                </div>
                            </div>
							<div class="row">
                                <div class="col-xs-6 col-sm-6 col-md-6">
                                    <label class="pharmacist">Pharmacist<span style="color: red;">*</span></label>
                                    <div class="form-group">
                                        <div class="input-group input-group-merge input-group-alternative">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-user"></i></span>
                                            </div>
                                            <select class="form-select" id="pharmacist" value="{{ old('pharmacist') }}" name="pharmacist" tabindex="-1" aria-hidden="true" required>
                                                <option value="" selected="" disabled="">Select Pharmacist Type </option>
                                                <option value="Pharmacist">Pharmacist</option>
                                                <option value="Clinical Oncologist">Oncology Pharmacist</option>
                
                                                <option value="Others">Others</option>
                                            </select>
                                        </div>
                                        <span class="text-danger">@error('pharmacist') {{$message}} @enderror</span>
                                    </div>
                                </div>
                                <div class="col-xs-6 col-sm-6 col-md-6">
                                    <label class="designation">Designation<span style="color: red;">*</span></label>
                                    <div class="form-group">
                                        <div class="input-group input-group-merge input-group-alternative">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-user"></i></span>
                                            </div>
                                            <input
                                                type="text"
                                                name="designation"
                                                id="designation"
                                                class="form-control input-sm"
                                                placeholder="Curent designation"
                                                value="{{old('designation')}}"
                                                minlength="4"
                                                data-v-message="Please enter minimum 4 characters"
                                                required
                                            />
                                        </div>
                                        <span class="text-danger">@error('designation') {{$message}} @enderror</span>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-xs-6 col-sm-6 col-md-6">
                                    <label class="institute">Institute Name <span style="color: red;">*</span></label>
                                    <div class="form-group">
                                        <div class="input-group input-group-merge input-group-alternative">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-building"></i></span>
                                            </div>
                                            <input
                                                type="text"
                                                name="institute"
                                                id="institute"
                                                class="form-control input-sm"
                                                value="{{old('institute')}}"
                                                placeholder="Institute/Company Name"
                                                minlength="4"
                                                data-v-message="Please enter minimum 4 characters"
                                                required
                                            />
                                        </div>
                                        <span class="text-danger">@error('institute') {{$message}} @enderror</span>
                                    </div>
                                </div>
                                <div class="col-xs-6 col-sm-6 col-md-6">
                                    <label class="institute_address">Institute Address <span style="color: red;">*</span></label>
                                    <div class="form-group">
                                        <div class="input-group input-group-merge input-group-alternative">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-user"></i></span>
                                            </div>
                                            <input
                                                type="text"
                                                name="institute_address"
                                                id="institute_address"
                                                class="form-control input-sm"
                                                placeholder="Curent institute_address"
                                                value="{{old('institute_address')}}"
                                                minlength="4"
                                                data-v-message="Please enter minimum 4 characters"
                                                required
                                            />
                                        </div>
                                        <span class="text-danger">@error('institute_address') {{$message}} @enderror</span>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-xs-6 col-sm-6 col-md-6">
                                    <label class="identification">Identification Type <span style="color: red;">*</span></label>
                                    <div class="form-group">
                                        <div class="input-group input-group-merge input-group-alternative">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-user-shield"></i></span>
                                            </div>
                                            <select class="form-select data-control mb-3" name="identification_type" id="identification_type" data-placeholder="Choose one (with searchbox)" tabindex="-1" aria-hidden="true" required>
                                                <option value="" selected="" disabled="">Select Type </option>
                                                <option data-id="Passport No *" value="Passport number">Passport number </option>
                                                <option data-id="National Identify No *" value="National Identify No">National Identify No </option>
                                                <option data-id="Birth Registration No *" value="Birth registration number">Birth registration number</option>
                                                <option data-id="Driving License *" value="Driving license">Driving license</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xs-6 col-sm-6 col-md-6">
                                    <label class="Identify" id="setIdtype">Identify No <span style="color: red;">*</span></label>
                                    <div class="input-group input-group-merge input-group-alternative">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-passport"></i></span>
                                        </div>
                                        <input type="text" name="identification_no" id="identification_no"  class="form-control input-sm" placeholder="XXXXXXXXXXXXXX" required />
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                              <div class="col-md-12">
                                       <div class="form-group files">
                                         <label>Upload Your Photo ( <span style="font-size:12px;color:red;font-weight:bold">Max Size 1MB</span>)</label>
                                         <input type="file" class="form-control" name="image">
                                         <span class="text-danger">@error('image') {{$message}} @enderror</span>
                                       </div>
                              </div>
                              </div>
                            <div class="col-12 mt-3">
                                <div class="text-center">
                                    <button class="btn btn-primary bd-0 primary-btn-fill bics-btn" type="submit"><i class="icofont-check-alt"></i> <span> &nbsp;SUBMIT</span></button>
                                    <button class="btn btn-danger bd-0 bics-danger-btn" onclick="window.location.reload()"><i class="icofont-ui-rotation"></i><span> &nbsp;RESET</span></button>
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
