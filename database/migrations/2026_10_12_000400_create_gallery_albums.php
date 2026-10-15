<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Photo albums for the gallery (Admin > Gallery): create an album title, then upload its photos.
     * Moves the photos that were in the gallery page section into albums (same order, same files).
     */
    public function up(): void
    {
        Schema::create('gallery_albums', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->integer('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('gallery_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->constrained('gallery_albums')->cascadeOnDelete();
            $table->string('image');
            $table->string('caption', 255)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->integer('sort')->default(0);
            $table->timestamps();
            $table->index(['album_id', 'sort']);
        });

        // Existing gallery section: filters -> albums, images -> photos
        $now = now();
        foreach (DB::table('page_sections')->where('type', 'gallery')->get() as $section) {
            $content = json_decode($section->content ?? '[]', true) ?: [];
            // Fresh install: the section was saved without its photo list, so use the original photos
            if (empty($content['images'])) {
                $content = array_merge($content, array_intersect_key(\App\Support\PageSeed::gallery(), array_flip(['filters', 'images'])));
            }
            $albumIds = [];
            foreach ($content['filters'] ?? [] as $i => $filter) {
                if (empty($filter['key']) || isset($albumIds[$filter['key']])) continue;
                $albumIds[$filter['key']] = DB::table('gallery_albums')->insertGetId([
                    'title' => $filter['label'] ?: $filter['key'], 'sort' => $i + 1, 'is_active' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            $sort = [];
            foreach ($content['images'] ?? [] as $img) {
                if (empty($img['image'])) continue;
                $key = $img['group'] ?? '';
                if (!isset($albumIds[$key])) {
                    $albumIds[$key] = DB::table('gallery_albums')->insertGetId([
                        'title' => $key ?: 'Photos', 'sort' => count($albumIds) + 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
                $album = $albumIds[$key];
                $size = @getimagesize(base_path(ltrim($img['image'], '/')));
                DB::table('gallery_photos')->insert([
                    'album_id' => $album, 'image' => $img['image'], 'caption' => $img['alt'] ?? null,
                    'width' => $size[0] ?? null, 'height' => $size[1] ?? null,
                    'sort' => $sort[$album] = ($sort[$album] ?? 0) + 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            // The section now only keeps its title / subtitle / layout; photos live in the albums
            unset($content['filters'], $content['images']);
            DB::table('page_sections')->where('id', $section->id)->update(['content' => json_encode($content), 'updated_at' => $now]);
            break; // one gallery section on the site
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_photos');
        Schema::dropIfExists('gallery_albums');
    }
};
