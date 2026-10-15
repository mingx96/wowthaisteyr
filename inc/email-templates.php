<?php
/**
 * WOW Thai Steyr - Branded HTML Email Template
 * Sends luxury dark & gold responsive emails for Orders and Reservations
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function wowthai_send_branded_email( $to, $subject, $headline, $body_html, $action_url = '', $action_label = '' ) {
    $logo_url = get_template_directory_uri() . '/assets/img/logo.png';
    $phone    = '+43 7252 74973';
    $address  = 'City Point · 1. Obergeschoss, Leopold-Werndl-Straße 2, 4400 Steyr';

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
                            Telefon: <a href="tel:<?php echo esc_attr( $phone ); ?>"><?php echo esc_html( $phone ); ?></a> · 
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
        'From: WOW Thai Steyr <' . get_option( 'admin_email' ) . '>',
    ];

    return wp_mail( $to, $subject, $message, $headers );
}
