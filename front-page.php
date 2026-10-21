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
$wow_dishes = function_exists('wowthai_get_homepage_dishes') ? wowthai_get_homepage_dishes() : [];
$wow_maps = $wow_info['google_maps_url'] ?? 'https://maps.google.com/?q=Leopold-Werndl-Stra%C3%9Fe+2+4400+Steyr';
?>

<main id="main" tabindex="-1">
  <section class="hero hero-full" aria-labelledby="home-title">
    <div class="hero-media" aria-hidden="true">
      <picture>
        <source type="image/webp" srcset="<?php echo esc_url(get_template_directory_uri() . '/assets/img/hero-640.webp'); ?> 640w, <?php echo esc_url(get_template_directory_uri() . '/assets/img/hero-1151.webp'); ?> 1151w" sizes="100vw">
        <img class="hero-photo" src="<?php echo esc_url(get_template_directory_uri() . '/assets/img/hero.jpg'); ?>" alt="Sushi auf einer dunklen Platte" width="1439" height="2560" fetchpriority="high" decoding="async">
      </picture>
    </div>
    <div class="hero-copy">
      <p class="eyebrow"><span class="eyebrow-dot"></span> THAI · SUSHI · WOK &nbsp; / &nbsp; STEYR</p>
      <h1 id="home-title">
        <span class="hero-seo-badge">Authentische Thai-Küche &amp; frisches Sushi in Steyr</span>
        Schön, dass<br><em>Sie da sind.</em>
      </h1>
      <p class="hero-lead">Aromatische Thai-Küche, Wok und Sushi. Genießen Sie Ihre Lieblingsgerichte bei uns im City Point Steyr oder nehmen Sie sie mit nach Hause.</p>
      <div class="button-row hero-actions">
        <a class="button button-dark" href="<?php echo esc_url(home_url('/menu/')); ?>">Speisekarte &amp; bestellen <?php echo wowthai_action_icon(); ?></a>
        <a class="button button-quiet" href="<?php echo esc_url(home_url('/reservations/')); ?>">Tisch reservieren <?php echo wowthai_action_icon(); ?></a>
      </div>
    </div>
    <div class="hero-location">
      <span class="hero-location-mark" aria-hidden="true">📍</span>
      <span>CITY POINT <i>·</i> 1. OBERGESCHOSS</span>
      <span class="hero-location-rule" aria-hidden="true"></span>
      <span>LEOPOLD-WERNDL-STRASSE 2</span>
    </div>
    <a class="hero-scroll" href="<?php echo !empty($wow_dishes) ? '#aus-unserer-kueche' : '#besuch'; ?>"><span></span> WEITER ENTDECKEN</a>
  </section>

  <?php if (!empty($wow_dishes)): ?>
  <section id="aus-unserer-kueche" class="home-discovery wrap" aria-labelledby="home-discovery-title">
    <div class="home-section-heading">
      <div>
        <p class="eyebrow">AUS UNSERER KÜCHE</p>
        <h2 id="home-discovery-title">Worauf haben Sie<br><em>heute Lust?</em></h2>
      </div>
      <a class="home-text-link" href="<?php echo esc_url(home_url('/menu/')); ?>">Die ganze Speisekarte <?php echo wowthai_action_icon(); ?></a>
    </div>
    <div class="home-dishes">
      <?php foreach ($wow_dishes as $dish): ?>
        <a class="home-dish<?php echo !empty($dish['image_url']) ? ' home-dish--photo' : ''; ?>" href="<?php echo esc_url($dish['menu_url']); ?>">
          <?php if (!empty($dish['image_url'])): ?>
            <img src="<?php echo esc_url($dish['image_url']); ?>" alt="" width="640" height="480" loading="lazy" decoding="async">
          <?php endif; ?>
          <div class="home-dish-copy">
            <span class="home-dish-category"><?php echo esc_html($dish['category']); ?></span>
            <h3><?php echo esc_html($dish['title']); ?></h3>
            <?php if (!empty($dish['description'])): ?>
              <p><?php echo esc_html($dish['description']); ?></p>
            <?php endif; ?>
            <div class="home-dish-bottom">
              <span class="home-dish-price"><?php echo esc_html($dish['formatted_price']); ?></span>
              <span class="home-dish-action">Im Menü ansehen <?php echo wowthai_action_icon('action-icon--compact'); ?></span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <p class="home-discovery-note"><?php echo esc_html($wow_hd['sushi_note'] ?? 'Montags Sushi-Ruhetag'); ?>.</p>
  </section>
  <?php endif; ?>

  <section id="besuch" class="home-visit wrap" aria-labelledby="home-visit-title">
    <div class="home-visit-location">
      <p class="eyebrow">IHR BESUCH BEI UNS</p>
      <h2 id="home-visit-title">Mitten in Steyr.<br><em>Einfach vorbeikommen.</em></h2>
      <address>
        <strong><?php echo esc_html($wow_info['name'] ?? 'WOW Thai Steyr'); ?></strong>
        <span><?php echo esc_html($wow_info['street'] ?? 'Leopold-Werndl-Straße 2 (City Point · 1. OG)'); ?></span>
        <span><?php echo esc_html(($wow_info['postal_code'] ?? '4400') . ' ' . ($wow_info['city'] ?? 'Steyr')); ?></span>
      </address>
      <p class="home-arrival-note">Tiefgarage im City Point. Zugang zum Parkhaus über Treppe oder Lift.</p>
      <div class="home-arrival-links">
        <a class="home-text-link" href="<?php echo esc_url($wow_maps); ?>" target="_blank" rel="noopener noreferrer">Route planen <span class="visually-hidden">(neuer Tab)</span><?php echo wowthai_action_icon('action-icon--compact'); ?></a>
        <a class="home-text-link" href="https://citypoint.at/parken-im-city-point/" target="_blank" rel="noopener noreferrer">Parkinformationen <span class="visually-hidden">(neuer Tab)</span><?php echo wowthai_action_icon('action-icon--compact'); ?></a>
      </div>
      <div class="home-contact">
        <a href="tel:<?php echo esc_attr($wow_tel); ?>"><?php echo esc_html($wow_ph); ?></a>
        <a href="mailto:<?php echo esc_attr($wow_mail); ?>"><?php echo esc_html($wow_mail); ?></a>
      </div>
    </div>
    <div class="home-visit-hours">
      <p class="eyebrow">KÜCHENZEITEN</p>
      <div class="home-live-status" data-wowthai-home-status data-state="pending" aria-live="polite" aria-atomic="true" hidden>
        <p class="home-status-label"><span aria-hidden="true"></span><strong data-wowthai-home-label>Zeiten werden geladen …</strong></p>
        <p class="home-today-label">Heute</p>
        <p class="home-today-hours" data-wowthai-today-hours>Aktueller Tagesplan wird geprüft.</p>
        <p class="home-hours-feedback" data-wowthai-home-feedback></p>
      </div>
      <noscript><p>Den Wochenplan finden Sie unten. Aktuelle Änderungen erfragen Sie bitte telefonisch.</p></noscript>
      <details class="home-weekly">
        <summary>Wochenplan ansehen</summary>
        <div data-wowthai-hours-rows>
          <?php foreach (($wow_hd['rows'] ?? []) as $row): ?>
            <p data-wowthai-hours-row><?php echo esc_html($row['days'] . ': ' . $row['hours']); ?></p>
          <?php endforeach; ?>
        </div>
        <p class="home-sushi-note"><?php echo esc_html($wow_hd['sushi_note'] ?? 'Montags Sushi-Ruhetag'); ?>.</p>
      </details>
      <a class="button button-quiet home-reservation" href="<?php echo esc_url(home_url('/reservations/')); ?>">Tisch reservieren <?php echo wowthai_action_icon(); ?></a>
    </div>
  </section>
</main>

<?php get_footer(); ?>
