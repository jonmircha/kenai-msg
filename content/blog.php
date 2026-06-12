---
layout: page
title: Blog
description: Artículos del blog, paginados y ordenados del más reciente al más antiguo.
tags: ["paginas"]
date: 2025-12-01
---

<?php
// $pageNum: la inyecta el enrutador en /blog/page/N (1 por defecto en /blog).
// OJO: no usar $page, que es el contexto de la página (meta, uri, base, ...).
$pageNum = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($site['perPage'] ?? 5);
$pag = paginate(getCollection('blog', true), $pageNum, $perPage);
?>

<p><?= htmlspecialchars($page['meta']['description']) ?></p>

<?php if ($pag['total'] === 0): ?>
  <p>Aún no hay artículos publicados.</p>
<?php else: ?>
  <ol class="post-list">
    <?php foreach ($pag['items'] as $post): ?>
      <li>
        <a href="<?= htmlspecialchars($post['url']) ?>">
          <?= htmlspecialchars($post['data']['title']) ?>
        </a>
        <?php if (!empty($post['data']['date'])): ?>
          - <small><?= htmlspecialchars($post['data']['date']) ?></small>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>

  <nav class="pagination" aria-label="Paginación del blog">
    <?php if ($pag['hasPrev']): ?>
      <a class="pagination-prev"
        href="<?= htmlspecialchars($site['url'] . ($pag['prevPage'] > 1 ? '/blog/page/' . $pag['prevPage'] : '/blog')) ?>">←
        Anteriores</a>
    <?php endif; ?>

    <span class="pagination-info">Página <?= $pag['page'] ?> de <?= $pag['totalPages'] ?></span>

    <?php if ($pag['hasNext']): ?>
      <a class="pagination-next"
        href="<?= htmlspecialchars($site['url'] . '/blog/page/' . $pag['nextPage']) ?>">Siguientes →</a>
    <?php endif; ?>
  </nav>
<?php endif; ?>
