<?php
/**
 * WOW Thai Steyr - Front Page Template
 */
get_header();
?>

<main>
  <section class="hero hero-full" aria-labelledby="home-title">
    <div class="hero-media" aria-hidden="true">
      <img class="hero-photo" src="<?php echo get_template_directory_uri(); ?>/assets/img/hero.jpg" alt="WOW Thai Restaurant Steyr" fetchpriority="high" decoding="async">
    </div>
    <div class="hero-copy">
      <p class="eyebrow"><span class="eyebrow-dot"></span> THAI · SUSHI · WOK &nbsp; / &nbsp; STEYR</p>
      <h1 id="home-title">Schön, dass<br><em>Sie da sind.</em></h1>
      <p class="hero-lead">Lust auf ein aromatisches Curry, frisch gebratenen Wok oder Sushi? Wir freuen uns, Sie im City Point in Steyr zu begrüßen – oder bereiten Ihre Lieblingsgerichte zur Abholung vor.</p>
      <div class="button-row">
        <a class="button button-dark" href="<?php echo esc_url(home_url('/menu/')); ?>">Speisekarte & bestellen <span aria-hidden="true">↗</span></a>
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
        <svg viewBox="0 0 24 24"><path d="M3 4h2l2.1 10.2a2 2 0 0 0 2 1.6h8.8a2 2 0 0 0 1.9-1.4L22 8H6M10 20h.01M18 20h.01"/></svg>
      </span>
      <span>
        <strong>Speisen entdecken</strong>
        <small>In Ruhe auswählen und online bestellen</small>
      </span>
      <span class="quick-arrow" aria-hidden="true">↗</span>
    </a>

    <a class="quick-card" href="<?php echo esc_url(home_url('/reservations/')); ?>">
      <span class="quick-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 17h.01M12 17h.01"/></svg>
      </span>
      <span>
        <strong>Tisch reservieren</strong>
        <small>Wir freuen uns auf Ihren Besuch</small>
      </span>
      <span class="quick-arrow" aria-hidden="true">↗</span>
    </a>

    <a class="quick-card" href="tel:+43725274973">
      <span class="quick-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M21 16.4v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.4 19.4 0 0 1-6-6A19.8 19.8 0 0 1 1.1 3.7 2 2 0 0 1 3.1 1.5h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1L7 9.5a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.7 2z"/></svg>
      </span>
      <span>
        <strong>Lieber persönlich?</strong>
        <small>Rufen Sie uns an: +43 7252 74973</small>
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
        <a href="tel:+43725274973">+43 7252 74973</a>
        <a href="mailto:info@wow-thai-restaurant.at">info@wow-thai-restaurant.at</a>
      </div>
    </div>
    <div class="hours-row">
      <span>Öffnungszeiten</span>
      <span>Mo–Sa&nbsp; 11:00–14:30 · 17:00–22:00</span>
      <span>So&nbsp; 11:30–20:00</span>
    </div>
  </section>
</main>

<?php get_footer(); ?>
