<?php
/** Small, read-only homepage selection backed by the existing managed menu. */
if (!defined('ABSPATH')) {
    exit;
}

function wowthai_get_homepage_dishes(): array {
    if (!function_exists('wc_get_product') || !class_exists('WC_Product')) {
        return [];
    }

    $requested_ids = apply_filters('wowthai_homepage_product_ids', [56, 43, 119]);
    if (!is_array($requested_ids)) {
        return [];
    }

    // Bound product reads before loading anything; malformed IDs never coerce.
    $ids = [];
    foreach ($requested_ids as $candidate) {
        if ((!is_int($candidate) && !is_string($candidate)) || !preg_match('/^[1-9][0-9]*$/D', (string) $candidate)) {
            continue;
        }
        $id = filter_var($candidate, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || isset($ids[$id])) {
            continue;
        }
        $ids[$id] = $id;
        if (count($ids) === 6) {
            break;
        }
    }

    $category_groups = wowthai_menu_category_groups();
    $dishes = [];
    foreach ($ids as $id) {
        $product = wc_get_product($id);
        if (!$product instanceof WC_Product || !$product->exists() || $product->get_status() !== 'publish'
            || get_post_type($id) !== 'product' || post_password_required($id)
            || !in_array($product->get_catalog_visibility(), ['visible', 'catalog'], true)
            || !$product->is_purchasable() || !$product->is_in_stock()) {
            continue;
        }

        $terms = get_the_terms($id, 'product_cat');
        if (!is_array($terms) || is_wp_error($terms)) {
            continue;
        }
        $category = null;
        foreach ($category_groups as $menu_type => $category_names) {
            foreach ($terms as $term) {
                if (isset($term->name) && in_array($term->name, $category_names, true)) {
                    $category = $term;
                    break 2;
                }
            }
        }
        if ($category === null) {
            continue;
        }

        $dish = wowthai_get_menu_product($id);
        $dish['category'] = function_exists('wowthai_normalize_menu_text') ? wowthai_normalize_menu_text($category->name) : $category->name;
        $dish['menu_type'] = $menu_type;
        $dish['menu_url'] = home_url('/menu/#gericht-' . $id);
        $dishes[] = $dish;
    }

    return $dishes;
}
