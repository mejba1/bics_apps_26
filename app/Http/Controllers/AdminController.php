<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Registration;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class AdminController extends Controller
{
    public function __construct()
    {
        // registrations of an ended / archived event cannot be deleted
        $this->middleware(function ($request, $next) {
            \App\Support\EventLock::ensureCurrent(\App\Support\EventLock::forRegistration((int) $request->route('id')), 'registration');
            return $next($request);
        })->only(['destroy']);
    }

    // PDF: all registrations (every event)
    public function createPdf(Request $request){
        return \App\Support\RegistrationReport::download([]);
    }
    public function profilePdf(Request $request, $id){
        // Attendee record PDF (photo, details, QR code)
        $data = DB::table('registrations')->where('id', $id)->first();
        abort_unless($data, 404);
        $pdf = Pdf::loadView('backend.pages.report.profile', ['data' => $data])->setPaper('a4', 'portrait')->setOption('isFontSubsettingEnabled', true);
        $name = 'BICS-' . $data->qr_code . '-' . (\Illuminate\Support\Str::slug($data->firstname . ' ' . $data->lastname) ?: 'attendee') . '.pdf';
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        $event = $data->event_id ? \App\Models\Event::find($data->event_id) : null;
        \App\Support\PdfFooter::apply($dompdf, \App\Support\PdfFooter::label($data->venue, $event), 'Confidential attendee record');
        return response($dompdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="' . $name . '"']);
    }
    
//   //Delele register user info by ajax
//     public function destroy($id){
//         Registration::find($id)->delete($id);
//         return response()->json([
//             'success' => 'Record deleted successfully!'
//         ]);
//     }

    // PDF: one country (every event)
    public function countryWiseReport(Request $request, $country){
        return \App\Support\RegistrationReport::download(['country' => $country]);
    }
    
    //Delele register user all info by ajax
    public function destroy($id){
       
        $data = Registration::findOrFail($id);

        $files = [
            base_path('upload/userprofile/'.$data->lastname.'-'.$data->qr_code.'.pdf'),
        ];
        // The shared "no photo" placeholder must never be removed
        if ($data->image && $data->image !== 'no-photo.png') {
            $files[] = base_path('upload/images/'.$data->image);
        }
        if ($data->qr_img) {
            $files[] = base_path('upload/qrcodeimg/'.$data->qr_img);
        }
        foreach ($files as $file) {
            if (File::isFile($file)) {
                File::delete($file);
            }
        }
        $data->delete();
        
        return response()->json([
            'success' => 'Record deleted successfully!'
        ]);
    }
    
    //Rafele Draw Pdf
    public function refelDrawPdf(Request $request){
        $today = date("Y-m-d_h:i:sa");
        $data = DB::table('registrations')->get();
        $pdf = Pdf::loadView('backend.pages.report.rafeldraw', ['data'=>$data])
        ->setPaper('a4', 'landscape');
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        \App\Support\PdfFooter::apply($dompdf, \App\Support\PdfFooter::label(), 'Confidential — contains personal data');
        return response($dompdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="' . $today . '.pdf"']);
    }
    
    //Cerate User
    public function createUser(){
        return view('backend.pages.create-user');
    }
    public function addUserinfo(Request $request) {
        $request->validate([
            'name' => 'required|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|min:8',
            'role' => 'required|in:admin,user',
        ]);

        $data = new User;
        $data['name'] = $request->name;
        $data['email'] = $request->email;
        $data['password'] = bcrypt($request->password);
        $data['role'] = $request->role;
        $data->save();
        return response()->json(['success'=>true, $data]);
    }
}
