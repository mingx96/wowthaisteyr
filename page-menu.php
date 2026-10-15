<?php
/**
 * Template Name: Speisekarte & Online-Bestellung
 */
get_header();

$menu_data = wowthai_get_menu_structure();
$opening_status = function_exists('wowthai_get_opening_status') ? wowthai_get_opening_status() : null;
$allergens = function_exists('wowthai_get_allergen_list') ? wowthai_get_allergen_list() : [];
$is_sushi_day = function_exists('wowthai_is_sushi_available_on_day') ? wowthai_is_sushi_available_on_day() : true;
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
        <button class="menu-tab" data-menu="sushi" type="button">Speisekarte Sushi<?php if (!$is_sushi_day): ?> <span style="font-size:11px;background:#fef3c7;color:#92400e;padding:2px 7px;border-radius:4px;font-weight:700;margin-left:4px;">Montags Ruhetag</span><?php endif; ?></button>
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
          <button type="button" class="filter-chip chip-allergen-btn" id="btn-open-allergens" style="margin-left:auto;">ℹ️ Allergen-Legende (A–R)</button>
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
            $spice_pill = !empty($p['spice']['pill']) ? $p['spice']['pill'] : '';
          ?>
            <article class="dish-card" 
              data-dish-title="<?php echo esc_attr(mb_strtolower($p['title'])); ?>"
              data-dish-search="<?php echo esc_attr($filter_meta); ?>"
              data-dish-labels="<?php echo esc_attr(mb_strtolower($labels_text)); ?>">
              <?php if (!empty($p['image_url'])): ?>
                <img class="dish-photo" src="<?php echo esc_url($p['image_url']); ?>" alt="<?php echo esc_attr($p['title']); ?>" loading="lazy">
              <?php endif; ?>
              <div class="dish-topline">
                <h3>
                  <?php echo esc_html($p['title']); ?>
                  <?php echo $spice_pill; ?>
                </h3>
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
                  data-modifier-groups="<?php echo esc_attr(wp_json_encode($p['modifier_groups'])); ?>"
                  data-has-modifiers="<?php echo !empty($p['modifier_groups']) ? 'yes' : 'no'; ?>"
                  data-has-variants="<?php echo $p['has_variants'] ? 'yes' : 'no'; ?>"
                  data-title="<?php echo esc_attr($p['title']); ?>"
                  data-description="<?php echo esc_attr($p['description']); ?>"
                  data-variants="<?php echo esc_attr(json_encode($p['variants'])); ?>">
                  <?php echo ($p['has_variants'] || !empty($p['modifier_groups'])) ? 'Auswählen ↗' : 'In den Warenkorb +'; ?>
                </button>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>

    <!-- Sushi Sections -->
    <?php if (!$is_sushi_day): ?>
      <div id="sushi-monday-banner" class="sushi-closed-banner" style="display:none;background:rgba(217,119,6,0.15);border:1px solid #d97706;border-radius:8px;padding:16px 20px;margin:16px 0 24px 0;color:#fef3c7;align-items:center;gap:14px;">
        <span style="font-size:32px;line-height:1;">🍣</span>
        <div>
          <strong style="color:#d4a359;font-size:15px;display:block;margin-bottom:4px;">Montags Sushi-Ruhetag</strong>
          <p style="margin:0;font-size:13px;line-height:1.5;color:#e6e1d8;">Unsere Sushi-Küche hat montags ihren Ruhetag. Sämtliche warmen Speisen (Currys, Wok, Spezialitäten) bereiten wir sehr gerne für Sie zu! Vorbestellungen für Sushi ab Dienstag sind jederzeit möglich.</p>
        </div>
      </div>
    <?php endif; ?>

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
            $spice_pill = !empty($p['spice']['pill']) ? $p['spice']['pill'] : '';
          ?>
            <article class="dish-card"
              data-dish-title="<?php echo esc_attr(mb_strtolower($p['title'])); ?>"
              data-dish-search="<?php echo esc_attr($filter_meta); ?>"
              data-dish-labels="<?php echo esc_attr(mb_strtolower($labels_text)); ?>">
              <?php if (!empty($p['image_url'])): ?>
                <img class="dish-photo" src="<?php echo esc_url($p['image_url']); ?>" alt="<?php echo esc_attr($p['title']); ?>" loading="lazy">
              <?php endif; ?>
              <div class="dish-topline">
                <h3>
                  <?php echo esc_html($p['title']); ?>
                  <?php echo $spice_pill; ?>
                </h3>
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
                <?php if (!$is_sushi_day): ?>
                  <button type="button" class="dish-control disabled" disabled style="opacity:0.65;cursor:not-allowed;background:#2b2620;color:#d4a359;border:1px solid rgba(212,163,89,0.3);" title="Montags Sushi-Ruhetag">
                    🍣 Montags Ruhetag
                  </button>
                <?php else: ?>
                  <button type="button" class="dish-control"
                    data-product-id="<?php echo esc_attr($p['id']); ?>"
                    data-modifier-groups="<?php echo esc_attr(wp_json_encode($p['modifier_groups'])); ?>"
                  data-has-modifiers="<?php echo !empty($p['modifier_groups']) ? 'yes' : 'no'; ?>"
                  data-has-variants="<?php echo $p['has_variants'] ? 'yes' : 'no'; ?>"
                    data-title="<?php echo esc_attr($p['title']); ?>"
                    data-description="<?php echo esc_attr($p['description']); ?>"
                    data-variants="<?php echo esc_attr(json_encode($p['variants'])); ?>">
                    <?php echo ($p['has_variants'] || !empty($p['modifier_groups'])) ? 'Auswählen ↗' : 'In den Warenkorb +'; ?>
                  </button>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  </section>

  <!-- Allergen Legende Modal (A - R Codex Österreich) -->
  <div id="allergen-modal-wrap" class="modal-wrap" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="allergen-modal-title">
    <div class="dish-modal allergen-modal" style="max-width: 680px;">
      <div class="modal-head">
        <div>
          <span class="eyebrow" style="color:var(--green);font-size:11px;font-weight:700;letter-spacing:1px;">LEBENSMITTEL-INFORMATION</span>
          <h2 id="allergen-modal-title" style="margin-top:4px;">Allergene &amp; Zusatzstoffe (A–R)</h2>
        </div>
        <button class="icon-close" id="allergen-modal-close" type="button" aria-label="Schließen">&times;</button>
      </div>
      <p class="modal-description" style="margin-bottom:18px;">
        Gemäß der EU-Lebensmittelinformationsverordnung (LMIV) und österreichischer Codex-Richtlinie informieren wir Sie transparent über enthaltene Allergene in unseren Speisen. Bei Fragen oder schweren Unverträglichkeiten wenden Sie sich gerne direkt an unser Küchenteam!
      </p>
      <div class="allergen-grid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:10px;">
        <?php foreach ($allergens as $al): ?>
          <div class="allergen-item" style="background:#201c18;border:1px solid #3d3529;border-radius:4px;padding:8px 12px;display:flex;gap:10px;align-items:flex-start;">
            <span style="background:#d4a359;color:#100f0d;font-weight:800;font-size:12px;width:22px;height:22px;border-radius:3px;display:inline-grid;place-items:center;flex-shrink:0;"><?php echo esc_html($al['letter']); ?></span>
            <div>
              <strong style="color:#f7f3ec;font-size:13px;display:block;line-height:1.3;"><?php echo esc_html($al['name']); ?></strong>
              <small style="color:#b7afa2;font-size:11px;line-height:1.4;display:block;"><?php echo esc_html($al['detail']); ?></small>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</main>

<?php get_footer(); ?>
