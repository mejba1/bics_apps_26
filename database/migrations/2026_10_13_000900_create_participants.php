<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Participant profiles (docs/NFC-PROFILE-PLAN.md, phase 1).
 * - participants: one permanent profile per person across events (code MBR-000001, URL /p/MBR-000001).
 * - registrations.participant_id: each event registration belongs to a participant (matched by email).
 * - profile_stats: daily counters per participant (views by source, vCard / QR downloads). No visitor data.
 * Existing registrations get a participant here (same email = same person).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->unsignedInteger('sequence')->unique();
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->string('title', 20)->nullable();
            $table->string('first_name', 150);
            $table->string('last_name', 150)->nullable();
            $table->string('designation', 150)->nullable();
            $table->string('organization', 190)->nullable();
            $table->string('department', 150)->nullable();
            $table->string('category', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('photo', 255)->nullable();
            $table->string('logo', 255)->nullable();
            $table->string('email', 190)->nullable()->index();
            $table->string('mobile', 40)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('whatsapp', 40)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('map_url', 500)->nullable();
            $table->string('website', 255)->nullable();
            $table->json('socials')->nullable();
            $table->text('bio')->nullable();
            $table->text('expertise')->nullable();
            $table->json('custom_fields')->nullable();
            $table->json('visibility')->nullable();
            $table->boolean('editing_enabled')->default(true);
            $table->json('locked_fields')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->foreignId('participant_id')->nullable()->after('event_id')->constrained('participants')->nullOnDelete();
        });

        Schema::create('profile_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('participants')->cascadeOnDelete();
            $table->date('day');
            $table->string('metric', 30);
            $table->unsignedInteger('count')->default(0);
            $table->unique(['participant_id', 'day', 'metric']);
            $table->index(['day', 'metric']);
        });

        // A profile for every existing registration: the same (valid) email = the same person
        $now = now();
        $seq = 0;
        $byEmail = [];
        foreach (DB::table('registrations')->orderBy('created_at')->orderBy('id')->get() as $r) {
            $email = strtolower(trim((string) $r->email));
            $valid = (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
            if ($valid && isset($byEmail[$email])) {
                DB::table('registrations')->where('id', $r->id)->update(['participant_id' => $byEmail[$email]]);
                continue;
            }
            $seq++;
            $cut = fn ($v, $n) => ($v = mb_substr(trim((string) $v), 0, $n)) !== '' ? $v : null;
            // Bot sign-ups (links / markup in the name) get a suspended profile: never shown publicly
            $spam = (bool) preg_match('#https?://|<a\s|www\.#i', $r->firstname . ' ' . $r->lastname . ' ' . $r->institute);
            $id = DB::table('participants')->insertGetId([
                'code' => 'MBR-' . str_pad((string) $seq, 6, '0', STR_PAD_LEFT), 'sequence' => $seq, 'status' => $spam ? 'suspended' : 'active',
                'title' => $cut($r->title, 20), 'first_name' => $cut($r->firstname, 150) ?? 'Participant', 'last_name' => $cut($r->lastname, 150),
                'designation' => $cut($r->designation ?: $r->profession, 150), 'organization' => $cut($r->institute, 190), 'department' => $cut($r->specialty, 150),
                'category' => $cut($r->profession, 100), 'country' => $cut($r->country, 100),
                'photo' => $r->image && $r->image !== 'no-photo.png' && is_file(base_path('upload/images/' . $r->image)) ? 'upload/images/' . $r->image : null,
                'email' => $valid ? $cut($email, 190) : null, 'mobile' => $cut(trim((string) $r->phone, " -"), 40),
                'visibility' => null, 'editing_enabled' => true, 'created_at' => $r->created_at ?: $now, 'updated_at' => $now,
            ]);
            if ($valid) $byEmail[$email] = $id;
            DB::table('registrations')->where('id', $r->id)->update(['participant_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('participant_id');
        });
        Schema::dropIfExists('profile_stats');
        Schema::dropIfExists('participants');
    }
};
