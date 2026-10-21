<?php
/** Standalone coverage of the real menu mapping and bounded homepage selection. */
define('ABSPATH', dirname(__DIR__, 2) . '/');

// Load real theme functions without bootstrapping unrelated WordPress hooks.
function homepage_load_function(string $file, string $name): bool {
    $tokens = token_get_all(file_get_contents($file));
    foreach ($tokens as $start => $token) {
        if (!is_array($token) || $token[0] !== T_FUNCTION) continue;
        $next = $start + 1;
        while (isset($tokens[$next]) && is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) $next++;
        if (!isset($tokens[$next]) || !is_array($tokens[$next]) || $tokens[$next][1] !== $name) continue;
        $code = '';
        $depth = 0;
        $opened = false;
        for ($i = $start; $i < count($tokens); $i++) {
            $part = $tokens[$i];
            $code .= is_array($part) ? $part[1] : $part;
            if ($part === '{') { $depth++; $opened = true; }
            if ($part === '}' && --$depth === 0 && $opened) {
                eval($code);
                return true;
            }
        }
    }
    return false;
}

// The only doubles are WordPress/WooCommerce I/O and enqueue boundaries.
function add_action(...$args) {}
function add_filter(...$args) {}
function apply_filters($hook, $value, ...$args) {
    return $hook === 'wowthai_homepage_product_ids' && array_key_exists('homepage_ids', $GLOBALS)
        ? $GLOBALS['homepage_ids'] : $value;
}
function wp_strip_all_tags($text, $remove_breaks = false) {
    return trim(strip_tags(preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $text)));
}
function get_post_meta($id, $key, $single = false) { return $GLOBALS['catalog'][$id]['meta'][$key] ?? ''; }
function get_the_title($id = 0) { return $GLOBALS['catalog'][$id ?: $GLOBALS['loop_id']]['title'] ?? ''; }
function get_post_field($field, $id) { return $GLOBALS['catalog'][$id][$field] ?? ''; }
function post_password_required($post = null) {
    return $GLOBALS['catalog'][$post ?? $GLOBALS['loop_id']]['password_required'] ?? false;
}
function get_the_content($more_link_text = null, $strip_teaser = false, $post = null) {
    $id = $post ?? $GLOBALS['loop_id'];
    // Model WordPress's protected/public content boundary separately from raw storage.
    if (post_password_required($id)) return '<form><p>Bitte Passwort eingeben.</p></form>';
    return $GLOBALS['catalog'][$id]['content_output'] ?? get_post_field('post_content', $id);
}
function get_the_ID() { return $GLOBALS['loop_id']; }
function get_post_type($id) { return $GLOBALS['catalog'][$id]['post_type'] ?? 'product'; }
function get_the_terms($id, $taxonomy) { return $GLOBALS['catalog'][$id]['terms'] ?? false; }
function get_term_by($field, $value, $taxonomy) {
    foreach ($GLOBALS['terms'] as $term) if ($term->$field === $value) return $term;
    return false;
}
function wp_reset_postdata() { $GLOBALS['loop_id'] = 999; }
function home_url($path = '') { return 'https://fixture.invalid' . $path; }
function is_wp_error($value) { return $value instanceof WP_Error; }
class WP_Error {}
class WC_Product {
    private $id;
    public function __construct($id) { $this->id = $id; }
    public function get_id() { return $this->id; }
    public function exists() { return $GLOBALS['catalog'][$this->id]['exists'] ?? true; }
    public function get_status() { return $GLOBALS['catalog'][$this->id]['status'] ?? 'publish'; }
    public function get_catalog_visibility() { return $GLOBALS['catalog'][$this->id]['visibility'] ?? 'visible'; }
    public function is_in_stock() { return $GLOBALS['catalog'][$this->id]['in_stock'] ?? true; }
    public function is_purchasable() { return $GLOBALS['catalog'][$this->id]['purchasable'] ?? true; }
}
function wc_get_product($id) {
    $GLOBALS['product_reads'][] = $id;
    if (!isset($GLOBALS['catalog'][$id])) return false;
    return $GLOBALS['catalog'][$id]['invalid_object'] ?? new WC_Product($id);
}
class WP_Query {
    private $ids;
    private $offset = 0;
    public function __construct($args) {
        $GLOBALS['queries'][] = $args;
        $this->ids = $GLOBALS['query_ids'];
    }
    public function have_posts() { return $this->offset < count($this->ids); }
    public function the_post() { $GLOBALS['loop_id'] = $this->ids[$this->offset++]; }
}
function get_template_directory() { return dirname(__DIR__, 2); }
function get_template_directory_uri() { return 'https://fixture.invalid/theme'; }
function is_front_page() { return $GLOBALS['front_page']; }
function wp_enqueue_style($handle, $src, $deps = [], $version = false) { $GLOBALS['styles'][$handle] = compact('src', 'deps', 'version'); }
function wp_enqueue_script(...$args) {}
function wp_localize_script(...$args) {}
function rest_url($path) { return 'https://fixture.invalid/wp-json/' . $path; }
function admin_url($path) { return 'https://fixture.invalid/wp-admin/' . $path; }
function wp_create_nonce($action) { return 'synthetic-test-nonce'; }

require_once ABSPATH . 'inc/menu-normalizer.php';
foreach (['wowthai_get_modifier_groups', 'wowthai_menu_category_groups', 'wowthai_get_menu_product', 'wowthai_get_products_in_cat', 'wowthai_get_menu_structure', 'wowthai_enqueue_scripts'] as $function) {
    homepage_load_function(ABSPATH . 'functions.php', $function);
}
homepage_load_function(ABSPATH . 'inc/restaurant-experience.php', 'wowthai_get_dish_spice_level');
if (is_file(ABSPATH . 'inc/homepage.php')) require_once ABSPATH . 'inc/homepage.php';

$passed = 0;
$failed = 0;
function homepage_expect($actual, $expected, string $label): void {
    if ($actual === $expected) { $GLOBALS['passed']++; return; }
    $GLOBALS['failed']++;
    fwrite(STDERR, "FAIL {$label}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n");
}
function homepage_dishes(): array {
    return function_exists('wowthai_get_homepage_dishes') ? wowthai_get_homepage_dishes() : [];
}
function homepage_product($id): array {
    return function_exists('wowthai_get_menu_product') ? wowthai_get_menu_product($id) : [];
}
function homepage_fixture(): void {
    unset($GLOBALS['homepage_ids']);
    $GLOBALS['product_reads'] = $GLOBALS['queries'] = $GLOBALS['styles'] = [];
    $GLOBALS['loop_id'] = 999;
    $GLOBALS['front_page'] = false;
    $GLOBALS['query_ids'] = [43, 56];
    $GLOBALS['terms'] = [
        (object) ['term_id' => 8, 'name' => 'HAUPTSPEISEN', 'slug' => 'hauptspeisen'],
        (object) ['term_id' => 9, 'name' => 'SPEZIALITÄT DES HAUSES', 'slug' => 'spezialitaet-des-hauses'],
        (object) ['term_id' => 10, 'name' => 'Special Rolls', 'slug' => 'special-rolls'],
        (object) ['term_id' => 11, 'name' => 'Vorpseise Sushi', 'slug' => 'vorpseise-sushi'],
    ];
    $GLOBALS['catalog'] = [
        999 => ['title' => 'Homepage context must never become a dish', 'post_content' => 'Wrong page content'],
        56 => [
            'title' => '★M1 – Reis &amp; Gemüse', 'post_content' => '<p>Reis mit Chili</p><p>und Gemüse</p>',
            'terms' => [$GLOBALS['terms'][0]],
            'meta' => [
                '_price' => '23.10', '_wowthai_has_variants' => 'yes',
                '_wowthai_variants' => [['id' => 'a', 'name' => 'Option A', 'price' => '15.30'], ['id' => 'b', 'name' => 'Option B', 'price' => '13.25']],
                '_wowthai_labels' => ['Vegi'], '_wowthai_image_url' => 'https://images.invalid/managed-dish.webp',
                '_wowthai_modifier_groups' => [['id' => 'sauce', 'name' => 'Sauce', 'min' => 0, 'max' => 1, 'options' => [['id' => 'extra', 'name' => 'Extra', 'price' => '1.20']]]],
            ],
        ],
        43 => ['title' => 'Fixture house dish', 'post_content' => '<em>Mit Reis</em>', 'terms' => [$GLOBALS['terms'][1]], 'meta' => ['_price' => '1200.5']],
        119 => ['title' => 'Fixture sushi dish', 'post_content' => 'Gurke &amp; Avocado', 'terms' => [$GLOBALS['terms'][2]], 'meta' => ['_price' => '18.35']],
    ];
}

homepage_fixture();
homepage_expect(function_exists('wowthai_get_menu_product'), true, 'shared menu mapper exists');
homepage_expect(function_exists('wowthai_get_homepage_dishes'), true, 'homepage selector exists');

// Catch global-post leakage, changed price math, lost fields and skipped normalization.
$product = homepage_product(56);
homepage_expect($product['id'] ?? null, 56, 'mapper retains the requested product ID');
homepage_expect($product['title'] ?? null, '★ M1 – Reis & Gemüse', 'mapper normalizes the requested title');
homepage_expect($product['description'] ?? null, 'Reis mit Chili und Gemüse', 'mapper reads and normalizes the requested content');
homepage_expect($product['price'] ?? null, '23.10', 'mapper retains raw base price');
homepage_expect($product['formatted_price'] ?? null, 'ab €13,25', 'mapper preserves minimum variant pricing');
homepage_expect($product['has_variants'] ?? null, true, 'mapper retains variant flag');
homepage_expect($product['variants'][0]['id'] ?? null, 'a', 'mapper retains variants');
homepage_expect($product['modifier_groups'][0]['options'][0]['price'] ?? null, '1.20', 'mapper retains modifier data');
homepage_expect($product['labels'] ?? null, ['Vegi'], 'mapper retains labels');
homepage_expect($product['image_url'] ?? null, 'https://images.invalid/managed-dish.webp', 'mapper retains only the configured image');
homepage_expect($product['spice']['level'] ?? null, 2, 'mapper keeps real spice derivation');
homepage_expect($GLOBALS['loop_id'], 999, 'standalone mapping does not change the global post');
$simple = homepage_product(43);
homepage_expect($simple['formatted_price'] ?? null, '€1.200,50', 'mapper preserves simple price formatting');
homepage_expect($simple['variants'] ?? null, [], 'mapper preserves empty variants');
homepage_expect($simple['modifier_groups'] ?? null, [], 'mapper preserves empty modifiers');
homepage_expect($simple['labels'] ?? null, [], 'mapper preserves empty labels');
homepage_expect($simple['image_url'] ?? null, '', 'missing photo remains empty');

// Catch raw-content reads that bypass WordPress protection, pagination or More output.
homepage_fixture();
$GLOBALS['catalog'][43]['post_content'] = '<p>Protected fixture description</p>';
$GLOBALS['catalog'][43]['password_required'] = true;
$protected = homepage_product(43);
homepage_expect($protected['description'] ?? null, 'Bitte Passwort eingeben.', 'mapper respects the requested product password boundary');
homepage_expect($GLOBALS['loop_id'], 999, 'protected mapping targets the product without changing global post');
$GLOBALS['catalog'][43]['password_required'] = false;
$GLOBALS['catalog'][999]['password_required'] = true;
homepage_expect(homepage_product(43)['description'] ?? null, 'Protected fixture description', 'unlocked target content is independent of a protected global post');
$GLOBALS['catalog'][43]['post_content'] = '<p>First page</p><!--nextpage--><p>Later page</p>';
$GLOBALS['catalog'][43]['content_output'] = '<p>First page</p>';
homepage_expect(homepage_product(43)['description'] ?? null, 'First page', 'mapper consumes WordPress page output rather than all stored pages');
$GLOBALS['catalog'][43]['post_content'] = '<p>Teaser</p><!--more--><p>Extended content</p>';
$GLOBALS['catalog'][43]['content_output'] = '<p>Teaser</p> <a href="https://fixture.invalid/dish#more-43">Continue reading</a>';
homepage_expect(homepage_product(43)['description'] ?? null, 'Teaser Continue reading', 'mapper retains WordPress More output before normalizing it');
homepage_fixture();

// Catch a catalog scan, substituted products, taxonomy drift or unsafe preview URLs.
$dishes = homepage_dishes();
homepage_expect(array_column($dishes, 'id'), [56, 43, 119], 'default homepage selection follows managed IDs');
homepage_expect($GLOBALS['queries'], [], 'homepage never issues a full catalog query');
homepage_expect($GLOBALS['product_reads'], [56, 43, 119], 'homepage loads only selected product IDs');
homepage_expect($dishes[0]['category'] ?? null, 'HAUPTSPEISEN', 'category label comes from taxonomy');
homepage_expect($dishes[2]['menu_type'] ?? null, 'sushi', 'sushi selection carries its actual menu group');
homepage_expect(array_column($dishes, 'menu_url'), ['https://fixture.invalid/menu/#gericht-56', 'https://fixture.invalid/menu/#gericht-43', 'https://fixture.invalid/menu/#gericht-119'], 'preview links use read-only article anchors');
homepage_expect($dishes[2]['image_url'] ?? null, '', 'homepage does not invent a missing product photo');

// Catch copied/cached marketing content after a manager edits a real product.
$GLOBALS['catalog'][56]['title'] = 'Fischer Lachs &amp; Reis';
$GLOBALS['catalog'][56]['post_content'] = '<p>Neues Gericht</p><p>Inkl.Reis</p>';
$GLOBALS['catalog'][56]['meta']['_wowthai_variants'][1]['price'] = '11.45';
$GLOBALS['catalog'][43]['meta']['_price'] = '19.65';
$changed = homepage_dishes();
homepage_expect($changed[0]['title'] ?? null, 'Frischer Lachs & Reis', 'managed title changes propagate');
homepage_expect($changed[0]['description'] ?? null, 'Neues Gericht Inkl.Reis', 'managed description changes propagate');
homepage_expect($changed[0]['formatted_price'] ?? null, 'ab €11,45', 'managed variant prices propagate');
homepage_expect($changed[1]['formatted_price'] ?? null, '€19,65', 'managed base prices propagate');

// Catch exposure of unavailable/private/non-menu items and preserve remaining selection.
$unavailable = [
    'missing product' => null,
    'invalid WooCommerce object' => ['invalid_object' => new stdClass()],
    'nonexistent WooCommerce product' => ['exists' => false],
    'draft product' => ['status' => 'draft'],
    'private product' => ['status' => 'private'],
    'password-required product' => ['password_required' => true],
    'hidden product' => ['visibility' => 'hidden'],
    'search-only product' => ['visibility' => 'search'],
    'out-of-stock product' => ['in_stock' => false],
    'unavailable purchase' => ['purchasable' => false],
    'variation outside menu query' => ['post_type' => 'product_variation'],
    'no category' => ['terms' => false],
    'taxonomy error' => ['terms' => new WP_Error()],
    'category outside the menu' => ['terms' => [(object) ['term_id' => 99, 'name' => 'Not on menu', 'slug' => 'not-on-menu']]],
];
foreach ($unavailable as $label => $override) {
    homepage_fixture();
    if ($override === null) unset($GLOBALS['catalog'][43]);
    else $GLOBALS['catalog'][43] = array_replace($GLOBALS['catalog'][43], $override);
    homepage_expect(array_column(homepage_dishes(), 'id'), [56, 119], $label . ' is omitted without a substitute');
}
homepage_fixture();
$GLOBALS['catalog'][43]['visibility'] = 'catalog';
homepage_expect(array_column(homepage_dishes(), 'id'), [56, 43, 119], 'catalog-only product remains eligible');
$GLOBALS['catalog'][999]['password_required'] = true;
homepage_expect(array_column(homepage_dishes(), 'id'), [56, 43, 119], 'homepage password eligibility checks each product rather than the global post');
$GLOBALS['catalog'][119]['terms'] = [$GLOBALS['terms'][3]];
$alias = homepage_dishes();
homepage_expect($alias[2]['category'] ?? null, 'Vorspeise Sushi', 'legacy menu category is normalized from taxonomy');
homepage_expect($alias[2]['menu_type'] ?? null, 'sushi', 'legacy category belongs to the same menu group');

// Catch coercion of malformed IDs, duplicates and unbounded database work.
homepage_fixture();
foreach ([201, 202, 203, 204, 205] as $id) $GLOBALS['catalog'][$id] = $GLOBALS['catalog'][43];
$GLOBALS['homepage_ids'] = [null, 0, -43, '1e2', true, 56.5, [], new stdClass(), '9999999999999999999999999', 56, '56', '43', 119, 201, 202, 203, 204, 205];
homepage_expect(array_column(homepage_dishes(), 'id'), [56, 43, 119, 201, 202, 203], 'selection accepts only unique positive integer IDs and caps at six');
homepage_expect($GLOBALS['product_reads'], [56, 43, 119, 201, 202, 203], 'six-ID cap is applied before product reads');
homepage_fixture();
$GLOBALS['homepage_ids'] = [56, 43, 119, 201, 202, 203, 204];
homepage_expect(array_column(homepage_dishes(), 'id'), [56, 43, 119], 'missing configured IDs never trigger replacement queries');
homepage_expect($GLOBALS['product_reads'], [56, 43, 119, 201, 202, 203], 'missing products do not extend the six-ID read budget');
foreach ([[], false, '56,43', null, new stdClass()] as $invalid_selection) {
    homepage_fixture();
    $GLOBALS['homepage_ids'] = $invalid_selection;
    homepage_expect(homepage_dishes(), [], 'empty or malformed selection produces no cards');
    homepage_expect($GLOBALS['product_reads'], [], 'empty or malformed selection performs no product loads');
}

// Catch changes to the existing category loop while extracting its shared mapper.
homepage_fixture();
$category = wowthai_get_products_in_cat(8);
homepage_expect(array_column($category, 'id'), [43, 56], 'category helper preserves query order');
homepage_expect(array_column($category, 'formatted_price'), ['€1.200,50', 'ab €13,25'], 'category helper preserves existing price behavior');
homepage_expect($category[1]['description'], 'Reis mit Chili und Gemüse', 'category helper shares normalized descriptions');
homepage_expect($GLOBALS['loop_id'], 999, 'category helper restores global post context');
homepage_expect($GLOBALS['queries'][0], ['post_type' => 'product', 'posts_per_page' => -1, 'tax_query' => [['taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => 8]], 'orderby' => 'menu_order', 'order' => 'ASC', 'post_status' => 'publish'], 'existing menu query contract is unchanged');

// Catch homepage CSS leaking to functional pages or loading before shared styles.
homepage_fixture();
wowthai_enqueue_scripts();
homepage_expect(isset($GLOBALS['styles']['wowthai-homepage']), false, 'homepage CSS is absent on other pages');
$GLOBALS['front_page'] = true;
wowthai_enqueue_scripts();
homepage_expect($GLOBALS['styles']['wowthai-homepage']['src'] ?? null, 'https://fixture.invalid/theme/assets/css/homepage.css', 'homepage CSS is loaded on front page');
homepage_expect($GLOBALS['styles']['wowthai-homepage']['deps'] ?? null, ['wowthai-main'], 'homepage CSS follows the actual shared style handle');
homepage_expect($GLOBALS['styles']['wowthai-homepage']['version'] ?? null, $GLOBALS['styles']['wowthai-main']['version'], 'homepage CSS shares theme cache version');

echo "Homepage tests: {$passed} passed, {$failed} failed.\n";
exit($failed > 0 ? 1 : 0);
