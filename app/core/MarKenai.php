<?php

/**
 * MarKenai 🦊📝
 *
 * Parser de Markdown propio para kEnAi MSG, SIN dependencias externas
 * (no depende de Parsedown ni de ninguna librería de terceros).
 *
 * Soporta el dialecto "GitHub Flavored Markdown" de uso común:
 *   - Encabezados ATX (#) y Setext (=== / ---) con IDs únicos (estilo GitHub)
 *   - Énfasis: **negrita**, *cursiva*, ***ambas***, ~~tachado~~, `código`
 *   - Bloques de código cercados (``` y ~~~) con lenguaje (clase language-*)
 *   - Diagramas Mermaid (```mermaid → <pre class="mermaid">)
 *   - Código indentado (4 espacios)
 *   - Listas ordenadas, desordenadas y anidadas, con task lists [ ] / [x]
 *   - Tablas (pipe tables) con alineación
 *   - Citas (>) anidadas
 *   - Reglas horizontales (---, ***, ___)
 *   - Enlaces e imágenes (inline y por referencia), autolinks <...> y "desnudos"
 *   - Emojis por shortcode (:smile:)
 *   - HTML inline/bloque (se respeta o se escapa según safe mode)
 *   - Saltos de línea duros (dos espacios o backslash al final de línea)
 *
 * Seguridad:
 *   - setSafeMode(true): escapa todo el HTML crudo del Markdown.
 *   - Las URLs de enlaces/imágenes se sanean SIEMPRE (se bloquean esquemas
 *     peligrosos como javascript:, data:, vbscript:).
 *
 * Uso:
 *   $md = new MarKenai();
 *   $html = $md->text($markdown);
 */
class MarKenai
{
  private bool $safeMode = false;
  private bool $breaksEnabled = false;
  private array $references = [];
  private array $usedSlugs = [];

  /** Esquemas de URL permitidos en enlaces e imágenes. */
  private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'ftp', 'tel'];

  /** Mapa básico de shortcodes de emoji → carácter Unicode. */
  private const EMOJI = [
    'smile' => '😄', 'grin' => '😁', 'joy' => '😂', 'rofl' => '🤣',
    'wink' => '😉', 'blush' => '😊', 'heart_eyes' => '😍', 'thinking' => '🤔',
    'sunglasses' => '😎', 'cry' => '😢', 'sob' => '😭', 'angry' => '😠',
    'thumbsup' => '👍', '+1' => '👍', 'thumbsdown' => '👎', '-1' => '👎',
    'ok_hand' => '👌', 'clap' => '👏', 'wave' => '👋', 'pray' => '🙏',
    'muscle' => '💪', 'fire' => '🔥', 'star' => '⭐', 'sparkles' => '✨',
    'tada' => '🎉', 'rocket' => '🚀', 'heart' => '❤️', 'broken_heart' => '💔',
    'check' => '✔️', 'white_check_mark' => '✅', 'x' => '❌', 'warning' => '⚠️',
    'bulb' => '💡', 'book' => '📖', 'memo' => '📝', 'pushpin' => '📌',
    'eyes' => '👀', 'zap' => '⚡', 'bug' => '🐛', 'lock' => '🔒',
    'tag' => '🏷️', 'fox_face' => '🦊', 'fox' => '🦊', 'robot' => '🤖',
    'point_right' => '👉', 'point_left' => '👈', 'arrow_right' => '➡️',
  ];

  public function setSafeMode(bool $enabled): self
  {
    $this->safeMode = $enabled;
    return $this;
  }

  public function setBreaksEnabled(bool $enabled): self
  {
    $this->breaksEnabled = $enabled;
    return $this;
  }

  /**
   * Convierte una cadena Markdown en HTML.
   */
  public function text(string $markdown): string
  {
    $this->references = [];
    $this->usedSlugs = [];

    $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
    $markdown = $this->expandTabs($markdown);
    $markdown = $this->extractReferences($markdown);

    $lines = explode("\n", $markdown);
    return trim($this->parseBlocks($lines));
  }

  /* ===================================================================
   *  NIVEL DE BLOQUE
   * =================================================================== */

  private function parseBlocks(array $lines): string
  {
    $html = '';
    $n = count($lines);
    $i = 0;

    while ($i < $n) {
      $line = $lines[$i];

      // Línea en blanco → separador
      if (trim($line) === '') {
        $i++;
        continue;
      }

      // 1) Bloque de código cercado (``` o ~~~), incl. mermaid
      if (preg_match('/^ {0,3}(`{3,}|~{3,})[ \t]*([\w#+.\-]*)[ \t]*$/', $line, $m)) {
        $fenceChar = $m[1][0];
        $fenceLen = strlen($m[1]);
        $lang = strtolower($m[2]);
        $i++;
        $code = [];
        $closeRe = '/^ {0,3}' . preg_quote($fenceChar, '/') . '{' . $fenceLen . ',}[ \t]*$/';
        while ($i < $n && !preg_match($closeRe, $lines[$i])) {
          $code[] = $lines[$i];
          $i++;
        }
        $i++; // saltar el cierre
        $codeStr = implode("\n", $code);
        if ($lang === 'mermaid') {
          $html .= '<pre class="mermaid">' . $this->esc($codeStr) . "</pre>\n";
        } else {
          $cls = $lang !== '' ? ' class="language-' . $this->esc($lang) . '"' : '';
          $html .= '<pre><code' . $cls . '>' . $this->esc($codeStr) . "</code></pre>\n";
        }
        continue;
      }

      // 2) Encabezado ATX (#)
      if (preg_match('/^ {0,3}(#{1,6})(?:[ \t]+(.*?))?(?:[ \t]+#+)?[ \t]*$/', $line, $m)) {
        $level = strlen($m[1]);
        $raw = isset($m[2]) ? trim($m[2]) : '';
        $html .= $this->renderHeading($level, $raw);
        $i++;
        continue;
      }

      // 3) Regla horizontal
      if (preg_match('/^ {0,3}([-*_])[ \t]*(?:\1[ \t]*){2,}$/', $line)) {
        $html .= "<hr>\n";
        $i++;
        continue;
      }

      // 4) Cita
      if (preg_match('/^ {0,3}>/', $line)) {
        $bq = [];
        while ($i < $n) {
          if (preg_match('/^ {0,3}>/', $lines[$i])) {
            $bq[] = preg_replace('/^ {0,3}> ?/', '', $lines[$i]);
            $i++;
          } elseif (trim($lines[$i]) !== '' && !$this->isBlockStart($lines[$i])) {
            $bq[] = $lines[$i]; // continuación "lazy"
            $i++;
          } else {
            break;
          }
        }
        $html .= "<blockquote>\n" . trim($this->parseBlocks($bq)) . "\n</blockquote>\n";
        continue;
      }

      // 5) Tabla (encabezado + fila separadora)
      if (
        strpos($line, '|') !== false && isset($lines[$i + 1]) &&
        preg_match('/^ {0,3}\|?[ \t]*:?-+:?[ \t]*(\|[ \t]*:?-+:?[ \t]*)*\|?[ \t]*$/', $lines[$i + 1])
      ) {
        $html .= $this->parseTable($lines, $i, $n);
        continue;
      }

      // 6) Listas (ordenadas / desordenadas)
      if (preg_match('/^ {0,3}([-*+]|\d{1,9}[.)])([ \t]+|$)/', $line)) {
        $html .= $this->parseList($lines, $i, $n);
        continue;
      }

      // 7) Bloque HTML crudo (solo si NO safe mode)
      if (!$this->safeMode && preg_match('/^ {0,3}<(\/?[a-zA-Z][\w-]*|!--)/', $line)) {
        $block = [];
        while ($i < $n && trim($lines[$i]) !== '') {
          $block[] = $lines[$i];
          $i++;
        }
        $html .= implode("\n", $block) . "\n";
        continue;
      }

      // 8) Código indentado (4 espacios)
      if (preg_match('/^ {4}/', $line)) {
        $code = [];
        while ($i < $n && (preg_match('/^ {4}/', $lines[$i]) || trim($lines[$i]) === '')) {
          $code[] = trim($lines[$i]) === '' ? '' : substr($lines[$i], 4);
          $i++;
        }
        while (!empty($code) && end($code) === '') {
          array_pop($code);
        }
        $html .= '<pre><code>' . $this->esc(implode("\n", $code)) . "</code></pre>\n";
        continue;
      }

      // 9) Párrafo (con posible encabezado Setext)
      $para = [$line];
      $i++;
      while ($i < $n) {
        $l = $lines[$i];
        if (trim($l) === '') {
          break;
        }
        if (preg_match('/^ {0,3}(=+|-+)[ \t]*$/', $l, $sm)) {
          $level = $sm[1][0] === '=' ? 1 : 2;
          $html .= $this->renderHeading($level, trim(implode("\n", $para)));
          $i++;
          $para = [];
          break;
        }
        if ($this->isBlockStart($l)) {
          break;
        }
        $para[] = $l;
        $i++;
      }
      if (!empty($para)) {
        $html .= '<p>' . $this->parseInline(trim(implode("\n", $para))) . "</p>\n";
      }
    }

    return $html;
  }

  private function renderHeading(int $level, string $raw): string
  {
    $id = $this->uniqueSlug($this->slugify($this->stripInline($raw)));
    return "<h{$level} id=\"{$id}\">" . $this->parseInline($raw) . "</h{$level}>\n";
  }

  private function parseList(array $lines, int &$i, int $n): string
  {
    preg_match('/^( {0,3})([-*+]|\d{1,9}[.)])/', $lines[$i], $head);
    $ordered = ctype_digit($head[2][0]);

    $startAttr = '';
    if ($ordered) {
      $start = (int) rtrim($head[2], '.)');
      if ($start !== 1) {
        $startAttr = ' start="' . $start . '"';
      }
    }

    $items = [];
    $loose = false;
    $pendingBlank = false;

    while ($i < $n) {
      $line = $lines[$i];

      if (trim($line) === '') {
        $pendingBlank = true;
        $i++;
        continue;
      }

      if (!preg_match('/^( {0,3})([-*+]|\d{1,9}[.)])([ \t]+|$)(.*)$/', $line, $m)) {
        break; // ni ítem ni continuación válida → fin de la lista
      }

      if (ctype_digit($m[2][0]) !== $ordered) {
        break; // cambia el tipo de lista
      }

      if ($pendingBlank && !empty($items)) {
        $loose = true;
      }
      $pendingBlank = false;

      $markerIndent = strlen($m[1]);
      $contentIndent = $markerIndent + strlen($m[2]) + ($m[3] === '' ? 1 : strlen($m[3]));

      $itemLines = [$m[4]];
      $i++;

      while ($i < $n) {
        $cl = $lines[$i];
        if (trim($cl) === '') {
          $itemLines[] = '';
          $i++;
          continue;
        }
        // ¿nuevo ítem del mismo nivel? → pertenece a la lista, no al ítem
        if (preg_match('/^ {0,' . $markerIndent . '}([-*+]|\d{1,9}[.)])([ \t]+|$)/', $cl)) {
          break;
        }
        // ¿suficientemente indentado? → continuación o anidamiento
        if (strlen($cl) - strlen(ltrim($cl)) >= $contentIndent) {
          $itemLines[] = substr($cl, $contentIndent);
          $i++;
          continue;
        }
        // continuación "lazy" de párrafo: solo si la línea anterior NO está en
        // blanco. Tras un blanco, un renglón sin sangría ya no es del ítem: cierra
        // la lista (CommonMark). Sin esto, el párrafo que sigue a una lista se
        // metía en su último ítem, pegado y sin espacio («Fusionar.Cuando…»).
        if (!$this->isBlockStart($cl) && trim((string) end($itemLines)) !== '') {
          $itemLines[] = $cl;
          $i++;
          continue;
        }
        break;
      }

      $items[] = $itemLines;
    }

    $out = $ordered ? "<ol{$startAttr}>\n" : "<ul>\n";
    foreach ($items as $itemLines) {
      while (!empty($itemLines) && trim(end($itemLines)) === '') {
        array_pop($itemLines);
      }
      $text = implode("\n", $itemLines);

      // Task list ([ ] / [x])
      $prefix = '';
      if (preg_match('/^\[([ xX])\][ \t]+(.*)$/s', $text, $tm)) {
        $checked = strtolower($tm[1]) === 'x' ? ' checked' : '';
        $prefix = '<input type="checkbox" disabled' . $checked . '> ';
        $text = $tm[2];
      }

      $inner = trim($this->parseBlocks(explode("\n", $text)));
      if (!$loose) {
        // Lista "tight": quitar el <p> de los párrafos directos (inicio/fin),
        // conservando cualquier bloque anidado (p. ej. sublistas).
        $inner = preg_replace('#^<p>(.*?)</p>\n?#s', '$1', $inner);
        $inner = preg_replace('#\n?<p>(.*?)</p>$#s', '$1', $inner);
      }
      $out .= '<li>' . $prefix . $inner . "</li>\n";
    }
    $out .= $ordered ? "</ol>\n" : "</ul>\n";

    return $out;
  }

  private function parseTable(array $lines, int &$i, int $n): string
  {
    $header = $this->splitRow($lines[$i]);
    $aligns = [];
    foreach ($this->splitRow($lines[$i + 1]) as $spec) {
      $spec = trim($spec);
      $left = strpos($spec, ':') === 0;
      $right = substr($spec, -1) === ':';
      if ($left && $right) {
        $aligns[] = 'center';
      } elseif ($right) {
        $aligns[] = 'right';
      } elseif ($left) {
        $aligns[] = 'left';
      } else {
        $aligns[] = '';
      }
    }

    $i += 2;
    $rows = [];
    while ($i < $n && trim($lines[$i]) !== '' && strpos($lines[$i], '|') !== false) {
      $rows[] = $this->splitRow($lines[$i]);
      $i++;
    }

    $alignAttr = function (int $col) use ($aligns): string {
      return !empty($aligns[$col]) ? ' style="text-align:' . $aligns[$col] . '"' : '';
    };

    $out = "<table>\n<thead>\n<tr>\n";
    foreach ($header as $col => $cell) {
      $out .= '<th' . $alignAttr($col) . '>' . $this->parseInline(trim($cell)) . "</th>\n";
    }
    $out .= "</tr>\n</thead>\n<tbody>\n";
    foreach ($rows as $row) {
      $out .= "<tr>\n";
      for ($c = 0; $c < count($header); $c++) {
        $cell = $row[$c] ?? '';
        $out .= '<td' . $alignAttr($c) . '>' . $this->parseInline(trim($cell)) . "</td>\n";
      }
      $out .= "</tr>\n";
    }
    $out .= "</tbody>\n</table>\n";

    return $out;
  }

  private function splitRow(string $row): array
  {
    $row = trim($row);
    $row = preg_replace('/^\|/', '', $row);
    $row = preg_replace('/\|[ \t]*$/', '', $row);
    // Separar por | que no estén escapados (\|)
    $cells = preg_split('/(?<!\\\\)\|/', $row);
    return array_map(function ($c) {
      return str_replace('\\|', '|', $c);
    }, $cells);
  }

  /* ===================================================================
   *  NIVEL DE LÍNEA (INLINE)
   * =================================================================== */

  private function parseInline(string $text): string
  {
    $tokens = [];
    $store = function (string $html) use (&$tokens): string {
      $tokens[] = $html;
      return "\x02" . (count($tokens) - 1) . "\x03";
    };

    // 1) Escapes con backslash
    $text = preg_replace_callback('/\\\\([\\\\`*_{}\[\]()#+\-.!~>|"\'])/', function ($m) use ($store) {
      return $store($this->esc($m[1]));
    }, $text);

    // 2) Código en línea (`code`, ``co`de``)
    $text = preg_replace_callback('/(`+)(.+?)\1/s', function ($m) use ($store) {
      $code = preg_replace('/\s+/', ' ', trim($m[2]));
      return $store('<code>' . $this->esc($code) . '</code>');
    }, $text);

    // 3) Imágenes
    $text = preg_replace_callback('/!\[([^\]]*)\]\(\s*((?:[^\s()]|\([^\s()]*\))*)(?:\s+["\']([^"\']*)["\'])?\s*\)/', function ($m) use ($store) {
      $src = $this->sanitizeUrl($m[2]);
      if ($src === '') {
        return $store($this->esc($m[0]));
      }
      $title = isset($m[3]) && $m[3] !== '' ? ' title="' . $this->esc($m[3]) . '"' : '';
      return $store('<img src="' . $src . '" alt="' . $this->esc($m[1]) . '"' . $title . '>');
    }, $text);

    // 4) Enlaces inline
    $text = preg_replace_callback('/\[((?:[^\[\]]|\[[^\]]*\])*)\]\(\s*((?:[^\s()]|\([^\s()]*\))*)(?:\s+["\']([^"\']*)["\'])?\s*\)/', function ($m) use ($store) {
      $url = $this->sanitizeUrl($m[2]);
      $title = isset($m[3]) && $m[3] !== '' ? ' title="' . $this->esc($m[3]) . '"' : '';
      return $store('<a href="' . ($url !== '' ? $url : '#') . '"' . $title . '>' . $this->parseInline($m[1]) . '</a>');
    }, $text);

    // 4b) Enlaces por referencia: [texto][id], [id][], [id]
    $text = preg_replace_callback('/\[((?:[^\[\]]|\[[^\]]*\])*)\](?:\[([^\]]*)\])?/', function ($m) use ($store) {
      $id = strtolower(trim(($m[2] ?? '') !== '' ? $m[2] : $m[1]));
      if (!isset($this->references[$id])) {
        return $m[0];
      }
      $ref = $this->references[$id];
      $url = $this->sanitizeUrl($ref['url']);
      $title = $ref['title'] !== '' ? ' title="' . $this->esc($ref['title']) . '"' : '';
      return $store('<a href="' . ($url !== '' ? $url : '#') . '"' . $title . '>' . $this->parseInline($m[1]) . '</a>');
    }, $text);

    // 5) Autolinks delimitados <...>
    $text = preg_replace_callback('/<((?:https?|ftp|mailto|tel):[^>\s]+|[^>\s@]+@[^>\s]+\.[^>\s]+)>/i', function ($m) use ($store) {
      $raw = $m[1];
      $isEmail = strpos($raw, '@') !== false && !preg_match('#^[a-z]+:#i', $raw);
      $href = $isEmail ? 'mailto:' . $raw : $this->sanitizeUrl($raw);
      if ($href === '') {
        return $store($this->esc($m[0]));
      }
      return $store('<a href="' . $this->esc($href) . '">' . $this->esc($raw) . '</a>');
    }, $text);

    // 6) HTML crudo en línea (solo si NO safe mode)
    if (!$this->safeMode) {
      $text = preg_replace_callback('/<!--.*?-->/s', function ($m) use ($store) {
        return $store($m[0]);
      }, $text);
      $text = preg_replace_callback('#</?[a-zA-Z][a-zA-Z0-9-]*(?:\s[^<>]*)?/?>#', function ($m) use ($store) {
        return $store($m[0]);
      }, $text);
    }

    // 7) Autolinks "desnudos" (GFM): URLs y emails
    $text = preg_replace_callback('#(^|[\s(])((?:https?://|www\.)[^\s<>()]+[^\s<>()\.,;:!?\'"])#i', function ($m) use ($store) {
      $href = stripos($m[2], 'www.') === 0 ? 'http://' . $m[2] : $m[2];
      $href = $this->sanitizeUrl($href);
      if ($href === '') {
        return $m[0];
      }
      return $m[1] . $store('<a href="' . $href . '">' . $this->esc($m[2]) . '</a>');
    }, $text);
    $text = preg_replace_callback('/(^|[\s(])([A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,})/', function ($m) use ($store) {
      return $m[1] . $store('<a href="mailto:' . $this->esc($m[2]) . '">' . $this->esc($m[2]) . '</a>');
    }, $text);

    // --- Texto restante: escapar, aplicar énfasis, emojis y saltos ---
    $text = $this->escText($text);
    $text = $this->parseEmphasis($text);
    $text = $this->parseEmoji($text);

    $text = preg_replace('/(?: {2,}|\\\\)\n/', "<br>\n", $text);
    if ($this->breaksEnabled) {
      $text = str_replace("\n", "<br>\n", $text);
    }

    // Restaurar tokens protegidos
    $text = preg_replace_callback('/\x02(\d+)\x03/', function ($m) use (&$tokens) {
      return $tokens[(int) $m[1]] ?? '';
    }, $text);

    return $text;
  }

  private function parseEmphasis(string $t): string
  {
    $t = preg_replace('/\*\*\*(.+?)\*\*\*/s', '<strong><em>$1</em></strong>', $t);
    $t = preg_replace('/___(.+?)___/s', '<strong><em>$1</em></strong>', $t);
    $t = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $t);
    $t = preg_replace('/(?<![A-Za-z0-9])__(.+?)__(?![A-Za-z0-9])/s', '<strong>$1</strong>', $t);
    $t = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/s', '<em>$1</em>', $t);
    $t = preg_replace('/(?<![A-Za-z0-9_])_(?!_)(.+?)(?<!_)_(?![A-Za-z0-9_])/s', '<em>$1</em>', $t);
    $t = preg_replace('/~~(.+?)~~/s', '<del>$1</del>', $t);
    return $t;
  }

  private function parseEmoji(string $t): string
  {
    return preg_replace_callback('/:([a-z0-9_+\-]+):/i', function ($m) {
      $key = strtolower($m[1]);
      return self::EMOJI[$key] ?? $m[0];
    }, $t);
  }

  /* ===================================================================
   *  UTILIDADES
   * =================================================================== */

  /** Escapa para uso en atributos y código (entidades completas). */
  private function esc(string $s): string
  {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  }

  /** Escapa texto de prosa preservando entidades HTML ya existentes. */
  private function escText(string $s): string
  {
    $s = preg_replace('/&(?![A-Za-z][A-Za-z0-9]*;|#\d+;|#[xX][0-9A-Fa-f]+;)/', '&amp;', $s);
    return str_replace(['<', '>'], ['&lt;', '&gt;'], $s);
  }

  private function expandTabs(string $s): string
  {
    return preg_replace_callback('/^[ \t]+/m', function ($m) {
      return str_replace("\t", '    ', $m[0]);
    }, $s);
  }

  /**
   * Genera un slug estilo GitHub: minúsculas, sin puntuación/emoji,
   * conserva letras Unicode (acentos), espacios → guiones.
   */
  private function slugify(string $text): string
  {
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}\s_-]+/u', '', $text);
    $text = preg_replace('/\s+/u', '-', $text);
    return trim($text, '-');
  }

  private function uniqueSlug(string $slug): string
  {
    if ($slug === '') {
      $slug = 'section';
    }
    $base = $slug;
    $k = 0;
    while (isset($this->usedSlugs[$slug])) {
      $slug = $base . '-' . (++$k);
    }
    $this->usedSlugs[$slug] = true;
    return $slug;
  }

  /** Quita marcas de Markdown inline para calcular el slug del encabezado. */
  private function stripInline(string $t): string
  {
    $t = preg_replace('/!?\[([^\]]*)\]\([^)]*\)/', '$1', $t);
    $t = preg_replace('/[*_`~]+/', '', $t);
    return $t;
  }

  /**
   * Sanea una URL: bloquea esquemas peligrosos (javascript:, data:, ...).
   * Se aplica SIEMPRE, independientemente del safe mode.
   */
  private function sanitizeUrl(string $url): string
  {
    $url = trim($url);
    if ($url === '') {
      return '';
    }
    if (preg_match('#^([a-z][a-z0-9+.\-]*)\s*:#i', $url, $m)) {
      if (!in_array(strtolower($m[1]), self::ALLOWED_SCHEMES, true)) {
        return '';
      }
    }
    return $this->esc($url);
  }

  /** Extrae y elimina las definiciones de enlaces por referencia. */
  private function extractReferences(string $md): string
  {
    $out = [];
    foreach (explode("\n", $md) as $line) {
      if (preg_match('/^ {0,3}\[([^\]]+)\]:\s*<?([^\s>]+)>?(?:\s+["\'(](.+?)["\')])?\s*$/', $line, $m)) {
        $this->references[strtolower(trim($m[1]))] = [
          'url' => $m[2],
          'title' => $m[3] ?? '',
        ];
        continue;
      }
      $out[] = $line;
    }
    return implode("\n", $out);
  }

  /** ¿La línea inicia un nuevo bloque? (para cortar párrafos/continuaciones) */
  private function isBlockStart(string $line): bool
  {
    if (preg_match('/^ {0,3}#{1,6}([ \t]|$)/', $line)) {
      return true;
    }
    if (preg_match('/^ {0,3}(`{3,}|~{3,})/', $line)) {
      return true;
    }
    if (preg_match('/^ {0,3}>/', $line)) {
      return true;
    }
    if (preg_match('/^ {0,3}([-*_])[ \t]*(?:\1[ \t]*){2,}$/', $line)) {
      return true;
    }
    if (preg_match('/^ {0,3}([-*+])[ \t]+/', $line)) {
      return true;
    }
    if (preg_match('/^ {0,3}\d{1,9}[.)][ \t]+/', $line)) {
      return true;
    }
    if (!$this->safeMode && preg_match('/^ {0,3}<(\/?[a-zA-Z][\w-]*|!--)/', $line)) {
      return true;
    }
    return false;
  }
}
