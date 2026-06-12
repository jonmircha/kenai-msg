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
    <article class="page-container">
      <?php if (!empty($page['meta']['title'])): ?>
        <header class="page-header">
          <h1 class="page-title"><?= htmlspecialchars($page['meta']['title']) ?></h1>
        </header>
      <?php endif; ?>

      <div class="page-content">
        <?= $page['content'] ?>
      </div>
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