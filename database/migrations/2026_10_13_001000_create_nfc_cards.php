<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NFC cards (docs/NFC-PROFILE-PLAN.md, phase 2).
 * Each card stores its own short link /n/{token} in its NDEF record. BICS resolves it through the assignment
 * below, so a lost card can be switched off and a card can be reassigned without rewriting the chip.
 * The chip UID is optional extra information, never the security mechanism.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nfc_cards', function (Blueprint $table) {
            $table->id();
            $table->string('card_no', 30)->unique();                 // NFC-000001 (printed / written on the card)
            $table->unsignedInteger('sequence')->unique();
            $table->string('token', 16)->unique();                   // the /n/{token} link written to the chip
            $table->string('uid', 40)->nullable()->unique();         // chip serial, when the device can read it
            $table->foreignId('participant_id')->nullable()->constrained('participants')->nullOnDelete();
            // lifecycle: available (in stock) → assigned → active; or lost / damaged / deactivated
            $table->enum('status', ['available', 'assigned', 'active', 'lost', 'damaged', 'deactivated'])->default('available');
            $table->enum('encode_status', ['not_encoded', 'encoded', 'failed'])->default('not_encoded');
            $table->enum('verify_status', ['not_verified', 'verified', 'mismatch'])->default('not_verified');
            $table->enum('print_status', ['not_printed', 'printed'])->default('not_printed');
            $table->string('url_written', 255)->nullable();
            $table->boolean('locked')->default(false);              // chip made read-only
            $table->timestamp('encoded_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->foreignId('replaces_card_id')->nullable()->constrained('nfc_cards')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->index(['participant_id', 'status']);
        });

        Schema::create('nfc_card_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('nfc_cards')->cascadeOnDelete();
            $table->foreignId('participant_id')->nullable()->constrained('participants')->nullOnDelete();
            $table->string('action', 40);
            $table->string('details', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index(['card_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfc_card_logs');
        Schema::dropIfExists('nfc_cards');
    }
};
