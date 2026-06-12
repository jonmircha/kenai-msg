<?php
/**
 * Configuración de errores (segura por defecto)
 *
 * Los errores se MUESTRAN solo si APP_DEBUG está activo, p. ej. en local:
 *   APP_DEBUG=1 php -S localhost:8084
 * En producción (sin la variable) se REGISTRAN en el log del servidor pero
 * nunca se exponen al visitante, evitando filtrar rutas y trazas internas.
 */
$debug = filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');

/**
 * Carga de archivos del Core del sistema
 */
require_once 'app/core/YamlParser.php';
require_once 'app/core/ContentFile.php';
require_once 'app/core/MarKenai.php';
require_once 'app/core/helpers.php';


/**
 * 🛡️ Cabeceras de seguridad
 * Se envían antes de cualquier salida, por lo que aplican a todas las
 * respuestas (incluidas 404/500). La CSP usa script-src 'self' para
 * bloquear scripts inline (mitiga XSS), y 'unsafe-inline' solo en estilos
 * porque el sitio usa atributos style= en algunas plantillas/contenido.
 */
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header(
  "Content-Security-Policy: default-src 'self'; script-src 'self'; "
  . "style-src 'self' 'unsafe-inline'; img-src 'self' data:; "
  . "object-src 'none'; base-uri 'self'; frame-ancestors 'self'"
);


/**
 * 🧭 Detección de rutas
 * Detectar ruta solicitada y base del proyecto
 */
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = $_SERVER['SCRIPT_NAME'];
$basePath = str_replace('/index.php', '', $scriptName);
$uri = '/' . ltrim(str_replace($basePath, '', $requestUri), '/');
$uri = strtok($uri, '?');
$uri = trim($uri, '/');

/**
 * Normalización de ruta
 * Ruta vacía = index
 */
if ($uri === '')
  $uri = 'index';

/**
 * 🗺️ Rutas dinámicas especiales (XML / texto, sin layout):
 *    /sitemap.xml, /feed.xml y /robots.txt se generan al vuelo y terminan.
 */
if (in_array($uri, ['sitemap.xml', 'feed.xml', 'robots.txt'], true)) {
  $site = loadSiteConfig($basePath);
  if ($uri === 'robots.txt') {
    header('Content-Type: text/plain; charset=UTF-8');
    echo renderRobots($site);
  } else {
    header('Content-Type: application/xml; charset=UTF-8');
    echo $uri === 'feed.xml' ? renderFeed($site) : renderSitemap($site);
  }
  exit;
}

/**
 * Búsqueda de archivos y Enrutamiento
 * Buscar archivo .md, .html o .php
 */
$extensions = ['.md', '.html', '.php'];
$filepath = null;

/**
 * 🚦 Enrutamiento personalizado
 * Lógica para categorías amigables
 */
if (str_starts_with($uri, 'categorias/')) {
  $filepath = 'content/categorias.php';
  $parts = explode('/', $uri);
  // Asignar el tag a $_GET para que categorias.php lo consuma
  if (isset($parts[1])) {
    $_GET['tag'] = urldecode($parts[1]);
  }
} elseif (preg_match('#^blog/page/(\d+)$#', $uri, $m)) {
  // Paginación amigable del blog: /blog/page/N → carga blog.php con la página N
  $filepath = 'content/blog.php';
  $_GET['page'] = (int) $m[1];
} else {
  /**
   * Búsqueda estándar de archivos
   * 🔒 Confinada a content/ para evitar Path Traversal (LFI/RCE):
   *    realpath() resuelve los "../" y exigimos que la ruta final
   *    quede DENTRO de content/. Si no, se ignora (cae en 404).
   */
  $contentRoot = realpath(__DIR__ . '/content');
  foreach ($extensions as $ext) {
    $candidate = "content/$uri$ext";
    $real = realpath($candidate);
    if (
      $contentRoot !== false &&
      $real !== false &&
      str_starts_with($real, $contentRoot . DIRECTORY_SEPARATOR)
    ) {
      $filepath = $candidate;
      break;
    }
  }
}

/**
 * Manejo de Error 404
 * Mostrar 404 si no se encontró nada
 */
if (!$filepath) {
  http_response_code(404);
  $filepath = file_exists('content/404.md') ? 'content/404.md' : null;
  if (!$filepath) {
    die("<h1>Error 404</h1><p>Archivo no encontrado.</p>");
  }
}

try {
  /**
   * Configuración Global
   *
   * Carga app/config/site.yml y resuelve la URL base ('url'). 'url' es la base
   * ABSOLUTA del sitio (enlaces, canonical, Open Graph) y es OPCIONAL: si no se
   * define, se deriva de la petición. En producción conviene fijarla en
   * site.yml para tener una URL canónica estable (inmune a Host header
   * injection). $basePath (host-agnóstico) queda como $page['base'].
   */
  $site = loadSiteConfig($basePath);

  /**
   * 📄 Carga y Parseo
   * Cargar y parsear archivo de contenido
   */
  $file = new ContentFile($filepath, ['site' => $site]);
  $parsed = $file->parse();
  $meta = $parsed['meta'];

  /**
   * 🧾 Renderizado
   * Renderizado según extensión (.md, .php, .html)
   */
  if (str_ends_with($filepath, '.md')) {
    $parser = new MarKenai();
    // El contenido es de autor de confianza; cambia a true para escapar
    // todo el HTML crudo del Markdown (safe mode).
    $parser->setSafeMode(false);
    $html = $parser->text($parsed['content']);
  } elseif (str_ends_with($filepath, '.php')) {
    // Para archivos .php, ejecutar el contenido como plantilla PHP.
    // Datos disponibles dentro de la plantilla.
    $page = [
      'meta' => $meta,
      'filepath' => $filepath,
      'uri' => $uri,
      'layout' => $meta['layout'] ?? 'page',
      'base' => $basePath,
      'site' => $site
    ];

    // Renderizado SIN eval(): el cuerpo se cachea como archivo y se incluye.
    $html = renderTemplate($filepath, $parsed['content'], $page, $site);
  } else {
    $html = $parsed['content'];
  }

  /**
   * 📦 Datos para Layout
   * Datos que pasaremos al layout
   */
  $page = [
    'content' => $html,
    'meta' => $meta,
    'filepath' => $filepath,
    'uri' => $uri,
    'layout' => $meta['layout'] ?? 'page',
    'base' => $basePath,
    'site' => $site
  ];

  /**
   * 🧱 Layout Dinámico
   * Carga del layout correspondiente.
   * 🔒 El nombre proviene del frontmatter; se valida contra [a-zA-Z0-9_-]
   *    para impedir Path Traversal (p. ej. layout: ../core/helpers) al
   *    construir la ruta del include.
   */
  $layoutName = (string) $page['layout'];
  $layoutPath = preg_match('/^[a-zA-Z0-9_-]+$/', $layoutName)
    ? __DIR__ . "/app/layouts/{$layoutName}.php"
    : null;

  if ($layoutPath === null || !file_exists($layoutPath)) {
    http_response_code(500);
    error_log('[kenai] Layout inválido o no encontrado: ' . $layoutName);
    $detail = $debug ? ' <code>' . htmlspecialchars($layoutName) . '</code>' : '';
    die("<h1>Error</h1><p>No se encontró el layout{$detail}.</p>");
  }

  include $layoutPath;
} catch (\Throwable $e) {
  // Descartar cualquier salida parcial (p. ej. buffers abiertos al renderizar).
  while (ob_get_level() > 0) {
    ob_end_clean();
  }
  http_response_code(500);
  error_log('[kenai] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
  if ($debug) {
    echo '<h1>Error Interno</h1><pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
  } else {
    echo '<h1>Error Interno</h1><p>Ha ocurrido un error inesperado. Inténtalo de nuevo más tarde.</p>';
  }
}
