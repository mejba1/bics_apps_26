<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Files attached to the registration confirmation email (Admin > Event & Registration).
 * Starts with the invitation card that used to be hard-coded in EmailNotification.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('path');              // relative to the project root, e.g. upload/email-attachments/itinerary-1a2b.pdf
            $table->string('original_name')->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->string('mime', 100)->default('application/pdf');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $old = base_path('assets/frontend/images/BICS-Invitaion-Card-2026.pdf');
        if (is_file($old)) {
            File::ensureDirectoryExists(base_path('upload/email-attachments'));
            $path = 'upload/email-attachments/invitation-card-2026.pdf';
            File::copy($old, base_path($path));
            DB::table('email_attachments')->insert([
                'title' => 'Invitation card', 'path' => $path, 'original_name' => 'BICS-Invitaion-Card-2026.pdf',
                'size' => filesize($old), 'mime' => 'application/pdf', 'is_active' => true, 'sort_order' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_attachments');
    }
};
