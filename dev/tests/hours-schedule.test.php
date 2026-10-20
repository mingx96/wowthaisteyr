<?php
/** Standalone, synthetic schedule regressions. No WordPress/database writes. */
define('ABSPATH', dirname(__DIR__, 2) . '/');
$GLOBALS['test_options'] = [];
$GLOBALS['test_filter'] = null;
function get_option($name, $default = false) { return $GLOBALS['test_options'][$name] ?? $default; }
function apply_filters($tag, $value) { return $tag === 'wowthai_hours_schedule' && $GLOBALS['test_filter'] ? ($GLOBALS['test_filter'])($value) : $value; }
class WP_Error { public function __construct($code, $message) {} }
function add_filter(...$args) {}
function add_action(...$args) {}
function get_header() {}
function get_footer() {}
function wp_footer() {}
function wp_date($format) { return '2026'; }
function home_url($path = '') { return 'https://fixture.invalid' . $path; }
function get_template_directory_uri() { return 'https://fixture.invalid/theme'; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function sanitize_html_class($value) { return preg_replace('/[^A-Za-z0-9_-]/', '', (string) $value); }
function is_wp_error($value) { return $value instanceof WP_Error; }
require ABSPATH . 'inc/hours.php';
require ABSPATH . 'inc/ui.php';
$passed = 0;
$failed = 0;
function check($label, $actual, $expected) {
    global $passed, $failed;
    if ($actual === $expected) { $passed++; echo "PASS: $label\n"; }
    else { $failed++; echo "FAIL: $label expected " . var_export($expected, true) . ' got ' . var_export($actual, true) . "\n"; }
}
function at($value) { return new DateTimeImmutable($value, wowthai_timezone()); }
$weekly = [1 => [], 2 => [['12:00', '13:00']], 3 => [['12:00', '13:00']], 4 => [['18:00', '20:00']], 5 => [['12:00', '13:00']], 6 => [], 7 => [['10:00', '12:00']]];
$GLOBALS['test_options']['wowthai_weekly_hours'] = $weekly;
check('saved Monday closure overrides default', wowthai_hours_ranges_for_date(at('2026-10-19')), []);
check('saved Tuesday custom hours override default', wowthai_hours_ranges_for_date(at('2026-10-20')), [['12:00', '13:00']]);
check('display does not group different Monday and Tuesday hours', count(wowthai_hours_display()['rows']) > 2, true);
$GLOBALS['test_options']['wowthai_special_days'] = ['2026-10-20' => ['closed' => false, 'ranges' => [['18:00', '19:00']], 'reason' => 'Test']];
check('custom exception replaces weekly hours', wowthai_hours_ranges_for_date(at('2026-10-20')), [['18:00', '19:00']]);
check('lunch intersects actual exception hours', wowthai_mittagsmenu_available(at('2026-10-20 12:15')), false);
check('lunch slot intersects actual exception hours', wowthai_is_slot_valid_for_mittagsmenu(at('2026-10-20'), '12:15'), false);
$GLOBALS['test_options']['wowthai_special_days']['2026-10-20'] = ['closed' => true];
check('next opening skips closed next day', strpos(wowthai_compute_opening_status(at('2026-10-19 22:00'))['next_open_label'], '21.10.2026') !== false, true);
if (!function_exists('wowthai_hours_validate_ranges')) {
    check('strict validators are implemented', false, true);
} else {
    check('ranges sort chronologically', wowthai_hours_validate_ranges([['17:00', '20:00'], ['12:00', '14:00']]), [['12:00', '14:00'], ['17:00', '20:00']]);
    foreach ([null, '12:00', [['24:00', '25:00']], [['9:00', '10:00']], [['12:00', '12:00']], [['22:00', '02:00']], [['12:00', '14:00'], ['13:00', '15:00']], [['12:00', '14:00', 'extra']], [1 => ['12:00', '14:00']]] as $invalid) {
        check('invalid ranges rejected ' . json_encode($invalid), is_wp_error(wowthai_hours_validate_ranges($invalid)), true);
    }
    check('adjacent ranges allowed', is_wp_error(wowthai_hours_validate_ranges([['12:00', '14:00'], ['14:00', '15:00']])), false);
    check('closed day accepted', wowthai_hours_validate_ranges([]), []);
    check('weekly needs all seven ISO weekdays', is_wp_error(wowthai_hours_validate_weekly([1 => []])), true);
    check('weekly rejects extra days', is_wp_error(wowthai_hours_validate_weekly($weekly + [8 => []])), true);
    check('weekly rejects malformed day', is_wp_error(wowthai_hours_validate_weekly(array_replace($weekly, [2 => [['12:00', '11:00']]]))), true);
    check('weekly normalization', wowthai_hours_validate_weekly($weekly), $weekly);
    $before = $GLOBALS['test_options'];
    check('preview uses passed config, not saved exceptions', wowthai_hours_ranges_for_config(at('2026-10-20'), $weekly, []), [['12:00', '13:00']]);
    check('pure resolver normalizes timezone before selecting date', wowthai_hours_ranges_for_config(new DateTimeImmutable('2026-10-19 23:00:00', new DateTimeZone('UTC')), $weekly, []), [['12:00', '13:00']]);
    check('closed exception dominates its ranges', wowthai_hours_ranges_for_config(at('2026-10-20'), $weekly, ['2026-10-20' => ['closed' => true, 'ranges' => [['12:00', '13:00']]]]), []);
    check('legacy reason-only exception retains weekly', wowthai_hours_ranges_for_config(at('2026-10-20'), $weekly, ['2026-10-20' => ['closed' => false, 'reason' => 'Test']]), [['12:00', '13:00']]);
    check('bad custom ranges fail closed', wowthai_hours_ranges_for_config(at('2026-10-20'), $weekly, ['2026-10-20' => ['ranges' => [['25:00', '26:00']]]]), []);
    check('preview reservation slots use draft config', wowthai_reservation_slots(at('2026-10-20'), at('2026-10-19'), $weekly, []), ['12:00', '12:30']);
    check('pure preview has no option writes', $GLOBALS['test_options'], $before);
    $rows = wowthai_hours_grouped_rows($weekly);
    check('group equal nonconsecutive days without including Thursday', $rows[1]['weekdays'], [2, 3, 5]);
    check('closed group is explicit', $rows[0]['hours'], 'Geschlossen');
    check('all closed has no fictional opening', wowthai_compute_opening_status(at('2026-10-19'), array_fill(1, 7, []), [])['next_open_label'], '');
    check('preview status honors draft opening', wowthai_compute_opening_status(at('2026-10-20 12:15'), $weekly, [])['is_open'], true);
    check('next opening includes third same-day range', wowthai_hours_next_opening(at('2026-10-20 16:00'), [2 => [['08:00', '09:00'], ['12:00', '13:00'], ['18:00', '20:00']]], [])->format('Y-m-d H:i'), '2026-10-20 18:00');
    check('future exception opens otherwise closed week', wowthai_hours_next_opening(at('2026-10-20'), array_fill(1, 7, []), ['2027-01-03' => ['ranges' => [['10:00', '12:00']]]])->format('Y-m-d H:i'), '2027-01-03 10:00');
    $holiday = [];
    for ($offset = 0; $offset < 20; $offset++) { $holiday[at('2026-10-20')->modify('+' . $offset . ' days')->format('Y-m-d')] = ['closed' => true]; }
    check('next opening skips multiweek closure across DST', wowthai_hours_next_opening(at('2026-10-20'), $weekly, $holiday)->format('Y-m-d H:i'), '2026-11-10 12:00');
    check('same-day draft slots respect lead time', wowthai_reservation_slots(at('2026-10-20'), at('2026-10-20 12:01'), $weekly, []), []);
    check('past dates have no draft reservation slots', wowthai_reservation_slots(at('2026-10-20'), at('2026-10-21'), $weekly, []), []);

}
// Spring-forward wall-clock windows must not silently move into the next hour.
$gap_week = array_fill(1, 7, []);
$gap_week[7] = [['02:00', '02:30']];
$gap_date = at('2027-03-28');
$day_before_gap = at('2027-03-27 12:00');
$GLOBALS['test_options'] = ['wowthai_weekly_hours' => $gap_week];
check('spring gap stays a valid weekly definition', is_wp_error(wowthai_hours_validate_weekly($gap_week)), false);
check('spring gap range omitted in live resolver', wowthai_hours_ranges_for_date($gap_date), []);
check('spring gap range omitted in preview resolver', wowthai_hours_ranges_for_config($gap_date, $gap_week, []), []);
check('spring gap has no live reservation slots', wowthai_reservation_slots($gap_date, $day_before_gap), []);
check('spring gap has no preview reservation slots', wowthai_reservation_slots($gap_date, $day_before_gap, $gap_week, []), []);
check('spring gap never reopens at normalized 03:15', wowthai_is_open(at('2027-03-28 03:15')), false);
check('spring gap preview status never opens at 03:15', wowthai_compute_opening_status(at('2027-03-28 03:15'), $gap_week, [])['is_open'], false);
check('spring gap next opening skips to ordinary Sunday', wowthai_hours_next_opening($day_before_gap, $gap_week, [])?->format('Y-m-d H:i'), '2027-04-04 02:00');
check('ordinary Sunday retains same weekly range', wowthai_hours_ranges_for_config(at('2027-04-04'), $gap_week, []), [['02:00', '02:30']]);
$gap_exception = ['2027-03-28' => ['ranges' => [['02:00', '02:30']]]];
check('spring gap exception follows same boundary policy', wowthai_hours_ranges_for_config($gap_date, $weekly, $gap_exception), []);
$mixed_week = array_replace($gap_week, [7 => [['00:30', '01:30'], ['02:00', '02:30'], ['03:00', '04:00']]]);
check('spring gap preserves other valid windows', wowthai_hours_ranges_for_config($gap_date, $mixed_week, []), [['00:30', '01:30'], ['03:00', '04:00']]);
check('spring gap mixed-window slots only use real times', wowthai_reservation_slots($gap_date, $day_before_gap, $mixed_week, []), ['00:30', '01:00', '03:00', '03:30']);
check('spring gap invalid end omits whole window', wowthai_hours_ranges_for_config($gap_date, array_replace($gap_week, [7 => [['01:00', '02:30']]]), []), []);
check('spring gap invalid start omits whole window', wowthai_hours_ranges_for_config($gap_date, array_replace($gap_week, [7 => [['02:30', '04:00']]]), []), []);
$spanning_week = array_replace($gap_week, [7 => [['01:00', '04:00']]]);
check('valid range spanning spring jump remains open', wowthai_hours_ranges_for_config($gap_date, $spanning_week, []), [['01:00', '04:00']]);
check('spanning spring range never emits missing slots', wowthai_reservation_slots($gap_date, $day_before_gap, $spanning_week, []), ['01:00', '01:30', '03:00', '03:30']);
check('same-day spanning spring slots use real lead time', wowthai_reservation_slots($gap_date, at('2027-03-28 01:20'), $spanning_week, []), ['03:00', '03:30']);
check('spanning spring range preview is open at 03:15', wowthai_compute_opening_status(at('2027-03-28 03:15'), $spanning_week, [])['is_open'], true);
check('autumn repeated times remain bookable once', wowthai_reservation_slots(at('2027-10-31'), at('2027-10-30'), $gap_week, []), ['02:00']);
$GLOBALS['test_options'] = [];
check('default schedule remains unchanged without option', wowthai_hours_ranges_for_date(at('2026-10-19')), [['11:00', '14:30'], ['17:00', '21:00']]);
$GLOBALS['test_options']['wowthai_weekly_hours'] = [1 => []];
check('malformed stored weekly fails closed', wowthai_hours_ranges_for_date(at('2026-10-19')), []);
check('staff config can discover malformed weekly', is_wp_error(wowthai_hours_weekly_config()), true);
$GLOBALS['test_options']['wowthai_weekly_hours'] = array_fill(1, 7, []);
check('valid saved all-closed week stays closed', wowthai_hours_ranges_for_date(at('2026-10-20')), []);
$GLOBALS['test_options']['wowthai_weekly_hours'] = $weekly;
$schema = wowthai_schema_opening_hours();
check('schema omits closed Monday', count(array_filter($schema, static fn($row) => in_array('Monday', $row['dayOfWeek'], true))), 0);
check('schema gives Thursday its actual hours', array_values(array_filter($schema, static fn($row) => in_array('Thursday', $row['dayOfWeek'], true)))[0]['opens'] ?? null, '18:00');
require ABSPATH . 'inc/restaurant-info.php';
check('restaurant text includes Thursday custom hours', wowthai_get_restaurant_data()['opening_hours_text']['Do'] ?? null, '18:00–20:00 Uhr');
foreach (['front-page.php', 'footer.php'] as $template) {
    ob_start();
    require ABSPATH . $template;
    $html = html_entity_decode(ob_get_clean(), ENT_QUOTES, 'UTF-8');
    check($template . ' renders grouped closed days', strpos($html, 'Mo, Sa') !== false && strpos($html, 'Geschlossen') !== false, true);
    check($template . ' renders custom Thursday hours', strpos($html, '18:00–20:00 Uhr') !== false, true);
}
echo "Schedule Test Summary: $passed passed, $failed failed.\n";
exit($failed ? 1 : 0);
