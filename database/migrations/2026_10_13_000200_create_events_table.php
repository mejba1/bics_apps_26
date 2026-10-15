<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Events become real records (Admin > Events) instead of one set of "settings" keys.
     * - The event in the settings table becomes the current event (new registrations go there).
     * - Every other venue code found in registrations becomes an archived event.
     * - registrations / attendees get event_id (filled from their venue code).
     * - One registration per email PER EVENT (the email was unique across all events).
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('event_name', 200);
            $table->string('event_short', 50);
            $table->string('slug', 120)->unique();
            $table->string('venue_code', 50)->unique();   // saved on every registration ("venue")
            $table->string('tracking_no', 50)->nullable();
            $table->string('event_venue', 255)->nullable();
            $table->string('event_timezone', 64)->default('UTC');
            // "Y-m-d H:i" in the event's own timezone (same format the settings used)
            $table->string('event_start', 16)->nullable();
            $table->string('event_end', 16)->nullable();
            $table->string('registration_opens', 16)->nullable();
            $table->string('registration_closes', 16)->nullable();
            $table->text('registration_closed_message')->nullable();
            $table->text('registration_upcoming_message')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });

        $now = now();
        $settings = DB::table('settings')->pluck('value', 'key');
        $currentCode = trim((string) ($settings['venue_code'] ?? '')) ?: config('bics.venue');
        $slugs = [];
        $slug = function (string $text) use (&$slugs) {
            $base = Str::slug($text) ?: 'event';
            $s = $base;
            for ($i = 2; in_array($s, $slugs, true); $i++) $s = $base . '-' . $i;
            return $slugs[] = $s;
        };

        // Current event: everything Admin > Event & Registration had
        $short = $settings['event_short'] ?? config('bics.event_short');
        DB::table('events')->insert([
            'event_name' => $settings['event_name'] ?? config('bics.event_name'),
            'event_short' => $short,
            'slug' => $slug($short ?: $currentCode),
            'venue_code' => $currentCode,
            'tracking_no' => $settings['tracking_no'] ?? config('bics.tracking_no'),
            'event_venue' => $settings['event_venue'] ?? config('bics.event_venue'),
            'event_timezone' => ($settings['event_timezone'] ?? '') ?: config('app.timezone', 'UTC'),
            'event_start' => ($settings['event_start'] ?? '') ?: null,
            'event_end' => ($settings['event_end'] ?? '') ?: null,
            'registration_opens' => ($settings['registration_opens'] ?? '') ?: null,
            'registration_closes' => ($settings['registration_closes'] ?? '') ?: null,
            'registration_closed_message' => $settings['registration_closed_message'] ?? null,
            'registration_upcoming_message' => $settings['registration_upcoming_message'] ?? null,
            'status' => 'published',
            'is_current' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // Past events: one per other venue code that has registrations
        $past = DB::table('registrations')->select('venue', DB::raw('MIN(created_at) as first_at'))
            ->whereNotNull('venue')->where('venue', '!=', '')->where('venue', '!=', $currentCode)
            ->groupBy('venue')->orderBy('first_at')->get();
        foreach ($past as $p) {
            DB::table('events')->insert([
                'event_name' => 'BICS ' . $p->venue,
                'event_short' => Str::limit($p->venue, 50, ''),
                'slug' => $slug($p->venue),
                'venue_code' => Str::limit($p->venue, 50, ''),
                'event_timezone' => config('app.timezone', 'UTC'),
                'status' => 'archived',
                'is_current' => false,
                'created_at' => $p->first_at ?: $now, 'updated_at' => $now,
            ]);
        }

        // Link registrations and attendees to their event
        foreach (['registrations', 'attendees'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->foreignId('event_id')->nullable()->after('id')->constrained('events')->restrictOnDelete();
            });
            DB::statement("UPDATE `$t` r JOIN `events` e ON e.venue_code = r.venue SET r.event_id = e.id");
        }

        // Same person can register for many events, once per event
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropUnique('registrations_email_unique');
            $table->unique(['event_id', 'email'], 'registrations_event_email_unique');
            $table->index('email');
        });
        Schema::table('register_accounts', function (Blueprint $table) {
            $table->dropUnique('register_accounts_email_unique');
            $table->index('email');
        });

        // The old single-event settings now live on the event row
        DB::table('settings')->whereIn('key', [
            'event_name', 'event_short', 'event_venue', 'event_timezone', 'event_start', 'event_end',
            'registration_opens', 'registration_closes', 'registration_closed_message', 'registration_upcoming_message',
            'venue_code', 'tracking_no',
        ])->delete();
    }

    public function down(): void
    {
        // Put the current event back into the settings table
        $current = DB::table('events')->where('is_current', true)->first();
        if ($current) {
            foreach (['event_name', 'event_short', 'event_venue', 'event_timezone', 'event_start', 'event_end',
                'registration_opens', 'registration_closes', 'registration_closed_message', 'registration_upcoming_message',
                'venue_code', 'tracking_no'] as $k) {
                DB::table('settings')->updateOrInsert(['key' => $k], ['value' => (string) ($current->$k ?? ''), 'updated_at' => now(), 'created_at' => now()]);
            }
        }

        Schema::table('register_accounts', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->unique('email');
        });
        // Foreign keys first: MySQL may use the (event_id, email) index for the registrations foreign key
        foreach (['registrations', 'attendees'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropForeign(['event_id']);
            });
        }
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropUnique('registrations_event_email_unique');
            $table->dropIndex(['email']);
            $table->unique('email');
            $table->dropColumn('event_id');
        });
        Schema::table('attendees', function (Blueprint $table) {
            $table->dropColumn('event_id');
        });
        Schema::dropIfExists('events');
    }
};
