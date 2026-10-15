<?php

namespace App\Http\Controllers;

use App\Support\Cms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Public contact form (Contact page). Every message is saved in "contact_messages" and emailed to the
 * addresses in Admin > Website Pages > Site-wide > Footer, with Reply-To set to the sender.
 */
class ContactController extends Controller
{
    public function store(Request $request){
        // Spam trap filled in: pretend it worked
        if (trim((string) $request->input('website')) !== '') {
            return response()->json(['success' => true, 'message' => 'Thank you! Your message has been sent.']);
        }

        $plain = \App\Support\FormGuard::plain();
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150', $plain],
            'email' => ['required', 'email', 'max:190', \App\Support\FormGuard::realEmail()],
            'country' => ['nullable', 'string', 'max:100', $plain],
            'phone' => ['nullable', 'max:40', 'regex:/^[0-9+()\s\-]{5,40}$/'],
            'subject' => ['required', 'string', 'max:120', $plain],
            'message' => ['required', 'string', 'min:10', 'max:3000', function ($a, $v, $fail) {
                if (\App\Support\FormGuard::linkCount($v) > 2) $fail('Please include no more than 2 links in your message.');
                elseif (preg_match('~<\s*(a|script|iframe|img)\b|\[url~i', (string) $v)) $fail('Please write your message as plain text (no HTML).');
            }],
        ], ['phone.regex' => 'Please enter a valid phone number.', 'message.min' => 'Please write a little more (at least 10 characters).']);

        $id = DB::table('contact_messages')->insertGetId($data + ['ip' => $request->ip(), 'created_at' => now(), 'updated_at' => now()]);

        $footer = \App\Models\Page::globalContent('footer') ?? \App\Support\PageSeed::footer();
        $to = array_values(array_filter(Cms::lines($footer['emails'] ?? ''), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
        if ($to) {
            try {
                $body = "New message from the BICS website contact form\n\n"
                    . "Name: {$data['name']}\nEmail: {$data['email']}\nCountry: " . ($data['country'] ?? '-') . "\nPhone: " . ($data['phone'] ?? '-')
                    . "\nTopic: {$data['subject']}\n\n{$data['message']}\n\n— Message #{$id}, " . now()->format('j M Y, H:i T');
                Mail::raw($body, function ($m) use ($to, $data) {
                    $m->to($to)->replyTo($data['email'], $data['name'])->subject('BICS website: ' . $data['subject'] . ' — ' . $data['name']);
                });
                DB::table('contact_messages')->where('id', $id)->update(['emailed_at' => now()]);
            } catch (\Throwable $e) {
                Log::warning('BICS contact email failed', ['id' => $id, 'error' => $e->getMessage()]);
            }
        }

        return response()->json(['success' => true, 'message' => 'Thank you, ' . $data['name'] . '! Your message has been sent. We will reply to ' . $data['email'] . '.']);
    }
}
