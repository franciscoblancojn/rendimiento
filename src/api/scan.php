<?php

/**
 * AJAX del análisis: rastreo por lotes, eliminación de categorías vacías y papelera de páginas.
 *
 * El rastreo se hace en varias llamadas para no agotar el tiempo de PHP:
 *   rnd_crawl_start → rnd_crawl_batch (repetido) → rnd_crawl_finish.
 */
class RND_API_SCAN
{
    const NONCE = 'rnd_nonce';

    public static function init()
    {
        foreach (['crawl_start', 'crawl_batch', 'crawl_finish', 'delete_categories', 'delete_pages'] as $action) {
            add_action('wp_ajax_rnd_' . $action, [self::class, $action]);
        }
    }

    private static function guard()
    {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can(RND_CAPABILITY)) {
            wp_send_json_error('No tienes permisos para esta acción.', 403);
        }
    }

    private static function ids($key)
    {
        return array_values(array_filter(array_map('absint', (array) ($_POST[$key] ?? []))));
    }

    public static function crawl_start()
    {
        self::guard();
        $state = RND_CRAWLER::start();
        if (isset($state['error'])) {
            wp_send_json_error($state['error']);
        }
        wp_send_json_success($state);
    }

    public static function crawl_batch()
    {
        self::guard();
        $state = RND_CRAWLER::batch();
        if (isset($state['error'])) {
            wp_send_json_error($state['error']);
        }
        wp_send_json_success($state);
    }

    public static function crawl_finish()
    {
        self::guard();
        $report = RND_CRAWLER::finish();
        if (isset($report['error'])) {
            wp_send_json_error($report['error']);
        }
        wp_send_json_success($report);
    }

    public static function delete_categories()
    {
        self::guard();
        if (!RND_CATEGORIES::available()) {
            wp_send_json_error('WooCommerce no está activo.');
        }
        if (!current_user_can('manage_product_terms')) {
            wp_send_json_error('No tienes permisos para eliminar categorías.', 403);
        }

        $ids = self::ids('ids');
        if (!$ids) {
            wp_send_json_error('No se recibieron categorías.');
        }

        $result = RND_CATEGORIES::delete($ids);
        RND_LOG_SERVICE::add('eliminar_categorias', $result);

        wp_send_json_success($result);
    }

    public static function delete_pages()
    {
        self::guard();

        $ids = self::ids('ids');
        if (!$ids) {
            wp_send_json_error('No se recibieron páginas.');
        }

        $result = RND_PAGES::trash($ids);
        RND_LOG_SERVICE::add('eliminar_paginas', $result);

        wp_send_json_success($result);
    }
}

RND_API_SCAN::init();
