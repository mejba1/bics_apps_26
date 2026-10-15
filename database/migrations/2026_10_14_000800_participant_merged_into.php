<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A duplicate profile merged into another keeps pointing there, so its old link / QR code still opens the right person. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->foreignId('merged_into_id')->nullable()->after('status')->constrained('participants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('participants', fn (Blueprint $table) => $table->dropConstrainedForeignId('merged_into_id'));
    }
};
