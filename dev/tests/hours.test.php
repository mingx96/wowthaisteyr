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
    // Monday (2026-10-19 is Monday): open 11:00-14:30 & 17:00-21:00, no sushi, regular lunch yes, M4 no
    ['2026-10-19 00:16:00', false, false, false, false, 'Monday 00:16 closed, no sushi, no lunch, no M4'],
    ['2026-10-19 10:59:00', false, false, false, false, 'Monday 10:59 closed before lunch'],
    ['2026-10-19 11:00:00', true,  false, true,  false, 'Monday 11:00 open for lunch, no sushi, lunch active, no M4'],
    ['2026-10-19 14:29:00', true,  false, true,  false, 'Monday 14:29 still open for lunch, no M4'],
    ['2026-10-19 14:30:00', false, false, false, false, 'Monday 14:30 closed for afternoon break'],
    ['2026-10-19 16:59:00', false, false, false, false, 'Monday 16:59 still on break'],
    ['2026-10-19 17:00:00', true,  false, false, false, 'Monday 17:00 open for dinner, no lunch'],
    ['2026-10-19 20:59:00', true,  false, false, false, 'Monday 20:59 open for dinner'],
    ['2026-10-19 21:00:00', false, false, false, false, 'Monday 21:00 closed'],
    // Tuesday (2026-10-20 is Tuesday): M4 active at lunch
    ['2026-10-20 11:00:00', true,  true,  true,  true,  'Tuesday 11:00 open, sushi available, lunch & M4 available'],
    ['2026-10-20 12:00:00', true,  true,  true,  true,  'Tuesday 12:00 open, sushi available, lunch & M4 available'],
    ['2026-10-20 18:00:00', true,  true,  false, false, 'Tuesday 18:00 open, sushi available, dinner (no lunch, no M4)'],
    // Friday (2026-10-23 is Friday): M4 active at lunch
    ['2026-10-23 12:30:00', true,  true,  true,  true,  'Friday 12:30 open, sushi available, lunch & M4 available'],
    // Saturday (2026-10-24 is Saturday): no regular lunch or M4
    ['2026-10-24 12:00:00', true,  true,  false, false, 'Saturday 12:00 open, sushi available, no lunch or M4'],
    ['2026-10-24 20:59:00', true,  true,  false, false, 'Saturday 20:59 open'],
    ['2026-10-24 21:00:00', false, true,  false, false, 'Saturday 21:00 closed'],
    // Sunday (2026-10-25 is Sunday): 11:30-19:00, no lunch, no M4
    ['2026-10-25 11:29:00', false, true,  false, false, 'Sunday 11:29 closed'],
    ['2026-10-25 11:30:00', true,  true,  false, false, 'Sunday 11:30 open, sushi available, no Mittagsmenü on Sunday'],
    ['2026-10-25 15:30:00', true,  true,  false, false, 'Sunday 15:30 continuous open (no afternoon break)'],
    ['2026-10-25 18:59:00', true,  true,  false, false, 'Sunday 18:59 open'],
    ['2026-10-25 19:00:00', false, true,  false, false, 'Sunday 19:00 closed'],
];

foreach ($cases as [$dtStr, $expOpen, $expSushi, $expLunch, $expM4, $desc]) {
    $dt = new DateTimeImmutable($dtStr, $tz);
    assert_eq("{$desc} [is_open]", wowthai_is_open($dt), $expOpen);
    assert_eq("{$desc} [sushi]", wowthai_sushi_available($dt), $expSushi);
    assert_eq("{$desc} [mittagsmenu]", wowthai_mittagsmenu_available($dt), $expLunch);
    assert_eq("{$desc} [mittagsmenu_m4]", wowthai_mittagsmenu_m4_available($dt), $expM4);
}

// 2. Reservation slots test (Mo-Sa vs Sunday & DST days)
$tue = new DateTimeImmutable('2026-10-20 08:00:00', $tz);
$tueSlots = wowthai_reservation_slots($tue, $tue);
assert_eq('Tuesday first reservation slot is 11:00', $tueSlots[0] ?? '', '11:00');
assert_eq('Tuesday includes 14:00 (last lunch slot = 14:30 - 30m)', in_array('14:00', $tueSlots, true), true);
assert_eq('Tuesday excludes 14:30', in_array('14:30', $tueSlots, true), false);
assert_eq('Tuesday last dinner slot is 20:30 (21:00 - 30m)', end($tueSlots), '20:30');

// 3. Mittagsmenü & M4 slot validation tests
$mon = new DateTimeImmutable('2026-10-19 08:00:00', $tz);
$sat = new DateTimeImmutable('2026-10-24 08:00:00', $tz);
assert_eq('Monday 11:30 valid for regular Mittagsmenü', wowthai_is_slot_valid_for_mittagsmenu($mon, '11:30'), true);
assert_eq('Monday 11:30 INVALID for Mittagsmenü M4', wowthai_is_slot_valid_for_mittagsmenu_m4($mon, '11:30'), false);
assert_eq('Tuesday 11:30 valid for Mittagsmenü M4', wowthai_is_slot_valid_for_mittagsmenu_m4($tue, '11:30'), true);
assert_eq('Tuesday 18:00 INVALID for Mittagsmenü M4', wowthai_is_slot_valid_for_mittagsmenu_m4($tue, '18:00'), false);
assert_eq('Saturday 12:00 INVALID for regular Mittagsmenü', wowthai_is_slot_valid_for_mittagsmenu($sat, '12:00'), false);
assert_eq('Sunday 12:00 INVALID for regular Mittagsmenü', wowthai_is_slot_valid_for_mittagsmenu(new DateTimeImmutable('2026-10-25', $tz), '12:00'), false);
assert_eq('Lunch display states weekdays only', strpos(wowthai_hours_display()['mittags_note'], 'Mo – Fr') === 0, true);
assert_eq('Saturday 12:00 INVALID for Mittagsmenü M4', wowthai_is_slot_valid_for_mittagsmenu_m4($sat, '12:00'), false);

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
