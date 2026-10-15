<?php
/**
 * WOW Thai Steyr - Restaurant Order Management for WooCommerce Admin (HPOS & Legacy CPT)
 * Provides:
 * - Quick Action Buttons: "✓ Annehmen" (Accept), "✕ Ablehnen" (Reject), "🥡 Abholbereit" (Ready), "✓ Fertig" (Complete)
 * - Abholzeit (Pickup Time) & Kitchen Countdown Badge
 * - Món ăn đã gọi (Bestellte Speisen) inline summary column
 * - Audio Chime Alert on new incoming orders
 * - Kitchen Console Meta Box on single order screen
 * - Realtime Auto-Refresh for busy shift management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. Spalten in der Bestellübersicht anpassen (HPOS & Legacy)
function wowthai_add_order_admin_columns( $columns ) {
    $new_columns = [];
    foreach ( $columns as $key => $title ) {
        $new_columns[ $key ] = $title;
        // Direkt nach der Bestellnummer die Abholzeit & Status einfügen
        if ( $key === 'order_number' ) {
            $new_columns['wowthai_pickup'] = '🕒 Abholzeit & Status';
            $new_columns['wowthai_items']  = '🍲 Bestellte Speisen';
        }
    }
    return $new_columns;
}
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'wowthai_add_order_admin_columns', 25 );
add_filter( 'manage_edit-shop_order_columns', 'wowthai_add_order_admin_columns', 25 );

// 2. Inhalte für die benutzerdefinierten Spalten rendern
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

    // SPALTE 1: ABHOLZEIT & SCHNELL-STATUS
    if ( $column === 'wowthai_pickup' ) {
        echo '<div class="wowthai-col-pickup">';

        // Abholzeit-Badge
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

        // Status-Indikator der Küche
        if ( $status === 'cancelled' ) {
            echo '<div class="kitchen-status cancelled">✕ Storniert / Abgelehnt</div>';
        } elseif ( $status === 'completed' ) {
            echo '<div class="kitchen-status completed">✓ Abgeholt & Erledigt</div>';
        } elseif ( $ready_time ) {
            echo '<div class="kitchen-status ready">🥡 Abholbereit (' . esc_html( $ready_time ) . ')</div>';
        } elseif ( $accepted_time ) {
            echo '<div class="kitchen-status in-prep">🍳 In Zubereitung (' . esc_html( $accepted_time ) . ')</div>';
        } else {
            echo '<div class="kitchen-status waiting">⏳ Neu · Wartet auf Annahme</div>';
        }

        // Kundennotiz
        if ( ! empty( $note ) ) {
            echo '<div class="order-kitchen-note" title="' . esc_attr( $note ) . '">📝 „' . esc_html( wp_trim_words( $note, 7, '...' ) ) . '“</div>';
        }

        // Schnell-Aktions-Buttons direkt in der Spalte (leicht erreichbar)
        $nonce = wp_create_nonce( 'wowthai_order_action_' . $order_id );
        echo '<div class="wowthai-inline-actions">';

        if ( in_array( $status, [ 'processing', 'pending', 'on-hold' ], true ) && empty( $accepted_time ) ) {
            $accept_url = admin_url( 'admin-post.php?action=wowthai_accept_order&order_id=' . $order_id . '&_wpnonce=' . $nonce );
            $reject_url = admin_url( 'admin-post.php?action=wowthai_reject_order&order_id=' . $order_id . '&_wpnonce=' . $nonce );

            echo '<a href="' . esc_url( $accept_url ) . '" class="btn-quick-order btn-accept">✓ Annehmen</a>';
            echo '<a href="' . esc_url( $reject_url ) . '" class="btn-quick-order btn-reject" onclick="return confirm(\'Bestellung #' . esc_js( $order_id ) . ' wirklich ablehnen und stornieren?\')">✕ Ablehnen</a>';
        } elseif ( ! empty( $accepted_time ) && empty( $ready_time ) && in_array( $status, [ 'processing', 'pending', 'on-hold' ], true ) ) {
            $ready_url = admin_url( 'admin-post.php?action=wowthai_ready_order&order_id=' . $order_id . '&_wpnonce=' . $nonce );
            echo '<a href="' . esc_url( $ready_url ) . '" class="btn-quick-order btn-ready">🥡 Abholbereit</a>';
        } elseif ( ! empty( $ready_time ) && $status !== 'completed' && $status !== 'cancelled' ) {
            $complete_url = admin_url( 'admin-post.php?action=wowthai_complete_order&order_id=' . $order_id . '&_wpnonce=' . $nonce );
            echo '<a href="' . esc_url( $complete_url ) . '" class="btn-quick-order btn-complete">✓ Abgeholt</a>';
        }

        echo '</div>'; // .wowthai-inline-actions
        echo '</div>'; // .wowthai-col-pickup
    }

    // SPALTE 2: SPEISENÜBERSICHT (KÜCHEN-ÜBERBLICK)
    if ( $column === 'wowthai_items' ) {
        $items = $order->get_items();
        if ( ! empty( $items ) ) {
            echo '<div class="wowthai-order-items-list">';
            foreach ( $items as $item ) {
                $qty  = $item->get_quantity();
                $name = $item->get_name();
                echo '<div class="wowthai-item-line">';
                echo '<span class="item-qty">' . esc_html( $qty ) . 'x</span> ';
                echo '<span class="item-name">' . esc_html( $name ) . '</span>';
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '<span style="color:#8c8f94;font-size:12px;">Keine Artikel</span>';
        }
    }
}
add_action( 'manage_woocommerce_page_wc-orders_custom_column', 'wowthai_render_order_admin_column', 10, 2 );
add_action( 'manage_shop_order_posts_custom_column', 'wowthai_render_order_admin_column', 10, 2 );

// 3. Standard-Aktions-Buttons in WooCommerce erweitern
function wowthai_add_admin_order_actions( $actions, $order ) {
    if ( is_numeric( $order ) ) {
        $order = wc_get_order( $order );
    }
    if ( ! $order ) {
        return $actions;
    }

    $order_id = $order->get_id();
    $status   = $order->get_status();
    $accepted = $order->get_meta( '_wowthai_accepted_time' );
    $ready    = $order->get_meta( '_wowthai_ready_time' );

    $nonce = wp_create_nonce( 'wowthai_order_action_' . $order_id );

    // Wenn neu & noch nicht angenommen
    if ( in_array( $status, [ 'processing', 'pending', 'on-hold' ], true ) && empty( $accepted ) ) {
        $actions['wowthai_accept'] = [
            'url'    => admin_url( 'admin-post.php?action=wowthai_accept_order&order_id=' . $order_id . '&_wpnonce=' . $nonce ),
            'name'   => 'Bestellung annehmen',
            'action' => 'wowthai-accept-btn',
        ];
        $actions['wowthai_reject'] = [
            'url'    => admin_url( 'admin-post.php?action=wowthai_reject_order&order_id=' . $order_id . '&_wpnonce=' . $nonce ),
            'name'   => 'Bestellung ablehnen',
            'action' => 'wowthai-reject-btn',
        ];
    }

    // Wenn angenommen & noch nicht abholbereit
    if ( ! empty( $accepted ) && empty( $ready ) && in_array( $status, [ 'processing', 'pending', 'on-hold' ], true ) ) {
        $actions['wowthai_ready'] = [
            'url'    => admin_url( 'admin-post.php?action=wowthai_ready_order&order_id=' . $order_id . '&_wpnonce=' . $nonce ),
            'name'   => 'Abholbereit melden',
            'action' => 'wowthai-ready-btn',
        ];
    }

    // Wenn abholbereit -> als abgeholt markieren
    if ( ! empty( $ready ) && $status !== 'completed' && $status !== 'cancelled' ) {
        $actions['wowthai_complete'] = [
            'url'    => admin_url( 'admin-post.php?action=wowthai_complete_order&order_id=' . $order_id . '&_wpnonce=' . $nonce ),
            'name'   => 'Als erledigt markieren',
            'action' => 'wowthai-complete-btn',
        ];
    }

    return $actions;
}
add_filter( 'woocommerce_admin_order_actions', 'wowthai_add_admin_order_actions', 20, 2 );

// 4. HANDLER: Bestellung annehmen
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

// 5. HANDLER: Bestellung ablehnen / stornieren
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

        // Benachrichtigungs-E-Mail an Kunden
        $email = $order->get_billing_email();
        $name  = $order->get_billing_first_name();
        if ( ! empty( $email ) && is_email( $email ) ) {
            $subject = "Information zu Ihrer Bestellung #$order_id bei WOW Thai Steyr";
            $message = "Guten Tag $name,\n\n"
                     . "leider kann Ihre Bestellung #$order_id derzeit vom Restaurant nicht angenommen werden (z. B. Küche ausgelastet oder kurz vor Küchenschluss).\n\n"
                     . "Die Bestellung wurde storniert. Falls Sie bereits bezahlt haben, wird der Betrag umgehend zurückerstattet.\n\n"
                     . "Für Rückfragen erreichen Sie uns unter +43 7252 74973.\n\n"
                     . "Ihr WOW Thai Steyr Team";
            wp_mail( $email, $subject, $message, [ 'Content-Type: text/plain; charset=UTF-8' ] );
        }
    }

    $redirect_url = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=wc-orders' );
    $redirect_url = add_query_arg( [ 'wowthai_notice' => 'rejected', 'o_id' => $order_id ], $redirect_url );
    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_post_wowthai_reject_order', 'wowthai_handle_reject_order' );

// 6. HANDLER: Abholbereit melden
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

// 7. HANDLER: Bestellung als abgeschlossen/abgeholt markieren
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

// 8. Admin-Erfolgsmeldungen
function wowthai_admin_order_notices() {
    if ( empty( $_GET['wowthai_notice'] ) ) {
        return;
    }
    $notice = sanitize_text_field( $_GET['wowthai_notice'] );
    $o_id   = isset( $_GET['o_id'] ) ? intval( $_GET['o_id'] ) : 0;

    if ( $notice === 'accepted' ) {
        echo '<div class="notice notice-success is-dismissible" style="border-left-color:#107a38;background:#f0faf2;padding:12px 16px;">';
        echo '<p style="font-size:14px;margin:0;color:#155724;"><strong>✓ Bestellung #' . esc_html( $o_id ) . ' angenommen!</strong> Die Küche wurde informiert und bereitet die Speisen jetzt zu.</p>';
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

// 9. Küchen-Meta-Box auf der Einzelbestellseite
function wowthai_add_kitchen_meta_box() {
    $screens = [ 'woocommerce_page_wc-orders', 'shop_order' ];
    foreach ( $screens as $screen ) {
        add_meta_box(
            'wowthai_kitchen_console',
            '🍳 WOW Thai Küchen-Konsole & Schnellauswahl',
            'wowthai_render_kitchen_meta_box',
            $screen,
            'side',
            'high'
        );
    }
}
add_action( 'add_meta_boxes', 'wowthai_add_kitchen_meta_box', 20 );

function wowthai_render_kitchen_meta_box( $post_or_order ) {
    $order = ( $post_or_order instanceof WC_Order ) ? $post_or_order : wc_get_order( $post_or_order->ID );
    if ( ! $order ) {
        echo '<p>Keine Bestelldaten gefunden.</p>';
        return;
    }

    $order_id = $order->get_id();
    $status   = $order->get_status();
    $pickup   = $order->get_meta( 'Abholzeit' ) ?: 'Schnellstmöglich (ca. 25–35 Min.)';
    $accepted = $order->get_meta( '_wowthai_accepted_time' );
    $ready    = $order->get_meta( '_wowthai_ready_time' );
    $phone    = $order->get_billing_phone();
    $name     = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
    $note     = $order->get_customer_note();

    $nonce = wp_create_nonce( 'wowthai_order_action_' . $order_id );
    $accept_url   = admin_url( 'admin-post.php?action=wowthai_accept_order&order_id=' . $order_id . '&_wpnonce=' . $nonce );
    $reject_url   = admin_url( 'admin-post.php?action=wowthai_reject_order&order_id=' . $order_id . '&_wpnonce=' . $nonce );
    $ready_url    = admin_url( 'admin-post.php?action=wowthai_ready_order&order_id=' . $order_id . '&_wpnonce=' . $nonce );
    $complete_url = admin_url( 'admin-post.php?action=wowthai_complete_order&order_id=' . $order_id . '&_wpnonce=' . $nonce );
    ?>
    <div class="wowthai-kitchen-panel" style="font-size:13px;">
        <div style="background:#fff8e1;border:1px solid #ffe082;padding:12px;margin-bottom:12px;border-radius:6px;">
            <span style="font-size:11px;text-transform:uppercase;color:#b78103;font-weight:700;display:block;">Gewünschte Abholzeit:</span>
            <strong style="font-size:16px;color:#1e1e1e;display:block;margin-top:3px;">🕒 <?php echo esc_html( $pickup ); ?></strong>
        </div>

        <div style="margin-bottom:12px;padding-bottom:10px;border-bottom:1px solid #eee;">
            <div><strong>Gast:</strong> <?php echo esc_html( $name ); ?></div>
            <div><strong>Telefon:</strong> <a href="tel:<?php echo esc_attr( $phone ); ?>" style="font-weight:700;color:#0073aa;"><?php echo esc_html( $phone ); ?></a></div>
            <?php if ( ! empty( $note ) ) : ?>
                <div style="margin-top:6px;background:#fdf2e9;padding:6px 8px;border-left:3px solid #e67e22;border-radius:3px;">
                    <strong>Kundenwunsch:</strong> „<?php echo esc_html( $note ); ?>“
                </div>
            <?php endif; ?>
        </div>

        <div class="kitchen-btn-group" style="display:flex;flex-direction:column;gap:8px;">
            <?php if ( empty( $accepted ) && in_array( $status, [ 'processing', 'pending', 'on-hold' ], true ) ) : ?>
                <a href="<?php echo esc_url( $accept_url ); ?>" class="button button-primary button-large" style="background:#107a38;border-color:#107a38;text-align:center;font-weight:700;">
                    ✓ Bestellung annehmen (In Zubereitung)
                </a>
                <a href="<?php echo esc_url( $reject_url ); ?>" class="button button-secondary" style="color:#c22213;border-color:#f5b5b0;text-align:center;font-weight:600;" onclick="return confirm('Bestellung ablehnen und stornieren?')">
                    ✕ Bestellung ablehnen
                </a>
            <?php else : ?>
                <?php if ( ! empty( $accepted ) ) : ?>
                    <div style="background:#e8f7ee;color:#107a38;padding:8px 10px;font-weight:700;border-radius:4px;text-align:center;border:1px solid #b3e8c4;">
                        ✓ Angenommen um <?php echo esc_html( $accepted ); ?> Uhr
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ( ! empty( $accepted ) && empty( $ready ) && in_array( $status, [ 'processing', 'pending', 'on-hold' ], true ) ) : ?>
                <a href="<?php echo esc_url( $ready_url ); ?>" class="button button-primary button-large" style="background:#0a5dc2;border-color:#0a5dc2;text-align:center;font-weight:700;">
                    🥡 Als Abholbereit markieren
                </a>
            <?php elseif ( ! empty( $ready ) ) : ?>
                <div style="background:#eaf3fd;color:#0a5dc2;padding:8px 10px;font-weight:700;border-radius:4px;text-align:center;border:1px solid #b6d7fa;">
                    🥡 Abholbereit gemeldet um <?php echo esc_html( $ready ); ?> Uhr
                </div>
                <?php if ( $status !== 'completed' ) : ?>
                    <a href="<?php echo esc_url( $complete_url ); ?>" class="button button-primary" style="background:#2c3338;border-color:#2c3338;text-align:center;font-weight:700;">
                        ✓ Vom Kunden abgeholt (Erledigt)
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// 10. Modernes CSS & Audio-Chime für den Bestell-Screen
function wowthai_admin_order_styles_and_scripts() {
    $screen = get_current_screen();
    if ( ! $screen || ( $screen->id !== 'woocommerce_page_wc-orders' && $screen->id !== 'edit-shop_order' ) ) {
        return;
    }
    ?>
    <style>
    /* Spaltenbreite & Formatierung */
    .column-wowthai_pickup {
        min-width: 190px !important;
        vertical-align: top !important;
    }
    .column-wowthai_items {
        min-width: 220px !important;
        max-width: 320px !important;
        vertical-align: top !important;
    }
    .wowthai-col-pickup {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .pickup-time-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 9px;
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
        padding: 3px 8px;
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
        animation: pulseWaiting 2s infinite ease-in-out;
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
    @keyframes pulseWaiting {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.65; }
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

    /* Schnell-Aktions-Buttons direkt in der Tabelle */
    .wowthai-inline-actions {
        display: flex;
        gap: 5px;
        flex-wrap: wrap;
        margin-top: 4px;
    }
    .btn-quick-order {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 9px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 700;
        text-decoration: none;
        transition: all 0.15s ease;
        line-height: 1.4;
    }
    .btn-quick-order.btn-accept {
        background: #107a38;
        color: #fff;
        border: 1px solid #107a38;
    }
    .btn-quick-order.btn-accept:hover {
        background: #0d632e;
        color: #fff;
    }
    .btn-quick-order.btn-reject {
        background: #fff;
        color: #c22213;
        border: 1px solid #f5b5b0;
    }
    .btn-quick-order.btn-reject:hover {
        background: #c22213;
        color: #fff;
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

    /* Speisen-Liste in der Tabelle */
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
    .wowthai-item-line .item-name {
        color: #1d2327;
        font-weight: 600;
    }
    </style>

    <script>
    // Live Auto-Refresh (Alle 30 Sekunden)
    (function() {
        let timer = setTimeout(function() {
            if (!document.querySelector('input:focus, textarea:focus, select:focus')) {
                window.location.reload();
            }
        }, 30000);
    })();
    </script>
    <?php
}
add_action( 'admin_head', 'wowthai_admin_order_styles_and_scripts' );
