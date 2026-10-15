<?php

use App\Support\BirthDate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Participant dashboard, part 2: optional password (besides the emailed code), date of birth (private, for the age
 * shown on the dashboard — filled from the latest registration), "sign out other devices" and a sign-in history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
            $table->timestamp('password_changed_at')->nullable()->after('password');
            $table->unsignedInteger('session_version')->default(1)->after('password_changed_at');
            $table->date('birth_date')->nullable()->after('country');
        });
        Schema::create('participant_logins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('participants')->cascadeOnDelete();
            $table->string('method', 20);                 // link | code | password
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['participant_id', 'created_at']);
        });

        // date of birth from each participant's latest registration that has one
        $rows = DB::table('registrations')->whereNotNull('participant_id')->whereNotNull('birthday')->where('birthday', '!=', '')
            ->orderBy('id')->get(['participant_id', 'birthday']);
        $dates = [];
        foreach ($rows as $r) if ($d = BirthDate::parse($r->birthday)) $dates[$r->participant_id] = $d;
        foreach ($dates as $id => $d) DB::table('participants')->where('id', $id)->update(['birth_date' => $d]);
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_logins');
        Schema::table('participants', fn (Blueprint $table) => $table->dropColumn(['password', 'password_changed_at', 'session_version', 'birth_date']));
    }
};
