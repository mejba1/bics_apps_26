<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Current BICS event
    |--------------------------------------------------------------------------
    |
    | New registrations are saved under this venue / tracking number, and the
    | raffle draws from attendees of this venue. Change these for each event.
    |
    */

    'venue' => env('BICS_VENUE', 'Spain-26'),

    'tracking_no' => env('BICS_TRACKING_NO', 'Spain-20261028'),

    /*
    | Shown on the registration page and the attendee verification card (PDF).
    */

    /*
    | Public website address used in emails (images and links must be reachable from
    | the recipient's inbox, so never "localhost").
    */

    'public_url' => rtrim(env('BICS_PUBLIC_URL', 'https://bics.org'), '/'),

    'event_name' => env('BICS_EVENT_NAME', '4th Beacon International Cancer Summit 2026'),

    'event_short' => env('BICS_EVENT_SHORT', 'BICS 2026'),

    'event_venue' => env('BICS_EVENT_VENUE', 'Feria Valencia, 46035 Valencia, Spain'),

    'event_dates' => env('BICS_EVENT_DATES', '28 October 2026'),

];
