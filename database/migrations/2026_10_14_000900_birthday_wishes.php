<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Birthday wishes (Admin › Engagement › Birthdays): card designs, the greetings sent, and a per-person
 * "no birthday wishes" switch. Settings live in the settings table under "birthdays.*".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->boolean('birthday_wishes')->default(true)->after('birth_date');
        });

        Schema::create('birthday_cards', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('kind', 20)->default('preset');      // preset | upload
            $table->string('preset', 40)->nullable();           // confetti | balloons | elegant | bloom
            $table->string('image', 255)->nullable();           // uploaded artwork (kind = upload)
            $table->json('options')->nullable();                // headline, colours, text position …
            $table->boolean('is_default')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('birthday_greetings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('participant_id')->index();
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('auto_year')->nullable();   // = year for the automatic wish (one per person per year)
            $table->unsignedBigInteger('card_id')->nullable();
            $table->string('mode', 10)->default('auto');               // auto | manual | test
            $table->string('email', 190)->nullable();
            $table->string('subject', 200)->nullable();
            $table->text('message')->nullable();
            $table->json('card_options')->nullable();                  // what a custom card changed
            $table->string('status', 10)->default('sent');             // sent | failed | skipped
            $table->string('error', 255)->nullable();
            $table->unsignedBigInteger('sent_by')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['participant_id', 'auto_year']);
            $table->index(['year', 'status']);
        });

        $now = now();
        $presets = [
            ['Confetti night', 'confetti', true],
            ['Balloons', 'balloons', false],
            ['Gold & navy', 'elegant', false],
            ['Bloom', 'bloom', false],
        ];
        foreach ($presets as [$name, $preset, $default]) {
            DB::table('birthday_cards')->insert(['name' => $name, 'kind' => 'preset', 'preset' => $preset, 'options' => json_encode([]),
                'is_default' => $default, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('birthday_greetings');
        Schema::dropIfExists('birthday_cards');
        Schema::table('participants', fn (Blueprint $table) => $table->dropColumn('birthday_wishes'));
    }
};
