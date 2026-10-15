<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VerifyController extends Controller
{
    public function verifyinfo(){
        return view('frontend.pages.verifyinfo'); 
    }   

 public function verifyData(Request $request){
        $pin = (string) $request->qrcode;
        if (!preg_match('/^\d{1,12}$/', $pin)) return response()->json(['msg' => 'Sorry - Reg. Number Does Not Match!'], 200);
        $verifyData = DB::table('registrations')->where('qr_code', $pin)
            ->select('id', 'firstname', 'lastname', 'profession', 'phone', 'email', 'country', 'qr_code', 'verify_status', 'verify_at', 'venue')->first();
    // Fetch all records
        if($verifyData && !\App\Support\EventLock::isCurrentOrUnlocked(\App\Support\EventLock::forRegistration($verifyData->id))){
            return response()->json(['msg' => 'This registration is for a previous event. Check-in is only open for the current event.'], 200);
        }
        if($verifyData){

            // check in (once; also emails the certificate when the event sends them on check-in)
            $full = DB::table('registrations')->where('id', $verifyData->id)->first();
            \App\Support\CheckIn::confirm($full, 'desk', ['ip' => $request->ip(), 'user_id' => optional($request->user())->id]);

              //retrun data to view
            return response()->json(['success'=>true, $verifyData]); 
        }else{
            $msg = "Sorry - Reg. Number Does Not Match!";
            return response()->json(array('msg'=> $msg), 200);
        }
           
    }     
}


