<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Winner email (Raffle setup > Winner email): when it was sent, or why it failed. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('winners', function (Blueprint $table) {
            $table->timestamp('emailed_at')->nullable()->after('drawn_by');
            $table->string('email_error', 255)->nullable()->after('emailed_at');
        });
    }

    public function down(): void
    {
        Schema::table('winners', function (Blueprint $table) {
            $table->dropColumn(['emailed_at', 'email_error']);
        });
    }
};
