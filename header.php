<!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#100f0d">
  <link rel="preload" as="image" href="<?php echo get_template_directory_uri(); ?>/assets/img/hero.jpg" fetchpriority="high">
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header wrap<?php if (is_front_page()) echo ' home-header'; ?>">
  <a class="brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="WOW Thai Startseite">
    <img class="brand-logo" src="<?php echo get_template_directory_uri(); ?>/assets/img/logo.png" alt="WOW Thai Steyr">
    <span>STEYR <i>·</i> CITY POINT</span>
  </a>
  <nav class="nav-links" aria-label="Hauptnavigation">
    <a class="<?php if (is_page('menu') || is_page('speisekarte')) echo 'active'; ?>" href="<?php echo esc_url(home_url('/menu/')); ?>">Speisekarte</a>
    <a class="<?php if (is_page('reservations') || is_page('tisch-reservieren')) echo 'active'; ?>" href="<?php echo esc_url(home_url('/reservations/')); ?>">Tisch reservieren</a>
    <a class="phone-link" href="tel:+43725274973">+43 7252 74973</a>
  </nav>
  <button class="cart-trigger" id="cart-trigger" type="button" aria-label="Warenkorb öffnen">Bestellung <span id="cart-count">0</span></button>
</header>
