---
layout: page
title: Resultados de Búsqueda
description: Encuentra contenido en el sitio
tags: ["paginas"]
date: 2025-12-01
sitemap: false
---

<?php
$query = $_GET['q'] ?? '';
$query = htmlspecialchars($query);
?>

<?php if (empty($query)): ?>
    <p>Por favor, ingresa un término de búsqueda.</p>
<?php else: ?>
    <?php
    $results = searchContent($_GET['q']);
    $count = count($results);
    ?>
    <p>Se encontraron <strong><?= $count ?></strong> resultado(s) para: <strong>"<?= $query ?>"</strong></p>
    <?php if ($count > 0): ?>
        <div class="search-results">
            <?php foreach ($results as $result): ?>
                <article class="search-result">
                    <h3>
                        <a href="<?= htmlspecialchars($result['url']) ?>">
                            <?= htmlspecialchars($result['title']) ?>
                        </a>
                    </h3>
                    <?php if (!empty($result['excerpt'])): ?>
                        <p><?= htmlspecialchars($result['excerpt']) ?></p>
                    <?php endif; ?>
                    <p class="search-result-url">
                        <small><?= htmlspecialchars($result['url']) ?></small>
                    </p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>No se encontraron resultados. Intenta con otros términos de búsqueda.</p>
        <h3>Sugerencias:</h3>
        <ul>
            <li>Verifica la ortografía de las palabras</li>
            <li>Intenta con palabras clave más generales</li>
            <li>Intenta con diferentes términos de búsqueda</li>
        </ul>
    <?php endif; ?>
<?php endif; ?>
<p><a href="<?= $site['url'] ?>">← Volver al inicio</a></p>