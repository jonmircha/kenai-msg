/**
 * Inicializador de Mermaid para kEnAi MSG — auto-hospedado, sin CDN.
 *
 * MarKenai genera los diagramas como <pre class="mermaid">…</pre>.
 * Este script carga ./mermaid.min.js (colócalo en esta misma carpeta:
 * assets/js/mermaid.min.js) SOLO si la página contiene diagramas, de modo
 * que la CSP puede seguir estricta (script-src 'self', sin dependencias
 * externas). Si el archivo no está presente, los diagramas se muestran como
 * texto preformateado (degradación elegante) y se avisa por consola.
 *
 * Descarga la librería una vez desde:
 *   https://github.com/mermaid-js/mermaid (dist/mermaid.min.js)
 */
(function () {
  "use strict";

  if (!document.querySelector(".mermaid")) {
    return; // No hay diagramas: no se carga nada.
  }

  // Carpeta de este propio script, para resolver mermaid.min.js junto a él.
  var self = document.currentScript && document.currentScript.src;
  var base = self ? self.slice(0, self.lastIndexOf("/") + 1) : "";

  var script = document.createElement("script");
  script.src = base + "mermaid.min.js";

  script.onload = function () {
    if (!window.mermaid) {
      return;
    }
    window.mermaid.initialize({ startOnLoad: false });
    if (typeof window.mermaid.run === "function") {
      window.mermaid.run();
    } else if (typeof window.mermaid.init === "function") {
      window.mermaid.init();
    }
  };

  script.onerror = function () {
    console.warn(
      "[kEnAi] Mermaid no encontrado en " + script.src +
      " — coloca mermaid.min.js ahí para renderizar los diagramas."
    );
  };

  document.head.appendChild(script);
})();
