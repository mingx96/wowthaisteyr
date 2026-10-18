<?php
/**
 * Review order table for WOW Thai checkout
 */

defined( 'ABSPATH' ) || exit;

$cart = WC()->cart;
if ( ! $cart ) {
    return;
}

$subtotal = (float) $cart->get_subtotal();
$has_dlv  = function_exists( 'wowthai_branch_has_delivery' ) && wowthai_branch_has_delivery();

// Resolve current fulfillment type and postcode from session or POST
$fulfillment = 'pickup';
if ( $has_dlv ) {
    if ( isset( $_POST['fulfillment_type'] ) ) {
        $fulfillment = sanitize_text_field( wp_unslash( $_POST['fulfillment_type'] ) );
    } elseif ( function_exists( 'WC' ) && WC()->session ) {
        $fulfillment = WC()->session->get( 'wowthai_fulfillment_type', 'delivery' );
    } else {
        $fulfillment = 'delivery';
    }
}

$raw_plz = '';
if ( isset( $_POST['billing_postcode'] ) ) {
    $raw_plz = sanitize_text_field( wp_unslash( $_POST['billing_postcode'] ) );
} elseif ( function_exists( 'WC' ) && WC()->session ) {
    $raw_plz = (string) WC()->session->get( 'wowthai_delivery_plz', '' );
}

$plz = function_exists( 'wowthai_normalize_plz' ) ? wowthai_normalize_plz( $raw_plz ) : $raw_plz;
if ( empty( $plz ) && $has_dlv ) {
    $plz = '4300';
}

$pricing = function_exists( 'wowthai_get_delivery_pricing' )
    ? wowthai_get_delivery_pricing( $plz, $subtotal, $fulfillment )
    : [ 'valid' => true, 'fee' => 0.00, 'free_threshold_met' => true, 'remaining_to_free' => 0.00 ];

$eta = function_exists( 'wowthai_get_delivery_eta' ) ? wowthai_get_delivery_eta( $plz ) : 'ca. 30–45 Min.';
?>

<table class="shop_table woocommerce-checkout-review-order-table">
  <thead>
    <tr>
      <th class="product-name">Gericht</th>
      <th class="product-total">Summe</th>
    </tr>
  </thead>
  <tbody>
    <?php
    do_action( 'woocommerce_review_order_before_cart_contents' );

    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        $_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

        if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
            ?>
            <tr class="<?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">
              <td class="product-name">
                <span class="item-title"><?php echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) ); ?></span>
                <strong class="product-quantity">&times;&nbsp;<?php echo esc_html( $cart_item['quantity'] ); ?></strong>
                <?php echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php if ( ! empty( $cart_item['wowthai_variant_name'] ) ) : ?>
                  <div class="review-variant-pill">+ <?php echo esc_html( $cart_item['wowthai_variant_name'] ); ?></div>
                <?php endif; ?>
              </td>
              <td class="product-total">
                <?php echo apply_filters( 'woocommerce_cart_item_subtotal', $cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
              </td>
            </tr>
            <?php
        }
    }

    do_action( 'woocommerce_review_order_after_cart_contents' );
    ?>
  </tbody>
  <tfoot>
    <tr class="cart-subtotal">
      <th>Zwischensumme</th>
      <td><?php wc_cart_totals_subtotal_html(); ?></td>
    </tr>

    <?php foreach ( $cart->get_fees() as $fee ) : ?>
      <tr class="fee delivery-fee-row">
        <th><?php echo esc_html( $fee->name ); ?></th>
        <td><?php wc_cart_totals_fee_html( $fee ); ?></td>
      </tr>
    <?php endforeach; ?>

    <?php if ( wc_tax_enabled() && ! $cart->display_prices_including_tax() ) : ?>
      <?php if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) : ?>
        <?php foreach ( $cart->get_tax_totals() as $code => $tax ) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited ?>
          <tr class="tax-rate tax-rate-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
            <th><?php echo esc_html( $tax->label ); ?></th>
            <td><?php echo wp_kses_post( $tax->formatted_amount ); ?></td>
          </tr>
        <?php endforeach; ?>
      <?php else : ?>
        <tr class="tax-total">
          <th><?php echo esc_html( WC()->countries->tax_or_vat() ); ?></th>
          <td><?php wc_cart_totals_taxes_total_html(); ?></td>
        </tr>
      <?php endif; ?>
    <?php endif; ?>

    <?php do_action( 'woocommerce_review_order_before_order_total' ); ?>

    <tr class="order-total">
      <th>Gesamtsumme</th>
      <td><?php wc_cart_totals_order_total_html(); ?></td>
    </tr>

    <?php do_action( 'woocommerce_review_order_after_order_total' ); ?>
  </tfoot>
</table>

<?php if ( $has_dlv && ( $fulfillment === 'delivery' || $fulfillment === 'lieferung' ) ) : ?>
  <div class="review-delivery-summary-box" style="margin-top: 14px; padding: 12px; background: rgba(199, 157, 75, 0.08); border: 1px solid rgba(199, 157, 75, 0.25);">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
      <span style="font-weight: 700; font-size: 13px; color: var(--gold, #c79d4b);">
        🛵 Lieferung: <?php echo esc_html( $pricing['zone_name'] ?? 'Kernzone St. Valentin' ); ?>
      </span>
      <span style="font-size: 12px; color: var(--green, #22c55e); font-weight: 700;">
        ⏱️ <?php echo esc_html( $eta ); ?>
      </span>
    </div>
    <?php if ( ! empty( $pricing['free_threshold_met'] ) ) : ?>
      <p style="margin: 0; font-size: 12px; color: var(--green, #22c55e); font-weight: 600;">
        ✓ Kostenlose Lieferung! Sie haben die Freigrenze erreicht.
      </p>
    <?php elseif ( isset( $pricing['remaining_to_free'] ) && $pricing['remaining_to_free'] > 0 ) : ?>
      <p style="margin: 0; font-size: 12px; color: var(--text-muted, #a8a095);">
        Noch <strong><?php echo esc_html( number_format( (float) $pricing['remaining_to_free'], 2, ',', '.' ) ); ?> €</strong> bis zur kostenlosen Lieferung!
      </p>
    <?php endif; ?>
  </div>
<?php endif; ?>
