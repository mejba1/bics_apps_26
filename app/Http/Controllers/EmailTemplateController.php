<?php

namespace App\Http\Controllers;

use App\Models\EmailAttachment;
use App\Notifications\EmailNotification;
use App\Support\EmailTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Admin > Email Template: texts, colours, banner and attachments of the registration confirmation email,
 * with a live preview and a test send (AJAX).
 */
class EmailTemplateController extends Controller
{
    private const SAMPLE = ['title' => 'Dr', 'first_name' => 'Ayesha', 'name' => 'Rahman', 'pin' => '484704', 'profession' => 'Medical Doctor'];

    public function edit(){
        return view('backend.pages.email-template', [
            'tpl' => EmailTemplate::all(),
            'defaults' => EmailTemplate::DEFAULTS,
            'placeholders' => EmailTemplate::PLACEHOLDERS,
            'attachments' => EmailAttachment::orderBy('sort_order')->orderBy('id')->get()->map(fn ($x) => EmailAttachmentController::present($x)),
        ]);
    }

    public function update(Request $request){
        $data = $this->validated($request);
        EmailTemplate::save($data);
        return response()->json(['success' => true, 'message' => 'Email template saved. New confirmation emails use it right away.']);
    }

    /** Preview: saved template (GET) or the unsaved form values (POST). */
    public function preview(Request $request){
        $tpl = $request->isMethod('post') ? $this->validated($request, false) : [];
        return response($this->render($tpl));
    }

    public function reset(){
        EmailTemplate::reset();
        return response()->json(['success' => true, 'message' => 'Original texts restored.', 'tpl' => EmailTemplate::all()]);
    }

    public function banner(Request $request){
        $request->validate(['banner' => 'required|image|mimes:jpg,jpeg,png,gif|max:3072'], [
            'banner.max' => 'The banner is too large (max 3 MB). Wide images about 1200 px across work best.',
            'banner.image' => 'Upload a JPG, PNG or GIF image.', 'banner.mimes' => 'Upload a JPG, PNG or GIF image.',
        ]);
        File::ensureDirectoryExists(base_path('upload/email'));
        $file = $request->file('banner');
        $name = 'banner-' . Str::lower(Str::random(8)) . '.' . strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $file->move(base_path('upload/email'), $name);

        // Remove the previous uploaded banner (never the original site images in assets/)
        $old = EmailTemplate::get('banner');
        if (Str::startsWith($old, 'upload/email/')) File::delete(base_path($old));

        EmailTemplate::save(['banner' => 'upload/email/' . $name, 'show_banner' => '1']);
        return response()->json(['success' => true, 'message' => 'Banner updated.', 'banner' => 'upload/email/' . $name, 'url' => asset('upload/email/' . $name)]);
    }

    /** Send the saved template (with attachments) to one address. */
    public function test(Request $request){
        $email = $request->validate(['email' => 'required|email|max:190'], ['email.email' => 'Enter a valid email address.'])['email'];
        try {
            Notification::route('mail', $email)->notify(new EmailNotification([
                'title' => self::SAMPLE['title'], 'first_name' => self::SAMPLE['first_name'], 'name' => self::SAMPLE['name'],
                'pin' => self::SAMPLE['pin'], 'profession' => self::SAMPLE['profession'], 'qrcode' => 'upload/userprofile/Sample-484704.pdf',
            ]));
        } catch (\Throwable $e) {
            Log::error('BICS test email failed', ['to' => $email, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'The email could not be sent: ' . Str::limit($e->getMessage(), 160)], 500);
        }
        return response()->json(['success' => true, 'message' => 'Test email sent to ' . $email . '.']);
    }

    public function render(array $tpl = []): string
    {
        return view('mail.notificationview', [
            'title' => self::SAMPLE['title'], 'firstName' => self::SAMPLE['first_name'], 'name' => self::SAMPLE['name'],
            'pin' => self::SAMPLE['pin'], 'profession' => self::SAMPLE['profession'],
            'qrcode' => 'upload/userprofile/Sample-484704.pdf',
            'attachments' => EmailAttachment::active()->pluck('title')->all(),
            'tpl' => $tpl,
        ])->render();
    }

    private function validated(Request $request, bool $strict = true): array
    {
        $rules = [
            'subject' => ($strict ? 'required' : 'nullable') . '|max:200',
            'preheader' => 'nullable|max:255',
            'badge' => 'nullable|max:80',
            'heading' => 'nullable|max:150',
            'intro' => 'nullable|max:2000',
            'pin_label' => 'nullable|max:80',
            'pin_note' => 'nullable|max:255',
            'button_text' => 'nullable|max:80',
            'steps_title' => 'nullable|max:120',
            'steps' => 'nullable|max:3000',
            'closing' => 'nullable|max:2000',
            'footer_title' => 'nullable|max:150',
            'footer_note' => 'nullable|max:200',
            'bottom_note' => 'nullable|max:255',
            'brand_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
        $data = $strict
            ? $request->validate($rules, ['subject.required' => 'The email needs a subject.', 'brand_color.regex' => 'Pick a colour.', 'accent_color.regex' => 'Pick a colour.'])
            : array_intersect_key($request->only(array_keys($rules)), $rules);
        foreach ($data as $k => $v) $data[$k] = (string) ($v ?? '');
        foreach (EmailTemplate::TOGGLES as $k) $data[$k] = $request->boolean($k) ? '1' : '0';
        if (!$strict) {
            foreach (['brand_color', 'accent_color'] as $k) {
                if (!preg_match('/^#[0-9a-fA-F]{6}$/', $data[$k] ?? '')) unset($data[$k]);
            }
        }
        return $data;
    }
}
