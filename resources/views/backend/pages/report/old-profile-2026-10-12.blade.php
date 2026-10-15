<html>
<head>
    <style>
    body {
        font-family: sans-serif;
        font-size: 10pt;
		/* background-image: url('assets/frontend/images/logo.png');
		background-repeat: no-repeat;
		background-attachment: fixed;
  		background-position: center; */
    }

    p {
        margin: 0pt;
    }

    table.items {
        border: 0.1mm solid #e7e7e7;
    }

    td {
        vertical-align: top;
    }

    .items td {
        border-left: 0.1mm solid #e7e7e7;
        border-right: 0.1mm solid #e7e7e7;
    }

    table thead td {
        text-align: center;
        border: 0.1mm solid #e7e7e7;
    }

    .items td.blanktotal {
        background-color: #EEEEEE;
        border: 0.1mm solid #e7e7e7;
        background-color: #FFFFFF;
        border: 0mm none #e7e7e7;
        border-top: 0.1mm solid #e7e7e7;
        border-right: 0.1mm solid #e7e7e7;
    }

    .items td.totals {
        text-align: right;
        border: 0.1mm solid #e7e7e7;
    }

    .items td.cost {
        text-align: "."center;
    }
    </style>
</head>

<body>
    <table width="100%" style="font-family: sans-serif;border: 0.1mm solid #eee;" cellpadding="10">
        <tr>
            <td width="100%" style="padding: 0px; text-align: center;">
              <a href="#" target="_blank"><img src="{{ base_path('assets/frontend/images/logo.png') }}" width="150" height="90" alt="Logo" align="center" border="0"></a>
            </td>
        </tr>

        <tr>
          <td height="10" style="font-size: 0px; line-height: 10px; height: 10px; padding: 0px;">&nbsp;</td>
        </tr>
    </table>
	<table width="100%" style="font-family: sans-serif;" cellpadding="10">
		<tr>
			<td width="100%" style="text-align: right; font-size: 12px; font-weight: bold;">
                <strong>Report Date</strong> : {{date('d-m-Y')}}
			</td>
		</tr>
		<tr>
		  <td height="10" style="font-size: 0px; line-height: 10px; height: 10px; padding: 0px;">&nbsp;</td>
		</tr>
	</table>
	<table width="100%" style="font-family: sans-serif;" cellpadding="10">
		<tr>
			<td width="100%" style="text-align: center; font-size: 20px; font-weight: bold; padding: 0px;color:#000">
			  PROFILE
			</td>
		</tr>
		<tr>
		  <td height="10" style="font-size: 0px; line-height: 10px; height: 10px; padding: 0px;">&nbsp;</td>
		</tr>
	</table>
	<br>
	<br>
	    <table width="100%" style="font-family: sans-serif;" cellpadding="10">
        <tr>
            <td width="50%" style="border: 0.1mm solid #eee;">
				<table width="100%" align="right" style="font-family: sans-serif; font-size: 14px;" >
				    <tr>
						<th style="text-align:center;color:#0e4ca1" colspan="2">BICS REGISTER INFO</th> 
					</tr>
                    <tr>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;"><strong>Registration No</strong></td>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;">{{ $data->qr_code }}</td>
                    </tr>
                    <tr>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;"><strong>Verify Status</strong></td>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;">{{ $data->verify_status }}</td>
                    </tr>
					<tr>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;"><strong>Verify date</strong></td>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;">
                           @if($data->verify_at)
                                {{ $data->verify_at }} 
                            @else  
                            {{ "Not Verified" }}  
                            @endif
                            </td>
                    </tr>
                    <tr>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;"><strong>Registration Date</strong></td>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;">{{ $data->created_at }}</td>
                    </tr>
					<tr>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;"><strong>Vanue</strong></td>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;">{{ 'Spain', $data->venue }}</td>
                    </tr>
                   
					
                </table>
			
			</td>
            <td width="10%">&nbsp;</td>
            <td width="20%" style=""><img src="{{ $data->qr_img_path }}" style="border: 0.1mm solid #eee;" width="120" height="120" alt="Logo" align="center" border="0"></td>
            <td width="20%" style="">
                @php $photoPath = base_path('upload/images/'.$data->image); @endphp
                @if(is_file($photoPath))
                <img src="{{ $photoPath }}" style="border: 0.1mm solid #eee;" width="120" height="120" alt="Photo" align="center" border="0">
                @endif
            </td>
        </tr>
    </table>
    <br><br><br>
    <table width="100%" style="font-family: sans-serif; font-size: 14px;border: 0.1mm solid #eee;">
        <tr>
            <td>
                <table width="100%" align="right" style="font-family: sans-serif; font-size: 14px;" >
				    <tr>
						<th style="text-align:center;color:#0e4ca1" colspan="2">PERSONAL INFROMATION'S</th> 
					</tr>
                    <tr>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;"><strong>Name</strong></td>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;"> {{ $data->firstname.' '.$data->lastname }}</td>
                    </tr>
                    <tr>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;"><strong>Country</strong></td>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;">{{ $data->country }}</td>
                    </tr>
                    <tr>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;"><strong>E-mail</strong></td>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;">{{ $data->email }}</td>
                    </tr>
                    <tr>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;"><strong>Phone</strong></td>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;">{{ $data->phone }}</td>
                    </tr>
                    <tr>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;"><strong>Gender</strong></td>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;">{{ $data->gender }}</td>
                    </tr>
					<tr>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;"><strong>Date Of Birth</strong></td>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;">{{ $data->birthday }}</td>
                    </tr>
					<tr>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;"><strong>{{ $data->identification_type }}</strong></td>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;">{{ $data->identification_no }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
<br/>
<br/>
<table width="100%" style="font-family: sans-serif; font-size: 14px;border: 0.1mm solid #eee;">
         <tr>
					<th colspan="2" style="text-align:center;color:#0e4ca1">PROFESSIONAL INFROMATION'S</th>                 
                    </tr>
                    <tr>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;"><strong>Profession</strong></td>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;">{{ $data->profession }}</td>
                    </tr>   
					<tr>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;"><strong>Designation</strong></td>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;">{{ $data->designation }}</td>
                    </tr>
                    <tr>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;"><strong>Speciality</strong></td>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;">{{ $data->specialty }}</td>
                    </tr>
                    <tr>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;"><strong>Institute Name</strong></td>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;">{{ $data->institute }}</td>
                    </tr>
                    <tr>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;"><strong>Institiute Address</strong></td>
                        <td style="border: 1px #eee solid; padding: 0px 8px; line-height: 20px;">{{ $data->institute_address }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
	<br><br><br><br><br><br><br>
    <table width="100%" style="font-family: sans-serif; font-size: 14px;" >
        <br>
		<hr>
        <tr>
            <td>

                <table width="100%" align="left" style="font-family: sans-serif; font-size: 10px; text-align: center;">
                    <tr>
                        <td style="padding: 0px; line-height: 20px;color:#eac8d6">
                            <strong>Developed By @ BML IT Team</strong>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
        <br>
    </table>
</body>
</html>