<?php
/**
 * WoW Express Delivery Engine: Zone Validation, Pricing & Transit Estimation
 *
 * Single Source of Truth for delivery zones, minimum order thresholds,
 * delivery fees, and dynamic customer ETAs.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Normalizes postal code input (strips whitespace, 'A-'/'AT-' prefixes, non-digits).
 *
 * @param mixed $input
 * @return string 4-digit PLZ string or cleaned digits
 */
function wowthai_normalize_plz($input): string {
    if ($input === null || $input === false) {
        return '';
    }
    $str = strtoupper(trim((string) $input));
    if (strpos($str, 'A-') === 0 || strpos($str, 'AT-') === 0) {
        $str = preg_replace('/^(A|AT)-/i', '', $str);
    }
    if (preg_match('/\b\d{4}\b/', $str, $matches)) {
        return $matches[0];
    }
    return (string) preg_replace('/\D/', '', $str);
}

/**
 * Returns the immutable delivery zones dictionary.
 *
 * @return array
 */
function wowthai_get_delivery_zones(): array {
    return [
        'core' => [
            'zone'           => 'core',
            'name'           => 'Kernzone St. Valentin',
            'postcodes'      => ['4300'],
            'cities'         => ['4300' => 'St. Valentin'],
            'min_order'      => 20.00,
            'delivery_fee'   => 2.50,
            'free_threshold' => 30.00,
            'transit_min'    => 10,
            'transit_max'    => 15,
        ],
        'extended' => [
            'zone'           => 'extended',
            'name'           => 'Erweiterte Lieferzone',
            'postcodes'      => ['4303', '4470', '4481'],
            'cities'         => [
                '4303' => 'Ennsdorf',
                '4470' => 'Enns',
                '4481' => 'Asten',
            ],
            'min_order'      => 25.00,
            'delivery_fee'   => 3.90,
            'free_threshold' => 45.00,
            'transit_min'    => 15,
            'transit_max'    => 25,
        ],
    ];
}

/**
 * Validates Austrian postal code for delivery support.
 *
 * @param string $postcode
 * @return array
 */
function wowthai_validate_delivery_plz(string $postcode): array {
    $plz = wowthai_normalize_plz($postcode);

    if ($plz === '' || strlen($plz) !== 4 || !preg_match('/^\d{4}$/', $plz)) {
        return [
            'valid'          => false,
            'plz'            => $plz,
            'zone'           => null,
            'zone_name'      => null,
            'city'           => null,
            'min_order'      => null,
            'delivery_fee'   => null,
            'free_threshold' => null,
            'error_message'  => 'Bitte geben Sie eine gültige 4-stellige österreichische Postleitzahl ein.',
        ];
    }

    $zones = wowthai_get_delivery_zones();

    // Check Core Zone (4300)
    if (in_array($plz, $zones['core']['postcodes'], true)) {
        $z = $zones['core'];
        return [
            'valid'          => true,
            'plz'            => $plz,
            'zone'           => 'core',
            'zone_name'      => $z['name'],
            'city'           => $z['cities'][$plz] ?? 'St. Valentin',
            'min_order'      => $z['min_order'],
            'delivery_fee'   => $z['delivery_fee'],
            'free_threshold' => $z['free_threshold'],
            'error_message'  => null,
        ];
    }

    // Check Extended Zone (4303, 4470, 4481)
    if (in_array($plz, $zones['extended']['postcodes'], true)) {
        $z = $zones['extended'];
        return [
            'valid'          => true,
            'plz'            => $plz,
            'zone'           => 'extended',
            'zone_name'      => $z['name'],
            'city'           => $z['cities'][$plz] ?? '',
            'min_order'      => $z['min_order'],
            'delivery_fee'   => $z['delivery_fee'],
            'free_threshold' => $z['free_threshold'],
            'error_message'  => null,
        ];
    }

    // Out-of-Zone: Graceful German rejection
    return [
        'valid'          => false,
        'plz'            => $plz,
        'zone'           => null,
        'zone_name'      => null,
        'city'           => null,
        'min_order'      => null,
        'delivery_fee'   => null,
        'free_threshold' => null,
        'error_message'  => sprintf(
            'Leider beliefern wir die Postleitzahl %s derzeit nicht. Eine Lieferung ist nur in St. Valentin (4300), Ennsdorf (4303), Enns (4470) und Asten (4481) möglich. Möchten Sie Ihre Bestellung stattdessen zur kostenlosen Selbstabholung im Restaurant aufgeben?',
            esc_html($plz)
        ),
    ];
}

/**
 * Comprehensive Delivery Pricing & Threshold Engine.
 *
 * @param string $postcode
 * @param float $subtotal
 * @param string $fulfillment_type 'delivery'|'lieferung'|'pickup'|'abholung'
 * @return array
 */
function wowthai_get_delivery_pricing(string $postcode, float $subtotal, string $fulfillment_type = 'delivery'): array {
    $subtotal = round((float) $subtotal, 2);
    $ful = strtolower(trim($fulfillment_type));

    if ($ful === 'pickup' || $ful === 'abholung' || $ful === 'takeaway') {
        return [
            'valid'              => true,
            'allowed'            => true,
            'fulfillment_type'   => 'abholung',
            'zone'               => null,
            'zone_name'          => null,
            'city'               => null,
            'subtotal'           => $subtotal,
            'fee'                => 0.00,
            'min_order'          => 0.00,
            'deficit'            => 0.00,
            'free_threshold'     => null,
            'free_threshold_met' => true,
            'remaining_to_free'  => 0.00,
            'total'              => $subtotal,
            'notice'             => 'Kostenlose Selbstabholung im Restaurant',
            'error_message'      => null,
        ];
    }

    $val = wowthai_validate_delivery_plz($postcode);
    if (!$val['valid']) {
        return [
            'valid'              => false,
            'allowed'            => false,
            'fulfillment_type'   => 'lieferung',
            'zone'               => null,
            'zone_name'          => null,
            'city'               => null,
            'subtotal'           => $subtotal,
            'fee'                => 0.00,
            'min_order'          => null,
            'deficit'            => 0.00,
            'free_threshold'     => null,
            'free_threshold_met' => false,
            'remaining_to_free'  => null,
            'total'              => $subtotal,
            'notice'             => null,
            'error_message'      => $val['error_message'],
        ];
    }

    $min_order      = (float) $val['min_order'];
    $standard_fee   = (float) $val['delivery_fee'];
    $free_threshold = (float) $val['free_threshold'];

    // Below Minimum Order Threshold
    if ($subtotal < $min_order) {
        $deficit = round($min_order - $subtotal, 2);
        $ort = ($val['plz'] === '4300')
            ? '4300 St. Valentin'
            : trim($val['plz'] . ' ' . (string) ($val['city'] ?? ''));

        return [
            'valid'              => true,
            'allowed'            => false,
            'fulfillment_type'   => 'lieferung',
            'zone'               => $val['zone'],
            'zone_name'          => $val['zone_name'],
            'city'               => $val['city'],
            'subtotal'           => $subtotal,
            'fee'                => $standard_fee,
            'min_order'          => $min_order,
            'deficit'            => $deficit,
            'free_threshold'     => $free_threshold,
            'free_threshold_met' => false,
            'remaining_to_free'  => round($free_threshold - $subtotal, 2),
            'total'              => round($subtotal + $standard_fee, 2),
            'notice'             => null,
            'error_message'      => sprintf(
                'Der Mindestbestellwert für Lieferungen nach %s beträgt %s €. Ihr aktueller Bestellwert liegt bei %s €. Bitte fügen Sie noch Artikel im Wert von %s € hinzu.',
                $ort,
                number_format($min_order, 2, ',', '.'),
                number_format($subtotal, 2, ',', '.'),
                number_format($deficit, 2, ',', '.')
            ),
        ];
    }

    // Free Delivery Threshold Met
    if ($subtotal >= $free_threshold) {
        return [
            'valid'              => true,
            'allowed'            => true,
            'fulfillment_type'   => 'lieferung',
            'zone'               => $val['zone'],
            'zone_name'          => $val['zone_name'],
            'city'               => $val['city'],
            'subtotal'           => $subtotal,
            'fee'                => 0.00,
            'min_order'          => $min_order,
            'deficit'            => 0.00,
            'free_threshold'     => $free_threshold,
            'free_threshold_met' => true,
            'remaining_to_free'  => 0.00,
            'total'              => $subtotal,
            'notice'             => '✓ Kostenlose Lieferung! Sie haben die Freigrenze erreicht.',
            'error_message'      => null,
        ];
    }

    // Standard Delivery Fee Applies
    $remaining_to_free = round($free_threshold - $subtotal, 2);
    return [
        'valid'              => true,
        'allowed'            => true,
        'fulfillment_type'   => 'lieferung',
        'zone'               => $val['zone'],
        'zone_name'          => $val['zone_name'],
        'city'               => $val['city'],
        'subtotal'           => $subtotal,
        'fee'                => $standard_fee,
        'min_order'          => $min_order,
        'deficit'            => 0.00,
        'free_threshold'     => $free_threshold,
        'free_threshold_met' => false,
        'remaining_to_free'  => $remaining_to_free,
        'total'              => round($subtotal + $standard_fee, 2),
        'notice'             => sprintf(
            'Liefergebühr: %s € · Noch %s € bis zur kostenlosen Lieferung!',
            number_format($standard_fee, 2, ',', '.'),
            number_format($remaining_to_free, 2, ',', '.')
        ),
        'error_message'      => null,
    ];
}

/**
 * Calculates delivery fee as a simple float.
 *
 * @param string $postcode
 * @param float $subtotal
 * @return float
 */
function wowthai_calculate_delivery_fee(string $postcode, float $subtotal): float {
    $pricing = wowthai_get_delivery_pricing($postcode, $subtotal, 'delivery');
    return (float) $pricing['fee'];
}

/**
 * Calculates estimated delivery ETA window.
 *
 * @param string|null $postcode Postal code to evaluate (optional)
 * @param string|null $prep_preset Kitchen prep preset override (optional)
 * @return string Nominal ETA window, e.g. 'ca. 30–45 Min.' or '30–45 Minuten'
 */
function wowthai_get_delivery_eta(?string $postcode = null, ?string $prep_preset = null): string {
    $plz = !empty($postcode) ? wowthai_normalize_plz($postcode) : '4300';
    $val = wowthai_validate_delivery_plz($plz);

    // Resolve prep time boundaries
    if ($prep_preset === null) {
        $option_val = get_option('wowthai_kitchen_prep_time', '25–35 Min.');
        switch ($option_val) {
            case '15–20 Min.': $prep_preset = 'express'; break;
            case '40–50 Min.': $prep_preset = 'busy'; break;
            case '60+ Min.':   $prep_preset = 'surge'; break;
            default:           $prep_preset = 'normal'; break;
        }
    }

    $prep_min = 20;
    $prep_max = 30;
    switch ($prep_preset) {
        case 'express': $prep_min = 15; $prep_max = 20; break;
        case 'busy':    $prep_min = 35; $prep_max = 45; break;
        case 'surge':   $prep_min = 45; $prep_max = 60; break;
        default:        $prep_min = 20; $prep_max = 30; break;
    }

    $transit_min = ($val['zone'] === 'extended') ? 15 : 10;
    $transit_max = ($val['zone'] === 'extended') ? 25 : 15;

    $total_min = $prep_min + $transit_min;
    $total_max = $prep_max + $transit_max;

    return sprintf('ca. %d–%d Min.', $total_min, $total_max);
}

/**
 * AJAX Handler: Real-time Postal Code Verification
 */
function wowthai_ajax_check_delivery_plz() {
    $raw_plz  = isset($_REQUEST['postcode']) ? sanitize_text_field(wp_unslash($_REQUEST['postcode'])) : ''; // verify-ok
    $plz      = wowthai_normalize_plz($raw_plz);
    $subtotal = isset($_REQUEST['subtotal']) ? (float) sanitize_text_field(wp_unslash($_REQUEST['subtotal'])) : (function_exists('WC') && WC()->cart ? (float) WC()->cart->get_subtotal() : 0.0); // verify-ok

    $pricing  = wowthai_get_delivery_pricing($plz, $subtotal, 'delivery');
    $val      = wowthai_validate_delivery_plz($plz);
    $eta      = wowthai_get_delivery_eta($plz);

    $response = array_merge($pricing, [
        'plz'             => $plz,
        'city_suggestion' => $val['city'] ?? '',
        'eta'             => $eta,
    ]);

    wp_send_json_success($response);
}
add_action('wp_ajax_wowthai_check_delivery_plz', 'wowthai_ajax_check_delivery_plz');
add_action('wp_ajax_nopriv_wowthai_check_delivery_plz', 'wowthai_ajax_check_delivery_plz');

/**
 * Synchronizes fulfillment mode and delivery PLZ into WC Session during AJAX review.
 *
 * @param string $post_data
 */
function wowthai_sync_checkout_session($post_data) {
    if (!function_exists('WC') || !WC()->session) {
        return;
    }

    wp_parse_str($post_data, $data); // verify-ok

    $branch_has_dlv = function_exists('wowthai_branch_has_delivery') && wowthai_branch_has_delivery();
    $fulfillment    = sanitize_text_field($data['fulfillment_type'] ?? ($branch_has_dlv ? 'delivery' : 'pickup'));
    $postcode       = wowthai_normalize_plz($data['billing_postcode'] ?? '');

    WC()->session->set('wowthai_fulfillment_type', $fulfillment);
    WC()->session->set('wowthai_delivery_plz', $postcode);
}
add_action('woocommerce_checkout_update_order_review', 'wowthai_sync_checkout_session', 10, 1);

/**
 * Injects dynamic delivery fee to WooCommerce cart totals.
 *
 * @param WC_Cart $cart
 */
function wowthai_apply_delivery_fee_to_cart($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    $branch_id = function_exists('wowthai_get_branch_id') ? wowthai_get_branch_id() : 'steyr';
    if (!function_exists('wowthai_branch_has_delivery') || !wowthai_branch_has_delivery($branch_id)) {
        return;
    }

    // Resolve fulfillment mode
    $fulfillment = 'delivery';
    if (isset($_POST['fulfillment_type'])) {
        $fulfillment = sanitize_text_field(wp_unslash($_POST['fulfillment_type']));
    } elseif (isset($_POST['post_data'])) {
        wp_parse_str(wp_unslash($_POST['post_data']), $pd); // verify-ok
        if (!empty($pd['fulfillment_type'])) {
            $fulfillment = sanitize_text_field($pd['fulfillment_type']);
        }
    } elseif (function_exists('WC') && WC()->session) {
        $fulfillment = WC()->session->get('wowthai_fulfillment_type', 'delivery');
    }

    if ($fulfillment !== 'delivery' && $fulfillment !== 'lieferung') {
        return;
    }

    // Resolve delivery postcode
    $raw_plz = '';
    if (isset($_POST['billing_postcode'])) {
        $raw_plz = sanitize_text_field(wp_unslash($_POST['billing_postcode']));
    } elseif (isset($_POST['post_data'])) {
        wp_parse_str(wp_unslash($_POST['post_data']), $pd); // verify-ok
        if (!empty($pd['billing_postcode'])) {
            $raw_plz = sanitize_text_field($pd['billing_postcode']);
        }
    } elseif (function_exists('WC') && WC()->session) {
        $raw_plz = (string) WC()->session->get('wowthai_delivery_plz', '');
    }

    $plz = wowthai_normalize_plz($raw_plz);
    if ($plz === '') {
        return;
    }

    $subtotal = (float) $cart->get_subtotal();
    $pricing  = wowthai_get_delivery_pricing($plz, $subtotal, 'delivery');

    if ($pricing['valid'] && $pricing['fee'] > 0.0) {
        $label = !empty($pricing['zone_name'])
            ? sprintf('Liefergebühr (%s)', $pricing['zone_name'])
            : 'Liefergebühr';
        $cart->add_fee($label, $pricing['fee'], true, '');
    }
}
add_action('woocommerce_cart_calculate_fees', 'wowthai_apply_delivery_fee_to_cart', 20, 1);

/**
 * Validates delivery requirements during checkout submission.
 *
 * @param array $data Posted checkout data
 * @param WP_Error $errors WooCommerce error accumulator
 */
function wowthai_validate_delivery_checkout($data, $errors) {
    $branch_id    = function_exists('wowthai_get_branch_id') ? wowthai_get_branch_id() : 'steyr';
    $has_delivery = function_exists('wowthai_branch_has_delivery') && wowthai_branch_has_delivery($branch_id);

    $fulfillment = sanitize_text_field($_POST['fulfillment_type'] ?? ($has_delivery ? 'delivery' : 'pickup')); // verify-ok

    if ($fulfillment === 'delivery' || $fulfillment === 'lieferung') {
        if (!$has_delivery) {
            $errors->add('delivery_unsupported', 'Lieferung ist für diesen Restaurantstandort nicht verfügbar.');
            return;
        }

        // Validate customer delivery street
        $street = trim(sanitize_text_field($_POST['billing_address_1'] ?? '')); // verify-ok
        if ($street === '' || stripos($street, 'Selbstabholung') !== false) {
            $errors->add('billing_address_1_required', 'Bitte geben Sie Ihre Straße und Hausnummer für die Zustellung ein.');
        }

        // Validate phone number
        $phone = trim(sanitize_text_field($_POST['billing_phone'] ?? '')); // verify-ok
        if ($phone === '') {
            $errors->add('billing_phone_required', 'Bitte geben Sie Ihre Telefonnummer für allfällige Rückfragen des Fahrers ein.');
        }

        // Validate Postal Code
        $raw_plz = sanitize_text_field($_POST['billing_postcode'] ?? ''); // verify-ok
        $plz     = wowthai_normalize_plz($raw_plz);
        $val     = wowthai_validate_delivery_plz($plz);

        if (!$val['valid']) {
            $errors->add('out_of_zone', $val['error_message']);
            return;
        }

        // Validate Minimum Order Threshold
        $subtotal = function_exists('WC') && WC()->cart ? (float) WC()->cart->get_subtotal() : 0.0;
        $pricing  = wowthai_get_delivery_pricing($plz, $subtotal, 'delivery');

        if (!$pricing['allowed']) {
            $errors->add('min_order_deficit', $pricing['error_message']);
            return;
        }

        // Validate Delivery Cutoff (Last delivery order accepted 45m before kitchen close)
        if (function_exists('wowthai_now') && function_exists('wowthai_hours_ranges_for_date')) {
            $now = wowthai_now();
            $windows = wowthai_hours_ranges_for_date($now);
            $mode = sanitize_text_field($_POST['pickup_mode'] ?? 'asap'); // verify-ok
            if ($mode === 'asap') {
                $within_cutoff = false;
                foreach ($windows as [$start, $end]) {
                    $open  = wowthai_datetime_at_hm($now, $start);
                    $close = wowthai_datetime_at_hm($now, $end)->modify('-45 minutes');
                    if ($now >= $open && $now <= $close) {
                        $within_cutoff = true;
                        break;
                    }
                }
                if (!$within_cutoff && count($windows) > 0) {
                    $errors->add('delivery_cutoff', 'Bestellungen zur sofortigen Lieferung sind nur bis 45 Minuten vor Küchenschluss möglich. Bitte wählen Sie eine spätere Lieferzeit.');
                }
            }
        }
    }
}
add_action('woocommerce_after_checkout_validation', 'wowthai_validate_delivery_checkout', 20, 2);

/**
 * Persists delivery metadata to the WooCommerce Order upon placement.
 *
 * @param WC_Order $order
 * @param array $data
 */
function wowthai_save_delivery_order_meta($order, $data = []) {
    if (!$order instanceof WC_Order) {
        return;
    }
    $branch_id    = function_exists('wowthai_get_branch_id') ? wowthai_get_branch_id() : 'steyr';
    $has_delivery = function_exists('wowthai_branch_has_delivery') && wowthai_branch_has_delivery($branch_id);
    $fulfillment  = sanitize_text_field($_POST['fulfillment_type'] ?? ($has_delivery ? 'delivery' : 'pickup')); // verify-ok

    $order->update_meta_data('_wowthai_branch_id', $branch_id);
    $order->update_meta_data('_wowthai_delivery_type', $fulfillment);
    $order->update_meta_data('_fulfillment_type', $fulfillment);

    if ($fulfillment === 'delivery' || $fulfillment === 'lieferung') {
        $raw_plz     = sanitize_text_field($_POST['billing_postcode'] ?? ''); // verify-ok
        $plz         = wowthai_normalize_plz($raw_plz);
        $val         = wowthai_validate_delivery_plz($plz);
        $street      = sanitize_text_field($_POST['billing_address_1'] ?? ''); // verify-ok
        $details     = sanitize_text_field($_POST['billing_address_2'] ?? ''); // verify-ok
        $city        = sanitize_text_field($_POST['billing_city'] ?? ($val['city'] ?? '')); // verify-ok
        $subtotal    = (float) $order->get_subtotal();
        $pricing     = wowthai_get_delivery_pricing($plz, $subtotal, 'delivery');
        $eta         = wowthai_get_delivery_eta($plz);
        $driver_note = sanitize_textarea_field($_POST['order_comments'] ?? ''); // verify-ok

        // Persist customer delivery address on Order object
        $order->set_billing_country('AT');
        $order->set_billing_address_1($street);
        $order->set_billing_address_2($details);
        $order->set_billing_postcode($plz);
        $order->set_billing_city($city);

        $order->set_shipping_country('AT');
        $order->set_shipping_address_1($street);
        $order->set_shipping_address_2($details);
        $order->set_shipping_postcode($plz);
        $order->set_shipping_city($city);

        // Store specialized driver & routing metadata
        $order->update_meta_data('_wowthai_delivery_zone', $val['zone']);
        $order->update_meta_data('_wowthai_delivery_zone_name', $val['zone_name']);
        $order->update_meta_data('_wowthai_delivery_fee', (float) $pricing['fee']);
        $order->update_meta_data('_wowthai_plz', $plz);
        $order->update_meta_data('_wowthai_delivery_plz', $plz);
        $order->update_meta_data('_wowthai_delivery_city', $city);
        $order->update_meta_data('_wowthai_delivery_details', $details);
        $order->update_meta_data('_wowthai_delivery_eta', $eta);
        $order->update_meta_data('_wowthai_driver_note', $driver_note);
        $order->update_meta_data('Lieferadresse', sprintf('%s%s, %s %s', $street, $details ? ' (' . $details . ')' : '', $plz, $city));
        $order->update_meta_data('Lieferzeit', $eta);
    } else {
        $order->update_meta_data('_wowthai_delivery_zone', null);
        $order->update_meta_data('_wowthai_delivery_fee', 0.00);
    }
}
add_action('woocommerce_checkout_create_order', 'wowthai_save_delivery_order_meta', 15, 2);
add_action('woocommerce_checkout_order_created', 'wowthai_save_delivery_order_meta', 15, 1);
