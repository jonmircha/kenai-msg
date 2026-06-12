<?php

require_once __DIR__ . '/ContentFile.php'; // ContentFile::splitFrontMatter()

/**
 * Renderiza una plantilla de contenido .php SIN usar eval().
 *
 * El cuerpo (ya separado del frontmatter) se cachea como archivo PHP y se
 * incluye. Esto evita el anti-patrón eval(): soporta `?>` en el contenido,
 * los errores reportan archivo y línea reales, y nada se interpreta desde
 * una cadena. La caché se regenera cuando el archivo fuente cambia (mtime).
 *
 * @param string $filepath Ruta del archivo .php de contenido (clave de caché)
 * @param string $code     Cuerpo PHP a ejecutar (sin frontmatter)
 * @param array  $page     Datos de la página disponibles en la plantilla
 * @param array  $site     Configuración global del sitio
 * @return string HTML renderizado
 */
function renderTemplate(string $filepath, string $code, array $page, array $site): string
{
  $cacheDir = __DIR__ . '/../cache';
  if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
  }

  // Un archivo de caché por plantilla; se regenera si la fuente es más nueva.
  $cacheFile = $cacheDir . '/' . md5($filepath) . '.php';
  if (!file_exists($cacheFile) || filemtime($cacheFile) < filemtime($filepath)) {
    file_put_contents($cacheFile, $code, LOCK_EX);
  }

  // $page y $site quedan en scope dentro de la plantilla incluida;
  // las funciones helper son globales y siguen disponibles.
  ob_start();
  include $cacheFile;
  return ob_get_clean();
}

/**
 * Incluye un partial desde /partials
 * @param string $name nombre del archivo sin extensión (.php)
 * @param array $vars variables opcionales que estarán disponibles dentro del partial
 */
function partial(string $name, array $vars = [])
{
  global $site; // ← importante para hacer disponible el site global

  $partialPath = __DIR__ . '/../partials/' . $name . '.php';

  if (!file_exists($partialPath)) {
    echo "<!-- Partial no encontrado: $name -->";
    return;
  }

  extract(['site' => $site]); // garantiza que $site esté disponible
  extract($vars); // permite sobreescribir si se necesita

  include $partialPath;
}

/**
 * ¿El contenido debe aparecer en listados públicos?
 *
 * Oculta:
 *   - Borradores: `draft: true` en el FrontMatter.
 *   - Programados: `date` en el futuro (publicación diferida).
 *
 * En modo desarrollo (APP_DEBUG) devuelve siempre true, para poder
 * previsualizar borradores y publicaciones programadas. El acceso directo
 * por URL no se ve afectado: esto solo filtra los LISTADOS.
 *
 * @param array $meta Metadatos (FrontMatter) del contenido
 * @return bool
 */
function isPublished(array $meta): bool
{
  if (filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN)) {
    return true;
  }

  if (!empty($meta['draft'])) {
    return false;
  }

  if (!empty($meta['date'])) {
    $ts = strtotime((string) $meta['date']);
    if ($ts !== false && $ts > time()) {
      return false;
    }
  }

  return true;
}

/**
 * Pagina un array de items.
 *
 * @param array $items   Items a paginar (p. ej. el resultado de getCollection)
 * @param int   $page    Página actual (1-indexada; se acota al rango válido)
 * @param int   $perPage Items por página
 * @return array{items: array, page: int, perPage: int, total: int, totalPages: int, hasPrev: bool, hasNext: bool, prevPage: int, nextPage: int}
 */
function paginate(array $items, int $page = 1, int $perPage = 5): array
{
  $perPage = max(1, $perPage);
  $total = count($items);
  $totalPages = max(1, (int) ceil($total / $perPage));
  $page = max(1, min($page, $totalPages));
  $offset = ($page - 1) * $perPage;

  return [
    'items' => array_slice($items, $offset, $perPage),
    'page' => $page,
    'perPage' => $perPage,
    'total' => $total,
    'totalPages' => $totalPages,
    'hasPrev' => $page > 1,
    'hasNext' => $page < $totalPages,
    'prevPage' => $page - 1,
    'nextPage' => $page + 1,
  ];
}

/**
 * Obtiene todos los posts del blog desde content/blog/
 * @return array Array de posts con metadata y url
 */
function getAllPosts()
{
  global $site;
  $postsDir = __DIR__ . '/../../content/blog/';
  $posts = [];

  if (!is_dir($postsDir)) {
    return $posts;
  }

  $files = glob($postsDir . '*.md');

  foreach ($files as $file) {
    $front = ContentFile::splitFrontMatter(file_get_contents($file));

    // Solo posts con frontmatter, publicados (no borrador / no fecha futura)
    if ($front['hasFrontMatter'] && isPublished($front['meta'])) {
      $filename = basename($file, '.md');
      $posts[] = [
        'url' => $site['url'] . '/blog/' . $filename,
        'data' => $front['meta'],
        'filepath' => $file
      ];
    }
  }

  return $posts;
}

/**
 * Obtiene TODO el contenido del sitio (blog + páginas) recursivamente
 * @return array Array de items con metadata y url
 */
function getAllSiteContent()
{
  // Memoización por request: el índice de contenido no cambia dentro de una
  // misma petición. Una página como /blog llama a getCollection/getAllTags
  // varias veces; sin esto se re-escanearía todo el disco en cada llamada.
  static $cache = null;
  if ($cache !== null) {
    return $cache;
  }

  global $site;
  $contentDir = __DIR__ . '/../../content/';
  $items = [];

  $scan = function ($dir) use (&$scan, &$items, $site, $contentDir) {
    $files = glob($dir . '*');

    foreach ($files as $file) {
      if (is_dir($file)) {
        $scan($file . '/');
        continue;
      }

      if (!preg_match('/\.(md|html|php)$/', $file)) {
        continue;
      }

      $front = ContentFile::splitFrontMatter(file_get_contents($file));
      if ($front['hasFrontMatter'] && isPublished($front['meta'])) {
        $meta = $front['meta'];

        // Generar URL relativa limpia
        $relativePath = str_replace($contentDir, '', $file);
        $urlPath = preg_replace('/\.(md|html|php)$/', '', $relativePath);
        // Si es index, quitarlo para url limpia, excepto si es raíz
        if (basename($urlPath) === 'index') {
          $urlPath = dirname($urlPath);
          if ($urlPath === '.')
            $urlPath = '';
        }

        $url = ($site['url'] ?? '') . '/' . ltrim($urlPath, '/');

        $items[] = [
          'url' => $url,
          'data' => $meta,
          'filepath' => $file
        ];
      }
    }
  };

  $scan($contentDir);

  $cache = $items;
  return $items;
}

/**
 * Obtiene todos los tags únicos del sitio con su conteo
 * @return array Array asociativo ['tag' => count]
 */
function getAllTags()
{
  $items = getAllSiteContent();
  $tags = [];

  foreach ($items as $item) {
    if (isset($item['data']['tags']) && is_array($item['data']['tags'])) {
      foreach ($item['data']['tags'] as $tag) {
        if (!isset($tags[$tag])) {
          $tags[$tag] = 0;
        }
        $tags[$tag]++;
      }
    }
  }

  ksort($tags); // Ordenar alfabéticamente
  return $tags;
}

/**
 * Obtiene posts filtrados por tag/colección desde TODO el sitio
 * @param string $tag El tag para filtrar
 * @param bool $reverse Si debe ordenarse en reversa
 * @return array Array de posts filtrados
 */
function getCollection(string $tag, bool $reverse = false)
{
  // Ahora buscamos en todo el sitio, no solo en blog
  $allItems = getAllSiteContent();
  $filtered = [];

  foreach ($allItems as $item) {
    if (isset($item['data']['tags']) && is_array($item['data']['tags']) && in_array($tag, $item['data']['tags'])) {
      $filtered[] = $item;
    }
  }

  // Ordenar por fecha
  usort($filtered, function ($a, $b) {
    $dateA = $a['data']['date'] ?? '';
    $dateB = $b['data']['date'] ?? '';
    return strcmp($dateA, $dateB);
  });

  if ($reverse) {
    $filtered = array_reverse($filtered);
  }

  return $filtered;
}

/**
 * Busca un término en todos los archivos del sitio
 * @param string $query El término a buscar
 * @return array Array de resultados con título, descripción, url y extracto
 */
function searchContent(string $query)
{
  global $site;
  $results = [];
  $query = strtolower(trim($query));

  if (empty($query)) {
    return $results;
  }

  // Acotar la longitud: cada búsqueda recorre TODOS los archivos del sitio,
  // así que limitamos el término para evitar abuso/sobrecarga.
  $query = mb_substr($query, 0, 100);

  $contentDir = __DIR__ . '/../../content/';

  // Función recursiva para buscar archivos
  $searchFiles = function ($dir) use (&$searchFiles, $query, &$results, $site, $contentDir) {
    $files = glob($dir . '*');

    foreach ($files as $file) {
      if (is_dir($file)) {
        $searchFiles($file . '/');
        continue;
      }

      // Solo procesar archivos .md, .html, .php
      if (!preg_match('/\.(md|html|php)$/', $file)) {
        continue;
      }

      // Separar frontmatter (si existe); el cuerpo es el texto a indexar.
      $front = ContentFile::splitFrontMatter(file_get_contents($file));
      $meta = $front['meta'];
      $bodyContent = $front['content'];

      // No indexar borradores ni publicaciones con fecha futura.
      if (!isPublished($meta)) {
        continue;
      }

      // Buscar en título, descripción y contenido
      $title = $meta['title'] ?? basename($file);
      $description = $meta['description'] ?? '';
      $searchText = strtolower($title . ' ' . $description . ' ' . $bodyContent);

      if (strpos($searchText, $query) !== false) {
        // Generar URL relativa
        $relativePath = str_replace($contentDir, '', $file);
        $url = '/' . preg_replace('/\.(md|html|php)$/', '', $relativePath);

        // Crear extracto con contexto
        $excerpt = $description;
        if (empty($excerpt)) {
          $plainText = strip_tags($bodyContent);
          $position = stripos($plainText, $query);
          if ($position !== false) {
            $start = max(0, $position - 100);
            $excerpt = substr($plainText, $start, 200);
            $excerpt = '...' . trim($excerpt) . '...';
          } else {
            $excerpt = substr($plainText, 0, 150) . '...';
          }
        }

        $results[] = [
          'title' => $title,
          'description' => $description,
          'url' => ($site['url'] ?? '') . $url,
          'excerpt' => $excerpt,
          'filepath' => $file
        ];
      }
    }
  };

  $searchFiles($contentDir);

  return $results;
}

/**
 * Carga la configuración del sitio (app/config/site.yml) y resuelve la URL base.
 *
 * Si `url` no está definida, se deriva de la petición (esquema + host +
 * prefijo de ruta). Expone también `base` (el prefijo de ruta).
 *
 * @param string $basePath Prefijo de ruta donde está montada la app
 * @return array Configuración del sitio
 */
function loadSiteConfig(string $basePath): array
{
  $site = [];
  $path = __DIR__ . '/../config/site.yml';
  if (file_exists($path)) {
    $site = YamlParser::parse(file_get_contents($path));
  }

  $site['base'] = $basePath;
  if (empty($site['url'])) {
    $https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
      || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
      || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    $site['url'] = ($https ? 'https' : 'http') . '://' . $host . $basePath;
  }

  return $site;
}

/**
 * Genera el sitemap.xml del sitio (todo el contenido publicado).
 *
 * Excluye lo que ya filtra getAllSiteContent() (borradores / fecha futura) y
 * las páginas con `sitemap: false` en su FrontMatter (p. ej. 404, búsqueda).
 *
 * @param array $site Configuración del sitio (para la URL base)
 * @return string XML del sitemap
 */
function renderSitemap(array $site): string
{
  $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
  $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

  foreach (getAllSiteContent() as $item) {
    if (($item['data']['sitemap'] ?? true) === false) {
      continue;
    }
    $xml .= "  <url>\n";
    $xml .= '    <loc>' . htmlspecialchars($item['url'], ENT_QUOTES) . "</loc>\n";
    if (!empty($item['data']['date']) && ($ts = strtotime((string) $item['data']['date']))) {
      $xml .= '    <lastmod>' . date('Y-m-d', $ts) . "</lastmod>\n";
    }
    $xml .= "  </url>\n";
  }

  $xml .= '</urlset>' . "\n";
  return $xml;
}

/**
 * Genera el feed RSS 2.0 del blog (últimos artículos publicados).
 *
 * @param array $site  Configuración del sitio
 * @param int   $limit Número máximo de artículos en el feed
 * @return string XML del feed RSS
 */
function renderFeed(array $site, int $limit = 20): string
{
  $base = rtrim($site['url'] ?? '', '/');
  $posts = array_slice(getCollection('blog', true), 0, $limit);

  $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
  $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
  $xml .= "<channel>\n";
  $xml .= '  <title>' . htmlspecialchars($site['title'] ?? '', ENT_QUOTES) . "</title>\n";
  $xml .= '  <link>' . htmlspecialchars($base . '/blog', ENT_QUOTES) . "</link>\n";
  $xml .= '  <description>' . htmlspecialchars($site['description'] ?? '', ENT_QUOTES) . "</description>\n";
  $xml .= '  <atom:link href="' . htmlspecialchars($base . '/feed.xml', ENT_QUOTES) . '" rel="self" type="application/rss+xml"/>' . "\n";

  foreach ($posts as $post) {
    $url = htmlspecialchars($post['url'], ENT_QUOTES);
    $xml .= "  <item>\n";
    $xml .= '    <title>' . htmlspecialchars($post['data']['title'] ?? '', ENT_QUOTES) . "</title>\n";
    $xml .= '    <link>' . $url . "</link>\n";
    $xml .= '    <guid isPermaLink="true">' . $url . "</guid>\n";
    if (!empty($post['data']['date']) && ($ts = strtotime((string) $post['data']['date']))) {
      $xml .= '    <pubDate>' . date(DATE_RSS, $ts) . "</pubDate>\n";
    }
    if (!empty($post['data']['description'])) {
      $xml .= '    <description>' . htmlspecialchars($post['data']['description'], ENT_QUOTES) . "</description>\n";
    }
    $xml .= "  </item>\n";
  }

  $xml .= "</channel>\n</rss>\n";
  return $xml;
}

/**
 * Genera el robots.txt (permite todo y apunta al sitemap).
 *
 * @param array $site Configuración del sitio
 * @return string Contenido de robots.txt
 */
function renderRobots(array $site): string
{
  $base = rtrim($site['url'] ?? '', '/');
  return "User-agent: *\nAllow: /\n\nSitemap: {$base}/sitemap.xml\n";
}
