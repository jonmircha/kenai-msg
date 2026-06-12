---
layout: page
title: Categorías
description: Explora el contenido por categorías
tags: ["paginas"]
date: 2025-12-01
---

<?php
// Valor CRUDO: se usa para filtrar (getCollection). Se escapa solo al imprimir.
$currentTag = $_GET['tag'] ?? '';
?>
<?php if ($currentTag): ?>
    <!-- Vista de artículos por categoría -->
    <h2>Categoría: <em><?= htmlspecialchars($currentTag, ENT_QUOTES) ?></em></h2>
    <p>Explorando contenido etiquetado como <strong><?= htmlspecialchars($currentTag, ENT_QUOTES) ?></strong>.</p>
    <?php $posts = getCollection($currentTag, true); // true para orden descendente (más reciente primero) ?>
    <?php if (count($posts) > 0): ?>
        <ul>
            <?php foreach ($posts as $post): ?>
                <li>
                    <a href="<?= htmlspecialchars($post['url']) ?>">
                        <?= htmlspecialchars($post['data']['title']) ?>
                    </a>
                    <?php if (isset($post['data']['date'])): ?>
                        - <small><?= htmlspecialchars($post['data']['date']) ?></small>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No se encontraron artículos en esta categoría.</p>
    <?php endif; ?>
    <hr>
    <p><a href="<?= $site['url'] ?>/categorias">← Ver todas las categorías</a></p>
<?php else: ?>
    <!-- Vista de lista de categorías -->
    <p>Explora todo el contenido del sitio organizado por etiquetas.</p>
    <?php $tags = getAllTags(); ?>
    <?php if (count($tags) > 0): ?>
        <ul>
            <?php foreach ($tags as $tag => $count): ?>
                <li>
                    <a href="<?= $site['url'] ?>/categorias/<?= urlencode($tag) ?>">
                        <?= htmlspecialchars($tag) ?>
                    </a>
                    (<?= $count ?>)
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No hay categorías disponibles.</p>
    <?php endif; ?>
<?php endif; ?>