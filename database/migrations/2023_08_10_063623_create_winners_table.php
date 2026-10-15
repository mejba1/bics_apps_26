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
        Schema::create('winners', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('register_id');
            $table->foreign('register_id')->references('id')->on('registrations'); 
            $table->string('winners_venue')->nullable();
            $table->string('winners_name')->nullable();
            $table->string('winners_designation')->nullable();
            $table->string('winners_country')->nullable();
            $table->string('winners_number')->nullable();
            $table->string('prize')->nullable();
            $table->string('prize_type')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('winners');
    }
};
