<?php

namespace App\Services;

use App\Models\FormOption;
use App\Models\Registration;
use App\Notifications\EmailNotification;
use App\Support\EventSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Registering someone for the current event. Used by the public form and by
 * Admin > Registrations > New registration, so both follow exactly the same rules.
 */
class RegistrationService
{
    /** Validation rules, messages and field names (drop-down values must come from Admin > Form Options). */
    public function validationRules(Request $request): array
    {
        $ready = FormOption::tableReady();
        $choice = fn (string $list) => $ready ? [Rule::in(FormOption::active($list)->pluck('label')->all())] : [];
        $professions = $ready ? FormOption::professions() : collect();
        $profession = $professions->first(fn ($p) => $p->label === $request->profession);
        $specialityRule = $profession && $profession->children->isNotEmpty() ? [Rule::in($profession->children->pluck('label')->all())] : [];

        $rules = [
            'country' => array_merge(['required', 'max:100'], $choice('country')),
            'title' => array_merge(['required', 'max:20'], $choice('title')),
            'firstname' => ['required', 'max:100', \App\Support\FormGuard::plain()],
            'lastname' => ['required', 'max:100', \App\Support\FormGuard::plain()],
            'birthday' => 'required|date|before:today|after:1900-01-01',
            'gender' => 'required|in:Male,Female',
            'email' => 'required|email|max:190',
            'countryCode' => 'nullable|max:10',
            'phone' => ['required', 'regex:/^[0-9\s\-]{5,20}$/'],
            'profession' => array_merge(['required', 'max:100'], $ready ? [Rule::in($professions->pluck('label')->all())] : []),
            'specialty' => array_merge(['nullable', 'max:100'], $specialityRule),
            'designation' => ['nullable', 'max:150', \App\Support\FormGuard::plain()],
            'institute' => ['required', 'max:190', \App\Support\FormGuard::plain()],
            'institute_address' => ['nullable', 'max:255', \App\Support\FormGuard::plain()],
            'identification_type' => array_merge(['nullable', 'max:50'], $choice('id_type')),
            'identification_no' => ['nullable', 'max:50', \App\Support\FormGuard::plain()],
            'image' => 'nullable|image|mimes:jpg,png,jpeg|max:1024',
        ];
        $messages = [
            'birthday.before' => 'Date of birth must be in the past.',
            'phone.regex' => 'Please enter a valid phone number (digits only).',
            'image.max' => 'The photo must be smaller than 1 MB.',
            'country.in' => 'Please choose your country from the list.',
            'title.in' => 'Please choose a title from the list.',
            'profession.in' => 'Please choose your profession from the list.',
            'specialty.in' => 'Please choose a speciality that belongs to your profession.',
            'identification_type.in' => 'Please choose an ID type from the list.',
        ];
        $attributes = ['firstname' => 'first name', 'lastname' => 'last name', 'birthday' => 'date of birth', 'institute' => 'institution name', 'image' => 'photo'];
        return [$rules, $messages, $attributes];
    }

    public function venue(): string
    {
        return EventSettings::get('venue_code', config('bics.venue'));
    }

    /** Is this email already registered for the current event? */
    public function isDuplicate(string $email): bool
    {
        return Registration::where('email', $email)->where('venue', $this->venue())->exists();
    }

    /**
     * Save a registration (with photo, verification card PDF, account and attendee rows) and
     * optionally email the confirmation.
     *
     * @param  array  $input   validated form values
     * @param  array  $options send_email (bool, default true), checked_in (bool, default false),
     *                         photo_copy ("upload/…" photo to reuse when no new photo is given)
     * @return array  registration, email_sent, message, card_path, card_url
     */
    public function register(array $input, ?UploadedFile $photo = null, array $options = []): array
    {
        $options += ['send_email' => true, 'checked_in' => false];
        $venue = $this->venue();
        $trackingNo = EventSettings::get('tracking_no', config('bics.tracking_no'));

        // Unique 6-digit registration number
        do {
            $code = random_int(100000, 999999);
        } while (Registration::where('qr_code', $code)->exists());

        // Photo, with a safe file name
        $imageName = null;
        if ($photo) {
            $base = Str::slug(pathinfo($photo->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'photo';
            $imageName = $code . '.' . Str::limit($base, 60, '') . '.' . strtolower($photo->getClientOriginalExtension());
            $photo->move(base_path('upload/images'), $imageName);
            // Keep photos small (the public form already crops to 600px; admin uploads may be camera photos)
            $file = base_path('upload/images/' . $imageName);
            $size = @getimagesize($file);
            if ($size && max($size[0], $size[1]) > 800) \App\Support\Thumb::shrink($file, $file, 800);
        } elseif (!empty($options['photo_copy']) && is_file(base_path($options['photo_copy']))) {
            // returning participant without a new photo: reuse the earlier one (a copy, so each registration owns its file)
            $ext = strtolower(pathinfo($options['photo_copy'], PATHINFO_EXTENSION)) ?: 'jpg';
            $imageName = $code . '.photo.' . $ext;
            @copy(base_path($options['photo_copy']), base_path('upload/images/' . $imageName));
            if (!is_file(base_path('upload/images/' . $imageName))) $imageName = null;
        }

        $qrUrl = 'https://bics.org/verify/' . $code;
        $qrImg = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . $qrUrl;
        // Dial code from Admin > Form Options > Countries (the browser's value is only a fallback)
        $dial = FormOption::tableReady() ? FormOption::active('country')->firstWhere('label', $input['country'] ?? '')?->extra : null;

        $data = new Registration;
        $data['event_id'] = optional(\App\Models\Event::current())->id;
        $data['venue'] = $venue;
        $data['title'] = $input['title'];
        $data['trackingNo'] = $trackingNo;
        $data['onbehalf'] = 'Self';
        $data['country'] = $input['country'];
        $data['firstname'] = trim($input['firstname']);
        $data['lastname'] = trim($input['lastname']);
        $data['birthday'] = $input['birthday'];
        $data['gender'] = $input['gender'];
        $data['email'] = strtolower(trim($input['email']));
        $data['phone'] = ($dial ?: ($input['countryCode'] ?? '')) . '-' . preg_replace('/\s+/', '', $input['phone']);
        $data['profession'] = $input['profession'];
        $data['specialty'] = $input['specialty'] ?? null;
        $data['designation'] = $input['designation'] ?? null;
        $data['institute'] = $input['institute'];
        $data['institute_address'] = $input['institute_address'] ?? null;
        $data['identification_type'] = $input['identification_type'] ?? null;
        $data['identification_no'] = $input['identification_no'] ?? null;
        $data['image'] = $imageName ?: 'no-photo.png';
        $data['qr_code'] = $code;
        $data['qr_url'] = $qrUrl;
        $data['qr_img_path'] = $qrImg;
        $data['status'] = 'approved';
        if ($options['checked_in']) {
            $data['verify_status'] = 'Yes';
            $data['verify_at'] = date('d-m-Y h:i:sa');
        }

        // Attendee ID card PDF (linked from the confirmation email)
        $cardPath = \App\Support\IdCard::make($data);

        // Registration, account and attendee rows are saved together (all or nothing)
        DB::transaction(function () use ($data, $venue, $trackingNo, $code, $qrUrl, $qrImg) {
            $data->save();
            DB::table('register_accounts')->insert(['register_id' => $data->id, 'email' => $data->email]);
            DB::table('attendees')->insert([
                'register_id' => $data->id, 'event_id' => $data->event_id, 'venue' => $venue, 'trackingNo' => $trackingNo, 'onbehalf' => 'Self',
                'qr_code' => $code, 'qr_url' => $qrUrl, 'qr_img_path' => $qrImg,
            ]);
        });

        // Permanent participant profile (same email = same person across events): /p/{code}
        \App\Models\Participant::forRegistration($data);

        // Checked in on the spot: certificate of participation (if the event sends them automatically)
        if ($options['checked_in']) \App\Support\Certificate::onCheckedIn($data->id);

        // Confirmation email
        $emailSent = false;
        if ($options['send_email']) {
            try {
                $data->notify(new EmailNotification([
                    'pin' => $data->qr_code, 'profession' => $data->profession, 'name' => $data->lastname,
                    'title' => $data->title, 'qrcode' => $cardPath,
                ]));
                $data->update(['email_notification' => 'Yes']);
                $emailSent = true;
            } catch (\Exception $e) {
                Log::error('BICS Registration Email Failed', ['registration_id' => $data->id, 'email' => $data->email, 'error' => $e->getMessage()]);
            }
        }

        $message = $emailSent
            ? 'A confirmation email has been sent to ' . $data->email . '.'
            : ($options['send_email']
                ? 'Your registration is complete, but the confirmation email could not be sent. Please save your registration number.'
                : 'Registration saved. No confirmation email was sent.');

        return [
            'registration' => $data,
            'email_sent' => $emailSent,
            'message' => $message,
            'card_path' => $cardPath,
            'card_url' => asset($cardPath),
        ];
    }
}
