<?php
/**
 * WOW Thai Steyr - Restaurant Order Management for WooCommerce Admin (HPOS & Legacy CPT)
 * Optimiert für Küchen- & Thekenteam:
 * - Prioritätssortierung: Aktive Bestellungen (Neu, In Zubereitung) OBEN, erledigte/stornierte UNTEN
 * - Keine Massenaktionen (Bulk Actions) oder Checkboxen
 * - Quick Action Buttons auf der RECHTEN Seite: "✓ Annehmen", "✕ Ablehnen", "🥡 Abholbereit", "✓ Abgeholt"
 * - Tischreservierungs-Box oben auf der Seite
 * - Realtime Auto-Refresh (30s)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. Spalten in der Bestellübersicht aufräumen & anpassen (Nur Relevantes für die Gastronomie)
function wowthai_add_order_admin_columns( $columns ) {
    $new_columns = [];
    foreach ( $columns as $key => $title ) {
        // Unnötige Checkboxen & Versandspalten für Abholrestaurant entfernen
        if ( $key === 'cb' || $key === 'shipping_address' || $key === 'billing_address' ) {
            continue;
        }

        // Nach Bestellnummer die Abholzeit & Speisen einfügen
        if ( $key === 'order_number' ) {
            $new_columns[ $key ] = 'Bestellung / Gast';
            $new_columns['wowthai_pickup'] = '🕒 Abholzeit';
            $new_columns['wowthai_items']  = '🍲 Speisen';
            continue;
        }

        $new_columns[ $key ] = $title;
    }

    // Schnell-Aktionen ganz rechts
    $new_columns['wowthai_quick_actions'] = '⚡ Aktionen';
    return $new_columns;
}
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'wowthai_add_order_admin_columns', 25 );
add_filter( 'manage_edit-shop_order_columns', 'wowthai_add_order_admin_columns', 25 );

// 2. Massenaktionen (Bulk Actions) komplett entfernen
add_filter( 'bulk_actions-woocommerce_page_wc-orders', '__return_empty_array' );
add_filter( 'bulk_actions-edit-shop_order', '__return_empty_array' );

// 3. PRIORITÄTSSORTIERUNG (HPOS & Legacy): Aktive Bestellungen OBEN, Erledigte/Stornierte UNTEN
function wowthai_sort_orders_priority_hpos( $clauses ) {
    global $wpdb;
    if ( ! is_admin() ) {
        return $clauses;
    }
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || ( $screen->id !== 'woocommerce_page_wc-orders' && $screen->id !== 'edit-shop_order' ) ) {
        return $clauses;
    }

    // Falls der Benutzer nicht explizit auf eine Spalte zum Sortieren geklickt hat
    if ( empty( $_GET['orderby'] ) ) {
        $orders_table = $wpdb->prefix . 'wc_orders';
        $clauses['orderby'] = "
            CASE 
                WHEN {$orders_table}.status IN ('wc-pending', 'wc-on-hold') THEN 1
                WHEN {$orders_table}.status = 'wc-processing' THEN 2
                WHEN {$orders_table}.status = 'wc-completed' THEN 3
                WHEN {$orders_table}.status IN ('wc-cancelled', 'wc-refunded', 'wc-failed') THEN 4
                ELSE 5
            END ASC,
            {$orders_table}.date_created_gmt DESC
        ";
    }

    return $clauses;
}
add_filter( 'woocommerce_orders_table_query_clauses', 'wowthai_sort_orders_priority_hpos' );

function wowthai_sort_orders_priority_cpt( $clauses, $query ) {
    global $wpdb;
    if ( ! is_admin() || ! $query->is_main_query() || $query->get( 'post_type' ) !== 'shop_order' ) {
        return $clauses;
    }
    if ( empty( $_GET['orderby'] ) ) {
        $clauses['orderby'] = "
            CASE 
                WHEN {$wpdb->posts}.post_status IN ('wc-pending', 'wc-on-hold') THEN 1
                WHEN {$wpdb->posts}.post_status = 'wc-processing' THEN 2
                WHEN {$wpdb->posts}.post_status = 'wc-completed' THEN 3
                WHEN {$wpdb->posts}.post_status IN ('wc-cancelled', 'wc-refunded', 'wc-failed') THEN 4
                ELSE 5
            END ASC,
            {$wpdb->posts}.post_date DESC
        ";
    }
    return $clauses;
}
add_filter( 'posts_clauses', 'wowthai_sort_orders_priority_cpt', 10, 2 );

// Helper: Varianten & Modifier eines Bestellpostens sauber auslesen
if ( ! function_exists( 'wowthai_get_order_item_customizations' ) ) {
    function wowthai_get_order_item_customizations( $item ): array {
        $lines = [];
        if ( ! is_object( $item ) || ! method_exists( $item, 'get_meta_data' ) ) {
            return $lines;
        }
        $formatted = method_exists( $item, 'get_formatted_meta_data' )
            ? $item->get_formatted_meta_data( '_', true )
            : [];
        if ( ! empty( $formatted ) ) {
            foreach ( $formatted as $meta ) {
                $key = trim( wp_strip_all_tags( (string) ( $meta->display_key ?? $meta->key ?? '' ) ) );
                $val = trim( wp_strip_all_tags( (string) ( $meta->display_value ?? $meta->value ?? '' ) ) );
                if ( $key === '' || $val === '' || strpos( $key, '_' ) === 0 ) {
                    continue;
                }
                $lines[] = $key . ': ' . $val;
            }
            return $lines;
        }
        foreach ( $item->get_meta_data() as $meta ) {
            $data = $meta->get_data();
            $key  = trim( (string) ( $data['key'] ?? '' ) );
            $val  = is_scalar( $data['value'] ?? null ) ? trim( (string) $data['value'] ) : '';
            if ( $key === '' || $val === '' || strpos( $key, '_' ) === 0 ) {
                continue;
            }
            $lines[] = $key . ': ' . $val;
        }
        return $lines;
    }
}

// 4. Inhalte für die benutzerdefinierten Spalten rendern
function wowthai_render_order_admin_column( $column, $order ) {
    if ( is_numeric( $order ) ) {
        $order = wc_get_order( $order );
    }
    if ( ! $order ) {
        return;
    }

    $order_id      = $order->get_id();
    $status        = $order->get_status();
    $pickup        = $order->get_meta( 'Abholzeit' );
    $accepted_time = $order->get_meta( '_wowthai_accepted_time' );
    $ready_time    = $order->get_meta( '_wowthai_ready_time' );
    $note          = $order->get_customer_note();

    // SPALTE 1: ABHOLZEIT & STATUS-BADGE
    if ( $column === 'wowthai_pickup' ) {
        echo '<div class="wowthai-col-pickup">';

        if ( ! empty( $pickup ) ) {
            $is_asap = stripos( $pickup, 'Schnell' ) !== false;
            $pill_class = $is_asap ? 'pill-asap' : 'pill-time';
            echo '<div class="pickup-time-badge ' . esc_attr( $pill_class ) . '">';
            echo $is_asap ? '⚡ ' : '🕒 ';
            echo esc_html( $pickup );
            echo '</div>';
        } else {
            echo '<span class="pickup-time-badge pill-asap">⚡ Schnellstmöglich</span>';
        }

        // Status-Indikator
        if ( $status === 'cancelled' ) {
            echo '<div class="kitchen-status cancelled">✕ Storniert</div>';
        } elseif ( $status === 'completed' ) {
            echo '<div class="kitchen-status completed">✓ Abgeholt</div>';
        } elseif ( $ready_time ) {
            echo '<div class="kitchen-status ready">🥡 Abholbereit (' . esc_html( $ready_time ) . ')</div>';
        } elseif ( $accepted_time ) {
            echo '<div class="kitchen-status in-prep">🍳 In Zubereitung (' . esc_html( $accepted_time ) . ')</div>';
        } else {
            echo '<div class="kitchen-status waiting">⏳ Neu · Wartet</div>';
        }

        if ( ! empty( $note ) ) {
            echo '<div class="order-kitchen-note" title="' . esc_attr( $note ) . '">📝 „' . esc_html( wp_trim_words( $note, 7, '...' ) ) . '“</div>';
        }

        echo '</div>';
    }

    // SPALTE 2: SPEISENÜBERSICHT
    if ( $column === 'wowthai_items' ) {
        $items = $order->get_items();
        if ( ! empty( $items ) ) {
            echo '<div class="wowthai-order-items-list">';
            foreach ( $items as $item ) {
                $qty            = $item->get_quantity();
                $name           = $item->get_name();
                $customizations = wowthai_get_order_item_customizations( $item );
                echo '<div class="wowthai-item-line">';
                echo '<span class="item-qty">' . esc_html( $qty ) . 'x</span> ';
                echo '<span class="item-name">' . esc_html( $name ) . '</span>';
                if ( ! empty( $customizations ) ) {
                    echo '<small class="item-customizations" style="display:block;padding-left:22px;color:#b45309;font-size:11px;font-weight:600;">↳ ' . esc_html( implode( ' · ', $customizations ) ) . '</small>';
                }
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '<span style="color:#8c8f94;font-size:12px;">Keine Artikel</span>';
        }
    }

    // SPALTE 3: QUICK BUTTONS GANZ RECHTS
    if ( $column === 'wowthai_quick_actions' ) {
        $nonce = wp_create_nonce( 'wowthai_order_action_' . $order_id );
        $print_url = admin_url( 'admin-post.php?action=wowthai_print_order_bon&order_id=' . $order_id );
        echo '<div class="wowthai-right-actions">';

        // Immer verfügbar: 80mm Bon-Druck für die Küche
        echo '<button type="button" class="btn-quick-order btn-bon-print" onclick="window.open(\'' . esc_url( $print_url ) . '\', \'bon_print\', \'width=380,height=650,scrollbars=yes\')" title="80mm Küchenbon drucken">🖨️ Bon</button>';

        if ( in_array( $status, [ 'processing', 'pending', 'on-hold' ], true ) && empty( $accepted_time ) ) {
            $accept_url = admin_url( 'admin-post.php?action=wowthai_accept_order&order_id=' . $order_id . '&_wpnonce=' . $nonce );
            $reject_url = admin_url( 'admin-post.php?action=wowthai_reject_order&order_id=' . $order_id . '&_wpnonce=' . $nonce );

            echo '<a href="' . esc_url( $accept_url ) . '" class="btn-quick-order btn-accept" title="Bestellung annehmen">✓ Annehmen</a>';
            echo '<a href="' . esc_url( $reject_url ) . '" class="btn-quick-order btn-reject" title="Bestellung ablehnen" onclick="return confirm(\'Bestellung #' . esc_js( $order_id ) . ' wirklich ablehnen und stornieren?\')">✕ Ablehnen</a>';
        } elseif ( ! empty( $accepted_time ) && empty( $ready_time ) && in_array( $status, [ 'processing', 'pending', 'on-hold' ], true ) ) {
            $ready_url = admin_url( 'admin-post.php?action=wowthai_ready_order&order_id=' . $order_id . '&_wpnonce=' . $nonce );
            echo '<a href="' . esc_url( $ready_url ) . '" class="btn-quick-order btn-ready" title="Meldung: Speisen abholbereit">🥡 Abholbereit</a>';
        } elseif ( ! empty( $ready_time ) && $status !== 'completed' && $status !== 'cancelled' ) {
            $complete_url = admin_url( 'admin-post.php?action=wowthai_complete_order&order_id=' . $order_id . '&_wpnonce=' . $nonce );
            echo '<a href="' . esc_url( $complete_url ) . '" class="btn-quick-order btn-complete" title="Bestellung abgeschlossen">✓ Abgeholt</a>';
        }

        echo '</div>';
    }
}
add_action( 'manage_woocommerce_page_wc-orders_custom_column', 'wowthai_render_order_admin_column', 10, 2 );
add_action( 'manage_shop_order_posts_custom_column', 'wowthai_render_order_admin_column', 10, 2 );

// 5. HANDLER: Bestellung annehmen
function wowthai_handle_accept_order() {
    $order_id = isset( $_GET['order_id'] ) ? intval( $_GET['order_id'] ) : 0;
    check_admin_referer( 'wowthai_order_action_' . $order_id );

    if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( 'Keine Berechtigung.' );
    }

    $order = wc_get_order( $order_id );
    if ( $order ) {
        $current_time = current_time( 'H:i' );
        $order->update_meta_data( '_wowthai_accepted_time', $current_time );
        if ( $order->get_status() !== 'processing' ) {
            $order->set_status( 'processing', 'Bestellung vom Restaurant angenommen. In Zubereitung.' );
        } else {
            $order->add_order_note( 'Bestellung um ' . $current_time . ' Uhr angenommen. Die Küche bereitet die Speisen vor.' );
        }
        $order->save();
    }

    $redirect_url = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=wc-orders' );
    $redirect_url = add_query_arg( [ 'wowthai_notice' => 'accepted', 'o_id' => $order_id ], $redirect_url );
    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_post_wowthai_accept_order', 'wowthai_handle_accept_order' );

// 6. HANDLER: Bestellung ablehnen / stornieren
function wowthai_handle_reject_order() {
    $order_id = isset( $_GET['order_id'] ) ? intval( $_GET['order_id'] ) : 0;
    check_admin_referer( 'wowthai_order_action_' . $order_id );

    if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( 'Keine Berechtigung.' );
    }

    $order = wc_get_order( $order_id );
    if ( $order ) {
        $current_time = current_time( 'H:i' );
        $order->set_status( 'cancelled', 'Bestellung um ' . $current_time . ' Uhr vom Restaurant abgelehnt/storniert.' );
        $order->save();

        // Benachrichtigungs-E-Mail an Kunden mit Branded HTML Template
        $email = $order->get_billing_email();
        $name  = $order->get_billing_first_name();
        if ( ! empty( $email ) && is_email( $email ) ) {
            $subject = "Information zu Ihrer Bestellung #$order_id bei WOW Thai Steyr";
            $headline = "Information zu Ihrer Bestellung #$order_id";
            $body_html = "
                <p>Guten Tag " . esc_html( $name ) . ",</p>
                <p>leider kann Ihre Bestellung <strong>#$order_id</strong> derzeit vom Restaurant nicht angenommen werden (z. B. Küche kurzzeitig ausgelastet oder Küchenschluss).</p>
                <div class=\"card-box\">
                    <p style=\"margin:0;\">Die Bestellung wurde storniert. Falls Sie bereits online bezahlt haben, wird Ihnen der Betrag umgehend vollständig erstattet.</p>
                </div>
                <p>Für direkte Rückfragen erreichen Sie uns gerne telefonisch unter <a href=\"tel:+43725274973\" style=\"color:#d4a359;font-weight:700;\">+43 7252 74973</a>.</p>
                <p>Wir bitten um Ihr Verständnis.<br>Ihr WOW Thai Steyr Team</p>
            ";

            if ( function_exists( 'wowthai_send_branded_email' ) ) {
                wowthai_send_branded_email( $email, $subject, $headline, $body_html );
            } else {
                wp_mail( $email, $subject, strip_tags( $body_html ) );
            }
        }
    }

    $redirect_url = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=wc-orders' );
    $redirect_url = add_query_arg( [ 'wowthai_notice' => 'rejected', 'o_id' => $order_id ], $redirect_url );
    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_post_wowthai_reject_order', 'wowthai_handle_reject_order' );

// 6b. HANDLER: 80mm Küchenbon drucken (Optimiert für Gastro-Thermodrucker)
function wowthai_handle_print_order_bon() {
    $order_id = isset( $_GET['order_id'] ) ? intval( $_GET['order_id'] ) : 0;
    if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( 'Keine Berechtigung.' );
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        wp_die( 'Bestellung #' . esc_html( $order_id ) . ' nicht gefunden.' );
    }

    $abholzeit = trim( (string) $order->get_meta( 'Abholzeit', true ) );
    if ( $abholzeit === '' ) {
        $legacy_time = trim( (string) $order->get_meta( '_wowthai_pickup_time', true ) ); // legacy-fallback
        $legacy_date = trim( (string) $order->get_meta( '_wowthai_pickup_date', true ) ); // legacy-fallback
        $abholzeit   = trim( $legacy_date . ' ' . $legacy_time );
    }
    $is_asap     = ( $abholzeit === '' || stripos( $abholzeit, 'Schnell' ) !== false || stripos( $abholzeit, 'ASAP' ) !== false );
    $pickup_time = $abholzeit !== '' ? $abholzeit : 'Schnellstmöglich (ASAP)';
    $pickup_date = $order->get_date_created() ? $order->get_date_created()->date_i18n( 'd.m.Y · H:i' ) . ' Uhr' : wp_date( 'd.m.Y · H:i' ) . ' Uhr';
    $note        = $order->get_customer_note();
    $items       = $order->get_items();
    $total       = $order->get_formatted_order_total();
    $payment     = $order->get_payment_method_title() ?: 'Barzahlung bei Abholung';
    $customer    = $order->get_formatted_billing_full_name() ?: 'Gast';
    $phone       = $order->get_billing_phone();
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Küchenbon #<?php echo esc_html( $order_id ); ?> · WOW Thai Steyr</title>
        <style>
            @page { margin: 0; size: 80mm auto; }
            * { box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Courier New', monospace, sans-serif;
                width: 74mm;
                margin: 0 auto;
                padding: 4mm 2mm;
                color: #000;
                font-size: 13px;
                line-height: 1.35;
                background: #fff;
            }
            .center { text-align: center; }
            .bold { font-weight: 800; }
            .large { font-size: 16px; }
            .huge { font-size: 20px; letter-spacing: 0.3px; }
            .divider { border-top: 1px dashed #000; margin: 6px 0; }
            .double-divider { border-top: 2px solid #000; margin: 8px 0; }
            .flex-between { display: flex; justify-content: space-between; align-items: baseline; }
            .item-row { display: flex; justify-content: space-between; margin-bottom: 6px; }
            .item-name { flex: 1; padding-right: 6px; }
            .item-variant { font-size: 11px; padding-left: 12px; font-weight: 700; color: #111; margin-top: 2px; }
            .pickup-box {
                border: 2px solid #000;
                padding: 6px;
                margin: 8px 0;
                text-align: center;
                border-radius: 4px;
            }
            .btn-print-bar {
                margin-bottom: 12px;
                text-align: center;
                background: #f0f0f1;
                padding: 8px;
                border-radius: 4px;
            }
            .btn-action {
                padding: 6px 14px;
                font-size: 13px;
                font-weight: 700;
                cursor: pointer;
                border-radius: 4px;
                border: 1px solid #777;
            }
            .btn-primary { background: #107a38; color: #fff; border-color: #107a38; }
            @media print {
                .no-print { display: none !important; }
                body { width: 100%; padding: 0; }
            }
        </style>
    </head>
    <body onload="window.print()">
        <div class="no-print btn-print-bar">
            <button class="btn-action btn-primary" onclick="window.print()">🖨️ Bon Drucken</button>
            <button class="btn-action" onclick="window.close()" style="margin-left:8px;">✕ Schließen</button>
        </div>

        <div class="center bold large">WOW THAI STEYR</div>
        <div class="center" style="font-size: 11px;">City Point · Leopold-Werndl-Str. 2 · Steyr<br>Tel: +43 7252 74973</div>
        <div class="double-divider"></div>

        <div class="pickup-box">
            <div style="font-size: 10px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase;">
                <?php echo $is_asap ? '⚡ ABHOLZEIT (SOFORT)' : '⏰ VORBESTELLUNG / ABHOLTERMIN'; ?>
            </div>
            <div class="huge bold"><?php echo esc_html( $pickup_time ); ?></div>
            <div style="font-size: 11px; font-weight: 600;">Bestellt am: <?php echo esc_html( $pickup_date ); ?></div>
        </div>

        <div class="flex-between" style="font-size: 12px;">
            <span><strong>Bestellung:</strong> #<?php echo esc_html( $order_id ); ?></span>
            <span>Bon: <?php echo esc_html( wp_date( 'H:i' ) ); ?> Uhr</span>
        </div>
        <div style="font-size: 12px;"><strong>Gast:</strong> <?php echo esc_html( $customer ); ?></div>
        <?php if ( $phone ) : ?>
            <div style="font-size: 12px;"><strong>Tel:</strong> <?php echo esc_html( $phone ); ?></div>
        <?php endif; ?>

        <?php if ( ! empty( $note ) ) : ?>
            <div class="divider"></div>
            <div style="background:#eee; padding:4px 6px; font-size:12px; border-left:3px solid #000;">
                <strong>⚠️ KÜCHENNOTIZ:</strong><br><?php echo nl2br( esc_html( $note ) ); ?>
            </div>
        <?php endif; ?>

        <div class="double-divider"></div>
        <div class="bold" style="font-size: 12px; margin-bottom: 6px;">SPEISEN:</div>

        <?php foreach ( $items as $item ) : 
            $qty            = $item->get_quantity();
            $name           = $item->get_name();
            $line_total     = wc_price( $item->get_total() );
            $customizations = wowthai_get_order_item_customizations( $item );
        ?>
            <div class="item-row">
                <div class="item-name">
                    <span class="bold large"><?php echo esc_html( $qty ); ?>x</span> <strong><?php echo esc_html( $name ); ?></strong>
                    <?php foreach ( $customizations as $custom_line ) : ?>
                        <div class="item-variant">+ <?php echo esc_html( $custom_line ); ?></div>
                    <?php endforeach; ?>
                </div>
                <div class="bold"><?php echo strip_tags( $line_total ); ?></div>
            </div>
        <?php endforeach; ?>

        <div class="double-divider"></div>
        <div class="flex-between large bold">
            <span>GESAMT:</span>
            <span><?php echo strip_tags( $total ); ?></span>
        </div>
        <div style="font-size: 11px; margin-top: 3px;"><strong>Zahlung:</strong> <?php echo esc_html( $payment ); ?></div>
        <div class="divider"></div>
        <div class="center" style="font-size: 10px; margin-top: 6px; color:#444;">
            Frisch zubereitet bei WOW Thai Steyr<br>Guten Appetit!
        </div>
    </body>
    </html>
    <?php
    exit;
}
add_action( 'admin_post_wowthai_print_order_bon', 'wowthai_handle_print_order_bon' );

// 7. HANDLER: Abholbereit melden
function wowthai_handle_ready_order() {
    $order_id = isset( $_GET['order_id'] ) ? intval( $_GET['order_id'] ) : 0;
    check_admin_referer( 'wowthai_order_action_' . $order_id );

    if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( 'Keine Berechtigung.' );
    }

    $order = wc_get_order( $order_id );
    if ( $order ) {
        $current_time = current_time( 'H:i' );
        $order->update_meta_data( '_wowthai_ready_time', $current_time );
        $order->add_order_note( 'Speisen sind fertig zubereitet und abholbereit verpackt (um ' . $current_time . ' Uhr).' );
        $order->save();
    }

    $redirect_url = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=wc-orders' );
    $redirect_url = add_query_arg( [ 'wowthai_notice' => 'ready', 'o_id' => $order_id ], $redirect_url );
    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_post_wowthai_ready_order', 'wowthai_handle_ready_order' );

// 8. HANDLER: Bestellung als abgeschlossen/abgeholt markieren
function wowthai_handle_complete_order() {
    $order_id = isset( $_GET['order_id'] ) ? intval( $_GET['order_id'] ) : 0;
    check_admin_referer( 'wowthai_order_action_' . $order_id );

    if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( 'Keine Berechtigung.' );
    }

    $order = wc_get_order( $order_id );
    if ( $order ) {
        $order->set_status( 'completed', 'Bestellung wurde vom Kunden abgeholt und erfolgreich abgeschlossen.' );
        $order->save();
    }

    $redirect_url = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=wc-orders' );
    $redirect_url = add_query_arg( [ 'wowthai_notice' => 'completed', 'o_id' => $order_id ], $redirect_url );
    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_post_wowthai_complete_order', 'wowthai_handle_complete_order' );

// 9. Admin-Erfolgsmeldungen
function wowthai_admin_order_notices() {
    if ( empty( $_GET['wowthai_notice'] ) ) {
        return;
    }
    $notice = sanitize_text_field( $_GET['wowthai_notice'] );
    $o_id   = isset( $_GET['o_id'] ) ? intval( $_GET['o_id'] ) : 0;

    if ( $notice === 'accepted' ) {
        echo '<div class="notice notice-success is-dismissible" style="border-left-color:#107a38;background:#f0faf2;padding:12px 16px;">';
        echo '<p style="font-size:14px;margin:0;color:#155724;"><strong>✓ Bestellung #' . esc_html( $o_id ) . ' angenommen!</strong> Die Küche bereitet die Speisen jetzt zu.</p>';
        echo '</div>';
    } elseif ( $notice === 'rejected' ) {
        echo '<div class="notice notice-error is-dismissible" style="border-left-color:#c22213;background:#fdf2f2;padding:12px 16px;">';
        echo '<p style="font-size:14px;margin:0;color:#9b1c1c;"><strong>✕ Bestellung #' . esc_html( $o_id ) . ' wurde abgelehnt und storniert.</strong> Der Kunde wurde per E-Mail informiert.</p>';
        echo '</div>';
    } elseif ( $notice === 'ready' ) {
        echo '<div class="notice notice-success is-dismissible" style="border-left-color:#0a5dc2;background:#f0f7fb;padding:12px 16px;">';
        echo '<p style="font-size:14px;margin:0;color:#0a5dc2;"><strong>🥡 Bestellung #' . esc_html( $o_id ) . ' ist abholbereit!</strong> Die Speisen stehen zur Abholung bereit.</p>';
        echo '</div>';
    } elseif ( $notice === 'completed' ) {
        echo '<div class="notice notice-success is-dismissible" style="border-left-color:#107a38;background:#f0faf2;padding:12px 16px;">';
        echo '<p style="font-size:14px;margin:0;color:#155724;"><strong>✓ Bestellung #' . esc_html( $o_id ) . ' als abgeschlossen markiert!</strong></p>';
        echo '</div>';
    }
}
add_action( 'admin_notices', 'wowthai_admin_order_notices' );

// 10. TISCHRESERVIERUNGS-BOX OBEN AUF DER BESTELLSEITE (WC-ORDERS)
function wowthai_render_reservations_top_box() {
    $screen = get_current_screen();
    if ( ! $screen || ( $screen->id !== 'woocommerce_page_wc-orders' && $screen->id !== 'edit-shop_order' ) ) {
        return;
    }

    $today = current_time( 'Y-m-d' );
    $today_res = get_posts( [
        'post_type'      => 'tischanfrage',
        'post_status'    => [ 'publish', 'private' ],
        'posts_per_page' => 10,
        'meta_key'       => '_res_date',
        'meta_value'     => $today,
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
    ] );

    $pending_count = function_exists( 'wowthai_get_pending_res_count' ) ? wowthai_get_pending_res_count() : 0;
    $today_count   = count( $today_res );
    $manage_url    = admin_url( 'edit.php?post_type=tischanfrage' );
    ?>
    <div class="wowthai-res-dashboard-box" style="margin: 15px 0 20px 0; background: #fff; border: 1px solid #dcdcde; border-left: 4px solid #d97706; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 14px 18px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:<?php echo $today_count > 0 ? '12px' : '0'; ?>;">
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size:22px;">📅</span>
                <div>
                    <h3 style="margin:0; font-size:15px; font-weight:700; color:#1d2327;">
                        Tischreservierungen Heute (<?php echo esc_html( date_i18n('D, d. M Y') ); ?>)
                    </h3>
                    <div style="font-size:12px; color:#646970; margin-top:2px;">
                        <strong><?php echo $today_count; ?></strong> Tisch(e) reserviert 
                        <?php if ( $pending_count > 0 ) : ?>
                            · <span style="color:#b25e00; font-weight:700;">⏳ <?php echo $pending_count; ?> neu ausstehend</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div>
                <a href="<?php echo esc_url( $manage_url ); ?>" class="button button-secondary" style="font-weight:600; font-size:12px;">
                    Alle Reservierungen verwalten ↗
                </a>
            </div>
        </div>

        <?php if ( $today_count > 0 ) : ?>
            <div style="display:flex; gap:10px; flex-wrap:wrap; padding-top:12px; border-top:1px solid #f0f0f1;">
                <?php foreach ( $today_res as $res ) : 
                    $time   = get_post_meta( $res->ID, '_res_time', true ) ?: '18:00';
                    $party  = get_post_meta( $res->ID, '_res_party', true ) ?: 2;
                    $guest  = get_post_meta( $res->ID, '_res_name', true ) ?: $res->post_title;
                    $status = get_post_meta( $res->ID, '_res_status', true ) ?: 'pending';

                    $st_badge = [
                        'pending'   => [ 'bg' => '#fff4e5', 'col' => '#b25e00', 'lbl' => '⏳ Ausstehend' ],
                        'confirmed' => [ 'bg' => '#e8f7ee', 'col' => '#107a38', 'lbl' => '✓ Bestätigt' ],
                        'arrived'   => [ 'bg' => '#eaf3fd', 'col' => '#0a5dc2', 'lbl' => '🍽️ Am Tisch' ],
                        'cancelled' => [ 'bg' => '#fbeae8', 'col' => '#c22213', 'lbl' => '✕ Storniert' ],
                    ];
                    $st = $st_badge[ $status ] ?? $st_badge['pending'];
                ?>
                    <div style="background:#f6f7f7; border:1px solid #dcdcde; border-radius:6px; padding:7px 12px; font-size:13px; display:inline-flex; align-items:center; gap:8px;">
                        <span style="font-weight:800; color:#d97706;">🕒 <?php echo esc_html( $time ); ?> Uhr</span>
                        <span style="font-weight:700; color:#1d2327;"><?php echo esc_html( $guest ); ?></span>
                        <span style="color:#646970; font-size:12px;">👥 <?php echo esc_html( $party ); ?> Pers.</span>
                        <span style="font-size:11px; font-weight:700; padding:2px 7px; border-radius:10px; background:<?php echo $st['bg']; ?>; color:<?php echo $st['col']; ?>;">
                            <?php echo esc_html( $st['lbl'] ); ?>
                        </span>
                        <?php if ( $status === 'pending' ) : 
                            $nonce = wp_create_nonce( 'wowthai_res_status_' . $res->ID );
                            $conf_url = admin_url( 'admin-post.php?action=wowthai_res_change_status&res_id=' . $res->ID . '&new_status=confirmed&_wpnonce=' . $nonce );
                        ?>
                            <a href="<?php echo esc_url( $conf_url ); ?>" class="button button-small" style="background:#107a38; color:#fff; border-color:#107a38; font-weight:700; font-size:11px; padding:0 6px; line-height:20px; height:22px;" title="Bestätigen">✓</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
        <?php endif; ?>

        <?php
        $current_prep = function_exists( 'wowthai_get_kitchen_prep_time' ) ? wowthai_get_kitchen_prep_time() : '25–35 Min.';
        $prep_options = [
            '15–20 Min.' => '🟢 15–20 Min. (Ruhig)',
            '25–35 Min.' => '🟡 25–35 Min. (Normal)',
            '40–50 Min.' => '🟠 40–50 Min. (Voll)',
            '60+ Min.'   => '🔴 60+ Min. (Rush Hour)',
        ];
        ?>
        <div class="wowthai-prep-time-control" style="margin-top:14px; padding-top:12px; border-top:1px solid #f0f0f1; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div style="display:flex; align-items:center; gap:8px;">
                <span style="font-size:16px;">⏱️</span>
                <div>
                    <strong style="font-size:13px; color:#1d2327;">Aktuelle Küchen-Taktung (Zubereitungszeit):</strong>
                    <span style="font-size:12px; color:#646970; display:block;">Wird Kunden im Warenkorb und an der Kasse live angezeigt</span>
                </div>
            </div>
            <div class="prep-buttons" style="display:flex; gap:6px; flex-wrap:wrap;">
                <?php foreach ( $prep_options as $val => $lbl ) : 
                    $is_active = ( $current_prep === $val );
                ?>
                    <button type="button" class="btn-prep-time" data-time="<?php echo esc_attr( $val ); ?>" style="border:1px solid <?php echo $is_active ? '#d97706' : '#dcdcde'; ?>; background:<?php echo $is_active ? '#fef3d6' : '#f6f7f7'; ?>; color:<?php echo $is_active ? '#8c5b00' : '#2c3338'; ?>; font-weight:<?php echo $is_active ? '700' : '600'; ?>; padding:5px 12px; border-radius:4px; cursor:pointer; font-size:12px;">
                        <?php echo esc_html( $lbl ); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
}
add_action( 'admin_notices', 'wowthai_render_reservations_top_box', 5 );

// 11. Klares CSS für Bestell-Screen (Massenaktionen ausblenden & fokussierte Ansicht)
function wowthai_admin_order_styles_and_scripts() {
    $screen = get_current_screen();
    if ( ! $screen || ( $screen->id !== 'woocommerce_page_wc-orders' && $screen->id !== 'edit-shop_order' ) ) {
        return;
    }
    ?>
    <style>
    /* Massenaktionen und unnötige Dropdowns ausblenden */
    .tablenav .actions.bulkactions,
    .tablenav .actions select[name="action"],
    .tablenav .actions select[name="m"] {
        display: none !important;
    }

    /* Spaltenbreiten & Layout */
    .column-wowthai_pickup {
        min-width: 140px !important;
        vertical-align: top !important;
    }
    .column-wowthai_items {
        min-width: 200px !important;
        max-width: 320px !important;
        vertical-align: top !important;
    }
    .column-wowthai_quick_actions {
        min-width: 170px !important;
        vertical-align: top !important;
        text-align: right !important;
    }
    th#wowthai_quick_actions {
        text-align: right !important;
    }

    .wowthai-col-pickup {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    .pickup-time-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 8px;
        font-weight: 700;
        font-size: 12px;
        border-radius: 4px;
        line-height: 1.3;
        width: fit-content;
    }
    .pickup-time-badge.pill-asap {
        background: #fef3d6;
        color: #8c5b00;
        border: 1px solid #ffd880;
    }
    .pickup-time-badge.pill-time {
        background: #e8f0fe;
        color: #1a73e8;
        border: 1px solid #aecbfa;
    }
    .kitchen-status {
        font-size: 11px;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 4px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        width: fit-content;
    }
    .kitchen-status.waiting {
        background: #fff4e5;
        color: #b25e00;
        border: 1px solid #ffd599;
    }
    .kitchen-status.in-prep {
        background: #e8f7ee;
        color: #107a38;
        border: 1px solid #b3e8c4;
    }
    .kitchen-status.ready {
        background: #eaf3fd;
        color: #0a5dc2;
        border: 1px solid #b6d7fa;
    }
    .kitchen-status.completed {
        background: #f0f0f1;
        color: #50575e;
        border: 1px solid #dcdcde;
    }
    .kitchen-status.cancelled {
        background: #fbeae8;
        color: #c22213;
        border: 1px solid #f5b5b0;
    }
    .order-kitchen-note {
        font-size: 12px;
        color: #50575e;
        font-style: italic;
        background: #fbf3d5;
        padding: 4px 7px;
        border-left: 3px solid #e6a100;
        border-radius: 3px;
        margin-top: 2px;
    }

    /* RECHTE QUICK-BUTTONS */
    .wowthai-right-actions {
        display: flex;
        gap: 6px;
        justify-content: flex-end;
        align-items: center;
        flex-wrap: wrap;
    }
    .btn-quick-order {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 12px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
        transition: all 0.15s ease;
        line-height: 1.4;
        white-space: nowrap;
    }
    .btn-quick-order.btn-accept {
        background: #107a38;
        color: #fff;
        border: 1px solid #107a38;
    }
    .btn-quick-order.btn-accept:hover {
        background: #0d632e;
        color: #fff;
        box-shadow: 0 1px 3px rgba(16,122,56,0.3);
    }
    .btn-quick-order.btn-reject {
        background: #fff;
        color: #c22213;
        border: 1px solid #f5b5b0;
    }
    .btn-quick-order.btn-reject:hover {
        background: #fdf2f2;
        border-color: #c22213;
        color: #c22213;
    }
    .btn-quick-order.btn-ready {
        background: #0a5dc2;
        color: #fff;
        border: 1px solid #0a5dc2;
    }
    .btn-quick-order.btn-ready:hover {
        background: #084999;
        color: #fff;
    }
    .btn-quick-order.btn-complete {
        background: #2c3338;
        color: #fff;
        border: 1px solid #2c3338;
    }
    .btn-quick-order.btn-complete:hover {
        background: #1d2327;
        color: #fff;
    }

    /* Speisen-Liste */
    .wowthai-order-items-list {
        display: flex;
        flex-direction: column;
        gap: 3px;
        font-size: 12px;
    }
    .wowthai-item-line {
        line-height: 1.35;
    }
    .wowthai-item-line .item-qty {
        font-weight: 800;
        color: #d97706;
        background: #fef3d6;
        padding: 1px 5px;
        border-radius: 3px;
        margin-right: 4px;
        display: inline-block;
    }
    .btn-quick-order.btn-bon-print {
        background: #f6f7f7;
        color: #2c3338;
        border: 1px solid #c3c4c7;
        cursor: pointer;
    }
    .btn-quick-order.btn-bon-print:hover {
        background: #f0f0f1;
        border-color: #8c8f94;
        color: #1d2327;
    }

    /* Sound Alert Banner */
    .wowthai-sound-ctrl {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #f0f6fc;
        border: 1px solid #cce5ff;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        color: #004085;
        cursor: pointer;
        user-select: none;
    }
    .wowthai-sound-ctrl.muted {
        background: #f8f9fa;
        border-color: #d6d8db;
        color: #6c757d;
    }
    </style>

    <?php
    // Check if there are active orders requiring immediate staff attention
    $has_new_unaccepted = false;
    if ( function_exists( 'wc_get_orders' ) ) {
        $new_orders = wc_get_orders( [
            'status' => [ 'pending', 'on-hold' ],
            'limit'  => 1,
            'return' => 'ids',
        ] );
        $has_new_unaccepted = ! empty( $new_orders );
    }
    ?>

    <script>
    (function() {
        const hasNewOrders = <?php echo $has_new_unaccepted ? 'true' : 'false'; ?>;

        // Web Audio API Dual-Tone Chime
        function playChime() {
            try {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) return;
                const ctx = new AudioCtx();
                if (ctx.state === 'suspended') {
                    ctx.resume();
                }
                const now = ctx.currentTime;
                
                // Ding (587 Hz - D5)
                const osc1 = ctx.createOscillator();
                const gain1 = ctx.createGain();
                osc1.type = 'sine';
                osc1.frequency.setValueAtTime(587.33, now);
                gain1.gain.setValueAtTime(0.25, now);
                gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.4);
                osc1.connect(gain1);
                gain1.connect(ctx.destination);
                osc1.start(now);
                osc1.stop(now + 0.4);

                // Dong (880 Hz - A5)
                const osc2 = ctx.createOscillator();
                const gain2 = ctx.createGain();
                osc2.type = 'sine';
                osc2.frequency.setValueAtTime(880, now + 0.18);
                gain2.gain.setValueAtTime(0.25, now + 0.18);
                gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.7);
                osc2.connect(gain2);
                gain2.connect(ctx.destination);
                osc2.start(now + 0.18);
                osc2.stop(now + 0.7);
            } catch (e) {
                console.warn('Audio chime notice:', e);
            }
        }

        // Init sound controller pill in subsubsub bar
        document.addEventListener('DOMContentLoaded', function() {
            const isSoundOn = localStorage.getItem('wowthai_kitchen_sound') !== 'off';

            const subsubsub = document.querySelector('ul.subsubsub');
            if (subsubsub) {
                const li = document.createElement('li');
                li.style.float = 'right';
                li.innerHTML = '<span id="wowthai-toggle-sound" class="wowthai-sound-ctrl ' + (isSoundOn ? '' : 'muted') + '">' + 
                               (isSoundOn ? '🔔 Signal: AN' : '🔕 Signal: AUS') + '</span>';
                subsubsub.appendChild(li);

                document.getElementById('wowthai-toggle-sound').addEventListener('click', function() {
                    const current = localStorage.getItem('wowthai_kitchen_sound') !== 'off';
                    const next = !current;
                    localStorage.setItem('wowthai_kitchen_sound', next ? 'on' : 'off');
                    this.className = 'wowthai-sound-ctrl ' + (next ? '' : 'muted');
                    this.textContent = next ? '🔔 Signal: AN' : '🔕 Signal: AUS';
                    if (next) playChime();
                });
            }

            // Prep time switch buttons
            document.querySelectorAll('.btn-prep-time').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const time = this.dataset.time;
                    document.querySelectorAll('.btn-prep-time').forEach(function(b) {
                        b.style.background = '#f6f7f7';
                        b.style.borderColor = '#dcdcde';
                        b.style.color = '#2c3338';
                        b.style.fontWeight = '600';
                    });
                    this.style.background = '#fef3d6';
                    this.style.borderColor = '#d97706';
                    this.style.color = '#8c5b00';
                    this.style.fontWeight = '700';

                    const body = new URLSearchParams({
                        action: 'wowthai_set_prep_time',
                        prep_time: time,
                        nonce: '<?php echo wp_create_nonce("wowthai_admin_nonce"); ?>'
                    });
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: body.toString()
                    });
                });
            });

            // If there are incoming unaccepted orders, alert kitchen!
            if (hasNewOrders && isSoundOn) {
                // Play immediately after user interaction or if context available
                setTimeout(playChime, 800);
            }
        });

        // Live Auto-Refresh (Alle 30 Sekunden)
        setTimeout(function() {
            if (!document.querySelector('input:focus, textarea:focus, select:focus')) {
                window.location.reload();
            }
        }, 30000);
    })();
    </script>
    <?php
}
add_action( 'admin_head', 'wowthai_admin_order_styles_and_scripts' );
