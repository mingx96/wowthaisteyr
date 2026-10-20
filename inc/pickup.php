<?php
/**
 * Authoritative pickup dates and validation.
 * Delegates all opening-hour schedules, Sushi rules, and Mittagsmenü windows to inc/hours.php.
 */
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/hours.php';

function wowthai_pickup_timezone() {
    return wowthai_timezone();
}

function wowthai_pickup_now() {
    return wowthai_now();
}

function wowthai_pickup_prep_minutes() {
    // Use the upper estimate so a busy kitchen cannot accept a too-early pickup.
    $prep = function_exists('wowthai_get_kitchen_prep_time') ? wowthai_get_kitchen_prep_time() : '25–35 Min.';
    return ['15–20 Min.' => 20, '25–35 Min.' => 35, '40–50 Min.' => 50, '60+ Min.' => 60][$prep] ?? 35;
}

function wowthai_pickup_windows($date) {
    return wowthai_hours_ranges_for_date($date);
}

function wowthai_product_is_mittagsmenu_m4($product_id, $title = '') {
    if ($title === '' && $product_id) {
        $title = (string) get_post_field('post_title', $product_id);
    }
    $decoded = html_entity_decode((string) $title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return (bool) (
        preg_match('/(?:★|\b)\s*M4\b/u', $decoded) ||
        stripos($decoded, 'M4 ') !== false ||
        stripos($decoded, 'M4 –') !== false ||
        stripos($decoded, 'M4 -') !== false
    );
}

function wowthai_product_is_sushi($product_id) {
    if (wowthai_product_is_mittagsmenu_m4($product_id)) {
        return true;
    }
    $terms = get_the_terms($product_id, 'product_cat');
    if (!$terms || is_wp_error($terms)) {
        return false;
    }
    foreach ($terms as $term) {
        $names = [$term->name];
        foreach (get_ancestors($term->term_id, 'product_cat', 'taxonomy') as $parent) {
            $ancestor = get_term($parent, 'product_cat');
            if ($ancestor && !is_wp_error($ancestor)) {
                $names[] = $ancestor->name;
            }
        }
        foreach ($names as $name) {
            $name = html_entity_decode($name, ENT_QUOTES, 'UTF-8');
            if (stripos($name, 'sushi') !== false || in_array($name, ['Set', 'Maki & Sashimi', 'Special Rolls', 'Bowl'], true)) {
                return true;
            }
        }
    }
    return false;
}

function wowthai_product_is_mittagsmenu($product_id) {
    if (wowthai_product_is_mittagsmenu_m4($product_id)) {
        return true;
    }
    $terms = get_the_terms($product_id, 'product_cat');
    if (!$terms || is_wp_error($terms)) {
        return false;
    }
    foreach ($terms as $term) {
        $name = html_entity_decode($term->name, ENT_QUOTES, 'UTF-8');
        if (stripos($name, 'mittagsmen') !== false || $term->slug === 'mittagsmenue' || $term->slug === 'mittagsmenu') {
            return true;
        }
    }
    return false;
}

function wowthai_cart_has_mittagsmenu() {
    if (!function_exists('WC') || !WC()->cart) {
        return false;
    }
    foreach (WC()->cart->get_cart() as $item) {
        if (wowthai_product_is_mittagsmenu($item['product_id'])) {
            return true;
        }
    }
    return false;
}

function wowthai_cart_has_mittagsmenu_m4() {
    if (!function_exists('WC') || !WC()->cart) {
        return false;
    }
    foreach (WC()->cart->get_cart() as $item) {
        if (wowthai_product_is_mittagsmenu_m4($item['product_id'])) {
            return true;
        }
    }
    return false;
}

function wowthai_pickup_slots_for_date($date, $now = null, $has_mittagsmenu = null, $has_m4 = null, $hours_config = null) {
    $now = $now ?: wowthai_pickup_now();
    if ($has_mittagsmenu === null) {
        $has_mittagsmenu = wowthai_cart_has_mittagsmenu();
    }
    if ($has_m4 === null) {
        $has_m4 = wowthai_cart_has_mittagsmenu_m4();
    }
    $minimum = $now->modify('+' . wowthai_pickup_prep_minutes() . ' minutes');
    $windows = $hours_config === null ? wowthai_pickup_windows($date) : wowthai_hours_ranges_for_config($date, $hours_config['weekly'], $hours_config['exceptions']);
    $slots   = [];

    foreach ($windows as $idx => [$start, $end]) {
        $slot = wowthai_datetime_at_hm($date, $start);
        $last = wowthai_datetime_at_hm($date, $end);
        // Preserve lunch cutoff (-15 min) vs evening/Sunday cutoff (-30 min)
        $is_lunch_window = ($idx === 0 && count($windows) > 1);
        $last = $last->modify($is_lunch_window ? '-15 minutes' : '-30 minutes');

        while ($slot <= $last) {
            $hm = $slot->format('H:i');
            $valid_lunch = (!$has_mittagsmenu || wowthai_is_slot_valid_for_mittagsmenu($date, $hm));
            $valid_m4    = (!$has_m4 || wowthai_is_slot_valid_for_mittagsmenu_m4($date, $hm));
            if ($slot >= $minimum && $valid_lunch && $valid_m4) {
                $slots[] = $hm;
            }
            $slot = $slot->modify('+15 minutes');
        }
    }
    return $slots;
}

function wowthai_pickup_dates($now = null, $has_sushi = false, $has_mittagsmenu = null, $has_m4 = null) {
    $now = $now ?: wowthai_pickup_now();
    if ($has_mittagsmenu === null) {
        $has_mittagsmenu = wowthai_cart_has_mittagsmenu();
    }
    if ($has_m4 === null) {
        $has_m4 = wowthai_cart_has_mittagsmenu_m4();
    }
    $dates = [];
    for ($days = 0; $days <= 6 && count($dates) < 2; $days++) {
        $date = $now->setTime(0, 0)->modify('+' . $days . ' day');
        $dow  = (int) $date->format('N');
        if ($has_sushi && !wowthai_sushi_available($date)) {
            continue;
        }
        if ($has_mittagsmenu && !in_array($dow, WOWTHAI_MITTAGSMENU_DAYS, true)) {
            continue;
        }
        if ($has_m4 && !in_array($dow, WOWTHAI_MITTAGSMENU_M4_DAYS, true)) {
            continue;
        }
        $slots = wowthai_pickup_slots_for_date($date, $now, $has_mittagsmenu, $has_m4);
        if ($slots) {
            if ($days === 0) {
                $prefix = 'Heute';
            } elseif ($days === 1) {
                $prefix = 'Morgen';
            } else {
                $prefix = wp_date('l', $date->getTimestamp(), wowthai_pickup_timezone());
            }
            $dates[] = [
                'value' => $date->format('Y-m-d'),
                'label' => $prefix . ' (' . wp_date('l, j. F', $date->getTimestamp(), wowthai_pickup_timezone()) . ')',
                'slots' => $slots,
            ];
        }
    }
    return $dates;
}

function wowthai_pickup_asap_time($now = null, $has_mittagsmenu = null, $has_m4 = null) {
    $now = $now ?: wowthai_pickup_now();
    if ($has_mittagsmenu === null) {
        $has_mittagsmenu = wowthai_cart_has_mittagsmenu();
    }
    if ($has_m4 === null) {
        $has_m4 = wowthai_cart_has_mittagsmenu_m4();
    }
    if ($has_m4 && !wowthai_mittagsmenu_m4_available($now)) {
        return null;
    }
    if ($has_mittagsmenu && !wowthai_mittagsmenu_available($now)) {
        return null;
    }
    $ready = $now->modify('+' . wowthai_pickup_prep_minutes() . ' minutes');
    foreach (wowthai_pickup_windows($now) as [$start, $end]) {
        $open  = wowthai_datetime_at_hm($now, $start);
        $close = wowthai_datetime_at_hm($now, $end);
        if ($now >= $open && $now < $close && $ready <= $close) {
            if ($has_m4 && !wowthai_is_slot_valid_for_mittagsmenu_m4($now, $ready->format('H:i'))) {
                return null;
            }
            if ($has_mittagsmenu && !wowthai_is_slot_valid_for_mittagsmenu($now, $ready->format('H:i'))) {
                return null;
            }
            return $ready;
        }
    }
    return null;
}

function wowthai_validate_pickup_request($request, $now = null, $has_sushi = false, $has_mittagsmenu = null, $has_m4 = null) {
    $now = $now ?: wowthai_pickup_now();
    if ($has_mittagsmenu === null) {
        $has_mittagsmenu = wowthai_cart_has_mittagsmenu();
    }
    if ($has_m4 === null) {
        $has_m4 = wowthai_cart_has_mittagsmenu_m4();
    }
    $mode = isset($request['pickup_mode']) && is_string($request['pickup_mode']) ? sanitize_text_field(wp_unslash($request['pickup_mode'])) : '';

    if ($mode === 'asap') {
        if ($has_sushi && !wowthai_sushi_available($now)) {
            return new WP_Error('sushi_closed', 'Montags ist Sushi-Ruhetag. Bitte wählen Sie einen anderen Abholtag.');
        }
        if ($has_m4 && !wowthai_mittagsmenu_m4_available($now)) {
            $hd = wowthai_hours_display();
            return new WP_Error('mittagsmenu_m4_closed', 'Das Sushi-Mittagsmenü M4 ist nur ' . $hd['mittags_m4_note'] . ' verfügbar. Bitte wählen Sie eine Mittags-Abholzeit von Dienstag bis Freitag.');
        }
        if ($has_mittagsmenu && !wowthai_mittagsmenu_available($now)) {
            $hd = wowthai_hours_display();
            return new WP_Error('mittagsmenu_closed', 'Das Mittagsmenü ist nur ' . $hd['mittags_note'] . ' verfügbar. Bitte wählen Sie eine Abholzeit am Mittag.');
        }
        $ready = wowthai_pickup_asap_time($now, $has_mittagsmenu, $has_m4);
        return $ready
            ? 'Schnellstmöglich (ca. ' . $ready->format('H:i') . ' Uhr)'
            : new WP_Error('pickup_closed', 'Schnellstmögliche Abholung ist derzeit nicht verfügbar. Bitte wählen Sie eine Abholzeit.');
    }

    if ($mode !== 'later') {
        return new WP_Error('pickup_missing', 'Bitte wählen Sie eine Abholzeit.');
    }

    $date = isset($request['pickup_date']) && is_string($request['pickup_date']) ? sanitize_text_field(wp_unslash($request['pickup_date'])) : '';
    $time = isset($request['pickup_time']) && is_string($request['pickup_time']) ? sanitize_text_field(wp_unslash($request['pickup_time'])) : '';

    foreach (wowthai_pickup_dates($now, $has_sushi, $has_mittagsmenu, $has_m4) as $available) {
        if ($date === $available['value'] && in_array($time, $available['slots'], true)) {
            $when = new DateTimeImmutable($date . ' ' . $time, wowthai_pickup_timezone());
            return wp_date('l, j. F Y', $when->getTimestamp(), wowthai_pickup_timezone()) . ' um ' . $time . ' Uhr';
        }
    }

    if ($has_m4) {
        $hd = wowthai_hours_display();
        return new WP_Error('mittagsmenu_m4_unavailable', 'Das Sushi-Mittagsmenü M4 ist nur ' . $hd['mittags_m4_note'] . ' verfügbar. Bitte wählen Sie eine Mittags-Abholzeit von Dienstag bis Freitag.');
    }
    if ($has_mittagsmenu) {
        $hd = wowthai_hours_display();
        return new WP_Error('mittagsmenu_unavailable', 'Das Mittagsmenü ist nur ' . $hd['mittags_note'] . ' verfügbar. Bitte wählen Sie eine Mittags-Abholzeit.');
    }
    return new WP_Error('pickup_unavailable', 'Diese Abholzeit ist nicht verfügbar. Bitte wählen Sie eine neue Uhrzeit. Montags ist Sushi-Ruhetag.');
}

// Read-only refresh for open carts/checkouts when kitchen staff changes preparation time.
function wowthai_ajax_pickup_availability() {
    $now   = wowthai_pickup_now();
    $sushi = function_exists('wowthai_cart_has_sushi') ? wowthai_cart_has_sushi() : false;
    $lunch = wowthai_cart_has_mittagsmenu();
    $m4    = wowthai_cart_has_mittagsmenu_m4();
    $ready = ($sushi && !wowthai_sushi_available($now)) ? null : wowthai_pickup_asap_time($now, $lunch, $m4);
    wp_send_json_success([
        'prep_time' => function_exists('wowthai_get_kitchen_prep_time') ? wowthai_get_kitchen_prep_time() : '25–35 Min.',
        'asap'      => $ready ? $ready->format('H:i') : null,
        'dates'     => wowthai_pickup_dates($now, $sushi, $lunch, $m4),
    ]);
}
add_action('wp_ajax_wowthai_pickup_availability', 'wowthai_ajax_pickup_availability');
add_action('wp_ajax_nopriv_wowthai_pickup_availability', 'wowthai_ajax_pickup_availability');
