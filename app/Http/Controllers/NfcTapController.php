<?php

namespace App\Http\Controllers;

use App\Models\NfcCard;

/**
 * What an NFC card opens: /n/{token}. An active card goes to its participant's profile (?s=nfc counts as an NFC visit).
 * Any other card shows a neutral page without the owner's details (a lost card reveals nothing).
 * Staff who manage NFC cards get a link to the card in the admin panel (handy while encoding / verifying).
 */
class NfcTapController extends Controller
{
    public function tap(string $token){
        $card = NfcCard::where('token', $token)->with('participant')->first();
        abort_unless($card, 404);
        $p = $card->participant;
        if ($card->status === 'active' && $p && $p->status === 'active' && !$p->trashed()) {
            return redirect()->to(url('/p/' . $p->code) . '?s=nfc');
        }
        $staff = auth()->check() && auth()->user()->can('nfc.manage');
        return response()->view('profiles.card-inactive', ['card' => $card, 'staff' => $staff], in_array($card->status, ['lost', 'damaged', 'deactivated'], true) ? 410 : 200);
    }
}
