<?php
/**
 * Template Name: Speisekarte & Online-Bestellung
 */
get_header();

$menu_data = wowthai_get_menu_structure();
$opening_status = function_exists('wowthai_get_opening_status') ? wowthai_get_opening_status() : null;
?>

<main>
  <section class="page-intro wrap">
    <p class="eyebrow"><span class="eyebrow-dot"></span> WOW THAI · STEYR</p>
    <div class="page-intro-row">
      <div>
        <h1>Unsere <em>Speisekarte</em></h1>
        <p>Was darf es heute sein? Entdecken Sie unsere Spezialitäten und bestellen Sie zur frischen Zubereitung und bequemen Abholung im City Point Steyr.</p>
      </div>
      <a class="button button-quiet" href="<?php echo esc_url(home_url('/reservations/')); ?>">Tisch reservieren <span aria-hidden="true">↗</span></a>
    </div>

    <!-- Öffnungszeiten / Vorbestellungs-Banner -->
    <?php if ($opening_status): ?>
      <div class="menu-status-banner status-<?php echo esc_attr($opening_status['status_type']); ?>">
        <span class="status-pulse-dot"></span>
        <div class="status-content">
          <strong><?php echo esc_html($opening_status['status_label']); ?>:</strong> 
          <span><?php echo esc_html($opening_status['status_detail']); ?></span>
        </div>
      </div>
    <?php endif; ?>

    <div class="menu-tools">
      <div id="menu-tabs" class="menu-tabs" role="tablist" aria-label="Menüauswahl">
        <button class="menu-tab active" data-menu="restaurant" type="button">Speisekarte Restaurant</button>
        <button class="menu-tab" data-menu="sushi" type="button">Speisekarte Sushi</button>
      </div>

      <!-- Live Search & Filter Bar -->
      <div class="menu-search-filter-bar">
        <div class="search-input-box">
          <span class="search-icon" aria-hidden="true">🔍</span>
          <input type="search" id="dish-search-input" placeholder="Gericht oder Zutat suchen (z. B. Pad Thai, Curry, Frühlingsrollen, Lachs...)" autocomplete="off">
          <button type="button" id="dish-search-clear" class="search-clear-btn" aria-label="Suche löschen" style="display:none;">✕</button>
        </div>
        <div class="filter-chips-row" id="dish-filter-chips">
          <button type="button" class="filter-chip active" data-filter="all">Alle</button>
          <button type="button" class="filter-chip" data-filter="veggie">🌱 Vegetarisch / Vegan</button>
          <button type="button" class="filter-chip" data-filter="spicy">🌶️ Scharf / Pikant</button>
          <button type="button" class="filter-chip" data-filter="popular">⭐ Beliebt</button>
        </div>
      </div>

      <div id="section-nav" class="section-nav" aria-label="Menübereiche">
        <?php foreach ($menu_data['restaurant'] as $sec): ?>
          <a class="section-link" href="#sec-<?php echo esc_attr($sec['slug']); ?>" data-menu-type="restaurant"><?php echo esc_html($sec['name']); ?></a>
        <?php endforeach; ?>
        <?php foreach ($menu_data['sushi'] as $sec): ?>
          <a class="section-link" href="#sec-<?php echo esc_attr($sec['slug']); ?>" data-menu-type="sushi"><?php echo esc_html($sec['name']); ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="wrap menu-content" id="menu-root" aria-live="polite">
    <div class="menu-notice">
      Frisch zubereitet im City Point Steyr. Wählen Sie Ihre Speisen, legen Sie sie in den Warenkorb und holen Sie sie warm und frisch zur gewünschten Zeit ab.
    </div>

    <!-- Live Empty State for Search -->
    <div id="dish-search-empty" class="dish-search-empty" style="display:none;">
      <p style="font-size:32px;margin:0 0 10px 0;">🔍</p>
      <h3>Keine Gerichte gefunden</h3>
      <p>Leider passt kein Gericht zu Ihrer Suche. Bitte versuchen Sie einen anderen Begriff oder setzen Sie den Filter zurück.</p>
      <button type="button" id="dish-reset-search" class="button button-quiet" style="margin-top:12px;">Alle Gerichte anzeigen</button>
    </div>

    <!-- Restaurant Sections -->
    <?php foreach ($menu_data['restaurant'] as $sec): ?>
      <section class="menu-section" id="sec-<?php echo esc_attr($sec['slug']); ?>" data-menu-type="restaurant">
        <div class="section-heading">
          <h2><?php echo esc_html($sec['name']); ?></h2>
          <p class="section-count"><?php echo count($sec['products']); ?> Spezialitäten</p>
        </div>
        <div class="dish-grid">
          <?php foreach ($sec['products'] as $p): 
            $labels_text = !empty($p['labels']) ? implode(' ', $p['labels']) : '';
            $filter_meta = mb_strtolower($p['title'] . ' ' . ($p['description'] ?? '') . ' ' . $labels_text);
          ?>
            <article class="dish-card" 
              data-dish-title="<?php echo esc_attr(mb_strtolower($p['title'])); ?>"
              data-dish-search="<?php echo esc_attr($filter_meta); ?>"
              data-dish-labels="<?php echo esc_attr(mb_strtolower($labels_text)); ?>">
              <?php if (!empty($p['image_url'])): ?>
                <img class="dish-photo" src="<?php echo esc_url($p['image_url']); ?>" alt="<?php echo esc_attr($p['title']); ?>" loading="lazy">
              <?php endif; ?>
              <div class="dish-topline">
                <h3><?php echo esc_html($p['title']); ?></h3>
                <span class="dish-price"><?php echo esc_html($p['formatted_price']); ?></span>
              </div>
              <?php if (!empty($p['description'])): ?>
                <p class="dish-desc"><?php echo esc_html($p['description']); ?></p>
              <?php endif; ?>
              <?php if (!empty($p['labels'])): ?>
                <div class="dish-labels">
                  <?php foreach ($p['labels'] as $lbl): ?>
                    <span class="dish-label"><?php echo esc_html($lbl); ?></span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              <div class="dish-actions">
                <button type="button" class="dish-control"
                  data-product-id="<?php echo esc_attr($p['id']); ?>"
                  data-has-variants="<?php echo $p['has_variants'] ? 'yes' : 'no'; ?>"
                  data-title="<?php echo esc_attr($p['title']); ?>"
                  data-description="<?php echo esc_attr($p['description']); ?>"
                  data-variants="<?php echo esc_attr(json_encode($p['variants'])); ?>">
                  <?php echo $p['has_variants'] ? 'Auswählen ↗' : 'In den Warenkorb +'; ?>
                </button>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>

    <!-- Sushi Sections -->
    <?php foreach ($menu_data['sushi'] as $sec): ?>
      <section class="menu-section" id="sec-<?php echo esc_attr($sec['slug']); ?>" data-menu-type="sushi" style="display: none;">
        <div class="section-heading">
          <h2><?php echo esc_html($sec['name']); ?></h2>
          <p class="section-count"><?php echo count($sec['products']); ?> Sushi-Kreationen</p>
        </div>
        <div class="dish-grid">
          <?php foreach ($sec['products'] as $p): 
            $labels_text = !empty($p['labels']) ? implode(' ', $p['labels']) : '';
            $filter_meta = mb_strtolower($p['title'] . ' ' . ($p['description'] ?? '') . ' ' . $labels_text);
          ?>
            <article class="dish-card"
              data-dish-title="<?php echo esc_attr(mb_strtolower($p['title'])); ?>"
              data-dish-search="<?php echo esc_attr($filter_meta); ?>"
              data-dish-labels="<?php echo esc_attr(mb_strtolower($labels_text)); ?>">
              <?php if (!empty($p['image_url'])): ?>
                <img class="dish-photo" src="<?php echo esc_url($p['image_url']); ?>" alt="<?php echo esc_attr($p['title']); ?>" loading="lazy">
              <?php endif; ?>
              <div class="dish-topline">
                <h3><?php echo esc_html($p['title']); ?></h3>
                <span class="dish-price"><?php echo esc_html($p['formatted_price']); ?></span>
              </div>
              <?php if (!empty($p['description'])): ?>
                <p class="dish-desc"><?php echo esc_html($p['description']); ?></p>
              <?php endif; ?>
              <?php if (!empty($p['labels'])): ?>
                <div class="dish-labels">
                  <?php foreach ($p['labels'] as $lbl): ?>
                    <span class="dish-label"><?php echo esc_html($lbl); ?></span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              <div class="dish-actions">
                <button type="button" class="dish-control"
                  data-product-id="<?php echo esc_attr($p['id']); ?>"
                  data-has-variants="<?php echo $p['has_variants'] ? 'yes' : 'no'; ?>"
                  data-title="<?php echo esc_attr($p['title']); ?>"
                  data-description="<?php echo esc_attr($p['description']); ?>"
                  data-variants="<?php echo esc_attr(json_encode($p['variants'])); ?>">
                  <?php echo $p['has_variants'] ? 'Auswählen ↗' : 'In den Warenkorb +'; ?>
                </button>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  </section>
</main>

<?php get_footer(); ?>
