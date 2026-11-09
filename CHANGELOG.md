# 📋 Registro de cambios

Todos los cambios importantes del plugin Rendimiento. Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/).

> ✍️ Antes de publicar, agrega arriba una sección `## [Sin publicar]` con los cambios. `npm run push-v -- patch|minor|major` la renombra a `## [X.Y.Z] - fecha`, y no publica si esa sección no existe.

## [Sin publicar]

### ✨ Añadido
- 🧩 Dos pestañas nuevas, **Excluir CSS** y **Excluir JS**: analizan la home, listan sus archivos, calculan un % aproximado de «innecesario» (0 = obligatorio, 100 = se puede eliminar) y permiten añadirlos a una lista de exclusión.
- 🚫 Los archivos de la lista de exclusión se quitan del frontend con `wp_dequeue_*` (y filtros de etiqueta como red de seguridad), en todo el sitio.
- 🧪 Interruptor general y modo prueba para aplicar las exclusiones solo a administradores mientras se valida.

## [1.0.1] - 2026-11-09

### 🐛 Corregido
- 🗑️ Las páginas enviadas a la papelera desaparecen de la tabla de huérfanas al recargar. Antes seguían apareciendo porque el informe guardado en el transient no se actualizaba; ahora se quitan al borrar y el informe descarta también lo que ya no está publicado.

## [1.0.0] - 2026-11-09

### ✨ Añadido
- 🎉 Primera versión: detecta categorías de productos vacías (directas y por subcategorías) y permite eliminarlas.
- 🔗 Rastreo de enlaces internos desde la home por lotes AJAX, con barra de progreso y opción de continuar un análisis interrumpido.
- 🗑️ Informe de páginas, entradas y productos publicados sin enlaces desde la home, con envío a la papelera.
- ⚙️ Ajustes de URL de inicio, límite de páginas, profundidad, timeout y tipos de contenido.
- 📝 Registro de análisis y eliminaciones en el log del plugin.
