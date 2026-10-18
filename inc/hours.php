<?php
/**
 * WOW Thai — Single Source of Truth for Opening Hours, Timezone, Sushi & Mittagsmenü Rules
 * All templates, pickup slot calculators, reservation validators, and Schema.org outputs
 * MUST read from this file. Never hardcode hour literals elsewhere.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

if (file_exists(__DIR__ . '/branch-config.php')) {
    require_once __DIR__ . '/branch-config.php';
}

const WOWTHAI_SUSHI_CLOSED_DAYS           = [1]; // 1 = Montag (ISO-8601)
const WOWTHAI_MITTAGSMENU_DAYS            = [1, 2, 3, 4, 5, 6]; // Montag bis Samstag
const WOWTHAI_MITTAGSMENU_M4_DAYS         = [2, 3, 4, 5]; // Dienstag bis Freitag (M4 Sushi-Mittagsmenü)
const WOWTHAI_MITTAGSMENU_RANGE           = ['11:00', '14:30'];
const WOWTHAI_ENFORCE_MITTAGSMENU_HOURS   = true;
const WOWTHAI_LAST_RESERVATION_OFFSET_MIN = 30; // Letzte Reservierung 30 Min. vor Küchenschluss

/**
 * Verbindliche Restaurant-Zeitzone (Österreich).
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
 * Delegiert an wowthai_get_branch_config($branch_id) mit defensivem Fallback auf Steyr.
 */
function wowthai_hours_schedule(?string $branch_id = null): array {
    $default = [
        1 => [['11:00', '14:30'], ['17:00', '21:00']],
        2 => [['11:00', '14:30'], ['17:00', '21:00']],
        3 => [['11:00', '14:30'], ['17:00', '21:00']],
        4 => [['11:00', '14:30'], ['17:00', '21:00']],
        5 => [['11:00', '14:30'], ['17:00', '21:00']],
        6 => [['11:00', '14:30'], ['17:00', '21:00']],
        7 => [['11:30', '19:00']],
    ];
    $schedule = $default;

    if (function_exists('wowthai_get_branch_config')) {
        $cfg = wowthai_get_branch_config($branch_id);
        if (!empty($cfg['hours_schedule']) && is_array($cfg['hours_schedule'])) {
            $schedule = $cfg['hours_schedule'];
        }
    }

    if (function_exists('apply_filters')) {
        $schedule = apply_filters('wowthai_hours_schedule', $schedule, $branch_id);
    }

    return $schedule;
}

/**
 * Prüft, ob ein bestimmtes Datum als Sondertag / Betriebsurlaub geschlossen ist.
 */
function wowthai_get_special_closed_day(?DateTimeImmutable $at = null, ?string $branch_id = null): ?array {
    $at = $at ?? wowthai_now();
    $ymd = $at->format('Y-m-d');

    // 1. Branch dictionary configuration
    if (function_exists('wowthai_get_branch_config')) {
        $cfg = wowthai_get_branch_config($branch_id);
        if (!empty($cfg['special_days'][$ymd]['closed'])) {
            return [
                'date'   => $ymd,
                'reason' => (string) ($cfg['special_days'][$ymd]['reason'] ?? 'Sondertag / Betriebsurlaub'),
            ];
        }
    }

    // 2. Branch-specific DB option
    $bid = $branch_id;
    if ($bid === null && function_exists('wowthai_get_branch_id')) {
        $bid = wowthai_get_branch_id();
    }
    if ($bid !== null && $bid !== '' && function_exists('get_option')) {
        $branch_special = get_option("wowthai_{$bid}_special_days", null);
        if (is_array($branch_special) && !empty($branch_special[$ymd]['closed'])) {
            return [
                'date'   => $ymd,
                'reason' => (string) ($branch_special[$ymd]['reason'] ?? 'Sondertag / Betriebsurlaub'),
            ];
        }
    }

    // 3. Global DB option
    if (function_exists('get_option')) {
        $special_days = get_option('wowthai_special_days', []);
        if (is_array($special_days) && !empty($special_days[$ymd]['closed'])) {
            return [
                'date'   => $ymd,
                'reason' => (string) ($special_days[$ymd]['reason'] ?? 'Sondertag / Betriebsurlaub'),
            ];
        }
    }

    return null;
}

/**
 * Liefert die Öffnungs-Zeitfenster für das konkrete Datum (unter Berücksichtigung von Sondertagen).
 */
function wowthai_hours_ranges_for_date(DateTimeImmutable $date, ?string $branch_id = null): array {
    if (wowthai_get_special_closed_day($date, $branch_id) !== null) {
        return [];
    }
    $schedule = wowthai_hours_schedule($branch_id);
    $dow = (int) $date->format('N');
    return $schedule[$dow] ?? [];
}

/**
 * Erzeugt ein DateTimeImmutable am selben Kalendertag wie $base mit der Uhrzeit $hm ("HH:MM").
 */
function wowthai_datetime_at_hm(DateTimeImmutable $base, string $hm): DateTimeImmutable {
    [$h, $m] = array_map('intval', explode(':', $hm));
    return $base->setTimezone(wowthai_timezone())->setTime($h, $m, 0);
}

/**
 * Prüft exakt, ob die Küche zum Zeitpunkt $at geöffnet ist.
 */
function wowthai_is_open(?DateTimeImmutable $at = null, ?string $branch_id = null): bool {
    $at = ($at ?? wowthai_now())->setTimezone(wowthai_timezone());
    foreach (wowthai_hours_ranges_for_date($at, $branch_id) as [$start_hm, $end_hm]) {
        $start = wowthai_datetime_at_hm($at, $start_hm);
        $end   = wowthai_datetime_at_hm($at, $end_hm);
        if ($at >= $start && $at < $end) {
            return true;
        }
    }
    return false;
}

/**
 * Prüft, ob Sushi am angegebenen Zeitpunkt serviert wird.
 * Berücksichtigt has_sushi Feature-Flag der Filiale (z. B. Saalbach hat kein Sushi).
 */
function wowthai_sushi_available(?DateTimeImmutable $at = null, ?string $branch_id = null): bool {
    if (function_exists('wowthai_get_branch_config')) {
        $cfg = wowthai_get_branch_config($branch_id);
        if (isset($cfg['has_sushi']) && !$cfg['has_sushi']) {
            return false;
        }
    }
    $at = ($at ?? wowthai_now())->setTimezone(wowthai_timezone());
    $dow = (int) $at->format('N');
    return !in_array($dow, WOWTHAI_SUSHI_CLOSED_DAYS, true);
}

/**
 * Prüft anhand des ISO-8601 Wochentags (1=Mo .. 7=So), ob die Sushi-Bar grundsätzlich geöffnet ist.
 */
function wowthai_sushi_available_on_dow(?int $day_of_week = null, ?string $branch_id = null): bool {
    if (function_exists('wowthai_get_branch_config')) {
        $cfg = wowthai_get_branch_config($branch_id);
        if (isset($cfg['has_sushi']) && !$cfg['has_sushi']) {
            return false;
        }
    }
    if ($day_of_week === null) {
        return wowthai_sushi_available(null, $branch_id);
    }
    return !in_array((int) $day_of_week, WOWTHAI_SUSHI_CLOSED_DAYS, true);
}

/**
 * Prüft, ob zum Zeitpunkt $at das reguläre Mittagsmenü serviert wird (Mo–Sa 11:00–14:30).
 * Berücksichtigt has_mittagsmenu Feature-Flag der Filiale (z. B. Saalbach hat durchgehend Küche, kein Mittagsmenü).
 */
function wowthai_mittagsmenu_available(?DateTimeImmutable $at = null, ?string $branch_id = null): bool {
    if (function_exists('wowthai_get_branch_config')) {
        $cfg = wowthai_get_branch_config($branch_id);
        if (isset($cfg['has_mittagsmenu']) && !$cfg['has_mittagsmenu']) {
            return false;
        }
    }
    $at = ($at ?? wowthai_now())->setTimezone(wowthai_timezone());
    if (wowthai_get_special_closed_day($at, $branch_id) !== null) {
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
function wowthai_mittagsmenu_m4_available_on_dow(?int $day_of_week = null, ?string $branch_id = null): bool {
    if (function_exists('wowthai_get_branch_config')) {
        $cfg = wowthai_get_branch_config($branch_id);
        if ((isset($cfg['has_sushi']) && !$cfg['has_sushi']) || (isset($cfg['has_mittagsmenu']) && !$cfg['has_mittagsmenu'])) {
            return false;
        }
    }
    if ($day_of_week === null) {
        $now = wowthai_now();
        if (wowthai_get_special_closed_day($now, $branch_id) !== null) {
            return false;
        }
        $day_of_week = (int) $now->format('N');
    }
    return in_array((int) $day_of_week, WOWTHAI_MITTAGSMENU_M4_DAYS, true);
}

/**
 * Prüft, ob zum Zeitpunkt $at das Mittagsmenü M4 (Sushi) verfügbar ist (Di–Fr im Mittagsfenster 11:00–14:30).
 */
function wowthai_mittagsmenu_m4_available(?DateTimeImmutable $at = null, ?string $branch_id = null): bool {
    if (function_exists('wowthai_get_branch_config')) {
        $cfg = wowthai_get_branch_config($branch_id);
        if ((isset($cfg['has_sushi']) && !$cfg['has_sushi']) || (isset($cfg['has_mittagsmenu']) && !$cfg['has_mittagsmenu'])) {
            return false;
        }
    }
    $at = ($at ?? wowthai_now())->setTimezone(wowthai_timezone());
    $dow = (int) $at->format('N');
    if (!in_array($dow, WOWTHAI_MITTAGSMENU_M4_DAYS, true)) {
        return false;
    }
    return wowthai_mittagsmenu_available($at, $branch_id);
}

/**
 * Prüft, ob ein geplanter Abhol-Slot ($date + $slot_hm) für das Mittagsmenü zulässig ist.
 */
function wowthai_is_slot_valid_for_mittagsmenu(DateTimeImmutable $date, string $slot_hm, ?string $branch_id = null): bool {
    if (function_exists('wowthai_get_branch_config')) {
        $cfg = wowthai_get_branch_config($branch_id);
        if (isset($cfg['has_mittagsmenu']) && !$cfg['has_mittagsmenu']) {
            return false;
        }
    }
    if (!WOWTHAI_ENFORCE_MITTAGSMENU_HOURS) {
        return true;
    }
    $dow = (int) $date->format('N');
    if (!in_array($dow, WOWTHAI_MITTAGSMENU_DAYS, true)) {
        return false;
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $slot_hm)) {
        return false;
    }
    [$start_hm, $end_hm] = WOWTHAI_MITTAGSMENU_RANGE;
    $slot_dt  = wowthai_datetime_at_hm($date, $slot_hm);
    $start_dt = wowthai_datetime_at_hm($date, $start_hm);
    $end_dt   = wowthai_datetime_at_hm($date, $end_hm);
    return ($slot_dt >= $start_dt && $slot_dt <= $end_dt);
}

/**
 * Prüft, ob ein geplanter Abhol-Slot ($date + $slot_hm) für Mittagsmenü M4 (Sushi, nur Di–Fr 11:00–14:30) zulässig ist.
 */
function wowthai_is_slot_valid_for_mittagsmenu_m4(DateTimeImmutable $date, string $slot_hm, ?string $branch_id = null): bool {
    if (function_exists('wowthai_get_branch_config')) {
        $cfg = wowthai_get_branch_config($branch_id);
        if ((isset($cfg['has_sushi']) && !$cfg['has_sushi']) || (isset($cfg['has_mittagsmenu']) && !$cfg['has_mittagsmenu'])) {
            return false;
        }
    }
    if (!WOWTHAI_ENFORCE_MITTAGSMENU_HOURS) {
        return true;
    }
    $dow = (int) $date->format('N');
    if (!in_array($dow, WOWTHAI_MITTAGSMENU_M4_DAYS, true)) {
        return false;
    }
    return wowthai_is_slot_valid_for_mittagsmenu($date, $slot_hm, $branch_id);
}

/**
 * Liefert vorformatierte Anzeige-Zeilen für Header, Footer, Startseite & Reservierungen.
 */
function wowthai_hours_display(?string $branch_id = null): array {
    $schedule = wowthai_hours_schedule($branch_id);
    $cfg = function_exists('wowthai_get_branch_config') ? wowthai_get_branch_config($branch_id) : [];
    $has_sushi = $cfg['has_sushi'] ?? true;
    $has_mittag = $cfg['has_mittagsmenu'] ?? true;

    $mosa_ranges = $schedule[1] ?? [];
    $sun_ranges  = $schedule[7] ?? [];

    $format_ranges = static function (array $ranges, string $sep = ' · '): string {
        $parts = array_map(static fn($r) => $r[0] . '–' . $r[1], $ranges);
        return implode($sep, $parts);
    };

    $mosa_short = $format_ranges($mosa_ranges, ' · ');
    $mosa_amp   = $format_ranges($mosa_ranges, ' & ');
    $sun_short  = $format_ranges($sun_ranges, ' · ');
    [$lunch_start, $lunch_end] = WOWTHAI_MITTAGSMENU_RANGE;

    $mosa_suffix = count($mosa_ranges) === 1 ? ' Uhr (durchgehend)' : ' Uhr';

    return [
        'mosa_days'        => 'Mo – Sa',
        'mosa_hours'       => $mosa_short,
        'mosa_hours_uhr'   => $mosa_short . $mosa_suffix,
        'mosa_hours_amp'   => $mosa_amp . ' Uhr',
        'sun_days'         => 'Sonntag',
        'sun_hours'        => $sun_short,
        'sun_hours_uhr'    => $sun_short . ' Uhr (durchgehend)',
        'sun_hours_short'  => $sun_short . ' Uhr',
        'sushi_note'       => $has_sushi ? 'Montags Sushi-Ruhetag (Warme Küche regulär)' : '',
        'sushi_note_short' => $has_sushi ? 'Sushi: Montags Ruhetag (Warme Küche geöffnet)' : '',
        'mittags_note'     => $has_mittag ? 'Mo – Sa · ' . $lunch_start . ' – ' . $lunch_end . ' Uhr (M4 Sushi: Di – Fr)' : '',
        'mittags_m4_note'  => $has_mittag ? 'Di – Fr · ' . $lunch_start . ' – ' . $lunch_end . ' Uhr' : '',
        'rows'             => [
            [
                'days'  => 'Mo – Sa',
                'hours' => $mosa_short . $mosa_suffix,
            ],
            [
                'days'  => 'Sonntag',
                'hours' => $sun_short . ' Uhr (durchgehend)',
            ],
        ],
    ];
}

/**
 * Berechnet den detaillierten Öffnungsstatus anhand von wowthai_hours_schedule().
 * Unterstützt geteilte Schichten (Mittags/Abendservice) sowie durchgehende Öffnungszeiten
 * (z. B. Saalbach Alpine Continuous oder Sonntage) ohne fehlerhafte "Mittagsservice"-Bezeichnung.
 */
function wowthai_compute_opening_status(?DateTimeImmutable $at = null, ?string $branch_id = null): array {
    $now       = ($at ?? wowthai_now())->setTimezone(wowthai_timezone());
    $dow       = (int) $now->format('N');
    $hour_min  = $now->format('H:i');
    $prep_time = function_exists('wowthai_get_kitchen_prep_time') ? wowthai_get_kitchen_prep_time() : '20–30 Min.';

    $cfg = function_exists('wowthai_get_branch_config') ? wowthai_get_branch_config($branch_id) : [];
    $has_sushi  = $cfg['has_sushi'] ?? true;
    $has_mittag = $cfg['has_mittagsmenu'] ?? true;

    $special = wowthai_get_special_closed_day($now, $branch_id);
    if ($special !== null) {
        return [
            'is_open'         => false,
            'status_type'     => 'preorder',
            'status_label'    => 'Heute geschlossen',
            'status_detail'   => $special['reason'] . ' · Vorbestellung für den nächsten Öffnungstag möglich.',
            'next_open_label' => 'am nächsten Öffnungstag',
            'time_now'        => $hour_min,
            'day_now'         => $dow,
            'is_sushi_day'    => false,
            'is_m4_day'       => false,
        ];
    }

    $ranges          = wowthai_hours_ranges_for_date($now, $branch_id);
    $is_open         = false;
    $status_type     = 'preorder';
    $status_label    = 'Vorbestellung möglich';
    $status_detail   = '';
    $next_open_label = '';

    foreach ($ranges as $idx => [$start_hm, $end_hm]) {
        $start = wowthai_datetime_at_hm($now, $start_hm);
        $end   = wowthai_datetime_at_hm($now, $end_hm);
        if ($now >= $start && $now < $end) {
            $is_open      = true;
            $status_type  = 'open';
            $status_label = 'Jetzt geöffnet';
            if (count($ranges) === 1) {
                // Durchgehender Service (Sonntag oder Saalbach durchgehender Winterbetrieb)
                $day_prefix = ($dow === 7) ? 'Sonntag durchgehend geöffnet' : 'Durchgehend geöffnet';
                $status_detail = $day_prefix . ' bis ' . $end_hm . ' Uhr · Abholung bereit in ca. ' . $prep_time;
            } elseif ($idx === 0) {
                $status_detail = 'Mittagsservice bis ' . $end_hm . ' Uhr · Abholung bereit in ca. ' . $prep_time;
            } else {
                $status_detail = 'Abendservice bis ' . $end_hm . ' Uhr · Abholung bereit in ca. ' . $prep_time;
            }
            break;
        }
    }

    if (!$is_open) {
        $first_open  = $ranges[0][0] ?? '11:00';
        $second_open = $ranges[1][0] ?? null;
        $first_close = $ranges[0][1] ?? null;

        if (!empty($ranges) && $now < wowthai_datetime_at_hm($now, $first_open)) {
            $next_open_label = 'heute um ' . $first_open . ' Uhr';
            if (count($ranges) === 1 && $dow === 7) {
                $status_detail = 'Küche öffnet am Sonntag um ' . $first_open . ' Uhr. Vorbestellung jetzt möglich!';
            } elseif (count($ranges) === 1) {
                $status_detail = 'Küche öffnet heute um ' . $first_open . ' Uhr. Vorbestellung jetzt möglich!';
            } else {
                $status_detail = 'Küche öffnet heute um ' . $first_open . ' Uhr. Sie können jetzt für den Mittag vorbestellen!';
            }
        } elseif ($second_open !== null && $first_close !== null && $now >= wowthai_datetime_at_hm($now, $first_close) && $now < wowthai_datetime_at_hm($now, $second_open)) {
            $next_open_label = 'heute um ' . $second_open . ' Uhr';
            $status_detail   = 'Nachmittagspause · Küche öffnet wieder um ' . $second_open . ' Uhr. Vorbestellung jetzt möglich!';
        } else {
            $tomorrow       = $now->modify('+1 day');
            $tomorrow_ranges = wowthai_hours_ranges_for_date($tomorrow, $branch_id);
            $tomorrow_open   = $tomorrow_ranges[0][0] ?? $first_open;
            if ($dow === 6) {
                $next_open_label = 'morgen (Sonntag) ab ' . $tomorrow_open . ' Uhr';
                $status_detail   = 'Küche für heute geschlossen. Vorbestellung für Sonntag ab ' . $tomorrow_open . ' Uhr möglich!';
            } elseif ($dow === 7) {
                $next_open_label = 'morgen (Montag) ab ' . $tomorrow_open . ' Uhr';
                $sushi_note = $has_sushi ? ' (Montags Sushi-Ruhetag)' : '';
                $status_detail   = 'Schönen Sonntagabend! Wir sind morgen ab ' . $tomorrow_open . ' Uhr wieder für Sie da' . $sushi_note . '.';
            } else {
                $next_open_label = 'morgen ab ' . $tomorrow_open . ' Uhr';
                $status_detail   = 'Küche für heute geschlossen. Vorbestellung für morgen ab ' . $tomorrow_open . ' Uhr möglich!';
            }
        }
    }

    return [
        'is_open'         => $is_open,
        'status_type'     => $status_type,
        'status_label'    => $status_label,
        'status_detail'   => $status_detail,
        'next_open_label' => $next_open_label,
        'time_now'        => $hour_min,
        'day_now'         => $dow,
        'is_sushi_day'    => wowthai_sushi_available($now, $branch_id),
        'is_m4_day'       => $has_mittag ? wowthai_mittagsmenu_m4_available_on_dow($dow, $branch_id) : false,
    ];
}

/**
 * Generiert die gültigen Tischreservierungs-Zeitslots (im 30-Minuten-Takt) für ein Datum.
 * Letzter Slot ist jeweils WOWTHAI_LAST_RESERVATION_OFFSET_MIN (30 Min.) vor Küchenschluss.
 */
function wowthai_reservation_slots(DateTimeImmutable $date, ?DateTimeImmutable $now = null, ?string $branch_id = null): array {
    $tz   = wowthai_timezone();
    $date = $date->setTimezone($tz);
    $now  = ($now ?? wowthai_now())->setTimezone($tz);

    if ($date->format('Y-m-d') < $now->format('Y-m-d')) {
        return [];
    }

    $ranges  = wowthai_hours_ranges_for_date($date, $branch_id);
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
            if ($is_same) {
                $slot_dt = $date->setTime($hh, $mm, 0);
                if ($slot_dt < $min_dt) {
                    continue;
                }
            }
            $slots[] = $hm;
        }
    }

    return array_values(array_unique($slots));
}

/**
 * Liefert ein Lookup-Array aller Reservierungsslots nach Wochentag (1..7) für Client-JS.
 */
function wowthai_reservation_slots_by_weekday(?string $branch_id = null): array {
    $tz  = wowthai_timezone();
    $ref = new DateTimeImmutable('2026-10-19 06:00:00', $tz); // Montag
    $out = [];
    for ($i = 0; $i < 7; $i++) {
        $d   = $ref->modify("+{$i} days");
        $dow = (int) $d->format('N');
        $out[$dow] = wowthai_reservation_slots($d, $d, $branch_id);
    }
    return $out;
}

/**
 * Erzeugt das Schema.org OpeningHoursSpecification-Array aus wowthai_hours_schedule().
 */
function wowthai_schema_opening_hours(?string $branch_id = null): array {
    $schedule = wowthai_hours_schedule($branch_id);
    $day_names = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
    ];

    // Group days by identical range combinations
    $groups = [];
    foreach ($day_names as $dow => $name) {
        $ranges = $schedule[$dow] ?? [];
        if (empty($ranges)) continue;
        $sig = serialize($ranges);
        if (!isset($groups[$sig])) {
            $groups[$sig] = [
                'days'   => [],
                'ranges' => $ranges,
            ];
        }
        $groups[$sig]['days'][] = $name;
    }

    $specs = [];
    foreach ($groups as $g) {
        foreach ($g['ranges'] as [$opens, $closes]) {
            $specs[] = [
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => $g['days'],
                'opens'     => $opens,
                'closes'    => $closes,
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
