<?php

/**
 * Rastreo de enlaces internos desde la home.
 *
 * El trabajo se reparte en lotes AJAX (RND_CRAWLER::BATCH páginas por llamada) para no
 * agotar el tiempo de PHP. El estado vive en un transient y el informe final en otro,
 * así la página del plugin puede mostrar el último resultado sin volver a rastrear.
 */
class RND_CRAWLER
{
    const STATE = 'rnd_crawl_state';
    const REPORT = 'rnd_crawl_report';
    const BATCH = 4;

    /**
     * Empieza un análisis nuevo y devuelve el estado resumido.
     */
    public static function start()
    {
        $config = (new RND_USE_DATA_CONFIG())->get();
        $start = self::start_url($config);
        $key = self::normalize($start);
        if ('' === $key) {
            return ['error' => 'La URL de inicio no es válida.'];
        }

        $state = [
            'start' => $start,
            'start_key' => $key,
            'queue' => [$start],
            'seen' => [$key => true],
            'visited' => [],
            'reachable' => [$key => true],
            'depth' => [$key => 0],
            'links' => 0,
            'errors' => [],
            'max_pages' => (int) $config['max_pages'],
            'max_depth' => (int) $config['max_depth'],
            'timeout' => (int) $config['timeout'],
            'started' => time(),
        ];
        self::save_state($state);

        return self::summary($state);
    }

    /**
     * Procesa un lote de la cola. Devuelve el estado resumido o un error.
     */
    public static function batch()
    {
        $state = self::state();
        if (!$state) {
            return ['error' => 'No hay un análisis en curso.'];
        }

        $processed = 0;
        while ($processed < self::BATCH && !empty($state['queue'])) {
            $url = array_shift($state['queue']);
            $key = self::normalize($url);
            if ('' === $key || isset($state['visited'][$key])) {
                continue;
            }

            $depth = isset($state['depth'][$key]) ? (int) $state['depth'][$key] : 0;
            $result = self::fetch($url, (int) $state['timeout']);
            $processed++;

            if (empty($result['ok'])) {
                if (count($state['errors']) < 200) {
                    $state['errors'][] = ['url' => $url, 'error' => $result['error']];
                }
                continue;
            }

            $state['visited'][$key] = [
                'url' => $url,
                'depth' => $depth,
                'status' => (int) $result['status'],
            ];
            $state['reachable'][$key] = true;

            // La canónica cubre enlaces antiguos (?p=123), http/https y redirecciones.
            $canonical = self::extract_canonical($result['body']);
            if ('' !== $canonical) {
                $canonical = self::resolve($url, $canonical);
                if ('' !== $canonical && self::crawlable($canonical)) {
                    $canonical_key = self::normalize($canonical);
                    if ('' !== $canonical_key) {
                        $state['reachable'][$canonical_key] = true;
                    }
                }
            }

            if ($depth >= (int) $state['max_depth']) {
                continue;
            }

            foreach (self::extract_links($result['body'], $url) as $link) {
                if (count($state['seen']) >= (int) $state['max_pages']) {
                    break;
                }
                $link_key = self::normalize($link);
                if ('' === $link_key || isset($state['seen'][$link_key])) {
                    continue;
                }
                $state['seen'][$link_key] = true;
                $state['depth'][$link_key] = $depth + 1;
                $state['queue'][] = $link;
                $state['links']++;
            }
        }

        self::save_state($state);

        return self::summary($state);
    }

    /**
     * Compara lo visitado con el contenido publicado y guarda el informe.
     */
    public static function finish()
    {
        $state = self::state();
        if (!$state) {
            return ['error' => 'No hay un análisis en curso.'];
        }

        $types = RND_PAGES::types();
        $posts = get_posts([
            'post_type' => $types,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        $visited = $state['visited'];
        $reachable = isset($state['reachable']) ? $state['reachable'] : $visited;
        $protected = RND_PAGES::protected_ids();
        $orphans = [];
        foreach ($posts as $post) {
            $url = get_permalink($post);
            if (!$url) {
                continue;
            }
            $key = self::normalize($url);
            if ('' !== $key && isset($reachable[$key])) {
                continue;
            }

            $type = get_post_type_object($post->post_type);
            $orphans[] = [
                'id' => (int) $post->ID,
                'title' => get_the_title($post->ID) ? get_the_title($post->ID) : '(sin título)',
                'type' => $post->post_type,
                'type_label' => $type && isset($type->labels->singular_name) ? $type->labels->singular_name : $post->post_type,
                'url' => $url,
                'edit' => (string) get_edit_post_link($post->ID, 'raw'),
                'date' => get_the_date('Y-m-d', $post),
                'protected' => isset($protected[$post->ID]) ? $protected[$post->ID] : '',
            ];
        }

        // El informe se guarda en un transient: se limita la lista para no guardar un objeto enorme.
        $orphan_count = count($orphans);
        $orphan_limit = 2000;

        $report = [
            'date' => current_time('mysql'),
            'timestamp' => time(),
            'start' => $state['start'],
            'visited' => count($visited),
            'seen' => count($state['seen']),
            'links' => (int) $state['links'],
            'errors' => array_slice($state['errors'], 0, 50),
            'error_count' => count($state['errors']),
            'types' => $types,
            'orphans' => array_slice($orphans, 0, $orphan_limit),
            'orphan_count' => $orphan_count,
            'orphans_truncated' => $orphan_count > $orphan_limit,
            'truncated' => count($state['seen']) >= (int) $state['max_pages'],
            'duration' => max(0, time() - (int) $state['started']),
        ];
        set_transient(self::REPORT, $report, DAY_IN_SECONDS);
        delete_transient(self::STATE);

        RND_LOG_SERVICE::add('analisis_enlaces', [
            'start' => $state['start'],
            'visited' => count($visited),
            'orphans' => count($orphans),
        ]);

        return [
            'orphans' => count($orphans),
            'visited' => count($visited),
            'seen' => count($state['seen']),
            'links' => (int) $state['links'],
            'errors' => count($state['errors']),
            'truncated' => $report['truncated'],
            'duration' => $report['duration'],
        ];
    }

    public static function state()
    {
        $state = get_transient(self::STATE);

        return is_array($state) ? $state : null;
    }

    public static function report()
    {
        $report = get_transient(self::REPORT);
        if (!is_array($report)) {
            return null;
        }

        return self::prune($report);
    }

    /**
     * Quita del informe las páginas indicadas (p. ej. las enviadas a la papelera).
     *
     * @param int[] $ids
     * @return int Cuántas entradas se quitaron.
     */
    public static function remove_orphans($ids)
    {
        $ids = array_values(array_filter(array_map('absint', (array) $ids)));
        if (!$ids) {
            return 0;
        }

        $report = get_transient(self::REPORT);
        if (!is_array($report) || empty($report['orphans'])) {
            return 0;
        }

        $drop = array_fill_keys($ids, true);
        $kept = [];
        foreach ($report['orphans'] as $item) {
            if (!isset($drop[(int) $item['id']])) {
                $kept[] = $item;
            }
        }

        $removed = count($report['orphans']) - count($kept);
        if ($removed <= 0) {
            return 0;
        }

        $report['orphans'] = $kept;
        $report['orphan_count'] = max(count($kept), (int) $report['orphan_count'] - $removed);
        set_transient(self::REPORT, $report, DAY_IN_SECONDS);

        return $removed;
    }

    /**
     * Descarta del informe lo que ya no está publicado (papelera, borrado, borrador…),
     * para que la tabla no muestre páginas que ya no existen.
     */
    private static function prune($report)
    {
        if (empty($report['orphans'])) {
            return $report;
        }

        $ids = [];
        foreach ($report['orphans'] as $item) {
            $ids[] = (int) $item['id'];
        }
        $published = self::published_ids($ids);

        $kept = [];
        foreach ($report['orphans'] as $item) {
            if (isset($published[(int) $item['id']])) {
                $kept[] = $item;
            }
        }

        $removed = count($report['orphans']) - count($kept);
        if ($removed <= 0) {
            return $report;
        }

        $report['orphans'] = $kept;
        $report['orphan_count'] = max(count($kept), (int) $report['orphan_count'] - $removed);
        set_transient(self::REPORT, $report, DAY_IN_SECONDS);

        return $report;
    }

    /**
     * IDs de la lista que siguen publicados, en una sola consulta.
     *
     * @param int[] $ids
     * @return array<int,bool>
     */
    private static function published_ids($ids)
    {
        global $wpdb;

        $ids = array_values(array_filter(array_map('absint', (array) $ids)));
        if (!$ids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $found = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND ID IN ($placeholders)",
                $ids
            )
        );

        $out = [];
        foreach ((array) $found as $id) {
            $out[(int) $id] = true;
        }

        return $out;
    }

    public static function status()
    {
        $state = self::state();

        return $state ? self::summary($state) : null;
    }

    private static function summary($state)
    {
        $visited = count($state['visited']);
        $queued = count($state['queue']);

        return [
            'visited' => $visited,
            'queued' => $queued,
            'seen' => count($state['seen']),
            'links' => (int) $state['links'],
            'errors' => count($state['errors']),
            'max_pages' => (int) $state['max_pages'],
            'done' => 0 === $queued || $visited >= (int) $state['max_pages'],
        ];
    }

    private static function save_state($state)
    {
        set_transient(self::STATE, $state, HOUR_IN_SECONDS);
    }

    private static function start_url($config)
    {
        $url = !empty($config['start_url']) ? $config['start_url'] : home_url('/');

        // Solo se rastrea el propio sitio: si la URL guardada apunta fuera, se usa la home.
        return self::crawlable($url) ? $url : home_url('/');
    }

    /**
     * Pide una URL y devuelve el HTML. Errores de red, HTTP o contenido no HTML.
     */
    private static function fetch($url, $timeout)
    {
        $response = wp_remote_get($url, [
            'timeout' => max(3, (int) $timeout),
            'redirection' => 3,
            'sslverify' => true,
            'user-agent' => 'Mozilla/5.0 (compatible; RND-Crawler/1.0; +' . home_url('/') . ')',
            'headers' => ['Accept' => 'text/html,application/xhtml+xml'],
        ]);
        if (is_wp_error($response)) {
            return ['ok' => false, 'error' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 400) {
            return ['ok' => false, 'error' => 'HTTP ' . $code];
        }

        $type = (string) wp_remote_retrieve_header($response, 'content-type');
        if ('' !== $type && false === stripos($type, 'html')) {
            return ['ok' => false, 'error' => 'No es HTML'];
        }

        $body = (string) wp_remote_retrieve_body($response);
        if ('' === $body) {
            return ['ok' => false, 'error' => 'Respuesta vacía'];
        }

        return ['ok' => true, 'status' => $code, 'body' => $body];
    }

    /**
     * Enlaces <a> / <area> del HTML, resueltos y filtrados al propio sitio.
     */
    private static function extract_links($html, $base)
    {
        $hrefs = [];
        if (preg_match_all('/<(?:a|area)\b[^>]*?\bhref\s*=\s*(["\'])(.*?)\1/is', $html, $matches)) {
            foreach ($matches[2] as $href) {
                $hrefs[] = $href;
            }
        }
        if (preg_match_all('/<(?:a|area)\b[^>]*?\bhref\s*=\s*([^\s>"\']+)/is', $html, $matches)) {
            foreach ($matches[1] as $href) {
                $hrefs[] = $href;
            }
        }

        $links = [];
        foreach ($hrefs as $href) {
            $href = html_entity_decode($href, ENT_QUOTES, 'UTF-8');
            $url = self::resolve($base, $href);
            if ('' === $url || !self::crawlable($url)) {
                continue;
            }
            $key = self::normalize($url);
            if ('' === $key) {
                continue;
            }
            // La clave normalizada evita repetir el mismo destino con otro fragmento.
            $links[$key] = $url;
        }

        return array_values($links);
    }

    /**
     * URL canónica declarada por la página (<link rel="canonical">), si la hay.
     */
    private static function extract_canonical($html)
    {
        if (!preg_match_all('/<link\b[^>]*>/is', $html, $matches)) {
            return '';
        }

        foreach ($matches[0] as $tag) {
            if (!preg_match('/\brel\s*=\s*(["\'])(.*?)\1/is', $tag, $rel)) {
                continue;
            }
            if (false === stripos($rel[2], 'canonical')) {
                continue;
            }
            if (preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/is', $tag, $href)) {
                return html_entity_decode($href[2], ENT_QUOTES, 'UTF-8');
            }
        }

        return '';
    }

    /**
     * Resuelve un href relativo contra la URL de la página.
     */
    private static function resolve($base, $href)
    {
        $href = trim($href);
        if ('' === $href || '#' === $href[0]) {
            return '';
        }

        $lower = strtolower($href);
        foreach (['mailto:', 'tel:', 'javascript:', 'data:', 'ftp:'] as $scheme) {
            if (0 === strpos($lower, $scheme)) {
                return '';
            }
        }

        $parts = wp_parse_url($base);
        if (!$parts || empty($parts['host'])) {
            return '';
        }
        $scheme = !empty($parts['scheme']) ? $parts['scheme'] : 'https';
        $host = $parts['host'];
        $port = !empty($parts['port']) ? ':' . (int) $parts['port'] : '';

        if (0 === strpos($href, '//')) {
            return $scheme . ':' . $href;
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $href)) {
            return $href;
        }

        $fragment = '';
        if (false !== ($pos = strpos($href, '#'))) {
            $fragment = substr($href, $pos);
            $href = substr($href, 0, $pos);
        }
        $query = '';
        if (false !== ($pos = strpos($href, '?'))) {
            $query = substr($href, $pos);
            $href = substr($href, 0, $pos);
        }

        if ('' === $href) {
            $path = !empty($parts['path']) ? $parts['path'] : '/';
        } elseif ('/' === $href[0]) {
            $path = $href;
        } else {
            $base_path = !empty($parts['path']) ? $parts['path'] : '/';
            $dir = preg_replace('#/[^/]*$#', '/', $base_path);
            $path = ('' === $dir ? '/' : $dir) . $href;
        }

        return $scheme . '://' . $host . $port . self::remove_dots($path) . $query . $fragment;
    }

    /**
     * Clave de comparación: sin esquema, sin www, sin fragmento ni parámetros de tracking.
     */
    public static function normalize($url)
    {
        $parts = wp_parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return '';
        }

        $host = self::host_key($parts['host']);
        $port = '';
        if (!empty($parts['port']) && !in_array((int) $parts['port'], [80, 443], true)) {
            $port = ':' . (int) $parts['port'];
        }
        $path = !empty($parts['path']) ? self::remove_dots($parts['path']) : '/';
        $query = !empty($parts['query']) ? self::clean_query($parts['query']) : '';

        // http y https del mismo sitio cuentan como la misma página.
        $key = 'https://' . $host . $port . $path;
        if ('' !== $query) {
            $key .= '?' . $query;
        }

        return $key;
    }

    private static function host_key($host)
    {
        $host = strtolower($host);

        return 0 === strpos($host, 'www.') ? substr($host, 4) : $host;
    }

    /**
     * Solo URLs del propio sitio, HTML y que no sean ruido (admin, assets, carrito…).
     */
    private static function crawlable($url)
    {
        $parts = wp_parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return false;
        }
        $scheme = !empty($parts['scheme']) ? strtolower($parts['scheme']) : '';
        if ('http' !== $scheme && 'https' !== $scheme) {
            return false;
        }

        $home = wp_parse_url(home_url('/'));
        if (!$home || empty($home['host']) || self::host_key($parts['host']) !== self::host_key($home['host'])) {
            return false;
        }

        $path = !empty($parts['path']) ? $parts['path'] : '/';
        if (preg_match('#^/(wp-admin|wp-login\.php|wp-json|xmlrpc\.php)#i', $path)) {
            return false;
        }
        if (preg_match('#/wp-(content|includes)/#i', $path)) {
            return false;
        }
        if (preg_match('#/feed/?$#i', $path)) {
            return false;
        }
        if (preg_match('#\.(jpe?g|png|gif|svg|webp|avif|ico|bmp|pdf|zip|gz|rar|7z|mp4|mp3|wav|css|js|json|xml|txt|woff2?|ttf|eot)$#i', $path)) {
            return false;
        }

        $query = !empty($parts['query']) ? $parts['query'] : '';
        if ('' !== $query && preg_match('/(^|&)(add-to-cart|replytocom|remove_item|wc-ajax|_wpnonce|s)=/i', $query)) {
            return false;
        }

        return true;
    }

    /**
     * Quita parámetros de tracking para que la misma página no cuente varias veces.
     */
    private static function clean_query($query)
    {
        parse_str($query, $args);
        foreach (array_keys($args) as $key) {
            if (preg_match('/^(utm_|fbclid$|gclid$|mc_cid$|mc_eid$|_ga$)/i', $key)) {
                unset($args[$key]);
            }
        }

        return http_build_query($args);
    }

    /**
     * Normaliza `.` y `..` y quita la barra final (salvo en la raíz).
     */
    private static function remove_dots($path)
    {
        $path = '/' . ltrim($path, '/');
        $out = [];
        foreach (explode('/', $path) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }
            if ('..' === $segment) {
                array_pop($out);
                continue;
            }
            $out[] = $segment;
        }

        return '/' . implode('/', $out);
    }
}
