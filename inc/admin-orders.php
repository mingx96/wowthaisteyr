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

    // SPALTE 3: QUICK BUTTONS GANZ RECHTS
    if ( $column === 'wowthai_quick_actions' ) {
        $nonce = wp_create_nonce( 'wowthai_order_action_' . $order_id );
        echo '<div class="wowthai-right-actions">';

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
        } else {
            echo '<span style="color:#a7aaad;font-size:12px;">–</span>';
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

        // Benachrichtigungs-E-Mail an Kunden
        $email = $order->get_billing_email();
        $name  = $order->get_billing_first_name();
        if ( ! empty( $email ) && is_email( $email ) ) {
            $subject = "Information zu Ihrer Bestellung #$order_id bei WOW Thai Steyr";
            $message = "Guten Tag $name,\n\n"
                     . "leider kann Ihre Bestellung #$order_id derzeit vom Restaurant nicht angenommen werden (z. B. Küche ausgelastet oder kurz vor Küchenschluss).\n\n"
                     . "Die Bestellung wurde storniert. Falls Sie bereits bezahlt haben, wird der Betrag umgehend zurückerstattet.\n\n"
                     . "Für Rückfragen erreichen Sie uns gerne unter +43 7252 74973.\n\n"
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
            </div>
        <?php endif; ?>
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
