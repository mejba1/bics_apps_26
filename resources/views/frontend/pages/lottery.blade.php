<!DOCTYPE html>
<html lang="en">
   <head>
      <title>Bics | Automated Raffle Draw</title>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <script src="{{ asset('assets/frontend') }}/js/jquery-3.6.0.min.js"></script>
      <script src="{{ asset('assets/frontend') }}/js/jquery.numscroll.min.js"></script>
      <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
     
      <link rel="preconnect" href="https://fonts.googleapis.com">
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700&family=Prompt:wght@700&family=Tektur&display=swap" rel="stylesheet">
      <link href="{{ asset('assets/backend') }}/vendor/quill/quill.bubble.css" rel="stylesheet">
      
     <style type="text/css">
         .btn-circle.btn-xl {
         width: 300px;
         height: 300px%;
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
         font-size: 25px;
         color: #128325;
         }
         .resultinfo{
         border: 2px solid red;
         padding:10px;
         font-weight:bold;
         font-size: 20px;
         }
         /*--====Go-Design======--*/
         .animate-charcter{
         text-transform: uppercase;
         background-image: linear-gradient(
         -225deg,
         #231557 0%,
         #44107a 29%,
         #ff1361 67%,
         #fff800 100%
         );
         background-size: auto auto;
         background-clip: border-box;
         background-size: 200% auto;
         color: #fff;
         background-clip: text;
         text-fill-color: transparent;
         -webkit-background-clip: text;
         -webkit-text-fill-color: transparent;
         animation: textclip 2s linear infinite;
         display: inline-block;
         font-size: 190px;
         }
         @keyframes textclip {
         to {
         background-position: 200% center;
         }
         }
         #congratulate{
         font-family: 'Calligraffitti', cursive;
         font-weight: 700;
         font-size: 40px;
         position: absolute;
         text-shadow: -15px 5px 20px #ced0d3;
         top: 120%;
         left: 50%;
         transform: translate(-50%,-50%);
         letter-spacing: 0.02em;
         text-align: center;
         color: #EF5097;
         }
#refresh{
   margin-top: 130px;
   margin-left: 130px;
}
         
      </style>
   </head>
   <body class="bg-info">
    <div class="container mt-3">
      {{-- 
      <h2 class="text-center">Bics Automated Raffle Draw System</h2>
      --}}
      <div class="card pb-5">
      <div class="container-fluid p-3 bg-primary text-white text-center">
         <h2>Bics Automated Raffle Draw System</h2>
      </div>
      <div class="container-fluid displayNo p-2 bg-warning text-muted text-center">
         
         <marquee scrollamount="20">
            <h1 class="mt-1  fw-bold"><span id="showRandomCode">All Number Show</span></h1>
         </marquee>
      </div>
      <div class="card-body mt-5">
         <div class="row">
            <div class="col-sm-3 col-md-6 col-lg-4">
               <div id="resultarea">
                  <div class="card mt-2" id="resultarea1">
                     <div class="card-body text-center ">
                        <div class="card-header">
                           <h1>Winner is:</h1>
                        </div>
                        <h1 class="card-text mt-5 card-title text-white fw-bold bg-success p-3 animate-charcter" id="result" style="letter-spacing: 0.5em;font-size:40px"></h1>
                        <p id="congratulate" class="mt-3"></p>
                     </div>
                  </div>
               </div>
            </div>
            <div class="col-sm-3 col-md-6 col-lg-4 id-btn">
               <div class="profile-box">
                  <button type="button startButton" id="countdown"  onclick="startCountdown()" class="btn btn-primary btn-circle btn-xl fw-bold animate-charcter">GO</button>
                 </div>
               <h1 class="align-middle mt-5 text-success fw-bold" id="winer-wish">Congratulation's !</h1>
              
               {{-- 
               <marquee scrollamount="30">
                  <h1 class="mt-3 test-danger fw-bold"><span id="showRandomCode">All Number Show</span></h1>
               </marquee>
               --}}
               <audio id="myAudio">
                  <source src="{{ asset('assets/frontend') }}/videos/20-countdown.mp3" type="audio/mpeg">
                  Your browser does not support the audio element.
               </audio>
               <button type="button" id="refresh" class="btn btn-primary btn-xl fw-bold"  onClick="window.location.reload();">Go for the <br>Next Winner</button>
               
            </div>
            <div class="col-sm-3 col-md-6 col-lg-4">
               <img  id="animationImg" src="{{ asset('assets/frontend') }}/images/animation_refine.gif" width="250" height="250" alt="">
               <div class="card" id="profile-show">
                  <div class="card-body text-center">
                     <span class="badge-box"><i class="fa fa-check"></i></span>
                     <h4 class="card-title">Md. Mejbabul Alam</h4>
                     <p class="card-text">Number: 0300018</p>
                     <p class="card-text"><strong>Venue:</strong> Dhaka,Bangladesh<br/>Date: 5<sup>th</sup> July, 2022</p>
                  </div>
                  <div class="col-12 mt-3">
                     <div class="text-center">
                        <button class="btn btn-primary w-50 active" type="submit"><i class="bi bi-check"></i> Accept</button>                        
                     </div>
                  </div>
               </div>
            </div>
            <div>
            </div>
         </div>
      </div>
      <script>
         document.getElementById("resultarea").style.display = "none";
         document.getElementById("showRandomCode").style.display = "none";
         document.getElementById("refresh").style.display = "none";
         document.getElementById("winer-wish").style.display = "none";
         document.getElementById("profile-show").style.display = "none";
         document.getElementById("animationImg").style.display = "none";

         function raffleDraw(numbers) {
           const randomIndex = Math.floor(Math.random() * numbers.length);
           return numbers[randomIndex];
         }
         function countdown(seconds) {
           let counter = seconds;
           const countdownElement = document.getElementById('countdown');
           const resultElement = document.getElementById('result');
           const resultElementCong = document.getElementById('congratulate');
           document.getElementById("resultarea").style.display = "block";
           document.getElementById("showRandomCode").style.display = "block";
           document.getElementById("animationImg").style.display = "block";
          
          
           const buttonElement = document.getElementsByClassName('startButton');
         
           // Disable the button while countdown is running
           buttonElement.disabled = true;
         
           const interval = setInterval(() => {
             if (counter < 1) {
               clearInterval(interval);
               const selectedNumber = raffleDraw(numbers);
               resultElement.textContent = `${selectedNumber}`;
               //resultElementCong.textContent = `Congaratulations!`;
               //After geeting the number, display will none
               document.getElementById("showRandomCode").style.display = "none";
               document.getElementById("countdown").style.display = "none";
               document.getElementById("refresh").style.display = "block";
               document.getElementById("winer-wish").style.display = "block";
               
              document.getElementById("profile-show").style.display = "block";
              document.getElementById("animationImg").style.display = "none";
               
             resultElement.classList.add('congratulation');
               // Enable the button after countdown finishes
              buttonElement.disabled = true;
               
             } else {
             // document.getElementsByClassName("profile-box").style.display = "none";
               countdownElement.textContent = `${counter}`;
               countdownElement.classList.add('resultinfo');
               counter--;
             }
           }, 1150);
         }
         
         const numbers = {!! json_encode($numbers) !!};
         const countdownSeconds = {!! json_encode($countdownSeconds) !!};
         console.log(numbers);
         //Number Showing in scrolling
         document.getElementById("showRandomCode").innerHTML = numbers;
         
         function startCountdown() {
           console.log("Starting countdown...");
           countdown(countdownSeconds);
         }
         
         
         //Coundown Audio
         var x = document.getElementById("myAudio"); 
         
         // function startCountdown() { 
         //   x.play(); 
         // } 
         
         // // function pauseAudio() { 
         // //   x.pause(); 
         // // } 
         
         $(document).ready(function(){
         $(".animate-charcter").click(function(){
          x.play(); 
         });
         });
      </script>
   </body>
</html>