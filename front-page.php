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
$wow_media = get_template_directory_uri() . '/assets/img/homepage/';
$wow_moments = [
    ['file' => 'sushi-moment', 'width' => 720, 'height' => 1280, 'title' => 'Thai & Sushi · 11 Sekunden', 'alt' => 'Gelbe und helle Sushi-Rollen auf Bambusmatten, mit Blumen garniert.'],
    ['file' => 'wok-moment', 'width' => 600, 'height' => 600, 'title' => 'Wok & Meeresfrüchte', 'alt' => 'Nudeln mit Garnelen, Muscheln und Gemüse auf einem blauen Teller.'],
    ['file' => 'thai-moment', 'width' => 960, 'height' => 1280, 'title' => 'Aromen der Thai-Küche', 'alt' => 'Gebratener Fisch mit Reis, Gemüse und einer orangefarbenen Sauce.'],
];
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
    <a class="hero-scroll" href="#einblicke"><span></span> WEITER ENTDECKEN</a>
  </section>

  <section id="einblicke" class="home-atmosphere" aria-labelledby="home-atmosphere-title" data-home-gallery>
    <div class="wrap">
      <div class="home-atmosphere-heading" data-home-reveal>
        <div>
          <p class="eyebrow">MOMENTE AUS DEM WOW THAI</p>
          <h2 id="home-atmosphere-title">Ein kleiner<br><em>Vorgeschmack.</em></h2>
        </div>
        <div class="home-atmosphere-intro">
          <p>Zum Teilen, zum Entdecken, zum Wiederkommen. Machen Sie es sich bei uns gemütlich – mitten im City Point Steyr.</p>
          <a class="home-film-link" href="https://www.facebook.com/wowthairestaurant/videos/wow-thai-restraurant-steyr-city-point/825142112695835/" target="_blank" rel="noopener noreferrer">
            <span class="home-film-mark" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span>
            Original auf Facebook <span class="visually-hidden">(neuer Tab)</span><?php echo wowthai_action_icon('action-icon--compact'); ?>
          </a>
        </div>
      </div>
      <ul class="home-gallery-track" id="home-gallery-track" data-home-gallery-track tabindex="0" aria-label="Bilder aus unserer Küche – horizontal blättern">
        <?php foreach ($wow_moments as $index => $moment): ?>
          <li class="home-gallery-slide home-gallery-slide--<?php echo esc_attr($moment['file']); ?>" data-home-gallery-slide>
            <figure>
              <div class="home-gallery-image">
                <?php if ($index === 0): ?>
                <video id="home-film" data-home-film controls playsinline muted preload="none"
                  poster="<?php echo esc_url($wow_media . 'sushi-moment-720.webp'); ?>" width="720" height="1280"
                  aria-label="Thai-Gerichte und Sushi-Platten auf einem gedeckten Tisch im WOW Thai Steyr. Kurzer Film ohne Ton.">
                  <source src="<?php echo esc_url($wow_media . 'sushi-moments.mp4'); ?>" type="video/mp4">
                  <a href="https://www.facebook.com/wowthairestaurant/videos/wow-thai-restraurant-steyr-city-point/825142112695835/">Sushi-Film auf Facebook ansehen</a>
                </video>
                <?php else: ?>
                <img src="<?php echo esc_url($wow_media . $moment['file'] . '-' . $moment['width'] . '.webp'); ?>"
                  srcset="<?php echo esc_url($wow_media . $moment['file'] . '-480.webp'); ?> 480w, <?php echo esc_url($wow_media . $moment['file'] . '-' . $moment['width'] . '.webp'); ?> <?php echo esc_attr($moment['width']); ?>w"
                  sizes="(max-width: 640px) 82vw, (max-width: 900px) 48vw, 450px"
                  alt="<?php echo esc_attr($moment['alt']); ?>" width="<?php echo esc_attr($moment['width']); ?>" height="<?php echo esc_attr($moment['height']); ?>" loading="lazy" decoding="async">
                <?php endif; ?>
              </div>
              <figcaption><span aria-hidden="true"><?php echo esc_html(sprintf('%02d', $index + 1)); ?></span><?php echo esc_html($moment['title']); ?></figcaption>
            </figure>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="home-gallery-footer">
        <a class="home-instagram-link" href="https://www.instagram.com/wowthairestaurant/" target="_blank" rel="noopener noreferrer">Mehr auf Instagram <span class="visually-hidden">(neuer Tab)</span><?php echo wowthai_action_icon('action-icon--compact'); ?></a>
        <div class="home-gallery-controls" data-home-gallery-controls hidden>
          <span class="home-gallery-status" data-home-gallery-status aria-live="polite" aria-atomic="true"></span>
          <button type="button" class="home-gallery-arrow home-gallery-arrow--prev" data-home-gallery-prev aria-label="Vorheriges Foto" aria-controls="home-gallery-track" disabled><?php echo wowthai_action_icon(); ?></button>
          <button type="button" class="home-gallery-arrow" data-home-gallery-next aria-label="Nächstes Foto" aria-controls="home-gallery-track"><?php echo wowthai_action_icon(); ?></button>
        </div>
      </div>
    </div>
  </section>

  <?php if (!empty($wow_dishes)): ?>
  <section id="aus-unserer-kueche" class="home-discovery wrap" aria-labelledby="home-discovery-title">
    <div class="home-section-heading" data-home-reveal>
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
