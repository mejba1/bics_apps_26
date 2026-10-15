<!DOCTYPE html>
<html lang="en">
   <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>BICS Management Card</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
     </head>
   <body>
      <div class="container" style="margin: auto 0">
         <div class="row">
            <div class="col-md-4"></div>
            <div class="col-md-4">
               <div class="m-4">
                  <div class="card mx-auto" style="width:300px;height:480px;background-color: #9e0ea1">
                     <div class="card-body text-center">
                        <div class="card card-01" style="height:415px;background-color: rgba(255, 255, 255, 0.896);">
                           <h2 class="card-title text-primary text-center fw-bold mt-4 text-underline" style="font-family: sans-serif;"><span style="color:#0e4ca1">B</span><span style="color:#B22E6B">I</span><span style="color:#668f41">C</span><span style="color:#519cbb">S</span> 2023</h2>
                           <h4 class="card-title text-success text-center mt-2 text-underline">Entry Pass</h4><hr/>
                           <div class="profile-box mt-2">
                              <img class="card-img-top" src="{{ $qrcode }}" alt="Bics" style="width: 120px;height: 120px;">
                           </div>
                           <div class="card-body text-center">
                              <span class="badge-box"><i class="fa fa-check"></i></span>
                              <h4 class="card-text"><strong>Name:</strong> {{ $name }}</h4>
                              <p class="card-text"><strong>Profession:</strong> {{ $profession }}</p>
                              <p class="card-text"><strong>ID:</strong> {{ $pin }}</p>
                              <p class="card-text"><strong>Venue:</strong> Algiers, Algeria<br/><strong>Date:</strong> 21-22<sup>th</sup> July, 2023</p>
                           </div>
                           <div class="row">
                              <div class="col-md-10">
                                 <p class="card-text text-white"><strong>Organized By:</strong> Beacon Pharma Ltd.</p>
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