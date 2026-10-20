<?php
/**
 * WOW Thai Steyr - Restaurant Info, Opening Hours & Structured Data (Schema.org)
 * Delegiert sämtliche Öffnungs- und Küchenzeiten an inc/hours.php (Single Source of Truth).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/hours.php';

/**
 * Zentrale Restaurant-Stammdaten (Steyr)
 */
function wowthai_get_restaurant_data() {
    $hd = wowthai_hours_display();
    $opening_hours = [];
    foreach ($hd['rows'] as $row) {
        $opening_hours[$row['days']] = $row['hours'];
    }
    $opening_hours['Hinweis'] = $hd['sushi_note'];
    $data = [
        'name'               => 'WOW Thai Steyr',
        'legal_name'         => 'WOW Thai Steyr Gastronomie GmbH',
        'cuisine'            => ['Thailändisch', 'Asiatisch', 'Sushi', 'Wok'],
        'street'             => 'Leopold-Werndl-Straße 2 (City Point · 1. OG)',
        'postal_code'        => '4400',
        'city'               => 'Steyr',
        'region'             => 'Oberösterreich',
        'country'            => 'AT',
        'country_name'       => 'Österreich',
        'phone'              => '+43 7252 74973',
        'phone_clean'        => '+43725274973',
        'phone_tel'          => '+43725274973',
        'email'              => 'info@wow-thai-restaurant.at',
        'price_range'        => '€€',
        'chamber'            => 'Wirtschaftskammer Oberösterreich, Fachgruppe Gastronomie',
        'authority'          => 'Magistrat der Stadt Steyr',
        'trade_regulation'   => 'Gewerbeordnung 1994 (abrufbar unter www.ris.bka.gv.at)',
        'uid'                => (string) get_option('wowthai_legal_uid', ''),
        'fn_number'          => (string) get_option('wowthai_legal_fn', ''),
        'fn_court'           => (string) get_option('wowthai_legal_court', 'Landesgericht Steyr'),
        'managing_director'  => (string) get_option('wowthai_legal_director', ''),
        'geo'                => [
            'latitude'       => '48.0435',
            'longitude'      => '14.4178',
        ],
        'google_maps_url'    => 'https://maps.google.com/?q=Leopold-Werndl-Stra%C3%9Fe+2+4400+Steyr',
        'opening_hours_text' => $opening_hours,
    ];
    return apply_filters('wowthai_restaurant_data', $data);
}

/**
 * Liefert eine verlässliche E-Mail-Adresse für Reservierungs- & Systembenachrichtigungen.
 * Verhindert, dass E-Mails an Platzhalter wie dev-email@wpengine.local verloren gehen.
 */
function wowthai_get_notification_email(): string {
    $custom = trim((string) get_option('wowthai_notification_email', ''));
    if ($custom !== '' && is_email($custom)) {
        return $custom;
    }
    $admin_email = trim((string) get_option('admin_email'));
    if (
        !is_email($admin_email) ||
        preg_match('/\.(local|invalid|test|example)$/i', $admin_email) ||
        stripos($admin_email, 'wpengine.local') !== false
    ) {
        $info = wowthai_get_restaurant_data();
        return $info['email'];
    }
    return $admin_email;
}

/**
 * Korrigiert die Absender-E-Mail-Adresse, falls WordPress noch auf .local steht.
 */
function wowthai_filter_wp_mail_from($from_email) {
    if (
        empty($from_email) ||
        preg_match('/\.(local|invalid|test|example)$/i', (string) $from_email) ||
        stripos((string) $from_email, 'wpengine.local') !== false ||
        stripos((string) $from_email, 'wordpress@') === 0
    ) {
        return wowthai_get_notification_email();
    }
    return $from_email;
}
add_filter('wp_mail_from', 'wowthai_filter_wp_mail_from', 20);

function wowthai_filter_wp_mail_from_name($from_name) {
    if (empty($from_name) || $from_name === 'WordPress') {
        return 'WOW Thai Steyr';
    }
    return $from_name;
}
add_filter('wp_mail_from_name', 'wowthai_filter_wp_mail_from_name', 20);

/**
 * Prüft ob am angegebenen Wochentag Sushi verfügbar ist
 * $day_of_week: 1 (Montag) bis 7 (Sonntag)
 */
function wowthai_is_sushi_available_on_day($day_of_week = null) {
    return wowthai_sushi_available_on_dow($day_of_week !== null ? (int) $day_of_week : null);
}

/**
 * Ermittelt aktuellen Öffnungsstatus basierend auf der aktuellen Uhrzeit (Europe/Vienna)
 */
function wowthai_get_opening_status() {
    return wowthai_compute_opening_status();
}

/**
 * Generiert die verfügbaren Abhol-Zeitslots für heute oder morgen
 */
function wowthai_get_available_pickup_slots($day = 'heute') {
    $now = wowthai_pickup_now();
    return wowthai_pickup_slots_for_date($day === 'morgen' ? $now->modify('+1 day') : $now, $now);
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
        'hasMap' => $info['google_maps_url'],
        'openingHoursSpecification' => wowthai_schema_opening_hours(),
    ];

    echo "\n<!-- WOW Thai Steyr - Google Restaurant Schema.org JSON-LD -->\n";
    echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . "</script>\n";
}
add_action( 'wp_head', 'wowthai_output_restaurant_schema', 5 );

/**
 * Gibt Schema.org JSON-LD für die Speisekarte (Menu & MenuSection) auf /menu/ aus.
 */
function wowthai_output_menu_schema() {
    if (!is_page('menu') && !is_page('speisekarte')) {
        return;
    }

    if (!function_exists('wowthai_get_menu_structure')) {
        return;
    }

    $menu_data = wowthai_get_menu_structure();
    $menu_url  = home_url('/menu/');

    $sections = [];
    foreach (['restaurant', 'sushi'] as $type) {
        if (empty($menu_data[$type])) {
            continue;
        }
        foreach ($menu_data[$type] as $sec) {
            $items = [];
            foreach (array_slice($sec['products'] ?? [], 0, 10) as $p) {
                $raw_price = is_numeric($p['price'] ?? null) ? (float) $p['price'] : 0.0;
                $item = [
                    '@type'       => 'MenuItem',
                    'name'        => $p['title'] ?? '',
                    'description' => $p['description'] ?? '',
                ];
                if ($raw_price > 0) {
                    $item['offers'] = [
                        '@type'         => 'Offer',
                        'price'         => number_format($raw_price, 2, '.', ''),
                        'priceCurrency' => 'EUR',
                    ];
                }
                if (!empty($p['image_url'])) {
                    $item['image'] = $p['image_url'];
                }
                $items[] = $item;
            }

            if (!empty($items)) {
                $sections[] = [
                    '@type'       => 'MenuSection',
                    'name'        => $sec['name'] ?? '',
                    'hasMenuItem' => $items,
                ];
            }
        }
    }

    if (empty($sections)) {
        return;
    }

    $schema = [
        '@context'       => 'https://schema.org',
        '@type'          => 'Menu',
        '@id'            => $menu_url . '#menu',
        'name'           => 'Speisekarte & Sushi – WOW Thai Steyr',
        'url'            => $menu_url,
        'inLanguage'     => 'de',
        'hasMenuSection' => $sections,
    ];

    echo "\n<!-- WOW Thai Steyr - Menu Schema.org JSON-LD -->\n";
    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
}
add_action('wp_head', 'wowthai_output_menu_schema', 6);

