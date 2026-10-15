<?php

use App\Support\SocialLinks;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Social links become an open list ([{type, url, label}], any number) instead of seven fixed boxes ({linkedin: url, …}).
 * Existing links are converted; down() turns the list back into the fixed keys (first link of each kind).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('participants')->whereNotNull('socials')->orderBy('id')->select('id', 'socials')->chunkById(200, function ($rows) {
            foreach ($rows as $r) {
                $list = SocialLinks::normalize($r->socials);
                DB::table('participants')->where('id', $r->id)->update(['socials' => $list ? json_encode($list, JSON_UNESCAPED_SLASHES) : null]);
            }
        });
    }

    public function down(): void
    {
        $old = ['linkedin', 'facebook', 'x', 'instagram', 'youtube', 'researchgate'];
        DB::table('participants')->whereNotNull('socials')->orderBy('id')->select('id', 'socials')->chunkById(200, function ($rows) use ($old) {
            foreach ($rows as $r) {
                $keyed = [];
                foreach (SocialLinks::normalize($r->socials) as $l) {
                    $k = in_array($l['type'], $old, true) ? $l['type'] : 'other';
                    $keyed[$k] ??= $l['url'];
                }
                DB::table('participants')->where('id', $r->id)->update(['socials' => $keyed ? json_encode($keyed, JSON_UNESCAPED_SLASHES) : null]);
            }
        });
    }
};
