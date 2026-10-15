<?php
/**
 * WOW Thai Steyr - Restaurant Info, Opening Hours & Structured Data (Schema.org)
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
            'Mo–Sa' => '11:00–14:30 · 17:00–22:00 Uhr',
            'So'    => '11:30–20:00 Uhr',
        ],
    ];
}

/**
 * Ermittelt aktuellen Öffnungsstatus basierend auf der aktuellen Uhrzeit (Europe/Vienna)
 */
function wowthai_get_opening_status() {
    // Aktuelle Zeit in der WordPress-Zeitzone
    $now = current_datetime(); // DateTimeImmutable mit WP-Timezone
    $day_of_week = (int) $now->format('N'); // 1 = Mo, 7 = So
    $hour_min = $now->format('H:i');

    $is_open = false;
    $status_label = 'Vorbestellung möglich';
    $status_detail = '';
    $status_type = 'preorder'; // 'open' oder 'preorder'

    if ($day_of_week >= 1 && $day_of_week <= 6) {
        // Mo - Sa: 11:00 - 14:30 & 17:00 - 22:00
        if ($hour_min >= '11:00' && $hour_min < '14:30') {
            $is_open = true;
            $status_type = 'open';
            $status_label = 'Jetzt geöffnet';
            $status_detail = 'Mittagsservice bis 14:30 Uhr · Abholung bereit in ca. 20–30 Min.';
        } elseif ($hour_min >= '17:00' && $hour_min < '22:00') {
            $is_open = true;
            $status_type = 'open';
            $status_label = 'Jetzt geöffnet';
            $status_detail = 'Abendservice bis 22:00 Uhr · Abholung bereit in ca. 20–30 Min.';
        } elseif ($hour_min < '11:00') {
            $status_detail = 'Küche öffnet heute um 11:00 Uhr. Sie können jetzt für den Mittag vorbestellen!';
        } elseif ($hour_min >= '14:30' && $hour_min < '17:00') {
            $status_detail = 'Nachmittagspause · Küche öffnet wieder um 17:00 Uhr. Vorbestellung jetzt möglich!';
        } else {
            $status_detail = 'Restaurant schließt für heute. Sie können gerne für morgen vorbestellen!';
        }
    } elseif ($day_of_week === 7) {
        // So: 11:30 - 20:00 durchgehend
        if ($hour_min >= '11:30' && $hour_min < '20:00') {
            $is_open = true;
            $status_type = 'open';
            $status_label = 'Jetzt geöffnet';
            $status_detail = 'Sonntag durchgehend geöffnet bis 20:00 Uhr.';
        } elseif ($hour_min < '11:30') {
            $status_detail = 'Küche öffnet am Sonntag um 11:30 Uhr. Vorbestellung jetzt möglich!';
        } else {
            $status_detail = 'Schönen Sonntagabend! Wir sind morgen ab 11:00 Uhr wieder für Sie da.';
        }
    }

    return [
        'is_open'       => $is_open,
        'status_type'   => $status_type,
        'status_label'  => $status_label,
        'status_detail' => $status_detail,
        'time_now'      => $hour_min,
        'day_now'       => $day_of_week,
    ];
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
                'opens'     => '11:00',
                'closes'    => '14:30',
            ],
            [
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                'opens'     => '17:00',
                'closes'    => '22:00',
            ],
            [
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => 'Sunday',
                'opens'     => '11:30',
                'closes'    => '20:00',
            ],
        ],
    ];

    echo "\n<!-- WOW Thai Steyr Structured Data -->\n";
    echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}
add_action( 'wp_head', 'wowthai_output_restaurant_schema', 20 );
