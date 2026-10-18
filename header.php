<?php
/**
 * WOW Thai Steyr — Global Header Template
 */
if (!defined('ABSPATH')) {
    exit;
}
$wow_info      = function_exists('wowthai_get_restaurant_data') ? wowthai_get_restaurant_data() : [];
$wow_hd        = function_exists('wowthai_hours_display') ? wowthai_hours_display() : [];
$wow_tel       = $wow_info['phone_tel'] ?? '+43725274973';
$wow_ph        = $wow_info['phone'] ?? '+43 7252 74973';
$theme_uri     = get_template_directory_uri();
$special_today = function_exists('wowthai_get_special_closed_day') ? wowthai_get_special_closed_day() : null;

$b_id       = function_exists('wowthai_get_branch_id') ? wowthai_get_branch_id() : 'steyr';
$b_name     = function_exists('wowthai_get_branch_name') ? wowthai_get_branch_name() : ($wow_info['name'] ?? 'WOW Thai Steyr');
$b_short    = function_exists('wowthai_get_branch_short_name') ? wowthai_get_branch_short_name() : 'WoW Thai';
$b_sub      = function_exists('wowthai_get_branch_brand_sub') ? wowthai_get_branch_brand_sub() : 'STEYR · CITY POINT';
$b_street_s = function_exists('wowthai_get_branch_street_short') ? wowthai_get_branch_street_short() : 'City Point · 1. OG';
$has_res    = function_exists('wowthai_branch_has_reservations') ? wowthai_branch_has_reservations() : true;

$seo_meta = function_exists('wowthai_get_seo_meta') ? wowthai_get_seo_meta() : [
    'title'    => 'WOW Thai Steyr | Thailändisches Restaurant & Sushi Bar im City Point',
    'desc'     => 'Authentische thailändische Küche, Wok-Gerichte & frisches Sushi im City Point Steyr (1. OG). Tisch online reservieren oder Takeaway vorbestellen.',
    'og_type'  => 'website',
    'og_image' => $theme_uri . '/assets/img/hero.jpg',
];
$meta_title = $seo_meta['title'];
$meta_desc  = $seo_meta['desc'];
$og_type    = $seo_meta['og_type'] ?? 'website';
$og_image   = $seo_meta['og_image'] ?? ($theme_uri . '/assets/img/hero.jpg');
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?php echo esc_attr($meta_desc); ?>">
  <meta property="og:locale" content="de_AT">
  <meta property="og:type" content="<?php echo esc_attr($og_type); ?>">
  <meta property="og:site_name" content="<?php echo esc_attr($b_name); ?>">
  <meta property="og:title" content="<?php echo esc_attr($meta_title); ?>">
  <meta property="og:description" content="<?php echo esc_attr($meta_desc); ?>">
  <meta property="og:url" content="<?php echo esc_url(home_url(add_query_arg([], $GLOBALS['wp']->request ?? ''))); ?>">
  <meta property="og:image" content="<?php echo esc_url($og_image); ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?php echo esc_attr($meta_title); ?>">
  <meta name="twitter:description" content="<?php echo esc_attr($meta_desc); ?>">
  <meta name="twitter:image" content="<?php echo esc_url($og_image); ?>">
  <meta name="theme-color" content="#100f0d">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="<?php echo esc_attr($b_short); ?>">
  <link rel="manifest" href="<?php echo esc_url($theme_uri . '/assets/pwa/manifest-' . $b_id . '.json'); ?>">
  <link rel="apple-touch-icon" href="<?php echo esc_url($theme_uri . '/assets/img/logo-sm.png'); ?>">
  <?php if (is_front_page()): ?>
  <link rel="preload" as="image" href="<?php echo esc_url($theme_uri . '/assets/img/hero.jpg'); ?>" fetchpriority="high">
  <?php endif; ?>
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a href="#main" class="skip-link visually-hidden">Zum Hauptinhalt springen</a>

<?php if ($special_today !== null): ?>
<div class="special-closed-banner" role="status" style="background:#3a1d16;border-bottom:1px solid #d4a359;color:#fff9f5;padding:10px 16px;text-align:center;font-size:13px;font-weight:600;">
  📢 <strong>Hinweis:</strong> Heute geschlossen (<?php echo esc_html($special_today['reason']); ?>). Vorbestellungen und Reservierungen für unsere nächsten Öffnungstage sind weiterhin online möglich!
</div>
<?php endif; ?>

<header class="site-header wrap<?php if (is_front_page()) echo ' home-header'; ?>">
  <a class="brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr($b_name . ' Startseite'); ?>">
    <img class="brand-logo" src="<?php echo esc_url($theme_uri . '/assets/img/logo-sm.png'); ?>" alt="<?php echo esc_attr($b_name); ?>" width="60" height="40" decoding="async">
    <span><?php echo esc_html($b_sub); ?></span>
  </a>
  <nav class="nav-links" aria-label="Hauptnavigation">
    <a class="<?php if (is_page('menu') || is_page('speisekarte')) echo 'active'; ?>" href="<?php echo esc_url(home_url('/menu/')); ?>">Speisekarte</a>
    <?php if ($has_res): ?>
    <a class="<?php if (is_page('reservations') || is_page('tisch-reservieren')) echo 'active'; ?>" href="<?php echo esc_url(home_url('/reservations/')); ?>">Tisch reservieren</a>
    <?php endif; ?>
    <a class="phone-link" href="tel:<?php echo esc_attr($wow_tel); ?>"><?php echo esc_html($wow_ph); ?></a>
  </nav>
  <div class="header-right-actions">
    <button class="cart-trigger" id="cart-trigger" type="button" aria-label="Bestellung öffnen (0 Artikel)">Bestellung <span id="cart-count" aria-hidden="true">0</span></button>
    <button class="mobile-nav-toggle" id="mobile-nav-toggle" type="button" aria-label="Menü öffnen" aria-expanded="false" aria-controls="mobile-nav-drawer">
      <span class="hamburger-bar"></span>
      <span class="hamburger-bar"></span>
      <span class="hamburger-bar"></span>
    </button>
  </div>
</header>

<!-- Mobile Navigation Drawer / Sheet -->
<div id="mobile-nav-drawer" class="mobile-nav-drawer" aria-hidden="true">
  <div class="mobile-nav-backdrop" id="mobile-nav-backdrop"></div>
  <div class="mobile-nav-panel">
    <div class="mobile-nav-head">
      <div class="brand-mini-info">
        <strong><?php echo esc_html($b_name); ?></strong>
        <span><?php echo esc_html($b_street_s); ?></span>
      </div>
      <button type="button" class="mobile-nav-close" id="mobile-nav-close" aria-label="Menü schließen">×</button>
    </div>
    <nav class="mobile-nav-links">
      <a class="<?php if (is_page('menu') || is_page('speisekarte')) echo 'active'; ?>" href="<?php echo esc_url(home_url('/menu/')); ?>">
        <span class="nav-icon">🍜</span>
        <span>Speisekarte &amp; Bestellen</span>
      </a>
      <?php if ($has_res): ?>
      <a class="<?php if (is_page('reservations') || is_page('tisch-reservieren')) echo 'active'; ?>" href="<?php echo esc_url(home_url('/reservations/')); ?>">
        <span class="nav-icon">📅</span>
        <span>Tisch online reservieren</span>
      </a>
      <?php endif; ?>
      <a href="tel:<?php echo esc_attr($wow_tel); ?>">
        <span class="nav-icon">📞</span>
        <span>Jetzt anrufen (<?php echo esc_html($wow_ph); ?>)</span>
      </a>
      <a href="<?php echo esc_url(home_url('/betrieb/')); ?>" style="opacity:0.75;font-size:12px;margin-top:12px;border-top:1px solid #332d25;padding-top:14px;">
        <span class="nav-icon">🔐</span>
        <span>Personal / Betriebs-Portal</span>
      </a>
    </nav>
    <div class="mobile-nav-footer">
      <p>Öffnungszeiten: <?php echo esc_html($wow_hd['mosa_days'] ?? 'Mo–Sa'); ?> <?php echo esc_html($wow_hd['mosa_hours'] ?? ''); ?><br><?php echo esc_html($wow_hd['sun_days'] ?? 'So'); ?> <?php echo esc_html($wow_hd['sun_hours'] ?? ''); ?></p>
    </div>
  </div>
</div>
