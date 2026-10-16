<?php
/**
 * WOW Thai Steyr — Menu Text & German Spelling Normalizer (Zero-DB-Sync Safe)
 * Fixes German spelling mistakes, category name typos ("Vorpseise Sushi" -> "Vorspeise Sushi"),
 * and the copy-pasted description on V10. Tod Man Gung across both front-end and WooCommerce.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Wörterbuch für deutsche Rechtschreibkorrekturen auf der Speisekarte.
 */
function wowthai_menu_typo_replacements(): array {
    return [
        'Vorpseise Sushi'                             => 'Vorspeise Sushi',
        'Gaeng Kari mit Hühn'                         => 'Gaeng Kari mit Huhn',
        'Fischer Lachs'                               => 'Frischer Lachs',
        'Fischer Thunfisch'                           => 'Frischer Thunfisch',
        'hausgernmachter Sauce'                       => 'hausgemachter Sauce',
        'hausgernmachter'                             => 'hausgemachter',
        'Tofostücke'                                  => 'Tofustücke',
        'Fischkäse'                                   => 'Frischkäse',
        'Tobiki'                                      => 'Tobiko',
        'Lachtartar'                                  => 'Lachstartar',
        'Lachs,Avocado, Tobiko, Chilli Mayo, Avocado' => 'Lachs, Avocado, Tobiko, Chili-Mayo',
        'Lach, Thunfisch'                             => 'Lachs, Thunfisch',
        'Mayonaise'                                   => 'Mayonnaise',
        'Acocado'                                     => 'Avocado',
        'Truffel Rolls'                               => 'Trüffel Rolls',
        'mit Truffel'                                 => 'mit Trüffel',
        'Schwarzer Klebreis'                          => 'Schwarzer Klebereis',
    ];
}

/**
 * Bereinigt einen Titel- oder Beschreibungstext anhand des Wörterbuchs.
 */
function wowthai_normalize_menu_text(string $text, string $context_title = ''): string {
    if ($text === '') {
        return '';
    }
    // Fix V10. Tod Man Gung copy-pasted Saté Gai description
    if (
        $context_title !== '' &&
        stripos($context_title, 'Tod Man Gung') !== false &&
        stripos($text, 'Hähnchenspieße') !== false
    ) {
        return 'Hausgemachte thailändische Garnelenküchlein (4 Stk.), knusprig gebacken und serviert mit süß-saurer Chilisauce.';
    }

    return strtr($text, wowthai_menu_typo_replacements());
}

add_filter('woocommerce_product_get_name', function ($name) {
    return wowthai_normalize_menu_text((string) $name);
}, 20);

add_filter('woocommerce_product_get_description', function ($desc, $product) {
    $title = is_object($product) && method_exists($product, 'get_name') ? (string) $product->get_name() : '';
    return wowthai_normalize_menu_text((string) $desc, $title);
}, 20, 2);

/**
 * Einmalige, idempotente Korrektur der Tippfehler direkt in den Produkt-/Kategorie-Einträgen,
 * damit auch Admin-Bestelllisten und 80mm Küchenbons die korrekte Schreibweise nutzen.
 */
function wowthai_fix_catalog_typos_once(): void {
    if (get_option('wowthai_catalog_typos_v1') === 'done') {
        return;
    }

    // 1. Kategorie "Vorpseise Sushi" -> "Vorspeise Sushi"
    $bad_term = get_term_by('name', 'Vorpseise Sushi', 'product_cat');
    if ($bad_term && !is_wp_error($bad_term)) {
        wp_update_term($bad_term->term_id, 'product_cat', [
            'name' => 'Vorspeise Sushi',
        ]);
    }

    // 2. Produkte prüfen und bei Bedarf aktualisieren
    $product_ids = get_posts([
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 200,
        'fields'         => 'ids',
    ]);

    foreach ($product_ids as $pid) {
        $post = get_post($pid);
        if (!$post) {
            continue;
        }
        $old_title = (string) $post->post_title;
        $old_desc  = (string) $post->post_content;
        $new_title = wowthai_normalize_menu_text($old_title);
        $new_desc  = wowthai_normalize_menu_text($old_desc, $new_title);

        if ($new_title !== $old_title || $new_desc !== $old_desc) {
            wp_update_post([
                'ID'           => $pid,
                'post_title'   => $new_title,
                'post_content' => $new_desc,
            ]);
        }
    }

    update_option('wowthai_catalog_typos_v1', 'done', true);
}
add_action('init', 'wowthai_fix_catalog_typos_once', 30);
