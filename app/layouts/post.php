<!DOCTYPE html>
<html lang="es">

<head>
  <?php partial('metatags', ['meta' => $page['meta'], 'page' => $page]); ?>
  <link rel="stylesheet" href="<?= $page['base'] ?>/assets/css/prism.css">
  <link rel="stylesheet" href="<?= $page['base'] ?>/assets/styles.css">
</head>

<body>
  <?php partial('header'); ?>
  <main>
    <article class="post-container">
      <header class="post-header">
        <h1 class="post-title"><?= htmlspecialchars($page['meta']['title'] ?? 'Sin Título') ?></h1>

        <div class="post-meta">
          <?php if (!empty($page['meta']['date'])): ?>
            <time datetime="<?= $page['meta']['date'] ?>">
              📅 <?= date('d/m/Y', strtotime($page['meta']['date'])) ?>
            </time>
          <?php endif; ?>

          <?php if (!empty($page['meta']['tags'])): ?>
            <span class="post-tags">
              &nbsp;| 🏷️
              <?php foreach ($page['meta']['tags'] as $tag): ?>
                <a href="<?= $page['site']['url'] ?>/categorias/<?= urlencode($tag) ?>" class="tag-pill">
                  <?= htmlspecialchars($tag) ?>
                </a>
              <?php endforeach; ?>
            </span>
          <?php endif; ?>
        </div>
      </header>
      <div class="post-content">
        <?= $page['content'] ?>
      </div>
      <footer class="post-footer">
        <nav>
          <a href="<?= $page['site']['url'] ?>/blog">← Volver al Blog</a>
        </nav>
      </footer>
    </article>
  </main>
  <?php partial('footer'); ?>
  <script src="<?= $page['base'] ?>/assets/js/prism.js"></script>
  <script src="<?= $page['base'] ?>/assets/main.js"></script>
  <?php /* Mermaid: solo si la página renderizó un diagrama real */ ?>
  <?php if (str_contains($page['content'], '<pre class="mermaid"')): ?>
    <script src="<?= $page['base'] ?>/assets/js/mermaid-init.js"></script>
  <?php endif; ?>
</body>

</html>