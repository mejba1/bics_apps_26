<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A participant's social / web links: any number, in the participant's order.
 * Stored in participants.socials as [{type, url, label, icon?, color?}]:
 *   - "type" is recognised from the address (linkedin.com → LinkedIn …), using the built-in platforms and the ones
 *     added in Admin › Participants › Social icons (custom ones are checked first);
 *   - "label" is optional (e.g. "Lab website");
 *   - "icon" / "color" are optional choices from the icon picker: icon = "bi-…" from ICONS, or "platform:{type}"
 *     to look like a platform (e.g. a custom logo). Empty = automatic.
 * Older profiles stored {linkedin: url, …}; normalize() reads both.
 */
class SocialLinks
{
    public const MAX = 100;
    public const SETTING = 'social.platforms';

    /** type => [name, Bootstrap icon, brand colour, host pattern] — order matters (first match wins). */
    public const PLATFORMS = [
        'linkedin' => ['LinkedIn', 'bi-linkedin', '#0a66c2', '/(^|\.)linkedin\.com$|(^|\.)lnkd\.in$/'],
        'x' => ['X (Twitter)', 'bi-twitter-x', '#111111', '/(^|\.)(x|twitter)\.com$/'],
        'facebook' => ['Facebook', 'bi-facebook', '#1877f2', '/(^|\.)(facebook|fb)\.com$|(^|\.)fb\.me$/'],
        'instagram' => ['Instagram', 'bi-instagram', '#d62976', '/(^|\.)instagram\.com$/'],
        'youtube' => ['YouTube', 'bi-youtube', '#ff0000', '/(^|\.)youtube\.com$|(^|\.)youtu\.be$/'],
        'tiktok' => ['TikTok', 'bi-tiktok', '#010101', '/(^|\.)tiktok\.com$/'],
        'threads' => ['Threads', 'bi-threads', '#000000', '/(^|\.)threads\.(net|com)$/'],
        'telegram' => ['Telegram', 'bi-telegram', '#229ed9', '/(^|\.)(t\.me|telegram\.me|telegram\.org)$/'],
        'whatsapp' => ['WhatsApp', 'bi-whatsapp', '#1fa855', '/(^|\.)(wa\.me|whatsapp\.com)$/'],
        'researchgate' => ['ResearchGate', 'bi-journal-text', '#00a99d', '/(^|\.)researchgate\.net$/'],
        'scholar' => ['Google Scholar', 'bi-mortarboard-fill', '#4285f4', '/^scholar\.google\./'],
        'orcid' => ['ORCID', 'bi-person-vcard', '#a6ce39', '/(^|\.)orcid\.org$/'],
        'pubmed' => ['PubMed', 'bi-journal-medical', '#20558a', '/(^|\.)(pubmed\.ncbi\.nlm\.nih\.gov|ncbi\.nlm\.nih\.gov)$/'],
        'github' => ['GitHub', 'bi-github', '#24292f', '/(^|\.)github\.com$/'],
        'medium' => ['Medium', 'bi-medium', '#000000', '/(^|\.)medium\.com$/'],
        'other' => ['Website', 'bi-link-45deg', '#475569', null],
    ];

    /** Icons anyone can pick for a link (Bootstrap Icons 1.11). */
    public const ICONS = [
        'bi-globe2' => 'Website', 'bi-link-45deg' => 'Link', 'bi-envelope-fill' => 'Email', 'bi-telephone-fill' => 'Phone', 'bi-geo-alt-fill' => 'Location',
        'bi-hospital-fill' => 'Hospital', 'bi-heart-pulse-fill' => 'Health', 'bi-building' => 'Organization', 'bi-briefcase-fill' => 'Work', 'bi-mortarboard-fill' => 'Education',
        'bi-journal-text' => 'Publications', 'bi-book-fill' => 'Book', 'bi-newspaper' => 'News', 'bi-mic-fill' => 'Podcast', 'bi-camera-video-fill' => 'Video',
        'bi-play-btn-fill' => 'Channel', 'bi-camera-fill' => 'Photos', 'bi-music-note-beamed' => 'Music', 'bi-calendar-event-fill' => 'Events', 'bi-chat-dots-fill' => 'Chat',
        'bi-rss-fill' => 'Blog / RSS', 'bi-file-earmark-pdf-fill' => 'PDF / CV', 'bi-award-fill' => 'Award', 'bi-star-fill' => 'Featured', 'bi-shop' => 'Shop',
        'bi-pinterest' => 'Pinterest', 'bi-snapchat' => 'Snapchat', 'bi-reddit' => 'Reddit', 'bi-discord' => 'Discord', 'bi-twitch' => 'Twitch',
        'bi-spotify' => 'Spotify', 'bi-skype' => 'Skype', 'bi-vimeo' => 'Vimeo', 'bi-behance' => 'Behance', 'bi-dribbble' => 'Dribbble',
        'bi-mastodon' => 'Mastodon', 'bi-wechat' => 'WeChat', 'bi-line' => 'LINE', 'bi-signal' => 'Signal', 'bi-substack' => 'Substack',
        'bi-quora' => 'Quora', 'bi-stack-overflow' => 'Stack Overflow', 'bi-google' => 'Google', 'bi-microsoft-teams' => 'Teams', 'bi-slack' => 'Slack',
        'bi-messenger' => 'Messenger', 'bi-wordpress' => 'WordPress', 'bi-apple' => 'Apple', 'bi-android2' => 'Android', 'bi-sina-weibo' => 'Weibo',
    ];

    /** Colours offered in the picker (any #rrggbb is accepted). */
    public const COLORS = ['#0e4ca1', '#b22e6b', '#1f9d55', '#c27c0e', '#c0392b', '#1f8fb3', '#6d28d9', '#475569', '#111111'];

    private static ?array $all = null;

    /* ---------------- platforms ---------------- */

    /** Platforms added by admins: [{type, name, domains[], icon (bi-…|null), image (path|null), color}]. */
    public static function custom(): array
    {
        try {
            $v = DB::table('settings')->where('key', static::SETTING)->value('value');
        } catch (\Throwable $e) {
            return [];
        }
        $list = $v ? (json_decode($v, true) ?: []) : [];
        return array_values(array_filter($list, fn ($p) => is_array($p) && !empty($p['type']) && !empty($p['name'])));
    }

    public static function saveCustom(array $list): void
    {
        DB::table('settings')->updateOrInsert(['key' => static::SETTING], ['value' => json_encode(array_values($list)), 'updated_at' => now(), 'created_at' => now()]);
        static::$all = null;
    }

    /**
     * Every platform, custom first: type => [name, icon, color, image url|null, matcher].
     * matcher = regex string (built-in) or list of domains (custom).
     */
    public static function all(): array
    {
        if (static::$all !== null) return static::$all;
        $out = [];
        foreach (static::custom() as $p) {
            $img = !empty($p['image']) && is_file(base_path($p['image'])) ? asset($p['image']) . '?v=' . @filemtime(base_path($p['image'])) : null;
            $out[$p['type']] = [$p['name'], $p['icon'] ?: 'bi-link-45deg', static::hex($p['color'] ?? null, '#475569'), $img, array_values((array) ($p['domains'] ?? []))];
        }
        foreach (static::PLATFORMS as $type => [$name, $icon, $color, $re]) {
            if ($type === 'other') continue;
            $out[$type] = [$name, $icon, $color, null, $re];
        }
        $o = static::PLATFORMS['other'];
        $out['other'] = [$o[0], $o[1], $o[2], null, null];
        return static::$all = $out;
    }

    public static function detect(?string $url): string
    {
        $host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));
        $host = preg_replace('/^(www|m|mobile)\./', '', $host);
        if ($host === '') return 'other';
        foreach (static::all() as $type => [, , , , $m]) {
            if (is_array($m)) { foreach ($m as $d) if ($host === $d || str_ends_with($host, '.' . $d)) return $type; }
            elseif ($m && preg_match($m, $host)) return $type;
        }
        return 'other';
    }

    /* ---------------- links ---------------- */

    /** Clean list from anything stored or sent: old {type: url}, [{url, label, icon, color}], or plain URLs. Invalid / duplicate links dropped. */
    public static function normalize($raw): array
    {
        if (is_string($raw)) $raw = json_decode($raw, true);
        if (!is_array($raw)) return [];
        $items = [];
        foreach ($raw as $k => $v) {
            // old keyed format ({linkedin: url}): the box it was typed in is kept as a hint for the platform
            if (is_string($v)) $items[] = ['url' => $v, 'label' => null, 'hint' => is_string($k) ? $k : null];
            elseif (is_array($v)) $items[] = ['url' => (string) ($v['url'] ?? ''), 'label' => $v['label'] ?? null, 'hint' => $v['type'] ?? null, 'icon' => $v['icon'] ?? null, 'color' => $v['color'] ?? null];
        }
        $platforms = static::all();
        $out = [];
        $seen = [];
        foreach ($items as $it) {
            $url = trim($it['url']);
            if ($url !== '' && !preg_match('#^https?://#i', $url)) $url = 'https://' . ltrim($url, '/');   // "linkedin.com/in/x" → https://…
            if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url) || mb_strlen($url) > 500) continue;
            $key = strtolower(rtrim(preg_replace('#^https?://(www\.)?#i', '', $url), '/'));
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $label = trim(strip_tags((string) ($it['label'] ?? '')));
            $type = static::detect($url);
            // an address the platform list does not know keeps the platform it was saved with (e.g. a custom LinkedIn domain)
            if ($type === 'other' && isset($platforms[$it['hint'] ?? '']) && $it['hint'] !== 'other') $type = $it['hint'];
            $link = ['type' => $type, 'url' => $url, 'label' => $label !== '' ? mb_substr($label, 0, 60) : null];
            // icon picker choice (only known values are kept)
            $icon = (string) ($it['icon'] ?? '');
            if (isset(static::ICONS[$icon]) || (str_starts_with($icon, 'platform:') && isset($platforms[substr($icon, 9)]))) $link['icon'] = $icon;
            if (($c = static::hex($it['color'] ?? null, '')) !== '') $link['color'] = $c;
            $out[] = $link;
            if (count($out) >= static::MAX) break;
        }
        return $out;
    }

    /** What to show for a link: its own label, else the platform name, else the web address. */
    public static function title(array $link): string
    {
        if (!empty($link['label'])) return $link['label'];
        $type = $link['type'] ?? 'other';
        if ($type !== 'other' && isset(static::all()[$type])) return static::all()[$type][0];
        return preg_replace('/^www\./', '', (string) parse_url($link['url'], PHP_URL_HOST)) ?: 'Website';
    }

    /** How a link looks: ['icon' => 'bi-…', 'image' => url|null, 'color' => '#…', 'name' => platform name]. */
    public static function look(array $link): array
    {
        $all = static::all();
        $p = $all[$link['type'] ?? 'other'] ?? $all['other'];
        $look = ['icon' => $p[1], 'image' => $p[3], 'color' => $p[2], 'name' => $p[0]];
        $icon = $link['icon'] ?? '';
        if (isset(static::ICONS[$icon])) { $look['icon'] = $icon; $look['image'] = null; }
        elseif (str_starts_with($icon, 'platform:') && isset($all[substr($icon, 9)])) {
            $q = $all[substr($icon, 9)];
            $look = ['icon' => $q[1], 'image' => $q[3], 'color' => $q[2], 'name' => $look['name']];
        }
        if (!empty($link['color'])) $look['color'] = $link['color'];
        return $look;
    }

    public static function icon(string $type): string
    {
        return (static::all()[$type] ?? static::all()['other'])[1];
    }

    public static function color(string $type): string
    {
        return (static::all()[$type] ?? static::all()['other'])[2];
    }

    /** For the editors' JavaScript. */
    public static function forJs(): array
    {
        $platforms = [];
        foreach (static::all() as $type => [$name, $icon, $color, $img, $m]) {
            $platforms[] = ['type' => $type, 'name' => $name, 'icon' => $icon, 'color' => $color, 'image' => $img,
                'pattern' => is_string($m) ? trim($m, '/') : null, 'domains' => is_array($m) ? $m : null];
        }
        return $platforms;
    }

    public static function iconsForJs(): array
    {
        return collect(static::ICONS)->map(fn ($n, $k) => ['icon' => $k, 'name' => $n])->values()->all();
    }

    /** "kaggle.com, https://www.kaggle.io/x" → ["kaggle.com", "kaggle.io"] */
    public static function domains(string $text): array
    {
        $out = [];
        foreach (preg_split('/[\s,;]+/', strtolower($text), -1, PREG_SPLIT_NO_EMPTY) as $d) {
            $d = preg_replace('#^https?://#', '', $d);
            $d = preg_replace('#^(www|m|mobile)\.#', '', explode('/', $d)[0]);
            if (preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $d)) $out[] = $d;
        }
        return array_values(array_unique($out));
    }

    public static function newType(string $name): string
    {
        $base = 'c_' . (Str::slug($name, '_') ?: 'link');
        $type = $base; $i = 2;
        $taken = array_merge(array_keys(static::PLATFORMS), array_column(static::custom(), 'type'));
        while (in_array($type, $taken, true)) $type = $base . '_' . $i++;
        return $type;
    }

    public static function hex($v, string $fallback): string
    {
        return is_string($v) && preg_match('/^#[0-9a-f]{6}$/i', $v) ? strtolower($v) : $fallback;
    }
}
