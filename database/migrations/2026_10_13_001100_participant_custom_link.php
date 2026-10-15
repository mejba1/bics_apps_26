<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional custom link name per participant, e.g. /p/dr-nadia-karim (for sharing).
 * The permanent /p/MBR-000001 keeps working; QR codes and NFC chips always use the permanent link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->string('slug', 60)->nullable()->unique()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
