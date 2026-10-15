<?php

namespace App\Models;

use App\Support\CardPresets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A card design (Card Design Studio). The design is stored in millimetres (see App\Support\CardPresets for the format)
 * and drawn by assets/backend/card-studio/card-renderer.js — in the editor, in previews and (phase 4) in print files.
 *
 * Which template a participant gets: event + category › event default › global default.
 * Every save keeps a version (restore any of them).
 */
class CardTemplate extends Model
{
    public const TYPES = ['text', 'image', 'qr', 'rect', 'ellipse', 'line', 'icon'];
    public const FONTS = ['Plus Jakarta Sans', 'Inter', 'Montserrat', 'Poppins', 'Roboto', 'Open Sans', 'Oswald', 'Playfair Display', 'Lora', 'Merriweather'];
    public const PLACEHOLDERS = [
        'name' => 'Title + full name', 'first_name' => 'First name', 'last_name' => 'Last name', 'title' => 'Title (Dr, Prof…)',
        'designation' => 'Designation', 'organization' => 'Organization', 'department' => 'Department', 'category' => 'Category',
        'country' => 'Country', 'participant_id' => 'Participant ID', 'email' => 'Email', 'mobile' => 'Mobile', 'phone' => 'Telephone',
        'website' => 'Website', 'event_name' => 'Event name', 'event_short' => 'Event short name', 'event_dates' => 'Event dates',
        'card_no' => 'NFC card number', 'profile_url' => 'Profile link',
    ];

    protected $fillable = ['name', 'event_id', 'category', 'is_default', 'design', 'version'];
    protected $casts = ['design' => 'array', 'is_default' => 'boolean'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function versions()
    {
        return $this->hasMany(CardTemplateVersion::class, 'template_id')->orderByDesc('version');
    }

    /** The template for a participant (at an event). */
    public static function forParticipant(?Participant $p, ?Event $event = null): ?CardTemplate
    {
        if ($event && $p && $p->category) {
            $t = static::where('event_id', $event->id)->where('category', $p->category)->first();
            if ($t) return $t;
        }
        if ($event) {
            $t = static::where('event_id', $event->id)->whereNull('category')->orderByDesc('is_default')->first();
            if ($t) return $t;
        }
        return static::where('is_default', true)->first() ?? static::orderBy('id')->first();
    }

    /** Save a new design as the next version. */
    public function saveDesign(array $design, ?string $name, ?int $userId, ?string $note = null): void
    {
        DB::transaction(function () use ($design, $name, $userId, $note) {
            $this->design = static::clean($design);
            if ($name) $this->name = Str::limit(trim($name), 120, '');
            $this->version = (int) $this->version + 1;
            $this->updated_by = $userId;
            $this->save();
            DB::table('card_template_versions')->insert(['template_id' => $this->id, 'version' => $this->version, 'name' => $this->name,
                'design' => json_encode($this->design), 'note' => $note ? Str::limit($note, 200, '') : null, 'user_id' => $userId, 'created_at' => now()]);
            // keep the last 60 versions
            $keep = DB::table('card_template_versions')->where('template_id', $this->id)->orderByDesc('version')->limit(60)->pluck('id');
            DB::table('card_template_versions')->where('template_id', $this->id)->whereNotIn('id', $keep)->delete();
        });
    }

    public static function createFrom(string $name, array $design, ?int $userId): CardTemplate
    {
        return DB::transaction(function () use ($name, $design, $userId) {
            $t = static::create(['name' => Str::limit(trim($name), 120, ''), 'design' => static::clean($design), 'version' => 1]);
            $t->updated_by = $userId;
            $t->save();
            DB::table('card_template_versions')->insert(['template_id' => $t->id, 'version' => 1, 'name' => $t->name, 'design' => json_encode($t->design),
                'note' => 'Created', 'user_id' => $userId, 'created_at' => now()]);
            return $t;
        });
    }

    /** Keep only known fields with safe values (the design comes from the browser). */
    public static function clean(array $d): array
    {
        $num = fn ($v, $min, $max, $def = 0) => is_numeric($v) ? round(max($min, min($max, (float) $v)), 2) : $def;
        $color = fn ($v, $def = '') => is_string($v) && preg_match('/^#[0-9a-f]{6}$/i', $v) ? strtolower($v) : $def;
        $img = function ($v) {
            $v = ltrim(str_replace('\\', '/', trim((string) $v)), '/');
            return $v !== '' && !str_contains($v, '..') && preg_match('#^(assets|upload)/[A-Za-z0-9_\-/.]+\.(png|jpe?g|webp|svg)$#i', $v) && is_file(base_path($v)) ? $v : '';
        };
        $w = $num($d['width'] ?? 54, 20, 300, 54);
        $h = $num($d['height'] ?? 85.6, 20, 300, 85.6);
        $out = ['width' => $w, 'height' => $h, 'bleed' => $num($d['bleed'] ?? 2, 0, 10, 2), 'safe' => $num($d['safe'] ?? 3, 0, 20, 3),
            'double_sided' => !empty($d['double_sided'])];
        $icons = array_keys(json_decode((string) @file_get_contents(base_path('assets/backend/card-studio/icons.json')), true) ?: []);
        foreach (['front', 'back'] as $side) {
            $s = is_array($d[$side] ?? null) ? $d[$side] : [];
            $bg = is_array($s['background'] ?? null) ? $s['background'] : [];
            $els = [];
            foreach (array_slice(is_array($s['elements'] ?? null) ? $s['elements'] : [], 0, 80) as $e) {
                $type = $e['type'] ?? '';
                if (!in_array($type, static::TYPES, true)) continue;
                $base = ['id' => preg_match('/^[A-Za-z0-9_-]{1,20}$/', (string) ($e['id'] ?? '')) ? $e['id'] : 'e' . Str::random(6), 'type' => $type,
                    'name' => Str::limit(trim((string) ($e['name'] ?? ucfirst($type))), 40, ''),
                    'x' => $num($e['x'] ?? 0, -50, 350), 'y' => $num($e['y'] ?? 0, -50, 350), 'w' => $num($e['w'] ?? 10, 0.1, 400, 10), 'h' => $num($e['h'] ?? 10, 0.05, 400, 10),
                    'rotate' => $num($e['rotate'] ?? 0, -360, 360), 'opacity' => $num($e['opacity'] ?? 1, 0, 1, 1), 'locked' => !empty($e['locked']), 'hidden' => !empty($e['hidden'])];
                $p = match ($type) {
                    'text' => ['text' => Str::limit((string) ($e['text'] ?? ''), 500, ''), 'font' => in_array($e['font'] ?? '', static::FONTS, true) ? $e['font'] : 'Plus Jakarta Sans',
                        'size' => $num($e['size'] ?? 8, 2, 120, 8), 'weight' => in_array((int) ($e['weight'] ?? 400), [300, 400, 500, 600, 700, 800], true) ? (int) $e['weight'] : 400,
                        'italic' => !empty($e['italic']), 'color' => $color($e['color'] ?? '', '#101a2e'), 'align' => in_array($e['align'] ?? '', ['left', 'center', 'right'], true) ? $e['align'] : 'left',
                        'valign' => in_array($e['valign'] ?? '', ['top', 'middle', 'bottom'], true) ? $e['valign'] : 'middle', 'upper' => !empty($e['upper']),
                        'letter' => $num($e['letter'] ?? 0, -0.1, 0.5), 'line' => $num($e['line'] ?? 1.15, 0.8, 3, 1.15), 'fit' => !empty($e['fit']), 'maxLines' => (int) $num($e['maxLines'] ?? 1, 1, 10, 1)],
                    'image' => ['source' => in_array($e['source'] ?? '', ['photo', 'logo', 'upload'], true) ? $e['source'] : 'upload', 'src' => $img($e['src'] ?? ''),
                        'fit' => ($e['fit'] ?? '') === 'contain' ? 'contain' : 'cover', 'radius' => $num($e['radius'] ?? 0, 0, 200), 'border' => $num($e['border'] ?? 0, 0, 10),
                        'borderColor' => $color($e['borderColor'] ?? '', '#ffffff')],
                    'qr' => ['source' => in_array($e['source'] ?? '', ['profile', 'nfc', 'custom'], true) ? $e['source'] : 'profile', 'text' => Str::limit((string) ($e['text'] ?? ''), 300, ''),
                        'color' => $color($e['color'] ?? '', '#000000'), 'bg' => $color($e['bg'] ?? '', '#ffffff')],
                    'rect' => ['fill' => $color($e['fill'] ?? ''), 'stroke' => $color($e['stroke'] ?? ''), 'strokeWidth' => $num($e['strokeWidth'] ?? 0, 0, 10), 'radius' => $num($e['radius'] ?? 0, 0, 200)],
                    'ellipse' => ['fill' => $color($e['fill'] ?? ''), 'stroke' => $color($e['stroke'] ?? ''), 'strokeWidth' => $num($e['strokeWidth'] ?? 0, 0, 10)],
                    'line' => ['stroke' => $color($e['stroke'] ?? '', '#000000'), 'strokeWidth' => $num($e['strokeWidth'] ?? 0.3, 0.05, 10, 0.3)],
                    'icon' => ['icon' => in_array($e['icon'] ?? '', $icons, true) ? $e['icon'] : ($icons[0] ?? 'star-fill'), 'color' => $color($e['color'] ?? '', '#000000')],
                };
                $els[] = $base + $p;
            }
            $out[$side] = ['background' => ['color' => $color($bg['color'] ?? '', '#ffffff'), 'image' => $img($bg['image'] ?? ''), 'fit' => ($bg['fit'] ?? '') === 'contain' ? 'contain' : 'cover'], 'elements' => $els];
        }
        return $out;
    }

    public static function blankDesign(string $size): array
    {
        $s = CardPresets::SIZES[$size] ?? CardPresets::SIZES['cr80_portrait'];
        return CardPresets::blank($s[1], $s[2]);
    }
}
