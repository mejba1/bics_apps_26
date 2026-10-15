<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website › Documents: PDFs shown at their own short address (e.g. /Itenary-2026). The file can be replaced and the
 * link stays the same. Files live in storage/app/documents (not directly reachable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();          // as typed (e.g. "Itenary-2026"); matched without regard to case
            $table->string('title', 160);
            $table->string('description', 500)->nullable();
            $table->string('file', 255)->nullable();        // storage/app/documents/…
            $table->string('original_name', 255)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('allow_download')->default(true);
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('downloads')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('file_updated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
