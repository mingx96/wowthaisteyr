<?php
/**
 * WOW Thai Steyr - Reservierungsverwaltung (Tischreservierungen)
 * Modernes, intuitives Management-System (inspiriert von Resmio, Wix & FiveStar)
 * 100% nativ, kostenfrei, ohne Drittanbieter-Plugins.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. Registrierung des Custom Post Types "tischanfrage" (Angezeigt als Reservierungen)
function wowthai_register_tischanfrage_cpt() {
    $pending_count = wowthai_get_pending_res_count();
    $menu_badge = $pending_count > 0 ? sprintf( ' <span class="awaiting-mod count-%1$d" style="background:#e6a100;color:#fff;border-radius:10px;padding:2px 7px;font-size:11px;font-weight:700;"><span class="pending-count">%1$d</span></span>', $pending_count ) : '';

    $labels = [
        'name'                  => 'Reservierungen',
        'singular_name'         => 'Reservierung',
        'menu_name'             => 'Reservierungen' . $menu_badge,
        'name_admin_bar'        => 'Reservierung',
        'add_new'               => 'Neue Reservierung eintragen',
        'add_new_item'          => 'Neue Tischreservierung manuell anlegen',
        'new_item'              => 'Neue Reservierung',
        'edit_item'             => 'Reservierung bearbeiten',
        'view_item'             => 'Reservierung ansehen',
        'all_items'             => 'Alle Reservierungen',
        'search_items'          => 'Reservierungen durchsuchen',
        'not_found'             => 'Keine Reservierungen gefunden.',
        'not_found_in_trash'    => 'Keine Reservierungen im Papierkorb.',
    ];

    $args = [
        'labels'             => $labels,
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => 'woocommerce', // Eingebettet in das WooCommerce Menü
        'query_var'          => false,
        'rewrite'            => false,
        'capability_type'    => 'post',
        'has_archive'        => false,
        'hierarchical'       => false,
        'supports'           => [ 'title' ],
    ];

    register_post_type( 'tischanfrage', $args );
}
add_action( 'init', 'wowthai_register_tischanfrage_cpt' );

// 1b. Badge für das WooCommerce-Untermenü anpassen
function wowthai_adjust_tischanfrage_submenu() {
    global $submenu;
    if ( isset( $submenu['woocommerce'] ) ) {
        $pending_count = wowthai_get_pending_res_count();
        $badge = $pending_count > 0 ? sprintf( ' <span class="awaiting-mod count-%1$d" style="background:#e6a100;color:#fff;border-radius:10px;padding:2px 7px;font-size:10px;font-weight:700;"><span class="pending-count">%1$d</span></span>', $pending_count ) : '';
        foreach ( $submenu['woocommerce'] as &$item ) {
            if ( $item[2] === 'edit.php?post_type=tischanfrage' ) {
                $item[0] = '📅 Reservierungen' . $badge;
                break;
            }
        }
    }
}
add_action( 'admin_menu', 'wowthai_adjust_tischanfrage_submenu', 999 );

// 2. Hilfsfunktion: Anzahl ausstehender Anfragen ermitteln
function wowthai_get_pending_res_count() {
    global $wpdb;
    $count = $wpdb->get_var( "
        SELECT COUNT(p.ID) 
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = '_res_status')
        WHERE p.post_type = 'tischanfrage' 
          AND p.post_status != 'trash'
          AND pm.meta_value = 'pending'
    " );
    return intval( $count );
}

// 3. Tab-Filter oben in der Listenansicht (Resmio / Wix Style)
function wowthai_render_res_views( $views ) {
    global $wpdb;

    $current_view = isset( $_GET['res_filter'] ) ? sanitize_text_field( $_GET['res_filter'] ) : 'all';
    $base_url = admin_url( 'edit.php?post_type=tischanfrage' );
    $today = current_time( 'Y-m-d' );

    // Zählungen berechnen
    $total = $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'tischanfrage' AND post_status != 'trash'" );
    $pending = $wpdb->get_var( "SELECT COUNT(p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = '_res_status') WHERE p.post_type = 'tischanfrage' AND p.post_status != 'trash' AND pm.meta_value = 'pending'" );
    $confirmed = $wpdb->get_var( "SELECT COUNT(p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = '_res_status') WHERE p.post_type = 'tischanfrage' AND p.post_status != 'trash' AND pm.meta_value = 'confirmed'" );
    $today_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = '_res_date') WHERE p.post_type = 'tischanfrage' AND p.post_status != 'trash' AND pm.meta_value = %s", $today ) );

    $custom_views = [];

    $custom_views['all'] = sprintf(
        '<a href="%s"%s>Alle <span class="count">(%d)</span></a>',
        esc_url( $base_url ),
        $current_view === 'all' ? ' class="current"' : '',
        $total
    );

    $custom_views['today'] = sprintf(
        '<a href="%s"%s>📅 <strong>Heute</strong> <span class="count">(%d)</span></a>',
        esc_url( add_query_arg( 'res_filter', 'today', $base_url ) ),
        $current_view === 'today' ? ' class="current"' : '',
        $today_count
    );

    $custom_views['pending'] = sprintf(
        '<a href="%s"%s>⏳ Ausstehend (Zu bearbeiten) <span class="count">(%d)</span></a>',
        esc_url( add_query_arg( 'res_filter', 'pending', $base_url ) ),
        $current_view === 'pending' ? ' class="current"' : '',
        $pending
    );

    $custom_views['confirmed'] = sprintf(
        '<a href="%s"%s>✓ Bestätigt <span class="count">(%d)</span></a>',
        esc_url( add_query_arg( 'res_filter', 'confirmed', $base_url ) ),
        $current_view === 'confirmed' ? ' class="current"' : '',
        $confirmed
    );

    return $custom_views;
}
add_filter( 'views_edit-tischanfrage', 'wowthai_render_res_views' );

// 4. SORTIERUNG: Ausstehende & aktive Anfragen NACH OBEN, erledigte/stornierte NACH UNTEN
function wowthai_sort_reservations_priority( $clauses, $query ) {
    global $wpdb;
    if ( ! is_admin() || ! $query->is_main_query() || $query->get( 'post_type' ) !== 'tischanfrage' ) {
        return $clauses;
    }

    $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS pm_res_status ON ({$wpdb->posts}.ID = pm_res_status.post_id AND pm_res_status.meta_key = '_res_status') ";
    $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS pm_res_date ON ({$wpdb->posts}.ID = pm_res_date.post_id AND pm_res_date.meta_key = '_res_date') ";
    $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS pm_res_time ON ({$wpdb->posts}.ID = pm_res_time.post_id AND pm_res_time.meta_key = '_res_time') ";

    // Priorität:
    // 1: pending (Ausstehend - cần xử lý ngay -> Ưu tiên trên cùng!)
    // 2: confirmed (Bestätigt - sắp tới)
    // 3: arrived (Am Tisch - đã vào bàn -> chuyển xuống dưới)
    // 4: cancelled (Storniert - đã hủy -> chuyển xuống dưới cùng)
    $clauses['orderby'] = " 
        CASE 
            WHEN pm_res_status.meta_value = 'pending' THEN 1
            WHEN pm_res_status.meta_value = 'confirmed' THEN 2
            WHEN pm_res_status.meta_value = 'arrived' THEN 3
            WHEN pm_res_status.meta_value = 'cancelled' THEN 4
            ELSE 5 
        END ASC,
        pm_res_date.meta_value ASC,
        pm_res_time.meta_value ASC,
        {$wpdb->posts}.post_date DESC
    ";

    return $clauses;
}
add_filter( 'posts_clauses', 'wowthai_sort_reservations_priority', 10, 2 );

// 5. Query filtern basierend auf Tabs & Datumsfilter
function wowthai_filter_tischanfrage_query( $query ) {
    global $pagenow;
    if ( ! is_admin() || $pagenow !== 'edit.php' || $query->get( 'post_type' ) !== 'tischanfrage' || ! $query->is_main_query() ) {
        return;
    }

    $filter = isset( $_GET['res_filter'] ) ? sanitize_text_field( $_GET['res_filter'] ) : '';
    $meta_query = $query->get( 'meta_query' ) ?: [];

    if ( $filter === 'today' ) {
        $meta_query[] = [
            'key'     => '_res_date',
            'value'   => current_time( 'Y-m-d' ),
            'compare' => '=',
        ];
    } elseif ( $filter === 'pending' || $filter === 'confirmed' || $filter === 'arrived' || $filter === 'cancelled' ) {
        $meta_query[] = [
            'key'     => '_res_status',
            'value'   => $filter,
            'compare' => '=',
        ];
    }

    // Spezieller Datums-Picker Filter
    if ( ! empty( $_GET['filter_res_date'] ) ) {
        $meta_query[] = [
            'key'     => '_res_date',
            'value'   => sanitize_text_field( $_GET['filter_res_date'] ),
            'compare' => '=',
        ];
    }

    if ( ! empty( $meta_query ) ) {
        $query->set( 'meta_query', $meta_query );
    }
}
add_action( 'pre_get_posts', 'wowthai_filter_tischanfrage_query' );

// 6. Datumsfilter-Input
function wowthai_res_restrict_manage_posts() {
    global $typenow;
    if ( $typenow !== 'tischanfrage' ) {
        return;
    }
    $selected_date = isset( $_GET['filter_res_date'] ) ? sanitize_text_field( $_GET['filter_res_date'] ) : '';
    echo '<input type="date" name="filter_res_date" value="' . esc_attr( $selected_date ) . '" style="line-height:28px;height:30px;vertical-align:middle;margin-right:6px;" />';
}
add_action( 'restrict_manage_posts', 'wowthai_res_restrict_manage_posts' );

// 7. Bulk Actions entfernen (keine Massenbearbeitung nötig)
add_filter( 'bulk_actions-edit-tischanfrage', '__return_empty_array' );

// 8. Tabellenspalten definieren (Checkboxen entfernt für sauberen Look)
function wowthai_res_columns( $columns ) {
    $new_cols = [];
    // Keine Checkboxen mehr!
    $new_cols['res_status']   = 'Status';
    $new_cols['res_datetime'] = '📅 Datum & Uhrzeit';
    $new_cols['res_party']    = '👥 Gäste';
    $new_cols['res_guest']    = 'Gast & Kontakt';
    $new_cols['res_notes']    = 'Anmerkungen / Wünsche';
    $new_cols['res_actions']  = '⚡ Schnell-Aktionen';
    return $new_cols;
}
add_filter( 'manage_tischanfrage_posts_columns', 'wowthai_res_columns' );

// 9. Spalteninhalte rendern (Resmio/Wix Design)
function wowthai_res_column_content( $column, $post_id ) {
    $status = get_post_meta( $post_id, '_res_status', true ) ?: 'pending';
    $date   = get_post_meta( $post_id, '_res_date', true );
    $time   = get_post_meta( $post_id, '_res_time', true );
    $party  = get_post_meta( $post_id, '_res_party', true ) ?: 2;
    $name   = get_post_meta( $post_id, '_res_name', true );
    $phone  = get_post_meta( $post_id, '_res_phone', true );
    $email  = get_post_meta( $post_id, '_res_email', true );
    $notes  = get_post_meta( $post_id, '_res_notes', true );

    $today = current_time( 'Y-m-d' );
    $is_today = ( $date === $today );

    switch ( $column ) {
        case 'res_status':
            $status_labels = [
                'pending'   => [ 'label' => 'Ausstehend', 'bg' => '#fff4e5', 'color' => '#b25e00', 'border' => '#ffd599', 'icon' => '⏳' ],
                'confirmed' => [ 'label' => 'Bestätigt',  'bg' => '#e8f7ee', 'color' => '#107a38', 'border' => '#b3e8c4', 'icon' => '✓' ],
                'arrived'   => [ 'label' => 'Am Tisch',   'bg' => '#eaf3fd', 'color' => '#0a5dc2', 'border' => '#b6d7fa', 'icon' => '🍽️' ],
                'cancelled' => [ 'label' => 'Storniert',  'bg' => '#fbeae8', 'color' => '#c22213', 'border' => '#f5b5b0', 'icon' => '✕' ],
            ];
            $cfg = $status_labels[ $status ] ?? $status_labels['pending'];
            echo sprintf(
                '<span class="res-status-badge status-%s" style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:700;background:%s;color:%s;border:1px solid %s;">%s %s</span>',
                esc_attr( $status ),
                $cfg['bg'],
                $cfg['color'],
                $cfg['border'],
                $cfg['icon'],
                esc_html( $cfg['label'] )
            );
            break;

        case 'res_datetime':
            $formatted_date = $date ? date_i18n( 'D, d. M Y', strtotime( $date ) ) : '–';
            echo '<div style="font-size:14px;font-weight:700;color:#1d2327;">' . esc_html( $formatted_date );
            if ( $is_today ) {
                echo ' <span style="background:#e53935;color:#fff;font-size:10px;padding:2px 6px;border-radius:4px;vertical-align:middle;text-transform:uppercase;font-weight:800;margin-left:4px;">Heute</span>';
            }
            echo '</div>';
            echo '<div style="font-size:13px;color:#d97706;font-weight:700;margin-top:2px;">🕒 ' . esc_html( $time ?: '–' ) . ' Uhr</div>';
            break;

        case 'res_party':
            echo '<div style="display:inline-flex;align-items:center;gap:4px;background:#f0f0f1;padding:4px 8px;border-radius:6px;font-size:13px;font-weight:700;color:#2c3338;">';
            echo '👥 ' . esc_html( $party ) . ' ' . ( intval( $party ) === 1 ? 'Person' : 'Personen' );
            echo '</div>';
            break;

        case 'res_guest':
            echo '<div style="font-weight:700;font-size:14px;color:#1d2327;">' . esc_html( $name ?: get_the_title( $post_id ) ) . '</div>';
            if ( $phone && $phone !== '+43' ) {
                echo '<div style="font-size:12px;margin-top:3px;"><a href="tel:' . esc_attr( $phone ) . '" style="color:#0073aa;text-decoration:none;font-weight:600;">📞 ' . esc_html( $phone ) . '</a></div>';
            }
            if ( $email ) {
                echo '<div style="font-size:12px;margin-top:2px;"><a href="mailto:' . esc_attr( $email ) . '" style="color:#646970;text-decoration:none;">✉️ ' . esc_html( $email ) . '</a></div>';
            }
            break;

        case 'res_notes':
            if ( ! empty( $notes ) ) {
                echo '<div style="font-size:12px;color:#444;background:#fbf3d5;border-left:3px solid #e6a100;padding:6px 8px;border-radius:3px;max-width:280px;line-height:1.4;">' . nl2br( esc_html( $notes ) ) . '</div>';
            } else {
                echo '<span style="color:#a7aaad;font-size:12px;">– Keine Besonderheiten –</span>';
            }
            break;

        case 'res_actions':
            $nonce = wp_create_nonce( 'wowthai_res_status_' . $post_id );
            $base_action = admin_url( 'admin-post.php?action=wowthai_res_change_status&res_id=' . $post_id . '&_wpnonce=' . $nonce );

            echo '<div class="res-quick-actions" style="display:flex;gap:6px;flex-wrap:wrap;">';

            if ( $status === 'pending' ) {
                $confirm_url = add_query_arg( 'new_status', 'confirmed', $base_action );
                $cancel_url  = add_query_arg( 'new_status', 'cancelled', $base_action );
                echo '<a href="' . esc_url( $confirm_url ) . '" class="button button-small" style="background:#107a38;color:#fff;border-color:#107a38;font-weight:700;padding:2px 10px;">✓ Bestätigen</a>';
                echo '<a href="' . esc_url( $cancel_url ) . '" class="button button-small" style="color:#c22213;border-color:#f5b5b0;" onclick="return confirm(\'Reservierung wirklich ablehnen/stornieren?\')">✕ Ablehnen</a>';
            } elseif ( $status === 'confirmed' ) {
                $arrived_url = add_query_arg( 'new_status', 'arrived', $base_action );
                $cancel_url  = add_query_arg( 'new_status', 'cancelled', $base_action );
                echo '<a href="' . esc_url( $arrived_url ) . '" class="button button-small" style="background:#0a5dc2;color:#fff;border-color:#0a5dc2;font-weight:700;padding:2px 10px;">🍽️ Am Tisch</a>';
                echo '<a href="' . esc_url( $cancel_url ) . '" class="button button-small" style="color:#c22213;" onclick="return confirm(\'Reservierung stornieren?\')">✕ Stornieren</a>';
            } elseif ( $status === 'arrived' ) {
                echo '<span style="color:#0a5dc2;font-weight:700;font-size:12px;">✓ Gäste sitzen am Tisch</span>';
            } elseif ( $status === 'cancelled' ) {
                $confirm_url = add_query_arg( 'new_status', 'confirmed', $base_action );
                echo '<a href="' . esc_url( $confirm_url ) . '" class="button button-small">↺ Reaktivieren</a>';
            }

            echo '</div>';
            break;
    }
}
add_action( 'manage_tischanfrage_posts_custom_column', 'wowthai_res_column_content', 10, 2 );

// 10. Schnell-Aktionen Handler (Status ändern + E-Mail Benachrichtigung)
function wowthai_handle_res_status_change() {
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( 'Keine ausreichenden Berechtigungen.' );
    }

    $res_id     = isset( $_GET['res_id'] ) ? intval( $_GET['res_id'] ) : 0;
    $new_status = isset( $_GET['new_status'] ) ? sanitize_text_field( $_GET['new_status'] ) : '';

    check_admin_referer( 'wowthai_res_status_' . $res_id );

    if ( ! $res_id || ! in_array( $new_status, [ 'pending', 'confirmed', 'arrived', 'cancelled' ], true ) ) {
        wp_die( 'Ungültige Anfrage.' );
    }

    update_post_meta( $res_id, '_res_status', $new_status );

    // E-Mail an Gast bei Bestätigung oder Stornierung
    $guest_email = get_post_meta( $res_id, '_res_email', true );
    $guest_name  = get_post_meta( $res_id, '_res_name', true );
    $res_date    = get_post_meta( $res_id, '_res_date', true );
    $res_time    = get_post_meta( $res_id, '_res_time', true );
    $res_party   = get_post_meta( $res_id, '_res_party', true );

    if ( ! empty( $guest_email ) && is_email( $guest_email ) ) {
        $formatted_date = $res_date ? date_i18n( 'd.m.Y', strtotime( $res_date ) ) : $res_date;
        $restaurant_name = 'WOW Thai Steyr';
        $restaurant_phone = '+43 7252 74973';

        if ( $new_status === 'confirmed' ) {
            $subject = "Bestätigung: Ihre Tischreservierung bei $restaurant_name";
            $message = "Guten Tag $guest_name,\n\n"
                     . "wir freuen uns, Ihnen mitteilen zu können, dass Ihre Tischreservierung erfolgreich bestätigt wurde!\n\n"
                     . "DETAILS IHRER RESERVIERUNG:\n"
                     . "------------------------------------\n"
                     . "Datum:     $formatted_date\n"
                     . "Uhrzeit:   $res_time Uhr\n"
                     . "Personen:  $res_party\n"
                     . "Ort:       WOW Thai Steyr (City Point, Steyr)\n"
                     . "------------------------------------\n\n"
                     . "Sollten Sie sich verspäten oder Fragen haben, erreichen Sie uns unter $restaurant_phone.\n\n"
                     . "Wir freuen uns auf Ihren Besuch!\n"
                     . "Herzliche Grüße,\nIhr WOW Thai Steyr Team";

            wp_mail( $guest_email, $subject, $message, [ 'Content-Type: text/plain; charset=UTF-8' ] );
        } elseif ( $new_status === 'cancelled' ) {
            $subject = "Information zu Ihrer Reservierung bei $restaurant_name";
            $message = "Guten Tag $guest_name,\n\n"
                     . "leider müssen wir Ihnen mitteilen, dass Ihre Tischreservierung für den $formatted_date um $res_time Uhr storniert oder abgelehnt wurde.\n\n"
                     . "Für Rückfragen oder alternative Termine erreichen Sie uns gerne telefonisch unter $restaurant_phone.\n\n"
                     . "Mit freundlichen Grüßen,\nIhr WOW Thai Steyr Team";

            wp_mail( $guest_email, $subject, $message, [ 'Content-Type: text/plain; charset=UTF-8' ] );
        }
    }

    $redirect_url = wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=tischanfrage' );
    $redirect_url = add_query_arg( [ 'wowthai_notice' => 'res_updated', 'status' => $new_status ], $redirect_url );
    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_post_wowthai_res_change_status', 'wowthai_handle_res_status_change' );

// 11. Erfolgsmeldungen im Admin anzeigen
function wowthai_res_admin_notices() {
    global $typenow;
    if ( $typenow !== 'tischanfrage' || empty( $_GET['wowthai_notice'] ) ) {
        return;
    }
    $status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
    $msg = 'Status der Reservierung wurde erfolgreich aktualisiert.';
    if ( $status === 'confirmed' ) {
        $msg = '✓ Reservierung erfolgreich BESTÄTIGT! Der Gast wurde per E-Mail benachrichtigt.';
    } elseif ( $status === 'cancelled' ) {
        $msg = '✕ Reservierung wurde STORNIERT.';
    } elseif ( $status === 'arrived' ) {
        $msg = '🍽️ Gäste als "Am Tisch" markiert.';
    }

    echo '<div class="notice notice-success is-dismissible" style="border-left-color:#107a38;font-size:14px;padding:10px 14px;font-weight:600;">' . esc_html( $msg ) . '</div>';
}
add_action( 'admin_notices', 'wowthai_res_admin_notices' );

// 12. Meta-Box auf der Bearbeiten-Seite der Reservierung
function wowthai_res_add_meta_box() {
    add_meta_box(
        'wowthai_res_details',
        '📋 Details der Reservierung',
        'wowthai_res_render_meta_box',
        'tischanfrage',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'wowthai_res_add_meta_box' );

function wowthai_res_render_meta_box( $post ) {
    wp_nonce_field( 'wowthai_res_meta_box', 'wowthai_res_nonce' );

    $status = get_post_meta( $post->ID, '_res_status', true ) ?: 'pending';
    $date   = get_post_meta( $post->ID, '_res_date', true ) ?: current_time( 'Y-m-d' );
    $time   = get_post_meta( $post->ID, '_res_time', true ) ?: '18:00';
    $party  = get_post_meta( $post->ID, '_res_party', true ) ?: 2;
    $name   = get_post_meta( $post->ID, '_res_name', true );
    $phone  = get_post_meta( $post->ID, '_res_phone', true );
    $email  = get_post_meta( $post->ID, '_res_email', true );
    $notes  = get_post_meta( $post->ID, '_res_notes', true );
    ?>
    <style>
        .res-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-top: 10px; }
        .res-field { display: flex; flex-direction: column; gap: 5px; }
        .res-field label { font-weight: 700; color: #1d2327; }
        .res-field input, .res-field select, .res-field textarea { width: 100%; max-width: 400px; padding: 6px 10px; border-radius: 4px; }
    </style>
    <div class="res-grid">
        <div class="res-field">
            <label>Status der Reservierung</label>
            <select name="res_status" style="font-weight:700;">
                <option value="pending" <?php selected( $status, 'pending' ); ?>>⏳ Ausstehend (Wartet auf Bestätigung)</option>
                <option value="confirmed" <?php selected( $status, 'confirmed' ); ?>>✓ Bestätigt</option>
                <option value="arrived" <?php selected( $status, 'arrived' ); ?>>🍽️ Am Tisch (Eingetroffen)</option>
                <option value="cancelled" <?php selected( $status, 'cancelled' ); ?>>✕ Storniert / Abgelehnt</option>
            </select>
        </div>
        <div class="res-field">
            <label>Personenanzahl</label>
            <input type="number" name="res_party" min="1" max="50" value="<?php echo esc_attr( $party ); ?>" required>
        </div>
        <div class="res-field">
            <label>Datum</label>
            <input type="date" name="res_date" value="<?php echo esc_attr( $date ); ?>" required>
        </div>
        <div class="res-field">
            <label>Uhrzeit</label>
            <input type="text" name="res_time" value="<?php echo esc_attr( $time ); ?>" placeholder="z. B. 18:30" required>
        </div>
        <div class="res-field">
            <label>Name des Gastes</label>
            <input type="text" name="res_name" value="<?php echo esc_attr( $name ); ?>" required>
        </div>
        <div class="res-field">
            <label>Telefonnummer</label>
            <input type="tel" name="res_phone" value="<?php echo esc_attr( $phone ); ?>">
        </div>
        <div class="res-field">
            <label>E-Mail-Adresse</label>
            <input type="email" name="res_email" value="<?php echo esc_attr( $email ); ?>">
        </div>
        <div class="res-field" style="grid-column: 1 / -1;">
            <label>Besondere Wünsche / Anmerkungen</label>
            <textarea name="res_notes" rows="3"><?php echo esc_textarea( $notes ); ?></textarea>
        </div>
    </div>
    <?php
}

function wowthai_res_save_meta_box( $post_id ) {
    if ( ! isset( $_POST['wowthai_res_nonce'] ) || ! wp_verify_nonce( $_POST['wowthai_res_nonce'], 'wowthai_res_meta_box' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $fields = [ 'res_status', 'res_date', 'res_time', 'res_party', 'res_name', 'res_phone', 'res_email', 'res_notes' ];
    foreach ( $fields as $f ) {
        if ( isset( $_POST[ $f ] ) ) {
            $val = ( $f === 'res_notes' ) ? sanitize_textarea_field( $_POST[ $f ] ) : sanitize_text_field( $_POST[ $f ] );
            update_post_meta( $post_id, '_' . $f, $val );
        }
    }

    // Titel automatisch aktualisieren
    $name  = sanitize_text_field( $_POST['res_name'] ?? 'Gast' );
    $party = intval( $_POST['res_party'] ?? 2 );
    $date  = sanitize_text_field( $_POST['res_date'] ?? '' );
    $time  = sanitize_text_field( $_POST['res_time'] ?? '' );

    remove_action( 'save_post_tischanfrage', 'wowthai_res_save_meta_box' );
    wp_update_post( [
        'ID'         => $post_id,
        'post_title' => "$name · $party Pers. · $date $time",
    ] );
    add_action( 'save_post_tischanfrage', 'wowthai_res_save_meta_box' );
}
add_action( 'save_post_tischanfrage', 'wowthai_res_save_meta_box' );

// 13. CSS zur Bereinigung der Oberfläche (Bulk Actions & unnötige Filter ausblenden)
function wowthai_clean_reservations_ui() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->id !== 'edit-tischanfrage' ) {
        return;
    }
    ?>
    <style>
    /* Bulk actions und unnötige Dropdowns ausblenden */
    .tablenav .actions.bulkactions,
    .tablenav .actions select[name="action"],
    .tablenav .actions select[name="m"] {
        display: none !important;
    }
    .tablenav.top {
        margin-bottom: 10px;
    }
    /* Tabellenspalten sauber formatieren */
    .column-res_status { width: 130px; }
    .column-res_datetime { width: 170px; }
    .column-res_party { width: 120px; }
    .column-res_guest { width: 180px; }
    .column-res_actions { width: 180px; text-align: right; }
    th#res_actions { text-align: right; }
    .res-quick-actions { justify-content: flex-end; }
    </style>
    <?php
}
add_action( 'admin_head', 'wowthai_clean_reservations_ui' );
