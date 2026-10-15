<!DOCTYPE html>
<html>
<head>
<style>
  table, th, td {
    border: 1px solid #80bfff;
    border-collapse: collapse;
  }
  td {
    padding: 5px;
    text-align: left;
    font-size: 12px;
    text-align: center;
  }
  
  th {
    font-weight: bold;
    padding: 5px;
    text-align: left;
    font-size: 12px;
    text-align: center;
  }
  
  .header, .footer {
    width: 100%;
    text-align: center;
  }
  
  .title-heading {
    background: #017dc7;
    color: #fff;
  }
  .footer {
    position: fixed;
    bottom: 0;
	  margin-top:5px;
  }
  
  .pagenum:before {
    content: counter(page);
  }
  
  .left-block {
    display: inline-block;
    width: 25%;
    height: 20px;
    padding: 5px;
  }
  
  .middle-block {
    display: inline-block;
    width: 37%;
    height: 20px;
    margin-top: 20px;
    color: #66b3ff;
    font-weight: bold;
  }
  .right-block {
    display: inline-block;
    width: 33%;
    height: 20px;
  }
  .right-block span {
    font-size: 12px !important;
  }
  
  h4 {
    line-height: 10px;
    margin-top: 0;
    margin-bottom: 0;
  }
  
  .report-date {
    text-align: right;
    margin-top: 40px;
    font-size: 12px;
    margin-right: 38px;
  }
  
.report-venue {
    text-align: center;
    text-decoration: underline;
    font-size: 25px;
    color:red;
  }
  
  .copy-right {
    font-size: 10px;
    border-top: 1px dotted gray;
  }
  
  .page-number {
    text-align: right; 
    font-size: 10px;
    font-style: italic;
    margin-right: 38px; 
  }
</style>
</head>
<body>
<div class="header">
  <div class="left-block"><img src="https://bics.org/assets/frontend/images/logo.png" style="width: 160px;height: 80px;"></div> 
  <div class="middle-block"></div> 
  <div class="right-block">
    <h4>Corporate Office:</h4>
    <span>9/B/2,Toyenbee Circular Road,Motijheel,Dhaka</span><br/>
    {{-- <span>Tel: +880-2-57165371-79,Fax:+880-2-57165380</span><br/> --}}
    <span>E-mail: info@beaconpharma.com.bd</span>
  </div> 
</div>
<!-- Define header and footer blocks before your content -->
<p class="report-date"><strong>Report Date</strong> : {{date('d-m-Y')}}</p>
<h2 class="report-venue">{{ $data->first()->venue }}</h2>
<table style="width:100%">
  <thead> <!-- Include the table header here -->
    <tr>
      <th colspan="10" class="title-heading text-uppercase"> BICS - Registered Total ( {{ $count = DB::table('registrations')->where('venue', $data->first()->venue)->count(); }} )</th>
    </tr>
    <tr>
      <th>#</th>
      <th>Reg. No</th>
      <th>Name</th>
      <th>Email</th>
      <th>Phone</th>
      <th>Gendar</th>
      <th>DoB</th>
      <th>Profession</th>
	    <th>Country</th>
      <th>Registration Date</th>
      {{-- <th>Verify Date</th>
      <th>Photo</th> --}}
    </tr>
  </thead>
  <tbody> <!-- Include the table body here -->
    @foreach($data as $data)
    <tr>
      <td>{{$loop->iteration}}</td>
      <td>{{$data->qr_code}}</td>
      <td>{{ $data->firstname.' '.$data->lastname}}</td>   
      <td>{{ $data->email }}</td>      
      <td>{{ $data->phone }}</td> 
      <td>{{ $data->gender }}</td>      
      <td>{{ $data->birthday }}</td>      
      <td>{{ $data->profession }}</td>      
      <td>{{ $data->country }}</td>      
      <td>{{ $data->created_at }}</td>   
      {{--  <td>{{ $data->verify_at }}</td>   
     <td>
          @if($data->image == 'no-photo.png')
             {{ 'No Photo' }}
         @else
         <img class="card-img-top" src="{{ asset('upload/images/').'/'.$data->image }}" alt="Photo" style="width: 50px;height: 50px;padding:5px"></td>  
         @endif                         
      --}}
    </tr>
    @endforeach
  </tbody>
</table>
<div class="footer">
  <span class="copy-right">Developed By @ Mejba (BML IT)</span>
  <div class="page-number">Page <span class="pagenum"></span></div>
</div>
</body>
</html>
 