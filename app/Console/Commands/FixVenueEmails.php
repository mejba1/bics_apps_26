<?php

namespace App\Console\Commands;

use App\Models\Participant;
use App\Support\Audit;
use App\Support\SocialLinks;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Old imports stored emails with the venue appended ("name@gmail.com_algeria") so one person could register at two
 * venues. That makes the address unusable: no confirmation / certificate emails, and no participant dashboard sign-in.
 *
 *   php artisan bics:fix-venue-emails --venue=Algeria --suffix=_algeria            (dry run: shows what would change)
 *   php artisan bics:fix-venue-emails --venue=Algeria --suffix=_algeria --apply    (changes the data)
 *
 * Per registration: the suffix is removed; the participant profile gets the email if it has none; if another
 * profile already uses that email (same person registered at another venue), the two profiles are merged into the
 * one with the email (registrations, cards, visits… moved; missing details copied; the old link redirects).
 */
class FixVenueEmails extends Command
{
    protected $signature = 'bics:fix-venue-emails {--venue= : Venue code, e.g. Algeria} {--suffix= : Text appended to the emails, e.g. _algeria} {--apply : Change the data (otherwise only a report)}';
    protected $description = 'Remove a venue suffix from registration emails and give the participant profiles their real email (merging duplicates)';

    private const COPY = ['title', 'designation', 'organization', 'department', 'category', 'country', 'birth_date', 'photo', 'logo', 'mobile', 'phone',
        'whatsapp', 'address', 'map_url', 'website', 'bio', 'expertise'];

    public function handle(): int
    {
        $venue = (string) $this->option('venue');
        $suffix = (string) $this->option('suffix');
        if ($venue === '' || $suffix === '') { $this->error('Give --venue and --suffix.'); return self::FAILURE; }
        $apply = (bool) $this->option('apply');
        $len = strlen($suffix);
        $rows = DB::table('registrations')->where('venue', $venue)->whereRaw('LOWER(RIGHT(email, ?)) = ?', [$len, strtolower($suffix)])->orderBy('id')->get();
        $this->info(($apply ? 'Fixing' : 'Dry run —') . ' ' . $rows->count() . " registration email(s) at $venue ending in \"$suffix\".");
        $stats = ['registrations' => 0, 'profiles given email' => 0, 'profiles merged' => 0, 'skipped' => 0, 'profile email kept (different)' => 0];
        $merges = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $r) {
                $clean = mb_strtolower(trim(substr($r->email, 0, -$len)));
                if (!filter_var($clean, FILTER_VALIDATE_EMAIL)) { $stats['skipped']++; $this->warn("  #{$r->id}: \"$clean\" is not a valid email — left as it is"); continue; }
                $clash = DB::table('registrations')->where('id', '!=', $r->id)->where('event_id', $r->event_id)->whereRaw('LOWER(email) = ?', [$clean])->exists();
                if ($clash) { $stats['skipped']++; $this->warn("  #{$r->id}: $clean is already registered for this event — left as it is"); continue; }
                DB::table('registrations')->where('id', $r->id)->update(['email' => $clean, 'updated_at' => now()]);
                $stats['registrations']++;

                $p = $r->participant_id ? Participant::find($r->participant_id) : null;
                if (!$p) continue;
                $keeper = Participant::where('email', $clean)->where('id', '!=', $p->id)->first();
                if ($keeper) {
                    $this->merge($p, $keeper);
                    $stats['profiles merged']++;
                    $merges[] = "{$p->code} → {$keeper->code} ($clean)";
                } elseif (!$p->email) {
                    DB::table('participants')->where('id', $p->id)->update(['email' => $clean, 'updated_at' => now()]);
                    $stats['profiles given email']++;
                } elseif ($p->email !== $clean) {
                    $stats['profile email kept (different)']++;
                    $this->line("  {$p->code}: profile already has {$p->email} — kept (registration now $clean)");
                }
            }
            foreach ($merges as $m) $this->line("  merged $m");
            if ($apply) {
                DB::commit();
                Audit::event('participants', 'Fixed venue emails', null, ['venue' => $venue, 'suffix' => $suffix] + $stats);
            } else {
                DB::rollBack();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Stopped, nothing was changed: ' . $e->getMessage());
            return self::FAILURE;
        }
        $this->table(['', 'count'], collect($stats)->map(fn ($n, $k) => [$k, $n])->values()->all());
        $this->info($apply ? 'Done.' : 'Nothing was changed. Run again with --apply to make these changes.');
        return self::SUCCESS;
    }

    /** Move everything of $dup to $keep, copy details $keep is missing, then retire $dup (its link redirects to $keep). */
    private function merge(Participant $dup, Participant $keep): void
    {
        foreach (['registrations', 'nfc_cards', 'card_print_job_items', 'participant_changes', 'participant_logins'] as $table) {
            DB::table($table)->where('participant_id', $dup->id)->update(['participant_id' => $keep->id]);
        }
        DB::table('participant_login_codes')->where('participant_id', $dup->id)->delete();
        // visit counts: add to the kept profile's day / metric
        foreach (DB::table('profile_stats')->where('participant_id', $dup->id)->get() as $s) {
            DB::statement('INSERT INTO profile_stats (participant_id, day, metric, count) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE count = count + VALUES(count)', [$keep->id, $s->day, $s->metric, $s->count]);
        }
        DB::table('profile_stats')->where('participant_id', $dup->id)->delete();

        $fill = [];
        foreach (static::COPY as $f) if (blank($keep->getAttribute($f)) && !blank($dup->getAttribute($f))) $fill[$f] = $dup->getAttribute($f);
        $links = SocialLinks::normalize(array_merge($keep->socialLinks(), $dup->socialLinks()));
        if (count($links) > count($keep->socialLinks())) $fill['socials'] = $links;
        if ($fill) $keep->fill($fill)->save();
        DB::table('participant_changes')->insert(['participant_id' => $keep->id, 'source' => 'admin', 'user_id' => null,
            'changes' => json_encode(['merged profile' => [$dup->code, $keep->code]] + array_map(fn ($v) => [null, is_array($v) ? '(list)' : $v], $fill)), 'created_at' => now()]);

        DB::table('participants')->where('id', $dup->id)->update(['merged_into_id' => $keep->id, 'slug' => null, 'deleted_at' => now(), 'updated_at' => now()]);
    }
}
