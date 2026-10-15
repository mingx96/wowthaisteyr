<?php
/**
 * Template Name: Tisch reservieren
 */
get_header();

$booking_submitted = false;
$booking_error = '';
$notification_sent = true;
$now = wowthai_pickup_now();
$today = $now->format('Y-m-d');
$booking_date = $today;
$booking_time = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wowthai_reservation_nonce'])) {
    $nonce = is_string($_POST['wowthai_reservation_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['wowthai_reservation_nonce'])) : '';
    if (wp_verify_nonce($nonce, 'wowthai_make_reservation')) {
        $name = sanitize_text_field(wp_unslash(is_string($_POST['res_name'] ?? null) ? $_POST['res_name'] : ''));
        $email = sanitize_email(wp_unslash(is_string($_POST['res_email'] ?? null) ? $_POST['res_email'] : ''));
        $phone = sanitize_text_field(wp_unslash(is_string($_POST['res_phone'] ?? null) ? $_POST['res_phone'] : ''));
        $date = sanitize_text_field(wp_unslash(is_string($_POST['res_date'] ?? null) ? $_POST['res_date'] : ''));
        $time = sanitize_text_field(wp_unslash(is_string($_POST['res_time'] ?? null) ? $_POST['res_time'] : ''));
        $party = isset($_POST['res_party']) && is_scalar($_POST['res_party']) ? absint($_POST['res_party']) : 0;
        $notes = sanitize_textarea_field(wp_unslash(is_string($_POST['res_notes'] ?? null) ? $_POST['res_notes'] : ''));
        $booking_date = $date;
        $booking_time = $time;

        if (empty($name) || empty($phone) || !in_array($party, [1, 2, 3, 4, 5, 6, 7, 8, 10], true)) {
            $booking_error = 'Bitte geben Sie Ihren Namen, Ihre Telefonnummer und die Personenzahl an.';
        } elseif ($email && !is_email($email)) {
            $booking_error = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
        } elseif (!wowthai_validate_reservation_time($date, $time, $now)) {
            $booking_error = 'Dieser Termin liegt außerhalb unserer Reservierungszeiten oder ist bereits vergangen. Bitte wählen Sie einen anderen Termin.';
        } else {
            // Keep guest details out of public posts and search results.
            $details = "Name: $name\nTelefon: $phone\nE-Mail: $email\nDatum: $date\nUhrzeit: $time Uhr\nPersonen: $party\nAnmerkungen: $notes";
            $res_id = wp_insert_post([
                'post_title' => "Tischanfrage: $date um $time Uhr ($party Personen)",
                'post_content' => $details,
                'post_status' => 'private',
                'post_type' => 'wowthai_reservation',
            ], true);

            if (is_wp_error($res_id) || !$res_id) {
                $booking_error = 'Ihre Anfrage konnte nicht gespeichert werden. Bitte rufen Sie uns an.';
            } else {
                $notification_sent = wp_mail(
                    get_option('admin_email'),
                    "Neue Tischanfrage: $party Personen am $date um $time Uhr",
                    $details
                );
                $booking_submitted = true;
            }
        }
    } else {
        $booking_error = 'Die Sitzung ist abgelaufen. Bitte laden Sie die Seite neu und versuchen Sie es erneut.';
    }
}
?>

<main>
  <section class="page-intro wrap reservation-intro">
    <p class="eyebrow"><span class="eyebrow-dot"></span> WOW THAI · STEYR</p>
    <div class="page-intro-row">
      <div>
        <h1>Ein Tisch für <em>Sie.</em></h1>
        <p>Planen Sie Ihren Besuch bei uns. Wählen Sie Datum, Uhrzeit und Personenzahl – wir freuen uns auf Ihren Besuch im City Point in Steyr.</p>
      </div>
      <a class="button button-quiet" href="<?php echo esc_url(home_url('/menu/')); ?>">Speisekarte ansehen <span aria-hidden="true">↗</span></a>
    </div>
  </section>

  <section class="wrap reservation-layout">
    <div class="reservation-aside">
      <span class="aside-mark">W</span>
      <p class="eyebrow">PERSÖNLICH FÜR SIE DA</p>
      <h2>Wir freuen uns<br>auf Ihren Besuch.</h2>
      <p>Fragen zu Ihrer Reservierung oder besondere Wünsche? Rufen Sie uns gern direkt an – wir beraten Sie persönlich.</p>
      <a class="phone-large" href="tel:+43725274973">+43 7252 74973</a>
    </div>

    <div class="reservation-panel" id="reservation-root" aria-live="polite">
      <?php if ($booking_submitted): ?>
        <div class="booking-success">
          <h2>Vielen Dank für Ihre Tischanfrage!</h2>
          <p>Wir haben Ihre Anfrage für <strong><?php echo esc_html($date); ?> um <?php echo esc_html($time); ?> Uhr</strong> für <strong><?php echo esc_html($party); ?> Personen</strong> erhalten. Der Tisch ist erst nach unserer Bestätigung reserviert.</p>
          <?php if (!$notification_sent): ?>
            <p>Die E-Mail-Benachrichtigung konnte nicht zugestellt werden. Bitte rufen Sie uns zur Bestätigung an: <a href="tel:+43725274973">+43 7252 74973</a>.</p>
          <?php endif; ?>
          <a class="button button-dark" href="<?php echo esc_url(home_url('/menu/')); ?>">Schon jetzt Speisekarte ansehen ↗</a>
        </div>
      <?php else: ?>
        <h2 class="booking-title">Tisch online reservieren</h2>
        <p class="booking-copy">Füllen Sie das Formular aus. Wir bestätigen Ihre Tischanfrage persönlich.</p>

        <?php if ($booking_error): ?>
          <p class="booking-error"><?php echo esc_html($booking_error); ?></p>
        <?php endif; ?>

        <form class="booking-form" method="POST" action="">
          <?php wp_nonce_field('wowthai_make_reservation', 'wowthai_reservation_nonce'); ?>

          <div class="field">
            <label for="res_date">Datum *</label>
            <input type="date" id="res_date" name="res_date" required min="<?php echo esc_attr($today); ?>" max="<?php echo esc_attr($now->modify('+90 days')->format('Y-m-d')); ?>" data-earliest="<?php echo esc_attr($now->modify('+30 minutes')->format('Y-m-d H:i')); ?>" value="<?php echo esc_attr($booking_date); ?>">
          </div>

          <div class="field">
            <label for="res_time">Uhrzeit *</label>
            <select id="res_time" name="res_time" required>
              <?php foreach (wowthai_reservation_times($now) as $slot): ?>
                <option value="<?php echo esc_attr($slot); ?>" <?php selected($booking_time, $slot); ?>><?php echo esc_html($slot); ?> Uhr</option>
              <?php endforeach; ?>
            </select>
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
              <option value="10">10+ Personen (Größere Gruppe)</option>
            </select>
          </div>

          <div class="field">
            <label for="res_name">Ihr Name *</label>
            <input type="text" id="res_name" name="res_name" required placeholder="Vor- und Nachname">
          </div>

          <div class="field">
            <label for="res_phone">Telefonnummer *</label>
            <input type="tel" id="res_phone" name="res_phone" required placeholder="+43 ...">
          </div>

          <div class="field full">
            <label for="res_email">E-Mail-Adresse</label>
            <input type="email" id="res_email" name="res_email" placeholder="ihre@email.at">
          </div>

          <div class="field full">
            <label for="res_notes">Besondere Wünsche / Anmerkungen (optional)</label>
            <textarea id="res_notes" name="res_notes" placeholder="z. B. Hochstuhl benötigt, Allergien, Geburtstag..."></textarea>
          </div>

          <div class="booking-submit">
            <button type="submit" class="button button-dark">Tisch anfragen →</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php get_footer(); ?>
