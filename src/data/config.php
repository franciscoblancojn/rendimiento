<?php

/**
 * Configuración del plugin (opción RND_CONFIG).
 *
 * DEFAULTS es la única lista de ajustes: get() la mezcla con lo guardado y sanitize()
 * normaliza lo que llega del formulario. Un ajuste nuevo se agrega en los dos.
 */
class RND_USE_DATA_CONFIG extends RND_USE_DATA_BASE
{
    protected $KEY = RND_CONFIG;

    const DEFAULTS = [
        // URL desde la que empieza el análisis. Vacío = home del sitio.
        'start_url' => '',
        // Límites del rastreo para no tumbar el servidor ni agotar el tiempo de PHP.
        'max_pages' => 300,
        'max_depth' => 4,
        'timeout' => 6,
        // Tipos de contenido que se comprueban al final del rastreo.
        'post_types' => ['page', 'post'],
    ];

    public function get()
    {
        return array_merge(self::DEFAULTS, $this->DATA);
    }

    public function set($data)
    {
        $this->DATA = $data;
        $this->save();
    }

    /**
     * Normaliza lo que llega del formulario de ajustes (ya con wp_unslash).
     */
    public static function sanitize($input)
    {
        $input = (array) $input;

        $allowed = get_post_types(['public' => true]);
        $post_types = [];
        foreach ((array) ($input['post_types'] ?? []) as $type) {
            $type = sanitize_key($type);
            if ($type && 'attachment' !== $type && isset($allowed[$type])) {
                $post_types[] = $type;
            }
        }
        if (!$post_types) {
            $post_types = self::DEFAULTS['post_types'];
        }

        return [
            'start_url' => esc_url_raw(trim((string) ($input['start_url'] ?? ''))),
            'max_pages' => min(2000, max(10, absint($input['max_pages'] ?? self::DEFAULTS['max_pages']))),
            'max_depth' => min(10, max(1, absint($input['max_depth'] ?? self::DEFAULTS['max_depth']))),
            'timeout' => min(20, max(3, absint($input['timeout'] ?? self::DEFAULTS['timeout']))),
            'post_types' => array_values(array_unique($post_types)),
        ];
    }
}
