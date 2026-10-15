<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ID cards are shown on the participant dashboard (/my → My events) only once an admin assigns them
 * (Registration › ID Cards, or the participant's admin page) — per registration, so per event / venue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->timestamp('idcard_assigned_at')->nullable()->after('status');
            $table->foreignId('idcard_assigned_by')->nullable()->after('idcard_assigned_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('idcard_assigned_by');
            $table->dropColumn('idcard_assigned_at');
        });
    }
};
