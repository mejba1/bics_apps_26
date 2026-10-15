// Array of specific numbers for the raffle draw
	//const numbers = ["734861", "645782", "903451", "785612", "763201", "876301", "761092", "640134"];
	//console.log(numbers);
	
	//=====Initial div hide==========
	//document.getElementById("showParticipantNumber").style.display = "none";
	document.getElementById("animation-dispaly").style.display = "none";
	document.querySelector(".accept-area").style.display = "none";
	document.querySelector(".next-winner-area").style.display = "none";
	document.querySelector(".winner-area").style.display = "none";
	document.querySelector(".start-animate-odometer").style.display = "none";

	// Function to pick a random number from the array
	function pickRandomNumber() {
	  const randomIndex = Math.floor(Math.random() * numbers.length);
	  return numbers[randomIndex];
	}

	// Function to update the countdown display
	function updateCountdown(seconds) {
	  const countdownElement = document.getElementById('countdown');
	  countdownElement.textContent = seconds;
	}

	// Function to start the raffle draw
	function startRaffle() {
	  const startButton = document.getElementById('startButton');
	  startButton.disabled = true;

	  let secondsLeft = 20;
	  const countdownInterval = setInterval(() => {
		updateCountdown(secondsLeft);
		secondsLeft--;
		
		//=======Click Start lottery info show=======
		 document.getElementById("showParticipantNumber").style.display = "block";
		 document.getElementById("animation-dispaly").style.display = "block";
		 document.getElementById("startButton").style.display = "none";
		 document.querySelector(".winner-area").style.display = "none";
		 document.getElementById("showParticipantNumber").innerHTML = numbers;
		 document.querySelector(".start-animate-odometer").style.display = "block";
		 //document.querySelector(".get-ready").style.display = "none";
		 document.querySelector(".animate-zoom").style.display = "none";
		 
		 //Coundown Audio
         var x = document.getElementById("myAudio"); 
         var c = document.getElementById("crowd"); 
         x.play(); 
		 
		 //Start Number animation format
			const startAnimation = document.querySelector(".start-animate-odometer");
			const odometer = new Odometer({
			  el: startAnimation,
			  duration: 20000,
			  format: 'd',
			 // theme: 'car'
			  animation: 'count'
			})
			//odometer.update(10864);
			startAnimation.innerHTML = 789462;
		 
		if (secondsLeft < 0) {
		  clearInterval(countdownInterval);
		  const congratulateElement = document.getElementById('congratulate');
		  const winningNumber = pickRandomNumber();
		  //Audio Stop
		  x.pause(); 
		  
		   
			//========After getting the result=========
			document.getElementById("showParticipantNumber").style.display = "none";
			document.getElementById("animation-dispaly").style.display = "none";
			document.getElementById("startButton").style.display = "none";
			document.querySelector(".accept-area").style.display = "block";
			document.querySelector(".next-winner-area").style.display = "block";
			document.querySelector(".winner-area").style.display = "block";
			document.querySelector(".start-animate-odometer").style.display = "none";
			 //document.querySelector(".get-ready").style.display = "none";
			  c.play(); 
			
			//odometer Number format
			const subsOdometer = document.querySelector(".winer-odometer");
			const odometer = new Odometer({
			  el: subsOdometer,
			  duration: 100,
			  format: 'd',
			 // theme: 'car'
			  //animation: 'count'
			})
			//odometer.update(10864);
			subsOdometer.innerHTML = winningNumber;
		  
		 // congratulateElement.textContent = `Congratulations! The winning number is ${winningNumber}!`;
		  congratulateElement.textContent = `Congratulations!`;
		  updateCountdown(0);

		//Upadte after Aceept Data by ajax
		$(document).ready(function(){
			$.ajaxSetup({
				headers: {
				   'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
				}
		  });
		  $('.accept').click(function() {
			$('#profile-show').hide();

			Swal.fire({
				title: 'Do you want to Accept?',
				showCancelButton: true,
				confirmButtonText: 'Yes',
				
			  }).then((result) => {
				
				if (result.isConfirmed) {

					$('#profile-show').show();
					const prizeType = $('.prize-type').text();
					const  prize = $('#prize').text();
					//alert(prizeType);
					 const winnerNo = winningNumber;
					//alert(winnerNo);
					$.ajax({
					  url: '/winnerAccept',
					  type: 'POST',
					  data: {
						  prize:prize,prize_type:prizeType,winners_number:winnerNo,
						  //_token: '{{ csrf_token() }}'
					  },
					  success: function(data) {
						  console.log(data.success);
						  // You can update the data list here using AJAX
						  $('.full-name').html(data["0"].firstname+' '+data["0"].lastname); 
						  $('.profession').html(data["0"].profession);  
						  $('.country').html(data["0"].country); 
						  $('.accept-area').hide();
					  }
				  });
				 

				} else if (result.isDenied) {
				  Swal.fire('Changes are not saved', '', 'info')
				}
			  })
			 
		});
		

			
		});


		}
	  }, 1000);
	}
	// const numbers = {!! json_encode($numbers) !!};
	// const countdownSeconds = {!! json_encode($countdownSeconds) !!};

	// Attach click event listener to the start button
	const startButton = document.getElementById('startButton');
	startButton.addEventListener('click', startRaffle);
	
	
	//Show profile
	$(document).ready(function(){
		$("#profile-show").hide();
		$("#myAudio").hide();
	  $(".accept").click(function(){
	  //alert('show');
	  
	    $("#countdown").hide();
		$("#profile-show").show();
	  });
	});
	
	//Text Animation
	$('.animate-zoom').animatedHeadline({
		animationType: 'zoom'
	});

	
	//====Confetti -Draw Canvas Hide
	setInterval(function() {
			$("#drawing_canvas").hide();
		},3000);
	
	
	//Winner Ignore
	$('#ignore-winner').click(function(){
		Swal.fire({
			title: 'Are you sure?',
			text: "You won't be able to revert this!",
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#3085d6',
			cancelButtonColor: '#d33',
			confirmButtonText: 'Yes, Ignore!'
		  }).then((result) => {
			if (result.isConfirmed) {
				window.location.reload();
			}
		  })
	}); 

