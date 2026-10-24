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

require_once get_template_directory() . '/inc/ui.php';

// 2. Enqueue Styles & Scripts
function wowthai_enqueue_scripts() {
    $theme_uri = get_template_directory_uri();
    $version = '1.5.5';

    // Fonts & Base Styles
    wp_enqueue_style('wowthai-fonts', $theme_uri . '/assets/css/fonts.css', [], $version);
    wp_enqueue_style('wowthai-main', $theme_uri . '/assets/css/styles.css', ['wowthai-fonts'], $version);
    if (is_front_page()) {
        wp_enqueue_style('wowthai-homepage', $theme_uri . '/assets/css/homepage.css', ['wowthai-main'], $version);
    }
    wp_enqueue_style('wowthai-checkout', $theme_uri . '/assets/css/checkout.css', ['wowthai-main'], $version);

    // Front-end interactive app
    wp_enqueue_script('wowthai-app', $theme_uri . '/assets/js/wowthai.js', ['jquery'], $version . '.' . substr(hash_file('sha256', get_template_directory() . '/assets/js/wowthai.js'), 0, 16), true);

    $hours_asset = '/assets/js/hours-public.js';
    wp_enqueue_script('wowthai-hours-public', $theme_uri . $hours_asset, ['wowthai-app'], substr(hash_file('sha256', get_template_directory() . $hours_asset), 0, 16), true);
    wp_localize_script('wowthai-hours-public', 'wowthaiHoursConfig', [
        'availability_url' => rest_url('wowthai-hours/v1/availability'),
    ]);

    $prep_time = function_exists('wowthai_get_kitchen_prep_time') ? wowthai_get_kitchen_prep_time() : '25–35 Min.';
    $upsell_items = function_exists('wowthai_get_upsell_suggestions') ? wowthai_get_upsell_suggestions() : [];

    wp_localize_script('wowthai-app', 'wowthaiConfig', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'checkout_url' => function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : '/checkout/',
        'cart_url' => function_exists('wc_get_cart_url') ? wc_get_cart_url() : '/cart/',
        'nonce' => wp_create_nonce('wowthai_cart_nonce'),
        'prep_time' => $prep_time,
        'upsell_items' => $upsell_items,
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
                'modifier_names' => $cart_item['wowthai_modifier_names'] ?? [],
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

// 5. AJAX: Add to Cart with product-owned options and prices.
function wowthai_get_trusted_variant($product_id, $variant_id) {
    if (get_post_meta($product_id, '_wowthai_has_variants', true) !== 'yes') {
        return null;
    }

    $variants = get_post_meta($product_id, '_wowthai_variants', true);
    if (!is_array($variants)) {
        return null;
    }

    foreach ($variants as $variant) {
        if (!is_array($variant) || !isset($variant['id'], $variant['name'], $variant['price'])) {
            continue;
        }
        if ((string) $variant['id'] !== (string) $variant_id || !is_numeric($variant['price']) || (float) $variant['price'] <= 0) {
            continue;
        }
        return [
            'id' => (string) $variant['id'],
            'name' => sanitize_text_field($variant['name']),
            'price' => (float) wc_format_decimal($variant['price']),
        ];
    }

    return null;
}

function wowthai_get_modifier_groups($product_id) {
    $groups = get_post_meta($product_id, '_wowthai_modifier_groups', true);
    return is_array($groups) ? $groups : [];
}

/** Resolve every selected option from product metadata; never trust prices from the browser or cart. */
function wowthai_get_trusted_modifiers($product_id, $selected_ids) {
    if (!is_array($selected_ids) || count($selected_ids) > 20) {
        return new WP_Error('invalid_modifiers', 'Ungültige Zusatzoptionen.');
    }
    foreach ($selected_ids as $selected_id) {
        if (!is_string($selected_id) && !is_int($selected_id)) {
            return new WP_Error('invalid_modifier_id', 'Ungültige Zusatzoption.');
        }
    }

    $groups = wowthai_get_modifier_groups($product_id);
    $selected_ids = array_map('strval', $selected_ids);
    if (count($selected_ids) !== count(array_unique($selected_ids))) {
        return new WP_Error('duplicate_modifiers', 'Eine Zusatzoption wurde mehrfach gewählt.');
    }

    $known = [];
    $resolved = [];
    $charge = 0.0;
    foreach ($groups as $group) {
        if (!is_array($group) || empty($group['id']) || !isset($group['options']) || !is_array($group['options'])) {
            return new WP_Error('invalid_group', 'Die Zusatzoptionen dieses Gerichts sind ungültig.');
        }
        $group_matches = [];
        foreach ($group['options'] as $option) {
            if (!is_array($option) || !isset($option['id'], $option['name'], $option['price']) || !is_numeric($option['price']) || (float) $option['price'] < 0) {
                return new WP_Error('invalid_option', 'Die Zusatzoptionen dieses Gerichts sind ungültig.');
            }
            $option_id = (string) $option['id'];
            $known[] = $option_id;
            if (in_array($option_id, $selected_ids, true)) {
                $group_matches[] = $option;
            }
        }
        $min = isset($group['min']) ? max(0, (int) $group['min']) : 0;
        $max = isset($group['max']) ? max($min, (int) $group['max']) : count($group['options']);
        if (count($group_matches) < $min || count($group_matches) > $max) {
            return new WP_Error('missing_modifier', 'Bitte wählen Sie eine gültige Option für ' . sanitize_text_field($group['name']) . '.');
        }
        foreach ($group_matches as $option) {
            $resolved[] = [
                'id' => (string) $option['id'],
                'group' => sanitize_text_field($group['name']),
                'name' => sanitize_text_field($option['name']),
            ];
            $charge += (float) wc_format_decimal($option['price']);
        }
    }

    if (count(array_diff($selected_ids, $known)) > 0) {
        return new WP_Error('unknown_modifier', 'Ungültige Zusatzoption.');
    }
    return ['ids' => array_column($resolved, 'id'), 'choices' => $resolved, 'charge' => $charge];
}

function wowthai_require_menu_variant($passed, $product_id, $quantity, $variation_id = 0, $variations = [], $cart_item_data = []) {
    if (get_post_meta($product_id, '_wowthai_has_variants', true) === 'yes' && !wowthai_get_trusted_variant($product_id, $cart_item_data['wowthai_variant_id'] ?? '')) {
        wc_add_notice('Bitte wählen Sie die Variante dieses Gerichts über die Speisekarte.', 'error');
        return false;
    }

    $modifiers = wowthai_get_trusted_modifiers($product_id, $cart_item_data['wowthai_modifier_ids'] ?? []);
    if (is_wp_error($modifiers)) {
        wc_add_notice($modifiers->get_error_message(), 'error');
        return false;
    }

    return $passed;
}
add_filter('woocommerce_add_to_cart_validation', 'wowthai_require_menu_variant', 10, 6);

function wowthai_ajax_add_to_cart() {
    check_ajax_referer('wowthai_cart_nonce', 'nonce');

    $product_id = isset($_POST['product_id']) && is_scalar($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $quantity = isset($_POST['quantity']) && is_scalar($_POST['quantity']) ? min(50, max(1, absint($_POST['quantity']))) : 1;
    $variant_id = isset($_POST['variant_id']) && is_string($_POST['variant_id']) ? sanitize_text_field(wp_unslash($_POST['variant_id'])) : '';
    $modifier_ids_json = isset($_POST['modifier_ids']) && is_string($_POST['modifier_ids']) ? wp_unslash($_POST['modifier_ids']) : '[]';
    $modifier_ids = json_decode($modifier_ids_json, true);
    $product = wc_get_product($product_id);
    if (!$product || !$product->is_purchasable() || !$product->is_in_stock()) {
        wp_send_json_error(['message' => 'Ungültiges Produkt']);
    }

    $cart_item_data = [];
    if (get_post_meta($product_id, '_wowthai_has_variants', true) === 'yes') {
        $variant = wowthai_get_trusted_variant($product_id, $variant_id);
        if (!$variant) {
            wp_send_json_error(['message' => 'Bitte wählen Sie eine gültige Variante.']);
        }
        $cart_item_data['wowthai_variant_id'] = $variant['id'];
        $cart_item_data['wowthai_variant_name'] = $variant['name'];
    } elseif ($variant_id !== '') {
        wp_send_json_error(['message' => 'Dieses Produkt hat keine Variante.']);
    }

    $modifiers = wowthai_get_trusted_modifiers($product_id, $modifier_ids);
    if (is_wp_error($modifiers)) {
        wp_send_json_error(['message' => $modifiers->get_error_message()]);
    }
    if ($modifiers['ids']) {
        $cart_item_data['wowthai_modifier_ids'] = $modifiers['ids'];
        $cart_item_data['wowthai_modifier_names'] = $modifiers['choices'];
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

// 6. Re-check cart prices on every calculation, including restored cart sessions.
function wowthai_custom_cart_item_price($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;

    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        $product_id = (int) $cart_item['product_id'];
        if (!empty($cart_item['wowthai_custom_title'])) {
            $cart->remove_cart_item($cart_item_key);
            continue;
        }
        $has_variants = get_post_meta($product_id, '_wowthai_has_variants', true) === 'yes';
        $variant = $has_variants ? wowthai_get_trusted_variant($product_id, $cart_item['wowthai_variant_id'] ?? '') : null;
        $modifiers = wowthai_get_trusted_modifiers($product_id, $cart_item['wowthai_modifier_ids'] ?? []);
        if (($has_variants && !$variant) || is_wp_error($modifiers) || (!$has_variants && !empty($cart_item['wowthai_variant_id']))) {
            $cart->remove_cart_item($cart_item_key);
            continue;
        }

        unset($cart->cart_contents[$cart_item_key]['wowthai_variant_price'], $cart->cart_contents[$cart_item_key]['wowthai_custom_title']);
        $cart->cart_contents[$cart_item_key]['wowthai_modifier_names'] = $modifiers['choices'];
        if ($variant) {
            $cart->cart_contents[$cart_item_key]['wowthai_variant_name'] = $variant['name'];
        }
        $base_price = $variant ? $variant['price'] : (float) wc_get_product($product_id)->get_price();
        $cart_item['data']->set_price($base_price + $modifiers['charge']);
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
    foreach ($cart_item['wowthai_modifier_names'] ?? [] as $choice) {
        $item_data[] = [
            'key' => $choice['group'],
            'value' => $choice['name'],
        ];
    }
    return $item_data;
}
add_filter('woocommerce_get_item_data', 'wowthai_display_cart_item_variant', 10, 2);

// Ensure /impressum/ and /datenschutz/ pages exist without manual DB sync
function wowthai_ensure_legal_pages() {
    if (get_option('wowthai_legal_pages_v1') === 'done') {
        return;
    }
    $legal_pages = [
        'impressum'   => 'Impressum',
        'datenschutz' => 'Datenschutzerklärung',
    ];
    foreach ($legal_pages as $slug => $title) {
        if (!get_page_by_path($slug, OBJECT, 'page')) {
            wp_insert_post([
                'post_type'      => 'page',
                'post_name'      => $slug,
                'post_title'     => $title,
                'post_status'    => 'publish',
                'post_content'   => '',
                'comment_status' => 'closed',
                'ping_status'    => 'closed',
            ]);
        }
    }
    update_option('wowthai_legal_pages_v1', 'done', true);
}
add_action('init', 'wowthai_ensure_legal_pages', 20);

// Ensure WooCommerce checkout privacy policy always resolves to /datenschutz/
add_filter('woocommerce_get_privacy_policy_url', function() {
    return home_url('/datenschutz/');
});

// Normalize WooCommerce checkout review table header strings to German
add_filter('gettext', function($translation, $text, $domain) {
    if ($domain === 'woocommerce') {
        switch ($text) {
            case 'Product':
                return 'Produkt';
            case 'Subtotal':
                return 'Zwischensumme';
            case 'Total':
                return 'Gesamtsumme';
        }
    }
    return $translation;
}, 20, 3);

function wowthai_redirect_legacy_pages() {
    $path = wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $path = untrailingslashit((string) $path);
    if ($path === '/speisekarte') {
        wp_safe_redirect(home_url('/menu/'), 301);
        exit;
    }
    if ($path === '/tisch-reservieren') {
        wp_safe_redirect(home_url('/reservations/'), 301);
        exit;
    }
    if (is_404() && ($path === '/impressum' || $path === '/datenschutz')) {
        global $wp_query;
        if ($wp_query) {
            $wp_query->is_404      = false;
            $wp_query->is_page     = true;
            $wp_query->is_singular = true;
        }
        status_header(200);
        $tpl = get_template_directory() . ($path === '/impressum' ? '/page-impressum.php' : '/page-datenschutz.php');
        if (file_exists($tpl)) {
            include $tpl;
            exit;
        }
    }
    if (function_exists('is_product') && is_product()) {
        // Product pages cannot display the custom restaurant choices safely.
        wp_safe_redirect(home_url('/menu/?dish=' . (int) get_queried_object_id()), 302);
        exit;
    }
    if (function_exists('is_shop') && is_shop()) {
        wp_safe_redirect(home_url('/menu/'), 301);
        exit;
    }
}
add_action('template_redirect', 'wowthai_redirect_legacy_pages', 1);
add_filter('woocommerce_return_to_shop_redirect', function () { return home_url('/menu/'); });

// 8. Save variant name into order line item meta
function wowthai_add_order_item_meta($item, $cart_item_key, $values, $order) {
    if (!empty($values['wowthai_variant_name'])) {
        $item->add_meta_data('Variante', $values['wowthai_variant_name']);
    }
    foreach ($values['wowthai_modifier_names'] ?? [] as $choice) {
        $item->add_meta_data($choice['group'], $choice['name']);
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
    
}
add_action('woocommerce_checkout_process', 'wowthai_autofill_checkout_fields', 5);

function wowthai_cart_has_sushi() {
    if (!function_exists('WC') || !WC()->cart) return false;
    foreach (WC()->cart->get_cart() as $item) {
        if (wowthai_product_is_sushi($item['product_id'])) return true;
    }
    return false;
}

function wowthai_validate_checkout_pickup() {
    $pickup = wowthai_validate_pickup_request($_POST, null, wowthai_cart_has_sushi());
    if (is_wp_error($pickup)) wc_add_notice($pickup->get_error_message(), 'error');
}
add_action('woocommerce_checkout_process', 'wowthai_validate_checkout_pickup', 20);

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

    $pickup_time = wowthai_validate_pickup_request($_POST, null, wowthai_cart_has_sushi());
    if (is_wp_error($pickup_time)) throw new Exception($pickup_time->get_error_message());
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
function wowthai_menu_category_groups(): array {
    return [
        'restaurant' => [
            'Mittagsmenü', 'VORSPEISEN', 'SUPPEN', 'HAUPTSPEISEN',
            'VIETNAMESISCHE SPEZIALITÄTEN', 'SPEZIALITÄT DES HAUSES',
            'NACHSPEISEN', 'EXTRAS'
        ],
        'sushi' => [
            'Vorspeise Sushi', 'Vorpseise Sushi', 'Set', 'Maki &amp; Sashimi', 'Maki & Sashimi',
            'Special Rolls', 'Bowl'
        ],
    ];
}

function wowthai_get_menu_structure() {
    $category_groups = wowthai_menu_category_groups();
    $restaurant_cats = $category_groups['restaurant'];
    $sushi_cats = $category_groups['sushi'];

    $output = [
        'restaurant' => [],
        'sushi' => []
    ];

    $seen_sections = [];
    foreach ($restaurant_cats as $cat_name) {
        $term = get_term_by('name', $cat_name, 'product_cat');
        if ($term && !isset($seen_sections[$term->term_id])) {
            $seen_sections[$term->term_id] = true;
            $products = wowthai_get_products_in_cat($term->term_id);
            if (!empty($products)) {
                $disp_name = function_exists('wowthai_normalize_menu_text') ? wowthai_normalize_menu_text($term->name) : $term->name;
                $output['restaurant'][] = [
                    'term_id' => $term->term_id,
                    'name' => $disp_name,
                    'slug' => $term->slug,
                    'products' => $products
                ];
            }
        }
    }

    foreach ($sushi_cats as $cat_name) {
        $term = get_term_by('name', $cat_name, 'product_cat');
        if ($term && !isset($seen_sections[$term->term_id])) {
            $seen_sections[$term->term_id] = true;
            $products = wowthai_get_products_in_cat($term->term_id);
            if (!empty($products)) {
                $disp_name = function_exists('wowthai_normalize_menu_text') ? wowthai_normalize_menu_text($term->name) : $term->name;
                $output['sushi'][] = [
                    'term_id' => $term->term_id,
                    'name' => $disp_name,
                    'slug' => $term->slug,
                    'products' => $products
                ];
            }
        }
    }

    return $output;
}

/** Map one managed product identically for menu and homepage consumers. */
function wowthai_get_menu_product($id) {
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

    $raw_title = get_the_title($id);
    $raw_desc  = get_the_content(null, false, $id);
    $title     = function_exists('wowthai_normalize_menu_text') ? wowthai_normalize_menu_text($raw_title) : $raw_title;
    $desc      = function_exists('wowthai_normalize_menu_text') ? wowthai_normalize_menu_text($raw_desc, $title, true) : wp_strip_all_tags($raw_desc);

    $spice = function_exists('wowthai_get_dish_spice_level') ? wowthai_get_dish_spice_level($title, $desc, $labels) : ['level' => 0, 'icons' => '', 'pill' => ''];

    return [
        'id' => $id,
        'title' => $title,
        'description' => $desc,
        'price' => $price,
        'formatted_price' => $formatted_price,
        'has_variants' => $has_variants,
        'variants' => $variants ?: [],
        'modifier_groups' => wowthai_get_modifier_groups($id),
        'labels' => $labels,
        'image_url' => $img_url,
        'spice' => $spice,
    ];
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
            $products[] = wowthai_get_menu_product(get_the_ID());
        }
        wp_reset_postdata();
    }

    return $products;
}

require_once get_template_directory() . '/inc/hours.php';
require_once get_template_directory() . '/inc/pickup.php';
require_once get_template_directory() . '/inc/hours-api.php';
require_once get_template_directory() . '/inc/menu-normalizer.php';
require_once get_template_directory() . '/inc/homepage.php';

// 14. Restaurant Stammdaten, Öffnungszeiten & Google Schema.org
require_once get_template_directory() . '/inc/restaurant-info.php';
require_once get_template_directory() . '/inc/seo.php';

// 15. Restaurant Experience, Allergens, Spice Levels & Kitchen Rush Time
require_once get_template_directory() . '/inc/restaurant-experience.php';

// 16. Branded HTML Email Templates (Dark & Gold Luxury)
require_once get_template_directory() . '/inc/email-templates.php';

// 17. Restaurant Order Management & Kitchen Console in WP-Admin
require_once get_template_directory() . '/inc/admin-orders.php';

// 18. Tischanfragen & Reservierungsverwaltung in WP-Admin (Resmio/Wix Style)
require_once get_template_directory() . '/inc/admin-reservations.php';
require_once get_template_directory() . '/inc/reservations-front.php';

// 19. WOW Thai Staff Operations Portal (/betrieb/)
add_filter('show_admin_bar', function($show) {
    if (is_page('betrieb') || (isset($GLOBALS['post']) && $GLOBALS['post']->post_name === 'betrieb')) {
        return false;
    }
    return $show;
});

add_action('wp_ajax_wowthai_portal_login', 'wowthai_ajax_portal_login');
add_action('wp_ajax_nopriv_wowthai_portal_login', 'wowthai_ajax_portal_login');
function wowthai_ajax_portal_login() {
    check_ajax_referer('wowthai_portal_login_nonce', 'nonce');
    $input_user = trim(sanitize_text_field($_POST['log'] ?? ''));
    $password   = wp_unslash((string)($_POST['pwd'] ?? '')); // verify-ok
    $remember   = !empty($_POST['rememberme']);

    if (empty($input_user) || empty($password)) {
        wp_send_json_error(['message' => 'Bitte Benutzername / E-Mail und Passwort eingeben.']);
    }

    // Resolve user by email or login
    $found_user = is_email($input_user) ? get_user_by('email', $input_user) : get_user_by('login', $input_user);
    $login_name = $found_user ? $found_user->user_login : $input_user;

    $creds = [
        'user_login'    => $login_name,
        'user_password' => $password,
        'remember'      => $remember,
    ];
    $user = wp_signon($creds, is_ssl());

    if (is_wp_error($user)) {
        if ($found_user) {
            wp_send_json_error([
                'message' => 'Passwort ungültig. Da dieses Konto über WordPress.com (SSO) verwaltet wird, bitte den goldenen Button "Mit WordPress.com anmelden" oben nutzen.',
                'is_wpcom_account' => true,
            ]);
        } else {
            wp_send_json_error(['message' => 'Benutzerkonto nicht gefunden. Bitte Zugangsdaten prüfen.']);
        }
    }

    $is_staff = user_can($user, 'manage_woocommerce') 
             || user_can($user, 'manage_options') 
             || user_can($user, 'edit_shop_orders')
             || in_array('shop_manager', (array)($user->roles ?? []), true)
             || in_array('administrator', (array)($user->roles ?? []), true);

    if (!$is_staff) {
        wp_logout();
        wp_send_json_error(['message' => 'Ihr Konto (' . esc_html($user->user_email) . ') hat keine Berechtigung für das Restaurant-Betriebsportal. Erforderliche Rolle: Administrator oder Shop-Manager.']);
    }

    wp_send_json_success(['redirect' => home_url('/betrieb/'), 'user' => $user->display_name]);
}

// Read-only staff portal API; mutations remain in WOW Thai Operations.
require_once get_template_directory() . '/inc/portal-read-model.php';
