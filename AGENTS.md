# Rendimiento — Reglas para IAs

## Código
- **PHP 7.4+**, estándares de WordPress. Clases y funciones con prefijo `RND_`.
- **JavaScript ES5 + jQuery** (sin arrow functions, `let`/`const` ni template literals). Funciones con prefijo `rnd_`.
- **CSS** con prefijo `rnd-` (las clases `fwue-` son de `wordpress_utils`).
- Escapar toda salida (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`) y sanitizar toda entrada.

## Seguridad
- Guardar ajustes: `check_admin_referer(RND_API_SETTINGS::ACTION)` + `current_user_can(RND_CAPABILITY)`.
- Guardar los ajustes de exclusión: `check_admin_referer(RND_API_ASSETS::ACTION_CONFIG)` + `current_user_can(RND_CAPABILITY)`.
- Todo AJAX pasa por `RND_API_SCAN::guard()`: `check_ajax_referer('rnd_nonce', 'nonce')` + `current_user_can(RND_CAPABILITY)`.
- El token de GitHub de `FWUUpdate` está en `index.php` (`token_array_split`), como en los demás plugins del autor. No agregar otras claves o secretos al código: `npm run check` falla si encuentra un token escrito de corrido.

## Arquitectura
- `index.php`: cabecera y constantes `RND_*`. Sin lógica. WooCommerce es **opcional**: comprobar `taxonomy_exists('product_cat')` antes de usar categorías.
- `src/_.php`: cargador maestro (data → services → hooks → api → page). Cada carpeta tiene su `_.php`; un archivo nuevo se agrega a ese `_.php` con `require_once RND_DIR . 'src/…'`.
- `src/data/`: `RND_USE_DATA_CONFIG::DEFAULTS` es la única lista de ajustes; `sanitize()` la normaliza (límites del rastreo y tipos de contenido).
- `src/services/categories.php`: conteo directo y por subcategorías. Solo se elimina una categoría si el total del subárbol es 0 y no es la categoría por defecto (`default_product_cat`).
- `src/services/crawler.php`: rastreo por lotes desde la home. El estado va en el transient `rnd_crawl_state` y el informe en `rnd_crawl_report`. No cambiar el esquema de URLs de `normalize()` sin revisar que la comparación con `get_permalink()` siga funcionando (ignora esquema, `www.`, fragmento y tracking).
- `src/services/pages.php`: las páginas huérfanas se envían a la **papelera** (`wp_trash_post`), nunca se borran de forma permanente.
- `src/services/assets.php`: análisis estático de CSS/JS de la home y lista de exclusión. El estado va en la opción `rnd_assets_state`, los tokens del HTML en `rnd_assets_home` y el informe en `rnd_assets_report` (opciones, no transients: deben sobrevivir a la purga de caché al excluir); la lista en la opción `rnd_excluded_assets`. El % es una orientación: CSS mide selectores ausentes del HTML; JS usa selectores de DOM, globales inline, eventos y librerías base. **No** prometer cobertura real. Si la home viene combinada por LiteSpeed, `analyze_start()` pide `?LSCWP_CTRL=before_optimize` (filtro `rnd_assets_unoptimized_urls`) para listar los archivos individuales. Nunca permitir excluir un archivo combinado (`is_combined_url()`): quitaría todo el CSS/JS de la página. `purge_cache()` purga LiteSpeed/WP Rocket al cambiar la lista.
- `src/hooks/assets.php`: aplica la lista con `wp_dequeue_*` a prioridad 9999 y con los filtros `style_loader_tag` / `script_loader_tag`. Nunca quitar una dependencia de otro archivo encolado (`is_dependency()`); nunca tocar el admin, el personalizador ni el modo prueba de un visitante.
- `src/api/`: `RND_API_SETTINGS` (admin-post), `RND_API_SCAN` (AJAX del rastreo y limpieza) y `RND_API_ASSETS` (AJAX del análisis de CSS/JS, exclusiones, ajustes y refresco `assets_render`). Las acciones de la UI (analizar, excluir, incluir, guardar ajustes) son AJAX y refrescan el informe/lista en sitio, sin recargar la página.
- `src/page/`: menú (`add.php`), página con pestañas `FWUPage` (`page.php`) y una sección por pestaña (`sections/<key>.php`). `sections/css.php` y `sections/js.php` comparten `sections/assets.php` mediante `$rnd_asset_type`.
- Plugin genérico: **no** usar nombres, textos ni URLs de un sitio concreto.
- Rendimiento: CSS/JS del admin solo en la página del plugin; el rastreo va en lotes de `RND_CRAWLER::BATCH` (4) y el análisis de assets en lotes de `RND_ASSETS::BATCH` (4) para no agotar el tiempo de PHP.
- Exclusiones CSS/JS: se aplican en **todo el sitio** (decisión del proyecto). El modo prueba, si está activo, solo aplica las exclusiones a administradores conectados; los visitantes no las ven.
- Toda operación relevante queda en el log: `RND_LOG_SERVICE::add('tipo', [...])`.
- `libs/` es Composer (`npm run install`) y se versiona: el zip de `master` que instala `FWUUpdate` lo necesita. No editar a mano.

## Publicar
- Anotar los cambios en `CHANGELOG.md` bajo `## [Sin publicar]`.
- `npm run push-v -- patch|minor|major`.
