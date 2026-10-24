<?php
/** Schedule persistence and public availability. Transaction records remain read only. */
if (!defined('ABSPATH')) exit;

function wowthai_hours_api_error(string $code, string $message, int $status = 400): WP_Error {
    return new WP_Error($code, $message, ['status' => $status]);
}

function wowthai_hours_api_can_edit(): bool {
    return current_user_can('manage_options') || current_user_can('manage_woocommerce');
}

function wowthai_hours_api_read_permission() {
    return wowthai_hours_api_can_edit() || current_user_can('edit_shop_orders') ? true : wowthai_hours_api_error('hours_forbidden', 'Keine Berechtigung.', is_user_logged_in() ? 403 : 401);
}

function wowthai_hours_api_write_permission($request) {
    if (!wowthai_hours_api_can_edit()) return wowthai_hours_api_error('hours_forbidden', 'Keine Berechtigung.', is_user_logged_in() ? 403 : 401);
    if (!wp_verify_nonce((string)$request->get_header('X-WP-Nonce'), 'wp_rest')) return wowthai_hours_api_error('hours_nonce', 'Sitzung abgelaufen. Bitte neu anmelden.', 403);
    return true;
}

function wowthai_hours_api_preview_permission($request) {
    $permission = wowthai_hours_api_read_permission();
    if (is_wp_error($permission)) return $permission;
    return wp_verify_nonce((string)$request->get_header('X-WP-Nonce'), 'wp_rest') ? true : wowthai_hours_api_error('hours_nonce', 'Sitzung abgelaufen. Bitte neu anmelden.', 403);
}

/** Round-trip validation prevents PHP normalizing impossible calendar dates. */
function wowthai_hours_api_date($value) {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) return wowthai_hours_api_error('hours_date', 'Bitte ein gültiges Datum wählen.');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, wowthai_timezone());
    return $date && $date->format('Y-m-d') === $value ? $date : wowthai_hours_api_error('hours_date', 'Bitte ein gültiges Datum wählen.');
}

/** Hash raw canonical options, including absent vs saved default. Reads never seed. */
function wowthai_hours_api_revision(): string {
    return hash('sha256', wp_json_encode([get_option('wowthai_weekly_hours', null), get_option('wowthai_special_days', []), get_option('wowthai_lunch_service', null)]));
}

function wowthai_hours_api_schedule() {
    $weekly = wowthai_hours_weekly_config();
    if (is_wp_error($weekly)) return wowthai_hours_api_error('hours_invalid_storage', 'Gespeicherter Wochenplan ist ungültig.', 503);
    $exceptions = get_option('wowthai_special_days', []);
    if (!is_array($exceptions)) return wowthai_hours_api_error('hours_invalid_storage', 'Gespeicherte Sondertage sind ungültig.', 503);
    $lunch = wowthai_lunch_validate(get_option('wowthai_lunch_service', wowthai_lunch_config()));
    if (is_wp_error($lunch)) return wowthai_hours_api_error('hours_invalid_storage', 'Gespeichertes Mittagsmenü ist ungültig.', 503);
    return ['lunch' => $lunch, 'weekly' => $weekly, 'exceptions' => $exceptions, 'revision' => wowthai_hours_api_revision(), 'can_edit' => wowthai_hours_api_can_edit(), 'timezone' => 'Europe/Vienna'];
}

/** Pure availability for live or normalized draft config; generic pickup never reads a cart. */
function wowthai_hours_api_for_date(DateTimeImmutable $date, array $weekly, array $exceptions, string $revision, ?array $lunch = null): array {
    $now = wowthai_now();
    $lunch = $lunch ?? wowthai_lunch_config();
    $pickup = wowthai_pickup_slots_for_date($date, $now, false, false, ['weekly'=>$weekly,'exceptions'=>$exceptions]);
    $valid_lunch_window = wowthai_hours_datetime_for_local_time($date,$lunch['range'][0]) && wowthai_hours_datetime_for_local_time($date,$lunch['range'][1]);
    $lunch_slots = $valid_lunch_window ? array_values(array_filter($pickup, static fn($hm)=>in_array((int)$date->format('N'),$lunch['days'],true) && $hm >= $lunch['range'][0] && $hm <= $lunch['range'][1])) : [];
    $lunch_day = wowthai_lunch_available_on_date($date,$lunch,$weekly,$exceptions);
    return ['lunch_available_on_date'=>$lunch_day, 'm4_available_on_date'=>$lunch_day && in_array((int)$date->format('N'),WOWTHAI_MITTAGSMENU_M4_DAYS,true), 'lunch_slots'=>$lunch_slots, 'mittags_note'=>wowthai_lunch_note($lunch), 'mittags_m4_note'=>wowthai_lunch_note($lunch,true), 'date' => $date->format('Y-m-d'), 'timezone' => 'Europe/Vienna',
        'ranges' => wowthai_hours_ranges_for_config($date, $weekly, $exceptions),
        'reservation_slots' => wowthai_reservation_slots($date, $now, $weekly, $exceptions),
        'pickup_slots' => $pickup,
        'display_rows' => wowthai_hours_grouped_rows($weekly), 'opening_status' => wowthai_compute_opening_status($now, $weekly, $exceptions), 'revision' => $revision,
        // The same public projection also rehydrates cached Restaurant JSON-LD.
        'schema_opening_hours' => wowthai_schema_opening_hours($weekly)];
}

function wowthai_hours_api_availability($request) {
    $date = wowthai_hours_api_date($request['date']);
    if (is_wp_error($date)) return $date;
    $today = wowthai_now()->setTime(0, 0);
    if ($date < $today || $date > $today->modify('+90 days')) return wowthai_hours_api_error('hours_date_range', 'Datum muss innerhalb der nächsten 90 Tage liegen.');
    // Public resolver fails closed for a corrupt saved weekly plan.
    $exceptions = get_option('wowthai_special_days', []);
    if (!is_array($exceptions)) return wowthai_hours_api_error('hours_invalid_storage', 'Öffnungszeiten derzeit nicht verfügbar.', 503);
    try {
        return wowthai_hours_api_for_date($date, wowthai_hours_schedule(), $exceptions, wowthai_hours_api_revision());
    } catch (Throwable $e) {
        return wowthai_hours_api_error('hours_dependencies', 'Öffnungszeiten derzeit nicht verfügbar.', 503);
    }
}

/** Normalize only the requested resource. Preserve every untouched legacy exception. */
function wowthai_hours_api_draft(array $body, array $schedule) {
    $kind = $body['kind'] ?? '';
    $weekly = $schedule['weekly']; $exceptions = $schedule['exceptions'];
    if ($kind === 'lunch') {
        $resource = wowthai_lunch_validate($body['lunch'] ?? null);
        if (is_wp_error($resource)) return $resource;
    } elseif ($kind === 'weekly') {
        $weekly = wowthai_hours_validate_weekly($body['weekly'] ?? null);
        if (is_wp_error($weekly)) return wowthai_hours_api_error('hours_weekly', 'Bitte einen gültigen Wochenplan eingeben.');
        $resource = $weekly;
    } elseif ($kind === 'exceptions') {
        $ex = $body['exception'] ?? null;
        if (!is_array($ex)) return wowthai_hours_api_error('hours_exception', 'Sondertag fehlt.');
        $start = wowthai_hours_api_date($ex['start_date'] ?? null); $end = wowthai_hours_api_date($ex['end_date'] ?? null);
        if (is_wp_error($start) || is_wp_error($end) || $end < $start || $start->diff($end)->days > 366) return wowthai_hours_api_error('hours_exception_dates', 'Bitte einen gültigen Zeitraum von höchstens 367 Tagen eingeben.');
        $mode = $ex['mode'] ?? '';
        if (!in_array($mode, ['closed', 'hours', 'remove'], true)) return wowthai_hours_api_error('hours_exception_mode', 'Ungültige Sondertag-Aktion.');
        $reason = $ex['reason'] ?? '';
        if (!is_string($reason) || strlen($reason) > 500) return wowthai_hours_api_error('hours_reason', 'Hinweis ist zu lang oder ungültig.');
        $entry = ['closed' => $mode === 'closed', 'reason' => sanitize_text_field($reason)];
        if ($mode === 'hours') {
            $ranges = wowthai_hours_validate_ranges($ex['ranges'] ?? null);
            if (is_wp_error($ranges) || !$ranges) return wowthai_hours_api_error('hours_exception_ranges', 'Bitte gültige Sonderöffnungszeiten eingeben.');
            $entry['ranges'] = $ranges;
        }
        for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
            $key = $date->format('Y-m-d');
            if ($mode === 'remove') unset($exceptions[$key]); else $exceptions[$key] = $entry;
        }
        ksort($exceptions); $resource = $exceptions;
    } else return wowthai_hours_api_error('hours_kind', 'Ungültiger Planbereich.');
    return ['kind' => $kind, 'weekly' => $weekly, 'exceptions' => $exceptions, 'resource' => $resource, 'lunch' => $kind === 'lunch' ? $resource : ($schedule['lunch'] ?? wowthai_lunch_config())];
}

/** Parse explicit service metadata only. Never substitute an order's creation date. */
function wowthai_hours_api_parse_service(string $label, string $legacy_date = '', string $legacy_time = ''): ?array {
    if ($label !== '') {
        $months = ['Januar'=>1,'Jänner'=>1,'Februar'=>2,'März'=>3,'April'=>4,'Mai'=>5,'Juni'=>6,'Juli'=>7,'August'=>8,'September'=>9,'Oktober'=>10,'November'=>11,'Dezember'=>12, 'January'=>1, 'February'=>2, 'March'=>3, 'May'=>5, 'June'=>6, 'July'=>7, 'October'=>10, 'December'=>12];
        if (preg_match('/^(?:[\p{L}]+, )?(\d{1,2})\. ([\p{L}]+) (\d{4}) um (\d{2}:\d{2}) Uhr$/uD', $label, $m) && isset($months[$m[2]])) {
            $legacy_date = sprintf('%04d-%02d-%02d', $m[3], $months[$m[2]], $m[1]); $legacy_time = $m[4];
        } elseif (preg_match('/^(\d{4}-\d{2}-\d{2}) (?:um )?(\d{2}:\d{2})(?: Uhr)?$/D', $label, $m)) {
            $legacy_date = $m[1]; $legacy_time = $m[2];
        } else return null; // ASAP or malformed canonical data must remain unknown.
    }
    if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/D', $legacy_date, $m)) $legacy_date = $m[3].'-'.$m[2].'-'.$m[1];
    $date = wowthai_hours_api_date($legacy_date);
    if (is_wp_error($date) || wowthai_hours_datetime_for_local_time($date, $legacy_time) === null) return null;
    return [$legacy_date, $legacy_time];
}

/** Existing service commitments compare against cutoffs, never a moving now+lead time. */
function wowthai_hours_api_service_fits(string $kind, array $service, array $draft): bool {
    $date = wowthai_hours_api_date($service[0]);
    if (($draft['kind'] ?? '') === 'lunch' && (!in_array((int)$date->format('N'),$draft['lunch']['days'],true) || $service[1] < $draft['lunch']['range'][0] || $service[1] > $draft['lunch']['range'][1])) return false;
    $ranges = wowthai_hours_ranges_for_config($date, $draft['weekly'], $draft['exceptions']);
    $instant = wowthai_hours_datetime_for_local_time($date, $service[1]);
    foreach ($ranges as $index => [$start, $end]) {
        $offset = $kind === 'reservation' ? WOWTHAI_LAST_RESERVATION_OFFSET_MIN : ($index === 0 && count($ranges) > 1 ? 15 : 30);
        $last = wowthai_hours_datetime_for_local_time($date, $end)->modify('-'.$offset.' minutes');
        if ($instant >= wowthai_hours_datetime_for_local_time($date, $start) && $instant <= $last) return true;
    }
    return false;
}

/** Read all active commitments via storage-independent Woo CRUD and private booking meta. */
function wowthai_hours_api_impact(array $draft): array {
    if (!function_exists('wc_get_orders') || !class_exists('WP_Query')) throw new RuntimeException('Terminprüfung derzeit nicht verfügbar.');
    $conflicts = []; $signature = []; $today = wowthai_now()->format('Y-m-d');
    $add = static function ($kind, $id, $status, $service, $version) use (&$conflicts, &$signature, $today, $draft) {
        if ($service !== null && $service[0] < $today) return;
        $row = ['kind'=>$kind, 'id'=>(int)$id, 'date'=>$service[0] ?? '', 'time'=>$service[1] ?? '', 'status'=>$status, 'unknown'=>$service === null];
        // Bind all relevant records, including a previously fitting record that changes after preview.
        $signature[] = [$row, $version];
        // Lunch membership of historical items cannot be inferred from mutable products.
        // Require review of all active orders, without altering any transaction.
        if ($service === null || ($draft['kind'] ?? '') === 'lunch' || !wowthai_hours_api_service_fits($kind, $service, $draft)) $conflicts[] = $row;
    };
    for ($page = 1; ; $page++) {
        $batch = wc_get_orders(['type'=>'shop_order', 'status'=>['pending','on-hold','processing'], 'limit'=>100, 'page'=>$page, 'paginate'=>false, 'orderby'=>'ID', 'order'=>'ASC']);
        if (!is_array($batch)) throw new RuntimeException('Bestellprüfung derzeit nicht verfügbar.');
        foreach ($batch as $order) {
            $label = trim((string)$order->get_meta('Abholzeit', true));
            // legacy-fallback: old explicit service metadata, never order creation time.
            $service = wowthai_hours_api_parse_service($label, trim((string)$order->get_meta('_wowthai_pickup_date', true)), trim((string)$order->get_meta('_wowthai_pickup_time', true))); // legacy-fallback
            $add('order', $order->get_id(), $order->get_status(), $service, [$order->get_date_modified() ? $order->get_date_modified()->format(DATE_ATOM) : '', $label, $order->get_meta('_wto_revision', true), $order->get_meta('_wowthai_accepted_time', true), $order->get_meta('_wowthai_ready_time', true), $order->get_meta('_wowthai_dispatched_time', true)]);
        }
        if (count($batch) < 100) break;
    }
    for ($page = 1; ($draft['kind'] ?? '') !== 'lunch'; $page++) {
        $query = new WP_Query(['post_type'=>'tischanfrage','post_status'=>'publish','posts_per_page'=>100,'paged'=>$page,'orderby'=>'ID','order'=>'ASC','no_found_rows'=>true]);
        foreach ($query->posts as $post) {
            $status = (string)get_post_meta($post->ID, '_res_status', true) ?: 'pending';
            if (!in_array($status, ['pending','confirmed'], true)) continue;
            $date = (string)get_post_meta($post->ID, '_res_date', true); $time = (string)get_post_meta($post->ID, '_res_time', true);
            // Bind the inspected Operations booking version inputs without returning contact data.
            $version = [$post->post_modified_gmt];
            foreach (['name','email','phone','date','time','party','notes','status'] as $key) $version[] = get_post_meta($post->ID, '_res_'.$key, true);
            $add('reservation', $post->ID, $status, wowthai_hours_api_parse_service('', $date, $time), hash('sha256', wp_json_encode($version)));
        }
        if (count($query->posts) < 100) break;
    }
    usort($signature, static fn($a,$b)=>strcmp($a[0]['kind'], $b[0]['kind']) ?: $a[0]['id'] <=> $b[0]['id']);
    return ['conflicts'=>$conflicts, 'signature'=>hash('sha256', wp_json_encode($signature))];
}

function wowthai_hours_api_token(array $draft, string $revision, string $signature, int $expires): string {
    $digest = hash_hmac('sha256', wp_json_encode([$draft['kind'], $draft['resource'], $revision, $signature, $expires]), wp_salt('auth'));
    return $expires.'.'.$digest;
}

function wowthai_hours_api_preview($request) {
    $body = $request->get_json_params();
    if (!is_array($body)) return wowthai_hours_api_error('hours_payload', 'Ungültige Anfrage.');
    $schedule = wowthai_hours_api_schedule(); if (is_wp_error($schedule)) return $schedule;
    if (($body['revision'] ?? '') !== $schedule['revision']) return wowthai_hours_api_error('stale_schedule', 'Plan wurde geändert. Bitte neu laden.', 409);
    $draft = wowthai_hours_api_draft($body, $schedule); if (is_wp_error($draft)) return $draft;
    $date = wowthai_hours_api_date($body['preview_date'] ?? wowthai_now()->format('Y-m-d')); if (is_wp_error($date)) return $date;
    try { $impact = wowthai_hours_api_impact($draft); } catch (Throwable $e) { return wowthai_hours_api_error('hours_dependencies', 'Terminprüfung derzeit nicht verfügbar.', 503); }
    return ['revision'=>$schedule['revision'], 'confirmation_token'=>wowthai_hours_api_token($draft, $schedule['revision'], $impact['signature'], wowthai_now()->getTimestamp()+600), 'conflicts'=>$impact['conflicts'], 'preview'=>wowthai_hours_api_for_date($date, $draft['weekly'], $draft['exceptions'], $schedule['revision'], $draft['lunch'])];
}

/** Connection-scoped lock cannot be stolen by an expired transient lease. */
function wowthai_hours_api_lock_name(): string {
    global $wpdb;
    return 'wowthai_hours_'.substr(hash('sha256', $wpdb->prefix.':'.get_current_blog_id()), 0, 48);
}

function wowthai_hours_api_refresh_options(): void {
    foreach (['wowthai_weekly_hours','wowthai_special_days','wowthai_lunch_service','alloptions','notoptions'] as $key) wp_cache_delete($key, 'options');
}

function wowthai_hours_api_save($request, string $kind) {
    global $wpdb;
    $permission = wowthai_hours_api_write_permission($request); if (is_wp_error($permission)) return $permission;
    $body = $request->get_json_params();
    if (!is_array($body) || ($body['kind'] ?? $kind) !== $kind) return wowthai_hours_api_error('hours_payload', 'Ungültige Anfrage.');
    $body['kind'] = $kind; $lock = wowthai_hours_api_lock_name();
    if ((string)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock)) !== '1') return wowthai_hours_api_error('hours_busy', 'Plan wird gerade gespeichert. Bitte erneut versuchen.', 503);
    try {
        wowthai_hours_api_refresh_options();
        $schedule = wowthai_hours_api_schedule(); if (is_wp_error($schedule)) return $schedule;
        if (($body['revision'] ?? '') !== $schedule['revision']) return wowthai_hours_api_error('stale_schedule', 'Plan wurde geändert. Bitte erneut prüfen.', 409);
        $draft = wowthai_hours_api_draft($body, $schedule); if (is_wp_error($draft)) return $draft;
        $impact = wowthai_hours_api_impact($draft);
        $token = $body['confirmation_token'] ?? ''; $expires = is_string($token) ? explode('.', $token)[0] : '';
        $now = wowthai_now()->getTimestamp();
        if (!ctype_digit($expires) || (int)$expires < $now || (int)$expires > $now+600 || !hash_equals(wowthai_hours_api_token($draft,$schedule['revision'],$impact['signature'],(int)$expires), $token)) return wowthai_hours_api_error('stale_preview', 'Vorschau ist abgelaufen oder Termine wurden geändert. Bitte erneut prüfen.', 409);
        if ($impact['conflicts'] && ($body['acknowledge_conflicts'] ?? false) !== true) return wowthai_hours_api_error('hours_acknowledgement_required', 'Betroffene Termine bitte ausdrücklich bestätigen.');
        $option = $kind === 'lunch' ? 'wowthai_lunch_service' : ($kind === 'weekly' ? 'wowthai_weekly_hours' : 'wowthai_special_days');
        $stored = get_option($option, null);
        if ($stored !== $draft['resource'] && !update_option($option, $draft['resource'], false)) return wowthai_hours_api_error('hours_storage_failed', 'Plan konnte nicht gespeichert werden.', 503);
        wowthai_hours_api_refresh_options();
        if (get_option($option, null) !== $draft['resource']) return wowthai_hours_api_error('hours_storage_failed', 'Gespeicherter Plan konnte nicht bestätigt werden.', 503);
        wowthai_hours_api_invalidate_public();
        return wowthai_hours_api_schedule();
    } catch (Throwable $e) {
        return wowthai_hours_api_error('hours_dependencies', 'Speichern oder Terminprüfung derzeit nicht verfügbar.', 503);
    } finally {
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
    }
}

/** WordPress cache hooks only; no claim of global host/CDN eviction. */
function wowthai_hours_api_invalidate_public(): void {
    $ids = [(int)get_option('page_on_front', 0)];
    foreach (['menu','reservations','checkout'] as $path) { $page = get_page_by_path($path); if ($page) $ids[] = (int)$page->ID; }
    foreach (array_unique(array_filter($ids)) as $id) clean_post_cache($id);
    do_action('wowthai_hours_saved', wowthai_hours_api_revision());
}

function wowthai_hours_api_response($result) {
    if (is_wp_error($result)) return $result;
    $response = new WP_REST_Response($result);
    $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    return $response;
}
add_action('rest_api_init', static function () {
    register_rest_route('wowthai-hours/v1', '/schedule', ['methods'=>'GET','permission_callback'=>'wowthai_hours_api_read_permission','callback'=>static fn()=>wowthai_hours_api_response(wowthai_hours_api_schedule())]);
    register_rest_route('wowthai-hours/v1', '/availability', ['methods'=>'GET','permission_callback'=>'__return_true','callback'=>static fn($r)=>wowthai_hours_api_response(wowthai_hours_api_availability($r))]);
    register_rest_route('wowthai-hours/v1', '/schedule/preview', ['methods'=>'POST','permission_callback'=>'wowthai_hours_api_preview_permission','callback'=>static fn($r)=>wowthai_hours_api_response(wowthai_hours_api_preview($r))]);
    foreach (['weekly','exceptions','lunch'] as $kind) register_rest_route('wowthai-hours/v1', '/schedule/'.$kind, ['methods'=>'PUT','permission_callback'=>'wowthai_hours_api_write_permission','callback'=>static fn($r)=>wowthai_hours_api_response(wowthai_hours_api_save($r,$kind))]);
});
// Include errors too: a cached failed availability response must never become authoritative.
add_filter('rest_post_dispatch', static function ($response, $server, $request) {
    if (str_starts_with($request->get_route(), '/wowthai-hours/v1/')) $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    return $response;
}, 10, 3);
