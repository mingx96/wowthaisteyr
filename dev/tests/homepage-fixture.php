<?php
/** Actual public templates with synthetic WordPress/catalog boundaries. CLI only. */
if (PHP_SAPI !== 'cli') exit;
define('ABSPATH', dirname(__DIR__, 2) . '/');
$GLOBALS['fixture_page'] = $argv[1] ?? 'home';
function add_action(...$args) {}
function add_filter(...$args) {}
function apply_filters($tag, $value) { return $value; }
function get_option($key, $default = false) { return $default; }
function is_wp_error($value) { return false; }
function home_url($path = '') { return $path; }
function admin_url($path = '') { return '/admin/' . $path; }
function get_template_directory_uri() { return ''; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function sanitize_html_class($value) { return preg_replace('/[^A-Za-z0-9_-]/', '', (string) $value); }
function wp_json_encode($value, ...$args) { return json_encode($value, ...$args); }
function wp_date($format) { return $format === 'Y' ? '2026' : '1'; }
function is_front_page() { return strpos($GLOBALS['fixture_page'], 'home') === 0; }
function is_page($slug) { return $GLOBALS['fixture_page'] === 'menu' && $slug === 'menu'; }
function language_attributes() { echo 'lang="de-AT"'; }
function bloginfo($key) { echo 'UTF-8'; }
function body_class() { echo is_front_page() ? 'class="home"' : 'class="page-menu"'; }
function wp_body_open() {}
function add_query_arg($args, $url = '') { return $url; }
function wp_head() {
    echo '<link rel="stylesheet" href="/assets/css/fonts.css"><link rel="stylesheet" href="/assets/css/styles.css">';
    if (is_front_page()) echo '<link rel="stylesheet" href="/assets/css/homepage.css">';
}
function wp_footer() {
    echo '<script>window.wowthaiHoursConfig={availability_url:"/api/availability"};window.wowthaiConfig={ajax_url:"/ajax",nonce:"fixture",upsell_items:[]};</script>';
    echo '<script src="/assets/js/wowthai.js"></script><script src="/assets/js/hours-public.js"></script>';
}
function get_header() { require ABSPATH . 'header.php'; }
function get_footer() { require ABSPATH . 'footer.php'; }
require ABSPATH . 'inc/hours.php';
require ABSPATH . 'inc/restaurant-info.php';
require ABSPATH . 'inc/ui.php';

function fixture_dishes() {
    $dishes = [];
    foreach ([
        [56, 'H5. Pad Thai', 'Gebratene Reisbandnudeln oder Glasnudeln mit Ei, Gemüse und Erdnüssen', 'ab €13,50', 'Hauptspeisen', 'restaurant'],
        [43, '★ S3. Gaeng Phet Ped Yang', 'Rotes Kokosmilch-Curry mit gerösteter Ente, Ananas und Thai-Basilikum', '€20,90', 'Spezialität des Hauses', 'restaurant'],
        [119, 'Dragon Rolls 8 Stk', 'Tempura Garnelen, Avocado, Gurken und Chili-Mayo', '€21,90', 'Special Rolls', 'sushi'],
    ] as [$id, $title, $description, $price, $category, $type]) {
        $dishes[] = [
            'id' => $id, 'title' => $title, 'description' => $description,
            'price' => '20.90', 'formatted_price' => $price,
            'has_variants' => false, 'variants' => [], 'modifier_groups' => [],
            'labels' => [], 'spice' => ['level' => 0, 'pill' => '', 'icons' => ''],
            'image_url' => $GLOBALS['fixture_page'] === 'home-images' ? '/assets/img/hero.jpg' : '',
            'category' => $category, 'menu_type' => $type, 'menu_url' => '/menu/#gericht-' . $id,
        ];
    }
    return $dishes;
}
function wowthai_get_homepage_dishes() {
    return $GLOBALS['fixture_page'] === 'home-empty' ? [] : fixture_dishes();
}
function wowthai_get_menu_structure() {
    $dishes = fixture_dishes();
    return [
        'restaurant' => [['name' => 'Hauptspeisen', 'slug' => 'hauptspeisen', 'products' => [$dishes[0], $dishes[1]]]],
        'sushi' => [['name' => 'Special Rolls', 'slug' => 'special-rolls', 'products' => [$dishes[2]]]],
    ];
}
require ABSPATH . (is_front_page() ? 'front-page.php' : 'page-menu.php');
