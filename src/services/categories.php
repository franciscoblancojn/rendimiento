<?php

/**
 * Categorías de productos (product_cat) y detección de las que no tienen productos.
 *
 * `count` de un término es el número de productos asignados directamente. `total` suma
 * también los productos de sus subcategorías: una categoría solo se puede eliminar sin
 * perder productos cuando su total es 0.
 */
class RND_CATEGORIES
{
    const TAXONOMY = 'product_cat';

    public static function available()
    {
        return taxonomy_exists(self::TAXONOMY);
    }

    /**
     * Todas las categorías con sus conteos directos y por subcategorías.
     */
    public static function all()
    {
        if (!self::available()) {
            return [];
        }

        $terms = get_terms([
            'taxonomy' => self::TAXONOMY,
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);
        if (is_wp_error($terms) || !$terms) {
            return [];
        }

        $children = [];
        $direct = [];
        $names = [];
        foreach ($terms as $term) {
            $id = (int) $term->term_id;
            $children[(int) $term->parent][] = $id;
            $direct[$id] = (int) $term->count;
            $names[$id] = $term->name;
        }

        // Total del subárbol, calculado una sola vez por categoría.
        $totals = [];
        $calc = function ($id) use (&$calc, &$totals, $children, $direct) {
            if (isset($totals[$id])) {
                return $totals[$id];
            }
            $total = isset($direct[$id]) ? $direct[$id] : 0;
            if (!empty($children[$id])) {
                foreach ($children[$id] as $child) {
                    $total += $calc($child);
                }
            }
            $totals[$id] = $total;

            return $total;
        };
        foreach ($direct as $id => $count) {
            $calc($id);
        }

        $default = self::default_id();
        $out = [];
        foreach ($terms as $term) {
            $id = (int) $term->term_id;
            $link = get_term_link($term);
            $edit = get_edit_term_link($id, self::TAXONOMY);

            $out[] = [
                'id' => $id,
                'name' => $term->name,
                'slug' => $term->slug,
                'parent' => (int) $term->parent,
                'parent_name' => isset($names[(int) $term->parent]) ? $names[(int) $term->parent] : '',
                'count' => (int) $term->count,
                'total' => isset($totals[$id]) ? $totals[$id] : (int) $term->count,
                'children' => isset($children[$id]) ? count($children[$id]) : 0,
                'default' => $id === $default,
                'edit' => ($edit && !is_wp_error($edit)) ? (string) $edit : '',
                'archive' => is_wp_error($link) ? '' : (string) $link,
            ];
        }

        return $out;
    }

    /**
     * Categoría por defecto de WooCommerce: nunca se elimina.
     */
    public static function default_id()
    {
        return (int) get_option('default_product_cat', 0);
    }

    /**
     * Productos de la categoría y de todas sus subcategorías.
     */
    public static function subtree_total($id)
    {
        $term = get_term($id, self::TAXONOMY);
        if (!$term || is_wp_error($term)) {
            return 0;
        }

        $total = (int) $term->count;
        $children = get_term_children($id, self::TAXONOMY);
        if (!is_wp_error($children)) {
            foreach ($children as $child_id) {
                $child = get_term($child_id, self::TAXONOMY);
                if ($child && !is_wp_error($child)) {
                    $total += (int) $child->count;
                }
            }
        }

        return $total;
    }

    /**
     * Elimina categorías vacías. Vuelve a comprobar el total justo antes de borrar.
     *
     * @param int[] $ids
     */
    public static function delete($ids)
    {
        $result = ['deleted' => 0, 'skipped' => [], 'errors' => []];

        if (!self::available()) {
            $result['errors'][] = ['id' => 0, 'reason' => 'WooCommerce no está activo.'];
            return $result;
        }

        $default = self::default_id();
        foreach ((array) $ids as $id) {
            $id = absint($id);
            $term = get_term($id, self::TAXONOMY);
            if (!$term || is_wp_error($term)) {
                $result['skipped'][] = ['id' => $id, 'reason' => 'No existe'];
                continue;
            }
            if ($id === $default) {
                $result['skipped'][] = ['id' => $id, 'reason' => 'Es la categoría por defecto'];
                continue;
            }
            if (self::subtree_total($id) > 0) {
                $result['skipped'][] = ['id' => $id, 'reason' => 'Tiene productos'];
                continue;
            }
            if (!current_user_can('delete_term', $id, self::TAXONOMY)) {
                $result['skipped'][] = ['id' => $id, 'reason' => 'Sin permisos'];
                continue;
            }

            $deleted = wp_delete_term($id, self::TAXONOMY);
            if (true === $deleted) {
                $result['deleted']++;
            } else {
                $result['errors'][] = [
                    'id' => $id,
                    'reason' => is_wp_error($deleted) ? $deleted->get_error_message() : 'No se pudo eliminar',
                ];
            }
        }

        return $result;
    }
}
