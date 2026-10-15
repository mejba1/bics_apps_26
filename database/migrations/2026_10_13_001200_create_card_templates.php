<?php

use App\Support\CardPresets;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Card Design Studio (docs/NFC-PROFILE-PLAN.md, phase 3): card templates (front / back layers in mm) and their
 * saved versions. Two starter designs are added; the portrait badge is the default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();   // default for this event
            $table->string('category', 100)->nullable();                                        // … and this participant category
            $table->boolean('is_default')->default(false);
            $table->json('design');
            $table->unsignedInteger('version')->default(1);
            $table->string('thumbnail', 255)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('card_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('card_templates')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('name', 120);
            $table->json('design');
            $table->string('note', 200)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['template_id', 'version']);
        });

        $now = now();
        foreach (CardPresets::all() as $key => [$name, $design]) {
            $id = DB::table('card_templates')->insertGetId(['name' => 'BICS ' . $name, 'is_default' => $key === 'badge', 'design' => json_encode($design),
                'version' => 1, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('card_template_versions')->insert(['template_id' => $id, 'version' => 1, 'name' => 'BICS ' . $name, 'design' => json_encode($design),
                'note' => 'Starter design', 'created_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('card_template_versions');
        Schema::dropIfExists('card_templates');
    }
};
