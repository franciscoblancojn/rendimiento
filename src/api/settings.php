<?php

/**
 * Guardar la configuración (formulario de la página del plugin, vía admin-post.php).
 *
 * Para acciones por AJAX: una clase RND_API_* con const NONCE, un guard() con
 * check_ajax_referer() + current_user_can() y wp_send_json_success/error.
 */
class RND_API_SETTINGS
{
    const ACTION = 'rnd_save_settings';

    public static function init()
    {
        add_action('admin_post_' . self::ACTION, [self::class, 'save']);
    }

    public static function save()
    {
        if (!current_user_can(RND_CAPABILITY)) {
            wp_die('No tienes permisos para cambiar esta configuración.', '', ['response' => 403]);
        }
        check_admin_referer(self::ACTION);

        $config = RND_USE_DATA_CONFIG::sanitize(wp_unslash($_POST['rnd'] ?? []));
        (new RND_USE_DATA_CONFIG())->set($config);

        RND_LOG_SERVICE::add('guardar_ajustes', $config);
        set_transient('rnd_respond', [
            'status' => 'ok',
            'message' => 'Ajustes guardados. El próximo análisis usará estos límites.',
        ], 60);

        $tab = sanitize_key($_POST['rnd_tab'] ?? '');
        wp_safe_redirect(add_query_arg(['page' => RND_KEY, 'tab' => $tab], admin_url('admin.php')));
        exit;
    }
}

RND_API_SETTINGS::init();
