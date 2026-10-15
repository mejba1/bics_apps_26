<?php

namespace App\Http\Controllers;

use App\Support\MailSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Admin > System > Email server (SMTP): save the mail server, test it before switching on.
 */
class SystemMailController extends Controller
{
    /** Common providers, to fill host / port / encryption in one click. */
    public const PRESETS = [
        'gmail' => ['Gmail / Google Workspace', 'smtp.gmail.com', 587, 'tls', 'Use an App Password (Google Account → Security → App passwords), not the normal password.'],
        'outlook' => ['Outlook / Microsoft 365', 'smtp.office365.com', 587, 'tls', 'SMTP AUTH must be enabled for the mailbox.'],
        'cpanel' => ['cPanel / hosting mailbox', 'mail.yourdomain.com', 465, 'ssl', 'Use the full email address as username and the mailbox password.'],
        'zoho' => ['Zoho Mail', 'smtp.zoho.com', 465, 'ssl', 'Use an application-specific password if 2FA is on.'],
        'sendgrid' => ['SendGrid', 'smtp.sendgrid.net', 587, 'tls', 'Username is "apikey", password is your API key.'],
        'brevo' => ['Brevo (Sendinblue)', 'smtp-relay.brevo.com', 587, 'tls', 'Use the SMTP key from Brevo, not your login password.'],
    ];

    public function edit(){
        $s = MailSettings::all();
        return view('backend.pages.system-mail', [
            's' => $s,
            'hasPassword' => $s['password'] !== '',
            'effective' => MailSettings::effective(),
            'presets' => self::PRESETS,
        ]);
    }

    public function update(Request $request){
        $data = $this->validated($request);
        // Switching on needs a working-looking configuration
        if ($request->boolean('enabled') && !$data['host']) {
            return response()->json(['message' => 'Enter the SMTP host first.', 'errors' => ['host' => ['Enter the SMTP host first.']]], 422);
        }
        MailSettings::save([
            'enabled' => $request->boolean('enabled') ? '1' : '0',
            'host' => $data['host'] ?? '', 'port' => (string) ($data['port'] ?? ''), 'encryption' => $data['encryption'] ?? '',
            'username' => $data['username'] ?? '', 'from_address' => $data['from_address'] ?? '', 'from_name' => $data['from_name'] ?? '',
            'reply_to' => $data['reply_to'] ?? '',
            // Empty password field = keep the saved one; "clear_password" removes it
            'password' => $request->boolean('clear_password') ? '' : ($request->filled('password') ? $request->input('password') : null),
        ]);
        MailSettings::apply();
        return response()->json([
            'success' => true,
            'message' => $request->boolean('enabled') ? 'Saved. All emails now go through ' . $data['host'] . '.' : 'Saved. Emails use the server settings from the .env file.',
            'effective' => MailSettings::effective(),
            'hasPassword' => MailSettings::all()['password'] !== '',
        ]);
    }

    /** Send a test email with the values in the form (saved or not), and report the server's answer. */
    public function test(Request $request){
        $data = $this->validated($request);
        $request->validate(['to' => 'required|email|max:190'], ['to.required' => 'Enter where to send the test email.']);
        if (!$data['host']) return response()->json(['message' => 'Enter the SMTP host first.'], 422);

        $password = $request->filled('password') ? $request->input('password') : MailSettings::password();
        $config = MailSettings::smtpConfig($data + ['encryption' => ''], $password);
        $from = $data['from_address'] ?: ($data['username'] && filter_var($data['username'], FILTER_VALIDATE_EMAIL) ? $data['username'] : config('mail.from.address'));
        $fromName = $data['from_name'] ?: config('mail.from.name');

        config(['mail.mailers.bics_test' => $config]);
        app('mail.manager')->purge('bics_test');
        $started = microtime(true);
        try {
            Mail::mailer('bics_test')->raw(
                "This is a test email from the BICS admin panel.\n\nServer: {$config['host']}:{$config['port']} ({$this->encLabel($config['encryption'])})\nSent: " . now()->format('j M Y, H:i:s T') . "\nBy: " . optional($request->user())->name . "\n\nIf you can read this, the email settings work.",
                function ($m) use ($request, $from, $fromName) {
                    $m->to($request->input('to'))->from($from, $fromName)->subject('BICS test email · ' . now()->format('H:i:s'));
                }
            );
        } catch (\Throwable $e) {
            Log::warning('BICS SMTP test failed', ['host' => $config['host'], 'error' => $e->getMessage()]);
            return response()->json(['message' => $this->explain($e->getMessage()), 'detail' => Str::limit($e->getMessage(), 400)], 500);
        } finally {
            app('mail.manager')->purge('bics_test');
        }
        return response()->json(['success' => true, 'message' => 'Test email sent to ' . $request->input('to') . ' in ' . round(microtime(true) - $started, 1) . ' s. Check the inbox (and spam folder).']);
    }

    private function validated(Request $request): array
    {
        $d = $request->validate([
            'host' => 'nullable|string|max:190|regex:/^[A-Za-z0-9.\-]+$/',
            'port' => 'nullable|integer|min:1|max:65535',
            'encryption' => 'nullable|in:tls,ssl,none',
            'username' => 'nullable|string|max:190',
            'password' => 'nullable|string|max:255',
            'from_address' => 'nullable|email|max:190',
            'from_name' => 'nullable|string|max:120',
            'reply_to' => 'nullable|email|max:190',
        ], [
            'host.regex' => 'Enter only the server name, e.g. smtp.gmail.com (no http:// or spaces).',
            'from_address.email' => 'Enter a valid sender address.', 'reply_to.email' => 'Enter a valid reply-to address.',
        ]);
        foreach (['host', 'port', 'encryption', 'username', 'from_address', 'from_name', 'reply_to'] as $k) $d[$k] = trim((string) ($d[$k] ?? ''));
        if ($d['encryption'] === 'none') $d['encryption'] = '';
        return $d;
    }

    private function encLabel(?string $enc): string
    {
        return $enc ? strtoupper($enc) : 'no encryption';
    }

    /** Plain-language hints for the usual SMTP errors. */
    private function explain(string $error): string
    {
        $e = strtolower($error);
        return match (true) {
            str_contains($e, '535') || str_contains($e, 'authentication') || str_contains($e, 'username and password not accepted')
                => 'The server rejected the username or password. For Gmail use an App Password; for other providers check the SMTP password.',
            str_contains($e, 'connection could not be established') || str_contains($e, 'timed out') || str_contains($e, 'connection refused')
                => 'Could not reach the server. Check host and port, and that the hosting provider allows outgoing SMTP on this port.',
            str_contains($e, 'ssl') || str_contains($e, 'tls') || str_contains($e, 'crypto')
                => 'Encryption did not match the port. Usually port 587 uses TLS and port 465 uses SSL.',
            str_contains($e, 'sender') || str_contains($e, '553') || str_contains($e, '550')
                => 'The server refused the sender address. Use a "From" address that belongs to this mailbox or domain.',
            default => 'The test email could not be sent.',
        };
    }
}
