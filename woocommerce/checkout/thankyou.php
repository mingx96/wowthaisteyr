<?php
/**
 * Custom Thank You / Order Received Template for WOW Thai Steyr
 * Warm, luminous, prestigious restaurant pickup ticket & celebratory receipt
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wowthai-thankyou-wrap">

<?php if ( $order ) :

    do_action( 'woocommerce_before_thankyou', $order->get_id() );

    if ( $order->has_status( 'failed' ) ) : ?>

        <div class="thankyou-card error-card">
            <div class="status-icon-badge error">✕</div>
            <h1>Bestellung leider fehlgeschlagen</h1>
            <p>Leider konnte Ihre Bestellung nicht verarbeitet werden. Bitte versuchen Sie es erneut oder kontaktieren Sie uns direkt im Restaurant.</p>
            <div class="thankyou-actions">
                <a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="btn-gold">Erneut zur Kasse</a>
                <a href="<?php echo esc_url( home_url('/menu/') ); ?>" class="btn-outline">Zur Speisekarte</a>
            </div>
        </div>

    <?php else : 

        $order_id = $order->get_id();
        $first_name = $order->get_billing_first_name();
        $full_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        $phone = $order->get_billing_phone();
        $email = $order->get_billing_email();
        $note = $order->get_customer_note();
        $pickup_time = $order->get_meta('Abholzeit');
        if (empty($pickup_time)) {
            $pickup_time = 'Schnellstmöglich (ca. 25–35 Min.)';
        }
        $order_date = wc_format_datetime( $order->get_date_created() );
        $payment_title = $order->get_payment_method_title() ?: 'Barzahlung bei Abholung';
        ?>

        <!-- 1. Hero Confirmation Card (Warm & Celebratory) -->
        <div class="thankyou-hero-card">
            <div class="hero-glow-layer"></div>
            <div class="hero-content">
                <div class="status-icon-badge success">
                    <span>✓</span>
                </div>
                <p class="hero-eyebrow">BESTELLUNG ERFOLGREICH EINGEGANGEN</p>
                <h1>Vielen Dank, <?php echo esc_html( $first_name ?: 'Lieber Gast' ); ?>!</h1>
                <p class="hero-sub">
                    Ihre Bestellung <strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong> wurde an unsere Küche im <strong>City Point Steyr</strong> übermittelt und wird frisch für Sie zubereitet.
                </p>
                
                <div class="hero-quick-badges">
                    <div class="quick-badge">
                        <span class="badge-lbl">Bestellnummer</span>
                        <strong class="badge-val">#<?php echo esc_html( $order->get_order_number() ); ?></strong>
                    </div>
                    <div class="quick-badge highlight">
                        <span class="badge-lbl">Gewünschte Abholung</span>
                        <strong class="badge-val">🕒 <?php echo esc_html( $pickup_time ); ?></strong>
                    </div>
                    <div class="quick-badge">
                        <span class="badge-lbl">Gesamtsumme</span>
                        <strong class="badge-val gold"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong>
                    </div>
                    <div class="quick-badge">
                        <span class="badge-lbl">Bezahlung</span>
                        <strong class="badge-val"><?php echo esc_html( $payment_title ); ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Main Two-Column Layout: Pickup Instructions + Order Receipt -->
        <div class="thankyou-grid">

            <!-- LEFT: Pickup Details & Restaurant Location Ticket -->
            <div class="thankyou-col-left">

                <!-- Golden Pickup Ticket -->
                <div class="thankyou-card pickup-ticket-card">
                    <div class="ticket-header">
                        <div class="ticket-title">
                            <span class="ticket-dot"></span>
                            <h2>Abholinformation</h2>
                        </div>
                        <span class="live-status-pill">● Küche bereitet vor</span>
                    </div>

                    <div class="ticket-time-box">
                        <span class="time-label">Bereit zur Abholung:</span>
                        <strong class="time-value"><?php echo esc_html( $pickup_time ); ?></strong>
                        <p class="time-note">Wir verpacken Ihre Speisen frisch und auslaufsicher für den optimalen Genuss zu Hause.</p>
                    </div>

                    <div class="ticket-details-list">
                        <div class="ticket-row">
                            <span class="ticket-icon">📍</span>
                            <div>
                                <strong>WOW Thai Restaurant</strong>
                                <span>City Point Steyr · 1. Obergeschoss<br>Leopold-Werndl-Straße 2, 4400 Steyr</span>
                            </div>
                        </div>
                        <div class="ticket-row">
                            <span class="ticket-icon">📞</span>
                            <div>
                                <strong>Rückfragen zu Ihrer Bestellung:</strong>
                                <span><a href="tel:+43725245866" class="phone-link">+43 7252 45866</a> (Restaurant-Küche)</span>
                            </div>
                        </div>
                        <div class="ticket-row">
                            <span class="ticket-icon">💳</span>
                            <div>
                                <strong>Zahlungsart:</strong>
                                <span><?php echo esc_html( $payment_title ); ?> (vor Ort in bar oder mit Bankomat-/Kreditkarte)</span>
                            </div>
                        </div>
                        <?php if ( ! empty($note) ) : ?>
                        <div class="ticket-row note-row">
                            <span class="ticket-icon">📝</span>
                            <div>
                                <strong>Ihre Wünsche an die Küche:</strong>
                                <span>„<?php echo esc_html( $note ); ?>“</span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="ticket-actions">
                        <a href="https://maps.google.com/?q=City+Point+Steyr+Leopold-Werndl-Straße+2+4400+Steyr" target="_blank" rel="noopener noreferrer" class="btn-action btn-gold">
                            <span>🗺️ Route planen (Google Maps) ↗</span>
                        </a>
                        <a href="tel:+43725245866" class="btn-action btn-dark">
                            <span>📞 Restaurant anrufen</span>
                        </a>
                    </div>
                </div>

                <!-- Contact & Confirmation Card -->
                <div class="thankyou-card contact-card">
                    <h3>Bestätigung für</h3>
                    <div class="contact-data-grid">
                        <div>
                            <span class="lbl">Name:</span>
                            <strong><?php echo esc_html( $full_name ); ?></strong>
                        </div>
                        <div>
                            <span class="lbl">Telefon:</span>
                            <strong><?php echo esc_html( $phone ); ?></strong>
                        </div>
                        <div class="full">
                            <span class="lbl">Bestätigungs-E-Mail gesendet an:</span>
                            <strong><?php echo esc_html( $email ); ?></strong>
                        </div>
                    </div>
                </div>

            </div>

            <!-- RIGHT: Itemized Receipt Summary -->
            <div class="thankyou-col-right">
                <div class="thankyou-card receipt-card">
                    <div class="receipt-header">
                        <h2>Bestellübersicht</h2>
                        <span class="order-date-pill"><?php echo esc_html( $order_date ); ?></span>
                    </div>

                    <div class="receipt-items-list">
                        <?php foreach ( $order->get_items() as $item_id => $item ) : 
                            $product = $item->get_product();
                            $variant = $item->get_meta('Variante');
                        ?>
                            <div class="receipt-item-row">
                                <div class="item-main">
                                    <div class="item-qty-badge"><?php echo esc_html( $item->get_quantity() ); ?>×</div>
                                    <div class="item-info">
                                        <h4><?php echo esc_html( $item->get_name() ); ?></h4>
                                        <?php if ( ! empty($variant) ) : ?>
                                            <span class="item-variant-pill"><?php echo esc_html( $variant ); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="item-price">
                                    <?php echo wp_kses_post( $order->get_formatted_line_subtotal( $item ) ); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="receipt-totals">
                        <div class="total-row subtotal">
                            <span>Zwischensumme:</span>
                            <strong><?php echo wp_kses_post( $order->get_subtotal_to_display() ); ?></strong>
                        </div>
                        <div class="total-row grand-total">
                            <span>Gesamtbetrag (inkl. MwSt.):</span>
                            <strong class="grand-price"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong>
                        </div>
                    </div>

                    <div class="receipt-footer-links">
                        <a href="<?php echo esc_url( home_url('/menu/') ); ?>" class="btn-menu-return">
                            ← Weitere Speisen zur Speisekarte ansehen
                        </a>
                        <a href="<?php echo esc_url( home_url('/reservations/') ); ?>" class="btn-reservations-link">
                            Tisch für das nächste Mal reservieren ↗
                        </a>
                    </div>
                </div>
            </div>

        </div>

    <?php endif; ?>

<?php else : ?>

    <div class="thankyou-card error-card">
        <p>Vielen Dank! Ihre Bestellung wurde entgegengenommen.</p>
        <a href="<?php echo esc_url( home_url('/menu/') ); ?>" class="btn-gold">Zur Speisekarte</a>
    </div>

<?php endif; ?>

</div>
