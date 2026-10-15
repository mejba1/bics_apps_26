<!DOCTYPE html>
<html lang="en">
   <head>
      <meta charset="utf-8">
      <meta content="width=device-width, initial-scale=1.0" name="viewport">
      <meta name="csrf-token" content="{{ csrf_token() }}" />
      <title>BICS | Verify Portal</title>
      <!-- Favicons -->
      <link href="{{ asset('assets/backend') }}/img/favicon.png" rel="icon">
      <!-- Vendor CSS Files -->
      <link href="{{ asset('assets/backend') }}/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
      <link href="{{ asset('assets/backend') }}/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
      <script src="{{ asset('assets/frontend') }}/js/jquery-3.6.0.min.js"></script>
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
      <link rel="preconnect" href="https://fonts.googleapis.com">
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;1,400;1,600;1,800&display=swap" rel="stylesheet">
      <style type="text/css">
      body{
         font-family: 'Poppins';
         font-weight: 600;
      }
      .card{
          box-shadow: 0px 5px 20px 0px #d2dae3;
      }
      .text-muted{
         letter-spacing: 0.1rem;
      }
      .information{
         letter-spacing: 0.4rem;
         text-transform: uppercase;
      }
      .bi-profile{
         font-size: 30px;
         color: #0adc26 !important;
      }
      .list-group-item{
         padding: 17px;
      }
      .input-lg{
         height: 50px;
         letter-spacing: .2rem;
         font-size: 16px;
         font-weight: 600;
         text-align: center;
      }
      .btn:hover{background-color: #b22e6a;
    border-color: #fff;
    color: #fff;}
      </style>
   </head>
   <body>
      <main>
         <div class="container">
            <section class="section d-flex flex-column align-items-center justify-content-center">
               <div class="container">
                  <div class="row justify-content-center mt-5">
                     <div class="col-lg-5 col-md-5 align-items-center justify-content-center">
                        <div class="d-flex justify-content-center">
                           <a href="#" class="logo d-flex align-items-center w-auto">
                              <img src="{{ asset('assets/backend') }}/img/logo.png" alt="">
                              <!--<span class="d-none d-lg-block">Bice</span>--->
                           </a>
                        </div>
                        <!-- End Logo -->
                        <div class="card mb-3" id="verifyForm">
                           <div class="card-body">
                              <div class="pt-4 pb-2">
                                 <h5 class="card-title text-center pb-0 fs-4 fw-bold">BICS Verify</h5>
                                 <!-- <p class="text-center small">Enter your username & password to login</p>-->
                              </div>
                              <form class="row g-3" id="GetQrcode" method="GET">
                                 @csrf 
                                 <div class="col-12">
                                    <input type="text" name="qrcode" class="form-control input-lg" placeholder="Enter pin number" id="qrcode" maxlength="6">
                                    <div class="invalid-feedback">Please enter Pin Number!</div>
                                 </div>
                                 {{-- {{ $verifyData->qr_code }} --}}
                           </div>
                           <div class="col-6 mx-auto mb-3">
                           <button type="submit" class="btn btn-primary w-100 btn-lg active" id="show-data">Verify <i class="bi bi-arrow-right-circle"></i></button>
                           </div>
                           </form>
                        
                           
                        </div>
                     </div>
                  </div>
                  <div class="row justify-content-center" id="profile">
                     <div class="col-lg-5 col-md-5 align-items-center justify-content-center">
                     <div class="card">
                        <div class="card-body" id="data-value">
                             <ul class="list-group">
                              <li class="list-group-item active bg-primary text-center information">Verifed Information's</li>
                              <li class="list-group-item" ><span>Name: </span><span class="text-muted" id="name"></span></li>
                              <li class="list-group-item" ><span>Profession: </span><span class="text-muted" id="profession"></span></li>
                              <li class="list-group-item" ><span>Phone: </span><span class="text-muted" id="phone"></span></li>
                              <li class="list-group-item" ><span>Email: </span><span class="text-muted" id="email"></span></li>
                              <li class="list-group-item" ><span>Country: </span><span class="text-muted" id="country"></span></li>
                              <li class="list-group-item" ><span>Verify Code: </span><span class="text-muted" id="pincode"></span> <i class="bi bi-patch-check-fill bi--3xl text-success"></i></li>
                         </ul>
                        </div>
                       </div>
                       </div>
                  </div>
               </div>
         </div>
         </section>
         </div>
      </main>
      <!-- End #main -->
      <!-- Vendor JS Files -->
      <script src="{{ asset('assets/backend') }}/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
      <script>
         $(document).ready(function(){
            $('#profile').hide();
            $.ajaxSetup({
                  headers: {
                     'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                  }
            });
               //$("#show-data").click(function(){
                   
            $('#GetQrcode').on('submit', function(e){
                  e.preventDefault();
                  //var qrcode = $("#qrcode").val();
                  var qrcode = Number($('#qrcode').val().trim());
                   if(!qrcode){
                      // alert('Please Only input 6 digit Pin number!');
                         Swal.fire({
                             icon: 'error',
                             title: 'Please Only input 6 digit Pin number!',
                          })
                  //  }else{
                  //    alert(qrcode);
                   }
                   if(qrcode > 0){
                      // AJAX POST request
                     $.ajax({
                        url: 'verifyData',
                        type: 'get',
                        contentType: "application/json",
                        data: {qrcode: qrcode},
                        dataType: 'json',
                        success: function(data){
                           console.log(data.success);
                           if(data.success==true){
                              //Check verification status
                              if(data["0"].verify_status == 'Yes'){
                                 Swal.fire({
                                    icon: 'warning',
                                    title: 'Sorry - Already Verified!',
                                 })
                              }else{
                                 Swal.fire({
                                  //position: 'top-end',
                                  icon: 'success',
                                  //title: 'Congratulation ! Verification Done'+'( '+data["0"].qr_code+' )',
                                  title: 'Congratulation ! Verification Done',
                                  showConfirmButton: true,
                                  //timer: 2500
                                })
                                $('#profile').show();
                                $('#verifyForm').hide();
                                $('#name').html(data["0"].firstname+' '+data["0"].lastname); 
                                $('#profession').html(data["0"].profession); 
                                $('#phone').html(data["0"].phone); 
                                $('#email').html(data["0"].email); 
                                $('#country').html(data["0"].country); 
                                $('#pincode').html(data["0"].qr_code); 
                              }
                           }else{
                              Swal.fire({
                                 icon: 'error',
                                 title: 'Pin Does Not Match!',
                              })
                           }                     
                     },
                     error: function(response) {
                        console.log(response.error);
                       
                     }              
                     });
                 }
               });
         });
      </script>
   </body>
</html>