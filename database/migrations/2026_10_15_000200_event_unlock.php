<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ended / archived events are read-only (App\Support\EventLock). A Super Admin can unlock one for a while to correct
 * something; these columns hold until when, and who did it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('unlocked_until')->nullable()->after('status');
            $table->unsignedBigInteger('unlocked_by')->nullable()->after('unlocked_until');
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn(['unlocked_until', 'unlocked_by']));
    }
};
