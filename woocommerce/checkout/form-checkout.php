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

$now          = wowthai_pickup_now();
$has_sushi    = wowthai_cart_has_sushi();
$has_lunch    = function_exists('wowthai_cart_has_mittagsmenu') ? wowthai_cart_has_mittagsmenu() : false;
$has_m4       = function_exists('wowthai_cart_has_mittagsmenu_m4') ? wowthai_cart_has_mittagsmenu_m4() : false;
$asap_ready   = wowthai_pickup_asap_time($now, $has_lunch, $has_m4);
if ($has_sushi && !wowthai_sushi_available($now)) {
    $asap_ready = null;
}
$pickup_dates = wowthai_pickup_dates($now, $has_sushi, $has_lunch, $has_m4);
$initial_mode = $asap_ready ? 'asap' : 'later';
$wow_hd       = function_exists('wowthai_hours_display') ? wowthai_hours_display() : [];

$b_name     = function_exists('wowthai_get_branch_name') ? wowthai_get_branch_name() : 'WOW Thai Steyr';
$b_short    = function_exists('wowthai_get_branch_short_name') ? wowthai_get_branch_short_name() : 'WoW Thai';
$b_city     = function_exists('wowthai_get_branch_city') ? wowthai_get_branch_city() : 'Steyr';
$b_street   = function_exists('wowthai_get_branch_street') ? wowthai_get_branch_street() : 'Leopold-Werndl-Straße 2';
$b_street_s = function_exists('wowthai_get_branch_street_short') ? wowthai_get_branch_street_short() : 'City Point · 1. OG';
$b_postcode = function_exists('wowthai_get_branch_postcode') ? wowthai_get_branch_postcode() : '4400';
$has_dlv      = function_exists('wowthai_branch_has_delivery') ? wowthai_branch_has_delivery() : false;
$mode_badge   = $has_dlv ? 'LIEFERUNG & ABHOLUNG' : 'ABHOLUNG';
$delivery_eta = function_exists('wowthai_get_delivery_eta') ? wowthai_get_delivery_eta() : 'ca. 30–45 Min.';
?>

<div class="checkout-intro-bar">
  <p class="eyebrow"><span class="eyebrow-dot"></span> <?php echo esc_html(strtoupper($b_short . ' · ' . $b_city . ' · ' . $mode_badge)); ?></p>
  <h1>Kasse &amp; <em><?php echo $has_dlv ? 'Lieferung' : 'Abholung'; ?></em></h1>
  <p class="intro-sub">
    <?php if ($has_dlv): ?>
      Schließen Sie Ihre Bestellung ab – frisch zubereitet zur schnellen Lieferung oder Selbstabholung in <?php echo esc_html($b_city); ?>.
    <?php else: ?>
      Schließen Sie Ihre Bestellung ab – frisch zubereitet zur Selbstabholung bei <?php echo esc_html($b_name); ?> (<?php echo esc_html($b_street_s); ?>).
    <?php endif; ?>
  </p>
</div>

<form name="checkout" method="post" class="checkout woocommerce-checkout wowthai-checkout-grid" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" aria-label="Kasse">

  <!-- LEFT COLUMN: Fulfillment, Delivery Address, Timing & Contact Details -->
  <div class="checkout-left-col">

    <?php if ($has_dlv): ?>
      <!-- FULFILLMENT MODE TOGGLE (When Branch Supports Delivery) -->
      <div class="fulfillment-card" id="block-fulfillment" style="margin-bottom: 20px;">
        <div class="fulfillment-segmented-control" role="tablist" aria-label="Bestellart">
          <button type="button" class="ful-seg-btn active" id="btn-ful-delivery" data-fulfillment="delivery" role="tab" aria-selected="true">
            <span class="ful-icon">🛵</span>
            <div class="ful-text">
              <span class="ful-title">Lieferung</span>
              <small class="ful-sub">Zustellung nach Hause</small>
            </div>
            <span class="ful-pill"><?php echo esc_html($delivery_eta); ?></span>
          </button>
          <button type="button" class="ful-seg-btn" id="btn-ful-pickup" data-fulfillment="pickup" role="tab" aria-selected="false">
            <span class="ful-icon">🥡</span>
            <div class="ful-text">
              <span class="ful-title">Selbstabholung</span>
              <small class="ful-sub"><?php echo esc_html($b_street_s); ?></small>
            </div>
            <span class="ful-pill"><?php echo esc_html(wowthai_get_kitchen_prep_time()); ?></span>
          </button>
        </div>
        <input type="hidden" name="fulfillment_type" id="fulfillment_type" value="delivery">
      </div>

      <!-- DELIVERY ESTIMATE & ADVISORY AREA -->
      <div class="delivery-eta-badge-bar" id="delivery-eta-bar" style="margin-bottom: 20px; padding: 10px 14px; background: rgba(199, 157, 75, 0.12); border: 1px solid rgba(199, 157, 75, 0.35); font-size: 13px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
        <div>
          <span class="eta-icon" style="margin-right: 6px;">🛵</span>
          <span class="eta-text">Geschätzte Lieferzeit: <strong><?php echo esc_html($delivery_eta); ?></strong></span>
        </div>
        <span class="eta-zones-hint" style="font-size: 11px; opacity: 0.85;">St. Valentin (4300), Ennsdorf (4303), Enns (4470), Asten (4481)</span>
      </div>

      <!-- BLOCK 1: Lieferadresse -->
      <div class="checkout-card" id="block-delivery-address" style="margin-bottom: 20px;">
        <div class="card-head">
          <span class="card-num">1</span>
          <div>
            <h2>Lieferadresse in St. Valentin &amp; Umgebung</h2>
            <p>Wohin dürfen wir Ihre Bestellung zustellen?</p>
          </div>
        </div>

        <div class="contact-grid">
          <div class="field-box full" id="field-box-address-1">
            <label for="billing_address_1">Straße &amp; Hausnummer <span class="req">*</span></label>
            <input type="text" class="input-text" name="billing_address_1" id="billing_address_1" placeholder="z. B. Westbahnstraße 12" value="<?php echo esc_attr( $checkout->get_value( 'billing_address_1' ) ); ?>" autocomplete="address-line1">
            <span class="field-error-msg" id="err-billing_address_1"></span>
          </div>

          <div class="field-box full" id="field-box-address-2">
            <label for="billing_address_2">Stiege, Stockwerk, Tür / Top (optional)</label>
            <input type="text" class="input-text" name="billing_address_2" id="billing_address_2" placeholder="z. B. Stiege 2, 1. Stock, Top 4" value="<?php echo esc_attr( $checkout->get_value( 'billing_address_2' ) ); ?>" autocomplete="address-line2">
          </div>

          <div class="field-box" id="field-box-postcode">
            <label for="billing_postcode">Postleitzahl (PLZ) <span class="req">*</span></label>
            <input type="text" class="input-text" name="billing_postcode" id="billing_postcode" placeholder="4300" maxlength="8" value="<?php echo esc_attr( $checkout->get_value( 'billing_postcode' ) ?: '4300' ); ?>" autocomplete="postal-code" inputmode="numeric">
            <span class="field-error-msg" id="err-billing_postcode"></span>
            <div id="plz-zone-badge" class="plz-zone-badge" style="display:none; margin-top: 6px; font-size: 12px; font-weight: 700;"></div>
          </div>

          <div class="field-box" id="field-box-city">
            <label for="billing_city">Ort <span class="req">*</span></label>
            <input type="text" class="input-text" name="billing_city" id="billing_city" placeholder="St. Valentin" value="<?php echo esc_attr( $checkout->get_value( 'billing_city' ) ?: 'St. Valentin' ); ?>" autocomplete="address-level2">
            <span class="field-error-msg" id="err-billing_city"></span>
          </div>
        </div>

        <!-- Realtime Dynamic Delivery Zone Callout Box -->
        <div id="delivery-zone-callout" class="delivery-zone-callout" style="display:none; margin-top: 14px;">
          <div id="delivery-zone-message" class="delivery-zone-message" style="padding: 10px 14px; font-size: 13px; line-height: 1.4;"></div>
          <div id="delivery-fallback-cta" class="delivery-fallback-cta" style="display:none; margin-top: 10px;">
            <button type="button" class="btn-fallback-pickup" id="btn-fallback-pickup" style="padding: 8px 14px; background: var(--green, #22c55e); color: #111; font-weight: 700; border: none; cursor: pointer;">
              🥡 Zur kostenlosen Selbstabholung wechseln
            </button>
          </div>
        </div>
      </div>
    <?php else: ?>
      <input type="hidden" name="fulfillment_type" id="fulfillment_type" value="pickup">
    <?php endif; ?>

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
          <span class="seg-pill"><?php echo $asap_ready ? esc_html(wowthai_get_kitchen_prep_time()) : 'Nicht verfügbar'; ?></span>
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

      <?php if (!$asap_ready): ?>
        <p class="kitchen-closed-banner">Schnellstmögliche Abholung ist derzeit nicht verfügbar. Bitte wählen Sie eine Abholzeit für die nächste Öffnungszeit.<?php if ($has_m4 && !empty($wow_hd['mittags_m4_note'])): ?> Das Sushi-Mittagsmenü M4 wird nur <?php echo esc_html($wow_hd['mittags_m4_note']); ?> serviert.<?php elseif ($has_lunch && !empty($wow_hd['mittags_note'])): ?> Mittagsmenü: <?php echo esc_html($wow_hd['mittags_note']); ?>.<?php elseif ($has_sushi): ?> Montags ist Sushi-Ruhetag.<?php endif; ?></p>
      <?php endif; ?>
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
      <input type="hidden" name="billing_state" id="billing_state" value="">
      <?php if (!$has_dlv): ?>
      <input type="hidden" name="billing_city" id="billing_city" value="<?php echo esc_attr($b_city); ?>">
      <input type="hidden" name="billing_address_1" id="billing_address_1" value="<?php echo esc_attr('Selbstabholung ' . $b_name); ?>">
      <input type="hidden" name="billing_postcode" id="billing_postcode" value="<?php echo esc_attr($b_postcode); ?>">
      <?php endif; ?>
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
          <span><?php echo esc_html($b_name . ' · ' . $b_street_s); ?><br><?php echo esc_html($b_street . ', ' . $b_postcode . ' ' . $b_city); ?></span>
        </div>
      </div>
    </div>
  </div>

</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
