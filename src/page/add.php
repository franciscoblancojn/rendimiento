<?php

add_action('admin_menu', function () {
    add_menu_page(
        'Rendimiento',            // Título de la página
        'Rendimiento',            // Nombre en el menú
        RND_CAPABILITY,           // Permisos
        RND_KEY,                  // Slug
        'RND_PAGE_VIEW',
        'dashicons-chart-area',
        59
    );
});

function RND_PAGE_VIEW()
{
    require_once RND_DIR . 'src/page/page.php';
}

// CSS y JS del admin solo en la página del plugin.
add_action('admin_enqueue_scripts', function ($hook) {
    if ('toplevel_page_' . RND_KEY !== $hook) {
        return;
    }
    $css = RND_DIR . 'src/css/admin.css';
    $js = RND_DIR . 'src/js/admin.js';
    wp_enqueue_style('rnd-admin', RND_URL . 'src/css/admin.css', [], filemtime($css));
    wp_enqueue_script('rnd-admin', RND_URL . 'src/js/admin.js', ['jquery'], filemtime($js), true);
    wp_localize_script('rnd-admin', 'RND_ADMIN', [
        'ajax' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce(RND_API_SCAN::NONCE),
    ]);
});
