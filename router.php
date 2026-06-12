<?php
/**
 * router.php — SOLO para el servidor de desarrollo de PHP:
 *
 *     php -S localhost:8084 router.php
 *
 * Reproduce en local lo que en producción hace Apache con el .htaccess.
 * NO define ni enumera rutas: la resolución de los contenidos
 * (content/*.md|html|php) la sigue haciendo index.php dinámicamente.
 *
 * Por cada petición decide una de tres cosas:
 *   1) /app o /content        → 403 (igual que el .htaccess)
 *   2) archivo estático real  → return false (php -S lo sirve tal cual)
 *   3) cualquier otra ruta    → index.php (front controller)
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

// 1) Bloquear acceso directo a los directorios internos
if (preg_match('#^/(app|content)(/|$)#', $uri)) {
  http_response_code(403);
  echo 'Forbidden';
  return true;
}

// 2) Servir archivos estáticos existentes tal cual (assets, img, css, js)
if ($uri !== '/' && is_file(__DIR__ . $uri)) {
  return false;
}

// 3) Todo lo demás → front controller (resuelve la ruta de contenido)
require __DIR__ . '/index.php';
