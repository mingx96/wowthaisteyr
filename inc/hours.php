<?php
/**
 * WOW Thai Steyr — Single Source of Truth for Opening Hours, Timezone, Sushi & Mittagsmenü Rules
 * All templates, pickup slot calculators, reservation validators, and Schema.org outputs
 * MUST read from this file. Never hardcode hour literals elsewhere.
 */

if (!defined('ABSPATH')) {
    exit;
}

const WOWTHAI_SUSHI_CLOSED_DAYS           = [1]; // 1 = Montag (ISO-8601)
const WOWTHAI_MITTAGSMENU_DAYS            = [1, 2, 3, 4, 5, 6]; // Montag bis Samstag
const WOWTHAI_MITTAGSMENU_M4_DAYS         = [2, 3, 4, 5]; // Dienstag bis Freitag (M4 Sushi-Mittagsmenü)
const WOWTHAI_MITTAGSMENU_RANGE           = ['11:00', '14:30'];
const WOWTHAI_ENFORCE_MITTAGSMENU_HOURS   = true;
const WOWTHAI_LAST_RESERVATION_OFFSET_MIN = 30; // Letzte Reservierung 30 Min. vor Küchenschluss

/**
 * Verbindliche Restaurant-Zeitzone (Steyr, Österreich).
 */
function wowthai_timezone(): DateTimeZone {
    return new DateTimeZone('Europe/Vienna');
}

/**
 * Aktuelle Zeit in Europe/Vienna als DateTimeImmutable.
 */
function wowthai_now(): DateTimeImmutable {
    return new DateTimeImmutable('now', wowthai_timezone());
}

/**
 * Einzige Datenquelle für die regulären Öffnungszeiten (ISO-8601 Wochentag 1=Mo .. 7=So).
 */
function wowthai_hours_default_schedule(): array {
    return [
        1 => [['11:00', '14:30'], ['17:00', '21:00']],
        2 => [['11:00', '14:30'], ['17:00', '21:00']],
        3 => [['11:00', '14:30'], ['17:00', '21:00']],
        4 => [['11:00', '14:30'], ['17:00', '21:00']],
        5 => [['11:00', '14:30'], ['17:00', '21:00']],
        6 => [['11:00', '14:30'], ['17:00', '21:00']],
        7 => [['11:30', '19:00']],
    ];
}

/** Validate and normalize same-day HH:MM ranges. Empty means closed. */
function wowthai_hours_validate_ranges($ranges) {
    if (!is_array($ranges) || array_values($ranges) !== $ranges) {
        return new WP_Error('invalid_hours_ranges', 'Zeitfenster müssen als Liste angegeben werden.');
    }
    foreach ($ranges as $range) {
        if (!is_array($range) || array_keys($range) !== [0, 1]
            || !is_string($range[0]) || !is_string($range[1])
            || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $range[0])
            || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $range[1])
            || $range[0] >= $range[1]) {
            return new WP_Error('invalid_hours_ranges', 'Ungültiges Zeitfenster: HH:MM und Beginn vor Ende erforderlich.');
        }
    }
    usort($ranges, static fn($a, $b) => strcmp($a[0], $b[0]));
    $previous_end = null;
    foreach ($ranges as $range) {
        if ($previous_end !== null && $range[0] < $previous_end) {
            return new WP_Error('invalid_hours_ranges', 'Zeitfenster dürfen sich nicht überschneiden.');
        }
        $previous_end = $range[1];
    }
    return $ranges;
}

/** A weekly override must explicitly provide all seven ISO weekdays. */
function wowthai_hours_validate_weekly($weekly) {
    if (!is_array($weekly) || count($weekly) !== 7) {
        return new WP_Error('invalid_hours_weekly', 'Alle sieben Wochentage sind erforderlich.');
    }
    $normalized = [];
    for ($day = 1; $day <= 7; $day++) {
        if (!array_key_exists($day, $weekly)) {
            return new WP_Error('invalid_hours_weekly', 'Ungültiger Wochentag.');
        }
        $ranges = wowthai_hours_validate_ranges($weekly[$day]);
        if ($ranges instanceof WP_Error) {
            return $ranges;
        }
        $normalized[$day] = $ranges;
    }
    return $normalized;
}

/** Return validation errors to staff config consumers; never seed defaults. */
function wowthai_hours_weekly_config() {
    $missing = new stdClass();
    $stored = get_option('wowthai_weekly_hours', $missing);
    return $stored === $missing ? wowthai_hours_default_schedule() : wowthai_hours_validate_weekly($stored);
}

/** Read only: corrupt saved overrides fail closed, never silently reopen defaults. */
function wowthai_hours_schedule(): array {
    $validated = wowthai_hours_weekly_config();
    $schedule = is_array($validated) ? $validated : array_fill(1, 7, []);
    $filtered = wowthai_hours_validate_weekly(apply_filters('wowthai_hours_schedule', $schedule));
    return is_array($filtered) ? $filtered : $schedule;
}

/** Pure resolver shared by saved schedules and unsaved previews. */
function wowthai_hours_ranges_for_config(DateTimeImmutable $date, array $weekly, array $exceptions): array {
    $date = $date->setTimezone(wowthai_timezone());
    $entry = $exceptions[$date->format('Y-m-d')] ?? null;
    if (is_array($entry) && !empty($entry['closed'])) {
        return [];
    }
    $ranges = is_array($entry) && array_key_exists('ranges', $entry)
        ? $entry['ranges'] : ($weekly[(int) $date->format('N')] ?? []);
    $validated = wowthai_hours_validate_ranges($ranges);
    if (!is_array($validated)) {
        return [];
    }
    // A missing local boundary invalidates this date's whole window, never shifts it.
    return array_values(array_filter($validated, static fn($range) =>
        wowthai_hours_datetime_for_local_time($date, $range[0]) !== null
        && wowthai_hours_datetime_for_local_time($date, $range[1]) !== null
    ));
}

/**
 * Prüft, ob ein bestimmtes Datum als Sondertag / Betriebsurlaub geschlossen ist.
 */
function wowthai_get_special_closed_day(?DateTimeImmutable $at = null): ?array {
    $at = $at ?? wowthai_now();
    $ymd = $at->setTimezone(wowthai_timezone())->format('Y-m-d');
    $special_days = get_option('wowthai_special_days', []);
    if (!is_array($special_days) || empty($special_days[$ymd])) {
        return null;
    }
    $entry = $special_days[$ymd];
    if (is_array($entry) && !empty($entry['closed'])) {
        return [
            'date'   => $ymd,
            'reason' => (string) ($entry['reason'] ?? 'Sondertag / Betriebsurlaub'),
        ];
    }
    return null;
}

/**
 * Liefert die Öffnungs-Zeitfenster für das konkrete Datum (unter Berücksichtigung von Sondertagen).
 */
function wowthai_hours_ranges_for_date(DateTimeImmutable $date): array {
    $exceptions = get_option('wowthai_special_days', []);
    return wowthai_hours_ranges_for_config($date, wowthai_hours_schedule(), is_array($exceptions) ? $exceptions : []);
}

/**
 * Erzeugt ein DateTimeImmutable am selben Kalendertag wie $base mit der Uhrzeit $hm ("HH:MM").
 */
function wowthai_datetime_at_hm(DateTimeImmutable $base, string $hm): DateTimeImmutable {
    [$h, $m] = array_map('intval', explode(':', $hm));
    return $base->setTimezone(wowthai_timezone())->setTime($h, $m, 0);
}

/**
 * Resolve an exact Vienna wall-clock time, or null if PHP normalizes it (spring gap).
 * Repeated autumn times retain the occurrence chosen by PHP's existing setTime policy.
 */
function wowthai_hours_datetime_for_local_time(DateTimeImmutable $date, string $hm): ?DateTimeImmutable {
    if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $hm)) {
        return null;
    }
    $date = $date->setTimezone(wowthai_timezone());
    $resolved = wowthai_datetime_at_hm($date, $hm);
    return $resolved->format('Y-m-d H:i') === $date->format('Y-m-d') . ' ' . $hm ? $resolved : null;
}

/**
 * Prüft exakt, ob die Küche zum Zeitpunkt $at geöffnet ist.
 */
function wowthai_is_open(?DateTimeImmutable $at = null): bool {
    $at = ($at ?? wowthai_now())->setTimezone(wowthai_timezone());
    foreach (wowthai_hours_ranges_for_date($at) as [$start_hm, $end_hm]) {
        $start = wowthai_datetime_at_hm($at, $start_hm);
        $end   = wowthai_datetime_at_hm($at, $end_hm);
        if ($at >= $start && $at < $end) {
            return true;
        }
    }
    return false;
}

/**
 * Prüft, ob am Tag von $at Sushi verfügbar ist (Montag = Ruhetag für Sushi).
 */
function wowthai_sushi_available(?DateTimeImmutable $at = null): bool {
    $at = ($at ?? wowthai_now())->setTimezone(wowthai_timezone());
    $dow = (int) $at->format('N');
    return !in_array($dow, WOWTHAI_SUSHI_CLOSED_DAYS, true);
}

/**
 * Prüft anhand der ISO-Wochentagsnummer (1=Mo .. 7=So), ob Sushi verfügbar ist.
 */
function wowthai_sushi_available_on_dow(?int $day_of_week = null): bool {
    if ($day_of_week === null) {
        return wowthai_sushi_available();
    }
    return !in_array((int) $day_of_week, WOWTHAI_SUSHI_CLOSED_DAYS, true);
}

/**
 * Prüft, ob zum Zeitpunkt $at das Mittagsmenü verfügbar ist (Mo–Sa im Mittagsfenster).
 */
function wowthai_mittagsmenu_available(?DateTimeImmutable $at = null): bool {
    $at = ($at ?? wowthai_now())->setTimezone(wowthai_timezone());
    if (!wowthai_is_open($at)) {
        return false;
    }
    $dow = (int) $at->format('N');
    if (!in_array($dow, WOWTHAI_MITTAGSMENU_DAYS, true)) {
        return false;
    }
    [$start_hm, $end_hm] = WOWTHAI_MITTAGSMENU_RANGE;
    $start = wowthai_datetime_at_hm($at, $start_hm);
    $end   = wowthai_datetime_at_hm($at, $end_hm);
    return ($at >= $start && $at < $end);
}

/**
 * Prüft anhand des Wochentags (1=Mo .. 7=So), ob Mittagsmenü M4 (Sushi) an diesem Tag grundsätzlich serviert wird (Di–Fr).
 */
function wowthai_mittagsmenu_m4_available_on_dow(?int $day_of_week = null): bool {
    if ($day_of_week === null) {
        $now = wowthai_now();
        if (wowthai_get_special_closed_day($now) !== null) {
            return false;
        }
        $day_of_week = (int) $now->format('N');
    }
    return in_array((int) $day_of_week, WOWTHAI_MITTAGSMENU_M4_DAYS, true);
}

/**
 * Prüft, ob zum Zeitpunkt $at das Mittagsmenü M4 (Sushi) verfügbar ist (Di–Fr im Mittagsfenster 11:00–14:30).
 */
function wowthai_mittagsmenu_m4_available(?DateTimeImmutable $at = null): bool {
    $at = ($at ?? wowthai_now())->setTimezone(wowthai_timezone());
    $dow = (int) $at->format('N');
    if (!in_array($dow, WOWTHAI_MITTAGSMENU_M4_DAYS, true)) {
        return false;
    }
    return wowthai_mittagsmenu_available($at);
}

/**
 * Prüft, ob ein geplanter Abhol-Slot ($date + $slot_hm) für das Mittagsmenü zulässig ist.
 */
function wowthai_is_slot_valid_for_mittagsmenu(DateTimeImmutable $date, string $slot_hm): bool {
    if (!WOWTHAI_ENFORCE_MITTAGSMENU_HOURS) {
        return true;
    }
    $date = $date->setTimezone(wowthai_timezone());
    $dow = (int) $date->format('N');
    if (!in_array($dow, WOWTHAI_MITTAGSMENU_DAYS, true)) {
        return false;
    }
    if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $slot_hm)) {
        return false;
    }
    [$start_hm, $end_hm] = WOWTHAI_MITTAGSMENU_RANGE;
    $slot_dt  = wowthai_datetime_at_hm($date, $slot_hm);
    $start_dt = wowthai_datetime_at_hm($date, $start_hm);
    $end_dt   = wowthai_datetime_at_hm($date, $end_hm);
    if ($slot_dt < $start_dt || $slot_dt > $end_dt) {
        return false;
    }
    // Pickup permits the kitchen closing endpoint; the pickup calculator enforces its own buffer.
    foreach (wowthai_hours_ranges_for_date($date) as [$opens, $closes]) {
        if ($slot_hm >= $opens && $slot_hm <= $closes) {
            return true;
        }
    }
    return false;
}

/**
 * Prüft, ob ein geplanter Abhol-Slot ($date + $slot_hm) für Mittagsmenü M4 (Sushi, nur Di–Fr 11:00–14:30) zulässig ist.
 */
function wowthai_is_slot_valid_for_mittagsmenu_m4(DateTimeImmutable $date, string $slot_hm): bool {
    if (!WOWTHAI_ENFORCE_MITTAGSMENU_HOURS) {
        return true;
    }
    $date = $date->setTimezone(wowthai_timezone());
    $dow = (int) $date->format('N');
    if (!in_array($dow, WOWTHAI_MITTAGSMENU_M4_DAYS, true)) {
        return false;
    }
    return wowthai_is_slot_valid_for_mittagsmenu($date, $slot_hm);
}

/** Group only weekdays with identical ranges; labels preserve gaps between days. */
function wowthai_hours_grouped_rows(?array $weekly = null): array {
    $weekly = $weekly ?? wowthai_hours_schedule();
    $groups = [];
    foreach (range(1, 7) as $day) {
        $ranges = wowthai_hours_validate_ranges($weekly[$day] ?? []);
        $ranges = is_array($ranges) ? $ranges : [];
        $key = json_encode($ranges);
        if (!isset($groups[$key])) {
            $groups[$key] = ['ranges' => $ranges, 'weekdays' => []];
        }
        $groups[$key]['weekdays'][] = $day;
    }
    $names = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];
    $rows = [];
    foreach ($groups as $group) {
        $runs = [];
        foreach ($group['weekdays'] as $day) {
            $last = count($runs) - 1;
            if ($last >= 0 && $runs[$last][1] === $day - 1) {
                $runs[$last][1] = $day;
            } else {
                $runs[] = [$day, $day];
            }
        }
        $labels = array_map(static fn($run) => $names[$run[0]] . ($run[0] === $run[1] ? '' : ' – ' . $names[$run[1]]), $runs);
        $hours = $group['ranges'] ? implode(' · ', array_map(static fn($r) => $r[0] . '–' . $r[1], $group['ranges'])) . ' Uhr' : 'Geschlossen';
        if ($group['weekdays'] === [7] && count($group['ranges']) === 1) {
            $hours .= ' (durchgehend)';
        }
        $rows[] = [
            'days' => $group['weekdays'] === [7] ? 'Sonntag' : implode(', ', $labels),
            'hours' => $hours,
            'weekdays' => $group['weekdays'],
        ];
    }
    return $rows;
}

/**
 * Liefert vorformatierte Anzeige-Zeilen für Header, Footer, Startseite & Reservierungen.
 */
function wowthai_hours_display(): array {
    $schedule = wowthai_hours_schedule();
    $mosa_ranges = $schedule[1] ?? [];
    $sun_ranges  = $schedule[7] ?? [];

    $format_ranges = static function (array $ranges, string $sep = ' · '): string {
        $parts = array_map(static fn($r) => $r[0] . '–' . $r[1], $ranges);
        return $parts ? implode($sep, $parts) : 'Geschlossen';
    };

    $mosa_short = $format_ranges($mosa_ranges, ' · ');
    $mosa_amp   = $format_ranges($mosa_ranges, ' & ');
    $sun_short  = $format_ranges($sun_ranges, ' · ');
    [$lunch_start, $lunch_end] = WOWTHAI_MITTAGSMENU_RANGE;

    return [
        'mosa_days'        => 'Mo – Sa',
        'mosa_hours'       => $mosa_short,
        'mosa_hours_uhr'   => $mosa_short . ($mosa_ranges ? ' Uhr' : ''),
        'mosa_hours_amp'   => $mosa_amp . ($mosa_ranges ? ' Uhr' : ''),
        'sun_days'         => 'Sonntag',
        'sun_hours'        => $sun_short,
        'sun_hours_uhr'    => $sun_short . ($sun_ranges ? ' Uhr' . (count($sun_ranges) === 1 ? ' (durchgehend)' : '') : ''),
        'sun_hours_short'  => $sun_short . ($sun_ranges ? ' Uhr' : ''),
        'sushi_note'       => 'Montags Sushi-Ruhetag · Küchenzeiten laut Wochenplan',
        'sushi_note_short' => 'Sushi: Montags Ruhetag · Küchenzeiten laut Wochenplan',
        'mittags_note'     => 'Mo – Sa · ' . $lunch_start . ' – ' . $lunch_end . ' Uhr innerhalb der Küchenzeiten (M4 Sushi: Di – Fr)',
        'mittags_m4_note'  => 'Di – Fr · ' . $lunch_start . ' – ' . $lunch_end . ' Uhr innerhalb der Küchenzeiten',
        'rows'             => wowthai_hours_grouped_rows($schedule),
    ];
}

/**
 * Berechnet den detaillierten Öffnungsstatus anhand von wowthai_hours_schedule().
 */
function wowthai_compute_opening_status(?DateTimeImmutable $at = null, ?array $weekly = null, ?array $exceptions = null): array {
    $now = ($at ?? wowthai_now())->setTimezone(wowthai_timezone());
    $weekly = $weekly ?? wowthai_hours_schedule();
    $exceptions = $exceptions ?? get_option('wowthai_special_days', []);
    $exceptions = is_array($exceptions) ? $exceptions : [];
    $ranges = wowthai_hours_ranges_for_config($now, $weekly, $exceptions);
    $is_open = false;
    $detail = '';
    foreach ($ranges as [$start_hm, $end_hm]) {
        if ($now >= wowthai_datetime_at_hm($now, $start_hm) && $now < wowthai_datetime_at_hm($now, $end_hm)) {
            $is_open = true;
            $prep = function_exists('wowthai_get_kitchen_prep_time') ? wowthai_get_kitchen_prep_time() : '20–30 Min.';
            $detail = 'Küche geöffnet bis ' . $end_hm . ' Uhr · Abholung bereit in ca. ' . $prep;
            break;
        }
    }
    $next_label = '';
    if (!$is_open) {
        $next = wowthai_hours_next_opening($now, $weekly, $exceptions);
        if ($next !== null) {
            $prefix = $next->format('Y-m-d') === $now->format('Y-m-d') ? 'heute um '
                : ($next->format('Y-m-d') === $now->modify('+1 day')->format('Y-m-d') ? 'morgen ab ' : 'am ' . $next->format('d.m.Y') . ' ab ');
            $next_label = $prefix . $next->format('H:i') . ' Uhr';
            $detail = 'Küche geschlossen · Wieder geöffnet ' . $next_label . '. Vorbestellung möglich!';
        } else {
            $detail = 'Derzeit sind keine weiteren Öffnungszeiten geplant.';
        }
        $entry = $exceptions[$now->format('Y-m-d')] ?? null;
        if (is_array($entry) && !empty($entry['closed']) && !empty($entry['reason']) && is_string($entry['reason'])) {
            $detail = $entry['reason'] . ' · ' . $detail;
        }
    }
    $dow = (int) $now->format('N');
    return [
        'is_open' => $is_open,
        'status_type' => $is_open ? 'open' : 'preorder',
        'status_label' => $is_open ? 'Jetzt geöffnet' : ($ranges ? 'Vorbestellung möglich' : 'Heute geschlossen'),
        'status_detail' => $detail,
        'next_open_label' => $next_label,
        'time_now' => $now->format('H:i'),
        'day_now' => $dow,
        'is_sushi_day' => !empty($ranges) && wowthai_sushi_available($now),
        'is_m4_day' => !empty($ranges) && wowthai_mittagsmenu_m4_available_on_dow($dow),
    ];
}

/** Find the next real opening, including exceptions beyond the next week. No fallback hour. */
function wowthai_hours_next_opening(DateTimeImmutable $at, ?array $weekly = null, ?array $exceptions = null): ?DateTimeImmutable {
    $at = $at->setTimezone(wowthai_timezone());
    $weekly = $weekly ?? wowthai_hours_schedule();
    $exceptions = $exceptions ?? get_option('wowthai_special_days', []);
    $exceptions = is_array($exceptions) ? $exceptions : [];
    $dates = [];
    $anchors = [$at->format('Y-m-d')];
    foreach (array_keys($exceptions) as $ymd) {
        if (!is_string($ymd) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $ymd)) {
            continue;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd, wowthai_timezone());
        if ($date && $date->format('Y-m-d') === $ymd && $ymd >= $at->format('Y-m-d')) {
            $anchors[] = $ymd;
        }
    }
    // Two weeks include the next occurrence when a weekly opening falls in the spring gap.
    // Exception anchors likewise include the next real weekly opening after each blocked date.
    foreach ($anchors as $anchor) {
        $base = new DateTimeImmutable($anchor, wowthai_timezone());
        for ($offset = 0; $offset <= 14; $offset++) {
            $date = $base->modify('+' . $offset . ' days');
            $dates[$date->format('Y-m-d')] = $date;
        }
    }
    ksort($dates);
    foreach ($dates as $date) {
        foreach (wowthai_hours_ranges_for_config($date, $weekly, $exceptions) as [$opens, $closes]) {
            $opening = wowthai_datetime_at_hm($date, $opens);
            if ($opening > $at) {
                return $opening;
            }
        }
    }
    return null;
}

/**
 * Generiert die gültigen Tischreservierungs-Zeitslots (im 30-Minuten-Takt) für ein Datum.
 * Letzter Slot ist jeweils WOWTHAI_LAST_RESERVATION_OFFSET_MIN (30 Min.) vor Küchenschluss.
 */
function wowthai_reservation_slots(DateTimeImmutable $date, ?DateTimeImmutable $now = null, ?array $weekly = null, ?array $exceptions = null): array {
    $tz   = wowthai_timezone();
    $date = $date->setTimezone($tz);
    $now  = ($now ?? wowthai_now())->setTimezone($tz);

    if ($date->format('Y-m-d') < $now->format('Y-m-d')) {
        return [];
    }

    $exceptions = $exceptions ?? get_option('wowthai_special_days', []);
    $ranges = wowthai_hours_ranges_for_config($date, $weekly ?? wowthai_hours_schedule(), is_array($exceptions) ? $exceptions : []);
    $is_same = ($date->format('Y-m-d') === $now->format('Y-m-d'));
    $min_dt  = $now->modify('+30 minutes');
    $slots   = [];

    foreach ($ranges as [$start_hm, $end_hm]) {
        [$sh, $sm] = array_map('intval', explode(':', $start_hm));
        [$eh, $em] = array_map('intval', explode(':', $end_hm));
        $start_min = $sh * 60 + $sm;
        $last_min  = ($eh * 60 + $em) - WOWTHAI_LAST_RESERVATION_OFFSET_MIN;

        for ($m = $start_min; $m <= $last_min; $m += 30) {
            $hh = intdiv($m, 60);
            $mm = $m % 60;
            $hm = sprintf('%02d:%02d', $hh, $mm);
            $slot_dt = wowthai_hours_datetime_for_local_time($date, $hm);
            if ($slot_dt === null || ($is_same && $slot_dt < $min_dt)) {
                continue;
            }
            $slots[] = $hm;
        }
    }

    return array_values(array_unique($slots));
}

/**
 * Liefert ein Lookup-Array aller Reservierungsslots nach Wochentag (1..7) für Client-JS.
 */
function wowthai_reservation_slots_by_weekday(): array {
    $tz  = wowthai_timezone();
    $ref = new DateTimeImmutable('2026-10-19 06:00:00', $tz); // Montag
    $out = [];
    for ($i = 0; $i < 7; $i++) {
        $d   = $ref->modify("+{$i} days");
        $dow = (int) $d->format('N');
        $out[$dow] = wowthai_reservation_slots($d, $d);
    }
    return $out;
}

/**
 * Erzeugt das Schema.org OpeningHoursSpecification-Array aus wowthai_hours_schedule().
 */
function wowthai_schema_opening_hours(?array $schedule = null): array {
    $schedule = $schedule ?? wowthai_hours_schedule();
    $names = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
    $specs = [];
    foreach (wowthai_hours_grouped_rows($schedule) as $row) {
        $days = array_map(static fn($day) => $names[$day], $row['weekdays']);
        foreach ($schedule[$row['weekdays'][0]] as [$opens, $closes]) {
            $specs[] = [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => $days,
                'opens' => $opens,
                'closes' => $closes,
            ];
        }
    }

    return $specs;
}

/**
 * Formatiert ein Datum ("YYYY-MM-DD") zeitzonensicher in Europe/Vienna.
 */
function wowthai_format_date_vienna(string $ymd, string $format = 'D, d. M Y'): string {
    $ymd = trim($ymd);
    if ($ymd === '') {
        return '–';
    }
    try {
        $dt = new DateTimeImmutable($ymd . ' 12:00:00', wowthai_timezone());
        if (function_exists('wp_date')) {
            return (string) wp_date($format, $dt->getTimestamp(), wowthai_timezone());
        }
        return $dt->format($format);
    } catch (Exception $e) {
        return $ymd;
    }
}

