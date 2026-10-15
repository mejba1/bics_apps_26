<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use App\Models\Speciality;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\FormOption;
use App\Support\EventSettings;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Notifications\EmailNotification;

class RegistrationController extends Controller
{

public function store(Request $request)
{
    // Spam trap: people never fill this hidden field, bots do
    if ($request->filled('reg_hp_check')) {
        \Log::notice('BICS registration blocked by spam trap', ['ip' => $request->ip(), 'email' => $request->email]);
        $message = 'Your submission could not be verified. Please reload the page and try again.';
        return $request->expectsJson()
            ? response()->json(['message' => $message, 'errors' => ['form' => [$message]]], 422)
            : back()->withInput()->withErrors(['form' => $message]);
    }

    // Registration window (Admin > Events (current event))
    $window = EventSettings::registration();
    if ($window['state'] !== 'open') {
        $message = $window['state'] === 'upcoming'
            ? 'Registration opens on ' . $window['opens']->format('j F Y, H:i') . '. Please come back then.'
            : EventSettings::get('registration_closed_message', 'Registration for this event is now closed.');
        return $request->expectsJson()
            ? response()->json(['message' => $message, 'errors' => ['form' => [$message]]], 422)
            : redirect('/registration')->withErrors(['form' => $message]);
    }

    // Same rules as the admin form (App\Services\RegistrationService)
    $service = app(\App\Services\RegistrationService::class);
    [$rules, $messages, $attributes] = $service->validationRules($request);
    $rules['email'] = array_merge(is_string($rules['email']) ? explode('|', $rules['email']) : $rules['email'], [\App\Support\FormGuard::realEmail()]);   // new sign-ups only
    $input = $request->validate($rules, $messages, $attributes);

    // One registration per email for the current event
    if ($service->isDuplicate($request->email)) {
        $message = 'This email is already registered for this event. Please use a different email.';
        return $request->expectsJson()
            ? response()->json(['message' => $message, 'errors' => ['email' => [$message]]], 422)
            : redirect()->back()->withInput()->withErrors(['email' => $message]);
    }

    $result = $service->register($input + $request->only('countryCode'), $request->file('image'));
    $data = $result['registration'];

    // AJAX form: answer with JSON for the success pop-up
    if ($request->expectsJson()) {
        return response()->json([
            'success' => true,
            'code' => (string) $data->qr_code,
            'name' => trim($data->title . ' ' . $data->firstname . ' ' . $data->lastname),
            'email' => $data->email,
            'email_sent' => $result['email_sent'],
            'message' => $result['message'],
            'card_url' => $result['card_url'],
        ]);
    }

    return redirect('/success')
        ->with('success', 'Please preserve this registration number for verification and raffle draw.')
        ->with('mailInfo', $result['message'])
        ->with('code', $data->qr_code)
        ->with('card_url', $result['card_url'])
        ->with('reg_name', trim($data->title . ' ' . $data->firstname . ' ' . $data->lastname))
        ->with('reg_email', $data->email)
        ->with('email_sent', $result['email_sent']);
}

    
    //Get Speciality data by select Profession
    public function speciality(Request $request){
       $getSpecialityData = Speciality::where('profession_id', $request->profession_id)->get();
        if($getSpecialityData){
            return response()->json(['status' => 'success', 'data' => $getSpecialityData], 200);
        }
        return response()->json(['status' => 'failed', 'message' => 'No Specilaty found'], 404);
    }

    //Show/Fetch Data By Unique QR Code
    public function verify($qrcode){
        $fdata = DB::table('registrations')
                ->where('qr_code', $qrcode)
                ->first();
                
                //if data is not found
                if (!$fdata) {
                    abort(404);
                }
            return view('frontend/pages/verify', ['data'=>$fdata]);
    }

//Ajax Data Submit
    
    // public function ajaxstore(Request $request){
    //     $validator = Validator::make($request->all(), [
    //         'firstname' => 'required|max:191',
    //         'lastname' => 'required|max:191', 
    //     ]);

    //     if($validator->fails()){
    //         return response()->json([
    //             'status'=>400,
    //             'errors'=>$validator->message()
    //         ]);
    //     }else{
    //         $data = new Registration;

    //          //Qrcode/Random Number Generate
    //         $qr_code = random_int(100000, 999999);
    //           // $qr_code = date("Y").'-'.random_int(100000, 999999);
    //         $qrcodeUrl = 'https://bics.org/verify/'.$qr_code;
    //         $qrcodeImg = $qr_code.'.'.'svg';
    //         $qrImgPath = 'upload/qrcodeimg/'.$qrcodeImg;
    //         QrCode::generate($qrcodeUrl, $qrImgPath);  

    //         //Venu
    //         //$venue = 'Algeria';
    //         $data['venue'] = 'Algeria';
    //         $data['onbehalf'] = 'self';
    //         $data['country'] = $request->country;
    //         $data['firstname'] = $request->firstname;
    //         $data['lastname'] = $request->lastname;
    //         $data['birthday'] = $request->birthday;
    //         $data['gender'] = $request->gender;
    //         $data['email'] = $request->email;
    //         $data['phone'] = $request->phone;
    //         $data['profession'] = $request->profession;
    //         $data['specialty'] = $request->specialty;
    //         $data['pharmacist'] = $request->pharmacist;
    //         $data['designation'] = $request->designation;
    //         $data['institute'] = $request->institute;
    //         $data['institute_address'] = $request->institute_address;
    //         $data['institute_phone'] = $request->institute_phone;
    //         $data['identification_type'] = $request->identification_type;
    //         $data['identification_no'] = $request->identification_no;

    //         if($request->file('image')){
    //             $file = $request->file('image');

    //             $ImgName = $qr_code.'.'.$request->image->getClientOriginalName();
    //             $file->move('upload/images', $ImgName);
    //             if($request->image){
    //                 $data['image'] = $ImgName;
    //             }else{
    //                 $data['image'] = 'no-photo.png';
    //             }
    //             //$data['image'] = $ImgName;
    //         }
    //         $data['qr_code'] = $qr_code;
    //         $data['qr_url'] = $qrcodeUrl;
    //         $data['qr_img'] = $qrcodeImg;
    //         $data['qr_img_path'] = $qrImgPath;
    //         $data['status'] = 'approved';
    //         $filename = $data['lastname'].'-'.$qr_code.'.'.'pdf';
    //         $savePdf = 'upload/userprofile/'.$filename;
    //         $pdf = Pdf::loadView('userprofile.profile', compact('data'));
    //         $pdf->save($savePdf);
            
    //         $data->save();

    //         return response()->json([
    //                 'status'=> 200,
    //                 'success'=>'Registration Successfully Completed!',
    //         ]);
    //     }
    // }
}