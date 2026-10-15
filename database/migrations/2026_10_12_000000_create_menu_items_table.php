<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Frontend navigation menu, managed from the admin panel (Website > Menu).
     */
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('title');
            $table->string('url')->default('#');
            $table->integer('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('new_tab')->default(false);
            $table->timestamps();
        });

        // Seed with the menu the site already had, so nothing changes visually
        $now = now();
        foreach (\App\Models\MenuItem::defaults() as $i => $item) {
            $parentId = DB::table('menu_items')->insertGetId([
                'title' => $item['title'], 'url' => $item['url'], 'sort' => $i + 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ($item['children'] as $j => $child) {
                DB::table('menu_items')->insert([
                    'parent_id' => $parentId, 'title' => $child['title'], 'url' => $child['url'], 'sort' => $j + 1,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
