<?php
/**
 * WOW Thai Steyr - Front Page Template
 */
get_header();
$wow_hd   = function_exists('wowthai_hours_display') ? wowthai_hours_display() : [];
$wow_info = function_exists('wowthai_get_restaurant_data') ? wowthai_get_restaurant_data() : [];
$wow_tel  = $wow_info['phone_tel'] ?? '+43725274973';
$wow_ph   = $wow_info['phone'] ?? '+43 7252 74973';
$wow_mail = $wow_info['email'] ?? 'info@wow-thai-restaurant.at';
?>

<main id="main" tabindex="-1">
  <section class="hero hero-full" aria-labelledby="home-title">
    <div class="hero-media" aria-hidden="true">
      <img class="hero-photo" src="<?php echo esc_url(get_template_directory_uri() . '/assets/img/hero.jpg'); ?>" alt="" width="1600" height="1067" fetchpriority="high" decoding="async">
    </div>
    <div class="hero-copy">
      <p class="eyebrow"><span class="eyebrow-dot"></span> THAI · SUSHI · WOK &nbsp; / &nbsp; STEYR</p>
      <h1 id="home-title">Schön, dass<br><em>Sie da sind.</em></h1>
      <p class="hero-lead">Lust auf ein aromatisches Curry, frisch gebratenen Wok oder Sushi? Wir freuen uns, Sie im City Point in Steyr zu begrüßen – oder bereiten Ihre Lieblingsgerichte zur Abholung vor.</p>
      <div class="button-row">
        <a class="button button-dark" href="<?php echo esc_url(home_url('/menu/')); ?>">Speisekarte &amp; bestellen <span aria-hidden="true">↗</span></a>
        <a class="button button-quiet" href="<?php echo esc_url(home_url('/reservations/')); ?>">Tisch reservieren <span aria-hidden="true">→</span></a>
      </div>
    </div>
    <div class="hero-location">
      <span class="hero-location-mark" aria-hidden="true">📍</span>
      <span>CITY POINT <i>·</i> 1. OBERGESCHOSS</span>
      <span class="hero-location-rule" aria-hidden="true"></span>
      <span>LEOPOLD-WERNDL-STRASSE 2</span>
    </div>
    <a class="hero-scroll" href="#direkteinstieg"><span></span> WEITER ENTDECKEN</a>
  </section>

  <section id="direkteinstieg" class="quick-links wrap" aria-label="Direkteinstieg">
    <a class="quick-card" href="<?php echo esc_url(home_url('/menu/')); ?>">
      <span class="quick-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.1 10.2a2 2 0 0 0 2 1.6h8.8a2 2 0 0 0 1.9-1.4L22 8H6M10 20h.01M18 20h.01"/></svg>
      </span>
      <span>
        <strong>Speisen entdecken</strong>
        <small>In Ruhe auswählen und online bestellen</small>
      </span>
      <span class="quick-arrow" aria-hidden="true">↗</span>
    </a>

    <a class="quick-card" href="<?php echo esc_url(home_url('/reservations/')); ?>">
      <span class="quick-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 17h.01M12 17h.01"/></svg>
      </span>
      <span>
        <strong>Tisch reservieren</strong>
        <small>Wir freuen uns auf Ihren Besuch</small>
      </span>
      <span class="quick-arrow" aria-hidden="true">↗</span>
    </a>

    <a class="quick-card" href="tel:<?php echo esc_attr($wow_tel); ?>">
      <span class="quick-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 16.4v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.4 19.4 0 0 1-6-6A19.8 19.8 0 0 1 1.1 3.7 2 2 0 0 1 3.1 1.5h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1L7 9.5a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.7 2z"/></svg>
      </span>
      <span>
        <strong>Lieber persönlich?</strong>
        <small>Rufen Sie uns an: <?php echo esc_html($wow_ph); ?></small>
      </span>
      <span class="quick-arrow" aria-hidden="true">↗</span>
    </a>
  </section>

  <section class="home-note wrap">
    <p class="eyebrow">BESUCHEN SIE UNS</p>
    <div class="home-note-row">
      <h2>Wir freuen uns<br>auf Ihren Besuch.</h2>
      <div class="location-details">
        <strong>City Point · 1. Obergeschoss</strong>
        <span>Leopold-Werndl-Straße 2, 4400 Steyr</span>
        <a href="tel:<?php echo esc_attr($wow_tel); ?>"><?php echo esc_html($wow_ph); ?></a>
        <a href="mailto:<?php echo esc_attr($wow_mail); ?>"><?php echo esc_html($wow_mail); ?></a>
      </div>
    </div>
    <div class="hours-row">
      <span>Öffnungszeiten</span>
      <span>Mo–Sa&nbsp; <?php echo esc_html($wow_hd['mosa_hours'] ?? ''); ?></span>
      <span>So&nbsp; <?php echo esc_html($wow_hd['sun_hours'] ?? ''); ?></span>
      <span style="color:#d4a359;font-size:12px;font-weight:600;">🍣 Montags Sushi-Ruhetag</span>
    </div>
  </section>
</main>

<?php get_footer(); ?>
