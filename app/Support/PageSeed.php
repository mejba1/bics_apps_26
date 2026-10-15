<?php

namespace App\Support;

use App\Models\Page;
use Illuminate\Support\Facades\DB;

/**
 * Initial page content: exactly what the site showed before pages became editable.
 * Called once by the create_pages_tables migration.
 */
class PageSeed
{
    public static function run(): void
    {
        $underConstruction = ['heading' => '', 'boxed' => false,
            'body' => '<h3 class="text-danger text-center mt-5 mb-5 fw-bold">Page Under Construction!!</h3>'];

        $pages = [
            ['home', 'Home', 'BICS', [
                ['slider', 'Home slider', ['slides' => [
                    ['image' => 'assets/frontend/images/slider/bics-spain-3.jpg', 'style' => 'none'],
                    ['image' => 'assets/frontend/images/slider/bics-algeria.png', 'style' => 'event', 'subtitle' => '17 September 2024',
                        'title' => 'Palma de Mallorca, Spain', 'show_countdown' => true,
                        'button1_text' => 'Registration Now', 'button1_url' => 'registration', 'button2_text' => 'View Details', 'button2_url' => '#'],
                    ['image' => 'assets/frontend/images/slider/bics-spain-3.jpg', 'style' => 'none'],
                    ['image' => 'assets/frontend/images/slider/slider-2.png', 'style' => 'headline', 'title' => "Leading The\nGlobal Fight\nAgainst Cancer"],
                ]]],
                ['about', 'What is BICS / Objectives', [
                    'left_title' => 'What is BICS?',
                    'left_body' => '<p><span class="text-danger fw-bold">Beacon International Cancer Summit (BICS) </span>is a global Summit organized by Beacon Pharma Ltd to unite cancer care professionals to improve quality of life of the cancer patients through exchanging latest information and innovative ideas in cancer treatments. </p>',
                    'button_text' => 'Read more', 'button_url' => '#',
                    'right_title' => 'Objectives:',
                    'right_items' => [
                        ['text' => 'Creating an open platform to discuss the best practices in cancer treatment'],
                        ['text' => 'Sharing knowledge and experience of using advanced medications in cancer management'],
                        ['text' => 'Capacity building in cancer care through networking & collaboration'],
                        ['text' => 'Building awareness for early detection of cancer'],
                    ],
                ]],
                ['image', 'BICS gallery banner', ['image' => 'assets/frontend/images/gellary.jpg', 'alt' => 'bics Gallery', 'layout' => 'full']],
                ['video', 'Highlights video', ['heading' => '', 'youtube_url' => 'https://www.youtube.com/watch?v=MKYLxN9t2bM'], false],
            ]],
            ['bics-overview', 'BICS Overview', 'Bics Overview', [['rich_text', 'Under construction', $underConstruction]]],
            ['upcomming-bics', 'Upcoming BICS', 'Upcomming Bics', [['rich_text', 'Under construction', $underConstruction]]],
            ['library-research', 'Library/ Research', 'Library-Research', [['rich_text', 'Under construction', $underConstruction]]],
            ['contact-us', 'Contact', 'Contact', [['contact_form', 'Contact form', ['image' => 'assets/frontend/images/contact-bics.png', 'form_title' => 'CONTACT FORM']]]],
            ['gallery', 'Gallery', 'Gallery', [['gallery', 'Photo gallery', static::gallery()]]],
            ['media', 'Media', 'Media', [['image', 'Media banner', ['image' => 'assets/frontend/images/gellary.jpg', 'alt' => 'bics Gallery', 'layout' => 'boxed']]]],
            [Page::GLOBAL_SLUG, 'Site-wide (footer, event sidebar, registration message)', null, [
                ['event_sidebar', 'Event sidebar & countdown', static::eventSidebar()],
                ['footer', 'Footer', static::footer()],
                ['registration_success', 'Registration success message', static::registrationSuccess()],
            ]],
        ];

        $now = now();
        foreach ($pages as [$slug, $title, $metaTitle, $sections]) {
            $pageId = DB::table('pages')->insertGetId([
                'slug' => $slug, 'title' => $title, 'meta_title' => $metaTitle, 'is_active' => true, 'is_system' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ($sections as $i => $section) {
                DB::table('page_sections')->insert([
                    'page_id' => $pageId, 'type' => $section[0], 'label' => $section[1],
                    'content' => json_encode(SectionTypes::clean($section[0], $section[2])),
                    'sort' => $i + 1, 'is_active' => $section[3] ?? true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    /** Texts of the registration success pop-up / page (also used until the admin edits them). */
    public static function registrationSuccess(): array
    {
        return [
            'title' => "You're registered!",
            'welcome' => 'Welcome, {name}',
            'show_code' => true,
            'code_label' => 'Registration number',
            'show_copy' => true,
            'show_email' => true,
            'email_sent' => 'Confirmation sent to {email}. Check your inbox (and spam folder).',
            'email_failed' => 'Your registration is complete, but the confirmation email could not be sent. Please save your registration number.',
            'note' => 'Keep this number — you need it for verification and the raffle draw.',
            'show_download' => true,
            'download_label' => 'Download card',
            'done_label' => 'Done',
            'done_url' => '',
        ];
    }

    /** Event sidebar as the site had it (also used before the migration has run). */
    public static function eventSidebar(): array
    {
        return ['year_text' => '2026', 'button_text' => 'Registration Now', 'button_url' => 'registration'];
    }

    /** Footer as the site had it (also used before the migration has run). */
    public static function footer(): array
    {
        return [
            'quick_links' => [['label' => 'About Us', 'url' => '#'], ['label' => 'Event', 'url' => '#'], ['label' => 'Privacy Policy', 'url' => '#'], ['label' => 'Term & Condition', 'url' => '#']],
            'about' => 'Beacon International Cancer Summit(BICS) is a global Summit organized by Beacon Pharma Ltd to unite cancer professional to improve quality of life of the cancer patient through exchanging latest information and innovation ideas in cancver treatments.',
            'facebook' => 'https://www.facebook.com/BeaconBics/', 'linkedin' => '#', 'twitter' => '#', 'youtube' => '#',
            'phones' => "+880-2-57165371\n+880-2-57165379",
            'emails' => "info@beaconpharma.com.bd\nbics@beaconpharma.com.bd",
            'address' => '9/B/2,Toyenbee Circular Road, Motijheel,Dhaka-1223, Bangladesh',
            'copyright' => 'Copyright © {year}  | Developed By - BML IT',
        ];
    }

    /** The original gallery photos (also used by the gallery albums migration on fresh installs). */
    public static function gallery(): array
    {
        $images = [];
        foreach ([1, 2, 4, 3, 5, 6, 7, 8, 9, 10, 11, 12] as $n) {
            $images[] = ['image' => "assets/frontend/images/gallery/Bics2023-$n.jpg", 'group' => 'Bics2023', 'alt' => in_array($n, [8, 10, 12]) ? 'Algeria bics' : 'Tanzania bics'];
        }
        foreach ([4, 8, 13, 11, 19, 17, 18, 20, 6, 16, 12, 10] as $n) {
            $images[] = ['image' => "assets/frontend/images/gallery/bics-tanzania-$n.jpg", 'group' => 'Bics2021', 'alt' => $n === 4 ? 'Tanzania' : 'Tanzania bics'];
        }

        return [
            'title' => 'Gallery',
            'filters' => [['key' => 'Bics2021', 'label' => 'Tanzania 2021'], ['key' => 'Bics2023', 'label' => 'Algeria 2023']],
            'images' => $images,
        ];
    }
}
