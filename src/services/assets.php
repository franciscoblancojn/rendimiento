<?php

/**
 * Análisis y exclusión de CSS/JS de la home.
 *
 * El análisis es estático: descarga el HTML de la home, lista sus <link rel="stylesheet">
 * y <script src>, descarga cada archivo y calcula un % aproximado de "innecesario"
 * (0 = obligatorio, 100 = probablemente se pueda quitar). No mide cobertura real ni
 * interacción, así que el % es una orientación y siempre se muestra el motivo.
 *
 * La lista de exclusión se aplica en el frontend con wp_dequeue_* y, como red de
 * seguridad, con los filtros style_loader_tag / script_loader_tag.
 */
class RND_ASSETS
{
    const OPTION = 'rnd_excluded_assets';
    const CONFIG = 'rnd_assets_config';
    const STATE = 'rnd_assets_state';
    const HOME = 'rnd_assets_home';
    const REPORT = 'rnd_assets_report';

    const BATCH = 4;
    const TIMEOUT = 6;
    const MAX_BYTES = 2097152;
    const HOME_MAX_BYTES = 3145728;

    const TYPES = ['css', 'js'];

    private static $config_cache = null;
    private static $exclusions_cache = null;
    private static $apply_cache = null;

    // ------------------------------------------------------------------ configuración

    /**
     * Ajustes de la exclusión: interruptor general y modo prueba.
     */
    public static function config()
    {
        if (null !== self::$config_cache) {
            return self::$config_cache;
        }

        $saved = get_option(self::CONFIG, []);
        if (!is_array($saved)) {
            $saved = [];
        }
        self::$config_cache = array_merge([
            'enabled' => 1,
            'test_mode' => 0,
        ], $saved);

        return self::$config_cache;
    }

    public static function save_config($enabled, $test_mode)
    {
        self::$config_cache = [
            'enabled' => $enabled ? 1 : 0,
            'test_mode' => $test_mode ? 1 : 0,
        ];
        update_option(self::CONFIG, self::$config_cache, false);
    }

    // ------------------------------------------------------------------ lista de exclusión

    /**
     * @param string $type css|js|'' (todas)
     * @return array
     */
    public static function exclusions($type = '')
    {
        if (null === self::$exclusions_cache) {
            $saved = get_option(self::OPTION, []);
            if (!is_array($saved)) {
                $saved = [];
            }
            self::$exclusions_cache = [
                'css' => !empty($saved['css']) && is_array($saved['css']) ? $saved['css'] : [],
                'js' => !empty($saved['js']) && is_array($saved['js']) ? $saved['js'] : [],
            ];
        }

        if ('' === $type) {
            return self::$exclusions_cache;
        }

        return isset(self::$exclusions_cache[$type]) ? self::$exclusions_cache[$type] : [];
    }

    public static function exclude($type, $url, $handle = '')
    {
        if (!in_array($type, self::TYPES, true)) {
            return false;
        }

        $url = esc_url_raw(trim((string) $url));
        $key = self::normalize($url);
        // Nunca excluir un archivo combinado por el optimizador: se quitaría todo el CSS/JS.
        if ('' === $key || self::is_combined_url($url)) {
            return false;
        }

        $all = self::exclusions();
        $all[$type][$key] = [
            'url' => $url,
            'handle' => sanitize_text_field((string) $handle),
            'date' => current_time('mysql'),
        ];

        self::$exclusions_cache = $all;
        update_option(self::OPTION, $all, false);

        return true;
    }

    public static function include_asset($type, $key)
    {
        if (!in_array($type, self::TYPES, true)) {
            return false;
        }

        $key = esc_url_raw(trim((string) $key));
        $all = self::exclusions();
        if ('' === $key || !isset($all[$type][$key])) {
            return false;
        }

        unset($all[$type][$key]);
        self::$exclusions_cache = $all;
        update_option(self::OPTION, $all, false);

        return true;
    }

    /**
     * Purga la caché del optimizador al cambiar las exclusiones. LiteSpeed es la del sitio;
     * el filtro permite añadir otras cachés.
     */
    public static function purge_cache()
    {
        if (has_action('litespeed_purge_all')) {
            do_action('litespeed_purge_all');
        }
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }

        do_action('rnd_assets_purge_cache');
    }

    public static function is_excluded($type, $url, $handle = '')
    {
        $list = self::exclusions($type);
        if (!$list) {
            return false;
        }

        $handle = (string) $handle;
        if ('' !== $handle) {
            foreach ($list as $item) {
                if (!empty($item['handle']) && $item['handle'] === $handle) {
                    return true;
                }
            }
        }

        $key = self::normalize($url);

        return '' !== $key && isset($list[$key]);
    }

    // ------------------------------------------------------------------ frontend

    /**
     * ¿Hay que aplicar las exclusiones en esta petición?
     */
    public static function should_apply()
    {
        if (null !== self::$apply_cache) {
            return self::$apply_cache;
        }

        if (is_admin()) {
            self::$apply_cache = false;
            return false;
        }
        if (function_exists('is_customize_preview') && is_customize_preview()) {
            self::$apply_cache = false;
            return false;
        }

        $config = self::config();
        if (empty($config['enabled'])) {
            self::$apply_cache = false;
            return false;
        }
        if (!empty($config['test_mode']) && !current_user_can(RND_CAPABILITY)) {
            self::$apply_cache = false;
            return false;
        }

        self::$apply_cache = true;

        return true;
    }

    /**
     * Quita de la cola los estilos y scripts de la lista de exclusión.
     */
    public static function dequeue()
    {
        if (!self::should_apply()) {
            return;
        }

        $all = self::exclusions();
        self::dequeue_type('css', $all['css']);
        self::dequeue_type('js', $all['js']);
    }

    private static function dequeue_type($type, $list)
    {
        if (!$list) {
            return;
        }

        $registry = 'css' === $type ? wp_styles() : wp_scripts();
        if (!$registry || empty($registry->queue)) {
            return;
        }

        foreach ((array) $registry->queue as $handle) {
            // No quitar una librería de la que dependa otro archivo encolado.
            if (self::is_dependency($handle, $type)) {
                continue;
            }

            $src = isset($registry->registered[$handle]) ? $registry->registered[$handle]->src : '';
            if (!self::is_excluded($type, $src, $handle)) {
                continue;
            }

            if ('css' === $type) {
                wp_dequeue_style($handle);
            } else {
                wp_dequeue_script($handle);
            }
        }
    }

    /**
     * Red de seguridad: si el archivo se imprime igualmente, se elimina la etiqueta.
     */
    public static function filter_style_tag($html, $handle, $href = '')
    {
        if (!self::should_apply() || self::is_dependency($handle, 'css')) {
            return $html;
        }

        return self::is_excluded('css', $href, $handle) ? '' : $html;
    }

    public static function filter_script_tag($tag, $handle, $src = '')
    {
        if (!self::should_apply() || self::is_dependency($handle, 'js')) {
            return $tag;
        }

        return self::is_excluded('js', $src, $handle) ? '' : $tag;
    }

    private static function is_dependency($handle, $type)
    {
        $handle = (string) $handle;
        if ('' === $handle) {
            return false;
        }

        $registry = 'css' === $type ? wp_styles() : wp_scripts();
        if (!$registry || empty($registry->queue)) {
            return false;
        }

        foreach ((array) $registry->queue as $queued) {
            if ($queued === $handle) {
                continue;
            }
            $deps = isset($registry->registered[$queued]) ? (array) $registry->registered[$queued]->deps : [];
            if (in_array($handle, $deps, true)) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------ análisis

    /**
     * Descarga la home y prepara la lista de archivos a analizar.
     */
    public static function analyze_start()
    {
        $home = home_url('/');
        $response = self::fetch($home, 'html', self::HOME_MAX_BYTES);
        if (isset($response['error'])) {
            return ['error' => 'No se pudo leer la home: ' . $response['error']];
        }

        $html = $response['body'];
        $assets = self::extract_assets($html, $home);
        $optimized = false;

        // Con LiteSpeed (u otro optimizador) la home trae un solo CSS/JS combinado, sin los
        // handles de WordPress. En ese caso se pide la versión «antes de optimizar» para poder
        // listar los archivos individuales y excluirlos por handle. La exclusión sigue siendo
        // del servidor: wp_dequeue_* corre antes de que LiteSpeed combine la página.
        if (self::has_combined_asset($assets)) {
            foreach (self::unoptimized_urls($home) as $url) {
                $original = self::fetch($url, 'html', self::HOME_MAX_BYTES);
                if (isset($original['error'])) {
                    continue;
                }
                $original_assets = self::extract_assets($original['body'], $home);
                if (!$original_assets || self::has_combined_asset($original_assets)) {
                    continue;
                }
                if (count($original_assets) > count($assets)) {
                    $html = $original['body'];
                    $assets = $original_assets;
                    $optimized = true;
                    break;
                }
            }
        }

        if (!$assets) {
            return ['error' => 'No se encontraron archivos CSS ni JS en la home.'];
        }

        set_transient(self::HOME, [
            'tokens' => self::html_tokens($html),
            'inline' => self::inline_scripts($html),
        ], HOUR_IN_SECONDS);

        $state = [
            'home' => $home,
            'assets' => $assets,
            'optimized' => $optimized,
            'index' => 0,
            'results' => [],
            'errors' => [],
            'started' => time(),
        ];
        set_transient(self::STATE, $state, HOUR_IN_SECONDS);

        return self::summary($state);
    }

    /**
     * URLs de la misma página sin optimizar. LiteSpeed es la que usa el sitio; el filtro
     * permite añadir las de otros optimizadores (Autoptimize, WP Rocket…).
     */
    private static function unoptimized_urls($home)
    {
        $urls = [add_query_arg('LSCWP_CTRL', 'before_optimize', $home)];

        return (array) apply_filters('rnd_assets_unoptimized_urls', $urls, $home);
    }

    private static function has_combined_asset($assets)
    {
        foreach ((array) $assets as $asset) {
            if (!empty($asset['url']) && self::is_combined_url($asset['url'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * ¿Es un archivo generado por el optimizador (combinado/minificado)? Excluirlo por URL
     * quitaría todo el CSS/JS de la página, así que no se ofrece como exclusión.
     */
    public static function is_combined_url($url)
    {
        $path = strtolower((string) wp_parse_url($url, PHP_URL_PATH));
        if ('' === $path) {
            return false;
        }

        $patterns = [
            '#/wp-content/litespeed/(css|js|ucss)/#',
            '#/wp-content/uploads/autoptimize/#',
            '#/wp-content/cache/(autoptimize|min|busting|wp-rocket|wpfc)/#',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Analiza un lote de archivos. Devuelve el estado resumido o un error.
     */
    public static function analyze_batch()
    {
        $state = get_transient(self::STATE);
        if (!is_array($state)) {
            return ['error' => 'No hay un análisis de CSS/JS en curso.'];
        }

        $home = get_transient(self::HOME);
        if (!is_array($home)) {
            $home = ['tokens' => ['classes' => [], 'ids' => [], 'tags' => []], 'inline' => ''];
        }

        $total = count($state['assets']);
        $end = min($total, (int) $state['index'] + self::BATCH);
        while ((int) $state['index'] < $end) {
            $asset = $state['assets'][(int) $state['index']];
            $state['index']++;

            $result = self::analyze_asset($asset, $home);
            if (isset($result['error'])) {
                $state['errors'][] = ['url' => $asset['url'], 'error' => $result['error']];
            } else {
                $state['results'][] = $result;
            }
        }

        set_transient(self::STATE, $state, HOUR_IN_SECONDS);

        return self::summary($state);
    }

    /**
     * Guarda el informe final.
     */
    public static function analyze_finish()
    {
        $state = get_transient(self::STATE);
        if (!is_array($state)) {
            return ['error' => 'No hay un análisis de CSS/JS en curso.'];
        }

        $results = $state['results'];
        usort($results, function ($a, $b) {
            return (int) $b['score'] - (int) $a['score'];
        });

        $css = 0;
        $js = 0;
        $combined = 0;
        foreach ($results as $item) {
            if ('css' === $item['type']) {
                $css++;
            } else {
                $js++;
            }
            if (!empty($item['combined'])) {
                $combined++;
            }
        }

        $report = [
            'date' => current_time('mysql'),
            'timestamp' => time(),
            'home' => $state['home'],
            'optimized' => !empty($state['optimized']),
            'results' => $results,
            'errors' => array_slice($state['errors'], 0, 50),
            'error_count' => count($state['errors']),
            'css_count' => $css,
            'js_count' => $js,
            'combined_count' => $combined,
            'duration' => max(0, time() - (int) $state['started']),
        ];
        set_transient(self::REPORT, $report, DAY_IN_SECONDS);
        delete_transient(self::STATE);
        delete_transient(self::HOME);

        RND_LOG_SERVICE::add('analisis_assets', [
            'home' => $state['home'],
            'optimized' => !empty($state['optimized']),
            'css' => $css,
            'js' => $js,
            'combined' => $combined,
            'errors' => count($state['errors']),
        ]);

        return [
            'css' => $css,
            'js' => $js,
            'combined' => $combined,
            'errors' => count($state['errors']),
            'duration' => $report['duration'],
        ];
    }

    public static function status()
    {
        $state = get_transient(self::STATE);

        return is_array($state) ? self::summary($state) : null;
    }

    public static function report()
    {
        $report = get_transient(self::REPORT);

        return is_array($report) ? $report : null;
    }

    private static function summary($state)
    {
        $total = count($state['assets']);
        $index = (int) $state['index'];

        return [
            'index' => $index,
            'total' => $total,
            'errors' => count($state['errors']),
            'done' => $index >= $total,
        ];
    }

    private static function analyze_asset($asset, $home)
    {
        $fetched = self::fetch($asset['url'], $asset['type'], self::MAX_BYTES);
        if (isset($fetched['error'])) {
            return ['error' => $fetched['error']];
        }

        $content = $fetched['body'];
        $score = 'css' === $asset['type']
            ? self::score_css($content, $home['tokens'])
            : self::score_js($content, $home['tokens'], $home['inline'], $asset);

        return [
            'type' => $asset['type'],
            'url' => $asset['url'],
            'key' => self::normalize($asset['url']),
            'handle' => $asset['handle'],
            'local' => $asset['local'],
            'size' => strlen($content),
            'score' => (int) $score['score'],
            'reason' => $score['reason'],
            'combined' => self::is_combined_url($asset['url']),
        ];
    }

    // ------------------------------------------------------------------ lectura de la home

    private static function fetch($url, $expect, $max_bytes)
    {
        $args = [
            'timeout' => self::TIMEOUT,
            'redirection' => 3,
            'sslverify' => true,
            'limit_response_size' => (int) $max_bytes,
            'user-agent' => 'Mozilla/5.0 (compatible; RND-Assets/1.0; +' . home_url('/') . ')',
        ];
        if ('html' === $expect) {
            $args['headers'] = ['Accept' => 'text/html,application/xhtml+xml'];
        }

        $response = wp_remote_get($url, $args);
        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 400) {
            return ['error' => 'HTTP ' . $code];
        }

        $type = strtolower((string) wp_remote_retrieve_header($response, 'content-type'));
        if ('' !== $type) {
            if ('html' === $expect && false === strpos($type, 'html')) {
                return ['error' => 'No es HTML'];
            }
            if ('html' !== $expect && false !== strpos($type, 'html')) {
                return ['error' => 'No es un archivo ' . strtoupper($expect)];
            }
        }

        $body = (string) wp_remote_retrieve_body($response);
        if ('' === $body) {
            return ['error' => 'Respuesta vacía'];
        }

        return ['body' => $body];
    }

    /**
     * <link rel="stylesheet"> y <script src> de la home, con su handle si WordPress lo imprime.
     */
    private static function extract_assets($html, $base)
    {
        $out = [];
        $seen = [];

        if (preg_match_all('/<link\b[^>]*>/is', $html, $matches)) {
            foreach ($matches[0] as $tag) {
                if (!preg_match('/\brel\s*=\s*(["\'])(.*?)\1/is', $tag, $rel)) {
                    continue;
                }
                if (false === stripos($rel[2], 'stylesheet')) {
                    continue;
                }
                if (!preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/is', $tag, $href)) {
                    continue;
                }

                $url = self::resolve($base, html_entity_decode($href[2], ENT_QUOTES, 'UTF-8'));
                if ('' === $url) {
                    continue;
                }
                $key = 'css|' . self::normalize($url);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = [
                    'type' => 'css',
                    'url' => $url,
                    'handle' => self::tag_handle($tag, 'css'),
                    'local' => self::is_local($url),
                ];
            }
        }

        if (preg_match_all('/<script\b[^>]*>/is', $html, $matches)) {
            foreach ($matches[0] as $tag) {
                if (!preg_match('/\bsrc\s*=\s*(["\'])(.*?)\1/is', $tag, $src)) {
                    continue;
                }

                $url = self::resolve($base, html_entity_decode($src[2], ENT_QUOTES, 'UTF-8'));
                if ('' === $url) {
                    continue;
                }
                $key = 'js|' . self::normalize($url);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = [
                    'type' => 'js',
                    'url' => $url,
                    'handle' => self::tag_handle($tag, 'js'),
                    'local' => self::is_local($url),
                ];
            }
        }

        return $out;
    }

    /**
     * WordPress imprime id="{handle}-css" / id="{handle}-js": de ahí sale el handle.
     */
    private static function tag_handle($tag, $type)
    {
        $suffix = '-' . $type;
        if (!preg_match('/\bid\s*=\s*(["\'])(.*?)\1/is', $tag, $id)) {
            return '';
        }

        $id = $id[2];
        if (strlen($id) > strlen($suffix) && substr($id, -strlen($suffix)) === $suffix) {
            return substr($id, 0, -strlen($suffix));
        }

        return '';
    }

    private static function is_local($url)
    {
        $home = wp_parse_url(home_url('/'));
        $parts = wp_parse_url($url);
        if (!$home || !$parts || empty($home['host']) || empty($parts['host'])) {
            return false;
        }

        return self::host_key($home['host']) === self::host_key($parts['host']);
    }

    private static function resolve($base, $href)
    {
        $href = trim($href);
        if ('' === $href || '#' === $href[0]) {
            return '';
        }

        $lower = strtolower($href);
        foreach (['data:', 'javascript:', 'mailto:', 'tel:', 'blob:'] as $scheme) {
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

        $query = '';
        if (false !== ($pos = strpos($href, '?'))) {
            $query = substr($href, $pos);
            $href = substr($href, 0, $pos);
        }
        $fragment = '';
        if (false !== ($pos = strpos($href, '#'))) {
            $fragment = substr($href, $pos);
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
     * Clave de comparación: sin esquema, sin www, sin query (versiones) ni fragmento.
     */
    public static function normalize($url)
    {
        $url = trim((string) $url);
        if ('' === $url) {
            return '';
        }

        if (0 === strpos($url, '//')) {
            $home = wp_parse_url(home_url('/'));
            $scheme = $home && !empty($home['scheme']) ? $home['scheme'] : 'https';
            $url = $scheme . ':' . $url;
        } elseif (0 === strpos($url, '/')) {
            // Raíz del dominio: no usar home_url('/') entero por si WordPress está en un subdirectorio.
            $home = wp_parse_url(home_url('/'));
            $scheme = $home && !empty($home['scheme']) ? $home['scheme'] : 'https';
            $host = $home && !empty($home['host']) ? $home['host'] : '';
            $port = $home && !empty($home['port']) ? ':' . (int) $home['port'] : '';
            $url = $scheme . '://' . $host . $port . $url;
        } elseif (!preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
            $url = home_url('/') . ltrim($url, '/');
        }

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

        return 'https://' . $host . $port . $path;
    }

    private static function host_key($host)
    {
        $host = strtolower((string) $host);

        return 0 === strpos($host, 'www.') ? substr($host, 4) : $host;
    }

    private static function remove_dots($path)
    {
        $path = '/' . ltrim((string) $path, '/');
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

    // ------------------------------------------------------------------ puntuación CSS

    private static function score_css($content, $tokens)
    {
        $selectors = self::css_selectors($content);
        $total = 0;
        $matched = 0;
        foreach ($selectors as $selector) {
            $token = self::selector_token($selector);
            if (!$token) {
                continue;
            }
            $total++;
            if (self::token_exists($token, $tokens)) {
                $matched++;
            }
        }

        if ($total < 5) {
            return [
                'score' => 30,
                'reason' => 'Muy pocas reglas para evaluar (posible fuente, animación o reset).',
            ];
        }

        return [
            'score' => max(0, min(100, 100 - (int) round($matched * 100 / $total))),
            'reason' => $matched . ' de ' . $total . ' selectores aparecen en la home.',
        ];
    }

    private static function css_selectors($css)
    {
        $css = preg_replace('#/\*.*?\*/#s', '', (string) $css);
        $out = [];
        if (preg_match_all('/([^{}]+)\{/', $css, $matches)) {
            foreach ($matches[1] as $chunk) {
                $chunk = trim($chunk);
                if ('' === $chunk || '@' === $chunk[0]) {
                    continue;
                }
                foreach (explode(',', $chunk) as $selector) {
                    $selector = trim($selector);
                    if ('' === $selector || preg_match('/^(from|to|\d+%)$/i', $selector)) {
                        continue;
                    }
                    $out[] = $selector;
                }
            }
        }

        return array_values(array_unique($out));
    }

    // ------------------------------------------------------------------ puntuación JS

    private static function score_js($content, $tokens, $inline, $asset)
    {
        $handle = strtolower((string) $asset['handle']);
        $path = strtolower((string) wp_parse_url($asset['url'], PHP_URL_PATH));
        $base = basename($path);

        $critical = [
            'jquery', 'jquery-core', 'jquery-migrate', 'wp-polyfill', 'wp-i18n', 'wp-hooks',
            'wp-dom-ready', 'wp-a11y', 'comment-reply', 'wp-embed', 'regenerator-runtime',
        ];
        foreach ($critical as $needle) {
            if ($needle === $handle || false !== strpos($base, $needle . '.js') || false !== strpos($path, '/' . $needle . '.')) {
                return [
                    'score' => 10,
                    'reason' => 'Librería base de WordPress o del navegador: normalmente necesaria.',
                ];
            }
        }

        $selectors = self::js_selectors($content);
        $sel_total = count($selectors);
        $sel_match = 0;
        foreach ($selectors as $selector) {
            $token = self::selector_token($selector);
            if ($token && self::token_exists($token, $tokens)) {
                $sel_match++;
            }
        }

        $globals = self::js_globals($content);
        $inline_lower = strtolower($inline);
        $global_match = 0;
        foreach ($globals as $name) {
            if ('' !== $name && false !== strpos($inline_lower, strtolower($name))) {
                $global_match++;
            }
        }

        $listeners = (bool) preg_match('/addEventListener\s*\(|\.on\s*\(|attachEvent\s*\(/i', $content);
        $referenced = '' !== $handle && false !== strpos($inline_lower, $handle);

        $score = 85;
        $parts = [];

        if ($sel_total > 0) {
            $score -= (int) round(($sel_match / $sel_total) * 70);
            $parts[] = $sel_match . ' de ' . $sel_total . ' selectores del archivo aparecen en la home';
        } else {
            $parts[] = 'no se detectaron selectores de DOM';
        }

        if ($globals) {
            if ($global_match > 0) {
                $score -= 20;
                $parts[] = $global_match . ' de ' . count($globals) . ' globales se usan en scripts inline';
            } else {
                $parts[] = 'sus globales no aparecen en scripts inline';
            }
        }

        if ($listeners) {
            $score -= 10;
            $parts[] = 'registra eventos';
        }

        if ($referenced) {
            $score -= 15;
            $parts[] = 'su handle aparece en scripts inline';
        }

        if (0 === $sel_total && !$globals) {
            $score = 60;
            $parts[] = 'sin datos suficientes: revisar a mano';
        }

        return [
            'score' => max(0, min(100, $score)),
            'reason' => ucfirst(implode('; ', $parts)) . '.',
        ];
    }

    private static function js_selectors($content)
    {
        $out = [];
        if (preg_match_all('/(?:querySelector(?:All)?|jQuery|\$)\s*\(\s*([\'"])(.+?)\1\s*\)/s', (string) $content, $matches)) {
            foreach ($matches[2] as $selector) {
                $selector = trim($selector);
                if ('' !== $selector && preg_match('/^[.#]?[A-Za-z_][\w-]*$/', $selector)) {
                    $out[] = $selector;
                }
            }
        }
        if (preg_match_all('/getElementById\s*\(\s*([\'"])(.+?)\1\s*\)/s', (string) $content, $matches)) {
            foreach ($matches[2] as $id) {
                $out[] = '#' . trim($id);
            }
        }
        if (preg_match_all('/getElementsByClassName\s*\(\s*([\'"])(.+?)\1\s*\)/s', (string) $content, $matches)) {
            foreach ($matches[2] as $class) {
                foreach (preg_split('/\s+/', trim($class)) as $name) {
                    if ('' !== $name) {
                        $out[] = '.' . $name;
                    }
                }
            }
        }

        return array_values(array_unique($out));
    }

    private static function js_globals($content)
    {
        $out = [];
        if (preg_match_all('/window\.([A-Za-z_$][\w$]*)\s*=/', (string) $content, $matches)) {
            foreach ($matches[1] as $name) {
                $out[] = $name;
            }
        }

        return array_values(array_unique($out));
    }

    // ------------------------------------------------------------------ tokens del HTML

    private static function html_tokens($html)
    {
        $tokens = ['classes' => [], 'ids' => [], 'tags' => []];

        // El contenido de scripts/estilos y los comentarios no son DOM real.
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', (string) $html);
        $html = preg_replace('#<style\b[^>]*>.*?</style>#is', '', (string) $html);
        $html = preg_replace('#<!--.*?-->#s', '', (string) $html);

        if (!preg_match_all('/<([a-zA-Z][\w:-]*)\b[^>]*>/s', $html, $matches)) {
            return $tokens;
        }

        foreach ($matches[0] as $i => $tag) {
            $tokens['tags'][strtolower($matches[1][$i])] = true;

            if (preg_match('/\bclass\s*=\s*(["\'])(.*?)\1/is', $tag, $class)) {
                foreach (preg_split('/\s+/', trim($class[2])) as $name) {
                    if ('' !== $name) {
                        $tokens['classes'][$name] = true;
                    }
                }
            }
            if (preg_match('/\bid\s*=\s*(["\'])(.*?)\1/is', $tag, $id)) {
                if ('' !== $id[2]) {
                    $tokens['ids'][$id[2]] = true;
                }
            }
        }

        return $tokens;
    }

    private static function inline_scripts($html)
    {
        $out = [];
        if (preg_match_all('/<script\b(?![^>]*\bsrc\s*=)[^>]*>(.*?)<\/script>/is', (string) $html, $matches)) {
            foreach ($matches[1] as $code) {
                $out[] = $code;
            }
        }
        if (preg_match_all('/\bon[a-z]+\s*=\s*(["\'])(.*?)\1/is', (string) $html, $matches)) {
            foreach ($matches[2] as $code) {
                $out[] = $code;
            }
        }

        return substr(implode("\n", $out), 0, 300000);
    }

    private static function selector_token($selector)
    {
        $selector = trim((string) $selector);
        if ('' === $selector || '@' === $selector[0]) {
            return null;
        }

        // Último compuesto del selector: es el elemento al que se aplica la regla.
        $selector = preg_replace('/\s*[>+~]\s*/', ' ', $selector);
        $parts = preg_split('/\s+/', $selector);
        $last = end($parts);
        if (!$last) {
            return null;
        }

        $last = preg_replace('/::?[A-Za-z-]+(\([^)]*\))?/', '', $last);
        if ('' === $last) {
            return null;
        }

        if (preg_match('/([.#])([A-Za-z_][\w-]*)/', $last, $match)) {
            return [
                'type' => '.' === $match[1] ? 'class' : 'id',
                'value' => $match[2],
            ];
        }
        if (preg_match('/^([A-Za-z][\w-]*)/', $last, $match)) {
            return ['type' => 'tag', 'value' => strtolower($match[1])];
        }

        return null;
    }

    private static function token_exists($token, $tokens)
    {
        $map = ['class' => 'classes', 'id' => 'ids', 'tag' => 'tags'];
        if (!isset($map[$token['type']])) {
            return false;
        }

        $bucket = $map[$token['type']];

        return isset($tokens[$bucket][$token['value']]);
    }
}
