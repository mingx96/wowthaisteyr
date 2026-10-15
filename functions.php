<?php
/**
 * WOW Thai Steyr Theme Functions
 */

if (!defined('ABSPATH')) {
    exit;
}

// 1. Theme Setup
function wowthai_theme_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption']);
    
    register_nav_menus([
        'primary' => __('Hauptnavigation', 'wowthai'),
    ]);
}
add_action('after_setup_theme', 'wowthai_theme_setup');

// 2. Enqueue Styles & Scripts
function wowthai_enqueue_scripts() {
    $theme_uri = get_template_directory_uri();
    $version = '1.0.5';

    // Fonts & Base Styles
    wp_enqueue_style('wowthai-fonts', $theme_uri . '/assets/css/fonts.css', [], $version);
    wp_enqueue_style('wowthai-main', $theme_uri . '/assets/css/styles.css', ['wowthai-fonts'], $version);
    wp_enqueue_style('wowthai-checkout', $theme_uri . '/assets/css/checkout.css', ['wowthai-main'], $version);

    // Front-end interactive app
    wp_enqueue_script('wowthai-app', $theme_uri . '/assets/js/wowthai.js', ['jquery'], $version, true);

    wp_localize_script('wowthai-app', 'wowthaiConfig', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'checkout_url' => function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : '/checkout/',
        'cart_url' => function_exists('wc_get_cart_url') ? wc_get_cart_url() : '/cart/',
        'nonce' => wp_create_nonce('wowthai_cart_nonce'),
    ]);
}
add_action('wp_enqueue_scripts', 'wowthai_enqueue_scripts');

// 3. Helper to format cart data for AJAX response
function wowthai_get_cart_payload() {
    if (!function_exists('WC') || !WC()->cart) {
        return ['count' => 0, 'subtotal' => '€0,00', 'items' => []];
    }

    $cart = WC()->cart;
    $items = [];

    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
        if ($_product && $_product->exists() && $cart_item['quantity'] > 0) {
            $name = $_product->get_name();
            $variant_name = $cart_item['wowthai_variant_name'] ?? '';
            $line_total = wc_price($cart_item['line_total'] + $cart_item['line_tax']);

            $items[] = [
                'key' => $cart_item_key,
                'product_id' => $cart_item['product_id'],
                'name' => $name,
                'variant_name' => $variant_name,
                'quantity' => $cart_item['quantity'],
                'line_total' => html_entity_decode(strip_tags($line_total)),
            ];
        }
    }

    return [
        'count' => $cart->get_cart_contents_count(),
        'subtotal' => html_entity_decode(strip_tags($cart->get_cart_subtotal())),
        'items' => $items,
    ];
}

// 4. AJAX: Get Cart
function wowthai_ajax_get_cart() {
    wp_send_json_success(wowthai_get_cart_payload());
}
add_action('wp_ajax_wowthai_get_cart', 'wowthai_ajax_get_cart');
add_action('wp_ajax_nopriv_wowthai_get_cart', 'wowthai_ajax_get_cart');

// 5. AJAX: Add to Cart with custom options/price
function wowthai_ajax_add_to_cart() {
    check_ajax_referer('wowthai_cart_nonce', 'nonce');

    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $quantity = isset($_POST['quantity']) ? max(1, intval($_POST['quantity'])) : 1;
    $variant_id = isset($_POST['variant_id']) ? sanitize_text_field($_POST['variant_id']) : '';
    $variant_name = isset($_POST['variant_name']) ? sanitize_text_field($_POST['variant_name']) : '';
    $variant_price = isset($_POST['variant_price']) ? floatval($_POST['variant_price']) : 0;

    if (!$product_id) {
        wp_send_json_error(['message' => 'Ungültiges Produkt']);
    }

    $cart_item_data = [];
    if (!empty($variant_id)) {
        $cart_item_data['wowthai_variant_id'] = $variant_id;
        $cart_item_data['wowthai_variant_name'] = $variant_name;
        $cart_item_data['wowthai_variant_price'] = $variant_price;
    }

    $added = WC()->cart->add_to_cart($product_id, $quantity, 0, [], $cart_item_data);

    if ($added) {
        wp_send_json_success(wowthai_get_cart_payload());
    } else {
        wp_send_json_error(['message' => 'Konnte nicht zum Warenkorb hinzugefügt werden']);
    }
}
add_action('wp_ajax_wowthai_add_to_cart', 'wowthai_ajax_add_to_cart');
add_action('wp_ajax_nopriv_wowthai_add_to_cart', 'wowthai_ajax_add_to_cart');

// 6. Custom price calculation when variant is selected
function wowthai_custom_cart_item_price($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    if (did_action('woocommerce_before_calculate_totals') >= 2) return;

    foreach ($cart->get_cart() as $cart_item) {
        if (!empty($cart_item['wowthai_variant_price']) && $cart_item['wowthai_variant_price'] > 0) {
            $cart_item['data']->set_price($cart_item['wowthai_variant_price']);
        }
    }
}
add_action('woocommerce_before_calculate_totals', 'wowthai_custom_cart_item_price', 20, 1);

// 7. Display variant name in standard WooCommerce Cart & Checkout tables
function wowthai_display_cart_item_variant($item_data, $cart_item) {
    if (!empty($cart_item['wowthai_variant_name'])) {
        $item_data[] = [
            'key' => 'Option / Variante',
            'value' => $cart_item['wowthai_variant_name'],
        ];
    }
    return $item_data;
}
add_filter('woocommerce_get_item_data', 'wowthai_display_cart_item_variant', 10, 2);

// 8. Save variant name into order line item meta
function wowthai_add_order_item_meta($item, $cart_item_key, $values, $order) {
    if (!empty($values['wowthai_variant_name'])) {
        $item->add_meta_data('Variante', $values['wowthai_variant_name']);
    }
}
add_action('woocommerce_checkout_create_order_line_item', 'wowthai_add_order_item_meta', 10, 4);

// 9. AJAX: Update Cart Quantity
function wowthai_ajax_update_cart_quantity() {
    check_ajax_referer('wowthai_cart_nonce', 'nonce');

    $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;

    if (!empty($cart_item_key)) {
        if ($quantity <= 0) {
            WC()->cart->remove_cart_item($cart_item_key);
        } else {
            WC()->cart->set_quantity($cart_item_key, $quantity);
        }
        wp_send_json_success(wowthai_get_cart_payload());
    } else {
        wp_send_json_error(['message' => 'Ungültiger Eintrag']);
    }
}
add_action('wp_ajax_wowthai_update_cart_quantity', 'wowthai_ajax_update_cart_quantity');
add_action('wp_ajax_nopriv_wowthai_update_cart_quantity', 'wowthai_ajax_update_cart_quantity');

// 10. Checkout Customization: Streamlined Restaurant Takeaway (Zero Address Clutter)
function wowthai_custom_checkout_fields($fields) {
    // Keep ONLY essential contact fields for pickup
    $fields['billing'] = [
        'billing_first_name' => [
            'type' => 'text',
            'label' => 'Vorname',
            'placeholder' => 'z. B. Max',
            'required' => true,
            'class' => ['form-row-first'],
            'priority' => 10,
        ],
        'billing_last_name' => [
            'type' => 'text',
            'label' => 'Nachname',
            'placeholder' => 'z. B. Mustermann',
            'required' => true,
            'class' => ['form-row-last'],
            'priority' => 20,
        ],
        'billing_phone' => [
            'type' => 'tel',
            'label' => 'Telefonnummer',
            'placeholder' => '+43 660 ... (für eventuelle Rückfragen)',
            'required' => true,
            'class' => ['form-row-wide'],
            'priority' => 30,
        ],
        'billing_email' => [
            'type' => 'email',
            'label' => 'E-Mail-Adresse',
            'placeholder' => 'ihre@email.at (für die Bestellbestätigung)',
            'required' => true,
            'class' => ['form-row-wide'],
            'priority' => 40,
        ],
    ];

    // Remove shipping fields entirely (restaurant takeaway doesn't ship)
    unset($fields['shipping']);

    // Customize order notes
    if (isset($fields['order']['order_comments'])) {
        $fields['order']['order_comments']['label'] = 'Besondere Wünsche zur Zubereitung (optional)';
        $fields['order']['order_comments']['placeholder'] = 'z. B. bitte extra scharf, keine Erdnüsse, Besteck benötigt...';
        $fields['order']['order_comments']['class'] = ['form-row-wide'];
    }

    return $fields;
}
add_filter('woocommerce_checkout_fields', 'wowthai_custom_checkout_fields', 99);

function wowthai_filter_billing_fields($fields) {
    unset($fields['billing_company']);
    unset($fields['billing_country']);
    unset($fields['billing_address_1']);
    unset($fields['billing_address_2']);
    unset($fields['billing_city']);
    unset($fields['billing_state']);
    unset($fields['billing_postcode']);
    return $fields;
}
add_filter('woocommerce_billing_fields', 'wowthai_filter_billing_fields', 9999);

function wowthai_default_address_fields($fields) {
    unset($fields['company']);
    unset($fields['country']);
    unset($fields['address_1']);
    unset($fields['address_2']);
    unset($fields['city']);
    unset($fields['state']);
    unset($fields['postcode']);
    return $fields;
}
add_filter('woocommerce_default_address_fields', 'wowthai_default_address_fields', 9999);

// Disable shipping address requirement
add_filter('woocommerce_cart_needs_shipping_address', '__return_false');

// Remove coupon form from top of checkout to keep page compact and clean
remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );

// 12. Auto-fill hidden billing address so WooCommerce passes validation effortlessly
function wowthai_autofill_checkout_fields() {
    $_POST['billing_country'] = 'AT';
    $_POST['billing_city'] = 'Steyr';
    $_POST['billing_address_1'] = 'Selbstabholung City Point Steyr';
    $_POST['billing_postcode'] = '4400';
    $_POST['billing_state'] = '';
    
    // Ensure abholzeit is populated
    if (empty($_POST['abholzeit'])) {
        $_POST['abholzeit'] = 'Schnellstmöglich (ca. 25–35 Min.)';
    }
}
add_action('woocommerce_checkout_process', 'wowthai_autofill_checkout_fields', 5);

// Ensure posted data always has country and pickup address during update_order_review AJAX
function wowthai_filter_checkout_posted_data($data) {
    $data['billing_country'] = 'AT';
    $data['billing_city'] = 'Steyr';
    $data['billing_address_1'] = 'Selbstabholung City Point Steyr';
    $data['billing_postcode'] = '4400';
    $data['billing_state'] = '';
    return $data;
}
add_filter('woocommerce_checkout_posted_data', 'wowthai_filter_checkout_posted_data', 10, 1);

// Clear, commanding order button text
function wowthai_custom_order_button_text() {
    return 'Bestellung abschicken (Bar / Karte bei Abholung)';
}
add_filter('woocommerce_order_button_text', 'wowthai_custom_order_button_text');

// Clean, courteous German error messages without "Billing" prefix
function wowthai_custom_checkout_error_messages($error) {
    $replacements = [
        'Billing Vorname is a required field.' => 'Bitte geben Sie Ihren Vornamen ein.',
        'Billing Nachname is a required field.' => 'Bitte geben Sie Ihren Nachnamen ein.',
        'Billing Telefonnummer is a required field.' => 'Bitte geben Sie Ihre Telefonnummer ein.',
        'Billing E-Mail-Adresse is a required field.' => 'Bitte geben Sie Ihre E-Mail-Adresse ein.',
        'Billing Vorname ist ein Pflichtfeld.' => 'Bitte geben Sie Ihren Vornamen ein.',
        'Billing Nachname ist ein Pflichtfeld.' => 'Bitte geben Sie Ihren Nachnamen ein.',
        'Billing Telefonnummer ist ein Pflichtfeld.' => 'Bitte geben Sie Ihre Telefonnummer ein.',
        'Billing E-Mail-Adresse ist ein Pflichtfeld.' => 'Bitte geben Sie Ihre E-Mail-Adresse ein.',
        'Billing ' => '',
        'is a required field.' => 'ist ein Pflichtfeld.',
    ];
    return strtr($error, $replacements);
}
add_filter('woocommerce_add_error', 'wowthai_custom_checkout_error_messages', 10, 1);

// 13. Save Pickup Time to Order Meta & autofill order address
function wowthai_save_pickup_time_order_meta($order, $data) {
    $order->set_billing_country('AT');
    $order->set_billing_city('Steyr');
    $order->set_billing_address_1('Selbstabholung City Point Steyr');
    $order->set_billing_postcode('4400');

    $pickup_time = !empty($_POST['abholzeit']) ? sanitize_text_field($_POST['abholzeit']) : 'Schnellstmöglich (ca. 25–35 Min.)';
    $order->update_meta_data('Abholzeit', $pickup_time);
}
add_action('woocommerce_checkout_create_order', 'wowthai_save_pickup_time_order_meta', 10, 2);

// 14. Display Pickup Time on Thank You Page and in Emails
function wowthai_display_pickup_time_on_order($order) {
    $pickup = $order->get_meta('Abholzeit');
    if ($pickup) {
        echo '<div class="pickup-summary-banner">';
        echo '<h3>🕒 Abholinformation</h3>';
        echo '<p><strong>Gewünschte Abholzeit:</strong> <span class="pickup-time-pill">' . esc_html($pickup) . '</span></p>';
        echo '<p><small>Abholort: <strong>WOW Thai Restaurant</strong>, City Point 1. OG, Leopold-Werndl-Straße 2, 4400 Steyr</small></p>';
        echo '</div>';
    }
}
add_action('woocommerce_order_details_after_order_table', 'wowthai_display_pickup_time_on_order', 10, 1);
add_action('woocommerce_email_after_order_table', 'wowthai_display_pickup_time_on_order', 10, 1);

// 13. Query Menu Data Helper for Templates
function wowthai_get_menu_structure() {
    $restaurant_cats = [
        'Mittagsmenü', 'VORSPEISEN', 'SUPPEN', 'HAUPTSPEISEN',
        'VIETNAMESISCHE SPEZIALITÄTEN', 'SPEZIALITÄT DES HAUSES',
        'NACHSPEISEN', 'EXTRAS'
    ];

    $sushi_cats = [
        'Vorpseise Sushi', 'Set', 'Maki &amp; Sashimi', 'Maki & Sashimi',
        'Special Rolls', 'Bowl'
    ];

    $output = [
        'restaurant' => [],
        'sushi' => []
    ];

    foreach ($restaurant_cats as $cat_name) {
        $term = get_term_by('name', $cat_name, 'product_cat');
        if ($term) {
            $products = wowthai_get_products_in_cat($term->term_id);
            if (!empty($products)) {
                $output['restaurant'][] = [
                    'term_id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'products' => $products
                ];
            }
        }
    }

    foreach ($sushi_cats as $cat_name) {
        $term = get_term_by('name', $cat_name, 'product_cat');
        if ($term) {
            $products = wowthai_get_products_in_cat($term->term_id);
            if (!empty($products)) {
                $output['sushi'][] = [
                    'term_id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'products' => $products
                ];
            }
        }
    }

    return $output;
}

function wowthai_get_products_in_cat($term_id) {
    $args = [
        'post_type' => 'product',
        'posts_per_page' => -1,
        'tax_query' => [
            [
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $term_id,
            ]
        ],
        'orderby' => 'menu_order',
        'order' => 'ASC',
        'post_status' => 'publish'
    ];

    $query = new WP_Query($args);
    $products = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $id = get_the_ID();
            $has_variants = get_post_meta($id, '_wowthai_has_variants', true) === 'yes';
            $variants = get_post_meta($id, '_wowthai_variants', true);
            $labels = get_post_meta($id, '_wowthai_labels', true) ?: [];
            $img_url = get_post_meta($id, '_wowthai_image_url', true);
            $price = get_post_meta($id, '_price', true);

            $formatted_price = '€' . number_format(floatval($price), 2, ',', '.');
            if ($has_variants && !empty($variants)) {
                $min_p = min(array_map(function($v) { return floatval($v['price']); }, $variants));
                $formatted_price = 'ab €' . number_format($min_p, 2, ',', '.');
            }

            $products[] = [
                'id' => $id,
                'title' => get_the_title(),
                'description' => get_the_content(),
                'price' => $price,
                'formatted_price' => $formatted_price,
                'has_variants' => $has_variants,
                'variants' => $variants ?: [],
                'labels' => $labels,
                'image_url' => $img_url,
            ];
        }
        wp_reset_postdata();
    }

    return $products;
}

// 14. Restaurant Order Management & Kitchen Console in WP-Admin
require_once get_template_directory() . '/inc/admin-orders.php';
