<?php
/** Run with: php tests/checkout-security.php */

define('ABSPATH', __DIR__ . '/');

function add_action() {}
function add_filter() {}
function remove_action() {}
function get_template_directory() { return dirname(__DIR__); }
function is_admin() { return false; }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', $value)); }
function wp_unslash($value) { return stripslashes($value); }
function wc_format_decimal($value) { return (string) $value; }
function wp_date($format, $timestamp, $timezone) {
    return (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone)->format($format);
}
function get_post_meta($id, $key) {
    global $product_meta;
    return $product_meta[$id][$key] ?? '';
}
function is_wp_error($value) { return $value instanceof WP_Error; }

class WP_Error {
    private $message;
    public function __construct($code, $message) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
class TestProduct {
    public $price;
    public function __construct($price) { $this->price = $price; }
    public function set_price($price) { $this->price = $price; }
}
class TestCart {
    public $cart_contents = [];
    public function get_cart() { return $this->cart_contents; }
    public function remove_cart_item($key) { unset($this->cart_contents[$key]); }
}

$product_meta = [
    79 => [
        '_wowthai_has_variants' => 'yes',
        '_wowthai_variants' => [
            ['id' => 'salad', 'name' => 'Gemischter Salat', 'price' => '16.90'],
            ['id' => 'soup', 'name' => 'Suppe', 'price' => '17.90'],
        ],
    ],
    127 => ['_wowthai_has_variants' => 'no'],
];

require dirname(__DIR__) . '/functions.php';

function check($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$variant = wowthai_get_trusted_variant(79, 'salad');
check($variant['price'] === 16.9 && $variant['name'] === 'Gemischter Salat', 'Catalog price/name must be authoritative');
check(wowthai_get_trusted_variant(79, 'forged') === null, 'Unknown option must be rejected');
check(wowthai_get_trusted_variant(127, 'salad') === null, 'Normal product must reject option');

$cart = new TestCart();
$cart->cart_contents = [
    'tampered' => ['product_id' => 79, 'data' => new TestProduct(0.01), 'wowthai_variant_id' => 'salad', 'wowthai_variant_name' => 'Fake', 'wowthai_variant_price' => 0.01],
    'invalid' => ['product_id' => 79, 'data' => new TestProduct(0.01), 'wowthai_variant_id' => 'forged', 'wowthai_variant_price' => 0.01],
];
wowthai_custom_cart_item_price($cart);
check($cart->cart_contents['tampered']['data']->price === 16.9, 'Tampered cart price must be corrected');
check($cart->cart_contents['tampered']['wowthai_variant_name'] === 'Gemischter Salat', 'Tampered option name must be corrected');
check(!isset($cart->cart_contents['tampered']['wowthai_variant_price']), 'Untrusted price must be discarded');
check(!isset($cart->cart_contents['invalid']), 'Unknown cart option must be removed');

$tz = wowthai_pickup_timezone();
$now = new DateTimeImmutable('2026-10-15 17:42', $tz);
check(wowthai_pickup_asap_time($now)->format('H:i') === '18:12', 'ASAP must use Vienna local time');
$dates = wowthai_pickup_dates($now);
check($dates[0]['value'] === '2026-10-15' && $dates[0]['slots'][0] === '18:15', 'Past and too-soon slots must be hidden');
check(is_wp_error(wowthai_validate_pickup_request(['pickup_mode' => 'later', 'pickup_date' => '2026-10-15', 'pickup_time' => '11:30'], $now)), 'Past slot must be rejected server-side');
check(!is_wp_error(wowthai_validate_pickup_request(['pickup_mode' => 'later', 'pickup_date' => '2026-10-15', 'pickup_time' => '18:15'], $now)), 'Available slot must be accepted');
$closed = new DateTimeImmutable('2026-10-15 21:10', $tz);
check(wowthai_pickup_asap_time($closed) === null, 'ASAP must close before kitchen cutoff');
check($dates[1]['value'] === '2026-10-16', 'Tomorrow must be offered');
$sunday = new DateTimeImmutable('2026-10-18 12:00', $tz);
$sunday_slots = wowthai_pickup_slots_for_date($sunday, $now);
check(end($sunday_slots) === '19:00', 'Sunday pickup must end before closing');
check(!wowthai_validate_reservation_time('2026-10-18', '21:00', $now), 'Sunday reservation after closing must be rejected');
check(wowthai_validate_reservation_time('2026-10-18', '19:00', $now), 'Valid Sunday reservation must be accepted');

echo "Checkout security and schedule checks passed\n";
