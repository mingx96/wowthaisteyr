<?php
/**
 * Template Name: Speisekarte & Online-Bestellung
 */
get_header();

$menu_data = wowthai_get_menu_structure();
?>

<main>
  <section class="page-intro wrap">
    <p class="eyebrow"><span class="eyebrow-dot"></span> WOW THAI · STEYR</p>
    <div class="page-intro-row">
      <div>
        <h1>Unsere <em>Speisekarte</em></h1>
        <p>Was darf es heute sein? Entdecken Sie unsere Gerichte und bestellen Sie während der Bestellzeiten bequem online zur Abholung vor Ort im City Point Steyr.</p>
      </div>
      <a class="button button-quiet" href="<?php echo esc_url(home_url('/reservations/')); ?>">Tisch reservieren <span aria-hidden="true">↗</span></a>
    </div>

    <div class="menu-tools">
      <div id="menu-tabs" class="menu-tabs" role="tablist" aria-label="Menüauswahl">
        <button class="menu-tab active" data-menu="restaurant" type="button">Speisekarte Restaurant</button>
        <button class="menu-tab" data-menu="sushi" type="button">Speisekarte Sushi</button>
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

    <!-- Restaurant Sections -->
    <?php foreach ($menu_data['restaurant'] as $sec): ?>
      <section class="menu-section" id="sec-<?php echo esc_attr($sec['slug']); ?>" data-menu-type="restaurant">
        <div class="section-heading">
          <h2><?php echo esc_html($sec['name']); ?></h2>
          <p><?php echo count($sec['products']); ?> Spezialitäten</p>
        </div>
        <div class="dish-grid">
          <?php foreach ($sec['products'] as $p): ?>
            <article class="dish-card">
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
              <?php if ($p['has_variants']): ?>
                <p class="dish-option-hint"><?php echo esc_html(count($p['variants'])); ?> Varianten mit eigenem Preis</p>
              <?php elseif (!empty($p['modifier_groups'])): ?>
                <p class="dish-option-hint">Zusatzoptionen verfügbar</p>
              <?php endif; ?>
              <div class="dish-actions">
                <button type="button" class="dish-control"
                  data-product-id="<?php echo esc_attr($p['id']); ?>"
                  data-has-variants="<?php echo $p['has_variants'] ? 'yes' : 'no'; ?>"
                  data-has-modifiers="<?php echo !empty($p['modifier_groups']) ? 'yes' : 'no'; ?>"
                  data-title="<?php echo esc_attr($p['title']); ?>"
                  data-description="<?php echo esc_attr($p['description']); ?>"
                  data-variants="<?php echo esc_attr(wp_json_encode($p['variants'])); ?>"
                  data-modifier-groups="<?php echo esc_attr(wp_json_encode($p['modifier_groups'])); ?>">
                  <?php echo ($p['has_variants'] || !empty($p['modifier_groups'])) ? 'Optionen & Preise wählen ↗' : 'In den Warenkorb +'; ?>
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
          <p><?php echo count($sec['products']); ?> Sushi-Kreationen</p>
        </div>
        <div class="dish-grid">
          <?php foreach ($sec['products'] as $p): ?>
            <article class="dish-card">
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
              <?php if ($p['has_variants']): ?>
                <p class="dish-option-hint"><?php echo esc_html(count($p['variants'])); ?> Varianten mit eigenem Preis</p>
              <?php elseif (!empty($p['modifier_groups'])): ?>
                <p class="dish-option-hint">Zusatzoptionen verfügbar</p>
              <?php endif; ?>
              <div class="dish-actions">
                <button type="button" class="dish-control"
                  data-product-id="<?php echo esc_attr($p['id']); ?>"
                  data-has-variants="<?php echo $p['has_variants'] ? 'yes' : 'no'; ?>"
                  data-has-modifiers="<?php echo !empty($p['modifier_groups']) ? 'yes' : 'no'; ?>"
                  data-title="<?php echo esc_attr($p['title']); ?>"
                  data-description="<?php echo esc_attr($p['description']); ?>"
                  data-variants="<?php echo esc_attr(wp_json_encode($p['variants'])); ?>"
                  data-modifier-groups="<?php echo esc_attr(wp_json_encode($p['modifier_groups'])); ?>">
                  <?php echo ($p['has_variants'] || !empty($p['modifier_groups'])) ? 'Optionen & Preise wählen ↗' : 'In den Warenkorb +'; ?>
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
