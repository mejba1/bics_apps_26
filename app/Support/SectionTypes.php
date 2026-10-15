<?php

namespace App\Support;

/**
 * Every kind of section a page can contain.
 *
 * Each type has a frontend view (resources/views/frontend/sections/{type}.blade.php)
 * and a list of fields; the admin editor builds its form from these fields.
 *
 * Field types: text, textarea, richtext, image, url, datetime, select, checkbox, code,
 * repeater (with its own "fields"; "bulk" names an image field filled by multi-upload).
 */
class SectionTypes
{
    public static function all(): array
    {
        $button = [
            ['name' => 'button_text', 'label' => 'Button text', 'type' => 'text'],
            ['name' => 'button_url', 'label' => 'Button link', 'type' => 'url'],
        ];
        // Shared by the event sections: heading block, menu anchor and background
        $head = [
            ['name' => 'eyebrow', 'label' => 'Small text above the heading', 'type' => 'text'],
            ['name' => 'heading', 'label' => 'Heading', 'type' => 'text'],
            ['name' => 'intro', 'label' => 'Intro text', 'type' => 'textarea'],
        ];
        $look = [
            ['name' => 'anchor', 'label' => 'Anchor for menu links (e.g. programme → link to /#programme)', 'type' => 'text'],
            ['name' => 'background', 'label' => 'Background', 'type' => 'select', 'options' => ['white' => 'White', 'soft' => 'Light blue-grey', 'dark' => 'Dark navy']],
        ];

        $types = [
            'slider' => [
                'label' => 'Slider', 'icon' => 'bi-images',
                'description' => 'Full-width image carousel with optional headline, countdown and buttons.',
                'fields' => [
                    ['name' => 'slides', 'label' => 'Slides', 'type' => 'repeater', 'item_label' => 'title', 'bulk' => 'image', 'fields' => [
                        ['name' => 'image', 'label' => 'Image', 'type' => 'image'],
                        ['name' => 'style', 'label' => 'Text style', 'type' => 'select', 'options' => ['none' => 'Image only', 'event' => 'Event (date, place, countdown, buttons)', 'headline' => 'Big headline (one line per row)']],
                        ['name' => 'subtitle', 'label' => 'Small text (e.g. date)', 'type' => 'text'],
                        ['name' => 'title', 'label' => 'Title / headline', 'type' => 'textarea'],
                        ['name' => 'show_countdown', 'label' => 'Show countdown (uses the event date in Admin > Events (current event))', 'type' => 'checkbox'],
                        ['name' => 'button1_text', 'label' => 'Button 1 text', 'type' => 'text'],
                        ['name' => 'button1_url', 'label' => 'Button 1 link', 'type' => 'url'],
                        ['name' => 'button2_text', 'label' => 'Button 2 text', 'type' => 'text'],
                        ['name' => 'button2_url', 'label' => 'Button 2 link', 'type' => 'url'],
                    ]],
                ],
                'defaults' => ['slides' => [['image' => '', 'style' => 'none']]],
            ],

            'about' => [
                'label' => 'Two info cards', 'icon' => 'bi-layout-split',
                'description' => 'Text card on the left, bullet list card on the right (like "What is BICS? / Objectives").',
                'fields' => array_merge([
                    ['name' => 'left_title', 'label' => 'Left card title', 'type' => 'text'],
                    ['name' => 'left_body', 'label' => 'Left card text', 'type' => 'richtext'],
                ], [
                    ['name' => 'button_text', 'label' => 'Left card button text', 'type' => 'text'],
                    ['name' => 'button_url', 'label' => 'Left card button link', 'type' => 'url'],
                    ['name' => 'right_title', 'label' => 'Right card title', 'type' => 'text'],
                    ['name' => 'right_items', 'label' => 'Right card list', 'type' => 'repeater', 'item_label' => 'text', 'fields' => [
                        ['name' => 'text', 'label' => 'Text', 'type' => 'text'],
                    ]],
                ]),
                'defaults' => ['left_title' => 'Title', 'left_body' => '<p>Text</p>', 'right_title' => 'List', 'right_items' => [['text' => 'Item']]],
            ],

            'rich_text' => [
                'label' => 'Text block', 'icon' => 'bi-text-paragraph',
                'description' => 'Heading and formatted text (bold, lists, links, images, tables).',
                'fields' => [
                    ['name' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                    ['name' => 'body', 'label' => 'Text', 'type' => 'richtext'],
                    ['name' => 'boxed', 'label' => 'Show inside a white card', 'type' => 'checkbox'],
                ],
                'defaults' => ['heading' => 'New section', 'body' => '<p>Write your text here.</p>', 'boxed' => true],
            ],

            'text_image' => [
                'label' => 'Text + image', 'icon' => 'bi-file-richtext',
                'description' => 'Text on one side, image on the other.',
                'fields' => array_merge([
                    ['name' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                    ['name' => 'body', 'label' => 'Text', 'type' => 'richtext'],
                    ['name' => 'image', 'label' => 'Image', 'type' => 'image'],
                    ['name' => 'image_position', 'label' => 'Image position', 'type' => 'select', 'options' => ['right' => 'Right', 'left' => 'Left']],
                ], $button),
                'defaults' => ['heading' => 'New section', 'body' => '<p>Write your text here.</p>', 'image' => '', 'image_position' => 'right'],
            ],

            'image' => [
                'label' => 'Image banner', 'icon' => 'bi-image',
                'description' => 'One large image in a card.',
                'fields' => [
                    ['name' => 'image', 'label' => 'Image', 'type' => 'image'],
                    ['name' => 'alt', 'label' => 'Image description (for accessibility)', 'type' => 'text'],
                    ['name' => 'link', 'label' => 'Link when clicked (optional)', 'type' => 'url'],
                    ['name' => 'layout', 'label' => 'Width', 'type' => 'select', 'options' => ['full' => 'Full width', 'boxed' => 'Centered']],
                ],
                'defaults' => ['image' => '', 'layout' => 'boxed'],
            ],

            'gallery' => [
                'label' => 'Photo gallery', 'icon' => 'bi-grid-3x3-gap',
                'description' => 'Photo grid with a filter button per album. Albums and photos are managed in Admin > Gallery.',
                'fields' => [
                    ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
                    ['name' => 'subtitle', 'label' => 'Short text under the title (optional)', 'type' => 'textarea'],
                    ['name' => 'layout', 'label' => 'Layout', 'type' => 'select', 'options' => ['masonry' => 'Natural shapes (masonry)', 'grid' => 'Even squares']],
                ],
                'defaults' => ['title' => 'Gallery', 'subtitle' => '', 'layout' => 'masonry'],
            ],

            'cards' => [
                'label' => 'Cards grid', 'icon' => 'bi-grid',
                'description' => 'Grid of cards with icon/photo, title, text and link (people, topics, programs...).',
                'fields' => [
                    ['name' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                    ['name' => 'intro', 'label' => 'Intro text', 'type' => 'textarea'],
                    ['name' => 'columns', 'label' => 'Cards per row', 'type' => 'select', 'options' => ['2' => '2', '3' => '3', '4' => '4']],
                    ['name' => 'items', 'label' => 'Cards', 'type' => 'repeater', 'item_label' => 'title', 'fields' => [
                        ['name' => 'image', 'label' => 'Photo (optional)', 'type' => 'image'],
                        ['name' => 'icon', 'label' => 'Icon (optional, e.g. bi-award — see icons.getbootstrap.com)', 'type' => 'text'],
                        ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
                        ['name' => 'text', 'label' => 'Text', 'type' => 'textarea'],
                        ['name' => 'link', 'label' => 'Link (optional)', 'type' => 'url'],
                    ]],
                ],
                'defaults' => ['heading' => 'New section', 'columns' => '3', 'items' => [['title' => 'Card title', 'text' => 'Card text']]],
            ],

            'video' => [
                'label' => 'Video', 'icon' => 'bi-play-btn',
                'description' => 'Embedded YouTube video.',
                'fields' => [
                    ['name' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                    ['name' => 'youtube_url', 'label' => 'YouTube link', 'type' => 'url'],
                ],
                'defaults' => ['heading' => '', 'youtube_url' => ''],
            ],

            'cta' => [
                'label' => 'Call to action', 'icon' => 'bi-megaphone',
                'description' => 'Coloured banner with a short message and a button.',
                'fields' => array_merge([
                    ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
                    ['name' => 'text', 'label' => 'Text', 'type' => 'textarea'],
                ], $button),
                'defaults' => ['title' => 'Join BICS 2026', 'text' => '', 'button_text' => 'Register now', 'button_url' => 'registration'],
            ],

            'event_countdown' => [
                'label' => 'Event countdown', 'icon' => 'bi-stopwatch',
                'description' => 'Event name, date, venue, live countdown and registration status. Dates come from Admin > Events (current event).',
                'fields' => [
                    ['name' => 'heading', 'label' => 'Heading (empty = event name)', 'type' => 'text'],
                    ['name' => 'subheading', 'label' => 'Small text above the heading', 'type' => 'text'],
                    ['name' => 'show_venue', 'label' => 'Show venue', 'type' => 'checkbox'],
                    ['name' => 'show_registration', 'label' => 'Show registration status (open / opens on / closed)', 'type' => 'checkbox'],
                    ['name' => 'button_text', 'label' => 'Button text (shown while registration is open)', 'type' => 'text'],
                    ['name' => 'button_url', 'label' => 'Button link', 'type' => 'url'],
                    ['name' => 'finished_text', 'label' => 'Text when the event has started', 'type' => 'text'],
                ],
                'defaults' => ['heading' => '', 'subheading' => 'Next summit', 'show_venue' => true, 'show_registration' => true,
                    'button_text' => 'Register now', 'button_url' => 'registration', 'finished_text' => 'The summit is happening now!'],
            ],

            'contact_form' => [
                'label' => 'Contact form', 'icon' => 'bi-envelope',
                'description' => 'Image with the contact form next to it.',
                'fields' => [
                    ['name' => 'image', 'label' => 'Image', 'type' => 'image'],
                    ['name' => 'form_title', 'label' => 'Heading', 'type' => 'text'],
                    ['name' => 'intro', 'label' => 'Intro text', 'type' => 'textarea'],
                ],
                'defaults' => ['image' => 'assets/frontend/images/contact-bics.png', 'form_title' => 'CONTACT FORM'],
            ],

            'html' => [
                'label' => 'Custom HTML', 'icon' => 'bi-code-slash',
                'description' => 'For advanced users: any HTML code, shown as is.',
                'fields' => [
                    ['name' => 'code', 'label' => 'HTML', 'type' => 'code'],
                ],
                'defaults' => ['code' => ''],
            ],

            /* ---- Event sections (BICS theme) ---- */

            'hero' => [
                'label' => 'Event hero', 'icon' => 'bi-stars',
                'description' => 'Big opening banner: event name, theme, date, venue, live countdown and registration buttons. Date, venue and registration state come from the current event (Admin > Events).',
                'fields' => [
                    ['name' => 'kicker', 'label' => 'Small label (e.g. "4th edition")', 'type' => 'text'],
                    ['name' => 'title', 'label' => 'Headline (empty = event name)', 'type' => 'textarea'],
                    ['name' => 'highlight', 'label' => 'Word(s) of the headline to colour (optional)', 'type' => 'text'],
                    ['name' => 'theme', 'label' => 'Theme / tagline', 'type' => 'textarea'],
                    ['name' => 'image', 'label' => 'Background photo', 'type' => 'image'],
                    ['name' => 'show_countdown', 'label' => 'Show countdown card', 'type' => 'checkbox'],
                    ['name' => 'countdown_title', 'label' => 'Countdown card title', 'type' => 'text'],
                    ['name' => 'finished_text', 'label' => 'Text when the event has started', 'type' => 'text'],
                    ['name' => 'button1_text', 'label' => 'Main button text (empty = "Register now" while open)', 'type' => 'text'],
                    ['name' => 'button1_url', 'label' => 'Main button link', 'type' => 'url'],
                    ['name' => 'button2_text', 'label' => 'Second button text', 'type' => 'text'],
                    ['name' => 'button2_url', 'label' => 'Second button link', 'type' => 'url'],
                ],
                'defaults' => ['kicker' => '', 'title' => '', 'theme' => '', 'image' => '', 'show_countdown' => true, 'countdown_title' => 'The summit starts in',
                    'finished_text' => 'The summit is happening now!', 'button1_text' => '', 'button1_url' => 'registration', 'button2_text' => 'View programme', 'button2_url' => '#programme'],
            ],

            'page_header' => [
                'label' => 'Page header', 'icon' => 'bi-window-desktop',
                'description' => 'Title banner for inner pages, with breadcrumb.',
                'fields' => [
                    ['name' => 'eyebrow', 'label' => 'Small text above the title', 'type' => 'text'],
                    ['name' => 'title', 'label' => 'Title (empty = page title)', 'type' => 'text'],
                    ['name' => 'subtitle', 'label' => 'Text under the title', 'type' => 'textarea'],
                    ['name' => 'image', 'label' => 'Background photo (optional)', 'type' => 'image'],
                ],
                'defaults' => ['eyebrow' => '', 'title' => '', 'subtitle' => '', 'image' => ''],
            ],

            'stats' => [
                'label' => 'Key numbers', 'icon' => 'bi-bar-chart',
                'description' => 'Big animated numbers. Live values: {registrations}, {countries}, {summits}, {host_cities} (e.g. "{registrations}+").',
                'fields' => array_merge($head, [
                    ['name' => 'items', 'label' => 'Numbers', 'type' => 'repeater', 'item_label' => 'label', 'fields' => [
                        ['name' => 'value', 'label' => 'Number (e.g. 350+, or {registrations}+)', 'type' => 'text'],
                        ['name' => 'label', 'label' => 'Label', 'type' => 'text'],
                        ['name' => 'icon', 'label' => 'Icon (e.g. bi-people)', 'type' => 'text'],
                    ]],
                ], $look),
                'defaults' => ['heading' => 'BICS in numbers', 'background' => 'dark', 'items' => [['value' => '{registrations}+', 'label' => 'Registrations', 'icon' => 'bi-people']]],
            ],

            'speakers' => [
                'label' => 'Speakers', 'icon' => 'bi-mic',
                'description' => 'Speaker cards with photo, role, organisation and talk. With no speakers yet, a "to be announced" box is shown.',
                'fields' => array_merge($head, [
                    ['name' => 'items', 'label' => 'Speakers', 'type' => 'repeater', 'item_label' => 'name', 'bulk' => 'photo', 'fields' => [
                        ['name' => 'photo', 'label' => 'Photo (square works best)', 'type' => 'image'],
                        ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
                        ['name' => 'role', 'label' => 'Role / title', 'type' => 'text'],
                        ['name' => 'organisation', 'label' => 'Organisation', 'type' => 'text'],
                        ['name' => 'country', 'label' => 'Country', 'type' => 'text'],
                        ['name' => 'topic', 'label' => 'Talk / topic', 'type' => 'text'],
                    ]],
                    ['name' => 'empty_title', 'label' => 'Title when no speakers are listed', 'type' => 'text'],
                    ['name' => 'empty_text', 'label' => 'Text when no speakers are listed', 'type' => 'textarea'],
                ], $look),
                'defaults' => ['heading' => 'Speakers', 'items' => [], 'empty_title' => 'Speakers to be announced', 'empty_text' => 'Our faculty of international experts will be announced soon.'],
            ],

            'agenda' => [
                'label' => 'Programme / agenda', 'icon' => 'bi-calendar3-week',
                'description' => 'Schedule grouped by day (a tab per day). Use the same "Day" text for sessions of the same day.',
                'fields' => array_merge($head, [
                    ['name' => 'items', 'label' => 'Sessions', 'type' => 'repeater', 'item_label' => 'title', 'fields' => [
                        ['name' => 'day', 'label' => 'Day (e.g. Day 1 · 28 Oct)', 'type' => 'text'],
                        ['name' => 'time', 'label' => 'Time (e.g. 09:00 – 09:30)', 'type' => 'text'],
                        ['name' => 'title', 'label' => 'Session title', 'type' => 'text'],
                        ['name' => 'speaker', 'label' => 'Speaker(s) / chair', 'type' => 'text'],
                        ['name' => 'room', 'label' => 'Room / hall', 'type' => 'text'],
                        ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => ['session' => 'Session', 'keynote' => 'Keynote', 'panel' => 'Panel discussion', 'break' => 'Break / networking', 'ceremony' => 'Ceremony']],
                    ]],
                    ['name' => 'note', 'label' => 'Note under the programme', 'type' => 'text'],
                    ['name' => 'empty_text', 'label' => 'Text when no sessions are listed', 'type' => 'textarea'],
                ], $look),
                'defaults' => ['heading' => 'Programme', 'items' => [], 'empty_text' => 'The scientific programme will be published soon.'],
            ],

            'key_dates' => [
                'label' => 'Key dates', 'icon' => 'bi-calendar-check',
                'description' => 'Important dates. Registration opening/closing and the summit day can be added automatically from the current event.',
                'fields' => array_merge($head, [
                    ['name' => 'auto', 'label' => 'Add registration and summit dates from Admin > Events automatically', 'type' => 'checkbox'],
                    ['name' => 'items', 'label' => 'Other dates', 'type' => 'repeater', 'item_label' => 'title', 'fields' => [
                        ['name' => 'date', 'label' => 'Date', 'type' => 'datetime'],
                        ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
                        ['name' => 'text', 'label' => 'Text', 'type' => 'textarea'],
                    ]],
                ], $look),
                'defaults' => ['heading' => 'Key dates', 'auto' => true, 'items' => []],
            ],

            'venue' => [
                'label' => 'Venue & travel', 'icon' => 'bi-geo-alt',
                'description' => 'Venue with map (from the current event address) or photo, plus travel information.',
                'fields' => array_merge($head, [
                    ['name' => 'venue_name', 'label' => 'Venue name (empty = event venue)', 'type' => 'text'],
                    ['name' => 'address', 'label' => 'Address for the map (empty = event venue)', 'type' => 'text'],
                    ['name' => 'media', 'label' => 'Show', 'type' => 'select', 'options' => ['map' => 'Google map', 'image' => 'Photo']],
                    ['name' => 'image', 'label' => 'Photo (when "Photo" is chosen)', 'type' => 'image'],
                    ['name' => 'items', 'label' => 'Travel information', 'type' => 'repeater', 'item_label' => 'title', 'fields' => [
                        ['name' => 'icon', 'label' => 'Icon (e.g. bi-airplane)', 'type' => 'text'],
                        ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
                        ['name' => 'text', 'label' => 'Text', 'type' => 'textarea'],
                    ]],
                ], $look),
                'defaults' => ['heading' => 'Venue', 'media' => 'map', 'items' => []],
            ],

            'past_events' => [
                'label' => 'Past summits', 'icon' => 'bi-clock-history',
                'description' => 'Photo cards of earlier summits (year, edition, city, theme).',
                'fields' => array_merge($head, [
                    ['name' => 'items', 'label' => 'Summits', 'type' => 'repeater', 'item_label' => 'city', 'fields' => [
                        ['name' => 'image', 'label' => 'Photo', 'type' => 'image'],
                        ['name' => 'year', 'label' => 'Year', 'type' => 'text'],
                        ['name' => 'edition', 'label' => 'Edition (e.g. 3rd BICS)', 'type' => 'text'],
                        ['name' => 'city', 'label' => 'City / country', 'type' => 'text'],
                        ['name' => 'theme', 'label' => 'Theme', 'type' => 'text'],
                        ['name' => 'link', 'label' => 'Link (e.g. gallery)', 'type' => 'url'],
                        ['name' => 'upcoming', 'label' => 'This is the upcoming summit (highlighted card)', 'type' => 'checkbox'],
                    ]],
                ], $look),
                'defaults' => ['heading' => 'Past summits', 'items' => []],
            ],

            'faq' => [
                'label' => 'FAQ', 'icon' => 'bi-question-circle',
                'description' => 'Questions and answers (accordion) with a contact box.',
                'fields' => array_merge($head, [
                    ['name' => 'items', 'label' => 'Questions', 'type' => 'repeater', 'item_label' => 'question', 'fields' => [
                        ['name' => 'question', 'label' => 'Question', 'type' => 'text'],
                        ['name' => 'answer', 'label' => 'Answer', 'type' => 'textarea'],
                    ]],
                    ['name' => 'help_title', 'label' => 'Contact box title (empty = hide)', 'type' => 'text'],
                    ['name' => 'help_text', 'label' => 'Contact box text', 'type' => 'textarea'],
                    ['name' => 'button_text', 'label' => 'Contact box button', 'type' => 'text'],
                    ['name' => 'button_url', 'label' => 'Contact box link', 'type' => 'url'],
                ], $look),
                'defaults' => ['heading' => 'Frequently asked questions', 'items' => [['question' => 'Question?', 'answer' => 'Answer.']]],
            ],

            'partners' => [
                'label' => 'Partners & sponsors', 'icon' => 'bi-buildings',
                'description' => 'Logo wall grouped by tier (organiser, partner, sponsor...).',
                'fields' => array_merge($head, [
                    ['name' => 'items', 'label' => 'Logos', 'type' => 'repeater', 'item_label' => 'name', 'bulk' => 'logo', 'fields' => [
                        ['name' => 'logo', 'label' => 'Logo', 'type' => 'image'],
                        ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
                        ['name' => 'tier', 'label' => 'Group (e.g. Organised by, Gold sponsor)', 'type' => 'text'],
                        ['name' => 'link', 'label' => 'Website', 'type' => 'url'],
                    ]],
                ], $look),
                'defaults' => ['heading' => 'Partners', 'items' => []],
            ],

            /* ---- Site-wide (only on the hidden "Site-wide" page) ---- */

            'footer' => [
                'label' => 'Footer', 'icon' => 'bi-layout-text-window-reverse', 'global' => true,
                'description' => 'Footer shown on every page.',
                'fields' => [
                    ['name' => 'quick_links', 'label' => 'Quick links', 'type' => 'repeater', 'item_label' => 'label', 'fields' => [
                        ['name' => 'label', 'label' => 'Text', 'type' => 'text'],
                        ['name' => 'url', 'label' => 'Link', 'type' => 'url'],
                    ]],
                    ['name' => 'about', 'label' => 'About text', 'type' => 'textarea'],
                    ['name' => 'facebook', 'label' => 'Facebook link', 'type' => 'url'],
                    ['name' => 'linkedin', 'label' => 'LinkedIn link', 'type' => 'url'],
                    ['name' => 'twitter', 'label' => 'Twitter / X link', 'type' => 'url'],
                    ['name' => 'youtube', 'label' => 'YouTube link', 'type' => 'url'],
                    ['name' => 'phones', 'label' => 'Phone numbers (one per line)', 'type' => 'textarea'],
                    ['name' => 'emails', 'label' => 'Emails (one per line)', 'type' => 'textarea'],
                    ['name' => 'address', 'label' => 'Address', 'type' => 'textarea'],
                    ['name' => 'copyright', 'label' => 'Copyright text ({year} = current year)', 'type' => 'text'],
                ],
                'defaults' => [],
            ],

            'registration_success' => [
                'label' => 'Registration success message', 'icon' => 'bi-patch-check', 'global' => true,
                'description' => 'What people see right after they register (pop-up and success page). You can use {name}, {email}, {code} and {event} in the texts.',
                'fields' => [
                    ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
                    ['name' => 'welcome', 'label' => 'Welcome line ({name} = person\'s title and name)', 'type' => 'text'],
                    ['name' => 'show_code', 'label' => 'Show the registration number', 'type' => 'checkbox'],
                    ['name' => 'code_label', 'label' => 'Label above the registration number', 'type' => 'text'],
                    ['name' => 'show_copy', 'label' => 'Show the "Copy" button', 'type' => 'checkbox'],
                    ['name' => 'show_email', 'label' => 'Show the email confirmation box', 'type' => 'checkbox'],
                    ['name' => 'email_sent', 'label' => 'Email confirmation text - when the email was sent ({email})', 'type' => 'textarea'],
                    ['name' => 'email_failed', 'label' => 'Email confirmation text - when the email could not be sent', 'type' => 'textarea'],
                    ['name' => 'note', 'label' => 'Note under the box (leave empty to hide)', 'type' => 'textarea'],
                    ['name' => 'show_download', 'label' => 'Show the "Download card" button', 'type' => 'checkbox'],
                    ['name' => 'download_label', 'label' => 'Download button text', 'type' => 'text'],
                    ['name' => 'done_label', 'label' => 'Main button text', 'type' => 'text'],
                    ['name' => 'done_url', 'label' => 'Main button goes to (empty = a fresh registration form)', 'type' => 'url'],
                ],
                'defaults' => [],
            ],

            'event_sidebar' => [
                'label' => 'Event sidebar & countdown', 'icon' => 'bi-stopwatch', 'global' => true,
                'description' => 'Slide-out event panel. Its countdown uses the event date from Admin > Events (current event).',
                'fields' => [
                    ['name' => 'year_text', 'label' => 'Text next to the logo', 'type' => 'text'],
                    ['name' => 'button_text', 'label' => 'Button text', 'type' => 'text'],
                    ['name' => 'button_url', 'label' => 'Button link', 'type' => 'url'],
                ],
                'defaults' => [],
            ],
        ];

        // Older section types get the same small heading, anchor and background options
        foreach (['about', 'cards', 'video', 'cta', 'rich_text', 'text_image', 'gallery', 'contact_form'] as $t) {
            $names = array_column($types[$t]['fields'], 'name');
            if (!in_array('eyebrow', $names, true)) array_unshift($types[$t]['fields'], $head[0]);
            $types[$t]['fields'] = array_merge($types[$t]['fields'], $look);
        }
        return $types;
    }

    public static function get(string $type): ?array
    {
        return static::all()[$type] ?? null;
    }

    /** Types an admin can add to a normal page (site-wide ones excluded). */
    public static function forPages(): array
    {
        return array_filter(static::all(), fn ($t) => empty($t['global']));
    }

    /**
     * Keep only the fields the type defines, with the right shape for each field type.
     */
    public static function clean(string $type, array $input): array
    {
        return static::cleanFields(static::get($type)['fields'] ?? [], $input);
    }

    private static function cleanFields(array $fields, array $input): array
    {
        $out = [];
        foreach ($fields as $field) {
            $value = $input[$field['name']] ?? null;
            switch ($field['type']) {
                case 'repeater':
                    $out[$field['name']] = array_values(array_map(
                        fn ($item) => static::cleanFields($field['fields'], is_array($item) ? $item : []),
                        is_array($value) ? $value : []
                    ));
                    break;
                case 'checkbox':
                    $out[$field['name']] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'select':
                    $options = array_map('strval', array_keys($field['options']));
                    $out[$field['name']] = in_array((string) $value, $options, true) ? (string) $value : $options[0];
                    break;
                default:
                    $out[$field['name']] = is_scalar($value) ? trim((string) $value) : '';
            }
        }
        return $out;
    }
}
