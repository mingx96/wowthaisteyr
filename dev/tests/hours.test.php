<?php
/**
 * WOW Thai Steyr — Opening Hours, Sushi, Mittagsmenü & DST Unit Tests
 * Run via:
 *   studio wp eval-file wp-content/themes/wowthai/dev/tests/hours.test.php
 * or standalone PHP CLI.
 */
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 2) . '/');
}
if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value) {
        return $value;
    }
}
if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        return $default;
    }
}

$hours_file = dirname(__DIR__, 2) . '/inc/hours.php';
if (!file_exists($hours_file)) {
    fwrite(STDERR, "FAIL: inc/hours.php does not exist yet.\n");
    exit(1);
}
require_once $hours_file;

$tz = new DateTimeZone('Europe/Vienna');
$passed = 0;
$failed = 0;

function assert_eq($label, $actual, $expected) {
    global $passed, $failed;
    if ($actual === $expected) {
        $passed++;
        echo "✅ PASS: {$label}\n";
    } else {
        $failed++;
        echo "❌ FAIL: {$label} (expected " . var_export($expected, true) . ", got " . var_export($actual, true) . ")\n";
    }
}

// 1. Boundary opening hours tests
$cases = [
    // Monday (2026-10-19 is Monday)
    ['2026-10-19 00:16:00', false, false, false, 'Monday 00:16 closed, no sushi, no lunch'],
    ['2026-10-19 11:29:00', false, false, false, 'Monday 11:29 closed before lunch'],
    ['2026-10-19 11:30:00', true,  false, true,  'Monday 11:30 open for lunch, no sushi, lunch active'],
    ['2026-10-19 14:29:00', true,  false, true,  'Monday 14:29 still open for lunch'],
    ['2026-10-19 14:30:00', false, false, false, 'Monday 14:30 closed for afternoon break'],
    ['2026-10-19 16:59:00', false, false, false, 'Monday 16:59 still on break'],
    ['2026-10-19 17:00:00', true,  false, false, 'Monday 17:00 open for dinner, no lunch'],
    ['2026-10-19 20:59:00', true,  false, false, 'Monday 20:59 open for dinner'],
    ['2026-10-19 21:00:00', false, false, false, 'Monday 21:00 closed'],
    // Tuesday (2026-10-20 is Tuesday)
    ['2026-10-20 12:00:00', true,  true,  true,  'Tuesday 12:00 open, sushi available, lunch available'],
    ['2026-10-20 18:00:00', true,  true,  false, 'Tuesday 18:00 open, sushi available, dinner (no lunch)'],
    // Saturday (2026-10-24 is Saturday)
    ['2026-10-24 20:59:00', true,  true,  false, 'Saturday 20:59 open'],
    ['2026-10-24 21:00:00', false, true,  false, 'Saturday 21:00 closed'],
    // Sunday (2026-10-25 is Sunday)
    ['2026-10-25 11:29:00', false, true,  false, 'Sunday 11:29 closed'],
    ['2026-10-25 11:30:00', true,  true,  false, 'Sunday 11:30 open, sushi available, no Mittagsmenü on Sunday'],
    ['2026-10-25 15:30:00', true,  true,  false, 'Sunday 15:30 continuous open (no afternoon break)'],
    ['2026-10-25 18:59:00', true,  true,  false, 'Sunday 18:59 open'],
    ['2026-10-25 19:00:00', false, true,  false, 'Sunday 19:00 closed'],
];

foreach ($cases as [$dtStr, $expOpen, $expSushi, $expLunch, $desc]) {
    $dt = new DateTimeImmutable($dtStr, $tz);
    assert_eq("{$desc} [is_open]", wowthai_is_open($dt), $expOpen);
    assert_eq("{$desc} [sushi]", wowthai_sushi_available($dt), $expSushi);
    assert_eq("{$desc} [mittagsmenu]", wowthai_mittagsmenu_available($dt), $expLunch);
}

// 2. Reservation slots test (Mo-Sa vs Sunday & DST days)
$tue = new DateTimeImmutable('2026-10-20 08:00:00', $tz);
$tueSlots = wowthai_reservation_slots($tue, $tue);
assert_eq('Tuesday first reservation slot is 11:30', $tueSlots[0] ?? '', '11:30');
assert_eq('Tuesday includes 14:00 (last lunch slot = 14:30 - 30m)', in_array('14:00', $tueSlots, true), true);
assert_eq('Tuesday excludes 14:30', in_array('14:30', $tueSlots, true), false);
assert_eq('Tuesday last dinner slot is 20:30 (21:00 - 30m)', end($tueSlots), '20:30');

$sunDstFall = new DateTimeImmutable('2026-10-25 02:30:00', $tz);
$sunSlots = wowthai_reservation_slots($sunDstFall, $sunDstFall);
assert_eq('Sunday (DST Fall-back 2026-10-25) first slot is 11:30', $sunSlots[0] ?? '', '11:30');
assert_eq('Sunday (DST Fall-back 2026-10-25) last slot is 18:30 (19:00 - 30m)', end($sunSlots), '18:30');
assert_eq('Sunday (DST Fall-back 2026-10-25) has 15 unique slots', count($sunSlots) === count(array_unique($sunSlots)) && count($sunSlots) === 15, true);

$sunDstSpring = new DateTimeImmutable('2026-03-29 02:30:00', $tz);
$springSlots = wowthai_reservation_slots($sunDstSpring, new DateTimeImmutable('2026-03-28 10:00:00', $tz));
assert_eq('Sunday (DST Spring-forward 2026-03-29) last slot is 18:30', end($springSlots), '18:30');

echo "==============================================================\n";
echo "Hours Test Summary: {$passed} passed, {$failed} failed.\n";
echo "==============================================================\n";
if ($failed > 0) {
    exit(1);
}
