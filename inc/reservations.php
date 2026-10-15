<?php
/**
 * Private table requests and opening-time validation.
 */

if (!defined('ABSPATH')) {
    exit;
}

function wowthai_register_reservation_type() {
    register_post_type('wowthai_reservation', [
        'labels' => [
            'name' => 'Tischanfragen',
            'singular_name' => 'Tischanfrage',
            'menu_name' => 'Tischanfragen',
        ],
        'public' => false,
        'publicly_queryable' => false,
        'exclude_from_search' => true,
        'show_ui' => true,
        'show_in_rest' => false,
        'supports' => ['title', 'editor'],
        'menu_icon' => 'dashicons-calendar-alt',
    ]);
}
add_action('init', 'wowthai_register_reservation_type');

function wowthai_reservation_times($date) {
    $windows = $date->format('N') === '7'
        ? [['11:30', '19:00']]
        : [['11:30', '14:00'], ['17:00', '21:00']];
    $times = [];

    foreach ($windows as $window) {
        $slot = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date->format('Y-m-d') . ' ' . $window[0], wowthai_pickup_timezone());
        $last = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date->format('Y-m-d') . ' ' . $window[1], wowthai_pickup_timezone());
        while ($slot <= $last) {
            $times[] = $slot->format('H:i');
            $slot = $slot->modify('+30 minutes');
        }
    }

    return $times;
}

function wowthai_validate_reservation_time($date_text, $time_text, $now = null) {
    $now = $now ?: wowthai_pickup_now();
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_text) || !preg_match('/^\d{2}:\d{2}$/', $time_text)) {
        return false;
    }

    $when = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date_text . ' ' . $time_text, wowthai_pickup_timezone());
    if (!$when || $when->format('Y-m-d H:i') !== $date_text . ' ' . $time_text) {
        return false;
    }

    return $when >= $now->modify('+30 minutes')
        && $when <= $now->modify('+90 days')
        && in_array($time_text, wowthai_reservation_times($when), true);
}
