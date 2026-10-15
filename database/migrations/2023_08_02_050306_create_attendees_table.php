<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('register_id');
            $table->foreign('register_id')->references('id')->on('registrations')->onDelete('cascade');
            $table->string('venue')->nullable();
            $table->string('trackingNo')->nullable();
            $table->string('onbehalf')->nullable();
            $table->string('qr_code')->nullable();
            $table->string('qr_url')->nullable();
            $table->string('qr_img_path')->nullable();
            $table->enum('email_notification', ['Yes', 'No'])->default('No');
            $table->string('ip')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('apply_date')->nullable();
            $table->enum('gift', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->string('verify_at')->nullable();
            $table->enum('verify_status', ['Yes', 'No'])->default('No');
            $table->enum('winner_status', ['Yes', 'No'])->default('No');
            $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Approved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendees');
    }
};
