<?php
/**
 * WOW Thai Multi-Tenant Branch Configuration Engine
 *
 * Single Source of Truth for branch identity, localization, feature flags,
 * opening schedule overrides, and delivery parameters across all chain locations.
 *
 * Supported Branches:
 * - 'steyr'    : Flagship restaurant (City Point Steyr, Oberösterreich)
 * - 'valentin' : Restaurant branch (St. Valentin, Niederösterreich)
 * - 'saalbach' : Alpine restaurant branch (Saalbach Hinterglemm, Salzburg)
 * - 'express'  : Dedicated delivery platform (WoW Express St. Valentin)
 */

if (!defined('ABSPATH')) {
    // Defensive check: Allow standalone CLI unit tests to require this file
}

/**
 * Validates whether a given string is a recognized branch ID.
 *
 * @param mixed $branch_id
 * @return bool
 */
function wowthai_is_branch_valid($branch_id): bool {
    if (!is_string($branch_id) || $branch_id === '') {
        return false;
    }
    return in_array($branch_id, ['steyr', 'valentin', 'saalbach', 'express'], true);
}

/**
 * Resolves the active branch ID via deterministic 4-stage hierarchy:
 * 1. Constant WOWTHAI_BRANCH in wp-config.php
 * 2. Database option 'wowthai_branch' (or 'wowthai_branch_id')
 * 3. Host header port / string matching (8881=>steyr, 8882=>valentin, 8883=>saalbach, 8884=>express)
 * 4. Default fallback: 'steyr'
 *
 * Filterable via 'wowthai_current_branch_id'.
 *
 * @return string ('steyr'|'valentin'|'saalbach'|'express')
 */
function wowthai_get_branch_id(): string {
    static $resolved_id = null;
    if ($resolved_id !== null) {
        return $resolved_id;
    }

    $branch_id = null;

    // Stage 1: wp-config.php constant
    if (defined('WOWTHAI_BRANCH') && is_string(WOWTHAI_BRANCH) && WOWTHAI_BRANCH !== '') {
        $candidate = strtolower(trim((string) WOWTHAI_BRANCH));
        if (wowthai_is_branch_valid($candidate)) {
            $branch_id = $candidate;
        }
    }

    // Stage 2: Database option
    if ($branch_id === null && function_exists('get_option')) {
        $opt = get_option('wowthai_branch', '');
        if (empty($opt)) {
            $opt = get_option('wowthai_branch_id', '');
        }
        if (is_string($opt) && $opt !== '') {
            $candidate = strtolower(trim($opt));
            if (wowthai_is_branch_valid($candidate)) {
                $branch_id = $candidate;
            }
        }
    }

    // Stage 3: Port and Host header inspection
    if ($branch_id === null) {
        $raw_port = (string) ($_SERVER['SERVER_PORT'] ?? '');
        $raw_host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));

        // Port detection (Studio port isolation: 8881=steyr, 8882=valentin, 8883=saalbach, 8884=express)
        $port = $raw_port;
        if ($port === '' && strpos($raw_host, ':') !== false) {
            $parts = explode(':', $raw_host);
            $port = end($parts);
        }

        switch ($port) {
            case '8881':
                $branch_id = 'steyr';
                break;
            case '8882':
                $branch_id = 'valentin';
                break;
            case '8883':
                $branch_id = 'saalbach';
                break;
            case '8884':
                $branch_id = 'express';
                break;
        }

        // Hostname substring detection
        if ($branch_id === null && $raw_host !== '') {
            if (strpos($raw_host, 'valentin') !== false) {
                $branch_id = 'valentin';
            } elseif (strpos($raw_host, 'saalbach') !== false) {
                $branch_id = 'saalbach';
            } elseif (strpos($raw_host, 'express') !== false) {
                $branch_id = 'express';
            } elseif (strpos($raw_host, 'steyr') !== false) {
                $branch_id = 'steyr';
            }
        }
    }

    // Stage 4: Default fallback
    if ($branch_id === null || !wowthai_is_branch_valid($branch_id)) {
        $branch_id = 'steyr';
    }

    // Stage 5: Extensibility filter hook
    if (function_exists('apply_filters')) {
        $filtered = apply_filters('wowthai_current_branch_id', $branch_id);
        if (wowthai_is_branch_valid($filtered)) {
            $branch_id = (string) $filtered;
        }
    }

    $resolved_id = $branch_id;
    return $resolved_id;
}

/**
 * Backwards compatibility alias for wowthai_get_branch_id().
 */
function wowthai_get_active_branch_id(): string {
    return wowthai_get_branch_id();
}

/**
 * Backwards compatibility alias for wowthai_get_all_branches().
 *
 * @return array<string, array>
 */
function wowthai_get_branches(): array {
    return wowthai_get_all_branches();
}

/**
 * Returns the immutable dictionary of all 4 branch configurations.
 *
 * @return array<string, array>
 */
function wowthai_get_all_branches(): array {
    return [
        'steyr' => [
            'id'             => 'steyr',
            'name'           => 'WOW Thai Steyr',
            'short_name'     => 'WoW Thai',
            'brand_sub'      => 'STEYR · CITY POINT',
            'legal_name'     => 'WOW Thai Steyr Gastronomie GmbH',
            'street'         => 'Leopold-Werndl-Straße 2 (City Point · 1. OG)',
            'street_short'   => 'City Point · 1. OG',
            'postal_code'    => '4400',
            'city'           => 'Steyr',
            'region'         => 'Oberösterreich',
            'country'        => 'AT',
            'country_name'   => 'Österreich',
            'phone'          => '+43 7252 74973',
            'phone_clean'    => '+43725274973',
            'phone_tel'      => '+43725274973',
            'email'          => 'info@wow-thai-restaurant.at',
            'website'        => 'wow-thai-steyr.at',
            'google_maps_url'=> 'https://maps.google.com/?q=Leopold-Werndl-Stra%C3%9Fe+2+4400+Steyr',
            'geo'            => [
                'lat'       => '48.0435',
                'lng'       => '14.4178',
                'latitude'  => '48.0435',
                'longitude' => '14.4178',
            ],
            'has_dining'     => true,
            'has_dinein'     => true,
            'has_takeaway'   => true,
            'has_delivery'   => false,
            'has_reservations'=> true,
            'has_sushi'      => true,
            'has_mittagsmenu'=> true,
            'cuisine'        => ['Thailändisch', 'Asiatisch', 'Sushi', 'Wok'],
            'price_range'    => '€€',
            'hero_tagline'   => 'Authentische Thai-Küche & frisches Sushi in Steyr',
            'hours_schedule' => [
                1 => [['11:00', '14:30'], ['17:00', '21:00']], // hours-ok
                2 => [['11:00', '14:30'], ['17:00', '21:00']], // hours-ok
                3 => [['11:00', '14:30'], ['17:00', '21:00']], // hours-ok
                4 => [['11:00', '14:30'], ['17:00', '21:00']], // hours-ok
                5 => [['11:00', '14:30'], ['17:00', '21:00']], // hours-ok
                6 => [['11:00', '14:30'], ['17:00', '21:00']], // hours-ok
                7 => [['11:30', '19:00']], // hours-ok
            ],
            'delivery'       => null,
            'delivery_zones' => null,
            'imprint_info'   => [
                'chamber'           => 'Wirtschaftskammer Oberösterreich, Fachgruppe Gastronomie',
                'authority'         => 'Magistrat der Stadt Steyr',
                'trade_regulation'  => 'Gewerbeordnung 1994 (abrufbar unter www.ris.bka.gv.at)',
                'fn_court'          => 'Landesgericht Steyr',
                'uid'               => '',
                'fn_number'         => '',
                'managing_director' => '',
                'business_purpose'  => 'Gastronomiebetrieb (Restaurant, Zubereitung thailändischer Speisen, Sushi & Takeaway)',
            ],
        ],
        'valentin' => [
            'id'             => 'valentin',
            'name'           => 'WOW Thai St. Valentin',
            'short_name'     => 'WoW Thai',
            'brand_sub'      => 'ST. VALENTIN',
            'legal_name'     => 'WOW Thai St. Valentin Gastronomie GmbH',
            'street'         => 'Westbahnstraße 1',
            'street_short'   => 'Westbahnstraße',
            'postal_code'    => '4300',
            'city'           => 'St. Valentin',
            'region'         => 'Niederösterreich',
            'country'        => 'AT',
            'country_name'   => 'Österreich',
            'phone'          => '+43 7435 50000',
            'phone_clean'    => '+43743550000',
            'phone_tel'      => '+43743550000',
            'email'          => 'valentin@wow-thai.at',
            'website'        => 'wow-thai-valentin.at',
            'google_maps_url'=> 'https://maps.google.com/?q=Westbahnstra%C3%9Fe+1+4300+St.+Valentin',
            'geo'            => [
                'lat'       => '48.1750',
                'lng'       => '14.5350',
                'latitude'  => '48.1750',
                'longitude' => '14.5350',
            ],
            'has_dining'     => true,
            'has_dinein'     => true,
            'has_takeaway'   => true,
            'has_delivery'   => false,
            'has_reservations'=> true,
            'has_sushi'      => true,
            'has_mittagsmenu'=> true,
            'cuisine'        => ['Thailändisch', 'Asiatisch', 'Sushi', 'Wok'],
            'price_range'    => '€€',
            'hero_tagline'   => 'Authentische Thai-Küche & frisches Sushi in St. Valentin',
            'hours_schedule' => null, // Inherits default regular schedule
            'delivery'       => null,
            'delivery_zones' => null,
            'imprint_info'   => [
                'chamber'           => 'Wirtschaftskammer Niederösterreich, Fachgruppe Gastronomie',
                'authority'         => 'Bezirkshauptmannschaft Amstetten',
                'trade_regulation'  => 'Gewerbeordnung 1994 (abrufbar unter www.ris.bka.gv.at)',
                'fn_court'          => 'Landesgericht St. Pölten',
                'uid'               => '',
                'fn_number'         => '',
                'managing_director' => '',
                'business_purpose'  => 'Gastronomiebetrieb (Restaurant, Zubereitung thailändischer Speisen, Sushi & Takeaway)',
            ],
        ],
        'saalbach' => [
            'id'             => 'saalbach',
            'name'           => 'WOW Thai Saalbach',
            'short_name'     => 'WoW Thai',
            'brand_sub'      => 'SAALBACH · HINTERGLEMM',
            'legal_name'     => 'WOW Thai Saalbach Gastronomie GmbH',
            'street'         => 'Dorfplatz 1',
            'street_short'   => 'Dorfplatz 1',
            'postal_code'    => '5753',
            'city'           => 'Saalbach',
            'region'         => 'Salzburg',
            'country'        => 'AT',
            'country_name'   => 'Österreich',
            'phone'          => '+43 6541 70000',
            'phone_clean'    => '+43654170000',
            'phone_tel'      => '+43654170000',
            'email'          => 'saalbach@wow-thai.at',
            'website'        => 'wowthai-saalbach.at',
            'google_maps_url'=> 'https://maps.google.com/?q=Dorfplatz+1+5753+Saalbach',
            'geo'            => [
                'lat'       => '47.3917',
                'lng'       => '12.6364',
                'latitude'  => '47.3917',
                'longitude' => '12.6364',
            ],
            'has_dining'     => true,
            'has_dinein'     => true,
            'has_takeaway'   => true,
            'has_delivery'   => false,
            'has_reservations'=> true,
            'has_sushi'      => false, // Alpine focus: hot wok, curry, soup
            'has_mittagsmenu'=> false, // Alpine continuous service through day
            'cuisine'        => ['Thailändisch', 'Asiatisch', 'Wok', 'Curry'],
            'price_range'    => '€€',
            'hero_tagline'   => 'Authentische Thai-Küche & alpine Wok-Spezialitäten in Saalbach',
            'hours_schedule' => [
                // Continuous winter alpine schedule
                1 => [['11:30', '22:00']], // hours-ok
                2 => [['11:30', '22:00']], // hours-ok
                3 => [['11:30', '22:00']], // hours-ok
                4 => [['11:30', '22:00']], // hours-ok
                5 => [['11:30', '22:00']], // hours-ok
                6 => [['11:30', '22:00']], // hours-ok
                7 => [['11:30', '21:00']], // hours-ok
            ],
            'delivery'       => null,
            'delivery_zones' => null,
            'imprint_info'   => [
                'chamber'           => 'Wirtschaftskammer Salzburg, Fachgruppe Gastronomie',
                'authority'         => 'Bezirkshauptmannschaft Zell am See',
                'trade_regulation'  => 'Gewerbeordnung 1994 (abrufbar unter www.ris.bka.gv.at)',
                'fn_court'          => 'Landesgericht Salzburg',
                'uid'               => '',
                'fn_number'         => '',
                'managing_director' => '',
                'business_purpose'  => 'Gastronomiebetrieb (Alpines Thai-Restaurant, Wok-Küche & Takeaway)',
            ],
        ],
        'express' => [
            'id'             => 'express',
            'name'           => 'WoW Express St. Valentin',
            'short_name'     => 'WoW Express',
            'brand_sub'      => 'LIEFERUNG & TAKEAWAY',
            'legal_name'     => 'WOW Express St. Valentin GmbH',
            'street'         => 'Westbahnstraße 1',
            'street_short'   => 'Westbahnstraße',
            'postal_code'    => '4300',
            'city'           => 'St. Valentin',
            'region'         => 'Niederösterreich',
            'country'        => 'AT',
            'country_name'   => 'Österreich',
            'phone'          => '+43 7435 50001',
            'phone_clean'    => '+43743550001',
            'phone_tel'      => '+43743550001',
            'email'          => 'express@wow-thai.at',
            'website'        => 'wow-express.at',
            'google_maps_url'=> 'https://maps.google.com/?q=Westbahnstra%C3%9Fe+1+4300+St.+Valentin',
            'geo'            => [
                'lat'       => '48.1750',
                'lng'       => '14.5350',
                'latitude'  => '48.1750',
                'longitude' => '14.5350',
            ],
            'has_dining'     => false, // Delivery & Takeaway only!
            'has_dinein'     => false,
            'has_takeaway'   => true,
            'has_delivery'   => true, // Standalone delivery platform
            'has_reservations'=> false, // No table reservations
            'has_sushi'      => true,
            'has_mittagsmenu'=> true,
            'cuisine'        => ['Thailändisch', 'Sushi', 'Wok', 'Lieferservice'],
            'price_range'    => '€€',
            'hero_tagline'   => 'Schnelle Thai-Lieferung & Sushi in St. Valentin, Enns & Umgebung',
            'hours_schedule' => [
                1 => [['11:00', '14:00'], ['17:00', '21:30']], // hours-ok
                2 => [['11:00', '14:00'], ['17:00', '21:30']], // hours-ok
                3 => [['11:00', '14:00'], ['17:00', '21:30']], // hours-ok
                4 => [['11:00', '14:00'], ['17:00', '21:30']], // hours-ok
                5 => [['11:00', '14:00'], ['17:00', '21:30']], // hours-ok
                6 => [['11:00', '14:00'], ['17:00', '21:30']], // hours-ok
                7 => [['11:30', '20:30']], // hours-ok
            ],
            'delivery'       => [
                'est_time_min'       => 30,
                'est_time_max'       => 45,
                'eta_display'        => '30–45 Minuten',
                'zones'              => [
                    'core'     => [
                        'name'           => 'Kernzone (St. Valentin)',
                        'postcodes'      => ['4300'],
                        'min_order'      => 20.00,
                        'fee'            => 2.50,
                        'free_threshold' => 30.00,
                    ],
                    'extended' => [
                        'name'           => 'Erweiterte Lieferzone (Ennsdorf, Enns, Asten)',
                        'postcodes'      => ['4303', '4470', '4481'],
                        'min_order'      => 25.00,
                        'fee'            => 3.90,
                        'free_threshold' => 45.00,
                    ],
                ],
                'out_of_zone_notice' => 'Lieferung an diese Postleitzahl leider nicht möglich. Gerne können Sie Ihre Speisen zur Selbstabholung bestellen!',
            ],
            'delivery_zones' => ['4300', '4303', '4470', '4481'],
            'imprint_info'   => [
                'chamber'           => 'Wirtschaftskammer Niederösterreich, Fachgruppe Gastronomie',
                'authority'         => 'Bezirkshauptmannschaft Amstetten',
                'trade_regulation'  => 'Gewerbeordnung 1994 (abrufbar unter www.ris.bka.gv.at)',
                'fn_court'          => 'Landesgericht St. Pölten',
                'uid'               => '',
                'fn_number'         => '',
                'managing_director' => '',
                'business_purpose'  => 'Zustelldienst für Speisen und Getränke (Online-Food-Delivery & Takeaway)',
            ],
        ],
    ];
}

/**
 * Returns the complete branch configuration array for the requested branch (or active branch if null).
 *
 * Filterable via 'wowthai_branch_config'.
 *
 * @param string|null $branch_id
 * @return array
 */
function wowthai_get_branch_config(?string $branch_id = null): array {
    $target_id = ($branch_id !== null && wowthai_is_branch_valid($branch_id))
        ? $branch_id
        : wowthai_get_branch_id();

    $branches = wowthai_get_all_branches();
    $config   = $branches[$target_id] ?? $branches['steyr'];

    if (function_exists('apply_filters')) {
        $config = apply_filters('wowthai_branch_config', $config, $target_id);
    }

    return (array) $config;
}

/**
 * Backwards compatibility alias for wowthai_get_branch_config().
 *
 * @param string|null $branch_id
 * @return array
 */
function wowthai_get_active_branch_config(?string $branch_id = null): array {
    return wowthai_get_branch_config($branch_id);
}

/**
 * Helper Getters for Branch Metadata
 */

function wowthai_get_branch_name(?string $branch_id = null): string {
    $config = wowthai_get_branch_config($branch_id);
    return (string) ($config['name'] ?? 'WOW Thai');
}

function wowthai_get_branch_short_name(?string $branch_id = null): string {
    $config = wowthai_get_branch_config($branch_id);
    return (string) ($config['short_name'] ?? 'WoW Thai');
}

function wowthai_get_branch_brand_sub(?string $branch_id = null): string {
    $config = wowthai_get_branch_config($branch_id);
    return (string) ($config['brand_sub'] ?? '');
}

function wowthai_get_branch_legal_name(?string $branch_id = null): string {
    $config = wowthai_get_branch_config($branch_id);
    return (string) ($config['legal_name'] ?? $config['name'] ?? '');
}

function wowthai_get_branch_address(?string $branch_id = null): string {
    $config = wowthai_get_branch_config($branch_id);
    $street = (string) ($config['street'] ?? '');
    $plz    = (string) ($config['postal_code'] ?? '');
    $city   = (string) ($config['city'] ?? '');
    return trim("{$street}, {$plz} {$city}");
}

function wowthai_get_branch_street(?string $branch_id = null): string {
    $config = wowthai_get_branch_config($branch_id);
    return (string) ($config['street'] ?? '');
}

function wowthai_get_branch_street_short(?string $branch_id = null): string {
    $config = wowthai_get_branch_config($branch_id);
    return (string) ($config['street_short'] ?? $config['street'] ?? '');
}

function wowthai_get_branch_postcode(?string $branch_id = null): string {
    $config = wowthai_get_branch_config($branch_id);
    return (string) ($config['postal_code'] ?? '');
}

function wowthai_get_branch_city(?string $branch_id = null): string {
    $config = wowthai_get_branch_config($branch_id);
    return (string) ($config['city'] ?? '');
}

function wowthai_get_branch_region(?string $branch_id = null): string {
    $config = wowthai_get_branch_config($branch_id);
    return (string) ($config['region'] ?? '');
}

function wowthai_get_branch_country(?string $branch_id = null): string {
    $config = wowthai_get_branch_config($branch_id);
    return (string) ($config['country'] ?? 'AT');
}

function wowthai_get_branch_country_name(?string $branch_id = null): string {
    $config = wowthai_get_branch_config($branch_id);
    return (string) ($config['country_name'] ?? 'Österreich');
}

function wowthai_get_branch_phone(?string $branch_id = null): string {
    $config = wowthai_get_branch_config($branch_id);
    return (string) ($config['phone'] ?? '');
}

function wowthai_get_branch_phone_clean(?string $branch_id = null): string {
    $config = wowthai_get_branch_config($branch_id);
    return (string) ($config['phone_clean'] ?? preg_replace('/[^\d+]/', '', (string) ($config['phone'] ?? '')));
}

function wowthai_get_branch_phone_tel(?string $branch_id = null): string {
    $config = wowthai_get_branch_config($branch_id);
    return (string) ($config['phone_tel'] ?? wowthai_get_branch_phone_clean($branch_id));
}

function wowthai_get_branch_email(?string $branch_id = null): string {
    $config = wowthai_get_branch_config($branch_id);
    return (string) ($config['email'] ?? '');
}

function wowthai_get_branch_website(?string $branch_id = null): string {
    $config = wowthai_get_branch_config($branch_id);
    return (string) ($config['website'] ?? '');
}

function wowthai_get_branch_maps_url(?string $branch_id = null): string {
    $config = wowthai_get_branch_config($branch_id);
    return (string) ($config['google_maps_url'] ?? '');
}

function wowthai_get_branch_geo(?string $branch_id = null): array {
    $config = wowthai_get_branch_config($branch_id);
    return (array) ($config['geo'] ?? ['lat' => '48.0435', 'lng' => '14.4178', 'latitude' => '48.0435', 'longitude' => '14.4178']);
}

function wowthai_branch_has_dining(?string $branch_id = null): bool {
    $config = wowthai_get_branch_config($branch_id);
    return !empty($config['has_dining']);
}

function wowthai_branch_has_dinein(?string $branch_id = null): bool {
    return wowthai_branch_has_dining($branch_id);
}

function wowthai_branch_has_takeaway(?string $branch_id = null): bool {
    $config = wowthai_get_branch_config($branch_id);
    return !empty($config['has_takeaway']);
}

function wowthai_branch_has_delivery(?string $branch_id = null): bool {
    $config = wowthai_get_branch_config($branch_id);
    return !empty($config['has_delivery']);
}

function wowthai_branch_has_reservations(?string $branch_id = null): bool {
    $config = wowthai_get_branch_config($branch_id);
    return !empty($config['has_reservations']);
}

function wowthai_branch_has_sushi(?string $branch_id = null): bool {
    $config = wowthai_get_branch_config($branch_id);
    return !empty($config['has_sushi']);
}

function wowthai_branch_has_mittagsmenu(?string $branch_id = null): bool {
    $config = wowthai_get_branch_config($branch_id);
    return !empty($config['has_mittagsmenu']);
}

/**
 * Checks whether a branch supports a specific capability or feature.
 *
 * Supported features:
 * - 'dining' / 'dinein'
 * - 'takeaway' / 'pickup'
 * - 'delivery'
 * - 'reservations'
 * - 'sushi'
 * - 'mittagsmenu' / 'lunch'
 *
 * @param string $feature
 * @param string|null $branch_id
 * @return bool
 */
function wowthai_branch_has_feature(string $feature, ?string $branch_id = null): bool {
    $config = wowthai_get_branch_config($branch_id);
    switch (strtolower(trim($feature))) {
        case 'dining':
        case 'dinein':
            return !empty($config['has_dining']);
        case 'takeaway':
        case 'pickup':
            return !empty($config['has_takeaway']);
        case 'delivery':
            return !empty($config['has_delivery']);
        case 'reservations':
            return !empty($config['has_reservations']);
        case 'sushi':
            return !empty($config['has_sushi']);
        case 'mittagsmenu':
        case 'lunch':
            return !empty($config['has_mittagsmenu']);
        default:
            return false;
    }
}

/**
 * Returns delivery settings for a branch (zones, minimum orders, fees, thresholds).
 *
 * @param string|null $branch_id
 * @return array|null
 */
function wowthai_get_branch_delivery_config(?string $branch_id = null): ?array {
    $config = wowthai_get_branch_config($branch_id);
    return $config['delivery'] ?? null;
}
