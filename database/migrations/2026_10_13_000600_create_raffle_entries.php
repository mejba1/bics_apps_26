<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raffle "my list": registration numbers allowed to win (typed in one by one, pasted, or imported from Excel / CSV).
 * Used when "Who can win" is set to "Only the numbers on my list".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raffle_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('number', 20);
            $table->string('source', 20)->default('manual'); // manual | paste | import
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raffle_entries');
    }
};
