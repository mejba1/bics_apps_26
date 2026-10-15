<?php

namespace App\Models;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\EpsImageBackEnd;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * A participant's permanent digital profile (docs/NFC-PROFILE-PLAN.md).
 * The code (e.g. MBR-000001) and the URL /p/{code} never change, so printed QR codes and NFC cards keep working
 * whatever is edited. Event registrations point here (registrations.participant_id).
 */
class Participant extends Model implements AuthenticatableContract
{
    use SoftDeletes, Authenticatable;

    /** Participants sign in with emailed links / codes: no password and no "remember me" token column. */
    public function getRememberTokenName()
    {
        return '';
    }

    /** Fields shown on the public profile, with their default visibility. Name / photo are always public. */
    public const FIELDS = [
        'designation' => ['Designation / title', true], 'organization' => ['Organization', true], 'department' => ['Department / speciality', true],
        'category' => ['Category', true], 'country' => ['Country', true], 'email' => ['Email', false], 'mobile' => ['Mobile', false],
        'phone' => ['Telephone', false], 'whatsapp' => ['WhatsApp', false], 'address' => ['Office address', true], 'map_url' => ['Map location', true],
        'website' => ['Website', true], 'socials' => ['Social media', true], 'bio' => ['Biography', true], 'expertise' => ['Areas of expertise', true],
        'logo' => ['Organization logo', true], 'events' => ['Events attended', true],
    ];

    /** Never sent to the browser or exports. */
    protected $hidden = ['password'];

    protected $fillable = [
        'title', 'first_name', 'last_name', 'designation', 'organization', 'department', 'category', 'country', 'birth_date', 'photo', 'logo',
        'email', 'mobile', 'phone', 'whatsapp', 'address', 'map_url', 'website', 'socials', 'bio', 'expertise', 'custom_fields',
        'visibility', 'editing_enabled', 'locked_fields', 'status', 'slug',
    ];

    protected $casts = [
        'socials' => 'array', 'custom_fields' => 'array', 'visibility' => 'array', 'locked_fields' => 'array', 'editing_enabled' => 'boolean',
        'last_login_at' => 'datetime', 'password_changed_at' => 'datetime', 'birth_date' => 'date',
    ];

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    /* ---------------- identity ---------------- */

    public static function prefix(): string
    {
        $p = DB::table('settings')->where('key', 'participants.prefix')->value('value');
        return preg_match('/^[A-Z]{2,10}$/', (string) $p) ? $p : 'MBR';
    }

    /** Next free code (MBR-000301). The sequence is shared, so codes never repeat even after deletions. */
    public static function nextIdentity(): array
    {
        $seq = (int) static::withTrashed()->lockForUpdate()->max('sequence') + 1;
        return [$seq, static::prefix() . '-' . str_pad((string) $seq, 6, '0', STR_PAD_LEFT)];
    }

    public static function createWithCode(array $attributes, ?int $userId = null): Participant
    {
        return DB::transaction(function () use ($attributes, $userId) {
            [$seq, $code] = static::nextIdentity();
            $p = new static($attributes);
            $p->code = $code;
            $p->sequence = $seq;
            $p->status = $attributes['status'] ?? 'active';
            $p->created_by = $userId;
            $p->save();
            return $p;
        });
    }

    /** The participant of a registration: same valid email = same person; otherwise a new profile. */
    public static function forRegistration(object $r): ?Participant
    {
        try {
            $email = strtolower(trim((string) $r->email));
            $p = filter_var($email, FILTER_VALIDATE_EMAIL) ? static::where('email', $email)->first() : null;
            if (!$p) {
                $cut = fn ($v, $n) => ($v = mb_substr(trim((string) $v), 0, $n)) !== '' ? $v : null;
                $spam = (bool) preg_match('#https?://|<a\s|www\.#i', ($r->firstname ?? '') . ' ' . ($r->lastname ?? '') . ' ' . ($r->institute ?? ''));
                $p = static::createWithCode([
                    'title' => $cut($r->title ?? '', 20), 'first_name' => $cut($r->firstname ?? '', 150) ?? 'Participant', 'last_name' => $cut($r->lastname ?? '', 150),
                    'designation' => $cut(($r->designation ?? null) ?: ($r->profession ?? ''), 150), 'organization' => $cut($r->institute ?? '', 190),
                    'department' => $cut($r->specialty ?? '', 150), 'category' => $cut($r->profession ?? '', 100), 'country' => $cut($r->country ?? '', 100),
                    'photo' => !empty($r->image) && $r->image !== 'no-photo.png' && is_file(base_path('upload/images/' . $r->image)) ? 'upload/images/' . $r->image : null,
                    'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $cut($email, 190) : null, 'mobile' => $cut(trim((string) ($r->phone ?? ''), ' -'), 40),
                    'status' => $spam ? 'suspended' : 'active',
                ]);
            }
            DB::table('registrations')->where('id', $r->id)->update(['participant_id' => $p->id]);
            return $p;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('BICS participant link failed', ['registration_id' => $r->id ?? null, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function getNameAttribute(): string
    {
        return trim(preg_replace('/\s+/', ' ', ($this->title ? $this->title . ' ' : '') . $this->first_name . ' ' . $this->last_name));
    }

    public function getInitialsAttribute(): string
    {
        return mb_strtoupper(mb_substr((string) $this->first_name, 0, 1) . mb_substr((string) $this->last_name, 0, 1));
    }

    /**
     * Permanent public URL (used by QR codes and NFC chips). $source (qr / nfc) only marks where a visit came from.
     * The address comes from Link settings: automatic (this site's own address) or a custom one.
     */
    public function url(?string $source = null): string
    {
        return \App\Support\PublicLinks::base() . '/p/' . $this->code . ($source ? '?s=' . $source : '');
    }

    /** The link to share: the custom link name if set (e.g. /p/dr-nadia-karim), otherwise the permanent URL. */
    public function shareUrl(): string
    {
        return $this->slug ? \App\Support\PublicLinks::base() . '/p/' . $this->slug : $this->url();
    }

    /** Words a custom link name may not be (they could clash with site addresses). */
    public const RESERVED_SLUGS = ['admin', 'login', 'logout', 'register', 'registration', 'profile', 'profiles', 'p', 'n', 'qr', 'vcard', 'api', 'home', 'event', 'events', 'bics'];

    /* ---------------- privacy ---------------- */

    public function isPublic(string $field): bool
    {
        $v = $this->visibility ?? [];
        return array_key_exists($field, $v) ? (bool) $v[$field] : (static::FIELDS[$field][1] ?? false);
    }

    /** A field's value if it is filled in and public, else null. */
    public function shown(string $field)
    {
        if (!$this->isPublic($field)) return null;
        $value = $this->getAttribute($field);
        return is_string($value) ? (trim($value) !== '' ? trim($value) : null) : ($value ?: null);
    }

    /** All social / web links, in order: [{type, url, label}]. */
    public function socialLinks(): array
    {
        return \App\Support\SocialLinks::normalize($this->socials ?? []);
    }

    /** Social links for the public profile (none when the participant made "Social media" private). */
    public function publicSocials(): array
    {
        return $this->isPublic('socials') ? $this->socialLinks() : [];
    }

    public function publicCustomFields(): array
    {
        return array_values(array_filter($this->custom_fields ?? [], fn ($f) => !empty($f['public']) && trim(($f['label'] ?? '') . ($f['value'] ?? '')) !== ''));
    }

    /** Events (registrations) of this participant, newest first. */
    public function events()
    {
        return Event::whereIn('id', $this->registrations()->whereNotNull('event_id')->pluck('event_id'))
            ->orWhereIn('venue_code', $this->registrations()->whereNull('event_id')->pluck('venue'))
            ->orderByDesc('event_start')->get();
    }

    /* ---------------- QR code ---------------- */

    /** Vector QR (SVG) of the QR URL. */
    public function qrSvg(int $size = 400): string
    {
        return (new Writer(new ImageRenderer(new RendererStyle($size, 2), new SvgImageBackEnd())))->writeString($this->url('qr'), 'UTF-8', ErrorCorrectionLevel::M());
    }

    /** Vector QR (EPS) for professional printing. */
    public function qrEps(int $size = 400): string
    {
        return (new Writer(new ImageRenderer(new RendererStyle($size, 2), new EpsImageBackEnd())))->writeString($this->url('qr'), 'UTF-8', ErrorCorrectionLevel::M());
    }

    /** PNG drawn with GD (no Imagick needed): exact modules, sharp edges at any size. */
    public function qrPng(int $size = 1000): string
    {
        $matrix = Encoder::encode($this->url('qr'), ErrorCorrectionLevel::M(), 'UTF-8')->getMatrix();
        $modules = $matrix->getWidth();
        $quiet = 4;
        $scale = max(1, intdiv($size, $modules + 2 * $quiet));
        $px = ($modules + 2 * $quiet) * $scale;
        $img = imagecreatetruecolor($px, $px);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefill($img, 0, 0, $white);
        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    imagefilledrectangle($img, ($x + $quiet) * $scale, ($y + $quiet) * $scale, ($x + $quiet + 1) * $scale - 1, ($y + $quiet + 1) * $scale - 1, $black);
                }
            }
        }
        ob_start();
        imagepng($img);
        imagedestroy($img);
        return (string) ob_get_clean();
    }

    /* ---------------- vCard ---------------- */

    public function vcard(): string
    {
        $esc = fn ($v) => str_replace(["\\", "\n", ",", ";"], ["\\\\", "\\n", "\\,", "\\;"], (string) $v);
        $lines = ['BEGIN:VCARD', 'VERSION:3.0',
            'N:' . $esc($this->last_name) . ';' . $esc($this->first_name) . ';;' . $esc($this->title) . ';',
            'FN:' . $esc($this->name)];
        if ($v = $this->shown('organization')) $lines[] = 'ORG:' . $esc($v) . ($this->shown('department') ? ';' . $esc($this->shown('department')) : '');
        if ($v = $this->shown('designation')) $lines[] = 'TITLE:' . $esc($v);
        if ($v = $this->shown('email')) $lines[] = 'EMAIL;TYPE=INTERNET,WORK:' . $esc($v);
        if ($v = $this->shown('mobile')) $lines[] = 'TEL;TYPE=CELL:' . $esc($v);
        if ($v = $this->shown('phone')) $lines[] = 'TEL;TYPE=WORK,VOICE:' . $esc($v);
        if ($v = $this->shown('address')) $lines[] = 'ADR;TYPE=WORK:;;' . $esc($v) . ';;;;' . $esc($this->shown('country'));
        if ($v = $this->shown('website')) $lines[] = 'URL:' . $esc($v);
        $lines[] = 'URL;TYPE=profile:' . $esc($this->shareUrl());
        foreach ($this->publicSocials() as $l) $lines[] = 'X-SOCIALPROFILE;TYPE=' . $l['type'] . ':' . $esc($l['url']);
        if ($this->photo && preg_match('/\.(jpe?g|png)$/i', $this->photo) && is_file(base_path($this->photo))) {
            $data = @file_get_contents(base_path($this->photo));
            if ($data && strlen($data) < 400000) $lines[] = 'PHOTO;ENCODING=b;TYPE=' . (str_ends_with(strtolower($this->photo), '.png') ? 'PNG' : 'JPEG') . ':' . base64_encode($data);
        }
        $lines[] = 'NOTE:' . $esc('Profile: ' . $this->shareUrl());
        $lines[] = 'END:VCARD';
        return implode("\r\n", $lines) . "\r\n";
    }

    /* ---------------- stats ---------------- */

    /** Count a profile event for today (privacy-friendly: no visitor data is stored). */
    public function record(string $metric): void
    {
        try {
            DB::statement('INSERT INTO profile_stats (participant_id, day, metric, count) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE count = count + 1',
                [$this->id, now()->toDateString(), $metric]);
        } catch (\Throwable $e) {
            // counters must never break the profile page
        }
    }

    public function stats(): array
    {
        return DB::table('profile_stats')->where('participant_id', $this->id)->select('metric', DB::raw('SUM(count) as n'))->groupBy('metric')->pluck('n', 'metric')->map(fn ($n) => (int) $n)->all();
    }
}
