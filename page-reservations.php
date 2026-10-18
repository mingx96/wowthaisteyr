<?php
/**
 * Template Name: Tisch reservieren
 */
if (!defined('ABSPATH')) {
    exit;
}

$has_reservations = !function_exists('wowthai_branch_has_reservations') || wowthai_branch_has_reservations();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wowthai_reservation_nonce'])) {
    if (function_exists('wowthai_handle_reservation_post')) {
        wowthai_handle_reservation_post();
    }
}

get_header();

$wow_hd   = function_exists('wowthai_hours_display') ? wowthai_hours_display() : [];
$wow_info = function_exists('wowthai_get_restaurant_data') ? wowthai_get_restaurant_data() : [];
$wow_tel  = $wow_info['phone_tel'] ?? '+43725274973';
$wow_ph   = $wow_info['phone'] ?? '+43 7252 74973';

$now_vienna = wowthai_now();
$today_ymd  = $now_vienna->format('Y-m-d');
$max_ymd    = $now_vienna->modify('+90 days')->format('Y-m-d');

// Default to today if slots remain today, otherwise tomorrow
$default_date_obj = $now_vienna;
$initial_slots    = wowthai_reservation_slots($default_date_obj, $now_vienna);
if (empty($initial_slots)) {
    $default_date_obj = $now_vienna->modify('+1 day');
    $initial_slots    = wowthai_reservation_slots($default_date_obj, $now_vienna);
}
$default_date_ymd = $default_date_obj->format('Y-m-d');
$slots_by_weekday = wowthai_reservation_slots_by_weekday();
$today_slots      = wowthai_reservation_slots($now_vienna, $now_vienna);

$res_ok        = isset($_GET['res']) && sanitize_text_field(wp_unslash($_GET['res'])) === 'ok';
$res_bot       = isset($_GET['bot']) && sanitize_text_field(wp_unslash($_GET['bot'])) === '1';
$res_id        = isset($_GET['rid']) ? absint($_GET['rid']) : 0;
$res_token     = isset($_GET['k']) ? sanitize_text_field(wp_unslash($_GET['k'])) : '';
$res_err_code  = isset($_GET['res_err']) ? sanitize_text_field(wp_unslash($_GET['res_err'])) : '';
$booking_error = $res_err_code !== '' && function_exists('wowthai_res_error_message')
    ? wowthai_res_error_message($res_err_code)
    : '';

$cancel_req_id    = isset($_GET['res_cancel']) ? absint($_GET['res_cancel']) : 0;
$cancel_req_token = isset($_GET['t']) ? sanitize_text_field(wp_unslash($_GET['t'])) : '';
$is_cancelled_ok  = isset($_GET['res_cancelled']) && sanitize_text_field(wp_unslash($_GET['res_cancelled'])) === '1';

$booking_submitted = false;
$confirmed_data    = null;
if ($res_ok && $res_bot) {
    $booking_submitted = true;
    $confirmed_data = [
        'date'  => $default_date_ymd,
        'time'  => $initial_slots[0] ?? '',
        'party' => 2,
        'id'    => 0,
    ];
} elseif ($res_ok && $res_id > 0 && function_exists('wowthai_res_token') && hash_equals(wowthai_res_token($res_id), $res_token)) {
    $booking_submitted = true;
    $confirmed_data = [
        'id'    => $res_id,
        'date'  => (string) get_post_meta($res_id, '_res_date', true),
        'time'  => (string) get_post_meta($res_id, '_res_time', true),
        'party' => (int) get_post_meta($res_id, '_res_party', true),
    ];
}
?>

<main id="main" tabindex="-1">
  <section class="page-intro wrap reservation-intro">
    <p class="eyebrow"><span class="eyebrow-dot"></span> <?php echo esc_html( mb_strtoupper( $wow_info['brand_sub'] ?? 'WOW THAI', 'UTF-8' ) ); ?></p>
    <div class="page-intro-row">
      <div>
        <h1>Ein Tisch für <em>Sie.</em></h1>
        <?php if (!$has_reservations): ?>
          <p>Online-Tischreservierungen sind für diesen Standort nicht verfügbar. Bitte nutzen Sie unseren Lieferservice.</p>
        <?php else: ?>
          <p>Planen Sie Ihren Besuch bei uns. Wählen Sie Datum, Uhrzeit und Personenzahl – wir freuen uns auf Ihren Besuch im Restaurant <?php echo esc_html( $wow_info['name'] ?? 'WOW Thai' ); ?>.</p>
        <?php endif; ?>
      </div>
      <a class="button button-quiet" href="<?php echo esc_url(home_url('/menu/')); ?>">Speisekarte ansehen <span aria-hidden="true">↗</span></a>
    </div>
  </section>

  <section class="wrap reservation-layout">
    <div class="reservation-aside">
      <span class="aside-mark" aria-hidden="true">W</span>
      <p class="eyebrow">PERSÖNLICH FÜR SIE DA</p>
      <h2>Wir freuen uns<br>auf Ihren Besuch.</h2>
      <p>Fragen zu Ihrer Reservierung oder besondere Wünsche? Rufen Sie uns gern direkt an – wir beraten Sie persönlich.</p>
      <a class="phone-large" href="tel:<?php echo esc_attr($wow_tel); ?>"><?php echo esc_html($wow_ph); ?></a>

      <div class="aside-hours-hint" style="margin-top:20px;padding-top:16px;border-top:1px solid rgba(255,255,255,0.1);font-size:13px;color:var(--muted);line-height:1.6;">
        <strong style="color:var(--ink);display:block;margin-bottom:4px;">🕒 Öffnungszeiten Küche:</strong>
        <?php foreach (($wow_hd['rows'] ?? []) as $row): ?>
          <?php echo esc_html($row['days'] . ': ' . $row['hours']); ?><br>
        <?php endforeach; ?>
        <?php if ( ! empty( $wow_info['has_sushi'] ) ): ?>
          <span style="color:#d4a359;font-weight:600;">🍣 <?php echo esc_html($wow_hd['sushi_note'] ?? 'Montags Sushi-Ruhetag (warme Küche regulär)'); ?></span>
        <?php endif; ?>
      </div>

      <div class="aside-parking-hint" style="margin-top:16px;padding-top:16px;border-top:1px solid rgba(255,255,255,0.1);font-size:13px;color:var(--muted);line-height:1.6;">
        <strong style="color:var(--ink);display:block;margin-bottom:4px;">🚗 Parken &amp; Barrierefreiheit:</strong>
        <?php if ( ( function_exists('wowthai_get_branch_id') ? wowthai_get_branch_id() : ($wow_info['id'] ?? '') ) === 'steyr' ): ?>
          Tiefgarage City Point direkt im Haus verfügbar. Bequem mit dem Lift ins 1. Obergeschoss zu unserem Restaurant.
        <?php else: ?>
          Parkmöglichkeiten direkt vor Ort verfügbar. Bequem erreichbar für Ihren genussvollen Besuch.
        <?php endif; ?>
      </div>
    </div>

    <div class="reservation-panel" id="reservation-root">
      <?php if (!$has_reservations): ?>
        <div class="booking-success" role="status">
          <h2>Tischreservierungen nicht verfügbar</h2>
          <p>Online-Tischreservierungen sind für diesen Standort nicht verfügbar. Bitte nutzen Sie unseren Lieferservice.</p>
          <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;">
            <a class="button button-dark" href="<?php echo esc_url(home_url('/menu/')); ?>">Zur Speisekarte &amp; Online-Bestellung →</a>
          </div>
        </div>

      <?php elseif ($is_cancelled_ok): ?>
        <div class="booking-success" role="status">
          <h2>Ihre Reservierung wurde storniert.</h2>
          <p>Vielen Dank für Ihre Mitteilung. Wir haben Ihren Tisch wieder freigegeben und hoffen, Sie bald zu einem anderen Zeitpunkt bei uns begrüßen zu dürfen!</p>
          <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;">
            <a class="button button-dark" href="<?php echo esc_url(home_url('/reservations/')); ?>">Neue Reservierung anfragen →</a>
            <a class="button button-quiet" href="<?php echo esc_url(home_url('/menu/')); ?>">Speisekarte ansehen ↗</a>
          </div>
        </div>

      <?php elseif ($cancel_req_id > 0 && function_exists('wowthai_res_cancel_token') && hash_equals(wowthai_res_cancel_token($cancel_req_id), $cancel_req_token)):
        $c_date  = wowthai_format_date_vienna((string) get_post_meta($cancel_req_id, '_res_date', true), 'd.m.Y');
        $c_time  = (string) get_post_meta($cancel_req_id, '_res_time', true);
        $c_party = (int) get_post_meta($cancel_req_id, '_res_party', true);
      ?>
        <div class="booking-success">
          <h2>Reservierung stornieren?</h2>
          <p>Möchten Sie Ihre Tischreservierung für den <strong><?php echo esc_html($c_date); ?> um <?php echo esc_html($c_time); ?> Uhr</strong> (<?php echo esc_html((string) $c_party); ?> Personen) verbindlich absagen?</p>
          <form method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;">
            <input type="hidden" name="action" value="wowthai_res_cancel">
            <input type="hidden" name="res_cancel_id" value="<?php echo esc_attr((string) $cancel_req_id); ?>">
            <input type="hidden" name="res_cancel_token" value="<?php echo esc_attr($cancel_req_token); ?>">
            <button type="submit" class="button button-dark">Ja, Reservierung jetzt stornieren</button>
            <a class="button button-quiet" href="<?php echo esc_url(home_url('/reservations/')); ?>">Abbrechen</a>
          </form>
        </div>

      <?php elseif ($booking_submitted && $confirmed_data):
        $c_date  = $confirmed_data['date'];
        $c_time  = $confirmed_data['time'];
        $c_party = $confirmed_data['party'];
        $c_id    = (int) $confirmed_data['id'];
        try {
            $start_dt = new DateTimeImmutable($c_date . ' ' . $c_time . ':00', wowthai_timezone());
        } catch (Exception $e) {
            $start_dt = $now_vienna;
        }
        $end_dt    = $start_dt->modify('+2 hours');
        $start_iso = $start_dt->format('Ymd\THis');
        $end_iso   = $end_dt->format('Ymd\THis');
        $res_branch_id  = function_exists('wowthai_get_branch_id') ? wowthai_get_branch_id() : 'steyr';
        $res_branch_cfg = function_exists('wowthai_get_branch_config') ? wowthai_get_branch_config($res_branch_id) : [];
        $res_bname      = $res_branch_cfg['name'] ?? 'WOW Thai Steyr';
        $cal_title      = rawurlencode("Tischreservierung: {$res_bname} ({$c_party} Pers.)");
        $cal_desc       = rawurlencode("Ihre Reservierung bei {$res_bname} für {$c_party} Personen. Tel: {$wow_ph}");
        $full_addr      = trim(($res_branch_cfg['street'] ?? 'Leopold-Werndl-Straße 2') . ', ' . ($res_branch_cfg['postal_code'] ?? '4400') . ' ' . ($res_branch_cfg['city'] ?? 'Steyr'));
        $cal_loc        = rawurlencode($res_bname . ', ' . $full_addr);
        $cal_url        = "https://calendar.google.com/calendar/render?action=TEMPLATE&text={$cal_title}&dates={$start_iso}/{$end_iso}&ctz=Europe/Vienna&details={$cal_desc}&location={$cal_loc}";
        $ics_url   = $c_id > 0 ? wowthai_res_ics_url($c_id) : '';
        $disp_date = wowthai_format_date_vienna($c_date, 'd.m.Y');
      ?>
        <div class="booking-success" role="status">
          <h2>Vielen Dank für Ihre Reservierung!</h2>
          <p>Wir haben Ihre Anfrage für den <strong><?php echo esc_html($disp_date); ?> um <?php echo esc_html($c_time); ?> Uhr</strong> für <strong><?php echo esc_html((string) $c_party); ?> Personen</strong> erhalten.</p>
          <p>Wir freuen uns darauf, Sie bald im Restaurant begrüßen zu dürfen!</p>
          <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;">
            <a class="button button-dark" href="<?php echo esc_url(home_url('/menu/')); ?>">Schon jetzt Speisekarte ansehen ↗</a>
            <a class="button button-quiet" href="<?php echo esc_url($cal_url); ?>" target="_blank" rel="noopener">📅 Google Kalender ↗</a>
            <?php if ($ics_url !== ''): ?>
              <a class="button button-quiet" href="<?php echo esc_url($ics_url); ?>">📲 Apple / Outlook Kalender (.ics)</a>
            <?php endif; ?>
          </div>
        </div>

      <?php else: ?>
        <h2 class="booking-title">Tisch online reservieren</h2>
        <p class="booking-copy">Füllen Sie das Formular aus – unser Team prüft und bestätigt Ihre Reservierungsanfrage umgehend per E-Mail.</p>

        <?php if ($booking_error !== ''): ?>
          <p class="booking-error" role="alert"><?php echo esc_html($booking_error); ?></p>
        <?php endif; ?>

        <form class="booking-form" method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
          <input type="hidden" name="action" value="wowthai_reserve">
          <input type="hidden" name="res_branch_id" value="<?php echo esc_attr(function_exists('wowthai_get_branch_id') ? wowthai_get_branch_id() : 'steyr'); ?>">
          <?php wp_nonce_field('wowthai_make_reservation', 'wowthai_reservation_nonce'); ?>

          <!-- Honeypot field against automated spam bots -->
          <div style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
            <label for="res_website">Website</label>
            <input type="text" id="res_website" name="website" value="" tabindex="-1" autocomplete="off">
          </div>

          <div class="field">
            <label for="res_date">Datum *</label>
            <input type="date" id="res_date" name="res_date" required min="<?php echo esc_attr($today_ymd); ?>" max="<?php echo esc_attr($max_ymd); ?>" value="<?php echo esc_attr($default_date_ymd); ?>">
          </div>

          <div class="field">
            <label for="res_time">Uhrzeit *</label>
            <select id="res_time" name="res_time" required>
              <?php foreach ($initial_slots as $idx => $slot_hm): ?>
                <option value="<?php echo esc_attr($slot_hm); ?>"<?php selected($idx === 0); ?>><?php echo esc_html($slot_hm); ?> Uhr</option>
              <?php endforeach; ?>
            </select>
            <div id="res_sushi_monday_hint" style="display:none;margin-top:6px;font-size:12px;color:#d4a359;font-weight:600;">🍣 Hinweis: Montags hat die Sushi-Bar Ruhetag. Warme Speisen &amp; Currys bereiten wir sehr gerne für Sie zu!</div>
          </div>

          <div class="field full">
            <label for="res_party">Personenanzahl *</label>
            <select id="res_party" name="res_party" required>
              <option value="1">1 Person</option>
              <option value="2" selected>2 Personen</option>
              <option value="3">3 Personen</option>
              <option value="4">4 Personen</option>
              <option value="5">5 Personen</option>
              <option value="6">6 Personen</option>
              <option value="7">7 Personen</option>
              <option value="8">8 Personen</option>
              <option value="10">10–12 Personen (Größere Gruppe)</option>
            </select>
          </div>

          <div class="field">
            <label for="res_name">Ihr Name *</label>
            <input type="text" id="res_name" name="res_name" required maxlength="80" autocomplete="name" placeholder="Vor- und Nachname">
          </div>

          <div class="field">
            <label for="res_phone">Telefonnummer *</label>
            <input type="tel" id="res_phone" name="res_phone" required maxlength="25" inputmode="tel" autocomplete="tel" placeholder="+43 ...">
          </div>

          <div class="field full">
            <label for="res_email">E-Mail-Adresse (für Ihre Bestätigung)</label>
            <input type="email" id="res_email" name="res_email" maxlength="120" autocomplete="email" placeholder="ihre@email.at">
          </div>

          <div class="field full">
            <label for="res_notes">Besondere Wünsche / Anmerkungen (optional)</label>
            <textarea id="res_notes" name="res_notes" maxlength="500" placeholder="z. B. Hochstuhl benötigt, Allergien, Geburtstag..."></textarea>
          </div>

          <div class="field full" style="margin-top:4px;">
            <label for="res_consent" style="display:flex;align-items:flex-start;gap:10px;font-size:12px;line-height:1.5;color:var(--muted);cursor:pointer;">
              <input type="checkbox" id="res_consent" name="res_consent" value="1" required style="margin-top:3px;min-width:18px;min-height:18px;accent-color:var(--orange);">
              <span>Ich stimme zu, dass meine Angaben zur Bearbeitung dieser Tischreservierung gemäß der <a href="<?php echo esc_url(home_url('/datenschutz/')); ?>" target="_blank" rel="noopener" style="color:#d4a359;text-decoration:underline;">Datenschutzerklärung (DSGVO)</a> verarbeitet werden. *</span>
            </label>
          </div>

          <div class="booking-submit">
            <button type="submit" class="button button-dark">Reservierungsanfrage senden →</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const dateInput = document.getElementById('res_date');
  const timeSelect = document.getElementById('res_time');
  const sushiHint = document.getElementById('res_sushi_monday_hint');
  const slotsByDow = <?php echo wp_json_encode($slots_by_weekday); ?>;
  const todayYmd = <?php echo wp_json_encode($today_ymd); ?>;
  const todaySlots = <?php echo wp_json_encode($today_slots); ?>;

  if (!dateInput || !timeSelect) return;

  function updateSlots() {
    if (!dateInput.value) return;
    const parts = dateInput.value.split('-');
    if (parts.length < 3) return;
    const d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
    const jsDay = d.getDay(); // 0 = So, 1 = Mo .. 6 = Sa
    const isoDow = jsDay === 0 ? 7 : jsDay;

    if (sushiHint) {
      sushiHint.style.display = (isoDow === 1) ? 'block' : 'none';
    }

    const available = (dateInput.value === todayYmd && todaySlots.length > 0)
      ? todaySlots
      : (slotsByDow[isoDow] || []);

    const prevVal = timeSelect.value;
    timeSelect.innerHTML = '';
    available.forEach(function(hm) {
      const opt = document.createElement('option');
      opt.value = hm;
      opt.textContent = hm + ' Uhr';
      timeSelect.appendChild(opt);
    });
    if (available.includes(prevVal)) {
      timeSelect.value = prevVal;
    } else if (available.length > 0) {
      timeSelect.value = available[0];
    }
  }

  dateInput.addEventListener('change', updateSlots);
  updateSlots();
});
</script>

<?php get_footer(); ?>
