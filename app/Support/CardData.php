<?php

namespace App\Support;

use App\Models\Event;
use App\Models\NfcCard;
use App\Models\Participant;

/**
 * What a card template needs for one participant: placeholder values, photo / logo addresses and QR contents.
 * Used by the studio preview now and by the print export (phase 4).
 * Note: the physical card is the participant's own, so contact details are taken as entered (not the public/private
 * switches of the online profile) — only put contact placeholders on a design when that is intended.
 */
class CardData
{
    public static function for(Participant $p, ?Event $event = null, bool $forPrint = false): array
    {
        $card = NfcCard::where('participant_id', $p->id)->whereIn('status', NfcCard::IN_USE)->first();
        $event = $event ?? $p->events()->first();
        $img = fn ($path) => $path && is_file(base_path($path)) ? ($forPrint ? base_path($path) : (preg_match('/\.(jpe?g|png|webp)$/i', $path) ? Thumb::url($path, 800) : asset($path))) : null;
        return [
            'id' => $p->id,
            'values' => [
                'name' => $p->name, 'first_name' => (string) $p->first_name, 'last_name' => (string) $p->last_name, 'title' => (string) $p->title,
                'designation' => (string) $p->designation, 'organization' => (string) $p->organization, 'department' => (string) $p->department,
                'category' => (string) $p->category, 'country' => (string) $p->country, 'participant_id' => $p->code, 'email' => (string) $p->email,
                'mobile' => (string) $p->mobile, 'phone' => (string) $p->phone, 'website' => preg_replace('#^https?://(www\.)?#', '', rtrim((string) $p->website, '/')),
                'event_name' => (string) ($event->event_name ?? ''), 'event_short' => (string) ($event->event_short ?? ''), 'event_dates' => $event ? $event->datesLabel() : '',
                'card_no' => (string) ($card->card_no ?? ''), 'profile_url' => preg_replace('#^https?://#', '', $p->shareUrl()),
            ],
            'photo' => $img($p->photo),
            'logo' => $img($p->logo),
            // full-size originals for print-quality exports made in the browser (PNG / JPG / EPS)
            'print' => $forPrint ? null : ['photo' => $p->photo && is_file(base_path($p->photo)) ? asset($p->photo) : null, 'logo' => $p->logo && is_file(base_path($p->logo)) ? asset($p->logo) : null],
            'initials' => $p->initials,
            'qr' => ['profile' => $p->url('qr'), 'nfc' => $card ? $card->url() : $p->url('nfc')],
        ];
    }

    /** A made-up participant for designing without real data. */
    public static function sample(): array
    {
        return [
            'id' => 0,
            'values' => [
                'name' => 'Dr Sarah Rahman', 'first_name' => 'Sarah', 'last_name' => 'Rahman', 'title' => 'Dr', 'designation' => 'Consultant Oncologist',
                'organization' => 'Dhaka Cancer Hospital', 'department' => 'Medical Oncology', 'category' => 'Speaker', 'country' => 'Bangladesh',
                'participant_id' => 'MBR-000123', 'email' => 'sarah.rahman@example.com', 'mobile' => '+880 1711 000000', 'phone' => '+880 2 0000000',
                'website' => 'example.com', 'event_name' => (string) EventSettings::get('event_name'), 'event_short' => (string) EventSettings::get('event_short'),
                'event_dates' => EventSettings::datesLabel(), 'card_no' => 'NFC-000123', 'profile_url' => preg_replace('#^https?://#', '', PublicLinks::base()) . '/p/MBR-000123',
            ],
            'photo' => null, 'logo' => null, 'initials' => 'SR',
            'qr' => ['profile' => PublicLinks::base() . '/p/MBR-000123?s=qr', 'nfc' => PublicLinks::base() . '/n/sample00000'],
        ];
    }
}
