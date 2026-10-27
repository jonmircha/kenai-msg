# Changelog

Todos los cambios notables de este proyecto se documentan en este archivo.

El formato se basa en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/)
y el proyecto sigue [Versionado Semántico](https://semver.org/lang/es/).

## [Unreleased]

### Fixed

- **MarKenai: un párrafo después de una lista ya no se mete en su último ítem.** La
  continuación «perezosa» de un ítem (un renglón sin sangría que sigue su párrafo) se
  aplicaba también después de una línea en blanco, y en CommonMark esa línea en blanco
  cierra la lista. El párrafo, la imagen o el enlace siguiente quedaban dentro del último
  `<li>`, pegados y sin espacio («Fusionar.Cuando…»), a veces con el HTML mal anidado.
  Afectaba a la propia página `acerca`, con siete párrafos dentro de listas.

## [1.0.0] - 2026-06-11

Primera versión pública de **kEnAi MSG — Minimalist Site Generator**: un generador
de sitios en PHP vanilla, **sin dependencias de terceros** y **sin paso de build**
(renderiza dinámicamente en cada petición desde una sola carpeta).

### Added

- **Motor de render dinámico**: front controller (`index.php`) que resuelve
  `content/<uri>.{md,html,php}` y lo envuelve en un layout. Sin compilación.
- **MarKenai**: parser de Markdown propio y sin dependencias. GFM de uso común:
  encabezados con IDs únicos (estilo GitHub), código cercado con resaltado Prism,
  tablas con alineación, listas anidadas, _task lists_, tachado, énfasis, citas,
  reglas horizontales, enlaces/imágenes (inline y por referencia), autolinks y emojis.
- **Diagramas Mermaid** (` ```mermaid ` → `<pre class="mermaid">`) con runtime
  **auto-hospedado** (sin CDN) y **carga diferida en dos niveles**.
- **Layouts** (`page`, `post`) y **partials** (`metatags`, `header`, `footer`, `search`).
- **Sistema de etiquetas / categorías**: `/categorias` y `/categorias/<tag>`.
- **Búsqueda** de texto completo: `/busqueda?q=termino`.
- **SEO automático**: `<link rel="canonical">`, Open Graph, Twitter Cards y
  `<link rel="alternate">` al feed. Imagen social desde el frontmatter `image` o el
  `ogImage` global de `site.yml`.
- **Sitemap, Feed RSS y robots.txt dinámicos**: `/sitemap.xml`, `/feed.xml` (RSS 2.0)
  y `/robots.txt`, generados al vuelo (sin archivos compilados).
- **Borradores y publicación programada**: `draft: true` y `date` futura se ocultan
  de listados, búsqueda y sitemap (visibles en desarrollo con `APP_DEBUG`).
- **Paginación del blog**: rutas amigables `/blog/page/N`, `perPage` configurable y
  helper reutilizable `paginate()`.
- **Configuración** `app/config/site.yml`: `url` opcional (se auto-deriva de la
  petición si no se define), `ogImage`, `twitter` y `perPage`.
- **`router.php`** para el servidor de desarrollo de PHP (replica el comportamiento
  de Apache: sirve estáticos, bloquea `app/`/`content/`, enruta el resto a `index.php`).
- **Resaltado de sintaxis** con Prism 1.30.0 (auto-hospedado).
- **`LICENSE`** (MIT) y documentación completa en `README.md` y en la página on-site
  `content/acerca.md`.

### Security

- Confinamiento anti **Path Traversal** de las rutas a `content/` con `realpath()`;
  el nombre del layout se valida antes de incluirlo.
- Plantillas `.php` ejecutadas mediante `include` cacheado (`app/cache/`), **nunca
  `eval`**; la interpolación `{{ }}` no se aplica a `.php`.
- **Content-Security-Policy** estricta (`script-src 'self'`) y cabeceras
  `X-Content-Type-Options`, `X-Frame-Options` y `Referrer-Policy`.
- Acceso HTTP directo a `app/` y `content/` denegado (Apache vía `.htaccess` y
  desarrollo vía `router.php`).
- **Manejo de errores por entorno**: en producción se registran sin exponerse; con
  `APP_DEBUG` se muestran. El `catch` de nivel superior es `\Throwable`.
- **Saneo de URLs** en MarKenai (bloquea `javascript:`, `data:`, …) y _safe mode_
  opcional para escapar todo el HTML crudo del Markdown.
- Escapado de la salida (metaetiquetas, búsqueda) y límite de longitud en la búsqueda.

[Unreleased]: https://github.com/jonmircha/kenai-msg/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/jonmircha/kenai-msg/releases/tag/v1.0.0
