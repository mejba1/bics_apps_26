<!DOCTYPE html>
<html>
<head>
  <title>Bics | Automated Raffle Draw</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
  <style type="text/css">
    .btn-circle.btn-xl {
        width: 300px;
        height: 300px;
        padding: 13px 18px;
        border-radius: 50%;
        font-size: 150px;
        text-align: center;
    }
    .header-title{
        border-bottom: 2px solid #ac2a8e;
        padding: 25px;
        text-transform: uppercase;
    }
.congratulation {
  text-align: center;
  font-size: 30px;
  color: #fff;
}
.resultinfo{
    border: 2px solid red;
    padding:10px;
    font-weight:bold;
    font-size: 20px;
}
</style>
<script>
    function raffleDraw(numbers) {
      const randomIndex = Math.floor(Math.random() * numbers.length);
      return numbers[randomIndex];
    }

    function countdown(seconds) {
      let counter = seconds;
      const countdownElement = document.getElementById('countdown');
      const resultElement = document.getElementById('result');
      const buttonElement = document.getElementById('startButton');

      // Disable the button while countdown is running
      buttonElement.disabled = true;

      const interval = setInterval(() => {
        if (counter < 0) {
          clearInterval(interval);
          const selectedNumber = raffleDraw(numbers);
          resultElement.textContent = `Congaratulations ! The Winer number is: ${selectedNumber}`;
          //resultElement.textContent = `Congaratulations ! The Winer number is: ${name}`;
		  resultElement.classList.add('congratulation');
          // Enable the button after countdown finishes
          buttonElement.disabled = false;
        } else {
          countdownElement.textContent = `${counter}`;
		  countdownElement.classList.add('resultinfo');
          counter--;
        }
      }, 1000);
    }

    const numbers = {!! json_encode($numbers) !!};
    const countdownSeconds = {!! json_encode($countdownSeconds) !!};

    function startCountdown() {
      console.log("Starting countdown...");
      countdown(countdownSeconds);
    }
  </script>
</head>
<body>
    <div class="container mx-auto">
       <div class="row">
             <div class="col-md-12">
                <div class="card" style="background-image: url('assets/frontend/images/raffle-draw/events-banner.png');">
                    {{-- <img src="{{ asset('assets/frontend') }}/images/contact-bics.png"> --}}
                   <div class="card-body text-center">
                      <div class="card card-01" style="background-color: #ffffff8f;">
                         <h4 class="card-title text-center mt-2 mb-3 header-title"><span style="color:#0e4ca1">B</span><span style="color:#b22e6a">I</span><span style="color:#668f41">C</span><span style="color:#519cbb">S</span> Rafel Draw Autometed System</h4>
                         <div class="profile-box">
                            <button type="button" id="countdown" class="btn btn-primary btn-circle btn-xl fw-bold">0</button>
                         </div>
                          
                          <div class="card bg-info mt-2">
                            <div class="card-body text-center">
                              <p class="card-text"id="result"></p>
                              </div>
                          </div> 
                           
                            <div class="card-body text-center">
                            <button type="button" id="startButton" onclick="startCountdown()" class="btn btn-primary">Start Draw</button>
                            </div>
                         <div class="card-body text-center">
                            <span class="badge-box"><i class="fa fa-check"></i></span>
                            <h4 class="card-title">Md. Mejbabul Alam</h4>
                            <p class="card-text">ID: 0300018</p>
                            <p class="card-text"><strong>Venue:</strong> Dhaka,Bangladesh<br/>Date: 5<sup>th</sup> July, 2022</p>
                         </div>
                         <div class="row">
                            <div class="col-md-10">
                               <p class="card-text"><img src="{{ asset('assets/frontend') }}/images/raffle-draw/logo.png" alt="Bics" style="width: 40px;height: 40px;top: -10px;position: relative;">Beacon Pharma Ltd.</p>
                            </div>
                            <div class="col-md-2">
                               <img src="{{ asset('assets/frontend') }}/images/raffle-draw/qr-code.png" alt="Bics" style="width: 40px;height: 40px;left: -25px;top: -7px;position: relative;">
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
