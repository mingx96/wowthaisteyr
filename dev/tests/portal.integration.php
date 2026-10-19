<?php
/** wp eval-file dev/tests/portal.integration.php -- [legacy|hpos]
 * Disposable local WordPress ONLY. Creates synthetic orders/booking records.
 * Requires WooCommerce + the inspected Operations DTO source, with email blocked.
 */
if (!defined('WP_CLI') || !WP_CLI || !defined('WOWTHAI_PORTAL_TEST') || WOWTHAI_PORTAL_TEST !== true || home_url() !== 'http://fixture.invalid') {
    exit('Disposable fixture environment required.');
}
$GLOBALS['portal_fixture_checks'] = 0;
function portal_expect($ok, $message) {
    $GLOBALS['portal_fixture_checks']++;
    if (!$ok) WP_CLI::error($message);
}
portal_expect(defined('WC_VERSION') && WC_VERSION === '11.2.1', 'expected WooCommerce 11.2.1');
portal_expect(is_callable(['WTO_Operations', 'order_data']), 'Operations serializer available');
// Purge only synthetic rows in this explicitly guarded disposable database.
foreach (wc_get_orders(['type' => 'shop_order', 'limit' => -1, 'status' => array_keys(wc_get_order_statuses())]) as $existing) {
    if ($existing->get_billing_first_name() === 'Synthetic fixture') $existing->delete(true);
}
$hpos = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
WP_CLI::log('Storage: ' . ($hpos ? 'HPOS' : 'legacy') . '; WP ' . get_bloginfo('version') . '; WC ' . WC_VERSION . '; PHP ' . PHP_VERSION);
$GLOBALS['portal_fixture_ids'] = [];
function portal_fixture_order($when, $total, $status = 'completed') {

    $order = new WC_Order();
    $order->set_date_created(new WC_DateTime($when));
    $order->set_billing_first_name('Synthetic fixture');
    $order->set_currency('EUR');
    $order->set_payment_method('cod');
    $order->set_payment_method_title('Fixture cash');
    $order->set_status($status);
    $order->set_total($total);
    $item = new WC_Order_Item_Product();
    $item->set_name('Synthetic fixture meal'); $item->set_quantity(1);
    $item->add_meta_data('Auswahl', 'Fixture tofu');
    $order->add_item($item);
    $order->save();
    $GLOBALS['portal_fixture_ids'][] = $order->get_id();
    return $order;
}
// Pick a unique date per storage run; no production source/DB or external calls.
$day = $hpos ? '2026-10-25' : '2026-03-29';
for ($i = 0; $i < 205; $i++) portal_fixture_order($day . 'T12:00:00' . ($hpos ? '+01:00' : '+02:00'), 10);
$processing = portal_fixture_order($day . 'T14:00:00' . ($hpos ? '+01:00' : '+02:00'), 20, 'processing');
$refund = wc_create_refund(['order_id' => $processing->get_id(), 'amount' => 5, 'refund_payment' => false, 'restock_items' => false]);
portal_expect(!is_wp_error($refund), 'partial refund fixture created');
portal_fixture_order($day . 'T15:00:00' . ($hpos ? '+01:00' : '+02:00'), 999, 'cancelled');
portal_fixture_order($day . 'T16:00:00' . ($hpos ? '+01:00' : '+02:00'), 999, 'failed');
$range = wowthai_portal_day($day);
portal_fixture_order((new DateTimeImmutable('@' . ($range['start'] - 1)))->format(DATE_ATOM), 777);
portal_fixture_order((new DateTimeImmutable('@' . ($range['end'] + 1)))->format(DATE_ATOM), 777);
$request = new WP_REST_Request('GET', '/wowthai-portal/v1/daily');
$request->set_param('date', $day); $request->set_param('page', 5);
wp_set_current_user(0);
$response = rest_do_request($request);
portal_expect($response->get_status() === 401, 'anonymous REST request rejected');
$login = 'fixture-customer-' . ($hpos ? 'hpos' : 'legacy');
$customer = username_exists($login) ?: wp_create_user($login, wp_generate_password(), 'fixture-' . ($hpos ? 'hpos' : 'legacy') . '@example.invalid');
wp_set_current_user($customer);
$response = rest_do_request($request);
portal_expect($response->get_status() === 403, 'customer REST request rejected');
wp_set_current_user(1);
$response = rest_do_request($request);
portal_expect($response->get_status() === 200, 'staff REST request succeeds');
portal_expect(str_contains($response->get_headers()['Cache-Control'] ?? '', 'no-store'), 'staff data response is no-store');
$data = $response->get_data();
portal_expect($data['orders']['total'] === 208, 'all daily records counted beyond old limit, adjacent days excluded');
portal_expect($data['orders']['gross_minor'] === 207000 && $data['orders']['refund_minor'] === 500 && $data['orders']['net_minor'] === 206500, 'totals reconcile with actual WooCommerce refund API');
portal_expect($data['pages'] === 5 && count($data['items']) === 8, 'real WooCommerce pagination');
$total = 0;
for ($page = 1; $page <= 5; $page++) {
    $request->set_param('page', $page);
    foreach (rest_do_request($request)->get_data()['items'] as $row) $total += $row['net_minor'];
}
portal_expect($total === $data['orders']['net_minor'], 'sum of every drilldown row exactly matches KPI');
$invalid = new WP_REST_Request('GET', '/wowthai-portal/v1/daily'); $invalid->set_param('date', '2026-02-30');
portal_expect(rest_do_request($invalid)->get_status() === 400, 'invalid date REST error');
$res_ids = [];
foreach ([['pending', '12:00', 2], ['confirmed', '18:00', 3], ['cancelled', '18:00', 8]] as [$status, $time, $party]) {
    $id = wp_insert_post(['post_type' => 'tischanfrage', 'post_status' => 'publish', 'post_title' => 'Synthetic fixture']);
    foreach (['status' => $status, 'date' => $day, 'time' => $time, 'party' => $party] as $key => $value) update_post_meta($id, '_res_' . $key, $value);
    $res_ids[] = $id;
}
$req = new WP_REST_Request('GET', '/wowthai-portal/v1/reservations');
$req->set_param('date', $day); $req->set_param('shift', 'dinner');
$res = rest_do_request($req)->get_data();
portal_expect(count($res['items']) === 2 && $res['total'] === 1 && $res['guests'] === 3, 'actual WP metadata filters/totals include cancelled detail only');
portal_expect(isset($res['items'][0]['version']), 'Operations reservation version preserved');
foreach ($res_ids as $id) wp_delete_post($id, true);
foreach ($GLOBALS['portal_fixture_ids'] as $id) { $o = wc_get_order($id); if ($o) $o->delete(true); }
$checks = $GLOBALS['portal_fixture_checks'];
WP_CLI::success("$checks portal integration assertions passed ($day / " . ($hpos ? 'HPOS' : 'legacy') . ').');
