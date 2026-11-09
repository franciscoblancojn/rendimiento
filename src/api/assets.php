<?php

/**
 * AJAX del análisis de CSS/JS y de la lista de exclusión.
 *
 * El análisis se hace por lotes para no agotar el tiempo de PHP:
 *   rnd_assets_start → rnd_assets_batch (repetido) → rnd_assets_finish.
 *
 * La lista de exclusión se guarda en la opción RND_ASSETS::OPTION y se aplica en el
 * frontend desde RND_HOOK_ASSETS.
 */
class RND_API_ASSETS
{
    const ACTION_CONFIG = 'rnd_save_assets_config';

    public static function init()
    {
        foreach (['assets_start', 'assets_batch', 'assets_finish', 'assets_exclude', 'assets_include'] as $action) {
            add_action('wp_ajax_rnd_' . $action, [self::class, $action]);
        }
        add_action('admin_post_' . self::ACTION_CONFIG, [self::class, 'save_config']);
    }

    private static function guard()
    {
        check_ajax_referer(RND_API_SCAN::NONCE, 'nonce');
        if (!current_user_can(RND_CAPABILITY)) {
            wp_send_json_error('No tienes permisos para esta acción.', 403);
        }
    }

    private static function items($key)
    {
        $items = isset($_POST[$key]) ? wp_unslash($_POST[$key]) : [];

        return is_array($items) ? $items : [];
    }

    public static function assets_start()
    {
        self::guard();
        $state = RND_ASSETS::analyze_start();
        if (isset($state['error'])) {
            wp_send_json_error($state['error']);
        }
        wp_send_json_success($state);
    }

    public static function assets_batch()
    {
        self::guard();
        $state = RND_ASSETS::analyze_batch();
        if (isset($state['error'])) {
            wp_send_json_error($state['error']);
        }
        wp_send_json_success($state);
    }

    public static function assets_finish()
    {
        self::guard();
        $report = RND_ASSETS::analyze_finish();
        if (isset($report['error'])) {
            wp_send_json_error($report['error']);
        }
        wp_send_json_success($report);
    }

    public static function assets_exclude()
    {
        self::guard();

        $excluded = 0;
        foreach (self::items('items') as $item) {
            if (!is_array($item)) {
                continue;
            }
            $type = sanitize_key($item['type'] ?? '');
            $url = esc_url_raw($item['url'] ?? '');
            $handle = sanitize_text_field($item['handle'] ?? '');
            if (!in_array($type, RND_ASSETS::TYPES, true) || '' === $url) {
                continue;
            }
            if (RND_ASSETS::exclude($type, $url, $handle)) {
                $excluded++;
            }
        }

        if (!$excluded) {
            wp_send_json_error('No se recibieron archivos válidos.');
        }

        RND_LOG_SERVICE::add('excluir_assets', ['count' => $excluded]);
        wp_send_json_success(['excluded' => $excluded]);
    }

    public static function assets_include()
    {
        self::guard();

        $removed = 0;
        foreach (self::items('items') as $item) {
            if (!is_array($item)) {
                continue;
            }
            $type = sanitize_key($item['type'] ?? '');
            $key = esc_url_raw($item['key'] ?? '');
            if (!in_array($type, RND_ASSETS::TYPES, true) || '' === $key) {
                continue;
            }
            if (RND_ASSETS::include_asset($type, $key)) {
                $removed++;
            }
        }

        if (!$removed) {
            wp_send_json_error('No se pudo quitar el archivo de la lista.');
        }

        RND_LOG_SERVICE::add('incluir_assets', ['count' => $removed]);
        wp_send_json_success(['removed' => $removed]);
    }

    /**
     * Guarda el interruptor general y el modo prueba (formulario admin-post).
     */
    public static function save_config()
    {
        if (!current_user_can(RND_CAPABILITY)) {
            wp_die('No tienes permisos para cambiar esta configuración.', '', ['response' => 403]);
        }
        check_admin_referer(self::ACTION_CONFIG);

        RND_ASSETS::save_config(
            !empty($_POST['enabled']),
            !empty($_POST['test_mode'])
        );

        RND_LOG_SERVICE::add('guardar_ajustes_assets', [
            'enabled' => !empty($_POST['enabled']),
            'test_mode' => !empty($_POST['test_mode']),
        ]);

        set_transient('rnd_respond', [
            'status' => 'ok',
            'message' => 'Ajustes de exclusión guardados.',
        ], 60);

        $tab = sanitize_key($_POST['rnd_tab'] ?? '');
        wp_safe_redirect(add_query_arg(['page' => RND_KEY, 'tab' => $tab], admin_url('admin.php')));
        exit;
    }
}

RND_API_ASSETS::init();
