<!DOCTYPE html>
<html lang="en">
   <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>BICS - Entry Pass Card</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
     </head>
   <body>
      <div class="container">
         <div class="row">
            <div class="col-md-4"></div>
            <div class="col-md-4">
               <div class="m-4">
                  <div class="card mx-auto" style="width:400px;height:575px;background-color: #9e0ea1">
                     <div class="card-body text-center">
                        <div class="card card-01" style="height:475px;background-color: rgba(255, 255, 255, 0.896);">
                           {{-- <h2 class="card-title text-primary text-center fw-bold mt-4 text-underline" style="font-family: sans-serif;"><span style="color:#0e4ca1">B</span><span style="color:#B22E6B">I</span><span style="color:#668f41">C</span><span style="color:#519cbb">S</span> 2023</h2> --}}
                           <h2 class="card-title"><img src="{{ asset('assets/frontend') }}/images/logo.png" alt="logo" height="85"> 2024</h2>
                           <h4 class="card-title text-success text-center text-underline">Entry Pass</h4><hr/>
                           <div class="profile-box">
                               @if($data->image == 'no-photo.png')
                               <img class="card-img-top" src="{{ $data->qr_img_path }}" alt="Bics" style="width: 120px;height: 120px;border: 1px solid #ddd7d7;padding: 5px;">
                               @else
                               <img class="card-img-top" src="{{ asset('upload/images/').'/'.$data->image }}" alt="Bics" style="width: 120px;height: 120px;border:1px solid #cdc0c0;padding:3px">
                               @endif
                         </div>
                           <div class="card-body text-center">
                              <span class="badge-box"><i class="fa fa-check"></i></span>
                              <p class="card-text"><strong>Name:</strong> {{ $data->firstname.' '.$data->lastname }}</p>
                              <p class="card-text"><strong>Profession:</strong> {{ $data->profession }}</p>
                              <p class="card-text"><strong>ID:</strong> {{ $data->qr_code }}</p>
                              <p class="card-text"><strong>Venue:</strong> Barcelona, Spain <br/><strong>Date:</strong> 13 - 17<sup>th</sup> September, 2024</p>
                           </div>
                           <div class="row">
                              <div class="col-md-10">
                                 <p class="card-text text-white mt-2 text-center"><strong>Organized By:</strong> Beacon Pharma Ltd.</p>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
            <div class="col-md-4"></div>
         </div>
      </div>
   </body>
</html>