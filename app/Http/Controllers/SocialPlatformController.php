<?php

namespace App\Http\Controllers;

use App\Support\SocialLinks;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Participants › Social icons (permission "participants.manage"): platforms added by admins — name, web addresses,
 * an icon (Bootstrap icon or uploaded logo) and a colour. Links to those addresses get the icon automatically,
 * in the admin and participant editors and on public profiles. Built-in platforms are listed read-only.
 */
class SocialPlatformController extends Controller
{
    public function index(){
        return view('backend.pages.participants.social-icons', [
            'custom' => SocialLinks::custom(),
            'builtin' => collect(SocialLinks::PLATFORMS)->except('other'),
            'icons' => SocialLinks::ICONS,
            'colors' => SocialLinks::COLORS,
            'usage' => $this->usage(),
        ]);
    }

    /** Add or edit a platform (multipart: optional logo upload). */
    public function save(Request $request){
        $data = $request->validate([
            'type' => 'nullable|string|max:60',
            'name' => 'required|string|max:40',
            'domains' => 'required|string|max:500',
            'icon' => ['nullable', Rule::in(array_keys(SocialLinks::ICONS))],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => 'nullable|file|mimes:png,jpg,jpeg,webp|max:1024',
            'remove_logo' => 'nullable|boolean',
        ], ['logo.mimes' => 'Use a PNG, JPG or WebP picture (square works best).', 'logo.max' => 'The logo must be smaller than 1 MB.', 'color.regex' => 'Choose a colour.']);
        $domains = SocialLinks::domains($data['domains']);
        if (!$domains) throw ValidationException::withMessages(['domains' => 'Enter at least one web address, e.g. kaggle.com']);

        $list = SocialLinks::custom();
        $idx = null;
        if (!empty($data['type'])) foreach ($list as $i => $p) if ($p['type'] === $data['type']) $idx = $i;
        if (!empty($data['type']) && $idx === null) abort(404);

        // a domain may belong to one custom platform only
        foreach ($list as $i => $p) {
            if ($i === $idx) continue;
            $clash = array_intersect($domains, (array) ($p['domains'] ?? []));
            if ($clash) throw ValidationException::withMessages(['domains' => reset($clash) . ' is already used by "' . $p['name'] . '".']);
        }

        $item = $idx !== null ? $list[$idx] : ['type' => SocialLinks::newType($data['name']), 'image' => null];
        $item['name'] = trim(strip_tags($data['name']));
        $item['domains'] = $domains;
        $item['icon'] = $data['icon'] ?: 'bi-link-45deg';
        $item['color'] = strtolower($data['color']);
        if ($request->boolean('remove_logo') && !empty($item['image'])) { $this->deleteFile($item['image']); $item['image'] = null; }
        if ($request->hasFile('logo')) {
            if (!empty($item['image'])) $this->deleteFile($item['image']);
            $item['image'] = $this->storeLogo($request->file('logo'), $item['type']);
        }
        if ($idx !== null) $list[$idx] = $item; else $list[] = $item;
        SocialLinks::saveCustom($list);
        return response()->json(['success' => true, 'message' => $item['name'] . ' saved. Links to ' . implode(', ', $domains) . ' now show this icon.']);
    }

    public function destroy(string $type){
        $list = SocialLinks::custom();
        $keep = [];
        $gone = null;
        foreach ($list as $p) { if ($p['type'] === $type) $gone = $p; else $keep[] = $p; }
        abort_unless($gone, 404);
        if (!empty($gone['image'])) $this->deleteFile($gone['image']);
        SocialLinks::saveCustom($keep);
        return response()->json(['success' => true, 'message' => $gone['name'] . ' removed. Its links show the standard icon again.']);
    }

    /** Re-encode as a 128 × 128 PNG (square crop): strips anything hidden in the file, keeps it small. */
    private function storeLogo($file, string $type): string
    {
        $src = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (!$src) throw ValidationException::withMessages(['logo' => 'This picture could not be read.']);
        $w = imagesx($src); $h = imagesy($src); $side = min($w, $h);
        if ($side < 32) throw ValidationException::withMessages(['logo' => 'The logo is too small — use at least 64 × 64 px.']);
        $out = imagecreatetruecolor(128, 128);
        imagealphablending($out, false); imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $src, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 2), 128, 128, $side, $side);
        $dir = 'upload/social-icons';
        if (!is_dir(base_path($dir))) @mkdir(base_path($dir), 0775, true);
        $path = $dir . '/' . preg_replace('/[^a-z0-9_]/', '', $type) . '-' . \Illuminate\Support\Str::random(6) . '.png';
        imagepng($out, base_path($path), 6);
        imagedestroy($src); imagedestroy($out);
        return $path;
    }

    private function deleteFile(string $path): void
    {
        if (str_starts_with($path, 'upload/social-icons/')) @unlink(base_path($path));
    }

    /** How many links use each platform (shown in the list). */
    private function usage(): array
    {
        $n = [];
        \App\Models\Participant::whereNotNull('socials')->select('socials')->chunk(500, function ($rows) use (&$n) {
            foreach ($rows as $p) foreach (SocialLinks::normalize($p->socials ?? []) as $l) $n[$l['type']] = ($n[$l['type']] ?? 0) + 1;
        });
        return $n;
    }
}
