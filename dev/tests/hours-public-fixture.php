<?php
/** Actual public/checkout templates, synthetic WordPress boundaries; CLI only.
 * Checkout drives the hours-changed event explicitly to control overlapping responses. */
if (PHP_SAPI !== 'cli') exit;
define('ABSPATH', dirname(__DIR__,2).'/'); $_SERVER['REQUEST_METHOD']='GET';
function add_action(...$a){} function add_filter(...$a){} function apply_filters($t,$v){return $v;}
function get_option($k,$d=false){return $d;} function wp_json_encode($v,...$a){return json_encode($v,...$a);}
function wp_date($f){return '2026';} function is_wp_error($v){return false;}
function home_url($p=''){return $p;} function admin_url($p=''){return '/admin/'.$p;}
function get_template_directory_uri(){return '';}
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');} function esc_attr($v){return esc_html($v);} function esc_url($v){return esc_html($v);}
function esc_textarea($v){return esc_html($v);}
function wowthai_cart_has_sushi(){return false;} function wowthai_get_kitchen_prep_time(){return '25–35 Min.';}
function wc_get_checkout_url(){return '/checkout';} function disabled($value){if($value)echo ' disabled';}
function do_action($tag,...$a){if($tag==='woocommerce_checkout_order_review')echo '<button id=place_order type=submit>Bestellen</button>';}
function wp_nonce_field(...$a){} function selected($v){if($v)echo ' selected';}
function get_header(){echo '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/css/styles.css"><link rel="stylesheet" href="/assets/css/checkout.css"><script type="application/ld+json">'.json_encode(['@type'=>'Restaurant','name'=>'Synthetic fixture','openingHoursSpecification'=>wowthai_schema_opening_hours()]).'</script></head><body><div class="menu-status-banner status-open"><div class="status-content"><strong>Cached open</strong><span>Cached status</span></div></div>';}
function get_footer(){require ABSPATH.'footer.php';}
function wp_footer(){echo '<script>window.wowthaiHoursConfig={availability_url:"/api/availability"};window.wowthaiConfig={ajax_url:"/ajax",nonce:"fixture",upsell_items:[]};</script><script src="/assets/js/wowthai.js"></script>';if (($GLOBALS['argv'][1]??'')!=='checkout') echo '<script src="/assets/js/hours-public.js"></script>';}
require ABSPATH.'inc/hours.php';
if (($argv[1]??'')==='home') require ABSPATH.'front-page.php';
elseif (($argv[1]??'')==='checkout') {
    require ABSPATH.'inc/pickup.php';
    $checkout=new class {function is_registration_enabled(){return true;}function is_registration_required(){return false;}function get_value($key){return '';}};
    get_header();require ABSPATH.'woocommerce/checkout/form-checkout.php';get_footer();
} else require ABSPATH.'page-reservations.php';
