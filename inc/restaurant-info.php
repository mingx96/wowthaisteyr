<?php
/**
 * WOW Thai Steyr - Restaurant Info, Opening Hours & Structured Data (Schema.org)
 * Zentrale, verbindliche Restaurant- & Küchenzeiten:
 * - Montag bis Samstag: 11:30–14:30 Uhr (Mittag) & 17:00–21:00 Uhr (Abend)
 * - Sonntag: 11:30–19:00 Uhr (durchgehend)
 * - Montags: Sushi-Ruhetag (Warme Küche regulär geöffnet)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Zentrale Restaurant-Stammdaten (Steyr)
 */
function wowthai_get_restaurant_data() {
    return [
        'name'            => 'WOW Thai Steyr',
        'legal_name'      => 'WOW Thai Steyr Gastronomie GmbH',
        'cuisine'         => ['Thailändisch', 'Asiatisch', 'Sushi', 'Wok'],
        'street'          => 'Leopold-Werndl-Straße 2 (City Point · 1. OG)',
        'postal_code'     => '4400',
        'city'            => 'Steyr',
        'region'          => 'Oberösterreich',
        'country'         => 'AT',
        'country_name'    => 'Österreich',
        'phone'           => '+43 7252 74973',
        'phone_clean'     => '+43725274973',
        'email'           => 'info@wow-thai-restaurant.at',
        'price_range'     => '€€',
        'geo'             => [
            'latitude'    => '48.0435',
            'longitude'   => '14.4178',
        ],
        'google_maps_url' => 'https://maps.google.com/?q=Leopold-Werndl-Stra%C3%9Fe+2+4400+Steyr',
        'opening_hours_text' => [
            'Mo–Sa'   => '11:30–14:30 · 17:00–21:00 Uhr',
            'So'      => '11:30–19:00 Uhr (durchgehend)',
            'Hinweis' => 'Montags Sushi-Ruhetag (Warme Küche regulär)',
        ],
    ];
}

/**
 * Prüft ob am angegebenen Wochentag Sushi verfügbar ist
 * $day_of_week: 1 (Montag) bis 7 (Sonntag)
 */
function wowthai_is_sushi_available_on_day($day_of_week = null) {
    if ($day_of_week === null) {
        $now = current_datetime();
        $day_of_week = (int) $now->format('N');
    }
    // Montag (1) ist Sushi-Ruhetag!
    return ((int)$day_of_week !== 1);
}

/**
 * Ermittelt aktuellen Öffnungsstatus basierend auf der aktuellen Uhrzeit (Europe/Vienna)
 */
function wowthai_get_opening_status() {
    $now = current_datetime(); // DateTimeImmutable in WordPress timezone
    $day_of_week = (int) $now->format('N'); // 1 = Mo, 7 = So
    $hour_min = $now->format('H:i');

    $is_open = false;
    $status_label = 'Vorbestellung möglich';
    $status_detail = '';
    $status_type = 'preorder'; // 'open' oder 'preorder'
    $next_open_label = '';

    $prep_time = function_exists('wowthai_get_kitchen_prep_time') ? wowthai_get_kitchen_prep_time() : '20–30 Min.';

    if ($day_of_week >= 1 && $day_of_week <= 6) {
        // Mo - Sa: 11:30 - 14:30 & 17:00 - 21:00
        if ($hour_min >= '11:30' && $hour_min < '14:30') {
            $is_open = true;
            $status_type = 'open';
            $status_label = 'Jetzt geöffnet';
            $status_detail = 'Mittagsservice bis 14:30 Uhr · Abholung bereit in ca. ' . $prep_time;
        } elseif ($hour_min >= '17:00' && $hour_min < '21:00') {
            $is_open = true;
            $status_type = 'open';
            $status_label = 'Jetzt geöffnet';
            $status_detail = 'Abendservice bis 21:00 Uhr · Abholung bereit in ca. ' . $prep_time;
        } elseif ($hour_min < '11:30') {
            $next_open_label = 'heute um 11:30 Uhr';
            $status_detail = 'Küche öffnet heute um 11:30 Uhr. Sie können jetzt für den Mittag vorbestellen!';
        } elseif ($hour_min >= '14:30' && $hour_min < '17:00') {
            $next_open_label = 'heute um 17:00 Uhr';
            $status_detail = 'Nachmittagspause · Küche öffnet wieder um 17:00 Uhr. Vorbestellung jetzt möglich!';
        } else {
            // Nach 21:00 Uhr
            if ($day_of_week === 6) {
                $next_open_label = 'morgen (Sonntag) ab 11:30 Uhr';
                $status_detail = 'Küche für heute geschlossen. Vorbestellung für Sonntag ab 11:30 Uhr möglich!';
            } else {
                $next_open_label = 'morgen ab 11:30 Uhr';
                $status_detail = 'Küche für heute geschlossen. Vorbestellung für morgen ab 11:30 Uhr möglich!';
            }
        }
    } elseif ($day_of_week === 7) {
        // So: 11:30 - 19:00 durchgehend
        if ($hour_min >= '11:30' && $hour_min < '19:00') {
            $is_open = true;
            $status_type = 'open';
            $status_label = 'Jetzt geöffnet';
            $status_detail = 'Sonntag durchgehend geöffnet bis 19:00 Uhr · Abholung bereit in ca. ' . $prep_time;
        } elseif ($hour_min < '11:30') {
            $next_open_label = 'heute um 11:30 Uhr';
            $status_detail = 'Küche öffnet am Sonntag um 11:30 Uhr. Vorbestellung jetzt möglich!';
        } else {
            // Nach 19:00 Uhr am Sonntag
            $next_open_label = 'morgen (Montag) ab 11:30 Uhr';
            $status_detail = 'Schönen Sonntagabend! Wir sind morgen ab 11:30 Uhr wieder für Sie da (Montags Sushi-Ruhetag).';
        }
    }

    return [
        'is_open'         => $is_open,
        'status_type'     => $status_type,
        'status_label'    => $status_label,
        'status_detail'   => $status_detail,
        'next_open_label' => $next_open_label,
        'time_now'        => $hour_min,
        'day_now'         => $day_of_week,
        'is_sushi_day'    => wowthai_is_sushi_available_on_day($day_of_week),
    ];
}

/**
 * Generiert die verfügbaren Abhol-Zeitslots für heute oder morgen
 */
function wowthai_get_available_pickup_slots($day = 'heute') {
    $now = current_datetime();
    $target_dt = ($day === 'morgen') ? $now->modify('+1 day') : $now;
    $target_day_of_week = (int) $target_dt->format('N');

    $raw_slots = [];

    if ($target_day_of_week === 7) {
        // Sonntag: 11:30 bis 18:30 (Küche schließt 19:00)
        for ($h = 11; $h <= 18; $h++) {
            $m_vals = ($h === 11) ? [30, 45] : (($h === 18) ? [0, 15, 30] : [0, 15, 30, 45]);
            foreach ($m_vals as $m) {
                $raw_slots[] = sprintf('%02d:%02d', $h, $m);
            }
        }
    } else {
        // Mo - Sa: Mittag 11:30 - 14:15, Abend 17:00 - 20:30 (Küche schließt 21:00)
        // Mittag
        for ($h = 11; $h <= 14; $h++) {
            $m_vals = ($h === 11) ? [30, 45] : (($h === 14) ? [0, 15] : [0, 15, 30, 45]);
            foreach ($m_vals as $m) {
                $raw_slots[] = sprintf('%02d:%02d', $h, $m);
            }
        }
        // Abend
        for ($h = 17; $h <= 20; $h++) {
            $m_vals = ($h === 20) ? [0, 15, 30] : [0, 15, 30, 45];
            foreach ($m_vals as $m) {
                $raw_slots[] = sprintf('%02d:%02d', $h, $m);
            }
        }
    }

    if ($day === 'morgen') {
        return $raw_slots;
    }

    // Für Heute: Filtere alle Slots heraus, die bereits vorbei sind
    // oder weniger als 25 Minuten ab jetzt sind (Küche braucht Vorlaufzeit)
    $min_time_ts = $now->getTimestamp() + (25 * 60);
    $min_time_str = wp_date('H:i', $min_time_ts);

    $filtered_slots = [];
    foreach ($raw_slots as $slot) {
        if ($slot >= $min_time_str) {
            $filtered_slots[] = $slot;
        }
    }

    return $filtered_slots;
}

/**
 * Gibt Schema.org JSON-LD structured data für Google / Suchmaschinen im Header aus
 */
function wowthai_output_restaurant_schema() {
    $info = wowthai_get_restaurant_data();
    $home = home_url('/');
    $menu_url = home_url('/menu/');
    $res_url = home_url('/reservations/');
    $logo_url = get_template_directory_uri() . '/assets/img/logo.png';
    $hero_url = get_template_directory_uri() . '/assets/img/hero.jpg';

    $schema = [
        '@context' => 'https://schema.org',
        '@type'    => 'Restaurant',
        '@id'      => $home . '#restaurant',
        'name'     => $info['name'],
        'legalName'=> $info['legal_name'],
        'url'      => $home,
        'image'    => [ $hero_url, $logo_url ],
        'logo'     => $logo_url,
        'telephone'=> $info['phone'],
        'email'    => $info['email'],
        'priceRange' => $info['price_range'],
        'servesCuisine' => $info['cuisine'],
        'acceptsReservations' => 'True',
        'hasMenu' => $menu_url,
        'menu'    => $menu_url,
        'potentialAction' => [
            [
                '@type' => 'ReserveAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $res_url,
                    'inLanguage' => 'de',
                    'actionPlatform' => [
                        'http://schema.org/DesktopWebPlatform',
                        'http://schema.org/MobileWebPlatform'
                    ]
                ],
                'result' => [
                    '@type' => 'FoodEstablishmentReservation',
                    'name'  => 'Tischreservierung WOW Thai Steyr'
                ]
            ],
            [
                '@type' => 'OrderAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $menu_url,
                    'inLanguage' => 'de',
                    'actionPlatform' => [
                        'http://schema.org/DesktopWebPlatform',
                        'http://schema.org/MobileWebPlatform'
                    ]
                ],
                'deliveryMethod' => 'http://purl.org/goodrelations/v1#DeliveryModePickUp'
            ]
        ],
        'address' => [
            '@type'           => 'PostalAddress',
            'streetAddress'   => $info['street'],
            'addressLocality'  => $info['city'],
            'addressRegion'    => $info['region'],
            'postalCode'       => $info['postal_code'],
            'addressCountry'   => $info['country'],
        ],
        'geo' => [
            '@type'     => 'GeoCoordinates',
            'latitude'  => $info['geo']['latitude'],
            'longitude' => $info['geo']['longitude'],
        ],
        'openingHoursSpecification' => [
            [
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                'opens'     => '11:30',
                'closes'    => '14:30',
            ],
            [
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                'opens'     => '17:00',
                'closes'    => '21:00',
            ],
            [
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => 'Sunday',
                'opens'     => '11:30',
                'closes'    => '19:00',
            ],
        ],
    ];

    echo "\n<!-- WOW Thai Steyr Structured Data -->\n";
    echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}
add_action( 'wp_head', 'wowthai_output_restaurant_schema', 20 );
