<?php
/**
 * WOW Thai Steyr - Restaurant Order Management for WooCommerce Admin (HPOS & CPT)
 * Provides: "Bestellung annehmen" quick actions, Abholzeit column, Kitchen Dashboard Box, and auto-refresh
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. Add Custom Columns to Orders List (HPOS & Legacy)
function wowthai_add_order_admin_columns( $columns ) {
    $new_columns = [];
    foreach ( $columns as $key => $title ) {
        $new_columns[ $key ] = $title;
        // Insert Abholzeit right after order_number
        if ( $key === 'order_number' ) {
            $new_columns['wowthai_pickup'] = '🕒 Abholzeit / Status';
        }
    }
    return $new_columns;
}
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'wowthai_add_order_admin_columns', 25 );
add_filter( 'manage_edit-shop_order_columns', 'wowthai_add_order_admin_columns', 25 );

// 2. Render Custom Column Content
function wowthai_render_order_admin_column( $column, $order ) {
    if ( is_numeric( $order ) ) {
        $order = wc_get_order( $order );
    }
    if ( ! $order || $column !== 'wowthai_pickup' ) {
        return;
    }

    $pickup = $order->get_meta( 'Abholzeit' );
    $accepted_time = $order->get_meta( '_wowthai_accepted_time' );
    $ready_time = $order->get_meta( '_wowthai_ready_time' );
    $note = $order->get_customer_note();

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

    // Kitchen status indicators
    if ( $ready_time ) {
        echo '<div class="kitchen-status ready">🥡 Abholbereit (' . esc_html( $ready_time ) . ')</div>';
    } elseif ( $accepted_time ) {
        echo '<div class="kitchen-status in-prep">🍳 In Zubereitung (seit ' . esc_html( $accepted_time ) . ')</div>';
    } else {
        echo '<div class="kitchen-status waiting">⏳ Wartet auf Annahme</div>';
    }

    if ( ! empty( $note ) ) {
        echo '<div class="order-kitchen-note" title="' . esc_attr( $note ) . '">📝 „' . esc_html( wp_trim_words( $note, 6, '...' ) ) . '“</div>';
    }

    echo '</div>';
}
add_action( 'manage_woocommerce_page_wc-orders_custom_column', 'wowthai_render_order_admin_column', 10, 2 );
add_action( 'manage_shop_order_posts_custom_column', 'wowthai_render_order_admin_column', 10, 2 );

// 3. Add Quick Action Buttons to Order List ("Bestellung annehmen")
function wowthai_add_admin_order_actions( $actions, $order ) {
    if ( is_numeric( $order ) ) {
        $order = wc_get_order( $order );
    }
    if ( ! $order ) {
        return $actions;
    }

    $order_id = $order->get_id();
    $status = $order->get_status();
    $accepted = $order->get_meta( '_wowthai_accepted_time' );
    $ready = $order->get_meta( '_wowthai_ready_time' );

    // If order is active and not yet accepted
    if ( in_array( $status, ['processing', 'pending', 'on-hold'] ) && empty( $accepted ) ) {
        $accept_url = wp_nonce_url(
            admin_url( 'admin-post.php?action=wowthai_accept_order&order_id=' . $order_id ),
            'wowthai_order_action_' . $order_id
        );
        $actions['wowthai_accept'] = [
            'url'    => $accept_url,
            'name'   => 'Bestellung annehmen',
            'action' => 'wowthai-accept-btn',
        ];
    }

    // If accepted and not yet marked ready
    if ( ! empty( $accepted ) && empty( $ready ) && in_array( $status, ['processing', 'pending', 'on-hold'] ) ) {
        $ready_url = wp_nonce_url(
            admin_url( 'admin-post.php?action=wowthai_ready_order&order_id=' . $order_id ),
            'wowthai_order_action_' . $order_id
        );
        $actions['wowthai_ready'] = [
            'url'    => $ready_url,
            'name'   => 'Abholbereit melden',
            'action' => 'wowthai-ready-btn',
        ];
    }

    return $actions;
}
add_filter( 'woocommerce_admin_order_actions', 'wowthai_add_admin_order_actions', 20, 2 );

// 4. Handle "Bestellung annehmen" Action
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
            $order->set_status( 'processing', 'Bestellung vom Restaurant angenommen.' );
        } else {
            $order->add_order_note( 'Bestellung um ' . $current_time . ' Uhr vom Restaurant angenommen. In Zubereitung.' );
        }
        $order->save();
    }

    $redirect_url = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=wc-orders' );
    $redirect_url = add_query_arg( [ 'wowthai_notice' => 'accepted', 'o_id' => $order_id ], $redirect_url );
    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_post_wowthai_accept_order', 'wowthai_handle_accept_order' );

// 5. Handle "Abholbereit melden" Action
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
        $order->add_order_note( 'Bestellung ist fertig zubereitet und abholbereit verpackt (um ' . $current_time . ' Uhr).' );
        $order->save();
    }

    $redirect_url = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=wc-orders' );
    $redirect_url = add_query_arg( [ 'wowthai_notice' => 'ready', 'o_id' => $order_id ], $redirect_url );
    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_post_wowthai_ready_order', 'wowthai_handle_ready_order' );

// 6. Admin Feedback Notices
function wowthai_admin_order_notices() {
    if ( empty( $_GET['wowthai_notice'] ) ) {
        return;
    }
    $notice = sanitize_text_field( $_GET['wowthai_notice'] );
    $o_id = isset( $_GET['o_id'] ) ? intval( $_GET['o_id'] ) : 0;

    if ( $notice === 'accepted' ) {
        echo '<div class="notice notice-success is-dismissible" style="border-left-color: #2bb652; background: #f0faf2; padding: 12px 16px;">';
        echo '<p style="font-size: 14px; margin: 0; color: #155724;"><strong>✓ Bestellung #' . esc_html( $o_id ) . ' angenommen!</strong> Die Küche wurde informiert und bereitet die Speisen jetzt zu.</p>';
        echo '</div>';
    } elseif ( $notice === 'ready' ) {
        echo '<div class="notice notice-success is-dismissible" style="border-left-color: #0073aa; background: #f0f7fb; padding: 12px 16px;">';
        echo '<p style="font-size: 14px; margin: 0; color: #004466;"><strong>🥡 Bestellung #' . esc_html( $o_id ) . ' ist abholbereit!</strong> Die Speisen stehen zur Abholung bereit.</p>';
        echo '</div>';
    }
}
add_action( 'admin_notices', 'wowthai_admin_order_notices' );

// 7. Kitchen Meta Box on Order Edit Page
function wowthai_add_kitchen_meta_box() {
    $screens = [ 'woocommerce_page_wc-orders', 'shop_order' ];
    foreach ( $screens as $screen ) {
        add_meta_box(
            'wowthai_kitchen_console',
            '🍳 WOW Thai Küchen-Konsole & Abholzeit',
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
    $pickup = $order->get_meta( 'Abholzeit' ) ?: 'Schnellstmöglich (ca. 25–35 Min.)';
    $accepted = $order->get_meta( '_wowthai_accepted_time' );
    $ready = $order->get_meta( '_wowthai_ready_time' );
    $phone = $order->get_billing_phone();
    $name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
    $note = $order->get_customer_note();

    $accept_url = wp_nonce_url( admin_url( 'admin-post.php?action=wowthai_accept_order&order_id=' . $order_id ), 'wowthai_order_action_' . $order_id );
    $ready_url = wp_nonce_url( admin_url( 'admin-post.php?action=wowthai_ready_order&order_id=' . $order_id ), 'wowthai_order_action_' . $order_id );
    ?>
    <div class="wowthai-kitchen-panel" style="font-size: 13px;">
        <div style="background: #fff8e1; border: 1px solid #ffe082; padding: 12px; margin-bottom: 12px; border-radius: 4px;">
            <span style="font-size: 11px; text-transform: uppercase; color: #b78103; font-weight: 700; display: block;">Gewünschte Abholzeit:</span>
            <strong style="font-size: 16px; color: #1e1e1e; display: block; margin-top: 2px;">🕒 <?php echo esc_html( $pickup ); ?></strong>
        </div>

        <div style="margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
            <div><strong>Gast:</strong> <?php echo esc_html( $name ); ?></div>
            <div><strong>Telefon:</strong> <a href="tel:<?php echo esc_attr( $phone ); ?>" style="font-weight: 700; text-decoration: underline;"><?php echo esc_html( $phone ); ?></a></div>
            <?php if ( ! empty($note) ) : ?>
                <div style="margin-top: 6px; background: #fdf2e9; padding: 6px 8px; border-left: 3px solid #e67e22;">
                    <strong>Kundenwunsch:</strong> „<?php echo esc_html( $note ); ?>“
                </div>
            <?php endif; ?>
        </div>

        <div class="kitchen-btn-group" style="display: flex; flex-direction: column; gap: 8px;">
            <?php if ( empty( $accepted ) ) : ?>
                <a href="<?php echo esc_url( $accept_url ); ?>" class="button button-primary button-large" style="background: #2bb652; border-color: #249945; text-align: center; font-weight: 700;">
                    ✓ Bestellung annehmen (In Zubereitung)
                </a>
            <?php else : ?>
                <div style="background: #e8f5e9; color: #2e7d32; padding: 6px 10px; font-weight: 700; border-radius: 3px; text-align: center;">
                    ✓ Angenommen um <?php echo esc_html( $accepted ); ?> Uhr
                </div>
            <?php endif; ?>

            <?php if ( ! empty( $accepted ) && empty( $ready ) ) : ?>
                <a href="<?php echo esc_url( $ready_url ); ?>" class="button button-secondary button-large" style="background: #0073aa; color: #fff; border-color: #006291; text-align: center; font-weight: 700;">
                    🥡 Als Abholbereit markieren
                </a>
            <?php elseif ( ! empty( $ready ) ) : ?>
                <div style="background: #e1f5fe; color: #0277bd; padding: 6px 10px; font-weight: 700; border-radius: 3px; text-align: center;">
                    🥡 Abholbereit gemeldet um <?php echo esc_html( $ready ); ?> Uhr
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// 8. Custom Admin CSS & Auto-refresh on Orders Screen
function wowthai_admin_order_styles_and_scripts() {
    $screen = get_current_screen();
    if ( ! $screen || ( $screen->id !== 'woocommerce_page_wc-orders' && $screen->id !== 'edit-shop_order' ) ) {
        return;
    }
    ?>
    <style>
    /* Styling for Abholzeit Column & Kitchen Action Buttons */
    .column-wowthai_pickup {
        min-width: 170px;
        vertical-align: top !important;
    }
    .wowthai-col-pickup {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .pickup-time-badge {
        display: inline-block;
        padding: 3px 8px;
        font-weight: 700;
        font-size: 12px;
        border-radius: 3px;
        line-height: 1.3;
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
        font-weight: 600;
        padding: 2px 6px;
        border-radius: 3px;
        display: inline-block;
        width: fit-content;
    }
    .kitchen-status.waiting {
        background: #fff0f0;
        color: #c92a2a;
        border: 1px solid #ffc9c9;
    }
    .kitchen-status.in-prep {
        background: #e6f4ea;
        color: #137333;
        border: 1px solid #ceead6;
    }
    .kitchen-status.ready {
        background: #e1f5fe;
        color: #0277bd;
        border: 1px solid #b3e5fc;
    }
    .order-kitchen-note {
        font-size: 11px;
        color: #666;
        font-style: italic;
        margin-top: 2px;
    }

    /* Transform order list actions into clear prominent buttons */
    .wc-action-button-wowthai-accept-btn {
        display: inline-flex !important;
        align-items: center !important;
        gap: 4px !important;
        background: #2bb652 !important;
        border-color: #249945 !important;
        color: #fff !important;
        font-weight: 700 !important;
        padding: 3px 10px !important;
        height: auto !important;
        border-radius: 3px !important;
        text-indent: 0 !important;
        width: auto !important;
    }
    .wc-action-button-wowthai-accept-btn::before {
        content: "✓" !important;
        font-weight: 900 !important;
        margin-right: 4px !important;
    }
    .wc-action-button-wowthai-accept-btn::after {
        content: "Annehmen" !important;
        font-size: 11px !important;
        text-transform: uppercase !important;
        letter-spacing: .03em !important;
    }
    .wc-action-button-wowthai-accept-btn:hover {
        background: #239843 !important;
    }

    .wc-action-button-wowthai-ready-btn {
        display: inline-flex !important;
        align-items: center !important;
        gap: 4px !important;
        background: #0073aa !important;
        border-color: #006291 !important;
        color: #fff !important;
        font-weight: 700 !important;
        padding: 3px 10px !important;
        height: auto !important;
        border-radius: 3px !important;
        text-indent: 0 !important;
        width: auto !important;
    }
    .wc-action-button-wowthai-ready-btn::before {
        content: "🥡" !important;
        margin-right: 4px !important;
    }
    .wc-action-button-wowthai-ready-btn::after {
        content: "Abholbereit" !important;
        font-size: 11px !important;
        text-transform: uppercase !important;
        letter-spacing: .03em !important;
    }
    .wc-action-button-wowthai-ready-btn:hover {
        background: #005a87 !important;
    }
    </style>
    <script>
    // Kitchen Live Auto-Refresh (Every 35 seconds if user is idle on orders page)
    (function() {
        let timer = setTimeout(function() {
            // Only auto-reload if not currently typing or editing
            if (!document.querySelector('input:focus, textarea:focus, select:focus')) {
                window.location.reload();
            }
        }, 35000);
    })();
    </script>
    <?php
}
add_action( 'admin_head', 'wowthai_admin_order_styles_and_scripts' );
