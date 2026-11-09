<?php

use franciscoblancojn\wordpress_utils\FWUSystemLog;

/**
 * Registro tolerante en el log del plugin (RND_LOG en la barra de administración).
 *
 * `FWUSystemLog` solo se declara si existen las funciones de wp-admin (`add_options_page`).
 * En el frontend, cron o AJAX puede no estar: el plugin no debe romperse por escribir en el log.
 */
class RND_LOG_SERVICE
{
    /**
     * @param string $type
     * @param array  $data
     */
    public static function add($type, $data = [])
    {
        if (!class_exists(FWUSystemLog::class)) {
            return;
        }

        FWUSystemLog::add(RND_KEY, [
            'type' => (string) $type,
            'data' => (array) $data,
        ]);
    }
}
