<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Website pages and their content sections, managed from the admin panel (Website > Pages).
     */
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            // Built-in pages (home, gallery, ...) keep their address and cannot be deleted
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('page_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('label')->nullable();
            $table->longText('content')->nullable();
            $table->integer('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['page_id', 'sort']);
        });

        // Fill with the content the site already shows, so nothing changes visually
        \App\Support\PageSeed::run();
    }

    public function down(): void
    {
        Schema::dropIfExists('page_sections');
        Schema::dropIfExists('pages');
    }
};
