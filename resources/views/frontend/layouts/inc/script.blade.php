<script src="{{ asset('assets/frontend') }}/js/jquery-3.6.0.min.js"></script>
<script src="{{ asset('assets/frontend') }}/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('assets/frontend') }}/js/jquery-ui.js"></script>
<script src="{{ asset('assets/frontend') }}/js/wow.min.js"></script>
<script src="{{ asset('assets/frontend') }}/js/jquery.fancybox.min.js"></script>
<script src="{{ asset('assets/frontend') }}/js/jquery.counterup.js"></script>
<script src="{{ asset('assets/frontend') }}/js/jquery.waypoints.js"></script>
<script src="{{ asset('assets/frontend') }}/js/main.js"></script>
<script src="{{ asset('assets/frontend') }}/js/bics-theme.js?v={{ @filemtime(base_path('assets/frontend/js/bics-theme.js')) }}"></script>
<script src="{{ asset('assets/frontend') }}/js/lobibox.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
{{-- //Registration Form --}}
<script>
//Date Picker
$(function () {
    $('#birthday').datepicker({ 
        format: 'yyyy-mm-dd' 
    });
});
//Country Change with country code
    $(document).ready(function(){
        $('#country').change(function(){
           // alert($(this).find(':selected').data('id'));
            var countryName = $(this).find(':selected').data('id');
            document.getElementById("setIdValue").innerHTML = countryName;
            document.querySelector('#countryCode').value = countryName;
        });
    });

    $(document).ready(function(){
        $('#identification_type').change(function(){
            //alert($(this).find(':selected').data('id'));
            var IdType = $(this).find(':selected').data('id');
            document.getElementById("setIdtype").innerHTML = IdType;
        });
    });
</script>

{{-- //Ajaxa data  Submit --}}

<script>
     $(document).ready(function(){

$.ajaxSetup({
headers: {
    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
}
 });

$(document).on('submit', '#formData', function(e){
   // alert('tets');
    e.preventDefault();
     let formData = new FormData($('#formData')[0]);
     $.ajax({
        type: "POST",
        url: "{{ url('/ajaxstore') }}",
        
        data: formData,
        contentType: false,
        processData: false,
        //dataType: 'json',
        beforeSend: function() {
            $("#bics_register_btn").prop('disabled', true);
        },
        success:function(response){
            console.log('success');
            if(response.success) {
                        console.log('Success data!');
                            Lobibox.notify('success', {
                                class: 'lobibox-notify-success',
                                icon: 'glyphicon glyphicon-ok-sign',
                                position: 'bottom right',
                                // size: 'large',  
                                title: 'Success', 
                                msg: 'Please wait - We are process to send a email Confirmation ! thanks for your patients',
                                  
                            });
                            // Lobibox.progress({
                            //     title: 'Please wait',
                            //     label: 'We are process to send a email Confirmation',
                            //     position: 'top right',
                            //     onShow: function ($this) {
                            //         var i = 0;
                            //         var inter = setInterval(function () {
                            //             window.console.log(i);
                            //             if (i > 100) {
                            //                 inter = clearInterval(inter);
                            //             }
                            //             i = i + 0.1;
                            //             $this.setProgress(i);
                            //         }, 10);
                            //     }
                            // });  
                            //window.location = "https://bics.org/email-notify";  
                            // setTimeout(function(){
                            //     window.location.reload(1);
                            //  }, 5000);                           
                    }
        },
        error: function(response) {
            console.log('Faild!');
            //$('#message').html("<div class='alert alert-danger text-center mt-2'><strong>Registration Faild!</strong> Some fild are missing, Please check & fill the required fill!</div>");
            Lobibox.alert('warning', {
                    title: 'Missing Info',
                    iconClass: 'glyphicon glyphicon-remove-sign',
                    position: 'top right',
                    'class': 'lobibox-notify-info',
                    msg: "Some required field are missing, Please check carefully & try again !",
                });
                $("#bics_register_btn").prop('disabled', false);
        }
     });
});
});


//show loading onclick submit
$(document).ready(function(){
    // Display animation onclick
    $('.submit-btn').click(function(){
      let timerInterval
        Swal.fire({
          title: 'Please Wait !',
         // html: 'I will close in <b></b> milliseconds.',
          timer: 10000,
          timerProgressBar: true,
          didOpen: () => {
            Swal.showLoading()
            const b = Swal.getHtmlContainer().querySelector('b')
            timerInterval = setInterval(() => {
              b.textContent = Swal.getTimerLeft()
            }, 100)
          },
          willClose: () => {
            clearInterval(timerInterval)
          }
        }).then((result) => {
          /* Read more about handling dismissals below */
          if (result.dismiss === Swal.DismissReason.timer) {
            console.log('I was closed by the timer')
          }
        })
    });
    
});

//Image preview in Registration
//file images
function preview(){
    frame.src = URL.createObjectURL(event.target.files[0]);
  }
  
 //Dependancy Select for Profession & Speciality
 
  $("#profession").change(function () {
    $.ajaxSetup({
        headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
        },
    });

    const profession = $(this).val();

   if(profession == "Medical Doctor"){
      profession_id =1
    }
    if(profession == "Pharmacist"){
      profession_id =2
    }
    if(profession == "Pathologist"){
      profession_id =3
    }
    if(profession == "Government Official"){
      profession_id =4
    }
    if(profession == "Procurement Officer"){
      profession_id =5
    }
    if(profession == "Nurse"){
      profession_id =6
    }

    //alert(profession_id);
    $.ajax({
      type: "POST",
      url: "{{ url('/speciality') }}",
      data: {profession_id: profession_id},
        success: function (result) {
          console.log(result);
          $("#specialty").empty();
            // $("#specialty").append(
            //     '<option selected disabled value="">Select Speciality</option>'
            // );
            if (result && result?.status === "success") {
              $.each(result.data, function (key, value) {
                    $("#specialty").append('<option value="' + value.speciality + '">' + value.speciality + '</option>');
                  });
            }
        },
    });
});
</script>