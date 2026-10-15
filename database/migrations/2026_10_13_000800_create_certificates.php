<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Participation certificates (Admin > Certificates), one per person per event.
 * The design and email texts live in "settings" (certificate.design.{event}, certificate.email.{event}).
 * token: secret part of the public link / QR code (/certificate/{token}).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained('registrations')->cascadeOnDelete();
            $table->string('number', 40)->unique();      // e.g. BICS2026-000123
            $table->unsignedInteger('sequence');           // 123
            $table->string('token', 40)->unique();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->string('email_error', 255)->nullable();
            $table->unsignedInteger('downloads')->default(0);
            $table->timestamps();
            $table->unique(['event_id', 'registration_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
