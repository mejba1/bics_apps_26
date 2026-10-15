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
        Schema::create('gift', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->nullable();
            $table->string('reg_id')->nullable();
            $table->string('ip')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('create_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gift');
    }
};
