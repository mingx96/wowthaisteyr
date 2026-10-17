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
    $theme_uri = get_template_directory_uri();
    $default_image = $theme_uri . '/assets/img/hero.jpg';

    if (is_front_page()) {
        return [
            'title'       => 'WOW Thai Steyr | Thailändisches Restaurant & Sushi Bar im City Point',
            'desc'        => 'Authentische thailändische Küche, Wok-Gerichte & frisches Sushi im City Point Steyr (1. OG). Tisch online reservieren oder Takeaway vorbestellen.',
            'og_type'     => 'website',
            'og_image'    => $default_image,
            'robots'      => 'max-image-preview:large',
        ];
    }

    if (is_page('menu') || is_page('speisekarte')) {
        return [
            'title'       => 'Speisekarte & Online Bestellen | WOW Thai Steyr',
            'desc'        => 'Original thailändische Currys, Wok-Spezialitäten & frisches Sushi bei WOW Thai im City Point Steyr online zur Selbstabholung vorbestellen.',
            'og_type'     => 'website',
            'og_image'    => $default_image,
            'robots'      => 'max-image-preview:large',
        ];
    }

    if (is_page('reservations') || is_page('tisch-reservieren')) {
        return [
            'title'       => 'Tisch online reservieren | WOW Thai Steyr (City Point 1. OG)',
            'desc'        => 'Reservieren Sie Ihren Tisch bei WOW Thai Steyr (City Point · 1. OG, Leopold-Werndl-Straße 2, 4400 Steyr) schnell und bequem online.',
            'og_type'     => 'website',
            'og_image'    => $default_image,
            'robots'      => 'max-image-preview:large',
        ];
    }

    if (is_page('impressum')) {
        return [
            'title'       => 'Impressum & Rechtliche Hinweise | WOW Thai Steyr',
            'desc'        => 'Impressum und Offenlegung nach §5 ECG, §14 UGB und §25 Mediengesetz für WOW Thai Steyr Gastronomie im City Point.',
            'og_type'     => 'article',
            'og_image'    => $default_image,
            'robots'      => 'max-image-preview:large',
        ];
    }

    if (is_page('datenschutz')) {
        return [
            'title'       => 'Datenschutzerklärung & Privatsphäre | WOW Thai Steyr',
            'desc'        => 'Informationen zum Datenschutz, DSGVO-Rechten und zur sicheren Datenverarbeitung beim Besuch und bei Online-Bestellungen auf wow-thai-steyr.at.',
            'og_type'     => 'article',
            'og_image'    => $default_image,
            'robots'      => 'max-image-preview:large',
        ];
    }

    if (is_page('cart')) {
        return [
            'title'       => 'Warenkorb | WOW Thai Steyr',
            'desc'        => 'Ihr Warenkorb bei WOW Thai Steyr. Prüfen Sie Ihre ausgewählten Speisen vor der Abholbestellung.',
            'og_type'     => 'website',
            'og_image'    => $default_image,
            'robots'      => 'noindex, follow',
        ];
    }

    if (is_page('checkout')) {
        return [
            'title'       => 'Kasse & Abholung | WOW Thai Steyr',
            'desc'        => 'Bestellung abschließen und Abholzeit wählen bei WOW Thai Steyr im City Point.',
            'og_type'     => 'website',
            'og_image'    => $default_image,
            'robots'      => 'noindex, nofollow',
        ];
    }

    if (is_page('my-account')) {
        return [
            'title'       => 'Mein Konto | WOW Thai Steyr',
            'desc'        => 'Kundenkonto und Bestellübersicht bei WOW Thai Steyr.',
            'og_type'     => 'website',
            'og_image'    => $default_image,
            'robots'      => 'noindex, nofollow',
        ];
    }

    if (is_page('betrieb')) {
        return [
            'title'       => 'WOW Thai Betrieb · Restaurant-Verwaltung',
            'desc'        => 'Internes Betriebs-Portal für Küchenteam und Service von WOW Thai Steyr.',
            'og_type'     => 'website',
            'og_image'    => $default_image,
            'robots'      => 'noindex, nofollow',
        ];
    }

    // Standard-Fallback
    $fallback_title = get_the_title();
    return [
        'title'       => ($fallback_title ? $fallback_title . ' | ' : '') . 'WOW Thai Steyr',
        'desc'        => 'Authentische thailändische Küche, Wok-Gerichte & frisches Sushi im City Point Steyr (1. OG). Tisch online reservieren oder Takeaway vorbestellen.',
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

    echo "\n<!-- WOW Thai Steyr - Breadcrumbs Schema.org JSON-LD -->\n";
    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
}
add_action('wp_head', 'wowthai_output_breadcrumbs_schema', 6);
