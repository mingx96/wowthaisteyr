<?php
/**
 * WOW Thai Steyr - SEO Engine & Metadata Management
 *
 * Provides optimized titles, unique meta descriptions, XML sitemap pruning,
 * OpenGraph / Twitter Cards, robots directives, and BreadcrumbList Schema.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Liefert konsistente SEO-Metadaten für jede Seite der Website.
 */
function wowthai_get_seo_meta(): array {
    $theme_uri     = get_template_directory_uri();
    $default_image = $theme_uri . '/assets/img/hero.jpg';

    $b_name     = function_exists('wowthai_get_branch_name') ? wowthai_get_branch_name() : 'WOW Thai Steyr';
    $b_city     = function_exists('wowthai_get_branch_city') ? wowthai_get_branch_city() : 'Steyr';
    $b_street_s = function_exists('wowthai_get_branch_street_short') ? wowthai_get_branch_street_short() : 'City Point Steyr';
    $b_addr     = function_exists('wowthai_get_branch_address') ? wowthai_get_branch_address() : 'Leopold-Werndl-Straße 2, 4400 Steyr';
    $b_site     = function_exists('wowthai_get_branch_website') ? wowthai_get_branch_website() : 'wow-thai-steyr.at';
    $has_sushi  = function_exists('wowthai_branch_has_sushi') ? wowthai_branch_has_sushi() : true;
    $has_dlv    = function_exists('wowthai_branch_has_delivery') ? wowthai_branch_has_delivery() : false;

    if (is_front_page()) {
        $cuisine_label = $has_sushi ? 'Thailändisches Restaurant & Sushi Bar' : 'Authentisches Thailändisches Restaurant';
        $order_hint    = $has_dlv ? 'Online bestellen mit bequemer Lieferung oder Abholung.' : 'Tisch online reservieren oder Takeaway vorbestellen.';
        return [
            'title'       => "{$b_name} | {$cuisine_label} in {$b_city}",
            'desc'        => "Authentische thailändische Küche" . ($has_sushi ? ', Wok-Gerichte & frisches Sushi' : ' & Wok-Spezialitäten') . " bei {$b_name} ({$b_street_s}). {$order_hint}",
            'og_type'     => 'website',
            'og_image'    => $default_image,
            'robots'      => 'max-image-preview:large',
        ];
    }

    if (is_page('menu') || is_page('speisekarte')) {
        return [
            'title'       => "Speisekarte & Online Bestellen | {$b_name}",
            'desc'        => "Original thailändische Currys & Wok-Spezialitäten" . ($has_sushi ? ' & frisches Sushi' : '') . " bei {$b_name} in {$b_city} online bestellen.",
            'og_type'     => 'website',
            'og_image'    => $default_image,
            'robots'      => 'max-image-preview:large',
        ];
    }

    if (is_page('reservations') || is_page('tisch-reservieren')) {
        return [
            'title'       => "Tisch online reservieren | {$b_name} ({$b_city})",
            'desc'        => "Reservieren Sie Ihren Tisch bei {$b_name} ({$b_addr}) schnell und bequem online.",
            'og_type'     => 'website',
            'og_image'    => $default_image,
            'robots'      => 'max-image-preview:large',
        ];
    }

    if (is_page('impressum')) {
        return [
            'title'       => "Impressum & Rechtliche Hinweise | {$b_name}",
            'desc'        => "Impressum und Offenlegung nach §5 ECG, §14 UGB und §25 Mediengesetz für {$b_name}.",
            'og_type'     => 'article',
            'og_image'    => $default_image,
            'robots'      => 'max-image-preview:large',
        ];
    }

    if (is_page('datenschutz')) {
        return [
            'title'       => "Datenschutzerklärung & Privatsphäre | {$b_name}",
            'desc'        => "Informationen zum Datenschutz, DSGVO-Rechten und zur sicheren Datenverarbeitung beim Besuch und bei Online-Bestellungen auf {$b_site}.",
            'og_type'     => 'article',
            'og_image'    => $default_image,
            'robots'      => 'max-image-preview:large',
        ];
    }

    if (is_page('cart')) {
        return [
            'title'       => "Warenkorb | {$b_name}",
            'desc'        => "Ihr Warenkorb bei {$b_name}. Prüfen Sie Ihre ausgewählten Speisen vor der Bestellung.",
            'og_type'     => 'website',
            'og_image'    => $default_image,
            'robots'      => 'noindex, follow',
        ];
    }

    if (is_page('checkout')) {
        return [
            'title'       => "Kasse & " . ($has_dlv ? 'Lieferung' : 'Abholung') . " | {$b_name}",
            'desc'        => "Bestellung abschließen und " . ($has_dlv ? 'Lieferzeit' : 'Abholzeit') . " wählen bei {$b_name}.",
            'og_type'     => 'website',
            'og_image'    => $default_image,
            'robots'      => 'noindex, nofollow',
        ];
    }

    if (is_page('my-account')) {
        return [
            'title'       => "Mein Konto | {$b_name}",
            'desc'        => "Kundenkonto und Bestellübersicht bei {$b_name}.",
            'og_type'     => 'website',
            'og_image'    => $default_image,
            'robots'      => 'noindex, nofollow',
        ];
    }

    if (is_page('betrieb')) {
        return [
            'title'       => "{$b_name} Betrieb · Restaurant-Verwaltung",
            'desc'        => "Internes Betriebs-Portal für Küchenteam und Service von {$b_name}.",
            'og_type'     => 'website',
            'og_image'    => $default_image,
            'robots'      => 'noindex, nofollow',
        ];
    }

    // Standard-Fallback
    $fallback_title = get_the_title();
    return [
        'title'       => ($fallback_title ? $fallback_title . ' | ' : '') . $b_name,
        'desc'        => "Authentische thailändische Küche bei {$b_name}.",
        'og_type'     => 'website',
        'og_image'    => $default_image,
        'robots'      => 'max-image-preview:large',
    ];
}

/**
 * Überschreibt den Dokument-Titel über WordPress Core APIs.
 */
function wowthai_seo_title_filter($title): string {
    $meta = wowthai_get_seo_meta();
    return $meta['title'] ?? (string) $title;
}
add_filter('pre_get_document_title', 'wowthai_seo_title_filter', 20);

/**
 * Passt document_title_parts an für Themes mit title-tag Support.
 */
function wowthai_seo_title_parts($parts): array {
    $meta = wowthai_get_seo_meta();
    if (!empty($meta['title'])) {
        return ['title' => $meta['title']];
    }
    return $parts;
}
add_filter('document_title_parts', 'wowthai_seo_title_parts', 20);

/**
 * Steuert robots Meta-Tags sauber per Hook.
 */
function wowthai_seo_robots_filter($robots): array {
    $meta = wowthai_get_seo_meta();
    if (!empty($meta['robots'])) {
        if (strpos($meta['robots'], 'noindex') !== false) {
            $robots['noindex'] = true;
            $robots['nofollow'] = (strpos($meta['robots'], 'nofollow') !== false);
            unset($robots['max-image-preview']);
        } else {
            $robots['noindex'] = false;
            $robots['max-image-preview'] = 'large';
        }
    }
    return $robots;
}
add_filter('wp_robots', 'wowthai_seo_robots_filter', 20);

/**
 * Bereinigt die WordPress Core XML-Sitemap (wp-sitemap.xml):
 * 1. Entfernt WooCommerce Einzelprodukte (da alle 302 auf /menu/?dish=XX weiterleiten).
 * 2. Entfernt Benutzer-Sitemap (wp-sitemap-users-1.xml) zum Schutz von Admin-Benutzernamen.
 * 3. Entfernt WooCommerce Produkt-Kategorien (da das Menü zentral auf /menu/ dargestellt wird).
 * 4. Entfernt interne Utility-Seiten (/cart/, /checkout/, /my-account/, /betrieb/).
 */
add_filter('wp_sitemaps_post_types', function($post_types) {
    unset($post_types['product']);
    return $post_types;
});

add_filter('wp_sitemaps_add_provider', function($provider, $name) {
    if ($name === 'users') {
        return false;
    }
    return $provider;
}, 10, 2);

add_filter('wp_sitemaps_taxonomies', function($taxonomies) {
    unset($taxonomies['product_cat'], $taxonomies['product_tag']);
    return $taxonomies;
});

add_filter('wp_sitemaps_posts_query_args', function($args, $post_type) {
    if ($post_type === 'page') {
        $excluded_slugs = ['cart', 'checkout', 'my-account', 'betrieb', 'shop'];
        $excluded_ids = [];
        foreach ($excluded_slugs as $slug) {
            $page = get_page_by_path($slug);
            if ($page) {
                $excluded_ids[] = $page->ID;
            }
        }
        if (!empty($excluded_ids)) {
            $existing = $args['post__not_in'] ?? [];
            $args['post__not_in'] = array_merge($existing, $excluded_ids);
        }
    }
    return $args;
}, 10, 2);

/**
 * Gibt BreadcrumbList Schema.org JSON-LD für Nicht-Startseiten aus.
 */
function wowthai_output_breadcrumbs_schema() {
    if (is_front_page()) {
        return;
    }

    $home_url = home_url('/');
    $current_url = home_url(add_query_arg([], $GLOBALS['wp']->request ?? ''));
    $title = get_the_title();

    if (is_page('menu') || is_page('speisekarte')) {
        $title = 'Speisekarte & Online-Bestellung';
    } elseif (is_page('reservations') || is_page('tisch-reservieren')) {
        $title = 'Tisch reservieren';
    } elseif (is_page('impressum')) {
        $title = 'Impressum';
    } elseif (is_page('datenschutz')) {
        $title = 'Datenschutzerklärung';
    }

    $schema = [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => [
            [
                '@type'    => 'ListItem',
                'position' => 1,
                'name'     => 'Startseite',
                'item'     => $home_url,
            ],
            [
                '@type'    => 'ListItem',
                'position' => 2,
                'name'     => $title,
                'item'     => $current_url,
            ],
        ],
    ];

    $b_name = function_exists('wowthai_get_branch_name') ? wowthai_get_branch_name() : 'WOW Thai Steyr';
    echo "\n<!-- " . esc_html($b_name) . " - Breadcrumbs Schema.org JSON-LD -->\n";
    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
}
add_action('wp_head', 'wowthai_output_breadcrumbs_schema', 6);
