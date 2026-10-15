<?php
/**
 * Custom Checkout Form for WOW Thai Steyr
 * Streamlined, squared, high-density restaurant takeaway UX
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

do_action( 'woocommerce_before_checkout_form', $checkout );

if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
    echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
    return;
}

$now = wowthai_pickup_now();
$asap_ready = wowthai_pickup_asap_time($now);
$pickup_dates = wowthai_pickup_dates($now);
$initial_mode = $asap_ready ? 'asap' : 'later';
?>

<div class="checkout-intro-bar">
  <p class="eyebrow"><span class="eyebrow-dot"></span> WOW THAI · STEYR · ABHOLUNG</p>
  <h1>Kasse &amp; <em>Abholung</em></h1>
  <p class="intro-sub">Schließen Sie Ihre Bestellung ab – frisch zubereitet zur Selbstabholung im City Point Steyr.</p>
</div>

<form name="checkout" method="post" class="checkout woocommerce-checkout wowthai-checkout-grid" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" aria-label="Kasse">

  <!-- LEFT COLUMN: Takeaway Timing & Contact Details -->
  <div class="checkout-left-col">

    <!-- BLOCK 1: Abholzeitpunkt -->
    <div class="checkout-card" id="block-timing">
      <div class="card-head">
        <span class="card-num">1</span>
        <div>
          <h2>Abholzeitpunkt</h2>
          <p>Wann möchten Sie Ihre Speisen im Restaurant abholen?</p>
        </div>
      </div>

      <div class="timing-segmented-control" role="tablist">
        <button type="button" class="seg-btn<?php echo $asap_ready ? ' active' : ''; ?>" id="btn-asap" data-mode="asap" <?php disabled(!$asap_ready); ?>>
          <span class="seg-icon">⚡</span>
          <span class="seg-label">Schnellstmöglich</span>
          <span class="seg-pill">ca. 25–35 Min.</span>
        </button>
        <button type="button" class="seg-btn<?php echo !$asap_ready ? ' active' : ''; ?>" id="btn-later" data-mode="later">
          <span class="seg-icon">🕒</span>
          <span class="seg-label">Später abholen</span>
          <span class="seg-pill">Vorbestellung</span>
        </button>
      </div>

      <!-- ASAP Status Box -->
      <div class="asap-status-box" id="asap-box"<?php echo !$asap_ready ? ' style="display: none;"' : ''; ?>>
        <span class="asap-status-dot"></span>
        <span>Küche bereit · Fertig zur Abholung um ca. <strong><?php echo $asap_ready ? esc_html($asap_ready->format('H:i')) : ''; ?> Uhr</strong></span>
      </div>

      <!-- Preorder Selectors Box -->
      <div class="preorder-select-box" id="later-box"<?php echo $asap_ready ? ' style="display: none;"' : ''; ?>>
        <div class="preorder-grid">
          <div class="preorder-item">
            <label for="pickup_date_select">Abholtag:</label>
            <select id="pickup_date_select" name="pickup_date" class="input-select">
              <?php foreach ($pickup_dates as $date): ?>
                <option value="<?php echo esc_attr($date['value']); ?>" data-slots="<?php echo esc_attr(wp_json_encode($date['slots'])); ?>"><?php echo esc_html($date['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="preorder-item">
            <label for="pickup_time_select">Uhrzeit:</label>
            <select id="pickup_time_select" name="pickup_time" class="input-select">
              <?php foreach (($pickup_dates[0]['slots'] ?? []) as $slot): ?>
                <option value="<?php echo esc_attr($slot); ?>"><?php echo esc_html($slot); ?> Uhr</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <input type="hidden" name="pickup_mode" id="pickup_mode" value="<?php echo esc_attr($initial_mode); ?>">
    </div>

    <!-- BLOCK 2: Kontaktdaten -->
    <div class="checkout-card" id="block-contact">
      <div class="card-head">
        <span class="card-num">2</span>
        <div>
          <h2>Ihre Kontaktdaten</h2>
          <p>Für die Abholbestätigung und Rückfragen zu Ihrer Bestellung.</p>
        </div>
      </div>

      <div class="contact-grid">
        <div class="field-box" id="field-box-first-name">
          <label for="billing_first_name">Vorname <span class="req">*</span></label>
          <input type="text" class="input-text" name="billing_first_name" id="billing_first_name" placeholder="z. B. Max" value="<?php echo esc_attr( $checkout->get_value( 'billing_first_name' ) ); ?>" required autocomplete="given-name">
          <span class="field-error-msg" id="err-billing_first_name"></span>
        </div>
        <div class="field-box" id="field-box-last-name">
          <label for="billing_last_name">Nachname <span class="req">*</span></label>
          <input type="text" class="input-text" name="billing_last_name" id="billing_last_name" placeholder="z. B. Mustermann" value="<?php echo esc_attr( $checkout->get_value( 'billing_last_name' ) ); ?>" required autocomplete="family-name">
          <span class="field-error-msg" id="err-billing_last_name"></span>
        </div>
        <div class="field-box" id="field-box-phone">
          <label for="billing_phone">Telefonnummer <span class="req">*</span></label>
          <input type="tel" class="input-text" name="billing_phone" id="billing_phone" placeholder="+43 660 ..." value="<?php echo esc_attr( $checkout->get_value( 'billing_phone' ) ); ?>" required autocomplete="tel">
          <span class="field-error-msg" id="err-billing_phone"></span>
        </div>
        <div class="field-box" id="field-box-email">
          <label for="billing_email">E-Mail-Adresse <span class="req">*</span></label>
          <input type="email" class="input-text" name="billing_email" id="billing_email" placeholder="ihre@email.at" value="<?php echo esc_attr( $checkout->get_value( 'billing_email' ) ); ?>" required autocomplete="email">
          <span class="field-error-msg" id="err-billing_email"></span>
        </div>
      </div>

      <div class="field-box full" style="margin-top: 14px;">
        <label for="order_comments">Besondere Wünsche zur Zubereitung (optional)</label>
        <textarea name="order_comments" class="input-textarea" id="order_comments" placeholder="z. B. bitte extra scharf, keine Erdnüsse, Besteck benötigt..."><?php echo esc_textarea( $checkout->get_value( 'order_comments' ) ); ?></textarea>
      </div>

      <!-- Hidden address defaults for restaurant takeaway so WooCommerce checkout.js & backend are completely satisfied -->
      <input type="hidden" name="billing_country" id="billing_country" value="AT">
      <input type="hidden" name="billing_city" id="billing_city" value="Steyr">
      <input type="hidden" name="billing_address_1" id="billing_address_1" value="Selbstabholung City Point Steyr">
      <input type="hidden" name="billing_postcode" id="billing_postcode" value="4400">
      <input type="hidden" name="billing_state" id="billing_state" value="">
    </div>

  </div>

  <!-- RIGHT COLUMN: Sticky Order Summary & Payment -->
  <div class="checkout-right-col">
    <div class="order-summary-card">
      <div class="summary-head">
        <h2>Ihre Bestellung</h2>
        <a href="<?php echo esc_url( home_url('/menu/') ); ?>" class="link-edit-menu">Speisen ändern ↗</a>
      </div>

      <div id="order_review" class="woocommerce-checkout-review-order">
        <?php do_action( 'woocommerce_checkout_order_review' ); ?>
      </div>

      <div class="pickup-location-note">
        <span class="loc-icon">📍</span>
        <div>
          <strong>Abholadresse im Restaurant:</strong>
          <span>WOW Thai · City Point 1. OG<br>Leopold-Werndl-Straße 2, 4400 Steyr</span>
        </div>
      </div>
    </div>
  </div>

</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
