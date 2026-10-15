<?php
/**
 * Pickup availability and server-side checkout validation for Steyr.
 */

if (!defined('ABSPATH')) {
    exit;
}

function wowthai_pickup_timezone() {
    return new DateTimeZone('Europe/Vienna');
}

function wowthai_pickup_now() {
    return new DateTimeImmutable('now', wowthai_pickup_timezone());
}

function wowthai_pickup_windows($date) {
    // Sunday is open continuously; on other days the kitchen pauses in the afternoon.
    if ($date->format('N') === '7') {
        return [['11:30', '19:00']];
    }

    return [['11:30', '14:00'], ['17:30', '21:00']];
}

function wowthai_pickup_slots_for_date($date, $now = null) {
    $now = $now ?: wowthai_pickup_now();
    $minimum = $now->modify('+30 minutes');
    $slots = [];

    foreach (wowthai_pickup_windows($date) as $window) {
        $slot = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date->format('Y-m-d') . ' ' . $window[0], wowthai_pickup_timezone());
        $last = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date->format('Y-m-d') . ' ' . $window[1], wowthai_pickup_timezone());

        while ($slot <= $last) {
            if ($slot >= $minimum) {
                $slots[] = $slot->format('H:i');
            }
            $slot = $slot->modify('+15 minutes');
        }
    }

    return $slots;
}

function wowthai_pickup_dates($now = null) {
    $now = $now ?: wowthai_pickup_now();
    $dates = [];

    for ($days = 0; $days <= 1; $days++) {
        $date = $now->setTime(0, 0)->modify('+' . $days . ' day');
        $slots = wowthai_pickup_slots_for_date($date, $now);
        if ($slots) {
            $dates[] = [
                'value' => $date->format('Y-m-d'),
                'label' => ($days === 0 ? 'Heute' : 'Morgen') . ' (' . wp_date('l, j. F', $date->getTimestamp(), wowthai_pickup_timezone()) . ')',
                'slots' => $slots,
            ];
        }
    }

    return $dates;
}

function wowthai_pickup_asap_time($now = null) {
    $now = $now ?: wowthai_pickup_now();
    $ready = $now->modify('+30 minutes');

    foreach (wowthai_pickup_windows($ready) as $window) {
        $time = $ready->format('H:i');
        if ($time >= $window[0] && $time <= $window[1]) {
            return $ready;
        }
    }

    return null;
}

function wowthai_validate_pickup_request($request, $now = null) {
    $now = $now ?: wowthai_pickup_now();
    $mode = isset($request['pickup_mode']) && is_string($request['pickup_mode'])
        ? sanitize_key(wp_unslash($request['pickup_mode'])) : '';

    if ($mode === 'asap') {
        $ready = wowthai_pickup_asap_time($now);
        if ($ready) {
            return 'Schnellstmöglich (ca. ' . $ready->format('H:i') . ' Uhr)';
        }
        return new WP_Error('pickup_closed', 'Schnellstmögliche Abholung ist zurzeit nicht verfügbar. Bitte wählen Sie eine Abholzeit.');
    }

    if ($mode !== 'later') {
        return new WP_Error('pickup_missing', 'Bitte wählen Sie eine Abholzeit.');
    }

    $date_text = isset($request['pickup_date']) && is_string($request['pickup_date'])
        ? sanitize_text_field(wp_unslash($request['pickup_date'])) : '';
    $time_text = isset($request['pickup_time']) && is_string($request['pickup_time'])
        ? sanitize_text_field(wp_unslash($request['pickup_time'])) : '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_text) || !preg_match('/^\d{2}:\d{2}$/', $time_text)) {
        return new WP_Error('pickup_invalid', 'Bitte wählen Sie einen gültigen Abholtag und eine Uhrzeit.');
    }

    foreach (wowthai_pickup_dates($now) as $date) {
        if ($date['value'] === $date_text && in_array($time_text, $date['slots'], true)) {
            $when = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date_text . ' ' . $time_text, wowthai_pickup_timezone());
            return wp_date('l, j. F', $when->getTimestamp(), wowthai_pickup_timezone()) . ' um ' . $time_text . ' Uhr';
        }
    }

    return new WP_Error('pickup_unavailable', 'Diese Abholzeit ist nicht mehr verfügbar. Bitte wählen Sie eine spätere Uhrzeit.');
}
