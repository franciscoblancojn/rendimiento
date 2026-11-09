# 📋 Registro de cambios

Todos los cambios importantes del plugin Rendimiento. Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/).

> ✍️ Antes de publicar, agrega arriba una sección `## [Sin publicar]` con los cambios. `npm run push-v -- patch|minor|major` la renombra a `## [X.Y.Z] - fecha`, y no publica si esa sección no existe.

## [1.0.0] - 2026-11-09

### ✨ Añadido
- 🎉 Primera versión: detecta categorías de productos vacías (directas y por subcategorías) y permite eliminarlas.
- 🔗 Rastreo de enlaces internos desde la home por lotes AJAX, con barra de progreso y opción de continuar un análisis interrumpido.
- 🗑️ Informe de páginas, entradas y productos publicados sin enlaces desde la home, con envío a la papelera.
- ⚙️ Ajustes de URL de inicio, límite de páginas, profundidad, timeout y tipos de contenido.
- 📝 Registro de análisis y eliminaciones en el log del plugin.
