<?php

/**
 * Aplica las exclusiones de CSS/JS en el frontend.
 *
 * Se engancha tarde (9999) para que el tema y los plugins ya hayan encolado sus archivos.
 * Los filtros de etiqueta son una red de seguridad para lo que se imprima fuera de la
 * cola normal. RND_ASSETS::should_apply() respeta el interruptor general y el modo prueba.
 */
class RND_HOOK_ASSETS
{
    public static function init()
    {
        add_action('wp_enqueue_scripts', [RND_ASSETS::class, 'dequeue'], 9999);
        add_action('wp_print_styles', [RND_ASSETS::class, 'dequeue'], 9999);
        add_action('wp_print_scripts', [RND_ASSETS::class, 'dequeue'], 9999);
        add_action('wp_print_footer_scripts', [RND_ASSETS::class, 'dequeue'], 9999);

        add_filter('style_loader_tag', [RND_ASSETS::class, 'filter_style_tag'], 9999, 3);
        add_filter('script_loader_tag', [RND_ASSETS::class, 'filter_script_tag'], 9999, 3);
    }
}

RND_HOOK_ASSETS::init();
