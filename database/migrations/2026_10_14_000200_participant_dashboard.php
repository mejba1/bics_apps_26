<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Participant dashboard (docs/NFC-PROFILE-PLAN.md, phase 5): sign-in by emailed link or code (no passwords),
 * and a history of profile changes (who changed what: the participant or an admin).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participant_login_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('participants')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();   // sha256 of the link token
            $table->string('code_hash', 64);              // sha256 of the 6-digit code (+ token, so codes are not guessable across rows)
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['participant_id', 'created_at']);
        });
        Schema::create('participant_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('participants')->cascadeOnDelete();
            $table->string('source', 20);                 // participant | admin
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('changes');                      // {field: [old, new]}
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['participant_id', 'created_at']);
        });
        Schema::table('participants', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable()->after('locked_fields');
        });
    }

    public function down(): void
    {
        Schema::table('participants', fn (Blueprint $table) => $table->dropColumn('last_login_at'));
        Schema::dropIfExists('participant_changes');
        Schema::dropIfExists('participant_login_codes');
    }
};
