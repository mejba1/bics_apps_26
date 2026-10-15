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
        Schema::create('verify_status', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('register_id');
            $table->foreign('register_id')->references('id')->on('registrations')->onDelete('cascade');
            $table->string('qr_code')->nullable();
            $table->enum('verify_status', ['Yes', 'No'])->default('No');
            $table->string('verify_ip')->nullable();
            $table->string('verify_country')->nullable();
            $table->string('verify_city')->nullable();
            $table->string('verify_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verify_status');
    }
};
