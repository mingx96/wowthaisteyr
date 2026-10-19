<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function nocache_headers() {}
// Local browser fixture only. No WordPress, credentials, or real restaurant data.
define('ABSPATH', __DIR__);
$_SERVER['REQUEST_METHOD'] = 'GET';
function get_template_directory_uri() { return ''; }
function get_template_directory() { return dirname(__DIR__, 2); }
function is_user_logged_in() { return true; }
function wp_get_current_user() { return (object)['display_name'=>'Test staff','user_login'=>'fixture','roles'=>['administrator']]; }
function current_user_can($cap) { return true; }
function wowthai_get_kitchen_prep_time() { return '15–20 Min.'; }
function wowthai_is_open() { return false; }
function bloginfo($key) { echo 'UTF-8'; }
function esc_url($s) { return htmlspecialchars($s, ENT_QUOTES); }
function esc_url_raw($s) { return $s; }
function esc_attr($s) { return htmlspecialchars($s, ENT_QUOTES); }
function esc_html($s) { return htmlspecialchars($s, ENT_QUOTES); }
function esc_js($s) { return $s; }
function home_url($s) { return $s; }
function admin_url($s) { return '/admin/'.$s; }
function wp_logout_url($s) { return '#logout'; }
function wp_create_nonce($s) { return 'fixture-only'; }
function rest_url($s) { return '/api/'.$s; }
function selected($a,$b) { if ($a===$b) echo 'selected'; }
require dirname(__DIR__, 2).'/page-betrieb.php';
