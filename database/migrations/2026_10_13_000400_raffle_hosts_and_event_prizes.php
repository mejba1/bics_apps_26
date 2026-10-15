<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Raffle per event:
 * - prizes and winners belong to an event (event_id, filled from their venue code);
 * - prizes have a draw order and remember who won them;
 * - raffle_hosts: the people assigned to run the draw of an event (nobody else can start it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prizes', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('id')->constrained('events')->nullOnDelete();
            $table->unsignedInteger('sort')->default(0)->after('prize_status');
            $table->foreignId('winner_id')->nullable()->after('sort')->constrained('winners')->nullOnDelete();
        });
        Schema::table('winners', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('id')->constrained('events')->nullOnDelete();
            $table->foreignId('drawn_by')->nullable()->after('prize_type')->constrained('users')->nullOnDelete();
        });
        Schema::create('raffle_hosts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });

        DB::statement('UPDATE prizes p JOIN events e ON e.venue_code = p.prize_venue SET p.event_id = e.id');
        DB::statement('UPDATE winners w JOIN events e ON e.venue_code = w.winners_venue SET w.event_id = e.id');
        // Old draw order: the prize added last was drawn first
        foreach (DB::table('prizes')->orderByDesc('id')->get(['id', 'prize_venue']) ->groupBy('prize_venue') as $rows) {
            foreach ($rows->values() as $i => $p) DB::table('prizes')->where('id', $p->id)->update(['sort' => $i + 1]);
        }
        // Link awarded prizes to their winner (same event and prize type)
        foreach (DB::table('winners')->get() as $w) {
            DB::table('prizes')->where('prize_venue', $w->winners_venue)->where('prize_type', $w->prize_type)->whereNull('winner_id')
                ->limit(1)->update(['winner_id' => $w->id, 'prize_status' => 'Yes']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('raffle_hosts');
        Schema::table('winners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('drawn_by');
            $table->dropConstrainedForeignId('event_id');
        });
        Schema::table('prizes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('winner_id');
            $table->dropColumn('sort');
            $table->dropConstrainedForeignId('event_id');
        });
    }
};
