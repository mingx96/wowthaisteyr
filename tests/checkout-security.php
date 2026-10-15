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
    public function get_price() { return $this->price; }
}
function wc_get_product($id) { return new TestProduct((float) get_post_meta($id, '_price')); }
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
    56 => [
        '_wowthai_has_variants' => 'yes',
        '_price' => '13.50',
        '_wowthai_variants' => [
            ['id' => 'vegi', 'name' => 'Vegetarisch', 'price' => '13.50'],
            ['id' => 'shrimp', 'name' => 'mit Garnelen', 'price' => '22.90'],
        ],
        '_wowthai_modifier_groups' => [[
            'id' => 'noodles', 'name' => 'Nudeln', 'min' => 1, 'max' => 1,
            'options' => [
                ['id' => 'rice', 'name' => 'mit Reisbandnudeln', 'price' => '0.00'],
                ['id' => 'glass', 'name' => 'mit Glasnudeln', 'price' => '0.00'],
            ],
        ]],
    ],
    47 => [
        '_wowthai_has_variants' => 'yes',
        '_price' => '17.50',
        '_wowthai_variants' => [['id' => 'chicken', 'name' => 'mit Huhn', 'price' => '17.50']],
        '_wowthai_modifier_groups' => [[
            'id' => 'extra', 'name' => 'Extra', 'min' => 0, 'max' => 1,
            'options' => [['id' => 'egg', 'name' => 'mit extra Spiegelei', 'price' => '2.60']],
        ]],
    ],
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

check(is_wp_error(wowthai_get_trusted_modifiers(56, [])), 'Required noodle choice must be enforced');
check(wowthai_get_trusted_modifiers(56, ['glass'])['choices'][0]['name'] === 'mit Glasnudeln', 'Noodle selection must resolve from catalog');
check(is_wp_error(wowthai_get_trusted_modifiers(56, ['rice', 'glass'])), 'Two noodles must be rejected');
check(is_wp_error(wowthai_get_trusted_modifiers(47, ['forged'])), 'Unknown extras must be rejected');
check(is_wp_error(wowthai_get_trusted_modifiers(47, ['egg', 'egg'])), 'Duplicate extras must be rejected');
$cart->cart_contents = [
    'pad-thai' => ['product_id' => 56, 'data' => new TestProduct(0.01), 'wowthai_variant_id' => 'shrimp', 'wowthai_modifier_ids' => ['glass']],
    'pad-thai-without-noodles' => ['product_id' => 56, 'data' => new TestProduct(13.50), 'wowthai_variant_id' => 'vegi'],
    'pad-ka-prao' => ['product_id' => 47, 'data' => new TestProduct(0.01), 'wowthai_variant_id' => 'chicken', 'wowthai_modifier_ids' => ['egg']],
];
wowthai_custom_cart_item_price($cart);
check($cart->cart_contents['pad-thai']['data']->price === 22.9, 'Pad Thai shrimp price must be trusted');
check($cart->cart_contents['pad-thai']['wowthai_modifier_names'][0]['name'] === 'mit Glasnudeln', 'Noodle name must reach the order');
check(!isset($cart->cart_contents['pad-thai-without-noodles']), 'Old Pad Thai cart without noodles must be removed');
check($cart->cart_contents['pad-ka-prao']['data']->price === 20.1, 'Paid extra must be added to the trusted variant price');

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
echo "Menu, checkout security and pickup schedule checks passed\n";
