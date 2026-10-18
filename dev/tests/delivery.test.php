<?php
/**
 * WOW Express Delivery Engine & Driver Receipt Unit Tests
 * 
 * Verifies:
 * - PLZ Zone validation (4300, 4303, 4470, 4481, boundaries, formatting)
 * - Fee calculation & minimum order thresholds
 * - Dynamic delivery ETA calculation across prep presets
 * - 42-column monospace 80mm driver thermal receipt generation
 * - Prominent online paid vs cash-on-delivery warning banners
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 2) . '/');
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value) { return $value; }
}
if (!function_exists('get_option')) {
    function get_option($key, $default = false) { return $default; }
}
if (!function_exists('wp_date')) {
    function wp_date($format, $timestamp = null, $timezone = null) {
        $tz = $timezone ?: new DateTimeZone('Europe/Vienna');
        $dt = new DateTimeImmutable($timestamp ? '@' . $timestamp : 'now');
        return $dt->setTimezone($tz)->format($format);
    }
}
if (!function_exists('esc_html')) {
    function esc_html($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('number_format_i18n')) {
    function number_format_i18n($number, $decimals = 0) { return number_format((float)$number, $decimals, ',', '.'); }
}
if (!function_exists('add_action')) {
    function add_action($tag, $callback, $priority = 10, $accepted_args = 1) {}
}
if (!function_exists('add_filter')) {
    function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) {}
}
if (!function_exists('admin_url')) {
    function admin_url($path = '') { return 'http://localhost/wp-admin/' . ltrim($path, '/'); }
}
if (!function_exists('wp_die')) {
    function wp_die($msg = '') { die($msg); }
}
if (!function_exists('current_user_can')) {
    function current_user_can($cap) { return true; }
}
if (!function_exists('is_admin')) {
    function is_admin() { return false; }
}
if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null) {}
}
if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null) {}
}
if (!function_exists('check_admin_referer')) {
    function check_admin_referer($action = -1, $query_arg = '_wpnonce') { return 1; }
}
if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) { return 'test_nonce'; }
}
if (!function_exists('add_meta_box')) {
    function add_meta_box($id, $title, $callback, $screen = null, $context = 'advanced', $priority = 'default', $callback_args = null) {}
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) { return trim(strip_tags((string)$str)); }
}
if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) { return trim(strip_tags((string)$str)); }
}
if (!function_exists('wp_unslash')) {
    function wp_unslash($val) { return is_string($val) ? stripslashes($val) : $val; }
}
if (!function_exists('wc_get_order')) {
    function wc_get_order($id) { return false; }
}

$theme_dir = dirname(__DIR__, 2);
require_once $theme_dir . '/inc/branch-config.php';
require_once $theme_dir . '/inc/delivery.php';
require_once $theme_dir . '/inc/admin-orders.php';

$passed = 0;
$failed = 0;

function assert_true($label, $condition) {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "✅ PASS: {$label}\n";
    } else {
        $failed++;
        echo "❌ FAIL: {$label}\n";
    }
}

function assert_eq($label, $actual, $expected) {
    global $passed, $failed;
    if ($actual === $expected) {
        $passed++;
        echo "✅ PASS: {$label}\n";
    } else {
        $failed++;
        echo "❌ FAIL: {$label} (expected " . var_export($expected, true) . ", got " . var_export($actual, true) . ")\n";
    }
}

echo "==============================================================\n";
echo " WOW EXPRESS DELIVERY ENGINE & DRIVER RECEIPT TEST SUITE\n";
echo "==============================================================\n\n";

// 1. PLZ Normalization
echo "--- 1. PLZ Normalization ---\n";
assert_eq('Standard 4-digit PLZ', wowthai_normalize_plz('4300'), '4300');
assert_eq('PLZ with whitespace', wowthai_normalize_plz('  4300  '), '4300');
assert_eq('PLZ with A- prefix', wowthai_normalize_plz('A-4300'), '4300');
assert_eq('PLZ with AT- prefix and city', wowthai_normalize_plz('AT-4470 Enns'), '4470');
assert_eq('Non-numeric input returns empty or digits', wowthai_normalize_plz('ABCD'), '');
assert_eq('German 5-digit PLZ', wowthai_normalize_plz('80331 München'), '80331');

// 2. PLZ Zone Validation
echo "\n--- 2. PLZ Zone Validation ---\n";
$val_4300 = wowthai_validate_delivery_plz('4300');
assert_true('PLZ 4300 is valid', $val_4300['valid']);
assert_eq('PLZ 4300 zone is core', $val_4300['zone'], 'core');
assert_eq('PLZ 4300 min_order is 20.00', $val_4300['min_order'], 20.00);
assert_eq('PLZ 4300 delivery_fee is 2.50', $val_4300['delivery_fee'], 2.50);
assert_eq('PLZ 4300 free_threshold is 30.00', $val_4300['free_threshold'], 30.00);

$val_4303 = wowthai_validate_delivery_plz('4303');
assert_true('PLZ 4303 is valid', $val_4303['valid']);
assert_eq('PLZ 4303 zone is extended', $val_4303['zone'], 'extended');
assert_eq('PLZ 4303 min_order is 25.00', $val_4303['min_order'], 25.00);
assert_eq('PLZ 4303 delivery_fee is 3.90', $val_4303['delivery_fee'], 3.90);
assert_eq('PLZ 4303 free_threshold is 45.00', $val_4303['free_threshold'], 45.00);

$val_4470 = wowthai_validate_delivery_plz('4470');
assert_true('PLZ 4470 is valid', $val_4470['valid']);
assert_eq('PLZ 4470 zone is extended', $val_4470['zone'], 'extended');

$val_4481 = wowthai_validate_delivery_plz('4481');
assert_true('PLZ 4481 is valid', $val_4481['valid']);
assert_eq('PLZ 4481 zone is extended', $val_4481['zone'], 'extended');

// Out of Zone Boundaries
$out_zones = ['4299', '4301', '4302', '4304', '4400', '4469', '4471', '4480', '4482', '80331'];
foreach ($out_zones as $oz) {
    $res = wowthai_validate_delivery_plz($oz);
    assert_true("PLZ {$oz} is invalid (Out of zone)", !$res['valid']);
    assert_true("PLZ {$oz} has advisory message", !empty($res['error_message']));
}

// 3. Delivery Fee & Minimum Order Calculations
echo "\n--- 3. Delivery Fee & Thresholds ---\n";
// Core Zone (4300): Min €20.00, Fee €2.50, Free €30.00
assert_eq('Core €15.00 fee is €2.50', wowthai_calculate_delivery_fee('4300', 15.00), 2.50);
assert_eq('Core €20.00 fee is €2.50', wowthai_calculate_delivery_fee('4300', 20.00), 2.50);
assert_eq('Core €29.99 fee is €2.50', wowthai_calculate_delivery_fee('4300', 29.99), 2.50);
assert_eq('Core €30.00 fee is waived (€0.00)', wowthai_calculate_delivery_fee('4300', 30.00), 0.00);
assert_eq('Core €50.00 fee is waived (€0.00)', wowthai_calculate_delivery_fee('4300', 50.00), 0.00);

// Extended Zone (4303, 4470, 4481): Min €25.00, Fee €3.90, Free €45.00
assert_eq('Extended €22.00 fee is €3.90', wowthai_calculate_delivery_fee('4470', 22.00), 3.90);
assert_eq('Extended €25.00 fee is €3.90', wowthai_calculate_delivery_fee('4470', 25.00), 3.90);
assert_eq('Extended €44.99 fee is €3.90', wowthai_calculate_delivery_fee('4470', 44.99), 3.90);
assert_eq('Extended €45.00 fee is waived (€0.00)', wowthai_calculate_delivery_fee('4470', 45.00), 0.00);

// 4. Exact 1-Cent Pricing Boundaries
echo "\n--- 4. Exact 1-Cent Pricing Boundaries ---\n";
$p_1999 = wowthai_get_delivery_pricing('4300', 19.99, 'delivery');
assert_true('Core €19.99 is blocked', !$p_1999['allowed']);
assert_eq('Core €19.99 deficit is €0.01', $p_1999['deficit'], 0.01);

$p_2000 = wowthai_get_delivery_pricing('4300', 20.00, 'delivery');
assert_true('Core €20.00 is allowed', $p_2000['allowed']);
assert_eq('Core €20.00 fee is 2.50', $p_2000['fee'], 2.50);
assert_eq('Core €20.00 remaining to free is 10.00', $p_2000['remaining_to_free'], 10.00);

$p_2999 = wowthai_get_delivery_pricing('4300', 29.99, 'delivery');
assert_true('Core €29.99 is allowed', $p_2999['allowed']);
assert_eq('Core €29.99 remaining to free is 0.01', $p_2999['remaining_to_free'], 0.01);
assert_true('Core €29.99 free threshold not met', !$p_2999['free_threshold_met']);

$p_3000 = wowthai_get_delivery_pricing('4300', 30.00, 'delivery');
assert_true('Core €30.00 is allowed', $p_3000['allowed']);
assert_eq('Core €30.00 fee is 0.00', $p_3000['fee'], 0.00);
assert_true('Core €30.00 free threshold is met', $p_3000['free_threshold_met']);

$p_2499 = wowthai_get_delivery_pricing('4470', 24.99, 'delivery');
assert_true('Extended €24.99 is blocked', !$p_2499['allowed']);
assert_eq('Extended €24.99 deficit is €0.01', $p_2499['deficit'], 0.01);

$p_2500 = wowthai_get_delivery_pricing('4470', 25.00, 'delivery');
assert_true('Extended €25.00 is allowed', $p_2500['allowed']);
assert_eq('Extended €25.00 fee is 3.90', $p_2500['fee'], 3.90);

$p_4499 = wowthai_get_delivery_pricing('4470', 44.99, 'delivery');
assert_true('Extended €44.99 remaining to free is 0.01', $p_4499['remaining_to_free'] == 0.01);

$p_4500 = wowthai_get_delivery_pricing('4470', 45.00, 'delivery');
assert_true('Extended €45.00 fee waived', $p_4500['fee'] == 0.00);
assert_true('Extended €45.00 free threshold met', $p_4500['free_threshold_met']);

// Takeaway fulfillment overrides
$p_pickup = wowthai_get_delivery_pricing('4400', 10.00, 'pickup');
assert_true('Takeaway allowed even under min order', $p_pickup['allowed']);
assert_eq('Takeaway fee is 0.00', $p_pickup['fee'], 0.00);

// 5. Dynamic Delivery ETA Windows
echo "\n--- 5. Dynamic Delivery ETA Windows ---\n";
assert_eq('Core Zone normal ETA', wowthai_get_delivery_eta('4300', 'normal'), 'ca. 30–45 Min.');
assert_eq('Extended Zone normal ETA', wowthai_get_delivery_eta('4470', 'normal'), 'ca. 35–55 Min.');
assert_eq('Core Zone surge ETA', wowthai_get_delivery_eta('4300', 'surge'), 'ca. 55–75 Min.');
assert_eq('Extended Zone surge ETA', wowthai_get_delivery_eta('4470', 'surge'), 'ca. 60–85 Min.');

// 6. 80mm Monospace Thermal Driver Receipt Generator
echo "\n--- 6. 80mm Monospace Thermal Driver Receipt Generator ---\n";
$sample_order_online = [
    'id'                   => 10452,
    'order_number'         => '10452',
    'created_at_vienna'    => '17.10.2026 · 18:25 Uhr',
    'target_time'          => 'ca. 30–45 Min. (ASAP)',
    'customer_name'        => 'Herr Markus Weber',
    'customer_phone'       => '+43 676 9876543',
    'customer_address'     => [
        'street'  => 'Franz-Lehar-Gasse 8',
        'details' => 'Stiege 1, Top 4 (2. Stock)',
        'plz'     => '4300',
        'city'    => 'St. Valentin',
    ],
    'zone_label'           => 'ZONE 1 (Core · St. Valentin)',
    'driver_note'          => 'Bei Weber läuten',
    'payment_status'       => 'paid',
    'payment_method'       => 'stripe',
    'payment_method_title' => 'Stripe Kreditkarte',
    'subtotal'             => 32.00,
    'delivery_fee'         => 2.50,
    'total'                => 34.50,
    'items'                => [
        ['quantity' => 2, 'name' => 'Gebratene Nudeln (Huhn)', 'modifiers' => ['Knuspriges Huhn', 'Scharf']],
        ['quantity' => 1, 'name' => 'Tom Kha Gai (Suppe)', 'modifiers' => []],
        ['quantity' => 2, 'name' => 'Singha Bier 0.33l', 'modifiers' => []],
    ],
];

$bon_online = wowthai_render_driver_receipt($sample_order_online);
assert_true('Separators = (42 chars)', strpos($bon_online, str_repeat('=', 42)) !== false);
assert_true('Separators - (42 chars)', strpos($bon_online, str_repeat('-', 42)) !== false);
assert_true('Separators * (42 chars)', strpos($bon_online, str_repeat('*', 42)) !== false);
assert_true('Customer name printed', strpos($bon_online, 'Herr Markus Weber') !== false);
assert_true('Street address printed', strpos($bon_online, 'Franz-Lehar-Gasse 8') !== false);
assert_true('Apartment details printed', strpos($bon_online, 'Stiege 1, Top 4 (2. Stock)') !== false);
assert_true('PLZ and City printed', strpos($bon_online, '4300 St. Valentin') !== false);
assert_true('Telephone number printed', strpos($bon_online, '+43 676 9876543') !== false);

// Online Paid Prominent Banners
assert_true('Online Paid banner: [✓ BEREITS ONLINE BEZAHLT - NICHT KASSIEREN]', strpos($bon_online, '[✓ BEREITS ONLINE BEZAHLT - NICHT KASSIEREN]') !== false);
assert_true('Online Paid banner: [ ✓ BEREITS ONLINE BEZAHLT ]', strpos($bon_online, '[ ✓ BEREITS ONLINE BEZAHLT ]') !== false);
assert_true('Online Paid banner: KEIN GELD BEIM KUNDEN KASSIEREN!', strpos($bon_online, 'KEIN GELD BEIM KUNDEN KASSIEREN!') !== false);
assert_true('Online Paid does NOT contain COD banner', strpos($bon_online, '[ ⚠️ BARZAHLUNG BEI LIEFERUNG ]') === false);

// Checklist items and totals
assert_true('Packing checklist header', strpos($bon_online, 'KONTROLL-PACKLISTE (3 Artikel):') !== false);
assert_true('Checklist checkbox [ ]', strpos($bon_online, ' [ ] 2x Gebratene Nudeln (Huhn)') !== false);
assert_true('Checklist modifier', strpos($bon_online, '+ Knuspriges Huhn, Scharf') !== false);
assert_true('Zwischensumme right-aligned', preg_match('/Zwischensumme:\s+32,00 €/', $bon_online) === 1);
assert_true('Liefergebühr right-aligned', preg_match('/Liefergebühr:\s+2,50 €/', $bon_online) === 1);
assert_true('GESAMTSUMME right-aligned', preg_match('/GESAMTSUMME:\s+34,50 €/', $bon_online) === 1);

// COD Order
$sample_order_cod = $sample_order_online;
$sample_order_cod['payment_status'] = 'unpaid';
$sample_order_cod['payment_method'] = 'cod';
$sample_order_cod['payment_method_title'] = 'Barzahlung bei Lieferung';

$bon_cod = wowthai_render_driver_receipt($sample_order_cod);
assert_true('COD banner: [⚠️ BARZAHLUNG BEI LIEFERUNG - KASSIEREN! ]', strpos($bon_cod, '[⚠️ BARZAHLUNG BEI LIEFERUNG - KASSIEREN! ]') !== false);
assert_true('COD banner: [ ⚠️ BARZAHLUNG BEI LIEFERUNG ]', strpos($bon_cod, '[ ⚠️ BARZAHLUNG BEI LIEFERUNG ]') !== false);
assert_true('COD banner: BITTE BEIM KUNDEN KASSIEREN:', strpos($bon_cod, 'BITTE BEIM KUNDEN KASSIEREN:') !== false);
assert_true('COD banner: EXAKTER BETRAG:  34,50 €', strpos($bon_cod, 'EXAKTER BETRAG:  34,50 €') !== false);
assert_true('COD does NOT contain Online Paid banner', strpos($bon_cod, '[ ✓ BEREITS ONLINE BEZAHLT ]') === false);

echo "\n==============================================================\n";
echo " Delivery Unit Test Summary: {$passed} passed, {$failed} failed.\n";
echo "==============================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
