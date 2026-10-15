<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every number on a raffle "my list" can win, registered or not:
 * - list entries can carry a name, email and country (from the imported file);
 * - a winner can be a list entry without a registration (winners.register_id becomes optional, winners.entry_id added).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raffle_entries', function (Blueprint $table) {
            $table->string('name', 150)->nullable()->after('number');
            $table->string('email', 190)->nullable()->after('name');
            $table->string('country', 100)->nullable()->after('email');
        });

        Schema::table('winners', function (Blueprint $table) {
            $table->dropForeign('winners_register_id_foreign');
        });
        DB::statement('ALTER TABLE winners MODIFY register_id BIGINT UNSIGNED NULL');
        Schema::table('winners', function (Blueprint $table) {
            $table->foreign('register_id')->references('id')->on('registrations')->nullOnDelete();
            $table->foreignId('entry_id')->nullable()->after('register_id')->constrained('raffle_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('winners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entry_id');
            $table->dropForeign(['register_id']);
        });
        DB::table('winners')->whereNull('register_id')->delete(); // winners that were list numbers only
        DB::statement('ALTER TABLE winners MODIFY register_id BIGINT UNSIGNED NOT NULL');
        Schema::table('winners', function (Blueprint $table) {
            $table->foreign('register_id')->references('id')->on('registrations');
        });
        Schema::table('raffle_entries', function (Blueprint $table) {
            $table->dropColumn(['name', 'email', 'country']);
        });
    }
};
