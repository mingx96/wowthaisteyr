<?php
/**
 * WOW Thai Steyr - Restaurant Experience, Allergens, Spice Levels & Kitchen Rush Time
 * 100% Native, open-source inspired, zero recurring plugin cost.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. Offizielle EU / Österreich 14 Lebensmittel-Allergene (Codex-Empfehlung)
 */
function wowthai_get_allergen_list() {
    return [
        'A' => [ 'letter' => 'A', 'name' => 'Glutenhaltiges Getreide', 'detail' => 'Weizen, Roggen, Gerste, Hafer, Dinkel' ],
        'B' => [ 'letter' => 'B', 'name' => 'Krebstiere', 'detail' => 'Garnelen, Krebse, Shrimps, Scampi' ],
        'C' => [ 'letter' => 'C', 'name' => 'Ei', 'detail' => 'Hühnerei und Eierzeugnisse' ],
        'D' => [ 'letter' => 'D', 'name' => 'Fisch', 'detail' => 'Alle Fischarten & Kaviar' ],
        'E' => [ 'letter' => 'E', 'name' => 'Erdnuss', 'detail' => 'Erdnüsse und Erdnussöl' ],
        'F' => [ 'letter' => 'F', 'name' => 'Soja', 'detail' => 'Sojabohnen, Tofu, Sojasauce' ],
        'G' => [ 'letter' => 'G', 'name' => 'Milch & Laktose', 'detail' => 'Milch von Säugetieren, Butter, Sahne' ],
        'H' => [ 'letter' => 'H', 'name' => 'Schalenfrüchte', 'detail' => 'Cashewnüsse, Mandeln, Haselnüsse, Walnüsse' ],
        'L' => [ 'letter' => 'L', 'name' => 'Sellerie', 'detail' => 'Knollen- und Staudensellerie' ],
        'M' => [ 'letter' => 'M', 'name' => 'Senf', 'detail' => 'Senfkörner und Senfsaat' ],
        'N' => [ 'letter' => 'N', 'name' => 'Sesam', 'detail' => 'Sesamsamen und Sesamöl' ],
        'O' => [ 'letter' => 'O', 'name' => 'Sulfite / Schwefeldioxid', 'detail' => 'In Wein und getrockneten Früchten' ],
        'P' => [ 'letter' => 'P', 'name' => 'Lupinen', 'detail' => 'Lupinenmehl und Lupinenprotein' ],
        'R' => [ 'letter' => 'R', 'name' => 'Weichtiere', 'detail' => 'Tintenfisch, Muscheln, Calamari' ],
    ];
}

/**
 * 2. Erkennt Schärfegrad eines Gerichts (0 = Mild, 1 = Pikant, 2 = Scharf, 3 = Sehr Scharf)
 */
function wowthai_get_dish_spice_level( $title = '', $description = '', $labels = [] ) {
    $text = mb_strtolower( $title . ' ' . $description . ' ' . implode( ' ', (array) $labels ) );

    if ( strpos( $text, 'sehr scharf' ) !== false || strpos( $text, 'thai-scharf' ) !== false || strpos( $text, 'feurig' ) !== false ) {
        return [
            'level' => 3,
            'label' => 'Sehr scharf',
            'icons' => '🌶️🌶️🌶️',
            'pill'  => '<span class="spice-pill spice-level-3" title="Sehr scharf (Authentisch scharf)">🌶️🌶️🌶️</span>',
        ];
    } elseif ( strpos( $text, 'scharf' ) !== false || strpos( $text, 'rotes curry' ) !== false || strpos( $text, 'grünes curry' ) !== false || strpos( $text, 'chili' ) !== false ) {
        return [
            'level' => 2,
            'label' => 'Scharf',
            'icons' => '🌶️🌶️',
            'pill'  => '<span class="spice-pill spice-level-2" title="Scharf">🌶️🌶️</span>',
        ];
    } elseif ( strpos( $text, 'pikant' ) !== false || strpos( $text, 'leicht scharf' ) !== false || strpos( $text, 'panang' ) !== false || strpos( $text, 'massaman' ) !== false ) {
        return [
            'level' => 1,
            'label' => 'Pikant / Mild scharf',
            'icons' => '🌶️',
            'pill'  => '<span class="spice-pill spice-level-1" title="Leicht pikant">🌶️</span>',
        ];
    }

    return [
        'level' => 0,
        'label' => 'Mild',
        'icons' => '',
        'pill'  => '',
    ];
}

/**
 * 3. Dynamische Küchen-Taktung (Zubereitungszeit)
 */
function wowthai_get_kitchen_prep_time() {
    $val = get_option( 'wowthai_kitchen_prep_time', '25–35 Min.' );
    return $val ?: '25–35 Min.';
}

/**
 * AJAX Handler: Küchenpersonal stellt Taktung im Admin ein
 */
function wowthai_ajax_set_kitchen_prep_time() {
    check_ajax_referer( 'wowthai_admin_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( [ 'message' => 'Keine Berechtigung' ] );
    }

    $time = isset( $_POST['prep_time'] ) ? sanitize_text_field( $_POST['prep_time'] ) : '25–35 Min.';
    update_option( 'wowthai_kitchen_prep_time', $time );

    wp_send_json_success( [ 'prep_time' => $time ] );
}
add_action( 'wp_ajax_wowthai_set_prep_time', 'wowthai_ajax_set_kitchen_prep_time' );

/**
 * 4. Upsell & Cross-Selling Produkte für den Slide-Cart (Thai Drinks & Sides)
 */
function wowthai_get_upsell_suggestions() {
    // Gibt 3 beliebte Zusatzartikel zurück (hohe Marge, blitzschnell zubereitet)
    return [
        [
            'title' => 'Thai Iced Tea (Cha Yen)',
            'desc'  => 'Traditioneller thailändischer Eistee mit Milch & feinem Aroma',
            'price' => '€3,90',
            'raw_price' => 3.90,
            'tag'   => 'Empfehlung',
            'icon'  => '🧋'
        ],
        [
            'title' => 'Krabbenchips (Krupuk)',
            'desc'  => 'Knusprig gebackene Garnelencracker mit süß-saurer Chilisauce',
            'price' => '€3,50',
            'raw_price' => 3.50,
            'tag'   => 'Snack',
            'icon'  => '🦐'
        ],
        [
            'title' => 'Mango Lassi',
            'desc'  => 'Cremiger Mango-Joghurt-Drink, herrlich fruchtig & erfrischend',
            'price' => '€4,20',
            'raw_price' => 4.20,
            'tag'   => 'Beliebt',
            'icon'  => '🥭'
        ]
    ];
}
