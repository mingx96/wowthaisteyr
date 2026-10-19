<?php
/** Standalone regression coverage for rich-text descriptions used as menu text. */
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 2) . '/');
}
if (!function_exists('add_filter')) {
    function add_filter(...$args) {}
}
if (!function_exists('add_action')) {
    function add_action(...$args) {}
}
if (!function_exists('wp_strip_all_tags')) {
    // WordPress is not bootstrapped in standalone CLI tests.
    function wp_strip_all_tags($text, $remove_breaks = false) {
        $text = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $text);
        $text = strip_tags($text);
        if ($remove_breaks) {
            $text = preg_replace('/[\\r\\n\\t ]+/', ' ', $text);
        }
        return trim($text);
    }
}
require_once dirname(__DIR__, 2) . '/inc/menu-normalizer.php';

$cases = [
    'inline emphasis' => ['<em>Nudeln mit</em><em> Gemüse</em>', 'Nudeln mit Gemüse'],
    'encoded emphasis' => ['&lt;em&gt;Nudeln mit Gemüse&lt;/em&gt;', 'Nudeln mit Gemüse'],
    'paragraphs' => ['<p>Curry</p><p>Inkl. Reis</p>', 'Curry Inkl. Reis'],
    'line breaks' => ['Curry<br>Inkl. Reis<br />mit Gemüse', 'Curry Inkl. Reis mit Gemüse'],
    'lists' => ['<ul><li>Reis</li><li>Gemüse</li></ul>', 'Reis Gemüse'],
    'editor whitespace' => ["<em>Curry</em>\r\n\r\n<em>Inkl. Reis</em>", 'Curry Inkl. Reis'],
    'entities and umlauts' => ['<em>Hähnchen&nbsp;&amp;&nbsp;Gemüse</em>', 'Hähnchen & Gemüse'],
    'plain text' => ['Hähnchen & Gemüse', 'Hähnchen & Gemüse'],
    'script and style bodies' => ['<script>alert(1)</script><style>p{color:red}</style><em>Reis</em>', 'Reis'],
    'angle bracket text' => ['Portion < 5 &amp; > 2', 'Portion < 5 & > 2'],
    'empty content' => ['<em> </em>', ''],
];
$failed = 0;
$passed = 0;
foreach ($cases as $label => [$input, $expected]) {
    $actual = wowthai_normalize_menu_text($input, 'Sample dish', true);
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL {$label}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n");
        $failed++;
    } else {
        $passed++;
    }
}

// Rich WooCommerce content keeps its existing behavior outside menu text output.
$rich = '<em>Reis</em>';
if (wowthai_normalize_menu_text($rich, 'Sample dish') !== $rich) {
    fwrite(STDERR, "FAIL default normalization must preserve rich content\n");
    $failed++;
} else {
    $passed++;
}
$fallback = wowthai_menu_fallback_descriptions()['V9. Hausgemachte Frühlingsrollen'];
if (wowthai_normalize_menu_text('', 'V9. Hausgemachte Frühlingsrollen', true) !== $fallback) {
    fwrite(STDERR, "FAIL existing fallback description changed\n");
    $failed++;
} else {
    $passed++;
}
echo "Menu description tests: {$passed} passed, {$failed} failed.\n";
exit($failed > 0 ? 1 : 0);
