# ⚡ Rendimiento

> Detecta categorías de productos vacías y páginas sin enlaces desde la home, y permite eliminarlas.

**Versión:** 1.0.1 · **WordPress:** 5.8+ · **PHP:** 7.4+ · **WooCommerce:** opcional · **Licencia:** GPLv2+

📋 [Registro de cambios](CHANGELOG.md) · 🤖 [Reglas para IAs](AGENTS.md) · 📦 [Releases](https://github.com/franciscoblancojn/rendimiento/releases)

---

## 💡 Cómo funciona

El plugin añade el menú **Rendimiento** con dos análisis de solo lectura (y una acción de limpieza para cada uno):

### Categorías vacías

- Revisa todas las categorías de productos (`product_cat`), incluidas las que no tienen productos.
- Distingue los productos **directos** de los de sus **subcategorías**: una categoría solo es eliminable
  cuando el total del subárbol es 0.
- La categoría por defecto de WooCommerce nunca se elimina.
- Se pueden eliminar de una en una o en lote. La eliminación es permanente (`wp_delete_term`).

### Páginas huérfanas

- Empieza en la home y sigue los enlaces internos (`<a href>`) de las páginas que encuentra.
- Al terminar, compara lo visitado con el contenido publicado de los tipos marcados (páginas, entradas
  y, si WooCommerce está activo, productos). Lo que no aparece enlazado desde la home queda como huérfano.
- No usa el sitemap: también detecta contenido que solo está en el sitemap.
- Solo sigue enlaces `<a href>` del HTML: los enlaces generados por JavaScript no se detectan.
- Se puede enviar a la papelera en lote. No se borra de forma permanente: se recupera desde la papelera.
- Las páginas del sistema (portada, página de entradas, tienda, carrito, checkout, mi cuenta y política
  de privacidad) nunca se envían a la papelera.

## ⚙️ Configuración

En el menú **Rendimiento** del admin:

| Pestaña | Ajustes |
|---|---|
| Categorías vacías | Lista de categorías sin productos, con conteos y eliminación. |
| Páginas huérfanas | URL de inicio, límite de páginas, profundidad, timeout y tipos de contenido. |

Ajustes del rastreo:

| Ajuste | Por defecto | Descripción |
|---|---|---|
| URL de inicio | Home | Debe ser del mismo dominio; si no, se usa la home. |
| Páginas | 300 | Máximo de URLs descubiertas. Al alcanzarlo, el informe avisa de posibles falsos huérfanos. |
| Profundidad | 4 | Niveles de enlaces que se siguen desde la home. |
| Timeout | 6 s | Tiempo máximo por petición. Cada lote pide 4 páginas. |
| Tipos de contenido | Páginas y entradas | Tipos publicados que se comprueban al final. |

El rastreo se hace por lotes AJAX con barra de progreso. Si se interrumpe, se puede continuar desde
la misma pestaña mientras el estado siga guardado (1 hora).

## 📋 Requisitos

- WordPress 5.8+
- PHP 7.4+
- WooCommerce: opcional (sin él, la pestaña de categorías muestra un aviso)
- Permiso `manage_options`

## ⚙️ Instalación

1. Descarga el zip de la rama `master`: https://github.com/franciscoblancojn/rendimiento/archive/refs/heads/master.zip
2. En WordPress: **Plugins → Añadir nuevo → Subir plugin** y actívalo.
3. Ve a **Rendimiento**, ajusta los límites del rastreo y pulsa «Analizar ahora».

## 🔄 Actualizaciones

- Usa `FWUUpdate` (librería `wordpress_utils`), configurado en `index.php` (repositorio, rama `master` y token), como en los demás plugins.
- En **Plugins** aparece «Actualizar» cuando hay una release más nueva en GitHub. Para eso, cada versión necesita su release: la crea el workflow al subir el tag.
- `FWUUpdate` instala el zip de la rama `master`, que se descomprime en la carpeta `rendimiento-master`. Conviene hacer la **primera instalación también desde ese zip**, para que la carpeta no cambie con la primera actualización: si cambia, WordPress desactiva el plugin.

## 🧑‍💻 Desarrollo

```bash
npm run install          # composer install → libs/ (franciscoblancojn/wordpress_utils)
npm run check            # validaciones: php -l, JS ES5, require_once, secretos y dependencias
npm run push-v -- patch  # o minor | major → publica una versión
```

`npm run push-v`:

1. exige una sección `## [Sin publicar]` en [`CHANGELOG.md`](CHANGELOG.md) y que `npm run check` pase;
2. sube la versión en `index.php`, `package.json` y este README;
3. cambia `## [Sin publicar]` por `## [X.Y.Z] - fecha`;
4. hace commit «Release X.Y.Z», crea el tag `X.Y.Z` y hace push.

Después, GitHub Actions crea la release con `rendimiento.zip`.

## 🗂️ Estructura

```
rendimiento/
├── index.php                 # Cabecera del plugin, constantes RND_*, FWUUpdate, carga
├── composer.json             # wordpress_utils, autoloader-suffix RND
├── libs/                     # Composer → libs (se versiona; no editar)
├── bin/harness.sh            # Validaciones (npm run check)
├── scripts/bump-version.sh   # npm run push-v
├── .github/workflows/        # Release con el zip al subir un tag
└── src/
    ├── _.php                 # Cargador maestro
    ├── data/                 # RND_USE_DATA_BASE / RND_USE_DATA_CONFIG (wp_options)
    ├── services/             # RND_CATEGORIES, RND_PAGES, RND_CRAWLER y RND_LOG_SERVICE
    ├── hooks/                # Integración con WordPress / WooCommerce
    ├── api/                  # Ajustes (admin-post) y AJAX de análisis
    ├── page/                 # Menú «Rendimiento» y secciones (FWUPage)
    ├── js/admin.js           # ES5 + jQuery
    └── css/admin.css
```
