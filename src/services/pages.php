<?php

/**
 * Páginas y entradas huérfanas: envío a la papelera desde el informe del rastreo.
 *
 * Nunca se borra de forma permanente desde el plugin: `wp_trash_post()` permite
 * recuperarlas desde la papelera de WordPress. Las páginas del sistema (portada,
 * tienda, carrito, checkout, mi cuenta…) no se tocan.
 */
class RND_PAGES
{
    /**
     * Tipos de contenido configurados para el análisis.
     */
    public static function types()
    {
        $config = (new RND_USE_DATA_CONFIG())->get();
        $types = array_values(array_filter((array) ($config['post_types'] ?? [])));

        return $types ? $types : ['page', 'post'];
    }

    /**
     * Páginas que WordPress o WooCommerce usan internamente: no se envían a la papelera.
     *
     * @return array<int,string> id => etiqueta
     */
    public static function protected_ids()
    {
        $protected = [];

        $front = (int) get_option('page_on_front', 0);
        if ($front > 0) {
            $protected[$front] = 'Portada';
        }
        $posts = (int) get_option('page_for_posts', 0);
        if ($posts > 0) {
            $protected[$posts] = 'Página de entradas';
        }
        $privacy = (int) get_option('wp_page_for_privacy_policy', 0);
        if ($privacy > 0) {
            $protected[$privacy] = 'Política de privacidad';
        }

        if (function_exists('wc_get_page_id')) {
            $pages = [
                'shop' => 'Tienda',
                'cart' => 'Carrito',
                'checkout' => 'Finalizar compra',
                'myaccount' => 'Mi cuenta',
                'terms' => 'Términos y condiciones',
            ];
            foreach ($pages as $key => $label) {
                $id = (int) wc_get_page_id($key);
                if ($id > 0) {
                    $protected[$id] = $label;
                }
            }
        }

        return $protected;
    }

    /**
     * Envía a la papelera los IDs indicados, comprobando tipo, estado y permisos.
     *
     * @param int[] $ids
     */
    public static function trash($ids)
    {
        $result = ['deleted' => 0, 'trashed' => [], 'skipped' => [], 'errors' => []];
        $types = self::types();
        $protected = self::protected_ids();

        foreach ((array) $ids as $id) {
            $id = absint($id);
            $post = get_post($id);
            if (!$post) {
                $result['skipped'][] = ['id' => $id, 'reason' => 'No existe'];
                continue;
            }
            if (!in_array($post->post_type, $types, true)) {
                $result['skipped'][] = ['id' => $id, 'reason' => 'Tipo de contenido no incluido en el análisis'];
                continue;
            }
            if ('publish' !== $post->post_status) {
                $result['skipped'][] = ['id' => $id, 'reason' => 'No está publicada'];
                continue;
            }
            if (isset($protected[$id])) {
                $result['skipped'][] = ['id' => $id, 'reason' => 'Página del sistema: ' . $protected[$id]];
                continue;
            }
            if (!current_user_can('delete_post', $id)) {
                $result['skipped'][] = ['id' => $id, 'reason' => 'Sin permisos'];
                continue;
            }

            $trashed = wp_trash_post($id);
            if ($trashed) {
                $result['deleted']++;
                $result['trashed'][] = $id;
            } else {
                $result['errors'][] = ['id' => $id, 'reason' => 'No se pudo enviar a la papelera'];
            }
        }

        return $result;
    }
}
