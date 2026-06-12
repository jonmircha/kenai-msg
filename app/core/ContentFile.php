<?php
require_once __DIR__ . '/YamlParser.php';

/**
 * Clase ContentFile
 * 
 * Se encarga de leer, parsear y procesar archivos de contenido (.md, .html, .php).
 * Separa el FrontMatter (YAML) del contenido y realiza la interpolación de variables.
 */
class ContentFile
{
  private string $path;
  private array $meta = [];
  private string $content = '';
  private array $globals = [];

  /**
   * Constructor
   * @param string $filepath Ruta absoluta al archivo
   * @param array $globals Variables globales disponibles en el contexto (ej: site)
   * @throws Exception Si el archivo no existe
   */
  public function __construct(string $filepath, array $globals = [])
  {
    if (!file_exists($filepath)) {
      throw new Exception("Archivo no encontrado: $filepath");
    }

    $this->path = $filepath;
    $this->globals = $globals; // ej. ['site' => [...]]
  }

  /**
   * Separa el FrontMatter (YAML) del cuerpo de un contenido en bruto.
   *
   * Punto único de parseo de frontmatter (antes la misma regex estaba
   * duplicada en varias funciones). Devuelve:
   *   - meta:           array con los metadatos YAML (vacío si no hay)
   *   - content:        cuerpo sin el bloque de frontmatter
   *   - hasFrontMatter: si el documento incluía bloque ---...---
   *
   * @param string $raw Contenido completo del archivo
   * @return array{meta: array, content: string, hasFrontMatter: bool}
   */
  public static function splitFrontMatter(string $raw): array
  {
    if (preg_match('/^---\s*(.*?)\s*---\s*(.*)$/s', $raw, $m)) {
      return [
        'meta' => YamlParser::parse($m[1]),
        'content' => $m[2],
        'hasFrontMatter' => true,
      ];
    }

    return ['meta' => [], 'content' => $raw, 'hasFrontMatter' => false];
  }

  /**
   * Parsea el archivo separando metadatos y contenido
   *
   * @return array ['meta' => array, 'content' => string]
   */
  public function parse(): array
  {
    $raw = file_get_contents($this->path);

    $front = self::splitFrontMatter($raw);
    $this->meta = $front['meta'];
    $this->content = $front['content'];

    // Contexto: variables YAML + globals (site, etc.)
    $context = array_merge($this->meta, $this->globals);

    // Interpolación {{ }} SOLO para .md y .html.
    // Los .php se ejecutan como plantilla (usan echo / etiquetas PHP nativas);
    // interpolar antes de ejecutarlos permitiría inyectar PHP desde el frontmatter.
    if (!str_ends_with($this->path, '.php')) {
      // Interpolación: {{ variable }} , {{ site.title }} , {{ tags[1] }} , {{ site.menu[0] }}
      $this->content = preg_replace_callback(
        '/{{\s*([a-zA-Z0-9_.\[\]]+)\s*}}/',
        function ($match) use ($context) {
          return $this->resolvePath($match[1], $context);
        },
        $this->content
      );
    }

    return [
      'meta' => $this->meta,
      'content' => $this->content,
    ];
  }

  /**
   * Resuelve rutas de variables anidadas (dot notation)
   * 
   * Soporta:
   * - Propiedades simples: title
   * - Propiedades anidadas: site.title
   * - Arrays con índice: tags[0], site.menu[1]
   * 
   * @param string $path La ruta de la variable (ej: "site.title")
   * @param array $context El array de datos donde buscar
   * @return mixed El valor encontrado o cadena vacía
   */
  private function resolvePath(string $path, array $context)
  {
    $segments = explode('.', $path);
    $value = $context;

    foreach ($segments as $segment) {
      // segment puede ser "tags" o "tags[1]"
      if (!preg_match('/^([a-zA-Z0-9_]+)(\[(\d+)\])?$/', $segment, $m)) {
        return '';
      }

      $key = $m[1];

      // Primero: bajar al nivel de la clave
      if (is_array($value) && array_key_exists($key, $value)) {
        $value = $value[$key];
      } else {
        return '';
      }

      // Segundo: si hay índice [n], accederlo
      if (isset($m[3])) {
        $index = (int) $m[3];
        if (is_array($value) && array_key_exists($index, $value)) {
          $value = $value[$index];
        } else {
          return '';
        }
      }
    }

    return is_scalar($value) ? $value : '';
  }
}
