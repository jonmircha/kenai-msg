<?php
/**
 * Clase YamlParser
 * 
 * Un parser simple de YAML para procesar metadatos (FrontMatter).
 * Soporta pares clave-valor básicos y listas simples.
 */
class YamlParser
{
  /**
   * Parsea una cadena YAML simple a un array PHP
   * 
   * @param string $yamlString El contenido YAML a parsear
   * @return array Array asociativo con los datos
   */
  public static function parse(string $yamlString): array
  {
    $lines = explode("\n", $yamlString);
    $data = [];

    foreach ($lines as $line) {
      $line = trim($line);
      if ($line === '' || $line[0] === '#')
        continue;

      // clave: valor
      if (preg_match('/^([a-zA-Z0-9_]+):\s*(.+)$/', $line, $m)) {
        $key = $m[1];
        $value = trim($m[2]);

        // Valores booleanos, nulos, números
        if ($value === 'true')
          $value = true;
        elseif ($value === 'false')
          $value = false;
        elseif ($value === 'null')
          $value = null;
        elseif (is_numeric($value))
          $value += 0;
        elseif (preg_match('/^\[(.+)\]$/', $value, $arrMatch)) {
          $items = explode(',', $arrMatch[1]);
          $clean = [];
          foreach ($items as $item) {
            $item = trim($item);
            $item = trim($item, "\"'"); // quita comillas simples/dobles
            $clean[] = $item;
          }
          $value = $clean;
        } else {
          $value = trim($value, "\"'");
        }

        $data[$key] = $value;
      }
    }

    return $data;
  }
}
