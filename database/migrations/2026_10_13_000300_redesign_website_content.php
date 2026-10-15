<?php

use App\Support\SectionTypes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Website redesign (BICS theme):
 * - contact_messages table for the working contact form;
 * - Home, BICS Overview, Upcoming BICS and Contact rebuilt with the new event sections;
 * - menu reorganised (dead "#" links switched off, real links to page sections added).
 * Old sections and menu items are only switched off, never deleted, so down() can bring them back.
 * Everything stays editable in Admin > Website Pages and Admin > Website Menu.
 */
return new class extends Migration
{
    private const STATE_KEY = 'redesign.2026-10-13';

    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('email', 190);
            $table->string('country', 100)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('subject', 120);
            $table->text('message');
            $table->string('ip', 45)->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        $state = ['added_sections' => [], 'disabled_sections' => [], 'changed_sections' => [], 'added_menu' => [], 'disabled_menu' => [], 'changed_menu' => []];
        $now = now();
        $pages = DB::table('pages')->pluck('id', 'slug');
        $gal = 'assets/frontend/images/gallery/';
        // Original order, so down() can put it back
        $state['sorts'] = DB::table('page_sections')->pluck('sort', 'id')->all();

        $add = function (string $slug, string $type, string $label, array $content) use (&$state, $pages, $now) {
            if (!isset($pages[$slug])) return;
            $sort = (int) DB::table('page_sections')->where('page_id', $pages[$slug])->max('sort') + 1;
            $state['added_sections'][] = DB::table('page_sections')->insertGetId([
                'page_id' => $pages[$slug], 'type' => $type, 'label' => $label,
                'content' => json_encode(SectionTypes::clean($type, $content + (SectionTypes::get($type)['defaults'] ?? []))),
                'sort' => $sort, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        };
        $disable = function (string $slug, array $types) use (&$state, $pages) {
            if (!isset($pages[$slug])) return;
            $ids = DB::table('page_sections')->where('page_id', $pages[$slug])->whereIn('type', $types)->where('is_active', true)->pluck('id')->all();
            DB::table('page_sections')->whereIn('id', $ids)->update(['is_active' => false]);
            $state['disabled_sections'] = array_merge($state['disabled_sections'], $ids);
        };
        // Put the given section ids first (in this order), the rest after
        $order = function (string $slug, array $ids) use ($pages) {
            if (!isset($pages[$slug])) return;
            $rest = DB::table('page_sections')->where('page_id', $pages[$slug])->whereNotIn('id', $ids)->orderBy('sort')->pluck('id')->all();
            foreach (array_merge($ids, $rest) as $i => $id) DB::table('page_sections')->where('id', $id)->update(['sort' => $i + 1]);
        };
        $last = function () use (&$state) { return end($state['added_sections']); };

        $objectives = [
            ['text' => 'Creating an open platform to discuss the best practices in cancer treatment'],
            ['text' => 'Sharing knowledge and experience of using advanced medications in cancer management'],
            ['text' => 'Capacity building in cancer care through networking & collaboration'],
            ['text' => 'Building awareness for early detection of cancer'],
        ];
        $focus = [
            ['icon' => 'bi-clipboard2-pulse', 'title' => 'Best practice in treatment', 'text' => 'An open platform for oncologists and care teams to discuss what works in cancer treatment today.'],
            ['icon' => 'bi-capsule', 'title' => 'Advanced therapies', 'text' => 'Knowledge and real-world experience of using advanced medications in cancer management.'],
            ['icon' => 'bi-people', 'title' => 'Networking & capacity building', 'text' => 'Building cancer care capacity through collaboration between professionals and institutions.'],
            ['icon' => 'bi-search-heart', 'title' => 'Early detection', 'text' => 'Raising awareness of early detection — one of the most effective ways to improve outcomes.'],
        ];
        $past = [
            ['image' => $gal . 'bics-tanzania-13.jpg', 'year' => '2021', 'edition' => 'BICS 2021', 'city' => 'Tanzania', 'theme' => 'Cancer Care in the Era of Advanced Therapeutics', 'link' => 'gallery', 'upcoming' => false],
            ['image' => $gal . 'Bics2023-2.jpg', 'year' => '2023', 'edition' => 'BICS 2023', 'city' => 'Algeria', 'theme' => '', 'link' => 'gallery', 'upcoming' => false],
            ['image' => '', 'year' => '2024', 'edition' => '3rd BICS', 'city' => 'Palma de Mallorca, Spain', 'theme' => 'Conquering Cancer With Advanced Therapies', 'link' => '', 'upcoming' => false],
            ['image' => '', 'year' => '2026', 'edition' => '4th BICS · Next', 'city' => 'Valencia, Spain', 'theme' => 'Join us for the next summit.', 'link' => 'registration', 'upcoming' => true],
        ];
        $faq = [
            ['question' => 'Who can attend BICS?', 'answer' => 'BICS is a summit for cancer care professionals — oncologists, doctors, pharmacists, nurses, researchers and others working in cancer care. Choose your profession and speciality on the registration form.'],
            ['question' => 'How do I register?', 'answer' => 'Fill in the online registration form. It takes a few minutes. When you finish you get a registration number on screen and a confirmation email with your personal ID card.'],
            ['question' => 'I did not receive the confirmation email. What should I do?', 'answer' => 'Check your spam or junk folder first. If it is not there, contact us with the name and email address you registered with and we will send it again.'],
            ['question' => 'What should I bring on the day?', 'answer' => 'Bring your registration number or the QR code from your confirmation email (on your phone or printed). The registration desk scans it to check you in quickly.'],
            ['question' => 'Can I register for more than one summit?', 'answer' => 'Yes. You can register for each BICS summit with the same email address — one registration per person per summit.'],
            ['question' => 'Where can I see photos from previous summits?', 'answer' => 'Visit the Gallery for photos from earlier BICS summits.'],
        ];
        $venueInfo = [
            ['icon' => 'bi-qr-code-scan', 'title' => 'Fast check-in', 'text' => 'Show the QR code from your confirmation email at the registration desk.'],
            ['icon' => 'bi-person-badge', 'title' => 'Your ID card', 'text' => 'Your personal ID card (PDF) is linked in your confirmation email.'],
            ['icon' => 'bi-headset', 'title' => 'Need help?', 'text' => 'Contact the BICS team for any question about your visit.'],
        ];
        $stats = [
            ['value' => '4', 'label' => 'Editions of BICS', 'icon' => 'bi-award'],
            ['value' => '3', 'label' => 'Host countries so far', 'icon' => 'bi-globe2'],
            ['value' => '{registrations}+', 'label' => 'Professionals registered online', 'icon' => 'bi-people'],
            ['value' => '{countries}', 'label' => 'Countries represented', 'icon' => 'bi-flag'],
        ];
        $cta = ['eyebrow' => 'Stronger together', 'title' => 'Ready to join the next summit?', 'text' => 'Register online in a few minutes — your confirmation email includes your registration number, QR code and ID card.', 'button_text' => 'Register now', 'button_url' => 'registration'];

        /* ---------- Home ---------- */
        $disable('home', ['slider', 'event_countdown', 'image']);
        // An empty placeholder "Two info cards" section ("Title / Text / List") was left on the home page
        $placeholder = DB::table('page_sections')->where('page_id', $pages['home'] ?? 0)->where('type', 'about')->where('content', 'like', '%"left_title":"Title"%')->where('is_active', true)->pluck('id')->all();
        DB::table('page_sections')->whereIn('id', $placeholder)->update(['is_active' => false]);
        $state['disabled_sections'] = array_merge($state['disabled_sections'], $placeholder);

        $home = [];
        $add('home', 'hero', 'Event hero', ['kicker' => 'Stronger Together', 'title' => '', 'highlight' => 'Cancer Summit', 'image' => $gal . 'Bics2023-2.jpg',
            'theme' => 'Uniting cancer care professionals from around the world to share the latest evidence, advanced therapies and practical experience — to improve the quality of life of people living with cancer.',
            'show_countdown' => true, 'countdown_title' => 'The summit starts in', 'finished_text' => 'The summit is happening now!',
            'button1_text' => '', 'button1_url' => 'registration', 'button2_text' => 'Programme & details', 'button2_url' => 'upcomming-bics']);
        $home[] = $last();
        $add('home', 'stats', 'Key numbers', ['heading' => 'BICS at a glance', 'background' => 'dark', 'items' => $stats]);
        $home[] = $last();

        // Existing "What is BICS / Objectives" section, with an eyebrow and a working button
        $about = DB::table('page_sections')->where('page_id', $pages['home'] ?? 0)->where('type', 'about')->where('is_active', true)->orderBy('sort')->first();
        if ($about) {
            $state['changed_sections'][$about->id] = $about->content;
            $c = json_decode($about->content, true) ?: [];
            $c['eyebrow'] = 'About BICS';
            $c['anchor'] = 'about';
            if (($c['button_url'] ?? '#') === '#') { $c['button_text'] = 'Discover BICS'; $c['button_url'] = 'bics-overview'; }
            DB::table('page_sections')->where('id', $about->id)->update(['content' => json_encode(SectionTypes::clean('about', $c))]);
            $home[] = $about->id;
        }
        $add('home', 'cards', 'What the summit covers', ['eyebrow' => 'Why attend', 'heading' => 'What the summit covers', 'intro' => 'Four themes guide every BICS programme.', 'columns' => '4', 'items' => $focus, 'background' => 'soft']);
        $home[] = $last();
        $add('home', 'speakers', 'Speakers', ['eyebrow' => 'Faculty', 'heading' => 'Speakers', 'intro' => 'International experts in oncology and cancer care.', 'anchor' => 'speakers', 'items' => [],
            'empty_title' => 'Speakers to be announced', 'empty_text' => 'Our faculty of international cancer care experts will be announced here soon.']);
        $home[] = $last();
        $add('home', 'venue', 'Venue', ['eyebrow' => 'Venue', 'heading' => 'Where we meet', 'anchor' => 'venue', 'media' => 'map', 'items' => $venueInfo, 'background' => 'soft']);
        $home[] = $last();
        $add('home', 'past_events', 'Past summits', ['eyebrow' => 'Our journey', 'heading' => 'Summits so far', 'intro' => 'From Tanzania to Spain — a growing community of cancer care professionals.', 'anchor' => 'past-summits', 'items' => $past, 'background' => 'white']);
        $home[] = $last();
        $video = DB::table('page_sections')->where('page_id', $pages['home'] ?? 0)->where('type', 'video')->where('is_active', true)->first();
        if ($video) {
            $state['changed_sections'][$video->id] = $video->content;
            $c = json_decode($video->content, true) ?: [];
            $c += ['eyebrow' => 'Highlights', 'heading' => ''];
            $c['eyebrow'] = 'Highlights';
            if (trim($c['heading']) === '') $c['heading'] = 'Relive BICS Tanzania 2021';
            $c['background'] = 'soft';
            DB::table('page_sections')->where('id', $video->id)->update(['content' => json_encode(SectionTypes::clean('video', $c))]);
            $home[] = $video->id;
        }
        $add('home', 'faq', 'FAQ', ['eyebrow' => 'FAQ', 'heading' => 'Good to know', 'anchor' => 'faq', 'items' => $faq,
            'help_title' => 'Still have a question?', 'help_text' => 'The BICS team is happy to help with registration, travel or anything else.', 'button_text' => 'Contact us', 'button_url' => 'contact-us']);
        $home[] = $last();
        $add('home', 'cta', 'Register banner', $cta);
        $home[] = $last();
        $order('home', $home);

        /* ---------- BICS Overview ---------- */
        $disable('bics-overview', ['rich_text']);
        $ov = [];
        $add('bics-overview', 'page_header', 'Page header', ['eyebrow' => 'About', 'title' => 'About BICS', 'subtitle' => 'The Beacon International Cancer Summit brings cancer care professionals together to improve the quality of life of people living with cancer.', 'image' => $gal . 'bics-tanzania-13.jpg']);
        $ov[] = $last();
        $aboutContent = $about ? (json_decode($about->content, true) ?: []) : [];
        $add('bics-overview', 'about', 'What is BICS / Objectives', ['eyebrow' => 'Who we are', 'left_title' => $aboutContent['left_title'] ?? 'What is BICS?',
            'left_body' => $aboutContent['left_body'] ?? '', 'button_text' => 'Register for the next summit', 'button_url' => 'registration',
            'right_title' => $aboutContent['right_title'] ?? 'Objectives:', 'right_items' => $aboutContent['right_items'] ?? $objectives, 'anchor' => 'objectives']);
        $ov[] = $last();
        $add('bics-overview', 'cards', 'Focus areas', ['eyebrow' => 'Focus', 'heading' => 'What the summit covers', 'columns' => '4', 'items' => $focus, 'background' => 'soft']);
        $ov[] = $last();
        $add('bics-overview', 'stats', 'Key numbers', ['heading' => 'BICS at a glance', 'background' => 'dark', 'items' => $stats]);
        $ov[] = $last();
        $add('bics-overview', 'past_events', 'Past summits', ['eyebrow' => 'Our journey', 'heading' => 'Summits so far', 'anchor' => 'past-summits', 'items' => $past, 'background' => 'white']);
        $ov[] = $last();
        $add('bics-overview', 'cta', 'Register banner', $cta);
        $ov[] = $last();
        $order('bics-overview', $ov);

        /* ---------- Upcoming BICS ---------- */
        $disable('upcomming-bics', ['rich_text']);
        $up = [];
        $add('upcomming-bics', 'page_header', 'Page header', ['eyebrow' => 'Next summit', 'title' => '', 'subtitle' => 'Dates, programme, speakers and venue of the next Beacon International Cancer Summit.', 'image' => $gal . 'Bics2023-4.jpg']);
        $up[] = $last();
        $add('upcomming-bics', 'key_dates', 'Key dates', ['eyebrow' => 'Plan ahead', 'heading' => 'Key dates', 'anchor' => 'key-dates', 'auto' => true, 'items' => []]);
        $up[] = $last();
        $add('upcomming-bics', 'agenda', 'Programme', ['eyebrow' => 'Programme', 'heading' => 'Scientific programme', 'anchor' => 'programme', 'items' => [],
            'empty_text' => 'The scientific programme is being finalised and will be published here soon. Register now and we will keep you updated by email.']);
        $up[] = $last();
        $add('upcomming-bics', 'speakers', 'Speakers', ['eyebrow' => 'Faculty', 'heading' => 'Speakers', 'anchor' => 'speakers', 'items' => [], 'background' => 'white',
            'empty_title' => 'Speakers to be announced', 'empty_text' => 'Our faculty of international cancer care experts will be announced here soon.']);
        $up[] = $last();
        $add('upcomming-bics', 'venue', 'Venue', ['eyebrow' => 'Venue', 'heading' => 'Venue & travel', 'anchor' => 'venue', 'media' => 'map', 'items' => $venueInfo, 'background' => 'soft']);
        $up[] = $last();
        $add('upcomming-bics', 'faq', 'FAQ', ['eyebrow' => 'FAQ', 'heading' => 'Good to know', 'anchor' => 'faq', 'items' => $faq,
            'help_title' => 'Still have a question?', 'help_text' => 'The BICS team is happy to help.', 'button_text' => 'Contact us', 'button_url' => 'contact-us']);
        $up[] = $last();
        $add('upcomming-bics', 'cta', 'Register banner', $cta);
        $up[] = $last();
        $order('upcomming-bics', $up);
        DB::table('pages')->where('slug', 'upcomming-bics')->update(['title' => 'Upcoming Summit']);

        /* ---------- Contact ---------- */
        $ct = [];
        $add('contact-us', 'page_header', 'Page header', ['eyebrow' => 'Contact', 'title' => 'Contact the BICS team', 'subtitle' => 'Registration, programme, partnerships, media or travel — we are happy to help.']);
        $ct[] = $last();
        $form = DB::table('page_sections')->where('page_id', $pages['contact-us'] ?? 0)->where('type', 'contact_form')->first();
        if ($form) {
            $state['changed_sections'][$form->id] = $form->content;
            DB::table('page_sections')->where('id', $form->id)->update(['content' => json_encode(SectionTypes::clean('contact_form', ['eyebrow' => 'Get in touch', 'form_title' => 'We’re here to help',
                'intro' => 'Questions about registration, the programme, partnerships or travel? Send us a message and the BICS team will reply by email.', 'image' => '', 'background' => 'soft']))]);
            $ct[] = $form->id;
        }
        $add('contact-us', 'faq', 'FAQ', ['eyebrow' => 'FAQ', 'heading' => 'Quick answers', 'items' => array_slice($faq, 1, 4), 'background' => 'white']);
        $ct[] = $last();
        $order('contact-us', $ct);

        /* ---------- Menu ---------- */
        $menu = DB::table('menu_items')->get();
        $top = fn (string $url) => $menu->first(fn ($m) => $m->parent_id === null && trim($m->url, '/') === $url);
        $rename = function ($item, array $changes) use (&$state) {
            if (!$item) return;
            $state['changed_menu'][$item->id] = (array) $item;
            DB::table('menu_items')->where('id', $item->id)->update($changes + ['updated_at' => now()]);
        };
        // Dead links ("#") under the top items are switched off
        $dead = $menu->filter(fn ($m) => $m->parent_id !== null && in_array(trim($m->url), ['#', '#menu-2', ''], true) && $m->is_active)->pluck('id')->all();
        DB::table('menu_items')->whereIn('id', $dead)->update(['is_active' => false]);
        $state['disabled_menu'] = $dead;

        $about = $top('bics-overview');
        $upcoming = $top('upcomming-bics');
        $rename($about, ['title' => 'About BICS', 'sort' => 2]);
        $rename($upcoming, ['title' => 'Upcoming Summit', 'sort' => 3]);
        $rename($top('gallery'), ['sort' => 4]);
        $rename($top('contact-us'), ['sort' => 6]);
        $addMenu = function ($parent, string $title, string $url, int $sort) use (&$state, $now) {
            if (!$parent) return;
            $state['added_menu'][] = DB::table('menu_items')->insertGetId(['parent_id' => $parent->id, 'title' => $title, 'url' => $url, 'sort' => $sort, 'is_active' => true, 'new_tab' => false, 'created_at' => $now, 'updated_at' => $now]);
        };
        $addMenu($about, 'What is BICS', 'bics-overview', 101);
        $addMenu($about, 'Objectives', 'bics-overview#objectives', 102);
        $addMenu($about, 'Past summits', 'bics-overview#past-summits', 103);
        $addMenu($upcoming, 'Key dates', 'upcomming-bics#key-dates', 101);
        $addMenu($upcoming, 'Programme', 'upcomming-bics#programme', 102);
        $addMenu($upcoming, 'Speakers', 'upcomming-bics#speakers', 103);
        $addMenu($upcoming, 'Venue & travel', 'upcomming-bics#venue', 104);
        $addMenu($upcoming, 'FAQ', 'upcomming-bics#faq', 105);
        $addMenu($upcoming, 'Register now', 'registration', 106);

        DB::table('settings')->updateOrInsert(['key' => self::STATE_KEY], ['value' => json_encode($state), 'created_at' => $now, 'updated_at' => $now]);
    }

    public function down(): void
    {
        $state = json_decode((string) DB::table('settings')->where('key', self::STATE_KEY)->value('value'), true) ?: [];
        DB::table('page_sections')->whereIn('id', $state['added_sections'] ?? [])->delete();
        DB::table('page_sections')->whereIn('id', $state['disabled_sections'] ?? [])->update(['is_active' => true]);
        foreach ($state['sorts'] ?? [] as $id => $sort) DB::table('page_sections')->where('id', $id)->update(['sort' => $sort]);
        foreach ($state['changed_sections'] ?? [] as $id => $content) DB::table('page_sections')->where('id', $id)->update(['content' => $content]);
        DB::table('menu_items')->whereIn('id', $state['added_menu'] ?? [])->delete();
        DB::table('menu_items')->whereIn('id', $state['disabled_menu'] ?? [])->update(['is_active' => true]);
        foreach ($state['changed_menu'] ?? [] as $id => $row) {
            DB::table('menu_items')->where('id', $id)->update(['title' => $row['title'], 'sort' => $row['sort']]);
        }
        DB::table('pages')->where('slug', 'upcomming-bics')->update(['title' => 'Upcoming BICS']);
        DB::table('settings')->where('key', self::STATE_KEY)->delete();
        Schema::dropIfExists('contact_messages');
    }
};
