<?php
/**
 * Shared presentational primitives for public theme templates.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return the decorative action arrow used beside visible link/button labels.
 */
function wowthai_action_icon($modifier = '') {
    $classes = 'action-icon';
    if ($modifier !== '') {
        $classes .= ' ' . sanitize_html_class($modifier);
    }

    return '<span class="' . esc_attr($classes) . '" aria-hidden="true">'
        . '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        . '<path d="M7 12h10M13 8l4 4-4 4"/>'
        . '</svg>'
        . '</span>';
}
