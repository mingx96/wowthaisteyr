<?php
/** Authoritative pickup dates and validation. All timestamps use restaurant time. */
if (!defined('ABSPATH')) exit;

function wowthai_pickup_timezone() { return new DateTimeZone('Europe/Vienna'); }
function wowthai_pickup_now() { return new DateTimeImmutable('now', wowthai_pickup_timezone()); }

function wowthai_pickup_prep_minutes() {
    // Use the upper estimate so a busy kitchen cannot accept a too-early pickup.
    return ['15–20 Min.' => 20, '25–35 Min.' => 35, '40–50 Min.' => 50, '60+ Min.' => 60][wowthai_get_kitchen_prep_time()] ?? 35;
}

function wowthai_pickup_windows($date) {
    return $date->format('N') === '7' ? [['11:30', '19:00']] : [['11:30', '14:30'], ['17:00', '21:00']];
}

function wowthai_pickup_slots_for_date($date, $now = null) {
    $now = $now ?: wowthai_pickup_now();
    $minimum = $now->modify('+' . wowthai_pickup_prep_minutes() . ' minutes');
    $slots = [];
    foreach (wowthai_pickup_windows($date) as [$start, $end]) {
        $slot = new DateTimeImmutable($date->format('Y-m-d') . ' ' . $start, wowthai_pickup_timezone());
        // Preserve the handed-over preorder cutoffs: 14:15, 20:30, Sunday 18:30.
        $last = new DateTimeImmutable($date->format('Y-m-d') . ' ' . $end, wowthai_pickup_timezone());
        $last = $last->modify($end === '14:30' ? '-15 minutes' : '-30 minutes');
        while ($slot <= $last) {
            if ($slot >= $minimum) $slots[] = $slot->format('H:i');
            $slot = $slot->modify('+15 minutes');
        }
    }
    return $slots;
}

function wowthai_pickup_dates($now = null, $has_sushi = false) {
    $now = $now ?: wowthai_pickup_now();
    $dates = [];
    for ($days = 0; $days <= 1; $days++) {
        $date = $now->setTime(0, 0)->modify('+' . $days . ' day');
        if ($has_sushi && $date->format('N') === '1') continue;
        $slots = wowthai_pickup_slots_for_date($date, $now);
        if ($slots) $dates[] = [
            'value' => $date->format('Y-m-d'),
            'label' => ($days === 0 ? 'Heute' : 'Morgen') . ' (' . wp_date('l, j. F', $date->getTimestamp(), wowthai_pickup_timezone()) . ')',
            'slots' => $slots,
        ];
    }
    return $dates;
}

function wowthai_pickup_asap_time($now = null) {
    $now = $now ?: wowthai_pickup_now();
    $ready = $now->modify('+' . wowthai_pickup_prep_minutes() . ' minutes');
    foreach (wowthai_pickup_windows($now) as [$start, $end]) {
        $open = new DateTimeImmutable($now->format('Y-m-d') . ' ' . $start, wowthai_pickup_timezone());
        $close = new DateTimeImmutable($now->format('Y-m-d') . ' ' . $end, wowthai_pickup_timezone());
        if ($now >= $open && $now < $close && $ready <= $close) return $ready;
    }
    return null;
}

function wowthai_validate_pickup_request($request, $now = null, $has_sushi = false) {
    $now = $now ?: wowthai_pickup_now();
    $mode = isset($request['pickup_mode']) && is_string($request['pickup_mode']) ? $request['pickup_mode'] : '';
    if ($mode === 'asap') {
        if ($has_sushi && $now->format('N') === '1') return new WP_Error('sushi_closed', 'Montags ist Sushi-Ruhetag. Bitte wählen Sie einen anderen Abholtag.');
        $ready = wowthai_pickup_asap_time($now);
        return $ready ? 'Schnellstmöglich (ca. ' . $ready->format('H:i') . ' Uhr)' : new WP_Error('pickup_closed', 'Schnellstmögliche Abholung ist derzeit nicht verfügbar. Bitte wählen Sie eine Abholzeit.');
    }
    if ($mode !== 'later') return new WP_Error('pickup_missing', 'Bitte wählen Sie eine Abholzeit.');
    $date = isset($request['pickup_date']) && is_string($request['pickup_date']) ? $request['pickup_date'] : '';
    $time = isset($request['pickup_time']) && is_string($request['pickup_time']) ? $request['pickup_time'] : '';
    foreach (wowthai_pickup_dates($now, $has_sushi) as $available) {
        if ($date === $available['value'] && in_array($time, $available['slots'], true)) {
            $when = new DateTimeImmutable($date . ' ' . $time, wowthai_pickup_timezone());
            return wp_date('l, j. F Y', $when->getTimestamp(), wowthai_pickup_timezone()) . ' um ' . $time . ' Uhr';
        }
    }
    return new WP_Error('pickup_unavailable', 'Diese Abholzeit ist nicht verfügbar. Bitte wählen Sie eine neue Uhrzeit. Montags ist Sushi-Ruhetag.');
}

function wowthai_product_is_sushi($product_id) {
    $terms = get_the_terms($product_id, 'product_cat');
    if (!$terms || is_wp_error($terms)) return false;
    foreach ($terms as $term) {
        $names = [$term->name];
        foreach (get_ancestors($term->term_id, 'product_cat', 'taxonomy') as $parent) {
            $ancestor = get_term($parent, 'product_cat');
            if ($ancestor && !is_wp_error($ancestor)) $names[] = $ancestor->name;
        }
        foreach ($names as $name) {
            $name = html_entity_decode($name, ENT_QUOTES, 'UTF-8');
            if (stripos($name, 'sushi') !== false || in_array($name, ['Set', 'Maki & Sashimi', 'Special Rolls', 'Bowl'], true)) return true;
        }
    }
    return false;
}

// Read-only refresh for open carts/checkouts when kitchen staff changes preparation time.
function wowthai_ajax_pickup_availability() {
    $now = wowthai_pickup_now();
    $sushi = wowthai_cart_has_sushi();
    $ready = $sushi && $now->format('N') === '1' ? null : wowthai_pickup_asap_time($now);
    wp_send_json_success([
        'prep_time' => wowthai_get_kitchen_prep_time(),
        'asap' => $ready ? $ready->format('H:i') : null,
        'dates' => wowthai_pickup_dates($now, $sushi),
    ]);
}
add_action('wp_ajax_wowthai_pickup_availability', 'wowthai_ajax_pickup_availability');
add_action('wp_ajax_nopriv_wowthai_pickup_availability', 'wowthai_ajax_pickup_availability');
