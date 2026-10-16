<?php
/**
 * WOW Thai Steyr - Branded HTML Email Template
 * Sends luxury dark & gold responsive emails for Orders and Reservations
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function wowthai_send_branded_email( $to, $subject, $headline, $body_html, $action_url = '', $action_label = '' ) {
    $res_info   = function_exists( 'wowthai_get_restaurant_data' ) ? wowthai_get_restaurant_data() : [];
    $logo_url   = get_template_directory_uri() . '/assets/img/logo.png';
    $phone      = $res_info['phone'] ?? '+43 7252 74973';
    $phone_tel  = $res_info['phone_clean'] ?? '+43725274973';
    $address    = 'City Point · 1. Obergeschoss, Leopold-Werndl-Straße 2, 4400 Steyr';
    $from_email = function_exists( 'wowthai_get_notification_email' )
        ? wowthai_get_notification_email()
        : ( $res_info['email'] ?? 'info@wow-thai-restaurant.at' );

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo esc_html( $subject ); ?></title>
        <style>
            body { margin: 0; padding: 0; background-color: #141311; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #f2efe9; }
            table { border-spacing: 0; }
            td { padding: 0; }
            img { border: 0; }
            .wrapper { width: 100%; table-layout: fixed; background-color: #141311; padding-bottom: 40px; }
            .main-table { background-color: #1b1a16; margin: 0 auto; width: 100%; max-width: 600px; border-spacing: 0; border: 1px solid #332f26; border-radius: 8px; overflow: hidden; }
            .header-td { background-color: #100f0d; text-align: center; padding: 28px 20px; border-bottom: 1px solid #2d2921; }
            .eyebrow { color: #d4a359; font-size: 11px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; margin: 0 0 8px 0; }
            .headline { color: #ffffff; font-size: 22px; font-weight: 700; margin: 0; line-height: 1.3; }
            .content-td { padding: 30px 32px; font-size: 15px; line-height: 1.6; color: #d5d0c7; }
            .card-box { background-color: #24221c; border: 1px solid #3d372b; border-left: 4px solid #d4a359; border-radius: 6px; padding: 18px 20px; margin: 22px 0; }
            .btn-gold { display: inline-block; background-color: #d4a359; color: #100f0d !important; text-decoration: none; font-weight: 700; font-size: 14px; padding: 12px 28px; border-radius: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
            .footer-td { background-color: #100f0d; text-align: center; padding: 24px 20px; font-size: 12px; color: #878278; line-height: 1.5; border-top: 1px solid #26231c; }
            .footer-td a { color: #d4a359; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class="wrapper">
            <center>
                <table class="main-table" width="600">
                    <!-- HEADER -->
                    <tr>
                        <td class="header-td">
                            <p class="eyebrow">WOW THAI · RESTAURANT & TAKEAWAY</p>
                            <h1 class="headline"><?php echo esc_html( $headline ); ?></h1>
                        </td>
                    </tr>

                    <!-- CONTENT -->
                    <tr>
                        <td class="content-td">
                            <?php echo $body_html; ?>

                            <?php if ( ! empty( $action_url ) && ! empty( $action_label ) ) : ?>
                                <div style="text-align: center; margin: 28px 0 10px 0;">
                                    <a href="<?php echo esc_url( $action_url ); ?>" class="btn-gold"><?php echo esc_html( $action_label ); ?></a>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- FOOTER -->
                    <tr>
                        <td class="footer-td">
                            <strong>WOW Thai Steyr</strong><br>
                            <?php echo esc_html( $address ); ?><br>
                            Telefon: <a href="tel:<?php echo esc_attr( $phone_tel ); ?>"><?php echo esc_html( $phone ); ?></a> · 
                            Web: <a href="<?php echo esc_url( home_url('/') ); ?>">wow-thai-steyr.at</a>
                            <p style="margin: 12px 0 0 0; padding-top: 10px; border-top: 1px dashed #26231c; font-size: 11px; color: #a39c8e;">
                                ⭐⭐⭐⭐⭐ Hat es Ihnen geschmeckt? Wir freuen uns über Ihre <a href="https://maps.google.com/?q=WOW+Thai+Steyr" target="_blank" style="color: #d4a359; text-decoration: underline;">5-Sterne-Bewertung auf Google</a>!
                            </p>
                        </td>
                    </tr>
                </table>
            </center>
        </div>
    </body>
    </html>
    <?php
    $message = ob_get_clean();

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: WOW Thai Steyr <' . $from_email . '>',
        'Reply-To: WOW Thai Steyr <' . $from_email . '>',
    ];

    $sent = wp_mail( $to, $subject, $message, $headers );
    wowthai_record_email_event( (string) $to, (string) $subject, $sent ? 'sent' : 'failed' );
    return $sent;
}

/**
 * Speichert die letzten 20 E-Mail-Ereignisse (Ringpuffer) zur Diagnose im Admin-Cockpit.
 */
function wowthai_record_email_event( string $to, string $subject, string $status, string $error = '' ): void {
    $log = get_option( 'wowthai_email_log', [] );
    if ( ! is_array( $log ) ) {
        $log = [];
    }
    $now_str = function_exists( 'wowthai_now' ) ? wowthai_now()->format( 'd.m.Y H:i:s' ) : current_time( 'd.m.Y H:i:s' );
    array_unshift( $log, [
        'time'    => $now_str,
        'to'      => $to,
        'subject' => $subject,
        'status'  => $status,
        'error'   => $error,
    ] );
    $log = array_slice( $log, 0, 20 );
    update_option( 'wowthai_email_log', $log, false );
}

add_action( 'wp_mail_failed', function ( $wp_error ) {
    if ( ! is_wp_error( $wp_error ) ) {
        return;
    }
    $data    = (array) $wp_error->get_error_data();
    $to      = isset( $data['to'] ) ? ( is_array( $data['to'] ) ? implode( ', ', $data['to'] ) : (string) $data['to'] ) : '';
    $subject = isset( $data['subject'] ) ? (string) $data['subject'] : '';
    wowthai_record_email_event( $to, $subject, 'failed', $wp_error->get_error_message() );
} );

/**
 * Formatiert die Bestellpositionen inkl. Varianten & Zusatzoptionen für Kunden-E-Mails.
 */
function wowthai_format_order_items_email_html( $order ): string {
    if ( ! is_object( $order ) || ! method_exists( $order, 'get_items' ) ) {
        return '';
    }
    $html = '<div style="margin-top:10px;padding-top:10px;border-top:1px solid #3d372b;">';
    foreach ( $order->get_items() as $item ) {
        $qty  = (int) $item->get_quantity();
        $name = (string) $item->get_name();
        $cust = function_exists( 'wowthai_get_order_item_customizations' )
            ? wowthai_get_order_item_customizations( $item )
            : [];
        $html .= '<p style="margin:0 0 4px 0;font-size:14px;color:#f2efe9;"><strong>' . esc_html( $qty . 'x ' . $name ) . '</strong>';
        if ( ! empty( $cust ) ) {
            $html .= '<br><span style="font-size:12px;color:#d4a359;padding-left:14px;">↳ ' . esc_html( implode( ' · ', $cust ) ) . '</span>';
        }
        $html .= '</p>';
    }
    $total = wp_strip_all_tags( (string) $order->get_formatted_order_total() );
    $html .= '<p style="margin:8px 0 0 0;padding-top:8px;border-top:1px dashed #3d372b;font-size:14px;color:#d4a359;"><strong>Gesamtsumme: ' . esc_html( html_entity_decode( $total, ENT_QUOTES, 'UTF-8' ) ) . '</strong></p>';
    $html .= '</div>';
    return $html;
}
