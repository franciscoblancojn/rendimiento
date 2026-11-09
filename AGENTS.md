# Rendimiento — Reglas para IAs

## Código
- **PHP 7.4+**, estándares de WordPress. Clases y funciones con prefijo `RND_`.
- **JavaScript ES5 + jQuery** (sin arrow functions, `let`/`const` ni template literals). Funciones con prefijo `rnd_`.
- **CSS** con prefijo `rnd-` (las clases `fwue-` son de `wordpress_utils`).
- Escapar toda salida (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`) y sanitizar toda entrada.

## Seguridad
- Guardar ajustes: `check_admin_referer(RND_API_SETTINGS::ACTION)` + `current_user_can(RND_CAPABILITY)`.
- Todo AJAX pasa por `RND_API_SCAN::guard()`: `check_ajax_referer('rnd_nonce', 'nonce')` + `current_user_can(RND_CAPABILITY)`.
- El token de GitHub de `FWUUpdate` está en `index.php` (`token_array_split`), como en los demás plugins del autor. No agregar otras claves o secretos al código: `npm run check` falla si encuentra un token escrito de corrido.

## Arquitectura
- `index.php`: cabecera y constantes `RND_*`. Sin lógica. WooCommerce es **opcional**: comprobar `taxonomy_exists('product_cat')` antes de usar categorías.
- `src/_.php`: cargador maestro (data → services → hooks → api → page). Cada carpeta tiene su `_.php`; un archivo nuevo se agrega a ese `_.php` con `require_once RND_DIR . 'src/…'`.
- `src/data/`: `RND_USE_DATA_CONFIG::DEFAULTS` es la única lista de ajustes; `sanitize()` la normaliza (límites del rastreo y tipos de contenido).
- `src/services/categories.php`: conteo directo y por subcategorías. Solo se elimina una categoría si el total del subárbol es 0 y no es la categoría por defecto (`default_product_cat`).
- `src/services/crawler.php`: rastreo por lotes desde la home. El estado va en el transient `rnd_crawl_state` y el informe en `rnd_crawl_report`. No cambiar el esquema de URLs de `normalize()` sin revisar que la comparación con `get_permalink()` siga funcionando (ignora esquema, `www.`, fragmento y tracking).
- `src/services/pages.php`: las páginas huérfanas se envían a la **papelera** (`wp_trash_post`), nunca se borran de forma permanente.
- `src/api/`: `RND_API_SETTINGS` (admin-post) y `RND_API_SCAN` (AJAX: `crawl_start`, `crawl_batch`, `crawl_finish`, `delete_categories`, `delete_pages`).
- `src/page/`: menú (`add.php`), página con pestañas `FWUPage` (`page.php`) y una sección por pestaña (`sections/<key>.php`).
- Plugin genérico: **no** usar nombres, textos ni URLs de un sitio concreto.
- Rendimiento: CSS/JS del admin solo en la página del plugin; el rastreo va en lotes de `RND_CRAWLER::BATCH` (4) para no agotar el tiempo de PHP.
- Toda operación relevante queda en el log: `RND_LOG_SERVICE::add('tipo', [...])`.
- `libs/` es Composer (`npm run install`) y se versiona: el zip de `master` que instala `FWUUpdate` lo necesita. No editar a mano.

## Publicar
- Anotar los cambios en `CHANGELOG.md` bajo `## [Sin publicar]`.
- `npm run push-v -- patch|minor|major`.
