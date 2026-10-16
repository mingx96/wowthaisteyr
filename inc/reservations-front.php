<?php
/**
 * WOW Thai Steyr — Secure Front-End Reservation Handler (PRG, Honeypot, Rate-Limit,
 * Server-Side Hours Validation, Guest Self-Cancellation, .ics Calendar Export & DSGVO Cleanup)
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/hours.php';

function wowthai_res_token(int $res_id): string {
    return substr(hash_hmac('sha256', 'res_view_' . $res_id, wp_salt('auth')), 0, 24);
}

function wowthai_res_cancel_token(int $res_id): string {
    return substr(hash_hmac('sha256', 'res_cancel_' . $res_id, wp_salt('auth')), 0, 24);
}

function wowthai_res_cancel_url(int $res_id): string {
    return add_query_arg(
        [
            'res_cancel' => $res_id,
            't'          => wowthai_res_cancel_token($res_id),
        ],
        home_url('/reservations/')
    );
}

function wowthai_res_ics_url(int $res_id): string {
    return add_query_arg(
        [
            'action' => 'wowthai_res_ics',
            'rid'    => $res_id,
            'k'      => wowthai_res_token($res_id),
        ],
        admin_url('admin-post.php')
    );
}

function wowthai_res_redirect_error(string $code): void {
    $url = add_query_arg('res_err', $code, home_url('/reservations/'));
    wp_safe_redirect($url);
    exit;
}

function wowthai_res_error_message(string $code): string {
    $messages = [
        'nonce'   => 'Sicherheitsprüfung abgelaufen. Bitte senden Sie das Formular erneut ab.',
        'rate'    => 'Zu viele Anfragen in kurzer Zeit. Bitte warten Sie einige Minuten oder rufen Sie uns direkt unter +43 7252 74973 an.',
        'consent' => 'Bitte bestätigen Sie die Datenschutzerklärung (DSGVO), um fortzufahren.',
        'fields'  => 'Bitte füllen Sie alle Pflichtfelder (Name, gültige Telefonnummer, Datum, Uhrzeit) korrekt aus.',
        'email'   => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
        'date'    => 'Bitte wählen Sie ein gültiges Reservierungsdatum (ab heute bis maximal 90 Tage im Voraus).',
        'closed'  => 'Am gewählten Tag ist unser Restaurant geschlossen. Bitte wählen Sie einen anderen Tag.',
        'slot'    => 'Die gewählte Uhrzeit liegt außerhalb unserer Reservierungszeiten oder ist bereits verstrichen. Bitte wählen Sie eine gültige Uhrzeit.',
        'party'   => 'Für Gruppen über 12 Personen bitten wir um direkte telefonische Reservierung unter +43 7252 74973.',
        'save'    => 'Fehler beim Speichern der Anfrage. Bitte versuchen Sie es erneut oder rufen Sie uns direkt an.',
    ];
    return $messages[$code] ?? '';
}

/**
 * Validiert und verarbeitet eine neue Tischreservierung (via admin-post.php oder Fallback-POST).
 */
function wowthai_handle_reservation_post(): void {
    $nonce = isset($_POST['wowthai_reservation_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['wowthai_reservation_nonce']))
        : '';
    if (!wp_verify_nonce($nonce, 'wowthai_make_reservation')) {
        wowthai_res_redirect_error('nonce');
    }

    // 1. Honeypot check (if filled by a bot, silently pretend success)
    $honeypot = isset($_POST['website']) ? trim(sanitize_text_field(wp_unslash($_POST['website']))) : '';
    if ($honeypot !== '') {
        wp_safe_redirect(add_query_arg(['res' => 'ok', 'bot' => '1'], home_url('/reservations/')));
        exit;
    }

    // 2. Privacy-preserving IP rate limit (max 4 submissions per 10 minutes)
    $raw_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0.0.0.0';
    $rl_key = 'wowthai_res_rl_' . substr(hash('sha256', $raw_ip . wp_salt('auth')), 0, 20);
    $count  = (int) get_transient($rl_key);
    if ($count >= 4) {
        wowthai_res_redirect_error('rate');
    }

    // 3. DSGVO consent check
    $consent = isset($_POST['res_consent']) ? sanitize_text_field(wp_unslash($_POST['res_consent'])) : '';
    if ($consent !== '1' && $consent !== 'on') {
        wowthai_res_redirect_error('consent');
    }

    // 4. Input extraction & validation
    $name  = isset($_POST['res_name']) ? trim(sanitize_text_field(wp_unslash($_POST['res_name']))) : '';
    $phone = isset($_POST['res_phone']) ? trim(sanitize_text_field(wp_unslash($_POST['res_phone']))) : '';
    $email = isset($_POST['res_email']) ? trim(sanitize_email(wp_unslash($_POST['res_email']))) : '';
    $date  = isset($_POST['res_date']) ? trim(sanitize_text_field(wp_unslash($_POST['res_date']))) : '';
    $time  = isset($_POST['res_time']) ? trim(sanitize_text_field(wp_unslash($_POST['res_time']))) : '';
    $party = isset($_POST['res_party']) ? absint($_POST['res_party']) : 2;
    $notes = isset($_POST['res_notes']) ? trim(sanitize_textarea_field(wp_unslash($_POST['res_notes']))) : '';

    if ($name === '' || mb_strlen($name) > 80 || !preg_match('/^[0-9+()\/\s-]{6,25}$/', $phone)) {
        wowthai_res_redirect_error('fields');
    }
    if ($email !== '' && !is_email($email)) {
        wowthai_res_redirect_error('email');
    }
    if ($party < 1 || $party > 12) {
        wowthai_res_redirect_error('party');
    }

    // 5. Date & Opening Hours Slot Validation in Europe/Vienna
    $tz  = wowthai_timezone();
    $now = wowthai_now();
    $date_obj = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz);
    if (!$date_obj || $date_obj->format('Y-m-d') !== $date) {
        wowthai_res_redirect_error('date');
    }
    $today_ymd = $now->format('Y-m-d');
    $max_ymd   = $now->modify('+90 days')->format('Y-m-d');
    if ($date < $today_ymd || $date > $max_ymd) {
        wowthai_res_redirect_error('date');
    }
    if (wowthai_get_special_closed_day($date_obj) !== null) {
        wowthai_res_redirect_error('closed');
    }
    $valid_slots = wowthai_reservation_slots($date_obj, $now);
    if (!in_array($time, $valid_slots, true)) {
        wowthai_res_redirect_error('slot');
    }

    // 6. Idempotency check: prevent duplicate bookings for the same phone + date + time
    $existing = get_posts([
        'post_type'      => 'tischanfrage',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [
            'relation' => 'AND',
            ['key' => '_res_phone', 'value' => $phone],
            ['key' => '_res_date',  'value' => $date],
            ['key' => '_res_time',  'value' => $time],
        ],
    ]);
    if (!empty($existing)) {
        $existing_id = (int) $existing[0];
        wp_safe_redirect(
            add_query_arg(
                [
                    'res' => 'ok',
                    'rid' => $existing_id,
                    'k'   => wowthai_res_token($existing_id),
                ],
                home_url('/reservations/')
            )
        );
        exit;
    }

    set_transient($rl_key, $count + 1, 10 * MINUTE_IN_SECONDS);

    // 7. Create tischanfrage post
    $formatted_date = wowthai_format_date_vienna($date, 'D, d. M Y');
    $res_id = wp_insert_post([
        'post_title'   => "{$name} · {$party} Pers. · {$date} {$time}",
        'post_content' => $notes,
        'post_status'  => 'publish',
        'post_type'    => 'tischanfrage',
    ]);

    if (!$res_id || is_wp_error($res_id)) {
        wowthai_res_redirect_error('save');
    }

    update_post_meta($res_id, '_res_name', $name);
    update_post_meta($res_id, '_res_phone', $phone);
    update_post_meta($res_id, '_res_email', $email);
    update_post_meta($res_id, '_res_date', $date);
    update_post_meta($res_id, '_res_time', $time);
    update_post_meta($res_id, '_res_party', $party);
    update_post_meta($res_id, '_res_notes', $notes);
    update_post_meta($res_id, '_res_status', 'pending');
    update_post_meta($res_id, '_res_consent_at', $now->format('c'));

    // 8. Send branded HTML notification email to restaurant (safe against dev-email@wpengine.local)
    $to = function_exists('wowthai_get_notification_email')
        ? wowthai_get_notification_email()
        : get_option('admin_email');
    $dashboard_url = admin_url('edit.php?post_type=tischanfrage');
    $subject = "🔔 Neue Reservierungsanfrage: {$name} ({$party} Pers. am {$date} um {$time} Uhr)";

    $admin_body = "
    <p>Eine neue Tischreservierungsanfrage ist über die Website eingegangen:</p>
    <div class=\"card-box\">
        <p style=\"margin:0 0 6px 0;\"><strong>Gast:</strong> " . esc_html($name) . "</p>
        <p style=\"margin:0 0 6px 0;\"><strong>Personen:</strong> " . esc_html((string) $party) . " Personen</p>
        <p style=\"margin:0 0 6px 0;\"><strong>Datum &amp; Zeit:</strong> " . esc_html($formatted_date) . " um " . esc_html($time) . " Uhr</p>
        <p style=\"margin:0 0 6px 0;\"><strong>Telefon:</strong> <a href=\"tel:" . esc_attr($phone) . "\" style=\"color:#d4a359;\">" . esc_html($phone) . "</a></p>
        <p style=\"margin:0 0 6px 0;\"><strong>E-Mail:</strong> " . esc_html($email ?: 'Keine Angabe') . "</p>
        <p style=\"margin:0;\"><strong>Wünsche:</strong> " . esc_html($notes ?: 'Keine Anmerkungen') . "</p>
    </div>
    <p style=\"font-size:13px;color:#999;\">Sie können diese Reservierung direkt mit einem Klick im WordPress-Dashboard annehmen oder bearbeiten.</p>
    ";

    if (function_exists('wowthai_send_branded_email')) {
        wowthai_send_branded_email($to, $subject, 'Neue Tischreservierung', $admin_body, $dashboard_url, 'In Dashboard verwalten ↗');
    } else {
        wp_mail($to, $subject, wp_strip_all_tags($admin_body));
    }

    // 9. Send branded HTML confirmation email to Guest (including 1-click self-cancel link)
    if ($email !== '' && is_email($email)) {
        $cancel_url = wowthai_res_cancel_url((int) $res_id);
        $guest_subject = 'Ihre Reservierungsanfrage bei WOW Thai Steyr';
        $guest_body = "
        <p>Guten Tag " . esc_html($name) . ",</p>
        <p>vielen Dank für Ihre Reservierungsanfrage! Wir haben Ihre Angaben erhalten und prüfen die Tischverfügbarkeit:</p>
        <div class=\"card-box\">
            <p style=\"margin:0 0 6px 0;\"><strong>Datum:</strong> " . esc_html($formatted_date) . "</p>
            <p style=\"margin:0 0 6px 0;\"><strong>Uhrzeit:</strong> " . esc_html($time) . " Uhr</p>
            <p style=\"margin:0 0 6px 0;\"><strong>Gästeanzahl:</strong> " . esc_html((string) $party) . " Personen</p>
            <p style=\"margin:0;\"><strong>Ort:</strong> City Point Steyr (1. OG), Leopold-Werndl-Straße 2</p>
        </div>
        <p>Unser Team prüft Ihre Anfrage. Sobald Ihr Tisch fest reserviert ist, erhalten Sie eine Bestätigung von uns.</p>
        <p style=\"font-size:13px;color:#a39c8e;\">Sollte sich Ihr Plan ändern, können Sie Ihre Anfrage jederzeit online stornieren: <a href=\"" . esc_url($cancel_url) . "\" style=\"color:#d4a359;\">Reservierung stornieren</a>.</p>
        <p>Wir freuen uns darauf, Sie bald mit authentischer thailändischer Küche begrüßen zu dürfen!</p>
        ";

        if (function_exists('wowthai_send_branded_email')) {
            wowthai_send_branded_email($email, $guest_subject, 'Reservierung eingegangen', $guest_body, home_url('/menu/'), 'Schon jetzt Speisekarte ansehen ↗');
        }
    }

    wp_safe_redirect(
        add_query_arg(
            [
                'res' => 'ok',
                'rid' => $res_id,
                'k'   => wowthai_res_token((int) $res_id),
            ],
            home_url('/reservations/')
        )
    );
    exit;
}
add_action('admin_post_nopriv_wowthai_reserve', 'wowthai_handle_reservation_post');
add_action('admin_post_wowthai_reserve', 'wowthai_handle_reservation_post');

/**
 * Verarbeitet eine Gast-Stornierung per HMAC-Token.
 */
function wowthai_handle_guest_cancel_post(): void {
    $res_id = isset($_POST['res_cancel_id']) ? absint($_POST['res_cancel_id']) : 0;
    $token  = isset($_POST['res_cancel_token']) ? sanitize_text_field(wp_unslash($_POST['res_cancel_token'])) : '';

    if (!$res_id || !hash_equals(wowthai_res_cancel_token($res_id), $token)) {
        wowthai_res_redirect_error('nonce');
    }

    $post = get_post($res_id);
    if (!$post || $post->post_type !== 'tischanfrage') {
        wowthai_res_redirect_error('nonce');
    }

    $current_status = (string) get_post_meta($res_id, '_res_status', true);
    if ($current_status !== 'cancelled') {
        update_post_meta($res_id, '_res_status', 'cancelled');
        $name  = (string) get_post_meta($res_id, '_res_name', true);
        $date  = (string) get_post_meta($res_id, '_res_date', true);
        $time  = (string) get_post_meta($res_id, '_res_time', true);
        $party = (string) get_post_meta($res_id, '_res_party', true);

        $to = function_exists('wowthai_get_notification_email')
            ? wowthai_get_notification_email()
            : get_option('admin_email');
        $subject = "✕ Reservierung vom Gast storniert: {$name} ({$date} {$time} Uhr)";
        $body = "<p>Der Gast <strong>" . esc_html($name) . "</strong> hat seine Reservierung für <strong>" . esc_html($date) . " um " . esc_html($time) . " Uhr</strong> (" . esc_html($party) . " Pers.) über den Storno-Link abgesagt.</p>";
        if (function_exists('wowthai_send_branded_email')) {
            wowthai_send_branded_email($to, $subject, 'Reservierung storniert', $body, admin_url('edit.php?post_type=tischanfrage'), 'Zur Übersicht ↗');
        }
    }

    wp_safe_redirect(add_query_arg('res_cancelled', '1', home_url('/reservations/')));
    exit;
}
add_action('admin_post_nopriv_wowthai_res_cancel', 'wowthai_handle_guest_cancel_post');
add_action('admin_post_wowthai_res_cancel', 'wowthai_handle_guest_cancel_post');

/**
 * Generiert eine .ics Kalenderdatei (Europe/Vienna) für Apple/Outlook/Smartphone-Kalender.
 */
function wowthai_handle_reservation_ics(): void {
    $res_id = isset($_GET['rid']) ? absint($_GET['rid']) : 0;
    $token  = isset($_GET['k']) ? sanitize_text_field(wp_unslash($_GET['k'])) : '';

    if (!$res_id || !hash_equals(wowthai_res_token($res_id), $token)) {
        wp_die('Ungültiger Kalender-Link.', 'Fehler', ['response' => 403]);
    }

    $post = get_post($res_id);
    if (!$post || $post->post_type !== 'tischanfrage') {
        wp_die('Reservierung nicht gefunden.', 'Fehler', ['response' => 404]);
    }

    $date  = (string) get_post_meta($res_id, '_res_date', true);
    $time  = (string) get_post_meta($res_id, '_res_time', true);
    $party = (int) get_post_meta($res_id, '_res_party', true);

    try {
        $start_dt = new DateTimeImmutable($date . ' ' . $time . ':00', wowthai_timezone());
    } catch (Exception $e) {
        $start_dt = wowthai_now();
    }
    $end_dt   = $start_dt->modify('+2 hours');
    $now_utc  = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Ymd\THis\Z');
    $dt_start = $start_dt->format('Ymd\THis');
    $dt_end   = $end_dt->format('Ymd\THis');

    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//WOW Thai Steyr//Tischreservierung//DE',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        'UID:wowthai-res-' . $res_id . '@wow-thai-steyr.at',
        'DTSTAMP:' . $now_utc,
        'DTSTART;TZID=Europe/Vienna:' . $dt_start,
        'DTEND;TZID=Europe/Vienna:' . $dt_end,
        'SUMMARY:Tischreservierung WOW Thai Steyr (' . $party . ' Pers.)',
        'LOCATION:WOW Thai Steyr\\, City Point 1. OG\\, Leopold-Werndl-Straße 2\\, 4400 Steyr',
        'DESCRIPTION:Ihre Tischreservierung bei WOW Thai Steyr fuer ' . $party . ' Personen. Tel: +43 7252 74973',
        'STATUS:CONFIRMED',
        'END:VEVENT',
        'END:VCALENDAR',
    ];

    nocache_headers();
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="wow-thai-reservierung-' . $res_id . '.ics"');
    echo implode("\r\n", $lines) . "\r\n";
    exit;
}
add_action('admin_post_nopriv_wowthai_res_ics', 'wowthai_handle_reservation_ics');
add_action('admin_post_wowthai_res_ics', 'wowthai_handle_reservation_ics');

/**
 * DSGVO Speicherbegrenzung: Automatische Bereinigung abgelaufener Reservierungen (> 90 Tage).
 */
function wowthai_schedule_res_cleanup(): void {
    if (!wp_next_scheduled('wowthai_daily_res_cleanup')) {
        wp_schedule_event( current_time('timestamp'), 'daily', 'wowthai_daily_res_cleanup' ); // verify-ok
    }
}
add_action('init', 'wowthai_schedule_res_cleanup');

function wowthai_run_res_cleanup(): void {
    $cutoff_ymd = wowthai_now()->modify('-90 days')->format('Y-m-d');
    $old_ids = get_posts([
        'post_type'      => 'tischanfrage',
        'post_status'    => 'any',
        'posts_per_page' => 50,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => '_res_date',
                'value'   => $cutoff_ymd,
                'compare' => '<',
                'type'    => 'CHAR',
            ],
        ],
    ]);
    foreach ($old_ids as $old_id) {
        wp_delete_post((int) $old_id, true);
    }
}
add_action('wowthai_daily_res_cleanup', 'wowthai_run_res_cleanup');
