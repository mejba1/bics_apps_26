<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Support\Certificate;
use Illuminate\Support\Facades\DB;

/**
 * Public certificate page (link in the email, QR code on the certificate): /certificate/{token}
 * shows that the certificate is genuine and lets the holder download it. The token is a long secret, so
 * certificates cannot be guessed or listed.
 */
class CertificatePublicController extends Controller
{
    public function show(string $token){
        [$cert, $r, $event] = $this->find($token);
        return view('frontend.pages.certificate', compact('cert', 'r', 'event'));
    }

    public function pdf(string $token){
        [$cert, $r, $event] = $this->find($token);
        DB::table('certificates')->where('id', $cert->id)->increment('downloads');
        return Certificate::pdf($event, $r, $cert)->download(Certificate::fileName($r, $cert));
    }

    private function find(string $token): array
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{40}$/', $token), 404);
        $cert = DB::table('certificates')->where('token', $token)->first();
        abort_unless($cert, 404);
        $r = DB::table('registrations')->where('id', $cert->registration_id)->first();
        $event = Event::find($cert->event_id);
        abort_unless($r && $event, 404);
        return [$cert, $r, $event];
    }
}
