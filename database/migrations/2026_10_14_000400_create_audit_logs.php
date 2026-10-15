<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reports & admin (docs/NFC-PROFILE-PLAN.md, phase 6): audit log of admin actions — every change made in the admin
 * area (who, what, which record, the fields sent) and admin sign-ins / sign-outs / failed sign-ins.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name', 120)->nullable();   // kept when the user is deleted
            $table->string('area', 40)->index();             // participants, nfc, raffle, auth…
            $table->string('action', 120);                   // readable: "Edited participant"
            $table->string('route', 120)->nullable();
            $table->string('method', 10)->nullable();
            $table->string('subject', 120)->nullable();      // e.g. "participant #12"
            $table->json('details')->nullable();             // fields sent (secrets removed)
            $table->unsignedSmallInteger('status')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
