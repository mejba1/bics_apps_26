<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bulk card printing (docs/NFC-PROFILE-PLAN.md, phase 4): every print / export run is kept with the participants,
 * the template version each one got and the files made, so a batch can be downloaded again, marked printed or reprinted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_print_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->nullable();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('card_templates')->nullOnDelete();   // null = each participant's own
            $table->json('settings');                       // layout, paper, sides, bleed, crop marks, gap, flip, outputs
            $table->unsignedInteger('participant_count')->default(0);
            $table->unsignedInteger('page_count')->default(0);
            $table->string('status', 20)->default('ready');  // ready | failed
            $table->json('files')->nullable();               // {pdf, zip, csv} paths in storage/app
            $table->text('notes')->nullable();               // warnings while making the files (missing fonts…)
            $table->timestamp('printed_at')->nullable();
            $table->foreignId('printed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('card_print_job_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('card_print_jobs')->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained('participants')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('card_templates')->nullOnDelete();
            $table->unsignedInteger('template_version')->nullable();
            $table->foreignId('nfc_card_id')->nullable()->constrained('nfc_cards')->nullOnDelete();
            $table->unsignedInteger('position');             // order in the file
            $table->string('warnings', 500)->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->index(['participant_id', 'printed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_print_job_items');
        Schema::dropIfExists('card_print_jobs');
    }
};
