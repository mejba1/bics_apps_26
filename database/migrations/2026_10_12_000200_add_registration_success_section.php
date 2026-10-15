<?php

use App\Models\Page;
use App\Support\PageSeed;
use App\Support\SectionTypes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Adds the editable "Registration success message" to Admin > Website Pages > Site-wide.
     * Only inserts a row; does nothing if it already exists (fresh installs get it from PageSeed).
     */
    public function up(): void
    {
        $pageId = DB::table('pages')->where('slug', Page::GLOBAL_SLUG)->value('id');
        // Keep the admin name in step with what the page now holds (only if it was never renamed)
        DB::table('pages')->where('slug', Page::GLOBAL_SLUG)->where('title', 'Site-wide (footer & event sidebar)')
            ->update(['title' => 'Site-wide (footer, event sidebar, registration message)']);
        if (!$pageId || DB::table('page_sections')->where('page_id', $pageId)->where('type', 'registration_success')->exists()) {
            return;
        }
        DB::table('page_sections')->insert([
            'page_id' => $pageId,
            'type' => 'registration_success',
            'label' => 'Registration success message',
            'content' => json_encode(SectionTypes::clean('registration_success', PageSeed::registrationSuccess())),
            'sort' => (int) DB::table('page_sections')->where('page_id', $pageId)->max('sort') + 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('page_sections')->where('type', 'registration_success')->delete();
    }
};
