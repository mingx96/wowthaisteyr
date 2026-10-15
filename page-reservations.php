<?php
/**
 * Template Name: Tisch reservieren
 */
get_header();

$booking_submitted = false;
$booking_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wowthai_reservation_nonce'])) {
    if (wp_verify_nonce($_POST['wowthai_reservation_nonce'], 'wowthai_make_reservation')) {
        $name = sanitize_text_field($_POST['res_name'] ?? '');
        $email = sanitize_email($_POST['res_email'] ?? '');
        $phone = sanitize_text_field($_POST['res_phone'] ?? '');
        $date = sanitize_text_field($_POST['res_date'] ?? '');
        $time = sanitize_text_field($_POST['res_time'] ?? '');
        $party = intval($_POST['res_party'] ?? 2);
        $notes = sanitize_textarea_field($_POST['res_notes'] ?? '');

        if (!empty($name) && !empty($phone) && !empty($date) && !empty($time)) {
            // Save reservation as post
            $res_id = wp_insert_post([
                'post_title' => "Reservierung: $name ($party Personen am $date um $time Uhr)",
                'post_content' => $notes,
                'post_status' => 'publish',
                'post_type' => 'post', // or custom post type
            ]);

            // Send notification email to restaurant
            $to = get_option('admin_email');
            $subject = "Neue Tischreservierung: $name ($party Personen am $date um $time)";
            $message = "Name: $name\nTelefon: $phone\nE-Mail: $email\nDatum: $date\nUhrzeit: $time Uhr\nPersonen: $party\nAnmerkungen: $notes\n";
            wp_mail($to, $subject, $message);

            $booking_submitted = true;
        } else {
            $booking_error = 'Bitte füllen Sie alle erforderlichen Felder aus (Name, Telefon, Datum, Uhrzeit).';
        }
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
          <h2>Vielen Dank für Ihre Reservierung!</h2>
          <p>Wir haben Ihre Anfrage für <strong><?php echo esc_html($date); ?> um <?php echo esc_html($time); ?> Uhr</strong> für <strong><?php echo esc_html($party); ?> Personen</strong> erhalten.</p>
          <p>Wir freuen uns darauf, Sie bald im Restaurant begrüßen zu dürfen!</p>
          <a class="button button-dark" href="<?php echo esc_url(home_url('/menu/')); ?>">Schon jetzt Speisekarte ansehen ↗</a>
        </div>
      <?php else: ?>
        <h2 class="booking-title">Tisch online reservieren</h2>
        <p class="booking-copy">Füllen Sie das Formular aus – wir reservieren Ihren Tisch umgehend.</p>

        <?php if ($booking_error): ?>
          <p class="booking-error"><?php echo esc_html($booking_error); ?></p>
        <?php endif; ?>

        <form class="booking-form" method="POST" action="">
          <?php wp_nonce_field('wowthai_make_reservation', 'wowthai_reservation_nonce'); ?>

          <div class="field">
            <label for="res_date">Datum *</label>
            <input type="date" id="res_date" name="res_date" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>">
          </div>

          <div class="field">
            <label for="res_time">Uhrzeit *</label>
            <select id="res_time" name="res_time" required>
              <option value="11:30">11:30 Uhr</option>
              <option value="12:00">12:00 Uhr</option>
              <option value="12:30">12:30 Uhr</option>
              <option value="13:00">13:00 Uhr</option>
              <option value="13:30">13:30 Uhr</option>
              <option value="14:00">14:00 Uhr</option>
              <option value="17:00">17:00 Uhr</option>
              <option value="17:30">17:30 Uhr</option>
              <option value="18:00" selected>18:00 Uhr</option>
              <option value="18:30">18:30 Uhr</option>
              <option value="19:00">19:00 Uhr</option>
              <option value="19:30">19:30 Uhr</option>
              <option value="20:00">20:00 Uhr</option>
              <option value="20:30">20:30 Uhr</option>
              <option value="21:00">21:00 Uhr</option>
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
            <button type="submit" class="button button-dark">Tisch verbindlich reservieren →</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php get_footer(); ?>
