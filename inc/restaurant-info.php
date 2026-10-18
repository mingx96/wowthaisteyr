<?php
/**
 * WOW Thai - Restaurant Info, Opening Hours & Structured Data (Schema.org)
 * Delegiert sämtliche Stammdaten an inc/branch-config.php und Öffnungszeiten an inc/hours.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/branch-config.php';
require_once __DIR__ . '/hours.php';

/**
 * Zentrale Restaurant-Stammdaten (Multi-Tenant delegiert)
 *
 * @param string|null $branch_id Optional branch ID override.
 * @return array
 */
function wowthai_get_restaurant_data(?string $branch_id = null): array {
    $branch = function_exists('wowthai_get_branch_config')
        ? wowthai_get_branch_config($branch_id)
        : [];

    $hd = function_exists('wowthai_hours_display') ? wowthai_hours_display($branch_id) : [];
    $imprint = $branch['imprint_info'] ?? [];

    $data = [
        'id'                 => $branch['id'] ?? 'steyr',
        'name'               => $branch['name'] ?? 'WOW Thai Steyr',
        'short_name'         => $branch['short_name'] ?? 'WoW Thai',
        'brand_sub'          => $branch['brand_sub'] ?? 'STEYR · CITY POINT',
        'legal_name'         => $branch['legal_name'] ?? 'WOW Thai Steyr Gastronomie GmbH',
        'cuisine'            => $branch['cuisine'] ?? ['Thailändisch', 'Asiatisch', 'Sushi', 'Wok'],
        'street'             => $branch['street'] ?? 'Leopold-Werndl-Straße 2 (City Point · 1. OG)',
        'street_short'       => $branch['street_short'] ?? 'City Point · 1. OG',
        'postal_code'        => $branch['postal_code'] ?? '4400',
        'postcode'           => $branch['postal_code'] ?? '4400',
        'city'               => $branch['city'] ?? 'Steyr',
        'region'             => $branch['region'] ?? 'Oberösterreich',
        'country'            => $branch['country'] ?? 'AT',
        'country_name'       => $branch['country_name'] ?? 'Österreich',
        'phone'              => $branch['phone'] ?? '+43 7252 74973',
        'phone_clean'        => $branch['phone_clean'] ?? '+43725274973',
        'phone_tel'          => $branch['phone_tel'] ?? ($branch['phone_clean'] ?? '+43725274973'),
        'email'              => $branch['email'] ?? 'info@wow-thai-restaurant.at',
        'website'            => $branch['website'] ?? 'wow-thai-steyr.at',
        'price_range'        => $branch['price_range'] ?? '€€',
        'chamber'            => $imprint['chamber'] ?? 'Wirtschaftskammer Oberösterreich, Fachgruppe Gastronomie',
        'authority'          => $imprint['authority'] ?? 'Magistrat der Stadt Steyr',
        'trade_regulation'   => $imprint['trade_regulation'] ?? 'Gewerbeordnung 1994 (abrufbar unter www.ris.bka.gv.at)',
        'business_purpose'   => $imprint['business_purpose'] ?? 'Gastronomiebetrieb (Restaurant, Zubereitung thailändischer Speisen, Sushi & Takeaway)',
        'imprint_info'       => $imprint,
        'uid'                => function_exists('get_option') ? (string) get_option('wowthai_legal_uid', $imprint['uid'] ?? '') : ($imprint['uid'] ?? ''),
        'fn_number'          => function_exists('get_option') ? (string) get_option('wowthai_legal_fn', $imprint['fn_number'] ?? '') : ($imprint['fn_number'] ?? ''),
        'fn_court'           => function_exists('get_option') ? (string) get_option('wowthai_legal_court', $imprint['fn_court'] ?? 'Landesgericht Steyr') : ($imprint['fn_court'] ?? 'Landesgericht Steyr'),
        'managing_director'  => function_exists('get_option') ? (string) get_option('wowthai_legal_director', $imprint['managing_director'] ?? '') : ($imprint['managing_director'] ?? ''),
        'geo'                => [
            'latitude'       => (string) ($branch['geo']['latitude'] ?? $branch['geo']['lat'] ?? '48.0435'),
            'longitude'      => (string) ($branch['geo']['longitude'] ?? $branch['geo']['lng'] ?? '14.4178'),
        ],
        'google_maps_url'    => $branch['google_maps_url'] ?? 'https://maps.google.com/?q=Leopold-Werndl-Stra%C3%9Fe+2+4400+Steyr',
        'has_dining'         => $branch['has_dining'] ?? true,
        'has_dinein'         => $branch['has_dinein'] ?? ($branch['has_dining'] ?? true),
        'has_takeaway'       => $branch['has_takeaway'] ?? true,
        'has_delivery'       => $branch['has_delivery'] ?? false,
        'has_reservations'   => $branch['has_reservations'] ?? true,
        'has_sushi'          => $branch['has_sushi'] ?? true,
        'has_mittagsmenu'    => $branch['has_mittagsmenu'] ?? true,
        'opening_hours_text' => [
            'Mo–Sa'   => $hd['mosa_hours_uhr'] ?? '',
            'So'      => $hd['sun_hours_uhr'] ?? '',
            'Hinweis' => $hd['sushi_note'] ?? '',
        ],
    ];

    if (function_exists('apply_filters')) {
        return apply_filters('wowthai_restaurant_data', $data, $branch_id);
    }
    return $data;
}

/**
 * Alias for wowthai_get_restaurant_data().
 */
function wowthai_get_restaurant_info(?string $branch_id = null): array {
    return wowthai_get_restaurant_data($branch_id);
}

function wowthai_get_restaurant_name(?string $branch_id = null): string {
    return wowthai_get_branch_name($branch_id);
}

function wowthai_get_restaurant_phone(?string $branch_id = null): string {
    return wowthai_get_branch_phone($branch_id);
}

function wowthai_get_restaurant_email(?string $branch_id = null): string {
    return wowthai_get_branch_email($branch_id);
}

function wowthai_get_restaurant_address(?string $branch_id = null): string {
    return wowthai_get_branch_address($branch_id);
}

function wowthai_get_restaurant_city(?string $branch_id = null): string {
    return wowthai_get_branch_city($branch_id);
}

function wowthai_get_restaurant_postcode(?string $branch_id = null): string {
    return wowthai_get_branch_postcode($branch_id);
}

/**
 * Liefert eine verlässliche E-Mail-Adresse für Reservierungs- & Systembenachrichtigungen.
 */
function wowthai_get_notification_email(): string {
    if (!function_exists('get_option')) {
        return 'info@wow-thai-restaurant.at';
    }
    $custom = trim((string) get_option('wowthai_notification_email', ''));
    if ($custom !== '' && function_exists('is_email') && is_email($custom)) {
        return $custom;
    }
    $admin_email = trim((string) get_option('admin_email', ''));
    if (
        !function_exists('is_email') ||
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
        return wowthai_get_branch_name();
    }
    return $from_name;
}
add_filter('wp_mail_from_name', 'wowthai_filter_wp_mail_from_name', 20);

/**
 * Prüft ob am angegebenen Wochentag Sushi verfügbar ist
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
    $info     = wowthai_get_restaurant_data();
    $home     = home_url('/');
    $menu_url = home_url('/menu/');
    $res_url  = home_url('/reservations/');
    $logo_url = get_template_directory_uri() . '/assets/img/logo.png';
    $hero_url = get_template_directory_uri() . '/assets/img/hero.jpg';

    $potential_actions = [];

    // Table reservation action (only if supported)
    if (!empty($info['has_reservations'])) {
        $potential_actions[] = [
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
                'name'  => 'Tischreservierung ' . $info['name']
            ]
        ];
    }

    // Takeaway order action
    if (!empty($info['has_takeaway'])) {
        $potential_actions[] = [
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
        ];
    }

    // Delivery order action
    if (!empty($info['has_delivery'])) {
        $potential_actions[] = [
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
            'deliveryMethod' => 'http://purl.org/goodrelations/v1#DeliveryModeDirectDownload'
        ];
    }

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
        'acceptsReservations' => !empty($info['has_reservations']) ? 'True' : 'False',
        'hasMenu' => $menu_url,
        'menu'    => $menu_url,
        'potentialAction' => $potential_actions,
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

    echo "\n<!-- WOW Thai - Google Restaurant Schema.org JSON-LD -->\n";
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

    $info      = wowthai_get_restaurant_data();
    $menu_data = wowthai_get_menu_structure();
    $menu_url  = home_url('/menu/');

    $menu_types = ['restaurant'];
    if (!empty($info['has_sushi'])) {
        $menu_types[] = 'sushi';
    }

    $sections = [];
    foreach ($menu_types as $type) {
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

    $menu_title = !empty($info['has_sushi'])
        ? 'Speisekarte & Sushi – ' . $info['name']
        : 'Speisekarte – ' . $info['name'];

    $schema = [
        '@context'       => 'https://schema.org',
        '@type'          => 'Menu',
        '@id'            => $menu_url . '#menu',
        'name'           => $menu_title,
        'url'            => $menu_url,
        'inLanguage'     => 'de',
        'hasMenuSection' => $sections,
    ];

    echo "\n<!-- WOW Thai - Menu Schema.org JSON-LD -->\n";
    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
}
add_action('wp_head', 'wowthai_output_menu_schema', 6);
