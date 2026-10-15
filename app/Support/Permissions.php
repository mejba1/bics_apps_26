<?php

namespace App\Support;

/**
 * Everything a role can be allowed to do in the admin panel (Admin > Roles & Permissions).
 * Checked with Laravel's Gate: @can('website.pages'), ->middleware('can:website.pages'), $user->can(...).
 */
class Permissions
{
    public static function groups(): array
    {
        return [
            'Registrations' => [
                'registrations.view' => ['View dashboard', 'Dashboard numbers, charts and venue summaries'],
                'registrations.list' => ['See the full attendee list', 'Names, emails and phone numbers of everyone registered'],
                'registrations.create' => ['Register attendees', 'Add registrations from the admin panel (e.g. on-site at the venue)'],
                'registrations.export' => ['Download reports & cards', 'PDF reports and attendee verification cards'],
                'idcards.assign' => ['Assign ID cards', 'Give attendees their ID card on the participant dashboard, per event'],
                'registrations.delete' => ['Delete registrations', 'Permanently remove a registration and its files'],
                'registrations.resend' => ['Resend confirmation emails', 'Email everyone who has not received a confirmation'],
            ],
            'Attendee activities' => [
                'attendees.verify' => ['Verify attendees', 'Check people in with their registration number'],
                'attendees.gifts' => ['Distribute gifts', 'Mark gifts as handed out'],
            ],
            'Raffle draw' => [
                'raffle.prizes' => ['Raffle setup & winners', 'Prizes, draw order, who can win, prize winners. Running the draw needs a host assignment (Super Admin, in Raffle setup).'],
            ],
            'Reports' => [
                'reports.analytics' => ['Participant analytics', 'Profile visits, QR / NFC use, top profiles, counts by event, organization, category and country'],
            ],
            'Participants' => [
                'participants.manage' => ['Participant profiles', 'Create and edit participant profiles, privacy settings, photos and QR codes'],
                'participants.delete' => ['Delete participant profiles', 'Move profiles to the trash and restore them'],
                'nfc.manage' => ['NFC cards', 'Card stock, assigning, encoding, verifying, activating, replacing and deactivating NFC cards'],
                'cards.design' => ['Card Design Studio', 'Design NFC / ID card templates (front and back), choose which template each event and category uses'],
                'cards.print' => ['Card printing & export', 'Check participants and make card print files (PDF, CSV list, ZIP), mark batches printed, reprint'],
            ],
            'Engagement' => [
                'birthdays.manage' => ['Birthday wishes', 'Switch automatic birthday cards on or off, designs, messages, and send custom cards'],
            ],
            'Certificates' => [
                'certificates.manage' => ['Certificates of participation', 'Design certificates per event, download them and email them to participants'],
            ],
            'Registration setup' => [
                'event.settings' => ['Events & registration settings', 'Create and edit events, choose the current event, registration open/close, email template'],
                'form.options' => ['Form options', 'Drop-down values: titles, professions, countries, ID types'],
            ],
            'Website' => [
                'website.pages' => ['Website pages', 'Edit page content, sections, footer and messages'],
                'website.menu' => ['Website menu', 'Top navigation menu'],
                'website.gallery' => ['Gallery', 'Albums and photos'],
            ],
            'Administration' => [
                'users.manage' => ['Manage users', 'Create users, change their role, reset passwords'],
                'roles.manage' => ['Manage roles & permissions', 'Create roles and choose what each can do'],
            ],
            'System' => [
                'system.mail' => ['Email server (SMTP)', 'Mail server, sender address and test emails'],
                'system.backup' => ['Database backup & export', 'Create, download and export backups (restoring/importing is Super Admin only)'],
                'system.settings' => ['Platform settings', 'Participant ID prefix and how long logs and visit counts are kept'],
                'audit.view' => ['Audit log', 'See who changed what in the admin area, and admin sign-ins'],
            ],
        ];
    }

    /** ['registrations.view' => 'View dashboard & registrations', ...] */
    public static function all(): array
    {
        $out = [];
        foreach (static::groups() as $items) foreach ($items as $key => $info) $out[$key] = $info[0];
        return $out;
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, static::all());
    }

    /** What the old "user" accounts could do (used for their new role and as a fallback). */
    public static function staffDefaults(): array
    {
        return ['registrations.view', 'attendees.verify', 'attendees.gifts', 'raffle.prizes'];
    }

    /** First admin page a user may open (where to send people after login). */
    public static function homeFor($user): string
    {
        $pages = [
            'registrations.view' => '/home', 'registrations.create' => '/registrations/new', 'attendees.verify' => '/verify-user', 'attendees.gifts' => '/gift-distribute',
            'raffle.prizes' => '/add-prize', 'event.settings' => '/events', 'form.options' => '/form-options',
            'website.pages' => '/cms/pages', 'website.gallery' => '/gallery-manager', 'website.menu' => '/menu',
            'participants.manage' => '/participants', 'nfc.manage' => '/nfc-cards', 'cards.design' => '/card-studio', 'cards.print' => '/card-print', 'idcards.assign' => '/id-cards', 'birthdays.manage' => '/birthdays', 'certificates.manage' => '/certificates', 'users.manage' => '/users', 'roles.manage' => '/roles', 'reports.analytics' => '/analytics', 'system.mail' => '/system/mail', 'audit.view' => '/system/audit', 'system.settings' => '/system/settings', 'system.backup' => '/system/backups',
        ];
        foreach ($pages as $perm => $url) if ($user->hasPermission($perm)) return $url;
        return '/home';
    }
}
