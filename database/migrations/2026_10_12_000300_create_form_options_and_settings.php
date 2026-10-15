<?php

use App\Models\Page;
use App\Support\SectionTypes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - form_options: every drop-down on the registration form (Admin > Form Options)
     * - settings: event & registration settings (Admin > Event & Registration)
     * - adds the "Event countdown" section to the home page
     * Old tables (professions, specialities, dropdownvalue) are only read, never changed.
     */
    public function up(): void
    {
        Schema::create('form_options', function (Blueprint $table) {
            $table->id();
            $table->string('list', 20);            // title | profession | speciality | id_type | country
            $table->foreignId('parent_id')->nullable()->constrained('form_options')->cascadeOnDelete(); // speciality -> profession
            $table->string('label', 150);
            $table->string('extra', 50)->nullable(); // country dial code
            $table->integer('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['list', 'parent_id', 'sort']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $now = now();
        $add = function (string $list, string $label, ?string $extra = null, ?int $parent = null, int $sort = 0) use ($now) {
            return DB::table('form_options')->insertGetId([
                'list' => $list, 'parent_id' => $parent, 'label' => trim($label), 'extra' => $extra,
                'sort' => $sort, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        };

        // Titles (the ones the form offered)
        foreach (['Dr', 'Prof', 'Mr', 'Mrs', 'Ms'] as $i => $t) $add('title', $t, null, null, $i + 1);

        // Professions + their specialities (from the existing tables)
        $professions = Schema::hasTable('professions') ? DB::table('professions')->orderBy('id')->get() : collect();
        foreach ($professions as $i => $p) {
            $pid = $add('profession', $p->profession, null, null, $i + 1);
            $specs = Schema::hasTable('specialities') ? DB::table('specialities')->where('profession_id', $p->id)->orderBy('id')->get() : collect();
            foreach ($specs as $j => $s) $add('speciality', $s->speciality, null, $pid, $j + 1);
        }

        // ID types and countries (from dropdownvalue)
        if (Schema::hasTable('dropdownvalue')) {
            $rows = DB::table('dropdownvalue')->orderBy('id')->get();
            $sort = 0;
            foreach ($rows->pluck('identification_type')->filter()->map('trim')->unique() as $type) $add('id_type', $type, null, null, ++$sort);
            $sort = 0;
            foreach ($rows as $r) {
                if (!trim((string) $r->countryName)) continue;
                $add('country', $r->countryName, $r->dialCode ? '+' . ltrim(trim($r->dialCode), '+') : null, null, ++$sort);
            }
        }

        // Event settings (start from the values the site used so far)
        $settings = [
            'event_name' => config('bics.event_name'),
            'event_short' => config('bics.event_short'),
            'event_venue' => config('bics.event_venue'),
            'event_timezone' => 'Europe/Madrid',
            'event_start' => '2026-10-28 09:00',
            'event_end' => '',
            'registration_opens' => '',
            'registration_closes' => '',
            'registration_closed_message' => 'Registration for this event is now closed. Thank you for your interest — see you at the summit!',
            'registration_upcoming_message' => 'Please come back when the countdown ends — we look forward to welcoming you.',
            'venue_code' => config('bics.venue'),
            'tracking_no' => config('bics.tracking_no'),
        ];
        foreach ($settings as $key => $value) {
            DB::table('settings')->insert(['key' => $key, 'value' => $value, 'created_at' => $now, 'updated_at' => $now]);
        }

        // Home page: event countdown right after the slider
        $home = DB::table('pages')->where('slug', 'home')->value('id');
        if ($home && !DB::table('page_sections')->where('page_id', $home)->where('type', 'event_countdown')->exists()) {
            $sliderSort = (int) DB::table('page_sections')->where('page_id', $home)->where('type', 'slider')->min('sort');
            DB::table('page_sections')->where('page_id', $home)->where('sort', '>', $sliderSort)->increment('sort');
            DB::table('page_sections')->insert([
                'page_id' => $home, 'type' => 'event_countdown', 'label' => 'Event countdown',
                'content' => json_encode(SectionTypes::clean('event_countdown', SectionTypes::get('event_countdown')['defaults'])),
                'sort' => $sliderSort + 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('page_sections')->where('type', 'event_countdown')->delete();
        Schema::dropIfExists('settings');
        Schema::dropIfExists('form_options');
    }
};
