<?php

namespace App\Support;

use App\Models\Participant;
use Illuminate\Validation\ValidationException;

/** Validation for participant profile fields — shared by the admin form and the participant's own dashboard. */
class ParticipantRules
{
    public static function rules(): array
    {
        $phone = ['nullable', 'max:40', 'regex:/^[0-9+()\s\-]{5,40}$/'];
        return [
            'title' => 'nullable|string|max:20', 'first_name' => ['required', 'string', 'max:150', \App\Support\FormGuard::plain()], 'last_name' => ['nullable', 'string', 'max:150', \App\Support\FormGuard::plain()],
            'designation' => ['nullable', 'string', 'max:150', \App\Support\FormGuard::plain()], 'organization' => ['nullable', 'string', 'max:190', \App\Support\FormGuard::plain()], 'department' => ['nullable', 'string', 'max:150', \App\Support\FormGuard::plain()],
            'category' => 'nullable|string|max:100', 'country' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date|after:1900-01-01|before:-10 years',
            'photo' => 'nullable|string|max:255', 'logo' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:190', 'mobile' => $phone, 'phone' => $phone, 'whatsapp' => $phone, 'address' => 'nullable|string|max:255',
            'map_url' => 'nullable|url|max:500', 'website' => 'nullable|url|max:255',
            'socials' => 'nullable|array|max:' . SocialLinks::MAX, 'socials.*' => 'array', 'socials.*.url' => 'nullable|string|max:500', 'socials.*.label' => 'nullable|string|max:60', 'socials.*.icon' => 'nullable|string|max:80', 'socials.*.color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'bio' => 'nullable|string|max:3000', 'expertise' => 'nullable|string|max:1000',
            'custom_fields' => 'nullable|array|max:15', 'custom_fields.*.label' => 'nullable|string|max:60', 'custom_fields.*.value' => 'nullable|string|max:255', 'custom_fields.*.public' => 'nullable|boolean',
            'visibility' => 'nullable|array', 'visibility.*' => 'boolean',
            'slug' => ['nullable', 'string', 'min:3', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ];
    }

    public static function messages(): array
    {
        return [
            'first_name.required' => 'Please enter the first name.',
            'birth_date.before' => 'Please check the date of birth.', 'birth_date.after' => 'Please check the date of birth.',
            'slug.regex' => 'Use lowercase letters, numbers and single dashes only (e.g. dr-nadia-karim).',
            'mobile.regex' => 'Use digits, spaces, + ( ) or - only.', 'phone.regex' => 'Use digits, spaces, + ( ) or - only.', 'whatsapp.regex' => 'Use digits (with country code), spaces, + or -.',
            'map_url.url' => 'Paste the full Google Maps link (https://…).', 'website.url' => 'Enter the full address, e.g. https://example.com', 'socials.max' => 'Up to ' . SocialLinks::MAX . ' links.', 'socials.*.label.max' => 'Keep link names under 60 characters.',
        ];
    }

    /**
     * Tidy validated values. $imageOk decides whether a photo / logo path may be used (only uploaded images).
     * Only keys present in $data are touched.
     */
    public static function normalize(array $data, ?Participant $p, callable $imageOk): array
    {
        foreach (['photo', 'logo'] as $k) {
            if (!array_key_exists($k, $data)) continue;
            $v = $data[$k];
            if ($v && !(preg_match('#^upload/(participants|images)/[A-Za-z0-9._\-]+\.(png|jpe?g|webp)$#i', $v) && is_file(base_path($v)) && $imageOk($v, $k))) {
                throw ValidationException::withMessages([$k => 'Please upload the image again.']);
            }
        }
        if (array_key_exists('slug', $data)) {
            $data['slug'] = $data['slug'] ? strtolower(trim($data['slug'])) : null;
            if ($data['slug'] && in_array($data['slug'], Participant::RESERVED_SLUGS, true)) throw ValidationException::withMessages(['slug' => 'This name is reserved. Please choose another.']);
            if ($data['slug'] && Participant::withTrashed()->where('slug', $data['slug'])->when($p, fn ($q) => $q->where('id', '!=', $p->id))->exists()) {
                throw ValidationException::withMessages(['slug' => 'Another participant already uses this link name.']);
            }
        }
        if (array_key_exists('email', $data)) {
            $data['email'] = $data['email'] ? strtolower(trim($data['email'])) : null;
            if ($data['email'] && Participant::withTrashed()->where('email', $data['email'])->when($p, fn ($q) => $q->where('id', '!=', $p->id))->exists()) {
                throw ValidationException::withMessages(['email' => 'Another participant already uses this email (it links their event registrations).']);
            }
        }
        if (array_key_exists('socials', $data)) {
            // every filled-in address must be a web address; empty rows are simply ignored
            foreach ((array) ($data['socials'] ?? []) as $i => $l) {
                $u = trim((string) ($l['url'] ?? ''));
                if ($u === '') continue;
                if (!SocialLinks::normalize([['url' => $u]])) throw ValidationException::withMessages(["socials.$i.url" => 'This is not a valid web address: ' . \Illuminate\Support\Str::limit($u, 60)]);
            }
            $data['socials'] = SocialLinks::normalize($data['socials'] ?? []);
        }
        if (array_key_exists('custom_fields', $data)) {
            $data['custom_fields'] = array_values(array_filter(array_map(fn ($f) => ['label' => trim($f['label'] ?? ''), 'value' => trim($f['value'] ?? ''), 'public' => !empty($f['public'])], $data['custom_fields'] ?? []),
                fn ($f) => $f['label'] !== '' || $f['value'] !== ''));
        }
        if (array_key_exists('visibility', $data)) $data['visibility'] = array_map('boolval', array_intersect_key($data['visibility'] ?? [], Participant::FIELDS));
        foreach ($data as $k => $v) if (is_string($v)) $data[$k] = trim($v) === '' ? null : trim($v);
        return $data;
    }
}
