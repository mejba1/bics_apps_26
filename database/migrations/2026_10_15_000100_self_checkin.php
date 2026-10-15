<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Check-in (Attendee Activities › Self Check-in): one row per checked-in registration — how (self on a phone, kiosk,
 * help desk) and when. registrations.verify_status / verify_at stay in sync for the older pages and reports.
 * Existing check-ins are copied in. Settings per event live in the settings table ("checkin.{event_id}").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->nullable()->index();
            $table->unsignedBigInteger('registration_id')->unique();
            $table->unsignedBigInteger('participant_id')->nullable()->index();
            $table->string('method', 10)->default('self');       // self | kiosk | desk
            $table->string('ip', 45)->nullable();
            $table->string('device', 64)->nullable();            // hash of the browser, to spot one phone checking in many people
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedInteger('distance_m')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();   // staff member (help desk)
            $table->timestamps();
            $table->index(['event_id', 'created_at']);
        });

        // check-ins made before this table existed
        $rows = DB::table('registrations')->where('verify_status', 'Yes')->get(['id', 'event_id', 'participant_id', 'verify_at', 'created_at']);
        foreach ($rows as $r) {
            $at = null;
            try { $at = $r->verify_at ? \Carbon\Carbon::createFromFormat('d-m-Y h:i:sa', $r->verify_at) : null; } catch (\Throwable $e) { $at = null; }
            DB::table('checkins')->insertOrIgnore(['event_id' => $r->event_id, 'registration_id' => $r->id, 'participant_id' => $r->participant_id,
                'method' => 'desk', 'created_at' => $at ?: $r->created_at, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('checkins');
    }
};
