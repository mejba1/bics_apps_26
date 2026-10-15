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
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->string('venue')->nullable();
            $table->string('title')->nullable();
            $table->string('trackingNo')->nullable();
            $table->string('onbehalf')->nullable();
            $table->string('country')->nullable();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('birthday')->nullable();
            $table->string('gender')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('profession')->nullable();
            $table->string('specialty')->nullable();
            $table->string('pharmacist')->nullable();
            $table->string('designation')->nullable();
            $table->string('institute')->nullable();
            $table->string('institute_address')->nullable();
            $table->string('institute_phone')->nullable();
            $table->string('identification_type')->nullable();
            $table->string('identification_no')->nullable();
            $table->string('image')->nullable();
            $table->string('qr_code')->nullable();
            $table->string('qr_img')->nullable();
            $table->string('qr_url')->nullable();
            $table->string('verify_at')->nullable();
            $table->enum('verify_status', ['Yes', 'No'])->default('No');
            $table->enum('email_notification', ['Yes', 'No'])->default('No');
            $table->string('qr_img_path')->nullable();
            $table->enum('gift', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Approved');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
