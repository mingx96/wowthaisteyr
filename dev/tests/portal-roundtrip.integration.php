<?php
/**
 * Opt-in: wp eval-file wp-content/themes/wowthai/dev/tests/portal-roundtrip.integration.php
 * Requires the full inspected Operations plugin in the disposable local fixture.
 * Real REST handlers + actual DB writes, with synthetic records and outbound mail/HTTP blocked.
 */
if (!defined('WP_CLI') || !WP_CLI || !defined('WOWTHAI_PORTAL_TEST') || WOWTHAI_PORTAL_TEST !== true || home_url() !== 'http://fixture.invalid') exit('Disposable fixture environment required.');
if (!defined('WP_HTTP_BLOCK_EXTERNAL') || !WP_HTTP_BLOCK_EXTERNAL) WP_CLI::error('Block outbound HTTP first.');
$GLOBALS['roundtrip_checks'] = 0;
function roundtrip_expect($condition, $message) {
    $GLOBALS['roundtrip_checks']++;
    if (!$condition) throw new RuntimeException($message);
}
function roundtrip_api($path, $method = 'GET', $body = []) {
    $request = new WP_REST_Request($method, $path);
    if ($method === 'GET') foreach ($body as $key => $value) $request->set_param($key, $value);
    else { $request->set_header('Content-Type', 'application/json'); $request->set_body(wp_json_encode($body)); }
    return rest_do_request($request);
}
$order_id = 0; $res_id = 0; $failure = null;
$prep_before = get_option('wowthai_kitchen_prep_time', null);
try {
    roundtrip_expect(class_exists('WTO_API') && class_exists('WTO_Contacts'), 'full Operations API and contacts layer loaded');
    wp_set_current_user(1);
    $date = (new DateTimeImmutable('+7 days', new DateTimeZone('Europe/Vienna')))->setTime(0, 0);
    $slots = wowthai_reservation_slots($date);
    roundtrip_expect(count($slots) > 0, 'canonical schedule supplies a valid fixture slot');
    $body = ['name' => 'Synthetic Österreicher', 'phone' => '+43000000000', 'email' => 'roundtrip@example.invalid', 'date' => $date->format('Y-m-d'), 'time' => $slots[0], 'party' => 3, 'notes' => 'Fixture: mild & ohne Zwiebeln'];
    $created = roundtrip_api('/wowthai-operations/v1/reservations', 'POST', $body);
    roundtrip_expect($created->get_status() === 200, 'staff reservation POST accepted');
    $row = $created->get_data(); $res_id = (int)($row['id'] ?? 0);
    roundtrip_expect($res_id > 0 && $row['status'] === 'pending', 'one new pending reservation has an ID');
    foreach (['name', 'phone', 'date', 'time', 'notes'] as $field) roundtrip_expect($row[$field] === $body[$field], 'reservation POST/read preserves ' . $field);
    roundtrip_expect((int)get_post_meta($res_id, '_res_party', true) === 3, 'party persisted in real post metadata');
    $read = roundtrip_api('/wowthai-portal/v1/reservations', 'GET', ['view' => 'daily', 'date' => $body['date']])->get_data();
    $found = array_values(array_filter($read['items'], fn($r) => (int)$r['id'] === $res_id));
    roundtrip_expect(count($found) === 1 && $found[0]['version'] === $row['version'], 'read adapter returns same ID and concurrency token');
    $old_version = $row['version'];
    $confirmed = roundtrip_api('/wowthai-operations/v1/reservations/' . $res_id, 'POST', ['action' => 'confirmed', 'version' => $old_version]);
    roundtrip_expect($confirmed->get_status() === 200 && get_post_meta($res_id, '_res_status', true) === 'confirmed', 'confirm writes database and response');
    $row = $confirmed->get_data();
    $conflict = roundtrip_api('/wowthai-operations/v1/reservations/' . $res_id, 'POST', ['action' => 'cancelled', 'version' => $old_version]);
    roundtrip_expect($conflict->get_status() === 409 && get_post_meta($res_id, '_res_status', true) === 'confirmed', 'stale token rejected without overwriting reservation');
    $arrived = roundtrip_api('/wowthai-operations/v1/reservations/' . $res_id, 'POST', ['action' => 'arrived', 'version' => $row['version']]);
    roundtrip_expect($arrived->get_status() === 200 && get_post_meta($res_id, '_res_status', true) === 'arrived', 'arrival persists and reads back');
    $row = $arrived->get_data();
    $reverse = roundtrip_api('/wowthai-operations/v1/reservations/' . $res_id, 'POST', ['action' => 'confirmed', 'version' => $row['version']]);
    roundtrip_expect($reverse->get_status() === 409, 'Operations has no arrival reversal: UI must not offer it');

    $order = new WC_Order(); $order->set_status('pending'); $order->set_total('23.40'); $order->set_currency('EUR');
    $order->set_payment_method('cod'); $order->set_billing_first_name('Synthetic fixture');
    $item = new WC_Order_Item_Product(); $item->set_name('Fixture Gemüse'); $item->set_quantity(2); $item->add_meta_data('Variante', 'Tofu'); $item->add_meta_data('Nudeln', 'Reisnudeln');
    $order->add_item($item); $order->save(); $order_id = $order->get_id();
    $dto = WTO_Operations::order_data(wc_get_order($order_id));
    roundtrip_expect($dto['stage'] === 'new' && str_contains(implode(' ', $dto['items'][0]['choices']), 'Reisnudeln'), 'order serializer preserves items and choices');
    $old_order_version = $dto['version'];
    $accepted = roundtrip_api('/wowthai-operations/v1/orders/' . $order_id . '/action', 'POST', ['action' => 'accept', 'version' => $dto['version']]);
    roundtrip_expect($accepted->get_status() === 200 && wc_get_order($order_id)->get_status() === 'processing', 'accept writes real WooCommerce order');
    $dto = $accepted->get_data();
    $active = roundtrip_api('/wowthai-portal/v1/orders', 'GET', ['view' => 'active'])->get_data();
    $active_row = array_values(array_filter($active['items'], fn($r) => (int)$r['id'] === $order_id))[0];
    roundtrip_expect(($active_row['payment_display'] ?? '') === 'Barzahlung vor Ort', 'COD display must not infer money received from processing status');
    roundtrip_expect($dto['stage'] === 'preparing' && $dto['version'] !== $old_order_version, 'accepted DTO/token changes consistently');
    $conflict = roundtrip_api('/wowthai-operations/v1/orders/' . $order_id . '/action', 'POST', ['action' => 'ready', 'version' => $old_order_version]);
    roundtrip_expect($conflict->get_status() === 409 && !wc_get_order($order_id)->get_meta('_wowthai_ready_time'), 'stale order token rejected without changing stage');
    $ready = roundtrip_api('/wowthai-operations/v1/orders/' . $order_id . '/action', 'POST', ['action' => 'ready', 'version' => $dto['version']]);
    roundtrip_expect($ready->get_status() === 200 && $ready->get_data()['stage'] === 'ready', 'ready writes and serializes consistently');
    $complete = roundtrip_api('/wowthai-operations/v1/orders/' . $order_id . '/action', 'POST', ['action' => 'complete', 'version' => $ready->get_data()['version']]);
    roundtrip_expect($complete->get_status() === 200 && wc_get_order($order_id)->get_status() === 'completed', 'complete persists completed order');
    $archive = roundtrip_api('/wowthai-portal/v1/orders', 'GET', ['view' => 'archive'])->get_data();
    $found = array_values(array_filter($archive['items'], fn($r) => (int)$r['id'] === $order_id));
    roundtrip_expect(count($found) === 1 && $found[0]['gross_minor'] === 2340, 'archive reads completed order ID/amount exactly');
    $set_prep = roundtrip_api('/wowthai-operations/v1/prep', 'POST', ['prep' => '40–50 Min.']);
    roundtrip_expect($set_prep->get_status() === 200 && wowthai_get_kitchen_prep_time() === '40–50 Min.', 'prep write uses canonical theme option');
    $bad_prep = roundtrip_api('/wowthai-operations/v1/prep', 'POST', ['prep' => 'fixture-invalid']);
    roundtrip_expect($bad_prep->get_status() === 400 && wowthai_get_kitchen_prep_time() === '40–50 Min.', 'invalid prep cannot overwrite stored option');
    wp_set_current_user(0);
    roundtrip_expect(roundtrip_api('/wowthai-operations/v1/prep', 'POST', ['prep' => '15–20 Min.'])->get_status() === 401, 'anonymous write rejected');
} catch (Throwable $e) { $failure = $e; }
finally {
    wp_set_current_user(1);
    if ($order_id) { $order = wc_get_order($order_id); if ($order) $order->delete(true); }
    if ($res_id) wp_delete_post($res_id, true);
    if ($prep_before === null) delete_option('wowthai_kitchen_prep_time'); else update_option('wowthai_kitchen_prep_time', $prep_before);
}
if ($failure) WP_CLI::error($failure->getMessage());
$storage = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? 'HPOS' : 'legacy';
WP_CLI::success($GLOBALS['roundtrip_checks'] . ' REST/database roundtrip assertions (' . $storage . '); production untouched.');
