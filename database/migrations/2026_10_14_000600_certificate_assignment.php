<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Certificates are shown on the participant dashboard (/my → My events) only once assigned by an admin
 * (Certificates › Participants › Assign), or when emailed. Admin previews / downloads no longer make them visible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->timestamp('assigned_at')->nullable()->after('issued_at');
            $table->foreignId('assigned_by')->nullable()->after('assigned_at')->constrained('users')->nullOnDelete();
        });
        // certificates already emailed were given to the participant
        DB::table('certificates')->whereNotNull('emailed_at')->update(['assigned_at' => DB::raw('emailed_at')]);
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_by');
            $table->dropColumn('assigned_at');
        });
    }
};
