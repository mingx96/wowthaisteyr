<?php
/**
 * Staff-only portal read model. No writes, messages, or payment transitions.
 * Keep the installed Operations plugin responsible for DTO versions and actions.
 * A theme-scoped adapter avoids replacing an unverified live plugin snapshot.
 */
if (!defined('ABSPATH')) exit;

function wowthai_portal_can_read(): bool {
    return current_user_can('manage_woocommerce') || current_user_can('manage_options') || current_user_can('edit_shop_orders');
}

function wowthai_portal_day(string $date) {
    $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Europe/Vienna'));
    if (!$day || $day->format('Y-m-d') !== $date) return new WP_Error('portal_date', 'Bitte ein gültiges Datum wählen.', ['status' => 400]);
    return ['date' => $date, 'start' => $day->getTimestamp(), 'end' => $day->modify('+1 day')->getTimestamp() - 1];
}

function wowthai_portal_today(): string {
    return (new DateTimeImmutable('now', new DateTimeZone('Europe/Vienna')))->format('Y-m-d');
}

/** WooCommerce's CRUD query works with legacy storage and HPOS. Never cap totals. */
function wowthai_portal_query_orders(array $args): array {
    if (!function_exists('wc_get_orders')) throw new RuntimeException('WooCommerce ist nicht verfügbar.');
    $all = [];
    $args += ['type' => 'shop_order', 'orderby' => 'ID', 'order' => 'ASC'];
    $args['limit'] = 100;
    $args['paginate'] = false;
    for ($page = 1; ; $page++) {
        $args['page'] = $page;
        $batch = wc_get_orders($args);
        $all = array_merge($all, $batch);
        if (count($batch) < $args['limit']) break;
    }
    return $all;
}

function wowthai_portal_require_operations(): void {
    foreach (['order_data', 'reservation_data', 'stage'] as $method) {
        if (!is_callable(['WTO_Operations', $method])) throw new RuntimeException('Operations-Schnittstelle nicht verfügbar. Bitte Administration informieren.');
    }
}

function wowthai_portal_paginate(array $items, $page): array {
    $pages = max(1, (int)ceil(count($items) / 50));
    $page = min($pages, max(1, absint($page)));
    return ['items' => array_slice($items, ($page - 1) * 50, 50), 'page' => $page, 'pages' => $pages, 'records' => count($items)];
}

function wowthai_portal_order_row($order): array {
    $row = WTO_Operations::order_data($order);
    // These fields come directly from WooCommerce, independently of plugin version.
    $row['status'] = $order->get_status();
    $row['created'] = $order->get_date_created() ? $order->get_date_created()->format(DATE_ATOM) : '';
    $row['created_ts'] = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0;
    $row['currency'] = $order->get_currency();
    $row['gross_minor'] = (int)round((float)$order->get_total() * 100);
    $row['refund_minor'] = (int)round((float)$order->get_total_refunded() * 100);
    $row['included'] = !in_array($row['status'], ['cancelled', 'failed'], true);
    $row['net_minor'] = $row['included'] ? $row['gross_minor'] - $row['refund_minor'] : 0;
    return $row;
}

function wowthai_portal_orders($request): array {
    wowthai_portal_require_operations();
    $archive = ($request['view'] ?? '') === 'archive';
    $orders = wowthai_portal_query_orders(['status' => $archive ? ['completed', 'cancelled', 'failed', 'refunded'] : ['pending', 'on-hold', 'processing']]);
    usort($orders, function ($a, $b) use ($archive) {
        $at = $a->get_date_created() ? $a->get_date_created()->getTimestamp() : 0;
        $bt = $b->get_date_created() ? $b->get_date_created()->getTimestamp() : 0;
        $cmp = ($at <=> $bt) ?: ($a->get_id() <=> $b->get_id());
        return $archive ? -$cmp : $cmp;
    });
    $counts = ['new' => 0, 'preparing' => 0, 'ready' => 0, 'dispatched' => 0];
    foreach ($orders as $o) {
        $stage = WTO_Operations::stage($o);
        if (isset($counts[$stage])) $counts[$stage]++;
    }
    $result = wowthai_portal_paginate($orders, $request['page'] ?? 1);
    $result['items'] = array_map('wowthai_portal_order_row', $result['items']);
    return $result + ['total' => count($orders), 'counts' => $counts];
}

/** Period is an operational grouping, not an alternative opening schedule. */
function wowthai_portal_res_period(string $time): string {
    if (!preg_match('/^\d{2}:\d{2}$/', $time)) return 'unknown';
    return $time < '16:00' ? 'lunch' : 'dinner';
}

function wowthai_portal_res_rows(): array {
    $query = new WP_Query(['post_type' => 'tischanfrage', 'post_status' => 'publish', 'posts_per_page' => -1, 'no_found_rows' => true]);
    $rows = [];
    foreach ($query->posts as $post) {
        $row = ['id' => (int)$post->ID];
        foreach (['date', 'time', 'status', 'party'] as $key) $row[$key] = get_post_meta($post->ID, '_res_' . $key, true);
        $row['status'] = $row['status'] ?: 'pending';
        $row['party'] = max(0, (int)$row['party']);
        $row['period'] = wowthai_portal_res_period((string)$row['time']);
        $rows[] = $row;
    }
    usort($rows, fn($a, $b) => strcmp($a['date'] . ' ' . $a['time'], $b['date'] . ' ' . $b['time']) ?: ($a['id'] <=> $b['id']));
    return $rows;
}

function wowthai_portal_res_totals(array $rows): array {
    $counts = [];
    $total = 0; $guests = 0;
    foreach ($rows as $row) {
        $status = $row['status'];
        if (!isset($counts[$status])) $counts[$status] = ['count' => 0, 'guests' => 0];
        $counts[$status]['count']++;
        $counts[$status]['guests'] += $row['party'];
        if ($status !== 'cancelled') { $total++; $guests += $row['party']; }
    }
    return ['total' => $total, 'guests' => $guests, 'counts' => $counts];
}

function wowthai_portal_reservations($request) {
    wowthai_portal_require_operations();
    $view = $request['view'] ?? 'daily';
    $shift = $request['shift'] ?? 'all';
    if (!in_array($view, ['daily', 'pending', 'all'], true) || !in_array($shift, ['all', 'lunch', 'dinner'], true)) {
        return new WP_Error('portal_filter', 'Ungültiger Filter.', ['status' => 400]);
    }
    $day = wowthai_portal_day((string)($request['date'] ?? wowthai_portal_today()));
    if ($day instanceof WP_Error) return $day;
    $rows = array_values(array_filter(wowthai_portal_res_rows(), function ($r) use ($view, $shift, $day) {
        return ($view !== 'daily' || $r['date'] === $day['date']) && ($view !== 'pending' || $r['status'] === 'pending') && ($shift === 'all' || $r['period'] === $shift);
    }));
    $result = wowthai_portal_paginate($rows, $request['page'] ?? 1);
    $result['items'] = array_map(function ($row) {
        return array_merge(WTO_Operations::reservation_data($row['id']), ['status' => $row['status'], 'period' => $row['period']]);
    }, $result['items']);
    return $result + wowthai_portal_res_totals($rows) + ['view' => $view, 'date' => $view === 'daily' ? $day['date'] : null];
}

/** Created-day order overview. Refunds are cumulative for these orders, not cash movements today. */
function wowthai_portal_daily($request) {
    wowthai_portal_require_operations();
    $day = wowthai_portal_day((string)($request['date'] ?? wowthai_portal_today()));
    if ($day instanceof WP_Error) return $day;
    $orders = wowthai_portal_query_orders(['status' => ['pending', 'on-hold', 'processing', 'completed', 'cancelled', 'failed', 'refunded'], 'date_created' => $day['start'] . '...' . $day['end']]);
    $totals = ['total' => count($orders), 'gross_minor' => 0, 'refund_minor' => 0, 'net_minor' => 0, 'excluded' => 0];
    $currencies = [];
    foreach ($orders as $o) {
        $currency = $o->get_currency();
        if (!isset($currencies[$currency])) $currencies[$currency] = ['gross_minor' => 0, 'refund_minor' => 0, 'net_minor' => 0];
        if (in_array($o->get_status(), ['cancelled', 'failed'], true)) { $totals['excluded']++; continue; }
        $currencies[$currency]['gross_minor'] += (int)round((float)$o->get_total() * 100);
        $currencies[$currency]['refund_minor'] += (int)round((float)$o->get_total_refunded() * 100);
        $currencies[$currency]['net_minor'] = $currencies[$currency]['gross_minor'] - $currencies[$currency]['refund_minor'];
    }
    // Never sum unlike currencies. Single-currency totals are convenient for the UI.
    if (count($currencies) === 1) $totals = array_merge($totals, reset($currencies));
    $result = wowthai_portal_paginate($orders, $request['page'] ?? 1);
    $result['items'] = array_map('wowthai_portal_order_row', $result['items']);
    $reservations = array_values(array_filter(wowthai_portal_res_rows(), fn($r) => $r['date'] === $day['date']));
    return $result + ['date' => $day['date'], 'orders' => $totals, 'currencies' => $currencies, 'reservations' => wowthai_portal_res_totals($reservations)];
}

function wowthai_portal_summary($request): array {
    wowthai_portal_require_operations();
    $today = wowthai_portal_today();
    $active = wowthai_portal_query_orders(['status' => ['pending', 'on-hold', 'processing']]);
    $new = 0; $backlog = 0; $new_ids = [];
    foreach ($active as $o) {
        if (WTO_Operations::stage($o) === 'new') { $new++; $new_ids[] = $o->get_id(); }
        if ($o->get_date_created() && $o->get_date_created()->setTimezone(new DateTimeZone('Europe/Vienna'))->format('Y-m-d') < $today) $backlog++;
    }
    $pending = count(array_filter(wowthai_portal_res_rows(), fn($r) => $r['status'] === 'pending'));
    return ['date' => $today, 'is_open' => function_exists('wowthai_is_open') ? wowthai_is_open() : null, 'new_orders' => $new, 'new_order_ids' => $new_ids, 'active_orders' => count($active), 'backlog_orders' => $backlog, 'pending_reservations' => $pending, 'server_time' => (new DateTimeImmutable('now', new DateTimeZone('Europe/Vienna')))->format(DATE_ATOM)];
}

add_action('rest_api_init', function () {
    foreach (['orders', 'reservations', 'daily', 'summary'] as $resource) {
        register_rest_route('wowthai-portal/v1', '/' . $resource, [
            'methods' => 'GET', 'permission_callback' => 'wowthai_portal_can_read',
            'callback' => function ($request) use ($resource) {
                try {
                    $data = call_user_func('wowthai_portal_' . $resource, $request);
                    if ($data instanceof WP_Error) return $data;
                    return new WP_REST_Response($data, 200, ['Cache-Control' => 'private, no-store, max-age=0']);
                } catch (Throwable $e) {
                    return new WP_Error('portal_unavailable', 'Betriebsdaten derzeit nicht verfügbar. Bitte erneut laden oder Administration informieren.', ['status' => 503]);
                }
            },
        ]);
    }
});
